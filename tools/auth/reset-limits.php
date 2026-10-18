<?php
/**
 * Clear the account system's rate-limit counters (STAGING only), so repeated
 * test runs from one IP don't trip the limits:
 *
 *   wp --path=… eval-file ~/lc-tools/r2/auth/reset-limits.php
 *
 * @package CardanoPress Bootstrap
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'production' === wp_get_environment_type() ) {
	exit( 1 );
}
global $wpdb;
$lc_n = $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_lc\_rl\_%' OR option_name LIKE '\_transient\_timeout\_lc\_rl\_%'" );
wp_cache_flush();
WP_CLI::line( 'cleared ' . (int) $lc_n . ' rate-limit rows' );
