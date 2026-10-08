<?php
/**
 * Encabezado de la grilla de cajas: los rótulos de las medidas una sola vez.
 *
 * @package AndreaniPlugin
 * @var array $strings Textos de Andreani_Product_Bultos::get_ui_strings().
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="andr-boxes__head" aria-hidden="true">
	<span></span>
	<span><?php echo esc_html( $strings['piece_reference'] ); ?></span>
	<span><?php echo esc_html( $strings['label_length'] ); ?></span>
	<span><?php echo esc_html( $strings['label_width'] ); ?></span>
	<span><?php echo esc_html( $strings['label_height'] ); ?></span>
	<span><?php echo esc_html( $strings['label_weight'] ); ?></span>
	<span></span>
</div>
