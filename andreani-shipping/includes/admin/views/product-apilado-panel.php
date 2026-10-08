<?php
/**
 * Campos de apilado — parte del panel de acomodo del tab Envío.
 *
 * @package AndreaniPlugin
 * @var array $apilado Config de apilado guardada.
 */

defined( 'ABSPATH' ) || exit;

$max_stackable_units   = isset( $apilado['maxStackableUnits'] ) ? (int) $apilado['maxStackableUnits'] : '';
$unit_increment_height = isset( $apilado['unitIncrementHeight'] ) ? (float) $apilado['unitIncrementHeight'] : '';
$unit_increment_width  = isset( $apilado['unitIncrementWidth'] ) ? (float) $apilado['unitIncrementWidth'] : '';
$unit_increment_depth  = isset( $apilado['unitIncrementDepth'] ) ? (float) $apilado['unitIncrementDepth'] : '';

$strings = Andreani_Product_Bultos::get_ui_strings();
?>

<?php wp_nonce_field( 'andreani_save_apilado', Andreani_Product_Apilado::NONCE_KEY ); ?>

<?php
$stack = array(
	'max'    => array( 'id' => 'andreani-apilado-max-units', 'name' => 'andreani_apilado_maxStackableUnits', 'value' => $max_stackable_units ),
	'height' => array( 'id' => 'andreani-apilado-inc-height', 'name' => 'andreani_apilado_unitIncrementHeight', 'value' => $unit_increment_height ),
	'width'  => array( 'id' => 'andreani-apilado-inc-width', 'name' => 'andreani_apilado_unitIncrementWidth', 'value' => $unit_increment_width ),
	'depth'  => array( 'id' => 'andreani-apilado-inc-depth', 'name' => 'andreani_apilado_unitIncrementDepth', 'value' => $unit_increment_depth ),
);
include ANDREANI_PLUGIN_DIR . 'includes/admin/views/dispatch-stack-fields.php';
?>

<p class="andr-dispatch__warnbox" id="andreani-apilado-invalid" style="display:none;">
	<?php echo esc_html( Andreani_Product_Apilado::invalid_message() ); ?>
</p>
