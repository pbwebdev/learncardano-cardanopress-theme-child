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


function wpb_modify_jquery()
{
	// Check if front-end is being viewed
	if (!is_admin()) {
		// Remove default WordPress jQuery
		wp_deregister_script('jquery');
		// Register new jQuery script via Google Library
		wp_register_script('jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js', false, '3.6.0');
		// Enqueue the script
		wp_enqueue_script('jquery');
	}
}
// Execute the action when WordPress is initialized
add_action('init', 'wpb_modify_jquery');

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
    wp_enqueue_style(
        'prismjs',
        'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/themes/prism-tomorrow.min.css'
    );
    // Copy button plugin CSS
    wp_enqueue_style(
        'prismjs-toolbar',
        'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/toolbar/prism-toolbar.min.css'
    );
    wp_enqueue_script(
        'prismjs',
        'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/components/prism-core.min.js',
        [], null, true
    );
    wp_enqueue_script(
        'prismjs-autoloader',
        'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/autoloader/prism-autoloader.min.js',
        ['prismjs'], null, true
    );
    // Toolbar (required by copy button)
    wp_enqueue_script(
        'prismjs-toolbar',
        'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/toolbar/prism-toolbar.min.js',
        ['prismjs'], null, true
    );
    // Copy to clipboard plugin
    wp_enqueue_script(
        'prismjs-copy',
        'https://cdnjs.cloudflare.com/ajax/libs/prism/1.29.0/plugins/copy-to-clipboard/prism-copy-to-clipboard.min.js',
        ['prismjs-toolbar'], null, true
    );
}
add_action('wp_enqueue_scripts', 'lc_enqueue_prism');

function lc_prism_autodetect() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.wp-block-code code').forEach(function(block) {
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


