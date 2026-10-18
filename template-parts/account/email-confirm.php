<?php
/**
 * /account/email-link/ : the "Continue" page. Showing it uses nothing up;
 * only the button (a POST bound to this browser) consumes the link.
 *
 * @package CardanoPress Bootstrap
 */

$lc_data = lc_auth_view()['data'];
?>
<h1 class="entry-title"><?php esc_html_e( 'Continue signing in', 'learncardano' ); ?></h1>
<?php if ( 'ready' === $lc_data['state'] ) : ?>
	<form method="post" action="<?php echo esc_url( lc_auth_account_url( 'email-link/' ) ); ?>" class="lc-auth__form">
		<input type="hidden" name="c" value="<?php echo esc_attr( $lc_data['c'] ); ?>">
		<p class="lc-auth__lead"><?php echo esc_html( $lc_data['label'] ); ?> <strong><?php echo esc_html( $lc_data['masked'] ); ?></strong></p>
		<?php if ( '' !== $lc_data['other'] ) : ?>
			<p><?php echo esc_html( sprintf( /* translators: %s: member name */ __( 'You are signed in as %s. Continuing switches accounts.', 'learncardano' ), $lc_data['other'] ) ); ?></p>
		<?php endif; ?>
		<button type="submit" class="lc-form__submit lc-auth__btn"><?php esc_html_e( 'Continue', 'learncardano' ); ?></button>
	</form>
<?php else : ?>
	<p class="lc-auth__notice lc-auth__notice--error" role="status"><?php echo esc_html( lc_auth_text( 'link_expired' ) ); ?></p>
	<?php echo lc_auth_render_panel( array( 'context' => 'page' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in template-parts/auth-panel.php. ?>
<?php endif; ?>
