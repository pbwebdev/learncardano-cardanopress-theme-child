<?php
/**
 * Courses without LearnDash: one-off data migration.
 *
 * - seed:     copies each course outline from LearnDash's ld_course_steps into
 *             the theme's ACF fields, loads the four quizzes from
 *             quiz-data.json and applies a few guarded content fixes. Safe while
 *             LearnDash is still active, and idempotent.
 * - cutover:  run right after LearnDash is deactivated. Drafts empty leftover
 *             steps and LearnDash utility pages, adds Rank Math 301s for them
 *             and for old URLs that bounce to the homepage, retargets two
 *             redirects and parks the LearnDash sidebar widget.
 * - rollback: restores everything a previous `apply` run changed, from its
 *             backup file.
 *
 * Copy this folder (migrate.php, quiz-data.json, expected-tree.json) and the
 * theme's acf-json/group_lcc_*.json to the server, e.g. ~/claude-scripts/courses/,
 * and run it with WP-CLI:
 *
 *   wp eval-file ~/claude-scripts/courses/migrate.php seed [apply] [overwrite] --path=...
 *   wp eval-file ~/claude-scripts/courses/migrate.php cutover [apply] [force-with-ld] --path=...
 *   wp eval-file ~/claude-scripts/courses/migrate.php rollback <backup.json> [apply] --path=...
 *
 * Seed can run before the theme is deployed: when the theme's course field
 * groups aren't loaded, it loads the group_lcc_*.json copies beside this file
 * (ACF local field groups, this run only), so update_field() writes the same
 * meta and field reference rows the theme will read. Once an outline field or
 * quiz is stored, a re-run leaves it alone (admin edits win) unless the bare
 * word `overwrite` is given.
 *
 * Without `apply` it is a dry run: it prints every planned change and writes
 * nothing. With `apply` it first saves a JSON backup of every value it is about
 * to change to ~/claude-backups/courses-migration-<phase>-<UTC>.json and keeps
 * that file updated after each write, so `rollback` can also undo a partial
 * run. WP-CLI rejects unknown --flags on eval-file, so the LearnDash override
 * is the bare word `force-with-ld` (`--force-with-ld` is accepted too if it
 * ever reaches the script). tools/ is never deployed with the theme.
 *
 * @package CardanoPress Bootstrap
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_CLI' ) ) {
	echo "Run with WP-CLI: wp eval-file migrate.php <seed|cutover|rollback> [apply] --path=...\n";
	return;
}

/**
 * One run of the migration: plan (and print) every change, then, in apply
 * mode only, back up and write.
 */
final class LC_Courses_Migration {

	const VERSION = '1.0.0';

	const LD_PLUGIN = 'sfwd-lms/sfwd_lms.php';

	/**
	 * ACF field keys by field name (acf-json/group_lcc_*.json).
	 */
	const FIELDS = array(
		'course_lessons'    => 'field_lcc_course_lessons',
		'course_final_quiz' => 'field_lcc_course_final_quiz',
		'superseded_by'     => 'field_lcc_superseded_by',
		'lesson_topics'     => 'field_lcc_lesson_topics',
		'lesson_quiz'       => 'field_lcc_lesson_quiz',
		'topic_quiz'        => 'field_lcc_topic_quiz',
		'quiz_pass_mark'    => 'field_lcc_quiz_pass_mark',
		'quiz_randomise'    => 'field_lcc_quiz_randomise',
		'quiz_questions'    => 'field_lcc_quiz_questions',
		'quiz_results'      => 'field_lcc_quiz_results',
	);

	const SUB_FIELDS = array(
		'field_lcc_question',
		'field_lcc_question_type',
		'field_lcc_answers',
		'field_lcc_answer_text',
		'field_lcc_answer_correct',
		'field_lcc_explanation_correct',
		'field_lcc_explanation_incorrect',
		'field_lcc_result_min',
		'field_lcc_result_message',
	);

	const LIST_FIELDS = array( 'course_lessons', 'lesson_topics' );

	const REPEATER_FIELDS = array( 'quiz_questions', 'quiz_results' );

	/**
	 * URL bases the theme registers (same as LearnDash's settings at analysis time).
	 */
	const POST_BASES = array(
		'sfwd-courses' => 'courses',
		'sfwd-lessons' => 'lessons',
		'sfwd-topic'   => 'topic',
		'sfwd-quiz'    => 'quizzes',
	);

	const TYPE_LABELS = array(
		'sfwd-courses' => 'course',
		'sfwd-lessons' => 'lesson',
		'sfwd-topic'   => 'topic',
		'sfwd-quiz'    => 'quiz',
	);

	const TAX_BASES = array(
		'ld_course_category' => 'course-category',
		'ld_course_tag'      => 'course-tag',
		'ld_topic_tag'       => 'topic-tag',
		'ld_quiz_category'   => 'quiz-category',
	);

	/**
	 * Live courses that take over visitors of deleted ones: key => slug.
	 */
	const LIVE_COURSES = array(
		'eternl'  => 'eternl-wallet-course-tutorials',
		'lace'    => 'lace-wallet',
		'minswap' => 'minswap-decentralised-exchange-course',
	);

	/**
	 * Deleted course ID => successor (wallet basics and Nami). Any other
	 * deleted course (VyFinance-style DEX, LenFi, Clarity DAO, CardanoPress,
	 * DeFi…) sends its leftovers to /courses/.
	 */
	const FORMER_COURSES = array(
		24528 => 'eternl',
		26813 => 'eternl',
	);

	/**
	 * Words in the slug/title of a leftover with no recorded former course.
	 */
	const KEYWORDS = array(
		'lace'    => 'lace',
		'minswap' => 'minswap',
		'eternl'  => 'eternl',
		'nami'    => 'eternl',
	);

	/** @var string */
	private $dir = '';
	/** @var string */
	private $phase = '';
	/** @var bool */
	private $apply = false;
	/** @var bool */
	private $force_ld = false;
	/** @var bool Seed: replace outline fields and quizzes that are already stored. */
	private $overwrite = false;
	/** @var string Where the ACF course field groups came from. */
	private $acf_source = '';
	/** @var string */
	private $backup_in = '';
	/** @var bool */
	private $ld_active = false;
	/** @var bool */
	private $acf_ready = false;
	/** @var bool */
	private $rm_ready = false;
	/** @var array<int, object> Posts of the four course types (not trashed). */
	private $posts = array();
	/** @var array<int, array> Parsed ld_course_steps by course ID, including deleted courses. */
	private $steps = array();
	/** @var array<int, array> Planned writes. */
	private $ops = array();
	/** @var int */
	private $note_count = 0;
	/** @var string */
	private $journal_file = '';
	/** @var string */
	private $started_utc = '';
	/** @var array|null */
	private $redirect_rows = null;
	/** @var array<string, bool> */
	private $planned_sources = array();
	/** @var array<string, int> Paths (no slashes) of posts this run sets to draft. */
	private $drafting = array();
	/** @var array<string, string> Lower-case path (no slashes) => where its 301 goes (planned or existing). */
	private $redirect_to = array();
	/** @var string[] Headline numbers for the summary. */
	private $facts = array();
	/** @var int Sources that an existing active redirect already handles. */
	private $kept_redirects = 0;

	/**
	 * Parse the positional arguments WP-CLI passes to eval-file.
	 *
	 * @param string $dir  Folder holding the data files.
	 * @param array  $args Positional arguments.
	 */
	public function __construct( $dir, $args ) {
		$this->dir = $dir;
		$rest      = array();
		foreach ( (array) $args as $arg ) {
			if ( 'apply' === $arg ) {
				$this->apply = true;
			} elseif ( in_array( $arg, array( 'force-with-ld', '--force-with-ld' ), true ) ) {
				$this->force_ld = true;
			} elseif ( 'overwrite' === $arg ) {
				$this->overwrite = true;
			} else {
				$rest[] = (string) $arg;
			}
		}
		$this->phase = (string) array_shift( $rest );
		if ( 'rollback' === $this->phase ) {
			$this->backup_in = (string) array_shift( $rest );
		}
		$valid = in_array( $this->phase, array( 'seed', 'cutover', 'rollback' ), true );
		if ( ! $valid || $rest || ( 'rollback' === $this->phase && '' === $this->backup_in ) || ( $this->overwrite && 'seed' !== $this->phase ) ) {
			WP_CLI::error( "Usage: wp eval-file migrate.php seed [apply] [overwrite] --path=...\n       wp eval-file migrate.php cutover [apply] [force-with-ld] --path=...\n       wp eval-file migrate.php rollback <backup.json> [apply] --path=..." );
		}
	}

	/**
	 * Plan, print, and (apply only) back up and write.
	 */
	public function run() {
		$this->started_utc = gmdate( 'Y-m-d\TH:i:s\Z' );
		$this->say( sprintf( 'Learn Cardano courses migration %s: phase "%s", %s', self::VERSION, $this->phase, $this->apply ? 'APPLY' : 'DRY RUN (nothing is written)' ) );
		$this->say( 'Site: ' . home_url() . '  WordPress: ' . ABSPATH );
		$this->environment();
		$this->load_posts();

		if ( 'seed' === $this->phase ) {
			$this->plan_seed();
		} elseif ( 'cutover' === $this->phase ) {
			$this->plan_cutover();
		} else {
			$this->plan_rollback();
		}

		$this->summary();

		if ( ! $this->apply ) {
			$this->say( '' );
			$this->say( 'DRY RUN: nothing was written. Re-run with `apply` to make the changes marked "+".' );
			return;
		}

		$this->guard_apply();
		if ( ! $this->ops ) {
			WP_CLI::success( 'Nothing to change.' );
			return;
		}
		$this->start_backup();
		$this->execute();
	}

	/* ------------------------------------------------------------------ */
	/* Environment and data loading                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Detect LearnDash, the ACF field groups and Rank Math's redirections API.
	 */
	private function environment() {
		$this->ld_active = defined( 'LEARNDASH_VERSION' ) || in_array( self::LD_PLUGIN, (array) get_option( 'active_plugins', array() ), true );

		$missing          = $this->missing_acf_fields();
		$this->acf_source = 'the active theme';
		if ( $missing && function_exists( 'acf_add_local_field_group' ) ) {
			$loaded = $this->load_field_groups();
			if ( $loaded ) {
				$missing          = $this->missing_acf_fields();
				$this->acf_source = implode( ', ', $loaded ) . ' beside migrate.php (theme not deployed yet)';
			}
		}
		$this->acf_ready = ! $missing;
		$this->rm_ready  = class_exists( '\RankMath\Redirections\Redirection' ) && class_exists( '\RankMath\Redirections\DB' );

		$this->say( 'LearnDash (sfwd-lms): ' . ( $this->ld_active ? 'ACTIVE' . ( defined( 'LEARNDASH_VERSION' ) ? ' ' . LEARNDASH_VERSION : '' ) : 'inactive' ) );
		$this->say( 'ACF course fields: ' . ( $this->acf_ready ? 'loaded from ' . $this->acf_source : 'NOT loaded (' . count( $missing ) . ' missing, first: ' . $missing[0] . '). Copy acf-json/group_lcc_*.json next to migrate.php.' ) );
		if ( $this->overwrite ) {
			$this->say( 'overwrite: stored outline fields and quizzes are replaced (admin edits are lost).' );
		}
		$this->say( 'Rank Math redirections API: ' . ( $this->rm_ready ? 'available' : 'NOT available' ) );
		$types = array();
		foreach ( array_keys( self::POST_BASES ) as $type ) {
			$types[] = $type . ( post_type_exists( $type ) ? '' : ' (not registered)' );
		}
		$this->say( 'Post types: ' . implode( ', ', $types ) );

		foreach ( $this->post_bases() as $type => $base ) {
			if ( self::POST_BASES[ $type ] !== $base ) {
				$this->note( sprintf( 'LearnDash URL base for %s is "%s" but the theme registers "%s": old URLs use the LearnDash base.', $type, $base, self::POST_BASES[ $type ] ) );
			}
		}
	}

	/**
	 * ACF field keys this script writes that ACF doesn't know.
	 *
	 * @return string[]
	 */
	private function missing_acf_fields() {
		if ( ! function_exists( 'acf_get_field' ) ) {
			return array( 'ACF itself' );
		}
		$missing = array();
		foreach ( array_merge( array_values( self::FIELDS ), self::SUB_FIELDS ) as $key ) {
			if ( ! acf_get_field( $key ) ) {
				$missing[] = $key;
			}
		}
		return $missing;
	}

	/**
	 * Register the theme's course field groups for this run from the
	 * group_lcc_*.json copies beside migrate.php, the same way ACF loads local
	 * JSON. Nothing is saved: they only let update_field() find the fields.
	 *
	 * @return string[] Files loaded.
	 */
	private function load_field_groups() {
		$loaded = array();
		foreach ( array( 'group_lcc_course', 'group_lcc_lesson', 'group_lcc_topic', 'group_lcc_quiz' ) as $key ) {
			$group = $this->read_json( $this->dir . '/' . $key . '.json' );
			if ( ! $group || ! isset( $group['key'] ) || $key !== $group['key'] || empty( $group['fields'] ) ) {
				continue;
			}
			if ( acf_add_local_field_group( $group ) ) {
				$loaded[] = $key . '.json';
			}
		}
		return $loaded;
	}

