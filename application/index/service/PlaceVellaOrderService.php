<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/6/27
 * Time: 11:17
 */

namespace app\index\service;

class PlaceVellaOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $reference_id = mt_rand(10000,99999).'_' . intval($params['center_id']);
        $amount = floatval($params['amount']);
        $key = env('stripe.public_key');
        $tags = env('stripe.private_key');
        $currency = 'NGN';// supported fiat NGNT,USDT,USDC
        $result = compact('reference_id','amount','key','tags','currency');
        return apiSuccess($result);
    }

    public function sendDataToCentral($status,$centerId,$transactionId,$msg = '')
    {
        // 发送到中控
        $postCenterData = [
            'transaction_id' => $transactionId,
            'center_id' => $centerId,
            'action' => 'create',
            'status' => $status,
            'failed_reason' => $msg
        ];
        $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
        if (!isset($sendResult['status']) or $sendResult['status'] == 0)
        {
            generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
            return false;
        }
        $riskyFlag = $sendResult['data']['success_risky'] ?? false;
        return ['success_risky' => $riskyFlag];
    }
}