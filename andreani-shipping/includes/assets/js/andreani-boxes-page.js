(function ($) {
  'use strict';

  const cfg = window.AndreaniBoxesPage;

  if (!cfg) return;

  const t = cfg.i18n || {};
  const ICONS = {
    caja: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8l-9 4-9-4 9-4 9 4z"/><path d="M3 8v8l9 4 9-4V8"/><line x1="12" y1="12" x2="12" y2="20"/></svg>',
    bolsa: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5.5" y="2.5" width="13" height="19" rx="1.5"/><line x1="5.5" y1="7.5" x2="18.5" y2="7.5"/><line x1="9" y1="5" x2="15" y2="5"/><line x1="5.5" y1="19" x2="18.5" y2="19"/></svg>',
    juego: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 7l-5-2-5 2 5 2 5-2z"/><path d="M4 7v5l5 2 5-2V7"/><path d="M20 12l-5-2-1 .4"/><path d="M20 12v5l-5 2-5-2"/></svg>',
    edit: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/></svg>',
    remove: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>',
  };

  const Page = {
    init() {
      this.$root = $('[data-bx-root]');
      if (!this.$root.length) return;

      this.$modal = this.$root.find('[data-bx-modal]');
      this.boxes = cfg.boxes || [];
      this.max = parseInt(cfg.max, 10) || 30;
      this.factor = parseFloat(cfg.cm_factor) || 1;
      this.unit = cfg.unit || 'cm';
      this.maxSet = parseInt(cfg.max_set, 10) || 10;
      window.AndreaniBoxPreview.configure({ cm_factor: this.factor });
      this.editing = null;
      this.confirming = -1;
      this.query = '';
      this.fit = null;
      this.token = 0;
      this.busy = false;

      this.bind();
      this.render();
      this.loadFit();
    },

    esc(value) {
      return String(value == null ? '' : value).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    },

    fmt(value) {
      return String(Math.round((parseFloat(value) || 0) * 100) / 100);
    },

    fill(template, value) {
      return String(template).replace('%s', value);
    },

    quick() {
      const r = (cm) => this.fmt(cm * this.factor);

      return [
        { name: t.quick_small, type: 'caja', length: r(20), width: r(15), height: r(10), text: '20×15×10' },
        { name: t.quick_medium, type: 'caja', length: r(40), width: r(30), height: r(30), text: '40×30×30' },
        { name: t.quick_bag, type: 'bolsa', length: r(35), width: r(45), height: r(5), text: '35×45' },
        { name: '', label: t.modal_new_set, type: 'juego' },
      ];
    },

    showError(text) {
      const $target = this.modalOpen() ? this.$modal.find('[data-bx-form-error]') : this.$root.find('[data-bx-error]');

      $target.text(text || '').prop('hidden', !text);
    },

    modalOpen() {
      return !this.$modal.prop('hidden');
    },

    bind() {
      const self = this;

      this.$root.on('click', '[data-bx-add]', function () {
        if (self.boxes.length >= self.max) {
          self.showError(self.fill(t.max, self.max));
          return;
        }

        self.openModal(-1);
      });

      this.$root.on('input', '[data-bx-search]', function () {
        self.query = String($(this).val() || '').trim().toLowerCase();
        self.render();
      });

      this.$root.on('click', '[data-bx-quick]', function () {
        if (self.boxes.length >= self.max) {
          self.showError(self.fill(t.max, self.max));
          return;
        }

        const q = self.quick()[parseInt($(this).attr('data-bx-quick'), 10)];

        self.openModal(-1, q);
      });

      this.$root.on('click', '[data-bx-edit]', function () {
        self.openModal(parseInt($(this).attr('data-bx-edit'), 10));
      });

      this.$root.on('click', '[data-bx-close]', function () {
        self.closeModal();
      });

      $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && self.$root.length && self.modalOpen()) {
          self.closeModal();
        }
      });

      this.$root.on('click', '[data-bx-type]', function () {
        const next = $(this).attr('data-bx-type');

        if ((next === 'juego') !== (self.editing.type === 'juego')) {
          self.editing.draftName = String(self.val('name') || '');
          self.editing.type = next;
          self.$modal.find('[data-bx-form]').html(self.formMarkup());
          self.$modal.find('[data-bx-modal-title]').text(self.modalTitle());
          self.drawStage();
          self.checkDuplicate();
          return;
        }

        self.editing.type = next;
        self.$modal.find('[data-bx-type]').each(function () {
          const on = $(this).attr('data-bx-type') === self.editing.type;
          $(this).toggleClass('is-on', on).attr('aria-pressed', on ? 'true' : 'false');
        });
        self.$modal.find('[data-bx-label="height"]').text(self.editing.type === 'bolsa' ? t.thickness : t.height);
        self.drawStage();
        self.checkDuplicate();
      });

      this.$root.on('input', '[data-bx-field], [data-bx-set]', function () {
        self.drawStage();
        self.checkDuplicate();
      });

      this.$root.on('click', '[data-bx-set-add]', function () {
        if (self.readSet().length >= self.maxSet) {
          self.showError(self.fill(t.set_max, self.maxSet));
          return;
        }

        self.$modal.find('[data-bx-sets]').append(self.setRowMarkup({}, self.readSet().length));
        self.renumberSet();
        self.$modal.find('[data-bx-set-row]').last().find('input').first().trigger('focus');
        self.drawStage();
      });

      this.$root.on('click', '[data-bx-set-remove]', function () {
        $(this).closest('[data-bx-set-row]').remove();
        self.renumberSet();
        self.showError('');
        self.drawStage();
      });

      this.$root.on('click', '[data-bx-save]', function () {
        self.saveEdit();
      });

      this.$root.on('keydown', '[data-bx-field], [data-bx-set]', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          self.saveEdit();
        }
      });

      this.$root.on('click', '[data-bx-remove]', function () {
        self.showError('');
        self.confirming = parseInt($(this).attr('data-bx-remove'), 10);
        self.render();
        self.$root.find('[data-bx-remove-yes]').trigger('focus');
      });

      this.$root.on('click', '[data-bx-cancel]', function () {
        self.confirming = -1;
        self.render();
      });

      this.$root.on('click', '[data-bx-remove-yes]', function () {
        const list = self.boxes.slice();

        list.splice(self.confirming, 1);
        self.persist(list);
      });
    },

    readSet() {
      return this.$modal.find('[data-bx-set-row]').map(function () {
        const row = {};

        $(this).find('[data-bx-set]').each(function () {
          row[$(this).attr('data-bx-set')] = $(this).val();
        });

        return row;
      }).get();
    },

    renumberSet() {
      const rows = this.$modal.find('[data-bx-set-row]');

      rows.each(function (i) {
        $(this).find('[data-bx-set-n]').text(t.set_box + ' ' + (i + 1));
      });
      rows.find('[data-bx-set-remove]').prop('hidden', rows.length <= 2);
    },

    setPieces(rows) {
      const pk = rows
        .map((r) => ({ length: parseFloat(r.length) || 0, width: parseFloat(r.width) || 0, height: parseFloat(r.height) || 0 }))
        .filter((r) => r.length > 0 && r.width > 0 && r.height > 0)
        .map((r) => ({ w: r.length / this.factor, d: r.width / this.factor, h: r.height / this.factor, kg: 1, units: 1, base: r.height / this.factor, inc: 0, count: 1, name: '', ref: '', fixed: false }));

      return window.AndreaniBoxPreview.separate(pk, 'kraft').filter((b) => !b.more);
    },

    piece(box, cm) {
      const bag = box.type === 'bolsa';
      const l = box.length / (cm ? 1 : this.factor);
      const w = box.width / (cm ? 1 : this.factor);
      const h = box.height / (cm ? 1 : this.factor);

      if (bag) {
        const across = Math.min(l, w);

        return [{ x: 0, y: 0, z: 0, w: across, d: Math.max(h, across * 0.2), h: Math.max(l, w), t: 'bag' }];
      }

      return [{ x: 0, y: 0, z: 0, w: l, d: w, h: h, tape: true, label: true, t: 'kraft' }];
    },

    paintThumbs() {
      const self = this;

      this.$root.find('[data-bx-thumb]').each(function () {
        const box = self.boxes[parseInt($(this).attr('data-bx-thumb'), 10)];

        if (box.type === 'juego') {
          window.AndreaniBoxPreview.render(this, self.setPieces(box.boxes), { W: 44, H: 44, pad: 3 });
          return;
        }

        window.AndreaniBoxPreview.render(this, self.piece(box), { W: 44, H: 44, pad: 3 });
      });
    },

    openModal(index, preset) {
      this.showError('');
      this.confirming = -1;
      this.editing = { index: index, preset: preset || null, type: preset ? preset.type : (index < 0 ? 'caja' : this.boxes[index].type) };
      this.$modal.find('[data-bx-modal-title]').text(this.modalTitle());
      this.$modal.find('[data-bx-form]').html(this.formMarkup());
      this.$modal.prop('hidden', false);
      $('body').addClass('andr-modal-open');
      this.drawStage();
      this.checkDuplicate();
      this.$modal.find('[data-bx-field="name"]').trigger('focus');
    },

    modalTitle() {
      const set = this.editing.type === 'juego';

      return this.editing.index < 0 ? (set ? t.modal_new_set : t.modal_new) : (set ? t.modal_edit_set : t.modal_edit);
    },

    closeModal() {
      this.showError('');
      this.$modal.prop('hidden', true);
      $('body').removeClass('andr-modal-open');
      this.editing = null;
    },

    val(key) {
      return this.$modal.find('[data-bx-field="' + key + '"]').val();
    },

    drawStage() {
      if (this.editing.type === 'juego') {
        window.AndreaniBoxPreview.render(this.$modal.find('[data-bx-stage]').get(0), this.setPieces(this.readSet()), { W: 360, H: 280, pad: 30, fit: true, max: 5 });
        return;
      }

      const bag = this.editing.type === 'bolsa';
      const read = (key, fallback) => {
        const n = parseFloat(this.val(key));

        return n > 0 ? n : fallback;
      };
      const l = read('length', 20 * this.factor);
      const w = read('width', 20 * this.factor);
      const h = read('height', (bag ? 5 : 20) * this.factor);
      const text = (letter, key, value) => letter + ' ' + (parseFloat(this.val(key)) > 0 ? this.fmt(value) + ' ' + this.unit : '–');

      window.AndreaniBoxPreview.render(
        this.$modal.find('[data-bx-stage]').get(0),
        this.piece({ type: this.editing.type, length: l, width: w, height: h }),
        {
          W: 360,
          H: 280,
          pad: 52,
          fit: true,
          max: 5,
          cotas: bag
            ? { w: text(l > w ? t.cota_w : t.cota_l, l > w ? 'width' : 'length', Math.min(l, w)), d: text(t.cota_t, 'height', h), h: text(l > w ? t.cota_l : t.cota_w, l > w ? 'length' : 'width', Math.max(l, w)) }
            : { w: text(t.cota_l, 'length', l), d: text(t.cota_w, 'width', w), h: text(t.cota_h, 'height', h) },
        }
      );
    },

    sides(box) {
      return [box.length, box.width, box.height].map((n) => this.fmt(n)).sort().join('x');
    },

    duplicate() {
      if (this.editing.type === 'juego') return null;

      const dims = { length: this.val('length'), width: this.val('width'), height: this.val('height') };

      if (!(parseFloat(dims.length) > 0 && parseFloat(dims.width) > 0 && parseFloat(dims.height) > 0)) return null;

      const key = this.editing.type + ':' + this.sides(dims);

      return this.boxes.find((box, i) => i !== this.editing.index && box.type + ':' + this.sides(box) === key) || null;
    },

    checkDuplicate() {
      const dup = this.duplicate();

      this.showError(dup ? this.fill(t.duplicate, dup.name) : '');
      this.$modal.find('[data-bx-save]').prop('disabled', !!dup);
    },

    nameKey(name) {
      return String(name || '').replace(/\s+/g, ' ').trim().toLowerCase();
    },

    problem(box) {
      const key = this.nameKey(box.name);

      if (!key) return t.name_required;

      if (this.boxes.some((other, i) => i !== this.editing.index && this.nameKey(other.name) === key)) return t.name_duplicate;

      const max = parseFloat(cfg.side_max) || 0;
      const pieces = box.type === 'juego' ? box.boxes : [box];
      const over = pieces.some((p) => [p.length, p.width, p.height].some((n) => max > 0 && parseFloat(n) > max + 0.01));

      return over ? this.fill(t.side_max, this.fmt(max) + ' ' + this.unit) : '';
    },

    saveSet() {
      const rows = this.readSet().map((r) => ({
        ref: String(r.ref || '').trim(),
        length: parseFloat(r.length) || 0,
        width: parseFloat(r.width) || 0,
        height: parseFloat(r.height) || 0,
        weight: parseFloat(r.weight) || 0,
      }));
      const complete = rows.filter((r) => r.length > 0 && r.width > 0 && r.height > 0 && r.weight > 0);

      if (complete.length < 2 || complete.length !== rows.length) {
        this.showError(t.set_min);
        return;
      }

      const box = { name: (this.val('name') || '').trim(), type: 'juego', boxes: complete };
      const problem = this.problem(box);

      if (problem) {
        this.showError(problem);
        return;
      }

      const list = this.boxes.slice();

      if (this.editing.index < 0) {
        list.unshift(box);
      } else {
        box.id = this.boxes[this.editing.index].id;
        list[this.editing.index] = box;
      }

      this.persist(list);
    },

    saveEdit() {
      if (this.busy || this.duplicate()) return;

      if (this.editing.type === 'juego') {
        this.saveSet();
        return;
      }

      const length = parseFloat(this.val('length'));
      const width = parseFloat(this.val('width'));
      const height = parseFloat(this.val('height'));
      const bag = this.editing.type === 'bolsa';

      if (!(length > 0) || !(width > 0) || (!bag && !(height > 0))) {
        this.showError(t.invalid);
        return;
      }

      const box = {
        name: (this.val('name') || '').trim(),
        type: this.editing.type,
        length: length,
        width: width,
        height: bag && !(height > 0) ? this.fmt(5 * this.factor) : height,
      };
      const problem = this.problem(box);

      if (problem) {
        this.showError(problem);
        return;
      }

      const list = this.boxes.slice();

      if (this.editing.index < 0) {
        list.unshift(box);
      } else {
        box.id = this.boxes[this.editing.index].id;
        list[this.editing.index] = box;
      }

      this.persist(list);
    },

    persist(list) {
      if (this.busy) return;

      this.busy = true;
      this.showError('');
      this.$root.find('[data-bx-save], [data-bx-remove-yes]').prop('disabled', true);

      $.post(cfg.ajax_url, { action: cfg.action, nonce: cfg.nonce, boxes: JSON.stringify(list) })
        .done((res) => {
          if (!res || !res.success) {
            this.failed(res && res.data && res.data.message);
            return;
          }

          this.boxes = res.data.boxes || [];
          this.confirming = -1;
          this.fit = null;

          if (this.modalOpen()) this.closeModal();

          this.render();
          this.loadFit();
        })
        .fail((xhr) => {
          this.failed(xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message);
        })
        .always(() => {
          this.busy = false;
        });
    },

    failed(message) {
      this.showError(message || t.save_error);
      this.$root.find('[data-bx-save], [data-bx-remove-yes]').prop('disabled', false);
    },

    loadFit() {
      if (!this.boxes.length) return;

      const token = ++this.token;

      $.post(cfg.ajax_url, { action: cfg.fit_action, nonce: cfg.nonce })
        .done((res) => {
          if (token !== this.token) return;
          this.fit = res && res.success ? res.data : false;
          this.paintFit();
        })
        .fail(() => {
          if (token !== this.token) return;
          this.fit = false;
          this.paintFit();
        });
    },

    assignedCount(box) {
      return this.fit && this.fit.assigned && box ? (parseInt(this.fit.assigned[box.id], 10) || 0) : 0;
    },

    fitText(index) {
      if (this.fit === null) return '…';
      if (this.fit === false || typeof this.fit.counts[index] === 'undefined') return t.fit_unknown;

      const box = this.boxes[index];
      const assigned = this.assignedCount(box);
      const enter = box && box.type === 'juego' ? 0 : this.fit.counts[index];
      const parts = [];

      if (assigned > 0) parts.push(assigned === 1 ? t.assigned_one : this.fill(t.assigned_many, assigned));
      if (enter > 0) parts.push(enter === 1 ? t.enter_one : this.fill(t.enter_many, enter));

      return parts.length ? parts.join(' · ') : t.fit_none;
    },

    paintFit() {
      const self = this;

      this.$root.find('[data-bx-fit]').each(function () {
        $(this).text(self.fitText(parseInt($(this).attr('data-bx-fit'), 10)));
      });

      const evaluated = this.fit && this.fit.evaluated ? this.fit.evaluated : 0;

      this.$root.find('[data-bx-note]').text(evaluated ? this.fill(t.fit_note, evaluated) : '').prop('hidden', !evaluated);
    },

    dimsText(box) {
      if (box.type === 'juego') {
        return this.fill(t.set_count, box.boxes.length);
      }

      if (box.type === 'bolsa') {
        return this.fmt(box.length) + '×' + this.fmt(box.width) + ' ' + this.unit + ' · ' + this.fill(t.thickness_txt, this.fmt(box.height));
      }

      return this.fmt(box.length) + '×' + this.fmt(box.width) + '×' + this.fmt(box.height) + ' ' + this.unit;
    },

    field(key, label, value, extra) {
      const name = key === 'name';

      return '<label class="andr-bx__field' + (extra || '') + '"><span class="andr-bx__label" data-bx-label="' + key + '">' + this.esc(label) + '</span>'
        + '<span class="andr-bx__input"><input type="' + (name ? 'text' : 'number') + '" data-bx-field="' + key + '" value="' + this.esc(value) + '"'
        + (name ? ' maxlength="40" placeholder="' + this.esc(t.name_hint) + '"' : ' min="0" step="any"') + '>'
        + (name ? '' : '<span class="andr-bx__unit">' + this.esc(this.unit) + '</span>') + '</span></label>';
    },

    formMarkup() {
      const box = this.editing.index < 0 ? (this.editing.preset || { name: '', length: '', width: '', height: '' }) : this.boxes[this.editing.index];
      const bag = this.editing.type === 'bolsa';

      if (this.editing.draftName !== undefined) {
        box.name = this.editing.draftName;
        this.editing.draftName = undefined;
      }
      const num = (v) => (v === '' ? '' : this.fmt(v));
      const type = (key, label) => '<button type="button" class="andr-bx__type' + (this.editing.type === key ? ' is-on' : '') + '" aria-pressed="' + (this.editing.type === key ? 'true' : 'false') + '" data-bx-type="' + key + '">' + ICONS[key] + this.esc(label) + '</button>';

      if (this.editing.type === 'juego') {
        const rows = box.boxes && box.boxes.length ? box.boxes : [{}, {}];

        return '<div class="andr-bx__types" role="group">' + type('caja', t.type_box) + type('bolsa', t.type_bag) + type('juego', t.type_set) + '</div>'
          + this.field('name', t.name, box.name, ' andr-bx__field--name').replace('placeholder="' + this.esc(t.name_hint) + '"', 'placeholder="' + this.esc(t.set_name_hint) + '"')
          + '<p class="andr-bx__sethint">' + this.esc(t.set_hint) + '</p>'
          + '<div class="andr-bx__sets" data-bx-sets>' + rows.map((r, i) => this.setRowMarkup(r, i)).join('') + '</div>'
          + '<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm" data-bx-set-add>' + this.esc(t.set_add) + '</button>';
      }

      return '<div class="andr-bx__types" role="group">' + type('caja', t.type_box) + type('bolsa', t.type_bag) + type('juego', t.type_set) + '</div>'
        + this.field('name', t.name, box.name, ' andr-bx__field--name')
        + '<div class="andr-bx__sides">'
        + this.field('length', t.length, num(box.length))
        + this.field('width', t.width, num(box.width))
        + this.field('height', bag ? t.thickness : t.height, num(box.height))
        + '</div>';
    },

    setField(key, label, value, unit) {
      const v = value === undefined || value === null || value === '' ? '' : this.fmt(value);

      return '<label class="andr-bx__field"><span class="andr-bx__label">' + this.esc(label) + '</span>'
        + '<span class="andr-bx__input"><input type="number" min="0" step="any" data-bx-set="' + key + '" value="' + this.esc(v) + '"><span class="andr-bx__unit">' + this.esc(unit) + '</span></span></label>';
    },

    setRowMarkup(r, i) {
      return '<div class="andr-bx__setrow" data-bx-set-row>'
        + '<div class="andr-bx__sethead"><span class="andr-bx__setn" data-bx-set-n>' + this.esc(t.set_box + ' ' + (i + 1)) + '</span>'
        + '<input type="text" class="andr-bx__setref" data-bx-set="ref" maxlength="60" value="' + this.esc(r.ref || '') + '" placeholder="' + this.esc(t.set_ref_hint) + '" aria-label="' + this.esc(t.set_ref) + '">'
        + '<button type="button" class="andr-bx__btn andr-bx__btn--danger" data-bx-set-remove aria-label="' + this.esc(t.set_remove) + '" title="' + this.esc(t.set_remove) + '">' + ICONS.remove + '</button></div>'
        + '<div class="andr-bx__setdims">'
        + this.setField('length', t.length, r.length, this.unit)
        + this.setField('width', t.width, r.width, this.unit)
        + this.setField('height', t.height, r.height, this.unit)
        + this.setField('weight', t.set_weight, r.weight, cfg.weight_unit || t.weight_unit)
        + '</div></div>';
    },

    row(box, i) {
      const bag = box.type === 'bolsa';
      const confirm = this.confirming === i
        ? '<div class="andr-bx__confirm" role="alert"><span>' + this.esc(this.assignedCount(box) > 0 ? t.remove_assigned : t.confirm) + '</span>'
          + '<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" data-bx-remove-yes>' + this.esc(t.remove) + '</button>'
          + '<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm" data-bx-cancel>' + this.esc(t.cancel) + '</button></div>'
        : '';

      return '<div class="andr-bx__entry"><div class="andr-bx__row">'
        + '<svg class="andr-bx__thumb" data-bx-thumb="' + i + '" width="44" height="44" aria-hidden="true"></svg>'
        + '<span class="andr-bx__name" title="' + this.esc(box.name) + '">' + this.esc(box.name) + '</span>'
        + '<span class="andr-bx__dims">' + this.esc(this.dimsText(box)) + '</span>'
        + '<span class="andr-bx__kind" data-label="' + this.esc(t.type) + '">' + this.esc(box.type === 'juego' ? t.type_set : (bag ? t.type_bag : t.type_box)) + '</span>'
        + '<span class="andr-bx__fit" data-label="' + this.esc(t.fit) + '" data-bx-fit="' + i + '">' + this.esc(this.fitText(i)) + '</span>'
        + '<span class="andr-bx__actions">'
        + '<button type="button" class="andr-bx__btn" data-bx-edit="' + i + '" aria-label="' + this.esc(t.edit + ' ' + box.name) + '" title="' + this.esc(t.edit) + '">' + ICONS.edit + '</button>'
        + '<button type="button" class="andr-bx__btn andr-bx__btn--danger" data-bx-remove="' + i + '" aria-label="' + this.esc(t.remove + ' ' + box.name) + '" title="' + this.esc(t.remove) + '">' + ICONS.remove + '</button>'
        + '</span></div>' + confirm + '</div>';
    },

    render() {
      const n = this.boxes.length;
      const visible = this.boxes.map((box, i) => ({ box: box, i: i })).filter((entry) => !this.query || entry.box.name.toLowerCase().indexOf(this.query) !== -1);
      const quicks = this.quick().map((q, i) => '<button type="button" class="andr-bx__quick" data-bx-quick="' + i + '">' + this.esc(q.label || q.name + ' ' + q.text) + '</button>').join('');

      this.$root.find('[data-bx-quicks]').html(quicks);
      this.$root.find('[data-bx-bar] [data-bx-quicks], [data-bx-search-wrap]').prop('hidden', n === 0);
      this.$root.find('[data-bx-count]').text(n ? t.count.replace('%1$s', n).replace('%2$s', this.max) : '').prop('hidden', !n);
      this.$root.find('[data-bx-empty]').prop('hidden', n > 0);

      let html = '';

      if (n > 0) {
        html += '<div class="andr-bx__row andr-bx__row--head" aria-hidden="true"><span></span><span>' + this.esc(t.name) + '</span><span>' + this.esc(t.dims) + '</span><span>' + this.esc(t.type) + '</span><span>' + this.esc(t.fit) + '</span><span></span></div>';
        html += visible.length ? visible.map((entry) => this.row(entry.box, entry.i)).join('') : '<p class="andr-bx__none">' + this.esc(t.search_none) + '</p>';
      }

      this.$root.find('[data-bx-list]').prop('hidden', n === 0).html(html);
      this.paintThumbs();
      this.paintFit();
    },
  };

  $(function () {
    Page.init();
  });
})(jQuery);
