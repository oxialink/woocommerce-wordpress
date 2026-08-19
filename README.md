<p align="center">
  <a href="https://oxialink.com"><img src="https://oxialink.com/brand/logo.png" alt="Oxialink" width="260"></a>
</p>

# Oxialink Payments for WooCommerce

Accept **USDT, Bitcoin, Ethereum, TON and more** in your WooCommerce store. Payments settle on-chain to a wallet you control - no chargebacks, no rolling reserves, fees from 0.2%.

[Oxialink](https://oxialink.com) is a crypto payment gateway built to settle: every payment is tracked to real blockchain confirmation, and the checkout even runs inside Telegram as a Mini App.

## Features

- **14 coins across 6 networks**: USDT (TON, BSC, Solana, Tron, Ethereum), USDC, BTC, ETH, LTC, DOGE, DASH, BNB, TRX, Toncoin
- **Coin picker at checkout** - works on both the classic checkout and the block-based checkout
- **One set of credentials** - a single API key covers every coin (no per-wallet key juggling)
- **Signed webhooks** - orders complete automatically; every notification is HMAC-SHA256 verified byte-exactly against the raw body, with a replay guard
- **Nothing gets stuck** - an hourly status poll backs the webhook up, so a missed callback can never strand an order
- **Honest edge-case handling** - underpaid payments put the order on hold with the received/requested amounts; expired invoices cancel the order
- **Merchant controls** - order status after payment, invoice expiry, minimum order total, and a crypto surcharge/discount multiplier
- **HPOS compatible** (WooCommerce custom order tables)

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+
- A free [Oxialink account](https://oxialink.com/register)

## Installation

1. Download the latest release zip (or zip the `oxialink-payments` folder from this repo).
2. WordPress admin → **Plugins → Add New → Upload Plugin** → choose the zip → **Activate**.
3. **WooCommerce → Settings → Payments → Oxialink**:
   - Paste your **API key** and **API secret** (from the [API docs page](https://oxialink.com/api-docs) while signed in)
   - Paste your **webhook secret** (dashboard **Settings**)
   - Pick the coins to offer and enable the gateway.
4. Create a wallet in your Oxialink dashboard for each coin you offer.

That's it. Orders move to *Processing* (or *Completed*, your choice) the moment the payment is final on-chain.

## How a payment flows

1. The customer picks a coin at checkout and is redirected to a hosted payment page with a QR code and a **live confirmation count**.
2. Oxialink watches the blockchain and settles the payment at confirmation depth.
3. Your store receives a signed `payment.completed` webhook and completes the order; the funds sit in your Oxialink wallet, withdrawable to your own address any time.

## Support

- Docs: https://oxialink.com/api-docs
- Fees: https://oxialink.com/fees
- Issues: open one right here on GitHub

## License

GPLv2 or later.
