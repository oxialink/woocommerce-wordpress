<?php
/**
 * Plugin Name: Oxialink Crypto Payments for WooCommerce
 * Plugin URI: https://oxialink.com/woocommerce-crypto-payment-gateway
 * Description: Accept USDT, Bitcoin, Ethereum, TON and more at checkout. Real on-chain settlement, no chargebacks. Crypto payment gateway by Oxialink.
 * Version: 1.0.0
 * Author: Oxialink
 * Author URI: https://oxialink.com
 * Text Domain: oxialink-crypto-payments-for-woocommerce
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OXIALINK_WC_VERSION', '1.0.0');
define('OXIALINK_WC_PLUGIN_FILE', __FILE__);

// High-Performance Order Storage (custom order tables) compatibility.
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', function () {
    if (!class_exists('WC_Payment_Gateway')) {
        return; // WooCommerce is not active.
    }
    require_once __DIR__ . '/includes/class-oxialink-payment-gateway.php';

    add_filter('woocommerce_payment_gateways', function ($gateways) {
        $gateways[] = 'Oxialink_Payment_Gateway';
        return $gateways;
    });
});

// Block-based checkout support.
add_action('woocommerce_blocks_loaded', function () {
    if (!class_exists(\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class)) {
        return;
    }
    require_once __DIR__ . '/includes/class-oxialink-blocks.php';
    add_action('woocommerce_blocks_payment_method_type_registration', function ($registry) {
        $registry->register(new Oxialink_Blocks_Support());
    });
});

// Webhook-miss safety net: hourly status poll for pending Oxialink orders.
register_activation_hook(__FILE__, function () {
    if (!wp_next_scheduled('oxialink_check_pending')) {
        wp_schedule_event(time() + 300, 'hourly', 'oxialink_check_pending');
    }
});
register_deactivation_hook(__FILE__, function () {
    wp_clear_scheduled_hook('oxialink_check_pending');
});
add_action('oxialink_check_pending', function () {
    if (!function_exists('WC')) {
        return;
    }
    $gateways = WC()->payment_gateways()->payment_gateways();
    if (isset($gateways['oxialink']) && 'yes' === $gateways['oxialink']->enabled) {
        $gateways['oxialink']->check_pending_orders();
    }
});

// Invoice details on the thank-you page while the payment confirms.
add_action('woocommerce_thankyou', function ($order_id) {
    $order = wc_get_order($order_id);
    if (!$order || 'oxialink' !== $order->get_payment_method() || $order->is_paid()) {
        return;
    }
    $pay_url = $order->get_meta('_oxialink_pay_url');
    $code    = $order->get_meta('_oxialink_invoice_code');
    if (!$pay_url) {
        return;
    }
    echo '<section style="margin:1.2em 0;padding:18px 20px;border:1.5px solid #ffd4a8;border-left:5px solid #ff914d;border-radius:12px;background:#fff9f3;">';
    echo '<p style="margin:0 0 4px;font-weight:600;color:#1f2937;">'
        . esc_html__('Crypto payment in progress', 'oxialink-crypto-payments-for-woocommerce') . '</p>';
    echo '<p style="margin:0 0 12px;color:#4b5563;font-size:14px;">' . sprintf(
        /* translators: %s: invoice code */
        esc_html__('Invoice %s is confirming on-chain. This page updates once it settles.', 'oxialink-crypto-payments-for-woocommerce'),
        '<code>' . esc_html($code) . '</code>'
    ) . '</p>';
    echo '<p style="margin:0;"><a href="' . esc_url($pay_url) . '" style="display:inline-block;padding:10px 22px;background:#ff914d;color:#1f2430;font-weight:600;border-radius:10px;text-decoration:none;">'
        . esc_html__('Reopen the payment page', 'oxialink-crypto-payments-for-woocommerce') . '</a></p>';
    echo '</section>';
}, 10);

// Settings shortcut on the plugins screen.
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=oxialink');
    array_unshift($links, '<a href="' . esc_url($url) . '">' . esc_html__('Settings', 'oxialink-crypto-payments-for-woocommerce') . '</a>');
    return $links;
});
