<?php

add_theme_support( 'responsive-embeds' );

if ( class_exists( 'ThemePlate' ) ) :
	ThemePlate( array(
		'title' => 'Learn Cardano',
		'key'   => 'lc',
	) );

	
	require_once 'setup/post-types.php';
endif;


if ( ! function_exists( 'lc_widgets_init' ) ) {
    function lc_widgets_init() {
        register_sidebar( array(
            'id'            => 'FooterCol1',
            'name'          => __( 'Footer Col 1', 'learncardano' ),
            'description'   => __( 'Footer Col 1', 'learncardano' ),
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
            'before_widget' => '<div class="section %2$s">',
            'after_widget'  => '</div>'
        ) );
        register_sidebar( array(
            'id'            => 'FooterCol2',
            'name'          => __( 'Footer Col 2', 'learncardano' ),
            'description'   => __( 'Footer Col 2', 'learncardano' ),
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
            'before_widget' => '<section class="widget %2$s">',
            'after_widget'  => '</section>'
        ) );
        register_sidebar( array(
            'id'            => 'FooterCol3',
            'name'          => __( 'Footer Col 3', 'learncardano' ),
            'description'   => __( 'Footer Col 3', 'learncardano' ),
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
            'before_widget' => '<section class="widget %2$s">',
            'after_widget'  => '</section>'
        ) );

    }
    add_action( 'widgets_init', 'lc_widgets_init' );
}


if( function_exists('acf_add_local_field_group') ):

    add_action( 'acf/include_fields', function() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	acf_add_local_field_group( array(
	'key' => 'group_62ba92870d1cc',
	'title' => 'Podcast Episode Meta',
	'fields' => array(
		array(
			'key' => 'field_62ba92a41d393',
			'label' => 'Embed Code',
			'name' => 'spreaker_code',
			'aria-label' => '',
			'type' => 'text',
			'instructions' => 'Inline code for embed

<iframe style="border-radius:12px" src="https://open.spotify.com/embed/episode/3Qf2wWqpPwecaiCNOQYxwH?utm_source=generator" width="100%" height="100" frameBorder="0" allowfullscreen="" allow="clipboard-write; encrypted-media; fullscreen" loading="lazy"></iframe>',
			'required' => 1,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'default_value' => '<iframe style="border-radius:12px" src="https://open.spotify.com/embed/episode/3Qf2wWqpPwecaiCNOQYxwH?utm_source=generator" width="100%" height="100" frameBorder="0" allowfullscreen="" allow="clipboard-write; encrypted-media; fullscreen" loading="lazy"></iframe>',
			'maxlength' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
		),
		array(
			'key' => 'field_62ba92de1d394',
			'label' => 'YouTube Video',
			'name' => 'youtube_video',
			'aria-label' => '',
			'type' => 'text',
			'instructions' => 'Just the video ID :)',
			'required' => 1,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'default_value' => '',
			'maxlength' => '',
			'placeholder' => '',
			'prepend' => '',
			'append' => '',
		),
		array(
			'key' => 'field_64a7e9539f809',
			'label' => 'Text transcript',
			'name' => 'text_transcript',
			'aria-label' => '',
			'type' => 'wysiwyg',
			'instructions' => '',
			'required' => 0,
			'conditional_logic' => 0,
			'wrapper' => array(
				'width' => '',
				'class' => '',
				'id' => '',
			),
			'default_value' => '',
			'maxlength' => '',
			'rows' => '',
			'placeholder' => '',
			'new_lines' => '',
		),
	),
	'location' => array(
		array(
			array(
				'param' => 'post_type',
				'operator' => '==',
				'value' => 'podcasts',
			),
		),
	),
	'menu_order' => 0,
	'position' => 'normal',
	'style' => 'default',
	'label_placement' => 'top',
	'instruction_placement' => 'label',
	'hide_on_screen' => '',
	'active' => true,
	'description' => '',
	'show_in_rest' => 0,
) );
} );
    
    endif;		


function cardanopress_child_bootstrap_class( string $key ): string {
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

    if ( 'content' === $key ) {
        $exta_class_key     = is_active_sidebar( 'sidebar' ) ? 'content-active-sidebar' : 'content-inactive-sidebar';
        $classes['content'] = array_merge( $classes['content'], $classes[ $exta_class_key ] );
    } elseif ( 'content-full' === $key ) {
        $key                = 'content';
        $classes['content'] = array_merge( $classes['content'], array( 'w-100' ) );
    }

    if ( array_key_exists( $key, $classes ) ) {
        $class_string = implode( ' ', $classes[ $key ] );
    }

    return $class_string;
}

add_filter( 'comment_form_defaults', function( $fields ) {
	
	if ( !is_user_logged_in() ) :
	
    $fields['must_log_in'] = sprintf( 
            __(do_shortcode('[learndash_login login_label=" You must be logged in to post a comment."]')), wp_registration_url(), wp_login_url( apply_filters( 'the_permalink', get_permalink() ) )   
    );

    return $fields;
	
	endif;
});

add_filter( 'get_comment_author', function (  $author, $comment_ID, $comment )
{
    // Get the current post object
    $post = get_post();

    // The user ids are the same, lets append our extra text
	if(strlen($author) >= 10) {
		$author = substr($author, 0, 5)."...";
	} else {
		$author = $author;
	}
    
    return $author;
}, 10, 3 );

add_action('init', 'enable_proposal_comments');
function enable_proposal_comments() {
	add_post_type_support( 'proposal', 'comments' );
}


function wpb_modify_jquery() {
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