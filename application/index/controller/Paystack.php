<?php

namespace app\index\controller;

class Paystack
{
    public function webhook()
    {
        try {
            // only a post with paystack signature header gets our attention
            if ((strtoupper($_SERVER['REQUEST_METHOD']) != 'POST' ) || !array_key_exists('HTTP_X_PAYSTACK_SIGNATURE', $_SERVER) )
                exit('Illegal Param!');

            // Retrieve the request's body
            $input = @file_get_contents("php://input");
            // validate event do all at once to avoid timing attack
            if($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] !== hash_hmac('sha512', $input, env('stripe.private_key')))
                exit('Illegal Access!');

            // parse event (which is json string) as object
            // Do something - that will not take long - with $event
            generateApiLog([
                'type' => 'notify',
                'input' => $input,
            ]);
            $eventArrData = json_decode($input,true);
            $event = $eventArrData['event'] ?? '';
            if (empty($event))
            {
                http_response_code(200);
                die('Illegal Access!');
            }
            if ($event !== 'charge.success')
            {
                generateApiLog('事件不在接收范围内!');
                http_response_code(200);
                exit('Illegal Event!');
            }

            if (!isset($eventArrData['data']['metadata']['cart_id']))
            {
                generateApiLog('中控参数缺失:'.$event);
                http_response_code(200);
                exit('Illegal Access');
            }

            $eventArrData = $eventArrData['data'];
            $evtAmount = $eventArrData['amount'];
            $evtCurrency = $eventArrData['currency'];

            $centerId = customDecrypt($eventArrData['metadata']['cart_id']);
            $transactionId = $eventArrData['reference'];
            $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $centerId . '.txt';
            if (!file_exists($fileName))
            {
                generateApiLog($centerId . '-Data Not Exist');
                http_response_code(200);
                die('Internal Error');
            }
            $data = file_get_contents($fileName);
            $fileData = json_decode($data, true);
            if ($evtAmount != $fileData['amount'] || $evtCurrency != $fileData['currency'])
            {
                generateApiLog('货币或金额不对:'.$data);
                http_response_code(200);
                die('Illegal Access!');
            }
            $status = 'success';
            $msg = $eventArrData['data']['message'] ?? '';
            $result = app('paystack')->sendDataToCentral($status,$centerId,$transactionId,$msg);
            if (!$result)
            {
                generateApiLog('发送中控失败:'.json_encode([$status,$centerId,$transactionId,$msg]));
            }
        } catch (\Exception $e)
        {
            generateApiLog('Notify异常:'.$e->getMessage());
        }
        http_response_code(200);
        die('ok');
    }
}