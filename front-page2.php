<?php

/**
 * The template for displaying pages
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

?>

    <div class="section hero-v2 wf-section">
        <div class="container col-10">

            <div class="hero-wrapper">

                <div class="split-content hero">

                    <div class="hero-host">
                        <a href="/host" class="host-link-container w-inline-block">
                            <div class="hosted-wrapper">
                                <img src="https://pbs.twimg.com/profile_images/1524504182149234689/jZ-c0Dlq_400x400.jpg" alt="Peter Bui - Learn Cardano Podcast" class="hosted-picture float-start">
                                <div class="hosted-text float-start">Hosted by Peter Bui </div>
                            </div>
                        </a>
                    </div>
                    <div class="hero-description">
                        <h1 class="float-none">Cardano Podcast for the Community</h1>
                        <p>Keep up to date with core Cardano news, projects, development and ideas coming from the Cardano Community. For beginners to experts wanting to know more about Cardano & blockchain technology.</p>
                    </div>
                    <div class="hero-cta">

                            <a class="btn btn-primary dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-expanded="false">
                                Subscribe
                            </a>

                            <ul class="dropdown-menu" aria-labelledby="dropdownMenuLink">
                                <li><span class="fa-brands fa-youtube fa-xl"></span> <a class="dropdown-item" href="https://youtube.com/learncardano/"
                                target="_blank">YouTube</a></li>

                                <li><span class="fa-brands fa-spotify fa-xl"></span> <a class="dropdown-item" href="https://open.spotify.com/show/11FpPiHPzivZoSOTgVcfgT"
                                 target="_blank">Spotify</a></li>

                                <li><span class="fa-brands fa-apple fa-xl"></span> <a class="dropdown-item" href="https://podcasts.apple.com/podcast/learn-cardano-podcast/id1560654454"
                                 target="_blank">Apple Podcasts</a></li>

                                <li><span class="fa-brands fa-google fa-xl"></span> <a class="dropdown-item" href="https://podcasts.google.com/feed/aHR0cHM6Ly93d3cuc3ByZWFrZXIuY29tL3Nob3cvNDg1NjIwNy9lcGlzb2Rlcy9mZWVk"
                                 target="_blank">Google Podcasts</a></li>
                            </ul>

                        <a class="btn btn-secondary" href="/podcasts/"><span class="fa-solid fa-play"></span> Latest Episode</a>
                    </div>

                </div>
            </div>
            <div>
                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/ellipses-background-hero-01.svg" alt="" class="bg-hero-ellipses-01">
            </div>

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




<?php

get_footer();
