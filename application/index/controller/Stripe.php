<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/9/1
 * Time: 15:53
 */

namespace app\index\controller;

class Stripe
{
    private $fData = [];
    public function payReturn()
    {
        generateApiLog([
            'type' => 'success',
            'input' => input(),
        ]);
        $params = input();
        if (!isset($params['payment_intent'],$params['cid'])) return apiError();
        if (isset($params['source_redirect_slug'])) return apiError();
        try{
            $stripe = new \Stripe\StripeClient(['api_key' => env('stripe.private_key'),]);
            $paymentIntent =  $stripe->paymentIntents->retrieve(
                $params['payment_intent']
            );

            $isIllegal = $this->getFileData();
            if (!$isIllegal) return apiError();
            $centerId = intval($this->fData['center_id']);
            $msg =  '';

            $paymentStatus = $paymentIntent->status;
            if ($paymentStatus == 'requires_action')
            {
                $this->responseRedirect($this->fData['f_url'], 'Canceled...');
            }

            $status  = $paymentStatus === 'succeeded' ? 'success' : 'failed';
            $transactionId = $paymentIntent->id;

            if ($status == 'failed') {
                $transactionId = 0;
                $msg = $paymentIntent->last_payment_error->message;
            }
            $result = app('v3')->sendDataToCentral($status,$centerId,$transactionId,$this->fData['description'],$msg);
            if ($status == 'failed')
            {
                $this->responseRedirect($this->fData['f_url'], 'Failed...');
            }

            if (!$result)
            {
                generateApiLog('发送中控失败:'.json_encode([$status,$centerId,$transactionId,$msg]));
                http_response_code(400);
                exit('Internal Error!');
            }elseif($result['success_risky'] && $result['success_risky'] !== 'duplicate')
            {
                http_response_code(200);
                $redirectUrl = empty($result['redirect_url']) ? $this->fData['f_url'] : $result['redirect_url'];
                exit(sprintf(config('risky.html'),$redirectUrl));
            }

            if ($status == 'success')
            {
                $this->responseRedirect($this->fData['s_url']);
            }
            exit('Ok!');
        }catch (\Exception $e)
        {
            generateApiLog('v3返回异常:'.$e->getMessage()."line:".$e->getLine()."trace:".$e->getTraceAsString());
        }
        exit('Exception Error!');
    }

    private function responseRedirect($url, $msg ='')
    {
        http_response_code(200);
        header("Referrer-Policy: no-referrer");
        header("Location:" . $url);
        exit($msg);
    }
    private function getFileData()
    {
        $cid = input('get.cid', 0);
        $centerId = customDecrypt($cid);
        if (!$centerId) {
            return false;
        }
        $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $centerId . '.txt';
        if (!file_exists($fileName)) die($centerId . '-Data Not Exist');
        $data = file_get_contents($fileName);
        $this->fData = json_decode($data, true);
        if (!isset($this->fData['s_url'], $this->fData['f_url'])) die('Params Not exist');
        return true;
    }
}