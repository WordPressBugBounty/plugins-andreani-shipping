<?php
/**
 * Control segmentado de cómo se despacha un pedido: en su caja, apiladas o varias cajas.
 *
 * @package AndreaniPlugin
 * @var array  $strings   Textos de Andreani_Product_Bultos::get_ui_strings().
 * @var string $seg_name  Atributo name de los radios.
 * @var string $seg_id    Prefijo del id de cada radio.
 * @var string $seg_mode  Modo seleccionado.
 */

defined( 'ABSPATH' ) || exit;

$seg_art = array(
	Andreani_Product_Bultos::MODE_SINGLE     => 'una',
	Andreani_Product_Bultos::MODE_APILADO    => 'apilado',
	Andreani_Product_Bultos::MODE_MULTIBULTO => 'piezas',
);

$seg_labels = array(
	Andreani_Product_Bultos::MODE_SINGLE     => $strings['mode_single_title'],
	Andreani_Product_Bultos::MODE_APILADO    => $strings['mode_apilado_title'],
	Andreani_Product_Bultos::MODE_MULTIBULTO => $strings['mode_multibulto_title'],
);
?>
<div class="andr-seg" role="radiogroup" aria-label="<?php echo esc_attr( $strings['mode_question'] ); ?>">
	<?php foreach ( $seg_labels as $seg_value => $seg_label ) : ?>
		<label class="andr-seg__option" for="<?php echo esc_attr( $seg_id . $seg_value ); ?>">
			<input type="radio"
				name="<?php echo esc_attr( $seg_name ); ?>"
				id="<?php echo esc_attr( $seg_id . $seg_value ); ?>"
				class="andr-seg__input"
				value="<?php echo esc_attr( $seg_value ); ?>"
				<?php checked( $seg_mode, $seg_value ); ?>>
			<svg class="andr-seg__art" data-andr-art="<?php echo esc_attr( $seg_art[ $seg_value ] ); ?>" aria-hidden="true" focusable="false"></svg>
			<span class="andr-seg__text"><?php echo esc_html( $seg_label ); ?></span>
		</label>
	<?php endforeach; ?>
</div>
