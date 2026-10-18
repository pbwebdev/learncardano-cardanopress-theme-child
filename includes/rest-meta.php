<?php
/**
 * REST meta for the publishing automation, moved from the mu-plugins
 * kb-acf-rest.php (v2.0) and podcast-acf-rest.php (v1.0) without changing
 * behaviour: the knowledge base types' and podcasts' ACF scalar and
 * post_object fields are registered as post meta with show_in_rest, so
 * publish-content-to-wordpress.js / publish-to-wordpress.js (and OpenClaw)
 * can write them with a basic-auth POST to /wp-json/wp/v2/<type>/{id}.
 *
 * Repeater sub-fields are NOT registered: the importer sends repeaters via
 * the `acf:` REST key (ACF's own REST integration). Keep the lists in sync
 * with the importer.
 *
 * Runs at init 20, after the mu-plugins (init 10), and skips any key that is
 * already registered, so nothing double-registers while they still exist.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * Meta keys per post type, by kind.
 *
 * @return array<string, array{string: string[], bool: string[], relation_multi: string[], relation_single: string[]}>
 */
function lc_rest_meta_schema() {
	return array(
		'recipe'     => array(
			'string'          => array(
				'est_time_minutes',     // ACF Text — string, not int.
				'last_verified',
				'severity_of_drift',
				'cardano_era',          // Legacy, kept for existing posts.
				'video_url',
				'github_url',
				'sandbox_url',
				'demo_url',
				'tldr',
				'prerequisites',
				'learning_outcomes',
				'ai_system_prompt',
				'ai_starter_prompt',
				'ai_context_files',
			),
			'bool'            => array( 'ai_ready' ),
			'relation_multi'  => array( 'related_pitfalls', 'related_concepts', 'related_recipes' ),
			'relation_single' => array(),
		),
		'concept'    => array(
			'string'          => array( 'concept_aka', 'concept_type', 'tldr', 'analogy_evm', 'common_misconceptions' ),
			'bool'            => array(),
			'relation_multi'  => array( 'prerequisite_concepts', 'related_recipes', 'related_pitfalls' ),
			'relation_single' => array(),
		),
		'pitfall'    => array(
			'string'          => array( 'severity', 'first_seen_in_era', 'symptom', 'cause', 'fix', 'error_signatures' ),
			'bool'            => array( 'still_present' ),
			'relation_multi'  => array( 'related_recipes', 'related_concepts' ),
			'relation_single' => array(),
		),
		'minidapp'   => array(
			'string'          => array(
				'dapp_status',
				'last_verified',
				'version',
				'github_url',
				'demo_url',
				'sandbox_url',
				'video_series_url',
				'tldr',
				'architecture_summary',
				'on_chain_lang',
				'off_chain_stack',
				'frontend_stack',
			),
			'bool'            => array(),
			'relation_multi'  => array( 'composed_of_recipes', 'related_pitfalls' ),
			'relation_single' => array(),
			// Repeater tx_examples goes through the acf: payload.
		),
		'comparison' => array(
			'string'          => array( 'question', 'tldr', 'last_updated', 'confidence' ),
			'bool'            => array(),
			'relation_multi'  => array(),
			'relation_single' => array(),
			// Repeaters options, use_case_matrix go through the acf: payload.
		),
		'track'      => array(
			'string'          => array(
				'primary_audience',
				'est_total_hours',  // ACF Number, kept as string for REST flexibility.
				'track_status',
				'tldr',
				'outcome_statement',
			),
			'bool'            => array(),
			'relation_multi'  => array(),
			'relation_single' => array( 'capstone_dapp' ),
			// Repeater track_steps goes through the acf: payload.
		),
		'podcasts'   => array(
			'string'          => array( 'youtube_video', 'text_transcript' ),
			'bool'            => array(),
			'relation_multi'  => array(),
			'relation_single' => array(),
		),
	);
}

