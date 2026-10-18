<?php
/**
 * SVG uploads without the Safe SVG plugin. Administrators (manage_options)
 * may upload .svg files; every upload is cleaned by the vendored
 * enshrined/svg-sanitize library (vendor/svg-sanitize/, GPL-2.0-or-later)
 * and rejected if it can't be parsed. Compressed SVG (.svgz) is refused
 * because it can't be sanitized reliably. Width/height metadata is filled
 * from the file so the media library, featured images and
 * wp_get_attachment_image() show SVGs at their real size.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * PSR-4 autoloader for enshrined\svgSanitize. Registered after any Composer
 * autoloader (e.g. Safe SVG's copy), so whichever is loaded first wins.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'enshrined\\svgSanitize\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$file = get_stylesheet_directory() . '/vendor/svg-sanitize/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Whether the current user may upload SVG files.
 */
function lc_svg_user_can_upload() {
	return current_user_can( 'manage_options' );
}

add_filter(
	'upload_mimes',
	function ( $mimes ) {
		if ( lc_svg_user_can_upload() ) {
			$mimes['svg'] = 'image/svg+xml';
		} else {
			unset( $mimes['svg'] );
		}
		unset( $mimes['svgz'] );
		return $mimes;
	}
);

/**
 * finfo reports SVGs as text/plain, text/html or image/svg+xml depending on
 * the file, which makes core reject them. Trust the .svg extension for
 * users allowed to upload them (the content is sanitized on upload anyway).
 */
add_filter(
	'wp_check_filetype_and_ext',
	function ( $data, $file, $filename ) {
		if ( 'svg' === strtolower( pathinfo( (string) $filename, PATHINFO_EXTENSION ) ) && lc_svg_user_can_upload() ) {
			$data['ext']  = 'svg';
			$data['type'] = 'image/svg+xml';
		}
		return $data;
	},
	10,
	3
);

/**
 * Sanitize SVG uploads in place, and refuse .svgz and anything unparseable.
 */
function lc_svg_sanitize_upload( $file ) {
	if ( ! empty( $file['error'] ) ) {
		return $file;
	}
	$ext = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
	if ( 'svgz' === $ext ) {
		$file['error'] = __( 'Compressed SVG (.svgz) files are not allowed. Upload a plain .svg instead.', 'learncardano' );
		return $file;
	}
	if ( 'svg' !== $ext && 'image/svg+xml' !== ( $file['type'] ?? '' ) ) {
		return $file;
	}
	if ( ! lc_svg_user_can_upload() ) {
		$file['error'] = __( 'Sorry, you are not allowed to upload SVG files.', 'learncardano' );
		return $file;
	}

	$dirty = file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload tmp file.
	// A gzipped body behind a .svg name is an svgz in disguise.
	if ( false === $dirty || 0 === strpos( $dirty, "\x1f\x8b" ) ) {
		$file['error'] = __( 'This SVG could not be read or is compressed. Upload a plain .svg file.', 'learncardano' );
		return $file;
	}

	if ( ! class_exists( '\enshrined\svgSanitize\Sanitizer' ) ) {
		$file['error'] = __( 'SVG sanitizer is missing, so SVG uploads are disabled.', 'learncardano' );
		return $file;
	}
	$clean = lc_svg_clean( $dirty );
	if ( false === $clean ) {
		$file['error'] = __( 'This SVG could not be sanitized and was not uploaded.', 'learncardano' );
		return $file;
	}
	file_put_contents( $file['tmp_name'], $clean ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local upload tmp file.
	$file['size'] = strlen( $clean );
	return $file;
}
add_filter( 'wp_handle_upload_prefilter', 'lc_svg_sanitize_upload' );
add_filter( 'wp_handle_sideload_prefilter', 'lc_svg_sanitize_upload' );

/**
 * Sanitized SVG markup, or false when it is compressed, unparseable or the
 * sanitizer is missing.
 *
 * @return string|false
 */
function lc_svg_clean( $dirty ) {
	if ( ! is_string( $dirty ) || 0 === strpos( $dirty, "\x1f\x8b" ) || ! class_exists( '\enshrined\svgSanitize\Sanitizer' ) ) {
		return false;
	}
	$sanitizer = new \enshrined\svgSanitize\Sanitizer();
	$sanitizer->removeRemoteReferences( true );
	$sanitizer->minify( true );
	$clean = $sanitizer->sanitize( $dirty );
	return ( false === $clean || '' === trim( (string) $clean ) ) ? false : (string) $clean;
}

/**
 * Backstop for SVG attachments that skipped the upload prefilters (e.g.
 * wp_upload_bits() + wp_insert_attachment() from a plugin or import):
 * sanitize the stored file in place, or delete the attachment when that
 * fails. Already-clean files come out unchanged, so this is cheap to repeat.
 */
add_action(
	'add_attachment',
	function ( $attachment_id ) {
		$path = get_attached_file( $attachment_id );
		$ext  = $path ? strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) : '';
		if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) && 'svg' !== $ext && 'svgz' !== $ext ) {
			return;
		}
		$dirty = ( 'svgz' !== $ext && $path && is_readable( $path ) ) ? file_get_contents( $path ) : false; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local upload.
		$clean = false === $dirty ? false : lc_svg_clean( $dirty );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- local upload.
		if ( false === $clean || ( $clean !== $dirty && false === file_put_contents( $path, $clean ) ) ) {
			error_log( sprintf( 'lc_svg: deleted attachment %d, its SVG could not be sanitized', $attachment_id ) );
			wp_delete_attachment( $attachment_id, true );
		}
	}
);

