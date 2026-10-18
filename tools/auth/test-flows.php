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
lc_auth_load( 'oauth' );
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

/* ---- OAuth helpers (R3) --------------------------------------------------- */

$GLOBALS['lc_t_http'] = array(); // Every outgoing HTTP request the mocks saw.

// Test client credentials, only when the site has none (HTTP is always mocked, so nothing real is called).
foreach ( array( 'GOOGLE', 'DISCORD' ) as $lc_t_p ) {
	if ( ! defined( "LC_{$lc_t_p}_CLIENT_ID" ) ) {
		define( "LC_{$lc_t_p}_CLIENT_ID", 'test-client' );
	}
	if ( ! defined( "LC_{$lc_t_p}_CLIENT_SECRET" ) ) {
		define( "LC_{$lc_t_p}_CLIENT_SECRET", 'test-secret-do-not-print' );
	}
}

/** A canned wp_remote_* response. */
function t_resp( $code, $body ) {
	return array( 'headers' => array(), 'body' => is_string( $body ) ? $body : wp_json_encode( $body ), 'response' => array( 'code' => $code, 'message' => '' ), 'cookies' => array(), 'filename' => null );
}

/** Answer outgoing HTTP by URL prefix; unmatched URLs fail. Requests are logged in $GLOBALS['lc_t_http']. Returns the filter (remove_filter it). */
function t_oauth_http( array $map ) {
	$fn = function ( $pre, $args, $url ) use ( $map ) {
		unset( $pre );
		$GLOBALS['lc_t_http'][] = array( 'url' => $url, 'args' => $args );
		foreach ( $map as $prefix => $resp ) {
			if ( 0 === strpos( $url, $prefix ) ) {
				return $resp;
			}
		}
		return new WP_Error( 't_unmocked', $url );
	};
	add_filter( 'pre_http_request', $fn, 10, 3 );
	return $fn;
}

/** Unsigned id_token: the signature is not checked (the token comes from the direct TLS exchange). */
function t_jwt( array $claims, array $header = array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) {
	return lc_auth_b64url( wp_json_encode( $header ) ) . '.' . lc_auth_b64url( wp_json_encode( $claims ) ) . '.' . lc_auth_b64url( 'not-a-signature' );
}

/** Default claims, with $over applied (a null value removes the claim). */
function t_claims( $nonce, array $over = array() ) {
	$cid = lc_auth_oauth_creds( 'google' )['id'];
	$now = time();
	$c   = array(
		'iss'            => 'https://accounts.google.com',
		'aud'            => $cid,
		'azp'            => $cid,
		'sub'            => 'lctest-' . bin2hex( random_bytes( 6 ) ),
		'email'          => 'oa' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid',
		'email_verified' => true,
		'given_name'     => 'Tess',
		'iat'            => $now,
		'exp'            => $now + 3600,
		'nonce'          => $nonce,
	);
	foreach ( $over as $k => $v ) {
		if ( null === $v ) {
			unset( $c[ $k ] );
		} else {
			$c[ $k ] = $v;
		}
	}
	return $c;
}

/** Start a flow and pull state/nonce out of the provider URL. */
function t_oauth_begin( $provider, $intent = 'signin', $redirect = '' ) {
	$r = lc_auth_oauth_start( $provider, $intent, $redirect );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	parse_str( (string) wp_parse_url( $r['url'], PHP_URL_QUERY ), $q );
	return array( 'url' => $r['url'], 'state' => $q['state'] ?? '', 'nonce' => $q['nonce'] ?? '', 'q' => $q );
}

function t_track() {
	if ( get_current_user_id() ) {
		$GLOBALS['lc_t']['users'][] = get_current_user_id();
	}
}

/** A whole Google round trip with mocked HTTP. Returns array( res, claims, b ). */
function t_g_flow( $intent = 'signin', array $over = array(), array $q = array(), $redirect = '', $token_body = null ) {
	$b = t_oauth_begin( 'google', $intent, $redirect );
	if ( is_wp_error( $b ) ) {
		return array( 'res' => $b, 'claims' => array(), 'b' => array() );
	}
	$claims = t_claims( $b['nonce'], $over );
	$f      = t_oauth_http( array( 'https://oauth2.googleapis.com/token' => $token_body ?? t_resp( 200, array( 'id_token' => t_jwt( $claims ), 'access_token' => 'at', 'token_type' => 'Bearer' ) ) ) );
	$res    = lc_auth_oauth_callback( 'google', array( 'code' => 'c0de', 'state' => $b['state'] ) + $q );
	remove_filter( 'pre_http_request', $f, 10 );
	t_track();
	return array( 'res' => $res, 'claims' => $claims, 'b' => $b );
}

/** A whole Discord round trip with mocked HTTP. $profile overrides the /users/@me answer; null removes a field. */
function t_d_flow( $intent = 'signin', array $profile = array(), array $q = array(), $redirect = '' ) {
	$b = t_oauth_begin( 'discord', $intent, $redirect );
	if ( is_wp_error( $b ) ) {
		return array( 'res' => $b, 'profile' => array(), 'b' => array() );
	}
	$u = array(
		'id'          => '99999' . random_int( 100000000000, 999999999999 ),
		'username'    => 'tessd',
		'global_name' => 'Tess D',
		'email'       => 'od' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid',
		'verified'    => true,
	);
	foreach ( $profile as $k => $v ) {
		if ( null === $v ) {
			unset( $u[ $k ] );
		} else {
			$u[ $k ] = $v;
		}
	}
	$f   = t_oauth_http(
		array(
			'https://discord.com/api/oauth2/token/revoke' => t_resp( 200, '{}' ),
			'https://discord.com/api/oauth2/token'        => t_resp( 200, array( 'access_token' => 'at-d', 'token_type' => 'Bearer' ) ),
			'https://discord.com/api/v10/users/@me'       => t_resp( 200, $u ),
		)
	);
	$res = lc_auth_oauth_callback( 'discord', array( 'code' => 'c0de', 'state' => $b['state'] ) + $q );
	remove_filter( 'pre_http_request', $f, 10 );
	t_track();
	return array( 'res' => $res, 'profile' => $u, 'b' => $b );
}

