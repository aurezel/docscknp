<?php

namespace app\index\service;

class PlaceAlipayCaptureOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $centralIdFile = app()->getRootPath() . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR . $params['center_id'] . '.txt';
        if (!file_exists($centralIdFile)) return apiError();
        $cid = customEncrypt($params['center_id']);
        $baseUrl = request()->domain();
        $statement = env('stripe.COMPANY');
        $rootDomain = request()->rootDomain();
        if (empty($statement)) $statement = explode('.', $rootDomain)[0];
        $centerId = intval($params['center_id']);
        $referenceId = $statement . '-' . $centerId . '-' . date('YmdHis').mt_rand(10000,99999);
        $sPath = env('stripe.checkout_success_path');
        $nPath = env('stripe.checkout_notify_path');
        $successPath = empty($sPath) ? '/checkout/pay/aliRedirect' : $sPath;
        $notifyPath = empty($nPath) ? '/checkout/pay/aliNotify' : $nPath;
        $complete_checkout_url = $baseUrl . $successPath . "?r_type=s&cid=$cid";
        $notify_checkout_url = $baseUrl . $notifyPath ."?cid=$cid";

        try {
            $fileData = json_decode(file_get_contents($centralIdFile),true);
            if (!isset($fileData['f_url'])) return apiError();
            $aSiteDomain = parse_url($fileData['f_url'])['host'];
            if ($aSiteDomain == $rootDomain)
            {
                $referenceId = $params['order_no'];
                $fileData['from_b_site'] = true;
                file_put_contents($centralIdFile,json_encode($fileData));
            }
            $cardNumber = $this->processInput(str_replace(' ', '', $params['card_number']));
            $cardStart = $cardNumber[0];
            $cardSuffix = substr($cardNumber,0,6);
            if (in_array($cardSuffix,['467309', '436375', '490632']) || ($cardStart != 4 && $cardStart != 5))
            {
                return apiError('Card Declined.');
            }
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
                        'captureMode' => 'MANUAL'
                    ),
            );


            $responseData = app('alipay')->sendRequest('/v1/payments/pay',$requestData);
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
                $result = app('alipay')->sendDataToCentral('failed', $centerId, 0,$failedMsg);
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
}