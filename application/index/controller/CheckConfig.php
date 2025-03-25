<?php

namespace app\index\controller;

class CheckConfig
{
    public function index()
    {
        $phpversion = PHP_VERSION;
        $appRootPath = app()->getRootPath();
        $envFilePath = $appRootPath . '.env';
        $fileDir = $appRootPath . 'file';
        $extensions = ['curl','openssl','bcmath','hash','json','iconv','xml','dom','SimpleXML','xmlreader','xmlwriter','mbstring','pdo'];

        $htaccessFile = dirname($appRootPath).DIRECTORY_SEPARATOR . '.htaccess';
        $envHtml = is_writeable($envFilePath) ? '<td>是</td>' : '<td class="red-txt">否</td>';
        $fileDirHtml = is_writeable($fileDir) ? '<td>是</td>' : '<td class="red-txt">否</td>';
        $htaccessHtml = '<td>正常</td>';
        if (!is_readable($htaccessFile))
        {
            $htaccessHtml = '<td class="red-txt">不可读</td>';
        }else{
            $stripeVersion = ltrim(env('stripe.stripe_version'),'/');
            $checkoutSuccessPath = ltrim(env('stripe.checkout_success_path'),'/');
            $checkoutCancelPath = ltrim(env('stripe.checkout_cancel_path'),'/');
            $checkoutNotifyPath = ltrim(env('stripe.checkout_notify_path'),'/');

            if (empty($stripeVersion))
            {
                $htaccessHtml = '<td class="red-txt">Stripe Version不存在</td>';
            }else{
                $matchMap = [
                    'rapyd_api' => [$checkoutNotifyPath => 'rdWebhook', $checkoutSuccessPath =>'rdRedirect'],
                    'stripe_link' => [$checkoutNotifyPath=>'stlWebhook', $checkoutSuccessPath=>'stlSuccess'],
                    'eusiapay' => [$checkoutSuccessPath=>'eusReturn',$checkoutNotifyPath=>'eusNotify'],
                    'tazapay' => [$checkoutSuccessPath=>'tazaSuccess',$checkoutCancelPath=>'tazaCancel',$checkoutNotifyPath=>'tazaNotify'],
                    'stripe_checkout' => [$checkoutSuccessPath=>'stckSuccess', $checkoutCancelPath=>'stckCancel', $checkoutNotifyPath=>'stckWebhook'],
                    'checkout_beta' => [$checkoutNotifyPath=>'stcbWebhook',$checkoutSuccessPath=>'stckSuccess',$checkoutCancelPath=>'stckCancel']
                ];
                $checkoutPath = $matchMap[$stripeVersion] ?? '';
                if (empty($checkoutPath))
                {
                    $htaccessHtml = '<td class="red-txt">Stripe Version不在检测范围内</td>';
                }else {
                    $content = explode("\n", file_get_contents($htaccessFile));
                    $tmpHtaccess = '';
                    foreach ($checkoutPath as $key => $path) {
                        $tmpHtml = '';
                        foreach ($content as $data) {
                            if (false !== strpos($data, "^$key$") && false !== strpos($data, 'checkout/pay/'.$path)) {
                                $tmpHtml = '';
                                break;
                            } else {
                                $tmpHtml = "$key";
                            }
                        }
                        $tmpHtaccess .= $tmpHtml . ',';
                    }
                    $tmpHtaccess = str_replace(',','<br>',trim($tmpHtaccess, ','));
                    if (!empty($tmpHtaccess)) $htaccessHtml = '<td class="red-txt">' . $tmpHtaccess . '<br>配置异常</td>';
                }
            }

        }
        $extensionHtml = '';
        foreach ($extensions as $extension)
        {
            $loadedHtml = extension_loaded($extension) ? '<td>是</td>' : '<td class="red-txt">否</td>';
            $extensionHtml .= '<tr><td>'.strtoupper($extension).'</td>'.$loadedHtml. '</tr>';

        }

        echo <<<EOF

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>配置信息</title>
    <style>
        table {
            width: 50%;
            border-collapse: collapse;
            margin: 20px auto;
        }

        th, td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        table tr:nth-child(odd) {
            background-color:#e6e6e6;
        }
        table tr td:nth-child(1){
            width: 50%;
        }
        .red-txt{
            color: red;
        }
    </style>
</head>
<body>

<table>
    <tr>
        <th>配置名称</th>
        <th>配置值</th>
    </tr>
   
    <tr>
        <td>PHP版本</td>
        <td>$phpversion</td>
    </tr>
    <tr>
        <td>ENV可读写</td>
        $envHtml
    </tr>
    <tr>
        <td>FILE目录可读写</td>
        $fileDirHtml
    </tr>
    <tr>
        <td>HTACCESS状态</td>
        $htaccessHtml
    </tr>
</table>

<br>

<table>
    <tr>
        <th>扩展名称</th>
        <th>是否启用</th>
    </tr>
    $extensionHtml
</table>
</body>
</html>
EOF;
    }

}