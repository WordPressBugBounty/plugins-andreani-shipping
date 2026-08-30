(function (wp, wc) {
  'use strict';

  if (!wp || !wp.element || !wp.data || !wp.i18n || !wc || !wc.blocksCheckout || !wc.wcBlocksData || !wc.wcSettings) {
    return;
  }

  var el = wp.element.createElement;
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var useMemo = wp.element.useMemo;
  var useRef = wp.element.useRef;
  var useCallback = wp.element.useCallback;
  var useSelect = wp.data.useSelect;
  var useDispatch = wp.data.useDispatch;
  var sprintf = wp.i18n.sprintf;
  var registerCheckoutBlock = wc.blocksCheckout.registerCheckoutBlock;
  var CART_STORE_KEY = wc.wcBlocksData.CART_STORE_KEY;
  var VALIDATION_STORE_KEY = wc.wcBlocksData.VALIDATION_STORE_KEY;
  var getSetting = wc.wcSettings.getSetting;

  var BLOCK_NAME = 'andreani/sucursal-selector';
  var SETTING_KEY = 'andreani_data';
  var ERROR_ID = 'andreani-sucursal';
  var SELECT_ID = 'andreani-bloque-sucursal-select';
  var ESTADO_ID = 'andreani-bloque-sucursal-estado';
  var DEBOUNCE_MS = 400;
  var DEFAULT_NAMESPACE = 'andreani';
  var DEFAULT_MATCH = ['andreani', 'sucursal'];
  var LISTA_VACIA = { options: [], details: {} };

  function normalizePostcode(raw) {
    var value = String(raw || '').trim();
    if (/^\d{4}$/.test(value)) {
      return value;
    }
    var cpa = value.match(/^[A-Za-z](\d{4})[A-Za-z]{3}$/);
    return cpa ? cpa[1] : '';
  }

  function rateMatches(rateId, needles) {
    var id = String(rateId || '').toLowerCase();
    if (!needles.length) {
      return false;
    }
    for (var i = 0; i < needles.length; i++) {
      if (id.indexOf(String(needles[i]).toLowerCase()) === -1) {
        return false;
      }
    }
    return true;
  }

  function findSelectedSucursalRate(packages, needles) {
    if (!Array.isArray(packages)) {
      return null;
    }
    for (var p = 0; p < packages.length; p++) {
      var rates = packages[p] && packages[p].shipping_rates;
      if (!Array.isArray(rates)) {
        continue;
      }
      for (var r = 0; r < rates.length; r++) {
        if (rates[r] && rates[r].selected && rateMatches(rates[r].rate_id, needles)) {
          return rates[r];
        }
      }
    }
    return null;
  }

  function toLista(payload) {
    var raw = payload && payload.options ? payload.options : {};
    var options = [];
    Object.keys(raw).forEach(function (codigo) {
      if (codigo === '0') {
        return;
      }
      options.push({ codigo: codigo, texto: String(raw[codigo]) });
    });
    var details = payload && payload.details && typeof payload.details === 'object' ? payload.details : {};
    return { options: options, details: details };
  }

  function hasOption(lista, codigo) {
    return !!codigo && lista.options.some(function (o) {
      return o.codigo === codigo;
    });
  }

  function resolveInfo(lista, codigo) {
    var info = { nombre: '', direccion: '' };
    var detail = lista.details[codigo];
    if (detail) {
      info.nombre = String(detail.descripcion || '');
      info.direccion = String(detail.direccion || '');
      return info;
    }
    var option = lista.options.filter(function (o) {
      return o.codigo === codigo;
    })[0];
    if (!option) {
      return info;
    }
    var parts = option.texto.split(' - ');
    if (parts.length >= 2) {
      info.nombre = parts.slice(0, 2).join(' - ');
      info.direccion = parts.slice(2).join(' - ');
    } else {
      info.nombre = option.texto;
    }
    return info;
  }

  function fetchSucursales(data, postcode) {
    var body = new URLSearchParams({
      action: data.ajaxAction,
      postcode: postcode,
      nonce: data.nonce
    });
    return fetch(data.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    }).then(function (response) {
      if (!response.ok) {
        throw new Error('http ' + response.status);
      }
      return response.json();
    }).then(function (json) {
      if (!json || !json.success || !json.data) {
        throw new Error('respuesta invalida');
      }
      return toLista(json.data);
    });
  }

  function AndreaniSucursalSelector(props) {
    var data = getSetting(SETTING_KEY);
    var namespace = (data && data.namespace) || DEFAULT_NAMESPACE;
    var needles = data && Array.isArray(data.sucursalMatch) && data.sucursalMatch.length ? data.sucursalMatch : DEFAULT_MATCH;
    var i18n = (data && data.i18n) || {};
    var setExtensionData = props.checkoutExtensionData && props.checkoutExtensionData.setExtensionData;

    var selectedState = useState('');
    var selected = selectedState[0];
    var setSelected = selectedState[1];

    var statusState = useState('idle');
    var status = statusState[0];
    var setStatus = statusState[1];

    var listaState = useState(LISTA_VACIA);
    var lista = listaState[0];
    var setLista = listaState[1];

    var cacheRef = useRef({});
    var requestRef = useRef(0);
    var sentRef = useRef(null);

    var cart = useSelect(function (select) {
      var store = select(CART_STORE_KEY);
      return { rates: store.getShippingRates(), customer: store.getCustomerData() };
    }, []);

    var validationError = useSelect(function (select) {
      return select(VALIDATION_STORE_KEY).getValidationError(ERROR_ID);
    }, []);

    var validationActions = useDispatch(VALIDATION_STORE_KEY);
    var setValidationErrors = validationActions.setValidationErrors;
    var clearValidationError = validationActions.clearValidationError;

    var postcode = useMemo(function () {
      var customer = cart.customer || {};
      var shipping = customer.shippingAddress || {};
      var billing = customer.billingAddress || {};
      return normalizePostcode(shipping.postcode || billing.postcode || '');
    }, [cart.customer]);

    var sucursalRate = useMemo(function () {
      return findSelectedSucursalRate(cart.rates, needles);
    }, [cart.rates, needles]);
    var esSucursal = !!(data && sucursalRate);
    var confirmada = esSucursal && status === 'ready' && hasOption(lista, selected);

    var enviar = useCallback(function (codigo, nombre, direccion) {
      if (typeof setExtensionData !== 'function') {
        return;
      }
      var previo = sentRef.current;
      var vacio = !codigo && !nombre && !direccion;
      if ((previo === null && vacio) || (previo && previo.codigo === codigo && previo.nombre === nombre && previo.direccion === direccion)) {
        sentRef.current = { codigo: codigo, nombre: nombre, direccion: direccion };
        return;
      }
      sentRef.current = { codigo: codigo, nombre: nombre, direccion: direccion };
      setExtensionData(namespace, 'branch_code', codigo);
      setExtensionData(namespace, 'branch_name', nombre);
      setExtensionData(namespace, 'branch_address', direccion);
    }, [setExtensionData, namespace]);

    useEffect(function () {
      if (!esSucursal) {
        return;
      }
      if (!postcode) {
        setLista(LISTA_VACIA);
        setStatus('idle');
        return;
      }
      var cached = cacheRef.current[postcode];
      if (cached) {
        setLista(cached);
        setStatus('ready');
        return;
      }
      setLista(LISTA_VACIA);
      setStatus('loading');
      var timer = setTimeout(function () {
        requestRef.current += 1;
        var requestId = requestRef.current;
        fetchSucursales(data, postcode).then(function (nueva) {
          cacheRef.current[postcode] = nueva;
          if (requestId !== requestRef.current) {
            return;
          }
          setLista(nueva);
          setStatus('ready');
        }, function () {
          if (requestId !== requestRef.current) {
            return;
          }
          setStatus('error');
        });
      }, DEBOUNCE_MS);
      return function () {
        clearTimeout(timer);
        requestRef.current += 1;
      };
    }, [data, esSucursal, postcode]);

    useEffect(function () {
      if (status === 'ready' && selected && !hasOption(lista, selected)) {
        setSelected('');
      }
    }, [status, lista, selected]);

    useEffect(function () {
      if (!data) {
        return;
      }
      if (!esSucursal) {
        enviar('', '', '');
        clearValidationError(ERROR_ID);
        return;
      }
      if (confirmada) {
        var info = resolveInfo(lista, selected);
        enviar(selected, info.nombre, info.direccion);
        clearValidationError(ERROR_ID);
        return;
      }
      enviar('', '', '');
      if (!wp.data.select(VALIDATION_STORE_KEY).getValidationError(ERROR_ID)) {
        var errores = {};
        errores[ERROR_ID] = { message: i18n.requerido || '', hidden: true };
        setValidationErrors(errores);
      }
    }, [data, esSucursal, confirmada, selected, lista, enviar, setValidationErrors, clearValidationError, i18n.requerido]);

    useEffect(function () {
      return function () {
        clearValidationError(ERROR_ID);
        enviar('', '', '');
      };
    }, [clearValidationError, enviar]);

    var onChange = useCallback(function (event) {
      setSelected(event.target.value || '');
    }, []);

    if (!esSucursal) {
      return null;
    }

    var options = lista.options;
    var estado = '';
    if (!postcode) {
      estado = i18n.sinCp || '';
    } else if (status === 'loading') {
      estado = i18n.cargando || '';
    } else if (status === 'error') {
      estado = i18n.error || '';
    } else if (status === 'ready' && options.length === 0) {
      estado = sprintf(i18n.sinSucursales || '', postcode);
    }

    var detalle = confirmada ? lista.details[selected] : null;
    var mostrarError = !!(validationError && !validationError.hidden && validationError.message);

    return el(
      'div',
      { className: 'wc-block-components-shipping-rates-control andreani-bloque-sucursal' },
      el('label', { className: 'andreani-bloque-sucursal__label', htmlFor: SELECT_ID }, i18n.titulo || ''),
      el(
        'select',
        {
          id: SELECT_ID,
          className: 'andreani-bloque-sucursal__select',
          value: confirmada ? selected : '',
          disabled: status !== 'ready' || options.length === 0,
          onChange: onChange,
          'aria-describedby': ESTADO_ID,
          'aria-invalid': mostrarError ? 'true' : 'false'
        },
        el('option', { value: '' }, i18n.placeholder || ''),
        options.map(function (o) {
          return el('option', { key: o.codigo, value: o.codigo }, o.texto);
        })
      ),
      el('p', { id: ESTADO_ID, className: 'andreani-bloque-sucursal__estado', 'aria-live': 'polite' }, estado),
      detalle
        ? el(
          'div',
          { className: 'andreani-bloque-sucursal__detalle' },
          detalle.descripcion ? el('strong', { className: 'andreani-bloque-sucursal__nombre' }, String(detalle.descripcion)) : null,
          detalle.direccion
            ? el(
              'span',
              { className: 'andreani-bloque-sucursal__direccion' },
              i18n.direccion ? el('span', { className: 'andreani-bloque-sucursal__direccion-label' }, i18n.direccion + ' ') : null,
              String(detalle.direccion)
            )
            : null
        )
        : null,
      mostrarError
        ? el('div', { className: 'wc-block-components-validation-error', role: 'alert' }, el('p', null, validationError.message))
        : null
    );
  }

  registerCheckoutBlock({
    metadata: {
      name: BLOCK_NAME,
      parent: ['woocommerce/checkout-shipping-methods-block'],
      attributes: {
        lock: { type: 'object', default: { remove: true, move: true } }
      }
    },
    component: AndreaniSucursalSelector
  });
})(window.wp, window.wc);
