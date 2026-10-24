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
	<main class="<?php echo lc_layout_class( 'content-full' ); ?>">

		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<?php if ( false === stripos( get_the_content(), '<h1' ) ) : ?>
				<h1 class="entry-title"><?php echo esc_html( get_the_title() ); ?></h1>
			<?php endif; ?>
			<?php the_content(); ?>
		<?php endwhile; ?>

	</main><!-- .content -->
</div>

<?php

get_footer();
