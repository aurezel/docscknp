<?php

namespace app\index\service;
class PlaceXpayOrderService extends BaseService
{
    private $publicKey;
    private $privateKey;
    private $gatewayBaseUrl;

    public function __construct()
    {
        $this->publicKey = env('stripe.public_key');
        $this->privateKey = env('stripe.private_key');
        $this->gatewayBaseUrl = env('local_env') ? 'https://int-ecommerce.nexi.it' : 'https://ecommerce.nexi.it';
    }
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $cid = customEncrypt($params['center_id']);
        $baseUrl = request()->domain();
        $sPath = env('stripe.checkout_success_path');
        $nPath = env('stripe.checkout_notify_path');
        $successPath = empty($sPath) ? '/checkout/pay/xRedirect' : $sPath;
        $notifyPath = empty($nPath) ? '/checkout/pay/xNotify' : $nPath;
        $complete_checkout_url = $baseUrl . $successPath . "?cid=$cid";
        //$baseUrl = 'https://6f9a-182-255-32-51.ngrok-free.app';
        $notify_checkout_url = $baseUrl . $notifyPath ."?cid=$cid";

        try {
            $firstName = str_replace(['-', "'", '.', '_', ','], ['', '', '', '', ''], $params['first_name']);
            $lastName = str_replace(['-', "'", '.', '_', ','], ['', '', '', '', ''], $params['last_name']);

            $amount = floatval($params['amount']);
            $currentMicroTime = round(microtime(true) * 1000);
            $requestUrl = $this->gatewayBaseUrl . '/ecomm/ecomm/DispatcherServlet';
            $codTrans = "PS" . $currentMicroTime;
            $currency = 'EUR';
            $amount = bcmul($amount,100); // 欧元
            $mac = sha1('codTrans=' . $codTrans . 'divisa=' . $currency . 'importo=' . $amount . $this->privateKey);
            $requestData = array(
                'alias' => $this->publicKey,
                'importo' => $amount,
                'divisa' => $currency,
                'codTrans' => $codTrans,
                'url' => $complete_checkout_url . '&r_type=s',
                'url_back' => $complete_checkout_url . '&r_type=r',
                'mac' => $mac,
                // optional
                'urlpost' => $notify_checkout_url,
                'nome' => $firstName,
                'cognome' => $lastName,
            );

            $extendsParams = array();
            $requestParams = array_merge($requestData, $extendsParams);
            $html = '<head>
    <meta charset="utf-8">
    <title>Pay with debit or credit card</title>
    <meta name="viewport"
          content="width=device-width,initial-scale=1.0,maximum-scale=1.0, user-scalable=no, minimal-ui">
</head>';
            $html .= "<div style='color: green;top: 25%;text-align: center;'>Loading Now...</div>";
            $html .= "<form id='payment-form' method='POST' action='$requestUrl'>";
            foreach ($requestParams as $name => $value)
            {
                $value = htmlentities($value);
                $html .= "<input type='hidden' name='$name' value='$value' />";
            }
            $html .="<input style='display: none;' type='submit' value='Pay Now...' />";
            $html .= '</form>';
            $html .= "<script>document.getElementById('payment-form').submit();</script>";
            return apiSuccess([
                'html' => $html
            ]);
        } catch (\Exception $e) {
            generateApiLog('XPay接口异常:' . $e->getMessage() . ',line:' . $e->getLine() . ',trace:' . $e->getTraceAsString());
        }
        return apiError();
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