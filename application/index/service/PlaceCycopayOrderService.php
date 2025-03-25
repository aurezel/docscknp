<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/8/1
 * Time: 10:05
 */

namespace app\index\service;

class PlaceCycopayOrderService extends BaseService
{

    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $cid = customEncrypt($params['center_id']);
        $baseUrl = request()->domain(). '/checkout/pay/';
        $postData['apiKey'] = env('stripe.private_key');
        $postData['amount'] = $params['amount'];
        $postData['webhookURL'] = $baseUrl  . 'cyWebhook?cid='.$cid;
        $postData['successURL'] = $baseUrl  . 'cySuccess?cid='.$cid;
        $postData['failureURL'] = $baseUrl  . 'cyFailure?cid='.$cid;
        $postData['currency'] = $params['currency'];
        $postData['description'] = 'Cycopay Payment';
        $postData['email'] = $params['email'];
        $postData['fullName'] = $params['first_name'] . ' '.$params['last_name'];
        generateApiLog($postData);
        $result = $this->getCyLink('https://api.cycopay.com/api/public/payment/create',$postData);
        if (!$result) return apiError();
        $response = json_decode($result,true);
        if ($response['status'] != 'success')
        {
            generateApiLog("生成支付链接失败：".$result);
            return apiError();
        }
        generateApiLog(['payUrl' => $response['url']]);
        return apiSuccess($response['url']);
    }

    public function sendDataToCentral($status, $center_id,$payment_id = 0,$msg = '')
    {
        if (!in_array($status,['success','failed'])) return false;
        $centralStatus = 'failed';
        if ($status == 'success') $centralStatus = 'success';
        // 发送到中控
        $postCenterData = [
            'transaction_id' => $payment_id,
            'center_id' => $center_id,
            'action' => 'create',
            'status' => $centralStatus,
            'failed_reason' => $msg
        ];
        $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
        if (!isset($sendResult['status']) or $sendResult['status'] == 0)
        {
            generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
            return false;
        }
        return true;
    }

    private function getCyLink($url = '', $post_data = array()) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch,CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
        $data = curl_exec($ch);
        if($data === false){
            generateApiLog("CURL ERROR:".curl_error($ch));
            return false;
        }else{
            curl_close($ch);
            return $data;
        }
    }
}