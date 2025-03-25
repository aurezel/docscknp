<html xmlns="https://www.w3.org/1999/xhtml" dir="ltr" xml:lang="en" lang="en" xmlns="https://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Credit Card Payment Gateway</title>
    <meta http-equiv="X-UA-Compatible" content="IE-Edge,chrome">
    <meta name="viewport"
          content="width=device-width,initial-scale=1.0,maximum-scale=1.0, user-scalable=no, minimal-ui">
    <script type="text/javascript" src="./static/js/jq.js" charset="UTF-8"></script>

    <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css">
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <script src="https://www.mercadopago.com/v2/security.js" view="checkout" output="deviceId"></script>
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

        main {
            margin: 4px 0 0px 0;
            background-color: #f6f6f6;
            min-height: 90%;
            padding-bottom: 100px;
        }

        .hidden {
            display: none
        }

        .h-40 {
            height: 40px;
        }

        .payment-form {
            padding-bottom: 10px;
            margin-right: 15px;
            margin-left: 15px;
            font-family: "Helvetica Neue",Helvetica,sans-serif;
        }

        .payment-form.dark {
            background-color: #f6f6f6;
        }

        .payment-form .panelImg{
            width: 100%;
            margin-bottom:10px;
        }
        .payment-form .panelImg img{
            width: 100%;
        }
        .payment-form .loading img{
            width: 4rem;
            height: 4rem;
            margin-top: -95%;
            margin-left: -9%;
            z-index: 1000;
            position: fixed;
            display: none;
        }
        .payment-form .form-payment {
            border-top: 2px solid #C6E9FA;
            box-shadow: 0px 2px 10px rgba(0, 0, 0, 0.075);
            background-color: #ffffff;
            padding: 0;
            max-width: 600px;
            margin: auto;
        }

        .payment-form .payment-details {
            padding: 25px 25px 15px;
            height: 100%;
        }

        .payment-form .payment-details label {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 15px;
            color: #8C8C8C;
            text-transform: uppercase;
        }

        .payment-form .payment-details button {
            margin-top: 0.6em;
            padding: 12px 0;
            font-weight: 500;
            background-color: #009EE3;
            margin-bottom: 10px;
        }

        .payment-form a, .payment-form a:not([href]) {
            margin: 0;
            padding: 0;
            font-size: 13px;
            color: #009ee3;
            cursor:pointer;
        }

        .payment-form a:not([href]):hover{
            color: #3483FA;
            cursor:pointer;
        }

        @media (min-width: 576px) {
            .payment-form .payment-details {
                padding: 40px 40px 30px;
            }
            .payment-form .payment-details button {
                margin-top: 1em;
                margin-bottom: 15px;
            }
        }
    </style>
</head>

<body marginwidth="0" marginheight="0">

