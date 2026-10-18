<?php
/**
 * Accounts, part 3: Cardano wallet sign-in and linking (CIP-30 signData).
 * Loaded by the /account/* router. The cryptography is in cardano-crypto.php.
 *
 * Flow (all JSON, all POST, same-origin only):
 * 1. /account/wallet/challenge {reward, intent}: the wallet's reward address
 *    (mainnet only) and "signin" or "link". The server answers with a message
 *    to sign (host, stake address, nonce, expiry) and remembers it, bound to
 *    the browser's flow cookie, the exact reward address and, for "link", the
 *    signed-in user and session.
 * 2. The wallet signs the message with its STAKE key.
 * 3. /account/wallet/verify {nonce, signature, key, redirect_to}: the
 *    challenge is used up (single use), the signature is checked, and the
 *    stake key must hash to the credential in the stake address. That last
 *    check is the one CardanoPress was missing.
 *
 * Accounts are found by `lc_wallet` user meta (several rows per user are
 * allowed). Accounts CardanoPress created have a login of md5(stake address);
 * they are adopted lazily, the first time their wallet signs in. Staff accounts
 * are never reachable this way. `cardanopress_connected_stake` is ignored:
 * the old plugin let any signed-in user attach any stake address.
 *
 * @package CardanoPress Bootstrap
 */

require_once __DIR__ . '/cardano-crypto.php';

const LC_AUTH_CHALLENGE_TTL = 600;

/* -------------------------------------------------------------- the message */

/**
 * The exact text a wallet is asked to sign. Built only here, stored, and
 * compared byte for byte by the verifier.
 */
function lc_auth_wallet_message( $stake, $intent, $nonce, $issued, $expires ) {
	$sign_in = 'signin' === $intent;
	return wp_parse_url( home_url(), PHP_URL_HOST ) . ' wants you to ' . ( $sign_in ? 'sign in with your Cardano wallet.' : 'connect this wallet to your Learn Cardano account.' ) . "\n\n"
		. 'Stake address: ' . $stake . "\n"
		. "Network: Mainnet\n"
		. 'URI: ' . home_url() . "\n"
		. 'Purpose: ' . ( $sign_in ? 'Sign in' : 'Link wallet' ) . "\n"
		. 'Nonce: ' . $nonce . "\n"
		. 'Issued At: ' . gmdate( 'Y-m-d\TH:i:s\Z', $issued ) . "\n"
		. 'Expiration Time: ' . gmdate( 'Y-m-d\TH:i:s\Z', $expires ) . "\n\n"
		. 'This only proves you control this wallet. It is not a transaction and costs nothing.';
}

function lc_auth_wallet_error( $code, $status, array $extra = array() ) {
	return new WP_Error( $code, lc_auth_text( $code ), array( 'status' => $status ) + $extra );
}

/** sha256 of the current login session token ('' = none, e.g. application passwords). */
function lc_auth_session_hash() {
	$token = wp_get_session_token();
	return '' === $token ? '' : hash( 'sha256', $token );
}

/**
 * Step 1.
 *
 * @param array $in reward, intent, nonce (page nonce, link only).
 * @return array|WP_Error nonce, message, stake, expires.
 */
