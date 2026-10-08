<?php
/**
 * Campos de apilado en línea: cuántas entran por pila y cuánto suma cada una.
 *
 * @package AndreaniPlugin
 * @var array $strings Textos de Andreani_Product_Bultos::get_ui_strings().
 * @var array $stack   Por campo (max, height, width, depth): id, value y name opcional.
 */

defined( 'ABSPATH' ) || exit;

$stack_input = static function ( $key, $min, $step ) use ( $stack ) {
	$field = $stack[ $key ];

	return '<input type="number" class="andr-stack__input"'
		. ' id="' . esc_attr( $field['id'] ) . '"'
		. ( isset( $field['name'] ) ? ' name="' . esc_attr( $field['name'] ) . '"' : '' )
		. ( isset( $field['value'] ) ? ' value="' . esc_attr( $field['value'] ) . '"' : '' )
		. ' min="' . esc_attr( $min ) . '" step="' . esc_attr( $step ) . '">';
};
?>
<div class="andr-stack">
	<label class="andr-stack__item">
		<?php echo esc_html( $strings['stack_up_to'] ); ?>
		<?php echo $stack_input( 'max', '2', '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo esc_html( $strings['stack_up_to_unit'] ); ?>
	</label>
	<span class="andr-stack__sep" aria-hidden="true">&middot;</span>
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
