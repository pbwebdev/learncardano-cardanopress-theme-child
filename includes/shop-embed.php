<?php
/**
 * Shop page (/shop/, page 27613): load the Spreadshop client late.
 *
 * The page content embeds Spreadshop (an inline `spread_shop_config` script, then
 * shopclient.nocache.js). Its cookie banner became the largest paint, so the
 * client script is now injected after window load and an idle moment, which lets
 * our own heading and intro paint first. The post content is left alone: the
 * rewrite happens when the content is rendered.
 *
 * @package CardanoPress Bootstrap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const LC_SHOP_PAGE_ID   = 27613;
const LC_SHOP_HOST_ROOT = 'learn-cardano.myspreadshop.com.au';

/**
 * True on the shop page.
 */
function lc_is_shop_page() {
	return is_page( LC_SHOP_PAGE_ID );
}

/**
 * Swap the blocking shopclient <script src> tag for a deferred loader.
 * The inline config script before it is not touched.
 *
 * @param string $content Post content.
 * @return string
 */
function lc_shop_defer_client( $content ) {
	if ( ! lc_is_shop_page() || ! is_main_query() || ! in_the_loop() || false === stripos( $content, 'shopclient.nocache.js' ) ) {
		return $content;
	}

	$pattern = '#<script\b[^>]*\bsrc=(["\'])(https://[a-z0-9.-]*myspreadshop\.[a-z.]+/shopfiles/shopclient/shopclient\.nocache\.js[^"\']*)\1[^>]*>\s*</script>#i';

	return preg_replace_callback(
		$pattern,
		function ( $matches ) {
			$src = esc_url_raw( $matches[2] );
			if ( '' === $src ) {
				return $matches[0];
			}
			return '<script>(function(){var src=' . wp_json_encode( $src ) . ';'
				. 'function add(){var s=document.createElement("script");s.src=src;s.async=true;document.head.appendChild(s);}'
				. 'function idle(){if("requestIdleCallback" in window){window.requestIdleCallback(add,{timeout:2000});}else{setTimeout(add,200);}}'
				. 'if(document.readyState==="complete"){idle();}else{window.addEventListener("load",idle);}'
				. '})();</script>';
		},
		$content,
		1
	);
}
add_filter( 'the_content', 'lc_shop_defer_client', 20 );

/**
 * Warm up the connection to the Spreadshop host, on the shop page only.
 */
function lc_shop_preconnect() {
	if ( ! lc_is_shop_page() ) {
		return;
	}
	echo '<link rel="preconnect" href="' . esc_url( 'https://' . LC_SHOP_HOST_ROOT ) . '">' . "\n";
	// The store fills in after the page has painted: hold its space so the content below does not jump.
	echo '<style>#myShop{min-height:100vh}</style>' . "\n";
}
add_action( 'wp_head', 'lc_shop_preconnect', 3 );
