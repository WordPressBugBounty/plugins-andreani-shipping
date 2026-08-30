<?php
/**
 * @package Andreani_Shipping
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Postcode {

	public static function normalize( $postcode ) {
		$postcode = trim( (string) $postcode );

		if ( preg_match( '/^\d{4}$/', $postcode ) ) {
			return $postcode;
		}

		if ( preg_match( '/^[A-Za-z](\d{4})[A-Za-z]{0,3}$/', $postcode, $matches ) ) {
			return $matches[1];
		}

		return $postcode;
	}

	public static function is_valid( $postcode ) {
		$postcode = trim( (string) $postcode );

		if ( preg_match( '/^\d{4}$/', $postcode ) ) {
			return true;
		}

		if ( preg_match( '/^[A-Z]\d{4}[A-Z]{3}$/i', $postcode ) ) {
			return true;
		}

		return false;
	}
}
