<?php
/**
 * Loader de Andreani para las vistas del admin (animación oficial + texto).
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Admin_Loader {

	const SIZE_LG = 'lg';
	const SIZE_SM = 'sm';

	private static $dimensions = array(
		self::SIZE_LG => array( 229, 150 ),
		self::SIZE_SM => array( 96, 63 ),
	);

	public static function gif_url() {
		return ANDREANI_PLUGIN_URL . 'includes/assets/img/andreani-loading.gif';
	}

	public static function shipments_phrases() {
		return array(
			__( 'Buscando tus envíos…', 'andreani-shipping' ),
			__( 'Trayendo los estados de seguimiento…', 'andreani-shipping' ),
			__( 'Casi listo…', 'andreani-shipping' ),
		);
	}

	public static function products_phrases() {
		return array(
			__( 'Cargando tus productos…', 'andreani-shipping' ),
			__( 'Revisando cómo viaja cada uno…', 'andreani-shipping' ),
			__( 'Casi listo…', 'andreani-shipping' ),
		);
	}

	public static function get( $text = '', $size = self::SIZE_LG, $phrases = array() ) {
		$size    = isset( self::$dimensions[ $size ] ) ? $size : self::SIZE_LG;
		$phrases = array_values( array_filter( (array) $phrases, 'strlen' ) );
		$label   = $phrases ? $phrases[0] : $text;

		list( $width, $height ) = self::$dimensions[ $size ];

		return sprintf(
			'<div class="andr-loader andr-loader--%1$s" role="status" aria-live="polite"%2$s><img class="andr-loader__img" src="%3$s" alt="" width="%4$d" height="%5$d"><span class="andr-loader__text">%6$s</span></div>',
			esc_attr( $size ),
			$phrases ? ' data-andr-phrases="' . esc_attr( wp_json_encode( $phrases ) ) . '"' : '',
			esc_url( self::gif_url() ),
			$width,
			$height,
			esc_html( $label )
		);
	}

	public static function render( $text = '', $size = self::SIZE_LG, $phrases = array() ) {
		echo self::get( $text, $size, $phrases ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
