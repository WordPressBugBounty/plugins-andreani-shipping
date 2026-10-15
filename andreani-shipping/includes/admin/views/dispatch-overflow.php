<?php
/**
 * Qué pasa si te compran más de lo que entra en una caja: todo junto o cada caja con su etiqueta.
 *
 * @package AndreaniPlugin
 * @var array $strings  Textos de Andreani_Product_Bultos::get_ui_strings().
 * @var array $overflow prefix, mode y field (name, value).
 */

defined( 'ABSPATH' ) || exit;

$ov_multi     = Andreani_Product_Bultos::MODE_MULTIBULTO === $overflow['mode'];
$ov_supported = Andreani_Product_Bultos::overflow_supported();
$ov_view      = Andreani_Product_Bultos::exception_view( $overflow['field']['value'] );
$ov_options   = array(
	'together' => array( $strings['opt_together_title'], $strings['opt_together_hint'], ! $ov_view['on'] ),
	'separate' => array( $strings['opt_separate_title'], $strings['opt_separate_hint'], $ov_view['on'] ),
);
?>
<div class="andr-overflow" data-andr="overflow" data-no-bigger="<?php echo false === Andreani_Product_Bultos::account_has_bigger() ? '1' : '0'; ?>" data-overflow-supported="<?php echo $ov_supported ? '1' : '0'; ?>">
	<div class="andr-ask" data-andr="capacity-overflow-wrap"<?php echo ( $ov_supported && ! $ov_multi ) ? '' : ' hidden'; ?>>
		<span class="andr-ask__q" id="<?php echo esc_attr( $overflow['prefix'] ); ?>overflow-q"><?php echo esc_html( $strings['overflow_question'] ); ?></span>
		<input type="hidden" name="<?php echo esc_attr( $overflow['field']['name'] ); ?>" id="<?php echo esc_attr( $overflow['prefix'] . 'overflow' ); ?>" value="<?php echo esc_attr( $ov_view['stored'] ); ?>" data-andr="overflow-input">
		<div class="andr-opts" role="radiogroup" aria-labelledby="<?php echo esc_attr( $overflow['prefix'] ); ?>overflow-q">
			<?php foreach ( $ov_options as $ov_key => $ov_option ) : ?>
				<label class="andr-opt">
					<input type="radio" class="andr-opt__input" name="<?php echo esc_attr( $overflow['prefix'] ); ?>overflow-choice" value="<?php echo esc_attr( $ov_key ); ?>"<?php checked( $ov_option[2] ); ?>>
					<?php echo Andreani_Product_Bultos::art( $ov_key, array( 64, 50 ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span class="andr-opt__text">
						<span class="andr-opt__title"><?php echo esc_html( $ov_option[0] ); ?></span>
						<span class="andr-opt__hint"><?php echo esc_html( $ov_option[1] ); ?></span>
					</span>
				</label>
			<?php endforeach; ?>
		</div>
	</div>
	<p class="andr-dispatch__warnbox" data-andr="no-bigger" hidden><?php echo esc_html( $strings['no_bigger'] ); ?></p>
</div>
