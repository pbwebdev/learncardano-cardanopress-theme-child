<?php

/**
 * The template for displaying a single dream
 *
 * @package Learn Cardano
 * @since 0.1.0
 */


get_header();

// Embed IDs go into src attributes: read them raw and only print values
// that look like real IDs (lc_youtube_id() / lc_spotify_id() in
// includes/rest-meta.php also accept a pasted YouTube/Spotify URL).
$lc_youtube_id = function_exists('lc_youtube_id') ? lc_youtube_id(get_field('youtube_video', false, false)) : '';
$lc_spotify_id = function_exists('lc_spotify_id') ? lc_spotify_id(get_field('spotify_id', false, false)) : '';

?>
<div class="podcast-episode">
    <main class="content" role="main">

        <?php while (have_posts()) : the_post(); ?>

            <?php
            /**
             * PodcastEpisode structured data (Schema.org JSON-LD).
             * Built as a PHP array and emitted with wp_json_encode() so all
             * escaping (quotes, newlines, unicode) is handled automatically.
             * audio + duration are intentionally omitted — the podcast has no
             * direct MP3/duration data source (episodes are Spotify/YouTube embeds).
             */
            $lc_schema = array(
                '@context'      => 'https://schema.org',
                '@type'         => 'PodcastEpisode',
                '@id'           => get_permalink() . '#episode',
                'name'          => get_the_title(),
                'url'           => get_permalink(),
                'datePublished' => get_the_date('Y-m-d'),
                'partOfSeries'  => array(
                    '@type' => 'PodcastSeries',
                    'name'  => 'Learn Cardano Podcast',
                    'url'   => home_url('/podcasts/'),
                ),
            );

            // Description: manual excerpt if set, else a trimmed version of the show notes.
            $lc_desc = has_excerpt()
                ? get_the_excerpt()
                : wp_trim_words( wp_strip_all_tags( get_the_content() ), 60, '…' );
            $lc_desc = trim( wp_strip_all_tags( $lc_desc ) );
            if ( $lc_desc !== '' ) {
                $lc_schema['description'] = $lc_desc;
            }

            // Transcript: strip markup to plain text and collapse whitespace.
            // json_encode escapes the quotes/newlines, so the string stays valid.
            $lc_transcript = get_field('text_transcript');
            if ( $lc_transcript ) {
                $lc_transcript = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $lc_transcript ) ) );
                if ( $lc_transcript !== '' ) {
                    $lc_schema['transcript'] = $lc_transcript;
                }
            }

            if ( has_post_thumbnail() ) {
                $lc_schema['image'] = wp_get_attachment_image_url( get_post_thumbnail_id(), 'full' );
            }

            // The episode's YouTube video as a VideoObject (includes/seo.php),
            // linked from the episode, so it can show in Google's video results.
            $lc_graph = array( $lc_schema );
            if ( $lc_youtube_id && function_exists( 'lc_episode_video_schema' ) ) {
                $lc_video = lc_episode_video_schema(
                    get_the_ID(),
                    $lc_youtube_id,
                    isset( $lc_schema['description'] ) ? lc_trim_description( $lc_schema['description'], 300 ) : ''
                );
                $lc_graph[0]['video'] = array( '@id' => $lc_video['@id'] );
                $lc_graph[]           = $lc_video;
            }

            echo '<script type="application/ld+json">'
                . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => array_map( function ( $node ) { unset( $node['@context'] ); return $node; }, $lc_graph ) ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
                . '</script>' . "\n";
            ?>

            <article id="podcast-<?php the_ID(); ?>" class="entry-wrapper">

                <div class="content-header py-3">
                    <div class="container">
                        <div class="row justify-content-md-center">
                            <div class="col-md-10 col-sm-12">
                                <?php if ($lc_youtube_id): ?>
                                    <?php
                                    // YouTube facade: the episode thumbnail and a play button stand in
                                    // for the player, which is ~1MB of third-party script. Clicking
                                    // swaps in the real iframe (autoplaying); without JS the link
                                    // opens the video on YouTube. The thumbnail is the episode's
                                    // featured image (the YouTube thumbnail, imported into the
                                    // media library: same-origin, WebP, responsive), falling back
                                    // to YouTube's own still. functions.php preloads the same image.
                                    $lc_yt_thumb_id = get_post_thumbnail_id();
                                    $lc_yt_thumb    = $lc_yt_thumb_id ? wp_get_attachment_image(
                                        $lc_yt_thumb_id,
                                        'large',
                                        false,
                                        array(
                                            'class'         => 'lc-yt__thumb skip-lazy',
                                            'alt'           => '',
                                            'sizes'         => LC_PODCAST_PLAYER_SIZES,
                                            'loading'       => 'eager',
                                            'fetchpriority' => 'high',
                                            'decoding'      => 'async',
                                        )
                                    ) : '';
                                    if ( ! $lc_yt_thumb ) {
                                        $lc_yt_thumb = '<img class="lc-yt__thumb" src="' . esc_url('https://i.ytimg.com/vi/' . $lc_youtube_id . '/hqdefault.jpg') . '" width="480" height="360" alt="" fetchpriority="high" decoding="async">';
                                    }
                                    ?>
                                    <div class="youtube-embed col-md-10 mb-2 offset-md-1">
                                        <div class="embed-container">
                                            <a class="lc-yt" href="<?php echo esc_url('https://www.youtube.com/watch?v=' . $lc_youtube_id); ?>" data-id="<?php echo esc_attr($lc_youtube_id); ?>" data-title="<?php echo esc_attr(get_the_title()); ?>" target="_blank" rel="noopener">
                                                <?php echo $lc_yt_thumb; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built by wp_get_attachment_image() or escaped above. ?>
                                                <span class="lc-yt__play" aria-hidden="true"><?php echo lc_icon('play'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
                                                <span class="visually-hidden"><?php echo esc_html(sprintf(__('Play video: %s', 'learncardano'), get_the_title())); ?></span>
                                            </a>
                                        </div>
                                    </div>
                                    <script>
                                    (function () {
                                        var link = document.querySelector('.lc-yt');
                                        if (!link) { return; }
                                        var id = link.getAttribute('data-id');
                                        // YouTube fallback still: hqdefault is 480px wide; on larger
                                        // screens swap in the HD still once the page has loaded, if the
                                        // video has one (YouTube serves a 120px placeholder when it doesn't).
                                        var thumb = link.querySelector('img');
                                        if (window.innerWidth >= 768 && thumb && !thumb.srcset) {
                                            window.addEventListener('load', function () {
                                                var hd = new Image();
                                                hd.onload = function () {
                                                    if (hd.naturalWidth > 120) { thumb.src = hd.src; }
                                                };
                                                hd.src = 'https://i.ytimg.com/vi/' + id + '/maxresdefault.jpg';
                                            });
                                        }
                                        link.addEventListener('click', function (e) {
                                            e.preventDefault();
                                            var frame = document.createElement('iframe');
                                            frame.src = 'https://www.youtube.com/embed/' + encodeURIComponent(id) + '?autoplay=1&rel=0';
                                            frame.title = link.getAttribute('data-title');
                                            frame.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
                                            frame.setAttribute('allowfullscreen', '');
                                            frame.setAttribute('frameborder', '0');
                                            link.parentNode.replaceChild(frame, link);
                                            frame.focus();
                                        });
                                    })();
                                    </script>
                                <?php endif; ?>
                                <h1 class="entry-title text-center"><?php the_title(); ?></h1>
                                <div class="hero-host">
                                    <div class="hosted-wrapper">
                                        <!-- <img src="--><?php //echo get_theme_root_uri(); 
                                                            ?><!--/learncardano-cardanopress-theme-child/images/peter-bui-55x55.jpg" alt="Peter Bui - Learn Cardano Podcast" class="hosted-picture float-start">-->
                                        <p class="hosted-text">Episode by Peter Bui on <span class="episode-date"> <?php the_time('F jS, Y') ?></span></p>
                                    </div>
                                </div>

                                <?php if ($lc_spotify_id): ?>
                                    <?php
                                    // Spotify facade: the embed is ~800KB of third-party script, so a
                                    // 152px card of the same size stands in until it's clicked. Without
                                    // JS the card links to the episode on Spotify.
                                    $lc_cover = get_stylesheet_directory_uri() . '/images/learn-cardano-podcast-cover-360x360';
                                    ?>
                                    <a class="lc-spotify" href="<?php echo esc_url('https://open.spotify.com/episode/' . $lc_spotify_id); ?>" data-src="<?php echo esc_url('https://open.spotify.com/embed/episode/' . $lc_spotify_id . '?utm_source=generator&theme=0'); ?>" target="_blank" rel="noopener">
                                        <picture>
                                            <source srcset="<?php echo esc_url($lc_cover . '.webp'); ?>" type="image/webp">
                                            <img class="lc-spotify__cover" src="<?php echo esc_url($lc_cover . '.jpg'); ?>" width="120" height="120" alt="" loading="lazy" decoding="async">
                                        </picture>
                                        <span class="lc-spotify__text">
                                            <span class="lc-spotify__title"><?php the_title(); ?></span>
                                            <span class="lc-spotify__show"><?php esc_html_e('Learn Cardano Podcast', 'learncardano'); ?></span>
                                            <span class="lc-spotify__cta">
                                                <img src="<?php echo esc_url(get_stylesheet_directory_uri() . '/images/platforms/spotify.svg'); ?>" width="20" height="20" alt="" loading="lazy" decoding="async">
                                                <?php esc_html_e('Listen on Spotify', 'learncardano'); ?>
                                            </span>
                                        </span>
                                        <span class="lc-spotify__play" aria-hidden="true"><?php echo lc_icon('play'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
                                    </a>
                                    <script>
                                    (function () {
                                        var card = document.querySelector('.lc-spotify');
                                        if (!card) { return; }
                                        card.addEventListener('click', function (e) {
                                            e.preventDefault();
                                            var frame = document.createElement('iframe');
                                            frame.src = card.getAttribute('data-src');
                                            frame.className = 'lc-spotify-frame';
                                            frame.title = <?php echo wp_json_encode(sprintf(__('Spotify player: %s', 'learncardano'), get_the_title())); ?>;
                                            frame.width = '100%';
                                            frame.height = '152';
                                            frame.setAttribute('frameborder', '0');
                                            frame.setAttribute('allow', 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture');
                                            card.parentNode.replaceChild(frame, card);
                                            frame.focus();
                                        });
                                    })();
                                    </script>
                                <?php endif; ?>

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
                                    <div id="text_transcript" style="width: 100%; display: block; height: 450px; overflow:auto;padding:1em;
">
                                        <?php
                                            the_field('text_transcript');
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
