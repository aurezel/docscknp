<?php

namespace app\index\controller;
class Tazapay
{
    private $fData = [];
    public function payReturn()
    {
        generateApiLog([
            'type' => 'success',
            'input' => input(),
        ]);
        $isIllegal = $this->getFileData();
        if (!$isIllegal) return apiError();
        $tried = 0;
        $max_tried_cnt = 1;

        if (!isset($this->fData['is_view']))
        {
            $this->fData['is_view'] = true;
            $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $this->fData['center_id'] . '.txt';
            file_put_contents($fileName,json_encode($this->fData));
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
        }else{
            $orderData = $this->fData['html_data'];
            $orderInfo= 'Order No: <b>'.$orderData['order_no'].'</b><br>Amount:<b>'.$orderData['amount'].' '.$orderData['currency'].'</b>';
            $shippingInfo = $billingInfo = '<b>'.$orderData['first_name'] . ' '. $orderData['last_name'] .'<br>'.
                $orderData['address'].'<br>'.$orderData['telephone'].'<br>'.
                $orderData['city'] .','.$orderData['state'].' '.$orderData['zip_code'].'<br>'.
                $orderData['country'].'</b>';
            $host = request()->domain();
            $domain = '<a href="'.$host.'">'.$host.'</a>';
            exit(sprintf(config('tazapay.success'),$orderInfo,$shippingInfo,$billingInfo,$domain));
        }

    }

    public function payCancel()
    {
        generateApiLog([
            'type' => 'cancel',
            'input' => input(),
        ]);
        $isIllegal = $this->getFileData();
        if (!$isIllegal) return apiError();
        if (!isset($this->fData['is_view']))
        {
            $this->fData['is_view'] = true;
            $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $this->fData['center_id'] . '.txt';
            file_put_contents($fileName,json_encode($this->fData));
            $url = $this->fData['f_url'];
        }else{
            $url = 'https://' . $this->fData['html_data']['domain'];
        }
        header("Referrer-Policy: no-referrer");
        header("Location:" . $url);
        exit;
    }

    public function webhook()
    {
        http_response_code(200);
        $response = json_decode(file_get_contents("php://input"), true);
        generateApiLog([
            'tazaWebhook' => $response,
        ]);
        if (is_null($response) || !isset($response['type'])) {
            generateApiLog('Webhook数据异常');
            exit;
        }
        $responseData = $response['data'];
        $referenceId = $responseData['reference_id'];
        $centerId = customDecrypt($referenceId);
        if (!$centerId) {
            generateApiLog('Webhook Center Id异常');
            exit();
        }
        $reason = '';
        // 成功状态
        if ($response['type'] == 'checkout.paid' && $responseData['payment_status'] == 'paid') {
            $status = 'success';
        } elseif ($responseData['payment_status'] == 'failed') {
            $status = 'failed';
            $reason = 'failed'; // TODO::detail of response struct
        }

        if (isset($status)) {
            $transactionId = $responseData['id'];
            $result = app('tazapay')->sendDataToCentral($status, $centerId, $transactionId, $reason);
            if (!$result) {
                generateApiLog('发送中控失败:' . json_encode([$status, $centerId, $transactionId, $reason]));
            }elseif($status == 'success')
            {
                $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $centerId . '.txt';
                $fileData = json_decode(file_get_contents($fileName),true);
                if(!empty($result['redirect_url'])) {
                    $fileData['f_url'] = $result['redirect_url'];
                }
                $fileData['risky'] = $result['success_risky'];
                file_put_contents($fileName,json_encode($fileData));
            }
        }
        exit('ok');
    }
    private function getFileData()
    {
        $cid = input('get.cid', 0);
        $centerId = customDecrypt($cid);
        if (!$centerId) {
            return false;
        }
        $this->fData['center_id'] = $centerId;
        $fileName = app()->getRootPath() . 'file' . DIRECTORY_SEPARATOR . $centerId . '.txt';
        if (!file_exists($fileName)) die($centerId . '-Data Not Exist');
        $data = file_get_contents($fileName);
        $this->fData = json_decode($data, true);
        if (!isset($this->fData['s_url'], $this->fData['f_url'])) die('Params Not exist');
        return true;
    }
}