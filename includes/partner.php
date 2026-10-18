<?php
/**
 * Partner / media kit page (template-partner.php): assets that load only on
 * pages using that template. Styles live in assets/css/partner.css under
 * `.pk__`. The page has no script of its own: its numbers are masked
 * placeholders (the real ones are in the gated media kit), so the old stat
 * count-up was removed.
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
 * Partner styles and Calendly's embed script, on the partner page only, so no
 * other page pays for them.
 */
function lc_partner_enqueue() {
	if ( is_admin() || ! lc_is_partner_view() ) {
		return;
	}
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = '/assets/css/partner.css';

	wp_enqueue_style( 'lc-partner', $uri . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );

	// The booking section's inline scheduler. Async: it only fills the embed box.
	wp_enqueue_script( 'lc-calendly', 'https://assets.calendly.com/assets/external/widget.js', array(), null, true );
	wp_script_add_data( 'lc-calendly', 'strategy', 'async' );
}
add_action( 'wp_enqueue_scripts', 'lc_partner_enqueue' );

/**
 * SiteGround Speed Optimizer strips ?ver= and serves a year-cached minified
 * copy, so edits to partner.css would never reach browsers. Keep the filemtime
 * version and leave the file out of SG's minifier (as courses.php).
 */
add_filter(
	'sgo_rqs_exclude',
	function ( $list ) {
		$list[] = 'assets/css/partner\.css';
		return $list;
	}
);
add_filter(
	'sgo_css_minify_exclude',
	function ( $list ) {
		$list[] = 'lc-partner';
		return $list;
	}
);
