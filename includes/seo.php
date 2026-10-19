<?php
/**
 * Rank Math adjustments that belong with the templates rather than in its
 * settings screen (those stay editable in Rank Math > Titles & Meta).
 *
 * @package CardanoPress Bootstrap
 */

/**
 * Google shows about 60 characters of a title and 155-160 of a description.
 * For articles and episodes without a hand-written SEO title, drop the
 * " - Learn Cardano" suffix when it would push the title past that, so the
 * episode name itself isn't cut off.
 */
add_filter(
	'rank_math/frontend/title',
	function ( $title ) {
		if ( ! is_singular( array( 'post', 'podcasts' ) ) ) {
			return $title;
		}
		$post_id = get_queried_object_id();
		if ( get_post_meta( $post_id, 'rank_math_title', true ) || mb_strlen( $title ) <= 60 ) {
			return $title;
		}
		return wp_strip_all_tags( get_the_title( $post_id ) );
	}
);

/**
 * Auto-generated descriptions (the excerpt) can run past 200 characters;
 * trim them at a word boundary. Hand-written Rank Math descriptions are
 * left exactly as written.
 */
add_filter(
	'rank_math/frontend/description',
	function ( $description ) {
		if ( ! is_singular() || mb_strlen( $description ) <= 160 ) {
			return $description;
		}
		if ( get_post_meta( get_queried_object_id(), 'rank_math_description', true ) ) {
			return $description;
		}
		return lc_trim_description( $description, 155 );
	}
);

/**
 * Trim text to at most $max characters at a word boundary, ending in an ellipsis.
 *
 * @param string $text Plain text.
 * @param int    $max  Maximum length including the ellipsis.
 * @return string
 */
function lc_trim_description( $text, $max ) {
	$text = trim( preg_replace( '/\s+/u', ' ', $text ) );
	if ( mb_strlen( $text ) <= $max ) {
		return $text;
	}
	$cut = mb_substr( $text, 0, $max - 1 );
	$cut = preg_replace( '/\s+\S*$/u', '', $cut );
	return rtrim( $cut, " \t,;:-–—." ) . '…';
}

/**
 * Structured data clean-up:
 * - The front page is a page, so Rank Math gave it the default Article
 *   schema for pages. It isn't an article: keep the WebSite / WebPage /
 *   Organization entities and drop the Article.
 */
add_filter(
	'rank_math/json_ld',
	function ( $data ) {
		if ( is_front_page() ) {
			foreach ( $data as $key => $entity ) {
				if ( is_array( $entity ) && isset( $entity['@type'] ) && 'Article' === $entity['@type'] ) {
					unset( $data[ $key ] );
				}
			}
		}
		return $data;
	},
	100
);

/**
 * Podcast episodes print their own PodcastEpisode + VideoObject schema
 * (single-podcasts.php). Rank Math's default "Video" type for episodes
 * finds no video in the post content, and then outputs no JSON-LD at all,
 * so episodes were missing the site-wide WebSite / Organization / WebPage /
 * breadcrumb entities. Add those back.
 */
add_filter(
	'rank_math/schema/add_global_entities',
	function ( $add ) {
		return is_singular( 'podcasts' ) ? true : $add;
	}
);

/**
 * Schema.org VideoObject for an episode's YouTube video, for Google's video
 * results. Uses the featured image (the video's thumbnail) and the post date.
 *
 * @param int    $post_id    Episode ID.
 * @param string $youtube_id Validated YouTube ID.
 * @param string $description Plain-text description.
 * @return array
 */
function lc_episode_video_schema( $post_id, $youtube_id, $description ) {
	$thumbs   = array();
	$thumb_id = get_post_thumbnail_id( $post_id );
	if ( $thumb_id ) {
		$full = wp_get_attachment_image_url( $thumb_id, 'full' );
		if ( $full ) {
			$thumbs[] = $full;
		}
	}
	$thumbs[] = 'https://i.ytimg.com/vi/' . $youtube_id . '/hqdefault.jpg';

	return array(
		'@type'        => 'VideoObject',
		'@id'          => get_permalink( $post_id ) . '#video',
		'name'         => wp_strip_all_tags( get_the_title( $post_id ) ),
		'description'  => '' !== $description ? $description : wp_strip_all_tags( get_the_title( $post_id ) ),
		'thumbnailUrl' => $thumbs,
		'uploadDate'   => get_the_date( 'c', $post_id ),
		'embedUrl'     => 'https://www.youtube.com/embed/' . $youtube_id,
		'url'          => 'https://www.youtube.com/watch?v=' . $youtube_id,
		'publisher'    => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		),
	);
}
