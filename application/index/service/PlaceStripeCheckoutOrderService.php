<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/7/3
 * Time: 11:03
 */

namespace app\index\service;

class PlaceStripeCheckoutOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError('Token Error');

        try{
            $centerId = $params['center_id'];
            $centralIdFile = app()->getRootPath() .DIRECTORY_SEPARATOR.'file'.DIRECTORY_SEPARATOR.$centerId .'.txt';
            if (!file_exists($centralIdFile)) die('文件不存在');
            $cid = customEncrypt($centerId);
            $baseUrl = request()->domain();
            $randomNumber = mt_rand(10000,99999);
            $currency_dec = config('parameters.currency_dec');
            $amount = floatval($params['amount']);
            $currency = strtoupper($params['currency']);
            $scale = 1;
            for($i = 0; $i < $currency_dec[$currency]; $i++) {
                $scale*=10;
            }
            $amount = bcmul($amount,$scale);

            $orderId = env('stripe.merchant_token');

            //替换订单号规则
            $orderId = preg_replace_callback("|random_int(\d+)|",array(&$this, 'next_rand1'),$orderId); //数字
            $orderId = preg_replace_callback("|random_char(\d+)|",array(&$this, 'next_rand3'),$orderId);//字符串
            $orderId = preg_replace_callback("|random_letter(\d+)|",array(&$this, 'next_rand2'),$orderId);//字母

            $productsFile = app()->getRootPath() . 'product.csv';
            $productName = 'Your items in cart';
            $description = $randomNumber . '_' . $centerId;
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
                    $productName = str_replace('product_desc',$orderId,$productName);
                    if (empty($singleProduct['description']))
                    {
                        $singleProduct['description'] = $productName;
                    }
                }
            }

            $priceData = array(
                'currency' => $currency,
                'product_data' => array(
                    'name' => $productName,
                    'description' => isset($singleProduct) ? $singleProduct['description'] : $productName,
                    'metadata' => array(
                        'order_id' => $description
                    )
                ),
                'unit_amount' => $amount,
            );

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
                'description' => $description,
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
                generateApiLog('创建客户ID失败:'.$customerResponse);
                return apiError();
            }

            $customerId = $customerResponse['id'];
            $sPath = env('stripe.checkout_success_path');
            $cPath = env('stripe.checkout_cancel_path');
            $successPath = empty($sPath) ? '/checkout/pay/stckSuccess' : $sPath;
            $cancelPath = empty($cPath) ? '/checkout/pay/stckCancel' : $cPath;
            $checkout_session = $stripe->checkout->sessions->create([
                'line_items' => [[
                    'price_data' => $priceData,
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'payment_method_types' => ['card'],
                'customer' => $customerId,
                'success_url' => $baseUrl . $successPath .  '?cid='.$cid,
                'cancel_url' => $baseUrl . $cancelPath . '?cid='.$cid,
            ]);
            if (!isset($checkout_session->id))
            {
                generateApiLog('checkout session:'.$checkout_session);
                return apiError();
            }
            $transactionIdFile = app()->getRootPath() . DIRECTORY_SEPARATOR . 'file' .DIRECTORY_SEPARATOR .$customerId.'.txt';
            file_put_contents($transactionIdFile,$centerId);
            return apiSuccess([
                'url' => $checkout_session->url
            ]);
        }catch (\Exception $e)
        {
            generateApiLog('创建session接口异常:'.$e->getMessage() .',line:'.$e->getLine().',trace:'.$e->getTraceAsString());
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