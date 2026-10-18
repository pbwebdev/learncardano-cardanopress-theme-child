<?php
/**
 * Accounts, part 1 (R2): the shared plumbing. Loaded on every request, so it
 * is only hooks and small helpers; email.php, wallet.php and oauth.php
 * (Google/Discord, R3) load when an /account/* route needs them.
 *
 * What the account system is:
 * - Sign-in by email link or Cardano wallet (CIP-30), plus the normal
 *   password login on wp-login.php. Everything else lives under /account/*.
 * - ONE transport: every endpoint is an /account/* front-end route. XHR
 *   (Accept: application/json) gets JSON, navigations get pages or 303s.
 *   No REST routes (REST drops the cookie user without a nonce and reflects
 *   CORS origins).
 * - Staff (anyone who can edit posts, moderate comments or list users, or
 *   has a role other than subscriber/student) are PASSWORD-ONLY: every
 *   non-password method refuses them (lc_auth_complete_signin()).
 *
 * Kill switches (wp-config.php): LC_AUTH_DISABLE (true = no email/wallet
 * sign-in at all, password login unaffected) and LC_AUTH_SIGNUP (false = no
 * new accounts; existing ones still sign in).
 *
 * Logs: error_log() lines "lc_auth: event=… user=ID ip=<8 hex> …". Never
 * emails, stake addresses, tokens or signatures.
 *
 * @package CardanoPress Bootstrap
 */

const LC_AUTH_REWRITE_VERSION = 1;

/* ------------------------------------------------------------ switches/policy */

/** False when LC_AUTH_DISABLE is set: email and wallet sign-in answer 503. */
function lc_auth_enabled() {
	return ! ( defined( 'LC_AUTH_DISABLE' ) && LC_AUTH_DISABLE );
}

/** Whether new accounts may be created (LC_AUTH_SIGNUP, default on). */
function lc_auth_signup_enabled() {
	$on = ! defined( 'LC_AUTH_SIGNUP' ) || LC_AUTH_SIGNUP;
	return (bool) apply_filters( 'lc_auth_signup_enabled', $on );
}

/**
 * Staff accounts: password-only everywhere. True for anyone who can edit
 * posts, moderate comments or list users, or holds any role besides
 * subscriber and student.
 *
 * @param WP_User $user User.
 */
function lc_auth_is_privileged( $user ) {
	if ( ! $user instanceof WP_User || ! $user->exists() ) {
		return false;
	}
	foreach ( array( 'edit_posts', 'moderate_comments', 'list_users' ) as $cap ) {
		if ( user_can( $user, $cap ) ) {
			return true;
		}
	}
	return (bool) array_diff( (array) $user->roles, array( 'subscriber', 'student' ) );
}

/**
 * Whether an account was created by CardanoPress wallet sign-up: its login
 * is md5(stake address), 32 lowercase hex characters. Also defined in
 * cardanopress-lockdown.php (which goes away with the plugin's backup), so
 * both copies are guarded.
 *
 * @param WP_User|int|null $user User or ID.
 * @return bool
 */
if ( ! function_exists( 'lc_is_legacy_wallet_account' ) ) {
	function lc_is_legacy_wallet_account( $user ) {
		if ( is_numeric( $user ) ) {
			$user = get_userdata( (int) $user );
		}
		return $user instanceof WP_User && 1 === preg_match( '/^[0-9a-f]{32}$/', (string) $user->user_login );
	}
}

/** Load an optional module (email | wallet | oauth) once. */
function lc_auth_load( $module ) {
	if ( in_array( $module, array( 'email', 'wallet', 'oauth' ), true ) ) {
		require_once __DIR__ . '/' . $module . '.php';
	}
}

/* ----------------------------------------------------------------- crypto/ids */

/** HMAC keyed with the site's auth salt; $purpose keeps uses apart. */
function lc_auth_hmac( $purpose, $data ) {
	return hash_hmac( 'sha256', $purpose . '|' . $data, wp_salt( 'auth' ) );
}

function lc_auth_b64url( $raw ) {
	return rtrim( strtr( base64_encode( $raw ), '+/', '-_' ), '=' );
}

/** A fresh 256-bit token (43 URL-safe characters). */
function lc_auth_new_token() {
	return lc_auth_b64url( random_bytes( 32 ) );
}

/** Whether a string has the shape of lc_auth_new_token() output. */
function lc_auth_is_token( $value ) {
	return is_string( $value ) && 1 === preg_match( '/^[A-Za-z0-9_-]{43}$/', $value );
}

/* ------------------------------------------------------------ one-time tokens */

/**
 * One-time records (magic links, wallet challenges) live in transients under
 * an HMAC of the token, so a database leak yields nothing usable. With no
 * persistent object cache a transient is an options row, which is what makes
 * lc_auth_store_take() atomic.
 */
function lc_auth_store_key( $kind, $token ) {
	return 'lc_' . $kind . '_' . substr( lc_auth_hmac( $kind, $token ), 0, 40 );
}

/**
 * Store a record; returns the raw token (or $token when the caller chooses
 * it, e.g. the wallet nonce).
 */
function lc_auth_store_put( $kind, array $rec, $ttl, $token = null ) {
	$token     = null === $token ? lc_auth_new_token() : $token;
	$rec['h']  = lc_auth_hmac( $kind, $token );
	$rec['x']  = time() + (int) $ttl;
	set_transient( lc_auth_store_key( $kind, $token ), $rec, (int) $ttl );
	return $token;
}

/** The record for a token, or null when unknown/expired. Does not consume it. */
function lc_auth_store_get( $kind, $token ) {
	if ( ! is_string( $token ) || '' === $token || strlen( $token ) > 128 ) {
		return null;
	}
	$rec = get_transient( lc_auth_store_key( $kind, $token ) );
	if ( ! is_array( $rec ) || ! isset( $rec['h'], $rec['x'] ) ) {
		return null;
	}
	if ( ! hash_equals( (string) $rec['h'], lc_auth_hmac( $kind, $token ) ) || (int) $rec['x'] < time() ) {
		return null;
	}
	return $rec;
}

/**
 * Consume a record. Only the request whose delete_transient() really removed
 * the row wins, so two parallel requests can never both use one token.
 */
function lc_auth_store_take( $kind, $token ) {
	$rec = lc_auth_store_get( $kind, $token );
	if ( null === $rec || ! delete_transient( lc_auth_store_key( $kind, $token ) ) ) {
		return null;
	}
	return $rec;
}

/* --------------------------------------------------------- rate limits / locks */

