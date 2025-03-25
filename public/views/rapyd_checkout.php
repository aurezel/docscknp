<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/8/1
 * Time: 10:08
 */

$items = json_decode($centerParams['items'], true);
$filePath = dirname(dirname(dirname(__FILE__))) . DIRECTORY_SEPARATOR . 'file' .DIRECTORY_SEPARATOR;
$sUrl = $items['return_url'];
$fParams = parse_url($sUrl);
$isIframePay = intval(get_env_value('merchant_token'));
$iframeScript = get_env_value('local_env') ? 'https://sandboxcheckouttoolkit.rapyd.net' : 'https://checkouttoolkit.rapyd.net';


file_put_contents($filePath .$centerId . '.txt', json_encode(
    [
        'f_url' => $fParams['scheme'] . '://' . $fParams['host'],
        's_url' => $sUrl
    ]
));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pay with debit or credit card</title>
    <meta name="description" content="A demo of Stripe Payment Intents">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="./static/js/jq.js"></script>
    <?php
    if ($isIframePay) {?>
        <script src="<?=$iframeScript?>"></script>
    <?php } ?>
</head>
<body <?php if ($isIframePay){?>
    style="background-color: #f1f1f1; display: flex; align-items: center; flex-direction: column; margin: 0"
<?php }?>
>
<?php
if ($isIframePay) {?>
    <div style="width: 500px;" id="rapyd-checkout" ></div>
<?php } ?>
<span style="display: block;color: red;height: 0;line-height:24px;transition: all 0.3s;overflow: hidden;"
      id="errorText"></span>
<script>
    // merchant supplies this data
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
        success: function (data) {
            if (data.errcode === 0) {
                <?php
                if ($isIframePay) {?>
                let checkout = new RapydCheckoutToolkit({
                    pay_button_text: "Pay",
                    pay_button_color: "blue",
                    id: data.data,
                    close_on_complete:false,
                });
                checkout.displayCheckout();

                let isSuccessLoading = false;
                window.addEventListener('onCheckoutPaymentSuccess', function (event) {
                    isSuccessLoading = true;
                    window.parent.postMessage("succeeded","*");
                    $(".load-container.load2").show();
                    showErrorMsg('Payment is successful and is jumping now...');
                    $("#errorText").css({
                        'height':'auto',
                        'color':'green'
                    });
                });
                window.addEventListener('onLoading', (event) => {
                    if (event.detail.loading === false)
                    {
                        if (!isSuccessLoading) showErrorMsg();
                        window.parent.postMessage("loadinged", "*");
                    }else{
                        showErrorMsg('Page Loading...');
                        $("#errorText").css({
                            'height':'auto',
                            'color':'green'
                        });
                    }
                })
                window.addEventListener('onCheckoutPaymentFailure', function (event) {
                    sendFailedCount();
                    sendFailedMsg(event.detail.error)
                });
                <?php }else{ ?>
                window.parent.postMessage("loadinged", "*");
                window.parent.postMessage(data.data, "*");
                <?php }?>
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

    function showErrorMsg(msg = '', is_empty = false) {
        let height = is_empty ? '0px' : 'auto';
        $("#errorText").text(msg).css("height", height);
    }
</script>
</body>
</html>
