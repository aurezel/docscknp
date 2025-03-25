<?php

namespace app\index\service;

use app\index\traits\AlipaySignTool;

class PlaceAlipayOrderService extends BaseService
{
    private $merchantPrivateKey;
    private $clientId;
    private $gatewayBaseUrl;
    private $gatewaySandboxProductPath;

    public function __construct()
    {
        $this->clientId = env('stripe.public_key');
        $this->merchantPrivateKey = env('stripe.private_key');
        $this->gatewayBaseUrl = env('stripe.payment_gateway_url','https://open-sea-global.alipay.com');
        $this->gatewaySandboxProductPath = env('local_env') ? '/ams/sandbox/api' : '/ams/api';
    }
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $centralIdFile = app()->getRootPath() . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR . $params['center_id'] . '.txt';
        if (!file_exists($centralIdFile)) return apiError();
        $cid = customEncrypt($params['center_id']);
        $baseUrl = request()->domain();
        $statement = env('stripe.COMPANY');
        if (empty($statement)) $statement = explode('.', request()->rootDomain())[0];
        $centerId = intval($params['center_id']);
        $referenceId = $statement . '-' . $centerId . '-' . date('YmdHis').mt_rand(10000,99999);
        $sPath = env('stripe.checkout_success_path');
        $nPath = env('stripe.checkout_notify_path');
        $successPath = empty($sPath) ? '/checkout/pay/aliRedirect' : $sPath;
        $notifyPath = empty($nPath) ? '/checkout/pay/aliNotify' : $nPath;
        $complete_checkout_url = $baseUrl . $successPath . "?r_type=s&cid=$cid";
        $notify_checkout_url = $baseUrl . $notifyPath ."?cid=$cid";

