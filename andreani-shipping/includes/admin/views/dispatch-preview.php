<?php
/**
 * Simulador de cómo viaja un pedido: cantidad, dibujo y etiquetas resultantes.
 *
 * @package AndreaniPlugin
 * @var array $strings Textos de Andreani_Product_Bultos::get_ui_strings().
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="andr-dispatch__preview">
	<div class="andr-dispatch__simulator">
		<div class="andr-dispatch__qty">
			<span class="andr-ask__q"><?php echo esc_html( $strings['preview_qty'] ); ?></span>
			<div class="andr-stepper-xl">
				<button type="button" class="andr-stepper-xl__btn" data-qty-step="-1" aria-label="<?php echo esc_attr( $strings['units_less'] ); ?>">&minus;</button>
				<input type="number" class="andr-stepper-xl__input" data-andr="qty-input" min="1" max="99" step="1" value="4" inputmode="numeric" aria-label="<?php echo esc_attr( $strings['preview_qty'] ); ?>">
				<button type="button" class="andr-stepper-xl__btn" data-qty-step="1" aria-label="<?php echo esc_attr( $strings['units_more'] ); ?>">+</button>
			</div>
		</div>
		<div class="andr-dispatch__stage">
			<svg data-andr="stage" role="img" aria-label="<?php echo esc_attr( $strings['pack_title'] ); ?>"></svg>
		</div>
		<div class="andr-dispatch__result" data-andr="result" aria-live="polite"></div>
	</div>
</div>
