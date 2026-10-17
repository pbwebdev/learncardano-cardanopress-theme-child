<?php
/**
 * One field of a site form (see lc_form_definitions() in includes/forms.php).
 * POST names are the field keys prefixed `lc_`; the email confirmation box
 * posts as `lc_email_confirm`.
 *
 * @package CardanoPress Bootstrap
 *
 * @var array $args key, field, uid, error (message or '').
 */

$lcf_key      = $args['key'];
$lcf_field    = $args['field'];
$lcf_error    = $args['error'];
$lcf_id       = $args['uid'] . '-' . $lcf_key;
$lcf_required = ! empty( $lcf_field['required'] );
$lcf_type     = $lcf_field['type'];
$lcf_confirm  = 'email' === $lcf_type && ! empty( $lcf_field['confirm'] );
$lcf_group    = $lcf_confirm || 'checkbox' === $lcf_type;

$lcf_describe = array();
if ( ! empty( $lcf_field['description'] ) ) {
	$lcf_describe[] = $lcf_id . '-desc';
}
$lcf_describe[] = $lcf_id . '-error';

$lcf_classes = array( 'lc-form__field', 'lc-form__field--' . $lcf_type );
if ( ! empty( $lcf_field['half'] ) ) {
	$lcf_classes[] = 'lc-form__field--half';
}
if ( $lcf_error ) {
	$lcf_classes[] = 'is-invalid';
}

// Attributes shared by the field's controls.
$lcf_attrs = ' aria-describedby="' . esc_attr( implode( ' ', $lcf_describe ) ) . '"';
if ( $lcf_required ) {
	$lcf_attrs .= ' required aria-required="true"';
}
if ( $lcf_error ) {
	$lcf_attrs .= ' aria-invalid="true"';
}

$lcf_show_if = '';
if ( ! empty( $lcf_field['show_if'] ) ) {
	$lcf_show_if = ' data-lc-show-if="' . esc_attr(
		wp_json_encode(
			array(
				'field'  => $lcf_field['show_if'][0],
				'op'     => $lcf_field['show_if'][1],
				'values' => $lcf_field['show_if'][2],
			)
		)
	) . '"';
}

$lcf_req_mark = $lcf_required ? ' <span class="lc-form__req">' . esc_html__( '(Required)', 'learncardano' ) . '</span>' : '';
$lcf_label    = esc_html( $lcf_field['label'] ) . $lcf_req_mark;
$lcf_label_cl = 'lc-form__label' . ( ! empty( $lcf_field['hide_label'] ) ? ' lc-form__label--hidden' : '' );
$lcf_text_cl  = 'lc-form__input';
$lcf_ph       = ! empty( $lcf_field['placeholder'] ) && 'select' !== $lcf_type ? ' placeholder="' . esc_attr( $lcf_field['placeholder'] ) . '"' : '';
$lcf_max      = ! empty( $lcf_field['maxlength'] ) ? ' maxlength="' . (int) $lcf_field['maxlength'] . '"' : '';
$lcf_auto     = ! empty( $lcf_field['autocomplete'] ) ? ' autocomplete="' . esc_attr( $lcf_field['autocomplete'] ) . '"' : '';
?>
<?php if ( $lcf_group ) : ?>
<fieldset class="<?php echo esc_attr( implode( ' ', $lcf_classes ) ); ?>" data-lc-field="<?php echo esc_attr( $lcf_key ); ?>"<?php echo $lcf_show_if; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
	<legend class="<?php echo esc_attr( $lcf_label_cl ); ?>"><?php echo $lcf_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></legend>

	<?php if ( $lcf_confirm ) : ?>
		<div class="lc-form__pair">
			<div class="lc-form__sub">
				<input class="<?php echo esc_attr( $lcf_text_cl ); ?>" type="email" id="<?php echo esc_attr( $lcf_id ); ?>" name="lc_<?php echo esc_attr( $lcf_key ); ?>" autocomplete="email" inputmode="email"<?php echo $lcf_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
				<label class="lc-form__sublabel" for="<?php echo esc_attr( $lcf_id ); ?>"><?php echo esc_html( $lcf_field['sublabels'][0] ); ?></label>
			</div>
			<div class="lc-form__sub">
				<input class="<?php echo esc_attr( $lcf_text_cl ); ?>" type="email" id="<?php echo esc_attr( $lcf_id . '-confirm' ); ?>" name="lc_<?php echo esc_attr( $lcf_key ); ?>_confirm" autocomplete="email" inputmode="email"<?php echo $lcf_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
				<label class="lc-form__sublabel" for="<?php echo esc_attr( $lcf_id . '-confirm' ); ?>"><?php echo esc_html( $lcf_field['sublabels'][1] ); ?></label>
			</div>
		</div>
	<?php else : ?>
		<div class="lc-form__check">
			<input type="checkbox" id="<?php echo esc_attr( $lcf_id ); ?>" name="lc_<?php echo esc_attr( $lcf_key ); ?>" value="1"<?php echo $lcf_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
			<label for="<?php echo esc_attr( $lcf_id ); ?>">
				<?php
				echo wp_kses(
					$lcf_field['checkbox_label'],
					array(
						'a' => array(
							'href'   => true,
							'target' => true,
							'rel'    => true,
						),
					)
				);
				?>
			</label>
		</div>
	<?php endif; ?>
