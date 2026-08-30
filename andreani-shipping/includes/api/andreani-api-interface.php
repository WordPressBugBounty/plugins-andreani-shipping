<?php

/**
 * Andreani_API_Interface
 *
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;


/**
 * Interface para la API de Andreani
 *
 * Define los métodos que deben implementarse en las clases de API específicas.
 */
interface Andreani_API_Interface {
    /**
     * Obtener cotización de envío
     *
     * @param array $package Paquete de WooCommerce con datos del envío
     * @param array $config Configuración del método de envío
     * @return array Array con structure: ['rates' => array, 'errors' => array]
     */
    public function get_cotizacion($package);

    /**
     * Obtener sucursales disponibles
     *
     * @param string $codigo_postal Código postal para filtrar sucursales (opcional)
     * @return array|WP_Error Array de sucursales o error
     */
    public function get_sucursales($codigo_postal = '');

    /**
     * Crear orden de envío
     *
     * @param int $order_id ID de la orden WooCommerce
     * @return array|WP_Error Respuesta de la API o error
     */
    public function create_orden($order_id);

    /**
     * Obtiene info cliente con hash y la guarda en la base de datos
     *
     * @param string $hash Hash de autenticación del cliente
     * @return bool True si la información se encontró y guardó correctamente, false en caso contrario
     */
    public function validate_hash($hash);

    /**
     * Obtener tipo de cliente
     * @return string Tipo de cliente ('pyme', 'middle_market', 'corporativo')
     */
    public static function get_client_type();

    /**
    * Obtener etiqueta
    * @param int $order_id ID de la orden WooCommerce
    * @return string Etiqueta del envio
    */
    public function get_etiqueta($order_id);

}