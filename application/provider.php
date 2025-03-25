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

// 应用容器绑定定义
namespace app\index\service;

return [
    'v2' => PlaceV2OrderService::class,
    'v3' => PlaceV3OrderService::class,
    'connect' => PlaceV3OrderService::class,
    'authorize' => PlaceAuthorizeOrderService::class,
    'first_data' => PlaceFirstDataOrderService::class,
    'maverick' => PlaceMaverickOrderService::class,
    'nmi' => PlaceMaverickOrderService::class,
    'clover' => PlaceCloverOrderService::class,
    'valor' => PlaceValorOrderService::class,
    'elavon' => PlaceElavonOrderService::class,
    'flutter' => PlaceFlutterOrderService::class,
    'braintree' => PlaceBraintreeOrderService::class,
    'poynt' => PlacePoyntOrderService::class,
    'wepay' => PlaceWePayOrderService::class,
    'heartland' => PlaceHeartLandOrderService::class,
    'squareup' => PlaceSquareUpOrderService::class,
    'cycopay' => PlaceCycopayOrderService::class,
    'kinerjapay' => PlaceKinerjapayOrderService::class,
    'rapyd' => PlaceRapydOrderService::class,
    'rapyd_api' => PlaceRapydApiOrderService::class,
    'rapyd_capture' => PlaceRapydCaptureOrderService::class,
    'airwallex' => PlaceAirwallexOrderService::class,
    'simplify' => PlaceSimplifyOrderService::class,
    'mercadopago' => PlaceMercadopagoOrderService::class,
    'zen' => PlaceZenOrderService::class,
    'payoneer' => PlacePayoneerOrderService::class,
    'revolut' => PlaceRevolutOrderService::class,
    'vella' => PlaceVellaOrderService::class,
    'stripe_checkout' => PlaceStripeCheckoutOrderService::class,
    'cloudpay' => PlaceCloudPayOrderService::class,
    'nuvei' => PlaceNuveiOrderService::class,
    'worldpay' => PlaceWorldPayOrderService::class,
    'tazapay' => PlaceTazapayOrderService::class,
    'eusiapay' => PlaceEusiapayOrderService::class,
    'stripe_link' => PlaceStripeLinkOrderService::class,
    'paystack' => PlacePaystackOrderService::class,
    'checkout_beta' => PlaceCheckoutBetaOrderService::class,
    'st_checkout_price' => PlaceStripeCheckoutPriceOrderService::class,
    'alipay' => PlaceAlipayOrderService::class,
    'alipay_capture' => PlaceAlipayCaptureOrderService::class,
    'alipay_checkout' => PlaceAlipayCheckoutOrderService::class,
    'useepay' => PlaceUseepayOrderService::class,
    'useepay_api' => PlaceUseepayApiOrderService::class,
    'xpay' => PlaceXpayOrderService::class
];
