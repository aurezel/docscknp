<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/2/25
 * Time: 14:20
 */

namespace app\index\service;

class PlaceRapydOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $centralIdFile = app()->getRootPath() . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR . $params['center_id'] . '.txt';
        if (!file_exists($centralIdFile)) return apiError();
        $cid = customEncrypt($params['center_id']);
        $baseUrl = request()->domain();

        $cancel_checkout_url = $baseUrl . "/checkout/pay/rdRedirect?r_type=f&cid=$cid";
        $complete_checkout_url = $baseUrl . "/checkout/pay/rdRedirect?r_type=s&cid=$cid";

        $body = [
            "amount" =>  $params['amount'],
            "complete_checkout_url" => $complete_checkout_url,
            "country" =>$params['country'],
            "currency" => $params['currency'],
            "cancel_checkout_url" => $cancel_checkout_url,
            "language" => "en",
            'merchant_reference_id' => $cid,
            'payment_method_type_categories' => [
                'card'
            ]
        ];

        generateApiLog($body);
        try {
            $object = app('rapyd_api')->makeRequest('post', '/v1/checkout', $body);
            if (!isset($object['status']['status']) || $object['status']['status'] !== 'SUCCESS') return apiError();
            $responseData = intval(env('stripe.merchant_token')) ? $object['data']['id'] : $object["data"]["redirect_url"];
            return apiSuccess($responseData);
        } catch(\Exception $e) {
            generateApiLog('Rapyd接口异常:'.$e->getMessage() .',line:'.$e->getLine().',trace:'.$e->getTraceAsString());
            return apiError();
        }
    }
}