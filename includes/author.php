<?php
/**
 * Public authorship. All site content is published under one editor account
 * (LearnCardano, user 451) so bylines, feeds and the REST API never point at
 * an administrator account.
 *
 * @package CardanoPress Bootstrap
 */

/** The content publishing account (editor role, not an administrator). */
const LC_PUBLISHING_AUTHOR_ID = 451;

/**
 * The publishing account's avatar is Peter's photo, served from the theme
 * instead of Gravatar: bylines don't carry an email-derived Gravatar hash,
 * and there's no third-party image request.
 */
add_filter(
	'pre_get_avatar_data',
	function ( $args, $id_or_email ) {
		$user_id = 0;
		if ( is_numeric( $id_or_email ) ) {
			$user_id = (int) $id_or_email;
		} elseif ( $id_or_email instanceof WP_User ) {
			$user_id = $id_or_email->ID;
		} elseif ( $id_or_email instanceof WP_Post ) {
			$user_id = (int) $id_or_email->post_author;
		} elseif ( $id_or_email instanceof WP_Comment ) {
			$user_id = (int) $id_or_email->user_id;
		}
		if ( LC_PUBLISHING_AUTHOR_ID !== $user_id ) {
			return $args;
		}
		$args['url']          = get_stylesheet_directory_uri() . '/images/peter-bui-avatar-256.webp';
		$args['found_avatar'] = true;
		return $args;
	},
	10,
	2
);

/**
 * oEmbed responses (the cards other sites build when a post is linked)
 * included the author's name and author archive URL, which carries the
 * account's login slug. Author archives are disabled anyway; drop both.
 */
add_filter(
	'oembed_response_data',
	function ( $data ) {
		unset( $data['author_name'], $data['author_url'] );
		return $data;
	},
	20
);
