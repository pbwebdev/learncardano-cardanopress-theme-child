<?php

/**
 * Resources — the blog posts index (the posts page set in Settings → Reading).
 *
 * Matches the podcast archive (archive-podcasts.php): hero with gradient title,
 * article search and a category filter, then large cards and numbered
 * pagination. Built from template-parts/archive-{hero,list,card}.php, which
 * category.php shares. index.php is left alone because it is WordPress's
 * fallback for every other view. Search results use search.php.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();
?>

<main class="resources-archive <?php echo lc_layout_class( 'content-full' ); ?>">

	<?php
	get_template_part(
		'template-parts/archive-hero',
		null,
		array(
			'title'           => 'Crypto Resources & Tutorials',
			'subtitle'        => 'Tutorials, explainers and news from across crypto, with a strong focus on Cardano.',
			'pills'           => lc_blog_category_pills(),
			'pills_label'     => __( 'Article categories', 'learncardano' ),
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
