/**
 * Oxialink payment method for the WooCommerce block-based checkout.
 * No build step: uses the globals WooCommerce Blocks ships.
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
            meta: {
              paymentMethodData: {
                oxialink_coin: coin,
              },
            },
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
      coins.length
        ? el(
            'select',
            {
              value: coin,
              onChange: function (e) { setCoin(e.target.value); },
              style: { width: '100%', maxWidth: '320px', padding: '8px', marginTop: '4px' },
            },
            coins.map(function (c) {
              return el('option', { key: c.value, value: c.value }, c.label);
            })
          )
        : null
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
