<?php

require_once 'includes/theme-base.php';
require_once 'includes/nav-walker.php';
require_once 'includes/security.php';
require_once 'includes/cardanopress-lockdown.php';
require_once 'includes/social-feed.php';
require_once 'includes/webp.php';
require_once 'includes/knowledge-base.php';
require_once 'includes/kb-admin.php';
require_once 'includes/delegate.php';
require_once 'includes/courses.php';
require_once 'includes/partner.php';
require_once 'includes/search.php';
require_once 'includes/svg-uploads.php';
require_once 'includes/rest-meta.php';
require_once 'includes/mail.php';
require_once 'includes/admin-tweaks.php';
require_once 'includes/seo.php';
require_once 'includes/author.php';
require_once 'includes/x-embeds.php';

// Forms and the newsletter popup (replace Gravity Forms and WP Popups).
foreach ( array( 'includes/forms.php', 'includes/newsletter-popup.php' ) as $lc_include ) {
	if ( file_exists( __DIR__ . '/' . $lc_include ) ) {
		require_once __DIR__ . '/' . $lc_include;
	}
}

// Accounts: email link and wallet sign-in, /account/, comment form, members-only helpers.
// core.php and account.php are hooks and small helpers; email.php and wallet.php
// load only for /account/* routes. The test mail outbox exists off production only.
require_once __DIR__ . '/includes/auth/core.php';
require_once __DIR__ . '/includes/auth/account.php';
if ( 'production' !== wp_get_environment_type() ) {
	require_once __DIR__ . '/includes/auth/dev-outbox.php';
}

add_theme_support('responsive-embeds');
add_filter( 'wp_is_application_passwords_available', '__return_true' ); 


if (! function_exists('lc_widgets_init')) {
	function lc_widgets_init()
	{
		register_sidebar(array(
			'id'            => 'FooterCol1',
			'name'          => __('Footer Col 1', 'learncardano'),
			'description'   => __('Footer Col 1', 'learncardano'),
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
			'before_widget' => '<div class="section %2$s">',
			'after_widget'  => '</div>'
		));
		register_sidebar(array(
			'id'            => 'FooterCol2',
			'name'          => __('Footer Col 2', 'learncardano'),
			'description'   => __('Footer Col 2', 'learncardano'),
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
			'before_widget' => '<section class="widget %2$s">',
			'after_widget'  => '</section>'
		));
		register_sidebar(array(
			'id'            => 'FooterCol3',
			'name'          => __('Footer Col 3', 'learncardano'),
			'description'   => __('Footer Col 3', 'learncardano'),
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
			'before_widget' => '<section class="widget %2$s">',
			'after_widget'  => '</section>'
		));
	}
	add_action('widgets_init', 'lc_widgets_init');
}


function cardanopress_child_bootstrap_class(string $key): string
{
	$classes = array(
		'content' => array(
			'content',
		),

		'content-active-sidebar' => array(
			'col-sm-12',
			'col-lg-7',
			'py-3',
		),

		'content-inactive-sidebar' => array(
			'inactive-sidebar',
			'w-100',
			'py-3',
		),

		'sidebar' => array(
			'sidebar',
			'py-3',
			'col-sm-12',
			'col-lg-4',
			'offset-lg-1',
		),
	);

	$class_string = '';

	if ('content' === $key) {
		$exta_class_key     = is_active_sidebar('sidebar') ? 'content-active-sidebar' : 'content-inactive-sidebar';
		$classes['content'] = array_merge($classes['content'], $classes[$exta_class_key]);
	} elseif ('content-full' === $key) {
		$key                = 'content';
		$classes['content'] = array_merge($classes['content'], array('w-100'));
	}

	if (array_key_exists($key, $classes)) {
		$class_string = implode(' ', $classes[$key]);
	}

	return $class_string;
}

// Wallet-created accounts are named after md5(stake address); show those as
// "Cardano member" instead of the hex string. Every other name is left as
// written (this used to cut any name of 10+ characters to 5).
add_filter('get_comment_author', function ($author) {
	if ( preg_match( '/^([0-9a-f]{32}|stake(_test)?1[0-9a-z]{20,}|addr(_test)?1[0-9a-z]{20,})$/', (string) $author ) ) {
		return __( 'Cardano member', 'learncardano' );
	}
	return $author;
});

