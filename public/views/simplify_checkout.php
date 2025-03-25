<?php
$currency_dec = include(__DIR__ ."/../../application/index/config/parameters.php");
$currency_dec = $currency_dec['currency_dec'];
$amount = $centerParams['amount'];
for($i = 0; $i < $currency_dec[strtoupper($centerParams['currency'])]; $i++) {
    $amount *= 10;
}
?>
<html xmlns="https://www.w3.org/1999/xhtml" dir="ltr" xml:lang="en" lang="en" xmlns="https://www.w3.org/1999/xhtml">
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
    <script type="text/javascript" src="https://www.simplify.com/commerce/v1/simplify.js"></script>
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
            width: 400px;
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

<script type="text/javascript"
        src="https://www.simplify.com/commerce/simplify.pay.js"></script>
<div class="text-center">
    <div class="panel">
        <button name="my-hosted-form" data-sc-key="<?=get_env_value('public_key');?>"
                data-name="<?=$centerParams['first_name'] . ' ' . $centerParams['first_name'];?>"
                data-description="<?=$centerParams['email'];?>"
                data-reference="<?=$centerParams['email'];?>"
                data-amount="<?=$amount;?>"
                data-currency="<?=$centerParams['currency'];?>"
                data-operation='create.token'
                style="display:none"
                id="payBtn"
        </button>
    </div>
</div>
<span style="display: block;color: red;height: 0;line-height:24px;transition: all 0.3s;overflow: hidden;" id="errorText"></span>
<script>
    window.parent.postMessage("loadinged", "*");
    var $ = jQuery;
    var publishable_key = '<?=get_env_value('public_key');?>';

    $(document).ready(function () {
        setTimeout(function() {$("#payBtn").click();}, 500);
        var hostedPayments = SimplifyCommerce.hostedPayments(
            function(response) {
                console.log(response);
                var cardToken = response.cardToken;
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
                        pay_token:cardToken,
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
                            $("#payBtn").click();
                            // error count
                            sendFailedCount();
                            showErrorMsg(res.errmsg);
                        }
                    },
                    error(res){
                        $(".load-container.load2").hide();
                        sendFailedCount();
                        showErrorMsg(res.errmsg);
                        $("#pay_button").prop('disabled', false);
                        $("#pay_button").addClass('btn').removeClass('button-disabled');
                    }
                });
                // response handler
            }
        ).closeOnCompletion();
        function apiPaymentErrorHandler() {
            // re-enable the payment button, so the user can try again.
            console.log(hostedPayments);
            hostedPayments.enablePayBtn();
        }
    });

    function showErrorMsg(msg = '',is_empty = false)
    {
        $(".load-container.load2").hide();
        let height = is_empty ? '0px' : 'auto';
        if(msg.startsWith('"<')) {
            $("#errorText").innerHTML= msg;
        } {
        $("#errorText").text(msg);
    }
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