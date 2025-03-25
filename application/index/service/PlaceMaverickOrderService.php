<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 10:23
 */

namespace app\index\service;


class PlaceMaverickOrderService extends BaseService
{
    private $login;
    private $billing;
    private $shipping;
    private $order;
    private $responses;

    /**
     * 调用First Data的支付 by zhuzh
     * @return \think\response\Json
     */
    public function placeOrder(array $params = [])
    {
        $postData = $params;
        $currency_dec = config('parameters.currency_dec');

        try {
            if (!$this->checkToken($params)) return apiError();
            if(isset($currency_dec[strtoupper($postData['currency_code'])])) {
                try {
                    //请求支付交易
                    $postData['card_number'] = str_replace(' ', '', $postData['card_number']);
                    if(empty($postData['card_number']) || empty($postData['cvc']) || empty($postData['expiry_month']) || empty($postData['expiry_year']) ){
                        return apiError('system error');
                    }

                    $apiKey = env('stripe.public_key');
                    $apiSecret = env('stripe.private_key');

                    $nonce = strval(hexdec(bin2hex(openssl_random_pseudo_bytes(4, $cstrong))));

//                    $amount = $postData['amount'];
//                    for($i = 0; $i < $currency_dec[strtoupper($postData['currency_code'])]; $i++) {
//                        $amount *= 10;
//                    }

                    //月份补零
                    $postData['expiry_month'] = str_pad($postData['expiry_month'],2,"0",STR_PAD_LEFT);

                    $card_number = $this->processInput($postData['card_number']);
                    $card_cvv = $this->processInput($postData['cvc']);
                    $card_expiry = $this->processInput($postData['expiry_month'].$postData['expiry_year']);
                    $amount = $this->processInput($postData['amount']);
                    $currency_code = $this->processInput($postData['currency']);
                    $merchant_ref = $this->processInput($postData['center_id']);

                    $this->setLogin($apiSecret);
                    $this->setBilling($postData['first_name'],$postData['last_name'],"",$postData['address1'],"", $postData['city'],$params['state'],$postData['zip'],$postData['country'],$postData['phone'],$postData['phone'],$postData['email'],"");
                    $this->setShipping($postData['first_name'],$postData['last_name'],"",$postData['address1'],"", $postData['city'],$params['state'],$postData['zip'],$postData['country'],$postData['email']);
                    $this->setOrder($nonce,"",0, 0, $merchant_ref,$currency_code);
                    $this->doSale($amount,$card_number,$card_expiry,$card_cvv);

                    $payment_status = isset($this->responses['response']) && $this->responses['response'] == 1 ? 'success' : 'failed';
                    if($payment_status == 'success') {
                        $this->validateCard(true);
                    } else {
                        $this->validateCard(false);
                    }

                    $postCenterData = [
                        'transaction_id' => isset($this->responses['transactionid']) ? $this->responses['transactionid'] : 0,
                        'center_id' => $postData['center_id'] ?? 0,
                        'action' => 'create',
                        'status' => $payment_status,
                        'failed_reason' => isset($this->responses['response']) && $this->responses['response'] == 1 ? '' : $this->responses['responsetext']
                    ];

                    $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
                    if (!isset($sendResult['status']) or $sendResult['status'] == 0)
                    {
                        generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
                        return apiError('send error');
                    }

                    $riskyFlag = $sendResult['data']['success_risky'] ?? false;
                    if(isset($this->responses['response']) && $this->responses['response'] == 1) {
                        return apiSuccess(['success_risky' => $riskyFlag]);
                    }

                    return apiError($this->responses['responsetext']);

                }catch(\Exception $e) {
                    $postCenterData = [
                        'transaction_id' => 0,
                        'center_id' => $postData['center_id'] ?? 0,
                        'action' => 'create',
                        'status' => 'failed',
                        'failed_reason' => $e->getMessage()
                    ];
                    $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
                    if (!isset($sendResult['status']) or $sendResult['status'] == 0)
                    {
                        generateApiLog(REFERER_URL .'createOrder创建订单传送信息到中控失败：' . json_encode($sendResult));
                    }
                    $this->validateCard(false);
                    throw new \Exception($e->getMessage());
                    //return apiError($e->getMessage());
                }
            }


        }catch (\Exception $ex) {
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
            return apiError();
        }
    }

