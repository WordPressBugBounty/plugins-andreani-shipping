<?php
/**
 * Reparto de los campos de la configuración entre las pestañas y textos de cada modo de entrega.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Settings_Layout {

	const FIELDS = array(
		'oculto'   => array( 'tipo_cliente', 'config_por_modo' ),
		'cuenta'   => array( 'hash_andreani' ),
		'origen'   => array( 'cp_origen' ),
		'modos'    => array( 'mostrar_sin_decimales' ),
		'checkout'  => array( 'checkout_modo', 'checkout_shortcodes_info' ),
		'cotizador' => array( 'cotizador_producto', 'cotizador_tema', 'cotizador_modo', 'cotizador_posicion' ),
		'avanzado'  => array( 'modo_debug', 'checkout_force_enqueue' ),
	);

	/**
	 * @param string $tab    Clave de FIELDS.
	 * @param array  $fields Campos del método de envío, por key.
	 * @return array Los campos de esa pestaña.
	 */
	public static function fields_for( $tab, array $fields ) {
		return isset( self::FIELDS[ $tab ] ) ? array_intersect_key( $fields, array_flip( self::FIELDS[ $tab ] ) ) : array();
	}

	/**
	 * @param string $modo_key Modo de entrega normalizado (Andreani_Api_Response::normalize_modo_key()).
	 * @return string Cuándo se ofrece, vacío si el modo no tiene texto.
	 */
	public static function modo_cuando( $modo_key ) {
		switch ( $modo_key ) {
			case 'llega-hoy':
				return __( 'Según tu código postal y la hora del pedido', 'andreani-shipping' );

			case 'estandar':
				return __( 'A domicilio', 'andreani-shipping' );

			case 'sucursal':
				return __( 'El cliente retira en una sucursal o punto Andreani', 'andreani-shipping' );

			case 'bigger':
				return sprintf(
					/* translators: 1: peso máximo de Paquetería en kg, 2: lado máximo en cm, 3: suma máxima de los tres lados en cm */
					__( 'Cuando el pedido va con varias etiquetas o supera %1$s kg, %2$s cm de lado o %3$s cm entre los tres lados', 'andreani-shipping' ),
					Andreani_Api_Config::BIGGER_WEIGHT_KG,
					Andreani_Api_Config::BIGGER_MAX_SIDE_CM,
					Andreani_Api_Config::BIGGER_SUM_SIDES_CM
				);
		}

		return '';
	}
}
