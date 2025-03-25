<?php

namespace app\index\controller;

use app\index\service\BaseService;
use app\index\traits\USeePayTool;

class Useepay
{
    public function redirect()
    {
        generateApiLog([
            'type' => 'USeePay Redirect',
            'data' => input()
        ]);
        $type = input('get.r_type','');
        $cid = input('get.cid',0);
        $threeDSServerTransId = input('get.threeDSServerTransId');
        $resultCode = input('get.resultCode');
        $errorMsg = input('get.errorMsg');
        $centerId = (int) customDecrypt($cid);
        if (empty($type) || !$centerId || !in_array($type,['s','t'])) die('Illegal Access!');
        if ($type === 't')
        {
            if (empty($threeDSServerTransId)) die('Illegal Access!');
            return $this->threeDSMethodCompletionMethod($threeDSServerTransId,'Y',true);
        }else{
            if (empty($resultCode)) die('Illegal Access!');
            if ($resultCode == 'succeed')
            {
                echo <<<EOF
<body><div style="text-align: center;color: green;">Payment successful! You will be redirected to the confirmation page momentarily.</div></body>
<script>
    window.parent.parent.postMessage("succeeded", "*");
</script>
EOF;
            }else{
                echo <<<EOF
<body><div style="text-align: center;color: red;">$errorMsg,Jumping to homepage now...</div></body>
<script>
setTimeout(function() {
     window.parent.parent.postMessage("risky", "*");
}, 3000);
</script>
EOF;

            }
           die();
        }
    }
    public function webhook()
    {
        generateApiLog([
            'type' => 'USeePayWebhook',
            'input' => input(),
        ]);
        try{
            $postData = input();
            if (!isset($postData['echoParam'],$postData['reference'],$postData['merchantNo'],$postData['transactionType'],$postData['resultCode'])) die('Params Not Found!');
            $centerId = intval(customDecrypt($postData['echoParam']));
            if(empty($centerId) || env('stripe.public_key') !== $postData['merchantNo'] || 'pay' !== $postData['transactionType']) die("Illegal Access");
            $notifyType = $postData['resultCode'];
            $transactionId = $postData['reference'];
            if ($notifyType == 'succeed')
            {
                $status = 'success';
                $failedMsg = '';
            }elseif ($notifyType == 'failed')
            {
                $status = 'failed';
                $failedMsg = $postData['errorMsg'] ?? '';
            }else{
                return json(['msg' => 'Waiting for deal...']);
            }
            app('useepay')->sendDataToCentral($status,$centerId,$transactionId,$failedMsg);
        }catch (\Exception $e)
        {
            generateApiLog('USeePay Webhook 接口异常:'.$e->getMessage());
        }
        return json(['msg' => 'ok']);
    }

    public function threeDSMethodCompletionMethod($threeDSServerTransId = 0,$threeDSCompleted = 0,$isInternalCalled = false)
    {
        try{
            if (!$isInternalCalled)
            {
                generateApiLog([
                    'type' => 'USeePay threeDSMethodCompletionMethod',
                    'data' => input()
                ]);
                $threeDSServerTransId =  input('post.threeDSServerTransId');
                $threeDSCompleted = input('post.threeDSCompleted');
            }
            if (empty($threeDSServerTransId) || !in_array($threeDSCompleted,['Y','N'],true)) return apiError();
            $gatewayBaseUrl = env('local_env') ? 'https://pay-gateway1.uat.useepay.com' : 'https://pay-gateway.useepay.com';
            $requestData = array(
                'threeDSServerTransId' => $threeDSServerTransId,
                'threeDSCompleted' => $threeDSCompleted,
                'merchantNo' => env('stripe.public_key'),
                'transactionType' => 'threeDSMethodCompletion',
                'signType' => 'MD5',
                'version' => '1.0'
            );
            $requestData['sign'] = USeePayTool::md5Sign($requestData,env('stripe.private_key'));
            $responseData = USeePayTool::submitWithReturn($gatewayBaseUrl . '/api',$requestData);
            $threeDForm = app('useepay_api')->generateThreeDSForm($responseData);
            return apiSuccess([
                'respResultCode' => $responseData['resultCode'],
                'respErrorCode' => $responseData['errorCode'] ?? 0,
                'errorMsg' => $responseData['errorMsg'],
                'threeDForm' => $threeDForm
            ]);
        }catch (\Exception $e)
        {
            generateApiLog('USeePay 3DS验证完成接口异常:'.$e->getMessage());
        }
        return apiError();
    }
    public function confirmResult()
    {
        try{
            generateApiLog([
                'type' => 'USeePay Confirm Result',
                'data' => input()
            ]);
            $params = request()->post();
            $flag = (new BaseService())->checkToken($params);
            if (!$flag) return apiError();
            // 失败状态直接处理，最终状态通过Webhook处理
            if (!isset($params['errorCode'],$params['reference'],$params['errorMsg']))
            {
                generateApiLog('USeepay Confirm 回调缺少参数');
                return apiError('Illegal Params!');
            }
            if ($params['errorCode'] === '0000') return apiSuccess();
            $errorMsg = $params['errorMsg'];
            app('useepay')->sendDataToCentral('failed',$params['center_id'],$params['reference'],$errorMsg);
            return apiError($errorMsg);
        }catch (\Exception $exception)
        {
            generateApiLog('USeePay Confirm Result接口异常:'.$exception->getMessage());
        }
        return apiError();
    }
}