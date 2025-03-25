<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/2/25
 * Time: 14:20
 */

namespace app\index\service;

class PlaceRapydCaptureOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError();
        $centralIdFile = app()->getRootPath() . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR . $params['center_id'] . '.txt';
        if (!file_exists($centralIdFile)) return apiError();
        $cid = customEncrypt($params['center_id']);
        $baseUrl = request()->domain();

        $statement = env('stripe.COMPANY');
        if (empty($statement)) $statement = explode('.',request()->rootDomain())[0];
        $productDescription = env('stripe.description');
        if (empty($productDescription)) $productDescription = 'goods';
        $referenceId = $statement .'-'.intval($params['center_id']);
        $sPath = env('stripe.checkout_success_path');
        $cPath = env('stripe.checkout_cancel_path');
        $successPath = empty($sPath) ? '/checkout/pay/rdRedirect' : $sPath;
        $cancelPath = empty($cPath) ? '/checkout/pay/rdRedirect' : $cPath;
        $cancel_checkout_url = $baseUrl . $cancelPath . "?r_type=f&cid=$cid";
        $complete_checkout_url = $baseUrl . $successPath ."?r_type=s&cid=$cid";

        try {
            $cardNumber = $this->processInput(str_replace(' ','',$params['card_number']));
            $countrySwitch = env('stripe.ONLY_EUROPE_CARD',false);
            $rapydApi = app('rapyd_api');
            if ($countrySwitch && !$rapydApi->isEuropeCountry($cardNumber)) return apiError('Only support European countries credit card.');
            $fullName = str_replace(['-',"'",'.','_',','],['','','','',''],$params['first_name'] . ' ' . $params['last_name']);
            $currency = $params['currency'];
            $customerBody = [
                'addresses' => [
                    [
                        "name" => $fullName,
                        "line_1" => $params['address1'],
                        "line_2" => $params['address2'],
                        "line_3" => "",
                        "city" => $params['city'],
                        "district" => "",
                        "canton" => "",
                        "state" => $params['state'],
                        "country" => $params['country'],
                        "zip" => $params['zip'],
                        "metadata" => array(
                            "merchant_defined" => true
                        )
                    ]
                ],
                'name' => $fullName,
                'email' => $params['email']
            ];

            $firstDigit = substr($cardNumber, 0, 1);
            if($firstDigit != '4' && $firstDigit != '5' && $firstDigit != '2') {
                return apiError('VISA and Master card only!');
            }
            $cardType = 'gb_mastercard_card';
            if($firstDigit == '4') {
                $cardType = 'gb_visa_card';
            }

            $body = [
                "amount" =>  floatval($params['amount']),
                "complete_payment_url" => $complete_checkout_url,
                'error_payment_url' => $cancel_checkout_url,
                "currency" => $currency,
                'description' => $productDescription,
                'statement_descriptor' => $statement,
                'merchant_reference_id' => $referenceId,
                'customer' => $customerBody,
                'ewallet' => env('stripe.merchant_token'),
                'payment_method' => [
                    'type' => $cardType,
                    'fields' => [
                        'number' => $cardNumber,
                        'expiration_month' => str_pad(self::processInput($params['expiry_month']),'2','0',STR_PAD_LEFT),
                        'expiration_year' => self::processInput($params['expiry_year']),
                        'name' => $fullName,
                        'cvv' => self::processInput($params['cvc']),
                    ],
                    'metadata' => null
                ],
                'capture' => false,
                'expiration' => strtotime( "+7 day" )
            ];

            if (!in_array($currency,['USD','EUR','GBP']))
            {
                $body['fixed_side'] = 'sell';
                $body['requested_currency'] = 'USD';
            }

            $object = $rapydApi->makeRequest('post', '/v1/payments', $body);
            if (!isset($object['status']['status'])) return apiError();
            if ($object['status']['status'] === 'SUCCESS')
            {
                if ($object['data']['status'] == 'CLO')
                {
                    $this->validateCard(true);
                    return apiSuccess();
                }elseif ($object['data']['status'] == 'ACT')
                {
                    $this->validateCard();
                    return apiSuccess(['url' => $object['data']['redirect_url']]);
                }
            }else{
                $errorMsg = $object['status']['message'];
                $rapydApi->sendDataToCentral('failed',$params['center_id'],0,$errorMsg);
                $this->validateCard();
                return apiError($errorMsg);
            }
            return apiError($object['status']['message']);
        } catch(\Exception $e) {
            generateApiLog('Rapyd Capture接口异常:'.$e->getMessage() .',line:'.$e->getLine().',trace:'.$e->getTraceAsString());
        }
        return apiError();
    }
    private static function processInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return strval($data);
    }
}