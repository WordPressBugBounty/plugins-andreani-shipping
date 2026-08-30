/**
 * Andreani — Panel de bultos adicionales.
 *
 * Evalúa los totales combinados (peso sumado, mayor suma de lados,
 * mayor lado) de todos los bultos para determinar si califica como Bigger.
 * Usa delegación de eventos para sobrevivir a redibujos del DOM de WC.
 */
(function ($) {
	'use strict';

	var WC_INPUTS_SELECTOR = 'input[name="_weight"], input[name="_width"], input[name="_height"], input[name="_length"]';
	var BULTO_INPUTS_SELECTOR = '.andreani-bulto-row input[type="number"]';

	$(function () {
		var $section = $('.andreani-bultos-section');

		if ( ! $section.length ) {
			return;
		}

		var config = window.AndreaniBultosConfig || {};
		var thresholds = config.thresholds || { weight: 50, sum_sides: 300, max_side: 165 };
		var i18n = config.i18n || { badge_bigger: 'Bigger', badge_paquete: 'Paquete común' };

		var $toggle  = $('#andreani-bultos-toggle');
		var $content = $('#andreani-bultos-content');
		var $list    = $('#andreani-bultos-list');
		var $badge   = $section.find('.andreani-bultos-badge');

		var tmpl = null;
		if ( typeof window.wp !== 'undefined' && window.wp.template ) {
			try {
				tmpl = window.wp.template('andreani-bulto-row');
			} catch (e) {
				tmpl = null;
			}
		}

		function evaluateBigger() {
			var totalWeight = parseFloat( $('input[name="_weight"]').val() ) || 0;
			var maxSumSides = ( parseFloat( $('input[name="_width"]').val() ) || 0 )
			                + ( parseFloat( $('input[name="_height"]').val() ) || 0 )
			                + ( parseFloat( $('input[name="_length"]').val() ) || 0 );
			var maxSide     = Math.max(
				parseFloat( $('input[name="_width"]').val() ) || 0,
				parseFloat( $('input[name="_height"]').val() ) || 0,
				parseFloat( $('input[name="_length"]').val() ) || 0
			);

			$list.find('.andreani-bulto-row').each(function () {
				var $row = $(this);
				var bW = parseFloat( $row.find('input[name="andreani_bulto_weight[]"]').val() ) || 0;
				var bX = parseFloat( $row.find('input[name="andreani_bulto_width[]"]').val() )  || 0;
				var bY = parseFloat( $row.find('input[name="andreani_bulto_height[]"]').val() ) || 0;
				var bZ = parseFloat( $row.find('input[name="andreani_bulto_length[]"]').val() ) || 0;

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

			return totalWeight > thresholds.weight
				|| maxSumSides > thresholds.sum_sides
				|| maxSide > thresholds.max_side;
		}

		function updateBadge() {
			var isBigger = evaluateBigger();
			$badge
				.text( isBigger ? i18n.badge_bigger : i18n.badge_paquete )
				.toggleClass( 'andreani-bultos-badge--bigger', isBigger )
				.toggleClass( 'andreani-bultos-badge--regular', ! isBigger );
		}

		// Escuchar inputs de WC (delegación para sobrevivir a redibujos)
		$(document)
			.on('input.andreaniBultos change.andreaniBultos',
				WC_INPUTS_SELECTOR,
				updateBadge);

		// Escuchar inputs de bultos adicionales (delegación para filas dinámicas)
		$(document)
			.on('input.andreaniBultos change.andreaniBultos',
				BULTO_INPUTS_SELECTOR,
				updateBadge);

		$toggle.on('change', function () {
			if (this.checked) {
				$content.addClass('active');
			} else {
				$content.removeClass('active');
				$list.empty();
				updateBadge();
			}
		});

		function reindex() {
			$list.find('.andreani-bulto-row').each(function (i) {
				$(this).attr('data-index', i);
				$(this).find('.andreani-bulto-label').text('Bulto ' + (i + 1));
			});
		}

		$('#andreani-add-bulto').on('click', function () {
			if ( ! tmpl ) {
				return;
			}
			var count = $list.find('.andreani-bulto-row').length;
			var html = tmpl({ index: count, number: count + 1 });
			$list.append(html);
		});

		$(document).on('click.andreaniBultos', '.andreani-bultos-section .andreani-remove-bulto', function () {
			$(this).closest('.andreani-bulto-row').remove();
			reindex();
			updateBadge();

			if ($list.find('.andreani-bulto-row').length === 0) {
				$toggle.prop('checked', false).trigger('change');
			}
		});

		updateBadge();
	});

})(jQuery);
