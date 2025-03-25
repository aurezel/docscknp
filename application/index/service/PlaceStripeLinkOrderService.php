<?php

namespace app\index\service;

class PlaceStripeLinkOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError('Token Error');

        try{
            $centerId = $params['center_id'];
            $centralIdFile = app()->getRootPath() .DIRECTORY_SEPARATOR.'file'.DIRECTORY_SEPARATOR.$centerId .'.txt';
            if (!file_exists($centralIdFile)) return  apiError();
            $cid = customEncrypt($centerId);
            $baseUrl = request()->domain();
            $currency_dec = config('parameters.currency_dec');
            $amount = floatval($params['amount']);
            $currency = strtoupper($params['currency']);
            $scale = 1;
            for($i = 0; $i < $currency_dec[$currency]; $i++) {
                $scale*=10;
            }
            $amount = bcmul($amount,$scale);

            $productsFile = app()->getRootPath() . 'product.csv';
            $productName = 'Your items in cart';
            if (file_exists($productsFile))
            {
                $productNameData = array();
                if (($handle = fopen($productsFile, "r")) !== FALSE) {
                    while (($data = fgetcsv($handle)) !== FALSE) {
                        $productNameData[] = [
                            'product_name' => $data[0],
                            'description' => $data[1] ?? ''
                        ];
                    }
                    fclose($handle);
                }
                $productNameCount = count($productNameData);
                if ($productNameCount > 0)
                {
                    $productName = $productNameData[mt_rand(0,$productNameCount -1)]['product_name'];
                }
            }

            $sPath = env('stripe.link_success_path');
            $successPath = empty($sPath) ? '/checkout/pay/stlSuccess' : $sPath;

            header('Content-Type: application/json');
            $stripe = new \Stripe\StripeClient(env('stripe.private_key'));
            $priceResponse = $stripe->prices->create([
                'currency' => $currency,
                'unit_amount' => $amount,
                'product_data' => [
                    'name' => $productName
                ],
            ]);
            if (!isset($priceResponse->id)) {
                generateApiLog('获取价格接口响应错误:' . $priceResponse);
                return apiError();
            }
            $linkResponse = $stripe->paymentLinks->create([
                'line_items' => [
                    [
                        'price' => $priceResponse->id,
                        'quantity' => 1,
                    ],
                ],
                'after_completion' => [
                    'type' => 'redirect',
                    'redirect' => [
                        'url' => $baseUrl . $successPath .'?cid='.$cid
                    ]
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'cid' => $cid,
                    ],
                ]
            ]);
            if (!isset($linkResponse->url, $linkResponse->object) || $linkResponse->object !== 'payment_link') {
                generateApiLog('获取链接响应错误:' . $linkResponse);
                return apiError();
            }
            return apiSuccess(['url' => $linkResponse->url]);
        }catch (\Exception $e)
        {
            generateApiLog('创建StripeLink接口异常:'.$e->getMessage() .',line:'.$e->getLine().',trace:'.$e->getTraceAsString());
            return apiError();
        }
    }

    public function sendDataToCentral($status,$centerId,$transactionId,$msg = '')
    {
        // 发送到中控
        $postCenterData = [
            'transaction_id' => $transactionId,
            'center_id' => $centerId,
            'action' => 'create',
            'status' => $status,
            'failed_reason' => $msg
        ];
        $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
        if (!isset($sendResult['status']) or $sendResult['status'] == 0)
        {
            generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
            return false;
        }
        return ['success_risky' => $sendResult['data']['success_risky'],'redirect_url' => $sendResult['data']['redirect_url'] ?? ''];
    }

}