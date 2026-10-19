<?php
/**
 * Accounts, part 2: email sign-in ("magic link"), email verification and
 * email change. Loaded by the /account/* router.
 *
 * The flow:
 * 1. POST /account/ lc_action=email_link  (honeypot lc_hp, signed panel token,
 *    rate limits). The answer is ALWAYS the same generic message, so it never
 *    reveals whether an address has an account.
 * 2. The email holds https://…/account/email-link/?t=TOKEN. GET stores the
 *    token in a short HttpOnly cookie and 303s to /account/email-link/ so the
 *    token leaves the URL (history, referrers, analytics). That page only
 *    SHOWS "Continue as p•••@…": mail scanners that prefetch the link use up
 *    nothing.
 * 3. POST /account/email-link/ (button) consumes the token and signs in. The
 *    form carries `c`, an HMAC of the token and the flow cookie, so a
 *    cross-site auto-submit cannot sign someone into an attacker's account.
 *
 * Tokens: 32 random bytes, stored only as an HMAC, 15 minutes, single use.
 * Staff accounts and not-yet-reclaimed wallet accounts never get a token.
 *
 * @package CardanoPress Bootstrap
 */

const LC_AUTH_LINK_TTL = 900;

/* ------------------------------------------------------------- panel token */

/**
 * The sign-in form's signed token: time + flow cookie prefix + HMAC. A bot
 * that submits at once, or without our cookie, is refused; a person has
 * about an hour. $time is for tests.
 */
function lc_auth_panel_token( $time = null ) {
	$t = null === $time ? time() : (int) $time;
	$f = substr( lc_auth_flow(), 0, 16 );
	return $t . '.' . $f . '.' . lc_auth_hmac( 'ml_tok', $t . '|' . $f );
}

function lc_auth_panel_token_ok( $token ) {
	$parts = explode( '.', (string) $token );
	if ( 3 !== count( $parts ) || ! ctype_digit( $parts[0] ) ) {
		return false;
	}
	$flow = lc_auth_flow( false );
	$age  = time() - (int) $parts[0];
	return '' !== $flow
		&& substr( $flow, 0, 16 ) === $parts[1]
		&& hash_equals( lc_auth_hmac( 'ml_tok', $parts[0] . '|' . $parts[1] ), $parts[2] )
		&& $age >= 2 && $age <= HOUR_IN_SECONDS;
}

/* ----------------------------------------------------------------- sending */

/**
 * wp_mail wrapper, and the ONE gate every email of this system passes
 * (sign-in links, wallet-first and password notices, email change and verify
 * links, wallet-linked notices): per-recipient, per-visitor and global caps,
 * see lc_auth_mail_check(). Over a limit the mail is skipped silently and the
 * caller gets the same "sent" answer, so nothing reveals the limit or whether
 * an address has an account. The limits are only LOOKED at first and counted
 * just before the mail goes out, so a skipped mail uses no budget. The security
 * notices (idp_linked, idp_unlinked, wallet_linked) skip the recipient and visitor
 * buckets, keep the global cap and have a small per-recipient daily bucket.
 *
 * Off production wp_mail() returns false by design; that is not an error.
 *
 * @param string         $to      Recipient.
 * @param string         $subject Subject.
 * @param string|Closure $body    Text, or a closure that builds it (so a link
 *                                token is only made when the mail will go out).
 * @param string         $kind    signin|wallet_first|password|email_change|email_verify|wallet_linked|idp_linked|idp_unlinked.
 */
function lc_auth_email_send( $to, $subject, $body, $kind ) {
	// Notices about a change to an existing account (sent to its verified address) must not be
	// suppressible by filling the recipient/visitor buckets: they use a small bucket of their own.
	$security = in_array( $kind, array( 'idp_linked', 'idp_unlinked', 'wallet_linked' ), true );
	$blocked  = lc_auth_mail_check( $to, $security );
	if ( '' !== $blocked ) {
		lc_auth_log( 'mail_capped', array( 'kind' => $kind, 'bucket' => $blocked ) );
		return true;
	}
	lc_auth_mail_count( $to, $security );
	if ( $body instanceof Closure ) {
		$body = $body();
	}
	$sent = wp_mail( $to, $subject, $body );
	lc_auth_log( 'mail', array( 'kind' => $kind, 'sent' => (int) $sent ) );
	return $sent;
}

