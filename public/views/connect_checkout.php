<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/3/29
 * Time: 14:28
 */
?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pay with debit or credit card</title>
    <meta name="description" content="A demo of Stripe Payment Intents">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="./static/css/normalize.css">
    <link rel="stylesheet" href="./static/css/global.css">
    <link rel="stylesheet" href="./static/css/lyq-stripe.css">
    <script src="./static/js/jq.js"></script>

    <script src="https://js.stripe.com/v3/"></script>


    <style type="text/css">#stripe-card-element {
            background: #eee;
            clear: both;
            padding: 10px;
            border: 1px solid #ddd;
        }

        #stripe-card-errors {
            display: none;
            float: none;
            clear: both;
            color: #721c24;
            background-color: #f8d7da;
            border-color: #f5c6cb;
            border: 1px solid transparent;
            border-radius: .25rem;
            padding: .75rem 1.25rem;
        }

        label[for="z_stripe-save-cc"] {
            float: none;
            width: auto;
        }

        #stripe-card-element-owner {
            clear: both;
        }

        #stripe-card-owner {
            font-size: 17px;
            border: 1px solid #ddd;
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            margin-bottom: 10px;
            color: #5b5858;
        }
    </style>

    <script charset="utf-8"
            src="https://js.stripe.com/v3/fingerprinted/js/trusted-types-checker-9b6e874f149cc545c2c2335f8707fd1f.js"></script>
</head>

