<?php

/**
 * Single quiz: course breadcrumb, the quiz intro, then the quiz itself
 * (scored in the browser by assets/js/courses.js; passing marks it done),
 * and previous/next in course order. Quizzes have no comments. Shared
 * layout in template-parts/course-step.php, which adds the quiz block.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

while ( have_posts() ) :
	the_post();
	get_template_part( 'template-parts/course-step' );
endwhile;

get_footer();
