<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 10:23
 */

namespace app\index\service;


class PlacePoyntOrderService extends BaseService
{
    /**
     * 调用First Data的支付 by zhuzh
     * @return \think\response\Json
     */
    public function placeOrder(array $params = [])
    {
        $postData = $params;
        $currency_dec = config('parameters.currency_dec');

        try {
            if (!$this->checkToken($params)) return apiError();
            if(isset($currency_dec[strtoupper($postData['currency_code'])])) {
                try {
                    generateApiLog(REFERER_URL .'poynt 返回格式' . json_encode($postData['transaction']));

                    $result = json_encode($postData['transaction']);
                    $result = json_decode($result, true);

                    $array = [];
                    $array['response'] = isset($result['data']['processorResponse']['status']) && $result['data']['processorResponse']['status'] == 'Successful' ? 1 : 0;
                    $array['transactionid'] = $result['data']['processorResponse']['transactionId'];
                    $array['responsetext'] = isset($result['data']['processorResponse']['statusMessage']) ?? '';


                    $payment_status = isset($array['response']) && $array['response'] == 1 ? 'success' : 'failed';
//                    if($payment_status == 'success') {
//                        $this->validateCard(true);
//                    } else {
//                        $this->validateCard(false);
//                    }

                    $postCenterData = [
                        'transaction_id' => isset($array['transactionid']) ? $array['transactionid'] : 0,
                        'center_id' => $postData['center_id'] ?? 0,
                        'action' => 'create',
                        'status' => $payment_status,
                        'failed_reason' => isset($array['response']) && $array['response'] == 1 ? '' : $array['responsetext']
                    ];

                    $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
                    if (!isset($sendResult['status']) or $sendResult['status'] == 0)
                    {
                        generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
                        return apiError('send error');
                    }

                    $riskyFlag = $sendResult['data']['success_risky'] ?? false;
                    if(isset($array['response']) && $array['response'] == 1) {
                        return apiSuccess(['success_risky' => $riskyFlag]);
                    }

                    return apiError($array['responsetext']);

                }catch(\Exception $e) {
                    $postCenterData = [
                        'transaction_id' => 0,
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
                    //$this->validateCard(false);
                    throw new \Exception($e->getMessage());
                    //return apiError($e->getMessage());
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

    private function processInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return strval($data);
    }
}