/**
 * Resolve an episode's thumbnail URL, falling back to the bundled cover art.
 * The episode cards now render real images with lc_card_image(); this stays
 * for anything that still needs a plain URL.
 */
if ( ! function_exists( 'lc_episode_thumb_url' ) ) {
	function lc_episode_thumb_url( $size = 'medium_large' ) {
		$url = get_the_post_thumbnail_url( get_the_ID(), $size );
		if ( ! $url ) {
			$url = get_stylesheet_directory_uri() . '/images/learn-cardano-podcast-cover.jpg';
		}
		return $url;
	}
}

/**
 * A listing card's image for the current post: the featured image as a real
 * <img> with srcset/sizes, or the bundled cover art when there is none.
 *
 * Cards used to paint these as inline background-images, which the browser
 * only discovers once the CSS is in and can't size responsively; on mobile the
 * first card is the LCP element. The first card (eager) now loads at high
 * priority and carries `skip-lazy` so SiteGround Optimizer's JS lazy-load
 * leaves it alone; every other card uses native lazy loading. style.css sizes
 * img.pa__media-img / img.lep__media-img with object-fit:cover, which looks
 * the same as the old background-size:cover.
 *
 * Args: class, size, sizes, eager.
 */
/**
 * A 600px-wide size between medium (242) and medium_large (768). On phones a
 * full-width card is ~330-380 CSS px, so at 1.5-2x the browser wanted ~600-
 * 720px but could only pick the 768px file. WordPress adds it to every
 * srcset automatically (same aspect ratio). Existing uploads got the size
 * (plus SiteGround-style .webp copies) from a one-off regeneration.
 */
add_action(
	'after_setup_theme',
	function () {
		add_image_size( 'lc_card', 600, 0 );
	}
);

/**
 * Gallery images: WordPress gives them the generic full-width sizes hint,
 * so gallery thumbnails loaded the 768px file. The core gallery (default
 * columns) is 2-across under 600px; above that, in the 720px article
 * column, a 2-image gallery is 2-across (~352px) and larger ones 3-across.
 */
add_filter(
	'render_block_core/gallery',
	function ( $html ) {
		$desktop = 2 === substr_count( $html, '<img' ) ? '360px' : '240px';
		return preg_replace( '/<img(?![^>]*\ssizes=)/i', '<img sizes="(max-width: 600px) 50vw, ' . $desktop . '"', $html );
	}
);

function lc_card_image( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'class' => '',
			'size'  => 'large',
			'sizes' => '(max-width: 780px) 100vw, 540px',
			'eager' => false,
		)
	);
	$attr = array(
		'class'    => trim( $args['class'] . ( $args['eager'] ? ' skip-lazy' : '' ) ),
		'alt'      => '',
		'sizes'    => $args['sizes'],
		'loading'  => $args['eager'] ? 'eager' : 'lazy',
		'decoding' => 'async',
	);
	if ( $args['eager'] ) {
		$attr['fetchpriority'] = 'high';
	}

	$thumb_id = get_post_thumbnail_id();
	$html     = $thumb_id ? wp_get_attachment_image( $thumb_id, $args['size'], false, $attr ) : '';
	if ( $html ) {
		return $html;
	}

	// Cover art fallback (the same image lc_episode_thumb_url() falls back to).
	unset( $attr['sizes'] );
	$cover = get_stylesheet_directory_uri() . '/images/learn-cardano-podcast-cover';
	$html  = '<img src="' . esc_url( $cover . '.jpg' ) . '" width="1400" height="1400"';
	foreach ( $attr as $name => $value ) {
		$html .= ' ' . $name . '="' . esc_attr( $value ) . '"';
	}
	return '<picture><source srcset="' . esc_url( $cover . '.webp' ) . '" type="image/webp">' . $html . '></picture>';
}

/**
 * Small inline SVG icon (play, comment, wallet), sized to the text with the
 * .lc-icon rule in style.css. Replaces the Font Awesome classes the templates
 * used, which rendered nothing once Font Awesome stopped being loaded.
 */
