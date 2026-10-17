<?php
/**
 * A site form (see includes/forms.php): the <form> element, status messages,
 * fields, anti-spam inputs and the submit button.
 *
 * @package CardanoPress Bootstrap
 *
 * @var array $args form_id, form, uid, args (show_title, show_description), status, recaptcha.
 */

$lcf_id      = $args['form_id'];
$lcf_form    = $args['form'];
$lcf_uid     = $args['uid'];
$lcf_opts    = $args['args'];
$lcf_status  = $args['status'];
$lcf_captcha = $args['recaptcha'];
$lcf_errors  = ( $lcf_status && 'error' === $lcf_status['status'] ) ? $lcf_status['errors'] : array();
$lcf_ok      = $lcf_status && 'success' === $lcf_status['status'];
$lcf_source  = is_singular() ? get_queried_object_id() : 0;
?>
<div class="lc-form-wrap" id="lc-form-<?php echo esc_attr( $lcf_opts['anchor'] ); ?>">
	<?php if ( $lcf_opts['show_title'] ) : ?>
		<h3 class="lc-form__title"><?php echo esc_html( $lcf_form['title'] ); ?></h3>
	<?php endif; ?>
	<?php if ( $lcf_opts['show_description'] && $lcf_form['description'] ) : ?>
		<p class="lc-form__intro"><?php echo esc_html( $lcf_form['description'] ); ?></p>
	<?php endif; ?>

	<?php if ( $lcf_ok ) : ?>
		<div class="lc-form__confirmation" role="status" tabindex="-1">
			<?php echo wp_kses_post( wpautop( esc_html( $lcf_form['confirmation'] ) ) ); ?>
		</div>
	<?php else : ?>
		<form class="lc-form lc-form--<?php echo esc_attr( $lcf_id ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-lc-form="<?php echo esc_attr( $lcf_id ); ?>">
			<div class="lc-form__summary" role="alert" tabindex="-1"<?php echo $lcf_status ? '' : ' hidden'; ?>>
				<?php
				if ( $lcf_status ) {
					echo esc_html( lc_form_message_text( $lcf_status['msg'] ? $lcf_status['msg'] : 'invalid' ) );
				}
				?>
			</div>

			<div class="lc-form__fields">
				<?php
				foreach ( $lcf_form['fields'] as $lcf_key => $lcf_field ) {
					get_template_part(
						'template-parts/form',
						'field',
						array(
							'key'   => $lcf_key,
							'field' => $lcf_field,
							'uid'   => $lcf_uid,
							'error' => isset( $lcf_errors[ $lcf_key ] ) ? lc_form_error_text( $lcf_errors[ $lcf_key ] ) : '',
						)
					);
				}
				?>

				<?php if ( $lcf_captcha ) : ?>
					<div class="lc-form__field lc-form__field--captcha" data-lc-field="captcha">
						<span class="lc-form__label lc-form__label--hidden"><?php echo esc_html( $lcf_form['captcha'] ); ?></span>
						<div class="lc-form__captcha" data-sitekey="<?php echo esc_attr( $lcf_captcha['site'] ); ?>"></div>
						<noscript><p class="lc-form__desc"><?php esc_html_e( 'Please enable JavaScript to complete the anti-spam check.', 'learncardano' ); ?></p></noscript>
						<p class="lc-form__error" id="<?php echo esc_attr( $lcf_uid . '-captcha-error' ); ?>"<?php echo 'captcha' === ( $lcf_status['msg'] ?? '' ) ? '' : ' hidden'; ?>><?php echo esc_html( lc_form_error_text( 'captcha' ) ); ?></p>
					</div>
				<?php endif; ?>
			</div>

			<?php // Honeypot: hidden from people and screen readers, bots fill it in. ?>
			<div class="lc-form__hp" aria-hidden="true">
				<label for="<?php echo esc_attr( $lcf_uid . '-hp' ); ?>"><?php esc_html_e( 'Leave this field empty', 'learncardano' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $lcf_uid . '-hp' ); ?>" name="lc_hp" value="" tabindex="-1" autocomplete="off">
			</div>
			<input type="hidden" name="action" value="<?php echo esc_attr( LC_FORM_ACTION ); ?>">
			<input type="hidden" name="lc_form" value="<?php echo esc_attr( $lcf_id ); ?>">
			<input type="hidden" name="lc_source" value="<?php echo esc_attr( (string) $lcf_source ); ?>">
			<input type="hidden" name="lc_ts" value="<?php echo esc_attr( lc_form_sign_time( $lcf_id ) ); ?>">
			<input type="hidden" name="_lc_nonce" value="<?php echo esc_attr( wp_create_nonce( lc_form_nonce_action( $lcf_id ) ) ); ?>">
			<?php wp_referer_field(); ?>

			<div class="lc-form__footer">
				<button type="submit" class="lc-form__submit"><?php echo esc_html( $lcf_form['button'] ); ?></button>
			</div>
		</form>
	<?php endif; ?>
</div>
