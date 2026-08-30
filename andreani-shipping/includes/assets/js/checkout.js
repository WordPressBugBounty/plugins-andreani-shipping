/**
 * Andreani Checkout — v1.5.0
 *
 * Modelo "zero-maintenance": el plugin NO detecta builders. El JS se bindea
 * a cualquier `.andreani-sucursales-select` que haya en el DOM, venga del
 * hook clasico de WooCommerce, del shortcode `[andreani_sucursales]`, o de
 * una integracion custom.
 *
 * Contrato publico (estable a partir de 1.5.0):
 *   - Clases CSS: .andreani-sucursales-select, .andreani-sucursales-standalone,
 *                 .andreani-sucursales-row (classic), .andreani-dni-field-shortcode
 *   - Eventos en document:
 *       andreani:ready          -> el plugin termino de bindear
 *       andreani:cp-changed     -> detail: { postcode }
 *       andreani:sucursal-selected -> detail: { code, nombre, direccion, wrapper, postcode }
 *       andreani:error          -> detail: { code, message?, postcode?, wrapper? }
 *   - API JS:
 *       window.andreaniCheckout.refresh([wrapper])     // recarga sucursales
 *       window.andreaniCheckout.getSelected([wrapper]) // info de la seleccion actual
 *       window.andreaniCheckout.init([wrapper])        // bindear DOM inyectado dinamicamente
 */
