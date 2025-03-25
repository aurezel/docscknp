<?php

namespace app\index\service;
use app\index\traits\USeePayTool;
class PlaceUseepayApiOrderService extends BaseService
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
        if (!$this->checkToken($params) || !isset($params['card_number'],$params['cvc'],$params['expiry_month'],$params['expiry_year']))
            return apiError();

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
            $firstName = $params['first_name'];
            $lastName = $params['last_name'];
            $billingAddress = array (
                'houseNo' => mt_rand(100,999),
                'email' => $email,
                'phoneNo' => $params['phone'],
                'firstName' => $firstName,
                'lastName' => $lastName,
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
            //$appId = 'ferrdinand.com';
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
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'cardNo' => self::processInput(str_replace(' ','',$params['card_number'])),
                    'expirationMonth' => str_pad(self::processInput($params['expiry_month']), '2', '0', STR_PAD_LEFT),
                    'expirationYear' => self::processInput($params['expiry_year']),
                    'cvv' => $params['cvc'],
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
                            'challengeWindowSize' => '390x400',
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
            $responseData = USeePayTool::submitWithReturn($this->gatewayBaseUrl . '/api',$requestData);
            $responseCode = $responseData['resultCode'];
            if ($responseCode === 'succeed')
            {
                $this->validateCard(true);
                return apiSuccess();
            }elseif ($responseCode == 'gather' || $responseCode == 'challenge')
            {
                $threeDForm = $this->generateThreeDSForm($responseData);
                generateApiLog('generated form:'.$threeDForm);
                return apiSuccess([
                    'threeDSServerTransId' => $responseData['threeDSServerTransId'],
                    'threeDForm' => $threeDForm,
                    'type' => $responseCode
                ]);
            }
            $this->validateCard();
            return apiError($responseData['errorMsg']);
        } catch (\Exception $e)
        {
            generateApiLog('USeePay接口异常:'.$e->getMessage()."Line:".$e->getLine());
        }
        return apiError();
    }

    private static function processInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return strval($data);
    }

    public function generateThreeDSForm($responseData): string
    {
        $responseRedirectUrl = $responseData['redirectUrl'];
        $method = $responseData['redirectMethod'];
        $redirectParam = json_decode($responseData['redirectParam'] ?? '{}',true);
        $form = "<iframe  name='threeDFrame' margin='0' width='100%;'  height='100%' style='height: 400px;' frameborder='no' border='0' marginwidth='0' marginheight='0' scrolling='no' allowtransparency='yes'   id='threeDFrame'></iframe>";
        if ('GET' == $method)
        {
            $form = "<iframe  name='threeDFrame' src='$responseRedirectUrl' margin='0' width='100%;'  height='100%' style='height: 400px;' frameborder='no' border='0' marginwidth='0' marginheight='0' scrolling='no' allowtransparency='yes'   id='threeDFrame'></iframe>";
        }
        if (!empty($redirectParam))
        {
            $form .= "<form target='threeDFrame' action='$responseRedirectUrl' id='threeDForm' method='$method'>";
            foreach ($redirectParam as $name => $value)
            {
                $form .= "<input type='hidden' name ='$name' value='$value'>";
            }
            $form .= '</form>';
        }
        $script = '';
        if ('POST' === $method)
        {
            $script .= '<script type="text/javascript">';
            $script .= 'document.getElementById("threeDForm").submit();';
            $script .= '</script>';
        }
        $form .= $script;
        return $form;
    }
}