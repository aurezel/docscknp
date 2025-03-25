<?php
error_reporting(0);
const BASE_PATH = __DIR__ .DIRECTORY_SEPARATOR;
require_once  BASE_PATH ."common.php";

if ($_SERVER['REQUEST_METHOD'] == 'POST')
{
    $centerId = $_POST['center_id'] ? intval($_POST['center_id']) : 0;
    if ($centerId == 0) return apiError('Illegal Params.');
    $centerUrl = get_env_value('local_env') ? get_env_value('local_url') : get_env_value('remote_url');
    $ip = get_client_ip();
    try{
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $centerUrl . '/zzpay/getParams');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept:application/xml',
            'Content-Type:application/xml;charset=utf-8',
            'token:'.get_params_config('token')
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        $host = $_SERVER['HTTP_HOST'];
        $ua = $_SERVER['HTTP_USER_AGENT'];
        $data = $host.'_' . $ua.'_' . $centerId . '_' . get_env_value('private_key');
        $accessToken = md5(hash('sha256',$data));
        $requestParams =
            [
                'center_id' => $centerId,
                'access_token' => $accessToken,
                'client_ip' => $ip,
                'host' => $host,
                'ua' => $ua
            ];
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($requestParams));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $curlResult = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        if ($curlErrNo) {
            $curlErrMsg = curl_error($ch);
            write_log("curlErrMsg:{$curlErrMsg}");
        }
        if (empty($curlResult))
        {
            write_log('checkout Curl获取数据为空');
            die('Illegal Access!');
        }
        $curlResult = json_decode($curlResult,true);
        if (!isset($curlResult['status']) or $curlResult['status'] == 0)
        {
            write_log('获取传参接口异常:'.json_encode($curlResult));
            die('Illegal Request!');
        }
        $centerParams = $curlResult['data'];
    }catch (\Exception $e)
    {
        write_log('checkout Curl Exception:'.$e->getMessage()."\r\n".'Line:'.$e->getLine().'Trace:'.$e->getTraceAsString());
        die('Internal Error!');
    }
} else{
    $centerParams = array();
    $_POST['center_id'] = 1;
    $centerId = 1;
    $centerParams['amount'] = 62.1;
    $centerParams['order_no'] = mt_rand(10000,99999);
    $centerParams['currency'] = "USD";
    $centerParams['email']='test@test.com';
    $centerParams['telephone']='9091231231';
    $centerParams['country']='US';
    $centerParams['city']='LA';
    $centerParams['state']='CA';
    $centerParams['address']='address';
    $centerParams['zip_code']='90121';
    $centerParams['first_name']='Jone';
    $centerParams['last_name'] = 'Lincc';
}

$stripeVersion = get_env_value('stripe_version');

if (!in_array($stripeVersion,get_params_config('stripe_version')))
{
    write_log('stripe version config not enable');
    $stripeVersion = 'v3'; //配置不存在默认为v3
}

$checkoutPath = BASE_PATH .'views' . DIRECTORY_SEPARATOR . $stripeVersion . '_checkout.php';
if (!file_exists($checkoutPath))
{
    write_log("File {$checkoutPath} Not Exist!");
    die('Internal Error!');
}

require_once $checkoutPath;

?>
