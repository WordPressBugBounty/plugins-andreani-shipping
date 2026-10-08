<?php
/**
 * Template: Página Productos
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap andreani-products-wrap" data-async-load="true">

	<hr class="wp-header-end">

	<div class="andreani-page-card">
		<?php
		$andreani_page_title = __( 'Ver mis productos', 'andreani-shipping' );
		require ANDREANI_PLUGIN_DIR . 'includes/admin/views/page-card-header.php';
		?>
		<div class="andreani-page-card__body">

	<?php
	Andreani_Products_Stats::finish_small_backfill();
	$counts    = Andreani_Products_Stats::get_counts();
	$analyzing = Andreani_Products_Stats::progress();
	?>
	<p id="andreani-products-analyzing" class="andreani-products-inline-msg andreani-products-inline-msg--info" <?php echo $analyzing ? '' : 'hidden'; ?>>
		<?php
		/* translators: 1: productos analizados, 2: total de productos */
		printf( esc_html__( 'Analizando tu catálogo… (%1$s de %2$s)', 'andreani-shipping' ), '<span data-analyzing="done">' . esc_html( number_format_i18n( $analyzing ? $analyzing['done'] : 0 ) ) . '</span>', '<span data-analyzing="total">' . esc_html( number_format_i18n( $analyzing ? $analyzing['total'] : 0 ) ) . '</span>' );
		?>
	</p>
	<form method="get" id="andreani-products-form">
		<input type="hidden" name="page" value="andreani-products" />

		<div class="andreani-toolbar">
			<div class="andreani-toolbar__search">
				<input type="search" name="s" id="andreani-products-search" class="andreani-search-input" placeholder="<?php esc_attr_e( 'Buscar por nombre o SKU…', 'andreani-shipping' ); ?>" value="<?php echo esc_attr( isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
				<button type="submit" class="andreani-icon-btn andreani-icon-btn--bordered" title="<?php esc_attr_e( 'Buscar', 'andreani-shipping' ); ?>" aria-label="<?php esc_attr_e( 'Buscar', 'andreani-shipping' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				</button>
				<button type="button" class="andreani-icon-btn andreani-icon-btn--bordered" id="andreani-products-refresh" title="<?php esc_attr_e( 'Refrescar', 'andreani-shipping' ); ?>" aria-label="<?php esc_attr_e( 'Refrescar', 'andreani-shipping' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
				</button>
			</div>

			<div class="andreani-toolbar__filters">
				<button type="button" class="andreani-filter-trigger" id="andreani-products-filters-trigger" aria-haspopup="dialog" aria-expanded="false" aria-controls="andreani-products-filters-popover">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
					<span class="andreani-filter-trigger__label"><?php esc_html_e( 'Filtros', 'andreani-shipping' ); ?></span>
					<span class="andreani-filter-trigger__count" hidden>0</span>
				</button>

				<div id="andreani-products-filters-popover" class="andreani-filters-popover" role="dialog" aria-label="<?php esc_attr_e( 'Filtros de productos', 'andreani-shipping' ); ?>" hidden>
					<div class="andreani-filters-popover__body">
						<?php
						$filter_groups = array(
							'service' => array(
								__( 'Servicio', 'andreani-shipping' ),
								array(
									Andreani_Products_Stats::CLASS_PAQUETERIA => __( 'Paquetería', 'andreani-shipping' ),
									Andreani_Products_Stats::CLASS_BIGGER     => __( 'Bigger', 'andreani-shipping' ),
									Andreani_Products_Stats::CLASS_MISSING    => __( 'Faltan medidas', 'andreani-shipping' ),
								),
							),
							'mode'    => array(
								__( 'Despacho', 'andreani-shipping' ),
								array(
									Andreani_Product_Bultos::MODE_SINGLE     => __( 'Cada una en su caja', 'andreani-shipping' ),
									Andreani_Product_Bultos::MODE_APILADO    => __( 'Se apilan', 'andreani-shipping' ),
									Andreani_Product_Bultos::MODE_MULTIBULTO => __( 'Varias cajas', 'andreani-shipping' ),
								),
							),
						);
						foreach ( $filter_groups as $group_key => $group ) :
							?>
							<section class="andreani-filters-section">
								<header class="andreani-filters-section__header">
									<h4 class="andreani-filters-section__title"><?php echo esc_html( $group[0] ); ?></h4>
								</header>
								<div class="andreani-filters-section__body">
									<div class="andreani-quick-filters__group" role="group" aria-label="<?php echo esc_attr( $group[0] ); ?>">
										<?php foreach ( $group[1] as $value => $label ) : ?>
											<button type="button" class="andreani-chip" aria-pressed="false" data-filter-group="<?php echo esc_attr( $group_key ); ?>" data-filter-value="<?php echo esc_attr( $value ); ?>" data-filter-label="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $label ); ?> <span data-count="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( number_format_i18n( $counts[ $value ] ) ); ?></span></button>
										<?php endforeach; ?>
									</div>
								</div>
							</section>
						<?php endforeach; ?>
					</div>
					<footer class="andreani-filters-popover__footer">
						<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm" id="andreani-products-filters-clear"><?php esc_html_e( 'Limpiar', 'andreani-shipping' ); ?></button>
						<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-products-filters-close"><?php esc_html_e( 'Cerrar', 'andreani-shipping' ); ?></button>
					</footer>
				</div>
			</div>

			<button type="button" class="andr-btn andr-btn--primary andreani-toolbar__sim" id="andreani-cart-sim-open">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M2.5 3.5h2.6l2.4 11.2a1.6 1.6 0 0 0 1.6 1.3h8.6a1.6 1.6 0 0 0 1.6-1.2l1.7-7.3H6.1"/></svg>
				<?php esc_html_e( 'Simular un carrito', 'andreani-shipping' ); ?>
			</button>
		</div>

		<div class="andreani-active-pills" id="andreani-products-pills">
			<div class="andreani-active-pills__list" id="andreani-products-pills-list"></div>
			<button type="button" class="andreani-active-pills__clear" id="andreani-products-pills-clear">
				<?php esc_html_e( 'Limpiar todo', 'andreani-shipping' ); ?>
			</button>
		</div>

		<div class="andr-dispatch__warnbox" id="andreani-products-missing"<?php echo $counts[ Andreani_Products_Stats::FILTER_MISSING ] > 0 ? '' : ' hidden'; ?>>
			<span>
				<?php
				printf(
					/* translators: %s: cantidad de productos sin medidas */
					esc_html__( 'Productos sin medidas: %s. No se pueden enviar con Andreani', 'andreani-shipping' ),
					'<span data-count="missing">' . esc_html( number_format_i18n( $counts[ Andreani_Products_Stats::FILTER_MISSING ] ) ) . '</span>'
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
				&middot; <button type="button" class="andr-pem__hint-action" id="andreani-products-missing-link"><?php esc_html_e( 'Ver', 'andreani-shipping' ); ?></button>
			</span>
		</div>

		<div id="andreani-products-table-container">
			<?php Andreani_Admin_Loader::render( '', Andreani_Admin_Loader::SIZE_LG, Andreani_Admin_Loader::products_phrases() ); ?>
		</div>

		<?php $current_per_page = Andreani_Products_List::resolve_per_page(); ?>
		<div class="andreani-per-page" role="group" aria-label="<?php esc_attr_e( 'Cantidad por página', 'andreani-shipping' ); ?>">
			<span class="andreani-per-page__label"><?php esc_html_e( 'Por página', 'andreani-shipping' ); ?></span>
			<?php foreach ( Andreani_Products_List::PER_PAGE_OPTIONS as $opt ) : ?>
				<button
					type="button"
					class="andreani-per-page__btn<?php echo $current_per_page === $opt ? ' is-active' : ''; ?>"
					data-per-page="<?php echo esc_attr( $opt ); ?>"
					aria-pressed="<?php echo $current_per_page === $opt ? 'true' : 'false'; ?>"
				><?php echo esc_html( $opt ); ?></button>
			<?php endforeach; ?>
		</div>
	</form>
		</div>
	</div>
</div>

<?php require ANDREANI_PLUGIN_DIR . 'includes/admin/views/product-editor-panel.php'; ?>
<?php require ANDREANI_PLUGIN_DIR . 'includes/admin/views/product-simulator-modal.php'; ?>
