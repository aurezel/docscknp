<?php

namespace app\index\controller;

class StripeLink
{
    public function payReturn()
    {
        generateApiLog([
            'type' => 'success',
            'input' => input(),
        ]);
        $isIllegal = $this->getFileData();
        if (!$isIllegal) return apiError();
        $tried = 0;
        $max_tried_cnt = 1;
        while ($tried <= $max_tried_cnt) {
            if (isset($this->fData['risky']))
            {
                if(!$this->fData['risky']) {
                    header("Referrer-Policy: no-referrer");
                    header("Location:" . $this->fData['s_url']);
                    exit('ok');
                } else {
                    exit(sprintf(config('risky.html'),$this->fData['f_url']));
                }
            } else {
                sleep(3);
                $this->getFileData();
                $tried ++;
            }
        }
        header("Referrer-Policy: no-referrer");
        header("Location:" . $this->fData['s_url']);
        exit('ok');
    }

    public function webhook()
    {
        try {
            $event = @file_get_contents('php://input');
            file_put_contents('evt_'.time().'.json',$event);
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

            $encryptCid = $objectData['metadata']['cid'] ?? '';
            if (empty($encryptCid))
            {
                generateApiLog('中控ID不存在!');
                http_response_code(200);
                exit('Illegal Event!');
            }
            $centerId  = customDecrypt($encryptCid);
            $status = 'failed';
            $msg = '';
            $transactionId = $objectData['id'];
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

            $result = app('stripe_link')->sendDataToCentral($status,$centerId,$transactionId,$msg);
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