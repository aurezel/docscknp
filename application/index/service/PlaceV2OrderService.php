<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 9:09
 */

namespace app\index\service;


class PlaceV2OrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $postData = $params;
        $private_key = env('stripe.private_key');
        $curl = new \Stripe\HttpClient\CurlClient(getCurlOpts());
        \Stripe\ApiRequestor::setHttpClient($curl);
        $stripe = new \Stripe\StripeClient($private_key);
        $currency_dec = config('parameters.currency_dec');

        try {
            $charge = null;
            if(isset($currency_dec[strtoupper($postData['currency_code'])])) {
                $amount = $postData['amount'];
                for($i = 0; $i < $currency_dec[strtoupper($postData['currency_code'])]; $i++) {
                    $amount *= 10;
                }
                try {
                    $shipping = array('address' => array('line1' => $postData['address1'], 'city'=>$postData['city'], 'country'=>$postData['country'], 'line2' => $postData['address2'],
                        'postal_code' => $postData['zip'], 'state'=>$postData['state']), 'name'=> $postData['first_name'] . ' ' . $postData['last_name'], 'phone'=>$postData['phone']);

                    $token = $postData['stripeToken'];

                    $charge = $stripe->charges->create([
                        'amount' => intval($amount),
                        'currency' => strtolower($postData['currency_code']),
                        'source' => $token,
                        'description' => "Order #" .substr(time(),-6,6).mt_rand(10,99) ,
                        'shipping' => $shipping
                    ]);

                    $this->validateCard(true);
                }catch(\Stripe\Exception\CardException $e) {
                    $transactionId = $e->getError()->charge;
                    $retrieveResult = $stripe->charges->retrieve(
                        $transactionId,
                        []
                    );
                    $outCome = $retrieveResult->outcome->jsonSerialize();
                    $postCenterData = [
                        'transaction_id' => $transactionId,
                        'center_id' => $postData['center_id'] ?? 0,
                        'action' => 'create',
                        'status' => 'failed',
                        'risk_level' => $outCome['risk_level'] ?? 'NULL',
                        'risk_score' => $outCome['risk_score'] ?? 0,
                        'failed_reason' => $e->getMessage()
                    ];
                    $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
                    if (!isset($sendResult['status']) or $sendResult['status'] == 0)
                    {
                        generateApiLog(REFERER_URL .'createOrder创建订单传送信息到中控失败：' . json_encode($sendResult));
                    }
                    $this->validateCard();
                    throw new \Exception($e->getMessage());
                }
            }

            if (!empty($charge)){
                $outCome = $charge->outcome->jsonSerialize();
                $riskScore = $outCome['risk_score'] ?? 0;
                $riskLevel = $outCome['risk_level'] ?? 'NULL';
                $postCenterData = [
                    'transaction_id' => $charge->id,
                    'center_id' => $postData['center_id'] ?? 0,
                    'action' => 'create',
                    'description' => $charge->description,
                    'status' => $charge->status == 'failed' ? 'failed' : 'success',
                    'risk_score' => $riskScore,
                    'risk_level' => $riskLevel,
                    'failed_reason' => $charge->failure_message
                ];
                $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
                if (!isset($sendResult['status']) or $sendResult['status'] == 0)
                {
                    generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
                    return apiError();
                }
                $riskyFlag = $sendResult['data']['success_risky'] ?? false;
                if($charge->status != 'failed') {
                    return apiSuccess(['success_risky' => $riskyFlag]);
                }
                return apiError($charge->failure_message);
            } else {
                return apiError('Empty Response!');
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