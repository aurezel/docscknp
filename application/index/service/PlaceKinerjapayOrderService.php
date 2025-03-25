<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/1/5
 * Time: 8:43
 */

namespace app\index\service;

class PlaceKinerjapayOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $centralIdFile = app()->getRootPath() .DIRECTORY_SEPARATOR.'file'.DIRECTORY_SEPARATOR.$params['center_id'] .'.txt';
        if (!file_exists($centralIdFile)) die('文件不存在');
        $fData = json_decode(file_get_contents($centralIdFile),true);
        $gatewayUrl = 'https://www.kinerjapay.com';
        if (env('local_env')) $gatewayUrl .= '/sandbox';
        $gatewayUrl .= '/services/kinerjapay/json/transaction-process.php';
        $cid = customEncrypt($params['center_id']);
        $baseUrl = request()->domain() . '/';
        //$baseUrl = 'https://dodopop.shop/';
        $postData['merchantAppCode'] = env('stripe.public_key');
        $postData['merchantAppPassword'] = env('stripe.private_key');
        $postData['orderNo'] = mt_rand(10,99) . '_' .$cid;
        $postData['orderAmt'] = $params['amount'];
        $postData['productNo'] = [1];
        $postData['productDesc'] = ['Order#'.mt_rand(100000,999999)];
        $postData['productAmt'] = [$params['amount']];
        $postData['productQty'] = [1];
        $postData['processURL'] = $baseUrl  . '/checkout/pay/kpProcess?cid='.$cid;
        $postData['successURL'] = $baseUrl  . '/checkout/pay/kpSuccess?cid='.$cid;
        $postData['cancelURL'] = $baseUrl  . '/checkout/pay/kpCancel?cid='.$cid;

        generateApiLog($postData);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gatewayUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept:' . 'application/json',
            'Content-Type:' . 'application/json;charset=utf-8',
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        if ($curlErrno) {
            generateApiLog(['CurlError' => curl_error($ch)]);
            return apiError();
        }

        generateApiLog($result);

        $response = json_decode($result,true);
        if ($response['code'] != 100)
        {
            generateApiLog("生成支付链接失败：".$result);
            return apiError();
        }

        $checkoutUrl = $response['result']['checkoutURL'] ?? '';
        generateApiLog(['checkoutUrl' => $checkoutUrl]);
        if (empty($checkoutUrl)) return apiError();
        if(strpos($checkoutUrl, '&lang=en') === false)
            $checkoutUrl .= '&lang=en';
        $fData['ts_id'] = $response['result']['token'];
        file_put_contents($centralIdFile,json_encode($fData));
        generateApiLog(['payUrl' => $checkoutUrl,'params' => $params]);
        return apiSuccess($checkoutUrl);
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