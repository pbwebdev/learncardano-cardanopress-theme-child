<?php
/**
 * Accounts, part 5 (R3): "Continue with Google" and "Continue with Discord".
 * Loaded by the /account/* router for /account/oauth/* and by the pages that
 * show the buttons (panel, account page); nothing here runs on other requests.
 *
 * Flow (authorization code + PKCE S256, all plain GET navigations):
 * 1. GET /account/oauth/{provider}/ (the panel's link) or the account page's
 *    "Connect" POST: a one-time record is stored under an HMAC of `state` and
 *    bound to the browser's flow cookie, the provider, the intent
 *    (signin|link), the PKCE verifier, the OIDC nonce and, for "link", the
 *    signed-in user and session. Then 302 to the provider.
 * 2. GET /account/oauth/{provider}/callback/: the record is used up (single
 *    use). The callback URL is per provider, so a response meant for one
 *    provider can never be redeemed as another (mix-up defence). The code is
 *    swapped for tokens by a direct TLS call; tokens are never stored or
 *    logged. The identity is the provider's stable subject, never the email.
 *
 * A provider is enabled only when its wp-config constants exist and are not
 * empty: LC_GOOGLE_CLIENT_ID / LC_GOOGLE_CLIENT_SECRET and
 * LC_DISCORD_CLIENT_ID / LC_DISCORD_CLIENT_SECRET. Secrets are read only at the
 * moment of the token call and are never printed.
 *
 * Storage: user meta lc_idp_google / lc_idp_discord hold the subject;
 * lc_idp_info holds provider => { linked, used, hint (masked email) }.
 *
 * Linking rules: see lc_auth_oauth_do_signin() and lc_auth_oauth_do_link().
 * Staff are password-only: they are never signed in, auto-linked or linked.
 *
 * @package CardanoPress Bootstrap
 */

lc_auth_load( 'email' );
lc_auth_load( 'wallet' ); // lc_auth_session_hash().

const LC_AUTH_OAUTH_TTL = 600;

/* ---------------------------------------------------------------- providers */

/**
 * The provider registry. With $all false, only enabled providers. Credentials
 * are not part of it (see lc_auth_oauth_creds()), so it is safe to dump.
 *
 * Not built yet: 'x' (X OAuth2 with PKCE, scopes users.read tweet.read
 * users.email, authorize https://x.com/i/oauth2/authorize, token
 * https://api.x.com/2/oauth2/token with HTTP Basic client auth). It would never
 * auto-link by email (X's email is not proof of control) and costs about
 * US$0.01 per login, so it stays a stub until there is a reason.
 *
 * @param bool $all Include providers that are not enabled.
 * @return array<string, array>
 */
function lc_auth_providers( $all = false ) {
	$registry = array(
		'google'  => array(
			'label'       => 'Google',
			'kind'        => 'oidc',
			'issuer'      => 'https://accounts.google.com',
			'authorize'   => 'https://accounts.google.com/o/oauth2/v2/auth',
			'token'       => 'https://oauth2.googleapis.com/token',
			'scope'       => 'openid email profile',
			'pkce'        => true,
			'auth_params' => array( 'prompt' => 'select_account' ),
		),
		'discord' => array(
			'label'     => 'Discord',
			'kind'      => 'oauth2',
			'issuer'    => 'https://discord.com',
			'authorize' => 'https://discord.com/oauth2/authorize',
			'token'     => 'https://discord.com/api/oauth2/token',
			'profile'   => 'https://discord.com/api/v10/users/@me',
			'revoke'    => 'https://discord.com/api/oauth2/token/revoke',
			'scope'     => 'identify email',
			'pkce'      => true,
		),
	);
	foreach ( $registry as $id => &$p ) {
		// Discord: turn PKCE off with add_filter( 'lc_auth_oauth_pkce', '__return_false' ) if it rejects code_verifier.
		$p['pkce']        = (bool) apply_filters( 'lc_auth_oauth_pkce', $p['pkce'], $id );
		$p['auth_params'] = isset( $p['auth_params'] ) ? $p['auth_params'] : array();
		$p['enabled']     = (bool) apply_filters( 'lc_auth_provider_enabled', null !== lc_auth_oauth_creds( $id ), $id );
	}
	unset( $p );
	/** Filter the registry (tests, or a different issuer string). */
	$registry = (array) apply_filters( 'lc_auth_providers', $registry );
	return $all ? $registry : array_filter( $registry, fn( $p ) => ! empty( $p['enabled'] ) );
}

/**
 * client_id and client_secret from wp-config, or null unless both are set and
 * not empty. Callers must never print the result.
 *
 * @param string $id Provider id.
 * @return array{id:string,secret:string}|null
 */
function lc_auth_oauth_creds( $id ) {
	if ( ! in_array( $id, lc_auth_idp_ids(), true ) ) {
		return null;
	}
	$a = 'LC_' . strtoupper( $id ) . '_CLIENT_ID';
	$b = 'LC_' . strtoupper( $id ) . '_CLIENT_SECRET';
	if ( ! defined( $a ) || ! defined( $b ) || ! is_string( constant( $a ) ) || ! is_string( constant( $b ) ) ) {
		return null;
	}
	$client = trim( constant( $a ) );
	$secret = trim( constant( $b ) );
	return '' === $client || '' === $secret ? null : array( 'id' => $client, 'secret' => $secret );
}

