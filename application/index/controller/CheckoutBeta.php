<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/7/3
 * Time: 11:04
 */

namespace app\index\controller;

class CheckoutBeta
{
    public function webhook()
    {
        try {
            $event = @file_get_contents('php://input');
            generateApiLog('Checkout Beata Notify事件:'.$event);
            $eventArrData = json_decode($event,true);
            if (!isset($eventArrData['data']['object']['object']))
            {
                generateApiLog('参数缺失:'.$event);
                http_response_code(200);
                exit('Illegal Access');
            }

            $objectData = $eventArrData['data']['object'];//
            if (!in_array($eventArrData['type'],['charge.succeeded','charge.failed']))
            {
                generateApiLog('事件不在接收范围内!');
                http_response_code(200);
                exit('Illegal Event!');
            }

            $orderId = $objectData['metadata']['order_id'] ?? '';
            $centerId = intval(customDecrypt($orderId));
            $status = 'failed';
            $msg = '';
            $transactionId = $objectData['id'];
            if(!empty($objectData['payment_intent'])) {
                $transactionId = $objectData['payment_intent'];
            }
            if (!$centerId)
            {
                generateApiLog('中控ID非法:'.$centerId);
                http_response_code(200);
                exit();
            }

            if ($eventArrData['type'] == 'charge.succeeded')
            {
                $status = 'success';
            }else{
                $msg = $objectData['failure_message'] ?? '';
            }

            $result = app('stripe_checkout')->sendDataToCentral($status,$centerId,$transactionId,$msg);
            if (!$result)
            {
                generateApiLog('发送中控失败:'.json_encode([$status,$centerId,$transactionId,$msg]));
            }elseif($status == 'success')
            {
                $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $centerId . '.txt';
                $fileData = json_decode(file_get_contents($fileName),true);
                if(!empty($result['redirect_url'])) {
                    $fileData['f_url'] = $result['redirect_url'];
                }
                $fileData['risky'] = $result['success_risky'];
                file_put_contents($fileName,json_encode($fileData));
            }
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            generateApiLog('UnexpectedValueException:' . $e->getMessage());
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            generateApiLog('UnexpectedValueException:' . $e->getMessage());
        }
        http_response_code(200);
        die('ok');
    }

}