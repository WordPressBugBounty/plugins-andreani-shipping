/**
 * Andreani — Panel de modo de despacho en la ficha de producto.
 *
 * Un solo paquete, varias unidades apiladas en un bulto, o una unidad repartida
 * en varias piezas: el modo elegido decide qué se evalúa y qué se guarda.
 * Usa delegación de eventos para sobrevivir a redibujos del DOM de WC.
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
		var thresholds = config.thresholds || { weight: 50, sum_sides: 300, max_side: 165 };
		var canonical = config.thresholds_canonical || { weight: 50, sum_sides: 300, max_side: 165 };
		var i18n = config.i18n || {};

		var cmFactor = config.cm_factor || 1;
		var kgFactor = config.kg_factor || 1;

		var $modeInputs = $section.find('.andreani-despacho-option__input');
		var $status = $('#andreani-despacho-status');
		var $list = $('#andreani-bultos-list');
		var $apiladoPanel = $('#andreani-despacho-panel-apilado');
		var $multibultoPanel = $('#andreani-despacho-panel-multibulto');
		var $apiladoInvalid = $('#andreani-apilado-invalid');
		var $bultosInvalid = $('#andreani-bultos-invalid');
		var $previewBody = $('#andreani-despacho-preview-body');
		var previewTimer = null;
		var previewRequest = 0;

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

		function format( value ) {
			return String( round2( value ) );
		}

		function toCm( value ) {
			return cmFactor ? value / cmFactor : value;
		}

		function toKg( value ) {
			return value * kgFactor;
		}

		function fill( template, first, second ) {
			return String( template || '' )
				.replace( '%1$s', first )
				.replace( '%2$s', second );
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

		function evaluateBigger() {
			var mode = currentMode();
			var weight = num('input[name="_weight"]');
			var width = num('input[name="_width"]');
			var height = num('input[name="_height"]');
			var length = num('input[name="_length"]');

			var totalWeight = weight;
			var maxSumSides = width + height + length;
			var maxSide = Math.max( width, height, length );

			if ( mode === MODE_APILADO ) {
				var apilado = apiladoConfig();

				if ( apilado ) {
					var extra = apilado.maxUnits - 1;
					var pilaW = width + apilado.incW * cmFactor * extra;
					var pilaH = height + apilado.incH * cmFactor * extra;
					var pilaL = length + apilado.incD * cmFactor * extra;

					totalWeight = weight * apilado.maxUnits;
					maxSumSides = pilaW + pilaH + pilaL;
					maxSide = Math.max( pilaW, pilaH, pilaL );
				}
			}

			if ( mode === MODE_MULTIBULTO ) {
				$list.find('.andreani-bulto-row').each(function () {
					var $row = $(this);
					var bW = parseFloat( $row.find('input[name="andreani_bulto_weight[]"]').val() ) || 0;
					var bX = parseFloat( $row.find('input[name="andreani_bulto_width[]"]').val() ) || 0;
					var bY = parseFloat( $row.find('input[name="andreani_bulto_height[]"]').val() ) || 0;
					var bZ = parseFloat( $row.find('input[name="andreani_bulto_depth[]"]').val() ) || 0;

					totalWeight += bW;

					var bultoSumSides = bX + bY + bZ;
					if ( bultoSumSides > maxSumSides ) {
						maxSumSides = bultoSumSides;
					}

					var bultoMaxSide = Math.max( bX, bY, bZ );
					if ( bultoMaxSide > maxSide ) {
						maxSide = bultoMaxSide;
					}
				});
			}

			if ( totalWeight > thresholds.weight ) {
				return { isBigger: true, text: fill( i18n.bigger_reason_weight, format( toKg( totalWeight ) ), format( canonical.weight ) ) };
			}

			if ( maxSumSides > thresholds.sum_sides ) {
				return { isBigger: true, text: fill( i18n.bigger_reason_sum_sides, format( toCm( maxSumSides ) ), format( canonical.sum_sides ) ) };
			}

			if ( maxSide > thresholds.max_side ) {
				return { isBigger: true, text: fill( i18n.bigger_reason_max_side, format( toCm( maxSide ) ), format( canonical.max_side ) ) };
			}

			return { isBigger: false, text: '' };
		}

		function previewDraft() {
			var mode = currentMode();
			var apilado = mode === MODE_APILADO ? apiladoConfig() : null;
			var bultos = [];

			if ( mode === MODE_MULTIBULTO ) {
				$list.find('.andreani-bulto-row').each(function () {
					var $row = $(this);

					bultos.push({
						name: '',
						height: parseFloat( $row.find('input[name="andreani_bulto_height[]"]').val() ) || 0,
						width: parseFloat( $row.find('input[name="andreani_bulto_width[]"]').val() ) || 0,
						depth: parseFloat( $row.find('input[name="andreani_bulto_depth[]"]').val() ) || 0,
						weight: parseFloat( $row.find('input[name="andreani_bulto_weight[]"]').val() ) || 0
					});
				});
			}

			return {
				action: 'andreani_preview_bultos',
				nonce: config.nonce_preview,
				weight: num('input[name="_weight"]'),
				length: num('input[name="_length"]'),
				width: num('input[name="_width"]'),
				height: num('input[name="_height"]'),
				dispatch_mode: mode,
				bultos_json: JSON.stringify( bultos ),
				apilado_json: JSON.stringify( apilado ? {
					maxStackableUnits: apilado.maxUnits,
					unitIncrementHeight: apilado.incH,
					unitIncrementWidth: apilado.incW,
					unitIncrementDepth: apilado.incD
				} : {} )
			};
		}

		function schedulePreview() {
			if ( ! $previewBody.length || ! config.ajax_url ) {
				return;
			}

			clearTimeout( previewTimer );

			previewTimer = setTimeout(function () {
				var request = ++previewRequest;

				$.post( config.ajax_url, previewDraft() ).done(function ( res ) {
					if ( request === previewRequest && res && res.success && res.data ) {
						$previewBody.html( res.data.html );
					}
				});
			}, 300 );
		}

		function updateStatus() {
			schedulePreview();

			var evaluation = evaluateBigger();
			var text = evaluation.isBigger
				? String( i18n.bigger_prefix || '%s' ).replace( '%s', evaluation.text )
				: ( i18n.bigger_regular || '' );

			$status
				.text( text )
				.toggleClass( 'andreani-despacho-status--bigger', evaluation.isBigger )
				.toggleClass( 'andreani-despacho-status--regular', ! evaluation.isBigger );
		}

		function reindex() {
			$list.find('.andreani-bulto-row').each(function (i) {
				$(this).attr('data-index', i);
				$(this).find('.andreani-bulto-label').text('Bulto ' + (i + 2));
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
					&& round2( $row.find('input[name="andreani_bulto_height[]"]').val() ) === height
					&& round2( $row.find('input[name="andreani_bulto_width[]"]').val() ) === width
					&& round2( $row.find('input[name="andreani_bulto_depth[]"]').val() ) === depth;

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

			var hasComplete = false;

			$list.find('.andreani-bulto-row').each(function () {
				var $row = $(this);
				var complete = [ 'weight', 'width', 'height', 'depth' ].every(function ( field ) {
					return ( parseFloat( $row.find('input[name="andreani_bulto_' + field + '[]"]').val() ) || 0 ) > 0;
				});

				if ( complete ) {
					hasComplete = true;
				}
			});

			return ! hasComplete;
		}

		function syncMode() {
			var mode = currentMode();

			$apiladoPanel.toggleClass( 'active', mode === MODE_APILADO );
			$multibultoPanel.toggleClass( 'active', mode === MODE_MULTIBULTO );
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

		$modeInputs.on('change', function () {
			if ( currentMode() !== MODE_MULTIBULTO ) {
				$list.empty();
			}

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

		$('#andreani-add-bulto').on('click', addRow);

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
		$('form#post').on('submit', function ( event ) {
			var apiladoInvalid = apiladoIsInvalid();
			var multibultoInvalid = multibultoIsInvalid();

			if ( ! apiladoInvalid && ! multibultoInvalid ) {
				return;
			}

			event.preventDefault();

			if ( apiladoInvalid ) {
				$apiladoInvalid.show();
				$('#andreani-apilado-max-units').focus();
			} else {
				$bultosInvalid.show();
				$list.find('.andreani-bulto-row').first().find('input').first().focus();
			}

			$('#publish, #save-post, .button-primary').removeClass('disabled button-primary-disabled').prop('disabled', false);
			$('.spinner').removeClass('is-active');
		});

		syncMode();
	});

})(jQuery);
