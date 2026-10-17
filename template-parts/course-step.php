<?php
/**
 * A lesson, topic or quiz page (single-sfwd-lessons/topic/quiz.php), for the
 * current post in the loop.
 *
 * Laid out like an article (single.php, `lca__`): breadcrumb, "Lesson 3 of
 * 10" kicker, title and byline, then the content beside a sticky "In this
 * course" panel (the course outline, current lesson open). Lessons list
 * their topics; quizzes add the quiz app (assets/js/courses.js reads the
 * JSON beside it). Lessons and topics get a "Mark as done" button; quizzes
 * count as done once passed. Previous/next follow the course order.
 *
 * A step that is in no current course shows its content with a note
 * pointing to the courses archive, and none of the course chrome.
 *
 * @package CardanoPress Bootstrap
 */

$lcc_id      = get_the_ID();
$lcc_types   = lc_course_types();
$lcc_type    = isset( $lcc_types[ get_post_type() ] ) ? $lcc_types[ get_post_type() ] : 'lesson';
$lcc_context = lc_course_step_context( $lcc_id );
// A step missing from the published sequence (its course, lesson or topic is
// unpublished) counts as outside a course for visitors: its breadcrumb and
// "In:" links would lead to pages they can't open. Editors keep the chrome.
if ( $lcc_context && ( -1 === $lcc_context['index'] || ! lc_course_is_published( $lcc_context['course'] ) ) && ! current_user_can( 'edit_post', $lcc_id ) ) {
	$lcc_context = null;
}
$lcc_course  = $lcc_context ? $lcc_context['course'] : 0;
$lcc_content = str_replace( ']]>', ']]&gt;', apply_filters( 'the_content', get_the_content() ) );
$lcc_hero_id = lc_course_hero_id( $lcc_content );
$lcc_quiz    = ( 'quiz' === $lcc_type ) ? lc_quiz_data( $lcc_id ) : null;

// Kicker: "Lesson 3 of 10", "Topic 2 of 5" or the plain type.
$lcc_kicker = lc_course_type_label( $lcc_type );
if ( $lcc_context && 'lesson' === $lcc_type && $lcc_context['lesson_number'] ) {
	/* translators: 1: lesson number, 2: number of lessons */
	$lcc_kicker = sprintf( __( 'Lesson %1$s of %2$s', 'learncardano' ), number_format_i18n( $lcc_context['lesson_number'] ), number_format_i18n( $lcc_context['lesson_count'] ) );
} elseif ( $lcc_context && 'topic' === $lcc_type && $lcc_context['topic_number'] ) {
	/* translators: 1: topic number, 2: number of topics in the lesson */
	$lcc_kicker = sprintf( __( 'Topic %1$s of %2$s', 'learncardano' ), number_format_i18n( $lcc_context['topic_number'] ), number_format_i18n( $lcc_context['topic_count'] ) );
}

// Breadcrumb: Courses › Course › Lesson › Topic (as they apply) › this step.
$lcc_crumbs = array( array( __( 'Courses', 'learncardano' ), lc_courses_url() ) );
$lcc_parent = 0;
if ( $lcc_context ) {
	$lcc_crumbs[] = array( get_the_title( $lcc_course ), get_permalink( $lcc_course ) );
	$lcc_parent   = $lcc_course;
	if ( 'lesson' !== $lcc_type && $lcc_context['lesson'] ) {
		$lcc_crumbs[] = array( get_the_title( $lcc_context['lesson'] ), get_permalink( $lcc_context['lesson'] ) );
		$lcc_parent   = $lcc_context['lesson'];
	}
	if ( 'quiz' === $lcc_type && $lcc_context['topic'] ) {
		$lcc_crumbs[] = array( get_the_title( $lcc_context['topic'] ), get_permalink( $lcc_context['topic'] ) );
		$lcc_parent   = $lcc_context['topic'];
	}
}

// Byline: questions or reading time, and where the step sits.
$lcc_meta = array();
if ( $lcc_quiz ) {
	if ( $lcc_quiz['questions'] ) {
		/* translators: %s: number of questions */
		$lcc_meta[] = esc_html( sprintf( _n( '%s question', '%s questions', count( $lcc_quiz['questions'] ), 'learncardano' ), number_format_i18n( count( $lcc_quiz['questions'] ) ) ) );
	}
} else {
	$lcc_minutes = lc_reading_minutes( $lcc_content );
	/* translators: %s: minutes */
	$lcc_meta[] = esc_html( sprintf( _n( '%s min read', '%s min read', $lcc_minutes, 'learncardano' ), number_format_i18n( $lcc_minutes ) ) );
}
if ( $lcc_parent && 'lesson' !== $lcc_type ) {
	/* translators: %s: linked title of the lesson, topic or course this step belongs to */
	$lcc_meta[] = sprintf( esc_html__( 'In: %s', 'learncardano' ), '<a href="' . esc_url( get_permalink( $lcc_parent ) ) . '">' . esc_html( get_the_title( $lcc_parent ) ) . '</a>' );
}

