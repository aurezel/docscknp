<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 10:23
 */

namespace app\index\service;


class PlaceValorOrderService extends BaseService
{

    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $postData = $params;
        $currency_dec = config('parameters.currency_dec');

        try {
            if(isset($currency_dec[strtoupper($postData['currency_code'])])) {
                try {
                    //请求支付交易
                    $postData['card_number'] = str_replace(' ', '', $postData['card_number']);
                    if(empty($postData['card_number']) || empty($postData['cvc']) || empty($postData['expiry_month']) || empty($postData['expiry_year']) || empty($postData['card_type'])){
                        return apiError('card info error');
                    }

                    //月份补零
                    $postData['expiry_month'] = str_pad($postData['expiry_month'],2,"0",STR_PAD_LEFT);

                    $card_number = $this->processInput($postData['card_number']);
                    $card_cvv = $this->processInput($postData['cvc']);
                    $amount = $this->processInput($postData['amount']);

                    //header头设置
                    $headers = [
                        'Content-Type:application/json'
                    ];

                    $data = array();
                    $data['appid'] = env('stripe.public_key');
                    $data['appkey'] = env('stripe.private_key');
                    $data['epi'] = env('stripe.merchant_token');
                    $data['txn_type'] = 'sale';
                    $data['surchargeIndicator'] = 0;
                    $data['address1'] = $postData['address1'];
                    $data['address2'] = $postData['address2'];
                    $data['city'] = $postData['city'];
                    $data['state'] = $postData['state'];
                    $data['zip'] = $postData['zip'];
                    $data['phone'] = $postData['phone'];
                    $data['ip'] = get_real_ip();
                    $data['email'] = $postData['email'];
                    $data['orderdescription'] = '';
                    $data['amount'] = $amount;
                    $data['cardnumber'] = $card_number;
                    $data['cardholdername'] = $postData['first_name'] . ' ' . $postData['last_name'];
                    $data['expirydate'] = $postData['expiry_month'] . $postData['expiry_year'];
                    $data['cvv'] = $card_cvv;
                    $data['invoicenumber'] = strval($postData['order_no']);
                    $data['shipping_country'] = $postData['country'];
                    $body = json_encode($data);

                    $url = env('local_env') ? 'https://securelinktest.valorpaytech.com:4430/' : 'https://securelink.valorpaytech.com:4430/';
                    $response_data = $this->get_curl_content($url, "POST", $headers, $body);

                    $result = [];
                    if ($response_data['error_no'] == 'S00') {
                        $result['msg'] = '';
                        $result['status'] = 'succeeded';
                        $result['transaction_id'] = $response_data['txnid'];
                    } else {
                        $error = '';

                        if (isset($response_data['mesg'])) {
                            $error .= $response_data['mesg'];
                        }
                        if (isset($response_data['desc'])) {
                            $error .= $response_data['desc'];
                        }

                        $result['msg'] = $error;
                        $result['status'] = 'failed';
                        $result['transaction_id'] = '';
                    }

                    $payment_status = isset($result['status']) && $result['status'] == 'succeeded' ? 'success' : 'failed';
                    if($payment_status == 'success') {
                        $this->validateCard(true);
                    } else {
                        $this->validateCard(false);
                    }

                    $postCenterData = [
                        'transaction_id' => isset($result['transaction_id']) ? $result['transaction_id'] : 0,
                        'center_id' => $postData['center_id'] ?? 0,
                        'action' => 'create',
                        'status' => $payment_status,
                        'failed_reason' => $result['msg']
                    ];

                    $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
                    if (!isset($sendResult['status']) or $sendResult['status'] == 0)
                    {
                        generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
                        return apiError('send error');
                    }

                    $riskyFlag = $sendResult['data']['success_risky'] ?? false;
                    if(isset($result['status']) && $result['status'] == 'succeeded') {
                        return apiSuccess(['success_risky' => $riskyFlag]);
                    }

                    return apiError($result['msg']);

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

    public function get_curl_content($url, $method, $headers, $body){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if($method == 'POST'){
            curl_setopt($ch, CURLOPT_POST, 1);
        }

        if($method == 'PATCH'){
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
        }

        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        $str = curl_exec($ch);
        //print_r(curl_errno($ch));
        curl_close($ch);
        $result = json_decode($str, true);
        if($result == null) {
            generateApiLog(REFERER_URL .'收到异常响应 ' . $str);
        }

        return $result;
    }


}