/** The exact redirect_uri registered with the provider (trailing slash included). */
function lc_auth_oauth_redirect_uri( $id ) {
	return home_url( '/account/oauth/' . $id . '/callback/' );
}

function lc_auth_oauth_error( $code, $status = 400, array $extra = array() ) {
	return new WP_Error( $code, lc_auth_text( $code ), array( 'status' => $status ) + $extra );
}

/** Whether the browser is only prefetching this URL (Sec-Purpose / Purpose: prefetch). */
function lc_auth_oauth_prefetch() {
	foreach ( array( 'HTTP_SEC_PURPOSE', 'HTTP_PURPOSE', 'HTTP_X_PURPOSE', 'HTTP_X_MOZ' ) as $h ) {
		if ( ! empty( $_SERVER[ $h ] ) && is_string( $_SERVER[ $h ] ) && false !== stripos( $_SERVER[ $h ], 'prefetch' ) ) {
			return true;
		}
	}
	return false;
}

/* ------------------------------------------------------------ identity store */

/** Users holding this provider subject (merged-away accounts excluded; at most 5). */
function lc_auth_idp_owners( $provider, $subject ) {
	global $wpdb;
	if ( ! in_array( $provider, lc_auth_idp_ids(), true ) ) {
		return array();
	}
	$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s LIMIT 5", 'lc_idp_' . $provider, $subject ) ) );
	// The column compares case-insensitively; subjects are exact strings.
	$ids = array_filter( $ids, fn( $id ) => hash_equals( (string) get_user_meta( $id, 'lc_idp_' . $provider, true ), (string) $subject ) );
	return array_values( array_filter( $ids, fn( $id ) => ! get_user_meta( $id, 'lc_merged_into', true ) ) );
}

/** Store (or refresh) a user's identity for a provider. */
function lc_auth_idp_set( $user_id, $provider, $subject, $email = '' ) {
	update_user_meta( $user_id, 'lc_idp_' . $provider, $subject );
	$info              = (array) get_user_meta( $user_id, 'lc_idp_info', true );
	$old               = isset( $info[ $provider ] ) && is_array( $info[ $provider ] ) ? $info[ $provider ] : array();
	$info[ $provider ] = array(
		'linked' => $old['linked'] ?? time(),
		'used'   => time(),
		'hint'   => '' !== $email ? lc_auth_mask_email( $email ) : ( $old['hint'] ?? '' ),
	);
	update_user_meta( $user_id, 'lc_idp_info', $info );
}

function lc_auth_idp_remove( $user_id, $provider ) {
	delete_user_meta( $user_id, 'lc_idp_' . $provider );
	$info = (array) get_user_meta( $user_id, 'lc_idp_info', true );
	unset( $info[ $provider ] );
	update_user_meta( $user_id, 'lc_idp_info', $info );
}

/** Tell the account's verified address that a provider was connected or disconnected. */
function lc_auth_idp_notify( WP_User $user, $provider, $kind ) {
	if ( ! lc_auth_email_verified( $user ) ) {
		return;
	}
	$all   = lc_auth_providers( true );
	$label = isset( $all[ $provider ]['label'] ) ? $all[ $provider ]['label'] : ucfirst( $provider );
	if ( 'idp_linked' === $kind ) {
		$subject = __( 'A sign-in method was connected to your Learn Cardano account', 'learncardano' );
		/* translators: 1: provider name, 2: account page URL */
		$body = __( "Your %1\$s account was just connected to your Learn Cardano account, so you can now sign in with it.\n\nIf this was you, there is nothing to do. If it wasn't, disconnect it and sign out everywhere else on your account page:\n%2\$s", 'learncardano' );
	} else {
		$subject = __( 'A sign-in method was removed from your Learn Cardano account', 'learncardano' );
		/* translators: 1: provider name, 2: account page URL */
		$body = __( "Your %1\$s account was just disconnected from your Learn Cardano account.\n\nIf this wasn't you, check your sign-in methods and sign out everywhere else on your account page:\n%2\$s", 'learncardano' );
	}
	lc_auth_email_send( $user->user_email, $subject, sprintf( $body, $label, lc_auth_account_url() ), $kind );
}

/* -------------------------------------------------------------------- start */

/**
 * Begin a flow. Returns the provider URL to send the browser to.
 *
 * @param string $id       Provider id.
 * @param string $intent   signin|link. "link" needs the signed-in, non-staff
 *                         user with a session at most 30 minutes old (the
 *                         caller has already checked the page nonce).
 * @param string $redirect Where to land after a successful sign-in.
 * @return array{url:string}|WP_Error
 */
