<?php
/**
 * Datos de la grilla de productos para la página "Productos".
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

	public static function clamp_per_page( $per_page ) {
		return max( 1, min( absint( $per_page ), max( self::PER_PAGE_OPTIONS ) ) );
	}

	/**
	 * Consulta acotada sobre la clasificación persistida: filtra, busca por nombre
	 * y por SKU (el de Andreani y el nativo de Woo) y pagina en SQL.
	 *
	 * @param array $args {
	 *   search  string Texto a buscar.
	 *   filter  string Filtro de Andreani_Products_Stats.
	 *   orderby string 'title' o 'sales'.
	 *   limit   int    Tope de filas.
	 *   offset  int    Filas a saltear.
	 *   count   bool   Devolver solo el total.
	 * }
	 * @return string SQL ya preparado.
	 */
	public static function build_query( array $args ) {
		global $wpdb;

		$search     = isset( $args['search'] ) ? trim( (string) $args['search'] ) : '';
		$conditions = Andreani_Products_Stats::filter_conditions( isset( $args['filter'] ) ? $args['filter'] : '' );
		$classes    = ! empty( $args['service'] ) ? $args['service'] : $conditions['classes'];
		$modes      = ! empty( $args['modes'] ) ? $args['modes'] : ( '' !== $conditions['mode'] ? array( $conditions['mode'] ) : array() );
		$lookup     = $wpdb->prefix . 'wc_product_meta_lookup';

		$joins  = $wpdb->prepare( "LEFT JOIN {$wpdb->postmeta} cls ON cls.post_id = p.ID AND cls.meta_key = %s", Andreani_Products_Stats::META_CLASS ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$where  = array( "p.post_type IN ('product', 'product_variation')", "p.post_status = 'publish'" );
		$joins .= " LEFT JOIN {$lookup} l ON l.product_id = p.ID";

		if ( empty( $classes ) ) {
			$where[] = $wpdb->prepare( '(cls.meta_value IS NULL OR cls.meta_value <> %s)', Andreani_Products_Stats::CLASS_NA ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$where[] = $wpdb->prepare( 'cls.meta_value IN (' . implode( ', ', array_fill( 0, count( $classes ), '%s' ) ) . ')', $classes ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( ! empty( $modes ) ) {
			$joins  .= $wpdb->prepare( " INNER JOIN {$wpdb->postmeta} md ON md.post_id = p.ID AND md.meta_key = %s AND md.meta_value IN (" . implode( ', ', array_fill( 0, count( $modes ), '%s' ) ) . ')', array_merge( array( Andreani_Products_Stats::META_MODE ), $modes ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$joins  .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} own ON own.post_id = p.ID AND own.meta_key = %s", Andreani_Product_Bultos::SKU_META_KEY ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$where[] = $wpdb->prepare( '(p.post_title LIKE %s OR l.sku LIKE %s OR own.meta_value LIKE %s)', $like, $like, $like ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$from = "FROM {$wpdb->posts} p {$joins} WHERE " . implode( ' AND ', $where );

		if ( ! empty( $args['count'] ) ) {
			return "SELECT COUNT(*) {$from}";
		}

		$order  = ( isset( $args['orderby'] ) && 'sales' === $args['orderby'] ) ? 'COALESCE(l.total_sales, 0) DESC, p.post_title ASC' : 'p.post_title ASC';
		$limit  = max( 1, (int) ( isset( $args['limit'] ) ? $args['limit'] : self::PER_PAGE_DEFAULT ) );
		$offset = max( 0, (int) ( isset( $args['offset'] ) ? $args['offset'] : 0 ) );

		return "SELECT p.ID {$from} ORDER BY {$order}, p.ID ASC LIMIT {$limit} OFFSET {$offset}";
	}

	/**
	 * @param array $args Ver build_query().
	 * @return int[]
	 */
	public static function query_ids( array $args ) {
		global $wpdb;

		return array_map( 'intval', (array) $wpdb->get_col( self::build_query( $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * @param array $args Ver build_query().
	 * @return int
	 */
	public static function query_total( array $args ) {
		global $wpdb;

		return (int) $wpdb->get_var( self::build_query( array_merge( $args, array( 'count' => true ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Devuelve productos WooCommerce con sus dimensiones.
	 * Los productos variables se omiten; se listan sus variaciones.
	 *
	 * @param array $args {
	 *   search   string  Filtro por nombre o SKU.
	 *   filter   string  Uno de los filtros de Andreani_Products_Stats.
	 *   service  array   Servicios (OR entre sí): paqueteria, bigger, missing.
	 *   modes    array   Modos de despacho (OR entre sí): single, apilado, multibulto.
	 *   per_page int     Registros por página.
	 *   paged    int     Página actual.
	 * }
	 * @return array{ items: array, total: int }
	 */
	public static function get_products_data( $args = array() ) {
		$per_page = self::clamp_per_page( isset( $args['per_page'] ) ? $args['per_page'] : self::PER_PAGE_DEFAULT );
		$paged    = isset( $args['paged'] ) ? max( 1, absint( $args['paged'] ) ) : 1;

		$query = array(
			'search' => isset( $args['search'] ) ? sanitize_text_field( $args['search'] ) : '',
			'filter'  => isset( $args['filter'] ) ? Andreani_Products_Stats::sanitize_filter( $args['filter'] ) : '',
			'service' => isset( $args['service'] ) ? Andreani_Products_Stats::sanitize_list( $args['service'], Andreani_Products_Stats::SERVICES ) : array(),
			'modes'   => isset( $args['modes'] ) ? Andreani_Products_Stats::sanitize_list( $args['modes'], Andreani_Products_Stats::MODES ) : array(),
		);

		$total = self::query_total( $query );
		$items = array();

		if ( $total > 0 ) {
			$ids = self::query_ids( array_merge( $query, array(
				'limit'  => $per_page,
				'offset' => ( $paged - 1 ) * $per_page,
			) ) );

			foreach ( $ids as $id ) {
				$product = wc_get_product( $id );
				if ( $product ) {
					$items[] = self::build_item( $product );
				}
			}
		}

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

		$bultos_adicionales = Andreani_Package_Builder::resolve_bultos( $product );
		$apilado            = Andreani_Package_Builder::resolve_apilado( $product );
		$classification     = Andreani_Products_Stats::classify_product( $product );

		$edit_url = $product->is_type( 'variation' )
			? get_edit_post_link( $product->get_parent_id(), 'raw' )
			: get_edit_post_link( $product_id, 'raw' );

		$thumb_units = ! empty( $apilado ) ? min( 3, (int) $apilado['maxStackableUnits'] ) : 1;

		return array(
			'id'           => $product_id,
			'name'         => $product->get_name(),
			'sku'          => Andreani_Product_Bultos::get_effective_sku( $product ),
			'woo_sku'      => (string) $product->get_sku( 'edit' ),
			'type'         => $product->get_type(),
			'thumb_url'    => $thumb_url,
			'thumb_url_lg' => $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'thumbnail' ) : '',
			'weight'       => floatval( $product->get_weight() ),
			'length'       => floatval( $product->get_length() ),
			'width'        => floatval( $product->get_width() ),
			'height'       => floatval( $product->get_height() ),
			'bultos'       => count( $bultos_adicionales ),
			'main_ref'     => Andreani_Package_Builder::resolve_main_ref( $product ),
			'bultos_data'  => Andreani_Product_Bultos::to_store_units( Andreani_Order_Mapper::get_bultos_adicionales( $product_id ) ),
			'boxes'        => Andreani_Product_Bultos::MODE_MULTIBULTO === $classification['mode'] ? Andreani_Product_Bultos::to_store_units( $bultos_adicionales ) : array(),
			'apilado'      => $apilado,
			'state'        => $classification['state'],
			'mode'         => $classification['mode'],
			'reason'       => self::reason_text( $classification ),
			'packages'     => 'missing' === $classification['state'] ? array() : Andreani_Package_Builder::draw_packages_for_product( $product, $thumb_units ),
			'edit_url'     => $edit_url ?: '',
		);
	}

	/**
	 * @param array $classification Resultado de Andreani_Products_Stats::classify().
	 * @return string Motivo por el que no entra en paquetería, o cadena vacía.
	 */
	private static function reason_text( array $classification ) {
		if ( 'bigger' !== $classification['state'] ) {
			return '';
		}

		$thresholds = Andreani_Product_Bultos::get_canonical_thresholds();
		$formats    = array(
			/* translators: 1: peso del producto, 2: máximo de paquetería */
			'weight'    => __( 'Pesa %1$s kg (máx. %2$s)', 'andreani-shipping' ),
			/* translators: 1: lado más largo, 2: máximo de paquetería */
			'max_side'  => __( 'Lado %1$s cm (máx. %2$s)', 'andreani-shipping' ),
			/* translators: 1: suma de lados, 2: máximo de paquetería */
			'sum_sides' => __( 'Suma %1$s cm (máx. %2$s)', 'andreani-shipping' ),
		);

		if ( ! isset( $formats[ $classification['reason'] ] ) ) {
			return '';
		}

		return sprintf(
			$formats[ $classification['reason'] ],
			Andreani_Product_Bultos::format_preview_number( $classification['value'] ),
			Andreani_Product_Bultos::format_preview_number( $thresholds[ $classification['reason'] ] )
		);
	}

	/**
	 * Medidas de un producto que son varias cajas: cantidad, detalle por caja y
	 * peso total de una unidad, en las unidades de la tienda.
	 *
	 * @param array  $item           Ítem de build_item().
	 * @param string $dimension_unit Unidad de medidas de la tienda.
	 * @return array{count:int,title:string,weight:float}|null Null si no son varias cajas.
	 */
	public static function multibox_measures( array $item, $dimension_unit ) {
		if ( empty( $item['boxes'] ) ) {
			return null;
		}

		$sides  = static function ( $length, $width, $height ) use ( $dimension_unit ) {
			return round( (float) $length, 2 ) . ' × ' . round( (float) $width, 2 ) . ' × ' . round( (float) $height, 2 ) . ' ' . $dimension_unit;
		};
		/* translators: %d: número de caja */
		$label  = __( 'Caja %d', 'andreani-shipping' );
		$parts  = array( sprintf( $label, 1 ) . ' · ' . $sides( $item['length'], $item['width'], $item['height'] ) );
		$weight = (float) $item['weight'];

		foreach ( array_values( $item['boxes'] ) as $position => $box ) {
			$parts[] = sprintf( $label, $position + 2 ) . ' · ' . $sides( $box['depth'], $box['width'], $box['height'] );
			$weight += (float) $box['weight'];
		}

		return array(
			'count'  => count( $parts ),
			'title'  => implode( ' · ', $parts ),
			'weight' => round( $weight, 3 ),
		);
	}

	/**
	 * @param array $item Ítem de build_item().
	 * @return string Cómo viaja cuando compran varias.
	 */
	public static function how_it_travels( array $item ) {
		$strings = Andreani_Product_Bultos::get_ui_strings();

		if ( Andreani_Product_Bultos::MODE_APILADO === $item['mode'] ) {
			/* translators: %d: unidades por pila */
			return sprintf( __( 'Se apilan de a %d', 'andreani-shipping' ), (int) $item['apilado']['maxStackableUnits'] );
		}

		if ( Andreani_Product_Bultos::MODE_MULTIBULTO === $item['mode'] ) {
			/* translators: %d: cajas que forman una unidad */
			return sprintf( __( 'Viaja en %d cajas', 'andreani-shipping' ), (int) $item['bultos'] + 1 );
		}

		return $strings['mode_single_title'];
	}
}
