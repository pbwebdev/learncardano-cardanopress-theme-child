<?php

/**
 * The template for displaying pages
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

?>
    <main class="<?php echo cardanopress_bootstrap_class( 'content-full' ); ?>">

        <div class="section hero-v2 wf-section">
            <div class="container col-10">

                <div class="hero-wrapper">

                    <div class="split-content hero">

                        <div class="hero-host">
                            <a href="/about/" class="host-link-container w-inline-block">
                                <div class="hosted-wrapper">
                                    <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/peter-bui-55x55.jpg" alt="Peter Bui - Learn Cardano Podcast" class="hosted-picture float-start">
                                    <div class="hosted-text float-start">Hosted by Peter Bui </div>
                                </div>
                            </a>
                        </div>
                        <div class="hero-description">
                            <h1 class="float-none">Cardano Podcast for the Community</h1>
                            <p>Keep up to date with core Cardano news, projects, development and ideas coming from the Cardano Community. For beginners to experts wanting to know more about Cardano & blockchain technology.</p>
                        </div>
                        <div class="hero-cta">
                            <a class="btn btn-primary">Subscribe</a>
                            <a class="btn btn-secondary"><span class="fa-solid fa-play"></span> Latest Episode</a>
                        </div>

                    </div>
                </div>
                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/ellipses-background-hero-01.svg" alt="" class="bg-hero-ellipses-01">
            </div>

        </div>

        <div class="latest section">
            <div class="container">

                <div class="row latest-intro justify-content-md-center">
                    <div class="col-10">
                        <h2 class="my-1"><span class="fa-solid fa-podcast"></span> Latest Cardano Podcast Episodes</h2>

                        <div class="row my-3 ">
                            <div class="latest-intro col">
                                <p class="latest-episodes ">
                                    View all of our latest episodes anywhere you listen to your favour podcasts and on YouTube.
                                </p>
                            </div>
                            <div class="col latest-cta">
                                <a class="view-all btn btn-secondary float-end">
                                    All Episodes
                                </a>
                            </div>
                        </div>

                    </div>
                </div>




                <div class="episode-list ">

                    <div class="row  justify-content-md-center">
                        <div class="col-md-10">

                            <?php get_template_part( 'template-parts/home', 'podcasts' ); ?>

                        </div>
                    </div>

                </div>


                <div class="episode-cta  text-center">
                    <a href="/podcasts/" class="btn-secondary btn">All episodes</a>
                </div>

            </div>
        </div>

    </main><!-- .content -->

<?php

get_footer();