function lc_auth_oauth_start( $id, $intent, $redirect ) {
	if ( lc_auth_oauth_prefetch() ) {
		return lc_auth_oauth_error( 'oauth_prefetch', 403 ); // No side effects: nothing is stored, no cookie is set.
	}
	if ( ! lc_auth_enabled() ) {
		return lc_auth_oauth_error( 'disabled', 503 );
	}
	$providers = lc_auth_providers();
	$creds     = isset( $providers[ $id ] ) ? lc_auth_oauth_creds( $id ) : null;
	if ( ! $creds ) {
		return lc_auth_oauth_error( 'oauth_unavailable', 404 );
	}
	$p     = $providers[ $id ];
	$retry = lc_auth_rate( 'oauth_start', null, 20, 10 * MINUTE_IN_SECONDS ) ?: lc_auth_rate( 'oauth_start_coarse', lc_auth_coarse_subject(), 100, 10 * MINUTE_IN_SECONDS );
	if ( $retry ) {
		return lc_auth_oauth_error( 'rate', 429, array( 'retry' => $retry ) );
	}
	$user_id = 0;
	$session = '';
	if ( 'link' === $intent ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return lc_auth_oauth_error( 'forbidden', 401 );
		}
		if ( lc_auth_is_privileged( $user ) ) {
			return lc_auth_oauth_error( 'password_only', 403 );
		}
		$session = lc_auth_session_hash();
		if ( '' === $session ) {
			return lc_auth_oauth_error( 'reload', 403 );
		}
		if ( lc_auth_session_age() > 30 * MINUTE_IN_SECONDS ) {
			return lc_auth_oauth_error( 'delete_recent', 403 ); // Connecting is a sensitive change, like deleting the account.
		}
		$user_id = $user->ID;
	} elseif ( 'signin' !== $intent ) {
		return lc_auth_oauth_error( 'unknown', 400 );
	}

	$flow = lc_auth_flow( true );
	// The flow lasts up to 10 minutes: re-issue the cookie (same value) with a fresh hour so it cannot expire mid-flow.
	$cookie = lc_auth_get_cookie( 'lcauth' );
	if ( '' !== $cookie ) {
		lc_auth_set_cookie( 'lcauth', $cookie, HOUR_IN_SECONDS );
	}
	$state    = lc_auth_new_token();
	$verifier = lc_auth_new_token();
	$nonce    = lc_auth_new_token();
	lc_auth_store_put(
		'oa',
		array(
			'p'  => $id,
			'i'  => $intent,
			'fh' => $flow,
			'v'  => $verifier,
			'n'  => $nonce,
			'r'  => lc_auth_safe_redirect( $redirect ),
			'u'  => $user_id,
			's'  => $session,
			't'  => time(),
		),
		LC_AUTH_OAUTH_TTL,
		$state
	);

	$params = array(
		'response_type' => 'code',
		'client_id'     => $creds['id'],
		'redirect_uri'  => lc_auth_oauth_redirect_uri( $id ),
		'scope'         => $p['scope'],
		'state'         => $state,
	);
	if ( $p['pkce'] ) {
		$params['code_challenge']        = lc_auth_b64url( hash( 'sha256', $verifier, true ) );
		$params['code_challenge_method'] = 'S256';
	}
	if ( 'oidc' === $p['kind'] ) {
		$params['nonce'] = $nonce;
	}
	$params = array_merge( $params, (array) $p['auth_params'] );
	return array( 'url' => $p['authorize'] . '?' . http_build_query( $params, '', '&', PHP_QUERY_RFC3986 ) );
}

/* ----------------------------------------------------------------- callback */

/**
 * Finish a flow. The returned WP_Error's code is an lc_auth_text() code; its
 * data carries `redirect` and `intent` when the state record was found.
 *
 * @param string $id Provider id (from the callback URL).
 * @param array  $q  The query string: code, state, error, iss.
 * @return array{redirect:string,new_account:bool}|WP_Error
 */
