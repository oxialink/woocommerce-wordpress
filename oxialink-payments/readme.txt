=== Oxialink Payments ===
Contributors: oxialink
Tags: crypto payment gateway, woocommerce, usdt, bitcoin, cryptocurrency
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept USDT, Bitcoin, Ethereum, TON and more in WooCommerce. Real on-chain settlement, no chargebacks, fees from 0.2%.

== Description ==

Oxialink is a crypto payment gateway built to settle: every payment is tracked to real on-chain confirmation and lands in a wallet you control.

* 14 coins across 6 networks: USDT (TON, BSC, Solana, Tron, Ethereum), USDC, BTC, ETH, LTC, DOGE, DASH, BNB, TRX, TON
* Customer picks the coin at checkout and pays on a hosted page with a QR code and a live confirmation count
* Works on both the classic checkout and the block-based checkout, HPOS compatible
* Orders complete automatically via HMAC-signed webhooks, verified byte-exactly before any order is touched, with an hourly status poll as a safety net
* Underpayments put the order on hold with a note; expired invoices cancel the order
* Merchant controls: order status after payment, invoice expiry, minimum order total, crypto surcharge or discount multiplier
* No chargebacks, no rolling reserves, deposits free, processing from 0.2%

This plugin connects your store to the Oxialink payment service. A free Oxialink account is required; Oxialink charges a processing fee per withdrawal as published at https://oxialink.com/fees. All plugin functionality is included with the free plan.

== External services ==

This plugin talks to the Oxialink API (https://oxialink.com) to process payments. It is required for the plugin to work.

What is sent, and when:

* When a customer chooses Oxialink at checkout and places the order, the plugin sends the order total, store currency, chosen cryptocurrency, an order description (store name and order number), and your store's callback and return URLs to api endpoint /api/v1/invoice/create, authenticated with your API key.
* Once per hour, for orders still awaiting payment, the plugin sends the invoice code to /api/v1/invoice/get to check the payment status.
* No customer personal data (name, email, address) is ever sent to Oxialink by this plugin.

The service is provided by Oxialink: terms of service https://oxialink.com/terms and privacy policy https://oxialink.com/privacy.

== Installation ==

1. Upload the plugin and activate it.
2. Create a free account at https://oxialink.com and create a wallet for each coin you want to accept.
3. In WooCommerce, go to Settings, then Payments, then Oxialink. Paste your API key, API secret and webhook secret (found in the Oxialink dashboard), pick the coins to offer, and enable the gateway.

== Frequently Asked Questions ==

= Do I need an Oxialink account? =
Yes. The plugin is a connector for the Oxialink payment service. Accounts are free and no KYC is required to start.

= Which store currencies are supported? =
Any currency Oxialink can quote against crypto (USD, EUR, IDR and more). The invoice is converted at the live rate when the customer checks out.

= Where does the money go? =
To your Oxialink wallet, withdrawable to your own address any time, or automatically with auto-withdraw.

= Is customer data shared with Oxialink? =
No. The plugin sends only order totals, currency, the chosen coin and an order reference. The customer pays on Oxialink's hosted page and never enters personal data there.

== Changelog ==

= 1.0.0 =
* First release: hosted checkout, coin picker on classic and block checkout, signed webhooks with status-poll fallback, underpaid and expired handling, HPOS compatible.

== Upgrade Notice ==

= 1.0.0 =
First release.
