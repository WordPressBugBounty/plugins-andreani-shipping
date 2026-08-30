<?php
/**
 * Template: Metabox de tracking Andreani (pagina del pedido individual).
 *
 * Usa el sistema de diseno del plugin (andr-status / andr-badge / andr-btn) y
 * prioriza la informacion logistica: estado del envio, mini-timeline, seguimiento
 * copiable, destino y acciones. El detalle tecnico vive en la grilla de envios.
 *
 * @package AndreaniPlugin
 * @var array $data Datos del envio (ya hidratados desde la API cuando aplica).
 */

defined( 'ABSPATH' ) || exit;

$status_classes = array(
	'success' => 'andreani-metabox--success',
	'error'   => 'andreani-metabox--error',
);

$status_class = isset( $status_classes[ $data['status'] ] ) ? $status_classes[ $data['status'] ] : '';
$type_info    = Andreani_Client_Type::from_id( $data['client_type'] );
$type_label   = $type_info ? $type_info->short_label() : 'Pyme';
$type_class   = $type_info ? $type_info->display( 'badge_css_class', 'andr-badge--pyme' ) : 'andr-badge--pyme';

$has_status   = ! empty( $data['tracking_status_label'] );
$is_online    = isset( $data['source'] ) && 'api' === $data['source'];
$timeline     = isset( $data['timeline'] ) && is_array( $data['timeline'] ) ? $data['timeline'] : array();
$tracking     = ! empty( $data['display_tracking'] ) ? $data['display_tracking'] : $data['tracking_number'];
$has_tracking = ! empty( $tracking );