function lc_auth_oauth_callback( $id, array $q ) {
	if ( ! lc_auth_enabled() ) {
		return lc_auth_oauth_error( 'disabled', 503 );
	}
	$providers = lc_auth_providers();
	$creds     = isset( $providers[ $id ] ) ? lc_auth_oauth_creds( $id ) : null;
	if ( ! $creds ) {
		return lc_auth_oauth_error( 'oauth_unavailable', 404 );
	}
	$p     = $providers[ $id ];
	$retry = lc_auth_rate( 'oauth_cb', null, 30, 10 * MINUTE_IN_SECONDS ) ?: lc_auth_rate( 'oauth_cb_coarse', lc_auth_coarse_subject(), 150, 10 * MINUTE_IN_SECONDS );
	if ( $retry ) {
		return lc_auth_oauth_error( 'rate', 429, array( 'retry' => $retry ) );
	}

	$state = isset( $q['state'] ) && is_string( $q['state'] ) ? $q['state'] : '';
	$rec   = lc_auth_is_token( $state ) ? lc_auth_store_take( 'oa', $state ) : null; // Single use: a replay finds nothing.
	$extra = $rec ? array( 'redirect' => lc_auth_safe_redirect( (string) $rec['r'] ), 'intent' => (string) $rec['i'] ) : array();
	$fail  = function ( $code, $why ) use ( $id, $extra ) {
		lc_auth_log( 'oauth_failed', array( 'provider' => $id, 'why' => $why ) );
		return lc_auth_oauth_error( $code, 400, $extra );
	};

	if ( isset( $q['error'] ) ) {
		// The person said no at the provider (access_denied) or it failed; either way nothing was granted.
		lc_auth_log( 'oauth_cancelled', array( 'provider' => $id ) );
		return lc_auth_oauth_error( 'oauth_cancelled', 400, $extra );
	}
	if ( ! $rec ) {
		return $fail( 'oauth_failed', 'state' );
	}
	if ( ! isset( $rec['p'], $rec['i'], $rec['fh'], $rec['v'], $rec['n'] ) || ! hash_equals( (string) $rec['p'], (string) $id ) ) {
		return $fail( 'oauth_failed', 'provider' ); // A response for another provider's flow.
	}
	$flow = lc_auth_flow( false );
	if ( '' === $flow || ! hash_equals( (string) $rec['fh'], $flow ) ) {
		return $fail( 'oauth_failed', 'flow_cookie' );
	}
	if ( isset( $q['iss'] ) ) {
		$issuers = array_map( fn( $i ) => rtrim( $i, '/' ), array_merge( array( (string) $p['issuer'] ), (array) ( $p['issuers'] ?? array() ) ) );
		if ( ! is_string( $q['iss'] ) || ! in_array( rtrim( $q['iss'], '/' ), $issuers, true ) ) {
			return $fail( 'oauth_failed', 'iss' );
		}
	}
	$user = null;
	if ( 'link' === $rec['i'] ) {
		$user    = wp_get_current_user();
		$session = lc_auth_session_hash();
		if ( ! $user->exists() || $user->ID !== (int) $rec['u'] || '' === $session || ! hash_equals( (string) $rec['s'], $session ) || lc_auth_is_privileged( $user ) ) {
			return $fail( 'oauth_failed', 'session' );
		}
	}
	$code = isset( $q['code'] ) && is_string( $q['code'] ) ? $q['code'] : '';
	if ( '' === $code || strlen( $code ) > 2000 ) {
		return $fail( 'oauth_failed', 'code' );
	}

	$tokens = lc_auth_oauth_exchange( $p, $creds, $id, $code, $p['pkce'] ? (string) $rec['v'] : '' );
	if ( is_wp_error( $tokens ) ) {
		return $fail( 'oauth_failed', $tokens->get_error_code() );
	}
	if ( 'oidc' === $p['kind'] ) {
		$ident = lc_auth_oauth_google_identity( isset( $tokens['id_token'] ) ? $tokens['id_token'] : '', $creds['id'], (string) $rec['n'] );
	} else {
		$ident = lc_auth_oauth_discord_identity( $p, $creds, isset( $tokens['access_token'] ) ? $tokens['access_token'] : '' );
	}
	if ( is_wp_error( $ident ) ) {
		return $fail( 'oauth_failed', $ident->get_error_code() );
	}
	unset( $tokens );

	$result = lc_auth_oauth_resolve( $ident, (string) $rec['i'], $rec );
	if ( is_wp_error( $result ) ) {
		lc_auth_log( 'oauth_refused', array( 'provider' => $id, 'code' => $result->get_error_code(), 'intent' => $rec['i'] ) );
		return lc_auth_oauth_error( $result->get_error_code(), 400, $extra );
	}
	return $result;
}

/**
 * Swap the code for tokens (direct TLS call from our server; the response is
 * never stored or logged).
 *
 * @return array|WP_Error Decoded JSON; WP_Error code is a short reason for the log.
 */
function lc_auth_oauth_exchange( array $p, array $creds, $id, $code, $verifier ) {
	$body = array(
		'grant_type'    => 'authorization_code',
		'code'          => $code,
		'redirect_uri'  => lc_auth_oauth_redirect_uri( $id ),
		'client_id'     => $creds['id'],
		'client_secret' => $creds['secret'],
	);
	if ( '' !== $verifier ) {
		$body['code_verifier'] = $verifier;
	}
	$res = wp_remote_post(
		$p['token'],
		array(
			'timeout'     => 10,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array( 'Accept' => 'application/json' ),
			'body'        => $body,
		)
	);
	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'token_http' );
	}
	$raw = (string) wp_remote_retrieve_body( $res );
	if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || strlen( $raw ) > 20000 ) {
		return new WP_Error( 'token_status_' . (int) wp_remote_retrieve_response_code( $res ) );
	}
	$json = json_decode( $raw, true );
	return is_array( $json ) ? $json : new WP_Error( 'token_json' );
}

/**
 * Google: the identity from the id_token.
 *
 * The signature is deliberately NOT checked. The token comes straight from
 * Google's token endpoint over a TLS connection we opened, authenticated with
 * our client secret, and that is exactly the case where Google says validation
 * of the signature can be skipped ("Validating an ID token": direct trust when
 * received from Google over HTTPS with the client secret) and where OpenID
 * Connect Core 3.1.3.7 step 6 lets TLS server validation stand in for it. We
 * still check everything inside the token. (Never use this function on a token
 * that came from the browser.)
 *
 * @param string $jwt       The id_token.
 * @param string $client_id Our client id.
 * @param string $nonce     The nonce we sent.
 * @param int    $now       Unix time (tests).
 * @return array{provider:string,subject:string,email:string,email_verified:bool,email_authoritative:bool,name:string}|WP_Error
 */
