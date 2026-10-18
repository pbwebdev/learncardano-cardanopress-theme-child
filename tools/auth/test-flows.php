<?php
/**
 * Flow tests for the account system (R2). Run on STAGING only:
 *
 *   wp --path=$HOME/www/staging2.learncardano.io/public_html eval-file ~/lc-tools/auth/test-flows.php
 *
 * No HTTP: it simulates cookies/Origin and calls the internal functions the
 * /account/* handlers call. Wallet signatures are real (sodium builds a
 * COSE_Sign1 and COSE_Key the way a CIP-30 wallet would, like test-cose.php).
 *
 * Every user it creates has an @lc-test.invalid address and meta lc_test=1, and
 * is removed in `finally`, with the test transients and the dev outbox.
 * Refuses to run on production.
 *
 * @package CardanoPress Bootstrap
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}
if ( 'production' === wp_get_environment_type() || ! function_exists( 'lc_auth_dev_outbox_latest' ) ) {
	WP_CLI::error( 'Staging only (needs the non-production dev outbox).' );
}
lc_auth_load( 'email' );
lc_auth_load( 'wallet' );
require_once ABSPATH . 'wp-admin/includes/user.php';
add_filter( 'send_auth_cookies', '__return_false' );

$GLOBALS['lc_t'] = array( 'pass' => 0, 'fail' => 0, 'users' => array(), 'comments' => array() );

function t( $name, $ok, $detail = '' ) {
	$ok ? ++$GLOBALS['lc_t']['pass'] : ++$GLOBALS['lc_t']['fail'];
	echo ( $ok ? 'PASS' : 'FAIL' ) . '  ' . $name . ( $ok || '' === $detail ? '' : "  ($detail)" ) . "\n";
}

function t_code( $res ) {
	return is_wp_error( $res ) ? $res->get_error_code() : ( is_array( $res ) ? 'ok' : (string) $res );
}

/* ---- environment helpers ------------------------------------------------ */

function t_origin() {
	$h = wp_parse_url( home_url() );
	$_SERVER['HTTP_ORIGIN'] = $h['scheme'] . '://' . $h['host'] . ( isset( $h['port'] ) ? ':' . $h['port'] : '' );
}

/** A fresh browser: new flow cookie, nobody signed in. Also resets rate limits unless $reset_limits is false. */
function t_browser( $reset_limits = true ) {
	global $wpdb;
	if ( $reset_limits ) {
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_lc\_rl\_%' OR option_name LIKE '\_transient\_timeout\_lc\_rl\_%'" );
		wp_cache_flush(); // the rows were deleted with SQL: drop the request's cached copies too.
	}
	$_COOKIE[ lc_auth_cookie_name( 'lcauth' ) ] = lc_auth_new_token();
	unset( $_COOKIE[ lc_auth_cookie_name( 'lcml' ) ] );
	unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	wp_set_current_user( 0 );
}

/** Sign a user in "in the browser": real session token, logged_in cookie. */
function t_session( $user_id ) {
	$token                    = WP_Session_Tokens::get_instance( $user_id )->create( time() + DAY_IN_SECONDS );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user_id, time() + DAY_IN_SECONDS, 'logged_in', $token );
	wp_set_current_user( $user_id );
}

/** Like t_session(), but the sign-in is $age seconds old (for the "recent sign-in" rules). */
function t_session_aged( $user_id, $age ) {
	$mgr     = WP_Session_Tokens::get_instance( $user_id );
	$token   = $mgr->create( time() + DAY_IN_SECONDS );
	$session = $mgr->get( $token );
	$session['login'] = time() - (int) $age;
	$mgr->update( $token, $session );
	$_COOKIE[ LOGGED_IN_COOKIE ] = wp_generate_auth_cookie( $user_id, time() + DAY_IN_SECONDS, 'logged_in', $token );
	wp_set_current_user( $user_id );
	return $token;
}

function t_user( $role = 'subscriber', $with_email = true, $login = null ) {
	$n  = bin2hex( random_bytes( 5 ) );
	$id = wp_insert_user(
		array(
			'user_login'   => $login ?? 'lctest_' . $n,
			'user_pass'    => wp_generate_password( 32 ),
			'user_email'   => $with_email ? "t{$n}@lc-test.invalid" : '',
			'role'         => $role,
			'display_name' => 'Test ' . $n,
		)
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( 'could not create test user: ' . $id->get_error_message() );
	}
	update_user_meta( $id, 'lc_test', 1 );
	$GLOBALS['lc_t']['users'][] = $id;
	return get_userdata( $id );
}

function t_wallet() {
	$sk = sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() );
	$pk = sodium_crypto_sign_publickey_from_secretkey( $sk );
	$r  = "\xE1" . sodium_crypto_generichash( $pk, '', 28 );
	return array( 'sk' => $sk, 'pk' => $pk, 'reward' => $r, 'stake' => lc_cardano_stake_bech32( $r ), 'hex' => bin2hex( $r ) );
}

function t_c_head( $major, $n ) {
	if ( $n < 24 ) {
		return chr( ( $major << 5 ) | $n );
	}
	return $n < 256 ? chr( ( $major << 5 ) | 24 ) . chr( $n ) : chr( ( $major << 5 ) | 25 ) . pack( 'n', $n );
}

/** COSE_Sign1 + COSE_Key as a wallet returns them. $header_reward lets a test put another address in the header. */
function t_sign( array $w, $message, $header_reward = null ) {
	$prot = "\xa2\x01\x27" . t_c_head( 3, 7 ) . 'address' . lc_cbor_bstr( $header_reward ?? $w['reward'] );
	$sig  = sodium_crypto_sign_detached( "\x84\x6aSignature1" . lc_cbor_bstr( $prot ) . "\x40" . lc_cbor_bstr( $message ), $w['sk'] );
	$cose = "\x84" . lc_cbor_bstr( $prot ) . "\xa1" . t_c_head( 3, 6 ) . 'hashed' . "\xf4" . lc_cbor_bstr( $message ) . lc_cbor_bstr( $sig );
	$key  = "\xa4\x01\x01\x03\x27\x20\x06\x21" . lc_cbor_bstr( $w['pk'] );
	return array( bin2hex( $cose ), bin2hex( $key ) );
}

function t_challenge( array $w, $intent = 'signin', $nonce = '' ) {
	return lc_auth_wallet_challenge( array( 'reward' => $w['hex'], 'intent' => $intent, 'nonce' => $nonce ) );
}

/** Full wallet sign-in as a browser would do it. */
function t_wallet_signin( array $w, $redirect = '' ) {
	$ch = t_challenge( $w );
	if ( is_wp_error( $ch ) ) {
		return $ch;
	}
	list( $sig, $key ) = t_sign( $w, $ch['message'] );
	return lc_auth_wallet_verify( array( 'nonce' => $ch['nonce'], 'signature' => $sig, 'key' => $key, 'redirect_to' => $redirect ) );
}

/** Full wallet link for the signed-in user. */
function t_wallet_link( array $w ) {
	$ch = t_challenge( $w, 'link', wp_create_nonce( 'lc_wallet_link' ) );
	if ( is_wp_error( $ch ) ) {
		return $ch;
	}
	list( $sig, $key ) = t_sign( $w, $ch['message'] );
	return lc_auth_wallet_verify( array( 'nonce' => $ch['nonce'], 'signature' => $sig, 'key' => $key ) );
}

function t_request_link( $email, array $over = array() ) {
	return lc_auth_email_request( $over + array( 'email' => $email, 'lc_hp' => '', 'lc_token' => lc_auth_panel_token( time() - 10 ), 'redirect_to' => '' ) );
}

function t_outbox_count( $email ) {
	$n = 0;
	foreach ( (array) get_option( LC_AUTH_OUTBOX_OPTION, array() ) as $m ) {
		foreach ( $m['to'] as $a ) {
			$n += strtolower( trim( $a ) ) === strtolower( $email ) ? 1 : 0;
		}
	}
	return $n;
}

/** The link token from the newest mail to an address. */
function t_link_token( $email ) {
	$url = lc_auth_dev_outbox_link( $email );
	return $url && preg_match( '/t=([A-Za-z0-9_-]{43})$/', $url, $m ) ? $m[1] : '';
}

/** Confirm a link the way the page does: token in cookie, c from the form. */
function t_confirm( $token, $c = null ) {
	$flow = lc_auth_flow( false );
	return lc_auth_email_confirm( $token, null === $c ? lc_auth_confirm_binding( $token, $flow ) : $c );
}

/** Count live link records (lc_ml_ transients), to prove a skipped mail made no token. */
function t_live_tokens() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_lc\_ml\_%'" );
}

