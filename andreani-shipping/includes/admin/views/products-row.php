<?php
/**
 * Partial: fila de un producto en la lista, con su contenedor de detalle.
 *
 * @package AndreaniPlugin
 * @var array $item Ítem de Andreani_Products_List::build_item().
 */

defined( 'ABSPATH' ) || exit;

$weight_unit    = get_option( 'woocommerce_weight_unit', 'kg' );
$dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );
$strings        = Andreani_Product_Bultos::get_ui_strings();
$is_missing     = 'missing' === $item['state'];
$is_multibulto  = Andreani_Product_Bultos::MODE_MULTIBULTO === $item['mode'];
$multibox       = $is_missing ? null : Andreani_Products_List::multibox_measures( $item, $dimension_unit );
if ( $multibox ) {
	$dims_text   = sprintf( /* translators: %d: cantidad de cajas */ __( '%d cajas', 'andreani-shipping' ), $multibox['count'] );
	$weight_text = $multibox['weight'] . ' ' . $weight_unit;
} else {
	$eff         = isset( $item['eff'] ) ? $item['eff'] : $item;
	$dims_text   = $is_missing ? '' : $eff['length'] . ' × ' . $eff['width'] . ' × ' . $eff['height'] . ' ' . $dimension_unit;
	$weight_text = $is_missing ? '' : $eff['weight'] . ' ' . $weight_unit;
}
$measures_text = trim( $dims_text . ( '' !== $dims_text && '' !== $weight_text ? ' · ' : '' ) . $weight_text );
if ( ! empty( $item['sku'] ) ) {
	/* translators: %s: SKU del producto */
	$measures_text = trim( $measures_text . ' · ' . sprintf( __( 'SKU %s', 'andreani-shipping' ), $item['sku'] ), ' ·' );
}
$pack      = Andreani_Products_List::pack_cell( $is_multibulto ? array_merge( $item, array( 'box' => null ) ) : $item );
$pack_text = ( $pack['store'] || $pack['deleted'] ) ? $pack['label'] : trim( $pack['label'] . ' ' . str_replace( ' cm', '', $pack['dims'] ) );
$pack_tone = $pack['deleted'] ? 'warning' : ( $pack['store'] ? 'success' : 'neutral' );
$how       = Andreani_Products_List::how_cell( $item );
$show_bigger = ! $is_missing && ( $is_multibulto || 'bigger' === $item['state'] );
$bigger_from = Andreani_Products_List::bigger_from( $item );
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
		data-mode="<?php echo esc_attr( $item['mode'] ); ?>"
		data-box-ref="<?php echo esc_attr( isset( $item['box_ref'] ) ? $item['box_ref'] : '' ); ?>"
		data-package-state="<?php echo esc_attr( isset( $item['package_state'] ) ? $item['package_state'] : 'none' ); ?>"
		data-bultos-json="<?php echo esc_attr( wp_json_encode( $item['bultos_data'] ) ); ?>"
		data-apilado-json="<?php echo esc_attr( wp_json_encode( $item['apilado'] ) ); ?>"
		data-units-per-box="<?php echo esc_attr( $item['units_per_box'] ); ?>"
		data-overflow="<?php echo esc_attr( $item['overflow'] ); ?>"
		data-missing="<?php echo $is_missing ? '1' : '0'; ?>">
		<div class="andreani-product-item__select-cell">
			<input type="checkbox" class="andreani-product-select" value="<?php echo esc_attr( $item['id'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: nombre del producto */ __( 'Seleccionar %s', 'andreani-shipping' ), $item['name'] ) ); ?>" />
		</div>
		<div class="andreani-product-item__product">
			<div class="andreani-product-item__thumb-cell">
				<?php if ( ! empty( $item['thumb_url'] ) ) : ?>
					<img class="andreani-product-item__thumb" src="<?php echo esc_url( $item['thumb_url'] ); ?>" alt="" />
				<?php else : ?>
					<svg class="andreani-product-item__box" viewBox="0 0 48 48" aria-hidden="true" data-andr-thumb="<?php echo esc_attr( wp_json_encode( $item['packages'] ) ); ?>"></svg>
				<?php endif; ?>
			</div>
			<div class="andreani-product-item__info">
				<span class="andreani-product-item__name" title="<?php echo esc_attr( $item['name'] ); ?>"><?php echo esc_html( $item['name'] ); ?></span>
				<span class="andreani-product-item__meta"<?php echo $multibox ? ' title="' . esc_attr( $multibox['title'] ) . '"' : ''; ?>><?php echo '' === $measures_text ? '&mdash;' : esc_html( $measures_text ); ?></span>
			</div>
		</div>
		<div class="andreani-product-item__how">
			<?php echo Andreani_Product_Bultos::art( $how['art'], array( 44, 36 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<span class="andreani-how">
				<span class="andreani-how__text"><?php echo esc_html( $how['text'] ); ?></span>
				<?php if ( '' !== $how['note'] ) : ?>
					<span class="andreani-how__note"><?php echo esc_html( $how['note'] ); ?></span>
				<?php endif; ?>
			</span>
		</div>
		<div class="andreani-product-item__pack">
			<?php if ( '' === $pack_text ) : ?>
				<span class="andreani-product-item__none">&mdash;</span>
			<?php else : ?>
				<span class="andr-badge andr-badge--sm andr-badge--<?php echo esc_attr( $pack_tone ); ?>" title="<?php echo esc_attr( $pack['deleted'] ? __( 'Paquete eliminado: elegí otro.', 'andreani-shipping' ) : $pack['label'] . ' ' . $pack['dims'] ); ?>"><?php echo esc_html( $pack_text ); ?></span>
			<?php endif; ?>
		</div>
		<div class="andreani-product-item__state">
			<?php if ( $is_missing ) : ?>
				<span class="andr-badge andr-badge--warning"><?php echo esc_html( $strings['status_missing'] ); ?></span>
				<span class="andr-btn andr-btn--primary andr-btn--sm andreani-product-item__complete"><?php esc_html_e( 'Completar', 'andreani-shipping' ); ?></span>
			<?php elseif ( $show_bigger ) : ?>
				<span class="andr-badge andr-badge--info"><?php echo Andreani_Product_Bultos::service_icon( 'bigger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Bigger', 'andreani-shipping' ); ?></span>
				<?php if ( '' !== $item['reason'] ) : ?>
					<span class="andreani-product-item__reason" title="<?php echo esc_attr( $item['reason'] ); ?>"><?php echo esc_html( $item['reason'] ); ?></span>
				<?php endif; ?>
			<?php else : ?>
				<span class="andr-badge andr-badge--success"><?php echo Andreani_Product_Bultos::service_icon( 'paqueteria' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php esc_html_e( 'Paquetería', 'andreani-shipping' ); ?></span>
				<?php if ( $bigger_from ) : ?>
					<span class="andreani-product-item__service-note"><?php echo esc_html( sprintf( $strings['grid_bigger_from'], $bigger_from ) ); ?></span>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<svg class="andreani-product-item__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
	</div>
	<div class="andreani-product-detail">
		<dl class="andreani-product-summary">
			<div class="andreani-product-summary__item andreani-product-summary__item--pack">
				<dt><?php esc_html_e( 'Paquete', 'andreani-shipping' ); ?></dt>
				<dd><?php echo '' === $pack_text ? '&mdash;' : esc_html( $pack_text ); ?></dd>
			</div>
		</dl>
		<div class="andreani-product-detail__content"></div>
	</div>
</div>
