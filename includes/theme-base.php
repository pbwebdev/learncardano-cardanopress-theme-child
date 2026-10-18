<?php
/**
 * Theme base: everything the child theme used to inherit from the
 * CardanoPress Bootstrap parent theme, so it can run without it.
 *
 * The parent is still loaded for now, so nothing here may share a name with a
 * parent function, class or constant (a redeclare is a fatal error): every
 * symbol is lc_ / LC_ prefixed. Each block mirrors the parent's setup/*.php
 * behaviour exactly; where the parent also registers it, the duplicate hook
 * simply returns the same value twice.
 *
 * Not carried over on purpose: post formats, custom logo, TGMPA, the update
 * checker, the QR library, the ISPO shim, textdomain loading, the editor
 * buttons, the admin footer credit, flush_rewrite_rules and the wp_mail_from
 * filters (includes/mail.php handles mail).
 *
 * @package learncardano
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme supports, image size and the one menu location the theme uses.
 */
function lc_theme_setup() {
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'title-tag' );
	add_theme_support( 'editor-styles' );

	add_image_size( 'max', 1920, 1080 );

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'cardanopress-bootstrap' ),
		)
	);
}
add_action( 'after_setup_theme', 'lc_theme_setup' );

/** Number of revisions to keep. */
function lc_keep_revisions() {
	return 30;
}
add_filter( 'wp_revisions_to_keep', 'lc_keep_revisions' );

/** No JPEG compression on generated sizes. */
function lc_jpeg_quality() {
	return 100;
}
add_filter( 'jpeg_quality', 'lc_jpeg_quality' );

/** Excerpt length, in words. */
function lc_excerpt_length() {
	return 50;
}
add_filter( 'excerpt_length', 'lc_excerpt_length' );

/** Excerpt "read more" string. */
function lc_excerpt_more() {
	return '&hellip;';
}
add_filter( 'excerpt_more', 'lc_excerpt_more' );

/** Default embed size. */
function lc_embed_defaults() {
	return array(
		'width'  => '1280',
		'height' => '720',
	);
}
add_filter( 'embed_defaults', 'lc_embed_defaults' );

/** Remove the WordPress icon from the admin bar. */
function lc_remove_wp_icon() {
	global $wp_admin_bar;
	$wp_admin_bar->remove_menu( 'wp-logo' );
}
add_action( 'wp_before_admin_bar_render', 'lc_remove_wp_icon' );

/** Disable the update notification for this theme from the WordPress.org repository. */
function lc_disable_theme_updates( $value ) {
	unset( $value->response[ get_stylesheet() ] );
	return $value;
}
add_filter( 'pre_set_site_transient_update_themes', 'lc_disable_theme_updates' );

/** Login logo links to the homepage. */
function lc_login_headerurl() {
	return home_url();
}
add_filter( 'login_headerurl', 'lc_login_headerurl' );

/** Login logo text is the site title instead of "WordPress". */
function lc_login_headertext() {
	return get_option( 'blogname' );
}
add_filter( 'login_headertext', 'lc_login_headertext' );

/**
 * Replace the WordPress login screen logo. Priority 11 so it comes after
 * the parent's own rule (priority 10) while both are loaded.
 */
function lc_login_logo() {
	?>
	<style type="text/css">
		body.login h1 a {
			background-image: url( <?php echo esc_url( get_stylesheet_directory_uri() . '/images/android-chrome-192x192.png' ); ?> );
			background-position: center;
			background-size: contain;
			background-repeat: no-repeat;
			width: 320px;
			height: 120px;
			box-shadow: 0 1px 3px rgba( 0, 0, 0, .13 );
		}
	</style>
	<?php
}
add_action( 'login_enqueue_scripts', 'lc_login_logo', 11 );

/**
 * Move all scripts to the footer (same hook and priority as the parent).
 */
function lc_scripts_to_footer() {
	remove_action( 'wp_head', 'wp_enqueue_scripts', 1 );
	remove_action( 'wp_head', 'wp_print_head_scripts', 9 );
	remove_action( 'wp_head', 'wp_print_scripts' );
}
add_action( 'wp_enqueue_scripts', 'lc_scripts_to_footer' );

/**
 * Layout class strings for the content/sidebar wrappers. A copy of the
 * parent's cardanopress_bootstrap_class() map, e.g. 'content-full' gives
 * 'content py-5 w-100'.
 *
 * @param string $key content | content-full | sidebar.
 * @return string
 */
function lc_layout_class( string $key ): string {
	$classes = array(
		'content'                  => array(
			'content',
			'py-5',
		),

		'content-active-sidebar'   => array(
			'col-sm-6',
			'col-md-7',
			'col-lg-8',
		),

		'content-inactive-sidebar' => array(
			'inactive-sidebar',
			'w-100',
		),

		'sidebar'                  => array(
			'sidebar',
			'py-5',
			'col-sm-6',
			'col-md-5',
			'col-lg-4',
		),
	);

	$class_string = '';

	if ( 'content' === $key ) {
		$extra_class_key    = is_active_sidebar( 'sidebar' ) ? 'content-active-sidebar' : 'content-inactive-sidebar';
		$classes['content'] = array_merge( $classes['content'], $classes[ $extra_class_key ] );
	} elseif ( 'content-full' === $key ) {
		$key                = 'content';
		$classes['content'] = array_merge( $classes['content'], array( 'w-100' ) );
	}

	if ( array_key_exists( $key, $classes ) ) {
		$class_string = implode( ' ', $classes[ $key ] );
	}

	return $class_string;
}

/**
 * The Bootstrap 5 base stylesheet (a byte-for-byte copy of the parent's
 * cardanopress-bootstrap.min.css), enqueued at the same priority the parent
 * enqueues its own. style.css depends on this handle.
 */
function lc_enqueue_base_assets() {
	wp_enqueue_style( 'lc-bootstrap', lc_theme_asset_url( 'assets/css/bootstrap-base.min.css' ), array(), null );
}
add_action( 'wp_enqueue_scripts', 'lc_enqueue_base_assets', 20 );

/**
 * Drop the parent's own stylesheet and scripts (priority 99, after the parent
 * has enqueued them at 5 and 20), so they stop loading while the parent is
 * still active. The parent's CSS is replaced by lc-bootstrap above. Its script
 * bundle is not needed: Bootstrap JS is loaded from footer.php. jQuery goes
 * too, since no child script uses it; Rank Math's admin-bar script declares
 * jquery as its own dependency, so it still loads for logged-in admins.
 */
function lc_detach_parent_assets() {
	wp_dequeue_style( 'cardanopress_bootstrap-style' );
	wp_dequeue_script( 'cardanopress_bootstrap-script' );
	wp_dequeue_script( 'jquery' );
}
add_action( 'wp_enqueue_scripts', 'lc_detach_parent_assets', 99 );
