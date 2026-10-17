<?php

/**
 * Courses archive (/courses/).
 *
 * Same look as the Resources and podcast archives: the shared hero, then one
 * large card per course (template-parts/course-card.php) with its lesson
 * counts and, once a visitor has started it, their progress from this
 * browser (filled in by assets/js/courses.js). Styles: style.css `pa__`
 * plus assets/css/courses.css `lcc__`.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();
?>

<main class="resources-archive courses-archive <?php echo esc_attr( cardanopress_bootstrap_class( 'content-full' ) ); ?>">

	<?php
	get_template_part(
		'template-parts/archive-hero',
		null,
		array(
			'title'       => __( 'Learn Cardano Courses', 'learncardano' ),
			'subtitle'    => __( 'Free, step-by-step courses on Cardano wallets, DEXs and DeFi. No sign-up needed: your progress is saved in this browser.', 'learncardano' ),
			'show_search' => false,
		)
	);
	?>

	<div class="pa__wrap">
		<?php if ( have_posts() ) : ?>
			<div class="pa__list">
				<?php
				$lc_index = 0;
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/course-card', null, array( 'index' => $lc_index ) );
					$lc_index++;
				endwhile;
				?>
			</div>

			<?php
			the_posts_pagination(
				array(
					'mid_size'           => 1,
					'end_size'           => 1,
					'prev_text'          => '&larr; Prev',
					'next_text'          => 'Next &rarr;',
					'screen_reader_text' => __( 'Courses navigation', 'learncardano' ),
				)
			);
			?>

		<?php else : ?>
			<p class="pa__empty"><?php esc_html_e( 'No courses yet.', 'learncardano' ); ?></p>
		<?php endif; ?>
	</div>

</main><!-- .courses-archive -->

<?php

get_footer();
