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
                <div class="col-md-3 episode-image align-self-start">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail(); ?>
                    <?php endif; ?>

<!--                    --><?php //if (get_the_post_thumbnail_url()) : ?>
<!--                        <img src="--><?php //echo get_the_post_thumbnail_url( $the_query->ID, array( 488, 488) ); ?><!--"  alt="Podcast Cover" class="episode-image float-start">-->
<!--                    --><?php //else: ?>
<!--                        <img src="--><?php //echo get_theme_root_uri(); ?><!--/learncardano-cardanopress-theme-child/images/learn-cardano-founders-cover-488x488.jpg"  alt="Radio Podcast Cover - Radio Webflow Template" class="episode-image float-start">-->
<!--                    --><?php //endif; ?>
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
                    </a> <span class="btn-secondary btn"><span class="fa fa-comment"></span> <?php printf( _n( '%d comment', '%d comments', get_comments_number(), 'learncardano' ), get_comments_number() ); ?></span>

                </div>
            </div>

        </div>

<!--            <div class="author">--><?php //printf( '<a href="%s">%s %s</a>', get_author_posts_url( get_the_author_meta( 'ID' ) ), wp_get_attachment_image( $image_id, array( 48, 48 ) ), get_the_author() ); ?><!--</div>-->
<!--            <div class="category">--><?php //printf( '<a href="%s">%s</a>', get_term_link( $category[0]->term_id ), $category[0]->name ); ?><!--</div>-->

</article><!-- #single-<?php the_ID(); ?> -->
