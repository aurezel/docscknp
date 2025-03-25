<?php

namespace app\index\traits;

trait USeePayTool
{
    public static function getTerminalType(): string
    {
        $terminalType = 'WEB';
        $user_agent = $_SERVER['HTTP_USER_AGENT'];
        if (preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $user_agent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($user_agent, 0, 4))) {
            $terminalType = 'H5';
        }
        return $terminalType;
    }


    /**
     * 过滤特殊字符串
     * @param $str
     * @return array|string|string[]
     */
    public static function filter_chars($str)
    {
        //转义"双引号,<小于号,>大于号,'单引号
        return str_replace(
            ["<", ">", "'", "\"", "&"],
            ["", "", "", "", ""],
            trim($str));
    }

    /**
     * 生成签名串
     * @param $param
     * @param $sign_key
     * @return string
     */
    public static function md5Sign($param, $sign_key): string
    {
        $sign_data = self::getSignData($param);
        $sign_data .= "&pkey=" . trim($sign_key);
        return strtolower(md5($sign_data));
    }

    /**
     * 建立跳转请求表单
     * @param string $url 数据提交跳转到的URL
     * @param array $data 请求参数数组
     * @param string $method 提交方式：post或get 默认post
     * @return string 提交表单的HTML文本
     */
    public static function build_request_form(string $url, array $data, string $method = 'post'): string
    {
        $sHtml = "<form id='requestForm' name='requestForm' action='" . $url . "' method='" . $method . "'>";
        foreach ($data as $key => $value) {
            $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $value . "' />";
        }

        $sHtml = $sHtml . "<input type='submit' value='确定' style='display:none;'></form>";
        return $sHtml . "<script>document.forms['requestForm'].submit();</script>";
    }

    /**
     * 发送请求
     * @param $paymentUrl
     * @param $paymentData
     * @return bool|string
     */
    public static function submitWithReturn($paymentUrl, $paymentData)
    {
        if (function_exists('curl_init') && function_exists('curl_exec')) {
            $info = self::vPost($paymentUrl, $paymentData);
        } else {
            $info = self::hPost($paymentUrl, $paymentData);
        }
        return $info;
    }

    public static function vPost($url, $data)
    {
        $curl_cookie = "";
        foreach ($_COOKIE as $key => $value) {
            $curl_cookie .= $key . "=" . $value . ";";
        }
        generateApiLog('USeePay Request Data:'.json_encode($data,JSON_UNESCAPED_SLASHES));
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($curl, CURLOPT_REFERER, $_SERVER['HTTP_HOST']);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_TIMEOUT, 300);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_COOKIE, $curl_cookie);
        $tmpInfo = curl_exec($curl);
        curl_close($curl);
        generateApiLog('USeePay Response Data:'.$tmpInfo);
        $rspBody = json_decode($tmpInfo,true);
        if (!isset($rspBody['errorCode']))  throw new \Exception("Useepay 响应数据异常:" . $rspBody);
        return $rspBody;
    }

    public static function hPost($url, $data)
    {
        $website = $_SERVER['HTTP_HOST'];
        $cookie = "";
        foreach ($_COOKIE as $key => $value) {
            $cookie .= $key . "=" . $value . ";";
        }
        $options = array(
            'http' => array(
                'method' => "POST",
                'header' => "Accept-language: en\r\n" . "Cookie: $cookie\r\n" . "referer:$website \r\n",
                'content-type' => "multipart/form-data",
                'content' => $data,
                'timeout' => 15 * 60
            )
        );
        //创建并返回一个流的资源
        $context = stream_context_create($options);
        //var_dump($options);exit;
        return file_get_contents($url, false, $context);
    }

    public static function rsaSign($param): string
    {
        $sign_data = self::getSignData($param);
        generateApiLog("sign_data step1:" . var_export($sign_data, true));

        $sign_data = str_replace(array("\r", "\n", "\r\n"), '', $sign_data);
        $sign_data = self::str2utf8($sign_data);
        generateApiLog("sign_data step2:" . var_export($sign_data, true));

        $sign = '';
        $private_key = self::str2key(env('stripe.private_key'), 'pri');
        $private_key_id = openssl_get_privatekey($private_key);
        openssl_sign($sign_data, $sign, $private_key_id, OPENSSL_ALGO_SHA256);
        $sign = base64_encode($sign);
        generateApiLog("sign:" . var_export($sign, true));
        return $sign;
    }

    function rsaVerify($param, $sign)
    {
        $sign_data = self::getSignData($param);
        $sign_data = str_replace(array("\r", "\n", "\r\n"), '', $sign_data);
        $sign_data = self::str2utf8($sign_data);
        // RSA签名串base64转码后'+'会变成' '， 做反向操作
        $sign = str_replace(' ', '+', $sign);
        $sign = base64_decode($sign);
        $public_key = self::str2key(env('stripe.public_key'), 'pub');
        $public_key_id = openssl_get_publickey($public_key);
        return openssl_verify($sign_data, $sign, $public_key_id, OPENSSL_ALGO_SHA256);
    }

    public static function getSignData($param)
    {
        $sign_data = '';
        ksort($param);
        foreach ($param as $key => $value) {
            $v = trim($value);
            if (strlen($v) > 0 && $key != 'sign') {
                $sign_data .= '&' . $key . '=' . $v;
            }
        }
        return substr($sign_data, 1);
    }

    /**
     * 将字符串转为公私钥格式
     * @param string $str 字符串
     * @param string $type pub || pri
     * @return string
     */
    public static function str2key(string $str, string $type): string
    {
        $key = wordwrap($str, 64, PHP_EOL, true);
        $start = '';
        $end = '';
        switch ($type) {
            case 'pub':
                $start = '-----BEGIN PUBLIC KEY-----' . PHP_EOL;
                $end = PHP_EOL . '-----END PUBLIC KEY-----' . PHP_EOL;
                break;
            case 'pri':
                $start = '-----BEGIN PRIVATE KEY-----' . PHP_EOL;
                $end = PHP_EOL . '-----END PRIVATE KEY-----' . PHP_EOL;
                break;
        }
        return $start . $key . $end;
    }
    /**
     * 将字符串编码转为 utf8
     * @param $str
     * @return string
     */
    public static function str2utf8($str): string
    {
        $encode = mb_detect_encoding($str, array('ASCII', 'UTF-8', 'GB2312', 'GBK', 'BIG5'));
        $str = $str ?: mb_convert_encoding($str, 'UTF-8', $encode);
        return is_string($str) ? $str : '';
    }
}