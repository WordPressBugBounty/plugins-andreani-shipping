<?php
/**
 * Partial: Modal de edición de dimensiones de producto.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

$wc_weight_unit    = get_option( 'woocommerce_weight_unit', 'kg' );
$wc_dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );

$strings = Andreani_Product_Bultos::get_ui_strings();

$mode_cards = array(
	Andreani_Product_Bultos::MODE_SINGLE     => array( $strings['mode_single_title'], $strings['mode_single_desc'] ),
	Andreani_Product_Bultos::MODE_APILADO    => array( $strings['mode_apilado_title'], $strings['mode_apilado_desc'] ),
	Andreani_Product_Bultos::MODE_MULTIBULTO => array( $strings['mode_multibulto_title'], $strings['mode_multibulto_desc'] ),
);
?>
<div id="andreani-product-edit-modal" class="andr-modal andreani-modal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="andreani-product-edit-modal-title">
	<div class="andr-modal__backdrop andreani-modal__backdrop"></div>
	<div class="andr-modal__container andreani-modal__container">
		<div class="andr-modal__header andreani-modal__header" data-draggable="true">
			<svg class="andr-modal__logo" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
			<h3 id="andreani-product-edit-modal-title" class="andr-modal__title"><?php esc_html_e( 'Editar dimensiones del producto', 'andreani-shipping' ); ?></h3>
			<button type="button" class="andr-modal__close andreani-modal__close" aria-label="<?php esc_attr_e( 'Cerrar', 'andreani-shipping' ); ?>">&times;</button>
		</div>
		<div class="andr-modal__body andreani-modal__body">
			<input type="hidden" id="andreani-edit-product-id" value="" />
			<div class="andreani-edit-product-head">
				<img id="andreani-edit-product-thumb" class="andreani-edit-product-head__thumb" src="" alt="" />
				<span id="andreani-edit-product-name" class="andreani-edit-product-head__name"></span>
			</div>

			<div class="andreani-product-dims">
				<label class="andreani-product-dims__field">
					<span class="andreani-product-dims__label"><?php printf( esc_html__( 'Peso (%s)', 'andreani-shipping' ), esc_html( $wc_weight_unit ) ); ?></span>
					<input type="number" id="andreani-edit-weight" class="regular-text" min="0" step="0.001" placeholder="0.000" />
				</label>
				<label class="andreani-product-dims__field">
					<span class="andreani-product-dims__label"><?php printf( esc_html__( 'Largo (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?></span>
					<input type="number" id="andreani-edit-length" class="regular-text" min="0" step="any" placeholder="0.00" />
				</label>
				<label class="andreani-product-dims__field">
					<span class="andreani-product-dims__label"><?php printf( esc_html__( 'Ancho (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?></span>
					<input type="number" id="andreani-edit-width" class="regular-text" min="0" step="any" placeholder="0.00" />
				</label>
				<label class="andreani-product-dims__field">
					<span class="andreani-product-dims__label"><?php printf( esc_html__( 'Alto (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?></span>
					<input type="number" id="andreani-edit-height" class="regular-text" min="0" step="any" placeholder="0.00" />
				</label>
			</div>

			<p class="andreani-despacho-status" id="andreani-edit-bigger-status"></p>

			<div class="andreani-despacho-block">
				<span class="andreani-despacho-block__title"><?php echo esc_html( $strings['mode_question'] ); ?></span>

				<div class="andreani-despacho-cards">
					<?php foreach ( $mode_cards as $mode_value => $mode_copy ) : ?>
						<label class="andreani-despacho-card" for="andreani-edit-mode-<?php echo esc_attr( $mode_value ); ?>">
							<input type="radio"
								name="andreani_edit_dispatch_mode"
								id="andreani-edit-mode-<?php echo esc_attr( $mode_value ); ?>"
								class="andreani-despacho-card__input"
								value="<?php echo esc_attr( $mode_value ); ?>"
								<?php checked( Andreani_Product_Bultos::MODE_SINGLE, $mode_value ); ?> />
							<span class="andreani-despacho-card__copy">
								<span class="andreani-despacho-card__title"><?php echo esc_html( $mode_copy[0] ); ?></span>
								<span class="andreani-despacho-card__desc"><?php echo esc_html( $mode_copy[1] ); ?></span>
							</span>
						</label>
					<?php endforeach; ?>
				</div>

				<div class="andreani-despacho-panel" id="andreani-edit-panel-apilado" style="display:none;">
					<div id="andreani-edit-apilado-fields" class="andreani-apilado-grid">
						<label class="andreani-product-dims__field">
							<span class="andreani-product-dims__label"><?php esc_html_e( 'Límite unidades apilables', 'andreani-shipping' ); ?></span>
							<input type="number" id="andreani-edit-apilado-max-units" class="regular-text" min="2" step="1" />
						</label>
						<label class="andreani-product-dims__field">
							<span class="andreani-product-dims__label"><?php esc_html_e( 'Aumenta alto (cm)', 'andreani-shipping' ); ?></span>
							<input type="number" id="andreani-edit-apilado-inc-height" class="regular-text" min="0" step="any" />
						</label>
						<label class="andreani-product-dims__field">
							<span class="andreani-product-dims__label"><?php esc_html_e( 'Aumenta ancho (cm)', 'andreani-shipping' ); ?></span>
							<input type="number" id="andreani-edit-apilado-inc-width" class="regular-text" min="0" step="any" />
						</label>
						<label class="andreani-product-dims__field">
							<span class="andreani-product-dims__label"><?php esc_html_e( 'Aumenta profund. (cm)', 'andreani-shipping' ); ?></span>
							<input type="number" id="andreani-edit-apilado-inc-depth" class="regular-text" min="0" step="any" />
						</label>
					</div>
					<p class="andreani-product-dims__hint"><?php esc_html_e( 'La primera unidad ocupa las medidas de arriba y cada unidad extra suma el incremento. Por ejemplo: una silla de 45 cm de alto que apila de a 6 sumando 15 cm por unidad, en un pedido de 4 unidades viaja como un solo bulto de 90 cm de alto.', 'andreani-shipping' ); ?></p>
					<p class="andreani-despacho-error" id="andreani-edit-apilado-invalid" style="display:none;"><?php echo esc_html( $strings['apilado_invalid'] ); ?></p>
				</div>

				<div class="andreani-despacho-panel" id="andreani-edit-panel-multibulto" style="display:none;">
					<p class="andreani-despacho-warning"><?php echo esc_html( $strings['mode_multibulto_warning'] ); ?></p>
					<div class="andreani-bultos-stepper">
						<button type="button" class="andreani-stepper-btn" id="andreani-bultos-minus" aria-label="<?php esc_attr_e( 'Quitar pieza', 'andreani-shipping' ); ?>">&minus;</button>
						<span class="andreani-bultos-count" id="andreani-bultos-count">0</span>
						<button type="button" class="andreani-stepper-btn" id="andreani-bultos-plus" aria-label="<?php esc_attr_e( 'Agregar pieza', 'andreani-shipping' ); ?>">+</button>
						<span class="andreani-bultos-stepper__label"><?php esc_html_e( 'piezas además del bulto principal', 'andreani-shipping' ); ?></span>
					</div>
					<div id="andreani-bultos-cards" class="andreani-bultos-cards"></div>
					<p class="andreani-despacho-error" id="andreani-edit-bultos-invalid" style="display:none;"><?php echo esc_html( $strings['bultos_invalid'] ); ?></p>
				</div>
			</div>

			<div class="andreani-despacho-block andreani-despacho-preview" id="andreani-edit-preview">
				<span class="andreani-despacho-block__title andreani-despacho-preview__title"><?php echo esc_html( $strings['preview_title'] ); ?></span>
				<p class="andreani-product-dims__hint andreani-despacho-preview__help"><?php echo esc_html( $strings['preview_help'] ); ?></p>
				<div class="andreani-despacho-preview__body" id="andreani-edit-preview-body" aria-live="polite"></div>
			</div>

			<div class="andreani-despacho-block andreani-edit-quote">
				<span class="andreani-despacho-block__title"><?php esc_html_e( 'Probar cotización', 'andreani-shipping' ); ?></span>
				<p class="andreani-product-dims__hint"><?php esc_html_e( 'Cotiza con lo que cargaste en pantalla, sin guardar el producto.', 'andreani-shipping' ); ?></p>
				<div class="andreani-quote-tester">
					<label class="andreani-quote-tester__field">
						<span class="andreani-quote-tester__label"><?php esc_html_e( 'CP destino', 'andreani-shipping' ); ?></span>
						<input type="text" id="andreani-edit-quote-cp" class="regular-text" maxlength="10" placeholder="<?php esc_attr_e( 'Ej: 1425', 'andreani-shipping' ); ?>" />
					</label>
					<label class="andreani-quote-tester__field andreani-edit-quote__qty">
						<span class="andreani-quote-tester__label"><?php esc_html_e( 'Unidades', 'andreani-shipping' ); ?></span>
						<input type="number" id="andreani-edit-quote-qty" min="1" max="99" step="1" value="1" />
					</label>
					<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-edit-quote-submit"><?php esc_html_e( 'Cotizar', 'andreani-shipping' ); ?></button>
				</div>
				<div id="andreani-edit-quote-results" class="andreani-quote-results" style="display:none;"></div>
				<div id="andreani-edit-quote-message" class="andreani-products-inline-msg" style="display:none;"></div>
			</div>

			<div id="andreani-edit-message" class="andreani-products-inline-msg" style="display:none;"></div>
		</div>
		<div class="andr-modal__footer andreani-modal__footer">
			<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-modal__close"><?php esc_html_e( 'Cancelar', 'andreani-shipping' ); ?></button>
			<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-product-edit-save"><?php esc_html_e( 'Guardar', 'andreani-shipping' ); ?></button>
		</div>
	</div>
</div>
