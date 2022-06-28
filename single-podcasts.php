<?php

/**
 * The template for displaying a single dream
 *
 * @package Learn Cardano
 * @since 0.1.0
 */


get_header(); ?>

    <div class="content-sidebar">
        <main class="content" role="main">

            <?php while ( have_posts() ) : the_post(); ?>

                <article id="podcast-<?php the_ID(); ?>" class="entry-wrapper">

                    <div class="content-header py-5">
                        <div class="container">
                            <div class="row">
                                <div class="col-md-4">
                                    <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/learn-cardano-podcast-cover-360x360.jpg">
                                </div>
                                <div class="col-md-8  align-self-center">
                                    <h1 class="entry-title"><?php the_title(); ?></h1>
                                    <div class="hero-host">
                                            <div class="hosted-wrapper">
                                                <img src="https://pbs.twimg.com/profile_images/1524504182149234689/jZ-c0Dlq_400x400.jpg" alt="Peter Bui - Learn Cardano Podcast" class="hosted-picture float-start">
                                                <p class="hosted-text">Hosted by Peter Bui </p>
                                                <p class="episode-date"> <?php the_time('F jS, Y') ?></p>
                                            </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="content-body py-5">
                        <div class="container">
                            <div class="row justify-content-md-center">
                                <div class="col-md-8">
                                    <?php the_content(); ?>
                                </div>
                            </div>
                        </div>
                    </div>



                    <?php
                    $post_id = get_the_ID();
                    $key = ThemePlate()->key;
//                    $ids = array(
//                        'dc_dream_notes',
//                        'dc_dream_feelings',
//                        'dc_dream_consult',
//                        'dc_consultation_author',
//                        'dc_consultation_content'
//                    );


                    ?>


                    <div class="podcast-comments section">
                        <div class="container">
                            <div class="row justify-content-md-center">
                                <div class="col-md-8">
                                    <h3>Comments</h3>
                                    <?php comments_template(); ?>
                                </div>
                            </div>
                        </div>
                    </div>


                </article><!-- #episode-<?php the_ID(); ?> -->

            <?php endwhile; ?>

        </main><!-- .content -->

    </div><!-- .content-sidebar.container -->

    <script async src="https://widget.spreaker.com/widgets.js"></script>

<?php get_footer();