/**
 * Width and height of an SVG file from its root element: numeric (px)
 * width/height attributes, else the viewBox.
 *
 * @return array{0: int, 1: int}|null
 */
function lc_svg_dimensions( $path ) {
	if ( ! $path || ! is_readable( $path ) ) {
		return null;
	}
	$head = file_get_contents( $path, false, null, 0, 8192 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
	if ( ! $head || ! preg_match( '/<svg\b[^>]*>/is', $head, $tag ) ) {
		return null;
	}
	$attr = function ( $name ) use ( $tag ) {
		return preg_match( '/\s' . $name . '\s*=\s*("|\')([^"\']*)\1/i', $tag[0], $m ) ? trim( $m[2] ) : '';
	};
	$width  = $attr( 'width' );
	$height = $attr( 'height' );
	if ( preg_match( '/^[\d.]+(px)?$/', $width ) && preg_match( '/^[\d.]+(px)?$/', $height ) ) {
		return array( (int) round( (float) $width ), (int) round( (float) $height ) );
	}
	$box = preg_split( '/[\s,]+/', $attr( 'viewBox' ) );
	if ( 4 === count( $box ) && (float) $box[2] > 0 && (float) $box[3] > 0 ) {
		return array( (int) round( (float) $box[2] ), (int) round( (float) $box[3] ) );
	}
	return null;
}

/**
 * Record width/height (and the file path) for SVG attachments, which core
 * skips because it can't read their size.
 */
add_filter(
	'wp_generate_attachment_metadata',
	function ( $metadata, $attachment_id ) {
		if ( 'image/svg+xml' !== get_post_mime_type( $attachment_id ) ) {
			return $metadata;
		}
		$metadata = is_array( $metadata ) ? $metadata : array();
		$path     = get_attached_file( $attachment_id );
		$size     = lc_svg_dimensions( $path );
		if ( $size ) {
			$metadata['width']  = $size[0];
			$metadata['height'] = $size[1];
		}
		if ( empty( $metadata['file'] ) && $path ) {
			$metadata['file'] = _wp_relative_upload_path( $path );
		}
		if ( ! isset( $metadata['sizes'] ) ) {
			$metadata['sizes'] = array();
		}
		return $metadata;
	},
	10,
	2
);

/**
 * Let image_downsize() (and so wp_get_attachment_image_src(), featured
 * images and blocks) return SVGs at full size for every requested size.
 */
add_filter(
	'image_downsize',
	function ( $out, $id ) {
		if ( $out || 'image/svg+xml' !== get_post_mime_type( $id ) ) {
			return $out;
		}
		$meta   = wp_get_attachment_metadata( $id );
		$width  = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$height = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		if ( ! $width || ! $height ) {
			$size = lc_svg_dimensions( get_attached_file( $id ) );
			if ( $size ) {
				list( $width, $height ) = $size;
			}
		}
		return array( wp_get_attachment_url( $id ), $width, $height, false );
	},
	10,
	2
);

/**
 * Media library grid/modal: give SVGs a "full" size so they show as a
 * preview instead of a generic file icon.
 */
add_filter(
	'wp_prepare_attachment_for_js',
	function ( $response, $attachment ) {
		if ( 'image/svg+xml' !== $response['mime'] ) {
			return $response;
		}
		$src = image_downsize( $attachment->ID, 'full' );
		if ( $src ) {
			$response['width']  = $src[1];
			$response['height'] = $src[2];
			$response['sizes']  = array(
				'full' => array(
					'url'         => $src[0],
					'width'       => $src[1],
					'height'      => $src[2],
					'orientation' => $src[2] > $src[1] ? 'portrait' : 'landscape',
				),
			);
			$response['image'] = array(
				'src'    => $src[0],
				'width'  => $src[1],
				'height' => $src[2],
			);
		}
		return $response;
	},
	10,
	2
);