function lc_auth_oauth_google_identity( $jwt, $client_id, $nonce, $now = 0 ) {
	$now   = $now ? (int) $now : time();
	$parts = is_string( $jwt ) && strlen( $jwt ) <= 8000 ? explode( '.', $jwt ) : array();
	if ( 3 !== count( $parts ) ) {
		return new WP_Error( 'idt_shape' );
	}
	$dec = function ( $part ) {
		$raw = base64_decode( strtr( $part, '-_', '+/' ), true );
		$arr = false === $raw ? null : json_decode( $raw, true );
		return is_array( $arr ) ? $arr : null;
	};
	$h = $dec( $parts[0] );
	$c = $dec( $parts[1] );
	if ( null === $h || null === $c ) {
		return new WP_Error( 'idt_json' );
	}
	if ( ! isset( $h['alg'] ) || 'RS256' !== $h['alg'] ) {
		return new WP_Error( 'idt_alg' );
	}
	if ( ! isset( $c['iss'] ) || ! in_array( $c['iss'], array( 'https://accounts.google.com', 'accounts.google.com' ), true ) ) {
		return new WP_Error( 'idt_iss' );
	}
	$aud = $c['aud'] ?? null;
	$azp = $c['azp'] ?? null;
	if ( is_array( $aud ) ) {
		if ( ! in_array( $client_id, $aud, true ) || $azp !== $client_id ) {
			return new WP_Error( 'idt_aud' );
		}
	} elseif ( ! is_string( $aud ) || ! hash_equals( $client_id, $aud ) ) {
		return new WP_Error( 'idt_aud' );
	}
	if ( null !== $azp && $azp !== $client_id ) {
		return new WP_Error( 'idt_azp' );
	}
	if ( ! isset( $c['exp'] ) || ! is_numeric( $c['exp'] ) || (int) $c['exp'] < $now - 60 ) {
		return new WP_Error( 'idt_exp' );
	}
	if ( ! isset( $c['iat'] ) || ! is_numeric( $c['iat'] ) || (int) $c['iat'] > $now + 300 ) {
		return new WP_Error( 'idt_iat' );
	}
	if ( ! isset( $c['nonce'] ) || ! is_string( $c['nonce'] ) || '' === $nonce || ! hash_equals( $nonce, $c['nonce'] ) ) {
		return new WP_Error( 'idt_nonce' );
	}
	if ( ! isset( $c['sub'] ) || ! is_string( $c['sub'] ) || strlen( $c['sub'] ) < 1 || strlen( $c['sub'] ) > 255 ) {
		return new WP_Error( 'idt_sub' );
	}
	$email = isset( $c['email'] ) && is_string( $c['email'] ) ? strtolower( trim( $c['email'] ) ) : '';
	$email = is_email( $email ) && strlen( $email ) <= 254 ? $email : '';
	$ev    = isset( $c['email_verified'] ) && ( true === $c['email_verified'] || 'true' === $c['email_verified'] ) && '' !== $email;
	// Authoritative (Google's own guidance): verified AND either a Gmail address or a Workspace account (non-empty hd claim).
	$gmail = '' !== $email && 1 === preg_match( '/@(gmail|googlemail)\.com$/', $email );
	$hd    = isset( $c['hd'] ) && is_string( $c['hd'] ) && '' !== trim( $c['hd'] );
	$name  = '';
	foreach ( array( 'given_name', 'name' ) as $k ) {
		if ( '' === $name && isset( $c[ $k ] ) && is_string( $c[ $k ] ) ) {
			$name = lc_auth_oauth_name( $c[ $k ] );
		}
	}
	return array(
		'provider'       => 'google',
		'subject'        => $c['sub'],
		'email'          => $email,
		'email_verified' => $ev,
		'email_authoritative' => $ev && ( $gmail || $hd ),
		'name'           => $name,
	);
}

/** A provider-given name as a display name, or '' when it does not pass our rules. */
function lc_auth_oauth_name( $raw ) {
	$name = lc_auth_validate_display_name( mb_substr( (string) $raw, 0, 40 ) );
	return false === $name ? '' : $name;
}

/**
 * Discord: id, email and verified flag from /users/@me; the access token is
 * revoked afterwards (best effort).
 *
 * @return array|WP_Error Same shape as the Google one.
 */
function lc_auth_oauth_discord_identity( array $p, array $creds, $access_token ) {
	if ( ! is_string( $access_token ) || '' === $access_token || strlen( $access_token ) > 500 ) {
		return new WP_Error( 'no_token' );
	}
	$res = wp_remote_get(
		$p['profile'],
		array(
			'timeout'     => 10,
			'redirection' => 0,
			'sslverify'   => true,
			'headers'     => array(
				'Authorization' => 'Bearer ' . $access_token,
				'Accept'        => 'application/json',
			),
		)
	);
	if ( ! empty( $p['revoke'] ) ) {
		wp_remote_post(
			$p['revoke'],
			array(
				'timeout'  => 5,
				'blocking' => false,
				'body'     => array(
					'client_id'       => $creds['id'],
					'client_secret'   => $creds['secret'],
					'token'           => $access_token,
					'token_type_hint' => 'access_token',
				),
			)
		);
	}
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return new WP_Error( 'profile_http' );
	}
	$raw  = (string) wp_remote_retrieve_body( $res );
	$user = strlen( $raw ) <= 20000 ? json_decode( $raw, true ) : null;
	if ( ! is_array( $user ) || ! isset( $user['id'] ) || ! is_string( $user['id'] ) || 1 !== preg_match( '/^[0-9]{1,32}$/', $user['id'] ) ) {
		return new WP_Error( 'profile_json' );
	}
	$email = isset( $user['email'] ) && is_string( $user['email'] ) ? strtolower( trim( $user['email'] ) ) : '';
	$email = is_email( $email ) && strlen( $email ) <= 254 ? $email : '';
	$name  = '';
	foreach ( array( 'global_name', 'username' ) as $k ) {
		if ( '' === $name && isset( $user[ $k ] ) && is_string( $user[ $k ] ) ) {
			$name = lc_auth_oauth_name( $user[ $k ] );
		}
	}
	return array(
		'provider'       => 'discord',
		'subject'        => $user['id'],
		'email'          => $email,
		'email_verified' => '' !== $email && ( true === ( $user['verified'] ?? false ) ),
		'email_authoritative' => false, // Discord never proves control of an address.
		'name'           => $name,
	);
}