function lc_auth_wallet_challenge( array $in ) {
	if ( ! lc_auth_enabled() ) {
		return lc_auth_wallet_error( 'disabled', 503 );
	}
	$retry = lc_auth_rate( 'wallet_ch', null, 20, 5 * MINUTE_IN_SECONDS );
	if ( $retry ) {
		return lc_auth_wallet_error( 'rate', 429, array( 'retry' => $retry ) );
	}
	$intent  = isset( $in['intent'] ) ? $in['intent'] : '';
	$user_id = 0;
	$session = '';
	if ( 'link' === $intent ) {
		$user = wp_get_current_user();
		if ( ! $user->exists() ) {
			return lc_auth_wallet_error( 'forbidden', 401 );
		}
		if ( lc_auth_is_privileged( $user ) ) {
			return lc_auth_wallet_error( 'password_only', 403 );
		}
		$session = lc_auth_session_hash();
		if ( '' === $session || ! wp_verify_nonce( (string) ( $in['nonce'] ?? '' ), 'lc_wallet_link' ) ) {
			return lc_auth_wallet_error( 'reload', 403 );
		}
		$user_id = $user->ID;
	} elseif ( 'signin' !== $intent ) {
		return lc_auth_wallet_error( 'wallet_failed', 400 );
	}

	$raw = lc_cardano_reward_bytes( (string) ( $in['reward'] ?? '' ) );
	if ( null === $raw ) {
		return lc_auth_wallet_error( 'wallet_address', 400 );
	}
	if ( 0xE1 !== ord( $raw[0] ) ) {
		return lc_auth_wallet_error( 'wallet_network', 400 ); // Mainnet only.
	}

	$flow    = lc_auth_flow( true );
	$nonce   = bin2hex( random_bytes( 16 ) );
	$now     = time();
	$stake   = lc_cardano_stake_bech32( $raw );
	$message = lc_auth_wallet_message( $stake, $intent, $nonce, $now, $now + LC_AUTH_CHALLENGE_TTL );
	lc_auth_store_put(
		'wc',
		array(
			'fh' => $flow,
			'i'  => $intent,
			'u'  => $user_id,
			's'  => $session,
			'm'  => $message,
			'a'  => bin2hex( $raw ),
			't'  => $now,
		),
		LC_AUTH_CHALLENGE_TTL,
		$nonce
	);
	return array(
		'nonce'   => $nonce,
		'message' => $message,
		'stake'   => $stake,
		'expires' => gmdate( 'Y-m-d\TH:i:s\Z', $now + LC_AUTH_CHALLENGE_TTL ),
	);
}

/* ------------------------------------------------------------------ accounts */

/** Users with this stake address in their lc_wallet meta (merged-away accounts excluded). */
function lc_auth_wallet_owners( $stake ) {
	global $wpdb;
	$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'lc_wallet' AND meta_value = %s LIMIT 5", $stake ) ) );
	return array_values( array_filter( $ids, fn( $id ) => ! get_user_meta( $id, 'lc_merged_into', true ) ) );
}

/** The CardanoPress-era account for a stake address (login = md5), if it is still live. */
function lc_auth_wallet_legacy_account( $stake ) {
	$user = get_user_by( 'login', md5( $stake ) );
	return $user && lc_is_legacy_wallet_account( $user ) && ! get_user_meta( $user->ID, 'lc_merged_into', true ) ? $user : null;
}

function lc_auth_wallet_add( $user_id, $stake ) {
	if ( ! in_array( $stake, lc_auth_wallets( $user_id ), true ) ) {
		add_user_meta( $user_id, 'lc_wallet', $stake );
	}
	$info           = (array) get_user_meta( $user_id, 'lc_wallet_info', true );
	$info[ $stake ] = array(
		'linked' => $info[ $stake ]['linked'] ?? time(),
		'used'   => time(),
	);
	update_user_meta( $user_id, 'lc_wallet_info', $info );
}

function lc_auth_wallet_remove( $user_id, $stake ) {
	delete_user_meta( $user_id, 'lc_wallet', $stake );
	$info = (array) get_user_meta( $user_id, 'lc_wallet_info', true );
	unset( $info[ $stake ] );
	update_user_meta( $user_id, 'lc_wallet_info', $info );
}

/**
 * May $old's wallet be moved to the signed-in user? Only a CardanoPress-era
 * account with no verified email and no other way in: proving the wallet
 * proves the account is theirs, and nobody else can use it.
 */
function lc_auth_wallet_claimable( WP_User $old, $stake ) {
	return lc_is_legacy_wallet_account( $old )
		&& ! lc_auth_is_privileged( $old )
		&& ! lc_auth_email_verified( $old )
		&& ! array_diff( lc_auth_methods( $old ), array( 'wallet:' . $stake ) );
}