/**
 * Make a link token and return it. A sign-in address keeps up to 3 live
 * tokens (a 4th drops the oldest), so someone who keeps requesting links for
 * an address cannot cancel the owner's link; every other purpose keeps one,
 * and an email change is kept per user AND address, so another member cannot
 * cancel somebody's pending change by asking for the same address.
 */
function lc_auth_email_issue( $purpose, $email, $user_id, $redirect ) {
	$keep = 'signin' === $purpose ? 3 : 1;
	$slot = 'lc_mli_' . substr( lc_auth_hmac( 'mli', $purpose . '|' . $email . ( 'email_change' === $purpose ? '|' . (int) $user_id : '' ) ), 0, 32 );
	$list = get_transient( $slot );
	$list = is_string( $list ) ? array( $list ) : ( is_array( $list ) ? array_values( $list ) : array() ); // A string is the old single-pointer format.
	$list = array_values( array_filter( $list, fn( $k ) => is_string( $k ) && 0 === strpos( $k, 'lc_ml_' ) && false !== get_transient( $k ) ) );
	while ( count( $list ) >= $keep ) {
		delete_transient( array_shift( $list ) );
	}
	$token = lc_auth_store_put(
		'ml',
		array(
			'p' => $purpose,
			'e' => $email,
			'u' => (int) $user_id,
			'r' => $redirect,
			't' => time(),
		),
		LC_AUTH_LINK_TTL
	);
	$list[] = lc_auth_store_key( 'ml', $token );
	set_transient( $slot, $list, LC_AUTH_LINK_TTL );
	return $token;
}

function lc_auth_email_link_url( $token ) {
	return lc_auth_account_url( 'email-link/?t=' . $token );
}

/* --------------------------------------------------------------- the request */

/**
 * Must this address sign in with a wallet first? True for a not-yet-reclaimed
 * CardanoPress account, and for any account that has a wallet but whose email
 * was never verified (it was set some other way, so it may not be the
 * owner's). Staff are handled separately (password-only).
 *
 * @param WP_User $user Account found by email.
 */
function lc_auth_email_needs_wallet( WP_User $user ) {
	if ( lc_auth_email_verified( $user ) ) {
		return false;
	}
	return lc_is_legacy_wallet_account( $user ) || (bool) lc_auth_wallets( $user->ID );
}

/**
 * Handle "email me a sign-in link".
 *
 * @param array $in email, lc_hp, lc_token, redirect_to.
 * @return array ok, code (lc_auth_text), status, retry.
 */
function lc_auth_email_request( array $in ) {
	$generic = array( 'ok' => true, 'code' => 'email_sent' );
	if ( ! lc_auth_enabled() ) {
		return array( 'ok' => false, 'code' => 'disabled', 'status' => 503 );
	}
	if ( ! empty( $in['lc_hp'] ) ) {
		return $generic; // A bot: look successful, do nothing.
	}
	$email = strtolower( trim( (string) ( $in['email'] ?? '' ) ) );
	if ( ! is_email( $email ) || strlen( $email ) > 254 ) {
		return array( 'ok' => false, 'code' => 'email_bad', 'status' => 400 );
	}
	if ( ! lc_auth_panel_token_ok( $in['lc_token'] ?? '' ) ) {
		return array( 'ok' => false, 'code' => 'reload', 'status' => 400 );
	}
	$retry = lc_auth_rate( 'ml_ip', null, 5, 15 * MINUTE_IN_SECONDS ) ?: lc_auth_rate( 'ml_coarse', lc_auth_coarse_subject(), 25, 15 * MINUTE_IN_SECONDS );
	if ( $retry ) {
		return array( 'ok' => false, 'code' => 'rate', 'status' => 429, 'retry' => $retry );
	}
	// Per address AND visitor, so a stranger hammering an address cannot use up
	// the owner's turns; the absolute ceilings for an address (5 an hour, 10 a
	// day, any visitor) and the site-wide cap are in the mail gate. This one
	// stays silent: the answer must not change.
	if ( lc_auth_rate( 'ml_email_h', lc_auth_email_key( $email ) . '|' . lc_auth_ip_subject(), 3, HOUR_IN_SECONDS ) ) {
		lc_auth_log( 'email_limited', array( 'scope' => 'address' ) );
		return $generic;
	}

	$redirect = lc_auth_safe_redirect( (string) ( $in['redirect_to'] ?? '' ) );
	$user     = get_user_by( 'email', $email );
	if ( $user && lc_auth_is_privileged( $user ) ) {
		lc_auth_email_send(
			$email,
			__( 'Signing in to Learn Crypto', 'learncardano' ),
			sprintf(
				/* translators: 1: login URL, 2: lost password URL */
				__( "This Learn Crypto account signs in with a password, not an email link.\n\nSign in: %1\$s\nForgot it? %2\$s\n\nIf you didn't ask for this, you can ignore this email.", 'learncardano' ),
				wp_login_url(),
				wp_lostpassword_url()
			),
			'password'
		);
	} elseif ( $user && lc_auth_email_needs_wallet( $user ) ) {
		lc_auth_email_send(
			$email,
			__( 'Signing in to Learn Crypto', 'learncardano' ),
			sprintf(
				/* translators: %s: sign-in page URL */
				__( "This email address is on a Learn Crypto account that signs in with a Cardano wallet.\n\nSign in with that wallet first, then verify this address on your account page:\n%s\n\nIf you didn't ask for this, you can ignore this email.", 'learncardano' ),
				lc_auth_account_url()
			),
			'wallet_first'
		);
	} else {
		$uid = $user ? $user->ID : 0;
		lc_auth_email_send(
			$email,
			__( 'Your Learn Crypto sign-in link', 'learncardano' ),
			// Built only if the mail is allowed out: a skipped request makes no token.
			function () use ( $email, $uid, $redirect ) {
				return sprintf(
					/* translators: %s: sign-in link */
					__( "Use this link to sign in to Learn Crypto. It works once and expires in 15 minutes:\n\n%s\n\nIf you didn't ask for this, ignore this email: nobody can sign in without the link.", 'learncardano' ),
					lc_auth_email_link_url( lc_auth_email_issue( 'signin', $email, $uid, $redirect ) )
				);
			},
			'signin'
		);
	}
	return $generic;
}