/* ------------------------------------------------------------------ linking */

/**
 * Apply the linking rules to a proven identity.
 *
 * @param array  $ident  provider, subject, email, email_verified, email_authoritative, name.
 * @param string $intent signin|link.
 * @param array  $rec    The state record (r = redirect).
 * @return array{redirect:string,new_account:bool}|WP_Error
 */
function lc_auth_oauth_resolve( array $ident, $intent, array $rec ) {
	$id_key = 'idp:' . $ident['provider'] . ':' . md5( $ident['subject'] );
	$run    = function () use ( $id_key, $ident, $intent, $rec ) {
		return lc_auth_lock(
			$id_key,
			function () use ( $ident, $intent, $rec ) {
				return 'link' === $intent ? lc_auth_oauth_do_link( $ident, $rec ) : lc_auth_oauth_do_signin( $ident, $rec );
			}
		);
	};
	// Same lock the email flows use for this address, so two sign-ups with one address cannot race.
	$result = '' !== $ident['email'] && ! empty( $ident['email_authoritative'] ) ? lc_auth_lock( 'email:' . md5( $ident['email'] ), $run ) : $run();
	return is_wp_error( $result ) && 'lc_auth_busy' === $result->get_error_code() ? new WP_Error( 'busy' ) : $result;
}

/**
 * Sign-in matrix (UL = account holding this provider subject, UE = account
 * with the provider's verified email):
 * - UL exists: sign in (staff refused). If the provider is AUTHORITATIVE for
 *   the email (email_authoritative) and it equals the account's email, that
 *   email becomes verified.
 * - no UL, provider email present, UE exists:
 *     provider not authoritative (always Discord; Google unless Gmail or a
 *       Workspace `hd`) -> refused (oauth_email_exists): sign in by email link
 *       first, then connect on the account page ("connect once, then automatic");
 *     staff -> refused (password_only);
 *     UE's email not verified -> refused (oauth_email_exists);
 *     UE's email verified -> auto-link, notify that address, sign in.
 * - no UL, no UE: new member (lc_auth_create_member caps apply), with the
 *   email only if the provider is authoritative for it and it is free. Others
 *   (Discord, non-Gmail Google without hd) get an account with no email and the
 *   usual "add an email" nudge.
 *
 * @return array|WP_Error
 */
function lc_auth_oauth_do_signin( array $ident, array $rec ) {
	$provider = $ident['provider'];
	$subject  = $ident['subject'];
	$auth     = ! empty( $ident['email_authoritative'] );
	$email    = $auth ? $ident['email'] : ''; // Only an address the provider is authoritative for is ever acted on.
	$hint     = $ident['email_verified'] ? $ident['email'] : ''; // Masked hint only.
	$owners   = lc_auth_idp_owners( $provider, $subject );
	if ( count( $owners ) > 1 ) {
		lc_auth_log( 'idp_conflict', array( 'provider' => $provider, 'count' => count( $owners ) ) );
		return new WP_Error( 'idp_conflict' );
	}
	$new  = false;
	$user = $owners ? get_userdata( $owners[0] ) : null;

	if ( $user ) {
		if ( lc_auth_is_privileged( $user ) ) {
			lc_auth_log( 'refused_privileged', array( 'user' => $user->ID, 'method' => 'idp:' . $provider ) );
			return new WP_Error( 'password_only' );
		}
		if ( '' !== $email && strtolower( $user->user_email ) === $email && ! lc_auth_email_verified( $user ) ) {
			lc_auth_mark_email_verified( $user->ID, $email );
		}
		lc_auth_idp_set( $user->ID, $provider, $subject, $hint );
	} else {
		$existing = '' !== $hint ? get_user_by( 'email', $hint ) : false;
		if ( $existing && ! $auth ) {
			// Connect once, then automatic: a provider that is not authoritative for this address never joins an existing account by email.
			lc_auth_log( 'idp_autolink_refused', array( 'user' => $existing->ID, 'provider' => $provider, 'why' => 'not_authoritative' ) );
			return new WP_Error( 'oauth_email_exists' );
		}
		if ( $existing ) {
			if ( lc_auth_is_privileged( $existing ) ) {
				lc_auth_log( 'refused_privileged', array( 'user' => $existing->ID, 'method' => 'idp:' . $provider ) );
				return new WP_Error( 'password_only' );
			}
			if ( ! lc_auth_email_verified( $existing ) ) {
				lc_auth_log( 'idp_autolink_refused', array( 'user' => $existing->ID, 'provider' => $provider, 'why' => 'local_unverified' ) );
				return new WP_Error( 'oauth_email_exists' );
			}
			if ( isset( lc_auth_idps( $existing->ID )[ $provider ] ) ) {
				return new WP_Error( 'idp_has_other' ); // Same address, but a different account of this provider is already connected.
			}
			lc_auth_idp_set( $existing->ID, $provider, $subject, $hint );
			lc_auth_log( 'idp_autolink', array( 'user' => $existing->ID, 'provider' => $provider ) );
			lc_auth_idp_notify( $existing, $provider, 'idp_linked' );
			$user = $existing;
		} else {
			$user = lc_auth_create_member(
				array(
					'email'          => $email,
					'email_verified' => '' !== $email,
					'display_name'   => $ident['name'],
					'method'         => 'idp_' . $provider,
				)
			);
			if ( is_wp_error( $user ) ) {
				$code = $user->get_error_code();
				return new WP_Error( 'lc_auth_rate' === $code ? 'rate' : ( 'lc_auth_signup_closed' === $code ? 'signup_closed' : 'oauth_failed' ) );
			}
			lc_auth_idp_set( $user->ID, $provider, $subject, $hint );
			$new = true;
		}
	}

	if ( is_user_logged_in() && get_current_user_id() !== $user->ID ) {
		wp_destroy_current_session(); // Switching accounts: end the old session.
	}
	$signed = lc_auth_complete_signin( $user, 'idp:' . $provider );
	if ( is_wp_error( $signed ) ) {
		return new WP_Error( 'lc_auth_disabled' === $signed->get_error_code() ? 'disabled' : 'password_only' );
	}
	return array(
		'redirect'    => lc_auth_safe_redirect( (string) ( $rec['r'] ?? '' ) ),
		'new_account' => $new,
	);
}

