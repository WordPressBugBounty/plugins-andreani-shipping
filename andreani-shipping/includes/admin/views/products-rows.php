<?php
/**
 * Partial: filas de la lista de productos y su paginación.
 *
 * @package AndreaniPlugin
 * @var array $items       Ítems de Andreani_Products_List::build_item().
 * @var int   $paged       Página actual.
 * @var int   $total_pages Total de páginas.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="andreani-products-list">
	<div class="andreani-product-item andreani-product-item--head" aria-hidden="true">
		<span class="andreani-product-item__select-cell"></span>
		<span class="andreani-product-item__product"><?php esc_html_e( 'Producto', 'andreani-shipping' ); ?></span>
		<span class="andreani-product-item__how"><?php esc_html_e( 'Cómo viaja', 'andreani-shipping' ); ?></span>
		<span class="andreani-product-item__pack"><?php esc_html_e( 'Paquete', 'andreani-shipping' ); ?></span>
		<span class="andreani-product-item__state"><?php esc_html_e( 'Servicio', 'andreani-shipping' ); ?></span>
		<span class="andreani-product-item__chevron-cell"></span>
	</div>
	<?php foreach ( $items as $item ) : ?>
		<?php require ANDREANI_PLUGIN_DIR . 'includes/admin/views/products-row.php'; ?>
	<?php endforeach; ?>
</div>
<?php require ANDREANI_PLUGIN_DIR . 'includes/admin/views/pagination.php'; ?>