/** Number of live OAuth state records. */
function t_live_states() {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_lc\_oa\_%'" );
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
	$kinds = array( 'signin', 'wallet_first', 'password', 'email_verify', 'signin', 'email_change' ); // Not wallet_linked: security notices have their own bucket.
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
	// Render the account page for a member with every way to sign in connected,
	// so a template error in any row (wallet, Google, Discord) fails here, not on the site.
	$full = t_user();
	lc_auth_mark_email_verified( $full->ID, $full->user_email );
	add_user_meta( $full->ID, 'lc_wallet', 'stake1u' . str_repeat( 'q', 52 ) );
	update_user_meta( $full->ID, 'lc_wallet_info', array( 'stake1u' . str_repeat( 'q', 52 ) => array( 'linked' => time(), 'used' => time() ) ) );
	foreach ( array( 'google', 'discord' ) as $p ) {
		update_user_meta( $full->ID, 'lc_idp_' . $p, 'lctest-render-' . $p . '-' . $full->ID );
	}
	update_user_meta( $full->ID, 'lc_idp_info', array( 'google' => array( 'linked' => time(), 'used' => time(), 'hint' => 'p•••@gmail.com' ), 'discord' => array( 'linked' => time(), 'used' => 0, 'hint' => '' ) ) );
	wp_set_current_user( $full->ID );
	$html = '';
	try {
		ob_start();
		get_template_part( 'template-parts/account/manage' );
		$html = ob_get_clean();
	} catch ( Throwable $e ) {
		ob_end_clean();
		$html = 'FATAL ' . $e->getMessage();
	}
	t( 'account page renders with wallet, Google and Discord all connected', false !== strpos( $html, 'value="signout"' ) && substr_count( $html, 'Disconnect' ) >= 2, substr( $html, 0, 120 ) );
	wp_set_current_user( 0 );
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
	/* =============================================================== OAuth == */
	$report( 'OAuth: providers, start' );
	t_browser();
	$cid_g   = lc_auth_oauth_creds( 'google' )['id'];
	$enabled = lc_auth_providers();
	t( 'registry: google and discord are enabled with constants set', isset( $enabled['google'], $enabled['discord'] ) && ! isset( $enabled['x'] ) );
	t( 'registry never carries a secret', false === strpos( wp_json_encode( lc_auth_providers( true ) ), lc_auth_oauth_creds( 'google' )['secret'] ) && false === strpos( wp_json_encode( lc_auth_providers( true ) ), lc_auth_oauth_creds( 'discord' )['secret'] ) );
	$off = function ( $on, $id ) {
		return 'discord' === $id ? false : $on;
	};
	add_filter( 'lc_auth_provider_enabled', $off, 10, 2 );
	t( 'registry: lc_auth_provider_enabled can switch one off', array( 'google' ) === array_keys( lc_auth_providers() ) && isset( lc_auth_providers( true )['discord'] ) );
	t( 'disabled provider: start refused', 'oauth_unavailable' === t_code( lc_auth_oauth_start( 'discord', 'signin', '' ) ) );
	t( 'disabled provider: no sign-in link in the panel', false === strpos( lc_auth_oauth_buttons( '' ), 'oauth/discord/' ) && false !== strpos( lc_auth_oauth_buttons( '' ), 'oauth/google/' ) );
	remove_filter( 'lc_auth_provider_enabled', $off, 10 );
	t( 'unknown provider: refused', 'oauth_unavailable' === t_code( lc_auth_oauth_start( 'x', 'signin', '' ) ) );
	$btns = lc_auth_oauth_buttons( home_url( '/courses/' ) );
	t( 'panel links are plain nofollow anchors carrying redirect_to', 2 === preg_match_all( '#<a class="lc-auth__sso [^"]*" href="[^"]*/account/oauth/(google|discord)/[^"]*" rel="nofollow">#', $btns ) && false !== strpos( $btns, 'redirect_to=' . rawurlencode( home_url( '/courses/' ) ) ) );

	$b = t_oauth_begin( 'google', 'signin', home_url( '/courses/' ) );
	t( 'start google: ok', is_array( $b ), t_code( $b ) );
	if ( is_array( $b ) ) {
		$q = $b['q'];
		t( 'start google: provider URL, response_type=code, exact redirect_uri', 0 === strpos( $b['url'], 'https://accounts.google.com/o/oauth2/v2/auth?' ) && 'code' === $q['response_type'] && home_url( '/account/oauth/google/callback/' ) === $q['redirect_uri'] );
		t( 'start google: client id, scope, prompt=select_account', $cid_g === $q['client_id'] && 'openid email profile' === $q['scope'] && 'select_account' === $q['prompt'] );
		t( 'start google: state and nonce are 43-char tokens, PKCE S256', lc_auth_is_token( $q['state'] ) && lc_auth_is_token( $q['nonce'] ) && 'S256' === $q['code_challenge_method'] && 43 === strlen( $q['code_challenge'] ) );
		$rec = lc_auth_store_get( 'oa', $q['state'] );
		t( 'start google: record bound to flow cookie, provider, intent; challenge matches verifier', $rec && hash_equals( $rec['fh'], lc_auth_flow( false ) ) && 'google' === $rec['p'] && 'signin' === $rec['i'] && lc_auth_b64url( hash( 'sha256', $rec['v'], true ) ) === $q['code_challenge'] && $rec['n'] === $q['nonce'] );
		t( 'start: the raw state is not an option name (key is an HMAC)', false === get_transient( 'lc_oa_' . $q['state'] ) );
	}
	$bd = t_oauth_begin( 'discord', 'signin', '' );
	t( 'start discord: authorize URL, scope identify email, no OIDC nonce, S256', is_array( $bd ) && 0 === strpos( $bd['url'], 'https://discord.com/oauth2/authorize?' ) && 'identify email' === $bd['q']['scope'] && ! isset( $bd['q']['nonce'] ) && 'S256' === $bd['q']['code_challenge_method'] );
	add_filter( 'lc_auth_oauth_pkce', '__return_false' );
	$bn = t_oauth_begin( 'discord', 'signin', '' );
	remove_filter( 'lc_auth_oauth_pkce', '__return_false' );
	t( 'start discord: the lc_auth_oauth_pkce filter turns PKCE off', is_array( $bn ) && ! isset( $bn['q']['code_challenge'] ) );
	t( 'start: bad intent refused', 'unknown' === t_code( lc_auth_oauth_start( 'google', 'merge', '' ) ) );

	// Prefetch: no side effects at all.
	t_browser();
	$before                      = t_live_states();
	$_SERVER['HTTP_SEC_PURPOSE'] = 'prefetch;prerender';
	$res                         = lc_auth_oauth_start( 'google', 'signin', '' );
	unset( $_SERVER['HTTP_SEC_PURPOSE'] );
	t( 'start: Sec-Purpose prefetch refused with no record stored', 'oauth_prefetch' === t_code( $res ) && t_live_states() === $before );
	$_SERVER['HTTP_PURPOSE'] = 'prefetch';
	$res                     = lc_auth_oauth_start( 'google', 'signin', '' );
	unset( $_SERVER['HTTP_PURPOSE'] );
	t( 'start: Purpose prefetch refused too', 'oauth_prefetch' === t_code( $res ) );

	// Rate limit.
	t_browser();
	$last = null;
	for ( $i = 0; $i < 21; $i++ ) {
		$last = lc_auth_oauth_start( 'google', 'signin', '' );
	}
	t( 'start: rate limited after 20 per window', 'rate' === t_code( $last ) );
	t_browser();

	// Link start needs the signed-in, non-staff user with a recent session.
	$ls = t_user();
	lc_auth_mark_email_verified( $ls->ID, $ls->user_email );
	t( 'link start: signed out refused', 'forbidden' === t_code( lc_auth_oauth_start( 'google', 'link', '' ) ) );
	t_session_aged( $ls->ID, 31 * MINUTE_IN_SECONDS );
	t( 'link start: a 31 minute old session is refused (delete_recent)', 'delete_recent' === t_code( lc_auth_oauth_start( 'google', 'link', '' ) ) );
	$ed = t_user( 'editor' );
	t_session( $ed->ID );
	t( 'link start: staff refused (password_only)', 'password_only' === t_code( lc_auth_oauth_start( 'google', 'link', '' ) ) );
	t_session_aged( $ls->ID, 5 * MINUTE_IN_SECONDS );
	$bl = t_oauth_begin( 'google', 'link', '' );
	$rl = is_array( $bl ) ? lc_auth_store_get( 'oa', $bl['state'] ) : null;
	t( 'link start: a 5 minute old session works; the record names user and session', is_array( $bl ) && $rl && (int) $rl['u'] === $ls->ID && hash_equals( $rl['s'], lc_auth_session_hash() ), t_code( $bl ) );

	/* ---- id_token validation (direct) ----------------------------------- */
	$report( 'OAuth: id_token checks' );
	$nonce = lc_auth_new_token();
	$now   = time();
	$ok    = lc_auth_oauth_google_identity( t_jwt( t_claims( $nonce ) ), $cid_g, $nonce );
	t( 'id_token: a good token gives subject, verified email and name', is_array( $ok ) && 'google' === $ok['provider'] && 0 === strpos( $ok['subject'], 'lctest-' ) && $ok['email_verified'] && 'Tess' === $ok['name'], t_code( $ok ) );
	$bad = array(
		'wrong aud'                  => array( array( 'aud' => 'someone-else', 'azp' => null ), null, 'idt_aud' ),
		'aud list without azp'       => array( array( 'aud' => array( $cid_g, 'other' ), 'azp' => null ), null, 'idt_aud' ),
		'wrong azp'                  => array( array( 'azp' => 'someone-else' ), null, 'idt_azp' ),
		'wrong iss'                  => array( array( 'iss' => 'https://evil.example' ), null, 'idt_iss' ),
		'iss lookalike'              => array( array( 'iss' => 'https://accounts.google.com.evil.example' ), null, 'idt_iss' ),
		'wrong nonce'                => array( array( 'nonce' => lc_auth_new_token() ), null, 'idt_nonce' ),
		'missing nonce'              => array( array( 'nonce' => null ), null, 'idt_nonce' ),
		'expired (2 min ago)'        => array( array( 'exp' => $now - 120 ), null, 'idt_exp' ),
		'iat far in the future'      => array( array( 'iat' => $now + 400 ), null, 'idt_iat' ),
		'alg none'                   => array( array(), array( 'alg' => 'none' ), 'idt_alg' ),
		'alg HS256'                  => array( array(), array( 'alg' => 'HS256' ), 'idt_alg' ),
		'empty sub'                  => array( array( 'sub' => '' ), null, 'idt_sub' ),
		'sub of 256 characters'      => array( array( 'sub' => str_repeat( 'a', 256 ) ), null, 'idt_sub' ),
		'numeric sub (not a string)' => array( array( 'sub' => 12345 ), null, 'idt_sub' ),
	);
	foreach ( $bad as $name => $case ) {
		$claims = t_claims( $nonce, $case[0] );
		$jwt    = null === $case[1] ? t_jwt( $claims ) : t_jwt( $claims, $case[1] );
		$res    = lc_auth_oauth_google_identity( $jwt, $cid_g, $nonce );
		t( 'id_token rejected: ' . $name, $case[2] === t_code( $res ), t_code( $res ) );
	}
	t( 'id_token: iss without the scheme is accepted', is_array( lc_auth_oauth_google_identity( t_jwt( t_claims( $nonce, array( 'iss' => 'accounts.google.com' ) ) ), $cid_g, $nonce ) ) );
	t( 'id_token: an aud list containing the client, with matching azp, is accepted', is_array( lc_auth_oauth_google_identity( t_jwt( t_claims( $nonce, array( 'aud' => array( 'other', $cid_g ) ) ) ), $cid_g, $nonce ) ) );
	t( 'id_token: exp 30 s ago is inside the 60 s skew', is_array( lc_auth_oauth_google_identity( t_jwt( t_claims( $nonce, array( 'exp' => $now - 30 ) ) ), $cid_g, $nonce ) ) );
	t( 'id_token: email_verified "true" (string) counts as verified', true === lc_auth_oauth_google_identity( t_jwt( t_claims( $nonce, array( 'email_verified' => 'true' ) ) ), $cid_g, $nonce )['email_verified'] );
	$unv = lc_auth_oauth_google_identity( t_jwt( t_claims( $nonce, array( 'email_verified' => false ) ) ), $cid_g, $nonce );
	t( 'id_token: email_verified false is reported as unverified', is_array( $unv ) && false === $unv['email_verified'] );
	$unv2 = lc_auth_oauth_google_identity( t_jwt( t_claims( $nonce, array( 'email_verified' => 'false' ) ) ), $cid_g, $nonce );
	t( 'id_token: email_verified "false" (string) is unverified', is_array( $unv2 ) && false === $unv2['email_verified'] );
	t( 'id_token: garbage and wrong shapes rejected', is_wp_error( lc_auth_oauth_google_identity( 'x.y', $cid_g, $nonce ) ) && is_wp_error( lc_auth_oauth_google_identity( '', $cid_g, $nonce ) ) && is_wp_error( lc_auth_oauth_google_identity( 'a.b.c', $cid_g, $nonce ) ) );

	/* ---- callback checks end to end -------------------------------------- */
	$report( 'OAuth: callback checks' );
	t_browser();
	$f = t_g_flow( 'signin', array( 'nonce' => lc_auth_new_token() ) );
	t( 'callback: wrong nonce in the token refused, nobody signed in', 'oauth_failed' === t_code( $f['res'] ) && 0 === get_current_user_id() );
	t_browser();
	$f = t_g_flow( 'signin', array( 'aud' => 'someone-else', 'azp' => null ) );
	t( 'callback: wrong aud refused', 'oauth_failed' === t_code( $f['res'] ) && 0 === get_current_user_id() );
	t_browser();
	$f = t_g_flow( 'signin', array( 'exp' => time() - 600 ) );
	t( 'callback: expired token refused', 'oauth_failed' === t_code( $f['res'] ) && 0 === get_current_user_id() );
	t_browser();
	$f = t_g_flow( 'signin', array( 'iss' => 'https://evil.example' ) );
	t( 'callback: wrong iss in the token refused', 'oauth_failed' === t_code( $f['res'] ) );
	t_browser();
	$f = t_g_flow( 'signin', array(), array(), '', t_resp( 400, array( 'error' => 'invalid_grant' ) ) );
	t( 'callback: token endpoint error refused', 'oauth_failed' === t_code( $f['res'] ) && 0 === get_current_user_id() );
	t_browser();
	$f = t_g_flow( 'signin', array(), array(), '', t_resp( 200, array( 'access_token' => 'x' ) ) );
	t( 'callback: no id_token in the answer refused', 'oauth_failed' === t_code( $f['res'] ) );
	t_browser();
	$f = t_g_flow( 'signin', array(), array( 'iss' => 'https://evil.example' ) );
	t( 'callback: an iss query parameter that does not match is refused', 'oauth_failed' === t_code( $f['res'] ) && 0 === get_current_user_id() );
	t_browser();
	$f = t_g_flow( 'signin', array(), array( 'iss' => 'https://accounts.google.com' ) );
	t( 'callback: a matching iss query parameter is accepted', is_array( $f['res'] ), t_code( $f['res'] ) );

	// Replay: the state record is single use.
	t_browser();
	$b      = t_oauth_begin( 'google', 'signin', '' );
	$claims = t_claims( $b['nonce'] );
	$hf     = t_oauth_http( array( 'https://oauth2.googleapis.com/token' => t_resp( 200, array( 'id_token' => t_jwt( $claims ) ) ) ) );
	$cookie = $_COOKIE[ lc_auth_cookie_name( 'lcauth' ) ] ?? '';
	$r1     = lc_auth_oauth_callback( 'google', array( 'code' => 'c', 'state' => $b['state'] ) );
	t_track();
	t_browser( false );
	$_COOKIE[ lc_auth_cookie_name( 'lcauth' ) ] = $cookie; // The same browser flow cookie as the first use.
	$r2 = lc_auth_oauth_callback( 'google', array( 'code' => 'c', 'state' => $b['state'] ) );
	remove_filter( 'pre_http_request', $hf, 10 );
	t( 'callback: first use works, a replay of the same state is refused', is_array( $r1 ) && 'oauth_failed' === t_code( $r2 ), t_code( $r1 ) . '/' . t_code( $r2 ) );
	t( 'callback: unknown, malformed or missing state refused', 'oauth_failed' === t_code( lc_auth_oauth_callback( 'google', array( 'code' => 'c', 'state' => lc_auth_new_token() ) ) ) && 'oauth_failed' === t_code( lc_auth_oauth_callback( 'google', array( 'code' => 'c', 'state' => 'short' ) ) ) && 'oauth_failed' === t_code( lc_auth_oauth_callback( 'google', array() ) ) );

	// A state from Google's flow cannot be redeemed on Discord's callback.
	t_browser();
	$b  = t_oauth_begin( 'google', 'signin', '' );
	$hf = t_oauth_http( array( 'https://discord.com' => t_resp( 200, array( 'access_token' => 'x', 'id' => '99999123456789012' ) ) ) );
	$n0 = count( $GLOBALS['lc_t_http'] );
	$r  = lc_auth_oauth_callback( 'discord', array( 'code' => 'c', 'state' => $b['state'] ) );
	remove_filter( 'pre_http_request', $hf, 10 );
	t( 'callback: wrong provider (mix-up) refused, no HTTP call made', 'oauth_failed' === t_code( $r ) && 0 === get_current_user_id() && count( $GLOBALS['lc_t_http'] ) === $n0 );
	t( 'callback: a mix-up burns the state', null === lc_auth_store_get( 'oa', $b['state'] ) );

	// Flow cookie mismatch (login CSRF).
	t_browser();
	$b = t_oauth_begin( 'google', 'signin', '' );
	t_browser( false ); // A different browser flow cookie.
	$hf = t_oauth_http( array( 'https://oauth2.googleapis.com/token' => t_resp( 200, array( 'id_token' => t_jwt( t_claims( $b['nonce'] ) ) ) ) ) );
	$n0 = count( $GLOBALS['lc_t_http'] );
	$r  = lc_auth_oauth_callback( 'google', array( 'code' => 'c', 'state' => $b['state'] ) );
	remove_filter( 'pre_http_request', $hf, 10 );
	t( 'callback: flow cookie mismatch refused before any token call', 'oauth_failed' === t_code( $r ) && 0 === get_current_user_id() && count( $GLOBALS['lc_t_http'] ) === $n0 );
	t_browser();
	$b = t_oauth_begin( 'google', 'signin', '' );
	unset( $_COOKIE[ lc_auth_cookie_name( 'lcauth' ) ] );
	t( 'callback: no flow cookie at all refused', 'oauth_failed' === t_code( lc_auth_oauth_callback( 'google', array( 'code' => 'c', 'state' => $b['state'] ) ) ) );

	// The person cancelled at the provider.
	t_browser();
	$b = t_oauth_begin( 'google', 'signin', home_url( '/courses/' ) );
	$r = lc_auth_oauth_callback( 'google', array( 'error' => 'access_denied', 'state' => $b['state'] ) );
	t( 'callback: error=access_denied gives oauth_cancelled, keeps the redirect, burns the state', 'oauth_cancelled' === t_code( $r ) && home_url( '/courses/' ) === ( $r->get_error_data()['redirect'] ?? '' ) && null === lc_auth_store_get( 'oa', $b['state'] ) );
	t( 'callback: error with no state still gives oauth_cancelled', 'oauth_cancelled' === t_code( lc_auth_oauth_callback( 'google', array( 'error' => 'access_denied' ) ) ) );

	// Callback rate limit.
	t_browser();
	$r = null;
	for ( $i = 0; $i < 31; $i++ ) {
		$r = lc_auth_oauth_callback( 'google', array( 'code' => 'c', 'state' => 'x' ) );
	}
	t( 'callback: rate limited after 30 per window', 'rate' === t_code( $r ) );

	// Redirect handling.
	t_browser();
	$f = t_g_flow( 'signin', array(), array(), 'https://evil.example/phish' );
	t( 'redirect: an off-site redirect_to falls back to /account/', is_array( $f['res'] ) && lc_auth_account_url() === $f['res']['redirect'], t_code( $f['res'] ) );
	t_browser();
	$f = t_g_flow( 'signin', array(), array(), home_url( '/courses/' ) );
	t( 'redirect: a same-site redirect_to is kept', is_array( $f['res'] ) && home_url( '/courses/' ) === $f['res']['redirect'], t_code( $f['res'] ) );
	t_browser();
	$f = t_g_flow( 'signin', array(), array(), home_url( '/account/email-link/?t=abc' ) );
	t( 'redirect: our own flow pages are not valid destinations', is_array( $f['res'] ) && lc_auth_account_url() === $f['res']['redirect'] );

	/* ---- Google sign-in matrix ------------------------------------------ */
	$report( 'OAuth: Google sign-in matrix' );
	t_browser();
	$users_before = (int) count_users()['total_users'];
	$email_new    = 'oa' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	$f            = t_g_flow( 'signin', array( 'email' => $email_new, 'hd' => 'lc-test.invalid' ) ); // hd present: Google is authoritative for the address.
	$nu           = get_user_by( 'email', $email_new );
	t( 'no match + authoritative: a new member is created and signed in', is_array( $f['res'] ) && $f['res']['new_account'] && $nu && get_current_user_id() === $nu->ID, t_code( $f['res'] ) );
	t( 'new member: verified email, provider name, subscriber, subject stored with a masked hint', $nu && lc_auth_email_verified( $nu ) && 'Tess' === $nu->display_name && array( 'subscriber' ) === $nu->roles && $f['claims']['sub'] === get_user_meta( $nu->ID, 'lc_idp_google', true ) && lc_auth_mask_email( $email_new ) === ( get_user_meta( $nu->ID, 'lc_idp_info', true )['google']['hint'] ?? '' ) );
	t( 'new member: methods = email + idp:google', $nu && array( 'email', 'idp:google' ) === lc_auth_methods( $nu ) );
	t( 'new member: login is random (no email, no subject)', $nu && 0 === strpos( $nu->user_login, 'm_' ) && false === strpos( $nu->user_login, 'oa' ) );
	t( 'no match: exactly one user was added', (int) count_users()['total_users'] === $users_before + 1 );

	// Existing identity.
	t_browser();
	$f2 = t_g_flow( 'signin', array( 'sub' => $f['claims']['sub'], 'email' => $email_new ) );
	t( 'existing identity: signs in as that account, no new user', is_array( $f2['res'] ) && ! $f2['res']['new_account'] && $nu && get_current_user_id() === $nu->ID && (int) count_users()['total_users'] === $users_before + 1, t_code( $f2['res'] ) );
	t_browser();
	$f2 = t_g_flow( 'signin', array( 'sub' => $f['claims']['sub'], 'email' => 'changed' . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid' ) );
	t( 'existing identity: a changed provider email does not matter (the key is the subject)', is_array( $f2['res'] ) && $nu && get_current_user_id() === $nu->ID );

	// Existing identity marks a matching, still-unverified local email verified.
	t_browser();
	$eu = t_user();
	update_user_meta( $eu->ID, 'lc_idp_google', 'lctest-ev-' . $eu->ID );
	t( 'precondition: local email unverified', ! lc_auth_email_verified( $eu ) );
	$f2 = t_g_flow( 'signin', array( 'sub' => 'lctest-ev-' . $eu->ID, 'email' => $eu->user_email, 'hd' => 'lc-test.invalid' ) );
	t( 'existing identity + authoritative matching email: the email becomes verified', is_array( $f2['res'] ) && get_current_user_id() === $eu->ID && lc_auth_email_verified( $eu->ID ), t_code( $f2['res'] ) );
	t_browser();
	$eu2 = t_user();
	update_user_meta( $eu2->ID, 'lc_idp_google', 'lctest-ev2-' . $eu2->ID );
	$f2 = t_g_flow( 'signin', array( 'sub' => 'lctest-ev2-' . $eu2->ID, 'email' => 'other' . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid' ) );
	t( 'existing identity + a different provider email: the local email stays unverified', is_array( $f2['res'] ) && get_current_user_id() === $eu2->ID && ! lc_auth_email_verified( $eu2->ID ) );
	t_browser();
	$eu3 = t_user();
	update_user_meta( $eu3->ID, 'lc_idp_google', 'lctest-ev3-' . $eu3->ID );
	$f2 = t_g_flow( 'signin', array( 'sub' => 'lctest-ev3-' . $eu3->ID, 'email' => $eu3->user_email ) ); // Verified but not authoritative (no hd, not Gmail).
	t( 'existing identity + NON-authoritative matching email: still signs in directly, email stays unverified', is_array( $f2['res'] ) && get_current_user_id() === $eu3->ID && ! lc_auth_email_verified( $eu3->ID ), t_code( $f2['res'] ) );

	// Staff with an identity are refused.
	t_browser();
	$st = t_user( 'editor' );
	update_user_meta( $st->ID, 'lc_idp_google', 'lctest-staff-' . $st->ID );
	$f2 = t_g_flow( 'signin', array( 'sub' => 'lctest-staff-' . $st->ID, 'email' => $st->user_email ) );
	t( 'existing identity on a staff account: refused (password_only), not signed in', 'password_only' === t_code( $f2['res'] ) && 0 === get_current_user_id() );

	// Auto-link: provider email verified AND local email verified.
	t_browser();
	$al = t_user();
	lc_auth_mark_email_verified( $al->ID, $al->user_email );
	lc_auth_dev_outbox_clear();
	$f2 = t_g_flow( 'signin', array( 'email' => $al->user_email, 'hd' => 'lc-test.invalid' ) );
	t( 'auto-link: verified local + authoritative provider email -> linked, signed in as the existing account', is_array( $f2['res'] ) && ! $f2['res']['new_account'] && get_current_user_id() === $al->ID && $f2['claims']['sub'] === get_user_meta( $al->ID, 'lc_idp_google', true ), t_code( $f2['res'] ) );
	t( 'auto-link: the verified address is notified once', 1 === t_outbox_count( $al->user_email ) );
	t( 'auto-link: methods now include idp:google', in_array( 'idp:google', lc_auth_methods( get_userdata( $al->ID ) ), true ) );

	// Provider email unverified: no auto-link, no account takeover.
	t_browser();
	$un = t_user();
	lc_auth_mark_email_verified( $un->ID, $un->user_email );
	$f2 = t_g_flow( 'signin', array( 'email' => $un->user_email, 'email_verified' => false ) );
	$nm = get_current_user_id();
	t( 'provider email unverified: not linked to the existing account', '' === (string) get_user_meta( $un->ID, 'lc_idp_google', true ) && $nm !== $un->ID );
	t( 'provider email unverified: a fresh member with no email is created instead', is_array( $f2['res'] ) && $f2['res']['new_account'] && '' === get_userdata( $nm )->user_email && ! lc_auth_email_verified( $nm ) );

	// Local email unverified: refuse and say how to proceed.
	t_browser();
	$lu = t_user();
	$f2 = t_g_flow( 'signin', array( 'email' => $lu->user_email, 'hd' => 'lc-test.invalid' ) );
	t( 'local email unverified (authoritative provider): refused (oauth_email_exists), nothing linked, not signed in', 'oauth_email_exists' === t_code( $f2['res'] ) && 0 === get_current_user_id() && '' === (string) get_user_meta( $lu->ID, 'lc_idp_google', true ) );
	t( 'oauth_email_exists has its own wording that points to the email link', lc_auth_text( 'oauth_email_exists' ) !== lc_auth_text( 'unknown' ) && false !== stripos( lc_auth_text( 'oauth_email_exists' ), 'email link' ) );

	// Staff by email (drop the identity the earlier staff case gave them, so "nothing linked" is checkable).
	delete_user_meta( $st->ID, 'lc_idp_google' );
	t_browser();
	$f2 = t_g_flow( 'signin', array( 'email' => $st->user_email, 'hd' => 'lc-test.invalid' ) );
	t( 'staff account with that email: refused (password_only), nothing linked', 'password_only' === t_code( $f2['res'] ) && 0 === get_current_user_id() && '' === (string) get_user_meta( $st->ID, 'lc_idp_google', true ), (string) t_code( $f2['res'] ) );
	lc_auth_mark_email_verified( $st->ID, $st->user_email );
	t_browser();
	$f2 = t_g_flow( 'signin', array( 'email' => $st->user_email, 'hd' => 'lc-test.invalid' ) );
	t( 'staff account with a VERIFIED matching email: still refused', 'password_only' === t_code( $f2['res'] ) && '' === (string) get_user_meta( $st->ID, 'lc_idp_google', true ), (string) t_code( $f2['res'] ) );

	// Existing account already has a different Google identity.
	t_browser();
	$hs = t_user();
	lc_auth_mark_email_verified( $hs->ID, $hs->user_email );
	update_user_meta( $hs->ID, 'lc_idp_google', 'lctest-other-' . $hs->ID );
	$f2 = t_g_flow( 'signin', array( 'email' => $hs->user_email, 'hd' => 'lc-test.invalid' ) );
	t( 'auto-link refused when the account already has another Google identity', 'idp_has_other' === t_code( $f2['res'] ) && 'lctest-other-' . $hs->ID === get_user_meta( $hs->ID, 'lc_idp_google', true ) && 0 === get_current_user_id() );

	// Integrity: the same subject on two accounts.
	t_browser();
	$d1 = t_user();
	$d2 = t_user();
	update_user_meta( $d1->ID, 'lc_idp_google', 'lctest-dup-' . $d1->ID );
	update_user_meta( $d2->ID, 'lc_idp_google', 'lctest-dup-' . $d1->ID );
	$f2 = t_g_flow( 'signin', array( 'sub' => 'lctest-dup-' . $d1->ID ) );
	t( 'integrity: one subject on two accounts is refused (idp_conflict)', 'idp_conflict' === t_code( $f2['res'] ) && 0 === get_current_user_id() );
	update_user_meta( $d2->ID, 'lc_merged_into', $d1->ID );
	t_browser();
	$f2 = t_g_flow( 'signin', array( 'sub' => 'lctest-dup-' . $d1->ID ) );
	t( 'integrity: a merged-away holder is ignored', is_array( $f2['res'] ) && get_current_user_id() === $d1->ID );

	// Sign-up switched off: new identities refused, existing ones still in.
	t_browser();
	add_filter( 'lc_auth_signup_enabled', '__return_false' );
	$f2 = t_g_flow( 'signin' );
	$f3 = t_g_flow( 'signin', array( 'sub' => $f['claims']['sub'], 'email' => $email_new ) );
	remove_filter( 'lc_auth_signup_enabled', '__return_false' );
	t( 'sign-up closed: a new identity is refused (signup_closed); a known one still signs in', 'signup_closed' === t_code( $f2['res'] ) && is_array( $f3['res'] ) );

	// Signing in while signed in as someone else ends the old session.
	t_browser();
	$other = t_user();
	t_session( $other->ID );
	$old_token = wp_get_session_token();
	$f2        = t_g_flow( 'signin', array( 'sub' => $f['claims']['sub'], 'email' => $email_new ) );
	t( 'switching accounts: signed in as the identity owner; the previous session is destroyed', is_array( $f2['res'] ) && get_current_user_id() === $nu->ID && null === WP_Session_Tokens::get_instance( $other->ID )->get( $old_token ) );

	/* ---- authoritative vs not (connect once, then automatic) ------------- */
	$report( 'OAuth: email authority (Gmail / hd / Discord)' );
	$gm_set = function ( WP_User $u ) {
		$new = 'lctest.' . bin2hex( random_bytes( 4 ) ) . '@gmail.com';
		wp_update_user( array( 'ID' => $u->ID, 'user_email' => $new ) );
		return get_userdata( $u->ID );
	};
	$nonce_x = lc_auth_new_token();
	$ident   = function ( array $over ) use ( $nonce_x, $cid_g ) {
		return lc_auth_oauth_google_identity( t_jwt( t_claims( $nonce_x, $over ) ), $cid_g, $nonce_x );
	};
	t( 'authority: verified @gmail.com is authoritative', true === $ident( array( 'email' => 'someone@gmail.com' ) )['email_authoritative'] );
	t( 'authority: verified @googlemail.com is authoritative', true === $ident( array( 'email' => 'someone@googlemail.com' ) )['email_authoritative'] );
	t( 'authority: UNverified @gmail.com is not', false === $ident( array( 'email' => 'someone@gmail.com', 'email_verified' => false ) )['email_authoritative'] );
	t( 'authority: verified non-Gmail without hd is not', false === $ident( array() )['email_authoritative'] );
	t( 'authority: verified non-Gmail with a non-empty hd is (hd is not compared to the domain)', true === $ident( array( 'hd' => 'unrelated.example' ) )['email_authoritative'] );
	t( 'authority: empty or non-string hd does not count', false === $ident( array( 'hd' => '' ) )['email_authoritative'] && false === $ident( array( 'hd' => true ) )['email_authoritative'] );
	t( 'authority: a lookalike gmail domain does not count', false === $ident( array( 'email' => 'x@notgmail.com' ) )['email_authoritative'] && false === $ident( array( 'email' => 'x@gmail.com.evil.example' ) )['email_authoritative'] );

	t_browser();
	$gv = $gm_set( t_user() );
	lc_auth_mark_email_verified( $gv->ID, $gv->user_email );
	$f2 = t_g_flow( 'signin', array( 'email' => $gv->user_email ) );
	t( 'google @gmail.com verified: auto-links to the verified local account and signs in', is_array( $f2['res'] ) && get_current_user_id() === $gv->ID && $f2['claims']['sub'] === get_user_meta( $gv->ID, 'lc_idp_google', true ), t_code( $f2['res'] ) );

	t_browser();
	$nv = t_user();
	lc_auth_mark_email_verified( $nv->ID, $nv->user_email );
	$f2 = t_g_flow( 'signin', array( 'email' => $nv->user_email ) ); // Non-Gmail, no hd.
	t( 'google non-Gmail without hd + existing verified account: oauth_email_exists, nothing linked', 'oauth_email_exists' === t_code( $f2['res'] ) && '' === (string) get_user_meta( $nv->ID, 'lc_idp_google', true ) && 0 === get_current_user_id() );
	t_browser();
	$nu2 = t_user();
	$f2  = t_g_flow( 'signin', array( 'email' => $nu2->user_email ) );
	t( 'google non-Gmail without hd + existing UNverified account: oauth_email_exists too', 'oauth_email_exists' === t_code( $f2['res'] ) && '' === (string) get_user_meta( $nu2->ID, 'lc_idp_google', true ) );
	t_browser();
	$free_e = 'oa' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	$f2     = t_g_flow( 'signin', array( 'email' => $free_e ) );
	$nm2    = get_current_user_id();
	t( 'google non-Gmail without hd + no account: a member is created WITHOUT an email', is_array( $f2['res'] ) && $f2['res']['new_account'] && $nm2 && '' === get_userdata( $nm2 )->user_email && false === get_user_by( 'email', $free_e ) && $f2['claims']['sub'] === get_user_meta( $nm2, 'lc_idp_google', true ), t_code( $f2['res'] ) );

	t_browser();
	$hv = t_user();
	lc_auth_mark_email_verified( $hv->ID, $hv->user_email );
	$f2 = t_g_flow( 'signin', array( 'email' => $hv->user_email, 'hd' => 'lc-test.invalid' ) );
	t( 'google with hd: authoritative, auto-links to the verified account', is_array( $f2['res'] ) && get_current_user_id() === $hv->ID && $f2['claims']['sub'] === get_user_meta( $hv->ID, 'lc_idp_google', true ), t_code( $f2['res'] ) );

	t_browser();
	$gi = $gm_set( t_user() );
	update_user_meta( $gi->ID, 'lc_idp_google', 'lctest-linked-' . $gi->ID );
	$other_e = 'oa' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	$f2      = t_g_flow( 'signin', array( 'sub' => 'lctest-linked-' . $gi->ID, 'email' => $other_e ) );
	t( 'identity already linked: signs in directly regardless of the email (non-authoritative too)', is_array( $f2['res'] ) && get_current_user_id() === $gi->ID, t_code( $f2['res'] ) );

	/* ---- Discord ---------------------------------------------------------- */
	$report( 'OAuth: Discord' );
	t_browser();
	$de          = 'od' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	$http_before = count( $GLOBALS['lc_t_http'] );
	$d           = t_d_flow( 'signin', array( 'email' => $de ) );
	$du          = get_user_by( 'email', $de );
	$du = ! empty( $d['profile']['id'] ) ? get_userdata( (int) ( lc_auth_idp_owners( 'discord', $d['profile']['id'] )[0] ?? 0 ) ) : false;
	t( 'discord: new member (verified email, but Discord is never authoritative) has NO email, signed in', is_array( $d['res'] ) && $d['res']['new_account'] && $du && get_current_user_id() === $du->ID && '' === $du->user_email && false === get_user_by( 'email', $de ), t_code( $d['res'] ) );
	t( 'discord: display name from global_name; identity stored under lc_idp_discord; masked hint kept', $du && 'Tess D' === $du->display_name && $d['profile']['id'] === get_user_meta( $du->ID, 'lc_idp_discord', true ) && lc_auth_mask_email( $de ) === ( get_user_meta( $du->ID, 'lc_idp_info', true )['discord']['hint'] ?? '' ) );
	$reqs = array_slice( $GLOBALS['lc_t_http'], $http_before );
	$urls = array_column( $reqs, 'url' );
	t( 'discord: token, profile and revoke were called, in that order', array( 'https://discord.com/api/oauth2/token', 'https://discord.com/api/v10/users/@me', 'https://discord.com/api/oauth2/token/revoke' ) === $urls, implode( ' ', $urls ) );
	$tok = $reqs[0]['args'] ?? array();
	t( 'discord token call: form body, TLS verified, 10 s timeout, code_verifier matches the challenge', ( $tok['sslverify'] ?? null ) === true && 10 === (int) ( $tok['timeout'] ?? 0 ) && 'authorization_code' === ( $tok['body']['grant_type'] ?? '' ) && lc_auth_oauth_redirect_uri( 'discord' ) === ( $tok['body']['redirect_uri'] ?? '' ) && lc_auth_b64url( hash( 'sha256', (string) ( $tok['body']['code_verifier'] ?? '' ), true ) ) === $d['b']['q']['code_challenge'] );
	t( 'discord profile call sends the bearer token', 'Bearer at-d' === ( $reqs[1]['args']['headers']['Authorization'] ?? '' ) );
	t( 'discord revoke call carries the token', 'at-d' === ( $reqs[2]['args']['body']['token'] ?? '' ) );

	t_browser();
	$d  = t_d_flow( 'signin', array( 'email' => null ) );
	$nm = get_current_user_id();
	t( 'discord null email: a member with no email is created and signed in', is_array( $d['res'] ) && $d['res']['new_account'] && $nm && '' === get_userdata( $nm )->user_email && array( 'idp:discord' ) === lc_auth_methods( get_userdata( $nm ) ), t_code( $d['res'] ) );
	t( 'discord null email: no masked hint is stored', '' === ( get_user_meta( $nm, 'lc_idp_info', true )['discord']['hint'] ?? 'x' ) );
	t( 'last-method guard: a Discord-only account cannot remove Discord', ! lc_auth_can_remove( get_userdata( $nm ), 'idp:discord' ) );

	t_browser();
	$ue = 'od' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	$d  = t_d_flow( 'signin', array( 'email' => $ue, 'verified' => false ) );
	$nm = get_current_user_id();
	t( 'discord unverified email: a member is created WITHOUT that email', is_array( $d['res'] ) && $nm && '' === get_userdata( $nm )->user_email && false === get_user_by( 'email', $ue ) );

	// Discord is never authoritative: an existing account with that email is refused, verified or not.
	t_browser();
	$da = t_user();
	lc_auth_mark_email_verified( $da->ID, $da->user_email );
	lc_auth_dev_outbox_clear();
	$d = t_d_flow( 'signin', array( 'email' => $da->user_email ) );
	t( 'discord verified=true + verified local email: oauth_email_exists, nothing linked, nobody notified, not signed in', 'oauth_email_exists' === t_code( $d['res'] ) && '' === (string) get_user_meta( $da->ID, 'lc_idp_discord', true ) && 0 === t_outbox_count( $da->user_email ) && 0 === get_current_user_id() );
	t_browser();
	$da2 = t_user();
	$d   = t_d_flow( 'signin', array( 'email' => $da2->user_email ) );
	t( 'discord verified=true + UNverified local email: oauth_email_exists too', 'oauth_email_exists' === t_code( $d['res'] ) && '' === (string) get_user_meta( $da2->ID, 'lc_idp_discord', true ) );
	t_browser();
	$dl = t_user();
	update_user_meta( $dl->ID, 'lc_idp_discord', '99999000000' . $dl->ID );
	$d = t_d_flow( 'signin', array( 'id' => '99999000000' . $dl->ID, 'email' => $da->user_email ) );
	t( 'discord: an already-linked identity signs in directly whatever its email says', is_array( $d['res'] ) && get_current_user_id() === $dl->ID, t_code( $d['res'] ) );

	t_browser();
	$d = t_d_flow( 'signin', array( 'id' => 'not-digits' ) );
	t( 'discord: a non-numeric id is rejected', 'oauth_failed' === t_code( $d['res'] ) && 0 === get_current_user_id() );
	t_browser();
	$bd = t_oauth_begin( 'discord', 'signin', '' );
	$hf = t_oauth_http( array( 'https://discord.com/api/oauth2/token/revoke' => t_resp( 200, '{}' ), 'https://discord.com/api/oauth2/token' => t_resp( 200, array( 'access_token' => 'at' ) ), 'https://discord.com/api/v10/users/@me' => t_resp( 401, array( 'message' => '401: Unauthorized' ) ) ) );
	$r  = lc_auth_oauth_callback( 'discord', array( 'code' => 'c', 'state' => $bd['state'] ) );
	remove_filter( 'pre_http_request', $hf, 10 );
	t( 'discord: the profile call failing is refused', 'oauth_failed' === t_code( $r ) && 0 === get_current_user_id() );
	t_browser();
	$bd = t_oauth_begin( 'discord', 'signin', '' );
	$r  = lc_auth_oauth_callback( 'discord', array( 'code' => 'c', 'state' => $bd['state'], 'iss' => 'https://evil.example' ) );
	t( 'discord: an iss parameter mismatch is refused', 'oauth_failed' === t_code( $r ) );
	t_browser();
	$bd = t_oauth_begin( 'discord', 'signin', '' );
	$hf = t_oauth_http( array( 'https://discord.com' => new WP_Error( 'http_request_failed', 'timeout' ) ) );
	$r  = lc_auth_oauth_callback( 'discord', array( 'code' => 'c', 'state' => $bd['state'] ) );
	remove_filter( 'pre_http_request', $hf, 10 );
	t( 'discord: a network failure on the token call is refused', 'oauth_failed' === t_code( $r ) );

	/* ---- linking from the account page ---------------------------------- */
	$report( 'OAuth: linking, unlinking' );
	t_browser();
	$lk = t_user();
	lc_auth_mark_email_verified( $lk->ID, $lk->user_email );
	t_session( $lk->ID );
	lc_auth_dev_outbox_clear();
	$f2 = t_g_flow( 'link', array( 'email' => 'x' . bin2hex( random_bytes( 3 ) ) . '@lc-test.invalid' ) );
	t( 'link: connects Google to the signed-in account and lands on /account/ with a notice', is_array( $f2['res'] ) && $f2['claims']['sub'] === get_user_meta( $lk->ID, 'lc_idp_google', true ) && add_query_arg( 'done', 'idp_linked', lc_auth_account_url() ) === $f2['res']['redirect'], t_code( $f2['res'] ) );
	t( 'link: still the same user, and the verified address is notified', get_current_user_id() === $lk->ID && 1 === t_outbox_count( $lk->user_email ) );
	t( 'link: info row has linked/used timestamps and a masked hint', ! empty( get_user_meta( $lk->ID, 'lc_idp_info', true )['google']['linked'] ) && false !== strpos( (string) get_user_meta( $lk->ID, 'lc_idp_info', true )['google']['hint'], '•••@' ) );
	t( 'link: methods = email + idp:google', array( 'email', 'idp:google' ) === lc_auth_methods( get_userdata( $lk->ID ) ) );
	t( 'link: the local email is left alone when the account already has one', $lk->user_email === get_userdata( $lk->ID )->user_email );

	lc_auth_dev_outbox_clear();
	$f3 = t_g_flow( 'link', array( 'sub' => $f2['claims']['sub'] ) );
	t( 'link: the same subject again is a no-op (no second notice)', is_array( $f3['res'] ) && $f2['claims']['sub'] === get_user_meta( $lk->ID, 'lc_idp_google', true ) && 0 === t_outbox_count( $lk->user_email ), t_code( $f3['res'] ) );
	$f3 = t_g_flow( 'link', array( 'sub' => 'lctest-second-' . $lk->ID ) );
	t( 'link: a different Google account while one is connected is refused (disconnect first)', 'idp_has_other' === t_code( $f3['res'] ) && $f2['claims']['sub'] === get_user_meta( $lk->ID, 'lc_idp_google', true ) );

	t_browser();
	$lb = t_user();
	t_session( $lb->ID );
	$f3 = t_g_flow( 'link', array( 'sub' => $f2['claims']['sub'] ) );
	t( 'link conflict: a subject held by another account is refused (idp_in_use), nothing merged', 'idp_in_use' === t_code( $f3['res'] ) && '' === (string) get_user_meta( $lb->ID, 'lc_idp_google', true ) && $f2['claims']['sub'] === get_user_meta( $lk->ID, 'lc_idp_google', true ) );

	t_browser();
	$ln = t_user( 'subscriber', false );
	t_session( $ln->ID );
	$free = 'oa' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid';
	$f3   = t_g_flow( 'link', array( 'email' => $free, 'hd' => 'lc-test.invalid' ) );
	$ln2  = get_userdata( $ln->ID );
	t( 'link sets a missing email when the provider is authoritative and the address free (and verifies it)', is_array( $f3['res'] ) && $free === $ln2->user_email && lc_auth_email_verified( $ln2 ) && add_query_arg( 'done', 'idp_email_set', lc_auth_account_url() ) === $f3['res']['redirect'], t_code( $f3['res'] ) );
	t_browser();
	$ln = t_user( 'subscriber', false );
	t_session( $ln->ID );
	$taken = t_user();
	$f3    = t_g_flow( 'link', array( 'email' => $taken->user_email, 'hd' => 'lc-test.invalid' ) );
	t( 'link: a provider email that belongs to someone else is not copied (still linked)', is_array( $f3['res'] ) && '' === get_userdata( $ln->ID )->user_email && $f3['claims']['sub'] === get_user_meta( $ln->ID, 'lc_idp_google', true ) );
	t_browser();
	$ln = t_user( 'subscriber', false );
	t_session( $ln->ID );
	$f3 = t_g_flow( 'link', array( 'email' => 'oa' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid', 'email_verified' => false ) );
	t_browser();
	$ln = t_user( 'subscriber', false );
	t_session( $ln->ID );
	$f3 = t_g_flow( 'link', array( 'email' => 'oa' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid' ) ); // Verified, but not authoritative.
	t( 'link: a verified but NON-authoritative provider email is never copied', is_array( $f3['res'] ) && '' === get_userdata( $ln->ID )->user_email );
	t_browser();
	$ln = t_user( 'subscriber', false );
	t_session( $ln->ID );
	$f3 = t_g_flow( 'link', array( 'email' => 'oa' . bin2hex( random_bytes( 4 ) ) . '@lc-test.invalid', 'email_verified' => false ) );
	t( 'link: an unverified provider email is never copied', is_array( $f3['res'] ) && '' === get_userdata( $ln->ID )->user_email );

	// The link callback must come back to the same user and session.
	t_browser();
	$u1 = t_user();
	$u2 = t_user();
	t_session( $u1->ID );
	$b = t_oauth_begin( 'google', 'link', '' );
	t_session( $u2->ID ); // Another user in the same browser.
	$hf = t_oauth_http( array( 'https://oauth2.googleapis.com/token' => t_resp( 200, array( 'id_token' => t_jwt( t_claims( $b['nonce'] ) ) ) ) ) );
	$r  = lc_auth_oauth_callback( 'google', array( 'code' => 'c', 'state' => $b['state'] ) );
	remove_filter( 'pre_http_request', $hf, 10 );
	t( 'link callback: a different signed-in user is refused', 'oauth_failed' === t_code( $r ) && '' === (string) get_user_meta( $u2->ID, 'lc_idp_google', true ) );
	t_browser();
	t_session( $u1->ID );
	$b = t_oauth_begin( 'google', 'link', '' );
	t_session( $u1->ID ); // Same user, a new session token.
	$hf = t_oauth_http( array( 'https://oauth2.googleapis.com/token' => t_resp( 200, array( 'id_token' => t_jwt( t_claims( $b['nonce'] ) ) ) ) ) );
	$r  = lc_auth_oauth_callback( 'google', array( 'code' => 'c', 'state' => $b['state'] ) );
	remove_filter( 'pre_http_request', $hf, 10 );
	t( 'link callback: the same user on a different session is refused', 'oauth_failed' === t_code( $r ) && '' === (string) get_user_meta( $u1->ID, 'lc_idp_google', true ) );
	t_browser();
	t_session( $u1->ID );
	$b = t_oauth_begin( 'google', 'link', '' );
	unset( $_COOKIE[ LOGGED_IN_COOKIE ] );
	wp_set_current_user( 0 );
	$hf = t_oauth_http( array( 'https://oauth2.googleapis.com/token' => t_resp( 200, array( 'id_token' => t_jwt( t_claims( $b['nonce'] ) ) ) ) ) );
	$r  = lc_auth_oauth_callback( 'google', array( 'code' => 'c', 'state' => $b['state'] ) );
	remove_filter( 'pre_http_request', $hf, 10 );
	t( 'link callback: signed out in between is refused (never turns into a sign-in)', 'oauth_failed' === t_code( $r ) && 0 === get_current_user_id() );

	// A sign-in state never links to whoever happens to be signed in.
	t_browser();
	$u3 = t_user();
	$b  = t_oauth_begin( 'google', 'signin', '' );
	t_session( $u3->ID );
	$hf = t_oauth_http( array( 'https://oauth2.googleapis.com/token' => t_resp( 200, array( 'id_token' => t_jwt( t_claims( $b['nonce'] ) ) ) ) ) );
	$r  = lc_auth_oauth_callback( 'google', array( 'code' => 'c', 'state' => $b['state'] ) );
	remove_filter( 'pre_http_request', $hf, 10 );
	t_track();
	t( 'a sign-in state never links to the account that happens to be signed in', is_array( $r ) && '' === (string) get_user_meta( $u3->ID, 'lc_idp_google', true ) && get_current_user_id() !== $u3->ID );

	// Unlink and the last-method guard.
	t_browser();
	t( 'unlink: allowed while another method remains (verified email)', lc_auth_can_remove( get_userdata( $lk->ID ), 'idp:google' ) );
	lc_auth_dev_outbox_clear();
	lc_auth_idp_remove( $lk->ID, 'google' );
	lc_auth_idp_notify( get_userdata( $lk->ID ), 'google', 'idp_unlinked' );
	t( 'unlink: identity and info are gone, the address is told', '' === (string) get_user_meta( $lk->ID, 'lc_idp_google', true ) && ! isset( get_user_meta( $lk->ID, 'lc_idp_info', true )['google'] ) && 1 === t_outbox_count( $lk->user_email ) );
	t( 'unlink: no longer a method', ! in_array( 'idp:google', lc_auth_methods( get_userdata( $lk->ID ) ), true ) );
	$only = t_user();
	update_user_meta( $only->ID, 'lc_idp_google', 'lctest-only-' . $only->ID );
	t( 'last-method guard: a Google-only account (unverified email) cannot unlink Google', ! lc_auth_can_remove( get_userdata( $only->ID ), 'idp:google' ) && array( 'idp:google' ) === lc_auth_methods( get_userdata( $only->ID ) ) );
	$wl = t_wallet();
	add_user_meta( $only->ID, 'lc_wallet', $wl['stake'] );
	t( 'last-method guard: with a wallet as well, Google can be unlinked', lc_auth_can_remove( get_userdata( $only->ID ), 'idp:google' ) && lc_auth_can_remove( get_userdata( $only->ID ), 'wallet:' . $wl['stake'] ) );
	$ghost = t_user();
	t( 'last-method guard: a provider that is not linked is not a method', ! lc_auth_can_remove( get_userdata( $ghost->ID ), 'idp:discord' ) );

	// Last-method guard counts only USABLE methods (a switched-off provider is no way in).
	$gd = t_user();
	lc_auth_mark_email_verified( $gd->ID, $gd->user_email );
	update_user_meta( $gd->ID, 'lc_idp_discord', 'lctest-guard-' . $gd->ID );
	t( 'usable guard: with Discord enabled, email and Discord can each be removed', lc_auth_can_remove( get_userdata( $gd->ID ), 'idp:discord' ) && lc_auth_can_remove( get_userdata( $gd->ID ), 'email' ) );
	$off_d = fn( $on, $id ) => 'discord' === $id ? false : $on;
	add_filter( 'lc_auth_provider_enabled', $off_d, 10, 2 );
	t( 'usable guard: Discord switched off -> it no longer counts, so the email cannot be removed', ! lc_auth_can_remove( get_userdata( $gd->ID ), 'email' ) && array( 'email' ) === lc_auth_usable_methods( get_userdata( $gd->ID ) ) );
	t( 'usable guard: lc_auth_methods() still lists the dormant identity', in_array( 'idp:discord', lc_auth_methods( get_userdata( $gd->ID ) ), true ) );
	$gd_only = t_user();
	update_user_meta( $gd_only->ID, 'lc_idp_google', 'lctest-guard2-' . $gd_only->ID );
	update_user_meta( $gd_only->ID, 'lc_idp_discord', 'lctest-guard2d-' . $gd_only->ID );
	t( 'usable guard: Google + disabled Discord -> Google is the only usable method, cannot be removed', ! lc_auth_can_remove( get_userdata( $gd_only->ID ), 'idp:google' ) );
	remove_filter( 'lc_auth_provider_enabled', $off_d, 10 );
	t( 'usable guard: Discord back on -> counted again', lc_auth_can_remove( get_userdata( $gd_only->ID ), 'idp:google' ) );

	// Magic-link takeover revocation also drops connected identities.
	t_browser();
	$rv = t_user(); // Unverified address, e.g. planted by someone else.
	update_user_meta( $rv->ID, 'lc_idp_google', 'lctest-rv-' . $rv->ID );
	update_user_meta( $rv->ID, 'lc_idp_discord', 'lctest-rvd-' . $rv->ID );
	lc_auth_dev_outbox_clear();
	t_request_link( $rv->user_email );
	$tk = t_link_token( $rv->user_email );
	$cr = '' !== $tk ? t_confirm( $tk ) : new WP_Error( 'no_token' );
	t_track();
	t( 'magic link on an unverified account: signs the real owner in', is_array( $cr ) && get_current_user_id() === $rv->ID, t_code( $cr ) );
	t( 'magic link revocation: the Google and Discord identities are removed too', array() === lc_auth_idps( $rv->ID ) && empty( get_user_meta( $rv->ID, 'lc_idp_info', true )['google'] ) );

	// Sub-resource GETs and prefetch.
	$sf = array( 'HTTP_SEC_FETCH_MODE' => 'navigate', 'HTTP_SEC_FETCH_DEST' => 'document' );
	foreach ( array_keys( $sf ) as $k ) {
		unset( $_SERVER[ $k ] );
	}
	t( 'sec-fetch: no headers -> allowed (older browsers keep working)', ! lc_auth_oauth_subresource() );
	$_SERVER['HTTP_SEC_FETCH_MODE'] = 'navigate';
	$_SERVER['HTTP_SEC_FETCH_DEST'] = 'document';
	t( 'sec-fetch: navigate + document -> allowed', ! lc_auth_oauth_subresource() );
	$_SERVER['HTTP_SEC_FETCH_MODE'] = 'no-cors';
	t( 'sec-fetch: mode no-cors -> rejected', lc_auth_oauth_subresource() );
	$_SERVER['HTTP_SEC_FETCH_MODE'] = 'navigate';
	$_SERVER['HTTP_SEC_FETCH_DEST'] = 'image';
	t( 'sec-fetch: dest image -> rejected', lc_auth_oauth_subresource() );
	unset( $_SERVER['HTTP_SEC_FETCH_MODE'] );
	$_SERVER['HTTP_SEC_FETCH_DEST'] = 'iframe';
	t( 'sec-fetch: dest alone is enough to reject', lc_auth_oauth_subresource() );
	unset( $_SERVER['HTTP_SEC_FETCH_DEST'] );
	t_browser();
	$before                      = t_live_states();
	$_SERVER['HTTP_SEC_PURPOSE'] = 'prefetch';
	$res                         = lc_auth_oauth_start( 'google', 'signin', '' );
	unset( $_SERVER['HTTP_SEC_PURPOSE'] );
	t( 'prefetch: start returns without creating a state record or a cookie', 'oauth_prefetch' === t_code( $res ) && t_live_states() === $before );
	t( 'panel anchors carry the no-prefetch class', 2 === substr_count( lc_auth_oauth_buttons( '' ), ' no-prefetch"' ) );

	// Flow cookie is kept (same value) after a start.
	t_browser();
	$cv0 = $_COOKIE[ lc_auth_cookie_name( 'lcauth' ) ];
	t_oauth_begin( 'google', 'signin', '' );
	t( 'start: the flow cookie value is unchanged (only its lifetime is refreshed)', $cv0 === $_COOKIE[ lc_auth_cookie_name( 'lcauth' ) ] );

	// Security notices cannot be silenced by filling the recipient bucket.
	t_browser();
	$sn = t_user();
	lc_auth_mark_email_verified( $sn->ID, $sn->user_email );
	$lim_fn = t_limits( array( 'rcpt_h' => 1, 'rcpt_d' => 1, 'security_d' => 2 ) );
	lc_auth_dev_outbox_clear();
	lc_auth_email_send( $sn->user_email, 's', 'b', 'signin' );
	lc_auth_email_send( $sn->user_email, 's', 'b', 'signin' ); // Over the recipient cap: skipped.
	t( 'security notice setup: the recipient bucket is full (second ordinary mail skipped)', 1 === t_outbox_count( $sn->user_email ) );
	lc_auth_idp_notify( $sn, 'google', 'idp_linked' );
	t( 'security notice: idp_linked still goes out with the recipient bucket full', 2 === t_outbox_count( $sn->user_email ) );
	lc_auth_idp_notify( $sn, 'google', 'idp_unlinked' );
	t( 'security notice: idp_unlinked too', 3 === t_outbox_count( $sn->user_email ) );
	lc_auth_idp_notify( $sn, 'google', 'idp_linked' );
	t( 'security notice: the small per-user bucket (2 here) stops a mail-bomb', 3 === t_outbox_count( $sn->user_email ) );
	remove_filter( 'lc_auth_mail_limits', $lim_fn );
	t_browser();
	// The visitor buckets do not stop it either, but the global cap still applies (to unverified/unknown recipients only).
	$lim_fn = t_limits( array( 'ip' => 0 ) );
	$sn2    = t_user();
	lc_auth_mark_email_verified( $sn2->ID, $sn2->user_email );
	lc_auth_dev_outbox_clear();
	lc_auth_email_send( $sn2->user_email, 's', 'b', 'signin' ); // Blocked by the visitor bucket (limit 0).
	$ord = t_outbox_count( $sn2->user_email );
	lc_auth_idp_notify( $sn2, 'google', 'idp_linked' );
	t( 'security notice: not blocked by a full per-visitor bucket either', 0 === $ord && 1 === t_outbox_count( $sn2->user_email ), (string) $ord );
	remove_filter( 'lc_auth_mail_limits', $lim_fn );

	// Secrets never appear in request URLs; routes are GET-only and whitelisted.
	$all_http = wp_json_encode( array_column( $GLOBALS['lc_t_http'], 'url' ) );
	t( 'secrets never appear in request URLs', false === strpos( $all_http, lc_auth_oauth_creds( 'google' )['secret'] ) && false === strpos( $all_http, lc_auth_oauth_creds( 'discord' )['secret'] ) );
	$routes = lc_auth_routes();
	t( 'routes: start and callback for both providers are whitelisted, GET only', isset( $routes['oauth/google']['GET'], $routes['oauth/google/callback']['GET'], $routes['oauth/discord']['GET'], $routes['oauth/discord/callback']['GET'] ) && 1 === count( $routes['oauth/google'] ) && 1 === count( $routes['oauth/discord/callback'] ) && is_callable( $routes['oauth/google']['GET'] ) && is_callable( $routes['oauth/discord/callback']['GET'] ) );

} catch ( Throwable $e ) {
	t( 'no exception', false, get_class( $e ) . ': ' . $e->getMessage() . ' @' . basename( $e->getFile() ) . ':' . $e->getLine() );
} finally {
	global $wpdb;
	wp_set_current_user( 0 );
	foreach ( $GLOBALS['lc_t']['comments'] as $cid ) {
		wp_delete_comment( $cid, true );
	}
	// Anything created by flows (members made by wallet/email sign-up) carries no lc_test meta; the ones we saw are tracked.
	$ids = array_unique( array_merge( $GLOBALS['lc_t']['users'], array_map( 'intval', $wpdb->get_col( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'lc_test' OR (meta_key = 'lc_idp_google' AND meta_value LIKE 'lctest-%')" ) ), array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->users} WHERE user_email LIKE '%@lc-test.invalid'" ) ) ) );
	foreach ( $ids as $id ) {
		if ( get_userdata( $id ) ) {
			wp_delete_user( $id );
		}
	}
	foreach ( array( 'lc\_oa\_', 'lc\_rl\_', 'lc\_ml\_', 'lc\_mli\_', 'lc\_wc\_', 'lc\_tt\_' ) as $prefix ) {
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
