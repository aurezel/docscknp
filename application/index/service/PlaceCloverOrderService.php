<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 10:23
 */

namespace app\index\service;


class PlaceCloverOrderService extends BaseService
{
    private $billing;
    private $shipping;
    private $order;
    private $responses;

    /**
     * 调用First Data的支付 by zhuzh
     * @return \think\response\Json
     */
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

                    $PUBLIC_TOKEN = env('stripe.public_key');
                    $PRIVATE_TOKEN = env('stripe.private_key');

//                    $amount = $postData['amount'];
//                    for($i = 0; $i < $currency_dec[strtoupper($postData['currency_code'])]; $i++) {
//                        $amount *= 10;
//                    }

                    //月份补零
                    $postData['expiry_month'] = str_pad($postData['expiry_month'],2,"0",STR_PAD_LEFT);

                    $card_number = $this->processInput($postData['card_number']);
                    $card_cvv = $this->processInput($postData['cvc']);
                    $amount = $this->processInput($postData['amount']);
                    $currency_code = $this->processInput($postData['currency']);
                    for($i = 0; $i < $currency_dec[strtoupper($postData['currency_code'])]; $i++) {
                        $amount *= 10;
                    }

                    $first6 = substr($card_number,0,6);
                    $last4 = substr($card_number,-4,4);

                    $carData = [
                        'Visa' => 'VISA',
                        'Mastercard' => 'MC',
                        'AMEX' => 'AMEX',
                        'Discover' => 'DISCOVER',
                        'Diners' => 'DINERS_CLUB',
                        'Diners - Carte Blanche' => 'DINERS_CLUB',
                        'JCB' => 'JCB'
                    ];

                    if(!isset($carData[$postData['card_type']])){
                        return apiError('This card type is not supported');
                    }

                    //创建卡的Id
                    $body = [
                        'card' =>[
                            'number' => $card_number,
                            'exp_month' => $postData['expiry_month'],
                            'exp_year' => $postData['expiry_year'],
                            'cvv' => $card_cvv,
                            'last4' => $last4,
                            'first6' => $first6,
                            'brand' => $carData[$postData['card_type']]
                        ]
                    ];
                    $body = json_encode($body);

                    //header头设置
                    $headers = [
                        'Accept:application/json',
                        'Content-Type:application/json',
                        'apikey:'.$PUBLIC_TOKEN,
                    ];

                    $url = env('local_env') ? 'https://token-sandbox.dev.clover.com/v1/tokens' : 'https://token.clover.com/v1/tokens';
                    $con = $this->get_curl_content($url, "POST", $headers, $body);

                    $result = [];
                    if(isset($con['error']['message'])){
                        //number报错
                        $result['status'] = 'failed';
                        $result['msg'] = $con['error']['message'];
                        $result['ref_num'] = '';
                    }elseif(isset($con['message'])){
                        //brand报错
                        $result['status'] = 'failed';
                        $result['msg'] = $con['message'];
                        $result['ref_num'] = '';
                    }else{
                        $url1 = env('local_env') ? 'https://scl-sandbox.dev.clover.com/v1/charges' : 'https://scl.clover.com/v1/charges';
                        $body1 = '{"ecomind":"ecom","amount":'.$amount.',"currency":"'.$currency_code.'","source":"'.$con['id'].'"}';
                        $headers1 = [
                            'Accept:application/json',
                            'Content-Type:application/json',
                            'Authorization:Bearer '.$PRIVATE_TOKEN,
                        ];
                        $result = $this->get_curl_content($url1, "POST", $headers1, $body1);
                        if(isset($result['error']['message'])){
                            //currency报错和source报错
                            $result['status'] = 'failed';
                            $result['msg'] = $result['error']['message'];
                        }elseif(isset($result['message'])){
                            //ecomind报错
                            $result['status'] = 'failed';
                            $result['msg'] = $result['message'];
                        }elseif(empty($result)){
                            //amount报错
                            $result['status'] = 'failed';
                            $result['msg'] = 'Charge amount in cents';
                        }else{
                            $result['msg'] = '';
                        }
                    }


                    $payment_status = isset($result['status']) && $result['status'] == 'succeeded' ? 'success' : 'failed';
                    if($payment_status == 'success') {
                        $this->validateCard(true);
                    } else {
                        $this->validateCard(false);
                    }

                    $postCenterData = [
                        'transaction_id' => isset($result['ref_num']) ? $result['ref_num'] : 0,
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
        $str = json_decode($str, true);

        return $str;
    }


}

