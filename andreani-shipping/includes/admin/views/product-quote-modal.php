<?php
/**
 * Partial: Modal probador de cotización de producto.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="andreani-product-quote-modal" class="andr-modal andreani-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="andreani-product-quote-modal-title">
	<div class="andr-modal__backdrop andreani-modal__backdrop"></div>
	<div class="andr-modal__container andreani-modal__container">
		<div class="andr-modal__header andreani-modal__header" data-draggable="true">
			<svg class="andr-modal__logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
			<h3 id="andreani-product-quote-modal-title" class="andr-modal__title"><?php esc_html_e( 'Probar cotización', 'andreani-shipping' ); ?></h3>
			<button type="button" class="andr-modal__close andreani-modal__close" aria-label="<?php esc_attr_e( 'Cerrar', 'andreani-shipping' ); ?>">&times;</button>
		</div>
		<div class="andr-modal__body andreani-modal__body">
			<input type="hidden" id="andreani-quote-product-id" value="" />

			<div class="andreani-edit-product-head">
				<img id="andreani-quote-product-thumb" class="andreani-edit-product-head__thumb" src="" alt="" />
				<span id="andreani-quote-product-name" class="andreani-edit-product-head__name"></span>
			</div>

			<div class="andreani-quote-tester">
				<label class="andreani-quote-tester__field">
					<span class="andreani-quote-tester__label"><?php esc_html_e( 'CP destino', 'andreani-shipping' ); ?></span>
					<input type="text" id="andreani-quote-cp" class="regular-text" maxlength="10" placeholder="<?php esc_attr_e( 'Ej: 1425', 'andreani-shipping' ); ?>" />
				</label>
				<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-quote-submit">
					<?php esc_html_e( 'Cotizar', 'andreani-shipping' ); ?>
				</button>
			</div>

			<div id="andreani-quote-empty" class="andreani-quote-empty">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
				<p><?php esc_html_e( 'Ingresá un código postal y tocá Cotizar para ver las tarifas de envío de este producto.', 'andreani-shipping' ); ?></p>
			</div>

			<div id="andreani-quote-results" class="andreani-quote-results" style="display:none;"></div>
			<div id="andreani-quote-message" class="andreani-products-inline-msg" style="display:none;"></div>

			<div id="andreani-quote-loader" class="andreani-quote-loader" hidden>
				<svg class="andreani-box-anim" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
					<defs>
						<clipPath id="andreani-box-top-clip"><rect x="0" y="0" width="48" height="21" /></clipPath>
					</defs>
					<rect class="andreani-box-anim__item" x="20" y="2" width="8" height="8" rx="1.2" fill="currentColor" stroke="none" clip-path="url(#andreani-box-top-clip)" />
					<g clip-path="url(#andreani-box-top-clip)">
						<path class="andreani-box-anim__lid andreani-box-anim__lid--l" d="M9 20 L24 20 L24 12 L14 12 Z" />
						<path class="andreani-box-anim__lid andreani-box-anim__lid--r" d="M39 20 L24 20 L24 12 L34 12 Z" />
					</g>
					<path d="M9 20 h30 v17 a2 2 0 0 1 -2 2 H11 a2 2 0 0 1 -2 -2 Z" />
				</svg>
				<span class="andreani-quote-loader__text"><?php esc_html_e( 'Cotizando…', 'andreani-shipping' ); ?></span>
			</div>
		</div>
		<div class="andr-modal__footer andreani-modal__footer">
			<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-modal__close"><?php esc_html_e( 'Cerrar', 'andreani-shipping' ); ?></button>
		</div>
	</div>
</div>
