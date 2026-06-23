<?php

/**
 * Latest Episodes — home page (Option B: featured hero + 2-up list).
 *
 * Renders the newest podcast as a large featured card, then the next two as
 * compact list cards. On mobile all three collapse to full-width stacked
 * cards (handled in style.css). Markup uses the `lep__` class namespace;
 * all styling lives in the theme stylesheet, no inline styles.
 *
 * @package Learn Cardano
 * @since 0.1.0
 */

$query = new WP_Query( array(
	'post_type'           => 'podcasts',
	'posts_per_page'      => 3,
	'ignore_sticky_posts' => true,
) );

if ( ! $query->have_posts() ) {
	return;
}

// lc_episode_thumb_url() is defined in functions.php (shared with the archive).

$index = 0;
?>

<div class="lep__items">
	<?php
	while ( $query->have_posts() ) :
		$query->the_post();
		$is_featured = ( 0 === $index );
		$title_attr  = the_title_attribute( array( 'echo' => false ) );
		$thumb       = esc_url( lc_episode_thumb_url( $is_featured ? 'large' : 'medium_large' ) );
		$permalink   = esc_url( get_permalink() );
		$date        = esc_html( get_the_date( 'F j, Y' ) );

		if ( $is_featured ) :
			?>
			<a class="lep__featured" href="<?php echo $permalink; ?>" aria-label="<?php echo esc_attr( $title_attr ); ?>">
				<div class="lep__media lep__media--featured">
					<div class="lep__media-img" style="background-image:url('<?php echo $thumb; ?>')" role="img" aria-label="<?php echo esc_attr( $title_attr ); ?>"></div>
					<div class="lep__media-grad" aria-hidden="true"></div>
					<span class="lep__badge">Newest</span>
					<span class="lep__play" aria-hidden="true">
						<span class="lep__play-circle lep__play-circle--lg"><span class="lep__play-tri"></span></span>
					</span>
				</div>
				<div class="lep__body">
					<div class="lep__date"><?php echo $date; ?></div>
					<h3 class="lep__ftitle"><?php the_title(); ?></h3>
					<p class="lep__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 36 ) ); ?></p>
					<span class="lep__btn">
						<span class="lep__btn-tri" aria-hidden="true"></span>
						Play episode
					</span>
				</div>
			</a>
			<?php
		else :
			// First list card opens the wrapping grid.
			if ( 1 === $index ) {
				echo '<div class="lep__list">';
			}
			?>
			<a class="lep__card" href="<?php echo $permalink; ?>" aria-label="<?php echo esc_attr( $title_attr ); ?>">
				<div class="lep__media lep__media--card">
					<div class="lep__media-img" style="background-image:url('<?php echo $thumb; ?>')" role="img" aria-label="<?php echo esc_attr( $title_attr ); ?>"></div>
					<span class="lep__play" aria-hidden="true">
						<span class="lep__play-circle lep__play-circle--sm"><span class="lep__play-tri"></span></span>
					</span>
				</div>
				<div class="lep__card-body">
					<div class="lep__date"><?php echo $date; ?></div>
					<h3 class="lep__card-title"><?php the_title(); ?></h3>
					<span class="lep__card-cta">
						<span class="lep__cta-tri" aria-hidden="true"></span>
						Play episode
					</span>
				</div>
			</a>
			<?php
		endif;

		$index++;
	endwhile;

	// Close the list grid if any list cards were rendered.
	if ( $index > 1 ) {
		echo '</div>';
	}

	wp_reset_postdata();
	?>
</div>
