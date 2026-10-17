<?php

/**
 * Generic archive: post type archives without their own template (the
 * knowledge base types, LearnDash groups…), taxonomy, tag, author and date
 * archives. Podcasts, courses, proposals and blog categories have their own.
 *
 * Uses the podcast-archive hero with the real archive title (this template
 * used to say "Learn Cardano Podcast Episode Archives" everywhere). Knowledge
 * base archives get the compact card grid and type filter; everything else
 * gets the large cards.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

add_filter( 'get_the_archive_title_prefix', '__return_empty_string' );

$lc_kb_types   = lc_kb_types();
$lc_kb_type    = is_post_type_archive( array_keys( $lc_kb_types ) ) ? get_query_var( 'post_type' ) : '';
$lc_kb_type    = is_array( $lc_kb_type ) ? reset( $lc_kb_type ) : $lc_kb_type;
$lc_is_kb      = $lc_kb_type || is_tax( lc_kb_taxonomies() );
$lc_title      = wp_strip_all_tags( get_the_archive_title() );
$lc_subtitle   = get_the_archive_description();
if ( ! $lc_subtitle && $lc_kb_type ) {
	$lc_subtitle = $lc_kb_types[ $lc_kb_type ]['blurb'];
}
?>

<main class="resources-archive <?php echo cardanopress_bootstrap_class( 'content-full' ); ?>">

	<?php
	get_template_part(
		'template-parts/archive-hero',
		null,
		array(
			'title'       => $lc_title,
			'subtitle'    => $lc_subtitle,
			'placeholder' => $lc_is_kb ? __( 'Search Learn Cardano', 'learncardano' ) : __( 'Search articles', 'learncardano' ),
			'post_type'   => $lc_is_kb ? '' : 'post',
			'pills'       => $lc_is_kb ? lc_kb_type_pills( (string) $lc_kb_type ) : array(),
			'pills_label' => __( 'Knowledge base sections', 'learncardano' ),
		)
	);

	if ( $lc_is_kb ) {
		get_template_part( 'template-parts/kb-grid' );
	} else {
		get_template_part(
			'template-parts/archive-list',
			null,
			array(
				'empty'     => __( 'Nothing here yet.', 'learncardano' ),
				'nav_label' => __( 'Archive navigation', 'learncardano' ),
			)
		);
	}
	?>

</main><!-- .resources-archive -->

<?php

get_footer();
