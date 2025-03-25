<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 10:23
 */

namespace app\index\service;


use Braintree;

class PlaceBraintreeOrderService extends BaseService
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
                    //请求支付交易
                    $postData['card_number'] = str_replace(' ', '', $postData['card_number']);
                    if(empty($postData['card_number']) || empty($postData['cvc']) || empty($postData['expiry_month']) || empty($postData['expiry_year']) ){
                        return apiError('system error');
                    }

                    $environment = env('local_env') ? 'sandbox' : 'production';
                    $publicKey = env('stripe.public_key');
                    $privateKey = env('stripe.private_key');
                    $merchantId = env('stripe.merchant_token');


                    //月份补零
                    $postData['expiry_month'] = str_pad($postData['expiry_month'],2,"0",STR_PAD_LEFT);

                    $card_number = $this->processInput($postData['card_number']);
                    $card_cvv = $this->processInput($postData['cvc']);
                    $card_expiry = $this->processInput($postData['expiry_month'].'/'.$postData['expiry_year']);
                    $amount = $this->processInput($postData['amount']);
                    $currency_code = $this->processInput($postData['currency']);
                    $merchant_ref = $this->processInput($postData['center_id']);
                    $cardholderName = $postData['first_name'] . ' ' . $postData['last_name'];


                    $gateway = new Braintree\Gateway([
                        'environment' => $environment,
                        'merchantId' => $merchantId,
                        'publicKey' => $publicKey,
                        'privateKey' => $privateKey
                    ]);

                    $config = new Braintree\Configuration([
                        'environment' => $environment,
                        'merchantId' => $merchantId,
                        'publicKey' => $publicKey,
                        'privateKey' => $privateKey
                    ]);

                    $gateway = new Braintree\Gateway($config);


                    $result = $gateway->transaction()->sale([
                        'amount' => $amount,
                        'orderId' => $merchant_ref,
                        //'merchantAccountId' => 'testproduct',
                        //'paymentMethodNonce' => rand(1000000,9000000),
                        'creditCard' => [
                            'cardholderName' => $cardholderName,
                            'cvv' => $card_cvv,
                            'expirationDate' => $card_expiry,
                            'number' => $card_number,
                        ],
                        'options' => [
                            'submitForSettlement' => true
                        ]
                    ]);

                    $array = [];
                    if ($result->success) {
                        //print_r("success!: " . $result->transaction->id);
                        $array['response'] = 1;
                        $array['transactionid'] = $result->transaction->id;
                        $array['responsetext'] = '';
                    } else if ($result->transaction) {
//                        print_r("Error processing transaction:");
//                        print_r("\n  code: " . $result->transaction->processorResponseCode);
//                        print_r("\n  text: " . $result->transaction->processorResponseText);
                        $array['response'] = 0;
                        $array['transactionid'] = $result->transaction->id;
                        $array['responsetext'] = $result->transaction->processorResponseText;
                    } else {
                        $array['response'] = 0;
                        $array['transactionid'] = 0;
                        $array['responsetext'] = '';

                        foreach($result->errors->deepAll() AS $error) {
                            //print_r($error->code . ": " . $error->message . "\n");
                            $array['responsetext'] .= $error->code . ": " . $error->message . "\n";
                        }
                    }

                    $payment_status = isset($array['response']) && $array['response'] == 1 ? 'success' : 'failed';
                    if($payment_status == 'success') {
                        $this->validateCard(true);
                    } else {
                        $this->validateCard(false);
                    }

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
                    $this->validateCard(false);
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

