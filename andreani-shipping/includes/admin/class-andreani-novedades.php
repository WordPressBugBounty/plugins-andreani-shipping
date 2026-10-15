<?php
/**
 * Novedades del plugin: registro, vistas por usuario, globito de menú, aviso y página.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

class Andreani_Novedades {

	const META_SEEN  = 'andreani_novedades_vistas';
	const PAGE_SLUG  = 'andreani-novedades';
	const CAPABILITY = 'manage_woocommerce';
	const NONCE      = 'andreani_novedades';
	const GO_PARAM   = 'andreani_novedad_ir';
	const SCRIPT     = 'andreani-novedades';

	private static $instance = null;

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', array( $this, 'handle_go' ) );
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 30 );
		add_action( 'admin_menu', array( $this, 'add_menu_bubble' ), 999 );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_andreani_novedad_vista', array( $this, 'handle_dismiss' ) );
	}

	/**
	 * @return array<int,array{id:string,version:string,titulo:string,texto:string,accion:string,extras:array}>
	 */
	public static function items() {
		return array(
			array(
				'id'      => 'cajas-bigger',
				'version' => '1.8.2',
				'titulo'  => __( 'Nuevo: elegí cómo viajan tus pedidos grandes', 'andreani-shipping' ),
				'texto'   => __( 'Elegí si cada caja va con su etiqueta, por producto. Te lleva 1 minuto.', 'andreani-shipping' ),
				'accion'  => __( 'Configurar ahora', 'andreani-shipping' ),
				'extras'  => array(
					array(
						'titulo' => __( 'Cuántos entran en una caja', 'andreani-shipping' ),
						'texto'  => __( 'Cargalo una vez y armamos las cajas solas.', 'andreani-shipping' ),
					),
					array(
						'titulo' => __( 'Productos en varias cajas', 'andreani-shipping' ),
						'texto'  => __( 'Los productos que vienen en varias cajas ahora viajan siempre como Bigger (una etiqueta por caja, solo a domicilio).', 'andreani-shipping' ),
					),
					array(
						'titulo' => __( 'Paquetes guardados', 'andreani-shipping' ),
						'texto'  => __( 'Guardá tus cajas, sobres y bolsas y te decimos cuál usar.', 'andreani-shipping' ),
						'accion' => __( 'Ir a Paquetes guardados', 'andreani-shipping' ),
						'page'   => 'andreani-boxes',
					),
				),
			),
		);
	}

	/**
	 * @param array $item Ítem de items().
	 * @return string
	 */
	public static function url_for( array $item ) {
		$base = admin_url( 'admin.php?page=andreani-products' );

		return $base;
	}

	/**
	 * @param int $user_id ID del usuario.
	 * @return string[] IDs de las novedades que el usuario ya vio.
	 */
	public static function seen( $user_id ) {
		$value = get_user_meta( $user_id, self::META_SEEN, true );

		return is_array( $value ) ? array_values( array_unique( array_map( 'strval', $value ) ) ) : array();
	}

	/**
	 * @param int $user_id ID del usuario.
	 * @return array Novedades que el usuario todavía no vio.
	 */
	public static function unseen( $user_id ) {
		$seen = self::seen( $user_id );

		return array_values(
			array_filter(
				self::items(),
				static function ( $item ) use ( $seen ) {
					return ! in_array( $item['id'], $seen, true );
				}
			)
		);
	}

	/**
	 * @param int $user_id ID del usuario.
	 * @return int
	 */
	public static function unseen_count( $user_id ) {
		return count( self::unseen( $user_id ) );
	}

	/**
	 * @param int      $user_id ID del usuario.
	 * @param string[] $ids     IDs a marcar; los que no existen en el registro se ignoran.
	 * @return string[] Vistas del usuario tras marcar.
	 */
	public static function mark_seen( $user_id, array $ids ) {
		$known = array_column( self::items(), 'id' );
		$seen  = array_values( array_unique( array_merge( self::seen( $user_id ), array_intersect( $ids, $known ) ) ) );

		update_user_meta( $user_id, self::META_SEEN, $seen );

		return $seen;
	}

	/**
	 * @return string URL de la página de Novedades.
	 */
	public static function page_url() {
		return admin_url( 'admin.php?page=' . self::PAGE_SLUG );
	}

	/**
	 * @param int $count Novedades sin ver.
	 * @return string Globito con el patrón del core, vacío si no hay.
	 */
	public static function bubble( $count ) {
		$count = (int) $count;

		if ( $count < 1 ) {
			return '';
		}

		return ' <span class="update-plugins count-' . $count . '"><span class="plugin-count" aria-hidden="true">' . number_format_i18n( $count ) . '</span><span class="screen-reader-text">'
			/* translators: %d: cantidad de novedades sin ver */
			. sprintf( _n( '%d novedad sin ver', '%d novedades sin ver', $count, 'andreani-shipping' ), $count )
			. '</span></span>';
	}

	private function is_novedades_page() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['page'] ) && self::PAGE_SLUG === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	private function visible_count() {
		if ( ! current_user_can( self::CAPABILITY ) || $this->is_novedades_page() ) {
			return 0;
		}

		return self::unseen_count( get_current_user_id() );
	}

	public function register_submenu() {
		add_submenu_page(
			Andreani_Admin_Menu::MENU_SLUG,
			__( 'Novedades', 'andreani-shipping' ),
			__( 'Novedades', 'andreani-shipping' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function add_menu_bubble() {
		global $menu, $submenu;

		$bubble = self::bubble( $this->visible_count() );

		if ( '' === $bubble || ! is_array( $menu ) ) {
			return;
		}

		$menu = self::attach_bubble( $menu, Andreani_Admin_Menu::MENU_SLUG, $bubble );

		if ( ! empty( $submenu[ Andreani_Admin_Menu::MENU_SLUG ] ) ) {
			$submenu[ Andreani_Admin_Menu::MENU_SLUG ] = self::attach_bubble( $submenu[ Andreani_Admin_Menu::MENU_SLUG ], self::PAGE_SLUG, $bubble );
		}
	}

	/**
	 * @param array  $entries Entradas de $menu o $submenu de WordPress (el slug va en [2]).
	 * @param string $slug    Slug de la entrada que lleva el globito.
	 * @param string $bubble  HTML del globito.
	 * @return array
	 */
	public static function attach_bubble( array $entries, $slug, $bubble ) {
		foreach ( $entries as $position => $entry ) {
			if ( isset( $entry[2] ) && $slug === $entry[2] ) {
				$entries[ $position ][0] .= $bubble;
				break;
			}
		}

		return $entries;
	}

	private function on_notice_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen ) {
			return false;
		}

		if ( in_array( $screen->id, array( 'dashboard', 'toplevel_page_' . Andreani_Admin_Menu::MENU_SLUG, 'andreani_page_andreani-products' ), true ) ) {
			return true;
		}

		return Andreani_Admin_Menu::get_instance()->is_andreani_settings_screen();
	}

	private function pending_notice() {
		if ( ! current_user_can( self::CAPABILITY ) || ! $this->on_notice_screen() ) {
			return null;
		}

		$pending = self::unseen( get_current_user_id() );

		return $pending ? $pending[0] : null;
	}

	public function enqueue_assets() {
		$notice = $this->pending_notice();

		if ( ! $notice && ! $this->is_novedades_page() ) {
			return;
		}

		wp_enqueue_style(
			self::SCRIPT,
			ANDREANI_PLUGIN_URL . 'includes/assets/css/views/admin-novedades.css',
			array(
				'andreani-core-base',
				Andreani_Core_Assets::HANDLE_C_BADGE,
				Andreani_Core_Assets::HANDLE_C_BUTTON,
				Andreani_Core_Assets::HANDLE_C_PAGE_HEADER,
			),
			ANDREANI_PLUGIN_VERSION
		);

		if ( ! $notice ) {
			return;
		}

		wp_enqueue_script( self::SCRIPT, ANDREANI_PLUGIN_URL . 'includes/assets/js/andreani-novedades.js', array( 'jquery' ), ANDREANI_PLUGIN_VERSION, true );
		wp_localize_script(
			self::SCRIPT,
			'AndreaniNovedades',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE ),
			)
		);
	}

	public function render_notice() {
		$item = $this->pending_notice();

		if ( ! $item ) {
			return;
		}

		$go = add_query_arg(
			array(
				self::GO_PARAM => $item['id'],
				'_wpnonce'     => wp_create_nonce( self::NONCE ),
			),
			admin_url( 'index.php' )
		);
		?>
		<div class="notice andr-novedad is-dismissible" data-novedad="<?php echo esc_attr( $item['id'] ); ?>">
			<?php echo Andreani_Product_Bultos::service_icon( 'bigger' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<div class="andr-novedad__text">
				<strong><?php echo esc_html( $item['titulo'] ); ?></strong>
			</div>
			<div class="andr-novedad__actions">
				<a class="andr-btn andr-btn--primary andr-btn--sm" href="<?php echo esc_url( $go ); ?>"><?php echo esc_html( $item['accion'] ); ?></a>
				<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm" data-novedad-later><?php esc_html_e( 'Más tarde', 'andreani-shipping' ); ?></button>
			</div>
		</div>
		<?php
	}

	public function handle_go() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ self::GO_PARAM ] ) || ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		check_admin_referer( self::NONCE );

		$id = sanitize_key( wp_unslash( $_GET[ self::GO_PARAM ] ) );

		foreach ( self::items() as $item ) {
			if ( $item['id'] === $id ) {
				self::mark_seen( get_current_user_id(), array( $id ) );
				wp_safe_redirect( self::url_for( $item ) );
				exit;
			}
		}
	}

	public function handle_dismiss() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( array( 'message' => __( 'No tenes permisos.', 'andreani-shipping' ) ), 403 );
		}

		$id = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : '';

		self::mark_seen( get_current_user_id(), array( $id ) );

		wp_send_json_success();
	}

	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'No tenes permisos para acceder a esta pagina.', 'andreani-shipping' ) );
		}

		$items = self::items();

		self::mark_seen( get_current_user_id(), array_column( $items, 'id' ) );

		include ANDREANI_PLUGIN_DIR . 'includes/admin/views/novedades-page.php';
	}
}
