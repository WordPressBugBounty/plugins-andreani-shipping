<?php

/**
 * Andreani Shipping Form Fields
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Arma el HTML del info box "Shortcodes para el modo Manual".
 *
 * El archivo se incluye con `include` (no `require_once`) desde Andreani_Shipping::init(),
 * que WC puede invocar varias veces por instance — por eso el guard con `function_exists()`.
 *
 * @return string HTML ya sanitizado.
 */
if ( ! function_exists( 'andreani_render_shortcodes_info_content' ) ) :
function andreani_render_shortcodes_info_content() {
	$shortcodes = array(
		array(
			'code'        => '[andreani_sucursales]',
			'description' => __( 'Selector de sucursales (visible solo cuando el cliente elige modo "Sucursal")', 'andreani-shipping' ),
		),
		array(
			'code'        => '[andreani_dni_field context="billing"]',
			'description' => __( 'Campo DNI dentro del bloque de facturación', 'andreani-shipping' ),
		),
		array(
			'code'        => '[andreani_dni_field context="shipping"]',
			'description' => __( 'Campo DNI dentro del bloque de envío', 'andreani-shipping' ),
		),
	);

	$css_selectors = array(
		array(
			'selector'    => '.andreani-sucursales-standalone',
			'description' => __( 'Wrapper del selector de sucursales', 'andreani-shipping' ),
		),
		array(
			'selector'    => '.andreani-sucursales-select',
			'description' => __( 'El <select> de sucursales en sí', 'andreani-shipping' ),
		),
		array(
			'selector'    => '.andreani-dni-field-shortcode',
			'description' => __( 'Wrapper del campo DNI', 'andreani-shipping' ),
		),
	);

	$copy_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
	$check_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
	$info_svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/></svg>';

	$html  = '<p class="andreani-shortcodes-intro">' . esc_html__( 'Pegá estos shortcodes en tu página de checkout donde quieras que aparezcan los elementos del plugin. Hacé click en cualquiera para copiarlo.', 'andreani-shipping' ) . '</p>';

	$html .= '<div class="andreani-code-card-list">';
	foreach ( $shortcodes as $sc ) {
		$copied_label = __( 'Copiado', 'andreani-shipping' );
		$copy_label   = __( 'Copiar al portapapeles', 'andreani-shipping' );

		$html .= '<div class="andreani-code-card andreani-copy-click" role="button" tabindex="0" data-copy-text="' . esc_attr( $sc['code'] ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: shortcode literal. */ __( 'Copiar shortcode %s', 'andreani-shipping' ), $sc['code'] ) ) . '">';
		$html .=   '<div class="andreani-code-card__main">';
		$html .=     '<code class="andreani-code-card__code">' . esc_html( $sc['code'] ) . '</code>';
		$html .=     '<span class="andreani-code-card__desc">' . esc_html( $sc['description'] ) . '</span>';
		$html .=   '</div>';
		$html .=   '<span class="andreani-code-card__copy" aria-hidden="true">';
		$html .=     '<span class="andreani-code-card__copy-icon andreani-code-card__copy-icon--default" title="' . esc_attr( $copy_label ) . '">' . $copy_svg . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG literal.
		$html .=     '<span class="andreani-code-card__copy-icon andreani-code-card__copy-icon--success">' . $check_svg . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG literal.
		$html .=     '<span class="andreani-code-card__copy-label andreani-code-card__copy-label--default">' . esc_html__( 'Copiar', 'andreani-shipping' ) . '</span>';
		$html .=     '<span class="andreani-code-card__copy-label andreani-code-card__copy-label--success">' . esc_html( $copied_label ) . '</span>';
		$html .=   '</span>';
		$html .= '</div>';
	}
	$html .= '</div>';

	$html .= '<p class="andreani-shortcodes-tip">' . wp_kses(
		__( 'Si después de cambiar a <strong>Manual</strong> no ves el selector, activá <strong>Forzar carga de assets</strong> más abajo.', 'andreani-shipping' ),
		array( 'strong' => array() )
	) . '</p>';

	$html .= '<details class="andreani-info-box__advanced andreani-code-reference">';
	$html .=   '<summary>' . esc_html__( 'Personalización con CSS', 'andreani-shipping' ) . '</summary>';
	$html .=   '<div class="andreani-code-reference__inner">';
	$html .=     '<div class="andreani-code-reference__table" role="table">';
	$html .=       '<div class="andreani-code-reference__head" role="row">';
	$html .=         '<span class="andreani-code-reference__cell andreani-code-reference__cell--selector" role="columnheader">' . esc_html__( 'Selector', 'andreani-shipping' ) . '</span>';
	$html .=         '<span class="andreani-code-reference__cell andreani-code-reference__cell--desc" role="columnheader">' . esc_html__( 'Descripción', 'andreani-shipping' ) . '</span>';
	$html .=       '</div>';
	foreach ( $css_selectors as $css ) {
		$html .=     '<div class="andreani-code-reference__row" role="row">';
		$html .=       '<span class="andreani-code-reference__cell andreani-code-reference__cell--selector" role="cell"><code>' . esc_html( $css['selector'] ) . '</code></span>';
		$html .=       '<span class="andreani-code-reference__cell andreani-code-reference__cell--desc" role="cell">' . esc_html( $css['description'] ) . '</span>';
		$html .=     '</div>';
	}
	$html .=     '</div>';
	$html .=     '<p class="andreani-code-reference__footer">';
	$html .=       '<span class="andreani-code-reference__footer-icon" aria-hidden="true">' . $info_svg . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inline SVG literal.
	$html .=       '<span>' . wp_kses( __( 'Editá desde <strong>Apariencia → Personalizar → CSS adicional</strong>. Los colores y tipografía se heredan de tu tema.', 'andreani-shipping' ), array( 'strong' => array() ) ) . '</span>';
	$html .=     '</p>';
	$html .=   '</div>';
	$html .= '</details>';

	return $html;
}
endif;

