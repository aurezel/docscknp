<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/7/3
 * Time: 11:04
 */

namespace app\index\controller;

class StripeCheckout
{
    private $fData = [];

    public function payCancel()
    {
        generateApiLog([
            'type' => 'cancel',
            'input' => input(),
        ]);
        $isIllegal = $this->getFileData();
        if (!$isIllegal) return apiError();
        header("Referrer-Policy: no-referrer");
        header("Location:" . $this->fData['f_url']);
        exit;
    }

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

    public function addWebhook()
    {
        $path = input('post.webhook_path', '');
        if (empty($path)) return apiError('Illegal Access!');

        try {
            \Stripe\Stripe::setApiKey(env('stripe.private_key'));
            //
            $webhookUrl = request()->domain() . $path;
            $endpointList = \Stripe\WebhookEndpoint::all();
            foreach ($endpointList->data as $dt)
            {
                if ($webhookUrl === $dt->url) return apiError('Webhook url already exist.');
            }
            $webhookPostData = [
                'url' => $webhookUrl,
                'enabled_events' => [
                    'charge.failed',
                    'charge.succeeded',
                ],
            ];
            $endpoint = \Stripe\WebhookEndpoint::create($webhookPostData);
            if ($endpoint->status == 'enabled' && $endpoint->url == $webhookUrl) {
                return apiSuccess();
            }
        } catch (\Exception $e) {
            generateApiLog("添加webhook接口异常:" . $e->getMessage() . "行数:" . $e->getMessage() . "Tracking:" . $e->getTraceAsString());

        }
        return apiError();
    }

    public function getWebhookUrl()
    {
        try{
            \Stripe\Stripe::setApiKey(env('stripe.private_key'));
            $endpointList = \Stripe\WebhookEndpoint::all();
            $urls = [];
            foreach ($endpointList->data as $dt)
            {
                $urls[] = $dt->url;
            }
            return apiSuccess([
                'url' => join(',',$urls)
            ]);
        }catch (\Exception $e)
        {
            generateApiLog("获取webhook链接接口异常:" . $e->getMessage() . "行数:" . $e->getMessage() . "Tracking:" . $e->getTraceAsString());
        }
        return apiError();
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

            $transactionIdFile = app()->getRootPath() . DIRECTORY_SEPARATOR .'file' .DIRECTORY_SEPARATOR .$objectData['customer'].'.txt';
            if (!file_exists($transactionIdFile))
            {
                generateApiLog('交易ID文件不存在:'.$transactionIdFile);
                http_response_code(200);
                exit();
            }

            $centerId = intval(file_get_contents($transactionIdFile));
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

    public function getAccountStatus()
    {
        try{
            $stripe = new \Stripe\StripeClient(env('stripe.private_key'));
            $retrieve = $stripe->accounts->retrieve();
            return apiSuccess(['id' => $retrieve->id,'charge_enable' => $retrieve->charges_enabled,'payouts_enabled' => $retrieve->payouts_enabled]);
        }catch (\Exception $e)
        {
            generateApiLog("获取stripe checkout账户状态接口异常:".$e->getMessage()."line:".$e->getLine()."trace:".$e->getTraceAsString());
            return apiError($e->getMessage());
        }
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