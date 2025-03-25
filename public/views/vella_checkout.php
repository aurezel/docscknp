<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/6/27
 * Time: 11:20
 */
$firstname = $centerParams['first_name'];
$lastname = $centerParams['last_name'];
$amount = $centerParams['amount'];
$email = $centerParams['email'];
$country = $centerParams['country'];
$city = $centerParams['city'];
$zipCode = $centerParams['zip_code'];
$address = $centerParams['address'];
$abbState = $state = $centerParams['state'];
$currency = $centerParams['currency'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pay with debit or credit card</title>
    <meta name="description" content="A demo of Stripe Payment Intents">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="./static/css/normalize.css">
    <link rel="stylesheet" href="./static/css/global.css">
    <link rel="stylesheet" href="./static/css/lyq-stripe.css">
    <script src="https://checkout.vella.finance/widget/sdk.js"  ></script>
    <script src="./static/js/jq.js"></script>
    <style>
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
        @media(max-width:768px){
            .pp{
                margin-top: 50%;transform: translateY(-30%);
            }
        }

        .load2 .loader {
            animation: aniLoad2 1.3s infinite linear;
        }
    </style>
</head>
<body>
<div class="sr-root">
    <div class="sr-main" id="stripe_main">
        <div class="payment-info pp" >
            <div style="display: block;text-align:center;color: red;height: 0;line-height:24px;transition: all 0.3s;overflow: hidden;" id="errorText"></div>
            <div class="load-container load2 bbox_a">
                <div class="loader"></div>
            </div>
        </div>


    </div>
</div>

<script>

    var errorFlag = false;
    $.ajax({
        type: "post",
        url: '/checkout/pay/createOrder',
        data: {
            amount: <?=$amount;?>,
            order_no: "<?=$centerParams['order_no'];?>",
            currency: "<?=$centerParams['currency'];?>",
            center_id: "<?=$centerId;?>",
            email: "<?=$email;?>",
            phone: "<?=$centerParams['telephone'];?>",
            country: "<?=$country?>",
            currency_code: "<?=$currency;?>",
            city: "<?=$city;?>",
            state: "<?=$state;?>",
            address1: "<?=$address;?>",
            address2: "",
            zip: "<?=$zipCode;?>",
            first_name: "<?=$firstname;?>",
            last_name: "<?=$lastname;?>",
            token: "<?= get_params_token($firstname, $centerId, $amount, $lastname);?>"
        },
        dataType: "json",
        success: function (data) {

            if (data.errcode === 0) {
                window.parent.postMessage("loadinged", "*");

                let result = data.data;
                if (result.amount > 0)
                {
                    initiatePayment(result.key,result.amount,result.currency,result.tags,result.reference_id);
                }
                return false;
            }
            sendFailedCount();
            showErrorMsg(data.errmsg);
            return false;
        },
        error: function (data) {
            console.log('error',data);
            sendFailedCount();
            return false;
        }
    });

    function initiatePayment(public_key,amount,currency,tags,reference) {
        var key = public_key;
        const config = {
            email: "<?=$email;?>",
            name: "<?=$firstname . ' '.$lastname;?>",
            amount: amount,
            currency: currency,
            merchant_id: tags,
            reference: reference,
            custom_meta: [],
            source: '',
        };
        const vellaSDK = VellaCheckoutSDK.init(key, config);

        vellaSDK.onSuccess(response => {
            let payResult = response.data;
            if (undefined === payResult)
            {
                payResult = response.transaction_data;
            }
            console.log('response',response);
            console.log(payResult);
            if (payResult.status === 'Completed' && payResult.reference === reference)
            {
                $.ajax({
                    type: "post",
                    url: '/checkout/pay/vellaVerify',
                    dataType: "json",
                    data: {
                        reference_id:reference,
                        key:key,
                        tags: tags
                    },
                    success: function (data) {
                        console.log(data)
                        if (data.errcode === 1) {
                            window.parent.postMessage("risky", "*");
                            return true;
                        }
                        window.parent.postMessage("succeeded",'*');
                    },
                    error: function (data) {
                        console.log('error',data);
                        window.parent.postMessage("risky", "*");
                        return false;
                    }
                });
                return true;
            }
            showErrorMsg('Paid Failed');
            return  false;
        })
        vellaSDK.onError(error => {
            console.log("error", error)
            errorFlag = true;
            showErrorMsg(error.message)
            sendFailedCount();
            return false;
        });
        vellaSDK.onClose(() => {
            if(errorFlag)
            {
                var num = 0;
                var timer = setInterval(function(){
                    num ++;
                    if (num > 2)
                    {
                        clearInterval(timer);
                        closePopup();
                    }
                }, 1000);
            }else{
                closePopup();
            }
        });
    }

    function verifyResult()
    {
        $.ajax({
            type: "post",
            url: '/checkout/pay/vellaVerify',
            dataType: "json",
            data: {center_id: <?=$_POST['center_id'] ?? 0;?>},
            success: function (data) {
                if (data.errcode === 1) {
                    console.log('set error failed!');
                }
                window.parent.postMessage("risky", "*");
            },
            error: function (data) {
                console.log('set error failed');
                return false;
            }
        });
    }

    var error_count = 0;
    function sendFailedCount() {
        error_count++;
        if (error_count >= 2) {
            $.ajax({
                type: "post",
                url: '/checkout/pay/errorCount',
                dataType: "json",
                data: {center_id: <?=$_POST['center_id'] ?? 0;?>},
                success: function (data) {
                    if (data.errcode === 1) {
                        console.log('set error failed!');
                    }
                    window.parent.postMessage("risky", "*");
                },
                error: function (data) {
                    console.log('set error failed');
                    return false;
                }
            });
        }
    }

    function showErrorMsg(msg = '', is_empty = false) {
        let height = is_empty ? '0px' : 'auto';
        $("#errorText").text(msg).css("height", height);
    }



    function closePopup() {
        showErrorMsg('Payment closed,jumping now...');
        window.parent.postMessage('risky','*')
    }
</script>



</body>
</html>

