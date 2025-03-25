<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/29
 * Time: 14:24
 */

namespace app\index\service;

class PlaceFlutterOrderService extends BaseService
{
    private $secretKey,$encryptKey,$centerId,$transactionId,$errMsg;

    const BASE_URL = 'https://api.flutterwave.com/v3';
    const CHARGE_URL =  self::BASE_URL . '/charges?type=card';
    const VALIDATE_URL = self::BASE_URL .'/validate-charge';
    const VERIFY_TRANSACTION_URL = self::BASE_URL . '/transactions';

    public function __construct($centerId = 0,$transactionId = 0,$errMsg = '')
    {
        $this->secretKey = env('stripe.public_key');
        $this->encryptKey = env('stripe.private_key');

        //params
        $this->centerId = $centerId;
        $this->transactionId = $transactionId;
        $this->errMsg = $errMsg;
    }

    public function placeOrder(array $params = [])
    {
        try{
            if (!$this->checkToken($params)) return apiError();
            $this->centerId = $params['center_id'] ?? 0;
            $postData = [
                'amount' => $params['amount'],
                'card_number' => str_replace(' ','',$params['card_number']),
                'tx_ref' => self::generateOrderId('FLT_'),
                'cvv' => $params['cvc'],
                'expiry_month' => str_pad($params['expiry_month'],2,"0",STR_PAD_LEFT),
                'expiry_year' => $params['expiry_year'],
                'email' => $params['email'],
                'currency' => $params['currency_code'],
                'phone_number' => $params['phone'],
                'fullname' => $params['first_name'] . ' '.$params['last_name'],
                'redirect_url' => request()->domain() .'/checkout/pay/flutterRedirect?center_id='.customEncrypt($this->centerId),
                'client_ip' => get_real_ip(),
            ];
            $mode = $params['mode'] ?? '';
            if (empty($mode))
            {
                $this->validateCard();
                $chargeResult = $this->sendFlutterData(self::CHARGE_URL,$postData);
                if (!$chargeResult) return apiError();
                if ($chargeResult['status'] == 'error')
                {
                    $this->errMsg = $chargeResult['message'];
                    $this->sendDataToCentral('error');
                    return apiError();
                }
            }

            if ($mode == 'pin')
            {
                $pin = $params['pin'] ?? '';
                if (empty($pin)) return apiError();
                $postData['authorization'] = [
                    'mode' => 'pin',
                    'pin' => $pin
                ];

                $pinResult = $this->sendFlutterData(self::CHARGE_URL,$postData);
                if (!$pinResult) return apiError();
                if ($pinResult['status'] == 'error')
                {
                    $this->errMsg = $pinResult['message'];
                    $this->sendDataToCentral('error');
                    return apiError();
                }
                else{
                    if ($pinResult['data']['status'] == 'successful')
                    {
                        // 直接成功的 ok
                        $transaction = $this->verifyTransaction($pinResult['data']['id']);
                        $this->transactionId = $transaction['data']['tx_ref']; //参考交易ID，实际交易ID为data.id
                        $sendResult = $this->sendDataToCentral('success');
                        if (!$sendResult) return apiError();
                        return apiSuccess($sendResult);
                    }else if(isset($pinResult['meta']['authorization']['mode']) && $pinResult['meta']['authorization']['mode'] == 'otp')
                    {
                        // otp charge validate
                        return apiSuccess(['otp' => true,'flw_ref' => $pinResult['data']['flw_ref']]); //让用户输入OTP
                    }
                }
            }
            if ($mode == 'otp')
            {
                $otp = $params['otp'] ?? '';
                $flw_ref = $params['flw_ref'] ?? '';
                if (empty($otp) || empty($flw_ref)) return apiError();
                // opt提交验证
                $validateChargeResult = $this->sendFlutterData(self::VALIDATE_URL,[
                    'type' => 'card',//type can be card or account
                    'flw_ref' => $flw_ref,
                    'otp' => $otp,
                ],true,false);
                if ($validateChargeResult['status'] == 'error')
                {
                    $this->errMsg = $validateChargeResult['message'];
                    $this->sendDataToCentral('error');
                    return apiError();
                }
                if ($validateChargeResult['data']['status'] == 'successful')
                {
                    $this->transactionId = $validateChargeResult['data']['tx_ref']; //参考交易ID，实际交易ID为data.id
                    $sendResult = $this->sendDataToCentral('success');
                    if (!$sendResult) return apiError();
                    return apiSuccess($sendResult);
                }
            }

            switch ($chargeResult['meta']['authorization']['mode'] ?? null) {
                case 'pin':
                    return apiSuccess(['pin' => true]); //让用户输入PIN
                case 'avs_noauth':
                    $postData["authorization"] = array(
                        "mode" => "avs_noauth",
                        "city" => $params['city'],
                        "address" => empty($params['address2']) ? $params['address1'] : $params['address1'] . ' ' . $params['address2'],
                        "state" => $params['state'],
                        "country" => $params['country'],
                        "zipcode" => $params['zip'],
                    );
                    $avsResult = $this->sendFlutterData(self::CHARGE_URL,$postData);
                    if (!$avsResult) return apiError();
                    $redirectUrl = $avsResult['meta']['authorization']['redirect'] ?? '';
                    if (empty($redirectUrl)) return apiError();
                    return apiSuccess(['redirect_url' => $redirectUrl]);
                case 'redirect':
                    //ok
                    $redirectUrl = $chargeResult['meta']['authorization']['redirect'] ?? '';
                    if (empty($redirectUrl)) return apiError();
                    return apiSuccess(['redirect_url' => $redirectUrl]);
                default:
                    // ok
                    $transactionId = $chargeResult['data']['id'] ?? 0;
                    if (!$transactionId) return apiError();
                    $transaction = $this->verifyTransaction($transactionId);
                    if ($transaction['data']['status'] == "successful") {
                        $this->transactionId = $transaction['data']['tx_ref']; //参考交易ID，实际交易ID为data.id
                        $sendResult = $this->sendDataToCentral('success');
                        if (!$sendResult) return apiError();
                        return apiSuccess($sendResult);
                    } else {
                        $this->errMsg = $transaction['data']['message']; // confirm
                        $this->sendDataToCentral('error');
                        return apiError();
                    }
            }

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


    private function verifyTransaction($id)
    {
        $url = self::VERIFY_TRANSACTION_URL ."/" . $id . "/verify";
        $result = $this->sendFlutterData($url,[],false);
        return $result;
    }

    public function sendDataToCentral($status)
    {
        if (!in_array($status,['success','error'])) return false;
        $centralStatus = 'failed';
        if ($status == 'success') $centralStatus = 'success';
        // 发送到中控
        $postCenterData = [
            'transaction_id' => $this->transactionId,
            'center_id' => $this->centerId,
            'action' => 'create',
            'status' => $centralStatus,
            'failed_reason' => $this->errMsg
        ];
        $sendResult = json_decode(sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA),true);
        if (!isset($sendResult['status']) or $sendResult['status'] == 0)
        {
            generateApiLog(REFERER_URL .'创建订单传送信息到中控失败：' . json_encode($sendResult));
            return false;
        }
        $riskyFlag = $sendResult['data']['success_risky'] ?? false;
        return ['success_risky' => $riskyFlag];
    }
    private function sendFlutterData($url,$data,$isPost = true,$isEncrypt = true)
    {
        $headers[] = "Content-Type:application/json";
        $headers[] = "Authorization: Bearer ".$this->secretKey;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($isPost)
        {
            $encryptData = array(
                'client' => $this->encrypt($this->encryptKey,$data)
            );
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $isEncrypt ? json_encode($encryptData) : json_encode($data));
        }
        $response = curl_exec($ch);
        $curlErrMsg = curl_error($ch);
        if ($curlErrMsg)
        {
            generateApiLog("CURL错误：".$curlErrMsg);
            curl_close($ch);
            return false;
        }

        curl_close($ch);
        return json_decode($response,true);
    }

    private static function generateOrderId($prefix = '')
    {
        $rndStr = date('YmdHis');
        list($mt, $tm) = explode(' ', microtime());
        $millisecondsStr = str_pad(intval($mt * 1000), 3, '0', STR_PAD_LEFT);
        $rnd = rand(1000, 9999);
        return $prefix . $rndStr . $millisecondsStr . $rnd;
    }

    private function encrypt(string $encryptionKey, array $payload)
    {
        $encrypted = openssl_encrypt(json_encode($payload), 'DES-EDE3', $encryptionKey, OPENSSL_RAW_DATA);
        return base64_encode($encrypted);
    }
}