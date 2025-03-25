<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/8/24
 * Time: 9:52
 */

namespace app\index\controller;

use think\Controller;

class Cycopay extends Controller
{
    public function cyWebhook()
    {
        generateApiLog([
            'type' => 'webhook',
            'input' => input(),
        ]);
        $postData = input('post.');
        $cid = input('get.cid',0);
        $centerId = customDecrypt($cid);
        if (!$centerId) die('Illegal Cid');
        if (!isset($postData['apiKey']) || !isset($postData['status']) || !isset($postData['paymentID']) || $postData['apiKey'] !== env('stripe.private_key')) die('Illegal Access!');
        $status = $postData['status'] === 'completed' ? 'success' : 'failed';
        $sendResult = app('cycopay')->sendDataToCentral($status,$centerId,$postData['paymentID']);
        if (!$sendResult)
        {
            generateApiLog(['发送成功状态到中控异常' => $sendResult]);
            return apiError();
        }
        return apiSuccess('ok');
    }

    public function cySuccess()
    {
        generateApiLog([
            'type' => 'success',
            'input' => input()
        ]);
        $cid = input('get.cid',0);
        $centerId = customDecrypt($cid);
        if (!$centerId) return apiError('Illegal Access!');
        $fileName = app()->getRootPath() . 'file'.DIRECTORY_SEPARATOR . $centerId . '.txt';
        if (!file_exists($fileName)) return apiError('Data Not Exist');
        $data = json_decode(file_get_contents($fileName),true);
        if (!isset($data['s_url'])) return apiError('Params Not exist');
        @unlink($fileName);
        header("Referrer-Policy: no-referrer");
        header('location:'.$data['s_url']);
        exit;
    }

    public function cyFailure()
    {
        generateApiLog([
            'type' => 'failed',
            'input' => input(),
        ]);
        $cid = input('get.cid',0);
        $centerId = customDecrypt($cid);
        if (!$centerId) return apiError('Illegal Access');
        $fileName = app()->getRootPath() . 'file'.DIRECTORY_SEPARATOR . $centerId . '.txt';
        if (!file_exists($fileName)) return apiError('Data Not Exist');
        $data = json_decode(file_get_contents($fileName),true);
        if (!isset($data['f_url'])) return apiError('Params Not exist');
        $sendResult = app('cycopay')->sendDataToCentral('failed',$centerId);
        if (!$sendResult)
        {
            generateApiLog(['发送失败状态到中控异常' => $sendResult]);
            return apiError();
        }
        @unlink($fileName);
        header("Referrer-Policy: no-referrer");
        header("Location:".$data['f_url']);
        die;
    }
}