<section class="payment-form dark">
    <div class="container__payment">
        <div class="form-payment">
            <div class="load-container load2 bbox_a">
                <div class="loader"></div>
            </div>
            <div class="payment-details">
                <div class="panelImg" style="margin: 14px auto 24px;display: block;max-width:100%"><img src="./static/images/payTitlebg.png"></div>
                <form id="form-checkout">
                    <div class="row">
                        <div class="form-group col-sm-8 hidden">
                            <input id="form-checkout__cardholderEmail" name="cardholderEmail" type="email" value="<?= $centerParams['email']?>" class="form-control" />
                        </div>
                        <input type="hidden" id="deviceId">
                        <div class="form-group col-sm-8">
                            <div id="form-checkout__cardNumber" class="form-control h-40"></div>
                        </div>
                        <div class="form-group col-sm-4">
                            <div class="input-group expiration-date">
                                <div id="form-checkout__expirationDate" class="form-control h-40" ></div>
                            </div>
                        </div>
                        <div class="form-group col-sm-8">
                            <input id="form-checkout__cardholderName" name="cardholderName" type="text" class="form-control" value="<?=$centerParams['first_name'] . ' ' . $centerParams['last_name'];?>" />
                        </div>
                        <div class="form-group col-sm-4">
                            <div id="form-checkout__securityCode" class="form-control h-40"></div>
                        </div>
                        <div id="issuerInput" class="form-group col-sm-12 hidden">
                            <select id="form-checkout__issuer" name="issuer" class="form-control"></select>
                        </div>
                        <div class="form-group col-sm-12">
                            <select id="form-checkout__installments" name="installments" class="form-control"></select>
                        </div>
                        <div class="form-group col-sm-12">
                            <input type="hidden" id="amount" />
                            <div style="display: block;color: red;height: 0;line-height:24px;transition: all 0.3s;overflow: hidden;" id="errorText"></div>
                            <button id="form-checkout__submit" type="submit" class="btn btn-primary btn-block">Pay</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<script>
    window.parent.postMessage("loadinged", "*");
    var $ = jQuery;
    var error_count = 0;
    const publicKey = '<?=get_env_value('public_key');?>';
    const mercadopago = new MercadoPago(publicKey,{ locale: 'en-US'});

    function loadCardForm() {
        const payButton = document.getElementById("form-checkout__submit");
        const form = {
            id: "form-checkout",
            cardholderName: {
                id: "form-checkout__cardholderName",
                placeholder: "Holder name",
            },
            cardholderEmail: {
                id: "form-checkout__cardholderEmail",
                placeholder: "E-mail",
            },
            cardNumber: {
                id: "form-checkout__cardNumber",
                placeholder: "Card number",
                style: {
                    fontSize: "1rem"
                },
            },
            expirationDate: {
                id: "form-checkout__expirationDate",
                placeholder: "MM/YYYY",
                style: {
                    fontSize: "1rem"
                },
            },
            securityCode: {
                id: "form-checkout__securityCode",
                placeholder: "Security code",
                style: {
                    fontSize: "1rem"
                },
            },
            installments: {
                id: "form-checkout__installments",
                placeholder: "Installments",
            },
            issuer: {
                id: "form-checkout__issuer",
                placeholder: "Issuer",
            },
            deviceId:{
                id:"deviceId"
            }
        };

        const cardForm = mercadopago.cardForm({
            amount: '<?=$centerParams['amount'];?>',
            iframe: true,
            form,
            callbacks: {
                onFormMounted: error => {
                    if (error)
                        return console.warn("Form Mounted handling error: ", error);
                },
                onSubmit: event => {
                    event.preventDefault();
                    $(".load-container.load2").show()
                    const {
                        paymentMethodId,
                        issuerId,
                        cardholderEmail: email,
                        token,
                        installments,
                    } = cardForm.getCardFormData();

                    fetch("/checkout/pay/createOrder", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify({
                            issuerId,
                            paymentMethodId,
                            installments: Number(installments),
                            payer: {
                                email,
                                identification: {},
                            },
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
                            pay_token:token,
                            deviceId:$("#deviceId").val(),
                            token:"<?= get_params_token($centerParams['first_name'],$centerId,$centerParams['amount'],$centerParams['last_name']);?>"
                        }),
                    })
                        .then(response => {
                            console.log('response:',response)
                            return response.json();
                        })
                        .then(result => {
                            console.log('result:',result)
                            $(".load-container.load2").hide()
                            if (result.errcode === 0)
                            {
                                if (result.data.success_risky)
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
                                sendFailedCount();
                                showErrorMsg(result.errmsg);
                            }
                        })
                        .catch(error => {
                            console.log('error',error);
                            $(".load-container.load2").hide();
                            sendFailedCount();
                            showErrorMsg(JSON.stringify(error));
                            payButton.removeAttribute("disabled");
                        });
                },
                onFetching: (resource) => {
                    payButton.setAttribute('disabled', true);
                    return () => {
                        payButton.removeAttribute("disabled");
                    };
                },
                onCardTokenReceived: (errorData, token) => {
                    if (errorData) {
                        let msg = errorData.pop().message;
                        showErrorMsg(msg)
                        return false;
                    }
                    return token;
                },
                onValidityChange: (error, field) => {
                    const input = document.getElementById(form[field].id);
                    showErrorMsg('');
                    addFieldErrorMessages(input, error);
                    enableOrDisablePayButton(payButton);
                }
            },
        });
    };

    function showErrorMsg(msg='',is_empty = false)
    {
        $(".load-container.load2").hide();
        let height = is_empty ? '0px' : 'auto';
        $("#errorText").text(msg).css("height",height);

    }

    function addFieldErrorMessages(input, error) {
        if (error) {
            error.forEach((e, index) => {
                showErrorMsg(e.message)
                return false;
            });
        } else {
            showErrorMsg('');
        }
    }

    function enableOrDisablePayButton(payButton) {
        if ($("#errorText").text().length > 0) {
            payButton.setAttribute('disabled', true);
        } else {
            payButton.removeAttribute('disabled');
        }
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
    loadCardForm();
</script>
</body>
</html>