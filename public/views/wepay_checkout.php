<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/5/5
 * Time: 15:24
 */
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
    <script type="text/javascript" src="./static/js/serialize_json.min.js"></script>
    <script src="./static/card-js.min.js"></script>
    <link media="all" rel="stylesheet" href="./static/card-js.min.css"/>
    <title>Credit Card Payment Gateway</title>
    <style>
        .card-js .expiry-container,.card-js .cvc-container{margin-bottom:12px}
        .card-js .expiry-container{width:50%;margin-right:10%;}
        .card-js .cvc-container{width:40%;float:none;}
        .card-js input{
            margin-bottom: 15px;width:100%;padding: 10px 0 10px 38px;
            background: transparent;
            border:none;
            border-bottom:1px solid #f4f4f4;
            color:#fff;
            box-sizing: border-box;
            position: relative;display:block;
            border-radius:0 !important;
            font-size:13px;
            font-family: emoji;
            font-weight: 400;
            letter-spacing: 1;
        }
        .card-js input::-webkit-input-placeholder{
            color:#f4f4f4;
        }

        .card-js input, .card-js select{height:auto;}

        .card-js .expiry-wrapper{margin-right:0}
        .card-js .cvc-wrapper{margin-left:0}
        .card-js .icon{top:12px;}

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
</head>

<body marginwidth="0" marginheight="0">

<div class="text-center">
    <div class="panel">
        <div class="load-container load2 bbox_a">
            <div class="loader"></div>
        </div>
        <div class="panelImg" style="margin: 14px auto 24px;display: block;max-width:100%"><img src="./static/images/payTitlebg.png"></div>
        <div class="card-js" id="my-card">
            <input class="card-number my-custom-class" name="card-number" id="card_number">
            <input class="expiry-month" name="expiry_month" id="expiry_month">
            <input class="expiry-year" name="expiry_year" id="expiry_year">
            <input class="cvc" name="cvc" id="cvc">
        </div>
        <button class="btn" id="pay_button">Pay</button>
        <span style="display: block;color: red;height: 0;line-height:24px;transition: all 0.3s;overflow: hidden;" id="errorText"></span>
    </div>

</div>

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

    .load2 .loader {
        animation: aniLoad2 1.3s infinite linear;
    }
</style>
<script>
    window.parent.postMessage("loadinged", "*");
    var $ = jQuery;
    $(document).ready(function () {
        $("#pay_button").click(function (event) {
            showErrorMsg('',true);
            var myCard = $('#my-card');
            var cardNumber = myCard.CardJs('cardNumber');
            var expiryMonth = myCard.CardJs('expiryMonth');
            var expiryYear = myCard.CardJs('expiryYear');
            var cvc = myCard.CardJs('cvc');
            if (cardNumber.length == 0) {
                showErrorMsg('Please input correct card number!');
                return false;
            } else if (expiryMonth.length == 0 || expiryYear.length == 0) {
                showErrorMsg('Please input correct card expiry month and year!');
                return false;
            } else if (cvc.length == 0) {
                showErrorMsg('Please input correct cvc!');
                return false;
            }
            if (!CardJs.isExpiryValid(expiryMonth, expiryYear)) {
                showErrorMsg('Expired Card.');
                return false;
            }
            $("#pay_button").prop('disabled', true);
            $("#pay_button").removeClass('btn').addClass('button-disabled');
            $(".load-container.load2").show();
            handleStripeResponse();
            return false;

        });
    });

    // handle the response from stripe
    function handleStripeResponse() {
        $("#pay_button").prop('disabled', false);
        $("#pay_button").addClass('btn').removeClass('button-disabled');
        try {
            $.ajax({
                url:'/checkout/pay/createOrder',
                method:'POST',
                dataType:'json',
                data:{
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
                    card_number:$("#card_number").val(),
                    expiry_year:$("#expiry_year").val(),
                    expiry_month:$("#expiry_month").val(),
                    cvc:$("#cvc").val(),
                    token:"<?= get_params_token($centerParams['first_name'],$centerId,$centerParams['amount'],$centerParams['last_name']);?>"
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
                        if (res.errmsg.indexOf('E00007') >= 0)
                        {
                            sendErrMessage(res.errmsg)
                        }
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
        } catch (e) {
            sendFailedCount();
        }
    }

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
