<?php
/**
 * Cuántos entran en una caja o cuánto crece la pila, según cómo viaja el producto.
 *
 * @package AndreaniPlugin
 * @var array $strings Textos de Andreani_Product_Bultos::get_ui_strings().
 * @var array $cap     prefix, mode, units (name opcional, value) y stack_html.
 */

defined( 'ABSPATH' ) || exit;

$cap_mode     = $cap['mode'];
$cap_units_id = $cap['prefix'] . 'units';
?>
<div class="andr-capacity" data-andr="capacity">
	<div class="andr-capacity__units" data-andr="capacity-units"<?php echo Andreani_Product_Bultos::MODE_SINGLE === $cap_mode ? '' : ' hidden'; ?>>
		<label class="andr-ask__q" for="<?php echo esc_attr( $cap_units_id ); ?>"><?php echo esc_html( $strings['capacity_label'] ); ?></label>
		<div class="andr-stepper-xl">
			<button type="button" class="andr-stepper-xl__btn" data-units-step="-1" aria-label="<?php echo esc_attr( $strings['units_less'] ); ?>">&minus;</button>
			<input type="number" class="andr-stepper-xl__input" id="<?php echo esc_attr( $cap_units_id ); ?>"<?php echo isset( $cap['units']['name'] ) ? ' name="' . esc_attr( $cap['units']['name'] ) . '"' : ''; ?> value="<?php echo esc_attr( $cap['units']['value'] ); ?>" min="1" step="1" inputmode="numeric">
			<button type="button" class="andr-stepper-xl__btn" data-units-step="1" aria-label="<?php echo esc_attr( $strings['units_more'] ); ?>">+</button>
		</div>
	</div>
	<?php echo $cap['stack_html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
