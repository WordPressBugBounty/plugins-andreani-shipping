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
      $lista.html('<p class="andreani-origen-estado andreani-origen-estado--' + estado + '">' + escapeHtml(mensaje) + '</p>');
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
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).addClass('andreani-refresh-contratos--loading')
          .html('<svg class="andreani-spinner" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg> Actualizando...');

        $.post(ajaxurl, { action: 'andreani_refresh_contratos', nonce: $btn.data('nonce') })
          .done((res) => {
            if (res.success) {
              this.showNotice(res.data.message, 'success');
              setTimeout(() => location.reload(), 1000);
            } else {
              this.showNotice(res.data?.message || 'Error al actualizar contratos.', 'error');
              $btn.prop('disabled', false).removeClass('andreani-refresh-contratos--loading').html(originalHtml);
            }
          })
          .fail(() => {
            this.showNotice('Error de conexión.', 'error');
            $btn.prop('disabled', false).removeClass('andreani-refresh-contratos--loading').html(originalHtml);
          });
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

      // Pagination links (delegated para links cargados dinámicamente)
      $(document).on('click', '#andreani-table-container .tablenav-pages a', (e) => {
        e.preventDefault();
        if (self.isLoading) return;

        const href = $(e.currentTarget).attr('href');
        const params = new URLSearchParams(href.split('?')[1] || '');
        const paged = params.get('paged') || 1;

        self.loadTable({ paged: paged });
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
        .done((res) => {
          if (res.success && res.data.html) {
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
        })
        .fail(() => {
          self.showError(self.t('table_error'));
        })
        .always(() => {
          self.isLoading = false;
          self.$refreshBtn.removeClass('andreani-refresh-btn--loading');
        });
    },

    showLoader() {
      this.$refreshBtn.addClass('andreani-refresh-btn--loading');

      const logoPath = this.config.logo_path || 'M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z';
      const loaderHtml = `
        <div class="andreani-table-loader">
          <div class="andreani-table-loader__spinner">
            <svg class="andreani-table-loader__logo andreani-table-loader__logo--bg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341">
              <g transform="translate(0,341) scale(0.1,-0.1)" fill="#e0e0e0"><path d="${logoPath}"/></g>
            </svg>
            <svg class="andreani-table-loader__logo andreani-table-loader__logo--fill" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341">
              <g transform="translate(0,341) scale(0.1,-0.1)" fill="#e31e24"><path d="${logoPath}"/></g>
            </svg>
          </div>
        </div>
      `;

      this.$container.html(loaderHtml);
    },

    updateUrl() {
      const params = new URLSearchParams();
      params.set('page', 'andreani-shipping');

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
        const originalHtml = $btn.html();

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update-alt andreani-spin"></span>');

        $.post(self.config.ajax_url || ajaxurl, {
          action: 'andreani_export_shipments',
          nonce: self.config.nonce_export,
          andreani_status: params.get('andreani_status') || '',
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
        .always(() => $btn.prop('disabled', false).html(originalHtml));
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
        console.log('[Andreani] submit aborted: loading=true');
        return;
      }

      const orderId = $form.data('order-id');
      const url = $form.data('ajax-url') || this.config.ajax_url || ajaxurl;
      const nonce = $form.data('nonce');
      console.log('[Andreani] submit start', { orderId, url, hasNonce: !!nonce });
      if (!orderId || !nonce) {
        console.log('[Andreani] submit aborted: missing orderId or nonce');
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
      console.log('[Andreani] validation result', { fields, errors });
      if (Object.keys(errors).length > 0) {
        this.showRecipientErrors($form, errors);
        return;
      }

      const $submit = $form.find('.andreani-recipient-form__submit');
      const $feedback = $form.find('.andreani-recipient-form__feedback');
      const originalText = $submit.html();
      $form.data('loading', true);
      $submit.prop('disabled', true).html('<span class="dashicons dashicons-update-alt andreani-spin"></span> ' + this.t('retry_loading'));
      $feedback.removeClass('andreani-recipient-form__feedback--success andreani-recipient-form__feedback--error').text('');

      const rollback = self.snapshotRow(orderId);
      self.applyOptimistic(orderId, {
        status: { class: 'pending', label: self.t('retry_loading') }
      });

      console.log('[Andreani] sending AJAX request to', url);
      $.post(url, {
        action: 'andreani_update_recipient_and_retry',
        nonce: nonce,
        order_id: orderId,
        phone: fields.phone,
        dni: fields.dni
      })
        .done((res) => {
          console.log('[Andreani] AJAX response', res);
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
          $submit.prop('disabled', false).html(originalText);
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

      const originalHtml = $btn.html();
      $btn.data('loading', true).prop('disabled', true)
        .html('<span class="dashicons dashicons-update-alt andreani-spin"></span>')
        .attr('title', opts.loading);

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
          $btn.data('loading', false).prop('disabled', false).html(originalHtml);
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
            const safe = $('<div>').text(changes.tracking).html();
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
        const text = (dateFrom || '…') + ' → ' + (dateTo || '…');
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
          variant: 'primary',
          predicate: (sel) => sel.length > 0 && sel.some(r => r.paymentPending),
          handler: () => window.open(self.config.pyme_historial_url, '_blank', 'noopener')
        },
        {
          // Mejora sobre la competencia: se habilita con cualquier selección y el
          // handler reporta los parciales (incluidas vs. omitidas sin tracking) en
          // vez de descartar en silencio las que aún no tienen seguimiento.
          key: 'download-labels',
          label: self.t('bulk_labels_label'),
          variant: 'ghost',
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
      const originalText = $btn.text();
      this.busy = true;
      $btn.prop('disabled', true).text(this.t('bulk_labels_loading'));

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
          $btn.prop('disabled', false).text(originalText);
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
      const $actions = $('#andreani-bulk-bar-actions');

      if (selection.length === 0) {
        $bar.prop('hidden', true);
        $actions.empty();
        return;
      }

      $count.text(selection.length);
      this.renderActions(selection);
      $bar.prop('hidden', false);
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

    showLoader(isLoading) {
      $('#andreani-print-settings-modal [data-print-loader]').toggle(isLoading);
      $('#andreani-print-settings-modal [data-print-options]').prop('hidden', isLoading);
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
            self.showLoader(false);
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
      const originalText = $btn.text();
      $btn.prop('disabled', true).text(this.t('print_save_loading'));

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
          $btn.prop('disabled', false).text(originalText);
        });
    },

    t(key) {
      return this.config.i18n?.[key] || key;
    }
  };

  /* ========================================
   * TRACKING SYNC TOGGLE (AndreaniTrackingSync)
   * ======================================== */
  const AndreaniTrackingSync = {
    config: window.andreani_admin || {},

    init() {
      const $input = $('#andreani-tracking-sync-toggle');
      if (!$input.length) return;
      const self = this;
      $input.on('change', function() {
        self.toggle($(this));
      });
    },

    toggle($input) {
      const self = this;
      const next = $input.is(':checked');
      $input.prop('disabled', true);

      $.post(this.config.ajax_url || ajaxurl, {
        action: 'andreani_toggle_tracking_sync',
        nonce: this.config.nonce_toggle_sync,
        enabled: next ? '1' : '0'
      })
        .done((res) => {
          const $wrap = $('.andreani-shipments-wrap').first();
          if (res && res.success) {
            const on = !!res.data.enabled;
            $input.prop('checked', on).attr('aria-checked', on ? 'true' : 'false');
            AndreaniShipments.showNotice($wrap, res.data.message || (on ? self.t('sync_on') : self.t('sync_off')), 'success');
          } else {
            $input.prop('checked', !next).attr('aria-checked', (!next) ? 'true' : 'false');
            AndreaniShipments.showNotice($wrap, (res && res.data && res.data.message) || self.t('sync_toggle_error'), 'error');
          }
        })
        .fail(() => {
          $input.prop('checked', !next).attr('aria-checked', (!next) ? 'true' : 'false');
          AndreaniShipments.showNotice($('.andreani-shipments-wrap').first(), self.t('network_error'), 'error');
        })
        .always(() => {
          $input.prop('disabled', false);
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
    $container: null,
    isLoading: false,
    currentParams: { paged: 1, per_page: 10, s: '', missing_dims: 0 },

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

      $('#andreani-products-missing-filter').on('click', function() {
        const $btn = $(this);
        const isActive = $btn.attr('aria-pressed') === 'true';
        $btn.attr('aria-pressed', isActive ? 'false' : 'true').toggleClass('is-active', !isActive);
        self.currentParams.missing_dims = isActive ? 0 : 1;
        self.loadTable({ paged: 1 });
      });

      $(document).on('click', '.andreani-per-page__btn', function() {
        if (!$('.andreani-products-wrap').length) return;
        const val = parseInt($(this).data('per-page'), 10);
        $('.andreani-per-page__btn').removeClass('is-active').attr('aria-pressed', 'false');
        $(this).addClass('is-active').attr('aria-pressed', 'true');
        self.currentParams.per_page = val;
        self.loadTable({ paged: 1 });
      });

      $(document).on('click', '.andreani-products-page-btn', function() {
        const p = parseInt($(this).data('paged'), 10);
        if (p > 0) self.loadTable({ paged: p });
      });
    },

    loadTable(extra) {
      const self = this;
      if (self.isLoading) return;
      self.isLoading = true;

      const s = $('#andreani-products-search').val() || '';
      const $activePerPage = $('.andreani-per-page__btn.is-active');
      const perPage = $activePerPage.length
        ? parseInt($activePerPage.data('per-page'), 10)
        : (self.currentParams.per_page || 10);

      self.currentParams = Object.assign({}, self.currentParams, { s, per_page: perPage }, extra || {});

      self.showLoader();

      const params = Object.assign({}, self.currentParams, {
        action: 'andreani_products_table',
        nonce:  self.config.nonce_products_table,
      });

      $.post(self.config.ajax_url || ajaxurl, params)
        .done((res) => {
          if (res.success) {
            self.$container.html(res.data.html);
          } else {
            self.$container.html('<p class="andreani-products-empty">' + escapeHtml((self.config.i18n || {}).products_error || 'Error al cargar.') + '</p>');
          }
        })
        .fail(() => {
          self.$container.html('<p class="andreani-products-empty">' + escapeHtml((self.config.i18n || {}).products_error || 'Error al cargar.') + '</p>');
        })
        .always(() => { self.isLoading = false; });
    },

    showLoader() {
      const logoPath = (this.config || {}).logo_path || '';
      this.$container.html(
        '<div class="andreani-table-loader"><div class="andreani-table-loader__spinner">'
        + '<svg class="andreani-table-loader__logo andreani-table-loader__logo--fill" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341" style="color:var(--andr-color-brand);"><g transform="translate(0,341) scale(0.1,-0.1)" fill="currentColor"><path d="' + logoPath + '"/></g></svg>'
        + '</div></div>'
      );
    },
  };

  /* ========================================
   * PRODUCT EDIT MODAL (AndreaniProductEdit)
   * ======================================== */
  const AndreaniProductEdit = {
    config: window.andreani_admin || {},
    $modal: null,
    thresholds: { weight: 50, sum_sides: 300, max_side: 165 },

    init() {
      this.$modal = $('#andreani-product-edit-modal');
      if (!this.$modal.length) return;
      this.config = window.andreani_admin || {};
      this.thresholds = this.config.bigger_thresholds || this.thresholds;
      this.bindEvents();
    },

    bindEvents() {
      const self = this;

      $(document).on('click', '.andreani-product-edit-btn', function() {
        const $btn = $(this);
        $('#andreani-edit-product-id').val($btn.data('product-id'));
        $('#andreani-edit-product-name').text($btn.data('product-name'));
        const thumb = $btn.attr('data-thumb-url') || '';
        const $thumb = $('#andreani-edit-product-thumb');
        if (thumb) { $thumb.attr('src', thumb).show(); } else { $thumb.removeAttr('src').hide(); }
        $('#andreani-edit-weight').val($btn.data('weight') || '');
        $('#andreani-edit-length').val($btn.data('length') || '');
        $('#andreani-edit-width').val($btn.data('width') || '');
        $('#andreani-edit-height').val($btn.data('height') || '');
        $('#andreani-edit-message').hide().text('').removeClass('andreani-products-inline-msg--success andreani-products-inline-msg--error');

        let bultos = $btn.attr('data-bultos-json');
        try { bultos = bultos ? JSON.parse(bultos) : []; } catch (e) { bultos = []; }
        if (!Array.isArray(bultos)) bultos = [];
        self.renderCards(bultos);

        self.$modal.show();
      });

      this.$modal.on('click', '.andreani-modal__close, .andr-modal__backdrop', () => this.$modal.hide());

      // Stepper: subir/bajar la cantidad de bultos con un click.
      $('#andreani-bultos-plus').on('click', () => { self.addCard(); self.afterChange(); });
      $('#andreani-bultos-minus').on('click', () => {
        self.$modal.find('.andreani-bulto-card').last().remove();
        self.afterChange();
      });
      this.$modal.on('click', '.andreani-bulto-card__remove', function() {
        $(this).closest('.andreani-bulto-card').remove();
        self.afterChange();
      });

      // Recalcular categoría (Bigger / Paquete común) al tipear en principal o bultos.
      this.$modal.on('input',
        '#andreani-edit-weight, #andreani-edit-length, #andreani-edit-width, #andreani-edit-height, .andreani-bulto-card input',
        () => self.recalcBadge());

      $('#andreani-product-edit-save').on('click', () => this.save());
    },

    cardHtml(index, b) {
      b = b || {};
      const v = (x) => (x === undefined || x === null) ? '' : x;
      return ''
        + '<div class="andreani-bulto-card">'
        +   '<div class="andreani-bulto-card__head">'
        +     '<span class="andreani-bulto-card__title">Bulto ' + (index + 1) + '</span>'
        +     '<button type="button" class="andreani-bulto-card__remove" aria-label="Eliminar bulto">&times;</button>'
        +   '</div>'
        +   '<div class="andreani-bulto-card__grid">'
        +     '<label class="andreani-bulto-card__field"><span>Peso (kg)</span><input type="number" class="b-weight" min="0" step="0.001" value="' + v(b.weight) + '"></label>'
        +     '<label class="andreani-bulto-card__field"><span>Largo (cm)</span><input type="number" class="b-length" min="0" step="0.01" value="' + v(b.length) + '"></label>'
        +     '<label class="andreani-bulto-card__field"><span>Ancho (cm)</span><input type="number" class="b-width" min="0" step="0.01" value="' + v(b.width) + '"></label>'
        +     '<label class="andreani-bulto-card__field"><span>Alto (cm)</span><input type="number" class="b-height" min="0" step="0.01" value="' + v(b.height) + '"></label>'
        +   '</div>'
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
      $('#andreani-bultos-cards .andreani-bulto-card').each(function(i) {
        $(this).find('.andreani-bulto-card__title').text('Bulto ' + (i + 1));
      });
      const n = $('#andreani-bultos-cards .andreani-bulto-card').length;
      $('#andreani-bultos-count').text(n);
      $('#andreani-bultos-minus').prop('disabled', n === 0);
      this.recalcBadge();
    },

    collectBultos() {
      const bultos = [];
      $('#andreani-bultos-cards .andreani-bulto-card').each(function() {
        const $c = $(this);
        bultos.push({
          length: parseFloat($c.find('.b-length').val()) || 0,
          width:  parseFloat($c.find('.b-width').val())  || 0,
          height: parseFloat($c.find('.b-height').val()) || 0,
          weight: parseFloat($c.find('.b-weight').val()) || 0,
        });
      });
      return bultos;
    },

    recalcBadge() {
      const t = this.thresholds || { weight: 50, sum_sides: 300, max_side: 165 };
      let totalWeight = parseFloat($('#andreani-edit-weight').val()) || 0;
      let maxSumSides = (parseFloat($('#andreani-edit-length').val()) || 0)
                      + (parseFloat($('#andreani-edit-width').val())  || 0)
                      + (parseFloat($('#andreani-edit-height').val()) || 0);
      let maxSide = Math.max(
        parseFloat($('#andreani-edit-length').val()) || 0,
        parseFloat($('#andreani-edit-width').val())  || 0,
        parseFloat($('#andreani-edit-height').val()) || 0
      );
      this.collectBultos().forEach((b) => {
        totalWeight += b.weight;
        const sum = b.length + b.width + b.height;
        if (sum > maxSumSides) maxSumSides = sum;
        const ms = Math.max(b.length, b.width, b.height);
        if (ms > maxSide) maxSide = ms;
      });
      const isBigger = totalWeight > t.weight || maxSumSides > t.sum_sides || maxSide > t.max_side;
      $('#andreani-edit-bigger-badge')
        .text(isBigger ? 'Bigger' : 'Paquete común')
        .toggleClass('andr-badge--info', isBigger)
        .toggleClass('andr-badge--neutral', !isBigger);
    },

    save() {
      const self = this;
      const $btn = $('#andreani-product-edit-save');
      const $msg = $('#andreani-edit-message');
      const i18n = (this.config.i18n || {});

      $btn.prop('disabled', true).text(i18n.save_dims_loading || 'Guardando...');
      $msg.hide().text('').removeClass('andreani-products-inline-msg--success andreani-products-inline-msg--error');

      $.post(this.config.ajax_url || ajaxurl, {
        action:      'andreani_save_product_dims',
        nonce:       this.config.nonce_save_dims,
        product_id:  $('#andreani-edit-product-id').val(),
        weight:      $('#andreani-edit-weight').val(),
        length:      $('#andreani-edit-length').val(),
        width:       $('#andreani-edit-width').val(),
        height:      $('#andreani-edit-height').val(),
        bultos_json: JSON.stringify(this.collectBultos()),
      })
        .done((res) => {
          if (res.success) {
            $msg.text(i18n.save_dims_success || 'Guardado.').addClass('andreani-products-inline-msg--success').show();
            setTimeout(() => {
              self.$modal.hide();
              if (window.AndreaniProductsGrid) AndreaniProductsGrid.loadTable();
            }, 800);
          } else {
            $msg.text((res.data && res.data.message) || i18n.save_dims_error || 'Error.')
              .addClass('andreani-products-inline-msg--error').show();
          }
        })
        .fail(() => {
          $msg.text(i18n.save_dims_error || 'Error de red.').addClass('andreani-products-inline-msg--error').show();
        })
        .always(() => { $btn.prop('disabled', false).text('Guardar'); });
    },
  };

  /* ========================================
   * QUOTE TESTER (AndreaniQuoteTester)
   * ======================================== */
  const AndreaniQuoteTester = {
    config: window.andreani_admin || {},
    $modal: null,

    init() {
      this.$modal = $('#andreani-product-quote-modal');
      if (!this.$modal.length) return;
      this.config = window.andreani_admin || {};
      this.bindEvents();
    },

    bindEvents() {
      const self = this;

      $(document).on('click', '.andreani-product-quote-btn', function() {
        const $btn = $(this);
        $('#andreani-quote-product-id').val($btn.data('product-id'));
        $('#andreani-quote-product-name').text($btn.data('product-name'));
        const thumb = $btn.attr('data-thumb-url') || '';
        const $thumb = $('#andreani-quote-product-thumb');
        if (thumb) { $thumb.attr('src', thumb).show(); } else { $thumb.removeAttr('src').hide(); }
        $('#andreani-quote-cp').val('');
        $('#andreani-quote-results').hide().empty();
        $('#andreani-quote-message').hide().text('').removeClass('andreani-products-inline-msg--success andreani-products-inline-msg--error');
        $('#andreani-quote-empty').show();
        $('#andreani-quote-loader').prop('hidden', true);
        self.$modal.show();
        $('#andreani-quote-cp').focus();
      });

      this.$modal.on('click', '.andreani-modal__close, .andr-modal__backdrop', () => this.$modal.hide());

      $('#andreani-quote-submit').on('click', () => this.quote());

      this.$modal.on('keydown', '#andreani-quote-cp', (e) => {
        if (e.key === 'Enter') this.quote();
      });
    },

    quote() {
      const self = this;
      const $btn = $('#andreani-quote-submit');
      const $results = $('#andreani-quote-results');
      const $msg = $('#andreani-quote-message');
      const $loader = $('#andreani-quote-loader');
      const i18n = (this.config.i18n || {});
      const MIN_LOADER_MS = 700;

      $msg.hide().text('').removeClass('andreani-products-inline-msg--success andreani-products-inline-msg--error');
      $results.hide().empty();
      $('#andreani-quote-empty').hide();
      $loader.prop('hidden', false);
      $btn.prop('disabled', true).text(i18n.quote_loading || 'Cotizando...');
      const started = Date.now();

      // Dejamos que la animación de la cajita complete al menos un ciclo aunque
      // la respuesta llegue antes, para que no parpadee.
      const finish = (cb) => {
        const wait = Math.max(0, MIN_LOADER_MS - (Date.now() - started));
        setTimeout(() => { $loader.prop('hidden', true); cb(); }, wait);
      };

      $.post(this.config.ajax_url || ajaxurl, {
        action:     'andreani_test_quote',
        nonce:      this.config.nonce_test_quote,
        product_id: $('#andreani-quote-product-id').val(),
        cp_destino: $('#andreani-quote-cp').val().trim(),
      })
        .done((res) => {
          finish(() => {
            if (res.success && res.data.rates && res.data.rates.length) {
              let html = '';
              res.data.rates.forEach((r) => {
                html += '<div class="andreani-quote-rate">'
                  + '<span class="andreani-quote-rate__name">' + escapeHtml(String(r.label)) + '</span>'
                  + '<span class="andreani-quote-rate__price">$' + escapeHtml(parseFloat(r.cost).toFixed(2)) + '</span>'
                  + '</div>';
              });
              $results.html(html).show();
            } else {
              const msg = (res.data && res.data.message) || i18n.quote_error || 'Error al cotizar.';
              $msg.text(msg).addClass('andreani-products-inline-msg--error').show();
            }
          });
        })
        .fail(() => {
          finish(() => {
            $msg.text(i18n.quote_error || 'Error de red.').addClass('andreani-products-inline-msg--error').show();
          });
        })
        .always(() => { $btn.prop('disabled', false).text('Cotizar'); });
    },
  };

  /* ========================================
   * INITIALIZATION
   * ======================================== */
  $(function() {
    AndreaniAdmin.init();
    AndreaniShipments.init();
    AndreaniPrintSettings.init();
    AndreaniTrackingSync.init();
    AndreaniTableLoader.init();
    AndreaniInfoBox.init();
    AndreaniTabs.init();
    AndreaniGrid.init();
    AndreaniFilters.init();
    AndreaniBulkBar.init();
    AndreaniRowExpander.init();
    AndreaniProductsGrid.init();
    AndreaniProductEdit.init();
    AndreaniQuoteTester.init();
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
  window.AndreaniQuoteTester = AndreaniQuoteTester;
})(jQuery);
