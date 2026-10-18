<?php

/**
 * Blog category archive (/category/{slug}/), in the same layout as the
 * Resources listing, with the matching category highlighted in the filter.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

$lc_category = get_queried_object();
$lc_subtitle = category_description();
if ( ! $lc_subtitle ) {
	/* translators: %s: category name */
	$lc_subtitle = sprintf( __( 'Every Learn Cardano article filed under %s.', 'learncardano' ), single_cat_title( '', false ) );
}
?>

<main class="resources-archive <?php echo lc_layout_class( 'content-full' ); ?>">

	<?php
	get_template_part(
		'template-parts/archive-hero',
		null,
		array(
			'title'           => single_cat_title( '', false ),
			'subtitle'        => $lc_subtitle,
			'pills'           => lc_blog_category_pills( $lc_category ? $lc_category->term_id : 0 ),
			'pills_label'     => __( 'Article categories', 'learncardano' ),
		)
	);

	get_template_part(
		'template-parts/archive-list',
		null,
		array(
			'empty'     => 'No articles in this category yet.',
			'nav_label' => __( 'Articles navigation', 'learncardano' ),
		)
	);
	?>

</main><!-- .resources-archive -->

<?php

get_footer();
