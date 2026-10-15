<?php
/**
 * Template: Página Novedades
 *
 * @package AndreaniPlugin
 * @var array $items Ítems de Andreani_Novedades::items().
 */

defined( 'ABSPATH' ) || exit;

$by_version = array();

foreach ( $items as $item ) {
	$by_version[ $item['version'] ][] = $item;
}
?>
<div class="wrap andreani-novedades-wrap">
	<hr class="wp-header-end">

	<div class="andreani-page-card">
		<?php
		$andreani_page_title = __( 'Novedades', 'andreani-shipping' );
		require ANDREANI_PLUGIN_DIR . 'includes/admin/views/page-card-header.php';
		?>
		<div class="andreani-page-card__body">
			<?php foreach ( $by_version as $version => $version_items ) : ?>
				<section class="andr-news">
					<h2 class="andr-news__version">
						<?php
						/* translators: %s: número de versión del plugin */
						echo esc_html( sprintf( __( 'Versión %s', 'andreani-shipping' ), $version ) );
						?>
					</h2>
					<ul class="andr-news__list">
						<?php foreach ( $version_items as $item ) : ?>
							<li>
								<strong><?php echo esc_html( $item['titulo'] ); ?></strong>
								<span><?php echo esc_html( $item['texto'] ); ?></span>
								<a class="andr-btn andr-btn--secondary andr-btn--sm" href="<?php echo esc_url( Andreani_Novedades::url_for( $item ) ); ?>"><?php echo esc_html( $item['accion'] ); ?></a>
							</li>
							<?php foreach ( $item['extras'] as $extra ) : ?>
								<li>
									<strong><?php echo esc_html( $extra['titulo'] ); ?></strong>
									<span><?php echo esc_html( $extra['texto'] ); ?></span>
									<?php if ( ! empty( $extra['page'] ) ) : ?>
										<a class="andr-btn andr-btn--secondary andr-btn--sm" href="<?php echo esc_url( admin_url( 'admin.php?page=' . $extra['page'] ) ); ?>"><?php echo esc_html( $extra['accion'] ); ?></a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>
		</div>
	</div>
</div>
