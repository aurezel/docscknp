<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/6/27
 * Time: 11:18
 */
namespace app\index\controller;
class Vella
{
    public function vellaVerify()
    {
        $params = input();
        $referenceId = $params['reference_id'] ?? '';
        $publicKey = $params['key']?? '';
        $merchant_id = $params['tags'] ?? '';
        if (empty($referenceId) || empty($publicKey) || empty($merchant_id) ||
            $publicKey !== env('stripe.public_key') || $merchant_id !== env('stripe.private_key')) return apiError();
        $centerId = explode('_',$referenceId)[1] ?? 0;
        if (!$centerId) return apiError();
        $verifyUrl = env('local_env') ? "https://sandbox.vella.finance/api/v1/checkout/transaction/$referenceId/verify" : "https://api.vella.finance/api/v1/checkout/transaction/$referenceId/verify";

       try{
           $opts = array(
               'http'=>array(
                   'method'=>"GET",
                   'header'=>"Authorization: Bearer $publicKey\r\n"
               )
           );

           $context = stream_context_create($opts);

           $response = file_get_contents($verifyUrl,false,$context);

           $responseData = json_decode($response,true)['data'] ?? '';
           if (empty($responseData)) return apiError('empty response');
           $responseReferenceId= $responseData['reference'] ?? '';
           if ($responseReferenceId !== $referenceId) return apiError('error params');

           $status = $responseData['status'] == 'successful' ? 'success' :'failed';
           $msg = '';
           if ($status != 'success')
           {
               $msg = $response;
           }
           $sendResult = app('vella')->sendDataToCentral($status,$centerId,$referenceId,$msg);
           if (!isset($sendResult['success_risky']))
           {
               return apiError();
           }
           return apiSuccess();
       }catch (\Exception $e)
       {
           generateApiLog('CURL ERROR:'.$e->getMessage());
       }
       return apiError();
    }

    public function vellaWebhook()
    {
        generateApiLog([
            'type' => 'revolutWebhook',
            'input' => input(),
            'pInput' => file_get_contents('php://input')
        ]);

        $json = file_get_contents('php://input');
        $event = json_decode($json);

        $status = 'failed';
        $msg = '';
        $transactionId = 0;
        if (!isset($event->data->reference) || empty($event->data->reference)) die('Illegal Access!');
        $order_details = explode('_', $event->data->reference);
        $centerId = customDecrypt($order_details[1]);
        if (!$centerId) die('Illegal Params');
        if ('transaction.completed' == $event->type) {
            $transactionId = $event->data->tnx_ref ?? '';
            $status = 'success';
        }else{
            $msg = $event->type;
        }
        $sendResult = app('vella')->sendDataToCentral($status,$centerId,$transactionId,$msg);
        if (!isset($sendResult['success_risky']))
        {
            generateApiLog('发送中控制失败');
            die('failed');
        }
        exit('ok');
    }
}