<?php
/**
 * Created by PhpStorm.
 * User: hjl
 * Date: 2022/4/16
 * Time: 11:47
 */
namespace app\index\service;

interface PlaceOrderInterface{
    public function placeOrder(array $params = []);
}