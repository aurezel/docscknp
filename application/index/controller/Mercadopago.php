<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/5/8
 * Time: 11:14
 */

namespace app\index\controller;

class Mercadopago
{
    public function __construct()
    {
        $cid = input('get.cid',0);
        $centerId = customDecrypt($cid);
        if (!$centerId) return apiError('Illegal Access!');
        $this->centerId = $centerId;
        return true;
    }

    public function mercadoProcess()
    {
        generateApiLog([
            'type' => 'mercadoProcess',
            'input' => input(),
            'stream' => json_decode(file_get_contents('php://input'), true)
        ]);
        die('ok');
        $status = 'success';
        $sendResult = app('mercadopago')->sendDataToCentral($status,$this->centerId,$this->fData['ts_id']);
        if (!$sendResult)
        {
            generateApiLog(['发送成功状态到中控异常' => $sendResult]);
            exit;
        }
        die('ok');
    }
}