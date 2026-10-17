<?php

/**
 * Resources — the blog posts index (the posts page set in Settings → Reading).
 *
 * Matches the podcast archive (archive-podcasts.php): hero with gradient title,
 * article search and a category filter, then large cards and numbered
 * pagination. Built from template-parts/archive-{hero,list,card}.php, which
 * category.php shares. index.php is left alone because it is WordPress's
 * fallback for every other view. (Search results are rendered by the Algolia
 * plugin's InstantSearch page, not the theme.)
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();
?>

<main class="resources-archive <?php echo cardanopress_bootstrap_class( 'content-full' ); ?>">

	<?php
	get_template_part(
		'template-parts/archive-hero',
		null,
		array(
			'title'           => 'Cardano Resources & Tutorials',
			'subtitle'        => 'Tutorials, explainers, governance updates and news from across the Cardano ecosystem.',
			'show_categories' => true,
		)
	);

	get_template_part(
		'template-parts/archive-list',
		null,
		array(
			'empty'     => 'No articles found.',
			'badge'     => 'Latest',
			'nav_label' => __( 'Articles navigation', 'learncardano' ),
		)
	);
	?>

</main><!-- .resources-archive -->

<?php

get_footer();
