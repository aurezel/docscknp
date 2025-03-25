<?php

namespace app\index\service;

class PlacePaystackOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        return apiSuccess();
        if (empty($params['reference']) || !$this->checkToken($params) ) return apiError();
        try{
            $currency_dec = config('parameters.currency_dec');
            $amount = floatval($params['amount']);
            $currency = strtoupper($params['currency']);
            $scale = 1;
            for($i = 0; $i < $currency_dec[$currency]; $i++) {
                $scale*=10;
            }
            $amount = bcmul($amount,$scale);
            // verify transaction
            $curl = curl_init();
            $privateKey = env('stripe.private_key');
            curl_setopt_array($curl, array(
                CURLOPT_URL => "https://api.paystack.co/transaction/verify/{$params['reference']}",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                    "Authorization: Bearer $privateKey",
                    "Cache-Control: no-cache",
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);
            if ($err) {
                throw  new \Exception("cURL Error #:" . $err);
            } else {
                generateApiLog('Paystack响应:'.$response);
                $responseObj = json_decode($response);
                if (isset($responseObj->data->status) &&
                    $responseObj->data->status == 'success' && $responseObj->data->amount == $amount)
                {
                    return apiSuccess();
                }
            }
        }catch (\Exception $e)
        {
            generateApiLog('Paystack验证支付状态接口异常:'.$e->getMessage());
        }
        return apiError();
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
        return ['success_risky' => $sendResult['data']['success_risky'],'redirect_url' => $sendResult['data']['redirect_url'] ?? ''];
    }
}