/** Move a wallet and the old account's comments to $new, retire $old. */
function lc_auth_wallet_claim( WP_User $old, WP_User $new, $stake ) {
	global $wpdb;
	lc_auth_wallet_remove( $old->ID, $stake );
	lc_auth_wallet_add( $new->ID, $stake );

	$comments = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE user_id = %d", $old->ID ) ) );
	$wpdb->update( $wpdb->comments, array( 'user_id' => $new->ID ), array( 'user_id' => $old->ID ) );
	clean_comment_cache( $comments );

	update_user_meta( $old->ID, 'lc_merged_into', $new->ID );
	if ( '' !== $old->user_email ) {
		update_user_meta( $old->ID, 'lc_legacy_email', $old->user_email );
		$wpdb->update( $wpdb->users, array( 'user_email' => '' ), array( 'ID' => $old->ID ) );
		clean_user_cache( $old->ID );
	}
	$old->set_role( '' );
	WP_Session_Tokens::get_instance( $old->ID )->destroy_all();
	lc_auth_log( 'wallet_claim', array( 'user' => $new->ID, 'from' => $old->ID, 'comments' => count( $comments ) ) );
}

/**
 * Sign in (or sign up) with a proven stake address.
 *
 * @return array|WP_Error new_account, nudge_email.
 */
function lc_auth_wallet_signin( $stake ) {
	$new_account = false;
	$result      = lc_auth_lock(
		'wallet:' . md5( $stake ),
		function () use ( $stake, &$new_account ) {
			$owners = lc_auth_wallet_owners( $stake );
			if ( count( $owners ) > 1 ) {
				lc_auth_log( 'wallet_conflict', array( 'count' => count( $owners ) ) );
				return lc_auth_wallet_error( 'wallet_conflict', 409 );
			}
			$user = $owners ? get_userdata( $owners[0] ) : lc_auth_wallet_legacy_account( $stake );
			if ( $user ) {
				if ( lc_auth_is_privileged( $user ) ) {
					lc_auth_log( 'refused_privileged', array( 'user' => $user->ID, 'method' => 'wallet' ) );
					return lc_auth_wallet_error( 'password_only', 403 );
				}
				lc_auth_wallet_add( $user->ID, $stake ); // Adopts a legacy account; updates "last used".
			} elseif ( get_user_by( 'login', md5( $stake ) ) ) {
				return lc_auth_wallet_error( 'wallet_failed', 403 ); // A merged-away account: never recreate.
			} else {
				$user = lc_auth_create_member( array( 'method' => 'wallet' ) );
				if ( is_wp_error( $user ) ) {
					$code = $user->get_error_code();
					return lc_auth_wallet_error( 'lc_auth_rate' === $code ? 'rate' : ( 'lc_auth_signup_closed' === $code ? 'signup_closed' : 'wallet_failed' ), 'lc_auth_rate' === $code ? 429 : 403 );
				}
				lc_auth_wallet_add( $user->ID, $stake );
				$new_account = true;
			}
			if ( is_user_logged_in() && get_current_user_id() !== $user->ID ) {
				wp_destroy_current_session();
			}
			$signed = lc_auth_complete_signin( $user, 'wallet' );
			if ( is_wp_error( $signed ) ) {
				return lc_auth_wallet_error( 'lc_auth_disabled' === $signed->get_error_code() ? 'disabled' : 'password_only', 403 );
			}
			return array(
				'new_account'  => $new_account,
				'nudge_email'  => '' === $user->user_email,
			);
		}
	);
	return is_wp_error( $result ) && 'lc_auth_busy' === $result->get_error_code() ? lc_auth_wallet_error( 'busy', 409 ) : $result;
}

/**
 * Add a proven stake address to the signed-in user.
 *
 * @return array|WP_Error wallets (masked).
 */
function lc_auth_wallet_link( WP_User $user, $stake ) {
	$result = lc_auth_lock(
		'wallet:' . md5( $stake ),
		function () use ( $user, $stake ) {
			$others = array_values( array_diff( lc_auth_wallet_owners( $stake ), array( $user->ID ) ) );
			$legacy = lc_auth_wallet_legacy_account( $stake );
			if ( $legacy && $legacy->ID !== $user->ID && ! in_array( $legacy->ID, $others, true ) ) {
				$others[] = $legacy->ID; // A CardanoPress account that has not signed in here yet.
			}
			if ( count( $others ) > 1 ) {
				lc_auth_log( 'wallet_conflict', array( 'count' => count( $others ) ) );
				return lc_auth_wallet_error( 'wallet_conflict', 409 );
			}
			if ( $others ) {
				$old = get_userdata( $others[0] );
				if ( ! $old || ! lc_auth_wallet_claimable( $old, $stake ) ) {
					return lc_auth_wallet_error( 'wallet_in_use', 409 );
				}
				lc_auth_wallet_claim( $old, $user, $stake );
			} else {
				lc_auth_wallet_add( $user->ID, $stake );
				lc_auth_log( 'wallet_link', array( 'user' => $user->ID ) );
			}
			return array( 'wallets' => array_map( 'lc_auth_mask_stake', lc_auth_wallets( $user->ID ) ) );
		}
	);
	return is_wp_error( $result ) && 'lc_auth_busy' === $result->get_error_code() ? lc_auth_wallet_error( 'busy', 409 ) : $result;
}

