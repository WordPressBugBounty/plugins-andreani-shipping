/**
 * Andreani — Selector de paquete guardado para un producto.
 *
 * Lo comparten el editor inline de Mis productos y la pestaña Envío: elige una
 * caja o un juego de cajas guardado, sugiere el que coincide con lo tipeado a mano
 * y guarda lo tipeado como paquete nuevo.
 */
(function (window, $) {
	'use strict';

	var BP = window.AndreaniBoxPreview;
	var SET = 'juego';
	var MULTI = 'multibulto';

	function text(key) {
		return (BP.i18n() || {})[key] || '';
	}

	function esc(value) {
		return $('<div>').text(value == null ? '' : value).html();
	}

	function num(value) {
		return Math.round((parseFloat(value) || 0) * 100) / 100;
	}

	function fmt(value) {
		return String(num(value));
	}

	function unit() {
		return (BP.packageApi() || {}).unit || 'cm';
	}

	function weightUnit() {
		return (BP.packageApi() || {}).weight_unit || 'kg';
	}

	function find(id) {
		return id ? (BP.packages().filter(function (p) { return p.id === id; })[0] || null) : null;
	}

	function sides(p) {
		return [num(p.length), num(p.width), num(p.height)].sort(function (a, b) { return a - b; }).join('x');
	}

	function dimsText(p) {
		if (p.type === SET) {
			return text('pkg_set_count').replace('%d', p.boxes.length);
		}

		return p.type === 'bolsa'
			? fmt(p.length) + '×' + fmt(p.width) + ' ' + unit()
			: fmt(p.length) + '×' + fmt(p.width) + '×' + fmt(p.height) + ' ' + unit();
	}

	function thumbPackages(p) {
		var f = BP.cmFactor() || 1;
		var pieces = p.type === SET ? p.boxes : [p];

		return pieces.map(function (b) {
			var h = (parseFloat(b.height) || 0) / f;

			return { w: (parseFloat(b.length) || 0) / f, d: (parseFloat(b.width) || 0) / f, h: h, kg: 1, units: 1, base: h, inc: 0, count: 1, name: '', ref: '', fixed: false };
		});
	}

	function drawStage(svg, p, width, height) {
		var pk = thumbPackages(p).filter(function (b) { return b.w > 0 && b.d > 0 && b.h > 0; });

		if (!pk.length) {
			svg.innerHTML = '';
			return;
		}

		BP.render(svg, BP.separate(pk, 'kraft').filter(function (b) { return !b.more; }), { W: width, H: height, pad: 6, fit: true, max: 5 });
	}

	function draftSignature(draft) {
		if (draft.type === SET) {
			return SET + ':' + draft.boxes.map(function (b) { return sides(b) + '@' + num(b.weight); }).sort().join('|');
		}

		return 'caja:' + sides(draft);
	}

	function packageSignature(p) {
		if (p.type === SET) {
			return SET + ':' + p.boxes.map(function (b) { return sides(b) + '@' + num(b.weight); }).sort().join('|');
		}

		return p.type === 'bolsa' ? '' : 'caja:' + sides(p);
	}

	var modal = null;

	function ensureModal() {
		if (modal) {
			return modal;
		}

		modal = $(
			'<div class="andr-modal andr-pkgm" role="dialog" aria-modal="true" hidden>'
			+ '<div class="andr-modal__backdrop" data-pkgm-close></div>'
			+ '<div class="andr-modal__container andr-modal__container--narrow">'
			+ '<div class="andr-modal__header"><h3 class="andr-modal__title" data-pkgm-title></h3>'
			+ '<button type="button" class="andr-modal__close" data-pkgm-close aria-label="' + esc(text('pkg_cancel')) + '">&times;</button></div>'
			+ '<div class="andr-modal__body">'
			+ '<div class="andr-pkgm__stage"><svg data-pkgm-stage aria-hidden="true"></svg></div>'
			+ '<label class="andr-pkgm__field"><span>' + esc(text('pkg_name')) + '</span><input type="text" maxlength="40" data-pkgm-name></label>'
			+ '<p class="andr-pkgm__summary" data-pkgm-summary></p>'
			+ '<p class="andr-pkgm__error" role="alert" data-pkgm-error hidden></p>'
			+ '</div>'
			+ '<div class="andr-modal__footer">'
			+ '<button type="button" class="andr-btn andr-btn--ghost" data-pkgm-close>' + esc(text('pkg_cancel')) + '</button>'
			+ '<button type="button" class="andr-btn andr-btn--primary" data-pkgm-save>' + esc(text('pkg_confirm')) + '</button>'
			+ '</div></div></div>'
		).appendTo('body');

		modal.on('click', '[data-pkgm-close]', function () {
			closeModal();
		});

		$(document).on('keydown.andrPkgModal', function (e) {
			if (e.key === 'Escape' && modal && !modal.prop('hidden')) {
				closeModal();
			}
		});

		return modal;
	}

	function closeModal() {
		if (modal) {
			modal.prop('hidden', true).data('onSaved', null);
			$('body').removeClass('andr-modal-open');
		}
	}

	function summaryOf(draft) {
		if (draft.type === SET) {
			return draft.boxes.map(function (b, i) {
				return (text('piece_title') || 'Caja') + ' ' + (i + 1) + (b.ref ? ' · ' + b.ref : '') + ' · ' + fmt(b.length) + '×' + fmt(b.width) + '×' + fmt(b.height) + ' ' + unit() + ' · ' + fmt(b.weight) + ' ' + weightUnit();
			}).join('\n');
		}

		return fmt(draft.length) + '×' + fmt(draft.width) + '×' + fmt(draft.height) + ' ' + unit();
	}

	function openModal(draft, onSaved) {
		var $m = ensureModal();
		var isSet = draft.type === SET;

		$m.find('[data-pkgm-title]').text(text(isSet ? 'pkg_modal_title_set' : 'pkg_modal_title'));
		$m.find('[data-pkgm-name]').val('').attr('placeholder', text(isSet ? 'pkg_name_hint_set' : 'pkg_name_hint'));
		$m.find('[data-pkgm-summary]').css('white-space', 'pre-line').text(summaryOf(draft));
		$m.find('[data-pkgm-error]').prop('hidden', true).text('');
		$m.find('[data-pkgm-save]').prop('disabled', false);
		$m.data('draft', draft).data('onSaved', onSaved).prop('hidden', false);
		$('body').addClass('andr-modal-open');
		drawStage($m.find('[data-pkgm-stage]').get(0), isSet ? draft : Object.assign({}, draft, { type: 'caja' }), 360, 150);
		$m.find('[data-pkgm-name]').trigger('focus');

		$m.off('click.pkgmSave').on('click.pkgmSave', '[data-pkgm-save]', function () {
			var api = BP.packageApi();
			var $btn = $(this).prop('disabled', true);
			var payload = Object.assign({}, $m.data('draft'), { name: String($m.find('[data-pkgm-name]').val() || '').trim() });

			$.post(api.ajax_url, { action: api.action, nonce: api.nonce, package: JSON.stringify(payload) })
				.done(function (res) {
					if (!res || !res.success) {
						fail(res && res.data && res.data.message);
						return;
					}

					BP.setPackages(res.data.packages || []);

					var cb = $m.data('onSaved');

					closeModal();

					if (cb) {
						cb(res.data.id);
					}
				})
				.fail(function (xhr) {
					fail(xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message);
				});

			function fail(message) {
				$m.find('[data-pkgm-error]').text(message || text('pkg_error')).prop('hidden', false);
				$btn.prop('disabled', false);
			}
		});
	}

	function create(opts) {
		var $root = $(opts.root);
		var $select = $root.find('[data-pkg-select]');
		var $label = $root.find('[data-pkg-label]');
		var $hint = $root.find('[data-pkg-hint]');
		var $suggest = $root.find('[data-pkg-suggest]');
		var $save = $root.find('[data-pkg-save]');
		var $boxes = $root.find('[data-pkg-boxes]');
		var $thumb = $root.find('[data-pkg-thumb]');
		var state = { value: $root.attr('data-value') || '', deleted: '' };

		function mode() {
			return opts.getMode();
		}

		function isSetMode() {
			return mode() === MULTI;
		}

		function candidates() {
			return BP.packages().filter(function (p) {
				return (p.type === SET) === isSetMode();
			});
		}

		function current() {
			var p = find(state.value);

			return p && (p.type === SET) === isSetMode() ? p : null;
		}

		function optionLabel(p) {
			return p.name + ' · ' + dimsText(p);
		}

		function renderOptions() {
			var html = '<option value="">' + esc(text(isSetMode() ? 'pkg_manual_set' : 'pkg_manual')) + '</option>';

			candidates().forEach(function (p) {
				html += '<option value="' + esc(p.id) + '">' + esc(optionLabel(p)) + '</option>';
			});

			$select.html(html);
		}

		function renderSuggest() {
			var draft = !current() && opts.getManual ? opts.getManual() : null;
			var match = null;

			if (draft) {
				var sig = draftSignature(draft);

				match = candidates().filter(function (p) { return packageSignature(p) === sig; })[0] || null;
			}

			if (!match) {
				$suggest.prop('hidden', true).empty();
				return;
			}

			$suggest.removeClass('andr-pkg__suggest--error').prop('hidden', false)
				.html(esc(text('pkg_suggest').replace('%s', match.name)) + ' <button type="button" data-pkg-use="' + esc(match.id) + '">' + esc(text('pkg_suggest_use')) + '</button>');
		}

		function render() {
			var p = current();

			if (!p && state.value && !find(state.value)) {
				state.deleted = state.value;
			}

			if (!p) {
				state.value = '';
			}

			renderOptions();
			$select.val(state.value);
			$label.text(text(isSetMode() ? 'pkg_label_set' : 'pkg_label'));
			$hint.text(p ? text(isSetMode() ? 'pkg_hint_set' : 'pkg_hint_box') : '').prop('hidden', !p);
			$save.text(text(isSetMode() ? 'pkg_save_set' : 'pkg_save')).prop('hidden', !!p);
			$root.find('[data-pkg-deleted]').prop('hidden', !state.deleted || !!p);

			if (p && p.type === SET) {
				$boxes.html(p.boxes.map(function (b, i) {
					return '<li><b>' + esc((text('piece_title') || 'Caja') + ' ' + (i + 1)) + '</b>' + (b.ref ? '<span>' + esc(b.ref) + '</span>' : '')
						+ '<span>' + esc(fmt(b.length) + '×' + fmt(b.width) + '×' + fmt(b.height) + ' ' + unit()) + '</span><span>' + esc(fmt(b.weight) + ' ' + weightUnit()) + '</span></li>';
				}).join('')).prop('hidden', false);
			} else {
				$boxes.prop('hidden', true).empty();
			}

			if (p) {
				$thumb.attr('data-andr-thumb', JSON.stringify(thumbPackages(p))).prop('hidden', false);
				BP.mountThumbs($root.get(0));
			} else {
				$thumb.prop('hidden', true);
			}

			renderSuggest();
			$(opts.scope).attr('data-pkg', p ? (p.type === SET ? 'juego' : 'caja') : 'none');
		}

		function choose(id) {
			state.value = id || '';
			state.deleted = '';
			render();
			opts.onChange();
		}

		$select.on('change', function () {
			choose($(this).val());
		});

		$root.on('click', '[data-pkg-use]', function () {
			choose($(this).attr('data-pkg-use'));
		});

		$save.on('click', function () {
			var draft = opts.getManual ? opts.getManual() : null;

			if (!draft) {
				$suggest.addClass('andr-pkg__suggest--error').prop('hidden', false).text(text('pkg_incomplete'));
				return;
			}

			openModal(draft, function (id) {
				choose(id);
			});
		});

		render();

		return {
			value: function () {
				return current() ? state.value : '';
			},
			set: function (id) {
				state.value = id || '';
				state.deleted = '';
				render();
			},
			effective: function () {
				return current();
			},
			state: function () {
				return current() ? 'ok' : (state.deleted ? 'deleted' : 'none');
			},
			refresh: render,
			markDeleted: function (ref) {
				state.deleted = ref || '';
				render();
			}
		};
	}

	window.AndreaniPackagePicker = { create: create, find: find, dimsText: dimsText, thumbPackages: thumbPackages, drawStage: drawStage };
})(window, window.jQuery);
