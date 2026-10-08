<?php
/**
 * Vista previa de cómo viaja un pedido: dibujo, simulador de unidades y detalle técnico.
 *
 * @package AndreaniPlugin
 * @var array $strings Textos de Andreani_Product_Bultos::get_ui_strings().
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="andr-dispatch__preview">
	<div class="andr-dispatch__stage">
		<svg data-andr="stage" role="img" aria-label="<?php echo esc_attr( $strings['pack_title'] ); ?>"></svg>
	</div>
	<div class="andr-dispatch__simulator">
		<div class="andr-dispatch__qty">
			<span class="andr-dispatch__qty-text"><?php echo esc_html( $strings['preview_qty'] ); ?></span>
			<input type="number" class="andr-dispatch__qty-input" data-andr="qty-input" min="1" max="99" step="1" value="4" inputmode="numeric" aria-label="<?php echo esc_attr( $strings['preview_qty'] ); ?>">
			<span class="andr-dispatch__qty-text" data-andr="qty-unit"><?php echo esc_html( $strings['preview_unit_many'] ); ?></span>
			<input type="range" class="andr-dispatch__slider" data-andr="qty" min="1" max="14" step="1" value="4" aria-label="<?php echo esc_attr( $strings['preview_qty'] ); ?>">
			<span class="andr-dispatch__qty-cap" data-andr="qty-cap" aria-hidden="true">14+</span>
		</div>
		<div class="andr-dispatch__result" data-andr="result" aria-live="polite"></div>
		<details class="andr-dispatch__details">
			<summary><?php echo esc_html( $strings['preview_detail'] ); ?></summary>
			<p class="andr-dispatch__note"><?php echo esc_html( $strings['preview_help'] ); ?></p>
			<div data-andr="detail"></div>
		</details>
	</div>
</div>
