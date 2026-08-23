/**
 * Oxialink payment method for the WooCommerce block-based checkout.
 * Branded coin-tile picker; no build step, uses the globals Blocks ships.
 */
(function () {
  'use strict';

  var registerPaymentMethod = window.wc.wcBlocksRegistry.registerPaymentMethod;
  var getSetting = window.wc.wcSettings.getSetting;
  var el = window.wp.element.createElement;
  var useState = window.wp.element.useState;
  var useEffect = window.wp.element.useEffect;
  var decodeEntities = window.wp.htmlEntities.decodeEntities;

  var settings = getSetting('oxialink_data', {});
  var label = decodeEntities(settings.title || 'Cryptocurrency');
  var coins = settings.coins || [];

  var styles = {
    picker: { display: 'flex', flexWrap: 'wrap', gap: '8px', margin: '12px 0 4px' },
    tile: function (selected) {
      return {
        display: 'flex',
        alignItems: 'center',
        gap: '9px',
        padding: '9px 14px 9px 10px',
        border: '1.5px solid ' + (selected ? '#ff914d' : '#e2e5ec'),
        borderRadius: '10px',
        cursor: 'pointer',
        background: selected ? '#fff5ec' : '#fff',
        boxShadow: selected ? '0 2px 8px rgba(255,145,77,.25)' : 'none',
        transition: 'border-color .15s, box-shadow .15s, background .15s',
        font: 'inherit',
      };
    },
    logo: { width: '26px', height: '26px', borderRadius: '50%', display: 'block' },
    txt: { display: 'flex', flexDirection: 'column', lineHeight: 1.15, textAlign: 'left' },
    name: { fontWeight: 600, fontSize: '13px', color: '#1f2937' },
    net: { fontSize: '11px', color: '#6b7280' },
    note: { display: 'flex', alignItems: 'center', gap: '7px', fontSize: '12px', color: '#6b7280', margin: '10px 0 2px' },
  };

  function Content(props) {
    var state = useState(coins.length ? coins[0].value : '');
    var coin = state[0];
    var setCoin = state[1];
    var eventRegistration = props.eventRegistration;
    var emitResponse = props.emitResponse;

    useEffect(
      function () {
        var unsubscribe = eventRegistration.onPaymentSetup(function () {
          return {
            type: emitResponse.responseTypes.SUCCESS,
            meta: { paymentMethodData: { oxialink_coin: coin } },
          };
        });
        return unsubscribe;
      },
      [coin, eventRegistration, emitResponse]
    );

    return el(
      'div',
      null,
      settings.description ? el('p', null, decodeEntities(settings.description)) : null,
      el(
        'div',
        { style: styles.picker, role: 'radiogroup', 'aria-label': 'Choose a cryptocurrency' },
        coins.map(function (c) {
          var selected = coin === c.value;
          return el(
            'button',
            {
              key: c.value,
              type: 'button',
              style: styles.tile(selected),
              onClick: function () { setCoin(c.value); },
              'aria-pressed': selected,
            },
            el('img', { src: c.logo, alt: '', style: styles.logo, loading: 'lazy' }),
            el(
              'span',
              { style: styles.txt },
              el('span', { style: styles.name }, c.name || c.label),
              el('span', { style: styles.net }, c.network || '')
            )
          );
        })
      ),
      el(
        'p',
        { style: styles.note },
        el('span', { 'aria-hidden': 'true' }, '🔒'),
        'Secure payment page with QR code and live confirmation tracking.'
      )
    );
  }

  registerPaymentMethod({
    name: 'oxialink',
    label: el(
      'span',
      { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
      settings.icon ? el('img', { src: settings.icon, alt: '', style: { width: 22, height: 22 } }) : null,
      label
    ),
    content: el(Content, null),
    edit: el(Content, null),
    canMakePayment: function () { return true; },
    ariaLabel: label,
    supports: { features: (settings.supports || ['products']) },
  });
})();
