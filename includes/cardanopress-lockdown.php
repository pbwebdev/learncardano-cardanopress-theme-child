<?php
/**
 * CardanoPress lockdown (2026-10-18).
 *
 * CardanoPress's wallet sign-in verifies a signature from the wallet's
 * *payment* key but takes the account from the *stake* part of an address
 * the browser supplies, so anyone who knows a member's (public, on-chain)
 * stake address could sign in as them. Until the theme's own wallet sign-in
 * replaces it, every CardanoPress AJAX action is refused, and the accounts
 * CardanoPress created can't be reached by password or password reset
 * either (an attacker could have set an email or password on one).
 *
 * Keep this file until the plugin's backup copy is deleted: it does nothing
 * without the plugin, and protects a plugin rollback.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * Whether an account was created by CardanoPress wallet sign-up: its login
 * is md5(stake address), 32 lowercase hex characters.
 *
 * @param WP_User|int|null $user User or ID.
 * @return bool
 */
function lc_is_legacy_wallet_account( $user ) {
	if ( is_numeric( $user ) ) {
		$user = get_userdata( (int) $user );
	}
	return $user instanceof WP_User && 1 === preg_match( '/^[0-9a-f]{32}$/', (string) $user->user_login );
}

/**
 * Refuse every CardanoPress AJAX action. admin-ajax.php fires admin_init
 * before it dispatches wp_ajax_* actions, so this runs first.
 * cardanopress_user_change is left alone: it can only log the current user out.
 */
function lc_cardanopress_ajax_gate() {
	if ( ! wp_doing_ajax() || ! isset( $_REQUEST['action'] ) || ! is_string( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
		return;
	}
	$action = (string) $_REQUEST['action']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput -- compared against a fixed prefix; hook names are case-sensitive.
	if ( 0 !== strpos( $action, 'cardanopress_' ) || 'cardanopress_user_change' === $action ) {
		return;
	}
	nocache_headers();
	wp_send_json_error( __( 'Wallet sign-in is paused while we upgrade it. Please check back soon.', 'learncardano' ), 403 );
}
add_action( 'admin_init', 'lc_cardanopress_ajax_gate', PHP_INT_MIN );

/**
 * Defence in depth: unhook the sign-in, sign-up and wallet-link handlers too.
 */
add_action(
	'init',
	function () {
		foreach ( array( 'login_challenge', 'user_account', 'reconnect_account', 'sync_assets', 'save_handle' ) as $action ) {
			remove_all_actions( 'wp_ajax_cardanopress_' . $action );
			remove_all_actions( 'wp_ajax_nopriv_cardanopress_' . $action );
		}
	},
	PHP_INT_MAX
);

/**
 * No password reset for wallet-created accounts: any email on one may have
 * been set by someone who used the CardanoPress flaw. These members sign in
 * with their wallet again once the theme's wallet sign-in ships.
 */
add_filter(
	'allow_password_reset',
	function ( $allow, $user_id ) {
		return lc_is_legacy_wallet_account( (int) $user_id ) ? false : $allow;
	},
	10,
	2
);

/**
 * No password sign-in for wallet-created accounts either.
 */
add_filter(
	'wp_authenticate_user',
	function ( $user ) {
		if ( $user instanceof WP_User && lc_is_legacy_wallet_account( $user ) ) {
			return new WP_Error( 'lc_wallet_account', __( 'This account signs in with a Cardano wallet. Wallet sign-in is being upgraded and will be back soon.', 'learncardano' ) );
		}
		return $user;
	}
);
