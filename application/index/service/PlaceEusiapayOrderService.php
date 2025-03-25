<?php

namespace app\index\service;

class PlaceEusiapayOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError('Token Error');

        try{
            $cid = customEncrypt($params['center_id']);
            $baseUrl = request()->domain();
            $randomNumber = mt_rand(10000,99999);
            $gatewayUrl = 'https://app.eusiapay.com/gateway/MultipleInterface';
            $amount = floatval($params['amount']);
            $currency = strtoupper($params['currency']);

            $merchantNo = env('stripe.merchant_token');
            $gatewayNo = env('stripe.public_key');
            $firstName = $params['first_name'];
            $lastName = $params['last_name'];
            $country = $params['country'];
            $state = $params['state'];
            $city = $params['city'];
            $zipCode = $params['zip_code'];
            $phone = $params['phone'];
            $address = empty($params['address2']) ? $params['address1'] : $params['address1'] . ' ' . $params['address2'];
            $email = $params['email'];

            $orderNo = $params['order_no'];
            $sPath = env('stripe.checkout_success_path');
            $nPath = env('stripe.checkout_notify_path');
            $returnPath = empty($sPath) ? '/checkout/pay/eusReturn' : $sPath;
            $notifyPath = empty($nPath) ? '/checkout/pay/eusNotify' : $nPath;
            $returnUrl = $baseUrl . $returnPath .  '?cid='.$cid;

            $productsFile = app()->getRootPath() . 'product.csv';
            $productName = 'Your items in cart';
            if (file_exists($productsFile))
            {
                $productNameData = array();
                if (($handle = fopen($productsFile, "r")) !== FALSE) {
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        $productNameData[] = [
                            'product_name' => $data[0],
                            'description' => $data[1] ?? ''
                        ];
                    }
                    fclose($handle);
                }
                $productNameCount = count($productNameData);
                if ($productNameCount > 0)
                {
                    $singleProduct = $productNameData[mt_rand(0,$productNameCount -1)];
                    $productName = $singleProduct['product_name'];
                    $productName = preg_replace_callback("|random_int(\d+)|",array(&$this, 'next_rand1'),$productName); //数字
                    $productName = preg_replace_callback("|random_char(\d+)|",array(&$this, 'next_rand3'),$productName);//字符串
                    $productName = preg_replace_callback("|random_letter(\d+)|",array(&$this, 'next_rand2'),$productName);//字母
                    $productName = str_replace('product_desc',$orderNo,$productName);
                    if (empty($singleProduct['description']))
                    {
                        $singleProduct['description'] = $productName;
                    }
                }
            }

            $privateKy = env('stripe.private_key');
            $signData = $merchantNo.$gatewayNo.$orderNo.$currency.$amount.$returnUrl.$privateKy;
            $requestData = [
                'merNo' => $merchantNo,
                'gatewayNo' => $gatewayNo,
                'orderNo' => $orderNo,
                'orderAmount' => $amount,
                'orderCurrency' => $currency,
                'signInfo' => strtoupper(hash('sha256',$signData)),
                'paymentMethod' => 'Credit Card',
                'returnUrl' => $returnUrl,
                'notifyUrl' => $baseUrl . $notifyPath .  '?cid='.$cid,
                'website' => $baseUrl,
                'firstName' => $firstName,
                'lastName' => $lastName,
                'email' => $email,
                'phone' => $phone,
                'goodsInfo' => (isset($singleProduct) ? $singleProduct['description'] : $productName) . '#,#' .$randomNumber . '#,#' . $amount . '#,#'.'1',
                'country' => $country,
                'state' => $state,
                'city' => $city,
                'address' => $address,
                'zip' => $zipCode,
                'shipFirstName' => $firstName,
                'shipLastName' => $lastName,
                'shipEmail' => $email,
                'shipPhone' => $phone,
                'shipCountry' => $country,
                'shipState' => $state,
                'shipCity' => $city,
                'shipAddress' =>$address,
                'shipZip' => $zipCode,
                'remark' => $cid
            ];

            $responseObj = $this->requestApi($gatewayUrl,$requestData);
            $url = $responseObj->paymentUrl;
            if (false === strpos($url,'https://')) $url = 'https://'.$url;
            return apiSuccess([
                'url' =>$url
            ]);
        }catch (\Exception $e)
        {
            generateApiLog('创建Eusipay支付接口异常:'.$e->getMessage() .',line:'.$e->getLine().',trace:'.$e->getTraceAsString());
            return apiError();
        }
    }

    private function requestApi($url,$requestData)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($requestData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_TIMEOUT,45);
        $headers = [
            'Content-Type:application/x-www-form-urlencoded',
            "user-agent: Mozilla/5.0 (iPhone; CPU iPhone OS 16_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.6 Mobile/15E148 Safari/604.1"
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        generateApiLog('response:'.$response);
        if (curl_errno($ch))
        {
            throw new \Exception('CURL异常:'.curl_error($ch));
        }
        $responseObj = json_decode($response);
        if (!$responseObj || '' == $responseObj->paymentUrl)
        {
            throw new \Exception('结果响应失败:'.json_encode($responseObj));
        }
        curl_close ($ch);
        return $responseObj;
    }

    public function sendDataToCentral($status,$centerId,$transactionId,$msg = '')
    {
        // 发送到中控
        $postCenterData = [
            'transaction_id' => $transactionId,
            'center_id' => $centerId,
            'action' => 'create',
            'status' => $status,
            'failed_reason' => $msg
        ];
        $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
        if (!isset($sendResult['status']) or $sendResult['status'] == 0)
        {
            generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
            return false;
        }
        return ['success_risky' => $sendResult['data']['success_risky'],'redirect_url' => $sendResult['data']['redirect_url'] ?? ''];
    }

    //数字
    public function next_rand1($matches)
    {
        return $this->randnum($matches[1]);
    }

    //字母
    public function next_rand2($matches)
    {
        return $this->randzimu($matches[1]);
    }

    //字符串
    public function next_rand3($matches)
    {
        return $this->randomkeys($matches[1]);
    }

    //生成随机数字
    public function randnum($length){
        $string ='';
        for($i = 1; $i <= $length; $i++){
            $string.=rand(0,9);
        }

        return $string;

    }

    //生成随机字母
    public function randzimu($length){
        $str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';//62个字符
        $strlen = 62;
        while($length > $strlen){
            $str .= $str;
            $strlen += 62;
        }
        $str = str_shuffle($str);
        return substr($str,0,$length);
    }

    //生成随机字符串
    public function randomkeys($length)
    {
        $str = array_merge(range(0,9),range('a','z'),range('A','Z'));
        shuffle($str);
        return implode('',array_slice($str,0,$length));
    }
}