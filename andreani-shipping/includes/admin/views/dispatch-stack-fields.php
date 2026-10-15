<?php
/**
 * Campos de apilado en línea: hasta cuántas se apilan y cuánto suma cada una.
 *
 * @package AndreaniPlugin
 * @var array $strings Textos de Andreani_Product_Bultos::get_ui_strings().
 * @var array $stack   Por campo (max, height, width, depth): id, value y name opcional.
 */

defined( 'ABSPATH' ) || exit;

$stack_input = static function ( $key, $min, $step, $class = 'andr-stack__input' ) use ( $stack ) {
	$field = $stack[ $key ];

	return '<input type="number" class="' . esc_attr( $class ) . '"'
		. ' id="' . esc_attr( $field['id'] ) . '"'
		. ( isset( $field['name'] ) ? ' name="' . esc_attr( $field['name'] ) . '"' : '' )
		. ( isset( $field['value'] ) ? ' value="' . esc_attr( $field['value'] ) . '"' : '' )
		. ' min="' . esc_attr( $min ) . '" step="' . esc_attr( $step ) . '" data-stack="' . esc_attr( $key ) . '">';
};
?>
<div class="andr-stack">
	<label class="andr-ask__q" for="<?php echo esc_attr( $stack['max']['id'] ); ?>"><?php echo esc_html( $strings['stack_up_to'] ); ?></label>
	<div class="andr-stepper-xl">
		<button type="button" class="andr-stepper-xl__btn" data-stack-step="-1" aria-label="<?php echo esc_attr( $strings['units_less'] ); ?>">&minus;</button>
		<?php echo $stack_input( 'max', '2', '1', 'andr-stepper-xl__input' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<button type="button" class="andr-stepper-xl__btn" data-stack-step="1" aria-label="<?php echo esc_attr( $strings['units_more'] ); ?>">+</button>
	</div>
	<label class="andr-stack__item">
		<?php echo esc_html( $strings['stack_adds_height'] ); ?>
		<?php echo $stack_input( 'height', '0', 'any' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo esc_html( $strings['stack_adds_height_unit'] ); ?>
	</label>
</div>
<details class="andr-dispatch__advanced">
	<summary><?php echo esc_html( $strings['stack_advanced'] ); ?></summary>
	<div class="andr-stack">
		<label class="andr-stack__item">
			<?php echo esc_html( $strings['stack_adds_width'] ); ?>
			<?php echo $stack_input( 'width', '0', 'any' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo esc_html( $strings['stack_adds_unit'] ); ?>
		</label>
		<label class="andr-stack__item">
			<?php echo esc_html( $strings['stack_adds_depth'] ); ?>
			<?php echo $stack_input( 'depth', '0', 'any' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo esc_html( $strings['stack_adds_unit'] ); ?>
		</label>
	</div>
</details>