/** The visitor as a rate-limit subject: the IP, IPv6 collapsed to its /64. */
function lc_auth_ip_subject() {
	$ip = lc_client_ip();
	if ( false !== strpos( $ip, ':' ) ) {
		$bin = inet_pton( $ip );
		if ( false !== $bin ) {
			return bin2hex( substr( $bin, 0, 8 ) ) . '/64';
		}
	}
	return $ip;
}

/**
 * A wider rate-limit subject for the same visitor: IPv4 /24, IPv6 /48. A single
 * IPv6 customer owns a whole /48 (65 536 /64s) and an IPv4 attacker usually
 * rents several neighbouring addresses, so the per-IP buckets alone are cheap
 * to step around. A second bucket on this subject, at about 5x the per-IP
 * limit, makes that expensive without hurting a shared office or campus much.
 *
 * @param string|null $ip Address to reduce (default: the visitor's; for tests).
 */
function lc_auth_coarse_subject( $ip = null ) {
	$ip  = null === $ip ? lc_client_ip() : (string) $ip;
	$bin = '' === $ip ? false : inet_pton( $ip );
	if ( false === $bin ) {
		return $ip;
	}
	if ( 16 === strlen( $bin ) && 0 === strpos( $bin, str_repeat( "\0", 10 ) . "\xff\xff" ) ) {
		$bin = substr( $bin, 12 ); // ::ffff:a.b.c.d is an IPv4 visitor.
	}
	if ( 4 === strlen( $bin ) ) {
		return implode( '.', array_slice( array_map( 'ord', str_split( $bin ) ), 0, 3 ) ) . '.0/24';
	}
	return bin2hex( substr( $bin, 0, 6 ) ) . '/48';
}

/**
 * The key an address is rate-limited under: lowercase, no "+tag" in the local
 * part, and for Gmail no dots (googlemail.com is gmail.com). Without this one
 * mailbox is thousands of "different" addresses, which defeats every
 * per-address limit. Only for limit keys: never use it to send or store.
 *
 * @param string $email Address.
 */
function lc_auth_email_key( $email ) {
	$email = strtolower( trim( (string) $email ) );
	$at    = strrpos( $email, '@' );
	if ( false === $at ) {
		return $email;
	}
	$local  = substr( $email, 0, $at );
	$domain = substr( $email, $at + 1 );
	$plus   = strpos( $local, '+' );
	if ( false !== $plus && $plus > 0 ) {
		$local = substr( $local, 0, $plus );
	}
	if ( 'googlemail.com' === $domain ) {
		$domain = 'gmail.com';
	}
	if ( 'gmail.com' === $domain ) {
		$local = str_replace( '.', '', $local );
	}
	return $local . '@' . $domain;
}

/**
 * Count one hit against a bucket. Same idea as lc_delegation_rate_limited().
 *
 * @param string      $bucket  Bucket name.
 * @param string|null $subject Who is limited; null = the visitor's IP.
 * @param int         $limit   Hits allowed per window.
 * @param int         $window  Window in seconds.
 * @param bool        $count   False to only look.
 * @return int 0 when allowed, else seconds until the window resets.
 */
function lc_auth_rate( $bucket, $subject, $limit, $window, $count = true ) {
	$subject = null === $subject ? lc_auth_ip_subject() : (string) $subject;
	$key     = 'lc_rl_' . substr( lc_auth_hmac( 'rl', $bucket . '|' . $subject ), 0, 32 );
	$now     = time();
	$state   = get_transient( $key );
	if ( ! is_array( $state ) || empty( $state['reset'] ) || $state['reset'] <= $now ) {
		$state = array(
			'count' => 0,
			'reset' => $now + (int) $window,
		);
	}
	if ( $state['count'] >= (int) $limit ) {
		return max( 1, $state['reset'] - $now );
	}
	if ( $count ) {
		++$state['count'];
		set_transient( $key, $state, max( 1, $state['reset'] - $now ) );
	}
	return 0;
}

/**
 * Run $fn while holding a named lock (a unique options row; INSERT IGNORE is
 * atomic, unlike add_option(), whose ON DUPLICATE KEY UPDATE lets two
 * requests both "win"). A lock older than 30 s counts as abandoned.
 *
 * @return mixed|WP_Error $fn's result, or WP_Error lc_auth_busy.
 */
function lc_auth_lock( $key, callable $fn ) {
	global $wpdb;
	$name = 'lc_lock_' . substr( md5( $key ), 0, 24 );
	$grab = function () use ( $wpdb, $name ) {
		return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, (string) time() ) );
	};
	if ( ! $grab() ) {
		$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
		if ( $since && time() - $since > 30 ) {
			$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
		}
		if ( ! $grab() ) {
			return new WP_Error( 'lc_auth_busy', lc_auth_text( 'busy' ) );
		}
	}
	try {
		return $fn();
	} finally {
		$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
	}
}

/* ------------------------------------------------------------------ mail gate */

/**
 * Limits for every email this system sends, as one filterable array
 * (lc_auth_mail_limits) so tests can use small numbers.
 *
 * - rcpt_h / rcpt_d: per recipient (normalised, see lc_auth_email_key()).
 * - ip / ip_coarse: mails caused by the current visitor, per 15 minutes.
 * - The site-wide daily cap has its own filter, lc_auth_email_daily_cap.
 */
function lc_auth_mail_limits() {
	return (array) apply_filters(
		'lc_auth_mail_limits',
		array(
			'rcpt_h'    => 5,
			'rcpt_d'    => 10,
			'ip'        => 10,
			'ip_coarse' => 50,
		)
	);
}

/** The site-wide daily mail cap. */
function lc_auth_mail_global_cap() {
	return (int) apply_filters( 'lc_auth_email_daily_cap', 200 );
}

/** Tell the site admin, once a day, that the global mail cap was reached. */
function lc_auth_mail_cap_alert() {
	if ( get_transient( 'lc_auth_mail_cap_alerted' ) ) {
		return;
	}
	set_transient( 'lc_auth_mail_cap_alerted', 1, DAY_IN_SECONDS );
	lc_auth_log( 'mail_cap_reached' );
	wp_mail( get_option( 'admin_email' ), 'Learn Cardano: daily email cap reached', 'The account system hit its daily limit for emails. Mail to people with a verified account still goes out; sign-up and unverified addresses get nothing until the window resets. If this is a real surge, raise it with the lc_auth_email_daily_cap filter; if it is abuse, look for "mail_capped" lines in the log.' );
}

