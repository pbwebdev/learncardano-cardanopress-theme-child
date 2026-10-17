<?php

/**
 * Single topic: course breadcrumb, the topic, a "Mark as done" button and
 * previous/next in course order. Shared layout in
 * template-parts/course-step.php.
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