<body marginwidth="0" marginheight="0">
<div class="sr-root">
    <div id="message_info">

    </div>
    <div class="sr-main" id="redirect_main" style="display:none">
        <iframe name="redirectstripeiframe" src="" style="min-height: 300px;" margin="0" width="100%;" height="100%"
                frameborder="no" border="0" marginwidth="0" marginheight="0" scrolling="no" allowtransparency="yes"
                id="redirectstripeiframe">
        </iframe>
        <script>
            function resultPress(status, info) {
                var messages = '';
                $.ajax({
                    type: "post",
                    url: '/checkout/pay/createOrder',
                    data: {
                        paymentaction: "paymentresult",
                        status: status,
                        info: info,
                        stripe_data: "eyJjb2RlIjoiMCIsIm1lc3NhZ2UiOiJzdWNjZXNzIiwiZGF0YSI6eyJ3ZWJzaXRlX3N0cmlwZSI6InNrX2xpdmVfNTFKQ21sNkNjZkR2Y09nM2JVc3NUdDIxN0lWYWNuRmw4SFBYbm9uUWl3OHpoTHg4aEV5SVB3MmJYZHhlbWlLbVlxSEhWRzZqYmQ1QXRxZDVkckNGdWJ6cVUwMEZoeTNxbE5mIiwid2Vic2l0ZV9zdHJpcGVfcHVibGlzaGFibGUiOiJwa19saXZlXzUxSkNtbDZDY2ZEdmNPZzNiakl3aTJzeEp2UExqUWpXZURERUFJMTIyMWtlZXppTmp1NXpLODdFdG5OQzhUSTd0cHZCaE15ZHlOSE5HdzQzRUl3V0psemtFMDBvZkczZEFjTyIsIm5hbWUiOiJcdTlhZDhcdTZhNGJcdTkwNTNcdTUzNWEgXHUzMGJmXHUzMGFiXHUzMGNmXHUzMGI3XHUzMGRmXHUzMGMxXHUzMGQyXHUzMGVkIiwib3JkZXJzX2NvZGUiOiIxMjE3OSIsImxpbmUxIjoiMVx1NGUwMVx1NzZlZTE2NFx1MzAwMFx1MzBiMlx1MzBiOSIsImNpdHkiOiJcdTUzNTdcdTc2ZjhcdTk5YWNcdTVlMDJcdTUzOWZcdTc1M2FcdTUzM2FcdTk3NTJcdTg0NDlcdTc1M2EiLCJzdGF0ZSI6Ilx1OTA1M1x1NWU5Y1x1NzcwYyIsInBvc3RhbF9jb2RlIjoiOTc1LTAwMzkiLCJjb3VudHJ5IjoiSlAiLCJlbWFpbCI6ImtqaHNhZmxAZ21haWwuY29tIiwicGhvbmUiOiIwOTAtNDU2OFx1ZmYwZDg3NjciLCJwYXltZW50X21ldGhvZCI6IjEiLCJ3ZWJzaXRlX2NfdXJsIjpudWxsLCJ3ZWJzaXRlX2NfaWQiOm51bGwsInBheW1lbnRfbnVtYmVyX2lkIjpudWxsLCJjdXJyZW5jeV9jb2RlIjoiSlBZIiwiYW1vdW50X2FkZCI6IiIsImFtb3VudCI6IjQxMTAiLCJjYW5jZWxfdXJsIjoiaHR0cHM6XC9cL3Nob3B2aWFscy50b3BcL2luZGV4LnBocD9tYWluX3BhZ2U9Y2hlY2tvdXRfcGF5bWVudCIsInNob3dfaWNvbnMiOiJzaG93X2ljb25zIiwicHJvZHVjdHNfaW5mbyI6W3sicHJvZHVjdHNfaWQiOiI3NzUiLCJwcm9kdWN0c19uYW1lIjoiXHU4MGExXHU0ZTBiM1x1NGUwOFx1MzA0Ylx1MzA4OVx1OTA3OFx1MzA3OVx1MzA4Ylx1ZmYwMVx1MzBhNlx1MzBhOFx1MzBiOVx1MzBjOFx1N2RjZlx1MzBiNFx1MzBlMFx1MzA2ZVx1MzA3Ylx1MzA2M1x1MzA1ZFx1MzA4YVx1N2Y4ZVx1ODExYVx1MzBlOVx1MzBhNFx1MzBmM1x1MzBkMVx1MzBmM1x1MzBjNHx8fFx1NTE2OFx1NTRjMVx1MzBkZFx1MzBhNFx1MzBmM1x1MzBjODEwXHU1MDBkIDRcLzkgMjA6MDAtNFwvMTAgMjM6NTlcdTI1YTAiLCJwcm9kdWN0c19wcmljZSI6IjE2LjcyIiwicHJvZHVjdHNfcXVhbnRpdHkiOiIzIiwicHJvZHVjdHNfYXR0cmlidXRlcyI6IiJ9XSwib3JkZXJzX2lkIjoiMzkwM2RhOTI2NjI4YTJmODI2NjVlNzgxNTVmMmUwZmEiLCJjYWxsYmFja191cmwiOiJodHRwczpcL1wvbG9uc2hlbmdwYXkuY29tXC9zdHJpcGVfY2FsbGJhY2sucGhwIiwiZmV0Y2hfdXJsIjoiaHR0cHM6XC9cL2xvbnNoZW5ncGF5LmNvbVwvc3RyaXBlX2ZldGNoLnBocCJ9fQ=="
                    },
                    dataType: "json",
                    success: function (data) {

                        if (data.responseText == 'succeed') {
                            window.parent.postMessage("succeeded", "*");
                        }
                        else if (data.responseText.indexOf("http") != -1) {
                            window.location.href = data.responseText;
                        }

                    },
                    error: function (data) {
                        if (data.responseText == 'succeed') {
                            window.parent.postMessage("succeeded", "*");
                        }
                        else if (data.responseText.indexOf("http") != -1) {
                            window.location.href = data.responseText;
                        }
                    }
                });
            }

            function on3DSComplete() {
                // Hide the 3DS UI
                //$("#redirect_main").hide();
                // Check the PaymentIntent
                stripe.retrievePaymentIntent('')
                    .then(function (result) {
                        if (result.error) {
                            resultPress("failed", "PaymentIntent client secret was invalid");
                            $("#stripe_main").show();

                            $("#stripe_error").html("PaymentIntent client secret was invalid");
                            //
                        } else {
                            if (result.paymentIntent.status == 'succeeded') {
                                messages = '<span><strong>Order ID:</strong>12179</span><br /><p>Your order has been successfully processed!</p><p>Please direct any questions you have to the store owner.</p><p>Thanks for shopping with us online!</p>';

                                resultPress("succeeded", "");
                                $("#message_info").html(messages);

                                // Show your customer that the payment has succeeded
                            } else if (result.paymentIntent.status == 'requires_payment_method') {

                                resultPress("failed", "Payment failed");
                                $("#redirect_main").hide();
                                $("#stripe_main").show();
                                $("#stripe_error").html("Payment failed");
                                // Authentication failed, prompt the customer to enter another payment method
                            }
                        }
                    });
            }

            window.addEventListener('message', function (ev) {
                if (ev.data == '3DS-authentication-complete') {
                    on3DSComplete();
                }
            }, false);

        </script>
    </div>


    <div class="sr-main" id="stripe_main">
        <div class="payment-info">

            <form id="payment-form" action="/checkout/pay/createOrder" method="post" class="sr-payment-form lyq-stripe">
                <span style="color:red;    margin-bottom: 10px;display: inline-block;font-size: 14px;"
                      id="stripe_error"></span>
                <img src="./static/images/payTitlebg.png" style="margin: 14px auto 24px;display: block;max-width:100%"/>
                <div class="sr-combo-inputs-row">
                    <!-- <div style="
                        width: 100%;
                        max-width: 560px;
                        margin: -50px -50px 50px;
                        padding: 0 50px;
                        text-align: center;
                        line-height: 46px;
                        background: linear-gradient(45deg, #6ed8b0, #24b47e,#639280);
                        /* border-radius: 6px; */
                        font-size: 16px;
                        color: #fff;
                        box-sizing: content-box;
                         ">payment</div> -->

                    <!-- <div class="sr-input sr-card-element StripeElement StripeElement--empty" id="card-element"></div> -->

                    <div class='box'>
                        <div class="baseline"></div>
                        <div class="sr-input sr-card-element StripeElement StripeElement--empty"  id="example-card-number"></div>
                    </div>
                    <div class='box' style="width: 50%;margin-right: 10%;display: inline-block;">
                        <div class="baseline"></div>
                        <div class="sr-input sr-card-element StripeElement StripeElement--empty" id="example-card-expiry"></div>
                        <div class="baseline"></div>
                    </div>
                    <div class='box' style="width: 38%;display: inline-block;">
                        <div class="baseline"></div>
                        <div class="sr-input sr-card-element StripeElement StripeElement--empty" id="example-card-cvc"></div>
                    </div>
                    <!-- <div class="row box">
                        <div class="left" >
                            <div class="baseline"></div>
                            <div class="sr-input sr-card-element StripeElement StripeElement--empty" id="example-card-expiry"></div>
                            <div class="baseline"></div>
                        </div>
                        <div class="right box">
                            <div class="baseline"></div>
                            <div class="sr-input sr-card-element StripeElement StripeElement--empty" id="example-card-cvc"></div>
                        </div>

                    </div> -->


                    <input type="hidden" name="stripe_token" id="stripe-paymentmethod">
                    <input type="hidden" name="stripe_error" id="stripe-error">
                    <input type="hidden"
                           value="eyJjb2RlIjoiMCIsIm1lc3NhZ2UiOiJzdWNjZXNzIiwiZGF0YSI6eyJ3ZWJzaXRlX3N0cmlwZSI6InNrX2xpdmVfNTFKQ21sNkNjZkR2Y09nM2JVc3NUdDIxN0lWYWNuRmw4SFBYbm9uUWl3OHpoTHg4aEV5SVB3MmJYZHhlbWlLbVlxSEhWRzZqYmQ1QXRxZDVkckNGdWJ6cVUwMEZoeTNxbE5mIiwid2Vic2l0ZV9zdHJpcGVfcHVibGlzaGFibGUiOiJwa19saXZlXzUxSkNtbDZDY2ZEdmNPZzNiakl3aTJzeEp2UExqUWpXZURERUFJMTIyMWtlZXppTmp1NXpLODdFdG5OQzhUSTd0cHZCaE15ZHlOSE5HdzQzRUl3V0psemtFMDBvZkczZEFjTyIsIm5hbWUiOiJcdTlhZDhcdTZhNGJcdTkwNTNcdTUzNWEgXHUzMGJmXHUzMGFiXHUzMGNmXHUzMGI3XHUzMGRmXHUzMGMxXHUzMGQyXHUzMGVkIiwib3JkZXJzX2NvZGUiOiIxMjE3OSIsImxpbmUxIjoiMVx1NGUwMVx1NzZlZTE2NFx1MzAwMFx1MzBiMlx1MzBiOSIsImNpdHkiOiJcdTUzNTdcdTc2ZjhcdTk5YWNcdTVlMDJcdTUzOWZcdTc1M2FcdTUzM2FcdTk3NTJcdTg0NDlcdTc1M2EiLCJzdGF0ZSI6Ilx1OTA1M1x1NWU5Y1x1NzcwYyIsInBvc3RhbF9jb2RlIjoiOTc1LTAwMzkiLCJjb3VudHJ5IjoiSlAiLCJlbWFpbCI6ImtqaHNhZmxAZ21haWwuY29tIiwicGhvbmUiOiIwOTAtNDU2OFx1ZmYwZDg3NjciLCJwYXltZW50X21ldGhvZCI6IjEiLCJ3ZWJzaXRlX2NfdXJsIjpudWxsLCJ3ZWJzaXRlX2NfaWQiOm51bGwsInBheW1lbnRfbnVtYmVyX2lkIjpudWxsLCJjdXJyZW5jeV9jb2RlIjoiSlBZIiwiYW1vdW50X2FkZCI6IiIsImFtb3VudCI6IjQxMTAiLCJjYW5jZWxfdXJsIjoiaHR0cHM6XC9cL3Nob3B2aWFscy50b3BcL2luZGV4LnBocD9tYWluX3BhZ2U9Y2hlY2tvdXRfcGF5bWVudCIsInNob3dfaWNvbnMiOiJzaG93X2ljb25zIiwicHJvZHVjdHNfaW5mbyI6W3sicHJvZHVjdHNfaWQiOiI3NzUiLCJwcm9kdWN0c19uYW1lIjoiXHU4MGExXHU0ZTBiM1x1NGUwOFx1MzA0Ylx1MzA4OVx1OTA3OFx1MzA3OVx1MzA4Ylx1ZmYwMVx1MzBhNlx1MzBhOFx1MzBiOVx1MzBjOFx1N2RjZlx1MzBiNFx1MzBlMFx1MzA2ZVx1MzA3Ylx1MzA2M1x1MzA1ZFx1MzA4YVx1N2Y4ZVx1ODExYVx1MzBlOVx1MzBhNFx1MzBmM1x1MzBkMVx1MzBmM1x1MzBjNHx8fFx1NTE2OFx1NTRjMVx1MzBkZFx1MzBhNFx1MzBmM1x1MzBjODEwXHU1MDBkIDRcLzkgMjA6MDAtNFwvMTAgMjM6NTlcdTI1YTAiLCJwcm9kdWN0c19wcmljZSI6IjE2LjcyIiwicHJvZHVjdHNfcXVhbnRpdHkiOiIzIiwicHJvZHVjdHNfYXR0cmlidXRlcyI6IiJ9XSwib3JkZXJzX2lkIjoiMzkwM2RhOTI2NjI4YTJmODI2NjVlNzgxNTVmMmUwZmEiLCJjYWxsYmFja191cmwiOiJodHRwczpcL1wvbG9uc2hlbmdwYXkuY29tXC9zdHJpcGVfY2FsbGJhY2sucGhwIiwiZmV0Y2hfdXJsIjoiaHR0cHM6XC9cL2xvbnNoZW5ncGF5LmNvbVwvc3RyaXBlX2ZldGNoLnBocCJ9fQ=="
                           name="stripe_data" id="stripe_data">
                </div>
                <div class="error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 17 17">
                        <path class="base" fill="#000" d="M8.5,17 C3.80557963,17 0,13.1944204 0,8.5 C0,3.80557963 3.80557963,0 8.5,0 C13.1944204,0 17,3.80557963 17,8.5 C17,13.1944204 13.1944204,17 8.5,17 Z"></path>
                        <path class="glyph" fill="#FFF" d="M8.5,7.29791847 L6.12604076,4.92395924 C5.79409512,4.59201359 5.25590488,4.59201359 4.92395924,4.92395924 C4.59201359,5.25590488 4.59201359,5.79409512 4.92395924,6.12604076 L7.29791847,8.5 L4.92395924,10.8739592 C4.59201359,11.2059049 4.59201359,11.7440951 4.92395924,12.0760408 C5.25590488,12.4079864 5.79409512,12.4079864 6.12604076,12.0760408 L8.5,9.70208153 L10.8739592,12.0760408 C11.2059049,12.4079864 11.7440951,12.4079864 12.0760408,12.0760408 C12.4079864,11.7440951 12.4079864,11.2059049 12.0760408,10.8739592 L9.70208153,8.5 L12.0760408,6.12604076 C12.4079864,5.79409512 12.4079864,5.25590488 12.0760408,4.92395924 C11.7440951,4.59201359 11.2059049,4.59201359 10.8739592,4.92395924 L8.5,7.29791847 L8.5,7.29791847 Z"></path>
                    </svg>
                    <div class="sr-field-error" id="card-errors" role="alert">23123432</div>
                </div>

                <button id="submits">
                    <div class="spinner hidden" id="spinner"></div>
                    <span id="button-text" >Pay</span><span id="order-amount"></span>
                </button>

            </form>


            <div class="sr-result hidden">
                <p>Payment completed<br></p>
                <pre>            <code></code>
          </pre>
            </div>
        </div>


    </div>


</div>

<script>
    window.parent.postMessage("loadinged", "*");
</script>
<script>
    jQuery(function ($) {
        $("#transactionCart").click(function () {
            $(".transactionDetailsContainer").addClass("cart");

        });
        $("#closeCart").click(function () {
            $(".transactionDetailsContainer").removeClass("cart");
        });
        $(".itemNameContainer .more").click(function () {
            $(this).addClass("ng-hide");
            $(".detail-items .less").removeClass("ng-hide");
            $(".details ul.itemDetails").show();
        });
        $(".detail-items .less").click(function () {
            $(".itemNameContainer .more").removeClass("ng-hide");
            $(".detail-items .less").addClass("ng-hide");
            $(".details ul.itemDetails").hide();
        });


    });



    var showError = function (errorMsgText) {
        changeLoadingState(false);
        var errorMsg = document.querySelector(".sr-field-error");
        errorMsg.textContent = errorMsgText;
        setTimeout(function () {
            errorMsg.textContent = "";

            document.querySelector(".error").classList.remove('errorHide')
        }, 4000);
    };
    var changeLoadingState = function (isLoading) {
        if (isLoading) {
            document.querySelector("button").disabled = true;
            document.querySelector("#spinner").classList.remove("hidden");
            document.querySelector("#button-text").classList.add("hidden");
        } else {
            document.querySelector("button").disabled = false;
            document.querySelector("#spinner").classList.add("hidden");
            document.querySelector("#button-text").classList.remove("hidden");
        }
    };
    var stripe = Stripe("<?= get_env_value('public_key');?>",{
        stripeAccount: "<?= get_env_value('merchant_token');?>"
    });

    // Create an instance of Elements.
    var elements = stripe.elements({

        fonts: [
            {
                cssSrc: 'https://fonts.googleapis.com/css?family=Source+Code+Pro',
            },
        ],
    });

    // Custom styling can be passed to options when creating an Element.
    // (Note that this demo uses a wider set of styles than the guide below.)

    var style = {
        base: {
            color: '#f4f4f4',
            fontWeight: 500,
            fontFamily: 'Source Code Pro, Consolas, Menlo, monospace',
            fontSize: '16px',
            fontSmoothing: 'antialiased',

            '::placeholder': {
                color: '#f4f4f4',
            },
            ':-webkit-autofill': {
                color: '#e39f48',
            },
        },
        invalid: {
            color: '#E25950',
            '::placeholder': {
                color: '#f4f4f4',
            },
        },

    };
    var elementClasses = {
        focus: 'focused',
        empty: 'empty',
        invalid: 'invalid',
    };
    var cardNumber = elements.create('cardNumber', {
        showIcon: true,
        style: style,
        classes: elementClasses,
    });
    cardNumber.mount('#example-card-number');

    var cardExpiry = elements.create('cardExpiry', {
        style: style,
        classes: elementClasses,
    });
    cardExpiry.mount('#example-card-expiry');

    var cardCvc = elements.create('cardCvc', {
        style: style,
        classes: elementClasses,
    });
    cardCvc.mount('#example-card-cvc');


    // Create an instance of the card Element.
    //var card = elements.create("card", {style: style});

    // Add an instance of the card Element into the `stripe-card-element` <div>.
    //card.mount("#card-element");

    // Handle real-time validation errors from the card Element.
    cardNumber.addEventListener("change", function (event) {
        if (event.error) {
            showError(event.error.message);
            document.querySelector(".error").classList.add('errorHide')
        }else{
            document.querySelector(".error").classList.remove('errorHide')
        }
    });
    cardExpiry.addEventListener("change", function (event) {
        if (event.error) {
            showError(event.error.message);
            document.querySelector(".error").classList.add('errorHide')
        }else{
            document.querySelector(".error").classList.remove('errorHide')
        }
    });
    cardCvc.addEventListener("change", function (event) {
        if (event.error) {
            showError(event.error.message);
            document.querySelector(".error").classList.add('errorHide')
        }else{
            document.querySelector(".error").classList.remove('errorHide')
        }
    });

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

    function handleServerResponse(response) {
        if (response.error) {
            // Show error from server on payment form
            sendFailedCount();
            showError(response.error);
            document.querySelector(".error").classList.add('errorHide')
            return false;

        } else if (response.requires_action) {
            // Use Stripe.js to handle required card action
            document.querySelector(".error").classList.remove('errorHide')
            handleAction(response);
        } else {
            // Show success message
            let msg = response.success_risky ? 'success_risky' : 'succeeded';
            window.parent.postMessage(msg, "*");
            document.querySelector(".error").classList.remove('errorHide')
            return false;

        }
    }

    function handleAction(response) {
        stripe.handleCardAction(
            response.payment_intent_client_secret
        ).then(function (result) {
            if (result.error) {
                // Show error in payment form
                sendErrMessage(result.error.message);
                sendFailedCount();
                document.querySelector(".error").classList.add('errorHide')
                showError(result.error.message);
            } else {
                // The card action has been handled
                // The PaymentIntent can be confirmed again on the server
                // /index.php?a=confirmation
                fetch('/checkout/pay/createOrder', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        payment_intent_id: result.paymentIntent.id,
                        center_id:"<?=$_POST['center_id'];?>"
                    })
                }).then(function (confirmResult) {
                    return confirmResult.json();
                }).then(handleServerResponse);
                document.querySelector(".error").classList.remove('errorHide')
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
    // data
    var address_line1 = "<?=$centerParams['address'];?>";
    var address_line2 = '';
    var address_city = "<?=$centerParams['city'];?>";
    var address_state = "<?=$centerParams['state'];?>";
    var address_zip = "<?=$centerParams['zip_code'];?>";
    var address_country = "<?=$centerParams['country'];?>";
    var email = "<?=$centerParams['email'];?>";
    var phone = "<?=$centerParams['telephone'];?>";
    var name = "<?=$centerParams['first_name'] . ' ' . $centerParams['last_name'];?>";
    var form = document.getElementById("payment-form");
    form.addEventListener("submit", function (event) {
        event.preventDefault();
        changeLoadingState(true);
        if (name != "" && phone != "" && email != "" && address_city != "" && address_country != "" && address_zip != "" && address_state != "" && address_line1 != "") {
            var address = {
                city: address_city,
                country: address_country,
                line1: address_line1,
                line2: address_line2,
                postal_code: address_zip,
                state: address_state
            };
            var billing_details = {address: address, name: name, phone: phone, email: email};
            var billing_address = {type: "card", card: cardNumber, billing_details: billing_details};
        }
        else {
            var billing_address = {type: "card", card: cardNumber};
        }
        // process card info and return the token
        stripe.createPaymentMethod(billing_address).then(function (result) {
            if (result.error) {
                if (result.error.type === 'invalid_request_error')
                {
                    sendErrMessage(result.error.message);
                }
                sendFailedCount();
                showError(result.error.message);
                document.querySelector(".error").classList.add('errorHide')
                // Show error in payment form
            } else {
                // Send paymentMethod.id to server
                fetch('/checkout/pay/createOrder', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        payment_method_id: result.paymentMethod.id,
                        amount:<?=$centerParams['amount'];?>,
                        currency:"<?=$centerParams['currency'];?>",
                        center_id:"<?=$_POST['center_id'];?>"
                    })
                }).then(function (result) {
                    // Handle server response (see Step 3)
                    result.json().then(function (json) {
                        handleServerResponse(json);
                    })
                });
                document.querySelector(".error").classList.remove('errorHide')
            }
        });
    });
</script>
</body>
</html>