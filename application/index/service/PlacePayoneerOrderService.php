<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 10:23
 */

namespace app\index\service;

class PlacePayoneerOrderService extends BaseService
{

    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $centralIdFile = app()->getRootPath() .DIRECTORY_SEPARATOR.'file'.DIRECTORY_SEPARATOR.$params['center_id'] .'.txt';
        if (!file_exists($centralIdFile)) die('文件不存在');
        $fData = json_decode(file_get_contents($centralIdFile),true);
        $cid = customEncrypt($params['center_id']);
        $baseUrl = request()->domain();

        $baseValue = base64_encode(env('stripe.public_key') . ':' . env('stripe.merchant_token'));
        $token = sprintf('Basic %1$s', $baseValue);

        //header头设置
        $headers = [
            'Content-Type:application/json',
            'Authorization:'.$token
        ];

        //创建新的支付会话
        $data = array();
        $data['integration'] = 'HOSTED';
        $data['division'] = env('stripe.private_key');
        $data['transactionId'] = $params['order_no'];
        $data['country'] = $params['country'];
        $data['payment']['amount'] = $params['amount'];
        $data['payment']['currency'] = $params['currency'];
        $data['payment']['reference'] = 'order nr. '.$params['order_no'];
        $data['callback']['cancelUrl'] = $baseUrl  . '/checkout/pay/payoneerCancel?cid='.$cid;
        $data['callback']['notificationUrl'] = $baseUrl  . '/checkout/pay/payoneerProcess?cid='.$cid;
        $data['callback']['returnUrl'] = $baseUrl  . '/checkout/pay/payoneerSuccess?cid='.$cid;
        $data['callback']['summaryUrl'] = $baseUrl  . '/checkout/pay/payoneerHome?cid='.$cid;

        $data['customer']['email'] = $params['email'];
        $data['customer']['addresses']['billing']['street'] = $params['address1'];
        $data['customer']['addresses']['billing']['zip'] = $params['zip'];
        $data['customer']['addresses']['billing']['city'] = $params['city'];
        $data['customer']['addresses']['billing']['country'] = $params['country'];

        $data['customer']['addresses']['shipping']['street'] = $params['address1'];
        $data['customer']['addresses']['shipping']['zip'] = $params['zip'];
        $data['customer']['addresses']['shipping']['city'] = $params['city'];
        $data['customer']['addresses']['shipping']['country'] = $params['country'];

        $body = json_encode($data);

        $host = env('local_env') ? 'https://api.sandbox.oscato.com' : 'https://api.live.oscato.com';
        $url = $host.'/api/lists';

        //$url = 'https://api.pi-nightly.integration.oscato.com/demo/lists';

        $result = $this->get_curl_content($url, 'POST', $headers, $body);
        $statusCode = $result['status']['code'] ?? '';
        if ($statusCode !== 'listed') {
            return apiError(json_encode($result));
        }

        $fData['ts_id'] = $result['identification']['longId'];
        file_put_contents($centralIdFile,json_encode($fData));

        return apiSuccess([
            'longId'=>$result['identification']['longId'],
            'links'=>$result['links']['self'],
            'links2'=>$host.'/pci/v1/'.$result['identification']['longId'],
        ]);
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
        //print_r($str);
        curl_close($ch);
        $result = json_decode($str, true);
        if($result == null) {
            generateApiLog(REFERER_URL .'收到异常响应 ' . $str);
        }

        return $result;
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

