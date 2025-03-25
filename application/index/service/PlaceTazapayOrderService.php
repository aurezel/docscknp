<?php

namespace app\index\service;

class PlaceTazapayOrderService extends BaseService
{
    public function placeOrder(array $params = [])
    {
        if (!$this->checkToken($params)) return apiError('Token Error');

        try{
            $centerId = $params['center_id'];
            $baseUrl = request()->domain();
            $encryptCenterId = customEncrypt($centerId);
            $currency_dec = config('parameters.currency_dec');
            $amount = floatval($params['amount']);
            $currency = strtoupper($params['currency']);
            $scale = 1;
            for($i = 0; $i < $currency_dec[$currency]; $i++) {
                $scale*=10;
            }
            $amount = bcmul($amount,$scale);
            $orderId = 'random_char6';

            //替换订单号规则
            $orderId = preg_replace_callback("|random_int(\d+)|",array(&$this, 'next_rand1'),$orderId); //数字
            $orderId = preg_replace_callback("|random_char(\d+)|",array(&$this, 'next_rand3'),$orderId);//字符串
            $orderId = preg_replace_callback("|random_letter(\d+)|",array(&$this, 'next_rand2'),$orderId);//字母
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
                    $singleProduct = $productNameData[mt_rand(0,$productNameCount -1)];
                    $productName = $singleProduct['product_name'];
                    $productName = preg_replace_callback("|random_int(\d+)|",array(&$this, 'next_rand1'),$productName); //数字
                    $productName = preg_replace_callback("|random_char(\d+)|",array(&$this, 'next_rand3'),$productName);//字符串
                    $productName = preg_replace_callback("|random_letter(\d+)|",array(&$this, 'next_rand2'),$productName);//字母
                    $productName = str_replace('product_desc',$orderId,$productName);
                    if (empty($singleProduct['description']))
                    {
                        $singleProduct['description'] = $productName;
                    }
                }
            }

            $isIframePay = intval(env('stripe.merchant_token'));
            // request data build
            $fullName = $params['first_name'] . ' ' . $params['last_name'];
            $email = $params['email'];
            $country = $params['country'];
            $address = array(
                'line1'       => str_replace(['_','*','-',',','.'],['','','','',''],$params['address1']),
                'line2'       => $params['address2'],
                'city'        => $params['city'],
                'country'     => $country,
                'postal_code' => $params['zip_code'],
                'state'       => $params['state'],
            );
            $phoneData = array (
                'calling_code' => self::getPhoneCode($country),
                'number' => str_replace(['+','-',' '],['','',''],$params['phone'])
            );
            $customerCommon = array(
                'name' => $fullName,
                'phone' => $phoneData
            );
            $customerData = array_merge($customerCommon,array('address' => $address));
            $customerDetail = array_merge($customerCommon,array('email' => $email, 'country' => $country));
            $today = new \DateTime();
            $today->modify('+7 days');
            $expiresAt = $today->format('Y-m-d\TH:i:s\Z');
            $sPath = env('stripe.checkout_success_path');
            $cPath = env('stripe.checkout_cancel_path');
            $nPath = env('stripe.checkout_notify_path');
            $successPath = empty($sPath) ? '/checkout/pay/tazaSuccess' : $sPath ;
            $cancelPath = empty($cPath) ? '/checkout/pay/tazaCancel' : $cPath;
            $notifyPath = empty($nPath) ? '/checkout/pay/tazaNotify' : $nPath;

            $requestData = array (
                'amount' => intval($amount),
                'invoice_currency' => $currency,
                'transaction_description' => $productName,
                'txn_source_category' => 'woocommerce',
                'txn_source' => '3.0',
                'webhook_url' => $baseUrl .$notifyPath ,
                'success_url' => $baseUrl .$successPath .'?cid='.$encryptCenterId,
                'cancel_url' => $baseUrl .$cancelPath .'?cid='.$encryptCenterId,
                'shipping_details' =>
                    array (
                        'name' => $fullName,
                        'address' => $address,
                    ),
                'billing_details' => $customerData,
                'customer_details' => $customerDetail,
                'same_as_billing_address' => false,
                'expires_at' => $expiresAt,
                'reference_id' => $encryptCenterId,
                'statement_descriptor' => explode('.',request()->rootDomain())[0],
                'payment_methods' =>['card']
            );
            $responseDataObj = $this->requestApi('/v3/checkout',$requestData);
            if ($responseDataObj->status === 'success')
            {
                $param = $isIframePay ? $responseDataObj->data->token : $responseDataObj->data->url;
                return apiSuccess(['param' => $param]);
            }

