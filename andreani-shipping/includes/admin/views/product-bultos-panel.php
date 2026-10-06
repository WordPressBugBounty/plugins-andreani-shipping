<?php
/**
 * Panel de modo de despacho — vista para el tab Envío del producto.
 *
 * @package AndreaniPlugin
 * @var array  $bultos      Bultos adicionales existentes, en la unidad de la tienda.
 * @var string $bigger_text Cómo se despacha el producto y por qué.
 * @var bool   $is_bigger   Si el producto califica como Bigger.
 * @var string $mode        Modo de despacho derivado de lo guardado.
 */

defined( 'ABSPATH' ) || exit;

$wc_weight_unit    = get_option( 'woocommerce_weight_unit', 'kg' );
$wc_dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );

$strings = Andreani_Product_Bultos::get_ui_strings();

$mode_options = array(
	Andreani_Product_Bultos::MODE_SINGLE     => array( $strings['mode_single_title'], $strings['mode_single_desc'] ),
	Andreani_Product_Bultos::MODE_APILADO    => array( $strings['mode_apilado_title'], $strings['mode_apilado_desc'] ),
	Andreani_Product_Bultos::MODE_MULTIBULTO => array( $strings['mode_multibulto_title'], $strings['mode_multibulto_desc'] ),
);
?>

