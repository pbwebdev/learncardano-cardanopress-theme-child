<?php
/**
 * Small hardening tweaks that used to come from security plugins: no
 * anonymous user enumeration over REST, no XML-RPC, no WordPress version in
 * the markup, a Referrer-Policy header, and lc_client_ip(), the one place
 * that decides which visitor IP to trust (rate limits use it).
 *
 * @package CardanoPress Bootstrap
 */

/**
 * Hide the user list and user lookups from everyone who cannot already see
 * users: they leak author logins (slugs) for brute-force attempts, and
 * /wp/v2/users/me lets any member rewrite their own user_email with no
 * verification (an account pre-hijack: a later email sign-in for that
 * address would land in the attacker's account). Gated on capability, not on
 * login state; the publishing editor (edit_posts, application password)
 * keeps everything.
 */
add_filter(
	'rest_endpoints',
	function ( $endpoints ) {
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'list_users' ) ) {
			unset(
				$endpoints['/wp/v2/users'],
				$endpoints['/wp/v2/users/(?P<id>[\d]+)'],
				$endpoints['/wp/v2/users/me']
			);
		}
		return $endpoints;
	}
);

/**
 * Belt and braces for the filter above (routes can come back through other
 * plugins): refuse every write to /wp/v2/users* from a non-staff user.
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $server, $request ) {
		if ( null !== $result || 'GET' === $request->get_method() || 0 !== strpos( $request->get_route(), '/wp/v2/users' ) ) {
			return $result;
		}
		$user       = wp_get_current_user();
		$privileged = function_exists( 'lc_auth_is_privileged' ) ? lc_auth_is_privileged( $user ) : current_user_can( 'edit_posts' );
		if ( $privileged ) {
			return $result;
		}
		return new WP_Error( 'rest_forbidden', __( 'Sorry, you are not allowed to do that.', 'learncardano' ), array( 'status' => 403 ) );
	},
	10,
	3
);

/**
 * XML-RPC is unused (the automation talks REST) and a common brute-force and
 * pingback-amplification target, so switch it off completely.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter(
	'xmlrpc_methods',
	function () {
		return array();
	}
);
add_filter(
	'wp_headers',
	function ( $headers ) {
		unset( $headers['X-Pingback'] );
		return $headers;
	}
);

/**
 * Don't advertise the WordPress version (meta generator, feeds).
 */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/**
 * Referrer-Policy for front-end responses: full URLs stay on the site, other
 * sites only see the origin. Skipped when PHP already sent one.
 */
add_action(
	'send_headers',
	function () {
		if ( headers_sent() ) {
			return;
		}
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'referrer-policy:' ) ) {
				return;
			}
		}
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	}
);

/**
 * Cloudflare's published edge ranges (https://www.cloudflare.com/ips-v4 and
 * /ips-v6, fetched 2026-10-18). Only requests arriving from these may set
 * the client IP through CF-Connecting-IP. Filter `lc_cloudflare_ip_ranges`
 * if Cloudflare adds ranges before this list is updated.
 *
 * @return string[] CIDR ranges.
 */
function lc_cloudflare_ip_ranges() {
	$ranges = array(
		// IPv4.
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		// IPv6.
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);
	return (array) apply_filters( 'lc_cloudflare_ip_ranges', $ranges );
}

/**
 * Whether an IP (v4 or v6) is inside a CIDR range of the same family.
 */
function lc_ip_in_cidr( $ip, $cidr ) {
	$parts  = explode( '/', (string) $cidr, 2 );
	$subnet = $parts[0];
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) || ! filter_var( $subnet, FILTER_VALIDATE_IP ) ) {
		return false;
	}
	$ip_bin  = inet_pton( $ip );
	$net_bin = inet_pton( $subnet );
	if ( false === $ip_bin || false === $net_bin || strlen( $ip_bin ) !== strlen( $net_bin ) ) {
		return false;
	}
	$max  = strlen( $ip_bin ) * 8;
	$bits = isset( $parts[1] ) && ctype_digit( $parts[1] ) ? min( (int) $parts[1], $max ) : $max;

	// Compare the whole bytes of the prefix, then the leftover bits.
	$bytes = intdiv( $bits, 8 );
	if ( $bytes && substr( $ip_bin, 0, $bytes ) !== substr( $net_bin, 0, $bytes ) ) {
		return false;
	}
	$rest = $bits % 8;
	if ( ! $rest ) {
		return true;
	}
	$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;
	return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $net_bin[ $bytes ] ) & $mask );
}

/**
 * The visitor's IP. CF-Connecting-IP is only trusted when the request really
 * comes from a Cloudflare edge; otherwise anyone could send the header and
 * pick their own IP to dodge rate limits. Filter: `lc_client_ip`.
 *
 * @return string A valid IP, or '' when none is known.
 */
function lc_client_ip() {
	static $ip = null;
	if ( null !== $ip ) {
		return $ip;
	}
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$remote = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
	$ip     = $remote;

	if ( $remote && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
		$cf = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		if ( filter_var( $cf, FILTER_VALIDATE_IP ) ) {
			foreach ( lc_cloudflare_ip_ranges() as $range ) {
				if ( lc_ip_in_cidr( $remote, $range ) ) {
					$ip = $cf;
					break;
				}
			}
		}
	}

	$ip = (string) apply_filters( 'lc_client_ip', $ip, $remote );
	$ip = filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	return $ip;
}
