<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 9:09
 */
namespace app\index\service;


class PlaceSimplifyOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        require_once(env('ROOT_PATH') . 'vendor/simplify/Simplify.php');
        if (!$this->checkToken($params)) return apiError();
        $postData = $params;
        $private_key = env('stripe.private_key');
        $public_key = env('stripe.public_key');
        $currency_dec = config('parameters.currency_dec');

        \Simplify::$publicKey = $public_key;
        \Simplify::$privateKey = $private_key;


        try {
            if(isset($currency_dec[strtoupper($postData['currency_code'])])) {
                $amount = $postData['amount'];
                for($i = 0; $i < $currency_dec[strtoupper($postData['currency_code'])]; $i++) {
                    $amount *= 10;
                }
                $description = "Order #" . substr(time(), -6, 6) . mt_rand(10, 99);
                try {
                    $payment = \Simplify_Payment::createPayment(array(
                        'reference' => $description,
                        'amount' => (int)$amount,
                        'description' => $description,
                        'currency' => strtoupper($postData['currency_code']),
                        'token' => $postData['pay_token'],
                    ));

                    $status = 'failed';
                    $failed_reason = '';
                    if ($payment->paymentStatus == 'APPROVED') {
                        $status = 'success';
                    } else {
                        $failed_reason = $payment->paymentStatus . " " . $payment->declineReason;
                    }
                    $postCenterData = [
                        'transaction_id' => $payment->id,
                        'center_id' => $postData['center_id'] ?? 0,
                        'action' => 'create',
                        'description' => $description,
                        'status' => $status,
                        'failed_reason' => $failed_reason
                    ];
                    $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
                    if (!isset($sendResult['status']) or $sendResult['status'] == 0)
                    {
                        generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
                        return apiError();
                    }
                    if($status == "success") {
                        $riskyFlag = $sendResult['data']['success_risky'] ?? false;
                        return apiSuccess(['success_risky' => $riskyFlag]);
                    } else {
                        return apiError($failed_reason);
                    }
                }catch(Exception $e) {
                    $postCenterData = [
                        'transaction_id' => '',
                        'center_id' => $postData['center_id'] ?? 0,
                        'action' => 'create',
                        'status' => 'failed',
                        'failed_reason' => $e->getMessage()
                    ];
                    $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
                    if (!isset($sendResult['status']) or $sendResult['status'] == 0)
                    {
                        generateApiLog(REFERER_URL .'createOrder创建订单传送信息到中控失败：' . json_encode($sendResult));
                    }
                    return apiError($e->getMessage());
                }
            }
        }catch (\Exception $ex) {
            $orderNo = $postData['order_no'] ?? 0;
            $centerId = $postData['center_id'] ?? 0;
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
            return apiError();
        }
    }
}