<?php
/**
 * /feed/social/ — one RSS feed with the newest articles and podcast episodes,
 * for the RSS automation that shares new content to Facebook, X and Reddit.
 * Each item carries its featured image (media:content + enclosure) because
 * some automation services want an image in the feed itself.
 *
 * @package CardanoPress Bootstrap
 */

add_action(
	'init',
	function () {
		add_feed(
			'social',
			function () {
				load_template( ABSPATH . WPINC . '/feed-rss2.php' );
			}
		);
	}
);

add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( ! is_admin() && $query->is_main_query() && $query->is_feed( 'social' ) ) {
			$query->set( 'post_type', array( 'post', 'podcasts' ) );
			$query->set( 'post_status', 'publish' );
			$query->set( 'posts_per_rss', 20 );
		}
	}
);

add_action(
	'rss2_ns',
	function () {
		if ( is_feed( 'social' ) ) {
			echo 'xmlns:media="http://search.yahoo.com/mrss/"' . "\n";
		}
	}
);

add_action(
	'rss2_item',
	function () {
		if ( ! is_feed( 'social' ) || ! has_post_thumbnail() ) {
			return;
		}
		$id   = get_post_thumbnail_id();
		$src  = wp_get_attachment_image_src( $id, 'large' );
		$file = get_attached_file( $id );
		if ( ! $src ) {
			return;
		}
		$type = get_post_mime_type( $id );
		printf( "\t\t<media:content url=\"%s\" medium=\"image\" width=\"%d\" height=\"%d\" />\n", esc_url( $src[0] ), (int) $src[1], (int) $src[2] );
		printf( "\t\t<enclosure url=\"%s\" length=\"%d\" type=\"%s\" />\n", esc_url( $src[0] ), $file && file_exists( $file ) ? (int) filesize( $file ) : 0, esc_attr( $type ) );
	}
);
