<?php

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


/* CUSTOM POST TYPE PODCASTS */


// Rename defaults

ThemePlate()->post_type(
    array(
        'name'        => 'podcasts',
        'plural'      => __( 'Episodes', 'learncardano' ),
        'singular'    => __( 'Episodes', 'learncardano' ),
        'description' => __( 'Podcast episodes', 'learncardano' ),
        'args'        => array(
            'public'          => true,
            'menu_position'   => 5,
            'menu_icon'       => 'dashicons-controls-play',
            'capability_type' => 'post',
            'hierarchical'    => false,
            'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'comments', 'author' ),
            'has_archive'     => true,
            'rewrite'         => array(
                'slug'       => 'podcasts',
                'with_front' => false,
            ),
            'labels'          => array(
                'name'           => 'Podcasts',
                'search_items'   => 'Podcasts',
                'archives'       => 'Podcasts',
                'menu_name'      => 'Podcasts',
                'name_admin_bar' => 'Podcasts',
            ),
        ),
    )
);

ThemePlate()->taxonomy(
    array(
        'name'     => 'pcategory',
        'singular' => __( 'Category', 'learncardano' ),
        'plural'   => __( 'Categories', 'learncardano' ),
        'type'     => array( 'podcasts' ),
        'args'     => array(
            'hierarchical' => true,
            'rewrite'      => array(
                'with_front' => false,
            ),
        ),
    )
);

ThemePlate()->taxonomy(
    array(
        'name'     => 'ptag',
        'singular' => __( 'Tag', 'learncardano' ),
        'plural'   => __( 'Tags', 'learncardano' ),
        'type'     => array( 'podcasts' ),
        'args'     => array(
            'hierarchical' => false,
            'rewrite'      => array(
                'with_front' => false,
            ),
        ),
    )
);


if ( ! function_exists( 'lc_page_tags' ) ) {
    function lc_page_tags() {
        register_taxonomy_for_object_type( 'post_tag', 'page' );
    }
    add_action( 'init', 'lc_page_tags' );
}



if( function_exists('acf_add_local_field_group') ):

    acf_add_local_field_group(array(
        'key' => 'group_62ba92870d1cc',
        'title' => 'Podcast Episode Meta',
        'fields' => array(
            array(
                'key' => 'field_62ba92a41d393',
                'label' => 'Spreaker Code',
                'name' => 'spreaker_code',
                'type' => 'text',
                'instructions' => 'Single line Spreaker JS code',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
                'maxlength' => '',
            ),
            array(
                'key' => 'field_62ba92de1d394',
                'label' => 'YouTube Video',
                'name' => 'youtube_video',
                'type' => 'text',
                'instructions' => 'Just the video ID',
                'required' => 0,
                'conditional_logic' => 0,
                'wrapper' => array(
                    'width' => '',
                    'class' => '',
                    'id' => '',
                ),
                'default_value' => '',
                'placeholder' => '',
                'prepend' => '',
                'append' => '',
                'maxlength' => '',
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
    ));

endif;


function cardanopress_child_bootstrap_class( string $key ): string {
    $classes = array(
        'content' => array(
            'content',
        ),

        'content-active-sidebar' => array(
            'col-sm-12',
            'col-md-7',
            'col-lg-8',
        ),

        'content-inactive-sidebar' => array(
            'inactive-sidebar',
            'w-100',
        ),

        'sidebar' => array(
            'sidebar',
            'py-5',
            'col-sm-12',
            'col-md-5',
            'col-lg-4',
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