/**
 * May an email go to $to? The LOOK-ONLY half of the gate (nothing is counted),
 * so a request that ends up sending nothing never uses any budget.
 *
 * At the global daily cap mail still goes to an EXISTING account with a
 * verified address, so an abuser who burns the budget cannot lock every real
 * member out of signing in; only unknown and unverified addresses are stopped.
 *
 * @param string $to Recipient address.
 * @return string '' when allowed, else the name of the bucket that said no.
 */
function lc_auth_mail_check( $to, $security = false ) {
	$lim = lc_auth_mail_limits();
	$key = lc_auth_email_key( $to );
	if ( ! $security ) {
		// Security notices skip the recipient and visitor buckets, so exhausting them cannot hide a change from the owner.
		if ( lc_auth_rate( 'mail_rcpt_h', $key, (int) $lim['rcpt_h'], HOUR_IN_SECONDS, false ) ) {
			return 'rcpt_hour';
		}
		if ( lc_auth_rate( 'mail_rcpt_d', $key, (int) $lim['rcpt_d'], DAY_IN_SECONDS, false ) ) {
			return 'rcpt_day';
		}
		if ( lc_auth_rate( 'mail_ip', null, (int) $lim['ip'], 15 * MINUTE_IN_SECONDS, false ) ) {
			return 'ip';
		}
		if ( lc_auth_rate( 'mail_coarse', lc_auth_coarse_subject(), (int) $lim['ip_coarse'], 15 * MINUTE_IN_SECONDS, false ) ) {
			return 'ip_coarse';
		}
	} elseif ( lc_auth_rate( 'mail_sec_d', $key, (int) ( $lim['security_d'] ?? 10 ), DAY_IN_SECONDS, false ) ) {
		return 'security_day'; // Small separate bucket so notices cannot be used to mail-bomb an address.
	}
	if ( lc_auth_rate( 'mail_global', 'all', lc_auth_mail_global_cap(), DAY_IN_SECONDS, false ) ) {
		lc_auth_mail_cap_alert();
		$user = get_user_by( 'email', (string) $to );
		if ( ! $user || ! lc_auth_email_verified( $user ) ) {
			return 'global';
		}
	}
	return '';
}

/** The COUNTING half: call once, right before a mail really goes out. */
function lc_auth_mail_count( $to, $security = false ) {
	$lim = lc_auth_mail_limits();
	$key = lc_auth_email_key( $to );
	if ( $security ) {
		lc_auth_rate( 'mail_sec_d', $key, (int) ( $lim['security_d'] ?? 10 ), DAY_IN_SECONDS );
	} else {
		lc_auth_rate( 'mail_rcpt_h', $key, (int) $lim['rcpt_h'], HOUR_IN_SECONDS );
		lc_auth_rate( 'mail_rcpt_d', $key, (int) $lim['rcpt_d'], DAY_IN_SECONDS );
		lc_auth_rate( 'mail_ip', null, (int) $lim['ip'], 15 * MINUTE_IN_SECONDS );
		lc_auth_rate( 'mail_coarse', lc_auth_coarse_subject(), (int) $lim['ip_coarse'], 15 * MINUTE_IN_SECONDS );
	}
	lc_auth_rate( 'mail_global', 'all', lc_auth_mail_global_cap(), DAY_IN_SECONDS );
}

/**
 * Core's lost-password form (wp-login.php?action=lostpassword) is open to
 * anyone, mails any account and had no limit at all. Throttle it per visitor
 * and per account; an error stops core before it sends anything.
 *
 * @param WP_Error      $errors    Errors so far.
 * @param WP_User|false $user_data The account found, if any.
 */
add_action(
	'lostpassword_post',
	function ( $errors, $user_data = false ) {
		$ip     = lc_auth_rate( 'pw_reset_ip', null, 5, 15 * MINUTE_IN_SECONDS );
		$coarse = lc_auth_rate( 'pw_reset_coarse', lc_auth_coarse_subject(), 25, 15 * MINUTE_IN_SECONDS );
		$user   = $user_data instanceof WP_User ? lc_auth_rate( 'pw_reset_user', (string) $user_data->ID, 3, HOUR_IN_SECONDS ) : 0;
		if ( $ip || $coarse || $user ) {
			lc_auth_log( 'pw_reset_limited', array( 'scope' => $user ? 'user' : 'ip' ) );
			$errors->add( 'lc_auth_rate', esc_html__( 'Too many requests. Please try again later.', 'learncardano' ) );
		}
	},
	10,
	2
);

/**
 * The reset email itself goes through the same mail gate as ours (recipient,
 * visitor and global caps; verified accounts still get it at the global cap).
 * Returning false makes core stop quietly and answer as if it had sent.
 *
 * @param bool    $send       Whether to send.
 * @param string  $user_login Login.
 * @param WP_User $user_data  The account.
 */
add_filter(
	'send_retrieve_password_email',
	function ( $send, $user_login = '', $user_data = null ) {
		unset( $user_login );
		if ( ! $send || ! $user_data instanceof WP_User || '' === (string) $user_data->user_email ) {
			return $send;
		}
		$blocked = lc_auth_mail_check( $user_data->user_email );
		if ( '' !== $blocked ) {
			lc_auth_log( 'mail_capped', array( 'kind' => 'pw_reset', 'bucket' => $blocked ) );
			return false;
		}
		lc_auth_mail_count( $user_data->user_email );
		return true;
	},
	10,
	3
);

/* ------------------------------------------------------------------- logging */

/**
 * One log line. $ctx values are reduced to [A-Za-z0-9_.:-]; callers must never
 * pass emails, stake addresses, tokens or signatures.
 */
