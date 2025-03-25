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
$siteUrl = 'https://'.$centerParams['domain'];

$isIframePay = intval(get_env_value('merchant_token'));

$fileData = [
    'f_url' => $fParams['scheme'] . '://' . $fParams['host'],
    's_url' => $sUrl,
    'html_data' => $centerParams,
    'center_id' => $centerId
];
if ($isIframePay) $fileData['is_view'] = true;
file_put_contents($filePath . $centerId . '.txt', json_encode($fileData));
$payInScript = get_env_value('local_env') ? 'https://js-sandbox.tazapay.com/v2.0-sandbox.js' : 'https://js.tazapay.com/v2.0.js';
$publicKey = '';
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
        .my-container{
            width: 100%;
        }
        @media screen and (min-width: 700px) {
            .my-container{
                width: 750px;
            }
        }
    </style>
    <?php
    if ($isIframePay) {?>
        <script src="<?=$payInScript?>"></script>
    <?php } ?>
    <script src="./static/js/jq.js"></script>
</head>

<body marginwidth="0" marginheight="0">
<div id="errorText" class="hidden"></div>
<?php
if ($isIframePay) {?>
    <div class="my-container"  style="margin:  0 auto !important;">
        <div id="tz-checkout"></div>
    </div>
<?php } ?>
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
                window.parent.postMessage("loadinged", "*");
                <?php
                if ($isIframePay)
                {?>
                const options = {
                    clientToken: res.data.param, // Use the token obtained at step2.
                    callbacks: {
                        onPaymentSuccess: (res) => {
                            window.parent.postMessage("succeeded","*");
                        },
                        onPaymentFail: () => {
                            sendFailedCount();
                            showErrorMsg('Pay failed');
                        },
                        onPaymentMethodSelected: () => { console.log ("onPaymentMethodSelected") }, // optional
                        onPaymentCancel: () => {
                            console.log ("onPaymentCancel")
                        }, // optional
                    },
                    style: {
                        "container_zIndex": "1",
                        "container_padding": "0",
                        "modal_padding":"0"
                    }, // optional, for customising your integration,
                    config: {
                        redirectionTarget: "self", // optional -> "self" or "new_window"
                        popup: false, // optional -> true or false // by default iframe will be embedded
                        origins: "<?=$siteUrl?>", // required only, if tazapay iframe embedded site(your site) is loaded inside an another site/iframe(your host site).
                    },
                };
                window.tazapay.checkout(options);
            <?php }else{?>
                window.parent.postMessage(res.data.param, "*");
                <?php }
                ?>
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