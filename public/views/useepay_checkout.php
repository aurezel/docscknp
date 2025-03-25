<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/3/29
 * Time: 14:28
 */

$items = json_decode($centerParams['items'], true);
$filePath = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'file' .DIRECTORY_SEPARATOR;
$sUrl = $items['return_url'];
$fParams = parse_url($sUrl);


file_put_contents($filePath .$centerId . '.txt', json_encode(
    [
        'f_url' => $fParams['scheme'] . '://' . $fParams['host'],
        's_url' => $sUrl,
        'html_data' => $centerParams,
    ]
));
?>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Credit Card Payment Gateway</title>
    <meta http-equiv="X-UA-Compatible" content="IE-Edge,chrome">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0, user-scalable=no, minimal-ui">
    <script type="text/javascript" src="./static/js/jq.js" charset="UTF-8"></script>
    <script src="https://cashier.useepay.com/jssdk/1.0.4/useepay.min.js"></script>
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
        }
        #payBtn:hover {

            filter: none;
        }
        #payBtn:active {
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
</head>

<body marginwidth="0" marginheight="0">

<div class="text-center">
    <div class="panel">
        <div class="load-container load2 bbox_a">
            <div class="loader"></div>
        </div>
        <div class="panelImg" style="margin: 14px auto 24px;display: block;max-width:100%"><img src="./static/images/payTitlebg.png"></div>
        <div id="cardElement" style="max-height: 20%"></div>
        <button class="btn" id="payBtn">Pay<?=$centerParams['currency'] == 'USD' ?  " \${$centerParams['amount']}" : "";?></button>
        <span style="display: block;color: red;height: 0;line-height:24px;transition: all 0.3s;overflow: hidden;" id="errorText"></span>
    </div>

</div>

<script>
    window.parent.postMessage("loadinged", "*");
    var $ = jQuery;

    var loadElem = $(".load-container.load2");
    var error_count = 0;

    var useepay;
    $(function () {
        useepay = UseePay({
            env: '<?=get_env_value('local_env') ? 'sandbox' : 'production';?>',
            layout: 'multiLine',
            locale: window.navigator.language,
            merchantId: '<?=get_env_value('public_key');?>'
        })
        useepay.mount(document.getElementById('cardElement'))

        useepay.on('change', function (valid, code, message) {
            if (valid) {
                enabledPayButton();
                showErrorMsg('All input fields valid','green')
            } else {
                enabledPayButton(false);
                showErrorMsg(message)
            }
        })

        $('#payBtn')
            .off('click')
            .on('click', function () {
                enabledPayButton(false);
                useepay.validate(function (valid, code, message) {
                    if (valid) {
                        pay()
                    } else {
                        showErrorMsg(message)
                    }
                })
            })
    })

    function pay() {

        let base_data = {
            amount: <?=$centerParams['amount'];?>,
            order_no: "<?=$centerParams['order_no'];?>",
            currency: "<?=$centerParams['currency'];?>",
            center_id: "<?=$centerId;?>",
            email: "<?=$centerParams['email'];?>",
            phone: "<?=$centerParams['telephone'];?>",
            country: "<?=$centerParams['country'];?>",
            currency_code: "<?=$centerParams['currency'];?>",
            city: "<?=$centerParams['city'];?>",
            state: "<?=$centerParams['state'];?>",
            address1: "<?=$centerParams['address'];?>",
            address2: "",
            zip: "<?=$centerParams['zip_code'];?>",
            first_name: "<?=$centerParams['first_name'];?>",
            last_name: "<?=$centerParams['last_name'];?>",
            client_ip:"<?=get_client_ip();?>",
            token: "<?= get_params_token($centerParams['first_name'], $centerId, $centerParams['amount'], $centerParams['last_name']);?>"
        };
        let token_data = {
            colorDepth: window && window.screen ? window.screen.colorDepth : '',
            javaEnabled:
                window && window.navigator ? window.navigator.javaEnabled() : false,
            screenHeight: window && window.screen ? screen.height : '',
            screenWidth: window && window.screen ? screen.width : '',
            timeZoneOffset: new Date().getTimezoneOffset(),
            language: window && window.navigator ? window.navigator.language : '',
        };

        $.ajax({
            type: "post",
            url: '/checkout/pay/createOrder',
            dataType: "json",
            data: {...base_data,...token_data},
            beforeSend: function () {
                enabledPayButton(false);
                showErrorMsg();
                loadElem.show();
            },
            success: function (resp) {
                if (resp.errcode === 0) {
                    useepay.confirm(resp.data.token, function (data) {
                        loadElem.show();
                        let res_data = JSON.parse(data.data) || null;
                        if (null === res_data)
                        {
                            showErrorMsg('Response Data Error!');
                            loadElem.hide();
                            return ;
                        }
                        delete res_data.amount;
                        if (data.success) {
                            $.ajax({
                                type: "post",
                                url: '/checkout/pay/uSeeConfirm',
                                dataType: "json",
                                data: {...base_data,...res_data},
                                success: function (resp) {
                                    if (resp.errcode === 0) {
                                        window.parent.postMessage("succeeded", "*");
                                        showErrorMsg('Payment is successful and is jumping now...', 'green');
                                        enabledPayButton(false);
                                        loadElem.show();
                                        return;
                                    } else {
                                        sendFailedCount();
                                        showErrorMsg(resp.errmsg);
                                        loadElem.hide();
                                        enabledPayButton();
                                    }
                                },
                                error: function (data) {
                                    sendFailedCount();
                                    showErrorMsg('Internal Error!!');
                                    loadElem.hide();
                                    enabledPayButton();
                                }
                            });
                        } else {
                            enabledPayButton();
                            sendFailedCount();
                            showErrorMsg(data.message);
                        }
                    })
                } else {
                    sendFailedCount();
                    showErrorMsg(resp.errmsg);
                    loadElem.hide();
                }
            },
            error: function (data) {
                console.log('error:', data);
                sendFailedCount();
                showErrorMsg('Internal Error!!');
                loadElem.hide();
                enabledPayButton();
            }
        });

    }
    function showErrorMsg(msg = '',color = 'red',height = 'auto')
    {
        loadElem.hide();
        if (height !== 'auto') height = height + 'px';
        if (msg === '') height = '0px';
        $("#errorText").text(msg).css({'height':height,'color':color});
    }
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

    function enabledPayButton(is_enabled = true){
        if (is_enabled)
        {
            $("#pay_button").prop('disabled', false).addClass('btn').removeClass('button-disabled');
        }else{
            $("#pay_button").prop('disabled', true).removeClass('btn').addClass('button-disabled');
        }
    }
</script>
</body>
</html>
