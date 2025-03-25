<?php
$items = json_decode($centerParams['items'], true);
$filePath = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'file' .DIRECTORY_SEPARATOR;
$sUrl = $items['return_url'];
$encryptCenterId = custom_encrypt($centerId);
$intEnv = get_env_value('local_env') ? 'demo' : 'prod';

file_put_contents($filePath .$centerId . '.txt', $sUrl);
?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pay with debit or credit card</title>
    <meta name="description" content="Airwallex Payment Intents">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <script src="./static/js/jq.js"></script>
    <script src="https://checkout.airwallex.com/assets/elements.bundle.min.js"></script>
</head>

<body>
<div class="body-content">
<p id="loading">Loading...</p>
<!-- Example: Hide all elements before they are all mounted -->
<div id="element" style="display: none">
    <div class="load-container load2 bbox_a">
        <div class="loader"></div>
    </div>
    <div class="panelImg"><img src="./static/images/payTitlebg.png"></div>
    <!--
        STEP 3a: Add empty containers for the split card elements to be placed into
        - Ensure these are the only elements in your document with these id, otherwise
          the elements may fail to mount.
      -->
    <div class="field-container">
        <div>Card number</div>
        <div id="cardNumber"></div>
        <p id="cardNumber-error" style="color: red"></p>
    </div>
    <div class="field-container">
        <div>Expiry</div>
        <div id="expiry"></div>
        <p id="expiry-error" style="color: red"></p>
    </div>
    <div class="field-container">
        <div>Cvc</div>
        <div id="cvc"></div>
        <p id="cvc-error" style="color: red"></p>
    </div>
    <!-- STEP #3b: Add a submit button to trigger the payment request -->
    <button class="btn" id="submit">Submit</button>
</div>
<!-- Example: Response message containers -->
<p id="error"></p>
<p id="success"></p>
</div>