<div class="options_group andreani-despacho-section">
	<?php wp_nonce_field( 'andreani_save_bultos', Andreani_Product_Bultos::NONCE_KEY ); ?>

	<div class="andreani-despacho-header">
		<span class="andreani-despacho-title">
			<svg class="andreani-despacho-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341" aria-hidden="true" focusable="false">
				<g transform="translate(0,341) scale(0.1,-0.1)" fill="#e31e24">
					<path d="M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z"/>
				</g>
			</svg>
			<?php echo esc_html( $strings['mode_question'] ); ?>
		</span>
		<span class="andreani-despacho-status andreani-despacho-status--<?php echo $is_bigger ? 'bigger' : 'regular'; ?>" id="andreani-despacho-status"><?php echo esc_html( $bigger_text ); ?></span>
	</div>

	<div class="andreani-despacho-options">
		<?php foreach ( $mode_options as $mode_value => $mode_copy ) : ?>
			<label class="andreani-despacho-option" for="andreani-despacho-mode-<?php echo esc_attr( $mode_value ); ?>">
				<input type="radio"
					name="<?php echo esc_attr( Andreani_Product_Bultos::MODE_FIELD ); ?>"
					id="andreani-despacho-mode-<?php echo esc_attr( $mode_value ); ?>"
					class="andreani-despacho-option__input"
					value="<?php echo esc_attr( $mode_value ); ?>"
					<?php checked( $mode, $mode_value ); ?>>
				<span class="andreani-despacho-option__copy">
					<span class="andreani-despacho-option__title"><?php echo esc_html( $mode_copy[0] ); ?></span>
					<span class="andreani-despacho-option__desc"><?php echo esc_html( $mode_copy[1] ); ?></span>
				</span>
			</label>
		<?php endforeach; ?>
	</div>

	<div class="andreani-despacho-panel" id="andreani-despacho-panel-apilado">
		<?php Andreani_Product_Apilado::render_fields( $post->ID ); ?>
	</div>

	<div class="andreani-despacho-panel" id="andreani-despacho-panel-multibulto">
		<p class="andreani-bultos-warning"><?php echo esc_html( $strings['mode_multibulto_warning'] ); ?></p>

		<div id="andreani-bultos-list">
			<?php foreach ( $bultos as $i => $bulto ) : ?>
				<div class="andreani-bulto-row" data-index="<?php echo esc_attr( $i ); ?>">
					<span class="andreani-bulto-label"><?php printf( esc_html__( 'Bulto %d', 'andreani-shipping' ), $i + 2 ); ?></span>
					<label class="andreani-bulto-row__name">
						<?php esc_html_e( 'Referencia del bulto', 'andreani-shipping' ); ?>
						<input type="text" name="andreani_bulto_name[]" value="<?php echo esc_attr( $bulto['name'] ); ?>" placeholder="<?php esc_attr_e( 'Ej. Base de somier', 'andreani-shipping' ); ?>" maxlength="120">
					</label>
					<label>
						<?php printf( esc_html__( 'Alto (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
						<input type="number" name="andreani_bulto_height[]" value="<?php echo esc_attr( $bulto['height'] ); ?>" step="any" min="0">
					</label>
					<label>
						<?php printf( esc_html__( 'Ancho (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
						<input type="number" name="andreani_bulto_width[]" value="<?php echo esc_attr( $bulto['width'] ); ?>" step="any" min="0">
					</label>
					<label>
						<?php printf( esc_html__( 'Profundidad (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
						<input type="number" name="andreani_bulto_depth[]" value="<?php echo esc_attr( $bulto['depth'] ); ?>" step="any" min="0">
					</label>
					<label>
						<?php printf( esc_html__( 'Peso (%s)', 'andreani-shipping' ), esc_html( $wc_weight_unit ) ); ?>
						<input type="number" name="andreani_bulto_weight[]" value="<?php echo esc_attr( $bulto['weight'] ); ?>" step="any" min="0">
					</label>
					<button type="button" class="button andreani-remove-bulto" title="<?php esc_attr_e( 'Eliminar pieza', 'andreani-shipping' ); ?>">&times;</button>
					<div class="andreani-bulto-warning" style="display:none;">
						<span class="andreani-bulto-warning__text"><?php echo esc_html( $strings['same_dims_warning'] ); ?></span>
						<button type="button" class="button andreani-switch-to-apilado"><?php echo esc_html( $strings['switch_to_apilado'] ); ?></button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<button type="button" class="button andreani-add-bulto" id="andreani-add-bulto">
			+ <?php esc_html_e( 'Agregar pieza', 'andreani-shipping' ); ?>
		</button>

		<p class="andreani-apilado-help andreani-apilado-help--warn" id="andreani-bultos-invalid" style="display:none;"><?php echo esc_html( $strings['bultos_invalid'] ); ?></p>
	</div>

	<div class="andreani-despacho-preview" id="andreani-despacho-preview">
		<button type="button" class="andreani-despacho-preview__toggle" id="andreani-despacho-preview-toggle" aria-expanded="false" aria-controls="andreani-despacho-preview-content">
			<span class="andreani-despacho-preview__title"><?php echo esc_html( $strings['preview_title'] ); ?></span>
			<svg class="andreani-despacho-preview__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
		</button>
		<div class="andreani-despacho-preview__content" id="andreani-despacho-preview-content" hidden>
			<p class="andreani-despacho-preview__help"><?php echo esc_html( $strings['preview_help'] ); ?></p>
			<div class="andreani-despacho-preview__body" id="andreani-despacho-preview-body" aria-live="polite"></div>
		</div>
	</div>
</div>

<script type="text/html" id="tmpl-andreani-bulto-row">
	<div class="andreani-bulto-row" data-index="{{data.index}}">
		<span class="andreani-bulto-label"><?php esc_html_e( 'Bulto', 'andreani-shipping' ); ?> {{data.number}}</span>
		<label class="andreani-bulto-row__name">
			<?php esc_html_e( 'Referencia del bulto', 'andreani-shipping' ); ?>
			<input type="text" name="andreani_bulto_name[]" value="" placeholder="<?php esc_attr_e( 'Ej. Base de somier', 'andreani-shipping' ); ?>" maxlength="120">
		</label>
		<label>
			<?php printf( esc_html__( 'Alto (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
			<input type="number" name="andreani_bulto_height[]" value="" step="any" min="0">
		</label>
		<label>
			<?php printf( esc_html__( 'Ancho (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
			<input type="number" name="andreani_bulto_width[]" value="" step="any" min="0">
		</label>
		<label>
			<?php printf( esc_html__( 'Profundidad (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
			<input type="number" name="andreani_bulto_depth[]" value="" step="any" min="0">
		</label>
		<label>
			<?php printf( esc_html__( 'Peso (%s)', 'andreani-shipping' ), esc_html( $wc_weight_unit ) ); ?>
			<input type="number" name="andreani_bulto_weight[]" value="" step="any" min="0">
		</label>
		<button type="button" class="button andreani-remove-bulto" title="<?php esc_attr_e( 'Eliminar pieza', 'andreani-shipping' ); ?>">&times;</button>
		<div class="andreani-bulto-warning" style="display:none;">
			<span class="andreani-bulto-warning__text"><?php echo esc_html( $strings['same_dims_warning'] ); ?></span>
			<button type="button" class="button andreani-switch-to-apilado"><?php echo esc_html( $strings['switch_to_apilado'] ); ?></button>
		</div>
	</div>
</script>
