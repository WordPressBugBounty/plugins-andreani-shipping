/**
 * Andreani — Bloque de acomodo de varias unidades en la ficha de producto.
 *
 * Cada una en su caja, apiladas, o una unidad repartida en varias cajas: el modo
 * elegido decide qué se evalúa y qué se guarda. Usa delegación de eventos para
 * sobrevivir a redibujos del DOM de WC.
 */
(function ($) {
	'use strict';

	var WC_INPUTS_SELECTOR = 'input[name="_weight"], input[name="_width"], input[name="_height"], input[name="_length"]';
	var BULTO_INPUTS_SELECTOR = '.andreani-bulto-row input[type="number"]';

	var MODE_SINGLE = 'single';
	var MODE_APILADO = 'apilado';
	var MODE_MULTIBULTO = 'multibulto';

	$(function () {
		var $section = $('.andreani-despacho-section');

		if ( ! $section.length ) {
			return;
		}

		var config = window.AndreaniBultosConfig || {};
		var Preview = window.AndreaniBoxPreview;
		var i18n = ( config.preview || {} ).i18n || {};

		Preview.configure( config.preview );

		var $modeInput = $section.find('[data-andr="mode-input"]');
		var $status = $('#andreani-despacho-status');
		var $main = $('#andreani-despacho-main');
		var $mainTitle = $('#andreani-despacho-main-title');
		var $toggle = $('#andreani-despacho-toggle');
		var $body = $('#andreani-despacho-body');
		var $list = $('#andreani-bultos-list');
				var $multibultoPanel = $('#andreani-despacho-panel-multibulto');
		var $apiladoInvalid = $('#andreani-apilado-invalid');
		var $bultosInvalid = $('#andreani-bultos-invalid');
		var $units = $('#andreani-despacho-units');
		var OVERFLOW_INPUTS = 'input[name="andreani_overflow"]';

		var tmpl = null;
		if ( typeof window.wp !== 'undefined' && window.wp.template ) {
			try {
				tmpl = window.wp.template('andreani-bulto-row');
			} catch (e) {
				tmpl = null;
			}
		}

		function currentMode() {
			var mode = $modeInput.val();
			return mode === MODE_APILADO || mode === MODE_MULTIBULTO ? mode : MODE_SINGLE;
		}

		var picker = null;

		function packageMode() {
			var pkg = picker && picker.effective();

			return pkg ? ( pkg.type === 'juego' ? 'juego' : 'caja' ) : '';
		}

		function effective() {
			var pkg = picker && picker.effective();

			if ( pkg && pkg.type === 'juego' ) {
				return {
					weight: pkg.boxes[0].weight,
					length: pkg.boxes[0].length,
					width: pkg.boxes[0].width,
					height: pkg.boxes[0].height,
					bultos: pkg.boxes.slice(1).map(function ( b ) {
						return { name: b.ref, height: b.height, width: b.width, depth: b.length, weight: b.weight };
					})
				};
			}

			if ( pkg ) {
				return { weight: num('input[name="_weight"]'), length: pkg.length, width: pkg.width, height: pkg.height, bultos: [] };
			}

			return {
				weight: num('input[name="_weight"]'),
				length: num('input[name="_length"]'),
				width: num('input[name="_width"]'),
				height: num('input[name="_height"]'),
				bultos: bultos()
			};
		}

		function manualPackage() {
			var own = {
				length: num('input[name="_length"]'),
				width: num('input[name="_width"]'),
				height: num('input[name="_height"]'),
				weight: num('input[name="_weight"]')
			};

			if ( currentMode() !== MODE_MULTIBULTO ) {
				return own.length > 0 && own.width > 0 && own.height > 0 ? { type: 'caja', length: own.length, width: own.width, height: own.height } : null;
			}

			var boxes = [ $.extend( { ref: $.trim( $('input[name="andreani_main_box_ref"]').val() ) }, own ) ].concat( bultos().map(function ( b ) {
				return { ref: b.name, length: b.depth, width: b.width, height: b.height, weight: b.weight };
			}) );
			var complete = boxes.every(function ( b ) {
				return b.length > 0 && b.width > 0 && b.height > 0 && b.weight > 0;
			});

			return complete && boxes.length >= 2 ? { type: 'juego', boxes: boxes } : null;
		}

		function num( selector ) {
			return parseFloat( String( $( selector ).val() || '' ).replace( ',', '.' ) ) || 0;
		}

		function round2( value ) {
			return Math.round( ( parseFloat( value ) || 0 ) * 100 ) / 100;
		}

		function rowValue( $row, field ) {
			return parseFloat( $row.find('input[name="andreani_bulto_' + field + '[]"]').val() ) || 0;
		}

		function unitsValue() {
			return parseInt( $units.val(), 10 ) || 1;
		}

		function overflowValue() {
			return $section.find( OVERFLOW_INPUTS ).val() || 'inherit';
		}

		function capacityState() {
			return {
				mode: currentMode(),
				overflow: overflowValue()
			};
		}

		function apiladoConfig() {
			var maxUnits = parseInt( $('#andreani-apilado-max-units').val(), 10 ) || 0;
			var incH = parseFloat( $('#andreani-apilado-inc-height').val() ) || 0;
			var incW = parseFloat( $('#andreani-apilado-inc-width').val() ) || 0;
			var incD = parseFloat( $('#andreani-apilado-inc-depth').val() ) || 0;

			if ( maxUnits < 2 || incH < 0 || incW < 0 || incD < 0 || ( incH === 0 && incW === 0 && incD === 0 ) ) {
				return null;
			}

			return { maxUnits: maxUnits, incH: incH, incW: incW, incD: incD };
		}

		function bultoFilled( $row ) {
			return [ 'weight', 'width', 'height', 'depth' ].filter(function ( field ) {
				return rowValue( $row, field ) > 0;
			}).length;
		}

		function partialRows() {
			return $list.find('.andreani-bulto-row').filter(function () {
				var filled = bultoFilled( $(this) );

				return filled > 0 && filled < 4;
			});
		}

		function sortedDims( a, b, c ) {
			return [ round2( a ), round2( b ), round2( c ) ].sort(function ( x, y ) { return x - y; }).join('|');
		}

		function bultos() {
			var rows = [];

			$list.find('.andreani-bulto-row').each(function () {
				var $row = $(this);

				rows.push({
					name: '',
					height: rowValue( $row, 'height' ),
					width: rowValue( $row, 'width' ),
					depth: rowValue( $row, 'depth' ),
					weight: rowValue( $row, 'weight' )
				});
			});

			return rows;
		}

		picker = window.AndreaniPackagePicker.create({
			root: $section.find('[data-andr="package"]'),
			scope: $section,
			getMode: currentMode,
			getManual: manualPackage,
			getSize: function () {
				return {
					length: num( 'input[name="_length"]' ),
					width: num( 'input[name="_width"]' ),
					height: num( 'input[name="_height"]' )
				};
			},
			onChange: function () {
				syncMode();
			}
		});

		var preview = Preview.createPreview({
			root: $section.get(0),
			ajaxUrl: config.ajax_url,
			nonce: config.nonce_preview,
			hint: function () {
				return currentMode() === MODE_MULTIBULTO && packageMode() !== 'juego' ? Preview.ignoredHint( bultos() ) : '';
			},
			blocked: function () {
				if ( apiladoIsInvalid() ) {
					return i18n.stack_incomplete || '';
				}

				return currentMode() === MODE_MULTIBULTO && packageMode() !== 'juego' ? Preview.incompleteText( bultos() ) : '';
			},
			onResult: function ( bigger ) {
				Preview.applyBadge( $status, { missing: false, reason: bigger }, {
					ok: i18n.box_status_ok,
					bigger: i18n.box_status_bigger,
					missing: i18n.box_status_missing
				});
			},
			getDraft: function () {
				var mode = currentMode();
				var apilado = mode === MODE_APILADO ? apiladoConfig() : null;

				return {
					weight: num('input[name="_weight"]'),
					length: num('input[name="_length"]'),
					width: num('input[name="_width"]'),
					height: num('input[name="_height"]'),
						dispatch_mode: mode,
						box_ref: picker ? picker.value() : '',
						units_per_box: mode === MODE_SINGLE ? $units.val() : 1,
						overflow: overflowValue(),
					bultos_json: JSON.stringify( mode === MODE_MULTIBULTO && packageMode() !== 'juego' ? Preview.completeBultos( bultos() ) : [] ),
					apilado_json: JSON.stringify( apilado ? {
						maxStackableUnits: apilado.maxUnits,
						unitIncrementHeight: apilado.incH,
						unitIncrementWidth: apilado.incW,
						unitIncrementDepth: apilado.incD
					} : {} )
				};
			}
		});

		function updateMain() {
			var eff = effective();
			var length = round2( eff.length );
			var width = round2( eff.width );
			var height = round2( eff.height );
			var weight = round2( eff.weight );

			$mainTitle.text( currentMode() === MODE_MULTIBULTO ? i18n.piece_title + ' 1' : i18n.box_single );
			$('#andreani-despacho-main-ref').prop('hidden', currentMode() !== MODE_MULTIBULTO);
			var cells = {
				length: [ length, $main.attr('data-dim-unit') ],
				width: [ width, $main.attr('data-dim-unit') ],
				height: [ height, $main.attr('data-dim-unit') ],
				weight: [ weight, $main.attr('data-weight-unit') ]
			};

			$main.find('.andr-box__cell').each(function () {
				var cell = cells[ $(this).attr('data-cell') ];

				$(this).empty()
					.append( $('<span>').text( cell[0] > 0 ? cell[0] : '\u2014' ) )
					.append( $('<span class="andr-box__cell-unit">').text( cell[0] > 0 ? cell[1] : '' ) );
			});
		}

		function discardedConfig() {
			var mode = currentMode();
			var apilado = mode !== MODE_APILADO && !! apiladoConfig();
			var cajas = mode !== MODE_MULTIBULTO && bultos().some(function ( b ) {
				return b.weight > 0 || b.width > 0 || b.height > 0 || b.depth > 0;
			});

			if ( apilado && cajas ) {
				return 'both';
			}

			if ( apilado ) {
				return 'apilado';
			}

			return cajas ? 'bultos' : '';
		}

		function renderDiscard( confirming ) {
			var discarded = discardedConfig();
			var $text = $('#andreani-despacho-discard-text');

			$('#andreani-despacho-discard').prop('hidden', ! discarded);
			$('#andreani-despacho-discard-actions').prop('hidden', ! ( discarded && confirming ));
			$text.text( discarded ? $text.attr( 'data-' + ( confirming ? 'confirm-' : 'hint-' ) + discarded ) : '' );
		}

		function updateStatus() {
			var mode = currentMode();

			renderDiscard( false );
			updateMain();

			var eff = effective();

			Preview.applyBadge( $status, Preview.evaluateProduct({
				weight: eff.weight,
				length: eff.length,
				width: eff.width,
				height: eff.height,
				mode: mode,
				apilado: mode === MODE_APILADO ? apiladoConfig() : null,
					bultos: mode === MODE_MULTIBULTO ? eff.bultos : [],
					unitsPerBox: mode === MODE_SINGLE ? unitsValue() : 1
			}), {
				ok: i18n.box_status_ok,
				bigger: i18n.box_status_bigger,
				missing: i18n.box_status_missing
			});

				Preview.syncCapacity( $section, capacityState() );

				if ( ! $body.prop('hidden') ) {
					preview.refresh();
			}
		}

		function setOpen( open, persist ) {
			$toggle.attr('aria-expanded', open ? 'true' : 'false');
			$body.prop('hidden', ! open);

			if ( open ) {
				preview.refresh();
			}

			if ( persist ) {
				$.post( config.ajax_url, {
					action: 'andreani_dispatch_box_open',
					nonce: config.nonce_open,
					open: open ? 1 : 0
				});
			}
		}

		function reindex() {
			$list.find('.andreani-bulto-row').each(function (i) {
				$(this).attr('data-index', i);
				$(this).find('.andreani-bulto-label').text( i18n.piece_title + ' ' + (i + 2) );
			});
		}

		function updateSameDimsWarnings() {
			if ( packageMode() ) {
				$list.find('.andreani-bulto-warning').hide();
				return;
			}

			var width = round2( num('input[name="_width"]') );
			var height = round2( num('input[name="_height"]') );
			var depth = round2( num('input[name="_length"]') );
			var hasPrincipal = width > 0 && height > 0 && depth > 0;

			$list.find('.andreani-bulto-row').each(function () {
				var $row = $(this);
				var same = hasPrincipal
					&& sortedDims(
						$row.find('input[name="andreani_bulto_height[]"]').val(),
						$row.find('input[name="andreani_bulto_width[]"]').val(),
						$row.find('input[name="andreani_bulto_depth[]"]').val()
					) === sortedDims( height, width, depth );

				$row.find('.andreani-bulto-warning').toggle( !! same );
			});
		}

		function apiladoIsInvalid() {
			return currentMode() === MODE_APILADO && ! apiladoConfig();
		}

		function multibultoIsInvalid() {
			if ( currentMode() !== MODE_MULTIBULTO || packageMode() === 'juego' ) {
				return false;
			}

			return partialRows().length > 0 || ! bultos().some(function ( b ) {
				return b.weight > 0 && b.width > 0 && b.height > 0 && b.depth > 0;
			});
		}

		function syncMode() {
			var mode = currentMode();

			picker.refresh();

			$multibultoPanel.prop('hidden', mode !== MODE_MULTIBULTO);
			$apiladoInvalid.hide();
			$bultosInvalid.hide();

			updateSameDimsWarnings();
			updateStatus();
		}

		function addRow() {
			if ( ! tmpl ) {
				return;
			}

			var count = $list.find('.andreani-bulto-row').length;
			$list.append( tmpl({ index: count, number: count + 2 }) );
		}

		$toggle.on('click', function () {
			setOpen( !! $body.prop('hidden'), true );
		});

		$modeInput.on('change', function () {
			if ( currentMode() === MODE_MULTIBULTO && ! $list.find('.andreani-bulto-row').length ) {
				addRow();
			}

			syncMode();
		});

		$('#andreani-add-bulto').on('click', function () {
			addRow();
			updateStatus();

			var grid = $list.closest('.andr-boxes__grid').get(0);
			var first = $list.find('.andreani-bulto-row').last().find('input').get(0);

			grid.scrollTop = grid.scrollHeight;
			if ( first ) {
				first.focus({ preventScroll: true });
			}
		});

		$(document).on('click.andreaniBultos', '.andreani-despacho-section .andreani-remove-bulto', function () {
			$(this).closest('.andreani-bulto-row').remove();
			reindex();
			updateSameDimsWarnings();
			updateStatus();
		});

		$(document).on('click.andreaniBultos', '.andreani-despacho-section .andreani-switch-to-apilado', function () {
			$list.empty();
			$modeInput.val(MODE_APILADO).trigger('change');
		});

		$(document).on('input.andreaniBultos change.andreaniBultos',
			WC_INPUTS_SELECTOR + ', ' + BULTO_INPUTS_SELECTOR,
			function () {
				$bultosInvalid.hide();
					picker.refresh();
					$(this).closest('.andreani-bulto-row').find('.andreani-bulto-incomplete').hide();
				updateSameDimsWarnings();
				updateStatus();
			});

			$(document).on('input.andreaniCapacity change.andreaniCapacity',
				'#andreani-despacho-units, ' + OVERFLOW_INPUTS,
				updateStatus);

			$(document).on('input.andreaniApilado change.andreaniApilado',
			'#andreani-despacho-panel-apilado input',
			function () {
				$apiladoInvalid.toggle( apiladoIsInvalid() );
				updateStatus();
			});

		// El apilado inválido no puede quedar en silencio: sin esto WordPress
		// guarda el POST y la config se descarta después, sin avisar.
		var discardConfirmed = false;
		var submitter = null;

		function releaseButtons() {
			$('#publish, #save-post, .button-primary').removeClass('disabled button-primary-disabled').prop('disabled', false);
			$('.spinner').removeClass('is-active');
		}

		$('#andreani-despacho-discard-go').on('click', function () {
			discardConfirmed = true;
			( submitter || $('#publish').get(0) ).click();
		});

		$('#andreani-despacho-discard-cancel').on('click', function () {
			renderDiscard( false );
		});

		$('form#post').on('submit', function ( event ) {
			var apiladoInvalid = apiladoIsInvalid();
			var multibultoInvalid = multibultoIsInvalid();

			if ( ! apiladoInvalid && ! multibultoInvalid ) {
				if ( discardConfirmed || ! discardedConfig() ) {
					discardConfirmed = false;
					return;
				}

				event.preventDefault();
				submitter = event.originalEvent && event.originalEvent.submitter;

				if ( $body.prop('hidden') ) {
					setOpen( true, false );
				}

				renderDiscard( true );
				$('#andreani-despacho-discard-go').get(0).scrollIntoView({ block: 'nearest' });
				releaseButtons();
				return;
			}

			event.preventDefault();

			if ( $body.prop('hidden') ) {
				setOpen( true, false );
			}

			if ( apiladoInvalid ) {
				$apiladoInvalid.show();
				$('#andreani-apilado-max-units').focus();
			} else if ( partialRows().length ) {
				var $partial = partialRows();

				$partial.find('.andreani-bulto-incomplete').show();
				$partial.first().find('input[type="number"]').filter(function () {
					return ! ( parseFloat( $(this).val() ) > 0 );
				}).first().focus();
			} else {
				$bultosInvalid.show();
				$list.find('.andreani-bulto-row').first().find('input').first().focus();
			}

			releaseButtons();
		});

		syncMode();
	});

})(jQuery);
