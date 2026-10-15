<?php
/**
 * Template: Metabox de Andreani (página del pedido).
 *
 * @package AndreaniPlugin
 * @var array $data Datos del envío (ya hidratados desde la API cuando aplica).
 */

defined( 'ABSPATH' ) || exit;

$tracking     = ! empty( $data['display_tracking'] ) ? $data['display_tracking'] : $data['tracking_number'];
$shipments_url = add_query_arg(
	array(
		'page' => 'andreani-shipping',
		's'    => $data['order_id'],
	),
	admin_url( 'admin.php' )
);
?>
<div class="andreani-metabox andreani-metabox--<?php echo esc_attr( $data['status_key'] ); ?>">

	<?php if ( ! empty( $data['api_failure'] ) ) : ?>
		<p class="andreani-metabox__api-note" role="status">
			<span class="dashicons dashicons-cloud"></span>
			<?php esc_html_e( 'Datos locales (sin conexión en línea).', 'andreani-shipping' ); ?>
		</p>
	<?php endif; ?>

	<div class="andreani-metabox__status-row">
		<span class="andr-status andr-status--dot andr-status--sm andr-status--<?php echo esc_attr( str_replace( '_', '-', $data['status_key'] ) ); ?>"><?php echo esc_html( $data['status_text'] ); ?></span>
		<span class="andreani-metabox__meta"><?php echo esc_html( $data['service_text'] ); ?></span>
	</div>

	<?php if ( ! empty( $tracking ) ) : ?>
		<div class="andreani-metabox__section">
			<span class="andreani-metabox__section-title"><?php esc_html_e( 'N° de seguimiento', 'andreani-shipping' ); ?></span>
			<div class="andreani-metabox__tracking">
				<code class="andreani-copy-number andreani-copy-click" data-copy-text="<?php echo esc_attr( $tracking ); ?>" role="button" tabindex="0" title="<?php esc_attr_e( 'Click para copiar', 'andreani-shipping' ); ?>"><?php echo esc_html( $tracking ); ?></code>
				<span class="andreani-metabox__copy-hint" aria-hidden="true">
					<span class="dashicons dashicons-admin-page"></span>
					<?php esc_html_e( 'copiar', 'andreani-shipping' ); ?>
				</span>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( 'error' === $data['status'] && ! empty( $data['last_error'] ) ) : ?>
		<div class="andreani-metabox__error andreani-metabox__error--collapsed">
			<button type="button" class="andreani-metabox__error-toggle">
				<span class="dashicons dashicons-warning"></span>
				<strong><?php esc_html_e( 'Último error', 'andreani-shipping' ); ?></strong>
				<span class="andreani-metabox__error-arrow dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<div class="andreani-metabox__error-body">
				<code class="andreani-metabox__error-message"><?php echo esc_html( $data['last_error'] ); ?></code>
				<button type="button" class="andreani-metabox__error-copy andreani-copy-click" data-copy-text="<?php echo esc_attr( $data['last_error'] ); ?>">
					<span class="dashicons dashicons-clipboard"></span>
					<?php esc_html_e( 'Copiar error', 'andreani-shipping' ); ?>
				</button>
				<?php if ( ! empty( $data['last_error_body'] ) ) : ?>
					<div class="andreani-metabox__error-body-section">
						<strong><?php esc_html_e( 'Body enviado', 'andreani-shipping' ); ?></strong>
						<pre class="andreani-metabox__error-body-json"><?php echo esc_html( $data['last_error_body'] ); ?></pre>
						<button type="button" class="andreani-metabox__error-copy andreani-copy-click" data-copy-text="<?php echo esc_attr( $data['last_error_body'] ); ?>">
							<span class="dashicons dashicons-clipboard"></span>
							<?php esc_html_e( 'Copiar body', 'andreani-shipping' ); ?>
						</button>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="andreani-metabox__actions">
		<?php if ( $data['can_download'] ) : ?>
			<button type="button"
					class="andr-btn andr-btn--secondary andr-btn--sm andreani-download-label"
					data-order-id="<?php echo esc_attr( $data['order_id'] ); ?>"
					data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
					data-action="andreani_get_etiqueta"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'andreani_get_etiqueta' ) ); ?>">
				<span class="dashicons dashicons-media-document"></span>
				<?php esc_html_e( 'Descargar etiqueta', 'andreani-shipping' ); ?>
			</button>
		<?php endif; ?>

		<?php if ( $data['can_retry'] ) : ?>
			<button type="button"
					class="andr-btn andr-btn--secondary andr-btn--sm andreani-retry"
					data-order-id="<?php echo esc_attr( $data['order_id'] ); ?>"
					data-ajax-url="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
					data-action="andreani_retry_order"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'andreani_retry_order' ) ); ?>">
				<span class="dashicons dashicons-update"></span>
				<?php esc_html_e( 'Reintentar', 'andreani-shipping' ); ?>
			</button>
		<?php endif; ?>

		<a class="andr-btn andr-btn--primary andr-btn--sm" href="<?php echo esc_url( $shipments_url ); ?>">
			<?php esc_html_e( 'Ver en mis envíos', 'andreani-shipping' ); ?>
		</a>
	</div>
</div>
