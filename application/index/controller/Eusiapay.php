<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/7/3
 * Time: 11:04
 */

namespace app\index\controller;

class Eusiapay
{
    private $fData = [];


    public function eusNotify()
    {
        generateApiLog([
            'type' => 'notify',
            'input' => input(),
        ]);
        $orderInfo = input('post.order_info');

        if (empty($orderInfo)) die('Illegal Access!');
        $params = self::decryptData($orderInfo);
        if (empty($params))
        {
            generateApiLog('Notify解密失败:'.$orderInfo);
            die('Decrypt Failed');
        }
        generateApiLog('Notify数据:'.json_encode($params));
        if (!isset($params['remark'],$params['orderStatus'],$params['tradeNo']))
        {
            die('Illegal Params!');
        }
        $centerId = customDecrypt($params['remark']);
        if (!$centerId) {
            die('Illegal Access!');
        }

        $status = $params['orderStatus'] == 1 ? 'success' : 'failed';
        $msg = '';
        $transactionId = $params['tradeNo'];
        $result = app('eusiapay')->sendDataToCentral($status,$centerId,$transactionId,$msg);
        if (!$result)
        {
            generateApiLog('发送中控失败:'.json_encode([$status,$centerId,$transactionId,$msg]));
            die('Internal Error!');
        }
        $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $centerId . '.txt';
        $fileData = json_decode(file_get_contents($fileName),true);
        if(!empty($result['redirect_url'])) {
            $fileData['f_url'] = $result['redirect_url'];
        }
        $fileData['risky'] = $result['success_risky'];
        file_put_contents($fileName,json_encode($fileData));
        die('OK');
    }
    public function eusReturn()
    {
        generateApiLog([
            'type' => 'return',
            'input' => input(),
        ]);

        $isIllegal = $this->getFileData();
        $orderInfo = input('order_info');
        if (!$isIllegal || empty($orderInfo)) return apiError('Illegal Access!');
        $decryptData = self::decryptData($orderInfo);
        if (empty($decryptData))
        {
            generateApiLog('Return解密失败:'.$orderInfo);
            return apiError('Decrypt Failed!');
        }

        generateApiLog('Return数据:'.json_encode($decryptData));
        if ($decryptData['orderStatus'] == 0)
        {
            header("Referrer-Policy: no-referrer");
            header("Location:" . $this->fData['f_url']);
            exit('ok');
        }

        $tried = 0;
        $max_tried_cnt = 1;
        while ($tried <= $max_tried_cnt) {
            if (isset($this->fData['risky']))
            {
                if(!$this->fData['risky']) {
                    header("Referrer-Policy: no-referrer");
                    header("Location:" . $this->fData['s_url']);
                    exit('ok');
                } else {
                    exit(sprintf(config('risky.html'),$this->fData['f_url']));
                }
            } else {
                sleep(3);
                $this->getFileData();
                $tried ++;
            }
        }
        header("Referrer-Policy: no-referrer");
        header("Location:" . $this->fData['s_url']);
        exit('ok');
    }


    private function getFileData()
    {
        $cid = input('get.cid', 0);
        $centerId = customDecrypt($cid);
        if (!$centerId) {
            return false;
        }
        $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $centerId . '.txt';
        if (!file_exists($fileName)) die($centerId . '-Data Not Exist');
        $data = file_get_contents($fileName);
        $this->fData = json_decode($data, true);
        if (!isset($this->fData['s_url'], $this->fData['f_url'])) die('Params Not exist');
        return true;
    }

    private static function decryptData($data)
    {
        $key = env('stripe.private_key');
        $decodeText = base64_decode($data);
        $jsonObj = openssl_decrypt($decodeText, 'AES-128-ECB', $key.$key);
        return json_decode($jsonObj,true);
    }
}