(function ($) {
  "use strict";

  /* ==========================================================================
     Constantes del contrato publico
     ========================================================================== */

  var POSTCODE_SELECTORS = [
    'input[name="billing_postcode"]',
    'input[name="shipping_postcode"]',
    'input[name="calc_shipping_postcode"]'
  ].join(",");
  // Fallback amplio para compat con instalaciones que usan names no-estandar.
  var POSTCODE_FALLBACK = 'input[name*="postcode"]';

  var SELECT_SELECTOR    = ".andreani-sucursales-select";
  var WRAPPER_STANDALONE = ".andreani-sucursales-standalone";
  var WRAPPER_CLASSIC    = ".andreani-sucursales-row";
  var BOUND_FLAG         = "data-andreani-bound";

  var EV = {
    ready:    "andreani:ready",
    cpChange: "andreani:cp-changed",
    selected: "andreani:sucursal-selected",
    error:    "andreani:error"
  };

  /* ==========================================================================
     Helpers puros
     ========================================================================== */

  function emit(name, detail) {
    detail = detail || {};
    $(document).trigger(name, [detail]);
    try {
      document.dispatchEvent(new CustomEvent(name, { detail: detail, bubbles: true }));
    } catch (_) { /* navegadores viejos */ }
  }

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str == null ? "" : String(str);
    return div.innerHTML;
  }

  function i18n(key, fallback) {
    return (typeof andreaniCheckout !== "undefined"
      && andreaniCheckout.i18n
      && andreaniCheckout.i18n[key]) || fallback;
  }

  // Resuelve el wrapper relevante para un select dado. Preferimos el wrapper
  // explicito del shortcode; si no, la fila legacy del checkout clasico;
  // ultimo recurso, el parent directo.
  function getWrapperFor($select) {
    var $standalone = $select.closest(WRAPPER_STANDALONE);
    if ($standalone.length) return $standalone;
    var $classic = $select.closest(WRAPPER_CLASSIC);
    if ($classic.length) return $classic;
    return $select.parent();
  }

  // Obtiene el CP asociado al wrapper. Cascada: form del wrapper -> global -> fallback amplio.
  function getPostcodeForWrapper($wrapper) {
    var $form = $wrapper.closest("form");
    if ($form.length) {
      var cp = $form.find(POSTCODE_SELECTORS).first().val();
      if (cp) return cp;
    }
    var cpGlobal = $(POSTCODE_SELECTORS).first().val();
    if (cpGlobal) return cpGlobal;
    return $(POSTCODE_FALLBACK).first().val() || "";
  }

  // Estado por wrapper (loadedPostcodes + details del AJAX). Usamos una property
  // directa en el nodo DOM para no depender de WeakMap en browsers viejos y evitar
  // acumulacion de jQuery.data() keys.
  function getState($wrapper) {
    var node = $wrapper.get(0);
    if (!node) return { loadedPostcodes: {}, details: {} };
    if (!node.__andreani) node.__andreani = { loadedPostcodes: {}, details: {}, loading: false };
    return node.__andreani;
  }

  function isWcCheckoutContext() {
    return $("form.checkout").length > 0 && typeof wc_checkout_params !== "undefined";
  }

  function isAndreaniSucursalMethod(method) {
    var m = (method || "").toLowerCase();
    return m.indexOf("andreani") !== -1 && m.indexOf("sucursal") !== -1;
  }

  /* ==========================================================================
     Nucleo del plugin
     ========================================================================== */

  var Andreani = {
    // Estado SOLO del modo classic (seleccion preservada entre re-renders de WC).
    savedSucursalCode: null,
    savedSucursalNombre: null,
    savedSucursalDireccion: null,
    debounceTimer: null,

    debounce(fn, wait) {
      var self = this;
      return function () {
        var args = arguments;
        clearTimeout(self.debounceTimer);
        self.debounceTimer = setTimeout(function () { fn.apply(self, args); }, wait);
      };
    },

    /* ------------------------------------------------------------------------
       Bootstrap
       ------------------------------------------------------------------------ */

    init() {
      if (typeof andreaniCheckout === "undefined") {
        console.warn("[Andreani] andreaniCheckout no esta definido — script no iniciado.");
        return;
      }

      this.scan();
      this.bindGlobal();

      // WC y otros plugins re-renderean por AJAX. Re-escaneamos selects no bindeados.
      $(document).on("ajaxComplete.andreani", () => this.scan());

      if (isWcCheckoutContext()) {
        this.bindWcCheckout();
      }

      emit(EV.ready, { wcClassic: isWcCheckoutContext() });
    },

    // Busca selects no-bindeados y les agrega handlers. Idempotente.
    scan() {
      var self = this;
      $(SELECT_SELECTOR + ':not([' + BOUND_FLAG + '="true"])').each(function () {
        self.bindSelect($(this));
      });
    },

    bindSelect($select) {
      $select.attr(BOUND_FLAG, "true");
      var $wrapper = getWrapperFor($select);

      // Hidratacion inicial: si ya hay un CP en el form, precargar sucursales.
      var cp = getPostcodeForWrapper($wrapper);
      if (cp && cp.length >= 4) {
        this.loadSucursales($wrapper, cp);
      }

      $select.on("change.andreani", () => this.onSucursalChange($wrapper));

      // Focus/click: carga on-demand si el select esta vacio y tenemos CP.
      $select.on("focus.andreani click.andreani", () => {
        var postcode = getPostcodeForWrapper($wrapper);
        if (!postcode || postcode.length < 4) return;
        if ($select.find("option").length <= 1) {
          this.loadSucursales($wrapper, postcode);
        }
      });
    },

    // Listener global de cambios de CP — re-hidrata todos los wrappers.
    bindGlobal() {
      var self = this;
      var debounced = this.debounce(function (postcode) {
        emit(EV.cpChange, { postcode: postcode });
        $(SELECT_SELECTOR).each(function () {
          self.loadSucursales(getWrapperFor($(this)), postcode);
        });
      }, 300);

      $(document).on("change.andreani blur.andreani", POSTCODE_SELECTORS, function () {
        var postcode = $(this).val();
        if (!postcode || postcode.length < 4) return;
        debounced(postcode);
      });
    },

    /* ------------------------------------------------------------------------
       Integracion con WooCommerce checkout clasico
       ------------------------------------------------------------------------ */

    bindWcCheckout() {
      this.ensureSyncFields();

      $(document.body).on("update_checkout.andreani", () => {
        this.saveCurrentSelection();
      });

      $(document.body).on("updated_checkout.andreani", () => {
        this.ensureSyncFields();
        this.scan();
        this.restoreSavedSelection();
        this.checkVisibilityClassic();
      });

      $("form.checkout").on("checkout_place_order.andreani", () => this.validateSubmit());

      $(document).on("change.andreani", 'input[name="shipping_method[0]"]', () => this.checkVisibilityClassic());
      $(document).on("change.andreani", "#ship-to-different-address-checkbox", () => this.checkVisibilityClassic());

      this.checkVisibilityClassic();
      this.observeShippingChangesClassic();
    },

    // Hidden inputs dentro del form para que la seleccion sobreviva
    // al re-render de WC tras updated_checkout.
    ensureSyncFields() {
      var $form = $("form.checkout");
      if ($form.length === 0) return;
      if ($("#sucursales_andreani_sync").length === 0) {
        $form.append(
          '<input type="hidden" name="sucursales_andreani_sync" id="sucursales_andreani_sync" value="">' +
          '<input type="hidden" name="sucursal_nombre_sync" id="sucursal_nombre_sync" value="">' +
          '<input type="hidden" name="sucursal_direccion_sync" id="sucursal_direccion_sync" value="">'
        );
      }
    },

    saveCurrentSelection() {
      var val = $("#sucursales_andreani").val();
      if (!val || val === "0") return;
      this.savedSucursalCode = val;
      this.savedSucursalNombre = $("#sucursal_nombre").val() || "";
      this.savedSucursalDireccion = $("#sucursal_direccion").val() || "";
      $("#sucursales_andreani_sync").val(this.savedSucursalCode);
      $("#sucursal_nombre_sync").val(this.savedSucursalNombre);
      $("#sucursal_direccion_sync").val(this.savedSucursalDireccion);
    },

    restoreSavedSelection() {
      if (!this.savedSucursalCode) return;
      var $select = $("#sucursales_andreani");
      if ($select.find('option[value="' + this.savedSucursalCode + '"]').length) {
        $select.val(this.savedSucursalCode);
        this.onSucursalChange(getWrapperFor($select));
      }
    },

    observeShippingChangesClassic() {
      var el = document.querySelector(".woocommerce-shipping-methods, #shipping_method");
      if (!el || !window.MutationObserver) return;
      var obs = new MutationObserver(this.debounce(() => this.checkVisibilityClassic(), 150));
      obs.observe(el, { childList: true, subtree: true });
    },

    checkVisibilityClassic() {
      if (!isWcCheckoutContext()) return;
      var $row = $(WRAPPER_CLASSIC);
      if ($row.length === 0) return;

      if (!this.needsSucursalesClassic()) {
        $row.hide();
        $("#andreani-sucursal-details").hide().empty();
        return;
      }
      $row.show();
    },

    needsSucursalesClassic() {
      var chosen = $('input[name="shipping_method[0]"]:checked').val() || "";
      if (chosen) return isAndreaniSucursalMethod(chosen);
      var all = $('input[name="shipping_method[0]"]');
      if (all.length === 1) return isAndreaniSucursalMethod(all.val() || "");
      return false;
    },

    /* ------------------------------------------------------------------------
       AJAX: carga de sucursales por wrapper
       ------------------------------------------------------------------------ */

    loadSucursales($wrapper, postcode) {
      var $select = $wrapper.find(SELECT_SELECTOR);
      if ($select.length === 0) return;

      var state = getState($wrapper);
      var key = String(postcode);
      if (state.loadedPostcodes[key]) return;
      if (state.loading) return;
      state.loading = true;

      $select.prop("disabled", true)
        .empty()
        .append('<option value="0">' + escapeHtml(i18n("loading_sucursales", "Cargando sucursales...")) + '</option>');

      $.ajax({
        url: andreaniCheckout.ajaxUrl,
        type: "POST",
        data: {
          action: "andreani_get_sucursales",
          postcode: postcode,
          nonce: andreaniCheckout.nonce
        },
        success: (response) => {
          $select.empty();
          if (response && response.success && response.data && response.data.options) {
            $select.append('<option value="0">' + escapeHtml(i18n("select_sucursal", "Seleccione una sucursal")) + '</option>');
            $.each(response.data.options, function (value, text) {
              if (value !== "0") $select.append($("<option>", { value: value, text: text }));
            });
            state.details = response.data.details || {};
            state.loadedPostcodes[key] = true;

            // Restaurar seleccion guardada (solo classic tiene savedSucursalCode).
            if (this.savedSucursalCode && $select.find('option[value="' + this.savedSucursalCode + '"]').length) {
              $select.val(this.savedSucursalCode);
              this.onSucursalChange($wrapper);
            }
          } else {
            $select.append('<option value="0">No hay sucursales disponibles</option>');
            emit(EV.error, { code: "no_sucursales", postcode: postcode, wrapper: $wrapper.get(0) });
          }
        },
        error: () => {
          $select.empty().append('<option value="0">Error al cargar sucursales</option>');
          emit(EV.error, { code: "ajax_error", postcode: postcode, wrapper: $wrapper.get(0) });
        },
        complete: () => {
          $select.prop("disabled", false);
          state.loading = false;
        }
      });
    },

    /* ------------------------------------------------------------------------
       Cambio de seleccion — unificado para classic y standalone
       ------------------------------------------------------------------------ */

    onSucursalChange($wrapper) {
      var $select = $wrapper.find(SELECT_SELECTOR);
      var value = $select.val();

      if (!value || value === "0") {
        $wrapper.find('[name^="sucursal_nombre"], [id^="sucursal_nombre"]').val("");
        $wrapper.find('[name^="sucursal_direccion"], [id^="sucursal_direccion"]').val("");
        $wrapper.find(".andreani-sucursales-details, .andreani-sucursal-details").hide().empty();
        $("#andreani-sucursal-details").hide().empty();
        return;
      }

      var info = this.resolveSucursalInfo($wrapper, value);

      // Escribir en hidden fields del wrapper (classic y standalone comparten estructura).
      $wrapper.find('[name^="sucursal_nombre"], [id^="sucursal_nombre"]').val(info.descripcion);
      $wrapper.find('[name^="sucursal_direccion"], [id^="sucursal_direccion"]').val(info.direccion);

      // Sync fields para WC classic (preservan seleccion tras updated_checkout).
      if (isWcCheckoutContext()) {
        this.savedSucursalCode = value;
        this.savedSucursalNombre = info.descripcion;
        this.savedSucursalDireccion = info.direccion;
        $("#sucursales_andreani_sync").val(value);
        $("#sucursal_nombre_sync").val(info.descripcion);
        $("#sucursal_direccion_sync").val(info.direccion);
        this.clearErrorsClassic();
      }

      this.renderDetails($wrapper, info);

      emit(EV.selected, {
        code: value,
        nombre: info.descripcion,
        direccion: info.direccion,
        wrapper: $wrapper.get(0),
        postcode: getPostcodeForWrapper($wrapper)
      });
    },

    // Resuelve descripcion + direccion desde el cache del wrapper o,
    // en su defecto, parseando el texto del <option> seleccionado.
    resolveSucursalInfo($wrapper, value) {
      var state = getState($wrapper);
      var info = { descripcion: "", direccion: "" };

      if (state.details && state.details[value]) {
        info.descripcion = state.details[value].descripcion || "";
        info.direccion = state.details[value].direccion || "";
        return info;
      }

      var $select = $wrapper.find(SELECT_SELECTOR);
      var text = $select.find("option:selected").text();
      var parts = text.split(" - ");
      if (parts.length >= 2) {
        info.descripcion = parts.slice(0, 2).join(" - ");
        info.direccion = parts.slice(2).join(" - ");
      } else {
        info.descripcion = text;
      }
      return info;
    },

    renderDetails($wrapper, info) {
      // Standalone trae su propio div de details; classic usa uno global.
      var $details = $wrapper.find(".andreani-sucursales-details, .andreani-sucursal-details");
      if ($details.length === 0) $details = $("#andreani-sucursal-details");
      if ($details.length === 0 || !info.descripcion) {
        if ($details.length) $details.hide().empty();
        return;
      }
      var html = '<div class="andreani-sucursal-info"><strong>' + escapeHtml(info.descripcion) + '</strong>';
      if (info.direccion) {
        html += '<div class="andreani-sucursal-direccion">' + escapeHtml(info.direccion) + '</div>';
      }
      html += '</div>';
      $details.html(html).fadeIn(200);
    },

    /* ------------------------------------------------------------------------
       Validacion submit — solo aplica en WC classic
       ------------------------------------------------------------------------ */

    validateSubmit() {
      if (!this.needsSucursalesClassic()) {
        this.clearErrorsClassic();
        return true;
      }
      var $select = $("#sucursales_andreani");
      var value = $select.val();
      if ((!value || value === "0") && this.savedSucursalCode) {
        value = this.savedSucursalCode;
        if ($select.find('option[value="' + value + '"]').length) {
          $select.val(value);
          this.onSucursalChange(getWrapperFor($select));
        }
      }
      if (!value || value === "0") {
        var msg = i18n("error_sucursal_required", "Por favor seleccione una sucursal para el envío.");
        this.showErrorClassic(msg);
        emit(EV.error, { code: "sucursal_required", message: msg });
        return false;
      }
      this.clearErrorsClassic();
      return true;
    },

    showErrorClassic(message) {
      var $select = $("#sucursales_andreani");
      var $row = $(WRAPPER_CLASSIC);
      $select.addClass("woocommerce-invalid");
      $row.addClass("woocommerce-invalid");
      if ($row.find(".woocommerce-error").length === 0) {
        $row.find("td").append('<div class="woocommerce-error" role="alert">' + escapeHtml(message) + '</div>');
      }
      if ($select.length && $select.is(":visible")) {
        $("html, body").animate({ scrollTop: $select.offset().top - 100 }, 300);
        $select.focus();
      }
    },

    clearErrorsClassic() {
      var $select = $("#sucursales_andreani");
      var $row = $(WRAPPER_CLASSIC);
      $select.removeClass("woocommerce-invalid");
      $row.removeClass("woocommerce-invalid");
      $row.find(".woocommerce-error").remove();
    },

    /* ------------------------------------------------------------------------
       API publica (expuesta en window.andreaniCheckout)
       ------------------------------------------------------------------------ */

    publicRefresh($wrapper) {
      var self = this;
      var $targets = ($wrapper && $wrapper.length)
        ? $wrapper
        : $(SELECT_SELECTOR).map(function () { return getWrapperFor($(this)).get(0); });

      $targets.each(function () {
        var $w = $(this);
        var cp = getPostcodeForWrapper($w);
        if (!cp || cp.length < 4) return;
        var state = getState($w);
        delete state.loadedPostcodes[String(cp)];
        self.loadSucursales($w, cp);
      });
    },

    publicGetSelected($wrapper) {
      if (!$wrapper || !$wrapper.length) {
        $wrapper = $(WRAPPER_STANDALONE).first();
        if ($wrapper.length === 0) $wrapper = $(WRAPPER_CLASSIC).first();
      }
      if ($wrapper.length === 0) return null;
      var $select = $wrapper.find(SELECT_SELECTOR);
      var value = $select.val();
      if (!value || value === "0") return null;
      var info = this.resolveSucursalInfo($wrapper, value);
      return { code: value, nombre: info.descripcion, direccion: info.direccion };
    },

    publicInit(target) {
      if (!target) { this.scan(); return; }
      var $w = $(target);
      var self = this;
      $w.find(SELECT_SELECTOR + ':not([' + BOUND_FLAG + '="true"])').each(function () {
        self.bindSelect($(this));
      });
    }
  };

  /* ==========================================================================
     Arranque + exposicion API
     ========================================================================== */

  $(document).ready(() => Andreani.init());

  // andreaniCheckout ya existe como objeto localizado (ajaxUrl, nonce, i18n).
  // Sumamos los metodos de la API publica sin pisar las props.
  if (typeof window.andreaniCheckout === "object" && window.andreaniCheckout !== null) {
    window.andreaniCheckout.refresh = function (wrapper) {
      Andreani.publicRefresh(wrapper ? $(wrapper) : null);
    };
    window.andreaniCheckout.getSelected = function (wrapper) {
      return Andreani.publicGetSelected(wrapper ? $(wrapper) : null);
    };
    window.andreaniCheckout.init = function (wrapper) {
      Andreani.publicInit(wrapper || null);
    };
  }

  // Compat: referencia global al nucleo (no-contrato).
  window.AndreaniCheckout = Andreani;
})(jQuery);