        try {
            $cardNumber = $this->processInput(str_replace(' ', '', $params['card_number']));
            $firstName = str_replace(['-', "'", '.', '_', ','], ['', '', '', '', ''], $params['first_name']);
            $lastName = str_replace(['-', "'", '.', '_', ','], ['', '', '', '', ''], $params['last_name']);
            $fullName = $firstName . ' ' . $lastName;
            $currency_dec = config('parameters.currency_dec');
            $amount = floatval($params['amount']);
            $currency = strtoupper($params['currency']);
            $scale = 1;
            for($i = 0; $i < $currency_dec[$currency]; $i++) {
                $scale*=10;
            }
            $amount = bcmul($amount,$scale);
            $email = $params['email'];
            $currentMicroTime = round(microtime(true) * 1000);
            $addressArr = array (
                'zipCode' => $params['zip'],
                'address2' => $params['address2'],
                'city' => $params['city'],
                'address1' => $params['address1'],
                'state' => $params['state'],
                'region' => $params['country'],
            );
            $currencyAmountArr = array(
                'currency' => $currency,
                'value' => $amount,
            );
            $firstLastNameArr = array(
                'firstName' => $firstName,
                'lastName' => $lastName,
            );
            $mobileDetect = new \Detection\MobileDetect();
            $isMobileDevice = $mobileDetect->isMobile($mobileDetect->getUserAgent());
            $requestEnv = array (
                'terminalType' => $isMobileDevice ? 'WAP' : 'WEB',
                'clientIp' => $params['client_ip']
            );
            $requestData = array (
                'order' =>
                    array (
                        'orderAmount' => $currencyAmountArr,
                        'orderDescription' => 'Your item in cart',
                        'referenceOrderId' => $referenceId,
                    'env' => $requestEnv,
                        'buyer' =>
                            array (
                                'referenceBuyerId' => 'BY_'.$currentMicroTime,
                                'buyerEmail' => $email
                            ),

                    ),
                'env' => $requestEnv,
                'paymentAmount' => $currencyAmountArr,
                'settlementStrategy' => array(
                    'settlementCurrency' => $currency
                ),
                'paymentMethod' =>
                    array (
                        'paymentMethodType' => 'CARD',
                        'paymentMethodMetaData' =>
                            array (
                                //'is3DSAuthentication' => true, // for local test
                                'cvv' => self::processInput($params['cvc']),
                                'cardholderName' => $firstLastNameArr,
                                'expiryMonth' => str_pad(self::processInput($params['expiry_month']), '2', '0', STR_PAD_LEFT),
                                'billingAddress' => $addressArr,
                                'expiryYear' => self::processInput($params['expiry_year']),
                                'cardNo' => $cardNumber,
                                'enableAuthenticationUpgrade' => true,
                            ),
                    ),
                'paymentNotifyUrl' => $notify_checkout_url,
                'paymentRedirectUrl' => $complete_checkout_url,
                'paymentRequestId' => 'PR_' . $currentMicroTime,
                'productCode' => 'CASHIER_PAYMENT',
                'paymentFactor' =>
                    array (
                        'isAuthorization' => true,
                    ),
            );


            $responseData = $this->sendRequest('/v1/payments/pay',$requestData);
            $resultStatus = $responseData['result']['resultStatus'];
            $this->validateCard();
            if ($resultStatus == 'U' && isset($responseData['normalUrl']))
            {
                // for 3ds
                return apiSuccess([
                    'url' => $responseData['normalUrl']
                ]);

            }
            if ($resultStatus == 'S')
            {
                return apiSuccess();
            }else{
                $failedMsg = $responseData['result']['resultMessage'];
                $result = $this->sendDataToCentral('failed', $centerId, 0,$failedMsg);
                if (!$result)
                {
                    generateApiLog('发送中控失败:'.json_encode(['failed',$centerId,0,$failedMsg]));
                }
            }
            return apiError($failedMsg);
        } catch (\Exception $e) {
            generateApiLog('Alipay接口异常:' . $e->getMessage() . ',line:' . $e->getLine() . ',trace:' . $e->getTraceAsString());
        }
        return apiError();
    }

    private static function processInput($data): string
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return strval($data);
    }

    public function sendRequest($requestPath,$reqBody,$extendHeaders = [])
    {
        $httpMethod = 'POST';
        $requestTime = round(microtime(true) * 1000);
        $requestData = json_encode($reqBody);
        $signValue = AlipaySignTool::sign($httpMethod, $this->gatewaySandboxProductPath . $requestPath, $this->clientId, $requestTime, $requestData, $this->merchantPrivateKey);
        $baseHeaders = array();
        $baseHeaders[] = "Content-Type:application/json; charset=UTF-8";
        $baseHeaders[] = "User-Agent:global-alipay-sdk-php";
        $baseHeaders[] = "Request-Time:" . $requestTime;
        $baseHeaders[] = "client-id:" . $this->clientId;
        $signatureHeader = "algorithm=RSA256,keyVersion=1,signature=" . $signValue;
        $baseHeaders[] = "Signature:" . $signatureHeader;
        if(count($extendHeaders) > 0){
            $headers = array_merge($baseHeaders, $extendHeaders);
        } else {
            $headers = $baseHeaders;
        }
        $requestUrl = $this->gatewayBaseUrl . $this->gatewaySandboxProductPath . $requestPath;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $requestUrl);
        curl_setopt($curl, CURLOPT_FAILONERROR, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $requestData);

        generateApiLog('Request Data:'.json_encode(
            [
            'url' => $requestUrl,
                'headers' => $headers,
                'request_data' => json_decode($requestData,true)
            ],JSON_UNESCAPED_SLASHES));

        $rspContent = curl_exec($curl);
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) != '200') {
            return null;
        }
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $rspBody = substr($rspContent, $headerSize);
        generateApiLog('response data:'.$rspBody);
        curl_close($curl);
        $alipayRsp = json_decode($rspBody,true);
        if(!isset($alipayRsp['result'])){
            throw new \Exception("Response data error,result field is null,rspBody:" . $rspBody);
        }
        return $alipayRsp;
    }
    public function sendDataToCentral($status, $center_id, $payment_id = 0, $msg = '')
    {
        if (!in_array($status, ['success', 'failed'])) return false;
        // 发送到中控
        $postCenterData = [
            'transaction_id' => $payment_id,
            'center_id' => $center_id,
            'action' => 'create',
            'status' => $status,
            'failed_reason' => $msg
        ];
        $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL, $postCenterData, CURL_HEADER_DATA), true);
        if (!isset($sendResult['status']) or $sendResult['status'] == 0) {
            generateApiLog(REFERER_URL . '创建订单传送信息到中控失败：' . json_encode($sendResult));
            return false;
        }
        return true;
    }
}