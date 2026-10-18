<?php
/**
 * /account/ for visitors who are signed out: the sign-in panel, no JS needed.
 *
 * @package CardanoPress Bootstrap
 */

$lc_notice = lc_auth_notice();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only; validated by lc_auth_safe_redirect().
$lc_back = lc_auth_safe_redirect( isset( $_GET['redirect_to'] ) && is_string( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : '' );
$lc_has_back = lc_auth_account_url() !== $lc_back;
?>
<h1 class="entry-title"><?php esc_html_e( 'Sign in', 'learncardano' ); ?></h1>
<?php if ( $lc_notice ) : ?>
	<p class="lc-auth__notice lc-auth__notice--<?php echo esc_attr( $lc_notice[0] ); ?>" role="status"><?php echo esc_html( $lc_notice[1] ); ?></p>
<?php endif; ?>
<?php echo lc_auth_render_panel( array( 'context' => 'page', 'redirect' => $lc_back ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in template-parts/auth-panel.php. ?>
<?php if ( $lc_has_back ) : ?>
	<p class="lc-auth__fine"><a href="<?php echo esc_url( $lc_back ); ?>"><?php esc_html_e( 'Back to where you were', 'learncardano' ); ?></a></p>
<?php endif; ?>
