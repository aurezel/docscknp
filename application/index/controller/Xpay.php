<?php

namespace app\index\controller;
use think\response\Json;
class Xpay
{
    public function redirect()
    {
        generateApiLog([
            'type' => 'redirect',
            'date' => input()
        ]);
        $type = input('get.r_type','');
        $cid = input('get.cid',0);
        $result = input('get.esito','');
        $centerId = (int) customDecrypt($cid);
        if (empty($type) || empty($result)|| !$centerId || !in_array($type,['s','r'])) die('Illegal Access!');
        $postMessage = 'risky';
        if ($type == 's' && $result == 'OK')
        {
            $postMessage = 'succeeded';
            echo "<div style='color: green;margin-top: 25%;text-align: center;'>Payment Successful,jumping now...</div>";
        }else{
            echo "<div style='color: red;margin-top: 25%;text-align: center;'>Payment Failed,jumping now...</div>";
        }
        die("<script>window.parent.postMessage('$postMessage','*');</script>");
    }
    public function webhook()
    {
        generateApiLog([
            'type' => 'xpayWebhook',
            'input' => input(),
        ]);
        try{
            $postData = input();
            if (!isset($postData['cid'],$postData['codAut'],$postData['messaggio'],$postData['esito'])) die('Params Not Found!');
            $centerId = intval(customDecrypt($postData['cid']));
            if(empty($centerId)) die("Illegal Access");
            $transactionId = $postData['codAut'];
            $message = $postData['messaggio'];
            $status = 'failed';
            $result = $postData['esito'];
            if ($result == 'OK')
            {
                $status = 'success';
            }
            $sendResult = app('xpay')->sendDataToCentral($status,$centerId,$transactionId,$message);
            if (!$sendResult) die('Internal Error!');

        }catch (\Exception $e)
        {
            generateApiLog('XPay Webhook 接口异常:'.$e->getMessage());
        }
        die('Ok');
    }
}