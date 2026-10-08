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

		var $modeInputs = $section.find('.andr-seg__input');
		var $status = $('#andreani-despacho-status');
		var $main = $('#andreani-despacho-main');
		var $mainTitle = $('#andreani-despacho-main-title');
		var $mainSummary = $('#andreani-despacho-main-summary');
		var $toggle = $('#andreani-despacho-toggle');
		var $body = $('#andreani-despacho-body');
		var $list = $('#andreani-bultos-list');
		var $apiladoPanel = $('#andreani-despacho-panel-apilado');
		var $multibultoPanel = $('#andreani-despacho-panel-multibulto');
		var $apiladoInvalid = $('#andreani-apilado-invalid');
		var $bultosInvalid = $('#andreani-bultos-invalid');

		var tmpl = null;
		if ( typeof window.wp !== 'undefined' && window.wp.template ) {
			try {
				tmpl = window.wp.template('andreani-bulto-row');
			} catch (e) {
				tmpl = null;
			}
		}

		function currentMode() {
			var mode = $modeInputs.filter(':checked').val();
			return mode === MODE_APILADO || mode === MODE_MULTIBULTO ? mode : MODE_SINGLE;
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

		var preview = Preview.createPreview({
			root: $section.get(0),
			ajaxUrl: config.ajax_url,
			nonce: config.nonce_preview,
			hint: function () {
				return currentMode() === MODE_MULTIBULTO ? Preview.ignoredHint( bultos() ) : '';
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
					bultos_json: JSON.stringify( mode === MODE_MULTIBULTO ? Preview.completeBultos( bultos() ) : [] ),
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
			var length = round2( num('input[name="_length"]') );
			var width = round2( num('input[name="_width"]') );
			var height = round2( num('input[name="_height"]') );
			var weight = round2( num('input[name="_weight"]') );
			var complete = length > 0 && width > 0 && height > 0 && weight > 0;

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

			$mainSummary.text( complete
				? [ length, width, height ].join(' × ') + ' ' + $main.attr('data-dim-unit') + ' · ' + weight + ' ' + $main.attr('data-weight-unit')
				: i18n.box_main_empty );
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

			Preview.applyBadge( $status, Preview.evaluateProduct({
				weight: num('input[name="_weight"]'),
				length: num('input[name="_length"]'),
				width: num('input[name="_width"]'),
				height: num('input[name="_height"]'),
				mode: mode,
				apilado: mode === MODE_APILADO ? apiladoConfig() : null,
				bultos: mode === MODE_MULTIBULTO ? bultos() : []
			}), {
				ok: i18n.box_status_ok,
				bigger: i18n.box_status_bigger,
				missing: i18n.box_status_missing
			});

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
			if ( currentMode() !== MODE_MULTIBULTO ) {
				return false;
			}

			return partialRows().length > 0 || ! bultos().some(function ( b ) {
				return b.weight > 0 && b.width > 0 && b.height > 0 && b.depth > 0;
			});
		}

		function syncMode() {
			var mode = currentMode();

			$apiladoPanel.prop('hidden', mode !== MODE_APILADO);
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

		$modeInputs.on('change', function () {
			if ( currentMode() === MODE_MULTIBULTO && ! $list.find('.andreani-bulto-row').length ) {
				addRow();
			}

			if ( currentMode() === MODE_APILADO ) {
				var $maxUnits = $('#andreani-apilado-max-units');
				if ( ! $maxUnits.val() ) {
					$maxUnits.val( $maxUnits.attr('min') );
				}
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
			$('#andreani-despacho-mode-apilado').prop('checked', true).trigger('change');
		});

		$(document).on('input.andreaniBultos change.andreaniBultos',
			WC_INPUTS_SELECTOR + ', ' + BULTO_INPUTS_SELECTOR,
			function () {
				$bultosInvalid.hide();
				$(this).closest('.andreani-bulto-row').find('.andreani-bulto-incomplete').hide();
				updateSameDimsWarnings();
				updateStatus();
			});

		$(document).on('input.andreaniApilado change.andreaniApilado',
			'#andreani-despacho-panel-apilado input[type="number"]',
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
