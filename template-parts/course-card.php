<?php
/**
 * One course card for the courses archive, in the same markup family as
 * template-parts/archive-card.php: image (or the branded placeholder),
 * "Course" label with lesson/topic/quiz counts, title, excerpt and a
 * "Start course" button. data-lcc-steps lists the course's steps so
 * assets/js/courses.js can write "3 of 12 steps done" into
 * .lcc__card-progress for visitors who have started it.
 *
 * Args: index (position in the loop; the first image loads eagerly).
 *
 * @package CardanoPress Bootstrap
 */

$args = wp_parse_args(
	$args,
	array(
		'index' => 0,
	)
);

$lcc_id         = get_the_ID();
$lcc_steps      = wp_list_pluck( lc_course_sequence( $lcc_id ), 'id' );
$lcc_counts     = lc_course_count_parts( lc_course_counts( $lcc_id ) );
$lcc_superseded = lc_course_superseded_by( $lcc_id );
$lcc_thumb_id   = get_post_thumbnail_id();
// The card's accessible name: title, progress (once JS fills it) and the update badge.
$lcc_label_ids = array( 'lcc-card-title-' . $lcc_id, 'lcc-card-progress-' . $lcc_id );
if ( $lcc_superseded ) {
	$lcc_label_ids[] = 'lcc-card-badge-' . $lcc_id;
}
?>
<a class="pa__card lcc__card" href="<?php echo esc_url( get_permalink() ); ?>" aria-labelledby="<?php echo esc_attr( implode( ' ', $lcc_label_ids ) ); ?>" data-lcc-steps="<?php echo esc_attr( implode( ',', $lcc_steps ) ); ?>">
	<div class="pa__media">
		<?php if ( $lcc_thumb_id ) : ?>
			<?php
			// The first card is the mobile LCP element: load it eagerly at high priority.
			echo lc_card_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in lc_card_image().
				array(
					'class' => 'pa__media-img',
					'eager' => ( 0 === $args['index'] ),
				)
			);
			?>
		<?php else : ?>
			<div class="pa__media-img pa__placeholder" aria-hidden="true">
				<span class="pa__placeholder-mark">Learn Crypto</span>
			</div>
		<?php endif; ?>
	</div>
	<div class="pa__body">
		<div class="pa__date">
			<?php esc_html_e( 'Course', 'learncardano' ); ?>
			<?php if ( $lcc_counts ) : ?>
				<span class="pa__cat"><?php echo esc_html( implode( ' · ', $lcc_counts ) ); ?></span>
			<?php endif; ?>
		</div>
		<h2 class="pa__title" id="lcc-card-title-<?php echo esc_attr( $lcc_id ); ?>"><?php the_title(); ?></h2>
		<p class="pa__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 34 ) ); ?></p>
		<div class="pa__actions">
			<span class="pa__btn">
				<?php esc_html_e( 'Start course', 'learncardano' ); ?>
				<span class="pa__btn-arrow" aria-hidden="true">&rarr;</span>
			</span>
			<span class="lcc__card-progress" id="lcc-card-progress-<?php echo esc_attr( $lcc_id ); ?>"></span>
			<?php if ( $lcc_superseded ) : ?>
				<span class="lcc__badge" id="lcc-card-badge-<?php echo esc_attr( $lcc_id ); ?>"><?php esc_html_e( 'Updated course available', 'learncardano' ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</a>