/**
 * Start an email change (verify-then-switch): the link goes to the NEW
 * address and does nothing until opened, by the same signed-in user.
 */
function lc_auth_email_change_request( WP_User $user, $new ) {
	$new = strtolower( trim( $new ) );
	if ( ! is_email( $new ) || lc_auth_is_privileged( $user ) ) {
		return array( 'ok' => false, 'code' => 'email_bad' );
	}
	$retry = lc_auth_rate( 'email_change', (string) $user->ID, 3, HOUR_IN_SECONDS );
	if ( $retry ) {
		return array( 'ok' => false, 'code' => 'rate', 'retry' => $retry );
	}
	$other = email_exists( $new );
	if ( ! $other || (int) $other === $user->ID ) {
		$uid = $user->ID;
		lc_auth_email_send(
			$new,
			__( 'Confirm your new Learn Crypto email address', 'learncardano' ),
			function () use ( $new, $uid ) {
				return sprintf(
					/* translators: %s: confirmation link */
					__( "Open this link while you are signed in to your Learn Crypto account to make this your email address. It works once and expires in 15 minutes:\n\n%s\n\nIf you didn't ask for this, ignore this email.", 'learncardano' ),
					lc_auth_email_link_url( lc_auth_email_issue( 'email_change', $new, $uid, '' ) )
				);
			},
			'email_change'
		);
	}
	// Same answer when the address belongs to someone else.
	return array( 'ok' => true, 'code' => 'email_check' );
}

/** Email the current address a link that marks it verified. */
function lc_auth_email_verify_request( WP_User $user ) {
	if ( '' === $user->user_email || lc_auth_is_privileged( $user ) ) {
		return array( 'ok' => false, 'code' => 'email_bad' );
	}
	$retry = lc_auth_rate( 'email_verify', (string) $user->ID, 3, HOUR_IN_SECONDS );
	if ( $retry ) {
		return array( 'ok' => false, 'code' => 'rate', 'retry' => $retry );
	}
	$addr = strtolower( $user->user_email );
	$uid  = $user->ID;
	lc_auth_email_send(
		$user->user_email,
		__( 'Verify your Learn Crypto email address', 'learncardano' ),
		function () use ( $addr, $uid ) {
			return sprintf(
				/* translators: %s: confirmation link */
				__( "Open this link to verify this email address for your Learn Crypto account. It works once and expires in 15 minutes:\n\n%s\n\nIf you didn't ask for this, ignore this email.", 'learncardano' ),
				lc_auth_email_link_url( lc_auth_email_issue( 'email_verify', $addr, $uid, '' ) )
			);
		},
		'email_verify'
	);
	return array( 'ok' => true, 'code' => 'email_check' );
}

