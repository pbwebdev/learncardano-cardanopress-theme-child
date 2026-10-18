<?php
/**
 * Print the newest sign-in link mailed to a test address (staging only):
 *
 *   wp --path=… eval-file ~/lc-tools/auth/outbox.php email=x@lc-test.invalid
 *
 * Reads the dev outbox (includes/auth/dev-outbox.php), which only ever holds
 * mail to *@lc-test.invalid. Never exposed over HTTP.
 *
 * @package CardanoPress Bootstrap
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! function_exists( 'lc_auth_dev_outbox_link' ) ) {
	exit( 1 );
}
$lc_email = '';
foreach ( isset( $args ) ? $args : array() as $lc_arg ) {
	if ( 0 === strpos( $lc_arg, 'email=' ) ) {
		$lc_email = substr( $lc_arg, 6 );
	}
}
$lc_link = lc_auth_dev_outbox_link( $lc_email );
if ( ! $lc_link ) {
	WP_CLI::error( 'No link for that address.' );
}
WP_CLI::line( $lc_link );