$destination  = '';
if ( ! empty( $data['is_sucursal'] ) ) {
	$destination = trim( implode( ' &middot; ', array_filter( array(
		$data['destination_city'],
		__( 'Sucursal', 'andreani-shipping' ) . ( ! empty( $data['sucursal'] ) ? ' ' . $data['sucursal'] : '' ),
	) ) ) );
} else {
	$destination = trim( implode( ' &middot; ', array_filter( array(
		$data['destination_city'],
		__( 'A domicilio', 'andreani-shipping' ),
	) ) ) );
}
?>
<div class="andreani-metabox <?php echo esc_attr( $status_class ); ?>">

	<?php if ( ! empty( $data['api_failure'] ) ) : ?>
		<p class="andreani-metabox__api-note" role="status">
			<span class="dashicons dashicons-cloud"></span>
			<?php esc_html_e( 'Datos locales (sin conexion en linea).', 'andreani-shipping' ); ?>
		</p>
	<?php endif; ?>

	<div class="andreani-metabox__status-row">
		<?php if ( $has_status ) : ?>
			<span class="andr-status andr-status--dot andr-status--sm <?php echo esc_attr( $data['tracking_status_class'] ); ?>"><?php echo esc_html( $data['tracking_status_label'] ); ?></span>
		<?php else : ?>
			<span class="andr-status andr-status--dot andr-status--sm <?php echo esc_attr( 'success' === $data['status'] ? 'andr-status--success' : 'andr-status--error' ); ?>"><?php echo esc_html( $data['status_label'] ); ?></span>
		<?php endif; ?>
		<span class="andreani-metabox__meta">
			<?php if ( $is_online ) : ?>
				<span class="andreani-metabox__online" title="<?php esc_attr_e( 'Datos sincronizados con Andreani', 'andreani-shipping' ); ?>">
					<span class="dashicons dashicons-cloud-saved"></span>
					<?php esc_html_e( 'En linea', 'andreani-shipping' ); ?>
				</span>
			<?php endif; ?>
			<span class="andr-badge andr-badge--sm <?php echo esc_attr( $type_class ); ?>"><?php echo esc_html( $type_label ); ?></span>
		</span>
	</div>

	<?php if ( ! empty( $timeline ) ) : ?>
		<ol class="andreani-mini-timeline" aria-label="<?php esc_attr_e( 'Seguimiento del envio', 'andreani-shipping' ); ?>">
			<?php foreach ( $timeline as $step ) : ?>
				<li class="andreani-mini-timeline__step andreani-mini-timeline__step--<?php echo esc_attr( $step['state'] ); ?>">
					<span class="andreani-mini-timeline__dot" aria-hidden="true"></span>
					<span class="andreani-mini-timeline__label"><?php echo esc_html( $step['label'] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<?php if ( $has_tracking ) : ?>
		<div class="andreani-metabox__section">
			<span class="andreani-metabox__section-title"><?php esc_html_e( 'Seguimiento', 'andreani-shipping' ); ?></span>
			<div class="andreani-metabox__tracking">
				<code class="andreani-copy-number andreani-copy-click" data-copy-text="<?php echo esc_attr( $tracking ); ?>" role="button" tabindex="0" title="<?php esc_attr_e( 'Click para copiar', 'andreani-shipping' ); ?>"><?php echo esc_html( $tracking ); ?></code>
				<span class="andreani-metabox__copy-hint" aria-hidden="true">
					<span class="dashicons dashicons-admin-page"></span>
					<?php esc_html_e( 'copiar', 'andreani-shipping' ); ?>
				</span>
			</div>
			<a class="andreani-metabox__track-link" href="https://www.andreani.com/envio/<?php echo esc_attr( rawurlencode( $tracking ) ); ?>" target="_blank" rel="noopener noreferrer">
				<span class="dashicons dashicons-arrow-right-alt2"></span>
				<?php esc_html_e( 'Ver seguimiento', 'andreani-shipping' ); ?>
			</a>
		</div>
	<?php elseif ( ! empty( $data['identifier'] ) ) : ?>
		<div class="andreani-metabox__section">
			<span class="andreani-metabox__section-title"><?php echo esc_html( $data['identifier_label'] ); ?></span>
			<div class="andreani-metabox__tracking">
				<code class="andreani-copy-number andreani-copy-click" data-copy-text="<?php echo esc_attr( $data['identifier'] ); ?>" role="button" tabindex="0" title="<?php esc_attr_e( 'Click para copiar', 'andreani-shipping' ); ?>"><?php echo esc_html( $data['identifier'] ); ?></code>
				<span class="andreani-metabox__copy-hint" aria-hidden="true">
					<span class="dashicons dashicons-admin-page"></span>
					<?php esc_html_e( 'copiar', 'andreani-shipping' ); ?>
				</span>
			</div>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $destination ) : ?>
		<div class="andreani-metabox__section">
			<span class="andreani-metabox__section-title"><?php esc_html_e( 'Destino', 'andreani-shipping' ); ?></span>
			<p class="andreani-metabox__destination"><?php echo wp_kses_post( $destination ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( 'error' === $data['status'] && ! empty( $data['last_error'] ) ) : ?>
		<div class="andreani-metabox__error andreani-metabox__error--collapsed">
			<button type="button" class="andreani-metabox__error-toggle">
				<span class="dashicons dashicons-warning"></span>
				<strong><?php esc_html_e( 'Ultimo error', 'andreani-shipping' ); ?></strong>
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
				<?php esc_html_e( 'Etiqueta', 'andreani-shipping' ); ?>
			</button>
		<?php endif; ?>

		<?php if ( $has_tracking ) : ?>
			<a class="andr-btn andr-btn--primary andr-btn--sm"
			   href="https://www.andreani.com/envio/<?php echo esc_attr( rawurlencode( $tracking ) ); ?>"
			   target="_blank"
			   rel="noopener noreferrer">
				<span class="dashicons dashicons-admin-links"></span>
				<?php esc_html_e( 'Seguir', 'andreani-shipping' ); ?>
			</a>
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
	</div>

	<div class="andreani-metabox__footer">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=andreani-shipping' ) ); ?>" target="_blank">
			<?php esc_html_e( 'Ver todos los envios', 'andreani-shipping' ); ?> &rarr;
		</a>
	</div>
</div>
