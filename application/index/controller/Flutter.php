<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/8/24
 * Time: 9:53
 */

namespace app\index\controller;

use think\Controller;

class Flutter extends Controller
{
    public function flutterRedirect()
    {
        try{
            $params = input('response','');
            $responseData = json_decode($params,true);
            if (empty($params) || empty($responseData))
            {
                generateApiLog('FlutterRedirect 无数据');
                return apiError();
            }

            if (!isset($responseData['txRef'],$responseData['status'],$responseData['redirectUrl']))
                return apiError();

            parse_str(parse_url($responseData['redirectUrl'])['query'],$query);
            $centerId = customDecrypt($query['center_id'] ?? '');
            if (empty($centerId) || !$centerId)
            {
                generateApiLog("Flutter没有center_id");
                return apiError();
            }

            $flutterService = app('flutter',[$centerId,$responseData['txRef'],$responseData['message'] ?? '']);
            $status = $responseData['status'] === 'successful' ? 'success' : 'error';
            $sendResult = $flutterService->sendDataToCentral($status);
            if (!isset($sendResult['success_risky'])) return apiError();
            if ($sendResult['success_risky'])
            {
                echo "<script>
window.parent.parent.postMessage('success_risky', '*');
window.parent.close();
</script>";die();
            }
            echo "<script>
window.parent.parent.postMessage('succeeded', '*');
window.parent.close();
</script>";die();
        }catch (\Exception $e)
        {
            generateApiLog("Flutter回调接口异常：" .$e->getMessage()."\r\n".$e->getLine());
        }
        return apiError();
    }
}