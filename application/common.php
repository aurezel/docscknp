<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
use think\facade\Config;
use think\facade\Log;
use think\facade\Env;

if (!defined('CENTER_URL')) define('CENTER_URL',Env::get('local_env') ? Env::get('center.local_url') : Env::get('center.remote_url'));
if (!defined('REFERER_URL')) define('REFERER_URL',$_SERVER['HTTP_REFERER'] ?? 'Unknown referer domain');
const CHANGE_PAY_STATUS_URL = CENTER_URL . '/zzpay/changeStatus';
const GET_TRIED_COUNT_URL = CENTER_URL . '/zzpay/getTriedCount';
const CURL_HEADER_DATA = ['header' => true, 'type' => '', 'authorization' => ''];


// 接口请求成功
if (!function_exists('apiSuccess')) {
    function apiSuccess($data = [], $message = 'Request Success!', $error_code = 0)
    {
        if (empty($data)) {
            $data = (object)[];
        }
        $responseData = [
            'errmsg' => $message,
            'data' => $data,
            'errcode' => $error_code,
        ];
        return json($responseData);
    }
}

// 接口请求失败
if (!function_exists('apiError')) {
    function apiError($message = 'Internal error, please try again later!', $error_code = 1, $data = [])
    {
        if (empty($data)) {
            $data = (object)[];
        }
        $responseData = [
            'errmsg' => $message,
            'data' => $data,
            'errcode' => $error_code,
        ];
        return json($responseData);
    }
}

if (!function_exists('generateApiLog')) {
    /**
     * 生成API日志
     *
     * @param $msg
     * @param string $type
     */
    function generateApiLog($msg, $type = 'error')
    {
        generateCustomLog([
            'msg' => $msg,
            'params' => input()
        ], '/api', $type);
    }
}


if (!function_exists('generateCustomLog')) {
    /**
     * 生成自定义日志
     *
     * @param $msg
     * @param string $type
     * @param string $path
     */
    function generateCustomLog($msg, $path = '', $type = 'error')
    {
        $logConfig = Config::pull('log');
        if (!empty($path)) {
            $logConfig['path'] = config('app.log_server_root_path') . $path;
            $logConfig['close'] = true;
        }
        Log::init($logConfig);
        Log::write($msg, $type);
    }
}

if (!function_exists('objectToArray')){
    function objectToArray($array) {
        if(is_object($array)) {
            $array = (array)$array;
        }
        if(is_array($array)) {
            foreach($array as $key=>$value) {
                $array[$key] = objectToArray($value);
            }
        }
        return $array;
    }
}

if (!function_exists('sendCurlData')){
    // CURL 发送数据
    function sendCurlData($curlUrl, $requestParams = [], $params = [], $postBool = true)
    {
        try {
            $curlResult = [];
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $curlUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //不直接输出，返回到变量
            // set header
            if (isset($params['header']) && $params['header']) {
                if ($params['type'] == 'json') {
                    $contentType = 'application/json';
                } else {
                    $contentType = 'application/xml';
                }
                $header = [
                    'Accept:' . $contentType,
                    'Content-Type:' . $contentType . ';charset=utf-8',
                    'Authorization:' . $params['authorization'],
                    'token:' . config('parameters.token')
                ];
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            }

            // set ssl cert
            if (
                isset($params['sslcert']) && $params['sslcert']
                && isset($params['sslkey']) && $params['sslkey']

            ) {
                curl_setopt($ch, CURLOPT_SSLCERTTYPE, 'PEM');
                curl_setopt($ch, CURLOPT_SSLCERT, $params['sslcert']);
                curl_setopt($ch, CURLOPT_SSLKEYTYPE, 'PEM');
                curl_setopt($ch, CURLOPT_SSLKEY, $params['sslkey']);
            }

            // set send data
            if ($postBool) {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestParams));
            }

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $curlResult = curl_exec($ch);
            $curlErrNo = curl_errno($ch);
            if ($curlErrNo) {
                $curlErrMsg = curl_error($ch);
                generateApiLog('curlErrMsg:'.$curlErrMsg);
            }
        } catch (\Exception $e) {
            generateApiLog('exceptionMsg:' .$e->getMessage());
        }
        return $curlResult;
    }
}

if (!function_exists('randomStr'))
{
    function randomStr($length){
        //生成一个包含 大写英文字母, 小写英文字母, 数字 的数组
        $arr = array_merge(range(0, 9), range('a', 'z'), range('A', 'Z'));
        $str = '';
        $arrLen = count($arr)-1;
        for ($i = 0; $i < $length; $i++) {
            $rand = mt_rand(0, $arrLen);
            $str .= $arr[$rand];
        }
        return $str;
    }
}


if (!function_exists('get_real_ip'))
{
    function get_real_ip() {
        if (isset($_SERVER)) {
            if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
                $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
            }
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } else {
            if (getenv('HTTP_X_FORWARDED_FOR')) {
                $ip = getenv('HTTP_X_FORWARDED_FOR');
            } elseif (getenv('HTTP_CLIENT_IP')) {
                $ip = getenv('HTTP_CLIENT_IP');
            } else {
                $ip = getenv('REMOTE_ADDR');
            }
        }
        return $ip;
    }
}

if (!function_exists('getCurlOpts'))
{
    function getCurlOpts() {
    try {
        $use_proxy = intval(Env::get('stripe.use_proxy'));
        if ($use_proxy) {
            $proxy_server = Env::get('stripe.proxy_server');
            $proxy_auth = Env::get('stripe.proxy_auth');
            $opts = [CURLOPT_PROXY => $proxy_server];
            if(!empty($proxy_auth))
                $opts[CURLOPT_PROXYUSERPWD] = $proxy_auth;
            $proxy_type = Env::get('stripe.proxy_type');
            if($proxy_type == 'http')
                $opts[CURLOPT_HTTPPROXYTUNNEL] = 1;
            else if($proxy_type == 'socks5') {
                $opts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5;
            }
            return $opts;
        }
    }catch(\Exception $e) {
        generateApiLog('getCurlOpts接口异常：'.$e->getMessage());
        return array();
    }
    return array();
}
}

if (!function_exists('customEncrypt')) {
    /**
     * 加密方式
     * @param null $data
     * @return string
     */
    function customEncrypt($data = null)
    {
        $key = config('parameters.encrypt_key');
        $iv = config('parameters.encrypt_iv');
        $encrypt = base64_encode(openssl_encrypt(serialize($data), 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv));
        $encrypt = str_replace('+','_',$encrypt);
        $encrypt = str_replace('=','-',$encrypt);
        $encrypt = str_replace('/','*',$encrypt);
        return $encrypt;
    }
}

if (!function_exists('customDecrypt')) {
    /**
     * 解密方式
     * @param $encrypt
     * @return mixed
     */
    function customDecrypt($encrypt)
    {
        $encrypt = str_replace('_','+',$encrypt);
        $encrypt = str_replace('-','=',$encrypt);
        $encrypt = str_replace('*','/',$encrypt);
        $key = config('parameters.encrypt_key');
        $iv = config('parameters.encrypt_iv');
        $decrypt = unserialize(openssl_decrypt(base64_decode($encrypt), 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $iv));
        return $decrypt;
    }
}

if (!function_exists('chmodDir'))
{
    function chmodDir($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                $filePath = $dir . '/' . $file;
                if (is_dir($filePath)) {
                    chmodDir($filePath);
                } else {
                    chmod($filePath,0755);
                }
            }
        }
        return true;
    }
}