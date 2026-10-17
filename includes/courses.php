<?php
/**
 * Courses without LearnDash: the course structure, quiz data and admin
 * helpers behind archive-sfwd-courses.php, single-sfwd-*.php and
 * template-parts/course-{card,step}.php.
 *
 * The four LearnDash post types (sfwd-courses, sfwd-lessons, sfwd-topic,
 * sfwd-quiz) keep their keys, IDs and URLs and are registered from acf-json/
 * once LearnDash is off. A course lists its lessons and optional final quiz,
 * a lesson its topics and quiz, a topic its quiz (ACF fields). Progress and
 * quiz scores live only in the visitor's browser (assets/js/courses.js).
 * Nothing here calls LearnDash, so it works with the plugin on or off.
 * Styles live in assets/css/courses.css under `lcc__`.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * The course post types and the short name each step type goes by.
 *
 * @return array<string, string>
 */
function lc_course_types() {
	return array(
		'sfwd-courses' => 'course',
		'sfwd-lessons' => 'lesson',
		'sfwd-topic'   => 'topic',
		'sfwd-quiz'    => 'quiz',
	);
}

/**
 * Safety net for the course URLs: register any of the four post types that
 * nobody registered by init 20 (LearnDash runs at init 2, ACF's JSON post
 * types at init 5/6), e.g. while ACF is switched off. The args give the same
 * URLs and rewrite rules as acf-json/post_type_lcc_*.json, so about 130
 * indexed course URLs keep working; the ACF JSON stays the real definition.
 */
function lc_course_register_fallback_types() {
	$supports = array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'revisions', 'page-attributes', 'custom-fields' );
	$types    = array(
		'sfwd-courses' => array( 'courses', __( 'Courses', 'learncardano' ), __( 'Course', 'learncardano' ) ),
		'sfwd-lessons' => array( 'lessons', __( 'Lessons', 'learncardano' ), __( 'Lesson', 'learncardano' ) ),
		'sfwd-topic'   => array( 'topic', __( 'Topics', 'learncardano' ), __( 'Topic', 'learncardano' ) ),
		'sfwd-quiz'    => array( 'quizzes', __( 'Quizzes', 'learncardano' ), __( 'Quiz', 'learncardano' ) ),
	);
	foreach ( $types as $type => $def ) {
		if ( post_type_exists( $type ) ) {
			continue;
		}
		$is_course = ( 'sfwd-courses' === $type );
		$args      = array(
			'labels'              => array(
				'name'          => $def[1],
				'singular_name' => $def[2],
			),
			'public'              => true,
			'exclude_from_search' => ( 'sfwd-quiz' === $type ),
			'show_in_rest'        => true,
			'has_archive'         => $is_course,
			// Feeds follow has_archive (courses only), as in the ACF JSON.
			'rewrite'             => array(
				'slug'       => $def[0],
				'with_front' => false,
			),
			'supports'            => 'sfwd-quiz' === $type ? array_values( array_diff( $supports, array( 'comments' ) ) ) : $supports,
			'show_in_menu'        => $is_course ? true : 'edit.php?post_type=sfwd-courses',
			'delete_with_user'    => false,
		);
		if ( $is_course ) {
			$args['menu_position'] = 20;
			$args['menu_icon']     = 'dashicons-welcome-learn-more';
		}
		register_post_type( $type, $args );
	}
}
add_action( 'init', 'lc_course_register_fallback_types', 20 );

/**
 * Whether the current view is the courses archive or a course, lesson,
 * topic or quiz.
 */
function lc_is_course_view() {
	return is_post_type_archive( 'sfwd-courses' ) || is_singular( array_keys( lc_course_types() ) );
}

/**
 * URL of the courses archive (/courses/).
 */
function lc_courses_url() {
	$url = get_post_type_archive_link( 'sfwd-courses' );
	return $url ? $url : home_url( '/courses/' );
}

/**
 * Translated name of a step type ('course', 'lesson', 'topic' or 'quiz').
 */
function lc_course_type_label( $type ) {
	$labels = array(
		'course' => __( 'Course', 'learncardano' ),
		'lesson' => __( 'Lesson', 'learncardano' ),
		'topic'  => __( 'Topic', 'learncardano' ),
		'quiz'   => __( 'Quiz', 'learncardano' ),
	);
	return isset( $labels[ $type ] ) ? $labels[ $type ] : '';
}

/**
 * IDs stored in a relationship field's raw meta (a serialized array of ID
 * strings), in order, without duplicates.
 *
 * @return int[]
 */
function lc_course_meta_ids( $post_id, $key ) {
	$value = get_post_meta( $post_id, $key, true );
	if ( ! is_array( $value ) ) {
		$value = ( '' === $value || null === $value || false === $value ) ? array() : array( $value );
	}
	return array_values( array_unique( array_filter( array_map( 'absint', $value ) ) ) );
}

/**
 * The ID stored in a post object field's raw meta, or 0.
 */
function lc_course_meta_id( $post_id, $key ) {
	$value = get_post_meta( $post_id, $key, true );
	if ( is_array( $value ) ) {
		$value = reset( $value );
	}
	return absint( $value );
}

/**
 * Load posts and their meta in one query each so the per-item lookups
 * below hit the cache.
 *
 * @param int[] $ids Post IDs.
 */
function lc_course_prime( $ids ) {
	$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
	if ( $ids ) {
		_prime_post_caches( $ids, false, true );
	}
}

/**
 * The structure of every course (any status but trash/auto-draft) and of
 * every lesson, topic and quiz it lists, read from raw meta. Cached in the
 * `lc_course_map` transient (one-week expiry, cleared early by lc_course_map_flush())
 * and for the rest of the request. Also holds each course's counts under
 * 'counts' (see lc_course_counts()).
 *
 * @return array{courses: array, lessons: array, topics: array, quizzes: array, counts: array}
 */