/** Password set/change: core's reset email, only to a verified address. */
function lc_auth_password_link( WP_User $user ) {
	if ( lc_auth_is_privileged( $user ) || ! lc_auth_email_verified( $user ) ) {
		return array( 'ok' => false, 'code' => 'password_need' );
	}
	$retry = lc_auth_rate( 'password_link', (string) $user->ID, 3, HOUR_IN_SECONDS );
	if ( $retry ) {
		return array( 'ok' => false, 'code' => 'rate', 'retry' => $retry );
	}
	$result = retrieve_password( $user->user_login );
	if ( is_wp_error( $result ) ) {
		lc_auth_log( 'password_link_failed', array( 'code' => $result->get_error_code() ) );
		return array( 'ok' => false, 'code' => 'unknown' );
	}
	return array( 'ok' => true, 'code' => 'password_sent' );
}

/* --------------------------------------------------------- the confirm step */

/** The "c" value that ties the confirm form to this browser's cookies. */
function lc_auth_confirm_binding( $token, $flow ) {
	return lc_auth_hmac( 'mlc', hash( 'sha256', $token ) . '|' . $flow );
}

/**
 * Consume a link token and act on it.
 *
 * @param string $token The token from the short-lived cookie.
 * @param string $c     The form's binding value.
 * @return array|WP_Error array( redirect => url ) or WP_Error whose code is an lc_auth_text() code.
 */
function lc_auth_email_confirm( $token, $c ) {
	$flow = lc_auth_flow( false );
	if ( '' === $flow || ! lc_auth_is_token( $token ) ) {
		return new WP_Error( 'link_expired' );
	}
	if ( ! hash_equals( lc_auth_confirm_binding( $token, $flow ), (string) $c ) ) {
		return new WP_Error( 'link_failed' );
	}
	$rec = lc_auth_store_get( 'ml', $token );
	if ( ! $rec ) {
		return new WP_Error( 'link_expired' );
	}
	// An email change belongs to one signed-in user; check before using the link up.
	if ( 'email_change' === $rec['p'] && get_current_user_id() !== (int) $rec['u'] ) {
		return new WP_Error( 'link_failed' );
	}
	$rec = lc_auth_store_take( 'ml', $token );
	if ( ! $rec ) {
		return new WP_Error( 'link_expired' );
	}
	$email = (string) $rec['e'];

	if ( 'email_verify' === $rec['p'] ) {
		$user = get_userdata( (int) $rec['u'] );
		if ( ! $user || strtolower( $user->user_email ) !== $email ) {
			return new WP_Error( 'link_failed' );
		}
		lc_auth_mark_email_verified( $user->ID, $email );
		lc_auth_log( 'email_verified', array( 'user' => $user->ID ) );
		return array( 'redirect' => add_query_arg( 'done', 'email_verified', lc_auth_account_url() ) );
	}

	if ( 'email_change' === $rec['p'] ) {
		$user = get_userdata( (int) $rec['u'] );
		if ( ! $user || lc_auth_is_privileged( $user ) ) {
			return new WP_Error( 'link_failed' );
		}
		$result = lc_auth_lock(
			'email:' . md5( $email ),
			function () use ( $user, $email ) {
				$other = email_exists( $email );
				if ( $other && (int) $other !== $user->ID ) {
					return new WP_Error( 'link_failed' );
				}
				$res = wp_update_user( array( 'ID' => $user->ID, 'user_email' => $email ) );
				if ( is_wp_error( $res ) ) {
					return new WP_Error( 'link_failed' );
				}
				lc_auth_mark_email_verified( $user->ID, $email );
				return true;
			}
		);
		if ( true !== $result ) {
			return is_wp_error( $result ) && 'lc_auth_busy' !== $result->get_error_code() ? $result : new WP_Error( 'busy' );
		}
		lc_auth_log( 'email_changed', array( 'user' => $user->ID ) );
		return array( 'redirect' => add_query_arg( 'done', 'email_changed', lc_auth_account_url() ) );
	}

	// Sign in (or sign up).
	if ( 'signin' !== $rec['p'] ) {
		return new WP_Error( 'link_failed' );
	}
	$result = lc_auth_lock(
		'email:' . md5( $email ),
		function () use ( $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				if ( lc_auth_is_privileged( $user ) ) {
					return new WP_Error( 'password_only' );
				}
				if ( lc_auth_email_needs_wallet( $user ) ) {
					return new WP_Error( 'link_failed' ); // Could have been set by an attacker: wallet first.
				}
				if ( ! lc_auth_email_verified( $user ) ) {
					// Unverified address on a plain account: someone may have put it there
					// (REST/admin) and kept a password or session. Whoever opens this link is
					// the real owner, so cut every other way in before signing them in.
					// Order matters: close the ways in first (identities, password), end the
					// sessions last, so a sign-in racing this cannot keep a session.
					lc_auth_load( 'oauth' );
					$idps = array_keys( lc_auth_idps( $user->ID ) );
					foreach ( $idps as $provider ) {
						lc_auth_idp_remove( $user->ID, $provider );
					}
					wp_set_password( wp_generate_password( 48, true, true ), $user->ID );
					WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
					lc_auth_log( 'email_takeover_revoked', array( 'user' => $user->ID, 'idps_removed' => count( $idps ) ) );
				}
				lc_auth_mark_email_verified( $user->ID, $email );
			} else {
				$user = lc_auth_create_member(
					array(
						'email'          => $email,
						'email_verified' => true,
						'method'         => 'email',
					)
				);
				if ( is_wp_error( $user ) ) {
					return new WP_Error( 'lc_auth_signup_closed' === $user->get_error_code() ? 'signup_closed' : ( 'lc_auth_rate' === $user->get_error_code() ? 'rate' : 'link_failed' ) );
				}
			}
			if ( is_user_logged_in() && get_current_user_id() !== $user->ID ) {
				wp_destroy_current_session(); // Switching accounts: end the old session.
			}
			$signed = lc_auth_complete_signin( $user, 'email' );
			return is_wp_error( $signed ) ? new WP_Error( 'password_only' ) : true;
		}
	);
	if ( true !== $result ) {
		return is_wp_error( $result ) && 'lc_auth_busy' !== $result->get_error_code() ? $result : new WP_Error( 'busy' );
	}
	return array( 'redirect' => lc_auth_safe_redirect( (string) $rec['r'] ) );
}

