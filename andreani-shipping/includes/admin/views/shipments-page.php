<?php
/**
 * Template: Pagina de envios Andreani
 *
 * @package AndreaniPlugin
 * @var Andreani_Shipments_List $list_table
 */

defined( 'ABSPATH' ) || exit;

$active_type_info        = Andreani_Client_Type::from_id( Andreani_Api_Manager::get_client_type() );
$historial_url           = $active_type_info ? $active_type_info->historial_url() : ANDREANI_PYME_HISTORIAL_URL;
$andreani_stats          = Andreani_Shipments_List::get_shipment_stats();
$andreani_has_payment    = $active_type_info && $active_type_info->has_payment_step();
$andreani_awaiting_label = $andreani_has_payment
	? __( 'Por pagar', 'andreani-shipping' )
	: __( 'Listos', 'andreani-shipping' );
$andreani_awaiting_count = $andreani_has_payment ? $andreani_stats['awaiting'] : $andreani_stats['ready'];
$andreani_sync_enabled   = ! class_exists( 'Andreani_Tracking_Sync' ) || Andreani_Tracking_Sync::is_enabled();
?>
<div class="wrap andreani-shipments-wrap" data-async-load="true">

	<hr class="wp-header-end">

	<form method="get" id="andreani-shipments-form">
		<input type="hidden" name="page" value="<?php echo esc_attr( Andreani_Admin_Menu::MENU_SLUG ); ?>" />

		<div class="andreani-toolbar">
			<div class="andreani-toolbar__search">
				<input type="search" name="s" id="andreani-search-input" class="andreani-search-input" placeholder="<?php esc_attr_e( 'Buscar envío…', 'andreani-shipping' ); ?>" value="<?php echo esc_attr( isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
				<button type="submit" class="andreani-icon-btn andreani-icon-btn--bordered" title="<?php esc_attr_e( 'Buscar', 'andreani-shipping' ); ?>" aria-label="<?php esc_attr_e( 'Buscar', 'andreani-shipping' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				</button>
				<button type="button" class="andreani-icon-btn andreani-icon-btn--bordered" id="andreani-refresh-table" title="<?php esc_attr_e( 'Refrescar', 'andreani-shipping' ); ?>" aria-label="<?php esc_attr_e( 'Refrescar', 'andreani-shipping' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
				</button>
			</div>

			<div class="andreani-toolbar__filters">
				<button type="button" class="andreani-filter-trigger" id="andreani-filters-trigger" aria-haspopup="dialog" aria-expanded="false" aria-controls="andreani-filters-popover">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
					<span class="andreani-filter-trigger__label"><?php esc_html_e( 'Filtros', 'andreani-shipping' ); ?></span>
					<span class="andreani-filter-trigger__count" hidden>0</span>
				</button>

				<div id="andreani-filters-popover" class="andreani-filters-popover" role="dialog" aria-label="<?php esc_attr_e( 'Filtros de envíos', 'andreani-shipping' ); ?>" hidden>
					<div class="andreani-filters-popover__body">

						<section class="andreani-filters-section" data-section-key="date">
							<header class="andreani-filters-section__header">
								<h4 class="andreani-filters-section__title"><?php esc_html_e( 'Fecha', 'andreani-shipping' ); ?></h4>
							</header>
							<div class="andreani-filters-section__body">
								<div class="andreani-quick-filters__group" role="radiogroup" aria-label="<?php esc_attr_e( 'Rango rápido', 'andreani-shipping' ); ?>">
									<button type="button" class="andreani-chip" data-quick-filter="today" data-filter-group="date" role="radio" aria-checked="false"><?php esc_html_e( 'Hoy', 'andreani-shipping' ); ?></button>
									<button type="button" class="andreani-chip" data-quick-filter="week" data-filter-group="date" role="radio" aria-checked="false"><?php esc_html_e( 'Esta semana', 'andreani-shipping' ); ?></button>
									<button type="button" class="andreani-chip" data-quick-filter="last-15" data-filter-group="date" role="radio" aria-checked="false"><?php esc_html_e( 'Últimos 15 días', 'andreani-shipping' ); ?></button>
								</div>
								<div class="andreani-date-range" role="group" aria-label="<?php esc_attr_e( 'Rango personalizado', 'andreani-shipping' ); ?>">
									<label class="andreani-date-range__field">
										<span class="andreani-date-range__label"><?php esc_html_e( 'Desde', 'andreani-shipping' ); ?></span>
										<input type="date" name="andreani_date_from" class="andreani-date-range__input" value="" aria-label="<?php esc_attr_e( 'Fecha desde', 'andreani-shipping' ); ?>" />
									</label>
									<label class="andreani-date-range__field">
										<span class="andreani-date-range__label"><?php esc_html_e( 'Hasta', 'andreani-shipping' ); ?></span>
										<input type="date" name="andreani_date_to" class="andreani-date-range__input" value="" aria-label="<?php esc_attr_e( 'Fecha hasta', 'andreani-shipping' ); ?>" />
									</label>
								</div>
							</div>
						</section>

						<section class="andreani-filters-section" data-section-key="status">
							<header class="andreani-filters-section__header">
								<h4 class="andreani-filters-section__title"><?php esc_html_e( 'Estado del envío', 'andreani-shipping' ); ?></h4>
							</header>
							<div class="andreani-filters-section__body">
								<div class="andreani-quick-filters__group" role="group" aria-label="<?php esc_attr_e( 'Estado del envío', 'andreani-shipping' ); ?>">
									<button type="button" class="andreani-chip" data-quick-filter="not_packaged" data-filter-group="status" aria-pressed="false"><?php esc_html_e( 'Por Empaquetar', 'andreani-shipping' ); ?></button>
									<button type="button" class="andreani-chip" data-quick-filter="packaged" data-filter-group="status" aria-pressed="false"><?php esc_html_e( 'Empaquetados', 'andreani-shipping' ); ?></button>
									<button type="button" class="andreani-chip" data-quick-filter="ready" data-filter-group="status" aria-pressed="false"><?php esc_html_e( 'Listos', 'andreani-shipping' ); ?></button>
									<?php /* Chips reservados para futuras versiones (in_transit / delivered): cuando se integre tracking real, descomentar.
									<button type="button" class="andreani-chip" data-quick-filter="in_transit" data-filter-group="status" aria-pressed="false"><?php esc_html_e( 'En camino', 'andreani-shipping' ); ?></button>
									<button type="button" class="andreani-chip" data-quick-filter="delivered" data-filter-group="status" aria-pressed="false"><?php esc_html_e( 'Entregados', 'andreani-shipping' ); ?></button>
									*/ ?>
									<button type="button" class="andreani-chip" data-quick-filter="errors" data-filter-group="status" aria-pressed="false"><?php esc_html_e( 'Con error', 'andreani-shipping' ); ?></button>
								</div>
							</div>
						</section>

					</div>
					<footer class="andreani-filters-popover__footer">
						<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm" id="andreani-filters-clear"><?php esc_html_e( 'Limpiar', 'andreani-shipping' ); ?></button>
						<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-filters-close"><?php esc_html_e( 'Cerrar', 'andreani-shipping' ); ?></button>
					</footer>
				</div>
			</div>

			<div class="andreani-toolbar__utils">
				<span class="andreani-sync-pill" title="<?php esc_attr_e( 'Actualiza el estado de los envíos automáticamente en segundo plano', 'andreani-shipping' ); ?>">
					<span class="andreani-sync-pill__label"><?php esc_html_e( 'Seguimiento automático', 'andreani-shipping' ); ?></span>
					<label class="andreani-sync-switch">
						<input type="checkbox" role="switch"
							id="andreani-tracking-sync-toggle"
							class="andreani-sync-switch__input"
							<?php checked( $andreani_sync_enabled ); ?>
							aria-checked="<?php echo $andreani_sync_enabled ? 'true' : 'false'; ?>"
							aria-label="<?php esc_attr_e( 'Seguimiento automático de envíos', 'andreani-shipping' ); ?>" />
						<span class="andreani-sync-switch__slider"></span>
					</label>
				</span>
				<button type="button"
					class="andreani-icon-btn andreani-icon-btn--bordered"
					id="andreani-print-settings-trigger"
					title="<?php esc_attr_e( 'Configuración de impresión', 'andreani-shipping' ); ?>"
					aria-label="<?php esc_attr_e( 'Configuración de impresión', 'andreani-shipping' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
				</button>
				<a href="<?php echo esc_url( $historial_url ); ?>"
				   class="andreani-icon-btn andreani-icon-btn--bordered"
				   target="_blank"
				   rel="noopener noreferrer"
				   title="<?php esc_attr_e( 'Ver historial en Andreani', 'andreani-shipping' ); ?>"
				   aria-label="<?php esc_attr_e( 'Ver historial en Andreani', 'andreani-shipping' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 341 341" aria-hidden="true"><g transform="translate(0,341) scale(0.1,-0.1)" fill="currentColor"><path d="M1852 2575 c-35 -8 -75 -16 -90 -18 -87 -14 -331 -87 -407 -122 -190 -87 -263 -126 -368 -197 -318 -214 -521 -466 -571 -711 -29 -137 -18 -233 40 -352 73 -154 253 -283 470 -340 150 -39 469 -43 674 -9 459 77 963 364 1209 687 244 321 252 631 22 854 -41 40 -78 73 -83 73 -5 0 -26 11 -47 25 -48 32 -176 82 -261 101 -96 22 -504 29 -588 9z m498 -95 c215 -32 400 -150 477 -308 36 -73 38 -80 38 -176 0 -56 -6 -123 -14 -151 -37 -132 -133 -277 -274 -411 -87 -84 -127 -110 -150 -101 -16 6 -37 71 -92 282 -111 431 -180 661 -204 689 -21 24 -59 43 -101 51 -46 8 -56 -3 -161 -180 -180 -306 -670 -1077 -712 -1122 -27 -30 -81 -30 -150 -1 -186 78 -299 217 -320 393 -9 70 -7 91 11 163 62 243 254 463 567 647 52 30 96 55 99 55 2 0 34 14 69 30 36 17 69 30 74 30 4 0 20 6 35 14 42 22 201 66 333 92 104 21 140 23 265 19 80 -3 174 -9 210 -15z m-428 -573 c29 -118 76 -320 82 -354 l6 -33 -195 0 c-107 0 -195 3 -195 7 0 14 274 462 280 457 3 -3 13 -38 22 -77z m-26 -516 l150 -1 17 -72 c38 -172 33 -193 -56 -233 -67 -29 -248 -74 -362 -91 -22 -3 -51 -7 -64 -9 -61 -10 -192 -17 -215 -11 -51 13 -51 38 -1 134 25 48 72 130 103 182 l57 95 65 5 c36 3 85 4 110 4 25 -1 113 -2 196 -3z"/></g></svg>
				</a>
				<button type="button"
					class="andreani-icon-btn andreani-icon-btn--bordered"
					id="andreani-export-excel"
					title="<?php esc_attr_e( 'Exportar a Excel', 'andreani-shipping' ); ?>"
					aria-label="<?php esc_attr_e( 'Exportar a Excel', 'andreani-shipping' ); ?>">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
				</button>
				<?php if ( Andreani_Settings_Service::is_debug_enabled() ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&source=andreani-shipping' ) ); ?>"
					   class="andreani-icon-btn andreani-icon-btn--bordered"
					   target="_blank"
					   title="<?php esc_attr_e( 'Ver logs', 'andreani-shipping' ); ?>"
					   aria-label="<?php esc_attr_e( 'Ver logs', 'andreani-shipping' ); ?>">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><polyline points="7 10 10 13 7 16"/><line x1="14" y1="16" x2="17" y2="16"/></svg>
					</a>
				<?php endif; ?>
			</div>
		</div>

		<div class="andreani-active-pills" id="andreani-active-pills">
			<div class="andreani-active-pills__list" id="andreani-active-pills-list"></div>
			<button type="button" class="andreani-active-pills__clear" id="andreani-active-pills-clear">
				<?php esc_html_e( 'Limpiar todo', 'andreani-shipping' ); ?>
			</button>
		</div>

		<div id="andreani-table-container">
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

		<?php $current_per_page = Andreani_Shipments_List::resolve_per_page(); ?>
		<div class="andreani-per-page" role="group" aria-label="<?php esc_attr_e( 'Cantidad por página', 'andreani-shipping' ); ?>">
			<span class="andreani-per-page__label"><?php esc_html_e( 'Por página', 'andreani-shipping' ); ?></span>
			<?php foreach ( Andreani_Shipments_List::PER_PAGE_OPTIONS as $opt ) : ?>
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

<div id="andreani-error-modal" class="andr-modal andreani-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="andreani-error-modal-title">
	<div class="andr-modal__backdrop andreani-modal__backdrop"></div>
	<div class="andr-modal__container andr-modal__container--wide andreani-modal__container andreani-modal__container--draggable">
		<div class="andr-modal__header andreani-modal__header" data-draggable="true">
			<svg class="andr-modal__logo andreani-detail__error-modal-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
			<h3 id="andreani-error-modal-title" class="andr-modal__title"><?php esc_html_e( 'Error al crear envío', 'andreani-shipping' ); ?></h3>
			<button type="button" id="andreani-error-modal-copy" class="andreani-detail__error-card-copy andreani-copy-click" data-copy-text="" title="<?php esc_attr_e( 'Copiar reporte para soporte (mensaje + request)', 'andreani-shipping' ); ?>" aria-label="<?php esc_attr_e( 'Copiar reporte para soporte', 'andreani-shipping' ); ?>">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
				<span><?php esc_html_e( 'Copiar', 'andreani-shipping' ); ?></span>
			</button>
			<button type="button" class="andr-modal__close andreani-modal__close">&times;</button>
		</div>
		<div class="andr-modal__body andreani-modal__body andreani-error-modal__body">
			<div class="andr-tabs andreani-detail__error-tabs">
				<nav class="andr-tabs__list" role="tablist">
					<button type="button" class="andr-tabs__item andr-tabs__item--active" data-tab="mensaje" role="tab" aria-selected="true"><?php esc_html_e( 'Mensaje', 'andreani-shipping' ); ?></button>
					<button type="button" class="andr-tabs__item" data-tab="request" role="tab" aria-selected="false"><?php esc_html_e( 'Request', 'andreani-shipping' ); ?></button>
				</nav>
				<div class="andr-tabs__panel andr-tabs__panel--active andreani-detail__error-tab-content" data-panel="mensaje" role="tabpanel">
					<pre id="andreani-error-modal-message" class="andreani-detail__error-tab-text andreani-detail__error-tab-text--message"></pre>
				</div>
				<div class="andr-tabs__panel andreani-detail__error-tab-content" data-panel="request" role="tabpanel">
					<pre id="andreani-error-modal-body" class="andreani-detail__error-tab-text andreani-detail__error-tab-text--code"></pre>
				</div>
			</div>
		</div>
	</div>
</div>

<div id="andreani-bulk-bar" class="andreani-bulk-bar" role="region" aria-label="<?php esc_attr_e( 'Acciones sobre los envíos seleccionados', 'andreani-shipping' ); ?>" hidden>
	<span class="andreani-bulk-bar__count">
		<strong id="andreani-bulk-bar-count">0</strong>
		<span><?php esc_html_e( 'envíos seleccionados', 'andreani-shipping' ); ?></span>
	</span>
	<div class="andreani-bulk-bar__actions" id="andreani-bulk-bar-actions">
		<!-- Acciones contextuales renderizadas por AndreaniBulkBar.renderActions()
		     según el contenido de la selección (status, has-tracking, etc.):
		     marcar enviados, descargar etiquetas. -->
	</div>
	<button type="button" class="andreani-bulk-bar__close" id="andreani-bulk-bar-close" aria-label="<?php esc_attr_e( 'Cerrar', 'andreani-shipping' ); ?>" title="<?php esc_attr_e( 'Cancelar selección', 'andreani-shipping' ); ?>">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
	</button>
</div>

<?php require ANDREANI_PLUGIN_DIR . 'includes/admin/views/print-settings-modal.php'; ?>
