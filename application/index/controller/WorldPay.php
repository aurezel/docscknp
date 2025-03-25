<?php

namespace app\index\controller;

use think\Controller;

class WorldPay extends Controller
{
    public function wdpWebhook()
    {
        generateApiLog([
            'type' => 'webhook',
            'input' => input(),
        ]);
        $webhookData = request()->post();
        if (!isset($webhookData['eventId'],$webhookData['eventDetails']['type'],$webhookData['eventDetails']['transactionReference']))
            return apiError('Illegal Access!');

        $detailData = $webhookData['eventDetails'];
        $status = 'success';
        $failedMsg = '';
        $transactionId = $detailData['transactionReference'];
        $centerId = explode('.',$transactionId)[1] ?? 0;
        if (!$centerId)
        {
            generateApiLog('Webhook中控ID异常:'.$transactionId);
            return apiError();
        }
        $webhookType = $detailData['type'];
        if (!in_array($webhookType,['authorized','error','expired','refused'])) return apiSuccess('ok');


        if ($detailData['type'] !== 'authorized')
        {
            $status = 'failed';
            $failedMsg = $detailData['type'];
        }

        app('worldpay')->sendDataToCentral($status,$centerId,$transactionId,$failedMsg);
        return apiSuccess('ok');
    }

    public function wdpReturn()
    {
        generateApiLog([
            'type' => 'redirect',
            'input' => input(),
        ]);
        $type = input('get.type');
        $cid = input('get.cid');

        if (empty($cid) || empty($type)) die('Illegal Access!');
        $centerId = intval(customDecrypt($cid));
        if (!$centerId) die('Illegal Access!');
        $fileName = app()->getRootPath() . 'file'.DIRECTORY_SEPARATOR . $centerId . '.txt';
        if (!file_exists($fileName)) die('Data Not Exist');
        $data = file_get_contents($fileName);
        if (empty($data)) die('Data Not Exist');
        $fData = json_decode($data,true);
        header("Referrer-Policy: no-referrer");
        if (in_array($type,['success','pending']))
        {
            header("Location:".$fData['s_url']);
        }else{
            header("Location:".$fData['f_url']);
        }
        exit('ok');
    }
}