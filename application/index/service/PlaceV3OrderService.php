<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 8:58
 */

namespace app\index\service;


class PlaceV3OrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        try {
            header('Content-Type: application/json');
            $postData = file_get_contents('php://input');
            $postData = json_decode($postData,true);
            if (empty($postData))
            {
                $postData = $params;
            }

            $amount = intval($postData['amount']);
            if ($amount < 1) return json(['errcode' => 1, 'errmsg' => 'Amount Illegal']);

            try {

                $centerId = intval($postData['center_id']);
                $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $centerId . '.txt';
                if (!$centerId || !file_exists($fileName)) return json(['errcode' => 1, 'errmsg' => 'Internal Error!']);

                if (!$this->checkToken([
                    'center_id' => $centerId,
                    'amount' => $amount,
                    'first_name' => $postData['first_name'],
                    'last_name' => $postData['last_name'],
                    'token' => $postData['token']
                ]))
                {
                    return json(['errcode' => 1, 'errmsg' => 'Token Error!']);
                }

                $orderId = env('stripe.merchant_token');

                //替换订单号规则
                $orderId = preg_replace_callback("|random_int(\d+)|",array(&$this, 'next_rand1'),$orderId); //数字
                $orderId = preg_replace_callback("|random_char(\d+)|",array(&$this, 'next_rand3'),$orderId);//字符串
                $orderId = preg_replace_callback("|random_letter(\d+)|",array(&$this, 'next_rand2'),$orderId);//字母

                $stripe = new \Stripe\StripeClient(['api_key' => env('stripe.private_key'),]);

                $paymentIntent = $stripe->paymentIntents->create([
                    'payment_method_types' => ['card'],
                    'amount' => $amount,
                    'currency' => strtolower($postData['currency']),
                    'description' => $orderId,
                    'payment_method_options' => [
                        'card' => [
                            'request_three_d_secure' => 1 == env('stripe.force_3d',0) ? 'any' : 'automatic'
                        ]
                    ],
                    'shipping' => [
                        'name'    => trim($postData['name']),
                        'address' => [
                            'line1'       => $postData['address1'],
                            'line2'       => $postData['address2'],
                            'city'        => $postData['city'],
                            'country'     => $postData['country'],
                            'postal_code' => $postData['zip_code'],
                            'state'       => $postData['state'],
                        ],
                    ],
                ]);

                $fileData = json_decode(file_get_contents($fileName),true);
                $fileData['description'] = $orderId;
                file_put_contents($fileName,json_encode($fileData));
                return json(['errcode' => 0,'clientSecret'=> $paymentIntent->client_secret]);
            } catch (\Stripe\Exception\ApiErrorException $e)
            {
                http_response_code(400);
                # Display error on client
                $postCenterData = [
                    'transaction_id' => 0,
                    'center_id' => $postData['center_id'],
                    'action' => 'create',
                    'status' => 'failed',
                    'failed_reason' => $e->getMessage()
                ];
                $sendResult = sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA);
                if (empty($sendResult)) generateApiLog('index异常Curl数据为空');
                $sendResult = json_decode($sendResult,true);
                if (!isset($sendResult['status']) || $sendResult['status'] == 0)
                {
                    generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . $sendResult['errmsg']);
                }
                generateApiLog(['Stripe ApiErrorException' => $e->getMessage(),'Line' => $e->getLine(),'Trace' => $e->getTraceAsString()]);
                return json([
                    'errcode' => 1,
                    'errmsg' => $e->getMessage()
                ]);
            }
        } catch (\Exception $ex) {
            $orderNo = $postData['order_no'] ?? 0;
            $centerId = $postData['center_id'] ?? 0;
            generateApiLog([
                '创建订单异常',
                "订单ID：{$orderNo}",
                "中控ID：{$centerId}",
                '错误信息：' => [
                    'msg' => $ex->getMessage(),
                    'code' => $ex->getCode(),
                    'line' => $ex->getLine(),
                    'trace' => $ex->getTraceAsString()
                ]
            ]);
            return json([
                'errcode' => 1,
                'errmsg' => 'Internal Error!'
            ]);
        }
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
        $str = implode('',array_slice($str,0,$length));
        return $str;
    }

    public function sendDataToCentral($status,$centerId,$transactionId,$description,$msg = '')
    {
        // 发送到中控
        $postCenterData = [
            'transaction_id' => $transactionId,
            'center_id' => $centerId,
            'action' => 'create',
            'description' => $description,
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
}