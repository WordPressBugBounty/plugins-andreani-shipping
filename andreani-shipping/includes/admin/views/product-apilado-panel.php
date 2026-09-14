<?php
/**
 * Panel de apilado — vista para el tab Envío del producto.
 *
 * @package AndreaniPlugin
 * @var array $apilado     Config de apilado guardada.
 * @var bool  $has_apilado Si hay apilado configurado.
 * @var bool  $has_bultos  Si el producto tiene bultos adicionales cargados.
 */

defined( 'ABSPATH' ) || exit;

$max_stackable_units   = isset( $apilado['maxStackableUnits'] ) ? (int) $apilado['maxStackableUnits'] : '';
$unit_increment_height = isset( $apilado['unitIncrementHeight'] ) ? (float) $apilado['unitIncrementHeight'] : '';
$unit_increment_width  = isset( $apilado['unitIncrementWidth'] ) ? (float) $apilado['unitIncrementWidth'] : '';
$unit_increment_depth  = isset( $apilado['unitIncrementDepth'] ) ? (float) $apilado['unitIncrementDepth'] : '';

$readonly_attr = $has_bultos ? 'readonly' : '';
?>

<div class="options_group andreani-apilado-section<?php echo $has_bultos ? ' andreani-apilado-section--locked' : ''; ?>">
	<?php wp_nonce_field( 'andreani_save_apilado', Andreani_Product_Apilado::NONCE_KEY ); ?>

	<div class="andreani-apilado-header">
		<span class="andreani-apilado-title">
			<svg class="andreani-apilado-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 12l9 4 9-4"/><path d="M3 17l9 4 9-4"/></svg>
			<?php esc_html_e( 'Apilado de bultos', 'andreani-shipping' ); ?>
		</span>
	</div>

	<div class="andreani-apilado-notice" id="andreani-apilado-notice" <?php echo $has_bultos ? '' : 'style="display:none;"'; ?>>
		<?php esc_html_e( 'Este producto tiene bultos adicionales cargados. Un producto se envía en varios bultos o se apila, pero no las dos cosas: mientras haya bultos adicionales el apilado no se aplica. Quitá los bultos adicionales para poder apilar.', 'andreani-shipping' ); ?>
	</div>

	<div class="andreani-apilado-toggle-field">
		<label for="andreani-apilado-toggle">
			<input type="checkbox" name="andreani_apilado_activo" value="1" id="andreani-apilado-toggle" <?php checked( $has_apilado ); ?> <?php disabled( $has_bultos ); ?>>
			<?php esc_html_e( '¿Es un bulto apilable?', 'andreani-shipping' ); ?>
		</label>
	</div>

	<div id="andreani-apilado-content" class="<?php echo $has_apilado ? 'active' : ''; ?>">
		<p class="andreani-apilado-help">
			<?php esc_html_e( 'La primera unidad ocupa la medida que cargaste arriba y cada unidad extra suma el incremento que indiques. Por ejemplo: una silla de 45 cm de alto que apila de a 6 sumando 15 cm por unidad, en un pedido de 4 unidades viaja como un solo bulto de 45 + 15 × 3 = 90 cm.', 'andreani-shipping' ); ?>
		</p>

		<div class="andreani-apilado-fields">
			<label>
				<?php esc_html_e( 'Límite unidades apilables', 'andreani-shipping' ); ?>
				<input type="number" name="andreani_apilado_maxStackableUnits" id="andreani-apilado-max-units" value="<?php echo esc_attr( $max_stackable_units ); ?>" step="1" min="2" <?php echo esc_attr( $readonly_attr ); ?>>
			</label>
			<label>
				<?php esc_html_e( 'Aumenta alto (cm)', 'andreani-shipping' ); ?>
				<input type="number" name="andreani_apilado_unitIncrementHeight" id="andreani-apilado-inc-height" value="<?php echo esc_attr( $unit_increment_height ); ?>" step="any" min="0" placeholder="0" <?php echo esc_attr( $readonly_attr ); ?>>
			</label>
			<label>
				<?php esc_html_e( 'Aumenta ancho (cm)', 'andreani-shipping' ); ?>
				<input type="number" name="andreani_apilado_unitIncrementWidth" id="andreani-apilado-inc-width" value="<?php echo esc_attr( $unit_increment_width ); ?>" step="any" min="0" placeholder="0" <?php echo esc_attr( $readonly_attr ); ?>>
			</label>
			<label>
				<?php esc_html_e( 'Aumenta profund. (cm)', 'andreani-shipping' ); ?>
				<input type="number" name="andreani_apilado_unitIncrementDepth" id="andreani-apilado-inc-depth" value="<?php echo esc_attr( $unit_increment_depth ); ?>" step="any" min="0" placeholder="0" <?php echo esc_attr( $readonly_attr ); ?>>
			</label>
		</div>

		<p class="andreani-apilado-help andreani-apilado-help--warn" id="andreani-apilado-invalid" style="display:none;">
			<?php esc_html_e( 'Para que el apilado se aplique necesitás un límite de 2 unidades o más y que al menos uno de los tres incrementos sea mayor a cero.', 'andreani-shipping' ); ?>
		</p>
	</div>
</div>
