<?php

require_once 'includes/lockbox.php';

add_theme_support('responsive-embeds');
add_filter('rest_enabled', '__return_false');
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

add_filter('comment_form_defaults', function ($fields) {

	if (!is_user_logged_in()) :


        $button = do_shortcode('[cardanopress_template name="part/modal-trigger" if="!isConnected"]');
        $fields['must_log_in'] = "<p>Login with your wallet to comment</p>" . $button ;

		return $fields;

	endif;
});

add_filter('get_comment_author', function ($author, $comment_ID, $comment) {
	// Get the current post object
	$post = get_post();

	// The user ids are the same, lets append our extra text
	if (strlen($author) >= 10) {
		$author = substr($author, 0, 5) . "...";
	} else {
		$author = $author;
	}

	return $author;
}, 10, 3);

add_action('init', 'enable_proposal_comments');
function enable_proposal_comments()
{
	add_post_type_support('proposal', 'comments');
}

/**
 * Dequeue plugin assets on pages that don't need them.
 *
 * The site loads ~18 render-blocking stylesheets on every page from
 * plugins whose features aren't actually used there (LearnDash on a
 * podcast post, GravityForms on every page, etc.). Strip those so
 * each page only pays for what it actually renders.
 *
 * Runs at priority 100 so it fires after plugins have enqueued.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() ) {
		return;
	}

	$queried        = get_queried_object();
	$post_content   = ( $queried instanceof WP_Post ) ? $queried->post_content : '';
	$has_gform      = $post_content && (
		has_shortcode( $post_content, 'gravityform' )
		|| has_shortcode( $post_content, 'gravityforms' )
		|| ( function_exists( 'has_block' ) && $queried instanceof WP_Post && has_block( 'gravityforms/form', $queried ) )
	);
	$has_popup      = $post_content && (
		has_shortcode( $post_content, 'wppopup' )
		|| has_shortcode( $post_content, 'wppopups' )
	);

	// Note: an earlier version of this function deregistered `dashicons` for
	// anonymous frontend visitors. Don't. LearnDash registers `learndash-front`
	// (the entire LD30 theme stylesheet) with `dashicons` as a dependency, so
	// dropping dashicons silently dropped LD30 from every page on the site
	// and the LearnDash login modal rendered as raw HTML above the layout.
	// Saving was tiny; the breakage was not.

	// LearnDash CSS + JS bundle: only safe to strip on the homepage, which
	// uses front-page.php (verified to contain no LD shortcodes/blocks/queries).
	// Going broader was tried in PR #9 and broke course pages — restricted to
	// is_front_page() only this time. Verified via wp_styles->registered that
	// nothing else in the registered styles list depends on these handles,
	// so dequeuing them here doesn't cascade.
	if ( is_front_page() ) {
		$front_page_ld_handles = array(
			// styles
			'learndash',
			'learndash-front',
			'learndash-admin-bar',
			'learndash-course-grid-skin-grid',
			'learndash-course-grid-pagination',
			'learndash-course-grid-filter',
			'learndash-course-grid-card-grid-1',
			'jquery-dropdown-css',
			'learndash_lesson_video',
			'learndash_quiz_front_css',
			// scripts (SG-Optimizer-combined variants share the same handles)
			'learndash-front',
			'learndash-main',
			'learndash-breakpoints',
			'learndash-course-grid-skin-grid',
			'learndash-course-grid-elementor',
			'jquery-dropdown',
		);
		foreach ( $front_page_ld_handles as $h ) {
			wp_dequeue_style( $h );
			wp_dequeue_script( $h );
		}
	}

	// GravityForms only needed on pages that embed a form.
	if ( ! $has_gform ) {
		foreach ( array( 'gform_basic', 'gform_theme_components', 'gform_theme', 'gform_legacy_multifile', 'gform_chosen' ) as $h ) {
			wp_dequeue_style( $h );
		}
		foreach ( array( 'gform_gravityforms', 'gform_conditional_logic', 'gform_json', 'gform_textarea_counter', 'gform_masked_input', 'gform_chosen' ) as $h ) {
			wp_dequeue_script( $h );
		}
	}

	// wp-popups-lite only needed when a popup is actually triggered on the page.
	if ( ! $has_popup ) {
		wp_dequeue_style( 'wppopups-base' );
		wp_dequeue_script( 'wppopups-base' );
	}

	// UserAccessManager's login-form CSS only matters on its own login form.
	// Drop it on regular content; it'll still load if UAM injects its form.
	if ( ! is_page( 'login' ) && ! is_page( 'register' ) ) {
		wp_dequeue_style( 'UserAccessManagerLoginForm' );
	}
}, 100 );

/**
 * Add preconnect / dns-prefetch hints for the third-party origins the
 * site loads on every page (analytics, embedded media). Lets the browser
 * warm DNS + TLS in parallel with HTML parsing instead of waiting until
 * it sees the <script>/<iframe>.
 */
add_filter( 'wp_resource_hints', function ( $urls, $relation_type ) {
	if ( 'preconnect' === $relation_type ) {
		$urls[] = array( 'href' => 'https://www.googletagmanager.com', 'crossorigin' );
		$urls[] = array( 'href' => 'https://widget.spreaker.com', 'crossorigin' );
		$urls[] = array( 'href' => 'https://cdn.jsdelivr.net', 'crossorigin' );
	}
	if ( 'dns-prefetch' === $relation_type ) {
		$urls[] = 'https://i.ytimg.com';
		$urls[] = 'https://www.youtube.com';
	}
	return $urls;
}, 10, 2 );


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