	/**
	 * Load every course-type post and every ld_course_steps row (deleted
	 * courses keep theirs, which tells us where leftovers came from).
	 */
	private function load_posts() {
		global $wpdb;
		$types = "'" . implode( "','", array_keys( self::POST_BASES ) ) . "'";
		$rows  = $wpdb->get_results( "SELECT ID, post_type, post_status, post_name, post_title, post_content FROM {$wpdb->posts} WHERE post_type IN ({$types}) AND post_status NOT IN ('trash','auto-draft','inherit') ORDER BY ID" ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $rows as $row ) {
			$row->ID                = (int) $row->ID;
			$this->posts[ $row->ID ] = $row;
		}
		$meta = $wpdb->get_results( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = 'ld_course_steps' ORDER BY meta_id" );
		foreach ( $meta as $row ) {
			$id = (int) $row->post_id;
			if ( isset( $this->steps[ $id ] ) ) {
				continue;
			}
			$tree = $this->parse_steps( maybe_unserialize( $row->meta_value ) );
			if ( $tree ) {
				$this->steps[ $id ] = $tree;
			}
		}
	}

	/**
	 * Turn ld_course_steps into lessons > topics > quizzes, in visitor order
	 * (the key order of ['steps']['h']).
	 *
	 * @param mixed $data Unserialized meta value.
	 * @return array|null
	 */
	private function parse_steps( $data ) {
		if ( ! is_array( $data ) || ! isset( $data['steps']['h'] ) || ! is_array( $data['steps']['h'] ) ) {
			return null;
		}
		$h       = $data['steps']['h'];
		$lessons = ( isset( $h['sfwd-lessons'] ) && is_array( $h['sfwd-lessons'] ) ) ? $h['sfwd-lessons'] : array();
		$tree    = array(
			'lessons' => array(),
			'quizzes' => self::step_ids( isset( $h['sfwd-quiz'] ) ? $h['sfwd-quiz'] : null ),
		);
		foreach ( self::step_ids( $lessons ) as $lesson_id ) {
			$node   = ( isset( $lessons[ $lesson_id ] ) && is_array( $lessons[ $lesson_id ] ) ) ? $lessons[ $lesson_id ] : array();
			$tnodes = ( isset( $node['sfwd-topic'] ) && is_array( $node['sfwd-topic'] ) ) ? $node['sfwd-topic'] : array();
			$topics = array();
			foreach ( self::step_ids( $tnodes ) as $topic_id ) {
				$tnode    = ( isset( $tnodes[ $topic_id ] ) && is_array( $tnodes[ $topic_id ] ) ) ? $tnodes[ $topic_id ] : array();
				$topics[] = array(
					'id'      => $topic_id,
					'quizzes' => self::step_ids( isset( $tnode['sfwd-quiz'] ) ? $tnode['sfwd-quiz'] : null ),
				);
			}
			$tree['lessons'][] = array(
				'id'      => $lesson_id,
				'topics'  => $topics,
				'quizzes' => self::step_ids( isset( $node['sfwd-quiz'] ) ? $node['sfwd-quiz'] : null ),
			);
		}
		return $tree;
	}

	/**
	 * IDs at one level of ld_course_steps. LearnDash keys each level by post
	 * ID (id => children); a plain list of IDs is accepted too.
	 *
	 * @param mixed $node Level.
	 * @return int[]
	 */
	private static function step_ids( $node ) {
		if ( ! is_array( $node ) || ! $node ) {
			return array();
		}
		$is_list = ! array_filter( $node, 'is_array' ) && array_keys( $node ) === range( 0, count( $node ) - 1 );
		$ids     = $is_list ? array_values( $node ) : array_keys( $node );
		return array_values( array_filter( array_map( 'intval', $ids ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Phase: seed                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Plan the seed phase.
	 */
	private function plan_seed() {
		$this->head( 'Course outlines (from LearnDash ld_course_steps)' );
		$this->cross_check();

		$seen = array();
		foreach ( $this->ids_of( 'sfwd-courses' ) as $course_id ) {
			$this->seed_course( $course_id, $seen );
		}
		$placed = array();
		$hidden = 0;
		foreach ( array_keys( $seen ) as $id ) {
			$type            = self::TYPE_LABELS[ $this->posts[ $id ]->post_type ];
			$placed[ $type ] = isset( $placed[ $type ] ) ? $placed[ $type ] + 1 : 1;
			$hidden         += 'publish' === $this->posts[ $id ]->post_status ? 0 : 1;
		}
		$this->facts[] = sprintf(
			'Outline: %d courses, %d lessons, %d topics, %d quizzes placed (%d unpublished steps stay in the outline; the theme skips them)',
			count( $this->ids_of( 'sfwd-courses' ) ),
			isset( $placed['lesson'] ) ? $placed['lesson'] : 0,
			isset( $placed['topic'] ) ? $placed['topic'] : 0,
			isset( $placed['quiz'] ) ? $placed['quiz'] : 0,
			$hidden
		);

		$this->head( 'Superseded course' );
		if ( $this->is_type( 25093, 'sfwd-courses' ) && $this->is_type( 27739, 'sfwd-courses' ) ) {
			$this->plan_meta( 25093, array( 'superseded_by' => 27739 ), 'course 25093 "' . $this->posts[25093]->post_title . '"', 1 );
		} else {
			$this->note( 'course 25093 or 27739 is missing: superseded_by not set' );
		}

		$this->plan_quizzes();
		$this->plan_content_fixes();
	}

	/**
	 * Plan the outline fields of one course and its lessons and topics.
	 *
	 * @param int   $course_id Course ID.
	 * @param array $seen      Placed IDs (first placement wins), by reference.
	 */
	private function seed_course( $course_id, &$seen ) {
		$course = $this->posts[ $course_id ];
		$this->say( '' );
		$this->say( sprintf( 'course %d /%s "%s" [%s]', $course_id, $this->post_path( $course ), $course->post_title, $course->post_status ) );
		if ( ! isset( $this->steps[ $course_id ] ) ) {
			$this->note( 'no ld_course_steps: outline not seeded', 1 );
			return;
		}
		$tree  = $this->steps[ $course_id ];
		$notes = array();
		$plan  = array();
		foreach ( $tree['lessons'] as $lesson ) {
			if ( ! $this->claim( $lesson['id'], 'sfwd-lessons', $course_id, $seen, $notes ) ) {
				continue;
			}
			$topics = array();
			foreach ( $lesson['topics'] as $topic ) {
				if ( $this->claim( $topic['id'], 'sfwd-topic', $course_id, $seen, $notes ) ) {
					$topics[ $topic['id'] ] = $this->first_quiz( $topic['quizzes'], $course_id, $seen, $notes );
				}
			}
			$plan[ $lesson['id'] ] = array(
				'topics' => $topics,
				'quiz'   => $this->first_quiz( $lesson['quizzes'], $course_id, $seen, $notes ),
			);
		}
		$final = $this->first_quiz( $tree['quizzes'], $course_id, $seen, $notes );
		foreach ( $notes as $text ) {
			$this->note( $text, 1 );
		}

		$this->plan_meta(
			$course_id,
			array(
				'course_lessons'    => array_keys( $plan ),
				'course_final_quiz' => $final,
			),
			sprintf( 'course %d: %d lessons, final quiz %s', $course_id, count( $plan ), $final ? $final : 'none' ),
			1
		);
		foreach ( $plan as $lesson_id => $lesson ) {
			$this->plan_meta(
				$lesson_id,
				array(
					'lesson_topics' => array_keys( $lesson['topics'] ),
					'lesson_quiz'   => $lesson['quiz'],
				),
				sprintf( 'lesson %d "%s"%s: %d topics, quiz %s', $lesson_id, $this->posts[ $lesson_id ]->post_title, $this->status_tag( $lesson_id ), count( $lesson['topics'] ), $lesson['quiz'] ? $lesson['quiz'] : 'none' ),
				2
			);
			foreach ( $lesson['topics'] as $topic_id => $quiz_id ) {
				$this->plan_meta(
					$topic_id,
					array( 'topic_quiz' => $quiz_id ),
					sprintf( 'topic %d "%s"%s: quiz %s', $topic_id, $this->posts[ $topic_id ]->post_title, $this->status_tag( $topic_id ), $quiz_id ? $quiz_id : 'none' ),
					3
				);
			}
		}
	}

	/**
	 * Accept a step for a course if it exists, has the right type and is not
	 * placed elsewhere already.
	 *
	 * @param int    $id        Post ID.
	 * @param string $type      Expected post type.
	 * @param int    $course_id Course ID.
	 * @param array  $seen      Placed IDs, by reference.
	 * @param array  $notes     Notes, by reference.
	 * @return bool
	 */
	private function claim( $id, $type, $course_id, &$seen, &$notes ) {
		if ( ! $this->is_type( $id, $type ) ) {
			$notes[] = sprintf( '%d is listed as a %s but is missing, trashed or another type: left out', $id, self::TYPE_LABELS[ $type ] );
			return false;
		}
		if ( isset( $seen[ $id ] ) ) {
			$notes[] = sprintf( '%s %d is already placed in course %d: first placement wins, left out here', self::TYPE_LABELS[ $type ], $id, $seen[ $id ] );
			return false;
		}
		$seen[ $id ] = $course_id;
		return true;
	}

	/**
	 * First valid quiz of a level; later ones are reported.
	 *
	 * @param int[] $ids       Quiz IDs.
	 * @param int   $course_id Course ID.
	 * @param array $seen      Placed IDs, by reference.
	 * @param array $notes     Notes, by reference.
	 * @return int Quiz ID or 0.
	 */
	private function first_quiz( $ids, $course_id, &$seen, &$notes ) {
		$first = 0;
		foreach ( $ids as $id ) {
			if ( $first ) {
				$notes[] = sprintf( 'extra quiz %d at the same level as quiz %d: only the first quiz is used', $id, $first );
				continue;
			}
			if ( $this->claim( $id, 'sfwd-quiz', $course_id, $seen, $notes ) ) {
				$first = $id;
			}
		}
		return $first;
	}

	/**
	 * Compare the LearnDash outlines with expected-tree.json (the analysis
	 * export) and report every difference.
	 */
	private function cross_check() {
		$expected = $this->read_json( $this->dir . '/expected-tree.json' );
		if ( ! $expected || empty( $expected['courses'] ) ) {
			$this->note( 'expected-tree.json is missing or empty: cross-check skipped' );
			return;
		}
		$want = array();
		foreach ( $expected['courses'] as $course ) {
			$want[ (int) $course['id'] ] = $course;
		}
		$mismatches = 0;
		$totals     = array( 0, 0, 0 );
		foreach ( $want as $course_id => $course ) {
			if ( ! $this->is_type( $course_id, 'sfwd-courses' ) || ! isset( $this->steps[ $course_id ] ) ) {
				$this->note( sprintf( 'cross-check: expected course %d is missing or has no ld_course_steps', $course_id ) );
				++$mismatches;
				continue;
			}
			$got     = $this->steps[ $course_id ];
			$lessons = array_map( 'intval', wp_list_pluck( $course['lessons'], 'id' ) );
			if ( wp_list_pluck( $got['lessons'], 'id' ) !== $lessons ) {
				$this->note( sprintf( 'cross-check: course %d lessons differ. expected [%s] found [%s]', $course_id, implode( ',', $lessons ), implode( ',', wp_list_pluck( $got['lessons'], 'id' ) ) ) );
				++$mismatches;
				continue;
			}
			foreach ( $course['lessons'] as $i => $lesson ) {
				$found  = $got['lessons'][ $i ];
				$topics = array_map( 'intval', wp_list_pluck( $lesson['topics'], 'id' ) );
				if ( wp_list_pluck( $found['topics'], 'id' ) !== $topics ) {
					$this->note( sprintf( 'cross-check: lesson %d topics differ. expected [%s] found [%s]', $lesson['id'], implode( ',', $topics ), implode( ',', wp_list_pluck( $found['topics'], 'id' ) ) ) );
					++$mismatches;
					continue;
				}
				if ( array_map( 'intval', $lesson['quizzes'] ) !== $found['quizzes'] ) {
					$this->note( sprintf( 'cross-check: lesson %d quizzes differ. expected [%s] found [%s]', $lesson['id'], implode( ',', $lesson['quizzes'] ), implode( ',', $found['quizzes'] ) ) );
					++$mismatches;
				}
				foreach ( $lesson['topics'] as $j => $topic ) {
					if ( array_map( 'intval', $topic['quizzes'] ) !== $found['topics'][ $j ]['quizzes'] ) {
						$this->note( sprintf( 'cross-check: topic %d quizzes differ. expected [%s] found [%s]', $topic['id'], implode( ',', $topic['quizzes'] ), implode( ',', $found['topics'][ $j ]['quizzes'] ) ) );
						++$mismatches;
					}
					$totals[2] += count( $topic['quizzes'] );
				}
				$totals[1] += count( $topics );
				$totals[2] += count( $lesson['quizzes'] );
			}
			if ( array_map( 'intval', $course['quizzes'] ) !== $got['quizzes'] ) {
				$this->note( sprintf( 'cross-check: course %d final quizzes differ. expected [%s] found [%s]', $course_id, implode( ',', $course['quizzes'] ), implode( ',', $got['quizzes'] ) ) );
				++$mismatches;
			}
			$totals[0] += count( $lessons );
			$totals[2] += count( $course['quizzes'] );
		}
		foreach ( $this->ids_of( 'sfwd-courses' ) as $course_id ) {
			if ( ! isset( $want[ $course_id ] ) ) {
				$this->note( sprintf( 'cross-check: course %d "%s" [%s] is not in expected-tree.json (added since the analysis?)', $course_id, $this->posts[ $course_id ]->post_title, $this->posts[ $course_id ]->post_status ) );
				++$mismatches;
			}
		}
		$this->say(
			sprintf(
				'Cross-check with expected-tree.json: %s (%d courses, %d lessons, %d topics, %d quizzes expected)',
				$mismatches ? $mismatches . ' MISMATCH(ES), see notes' : 'OK, identical',
				count( $want ),
				$totals[0],
				$totals[1],
				$totals[2]
			)
		);
	}

	/**
	 * Plan the quiz fields from quiz-data.json.
	 */
	private function plan_quizzes() {
		$this->head( 'Quizzes (from quiz-data.json)' );
		$data = $this->read_json( $this->dir . '/quiz-data.json' );
		if ( ! $data || empty( $data['quizzes'] ) || ! is_array( $data['quizzes'] ) ) {
			WP_CLI::error( 'quiz-data.json is missing, empty or not valid JSON (it must sit next to migrate.php).' );
		}
		foreach ( $data['quizzes'] as $quiz ) {
			$id   = isset( $quiz['id'] ) ? (int) $quiz['id'] : 0;
			$slug = isset( $quiz['slug'] ) ? (string) $quiz['slug'] : '';
			if ( ! $this->is_type( $id, 'sfwd-quiz' ) ) {
				$this->note( sprintf( 'quiz %d is missing or not a quiz: skipped', $id ) );
				continue;
			}
			if ( $this->posts[ $id ]->post_name !== $slug ) {
				$this->note( sprintf( 'quiz %d slug is "%s", quiz-data.json expects "%s": skipped', $id, $this->posts[ $id ]->post_name, $slug ) );
				continue;
			}
			$problems = $this->validate_quiz( $quiz );
			if ( $problems ) {
				foreach ( $problems as $problem ) {
					$this->note( sprintf( 'quiz %d: %s', $id, $problem ) );
				}
				$this->note( sprintf( 'quiz %d skipped because its data is invalid', $id ) );
				continue;
			}

			$rows  = array();
			$types = array(
				'single'   => 0,
				'multiple' => 0,
			);
			foreach ( $quiz['questions'] as $question ) {
				$answers = array();
				foreach ( $question['answers'] as $answer ) {
					$answers[] = array(
						'field_lcc_answer_text'    => (string) $answer['text'],
						'field_lcc_answer_correct' => empty( $answer['correct'] ) ? 0 : 1,
					);
				}
				$rows[] = array(
					'field_lcc_question'              => (string) $question['question'],
					'field_lcc_question_type'         => $question['type'],
					'field_lcc_answers'               => $answers,
					'field_lcc_explanation_correct'   => (string) $question['explanation_correct'],
					'field_lcc_explanation_incorrect' => (string) $question['explanation_incorrect'],
				);
				++$types[ $question['type'] ];
			}
			$results = array();
			$list    = isset( $quiz['results'] ) && is_array( $quiz['results'] ) ? $quiz['results'] : array();
			usort(
				$list,
				function ( $a, $b ) {
					return (int) $a['min'] - (int) $b['min'];
				}
			);
			foreach ( $list as $result ) {
				$results[] = array(
					'field_lcc_result_min'     => (int) $result['min'],
					'field_lcc_result_message' => (string) $result['message'],
				);
			}
			$existing = (int) get_post_meta( $id, 'quiz_questions', true );
			$stored   = true;
			foreach ( array( 'quiz_pass_mark', 'quiz_randomise', 'quiz_questions', 'quiz_results' ) as $name ) {
				$stored = $stored && metadata_exists( 'post', $id, $name );
			}
			if ( $stored && ! $this->overwrite ) {
				// Seeded before (or edited in WP admin since): a re-run must not undo admin edits.
				$this->say( sprintf( '= quiz %d "%s": already stored (%d questions), left alone. Add `overwrite` to replace it from quiz-data.json.', $id, $this->posts[ $id ]->post_title, $existing ), 1 );
				continue;
			}
			$label = sprintf(
				'quiz %d "%s": pass mark %d%%, randomise %s, %d questions (%d single, %d multiple), %d result messages%s',
				$id,
				$this->posts[ $id ]->post_title,
				(int) $quiz['pass_mark'],
				empty( $quiz['randomise'] ) ? 'no' : 'yes',
				count( $rows ),
				$types['single'],
				$types['multiple'],
				count( $results ),
				$existing ? sprintf( ' (replaces %d existing question rows)', $existing ) : ''
			);
			$this->add_op(
				array(
					'op'      => 'meta',
					'post_id' => $id,
					'names'   => array( 'quiz_pass_mark', 'quiz_randomise', 'quiz_questions', 'quiz_results' ),
					'fields'  => array(
						'quiz_pass_mark' => (int) $quiz['pass_mark'],
						'quiz_randomise' => empty( $quiz['randomise'] ) ? 0 : 1,
						'quiz_questions' => $rows,
						'quiz_results'   => $results,
					),
					'label'   => $label,
				),
				1
			);
			foreach ( (array) ( isset( $quiz['changes'] ) ? $quiz['changes'] : array() ) as $change ) {
				$this->say( '- ' . $change, 2 );
			}
		}
	}

	/**
	 * Check one quiz from quiz-data.json.
	 *
	 * @param array $quiz Quiz data.
	 * @return string[] Problems.
	 */
	private function validate_quiz( $quiz ) {
		$problems = array();
		if ( ! isset( $quiz['pass_mark'] ) || (int) $quiz['pass_mark'] < 0 || (int) $quiz['pass_mark'] > 100 ) {
			$problems[] = 'pass_mark must be 0-100';
		}
		if ( empty( $quiz['questions'] ) || ! is_array( $quiz['questions'] ) ) {
			$problems[] = 'no questions';
			return $problems;
		}
		foreach ( $quiz['questions'] as $i => $question ) {
			$n       = $i + 1;
			$type    = isset( $question['type'] ) ? $question['type'] : '';
			$answers = isset( $question['answers'] ) && is_array( $question['answers'] ) ? $question['answers'] : array();
			$correct = count( array_filter( wp_list_pluck( $answers, 'correct' ) ) );
			if ( ! in_array( $type, array( 'single', 'multiple' ), true ) ) {
				$problems[] = "Q{$n}: type must be single or multiple";
			}
			if ( '' === trim( wp_strip_all_tags( isset( $question['question'] ) ? (string) $question['question'] : '' ) ) ) {
				$problems[] = "Q{$n}: empty question";
			}
			if ( count( $answers ) < 2 ) {
				$problems[] = "Q{$n}: needs at least 2 answers";
			}
			if ( 'single' === $type && 1 !== $correct ) {
				$problems[] = "Q{$n}: single choice needs exactly 1 correct answer, has {$correct}";
			}
			if ( 'multiple' === $type && $correct < 1 ) {
				$problems[] = "Q{$n}: no correct answer";
			}
			foreach ( $answers as $answer ) {
				if ( ! isset( $answer['text'] ) || '' === trim( (string) $answer['text'] ) ) {
					$problems[] = "Q{$n}: empty answer text";
				}
			}
			if ( ! array_key_exists( 'explanation_correct', $question ) || ! array_key_exists( 'explanation_incorrect', $question ) ) {
				$problems[] = "Q{$n}: explanation_correct / explanation_incorrect missing";
			}
		}
		return $problems;
	}

	/**
	 * Plan the guarded content fixes.
	 */
	private function plan_content_fixes() {
		$this->head( 'Content fixes (exact match, must occur exactly once)' );

		$this->plan_content(
			25541,
			'<p>Complete the quiz with a 100% success rate to be able to earn the points allocated for the quiz.</p>',
			'<p>Check what you have learned about impermanent loss with this short quiz.</p>',
			'stale GamiPress "points" sentence replaced with a short intro'
		);

		// Logged-out-only wallet-connect prompt: it only made sense with LearnDash's login. The intro text stays.
		$empty_p   = "<!-- wp:paragraph -->\n<p></p>\n<!-- /wp:paragraph -->";
		$logged_p  = '{"blockVisibility":{"hideBlock":false,"controlSets":[{"id":1,"enable":true,"controls":{"userRole":{"visibilityByRole":"logged-out"}}}]}}';
		$logged_sc = '{"blockVisibility":{"controlSets":[{"id":1,"enable":true,"controls":{"userRole":{"visibilityByRole":"logged-out"}}}]}}';
		$blocks    = array(
			$empty_p,
			"<!-- wp:paragraph {$logged_p} -->\n<p>Connect your Cardano wallet to create an anonymous account and get started.</p>\n<!-- /wp:paragraph -->",
			"<!-- wp:shortcode {$logged_sc} -->\n[cardanopress_template name=\"part/modal-trigger\" if=\"!isConnected\"]\n<!-- /wp:shortcode -->",
			$empty_p,
			$empty_p,
		);
		$this->plan_content(
			26291,
			"\n\n" . implode( "\n\n", $blocks ),
			'',
			'wallet-connect shortcode and logged-out-only Block Visibility paragraph removed (with the empty paragraphs around them); intro kept'
		);

		$literal = '&lt;!--more--&gt;';
		$found   = 0;
		foreach ( $this->ids_of( 'sfwd-courses' ) as $course_id ) {
			$content = $this->posts[ $course_id ]->post_content;
			if ( false === strpos( $content, $literal ) ) {
				continue;
			}
			++$found;
			$wrapped = '<p>' . $literal . '</p>';
			$search  = 1 === substr_count( $content, $wrapped ) ? $wrapped : $literal;
			$this->plan_content( $course_id, $search, '', 'visible "<!--more-->" text removed from the course intro' );
		}
		if ( ! $found ) {
			$this->say( '= no course content contains a literal "&lt;!--more--&gt;" (real more tags are fine): nothing to fix', 1 );
		}
	}

	/**
	 * Plan one search/replace on a post's content when the search text occurs
	 * exactly once.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $search  Exact text.
	 * @param string $replace Replacement.
	 * @param string $why     Description.
	 */
	private function plan_content( $post_id, $search, $replace, $why ) {
		$post = isset( $this->posts[ $post_id ] ) ? $this->posts[ $post_id ] : null;
		if ( ! $post ) {
			$this->note( sprintf( '%d: post missing, content fix skipped (%s)', $post_id, $why ), 1 );
			return;
		}
		$count = substr_count( $post->post_content, $search );
		if ( 1 !== $count ) {
			$hint = ( 0 === $count ) ? ' (already applied, or the content changed)' : '';
			$this->note( sprintf( '%s %d "%s": %s: text found %d times, needs exactly 1: skipped%s', self::TYPE_LABELS[ $post->post_type ], $post_id, $post->post_title, $why, $count, $hint ), 1 );
			return;
		}
		$this->add_op(
			array(
				'op'      => 'content',
				'post_id' => $post_id,
				'search'  => $search,
				'replace' => $replace,
				'label'   => sprintf( '%s %d "%s": %s', self::TYPE_LABELS[ $post->post_type ], $post_id, $post->post_title, $why ),
			),
			1
		);
	}

	/**
	 * Plan ACF field writes for a post, leaving out values that are already set.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $fields  Field name => value (IDs as ints, 0 for none).
	 * @param string $label   Description.
	 * @param int    $indent  Indent level.
	 */
	private function plan_meta( $post_id, $fields, $label, $indent ) {
		$write = array();
		$kept  = array();
		foreach ( $fields as $name => $value ) {
			if ( $this->field_matches( $post_id, $name, $value ) ) {
				continue;
			}
			// Stored and different: seeded before and edited in WP admin since. Admin edits win.
			if ( ! $this->overwrite && metadata_exists( 'post', $post_id, $name ) ) {
				$raw    = get_post_meta( $post_id, $name, true );
				$kept[] = sprintf( '%s is [%s]', $name, implode( ',', in_array( $name, self::LIST_FIELDS, true ) ? $this->id_list( $raw ) : array( (int) $raw ) ) );
				continue;
			}
			$write[ $name ] = in_array( $name, self::LIST_FIELDS, true ) ? array_map( 'intval', $value ) : ( $value ? (int) $value : '' );
		}
		if ( $kept ) {
			$this->note( sprintf( '%s: already stored and different (%s), left alone. Add `overwrite` to replace.', $label, implode( '; ', $kept ) ), $indent );
		}
		if ( ! $write ) {
			if ( ! $kept ) {
				$nothing = ! array_filter( $fields );
				$this->say( '= ' . $label . ( $nothing ? ' (nothing to store)' : ' (already set)' ), $indent );
			}
			return;
		}
		$parts = array();
		foreach ( $write as $name => $value ) {
			$parts[] = $name . '=' . ( is_array( $value ) ? '[' . implode( ',', $value ) . ']' : ( '' === $value ? 'none' : $value ) );
		}
		$this->add_op(
			array(
				'op'      => 'meta',
				'post_id' => (int) $post_id,
				'names'   => array_keys( $write ),
				'fields'  => $write,
				'label'   => $label . '  {' . implode( '; ', $parts ) . '}',
			),
			$indent
		);
	}

	/* ------------------------------------------------------------------ */
	/* Phase: cutover                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Plan the cutover phase.
	 */
	private function plan_cutover() {
		if ( $this->ld_active ) {
			$this->note( 'LearnDash is still active: ' . ( $this->force_ld ? 'force-with-ld given, apply will run anyway.' : 'apply will refuse to run until it is deactivated (or force-with-ld is given).' ) );
		}
		if ( ! $this->rm_ready ) {
			$this->note( 'Rank Math redirections are not available: redirects can be listed but not created.' );
		}
		$this->plan_orphans();
		$this->plan_old_urls();
		$this->plan_utility_pages();
		$this->plan_term_urls();
		$this->plan_retargets();
		$this->plan_chain_retargets();
		$this->plan_tidy_ups();
		$this->check_targets();
		$this->report_redirects();
		$this->plan_widgets();
	}

	/**
	 * 1. Published lessons/topics/quizzes in no course: empty ones go to draft
	 * with a 301; ones with content keep their URL.
	 */
	private function plan_orphans() {
		$this->head( '1. Leftover lessons, topics and quizzes (published, in no live course)' );
		$placed = $this->placed();
		$kept   = array();
		$empty  = array();
		foreach ( $this->posts as $id => $post ) {
			if ( 'sfwd-courses' === $post->post_type || 'publish' !== $post->post_status || isset( $placed[ $id ] ) ) {
				continue;
			}
			$signals = $this->content_signals( $post );
			if ( $signals ) {
				$kept[ $id ] = $signals;
			} else {
				$empty[] = $id;
			}
		}
		$this->facts[] = sprintf( 'Placed in a course: %d steps. Published leftovers: %d (%d keep their URL, %d empty to draft + 301).', count( $placed ), count( $kept ) + count( $empty ), count( $kept ), count( $empty ) );
		$this->say( end( $this->facts ) );

		$this->say( '' );
		$this->say( sprintf( 'Keep URL, the theme shows the "not part of a current course" note (%d):', count( $kept ) ) );
		foreach ( $kept as $id => $signals ) {
			$post = $this->posts[ $id ];
			$this->say( sprintf( '- %s %d /%s "%s": %s', self::TYPE_LABELS[ $post->post_type ], $id, $this->post_path( $post ), $post->post_title, implode( ', ', $signals ) ), 1 );
		}

		$this->say( '' );
		$this->say( sprintf( 'Empty (no text, embed or image): set to draft + 301 to the nearest live course (%d):', count( $empty ) ) );
		foreach ( $empty as $id ) {
			$post    = $this->posts[ $id ];
			$path    = $this->post_path( $post );
			$formers = $this->former_courses( $id );
			$target  = $this->orphan_target( $post, $formers );
			$this->say( sprintf( '%s %d /%s "%s" -> /%s (%s)', self::TYPE_LABELS[ $post->post_type ], $id, $path, $post->post_title, $target[0], $target[1] ), 1 );
			// Redirect first, then draft: a run that stops in between leaves a
			// published post (found again by a re-run), never a dead URL.
			$this->plan_redirect( $path, home_url( '/' . $target[0] ), 2 );
			$this->plan_status( $post, 'draft', 2 );
		}
	}

	/**
	 * Steps placed in a live (not trashed) course, from LearnDash's
	 * ld_course_steps and from the theme's ACF outline written by seed.
	 *
	 * @return array<int, int> Step ID => course ID.
	 */
	private function placed() {
		$by_ld  = array();
		$by_acf = array();
		foreach ( $this->ids_of( 'sfwd-courses' ) as $course_id ) {
			if ( isset( $this->steps[ $course_id ] ) ) {
				$tree = $this->steps[ $course_id ];
				foreach ( $tree['quizzes'] as $quiz_id ) {
					$by_ld += array( $quiz_id => $course_id );
				}
				foreach ( $tree['lessons'] as $lesson ) {
					$by_ld += array( $lesson['id'] => $course_id );
					foreach ( $lesson['quizzes'] as $quiz_id ) {
						$by_ld += array( $quiz_id => $course_id );
					}
					foreach ( $lesson['topics'] as $topic ) {
						$by_ld += array( $topic['id'] => $course_id );
						foreach ( $topic['quizzes'] as $quiz_id ) {
							$by_ld += array( $quiz_id => $course_id );
						}
					}
				}
			}
			$final = (int) get_post_meta( $course_id, 'course_final_quiz', true );
			if ( $final ) {
				$by_acf += array( $final => $course_id );
			}
			foreach ( $this->id_list( get_post_meta( $course_id, 'course_lessons', true ) ) as $lesson_id ) {
				$by_acf += array( $lesson_id => $course_id );
				$quiz    = (int) get_post_meta( $lesson_id, 'lesson_quiz', true );
				if ( $quiz ) {
					$by_acf += array( $quiz => $course_id );
				}
				foreach ( $this->id_list( get_post_meta( $lesson_id, 'lesson_topics', true ) ) as $topic_id ) {
					$by_acf += array( $topic_id => $course_id );
					$quiz    = (int) get_post_meta( $topic_id, 'topic_quiz', true );
					if ( $quiz ) {
						$by_acf += array( $quiz => $course_id );
					}
				}
			}
		}
		if ( $by_acf ) {
			$only_ld  = array_diff( array_keys( $by_ld ), array_keys( $by_acf ) );
			$only_acf = array_diff( array_keys( $by_acf ), array_keys( $by_ld ) );
			if ( $only_ld || $only_acf ) {
				$this->note( sprintf( 'outline sources differ: only in LearnDash [%s], only in the ACF outline [%s]. Both count as placed.', implode( ',', $only_ld ), implode( ',', $only_acf ) ) );
			} else {
				$this->say( 'ACF outline (from seed) and LearnDash ld_course_steps place the same steps.' );
			}
		} else {
			$this->say( 'No ACF outline yet (seed not applied): placement comes from LearnDash ld_course_steps.' );
		}
		return $by_ld + $by_acf;
	}

	/**
	 * Why a post counts as having content. Empty array = empty post.
	 * Media and embeds count even without words (e.g. a lesson that is only a
	 * YouTube video), and so do shortcodes and dynamic blocks, whose output
	 * plain text checks cannot see.
	 *
	 * @param object $post Post row.
	 * @return string[]
	 */
	private function content_signals( $post ) {
		$content = (string) $post->post_content;
		$signals = array();
		$media   = array( '<iframe', 'wp:embed', '[embed', 'youtube', 'youtu.be', 'vimeo', '<img', 'wp:image', '<video', 'wp:video', '<audio', 'wp:audio', 'wp:gallery', 'wp:cover', 'wp:media-text', 'wp:file', '<object', '<embed' );
		foreach ( $media as $needle ) {
			if ( false !== stripos( $content, $needle ) ) {
				$signals[] = 'media "' . $needle . '"';
				break;
			}
		}
		if ( (int) get_post_meta( $post->ID, '_thumbnail_id', true ) ) {
			$signals[] = 'featured image';
		}
		if ( preg_match( '/\[[a-z][a-z0-9_-]*[\s\]\/]/i', $content ) ) {
			$signals[] = 'shortcode';
		}
		if ( preg_match_all( '#<!--\s+wp:([a-z0-9/_-]+)[^>]*?/-->#i', $content, $matches ) ) {
			$dynamic = array_diff( array_unique( $matches[1] ), array( 'spacer', 'separator', 'more', 'nextpage', 'core/spacer', 'core/separator', 'core/more', 'core/nextpage' ) );
			if ( $dynamic ) {
				$signals[] = 'block ' . implode( ', ', $dynamic );
			}
		}
		if ( preg_match( '#^\s*https?://\S+\s*$#mi', $content ) ) {
			$signals[] = 'bare URL (oEmbed)';
		}
		if ( 'sfwd-quiz' === $post->post_type && get_post_meta( $post->ID, 'ld_quiz_questions', true ) ) {
			$signals[] = 'quiz questions';
		}
		$text = preg_replace( '/<!--.*?-->/s', ' ', $content );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = trim( preg_replace( '/[\s\x{00A0}\x{200B}]+/u', ' ', $text ) );
		if ( '' !== $text ) {
			$signals[] = sprintf( 'text (%d words)', count( preg_split( '/\s+/', $text ) ) );
		}
		return $signals;
	}

	/**
	 * Deleted (or still live) courses a leftover belonged to: deleted courses'
	 * ld_course_steps, LearnDash's course_id meta and ld_course_<id> meta.
	 *
	 * @param int $id Post ID.
	 * @return int[]
	 */
	private function former_courses( $id ) {
		global $wpdb;
		$formers = array();
		foreach ( $this->steps as $course_id => $tree ) {
			if ( $this->is_type( $course_id, 'sfwd-courses' ) ) {
				continue;
			}
			if ( in_array( $id, $this->tree_ids( $tree ), true ) ) {
				$formers[] = $course_id;
			}
		}
		$meta = (int) get_post_meta( $id, 'course_id', true );
		if ( $meta ) {
			$formers[] = $meta;
		}
		$keys = $wpdb->get_col( $wpdb->prepare( "SELECT meta_key FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s", $id, $wpdb->esc_like( 'ld_course_' ) . '%' ) );
		foreach ( $keys as $key ) {
			if ( preg_match( '/^ld_course_(\d+)$/', $key, $m ) ) {
				$formers[] = (int) $m[1];
			}
		}
		return array_values( array_unique( $formers ) );
	}

	/**
	 * Target path for an empty leftover and the reason.
	 *
	 * @param object $post    Post row.
	 * @param int[]  $formers Former course IDs.
	 * @return array{0: string, 1: string}
	 */
	private function orphan_target( $post, $formers ) {
		foreach ( $formers as $course_id ) {
			if ( isset( self::FORMER_COURSES[ $course_id ] ) ) {
				return $this->live_course_path( self::FORMER_COURSES[ $course_id ], 'former course ' . $course_id );
			}
			if ( $this->is_type( $course_id, 'sfwd-courses' ) && 'publish' === $this->posts[ $course_id ]->post_status ) {
				return array( $this->post_path( $this->posts[ $course_id ] ), 'its course ' . $course_id . ' is still live' );
			}
		}
		if ( $formers ) {
			return array( $this->post_bases()['sfwd-courses'] . '/', 'former course ' . implode( ', ', $formers ) . ' was deleted, no successor' );
		}
		$haystack = $post->post_name . ' ' . $post->post_title;
		foreach ( self::KEYWORDS as $word => $key ) {
			if ( preg_match( '/(^|[^a-z])' . $word . '([^a-z]|$)/i', $haystack ) ) {
				return $this->live_course_path( $key, 'no former course recorded, title/slug mentions "' . $word . '"' );
			}
		}
		return array( $this->post_bases()['sfwd-courses'] . '/', 'no former course recorded' );
	}

	/**
	 * Path of one of the live successor courses, checked to exist.
	 *
	 * @param string $key Key of LIVE_COURSES.
	 * @param string $why Reason so far.
	 * @return array{0: string, 1: string}
	 */
	private function live_course_path( $key, $why ) {
		$slug = self::LIVE_COURSES[ $key ];
		foreach ( $this->ids_of( 'sfwd-courses' ) as $course_id ) {
			$course = $this->posts[ $course_id ];
			if ( $course->post_name === $slug && 'publish' === $course->post_status ) {
				return array( $this->post_path( $course ), $why );
			}
		}
		$this->note( sprintf( 'live course "%s" not found or not published: using the courses archive instead', $slug ), 2 );
		return array( $this->post_bases()['sfwd-courses'] . '/', $why . ', successor missing' );
	}

	/**
	 * 5c. Tidy-ups found while checking staging (2026-10-18):
	 * - older draft steps outside any course whose URLs bounce to the homepage
	 *   get a 301 to the closest live page (drafts still in a course outline,
	 *   e.g. Minswap V2's 27813 and 27819, are left alone so they can be
	 *   published later);
	 * - the post type bases and Augment Types' /<base>/archive/ URLs, which
	 *   bounce home or show an empty list, go to the course archive;
	 * - the newly public Eternl topic 28704 links "Governance Tools" to a
	 *   drafted DAO lesson: point it at the Eternl governance lesson.
	 */
	private function plan_tidy_ups() {
		$this->head( '5c. Tidy-ups: older draft URLs, base/archive URLs, one in-content link' );
		$placed  = $this->placed();
		$courses = home_url( '/' . $this->post_bases()['sfwd-courses'] . '/' );

		// Draft id => target post id.
		$drafts = array(
			26547 => 26554, // CardanoPress "Web Hosting" lesson -> the published Web Hosting topic.
			26556 => 26552, // CardanoPress installation/configuration/WASM/troubleshooting -> CardanoPress overview (as #40).
			26558 => 26552,
			26560 => 26552,
			26562 => 26552,
			27811 => 27739, // Minswap liquidity drafts -> Minswap V2 course.
			27821 => 27739,
		);
		foreach ( $drafts as $id => $target_id ) {
			$post   = isset( $this->posts[ $id ] ) ? $this->posts[ $id ] : null;
			$target = isset( $this->posts[ $target_id ] ) ? $this->posts[ $target_id ] : null;
			if ( ! $post || 'draft' !== $post->post_status ) {
				$this->note( sprintf( '%d is missing or no longer a draft: skipped', $id ), 1 );
				continue;
			}
			if ( isset( $placed[ $id ] ) ) {
				$this->note( sprintf( '%d is in a course outline: skipped (publish it instead)', $id ), 1 );
				continue;
			}
			if ( ! $target || 'publish' !== $target->post_status ) {
				$this->note( sprintf( '%d: target %d is missing or not published: skipped', $id, $target_id ), 1 );
				continue;
			}
			$this->say( sprintf( '%s %d "%s" [draft]', self::TYPE_LABELS[ $post->post_type ], $id, $post->post_title ), 1 );
			$this->plan_redirect( $this->post_path( $post ), home_url( '/' . $this->post_path( $target ) ), 2 );
		}

		global $wpdb;
		foreach ( $this->post_bases() as $type => $base ) {
			foreach ( array( $base . '/', $base . '/archive/' ) as $source ) {
				if ( 'sfwd-courses' === $type && $base . '/' === $source ) {
					continue; // The course archive itself.
				}
				$owner = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ('page','post') AND post_status = 'publish' LIMIT 1", trim( basename( $source ), '/' ) ) );
				if ( $owner && false === strpos( $source, '/archive/' ) ) {
					$this->note( sprintf( '/%s belongs to published post %d: left alone', $source, $owner ), 1 );
					continue;
				}
				$this->plan_redirect( $source, $courses, 1 );
			}
		}

		$from = 'href="' . home_url( '/' . $this->post_bases()['sfwd-lessons'] . '/governance-2/' ) . '" data-type="sfwd-lessons" data-id="27429"';
		$to   = isset( $this->posts[28742] ) && 'publish' === $this->posts[28742]->post_status
			? 'href="' . home_url( '/' . $this->post_path( $this->posts[28742] ) ) . '" data-type="sfwd-lessons" data-id="28742"'
			: '';
		if ( $to ) {
			$this->plan_content( 28704, $from, $to, '"Governance Tools" link moved from the drafted DAO lesson 27429 to the Eternl governance lesson 28742' );
		} else {
			$this->note( 'lesson 28742 is missing or not published: 28704 link left alone', 1 );
		}
	}

	/**
	 * 2. Old URLs that bounce to the homepage (Rank Math's 404 fallback wins
	 * over WordPress's own old-slug redirect on this site).
	 */
	private function plan_old_urls() {
		global $wpdb;
		$this->head( '2. Old URLs that bounce to the homepage' );
		$items = array(
			array( 'sfwd-lessons', 'performing-a-swap', 25099, false ),
			array( 'sfwd-topic', 'understanding-stake-pools', 28737, false ),
			array( 'sfwd-topic', 'network-status-and-errors', 28777, false ),
			array( 'sfwd-courses', 'minswap-the-complete-guide-to-using-the-minswap-decentralised-exchange', 25093, false ),
			array( 'sfwd-courses', 'beginners-guide-to-genius-yield', 26186, true ),
			array( 'sfwd-courses', 'building-with-cardanopress', 26552, true ),
			array( 'sfwd-courses', 'creating-and-running-a-dao', 0, true ),
			array( 'sfwd-courses', 'understanding-everything-vyfinance', 0, true ),
			array( 'sfwd-courses', 'dexhunter-dex-aggregator-for-optimised-swaps', 0, true ),
			// The deleted Nami course (26813), #52's old target: same lesson #52 is retargeted to.
			array( 'sfwd-courses', 'how-to-use-nami-wallet-cardano', 26816, true ),
		);
		$bases    = $this->post_bases();
		$gf_table = $wpdb->prefix . 'gf_entry';
		$has_gf   = $gf_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $gf_table ) );
		foreach ( $items as $item ) {
			list( $type, $slug, $target_id, $deleted ) = $item;
			$source = $bases[ $type ] . '/' . $slug . '/';
			$live   = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s AND post_status NOT IN ('trash','auto-draft','inherit') LIMIT 1", $type, $slug ) );
			if ( $live ) {
				$this->note( sprintf( '/%s belongs to existing post %d: skipped', $source, $live ), 1 );
				continue;
			}
			if ( $deleted ) {
				$evidence = 'deleted course';
				if ( $has_gf ) {
					$hits      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$gf_table} WHERE source_url LIKE %s", '%' . $wpdb->esc_like( '/' . $source ) . '%' ) ); // phpcs:ignore WordPress.DB.PreparedSQL
					$evidence .= sprintf( ', seen in %d form entries', $hits );
				}
			} else {
				$owners = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT pm.post_id FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_wp_old_slug' AND pm.meta_value = %s AND p.post_type = %s", $slug, $type ) ) );
				if ( ! in_array( $target_id, $owners, true ) ) {
					$this->note( sprintf( '/%s: _wp_old_slug "%s" belongs to [%s], not %d: skipped', $source, $slug, implode( ',', $owners ), $target_id ), 1 );
					continue;
				}
				$evidence = sprintf( 'old slug of %d, _wp_old_slug verified', $target_id );
			}
			if ( $target_id ) {
				if ( ! isset( $this->posts[ $target_id ] ) || 'publish' !== $this->posts[ $target_id ]->post_status ) {
					$this->note( sprintf( '/%s: target %d is missing or not published: skipped', $source, $target_id ), 1 );
					continue;
				}
				$url = home_url( '/' . $this->post_path( $this->posts[ $target_id ] ) );
			} else {
				$url = home_url( '/' . $bases['sfwd-courses'] . '/' );
			}
			$this->say( sprintf( '/%s (%s)', $source, $evidence ), 1 );
			$this->plan_redirect( $source, $url, 2 );
		}
	}

	/**
	 * 3. LearnDash utility pages: draft + 301.
	 */
	private function plan_utility_pages() {
		$this->head( '3. LearnDash utility pages' );
		$pages = array(
			array( 29398, 'registration', '/' ),
			array( 29399, 'registration-success', '/' ),
			array( 29400, 'reset-password', '/wp-login.php?action=lostpassword' ),
			array( 29401, 'profile', 24947 ),
			array( 24254, 'courses1', '/' . $this->post_bases()['sfwd-courses'] . '/' ),
			array( 29402, 'courses', null ),
		);
		foreach ( $pages as $item ) {
			list( $id, $slug, $to ) = $item;

			$page = get_post( $id );
			if ( ! $page || 'page' !== $page->post_type || $page->post_name !== $slug ) {
				$this->note( sprintf( 'page %d is missing or no longer /%s/: skipped', $id, $slug ), 1 );
				continue;
			}
			$path = get_page_uri( $page ) . '/';
			$this->say( sprintf( 'page %d /%s "%s" [%s]', $id, $path, $page->post_title, $page->post_status ), 1 );
			// The `courses` page shares its URL with the course archive, so drafting it leaves /courses/ alive.
			$this->plan_status( $page, 'draft', 2, null !== $to );
			if ( null === $to ) {
				$this->say( '- no redirect: the /courses/ archive serves this URL', 2 );
				continue;
			}
			if ( is_int( $to ) ) {
				$target = get_post( $to );
				if ( ! $target || 'publish' !== $target->post_status ) {
					$this->note( sprintf( 'target page %d is missing or not published: redirect skipped', $to ), 2 );
					continue;
				}
				$url = home_url( '/' . get_page_uri( $target ) . '/' );
			} else {
				$url = home_url( $to );
			}
			$this->plan_redirect( $path, $url, 2 );
		}
		$this->plan_groups_archive();
	}

	/**
	 * LearnDash's Groups archive (/groups/) is indexed today (empty, 200) and
	 * becomes a 404 once LearnDash is off: send it to the courses archive,
	 * unless a published group or a page/post now owns that URL.
	 */
	private function plan_groups_archive() {
		global $wpdb;
		$saved = get_option( 'learndash_settings_permalinks', array() );
		$base  = ( is_array( $saved ) && ! empty( $saved['groups'] ) && is_string( $saved['groups'] ) ) ? trim( $saved['groups'], '/' ) : 'groups';
		$this->say( sprintf( 'LearnDash Groups archive /%s/', $base ), 1 );
		$groups = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'groups' AND post_status = 'publish'" );
		if ( $groups ) {
			$this->note( sprintf( '%d published LearnDash groups exist: /%s/ left alone', $groups, $base ), 2 );
			return;
		}
		$owner = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type IN ('page','post') AND post_status = 'publish' LIMIT 1", $base ) );
		if ( $owner ) {
			$this->note( sprintf( '/%s/ belongs to published post %d: left alone', $base, $owner ), 2 );
			return;
		}
		$this->plan_redirect( $base . '/', home_url( '/' . $this->post_bases()['sfwd-courses'] . '/' ), 2 );
	}

	/**
	 * 4. LearnDash taxonomy term archives: 301.
	 */
	private function plan_term_urls() {
		global $wpdb;
		$this->head( '4. LearnDash taxonomy term URLs' );
		$saved = get_option( 'learndash_settings_permalinks_taxonomies', array() );
		$terms = array(
			array( 'ld_course_category', 'free', 'courses' ),
			array( 'ld_course_category', 'paid', 'courses' ),
			array( 'ld_course_tag', 'wallets', 'courses' ),
			array( 'ld_course_tag', 'beginner', 'courses' ),
			array( 'ld_topic_tag', 'staking', 'lace' ),
			array( 'ld_topic_tag', 'lace-wallet', 'lace' ),
			array( 'ld_quiz_category', 'genius-yield', 26291 ),
		);
		foreach ( $terms as $item ) {
			list( $taxonomy, $slug, $to ) = $item;

			$base   = ( is_array( $saved ) && ! empty( $saved[ $taxonomy ] ) ) ? trim( $saved[ $taxonomy ], '/' ) : self::TAX_BASES[ $taxonomy ];
			$term   = $wpdb->get_row( $wpdb->prepare( "SELECT t.term_id, tt.count FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE tt.taxonomy = %s AND t.slug = %s", $taxonomy, $slug ) );
			$source = $base . '/' . $slug . '/';
			if ( ! $term ) {
				$this->note( sprintf( '/%s: no %s term "%s" in the database: skipped', $source, $taxonomy, $slug ), 1 );
				continue;
			}
			if ( 26291 === $to ) {
				if ( ! isset( $this->posts[ $to ] ) || 'publish' !== $this->posts[ $to ]->post_status ) {
					$this->note( sprintf( '/%s: quiz %d is missing or not published: skipped', $source, $to ), 1 );
					continue;
				}
				$url = home_url( '/' . $this->post_path( $this->posts[ $to ] ) );
			} elseif ( 'lace' === $to ) {
				$url = home_url( '/' . $this->live_course_path( 'lace', '' )[0] );
			} else {
				$url = home_url( '/' . $this->post_bases()['sfwd-courses'] . '/' );
			}
			$this->say( sprintf( '/%s (%s term %d, %d posts)', $source, $taxonomy, $term->term_id, $term->count ), 1 );
			$this->plan_redirect( $source, $url, 2 );
		}
	}

	/**
	 * 5. Existing redirects that land on dead pages.
	 */
	private function plan_retargets() {
		$this->head( '5. Existing redirects that land on dead pages' );
		$items = array(
			array( 40, 'topic/enabling-cardano-serialisation-library-wasm-file', 26552 ),
			array( 52, 'courses/how-to-use-nami-wallet-cardano', 26816 ),
		);
		foreach ( $items as $item ) {
			list( $id, $dead, $target_id ) = $item;

			$row = $this->redirect_row( $id );
			if ( ! $row ) {
				$this->note( sprintf( 'redirect #%d not found (or trashed): skipped', $id ), 1 );
				continue;
			}
			if ( ! isset( $this->posts[ $target_id ] ) || 'publish' !== $this->posts[ $target_id ]->post_status ) {
				$this->note( sprintf( 'redirect #%d: new target %d is missing or not published: skipped', $id, $target_id ), 1 );
				continue;
			}
			$url     = home_url( '/' . $this->post_path( $this->posts[ $target_id ] ) );
			$current = trim( (string) wp_parse_url( $row['url_to'], PHP_URL_PATH ), '/' );
			$sources = implode( ', ', $this->source_patterns( $row ) );
			if ( $row['url_to'] === $url ) {
				$this->say( sprintf( '= #%d %s already points to %s', $id, $sources, $url ), 1 );
				continue;
			}
			if ( $current !== $dead ) {
				$this->note( sprintf( 'redirect #%d now points to %s, expected /%s/: left alone', $id, $row['url_to'], $dead ), 1 );
				continue;
			}
			$this->add_op(
				array(
					'op'     => 'redirect_update',
					'id'     => $id,
					'from'   => $row['url_to'],
					'url_to' => $url,
					'label'  => sprintf( 'redirect #%d %s: %s -> %s', $id, $sources, $row['url_to'], $url ),
				),
				1
			);
		}
	}

	/**
	 * 5b. Existing redirects that land on a post this run drafts would chain
	 * (old URL -> drafted post -> its new 301). Point them straight at the
	 * final target instead; rollback restores them like any other edit.
	 */
	private function plan_chain_retargets() {
		$this->head( '5b. Existing redirects that would chain through a newly drafted post' );
		$updating = array();
		foreach ( $this->ops as $op ) {
			if ( 'redirect_update' === $op['op'] ) {
				$updating[ $op['id'] ] = true;
			}
		}
		$home  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$found = 0;
		foreach ( $this->redirects() as $row ) {
			if ( 'active' !== $row['status'] || isset( $updating[ (int) $row['id'] ] ) ) {
				continue;
			}
			$host = strtolower( (string) wp_parse_url( $row['url_to'], PHP_URL_HOST ) );
			$path = trim( (string) wp_parse_url( $row['url_to'], PHP_URL_PATH ), '/' );
			if ( ( '' !== $host && $host !== $home ) || ! isset( $this->drafting[ $path ] ) ) {
				continue;
			}
			++$found;
			$sources = implode( ', ', $this->source_patterns( $row ) );
			$key     = strtolower( $path );
			$to      = isset( $this->redirect_to[ $key ] ) ? $this->redirect_to[ $key ] : '';
			$to_path = strtolower( trim( (string) wp_parse_url( $to, PHP_URL_PATH ), '/' ) );
			$own     = array_map(
				function ( $pattern ) {
					return strtolower( trim( $pattern, '/' ) );
				},
				$this->source_patterns( $row, true )
			);
			if ( '' === $to || in_array( $to_path, $own, true ) ) {
				$this->note( sprintf( '#%d %s -> /%s/ (post %d, drafted by this run): no usable 301 from that URL, left alone', $row['id'], $sources, $path, $this->drafting[ $path ] ), 1 );
				continue;
			}
			$this->add_op(
				array(
					'op'     => 'redirect_update',
					'id'     => (int) $row['id'],
					'from'   => $row['url_to'],
					'url_to' => $to,
					'label'  => sprintf( 'redirect #%d %s: %s -> %s (one hop instead of a chain through drafted post %d)', $row['id'], $sources, $row['url_to'], $to, $this->drafting[ $path ] ),
				),
				1
			);
		}
		if ( ! $found ) {
			$this->say( '= no existing redirect lands on a post this run drafts', 1 );
		}
	}

	/**
	 * New redirects must not land on a post this run drafts.
	 */
	private function check_targets() {
		foreach ( $this->ops as $i => $op ) {
			if ( ! in_array( $op['op'], array( 'redirect_create', 'redirect_update' ), true ) ) {
				continue;
			}
			$path = trim( (string) wp_parse_url( $op['url_to'], PHP_URL_PATH ), '/' );
			if ( isset( $this->drafting[ $path ] ) ) {
				$this->note( sprintf( 'not planned: %s would land on post %d, which this run sets to draft', $op['label'], $this->drafting[ $path ] ) );
				unset( $this->ops[ $i ] );
			}
		}
		$this->ops = array_values( $this->ops );
	}

	/**
	 * Report (don't change): duplicate exact sources.
	 */
	private function report_redirects() {
		$this->head( 'Redirect checks (report only)' );
		$by_source = array();
		foreach ( $this->redirects() as $row ) {
			foreach ( $this->source_patterns( $row, true ) as $pattern ) {
				// Keyed by redirect ID: a source repeated inside one redirect is not a clash.
				$by_source[ strtolower( trim( $pattern, '/' ) ) ][ (int) $row['id'] ] = $row;
			}
		}
		$dupes = 0;
		foreach ( $by_source as $source => $rows ) {
			if ( count( $rows ) < 2 ) {
				continue;
			}
			++$dupes;
			$list   = array();
			$winner = null;
			foreach ( $rows as $row ) {
				$list[] = sprintf( '#%d [%s] -> %s', $row['id'], $row['status'], $row['url_to'] );
				if ( ! $winner && 'active' === $row['status'] ) {
					$winner = $row;
				}
			}
			$this->say( sprintf( '! duplicate source /%s/ in %d redirects: %s', $source, count( $rows ), implode( '; ', $list ) ), 1 );
			if ( $winner ) {
				$this->say( sprintf( 'Rank Math uses the most recently updated active one: #%d -> %s', $winner['id'], $winner['url_to'] ), 2 );
			}
		}
		if ( ! $dupes ) {
			$this->say( '= no duplicate exact sources', 1 );
		}
		$this->note_count += $dupes;
	}

	/**
	 * 6. Park LearnDash's Course Navigation widget(s).
	 */
	private function plan_widgets() {
		$this->head( '6. LearnDash "Course Navigation" widget' );
		$sidebars = get_option( 'sidebars_widgets', array() );
		if ( ! is_array( $sidebars ) ) {
			$this->note( 'sidebars_widgets is not an array: skipped', 1 );
			return;
		}
		$area = isset( $sidebars['sidebar'] ) && is_array( $sidebars['sidebar'] ) ? $sidebars['sidebar'] : array();
		$move = array_values( preg_grep( '/^widget_ldcoursenavigation-\d+$/', $area ) );
		foreach ( $sidebars as $name => $widgets ) {
			if ( in_array( $name, array( 'sidebar', 'wp_inactive_widgets', 'array_version' ), true ) || ! is_array( $widgets ) ) {
				continue;
			}
			$other = preg_grep( '/^(widget_ld|ld_|learndash)/', $widgets );
			if ( $other ) {
				$this->note( sprintf( 'LearnDash widget(s) %s in area "%s": not moved (only "sidebar" is in scope)', implode( ', ', $other ), $name ), 1 );
			}
		}
		if ( ! $move ) {
			$this->say( '= no Course Navigation widget in the "sidebar" area', 1 );
			return;
		}
		$new            = $sidebars;
		$new['sidebar'] = array_values( array_diff( $area, $move ) );
		$inactive       = isset( $new['wp_inactive_widgets'] ) && is_array( $new['wp_inactive_widgets'] ) ? $new['wp_inactive_widgets'] : array();

		$new['wp_inactive_widgets'] = array_values( array_merge( $inactive, $move ) );
		$this->add_op(
			array(
				'op'     => 'option',
				'name'   => 'sidebars_widgets',
				'expect' => $sidebars,
				'value'  => $new,
				'label'  => sprintf( 'move %s from "sidebar" to wp_inactive_widgets', implode( ', ', $move ) ),
			),
			1
		);
	}

	/**
	 * Plan a post status change.
	 *
	 * @param object $post      Post (row or WP_Post).
	 * @param string $status    New status.
	 * @param int    $indent    Indent level.
	 * @param bool   $dead_path Whether the post's URL stops working (checked against redirect targets).
	 */
	private function plan_status( $post, $status, $indent, $dead_path = true ) {
		$path = $this->post_path( $post );
		if ( $status === $post->post_status ) {
			$this->say( sprintf( '= already %s', $status ), $indent );
		} else {
			$this->add_op(
				array(
					'op'      => 'status',
					'post_id' => (int) $post->ID,
					'to'      => $status,
					'label'   => sprintf( '%s %d /%s: %s -> %s', 'page' === $post->post_type ? 'page' : self::TYPE_LABELS[ $post->post_type ], $post->ID, $path, $post->post_status, $status ),
				),
				$indent
			);
		}
		if ( 'draft' === $status && $dead_path ) {
			$this->drafting[ trim( $path, '/' ) ] = (int) $post->ID;
		}
	}

	/**
	 * Plan a new Rank Math 301 unless the source already redirects. Sources
	 * are stored as Rank Math does: path, no leading slash, trailing slash.
	 *
	 * @param string $source Source path.
	 * @param string $url_to Absolute target URL (built with home_url()).
	 * @param int    $indent Indent level.
	 */
	private function plan_redirect( $source, $url_to, $indent ) {
		$source = ltrim( $source, '/' );
		$key    = strtolower( trim( $source, '/' ) );
		if ( isset( $this->planned_sources[ $key ] ) ) {
			$this->note( sprintf( '/%s is already planned earlier in this run: not duplicated', $source ), $indent );
			return;
		}
		$existing = $this->find_redirect( $source );
		if ( $existing ) {
			if ( 'active' === $existing['status'] ) {
				$this->redirect_to[ $key ] = $existing['url_to'];
				++$this->kept_redirects;
				$this->say( sprintf( '= 301 /%s already handled by redirect #%d -> %s (kept)', $source, $existing['id'], $existing['url_to'] ), $indent );
			} else {
				$this->note( sprintf( '/%s is a source of %s redirect #%d -> %s: not creating a duplicate (activate or edit #%d instead)', $source, $existing['status'], $existing['id'], $existing['url_to'], $existing['id'] ), $indent );
			}
			return;
		}
		if ( trim( (string) wp_parse_url( $url_to, PHP_URL_PATH ), '/' ) === $key ) {
			$this->note( sprintf( '/%s would redirect to itself: skipped', $source ), $indent );
			return;
		}
		$this->planned_sources[ $key ] = true;
		$this->redirect_to[ $key ]     = $url_to;
		$this->add_op(
			array(
				'op'     => 'redirect_create',
				'source' => $source,
				'url_to' => $url_to,
				'label'  => sprintf( '301 /%s -> %s', $source, $url_to ),
			),
			$indent
		);
	}

	/* ------------------------------------------------------------------ */
	/* Phase: rollback                                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Plan the undo of every write recorded in a backup file, newest first.
	 */
	private function plan_rollback() {
		$file = $this->backup_in;
		if ( 0 === strpos( $file, '~/' ) ) {
			$file = $this->home_dir() . substr( $file, 1 );
		}
		$this->head( 'Rollback from ' . $file );
		$data = $this->read_json( $file );
		if ( ! $data || empty( $data['ops'] ) || ! is_array( $data['ops'] ) ) {
			WP_CLI::error( 'Backup file missing, unreadable or without operations: ' . $file );
		}
		if ( ( isset( $data['site'] ) ? $data['site'] : '' ) !== home_url() || ( isset( $data['abspath'] ) ? $data['abspath'] : '' ) !== ABSPATH ) {
			WP_CLI::error( sprintf( 'This backup belongs to %s (%s), not to this site %s (%s).', isset( $data['site'] ) ? $data['site'] : '?', isset( $data['abspath'] ) ? $data['abspath'] : '?', home_url(), ABSPATH ) );
		}
		$this->say( sprintf( 'Backup of phase "%s" started %s, last updated %s, %d operations.', isset( $data['phase'] ) ? $data['phase'] : '?', isset( $data['started_utc'] ) ? $data['started_utc'] : '?', isset( $data['updated_utc'] ) ? $data['updated_utc'] : '?', count( $data['ops'] ) ) );
		foreach ( array_reverse( $data['ops'] ) as $op ) {
			$state = isset( $op['state'] ) ? $op['state'] : 'planned';
			// "running": the run stopped during this write. Restoring "before" is safe even if nothing changed.
			if ( ! in_array( $state, array( 'done', 'failed', 'running' ), true ) ) {
				continue;
			}
			$inverse = $this->inverse( $op );
			if ( $inverse ) {
				$this->add_op( $inverse, 1 );
			} else {
				$this->note( 'nothing to undo for: ' . ( isset( $op['label'] ) ? $op['label'] : $op['op'] ), 1 );
			}
		}
	}

	/**
	 * The operation that undoes a recorded one, from its "before" snapshot.
	 *
	 * @param array $op Recorded operation.
	 * @return array|null
	 */
	private function inverse( $op ) {
		$before = array_key_exists( 'before', $op ) ? $op['before'] : null;
		$label  = isset( $op['label'] ) ? $op['label'] : $op['op'];
		switch ( $op['op'] ) {
			case 'meta':
			case 'meta_restore':
				$rows = is_array( $before ) ? $before : array();
				return array(
					'op'      => 'meta_restore',
					'post_id' => (int) $op['post_id'],
					'names'   => $op['names'],
					'rows'    => $rows,
					'label'   => $rows
						? sprintf( 'restore %d earlier meta rows of %s on post %d  [undo: %s]', count( $rows ), implode( ', ', $op['names'] ), $op['post_id'], $label )
						: sprintf( 'remove %s from post %d (not set before)  [undo: %s]', implode( ', ', $op['names'] ), $op['post_id'], $label ),
				);
			case 'status':
				if ( ! is_array( $before ) ) {
					return null;
				}
				return array(
					'op'        => 'status',
					'post_id'   => (int) $op['post_id'],
					'to'        => $before['post_status'],
					'post_name' => $before['post_name'],
					'label'     => sprintf( 'post %d back to %s  [undo: %s]', $op['post_id'], $before['post_status'], $label ),
				);
			case 'content':
			case 'content_restore':
				if ( ! is_array( $before ) ) {
					return null;
				}
				return array(
					'op'      => 'content_restore',
					'post_id' => (int) $op['post_id'],
					'content' => $before,
					'label'   => sprintf( 'restore content of post %d  [undo: %s]', $op['post_id'], $label ),
				);
			case 'redirect_create':
				$id = empty( $op['result']['id'] ) ? 0 : (int) $op['result']['id'];
				if ( ! $id && isset( $op['state'] ) && 'running' === $op['state'] ) {
					// Stopped before its ID was recorded: the source had no redirect before, so a match is this one.
					$row = $this->find_redirect( $op['source'] );
					if ( $row && $row['url_to'] === $op['url_to'] && in_array( $op['source'], $this->source_patterns( $row, true ), true ) ) {
						$id = (int) $row['id'];
					}
				}
				if ( ! $id ) {
					return null;
				}
				return array(
					'op'     => 'redirect_delete',
					'id'     => $id,
					'source' => $op['source'],
					'label'  => sprintf( 'delete redirect #%d (/%s)  [undo: %s]', $id, $op['source'], $label ),
				);
			case 'redirect_update':
			case 'redirect_restore':
			case 'redirect_delete':
				if ( ! is_array( $before ) ) {
					if ( 'redirect_restore' !== $op['op'] ) {
						return null;
					}
					return array(
						'op'     => 'redirect_delete',
						'id'     => (int) $op['id'],
						'source' => '',
						'label'  => sprintf( 'delete redirect #%d  [undo: %s]', $op['id'], $label ),
					);
				}
				return array(
					'op'    => 'redirect_restore',
					'id'    => (int) $before['id'],
					'row'   => $before,
					'label' => sprintf( 'restore redirect #%d -> %s [%s]  [undo: %s]', $before['id'], $before['url_to'], $before['status'], $label ),
				);
			case 'option':
				return array(
					'op'    => 'option',
					'name'  => $op['name'],
					'value' => $before,
					'label' => sprintf( 'restore option %s  [undo: %s]', $op['name'], $label ),
				);
		}
		return null;
	}

	/* ------------------------------------------------------------------ */
	/* Apply                                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Refuse to write when a precondition is missing. Nothing is written yet.
	 */
	private function guard_apply() {
		$types = array_unique( wp_list_pluck( $this->ops, 'op' ) );
		if ( ( 'seed' === $this->phase || in_array( 'meta', $types, true ) ) && ! $this->acf_ready ) {
			WP_CLI::error( "ACF course field groups are not loaded (acf_get_field( 'field_lcc_course_lessons' ) or another lcc field is missing). Copy acf-json/group_lcc_*.json next to migrate.php (README step 0). Nothing was written." );
		}
		if ( 'cutover' === $this->phase && $this->ld_active && ! $this->force_ld ) {
			WP_CLI::error( 'LearnDash (sfwd-lms) is still active. Deactivate it first, or add force-with-ld. Nothing was written.' );
		}
		if ( array_intersect( $types, array( 'redirect_create', 'redirect_update', 'redirect_restore', 'redirect_delete' ) ) && ! $this->rm_ready ) {
			WP_CLI::error( 'Rank Math redirections API is not available (is the Redirections module on?). Nothing was written.' );
		}
		if ( '' === $this->home_dir() ) {
			WP_CLI::error( 'HOME is not set, so there is nowhere to write the backup. Nothing was written.' );
		}
	}

	/**
	 * Snapshot every value about to change and write the backup/journal.
	 */
	private function start_backup() {
		$dir = $this->home_dir() . '/claude-backups';
		if ( ! wp_mkdir_p( $dir ) ) {
			WP_CLI::error( 'Cannot create ' . $dir . '. Nothing was written.' );
		}
		$this->journal_file = sprintf( '%s/courses-migration-%s-%s.json', $dir, $this->phase, gmdate( 'Ymd\THis\Z' ) );
		foreach ( $this->ops as $i => $op ) {
			$this->ops[ $i ]['before'] = $this->snapshot( $op );
			$this->ops[ $i ]['state']  = 'planned';
		}
		$this->write_journal();
		$this->say( '' );
		$this->say( 'Backup written before any change: ' . $this->journal_file );
		$this->say( 'Undo with: wp eval-file ' . $this->dir . '/migrate.php rollback ' . $this->journal_file . ' apply --path=' . untrailingslashit( ABSPATH ) );
	}

	/**
	 * Current value of everything an operation changes.
	 *
	 * @param array $op Operation.
	 * @return mixed
	 */
	private function snapshot( $op ) {
		global $wpdb;
		switch ( $op['op'] ) {
			case 'meta':
			case 'meta_restore':
				return $this->meta_rows( $op['post_id'], $op['names'] );
			case 'status':
				return $wpdb->get_row( $wpdb->prepare( "SELECT post_status, post_name FROM {$wpdb->posts} WHERE ID = %d", $op['post_id'] ), ARRAY_A );
			case 'content':
			case 'content_restore':
				return $wpdb->get_row( $wpdb->prepare( "SELECT post_content, post_modified, post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d", $op['post_id'] ), ARRAY_A );
			case 'redirect_update':
			case 'redirect_restore':
			case 'redirect_delete':
				return $this->redirect_raw( $op['id'] );
			case 'option':
				return get_option( $op['name'], null );
		}
		return null;
	}

	/**
	 * Run every planned operation, journaling after each one.
	 */
	private function execute() {
		// WP-CLI runs as no user, so kses would filter saved HTML; write it as an admin with unfiltered_html would.
		kses_remove_filters();
		$this->say( '' );
		$this->head( 'Applying' );
		$tally = array(
			'done'    => 0,
			'skipped' => 0,
			'failed'  => 0,
		);
		foreach ( $this->ops as $i => $op ) {
			// Journal the op as running first, so a run killed mid-write (SSH drop,
			// fatal error) can still be rolled back from its "before" snapshot.
			$this->ops[ $i ]['state'] = 'running';
			$this->write_journal();
			try {
				$result = $this->exec_op( $op );
			} catch ( \Throwable $e ) {
				$result = array(
					'state'   => 'failed',
					'message' => get_class( $e ) . ': ' . $e->getMessage(),
				);
			}
			$this->ops[ $i ]['state']   = $result['state'];
			$this->ops[ $i ]['message'] = isset( $result['message'] ) ? $result['message'] : '';
			if ( isset( $result['result'] ) ) {
				$this->ops[ $i ]['result'] = $result['result'];
			}
			$this->write_journal();
			++$tally[ $result['state'] ];
			$mark = array(
				'done'    => 'ok',
				'skipped' => 'SKIPPED',
				'failed'  => 'FAILED',
			);
			$this->say( sprintf( '[%s] %s%s', $mark[ $result['state'] ], $op['label'], $this->ops[ $i ]['message'] ? ' (' . $this->ops[ $i ]['message'] . ')' : '' ), 1 );
		}
		// The theme caches the course map in a transient; rebuild it on the next request.
		delete_transient( 'lc_course_map' );

		$this->say( '' );
		$this->say( sprintf( 'Applied: %d done, %d skipped, %d failed. Backup/journal: %s', $tally['done'], $tally['skipped'], $tally['failed'], $this->journal_file ) );
		$this->say( 'Purge caches afterwards (wp sg purge) so visitors see the changes.' );
		if ( $tally['failed'] ) {
			WP_CLI::error( 'Some operations failed (see above). Undo everything with the rollback command printed above, or fix the cause and re-run the same phase.' );
		}
		WP_CLI::success( 'Done.' );
	}

	/**
	 * Run one operation.
	 *
	 * @param array $op Operation.
	 * @return array{state: string, message?: string, result?: array}
	 */
	private function exec_op( $op ) {
		global $wpdb;
		switch ( $op['op'] ) {
			case 'meta':
				foreach ( $op['fields'] as $name => $value ) {
					update_field( self::FIELDS[ $name ], $value, (int) $op['post_id'] );
				}
				$bad = array();
				foreach ( $op['fields'] as $name => $value ) {
					if ( ! $this->field_matches( $op['post_id'], $name, $value ) ) {
						$bad[] = $name;
					}
				}
				return $bad ? $this->failed( 'read-back mismatch: ' . implode( ', ', $bad ) ) : $this->ok();

			case 'meta_restore':
				$this->delete_meta_rows( $op['post_id'], $op['names'] );
				foreach ( $op['rows'] as $row ) {
					$wpdb->insert(
						$wpdb->postmeta,
						array(
							'post_id'    => (int) $op['post_id'],
							'meta_key'   => $row['meta_key'], // phpcs:ignore WordPress.DB.SlowDBQuery
							'meta_value' => $row['meta_value'], // phpcs:ignore WordPress.DB.SlowDBQuery
						),
						array( '%d', '%s', '%s' )
					);
				}
				wp_cache_delete( (int) $op['post_id'], 'post_meta' );
				return count( $this->meta_rows( $op['post_id'], $op['names'] ) ) === count( $op['rows'] ) ? $this->ok() : $this->failed( 'row count differs after restore' );

			case 'status':
				$current = $wpdb->get_row( $wpdb->prepare( "SELECT post_status, post_name FROM {$wpdb->posts} WHERE ID = %d", $op['post_id'] ) );
				if ( ! $current ) {
					return $this->failed( 'post not found' );
				}
				if ( $current->post_status !== $op['to'] ) {
					$res = wp_update_post(
						array(
							'ID'          => (int) $op['post_id'],
							'post_status' => $op['to'],
						),
						true
					);
					if ( is_wp_error( $res ) ) {
						return $this->failed( $res->get_error_message() );
					}
				}
				if ( ! empty( $op['post_name'] ) && get_post_field( 'post_name', (int) $op['post_id'], 'raw' ) !== $op['post_name'] ) {
					$wpdb->update( $wpdb->posts, array( 'post_name' => $op['post_name'] ), array( 'ID' => (int) $op['post_id'] ), array( '%s' ), array( '%d' ) );
				}
				clean_post_cache( (int) $op['post_id'] );
				return get_post_status( (int) $op['post_id'] ) === $op['to'] ? $this->ok() : $this->failed( 'status is ' . get_post_status( (int) $op['post_id'] ) );

			case 'content':
				$content = (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $op['post_id'] ) );
				if ( 1 !== substr_count( $content, $op['search'] ) ) {
					return $this->skipped( 'text no longer occurs exactly once' );
				}
				return $this->write_content( $op['post_id'], str_replace( $op['search'], $op['replace'], $content ) );

			case 'content_restore':
				return $this->write_content( $op['post_id'], $op['content']['post_content'], $op['content']['post_modified'], $op['content']['post_modified_gmt'] );

			case 'redirect_create':
				$this->redirect_rows = null;
				$existing            = $this->find_redirect( $op['source'] );
				if ( $existing ) {
					return $this->skipped( sprintf( 'source now handled by #%d', $existing['id'] ) );
				}
				$redirection = \RankMath\Redirections\Redirection::from(
					array(
						'sources'     => array(
							array(
								'pattern'    => $op['source'],
								'comparison' => 'exact',
								'ignore'     => '',
							),
						),
						'url_to'      => $op['url_to'],
						'header_code' => '301',
						'status'      => 'active',
					)
				);
				$redirection->set_nocache( true );
				if ( $redirection->is_infinite_loop() ) {
					return $this->failed( 'Rank Math reports an infinite loop' );
				}
				$id = (int) $redirection->save();
				if ( ! $id ) {
					return $this->failed( 'Rank Math did not save the redirect' );
				}
				$this->redirect_rows = null;
				$row                 = $this->redirect_raw( $id );
				$check               = $row ? $this->source_patterns( $row, true ) : array();
				if ( ! $row || $row['url_to'] !== $op['url_to'] || ! in_array( $op['source'], $check, true ) ) {
					return $this->failed( 'saved redirect #' . $id . ' does not match', array( 'id' => $id ) );
				}
				return $this->ok( array( 'id' => $id ) );

			case 'redirect_update':
				$row = \RankMath\Redirections\DB::get_redirection_by_id( (int) $op['id'] );
				if ( ! $row ) {
					return $this->failed( 'redirect not found' );
				}
				if ( $row['url_to'] !== $op['from'] ) {
					return $this->skipped( 'target changed since planning: ' . $row['url_to'] );
				}
				$redirection = \RankMath\Redirections\Redirection::from(
					array(
						'id'          => (int) $op['id'],
						'sources'     => $row['sources'],
						'url_to'      => $op['url_to'],
						'header_code' => $row['header_code'],
						'status'      => $row['status'],
					)
				);
				$redirection->set_nocache( true );
				if ( $redirection->is_infinite_loop() ) {
					return $this->failed( 'Rank Math reports an infinite loop' );
				}
				$redirection->save();
				$after = $this->redirect_raw( (int) $op['id'] );
				return ( $after && $after['url_to'] === $op['url_to'] ) ? $this->ok() : $this->failed( 'url_to not updated' );

			case 'redirect_delete':
				$row = $this->redirect_raw( (int) $op['id'] );
				if ( ! $row ) {
					return $this->ok( null, 'already gone' );
				}
				if ( '' !== $op['source'] && ! in_array( $op['source'], $this->source_patterns( $row, true ), true ) ) {
					return $this->skipped( 'redirect #' . $op['id'] . ' no longer has source /' . $op['source'] );
				}
				\RankMath\Redirections\DB::delete( array( (int) $op['id'] ) );
				return $this->redirect_raw( (int) $op['id'] ) ? $this->failed( 'still present' ) : $this->ok();

			case 'redirect_restore':
				$row = $op['row'];
				if ( $this->redirect_raw( (int) $row['id'] ) ) {
					\RankMath\Redirections\DB::update(
						array(
							'id'          => (int) $row['id'],
							'sources'     => maybe_unserialize( $row['sources'] ),
							'url_to'      => $row['url_to'],
							'header_code' => $row['header_code'],
							'status'      => $row['status'],
							'updated'     => $row['updated'],
						)
					);
				} else {
					// Re-create with its original ID so anything pointing at #id still matches.
					$wpdb->insert( $wpdb->prefix . 'rank_math_redirections', $row );
				}
				$after = $this->redirect_raw( (int) $row['id'] );
				return ( $after && $after['url_to'] === $row['url_to'] && $after['status'] === $row['status'] ) ? $this->ok() : $this->failed( 'redirect not restored' );

			case 'option':
				if ( array_key_exists( 'expect', $op ) && maybe_serialize( get_option( $op['name'], null ) ) !== maybe_serialize( $op['expect'] ) ) {
					return $this->skipped( 'option changed since planning' );
				}
				update_option( $op['name'], $op['value'] );
				return maybe_serialize( get_option( $op['name'], null ) ) === maybe_serialize( $op['value'] ) ? $this->ok() : $this->failed( 'option not saved' );
		}
		return $this->failed( 'unknown operation ' . $op['op'] );
	}

	/**
	 * Write post content directly: no hooks, so LearnDash (active during
	 * seed) cannot react to a save, and no kses.
	 *
	 * @param int         $post_id      Post ID.
	 * @param string      $content      New content.
	 * @param string|null $modified     Restore this post_modified (rollback).
	 * @param string|null $modified_gmt Restore this post_modified_gmt (rollback).
	 * @return array
	 */
	private function write_content( $post_id, $content, $modified = null, $modified_gmt = null ) {
		global $wpdb;
		$saved = $wpdb->update(
			$wpdb->posts,
			array(
				'post_content'      => $content,
				'post_modified'     => $modified ? $modified : current_time( 'mysql' ),
				'post_modified_gmt' => $modified_gmt ? $modified_gmt : current_time( 'mysql', true ),
			),
			array( 'ID' => (int) $post_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
		clean_post_cache( (int) $post_id );
		if ( false === $saved ) {
			return $this->failed( $wpdb->last_error );
		}
		$after = (string) $wpdb->get_var( $wpdb->prepare( "SELECT post_content FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
		return $after === $content ? $this->ok() : $this->failed( 'content differs after write' );
	}

	/**
	 * Write the backup/journal file (rewritten after every operation).
	 */
	private function write_journal() {
		$data = array(
			'tool'          => 'learncardano-cardanopress-theme-child/tools/courses/migrate.php',
			'version'       => self::VERSION,
			'phase'         => $this->phase,
			'site'          => home_url(),
			'abspath'       => ABSPATH,
			'started_utc'   => $this->started_utc,
			'updated_utc'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'source_backup' => $this->backup_in,
			'note'          => 'Each op holds "before" (the value before the write) and "state". Undo: wp eval-file migrate.php rollback <this file> apply --path=...',
			'ops'           => $this->ops,
		);
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json || false === file_put_contents( $this->journal_file, $json, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$written = count( array_intersect( wp_list_pluck( $this->ops, 'state' ), array( 'done', 'failed' ) ) );
			WP_CLI::error( 'Could not write the backup file ' . $this->journal_file . ( $written ? sprintf( ' (%d changes were already written; see output above)', $written ) : '. Nothing was written.' ) );
		}
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Result helpers.
	 *
	 * @param array|null $result  Extra result data.
	 * @param string     $message Message.
	 * @return array
	 */
	private function ok( $result = null, $message = '' ) {
		$out = array(
			'state'   => 'done',
			'message' => $message,
		);
		if ( null !== $result ) {
			$out['result'] = $result;
		}
		return $out;
	}

	/**
	 * Failed result.
	 *
	 * @param string     $message Why.
	 * @param array|null $result  Extra result data.
	 * @return array
	 */
	private function failed( $message, $result = null ) {
		$out = array(
			'state'   => 'failed',
			'message' => $message,
		);
		if ( null !== $result ) {
			$out['result'] = $result;
		}
		return $out;
	}

	/**
	 * Skipped result.
	 *
	 * @param string $message Why.
	 * @return array
	 */
	private function skipped( $message ) {
		return array(
			'state'   => 'skipped',
			'message' => $message,
		);
	}

	/**
	 * Does the stored meta already hold this value?
	 *
	 * @param int    $post_id Post ID.
	 * @param string $name    Field name.
	 * @param mixed  $value   Wanted value (IDs, number, or repeater rows).
	 * @return bool
	 */
	private function field_matches( $post_id, $name, $value ) {
		$raw = get_post_meta( (int) $post_id, $name, true );
		if ( in_array( $name, self::REPEATER_FIELDS, true ) ) {
			return (int) $raw === count( (array) $value );
		}
		if ( in_array( $name, self::LIST_FIELDS, true ) ) {
			return $this->id_list( $raw ) === array_values( array_map( 'intval', (array) $value ) );
		}
		return (int) $raw === (int) $value;
	}

	/**
	 * IDs from a relationship meta value.
	 *
	 * @param mixed $raw Meta value.
	 * @return int[]
	 */
	private function id_list( $raw ) {
		$value = maybe_unserialize( $raw );
		return is_array( $value ) ? array_values( array_filter( array_map( 'intval', $value ) ) ) : array();
	}

	/**
	 * Meta rows ACF uses for these fields: name, _name, name_* and _name_*.
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $names   Field names.
	 * @return array[]
	 */
	private function meta_rows( $post_id, $names ) {
		global $wpdb;
		list( $where, $params ) = $this->meta_where( $post_id, $names );
		return $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE {$where} ORDER BY meta_id", $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/**
	 * Delete the meta rows of these fields.
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $names   Field names.
	 */
	private function delete_meta_rows( $post_id, $names ) {
		global $wpdb;
		list( $where, $params ) = $this->meta_where( $post_id, $names );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE {$where}", $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		wp_cache_delete( (int) $post_id, 'post_meta' );
	}

	/**
	 * WHERE clause for meta_rows() / delete_meta_rows().
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $names   Field names.
	 * @return array{0: string, 1: array}
	 */
	private function meta_where( $post_id, $names ) {
		global $wpdb;
		$parts  = array();
		$params = array( (int) $post_id );
		foreach ( (array) $names as $name ) {
			$parts[] = 'meta_key = %s OR meta_key = %s OR meta_key LIKE %s OR meta_key LIKE %s';
			array_push( $params, $name, '_' . $name, $wpdb->esc_like( $name . '_' ) . '%', $wpdb->esc_like( '_' . $name . '_' ) . '%' );
		}
		return array( 'post_id = %d AND ( ' . implode( ' OR ', $parts ) . ' )', $params );
	}

	/**
	 * Non-trashed Rank Math redirects, most recently updated first (the order
	 * Rank Math matches in), with sources unserialized.
	 *
	 * @return array[]
	 */
	private function redirects() {
		global $wpdb;
		if ( null !== $this->redirect_rows ) {
			return $this->redirect_rows;
		}
		$this->redirect_rows = array();
		$table               = $wpdb->prefix . 'rank_math_redirections';
		if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return $this->redirect_rows;
		}
		$rows = $wpdb->get_results( "SELECT * FROM {$table} WHERE status <> 'trashed' ORDER BY updated DESC, id DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $rows as $row ) {
			$row['sources_list']   = maybe_unserialize( $row['sources'] );
			$this->redirect_rows[] = $row;
		}
		return $this->redirect_rows;
	}

	/**
	 * One redirect row (not trashed) from the cached list.
	 *
	 * @param int $id Redirect ID.
	 * @return array|null
	 */
	private function redirect_row( $id ) {
		foreach ( $this->redirects() as $row ) {
			if ( (int) $row['id'] === (int) $id ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Raw redirect row straight from the table (any status).
	 *
	 * @param int $id Redirect ID.
	 * @return array|null
	 */
	private function redirect_raw( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
		return $row ? $row : null;
	}

	/**
	 * Source patterns of a redirect row.
	 *
	 * @param array $row        Row (raw or from redirects()).
	 * @param bool  $exact_only Only exact-comparison sources.
	 * @return string[]
	 */
	private function source_patterns( $row, $exact_only = false ) {
		$list = isset( $row['sources_list'] ) ? $row['sources_list'] : maybe_unserialize( $row['sources'] );
		$out  = array();
		foreach ( (array) $list as $source ) {
			if ( ! is_array( $source ) || ! isset( $source['pattern'] ) ) {
				continue;
			}
			$comparison = isset( $source['comparison'] ) ? $source['comparison'] : 'exact';
			if ( $exact_only && 'exact' !== $comparison ) {
				continue;
			}
			$pattern = wp_specialchars_decode( $source['pattern'] );
			$out[]   = $exact_only ? $pattern : $pattern . ( 'exact' === $comparison ? '' : ' (' . $comparison . ')' );
		}
		return $out;
	}

	/**
	 * Existing redirect that already handles a path: an exact source with
	 * the same path (any status but trashed), or an active one that Rank Math
	 * itself would match.
	 *
	 * @param string $path Path.
	 * @return array|null
	 */
	private function find_redirect( $path ) {
		$uri = trim( $path, '/' );
		foreach ( $this->redirects() as $row ) {
			foreach ( $this->source_patterns( $row, true ) as $pattern ) {
				if ( strtolower( trim( $pattern, '/' ) ) === strtolower( $uri ) ) {
					return $row;
				}
			}
		}
		if ( $this->rm_ready ) {
			foreach ( $this->redirects() as $row ) {
				if ( 'active' === $row['status'] && is_array( $row['sources_list'] ) && \RankMath\Redirections\DB::compare_sources( $row['sources_list'], $uri ) ) {
					return $row;
				}
			}
		}
		return null;
	}

	/**
	 * URL bases for the course post types: LearnDash's saved settings, or
	 * the theme's defaults.
	 *
	 * @return array<string, string>
	 */
	private function post_bases() {
		static $bases = null;
		if ( null !== $bases ) {
			return $bases;
		}
		$bases = self::POST_BASES;
		$saved = get_option( 'learndash_settings_permalinks', array() );
		$keys  = array(
			'sfwd-courses' => 'courses',
			'sfwd-lessons' => 'lessons',
			'sfwd-topic'   => 'topics',
			'sfwd-quiz'    => 'quizzes',
		);
		foreach ( $keys as $type => $key ) {
			if ( is_array( $saved ) && ! empty( $saved[ $key ] ) && is_string( $saved[ $key ] ) ) {
				$bases[ $type ] = trim( $saved[ $key ], '/' );
			}
		}
		return $bases;
	}

	/**
	 * Path of a course-type post or page: no leading slash, trailing slash
	 * (flat URLs; independent of whether the post type is registered).
	 *
	 * @param object $post Post row or WP_Post.
	 * @return string
	 */
	private function post_path( $post ) {
		$bases = $this->post_bases();
		if ( isset( $bases[ $post->post_type ] ) ) {
			return $bases[ $post->post_type ] . '/' . $post->post_name . '/';
		}
		if ( 'page' === $post->post_type ) {
			return get_page_uri( $post ) . '/';
		}
		return ltrim( (string) wp_parse_url( get_permalink( $post ), PHP_URL_PATH ), '/' );
	}

	/**
	 * All IDs of a step tree.
	 *
	 * @param array $tree Parsed ld_course_steps.
	 * @return int[]
	 */
	private function tree_ids( $tree ) {
		$ids = $tree['quizzes'];
		foreach ( $tree['lessons'] as $lesson ) {
			$ids[] = $lesson['id'];
			$ids   = array_merge( $ids, $lesson['quizzes'] );
			foreach ( $lesson['topics'] as $topic ) {
				$ids[] = $topic['id'];
				$ids   = array_merge( $ids, $topic['quizzes'] );
			}
		}
		return $ids;
	}

	/**
	 * IDs of loaded posts of a type.
	 *
	 * @param string $type Post type.
	 * @return int[]
	 */
	private function ids_of( $type ) {
		$ids = array();
		foreach ( $this->posts as $id => $post ) {
			if ( $type === $post->post_type ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Is this a loaded (not trashed) post of that type?
	 *
	 * @param int    $id   Post ID.
	 * @param string $type Post type.
	 * @return bool
	 */
	private function is_type( $id, $type ) {
		return isset( $this->posts[ $id ] ) && $type === $this->posts[ $id ]->post_type;
	}

	/**
	 * " [draft]" for unpublished steps (the theme skips them, the outline keeps them).
	 *
	 * @param int $id Post ID.
	 * @return string
	 */
	private function status_tag( $id ) {
		return 'publish' === $this->posts[ $id ]->post_status ? '' : ' [' . $this->posts[ $id ]->post_status . ']';
	}

	/**
	 * Decode a JSON file.
	 *
	 * @param string $file Path.
	 * @return array|null
	 */
	private function read_json( $file ) {
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$data = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return is_array( $data ) ? $data : null;
	}

	/**
	 * Home directory of the account running WP-CLI.
	 *
	 * @return string
	 */
	private function home_dir() {
		$home = getenv( 'HOME' );
		return $home ? untrailingslashit( $home ) : '';
	}

	/**
	 * Print the planned-writes summary.
	 */
	private function summary() {
		$this->head( 'Summary' );
		foreach ( $this->facts as $fact ) {
			$this->say( $fact );
		}
		if ( $this->kept_redirects ) {
			$this->say( sprintf( 'Sources already redirected by an existing active redirect (kept, not duplicated): %d', $this->kept_redirects ) );
		}
		$counts = array();
		foreach ( $this->ops as $op ) {
			$counts[ $op['op'] ] = isset( $counts[ $op['op'] ] ) ? $counts[ $op['op'] ] + 1 : 1;
		}
		$names = array(
			'meta'             => 'posts with ACF fields to write',
			'meta_restore'     => 'meta restores',
			'status'           => 'status changes',
			'content'          => 'content fixes',
			'content_restore'  => 'content restores',
			'redirect_create'  => 'redirects to create',
			'redirect_update'  => 'redirects to retarget',
			'redirect_delete'  => 'redirects to delete',
			'redirect_restore' => 'redirects to restore',
			'option'           => 'options to update',
		);
		if ( ! $counts ) {
			$this->say( 'Planned writes: none.' );
		}
		foreach ( $counts as $type => $n ) {
			$this->say( sprintf( '%4d %s', $n, isset( $names[ $type ] ) ? $names[ $type ] : $type ) );
		}
		$this->say( sprintf( '%4d notes / items to check (lines starting with "!")', $this->note_count ) );
	}

	/**
	 * Add an operation and print it.
	 *
	 * @param array $op     Operation.
	 * @param int   $indent Indent level.
	 */
	private function add_op( $op, $indent = 0 ) {
		$this->ops[] = $op;
		$this->say( '+ ' . $op['label'], $indent );
	}

	/**
	 * Print a note (something to check) and count it.
	 *
	 * @param string $text   Note.
	 * @param int    $indent Indent level.
	 */
	private function note( $text, $indent = 0 ) {
		++$this->note_count;
		$this->say( '! ' . $text, $indent );
	}

	/**
	 * Print a section heading.
	 *
	 * @param string $title Title.
	 */
	private function head( $title ) {
		WP_CLI::log( '' );
		WP_CLI::log( '== ' . $title . ' ==' );
	}

	/**
	 * Print a line.
	 *
	 * @param string $text   Text.
	 * @param int    $indent Indent level.
	 */
	private function say( $text, $indent = 0 ) {
		WP_CLI::log( str_repeat( '  ', $indent ) . $text );
	}
}

$lc_courses_migration = new LC_Courses_Migration( __DIR__, isset( $args ) ? $args : array() );
$lc_courses_migration->run();