/**
 * Link matrix (the signed-in, non-staff user was checked by the callback):
 * - subject held by another account -> refused (idp_in_use), nothing is merged;
 * - held by this account -> nothing to do;
 * - this account already has a different subject for the provider -> refused
 *   (idp_has_other): disconnect first;
 * - otherwise store it and tell the verified address. If the account has no
 *   email and the provider's email is verified and free, it becomes the
 *   account's verified email, but only when the provider is authoritative for it.
 *
 * @return array|WP_Error
 */
function lc_auth_oauth_do_link( array $ident, array $rec ) {
	$provider = $ident['provider'];
	$subject  = $ident['subject'];
	$user     = get_userdata( (int) $rec['u'] );
	if ( ! $user || lc_auth_is_privileged( $user ) ) {
		return new WP_Error( 'password_only' );
	}
	$owners = lc_auth_idp_owners( $provider, $subject );
	if ( count( $owners ) > 1 ) {
		lc_auth_log( 'idp_conflict', array( 'provider' => $provider, 'count' => count( $owners ) ) );
		return new WP_Error( 'idp_conflict' );
	}
	$done = add_query_arg( 'done', 'idp_linked', lc_auth_account_url() );
	if ( $owners ) {
		if ( $owners[0] !== $user->ID ) {
			return new WP_Error( 'idp_in_use' );
		}
		lc_auth_idp_set( $user->ID, $provider, $subject, $ident['email_verified'] ? $ident['email'] : '' );
		return array( 'redirect' => $done, 'new_account' => false ); // Already connected.
	}
	if ( isset( lc_auth_idps( $user->ID )[ $provider ] ) ) {
		return new WP_Error( 'idp_has_other' );
	}
	$was_verified = lc_auth_email_verified( $user );
	lc_auth_idp_set( $user->ID, $provider, $subject, $ident['email_verified'] ? $ident['email'] : '' );
	lc_auth_log( 'idp_link', array( 'user' => $user->ID, 'provider' => $provider ) );
	if ( $was_verified ) {
		lc_auth_idp_notify( $user, $provider, 'idp_linked' );
	}
	if ( '' === $user->user_email && ! empty( $ident['email_authoritative'] ) && '' !== $ident['email'] && ! email_exists( $ident['email'] ) ) {
		$res = wp_update_user( array( 'ID' => $user->ID, 'user_email' => $ident['email'] ) );
		if ( ! is_wp_error( $res ) ) {
			lc_auth_mark_email_verified( $user->ID, $ident['email'] );
			lc_auth_log( 'idp_email_set', array( 'user' => $user->ID, 'provider' => $provider ) );
			$done = add_query_arg( 'done', 'idp_email_set', lc_auth_account_url() );
		}
	}
	return array( 'redirect' => $done, 'new_account' => false );
}

/* ------------------------------------------------------------------- routes */

/** The provider named in the URL being routed (/account/oauth/{id}/…), or ''. */
function lc_auth_oauth_route_provider() {
	global $wp;
	$path = isset( $wp->query_vars['lc_account_path'] ) ? (string) $wp->query_vars['lc_account_path'] : '';
	return 1 === preg_match( '#^/?oauth/(google|discord)(/|$)#', $path, $m ) ? $m[1] : '';
}

/** Back to a page with a generic ?error= code. Exits. */
function lc_auth_oauth_fail_redirect( WP_Error $err ) {
	$data     = (array) $err->get_error_data();
	$redirect = isset( $data['redirect'] ) ? (string) $data['redirect'] : '';
	$url      = lc_auth_account_url();
	if ( 'link' !== ( $data['intent'] ?? '' ) && '' !== $redirect && lc_auth_account_url() !== $redirect ) {
		$url = lc_auth_signin_url( $redirect );
	}
	wp_safe_redirect( add_query_arg( 'error', $err->get_error_code(), $url ), 303 );
	exit;
}

/**
 * Refuse requests the browser makes as sub-resources (img, script, fetch, iframe)
 * rather than as a page navigation: Sec-Fetch-Mode must be "navigate" and
 * Sec-Fetch-Dest "document" when sent. Runs before any rate count or store
 * write. Browsers that send no Sec-Fetch headers keep the current behaviour.
 */