function lc_course_map() {
	if ( isset( $GLOBALS['lc_course_map'] ) && is_array( $GLOBALS['lc_course_map'] ) ) {
		return $GLOBALS['lc_course_map'];
	}
	$map = get_transient( 'lc_course_map' );
	if ( ! is_array( $map ) || ! isset( $map['courses'], $map['lessons'], $map['topics'], $map['quizzes'], $map['counts'] ) ) {
		$map = lc_course_map_build();
		// An expiry keeps the transient out of autoloaded options, so pages
		// outside the courses don't load it. The flush hooks keep it fresh.
		set_transient( 'lc_course_map', $map, WEEK_IN_SECONDS );
	}
	$GLOBALS['lc_course_map'] = $map;
	return $map;
}

/**
 * Build the course map from the database (see lc_course_map()).
 *
 * @return array
 */
function lc_course_map_build() {
	$map = array(
		'courses' => array(),
		'lessons' => array(),
		'topics'  => array(),
		'quizzes' => array(),
		'counts'  => array(),
	);

	$courses = get_posts(
		array(
			'post_type'              => 'sfwd-courses',
			'post_status'            => 'any',
			'numberposts'            => -1,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'suppress_filters'       => true,
			'update_post_term_cache' => false,
		)
	);
	// Published courses claim their steps before drafts do ("first placement wins").
	usort(
		$courses,
		function ( $a, $b ) {
			return array( 'publish' !== $a->post_status, $a->ID ) <=> array( 'publish' !== $b->post_status, $b->ID );
		}
	);

	$raw_courses = array();
	foreach ( $courses as $course ) {
		$raw_courses[ $course->ID ] = array(
			'lessons'    => lc_course_meta_ids( $course->ID, 'course_lessons' ),
			'final_quiz' => lc_course_meta_id( $course->ID, 'course_final_quiz' ),
		);
	}

	$lesson_ids = array();
	$quiz_ids   = array();
	foreach ( $raw_courses as $raw ) {
		$lesson_ids = array_merge( $lesson_ids, $raw['lessons'] );
		$quiz_ids[] = $raw['final_quiz'];
	}
	lc_course_prime( $lesson_ids );

	$raw_lessons = array();
	$topic_ids   = array();
	foreach ( array_unique( $lesson_ids ) as $lesson_id ) {
		$raw_lessons[ $lesson_id ] = array(
			'topics' => lc_course_meta_ids( $lesson_id, 'lesson_topics' ),
			'quiz'   => lc_course_meta_id( $lesson_id, 'lesson_quiz' ),
		);
		$topic_ids  = array_merge( $topic_ids, $raw_lessons[ $lesson_id ]['topics'] );
		$quiz_ids[] = $raw_lessons[ $lesson_id ]['quiz'];
	}
	lc_course_prime( $topic_ids );

	$raw_topics = array();
	foreach ( array_unique( $topic_ids ) as $topic_id ) {
		$raw_topics[ $topic_id ] = lc_course_meta_id( $topic_id, 'topic_quiz' );
		$quiz_ids[]              = $raw_topics[ $topic_id ];
	}
	lc_course_prime( $quiz_ids );

	// Claim a quiz for its first placement; later placements are ignored.
	$place_quiz = function ( $quiz_id, $course_id, $lesson_id, $topic_id ) use ( &$map ) {
		if ( ! $quiz_id || isset( $map['quizzes'][ $quiz_id ] ) || 'sfwd-quiz' !== get_post_type( $quiz_id ) ) {
			return 0;
		}
		$map['quizzes'][ $quiz_id ] = array(
			'course' => $course_id,
			'lesson' => $lesson_id,
			'topic'  => $topic_id,
		);
		return $quiz_id;
	};

	foreach ( $raw_courses as $course_id => $raw ) {
		$entry = array(
			'lessons'    => array(),
			'final_quiz' => 0,
		);
		foreach ( $raw['lessons'] as $lesson_id ) {
			if ( isset( $map['lessons'][ $lesson_id ] ) || 'sfwd-lessons' !== get_post_type( $lesson_id ) ) {
				continue;
			}
			$entry['lessons'][]           = $lesson_id;
			$map['lessons'][ $lesson_id ] = array(
				'course' => $course_id,
				'topics' => array(),
				'quiz'   => 0,
			);
			foreach ( $raw_lessons[ $lesson_id ]['topics'] as $topic_id ) {
				if ( isset( $map['topics'][ $topic_id ] ) || 'sfwd-topic' !== get_post_type( $topic_id ) ) {
					continue;
				}
				$map['lessons'][ $lesson_id ]['topics'][] = $topic_id;
				$map['topics'][ $topic_id ]               = array(
					'course' => $course_id,
					'lesson' => $lesson_id,
					'quiz'   => $place_quiz( $raw_topics[ $topic_id ], $course_id, $lesson_id, $topic_id ),
				);
			}
			$map['lessons'][ $lesson_id ]['quiz'] = $place_quiz( $raw_lessons[ $lesson_id ]['quiz'], $course_id, $lesson_id, 0 );
		}
		$entry['final_quiz']          = $place_quiz( $raw['final_quiz'], $course_id, 0, 0 );
		$map['courses'][ $course_id ] = $entry;
	}

	foreach ( array_keys( $map['courses'] ) as $course_id ) {
		$sequence = lc_course_build_sequence( $map, $course_id );
		$counts   = array(
			'lessons' => 0,
			'topics'  => 0,
			'quizzes' => 0,
			'steps'   => count( $sequence ),
			'minutes' => 0,
		);
		foreach ( $sequence as $step ) {
			$counts[ 'quiz' === $step['type'] ? 'quizzes' : $step['type'] . 's' ]++;
			$counts['minutes'] += lc_reading_minutes( get_post_field( 'post_content', $step['id'] ) );
		}
		$map['counts'][ $course_id ] = $counts;
	}

	return $map;
}

