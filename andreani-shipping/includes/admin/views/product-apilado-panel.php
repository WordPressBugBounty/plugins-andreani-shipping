<?php
/**
 * Campos de apilado — parte del panel de modo de despacho del tab Envío.
 *
 * @package AndreaniPlugin
 * @var array $apilado Config de apilado guardada.
 */

defined( 'ABSPATH' ) || exit;

$max_stackable_units   = isset( $apilado['maxStackableUnits'] ) ? (int) $apilado['maxStackableUnits'] : '';
$unit_increment_height = isset( $apilado['unitIncrementHeight'] ) ? (float) $apilado['unitIncrementHeight'] : '';
$unit_increment_width  = isset( $apilado['unitIncrementWidth'] ) ? (float) $apilado['unitIncrementWidth'] : '';
$unit_increment_depth  = isset( $apilado['unitIncrementDepth'] ) ? (float) $apilado['unitIncrementDepth'] : '';
?>

<?php wp_nonce_field( 'andreani_save_apilado', Andreani_Product_Apilado::NONCE_KEY ); ?>

<p class="andreani-apilado-help">
	<?php esc_html_e( 'La primera unidad ocupa la medida que cargaste arriba y cada unidad extra suma el incremento que indiques. Por ejemplo: una silla de 45 cm de alto que apila de a 6 sumando 15 cm por unidad, en un pedido de 4 unidades viaja como un solo bulto de 45 + 15 × 3 = 90 cm.', 'andreani-shipping' ); ?>
</p>

<div class="andreani-apilado-fields">
	<label>
		<?php esc_html_e( 'Límite unidades apilables', 'andreani-shipping' ); ?>
		<input type="number" name="andreani_apilado_maxStackableUnits" id="andreani-apilado-max-units" value="<?php echo esc_attr( $max_stackable_units ); ?>" step="1" min="2">
	</label>
	<label>
		<?php esc_html_e( 'Aumenta alto (cm)', 'andreani-shipping' ); ?>
		<input type="number" name="andreani_apilado_unitIncrementHeight" id="andreani-apilado-inc-height" value="<?php echo esc_attr( $unit_increment_height ); ?>" step="any" min="0">
	</label>
	<label>
		<?php esc_html_e( 'Aumenta ancho (cm)', 'andreani-shipping' ); ?>
		<input type="number" name="andreani_apilado_unitIncrementWidth" id="andreani-apilado-inc-width" value="<?php echo esc_attr( $unit_increment_width ); ?>" step="any" min="0">
	</label>
	<label>
		<?php esc_html_e( 'Aumenta profund. (cm)', 'andreani-shipping' ); ?>
		<input type="number" name="andreani_apilado_unitIncrementDepth" id="andreani-apilado-inc-depth" value="<?php echo esc_attr( $unit_increment_depth ); ?>" step="any" min="0">
	</label>
</div>

<p class="andreani-apilado-help andreani-apilado-help--warn" id="andreani-apilado-invalid" style="display:none;">
	<?php echo esc_html( Andreani_Product_Apilado::invalid_message() ); ?>
</p>
