<?php
/**
 * Staging test mail capture. Only loaded when the environment is not
 * production (functions.php).
 *
 * Staging never sends mail (includes/mail.php), so tests could not read
 * sign-in links. This keeps a copy of messages addressed ONLY to
 * *@lc-test.invalid (a domain that can never belong to a person, so no real
 * address or token is ever stored) in an autoload-off option: at most 20
 * entries, pruned after an hour. Never exposed over HTTP; read it with
 * WP-CLI (tools/auth/outbox.php, or lc_auth_dev_outbox_latest()).
 *
 * @package CardanoPress Bootstrap
 */

const LC_AUTH_OUTBOX_OPTION = 'lc_auth_dev_outbox';

function lc_auth_dev_outbox_is_test_address( $address ) {
	return 1 === preg_match( '/@lc-test\.invalid>?$/i', trim( (string) $address ) );
}

/** Capture before mail.php's blocker (priority 1) runs; return null so it still blocks. */
add_filter(
	'pre_wp_mail',
	function ( $return, $atts ) {
		$to = array_filter( array_map( 'trim', is_array( $atts['to'] ) ? $atts['to'] : explode( ',', (string) $atts['to'] ) ) );
		if ( ! $to || array_filter( $to, fn( $a ) => ! lc_auth_dev_outbox_is_test_address( $a ) ) ) {
			return $return;
		}
		$box   = (array) get_option( LC_AUTH_OUTBOX_OPTION, array() );
		$cut   = time() - HOUR_IN_SECONDS;
		$box   = array_values( array_filter( $box, fn( $m ) => $m['t'] >= $cut ) );
		$box[] = array(
			't'       => time(),
			'to'      => array_values( $to ),
			'subject' => (string) $atts['subject'],
			'message' => (string) $atts['message'],
		);
		update_option( LC_AUTH_OUTBOX_OPTION, array_slice( $box, -20 ), false );
		return $return;
	},
	0,
	2
);

/** The newest captured message to an address (or null). */
function lc_auth_dev_outbox_latest( $email ) {
	$email = strtolower( $email );
	foreach ( array_reverse( (array) get_option( LC_AUTH_OUTBOX_OPTION, array() ) ) as $m ) {
		foreach ( $m['to'] as $a ) {
			if ( strtolower( trim( preg_replace( '/^.*<|>$/', '', $a ) ) ) === $email ) {
				return $m;
			}
		}
	}
	return null;
}

/** The first /account/email-link/ URL in the newest message to an address. */
function lc_auth_dev_outbox_link( $email ) {
	$m = lc_auth_dev_outbox_latest( $email );
	return $m && preg_match( '#https?://\S+/account/email-link/\?t=[A-Za-z0-9_-]+#', $m['message'], $hit ) ? $hit[0] : null;
}

function lc_auth_dev_outbox_clear() {
	delete_option( LC_AUTH_OUTBOX_OPTION );
}
