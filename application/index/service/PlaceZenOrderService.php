<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 10:23
 */

namespace app\index\service;


use Zen\Payment\Util;

class PlaceZenOrderService extends BaseService
{
    
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $centralIdFile = app()->getRootPath() .DIRECTORY_SEPARATOR.'file'.DIRECTORY_SEPARATOR.$params['center_id'] .'.txt';
        if (!file_exists($centralIdFile)) die('文件不存在');
        $fData = json_decode(file_get_contents($centralIdFile),true);
        $cid = customEncrypt($params['center_id']);
        $baseUrl = request()->domain();
        $params['processURL'] = $baseUrl  . '/checkout/pay/zenProcess?cid='.$cid;
        $params['successURL'] = $baseUrl  . '/checkout/pay/zenSuccess?cid='.$cid;
        $params['cancelURL'] = $baseUrl  . '/checkout/pay/zenCancel?cid='.$cid;

        //zen payment
        $order_data = $this->prepareOrderData(
            $params['amount'],
            strtoupper($params['currency_code']),
            $params['order_no'],
            $params['first_name'],
            $params['last_name'],
            $params['email'],
            $params['successURL'],  //成功
            $params['cancelURL'],  //失败
            $params['successURL'],  //返回
            $params['processURL'],  //通知
            [],
            env('stripe.public_key'), //payment_zen_terminal_uuid
            env('stripe.private_key'), //payment_zen_paywall_secret
            'OpenCart',
            '3.0.2.0',
            'Zen',
            '2.0.3'
        );
        $paymentRequest = $this->createPayment(json_encode($order_data));

        if ($paymentRequest['success'] != 1)
        {
            generateApiLog("生成支付链接失败");
            return apiError();
        }

        $fData['ts_id'] = $params['center_id'];
        file_put_contents($centralIdFile,json_encode($fData));

        $checkoutUrl = $paymentRequest['body']['redirectUrl'] ?? '';
        generateApiLog(['checkoutUrl' => $checkoutUrl]);
        if (empty($checkoutUrl)) return apiError();
        return apiSuccess($checkoutUrl);
    }

    private function processInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return strval($data);
    }

    /**
     * @param string $paymentData
     *
     * @return array
     */
    public function createPayment($paymentData)
    {
        return $this->call(
            'https://secure.zen.com/api/checkouts',
            'POST',
            $paymentData,
            true
        );
    }

    /**
     * @param string $url
     * @param string $methodRequest
     * @param string $body
     * @param bool $decode
     *
     * @return array
     */
    private function call($url, $methodRequest, $body, $decode = false)
    {

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $methodRequest);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        $resultCurl = curl_exec($curl);

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if (($httpCode >= 300) || !$resultCurl) {
            return [
                'success' => false,
                'data' => [
                    'httpCode' => $httpCode,
                    'error' => curl_error($curl),
                    'body'  => json_decode($resultCurl, true),
                ],
            ];
        }

        if ($decode) {
            return [
                'success' => true,
                'body' => json_decode($resultCurl, true),
            ];
        }

        return [
            'success' => true,
            'body' => $resultCurl,
        ];
    }

    /**
     * DO NOT CHANGE ORDER OF CREATED KEYS - IT'S REQUIRED TO CALCULATE SIGNATURE
     * @param int $amount
     * @param string $currency
     * @param string $orderId
     * @param string $customerFirstName
     * @param string $customerLastName
     * @param string $customerEmail
     * @param string $urlSuccess
     * @param string $urlFailure
     * @param string $urlReturn
     * @param string $urlIpn
     * @param array $items
     * @param string $pluginName
     * @param string $pluginVersion
     * @param string $platformName
     * @param string $platformVersion
     *
     * @return array
     */
    public  function prepareOrderData(
        $amount, $currency, $orderId, $customerFirstName, $customerLastName,
        $customerEmail, $urlSuccess, $urlFailure, $urlReturn, $urlIpn, $items,
        $terminalId, $paywallSecret,
        $pluginName, $pluginVersion, $platformName, $platformVersion
    )
    {
        $data = [];

        $data['amount'] = strval($amount);
        $data['currency'] = $currency;
        $data['customer']['email'] = $customerEmail;
        $data['customer']['firstName'] = $customerFirstName;
        $data['customer']['lastName'] = $customerLastName;
        $data['customIpnUrl'] = $urlIpn;

        foreach ($items as $key => $value) {
            $data['items'][$key]['lineAmountTotal'] = strval($value['lineAmountTotal']);

            if (isset($value['name']) && $value['name']) {
                $data['items'][$key]['name'] = $value['name'];
            }

            $data['items'][$key]['price'] = strval($value['price']);
            $data['items'][$key]['quantity'] = strval($value['quantity']);
        }

        $data['merchantTransactionId'] = $orderId . '#' . uniqid();
        $data['sourceAdditionalData']['platformName'] = $platformName;
        $data['sourceAdditionalData']['platformVersion'] = $platformVersion;
        $data['sourceAdditionalData']['pluginName'] = $pluginName;
        $data['sourceAdditionalData']['pluginVersion'] = $pluginVersion;
        $data['terminalUuid'] = trim($terminalId);
        $data['urlFailure'] = $urlFailure;
        $data['urlRedirect'] = $urlReturn;
        $data['urlSuccess'] = $urlSuccess;

        $signature = $this->createSignature($data, trim($paywallSecret));
        $data['signature'] = $signature;

        return $data;
    }

    /**
     * @param array $orderData
     * @param string $serviceKey
     * @param string $hashMethod
     *
     * @return string|bool
     */
    private function createSignature($orderData, $serviceKey, $hashMethod = 'sha256')
    {
        $isHashMethodSupported = $this->getHashMethod($hashMethod);

        if (!$isHashMethodSupported || !is_array($orderData)) {
            return false;
        }

        $hashData = $this->prepareHashData($orderData);

        return $this->hashSignature($hashMethod, $hashData, $serviceKey) . ';' . $hashMethod;
    }

    /**
     * @param array $data
     * @param string $prefix
     *
     * @return string
     */
    public  function prepareHashData($data, $prefix = '')
    {
        $hashData = [];

        foreach ($data as $key => $value) {

            if ($prefix) {
                $key = $prefix . (is_numeric($key) ? ('[' . $key . ']') : ('.' . $key));
            }

            if (is_array($value)) {
                $hashData[] = $this->prepareHashData($value, $key);
            } else {
                $hashData[] = mb_strtolower($key, 'UTF-8') . '=' . mb_strtolower($value, 'UTF-8');
            }
        }

        return implode('&', $hashData);
    }

    /**
     * @param string $hashMethod
     * @param string $data
     * @param string $serviceKey
     *
     * @return string
     */
    public  function hashSignature($hashMethod, $data, $serviceKey)
    {
        return hash($hashMethod, $data . $serviceKey);
    }

    /**
     * @param string $hashMethod
     *
     * @return string
     */
    public  function getHashMethod($hashMethod)
    {
        $hashMethods = [
        'sha224' => 'sha224',
        'sha256' => 'sha256',
        'sha384' => 'sha384',
        'sha512' => 'sha512',
        ];

        if (isset($hashMethods[$hashMethod])) {
            return $hashMethods[$hashMethod];
        }

        return '';
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