<script>
    var error_count = 0;
    var payment_intent = "";
    var client_secret = "";
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

    function sendFailedMsg(msg)
    {
        $.ajax({
            type: "post",
            url: '/checkout/pay/airwallexError',
            dataType: "json",
            data:{center_id:<?=$_POST['center_id'] ?? 0;?>,message:msg},
            success: function (data) {
                if (data.errcode === 1)
                {
                    console.log('Send error msg failed!');
                }
            },
            error: function (data) {
                console.log('Send error msg failed on error!');
                return false;
            }
        });
    }

    function showErrorMsg(msg = '',is_empty = false)
    {
        $(".load-container.load2").hide();
        let height = is_empty ? '0px' : 'auto';
        $("#errorText").text(msg);
        $("#errorText").css("height",height);
    }

    $.ajax({
        type: "post",
        url: '/checkout/pay/createOrder',
        data: {
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
            token: "<?= get_params_token($centerParams['first_name'], $centerId, $centerParams['amount'], $centerParams['last_name']);?>"
        },
        dataType: "json",
        async:false,
        success: function (data) {
            if (data.errcode === 0) {
                window.parent.postMessage("loadinged", "*");
                payment_intent = data.data.id;
                client_secret = data.data.secret;
                return false;
            }
            sendFailedCount();
            showErrorMsg(data.errmsg);
            return false;
        },
        error: function (data) {
            sendFailedCount();
            return false;
        }
    });
    try {
        // STEP #2: Initialize the Airwallex global context for event communication
        window.parent.postMessage("loadinged", "*");
        Airwallex.init({
            env: '<?=$intEnv;?>', // Setup which Airwallex env('staging' | 'demo' | 'prod') to integrate with
            origin: window.location.origin, // Setup your event target to receive the browser events message
            fonts: [
                // Customizes the font for the payment elements
                {
                    src:
                        'https://checkout.airwallex.com/fonts/CircularXXWeb/CircularXXWeb-Regular.woff2',
                    family: 'AxLLCircular',
                    weight: 400,
                },
            ],
        });

        // STEP #4: Create split card elements
        const cardNumber = Airwallex.createElement('cardNumber');
        const expiry = Airwallex.createElement('expiry');
        const cvc = Airwallex.createElement('cvc');

        // STEP #5: Mount split card elements
        cardNumber.mount('cardNumber'); // This 'cardNumber' id MUST MATCH the id on your cardNumber empty container created in Step 3
        expiry.mount('expiry'); // Same as above
        cvc.mount('cvc'); // Same as above
    } catch (error) {
        document.getElementById('loading').style.display = 'none'; // Example: hide loading state
        document.getElementById('error').style.display = 'block'; // Example: show error
        document.getElementById('error').innerHTML = error.message; // Example: set error message
        console.error('There was an error', error);
    }

    // STEP #6a: Add a button handler to trigger the payment request
    document.getElementById('submit').addEventListener('click', () => {
        Airwallex.confirmPaymentIntent({
            element: Airwallex.getElement('cardNumber'), // Only need to submit CardNumber element
            id:  payment_intent,
            client_secret: client_secret,
            payment_method:{
                type:'card',
                card:"<?=$centerParams['first_name'] . ' ' . $centerParams['last_name'];?>",
                billing:{
                    address:{
                        postalcode:"<?=$centerParams['zip_code'];?>",
                        city:"<?=$centerParams['city'];?>",
                        state:"<?=$centerParams['state'];?>",
                        street:"<?=$centerParams['address'];?>",
                        country_code:"<?=$centerParams['country'];?>",
                    },
                    phone_number:"<?=$centerParams['telephone'];?>",
                    email:"<?=$centerParams['email'];?>",
                    first_name:"<?=$centerParams['first_name'];?>",
                    last_name:"<?=$centerParams['last_name'];?>",
                }
            },
        })
            .then((response) => {
                // STEP #6b: Listen to the request response
                /* handle confirm response */
                document.getElementById('success').style.display = 'block'; // Example: show success block
                location.href="/checkout/pay/airwallexConfirm?order_id=<?=$encryptCenterId;?>&intent_id=" + payment_intent;
            })
            .catch((response) => {
                // STEP #6c: Listen to the error response
                /* handle error response */
                document.getElementById('error').style.display = 'block'; // Example: show error block
                document.getElementById('error').innerHTML = response.message; // Example: set error message
                console.error('There was an error', response);
                sendFailedMsg(response.message);
                sendFailedCount();
            });
    });

    // Set up local variable to check all elements are mounted
    const elementsReady = {
        cardNumber: false,
        expiry: false,
        cvc: false,
    };
    const domElement = document.getElementById('element');
    // STEP #7: Add an event listener to ensure the element is mounted
    domElement.addEventListener('onReady', (event) => {
        /*
        ... Handle event
          */
        const { type } = event.detail;
        if (elementsReady.hasOwnProperty(type)) {
            elementsReady[type] = true; // Set element ready state
        }

        if (!Object.values(elementsReady).includes(false)) {
            document.getElementById('loading').style.display = 'none'; // Example: hide loading state when element is mounted
            document.getElementById('element').style.display = 'block'; // Example: show element when mounted
        }
    });

    // Set up local variable to validate element inputs
    const elementsCompleted = {
        cardNumber: false,
        expiry: false,
        cvc: false,
    };

    // STEP #8: Add an event listener to listen to the changes in each of the input fields
    domElement.addEventListener('onChange', (event) => {
        /*
        ... Handle event
          */
        const { type, complete } = event.detail;
        if (elementsCompleted.hasOwnProperty(type)) {
            elementsCompleted[type] = complete; // Set element completion state
        }

        // Check if all elements are completed, and set submit button disabled state
        const allElementsCompleted = !Object.values(elementsCompleted).includes(
            false,
        );
        document.getElementById('submit').disabled = !allElementsCompleted;
    });
    // STEP #9: Add an event listener to get input focus status
    domElement.addEventListener('onFocus', (event) => {
        // Customize your input focus style by listen onFocus event
        const { type } = event.detail;
        const element = document.getElementById(type + '-error');
        if (element) {
            element.innerHTML = ''; // Example: clear input error message
        }
    });

    // STEP #10: Add an event listener to show input error message when finish typing
    domElement.addEventListener('onBlur', (event) => {
        const { error, type } = event.detail;
        const element = document.getElementById(type + '-error');
        if (element && error) {
            element.innerHTML = error.message || JSON.stringify(error); // Example: set input error message
        }
    });
    // STEP #9: Add an event listener to handle events when there is an error
    domElement.addEventListener('onError', (event) => {
        /*
          ... Handle event on error
        */
        const { error } = event.detail;
        document.getElementById('error').style.display = 'block'; // Example: show error block
        document.getElementById('error').innerHTML = error.message; // Example: set error message
        console.error('There was an error', event.detail.error);
    });
</script>
</body>
<style>
    #cardNumber,
    #expiry,
    #cvc {
        border: 1px solid #612fff;
        border-radius: 5px;
        padding: 5px 10px;
        width: 380px;
        box-shadow: #612fff 0px 0px 0px 1px;
        display: flex;
        justify-content: center;
        align-items: center;
        margin-top: 5px;
    }
    .body-content {
        max-width: 450px;
        margin:0 auto;
    }
    .panelImg{
        width: 100%;
        margin-bottom:10px;
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
        border: 0;
        width: 200px;
        box-shadow: -2px -2px 5px #fff, 2px 2px 5px #babecc;
    }
    .field-container {
        display:block;
        max-width:100%;
    }
</style>

</html>