<?php
/*
 *  Plugin Name:       WanPay
 *  Plugin URI:        https://www.wan-pay.com/
 *  Description:       旺沛快點付金流外掛套件
 *  Version:           1.0.3
 *  WC requires at least: 4.3
 *  Requires PHP:      7.2
 * License: GPL2
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH'))
    exit ();


// 当前插件目录的URI
define('WCWP_WANPAY_URL', plugins_url('', __FILE__));

// 加载插件时，初始化自定义类
add_action('plugins_loaded', 'wcwp_init_my_gateway_class_wanpay');

function wcwp_init_my_gateway_class_wanpay()
{
    require_once('src/class_wcwp_gateway_wanpay.php');
}

// 告诉WC这个类的存在
add_filter('woocommerce_payment_gateways', 'wcwp_my_gateway_class_wanpay');
function wcwp_my_gateway_class_wanpay($methods)
{
    $methods[] = 'WCWP_Gateway_Wanpay';
    return $methods;
}

//加载wanpay插件的语言包
add_action('plugins_loaded', 'wcwp_load_plugin_wanpay');

function wcwp_load_plugin_wanpay()
{
    load_plugin_textdomain('wanpay-payment-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
