<?php

/**
 * The template for displaying pages
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

$lc_term_sub = term_description();
if ( ! $lc_term_sub ) {
	/* translators: %s: podcast category name */
	$lc_term_sub = sprintf( __( 'Every Learn Cardano Podcast episode filed under %s.', 'learncardano' ), single_term_title( '', false ) );
}
?>
    <main class="<?php echo cardanopress_bootstrap_class( 'content-full' ); ?>">

        <section class="pa__hero">
            <h1 class="pa__h1"><?php
                /* translators: %s: podcast category name */
                echo esc_html( sprintf( __( '%s Episodes', 'learncardano' ), single_term_title( '', false ) ) );
            ?></h1>
            <div class="pa__sub"><?php echo wp_kses_post( wpautop( $lc_term_sub ) ); ?></div>
        </section>

        <div class="latest section">
            <div class="container">

                <div class="episode-list ">

                    <div class="row  justify-content-md-center">
                        <div class="col-md-10">

                        <?php if ( have_posts() ) : ?>
				            <?php while ( have_posts() ) : the_post(); ?>

                            
                            <article id="<?php echo esc_attr( get_post_type() . '-' . get_the_ID() ); ?>" class="">

                            <div class="episode-card">

                                <div class="row">
                                    <div class="col-md-3 episode-image align-self-start">
                                        <?php if ( has_post_thumbnail() ) : ?>
                                            <?php
                                            // The first episode is the mobile LCP image: load it eagerly at
                                            // high priority and keep SiteGround Optimizer's JS lazy-load off
                                            // it (skip-lazy); the rest stay lazy.
                                            $lc_thumb_attr = array( 'sizes' => '(max-width: 767px) 100vw, 300px' );
                                            if ( 0 === $GLOBALS['wp_query']->current_post ) {
                                                $lc_thumb_attr += array(
                                                    'class'         => 'attachment-large size-large wp-post-image skip-lazy',
                                                    'loading'       => 'eager',
                                                    'fetchpriority' => 'high',
                                                    'decoding'      => 'async',
                                                );
                                            }
                                            the_post_thumbnail( 'large', $lc_thumb_attr );
                                            ?>
                                        <?php endif; ?>
                            
                                    </div>
                                    <div class="col-md-8 offset-md-1 episode-details">

                                        <div class="row metadata">
                                            <div class="episode-tags col">
    <?php
        // Episode tags ('ptag'), laid out as in taxonomy-ptag.php. This used to
        // echo an undefined $tags and ask for an unregistered 'episodes'
        // taxonomy; echoing that WP_Error was a fatal error, so every
        // /pcategory/ page returned HTTP 500.
        $lc_terms = get_the_term_list( $post->ID, 'ptag', '<span class="badge rounded-pill text-bg-primary">', ',</span><span class="badge rounded-pill text-bg-primary">', '</span>' );
        if ( $lc_terms && ! is_wp_error( $lc_terms ) ) {
            echo $lc_terms;
        }
        ?>
                                            </div>

                                            <div class="episode-date col text-end"> <?php the_time('F jS, Y') ?></div>
                                        </div>


                                        <div class="episode-title">
                                            <h3 id="post-<?php the_ID(); ?>">
                                                <a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>">
                                                    <?php the_title(); ?></a>
                                            </h3>
                                        </div>
                                        <div class="episode-excerpt">
                                            <?php the_excerpt(); ?>
                                        </div>
                                        <a href="<?php the_permalink() ?>" class="btn btn-primary"  rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>">
                                            <?php echo lc_icon( 'play' ); ?> Play episode
                                        </a> <span class="btn-secondary btn"><?php echo lc_icon( 'comment' ); ?> <?php printf( _n( '%d comment', '%d comments', get_comments_number(), 'learncardano' ), get_comments_number() ); ?></span>

                                    </div>
                                </div>

                            </div>


                            </article><!-- #single-<?php the_ID(); ?> -->

                            

                            <?php endwhile; ?>

				            <?php the_posts_pagination(); ?>

			            <?php endif; ?>

                        </div>
                    </div>

                </div>
                
            </div>
        </div>

    </main><!-- .content -->

<?php

get_footer();
