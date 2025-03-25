<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2021/5/5
 * Time: 10:30
 */
namespace app\index\validate;
use think\Validate;
class PayValidate extends Validate
{
    protected $rule = [
        'username' => ['require'],
        'host' => ['require'],
        'return_uri' => ['require','url'],
        'success_uri' => ['require','url'],
        'notify_url' => ['require','url'],
        'invoice_id' => ['require'],
        'order_no' => ['require'],
        'center_id' => ['require','integer','gt:0'],
        'amount' => ['require','float'],
        'first_name' => ['require'],
        'last_name' => ['require'],
        'currency' => ['require','max:4'],
        'address1' => ['require'],
        'city' => ['require'],
        'token' => ['require','length:32'],
        'transaction_id' => ['require']
    ];
    protected $message = [
        'username.require' => 'Username can not be empty.',
        'host.require' => 'Domain name cannot be empty.',
        'return_uri.require' => 'Callback link cannot be empty.',
        'require_uri.url' => 'Illegal callback link.',
        'success_uri.require' => 'Success callback link cannot be empty.',
        'success_uri.url' => 'Illegal success callback link.',
        'notify_url.require' => 'Asynchronous callback link cannot be empty.',
        'notify_url.url' => 'Illegal asynchronous callback link.',
        'invoice_id.require' => 'Invoice number cannot be empty.',
        'order_no.require' => 'Order number cannot be empty.',
        'order_no.integer' => 'The order number is an integer.',
        'order_no.gt' => 'Illegal order number.',
        'center_id.require' => 'The control number cannot be empty.',
        'center_id.integer' => 'The central control number is an integer.',
        'center_id.gt' => 'Illegal central control number.',
        'amount.require' => 'The payment amount cannot be empty.',
        'amount.float' => 'Illegal payment.',
        'first_name.require' => 'First name is empty.',
        'last_name.require' => 'Last name is empty.',
        'currency.require' => 'Currency cannot be empty.',
        'currency.max' => 'Currency illegal.',
        'address1.require' => 'Address cannot be empty.',
        'city.require' => 'The city cannot be empty.',
        'token.require' => 'Token can not be empty.',
        'token.length' => 'Error token size.',
        'transaction_id.require' => 'Transaction ID can not be empty'
    ];

    // 验证场景
    protected $scene = [
        'submit' => [],
        'createOrder' => [],
        'getAccountData' => ['token'],
        'refund' => ['transaction_id', 'token']
    ];
}