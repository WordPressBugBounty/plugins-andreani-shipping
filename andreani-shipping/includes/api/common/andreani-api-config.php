<?php
defined( 'ABSPATH' ) || exit;

class Andreani_Api_Config {
    const BIGGER_WEIGHT_KG    = 50;
    const BIGGER_SUM_SIDES_CM = 300;
    const BIGGER_MAX_SIDE_CM  = 165;

    private static $api_base_url = 'https://woocommerce-api-acom.andreani.com';

    public static function get_api_base_url() {
        return self::$api_base_url;
    }

    public static function get_endpoints( $client_type ) {
        $base_url = self::get_api_base_url();

        $common_endpoints = array(
            'sucursales'        => $base_url . '/api/v1/Branch',
            'sucursales_origen' => $base_url . '/api/v1/Branch/origin',
            'origen_default'    => $base_url . '/api/v1/Branch/default-origin',
            'login'          => $base_url . '/api/v1/Login',
            'settings'       => $base_url . '/api/v1/Settings',
            'settings_origen' => $base_url . '/api/v1/Settings/origin',
        );

        if ( $client_type === 'pyme' || $client_type === 'middle_market' ) {
            return array_merge( $common_endpoints, array(
                'cotizacion' => $base_url . '/api/v1/Pyme/rates',
                'orden'      => $base_url . '/api/v1/Pyme/ShippingRegistration',
                'etiqueta'   => $base_url . '/api/v1/Pyme/ticket',
            ));
        } else {
            return array_merge( $common_endpoints, array(
                'cotizacion' => $base_url . '/api/v1/Corporative/rates',
                'orden'      => $base_url . '/api/v1/Corporative/ShippingRegistration',
                'etiqueta'   => $base_url . '/api/v1/Corporative/ticket',
            ));
        }
    }
}
