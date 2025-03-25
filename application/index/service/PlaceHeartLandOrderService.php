<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/5/12
 * Time: 14:03
 */

namespace app\index\service;

use GlobalPayments\Api\ServiceConfigs\Gateways\PorticoConfig;
use GlobalPayments\Api\ServicesContainer;
use GlobalPayments\Api\PaymentMethods\CreditCardData;
use GlobalPayments\Api\Entities\Address;
use GlobalPayments\Api\Entities\Customer;

class PlaceHeartLandOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        try{
            if (!$this->checkToken($params)) return apiError();
            // Card Not Present
            $config = new PorticoConfig();
            $config->secretApiKey = env('stripe.private_key');
            $config->developerId = env('stripe.public_key');
            $config->versionNumber = env('stripe.merchant_token');
            $config->serviceUrl = env('local_env') ? 'https://cert.api2.heartlandportico.com' : 'https://api2.heartlandportico.com';
            ServicesContainer::configureService($config);

            $address1 = empty($params['address2']) ? $params['address1'] : $params['address1'] . ' ' . $params['address2'];
            $customer = new Customer();
            $customer->firstName = $params['first_name'];
            $customer->lastName = $params['last_name'];
            $customer->email = $params['email'];
            $customer->mobilePhone = $params['phone'];
            $customer->address = $address1;

            $address = new Address();
            $address->streetAddress1 = $address1;
            $address->streetAddress2 = $params['address2'];
            $address->isCountry($params['country']);
            $address->state = $params['state'];
            $address->city = $params['city'];
            $address->postalCode = $params['zip'];

            $card = new CreditCardData();
            $card->number = str_replace(' ','',$params['card_number']);
            $card->expMonth = str_pad($params['expiry_month'],2,"0",STR_PAD_LEFT);
            $card->expYear = '20'.$params['expiry_year'];
            $card->cvn = $params['cvc'];

            $response = $card->charge($params['amount'])
                ->withCustomerData($customer)
                ->withCurrency($params['currency'])
                ->withAddress($address)
                ->execute();

            if (!isset($response->responseCode))
            {
                generateApiLog("请求无响应");
                return apiError();
            }
            // Send Data To Central
            $transactionId = 0;
            $errorReason = '';
            $status = 'failed';
            if ($response->responseCode == "00")
            {
                $status = 'success';
                $transactionId = $response->transactionReference->transactionId ?? 0;
                $this->validateCard(true);
            }else{
                $errorReason = $response->responseMessage ?? '';
                $this->validateCard(false);
            }
            $postCenterData = [
                'transaction_id' => $transactionId,
                'center_id' => $params['center_id'] ?? 0,
                'action' => 'create',
                'status' => $status,
                'failed_reason' => $errorReason
            ];

            $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
            if (!isset($sendResult['status']) or $sendResult['status'] == 0)
            {
                generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
                return apiError();
            }
            $riskyFlag = $sendResult['data']['success_risky'] ?? false;
            if($status != 'failed') {
                return apiSuccess(['success_risky' => $riskyFlag]);
            }
            return apiError($errorReason);
        }catch (\Exception $ex)
        {
            $orderNo = $params['order_no'] ?? 0;
            $centerId = $params['center_id'] ?? 0;
            $errorReason = $ex->getMessage();
            generateApiLog([
                '创建订单异常',
                "订单ID：{$orderNo}",
                "中控ID：{$centerId}",
                '错误信息：' => [
                    'msg' => $errorReason,
                    'code' => $ex->getCode(),
                    'line' => $ex->getLine(),
                    'trace' => $ex->getTraceAsString()
                ]
            ]);
            if (false !== stripos($errorReason,'Unexpected HTTP status code [500]') ||
                false !== stripos($errorReason,'Unexpected Gateway Response')
            )
            {
                try{
                    $postCenterData = [
                        'transaction_id' => 0,
                        'center_id' => $centerId,
                        'action' => 'create',
                        'status' => 'failed',
                        'failed_reason' => $errorReason
                    ];
                    $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
                    if (!isset($sendResult['status']) or $sendResult['status'] == 0)
                    {
                        generateApiLog(REFERER_URL .'createOrder异常信息发送到中控失败：' . json_encode($sendResult));
                    }
                    $this->validateCard(false);
                }catch (\Exception $e)
                {
                    generateApiLog("异常信息发送失败：".$e->getMessage());
                }
            }
        }
        return apiError();
    }
}