<?php
/**
 * Template: Página Promociones de envío
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

$andreani_services = Andreani_Shipping_Promotions::services();
?>
<div class="wrap andreani-promos-wrap" data-pr-root>

	<hr class="wp-header-end">

	<div class="andreani-page-card">
		<?php
		$andreani_page_title = __( 'Promociones de envío', 'andreani-shipping' );
		$andreani_page_icon  = '<svg class="andr-pr__title-icon" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/></svg>';
		require ANDREANI_PLUGIN_DIR . 'includes/admin/views/page-card-header.php';
		?>
		<div class="andreani-page-card__body">
			<div class="andr-pr__bar">
				<p class="andr-pr__intro"><?php esc_html_e( 'Poné un descuento o un recargo al precio del envío para que el cliente lo vea ya aplicado. Si el envío ya es gratis, no se aplica ninguna promoción.', 'andreani-shipping' ); ?></p>
				<button type="button" class="andr-btn andr-btn--secondary andr-pr__add" data-pr-add>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
					<?php esc_html_e( 'Nueva promoción', 'andreani-shipping' ); ?>
				</button>
				<span class="andr-pr__count" data-pr-count></span>
			</div>

			<p class="andr-pr__note">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12.5"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
				<span><?php esc_html_e( 'La promoción solo cambia lo que paga tu cliente por el envío. No cambia lo que te factura Andreani.', 'andreani-shipping' ); ?></span>
			</p>

			<p class="andr-pr__error" data-pr-error role="alert" hidden></p>

			<div class="andr-pr__list" data-pr-list hidden></div>

			<div class="andr-pr__empty" data-pr-empty hidden>
				<p><?php esc_html_e( 'Todavía no cargaste promociones. Armá la primera: por ejemplo, 15 % menos en el envío durante una semana, o un envío más barato en el interior.', 'andreani-shipping' ); ?></p>
			</div>
		</div>
	</div>

	<div class="andr-modal andr-pr__modal" data-pr-modal role="dialog" aria-modal="true" aria-labelledby="andr-pr-modal-title" hidden>
		<div class="andr-modal__backdrop" data-pr-close></div>
		<div class="andr-modal__container">
			<div class="andr-modal__header">
				<div class="andr-pr__heading">
					<h3 id="andr-pr-modal-title" class="andr-modal__title" data-pr-modal-title></h3>
					<p class="andr-pr__subtitle"><?php esc_html_e( 'Descuento o recargo sobre el envío que paga tu cliente.','andreani-shipping' ); ?></p>
				</div>
				<button type="button" class="andr-modal__close" data-pr-close aria-label="<?php esc_attr_e( 'Cerrar', 'andreani-shipping' ); ?>">&times;</button>
			</div>
			<div class="andr-modal__body">
				<div class="andr-pr__form">

					<div class="andr-pr__col">
						<section class="andr-pr__box">
							<div class="andr-pr__box-head">
								<h4 class="andr-pr__box-title">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8z"/><circle cx="7.5" cy="7.5" r="1.2"/></svg>
									<?php esc_html_e( 'Promoción', 'andreani-shipping' ); ?>
								</h4>
								<p class="andr-pr__notice">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12.5"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
									<span><?php esc_html_e( 'No cambia lo que te factura Andreani.', 'andreani-shipping' ); ?></span>
								</p>
							</div>
							<div class="andr-pr__field">
								<label class="andr-pr__label" for="andr-pr-name"><?php esc_html_e( 'Nombre', 'andreani-shipping' ); ?></label>
								<input type="text" id="andr-pr-name" class="andr-pr__input" data-pr-name maxlength="<?php echo esc_attr( Andreani_Shipping_Promotions::NAME_MAX_LENGTH ); ?>" placeholder="<?php esc_attr_e( 'Ej. Black Friday', 'andreani-shipping' ); ?>" />
								<span class="andr-pr__hint">
									<?php
									/* translators: %d: máximo de caracteres del nombre */
									echo esc_html( sprintf( __( 'Lo ve el cliente junto al envío · máx. %d', 'andreani-shipping' ), Andreani_Shipping_Promotions::NAME_MAX_LENGTH ) );
									?>
								</span>
							</div>

							<div class="andr-pr__row andr-pr__row--calc">
								<div class="andr-pr__field">
									<span class="andr-pr__label" id="andr-pr-type-label"><?php esc_html_e( 'Tipo', 'andreani-shipping' ); ?></span>
									<div class="andr-pr__seg" role="radiogroup" aria-labelledby="andr-pr-type-label">
										<button type="button" class="andr-pr__segbtn" role="radio" data-pr-type="descuento"><?php esc_html_e( 'Descuento', 'andreani-shipping' ); ?></button>
										<button type="button" class="andr-pr__segbtn" role="radio" data-pr-type="recargo"><?php esc_html_e( 'Recargo', 'andreani-shipping' ); ?></button>
									</div>
								</div>
								<div class="andr-pr__field">
									<span class="andr-pr__label" id="andr-pr-kind-label"><?php esc_html_e( 'Cálculo', 'andreani-shipping' ); ?></span>
									<div class="andr-pr__seg" role="radiogroup" aria-labelledby="andr-pr-kind-label">
										<button type="button" class="andr-pr__segbtn" role="radio" data-pr-form-kind="porcentaje"><?php esc_html_e( 'Porcentaje', 'andreani-shipping' ); ?></button>
										<button type="button" class="andr-pr__segbtn" role="radio" data-pr-form-kind="fijo"><?php esc_html_e( 'Monto fijo', 'andreani-shipping' ); ?></button>
									</div>
								</div>
								<div class="andr-pr__field">
									<label class="andr-pr__label" for="andr-pr-value"><?php esc_html_e( 'Valor', 'andreani-shipping' ); ?></label>
									<label class="andr-pr__affix" for="andr-pr-value">
										<span class="andr-pr__prefix" data-pr-prefix hidden>$</span>
										<input type="text" id="andr-pr-value" class="andr-pr__affix-input" data-pr-value inputmode="decimal" autocomplete="off" />
										<span class="andr-pr__suffix" data-pr-suffix>%</span>
									</label>
								</div>
							</div>

							<p class="andr-pr__example" data-pr-example></p>
						</section>

						<section class="andr-pr__box">
							<h4 class="andr-pr__box-title">
								<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3.3 7.5L12 12.5l8.7-5"/><path d="M12 22v-9.5"/></svg>
								<?php esc_html_e( 'Servicios', 'andreani-shipping' ); ?>
							</h4>
							<div class="andr-pr__services">
								<?php foreach ( $andreani_services as $andreani_service => $andreani_service_label ) : ?>
									<?php $andreani_service_icons = Andreani_Promotions_Page::service_icons( $andreani_service ); ?>
									<label class="andr-pr__tile<?php echo '' !== $andreani_service_icons['on'] ? ' has-on' : ''; ?>">
										<input type="checkbox" class="andr-pr__tile-input" value="<?php echo esc_attr( $andreani_service ); ?>" data-pr-service />
										<span class="andr-pr__tile-ico">
											<img class="andr-pr__ico andr-pr__ico--off" src="<?php echo esc_url( $andreani_service_icons['off'] ); ?>" alt="" aria-hidden="true">
											<?php if ( '' !== $andreani_service_icons['on'] ) : ?>
												<img class="andr-pr__ico andr-pr__ico--on" src="<?php echo esc_url( $andreani_service_icons['on'] ); ?>" alt="" aria-hidden="true">
											<?php endif; ?>
										</span>
										<span class="andr-pr__tile-name"><?php echo esc_html( $andreani_service_label ); ?></span>
										<svg class="andr-pr__tile-check" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><polyline points="20 6 9 17 4 12"/></svg>
									</label>
								<?php endforeach; ?>
							</div>
						</section>

						<section class="andr-pr__box andr-pr__box--dates">
							<div class="andr-pr__titlebar">
								<h4 class="andr-pr__box-title">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
									<?php esc_html_e( 'Vigencia', 'andreani-shipping' ); ?>
								</h4>
								<span class="andr-pr__info" tabindex="0" role="img" title="<?php esc_attr_e( 'Vacío = sin límite. Ambos días incluidos.', 'andreani-shipping' ); ?>" aria-label="<?php esc_attr_e( 'Vacío = sin límite. Ambos días incluidos.', 'andreani-shipping' ); ?>">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
								</span>
							</div>
							<div class="andr-pr__row andr-pr__row--dates">
								<div class="andr-pr__field">
									<label class="andr-pr__label" for="andr-pr-from"><?php esc_html_e( 'Desde', 'andreani-shipping' ); ?></label>
									<input type="date" lang="es-AR" id="andr-pr-from" class="andr-pr__input" data-pr-from />
								</div>
								<div class="andr-pr__field">
									<label class="andr-pr__label" for="andr-pr-until"><?php esc_html_e( 'Hasta', 'andreani-shipping' ); ?></label>
									<input type="date" lang="es-AR" id="andr-pr-until" class="andr-pr__input" data-pr-until />
								</div>
							</div>
						</section>
					</div>

					<div class="andr-pr__col">
						<section class="andr-pr__box andr-pr__box--geo">
							<div class="andr-pr__box-head">
								<h4 class="andr-pr__box-title">
									<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
									<?php esc_html_e( 'Dónde aplica', 'andreani-shipping' ); ?>
								</h4>
								<span class="andr-pr__pill" data-pr-summary aria-live="polite"></span>
							</div>
							<div class="andr-pr__stage">
								<div class="andr-pr__geo-side">
									<div class="andr-pr__shortcuts">
										<button type="button" class="andr-pr__chip" aria-pressed="false" data-pr-pick="all"><?php esc_html_e( 'Todo el país', 'andreani-shipping' ); ?></button>
										<?php foreach ( array_keys( Andreani_Shipping_Promotions::province_groups() ) as $andreani_group ) : ?>
											<button type="button" class="andr-pr__chip" aria-pressed="false" data-pr-region="<?php echo esc_attr( $andreani_group ); ?>"><?php echo esc_html( $andreani_group ); ?></button>
										<?php endforeach; ?>
										<button type="button" class="andr-pr__clear" data-pr-pick="none"><?php esc_html_e( 'Limpiar', 'andreani-shipping' ); ?></button>
									</div>
									<div class="andr-pr__side">
										<span class="andr-pr__sidelabel"><?php esc_html_e( 'Seleccionado', 'andreani-shipping' ); ?></span>
										<p class="andr-pr__detail" data-pr-detail></p>
										<p class="andr-pr__help"><?php esc_html_e( 'Arrastrá para pintar o despintar provincias', 'andreani-shipping' ); ?></p>
									</div>
								</div>
								<div class="andr-pr__mapwrap">
									<div class="andr-pr__map" data-pr-map-slot></div>
									<span class="andr-pr__credit"><?php esc_html_e( 'Límites: IGN', 'andreani-shipping' ); ?></span>
								</div>
							</div>
						</section>
					</div>

				</div>
				<p class="andr-pr__error" data-pr-form-error role="alert" hidden></p>
			</div>
			<div class="andr-modal__footer">
				<button type="button" class="andr-btn andr-btn--ghost" data-pr-close><?php esc_html_e( 'Cancelar', 'andreani-shipping' ); ?></button>
				<button type="button" class="andr-btn andr-btn--primary" data-pr-save><?php esc_html_e( 'Guardar', 'andreani-shipping' ); ?></button>
			</div>
		</div>
	</div>
</div>
