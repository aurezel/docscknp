<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/5/24
 * Time: 16:24
 */

namespace app\index\service;

use Square\SquareClient;
use Square\Models\{Money,CreatePaymentRequest};

class PlaceSquareUpOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        try{
            if (!$this->checkToken($params)) return apiError();
            $currency_dec = config('parameters.currency_dec');
            $config = [
                'accessToken' => env('stripe.merchant_token'),
                'environment' => env('local_env') ? 'sandbox' : 'production',
            ];

            $json_str = file_get_contents('php://input');
            $json_obj = json_decode($json_str,true);
            $sourceId = $json_obj['sourceId'] ?? '';
            $locationId = $json_obj['locationId'] ?? '';

            if (empty($sourceId) || empty($locationId)) return apiError();

            $amount = $json_obj['amount'];
            $currency = strtoupper($params['currency']);
            $scale = 1;
            for($i = 0; $i < $currency_dec[$currency]; $i++) {
                $scale*=10;
            }
            $amount = bcmul($amount,$scale);


            $amount_money = new Money();
            $amount_money->setAmount($amount);
            $amount_money->setCurrency($currency);
            $body = new CreatePaymentRequest(
                $sourceId,
                uniqid('SQ_'),
                $amount_money
            );
            $body->setAutocomplete(true);
            $body->setLocationId($locationId);
            $body->setReferenceId($params['center_id']);
            $body->setNote('#order NO.'.$params['center_id']);

            $client = new SquareClient($config);
            $api_response = $client->getPaymentsApi()->createPayment($body);

            $transactionId = 0;
            $errorReason = '';
            $status = 'failed';

            if ($api_response->isSuccess()) {
                $response = $api_response->getResult();
                $result = json_decode(json_encode($response),true);
                $status = $result['payment']['status'] === 'COMPLETED' ? 'success' : 'failed';
                if ($status == 'failed')
                {
                    $errorReason = $result['payment']['status'];
                }
                $transactionId = $result['payment']['order_id'];

            } else {
                $response = $api_response->getErrors();
                $errorReason = json_decode(json_encode($response),true)[0]['detail'] ?? 'CAN NOT ERROR MSG!';
            }
            // Send Data To Central
            $postCenterData = [
                'transaction_id' => $transactionId,
                'center_id' => $params['center_id'] ?? 0,
                'action' => 'create',
                'status' => $status,
                'failed_reason' => $errorReason
            ];

            $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
            if (!isset($sendResult['status']) or $sendResult['status'] == 0)
            {
                generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
                return apiError();
            }
            $riskyFlag = $sendResult['data']['success_risky'] ?? false;
            if($status != 'failed') {
                return apiSuccess(['success_risky' => $riskyFlag]);
            }
            return apiError($errorReason);
        }catch (\Exception $ex)
        {
            $orderNo = $params['order_no'] ?? 0;
            $centerId = $params['center_id'] ?? 0;
            generateApiLog([
                '创建订单异常',
                "订单ID：{$orderNo}",
                "中控ID：{$centerId}",
                '错误信息：' => [
                    'msg' => $ex->getMessage(),
                    'code' => $ex->getCode(),
                    'line' => $ex->getLine(),
                    'trace' => $ex->getTraceAsString()
                ]
            ]);
        }
        return apiError();
    }
}