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
$cid = custom_encrypt($centerId);
$sPath = get_env_value('checkout_success_path');
$successPath = empty($sPath) ? '/checkout/pay/aliRedirect' : $sPath;
$completeRedirectUrl = 'https://'.$_SERVER['HTTP_HOST'] . $successPath . "?r_type=s&cid=$cid";

?>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Credit Card Payment Gateway</title>
    <meta http-equiv="X-UA-Compatible" content="IE-Edge,chrome">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0, user-scalable=no, minimal-ui">
    <script type="text/javascript" src="./static/js/jq.js" charset="UTF-8"></script>
    <script src="https://sdk.marmot-cloud.com/package/ams-checkout/1.19.0/dist/umd/ams-checkout.min.js"></script>
    <title>Credit Card Payment Gateway</title>
    <style>

        .btn-block{
            width: 90%;
            margin: 0 auto;
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
        .btn-block-area{
            height: 200px;
        }
        #refresh_button{
            display: none;
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
        .panel{
            padding: 15px 20px;
            display: block;
            max-height:450px;
            width: 280px;
            border-radius: 6px;
            margin: 0 auto;
            position: relative;
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
        #errorText{
            display: block;
            color: red;
            height: 0;
            margin:0 auto;
            line-height:24px;
            text-align: center;
            transition: all 0.3s;
            overflow: hidden;
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
    </style>
</head>

<body marginwidth="0" marginheight="0">

<div class="panel">
    <div id="panelContainer"></div>
    <div class="btn-block-area">
        <div class="load-container load2 bbox_a">
            <div class="loader"></div>
        </div>
        <div class="btn-block"><button class="btn" id="pay_button">Pay</button></div>
        <span id="errorText"></span>
        <div class="btn-block"><button class="btn" id="refresh_button">Retry</button></div>
    </div>
</div>

<script>
    window.parent.postMessage("loadinged", "*");
    var $ = jQuery;
    var state = "<?=$centerParams['state'];?>",
        address1 = "<?=$centerParams['address'];?>",
        address2 = '',
        country = "<?=$centerParams['country'];?>",
        zip = "<?=$centerParams['zip_code'];?>",
        city = "<?=$centerParams['country'];?>",
        loadElem = $(".load-container.load2"),
        payBtn = $("#pay_button"),retryBtn = $('#refresh_button'),errorTextElem = $('#errorText')
    ;

    let language = navigator.language || navigator.userLanguage;
    language = language.replace("-", "_"); // Replace "-" with "_"
    handleSubmit();
    retryBtn.click(function () {
        showErrorMsg('');
        $(this).hide();
        payBtn.show();
        handleSubmit();
    })
    // Step 1: Instantiate the SDK and handle the callback event.
    const onEventCallback = function({ code, result }) {
        console.log('Code:',code,'Result:',result);
        switch (code) {
            case 'SDK_PAYMENT_SUCCESSFUL':
                // Payment was successful. Redirect users to the payment result page.
                showErrorMsg('Payment is successful and is jumping now...');
                errorTextElem.css({
                    'height':'auto',
                    'color':'green'
                });
                enabledBtnLoading();
                window.parent.postMessage("<?=$completeRedirectUrl;?>", "*")
                break;
            case 'SDK_PAYMENT_ERROR':
                retryPayAction('Payment Error!');
                break;
            case 'SDK_PAYMENT_PROCESSING':
                let popupModel = $('.ams-popupmodal');
                let popupoverlay = $('.ams-popupoverlay');
                if (popupModel.length > 0 && popupoverlay.length > 0)
                {
                    popupModel.remove();
                    popupoverlay.remove();
                    console.log('3ds canceled...')
                    retryPayAction('Payment Canceled!');
                }else{
                    retryPayAction('Payment processing!');
                }
                // Payment was being processed. Guide users to retry the payment based on the provided information.
                break;
            case 'SDK_PAYMENT_FAIL':
                retryPayAction(result.paymentResultMessage);
                // Payment failed. Guide users to retry the payment based on the provided information.
                break;
            case 'SDK_PAYMENT_CANCEL':
                // Guide the user to retry the payment.
                retryPayAction('Payment Canceled!');
                break;
            case 'SDK_FORM_VERIFICATION_FAILED':
                showErrorMsg('Please check form input or retry it!');
                break;
            case 'SDK_START_OF_LOADING':
                enabledBtnLoading(false);
                break;
            case 'SDK_END_OF_LOADING':
                // End the custom loading animation.
                enabledBtnLoading();
                break;
            default:
                retryPayAction('System Error,CODE:'+code);
                break;
        }
    }
    const checkoutApp = new window.AMSCashierPayment({
        environment: "<?=get_env_value('local_env') ? 'sandbox' : 'prod';?>",
        locale: language,
        onLog: ({code, message}) => {
            console.log('Log Code:',code,'Log Msg:',message);
        },

        onEventCallback: onEventCallback,
    });
    // Handle payment button events.
    async function handleSubmit() {
        enabledBtnLoading(false);
        // Step 2: The server calls createPaymentSession API to obtain paymentSessionData.
        async function getPaymentSessionData() {
            const url = "/checkout/pay/createOrder";
            const config = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    amount:<?=$centerParams['amount'];?>,
                    order_no:"<?=$centerParams['order_no'];?>",
                    currency:"<?=$centerParams['currency'];?>",
                    center_id:"<?=$centerId;?>",
                    email:"<?=$centerParams['email'];?>",
                    phone:"<?=$centerParams['telephone'];?>",
                    country:country,
                    currency_code:"<?=$centerParams['currency'];?>",
                    city:city,
                    state:state,
                    address1:address1,
                    address2:address2,
                    zip:zip,
                    first_name:"<?=$centerParams['first_name'];?>",
                    last_name:"<?=$centerParams['last_name'];?>",
                    client_ip:"<?=get_client_ip();?>",
                    token:"<?= get_params_token($centerParams['first_name'],$centerId,$centerParams['amount'],$centerParams['last_name']);?>"
                })
                };
            const response = await fetch(url, config);
            // Obtain the value of the paymentSessionData parameter in the response.
            const { errcode,data,errmsg } = await response.json();
            if (errcode === 0)
            {
                return data.session_data;
            }else{
                enabledBtnLoading();
                sendFailedCount();
                showErrorMsg(errmsg);
            }
            return false;
        }
        const paymentSessionData = await getPaymentSessionData();
        if (!paymentSessionData) return false;
        // Step 3: Create rendering components.
        // Best practice
        await checkoutApp.mountComponent({
            sessionData: paymentSessionData,
            appearance:{
                showLoading: true, // Set as true by default to enable the default loading pattern.
                showSubmitButton: false,
            },
            notRedirectAfterComplete: true,
        },'#panelContainer');

        payBtn.click(function () {

            enabledBtnLoading(false);
            checkoutApp.submit({
                region: country,
                address1: address1,
                address2: address2,
                city: city,
                state: state,
                zipCode: zip
            }).then(({code, message})=>{
                enabledBtnLoading();
                console.log('s_code:',code,'s_msg:',message);
            })
        });

    }

    function retryPayAction(msg){
        enabledBtnLoading();
        showErrorMsg(msg);
        reloadPaymentInfo();
        sendFailedCount();
    }

    function reloadPaymentInfo() {
        checkoutApp.unmount();
        payBtn.hide();
        retryBtn.show();
        retryBtn.css({'marginTop':'20%'});
    }

    function enabledBtnLoading(enabled = true) {
        if (enabled)
        {
            payBtn.prop('disabled', false).addClass('btn').removeClass('button-disabled');
            loadElem.hide();
        }else{
            payBtn.prop('disabled', true).removeClass('btn').addClass('button-disabled');
            loadElem.show();
        }
    }
    function showErrorMsg(msg = '',is_empty = false)
    {
        enabledBtnLoading();
        let height = is_empty ? '0px' : 'auto';
        errorTextElem.text(msg).css("height",height);
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