return array(
	'tipo_cliente' => array(
		'type'    => 'hidden',
		'default' => '',
	),

	// Config unificada por modo/contrato (JSON)
	// Estructura: {"modo": {"enabled": true, "costo_adicional": 0, "motivo": "", "envio_gratis": false, "envio_gratis_monto": 0}}
	'config_por_modo' => array(
		'type'    => 'hidden',
		'default' => '{}',
	),

	'hash_andreani' => array(
		'title'       => __( 'Credencial ID (*)', 'andreani-shipping' ),
		'type'        => 'password',
		'description' => __( 'Ingresa la Credencial ID proporcionada por Andreani.', 'andreani-shipping' ),
		'desc_tip'    => true,
	),

	'cp_origen' => array(
		'title'       => __( 'Código Postal Origen (*)', 'andreani-shipping' ),
		'type'        => 'text',
		'description' => __( 'Código postal de donde salen los envíos.', 'andreani-shipping' ),
		'desc_tip'    => true,
		'default'     => get_option( 'woocommerce_store_postcode', '' ),
	),

	'mostrar_sin_decimales' => array(
		'title'       => __( 'Mostrar sin decimales', 'andreani-shipping' ),
		'label'       => __( 'Redondear costos al entero más cercano', 'andreani-shipping' ),
		'type'        => 'checkbox',
		'default'     => 'no',
		'description' => __( 'Ejemplo: $2000,99 se mostrará como $2001', 'andreani-shipping' ),
		'desc_tip'    => true,
	),

	'modo_debug' => array(
		'title'       => __( 'Modo Debug', 'andreani-shipping' ),
		'label'       => __( 'Activar registro de eventos (logs)', 'andreani-shipping' ),
		'type'        => 'checkbox',
		'default'     => 'no',
		'description' => __( '<strong>Importante:</strong> los logs contienen datos personales de tus clientes (DNI, email, teléfono). Dejalo activo solo mientras diagnosticás un problema y desactivalo al terminar. Si los compartís con el soporte de Andreani, usá un canal privado. Los registros se ven en WooCommerce &gt; Estado &gt; Registros.', 'andreani-shipping' ),
		'desc_tip'    => true,
	),

	'cotizador_producto' => array(
		'title'       => __( 'Cotizador en producto', 'andreani-shipping' ),
		'type'        => 'cotizador_config',
		'default'     => 'no',
	),

	// Estos campos se renderizan dentro de cotizador_config, no como campos separados
	'cotizador_tema' => array(
		'type'    => 'cotizador_subfield',
		'default' => 'light',
	),

	'cotizador_modo' => array(
		'type'    => 'cotizador_subfield',
		'default' => 'auto',
	),

	'checkout_modo' => array(
		'title'       => __( 'Modo de renderizado del checkout', 'andreani-shipping' ),
		'type'        => 'select',
		'options'     => array(
			'auto'   => __( 'Automático', 'andreani-shipping' ),
			'manual' => __( 'Manual', 'andreani-shipping' ),
		),
		'default'     => 'auto',
		'description' => __( 'Automático: se renderiza en el checkout clásico de WooCommerce. Manual: usás shortcodes para posicionar los elementos (recomendado para Elementor u otros page builders).', 'andreani-shipping' ),
		'desc_tip'    => false,
	),

	// Info box de shortcodes — visible solo cuando checkout_modo === "manual"
	// attached=true: se pega visualmente al select de arriba (border-left de acento)
	// para que se lea como la extensión del modo Manual, no una opción separada.
	'checkout_shortcodes_info' => array(
		'type'             => 'andreani_info_box',
		'title'            => __( 'Shortcodes para el modo Manual', 'andreani-shipping' ),
		'content'          => andreani_render_shortcodes_info_content(),
		'show_when_field'  => 'checkout_modo',
		'show_when_value'  => 'manual',
		'default_open'     => false,
		'icon'             => 'code',
		'attached'         => true,
	),

	'checkout_force_enqueue' => array(
		'title'       => __( 'Forzar carga de assets', 'andreani-shipping' ),
		'type'        => 'checkbox',
		'label'       => __( 'Cargar JS/CSS del checkout en todas las páginas', 'andreani-shipping' ),
		'default'     => 'no',
		'description' => __( 'Último recurso: activá solo si el shortcode no muestra el selector de sucursales. Desde la versión 1.5.0 esto se resuelve automáticamente.', 'andreani-shipping' ),
		'desc_tip'    => true,
	),

	'cotizador_posicion' => array(
		'type'    => 'cotizador_subfield',
		'default' => 'after_add_to_cart',
	),
);
