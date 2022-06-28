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
            'supports'        => array( 'title', 'editor', 'thumbnail' ),
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