<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/11/7
 * Time: 8:44
 */

return [
    'html' => <<<EOF
<head>
    <meta charset="utf-8">
    <title>Pay Success</title>
    <meta name="viewport"
          content="width=device-width,initial-scale=1.0,maximum-scale=1.0, user-scalable=no, minimal-ui">
</head>
<body>
<p style="color: red;text-align: center;">Payment success! Redirecting you to home page...</p>
<script>
var delay = 2;
function jumpToHome()
{
        var t = setTimeout('jumpToHome()', 1000);
        if (delay > 0) {
            delay--;
        } else {
            clearTimeout(t);
            window.location.href = "%s";
        }
        
}
jumpToHome();
</script>
</body>
EOF
];