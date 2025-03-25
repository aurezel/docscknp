<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/5/5
 * Time: 15:22
 */

namespace app\index\service;


class PlaceWePayOrderService extends BaseService
{
    private $sandbox,$appId,$appToken,$accountId;

    public function __construct()
    {
        $this->sandbox = env('env_local');
        $this->appId = env('stripe.public_key');
        $this->accountId = env('stripe.private_key');
        $this->appToken = env('stripe.merchant_token');
    }

    public function placeOrder(array $params = [])
    {
        try{
            if (!$this->checkToken($params)) return apiError();
            $wePayUrl = $this->sandbox ? 'https://stage-api.wepay.com': 'https://api.wepay.com';

            $postData = array (
                'account_id' => $this->accountId,
                'amount' => $params['amount'],
                'currency' => $params['currency'],
                'payment_method' =>
                    array (
                        'credit_card' =>
                            array (
                                'auto_update' => false,
                                'card_holder' =>
                                    array (
                                        'address' =>
                                            array (
                                                'city' => $params['city'],
                                                'country' => $params['country'],
                                                'line1' => $params['address1'],
                                                'line2' => $params['address2'],
                                                'postal_code' => $params['zip'],
                                                'region' => $params['state'],
                                            ),
                                        'email' => $params['email'],
                                        'holder_name' => $params['first_name'] . ' '.$params['last_name'],
                                    ),
                                'card_number' => str_replace(' ','',$params['card_number']),
                                'cvv' => $params['cvc'],
                                'expiration_month' => $params['expiry_month'],
                                'expiration_year' => $params['expiry_year'],
                            ),
                        'custom_data' =>
                            array (
                                'my_key' => 'invoice #54321',
                            ),
                        'type' => 'credit_card',
                    ),
                'auto_capture' => true,
                'custom_data' =>
                    array (
                        'my_key' => 'invoice #'.$params['order_no'],
                    ),
                'initiated_by' => 'customer',
                'reference_id' => randomStr(10),
            );

            $headers = [
                'Content-Type:application/json',
                'Accept:application/json',
                'App-Id:'.$this->appId,
                'App-Token:'.$this->appToken,
                'Api-Version:3.0',
                'Unique-Key:'.randomStr(10)
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $wePayUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            $response = curl_exec($ch);
            $curlErr = curl_error($ch);
            if ($curlErr){
                generateApiLog("CURL ERROR:{$curlErr}");
                curl_close($ch);
                return apiError();
            }
            curl_close($ch);
            $result = json_decode($response,true);
            $transactionId = 0;
            $status = 'failed';
            if (isset($result['error_code']))
            {
                $errorReason = $result['error_message'];
            }else{
                $transactionId = $result['id'];
                $status = $response['status'] = '' ? 'success' : 'failed';
                $errorReason = $result['failure_reason'];
            }

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
            if($status == 'success') {
                $this->validateCard(true);
                return apiSuccess(['success_risky' => $riskyFlag]);
            }
            $this->validateCard(false);
            return apiError();
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