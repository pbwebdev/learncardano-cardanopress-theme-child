<?php
/**
 * Partner / media kit page (template-partner.php): assets that load only on
 * pages using that template. Styles live in assets/css/partner.css under
 * `.pk__`; assets/js/partner.js only adds the stat count-up.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * Whether the current view uses the partner template.
 */
function lc_is_partner_view() {
	return is_page_template( 'template-partner.php' );
}

/**
 * Partner styles and the page script (count-up, chart draw-in, and Calendly loaded only near the booking section), on the
 * partner page only, so no other page pays for them.
 */
function lc_partner_enqueue() {
	if ( is_admin() || ! lc_is_partner_view() ) {
		return;
	}
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = '/assets/css/partner.css';
	$js  = '/assets/js/partner.js';

	wp_enqueue_style( 'lc-partner', $uri . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );
	wp_enqueue_script( 'lc-partner', $uri . $js, array(), file_exists( $dir . $js ) ? (string) filemtime( $dir . $js ) : null, true );
	wp_script_add_data( 'lc-partner', 'strategy', 'defer' );

	// Calendly's inline scheduler (about 3 MB of script and CSS, plus Stripe) is not
	// enqueued here: partner.js loads it when the booking section nears the screen.
}
add_action( 'wp_enqueue_scripts', 'lc_partner_enqueue' );

/**
 * SiteGround Speed Optimizer strips ?ver= and serves a year-cached minified
 * copy, so edits to partner.css/js would never reach browsers. Keep the
 * filemtime version and leave both files out of SG's minifier (as courses.php).
 */
add_filter(
	'sgo_rqs_exclude',
	function ( $list ) {
		$list[] = 'assets/(css|js)/partner\.(css|js)';
		return $list;
	}
);
foreach ( array( 'sgo_css_minify_exclude', 'sgo_js_minify_exclude' ) as $lc_sgo_filter ) {
	add_filter(
		$lc_sgo_filter,
		function ( $list ) {
			$list[] = 'lc-partner';
			return $list;
		}
	);
}
