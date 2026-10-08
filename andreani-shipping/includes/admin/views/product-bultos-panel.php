<?php
/**
 * Panel de cómo se acomodan varias unidades — vista para el tab Envío del producto.
 *
 * @package AndreaniPlugin
 * @var array  $bultos Cajas adicionales existentes, en la unidad de la tienda.
 * @var string $status Estado del producto: ok, bigger o missing.
 * @var string $mode   Modo de despacho derivado de lo guardado.
 * @var bool   $open   El usuario dejó el bloque expandido.
 */

defined( 'ABSPATH' ) || exit;

$wc_weight_unit    = get_option( 'woocommerce_weight_unit', 'kg' );
$wc_dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );

$strings = Andreani_Product_Bultos::get_ui_strings();

$status_badges = array(
	'ok'      => array( 'success', $strings['status_ok'] ),
	'bigger'  => array( 'info', $strings['status_bigger'] ),
	'missing' => array( 'warning', $strings['box_status_missing'] ),
);
$status_badges['ok'][1]     = $strings['box_status_ok'];
$status_badges['bigger'][1] = $strings['box_status_bigger'];

$box_row = static function ( $index, $number, $name, $values ) use ( $strings, $wc_weight_unit, $wc_dimension_unit ) {
	$values = array_merge( array( 'depth' => '', 'width' => '', 'height' => '', 'weight' => '' ), $values );
	?>
	<div class="andreani-bulto-row andr-box" data-index="<?php echo esc_attr( $index ); ?>">
		<span class="andr-box__title andreani-bulto-label"><?php echo esc_html( $strings['piece_title'] ); ?> <?php echo esc_html( $number ); ?></span>
		<label class="andr-box__ref">
			<span class="screen-reader-text"><?php echo esc_html( $strings['piece_reference'] ); ?></span>
			<input type="text" name="andreani_bulto_name[]" value="<?php echo esc_attr( $name ); ?>" placeholder="<?php echo esc_attr( $strings['piece_reference_hint'] ); ?>" maxlength="120">
		</label>
		<div class="andr-box__dims">
			<?php
			echo Andreani_Product_Bultos::dim_field( 'length', $strings['label_length'], $wc_dimension_unit, array( 'name' => 'andreani_bulto_depth[]', 'value' => $values['depth'], 'step' => 'any', 'min' => '0' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Andreani_Product_Bultos::dim_field( 'width', $strings['label_width'], $wc_dimension_unit, array( 'name' => 'andreani_bulto_width[]', 'value' => $values['width'], 'step' => 'any', 'min' => '0' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Andreani_Product_Bultos::dim_field( 'height', $strings['label_height'], $wc_dimension_unit, array( 'name' => 'andreani_bulto_height[]', 'value' => $values['height'], 'step' => 'any', 'min' => '0' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo Andreani_Product_Bultos::dim_field( 'weight', $strings['label_weight'], $wc_weight_unit, array( 'name' => 'andreani_bulto_weight[]', 'value' => $values['weight'], 'step' => 'any', 'min' => '0' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</div>
		<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-remove-bulto" title="<?php echo esc_attr( $strings['piece_remove'] ); ?>" aria-label="<?php echo esc_attr( $strings['piece_remove'] ); ?>">&times;</button>
		<div class="andreani-bulto-warning andr-dispatch__warnbox" style="display:none;">
			<span class="andreani-bulto-warning__text"><?php echo esc_html( $strings['same_dims_warning'] ); ?></span>
			<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm andreani-switch-to-apilado"><?php echo esc_html( $strings['switch_to_apilado'] ); ?></button>
		</div>
		<p class="andreani-bulto-incomplete andr-dispatch__warnbox" style="display:none;"><?php echo esc_html( $strings['piece_incomplete'] ); ?></p>
	</div>
	<?php
};
?>

<div class="andr-dispatch andr-dispatch--collapsible andreani-despacho-section">
	<?php wp_nonce_field( 'andreani_save_bultos', Andreani_Product_Bultos::NONCE_KEY ); ?>

	<button type="button" class="andr-dispatch__toggle" id="andreani-despacho-toggle" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="andreani-despacho-body">
		<span class="andr-dispatch__brand">
			<svg class="andr-dispatch__logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341" aria-hidden="true" focusable="false">
				<g transform="translate(0,341) scale(0.1,-0.1)" fill="#e31e24">
					<path d="M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z"/>
				</g>
			</svg>
			Andreani
		</span>
		<span class="andr-badge andr-badge--<?php echo esc_attr( $status_badges[ $status ][0] ); ?>" id="andreani-despacho-status">
			<svg class="andr-dispatch__badge-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path fill="currentColor" fill-rule="evenodd" d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm-.75 3.5h1.5v4.5h-1.5V4.5zm0 6h1.5V12h-1.5v-1.5z"/></svg>
			<span data-andr="badge-text"><?php echo esc_html( $status_badges[ $status ][1] ); ?></span>
		</span>
		<svg class="andr-dispatch__chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" aria-hidden="true" focusable="false"><path d="M5 7.5l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
	</button>

	<div class="andr-dispatch__body" id="andreani-despacho-body"<?php echo $open ? '' : ' hidden'; ?>>

	<div class="andr-ask">
		<span class="andr-ask__q"><?php echo esc_html( $strings['mode_question'] ); ?></span>
		<?php
		$seg_name = Andreani_Product_Bultos::MODE_FIELD;
		$seg_id   = 'andreani-despacho-mode-';
		$seg_mode = $mode;
		include ANDREANI_PLUGIN_DIR . 'includes/admin/views/dispatch-mode-segmented.php';
		?>
	</div>

	<div class="andr-boxes">
		<div class="andr-boxes__grid">
		<?php include ANDREANI_PLUGIN_DIR . 'includes/admin/views/dispatch-box-head.php'; ?>
		<div class="andr-box andr-box--main andr-box--readonly" id="andreani-despacho-main" data-dim-unit="<?php echo esc_attr( $wc_dimension_unit ); ?>" data-weight-unit="<?php echo esc_attr( $wc_weight_unit ); ?>">
			<span class="andr-box__title" id="andreani-despacho-main-title"><?php echo esc_html( $strings['box_single'] ); ?></span>
			<label class="andr-box__ref" id="andreani-despacho-main-ref"<?php echo Andreani_Product_Bultos::MODE_MULTIBULTO === $mode ? '' : ' hidden'; ?>>
				<span class="screen-reader-text"><?php echo esc_html( $strings['piece_reference'] ); ?></span>
				<input type="text" name="andreani_main_box_ref" value="<?php echo esc_attr( (string) get_post_meta( $post->ID, Andreani_Product_Bultos::MAIN_REF_META, true ) ); ?>" placeholder="<?php echo esc_attr( $strings['main_ref_hint'] ); ?>" maxlength="60">
			</label>
			<span class="andr-box__summary" id="andreani-despacho-main-summary"></span>
			<span class="andr-box__cells">
				<?php foreach ( array( 'length', 'width', 'height', 'weight' ) as $andreani_cell ) : ?>
					<span class="andr-box__cell" data-cell="<?php echo esc_attr( $andreani_cell ); ?>"></span>
				<?php endforeach; ?>
			</span>
			<span class="andr-box__note"><?php echo esc_html( $strings['box_main_note'] ); ?></span>
		</div>

			<div id="andreani-bultos-list" class="andr-boxes__list">
				<?php
				foreach ( $bultos as $i => $bulto ) {
					$box_row( (string) $i, (string) ( $i + 2 ), sprintf( 'Bulto %d', $i + 2 ) === $bulto['name'] ? '' : $bulto['name'], $bulto );
				}
				?>
			</div>
		</div>

		<div class="andr-boxes__panel" id="andreani-despacho-panel-apilado"<?php echo Andreani_Product_Bultos::MODE_APILADO === $mode ? '' : ' hidden'; ?>>
			<?php Andreani_Product_Apilado::render_fields( $post->ID ); ?>
		</div>

		<div class="andr-boxes__panel andr-boxes__panel--multi" id="andreani-despacho-panel-multibulto"<?php echo Andreani_Product_Bultos::MODE_MULTIBULTO === $mode ? '' : ' hidden'; ?>>
			<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm" id="andreani-add-bulto"><?php echo esc_html( $strings['piece_add'] ); ?></button>

			<p class="andr-dispatch__warnbox" id="andreani-bultos-invalid" style="display:none;"><?php echo esc_html( $strings['bultos_invalid'] ); ?></p>
		</div>

		<div class="andr-dispatch__warnbox" id="andreani-despacho-discard" role="status" hidden>
			<span id="andreani-despacho-discard-text"
				data-hint-apilado="<?php echo esc_attr__( 'Al guardar se quita la configuración de «Se apilan»', 'andreani-shipping' ); ?>"
				data-hint-bultos="<?php echo esc_attr__( 'Al guardar se quitan las cajas adicionales', 'andreani-shipping' ); ?>"
				data-hint-both="<?php echo esc_attr__( 'Al guardar se quitan la configuración de «Se apilan» y las cajas adicionales', 'andreani-shipping' ); ?>"
				data-confirm-apilado="<?php echo esc_attr__( 'Vas a quitar la configuración de «Se apilan» de este producto', 'andreani-shipping' ); ?>"
				data-confirm-bultos="<?php echo esc_attr__( 'Vas a quitar las cajas adicionales de este producto', 'andreani-shipping' ); ?>"
				data-confirm-both="<?php echo esc_attr__( 'Vas a quitar la configuración de «Se apilan» y las cajas adicionales de este producto', 'andreani-shipping' ); ?>"></span>
			<div id="andreani-despacho-discard-actions" hidden>
				<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm" id="andreani-despacho-discard-go"><?php esc_html_e( 'Guardar igual', 'andreani-shipping' ); ?></button>
				<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-despacho-discard-cancel"><?php esc_html_e( 'Cancelar', 'andreani-shipping' ); ?></button>
			</div>
		</div>
	</div>

	<?php include ANDREANI_PLUGIN_DIR . 'includes/admin/views/dispatch-preview.php'; ?>
	</div>
</div>

<script type="text/html" id="tmpl-andreani-bulto-row">
	<?php $box_row( '{{data.index}}', '{{data.number}}', '', array() ); ?>
</script>
