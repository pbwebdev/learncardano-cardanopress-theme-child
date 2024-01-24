<?php

/**
 * The template for displaying pages
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

?>


<div class="archive-header py-5">
    <div class="container">
        <div class="row justify-content-md-center">
            <div class="col-md-8 text-center">
                <h1>Cardano Resources & Tutorials</h1>
            </div>
        </div>
    </div>
</div>

    <main class="podcast-category <?php echo cardanopress_bootstrap_class( 'content-full' ); ?>">

        <div class="latest section">
            <div class="container">

                <div class="episode-list ">

                    <div class="row  justify-content-md-center">
                        <div class="col-md-10">

                            <?php if ( have_posts() ) : ?>
                                <?php while ( have_posts() ) : the_post(); ?>
                                    <article id="<?php echo $post_type . '-' . get_the_ID(); ?>" class="">

                                        <div class="episode-card">

                                            <div class="row">
                                                <div class="col-md-3 episode-image align-self-start">
                                                    <?php if ( has_post_thumbnail() ) : ?>
                                                        <?php the_post_thumbnail(); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-8 offset-md-1 episode-details">

                                                    <div class="row metadata">
                                                        <div class="episode-tags col">
    <?php echo get_the_term_list( $post->ID, 'ptag', '<span class="badge rounded-pill text-bg-primary">', ',</span> <span class="badge rounded-pill text-bg-primary">', '</span>' );
        ?></span>
                                                        </div>
                                                        <div class="episode-date col text-end"> <?php the_time('d M, Y') ?></div>
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
                                                    	Read more
                                                    </a> <a  href="<?php the_permalink() ?>#comments"  class="btn-secondary btn"><span class="fa fa-comment"></span> <?php printf( _n( '%d comment', '%d comments', get_comments_number(), 'learncardano' ), get_comments_number() ); ?></a>

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
