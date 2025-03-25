<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/3/29
 * Time: 14:29
 * 公用文件
 */

if (!function_exists('get_env_value'))
{
    function get_env_value($param)
    {
        $envPath = '../.env';
        static $envConfig;
        if (!isset($envConfig))
        {
            if (file_exists($envPath)) {
                $params = explode('.', $param);
                $envConfig = parse_ini_file($envPath);
                if (count($params) === 2) {
                    return $envConfig[$params[0]][$params[1]];
                }
                return $envConfig[$param];
            }
            return '';
        }
        return $envConfig[$param];
    }
}

if (!function_exists('get_client_ip'))
{
    function get_client_ip()
    {
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } else {
            if (getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
                $ip = getenv('HTTP_CLIENT_IP');
            } elseif (getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
                $ip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
                $ip = getenv('REMOTE_ADDR');
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        }
        return $ip;
    }
}


if (!function_exists('write_log'))
{
    function write_log($msg)
    {
        $currentTime = '['.date('Y-m-d H:i:s') . ']  ';
        $version = '====================Version:'.get_env_value('stripe_version')."====================\r\n";
        file_put_contents('error.log',$version . $currentTime. $msg ."\r\n",FILE_APPEND | LOCK_EX);
    }
}

if (!function_exists('get_params_token'))
{
    function get_params_token($first_name,$center_id,$amount,$last_name)
    {
        return openssl_encrypt(json_encode([
            'first_name' => $first_name,
            'center_id' => intval($center_id),
            'amount' => floatval($amount),
            'last_name' => $last_name
        ]),'DES-OFB',get_env_value('encrypt_password'),OPENSSL_DONT_ZERO_PAD_KEY,get_env_value('encrypt_iv'));
    }
}

if (!function_exists('get_3d_token'))
{
    function get_3d_token($center_id)
    {
        return openssl_encrypt(json_encode([
            'center_id' => intval($center_id)
        ]),'DES-OFB',get_env_value('encrypt_password'),OPENSSL_DONT_ZERO_PAD_KEY,get_env_value('encrypt_iv'));
    }
}

if (!function_exists('get_params_config'))
{
    function get_params_config($param_name)
    {
        static $config;
        if (!isset($config)) $config = require_once "../application/index/config/parameters.php";
        return $config[$param_name] ?? null;
    }
}

if (!function_exists('custom_encrypt'))
{
    function custom_encrypt($data)
    {
        $key = get_params_config('encrypt_key');
        $iv = get_params_config('encrypt_iv');
        $encrypt = base64_encode(openssl_encrypt(serialize($data), 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv));
        $encrypt = str_replace('+','_',$encrypt);
        $encrypt = str_replace('=','-',$encrypt);
        $encrypt = str_replace('/','*',$encrypt);
        return $encrypt;
    }
}

if (!function_exists('custom_decrypt'))
{
    function custom_decrypt($encrypt)
    {
        $encrypt = str_replace('_','+',$encrypt);
        $encrypt = str_replace('-','=',$encrypt);
        $encrypt = str_replace('*','/',$encrypt);
        $key = get_params_config('encrypt_key');
        $iv = get_params_config('encrypt_iv');
        $decrypt = unserialize(openssl_decrypt(base64_decode($encrypt), 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv));
        return $decrypt;
    }
}