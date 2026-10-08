/**
 * Andreani Admin JavaScript
 */
(function($) {
  'use strict';

  /* --- Shared utility --- */
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  }

  function escapeAttr(str) {
    return escapeHtml(String(str == null ? '' : str)).replace(/"/g, '&quot;');
  }

  function howHtml(mode, text) {
    const art = { apilado: 'apilado', multibulto: 'piezas' }[mode];
    return art && text
      ? '<span class="andreani-how"><svg class="andreani-how__icon" data-andr-icon="' + art + '" aria-hidden="true" focusable="false"></svg>' + escapeHtml(text) + '</span>'
      : '';
  }

  /* ========================================
   * RESULTADO DE COTIZACIÓN (AndreaniQuote)
   * ======================================== */
  const AndreaniQuote = {
    config() {
      return window.andreani_admin || {};
    },

    t(key) {
      return (this.config().i18n || {})[key] || '';
    },

    kind(rate) {
      const key = (String(rate.label || '') + ' ' + String(rate.id || ''))
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[-_]+/g, ' ');

      if (key.indexOf('sucursal') !== -1) return { icon: 'en-sucursal', title: 'rate_branch' };
      if (/llega hoy|same ?day/.test(key)) return { icon: 'llega-hoy', title: 'rate_today' };
      if (key.indexOf('bigger') !== -1) return { icon: 'estandar', title: 'rate_home', chip: 'rate_bigger' };
      return { icon: 'estandar', title: 'rate_home' };
    },

    price(cost) {
      const parts = (parseFloat(cost) || 0).toFixed(2).split('.');
      return '<span class="andr-rate__amount">$ ' + parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '</span>'
        + '<span class="andr-rate__cents">' + parts[1] + '</span>';
    },

    card(rate, cheapest) {
      const base = this.config().services_icons_url || '';
      const kind = this.kind(rate);
      const chip = (tone, key) => ' <span class="andr-badge andr-badge--' + tone + ' andr-badge--sm">' + escapeHtml(this.t(key)) + '</span>';

      return '<div class="andr-rate">'
        + '<span class="andr-rate__icon">'
        + '<img class="andr-rate__img" src="' + escapeAttr(base + kind.icon + '.svg') + '" alt="">'
        + '<img class="andr-rate__img andr-rate__img--hover" src="' + escapeAttr(base + kind.icon + '-rojo.svg') + '" alt="">'
        + '</span>'
        + '<span class="andr-rate__text">'
        + '<span class="andr-rate__title">' + escapeHtml(this.t(kind.title)) + (kind.chip ? chip('info', kind.chip) : '') + (cheapest ? chip('success', 'rate_cheapest') : '') + '</span>'
        + '<span class="andr-rate__sub">' + escapeHtml(String(rate.label || '')) + '</span>'
        + '</span>'
        + '<span class="andr-rate__price">' + this.price(rate.cost) + '</span>'
        + '</div>';
    },

    loading($box) {
      AndreaniLoader.show($box.prop('hidden', false), { size: 'sm', text: (this.config().i18n || {}).quote_loading });
    },

    rates($box, rates, skipped) {
      const cheapest = rates.length > 1 ? Math.min.apply(null, rates.map((r) => parseFloat(r.cost) || 0)) : null;
      let marked = false;

      const note = skipped && skipped.length
        ? '<div class="andr-dispatch__warnbox">' + escapeHtml(this.t('sim_skipped') + ' ' + skipped.join(', ')) + '</div>'
        : '';

      $box.removeAttr('aria-busy').html(rates.map((r) => {
        const isCheapest = !marked && cheapest !== null && (parseFloat(r.cost) || 0) === cheapest;
        marked = marked || isCheapest;
        return this.card(r, isCheapest);
      }).join('') + note).prop('hidden', false);
    },

    error($box, message) {
      $box.removeAttr('aria-busy').html('<div class="andr-dispatch__warnbox">' + escapeHtml(message) + '</div>').prop('hidden', false);
    },

    show($box, res, xhr) {
      const i18n = this.config().i18n || {};

      if (xhr) {
        const data = (xhr.responseJSON && xhr.responseJSON.data) || {};
        this.error($box, data.message || i18n.quote_error || 'Error de red.');
      } else if (res.success && res.data.rates && res.data.rates.length) {
        this.rates($box, res.data.rates, res.data.skipped);
      } else {
        this.error($box, (res.data && res.data.message) || i18n.quote_error || 'Error al cotizar.');
      }
    }
  };

  /* ========================================
   * SETTINGS PAGE (AndreaniAdmin)
   * ======================================== */
  const AndreaniAdmin = {
    originalCredential: '',

    config: window.andreani_admin || {},

    init() {
      this.bindFormEvents();
      this.initProductsWarning();
      this.initCredentialField();
      this.initCpOrigenValidation();
      this.initRefreshContratos();
      this.initConfigPorModo();
      this.initToggles();
      this.initTraerDireccion();
      this.initOrigen();
      // Diferido: AndreaniTabs.init() corre despues y restaura la solapa guardada,
      // que pisaria el foco si lo pusieramos ahora.
      setTimeout(() => this.focusCredentialWhenEmpty(), 0);
    },

    /* --- Event Bindings --- */
    bindFormEvents() {
      $(document).on('submit', 'form', (e) => this.validateForm(e));
      $(document).on('click', '.andreani-cancel-edit', (e) => {
        e.preventDefault();
        this.cancelCredentialEdit();
      });
    },

    /* --- Products Warning (collapsible) --- */
    initProductsWarning() {
      $('.andreani-products-warning').on('click', '.andreani-products-warning__header', (e) => {
        const $warning = $(e.currentTarget).closest('.andreani-products-warning');
        $warning.toggleClass('andreani-products-warning--collapsed andreani-products-warning--expanded');
      });
    },

    /* --- Credential Field --- */
    initCredentialField() {
      const $field = $('input[id*="hash_andreani"]');
      if (!$field.length) return;

      this.originalCredential = $field.val();

      // Bloquear autofill agresivo del browser (Chrome ignora autocomplete=off
      // en password fields). Combo: new-password + readonly hasta primer focus.
      $field.attr({
        autocomplete: 'new-password',
        autocorrect: 'off',
        autocapitalize: 'off',
        spellcheck: 'false',
      });
      if (!this.originalCredential) {
        $field.attr('readonly', 'readonly');
        $field.one('focus mousedown', function() {
          $(this).removeAttr('readonly');
        });
      }

      $field.wrap('<div class="andreani-credential-wrapper"></div>');

      const $toggle = $('<span class="andreani-credential-toggle dashicons dashicons-visibility" title="Mostrar/ocultar credencial"></span>');
      $field.after($toggle);
      $toggle.on('click', function() {
        const isPassword = $field.attr('type') === 'password';
        $field.attr('type', isPassword ? 'text' : 'password');
        $toggle.toggleClass('dashicons-visibility dashicons-hidden');
      });

      const $cancel = $('<button type="button" class="andreani-cancel-edit">Cancelar</button>').hide();
      $field.closest('.andreani-credential-wrapper').after($cancel);

      $field.on('input', () => {
        const hasChanged = $field.val() !== this.originalCredential;
        $('.andreani-cancel-edit').toggle(hasChanged);
        $('.andreani-cliente-info').toggleClass('andreani-cliente-info--hidden', hasChanged);
        $('.andreani-cliente-summary').toggleClass('andreani-cliente-summary--hidden', hasChanged);
        $('.andreani-modos-panel--contratos').toggleClass('andreani-modos-panel--hidden', hasChanged);
      });
    },

    cancelCredentialEdit() {
      const $field = $('input[id*="hash_andreani"]');
      $field.val(this.originalCredential).blur();
      $('.andreani-cancel-edit').hide();
      $('.andreani-cliente-info').removeClass('andreani-cliente-info--hidden');
      $('.andreani-cliente-summary').removeClass('andreani-cliente-summary--hidden');
      $('.andreani-modos-panel--contratos').removeClass('andreani-modos-panel--hidden');
    },

    focusCredentialField() {
      const $field = $('input[id*="hash_andreani"]');
      if (!$field.length) return;
      // El campo arranca readonly como anti-autofill: sin quitarlo, el foco
      // programatico deja el input enfocado pero no editable.
      $field.removeAttr('readonly').trigger('focus');
    },

    focusCredentialWhenEmpty() {
      const $field = $('input[id*="hash_andreani"]');
      if (!$field.length || String($field.val() || '').trim()) return;

      const $tab = $('.andr-tabs__item[data-tab="cuenta"]');
      if ($tab.length && !$tab.hasClass('andr-tabs__item--active')) {
        $tab.trigger('click');
      }

      this.focusCredentialField();
    },

    /* --- CP Origen Status --- */
    initCpOrigenValidation() {
      const $cp = $('input[id*="cp_origen"]');
      if (!$cp.length) return;

      const $status = $('<div class="andreani-cp-status"></div>');
      $cp.closest('td, fieldset').append($status);

      // Si el back marcó el CP como inválido, mostrar warning al cargar (solo si hay un CP guardado)
      if (this.config.cp_origen_saved && this.config.cp_origen_valid === 'no') {
        $status.text('No se encontró ninguna sucursal de Andreani que atienda este código postal.')
          .addClass('andreani-cp-status--error').show();
        $cp.addClass('andreani-field-error');
      }

      // Limpiar estado al editar
      $cp.on('input', () => {
        $status.removeClass('andreani-cp-status--success andreani-cp-status--error').text('').hide();
        $cp.removeClass('andreani-field-error andreani-field-success');
      });
    },

    /* --- Traer la dirección de la tienda --- */
    initTraerDireccion() {
      $(document).on('click', '.andreani-origen-traer', (e) => {
        const datos = $(e.currentTarget).data('andreani-origen-tienda');
        if (!datos || typeof datos !== 'object') return;

        Object.keys(datos).forEach((campo) => {
          if (campo === 'cp_tienda') return;
          const $input = $('#andreani_origen_' + campo);
          if ($input.length) $input.val(datos[campo]).trigger('change');
        });

        const $cp = $('input[id*="cp_origen"]');
        const cpTienda = this.normalizarCp(datos.cp_tienda);
        const cpCambio = Boolean(cpTienda && $cp.length && this.normalizarCp($cp.val()) !== cpTienda);
        if (cpCambio) {
          $cp.val(cpTienda).trigger('input').trigger('blur');
        }

        $('[data-andreani-origen-desde-tienda]').val('1');
        const $boton = $(e.currentTarget);
        $boton.siblings('.andreani-origen-traer__aviso').prop('hidden', false);
        $boton.siblings('[data-andreani-origen-aviso-cp]')
          .text(this.i18n('origen_cp_traido', 'También actualizamos tu código postal de origen con el de la tienda (%s). Si despachás desde otro lugar, corregilo antes de guardar.').replace('%s', cpTienda))
          .prop('hidden', !cpCambio);
      });

      // Si despues de traerla la retoca a mano, la direccion vuelve a ser suya.
      $(document).on('input', '.andreani-origen-grid input[type="text"]', () => {
        $('[data-andreani-origen-desde-tienda]').val('');
      });
    },

    /* --- Sucursal de origen --- */
    origenRequest: null,
    origenTimer: null,
    origenPostcode: '',

    initOrigen() {
      const $lista = $('[data-andreani-origen-lista]');
      const $cp = $('input[id*="cp_origen"]');
      if (!$lista.length || !$cp.length) return;

      this.origenPostcode = String($cp.val() || '').trim();
      this.initOrigenBuscador($lista);

      const refresh = () => {
        const postcode = String($cp.val() || '').trim();
        if (postcode === this.origenPostcode) return;
        this.origenPostcode = postcode;

        if (!postcode) {
          this.renderOrigenEstado($lista, 'vacio', this.i18n('origen_vacio', 'Cargá tu código postal de origen para ver las sucursales disponibles.'));
          return;
        }

        if (!/^\d{4}$/.test(postcode) && !/^[A-Za-z]\d{4}[A-Za-z]{3}$/.test(postcode)) {
          this.renderOrigenEstado($lista, 'vacio', this.i18n('origen_cp_invalido', 'El código postal no tiene un formato válido (ej: 1425 o C1425ABC).'));
          return;
        }

        this.loadOrigenSucursales($lista, postcode);
      };

      $cp.on('input', () => {
        clearTimeout(this.origenTimer);
        this.origenTimer = setTimeout(refresh, 1000);
      });

      $cp.on('blur', () => {
        clearTimeout(this.origenTimer);
        refresh();
      });
    },

    loadOrigenSucursales($lista, postcode) {
      if (this.origenRequest) {
        this.origenRequest.abort();
        this.origenRequest = null;
      }

      this.renderOrigenEstado($lista, 'cargando', this.i18n('origen_cargando', 'Buscando sucursales...'));

      this.origenRequest = $.post(ajaxurl, {
        action: 'andreani_origen_sucursales',
        nonce: this.config.nonce_origen_sucursales,
        postcode: postcode
      })
        .done((res) => {
          const sucursales = (res && res.success && res.data && res.data.sucursales) || [];
          if (!sucursales.length) {
            this.renderOrigenEstado($lista, 'sin-resultados', this.i18n('origen_sin_resultados', 'No encontramos sucursales habilitadas como origen para ese código postal.'));
            return;
          }
          this.renderOrigenOpciones($lista, sucursales);
          this.loadOrigenDefault($lista, postcode);
        })
        .fail((jqXHR, textStatus) => {
          if (textStatus === 'abort') return;
          const message = (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message)
            || this.i18n('origen_error', 'No pudimos traer las sucursales. Probá de nuevo en unos minutos.');
          this.renderOrigenEstado($lista, 'error', message);
        })
        .always(() => { this.origenRequest = null; });
    },

    loadOrigenDefault($lista, postcode) {
      $.post(ajaxurl, {
        action: 'andreani_origen_default',
        nonce: this.config.nonce_origen_default,
        postcode: postcode
      }).done((res) => {
        const data = (res && res.success && res.data) || {};
        if (!data.nombre) return;

        const $auto = $lista.find('.andreani-origen-opcion--auto');
        const $cuerpo = $auto.find('.andreani-origen-opcion__cuerpo');
        if (!$cuerpo.length) return;

        $cuerpo.html(
          '<span class="andreani-origen-opcion__titulo">' + escapeHtml(data.nombre) +
            '<span class="andreani-origen-opcion__tag">' + escapeHtml(this.i18n('origen_auto_tag', 'Por defecto')) + '</span>' +
          '</span>' +
          (data.direccion ? '<span class="andreani-origen-opcion__detalle">' + escapeHtml(data.direccion) + '</span>' : '') +
          '<span class="andreani-origen-opcion__detalle">' +
            escapeHtml(this.i18n('origen_auto_desc', 'Es la que Andreani asigna para tu código postal. Si cambia, se actualiza sola.')) +
          '</span>'
        );

        this.quitarOrigenDuplicada($lista, $auto, String(data.codigo || ''));
      });
    },

    quitarOrigenDuplicada($lista, $auto, codigo) {
      if (!codigo) return;

      const $radio = $lista
        .find('.andreani-origen-opcion').not($auto)
        .find('input[type="radio"]')
        .filter(function () { return $(this).val() === codigo; });
      if (!$radio.length) return;

      if ($radio.is(':checked')) $auto.find('input[type="radio"]').prop('checked', true);

      const prefijo = 'andreani_origen[sucursales][' + codigo + ']';
      $lista.find('input[type="hidden"]')
        .filter(function () { return String(this.name || '').indexOf(prefijo) === 0; })
        .remove();
      $radio.closest('.andreani-origen-opcion').remove();
      this.syncOrigenBuscador($lista);
    },

    initOrigenBuscador($lista) {
      const $buscador = $('[data-andreani-origen-buscador]');
      const $input = $buscador.find('[data-andreani-origen-filtro]');
      if (!$buscador.length || !$input.length) return;

      $input.on('keydown', (e) => {
        if (e.key === 'Enter') e.preventDefault();
      });

      $input.on('input search', () => this.filtrarOrigenOpciones($lista));

      // El link vive dentro del <label>: sin esto, abrirlo tambien marca el radio.
      $lista.on('click', '.andreani-origen-opcion__mapa', (e) => e.stopPropagation());

      this.syncOrigenBuscador($lista);
    },

    syncOrigenBuscador($lista) {
      const $buscador = $('[data-andreani-origen-buscador]');
      if (!$buscador.length) return;

      const total = $lista.find('.andreani-origen-opcion').not('.andreani-origen-opcion--auto').length;
      $buscador.prop('hidden', total === 0);
      $buscador.find('[data-andreani-origen-filtro]').val('');
      this.filtrarOrigenOpciones($lista);
    },

    filtrarOrigenOpciones($lista) {
      const $buscador = $('[data-andreani-origen-buscador]');
      const $contador = $buscador.find('[data-andreani-origen-contador]');
      const termino = String($buscador.find('[data-andreani-origen-filtro]').val() || '')
        .trim()
        .toLowerCase();

      const $opciones = $lista.find('.andreani-origen-opcion').not('.andreani-origen-opcion--auto');
      let visibles = 0;

      $opciones.each(function () {
        const $opcion = $(this);
        const texto = $opcion.text().toLowerCase();
        const coincide = !termino || texto.indexOf(termino) !== -1;
        $opcion.toggleClass('andreani-origen-opcion--oculta', !coincide);
        if (coincide) visibles += 1;
      });

      $lista.find('.andreani-origen-estado--sin-coincidencias').remove();
      if (termino && visibles === 0) {
        $lista.append(
          '<p class="andreani-origen-estado andreani-origen-estado--sin-coincidencias">' +
            escapeHtml(this.i18n('origen_sin_coincidencias', 'Ninguna sucursal coincide con tu búsqueda.')) +
            '</p>'
        );
      }

      $contador.text(
        termino
          ? this.i18n('origen_contador', '%1$s de %2$s')
              .replace('%1$s', visibles)
              .replace('%2$s', $opciones.length)
          : ''
      );
    },

    renderOrigenEstado($lista, estado, mensaje) {
      $lista.html(estado === 'cargando'
        ? AndreaniLoader.html({ size: 'sm', text: mensaje })
        : '<p class="andreani-origen-estado andreani-origen-estado--' + estado + '">' + escapeHtml(mensaje) + '</p>');
      this.syncOrigenBuscador($lista);
    },

    renderOrigenOpciones($lista, sucursales) {
      const parts = ['<input type="hidden" name="andreani_origen[sucursal_presente]" value="1" />'];

      parts.push(
        '<label class="andr-card andreani-origen-opcion andreani-origen-opcion--auto">' +
          '<input type="radio" name="andreani_origen[sucursal_codigo]" value="" checked />' +
          '<span class="andreani-origen-opcion__cuerpo">' +
            '<span class="andreani-origen-opcion__titulo">' +
              escapeHtml(this.i18n('origen_auto', 'Por defecto — la asigna Andreani por tu código postal')) +
            '</span>' +
          '</span>' +
        '</label>'
      );

      sucursales.forEach((sucursal) => {
        const codigo = escapeAttr(sucursal.codigo || '');
        if (!codigo) return;
        const nombre = escapeHtml(sucursal.nombre || sucursal.codigo || '');
        const direccion = sucursal.direccion
          ? '<span class="andreani-origen-opcion__detalle">' + escapeHtml(sucursal.direccion) + '</span>'
          : '';
        const mapa = sucursal.direccion
          ? '<a class="andreani-origen-opcion__mapa" target="_blank" rel="noopener noreferrer" href="https://www.google.com/maps/search/?api=1&query=' +
            encodeURIComponent(sucursal.direccion + ', Argentina') + '">' +
            escapeHtml(this.i18n('origen_ver_mapa', 'Ver en el mapa')) + '</a>'
          : '';

        parts.push(
          '<label class="andr-card andreani-origen-opcion">' +
            '<input type="radio" name="andreani_origen[sucursal_codigo]" value="' + codigo + '" />' +
            '<span class="andreani-origen-opcion__cuerpo">' +
              '<span class="andreani-origen-opcion__titulo">' + nombre + '</span>' +
              direccion +
            '</span>' +
            mapa +
          '</label>' +
          '<input type="hidden" name="andreani_origen[sucursales][' + codigo + '][nombre]" value="' + escapeAttr(sucursal.nombre || '') + '" />' +
          '<input type="hidden" name="andreani_origen[sucursales][' + codigo + '][direccion]" value="' + escapeAttr(sucursal.direccion || '') + '" />'
        );
      });

      $lista.html(parts.join(''));
      this.syncOrigenBuscador($lista);
    },

    i18n(key, fallback) {
      const strings = this.config.i18n || {};
      return strings[key] || fallback;
    },

    normalizarCp(cp) {
      const valor = String(cp == null ? '' : cp).trim();
      const cpa = valor.match(/^[A-Za-z](\d{4})[A-Za-z]{0,3}$/);
      return cpa ? cpa[1] : valor;
    },

    /* --- Refresh Contratos --- */
    initRefreshContratos() {
      $('.andreani-refresh-contratos').on('click', (e) => {
        e.preventDefault();
        const $btn = $(e.currentTarget);

        $btn.prop('disabled', true);
        const release = AndreaniLoader.busy(this.i18n('loader_contracts', 'Actualizando tus contratos…'));

        $.post(ajaxurl, { action: 'andreani_refresh_contratos', nonce: $btn.data('nonce') })
          .done((res) => {
            if (res.success) {
              this.showNotice(res.data.message, 'success');
              setTimeout(() => location.reload(), 1000);
            } else {
              this.showNotice(res.data?.message || 'Error al actualizar contratos.', 'error');
              $btn.prop('disabled', false);
            }
          })
          .fail(() => {
            this.showNotice('Error de conexión.', 'error');
            $btn.prop('disabled', false);
          })
          .always(release);
      });
    },

    /* --- Config por Modo --- */
    initConfigPorModo() {
      const $cards = $('.andreani-modo-card');
      const $hidden = $('input[id*="config_por_modo"]');
      if (!$cards.length || !$hidden.length) return;

      const syncConfig = () => {
        const config = {};
        $cards.each((_, el) => {
          const $card = $(el);
          const modo = $card.data('modo');
          config[modo] = {
            enabled: $card.find('.andreani-modo-enabled').is(':checked'),
            costo_adicional_enabled: $card.find('.andreani-modo-costo-enabled').is(':checked'),
            costo_adicional: parseFloat($card.find('.andreani-modo-costo').val()) || 0,
            motivo: $card.find('.andreani-modo-motivo').val() || '',
            envio_gratis: $card.find('.andreani-modo-gratis').is(':checked'),
            envio_gratis_monto: parseFloat($card.find('.andreani-modo-monto').val()) || 0
          };
        });
        $hidden.val(JSON.stringify(config));
      };

      const updateStats = ($panel) => {
        const $modoCards = $panel.find('.andreani-modo-card');
        const total = $modoCards.length;
        const enabled = $modoCards.find('.andreani-modo-enabled:checked').length;
        const $stats = $panel.find('.andreani-contratos-stats');
        $stats.find('.andreani-contratos-stats__count').text(`${enabled}/${total}`);
        const state = enabled === total ? 'all' : (enabled > 0 ? 'partial' : 'none');
        $stats
          .removeClass('andreani-contratos-stats--all andreani-contratos-stats--partial andreani-contratos-stats--none')
          .addClass(`andreani-contratos-stats--${state}`);
      };

      $cards
        .on('click', '.andreani-modo-card__header', (e) => {
          if (!$(e.target).closest('.andreani-modo-card__toggle').length) {
            $(e.currentTarget).closest('.andreani-modo-card').toggleClass('andreani-modo-card--collapsed andreani-modo-card--expanded');
          }
        })
        .on('change', '.andreani-modo-enabled', (e) => {
          const $card = $(e.target).closest('.andreani-modo-card');
          $card.toggleClass('andreani-modo-card--disabled', !e.target.checked);
          updateStats($card.closest('.andreani-modos-panel'));
          syncConfig();
        })
        .on('change', '.andreani-modo-costo-enabled', (e) => {
          $(e.target).closest('.andreani-modo-card').find('.andreani-modo-card__field--costo').toggleClass('andreani-hidden', !e.target.checked);
          syncConfig();
        })
        .on('change', '.andreani-modo-gratis', (e) => {
          $(e.target).closest('.andreani-modo-card').find('.andreani-modo-card__field--monto').toggleClass('andreani-hidden', !e.target.checked);
          syncConfig();
        })
        .on('input change', '.andreani-modo-costo, .andreani-modo-monto, .andreani-modo-motivo', syncConfig);

      // Sync inicial: el hidden arranca con lo que PHP renderizó, pero forzamos una
      // pasada para garantizar que refleje el estado actual de las cards ante cualquier
      // desfasaje (ej. valor default vs card enabled por defecto).
      syncConfig();

      // Sync pre-submit: si algún change/input se perdió (race condition, evento
      // interceptado por otro listener), este último sync captura el estado final
      // antes de enviar el POST al server.
      $hidden.closest('form').on('submit', syncConfig);
    },

    /* --- Simple Toggles (Cotizador) --- */
    // El cotizador ahora usa un <select> binario (Desactivado/Activado).
    // La visibilidad del info box (que contiene los subfields) la maneja initConditionalVisibility
    // observando el valor del select cotizador_producto === "yes".
    // Acá solo queda el listener que muestra/oculta el field "Posición" según el modo.
    initToggles() {
      // Delegación en document — el info box puede estar oculto al inicio (data-hidden="true")
      // y los handlers directos en hidden elements no se disparan hasta que se muestran.
      $(document).on('change', '.andreani-cotizador-modo', function() {
        const isAuto = $(this).val() === 'auto';
        $('.andreani-cotizador-config__posicion').toggleClass('andreani-hidden', !isAuto);
      });

      $('.andr-tabs__panel .woocommerce-help-tip').css('margin-right', '12px');
    },

    /* --- Form Validation --- */
    validateForm(e) {
      const $cp = $('input[id*="cp_origen"]');
      if (!$cp.length) return true;

      const errors = [];
      const cpValue = $cp.val().trim();

      if (!cpValue) {
        errors.push('El campo "Código Postal Origen" es obligatorio.');
        this.highlightField($cp);
      } else if (!/^\d{4}$/.test(cpValue) && !/^[A-Za-z]\d{4}[A-Za-z]{3}$/.test(cpValue)) {
        errors.push('El "Código Postal Origen" no tiene un formato válido (ej: 1425 o C1425ABC).');
        this.highlightField($cp);
      }

      const $cred = $('input[id*="hash_andreani"]');
      if ($cred.length && !$cred.val().trim()) {
        errors.push('El campo "Credencial ID" es obligatorio.');
        this.highlightField($cred);
      }

      if (errors.length) {
        e.preventDefault();
        this.showNotice('Por favor revise los siguientes campos:<br>• ' + errors.join('<br>• '), 'error');
        const $first = $('.andreani-field-error').first();
        if ($first.length) {
          $('html, body').animate({ scrollTop: $first.offset().top - 100 }, 500);
        }
        return false;
      }

      $('.andreani-settings-wrapper').addClass('andreani-settings-wrapper--saving');
      return true;
    },

    highlightField($field) {
      $field.addClass('andreani-field-error');
      $field.one('focus', function() {
        $(this).removeClass('andreani-field-error');
      });
    },

    showNotice(message, type = 'info') {
      $('.andreani-admin-notice').remove();
      const $notice = $(`<div class="notice notice-${type} is-dismissible andreani-admin-notice"><p>${message}</p><button type="button" class="notice-dismiss"></button></div>`);
      let $target = $('.andr-tabs').first();
      if (!$target.length) {
        $target = $('table.form-table').filter((_, el) => !$(el).closest('.andreani-settings-hidden-fields').length).first();
      }
      ($target.length ? $target : $('form').first()).before($notice);
      $notice.find('.notice-dismiss').on('click', () => $notice.remove());
      if (type !== 'error') setTimeout(() => $notice.fadeOut(400, function() { $(this).remove(); }), 5000);
    }
  };

  /* ========================================
   * ASYNC TABLE LOADER (AndreaniTableLoader)
   * ======================================== */
  const AndreaniTableLoader = {
    loaded: false,
    config: window.andreani_admin || {},
    $container: null,
    $refreshBtn: null,
    isLoading: false,
    currentParams: {},

    /**
     * Se ejecuta después de cada render AJAX (los banners vienen en la misma
     * respuesta). Maneja AMBOS notices del header de la grilla con el mismo
     * patrón: auto-dismiss vía `data-auto-dismiss="<ms>"` + bind del ✕.
     */
    bindFallbackNotice() {
      const $notices = this.$container.find('.andreani-fallback-notice, .andreani-api-notice');
      if (!$notices.length) return;

      $notices.each(function() {
        const $notice = $(this);
        const dismiss = () => {
          $notice.addClass('is-dismissing');
          setTimeout(() => $notice.remove(), 320);
        };

        $notice.find('.andreani-fallback-notice__close, .andreani-api-notice__close').on('click', dismiss);

        const timeout = parseInt($notice.attr('data-auto-dismiss'), 10);
        if (timeout > 0) setTimeout(dismiss, timeout);
      });
    },

    init() {
      this.$container = $('#andreani-table-container');
      this.$refreshBtn = $('#andreani-refresh-table');

      if (!this.$container.length || !$('.andreani-shipments-wrap').data('async-load')) {
        return;
      }

      // Leer parámetros iniciales de la URL
      this.currentParams = this.getUrlParams();
      this.loadTable();
      this.bindEvents();
    },

    getUrlParams() {
      const params = new URLSearchParams(window.location.search);
      return {
        paged: params.get('paged') || 1,
        per_page: params.get('per_page') || '',
        orderby: params.get('orderby') || '',
        order: params.get('order') || '',
        andreani_status: params.get('andreani_status') || '',
        s: params.get('s') || '',
        andreani_date_from: params.get('andreani_date_from') || '',
        andreani_date_to: params.get('andreani_date_to') || ''
      };
    },

    bindEvents() {
      const self = this;

      this.$refreshBtn.on('click', (e) => {
        e.preventDefault();
        if (!self.isLoading) {
          self.loadTable();
        }
      });

      // Intercept form submit for filters/search/pagination
      $(document).on('submit', '#andreani-shipments-form', (e) => {
        if (self.isLoading) {
          e.preventDefault();
          return;
        }

        e.preventDefault();
        self.loadTable();
      });

      // Pagination buttons (delegated para botones cargados dinámicamente)
      $(document).on('click', '#andreani-table-container .andreani-page-btn', (e) => {
        e.preventDefault();
        if (self.isLoading || $(e.currentTarget).attr('aria-disabled') === 'true') return;

        self.loadTable({ paged: $(e.currentTarget).attr('data-paged') || 1 });
      });

      $(document).on('click', '.andreani-per-page__btn', function(e) {
        e.preventDefault();
        if (self.isLoading) return;
        const $btn = $(this);
        const value = parseInt($btn.data('per-page'), 10);
        $('.andreani-per-page__btn').removeClass('is-active').attr('aria-pressed', 'false');
        $btn.addClass('is-active').attr('aria-pressed', 'true');
        self.currentParams.per_page = value;
        self.currentParams.paged = 1;
        self.loadTable();
      });

      // Sortable columns (delegated)
      $(document).on('click', '#andreani-table-container th.sortable a, #andreani-table-container th.sorted a', (e) => {
        e.preventDefault();
        if (self.isLoading) return;

        const href = $(e.currentTarget).attr('href');
        const params = new URLSearchParams(href.split('?')[1] || '');

        self.loadTable({
          orderby: params.get('orderby') || '',
          order: params.get('order') || ''
        });
      });

      $(document).on('click', '#andreani-table-container #filter_action, #andreani-shipments-form #filter_action', (e) => {
        e.preventDefault();
        if (self.isLoading) return;
        self.loadTable();
      });
    },

    getFormParams() {
      const $form = $('#andreani-shipments-form');
      const $container = this.$container;

      // Priorizar valores del form/container sobre los actuales
      const s = $form.find('input[name="s"]').val();

      const containerStatus = $container.find('select[name="andreani_status"]').val();
      const formStatus = $form.find('select[name="andreani_status"]').val();
      const status = containerStatus !== undefined ? containerStatus :
                     (formStatus !== undefined ? formStatus : this.currentParams.andreani_status);

      const $activePerPage = $('.andreani-per-page__btn.is-active');
      const perPage = $activePerPage.length ? $activePerPage.data('per-page') : (this.currentParams.per_page || '');

      // Hidden inputs para los quick filter chips. Si el chip no fue activado,
      // mantenemos el valor actual de currentParams (que puede venir de URL).
      const dateFrom = $form.find('input[name="andreani_date_from"]').val();
      const dateTo = $form.find('input[name="andreani_date_to"]').val();

      return {
        s: s !== undefined ? s : this.currentParams.s,
        andreani_status: status || '',
        per_page: perPage,
        andreani_date_from: dateFrom !== undefined ? dateFrom : (this.currentParams.andreani_date_from || ''),
        andreani_date_to: dateTo !== undefined ? dateTo : (this.currentParams.andreani_date_to || '')
      };
    },

    loadTable(extraParams = {}) {
      const self = this;

      if (this.isLoading) return;
      this.isLoading = true;

      // Obtener parámetros ANTES de mostrar el loader (que borra los selectores)
      const formParams = this.getFormParams();

      this.showLoader();

      // Merge order: currentParams ← formParams ← extraParams.
      // Si cambian filtros (o per_page), reseteamos paged a 1.
      const isFilterChange = extraParams.andreani_status !== undefined ||
                             extraParams.s !== undefined ||
                             extraParams.andreani_date_from !== undefined ||
                             extraParams.andreani_date_to !== undefined ||
                             extraParams.per_page !== undefined ||
                             (formParams.s !== this.currentParams.s) ||
                             (formParams.andreani_status !== this.currentParams.andreani_status) ||
                             (formParams.per_page !== this.currentParams.per_page) ||
                             (formParams.andreani_date_from !== this.currentParams.andreani_date_from) ||
                             (formParams.andreani_date_to !== this.currentParams.andreani_date_to);

      let mergedParams = $.extend({}, this.currentParams, formParams, extraParams);

      if (isFilterChange && !extraParams.paged) {
        mergedParams.paged = 1;
      }

      this.currentParams = $.extend({}, mergedParams);

      const params = $.extend({}, mergedParams, {
        action: 'andreani_load_shipments_table',
        nonce: this.config.nonce_load_table
      });

      $.post(this.config.ajax_url || ajaxurl, params)
        .done((res) => AndreaniLoader.hide(self.$container, () => {
          if (res.success && res.data.html) {
            self.loaded = true;
            self.$container.html(res.data.html);
            AndreaniShipments.bindCopyEvents();
            self.bindFallbackNotice();
            self.updateUrl();
            // Al recargar la tabla los rows expandidos desaparecen del DOM (el HTML
            // nuevo trae todos los detail rows colapsados). Reseteamos el flag para
            // mantener la consistencia del estado.
            if (window.AndreaniRowExpander) {
              window.AndreaniRowExpander.currentExpandedId = null;
            }
            $(document).trigger('andreani:table-loaded');
          } else {
            self.showError(res.data?.message || self.t('table_error'));
          }
        }))
        .fail(() => AndreaniLoader.hide(self.$container, () => self.showError(self.t('table_error'))))
        .always(() => {
          self.isLoading = false;
          self.$refreshBtn.removeClass('andreani-refresh-btn--loading');
        });
    },

    showLoader() {
      this.$refreshBtn.addClass('andreani-refresh-btn--loading');

      AndreaniLoader.show(this.$container, this.loaded
        ? { size: 'lg', text: this.t('loader_shipments_update') }
        : { size: 'lg', phrases: this.config.i18n.loader_shipments_phrases });
    },

    updateUrl() {
      const params = new URLSearchParams();
      params.set('page', new URLSearchParams(window.location.search).get('page') || 'andreani-shipping');

      const paged = parseInt(this.currentParams.paged, 10) || 1;
      if (paged > 1) params.set('paged', paged);
      if (this.currentParams.per_page) params.set('per_page', this.currentParams.per_page);
      if (this.currentParams.orderby) params.set('orderby', this.currentParams.orderby);
      if (this.currentParams.order) params.set('order', this.currentParams.order);
      if (this.currentParams.andreani_status) params.set('andreani_status', this.currentParams.andreani_status);
      if (this.currentParams.s) params.set('s', this.currentParams.s);
      if (this.currentParams.andreani_date_from) params.set('andreani_date_from', this.currentParams.andreani_date_from);
      if (this.currentParams.andreani_date_to) params.set('andreani_date_to', this.currentParams.andreani_date_to);

      const newUrl = window.location.pathname + '?' + params.toString();
      window.history.replaceState({}, '', newUrl);
    },

    showError(message) {
      const retryText = this.t('table_retry') !== 'table_retry' ? this.t('table_retry') : 'Reintentar';
      const errorHtml = `
        <div class="andreani-table-error">
          <svg class="andreani-table-error__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
          <p class="andreani-table-error__message">${escapeHtml(message)}</p>
          <button type="button" class="andreani-table-error__retry">${retryText}</button>
        </div>
      `;
      this.$container.html(errorHtml);

      this.$container.find('.andreani-table-error__retry').on('click', () => this.loadTable());
    },

    escapeHtml: escapeHtml,

    t(key) {
      return this.config.i18n?.[key] || key;
    }
  };

  /* ========================================
   * SHIPMENTS & ORDERS (AndreaniShipments)
   * ======================================== */
  const AndreaniShipments = {
    config: window.andreani_admin || {},

    init() {
      this.bindActionButtons();
      this.bindCopyEvents();
      this.bindModalEvents();
      this.bindExportButton();
      this.initMobileToggle();
      this.initCollapsibleNotices();
      this.bindErrorTooltips();
    },

    /* --- Collapsible Notices --- */
    initCollapsibleNotices() {
      const STORAGE_KEY = 'andreani_notice_expanded';

      const getExpandedState = () => {
        try {
          return JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
        } catch { return {}; }
      };

      const saveExpandedState = (state) => {
        try {
          localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch {}
      };

      const expanded = getExpandedState();
      $('.andreani-notice--collapsible').each(function() {
        const key = $(this).data('collapse-key');
        if (key && expanded[key]) {
          $(this).removeClass('andreani-notice--collapsed');
        }
      });

      $(document).on('click', '.andreani-notice--collapsible .andreani-notice__header', function(e) {
        e.preventDefault();
        const $notice = $(this).closest('.andreani-notice');
        const key = $notice.data('collapse-key');
        const isExpanded = $notice.toggleClass('andreani-notice--collapsed').hasClass('andreani-notice--collapsed') === false;

        if (key) {
          const state = getExpandedState();
          state[key] = isExpanded;
          saveExpandedState(state);
        }
      });
    },

    /* --- Mobile Toggle for responsive table --- */
    initMobileToggle() {
      $(document).on('click', '.andreani-shipments-wrap .toggle-row', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $row = $btn.closest('tr');
        $row.toggleClass('is-expanded');
      });
    },

    /* --- Action Buttons (Retry, Download, Mark Shipped) --- */
    bindActionButtons() {
      const self = this;

      $(document).on('click', '.andreani-retry:not([disabled])', function(e) {
        e.preventDefault();
        const $btn = $(this);
        if ($btn.data('loading')) return;

        const orderId = $btn.data('order-id');
        const rollback = self.snapshotRow(orderId);
        self.applyOptimistic(orderId, {
          status: { class: 'pending', label: self.t('retry_loading') },
          hideActions: ['andreani-retry']
        });

        self.ajaxAction($btn, {
          action: $btn.data('action') || 'andreani_retry_order',
          nonce: $btn.data('nonce') || self.config.nonce_retry,
          loading: self.t('retry_loading'),
          success: self.t('retry_success'),
          error: self.t('retry_error'),
          onSuccess: (res) => self.reloadAfterSuccess($btn, res.data?.row_html),
          onFail: () => { if (rollback) rollback(); }
        });
      });

      $(document).on('click', '.andreani-recipient-form__submit', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $form = $(this).closest('.andreani-recipient-form');
        if (!$form.length) return;
        self.handleRecipientFormSubmit($form);
      });

      $(document).on('click', '.andreani-recipient-form__edit-toggle', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        const $card = $btn.closest('.andreani-detail__card');
        const $form = $card.find('.andreani-recipient-form');
        if (!$form.length) return;
        const isEditing = $form.hasClass('andreani-recipient-form--editing');
        if (isEditing) {
          $form.removeClass('andreani-recipient-form--editing');
          $form.find('input').prop('disabled', true).attr('disabled', 'disabled');
          self.resetRecipientHints($form);
        } else {
          $form.addClass('andreani-recipient-form--editing');
          $form.find('input').prop('disabled', false).removeAttr('disabled');
          const $firstEmpty = $form.find('input').filter(function() { return !this.value; }).first();
          ($firstEmpty.length ? $firstEmpty : $form.find('input').first()).trigger('focus');
        }
      });

      $(document).on('input', '.andreani-recipient-form input', function() {
        const $input = $(this);
        if (!$input.attr('aria-invalid')) return;
        $input.removeAttr('aria-invalid');
        const field = $input.attr('name');
        const $hint = $input.closest('.andreani-recipient-form').find('[data-hint-for="' + field + '"]');
        const original = $hint.data('original-text');
        if (original !== undefined) {
          $hint.text(original);
        }
      });

      $(document).on('click', '.andreani-download-label:not([disabled])', function(e) {
        e.preventDefault();
        const $btn = $(this);
        if ($btn.data('loading')) return;

        self.ajaxAction($btn, {
          action: $btn.data('action') || 'andreani_get_etiqueta',
          nonce: $btn.data('nonce') || self.config.nonce_etiqueta,
          loading: self.t('label_loading'),
          success: self.t('label_success'),
          error: self.t('label_error'),
          onSuccess: (res) => res.data?.pdf && self.downloadFile(res.data.pdf, res.data.filename, 'pdf')
        });
      });

    },

    /* --- Copy Events --- */
    bindCopyEvents() {
      $(document).on('click', '.andreani-copy-number, .andreani-copy-click', function() {
        const $el = $(this);
        // Usar attr() en lugar de data() porque jQuery auto-parsea strings JSON
        // a object (y luego se serializan como "[object Object]" al copiar).
        const text = $el.attr('data-tracking') || $el.attr('data-copy-text') || $el.text().trim();
        if (!text) return;

        const copy = (txt) => {
          if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(txt);
          }
          const ta = document.createElement('textarea');
          ta.value = txt;
          ta.style.cssText = 'position:fixed;left:-9999px';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          ta.remove();
          return Promise.resolve();
        };

        copy(text).then(() => {
          $el.addClass('copied');
          setTimeout(() => $el.removeClass('copied'), 1500);
        });
      });

      // Keyboard support para .andreani-copy-click con role="button" (e.g. code cards
      // de shortcodes en settings → Checkout → modo Manual). Enter o Space disparan
      // el click — así son accesibles via teclado.
      $(document).on('keydown', '.andreani-copy-click[role="button"]', function(e) {
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
          e.preventDefault();
          $(this).trigger('click');
        }
      });
    },

    /* --- Error Tooltips & Copy --- */
    bindErrorTooltips() {
      const copyText = (txt, $btn) => {
        const doCopy = () => {
          if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(txt);
          }
          const ta = document.createElement('textarea');
          ta.value = txt;
          ta.style.cssText = 'position:fixed;left:-9999px';
          document.body.appendChild(ta);
          ta.select();
          document.execCommand('copy');
          ta.remove();
          return Promise.resolve();
        };
        doCopy().then(() => {
          $btn.addClass('copied');
          const orig = $btn.text();
          $btn.text('Copiado');
          setTimeout(() => {
            $btn.removeClass('copied');
            $btn.text(orig);
          }, 1500);
        });
      };

      // Metabox error toggle
      $(document).on('click', '.andreani-metabox__error-toggle', function(e) {
        e.preventDefault();
        $(this).closest('.andreani-metabox__error').toggleClass('andreani-metabox__error--collapsed andreani-metabox__error--expanded');
      });

      $(document).on('click', '.andreani-metabox__error-copy', function(e) {
        e.preventDefault();
        const $btn = $(this);
        copyText($btn.attr('data-copy-text'), $btn);
      });

      // attr() (no data()) — jQuery auto-parsea strings JSON y luego al copiar se
      // serializan como "[object Object]".
      $(document).on('click', '.andreani-error-trigger', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn    = $(this);
        const message = $btn.attr('data-error-message') || '';
        const body    = $btn.attr('data-error-body') || '';
        const payload = $btn.attr('data-error-payload') || '';
        const $modal  = $('#andreani-error-modal');
        if (!$modal.length) return;

        $('#andreani-error-modal-message').text(message);
        $('#andreani-error-modal-body').text(body || '—');
        $('#andreani-error-modal-copy').attr('data-copy-text', payload);

        $modal.find('.andr-tabs__item')
          .removeClass('andr-tabs__item--active')
          .attr('aria-selected', 'false');
        $modal.find('.andr-tabs__item[data-tab="mensaje"]')
          .addClass('andr-tabs__item--active')
          .attr('aria-selected', 'true');
        $modal.find('.andr-tabs__panel').removeClass('andr-tabs__panel--active');
        $modal.find('.andr-tabs__panel[data-panel="mensaje"]').addClass('andr-tabs__panel--active');

        // Si no hay body, ocultar la solapa "Request".
        $modal.find('.andr-tabs__item[data-tab="request"]').toggle(!!body);

        $modal.show();
        if (typeof AndreaniShipments !== 'undefined' && typeof AndreaniShipments.centerModal === 'function') {
          AndreaniShipments.centerModal($modal);
        }
      });
    },

    /* --- Modal Events --- */
    bindModalEvents() {
      this.initDraggableModals();

      const $errorModal = $('#andreani-error-modal');
      if ($errorModal.length) {
        $errorModal.on('click', '.andr-modal__backdrop, .andr-modal__close, .andreani-modal__backdrop, .andreani-modal__close', () => $errorModal.hide());
      }

      $(document).on('keydown', (e) => {
        if (e.key === 'Escape') {
          if ($errorModal.is(':visible')) $errorModal.hide();
        }
      });
    },

    /* --- Export Button --- */
    bindExportButton() {
      const self = this;
      const $btn = $('#andreani-export-excel');
      if (!$btn.length) return;

      $btn.on('click', function(e) {
        e.preventDefault();
        if ($btn.prop('disabled')) return;

        const params = new URLSearchParams(window.location.search);
        $btn.prop('disabled', true);
        const release = AndreaniLoader.busy(self.t('export_loading'));

        $.post(self.config.ajax_url || ajaxurl, {
          action: 'andreani_export_shipments',
          nonce: self.config.nonce_export,
          andreani_status: params.get('andreani_status') || '',
          client_type: params.get('client_type') || '',
          search: params.get('s') || '',
          andreani_date_from: params.get('andreani_date_from') || '',
          andreani_date_to: params.get('andreani_date_to') || ''
        })
        .done((res) => {
          if (res.success && res.data.csv) {
            self.downloadFile(res.data.csv, res.data.filename, 'csv');
            self.showNotice($btn, `${self.t('export_success')} (${res.data.count} envíos)`, 'success');
          } else {
            self.showNotice($btn, res.data?.message || self.t('export_error'), 'error');
          }
        })
        .fail(() => self.showNotice($btn, self.t('network_error'), 'error'))
        .always(() => {
          $btn.prop('disabled', false);
          release();
        });
      });
    },

    /* --- Draggable Modals --- */
    initDraggableModals() {
      const self = this;

      $('.andr-modal__header[data-draggable="true"], .andreani-modal__header[data-draggable="true"]').each(function() {
        const $header = $(this);
        const $container = $header.closest('.andr-modal__container');

        let isDragging = false;
        let startX, startY, startLeft, startTop;

        $header.on('mousedown', function(e) {
          if ($(e.target).closest('.andr-modal__close, .andreani-modal__close').length) return;

          isDragging = true;
          $container.addClass('andr-modal__container--dragging');

          const rect = $container[0].getBoundingClientRect();
          startX = e.clientX;
          startY = e.clientY;
          startLeft = rect.left;
          startTop = rect.top;

          e.preventDefault();
        });

        $(document).off('mousemove.andreaniDrag mouseup.andreaniDrag');

        $(document).on('mousemove.andreaniDrag', function(e) {
          if (!isDragging) return;

          const deltaX = e.clientX - startX;
          const deltaY = e.clientY - startY;

          let newLeft = startLeft + deltaX;
          let newTop = startTop + deltaY;

          // Clamp al viewport para que no se pueda arrastrar fuera de la ventana.
          const maxLeft = window.innerWidth - $container.outerWidth();
          const maxTop = window.innerHeight - $container.outerHeight();

          newLeft = Math.max(0, Math.min(newLeft, maxLeft));
          newTop = Math.max(0, Math.min(newTop, maxTop));

          $container.css({
            left: newLeft + 'px',
            top: newTop + 'px',
            transform: 'none',
            position: 'fixed'
          });
        });

        $(document).on('mouseup.andreaniDrag', function() {
          if (isDragging) {
            isDragging = false;
            $container.removeClass('andr-modal__container--dragging');
          }
        });
      });
    },

    centerModal($modal) {
      const $container = $modal.find('.andr-modal__container');
      const windowWidth = window.innerWidth;
      const windowHeight = window.innerHeight;
      const modalWidth = $container.outerWidth();
      const modalHeight = $container.outerHeight();

      const left = Math.max(0, (windowWidth - modalWidth) / 2);
      const top = Math.max(40, (windowHeight - modalHeight) / 2 - 50);

      $container.css({
        position: 'fixed',
        left: left + 'px',
        top: top + 'px',
        transform: 'none'
      });
    },

    handleRecipientFormSubmit($form) {
      const self = this;
      if ($form.data('loading')) {
        return;
      }

      const orderId = $form.data('order-id');
      const url = $form.data('ajax-url') || this.config.ajax_url || ajaxurl;
      const nonce = $form.data('nonce');
      if (!orderId || !nonce) {
        return;
      }

      $form.find('.andreani-recipient-form__hint').each(function() {
        const $hint = $(this);
        if ($hint.data('original-text') === undefined) {
          $hint.data('original-text', $hint.text());
        }
      });

      const fields = {
        phone: $form.find('[name="phone"]').val()?.trim() || '',
        dni: $form.find('[name="dni"]').val()?.trim() || ''
      };

      this.resetRecipientHints($form);

      const errors = this.validateRecipientFields(fields);
      if (Object.keys(errors).length > 0) {
        this.showRecipientErrors($form, errors);
        return;
      }

      const $submit = $form.find('.andreani-recipient-form__submit');
      const $feedback = $form.find('.andreani-recipient-form__feedback');
      $form.data('loading', true);
      $submit.prop('disabled', true);
      const release = AndreaniLoader.busy(this.t('retry_loading'));
      $feedback.removeClass('andreani-recipient-form__feedback--success andreani-recipient-form__feedback--error').text('');

      const rollback = self.snapshotRow(orderId);
      self.applyOptimistic(orderId, {
        status: { class: 'pending', label: self.t('retry_loading') }
      });

      $.post(url, {
        action: 'andreani_update_recipient_and_retry',
        nonce: nonce,
        order_id: orderId,
        phone: fields.phone,
        dni: fields.dni
      })
        .done((res) => {
          if (res.success) {
            $feedback.addClass('andreani-recipient-form__feedback--success').text(res.data?.message || self.t('retry_success'));
            if (res.data?.row_html) {
              self.updateRow(orderId, res.data.row_html);
            }
          } else {
            if (rollback) rollback();
            if (res.data?.errors) {
              self.showRecipientErrors($form, res.data.errors);
            }
            $feedback.addClass('andreani-recipient-form__feedback--error').text(res.data?.message || self.t('retry_error'));
          }
        })
        .fail(() => {
          if (rollback) rollback();
          $feedback.addClass('andreani-recipient-form__feedback--error').text(self.t('network_error'));
        })
        .always(() => {
          $form.data('loading', false);
          $submit.prop('disabled', false);
          release();
        });
    },

    validateRecipientFields(fields) {
      const errors = {};
      if (!fields.phone) {
        errors.phone = this.t('recipient_phone_required') || 'El teléfono es obligatorio.';
      } else if (fields.phone.replace(/\D/g, '').length < 8) {
        errors.phone = this.t('recipient_phone_invalid') || 'El teléfono debe tener al menos 8 dígitos.';
      }
      if (!fields.dni) {
        errors.dni = this.t('recipient_dni_required') || 'El DNI/CUIT es obligatorio.';
      } else {
        const dniDigits = fields.dni.replace(/\D/g, '').length;
        if (dniDigits < 7 || dniDigits > 11) {
          errors.dni = this.t('recipient_dni_invalid') || 'El DNI/CUIT debe tener entre 7 y 11 dígitos.';
        }
      }
      return errors;
    },

    showRecipientErrors($form, errors) {
      Object.keys(errors).forEach((field) => {
        const $hint = $form.find('.andreani-recipient-form__hint[data-hint-for="' + field + '"]');
        const $input = $form.find('[name="' + field + '"]');
        $hint.text(errors[field]);
        $input.attr('aria-invalid', 'true');
      });
      const firstField = Object.keys(errors)[0];
      $form.find('[name="' + firstField + '"]').trigger('focus');
    },

    resetRecipientHints($form) {
      $form.find('input').removeAttr('aria-invalid');
      $form.find('.andreani-recipient-form__hint').each(function() {
        const $hint = $(this);
        const original = $hint.data('original-text');
        if (original !== undefined) {
          $hint.text(original);
        }
      });
    },

    ajaxAction($btn, opts) {
      const orderId = $btn.data('order-id');
      const url = $btn.data('ajax-url') || this.config.ajax_url || ajaxurl;
      if (!orderId) return;

      $btn.data('loading', true).prop('disabled', true);
      const release = AndreaniLoader.busy(opts.loading);

      $.post(url, { action: opts.action, nonce: opts.nonce, order_id: orderId })
        .done((res) => {
          if (res.success) {
            this.showNotice($btn, res.data?.message || opts.success, 'success');
            opts.onSuccess?.(res);
          } else {
            opts.onFail?.(res);
            this.showNotice($btn, res.data?.message || opts.error, res.data?.type || 'error');
          }
        })
        .fail(() => {
          opts.onFail?.();
          this.showNotice($btn, this.t('network_error'), 'error');
        })
        .always(() => {
          $btn.data('loading', false).prop('disabled', false);
          release();
        });
    },

    reloadAfterSuccess($btn, rowHtml) {
      // Reemplazo inmediato del row con HTML server-rendered: es idempotente con la
      // actualización optimista ya aplicada al click, así que no hace falta delay.
      if (rowHtml) {
        this.updateRow($btn.data('order-id'), rowHtml);
      } else if (typeof AndreaniTableLoader !== 'undefined' && AndreaniTableLoader.loadTable) {
        AndreaniTableLoader.loadTable();
      } else {
        location.reload();
      }
    },

    /**
     * Toma snapshot del row + detail-row actuales para poder hacer rollback.
     * Retorna funcion que restaura el HTML al estado anterior. Si no hay row,
     * retorna null.
     */
    snapshotRow(orderId) {
      const $row = $(`tr[data-order-id="${orderId}"]`);
      const $detail = $(`tr.andreani-detail-row[data-detail-for="${orderId}"]`);
      if (!$row.length) return null;

      const rowHtml = $row[0].outerHTML;
      const detailHtml = $detail.length ? $detail[0].outerHTML : '';
      const wasExpanded = $row.hasClass('andreani-row--expanded');
      const self = this;

      return () => {
        const $current = $(`tr[data-order-id="${orderId}"]`);
        const $currentDetail = $(`tr.andreani-detail-row[data-detail-for="${orderId}"]`);
        if (!$current.length) return;

        $currentDetail.remove();
        $current.replaceWith(rowHtml);

        if (detailHtml) {
          $(`tr[data-order-id="${orderId}"]`).after(detailHtml);
        }

        if (wasExpanded) {
          $(`tr[data-order-id="${orderId}"]`).addClass('andreani-row--expanded');
          $(`tr.andreani-detail-row[data-detail-for="${orderId}"]`).addClass('andreani-detail-row--visible');
          if (typeof window.AndreaniRowExpander !== 'undefined') {
            window.AndreaniRowExpander.currentExpandedId = orderId;
          }
        }

        self.bindCopyEvents();
      };
    },

    /**
     * Aplica cambios visuales optimistas a un row (sin esperar al server).
     * @param {string|number} orderId
     * @param {object} changes
     *   - status:        { class: 'shipped|ready|awaiting|error|pending', label: 'Enviado' }
     *   - tracking:      string (display de la columna seguimiento). String vacio = "-".
     *   - hideActions:   array de classnames de botones a ocultar (.andreani-retry, etc)
     *   - showActions:   array de classnames a mostrar
     */
    applyOptimistic(orderId, changes) {
      const $row = $(`tr[data-order-id="${orderId}"]`);
      if (!$row.length || !changes) return;

      if (changes.status) {
        const $status = $row.find('.andr-status').first();
        if ($status.length) {
          $status
            .attr('class', `andr-status andr-status--${changes.status.class}`)
            .text(changes.status.label);
        }
      }

      if (typeof changes.tracking !== 'undefined') {
        const $cell = $row.find('td.column-tracking');
        if ($cell.length) {
          if (changes.tracking) {
            const safe = escapeAttr(changes.tracking);
            $cell.html(`<code class="andreani-tracking-code andreani-copy-click" data-tracking="${safe}" title="Click para copiar">${safe}</code>`);
          } else {
            $cell.html('<span class="andreani-tracking--empty">-</span>');
          }
        }
      }

      if (Array.isArray(changes.hideActions)) {
        changes.hideActions.forEach((cls) => {
          const sel = cls.charAt(0) === '.' ? cls : `.${cls}`;
          $row.find(sel).hide();
        });
      }
      if (Array.isArray(changes.showActions)) {
        changes.showActions.forEach((cls) => {
          const sel = cls.charAt(0) === '.' ? cls : `.${cls}`;
          $row.find(sel).show();
        });
      }
    },

    updateRow(orderId, rowHtml) {
      const $row = $(`tr[data-order-id="${orderId}"]`);
      if (!$row.length || !rowHtml) return;

      // El single_row del PHP genera <tr>+<tr.andreani-detail-row>. Si reemplazamos solo
      // el <tr>, el detail-row hermano queda stale. Lo removemos antes y re-aplicamos
      // el estado de expansion si correspondia.
      const $oldDetail = $(`tr.andreani-detail-row[data-detail-for="${orderId}"]`);
      const wasExpanded = $row.hasClass('andreani-row--expanded');

      $oldDetail.remove();
      $row.replaceWith(rowHtml);

      if (wasExpanded) {
        const $newRow = $(`tr[data-order-id="${orderId}"]`);
        const $newDetail = $(`tr.andreani-detail-row[data-detail-for="${orderId}"]`);
        $newRow.addClass('andreani-row--expanded');
        $newDetail.addClass('andreani-detail-row--visible');
        if (typeof window.AndreaniRowExpander !== 'undefined') {
          window.AndreaniRowExpander.currentExpandedId = orderId;
        }
      }

      this.bindCopyEvents();
    },

    downloadFile(data, filename, type) {
      try {
        let blob;
        if (type === 'pdf') {
          const binary = atob(data.replace(/^data:application\/pdf;base64,/, ''));
          const bytes = new Uint8Array(binary.length);
          for (let i = 0; i < binary.length; i++) bytes[i] = binary.charCodeAt(i);
          blob = new Blob([bytes], { type: 'application/pdf' });
          filename = (filename || 'etiqueta.pdf').replace(/[^a-zA-Z0-9_.-]/g, '_');
        } else {
          blob = new Blob([data], { type: 'text/csv;charset=utf-8;' });
          filename = filename || 'andreani-envios.csv';
        }

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(url); a.remove(); }, 500);
      } catch (e) {
        console.error('Andreani: Download error', e);
      }
    },

    showNotice($el, message, type) {
      // Modales: el notice queda DENTRO del modal (scopea el feedback al flow).
      // OJO: solo si el $el sigue en el DOM. Si el botón disparador fue reemplazado
      // por un rollback optimista, $el ya no está dentro de ningún modal.
      const $modalBody = ($el && $el.length && jQuery.contains(document, $el[0]))
        ? $el.closest('.andr-modal__body, .andreani-modal__body')
        : $();
      if ($modalBody.length) {
        $modalBody.find('.andreani-temp-notice').remove();
        const $modalNotice = $(`<div class="notice notice-${type || 'info'} andreani-temp-notice is-dismissible"><p>${escapeHtml(message)}</p></div>`);
        $modalNotice.append($('<button type="button" class="notice-dismiss">').on('click', () => $modalNotice.fadeOut(function() { $(this).remove(); })));
        $modalBody.prepend($modalNotice);
        if (type !== 'error') {
          setTimeout(() => $modalNotice.fadeOut(function() { $(this).remove(); }), 5000);
        }
        return;
      }

      // Slot admin estándar: container dedicado como hijo directo del wrap de
      // envíos, justo después de <hr class="wp-header-end">. Lo buscamos por
      // selector documental (no dependemos del $el). El motivo: el botón retry
      // hace optimistic + rollback, y al fallar el rollback reemplaza el <tr>
      // ANTES de que llegue acá. El $el original ya no está en el DOM, entonces
      // $el.closest('.wrap') devuelve vacío y el notice cae a fallbacks raros
      // (que pueden terminar pintándolo dentro del card de error del detalle).
      const $wrap = $('.andreani-shipments-wrap').first();
      if (!$wrap.length) {
        if (typeof AndreaniAdmin !== 'undefined') AndreaniAdmin.showNotice(message, type);
        return;
      }

      let $noticeContainer = $wrap.children('.andreani-notices-container').first();
      if (!$noticeContainer.length) {
        $noticeContainer = $('<div class="andreani-notices-container"></div>');
        const $headerEnd = $wrap.children('.wp-header-end').first();
        if ($headerEnd.length) {
          $headerEnd.after($noticeContainer);
        } else {
          $wrap.prepend($noticeContainer);
        }
      }

      $noticeContainer.find('.andreani-temp-notice').remove();
      const $notice = $(`<div class="notice notice-${type || 'info'} andreani-temp-notice is-dismissible"><p>${escapeHtml(message)}</p></div>`);
      $notice.append($('<button type="button" class="notice-dismiss">').on('click', () => $notice.fadeOut(function() { $(this).remove(); })));
      $noticeContainer.append($notice);

      // Los errores quedan visibles hasta que el user los cierre (igual que
      // AndreaniAdmin.showNotice). Info/success/warning se auto-cierran a los 5s.
      if (type !== 'error') {
        setTimeout(() => $notice.fadeOut(function() { $(this).remove(); }), 5000);
      }
    },

    escapeHtml: escapeHtml,

    t(key) {
      return this.config.i18n?.[key] || key;
    }
  };

  /* ========================================
   * INFO BOX — card expandible/colapsable
   * ======================================== */
  const AndreaniInfoBox = {

    init() {
      this.initConditionalVisibility();
      this.bindToggle();
    },

    /**
     * Evalúa la visibilidad condicional de cada info box.
     * Soporta dos mecanismos:
     *   1. data-show-when-field / data-show-when-value — observa un <select> de WC settings
     *      (el id del field en WC es woocommerce_{method_id}_{field_key}).
     *   2. data-show-when-checkbox — observa un checkbox por clase CSS (para campos custom
     *      como .andreani-cotizador-enabled que no son fields WC estándar).
     */
    initConditionalVisibility() {
      const self = this;

      $('.andreani-info-box-wrapper').each(function() {
        const $row = $(this);
        const watchField    = $row.data('show-when-field');
        const watchValue    = $row.data('show-when-value');
        const watchCheckbox = $row.data('show-when-checkbox');

        if (watchField) {
          // Buscar el field WC por sufijo de nombre o id (WC genera ids como woocommerce_{id}_{key})
          const $watched = $('[name$="_' + watchField + '"], [id$="_' + watchField + '"]').first();
          if (!$watched.length) return;

          const updateVisibility = () => {
            const currentValue = $watched.val();
            const shouldShow = (currentValue === String(watchValue));
            self.setRowVisibility($row, shouldShow);
          };

          $watched.on('change', updateVisibility);
          // Forzar evaluación inicial (el PHP puede haber puesto data-hidden="true" como seguridad)
          updateVisibility();
        } else if (watchCheckbox) {
          // Mecanismo alternativo: checkbox por clase CSS (ej: .andreani-cotizador-enabled)
          const $watched = $('.' + watchCheckbox).first();
          if (!$watched.length) return;

          const updateVisibilityCheckbox = () => {
            const shouldShow = $watched.is(':checked');
            self.setRowVisibility($row, shouldShow);
          };

          $watched.on('change', updateVisibilityCheckbox);
          // Evaluación inicial (el PHP ya calculó el estado, pero sincronizamos por si acaso)
          updateVisibilityCheckbox();
        }
      });
    },

    /**
     * Muestra u oculta la fila del info box y, cuando se muestra,
     * abre el box si data-initial-open="true".
     *
     * @param {jQuery} $row      La fila .andreani-info-box-wrapper
     * @param {boolean} shouldShow Verdadero si debe ser visible
     */
    setRowVisibility($row, shouldShow) {
      $row.attr('data-hidden', !shouldShow);

      if (shouldShow) {
        const $box = $row.find('.andreani-info-box');
        // Si el PHP indicó que debe abrirse al hacerse visible, aplicarlo
        if ($box.data('initial-open') === true || $box.attr('data-initial-open') === 'true') {
          $box.attr('data-open', 'true');
          $row.find('.andreani-info-box__header').attr('aria-expanded', 'true');
        }
      }
    },

    /**
     * Toggle expand/collapse al hacer click en el header del info box.
     * Usa delegación para soportar boxes generados dinámicamente.
     */
    bindToggle() {
      $(document).on('click', '.andreani-info-box__header', function() {
        const $box   = $(this).closest('.andreani-info-box');
        const isOpen = $box.attr('data-open') === 'true';
        $box.attr('data-open', !isOpen);
        $(this).attr('aria-expanded', !isOpen);
      });
    }

  };

  /**
   * AndreaniTabs — solapas horizontales (.andr-tabs).
   * Persistencia: localStorage por data-tabs (id único).
   */
  const AndreaniTabs = {
    init() {
      $(document).on('click', '.andr-tabs__item', function() {
        const $btn = $(this);
        const $tabs = $btn.closest('.andr-tabs');
        const target = $btn.attr('data-tab');
        if (!target) return;

        $tabs.find('.andr-tabs__item')
          .removeClass('andr-tabs__item--active')
          .attr('aria-selected', 'false');
        $btn.addClass('andr-tabs__item--active').attr('aria-selected', 'true');

        $tabs.find('.andr-tabs__panel').removeClass('andr-tabs__panel--active');
        $tabs.find('[data-panel="' + target + '"]').addClass('andr-tabs__panel--active');

        const tabsId = $tabs.attr('data-tabs');
        if (tabsId && window.localStorage) {
          try { localStorage.setItem('andr-tabs-' + tabsId, target); } catch (e) {}
        }
      });

      // Soporta navegación cross-tab desde botones de empty-states/CTAs:
      // <button data-goto-tab="cuenta"> dispara el click del tab correspondiente.
      $(document).on('click', '[data-goto-tab]', function(e) {
        e.preventDefault();
        const target = $(this).attr('data-goto-tab');
        if (!target) return;
        const $tabs = $(this).closest('.andr-tabs').length
          ? $(this).closest('.andr-tabs')
          : $('.andr-tabs').first();
        $tabs.find('.andr-tabs__item[data-tab="' + target + '"]').trigger('click');
      });

      $('.andr-tabs[data-tabs]').each(function() {
        const $tabs = $(this);
        const tabsId = $tabs.attr('data-tabs');
        if (!tabsId || !window.localStorage) return;
        let saved;
        try { saved = localStorage.getItem('andr-tabs-' + tabsId); } catch (e) { return; }
        if (saved && $tabs.find('[data-tab="' + saved + '"]').length) {
          $tabs.find('[data-tab="' + saved + '"]').trigger('click');
        }
      });
    }
  };

  /**
   * AndreaniGrid — stats bar + quick filters de la tabla de envíos.
   *  - .andreani-stat-card[data-status-filter] (mutuamente exclusivos)
   *  - .andreani-chip[data-quick-filter]      (combinables, salvo reset)
   * Sincroniza con AndreaniTableLoader vía hidden inputs y `<select name="andreani_status">`.
   */
  const AndreaniGrid = {
    init() {
      const $wrap = $('.andreani-shipments-wrap');
      if (!$wrap.length) return;

      this.bindStatCards();
      this.bindQuickFilters();
      this.bindCustomDateInputs();
      this.syncFromUrl();
    },

    /**
     * Restaurar el estado visual de chips desde los query params iniciales
     * (deep-link friendly: si recargo la pagina con ?andreani_status=error vuelvo
     * a ver el chip "Solo errores" activo).
     */
    syncFromUrl() {
      const params = new URLSearchParams(window.location.search);

      // Helper para activar un chip respetando los atributos ARIA correctos
      // (pressed para botones toggle, checked para chips dentro de un radiogroup).
      const activateChip = ($chip) => {
        $chip.addClass('andreani-chip--active')
             .attr('aria-pressed', 'true')
             .attr('aria-checked', 'true');
      };

      // Status: CSV (errors,pending,ready) → cada token activa su chip. Aceptamos
      // 'error' (singular legacy del select dropdown) como alias de 'errors'.
      const statusRaw = params.get('andreani_status') || '';
      const statusChipMap = { error: 'errors', errors: 'errors', pending: 'pending', ready: 'ready' };
      statusRaw.split(',').map(s => s.trim()).filter(Boolean).forEach(s => {
        const chipKey = statusChipMap[s];
        if (chipKey) {
          activateChip($('.andreani-chip[data-quick-filter="' + chipKey + '"]'));
        }
      });

      // Fecha: comparamos contra los rangos que generan "Hoy" / "Esta semana" / "Últimos 15 días".
      // Solo se activa el chip si el valor coincide exactamente; de lo contrario
      // se asume que es un filtro de fecha custom y ningún chip queda activo.
      const dateFrom = params.get('andreani_date_from') || '';
      const dateTo   = params.get('andreani_date_to') || '';
      if (dateFrom) {
        if (dateFrom === this.startOfToday()) {
          activateChip($('.andreani-chip[data-quick-filter="today"]'));
        } else if (dateFrom === this.startOfWeek()) {
          activateChip($('.andreani-chip[data-quick-filter="week"]'));
        } else if (dateFrom === this.startOfDaysAgo(15)) {
          activateChip($('.andreani-chip[data-quick-filter="last-15"]'));
        }
      }

      // Repopular los inputs visibles desde la URL (deep-link friendly).
      $('input[name="andreani_date_from"]').val(dateFrom);
      $('input[name="andreani_date_to"]').val(dateTo);

      // Mostrar el botón "Limpiar filtros" si quedó algún chip activo.
      this.updateResetVisibility();
    },

    bindStatCards() {
      const self = this;

      $(document).on('click', '.andreani-stat-card[data-status-filter]', function(e) {
        e.preventDefault();
        const $card = $(this);
        const filter = $card.data('status-filter') || '';
        const wasActive = $card.hasClass('andreani-stat-card--active');

        // Mutuamente excluyentes: limpiar todas las cards
        $('.andreani-stat-card').removeClass('andreani-stat-card--active').attr('aria-pressed', 'false');

        // Mapeo: "not_created" en la card === '' (vacio) en el server porque el filtro
        // server-side de status no tiene una opcion explicita "pendiente". Pendientes = sin
        // _order_andreani_created y sin _andreani_last_error. Lo aproximamos enviando
        // andreani_status='' y dejando que el conteo de la stats bar haga la diferencia.
        // Para "Pendientes" usamos el chip dedicado (data-quick-filter="pending") que SI
        // setea andreani_status='not_created' (no existe en server, asi que cae a "todos");
        // la solucion definitiva queda anotada para una proxima fase.
        let serverStatus = '';
        if (!wasActive) {
          $card.addClass('andreani-stat-card--active').attr('aria-pressed', 'true');
          // Las stat cards que SI tienen contraparte server-side: success / shipped / error
          if (filter === 'success' || filter === 'shipped' || filter === 'error') {
            serverStatus = filter;
          }
          // 'not_created' lo dejamos vacio: el server no soporta ese filtro hoy,
          // pero el highlight de la card persiste para feedback visual.
        }

        // Reflejar en el <select name="andreani_status"> y en el form
        $('select[name="andreani_status"]').val(serverStatus);

        // Forzar reload del table loader con el nuevo status
        if (window.AndreaniTableLoader && AndreaniTableLoader.loadTable) {
          AndreaniTableLoader.loadTable({ andreani_status: serverStatus });
        }
      });
    },

    bindQuickFilters() {
      const self = this;

      $(document).on('click', '.andreani-chip[data-quick-filter]', function(e) {
        e.preventDefault();
        const $chip = $(this);
        const filter = $chip.data('quick-filter') || '';
        const wasActive = $chip.hasClass('andreani-chip--active');

        // Reset = limpia todo
        if (filter === 'reset') {
          self.resetAll();
          return;
        }

        // Mutual exclusivity solo para el grupo "date" (rango de fecha es excluyente).
        // El grupo "status" es multi-select: el merchant puede combinar Listos +
        // Pendientes + Errores. Click sobre uno activo siempre toggle off.
        const group = $chip.data('filter-group') || '';

        if (wasActive) {
          $chip.removeClass('andreani-chip--active')
               .attr('aria-pressed', 'false')
               .attr('aria-checked', 'false');
        } else {
          if (group === 'date') {
            $('.andreani-chip[data-filter-group="date"]')
              .removeClass('andreani-chip--active')
              .attr('aria-pressed', 'false')
              .attr('aria-checked', 'false');
          }
          $chip.addClass('andreani-chip--active')
               .attr('aria-pressed', 'true')
               .attr('aria-checked', 'true');
        }

        self.updateResetVisibility();

        const extraParams = self.computeExtraParams();
        if (window.AndreaniTableLoader && AndreaniTableLoader.loadTable) {
          AndreaniTableLoader.loadTable(extraParams);
        }
      });
    },

    /**
     * Toggle del modificador --has-active en el contenedor de chips. El chip
     * "Limpiar filtros" solo se ve cuando hay al menos un chip activo (que no sea reset).
     */
    updateResetVisibility() {
      const $container = $('.andreani-quick-filters');
      const hasActive = $container.find('.andreani-chip.andreani-chip--active').not('.andreani-chip--reset').length > 0;
      $container.toggleClass('andreani-quick-filters--has-active', hasActive);
    },

    /**
     * Lee el estado de TODOS los chips activos y construye los extraParams para loadTable.
     * Grupo `date`: mutual exclusivity → un único valor.
     * Grupo `status`: multi-select → CSV con todos los activos. El server-side acepta
     * `errors,pending,ready` y filtra OR contra el shipping_status runtime.
     */
    computeExtraParams() {
      const params = {
        andreani_date_from: '',
        andreani_date_to: '',
        andreani_status: ''
      };

      const statusValues = [];

      $('.andreani-chip.andreani-chip--active').each(function() {
        const f = $(this).data('quick-filter');
        if (f === 'today') {
          params.andreani_date_from = AndreaniGrid.startOfToday();
        } else if (f === 'week') {
          params.andreani_date_from = AndreaniGrid.startOfWeek();
        } else if (f === 'last-15') {
          params.andreani_date_from = AndreaniGrid.startOfDaysAgo(15);
        } else if (f === 'errors' || f === 'pending' || f === 'ready') {
          statusValues.push(f);
        }
      });

      params.andreani_status = statusValues.join(',');

      // Sincronizar el form (hidden inputs) — AndreaniTableLoader.getFormParams los lee de ahi.
      $('input[name="andreani_date_from"]').val(params.andreani_date_from);
      $('input[name="andreani_date_to"]').val(params.andreani_date_to);
      $('select[name="andreani_status"]').val(params.andreani_status);

      return params;
    },

    resetAll() {
      $('.andreani-chip').removeClass('andreani-chip--active').attr('aria-pressed', 'false').attr('aria-checked', 'false');
      $('.andreani-quick-filters').removeClass('andreani-quick-filters--has-active');

      $('input[name="andreani_date_from"]').val('');
      $('input[name="andreani_date_to"]').val('');
      $('select[name="andreani_status"]').val('');
      $('input[name="s"]').val('');

      if (window.AndreaniTableLoader && AndreaniTableLoader.loadTable) {
        AndreaniTableLoader.loadTable({
          andreani_status: '',
          s: '',
          andreani_date_from: '',
          andreani_date_to: ''
        });
      }
    },

    /**
     * Formato Y-m-d que entiende wc_get_orders y los inputs <input type="date">.
     * wc_get_orders interpreta `2026-05-18` como `2026-05-18 00:00:00`.
     */
    startOfToday() {
      const now = new Date();
      const y = now.getFullYear();
      const m = String(now.getMonth() + 1).padStart(2, '0');
      const d = String(now.getDate()).padStart(2, '0');
      return `${y}-${m}-${d}`;
    },

    startOfWeek() {
      const now = new Date();
      const dayOfWeek = now.getDay(); // 0 = domingo, 1 = lunes...
      const diff = (dayOfWeek + 6) % 7; // dias desde lunes
      const monday = new Date(now);
      monday.setDate(now.getDate() - diff);
      const y = monday.getFullYear();
      const m = String(monday.getMonth() + 1).padStart(2, '0');
      const d = String(monday.getDate()).padStart(2, '0');
      return `${y}-${m}-${d}`;
    },

    startOfDaysAgo(days) {
      const past = new Date();
      past.setDate(past.getDate() - days);
      const y = past.getFullYear();
      const m = String(past.getMonth() + 1).padStart(2, '0');
      const d = String(past.getDate()).padStart(2, '0');
      return `${y}-${m}-${d}`;
    },

    /**
     * Cuando el merchant cambia las fechas a mano, deseleccionamos los chips
     * de date (Hoy / Esta semana / Últimos 15 días) y refrescamos la tabla.
     */
    bindCustomDateInputs() {
      const self = this;

      $(document).on('change', '.andreani-date-range__input', function() {
        $('.andreani-chip[data-filter-group="date"]')
          .removeClass('andreani-chip--active')
          .attr('aria-pressed', 'false')
          .attr('aria-checked', 'false');

        self.updateResetVisibility();

        const extraParams = {
          andreani_date_from: $('input[name="andreani_date_from"]').val() || '',
          andreani_date_to:   $('input[name="andreani_date_to"]').val() || ''
        };

        if (window.AndreaniTableLoader && AndreaniTableLoader.loadTable) {
          AndreaniTableLoader.loadTable(extraParams);
        }
      });
    }
  };

  const AndreaniOrderPacking = {
    cache: {},
    pending: {},

    init() {
      $(document).on('click', '.andreani-detail__tabs .andr-tabs__item[data-tab="armado"]', (e) => {
        const orderId = String($(e.currentTarget).closest('.andreani-detail').data('order-id') || '');
        if (orderId) this.load(orderId);
      });
    },

    config() {
      return window.andreani_admin || {};
    },

    text(key) {
      return (this.config().i18n || {})[key] || '';
    },

    $body(orderId) {
      return $('.andreani-detail__packing[data-packing-order="' + orderId + '"] [data-andr="packing-body"]');
    },

    load(orderId) {
      const $body = this.$body(orderId);
      if (!$body.length) return;

      if (this.cache[orderId]) {
        this.paint($body, this.cache[orderId]);
        return;
      }

      if (this.pending[orderId]) return;
      this.pending[orderId] = true;

      const config = this.config();
      AndreaniLoader.show($body, { size: 'sm', text: this.text('packing_loading') });

      $.post(config.ajax_url || ajaxurl, {
        action:   'andreani_order_packing',
        nonce:    config.nonce_order_packing,
        order_id: orderId,
      })
        .done((res) => {
          const ok = !!(res && res.success && res.data);
          if (ok) this.cache[orderId] = res.data;
          AndreaniLoader.hide(this.$body(orderId), () => (ok ? this.paint(this.$body(orderId), res.data) : this.fail(orderId)));
        })
        .fail(() => AndreaniLoader.hide(this.$body(orderId), () => this.fail(orderId)))
        .always(() => { delete this.pending[orderId]; });
    },

    fail(orderId) {
      this.$body(orderId).html('<p class="andr-packing__hint">' + escapeHtml(this.text('packing_error')) + '</p>');
    },

    paint($body, data) {
      const config = this.config();
      const preview = window.AndreaniBoxPreview;
      preview.configure($.extend({}, config.box_preview, { i18n: (config.i18n || {}).dispatch || {} }));

      const missing = data.missing || [];
      const packages = data.packages || [];

      if (missing.length) {
        $body.html(missing.map((m) => '<p class="andr-packing__missing">'
          + escapeHtml(preview.fill(this.text('packing_missing'), m.name))
          + (m.url ? ' · <a href="' + escapeAttr(m.url) + '">' + escapeHtml(this.text('packing_complete')) + '</a>' : '')
          + '</p>').join(''));
        return;
      }

      if (!packages.length) {
        $body.html('<p class="andr-packing__hint">' + escapeHtml(this.text('packing_empty')) + '</p>');
        return;
      }

      const weight = preview.weightText;
      const totalKg = packages.reduce((acc, p) => acc + p.kg * (p.count || 1), 0);
      const totalBoxes = data.total || packages.length;
      const bigTitle = '<p class="andr-packing__title">'
        + escapeHtml(totalBoxes === 1 ? this.text('packing_big_one') : preview.fill(this.text('packing_big_title'), preview.fmt(totalBoxes)))
        + '</p>';
      let boxes;
      let text;

      $body.removeClass('andr-packing--list');

      if (data.bigger && preview.heterogeneous(packages)) {
        $body.addClass('andr-packing--list').html('<div class="andr-packing__text">' + bigTitle
          + '<ul class="andr-boxlist" data-andr="boxlist"></ul></div>'
          + '<p class="andr-packing__hint">' + escapeHtml(this.text('packing_hint')) + '</p>');
        preview.boxList($body.find('[data-andr="boxlist"]').get(0), packages, { box: this.text('packing_box'), units: this.text('packing_units') });
        return;
      }

      if (data.bigger) {
        boxes = preview.separate(preview.sample(packages), 'kraft');
        let offset = 0;
        const rows = packages.map((p) => {
          const count = p.count || 1;
          const head = count > 1
            ? [preview.fmt(count) + ' × ' + (p.name || this.text('packing_box')), p.ref]
            : [this.text('packing_box') + ' ' + (offset + 1), p.name, p.ref];
          offset += count;

          return '<li>' + escapeHtml(head.concat([
            p.units > 1 ? preview.fill(this.text('packing_units'), p.units) : '',
            preview.dims(p) + ' cm',
            weight(p.kg),
          ]).filter(Boolean).join(' · ')) + '</li>';
        });
        text = bigTitle + '<ul class="andr-packing__list">' + rows.join('') + '</ul>';
      } else {
        const pack = preview.packed(packages);
        const info = pack.info;
        boxes = pack.boxes;

        const meta = '<p class="andr-packing__meta">' + escapeHtml(data.units === 1
          ? preview.fill(this.text('packing_fits_one'), weight(totalKg))
          : preview.fill(this.text('packing_fits_many'), preview.fmt(data.units), weight(totalKg))) + '</p>';

        if (info && info.ok) {
          text = '<p class="andr-packing__title">' + escapeHtml(preview.fill(this.text('packing_use_box'), info.w, info.d, info.h)) + '</p>' + meta;
        } else {
          text = info ? preview.tooBigHtml(info) : meta;
        }
      }

      $body.html('<div class="andr-packing__stage"><svg data-andr="stage" role="img" aria-label="' + escapeAttr(this.text('packing_stage')) + '"></svg></div>'
        + '<div class="andr-packing__text">' + text + '</div>'
        + '<p class="andr-packing__hint">' + escapeHtml(this.text('packing_hint')) + '</p>');

      preview.render($body.find('[data-andr="stage"]').get(0), boxes, { W: 160, H: 140, pad: 8, fit: true });
    },
  };

  /**
   * AndreaniRowExpander — toggle del detail row eager-loaded.
   *
   * El detail row viene ya en el HTML inicial (`<tr class="andreani-detail-row">`)
   * adyacente a cada `<tr data-order-id>`, oculto por CSS. El toggle es 100% client-side:
   * agregamos/quitamos `--visible` y `andreani-row--expanded` y la animación CSS hace
   * el resto. Cero AJAX, expand instantáneo.
   *
   * La row entera es clickeable; excluimos elementos actionables (input/button/a/code)
   * para no robar sus clicks. Tab + Enter/Space toggle desde teclado (tabindex=0 en PHP).
   */
  const AndreaniRowExpander = {
    currentExpandedId: null,

    init() {
      this.bindEvents();
      this.maybeAutoExpand();
    },

    maybeAutoExpand() {
      const params = new URLSearchParams(window.location.search);
      const openId = params.get('open');
      if (!openId) return;
      const self = this;
      setTimeout(() => {
        self.expand(openId);
        const $row = $('tr[data-order-id="' + openId + '"]');
        if ($row.length && $row[0].scrollIntoView) {
          $row[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }, 100);
    },

    bindEvents() {
      const self = this;

      // Selector de elementos actionables que NO deben disparar el toggle.
      // Si el click cayó en alguno (o en un descendiente), lo dejamos pasar.
      const ACTIONABLE = 'input, button, a, code, select, textarea, label, [role="button"], .andreani-copy-click, .andreani-error-trigger, .andreani-error-tooltip';

      // Click en cualquier parte del row del envío (delegado en tbody para
      // sobrevivir a reemplazos AJAX de la tabla).
      $(document).on('click', '.andreani-shipments-wrap tbody tr[data-order-id]', function(e) {
        if ($(e.target).closest(ACTIONABLE).length) {
          return;
        }
        const orderId = String($(this).data('order-id') || '');
        if (!orderId) return;
        self.toggle(orderId);
      });

      // Teclado: Enter/Space sobre el row con tabindex=0 dispara el toggle.
      $(document).on('keydown', '.andreani-shipments-wrap tbody tr[data-order-id]', function(e) {
        if (e.target !== this) {
          // Solo cuando el foco está en el `<tr>` mismo, no en hijos focusables.
          return;
        }
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
          e.preventDefault();
          const orderId = String($(this).data('order-id') || '');
          if (!orderId) return;
          self.toggle(orderId);
        }
      });
    },

    toggle(orderId) {
      orderId = String(orderId);
      if (this.currentExpandedId === orderId) {
        this.collapse(orderId);
        return;
      }
      if (this.currentExpandedId) {
        this.collapse(this.currentExpandedId);
      }
      this.expand(orderId);
    },

    expand(orderId) {
      orderId = String(orderId);
      const $row = $('tr[data-order-id="' + orderId + '"]');
      const $detailRow = $('tr.andreani-detail-row[data-detail-for="' + orderId + '"]');
      if (!$row.length || !$detailRow.length) return;

      $row.addClass('andreani-row--expanded').attr('aria-expanded', 'true');
      $detailRow.addClass('andreani-detail-row--visible');

      this.currentExpandedId = orderId;
    },

    collapse(orderId) {
      orderId = String(orderId);
      const $row = $('tr[data-order-id="' + orderId + '"]');
      const $detailRow = $('tr.andreani-detail-row[data-detail-for="' + orderId + '"]');

      $row.removeClass('andreani-row--expanded').attr('aria-expanded', 'false');
      $detailRow.removeClass('andreani-detail-row--visible');

      if (this.currentExpandedId === orderId) {
        this.currentExpandedId = null;
      }
    }
  };

  /**
   * AndreaniFilters — controla el popover de filtros + pills activos + favoritos.
   * Reusa los chips legacy de AndreaniGrid (mismo `bindQuickFilters`), solo
   * encapsula el comportamiento de open/close y la representación visual del
   * estado (pill afuera + contador en el trigger).
   *
   * Favoritos: bandera por sección (date/status) en localStorage.
   * Las secciones favoritas se mueven al tope del popover. No agregan UI extra
   * afuera para no romper la limpieza visual; el efecto es que el merchant
   * encuentra primero lo que más usa.
   */
  const AndreaniFilters = {
    SECTION_LABELS: {
      today: 'Hoy',
      week: 'Esta semana',
      'last-15': 'Últimos 15 días',
      ready: 'Listos',
      pending: 'Pendientes',
      errors: 'Errores'
    },

    init() {
      const $popover = $('#andreani-filters-popover');
      if (!$popover.length) return;

      this.bindTrigger();
      this.bindOutsideClick();
      this.bindClearAndClose();
      this.render();
    },

    bindTrigger() {
      const self = this;
      $('#andreani-filters-trigger').on('click', function(e) {
        e.stopPropagation();
        self.toggle();
      });
    },

    bindOutsideClick() {
      const self = this;
      $(document).on('click', function(e) {
        const $popover = $('#andreani-filters-popover');
        if (!$popover.is(':visible')) return;
        if ($(e.target).closest('#andreani-filters-popover, #andreani-filters-trigger').length) return;
        self.close();
      });
      $(document).on('keydown', function(e) {
        if (e.key === 'Escape') self.close();
      });
    },

    bindClearAndClose() {
      $('#andreani-filters-clear').on('click', () => {
        if (window.AndreaniGrid && AndreaniGrid.resetAll) AndreaniGrid.resetAll();
        this.render();
      });
      $('#andreani-filters-close').on('click', () => this.close());

      $('#andreani-active-pills-clear').on('click', () => {
        if (window.AndreaniGrid && AndreaniGrid.resetAll) AndreaniGrid.resetAll();
        this.render();
      });
    },

    toggle() {
      const $popover = $('#andreani-filters-popover');
      if ($popover.is(':visible')) {
        this.close();
      } else {
        this.open();
      }
    },

    open() {
      $('#andreani-filters-popover').prop('hidden', false);
      $('#andreani-filters-trigger').attr('aria-expanded', 'true');
    },

    close() {
      $('#andreani-filters-popover').prop('hidden', true);
      $('#andreani-filters-trigger').attr('aria-expanded', 'false');
    },

    /**
     * Renderiza el contador en el trigger y los pills activos abajo.
     * Se llama desde AndreaniGrid.computeExtraParams cada vez que cambian filtros.
     */
    render() {
      const active = this.collectActive();
      const total = active.length;

      const $count = $('#andreani-filters-trigger .andreani-filter-trigger__count');
      $count.text(total);
      $count.prop('hidden', total === 0);
      $('#andreani-filters-trigger').toggleClass('is-active', total > 0);

      const $pillsContainer = $('#andreani-active-pills');
      const $list = $('#andreani-active-pills-list');
      $list.empty();

      if (total === 0) {
        $pillsContainer.removeClass('is-active');
        return;
      }

      $pillsContainer.addClass('is-active');
      active.forEach((pill) => {
        const $el = $(
          '<span class="andreani-active-pill" data-pill-type="' + pill.type + '" data-pill-key="' + pill.key + '">' +
            '<span class="andreani-active-pill__text"></span>' +
            '<button type="button" class="andreani-active-pill__remove" aria-label="' + (pill.removeLabel || 'Quitar filtro') + '">' +
              '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
            '</button>' +
          '</span>'
        );
        $el.find('.andreani-active-pill__text').text(pill.label);
        $list.append($el);
      });
    },

    /**
     * Lee el estado del DOM (chips activos + date inputs) y devuelve la lista
     * de pills a mostrar.
     */
    collectActive() {
      const pills = [];

      $('.andreani-chip.andreani-chip--active').each((_, el) => {
        const $chip = $(el);
        const key = $chip.data('quick-filter');
        if (!key) return;
        const label = this.SECTION_LABELS[key] || $chip.text().trim();
        pills.push({ type: 'chip', key: key, label: label, removeLabel: 'Quitar ' + label });
      });

      const dateFrom = ($('input[name="andreani_date_from"]').val() || '').trim();
      const dateTo   = ($('input[name="andreani_date_to"]').val() || '').trim();
      const isQuickDate = $('.andreani-chip[data-filter-group="date"].andreani-chip--active').length > 0;

      if (!isQuickDate && (dateFrom || dateTo)) {
        const display = (iso) => iso.split('-').reverse().join('/');
        const text = (dateFrom ? display(dateFrom) : '…') + ' → ' + (dateTo ? display(dateTo) : '…');
        pills.push({ type: 'date-range', key: 'custom', label: text, removeLabel: 'Quitar rango de fechas' });
      }

      return pills;
    },

    /**
     * Handler de los pills "✕". Lo expone como API porque se bindea en delegate.
     */
    removePill($pill) {
      const type = $pill.data('pill-type');
      const key  = $pill.data('pill-key');

      if (type === 'chip') {
        $('.andreani-chip[data-quick-filter="' + key + '"]')
          .removeClass('andreani-chip--active')
          .attr('aria-pressed', 'false')
          .attr('aria-checked', 'false');
      } else if (type === 'date-range') {
        $('input[name="andreani_date_from"]').val('');
        $('input[name="andreani_date_to"]').val('');
      }

      if (window.AndreaniGrid && AndreaniGrid.updateResetVisibility) {
        AndreaniGrid.updateResetVisibility();
      }

      const extraParams = window.AndreaniGrid && AndreaniGrid.computeExtraParams
        ? AndreaniGrid.computeExtraParams()
        : {};

      if (window.AndreaniTableLoader && AndreaniTableLoader.loadTable) {
        AndreaniTableLoader.loadTable(extraParams);
      }

      this.render();
    }
  };

  /**
   * AndreaniBulkBar — barra flotante contextual que aparece cuando el
   * merchant selecciona filas con los checkboxes. Evalúa los `data-*` de
   * cada checkbox y renderiza solo las acciones que aplican al 100% de la
   * selección.
   *
   * Para registrar una acción nueva, sumar una entrada al array `ACTIONS`
   * con un `predicate(selection)` que retorne true si la acción aplica.
   * El `handler(ids, selection, $btn)` recibe los ids, la selección completa
   * (para inspeccionar `hasTracking` y demás) y el botón disparador.
   */
  const AndreaniBulkBar = {
    SELECTOR_ROW_CHECKBOX: 'input[name="order_ids[]"]',

    config: window.andreani_admin || {},

    ACTIONS: [],

    busy: false,

    init() {
      const $bar = $('#andreani-bulk-bar');
      if (!$bar.length) return;

      this.registerActions();
      this.bindCheckboxes();
      this.bindClose();
      $(window).on('resize', () => {
        if (!$bar.prop('hidden')) this.center();
      });
    },

    center() {
      const $bar = $('#andreani-bulk-bar');
      const card = document.querySelector('.andreani-page-card');
      if (!card || window.matchMedia('(max-width: 782px)').matches) {
        $bar.css('left', '');
        return;
      }
      const rect = card.getBoundingClientRect();
      $bar.css('left', rect.left + rect.width / 2);
    },

    t(key) {
      return this.config.i18n?.[key] || key;
    },

    /**
     * Tabla de acciones disponibles. Cada predicate recibe `selection`:
     * un array de objetos { id, status, hasTracking, shipped, hasError, clientType, paymentPending }.
     * El handler recibe (ids, selection, $btn).
     */
    registerActions() {
      const self = this;
      this.ACTIONS = [
        {
          key: 'pay',
          label: self.t('bulk_pay_label'),
          variant: 'ghost',
          predicate: (sel) => sel.length > 0 && sel.some(r => r.paymentPending),
          handler: () => window.open(self.config.pyme_historial_url, '_blank', 'noopener')
        },
        {
          // Mejora sobre la competencia: se habilita con cualquier selección y el
          // handler reporta los parciales (incluidas vs. omitidas sin tracking) en
          // vez de descartar en silencio las que aún no tienen seguimiento.
          key: 'download-labels',
          label: self.t('bulk_labels_label'),
          variant: 'primary',
          predicate: (sel) => sel.length > 0,
          handler: (ids, selection, $btn) => self.downloadLabels(selection, $btn)
        }
        // v1.6.0 podrá sumar: re-empaquetar (todos not_packaged + error).
      ];
    },

    /**
     * Descarga masiva de etiquetas. Separa las órdenes con tracking (incluidas) de
     * las que todavía no lo tienen (omitidas), respeta el tope del server y muestra
     * un notice con el resumen de parciales.
     */
    downloadLabels(selection, $btn) {
      if (this.busy) return;

      const withTracking = selection.filter(r => r.hasTracking).map(r => r.id);
      const skipped      = selection.filter(r => !r.hasTracking).length;

      if (!withTracking.length) {
        AndreaniShipments.showNotice($btn, this.t('bulk_labels_none'), 'warning');
        return;
      }

      const self = this;
      this.busy = true;
      $btn.prop('disabled', true);
      const release = AndreaniLoader.busy(this.t('bulk_labels_loading'));

      $.post(this.config.ajax_url || ajaxurl, {
        action: 'andreani_bulk_etiquetas',
        nonce: this.config.nonce_bulk_etiquetas,
        order_ids: withTracking
      })
        .done((res) => {
          if (res.success && res.data?.pdf) {
            AndreaniShipments.downloadFile(res.data.pdf, res.data.filename, 'pdf');
            self.notifySummary($btn, res.data.included, res.data.skipped + skipped);
          } else {
            AndreaniShipments.showNotice($btn, res.data?.message || self.t('bulk_labels_error'), 'error');
          }
        })
        .fail(() => {
          AndreaniShipments.showNotice($btn, self.t('network_error'), 'error');
        })
        .always(() => {
          self.busy = false;
          $btn.prop('disabled', false);
          release();
        });
    },

    notifySummary($btn, included, skipped) {
      let message = (included === 1)
        ? '1 etiqueta descargada.'
        : included + ' etiquetas descargadas.';

      if (skipped > 0) {
        message += (skipped === 1)
          ? ' 1 orden sin seguimiento todavía, no se incluyó.'
          : ' ' + skipped + ' órdenes sin seguimiento todavía, no se incluyeron.';
      }

      AndreaniShipments.showNotice($btn, message, skipped > 0 ? 'warning' : 'success');
    },

    bindCheckboxes() {
      const self = this;
      $(document).on('change', this.SELECTOR_ROW_CHECKBOX + ', .check-column input[type="checkbox"]', function() {
        self.update();
      });
      $(document).on('andreani:table-loaded', function() {
        self.update();
      });
    },

    bindClose() {
      const self = this;
      $('#andreani-bulk-bar-close').on('click', function() {
        $(self.SELECTOR_ROW_CHECKBOX + ':checked, .check-column input[type="checkbox"]:checked').prop('checked', false);
        self.update();
      });
    },

    selection() {
      return $(this.SELECTOR_ROW_CHECKBOX + ':checked').map(function() {
        const $cb = $(this);
        return {
          id: $cb.val(),
          status: $cb.data('status') || '',
          hasTracking: $cb.data('has-tracking') === 1 || $cb.data('has-tracking') === '1',
          shipped: $cb.data('shipped') === 1 || $cb.data('shipped') === '1',
          hasError: $cb.data('has-error') === 1 || $cb.data('has-error') === '1',
          clientType: $cb.data('client-type') || '',
          paymentPending: $cb.data('payment-pending') === 1 || $cb.data('payment-pending') === '1'
        };
      }).get();
    },

    update() {
      const selection = this.selection();
      const $bar = $('#andreani-bulk-bar');
      const $count = $('#andreani-bulk-bar-count');
      const $label = $('#andreani-bulk-bar-label');
      const $actions = $('#andreani-bulk-bar-actions');

      if (selection.length === 0) {
        $bar.prop('hidden', true);
        $('.andreani-page-card').removeClass('has-bulk-bar');
        $actions.empty();
        return;
      }

      $count.text(selection.length);
      $label.text($label.data(selection.length === 1 ? 'one' : 'many'));
      this.renderActions(selection);
      $bar.prop('hidden', false);
      $('.andreani-page-card').addClass('has-bulk-bar');
      this.center();
    },

    renderActions(selection) {
      const $actions = $('#andreani-bulk-bar-actions');
      $actions.empty();

      const ids = selection.map(r => r.id);

      this.ACTIONS.forEach((action) => {
        if (!action.predicate(selection)) return;

        const $btn = $('<button type="button" class="andr-btn andr-btn--sm"></button>')
          .addClass('andr-btn--' + (action.variant || 'primary'))
          .attr('data-bulk-action', action.key)
          .text(action.label)
          .on('click', () => action.handler(ids, selection, $btn));

        $actions.append($btn);
      });
    }
  };

  // Wrap computeExtraParams para disparar el render del popover/pills
  // sin tocar la lógica original (que es compartida con muchos lugares).
  const __originalComputeExtraParams = AndreaniGrid.computeExtraParams;
  AndreaniGrid.computeExtraParams = function() {
    const result = __originalComputeExtraParams.apply(this, arguments);
    if (window.AndreaniFilters && AndreaniFilters.render) {
      // Render asíncrono para que el DOM ya tenga las clases aplicadas
      // por el caller antes de leer el estado.
      setTimeout(() => AndreaniFilters.render(), 0);
    }
    return result;
  };

  // Delegate de "✕" en pills activos.
  $(document).on('click', '.andreani-active-pill__remove', function() {
    if (window.AndreaniFilters) AndreaniFilters.removePill($(this).closest('.andreani-active-pill'));
  });

  /* ========================================
   * PRINT SETTINGS (AndreaniPrintSettings)
   * ======================================== */
  const AndreaniPrintSettings = {
    config: window.andreani_admin || {},
    loaded: false,

    previewLabel(x, y, w, h) {
      const r = (n) => +n.toFixed(2);
      const pad = Math.min(w, h) * 0.09;
      const inner = w - pad * 2;
      const bars = Math.max(8, Math.round(inner / 4));
      const barW = inner / bars;
      const barH = h * 0.16;
      const barY = y + h - pad - barH;
      const headH = h * 0.12;
      const headX = x + pad;
      const headY = y + pad;
      const logoR = headH * 0.34;
      const logoCx = headX + headH * 0.72;
      const logoCy = headY + headH / 2;
      const strokeW = r(logoR * 0.26);

      let svg = `<rect x="${r(x)}" y="${r(y)}" width="${r(w)}" height="${r(h)}" rx="2" style="fill:var(--andr-color-surface);stroke:var(--andr-color-border)"/>`;
      svg += `<rect x="${r(headX)}" y="${r(headY)}" width="${r(inner)}" height="${r(headH)}" rx="1" style="fill:var(--andr-color-text-strong)"/>`;
      svg += `<g transform="rotate(-20 ${r(logoCx)} ${r(logoCy)})"><ellipse cx="${r(logoCx)}" cy="${r(logoCy)}" rx="${r(logoR)}" ry="${r(logoR * 0.6)}" stroke-width="${strokeW}" style="fill:none;stroke:var(--andr-color-surface)"/></g>`;
      svg += `<path d="M ${r(logoCx - logoR * 0.48)} ${r(logoCy + logoR * 0.5)} L ${r(logoCx)} ${r(logoCy - logoR * 0.5)} L ${r(logoCx + logoR * 0.48)} ${r(logoCy + logoR * 0.5)}" stroke-linejoin="round" stroke-width="${strokeW}" style="fill:none;stroke:var(--andr-color-surface)"/>`;
      [0.34, 0.46, 0.58].forEach((f, i) => {
        svg += `<rect x="${r(x + pad)}" y="${r(y + h * f)}" width="${r(inner * (0.9 - i * 0.22))}" height="${r(h * 0.035)}" rx="1" opacity="0.45" style="fill:var(--andr-color-text-subtle)"/>`;
      });
      for (let i = 0; i < bars; i++) {
        svg += `<rect x="${r(x + pad + i * barW)}" y="${r(barY)}" width="${r(barW * (i % 3 === 0 ? 0.7 : 0.34))}" height="${r(barH)}" style="fill:var(--andr-color-text-strong)"/>`;
      }
      return svg;
    },

    previewSvg(shape) {
      const zebra = shape === 'zebra';
      const paper = zebra ? { w: 150, h: 225 } : { w: 208, h: 294 };
      const vb = { w: 300, h: 320 };
      const ox = (vb.w - paper.w) / 2;
      const oy = (vb.h - paper.h) / 2;
      let labels = '';

      if (zebra) {
        const m = paper.w * 0.06;
        labels = this.previewLabel(ox + m, oy + m, paper.w - m * 2, paper.h - m * 2);
      } else {
        const m = paper.w * 0.1;
        const gap = paper.w * 0.06;
        const lw = (paper.w - m * 2 - gap) / 2;
        const lh = (paper.h - m * 2 - gap) / 2;
        if (shape === 'a4-4') {
          for (let row = 0; row < 2; row++) {
            for (let col = 0; col < 2; col++) {
              labels += this.previewLabel(ox + m + col * (lw + gap), oy + m + row * (lh + gap), lw, lh);
            }
          }
        } else {
          labels = this.previewLabel(ox + (paper.w - lw) / 2, oy + (paper.h - lh) / 2, lw, lh);
        }
      }

      return `<svg viewBox="0 0 ${vb.w} ${vb.h}" preserveAspectRatio="xMidYMid meet" aria-hidden="true" focusable="false"><rect x="${ox}" y="${oy}" width="${paper.w}" height="${paper.h}" rx="6" style="fill:var(--andr-color-surface);stroke:var(--andr-color-border)"/>${labels}</svg>`;
    },

    renderPreview() {
      const $modal = $('#andreani-print-settings-modal');
      const $radio = $modal.find('.andreani-print-option__radio:checked');
      $modal.find('[data-print-preview]').html($radio.length ? this.previewSvg($radio.data('preview-shape')) : '');
      $modal.find('[data-print-preview-caption]').text($radio.length ? $radio.data('preview-caption') : '');
    },

    init() {
      const $modal = $('#andreani-print-settings-modal');
      if (!$modal.length) return;

      const self = this;

      $(document).on('click', '#andreani-print-settings-trigger', (e) => {
        e.preventDefault();
        self.open();
      });

      $modal.on('click', '.andr-modal__backdrop, .andreani-modal__backdrop, .andr-modal__close, .andreani-modal__close', () => self.close());
      $modal.on('change', '.andreani-print-option__radio', function() {
        $('#andreani-print-settings-save').prop('disabled', !$(this).is(':checked'));
        self.renderPreview();
      });
      $modal.on('click', '#andreani-print-settings-save', () => self.save());

      $(document).on('keydown', (e) => {
        if (e.key === 'Escape' && $modal.is(':visible')) self.close();
      });
    },

    open() {
      const $modal = $('#andreani-print-settings-modal');
      $modal.show();
      if (AndreaniShipments.centerModal) AndreaniShipments.centerModal($modal);
      this.load();
    },

    close() {
      const $modal = $('#andreani-print-settings-modal');
      $modal.hide();
      $modal.find('.andr-modal__container').css({ left: '', top: '', transform: '', position: '' });
    },

    showLoader(isLoading, onHidden) {
      const $box = $('#andreani-print-settings-modal [data-print-loader]');

      if (isLoading) {
        $('#andreani-print-settings-modal [data-print-options]').prop('hidden', true);
        AndreaniLoader.show($box, { size: 'lg', text: this.t('loader_print') });
      } else {
        AndreaniLoader.hide($box, onHidden);
      }
    },

    load() {
      const self = this;
      const $modal = $('#andreani-print-settings-modal');

      this.showLoader(true);
      $('#andreani-print-settings-save').prop('disabled', true);
      $modal.find('.andreani-temp-notice').remove();

      $.post(this.config.ajax_url || ajaxurl, {
        action: 'andreani_get_print_settings',
        nonce: this.config.nonce_print_get
      })
        .done((res) => {
          if (res.success) {
            const key = parseInt(res.data?.key, 10) || 1;
            $modal.find('.andreani-print-option__radio').prop('checked', false);
            $modal.find(`.andreani-print-option__radio[value="${key}"]`).prop('checked', true);
            $('#andreani-print-settings-save').prop('disabled', false);
            self.renderPreview();
            self.showLoader(false, () => $('#andreani-print-settings-modal [data-print-options]').prop('hidden', false));
            self.loaded = true;
          } else {
            self.showLoadError(res.data?.message || self.t('print_load_error'));
          }
        })
        .fail(() => self.showLoadError(self.t('network_error')));
    },

    showLoadError(message) {
      this.showLoader(false);
      $('#andreani-print-settings-modal [data-print-options]').prop('hidden', true);
      AndreaniShipments.showNotice($('#andreani-print-settings-modal .andr-modal__body'), message, 'error');
    },

    save() {
      const self = this;
      const $modal = $('#andreani-print-settings-modal');
      const key = $modal.find('.andreani-print-option__radio:checked').val();
      if (!key) return;

      const $btn = $('#andreani-print-settings-save');
      $btn.prop('disabled', true);
      const release = AndreaniLoader.busy(this.t('print_save_loading'));

      $.post(this.config.ajax_url || ajaxurl, {
        action: 'andreani_save_print_settings',
        nonce: this.config.nonce_print_save,
        key: key
      })
        .done((res) => {
          if (res.success) {
            self.close();
            AndreaniShipments.showNotice($('.andreani-shipments-wrap, .andreani-settings-wrapper').first(), res.data?.message || self.t('print_save_success'), 'success');
          } else {
            AndreaniShipments.showNotice($('#andreani-print-settings-modal .andr-modal__body'), res.data?.message || self.t('print_save_error'), 'error');
          }
        })
        .fail(() => {
          AndreaniShipments.showNotice($('#andreani-print-settings-modal .andr-modal__body'), self.t('network_error'), 'error');
        })
        .always(() => {
          $btn.prop('disabled', false);
          release();
        });
    },

    t(key) {
      return this.config.i18n?.[key] || key;
    }
  };

  /* ========================================
   * PRODUCTS GRID (AndreaniProductsGrid)
   * ======================================== */
  const AndreaniProductsGrid = {
    config: window.andreani_admin || {},
    loaded: false,
    $container: null,
    isLoading: false,
    currentParams: { paged: 1, per_page: 10, s: '', service: [], mode: [] },

    init() {
      this.$container = $('#andreani-products-table-container');
      if (!this.$container.length || !$('.andreani-products-wrap').data('async-load')) return;
      this.config = window.andreani_admin || {};
      this.loadTable();
      this.bindEvents();
    },

    bindEvents() {
      const self = this;

      $('#andreani-products-refresh').on('click', (e) => {
        e.preventDefault();
        if (!self.isLoading) self.loadTable();
      });

      $(document).on('submit', '#andreani-products-form', (e) => {
        e.preventDefault();
        if (!self.isLoading) self.loadTable({ paged: 1 });
      });

      $(document).on('click', '.andreani-products-wrap [data-filter-group]', function() {
        const group = $(this).attr('data-filter-group');
        const value = $(this).attr('data-filter-value');
        const current = self.currentParams[group] || [];
        const next = current.indexOf(value) === -1 ? current.concat(value) : current.filter((v) => v !== value);
        self.loadTable({ paged: 1, [group]: next });
      });

      $('#andreani-products-filters-trigger').on('click', function(e) {
        e.stopPropagation();
        self.toggleFilters();
      });

      $(document).on('click', function(e) {
        if (!$(e.target).closest('#andreani-products-filters-popover, #andreani-products-filters-trigger').length) self.toggleFilters(false);
      });

      $(document).on('keydown', function(e) {
        if (e.key === 'Escape') self.toggleFilters(false);
      });

      $('#andreani-products-filters-close').on('click', () => self.toggleFilters(false));
      $('#andreani-products-filters-clear, #andreani-products-pills-clear').on('click', () => self.loadTable({ paged: 1, service: [], mode: [] }));
      $('#andreani-products-missing-link').on('click', () => self.loadTable({ paged: 1, service: ['missing'], mode: [] }));

      $(document).on('click', '#andreani-products-pills-list .andreani-active-pill__remove', function() {
        const $pill = $(this).closest('.andreani-active-pill');
        const group = $pill.attr('data-pill-group');
        self.loadTable({ paged: 1, [group]: (self.currentParams[group] || []).filter((v) => v !== $pill.attr('data-pill-value')) });
      });

      $(document).on('click', '.andreani-per-page__btn', function() {
        if (!$('.andreani-products-wrap').length) return;
        const val = parseInt($(this).data('per-page'), 10);
        $('.andreani-per-page__btn').removeClass('is-active').attr('aria-pressed', 'false');
        $(this).addClass('is-active').attr('aria-pressed', 'true');
        self.currentParams.per_page = val;
        self.loadTable({ paged: 1 });
      });

      $(document).on('click', '.andreani-products-wrap .andreani-page-btn', function() {
        if ($(this).attr('aria-disabled') === 'true') return;
        const p = parseInt($(this).attr('data-paged'), 10);
        if (p > 0) self.loadTable({ paged: p });
      });
    },

    loadTable(extra) {
      const self = this;
      if (self.isLoading) return;

      const editor = window.AndreaniProductEdit;
      if (editor && editor.$row) {
        editor.requestClose(() => self.loadTable(extra));
        return;
      }
      if (editor) editor.release();

      self.isLoading = true;

      const s = $('#andreani-products-search').val() || '';
      const $activePerPage = $('.andreani-per-page__btn.is-active');
      const perPage = $activePerPage.length
        ? parseInt($activePerPage.data('per-page'), 10)
        : (self.currentParams.per_page || 10);

      self.currentParams = Object.assign({}, self.currentParams, { s, per_page: perPage }, extra || {});
      self.renderFilters();

      self.showLoader();

      const params = Object.assign({}, self.currentParams, {
        action: 'andreani_products_table',
        nonce:  self.config.nonce_products_table,
      });

      $.post(self.config.ajax_url || ajaxurl, params)
        .done((res) => AndreaniLoader.hide(self.$container, () => {
          if (res.success) {
            self.loaded = true;
            self.$container.html(res.data.html);
            AndreaniBoxPreview.mountThumbs(self.$container.get(0));
            AndreaniBoxPreview.mountIcons(self.$container.get(0));
            self.updateCounts(res.data.counts);
            self.updateAnalyzing(res.data.analyzing);
          } else {
            self.$container.html('<p class="andreani-products-empty">' + escapeHtml((self.config.i18n || {}).products_error || 'Error al cargar.') + '</p>');
          }
        }))
        .fail(() => AndreaniLoader.hide(self.$container, () => {
          self.$container.html('<p class="andreani-products-empty">' + escapeHtml((self.config.i18n || {}).products_error || 'Error al cargar.') + '</p>');
        }))
        .always(() => { self.isLoading = false; });
    },

    updateCounts(counts) {
      Object.keys(counts || {}).forEach((key) => {
        $('[data-count="' + key + '"]').text(Number(counts[key]).toLocaleString('es-AR'));
      });
      $('#andreani-products-missing').prop('hidden', !(counts.missing > 0));
    },

    toggleFilters(open) {
      const show = open === undefined ? $('#andreani-products-filters-popover').prop('hidden') : open;
      $('#andreani-products-filters-popover').prop('hidden', !show);
      $('#andreani-products-filters-trigger').attr('aria-expanded', show ? 'true' : 'false');
    },

    renderFilters() {
      const params = this.currentParams;
      const pills = [];

      $('.andreani-products-wrap [data-filter-group]').each(function() {
        const group = $(this).attr('data-filter-group');
        const value = $(this).attr('data-filter-value');
        const on = (params[group] || []).indexOf(value) !== -1;
        $(this).attr('aria-pressed', on ? 'true' : 'false').toggleClass('andreani-chip--active', on);
        if (on) pills.push({ group, value, label: $(this).attr('data-filter-label') });
      });

      const $count = $('#andreani-products-filters-trigger .andreani-filter-trigger__count');
      $count.text(pills.length).prop('hidden', !pills.length);
      $('#andreani-products-filters-trigger').toggleClass('is-active', pills.length > 0);

      const $list = $('#andreani-products-pills-list').empty();
      pills.forEach((pill) => {
        const $el = $('<span class="andreani-active-pill"><span class="andreani-active-pill__text"></span>'
          + '<button type="button" class="andreani-active-pill__remove" aria-label="Quitar filtro"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></span>');
        $el.attr({ 'data-pill-group': pill.group, 'data-pill-value': pill.value }).find('.andreani-active-pill__text').text(pill.label);
        $list.append($el);
      });
      $('#andreani-products-pills').toggleClass('is-active', pills.length > 0);
    },

    flash(message) {
      $('.andreani-products-flash').remove();
      const $msg = $('<p class="andreani-products-flash andreani-products-inline-msg andreani-products-inline-msg--success" role="status"></p>').text(message);
      this.$container.before($msg);
      setTimeout(() => $msg.remove(), 3500);
    },

    updateAnalyzing(progress) {
      const $notice = $('#andreani-products-analyzing');
      if (progress) {
        $notice.find('[data-analyzing="done"]').text(Number(progress.done).toLocaleString('es-AR'));
        $notice.find('[data-analyzing="total"]').text(Number(progress.total).toLocaleString('es-AR'));
      }
      $notice.prop('hidden', !progress);
    },

    showLoader() {
      const i18n = this.config.i18n || {};

      AndreaniLoader.show(this.$container, this.loaded
        ? { size: 'lg', text: i18n.loader_products_update }
        : { size: 'lg', phrases: i18n.loader_products_phrases });
    },
  };

  /* ========================================
   * PRODUCT EDITOR (AndreaniProductEdit)
   * ======================================== */
  const AndreaniProductEdit = {
    config: window.andreani_admin || {},
    $panel: null,
    $holder: null,
    $row: null,
    preview: null,
    baseline: '',
    pending: null,

    wooSku: '',

    MODE_SINGLE: 'single',
    MODE_APILADO: 'apilado',
    MODE_MULTIBULTO: 'multibulto',

    init() {
      this.$panel = $('#andreani-product-editor');
      if (!this.$panel.length) return;
      this.$holder = $('#andreani-product-editor-holder');
      this.config = window.andreani_admin || {};
      AndreaniBoxPreview.configure($.extend({}, this.config.box_preview, { i18n: this.strings() }));
      this.preview = AndreaniBoxPreview.createPreview({
        root: this.$panel.find('.andr-dispatch__preview').get(0),
        ajaxUrl: this.config.ajax_url || ajaxurl,
        nonce: this.config.nonce_preview_bultos,
        getDraft: () => this.previewPayload(),
        hint: () => (this.currentMode() === this.MODE_MULTIBULTO ? AndreaniBoxPreview.ignoredHint(this.collectBultos()) : ''),
      });
      this.bindEvents();
    },

    strings() {
      return (this.config.i18n || {}).dispatch || {};
    },

    bindEvents() {
      const self = this;
      const ROW = '.andreani-products-wrap .andreani-product-item[data-product-id]';

      $(document).on('click', ROW, function() {
        self.toggle($(this));
      });

      $(document).on('keydown', ROW, function(e) {
        if (e.target !== this) return;
        if (e.key === 'Enter' || e.key === ' ' || e.key === 'Spacebar') {
          e.preventDefault();
          self.toggle($(this));
        }
      });

      $('#andreani-product-edit-cancel').on('click', () => this.requestClose());

      $('#andreani-edit-discard').on('click', () => {
        const then = self.pending;
        self.pending = null;
        $('#andreani-edit-confirm').prop('hidden', true);
        self.close();
        if (then) then();
      });

      $('#andreani-edit-keep').on('click', () => {
        self.pending = null;
        $('#andreani-edit-confirm').prop('hidden', true);
      });

      $('#andreani-bultos-add').on('click', () => {
        self.addCard();
        self.afterChange();
        const grid = $('#andreani-bultos-cards').closest('.andr-boxes__grid').get(0);
        const first = $('#andreani-bultos-cards .andreani-bulto-card').last().find('input').get(0);
        grid.scrollTop = grid.scrollHeight;
        if (first) first.focus({ preventScroll: true });
      });
      this.$panel.on('click', '.andreani-bulto-card__remove', function() {
        $(this).closest('.andreani-bulto-card').remove();
        self.afterChange();
      });

      this.$panel.on('click', '.andreani-bulto-card__switch', () => {
        $('#andreani-bultos-cards').empty();
        self.setMode(self.MODE_APILADO);
      });

      this.$panel.on('input',
        '#andreani-edit-weight, #andreani-edit-length, #andreani-edit-width, #andreani-edit-height, .andreani-bulto-card input',
        function() {
          $('#andreani-edit-bultos-invalid').hide();
          $(this).closest('.andreani-bulto-card').find('.andreani-bulto-card__incomplete').hide();
          self.markSameDims();
          self.recalcStatus();
        });

      this.$panel.on('change', '.andr-seg__input', function() {
        self.setMode($(this).val());
      });

      this.$panel.on('input', '#andreani-edit-apilado-fields input', () => {
        $('#andreani-edit-apilado-invalid').toggle(self.currentMode() === self.MODE_APILADO && !self.collectApilado());
        self.recalcStatus();
      });

      this.$panel.on('input', '#andreani-edit-sku', () => this.refreshSkuHint());
      this.$panel.on('click', '.andr-pem__hint-action', () => {
        $('#andreani-edit-sku').val(this.wooSku);
        this.refreshSkuHint();
      });

      $('#andreani-product-edit-save').on('click', () => this.save());
      $('#andreani-edit-discard-go').on('click', () => this.save(true));
      $('#andreani-edit-discard-cancel').on('click', () => $('#andreani-edit-confirm-discard').prop('hidden', true));
      $('#andreani-edit-quote-submit').on('click', () => this.quote());
      this.$panel.on('input change', '[data-andr="qty"], [data-andr="qty-input"]', function() {
        const qty = parseInt($(this).val(), 10);
        if (qty >= 1 && qty <= 99) {
          $('#andreani-edit-quote-qty').val(qty);
          self.markQuoteStale();
          self.syncQuoteContext();
        }
      });
      this.$panel.on('click', '.andr-stepper__btn', function() {
        const $qty = $('#andreani-edit-quote-qty');
        $qty.val((parseInt($qty.val(), 10) || 1) + parseInt($(this).attr('data-step'), 10)).trigger('change');
      });
      this.$panel.on('change', '#andreani-edit-quote-qty', function() {
        const qty = Math.min(99, Math.max(1, parseInt($(this).val(), 10) || 1));
        $(this).val(qty);
        const $qtyInput = self.$panel.find('[data-andr="qty-input"]');
        if (parseInt($qtyInput.val(), 10) !== qty) $qtyInput.val(qty).trigger('change');
        self.markQuoteStale();
        self.syncQuoteContext();
      });
      this.$panel.on('click', '.andr-tabs__item[data-tab="pem-quote"]', () => this.syncQuoteContext());
      new MutationObserver(() => this.syncQuoteContext()).observe(this.$panel.find('[data-andr="result"]').get(0), { childList: true, subtree: true });
    },

    syncQuoteContext() {
      const qty = parseInt($('#andreani-edit-quote-qty').val(), 10) || 1;
      const title = qty <= parseInt(this.$panel.find('[data-andr="qty"]').attr('max'), 10)
        ? this.$panel.find('.andr-dispatch__result-title').first().text()
        : '';
      const unit = qty === 1 ? this.strings().preview_unit_one : this.strings().preview_unit_many;
      $('#andreani-edit-quote-sum').text(qty + ' ' + unit + (title ? ' · ' + title : ''));

      const src = this.$panel.find('.andr-dispatch__stage svg').get(0);
      const dst = document.getElementById('andreani-edit-quote-art');
      if (src && dst) {
        if (src.getAttribute('viewBox')) dst.setAttribute('viewBox', src.getAttribute('viewBox'));
        dst.innerHTML = src.innerHTML;
      }
    },

    markQuoteStale() {
      const $results = $('#andreani-edit-quote-results');
      if (!$results.prop('hidden') && $results.children().length) $results.addClass('is-stale');
    },

    numAttr($row, key) {
      return parseFloat($row.attr('data-' + key)) || '';
    },

    snapshot() {
      return JSON.stringify([
        this.currentMode(),
        this.$panel.find('input[type="number"], input[type="text"]').not('#andreani-edit-quote-cp, #andreani-edit-quote-qty, [data-andr="qty-input"], [data-andr="qty"]').map((i, el) => el.value).get(),
      ]);
    },

    isDirty() {
      return !!this.$row && this.snapshot() !== this.baseline;
    },

    toggle($row) {
      if (this.$row && this.$row[0] === $row[0]) {
        this.requestClose();
        return;
      }
      this.requestClose(() => this.open($row));
    },

    requestClose(then) {
      if (!this.$row) {
        if (then) then();
        return;
      }

      if (this.isDirty()) {
        this.pending = then || null;
        const $confirm = $('#andreani-edit-confirm').prop('hidden', false);
        $confirm.get(0).scrollIntoView({ block: 'nearest' });
        return;
      }

      this.close();
      if (then) then();
    },

    open($row) {
      const $content = $row.closest('.andreani-product-entry').find('.andreani-product-detail__content');
      const parse = (key, fallback) => {
        try {
          const value = JSON.parse($row.attr(key) || '');
          return value && typeof value === 'object' ? value : fallback;
        } catch (e) {
          return fallback;
        }
      };

      this.$panel.appendTo($content);
      this.$row = $row;

      $('#andreani-edit-product-id').val($row.attr('data-product-id'));
      const productName = $row.attr('data-name') || '';
      const editUrl = $row.attr('data-edit-url') || '';
      $('#andreani-edit-product-name').text(productName).attr('title', productName);
      $('#andreani-edit-product-link').attr('href', editUrl || '#').prop('hidden', !editUrl);
      this.wooSku = $row.attr('data-woo-sku') || '';
      $('#andreani-edit-sku').val($row.attr('data-sku') || '');
      this.refreshSkuHint();
      $('#andreani-edit-main-ref').val($row.attr('data-main-ref') || '');
      $('#andreani-edit-weight').val(this.numAttr($row, 'weight'));
      $('#andreani-edit-length').val(this.numAttr($row, 'length'));
      $('#andreani-edit-width').val(this.numAttr($row, 'width'));
      $('#andreani-edit-height').val(this.numAttr($row, 'height'));
      $('#andreani-edit-message').hide().text('').removeClass('andreani-products-inline-msg--success andreani-products-inline-msg--error');
      $('#andreani-edit-quote-results').prop('hidden', true).removeClass('is-stale').empty();
      $('#andreani-edit-quote-cp').val(AndreaniQuoteCp.get());
      $('#andreani-edit-quote-qty').val(this.$panel.find('[data-andr="qty-input"]').val());
      this.$panel.find('.andr-tabs__item[data-tab="pem-config"]').trigger('click');
      $('#andreani-edit-confirm').prop('hidden', true);
      $('#andreani-edit-apilado-invalid, #andreani-edit-bultos-invalid').hide();

      $('#andreani-edit-confirm-discard').prop('hidden', true);

      let bultos = parse('data-bultos-json', []);
      if (!Array.isArray(bultos)) bultos = [];
      this.renderCards(bultos);

      let apilado = parse('data-apilado-json', {});
      if (Array.isArray(apilado)) apilado = {};
      this.renderApilado(apilado);

      let mode = this.MODE_SINGLE;
      if (bultos.length) mode = this.MODE_MULTIBULTO;
      else if (this.isValidApilado(apilado)) mode = this.MODE_APILADO;
      this.setMode(mode);

      this.baseline = this.snapshot();

      $row.attr('aria-expanded', 'true').closest('.andreani-product-entry').addClass('is-open');

      if ($row.attr('data-missing') === '1') {
        this.$panel.find('.andreani-product-dims input').filter(function() { return !parseFloat($(this).val()); }).first().focus();
      }
    },

    release() {
      this.pending = null;
      if (this.$row) {
        this.$row.attr('aria-expanded', 'false');
        this.$row = null;
      }
      if (this.$panel && this.$holder) this.$panel.appendTo(this.$holder);
    },

    close($replacement) {
      const $row = this.$row;
      if (!$row) return;

      const $entry = $row.closest('.andreani-product-entry');
      const hadFocus = this.$panel.get(0).contains(document.activeElement);
      this.$row = null;
      this.pending = null;
      $entry.removeClass('is-open');

      if ($replacement) {
        $row.replaceWith($replacement);
      } else {
        $row.attr('aria-expanded', 'false');
      }

      if (hadFocus) ($replacement || $row).get(0).focus({ preventScroll: true });

      setTimeout(() => {
        if (!this.$row) this.$panel.appendTo(this.$holder);
      }, 350);
    },

    refreshSkuHint() {
      const $hint = $('#andreani-edit-sku-hint').removeClass('andr-pem__hint--error');
      const value = $.trim($('#andreani-edit-sku').val());
      const text = (key) => $hint.attr('data-' + key);
      $('#andreani-edit-sku').attr('title', text('default'));

      if (this.wooSku && (value === this.wooSku || !value)) {
        $hint.text(text('woo'));
      } else if (this.wooSku) {
        $hint.text(text('own') + ' · ').append($('<button type="button" class="andr-pem__hint-action"></button>').text(text('use-woo')));
      } else {
        $hint.text('');
      }
    },

    currentMode() {
      const mode = this.$panel.find('.andr-seg__input:checked').val();
      return (mode === this.MODE_APILADO || mode === this.MODE_MULTIBULTO) ? mode : this.MODE_SINGLE;
    },

    setMode(mode) {
      mode = (mode === this.MODE_APILADO || mode === this.MODE_MULTIBULTO) ? mode : this.MODE_SINGLE;

      $('#andreani-edit-confirm-discard').prop('hidden', true);

      this.$panel.find('.andr-seg__input').each(function() {
        $(this).prop('checked', $(this).val() === mode);
      });

      if (mode === this.MODE_APILADO) {
        const $maxUnits = $('#andreani-edit-apilado-max-units');
        if (!$maxUnits.val()) $maxUnits.val($maxUnits.attr('min'));
      }

      if (mode === this.MODE_MULTIBULTO && !$('#andreani-bultos-cards .andreani-bulto-card').length) {
        this.addCard();
      }

      $('#andreani-edit-panel-apilado').prop('hidden', mode !== this.MODE_APILADO);
      $('#andreani-edit-panel-multibulto').prop('hidden', mode !== this.MODE_MULTIBULTO);
      $('#andreani-edit-apilado-invalid').hide();
      $('#andreani-edit-bultos-invalid').hide();

      this.afterChange();
    },

    cardHtml(index, b) {
      b = b || {};
      const v = (x) => (x === undefined || x === null) ? '' : x;
      const ownName = b.name && b.name !== 'Bulto ' + (index + 2) ? b.name : '';
      const u = this.config.units || { weight: 'kg', dimension: 'cm' };
      const s = this.strings();
      const field = (key, label, unit, cls, step, value) => AndreaniBoxPreview.dimField({ key, label, unit, cls, step, value: v(value) });
      return ''
        + '<div class="andreani-bulto-card andr-box">'
        +   '<span class="andr-box__title andreani-bulto-card__title">' + escapeHtml(s.piece_title) + ' ' + (index + 2) + '</span>'
        +   '<label class="andr-box__ref"><span class="screen-reader-text">' + escapeHtml(s.piece_reference) + '</span>'
        +     '<input type="text" class="b-name" maxlength="120" placeholder="' + escapeAttr(s.piece_reference_hint) + '" value="' + escapeAttr(ownName) + '"></label>'
        +   '<div class="andr-box__dims">'
        +     field('length', s.label_length, u.dimension, 'b-depth', '0.01', b.depth)
        +     field('width', s.label_width, u.dimension, 'b-width', '0.01', b.width)
        +     field('height', s.label_height, u.dimension, 'b-height', '0.01', b.height)
        +     field('weight', s.label_weight, u.weight, 'b-weight', '0.001', b.weight)
        +   '</div>'
        +   '<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-bulto-card__remove" aria-label="' + escapeAttr(s.piece_remove) + '">&times;</button>'
        +   '<div class="andreani-bulto-card__warning andr-dispatch__warnbox" style="display:none;">'
        +     '<span>' + escapeHtml(s.same_dims_warning || '') + '</span>'
        +     '<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm andreani-bulto-card__switch">' + escapeHtml(s.switch_to_apilado || '') + '</button>'
        +   '</div>'
        +   '<p class="andreani-bulto-card__incomplete andr-dispatch__warnbox" style="display:none;">' + escapeHtml(s.piece_incomplete || '') + '</p>'
        + '</div>';
    },

    renderCards(bultos) {
      const $cards = $('#andreani-bultos-cards');
      $cards.empty();
      (bultos || []).forEach((b, i) => $cards.append(this.cardHtml(i, b)));
      this.afterChange();
    },

    addCard(b) {
      const $cards = $('#andreani-bultos-cards');
      const i = $cards.find('.andreani-bulto-card').length;
      $cards.append(this.cardHtml(i, b));
    },

    afterChange() {
      const title = this.strings().piece_title;
      $('#andreani-edit-box-title').text(this.currentMode() === this.MODE_MULTIBULTO ? title + ' 1' : this.strings().box_single);
      $('#andreani-bultos-cards .andreani-bulto-card').each(function(i) {
        $(this).find('.andreani-bulto-card__title').text(title + ' ' + (i + 2));
      });
      this.markSameDims();
      this.recalcStatus();
    },

    round2(value) {
      return Math.round((parseFloat(value) || 0) * 100) / 100;
    },

    sortedDims(a, b, c) {
      return [this.round2(a), this.round2(b), this.round2(c)].sort((x, y) => x - y).join('|');
    },

    partialCards() {
      return $('#andreani-bultos-cards .andreani-bulto-card').filter(function() {
        const filled = ['.b-height', '.b-width', '.b-depth', '.b-weight']
          .filter((sel) => (parseFloat($(this).find(sel).val()) || 0) > 0).length;
        return filled > 0 && filled < 4;
      });
    },

    markSameDims() {
      const width = this.round2($('#andreani-edit-width').val());
      const height = this.round2($('#andreani-edit-height').val());
      const depth = this.round2($('#andreani-edit-length').val());
      const hasPrincipal = width > 0 && height > 0 && depth > 0;
      const self = this;

      $('#andreani-bultos-cards .andreani-bulto-card').each(function() {
        const $c = $(this);
        const same = hasPrincipal
          && self.sortedDims($c.find('.b-height').val(), $c.find('.b-width').val(), $c.find('.b-depth').val())
            === self.sortedDims(height, width, depth);
        $c.find('.andreani-bulto-card__warning').toggle(!!same);
      });
    },

    collectBultos() {
      const bultos = [];
      $('#andreani-bultos-cards .andreani-bulto-card').each(function() {
        const $c = $(this);
        bultos.push({
          name:   ($c.find('.b-name').val() || '').trim(),
          height: parseFloat($c.find('.b-height').val()) || 0,
          width:  parseFloat($c.find('.b-width').val())  || 0,
          depth:  parseFloat($c.find('.b-depth').val())  || 0,
          weight: parseFloat($c.find('.b-weight').val()) || 0,
        });
      });
      return bultos;
    },

    hasCompleteBulto() {
      return this.collectBultos().some((b) => b.weight > 0 && b.height > 0 && b.width > 0 && b.depth > 0);
    },

    draftPayload() {
      const mode = this.currentMode();
      const apilado = this.collectApilado();
      return {
        weight:        $('#andreani-edit-weight').val(),
        length:        $('#andreani-edit-length').val(),
        width:         $('#andreani-edit-width').val(),
        height:        $('#andreani-edit-height').val(),
        dispatch_mode: mode,
        bultos_json:   JSON.stringify(mode === this.MODE_MULTIBULTO ? this.collectBultos() : []),
        main_ref:      mode === this.MODE_MULTIBULTO ? $.trim($('#andreani-edit-main-ref').val()) : '',
        apilado_json:  JSON.stringify(mode === this.MODE_APILADO ? (apilado || {}) : {}),
      };
    },

    previewPayload() {
      const draft = this.draftPayload();
      if (this.currentMode() === this.MODE_MULTIBULTO) {
        draft.bultos_json = JSON.stringify(AndreaniBoxPreview.completeBultos(this.collectBultos()));
      }
      return draft;
    },

    serverError(xhr) {
      return (xhr && xhr.responseJSON && xhr.responseJSON.data) || {};
    },

    renderApilado(apilado) {
      const valid = this.isValidApilado(apilado);
      $('#andreani-edit-apilado-max-units').val(valid ? apilado.maxStackableUnits : '');
      $('#andreani-edit-apilado-inc-height').val(valid ? apilado.unitIncrementHeight : '');
      $('#andreani-edit-apilado-inc-width').val(valid ? apilado.unitIncrementWidth : '');
      $('#andreani-edit-apilado-inc-depth').val(valid ? apilado.unitIncrementDepth : '');
    },

    isValidApilado(a) {
      if (!a) return false;
      const maxUnits = parseInt(a.maxStackableUnits, 10) || 0;
      const incH = parseFloat(a.unitIncrementHeight) || 0;
      const incW = parseFloat(a.unitIncrementWidth) || 0;
      const incD = parseFloat(a.unitIncrementDepth) || 0;
      if (incH < 0 || incW < 0 || incD < 0) return false;
      return maxUnits >= 2 && (incH > 0 || incW > 0 || incD > 0);
    },

    collectApilado() {
      const a = {
        maxStackableUnits:   parseInt($('#andreani-edit-apilado-max-units').val(), 10) || 0,
        unitIncrementHeight: parseFloat($('#andreani-edit-apilado-inc-height').val()) || 0,
        unitIncrementWidth:  parseFloat($('#andreani-edit-apilado-inc-width').val()) || 0,
        unitIncrementDepth:  parseFloat($('#andreani-edit-apilado-inc-depth').val()) || 0,
      };
      return this.isValidApilado(a) ? a : null;
    },

    recalcStatus() {
      this.markQuoteStale();
      const mode = this.currentMode();
      const value = (id) => parseFloat($(id).val()) || 0;

      AndreaniBoxPreview.applyBadge($('#andreani-edit-bigger-status'), AndreaniBoxPreview.evaluateProduct({
        weight: value('#andreani-edit-weight'),
        length: value('#andreani-edit-length'),
        width: value('#andreani-edit-width'),
        height: value('#andreani-edit-height'),
        mode: mode,
        apilado: mode === this.MODE_APILADO ? this.apiladoForPreview() : null,
        bultos: mode === this.MODE_MULTIBULTO ? this.collectBultos() : [],
      }));

      this.preview.refresh();
    },

    apiladoForPreview() {
      const a = this.collectApilado();
      return a && {
        maxUnits: a.maxStackableUnits,
        incH: a.unitIncrementHeight,
        incW: a.unitIncrementWidth,
        incD: a.unitIncrementDepth,
      };
    },

    discardedConfig() {
      const mode = this.currentMode();
      const apilado = mode !== this.MODE_APILADO && !!this.collectApilado();
      const bultos = mode !== this.MODE_MULTIBULTO && this.collectBultos().some((b) => b.weight > 0 || b.height > 0 || b.width > 0 || b.depth > 0);
      if (apilado && bultos) return 'both';
      if (apilado) return 'apilado';
      return bultos ? 'bultos' : '';
    },

    save(confirmed) {
      const self = this;
      const $btn = $('#andreani-product-edit-save');
      const $msg = $('#andreani-edit-message');
      const i18n = (this.config.i18n || {});
      const mode = this.currentMode();
      const apilado = this.collectApilado();

      $msg.hide().text('').removeClass('andreani-products-inline-msg--success andreani-products-inline-msg--error');
      this.refreshSkuHint();

      // El apilado inválido no se descarta en silencio: sin esto el guardado
      // vuelve OK y el producto sigue cotizando una caja por unidad.
      if (mode === this.MODE_APILADO && !apilado) {
        $('#andreani-edit-apilado-invalid').show();
        $('#andreani-edit-apilado-max-units').focus();
        return;
      }

      if (mode === this.MODE_MULTIBULTO && this.partialCards().length) {
        const $partial = this.partialCards();
        $partial.find('.andreani-bulto-card__incomplete').show();
        $partial.first().find('input[type="number"]').filter(function() { return !(parseFloat($(this).val()) > 0); }).first().focus();
        $partial.first().find('.andreani-bulto-card__incomplete').get(0).scrollIntoView({ block: 'nearest' });
        return;
      }

      if (mode === this.MODE_MULTIBULTO && !this.hasCompleteBulto()) {
        $('#andreani-edit-bultos-invalid').show();
        $('#andreani-bultos-cards .andreani-bulto-card').first().find('input').first().focus();
        return;
      }

      const discarded = confirmed ? '' : this.discardedConfig();
      if (discarded) {
        const $text = $('#andreani-edit-confirm-discard-text');
        $text.text($text.attr('data-' + discarded));
        const $confirm = $('#andreani-edit-confirm-discard').prop('hidden', false);
        $confirm.get(0).scrollIntoView({ block: 'nearest' });
        return;
      }
      $('#andreani-edit-confirm-discard').prop('hidden', true);

      const $saving = $('#andreani-edit-saving');
      $btn.prop('disabled', true);
      AndreaniLoader.show($saving, { size: 'sm', text: i18n.save_dims_loading });

      $.post(this.config.ajax_url || ajaxurl, $.extend({
        action:     'andreani_save_product_dims',
        nonce:      this.config.nonce_save_dims,
        product_id: $('#andreani-edit-product-id').val(),
      }, this.draftPayload(), { sku: $('#andreani-edit-sku').val() }))
        .done((res) => {
          if (res.success) {
            const $newRow = $('<div>').html(res.data.html).find('.andreani-product-item');
            self.close($newRow);
            AndreaniBoxPreview.mountThumbs($newRow.get(0));
            AndreaniBoxPreview.mountIcons($newRow.get(0));
            AndreaniProductsGrid.updateCounts(res.data.counts);
            AndreaniProductsGrid.flash(i18n.editor_saved || 'Producto actualizado.');
          } else {
            $msg.text((res.data && res.data.message) || i18n.save_dims_error || 'Error.')
              .addClass('andreani-products-inline-msg--error').show();
          }
        })
        .fail((xhr) => {
          const data = self.serverError(xhr);
          if (data.field === 'apilado') {
            $('#andreani-edit-apilado-invalid').text(data.message).show();
            $('#andreani-edit-apilado-max-units').focus();
          } else if (data.field === 'sku') {
            $('#andreani-edit-sku-hint').addClass('andr-pem__hint--error').text(data.message);
            $('#andreani-edit-sku').focus();
          } else if (data.field === 'bultos') {
            $('#andreani-edit-bultos-invalid').text(data.message).show();
          } else {
            $msg.text(data.message || i18n.save_dims_error || 'Error de red.').addClass('andreani-products-inline-msg--error').show();
          }
        })
        .always(() => {
          $btn.prop('disabled', false);
          AndreaniLoader.hide($saving);
        });
    },

    quote() {
      const $btn = $('#andreani-edit-quote-submit');
      const $results = $('#andreani-edit-quote-results').removeClass('is-stale');

      AndreaniQuote.loading($results);
      $btn.prop('disabled', true);

      $.post(this.config.ajax_url || ajaxurl, $.extend({
        action:     'andreani_test_quote',
        nonce:      this.config.nonce_test_quote,
        product_id: $('#andreani-edit-product-id').val(),
        cp_destino: ($('#andreani-edit-quote-cp').val() || '').trim(),
        quantity:   $('#andreani-edit-quote-qty').val() || 1,
      }, this.draftPayload()))
        .done((res) => AndreaniLoader.hide($results, () => AndreaniQuote.show($results, res)))
        .fail((xhr) => AndreaniLoader.hide($results, () => AndreaniQuote.show($results, null, xhr)))
        .always(() => { $btn.prop('disabled', false); });
    },
  };

  /* ========================================
   * CART SIMULATOR (AndreaniCartSim)
   * ======================================== */
  const AndreaniCartSim = {
    config: window.andreani_admin || {},
    $modal: null,
    lines: [],
    searchTimer: null,
    searchXhr: null,
    lastQuery: null,
    simTimer: null,
    simRequest: 0,

    init() {
      this.$modal = $('#andreani-cart-sim-modal');
      if (!this.$modal.length) return;
      this.config = window.andreani_admin || {};
      AndreaniBoxPreview.configure($.extend({}, this.config.box_preview, { i18n: (this.config.i18n || {}).dispatch || {} }));
      this.bindEvents();
    },

    t(key) {
      return (this.config.i18n || {})[key] || '';
    },

    bindEvents() {
      const self = this;

      $('#andreani-cart-sim-open').on('click', () => {
        self.$modal.show();
        self.renderLines();
      });

      this.$modal.on('click', '.andreani-modal__close, .andr-modal__backdrop', () => this.$modal.hide());

      this.$modal.on('input', '#andreani-sim-search', () => self.searchSoon());

      this.$modal.on('focus', '#andreani-sim-search', () => {
        const q = self.query();
        if (q === self.lastQuery && $('#andreani-sim-results').children().length) {
          $('#andreani-sim-results').prop('hidden', false);
        } else if (q === '') {
          self.search();
        }
      });

      this.$modal.on('click', (e) => {
        if (!$(e.target).closest('.andreani-sim__search').length) $('#andreani-sim-results').prop('hidden', true);
      });

      this.$modal.on('click', '.andreani-sim__result:not([disabled])', function() {
        self.addLine($(this).data('product'));
        $('#andreani-sim-search').val('');
        $('#andreani-sim-results').prop('hidden', true).empty();
        self.lastQuery = null;
      });

      this.$modal.on('click', '.andreani-sim__remove', function() {
        const id = parseInt($(this).attr('data-id'), 10);
        self.lines = self.lines.filter((l) => l.id !== id);
        self.renderLines();
      });

      this.$modal.on('click', '.andreani-sim__step', function() {
        const id = parseInt($(this).attr('data-id'), 10);
        const delta = parseInt($(this).attr('data-d'), 10);
        self.lines = self.lines
          .map((l) => (l.id === id ? Object.assign({}, l, { qty: Math.min(99, l.qty + delta) }) : l))
          .filter((l) => l.qty > 0);
        self.renderLines();
      });

      $('#andreani-sim-quote').on('click', () => this.quote());
      this.$modal.on('keydown', '#andreani-sim-cp', (e) => {
        if (e.key === 'Enter') this.quote();
      });
    },

    thumbHtml(url, packages) {
      return url
        ? '<img class="andreani-product-item__thumb" src="' + escapeAttr(url) + '" alt="">'
        : '<span class="andreani-product-item__thumb-cell"><svg class="andreani-product-item__box" viewBox="0 0 48 48" aria-hidden="true" data-andr-thumb="' + escapeAttr(JSON.stringify(packages || [])) + '"></svg></span>';
    },

    metaHtml(p) {
      return [p.sku ? escapeHtml('SKU ' + p.sku) : '', howHtml(p.mode, p.how)].filter(Boolean).join(' · ');
    },

    query() {
      return ($('#andreani-sim-search').val() || '').trim();
    },

    searchSoon() {
      clearTimeout(this.searchTimer);
      if (this.searchXhr) this.searchXhr.abort();

      if (this.query().length === 1) {
        $('#andreani-sim-results').prop('hidden', true);
        return;
      }

      this.searchTimer = setTimeout(() => this.search(), 300);
    },

    search() {
      const self = this;
      const $results = $('#andreani-sim-results');
      const q = this.query();

      if (this.searchXhr) this.searchXhr.abort();

      AndreaniLoader.show($results.prop('hidden', false), { size: 'sm', text: this.t('loader_products_update') });

      this.searchXhr = $.post(this.config.ajax_url || ajaxurl, {
        action: 'andreani_sim_search',
        nonce:  this.config.nonce_sim_search,
        s:      q,
      }).done((res) => AndreaniLoader.hide($results, () => {
        if (!res.success) {
          $results.prop('hidden', true);
          return;
        }
        self.lastQuery = q;
        const results = res.data.results || [];
        let html = '';
        results.forEach((p, i) => {
          html += '<button type="button" class="andreani-sim__result" data-index="' + i + '"' + (p.missing ? ' disabled' : '') + '>'
            + self.thumbHtml(p.thumb, p.packages)
            + '<span class="andreani-sim__result-text"><span class="andreani-product-item__name">' + escapeHtml(p.name) + '</span>'
            + '<span class="andreani-product-item__sku">' + self.metaHtml(p) + '</span></span>'
            + (p.missing ? '<span class="andr-badge andr-badge--warning andr-badge--sm">' + escapeHtml(self.t('sim_missing')) + '</span>' : '')
            + '</button>';
        });
        if (results.length >= res.data.limit) {
          html += '<p class="andr-dispatch__message">' + escapeHtml(self.t('sim_limit').replace('%d', res.data.limit)) + '</p>';
        }
        $results.html(html || '<p class="andr-dispatch__message">' + escapeHtml(self.t('sim_no_results')) + '</p>').prop('hidden', false);
        AndreaniBoxPreview.mountThumbs($results.get(0));
        AndreaniBoxPreview.mountIcons($results.get(0));
        $results.find('.andreani-sim__result').each(function(i) { $(this).data('product', results[i]); });
      })).fail((xhr, status) => {
        if (status !== 'abort') AndreaniLoader.hide($results, () => $results.prop('hidden', true));
      });
    },

    addLine(product) {
      const found = this.lines.find((l) => l.id === product.id);
      if (found) {
        found.qty = Math.min(99, found.qty + 1);
      } else {
        this.lines.push({ id: product.id, name: product.name, thumb: product.thumb, packages: product.packages, how: product.how, mode: product.mode, qty: 1 });
      }
      this.renderLines();
    },

    renderLines() {
      let html = '';
      this.lines.forEach((l) => {
        html += '<div class="andreani-sim__line">'
          + this.thumbHtml(l.thumb, l.packages)
          + '<span class="andreani-sim__line-text"><span class="andreani-product-item__name">' + escapeHtml(l.name) + '</span>'
          + (l.how ? '<span class="andreani-product-item__sku">' + howHtml(l.mode, l.how) + '</span>' : '') + '</span>'
          + '<span class="andreani-sim__stepper">'
          + '<button type="button" class="andr-btn andr-btn--secondary andr-btn--icon andr-btn--sm andreani-sim__step" data-id="' + l.id + '" data-d="-1" aria-label="' + escapeAttr(this.t('sim_remove')) + '">&minus;</button>'
          + '<b>' + l.qty + '</b>'
          + '<button type="button" class="andr-btn andr-btn--secondary andr-btn--icon andr-btn--sm andreani-sim__step" data-id="' + l.id + '" data-d="1" aria-label="' + escapeAttr(this.t('sim_add')) + '">+</button>'
          + '</span>'
          + '<button type="button" class="andreani-icon-btn andreani-sim__remove" data-id="' + l.id + '" aria-label="' + escapeAttr(this.t('sim_remove_line')) + '" title="' + escapeAttr(this.t('sim_remove_line')) + '"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg></button>'
          + '</div>';
      });
      $('#andreani-sim-lines').html(html);
      AndreaniBoxPreview.mountThumbs($('#andreani-sim-lines').get(0));
      AndreaniBoxPreview.mountIcons($('#andreani-sim-lines').get(0));
      $('#andreani-sim-empty').toggle(!this.lines.length);
      $('#andreani-sim-rates').prop('hidden', true).empty();
      this.simulateSoon();
    },

    payloadLines() {
      return this.lines.map((l) => ({ product_id: l.id, quantity: l.qty }));
    },

    simulateSoon() {
      clearTimeout(this.simTimer);
      this.simTimer = setTimeout(() => this.simulate(), 250);
    },

    simulate() {
      const self = this;
      const paint = (packages, mixed) => AndreaniBoxPreview.paint({
        svg: document.getElementById('andreani-sim-stage'),
        result: document.getElementById('andreani-sim-result'),
        packages: packages,
        cart: true,
        list: true,
        mixed: mixed,
        emptyText: self.t('sim_empty_preview'),
      });
      const id = ++this.simRequest;
      const $skipped = $('#andreani-sim-skipped');

      if (!this.lines.length) {
        $skipped.prop('hidden', true);
        paint([], false);
        return;
      }

      $.post(this.config.ajax_url || ajaxurl, {
        action: 'andreani_simulate_cart',
        nonce:  this.config.nonce_simulate_cart,
        lines:  this.payloadLines(),
      }).done((res) => {
        if (id !== self.simRequest || !res.success) return;
        const packages = res.data.packages || [];
        const names = self.lines.filter((l) => (res.data.skipped || []).indexOf(l.id) !== -1).map((l) => l.name);
        paint(packages, new Set(packages.map((p) => p.product_id)).size > 1);
        $skipped.text(names.length ? self.t('sim_skipped') + ' ' + names.join(', ') : '').prop('hidden', !names.length);
      }).fail(() => {
        if (id !== self.simRequest) return;
        paint([], false);
        $skipped.text(self.t('network_error')).prop('hidden', false);
      });
    },

    quote() {
      const $btn = $('#andreani-sim-quote');
      const $results = $('#andreani-sim-rates');

      if (!this.lines.length) {
        AndreaniQuote.error($results, this.t('sim_empty_preview'));
        return;
      }

      AndreaniQuote.loading($results);
      $btn.prop('disabled', true);

      $.post(this.config.ajax_url || ajaxurl, {
        action:     'andreani_test_quote',
        nonce:      this.config.nonce_test_quote,
        cp_destino: ($('#andreani-sim-cp').val() || '').trim(),
        lines:      this.payloadLines(),
      })
        .done((res) => AndreaniLoader.hide($results, () => AndreaniQuote.show($results, res)))
        .fail((xhr) => AndreaniLoader.hide($results, () => AndreaniQuote.show($results, null, xhr)))
        .always(() => { $btn.prop('disabled', false); });
    },
  };

  /* ========================================
   * INITIALIZATION
   * ======================================== */
  $(function() {
    AndreaniAdmin.init();
    AndreaniShipments.init();
    AndreaniPrintSettings.init();
    AndreaniTableLoader.init();
    AndreaniInfoBox.init();
    AndreaniTabs.init();
    AndreaniGrid.init();
    AndreaniFilters.init();
    AndreaniBulkBar.init();
    AndreaniRowExpander.init();
    AndreaniOrderPacking.init();
    AndreaniProductsGrid.init();
    AndreaniProductEdit.init();
    AndreaniCartSim.init();
  });

  window.AndreaniAdmin = AndreaniAdmin;
  window.AndreaniShipments = AndreaniShipments;
  window.AndreaniPrintSettings = AndreaniPrintSettings;
  window.AndreaniTableLoader = AndreaniTableLoader;
  window.AndreaniInfoBox = AndreaniInfoBox;
  window.AndreaniTabs = AndreaniTabs;
  window.AndreaniGrid = AndreaniGrid;
  window.AndreaniFilters = AndreaniFilters;
  window.AndreaniBulkBar = AndreaniBulkBar;
  window.AndreaniRowExpander = AndreaniRowExpander;
  window.AndreaniProductsGrid = AndreaniProductsGrid;
  window.AndreaniProductEdit = AndreaniProductEdit;
  const AndreaniQuoteCp = {
    key: 'andreani_quote_cp',
    selector: '#andreani-edit-quote-cp, #andreani-sim-cp',

    get() {
      try {
        return window.sessionStorage.getItem(this.key) || '';
      } catch (e) {
        return '';
      }
    },

    set(value) {
      try {
        if (value) {
          window.sessionStorage.setItem(this.key, value);
        } else {
          window.sessionStorage.removeItem(this.key);
        }
      } catch (e) {
        return;
      }
    },

    init() {
      const saved = this.get();
      if (saved) {
        $(this.selector).each(function () {
          if (!this.value) this.value = saved;
        });
      }
      $(document).on('input change', this.selector, (e) => {
        const value = (e.currentTarget.value || '').trim();
        this.set(value);
        $(this.selector).not(e.currentTarget).val(value);
      });
    },
  };

  window.AndreaniCartSim = AndreaniCartSim;
  window.AndreaniQuoteCp = AndreaniQuoteCp;

  $(function () {
    AndreaniQuoteCp.init();
  });
})(jQuery);
