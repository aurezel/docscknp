<?php
/**
 * Created by PhpStorm.
 * User: HJL
 * Date: 2021/5/20 20:10
 */

namespace app\index\validate;

use think\Validate;
class IndexValidate extends Validate
{
    protected $rule = [
        'name' => ['require'],
        'public_key' => ['require'],
        'private_key' => ['require'],
        'remote_url' => ['require']
    ];
    protected $message = [
        'name.require' => 'Account cannot be empty',
        'public_key.require' => 'public_key cannot be empty',
        'private_key.require' => 'private_key can not be empty',
        'remote_url.require' => 'Central url cannot be empty'
    ];

    // 验证场景
    protected $scene = [
        'accountUpdate' => ['name','public_key','private_key','remote_url']
    ];
}