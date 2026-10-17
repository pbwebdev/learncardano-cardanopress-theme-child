<?php
/**
 * Inline critical CSS, previously pasted into the WP Headers and Footers
 * plugin's header box. The CSS now lives in the theme's critical.css
 * (content copied unchanged) and is printed first thing in <head>.
 *
 * @package CardanoPress Bootstrap
 */

add_action(
	'wp_head',
	function () {
		// The plugin still prints its own copy while it is active.
		if ( class_exists( 'WPHeaderAndFooter' ) ) {
			return;
		}
		$file = get_stylesheet_directory() . '/critical.css';
		if ( ! is_readable( $file ) ) {
			return;
		}
		$css = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local theme file.
		if ( ! $css ) {
			return;
		}
		// Theme-owned CSS, printed raw; a stray closing tag must not end the block early.
		echo "<style id=\"critical-css\">\n" . str_ireplace( '</style', '<\/style', $css ) . "\n</style>\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	},
	1
);
