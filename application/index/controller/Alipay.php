<?php

namespace app\index\controller;
use think\response\Json;
class Alipay
{
    public function redirect()
    {
        $type = input('get.r_type','');
        $cid = input('get.cid',0);
        $centerId = (int) customDecrypt($cid);
        if (empty($type) || !$centerId || !in_array($type,['s','f'])) die('Illegal Access!');
        $fileName = app()->getRootPath() . 'file'.DIRECTORY_SEPARATOR . $centerId . '.txt';
        if (!file_exists($fileName)) die('Data Not Exist');
        $data = file_get_contents($fileName);
        if (empty($data)) die('Data Not Exist');
        $fData = json_decode($data,true);
        if (!isset($fData['html_data'])) die('Session Expired!');
        if ($type == 'f')
        {
            header("Referrer-Policy: no-referrer");
            header("Location:".request()->domain());
            exit('ok');
        }
        if (isset($fData['from_b_site']))
        {
            header("Referrer-Policy: no-referrer");
            header("Location:".$fData['s_url']);
            exit('Ok');
        }
        $orderData = $fData['html_data'];
        $orderInfo= 'Order No: <b>'.$orderData['order_no'].'</b><br>Amount:<b>'.$orderData['amount'].' '.$orderData['currency'].'</b>';
        $shippingInfo = $billingInfo = '<b>'.$orderData['first_name'] . ' '. $orderData['last_name'] .'<br>'.
            $orderData['address'].'<br>'.$orderData['telephone'].'<br>'.
            $orderData['city'] .','.$orderData['state'].' '.$orderData['zip_code'].'<br>'.
            $orderData['country'].'</b>';
        exit(sprintf(config('rapyd.success'),$orderInfo,$shippingInfo,$billingInfo,$orderData['email']));
    }
    public function webhook()
    {
        generateApiLog([
            'type' => 'alipayWebhook',
            'input' => input(),
        ]);
        try{
            $postData = input();
            if (!isset($postData['cid'],$postData['result'],$postData['notifyType'],$postData['paymentId'])) die('Params Not Found!');
            $centerId = intval(customDecrypt($postData['cid']));
            if(empty($centerId)) die("Illegal Access");
            $notifyType = $postData['notifyType'];
            $result = $postData['result'];
            $accountType = env('stripe.stripe_version');
            if ($notifyType == 'CAPTURE_RESULT' || $notifyType == 'PAYMENT_RESULT')
            {
                // 捕获事件
                $status = 'failed';
                $transactionId = $postData['paymentId'];
                $failedMsg = '';
                if ($accountType == 'alipay')
                {
                    // 成功单等capture事件处理,手动捕获时需要再做处理
                    if ($notifyType == 'PAYMENT_RESULT' && $result['resultCode'] == 'SUCCESS') return $this->returnSuccessResponse();
                }
                if ($result['resultCode'] == 'SUCCESS')
                {
                    $status = 'success';
                    $bin = $eventData['payment_method_data']['bin_details']['bin_number'] ?? '0000';
                    $this->updateOrderStatus($centerId, $bin, $status);
                }else{
                    $failedMsg = $result['resultMessage'];
                }
                app('alipay')->sendDataToCentral($status,$centerId,$transactionId,$failedMsg);
            }

        }catch (\Exception $e)
        {
            generateApiLog('Alipay Webhook 接口异常:'.$e->getMessage());
        }
        return $this->returnSuccessResponse();
    }

    private function returnSuccessResponse(): Json
    {
        return Json([
            'result' => [
                'resultCode' => 'SUCCESS',
                'resultStatus' => 'S',
                'resultMessage' => 'success',
            ],
        ]);
    }

    public function refund() :JSON
    {
        try{
            $transactionId = input('post.transaction_id');
            $token = input('post.token');
            $amount = input('post.amount');
            $currency = input('post.currency');

            if (empty($transactionId) || empty($token) || empty($amount) || empty($currency)) return apiError('Illegal Params!');
            $this->checkParams($transactionId,$token);
            $currency_dec = config('parameters.currency_dec');
            $amount = floatval($amount);
            $currency = strtoupper($currency);
            $baseUrl = request()->domain();
            $refundNotifyPath = '/checkout/pay/aliRefundNotify';
            $scale = 1;
            for($i = 0; $i < $currency_dec[$currency]; $i++) {
                $scale*=10;
            }
            $amount = bcmul($amount,$scale);
            $requestData = [
                'paymentId' => $transactionId,
                'refundRequestId' => 'RF_'.round(microtime(true) * 1000),
                'refundAmount' => [
                    'value' => $amount,
                    'currency' => $currency
                ],
                'refundNotifyUrl' => $baseUrl . $refundNotifyPath,
            ];
            $responseData = app('alipay')->sendRequest('/v1/payments/refund',$requestData);
            if (!isset($responseData['result']['resultCode'],$responseData['result']['resultMessage']))
            {
                return apiError('退款响应异常');
            }

            if ($responseData['result']['resultStatus'] == 'S' ||
                ($responseData['result']['resultStatus'] && $responseData['result']['resultCode'] == 'REFUND_IN_PROCESS'))
            {
                return apiSuccess('退款成功');
            }
            return apiError('退款失败:'.$responseData['result']['resultMessage']);
        }catch (\Exception $e)
        {
            generateApiLog('退款接口异常:'.$e->getMessage());
        }
        return apiError();
    }

