<?php
/**
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Tracking_Notifier {

	const EMAIL_ID    = 'andreani_customer_tracking';
	const EMAIL_CLASS = 'Andreani_Email_Tracking';
	const ACTION      = 'andreani_tracking_updated';
	const SENT_META   = '_andreani_tracking_email_sent';
	const LOCK_PREFIX = 'andreani_tracking_email_lock_';
	const LOCK_TTL    = 300;
	const PREVIEW_TRACKING = '360000000000001';
	const ENABLED_AT_OPTION = 'andreani_tracking_email_enabled_at';
	const DISPATCHED_STATUSES = array( 'en camino', 'listo para retirar' );

	const PICKUP_EMAIL_ID         = 'andreani_customer_ready_pickup';
	const PICKUP_EMAIL_CLASS      = 'Andreani_Email_Ready_Pickup';
	const PICKUP_SENT_META        = '_andreani_ready_pickup_email_sent';
	const PICKUP_LOCK_PREFIX      = 'andreani_ready_pickup_email_lock_';
	const PICKUP_ENABLED_AT_OPTION = 'andreani_ready_pickup_email_enabled_at';
	const PICKUP_STATUS           = 'listo para retirar';
	const TRACKING_STATUS         = 'en camino';

	const SEND_ACTION = 'andreani_send_customer_email';
	const AS_GROUP    = 'andreani';

	const DEFINITIONS = array(
		self::EMAIL_ID        => array(
			'class'      => self::EMAIL_CLASS,
			'sent_meta'  => self::SENT_META,
			'lock'       => self::LOCK_PREFIX,
			'enabled_at' => self::ENABLED_AT_OPTION,
			'file'       => 'class-andreani-email-tracking.php',
		),
		self::PICKUP_EMAIL_ID => array(
			'class'      => self::PICKUP_EMAIL_CLASS,
			'sent_meta'  => self::PICKUP_SENT_META,
			'lock'       => self::PICKUP_LOCK_PREFIX,
			'enabled_at' => self::PICKUP_ENABLED_AT_OPTION,
			'file'       => 'class-andreani-email-ready-pickup.php',
		),
	);

	public static function register() {
		add_filter( 'woocommerce_email_classes', array( __CLASS__, 'register_email' ) );
		add_action( self::ACTION, array( __CLASS__, 'notify' ), 10, 3 );
		add_action( self::SEND_ACTION, array( __CLASS__, 'send_queued' ), 10, 3 );
		add_filter( 'woocommerce_prepare_email_for_preview', array( __CLASS__, 'prepare_preview' ) );

		foreach ( array_keys( self::DEFINITIONS ) as $email_id ) {
			add_action(
				'add_option_' . self::settings_key( $email_id ),
				function ( $option, $value ) use ( $email_id ) {
					self::stamp_if_turned_on( array(), $value, $email_id );
				},
				10,
				2
			);
			add_action(
				'update_option_' . self::settings_key( $email_id ),
				function ( $old, $new ) use ( $email_id ) {
					self::stamp_if_turned_on( $old, $new, $email_id );
				},
				10,
				2
			);
		}
	}

	public static function email_ids() {
		return array_keys( self::DEFINITIONS );
	}

	public static function is_own_email_id( $email_id ) {
		return isset( self::DEFINITIONS[ $email_id ] );
	}

	public static function email_class( $email_id ) {
		return self::DEFINITIONS[ $email_id ]['class'];
	}

	public static function stamp_if_turned_on( $old_settings, $new_settings, $email_id = self::EMAIL_ID ) {
		if ( self::settings_enabled( $new_settings ) && ! self::settings_enabled( $old_settings ) ) {
			update_option( self::DEFINITIONS[ $email_id ]['enabled_at'], time(), false );
		}
	}

	public static function backfill_enabled_at( $email_id = self::EMAIL_ID ) {
		$option = self::DEFINITIONS[ $email_id ]['enabled_at'];

		if ( self::is_enabled( $email_id ) && (int) get_option( $option, 0 ) <= 0 ) {
			update_option( $option, time(), false );
		}
	}

	private static function settings_enabled( $settings ) {
		return is_array( $settings ) && isset( $settings['enabled'] ) && 'yes' === $settings['enabled'];
	}

	public static function prepare_preview( $email ) {
		if ( is_object( $email ) && self::is_own_email_id( $email->id ) && '' === (string) $email->tracking_number ) {
			$email->tracking_number                   = self::PREVIEW_TRACKING;
			$email->placeholders['{tracking_number}'] = self::PREVIEW_TRACKING;
		}

		return $email;
	}

	public static function register_email( $emails ) {
		foreach ( self::DEFINITIONS as $definition ) {
			require_once ANDREANI_PLUGIN_DIR . 'includes/notifications/' . $definition['file'];

			$class = $definition['class'];

			$emails[ $class ] = new $class();
		}

		return $emails;
	}

	public static function settings_key( $email_id = self::EMAIL_ID ) {
		return 'woocommerce_' . $email_id . '_settings';
	}

	public static function is_enabled( $email_id = self::EMAIL_ID ) {
		return self::settings_enabled( get_option( self::settings_key( $email_id ), array() ) );
	}

	public static function set_enabled( $enabled, $email_id = self::EMAIL_ID ) {
		$settings = get_option( self::settings_key( $email_id ), array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$old                 = $settings;
		$settings['enabled'] = $enabled ? 'yes' : 'no';
		update_option( self::settings_key( $email_id ), $settings );
		self::stamp_if_turned_on( $old, $settings, $email_id );
	}

	public static function current_subject( $email_id ) {
		$settings = get_option( self::settings_key( $email_id ), array() );
		$subject  = is_array( $settings ) && isset( $settings['subject'] ) ? trim( (string) $settings['subject'] ) : '';

		if ( '' !== $subject ) {
			return $subject;
		}

		if ( function_exists( 'WC' ) ) {
			$emails = WC()->mailer()->get_emails();
			$class  = self::email_class( $email_id );

			if ( ! empty( $emails[ $class ] ) ) {
				return (string) $emails[ $class ]->get_default_subject();
			}
		}

		return '';
	}

	/**
	 * Un pedido es a sucursal o punto HOP cuando el checkout guardo el codigo de la sucursal elegida:
	 * es el mismo dato con el que el alta pide la entrega en sucursal.
	 */
	public static function is_pickup_order( $order ) {
		return is_object( $order ) && '' !== trim( (string) $order->get_meta( '_shipping_branch_code', true ) );
	}

	/**
	 * El tracking existe desde el alta, antes de que la tienda entregue el paquete: avisar ahi
	 * le muestra al comprador un seguimiento que todavia no se mueve. Sale recien con el envio despachado.
	 *
	 * Con "listo para retirar" sale el mail de retiro; el de "en camino" solo hace de respaldo si el de
	 * retiro no va a salir para ese pedido, para no mandar dos mails en el mismo cambio de estado.
	 *
	 * @param WC_Order $order
	 * @param string   $tracking
	 * @param string   $status TrackingStatus de la API de envios.
	 * @return bool True si el mail salio o quedo encolado.
	 */
	public static function notify( $order, $tracking, $status = '' ) {
		$tracking = trim( (string) $tracking );

		if ( '' === $tracking || ! is_object( $order ) ) {
			return false;
		}

		$email_id = self::email_for( $order, $status );
		if ( null === $email_id ) {
			return false;
		}

		if ( ! self::is_enabled( $email_id ) || self::already_sent( $order, $email_id ) ) {
			return false;
		}

		if ( ! self::created_since_enabled( $order, $email_id ) ) {
			return false;
		}

		$recipient = trim( (string) $order->get_billing_email() );
		if ( '' === $recipient || ! is_email( $recipient ) ) {
			return false;
		}

		$order_id = (int) $order->get_id();
		if ( ! self::acquire_lock( $order_id, $email_id ) ) {
			return false;
		}

		try {
			$order = self::fresh( $order );
			if ( self::already_sent( $order, $email_id ) ) {
				return false;
			}

			$order->update_meta_data( self::DEFINITIONS[ $email_id ]['sent_meta'], time() );
			$order->save();

			if ( static::enqueue( $order_id, $email_id, $tracking ) ) {
				return true;
			}

			return static::deliver( $order, $email_id, $tracking );
		} finally {
			self::release_lock( $order_id, $email_id );
		}
	}

	public static function send_queued( $order_id, $email_id, $tracking ) {
		if ( ! self::is_own_email_id( $email_id ) ) {
			return false;
		}

		$order = static::load_order( (int) $order_id );
		if ( ! $order ) {
			return false;
		}

		return static::deliver( $order, $email_id, trim( (string) $tracking ) );
	}

	private static function email_for( $order, $status ) {
		$status = strtolower( trim( (string) $status ) );

		if ( self::TRACKING_STATUS === $status ) {
			return self::EMAIL_ID;
		}

		if ( self::PICKUP_STATUS !== $status ) {
			return null;
		}

		if ( self::pickup_applies( $order ) ) {
			return self::PICKUP_EMAIL_ID;
		}

		return self::EMAIL_ID;
	}

	private static function pickup_applies( $order ) {
		return self::is_enabled( self::PICKUP_EMAIL_ID )
			&& self::is_pickup_order( $order )
			&& self::created_since_enabled( $order, self::PICKUP_EMAIL_ID );
	}

	protected static function enqueue( $order_id, $email_id, $tracking ) {
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			return false;
		}

		return (int) as_enqueue_async_action( self::SEND_ACTION, array( $order_id, $email_id, $tracking ), self::AS_GROUP ) > 0;
	}

	protected static function load_order( $order_id ) {
		return function_exists( 'wc_get_order' ) ? wc_get_order( $order_id ) : false;
	}

	protected static function deliver( $order, $email_id, $tracking ) {
		$order_id  = (int) $order->get_id();
		$recipient = trim( (string) $order->get_billing_email() );
		$label     = self::PICKUP_EMAIL_ID === $email_id ? 'aviso de retiro' : 'aviso de seguimiento';

		if ( ! static::dispatch( $order, $tracking, $email_id ) ) {
			$order->delete_meta_data( self::DEFINITIONS[ $email_id ]['sent_meta'] );
			$order->save();
			Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] No se pudo enviar el {$label}", 'error' );
			return false;
		}

		if ( self::PICKUP_EMAIL_ID === $email_id ) {
			/* translators: %s: email del comprador */
			$note = __( 'Andreani: aviso de retiro enviado a %s', 'andreani-shipping' );
		} else {
			/* translators: %s: email del comprador */
			$note = __( 'Andreani: aviso de seguimiento enviado a %s', 'andreani-shipping' );
		}

		$order->add_order_note( sprintf( $note, $recipient ), false );
		Andreani_Utils::andreani_log( "[ORDEN #{$order_id}] " . ucfirst( $label ) . ' enviado', 'info' );

		return true;
	}

	protected static function dispatch( $order, $tracking, $email_id = self::EMAIL_ID ) {
		if ( ! function_exists( 'WC' ) ) {
			return false;
		}

		$emails = WC()->mailer()->get_emails();
		$class  = self::email_class( $email_id );
		if ( empty( $emails[ $class ] ) ) {
			return false;
		}

		return (bool) $emails[ $class ]->trigger( $order, $tracking );
	}

	/**
	 * La grilla de envios completa desde la API el tracking de pedidos de meses atras con el meta vacio:
	 * sin este corte, prender el aviso les manda "en camino" a compras ya entregadas.
	 */
	private static function created_since_enabled( $order, $email_id ) {
		$since = (int) get_option( self::DEFINITIONS[ $email_id ]['enabled_at'], 0 );
		if ( $since <= 0 || ! method_exists( $order, 'get_date_created' ) ) {
			return false;
		}

		$created = $order->get_date_created();

		return $created && $created->getTimestamp() >= $since;
	}

	public static function is_dispatched( $status ) {
		return in_array( strtolower( trim( (string) $status ) ), self::DISPATCHED_STATUSES, true );
	}

	private static function already_sent( $order, $email_id ) {
		return ! empty( $order->get_meta( self::DEFINITIONS[ $email_id ]['sent_meta'], true ) );
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
	private static function acquire_lock( $order_id, $email_id ) {
		$key = self::DEFINITIONS[ $email_id ]['lock'] . $order_id;

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

	private static function release_lock( $order_id, $email_id ) {
		delete_option( self::DEFINITIONS[ $email_id ]['lock'] . $order_id );
	}
}
