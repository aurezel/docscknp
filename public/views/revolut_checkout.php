<?php
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
if ($country == 'US')
{
    $usStateAbbArr = array ('Alabama' => 'AL','Alaska' => 'AK','American Samoa' => 'AS','Arizona' => 'AZ','Arkansas' => 'AR','California' => 'CA','Colorado' => 'CO','Connecticut' => 'CT','Delaware' => 'DE','District Of Columbia' => 'DC','Federated States Of Micronesia' => 'FM','Florida' => 'FL','Georgia' => 'GA','Guam' => 'GU','Hawaii' => 'HI','Idaho' => 'ID','Illinois' => 'IL','Indiana' => 'IN','Iowa' => 'IA','Kansas' => 'KS','Kentucky' => 'KY','Louisiana' => 'LA','Maine' => 'ME','Marshall Islands' => 'MH','Maryland' => 'MD','Massachusetts' => 'MA','Michigan' => 'MI','Minnesota' => 'MN','Mississippi' => 'MS','Missouri' => 'MO','Montana' => 'MT','Nebraska' => 'NE','Nevada' => 'NV','New Hampshire' => 'NH','New Jersey' => 'NJ','New Mexico' => 'NM','New York' => 'NY','North Carolina' => 'NC','North Dakota' => 'ND','Northern Mariana Islands' => 'MP','Ohio' => 'OH','Oklahoma' => 'OK','Oregon' => 'OR','Palau' => 'PW','Pennsylvania' => 'PA','Puerto Rico' => 'PR','Rhode Island' => 'RI','South Carolina' => 'SC','South Dakota' => 'SD','Tennessee' => 'TN','Texas' => 'TX','Utah' => 'UT','Vermont' => 'VT','Virgin Islands' => 'VI','Virginia' => 'VA','Washington' => 'WA','West Virginia' => 'WV','Wisconsin' => 'WI','Wyoming' => 'WY');
    $abbState = $usStateAbbArr[$state] ?? $state;
}



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
    <script src="./static/js/jq.js"></script>
    <script>!function(e,o,n){var r=e=>{var n={sandbox:"https://sandbox-merchant.revolut.com/embed.js",prod:"https://merchant.revolut.com/embed.js",dev:"https://merchant.revolut.codes/embed.js"},r=o.createElement("script");return r.id="revolut-checkout",r.src=n[e]||n.prod,r.async=!0,o.head.appendChild(r),r},t=function(e,r){return{then:function(t,c){e.onload=function(){t(r())},e.onerror=function(){o.head.removeChild(e),c&&c(new Error(n+" failed to load"))}}}};e[n]=function(o,c){var u=t(r(c||"prod"),(function(){return e[n](o)}));return"function"==typeof Promise?Promise.resolve(u):u},e[n].payments=function(o){var c=t(r(o.mode||"prod"),(function(){return e[n].payments({locale:o.locale||"en",publicToken:o.publicToken||null})}));return"function"==typeof Promise?Promise.resolve(c):c}}(window,document,"RevolutCheckout");
    </script>
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
            <div style='display: flex;align-items: center;justify-content: center;padding: 10px 0px 20px;'>
                <img src="./static/images/payTitlebg.png" style="display: block;max-width:100%;"/>
            </div>
            <form>
                <div id="card_panel"></div>
                <button id="submits">
                    <div class="spinner hidden" id="spinner"></div>
                    <span id="button-text" >Pay</span><span id="order-amount"><?=$currency == 'USD' ?  " \${$amount}" : "";?></span>
                </button>
                <div style="display: block;text-align:center;color: red;height: 0;line-height:24px;transition: all 0.3s;overflow: hidden;" id="errorText"></div>
            </form>

            <div class="load-container load2 bbox_a">
                <div class="loader"></div>
            </div>
        </div>


    </div>
</div>

<script>
    // merchant supplies this data
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
            console.log(data);

            if (data.errcode === 0) {
                window.parent.postMessage("loadinged", "*");
                RevolutCheckout(data.data.public_id,"<?=get_env_value('local_env') ? 'sandbox' : 'prod';?>").then(function (instance) {
                    var form = document.querySelector("form");
                    var loadElem = $(".load-container.load2");
                    var card = instance.createCardField({
                        target: document.querySelector("[id=card_panel]"),
                        onSuccess() {
                            loadElem.hide()
                            showErrorMsg('Payment is successful and is jumping now...');
                            $("#errorText").css({
                                'height':'auto',
                                'color':'green'
                            });
                            loadElem.show();
                            window.parent.postMessage('succeeded', "*");
                        },
                        onError(message) {
                            console.log(message)
                            loadElem.hide();
                            showErrorMsg('Paid failed:'+message.message)
                            sendFailedCount();
                            return false;
                        },
                        oncancel(){
                            loadElem.hide();
                            showErrorMsg('Payment cancelled!')
                        },
                        locale: "en"
                    });

                    form.addEventListener("submit", function (event) {
                        // Prevent browser form submission. You need to submit card details first.
                        event.preventDefault();
                        loadElem.show()
                        card.submit({
                            name: "<?=$firstname . ' '. $lastname?>",
                            email: "<?=$email?>",
                            billingAddress: {
                                countryCode: '<?=$country;?>',
                                region: '<?=$abbState;?>',
                                city: '<?=$city;?>',
                                streetLine1: '<?=$address;?>',
                                streetLine2: '',
                                postcode: '<?=$zipCode;?>'
                            }
                        });
                    });
                });
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
        $("#errorText").text(msg);
        $("#errorText").css("height", height);
    }
</script>



</body>
</html>