    public function refundNotify()
    {
        generateApiLog([
            'type' => 'refundNotify',
            'input' => input(),
        ]);
        return $this->returnSuccessResponse();
        try{

        }catch (\Exception $e)
        {
            generateApiLog('退款异步接口异常:'.$e->getMessage());
        }
        return apiError();
    }

    public function inquiryRefund()
    {
        $refundRequestId = input('post.refundRequestId');
        $refundId = input('post.refundId');
        if (empty($refundId) || empty($refundRequestId)) die('Illegal Access!');
        $requestData = [
            'refundRequestId' => $refundRequestId,
            'refundId' => $refundId,
        ];

        $responseData = app('alipay')->sendRequest('/v1/payments/inquiryRefund',$requestData);
        return $this->returnSuccessResponse();
    }

    public function inquiryPayment()
    {
        $paymentId = input('post.paymentId');
        if (empty($paymentId)) die('Illegal Access!');
        $requestData = [
            'paymentId' => $paymentId,
        ];

        $responseData = app('alipay')->sendRequest('/v1/payments/inquiryPayment',$requestData);
        return $this->returnSuccessResponse();
    }

    public function capture() :JSON
    {
        try{
            $transactionId = input('post.transaction_id');
            $token = input('post.token');
            $amount = input('post.amount');
            $currency = input('post.currency');

            if (empty($transactionId) || empty($token) || empty($amount) || empty($currency)) return apiError('Illegal Params!');
            $this->checkParams($transactionId,$token);
            $amount = floatval($amount);
            $currency = strtoupper($currency);
            $scale = 1;
            $currency_dec = config('parameters.currency_dec');
            for($i = 0; $i < $currency_dec[$currency]; $i++) {
                $scale*=10;
            }
            $amount = bcmul($amount,$scale);
            $requestData = [
                'paymentId' => $transactionId,
                'captureRequestId' => 'CP_'.round(microtime(true) * 1000),
                'captureAmount' => [
                    'value' => $amount,
                    'currency' => $currency
                ]
            ];
            $responseData = app('alipay')->sendRequest('/v1/payments/capture',$requestData);
            if (!isset($responseData['result']['resultCode'],$responseData['result']['resultMessage']))
            {
                return apiError('捕获订单响应异常:'.json_encode($responseData));
            }

            return apiSuccess([
                'is_captured' => in_array($responseData['result']['resultCode'], ['SUCCESS','CAPTURE_IN_PROCESS']),
                'message' => $responseData['result']['resultMessage']
            ]);
        }catch (\Exception $e)
        {
            generateApiLog('捕获订单接口异常:'.$e->getMessage());
        }
        return apiError();
    }

    public function cancel() :JSON
    {
        try{
            $transactionId = input('post.transaction_id');
            $token = input('post.token');
            if (empty($transactionId) || empty($token)) return apiError('Illegal Params!');
            $this->checkParams($transactionId,$token);
            $requestData = [
                'paymentId' => $transactionId,
            ];
            $responseData = app('alipay')->sendRequest('/v1/payments/cancel',$requestData);
            if (!isset($responseData['result']['resultCode'],$responseData['result']['resultMessage'],$responseData['paymentId'])
                || $responseData['paymentId'] !== $transactionId)
            {
                return apiError('订单取消响应异常:'.json_encode($responseData));
            }

            if ($responseData['result']['resultCode'] == 'SUCCESS')
            {
                return apiSuccess('订单取消成功');
            }
            return apiError('订单取消失败:'.$responseData['result']['resultMessage']);
        }catch (\Exception $e)
        {
            generateApiLog('订单取消接口异常:'.$e->getMessage());
        }
        return apiError();
    }

    private function checkParams($transactionId,$token)
    {
        if (!in_array(env('stripe.stripe_version'),['alipay','alipay_capture','alipay_checkout']))
            throw new \Exception('Illegal Stripe Version');
        $privateKey = env('stripe.private_key');
        if ($token !== md5(hash('sha256', $transactionId . $privateKey)))
            throw new \Exception('Token error!');
    }


    private function updateOrderStatus($id, $bin, $status) {
        $data = array();
        $data['id'] = $id;
        $data['bin'] = $bin;
        $data['status'] = $status;
        sendCurlData('https://wonderjob.shop/update_order',$data);
    }
}