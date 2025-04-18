<?php

require_once 'includes/lockbox.php';

add_theme_support('responsive-embeds');


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