            return apiError();

        }catch (\Exception $e)
        {
            generateApiLog('创建session接口异常:'.$e->getMessage() .',line:'.$e->getLine().',trace:'.$e->getTraceAsString());
            return apiError();
        }
    }

    private function requestApi($url,$requestData)
    {
        $ch = curl_init();
        $url = self::getGatewayUrl() . $url;
        curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($requestData));
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch,CURLOPT_TIMEOUT,45);
        curl_setopt($ch,CURLOPT_HTTP_VERSION,'1.0');
        $headers = [
            'Authorization: ' . self::authentication(),
            'Content-Type:application/json',
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        if (curl_errno($ch))
        {
            throw new \Exception('CURL异常:'.curl_error($ch));
        }
        $responseObj = json_decode($response);
        if (!$responseObj || 'success' !== $responseObj->status)
        {
            throw new \Exception('结果响应失败:'.json_encode($responseObj));
        }
        curl_close ($ch);
        return $responseObj;
    }
    private static function getPhoneCode($countryCode)
    {
        $countryCodeArray = [
            'AD' => '376','AE' => '971','AF' => '93','AG' => '1268','AI' => '1264','AL' => '355','AM' => '374','AN' => '599','AO' => '244','AQ' => '672','AR' => '54','AS' => '1684','AT' => '43','AU' => '61','AW' => '297','AZ' => '994','BA' => '387','BB' => '1246','BD' => '880','BE' => '32','BF' => '226','BG' => '359','BH' => '973','BI' => '257','BJ' => '229','BL' => '590','BM' => '1441','BN' => '673','BO' => '591','BR' => '55','BS' => '1242','BT' => '975','BW' => '267','BY' => '375','BZ' => '501','CA' => '1','CC' => '61','CD' => '243','CF' => '236','CG' => '242','CH' => '41','CI' => '225','CK' => '682','CL' => '56','CM' => '237','CN' => '86','CO' => '57','CR' => '506','CU' => '53','CV' => '238','CX' => '61','CY' => '357','CZ' => '420','DE' => '49','DJ' => '253','DK' => '45','DM' => '1767','DO' => '1809','DZ' => '213','EC' => '593','EE' => '372','EG' => '20','ER' => '291','ES' => '34','ET' => '251','FI' => '358','FJ' => '679','FK' => '500','FM' => '691','FO' => '298','FR' => '33','GA' => '241','GB' => '44','GD' => '1473','GE' => '995','GH' => '233','GI' => '350','GL' => '299','GM' => '220','GN' => '224','GQ' => '240','GR' => '30','GT' => '502','GU' => '1671','GW' => '245','GY' => '592','HK' => '852','HN' => '504','HR' => '385','HT' => '509','HU' => '36','ID' => '62','IE' => '353','IL' => '972','IM' => '44','IN' => '91','IQ' => '964','IR' => '98','IS' => '354','IT' => '39','JM' => '1876','JO' => '962',
            'JP' => '81','KE' => '254','KG' => '996','KH' => '855','KI' => '686','KM' => '269','KN' => '1869','KP' => '850','KR' => '82','KW' => '965','KY' => '1345','KZ' => '7','LA' => '856','LB' => '961','LC' => '1758','LI' => '423','LK' => '94','LR' => '231','LS' => '266','LT' => '370','LU' => '352','LV' => '371','LY' => '218','MA' => '212','MC' => '377','MD' => '373','ME' => '382',
            'MF' => '1599','MG' => '261','MH' => '692','MK' => '389','ML' => '223','MM' => '95','MN' => '976','MO' => '853','MP' => '1670','MR' => '222','MS' => '1664','MT' => '356','MU' => '230','MV' => '960','MW' => '265','MX' => '52','MY' => '60','MZ' => '258','NA' => '264','NC' => '687','NE' => '227',
            'NG' => '234','NI' => '505','NL' => '31','NO' => '47','NP' => '977','NR' => '674','NU' => '683','NZ' => '64','OM' => '968','PA' => '507','PE' => '51','PF' => '689','PG' => '675','PH' => '63','PK' => '92','PL' => '48','PM' => '508','PN' => '870','PR' => '1','PT' => '351','PW' => '680','PY' => '595','QA' => '974','RO' => '40','RS' => '381','RU' => '7','RW' => '250','SA' => '966','SB' => '677','SC' => '248','SD' => '249','SE' => '46','SG' => '65','SH' => '290','SI' => '386','SK' => '421','SL' => '232','SM' => '378','SN' => '221','SO' => '252','SR' => '597','ST' => '239','SV' => '503','SY' => '963','SZ' => '268','TC' => '1649','TD' => '235','TG' => '228','TH' => '66','TJ' => '992','TK' => '690','TL' => '670','TM' => '993','TN' => '216','TO' => '676','TR' => '90','TT' => '1868','TV' => '688','TW' => '886','TZ' => '255','UA' => '380','UG' => '256','US' => '1','UY' => '598','UZ' => '998','VA' => '39','VC' => '1784','VE' => '58','VG' => '1284','VI' => '1340',
            'VN' => '84','VU' => '678','WF' => '681','WS' => '685','XK' => '381','YE' => '967','YT' => '262','ZA' => '27','ZM' => '260','ZW' => '263'
        ];
        return $countryCodeArray[$countryCode];
    }

    private static function getGatewayUrl()
    {
        return env('local_env') ? 'https://service-sandbox.tazapay.com' :
            'https://service.tazapay.com';
    }

    private static function authentication()
    {
        $apiKey = env('stripe.public_key');
        $apiSecret = env('stripe.private_key');
        $basic_auth = $apiKey . ':' . $apiSecret;
        return "Basic " . base64_encode($basic_auth);
    }

    //数字
    public function next_rand1($matches)
    {
        return $this->randnum($matches[1]);
    }

    //字母
    public function next_rand2($matches)
    {
        return $this->randzimu($matches[1]);
    }

    //字符串
    public function next_rand3($matches)
    {
        return $this->randomkeys($matches[1]);
    }

    //生成随机数字
    public function randnum($length){
        $string ='';
        for($i = 1; $i <= $length; $i++){
            $string.=rand(0,9);
        }

        return $string;

    }

    //生成随机字母
    public function randzimu($length){
        $str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';//62个字符
        $strlen = 62;
        while($length > $strlen){
            $str .= $str;
            $strlen += 62;
        }
        $str = str_shuffle($str);
        return substr($str,0,$length);
    }

    //生成随机字符串
    public function randomkeys($length)
    {
        $str = array_merge(range(0,9),range('a','z'),range('A','Z'));
        shuffle($str);
        return implode('',array_slice($str,0,$length));
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