    private function processInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return strval($data);
    }

    private function setLogin($security_key) {
        $this->login['security_key'] = $security_key;
    }

    private function setOrder($orderid,
                      $orderdescription,
                      $tax,
                      $shipping,
                      $ponumber,
                      $currency) {
        $this->order['orderid']          = $orderid;
        $this->order['orderdescription'] = $orderdescription;
        $this->order['tax']              = $tax;
        $this->order['shipping']         = $shipping;
        $this->order['ponumber']         = $ponumber;
        $this->order['currency']        = $currency;
    }

    private function setBilling($firstname,
                        $lastname,
                        $company,
                        $address1,
                        $address2,
                        $city,
                        $state,
                        $zip,
                        $country,
                        $phone,
                        $fax,
                        $email,
                        $website) {
        $this->billing['firstname'] = $firstname;
        $this->billing['lastname']  = $lastname;
        $this->billing['company']   = $company;
        $this->billing['address1']  = $address1;
        $this->billing['address2']  = $address2;
        $this->billing['city']      = $city;
        $this->billing['state']     = $state;
        $this->billing['zip']       = $zip;
        $this->billing['country']   = $country;
        $this->billing['phone']     = $phone;
        $this->billing['fax']       = $fax;
        $this->billing['email']     = $email;
        $this->billing['website']   = $website;
    }

    private function setShipping($firstname,
                         $lastname,
                         $company,
                         $address1,
                         $address2,
                         $city,
                         $state,
                         $zip,
                         $country,
                         $email) {
        $this->shipping['firstname'] = $firstname;
        $this->shipping['lastname']  = $lastname;
        $this->shipping['company']   = $company;
        $this->shipping['address1']  = $address1;
        $this->shipping['address2']  = $address2;
        $this->shipping['city']      = $city;
        $this->shipping['state']     = $state;
        $this->shipping['zip']       = $zip;
        $this->shipping['country']   = $country;
        $this->shipping['email']     = $email;
    }

    // Transaction Functions

    private function doSale($amount, $ccnumber, $ccexp, $cvv="") {

        $query  = "";
        // Login Information
        $query .= "security_key=" . urlencode($this->login['security_key']) . "&";
        // Sales Information
        $query .= "ccnumber=" . urlencode($ccnumber) . "&";
        $query .= "ccexp=" . urlencode($ccexp) . "&";
        $query .= "amount=" . urlencode(number_format($amount,2,".","")) . "&";
        $query .= "cvv=" . urlencode($cvv) . "&";
        $query .= "currency=" . urlencode($this->order['currency']) . "&";
        // Order Information
        //$query .= "ipaddress=" . urlencode($this->order['ipaddress']) . "&";
        $query .= "orderid=" . urlencode($this->order['orderid']) . "&";
        $query .= "orderdescription=" . urlencode($this->order['orderdescription']) . "&";
        $query .= "tax=" . urlencode(number_format($this->order['tax'],2,".","")) . "&";
        $query .= "shipping=" . urlencode(number_format($this->order['shipping'],2,".","")) . "&";
        $query .= "ponumber=" . urlencode($this->order['ponumber']) . "&";
        // Billing Information
        $query .= "firstname=" . urlencode($this->billing['firstname']) . "&";
        $query .= "lastname=" . urlencode($this->billing['lastname']) . "&";
        $query .= "company=" . urlencode($this->billing['company']) . "&";
        $query .= "address1=" . urlencode($this->billing['address1']) . "&";
        $query .= "address2=" . urlencode($this->billing['address2']) . "&";
        $query .= "city=" . urlencode($this->billing['city']) . "&";
        $query .= "state=" . urlencode($this->billing['state']) . "&";
        $query .= "zip=" . urlencode($this->billing['zip']) . "&";
        $query .= "country=" . urlencode($this->billing['country']) . "&";
        $query .= "phone=" . urlencode($this->billing['phone']) . "&";
        $query .= "fax=" . urlencode($this->billing['fax']) . "&";
        $query .= "email=" . urlencode($this->billing['email']) . "&";
        //$query .= "website=" . urlencode($this->billing['website']) . "&";
        // Shipping Information
        $query .= "shipping_firstname=" . urlencode($this->shipping['firstname']) . "&";
        $query .= "shipping_lastname=" . urlencode($this->shipping['lastname']) . "&";
        $query .= "shipping_company=" . urlencode($this->shipping['company']) . "&";
        $query .= "shipping_address1=" . urlencode($this->shipping['address1']) . "&";
        $query .= "shipping_address2=" . urlencode($this->shipping['address2']) . "&";
        $query .= "shipping_city=" . urlencode($this->shipping['city']) . "&";
        $query .= "shipping_state=" . urlencode($this->shipping['state']) . "&";
        $query .= "shipping_zip=" . urlencode($this->shipping['zip']) . "&";
        $query .= "shipping_country=" . urlencode($this->shipping['country']) . "&";
        $query .= "shipping_email=" . urlencode($this->shipping['email']) . "&";
        $query .= "type=sale";
        return $this->_doPost($query);
    }

    private function doAuth($amount, $ccnumber, $ccexp, $cvv="") {

        $query  = "";
        // Login Information
        $query .= "security_key=" . urlencode($this->login['security_key']) . "&";
        // Sales Information
        $query .= "ccnumber=" . urlencode($ccnumber) . "&";
        $query .= "ccexp=" . urlencode($ccexp) . "&";
        $query .= "amount=" . urlencode(number_format($amount,2,".","")) . "&";
        $query .= "cvv=" . urlencode($cvv) . "&";
        // Order Information
        $query .= "ipaddress=" . urlencode($this->order['ipaddress']) . "&";
        $query .= "orderid=" . urlencode($this->order['orderid']) . "&";
        $query .= "orderdescription=" . urlencode($this->order['orderdescription']) . "&";
        $query .= "tax=" . urlencode(number_format($this->order['tax'],2,".","")) . "&";
        $query .= "shipping=" . urlencode(number_format($this->order['shipping'],2,".","")) . "&";
        $query .= "ponumber=" . urlencode($this->order['ponumber']) . "&";
        // Billing Information
        $query .= "firstname=" . urlencode($this->billing['firstname']) . "&";
        $query .= "lastname=" . urlencode($this->billing['lastname']) . "&";
        $query .= "company=" . urlencode($this->billing['company']) . "&";
        $query .= "address1=" . urlencode($this->billing['address1']) . "&";
        $query .= "address2=" . urlencode($this->billing['address2']) . "&";
        $query .= "city=" . urlencode($this->billing['city']) . "&";
        $query .= "state=" . urlencode($this->billing['state']) . "&";
        $query .= "zip=" . urlencode($this->billing['zip']) . "&";
        $query .= "country=" . urlencode($this->billing['country']) . "&";
        $query .= "phone=" . urlencode($this->billing['phone']) . "&";
        $query .= "fax=" . urlencode($this->billing['fax']) . "&";
        $query .= "email=" . urlencode($this->billing['email']) . "&";
        $query .= "website=" . urlencode($this->billing['website']) . "&";
        // Shipping Information
        $query .= "shipping_firstname=" . urlencode($this->shipping['firstname']) . "&";
        $query .= "shipping_lastname=" . urlencode($this->shipping['lastname']) . "&";
        $query .= "shipping_company=" . urlencode($this->shipping['company']) . "&";
        $query .= "shipping_address1=" . urlencode($this->shipping['address1']) . "&";
        $query .= "shipping_address2=" . urlencode($this->shipping['address2']) . "&";
        $query .= "shipping_city=" . urlencode($this->shipping['city']) . "&";
        $query .= "shipping_state=" . urlencode($this->shipping['state']) . "&";
        $query .= "shipping_zip=" . urlencode($this->shipping['zip']) . "&";
        $query .= "shipping_country=" . urlencode($this->shipping['country']) . "&";
        $query .= "shipping_email=" . urlencode($this->shipping['email']) . "&";
        $query .= "type=auth";
        return $this->_doPost($query);
    }

    private function doCredit($amount, $ccnumber, $ccexp) {

        $query  = "";
        // Login Information
        $query .= "security_key=" . urlencode($this->login['security_key']) . "&";
        // Sales Information
        $query .= "ccnumber=" . urlencode($ccnumber) . "&";
        $query .= "ccexp=" . urlencode($ccexp) . "&";
        $query .= "amount=" . urlencode(number_format($amount,2,".","")) . "&";
        // Order Information
        //$query .= "ipaddress=" . urlencode($this->order['ipaddress']) . "&";
        $query .= "orderid=" . urlencode($this->order['orderid']) . "&";
        $query .= "orderdescription=" . urlencode($this->order['orderdescription']) . "&";
        $query .= "tax=" . urlencode(number_format($this->order['tax'],2,".","")) . "&";
        $query .= "shipping=" . urlencode(number_format($this->order['shipping'],2,".","")) . "&";
        $query .= "ponumber=" . urlencode($this->order['ponumber']) . "&";
        // Billing Information
        $query .= "firstname=" . urlencode($this->billing['firstname']) . "&";
        $query .= "lastname=" . urlencode($this->billing['lastname']) . "&";
        $query .= "company=" . urlencode($this->billing['company']) . "&";
        $query .= "address1=" . urlencode($this->billing['address1']) . "&";
        $query .= "address2=" . urlencode($this->billing['address2']) . "&";
        $query .= "city=" . urlencode($this->billing['city']) . "&";
        $query .= "state=" . urlencode($this->billing['state']) . "&";
        $query .= "zip=" . urlencode($this->billing['zip']) . "&";
        $query .= "country=" . urlencode($this->billing['country']) . "&";
        $query .= "phone=" . urlencode($this->billing['phone']) . "&";
        $query .= "fax=" . urlencode($this->billing['fax']) . "&";
        $query .= "email=" . urlencode($this->billing['email']) . "&";
        $query .= "website=" . urlencode($this->billing['website']) . "&";
        $query .= "type=credit";
        return $this->_doPost($query);
    }

    private function doOffline($authorizationcode, $amount, $ccnumber, $ccexp) {

        $query  = "";
        // Login Information
        $query .= "security_key=" . urlencode($this->login['security_key']) . "&";
        // Sales Information
        $query .= "ccnumber=" . urlencode($ccnumber) . "&";
        $query .= "ccexp=" . urlencode($ccexp) . "&";
        $query .= "amount=" . urlencode(number_format($amount,2,".","")) . "&";
        $query .= "authorizationcode=" . urlencode($authorizationcode) . "&";
        // Order Information
       // $query .= "ipaddress=" . urlencode($this->order['ipaddress']) . "&";
        $query .= "orderid=" . urlencode($this->order['orderid']) . "&";
        $query .= "orderdescription=" . urlencode($this->order['orderdescription']) . "&";
        $query .= "tax=" . urlencode(number_format($this->order['tax'],2,".","")) . "&";
        $query .= "shipping=" . urlencode(number_format($this->order['shipping'],2,".","")) . "&";
        $query .= "ponumber=" . urlencode($this->order['ponumber']) . "&";
        // Billing Information
        $query .= "firstname=" . urlencode($this->billing['firstname']) . "&";
        $query .= "lastname=" . urlencode($this->billing['lastname']) . "&";
        $query .= "company=" . urlencode($this->billing['company']) . "&";
        $query .= "address1=" . urlencode($this->billing['address1']) . "&";
        $query .= "address2=" . urlencode($this->billing['address2']) . "&";
        $query .= "city=" . urlencode($this->billing['city']) . "&";
        $query .= "state=" . urlencode($this->billing['state']) . "&";
        $query .= "zip=" . urlencode($this->billing['zip']) . "&";
        $query .= "country=" . urlencode($this->billing['country']) . "&";
        $query .= "phone=" . urlencode($this->billing['phone']) . "&";
        $query .= "fax=" . urlencode($this->billing['fax']) . "&";
        $query .= "email=" . urlencode($this->billing['email']) . "&";
        $query .= "website=" . urlencode($this->billing['website']) . "&";
        // Shipping Information
        $query .= "shipping_firstname=" . urlencode($this->shipping['firstname']) . "&";
        $query .= "shipping_lastname=" . urlencode($this->shipping['lastname']) . "&";
        $query .= "shipping_company=" . urlencode($this->shipping['company']) . "&";
        $query .= "shipping_address1=" . urlencode($this->shipping['address1']) . "&";
        $query .= "shipping_address2=" . urlencode($this->shipping['address2']) . "&";
        $query .= "shipping_city=" . urlencode($this->shipping['city']) . "&";
        $query .= "shipping_state=" . urlencode($this->shipping['state']) . "&";
        $query .= "shipping_zip=" . urlencode($this->shipping['zip']) . "&";
        $query .= "shipping_country=" . urlencode($this->shipping['country']) . "&";
        $query .= "shipping_email=" . urlencode($this->shipping['email']) . "&";
        $query .= "type=offline";
        return $this->_doPost($query);
    }

    private function doCapture($transactionid, $amount =0) {

        $query  = "";
        // Login Information
        $query .= "security_key=" . urlencode($this->login['security_key']) . "&";
        // Transaction Information
        $query .= "transactionid=" . urlencode($transactionid) . "&";
        if ($amount>0) {
            $query .= "amount=" . urlencode(number_format($amount,2,".","")) . "&";
        }
        $query .= "type=capture";
        return $this->_doPost($query);
    }

    private function doVoid($transactionid) {

        $query  = "";
        // Login Information
        $query .= "security_key=" . urlencode($this->login['security_key']) . "&";
        // Transaction Information
        $query .= "transactionid=" . urlencode($transactionid) . "&";
        $query .= "type=void";
        return $this->_doPost($query);
    }

    private function doRefund($transactionid, $amount = 0) {

        $query  = "";
        // Login Information
        $query .= "security_key=" . urlencode($this->login['security_key']) . "&";
        // Transaction Information
        $query .= "transactionid=" . urlencode($transactionid) . "&";
        if ($amount>0) {
            $query .= "amount=" . urlencode(number_format($amount,2,".","")) . "&";
        }
        $query .= "type=refund";
        return $this->_doPost($query);
    }

    private function _doPost($query) {
        $serviceURL = env('stripe.stripe_version') == 'nmi' ? 'https://secure.networkmerchants.com/api/transact.php' : 'https://secure.maverickgateway.com/api/transact.php';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $serviceURL);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
        curl_setopt($ch, CURLOPT_POST, 1);

        if (!($data = curl_exec($ch))) {
            return false;
        }
        curl_close($ch);
        unset($ch);
        //print $data;
        $data = explode("&",$data);
        for($i=0;$i<count($data);$i++) {
            $rdata = explode("=",$data[$i]);
            $this->responses[$rdata[0]] = $rdata[1];
        }
        return $this->responses['response'];
    }
}