function lc_icon( $name ) {
	$paths = array(
		'play'    => 'M4 2.4v11.2L13.5 8z',
		'comment' => 'M3.5 1.5h9A1.5 1.5 0 0 1 14 3v6.5a1.5 1.5 0 0 1-1.5 1.5H7.2L4 13.8V11h-.5A1.5 1.5 0 0 1 2 9.5V3a1.5 1.5 0 0 1 1.5-1.5z',
		'wallet'  => 'M2.5 3A1.5 1.5 0 0 0 1 4.5v8A1.5 1.5 0 0 0 2.5 14h11a1.5 1.5 0 0 0 1.5-1.5V11h-3a2.5 2.5 0 0 1 0-5h3V4.5A1.5 1.5 0 0 0 13.5 3h-11zM12 7.25a1.25 1.25 0 0 0 0 2.5h3v-2.5h-3z',
	);
	if ( ! isset( $paths[ $name ] ) ) {
		return '';
	}
	return '<svg class="lc-icon" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true" focusable="false"><path d="' . $paths[ $name ] . '"/></svg>';
}

/**
 * URL of a child-theme asset with its modified time as the version, so each
 * deploy busts browser caches. Used for the child theme's stylesheets.
 */
function lc_theme_asset_url( $file ) {
	$path = get_stylesheet_directory() . '/' . $file;
	$ver  = file_exists( $path ) ? filemtime( $path ) : false;
	return add_query_arg( 'ver', $ver, get_stylesheet_directory_uri() . '/' . $file );
}

/**
 * The child theme's style.css and fonts.css, in the <head>.
 *
 * They used to be printed as <link>s after wp_footer(), so every page first
 * painted with only the inline critical CSS (a stale snapshot, since removed)
 * and then jumped when style.css arrived: mobile CLS 0.85-1.07. Depending on
 * the parent stylesheet keeps them after the parent and plugin styles, so the
 * cascade order is unchanged. The ver= query is the file's mtime
 * (lc_theme_asset_url()); SiteGround Optimizer must not strip it or swap in
 * its year-cached minified copy (see the filters below).
 */
function lc_enqueue_theme_styles() {
	$deps = array( 'lc-bootstrap' );
	wp_enqueue_style( 'cardanopress-bootstrap-child-theme', lc_theme_asset_url( 'style.css' ), $deps, null );
	wp_enqueue_style( 'cardanopress-bootstrap-child-theme-fonts', lc_theme_asset_url( 'fonts.css' ), array( 'cardanopress-bootstrap-child-theme' ), null );
}
add_action( 'wp_enqueue_scripts', 'lc_enqueue_theme_styles', 50 );

/**
 * Inline the two small render-blocking stylesheets in place of their
 * <link>s, so they cost no extra request before first paint:
 * - CardanoPress's stylesheet (~9KB, ~3KB gzipped). The plugin also
 *   preloaded a second build of the same CSS (style-*.css) that the page
 *   never applied; with no src left to preload, that request goes too.
 * - fonts.css (the @font-face rules, ~1.5KB).
 * Each handle keeps its place in the queue, so the cascade order is
 * unchanged. Skipped for anything larger, or with relative url()s that
 * would break once the CSS no longer sits next to its file.
 */
function lc_inline_small_styles() {
	$styles = wp_styles();
	foreach ( array( 'cardanopress-style', 'cardanopress-bootstrap-child-theme-fonts' ) as $handle ) {
		if ( empty( $styles->registered[ $handle ] ) || ! in_array( $handle, $styles->queue, true ) ) {
			continue;
		}
		$src = strtok( (string) $styles->registered[ $handle ]->src, '?' );
		if ( 0 !== strpos( $src, content_url() ) ) {
			continue;
		}
		$path = WP_CONTENT_DIR . substr( $src, strlen( content_url() ) );
		if ( ! is_readable( $path ) || filesize( $path ) > 12000 ) {
			continue;
		}
		$css = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file.
		if ( false === $css || preg_match( '/url\(\s*+[\'"]?+(?!data:|https?:|\/)/i', $css ) ) {
			continue;
		}
		$styles->registered[ $handle ]->src = false;
		wp_add_inline_style( $handle, $css );
	}
}
add_action( 'wp_enqueue_scripts', 'lc_inline_small_styles', 60 );