/**
 * Forget the cached course map. Runs whenever a course step is saved,
 * published, trashed or deleted, or its outline fields change.
 */
function lc_course_map_flush() {
	delete_transient( 'lc_course_map' );
	unset( $GLOBALS['lc_course_map'] );
	$GLOBALS['lc_course_cache'] = array();
}

/**
 * Flush the map when the given post is one of the course post types.
 *
 * @param int|string $post_id Post ID (ACF also passes 'options', 'user_1'…).
 */
function lc_course_map_flush_for( $post_id ) {
	if ( is_numeric( $post_id ) && array_key_exists( (string) get_post_type( (int) $post_id ), lc_course_types() ) ) {
		lc_course_map_flush();
	}
}

foreach ( array_keys( lc_course_types() ) as $lc_course_type ) {
	add_action( "save_post_{$lc_course_type}", 'lc_course_map_flush', 10, 0 );
}
unset( $lc_course_type );
foreach ( array( 'trashed_post', 'untrashed_post' ) as $lc_course_hook ) {
	add_action( $lc_course_hook, 'lc_course_map_flush_for', 10, 1 );
}
unset( $lc_course_hook );
add_action(
	'deleted_post',
	function ( $post_id, $post = null ) {
		// The row is gone by now, so use the post object WordPress passes along.
		if ( $post instanceof WP_Post ? array_key_exists( $post->post_type, lc_course_types() ) : true ) {
			lc_course_map_flush();
		}
	},
	10,
	2
);
add_action( 'acf/save_post', 'lc_course_map_flush_for', 20, 1 );
add_action(
	'transition_post_status',
	function ( $new_status, $old_status, $post ) {
		if ( $new_status !== $old_status && $post instanceof WP_Post && array_key_exists( $post->post_type, lc_course_types() ) ) {
			lc_course_map_flush();
		}
	},
	10,
	3
);
// update_field() (the migration script, WP-CLI) writes meta without firing
// acf/save_post, so watch the outline fields' meta directly as well.
foreach ( array( 'added_post_meta', 'updated_post_meta', 'deleted_post_meta' ) as $lc_course_hook ) {
	add_action(
		$lc_course_hook,
		function ( $meta_id, $object_id, $meta_key ) {
			if ( in_array( $meta_key, array( 'course_lessons', 'course_final_quiz', 'lesson_topics', 'lesson_quiz', 'topic_quiz' ), true ) ) {
				lc_course_map_flush();
			}
		},
		10,
		3
	);
}
unset( $lc_course_hook );

/**
 * Per-request cache shared by the lookups below (emptied on flush).
 */
function &lc_course_cache( $bucket ) {
	if ( ! isset( $GLOBALS['lc_course_cache'] ) || ! is_array( $GLOBALS['lc_course_cache'] ) ) {
		$GLOBALS['lc_course_cache'] = array();
	}
	if ( ! isset( $GLOBALS['lc_course_cache'][ $bucket ] ) ) {
		$GLOBALS['lc_course_cache'][ $bucket ] = array();
	}
	return $GLOBALS['lc_course_cache'][ $bucket ];
}

/**
 * The course a course post, lesson, topic or quiz belongs to, or 0.
 */
function lc_course_for( $post_id ) {
	$post_id = (int) $post_id;
	$types   = lc_course_types();
	$type    = isset( $types[ (string) get_post_type( $post_id ) ] ) ? $types[ get_post_type( $post_id ) ] : '';
	if ( 'course' === $type ) {
		return $post_id;
	}
	$groups = array(
		'lesson' => 'lessons',
		'topic'  => 'topics',
		'quiz'   => 'quizzes',
	);
	if ( ! isset( $groups[ $type ] ) ) {
		return 0;
	}
	$map = lc_course_map();
	return isset( $map[ $groups[ $type ] ][ $post_id ] ) ? (int) $map[ $groups[ $type ] ][ $post_id ]['course'] : 0;
}

/**
 * Whether a step is published.
 */
function lc_course_is_published( $post_id ) {
	return $post_id && 'publish' === get_post_status( $post_id );
}

/**
 * A course's published steps in reading order, from a given map (see
 * lc_course_sequence()). Unpublished items are skipped with their children.
 *
 * @return array<int, array{id: int, type: string, lesson: int, topic: int, depth: int}>
 */
function lc_course_build_sequence( $map, $course_id ) {
	$course_id = (int) $course_id;
	if ( ! isset( $map['courses'][ $course_id ] ) ) {
		return array();
	}
	$course = $map['courses'][ $course_id ];
	$ids    = $course['lessons'];
	foreach ( $course['lessons'] as $lesson_id ) {
		$ids = array_merge( $ids, $map['lessons'][ $lesson_id ]['topics'] );
	}
	lc_course_prime( $ids );

	$steps = array();
	$step  = function ( $id, $type, $lesson, $topic, $depth ) {
		return array(
			'id'     => (int) $id,
			'type'   => $type,
			'lesson' => (int) $lesson,
			'topic'  => (int) $topic,
			'depth'  => $depth,
		);
	};
	foreach ( $course['lessons'] as $lesson_id ) {
		if ( ! lc_course_is_published( $lesson_id ) ) {
			continue;
		}
		$lesson  = $map['lessons'][ $lesson_id ];
		$steps[] = $step( $lesson_id, 'lesson', $lesson_id, 0, 0 );
		foreach ( $lesson['topics'] as $topic_id ) {
			if ( ! lc_course_is_published( $topic_id ) ) {
				continue;
			}
			$steps[]    = $step( $topic_id, 'topic', $lesson_id, $topic_id, 1 );
			$topic_quiz = $map['topics'][ $topic_id ]['quiz'];
			if ( lc_course_is_published( $topic_quiz ) ) {
				$steps[] = $step( $topic_quiz, 'quiz', $lesson_id, $topic_id, 2 );
			}
		}
		if ( lc_course_is_published( $lesson['quiz'] ) ) {
			$steps[] = $step( $lesson['quiz'], 'quiz', $lesson_id, 0, 1 );
		}
	}
	if ( lc_course_is_published( $course['final_quiz'] ) ) {
		$steps[] = $step( $course['final_quiz'], 'quiz', 0, 0, 0 );
	}
	return $steps;
}

