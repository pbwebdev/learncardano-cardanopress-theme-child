<?php
/**
 * Brand assets page (template-brand.php): styles and the small copy-to-
 * clipboard script, on that template only.
 *
 * @package CardanoPress Bootstrap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand page styles and script.
 */
function lc_brand_page_enqueue() {
	if ( is_admin() || ! is_page_template( 'template-brand.php' ) ) {
		return;
	}
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = '/assets/css/brand-page.css';
	$js  = '/assets/js/brand-page.js';

	wp_enqueue_style( 'lc-brand-page', $uri . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );
	wp_enqueue_script( 'lc-brand-page', $uri . $js, array(), file_exists( $dir . $js ) ? (string) filemtime( $dir . $js ) : null, true );
	wp_script_add_data( 'lc-brand-page', 'strategy', 'defer' );
}
add_action( 'wp_enqueue_scripts', 'lc_brand_page_enqueue' );

/**
 * Keep SiteGround Speed Optimizer from stripping the filemtime version or
 * minifying these files (same as partner.php), so edits reach browsers.
 */
add_filter(
	'sgo_rqs_exclude',
	function ( $list ) {
		$list[] = 'assets/(css|js)/brand-page\.(css|js)';
		return $list;
	}
);
foreach ( array( 'sgo_css_minify_exclude', 'sgo_js_minify_exclude' ) as $lc_sgo_filter ) {
	add_filter(
		$lc_sgo_filter,
		function ( $list ) {
			$list[] = 'lc-brand-page';
			return $list;
		}
	);
}
