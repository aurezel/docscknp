<?php

namespace app\index\service;

class PlaceStripeCheckoutPriceOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError('Token Error');

        try{
            $centerId = $params['center_id'];
            $centralIdFile = app()->getRootPath() .DIRECTORY_SEPARATOR.'file'.DIRECTORY_SEPARATOR.$centerId .'.txt';
            $productsFile = app()->getRootPath() . 'product.csv';
            if (!file_exists($centralIdFile) || !file_exists($productsFile))
            {
                throw new \Exception('中控ID文件或产品文件不存在!');
            }
            $cid = customEncrypt($centerId);
            $baseUrl = request()->domain();
            $amount = floatval($params['amount']);
            $orderId = env('stripe.merchant_token');

            //替换订单号规则
            $orderId = preg_replace_callback("|random_int(\d+)|",array(&$this, 'next_rand1'),$orderId); //数字
            $orderId = preg_replace_callback("|random_char(\d+)|",array(&$this, 'next_rand3'),$orderId);//字符串
            $orderId = preg_replace_callback("|random_letter(\d+)|",array(&$this, 'next_rand2'),$orderId);//字母
            // 获取产品价格数据
            $productDataArr = [];
            if (file_exists($productsFile))
            {
                if (($handle = fopen($productsFile, "r")) !== FALSE) {
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        $productDataArr[] = [
                            'price_id' => $data[0],
                            'amount' => floatval($data[1] ?? 0)
                        ];
                    }
                    fclose($handle);
                }
            }

            if (empty($productDataArr))
            {
                throw new \Exception('获取不到产品数据');
            }

            $priceId = $this->getPriceId($productDataArr,$amount);
            if (empty($priceId))
            {
                throw new \Exception('获取不到PriceId');
            }
            $addressData = array(
                'city' => $params['city'],
                'country' => $params['country'],
                'line1' => $params['address1'],
                'line2' => $params['address2'],
                'postal_code' => $params['zip_code'],
                'state' => $params['state']
            );
            $phone = $params['phone'];
            $customerName = $params['name'];
            $customerData = array(
                'address' => $addressData,
                'email' => $params['email'],
                'description' => $orderId,
                'name' => $customerName,
                'phone' => $phone,
                'shipping' => array(
                    'name' => $customerName,
                    'phone' => $phone,
                    'address' => $addressData
                )

            );

            header('Content-Type: application/json');
            $stripe = new \Stripe\StripeClient(env('stripe.private_key'));
            $customerResponse = $stripe->customers->create($customerData);
            if (!isset($customerResponse->id))
            {
                throw new \Exception('创建客户ID失败:'.$customerResponse);
            }

            $customerId = $customerResponse->id;
            $sPath = env('stripe.checkout_success_path');
            $cPath = env('stripe.checkout_cancel_path');
            $successPath = empty($sPath) ? '/checkout/pay/stckSuccess' : $sPath;
            $cancelPath = empty($cPath) ? '/checkout/pay/stckCancel' : $cPath;
            $checkoutPostData = [
                'line_items' => [[
                    'price' => $priceId,
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                //'payment_method_types' => ['card'],
                'customer' => $customerId,
                'success_url' => $baseUrl . $successPath .  '?cid='.$cid,
                'cancel_url' => $baseUrl . $cancelPath . '?cid='.$cid,
            ];
            $checkout_session = $stripe->checkout->sessions->create($checkoutPostData);
            if (!isset($checkout_session->id))
            {
                throw new \Exception('checkout session:'.$checkout_session);
            }
            $transactionIdFile = app()->getRootPath() . DIRECTORY_SEPARATOR . 'file' .DIRECTORY_SEPARATOR .$customerId.'.txt';
            file_put_contents($transactionIdFile,$centerId);
            return apiSuccess([
                'url' => $checkout_session->url
            ]);
        }catch (\Exception $e)
        {
            generateApiLog('Stripe Checkout Price 创建session接口异常:'.$e->getMessage() .',line:'.$e->getLine().',trace:'.$e->getTraceAsString());
            return apiError();
        }
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

    private function getPriceId($productData,$amount)
    {
        $priceId = '';
        if (!$amount) return $priceId;
        $nearPrice = self::nearPriceSearch($productData,$amount);
        foreach ($productData as $data)
        {
            if ($data['amount'] == $nearPrice)
            {
                $priceId = $data['price_id'];
                break;
            }
        }
        return $priceId;
    }

    private static function nearPriceSearch($data, $amount)
    {
        $array = array_column($data,'amount');
        sort($array);
        $nearPrice = null;
        foreach ($array as $number) {
            if ($number <= $amount) {
                if ($nearPrice === null || $number > $nearPrice) {
                    $nearPrice = $number;
                }
            }
        }
        if (empty($nearPrice)) $nearPrice = current($array);
        return $nearPrice;
    }

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