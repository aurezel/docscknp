<?php
/**
 * Created by PhpStorm.
 * User: HJL
 * Date: 2023/4/29 13:45
 */

namespace app\index\service;

use MercadoPago;
class PlaceMercadopagoOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $private_key = env('stripe.private_key');
        $description = "Order #" . substr(time(), -6, 6) . mt_rand(10, 99);
        $contents = json_decode(file_get_contents('php://input'), true);
        try{
            $cid = customEncrypt($params['center_id']);
            $amount = (float) $contents['amount'];
            $zipcode = $contents['zip'];
            $address = $contents['address1'];
            $city = $contents['city'];
            $state = $contents['state'];
            $country = $contents['country'];
            $phone = $contents['phone'];
            $firstName = $contents['first_name'];
            $lastName = $contents['last_name'];
            $notifyUrl = request()->domain() . '/checkout/pay/mercadoProcess?cid=' . $cid;

            MercadoPago\SDK::setAccessToken($private_key);
            MercadoPago\SDK::setMultipleCredentials(
                array(
                    'X-meli-session-id' =>$contents['deviceId']
                )
            );
            $payment = new MercadoPago\Payment();
            $payment->transaction_amount = $amount;
            $payment->token = $contents['pay_token'];
            $payment->description = $description;
            $payment->installments = (int)$contents['installments'];
            $payment->payment_method_id = $contents['paymentMethodId'];
            $payment->issuer_id = (int)$contents['issuerId'];
            $payment->notification_url = $notifyUrl;
            $payment->statement_descriptor = $description;
            $payment->external_reference = substr(time(), -6, 6) . mt_rand(10000, 99999);

            $payer = new MercadoPago\Payer();
            // user info
            $payer->email = $contents['email'];
            $payer->first_name = $firstName;
            $payer->last_name = $lastName;
            // address info
            $payer->address = array(
                'zip_code' => $zipcode,
                'street_name' => $address,
                'federal_unit' => $state,
                'city' => $city,
                'street_number' => '',
                'neighborhood' => ''
            );

            $payment->payer = $payer;
            // additional info
            $payment->additional_info = array(
                'ip_address' => get_real_ip(),
                'payer' => array(
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => array(
                        'number' => $phone
                    ),
                    'address' => array(
                        'zip_code' => $zipcode,
                        'street_name' => $address . ' / ' . $city . ' '.$state. ' '.$country
                    )
                ),
                'shipments' => array(
                    'receiver_address' => array(
                        'zip_code' => $zipcode,
                        'street_name' => $address . ' ' . $city . ' ' . $state .' ' . $country,
                        'apartment' => '',//address2
                        'city_name' => $city,
                        'state_name' => $state
                    )
                )
            );

           $payment->save();
            $status = 'failed';
            $failed_reason = '';
            if ($payment->status == 'approved') {
                $status = 'success';
            } else {
                $failed_reason = $payment->status . " " . $payment->status_detail;
            }
            $postCenterData = [
                'transaction_id' => $payment->id,
                'center_id' => $contents['center_id'] ?? 0,
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
        }catch (\Exception $ex)
        {
            $orderNo = $contents['order_no'] ?? 0;
            $centerId = $contents['center_id'] ?? 0;
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
}