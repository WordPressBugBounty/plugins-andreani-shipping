<?php
/**
 * Vocabulario único de estados de un envío, compartido por Ver mis envíos y el pedido.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Shipment_Status {

	const NOT_PACKAGED = 'not_packaged';
	const PACKAGED     = 'packaged';
	const READY        = 'ready';
	const IN_TRANSIT   = 'in_transit';
	const DELIVERED    = 'delivered';
	const ERROR        = 'error';

	const FROM_INTERNAL = array(
		'not_packaged'  => self::NOT_PACKAGED,
		'packaged'      => self::PACKAGED,
		'pending_entry' => self::READY,
		'ready'         => self::READY,
		'in_transit'    => self::IN_TRANSIT,
		'ready_pickup'  => self::IN_TRANSIT,
		'not_delivered' => self::IN_TRANSIT,
		'delivered'     => self::DELIVERED,
		'error'         => self::ERROR,
	);

	/**
	 * @param string $internal  Estado interno de Andreani_Shipments_List.
	 * @param bool   $has_error Si el envío quedó sin generar por un error.
	 * @return string Uno de los seis estados del vocabulario.
	 */
	public static function unify( $internal, $has_error = false ) {
		if ( $has_error && self::NOT_PACKAGED === $internal ) {
			return self::ERROR;
		}

		return isset( self::FROM_INTERNAL[ $internal ] ) ? self::FROM_INTERNAL[ $internal ] : self::ERROR;
	}

	/**
	 * @param string $status Estado del vocabulario.
	 * @return string
	 */
	public static function label( $status ) {
		$labels = array(
			self::NOT_PACKAGED => __( 'Por empaquetar', 'andreani-shipping' ),
			self::PACKAGED     => __( 'Empaquetado', 'andreani-shipping' ),
			self::READY        => __( 'Listo', 'andreani-shipping' ),
			self::IN_TRANSIT   => __( 'En camino', 'andreani-shipping' ),
			self::DELIVERED    => __( 'Entregado', 'andreani-shipping' ),
			self::ERROR        => __( 'Con error', 'andreani-shipping' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels[ self::ERROR ];
	}

	/**
	 * @param string $status Estado del vocabulario.
	 * @return string[] Estados internos que se muestran con ese estado.
	 */
	public static function internals_for( $status ) {
		return array_keys( array_filter( self::FROM_INTERNAL, static function ( $unified ) use ( $status ) {
			return $unified === $status;
		} ) );
	}
}
