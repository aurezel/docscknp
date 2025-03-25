<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 首页
Route::rule('/','Index/index');
// 提交支付
Route::post('pay/submit','Pay/submit');
Route::post('pay/refund','Pay/refund');
Route::post('pay/createOrder', 'Pay/createOrder');
Route::post('pay/errorCount','Pay/errorCount');
Route::post('pay/keyError','Pay/publicKeyErrorDeal');


Route::rule('test','Index/test');
Route::post('pay/getAccountData','Pay/getAccountData');
// 更新配置文件
Route::post('accountUpdate','Index/accountUpdate');
Route::get('getAccountInfo','Index/getAccountInfo');
Route::post('coverSingleFile','Index/coverSingleFile');
Route::get('getConfigContent','Index/getConfigContent');
// 更新checkout目录
Route::post('updateCheckoutFile','Index/updateCheckoutFile');
// miss路由
Route::miss('Index/miss');
#poytnTest
Route::get('poytnTest','Index/test');

//Flutter回调
Route::get('pay/flutterRedirect','Flutter/flutterRedirect');
// Cycopay回调
Route::post('pay/cyWebhook','Cycopay/cyWebhook');
Route::rule('pay/cySuccess','Cycopay/cySuccess');
Route::rule('pay/cyFailure','Cycopay/cyFailure');

//Kinerjapay回调
Route::rule('pay/kpProcess','Kinerjapay/kpayProcess');
Route::rule('pay/kpSuccess','Kinerjapay/kpaySuccess');
Route::rule('pay/kpCancel','Kinerjapay/kpayCancel');

//rapyd回调
Route::rule('pay/rdWebhook','Rapyd/rapydWebhook');
Route::rule('pay/rdRedirect','Rapyd/rapydRedirect');
Route::post('pay/rdRefund','Rapyd/rapydRefund');
Route::post('pay/rdCapture','Rapyd/rapydCapturePayment');
Route::post('pay/rdCancel','Rapyd/rapydCancelPayment');
Route::rule('rapydPaymentPage','Rapyd/rapydPrivatePayment');

//airwallex回调
Route::rule('pay/airwallexSuccess','Airwallex/axSuccess');
Route::rule('pay/airwallexError','Airwallex/axError');
Route::rule('pay/airwallexConfirm','Airwallex/axConfirmation');

//Zenpay回调
Route::rule('pay/zenProcess','Zenpay/zenProcess');
Route::rule('pay/zenSuccess','Zenpay/zenSuccess');
Route::rule('pay/zenCancel','Zenpay/zenCancel');

//Payoneer回调
Route::rule('pay/payoneerProcess','Payoneerpay/payoneerProcess');
Route::rule('pay/payoneerSuccess','Payoneerpay/payoneerSuccess');
Route::rule('pay/payoneerCancel','Payoneerpay/payoneerCancel');
Route::rule('pay/payoneerHome','Payoneerpay/payoneerHome');

//mercadopago
Route::rule('pay/mercadoProcess','Mercadopago/mercadoProcess');

//Revolut webhook
Route::post('pay/revolutWebhook','Revolut/revolutWebhook');
Route::post('pay/revolutAddWebhook','Revolut/addWebhook');
Route::get('pay/revolutGetWebhookUrl','Revolut/getWebhookUrl');
// Vella webhook
Route::rule('pay/vellaWebhook','Vella/vellaWebhook');
Route::post('pay/vellaVerify','Vella/vellaVerify');

//stripe checkout
Route::get('pay/stckSuccess','StripeCheckout/payReturn');
Route::get('pay/stckCancel','StripeCheckout/payCancel');
Route::rule('pay/stckWebhook','StripeCheckout/webhook');
Route::post('pay/stckAddWebhook','StripeCheckout/addWebhook');
Route::get('pay/stckGetWebhookUrl','StripeCheckout/getWebhookUrl');
Route::get('pay/stckGetAccountStatus','StripeCheckout/getAccountStatus');

//Tazapay
Route::get('pay/tazaSuccess','Tazapay/payReturn');
Route::get('pay/tazaCancel','Tazapay/payCancel');
Route::rule('pay/tazaNotify','Tazapay/webhook');

// cloudPay 回调
Route::get('pay/cp3DReturn','CloudPay/cp3DReturn');
Route::post('pay/cpWebhook','CloudPay/cpWebhook');

// nuvei回调
Route::get('pay/nv3DReturn','Nuvei/nuvei3DReturn');
Route::post('pay/nvWebhook','Nuvei/nuveiWebhook');

// worldpay回调
Route::get('pay/wdpReturn','WorldPay/wdpReturn');
Route::rule('pay/wdpWebhook','WorldPay/wdpWebhook');

// eusiapay回调
Route::rule('pay/eusReturn','Eusiapay/eusReturn');
Route::post('pay/eusNotify','Eusiapay/eusNotify');

// stripe link
Route::get('pay/stlSuccess','StripeLink/payReturn');
Route::rule('pay/stlWebhook','StripeLink/webhook');

//stripe
Route::get('pay/stSuccess','Stripe/payReturn');

// paystack
Route::post('pay/pstkWebhook','Paystack/webhook');

//checkout beta
Route::rule('pay/stcbWebhook','CheckoutBeta/webhook');

//alipay
Route::rule('pay/aliNotify','Alipay/webhook');
Route::rule('pay/aliRedirect','Alipay/redirect');
Route::post('pay/aliRefund','Alipay/refund');
Route::post('pay/aliRefundNotify','Alipay/refundNotify');
Route::post('pay/aliInquiryRefund','Alipay/inquiryRefund');
Route::post('pay/aliInquiryPayment','Alipay/inquiryPayment');
Route::post('pay/aliCapture','Alipay/capture');
Route::post('pay/aliCancel','Alipay/cancel');

//useepay
Route::rule('pay/uSeeRedirect','Useepay/redirect');
Route::rule('pay/uSeeNotify','Useepay/webhook');
Route::post('pay/uSeeConfirm','Useepay/confirmResult');
Route::post('pay/uSeeThreeDSComplete','Useepay/threeDSMethodCompletionMethod');

//xpay
Route::rule('pay/xNotify','Xpay/webhook');
Route::rule('pay/xRedirect','Xpay/redirect');

Route::get('pay/checkConfig','CheckConfig/index');
return [

];
