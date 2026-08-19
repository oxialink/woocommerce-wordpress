<?php
/**
 * WooCommerce payment gateway for Oxialink.
 *
 * Flow: the customer picks a coin at checkout, the plugin creates an
 * invoice over the Oxialink API and redirects to the hosted payment page
 * (which also runs inside Telegram as a Mini App). Oxialink calls back the
 * per-order webhook with an HMAC-SHA256 signed event when the payment
 * settles on-chain; the handler verifies the signature byte-exactly against
 * the raw body before touching the order.
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Gateway_Oxialink extends WC_Payment_Gateway
{
    /** Coins the gateway can offer. Mirrors Oxialink's live catalog. */
    const COINS = array(
        'USDT_TON'    => 'USDT (TON) - settles in seconds',
        'USDT_BEP20'  => 'USDT (BSC)',
        'USDT_SOLANA' => 'USDT (Solana)',
        'USDT_TRC20'  => 'USDT (Tron)',
        'USDT_ERC20'  => 'USDT (Ethereum)',
        'USDC_SOLANA' => 'USDC (Solana)',
        'BTC'         => 'Bitcoin',
        'ETH'         => 'Ethereum',
        'LTC'         => 'Litecoin',
        'DOGE'        => 'Dogecoin',
        'DASH'        => 'Dash',
        'BNB'         => 'BNB',
        'TRX'         => 'TRON',
        'TON'         => 'Toncoin',
    );

    public function __construct()
    {
        $this->id                 = 'oxialink';
        $this->method_title       = __('Oxialink', 'oxialink-payments');
        $this->method_description = __('Accept USDT, Bitcoin, Ethereum, TON and more. Payments settle on-chain to your Oxialink wallet - no chargebacks.', 'oxialink-payments');
        $this->has_fields         = true;
        $this->icon               = plugins_url('assets/icon.png', OXIALINK_WC_PLUGIN_FILE);

        $this->init_form_fields();
        $this->init_settings();

        $this->title       = $this->get_option('title');
        $this->description = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_oxialink_webhook', array($this, 'handle_webhook'));
    }

    public function init_form_fields()
    {
        $coin_options = self::COINS;

        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'oxialink-payments'),
                'type'    => 'checkbox',
                'label'   => __('Enable Oxialink payments', 'oxialink-payments'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'oxialink-payments'),
                'type'        => 'text',
                'description' => __('Shown to the customer at checkout.', 'oxialink-payments'),
                'default'     => __('Cryptocurrency (USDT, BTC, ETH & more)', 'oxialink-payments'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'oxialink-payments'),
                'type'        => 'textarea',
                'default'     => __('Pay with crypto. You will be redirected to a secure payment page with a QR code and live confirmation tracking.', 'oxialink-payments'),
                'desc_tip'    => true,
            ),
            'api_credentials' => array(
                'title'       => __('API credentials', 'oxialink-payments'),
                'type'        => 'title',
                'description' => sprintf(
                    /* translators: %s: settings URL */
                    __('Create a free account and find your keys in the <a href="%s" target="_blank">Oxialink API docs page</a>. The webhook secret is in dashboard Settings.', 'oxialink-payments'),
                    'https://oxialink.com/api-docs'
                ),
            ),
            'api_key' => array(
                'title' => __('API key', 'oxialink-payments'),
                'type'  => 'text',
            ),
            'api_secret' => array(
                'title' => __('API secret', 'oxialink-payments'),
                'type'  => 'password',
            ),
            'webhook_secret' => array(
                'title'       => __('Webhook secret', 'oxialink-payments'),
                'type'        => 'password',
                'description' => __('From Oxialink dashboard Settings. Used to verify that payment notifications really come from Oxialink.', 'oxialink-payments'),
                'desc_tip'    => true,
            ),
            'coins' => array(
                'title'       => __('Coins offered at checkout', 'oxialink-payments'),
                'type'        => 'multiselect',
                'class'       => 'wc-enhanced-select',
                'options'     => $coin_options,
                'default'     => array('USDT_TON', 'USDT_BEP20', 'USDT_TRC20', 'BTC', 'TON'),
                'description' => __('The customer picks one of these at checkout.', 'oxialink-payments'),
                'desc_tip'    => true,
            ),
            'behavior' => array(
                'title' => __('Behavior', 'oxialink-payments'),
                'type'  => 'title',
            ),
            'paid_status' => array(
                'title'       => __('Order status after payment', 'oxialink-payments'),
                'type'        => 'select',
                'options'     => array(
                    'processing' => __('Processing (default)', 'oxialink-payments'),
                    'completed'  => __('Completed', 'oxialink-payments'),
                ),
                'default'     => 'processing',
                'description' => __('Status the order gets once the payment settles on-chain.', 'oxialink-payments'),
                'desc_tip'    => true,
            ),
            'expiry_minutes' => array(
                'title'       => __('Invoice expiry (minutes)', 'oxialink-payments'),
                'type'        => 'number',
                'default'     => '60',
                'custom_attributes' => array('min' => '10', 'max' => '1440'),
                'description' => __('How long the customer has to pay before the invoice expires.', 'oxialink-payments'),
                'desc_tip'    => true,
            ),
            'min_order_total' => array(
                'title'       => __('Minimum order total', 'oxialink-payments'),
                'type'        => 'number',
                'default'     => '0',
                'custom_attributes' => array('min' => '0', 'step' => 'any'),
                'description' => __('Hide this payment method for orders below this amount (store currency). 0 = always show.', 'oxialink-payments'),
                'desc_tip'    => true,
            ),
            'order_multiplier' => array(
                'title'       => __('Order multiplier', 'oxialink-payments'),
                'type'        => 'number',
                'default'     => '1',
                'custom_attributes' => array('min' => '0.5', 'max' => '2', 'step' => '0.01'),
                'description' => __('Multiply the charged amount: 1.05 adds a 5% surcharge, 0.95 gives a 5% crypto discount.', 'oxialink-payments'),
                'desc_tip'    => true,
            ),
            'api_base' => array(
                'title'       => __('API base URL', 'oxialink-payments'),
                'type'        => 'text',
                'default'     => 'https://oxialink.com',
                'description' => __('Leave as-is unless told otherwise.', 'oxialink-payments'),
                'desc_tip'    => true,
            ),
        );
    }

    public function is_available()
    {
        if (!parent::is_available()) {
            return false;
        }
        $min = (float) $this->get_option('min_order_total', 0);
        if ($min > 0 && isset(WC()->cart) && WC()->cart && (float) WC()->cart->get_total('edit') < $min) {
            return false;
        }
        return (bool) ($this->get_option('api_key') && $this->get_option('api_secret'));
    }

    public function offered_coins()
    {
        $coins = (array) $this->get_option('coins', array());
        $coins = array_values(array_intersect($coins, array_keys(self::COINS)));
        return $coins ? $coins : array('USDT_TON', 'BTC');
    }

    public function payment_fields()
    {
        if ($this->description) {
            echo wp_kses_post(wpautop($this->description));
        }
        $coins = $this->offered_coins();
        echo '<p class="form-row form-row-wide">';
        echo '<label for="oxialink_coin">' . esc_html__('Pay with', 'oxialink-payments') . '</label>';
        echo '<select id="oxialink_coin" name="oxialink_coin" style="width:100%;max-width:320px;">';
        foreach ($coins as $coin) {
            printf('<option value="%s">%s</option>', esc_attr($coin), esc_html(self::COINS[$coin]));
        }
        echo '</select></p>';
    }

    public function validate_fields()
    {
        $coin = isset($_POST['oxialink_coin']) ? sanitize_text_field(wp_unslash($_POST['oxialink_coin'])) : '';
        if (!in_array($coin, $this->offered_coins(), true)) {
            wc_add_notice(__('Please choose a cryptocurrency to pay with.', 'oxialink-payments'), 'error');
            return false;
        }
        return true;
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);
        $offered = $this->offered_coins();
        $coin = isset($_POST['oxialink_coin']) ? sanitize_text_field(wp_unslash($_POST['oxialink_coin'])) : '';
        if (!in_array($coin, $offered, true)) {
            $coin = $offered[0]; // Blocks checkout may omit it; never fail the sale over a picker.
        }

        $multiplier = (float) $this->get_option('order_multiplier', 1);
        if ($multiplier < 0.5 || $multiplier > 2) {
            $multiplier = 1;
        }

        $body = array(
            'amount'        => round((float) $order->get_total() * $multiplier, 2),
            'fiat_currency' => get_woocommerce_currency(),
            'coin'          => $coin,
            'description'   => sprintf('%s order #%s', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), $order->get_order_number()),
            'callback_url'  => WC()->api_request_url('oxialink_webhook'),
            'redirect_url'  => $this->get_return_url($order),
            'expiry_minutes' => max(10, min(1440, (int) $this->get_option('expiry_minutes', 60))),
        );

        $response = wp_remote_post(
            rtrim($this->get_option('api_base', 'https://oxialink.com'), '/') . '/api/v1/invoice/create',
            array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type'   => 'application/json',
                    'x-api-key'      => $this->get_option('api_key'),
                    'x-api-password' => $this->get_option('api_secret'),
                ),
                'body' => wp_json_encode($body),
            )
        );

        if (is_wp_error($response)) {
            wc_add_notice(__('Could not reach the payment service. Please try again.', 'oxialink-payments'), 'error');
            return array('result' => 'failure');
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($data['success']) || empty($data['data']['invoice_id'])) {
            $message = !empty($data['message']) ? $data['message'] : __('Payment service rejected the request.', 'oxialink-payments');
            wc_add_notice(esc_html($message), 'error');
            return array('result' => 'failure');
        }

        $invoice_code  = sanitize_text_field($data['data']['invoice_id']);
        $pay_url       = esc_url_raw($data['data']['url']);
        $crypto_amount = isset($data['data']['amount']) ? wc_clean($data['data']['amount']) : '';

        $order->update_meta_data('_oxialink_invoice_code', $invoice_code);
        $order->update_meta_data('_oxialink_coin', $coin);
        $order->update_meta_data('_oxialink_pay_url', $pay_url);
        $order->update_status('pending', sprintf(
            /* translators: 1: invoice code, 2: crypto amount, 3: coin */
            __('Oxialink invoice %1$s created: %2$s %3$s at the live rate. Awaiting on-chain payment.', 'oxialink-payments'),
            $invoice_code,
            $crypto_amount,
            $coin
        ));
        $order->save();

        WC()->cart->empty_cart();

        return array(
            'result'   => 'success',
            'redirect' => $pay_url,
        );
    }

    // ------------------------------------------------------------ webhook

    public function handle_webhook()
    {
        $raw = file_get_contents('php://input');
        // Headers feed an HMAC comparison, so they are unslashed but kept
        // byte-exact; sanitize_text_field would corrupt valid signatures no
        // worse than reject them, and these values are never echoed.
        $delivery  = isset($_SERVER['HTTP_X_OXIALINK_DELIVERY']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_OXIALINK_DELIVERY'])) : '';
        $timestamp = isset($_SERVER['HTTP_X_OXIALINK_TIMESTAMP']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_OXIALINK_TIMESTAMP'])) : '';
        $event     = isset($_SERVER['HTTP_X_OXIALINK_EVENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_OXIALINK_EVENT'])) : '';
        $signature = isset($_SERVER['HTTP_X_OXIALINK_SIGNATURE']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_X_OXIALINK_SIGNATURE'])) : '';

        $secret = $this->get_option('webhook_secret');
        if (!$secret || !$raw || !$delivery || !$timestamp || !$event || !$signature) {
            status_header(400);
            exit('missing fields');
        }

        // Replay guard: reject stale deliveries.
        $age = abs(time() - strtotime($timestamp));
        if (!strtotime($timestamp) || $age > 600) {
            status_header(400);
            exit('stale');
        }

        // The signature covers the *exact bytes* of the data object as the
        // sender serialized it, so extract it from the raw body instead of
        // re-encoding (PHP and JS JSON output differ on floats and slashes).
        $raw_data = $this->extract_raw_json_value($raw, 'data');
        if ($raw_data === null) {
            status_header(400);
            exit('bad body');
        }

        $expected = hash_hmac('sha256', $delivery . '.' . $timestamp . '.' . $event . '.' . $raw_data, $secret);
        if (!hash_equals($expected, $signature)) {
            status_header(401);
            exit('bad signature');
        }

        $payload = json_decode($raw, true);
        $data    = isset($payload['data']) ? $payload['data'] : array();
        $invoice = isset($data['invoice_code']) ? sanitize_text_field($data['invoice_code']) : '';

        if (!$invoice) {
            // Signed but not an invoice event (wallet.receive etc) - fine.
            status_header(200);
            exit('ok');
        }

        $orders = wc_get_orders(array(
            'limit'      => 1,
            'meta_key'   => '_oxialink_invoice_code',
            'meta_value' => $invoice,
        ));
        if (!$orders) {
            status_header(200);
            exit('no matching order');
        }
        $order = $orders[0];

        // Idempotency: never regress an order that is already paid.
        $already_paid = $order->is_paid();

        switch ($event) {
            case 'payment.completed':
                if (!$already_paid) {
                    $tx = isset($data['tx_hash']) ? sanitize_text_field($data['tx_hash']) : '';
                    $this->mark_order_paid($order, isset($data['amount']) ? wc_clean($data['amount']) : '?', isset($data['coin']) ? wc_clean($data['coin']) : '', $tx);
                }
                break;

            case 'payment.underpaid':
                if (!$already_paid) {
                    $order->update_status('on-hold', sprintf(
                        /* translators: 1: received, 2: requested, 3: coin */
                        __('Oxialink: UNDERPAID - received %1$s of %2$s %3$s. Review before fulfilling.', 'oxialink-payments'),
                        isset($data['amount']) ? wc_clean($data['amount']) : '?',
                        isset($data['amount_requested']) ? wc_clean($data['amount_requested']) : '?',
                        isset($data['coin']) ? wc_clean($data['coin']) : ''
                    ));
                }
                break;

            case 'invoice.expired':
                if ($order->has_status('pending')) {
                    $order->update_status('cancelled', __('Oxialink: invoice expired unpaid.', 'oxialink-payments'));
                }
                break;

            case 'payment.uncredited':
                $order->add_order_note(__('Oxialink: a payment arrived below the network minimum and was not credited.', 'oxialink-payments'));
                break;
        }

        $order->save();
        status_header(200);
        exit('ok');
    }

    public function mark_order_paid($order, $amount, $coin, $tx)
    {
        $order->add_order_note(sprintf(
            /* translators: 1: amount, 2: coin, 3: tx hash */
            __('Oxialink: payment settled on-chain. %1$s %2$s (tx %3$s).', 'oxialink-payments'),
            $amount,
            $coin,
            $tx
        ));
        $order->payment_complete($tx);
        if ('completed' === $this->get_option('paid_status', 'processing') && !$order->has_status('completed')) {
            $order->update_status('completed');
        }
    }

    /**
     * Webhook-miss safety net: wp-cron polls pending Oxialink orders against
     * the invoice API so a dropped callback can never strand an order.
     */
    public function check_pending_orders()
    {
        $orders = wc_get_orders(array(
            'limit'        => 20,
            'status'       => 'pending',
            'meta_key'     => '_oxialink_invoice_code',
            'meta_compare' => 'EXISTS',
            'date_created' => '>' . (time() - 3 * DAY_IN_SECONDS),
        ));
        foreach ($orders as $order) {
            $code = $order->get_meta('_oxialink_invoice_code');
            if (!$code) {
                continue;
            }
            $response = wp_remote_post(
                rtrim($this->get_option('api_base', 'https://oxialink.com'), '/') . '/api/v1/invoice/get',
                array(
                    'timeout' => 20,
                    'headers' => array(
                        'Content-Type'   => 'application/json',
                        'x-api-key'      => $this->get_option('api_key'),
                        'x-api-password' => $this->get_option('api_secret'),
                    ),
                    'body' => wp_json_encode(array('invoice_id' => $code)),
                )
            );
            if (is_wp_error($response)) {
                continue;
            }
            $data = json_decode(wp_remote_retrieve_body($response), true);
            $status = isset($data['data']['status']) ? strtolower((string) $data['data']['status']) : '';
            if ('paid' === $status && !$order->is_paid()) {
                $this->mark_order_paid($order, '', $order->get_meta('_oxialink_coin'), '');
                $order->add_order_note(__('Oxialink: confirmed by status poll (webhook was missed).', 'oxialink-payments'));
                $order->save();
            } elseif (in_array($status, array('expired', 'cancelled'), true) && $order->has_status('pending')) {
                $order->update_status('cancelled', __('Oxialink: invoice expired unpaid (status poll).', 'oxialink-payments'));
                $order->save();
            }
        }
    }

    /**
     * Return the exact raw bytes of one top-level JSON object value (e.g.
     * "data") from a JSON document, string-aware so braces inside strings
     * cannot fool the scanner.
     */
    private function extract_raw_json_value($json, $key)
    {
        $needle = '"' . $key . '":';
        $pos = strpos($json, $needle);
        if ($pos === false) {
            return null;
        }
        $i = $pos + strlen($needle);
        $len = strlen($json);
        while ($i < $len && ($json[$i] === ' ' || $json[$i] === "\t")) {
            $i++;
        }
        if ($i >= $len || $json[$i] !== '{') {
            return null;
        }
        $start = $i;
        $depth = 0;
        $in_string = false;
        for (; $i < $len; $i++) {
            $c = $json[$i];
            if ($in_string) {
                if ($c === '\\') {
                    $i++;
                } elseif ($c === '"') {
                    $in_string = false;
                }
                continue;
            }
            if ($c === '"') {
                $in_string = true;
            } elseif ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($json, $start, $i - $start + 1);
                }
            }
        }
        return null;
    }
}
