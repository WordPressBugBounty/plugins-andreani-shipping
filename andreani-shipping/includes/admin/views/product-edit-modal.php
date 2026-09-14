<?php
/**
 * Partial: Modal de edición de dimensiones de producto.
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

$wc_weight_unit    = get_option( 'woocommerce_weight_unit', 'kg' );
$wc_dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );
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
					<input type="number" id="andreani-edit-length" class="regular-text" min="0" step="0.01" placeholder="0.00" />
				</label>
				<label class="andreani-product-dims__field">
					<span class="andreani-product-dims__label"><?php printf( esc_html__( 'Ancho (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?></span>
					<input type="number" id="andreani-edit-width" class="regular-text" min="0" step="0.01" placeholder="0.00" />
				</label>
				<label class="andreani-product-dims__field">
					<span class="andreani-product-dims__label"><?php printf( esc_html__( 'Alto (%s)', 'andreani-shipping' ), esc_html( $wc_dimension_unit ) ); ?></span>
					<input type="number" id="andreani-edit-height" class="regular-text" min="0" step="0.01" placeholder="0.00" />
				</label>
			</div>

			<div class="andreani-bultos-block">
				<div class="andreani-bultos-block__head">
					<span class="andreani-bultos-block__title"><?php esc_html_e( 'Bultos del envío', 'andreani-shipping' ); ?></span>
					<span id="andreani-edit-bigger-badge" class="andr-badge andr-badge--neutral"></span>
				</div>
				<p class="andreani-product-dims__hint"><?php esc_html_e( 'El bulto principal usa el peso y las medidas de arriba. Sumá un bulto extra por cada paquete adicional. El envío se categoriza como Bigger solo si la suma supera los límites; si no, es Paquete común.', 'andreani-shipping' ); ?></p>
				<div class="andreani-bultos-stepper">
					<button type="button" class="andreani-stepper-btn" id="andreani-bultos-minus" aria-label="<?php esc_attr_e( 'Quitar bulto', 'andreani-shipping' ); ?>">&minus;</button>
					<span class="andreani-bultos-count" id="andreani-bultos-count">0</span>
					<button type="button" class="andreani-stepper-btn" id="andreani-bultos-plus" aria-label="<?php esc_attr_e( 'Agregar bulto', 'andreani-shipping' ); ?>">+</button>
					<span class="andreani-bultos-stepper__label"><?php esc_html_e( 'bultos adicionales', 'andreani-shipping' ); ?></span>
				</div>
				<div id="andreani-bultos-cards" class="andreani-bultos-cards"></div>
			</div>

			<div class="andreani-bultos-block andreani-apilado-block">
				<div class="andreani-bultos-block__head">
					<span class="andreani-bultos-block__title"><?php esc_html_e( 'Apilado de bultos', 'andreani-shipping' ); ?></span>
				</div>
				<div class="andr-badge andr-badge--warning andreani-apilado-block__lock" id="andreani-edit-apilado-lock" style="display:none;">
					<?php esc_html_e( 'No se puede apilar mientras el producto tenga bultos adicionales.', 'andreani-shipping' ); ?>
				</div>
				<label class="andreani-apilado-block__toggle">
					<input type="checkbox" id="andreani-edit-apilado-toggle" />
					<span><?php esc_html_e( '¿Es un bulto apilable?', 'andreani-shipping' ); ?></span>
				</label>
				<div id="andreani-edit-apilado-fields" class="andreani-apilado-grid">
					<label class="andreani-product-dims__field">
						<span class="andreani-product-dims__label"><?php esc_html_e( 'Límite unidades apilables', 'andreani-shipping' ); ?></span>
						<input type="number" id="andreani-edit-apilado-max-units" class="regular-text" min="2" step="1" placeholder="6" />
					</label>
					<label class="andreani-product-dims__field">
						<span class="andreani-product-dims__label"><?php esc_html_e( 'Aumenta alto (cm)', 'andreani-shipping' ); ?></span>
						<input type="number" id="andreani-edit-apilado-inc-height" class="regular-text" min="0" step="0.01" placeholder="0.00" />
					</label>
					<label class="andreani-product-dims__field">
						<span class="andreani-product-dims__label"><?php esc_html_e( 'Aumenta ancho (cm)', 'andreani-shipping' ); ?></span>
						<input type="number" id="andreani-edit-apilado-inc-width" class="regular-text" min="0" step="0.01" placeholder="0.00" />
					</label>
					<label class="andreani-product-dims__field">
						<span class="andreani-product-dims__label"><?php esc_html_e( 'Aumenta profund. (cm)', 'andreani-shipping' ); ?></span>
						<input type="number" id="andreani-edit-apilado-inc-depth" class="regular-text" min="0" step="0.01" placeholder="0.00" />
					</label>
				</div>
				<p class="andreani-product-dims__hint"><?php esc_html_e( 'La primera unidad ocupa las medidas de arriba y cada unidad extra suma el incremento. Por ejemplo: una silla de 45 cm de alto que apila de a 6 sumando 15 cm por unidad, en un pedido de 4 unidades viaja como un solo bulto de 90 cm de alto.', 'andreani-shipping' ); ?></p>
			</div>

			<div id="andreani-edit-message" class="andreani-products-inline-msg" style="display:none;"></div>
		</div>
		<div class="andr-modal__footer andreani-modal__footer">
			<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm andreani-modal__close"><?php esc_html_e( 'Cancelar', 'andreani-shipping' ); ?></button>
			<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-product-edit-save"><?php esc_html_e( 'Guardar', 'andreani-shipping' ); ?></button>
		</div>
	</div>
</div>