/**
 * A course's published steps in reading order: each lesson, then its topics
 * (each followed by its quiz), then the lesson's quiz; the course's final
 * quiz last.
 *
 * @return array<int, array{id: int, type: string, lesson: int, topic: int, depth: int}>
 */
function lc_course_sequence( $course_id ) {
	$course_id = (int) $course_id;
	$cache     = &lc_course_cache( 'sequence' );
	if ( ! isset( $cache[ $course_id ] ) ) {
		$cache[ $course_id ] = lc_course_build_sequence( lc_course_map(), $course_id );
	}
	return $cache[ $course_id ];
}

/**
 * Where a lesson, topic or quiz sits in its course: owning course, lesson
 * and topic, its 0-based position in the sequence (-1 when unpublished),
 * the previous and next steps, and lesson/topic numbering. Null when the
 * post is in no course (or isn't a step).
 *
 * @return array|null
 */
function lc_course_step_context( $post_id ) {
	$post_id = (int) $post_id;
	$cache   = &lc_course_cache( 'context' );
	if ( array_key_exists( $post_id, $cache ) ) {
		return $cache[ $post_id ];
	}

	$types  = lc_course_types();
	$type   = isset( $types[ (string) get_post_type( $post_id ) ] ) ? $types[ get_post_type( $post_id ) ] : '';
	$groups = array(
		'lesson' => 'lessons',
		'topic'  => 'topics',
		'quiz'   => 'quizzes',
	);
	$map    = lc_course_map();
	if ( ! isset( $groups[ $type ], $map[ $groups[ $type ] ][ $post_id ] ) ) {
		$cache[ $post_id ] = null;
		return null;
	}

	$entry  = $map[ $groups[ $type ] ][ $post_id ];
	$course = (int) $entry['course'];
	$lesson = 'lesson' === $type ? $post_id : (int) $entry['lesson'];
	$topic  = 'topic' === $type ? $post_id : ( 'quiz' === $type ? (int) $entry['topic'] : 0 );

	$sequence   = lc_course_sequence( $course );
	$ids        = array_column( $sequence, 'id' );
	$index      = array_search( $post_id, $ids, true );
	$index      = false === $index ? -1 : (int) $index;
	$lesson_ids = array();
	$topic_ids  = array();
	foreach ( $sequence as $step ) {
		if ( 'lesson' === $step['type'] ) {
			$lesson_ids[] = $step['id'];
		} elseif ( 'topic' === $step['type'] && $lesson && $step['lesson'] === $lesson ) {
			$topic_ids[] = $step['id'];
		}
	}
	$lesson_pos = $lesson ? array_search( $lesson, $lesson_ids, true ) : false;
	$topic_pos  = $topic ? array_search( $topic, $topic_ids, true ) : false;

	$cache[ $post_id ] = array(
		'course'        => $course,
		'lesson'        => $lesson,
		'topic'         => $topic,
		'type'          => $type,
		'index'         => $index,
		'total'         => count( $ids ),
		'prev'          => $index > 0 ? $ids[ $index - 1 ] : 0,
		'next'          => ( $index >= 0 && $index < count( $ids ) - 1 ) ? $ids[ $index + 1 ] : 0,
		'lesson_number' => false === $lesson_pos ? 0 : $lesson_pos + 1,
		'lesson_count'  => count( $lesson_ids ),
		'topic_number'  => false === $topic_pos ? 0 : $topic_pos + 1,
		'topic_count'   => count( $topic_ids ),
	);
	return $cache[ $post_id ];
}

/**
 * Published lessons, topics, quizzes and steps in a course, and the total
 * reading time in minutes.
 *
 * @return array{lessons: int, topics: int, quizzes: int, steps: int, minutes: int}
 */
function lc_course_counts( $course_id ) {
	$map = lc_course_map();
	if ( isset( $map['counts'][ (int) $course_id ] ) ) {
		return $map['counts'][ (int) $course_id ];
	}
	return array(
		'lessons' => 0,
		'topics'  => 0,
		'quizzes' => 0,
		'steps'   => 0,
		'minutes' => 0,
	);
}

/**
 * Count phrases for a course or lesson: "10 lessons", "13 topics" and
 * "Quiz" (or "1 quiz" when $quiz_word is false, "2 quizzes" when several).
 * Zero counts other than lessons are left out.
 *
 * @return string[]
 */
