<?php
/**
 * Site search without Algolia: WordPress's own search on the main query,
 * limited to the content types visitors care about and rendered by search.php
 * in the archive design.
 *
 * The filter pills use `?post_type=<group>`. Groups are read straight from
 * $_GET because WordPress drops `post_type` values that are not publicly
 * queryable post types (`page`, `kb`) before pre_get_posts runs.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * Search filter groups, in pill order: key => label and post types.
 *
 * @return array<string, array{label: string, types: string[]}>
 */
function lc_search_groups() {
	$kb_types = function_exists( 'lc_kb_types' ) ? array_keys( lc_kb_types() ) : array();
	// lc_kb_types() is empty while ACF is off; the KB post types are known.
	if ( ! $kb_types ) {
		$kb_types = array( 'recipe', 'concept', 'pitfall', 'minidapp', 'comparison', 'track' );
	}
	$groups = array(
		'post'         => array(
			'label' => __( 'Articles', 'learncardano' ),
			'types' => array( 'post' ),
		),
		'podcasts'     => array(
			'label' => __( 'Podcasts', 'learncardano' ),
			'types' => array( 'podcasts' ),
		),
		'sfwd-courses' => array(
			'label' => __( 'Courses', 'learncardano' ),
			'types' => array( 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic' ),
		),
		'kb'           => array(
			'label' => __( 'Knowledge base', 'learncardano' ),
			'types' => $kb_types,
		),
		'page'         => array(
			'label' => __( 'Pages', 'learncardano' ),
			'types' => array( 'page' ),
		),
	);
	foreach ( $groups as $key => $group ) {
		$groups[ $key ]['types'] = array_values( array_filter( $group['types'], 'post_type_exists' ) );
		if ( ! $groups[ $key ]['types'] ) {
			unset( $groups[ $key ] );
		}
	}
	return $groups;
}

/**
 * The active filter group key from `?post_type=`, or '' for everything.
 * Also accepts any single searchable post type (e.g. `sfwd-lessons`) and
 * maps it to its group.
 */
function lc_search_current_group() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
	$raw = isset( $_GET['post_type'] ) && is_string( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
	if ( '' === $raw ) {
		return '';
	}
	$groups = lc_search_groups();
	if ( isset( $groups[ $raw ] ) ) {
		return $raw;
	}
	foreach ( $groups as $key => $group ) {
		if ( in_array( $raw, $group['types'], true ) ) {
			return $key;
		}
	}
	return '';
}

/**
 * Every post type the site search covers.
 *
 * @return string[]
 */
function lc_search_post_types() {
	$types = array();
	foreach ( lc_search_groups() as $group ) {
		$types = array_merge( $types, $group['types'] );
	}
	return array_values( array_unique( $types ) );
}

/**
 * Utility pages that should never show up as results (shop/account pages
 * left over from plugins, the old Algolia search page, the front and posts
 * pages which have no useful content of their own).
 *
 * @return int[]
 */
function lc_search_excluded_ids() {
	$slugs = apply_filters( 'lc_search_excluded_page_slugs', array( 'search', 'cart', 'checkout', 'my-account', 'shop', 'dashboard' ) );
	$ids   = array( (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) );
	foreach ( $slugs as $slug ) {
		$page = get_page_by_path( $slug );
		if ( $page ) {
			$ids[] = (int) $page->ID;
		}
	}
	return array_values( array_filter( array_unique( $ids ) ) );
}

/**
 * Scope the main front-end search query. WordPress orders searches by
 * relevance (title matches first) by default, so orderby is left alone.
 */
add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		// An empty search would list every post; return nothing and let
		// search.php show the prompt instead.
		if ( '' === trim( (string) $query->get( 's' ) ) ) {
			$query->set( 'post__in', array( 0 ) );
			return;
		}

		$groups = lc_search_groups();
		$group  = lc_search_current_group();
		$query->set( 'post_type', $group ? $groups[ $group ]['types'] : lc_search_post_types() );
		$query->set( 'post_status', 'publish' );
		$query->set( 'post__not_in', lc_search_excluded_ids() );
		$query->set( 'ignore_sticky_posts', true );
	}
);

/**
 * Filter pills for search.php: "All" plus each group, keeping the query.
 *
 * @return array<int, array{0: string, 1: string, 2: bool}>
 */
function lc_search_pills() {
	$current = lc_search_current_group();
	$base    = add_query_arg( 's', rawurlencode( get_search_query( false ) ), home_url( '/' ) );
	$pills   = array( array( __( 'All', 'learncardano' ), $base, '' === $current ) );
	foreach ( lc_search_groups() as $key => $group ) {
		$pills[] = array( $group['label'], add_query_arg( 'post_type', $key, $base ), $key === $current );
	}
	return $pills;
}
