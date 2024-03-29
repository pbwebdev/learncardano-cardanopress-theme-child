<?php

/**
 * The template for displaying single posts
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

?>

<div class="container">
	<div class="content-sidebar row">
		<main class="<?php echo cardanopress_child_bootstrap_class( 'content' ); ?> ">

			<?php while ( have_posts() ) : ?>
				<?php the_post(); ?>

				<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>					

					<div class="entry-wrap">
						<header class="entry-header">
							<h1 class="entry-title clearfix"><?php the_title(); ?></h1>
						</header>

						<div class="entry-content">
							<?php the_content(); ?>
						</div>

						<footer class="entry-footer">
							<?php the_tags(); ?>
						</footer>
                        <div class="entry-meta">
                            <time>Posted: <?php the_date(); ?></time>
                            <span class="float-end">Written by <?php the_author(); ?></span>
                        </div>
					</div>
				</article><!-- #post-<?php the_ID(); ?> -->

			<?php endwhile; ?>

		</main><!-- .content -->

		<?php get_sidebar(); ?>

		
                       
        <?php
        // If comments are open or we have at least one comment, load up the comment template.
        if ( comments_open() || get_comments_number() ) : ?>

        <div class="container">
            <div class="row justify-content-md-left">
                <div class="col-lg-8 col-md-8">
                        <?php comments_template(); ?>
                </div>
            </div>
        </div>

        <?php endif; ?>
	</div><!-- .content-sidebar -->
</div><!-- .container -->

<?php

get_footer();
