<?php
namespace app\index\controller;

use think\Controller;
use app\index\service\PlaceAirwallexOrderService;

class Airwallex extends Controller
{
    public function axSuccess()
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
        $sUrl = file_get_contents($fileName);
        if (!isset($data['s_url'])) return apiError('Params Not exist');
        @unlink($fileName);
        header("Referrer-Policy: no-referrer");
        header('location:'.$sUrl);
        exit;
    }

    public function axError()
    {
        $center_id = input('post.center_id/d',0);
        $message = input('post.message/s','');
        if (empty($center_id)) return apiError('Illegal Access');
        $res = app('airwallex')->sendDataToCentral('failed', $center_id, 0,$message);
        return $res ? apiSuccess() : apiError();
    }

    public function axConfirmation() {
        $intent_id = input('intent_id');
        $cid = input('order_id',0);
        $center_id = customDecrypt($cid);
        if(empty($center_id) || empty($intent_id)) {
            die("Illegal Access");
        }
        //get PaymentIntent
        $intent = app('airwallex')->getPaymentIntent($intent_id);
        if(empty($intent)) {
            generateApiLog([
                'type' => 'error',
                'input' => input()
            ]);
            die("no such intent");
        }
        if (!in_array($intent['status'], ["SUCCEEDED", "REQUIRES_CAPTURE"], true)) {
            generateApiLog([
                'type' => 'error',
                'intent' => json_encode($intent)
            ]);
            die("Invalid status");
        }
        if($intent['status'] == "SUCCEEDED") {
            app('airwallex')->sendDataToCentral('success', $center_id, $intent_id);
            echo '<script>window.parent.postMessage("succeeded","*");</script>';
        } else {
            //handle REQUIRES_CAPTURE state
            $intentObj = app('airwallex')->capturePaymentIntent($intent['id'], $intent['amount']);
            if($intentObj['status'] != "SUCCEEDED") {
                generateApiLog([
                    'type' => 'error',
                    'capture' => json_encode($intentObj)
                ]);
                echo "Capture Error";
            }
            app('airwallex')->sendDataToCentral('success', $center_id, $intent_id);
            echo '<script>window.parent.postMessage("succeeded","*");</script>';
        }
    }
}