<?php

/**
 * The template for displaying pages
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

?>

<div class="container col-10">
	<main class="<?php echo cardanopress_bootstrap_class( 'content-full' ); ?>">

		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<?php the_content(); ?>
		<?php endwhile; ?>

	</main><!-- .content -->
</div>

<?php

get_footer();