/** A lc_auth_mail_limits filter with some values overridden; pass the result to remove_filter() when done. */
function t_limits( array $over ) {
	$fn = function ( $d ) use ( $over ) {
		return array_merge( $d, $over );
	};
	add_filter( 'lc_auth_mail_limits', $fn );
	return $fn;
}

try {
	t_origin();
	$report = static function ( $title ) {
		echo "\n== $title\n";
	};

	/* ======================================================== primitives == */
	$report( 'Primitives' );
	t_browser();
	$tok = lc_auth_store_put( 'tt', array( 'a' => 1 ), 60 );
	t( 'store: get returns the record', 1 === (int) ( lc_auth_store_get( 'tt', $tok )['a'] ?? 0 ) );
	t( 'store: take once', null !== lc_auth_store_take( 'tt', $tok ) && null === lc_auth_store_take( 'tt', $tok ) );
	$tok = lc_auth_store_put( 'tt', array( 'a' => 1 ), 60 );
	t( 'store: wrong kind cannot read it', null === lc_auth_store_get( 'xx', $tok ) );
	$rec      = get_transient( lc_auth_store_key( 'tt', $tok ) );
	$rec['x'] = time() - 5;
	set_transient( lc_auth_store_key( 'tt', $tok ), $rec, 60 );
	t( 'store: expired record is gone', null === lc_auth_store_get( 'tt', $tok ) );
	delete_transient( lc_auth_store_key( 'tt', $tok ) );

	t( 'rate: allowed then blocked at the limit', 0 === lc_auth_rate( 'tt', 'x', 2, 60 ) && 0 === lc_auth_rate( 'tt', 'x', 2, 60 ) && lc_auth_rate( 'tt', 'x', 2, 60 ) > 0 );
	t( 'rate: other subject unaffected', 0 === lc_auth_rate( 'tt', 'y', 2, 60 ) );
	$got = lc_auth_lock( 'tt', fn() => lc_auth_lock( 'tt', fn() => 'inner' ) );
	t( 'lock: not re-entrant (inner is busy)', is_wp_error( $got ) && 'lc_auth_busy' === $got->get_error_code() );
	t( 'lock: runs and releases', 'ran' === lc_auth_lock( 'tt', fn() => 'ran' ) && 'again' === lc_auth_lock( 'tt', fn() => 'again' ) );

	$home = home_url();
	t( 'origin: same origin accepted', lc_auth_same_origin() );
	$_SERVER['HTTP_ORIGIN'] = 'https://evil.test';
	t( 'origin: foreign refused', ! lc_auth_same_origin() );
	$_SERVER['HTTP_ORIGIN'] = 'null';
	t( 'origin: "null" refused', ! lc_auth_same_origin() );
	unset( $_SERVER['HTTP_ORIGIN'] );
	t( 'origin: none and no Sec-Fetch-Site refused', ! lc_auth_same_origin() );
	$_SERVER['HTTP_SEC_FETCH_SITE'] = 'same-origin';
	t( 'origin: Sec-Fetch-Site same-origin accepted', lc_auth_same_origin() );
	$_SERVER['HTTP_SEC_FETCH_SITE'] = 'cross-site';
	t( 'origin: Sec-Fetch-Site cross-site refused', ! lc_auth_same_origin() );
	unset( $_SERVER['HTTP_SEC_FETCH_SITE'] );
	t_origin();

	t( 'redirect: external host falls back', lc_auth_account_url() === lc_auth_safe_redirect( 'https://evil.test/x' ) );
	t( 'redirect: same site kept', $home . '/post/' === lc_auth_safe_redirect( $home . '/post/' ) );
	t( 'redirect: own flow pages refused', lc_auth_account_url() === lc_auth_safe_redirect( $home . '/account/email-link/' ) && lc_auth_account_url() === lc_auth_safe_redirect( $home . '/account/wallet/verify' ) );
	t( 'redirect: token params stripped', false === strpos( lc_auth_safe_redirect( $home . '/post/?t=abc&x=1' ), 't=abc' ) );
	t( 'redirect: javascript: refused', lc_auth_account_url() === lc_auth_safe_redirect( 'javascript:alert(1)' ) );

	/* ============================================================ policy == */
	$report( 'Policy' );
	$sub     = t_user();
	$editor  = t_user( 'editor' );
	$admin   = t_user( 'administrator' );
	$admin->add_role( 'student' );
	$student = t_user( 'student' );
	$author  = t_user( 'author' );
	t( 'privileged: subscriber no, student no', ! lc_auth_is_privileged( $sub ) && ! lc_auth_is_privileged( $student ) );
	t( 'privileged: editor, author, admin+student yes', lc_auth_is_privileged( $editor ) && lc_auth_is_privileged( $author ) && lc_auth_is_privileged( get_userdata( $admin->ID ) ) );
	t( 'app passwords: staff only', wp_is_application_passwords_available_for_user( $editor ) && ! wp_is_application_passwords_available_for_user( $sub ) );
	$res = lc_auth_complete_signin( $editor, 'email' );
	t( 'complete_signin refuses staff', is_wp_error( $res ) && 'lc_auth_password_only' === $res->get_error_code() && 0 === get_current_user_id() );
	t_browser();

	/* ============================================================ wallet == */
	$report( 'Wallet: challenge' );
	t_browser();
	$w  = t_wallet();
	$ch = t_challenge( $w );
	t( 'challenge ok', is_array( $ch ) && 32 === strlen( $ch['nonce'] ), t_code( $ch ) );
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$ok   = is_array( $ch ) && 1 === preg_match(
		'/^' . preg_quote( $host, '/' ) . ' wants you to sign in with your Cardano wallet\.\n\nStake address: ' . $w['stake'] . '\nNetwork: Mainnet\nURI: ' . preg_quote( home_url(), '/' ) . '\nPurpose: Sign in\nNonce: [0-9a-f]{32}\nIssued At: \d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ\nExpiration Time: \d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ\n\nThis only proves you control this wallet\. It is not a transaction and costs nothing\.$/',
		$ch['message']
	);
	t( 'challenge message has the exact format', $ok );
	$testnet = lc_auth_wallet_challenge( array( 'reward' => 'e0' . substr( $w['hex'], 2 ), 'intent' => 'signin' ) );
	t( 'testnet reward refused', 'wallet_network' === t_code( $testnet ) );
	$script = lc_auth_wallet_challenge( array( 'reward' => 'f1' . substr( $w['hex'], 2 ), 'intent' => 'signin' ) );
	t( 'script credential refused', 'wallet_address' === t_code( $script ) );
	t( 'garbage address refused', 'wallet_address' === t_code( lc_auth_wallet_challenge( array( 'reward' => 'zzz', 'intent' => 'signin' ) ) ) );
	t( 'bad intent refused', 'wallet_failed' === t_code( lc_auth_wallet_challenge( array( 'reward' => $w['hex'], 'intent' => 'x' ) ) ) );
	t( 'link intent needs a signed-in user', 'forbidden' === t_code( lc_auth_wallet_challenge( array( 'reward' => $w['hex'], 'intent' => 'link' ) ) ) );
	t( 'bech32 reward accepted', is_array( lc_auth_wallet_challenge( array( 'reward' => $w['stake'], 'intent' => 'signin' ) ) ) );

	$report( 'Wallet: sign-in' );
	t_browser();
	$w   = t_wallet();
	$res = t_wallet_signin( $w, $home . '/some-post/' );
	t( 'new wallet creates a member and signs in', is_array( $res ) && ! empty( $res['new_account'] ) && get_current_user_id() > 0, t_code( $res ) );
	$member = get_userdata( get_current_user_id() );
	if ( $member ) {
		$GLOBALS['lc_t']['users'][] = $member->ID;
		t( 'member: random login, subscriber, admin bar off, wallet linked', 0 === strpos( $member->user_login, 'm_' ) && array( 'subscriber' ) === $member->roles && 'false' === get_user_meta( $member->ID, 'show_admin_bar_front', true ) && array( $w['stake'] ) === lc_auth_wallets( $member->ID ) );
		t( 'redirect_to honoured', $home . '/some-post/' === ( $res['redirect'] ?? '' ) );
		t( 'wallet-only member is nudged to add an email', ! empty( $res['nudge_email'] ) );
		t( 'wallet-only: one method, cannot remove it', 1 === count( lc_auth_methods( $member ) ) && ! lc_auth_can_remove( $member, 'wallet:' . $w['stake'] ) );
	}
	$first_id = get_current_user_id();
	t_browser();
	$res = t_wallet_signin( $w );
	t( 'second sign-in lands on the same user', is_array( $res ) && empty( $res['new_account'] ) && get_current_user_id() === $first_id, t_code( $res ) );
	t( 'no duplicate user', 1 === count( lc_auth_wallet_owners( $w['stake'] ) ) );

	$report( 'Wallet: challenge handling' );
	t_browser();
	$w  = t_wallet();
	$ch = t_challenge( $w );
	list( $sig, $key ) = t_sign( $w, $ch['message'] );
	$in = array( 'nonce' => $ch['nonce'], 'signature' => $sig, 'key' => $key );
	t( 'verify succeeds once', is_array( lc_auth_wallet_verify( $in ) ) );
	t_browser();
	$again = lc_auth_wallet_verify( $in );
	t( 'nonce reuse gives 410', 'wallet_expired' === t_code( $again ) && 410 === ( $again->get_error_data()['status'] ?? 0 ) );

	t_browser();
	$ch  = t_challenge( $w );
	$rec = get_transient( lc_auth_store_key( 'wc', $ch['nonce'] ) );
	$rec['x'] = time() - 1;
	set_transient( lc_auth_store_key( 'wc', $ch['nonce'] ), $rec, 60 );
	list( $sig, $key ) = t_sign( $w, $ch['message'] );
	t( 'expired challenge gives 410', 'wallet_expired' === t_code( lc_auth_wallet_verify( array( 'nonce' => $ch['nonce'], 'signature' => $sig, 'key' => $key ) ) ) );

	t_browser();
	$ch = t_challenge( $w );
	list( $sig, $key ) = t_sign( $w, $ch['message'] );
	$_COOKIE[ lc_auth_cookie_name( 'lcauth' ) ] = lc_auth_new_token(); // Another browser.
	$res = lc_auth_wallet_verify( array( 'nonce' => $ch['nonce'], 'signature' => $sig, 'key' => $key ) );
	t( 'wrong flow cookie refused, generic 400', 'wallet_failed' === t_code( $res ) && 0 === get_current_user_id() );

	t_browser();
	$ch = t_challenge( $w );
	list( $sig, $key ) = t_sign( $w, $ch['message'] . 'x' );
	t( 'signature over a different message refused', 'wallet_failed' === t_code( lc_auth_wallet_verify( array( 'nonce' => $ch['nonce'], 'signature' => $sig, 'key' => $key ) ) ) );

	// The CardanoPress flaw: attacker's own key, victim's address in the header.
	t_browser();
	$victim   = t_wallet();
	$attacker = t_wallet();
	$vuser    = t_user( 'subscriber', false );
	add_user_meta( $vuser->ID, 'lc_wallet', $victim['stake'] );
	$ch = t_challenge( $victim );
	list( $sig, $key ) = t_sign( $attacker, $ch['message'], $victim['reward'] );
	$res = lc_auth_wallet_verify( array( 'nonce' => $ch['nonce'], 'signature' => $sig, 'key' => $key ) );
	t( 'FLAW: attacker key + victim address refused, not signed in', 'wallet_failed' === t_code( $res ) && 0 === get_current_user_id() );
	t_browser();
	$ch = t_challenge( $victim );
	$base = "\x01" . sodium_crypto_generichash( $attacker['pk'], '', 28 ) . substr( $victim['reward'], 1 );
	list( $sig, $key ) = t_sign( $attacker, $ch['message'], $base );
	$res = lc_auth_wallet_verify( array( 'nonce' => $ch['nonce'], 'signature' => $sig, 'key' => $key ) );
	t( 'FLAW: forged base address refused', 'wallet_failed' === t_code( $res ) && 0 === get_current_user_id() );
	t_browser();
	$ok = t_wallet_signin( $victim );
	t( 'the real owner still signs in to that account', is_array( $ok ) && get_current_user_id() === $vuser->ID );

	$report( 'Wallet: accounts' );
	// Legacy md5 adoption.
	t_browser();
	$w      = t_wallet();
	$legacy = t_user( 'subscriber', false, md5( $w['stake'] ) );
	t( 'legacy account recognised', lc_is_legacy_wallet_account( $legacy ) );
	$res = t_wallet_signin( $w );
	t( 'legacy md5 account adopted and signed in', is_array( $res ) && get_current_user_id() === $legacy->ID && array( $w['stake'] ) === lc_auth_wallets( $legacy->ID ), t_code( $res ) );
	t( 'adoption created no second user', 1 === count( lc_auth_wallet_owners( $w['stake'] ) ) );

	// Staff refused (lc_wallet on an editor; legacy-login admin).
	t_browser();
	$w = t_wallet();
	add_user_meta( $editor->ID, 'lc_wallet', $w['stake'] );
	$res = t_wallet_signin( $w );
	t( 'privileged user with lc_wallet refused (403, not signed in)', 'password_only' === t_code( $res ) && 0 === get_current_user_id() );
	t_browser();
	$w     = t_wallet();
	$staff = t_user( 'administrator', false, md5( $w['stake'] ) );
	$res   = t_wallet_signin( $w );
	t( 'privileged md5-login account refused and not adopted', 'password_only' === t_code( $res ) && ! lc_auth_wallets( $staff->ID ) );

	// Same stake on two accounts.
	t_browser();
	$w  = t_wallet();
	$u1 = t_user();
	$u2 = t_user();
	add_user_meta( $u1->ID, 'lc_wallet', $w['stake'] );
	add_user_meta( $u2->ID, 'lc_wallet', $w['stake'] );
	$res = t_wallet_signin( $w );
	t( 'stake on two users refused as a conflict', 'wallet_conflict' === t_code( $res ) && 0 === get_current_user_id() );

	// A merged-away account is never resurrected.
	t_browser();
	$w   = t_wallet();
	$old = t_user( 'subscriber', false, md5( $w['stake'] ) );
	update_user_meta( $old->ID, 'lc_merged_into', $u1->ID );
	$res = t_wallet_signin( $w );
	t( 'merged-away legacy account is not signed into', 'wallet_failed' === t_code( $res ) && 0 === get_current_user_id() );

	// Sign-up switched off.
	t_browser();
	add_filter( 'lc_auth_signup_enabled', '__return_false' );
	$res = t_wallet_signin( t_wallet() );
	remove_filter( 'lc_auth_signup_enabled', '__return_false' );
	t( 'sign-up off: unknown wallet refused', 'signup_closed' === t_code( $res ) && 0 === get_current_user_id() );

	// Daily ceiling on new wallet-only accounts.
	t_browser();
	$wc_caught = 0;
	$wc_catch  = function ( $return, $atts ) use ( &$wc_caught ) {
		$to = is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : (string) $atts['to'];
		if ( false !== stripos( $to, (string) get_option( 'admin_email' ) ) ) {
			++$wc_caught;
			return true;
		}
		return $return;
	};
	add_filter( 'pre_wp_mail', $wc_catch, -1, 2 );
	delete_transient( 'lc_auth_wallet_signup_alerted' );
	$wc_cap = fn() => 1;
	add_filter( 'lc_auth_wallet_signup_daily_cap', $wc_cap );
	$wa  = t_wallet();
	$wb  = t_wallet();
	$res = t_wallet_signin( $wa );
	$GLOBALS['lc_t']['users'][] = get_current_user_id();
	t( 'wallet ceiling: the first new wallet is within the cap and signs up', is_array( $res ) && ! empty( $res['new_account'] ), t_code( $res ) );
	t_browser( false ); // Another visit, same day: counters stay.
	$res = t_wallet_signin( $wb );
	t( 'wallet ceiling: a second NEW wallet is refused with a friendly message', 'wallet_signup_full' === t_code( $res ) && 0 === get_current_user_id() && ! lc_auth_wallet_owners( $wb['stake'] ) );
	t( 'wallet ceiling: the admin is emailed once', 1 === $wc_caught && (bool) get_transient( 'lc_auth_wallet_signup_alerted' ) );
	t_browser( false );
	$res = t_wallet_signin( $wb );
	t( 'wallet ceiling: still refused, admin not emailed again', 'wallet_signup_full' === t_code( $res ) && 1 === $wc_caught );
	t_browser( false );
	$res = t_wallet_signin( $wa );
	t( 'wallet ceiling: an existing wallet still signs in', is_array( $res ) && empty( $res['new_account'] ) && get_current_user_id() > 0, t_code( $res ) );
	remove_filter( 'lc_auth_wallet_signup_daily_cap', $wc_cap );
	remove_filter( 'pre_wp_mail', $wc_catch, -1 );
	delete_transient( 'lc_auth_wallet_signup_alerted' );
	t_browser();

	$report( 'Wallet: link / unlink' );
	t_browser();
	$a = t_user();
	lc_auth_mark_email_verified( $a->ID, $a->user_email );
	t_session( $a->ID );
	$w1  = t_wallet();
	$res = t_wallet_link( $w1 );
	t( 'link adds the wallet', is_array( $res ) && array( $w1['stake'] ) === lc_auth_wallets( $a->ID ), t_code( $res ) );
	t( 'email + wallet: wallet can be removed', lc_auth_can_remove( get_userdata( $a->ID ), 'wallet:' . $w1['stake'] ) );
	t( 'link is idempotent', is_array( t_wallet_link( $w1 ) ) && 1 === count( lc_auth_wallets( $a->ID ) ) );
	$ch = t_challenge( $w1, 'link', 'bad-nonce' );
	t( 'link without the page nonce refused', 'reload' === t_code( $ch ) );
	t_browser();
	$other = t_user();
	t_session( $other->ID );
	$ch = t_challenge( $w1, 'link', wp_create_nonce( 'lc_wallet_link' ) );
	t_session( $a->ID ); // A different session completes it.
	list( $sig, $key ) = t_sign( $w1, $ch['message'] );
	t( 'link challenge is bound to its user/session', 'wallet_failed' === t_code( lc_auth_wallet_verify( array( 'nonce' => $ch['nonce'], 'signature' => $sig, 'key' => $key ) ) ) );
	t_browser();
	t_session( $editor->ID );
	t( 'staff cannot link a wallet', 'password_only' === t_code( t_challenge( t_wallet(), 'link', wp_create_nonce( 'lc_wallet_link' ) ) ) );

	// Last-method guard: unverified email does not count.
	$wo = t_user();
	add_user_meta( $wo->ID, 'lc_wallet', $w1['stake'] . 'x' );
	t( 'unverified email is not a sign-in method', array( 'wallet:' . $w1['stake'] . 'x' ) === lc_auth_methods( $wo ) && ! lc_auth_can_remove( $wo, 'wallet:' . $w1['stake'] . 'x' ) );
	lc_auth_mark_email_verified( $wo->ID, $wo->user_email );
	t( 'verified email counts, wallet then removable', lc_auth_can_remove( get_userdata( $wo->ID ), 'wallet:' . $w1['stake'] . 'x' ) );
	wp_update_user( array( 'ID' => $wo->ID, 'user_email' => 'changed' . $wo->ID . '@lc-test.invalid' ) );
	t( 'email changed another way counts as unverified', ! lc_auth_email_verified( $wo->ID ) );

	// 409 and claim.
	t_browser();
	$w2    = t_wallet();
	$owner = t_user();
	lc_auth_mark_email_verified( $owner->ID, $owner->user_email );
	add_user_meta( $owner->ID, 'lc_wallet', $w2['stake'] );
	t_session( $a->ID );
	t( 'wallet on another account: 409', 'wallet_in_use' === t_code( t_wallet_link( $w2 ) ) );

	t_browser();
	$w3  = t_wallet();
	$old = t_user( 'subscriber', false, md5( $w3['stake'] ) );
	$cid = wp_insert_comment( array( 'comment_post_ID' => (int) get_option( 'page_on_front' ) ?: 1, 'comment_content' => 'lc-test', 'user_id' => $old->ID, 'comment_author' => md5( $w3['stake'] ), 'comment_approved' => 1 ) );
	$GLOBALS['lc_t']['comments'][] = $cid;
	update_user_meta( $old->ID, 'cardanopress_payment_address', 'addr1' . bin2hex( random_bytes( 8 ) ) );
	update_user_meta( $old->ID, 'cardanopress_assets', array( 'policy' => 1 ) );
	update_user_meta( $old->ID, 'cardanopress_handles', array( 'x' ) );
	update_user_meta( $old->ID, 'cardanopress_account_info', array( 'a' => 1 ) );
	$b = t_user();
	t_session( $b->ID );
	$res = t_wallet_link( $w3 );
	t( 'claim: unadopted legacy account without email hands over its wallet', is_array( $res ) && array( $w3['stake'] ) === lc_auth_wallets( $b->ID ), t_code( $res ) );
	t( 'claim: comments reassigned', (int) get_comment( $cid )->user_id === $b->ID );
	t( 'claim: old account marked merged, no roles, wallet gone', (int) get_user_meta( $old->ID, 'lc_merged_into', true ) === $b->ID && ! get_userdata( $old->ID )->roles && ! lc_auth_wallets( $old->ID ) );
	global $wpdb;
	$cp_left = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s", $old->ID, $wpdb->esc_like( 'cardanopress_' ) . '%' ) );
	t( "claim: the old account's cardanopress_* meta is dropped, the merge record stays", 0 === $cp_left && (int) get_user_meta( $old->ID, 'lc_merged_into', true ) === $b->ID );
	t_browser();
	$res = t_wallet_signin( $w3 );
	t( 'claim: wallet now signs in to the new owner', is_array( $res ) && get_current_user_id() === $b->ID );

	t_browser();
	$w4  = t_wallet();
	$old = t_user( 'subscriber', true, md5( $w4['stake'] ) );
	lc_auth_mark_email_verified( $old->ID, $old->user_email );
	t_session( $b->ID );
	t( 'no claim when the old account has a verified email', 'wallet_in_use' === t_code( t_wallet_link( $w4 ) ) );
	t_browser();
	$w5  = t_wallet();
	$old = t_user( 'subscriber', false, md5( $w5['stake'] ) );
	add_user_meta( $old->ID, 'lc_wallet', $w5['stake'] );
	$w5b = t_wallet();
	add_user_meta( $old->ID, 'lc_wallet', $w5b['stake'] );
	t_session( $b->ID );
	t( 'no claim when the old account has another wallet', 'wallet_in_use' === t_code( t_wallet_link( $w5 ) ) );

	/* ====================================================== magic link == */
	$report( 'Email link: requests' );
	t_browser();
	$member = t_user();
	lc_auth_mark_email_verified( $member->ID, $member->user_email );
	$new_addr = 'new' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	lc_auth_dev_outbox_clear();
	$r_exist = t_request_link( $member->user_email );
	$r_new   = t_request_link( $new_addr );
	$r_staff = t_request_link( $editor->user_email );
	t( 'responses identical: existing, new, staff', $r_exist === $r_new && $r_new === $r_staff && true === $r_exist['ok'] && 'email_sent' === $r_exist['code'] );
	t( 'existing and new addresses get a link', '' !== t_link_token( $member->user_email ) && '' !== t_link_token( $new_addr ) );
	$staff_mail = lc_auth_dev_outbox_latest( $editor->user_email );
	t( 'staff address gets a "use your password" mail with NO link', $staff_mail && false === strpos( $staff_mail['message'], '/account/email-link/' ) && false !== strpos( $staff_mail['message'], 'password' ) );

	$legacy_mail = t_user( 'subscriber', true, md5( 'legacy-mail-' . bin2hex( random_bytes( 4 ) ) ) );
	t_request_link( $legacy_mail->user_email );
	$m = lc_auth_dev_outbox_latest( $legacy_mail->user_email );
	t( 'legacy wallet account with unverified email gets "wallet first", NO link', $m && false === strpos( $m['message'], '/account/email-link/' ) && false !== stripos( $m['message'], 'wallet' ) );

	t_browser();
	lc_auth_dev_outbox_clear();
	$r = t_request_link( $member->user_email, array( 'lc_hp' => 'http://spam' ) );
	t( 'honeypot: looks successful, sends nothing', true === $r['ok'] && 0 === t_outbox_count( $member->user_email ) );
	$r = t_request_link( $member->user_email, array( 'lc_token' => lc_auth_panel_token() ) );
	t( 'panel token too young refused', 'reload' === $r['code'] && 0 === t_outbox_count( $member->user_email ) );
	$r = t_request_link( $member->user_email, array( 'lc_token' => '' ) );
	t( 'missing panel token refused', 'reload' === $r['code'] );
	$bad = lc_auth_panel_token( time() - 10 );
	$_COOKIE[ lc_auth_cookie_name( 'lcauth' ) ] = lc_auth_new_token();
	t( 'panel token from another browser refused', 'reload' === t_request_link( $member->user_email, array( 'lc_token' => $bad ) )['code'] );
	t( 'invalid email refused', 'email_bad' === t_request_link( 'nope' )['code'] );

	t_browser();
	lc_auth_dev_outbox_clear();
	$cap_addr = 'cap' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	for ( $i = 0; $i < 4; $i++ ) {
		$r = t_request_link( $cap_addr );
	}
	t( 'per-address-and-visitor cap: same answer, 4th mail from one visitor not sent', true === $r['ok'] && 3 === t_outbox_count( $cap_addr ) );
	t_browser();
	lc_auth_dev_outbox_clear();
	$tag_base = 'tag' . bin2hex( random_bytes( 4 ) );
	for ( $i = 0; $i < 4; $i++ ) {
		$r = t_request_link( $tag_base . '+v' . $i . '@lc-test.invalid' ); // One visitor, four "different" +tag addresses.
	}
	$sum = 0;
	for ( $i = 0; $i < 4; $i++ ) {
		$sum += t_outbox_count( $tag_base . '+v' . $i . '@lc-test.invalid' );
	}
	t( 'per-address-and-visitor cap sees through +tags: 4 variants get 3 mails', true === $r['ok'] && 3 === $sum, (string) $sum );
	t_browser();
	$last = null;
	for ( $i = 0; $i < 6; $i++ ) {
		$last = t_request_link( 'ip' . $i . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid' );
	}
	t( 'per-IP cap: 6th request in the window is 429', 'rate' === $last['code'] && 429 === $last['status'] && $last['retry'] > 0 );

	$report( 'Email link: confirm' );
	t_browser();
	lc_auth_dev_outbox_clear();
	$addr = 'conf' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	t_request_link( $addr, array( 'redirect_to' => $home . '/after/' ) );
	$token = t_link_token( $addr );
	t( 'link token is 43 chars', 43 === strlen( $token ) );
	t( 'token is stored hashed (raw token not in any option key)', 0 === (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT COUNT(*) FROM {$GLOBALS['wpdb']->options} WHERE option_name LIKE %s", '%' . $GLOBALS['wpdb']->esc_like( $token ) . '%' ) ) );
	t( 'confirm without c fails', 'link_failed' === t_code( t_confirm( $token, '' ) ) );
	t( 'confirm with a wrong c fails', 'link_failed' === t_code( t_confirm( $token, str_repeat( 'a', 64 ) ) ) );
	$flow_ok = $_COOKIE[ lc_auth_cookie_name( 'lcauth' ) ];
	$c       = lc_auth_confirm_binding( $token, lc_auth_flow( false ) );
	$_COOKIE[ lc_auth_cookie_name( 'lcauth' ) ] = lc_auth_new_token(); // CSRF: a different browser.
	t( 'confirm from another browser (cookie mismatch) fails', 'link_failed' === t_code( t_confirm( $token, $c ) ) );
	$_COOKIE[ lc_auth_cookie_name( 'lcauth' ) ] = $flow_ok;
	t( 'failed attempts did not use the link up', null !== lc_auth_store_get( 'ml', $token ) );
	$res = t_confirm( $token, $c );
	t( 'confirm creates the member, verified, signed in', is_array( $res ) && get_current_user_id() > 0 && $home . '/after/' === $res['redirect'], t_code( $res ) );
	$created = get_userdata( get_current_user_id() );
	if ( $created ) {
		$GLOBALS['lc_t']['users'][] = $created->ID;
		t( 'new member: email verified, random login, subscriber', lc_auth_email_verified( $created ) && 0 === strpos( $created->user_login, 'm_' ) && array( 'subscriber' ) === $created->roles );
	}
	t( 'second use of the link fails', 'link_expired' === t_code( t_confirm( $token, $c ) ) );

	t_browser();
	lc_auth_dev_outbox_clear();
	t_request_link( $member->user_email );
	$token = t_link_token( $member->user_email );
	$res   = t_confirm( $token );
	t( 'existing member signs in with the link', is_array( $res ) && get_current_user_id() === $member->ID, t_code( $res ) );

	$multi = 'multi' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	$toks  = array();
	for ( $i = 0; $i < 3; $i++ ) {
		t_browser();
		t_request_link( $multi );
		$toks[] = t_link_token( $multi );
	}
	t( 'up to 3 sign-in links stay live for one address', 3 === count( array_unique( $toks ) ) && ! array_filter( $toks, fn( $x ) => null === lc_auth_store_get( 'ml', $x ) ) );
	t_browser();
	t_request_link( $multi );
	$toks[] = t_link_token( $multi );
	t( 'a 4th link drops the oldest, keeps the other 3', null === lc_auth_store_get( 'ml', $toks[0] ) && null !== lc_auth_store_get( 'ml', $toks[1] ) && null !== lc_auth_store_get( 'ml', $toks[2] ) && null !== lc_auth_store_get( 'ml', $toks[3] ) );

	t_browser();
	lc_auth_dev_outbox_clear();
	t_request_link( $member->user_email );
	$token = t_link_token( $member->user_email );
	$rec   = get_transient( lc_auth_store_key( 'ml', $token ) );
	$rec['x'] = time() - 1;
	set_transient( lc_auth_store_key( 'ml', $token ), $rec, 60 );
	t( 'expired link fails', 'link_expired' === t_code( t_confirm( $token ) ) );

	// Defence in depth: tokens forced for staff / unverified legacy accounts still do not sign in.
	t_browser();
	$token = lc_auth_email_issue( 'signin', strtolower( $editor->user_email ), $editor->ID, '' );
	$res   = t_confirm( $token );
	t( 'staff token refused at confirm too', 'password_only' === t_code( $res ) && 0 === get_current_user_id() );
	t_browser();
	$token = lc_auth_email_issue( 'signin', strtolower( $legacy_mail->user_email ), $legacy_mail->ID, '' );
	$res   = t_confirm( $token );
	t( 'unverified legacy token refused at confirm too', 'link_failed' === t_code( $res ) && 0 === get_current_user_id() );

	// An address set another way (REST, admin) on an account that has a wallet: wallet first, never a link.
	t_browser();
	lc_auth_dev_outbox_clear();
	$hj = t_user( 'subscriber', false );
	$hj_addr = 'hj' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	wp_update_user( array( 'ID' => $hj->ID, 'user_email' => $hj_addr ) );
	add_user_meta( $hj->ID, 'lc_wallet', t_wallet()['stake'] );
	t_request_link( $hj_addr );
	$m = lc_auth_dev_outbox_latest( $hj_addr );
	t( 'unverified email on a wallet account: "wallet first" mail, NO link', '' === t_link_token( $hj_addr ) && $m && false === strpos( $m['message'], '/account/email-link/' ) );
	$token = lc_auth_email_issue( 'signin', strtolower( $hj_addr ), $hj->ID, '' );
	t( 'unverified email on a wallet account: forced token refused at confirm', 'link_failed' === t_code( t_confirm( $token ) ) && 0 === get_current_user_id() );

	// Same address, no wallet: the real owner may sign in, but every other way in is revoked first.
	t_browser();
	lc_auth_dev_outbox_clear();
	$pw = t_user();
	wp_set_password( 'attacker-pass-123', $pw->ID );
	$old_tok = WP_Session_Tokens::get_instance( $pw->ID )->create( time() + DAY_IN_SECONDS );
	t( 'pre-hijack setup: old password works, old session exists', wp_check_password( 'attacker-pass-123', get_userdata( $pw->ID )->user_pass, $pw->ID ) && null !== WP_Session_Tokens::get_instance( $pw->ID )->get( $old_tok ) );
	t_request_link( $pw->user_email );
	$token = t_link_token( $pw->user_email );
	$res   = t_confirm( $token );
	t( 'unverified address, no wallet: email link still signs the owner in', is_array( $res ) && get_current_user_id() === $pw->ID && lc_auth_email_verified( $pw->ID ), t_code( $res ) );
	t( 'sign-in revoked the old password', ! wp_check_password( 'attacker-pass-123', get_userdata( $pw->ID )->user_pass, $pw->ID ) );
	t( 'sign-in revoked the old session', null === WP_Session_Tokens::get_instance( $pw->ID )->get( $old_tok ) );

	$report( 'Email change / verify / password' );
	t_browser();
	lc_auth_dev_outbox_clear();
	$u = t_user();
	lc_auth_mark_email_verified( $u->ID, $u->user_email );
	t_session( $u->ID );
	$old_email = $u->user_email;
	$new_email = 'chg' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	$r = lc_auth_email_change_request( get_userdata( $u->ID ), $new_email );
	t( 'change request: link goes to the NEW address', true === $r['ok'] && '' !== t_link_token( $new_email ) && 0 === t_outbox_count( $old_email ) );
	t( 'change request changes nothing yet', strtolower( get_userdata( $u->ID )->user_email ) === strtolower( $old_email ) );
	$token = t_link_token( $new_email );
	wp_set_current_user( 0 ); // Not signed in as that user.
	unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	t( 'change link refuses anyone but the signed-in user, and is kept', 'link_failed' === t_code( t_confirm( $token ) ) && null !== lc_auth_store_get( 'ml', $token ) );
	t_session( $u->ID );
	$res = t_confirm( $token );
	$u   = get_userdata( $u->ID );
	t( 'change link switches the address, verified', is_array( $res ) && strtolower( $u->user_email ) === $new_email && lc_auth_email_verified( $u ), t_code( $res ) );
	t( 'change link signs nobody in (session unchanged)', get_current_user_id() === $u->ID );
	t( 'core notice for the old (verified) address was captured', t_outbox_count( $old_email ) >= 1 );
	// Address that belongs to someone else: same answer, no mail.
	lc_auth_dev_outbox_clear();
	$r = lc_auth_email_change_request( $u, $member->user_email );
	t( 'change to a taken address: same answer, no mail', true === $r['ok'] && 0 === t_outbox_count( $member->user_email ) );

	// The pending change is kept per user AND address.
	$ea     = t_user();
	$eb     = t_user();
	$shared = 'slot' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	$ta     = lc_auth_email_issue( 'email_change', $shared, $ea->ID, '' );
	$tb     = lc_auth_email_issue( 'email_change', $shared, $eb->ID, '' );
	t( 'email_change slot: another member asking for the same address does not cancel mine', null !== lc_auth_store_get( 'ml', $ta ) && null !== lc_auth_store_get( 'ml', $tb ) );
	$ta2 = lc_auth_email_issue( 'email_change', $shared, $ea->ID, '' );
	t( 'email_change slot: my own newer request replaces only my older one', null === lc_auth_store_get( 'ml', $ta ) && null !== lc_auth_store_get( 'ml', $ta2 ) && null !== lc_auth_store_get( 'ml', $tb ) );

	// Verify an unverified address.
	$v = t_user();
	t_session( $v->ID );
	t( 'new test user starts unverified', ! lc_auth_email_verified( $v ) );
	lc_auth_dev_outbox_clear();
	$r = lc_auth_email_verify_request( $v );
	$token = t_link_token( $v->user_email );
	wp_set_current_user( 0 );
	$res = t_confirm( $token );
	t( 'verify link marks the address verified (no sign-in needed)', true === $r['ok'] && is_array( $res ) && lc_auth_email_verified( $v->ID ) && 0 === get_current_user_id() );

	// Password link only for verified addresses.
	lc_auth_dev_outbox_clear();
	$un = t_user();
	t( 'password link needs a verified email', 'password_need' === lc_auth_password_link( $un )['code'] );
	lc_auth_mark_email_verified( $un->ID, $un->user_email );
	$r = lc_auth_password_link( get_userdata( $un->ID ) );
	t( 'password link sends core reset mail to a verified address', true === $r['ok'] && 1 === t_outbox_count( $un->user_email ), $r['code'] );

	/* ============================================================ mail gate == */
	$report( 'Mail gate' );
	// Anything addressed to the site admin (the "cap reached" alerts) is captured, never sent.
	$admin_mails = 0;
	$catch_admin = function ( $return, $atts ) use ( &$admin_mails ) {
		$to = is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : (string) $atts['to'];
		if ( false !== stripos( $to, (string) get_option( 'admin_email' ) ) ) {
			++$admin_mails;
			return true;
		}
		return $return;
	};
	add_filter( 'pre_wp_mail', $catch_admin, -1, 2 );
	delete_transient( 'lc_auth_mail_cap_alerted' );

	// Normalised address keys.
	t( 'email key: lowercase, +tag removed', 'jo.smith@example.com' === lc_auth_email_key( ' Jo.Smith+news@Example.COM ' ) );
	t( 'email key: gmail dots dropped, googlemail is gmail', 'josmith@gmail.com' === lc_auth_email_key( 'Jo.Smith+x@googlemail.com' ) && lc_auth_email_key( 'j.o@gmail.com' ) === lc_auth_email_key( 'jo+2@googlemail.com' ) );
	t( 'email key: dots kept on other domains', 'a.b@lc-test.invalid' === lc_auth_email_key( 'a.b+c@lc-test.invalid' ) );

	// Per-recipient cap across every kind, including email_change.
	t_browser();
	lc_auth_dev_outbox_clear();
	$gm    = 'gate' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	$kinds = array( 'signin', 'wallet_first', 'password', 'email_verify', 'wallet_linked', 'email_change' );
	$ret   = array();
	foreach ( $kinds as $k ) {
		$ret[] = lc_auth_email_send( $gm, 'subject', 'body', $k );
	}
	t( 'recipient cap: 5 an hour over all kinds, the 6th (email_change) is skipped', 5 === t_outbox_count( $gm ), (string) t_outbox_count( $gm ) );
	t( 'recipient cap: the caller gets the same success for a skipped mail', ! in_array( false, $ret, true ) );
	$chg = t_user();
	lc_auth_dev_outbox_clear();
	$r = lc_auth_email_change_request( $chg, $gm );
	t( 'recipient cap holds for the email-change request (same answer, no mail)', true === $r['ok'] && 'email_check' === $r['code'] && 0 === t_outbox_count( $gm ) );
	$base = 'rc' . bin2hex( random_bytes( 4 ) );
	t_browser();
	lc_auth_dev_outbox_clear();
	$sum = 0;
	foreach ( array( '', '+a', '+b', '+c', '+d', '+e', '+f' ) as $tag ) {
		lc_auth_email_send( $base . $tag . '@lc-test.invalid', 's', 'b', 'signin' );
		$sum += t_outbox_count( $base . $tag . '@lc-test.invalid' );
		lc_auth_dev_outbox_clear();
	}
	t( 'recipient cap: +tag variants share one bucket (5 of 7 delivered)', 5 === $sum, (string) $sum );

	// Gmail aliases share a bucket (checked on the gate itself: gmail mail is not captured).
	t_browser();
	$f_lim = t_limits( array( 'rcpt_h' => 2 ) );
	lc_auth_mail_count( 'first.last@gmail.com' );
	lc_auth_mail_count( 'firstlast+x@googlemail.com' );
	t( 'gmail: dotted, +tagged and googlemail forms share the recipient bucket', 'rcpt_hour' === lc_auth_mail_check( 'f.i.r.s.t.l.a.s.t@gmail.com' ) && '' === lc_auth_mail_check( 'someone.else@gmail.com' ) );
	remove_filter( 'lc_auth_mail_limits', $f_lim );

	// Per-visitor and coarse buckets.
	t_browser();
	lc_auth_dev_outbox_clear();
	$f_lim = t_limits( array( 'ip' => 3 ) );
	$sum   = 0;
	for ( $i = 0; $i < 5; $i++ ) {
		$a = 'v' . $i . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid';
		lc_auth_email_send( $a, 's', 'b', 'signin' );
		$sum += t_outbox_count( $a );
	}
	remove_filter( 'lc_auth_mail_limits', $f_lim );
	t( 'visitor cap: only 3 mails per 15 min from one visitor', 3 === $sum, (string) $sum );
	t_browser();
	lc_auth_dev_outbox_clear();
	$f_lim = t_limits( array( 'ip_coarse' => 2 ) );
	$sum   = 0;
	for ( $i = 0; $i < 4; $i++ ) {
		$a = 'c' . $i . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid';
		lc_auth_email_send( $a, 's', 'b', 'signin' );
		$sum += t_outbox_count( $a );
	}
	remove_filter( 'lc_auth_mail_limits', $f_lim );
	t( 'coarse cap: only 2 mails per 15 min from one /24 or /48', 2 === $sum, (string) $sum );

	// Coarse subject: IPv4 /24, IPv6 /48.
	t( 'coarse subject: IPv4 is its /24', '203.0.113.0/24' === lc_auth_coarse_subject( '203.0.113.77' ) && lc_auth_coarse_subject( '203.0.113.5' ) === lc_auth_coarse_subject( '203.0.113.250' ) && lc_auth_coarse_subject( '203.0.114.5' ) !== lc_auth_coarse_subject( '203.0.113.5' ) );
	t( 'coarse subject: IPv6 is its /48 (two /64s inside it share it)', lc_auth_coarse_subject( '2001:db8:1:2::1' ) === lc_auth_coarse_subject( '2001:db8:1:ffff::9' ) && lc_auth_coarse_subject( '2001:db8:2:2::1' ) !== lc_auth_coarse_subject( '2001:db8:1:2::1' ) && '/48' === substr( lc_auth_coarse_subject( '2001:db8:1:2::1' ), -3 ) );
	t( 'coarse subject: IPv4-mapped IPv6 counts as IPv4', '203.0.113.0/24' === lc_auth_coarse_subject( '::ffff:203.0.113.9' ) );
	t_browser();
	for ( $i = 0; $i < 25; $i++ ) {
		lc_auth_rate( 'ml_coarse', lc_auth_coarse_subject(), 25, 15 * MINUTE_IN_SECONDS );
	}
	$r = t_request_link( 'co' . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid' );
	t( 'magic-link requests: the coarse bucket answers 429 (about 5x the per-IP limit)', 'rate' === $r['code'] && 429 === ( $r['status'] ?? 0 ) );

	// A skipped mail makes no token and uses no budget.
	t_browser();
	lc_auth_dev_outbox_clear();
	$f_lim  = t_limits( array( 'rcpt_h' => 0 ) );
	$before = t_live_tokens();
	t_request_link( 'skip' . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid' );
	t( 'a request whose mail is skipped issues no link token', t_live_tokens() === $before );
	remove_filter( 'lc_auth_mail_limits', $f_lim );

	// Global cap.
	t_browser();
	lc_auth_dev_outbox_clear();
	$cap0 = fn() => 0;
	add_filter( 'lc_auth_email_daily_cap', $cap0 );
	$gver = t_user();
	lc_auth_mark_email_verified( $gver->ID, $gver->user_email );
	$gunv = t_user();
	$gnew = 'gnew' . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid';
	$admin_mails = 0;
	lc_auth_email_send( $gver->user_email, 's', 'b', 'signin' );
	lc_auth_email_send( $gnew, 's', 'b', 'signin' );
	lc_auth_email_send( $gunv->user_email, 's', 'b', 'signin' );
	t( 'global cap reached: a verified existing account still gets its mail', 1 === t_outbox_count( $gver->user_email ) );
	t( 'global cap reached: an unknown address gets nothing', 0 === t_outbox_count( $gnew ) );
	t( 'global cap reached: an existing but unverified address gets nothing', 0 === t_outbox_count( $gunv->user_email ) );
	t( 'global cap reached: the admin is emailed once (not per blocked mail)', 1 === $admin_mails, (string) $admin_mails );
	t( 'global cap reached: same for core reset mail (verified yes, unverified no)', true === apply_filters( 'send_retrieve_password_email', true, $gver->user_login, get_userdata( $gver->ID ) ) && false === apply_filters( 'send_retrieve_password_email', true, $gunv->user_login, get_userdata( $gunv->ID ) ) );
	t_browser();
	lc_auth_dev_outbox_clear();
	$r = t_request_link( $gver->user_email );
	t( 'global cap reached: a verified member can still request a sign-in link', true === $r['ok'] && '' !== t_link_token( $gver->user_email ) );
	remove_filter( 'lc_auth_email_daily_cap', $cap0 );

	// Skipped mails do not use the global budget.
	t_browser();
	lc_auth_dev_outbox_clear();
	$cap2 = fn() => 2;
	add_filter( 'lc_auth_email_daily_cap', $cap2 );
	$f_lim = t_limits( array( 'rcpt_h' => 1 ) );
	$ga    = 'ga' . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid';
	$gb    = 'gb' . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid';
	$gc    = 'gc' . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid';
	lc_auth_email_send( $ga, 's', 'b', 'signin' );
	lc_auth_email_send( $ga, 's', 'b', 'signin' ); // Skipped by the recipient cap.
	lc_auth_email_send( $ga, 's', 'b', 'signin' ); // Skipped again.
	lc_auth_email_send( $gb, 's', 'b', 'signin' );
	lc_auth_email_send( $gc, 's', 'b', 'signin' ); // Third real mail: over the cap of 2.
	t( 'skipped mails use no global budget (A once, B sent, C stopped by the cap of 2)', 1 === t_outbox_count( $ga ) && 1 === t_outbox_count( $gb ) && 0 === t_outbox_count( $gc ) );
	remove_filter( 'lc_auth_mail_limits', $f_lim );
	remove_filter( 'lc_auth_email_daily_cap', $cap2 );

	// Core's lost-password form.
	t_browser();
	lc_auth_dev_outbox_clear();
	$lp = t_user();
	lc_auth_mark_email_verified( $lp->ID, $lp->user_email );
	$errs = array();
	for ( $i = 0; $i < 4; $i++ ) {
		$e = new WP_Error();
		do_action( 'lostpassword_post', $e, get_userdata( $lp->ID ) );
		$errs[] = $e->has_errors();
	}
	t( 'lostpassword_post: the 4th request for one account in an hour adds an error', array( false, false, false, true ) === $errs );
	t_browser();
	$errs = array();
	for ( $i = 0; $i < 6; $i++ ) {
		$e = new WP_Error();
		do_action( 'lostpassword_post', $e, false ); // Unknown account: only the visitor bucket applies.
		$errs[] = $e->has_errors();
	}
	t( 'lostpassword_post: the 6th request from one visitor in 15 min adds an error', array( false, false, false, false, false, true ) === $errs );
	t_browser();
	lc_auth_dev_outbox_clear();
	$n = 0;
	for ( $i = 0; $i < 4; $i++ ) {
		$res = retrieve_password( get_userdata( $lp->ID )->user_login );
		$n  += is_wp_error( $res ) ? 0 : 1;
	}
	t( 'core retrieve_password: 3 reset mails go, the 4th is refused and sends nothing', 3 === $n && 3 === t_outbox_count( $lp->user_email ), $n . '/' . t_outbox_count( $lp->user_email ) );

	remove_filter( 'pre_wp_mail', $catch_admin, -1 );
	delete_transient( 'lc_auth_mail_cap_alerted' );

	/* ======================================== REST, recency, lockdown, CSRF == */
	$report( 'REST users endpoint' );
	$rest = function ( $user_id, $method, $route, array $params = array() ) {
		$GLOBALS['wp_rest_server'] = null; // rest_endpoints is filtered once per server: rebuild it for this user.
		wp_set_current_user( $user_id );
		$req = new WP_REST_Request( $method, $route );
		foreach ( $params as $k => $v ) {
			$req->set_param( $k, $v );
		}
		$res = rest_do_request( $req );
		$GLOBALS['wp_rest_server'] = null;
		return $res->get_status();
	};
	t_browser();
	$rs = t_user();
	$st = $rest( $rs->ID, 'POST', '/wp/v2/users/me', array( 'email' => 'steal' . $rs->ID . '@lc-test.invalid' ) );
	t( 'subscriber cannot update /wp/v2/users/me (403 or 404)', in_array( $st, array( 403, 404 ), true ), (string) $st );
	t( 'subscriber email unchanged', strtolower( get_userdata( $rs->ID )->user_email ) === strtolower( $rs->user_email ) );
	$st = $rest( $rs->ID, 'POST', '/wp/v2/users/' . $rs->ID, array( 'email' => 'steal' . $rs->ID . '@lc-test.invalid' ) );
	t( 'subscriber cannot update /wp/v2/users/<own id> (403 or 404)', in_array( $st, array( 403, 404 ), true ), (string) $st );
	$st = $rest( 0, 'GET', '/wp/v2/users' );
	t( 'visitor cannot list users (401, 403 or 404)', in_array( $st, array( 401, 403, 404 ), true ), (string) $st );
	$st = $rest( $editor->ID, 'GET', '/wp/v2/users/me' );
	t( 'editor can still GET /wp/v2/users/me', 200 === $st, (string) $st );
	wp_set_current_user( 0 );

	$report( 'Recent sign-in for sensitive changes' );
	t_browser();
	$ra = t_user();
	lc_auth_mark_email_verified( $ra->ID, $ra->user_email );
	t_session_aged( $ra->ID, 31 * MINUTE_IN_SECONDS );
	t( 'wallet link needs a recent sign-in (31 min old refused)', 'delete_recent' === t_code( t_challenge( t_wallet(), 'link', wp_create_nonce( 'lc_wallet_link' ) ) ) );
	t_session_aged( $ra->ID, 5 * MINUTE_IN_SECONDS );
	$rw = t_wallet();
	$ch = t_challenge( $rw, 'link', wp_create_nonce( 'lc_wallet_link' ) );
	t( 'wallet link with a 5 min old sign-in works', is_array( $ch ), t_code( $ch ) );
	t( 'link challenge names the account', is_array( $ch ) && false !== strpos( $ch['message'], 'Learn Cardano account “' . lc_public_name( get_userdata( $ra->ID ) ) . '”.' ) );
	lc_auth_dev_outbox_clear();
	if ( is_array( $ch ) ) {
		list( $sig, $key ) = t_sign( $rw, $ch['message'] );
		$res = lc_auth_wallet_verify( array( 'nonce' => $ch['nonce'], 'signature' => $sig, 'key' => $key ) );
		t( 'wallet link succeeds and emails the verified address', is_array( $res ) && 1 === t_outbox_count( $ra->user_email ), t_code( $res ) );
	}

	$report( 'CardanoPress lockdown lifts after adoption + verified email' );
	t_browser();
	$lw = t_wallet();
	$lu = t_user( 'subscriber', true, md5( $lw['stake'] ) );
	if ( function_exists( 'lc_cardanopress_account_locked' ) ) {
		t( 'legacy account: password reset refused while locked', ! apply_filters( 'allow_password_reset', true, $lu->ID ) );
		t( 'legacy account: password login refused while locked', is_wp_error( apply_filters( 'wp_authenticate_user', get_userdata( $lu->ID ), '' ) ) );
	}
	$res = t_wallet_signin( $lw );
	t( 'legacy account adopted by wallet sign-in', is_array( $res ) && get_current_user_id() === $lu->ID, t_code( $res ) );
	t_browser();
	lc_auth_dev_outbox_clear();
	lc_auth_email_verify_request( get_userdata( $lu->ID ) );
	$token = t_link_token( $lu->user_email );
	$res   = t_confirm( $token );
	t( 'legacy account verifies its email', is_array( $res ) && lc_auth_email_verified( $lu->ID ), t_code( $res ) );
	$pl = lc_auth_password_link( get_userdata( $lu->ID ) );
	t( 'legacy account then gets a password email (password_sent)', 'password_sent' === ( $pl['code'] ?? '' ), (string) ( $pl['code'] ?? '' ) );
	t( 'core email-changed notice stays on for staff', true === apply_filters( 'send_email_change_email', true, array( 'ID' => $editor->ID, 'user_email' => $editor->user_email ), array() ) );

	/* ============================================================ account == */
	$report( 'Account' );
	t_browser();
	$names_ok  = array( 'Alice B', 'Jo', 'Åsa Ünal', str_repeat( 'a', 40 ) );
	$names_bad = array( 'a', str_repeat( 'a', 41 ), 'me@example.com', 'see http://x.test', 'www.example.com', md5( 'x' ), $w['stake'], 'addr1' . str_repeat( 'q', 30 ), $editor->display_name, strtoupper( $editor->display_name ), 'Admin', $editor->user_login );
	t( 'display names: good ones accepted', ! in_array( false, array_map( 'lc_auth_validate_display_name', $names_ok ), true ) );
	$rejected = array_filter( $names_bad, fn( $n ) => false === lc_auth_validate_display_name( $n ) );
	t( 'display names: bad ones refused (' . count( $rejected ) . '/' . count( $names_bad ) . ')', count( $rejected ) === count( $names_bad ), implode( ' | ', array_diff( $names_bad, $rejected ) ) );
	$mach = t_user();
	wp_update_user( array( 'ID' => $mach->ID, 'display_name' => md5( 'a' ) ) );
	t( 'public name: a hex name shows as "Cardano member"', 'Cardano member' === lc_public_name( get_userdata( $mach->ID ) ) );
	wp_update_user( array( 'ID' => $mach->ID, 'display_name' => 'Real Name' ) );
	t( 'public name: a real name is kept', 'Real Name' === lc_public_name( get_userdata( $mach->ID ) ) );

	$d  = t_user();
	$cm = wp_insert_comment( array( 'comment_post_ID' => (int) get_option( 'page_on_front' ) ?: 1, 'comment_content' => 'lc-test-delete', 'user_id' => $d->ID, 'comment_author' => 'Dee', 'comment_author_email' => $d->user_email, 'comment_author_IP' => '203.0.113.9', 'comment_agent' => 'Mozilla/5.0 lc-test', 'comment_approved' => 1 ) );
	$GLOBALS['lc_t']['comments'][] = $cm;
	lc_auth_delete_account( $d );
	$c = get_comment( $cm );
	t( 'delete: user gone', false === get_userdata( $d->ID ) );
	t( 'delete: comment kept but anonymised', $c && 0 === (int) $c->user_id && 'Former member' === $c->comment_author && '' === $c->comment_author_email && '' === $c->comment_author_IP && '' === $c->comment_agent );


	// Deleting a member also deletes the legacy wallet accounts merged into them (and only those).
	t_browser();
	$dw   = t_wallet();
	$dold = t_user( 'subscriber', false, md5( $dw['stake'] ) );
	$dnew = t_user();
	t_session( $dnew->ID );
	$res = t_wallet_link( $dw );
	t( 'delete setup: legacy account merged into the member', is_array( $res ) && (int) get_user_meta( $dold->ID, 'lc_merged_into', true ) === $dnew->ID && ! get_userdata( $dold->ID )->roles, t_code( $res ) );
	$dkeep = t_user(); // Not a legacy account, merely pointing at the member: stays.
	update_user_meta( $dkeep->ID, 'lc_merged_into', $dnew->ID );
	$dkeep2 = t_user( 'subscriber', false, md5( 'keep-' . bin2hex( random_bytes( 4 ) ) ) ); // Legacy login but still has a role: stays.
	update_user_meta( $dkeep2->ID, 'lc_merged_into', $dnew->ID );
	wp_set_current_user( 0 );
	lc_auth_delete_account( get_userdata( $dnew->ID ) );
	t( 'delete: the member and the merged legacy account are both gone', false === get_userdata( $dnew->ID ) && false === get_userdata( $dold->ID ) );
	t( 'delete: other users are left alone (no legacy login, or still has a role)', false !== get_userdata( $dkeep->ID ) && false !== get_userdata( $dkeep2->ID ) );

	// The verified flag is not a second copy of the address.
	$ev = t_user();
	lc_auth_mark_email_verified( $ev->ID, $ev->user_email );
	$raw = (string) get_user_meta( $ev->ID, 'lc_email_verified', true );
	t( 'lc_email_verified is an HMAC, not the address', false === strpos( $raw, '@' ) && false === stripos( $raw, explode( '@', $ev->user_email )[0] ) && 64 === strlen( $raw ) && lc_auth_email_verified( $ev->ID ) );
	update_user_meta( $ev->ID, 'lc_email_verified', strtoupper( $ev->user_email ) ); // Written by the old code.
	t( 'legacy plaintext value still verifies the same address...', lc_auth_email_verified( $ev->ID ) );
	t( '...and was rewritten as an HMAC by that check', false === strpos( (string) get_user_meta( $ev->ID, 'lc_email_verified', true ), '@' ) && lc_auth_email_verified( $ev->ID ) );
	update_user_meta( $ev->ID, 'lc_email_verified', 'someone-else@lc-test.invalid' );
	t( 'legacy plaintext for another address does not verify and is not rewritten', ! lc_auth_email_verified( $ev->ID ) && false !== strpos( (string) get_user_meta( $ev->ID, 'lc_email_verified', true ), '@' ) );
	wp_update_user( array( 'ID' => $ev->ID, 'user_email' => 'ev2' . $ev->ID . '@lc-test.invalid' ) );
	t( 'a changed address is unverified (HMAC no longer matches)', ! lc_auth_email_verified( $ev->ID ) );

	$sess = t_user();
	t_session( $sess->ID );
	t( 'session age: fresh session is recent', lc_auth_session_age() < 60 );

	t( 'rewrite rule for /account/ is registered', (bool) array_filter( array_keys( (array) get_option( 'rewrite_rules' ) ), fn( $r ) => 0 === strpos( $r, '^account' ) ), 'run wp rewrite flush' );
} catch ( Throwable $e ) {
	t( 'no exception', false, get_class( $e ) . ': ' . $e->getMessage() . ' @' . basename( $e->getFile() ) . ':' . $e->getLine() );
} finally {
	global $wpdb;
	wp_set_current_user( 0 );
	foreach ( $GLOBALS['lc_t']['comments'] as $cid ) {
		wp_delete_comment( $cid, true );
	}
	// Anything created by flows (members made by wallet/email sign-up) carries no lc_test meta; the ones we saw are tracked.
	$ids = array_unique( array_merge( $GLOBALS['lc_t']['users'], array_map( 'intval', $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'lc_test'" ) ) ) );
	foreach ( $ids as $id ) {
		if ( get_userdata( $id ) ) {
			wp_delete_user( $id );
		}
	}
	foreach ( array( 'lc\_rl\_', 'lc\_ml\_', 'lc\_mli\_', 'lc\_wc\_', 'lc\_tt\_' ) as $prefix ) {
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_{$prefix}%' OR option_name LIKE '\\_transient\\_timeout\\_{$prefix}%'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed strings.
	}
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'lc\\_lock\\_%'" );
	delete_transient( 'lc_auth_mail_cap_alerted' );
	delete_transient( 'lc_auth_wallet_signup_alerted' );
	lc_auth_dev_outbox_clear();
	echo "\nCleaned up " . count( $ids ) . " test users.\n";
	printf( "\n%d passed, %d failed\n", $GLOBALS['lc_t']['pass'], $GLOBALS['lc_t']['fail'] );
	WP_CLI::halt( $GLOBALS['lc_t']['fail'] ? 1 : 0 );
}
