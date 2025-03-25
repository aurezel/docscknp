<?php
/**
 * Created by PhpStorm.
 * User: kjj
 * Date: 2021/5/5
 * Time: 9:55
 */
namespace app\index\controller;
use app\index\validate\PayValidate;
use think\Controller;
use think\facade\Request;

class Pay extends Controller
{
    private $validate;
    private $paramsArray;
    protected $checkResult;

    public function initialize()
    {
        parent::initialize();
        $this->paramsArray = input();
        $this->validate = validate(PayValidate::class);
        $this->checkResult = $this->validate->scene(request()->action(true))->check($this->paramsArray);
    }

    public function createOrder()
    {
        if (!$this->getTried()) return apiError();
        $version = env('stripe.stripe_version');
        if (!in_array($version,config('parameters.stripe_version'))) $version = 'v3';
        return app($version)->placeOrder($this->paramsArray);
    }

    private function getTried(int $num = 5)
    {
        $centerId = $this->paramsArray['center_id'] ?? 0;
        if (!$centerId) return false;
        try {
            $result = sendCurlData(GET_TRIED_COUNT_URL . "?center_id={$centerId}",[],CURL_HEADER_DATA,false);
            $result = json_decode($result,true);
            if ($result['status'] == 0) return false;
            $data = $result['data'];
            if ($data['tried'] >= $num || $data['captcha_status'] == 0 || $data['status'] == 'success') return false;
            return true;
        } catch (\Exception $e)
        {
            generateApiLog("请求中控交易次数接口异常：".$e->getMessage()."\r\n" . $e->getLine());
        }
        return false;
    }

    public function errorCount()
    {
        $centerId = input('center_id/d',0);
        if ($centerId === 0) return apiError('Illegal Params');
        $result = sendCurlData(CENTER_URL.'/zzpay/setBlackList',['center_id' => $centerId]);
        if (empty($result))
        {
            generateApiLog('errorCount获取数据为空');
            return apiError();
        }
        $result = json_decode($result,true);
        if (!isset($result['status']) or $result['status'] == 0) return apiError();
        return apiSuccess();
    }

    public function publicKeyErrorDeal()
    {
        $centerId = input('post.center_id/d',0);
        $reason = input('post.reason/s','public key error!');
        if ($centerId == 0) return apiError('Illegal Params.');
        try{
            $postCenterData = [
                'transaction_id' => 0,
                'center_id' => $centerId,
                'action' => 'create',
                'status' => 'failed',
                'failed_reason' => $reason
            ];
            $sendResult = sendCurlData(CHANGE_PAY_STATUS_URL,$postCenterData,CURL_HEADER_DATA);
            if (empty($sendResult))
            {
                generateApiLog('publicKeyErrorDeal Curl获取数据为空');
                return apiError();
            }
            $sendResult = json_decode($sendResult,true);
            if (!isset($sendResult['status']) or $sendResult['status'] == 0)
            {
                generateApiLog(REFERER_URL .'publicKeyErrorDeal创建订单传送信息到中控失败：' . json_encode($sendResult));
                return apiError();
            }
            return apiSuccess();
        }catch (\Exception $e)
        {
            generateApiLog(['Public Key Error Deal Api Exception:' => $e->getMessage()]);
            return apiError();
        }
    }

