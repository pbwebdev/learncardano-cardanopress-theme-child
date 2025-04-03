<?php

/**
 * The template for displaying a single dream
 *
 * @package Learn Cardano
 * @since 0.1.0
 */


get_header();



?>
<?php if (get_field('youtube_video')): ?>
    <div id="preloader" style="visibility: hidden; display: none;">
        <img src="https://i.ytimg.com/vi/<?php the_field('youtube_video'); ?>/hqdefault.jpg" height="1" width="1" style="visibility: hidden; display: none;" alt="">
    </div>
<?php endif; ?>

<div class="podcast-episode">
    <main class="content" role="main">

        <?php while (have_posts()) : the_post(); ?>

            <article id="podcast-<?php the_ID(); ?>" class="entry-wrapper">

                <div class="content-header py-3">
                    <div class="container">
                        <div class="row justify-content-md-center">
                            <div class="col-md-10 col-sm-12">
                                <?php if (get_field('youtube_video')): ?>
                                    <div class="youtube-embed col-md-10 mb-2 offset-md-1">
                                        <style>
                                            .embed-container {
                                                position: relative;
                                                padding-bottom: 56.25%;
                                                height: 0;
                                                overflow: hidden;
                                                max-width: 100%;
                                            }

                                            .embed-container iframe,
                                            .embed-container object,
                                            .embed-container embed {
                                                position: absolute;
                                                top: 0;
                                                left: 0;
                                                width: 100%;
                                                height: 100%;
                                            }
                                        </style>
                                        <div class='embed-container'>
                                            <iframe src='https://www.youtube.com/embed/<?php the_field('youtube_video'); ?>' frameborder='0' allowfullscreen></iframe>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <h1 class="entry-title text-center"><?php the_title(); ?></h1>
                                <div class="hero-host">
                                    <div class="hosted-wrapper">
                                        <!-- <img src="--><?php //echo get_theme_root_uri(); 
                                                            ?><!--/learncardano-cardanopress-theme-child/images/peter-bui-55x55.jpg" alt="Peter Bui - Learn Cardano Podcast" class="hosted-picture float-start">-->
                                        <p class="hosted-text">Episode by Peter Bui on <span class="episode-date"> <?php the_time('F jS, Y') ?></span></p>
                                    </div>
                                </div>
                                <?php //the_field('spreaker_code'); 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="content-body py-4">
                    <div class="container">
                        <div class="row justify-content-md-center">
                            <div class="col-lg-8 col-md-12">

                                <?php the_content(); ?>

                                <?php if (get_field('text_transcript')) : ?>
                                    <h2>Text Transcript</h2>
                                    <div id="text_transcript" style="width: 100%; display: block; height: 250px; overflow:auto;">
                                        <?php
                                            cardano_acf_locked_field('text_transcript', [
                                                'title' => 'Transcript is for subscribers only.',
                                            ]);
                                        ?>
                                    </div>
                                <?php endif; ?>

                            </div>

                        </div>
                    </div>
                </div>


                <div class="podcast-comments pt-5 section">
                    <div class="container">
                        <div class="row justify-content-md-center">
                            <div class="col-lg-10 col-md-12">
                                <h2>Comments</h2>
                                <?php

                                // If comments are open or we have at least one comment, load up the comment template.
                                if (comments_open() || get_comments_number()) :
                                    comments_template();
                                endif;

                                the_post_navigation(
                                    array(
                                        'prev_text' => '<span class="screen-reader-text">' . __('Previous Post', 'learncardano') . '</span><span aria-hidden="true" class="nav-subtitle">' . __('Previous', 'learncardano') . '</span> <span class="nav-title"><span class="nav-title-icon-wrapper"> <<< </span>%title</span>',
                                        'next_text' => '<span class="screen-reader-text">' . __('Next Post', 'learncardano') . '</span><span aria-hidden="true" class="nav-subtitle">' . __('Next', 'learncardano') . '</span> <span class="nav-title">%title<span class="nav-title-icon-wrapper"> >>> </span></span>',
                                    )
                                );

                                ?>
                            </div>
                        </div>
                    </div>
                </div>


            </article><!-- #episode-<?php the_ID(); ?> -->

        <?php endwhile; ?>

    </main><!-- .content -->

</div><!-- .content-sidebar.container -->

<?php get_footer();
