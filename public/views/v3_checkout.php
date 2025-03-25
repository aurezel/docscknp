<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/7/3
 * Time: 11:04
 */

$protocol = "http://";
if((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == "https")) $protocol = "https://";

$returnUrl =  $protocol .$_SERVER['HTTP_HOST'] . "/checkout/pay/stSuccess?cid=".custom_encrypt($centerId);
$items = json_decode($centerParams['items'], true);
$filePath = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'file' . DIRECTORY_SEPARATOR;
$sUrl = $items['return_url'];

$fParams = parse_url($sUrl);
$currency_dec = get_params_config('currency_dec');
for($i = 0; $i < $currency_dec[strtoupper($centerParams['currency'])]; $i++) {
    $centerParams['amount'] *= 10;
}
$centerParams['amount'] = intval($centerParams['amount']);
file_put_contents($filePath . $centerId . '.txt', json_encode(
    [
        'f_url' => $fParams['scheme'] . '://' . $fParams['host'],
        's_url' => $sUrl,
        'center_id' => $centerId
    ]
));

?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pay with debit or credit card</title>
    <meta name="description" content="A demo of Stripe Payment Intents">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./static/css/normalize.css">
    <link rel="stylesheet" href="./static/css/global.css">
    <link rel="stylesheet" href="./static/css/lyq-stripe.css">
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

        #st-title-img{
            max-width:93.8%;
        }
        @media screen and (max-width: 760px) {
            #st-title-img{
                max-width:92.5%;
            }
        }

    </style>
    <script src="https://js.stripe.com/v3/"></script>
    <script src="./static/js/jq.js"></script>
</head>

<body marginwidth="0" marginheight="0">
<form id="payment-form" style="margin: 5% auto;max-width: 460px;">
    <div style='display: flex;align-items: center;justify-content: center;padding: 10px 0px 20px;'>
        <img src="./static/images/stripe.png" id="st-title-img" style="display: block;"/>
    </div>
    <div id="payment-element" style="padding:0 20px"></div>
    <button id="submits">
        <div class="spinner hidden" id="spinner"></div>
        <span id="button-text" >Pay</span>
    </button>
    <div id="errorText" class="hidden" style="max-width:100%"></div>
</form>

<div id="messages" role="alert" style="display: none;"></div>

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

    // This is a public sample test API key.
    // Don’t submit any personally identifiable information in requests made with this key.
    // Sign in to see your own test API key embedded in code samples.
    const stripe = Stripe("<?= get_env_value('public_key');?>");
    let elements;
    initialize();
    document.querySelector("#payment-form").addEventListener("submit", handleSubmit);
    // Fetches a payment intent and captures the client secret
    async function initialize() {
        window.parent.postMessage("loadinged", "*");
        const options = {
            paymentMethodTypes:['card'],
            mode: 'payment',
            currency: currency.toLowerCase(),
            amount: parseInt(amount),
        };
        elements = stripe.elements(options);
        const paymentElement = elements.create("payment",{
            fields: {
                billingDetails: {
                    address: 'never',
                }
            },
        });
        paymentElement.mount("#payment-element");
        changeLoadingState(false);
    }

    async function completeSubmit(){
        showErrorMsg();
        const { clientSecret,errcode,errmsg } = await fetch("/checkout/pay/createOrder", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({
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
            })
        }).then((r) => r.json())

        if(errcode){
            showErrorMsg(errmsg);
            sendFailedCount(center_id);
            changeLoadingState(false);
            return false;
        }
        const { error } = await stripe.confirmPayment({
            elements,
            clientSecret,
            confirmParams: {
                return_url: "<?=$returnUrl?>",
                payment_method_data : {
                    billing_details : {
                        address : {
                            "city": address_city,
                            "country": address_country,
                            "line1": address_line1,
                            "line2": address_line2,
                            "postal_code": address_zip,
                            "state": address_state
                        },
                        name: name,
                        email:email,
                        phone:phone
                    }
                }
            },
        });

        if (error.type === "card_error" || error.type === "validation_error") {
            $.get("<?=$returnUrl?>&payment_intent="+error.payment_intent.id, function(result){
                showErrorMsg(error.message);
                changeLoadingState(false);
            });
            sendFailedCount(center_id);
        } else {
            changeLoadingState(false);
            showErrorMsg("An unexpected error occurred.");
        }
    }

    async function handleSubmit(e)
    {
        changeLoadingState(true);
        e.preventDefault();
        // Trigger form validation and wallet collection
        const {error: submitError} = await elements.submit();
        if (submitError) {
            changeLoadingState(false);
            sendFailedCount(center_id);
            return;
        }
        window.postMessage({type:"zzpay",code:200,data:""},'*');
    }

    window.addEventListener('message',function(event, s){
        if(event.data && event.data.type && event.data.type ==='zzpay'){
            if(event.data.code === 200){
                completeSubmit()
                return
            }

            if(event.data.code === 400){
                changeLoadingState(false);
            }
        }
    })

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

    function changeLoadingState(isLoading) {
        if (isLoading) {
            document.querySelector("button").disabled = true;
            document.querySelector("#spinner").classList.remove("hidden");
            document.querySelector("#button-text").classList.add("hidden");
        } else {
            document.querySelector("button").disabled = false;
            document.querySelector("#spinner").classList.add("hidden");
            document.querySelector("#button-text").classList.remove("hidden");
        }
    }
</script>
</body>
</html>