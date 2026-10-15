<?php
/**
 * Template: Página Paquetes guardados
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap andreani-boxes-wrap" data-bx-root>

	<hr class="wp-header-end">

	<div class="andreani-page-card">
		<?php
		$andreani_page_title  = __( 'Paquetes guardados', 'andreani-shipping' );
		$andreani_page_icon   = '<svg class="andr-bx__title-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 8l-9 4-9-4 9-4 9 4z"/><path d="M3 8v8l9 4 9-4V8"/><path d="M12 18s-2.6-1.6-2.6-3.2a1.4 1.4 0 0 1 2.6-.7 1.4 1.4 0 0 1 2.6.7C14.6 16.4 12 18 12 18z"/></svg>';
		require ANDREANI_PLUGIN_DIR . 'includes/admin/views/page-card-header.php';
		?>
		<div class="andreani-page-card__body">
			<div class="andr-bx__bar" data-bx-bar>
				<div class="andr-bx__search" data-bx-search-wrap>
					<input type="search" class="andreani-search-input" data-bx-search placeholder="<?php esc_attr_e( 'Buscar por nombre…', 'andreani-shipping' ); ?>" aria-label="<?php esc_attr_e( 'Buscar por nombre', 'andreani-shipping' ); ?>" />
				</div>
				<button type="button" class="andr-btn andr-btn--secondary andr-bx__add" data-bx-add>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
					<?php esc_html_e( 'Agregar paquete', 'andreani-shipping' ); ?>
				</button>
				<div class="andr-bx__quicks" data-bx-quicks></div>
				<span class="andr-bx__count" data-bx-count></span>
			</div>

			<p class="andr-bx__error" data-bx-error role="alert" hidden></p>

			<div class="andr-bx__list" data-bx-list hidden></div>

			<div class="andr-bx__empty" data-bx-empty hidden>
				<svg class="andr-bx__art" data-andr-art="multi" width="220" height="110" aria-hidden="true"></svg>
				<p><?php esc_html_e( 'Guardá las cajas y sobres que usás y te decimos cuál usar en cada pedido. Es opcional.', 'andreani-shipping' ); ?></p>
				<div class="andr-bx__quicks" data-bx-quicks></div>
			</div>

			<p class="andr-bx__note" data-bx-note hidden></p>
		</div>
	</div>

	<div class="andr-modal andr-bx__modal" data-bx-modal role="dialog" aria-modal="true" aria-labelledby="andr-bx-modal-title" hidden>
		<div class="andr-modal__backdrop" data-bx-close></div>
		<div class="andr-modal__container">
			<div class="andr-modal__header">
				<h3 id="andr-bx-modal-title" class="andr-modal__title" data-bx-modal-title></h3>
				<button type="button" class="andr-modal__close" data-bx-close aria-label="<?php esc_attr_e( 'Cerrar', 'andreani-shipping' ); ?>">&times;</button>
			</div>
			<div class="andr-modal__body">
				<div class="andr-bx__formgrid">
					<div class="andr-bx__formcol" data-bx-form></div>
					<div class="andr-bx__stage">
						<svg data-bx-stage aria-hidden="true"></svg>
					</div>
				</div>
				<p class="andr-bx__error" data-bx-form-error role="alert" hidden></p>
			</div>
			<div class="andr-modal__footer">
				<button type="button" class="andr-btn andr-btn--ghost" data-bx-close><?php esc_html_e( 'Cancelar', 'andreani-shipping' ); ?></button>
				<button type="button" class="andr-btn andr-btn--primary" data-bx-save><?php esc_html_e( 'Guardar', 'andreani-shipping' ); ?></button>
			</div>
		</div>
	</div>
</div>