function lc_auth_oauth_subresource() {
	$mode = isset( $_SERVER['HTTP_SEC_FETCH_MODE'] ) && is_string( $_SERVER['HTTP_SEC_FETCH_MODE'] ) ? strtolower( $_SERVER['HTTP_SEC_FETCH_MODE'] ) : null;
	$dest = isset( $_SERVER['HTTP_SEC_FETCH_DEST'] ) && is_string( $_SERVER['HTTP_SEC_FETCH_DEST'] ) ? strtolower( $_SERVER['HTTP_SEC_FETCH_DEST'] ) : null;
	return ( null !== $mode && 'navigate' !== $mode ) || ( null !== $dest && 'document' !== $dest );
}

function lc_auth_oauth_reject_subresource() {
	if ( lc_auth_oauth_subresource() ) {
		status_header( 403 );
		header( 'Cache-Control: no-store' );
		exit;
	}
}

/** GET /account/oauth/{provider}/ : the sign-in link in the panel. */
function lc_auth_get_oauth_start() {
	lc_auth_oauth_reject_subresource();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a plain link; the state record is the protection.
	$redirect = lc_auth_safe_redirect( isset( $_GET['redirect_to'] ) && is_string( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : '' );
	if ( is_user_logged_in() || ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) ) ) {
		wp_safe_redirect( $redirect, 303 ); // Nothing to sign in to (and a HEAD probe must not start a flow).
		exit;
	}
	$res = lc_auth_oauth_start( lc_auth_oauth_route_provider(), 'signin', $redirect );
	if ( is_wp_error( $res ) ) {
		if ( 'oauth_prefetch' === $res->get_error_code() ) {
			// A prefetch gets nothing it could cache or follow; the real click comes back as a navigation.
			status_header( 503 );
			header( 'Retry-After: 0' );
			header( 'Cache-Control: no-store' );
			exit;
		}
		lc_auth_oauth_fail_redirect( new WP_Error( $res->get_error_code(), '', array( 'redirect' => $redirect ) ) );
	}
	header( 'Referrer-Policy: no-referrer' );
	wp_redirect( $res['url'], 302 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the provider's fixed authorize URL.
	exit;
}

/** GET /account/oauth/{provider}/callback/ */
function lc_auth_get_oauth_callback() {
	lc_auth_oauth_reject_subresource();
	header( 'Referrer-Policy: no-referrer' ); // The code and state are in this URL.
	$q = array();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the single-use state record is the check.
	foreach ( array( 'code', 'state', 'error', 'iss' ) as $k ) {
		if ( isset( $_GET[ $k ] ) && is_string( $_GET[ $k ] ) ) {
			$q[ $k ] = substr( wp_unslash( $_GET[ $k ] ), 0, 2100 );
		}
	}
	// phpcs:enable
	$res = lc_auth_oauth_callback( lc_auth_oauth_route_provider(), $q );
	if ( is_wp_error( $res ) ) {
		lc_auth_oauth_fail_redirect( $res );
	}
	wp_safe_redirect( $res['redirect'], 303 );
	exit;
}

/* ----------------------------------------------------------------------- UI */

/** A small inline logo (decorative; the button text names the provider). */
function lc_auth_oauth_logo( $id ) {
	if ( 'google' === $id ) {
		return '<svg class="lc-auth__logo" width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false"><path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62z"/><path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.7H.96v2.33A9 9 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.97 10.72A5.4 5.4 0 0 1 3.68 9c0-.6.1-1.18.29-1.72V4.95H.96A9 9 0 0 0 0 9c0 1.45.35 2.83.96 4.05l3.01-2.33z"/><path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .96 4.95l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58z"/></svg>';
	}
	if ( 'discord' === $id ) {
		return '<svg class="lc-auth__logo" width="18" height="18" viewBox="0 0 18 18" aria-hidden="true" focusable="false"><path fill="#5865F2" d="M3.2 4.2Q6 2.2 9 2.2t5.8 2Q16.8 8 17 13q-2.4 2.4-4.6 2.4l-.9-1.4Q9 14.4 6.5 14l-.9 1.4Q3.4 15.4 1 13 1.2 8 3.2 4.2z"/><ellipse cx="6.4" cy="9.2" rx="1.3" ry="1.5" fill="#fff"/><ellipse cx="11.6" cy="9.2" rx="1.3" ry="1.5" fill="#fff"/></svg>';
	}
	return '';
}

/**
 * The "Continue with …" links for the sign-in panel: plain GET links, only
 * for enabled providers. Returns '' when none are enabled.
 *
 * @param string $redirect Where to land after signing in.
 * @return string HTML (escaped).
 */
function lc_auth_oauth_buttons( $redirect ) {
	$out = '';
	foreach ( lc_auth_providers() as $id => $p ) {
		$url = lc_auth_account_url( 'oauth/' . $id . '/' );
		if ( '' !== $redirect && lc_auth_account_url() !== $redirect ) {
			$url = add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url );
		}
		/* translators: %s: provider name (Google, Discord) */
		$text = sprintf( __( 'Continue with %s', 'learncardano' ), $p['label'] );
		$out .= '<a class="lc-auth__sso lc-auth__sso--' . esc_attr( $id ) . ' no-prefetch" href="' . esc_url( $url ) . '" rel="nofollow">' . lc_auth_oauth_logo( $id ) . '<span>' . esc_html( $text ) . '</span></a>';
	}
	return '' === $out ? '' : '<div class="lc-auth__sso-list">' . $out . '</div>';
}
