<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/1/5
 * Time: 8:44
 */

namespace app\index\controller;

use think\App;
use think\Controller;

class Zenpay
{
    private $fData = [],$centerId;
    public function __construct()
    {
        $cid = input('get.cid',0);
        $centerId = customDecrypt($cid);
        if (!$centerId) return apiError('Illegal Access!');
        $fileName = app()->getRootPath() . 'file'.DIRECTORY_SEPARATOR . $centerId . '.txt';
        if (!file_exists($fileName)) die('Data Not Exist');
        $data = file_get_contents($fileName);
        $this->fData = json_decode($data,true);
        if (!isset($this->fData['s_url'])) die('Params Not exist');
        $this->centerId = $centerId;
        @unlink($fileName);
        return true;
    }

    public function zenSuccess()
    {
        generateApiLog([
            'type' => 'failed',
            'input' => input(),
        ]);
        if (!isset($this->fData['s_url'])) die('Params Not exist');
        header("Referrer-Policy: no-referrer");
        header("Location:".$this->fData['s_url']);
        exit;
    }

    public function zenProcess()
    {
        generateApiLog([
            'type' => 'zenProcess',
            'input' => input(),
        ]);
        $status = 'success';
        if (!isset($this->fData['ts_id'])) return apiError('Params Not exist');
        $sendResult = app('zen')->sendDataToCentral($status,$this->centerId,$this->fData['ts_id']);
        if (!$sendResult)
        {
            generateApiLog(['发送成功状态到中控异常' => $sendResult]);
            exit;
        }
        die('ok');
    }

    public function zenCancel()
    {
        generateApiLog([
            'type' => 'cancel',
            'input' => input(),
        ]);
        if (!isset($this->fData['f_url'])) return apiError('Params Not exist');
        header("Referrer-Policy: no-referrer");
        header("Location:".$this->fData['f_url']);
        exit;
    }
}