function lc_course_count_parts( $counts, $quiz_word = true ) {
	$parts = array();
	if ( ! empty( $counts['lessons'] ) ) {
		/* translators: %s: number of lessons */
		$parts[] = sprintf( _n( '%s lesson', '%s lessons', $counts['lessons'], 'learncardano' ), number_format_i18n( $counts['lessons'] ) );
	}
	if ( ! empty( $counts['topics'] ) ) {
		/* translators: %s: number of topics */
		$parts[] = sprintf( _n( '%s topic', '%s topics', $counts['topics'], 'learncardano' ), number_format_i18n( $counts['topics'] ) );
	}
	if ( ! empty( $counts['quizzes'] ) ) {
		$parts[] = ( $quiz_word && 1 === (int) $counts['quizzes'] )
			? __( 'Quiz', 'learncardano' )
			/* translators: %s: number of quizzes */
			: sprintf( _n( '%s quiz', '%s quizzes', $counts['quizzes'], 'learncardano' ), number_format_i18n( $counts['quizzes'] ) );
	}
	return $parts;
}

/**
 * The published course that replaces a course (its superseded_by field), or 0.
 */
function lc_course_superseded_by( $course_id ) {
	$new_id = lc_course_meta_id( $course_id, 'superseded_by' );
	return ( $new_id && $new_id !== (int) $course_id && 'sfwd-courses' === get_post_type( $new_id ) && lc_course_is_published( $new_id ) ) ? $new_id : 0;
}

/**
 * The featured image to show as a hero above a course page or step, or 0.
 * Same rule as single.php: skip it when the image already opens the
 * content (before the first H2).
 */
function lc_course_hero_id( $content, $post_id = null ) {
	$thumb_id = (int) get_post_thumbnail_id( $post_id );
	if ( ! $thumb_id ) {
		return 0;
	}
	$img_pos = strpos( $content, 'wp-image-' . $thumb_id );
	$h2_pos  = strpos( $content, '<h2' );
	return ( false === $img_pos || $img_pos > ( false === $h2_pos ? 3000 : $h2_pos ) ) ? $thumb_id : 0;
}

/**
 * One outline link: done check, number, title and a short type note.
 */
