<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 9:10
 */

namespace app\index\service;

class PlaceElavonOrderService extends BaseService
{
    private $sandbox,$merchantId,$userId,$pin;

    public function __construct()
    {
        $this->sandbox = env('local_env');
        $this->merchantId = env('stripe.public_key');
        $this->userId = env('stripe.merchant_token');
        $this->pin = env('stripe.private_key');
    }

    public function placeOrder(array $params = [])
    {
        try{
            if (!$this->checkToken($params)) return apiError();
            $first_name = $this->cutStr($params['first_name'],50);
            $last_name = $this->cutStr($params['last_name'],50);
            $state = $this->cutStr($params['state'],2);
            $country = $this->cutStr($params['country'],3);
            $city = $this->cutStr($params['city'],30);
            $zip = $this->cutStr($params['zip'],9);
            $phone = $this->cutStr($params['phone'],20);
            $adrress1 = $this->cutStr($params['address1'],30);

            $submit_data = array(
                'ssl_merchant_id' => $this->merchantId,
                'ssl_user_id' => $this->userId,
                'ssl_pin' => $this->pin,
                'ssl_transaction_type' => 'ccsale',
                'ssl_card_number' => preg_replace('/[^0-9]/', '', $params['card_number']),
                'ssl_exp_date' => preg_replace('/[^0-9]/', '', str_pad($params['expiry_month'],2,"0",STR_PAD_LEFT).$params['expiry_year']),
                'ssl_cvv2cvc2' => preg_replace('/[^0-9]/', '', $params['cvc']),
                'ssl_cvv2cvc2_indicator' => !empty($params['cvc']), // indicates that we are passing a CVV value

                'ssl_amount' => $params['amount'],
                'ssl_invoice_number' => "{$this->cutStr($params['order_no'],20)}",

                'ssl_show_form' => 'false', // collect card data onsite
                'ssl_result_format' => 'ascii', // we can only parse key-value pairs, not an HTML response
                'ssl_get_token' => 'N',
                'ssl_add_token' => 'N',

                'ssl_company' => '',
                'ssl_first_name' => $first_name,
                'ssl_last_name' => $last_name,
                'ssl_avs_address' => $adrress1,
                'ssl_city' => $city,
                'ssl_state' => $state,
                'ssl_avs_zip' => $zip,
                'ssl_country' => $country, //iso3
                'ssl_phone' => $phone,
                'ssl_email' => $this->cutStr($params['email'],100),
                'ssl_ship_to_company' => '',
                'ssl_ship_to_first_name' => $first_name,
                'ssl_ship_to_last_name' => $last_name,
                'ssl_ship_to_address1' => $adrress1,
                'ssl_ship_to_address2' => strlen($params['address1']) > 30 ? substr($params['address1'],30,strlen($params['address1']) - 30) : '',
                'ssl_ship_to_city' => $city,
                'ssl_ship_to_state' => $state,
                'ssl_ship_to_zip' => $zip,
                'ssl_ship_to_country' => $country, //iso3
                'ssl_ship_to_phone' => $phone,
                'ssl_cardholder_ip' => get_real_ip(),
                'ssl_description' => 'Website Purchase from myStore',
            );

            $url = $this->sandbox ? 'https://api.demo.convergepay.com/VirtualMerchantDemo/process.do' :
                'https://api.convergepay.com/VirtualMerchant/process.do';

            $response = $this->parseResponseIntoPairs(sendCurlData($url,$submit_data));

            // Send Data To Central
            $transactionId = 0;
            $errorReason = '';
            $status = 'failed';
            if (isset($response['ssl_result']))
            {
                if ($response['ssl_result'] == '0')
                {
                    $status = 'success';
                    $transactionId = $response['ssl_txn_id'];
                }else{
                    $errorReason = $response['ssl_result_message'] ?? 'Can not get ssl_result_message!';
                }
            }
            if($status == 'success') {
                $this->validateCard(true);
            } else {
                $this->validateCard(false);
            }
            if (isset($response['errorCode']))
            {
                $errorReason = "ErrCode:{$response['errorCode']},ErrMsg:{$response['errorMessage']}";
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

    private function cutStr($char,$limit)
    {
        return strlen($char) > $limit ? substr($char,0,$limit) : $char;
    }

    protected function parseResponseIntoPairs($response)
    {
        $retVal = array();
        $lines = explode("\n", $response);
        if (count($lines) == 0) return $retVal;
        foreach($lines as $line) {
            $pair = explode('=', $line);
            $retVal[$pair[0]] = $pair[1];
        }
        return $retVal;
    }
}