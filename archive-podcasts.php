<?php

/**
 * Podcast archive — redesigned episode listing.
 *
 * Hero (gradient title + search) followed by a vertical stack of large
 * episode cards (thumbnail + body), then numbered pagination. The site
 * header/footer (nav, About, Support, footer) come from header.php /
 * footer.php, so they're intentionally not repeated here. Styling lives
 * in style.css under the `pa__` namespace; fonts (Chivo/Mulish) and the
 * accent are shared with the Latest Episodes redesign.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();
?>

<main class="podcast-archive <?php echo cardanopress_bootstrap_class( 'content-full' ); ?>">

	<section class="pa__hero">
		<h1 class="pa__h1">Learn Cardano Podcast Episode Archives</h1>
		<p class="pa__sub">View all of the latest episodes produced by the Learn Cardano Podcast.</p>
		<form class="pa__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
			<label class="sr-only" for="pa-search"><?php echo esc_html_x( 'Search episodes', 'label', 'learncardano' ); ?></label>
			<input id="pa-search" class="pa__search-input" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="Search episodes">
			<input type="hidden" name="post_type" value="podcasts">
			<button class="pa__search-btn" type="submit">Search</button>
		</form>
	</section>

	<section class="pa__wrap">
		<?php if ( have_posts() ) : ?>
			<div class="pa__list">
				<?php
				$is_first_page = ( absint( get_query_var( 'paged' ) ) <= 1 );
				$i = 0;
				while ( have_posts() ) :
					the_post();
					$title_attr  = the_title_attribute( array( 'echo' => false ) );
					$thumb       = esc_url( lc_episode_thumb_url( 'large' ) );
					$permalink   = esc_url( get_permalink() );
					$date        = esc_html( get_the_date( 'd M, Y' ) );
					$is_newest   = ( $is_first_page && 0 === $i );
					$comments_no = get_comments_number();
					?>
					<a class="pa__card" href="<?php echo $permalink; ?>" aria-label="<?php echo esc_attr( $title_attr ); ?>">
						<div class="pa__media<?php echo $is_newest ? ' pa__media--newest' : ''; ?>">
							<div class="pa__media-img" style="background-image:url('<?php echo $thumb; ?>')" role="img" aria-label="<?php echo esc_attr( $title_attr ); ?>"></div>
							<?php if ( $is_newest ) : ?>
								<span class="pa__badge">Newest</span>
							<?php endif; ?>
							<span class="pa__play" aria-hidden="true">
								<span class="pa__play-circle"><span class="pa__play-tri"></span></span>
							</span>
						</div>
						<div class="pa__body">
							<div class="pa__date"><?php echo $date; ?></div>
							<h3 class="pa__title"><?php the_title(); ?></h3>
							<p class="pa__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 34 ) ); ?></p>
							<div class="pa__actions">
								<span class="pa__btn">
									<span class="pa__btn-tri" aria-hidden="true"></span>
									Play episode
								</span>
								<span class="pa__comments"><?php printf( esc_html( _n( '%s comment', '%s comments', $comments_no, 'learncardano' ) ), number_format_i18n( $comments_no ) ); ?></span>
							</div>
						</div>
					</a>
					<?php
					$i++;
				endwhile;
				?>
			</div>

			<?php
			the_posts_pagination( array(
				'mid_size'           => 1,
				'end_size'           => 1,
				'prev_text'          => '&larr; Prev',
				'next_text'          => 'Next &rarr;',
				'screen_reader_text' => __( 'Episodes navigation', 'learncardano' ),
			) );
			?>

		<?php else : ?>
			<p class="pa__empty">No episodes found.</p>
		<?php endif; ?>
	</section>

</main><!-- .podcast-archive -->

<?php

get_footer();
