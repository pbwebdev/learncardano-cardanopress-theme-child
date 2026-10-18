<?php
/**
 * The sign-in panel, shared by the /account/ page and the modal
 * (lc_auth_render_panel()). Works without JS: the email form posts to
 * /account/; assets/js/auth.js reveals the wallet button and submits by fetch.
 *
 * Variables from lc_auth_render_panel(): $lc_panel (title, context, redirect, token).
 *
 * @package CardanoPress Bootstrap
 */

$lc_redirect = (string) $lc_panel['redirect'];
$lc_privacy  = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
?>
<div class="lc-auth__panel" data-lc-auth-panel data-context="<?php echo esc_attr( $lc_panel['context'] ); ?>" data-redirect="<?php echo esc_url( $lc_redirect ); ?>">
	<h2 class="lc-auth__title" id="lc-auth-title"><?php echo esc_html( $lc_panel['title'] ); ?></h2>
	<?php if ( lc_auth_enabled() ) : ?>
		<p class="lc-auth__lead"><?php esc_html_e( 'New here? Signing in creates your free account.', 'learncardano' ); ?></p>
		<div class="lc-auth__status" role="status" aria-live="polite" tabindex="-1" data-lc-auth-status></div>

		<div class="lc-auth__wallet" data-lc-auth-wallet-wrap hidden>
			<button type="button" class="lc-form__submit lc-auth__btn" data-lc-auth-wallet><?php esc_html_e( 'Continue with a Cardano wallet', 'learncardano' ); ?></button>
			<ul class="lc-auth__wallets" data-lc-auth-wallets hidden></ul>
			<p class="lc-auth__or"><span><?php esc_html_e( 'or', 'learncardano' ); ?></span></p>
		</div>

		<form method="post" action="<?php echo esc_url( lc_auth_account_url() ); ?>" class="lc-form lc-auth__form" data-lc-auth-email>
			<input type="hidden" name="lc_action" value="email_link">
			<input type="hidden" name="lc_token" value="<?php echo esc_attr( $lc_panel['token'] ); ?>">
			<input type="hidden" name="redirect_to" value="<?php echo esc_url( $lc_redirect ); ?>">
			<div class="lc-form__hp" aria-hidden="true"><label>Leave this empty <input type="text" name="lc_hp" value="" tabindex="-1" autocomplete="off"></label></div>
			<label class="lc-form__label" for="lc-auth-email"><?php esc_html_e( 'Email me a sign-in link', 'learncardano' ); ?></label>
			<input class="lc-form__input" type="email" id="lc-auth-email" name="email" required autocomplete="email" inputmode="email" placeholder="you@example.com">
			<button type="submit" class="lc-form__submit lc-auth__btn"><?php esc_html_e( 'Email me a link', 'learncardano' ); ?></button>
		</form>
	<?php else : ?>
		<p><?php echo esc_html( lc_auth_text( 'disabled' ) ); ?></p>
	<?php endif; ?>
	<p class="lc-auth__fine">
		<?php esc_html_e( 'Have a password?', 'learncardano' ); ?>
		<a href="<?php echo esc_url( wp_login_url( $lc_redirect ) ); ?>" rel="nofollow"><?php esc_html_e( 'Sign in with password', 'learncardano' ); ?></a>
		<?php if ( $lc_privacy ) : ?>
			&middot; <a href="<?php echo esc_url( $lc_privacy ); ?>"><?php esc_html_e( 'Privacy', 'learncardano' ); ?></a>
		<?php endif; ?>
	</p>
</div>