<?php else : ?>
<div class="<?php echo esc_attr( implode( ' ', $lcf_classes ) ); ?>" data-lc-field="<?php echo esc_attr( $lcf_key ); ?>"<?php echo $lcf_show_if; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
	<label class="<?php echo esc_attr( $lcf_label_cl ); ?>" for="<?php echo esc_attr( $lcf_id ); ?>"><?php echo $lcf_label; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?></label>

	<?php if ( 'select' === $lcf_type ) : ?>
		<select class="<?php echo esc_attr( $lcf_text_cl ); ?>" id="<?php echo esc_attr( $lcf_id ); ?>" name="lc_<?php echo esc_attr( $lcf_key ); ?>"<?php echo $lcf_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
			<option value=""><?php echo esc_html( ! empty( $lcf_field['placeholder'] ) ? $lcf_field['placeholder'] : '' ); ?></option>
			<?php foreach ( $lcf_field['choices'] as $lcf_value => $lcf_text ) : ?>
				<option value="<?php echo esc_attr( $lcf_value ); ?>"><?php echo esc_html( $lcf_text ); ?></option>
			<?php endforeach; ?>
		</select>
	<?php elseif ( 'textarea' === $lcf_type ) : ?>
		<textarea class="<?php echo esc_attr( $lcf_text_cl ); ?>" id="<?php echo esc_attr( $lcf_id ); ?>" name="lc_<?php echo esc_attr( $lcf_key ); ?>" rows="8"<?php echo $lcf_max . $lcf_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>></textarea>
	<?php else : ?>
		<?php
		$lcf_input_type = in_array( $lcf_type, array( 'email', 'url' ), true ) ? $lcf_type : 'text';
		if ( 'email' === $lcf_type && ! $lcf_auto ) {
			$lcf_auto = ' autocomplete="email"';
		}
		?>
		<input class="<?php echo esc_attr( $lcf_text_cl ); ?>" type="<?php echo esc_attr( $lcf_input_type ); ?>" id="<?php echo esc_attr( $lcf_id ); ?>" name="lc_<?php echo esc_attr( $lcf_key ); ?>"<?php echo $lcf_ph . $lcf_max . $lcf_auto . $lcf_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above. ?>>
	<?php endif; ?>
<?php endif; ?>

	<?php if ( ! empty( $lcf_field['description'] ) ) : ?>
		<p class="lc-form__desc" id="<?php echo esc_attr( $lcf_id . '-desc' ); ?>"><?php echo esc_html( $lcf_field['description'] ); ?></p>
	<?php endif; ?>
	<p class="lc-form__error" id="<?php echo esc_attr( $lcf_id . '-error' ); ?>"<?php echo $lcf_error ? '' : ' hidden'; ?>><?php echo esc_html( $lcf_error ); ?></p>
<?php echo $lcf_group ? '</fieldset>' : '</div>'; ?>
