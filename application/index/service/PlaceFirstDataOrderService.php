<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 10:23
 */

namespace app\index\service;


class PlaceFirstDataOrderService extends BaseService
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

                    $serviceURL = env('local_env') ? 'https://api-cert.payeezy.com/v1/transactions' : 'https://api.payeezy.com/v1/transactions';
                    $apiKey = env('stripe.public_key');
                    $apiSecret = env('stripe.private_key');
                    $token = env('stripe.merchant_token');

                    $nonce = strval(hexdec(bin2hex(openssl_random_pseudo_bytes(4, $cstrong))));
                    $timestamp = strval(time()*1000); //time stamp in milli seconds

                    $amount = $postData['amount'];
                    for($i = 0; $i < $currency_dec[strtoupper($postData['currency_code'])]; $i++) {
                        $amount *= 10;
                    }

                    //月份补零
                    $postData['expiry_month'] = str_pad($postData['expiry_month'],2,"0",STR_PAD_LEFT);

                    $card_holder_name = $this->processInput($postData['last_name'].' '.$postData['first_name']);
                    $card_number = $this->processInput($postData['card_number']);
                    $card_type = $this->processInput(strtolower($postData['card_type']));
                    $card_cvv = $this->processInput($postData['cvc']);
                    $card_expiry = $this->processInput($postData['expiry_month'].$postData['expiry_year']);
                    $amount = $this->processInput($amount);
                    $currency_code = $this->processInput($postData['currency']);
                    $merchant_ref = $this->processInput($postData['center_id']);

                    $primaryTxPayload = array(
                        "amount"=> $amount,
                        "card_number" => $card_number,
                        "card_type" => $card_type,
                        "card_holder_name" => $card_holder_name,
                        "card_cvv" => $card_cvv,
                        "card_expiry" => $card_expiry,
                        "merchant_ref" => $merchant_ref,
                        "currency_code" => $currency_code,
                    );

                    $payload = $this->getPayload($primaryTxPayload);
                    $data = $apiKey . $nonce . $timestamp . $token . $payload;
                    $hashAlgorithm = "sha256";
                    $hmac = hash_hmac ( $hashAlgorithm , $data , $apiSecret, false );
                    $hmac_enc = base64_encode($hmac);

                    $curl = curl_init($serviceURL);
                    $headers = array(
                        'Content-Type: application/json',
                        'apikey:'.strval($apiKey),
                        'token:'.strval($token),
                        'Authorization:'.$hmac_enc,
                        'nonce:'.$nonce,
                        'timestamp:'.$timestamp,
                    );
                    curl_setopt($curl, CURLOPT_HEADER, false);
                    curl_setopt($curl, CURLOPT_POST, true);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($curl, CURLOPT_VERBOSE, true);
                    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    $json_response = curl_exec($curl);
                    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                    $response = json_decode($json_response, true);
                    curl_close($curl);

                    generateApiLog($json_response);
                    $transaction_messages = '';
                    if (in_array($status, array(200, 201, 202))) {
                        // This clause now deals with an approved transaction.
                        if ($response['transaction_status'] === 'approved') {

                        }

                        elseif ($response['transaction_status'] === 'declined') {
                            // 238 = invalid currency
                            // 243 = invalid Level 3 data, or card not suited for Level 3
                            // 258 = soft_descriptors not allowed/configured on this merchant account
                            // 260 = AVS - Authorization network could not reach the bank which issued the card
                            // 264 = Duplicate transaction; rejected.
                            // 301 = Issuer Unavailable. Try again.
                            // 303 = (Generic) Processor Decline: no other explanation offered
                            // 351, 353, 354 = Transarmor errors

                            // 301 means timeout, try again, because Authorization network could not reach the bank which issued the card
                            // if ($response['bank_resp_code'] == 301) {
                            //     $response = $this->postTransaction($payload, $this->hmacAuthorizationToken($payload));
                            //     $this->logTransactionData($response, $payload_logged);
                            // }

                            // Check for soft-descriptor failure, and resubmit without it.
                            if ($response['bank_resp_code'] == 258 || $response['bank_resp_code'] == 243) {

                                $transaction_messages = $response['bank_resp_code'] . ' ' . $response['bank_message'] . ' ' . $response['gateway_resp_code'] . ' ' . $response['gateway_message'];
                                if (isset($response['avs'])) {
                                    $transaction_messages .= 'AVS: ' . $this->setAvsCvvMeaningsToAvs($response['avs']);
                                }

                                if (isset($response['cvv2'])) {
                                    $transaction_messages .= 'CVV: ' . $this->setAvsCvvMeaningsToCvv($response['cvv2']);
                                }

                            }


                            // check if card is flagged for fraud
                            /*if (in_array($response['bank_resp_code'], array(500, 501, 502, 503, 596, 534, 524, 519))) {
                                $transaction_messages = 'error';
                            }*/
                            if(empty($transaction_messages) && isset($response['bank_message'])) {
                                $transaction_messages = $response['bank_message'];
                            }
                        }

                    }
                    elseif ($status == 400) {
                        $msg = '';
                        if(isset($response['Error']['messages']['description'])){
                            $msg = $response['Error']['messages']['description'];
                        }else{
                            foreach ($response['Error']['messages'] as $resp) {
                                $msg .= $resp['description'];
                            }
                        }

                        $transaction_messages = $msg;
                    }

                    // invalid API key and token
                    elseif ($status == 401) {
                        $transaction_messages = '401 Bad Token';
                    }

                    elseif ($status == 403) {
                        $transaction_messages = '403 Bad HMAC';
                    }

                    // bad transaction call
                    elseif ($status == 404) {
                        $transaction_messages = '404 Failed';
                    }

                    // error at Payeezy. Call tech support
                    elseif (in_array($status, array(500, 502, 503, 504))) {
                        $transaction_messages = '500 Processor Error';
                    }

                    $payment_status = isset($response['transaction_status']) && $response['transaction_status'] == 'approved' ? 'success' : 'failed';
                    if($payment_status == 'success') {
                        $this->validateCard(true);
                    } else {
                        $this->validateCard(false);
                    }

                    $postCenterData = [
                        'transaction_id' => isset($response['transaction_id']) ? $response['transaction_id'] : 0,
                        'center_id' => $postData['center_id'] ?? 0,
                        'action' => 'create',
                        'status' => $payment_status,
                        'failed_reason' => $transaction_messages
                    ];

                    $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
                    if (!isset($sendResult['status']) or $sendResult['status'] == 0)
                    {
                        generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
                        return apiError();
                    }

                    $riskyFlag = $sendResult['data']['success_risky'] ?? false;
                    if(isset($response['transaction_status']) && $response['transaction_status'] == 'approved') {
                        return apiSuccess(['success_risky' => $riskyFlag]);
                    }

                    return apiError($transaction_messages);

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

    private function getPayload($args = array())
    {
        $args = array_merge(array(
            "amount"=> "",
            "card_number" => "",
            "card_type" => "",
            "card_holder_name" => "",
            "card_cvv" => "",
            "card_expiry" => "",
            "merchant_ref" => "",
            "currency_code" => "",
            "transaction_tag" => "",
            "split_shipment" => "",
            "transaction_id" => "",

        ), $args);

        $data = "";

        $data = array(
            'merchant_ref'=> $args['merchant_ref'],
            'transaction_type'=> "purchase",
            'method'=> 'credit_card',
            'amount'=> $args['amount'],
            'currency_code'=> strtoupper($args['currency_code']),
            'credit_card'=> array(
                'type'=> $args['card_type'],
                'cardholder_name'=> $args['card_holder_name'],
                'card_number'=> $args['card_number'],
                'exp_date'=> $args['card_expiry'],
                'cvv'=> $args['card_cvv'],
            )
        );

        return json_encode($data, JSON_FORCE_OBJECT);
    }


    private function setAvsCvvMeaningsToCvv($key){
        $cvv_codes['M'] = 'CVV2/CVC2 Match - Indicates that the card is authentic. Complete the transaction if the authorization request was approved.';
        $cvv_codes['N'] = 'CVV2 / CVC2 No Match – May indicate a problem with the card. Contact the cardholder to verify the CVV2 code before completing the transaction, even if the authorization request was approved.';
        $cvv_codes['P'] = 'Not Processed - Indicates that the expiration date was not provided with the request, or that the card does not have a valid CVV2 code. If the expiration date was not included with the request, resubmit the request with the expiration date.';
        $cvv_codes['S'] = 'Merchant Has Indicated that CVV2 / CVC2 is not present on card - May indicate a problem with the card. Contact the cardholder to verify the CVV2 code before completing the transaction.';
        $cvv_codes['U'] = 'Issuer is not certified and/or has not provided visa encryption keys';
        $cvv_codes['I'] = 'CVV2 code is invalid or empty';

        return $cvv_codes[$key];
    }

    private function setAvsCvvMeaningsToAvs($key){
        $avs_codes['X'] = 'Exact match, 9 digit zip - Street Address, and 9 digit ZIP Code match';
        $avs_codes['Y'] = 'Exact match, 5 digit zip - Street Address, and 5 digit ZIP Code match';
        $avs_codes['A'] = 'Partial match - Street Address matches, ZIP Code does not';
        $avs_codes['W'] = 'Partial match - ZIP Code matches, Street Address does not';
        $avs_codes['Z'] = 'Partial match - 5 digit ZIP Code match only';
        $avs_codes['N'] = 'No match - No Address or ZIP Code match';
        $avs_codes['U'] = 'Unavailable - Address information is unavailable for that account number, or the card issuer does not support';
        $avs_codes['G'] = 'Service Not supported, non-US Issuer does not participate';
        $avs_codes['R'] = 'Retry - Issuer system unavailable, retry later';
        $avs_codes['E'] = 'Not a mail or phone order';
        $avs_codes['S'] = 'Service not supported';
        $avs_codes['Q'] = 'Bill to address did not pass edit checks/Card Association cannot verify the authentication of an address';
        $avs_codes['D'] = 'International street address and postal code match';
        $avs_codes['B'] = 'International street address match, postal code not verified due to incompatible formats';
        $avs_codes['C'] = 'International street address and postal code not verified due to incompatible formats';
        $avs_codes['P'] = 'International postal code match, street address not verified due to incompatible format';
        $avs_codes['1'] = 'Cardholder name matches';
        $avs_codes['2'] = 'Cardholder name, billing address, and postal code match';
        $avs_codes['3'] = 'Cardholder name and billing postal code match';
        $avs_codes['4'] = 'Cardholder name and billing address match';
        $avs_codes['5'] = 'Cardholder name incorrect, billing address and postal code match';
        $avs_codes['6'] = 'Cardholder name incorrect, billing postal code matches';
        $avs_codes['7'] = 'Cardholder name incorrect, billing address matches';
        $avs_codes['8'] = 'Cardholder name, billing address, and postal code are all incorrect';
        $avs_codes['F'] = 'Address and Postal Code match (UK only)';
        $avs_codes['I'] = 'Address information not verified for international transaction';
        $avs_codes['M'] = 'Address and Postal Code match';


        return $avs_codes[$key];
    }

    /**
     * Just sets errors. Taken from https://docs.paymentjs.firstdata.com/#authorize-session
     */
    private function setSystemErrorCodes($k)
    {
        $cvv_codes['BAD_REQUEST'] = 'the request body is missing or incorrect for endpoint';
        $cvv_codes['DECRYPTION_ERROR'] = 'failed to decrypt card data';
        $cvv_codes['INVALID_GATEWAY_CREDENTIALS'] = 'gateway credentials failed';
        $cvv_codes['JSON_ERROR'] = 'the request body is either not valid JSON or larger than 2kb';
        $cvv_codes['KEY_NOT_FOUND'] = 'no available key found';
        $cvv_codes['MISSING_CVV'] = 'zero dollar auth requires cvv in form data';
        $avs_codes['NETWORK'] = 'gateway connection error';
        $avs_codes['REJECTED'] = 'the request was rejected by the gateway';
        $avs_codes['SESSION_CONSUMED'] = 'session completed in another request';
        $avs_codes['SESSION_INSERT'] = 'failed to store session data';
        $avs_codes['SESSION_INVALID'] = 'failed to match clientToken with valid record; can occur during deployment';
        $avs_codes['UNEXPECTED_RESPONSE'] = 'the gateway did not respond with the expected data';
        $avs_codes['UNKNOWN'] = 'unknown error';

        return $cvv_codes[$k];
    }
}

