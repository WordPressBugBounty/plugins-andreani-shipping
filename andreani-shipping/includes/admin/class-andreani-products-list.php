<?php
/**
 * Datos de la grilla de productos para la página "Ver mis productos".
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Products_List {

	const PER_PAGE_DEFAULT = 10;
	const PER_PAGE_OPTIONS = array( 5, 10, 25 );

	public static function resolve_per_page() {
		if ( isset( $_REQUEST['per_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$candidate = absint( $_REQUEST['per_page'] );
			if ( in_array( $candidate, self::PER_PAGE_OPTIONS, true ) ) {
				return $candidate;
			}
		}
		return self::PER_PAGE_DEFAULT;
	}

	/**
	 * Devuelve productos WooCommerce con sus dimensiones.
	 * Los productos variables se omiten; se listan sus variaciones.
	 *
	 * @param array $args {
	 *   search       string  Filtro por nombre o SKU.
	 *   missing_dims bool    Solo productos con dims incompletas.
	 *   per_page     int     Registros por página.
	 *   paged        int     Página actual.
	 * }
	 * @return array{ items: array, total: int }
	 */
	public static function get_products_data( $args = array() ) {
		$search       = isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '';
		$missing_only = ! empty( $args['missing_dims'] );
		$per_page     = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : self::PER_PAGE_DEFAULT;
		$paged        = isset( $args['paged'] ) ? max( 1, absint( $args['paged'] ) ) : 1;

		$query_args = array(
			'post_type'      => array( 'product', 'product_variation' ),
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'fields'         => 'ids',
		);

		if ( '' !== $search ) {
			$query_args['s'] = $search;
		}

		$ids   = get_posts( $query_args );
		$items = array();

		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->needs_shipping() ) {
				continue;
			}
			// Los productos variables no tienen dims propias; sus variaciones sí.
			if ( $product->is_type( 'variable' ) ) {
				continue;
			}
			$items[] = self::build_item( $product );
		}

		if ( $missing_only ) {
			$items = array_values( array_filter( $items, function ( $i ) {
				return $i['missing_dims'] || $i['missing_weight'];
			} ) );
		}

		$total  = count( $items );
		$offset = ( $paged - 1 ) * $per_page;
		$items  = array_slice( $items, $offset, $per_page );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Construye el array de datos de un producto individual.
	 *
	 * @param WC_Product $product
	 * @return array
	 */
	public static function build_item( $product ) {
		$product_id = $product->get_id();

		$thumb_id  = $product->get_image_id();
		$thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, array( 40, 40 ) ) : '';

		$weight = floatval( $product->get_weight() );
		$length = floatval( $product->get_length() );
		$width  = floatval( $product->get_width() );
		$height = floatval( $product->get_height() );

		// Separamos los faltantes: dimensiones (largo/ancho/alto) y peso. Cualquiera
		// de los dos impide cotizar (misma regla que prepare_products_for_cotizacion).
		$missing_dims   = ( $width <= 0 || $height <= 0 || $length <= 0 );
		$missing_weight = ( $weight <= 0 );

		$bultos_adicionales = class_exists( 'Andreani_Product_Bultos' )
			? Andreani_Product_Bultos::get_bultos( $product_id )
			: array();

		$edit_url = $product->is_type( 'variation' )
			? get_edit_post_link( $product->get_parent_id(), 'raw' )
			: get_edit_post_link( $product_id, 'raw' );

		$is_bigger = class_exists( 'Andreani_Product_Bultos' )
			? Andreani_Product_Bultos::is_bigger_product( $product_id )
			: false;

		return array(
			'id'           => $product_id,
			'name'         => $product->get_name(),
			'sku'          => $product->get_sku(),
			'type'         => $product->get_type(),
			'thumb_url'    => $thumb_url,
			'thumb_url_lg' => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '',
			'weight'       => $weight,
			'length'       => $length,
			'width'        => $width,
			'height'       => $height,
			'bultos'       => count( $bultos_adicionales ),
			'bultos_data'  => array_values( $bultos_adicionales ),
			'missing_dims'   => $missing_dims,
			'missing_weight' => $missing_weight,
			'is_bigger'      => $is_bigger,
			'edit_url'     => $edit_url ?: '',
		);
	}
}
