<?php
/**
 * Partial: Modal de "Configuración de impresión" de etiquetas, atajo desde la grilla de envíos.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'ANDREANI_PRINT_SETTINGS_MODAL_RENDERED' ) ) {
	return;
}
define( 'ANDREANI_PRINT_SETTINGS_MODAL_RENDERED', true );
?>
<div id="andreani-print-settings-modal" class="andr-modal andreani-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="andreani-print-settings-modal-title">
	<div class="andr-modal__backdrop andreani-modal__backdrop"></div>
	<div class="andr-modal__container andr-modal__container--wide andreani-modal__container">
		<div class="andr-modal__header andreani-modal__header" data-draggable="true">
			<svg class="andr-modal__logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
			<h3 id="andreani-print-settings-modal-title" class="andr-modal__title"><?php esc_html_e( 'Configuración de impresión', 'andreani-shipping' ); ?></h3>
			<button type="button" class="andr-modal__close andreani-modal__close" aria-label="<?php esc_attr_e( 'Cerrar', 'andreani-shipping' ); ?>">&times;</button>
		</div>
		<div class="andr-modal__body andreani-modal__body">
			<?php require ANDREANI_PLUGIN_DIR . 'includes/admin/views/print-settings-form.php'; ?>
		</div>
		<div class="andr-modal__footer andreani-modal__footer">
			<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-modal__close"><?php esc_html_e( 'Cancelar', 'andreani-shipping' ); ?></button>
			<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-print-settings-save" disabled><?php esc_html_e( 'Guardar', 'andreani-shipping' ); ?></button>
		</div>
	</div>
</div>
