<?php
/**
 * Partial: Formulario de formato de impresión de etiquetas.
 *
 * Lo comparten el modal de la grilla de envíos y la pestaña Etiquetas de la configuración.
 * Al cargar hace GET para marcar el formato actual; al guardar hace PATCH (admin.js).
 * El default lo resuelve la API (key=1) cuando la cuenta nunca guardó configuración.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

$andreani_print_formats = array(
	1 => array(
		'label'   => __( 'Hoja A4 (1 etiqueta por hoja)', 'andreani-shipping' ),
		'desc'    => __( 'Una etiqueta centrada en una hoja A4.', 'andreani-shipping' ),
		'shape'   => 'a4-1',
		'caption' => __( 'Una etiqueta por hoja A4', 'andreani-shipping' ),
	),
	2 => array(
		'label'   => __( 'Hoja A4 (4 etiquetas por hoja)', 'andreani-shipping' ),
		'desc'    => __( 'Cuatro etiquetas distribuidas en una hoja A4.', 'andreani-shipping' ),
		'shape'   => 'a4-4',
		'caption' => __( 'Cuatro etiquetas por hoja A4', 'andreani-shipping' ),
	),
	3 => array(
		'label'   => __( 'Zebra (10x15)', 'andreani-shipping' ),
		'desc'    => __( 'Formato para impresoras térmicas Zebra de 10x15 cm.', 'andreani-shipping' ),
		'shape'   => 'zebra',
		'caption' => __( 'Etiqueta térmica 10×15', 'andreani-shipping' ),
	),
);
?>
<div class="andreani-print-settings" data-print-settings>
	<p class="andr-modal__hint andreani-print-settings__intro"><?php esc_html_e( 'Elegí el formato en el que querés imprimir las etiquetas de tus envíos. Se aplica a toda la cuenta.', 'andreani-shipping' ); ?></p>

	<div class="andreani-print-settings__loader" data-print-loader></div>

	<div class="andreani-print-settings__layout" data-print-options hidden>
		<fieldset class="andreani-print-settings__options">
			<legend class="screen-reader-text"><?php esc_html_e( 'Formato de impresión', 'andreani-shipping' ); ?></legend>
			<?php foreach ( $andreani_print_formats as $key => $format ) : ?>
				<label class="andreani-print-option">
					<input type="radio" name="andreani_print_format" value="<?php echo esc_attr( $key ); ?>" class="andreani-print-option__radio" data-preview-shape="<?php echo esc_attr( $format['shape'] ); ?>" data-preview-caption="<?php echo esc_attr( $format['caption'] ); ?>" />
					<span class="andreani-print-option__content">
						<span class="andreani-print-option__label"><?php echo esc_html( $format['label'] ); ?></span>
						<span class="andreani-print-option__desc"><?php echo esc_html( $format['desc'] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<div class="andreani-print-preview" aria-hidden="true">
			<div class="andreani-print-preview__canvas" data-print-preview></div>
			<span class="andreani-print-preview__caption" data-print-preview-caption></span>
		</div>
	</div>
</div>