function lc_course_outline_link( $post_id, $is_current, $num = '', $note = '' ) {
	$html  = '<a class="lcc__link" href="' . esc_url( get_permalink( $post_id ) ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '>';
	$html .= '<span class="lcc__check" aria-hidden="true"></span>';
	if ( '' !== (string) $num ) {
		$html .= '<span class="lcc__num">' . esc_html( $num ) . '</span>';
	}
	$html .= '<span class="lcc__label">' . esc_html( get_the_title( $post_id ) ) . '</span>';
	if ( '' !== $note ) {
		$html .= '<span class="lcc__type">' . esc_html( $note ) . '</span>';
	}
	return $html . '</a>';
}

/**
 * Echo a course outline: lessons in order with their topics and quizzes,
 * then the final quiz. Each step is an `li[data-lcc-step]` whose first child
 * is its link, which assets/js/courses.js marks done and uses to resume.
 *
 * Args: current (post ID being viewed, 0 for none), mode ('full' expands
 * every lesson; 'panel' collapses all but the current lesson in <details>),
 * heading ('h2' or 'h3' adds a "Course outline" heading; '' for none).
 */
function lc_course_render_outline( $course_id, $args = array() ) {
	$args      = wp_parse_args(
		$args,
		array(
			'current' => 0,
			'mode'    => 'full',
			'heading' => '',
		)
	);
	$course_id = (int) $course_id;
	$current   = (int) $args['current'];
	$panel     = ( 'panel' === $args['mode'] );
	$sequence  = lc_course_sequence( $course_id );
	if ( ! $sequence ) {
		return;
	}
	$context        = $current ? lc_course_step_context( $current ) : null;
	$current_lesson = ( $context && $context['course'] === $course_id ) ? $context['lesson'] : 0;

	// Rebuild the tree from the flat sequence: lesson → items → topic quiz.
	$lessons = array();
	$finals  = array();
	foreach ( $sequence as $step ) {
		if ( 'lesson' === $step['type'] ) {
			$lessons[ $step['id'] ] = array();
		} elseif ( 0 === $step['depth'] ) {
			$finals[] = $step;
		} elseif ( 1 === $step['depth'] ) {
			$lessons[ $step['lesson'] ][ $step['id'] ] = array(
				'step'    => $step,
				'quizzes' => array(),
			);
		} elseif ( isset( $lessons[ $step['lesson'] ][ $step['topic'] ] ) ) {
			$lessons[ $step['lesson'] ][ $step['topic'] ]['quizzes'][] = $step;
		}
	}

	$heading = in_array( $args['heading'], array( 'h2', 'h3' ), true ) ? $args['heading'] : '';
	if ( $heading ) {
		printf( '<%1$s class="lcc__outline-title" id="lcc-outline-title-%2$d">%3$s</%1$s>', $heading, $course_id, esc_html__( 'Course outline', 'learncardano' ) );
	}
	printf( '<ol class="lcc__outline lcc__outline--%1$s" data-lcc-outline="%2$d"%3$s>', esc_attr( $panel ? 'panel' : 'full' ), $course_id, $heading ? ' aria-labelledby="lcc-outline-title-' . $course_id . '"' : '' );

	$number = 0;
	foreach ( $lessons as $lesson_id => $items ) {
		$number++;
		$topics  = 0;
		$quizzes = 0;
		foreach ( $items as $item ) {
			if ( 'topic' === $item['step']['type'] ) {
				$topics++;
			} else {
				$quizzes++;
			}
			$quizzes += count( $item['quizzes'] );
		}
		$note    = implode(
			' · ',
			lc_course_count_parts(
				array(
					'topics'  => $topics,
					'quizzes' => $quizzes,
				)
			)
		);
		$classes = array( 'lcc__item', 'lcc__item--lesson' );
		if ( $lesson_id === $current ) {
			$classes[] = 'is-current';
		} elseif ( $lesson_id === $current_lesson ) {
			$classes[] = 'has-current';
		}
		if ( $items ) {
			$classes[] = 'has-children';
		}

		echo '<li class="' . esc_attr( implode( ' ', $classes ) ) . '" data-lcc-step="' . esc_attr( $lesson_id ) . '">';
		echo lc_course_outline_link( $lesson_id, $lesson_id === $current, (string) $number, $note ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in lc_course_outline_link().

		if ( $items ) {
			$sub = '<ol class="lcc__sub">';
			foreach ( $items as $item_id => $item ) {
				$step        = $item['step'];
				$is_topic    = ( 'topic' === $step['type'] );
				$sub_classes = array( 'lcc__item', 'lcc__item--' . $step['type'] );
				if ( $item_id === $current ) {
					$sub_classes[] = 'is-current';
				}
				if ( $item['quizzes'] ) {
					$sub_classes[] = 'has-children';
				}
				$sub .= '<li class="' . esc_attr( implode( ' ', $sub_classes ) ) . '" data-lcc-step="' . esc_attr( $item_id ) . '">';
				$sub .= lc_course_outline_link( $item_id, $item_id === $current, '', $is_topic ? '' : __( 'Quiz', 'learncardano' ) );
				if ( $item['quizzes'] ) {
					$sub .= '<ol class="lcc__sub">';
					foreach ( $item['quizzes'] as $quiz ) {
						$sub .= '<li class="lcc__item lcc__item--quiz' . ( $quiz['id'] === $current ? ' is-current' : '' ) . '" data-lcc-step="' . esc_attr( $quiz['id'] ) . '">';
						$sub .= lc_course_outline_link( $quiz['id'], $quiz['id'] === $current, '', __( 'Quiz', 'learncardano' ) );
						$sub .= '</li>';
					}
					$sub .= '</ol>';
				}
				$sub .= '</li>';
			}
			$sub .= '</ol>';

			if ( $panel ) {
				// The summary is drawn as a chevron beside the lesson link (courses.css); its text is for screen readers.
				$open = ( $lesson_id === $current_lesson );
				/* translators: %s: lesson title */
				$toggle = sprintf( __( 'Steps in %s', 'learncardano' ), wp_strip_all_tags( get_the_title( $lesson_id ) ) );
				echo '<details class="lcc__more"' . ( $open ? ' open' : '' ) . '><summary><span class="lcc__more-text">' . esc_html( $toggle ) . '</span></summary>' . $sub . '</details>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
			} else {
				echo $sub; // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts above.
			}
		}
		echo '</li>';
	}

	foreach ( $finals as $quiz ) {
		echo '<li class="lcc__item lcc__item--quiz lcc__item--final' . ( $quiz['id'] === $current ? ' is-current' : '' ) . '" data-lcc-step="' . esc_attr( $quiz['id'] ) . '">';
		echo lc_course_outline_link( $quiz['id'], $quiz['id'] === $current, '', __( 'Final quiz', 'learncardano' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in lc_course_outline_link().
		echo '</li>';
	}

	echo '</ol>';
}

/**
 * Plain text of an answer: tags dropped, entities decoded (the quiz app
 * inserts it with textContent).
 */
function lc_quiz_plain( $text ) {
	return trim( html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
}

/**
 * A quiz's settings, questions and result messages for the quiz app
 * (JSON in the page). Questions read through ACF; without ACF the list is
 * empty. Questions with no text, no answers or no correct answer are left
 * out because nobody could get them right.
 *
 * @return array{id: int, passMark: int, randomise: bool, questions: array, results: array}
 */
function lc_quiz_data( $quiz_id ) {
	$quiz_id = (int) $quiz_id;
	$data    = array(
		'id'        => $quiz_id,
		'passMark'  => 80,
		'randomise' => false,
		'questions' => array(),
		'results'   => array(),
	);
	if ( ! function_exists( 'get_field' ) ) {
		return $data;
	}

	$pass_mark = get_field( 'quiz_pass_mark', $quiz_id );
	if ( is_numeric( $pass_mark ) ) {
		$data['passMark'] = max( 0, min( 100, (int) round( (float) $pass_mark ) ) );
	}
	$data['randomise'] = (bool) get_field( 'quiz_randomise', $quiz_id );

	$rows = get_field( 'quiz_questions', $quiz_id );
	foreach ( is_array( $rows ) ? $rows : array() as $row ) {
		$html = trim( wp_kses_post( isset( $row['question'] ) ? (string) $row['question'] : '' ) );
		// The app puts the question in a <legend>, where <p> isn't allowed.
		if ( preg_match( '#^<p>(.*)</p>$#s', $html, $match ) && false === stripos( $match[1], '<p' ) ) {
			$html = trim( $match[1] );
		}
		$answers = array();
		$correct = 0;
		foreach ( ( isset( $row['answers'] ) && is_array( $row['answers'] ) ) ? $row['answers'] : array() as $answer ) {
			$text = lc_quiz_plain( isset( $answer['answer_text'] ) ? $answer['answer_text'] : '' );
			if ( '' === $text ) {
				continue;
			}
			$is_correct = ! empty( $answer['answer_correct'] );
			$correct   += $is_correct ? 1 : 0;
			$answers[]  = array(
				'text'    => $text,
				'correct' => $is_correct,
			);
		}
		if ( '' === trim( wp_strip_all_tags( $html ) ) || ! $answers || ! $correct ) {
			continue;
		}
		$type                = ( isset( $row['question_type'] ) && 'multiple' === $row['question_type'] ) ? 'multiple' : 'single';
		$data['questions'][] = array(
			'html'             => $html,
			// Radio buttons can't pick two answers, so a "single" question with several correct ones becomes multiple.
			'type'             => $correct > 1 ? 'multiple' : $type,
			'answers'          => $answers,
			'explainCorrect'   => trim( wp_kses_post( isset( $row['explanation_correct'] ) ? (string) $row['explanation_correct'] : '' ) ),
			'explainIncorrect' => trim( wp_kses_post( isset( $row['explanation_incorrect'] ) ? (string) $row['explanation_incorrect'] : '' ) ),
		);
	}

	$results = get_field( 'quiz_results', $quiz_id );
	foreach ( is_array( $results ) ? $results : array() as $row ) {
		$html = trim( wp_kses_post( isset( $row['result_message'] ) ? (string) $row['result_message'] : '' ) );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			continue;
		}
		$data['results'][] = array(
			'min'  => max( 0, min( 100, (int) ( isset( $row['result_min'] ) ? $row['result_min'] : 0 ) ) ),
			'html' => $html,
		);
	}
	usort(
		$data['results'],
		function ( $a, $b ) {
			return $a['min'] <=> $b['min'];
		}
	);

	return $data;
}

/**
 * Share links for a course page (same markup as single.php).
 */
function lc_course_render_share( $post_id ) {
	$url  = rawurlencode( get_permalink( $post_id ) );
	$text = rawurlencode( html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ) );
	?>
	<div class="lca__share">
		<span class="lca__share-label"><?php esc_html_e( 'Share', 'learncardano' ); ?></span>
		<a href="https://twitter.com/intent/tweet?url=<?php echo esc_attr( $url ); ?>&amp;text=<?php echo esc_attr( $text ); ?>" target="_blank" rel="noopener">X</a>
		<a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo esc_attr( $url ); ?>" target="_blank" rel="noopener">LinkedIn</a>
		<a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo esc_attr( $url ); ?>" target="_blank" rel="noopener">Facebook</a>
		<a href="https://t.me/share/url?url=<?php echo esc_attr( $url ); ?>&amp;text=<?php echo esc_attr( $text ); ?>" target="_blank" rel="noopener">Telegram</a>
		<button type="button" class="lca__copy" data-url="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php esc_html_e( 'Copy link', 'learncardano' ); ?></button>
	</div>
	<script>
		document.querySelectorAll('.lca__copy').forEach(function (button) {
			button.addEventListener('click', function () {
				var label = button.textContent;
				navigator.clipboard.writeText(button.dataset.url).then(function () {
					button.textContent = '<?php echo esc_js( __( 'Link copied', 'learncardano' ) ); ?>';
					setTimeout(function () { button.textContent = label; }, 2000);
				});
			});
		});
	</script>
	<?php
}

/**
 * Course styles and the progress/quiz script, on course views only.
 */
function lc_courses_enqueue() {
	if ( is_admin() || ! lc_is_course_view() ) {
		return;
	}
	$dir = get_stylesheet_directory();
	$uri = get_stylesheet_directory_uri();
	$css = '/assets/css/courses.css';
	$js  = '/assets/js/courses.js';

	wp_enqueue_style( 'lc-courses', $uri . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );
	wp_enqueue_script( 'lc-courses', $uri . $js, array(), file_exists( $dir . $js ) ? (string) filemtime( $dir . $js ) : null, true );
	wp_script_add_data( 'lc-courses', 'strategy', 'defer' );
	wp_localize_script(
		'lc-courses',
		'lcCourses',
		array(
			'strings' => array(
				'start'         => __( 'Start course', 'learncardano' ),
				'continue'      => __( 'Continue course', 'learncardano' ),
				'review'        => __( 'Review course', 'learncardano' ),
				'markDone'      => __( 'Mark as done', 'learncardano' ),
				'done'          => __( 'Done', 'learncardano' ),
				/* translators: 1: steps done, 2: total steps */
				'stepsDone'     => __( '%1$s of %2$s steps done', 'learncardano' ),
				'startQuiz'     => __( 'Start quiz', 'learncardano' ),
				/* translators: 1: question number, 2: number of questions */
				'questionOf'    => __( 'Question %1$s of %2$s', 'learncardano' ),
				'check'         => __( 'Check answer', 'learncardano' ),
				'next'          => __( 'Next question', 'learncardano' ),
				'seeResults'    => __( 'See results', 'learncardano' ),
				'correct'       => __( 'Correct!', 'learncardano' ),
				'incorrect'     => __( 'Not quite.', 'learncardano' ),
				'selectOne'     => __( 'Select one answer', 'learncardano' ),
				'selectAll'     => __( 'Select all that apply', 'learncardano' ),
				'chooseAnswer'  => __( 'Choose an answer first', 'learncardano' ),
				/* translators: %s: score percentage (%% is a literal percent sign) */
				'yourScore'     => __( 'You scored %s%%', 'learncardano' ),
				'passed'        => __( 'You passed!', 'learncardano' ),
				/* translators: %s: pass mark percentage (%% is a literal percent sign) */
				'notPassed'     => __( 'Not quite. You need %s%% to pass.', 'learncardano' ),
				'retry'         => __( 'Try again', 'learncardano' ),
				/* translators: %s: best score percentage (%% is a literal percent sign) */
				'bestScore'     => __( 'Your best: %s%%', 'learncardano' ),
				/* translators: %s: pass mark percentage (%% is a literal percent sign) */
				'passMark'      => __( 'Pass mark %s%%', 'learncardano' ),
				/* translators: %s: number of questions */
				'questions'     => __( '%s questions', 'learncardano' ),
				'reviewAnswers' => __( 'Review your answers', 'learncardano' ),
				'yourAnswer'    => __( 'Your answer', 'learncardano' ),
				'correctAnswer' => __( 'Correct answer', 'learncardano' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'lc_courses_enqueue' );

/**
 * SiteGround Speed Optimizer removes ?ver= from asset URLs and serves CSS from
 * a minified copy with a fixed name, both cached for a year, so a changed
 * courses.css or courses.js would not reach browsers. Keep the filemtime
 * version on the course assets and leave them out of SG's minifier.
 */
add_filter(
	'sgo_rqs_exclude',
	function ( $list ) {
		$list[] = 'assets/(css|js)/courses\.(css|js)';
		return $list;
	}
);
foreach ( array( 'sgo_css_minify_exclude', 'sgo_js_minify_exclude' ) as $lc_sgo_filter ) {
	add_filter(
		$lc_sgo_filter,
		function ( $list ) {
			$list[] = 'lc-courses';
			return $list;
		}
	);
}

/**
 * Only courses have feeds (as under LearnDash), so don't advertise comment
 * feeds for lessons, topics and quizzes: those URLs don't exist.
 */
add_filter(
	'feed_links_extra_show_post_comments_feed',
	function ( $show ) {
		return is_singular( array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ) ) ? false : $show;
	}
);

/**
 * No ?p=ID shortlink on course views: for these post types Rank Math's
 * 404-to-homepage fallback answers ?p=ID before WordPress can redirect it,
 * so the shortlink in the head and Link header would lead to the homepage.
 */
add_action(
	'wp',
	function () {
		if ( is_singular( array_keys( lc_course_types() ) ) ) {
			remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
			remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		}
	}
);

/**
 * Body classes: `lcc-view` on every course view, `lcc-step` on lessons,
 * topics and quizzes.
 */
function lc_courses_body_class( $classes ) {
	if ( lc_is_course_view() ) {
		$classes[] = 'lcc-view';
		if ( is_singular( array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ) ) ) {
			$classes[] = 'lcc-step';
		}
	}
	return $classes;
}
add_filter( 'body_class', 'lc_courses_body_class' );

/**
 * Admin: links to where a step sits (course › lesson › topic), as HTML.
 * '' when it is in no course.
 */
function lc_course_admin_trail( $post_id ) {
	$context = lc_course_step_context( $post_id );
	if ( ! $context ) {
		return '';
	}
	$ids = array( $context['course'] );
	if ( $context['lesson'] && $context['lesson'] !== (int) $post_id ) {
		$ids[] = $context['lesson'];
	}
	if ( $context['topic'] && $context['topic'] !== (int) $post_id ) {
		$ids[] = $context['topic'];
	}
	$links = array();
	foreach ( $ids as $id ) {
		$title  = get_the_title( $id );
		$title  = '' === $title ? __( '(no title)', 'learncardano' ) : $title;
		$status = get_post_status( $id );
		$suffix = ( 'publish' !== $status && get_post_status_object( $status ) ) ? ' (' . get_post_status_object( $status )->label . ')' : '';
		$edit   = get_edit_post_link( $id );
		$links[] = $edit
			? '<a href="' . esc_url( $edit ) . '">' . esc_html( $title ) . '</a>' . esc_html( $suffix )
			: esc_html( $title . $suffix );
	}
	return implode( ' &rsaquo; ', $links );
}

/**
 * Admin: "Course" column on the lessons, topics and quizzes lists.
 */
function lc_course_admin_columns( $columns ) {
	$out = array();
	foreach ( $columns as $key => $label ) {
		$out[ $key ] = $label;
		if ( 'title' === $key ) {
			$out['lc_course'] = __( 'Course', 'learncardano' );
		}
	}
	if ( ! isset( $out['lc_course'] ) ) {
		$out['lc_course'] = __( 'Course', 'learncardano' );
	}
	return $out;
}

/**
 * Admin: the course title, linked to its edit screen, or a dash.
 */
function lc_course_admin_column( $column, $post_id ) {
	if ( 'lc_course' !== $column ) {
		return;
	}
	$course_id = lc_course_for( $post_id );
	if ( ! $course_id ) {
		echo '&mdash;';
		return;
	}
	$title = get_the_title( $course_id );
	$title = '' === $title ? __( '(no title)', 'learncardano' ) : $title;
	$edit  = get_edit_post_link( $course_id );
	echo $edit ? '<a href="' . esc_url( $edit ) . '">' . esc_html( $title ) . '</a>' : esc_html( $title );
}

foreach ( array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ) as $lc_course_type ) {
	add_filter( "manage_{$lc_course_type}_posts_columns", 'lc_course_admin_columns' );
	add_action( "manage_{$lc_course_type}_posts_custom_column", 'lc_course_admin_column', 10, 2 );
}
unset( $lc_course_type );

/**
 * Admin: "Part of" box on lesson, topic and quiz edit screens.
 */
function lc_course_add_part_of_box() {
	add_meta_box( 'lc-course-part-of', __( 'Part of', 'learncardano' ), 'lc_course_part_of_box', array( 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz' ), 'side', 'high' );
}
add_action( 'add_meta_boxes', 'lc_course_add_part_of_box' );

/**
 * Render the "Part of" box.
 *
 * @param WP_Post $post The post being edited.
 */
function lc_course_part_of_box( $post ) {
	$trail = lc_course_admin_trail( $post->ID );
	if ( '' === $trail ) {
		echo '<p>' . esc_html__( 'Not part of any course.', 'learncardano' ) . '</p>';
	} else {
		echo '<p>' . $trail . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in lc_course_admin_trail().
	}
	$hints = array(
		'sfwd-lessons' => __( "Reorder on the course's Course outline field.", 'learncardano' ),
		'sfwd-topic'   => __( "Reorder on the lesson's Lesson outline field.", 'learncardano' ),
		'sfwd-quiz'    => __( 'Placed by the quiz field on its course, lesson or topic.', 'learncardano' ),
	);
	if ( isset( $hints[ $post->post_type ] ) ) {
		echo '<p class="description">' . esc_html( $hints[ $post->post_type ] ) . '</p>';
	}
}
