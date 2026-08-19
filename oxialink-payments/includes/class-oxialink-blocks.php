<?php
/**
 * WooCommerce Blocks (block-based checkout) support. Registers the payment
 * method with a coin picker rendered by assets/blocks.js; the chosen coin
 * travels in paymentMethodData and reaches process_payment via the Store
 * API's legacy $_POST bridge.
 */

if (!defined('ABSPATH')) {
    exit;
}

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class Oxialink_Blocks_Support extends AbstractPaymentMethodType
{
    /** @var WC_Gateway_Oxialink */
    private $gateway;

    protected $name = 'oxialink';

    public function initialize()
    {
        $this->settings = get_option('woocommerce_oxialink_settings', array());
        $gateways       = WC()->payment_gateways()->payment_gateways();
        $this->gateway  = isset($gateways['oxialink']) ? $gateways['oxialink'] : null;
    }

    public function is_active()
    {
        return $this->gateway && $this->gateway->is_available();
    }

    public function get_payment_method_script_handles()
    {
        wp_register_script(
            'oxialink-blocks',
            plugins_url('assets/blocks.js', OXIALINK_WC_PLUGIN_FILE),
            array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities'),
            OXIALINK_WC_VERSION,
            true
        );
        return array('oxialink-blocks');
    }

    public function get_payment_method_data()
    {
        $coins = array();
        if ($this->gateway) {
            foreach ($this->gateway->offered_coins() as $coin) {
                $coins[] = array(
                    'value' => $coin,
                    'label' => WC_Gateway_Oxialink::COINS[$coin],
                );
            }
        }
        return array(
            'title'       => $this->get_setting('title', __('Cryptocurrency', 'oxialink-payments')),
            'description' => $this->get_setting('description', ''),
            'icon'        => plugins_url('assets/icon.png', OXIALINK_WC_PLUGIN_FILE),
            'coins'       => $coins,
            'supports'    => array('products'),
        );
    }
}
