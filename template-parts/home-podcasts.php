<?php

/**
 * The template for displaying home articles section
 *
 * @package Learn Cardano
 * @since 0.1.0
 */

$id = get_option( 'page_on_front' );
$args = array (
    'post_type' => 'podcasts',
    'posts_per_page' => 3
);
$query = new WP_Query( $args );

if ( ! $title && ! $query ) {
    return;
}

set_query_var( 'post_type', 'podcasts' );

?>

<?php while ( $query->have_posts() ) : $query->the_post(); ?>
    <?php get_template_part( 'template-parts/item', 'wrapper' ); ?>
<?php endwhile; ?>