/**
 * A YouTube video ID from an ID or a YouTube URL (watch, youtu.be, embed,
 * shorts, live). Returns '' unless the result looks like a real ID, so it is
 * safe to put in an embed URL.
 */
function lc_youtube_id( $value ) {
	$value = trim( (string) $value );
	if ( preg_match( '~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:embed/|shorts/|live/|v/|watch\?(?:[^#]*&)?v=))([A-Za-z0-9_-]+)~i', $value, $m ) ) {
		$value = $m[1];
	}
	return preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $value ) ? $value : '';
}

/**
 * A Spotify episode/show ID from an ID or an open.spotify.com URL, or ''.
 */
function lc_spotify_id( $value ) {
	$value = trim( (string) $value );
	if ( preg_match( '~open\.spotify\.com/(?:embed/)?(?:episode|show)/([A-Za-z0-9]+)~i', $value, $m ) ) {
		$value = $m[1];
	}
	return preg_match( '/^[A-Za-z0-9]{10,40}$/', $value ) ? $value : '';
}

/**
 * Sanitizers for meta keys that end up in embed URLs: keep only the ID
 * characters instead of wp_kses_post (which lets quotes through).
 *
 * @return array<string, callable>
 */
function lc_rest_meta_id_sanitizers() {
	return array(
		'youtube_video' => function ( $v ) {
			$v  = is_string( $v ) ? sanitize_text_field( $v ) : '';
			$id = lc_youtube_id( $v );
			return '' !== $id ? $id : preg_replace( '/[^A-Za-z0-9_-]/', '', $v );
		},
		'spotify_id'    => function ( $v ) {
			$v  = is_string( $v ) ? sanitize_text_field( $v ) : '';
			$id = lc_spotify_id( $v );
			return '' !== $id ? $id : preg_replace( '/[^A-Za-z0-9]/', '', $v );
		},
	);
}

add_action(
	'init',
	function () {
		$id_sanitizers = lc_rest_meta_id_sanitizers();

		$auth = function () {
			return current_user_can( 'edit_posts' );
		};

		$kinds = array(
			'string'          => array(
				'type'              => 'string',
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $v ) {
					return is_string( $v ) ? wp_kses_post( $v ) : '';
				},
			),
			'bool'            => array(
				'type'              => 'integer', // ACF true/false stores 1/0.
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $v ) {
					if ( is_bool( $v ) ) {
						return $v ? 1 : 0;
					}
					if ( is_numeric( $v ) ) {
						return (int) $v ? 1 : 0;
					}
					if ( is_string( $v ) ) {
						return ( 'true' === $v || '1' === $v ) ? 1 : 0;
					}
					return 0;
				},
			),
			'relation_multi'  => array(
				'type'              => 'array',
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				),
				'sanitize_callback' => function ( $v ) {
					if ( ! is_array( $v ) ) {
						return array();
					}
					return array_values( array_filter( array_map( 'intval', $v ) ) );
				},
			),
			'relation_single' => array(
				'type'              => 'integer',
				'show_in_rest'      => true,
				'sanitize_callback' => function ( $v ) {
					return is_numeric( $v ) ? (int) $v : 0;
				},
			),
		);

		foreach ( lc_rest_meta_schema() as $post_type => $groups ) {
			foreach ( $groups as $kind => $keys ) {
				foreach ( $keys as $key ) {
					// Still registered by the old mu-plugin: leave it alone.
					if ( registered_meta_key_exists( 'post', $key, $post_type ) ) {
						continue;
					}
					$args = array_merge(
						$kinds[ $kind ],
						array(
							'single'        => true,
							'auth_callback' => $auth,
						)
					);
					if ( isset( $id_sanitizers[ $key ] ) ) {
						$args['sanitize_callback'] = $id_sanitizers[ $key ];
					}
					register_post_meta( $post_type, $key, $args );
				}
			}
		}
	},
	20
);