add_filter(
	'sgo_rqs_exclude',
	function ( $list ) {
		$list[] = 'learncardano-cardanopress-theme-child/(style|fonts)\.css';
		$list[] = 'learncardano-cardanopress-theme-child/assets/css/bootstrap-base\.min\.css';
		return $list;
	}
);
add_filter(
	'sgo_css_minify_exclude',
	function ( $list ) {
		$list[] = 'lc-bootstrap';
		$list[] = 'cardanopress-bootstrap-child-theme';
		$list[] = 'cardanopress-bootstrap-child-theme-fonts';
		return $list;
	}
);

/**
 * Preload the self-hosted Chivo + Mulish files (fonts.css) on the redesigned
 * views, where the hero H1 is Chivo and the body text Mulish. Without it the
 * browser only finds them after style.css and fonts.css are parsed, and the
 * swap from the fallback font reflows the hero and pushes the cards down.
 * The older templates (plain pages, 404) use Open Sans / Rubik
 * above the fold instead, so they preload those. Skipped on the front page
 * and podcast episodes, whose preloaded LCP image should not compete.
 */
function lc_preload_fonts() {
	if ( is_front_page() || is_singular( 'podcasts' ) ) {
		return;
	}
	$older = is_404() || ( is_page() && ! get_page_template_slug() );
	$fonts = $older ? array( 'rubik-latin-var', 'open-sans-latin-var' ) : array( 'chivo-latin-var', 'mulish-latin-var' );
	foreach ( $fonts as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( get_stylesheet_directory_uri() . '/assets/fonts/' . $font . '.woff2' )
		);
	}
}
add_action( 'wp_head', 'lc_preload_fonts', 2 );

/** `sizes` of the podcast episode's video thumbnail (single-podcasts.php). */
define( 'LC_PODCAST_PLAYER_SIZES', '(max-width: 767px) 100vw, 700px' );

/**
 * Preload the LCP image where we know it in advance, so it starts with the
 * stylesheets instead of after the parser reaches it:
 * - front page: the newest episode's featured image (template-parts/home-podcasts.php);
 * - podcast episode: the video facade thumbnail (single-podcasts.php), which
 *   is the featured image, or YouTube's still when there is none.
 */
