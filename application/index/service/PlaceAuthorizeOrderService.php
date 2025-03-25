<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 10:13
 */

namespace app\index\service;

use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

class PlaceAuthorizeOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        if (!defined('AUTHORIZENET_LOG_FILE')) define("AUTHORIZENET_LOG_FILE", "authorize.log");
        try{

            $merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
            $merchantAuthentication->setName(env('stripe.public_key'));
            $merchantAuthentication->setTransactionKey(env('stripe.private_key'));

            // Create the payment data for a credit card
            $postData = $params;
            $cardNumber = str_replace(' ','',$postData['card_number']);
            //月份补零
            $postData['expiry_month'] = str_pad($postData['expiry_month'],2,"0",STR_PAD_LEFT);
            $expireDate = '20'.$postData['expiry_year'] . '-' .$postData['expiry_month'];
            $address = empty($postData['address2']) ? $postData['address1'] : $postData['address1'] . ' '.$postData['address2'];
            $creditCard = new AnetAPI\CreditCardType();
            $creditCard->setCardNumber($this->cutStr($cardNumber,16));
            $creditCard->setExpirationDate($expireDate);
            $creditCard->setCardCode($this->cutStr($postData['cvc'],4));

            // Add the payment data to a paymentType object
            $paymentOne = new AnetAPI\PaymentType();
            $paymentOne->setCreditCard($creditCard);

            // Create order information
            $order = new AnetAPI\OrderType();
            $invoiceNumber = $this->cutStr($postData['order_no'],20);
            $order->setInvoiceNumber("{$invoiceNumber}");
            $order->setDescription("your items in shopping cart");

            // Set the customer's Bill To address
            $customerAddress = new AnetAPI\CustomerAddressType();
            $customerAddress->setFirstName($this->cutStr($postData['first_name'],50));
            $customerAddress->setLastName($this->cutStr($postData['last_name'],50));
            $customerAddress->setCompany("");
            $customerAddress->setAddress($this->cutStr($address,60));
            $customerAddress->setCity($this->cutStr($postData['city'],40));
            $customerAddress->setState($this->cutStr($postData['state'],40));
            $customerAddress->setZip($this->cutStr($postData['zip'],20));//沙盒模式下46282用于测试卡无效
            $customerAddress->setCountry($this->cutStr($postData['country'],60));

            // Set the customer's identifying information
            $customerData = new AnetAPI\CustomerDataType();
            $customerData->setType("individual");
            $customerData->setId(mt_rand(100000,999999));
            $customerData->setEmail($this->cutStr($postData['email'],255));

            // Add values for transaction settings
            $duplicateWindowSetting = new AnetAPI\SettingType();
            $duplicateWindowSetting->setSettingName("duplicateWindow");
            $duplicateWindowSetting->setSettingValue("60");

            // Add some merchant defined fields. These fields won't be stored with the transaction,
            // but will be echoed back in the response.
            $merchantDefinedField1 = new AnetAPI\UserFieldType();
            $merchantDefinedField1->setName("customerLoyaltyNum");
            $merchantDefinedField1->setValue(mt_rand(1000000,9999999));

            $merchantDefinedField2 = new AnetAPI\UserFieldType();
            $merchantDefinedField2->setName("favoriteColor");
            $color = ['blue','yellow','white','cyan','black','pink','green'];
            $merchantDefinedField2->setValue($color[mt_rand(0,6)]);

            // Create a TransactionRequestType object and add the previous objects to it
            $transactionRequestType = new AnetAPI\TransactionRequestType();
            $transactionRequestType->setTransactionType("authCaptureTransaction");
            $transactionRequestType->setAmount($postData['amount']);
            $transactionRequestType->setCurrencyCode($this->cutStr(strtoupper($postData['currency_code']),3));
            $transactionRequestType->setOrder($order);
            $transactionRequestType->setPayment($paymentOne);
            $transactionRequestType->setCustomerIP(get_real_ip());
            $transactionRequestType->setBillTo($customerAddress);
            $transactionRequestType->setCustomer($customerData);
            $transactionRequestType->addToTransactionSettings($duplicateWindowSetting);
            $transactionRequestType->addToUserFields($merchantDefinedField1);
            $transactionRequestType->addToUserFields($merchantDefinedField2);

            // Assemble the complete transaction request
            $request = new AnetAPI\CreateTransactionRequest();
            $request->setMerchantAuthentication($merchantAuthentication);
            $request->setRefId($postData['center_id']);
            $request->setTransactionRequest($transactionRequestType);

            // Create the controller and get the response
            $controller = new AnetController\CreateTransactionController($request);

            $response = $controller->executeWithApiResponse(
                env('local_env') ? \net\authorize\api\constants\ANetEnvironment::SANDBOX
                    : \net\authorize\api\constants\ANetEnvironment::PRODUCTION
            );

            $transactionId = 0;
            $status = 'failed';
            $errorReason = '';

            if ($response != null) {

                if ($response->getMessages()->getResultCode() == "Ok") {
                    $transResponse = $response->getTransactionResponse();
                    $transactionId = $transResponse->getTransId();
                    $responseCode  = $transResponse->getResponseCode();
                    $status = ($responseCode == 1 ||  $responseCode == 4) ? 'success' : 'failed';
                    if ($status == 'failed' ) $errorReason = $transResponse->getErrors()[0]->getErrorText() . "Error Code:".
                        $transResponse->getErrors()[0]->getErrorCode();
                } else {
                    $transResponse = $response->getTransactionResponse();
                    if ($transResponse != null && $transResponse->getErrors() != null) {
                        $errorReason = $transResponse->getErrors()[0]->getErrorText() ."Error Code:".
                            $transResponse->getErrors()[0]->getErrorCode();
                    } else {
                        $errorReason = $response->getMessages()->getMessage()[0]->getText() ."Error Code:".
                            $response->getMessages()->getMessage()[0]->getCode();
                    }
                }
            } else {
                $errorReason = "No response returned";
            }

            // Send Data To Central
            $postCenterData = [
                'transaction_id' => $transactionId,
                'center_id' => $postData['center_id'] ?? 0,
                'action' => 'create',
                'status' => $status,
                'failed_reason' => $errorReason
            ];
            if($status == 'success') {
                $this->validateCard(true);
            } else {
                $this->validateCard(false);
            }

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
        }
        return apiError();
    }

    private function cutStr($char,$limit)
    {
        return strlen($char) > $limit ? substr($char,0,$limit) : $char;
    }
}