<?php

namespace app\index\controller;

use think\Controller;

class Nuvei extends Controller
{
    public function nuveiWebhook()
    {
        $data = input();
        generateApiLog([
            'type' => 'webhook',
            'input' => input(),
        ]);
        if (!$this->validate_checksum())
        {
            exit('Illegal Access!!');
        }


        $action = $data['action'] ?? '';
        $eventData = $data['event'];
        if ($action == 'order_result')
        {
            $transactionId = $eventData['order_id'];
            $description = $msg = '';
            $centerId = $this->getCenterIdByFile($transactionId);
            if (!$centerId)
            {
                generateApiLog('中控ID不存在');
                die('Internal Error!');
            }
            if ($eventData['status'] == 1)
            {
                $status = 'success';
            }else{
                //  0.处理中，1.支付成功，2.确认中，3.异常，4.失败，5.取消 6.订单过期 7.退款中 8.退款成功 9.退款失败
                $status = 'failed';
                $msg = $eventData['reason'];
            }
            $result = app('cloudpay')->sendDataToCentral($status,$centerId,$transactionId,$description,$msg);
            if (!$result)
            {
                generateApiLog('发送中控失败:'.json_encode([$status,$centerId,$transactionId,$msg]));
                exit('Internal Error!');
            }
        }else{
            generateApiLog('未处理数据');
            exit('Internal Error!');
        }

        return json([
            'code' => 0,
            'msg' => 'SUCCESS'
        ]);

    }

    public function nuvei3DReturn()
    {
        $params = input();
        generateApiLog([
            '3dData' => $params
        ]);

        $orderId = $params['cust_order_id'] ?? ($params['csid'] ?? 0);
        if (!isset($params['cid']) || empty($orderId))
        {
            return apiError();
        }
        $centerId = customDecrypt($params['cid']);
        if (!$centerId)
        {
            generateApiLog('中控ID错误');
            return apiError();
        }
        $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $centerId . '.txt';
        if (!file_exists($fileName)) die($centerId . '-Data Not Exist');
        $data = file_get_contents($fileName);

        $timeStamp = round(microtime(true) * 1000);
        $publicKey = env('stripe.public_key');
        $signatureData = $publicKey .
            "&" . env('stripe.private_key') .
            "&" . $timeStamp;
        $requestData = [
            'cust_order_id' => $orderId
        ];

        $fData =  json_decode($data, true);
        if (!isset($fData['s_url'],$fData['f_url'])) return apiError();
        try{
            $responseData = app('cloudpay')->cloudPayHttp('/api/v1/orders?',$requestData, $signatureData, $timeStamp,false);
            if (isset($responseData['result']['records'][0]['status']) &&
                $responseData['result']['records'][0]['status'] == 1)
            {
                header("Referrer-Policy: no-referrer");
                header("Location: ".$fData['s_url']);
                exit('ok');
            }
        }catch (\ErrorException $e)
        {
            generateApiLog("3DReturn异常:".$e->getMessage());
        }
        header("Referrer-Policy: no-referrer");
        header("Location: ".$fData['f_url']);
        exit('ok');
    }


    private function getCenterIdByFile($custOrderId)
    {
        $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $custOrderId . '.txt';
        if (!file_exists($fileName)) return false;
        return (int) file_get_contents($fileName);
    }

    private function validate_checksum()
    {
        $advanceResponseChecksum = input('advanceResponseChecksum');
        $responsechecksum        = input('responsechecksum');

        if (empty($advanceResponseChecksum) && empty($responsechecksum))
        {
            generateApiLog('advanceResponseChecksum and responsechecksum parameters are empty.');
            return false;
        }

        $merchant_secret = env('stripe.private_key');

        // advanceResponseChecksum case
        if (!empty($advanceResponseChecksum)) {
            $concat = $merchant_secret
                . input('totalAmount')
                . input('currency')
                . input('responseTimeStamp')
                . input('PPP_TransactionID')
                . self::get_request_status()
                . input('productId');

            $str = hash('sha256', $concat);

            if (strval($str) == $advanceResponseChecksum) {
                return true;
            }
            generateApiLog('advanceResponseChecksum validation fail.');
            return false;
        }

        # subscription DMN with responsechecksum case
        $concat        = '';
        $request_arr   = $_REQUEST;
        $custom_params = array(
            'wc-api'            => '',
            'responsechecksum'  => '',

            /** @deprecated
             * TODO - must be removed in near future.
             * Be new notify URL is provided to Integration/TechSupport Team
             */
            'save_logs'         => '',
            'test_mode'         => '',
            'stop_dmn'          => '',
        );

        // remove parameters not part of the checksum
        $dmn_params = array_diff_key($request_arr, $custom_params);
        $concat     = implode('', $dmn_params);

        $concat_final = $concat . $merchant_secret;
        $checksum     = hash('sha256', $concat_final);

        if ($responsechecksum !== $checksum)
        {
            generateApiLog('responsechecksum validation fail:'.json_encode(
                [
                'string_concat' =>$concat,
                    'checksum' => $checksum
                ]));
            return false;
        }
        return true;
    }

    private static function get_request_status( $params = array()) {
        $Status = input('Status');
        $status = input('status');

        if (empty($params)) {
            if ('' != $Status) {
                return $Status;
            }

            if ('' != $status) {
                return $status;
            }
        } else {
            if (isset($params['Status'])) {
                return $params['Status'];
            }

            if (isset($params['status'])) {
                return $params['status'];
            }
        }

        return '';
    }
}