<?php
/**
 * Panel de bultos adicionales — vista para el tab Envío del producto.
 *
 * El toggle de bultos está disponible para cualquier producto.
 * El badge Bigger es solo informativo y se actualiza reactivamente
 * desde el JS cuando cambian las dimensiones.
 *
 * @package AndreaniPlugin
 * @var array $bultos     Bultos adicionales existentes.
 * @var bool  $is_bigger  Si el producto califica como Bigger.
 * @var bool  $has_bultos Si hay bultos guardados.
 */

defined( 'ABSPATH' ) || exit;

$wc_weight_unit    = get_option( 'woocommerce_weight_unit', 'kg' );
$wc_dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );
?>

<div class="options_group andreani-bultos-section">
	<?php wp_nonce_field( 'andreani_save_bultos', Andreani_Product_Bultos::NONCE_KEY ); ?>

	<div class="andreani-bultos-header">
		<span class="andreani-bultos-title">
			<svg class="andreani-bultos-logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341" aria-hidden="true" focusable="false">
				<g transform="translate(0,341) scale(0.1,-0.1)" fill="#e31e24">
					<path d="M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z"/>
				</g>
			</svg>
			<?php esc_html_e( 'Bultos adicionales', 'andreani-shipping' ); ?>
		</span>
		<span class="andreani-bultos-badge andreani-bultos-badge--<?php echo $is_bigger ? 'bigger' : 'regular'; ?>">
			<?php echo $is_bigger ? esc_html__( 'Bigger', 'andreani-shipping' ) : esc_html__( 'Paquete común', 'andreani-shipping' ); ?>
		</span>
	</div>

	<div class="andreani-bultos-toggle-field">
		<label for="andreani-bultos-toggle">
			<input type="checkbox" id="andreani-bultos-toggle" <?php checked( $has_bultos ); ?>>
			<?php esc_html_e( 'Este producto se envía en múltiples bultos', 'andreani-shipping' ); ?>
		</label>
	</div>

	<div id="andreani-bultos-content" class="<?php echo $has_bultos ? 'active' : ''; ?>">
		<p class="andreani-bultos-help">
			<?php esc_html_e( 'El bulto principal usa el peso y dimensiones de arriba. Cada fila representa un bulto adicional.', 'andreani-shipping' ); ?>
		</p>

		<div id="andreani-bultos-list">
			<?php if ( $has_bultos ) : ?>
				<?php foreach ( $bultos as $i => $bulto ) : ?>
					<div class="andreani-bulto-row" data-index="<?php echo esc_attr( $i ); ?>">
						<span class="andreani-bulto-label"><?php printf( esc_html__( 'Bulto %d', 'andreani-shipping' ), $i + 1 ); ?></span>
						<label>
							<?php printf( esc_html__( 'Ancho (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
							<input type="number" name="andreani_bulto_width[]" value="<?php echo esc_attr( $bulto['width'] ); ?>" step="any" min="0">
						</label>
						<label>
							<?php printf( esc_html__( 'Alto (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
							<input type="number" name="andreani_bulto_height[]" value="<?php echo esc_attr( $bulto['height'] ); ?>" step="any" min="0">
						</label>
						<label>
							<?php printf( esc_html__( 'Largo (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
							<input type="number" name="andreani_bulto_length[]" value="<?php echo esc_attr( $bulto['length'] ); ?>" step="any" min="0">
						</label>
						<label>
							<?php printf( esc_html__( 'Peso (%s)', 'andreani-shipping' ), esc_html( $wc_weight_unit ) ); ?>
							<input type="number" name="andreani_bulto_weight[]" value="<?php echo esc_attr( $bulto['weight'] ); ?>" step="any" min="0">
						</label>
						<button type="button" class="button andreani-remove-bulto" title="<?php esc_attr_e( 'Eliminar bulto', 'andreani-shipping' ); ?>">&times;</button>
					</div>
				<?php endforeach; ?>
			<?php endif; ?>
		</div>

		<button type="button" class="button andreani-add-bulto" id="andreani-add-bulto">
			+ <?php esc_html_e( 'Agregar bulto', 'andreani-shipping' ); ?>
		</button>
	</div>
</div>

<script type="text/html" id="tmpl-andreani-bulto-row">
	<div class="andreani-bulto-row" data-index="{{data.index}}">
		<span class="andreani-bulto-label"><?php esc_html_e( 'Bulto', 'andreani-shipping' ); ?> {{data.number}}</span>
		<label>
			<?php printf( esc_html__( 'Ancho (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
			<input type="number" name="andreani_bulto_width[]" value="" step="any" min="0">
		</label>
		<label>
			<?php printf( esc_html__( 'Alto (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
			<input type="number" name="andreani_bulto_height[]" value="" step="any" min="0">
		</label>
		<label>
			<?php printf( esc_html__( 'Largo (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?>
			<input type="number" name="andreani_bulto_length[]" value="" step="any" min="0">
		</label>
		<label>
			<?php printf( esc_html__( 'Peso (%s)', 'andreani-shipping' ), esc_html( $wc_weight_unit ) ); ?>
			<input type="number" name="andreani_bulto_weight[]" value="" step="any" min="0">
		</label>
		<button type="button" class="button andreani-remove-bulto" title="<?php esc_attr_e( 'Eliminar bulto', 'andreani-shipping' ); ?>">&times;</button>
	</div>
</script>
