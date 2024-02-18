<?php

/**
 * The template for displaying the single item wrapper
 *
 * @package Learn Cardano
 * @since 0.1.0
 */


$tile = get_post_meta( get_the_ID(), 'lc_item_tile', TRUE );

$auth_id  = get_the_author_meta( 'ID' );
$image_id = get_user_meta( $auth_id, 'lc_user_image', TRUE );
$category = get_the_terms( false, ( 'podcasts' === $post_type ? 'pcategory' : 'category' ) );
$tags     = get_the_term_list( false, ( 'podcasts' === $post_type ? 'ptag' : 'post_tag' ) );

?>

<article id="<?php echo $post_type . '-' . get_the_ID(); ?>" class="">

        <div class="episode-card">

            <div class="row">
                <div class="col-md-3 episode-image align-self-start d-none d-xs-none d-sm-none d-md-block">
                    <?php if ( has_post_thumbnail() ) { ?>
                        <?php the_post_thumbnail('medium', ['class' => 'img-responsive responsive--full', 'title' => 'Podcast cover image']);; ?>
                    <?php } else { ?>
                        <img src="<?php echo get_theme_root_uri(); ?>/learncardano-cardanopress-theme-child/assets/images/learn-cardano-podcast-cover.jpg" class="img-responsive responsive--full" title="Podcast cover image">
                    <?php } ?>
                </div>
                <div class="col-md-8 offset-md-1 episode-details">

                    <div class="row metadata">
                        <div class="episode-tags col">
                            <span class="badge rounded-pill text-bg-primary"><?php echo $tags; ?></span>
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
                        <span class="fa fa-play"></span> Play episode
                    </a>

                </div>
            </div>

        </div>

</article>
<!-- #single-<?php the_ID(); ?> -->
