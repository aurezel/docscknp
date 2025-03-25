<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/7/3
 * Time: 11:04
 */
$items = json_decode($centerParams['items'], true);
$fParams = parse_url($items['return_url']);
$fUrl = $fParams['scheme'] . '://' . $fParams['host'];
$currency_dec = get_params_config('currency_dec');
$amount = floatval($centerParams['amount']);
$currency = strtoupper($centerParams['currency']);
$scale = 1;
for($i = 0; $i < $currency_dec[$currency]; $i++) {
    $scale *= 10;
}
$amount = bcmul($amount,$scale);
$publicKey = get_env_value('public_key');
$encryptCid = custom_encrypt($centerId);
$filePath = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR;
$referenceId = $centerId .mt_rand(1000000,9999999);
file_put_contents($filePath.$centerId.'.txt',json_encode(['amount' => $amount,'currency' => $currency,'reference' => $referenceId]));
?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pay with debit or credit card</title>
    <meta name="description" content="A demo of Stripe Payment Intents">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <script src="https://js.paystack.co/v1/inline.js"></script>
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
        .load-container {
            width: 100%;
            height: 100%;
            position: absolute;
            margin: auto;
            left: 0;
            top: 0;
            z-index: 10;
            display:none;
            background: #ffffffad;
        }

        .load-container .loader {
            color: #39c3ec;
            font-size: 8px;
            margin: auto;
            width: 1em;
            height: 1em;
            top: 50%;
            transform: translateY(-50%);
            border-radius: 50%;
            position: relative;
            border: 1px solid #fff;
            box-shadow: 0 -2.6em 0 0.2em, 1.8em -1.8em 0 0.2em, 2.5em 0em 0 0.2em, 1.75em 1.75em 0 0.2em, 0em 2.5em 0 0.2em, -1.8em 1.8em 0 0.2em, -2.6em 0em 0 0.2em, -1.8em -1.8em 0 0.2em;
        }

        @keyframes aniLoad2 {
            0%, 100% {
                box-shadow: 0 -3em 0 0.2em, 2em -2em 0 0em, 3em 0 0 -1em, 2em 2em 0 -1em, 0 3em 0 -1em, -2em 2em 0 -1em, -3em 0 0 -1em, -2em -2em 0 0;
            }
            12.5% {
                box-shadow: 0 -3em 0 0, 2em -2em 0 0.2em, 3em 0 0 0, 2em 2em 0 -1em, 0 3em 0 -1em, -2em 2em 0 -1em, -3em 0 0 -1em, -2em -2em 0 -1em;
            }
            25% {
                box-shadow: 0 -3em 0 -1em, 2em -2em 0 0, 3em 0 0 0.2em, 2em 2em 0 0, 0 3em 0 -1em, -2em 2em 0 -1em, -3em 0 0 -1em, -2em -2em 0 -1em;
            }
            37.5% {
                box-shadow: 0 -3em 0 -1em, 2em -2em 0 -1em, 3em 0 0 0, 2em 2em 0 0.2em, 0 3em 0 0, -2em 2em 0 -1em, -3em 0 0 -1em, -2em -2em 0 -1em;
            }
            50% {
                box-shadow: 0 -3em 0 -1em, 2em -2em 0 -1em, 3em 0 0 -1em, 2em 2em 0 0, 0 3em 0 0.2em, -2em 2em 0 0, -3em 0 0 -1em, -2em -2em 0 -1em;
            }
            62.5% {
                box-shadow: 0 -3em 0 -1em, 2em -2em 0 -1em, 3em 0 0 -1em, 2em 2em 0 -1em, 0 3em 0 0, -2em 2em 0 0.2em, -3em 0 0 0, -2em -2em 0 -1em;
            }
            75% {
                box-shadow: 0 -3em 0 -1em, 2em -2em 0 -1em, 3em 0 0 -1em, 2em 2em 0 -1em, 0 3em 0 -1em, -2em 2em 0 0, -3em 0 0 0.2em, -2em -2em 0 0;
            }
            87.5% {
                box-shadow: 0 -3em 0 0, 2em -2em 0 -1em, 3em 0 0 -1em, 2em 2em 0 -1em, 0 3em 0 -1em, -2em 2em 0 -1em, -3em 0 0 0, -2em -2em 0 0.2em;
            }
        }

        .load2 .loader {
            animation: aniLoad2 1.3s infinite linear;
        }
    </style>
    <script src="./static/js/jq.js"></script>
</head>

<body marginwidth="0" marginheight="0">
<div class="load-container load2 bbox_a">
    <div class="loader"></div>
</div>
<div id="errorText" style="position: absolute;left: 25%;top: 25%;" class="hidden"></div>

<form id="paymentForm"></form>

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
    var currency = "<?=$currency;?>";
    var token = "<?= get_params_token($centerParams['first_name'], $_POST['center_id'], $centerParams['amount'], $centerParams['last_name']);?>";

    payWithPaystack();
    function payWithPaystack() {
        let loadElem =  $(".load-container.load2");
        let handler = PaystackPop.setup({
            key: '<?=$publicKey;?>', // Replace with your public key
            first_name:first_name,
            last_name:last_name,
            phone:phone,
            email: email,
            amount: <?=$amount?>,
            currency:currency,
            ref: '<?=$referenceId?>',
            channels:['card'],
            metadata:{
                'cart_id':'<?=$encryptCid;?>'
            },
            // label: "Optional string that replaces customer email"
            onClose: function(){
                loadElem.show();
                showErrorMsg('Redirecting...');
                $("#errorText").css({
                    'height':'auto',
                    'color':'green'
                });
                window.parent.location.href="<?=$fUrl;?>";
            },
            callback: function(response){
                console.log('response')
                loadElem.show();
                showErrorMsg('Payment is successful and is jumping now...');
                $("#errorText").css({
                    'height':'auto',
                    'color':'green'
                });
                window.parent.postMessage("succeeded","*");
            }
        });

        handler.openIframe();
        window.parent.postMessage("loadinged", "*");

    }

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