/* ----------------------------------------------------------------- routes */

/** GET /account/email-link/ : with ?t= move the token into a cookie; without, show the confirm page. */
function lc_auth_get_email_link() {
	if ( isset( $_GET['t'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the token is the credential.
		$t = is_string( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( lc_auth_is_token( $t ) ) {
			lc_auth_set_cookie( 'lcml', $t, 10 * MINUTE_IN_SECONDS );
		}
		header( 'Referrer-Policy: no-referrer' );
		wp_safe_redirect( lc_auth_account_url( 'email-link/' ), 303 );
		exit;
	}
	$token = lc_auth_get_cookie( 'lcml' );
	$rec   = $token ? lc_auth_store_get( 'ml', $token ) : null;
	$flow  = lc_auth_flow( true ); // Cookie must be set before output.
	if ( ! $rec ) {
		return array(
			'view'   => 'email-confirm',
			'status' => 410,
			'data'   => array( 'state' => 'invalid' ),
		);
	}
	$labels = array(
		'signin'       => __( 'Continue as', 'learncardano' ),
		'email_change' => __( 'Make this your email address:', 'learncardano' ),
		'email_verify' => __( 'Verify the address', 'learncardano' ),
	);
	return array(
		'view' => 'email-confirm',
		'data' => array(
			'state'   => 'ready',
			'purpose' => $rec['p'],
			'label'   => $labels[ $rec['p'] ] ?? '',
			'masked'  => lc_auth_mask_email( (string) $rec['e'] ),
			'c'       => lc_auth_confirm_binding( $token, $flow ),
			'other'   => is_user_logged_in() && 'signin' === $rec['p'] ? wp_get_current_user()->display_name : '',
		),
	);
}

/** POST /account/email-link/ : the confirm button. */
function lc_auth_post_email_link() {
	if ( ! lc_auth_enabled() ) {
		lc_auth_respond( false, 'disabled', '', array(), 503 );
	}
	$retry = lc_auth_rate( 'ml_confirm', null, 20, 15 * MINUTE_IN_SECONDS );
	if ( $retry ) {
		lc_auth_respond( false, 'rate', '', array(), 429, $retry );
	}
	$in     = lc_auth_input();
	$result = lc_auth_email_confirm( lc_auth_get_cookie( 'lcml' ), (string) ( $in['c'] ?? '' ) );
	lc_auth_set_cookie( 'lcml', '', 0 );
	if ( is_wp_error( $result ) ) {
		lc_auth_log( 'email_confirm_failed', array( 'code' => $result->get_error_code() ) );
		lc_auth_respond( false, $result->get_error_code() );
	}
	wp_safe_redirect( $result['redirect'], 303 );
	exit;
}