    public function getAccountData()
    {
        if (!$this->checkResult) return apiError($this->validate->getError());
        try{
            $responseData = array();
            $publicKey = env('stripe.public_key');
            $privateKey = env('stripe.private_key');
            if ($this->paramsArray['token'] !== md5(hash('sha256', $publicKey . $privateKey))) return apiError('Token error!');
            $curl = new \Stripe\HttpClient\CurlClient(getCurlOpts());
            \Stripe\ApiRequestor::setHttpClient($curl);
            $stripe = new \Stripe\StripeClient($privateKey);
            $accountInfo = $stripe->accounts->retrieve();
            if (!isset($accountInfo->object)) return apiError('Error Account API');
            $responseData['charges_enabled'] = $accountInfo->charges_enabled;
            $responseData['payout_enable'] = $accountInfo->payouts_enabled;
            $responseData['default_currency'] = strtoupper($accountInfo->default_currency);
            $responseData['period'] = $accountInfo->settings->payouts->schedule->delay_days;

            $balanceInfo = $stripe->balance->retrieve();
            if (!isset($balanceInfo->object)) return apiError('Error Balance Api!');
            $availableCurrency = strtoupper($balanceInfo->available[0]->currency);
            $availableAmount = $balanceInfo->available[0]->amount;
            $pendingCurrency = strtoupper($balanceInfo->pending[0]->currency);
            $pendingAmount = $balanceInfo->pending[0]->amount;
            $currency_dec = config('parameters.currency_dec');
            for($i = 0; $i < $currency_dec[$availableCurrency]; $i++) {
                $availableAmount /= 10;
            }
            for($i = 0; $i < $currency_dec[$pendingCurrency]; $i++) {
                $pendingAmount /= 10;
            }

            $responseData['available_amount'] = round($availableAmount,$currency_dec[$availableCurrency]);
            $responseData['available_currency'] = $availableCurrency;
            $responseData['pending_amount'] = round($pendingAmount,$currency_dec[$pendingCurrency]);
            $responseData['pending_currency'] = $pendingCurrency;
            $responseData['live_mode'] = $balanceInfo->livemode;

            $withdrawRecords = $stripe->payouts->all();
            if (!isset($withdrawRecords->object)) return apiError('Error Withdraw Api!');

            $withdrawData = $withdrawRecords->data;
            if (empty($withdrawData))
            {
                $responseData['withdraw_record'] = '';
            }else{
                foreach ($withdrawData as $key => $data)
                {
                    $withdraw_amount = $data['amount'];
                    $withdraw_currency = strtoupper($data['currency']);
                    for($i = 0; $i < $currency_dec[$withdraw_currency]; $i++) {
                        $withdraw_amount /= 10;
                    }
                    $responseData['withdraw_record'][$key]['amount'] = $withdraw_amount;
                    $responseData['withdraw_record'][$key]['currency'] = $withdraw_currency;
                    $responseData['withdraw_record'][$key]['create_time'] = $data['created'];
                    $responseData['withdraw_record'][$key]['arrival_time'] = $data['arrival_date'];
                    $responseData['withdraw_record'][$key]['status'] = $data['status'];
                }
            }
            return apiSuccess($responseData);
        }catch (\Exception $e)
        {
            generateApiLog('获取账户信息接口异常：'.$e->getMessage() .'行数：'.$e->getLine());
            return apiError();
        }
    }

    public function refund() {
        if (!$this->checkResult) return apiError($this->validate->getError());
        $privateKey = env('stripe.private_key');
        $version = env('stripe.stripe_version');
        if(!in_array($version,['v2','v3','connect','stripe_checkout','stripe_link','checkout_beta','st_checkout_price'])) {
            return apiError("Unsupported version type");
        }
        $transaction_id = $this->paramsArray['transaction_id'];
        if ($this->paramsArray['token'] !== md5(hash('sha256', $transaction_id . $privateKey))) return apiError('Token error!');

        $curl = new \Stripe\HttpClient\CurlClient(getCurlOpts());
        \Stripe\ApiRequestor::setHttpClient($curl);
        $stripe = new \Stripe\StripeClient($privateKey);

        if ($version == 'connect')
        {
            \Stripe\Stripe::setAccountId(env('stripe.merchant_token'));
        }

        try {
            if(substr($transaction_id, 0,3) == 'ch_') {
                $body = ['charge' => $transaction_id];
            } else if(substr($transaction_id, 0,3) == 'pi_') {
                $body = ['payment_intent' => $transaction_id];
            }
            $body['reason'] = 'requested_by_customer';
            $stripe->refunds->create($body);
            return apiSuccess();
        }catch(\Exception $e) {
            return apiError($e->getMessage());
        }
    }
}