/* -------------------------------------------------------------------- step 3 */

/**
 * Step 3.
 *
 * @param array $in nonce, signature, key, redirect_to.
 * @return array|WP_Error
 */
function lc_auth_wallet_verify( array $in ) {
	if ( ! lc_auth_enabled() ) {
		return lc_auth_wallet_error( 'disabled', 503 );
	}
	$retry = lc_auth_rate( 'wallet_verify', null, 10, 5 * MINUTE_IN_SECONDS );
	if ( $retry ) {
		return lc_auth_wallet_error( 'rate', 429, array( 'retry' => $retry ) );
	}
	$nonce = (string) ( $in['nonce'] ?? '' );
	$sig   = (string) ( $in['signature'] ?? '' );
	$key   = (string) ( $in['key'] ?? '' );
	if ( 1 !== preg_match( '/^[0-9a-f]{32}$/', $nonce ) || ! ctype_xdigit( $sig ) || ! ctype_xdigit( $key ) ) {
		return lc_auth_wallet_error( 'wallet_failed', 400 );
	}
	$rec = lc_auth_store_take( 'wc', $nonce );
	if ( ! $rec ) {
		return lc_auth_wallet_error( 'wallet_expired', 410 );
	}

	// From here every failure is one generic 400; the reason only goes to the log.
	$fail = function ( $why ) {
		lc_auth_log( 'wallet_verify_failed', array( 'why' => $why ) );
		return lc_auth_wallet_error( 'wallet_failed', 400 );
	};
	$flow = lc_auth_flow( false );
	if ( '' === $flow || ! hash_equals( (string) $rec['fh'], $flow ) ) {
		return $fail( 'flow_cookie' );
	}
	$user = null;
	if ( 'link' === $rec['i'] ) {
		$user    = wp_get_current_user();
		$session = lc_auth_session_hash();
		if ( ! $user->exists() || $user->ID !== (int) $rec['u'] || '' === $session || ! hash_equals( (string) $rec['s'], $session ) || lc_auth_is_privileged( $user ) ) {
			return $fail( 'session' );
		}
	}
	$raw    = hex2bin( (string) $rec['a'] );
	$result = lc_cose_verify_cip30( strtolower( $sig ), strtolower( $key ), (string) $rec['m'], $raw );
	if ( true !== $result ) {
		return $fail( 'crypto:' . $result );
	}
	$stake = lc_cardano_stake_bech32( $raw );

	if ( 'link' === $rec['i'] ) {
		return lc_auth_wallet_link( $user, $stake );
	}
	$done = lc_auth_wallet_signin( $stake );
	if ( is_wp_error( $done ) ) {
		return $done;
	}
	$done['redirect'] = lc_auth_safe_redirect( (string) ( $in['redirect_to'] ?? '' ) );
	return $done;
}

/* ------------------------------------------------------------------ routes */

/** Send a wallet result as JSON (these routes are XHR-only). Exits. */
function lc_auth_wallet_send( $result ) {
	lc_auth_no_cache();
	if ( is_wp_error( $result ) ) {
		$data = $result->get_error_data();
		if ( ! empty( $data['retry'] ) ) {
			header( 'Retry-After: ' . (int) $data['retry'] );
		}
		wp_send_json(
			array(
				'ok'      => false,
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
			),
			isset( $data['status'] ) ? (int) $data['status'] : 400
		);
	}
	wp_send_json( array( 'ok' => true ) + $result );
}

function lc_auth_post_wallet_challenge() {
	lc_auth_wallet_send( lc_auth_wallet_challenge( lc_auth_input() ) );
}

function lc_auth_post_wallet_verify() {
	lc_auth_wallet_send( lc_auth_wallet_verify( lc_auth_input() ) );
}
