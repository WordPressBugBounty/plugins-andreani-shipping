<?php
/**
 * Partial: panel de edición inline de un producto (se mueve a la fila abierta).
 *
 * @package AndreaniPlugin
 */

defined( 'ABSPATH' ) || exit;

$wc_weight_unit    = get_option( 'woocommerce_weight_unit', 'kg' );
$wc_dimension_unit = get_option( 'woocommerce_dimension_unit', 'cm' );

$strings = Andreani_Product_Bultos::get_ui_strings();

?>
<div id="andreani-product-editor-holder" hidden>
<div id="andreani-product-editor" class="andreani-product-editor">
	<input type="hidden" id="andreani-edit-product-id" value="" />
	<div class="andr-pem andr-tabs">
		<div class="andr-pem__bar">
			<div class="andr-pem__product">
				<span class="andr-pem__product-name" id="andreani-edit-product-name"></span>
				<span class="andr-badge andr-badge--warning" id="andreani-edit-bigger-status"><?php echo esc_html( $strings['box_status_missing'] ); ?></span>
				<a class="andr-pem__product-link" id="andreani-edit-product-link" href="#" target="_blank" rel="noopener" hidden><?php esc_html_e( 'Editar en WooCommerce', 'andreani-shipping' ); ?> &#8599;</a>
			</div>
		</div>

		<div class="andr-tabs__panel andr-tabs__panel--active" data-panel="pem-config" role="tabpanel">
			<div class="andr-pem__cols">
				<div class="andr-pem__col andr-pem__col--data">
					<?php
					$kind = array(
						'prefix' => 'andreani-edit-',
						'name'   => 'andreani_edit_dispatch_mode',
						'mode'   => Andreani_Product_Bultos::MODE_SINGLE,
					);
					include ANDREANI_PLUGIN_DIR . 'includes/admin/views/dispatch-kind.php';
					?>

				<?php
				$stack = array(
					'max'    => array( 'id' => 'andreani-edit-apilado-max-units' ),
					'height' => array( 'id' => 'andreani-edit-apilado-inc-height' ),
					'width'  => array( 'id' => 'andreani-edit-apilado-inc-width' ),
					'depth'  => array( 'id' => 'andreani-edit-apilado-inc-depth' ),
				);
				ob_start();
				?>
				<div class="andr-boxes__panel" id="andreani-edit-panel-apilado" data-andr="stack-fields" hidden>
					<div id="andreani-edit-apilado-fields">
						<?php include ANDREANI_PLUGIN_DIR . 'includes/admin/views/dispatch-stack-fields.php'; ?>
					</div>
					<p class="andr-dispatch__warnbox" id="andreani-edit-apilado-invalid" style="display:none;"><?php echo esc_html( $strings['apilado_invalid'] ); ?></p>
				</div>
				<?php
				$cap = array(
					'stack_html' => ob_get_clean(),
					'prefix'   => 'andreani-edit-',
					'mode'     => Andreani_Product_Bultos::MODE_SINGLE,
					'units'    => array( 'value' => 1 ),
				);
				include ANDREANI_PLUGIN_DIR . 'includes/admin/views/dispatch-capacity.php';
				?>

					<?php
					$pkg = array( 'value' => '' );
					include ANDREANI_PLUGIN_DIR . 'includes/admin/views/dispatch-package.php';
					?>

					<div class="andr-field andr-pem__measures">
					<span class="andr-ask__q" id="andreani-edit-measures-label" data-wc="<?php esc_attr_e( 'Medidas (las de WooCommerce)', 'andreani-shipping' ); ?>" data-weight="<?php esc_attr_e( 'Peso (el de WooCommerce)', 'andreani-shipping' ); ?>"><?php esc_html_e( 'Medidas (las de WooCommerce)', 'andreani-shipping' ); ?></span>
					<section class="andr-boxes">
					<div class="andr-boxes__grid">
						<?php include ANDREANI_PLUGIN_DIR . 'includes/admin/views/dispatch-box-head.php'; ?>
					<div class="andr-box andr-box--main">
						<span class="andr-box__title" id="andreani-edit-box-title"><?php echo esc_html( $strings['box_single'] ); ?></span>
							<label class="andr-box__own andr-box__ref">
								<span class="screen-reader-text"><?php echo esc_html( $strings['piece_reference'] ); ?></span>
								<input type="text" id="andreani-edit-main-ref" maxlength="60" autocomplete="off" placeholder="<?php echo esc_attr( $strings['main_ref_hint'] ); ?>" />
							</label>
						<div class="andr-box__dims andreani-product-dims">
							<?php
							echo Andreani_Product_Bultos::dim_field( 'length', $strings['label_length'], $wc_dimension_unit, array( 'id' => 'andreani-edit-length', 'min' => '0', 'step' => 'any', 'placeholder' => '0.00' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo Andreani_Product_Bultos::dim_field( 'width', $strings['label_width'], $wc_dimension_unit, array( 'id' => 'andreani-edit-width', 'min' => '0', 'step' => 'any', 'placeholder' => '0.00' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo Andreani_Product_Bultos::dim_field( 'height', $strings['label_height'], $wc_dimension_unit, array( 'id' => 'andreani-edit-height', 'min' => '0', 'step' => 'any', 'placeholder' => '0.00' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							echo Andreani_Product_Bultos::dim_field( 'weight', $strings['label_weight'], $wc_weight_unit, array( 'id' => 'andreani-edit-weight', 'min' => '0', 'step' => '0.001', 'placeholder' => '0.000' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
					</div>
					<div id="andreani-bultos-cards" class="andr-boxes__list"></div>
					</div>

					<div class="andr-boxes__panel andr-boxes__panel--multi" id="andreani-edit-panel-multibulto" hidden>
						<p class="andr-dispatch__note"><?php echo esc_html( $strings['multi_note'] ); ?></p>
						<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm" id="andreani-bultos-add"><?php echo esc_html( $strings['piece_add'] ); ?></button>
						<p class="andr-dispatch__warnbox" id="andreani-edit-bultos-invalid" style="display:none;"><?php echo esc_html( $strings['bultos_invalid'] ); ?></p>
					</div>
				</section>
				</div>
				<?php
				$overflow = array(
					'prefix' => 'andreani-edit-',
					'mode'   => Andreani_Product_Bultos::MODE_SINGLE,
					'field'  => array( 'name' => 'andreani_edit_overflow', 'value' => Andreani_Product_Bultos::OVERFLOW_INHERIT ),
				);
				include ANDREANI_PLUGIN_DIR . 'includes/admin/views/dispatch-overflow.php';
				?>

				<div class="andr-field andr-pem__sku-field" hidden>
					<label class="andr-ask__q" for="andreani-edit-sku"><?php echo esc_html( $strings['sku_label'] ); ?></label>
					<input type="text" id="andreani-edit-sku" class="andr-pem__sku" autocomplete="off" />
					<p class="andr-pem__hint" id="andreani-edit-sku-hint" data-default="<?php esc_attr_e( 'Lo vamos a usar para sincronizar tus productos con Andreani.', 'andreani-shipping' ); ?>" data-woo="<?php esc_attr_e( 'Tomado de WooCommerce', 'andreani-shipping' ); ?>" data-own="<?php esc_attr_e( 'Propio de Andreani', 'andreani-shipping' ); ?>" data-use-woo="<?php esc_attr_e( 'Usar el de WooCommerce', 'andreani-shipping' ); ?>"></p>
				</div>
				</div>

				<div class="andr-pem__col andr-pem__col--result">
					<?php include ANDREANI_PLUGIN_DIR . 'includes/admin/views/dispatch-preview.php'; ?>
					<div class="andr-pem__quote">
						<div class="andr-quote-row">
							<label class="andr-quote-field andr-quote-field--cp">
								<span class="andr-quote-field__label"><?php esc_html_e( 'CP destino', 'andreani-shipping' ); ?></span>
								<input type="text" id="andreani-edit-quote-cp" maxlength="10" placeholder="<?php esc_attr_e( 'Ej: 1425', 'andreani-shipping' ); ?>" />
							</label>
							<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-edit-quote-submit"><?php esc_html_e( 'Cotizar', 'andreani-shipping' ); ?></button>
						</div>
						<div id="andreani-edit-quote-results" class="andreani-quote-results" data-stale-text="<?php esc_attr_e( 'Volvé a cotizar', 'andreani-shipping' ); ?>" aria-live="polite" hidden></div>
					</div>
				</div>
			</div>
		</div>

	</div>

	<div id="andreani-edit-saving"></div>

	<div id="andreani-edit-message" class="andreani-products-inline-msg" style="display:none;"></div>

	<div class="andr-pem__footer">
		<div class="andr-pem__confirm" id="andreani-edit-confirm" role="alert" hidden>
			<span><?php esc_html_e( 'Tenés cambios sin guardar', 'andreani-shipping' ); ?></span>
			<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm" id="andreani-edit-discard"><?php esc_html_e( 'Descartar', 'andreani-shipping' ); ?></button>
			<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-edit-keep"><?php esc_html_e( 'Seguir editando', 'andreani-shipping' ); ?></button>
		</div>
		<div class="andr-pem__confirm" id="andreani-edit-confirm-discard" role="alert" hidden>
			<span id="andreani-edit-confirm-discard-text"
				data-apilado="<?php echo esc_attr__( 'Vas a quitar la configuración de «Se apilan» de este producto', 'andreani-shipping' ); ?>"
				data-bultos="<?php echo esc_attr__( 'Vas a quitar las cajas adicionales de este producto', 'andreani-shipping' ); ?>"
				data-both="<?php echo esc_attr__( 'Vas a quitar la configuración de «Se apilan» y las cajas adicionales de este producto', 'andreani-shipping' ); ?>"></span>
			<button type="button" class="andr-btn andr-btn--secondary andr-btn--sm" id="andreani-edit-discard-go"><?php esc_html_e( 'Guardar igual', 'andreani-shipping' ); ?></button>
			<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-edit-discard-cancel"><?php esc_html_e( 'Cancelar', 'andreani-shipping' ); ?></button>
		</div>
		<button type="button" class="andr-btn andr-btn--ghost andr-btn--sm" id="andreani-product-edit-cancel"><?php esc_html_e( 'Cancelar', 'andreani-shipping' ); ?></button>
		<button type="button" class="andr-btn andr-btn--primary andr-btn--sm" id="andreani-product-edit-save"><?php esc_html_e( 'Guardar', 'andreani-shipping' ); ?></button>
	</div>
</div>
</div>
