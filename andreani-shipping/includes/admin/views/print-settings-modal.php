<?php
/**
 * Partial: Modal de "Configuración de impresión" de etiquetas.
 *
 * Reutilizable entre la grilla de envíos y la página de configuración del plugin.
 * Al abrir hace GET para marcar el formato actual; al guardar hace PATCH (admin.js).
 * El default lo resuelve la API (key=1) cuando la cuenta nunca guardó configuración.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'ANDREANI_PRINT_SETTINGS_MODAL_RENDERED' ) ) {
	return;
}
define( 'ANDREANI_PRINT_SETTINGS_MODAL_RENDERED', true );

$andreani_print_formats = array(
	1 => array(
		'label' => __( 'Hoja A4 (1 etiqueta por hoja)', 'andreani-shipping' ),
		'desc'  => __( 'Una etiqueta centrada en una hoja A4.', 'andreani-shipping' ),
	),
	2 => array(
		'label' => __( 'Hoja A4 (4 etiquetas por hoja)', 'andreani-shipping' ),
		'desc'  => __( 'Cuatro etiquetas distribuidas en una hoja A4.', 'andreani-shipping' ),
	),
	3 => array(
		'label' => __( 'Zebra (10x15)', 'andreani-shipping' ),
		'desc'  => __( 'Formato para impresoras térmicas Zebra de 10x15 cm.', 'andreani-shipping' ),
	),
);
?>
<div id="andreani-print-settings-modal" class="andr-modal andreani-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="andreani-print-settings-modal-title">
	<div class="andr-modal__backdrop andreani-modal__backdrop"></div>
	<div class="andr-modal__container andreani-modal__container">
		<div class="andr-modal__header andreani-modal__header" data-draggable="true">
			<svg class="andr-modal__logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
			<h3 id="andreani-print-settings-modal-title" class="andr-modal__title"><?php esc_html_e( 'Configuración de impresión', 'andreani-shipping' ); ?></h3>
			<button type="button" class="andr-modal__close andreani-modal__close" aria-label="<?php esc_attr_e( 'Cerrar', 'andreani-shipping' ); ?>">&times;</button>
		</div>
		<div class="andr-modal__body andreani-modal__body">
			<p class="andr-modal__hint andreani-print-settings__intro"><?php esc_html_e( 'Elegí el formato en el que querés imprimir las etiquetas de tus envíos. Se aplica a toda la cuenta.', 'andreani-shipping' ); ?></p>

			<div class="andreani-print-settings__loader" data-print-loader>
				<span class="spinner is-active"></span>
				<span><?php esc_html_e( 'Cargando configuración...', 'andreani-shipping' ); ?></span>
			</div>

			<fieldset class="andreani-print-settings__options" data-print-options hidden>
				<legend class="screen-reader-text"><?php esc_html_e( 'Formato de impresión', 'andreani-shipping' ); ?></legend>
				<?php foreach ( $andreani_print_formats as $key => $format ) : ?>
					<label class="andreani-print-option">
						<input type="radio" name="andreani_print_format" value="<?php echo esc_attr( $key ); ?>" class="andreani-print-option__radio" />
						<span class="andreani-print-option__content">
							<span class="andreani-print-option__label"><?php echo esc_html( $format['label'] ); ?></span>
							<span class="andreani-print-option__desc"><?php echo esc_html( $format['desc'] ); ?></span>
						</span>
					</label>
				<?php endforeach; ?>
			</fieldset>
		</div>
		<div class="andr-modal__footer andreani-modal__footer">
			<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-modal__close"><?php esc_html_e( 'Cancelar', 'andreani-shipping' ); ?></button>
			<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-print-settings-save" disabled><?php esc_html_e( 'Guardar', 'andreani-shipping' ); ?></button>
		</div>
	</div>
</div>
