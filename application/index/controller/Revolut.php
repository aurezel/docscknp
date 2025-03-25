<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/6/26
 * Time: 9:42
 */

namespace app\index\controller;

class Revolut
{
    public function revolutWebhook()
    {
        // url:/checkout/pay/revolutWebhook
        generateApiLog('Webhook消息');
        $params = input('post.');
        if (!isset($params['event'],$params['order_id'],$params['merchant_order_ext_ref'])) return apiError();

        try{
            $centerId = explode('_',$params['merchant_order_ext_ref'])[0] ?? 0;
            if (!$centerId)
            {
                generateApiLog('centerId获取不到！');
                return apiError();
            }

            $event = $params['event'];
            $transactionId = $params['order_id'];
            $status = 'failed';
            $reason = '';
            switch ($event)
            {
                case 'ORDER_COMPLETED':
                    $status = 'success';
                    break;
                case 'ORDER_PAYMENT_FAILED':
                case 'ORDER_PAYMENT_DECLINED':
                    $reason = $event;
                    break;
                default: return apiError();
            }

            $sendResult = app('revolut')->sendDataToCentral($centerId,$transactionId,$status,$reason);
            if (!$sendResult)
            {
                generateApiLog('发送到中控失败:'.json_encode([$centerId,$transactionId,$status,$reason]));
                return apiError();
            }
            return apiSuccess();
        }catch (\Exception $e)
        {
            generateApiLog('处理webhook接口异常:'.json_encode(['msg' => $e->getMessage(),'line' => $e->getLine(),'tracing' => $e->getTraceAsString()]));
        }
        return apiError();
    }

    public function addWebhook()
    {
        $path = input('post.webhook_path', '');
        if (empty($path)) return apiError('Illegal Access!');

        try{

            $webhookListData = $this->getWebhookUrlList();
            $webhookId = $webhookListData[0]['id'] ?? '';
            $webhookUrl = request()->domain() . $path;
            $url = $this->getRequestWebhookUrl();
            $requestData = [
                'url' => $webhookUrl,
                'events' => [
                    'ORDER_COMPLETED','ORDER_AUTHORISED','ORDER_PAYMENT_AUTHENTICATED',
                    'ORDER_PAYMENT_DECLINED','ORDER_PAYMENT_FAILED'
                ]
            ];

            if (empty($webhookId))
            {
                $responseData = $this->revolutRequest($url,$requestData);
            }else{
                if ($webhookListData[0]['url'] == $webhookUrl) return apiError('数据未更新');
                $responseData = $this->revolutRequest($url . "/$webhookId",$requestData,'PUT');
            }

            if (isset($responseData['url']) && $responseData['url'] == $webhookUrl)
            {
                return apiSuccess();
            }else{
                generateApiLog('创建Webhook失败:' . json_encode($responseData));
            }

            return apiError();
        }catch (\Exception $e)
        {
            return apiError($e->getMessage());
        }
    }

    public function getWebhookUrl()
    {
        try{
            $responseData = $this->getWebhookUrlList();
            return apiSuccess(['url' => $responseData[0]['url'] ?? '']);
        }catch (\Exception $e)
        {
            return apiError('获取Webhook接口异常:'.$e->getMessage());
        }
    }


    private function getWebhookUrlList()
    {
        $webhookUrl = $this->getRequestWebhookUrl();
        return $this->revolutRequest($webhookUrl,[],'GET');
    }


    private function revolutRequest($url, $data,$method = 'POST')
    {
        $curl = curl_init();
        $curlOptArr = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer '.env('stripe.private_key')
            ),
        );

        if ($method != 'GET')
        {
            $curlOptArr[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        curl_setopt_array($curl, $curlOptArr);

        $response = curl_exec($curl);
        if (curl_errno($curl))
        {
            throw new \Exception("Curl请求{$url}错误:".curl_error($curl));
        }
        curl_close($curl);
        return json_decode($response,true);
    }


    private function getRequestWebhookUrl()
    {
        return env('local_env') ? 'https://sandbox-merchant.revolut.com/api/1.0/webhooks' : 'https://merchant.revolut.com/api/1.0/webhooks';
    }

}