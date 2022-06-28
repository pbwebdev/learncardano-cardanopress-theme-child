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
                        <a class="btn btn-primary">Subscribe</a>
                        <a class="btn btn-secondary"><span class="fa-solid fa-play"></span> Latest Episode</a>
                    </div>

                </div>
            </div>
            <div>
                <img src="https://assets.website-files.com/5ec2a2f907dd528c95f3acd3/5ec46897405dcd1076e10506_ellipses-background-hero-01-radio-template.svg" alt="" class="bg-hero-ellipses-01">
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

<div class="about-home">
    <div class="container col-10">
        <div class="row">
            <div class="col align-self-center ">
                <h2>About Learn Cardano Podcast</h2>
                <h3>Hi, I'm Peter, I've been podcasting since 2013</h3>
                <p>After years of trading and learning about crypto, I decided that Cardano was my chain of choice
                and put all my efforts into understanding everything about Cardano. Learn Cardano is where I share
                all that I learn with you.</p>
                <a href="/about/" class="btn btn-primary">Learn more</a>
            </div>
            <div class="col-md-5 offset-md-1 text-center">
                <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/images/peter-bui-250x250.jpg"  alt="Peter Bui Cover" class="about-image" width="250">
            </div>
        </div>
    </div>
</div>



<?php

get_footer();
