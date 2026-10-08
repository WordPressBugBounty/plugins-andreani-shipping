<?php
/**
 * Partial: fila de un producto en la lista, con su contenedor de detalle.
 *
 * @package AndreaniPlugin
 * @var array $item Ítem de Andreani_Products_List::build_item().
 */

defined( 'ABSPATH' ) || exit;

$strings        = Andreani_Product_Bultos::get_ui_strings();
$weight_unit    = get_option( 'woocommerce_weight_unit', 'kg' );
$dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );
$is_missing     = 'missing' === $item['state'];
$multibox       = $is_missing ? null : Andreani_Products_List::multibox_measures( $item, $dimension_unit );
$mode_icons     = array(
	Andreani_Product_Bultos::MODE_APILADO    => 'apilado',
	Andreani_Product_Bultos::MODE_MULTIBULTO => 'piezas',
);
?>
<div class="andreani-product-entry">
	<div class="andreani-product-item"
		role="button"
		tabindex="0"
		aria-expanded="false"
		data-product-id="<?php echo esc_attr( $item['id'] ); ?>"
		data-name="<?php echo esc_attr( $item['name'] ); ?>"
		data-edit-url="<?php echo esc_url( $item['edit_url'] ); ?>"
		data-sku="<?php echo esc_attr( isset( $item['sku'] ) ? $item['sku'] : '' ); ?>"
		data-woo-sku="<?php echo esc_attr( isset( $item['woo_sku'] ) ? $item['woo_sku'] : '' ); ?>"
		data-weight="<?php echo esc_attr( $item['weight'] ); ?>"
		data-length="<?php echo esc_attr( $item['length'] ); ?>"
		data-width="<?php echo esc_attr( $item['width'] ); ?>"
		data-height="<?php echo esc_attr( $item['height'] ); ?>"
		data-main-ref="<?php echo esc_attr( $item['main_ref'] ); ?>"
		data-bultos-json="<?php echo esc_attr( wp_json_encode( $item['bultos_data'] ) ); ?>"
		data-apilado-json="<?php echo esc_attr( wp_json_encode( $item['apilado'] ) ); ?>"
		data-missing="<?php echo $is_missing ? '1' : '0'; ?>">
		<div class="andreani-product-item__thumb-cell">
			<?php if ( ! empty( $item['thumb_url'] ) ) : ?>
				<img class="andreani-product-item__thumb" src="<?php echo esc_url( $item['thumb_url'] ); ?>" alt="" />
			<?php else : ?>
				<svg class="andreani-product-item__box" viewBox="0 0 48 48" aria-hidden="true" data-andr-thumb="<?php echo esc_attr( wp_json_encode( $item['packages'] ) ); ?>"></svg>
			<?php endif; ?>
		</div>
		<div class="andreani-product-item__info">
			<span class="andreani-product-item__name" title="<?php echo esc_attr( $item['name'] ); ?>"><?php echo esc_html( $item['name'] ); ?></span>
			<?php if ( ! empty( $item['sku'] ) ) : ?>
				<span class="andreani-product-item__sku"><?php echo esc_html( sprintf( /* translators: %s: SKU del producto */ __( 'SKU %s', 'andreani-shipping' ), $item['sku'] ) ); ?></span>
			<?php endif; ?>
		</div>
		<span class="andreani-product-item__measures">
			<?php if ( $multibox ) : ?>
				<span class="andreani-product-item__dims" title="<?php echo esc_attr( $multibox['title'] ); ?>"><?php echo esc_html( sprintf( /* translators: %d: cantidad de cajas */ __( '%d cajas', 'andreani-shipping' ), $multibox['count'] ) ); ?></span>
				<span class="andreani-product-item__weight"><?php echo esc_html( $multibox['weight'] . ' ' . $weight_unit ); ?></span>
			<?php else : ?>
				<span class="andreani-product-item__dims"><?php echo $is_missing ? '&mdash;' : esc_html( $item['length'] . ' × ' . $item['width'] . ' × ' . $item['height'] . ' ' . $dimension_unit ); ?></span>
				<span class="andreani-product-item__weight"><?php echo $is_missing ? '&mdash;' : esc_html( $item['weight'] . ' ' . $weight_unit ); ?></span>
			<?php endif; ?>
		</span>
		<div class="andreani-product-item__how">
			<?php if ( isset( $mode_icons[ $item['mode'] ] ) ) : ?>
				<span class="andreani-how"><svg class="andreani-how__icon" data-andr-icon="<?php echo esc_attr( $mode_icons[ $item['mode'] ] ); ?>" aria-hidden="true" focusable="false"></svg><?php echo esc_html( Andreani_Products_List::how_it_travels( $item ) ); ?></span>
			<?php else : ?>
				<span class="andreani-product-item__none">&mdash;</span>
			<?php endif; ?>
		</div>
		<div class="andreani-product-item__state">
			<?php if ( $is_missing ) : ?>
				<span class="andr-badge andr-badge--warning"><?php echo esc_html( $strings['status_missing'] ); ?></span>
				<span class="andr-btn andr-btn--primary andr-btn--sm andreani-product-item__complete"><?php esc_html_e( 'Completar', 'andreani-shipping' ); ?></span>
			<?php elseif ( 'bigger' === $item['state'] ) : ?>
				<span class="andr-badge andr-badge--info"><?php esc_html_e( 'Bigger', 'andreani-shipping' ); ?></span>
				<?php if ( '' !== $item['reason'] ) : ?>
					<span class="andreani-product-item__reason" title="<?php echo esc_attr( $item['reason'] ); ?>"><?php echo esc_html( $item['reason'] ); ?></span>
				<?php endif; ?>
			<?php else : ?>
				<span class="andr-badge andr-badge--success"><?php esc_html_e( 'Paquetería', 'andreani-shipping' ); ?></span>
			<?php endif; ?>
		</div>
		<svg class="andreani-product-item__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
	</div>
	<div class="andreani-product-detail">
		<div class="andreani-product-detail__content"></div>
	</div>
</div>
