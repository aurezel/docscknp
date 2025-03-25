<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2023/11/7
 * Time: 8:44
 */

return [
    'success' => <<<EOF
<html xmlns="http://www.w3.org/1999/xhtml"><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=0.5, maximum-scale=2.0, user-scalable=yes">
        
        <title>Confirm Order</title>

        <style type="text/css">
            .silder_width {
                margin: 0 auto
            }

            @media screen and (min-width: 1201px) {
                .silder_width {
                    width: 1000px
                }
            }

            @media screen and (max-width: 1200px) {
                .silder_width {
                    width: 900px
                }
            }

            @media screen and (max-width: 900px) {
                .silder_width {
                    width: 200px;
                }
            }

            @media screen and (max-width: 500px) {
                .silder_width {
                    width: 100%%;
                }
            }

            body {
                font: 12 rpx/1.2 Arial, Helvetica, sans;
                font-size: 12 rpx;
                font-weight: normal;
                font-style: normal;
            }

            body,
            h1,
            h3,
            p,
            ul,
            li,
            cite {
                margin: 0;
                padding: 10;
            }


            p {
                margin: 50 rpx auto;
                line-height: 1.5em;
            }

            span {
                color: #F00;
            }

            strong {
                color: #090;
            }

            em {
                color: #06C;
                font-style: normal;
                text-decoration: underline;
            }

            a {
                text-decoration: underline;
                color: #06C;
            }

            a:hover {
                text-decoration: underline;
            }

            ul {
                margin-left: 1.5em;
            }

            li {
                list-style: square;
                line-height: 24 rpx;
            }
        </style>
    <style type="text/css">#_copy{align-items:center;background:#4494d5;border-radius:3px;color:#fff;cursor:pointer;display:flex;font-size:13px;height:30px;justify-content:center;position:absolute;width:60px;z-index:1000}#select-tooltip,#sfModal,.modal-backdrop,div[id^=reader-helper]{display:none!important}.modal-open{overflow:auto!important}._sf_adjust_body{padding-right:0!important}.super_copy_btns_div{position:fixed;width:154px;left:10px;top:45%%;background:#e7f1ff;border:2px solid #4595d5;font-weight:600;border-radius:2px;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,PingFang SC,Hiragino Sans GB,Microsoft YaHei,Helvetica Neue,Helvetica,Arial,sans-serif,Apple Color Emoji,Segoe UI Emoji,Segoe UI Symbol;z-index:5000}.super_copy_btns_logo{width:100%%;background:#4595d5;text-align:center;font-size:12px;color:#e7f1ff;line-height:30px;height:30px}.super_copy_btns_btn{display:block;width:128px;height:28px;background:#7f5711;border-radius:4px;color:#fff;font-size:12px;border:0;outline:0;margin:8px auto;font-weight:700;cursor:pointer;opacity:.9}.super_copy_btns_btn:hover{opacity:.8}.super_copy_btns_btn:active{opacity:1}</style><input type="hidden" id="_w_simile" data-inspect-config="3"><script type="text/javascript" src="chrome-extension://dbjbempljhcmhlfpfacalomonjpalpko/scripts/inspector.js"></script></head>
    <body>
    <div class="silder_width">
        <h2 style="font-size: 24rpx;font-weight: bold;height: 186rpx;line-height: 3.5em;background-color: #007cba;color: #FFFFFF;">
            &nbsp;&nbsp; </h2>
                <div align="center">
            <img src="data:image/png;base64,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">
        </div>
        <h2 align="center" style="font-weight: bold;">Thanks For Shopping With Us! </h2>
        <p align="center" style="font-size:14rpx;">You will receive your goods in about 7 to 15 days</p>
                <table cellpadding="0" cellspacing="0" class="x_stylingblock-content-wrapper" role="presentation" style="min-width:100%%" width="100%%">
            <tbody>
            <tr>
                <td class="x_stylingblock-content-wrapper x_camarker-inner">
                    <div style="font:8px Monospace"></div>
                    <table border="0" cellpadding="0" cellspacing="0" width="100%%">
                        <tbody>
                        <tr>
                            <td class="x_pad20" style="padding:0px 40px 20px 40px">
                                <table border="0" cellpadding="0" cellspacing="0" width="100%%">
                                    <tbody>
                                    <tr>
                                        <td style="border:1px solid #D2CFD3; border-radius:3px">
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%%">
                                                <tbody>
                                                <tr>
                                                    <td style="font-family:arial,helvetica,sans-serif; font-size:18px; line-height:22px; font-weight:bold; padding:20px; color:#303030; border-bottom:1px solid #D2CFD3; background-color:#f5f5f5">
                                                        Shipping
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td style="padding:20px; background-color:#f5f5f5; font-family:arial,helvetica,sans-serif; font-size:14px; line-height:24px; color:#303030">
                                                        <div style="font:10px Monospace"></div>
                                                        %s
                                                        <div style="font:10px Monospace"></div>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="x_appleLinksGray4" style="padding:0px 20px 20px; background-color:#f5f5f5; font-family:arial,helvetica,sans-serif; font-size:14px; line-height:26px; color:#303030">
                                                        Ship To: <br>
                                                        %s</td>
                                                </tr>
                                                <tr>
                                                    <td style="background-color:#f5f5f5; padding:0px 20px 20px">
                                                        <div style="font:10px Monospace"></div>
                                                    </td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <table cellpadding="0" cellspacing="0" class="x_stylingblock-content-wrapper" role="presentation" style="background-color:transparent; min-width:100%%; border-top:1px solid #D2CFD3; border-right:0px; border-bottom:0px; border-left:0px" width="100%%">
                        <tbody>
                        <tr>
                            <td class="x_stylingblock-content-wrapper x_camarker-inner" style="padding:0px">
                                <table align="left" border="0" cellpadding="0" cellspacing="0" width="100%%">
                                    <tbody>
                                    <tr>
                                        <td class="x_pad20" style="text-align:left; font-size:24px; line-height:26px; color:#27251F; font-family:arial,helvetica,sans-serif; padding-top:40px; padding-left:40px">
                                            Billing Information
                                        </td>
                                    </tr>
                                    <tr>
                                        <td align="left" style="">
                                            <table align="left" border="0" cellpadding="0" cellspacing="0" class="x_container" width="90%%">
                                                <tbody>
                                                <tr>
                                                    <td align="left" class="x_noBorder x_block" style="" valign="top" width="50%%">
                                                        <table border="0" cellpadding="0" cellspacing="0" width="100%%">
                                                            <tbody>
                                                            <tr>
                                                                <td align="left" class="x_padBot10 x_pad20" style="padding:30px 50px 40px 40px">
                                                                    <table align="left" border="0" cellpadding="0" cellspacing="0">
                                                                        <tbody>
                                                                        <tr>
                                                                            <td align="left" style="font-size:14px; line-height:22px; text-align:left; color:#27251F; font-family:arial,helvetica,sans-serif; font-weight:bold">
                                                                                Billing Address
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td align="left" class="x_appleLinksBlack" style="font-size:14px; line-height:22px; text-align:left; color:#27251F; font-family:arial,helvetica,sans-serif; padding-top:10px">
                                                                                %s</td>
                                                                        </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                            </tbody>
                                                        </table>
                                                    </td>
                                                    <td class="x_noBorder x_block" style="" valign="top">
                                                        <table border="0" cellpadding="0" cellspacing="0" width="100%%">
                                                            <tbody>
                                                            <tr>
                                                                <td class="x_pad20" style="padding:30px 50px 40px 50px">
                                                                    <table align="left" border="0" cellpadding="0" cellspacing="0">
                                                                        <tbody>
                                                                        <tr>
                                                                            <td align="left" style="font-size:14px; line-height:22px; text-align:left; color:#27251F; font-family:arial,helvetica,sans-serif; font-weight:bold">
                                                                                Payment Store
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td align="left" style="font-size:14px; line-height:22px; text-align:left; color:#27251F; font-family:arial,helvetica,sans-serif; padding-top:10px">
                                                                                %s
                                                                                <br>
                                                                            </td>
                                                                        </tr>
                                                                        </tbody>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                            </tbody>
                                                        </table>
                                                    </td>
                                                </tr>
                                                </tbody>
                                            </table>
                                        </td>
                                    </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                    <h2 style="font-size: 24rpx;font-weight: bold;height: 186rpx;line-height: 3.5em;background-color: #007cba;color: #FFFFFF;">
                        &nbsp; </h2>
    
    
    
    </td></tr></tbody></table></div></body></html>
EOF
];