<?php
/**
 * Small admin tweaks that used to need plugins: no front-end admin bar for
 * members (User Role Editor setting) and Podcasts/Courses at the top of the
 * admin menu (Admin Menu Editor ordering).
 *
 * @package CardanoPress Bootstrap
 */

/**
 * Hide the front-end admin bar for users whose only roles are member-type
 * roles (student = "Member", subscriber). Anyone with another role keeps it.
 */
add_filter(
	'show_admin_bar',
	function ( $show ) {
		if ( ! $show || ! is_user_logged_in() ) {
			return $show;
		}
		$roles = (array) wp_get_current_user()->roles;
		if ( $roles && ! array_diff( $roles, array( 'student', 'subscriber' ) ) ) {
			return false;
		}
		return $show;
	}
);

/**
 * Admin menu order as Admin Menu Editor had it: Dashboard, Podcasts,
 * Courses, Posts, then Pages, Media and Comments. Everything else follows in
 * WordPress's default order.
 */
add_filter( 'custom_menu_order', '__return_true' );
add_filter(
	'menu_order',
	function () {
		return array(
			'index.php',
			'edit.php?post_type=podcasts',
			'edit.php?post_type=sfwd-courses',
			'edit.php',
			'separator1',
			'edit.php?post_type=page',
			'upload.php',
			'edit-comments.php',
		);
	}
);
