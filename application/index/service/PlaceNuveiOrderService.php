<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 8:58
 */

namespace app\index\service;


use think\Exception;

class PlaceNuveiOrderService extends BaseService
{
    const NUVEI_BROWSERS_LIST = ['ucbrowser', 'firefox', 'chrome', 'opera', 'msie', 'edge', 'safari', 'blackberry', 'trident'];
    const NUVEI_DEVICES_LIST = ['iphone', 'ipad', 'android', 'silk', 'blackberry', 'touch', 'linux', 'windows', 'mac'];
    const NUVEI_DEVICES_TYPES_LIST = ['macintosh', 'tablet', 'mobile', 'tv', 'windows', 'linux', 'tv', 'smarttv', 'googletv', 'appletv', 'hbbtv', 'pov_tv', 'netcast.tv', 'bluray'];

    public function placeOrder(array $params = [])
    {
        $postData = $params;
        $currency_dec = config('parameters.currency_dec');

        try {
            if (!$this->checkToken($params)) return apiError();
            if (isset($currency_dec[strtoupper($postData['currency_code'])])) {
                try {
                    //请求支付交易
                    $postData['card_number'] = str_replace(' ', '', $postData['card_number']);
                    if (empty($postData['card_number']) || empty($postData['cvc']) || empty($postData['expiry_month']) || empty($postData['expiry_year'])) {
                        return apiError('system error');
                    }

                    $amount = $postData['amount'];
                    for ($i = 0; $i < $currency_dec[strtoupper($postData['currency_code'])]; $i++) {
                        $amount *= 10;
                    }

                    //月份补零
                    $postData['expiry_month'] = str_pad($postData['expiry_month'], 2, "0", STR_PAD_LEFT);

                    $card_holder_name = $this->processInput($postData['first_name'] . ' ' . $postData['last_name']);
                    $card_number = $this->processInput($postData['card_number']);
                    $card_cvv = $this->processInput($postData['cvc']);
                    $currency_code = $this->processInput($postData['currency']);
                    $merchant_ref = $this->processInput($postData['center_id']);



                    $transaction_messages = '';

                    $postCenterData = [
                        'transaction_id' => isset($response['transaction_id']) ? $response['transaction_id'] : 0,
                        'center_id' => $postData['center_id'] ?? 0,
                        'action' => 'create',
                        'status' => $payment_status,
                        'failed_reason' => $transaction_messages
                    ];

                    $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL, $postCenterData, CURL_HEADER_DATA), true);
                    if (!isset($sendResult['status']) or $sendResult['status'] == 0) {
                        generateApiLog(REFERER_URL . '创建订单传送信息到中控失败：' . json_encode($sendResult));
                        return apiError();
                    }

                    $riskyFlag = $sendResult['data']['success_risky'] ?? false;
                    if (isset($response['transaction_status']) && $response['transaction_status'] == 'approved') {
                        return apiSuccess(['success_risky' => $riskyFlag]);
                    }

                    return apiError($transaction_messages);

                } catch (\Exception $e) {
                    $postCenterData = [
                        'transaction_id' => 0,
                        'center_id' => $postData['center_id'] ?? 0,
                        'action' => 'create',
                        'status' => 'failed',
                        'failed_reason' => $e->getMessage()
                    ];
                    $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL, $postCenterData, CURL_HEADER_DATA), true);
                    if (!isset($sendResult['status']) or $sendResult['status'] == 0) {
                        generateApiLog(REFERER_URL . 'createOrder创建订单传送信息到中控失败：' . json_encode($sendResult));
                    }
                    throw new \Exception($e->getMessage());
                    //return apiError($e->getMessage());
                }
            }


        } catch (\Exception $ex) {
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

    private function processInput($data)
    {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return strval($data);
    }

    public function nuveiHttp($method, $params,$method_type)
    {
        $merchant_hash = 'sha256';
        $merchant_secret = env('stripe.private_key');
        $concat = '';
        $resp = false;
        $url = $this->get_endpoint_base() . $method . '.do';

        if (isset($params['status']) && 'ERROR' == $params['status']) {
            return $params;
        }

        $time = gmdate('Ymdhis');

        $base_params = array(
            'merchantId' => env('stripe.merchant_token'),
            'merchantSiteId' => env('stripe.public_key'),
            'clientRequestId' => $time . '_' . uniqid(),
            'timeStamp' => $time,
            'webMasterId' => 'WooCommerce 5.7.1; Plugin v2.0.0',
            'sourceApplication' => 'wooCommerce Plugin',
            'encoding' => 'UTF-8',
            'deviceDetails' => $this->get_device_details(),
            'merchantDetails' =>  array(
                'customField3' => time()
            )
        );

        $all_params = array_merge_recursive($base_params, $params);
        // use incoming clientRequestId instead of auto generated one
        if (!empty($params['clientRequestId'])) {
            $all_params['clientRequestId'] = $params['clientRequestId'];
        }

        // add the checksum
        $checksum_keys = $this->get_checksum_params($method,$method_type);

        if (is_array($checksum_keys)) {
            foreach ($checksum_keys as $key) {
                if (isset($all_params[$key])) {
                    $concat .= $all_params[$key];
                }
            }
        }

        $all_params['checksum'] = hash(
            $merchant_hash,
            $concat . $merchant_secret
        );
        // add the checksum END

        $json_post = json_encode($all_params);

        try {
            $header = array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($json_post),
            );

            generateApiLog('Nuvei Request data:' . json_encode(array(
                    'Request URL' => $url,
                    'Request header' => $header,
                    'Request params' => $all_params,
                )));
            // create cURL post
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

            $resp = curl_exec($ch);
            $resp_array = json_decode($resp, true);
            $resp_info = curl_getinfo($ch);

            generateApiLog('Response:' . (is_array($resp_array) ? json_encode($resp_array) : $resp));
            curl_close($ch);

            if (false === $resp) {
                generateApiLog('Response info:' . $resp_info);
                throw new Exception('Response is false');
            }

            return $resp_array;
        } catch (\Exception $e) {
            return array(
                'status' => 'ERROR',
                'message' => 'Exception ERROR when call REST API: ' . $e->getMessage()
            );
        }
    }

    private function get_endpoint_base()
    {
        return env('local_env') ? 'https://ppp-test.nuvei.com/ppp/api/v1/'
            : 'https://secure.safecharge.com/ppp/api/v1/';
    }


    private function get_checksum_params($method,$method_type)
    {
        $result = [];
        switch ($method_type)
        {
            case 'payment':
                $result = ['merchantId', 'merchantSiteId', 'clientRequestId', 'amount', 'currency', 'timeStamp', 'merchantSecretKey'];
                break;
            case 'openOrder':
                $result = ['merchantId', 'merchantSiteId', 'clientRequestId', 'amount', 'currency', 'timeStamp'];
                break;
            case 'sessionToken':
                $result = ['merchantId', 'merchantSiteId', 'clientRequestId', 'timeStamp', 'merchantSecretKey'];
                break;
            case 'notifyUrl':
                if ('voidTransaction' == $method)
                {
                    $result = ['merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'url', 'timeStamp'];
                }
                break;
        }
        return $result;
    }

    private function get_device_details()
    {
        $device_details = array(
            'deviceType' => 'UNKNOWN', // DESKTOP, SMARTPHONE, TABLET, TV, and UNKNOWN
            'deviceName' => 'UNKNOWN',
            'deviceOS' => 'UNKNOWN',
            'browser' => 'UNKNOWN',
            'ipAddress' => '0.0.0.0',
        );

        if (empty($_SERVER['HTTP_USER_AGENT'])) {
            $device_details['Warning'] = 'User Agent is empty.';

            return $device_details;
        }

        $user_agent = strtolower(filter_var($_SERVER['HTTP_USER_AGENT'], FILTER_SANITIZE_STRING));

        if (empty($user_agent)) {
            $device_details['Warning'] = 'Probably the merchant Server has problems with PHP filter_var function!';

            return $device_details;
        }

        $device_details['deviceName'] = $user_agent;

        foreach (self::NUVEI_DEVICES_TYPES_LIST as $d) {
            if (strstr($user_agent, $d) !== false) {
                if (in_array($d, array('linux', 'windows', 'macintosh'), true)) {
                    $device_details['deviceType'] = 'DESKTOP';
                } elseif ('mobile' === $d) {
                    $device_details['deviceType'] = 'SMARTPHONE';
                } elseif ('tablet' === $d) {
                    $device_details['deviceType'] = 'TABLET';
                } else {
                    $device_details['deviceType'] = 'TV';
                }

                break;
            }
        }

        foreach (self::NUVEI_DEVICES_LIST as $d) {
            if (strstr($user_agent, $d) !== false) {
                $device_details['deviceOS'] = $d;
                break;
            }
        }

        foreach (self::NUVEI_BROWSERS_LIST as $b) {
            if (strstr($user_agent, $b) !== false) {
                $device_details['browser'] = $b;
                break;
            }
        }

        // get ip
        if (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip_address = filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP);
        }
        if (empty($ip_address) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP);
        }
        if (empty($ip_address) && !empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip_address = filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP);
        }
        if (!empty($ip_address)) {
            $device_details['ipAddress'] = (string)$ip_address;
        } else {
            $device_details['Warning'] = array(
                'REMOTE_ADDR' => empty($_SERVER['REMOTE_ADDR'])
                    ? '' : filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP),
                'HTTP_X_FORWARDED_FOR' => empty($_SERVER['HTTP_X_FORWARDED_FOR'])
                    ? '' : filter_var($_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP),
                'HTTP_CLIENT_IP' => empty($_SERVER['HTTP_CLIENT_IP'])
                    ? '' : filter_var($_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP),
            );
        }

        return $device_details;
    }
}