(function ($) {
  'use strict';

  const cfg = window.AndreaniPromotionsPage;

  if (!cfg) return;

  const t = cfg.i18n || {};
  const ICONS = {
    edit: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
    pause: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/></svg>',
    resume: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="6 4 20 12 6 20 6 4"/></svg>',
    remove: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
  };
  const BADGES = { activa: 'andr-badge--success', programada: 'andr-badge--info', finalizada: 'andr-badge--neutral', pausada: 'andr-badge--warning' };
  const EXAMPLE_PRICE = 10000;
  const MAP_MIN_WIDTH = 240;

  const Page = {
    init() {
      this.$root = $('[data-pr-root]');
      if (!this.$root.length) return;

      this.$modal = this.$root.find('[data-pr-modal]').appendTo(document.body);
      this.$ui = this.$root.add(this.$modal);
      this.promotions = cfg.promotions || [];
      this.editing = null;
      this.confirming = '';
      this.busy = false;
      this.type = 'descuento';
      this.kind = 'porcentaje';
      this.sel = {};
      this.hover = '';
      this.stroke = null;
      this.map = null;
      this.geoPromise = null;

      this.bind();
      this.render();
    },

    esc(value) {
      return String(value == null ? '' : value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    },

    number(value) {
      const v = Math.round((parseFloat(value) || 0) * 100) / 100;

      return v.toLocaleString('es-AR', { minimumFractionDigits: Number.isInteger(v) ? 0 : 2, maximumFractionDigits: 2 });
    },

    parseAmount(raw) {
      return parseFloat(String(raw == null ? '' : raw).replace(/\./g, '').replace(',', '.')) || 0;
    },

    groupAmount(raw) {
      const clean = String(raw == null ? '' : raw).replace(/[^\d,]/g, '');
      const comma = clean.indexOf(',');
      const int = (comma === -1 ? clean : clean.slice(0, comma)).replace(/^0+(?=\d)/, '');
      const dec = comma === -1 ? '' : ',' + clean.slice(comma + 1).replace(/,/g, '').slice(0, 2);

      return int.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + dec;
    },

    fill(template, value) {
      return String(template).replace('%s', value);
    },

    showError(text) {
      const $target = this.modalOpen() ? this.$modal.find('[data-pr-form-error]') : this.$root.find('[data-pr-error]');

      $target.text(text || '').prop('hidden', !text);
    },

    modalOpen() {
      return !this.$modal.prop('hidden');
    },

    bind() {
      const self = this;

      this.$ui.on('click', '[data-pr-add]', function () {
        if (self.promotions.length >= cfg.max) {
          self.showError(self.fill(t.max, cfg.max));
          return;
        }

        self.openModal(null);
      });

      this.$ui.on('click', '[data-pr-edit]', function () {
        self.openModal(self.find($(this).attr('data-pr-edit')));
      });

      this.$ui.on('click', '[data-pr-close]', function () {
        self.closeModal();
      });

      $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && self.$root.length && self.modalOpen()) {
          self.closeModal();
        }
      });

      this.$ui.on('click', '[data-pr-save]', function () {
        self.save();
      });

      this.$ui.on('click', '[data-pr-toggle]', function () {
        const promo = self.find($(this).attr('data-pr-toggle'));

        self.showError('');
        self.send(cfg.toggle_action, { id: promo.id, active: promo.activa ? '0' : '1' });
      });

      this.$ui.on('click', '[data-pr-remove]', function () {
        self.showError('');
        self.confirming = $(this).attr('data-pr-remove');
        self.render();
        self.$root.find('[data-pr-remove-yes]').trigger('focus');
      });

      this.$ui.on('click', '[data-pr-cancel]', function () {
        self.confirming = '';
        self.render();
      });

      this.$ui.on('click', '[data-pr-remove-yes]', function () {
        self.send(cfg.delete_action, { id: self.confirming });
      });

      this.$ui.on('click', '[data-pr-type]', function () {
        self.type = $(this).attr('data-pr-type');
        self.syncForm();
      });

      this.$ui.on('click', '[data-pr-form-kind]', function () {
        const kind = $(this).attr('data-pr-form-kind');
        const changed = kind !== self.kind;

        self.kind = kind;
        if (changed) self.$modal.find('[data-pr-value]').val('');
        self.syncForm();
        if (changed) self.$modal.find('[data-pr-value]').trigger('focus');
      });

      this.$ui.on('input', '[data-pr-value]', function () {
        const el = this;
        const caret = el.selectionStart;
        const digitsBefore = el.value.slice(0, caret).replace(/[^\d,]/g, '').length;
        const grouped = self.groupAmount(el.value);

        if (grouped !== el.value) {
          el.value = grouped;
          let pos = 0;
          let seen = 0;
          while (pos < grouped.length && seen < digitsBefore) {
            if (/[\d,]/.test(grouped.charAt(pos))) seen += 1;
            pos += 1;
          }
          el.setSelectionRange(pos, pos);
        }
        self.syncExample();
      });

      this.$ui.on('click', '[data-pr-pick]', function () {
        self.pick($(this).attr('data-pr-pick'));
      });

      this.$ui.on('click', '[data-pr-region]', function () {
        self.toggleRegion($(this).attr('data-pr-region'));
      });
    },

    find(id) {
      return this.promotions.filter((p) => p.id === id)[0] || null;
    },

    openModal(promo) {
      this.editing = promo;
      this.type = promo ? promo.tipo : 'descuento';
      this.kind = promo ? promo.forma : 'porcentaje';

      const $m = this.$modal;
      const services = promo ? promo.servicios : $m.find('[data-pr-service]').map(function () { return $(this).val(); }).get();
      const provinces = promo && promo.provincias.length ? promo.provincias : Object.keys(this.provinceNames());

      $m.find('[data-pr-modal-title]').text(promo ? t.modal_edit : t.modal_new);
      $m.find('[data-pr-name]').val(promo ? promo.nombre : '');
      $m.find('[data-pr-value]').val(promo ? this.groupAmount(String(promo.valor).replace('.', ',')) : '');
      $m.find('[data-pr-from]').val(promo && promo.desde ? promo.desde : '');
      $m.find('[data-pr-until]').val(promo && promo.hasta ? promo.hasta : '');
      $m.find('[data-pr-service]').each(function () {
        $(this).prop('checked', services.indexOf($(this).val()) !== -1);
      });

      this.sel = {};
      provinces.forEach((c) => { this.sel[c] = true; });
      this.showError('');
      this.syncForm();
      this.syncSummary();
      $m.prop('hidden', false);
      $('body').addClass('andr-modal-open');
      $m.find('[data-pr-name]').trigger('focus');
      this.openMap();
    },

    provinceNames() {
      return cfg.provinces || {};
    },

    label(code) {
      return code === 'C' ? t.caba : (this.provinceNames()[code] || code);
    },

    regionByName(name) {
      return (cfg.regions || []).filter((r) => r.name === name)[0] || null;
    },

    isFull(region) {
      return region.codes.every((c) => this.sel[c]);
    },

    selected() {
      return Object.keys(this.provinceNames()).filter((c) => this.sel[c]);
    },

    setSelected(codes) {
      this.sel = {};
      codes.forEach((c) => { this.sel[c] = true; });
      this.paintAll();
      this.syncSummary();
    },

    pick(what) {
      this.setSelected(what === 'all' ? Object.keys(this.provinceNames()) : []);
    },

    toggleRegion(name) {
      const region = this.regionByName(name);

      if (!region) return;

      const current = this.selected();

      this.setSelected(this.isFull(region)
        ? current.filter((c) => region.codes.indexOf(c) === -1)
        : current.concat(region.codes.filter((c) => !this.sel[c])));
    },

    syncSummary() {
      const total = parseInt(cfg.province_count, 10) || 24;
      const chosen = this.selected();
      const regions = cfg.regions || [];
      const $pill = this.$modal.find('[data-pr-summary]');
      const $detail = this.$modal.find('[data-pr-detail]');
      let pill = t.none;
      let detail = t.provinces_help;

      if (chosen.length === total) {
        pill = t.all_country;
        detail = t.all_detail;
      } else if (chosen.length) {
        const full = regions.filter((r) => this.isFull(r));
        const covered = full.reduce((acc, r) => acc.concat(r.codes), []);
        const loose = chosen.filter((c) => covered.indexOf(c) === -1).map((c) => this.label(c));

        pill = chosen.length === 1 ? t.province_one : this.fill(t.province_many, chosen.length);
        detail = full.map((r) => r.name).concat(loose).join(', ');
      }

      $pill.text(pill).toggleClass('is-empty', !chosen.length);
      $detail.text(detail).toggleClass('is-empty', !chosen.length);

      this.$modal.find('[data-pr-pick="all"]').each(function () {
        $(this).toggleClass('is-on', chosen.length === total).attr('aria-pressed', chosen.length === total ? 'true' : 'false');
      });
      this.$modal.find('[data-pr-region]').each(function () {
        const region = Page.regionByName($(this).attr('data-pr-region'));
        const on = !!region && Page.isFull(region);

        $(this).toggleClass('is-on', on).attr('aria-pressed', on ? 'true' : 'false');
      });
    },

    loadGeo() {
      if (!this.geoPromise) {
        this.geoPromise = fetch(cfg.geojson_url).then((r) => {
          if (!r.ok) throw new Error('geojson');

          return r.json();
        });
        this.geoPromise.catch(() => { this.geoPromise = null; });
      }

      return this.geoPromise;
    },

    openMap() {
      if (!window.L || !cfg.geojson_url) return;

      this.loadGeo().then((geo) => {
        if (!this.modalOpen()) return;

        if (!this.map) this.buildMap(geo);

        this.fit();
        this.paintAll();
      }).catch(() => {});
    },

    fit() {
      const el = this.map.getContainer();

      el.style.width = Math.max(MAP_MIN_WIDTH, Math.round(el.clientHeight * this.ratio)) + 'px';
      this.map.invalidateSize();
      this.map.fitBounds(this.bounds, { paddingTopLeft: [0, 0], paddingBottomRight: [0, 0] });
    },

    cssVar(name) {
      return getComputedStyle(this.$modal[0]).getPropertyValue(name).trim();
    },

    style(code) {
      const on = !!this.sel[code];
      const c = this.colors;

      return {
        fillColor: on ? c.on : c.off,
        fillOpacity: on ? 0.6 : 1,
        color: code === 'C' && this.caba && !on ? c.hover : c.line,
        weight: 1,
        opacity: 1,
      };
    },

    paintOne(code) {
      if (this.layers[code]) this.layers[code].setStyle(this.style(code));
      if (code === 'C') this.caba.setStyle(this.style('C'));
    },

    paintAll() {
      if (!this.map) return;

      Object.keys(this.provinceNames()).forEach((code) => this.paintOne(code));
    },

    buildMap(geo) {
      const self = this;
      const $slot = this.$modal.find('[data-pr-map-slot]');

      $slot.append('<div class="andr-pr__leaflet" aria-label="' + this.esc(t.map_label) + '"></div>');

      this.colors = {
        on: this.cssVar('--andr-color-brand'),
        off: this.cssVar('--andr-color-surface-muted'),
        line: this.cssVar('--andr-color-surface'),
        hover: this.cssVar('--andr-color-text-muted'),
      };
      this.layers = {};

      const map = L.map($slot.find('.andr-pr__leaflet')[0], {
        zoomControl: false,
        attributionControl: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        touchZoom: false,
        dragging: false,
        keyboard: false,
        zoomSnap: 0,
      });

      const group = L.geoJSON(geo, {
        filter: (feature) => feature.properties.code !== 'C',
        style: (feature) => Object.assign(this.style(feature.properties.code), {
          className: 'andr-prov andr-prov--' + feature.properties.code,
        }),
        onEachFeature(feature, layer) {
          self.layers[feature.properties.code] = layer;
        },
      });

      this.bounds = group.getBounds();

      const nw = L.CRS.EPSG3857.latLngToPoint(this.bounds.getNorthWest(), 0);
      const se = L.CRS.EPSG3857.latLngToPoint(this.bounds.getSouthEast(), 0);

      this.ratio = (se.x - nw.x) / (se.y - nw.y);
      this.map = map;
      this.fit();
      group.addTo(map);

      this.caba = L.circleMarker([-34.61, -58.44], Object.assign({ radius: 6, className: 'andr-prov andr-prov--C' }, this.style('C'))).addTo(map);
      this.tip = $('<div class="andr-pr__tip" hidden></div>').appendTo(this.$modal)[0];

      const el = map.getContainer();

      el.addEventListener('pointerdown', (e) => self.strokeStart(e));
      el.addEventListener('pointerleave', () => self.setHover('', null));
      document.addEventListener('pointerout', (e) => {
        if (!e.relatedTarget) self.setHover('', null);
      });
      document.addEventListener('pointermove', (e) => self.pointerMove(e));
      document.addEventListener('pointerup', (e) => self.strokeEnd(e));
      document.addEventListener('pointercancel', (e) => self.strokeEnd(e));
      window.addEventListener('blur', () => self.strokeEnd(null));
      const refit = () => {
        if (self.modalOpen()) self.fit();
      };

      window.addEventListener('resize', refit);

      if (window.ResizeObserver) new ResizeObserver(refit).observe(el.closest('.andr-pr__stage'));
    },

    codeAt(x, y) {
      const el = document.elementFromPoint(x, y);
      const node = el && el.closest ? el.closest('.andr-prov') : null;
      const match = node ? /andr-prov--(\w+)/.exec(node.getAttribute('class') || '') : null;

      return match ? match[1] : '';
    },

    strokeStart(e) {
      if (e.pointerType === 'mouse' && e.button !== 0) return;

      const code = this.codeAt(e.clientX, e.clientY);

      if (!code) return;

      e.preventDefault();

      if (e.target.hasPointerCapture && e.target.hasPointerCapture(e.pointerId)) e.target.releasePointerCapture(e.pointerId);

      this.stroke = { paint: !this.sel[code] };
      document.body.classList.add('andr-pr--painting');
      this.apply(code);
      this.setHover(code, e);
    },

    pointerMove(e) {
      if (!this.map || !this.modalOpen()) return;

      const code = this.codeAt(e.clientX, e.clientY);

      if (this.stroke && code) this.apply(code);

      this.setHover(code, e);
    },

    strokeEnd(e) {
      if (!this.stroke) return;

      this.stroke = null;
      document.body.classList.remove('andr-pr--painting');

      if (!e || e.pointerType === 'touch') {
        this.setHover('', null);
        return;
      }

      this.setHover(this.codeAt(e.clientX, e.clientY), e);
    },

    apply(code) {
      if (!!this.sel[code] === this.stroke.paint) return;

      if (this.stroke.paint) this.sel[code] = true;
      else delete this.sel[code];

      this.paintOne(code);
      this.syncSummary();
    },

    setHover(code, e) {
      if (code !== this.hover) {
        this.hover = code;
        this.drawOutline();
      }

      if (!code || !e) {
        this.tip.hidden = true;
        return;
      }

      this.tip.textContent = this.provinceNames()[code] || code;
      this.tip.hidden = false;

      const half = this.tip.offsetWidth / 2 + 4;

      this.tip.style.left = Math.min(Math.max(e.clientX, half), window.innerWidth - half) + 'px';
      this.tip.style.top = (e.clientY - 12) + 'px';
    },

    drawOutline() {
      const layer = this.hover ? this.layers[this.hover] : null;

      if (!layer) {
        if (this.outline) this.map.removeLayer(this.outline);
        return;
      }

      if (!this.outline) {
        this.outline = L.polygon(layer.getLatLngs(), { interactive: false, fill: false, color: this.colors.hover, weight: 2, opacity: 1 });
      } else {
        this.outline.setLatLngs(layer.getLatLngs());
      }

      if (!this.map.hasLayer(this.outline)) this.outline.addTo(this.map);
    },

    closeModal() {
      this.$modal.prop('hidden', true);
      $('body').removeClass('andr-modal-open');
      this.showError('');
      this.editing = null;

      if (this.map) {
        this.strokeEnd(null);
        this.setHover('', null);
      }
    },

    syncForm() {
      const $m = this.$modal;
      const percent = this.kind === 'porcentaje';

      $m.find('[data-pr-type]').each(function () {
        const on = $(this).attr('data-pr-type') === Page.type;

        $(this).toggleClass('is-on', on).attr('aria-checked', on ? 'true' : 'false');
      });
      $m.find('[data-pr-form-kind]').each(function () {
        const on = $(this).attr('data-pr-form-kind') === Page.kind;

        $(this).toggleClass('is-on', on).attr('aria-checked', on ? 'true' : 'false');
      });
      $m.find('[data-pr-prefix]').prop('hidden', percent);
      $m.find('[data-pr-suffix]').prop('hidden', !percent);
      this.syncExample();
    },

    price(base, type, kind, value) {
      const amount = kind === 'porcentaje' ? base * value / 100 : value;

      return Math.max(0, Math.round((base + (type === 'descuento' ? -amount : amount)) * 100) / 100);
    },

    syncExample() {
      const value = this.parseAmount(this.$modal.find('[data-pr-value]').val());
      const $example = this.$modal.find('[data-pr-example]');

      if (!(value > 0)) {
        $example.text(t.example_empty);
        return;
      }

      const base = '$' + this.number(EXAMPLE_PRICE);
      const final = '$' + this.number(this.price(EXAMPLE_PRICE, this.type, this.kind, value));

      $example.text(String(t.example).replace('%1$s', base).replace('%2$s', final));
    },

    readForm() {
      const $m = this.$modal;
      const total = parseInt(cfg.province_count, 10) || 24;
      const provinces = this.selected();

      return {
        id: this.editing ? this.editing.id : '',
        nombre: String($m.find('[data-pr-name]').val() || '').trim(),
        tipo: this.type,
        forma: this.kind,
        valor: this.parseAmount($m.find('[data-pr-value]').val()),
        servicios: $m.find('[data-pr-service]:checked').map(function () { return $(this).val(); }).get(),
        provincias: provinces.length === total ? [] : provinces,
        selected: provinces.length,
        desde: String($m.find('[data-pr-from]').val() || ''),
        hasta: String($m.find('[data-pr-until]').val() || ''),
        activa: this.editing ? this.editing.activa : true,
      };
    },

    problem(p) {
      if (!p.nombre) return t.name_required;
      if (!(p.valor > 0)) return t.value_required;
      if (p.tipo === 'descuento' && p.forma === 'porcentaje' && p.valor > 100) return t.percent_max;
      if (!p.servicios.length) return t.services_none;
      if (!p.selected) return t.provinces_none;
      if (p.desde && p.hasta && p.hasta < p.desde) return t.dates_order;

      return '';
    },

    save() {
      const p = this.readForm();
      const problem = this.problem(p);

      if (problem) {
        this.showError(problem);
        return;
      }

      delete p.selected;
      this.send(cfg.save_action, { promotion: JSON.stringify(p) }, true);
    },

    send(action, data, closes) {
      if (this.busy) return;

      this.busy = true;

      $.post(cfg.ajax_url, $.extend({ action: action, nonce: cfg.nonce }, data))
        .done((res) => {
          this.promotions = res.data.promotions || [];
          this.confirming = '';

          if (closes) this.closeModal();

          this.render();
        })
        .fail((xhr) => {
          const message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message;

          this.confirming = '';
          this.render();
          this.showError(message || t.save_error);
        })
        .always(() => {
          this.busy = false;
        });
    },

    tag(p) {
      const sign = p.tipo === 'descuento' ? '−' : '+';

      return sign + (p.forma === 'porcentaje' ? this.number(p.valor) + ' %' : '$' + this.number(p.valor));
    },

    card(p) {
      const paused = !p.activa;
      const confirm = this.confirming === p.id
        ? '<div class="andr-pr__confirm" role="alert"><span>' + this.esc(t.confirm) + '</span>'
          + '<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" data-pr-remove-yes>' + this.esc(t.remove) + '</button>'
          + '<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm" data-pr-cancel>' + this.esc(t.cancel) + '</button></div>'
        : '';

      return '<div class="andr-pr__entry"><div class="andr-pr__card">'
        + '<span class="andr-pr__tag andr-pr__tag--' + this.esc(p.tipo) + '">' + this.esc(this.tag(p)) + '</span>'
        + '<div class="andr-pr__main"><div class="andr-pr__head"><span class="andr-pr__name" title="' + this.esc(p.nombre) + '">' + this.esc(p.nombre) + '</span>'
        + '<span class="andr-badge ' + (BADGES[p.estado] || 'andr-badge--neutral') + '">' + this.esc(p.estado_texto) + '</span></div>'
        + '<p class="andr-pr__summary">' + this.esc(p.resumen) + '</p></div>'
        + '<div class="andr-pr__actions">'
        + '<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm" data-pr-edit="' + this.esc(p.id) + '">' + ICONS.edit + '<span>' + this.esc(t.edit) + '</span></button>'
        + '<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm" data-pr-toggle="' + this.esc(p.id) + '">' + (paused ? ICONS.resume : ICONS.pause) + '<span>' + this.esc(paused ? t.resume : t.pause) + '</span></button>'
        + '<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andr-pr__danger" data-pr-remove="' + this.esc(p.id) + '">' + ICONS.remove + '<span>' + this.esc(t.remove) + '</span></button>'
        + '</div></div>' + confirm + '</div>';
    },

    render() {
      const n = this.promotions.length;

      this.$root.find('[data-pr-list]').html(this.promotions.map((p) => this.card(p)).join('')).prop('hidden', !n);
      this.$root.find('[data-pr-empty]').prop('hidden', n > 0);
      this.$root.find('[data-pr-count]').text(n === 0 ? '' : (n === 1 ? t.count_one : this.fill(t.count_many, n)));
    },
  };

  $(function () {
    Page.init();
  });
})(jQuery);
