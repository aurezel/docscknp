<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/2/25
 * Time: 14:20
 */

namespace app\index\service;

class PlaceRapydApiOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $centralIdFile = app()->getRootPath() . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR . $params['center_id'] . '.txt';
        if (!file_exists($centralIdFile)) return apiError();
        $cid = customEncrypt($params['center_id']);
        $baseUrl = request()->domain();

        $statement = env('stripe.COMPANY');
        if (empty($statement)) $statement = explode('.',request()->rootDomain())[0];
        $productDescription = env('stripe.description');
        if (empty($productDescription)) $productDescription = 'goods';
        $referenceId = $statement .'-'.intval($params['center_id']);
        $sPath = env('stripe.checkout_success_path');
        $cPath = env('stripe.checkout_cancel_path');
        $successPath = empty($sPath) ? '/checkout/pay/rdRedirect' : $sPath;
        $cancelPath = empty($cPath) ? '/checkout/pay/rdRedirect' : $cPath;
        $cancel_checkout_url = $baseUrl . $cancelPath . "?r_type=f&cid=$cid";
        $complete_checkout_url = $baseUrl . $successPath ."?r_type=s&cid=$cid";

        try {
            $cardNumber = self::processInput(str_replace(' ','',$params['card_number']));
            $countrySwitch = env('stripe.ONLY_EUROPE_CARD',false);
            if ($countrySwitch && !$this->isEuropeCountry($cardNumber)) return apiError('Only support European countries credit card.');
            $fullName = str_replace(['-',"'",'.','_',','],['','','','',''],$params['first_name'] . ' ' . $params['last_name']);
            $zip = str_replace(['-',"'",'.','_',',','+','(',')'],['','','','','','','',''],$params['zip']);
            $currency = $params['currency'];
            $customerBody = [
                'addresses' => [
                    [
                        "name" => $fullName,
                        "line_1" => $params['address1'],
                        "line_2" => $params['address2'],
                        "line_3" => "",
                        "city" => $params['city'],
                        "district" => "",
                        "canton" => "",
                        "state" => $params['state'],
                        "country" => $params['country'],
                        "zip" => $zip,
                        "metadata" => array(
                            "merchant_defined" => true
                        )
                    ]
                ],
                'name' => $fullName,
                'email' => $params['email']
            ];

            $firstDigit = substr($cardNumber, 0, 1);
            if($firstDigit != '4' && $firstDigit != '5' && $firstDigit != '2') {
                return apiError('VISA and Master card only!');
            }
            $cardType = 'gb_mastercard_card';
            if($firstDigit == '4') {
                $cardType = 'gb_visa_card';
            }

            $body = [
                "amount" =>  floatval($params['amount']),
                "complete_payment_url" => $complete_checkout_url,
                'error_payment_url' => $cancel_checkout_url,
                "currency" => $currency,
                'description' => $productDescription,
                'statement_descriptor' => $statement,
                'merchant_reference_id' => $referenceId,
                'customer' => $customerBody,
                'ewallet' => env('stripe.merchant_token'),
                'payment_method' => [
                    'type' => $cardType,
                    'fields' => [
                        'number' => $cardNumber,
                        'expiration_month' => str_pad(self::processInput($params['expiry_month']),'2','0',STR_PAD_LEFT),
                        'expiration_year' => self::processInput($params['expiry_year']),
                        'name' => $fullName,
                        'cvv' => self::processInput($params['cvc']),
                    ],
                    'metadata' => null
                ],
                'capture' => true,
                'expiration' => strtotime( "+7 day" )
            ];

            if (!in_array($currency,['USD','EUR','GBP']))
            {
                $body['fixed_side'] = 'sell';
                $body['requested_currency'] = 'USD';
            }

            $object = $this->makeRequest('post', '/v1/payments', $body);
            if (!isset($object['status']['status'])) return apiError();
            if ($object['status']['status'] === 'SUCCESS')
            {
                if ($object['data']['status'] == 'CLO')
                {
                    $this->validateCard(true);
                    return apiSuccess();
                }elseif ($object['data']['status'] == 'ACT')
                {
                    $this->validateCard();
                    return apiSuccess(['url' => $object['data']['redirect_url']]);
                }
            }else{
                $errorMsg = $object['status']['message'];
                $this->sendDataToCentral('failed',$params['center_id'],0,$errorMsg);
                $this->validateCard();
                return apiError($errorMsg);
            }
            return apiError($object['status']['message']);
        } catch(\Exception $e) {
            generateApiLog('Rapyd Api接口异常:'.$e->getMessage() .',line:'.$e->getLine().',trace:'.$e->getTraceAsString());
        }
        return apiError();
    }

    public function isEuropeCountry($cardNumber): bool
    {
        $europeCountries = array('AL','AD','AT','EU','BY','BE','BA','BG','HR','CY','CZ','DK','EE','FO','FI','FR','DE','GI','GR','GL','HU','IS','IE','IT','LV','LI','LT','LU','MK','MT','MD','MC','ME','NL','NO','PL','PT','RO','RU','SM','RS','SK','SI','ES','SE','CH','UA','GB','VA');
        $url = 'https://api.iinlist.com/cards?iin=' . substr($cardNumber, 0, 8);
        $ch = curl_init($url);
        $apiKey = 'QsmLWcfSj29hFJ6KFIGS6aDSxmGES0k14p1oJSmP';
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-API-Key:$apiKey",
            'Accept: application/hal+json; charset=utf-8; version=1'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            generateApiLog("[$cardNumber] 获取ISO2 CURL请求错误:" . curl_error($ch));
            return false;
        }
        $responseObj = json_decode($response);
        if (200 !== curl_getinfo($ch, CURLINFO_HTTP_CODE))
        {
            generateApiLog("[$cardNumber] 获取ISO2 CURL响应错误:" . $response);
            return false;
        }
        if ($responseObj->count == 0 || !isset($responseObj->_embedded))
        {
            generateApiLog("[$cardNumber] 不存在ISO2." . $response);
            return false;
        }

        $flag = false;
        foreach ($responseObj->_embedded->cards as $card)
        {
            if (in_array($card->account->country->code,$europeCountries))
            {
                $flag = true;
                break;
            }
        }
        return $flag;
    }

    public function makeRequest($method, $path, $body = null) {
        $base_url = 'https://api.rapyd.net';
        if (env('local_env')) $base_url = 'https://sandboxapi.rapyd.net';

        $access_key = env('stripe.public_key');     // The access key received from Rapyd.
        $secret_key = env('stripe.private_key'); //     // Never transmit the secret key by itself.

        $idempotency = randomStr(12);      // Unique for each request.
        $http_method = $method;                // Lower case.
        $salt = randomStr(12);             // Randomly generated for each request.
        $date = new \DateTime();
        $timestamp = $date->getTimestamp();    // Current Unix time.

        $body_string = !is_null($body) ? json_encode($body,JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $sig_string = "$http_method$path$salt$timestamp$access_key$secret_key$body_string";

        $hash_sig_string = hash_hmac("sha256", $sig_string, $secret_key);
        $signature = base64_encode($hash_sig_string);

        $request_data = NULL;

        if ($method === 'post') {
            $request_data = array(
                CURLOPT_URL => "$base_url$path",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body_string,
                CURLOPT_SSL_VERIFYPEER => 0

            );
        }elseif ($method === 'delete'){
            $request_data = array(
                CURLOPT_URL => "$base_url$path",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_CUSTOMREQUEST => 'DELETE',
                CURLOPT_SSL_VERIFYPEER => 0
            );
        } else {
            $request_data = array(
                CURLOPT_URL => "$base_url$path",
                CURLOPT_RETURNTRANSFER => true,
            );
        }

        $curl = curl_init();
        curl_setopt_array($curl, $request_data);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "access_key: $access_key",
            "salt: $salt",
            "timestamp: $timestamp",
            "signature: $signature",
            "idempotency: $idempotency"
        ));

        $response = curl_exec($curl);
        generateApiLog($response);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            throw new \Exception("cURL Error #:".$err);
        } else {
            return json_decode($response, true);
        }
    }

    public function sendDataToCentral($status, $center_id, $payment_id = 0, $msg = '')
    {
        if (!in_array($status, ['success', 'failed'])) return false;
        // 发送到中控
        $postCenterData = [
            'transaction_id' => $payment_id,
            'center_id' => $center_id,
            'action' => 'create',
            'status' => $status,
            'failed_reason' => $msg
        ];
        $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL, $postCenterData, CURL_HEADER_DATA), true);
        if (!isset($sendResult['status']) or $sendResult['status'] == 0) {
            generateApiLog(REFERER_URL . '创建订单传送信息到中控失败：' . json_encode($sendResult));
            return false;
        }
        return true;
    }

    private static function processInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return strval($data);
    }
}