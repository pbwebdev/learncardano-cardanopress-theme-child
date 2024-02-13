<?php

/**
 * The template for displaying pages
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

?>

<style>

</style>

<div class="archive-header pt-5">
    <div class="container">
        <div class="row justify-content-md-center">
            <div class="col-md-8 text-center">
                <h1>Learn Cardano Courses</h1>
                <p>If you ever wanted to learn about Cardano and it's decentralised finance ecosystem, <br>this is the place to do it.</p>
            </div>
        </div>
    </div>
</div>

    <main class="podcast-category content pt-5 w-100">

        <div class="latest section">
            <div class="container">

                <div class="episode-list ">

                    <div class="row  justify-content-md-center">
                        <div class="col-12">

                        <div class="row">

                            <?php if ( have_posts() ) : ?>
                                <?php while ( have_posts() ) : the_post(); ?>
                                    <article id="<?php echo $post_type . '-' . get_the_ID(); ?>" class="col-12 col-md-4">

                                        <div class="episode-card">

                                            <div class="row">
                                                <div class="col-12 episode-image align-self-start">
                                                    <?php if ( has_post_thumbnail() ) : ?>
                                                        <?php the_post_thumbnail(); ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-12 episode-details">

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
        </div>

    </main><!-- .content -->

<?php

get_footer();

