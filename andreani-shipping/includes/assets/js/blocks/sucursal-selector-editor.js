(function (wp) {
  'use strict';

  if (!wp || !wp.blocks || !wp.element) {
    return;
  }

  var el = wp.element.createElement;

  wp.blocks.registerBlockType('andreani/sucursal-selector', {
    title: 'Sucursal Andreani',
    category: 'woocommerce',
    parent: ['woocommerce/checkout-shipping-methods-block'],
    attributes: {
      lock: { type: 'object', default: { remove: true, move: true } }
    },
    supports: { html: false, multiple: false, reusable: false, inserter: false },
    edit: function () {
      return el(
        'div',
        { className: 'andreani-bloque-sucursal andreani-bloque-sucursal--editor' },
        el('p', null, 'Selector de sucursal de retiro de Andreani — se muestra cuando el comprador elige "Andreani sucursal".')
      );
    },
    save: function () {
      return null;
    }
  });
})(window.wp);
