<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/7/3
 * Time: 11:04
 */
$items = json_decode($centerParams['items'], true);
$filePath = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR;
$sUrl = $items['return_url'];
$fParams = parse_url($sUrl);
file_put_contents($filePath . $centerId . '.txt', json_encode(
    [
        'f_url' => $fParams['scheme'] . '://' . $fParams['host'],
        's_url' => $sUrl
    ]
));

?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pay with debit or credit card</title>
    <meta name="description" content="A demo of Stripe Payment Intents">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <style>
        #errorText{
            display: flex;
            max-width: 1000px;
            justify-content: center;
            color: red;
        }

        .hidden{
            display: none;
        }
    </style>
    <script src="./static/js/jq.js"></script>
</head>

<body marginwidth="0" marginheight="0">
<div id="errorText" class="hidden"></div>

<script>
    var center_id = "<?=$_POST['center_id'] ?? 0;?>";
    var address_line1 = "<?=$centerParams['address'];?>";
    var address_line2 = '';
    var address_city = "<?=$centerParams['city'];?>";
    var address_state = "<?=$centerParams['state'];?>";
    var address_zip = "<?=$centerParams['zip_code'];?>";
    var address_country = "<?=$centerParams['country'];?>";
    var email = "<?=$centerParams['email'];?>";
    var phone = "<?=$centerParams['telephone'];?>";
    var name = "<?=$centerParams['first_name'] . ' ' . $centerParams['last_name'];?>";
    var first_name = "<?=$centerParams['first_name'];?>";
    var last_name = "<?=$centerParams['last_name'];?>";
    var amount = "<?=$centerParams['amount'];?>";
    var currency = "<?=$centerParams['currency'];?>";
    var token = "<?= get_params_token($centerParams['first_name'], $_POST['center_id'], $centerParams['amount'], $centerParams['last_name']);?>";

    $.ajax({
        url: '/checkout/pay/createOrder',
        method: 'POST',
        dataType: 'json',
        data: {
            address1: address_line1,
            address2: address_line2,
            city: address_city,
            state: address_state,
            zip_code: address_zip,
            country: address_country,
            name: name,
            phone:phone,
            email:email,
            first_name: first_name,
            last_name: last_name,
            amount: amount,
            currency: currency,
            center_id: center_id,
            token: token
        },

        success(res) {
            if (res.errcode === 0) {
                showErrorMsg('Redirecting to payment page...');
                $("#errorText").css({
                    'height':'auto',
                    'color':'green'
                });
                window.parent.postMessage(res.data.url, "*");
                return false;
            } else {
                // error count
                sendFailedCount();
                showErrorMsg(res.errmsg);
                return false;
            }
        },
        error(res) {
            sendFailedCount();
            showErrorMsg(res.errmsg);
            return false;
        }
    });

    var error_count = 0;

    function sendFailedCount(center_id) {
        error_count++;
        if (error_count >= 2) {
            $.ajax({
                type: "post",
                url: '/checkout/pay/errorCount',
                dataType: "json",
                data: {center_id: center_id},
                success: function (data) {
                    if (data.errcode === 1) {
                        console.log('set error failed!');
                    }
                    window.parent.postMessage("risky", "*");
                },
                error: function (data) {
                    console.log('set error failed');
                }
            });
        }
    }

    function showErrorMsg(msg = '', is_empty = false) {
        let height = is_empty ? '0px' : 'auto';
        $("#errorText").text(msg).css("height", height).show();
    }
</script>
</body>
</html>