<?php
/**
 * Paginación Anterior / Siguiente de las grillas del plugin.
 *
 * @package AndreaniPlugin
 * @var int $paged       Página actual.
 * @var int $total_pages Total de páginas.
 */

defined( 'ABSPATH' ) || exit;

if ( $total_pages < 1 ) {
	return;
}
?>
<div class="andreani-pagination">
	<span class="andreani-pagination__info">
		<?php
		printf(
			/* translators: 1: current page, 2: total pages */
			esc_html__( 'Página %1$d de %2$d', 'andreani-shipping' ),
			(int) $paged,
			(int) $total_pages
		);
		?>
	</span>
	<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm andreani-page-btn" data-paged="<?php echo esc_attr( max( 1, $paged - 1 ) ); ?>"<?php echo $paged <= 1 ? ' disabled aria-disabled="true"' : ''; ?>>&laquo; <?php esc_html_e( 'Anterior', 'andreani-shipping' ); ?></button>
	<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm andreani-page-btn" data-paged="<?php echo esc_attr( min( $total_pages, $paged + 1 ) ); ?>"<?php echo $paged >= $total_pages ? ' disabled aria-disabled="true"' : ''; ?>><?php esc_html_e( 'Siguiente', 'andreani-shipping' ); ?> &raquo;</button>
</div>
