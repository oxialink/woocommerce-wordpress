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
    /** @var Oxialink_Payment_Gateway */
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
                $meta = isset(Oxialink_Payment_Gateway::COIN_META[$coin]) ? Oxialink_Payment_Gateway::COIN_META[$coin] : array($coin, '', 'btc');
                $coins[] = array(
                    'value' => $coin,
                    'label' => Oxialink_Payment_Gateway::COINS[$coin],
                    'name' => $meta[0],
                    'network' => $meta[1],
                    'logo' => 'https://oxialink.com/coin-logos/' . $meta[2] . '.png',
                );
            }
        }
        return array(
            'title'       => $this->get_setting('title', __('Cryptocurrency', 'oxialink-crypto-payments-for-woocommerce')),
            'description' => $this->get_setting('description', ''),
            'icon'        => plugins_url('assets/icon.png', OXIALINK_WC_PLUGIN_FILE),
            'coins'       => $coins,
            'supports'    => array('products'),
        );
    }
}
