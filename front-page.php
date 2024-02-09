<?php

/**
 * The template for displaying pages
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

?>
    <main class="content pt-5 w-100">
        <div class="section hero-v2 wf-section">
            <div class="container col-10">
                <div class="hero-wrapper">
                    <div class="split-content hero">
                        <div class="hero-description">
                            <h1 class="float-none">Cardano Podcast for You</h1>
                            <p>Keep up to date with core Cardano news, projects, development and ideas coming from the Cardano Community. For beginners to experts wanting to know more about Cardano & blockchain technology.</p>
                        </div>
                        <div class="hero-cta dropdown">
                        <button class="btn btn-primary dropdown-toggle" type="button" id="dropdownMenuButton1" data-bs-toggle="dropdown" aria-expanded="false">
                            Subscribe
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="dropdownMenuButton1">
                            <li>
                                <a class="dropdown-item" href="https://youtube.com/learncardano/" target="_blank">
                                    <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/icon-youtube.svg" alt="YouTube">  YouTube
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="https://open.spotify.com/show/11FpPiHPzivZoSOTgVcfgT" target="_blank">
                                    <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/icon-spotify.svg" alt="Spotify"> Spotify
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="https://podcasts.apple.com/podcast/learn-cardano-podcast/id1560654454" target="_blank">
                                    <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/icon-apple-podcasts.svg" alt="Apple Podcasts"> Apple Podcasts
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="https://podcasts.google.com/feed/aHR0cHM6Ly93d3cuc3ByZWFrZXIuY29tL3Nob3cvNDg1NjIwNy9lcGlzb2Rlcy9mZWVk" target="_blank">
                                    <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/icon-google-podcasts.svg" alt="Google Podcasts"> Google Podcasts
                                </a>
                            </li>
                        </ul>
                        </div>
                        
                    </div>
                </div>
                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/ellipses-background-hero-01.svg" alt="" class="bg-hero-ellipses-01">
            </div>
        </div>
        <div class="latest section">
            <div class="container">
                <div class="row latest-intro justify-content-md-center">
                    <div class="col-md-10">
                        <h2 class="my-1"><span class="fa-solid fa-podcast"></span> Latest Episodes</h2>
                        <div class="row my-3 ">
                            <div class="latest-intro col-md-6">
                                <p class="latest-episodes ">
                                    View all of our latest episodes anywhere you listen to your favour podcasts and on YouTube.
                                </p>
                            </div>
                            <div class="col-md-6 latest-cta text-center">
                                <a class="view-all btn btn-secondary float-md-end" href="/podcasts/">
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
