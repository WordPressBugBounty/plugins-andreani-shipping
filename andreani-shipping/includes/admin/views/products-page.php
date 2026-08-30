<?php
/**
 * Template: Página Ver mis productos
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap andreani-products-wrap" data-async-load="true">

	<hr class="wp-header-end">

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
				<button type="button"
					class="andreani-chip"
					id="andreani-products-missing-filter"
					aria-pressed="false"
					data-filter="missing_dims">
					<?php esc_html_e( 'Sin dimensiones/peso', 'andreani-shipping' ); ?>
				</button>
			</div>
		</div>

		<div id="andreani-products-table-container">
			<div class="andreani-table-loader">
				<div class="andreani-table-loader__spinner">
					<svg class="andreani-table-loader__logo andreani-table-loader__logo--bg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341" style="color: var(--andr-color-border-strong);">
						<g transform="translate(0,341) scale(0.1,-0.1)" fill="currentColor">
							<path d="M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z"/>
						</g>
					</svg>
					<svg class="andreani-table-loader__logo andreani-table-loader__logo--fill" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341" style="color: var(--andr-color-brand);">
						<g transform="translate(0,341) scale(0.1,-0.1)" fill="currentColor">
							<path d="M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z"/>
						</g>
					</svg>
				</div>
			</div>
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

<?php require ANDREANI_PLUGIN_DIR . 'includes/admin/views/product-edit-modal.php'; ?>
<?php require ANDREANI_PLUGIN_DIR . 'includes/admin/views/product-quote-modal.php'; ?>