// Steps inside this lesson, for the "In this lesson" list.
$lcc_children = array();
if ( $lcc_context && 'lesson' === $lcc_type ) {
	foreach ( lc_course_sequence( $lcc_course ) as $lcc_step ) {
		if ( $lcc_step['lesson'] === $lcc_id && 'lesson' !== $lcc_step['type'] ) {
			$lcc_children[] = $lcc_step;
		}
	}
}

$lcc_orphan_notes = array(
	'lesson' => __( "This lesson isn't part of a current course.", 'learncardano' ),
	'topic'  => __( "This topic isn't part of a current course.", 'learncardano' ),
	'quiz'   => __( "This quiz isn't part of a current course.", 'learncardano' ),
);
?>

<main class="lca lcc-<?php echo esc_attr( $lcc_type ); ?>">
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'lca__article' ); ?>>

		<header class="lca__header">
			<nav class="lcc__crumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'learncardano' ); ?>">
				<ol>
					<?php foreach ( $lcc_crumbs as $lcc_crumb ) : ?>
						<li><a href="<?php echo esc_url( $lcc_crumb[1] ); ?>"><?php echo esc_html( $lcc_crumb[0] ); ?></a></li>
					<?php endforeach; ?>
					<li><span aria-current="page"><?php the_title(); ?></span></li>
				</ol>
			</nav>
			<span class="lca__kicker"><?php echo esc_html( $lcc_kicker ); ?></span>
			<h1 class="lca__title entry-title"><?php the_title(); ?></h1>
			<?php if ( $lcc_meta ) : ?>
				<div class="lca__byline">
					<?php foreach ( $lcc_meta as $lcc_i => $lcc_part ) : ?>
						<?php if ( $lcc_i ) : ?>
							<span class="lca__dot" aria-hidden="true">&middot;</span>
						<?php endif; ?>
						<span><?php echo $lcc_part; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped when built above. ?></span>
					<?php endforeach; ?>
				</div>
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

		<?php // The outline panel comes after the main column in the source so keyboard users reach the lesson first; courses.css puts it above the content below 1200px. ?>
		<div class="lca__layout<?php echo $lcc_context ? ' lca__layout--toc lcc__layout' : ''; ?>">

			<div class="lca__main">
				<?php if ( ! $lcc_context ) : ?>
					<p class="lcc__notice">
						<?php echo esc_html( $lcc_orphan_notes[ $lcc_type ] ); ?>
						<a href="<?php echo esc_url( lc_courses_url() ); ?>"><?php esc_html_e( 'Browse all courses', 'learncardano' ); ?> <span aria-hidden="true">&rarr;</span></a>
					</p>
				<?php endif; ?>

				<div class="lca__content entry-content">
					<?php echo $lcc_content; // phpcs:ignore WordPress.Security.EscapeOutput -- filtered post content. ?>
				</div>

				<?php if ( $lcc_children ) : ?>
					<section class="lcc__lesson-topics" aria-labelledby="lcc-lesson-topics-title">
						<h2 class="lcc__section-title" id="lcc-lesson-topics-title"><?php esc_html_e( 'In this lesson', 'learncardano' ); ?></h2>
						<ol class="lcc__steps">
							<?php
							$lcc_topic_no = 0;
							foreach ( $lcc_children as $lcc_step ) :
								if ( 'topic' === $lcc_step['type'] ) {
									$lcc_topic_no++;
									$lcc_step_minutes = lc_reading_minutes( get_post_field( 'post_content', $lcc_step['id'] ) );
									/* translators: %s: minutes */
									$lcc_note = sprintf( _n( '%s min read', '%s min read', $lcc_step_minutes, 'learncardano' ), number_format_i18n( $lcc_step_minutes ) );
									$lcc_num  = (string) $lcc_topic_no;
								} else {
									$lcc_note = __( 'Quiz', 'learncardano' );
									$lcc_num  = '';
								}
								$lcc_classes = 'lcc__item lcc__item--' . $lcc_step['type'] . ( 2 === $lcc_step['depth'] ? ' lcc__item--nested' : '' );
								?>
								<li class="<?php echo esc_attr( $lcc_classes ); ?>" data-lcc-step="<?php echo esc_attr( $lcc_step['id'] ); ?>"><?php echo lc_course_outline_link( $lcc_step['id'], false, $lcc_num, $lcc_note ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in lc_course_outline_link(). ?></li>
							<?php endforeach; ?>
						</ol>
					</section>
				<?php endif; ?>

				<?php if ( $lcc_quiz && $lcc_quiz['questions'] ) : ?>
					<div class="lcc__quiz" data-lcc-quiz="<?php echo esc_attr( $lcc_id ); ?>">
						<script type="application/json" class="lcc__quiz-data"><?php echo wp_json_encode( $lcc_quiz, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); // phpcs:ignore WordPress.Security.EscapeOutput -- JSON with <, >, &, ' and " hex-escaped. ?></script>
						<div class="lcc__quiz-app"></div>
						<noscript><p class="lcc__quiz-noscript"><?php esc_html_e( 'This quiz runs in your browser: turn on JavaScript to take it.', 'learncardano' ); ?></p></noscript>
					</div>
				<?php elseif ( $lcc_quiz && $lcc_context ) : ?>
					<p class="lcc__notice"><?php esc_html_e( "This quiz doesn't have any questions yet.", 'learncardano' ); ?></p>
				<?php endif; ?>

				<?php if ( $lcc_context && 'quiz' !== $lcc_type ) : ?>
					<div class="lcc__mark-wrap">
						<button type="button" class="lcc__mark" data-lcc-mark="<?php echo esc_attr( $lcc_id ); ?>" aria-pressed="false"><?php esc_html_e( 'Mark as done', 'learncardano' ); ?></button>
						<p class="lcc__mark-note"><?php esc_html_e( 'Your progress is saved in this browser.', 'learncardano' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( $lcc_context ) : ?>
					<nav class="lcc__stepnav" aria-label="<?php esc_attr_e( 'Course steps', 'learncardano' ); ?>">
						<?php if ( $lcc_context['prev'] || $lcc_context['next'] ) : ?>
							<div class="lcc__stepnav-cards">
								<?php
								foreach (
									array(
										'prev' => __( 'Previous', 'learncardano' ),
										'next' => __( 'Next', 'learncardano' ),
									) as $lcc_dir => $lcc_dir_label
								) :
									$lcc_target = $lcc_context[ $lcc_dir ];
									if ( ! $lcc_target ) {
										continue;
									}
									$lcc_target_type = isset( $lcc_types[ get_post_type( $lcc_target ) ] ) ? $lcc_types[ get_post_type( $lcc_target ) ] : '';
									?>
									<a class="lcc__stepnav-<?php echo esc_attr( $lcc_dir ); ?>" href="<?php echo esc_url( get_permalink( $lcc_target ) ); ?>" rel="<?php echo esc_attr( $lcc_dir ); ?>">
										<span class="lcc__stepnav-label">
											<?php if ( 'prev' === $lcc_dir ) : ?>
												<span aria-hidden="true">&larr;</span> <?php echo esc_html( $lcc_dir_label ); ?>
											<?php else : ?>
												<?php echo esc_html( $lcc_dir_label ); ?> <span aria-hidden="true">&rarr;</span>
											<?php endif; ?>
										</span>
										<span class="lcc__stepnav-title"><?php echo esc_html( get_the_title( $lcc_target ) ); ?></span>
										<span class="lcc__stepnav-type"><?php echo esc_html( lc_course_type_label( $lcc_target_type ) ); ?></span>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<a class="lcc__back" href="<?php echo esc_url( get_permalink( $lcc_course ) ); ?>"><span aria-hidden="true">&larr;</span> <?php esc_html_e( 'Back to course', 'learncardano' ); ?></a>
					</nav>
				<?php endif; ?>
			</div>

			<?php if ( $lcc_context ) : ?>
				<aside class="lca__toc lcc__aside" aria-label="<?php esc_attr_e( 'Course outline', 'learncardano' ); ?>">
					<details class="lca__toc-box lcc__panel" open>
						<summary><?php esc_html_e( 'In this course', 'learncardano' ); ?></summary>
						<a class="lcc__panel-course" href="<?php echo esc_url( get_permalink( $lcc_course ) ); ?>"><?php echo esc_html( get_the_title( $lcc_course ) ); ?></a>
						<?php
						lc_course_render_outline(
							$lcc_course,
							array(
								'mode'    => 'panel',
								'current' => $lcc_id,
							)
						);
						?>
					</details>
					<script>
						if (window.matchMedia('(max-width: 1199px)').matches) {
							document.currentScript.previousElementSibling.removeAttribute('open');
						}
					</script>
				</aside>
			<?php endif; ?>
		</div>
	</article><!-- #post-<?php the_ID(); ?> -->

	<?php if ( 'quiz' !== $lcc_type && ( comments_open() || get_comments_number() ) ) : ?>
		<div class="lca__wide">
			<div class="lca__comments">
				<?php comments_template(); ?>
			</div>
		</div>
	<?php endif; ?>
</main><!-- .lca -->
