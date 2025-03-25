<?php

namespace app\index\service;

class PlaceWorldPayOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        try {
            if (!$this->checkToken($params)) return apiError();
            $centerId = intval($params['center_id']);
            $firstName = str_replace(['-', "'", '.', '_', ','], ['', '', '', '', ''], $params['first_name']);
            $lastName = str_replace(['-', "'", '.', '_', ','], ['', '', '', '', ''], $params['last_name']);
            $fullName = $firstName . ' ' . $lastName;
            $currency_dec = config('parameters.currency_dec');
            $baseUrl = request()->domain();
            //$baseUrl = 'https://fe99-182-255-32-14.ngrok-free.app';
            $amount = floatval($params['amount']);
            $currency = strtoupper($params['currency']);
            $scale = 1;
            for($i = 0; $i < $currency_dec[$currency]; $i++) {
                $scale*=10;
            }
            $amount = bcmul($amount,$scale);
            $cid = customEncrypt($centerId);
            $now = round(microtime(true) * 1000);

            $requestData = array (
                'transactionReference' => 'NO.'.$centerId . '.'.$now,
                'merchant' =>
                    array (
                        'entity' => env('stripe.merchant_token'),
                    ),
                'narrative' =>
                    array (
                        'line1' => 'MC'.$now,
                    ),
                'value' =>
                    array (
                        'currency' => $currency,
                        'amount' => $amount,
                    ),
                'description' => 'Your item in cart.',
                'billingAddressName' => $fullName,
                'billingAddress' =>
                    array (
                        'address1' => $params['address1'],
                        'address2' => '',
                        'address3' => '',
                        'postalCode' => $params['zip'],
                        'city' => $params['city'],
                        'state' => $params['state'],
                        'countryCode' => $params['country'],
                    ),
                "riskData" => array(
                    "shipping" => array(
                        "firstName" => $firstName,
                        "lastName" => $lastName,
                        "email" => $params['email'],
                    )
                ),
                'resultURLs' =>
                    array (
                        'successURL' => $baseUrl . '/checkout/pay/wdpReturn?type=success&cid='.$cid,
                        'pendingURL' => $baseUrl . '/checkout/pay/wdpReturn?type=pending&cid='.$cid,
                        'failureURL' => $baseUrl . '/checkout/pay/wdpReturn?type=failure&cid='.$cid,
                        'errorURL' => $baseUrl . '/checkout/pay/wdpReturn?type=error&cid='.$cid,
                        'cancelURL' => $baseUrl . '/checkout/pay/wdpReturn?type=cancel&cid='.$cid,
                        'expiryURL' => $baseUrl . '/checkout/pay/wdpReturn?type=expiry&cid='.$cid
                    ),
                'expiry' => '86400', // one day
            );

            $responseData = $this->wpdRequest('/payment_pages',$requestData);
            if (isset($responseData['url']))
            {
                return apiSuccess($responseData);
            }

            if (isset($responseData['errorName'],$responseData['message']))
            {
                $failedMsg = $responseData['message'];
                $result = $this->sendDataToCentral('failed', $centerId, 0,$failedMsg);
                if (!$result)
                {
                    generateApiLog('发送中控失败:'.json_encode(['failed',$centerId,0,$failedMsg]));
                }
                return apiError($failedMsg);
            }
            generateApiLog('WorldPay响应结果异常:'.json_encode($responseData));
        }catch (\Exception $e) {
            generateApiLog('WorldPay接口异常:' . $e->getMessage() . ',line:' . $e->getLine() . ',trace:' . $e->getTraceAsString());
        }
        return apiError();
    }

    private function wpdRequest($requestUri,$requestData)
    {
        generateApiLog('Worldpay Request Data:'.
            json_encode([
                'url' => $requestUri,
                'body' => $requestData
            ]));

        $basicUrl = env('local_env') ? 'https://try.access.worldpay.com' : 'https://access.worldpay.com';
        $username = env('stripe.public_key');
        $password = env('stripe.private_key');
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => [
               // "Authorization: BasicAuth <YOUR_TOKEN_HERE>",
                "Content-Type: application/vnd.worldpay.payment_pages-v1.hal+json",
                "User-Agent: string",
                "WP-CorrelationId: string"
            ],
            CURLOPT_POSTFIELDS => json_encode($requestData),
            CURLOPT_URL => $basicUrl . $requestUri,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_USERPWD => "$username:$password"
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);

        if ($error) {
            throw new \Exception('WorldPay Curl请求异常:'.$error);
        }
        generateApiLog('Worldpay Response Data:'. $response);
        return json_decode($response,true);
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
}