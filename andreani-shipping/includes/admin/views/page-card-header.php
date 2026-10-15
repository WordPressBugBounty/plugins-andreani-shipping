<?php
/**
 * Template: Header de la card de una pantalla del plugin
 *
 * @package AndreaniPlugin
 * @var string $andreani_page_title
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="andreani-page-card__header">
	<h1 class="andreani-page-card__crumb">
		<span class="andreani-page-card__crumb-root"><?php esc_html_e( 'Andreani', 'andreani-shipping' ); ?></span>
		<span class="andreani-page-card__crumb-sep" aria-hidden="true">&rsaquo;</span>
		<?php echo isset( $andreani_page_icon ) ? $andreani_page_icon : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<span><?php echo esc_html( $andreani_page_title ); ?></span>
	</h1>
	<div class="andreani-page-card__side">
		<img class="andreani-page-card__logo" src="<?php echo esc_url( ANDREANI_PLUGIN_URL . 'includes/assets/img/andreani.png' ); ?>" alt="Andreani" />
	</div>
</div>
