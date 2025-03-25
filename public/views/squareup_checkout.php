<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/5/24
 * Time: 16:26
 */
?>

<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Credit Card Payment Gateway</title>
    <meta http-equiv="X-UA-Compatible" content="IE-Edge,chrome">
    <meta name="viewport"
          content="width=device-width,initial-scale=1.0,maximum-scale=1.0, user-scalable=no, minimal-ui">
    <link href="./static/css/app.css" rel="stylesheet" />
    <script type="text/javascript" src="<?= get_env_value('local_env') ? 'https://sandbox.web.squarecdn.com/v1/square.js' : 'https://web.squarecdn.com/v1/square.js';?>"></script>
    <script type="text/javascript" src="./static/js/jq.js" charset="UTF-8"></script>
    <script>
        window.parent.postMessage("loadinged", "*");
        // transparent
        const darkModeCardStyle = {
            '.input-container': {
                borderColor: '#ddd',
            },
            '.input-container.is-focus': {
                borderColor: '#ddd',
            },
            '.input-container.is-error': {
                borderColor: '#ddd',
            },
            '.message-text': {
                color: '#fff',
            },
            '.message-icon': {
                color: '#fff',
            },
            '.message-text.is-error': {
                color: 'red',
            },
            '.message-icon.is-error': {
                color: 'red',
            },
            input: {
                backgroundColor: 'transparent',
                color: '#FFFFFF',
                fontFamily: 'helvetica neue, sans-serif',
            },
            'input::placeholder': {
                color: '#fff',
            },
            'input.is-error': {
                color: 'red',
            },
            '@media screen and (max-width: 600px)': {
                'input': {
                    'fontSize': '12px',
                }
            }
        };

        const locationId = "<?= get_env_value('public_key');?>";
        const appId = "<?= get_env_value('private_key');?>";
        async function initializeCard(payments) {
            const card = await payments.card({
                style: darkModeCardStyle,
            });
            await card.attach('#card-container');
            return card;
        }

        async function createPayment(token) {

            const body = JSON.stringify({
                locationId,
                sourceId: token,
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
            });

            const paymentResponse = await fetch('/checkout/pay/createOrder', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body,
            });
            if (paymentResponse.ok) {
                return paymentResponse.json();
            }

            const errorBody = await paymentResponse.text();
            throw new Error(errorBody);
        }
        //get token
        async function tokenize(paymentMethod) {
            const tokenResult = await paymentMethod.tokenize();
            if (tokenResult.status === 'OK') {
                return tokenResult.token;
            } else {
                let errorMessage = `Tokenization failed with status: ${tokenResult.status}`;
                if (tokenResult.errors) {
                    errorMessage += ` and errors: ${JSON.stringify(
                        tokenResult.errors
                    )}`;
                }

                throw new Error(errorMessage);
            }
        }


        // status is either SUCCESS or FAILURE;
        function displayPaymentResults(status) {
            const statusContainer = document.getElementById(
                'payment-status-container'
            );
            if (status === 'SUCCESS') {
                statusContainer.classList.remove('is-failure');
                statusContainer.classList.add('is-success');
            } else {
                statusContainer.classList.remove('is-success');
                statusContainer.classList.add('is-failure');
            }

            statusContainer.style.visibility = 'visible';
        }

        var error_count = 0;
        var $ = jQuery;
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

        document.addEventListener('DOMContentLoaded', async function () {
            if (!window.Square) {
                throw new Error('Square.js failed to load properly');
            }

            let payments;
            try {
                payments = window.Square.payments(appId, locationId);
            } catch {
                const statusContainer = document.getElementById(
                    'payment-status-container'
                );
                statusContainer.className = 'missing-credentials';
                statusContainer.style.visibility = 'visible';
                return;
            }

            let card;
            try {
                card = await initializeCard(payments);
            } catch (e) {
                console.error('Initializing Card failed', e);
                return;
            }

            // Checkpoint
            async function handlePaymentMethodSubmission(event, paymentMethod) {
                event.preventDefault();

                try {
                    cardButton.disabled = true;
                    const token = await tokenize(paymentMethod);
                    const paymentResults = await createPayment(token);
                    if (paymentResults.errcode === 0)
                    {
                        if (paymentResults.data.success_risky)
                        {
                            displayPaymentResults('FAILURE');
                            window.parent.postMessage("success_risky","*");
                            return false;
                        }
                        displayPaymentResults('SUCCESS');
                        window.parent.postMessage("succeeded","*");
                    }else{
                        cardButton.disabled = false;
                        sendFailedCount();
                        displayPaymentResults('FAILURE');
                    }

                } catch (e) {
                    cardButton.disabled = false;
                    displayPaymentResults('FAILURE');
                    sendFailedCount();
                }
            }

            const cardButton = document.getElementById('card-button');
            cardButton.addEventListener('click', async function (event) {
                await handlePaymentMethodSubmission(event, card);
            });
        });

    </script>
</head>
<body>
<form id="payment-form">
    <img src="./static/images/payTitlebg.png" style="width:100%;margin-bottom:-16px;">
    <div id="card-container"></div>
    <button id="card-button" type="button">Pay</button>
    <div id="payment-status-container"></div>
</form>

</body>
<style>
    #payment-form{width:280px;min-height:350px;}
    #card-container{
        padding: 30px 20px 0;
        background-image: linear-gradient(to top right,#47a5e2, #b5deff);
        border-radius: 12px;
        position: relative;
    }
    #card-button{
        border-radius: 50px;
        color: #f4f4f4;
        background-image: linear-gradient(to right,#75c2ff, #45a3e0);
        transition: all 0.15s ease-in-out;
        margin: 20px auto 20px;
        display: block;
        font-weight: 600;
        cursor: pointer;
        padding: 12px 16px;
        font-size: 1rem;
        border: 0;
        width: 100%;
        box-shadow: -2px -2px 5px #fff, 2px 2px 5px #babecc;
    }
    #card-button:active {
        box-shadow: inset 1px 1px 2px #babecc, inset -1px -1px 2px #fff;
        filter: none;
        transform: none;
    }

    html{height:100%}
    body{height:100%;display: flex;justify-content: center;align-items: center;}
</style>
</html>
