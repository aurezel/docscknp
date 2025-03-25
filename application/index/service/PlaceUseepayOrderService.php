<?php

namespace app\index\service;
use app\index\traits\USeePayTool;
class PlaceUseepayOrderService extends BaseService
{
    use USeePayTool;
    private $merchantPrivateKey;
    private $merchantPublicKey;
    private $gatewayBaseUrl;
    private $gatewaySandboxProductPath;

    public function __construct()
    {
        $this->merchantPublicKey = env('stripe.public_key');
        $this->merchantPrivateKey = env('stripe.private_key');
        $this->gatewayBaseUrl = env('local_env') ? 'https://pay-gateway1.uat.useepay.com' : 'https://pay-gateway.useepay.com';
    }
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $centralIdFile = app()->getRootPath() . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR . $params['center_id'] . '.txt';
        $productsFile = app()->getRootPath() . 'product.csv';
        if (!file_exists($centralIdFile) || !file_exists($productsFile)) return apiError();
        $centerId = $params['center_id'];
        $cid = customEncrypt($centerId);
        $baseUrl = request()->domain();
        $sPath = env('stripe.checkout_success_path');
        $nPath = env('stripe.checkout_notify_path');
        $successPath = empty($sPath) ? '/checkout/pay/uSeeRedirect' : $sPath;
        $notifyPath = empty($nPath) ? '/checkout/pay/uSeeNotify' : $nPath;
        $completeCheckoutUrl = $baseUrl . $successPath . "?r_type=s&cid=$cid";
        $notifyCheckoutUrl = $baseUrl . $notifyPath;
        $threeDSRedirect = $baseUrl . $successPath . "?r_type=t&cid=$cid";

        try {
            $email = $params['email'];
            $orderId = $params['order_no'];
            $currency_dec = config('parameters.currency_dec');
            $amount = floatval($params['amount']);
            $currency = strtoupper($params['currency']);
            $scale = 1;
            for($i = 0; $i < $currency_dec[$currency]; $i++) {
                $scale*=10;
            }
            $amount = bcmul($amount,$scale);
            // 产品信息
            $goodsInfoArr = array();
            if (($handle = fopen($productsFile, "r")) !== FALSE) {
                while (($data = fgetcsv($handle)) !== FALSE) {
                    $name = $data[1];
                    $goodsInfoArr[] = [
                        'id' => $data[0],
                        'name' => $name,
                        'body' => $name,
                        'quantity' => 1,
                        'price' => $amount,
                    ];
                }
                fclose($handle);
            }
            $goodsInfoArrCount = count($goodsInfoArr);
            if ($goodsInfoArrCount > 0)
            {
                $goodsInfo = $goodsInfoArr[mt_rand(0,$goodsInfoArrCount -1)];
            }else{
                generateApiLog('产品数据为空');
                return apiError();
            }

            $address1 = $params['address1'];
            $billingAddress = array (
                'houseNo' => mt_rand(100,999),
                'email' => $email,
                'phoneNo' => $params['phone'],
                'firstName' => $params['first_name'],
                'lastName' => $params['last_name'],
                'stlogreet' => $address1,
                'postalCode' => $params['zip'],
                'city' => $params["city"],
                'state' => empty($params['state']) ? 'None' : $params['state'],
                'country' => $params["country"],
                'street' => $address1,
            );

            $orderInfo['subject'] = '#'.$orderId;
            $orderInfo['shippingAddress'] = $billingAddress;
            $orderInfo['goodsInfo'] = $goodsInfo;
            $appId = request()->rootDomain();
            // $appId = 'ferrdinand.com';
            $requestData = [
                'transactionType' => 'pay', // 交易类型 pay 或 authorization
                'autoRedirect' => 'false', //正常请求时是否有值？
                'terminalType' => USeePayTool::getTerminalType(),
                'version' => '1.0', // 版本
                'signType' => 'MD5',  // 签名类型
                'merchantNo' => $this->merchantPublicKey,  //  商户号
                'transactionId' => 'RAY_'.round(microtime(true) * 1000).mt_rand(10000,99999),    // 交易流水号
                'transactionExpirationTime' => 10080, //  订单有效时长(分钟)7天
                'appId' => $appId,    //  交易网站域名
                'amount' => $amount, // 订单交易金额
                'currency' => $currency, // 订单交易币种
                'notifyUrl' => $notifyCheckoutUrl,  //  异步通知地址
                'redirectUrl' => $completeCheckoutUrl,    // 回调地址
                'echoParam' => $cid,   // 回声参数
                'reserved' => 'reserved',   // 保留字段
                // 付款人信息
                'payerInfo' => json_encode(array (
                    'paymentMethod' => 'credit_card',
                    'authorizationMethod' => 'cvv',
                    'threeDS2RequestData' =>
                        array (
                            'deviceChannel' => 'browser',
                            'acceptHeader' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
                            'colorDepth' => $params['colorDepth'],
                            'javaEnabled' => $params['javaEnabled'],
                            'language' => $params['language'],
                            'screenHeight' => $params['screenHeight'],
                            'screenWidth' => $params['screenWidth'],
                            'timeZoneOffset' => $params['timeZoneOffset'],
                            'userAgent' => $_SERVER['HTTP_USER_AGENT'],
                            'threeDSMethodCallbackUrl' => $threeDSRedirect,
                        ),
                    'billingAddress' => $billingAddress,
                )),

                // 订单信息
                'orderInfo' => json_encode(array(
                    'subject' => 'NO.'.$orderId,
                    'shippingAddress' => $billingAddress,
                    'orderInfo' => $orderInfo
                )),
                // 用户信息
                'userInfo' => json_encode(array (
                    'userId' => md5($orderId),
                    'ip' => $params['client_ip'],
                    'email' => $email,
                )),
            ];

            $requestData['sign'] = USeePayTool::md5Sign($requestData,$this->merchantPrivateKey);
            $responseData = USeePayTool::submitWithReturn($this->gatewayBaseUrl . '/cashier',$requestData);
            if ($responseData['errorCode'] === '0000')
            {
                if (isset($responseData['token'])) return apiSuccess(['token' => $responseData['token']]);
                generateApiLog('USeePay响应Token不存在!');
                return apiError();
            }
            $errorMsg = $responseData['errorMsg'];
            $this->sendDataToCentral('failed',$centerId,0,$errorMsg);
            return apiError($errorMsg);
        } catch (\Exception $e)
        {
            generateApiLog('USeePay接口异常:'.$e->getMessage()."Line:".$e->getLine());
        }
        return apiError();
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