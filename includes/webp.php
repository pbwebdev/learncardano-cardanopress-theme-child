<?php
/**
 * Serve the WebP copies SiteGround Optimizer already generates next to each
 * upload (photo.jpg -> photo.jpg.webp). SG's own WebP delivery depends on
 * an Accept-header rewrite, and Cloudflare caches the JPEG without
 * Vary: Accept, so visitors were getting the JPEGs (often 5-10x larger).
 * Wrapping images in <picture> with a WebP <source> works through any cache.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * The .webp URL for an uploads URL when SG has written the file, else ''.
 *
 * @param string $url Image URL.
 * @return string
 */
function lc_webp_url( $url ) {
	static $uploads = null;
	if ( null === $uploads ) {
		$uploads = wp_get_upload_dir();
	}
	$path_part = strtok( $url, '?' );
	if ( 0 !== strpos( $path_part, $uploads['baseurl'] ) || ! preg_match( '/\.(jpe?g|png)$/i', $path_part ) ) {
		return '';
	}
	$file = $uploads['basedir'] . substr( $path_part, strlen( $uploads['baseurl'] ) );
	return file_exists( $file . '.webp' ) ? $path_part . '.webp' : '';
}

/**
 * Wrap an <img> tag in <picture> with a WebP source, when every candidate in
 * its src/srcset has a WebP copy. Leaves anything else untouched.
 *
 * @param string $img <img> HTML.
 * @return string
 */
function lc_webp_picture( $img ) {
	if ( false === stripos( $img, '<img' ) || false !== stripos( $img, 'data-no-webp' ) ) {
		return $img;
	}
	if ( ! preg_match( '/\ssrc=["\']([^"\']+)["\']/i', $img, $src ) ) {
		return $img;
	}
	$sources = array();
	if ( preg_match( '/\ssrcset=["\']([^"\']+)["\']/i', $img, $srcset ) ) {
		foreach ( explode( ',', $srcset[1] ) as $candidate ) {
			$parts = preg_split( '/\s+/', trim( $candidate ) );
			$webp  = lc_webp_url( $parts[0] );
			if ( ! $webp ) {
				return $img; // Mixed sets would let the browser pick a missing file.
			}
			$sources[] = $webp . ( isset( $parts[1] ) ? ' ' . $parts[1] : '' );
		}
	} else {
		$webp = lc_webp_url( html_entity_decode( $src[1] ) );
		if ( ! $webp ) {
			return $img;
		}
		$sources[] = $webp;
	}
	$sizes = preg_match( '/\ssizes=["\']([^"\']+)["\']/i', $img, $m ) ? ' sizes="' . esc_attr( $m[1] ) . '"' : '';
	return '<picture class="lc-picture"><source type="image/webp" srcset="' . esc_attr( implode( ', ', $sources ) ) . '"' . $sizes . '>' . $img . '</picture>';
}

if ( ! is_admin() ) {
	// Featured images, cards and anything built with wp_get_attachment_image().
	add_filter( 'wp_get_attachment_image', 'lc_webp_picture', 20 );
	// Images inside post content (WP 6.0+ per-image filter).
	add_filter( 'wp_content_img_tag', 'lc_webp_picture', 20 );
	// the_post_thumbnail() goes through post_thumbnail_html as well.
	add_filter(
		'post_thumbnail_html',
		function ( $html ) {
			return false === stripos( $html, '<picture' ) ? lc_webp_picture( $html ) : $html;
		},
		20
	);
}