function lc_preload_lcp_image() {
	if ( is_singular( 'podcasts' ) ) {
		$id = function_exists( 'lc_youtube_id' ) ? lc_youtube_id( get_field( 'youtube_video', get_queried_object_id(), false ) ) : '';
		if ( ! $id ) {
			return;
		}
		if ( ! lc_preload_attachment( get_post_thumbnail_id( get_queried_object_id() ), LC_PODCAST_PLAYER_SIZES ) ) {
			printf( '<link rel="preload" as="image" href="%s" fetchpriority="high">' . "\n", esc_url( 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg' ) );
		}
		return;
	}
	if ( ! is_front_page() ) {
		return;
	}
	$newest = get_posts(
		array(
			'post_type'      => 'podcasts',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	if ( $newest ) {
		lc_preload_attachment( get_post_thumbnail_id( $newest[0] ), '(max-width: 899px) 100vw, 610px' );
	}
}
add_action( 'wp_head', 'lc_preload_lcp_image', 2 );

/**
 * Print a high-priority preload for an attachment's 'large' srcset, matching
 * what wp_get_attachment_image() + includes/webp.php put in the page (the WebP
 * <source> when every size has a WebP copy). Returns false if nothing printed.
 *
 * @param int    $thumb_id Attachment ID.
 * @param string $sizes    The image's sizes attribute.
 * @return bool
 */
function lc_preload_attachment( $thumb_id, $sizes ) {
	$srcset = $thumb_id ? wp_get_attachment_image_srcset( $thumb_id, 'large' ) : '';
	if ( ! $srcset ) {
		return false;
	}
	$type = '';
	if ( function_exists( 'lc_webp_url' ) ) {
		$webp = array();
		foreach ( explode( ',', $srcset ) as $candidate ) {
			$parts = preg_split( '/\s+/', trim( $candidate ) );
			$url   = lc_webp_url( $parts[0] );
			if ( ! $url ) {
				$webp = array();
				break;
			}
			$webp[] = $url . ( isset( $parts[1] ) ? ' ' . $parts[1] : '' );
		}
		if ( $webp ) {
			$srcset = implode( ', ', $webp );
			$type   = ' type="image/webp"';
		}
	}
	printf(
		'<link rel="preload" as="image" imagesrcset="%s" imagesizes="%s"%s fetchpriority="high">' . "\n",
		esc_attr( $srcset ),
		esc_attr( $sizes ),
		$type // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static attribute.
	);
	return true;
}

/**
 * A post's primary category: Rank Math's primary pick when set, otherwise the
 * first category. Uncategorized counts as no category.
 */
function lc_post_primary_category( $post_id = null ) {
	$post_id = $post_id ? $post_id : get_the_ID();
	$term    = null;
	$primary = (int) get_post_meta( $post_id, 'rank_math_primary_category', true );
	if ( $primary ) {
		$term = get_term( $primary, 'category' );
	}
	if ( ! $term || is_wp_error( $term ) ) {
		$cats = get_the_category( $post_id );
		$term = $cats ? $cats[0] : null;
	}
	return ( $term && 'uncategorized' !== $term->slug ) ? $term : null;
}

/**
 * Categories used by published blog posts, most-used first, for the category
 * filter on the Resources archive. Cached, and cleared whenever a post is
 * saved or deleted or a category changes.
 */
function lc_blog_categories() {
	$terms = get_transient( 'lc_blog_categories' );
	if ( false !== $terms ) {
		return $terms;
	}
	$counts = array();
	$ids    = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		foreach ( wp_get_post_categories( $id ) as $term_id ) {
			$counts[ $term_id ] = ( $counts[ $term_id ] ?? 0 ) + 1;
		}
	}
	arsort( $counts );
	$terms = array();
	foreach ( array_keys( $counts ) as $term_id ) {
		$term = get_term( $term_id, 'category' );
		if ( $term && ! is_wp_error( $term ) && 'uncategorized' !== $term->slug ) {
			$terms[] = $term;
		}
	}
	set_transient( 'lc_blog_categories', $terms, DAY_IN_SECONDS );
	return $terms;
}
foreach ( array( 'save_post_post', 'deleted_post', 'edited_category', 'delete_category' ) as $lc_hook ) {
	add_action( $lc_hook, function () {
		delete_transient( 'lc_blog_categories' );
	} );
}

/**
 * Filter pills for the blog archives: "All" plus each category in use, with
 * the given category (term ID, 0 for "All") marked active.
 *
 * @return array<int, array{0: string, 1: string, 2: bool}>
 */
function lc_blog_category_pills( $active = 0 ) {
	$pills = array( array( __( 'All', 'learncardano' ), get_permalink( get_option( 'page_for_posts' ) ), ! $active ) );
	foreach ( lc_blog_categories() as $term ) {
		$pills[] = array( $term->name, get_term_link( $term ), (int) $active === (int) $term->term_id );
	}
	return $pills;
}

/**
 * Give every H2 in rendered post content an id and return the list for the
 * article's "On this page" box. Existing ids are kept. Posts that already
 * contain a table of contents (Rank Math or core block) get no generated list.
 *
 * @return array{0: string, 1: array<int, array{id: string, text: string}>}
 */
function lc_heading_ids_and_toc( $html ) {
	$toc  = array();
	$used = array();
	if ( false !== strpos( $html, 'rank-math-toc' ) || false !== strpos( $html, 'wp-block-table-of-contents' ) ) {
		return array( $html, $toc );
	}
	$html = preg_replace_callback(
		'/<h2(\s[^>]*)?>(.*?)<\/h2>/is',
		function ( $m ) use ( &$toc, &$used ) {
			$attrs = isset( $m[1] ) ? $m[1] : '';
			$text  = trim( wp_strip_all_tags( $m[2] ) );
			if ( '' === $text ) {
				return $m[0];
			}
			if ( preg_match( '/\sid=("|\')([^"\']+)\1/', $attrs, $id_match ) ) {
				$id = $id_match[2];
			} else {
				$base = sanitize_title( $text );
				$base = '' === $base ? 'section' : $base;
				$id   = $base;
				$n    = 2;
				while ( isset( $used[ $id ] ) ) {
					$id = $base . '-' . $n++;
				}
				$attrs .= ' id="' . esc_attr( $id ) . '"';
			}
			$used[ $id ] = true;
			$toc[]       = array(
				'id'   => $id,
				'text' => html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ),
			);
			return '<h2' . $attrs . '>' . $m[2] . '</h2>';
		},
		$html
	);
	return array( $html, $toc );
}

/**
 * Estimated reading time in minutes (220 words a minute, at least 1).
 */
function lc_reading_minutes( $html ) {
	return max( 1, (int) round( str_word_count( wp_strip_all_tags( $html ) ) / 220 ) );
}

/**
 * Up to $count published posts related to a post: same primary category
 * first, topped up with the latest posts.
 *
 * @return WP_Post[]
 */
function lc_related_posts( $post_id, $count = 3 ) {
	$base     = array(
		'post_type'           => 'post',
		'post_status'         => 'publish',
		'posts_per_page'      => $count,
		'post__not_in'        => array( $post_id ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	);
	$related  = array();
	$category = lc_post_primary_category( $post_id );
	if ( $category ) {
		$related = get_posts( array_merge( $base, array( 'cat' => $category->term_id ) ) );
	}
	if ( count( $related ) < $count ) {
		$exclude = array_merge( array( $post_id ), wp_list_pluck( $related, 'ID' ) );
		$related = array_merge(
			$related,
			get_posts(
				array_merge(
					$base,
					array(
						'posts_per_page' => $count - count( $related ),
						'post__not_in'   => $exclude,
					)
				)
			)
		);
	}
	return $related;
}

/**
 * Dequeue plugin assets on pages that don't need them.
 *
 * Runs at priority 100 so it fires after plugins have enqueued.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}

	// LearnDash was removed: courses are theme templates now (includes/courses.php).
	// Gravity Forms and WP Popups were replaced by includes/forms.php and
	// includes/newsletter-popup.php, which only load their assets when used.

	// UserAccessManager's login-form CSS only matters on its own login form.
	// Drop it on regular content; it'll still load if UAM injects its form.
	if ( ! is_page( 'login' ) && ! is_page( 'register' ) ) {
		wp_dequeue_style( 'UserAccessManagerLoginForm' );
	}
}, 100 );

/**
 * Defer non-critical scripts (safe subset only).
 *
 * An earlier version of this filter also deferred cardanopress-bootstrap-script,
 * cardanopress-script, cardanopress-notification, jquery-migrate, and the
 * @wordpress/* runtime bundles (wp-hooks, wp-dom-ready, wp-i18n, wp-a11y).
 * That broke the live site in two ways:
 *
 *  1. The body element has x-data="cardanoPress" — Alpine evaluates that
 *     expression as soon as alpinejs.min.js runs. If cardanopress-bootstrap-script
 *     hasn't run yet (because we deferred it), the cardanoPress() function
 *     doesn't exist and every directive that references isConnected / showModal /
 *     supportedWallets / $store.toastNotification throws a ReferenceError.
 *  2. wp-i18n / wp-hooks etc. emit inline-after scripts that synchronously
 *     reference the `wp` global. Deferring the parent module breaks those
 *     inline blocks ("wp is not defined").
 *
 * Reverted to a small, demonstrably safe defer list — purely progressive-
 * enhancement assets with no inline-after / Alpine consumers.
 */
add_filter( 'script_loader_tag', function ( $tag, $handle ) {
	if ( is_admin() ) {
		return $tag;
	}
	$defer = array(
		'siteground-optimizer-lazy-sizes-js',
	);
	if ( ! in_array( $handle, $defer, true ) ) {
		return $tag;
	}
	if ( false !== strpos( $tag, ' defer ' ) || false !== strpos( $tag, ' async' ) || false !== strpos( $tag, ' type=\'module\'' ) ) {
		return $tag;
	}
	return preg_replace( '/(?=><\/script>)/', ' defer', $tag, 1 );
}, 10, 2 );

// No third-party origin is needed for first render any more, so the site
// adds no preconnect / dns-prefetch hints (a hint to an origin that isn't
// used early only competes with the page's own requests):
// - fonts are self-hosted (fonts.css), not fonts.gstatic.com;
// - Tag Manager loads after the page is idle (footer.php);
// - YouTube, Spotify and Spreaker players are click-to-load facades.
// Episode pages still preload their own LCP image (lc_preload_lcp_image()).

/**
 * The parent theme enqueues a Google Fonts stylesheet (Lato + Open Sans),
 * which SiteGround Optimizer then inlined into every page as @font-face
 * rules pointing at fonts.gstatic.com. Open Sans and Rubik are self-hosted
 * in fonts.css; Lato isn't used.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_dequeue_style( 'cardanopress_bootstrap-fonts' );
	},
	6
);


// jQuery is intentionally left at WordPress's bundled version so it can be
// served from the site's own origin (cacheable by Cloudflare) instead of
// ajax.googleapis.com. The previous Google-CDN swap added a third-party DNS
// hop with no benefit on a Cloudflare-fronted site.

function lc_post_has_code_block() {
    if ( ! is_singular() ) {
        return false;
    }
    $post = get_post();
    if ( ! $post ) {
        return false;
    }
    if ( function_exists( 'has_block' ) && has_block( 'code', $post ) ) {
        return true;
    }
    return ( false !== stripos( $post->post_content, '<pre' ) && false !== stripos( $post->post_content, '<code' ) );
}

function lc_enqueue_prism() {
    if ( ! lc_post_has_code_block() ) {
        return;
    }
    $base = get_stylesheet_directory_uri() . '/assets/prism/';
    $ver  = '1.29.0';

    wp_enqueue_style( 'prismjs', $base . 'prism-tomorrow.min.css', array(), $ver );
    wp_enqueue_style( 'prismjs-toolbar', $base . 'prism-toolbar.min.css', array(), $ver );

    wp_enqueue_script( 'prismjs', $base . 'prism-core.min.js', array(), $ver, true );
    wp_enqueue_script( 'prismjs-autoloader', $base . 'prism-autoloader.min.js', array( 'prismjs' ), $ver, true );
    wp_enqueue_script( 'prismjs-toolbar', $base . 'prism-toolbar.min.js', array( 'prismjs' ), $ver, true );
    wp_enqueue_script( 'prismjs-copy', $base . 'prism-copy-to-clipboard.min.js', array( 'prismjs-toolbar' ), $ver, true );

    // Point the autoloader at our locally bundled language components.
    wp_add_inline_script(
        'prismjs-autoloader',
        'Prism.plugins.autoloader.languages_path = ' . wp_json_encode( $base . 'components/' ) . ';',
        'before'
    );

    foreach ( array( 'prismjs', 'prismjs-autoloader', 'prismjs-toolbar', 'prismjs-copy' ) as $handle ) {
        wp_script_add_data( $handle, 'strategy', 'defer' );
    }
}
add_action('wp_enqueue_scripts', 'lc_enqueue_prism');

function lc_prism_autodetect() {
    if ( ! lc_post_has_code_block() ) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var blocks = document.querySelectorAll('.wp-block-code code');
        if (!blocks.length) { return; }
        blocks.forEach(function(block) {
            if (!block.className.includes('language-')) {
                var text = block.textContent;
                if (text.includes('import {') || text.includes('async function') || 
                    text.includes(': string') || text.includes(': number')) {
                    block.classList.add('language-typescript');
                } else if (text.includes('npm install') || text.includes('aiken ') || 
                           text.startsWith('aiken') || text.startsWith('node ')) {
                    block.classList.add('language-bash');
                } else if (text.includes('use aiken') || text.includes('validator ') || 
                           text.includes('pub type')) {
                    block.classList.add('language-haskell'); // closest to Aiken
                } else {
                    block.classList.add('language-javascript');
                }
            }
        });
        Prism.highlightAll();
    });
    </script>
    <?php
}
add_action('wp_footer', 'lc_prism_autodetect');


