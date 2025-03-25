<?php

namespace app\index\service;

class PlaceAlipayCheckoutOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $centralIdFile = app()->getRootPath() . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR . $params['center_id'] . '.txt';
        if (!file_exists($centralIdFile)) return apiError();
        $cid = customEncrypt($params['center_id']);
        $baseUrl = request()->domain();
        //$baseUrl = 'https://17ab-103-171-177-227.ngrok-free.app';
        $statement = env('stripe.COMPANY');
        $rootDomain = request()->rootDomain();
        if (empty($statement)) $statement = explode('.', $rootDomain)[0];
        $centerId = intval($params['center_id']);
        $referenceId = $statement . '-' . $centerId . '-' . date('YmdHis').mt_rand(10000,99999);
        $sPath = env('stripe.checkout_success_path');
        $nPath = env('stripe.checkout_notify_path');
        $successPath = empty($sPath) ? '/checkout/pay/aliRedirect' : $sPath;
        $notifyPath = empty($nPath) ? '/checkout/pay/aliNotify' : $nPath;
        $complete_checkout_url = $baseUrl . $successPath . "?r_type=s&cid=$cid";
        $notify_checkout_url = $baseUrl . $notifyPath ."?cid=$cid";

        try {
            $fileData = json_decode(file_get_contents($centralIdFile),true);
            if (!isset($fileData['f_url'])) return apiError();
            $aSiteDomain = parse_url($fileData['f_url'])['host'];
            if ($aSiteDomain == $rootDomain)
            {
                $referenceId = $params['order_no'];
                $fileData['from_b_site'] = true;
                file_put_contents($centralIdFile,json_encode($fileData));
            }
            $currency_dec = config('parameters.currency_dec');
            $amount = floatval($params['amount']);
            $currency = strtoupper($params['currency']);
            $scale = 1;
            for($i = 0; $i < $currency_dec[$currency]; $i++) {
                $scale*=10;
            }
            $amount = bcmul($amount,$scale);
            $currentMicroTime = round(microtime(true) * 1000);
            $currencyAmountArr = array(
                'currency' => $currency,
                'value' => $amount,
            );
            $requestData = array (
                'order' =>
                    array (
                        'orderAmount' => $currencyAmountArr,
                        'orderDescription' => 'Your item in cart',
                        'referenceOrderId' => $referenceId,
                        'buyer' =>
                            array (
                                'referenceBuyerId' => 'BY_'.$currentMicroTime,
                            ),

                    ),
                'paymentAmount' => $currencyAmountArr,
                'paymentMethod' =>
                    array (
                        'paymentMethodType' => 'CARD',
//                        'paymentMethodMetaData' =>
//                            array (
//                                'is3DSAuthentication' => true, // for local test
//                            ),
                    ),
                'paymentNotifyUrl' => $notify_checkout_url,
                'paymentRedirectUrl' => $complete_checkout_url,
                'paymentRequestId' => 'PR_' . $currentMicroTime,
                'productCode' => 'CASHIER_PAYMENT',
                'paymentFactor' =>
                    array (
                        'isAuthorization' => true,
                        //'captureMode' => 'MANUAL'
                    ),
            );

            $responseData = app('alipay')->sendRequest('/v1/payments/createPaymentSession',$requestData);
            $resultStatus = $responseData['result']['resultStatus'];
            if ($resultStatus == 'S')
            {
                return apiSuccess(['session_data' => $responseData['paymentSessionData']]);
            }else{
                $failedMsg = $responseData['result']['resultMessage'];
                $result = app('alipay')->sendDataToCentral('failed', $centerId, 0,$failedMsg);
                if (!$result)
                {
                    generateApiLog('发送中控失败:'.json_encode(['failed',$centerId,0,$failedMsg]));
                }
            }
            return apiError($failedMsg);
        } catch (\Exception $e) {
            generateApiLog('Alipay Checkout接口异常:' . $e->getMessage() . ',line:' . $e->getLine() . ',trace:' . $e->getTraceAsString());
        }
        return apiError();
    }
}