function lc_auth_log( $event, array $ctx = array() ) {
	$user = isset( $ctx['user'] ) ? (int) $ctx['user'] : get_current_user_id();
	unset( $ctx['user'] );
	$line = 'lc_auth: event=' . preg_replace( '/[^A-Za-z0-9_.-]/', '', (string) $event ) . ' user=' . $user . ' ip=' . substr( lc_auth_hmac( 'ip', lc_client_ip() ), 0, 8 );
	foreach ( $ctx as $k => $v ) {
		$line .= ' ' . preg_replace( '/[^a-z0-9_]/', '', strtolower( (string) $k ) ) . '=' . substr( preg_replace( '/[^A-Za-z0-9_.:-]/', '_', (string) $v ), 0, 40 );
	}
	error_log( $line ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

/* ------------------------------------------------------------ cookies / flow */

/** Cookie name: the __Host- prefix (no subdomain can overwrite it) needs HTTPS. */
function lc_auth_cookie_name( $base ) {
	return ( is_ssl() ? '__Host-' : '' ) . $base;
}

/** Set (or with $ttl <= 0 clear) one of our cookies; also updates $_COOKIE. */
function lc_auth_set_cookie( $base, $value, $ttl ) {
	$name = lc_auth_cookie_name( $base );
	if ( $ttl > 0 ) {
		$_COOKIE[ $name ] = $value;
	} else {
		unset( $_COOKIE[ $name ] );
	}
	if ( headers_sent() ) {
		return;
	}
	setcookie(
		$name,
		$ttl > 0 ? $value : '',
		array(
			'expires'  => $ttl > 0 ? time() + $ttl : time() - HOUR_IN_SECONDS,
			'path'     => '/',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}

function lc_auth_get_cookie( $base ) {
	$name = lc_auth_cookie_name( $base );
	return isset( $_COOKIE[ $name ] ) && lc_auth_is_token( $_COOKIE[ $name ] ) ? (string) $_COOKIE[ $name ] : '';
}

/**
 * The sign-in flow cookie: 32 random bytes in an HttpOnly cookie; the server
 * only ever sees/stores its sha256. Everything one-time (wallet challenge,
 * email-link confirm, panel token) is bound to it, which stops login CSRF.
 *
 * @param bool $create Make one when missing (only before output starts).
 * @return string sha256 hex of the cookie, or '' when there is none.
 */
function lc_auth_flow( $create = true ) {
	$value = lc_auth_get_cookie( 'lcauth' );
	if ( '' === $value ) {
		if ( ! $create ) {
			return '';
		}
		$value = lc_auth_new_token();
		lc_auth_set_cookie( 'lcauth', $value, HOUR_IN_SECONDS );
	}
	return hash( 'sha256', $value );
}

/** Drop the flow cookie (after a successful sign-in). */
function lc_auth_flow_clear() {
	lc_auth_set_cookie( 'lcauth', '', 0 );
}

/* ----------------------------------------------------------- requests/responses */

/** Same-origin check for POSTs: Origin must be this site, else Sec-Fetch-Site must say so. */
function lc_auth_same_origin() {
	$home = wp_parse_url( home_url() );
	if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) && is_string( $_SERVER['HTTP_ORIGIN'] ) ) {
		$origin = wp_parse_url( sanitize_text_field( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) );
		return is_array( $origin ) && ! empty( $origin['host'] )
			&& isset( $origin['scheme'] ) && strtolower( $origin['scheme'] ) === strtolower( (string) $home['scheme'] )
			&& strtolower( $origin['host'] ) === strtolower( (string) $home['host'] )
			&& ( isset( $origin['port'] ) ? (int) $origin['port'] : 0 ) === ( isset( $home['port'] ) ? (int) $home['port'] : 0 );
	}
	return isset( $_SERVER['HTTP_SEC_FETCH_SITE'] ) && 'same-origin' === $_SERVER['HTTP_SEC_FETCH_SITE'];
}

/** Whether the caller wants JSON (our fetch() calls send Accept: application/json). */
function lc_auth_wants_json() {
	return ! empty( $_SERVER['HTTP_ACCEPT'] ) && false !== strpos( (string) $_SERVER['HTTP_ACCEPT'], 'application/json' );
}

/**
 * The request's fields: JSON body (when sent as such) merged with $_POST.
 * Strings only, trimmed to a sane size. Nonces are checked by the callers.
 */
function lc_auth_input() {
	$in = array();
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- every caller verifies a nonce, token or cookie binding.
	foreach ( $_POST as $k => $v ) {
		if ( is_string( $v ) ) {
			$in[ (string) $k ] = wp_unslash( $v );
		}
	}
	// phpcs:enable
	$type = isset( $_SERVER['CONTENT_TYPE'] ) ? (string) $_SERVER['CONTENT_TYPE'] : '';
	if ( false !== stripos( $type, 'application/json' ) ) {
		$body = json_decode( (string) file_get_contents( 'php://input', false, null, 0, 20000 ), true );
		if ( is_array( $body ) ) {
			foreach ( $body as $k => $v ) {
				if ( is_string( $v ) ) {
					$in[ (string) $k ] = $v;
				}
			}
		}
	}
	return array_map(
		function ( $v ) {
			return substr( $v, 0, 9000 );
		},
		$in
	);
}

/** Visitor-facing wording for codes (shown on pages and returned in JSON). */
function lc_auth_text( $code ) {
	$text = array(
		'email_sent'      => __( 'If that address can receive email, a sign-in link is on its way. It works for 15 minutes.', 'learncardano' ),
		'email_bad'       => __( 'Please enter a valid email address.', 'learncardano' ),
		'reload'          => __( 'That form was out of date. Please try again.', 'learncardano' ),
		'rate'            => __( 'Too many attempts. Please wait a few minutes and try again.', 'learncardano' ),
		'busy'            => __( 'Something else is updating this account right now. Please try again.', 'learncardano' ),
		'disabled'        => __( 'Sign-in by email or wallet is switched off for now. If you have a password, use that.', 'learncardano' ),
		'signup_closed'   => __( 'New accounts are paused right now. Please try again later.', 'learncardano' ),
		'wallet_signup_full' => __( 'New wallet sign-ups are paused for today. Please try again tomorrow, or sign up with your email address instead.', 'learncardano' ),
		'password_only'   => __( 'This account signs in with a password. Please use the password sign-in.', 'learncardano' ),
		'link_expired'    => __( 'That link has expired or was already used. Request a new one below.', 'learncardano' ),
		'link_failed'     => __( 'We could not sign you in with that link. Request a new one below.', 'learncardano' ),
		'wallet_failed'   => __( 'Wallet sign-in failed. Please try again.', 'learncardano' ),
		'wallet_expired'  => __( 'That wallet request expired. Please try again.', 'learncardano' ),
		'wallet_address'  => __( 'We could not read a stake address from that wallet account.', 'learncardano' ),
		'wallet_network'  => __( 'Please use a Cardano mainnet wallet account.', 'learncardano' ),
		'wallet_conflict' => __( 'This wallet is linked to more than one account. Please contact us so we can sort it out.', 'learncardano' ),
		'wallet_in_use'   => __( 'This wallet already has its own account. Sign in with it there, or remove it from that account first.', 'learncardano' ),
		'wallet_linked'   => __( 'Wallet connected.', 'learncardano' ),
		'oauth_cancelled' => __( 'Sign-in was cancelled. You can try again or use another way to sign in.', 'learncardano' ),
		'oauth_failed'    => __( 'We could not complete that sign-in. Please try again.', 'learncardano' ),
		'oauth_unavailable' => __( 'That sign-in method is not available right now.', 'learncardano' ),
		'oauth_email_exists' => __( 'An account already uses this email address. Sign in with an email link first, then connect Google or Discord on your account page.', 'learncardano' ),
		'idp_in_use'      => __( 'That account is already connected to a different Learn Cardano account. Sign in with it there, or disconnect it from that account first.', 'learncardano' ),
		'idp_has_other'   => __( 'You already have a different account connected for this provider. Disconnect it first.', 'learncardano' ),
		'idp_conflict'    => __( 'That account is connected more than once. Please contact us so we can sort it out.', 'learncardano' ),
		'idp_linked'      => __( 'Account connected.', 'learncardano' ),
		'idp_email_set'   => __( 'Account connected, and its email address is now your verified email.', 'learncardano' ),
		'idp_removed'     => __( 'Account disconnected.', 'learncardano' ),
		'wallet_removed'  => __( 'Wallet removed.', 'learncardano' ),
		'last_method'     => __( 'That is your only way to sign in, so it cannot be removed. Add another one first.', 'learncardano' ),
		'name_invalid'    => __( 'Choose a display name of 2 to 40 characters. It cannot contain "@" or web addresses, look like a wallet address, or copy a staff name.', 'learncardano' ),
		'name_saved'      => __( 'Display name saved.', 'learncardano' ),
		'email_changed'   => __( 'Your email address is changed and verified.', 'learncardano' ),
		'email_verified'  => __( 'Your email address is verified.', 'learncardano' ),
		'email_check'     => __( 'Check your inbox: we sent a link to confirm. Nothing changes until you open it.', 'learncardano' ),
		'password_sent'   => __( 'We emailed you a link to set your password.', 'learncardano' ),
		'password_need'   => __( 'Verify your email address first, then you can set a password.', 'learncardano' ),
		'others_out'      => __( 'Signed out everywhere else.', 'learncardano' ),
		'delete_confirm'  => __( 'To delete your account, type DELETE in the box.', 'learncardano' ),
		'delete_recent'   => __( 'For safety, sign in again to make this change.', 'learncardano' ),
		'blocked'         => __( 'That request was blocked. Please reload the page and try again.', 'learncardano' ),
		'forbidden'       => __( 'You cannot do that.', 'learncardano' ),
		'unknown'         => __( 'Something went wrong. Please try again.', 'learncardano' ),
	);
	return isset( $text[ $code ] ) ? $text[ $code ] : $text['unknown'];
}

/**
 * Answer a handler: JSON for XHR, otherwise a 303 back to a page with
 * ?done=/?error= (the page shows lc_auth_text() of it). Exits.
 *
 * @param bool   $ok       Success?
 * @param string $code     Text code (lc_auth_text()).
 * @param string $url      Where a navigation goes (default: /account/).
 * @param array  $extra    More JSON fields.
 * @param int    $status   HTTP status for JSON.
 * @param int    $retry    Retry-After seconds (429).
 */
function lc_auth_respond( $ok, $code, $url = '', array $extra = array(), $status = 200, $retry = 0 ) {
	lc_auth_no_cache();
	if ( $retry ) {
		header( 'Retry-After: ' . (int) $retry );
	}
	if ( lc_auth_wants_json() ) {
		$body = array_merge(
			array(
				'ok'      => (bool) $ok,
				'code'    => $code,
				'message' => lc_auth_text( $code ),
			),
			$extra
		);
		wp_send_json( $body, $ok ? 200 : $status );
	}
	$url = '' === $url ? lc_auth_account_url() : $url;
	wp_safe_redirect( add_query_arg( $ok ? 'done' : 'error', $code, $url ), 303 );
	exit;
}

/* ------------------------------------------------------------------- redirects */

function lc_auth_account_url( $path = '' ) {
	return home_url( '/account/' . ltrim( $path, '/' ) );
}

/**
 * Keep a redirect_to on this site and off our own flow pages and tokens.
 *
 * @param string $url      Wanted destination.
 * @param string $fallback Used when $url is not acceptable (default /account/).
 */
function lc_auth_safe_redirect( $url, $fallback = '' ) {
	$fallback = '' === $fallback ? lc_auth_account_url() : $fallback;
	$url      = is_string( $url ) ? trim( $url ) : '';
	if ( '' === $url || strlen( $url ) > 2000 ) {
		return $fallback;
	}
	$url = wp_validate_redirect( $url, '' );
	if ( '' === $url ) {
		return $fallback;
	}
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( 0 === strpos( $path, '/account/' ) && '/account/' !== $path ) {
		return $fallback;
	}
	return remove_query_arg( array( 't', 'code', 'state', 'done', 'error', 'lc_action', 'lc_token' ), $url );
}

/** Link to the sign-in page (the modal opens instead when JS runs). */
function lc_auth_signin_url( $redirect = '' ) {
	return '' === $redirect ? lc_auth_account_url() : add_query_arg( 'redirect_to', rawurlencode( $redirect ), lc_auth_account_url() );
}

/** The URL of the page being viewed (for redirect_to in cached HTML). */
function lc_auth_current_url() {
	global $wp;
	return trailingslashit( home_url( isset( $wp->request ) ? $wp->request : '' ) );
}

/* ----------------------------------------------------------------- sign-in/up */

/**
 * Finish a sign-in for a user who has proved themselves by $method. This is
 * the one place that refuses staff (and merged-away) accounts.
 *
 * @return true|WP_Error
 */
function lc_auth_complete_signin( WP_User $user, $method ) {
	if ( ! lc_auth_enabled() ) {
		return new WP_Error( 'lc_auth_disabled', lc_auth_text( 'disabled' ), array( 'status' => 503 ) );
	}
	if ( lc_auth_is_privileged( $user ) ) {
		lc_auth_log( 'refused_privileged', array( 'user' => $user->ID, 'method' => $method ) );
		return new WP_Error( 'lc_auth_password_only', lc_auth_text( 'password_only' ), array( 'status' => 403 ) );
	}
	if ( get_user_meta( $user->ID, 'lc_merged_into', true ) || ! $user->roles ) {
		lc_auth_log( 'refused_merged', array( 'user' => $user->ID, 'method' => $method ) );
		return new WP_Error( 'lc_auth_merged', lc_auth_text( 'wallet_failed' ), array( 'status' => 403 ) );
	}
	wp_set_current_user( $user->ID );
	wp_set_auth_cookie( $user->ID, true, is_ssl() ); // A new session token: no fixation.
	do_action( 'wp_login', $user->user_login, $user );
	lc_auth_flow_clear();
	lc_auth_log( 'signin', array( 'user' => $user->ID, 'method' => $method ) );
	return true;
}

/**
 * Create a member. Login and nicename are random (never an email or a stake
 * address); the password is random and unknown, so a member signs in by
 * email link, wallet, or sets a password through the reset email.
 *
 * @param array $a email, email_verified, display_name, method.
 * @return WP_User|WP_Error
 */
function lc_auth_create_member( array $a ) {
	if ( ! lc_auth_signup_enabled() ) {
		return new WP_Error( 'lc_auth_signup_closed', lc_auth_text( 'signup_closed' ), array( 'status' => 403 ) );
	}
	$retry = lc_auth_rate( 'signup_ip', null, 10, HOUR_IN_SECONDS ) ?: lc_auth_rate( 'signup_coarse', lc_auth_coarse_subject(), 50, HOUR_IN_SECONDS );
	if ( $retry ) {
		return new WP_Error( 'lc_auth_rate', lc_auth_text( 'rate' ), array( 'status' => 429, 'retry' => $retry ) );
	}
	// A flood of sign-ups alerts the owner once a day instead of closing the door.
	if ( lc_auth_rate( 'signup_global', 'all', (int) apply_filters( 'lc_auth_signup_daily_alert', 100 ), DAY_IN_SECONDS ) && ! get_transient( 'lc_auth_signup_alerted' ) ) {
		set_transient( 'lc_auth_signup_alerted', 1, DAY_IN_SECONDS );
		wp_mail( get_option( 'admin_email' ), 'Learn Cardano: unusual number of new sign-ups', 'More than 100 new accounts were created in the last 24 hours. Sign-up stays open; set LC_AUTH_SIGNUP to false in wp-config.php to pause it.' );
	}

	$email = isset( $a['email'] ) ? strtolower( trim( (string) $a['email'] ) ) : '';
	do {
		$login = 'm_' . bin2hex( random_bytes( 6 ) );
	} while ( username_exists( $login ) );

	$name = isset( $a['display_name'] ) && '' !== $a['display_name'] ? (string) $a['display_name'] : 'Member ' . random_int( 1000, 9999 );
	$id   = wp_insert_user(
		array(
			'user_login'           => $login,
			'user_nicename'        => $login,
			'user_pass'            => wp_generate_password( 48, true, true ),
			'user_email'           => $email,
			'display_name'         => $name,
			'nickname'             => $name,
			'role'                 => 'subscriber',
			'show_admin_bar_front' => 'false',
		)
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_user_meta( $id, 'lc_signup', array( 'm' => (string) ( $a['method'] ?? '' ), 't' => time() ) );
	if ( '' !== $email && ! empty( $a['email_verified'] ) ) {
		lc_auth_mark_email_verified( $id, $email );
	}
	lc_auth_log( 'signup', array( 'user' => $id, 'method' => $a['method'] ?? '' ) );
	return get_userdata( $id );
}

/* -------------------------------------------------- email verified / methods */

/**
 * Record that $email was proven by its owner (clicked from the inbox). Only an
 * HMAC of the address is kept: the meta says "this address was verified"
 * without being a second copy of the address in the database.
 */
function lc_auth_mark_email_verified( $user_id, $email ) {
	update_user_meta( $user_id, 'lc_email_verified', lc_auth_hmac( 'ev', strtolower( trim( $email ) ) ) );
	update_user_meta( $user_id, 'lc_email_verified_at', time() );
}

/**
 * Does the stored "verified" value belong to $email? Values written before the
 * HMAC change are the plaintext address (they contain "@"); those are compared
 * as before and, on a match, rewritten as an HMAC on the spot.
 *
 * @param int    $user_id User.
 * @param string $email   Address to check.
 */
function lc_auth_verified_matches( $user_id, $email ) {
	$v     = (string) get_user_meta( $user_id, 'lc_email_verified', true );
	$email = strtolower( trim( (string) $email ) );
	if ( '' === $v || '' === $email ) {
		return false;
	}
	if ( false !== strpos( $v, '@' ) ) {
		if ( strtolower( $v ) !== $email ) {
			return false;
		}
		update_user_meta( $user_id, 'lc_email_verified', lc_auth_hmac( 'ev', $email ) ); // One-off upgrade of a legacy value.
		return true;
	}
	return hash_equals( lc_auth_hmac( 'ev', $email ), $v );
}

/**
 * Verified only while the stored verified address equals user_email, so an
 * address changed any other way (admin, REST) counts as unverified at once.
 */
function lc_auth_email_verified( $user ) {
	$user = is_numeric( $user ) ? get_userdata( (int) $user ) : $user;
	if ( ! $user instanceof WP_User || '' === (string) $user->user_email ) {
		return false;
	}
	return lc_auth_verified_matches( $user->ID, $user->user_email );
}

/** Stake addresses linked to a user (lc_wallet meta, several rows allowed). */
function lc_auth_wallets( $user_id ) {
	return array_values( array_unique( array_filter( array_map( 'strval', (array) get_user_meta( $user_id, 'lc_wallet', false ) ) ) ) );
}

/** Identity providers an account can link (R3). The provider registry itself is in oauth.php. */
function lc_auth_idp_ids() {
	return array( 'google', 'discord' );
}

/** Linked identities of a user: provider => subject (user meta lc_idp_<provider>). */
function lc_auth_idps( $user_id ) {
	$out = array();
	foreach ( lc_auth_idp_ids() as $provider ) {
		$subject = (string) get_user_meta( $user_id, 'lc_idp_' . $provider, true );
		if ( '' !== $subject ) {
			$out[ $provider ] = $subject;
		}
	}
	return $out;
}

/** Ways to sign in: 'email' (only when verified), 'wallet:<stake>', 'idp:<provider>'. */
function lc_auth_methods( $user ) {
	$methods = array();
	if ( lc_auth_email_verified( $user ) ) {
		$methods[] = 'email';
	}
	foreach ( lc_auth_wallets( $user->ID ) as $stake ) {
		$methods[] = 'wallet:' . $stake;
	}
	foreach ( array_keys( lc_auth_idps( $user->ID ) ) as $provider ) {
		$methods[] = 'idp:' . $provider;
	}
	return (array) apply_filters( 'lc_auth_methods', $methods, $user );
}

/**
 * Whether a provider can be used to sign in right now: both wp-config constants
 * set and not empty, then the same lc_auth_provider_enabled filter the provider
 * registry applies. Cheap, so core does not have to load oauth.php.
 */
function lc_auth_idp_enabled( $provider ) {
	if ( ! in_array( $provider, lc_auth_idp_ids(), true ) ) {
		return false;
	}
	$a  = 'LC_' . strtoupper( $provider ) . '_CLIENT_ID';
	$b  = 'LC_' . strtoupper( $provider ) . '_CLIENT_SECRET';
	$on = defined( $a ) && defined( $b ) && is_string( constant( $a ) ) && is_string( constant( $b ) )
		&& '' !== trim( constant( $a ) ) && '' !== trim( constant( $b ) );
	return (bool) apply_filters( 'lc_auth_provider_enabled', $on, $provider );
}

/** The methods that work right now: lc_auth_methods() minus connected providers that are switched off. */
function lc_auth_usable_methods( $user ) {
	return array_values(
		array_filter(
			lc_auth_methods( $user ),
			function ( $m ) {
				return 0 !== strpos( (string) $m, 'idp:' ) || lc_auth_idp_enabled( substr( (string) $m, 4 ) );
			}
		)
	);
}

/** A method may go only while another USABLE one remains (a disabled provider is no way in). */
function lc_auth_can_remove( $user, $key ) {
	$methods = lc_auth_methods( $user );
	return in_array( $key, $methods, true ) && count( array_diff( lc_auth_usable_methods( $user ), array( $key ) ) ) >= 1;
}

/** stake1u8…k2x9 */
function lc_auth_mask_stake( $stake ) {
	return substr( $stake, 0, 8 ) . '…' . substr( $stake, -4 );
}

/** p•••@example.com */
function lc_auth_mask_email( $email ) {
	$at = strpos( $email, '@' );
	return false === $at ? '•••' : substr( $email, 0, 1 ) . '•••' . substr( $email, $at );
}

/* ------------------------------------------------------------------ hooks: users */

/** Application passwords are for staff (the publishing editor), not members. */
add_filter(
	'wp_is_application_passwords_available_for_user',
	function ( $available, $user ) {
		return $available && user_can( $user, 'edit_posts' );
	},
	10,
	2
);

/**
 * Core mails the OLD address when the email changes. On a legacy wallet
 * account that address may have been set by someone else, so only tell an
 * old address that was verified. Every other account (staff included) keeps
 * core's notice.
 */
add_filter(
	'send_email_change_email',
	function ( $send, $user ) {
		$u = get_userdata( (int) $user['ID'] );
		if ( ! $u || ! lc_is_legacy_wallet_account( $u ) ) {
			return $send; // Staff and everyone else keep core's "email changed" notice.
		}
		return $send && lc_auth_verified_matches( (int) $user['ID'], (string) $user['user_email'] );
	},
	10,
	2
);

/**
 * Members (no edit_posts) have nothing to do in wp-admin: send them to
 * /account/. Capability-based on purpose: the owner's admin also has the
 * student role. AJAX, admin-post and uploads keep working.
 */
add_action(
	'admin_init',
	function () {
		if ( ! is_user_logged_in() || wp_doing_ajax() || lc_auth_is_privileged( wp_get_current_user() ) ) {
			return;
		}
		$script = isset( $_SERVER['SCRIPT_NAME'] ) ? basename( (string) $_SERVER['SCRIPT_NAME'] ) : '';
		if ( in_array( $script, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' ), true ) ) {
			return;
		}
		wp_safe_redirect( lc_auth_account_url() );
		exit;
	},
	1
);

/**
 * Login CSRF: wp-login.php takes a password POST from any site, so a page an
 * attacker controls could sign a visitor into the ATTACKER's account (and
 * from there a wallet link or claim would attach to it). Browsers always send
 * Origin or Sec-Fetch-Site on a cross-site POST, so refuse those. Runs first
 * on login_init; the password form on this site is same-origin.
 */
add_action(
	'login_init',
	function () {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
		if ( 'POST' !== $method || lc_auth_same_origin() ) {
			return;
		}
		lc_auth_log( 'blocked_origin', array( 'path' => 'wp-login' ) );
		wp_die( esc_html( lc_auth_text( 'blocked' ) ), '', array( 'response' => 403 ) );
	},
	1
);

/* ------------------------------------------------------------ rewrite + router */

add_filter(
	'query_vars',
	function ( $vars ) {
		$vars[] = 'lc_account';
		$vars[] = 'lc_account_path';
		return $vars;
	}
);

add_action(
	'init',
	function () {
		add_rewrite_rule( '^account(/.*)?$', 'index.php?lc_account=1&lc_account_path=$matches[1]', 'top' );
		add_rewrite_rule( '^dashboard/?$', 'index.php?lc_account=1&lc_account_path=/__dashboard', 'top' );
	}
);

/**
 * Safety net for the rewrite rules: flush once per LC_AUTH_REWRITE_VERSION,
 * after init so every plugin's rules are registered, and only one request
 * does it. (A deploy does not flush; also run `wp rewrite flush`.)
 */
add_action(
	'wp_loaded',
	function () {
		if ( (string) LC_AUTH_REWRITE_VERSION === (string) get_option( 'lc_auth_rewrite_ver' ) ) {
			return;
		}
		lc_auth_lock(
			'rewrite',
			function () {
				if ( (string) LC_AUTH_REWRITE_VERSION !== (string) get_option( 'lc_auth_rewrite_ver' ) ) {
					flush_rewrite_rules( false );
					update_option( 'lc_auth_rewrite_ver', LC_AUTH_REWRITE_VERSION, false );
				}
			}
		);
	}
);

/** True while serving any /account/* route (set by the router). */
function lc_auth_is_private_view( $set = null ) {
	static $private = false;
	if ( true === $set ) {
		$private = true;
	}
	return $private;
}

/** The view the router chose: array( 'view' => name, 'status' => int, 'data' => array ). */
function lc_auth_view( $set = null ) {
	static $view = null;
	if ( is_array( $set ) ) {
		$view = $set + array( 'status' => 200, 'data' => array() );
	}
	return $view;
}

/**
 * Whitelisted routes. path => method => handler. A handler either exits (after
 * lc_auth_respond()/redirect) or returns a view: array( 'view' => …, … ).
 */
function lc_auth_routes() {
	return array(
		''                 => array(
			'GET'  => 'lc_auth_get_home',
			'POST' => 'lc_auth_post_home',
		),
		'panel'            => array( 'GET' => 'lc_auth_get_panel' ),
		'email-link'       => array(
			'GET'  => 'lc_auth_get_email_link',
			'POST' => 'lc_auth_post_email_link',
		),
		'wallet/challenge' => array( 'POST' => 'lc_auth_post_wallet_challenge' ),
		'wallet/verify'    => array( 'POST' => 'lc_auth_post_wallet_verify' ),
		// Google / Discord (oauth.php): start and per-provider callback.
		'oauth/google'           => array( 'GET' => 'lc_auth_get_oauth_start' ),
		'oauth/google/callback'  => array( 'GET' => 'lc_auth_get_oauth_callback' ),
		'oauth/discord'          => array( 'GET' => 'lc_auth_get_oauth_start' ),
		'oauth/discord/callback' => array( 'GET' => 'lc_auth_get_oauth_callback' ),
		'__dashboard'      => array( 'GET' => 'lc_auth_get_dashboard' ),
	);
}

/** No caching anywhere, ever, for these responses. */
function lc_auth_no_cache() {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	if ( headers_sent() ) {
		return;
	}
	nocache_headers();
	header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	header( 'X-Frame-Options: DENY' ); // Nobody frames the account pages (clickjacking).
	header( "Content-Security-Policy: frame-ancestors 'none'" );
}

/**
 * The router. Runs on parse_request before the main query, so handlers cost
 * no database work beyond their own.
 *
 * @param WP $wp The WordPress environment.
 */
function lc_auth_route_request( $wp ) {
	// Only the rewrite rules count: ?lc_account=1 typed on another URL must not route here.
	$rule = isset( $wp->matched_rule ) ? ltrim( (string) $wp->matched_rule, '^' ) : '';
	if ( empty( $wp->query_vars['lc_account'] ) || ( 0 !== strpos( $rule, 'account' ) && 0 !== strpos( $rule, 'dashboard' ) ) ) {
		return;
	}
	lc_auth_is_private_view( true );
	lc_auth_no_cache();
	add_filter( 'redirect_canonical', '__return_false' );
	add_filter( 'wp_headers', 'lc_auth_response_headers' );

	$path   = trim( isset( $wp->query_vars['lc_account_path'] ) ? (string) $wp->query_vars['lc_account_path'] : '', '/' );
	$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
	$method = 'HEAD' === $method ? 'GET' : $method;
	$routes = lc_auth_routes();

	lc_auth_load( 'email' );
	lc_auth_load( 'wallet' );
	if ( 0 === strpos( $path, 'oauth/' ) ) {
		lc_auth_load( 'oauth' ); // Only the oauth routes (and the panel/account page) need it.
	}

	if ( 'POST' === $method && ! lc_auth_same_origin() ) {
		lc_auth_log( 'blocked_origin', array( 'path' => $path ) );
		lc_auth_respond( false, 'blocked', '', array(), 403 );
	}
	if ( ! isset( $routes[ $path ][ $method ] ) || ! is_callable( $routes[ $path ][ $method ] ) ) {
		lc_auth_view( array( 'view' => 'notfound', 'status' => 404 ) );
	} else {
		$view = call_user_func( $routes[ $path ][ $method ] );
		if ( is_array( $view ) ) {
			lc_auth_view( $view );
		}
	}
	lc_auth_prepare_view();
}
add_action( 'parse_request', 'lc_auth_route_request', 1 );

function lc_auth_response_headers( $headers ) {
	$headers['Cache-Control'] = 'no-store, no-cache, must-revalidate, max-age=0, private';
	$headers['X-Robots-Tag']  = 'noindex, nofollow';
	$headers['X-Frame-Options']        = 'DENY';
	$headers['Content-Security-Policy'] = "frame-ancestors 'none'";
	return $headers;
}

/** Wire a view into the theme: no main query, our template, title, noindex. */
function lc_auth_prepare_view() {
	$view = lc_auth_view();
	if ( ! $view ) {
		return;
	}
	add_filter( 'pre_handle_404', '__return_true' );
	add_filter(
		'posts_pre_query',
		function ( $posts, $query ) {
			return $query->is_main_query() ? array() : $posts;
		},
		10,
		2
	);
	add_action(
		'parse_query',
		function ( $query ) {
			if ( $query->is_main_query() ) {
				$query->is_home = false;
			}
		}
	);
	add_filter(
		'template_include',
		function () {
			status_header( (int) lc_auth_view()['status'] );
			return get_stylesheet_directory() . '/template-parts/account/view.php';
		},
		99
	);
	add_filter( 'pre_get_document_title', 'lc_auth_view_title', 99 );
	add_filter( 'rank_math/frontend/title', 'lc_auth_view_title' );
	add_filter(
		'body_class',
		function ( $classes ) {
			$classes[] = 'lc-account';
			return $classes;
		}
	);
	add_filter( 'wp_robots', 'wp_robots_no_robots' );
	add_filter( 'rank_math/frontend/canonical', '__return_false' );
	add_filter(
		'rank_math/frontend/robots',
		function () {
			return array(
				'index'  => 'noindex',
				'follow' => 'nofollow',
			);
		}
	);
	remove_action( 'wp_head', 'rel_canonical' );
}

function lc_auth_view_title() {
	$view  = lc_auth_view();
	$title = 'email-confirm' === $view['view'] ? __( 'Continue signing in', 'learncardano' ) : ( is_user_logged_in() && 'home' === $view['view'] ? __( 'Your account', 'learncardano' ) : __( 'Sign in', 'learncardano' ) );
	return $title . ' - ' . get_bloginfo( 'name' );
}

/** /dashboard/ was the CardanoPress page; it lives at /account/ now. */
function lc_auth_get_dashboard() {
	wp_safe_redirect( lc_auth_account_url(), 301 );
	exit;
}

/* ----------------------------------------------------- caches, GTM, popup, prefetch */

// SiteGround Optimizer (if its dynamic cache is ever turned on) and speculative prefetch must skip /account/*.
add_filter(
	'sgo_exclude_urls_from_cache',
	function ( $list ) {
		return array_merge( (array) $list, array( '/account/*', '/dashboard/*' ) );
	}
);
add_filter(
	'wp_speculation_rules_href_exclude_paths',
	function ( $paths ) {
		$paths[] = '/account/*';
		return $paths;
	}
);

// No ad/analytics tag or newsletter popup on private pages (they carry tokens and account details).
add_filter(
	'lc_newsletter_popup_enabled',
	function ( $on ) {
		return $on && ! lc_auth_is_private_view();
	}
);
