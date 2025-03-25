<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2021/12/27
 * Time: 15:49
 */
error_reporting(0);
if (strtoupper($_SERVER['REQUEST_METHOD']) !== 'POST' || !checkParams()) die('Illegal Access');
function checkParams()
{
    if (isset($_POST['result_json'])) return true;
    return isset($_POST['center_id']);
}
?>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Loading...</title>
    <meta name="description" content="A demo of Stripe Payment Intents">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
</head>

<body marginwidth="0" marginheight="0">
<form name="checkout" id="checkout_now" action="checkout.php" method="post">
    <?
    foreach ($_POST as $key => $val)
    {?>
        <input name="<?=$key?>" type="hidden" value="<?=$val?>"/>
    <?}
    ?>
</form>
<script>
    document.getElementById("checkout_now").submit();
</script>
</body>
</html>
