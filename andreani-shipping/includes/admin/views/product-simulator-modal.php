<?php
/**
 * Partial: Modal simulador de carrito.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;
?>
<div id="andreani-cart-sim-modal" class="andr-modal andreani-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="andreani-cart-sim-title">
	<div class="andr-modal__backdrop andreani-modal__backdrop"></div>
	<div class="andr-modal__container andreani-modal__container">
		<div class="andr-modal__header andreani-modal__header" data-draggable="true">
			<svg class="andr-modal__logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
			<h3 id="andreani-cart-sim-title" class="andr-modal__title"><?php esc_html_e( 'Simular un carrito', 'andreani-shipping' ); ?></h3>
			<button type="button" class="andr-modal__close andreani-modal__close" aria-label="<?php esc_attr_e( 'Cerrar', 'andreani-shipping' ); ?>">&times;</button>
		</div>
		<div class="andr-modal__body andreani-modal__body">
			<div class="andreani-sim">
				<div class="andreani-sim__cart">
					<div class="andreani-sim__search">
						<input type="search" id="andreani-sim-search" class="andreani-search-input" autocomplete="off" placeholder="<?php esc_attr_e( 'Agregar un producto…', 'andreani-shipping' ); ?>" />
						<div id="andreani-sim-results" class="andreani-sim__results" hidden></div>
					</div>

					<div id="andreani-sim-lines" class="andreani-sim__lines"></div>
					<p id="andreani-sim-empty" class="andr-dispatch__message"><?php esc_html_e( 'Buscá y sumá productos para ver cómo viajan juntos.', 'andreani-shipping' ); ?></p>

					<div class="andreani-quote-tester">
						<label class="andreani-quote-tester__field">
							<span class="andreani-quote-tester__label"><?php esc_html_e( 'CP destino', 'andreani-shipping' ); ?></span>
							<input type="text" id="andreani-sim-cp" class="regular-text" maxlength="10" placeholder="<?php esc_attr_e( 'Ej: 1425', 'andreani-shipping' ); ?>" />
						</label>
						<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-sim-quote"><?php esc_html_e( 'Cotizar', 'andreani-shipping' ); ?></button>
					</div>
					<div id="andreani-sim-rates" class="andreani-quote-results" aria-live="polite" hidden></div>
				</div>

				<div class="andreani-sim__preview">
					<div class="andr-dispatch__stage"><svg id="andreani-sim-stage" role="img" aria-label="<?php esc_attr_e( 'Vista previa del pedido', 'andreani-shipping' ); ?>"></svg></div>
					<div id="andreani-sim-result" class="andr-dispatch__result andr-dispatch__result--empty"></div>
					<p id="andreani-sim-skipped" class="andr-dispatch__message" hidden></p>
				</div>
			</div>
		</div>
	</div>
</div>
