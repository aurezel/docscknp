<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/3/29
 * Time: 14:28
 */
$centerParams['private_key'] = get_env_value('private_key');
$centerParams['public_key'] = get_env_value('public_key');

$currency_dec = get_params_config('currency_dec');
for($i = 0; $i < $currency_dec[strtoupper($centerParams['currency'])]; $i++) {
    $centerParams['amount'] *= 10;
}
?>
<html xmlns="https://www.w3.org/1999/xhtml" dir="ltr" xml:lang="en" lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Credit Card Payment Gateway</title>
    <meta http-equiv="X-UA-Compatible" content="IE-Edge,chrome">
    <meta name="viewport"
          content="width=device-width,initial-scale=1.0,maximum-scale=1.0, user-scalable=no, minimal-ui">
    <script type="text/javascript" src="./static/js/jq.js"
            charset="UTF-8"></script>
    <title>Credit Card Payment Gateway</title>
    <style>
        .panel {
            padding: 15px 20px;
            display: block;
            max-height:450px;
            width: 280px;
            border-radius: 6px;
            margin: 0 auto;
            position: relative;
        }
        .text-center {
            text-align: center;
            height: 100%;
            display: flex;
            align-items: center;
        }
        .btn {
            border-radius:50px;
            color: #f4f4f4;
            background-image: linear-gradient(to right,#75c2ff, #45a3e0);
            transition: all 0.15s ease-in-out;
            margin: 20px auto 20px;
            display: block;font-weight: 600;
            cursor: pointer;
            padding: 12px 16px;
            font-size:1rem;
            border: 0;width: 100%;
            box-shadow: -2px -2px 5px #fff, 2px 2px 5px #babecc;
            max-width: 300px;
        }
        #pay_button:hover {

            filter: none;
        }
        #pay_button:active {
            box-shadow: inset 1px 1px 2px #babecc, inset -1px -1px 2px #fff;
            filter: none;
            transform: none;
        }
        .button-disabled {
            background:rgb(120,125,128);
            color: #fff;
            display: block;
            width: 100%;
            border: 1px solid rgba(46, 86, 153, 0.0980392);
            border-bottom-color: rgba(46, 86, 153, 0.4);
            border-top: 0;
            border-radius: 4px;
            font-size: 17px;
            text-shadow: rgba(46, 86, 153, 0.298039) 0px -1px 0px;
            line-height: 34px;
            -webkit-font-smoothing: antialiased;
            font-weight: bold;
            margin-top: 20px;
        }
        .btn:hover {
            cursor: pointer;
        }
        .panel .centerImg img{
            width:180px;
            height:auto;
        }
        .panel .panelImg{
            width: 100%;
            margin-bottom:10px;
        }
        .panel .panelImg img{
            width: 100%;
        }
        .panel .loading img{
            width: 4rem;
            height: 4rem;
            margin-top: -95%;
            margin-left: -9%;
            z-index: 1000;
            position: fixed;
            display: none;
        }

        #my-card{
            padding: 30px 20px 0;
            background-image: linear-gradient(to top right,#47a5e2, #b5deff);
            border-radius: 12px;
            position: relative;
        }
        /* #my-card::before{
            content:'';
            background:#eb273d;
            position: absolute;
            width:20px;
            height:20px;
            border-radius:50%;
            top:12%;
            right:40px;
        }
        #my-card::after{
            content:'';
            background:#fd9616c7;
            position: absolute;
            width:20px;
            height:20px;
            border-radius:50%;
            top:12%;
            right:30px;
        } */
        #my-card .icon .svg{fill: #f4f4f4;}
        .card-js .card-number-wrapper, .card-js .cvc-wrapper, .card-js .expiry-wrapper, .card-js .name-wrapper{box-shadow:none;}
        .card-js input:focus, .card-js select:focus{box-shadow:none !important; border-color:#f4f4f4;   background: none !important}
        @media (max-width: 1024px){
            .panel{
                width: 70%;
                box-sizing: border-box;
                margin:0 auto;
            }
        }
        @media (max-width: 540px){
            .panel{
                width: 100%;
                box-sizing: border-box;
            }
        }
        @media (max-width: 420px){
            .panel{
                width: 90%;
                box-sizing: border-box;
            }

        }
        @media (max-width: 380px){
            .panel{
                width: 100%;
                box-sizing: border-box;
            }
            @media (max-height: 670px){
                .panel{
                }
            }

        }
        @media (max-width: 320px){
            .panel{
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>
    <script>
        const poyntCollect = document.createElement("script");
        poyntCollect.src = "https://poynt.net/snippet/poynt-collect/bundle.js";
        poyntCollect.async = true;
        poyntCollect.onload = () => {
            var collect = new PoyntCollect("<?=$centerParams['private_key'];?>", "<?=$centerParams['public_key'];?>");
            // var customerEmail = '12345@qq.com';
            // var firstName = 'davidif';
            // var lastName = 'zhu';
            const options = {
                //amount: 2000, // just hardcode amount to 123 for now
                iFrame: {
                    width: "250px",
                    height: "250px",
                    border: "1px",
                    borderRadius: "4px",
                    //boxShadow: "0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08)"
                },
                style: {
                    theme: "default", // theme: "customer", (alternative default theme)
                },
                displayComponents: {
                    firstName: false, // toggle these to true if you wish to show the forms
                    lastName: false,
                    emailAddress: false,
                    submitButton: false,
                    showEndingPage: false, // controls ending page,
                    labels: true
                },
                // fields: {
                //     emailAddress: customerEmail,
                //     firstName: firstName,
                //     lastName: lastName
                // },

                //additionalFieldsToValidate: ["firstName", "lastName", "zip"] // fields to validate
            }

            collect.mount("card-element", document, options);

            collect.on("ready", event => {
                // handle ready event, proceed.
                //console.log("ready");
            });

            collect.on("nonce", function(nonce) {
                // do something with the nonce
                //console.log("nonce", nonce);
            });

            collect.on("error", event => {
                // handle error event
                //console.log("error");
                //console.log(event);
                $("#errorText").text(event.data.error.message);
                $("#errorText").css({
                    'height':'auto',
                    'color':'green'
                });
                setTimeout(function() { $("#errorText").css({'height':'0px',}); }, 2000);
                //sendErrMessage(event.data.error.message);
            });

            collect.on("transaction_created", function(transaction) {
                //console.log("transaction_created");
                handleStripeResponse(transaction);
                // your process for storing or saving the transaction data
            });

            collect.on("transaction_declined", function(transaction) {
                //console.log("transaction_declined");
                $("#errorText").text(transaction.data.processorResponse.statusMessage);
                $("#errorText").css({
                    'height':'auto',
                    'color':'green'
                });
                sendErrMessage(transaction.data.processorResponse.statusMessage);
                // your process for storing or saving the transaction data
            });

            collect.on("token", function(token) {
                //console.log("token");
                // save this token for future use
            });

            var button = document.querySelector("button");
            button.addEventListener("click", event => {
                event.preventDefault();
                //collect.createToken();
                collect.createTransaction({
                    amount: <?=$centerParams['amount'];?>
                });
            });

            // put the upcoming collect initialization steps and create transaction steps here
        };
        document.head && document.head.appendChild(poyntCollect);
    </script>
</head>

<body marginwidth="0" marginheight="0">
<div style="text-align: center;">
    <div class="panelImg" style="margin: 14px auto 24px;display: block;max-width:100%"><img src="./static/images/payTitlebg.png"></div>
    <div id="card-element">
        <!-- Credit card form iFrame will be inserted here -->
    </div>
    <button id="pay_button" class="btn">Pay</button>
    <span style="display: block;color: red;height: 0;line-height:24px;transition: all 0.3s;overflow: hidden;" id="errorText"></span>
</div>
<script>
    window.parent.postMessage("loadinged", "*");
    var $ = jQuery;

    // handle the response from stripe
    function handleStripeResponse(transaction) {
        $("#pay_button").prop('disabled', false);
        $("#pay_button").addClass('btn').removeClass('button-disabled');
        $.ajax({
            url:'/checkout/pay/createOrder',
            method:'POST',
            dataType:'json',
            data:{
                transaction:transaction,
                amount:<?=$centerParams['amount'];?>,
                order_no:"<?=$centerParams['order_no'];?>",
                currency:"<?=$centerParams['currency'];?>",
                center_id:"<?=$centerId;?>",
                email:"<?=$centerParams['email'];?>",
                phone:"<?=$centerParams['telephone'];?>",
                country:"<?=$centerParams['country'];?>",
                currency_code:"<?=$centerParams['currency'];?>",
                city:"<?=$centerParams['city'];?>",
                state:"<?=$centerParams['state'];?>",
                address1:"<?=$centerParams['address'];?>",
                address2:"",
                zip:"<?=$centerParams['zip_code'];?>",
                first_name:"<?=$centerParams['first_name'];?>",
                last_name:"<?=$centerParams['last_name'];?>",
                token:"<?= get_params_token($centerParams['first_name'],$centerId,$centerParams['amount'],$centerParams['last_name']);?>"
            },
            beforeSend(){

            },
            success(res){
                $(".load-container.load2").hide()
                if (res.errcode === 0)
                {
                    if (res.data.success_risky)
                    {
                        window.parent.postMessage("success_risky","*");
                        return false;
                    }
                    window.parent.postMessage("succeeded","*");
                    showErrorMsg('Payment is successful and is jumping now...');
                    $("#errorText").css({
                        'height':'auto',
                        'color':'green'
                    });
                    $(".load-container.load2").show();
                }else{
                    // error count
                    sendFailedCount();
                    showErrorMsg(res.errmsg);
                }
            },
            complete(){
            },
            error(res){
                $(".load-container.load2").hide()
                sendFailedCount();
                showErrorMsg(res.errmsg);
            }
        });
    }
    // v2 end

    function showErrorMsg(msg = '',is_empty = false)
    {
        $(".load-container.load2").hide();
        let height = is_empty ? '0px' : 'auto';
        $("#errorText").text(msg);
        $("#errorText").css("height",height);
    }
    function sendErrMessage(message) {
        $.ajax({
            type: "post",
            url: '/checkout/pay/keyError',
            dataType: "json",
            data:{
                center_id:<?=$_POST['center_id'] ?? 0;?>,
                reason:message
            },
            success: function (data) {
                if (data.errcode === 1)
                {
                    console.log('set error failed!');
                }
            },
            error: function (data) {
                console.log('set error failed');
                return false;
            }
        });
    }

    var error_count = 0;
    function sendFailedCount() {
        error_count ++;
        if (error_count >= 2)
        {
            $.ajax({
                type: "post",
                url: '/checkout/pay/errorCount',
                dataType: "json",
                data:{center_id:<?=$_POST['center_id'] ?? 0;?>},
                success: function (data) {
                    if (data.errcode === 1)
                    {
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
</script>
</body>
</html>
