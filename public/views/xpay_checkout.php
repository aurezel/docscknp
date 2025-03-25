
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Pay with debit or credit card</title>
    <meta name="description" content="A demo of Stripe Payment Intents">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <style>
        #errorText{
            display: flex;
            max-width: 1000px;
            justify-content: center;
            text-align: center;
            margin: 0 auto;
            color: red;
        }

        .hidden{
            display: none;
        }
        #payment-block{display: none}
    </style>
    <script src="./static/js/jq.js"></script>
</head>

<body marginwidth="0" marginheight="0">
<div id="payment-block"></div>
<div id="errorText" class="hidden"></div>
<script>
    var center_id = "<?=$_POST['center_id'] ?? 0;?>";
    var address_line1 = "<?=$centerParams['address'];?>";
    var address_line2 = '';
    var address_city = "<?=$centerParams['city'];?>";
    var address_state = "<?=$centerParams['state'];?>";
    var address_zip = "<?=$centerParams['zip_code'];?>";
    var address_country = "<?=$centerParams['country'];?>";
    var email = "<?=$centerParams['email'];?>";
    var phone = "<?=$centerParams['telephone'];?>";
    var name = "<?=$centerParams['first_name'] . ' ' . $centerParams['last_name'];?>";
    var first_name = "<?=$centerParams['first_name'];?>";
    var last_name = "<?=$centerParams['last_name'];?>";
    var amount = "<?=$centerParams['amount'];?>";
    var currency = "<?=$centerParams['currency'];?>";
    var order_no = "<?=$centerParams['order_no'];?>";
    var token = "<?= get_params_token($centerParams['first_name'], $_POST['center_id'], $centerParams['amount'], $centerParams['last_name']);?>";
    $("#errorText").css({
        'height':'auto',
        'color':'green'
    });
   showErrorMsg('Loading...');
    $.ajax({
        url: '/checkout/pay/createOrder',
        method: 'POST',
        dataType: 'json',
        data: {
            address1: address_line1,
            address2: address_line2,
            city: address_city,
            state: address_state,
            zip: address_zip,
            country: address_country,
            name: name,
            phone:phone,
            email:email,
            order_no:order_no,
            first_name: first_name,
            last_name: last_name,
            amount: amount,
            currency: currency,
            center_id: center_id,
            token: token
        },

        success(res) {
            if (res.errcode === 0) {
                if (res.data.html)
                {
                    $( "#payment-block" ).append(res.data.html);
                    window.parent.postMessage("loadinged", "*");
                    $('#payment-form').submit();
                    return false;
                }
                showErrorMsg('Response Error!');
                return false;
            } else {
                // error count
                sendFailedCount();
                showErrorMsg(res.errmsg);
                return false;
            }
        },
        error(res) {
            sendFailedCount();
            showErrorMsg(res.errmsg);
            return false;
        }
    });

    var error_count = 0;

    function sendFailedCount(center_id) {
        error_count++;
        if (error_count >= 2) {
            $.ajax({
                type: "post",
                url: '/checkout/pay/errorCount',
                dataType: "json",
                data: {center_id: center_id},
                success: function (data) {
                    if (data.errcode === 1) {
                        console.log('set error failed!');
                    }
                    window.parent.postMessage("risky", "*");
                },
                error: function (data) {
                    console.log('set error failed');
                }
            });
        }
    }

    function showErrorMsg(msg = '', is_empty = false) {
        let height = is_empty ? '0px' : 'auto';
        $("#errorText").text(msg).css("height", height).show();
    }
</script>
</body>
</html>