<?php
/**
 * Cómo viaja el producto: en una caja, apilado o en varias cajas. El modo de despacho queda en un campo oculto.
 *
 * @package AndreaniPlugin
 * @var array $strings Textos de Andreani_Product_Bultos::get_ui_strings().
 * @var array $kind    prefix, name (campo del modo) y mode.
 */

defined( 'ABSPATH' ) || exit;

$kind_cards = array(
	'box'   => array( $strings['kind_box_title'], $strings['kind_box_hint'], Andreani_Product_Bultos::MODE_SINGLE, 'box' ),
	'stack' => array( $strings['kind_stack_title'], $strings['kind_stack_hint'], Andreani_Product_Bultos::MODE_APILADO, 'apilado' ),
	'multi' => array( $strings['kind_multi_title'], $strings['kind_multi_hint'], Andreani_Product_Bultos::MODE_MULTIBULTO, 'multi' ),
);
?>
<div class="andr-ask" data-andr="kind">
	<span class="andr-ask__q" id="<?php echo esc_attr( $kind['prefix'] ); ?>kind-q"><?php echo esc_html( $strings['mode_question'] ); ?></span>
	<input type="hidden" name="<?php echo esc_attr( $kind['name'] ); ?>" id="<?php echo esc_attr( $kind['prefix'] ); ?>mode" value="<?php echo esc_attr( $kind['mode'] ); ?>" data-andr="mode-input">
	<div class="andr-picks andr-picks--kind" role="radiogroup" aria-labelledby="<?php echo esc_attr( $kind['prefix'] ); ?>kind-q">
		<?php foreach ( $kind_cards as $kind_key => $kind_card ) : ?>
			<label class="andr-pick">
				<input type="radio" class="andr-pick__input" name="<?php echo esc_attr( $kind['prefix'] ); ?>kind" value="<?php echo esc_attr( $kind_key ); ?>"<?php checked( $kind_card[2] === $kind['mode'] ); ?>>
				<?php echo Andreani_Product_Bultos::art( $kind_card[3] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<span class="andr-pick__title"><?php echo esc_html( $kind_card[0] ); ?></span>
				<span class="andr-pick__hint"><?php echo esc_html( $kind_card[1] ); ?></span>
			</label>
		<?php endforeach; ?>
	</div>
</div>
