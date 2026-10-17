<?php

/**
 * Single course: the article layout (single.php, `lca__`) with the course
 * intro, a progress box whose button starts or resumes the course, and the
 * full outline of lessons, topics and quizzes. Progress comes from this
 * browser only (assets/js/courses.js); course data from includes/courses.php.
 * No date-ordered previous/next or related posts: they would jump between
 * unrelated content.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

while ( have_posts() ) :
	the_post();

	$lcc_id         = get_the_ID();
	$lcc_content    = str_replace( ']]>', ']]&gt;', apply_filters( 'the_content', get_the_content() ) );
	$lcc_sequence   = lc_course_sequence( $lcc_id );
	$lcc_steps      = wp_list_pluck( $lcc_sequence, 'id' );
	$lcc_counts     = lc_course_counts( $lcc_id );
	$lcc_meta       = lc_course_count_parts( $lcc_counts, false );
	$lcc_superseded = lc_course_superseded_by( $lcc_id );
	// Most course intros open with their cover image, so the hero follows
	// single.php's rule rather than doubling it.
	$lcc_hero_id = lc_course_hero_id( $lcc_content );
	if ( $lcc_counts['minutes'] ) {
		/* translators: %s: total reading time in minutes */
		$lcc_meta[] = sprintf( __( '~%s min', 'learncardano' ), number_format_i18n( $lcc_counts['minutes'] ) );
	}
	?>

	<main class="lca lcc-course">
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'lca__article' ); ?>>

			<header class="lca__header">
				<a class="lca__kicker" href="<?php echo esc_url( lc_courses_url() ); ?>"><?php esc_html_e( 'Course', 'learncardano' ); ?></a>
				<h1 class="lca__title entry-title"><?php the_title(); ?></h1>
				<?php if ( $lcc_meta ) : ?>
					<div class="lca__byline">
						<?php foreach ( $lcc_meta as $lcc_i => $lcc_part ) : ?>
							<?php if ( $lcc_i ) : ?>
								<span class="lca__dot" aria-hidden="true">&middot;</span>
							<?php endif; ?>
							<span><?php echo esc_html( $lcc_part ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<?php if ( $lcc_superseded ) : ?>
					<p class="lcc__notice">
						<?php esc_html_e( 'There is a newer version of this course:', 'learncardano' ); ?>
						<a href="<?php echo esc_url( get_permalink( $lcc_superseded ) ); ?>"><?php echo esc_html( get_the_title( $lcc_superseded ) ); ?> <span aria-hidden="true">&rarr;</span></a>
					</p>
				<?php endif; ?>
			</header>

			<?php if ( $lcc_hero_id ) : ?>
				<figure class="lca__hero">
					<?php
					echo wp_get_attachment_image(
						$lcc_hero_id,
						'large',
						false,
						array(
							'class'         => 'lca__hero-img',
							'loading'       => 'eager',
							'fetchpriority' => 'high',
							'sizes'         => '(max-width: 1080px) 100vw, 1080px',
						)
					);
					?>
				</figure>
			<?php endif; ?>

			<div class="lca__layout">
				<div class="lca__main">
					<div class="lca__content entry-content">
						<?php echo $lcc_content; // phpcs:ignore WordPress.Security.EscapeOutput -- filtered post content. ?>
					</div>

					<?php if ( $lcc_sequence ) : ?>
						<div class="lcc__progress" data-lcc-progress data-lcc-steps="<?php echo esc_attr( implode( ',', $lcc_steps ) ); ?>">
							<div class="lcc__progress-info">
								<p class="lcc__progress-text">
									<?php
									/* translators: 1: steps done, 2: total steps */
									printf( esc_html__( '%1$s of %2$s steps done', 'learncardano' ), '0', esc_html( number_format_i18n( count( $lcc_steps ) ) ) );
									?>
								</p>
								<div class="lcc__progress-bar" aria-hidden="true"><span></span></div>
								<p class="lcc__progress-note"><?php esc_html_e( 'Free, no sign-up. Your progress is saved in this browser.', 'learncardano' ); ?></p>
							</div>
							<a class="lcc__start pa__btn" data-lcc-continue href="<?php echo esc_url( get_permalink( $lcc_sequence[0]['id'] ) ); ?>"><?php esc_html_e( 'Start course', 'learncardano' ); ?></a>
						</div>

						<section class="lcc__outline-wrap">
							<?php
							lc_course_render_outline(
								$lcc_id,
								array(
									'mode'    => 'full',
									'heading' => 'h2',
								)
							);
							?>
						</section>
					<?php else : ?>
						<p class="lcc__notice"><?php esc_html_e( 'The lessons for this course are on their way. Browse the other courses in the meantime.', 'learncardano' ); ?> <a href="<?php echo esc_url( lc_courses_url() ); ?>"><?php esc_html_e( 'All courses', 'learncardano' ); ?> <span aria-hidden="true">&rarr;</span></a></p>
					<?php endif; ?>

					<footer class="lca__footer">
						<?php lc_course_render_share( $lcc_id ); ?>
					</footer>
				</div>
			</div>
		</article><!-- #post-<?php the_ID(); ?> -->

		<?php if ( comments_open() || get_comments_number() ) : ?>
			<div class="lca__wide">
				<div class="lca__comments">
					<?php comments_template(); ?>
				</div>
			</div>
		<?php endif; ?>
	</main><!-- .lca -->

	<?php
endwhile;

get_footer();
