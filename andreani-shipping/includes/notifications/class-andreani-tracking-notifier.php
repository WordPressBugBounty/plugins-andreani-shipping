<?php
/**
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Tracking_Notifier {

	const EMAIL_ID    = 'andreani_customer_tracking';
	const EMAIL_CLASS = 'Andreani_Email_Tracking';
	const ACTION      = 'andreani_tracking_assigned';
	const SENT_META   = '_andreani_tracking_email_sent';
	const LOCK_PREFIX = 'andreani_tracking_email_lock_';
	const LOCK_TTL    = 300;
	const PREVIEW_TRACKING = '360000000000001';
	const ENABLED_AT_OPTION = 'andreani_tracking_email_enabled_at';

	public static function register() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_email' ) );
		add_action( self::ACTION, array( __CLASS__, 'notify' ), 10, 2 );
		add_filter( 'woocommerce_prepare_email_for_preview', array( __CLASS__, 'prepare_preview' ) );
		add_action( 'add_option_' . self::settings_key(), array( __CLASS__, 'on_settings_added' ), 10, 2 );
		add_action( 'update_option_' . self::settings_key(), array( __CLASS__, 'stamp_if_turned_on' ), 10, 2 );
	}

	public static function on_settings_added( $option, $value ) {
		self::stamp_if_turned_on( array(), $value );
	}

	public static function stamp_if_turned_on( $old_settings, $new_settings ) {
		if ( self::settings_enabled( $new_settings ) && ! self::settings_enabled( $old_settings ) ) {
			update_option( self::ENABLED_AT_OPTION, time(), false );
		}
	}

	public static function backfill_enabled_at() {
		if ( self::is_enabled() && (int) get_option( self::ENABLED_AT_OPTION, 0 ) <= 0 ) {
			update_option( self::ENABLED_AT_OPTION, time(), false );
		}
	}

	private static function settings_enabled( $settings ) {
		return is_array( $settings ) && isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];
	}

	public static function prepare_preview( $email ) {
		if ( is_object( $email ) && self::EMAIL_ID === $email->id && '' === (string) $email->tracking_number ) {
			$email->tracking_number                   = self::PREVIEW_TRACKING;
			$email->placeholders['{tracking_number}'] = self::PREVIEW_TRACKING;
		}

		return $email;
	}

	public static function register_email( $emails ) {
		require_once ANDREANI_PLUGIN_DIR . 'includes/notifications/class-andreani-email-tracking.php';

		$emails[ self::EMAIL_CLASS ] = new Andreani_Email_Tracking();

		return $emails;
	}

	public static function settings_key() {
		return 'woocommerce_' . self::EMAIL_ID . '_settings';
	}

	public static function is_enabled() {
		return self::settings_enabled( get_option( self::settings_key(), array() ) );
	}

	public static function set_enabled( $enabled ) {
		$settings = get_option( self::settings_key(), array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$old                 = $settings;
		$settings['enabled'] = $enabled ? 'yes' : 'no';
		update_option( self::settings_key(), $settings );
		self::stamp_if_turned_on( $old, $settings );
	}

	/**
	 * Solo avisa en la transicion vacio -> valor que informan los escritores del tracking:
	 * al activar la opcion, los pedidos que ya tenian numero no reciben nada.
	 *
	 * @param WC_Order $order
	 * @param string   $tracking
	 * @return bool True si el mail salio.
	 */
	public static function notify( $order, $tracking ) {
		$tracking = trim( (string) $tracking );

		if ( '' === $tracking || ! is_object( $order ) || ! self::is_enabled() || self::already_sent( $order ) ) {
			return false;
		}

		if ( ! self::created_since_enabled( $order ) ) {
			return false;
		}

		$recipient = trim( (string) $order->get_billing_email() );
		if ( '' === $recipient || ! is_email( $recipient ) ) {
			return false;
		}

		$order_id = (int) $order->get_id();
		if ( ! self::acquire_lock( $order_id ) ) {
			return false;
		}

		try {
			$order = self::fresh( $order );
			if ( self::already_sent( $order ) ) {
				return false;
			}

			$order->update_meta_data( self::SENT_META, time() );
			$order->save();

			if ( ! static::dispatch( $order, $tracking ) ) {
				$order->delete_meta_data( self::SENT_META );
				$order->save();
				Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] No se pudo enviar el aviso de seguimiento", 'error' );
				return false;
			}

			$order->add_order_note(
				sprintf(
					/* translators: %s: email del comprador */
					__( 'Andreani: aviso de seguimiento enviado a %s', 'andreani-shipping' ),
					$recipient
				),
				false
			);
			Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] Aviso de seguimiento enviado", 'info' );

			return true;
		} finally {
			self::release_lock( $order_id );
		}
	}

	protected static function dispatch( $order, $tracking ) {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$emails = WC()->mailer()->get_emails();
		if ( empty( $emails[ self::EMAIL_CLASS ] ) ) {
			return false;
		}

		return (bool) $emails[ self::EMAIL_CLASS ]->trigger( $order, $tracking );
	}

	/**
	 * La grilla de envios completa desde la API el tracking de pedidos de meses atras con el meta vacio:
	 * sin este corte, prender el aviso les manda "en camino" a compras ya entregadas.
	 */
	private static function created_since_enabled( $order ) {
		$since = (int) get_option( self::ENABLED_AT_OPTION, 0 );
		if ( $since <= 0 || ! method_exists( $order, 'get_date_created' ) ) {
			return false;
		}

		$created = $order->get_date_created();

		return $created && $created->getTimestamp() >= $since;
	}

	private static function already_sent( $order ) {
		return ! empty( $order->get_meta( self::SENT_META, true ) );
	}

	private static function fresh( $order ) {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return $order;
		}

		$reloaded = wc_get_order( $order->get_id() );

		return $reloaded ? $reloaded : $order;
	}

	/**
	 * `add_option` es el unico lock atomico de WP (mismo criterio que el alta de la orden):
	 * un get+set de transient dejaria pasar a dos procesos que ven la orden sin flag a la vez.
	 */
	private static function acquire_lock( $order_id ) {
		$key = self::LOCK_PREFIX . $order_id;

		if ( add_option( $key, time(), '', false ) ) {
			return true;
		}

		$locked_at = (int) get_option( $key );
		if ( $locked_at > 0 && ( time() - $locked_at ) > self::LOCK_TTL ) {
			update_option( $key, time(), false );
			return true;
		}

		return false;
	}

	private static function release_lock( $order_id ) {
		delete_option( self::LOCK_PREFIX . $order_id );
	}
}
