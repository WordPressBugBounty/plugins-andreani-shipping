<?php
/**
 * Partial: selector de paquete guardado de un producto (caja o juego de cajas).
 * Lo comparten el editor inline de Mis productos y la pestaña Envío.
 *
 * @package AndreaniPlugin
 * @var array $pkg { name?: string, value: string }
 */

defined( 'ABSPATH' ) || exit;

$pkg_strings = Andreani_Product_Bultos::get_ui_strings();
?>
<div class="andr-field andr-pkg" data-andr="package" data-value="<?php echo esc_attr( $pkg['value'] ); ?>">
	<span class="andr-ask__q" data-pkg-label><?php echo esc_html( $pkg_strings['pkg_label'] ); ?></span>
	<div class="andr-pkg__row">
		<svg class="andr-pkg__thumb" data-pkg-thumb viewBox="0 0 48 48" aria-hidden="true" hidden></svg>
		<select class="andr-pkg__select" data-pkg-select<?php echo ! empty( $pkg['name'] ) ? ' name="' . esc_attr( $pkg['name'] ) . '"' : ''; ?> aria-label="<?php echo esc_attr( $pkg_strings['pkg_label'] ); ?>"></select>
		<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm andr-pkg__save" data-pkg-save><?php echo esc_html( $pkg_strings['pkg_save'] ); ?></button>
	</div>
	<p class="andr-pkg__note" data-pkg-hint hidden></p>
	<p class="andr-pkg__suggest" data-pkg-suggest role="status" hidden></p>
	<p class="andr-dispatch__warnbox" data-pkg-nofit role="status" hidden></p>
	<p class="andr-dispatch__warnbox" data-pkg-deleted role="alert" hidden><?php echo esc_html( $pkg_strings['pkg_deleted'] ); ?></p>
	<ul class="andr-pkg__boxes" data-pkg-boxes hidden></ul>
</div>
