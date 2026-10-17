<?php
/**
 * Replace the Gravity Forms blocks/shortcodes in post content with the
 * theme's [lc_form] shortcode (includes/forms.php), before Gravity Forms is
 * removed.
 *
 *   wp eval-file replace-gf-blocks.php          # dry run: report only
 *   wp eval-file replace-gf-blocks.php apply    # write, with a JSON backup
 *
 * Mapping: form 1 -> contact, 2 -> course-updates, 3 -> newsletter. The block
 * for form 6 (deleted long ago, on episode 022) is removed. Anything else is
 * reported and left alone. WP Popups' own post (wppopups, JSON content) is
 * skipped: the theme's popup replaces it and the plugin goes with it.
 *
 * Writes go straight to the posts table (then the post cache is cleared) so
 * WP-CLI's missing user can't trigger kses filtering of the content, and no
 * revisions or modified dates change. The backup holds every changed post's
 * old post_content, in ~/claude-backups/.
 *
 * @package CardanoPress Bootstrap
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	echo "Run with: wp eval-file replace-gf-blocks.php [apply]\n";
	return;
}

global $wpdb;

$lc_apply = isset( $args ) && in_array( 'apply', (array) $args, true );
$lc_map   = array(
	1 => 'contact',
	2 => 'course-updates',
	3 => 'newsletter',
);
$lc_drop  = array( 6 );

$lc_shortcode_block = function ( $form ) {
	return "<!-- wp:shortcode -->\n[lc_form id=\"" . $form . "\"]\n<!-- /wp:shortcode -->";
};

WP_CLI::log( sprintf( '%s on %s (%s)', $lc_apply ? 'APPLY' : 'DRY RUN', home_url(), wp_get_environment_type() ) );

$lc_rows = $wpdb->get_results(
	"SELECT ID, post_type, post_status, post_name, post_content FROM {$wpdb->posts}
	WHERE post_type NOT IN ('revision', 'wppopups')
	AND ( post_content LIKE '%wp:gravityforms/%' OR post_content LIKE '%[gravityform%' )
	ORDER BY ID"
);

$lc_skipped_popups = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'wppopups' AND post_content LIKE '%gravityform%'" );

$lc_totals  = array(
	'posts'      => 0,
	'replaced'   => 0,
	'removed'    => 0,
	'unexpected' => 0,
);
$lc_changes = array();

foreach ( $lc_rows as $lc_row ) {
	$lc_old   = $lc_row->post_content;
	$lc_new   = $lc_old;
	$lc_notes = array();

	// Blocks: <!-- wp:gravityforms/form {"formId":"1",...} /-->
	$lc_new = preg_replace_callback(
		'#<!-- wp:gravityforms/form (\{[^\n]*?\}) /-->(\n\n)?#',
		function ( $m ) use ( $lc_map, $lc_drop, $lc_shortcode_block, &$lc_notes, &$lc_totals ) {
			$attrs = json_decode( $m[1], true );
			$id    = is_array( $attrs ) && isset( $attrs['formId'] ) && ctype_digit( (string) $attrs['formId'] ) ? (int) $attrs['formId'] : 0;
			if ( isset( $lc_map[ $id ] ) ) {
				// Gravity Forms showed the title/description unless told not to.
				$extra = '';
				if ( 'course-updates' !== $lc_map[ $id ] && ( ! isset( $attrs['title'] ) || false !== $attrs['title'] ) ) {
					$extra = ' title="true"';
				}
				$lc_notes[] = sprintf( 'block form %d -> [lc_form id="%s"%s]', $id, $lc_map[ $id ], $extra );
				++$lc_totals['replaced'];
				$out = $lc_shortcode_block( $lc_map[ $id ] );
				if ( $extra ) {
					$out = str_replace( '"]', '"' . $extra . ']', $out );
				}
				return $out . ( isset( $m[2] ) ? $m[2] : '' );
			}
			if ( in_array( $id, $lc_drop, true ) ) {
				$lc_notes[] = sprintf( 'block form %d removed', $id );
				++$lc_totals['removed'];
				return '';
			}
			$lc_notes[] = 'UNEXPECTED block left alone: ' . $m[0];
			++$lc_totals['unexpected'];
			return $m[0];
		},
		$lc_new
	);

	// Shortcodes: [gravityform id="3" title="false" ...] / [gravityforms id=3]
	$lc_new = preg_replace_callback(
		'#\[gravityforms? ([^\]]*)\]#',
		function ( $m ) use ( $lc_map, $lc_drop, &$lc_notes, &$lc_totals ) {
			$atts = shortcode_parse_atts( $m[1] );
			$id   = isset( $atts['id'] ) && ctype_digit( (string) $atts['id'] ) ? (int) $atts['id'] : 0;
			if ( isset( $lc_map[ $id ] ) ) {
				$lc_notes[] = sprintf( 'shortcode form %d -> [lc_form id="%s"]', $id, $lc_map[ $id ] );
				++$lc_totals['replaced'];
				return '[lc_form id="' . $lc_map[ $id ] . '"]';
			}
			if ( in_array( $id, $lc_drop, true ) ) {
				$lc_notes[] = sprintf( 'shortcode form %d removed', $id );
				++$lc_totals['removed'];
				return '';
			}
			$lc_notes[] = 'UNEXPECTED shortcode left alone: ' . $m[0];
			++$lc_totals['unexpected'];
			return $m[0];
		},
		$lc_new
	);

	WP_CLI::log( sprintf( '#%d %s/%s (%s)', $lc_row->ID, $lc_row->post_type, $lc_row->post_name, $lc_row->post_status ) );
	foreach ( $lc_notes as $lc_note ) {
		WP_CLI::log( '    ' . $lc_note );
	}

	// Guard: the only differences are our replacements.
	if ( $lc_new === $lc_old ) {
		continue;
	}
	$lc_unexpected = count( preg_grep( '/^UNEXPECTED/', $lc_notes ) );
	if ( false !== strpos( $lc_new, 'wp:gravityforms/' ) && 0 === $lc_unexpected ) {
		WP_CLI::warning( "  #{$lc_row->ID} still contains a Gravity Forms block after replacement; skipped." );
		continue;
	}
	++$lc_totals['posts'];
	$lc_changes[ (int) $lc_row->ID ] = array(
		'post_type' => $lc_row->post_type,
		'post_name' => $lc_row->post_name,
		'old'       => $lc_old,
		'new'       => $lc_new,
	);
}

WP_CLI::log( sprintf( 'Posts to change: %d, forms replaced: %d, blocks removed: %d, unexpected: %d, wppopups posts skipped: %d', $lc_totals['posts'], $lc_totals['replaced'], $lc_totals['removed'], $lc_totals['unexpected'], $lc_skipped_popups ) );

if ( ! $lc_apply ) {
	WP_CLI::log( 'Dry run: nothing written. Re-run with "apply" to write.' );
	return;
}
if ( ! $lc_changes ) {
	WP_CLI::success( 'Nothing to change.' );
	return;
}

// Backup first; refuse to write without one.
$lc_dir = rtrim( (string) getenv( 'HOME' ), '/' ) . '/claude-backups';
if ( ! is_dir( $lc_dir ) && ! mkdir( $lc_dir, 0700, true ) ) {
	WP_CLI::error( "Could not create $lc_dir" );
}
$lc_file   = sprintf( '%s/gf-blocks-%s-%s.json', $lc_dir, wp_parse_url( home_url(), PHP_URL_HOST ), gmdate( 'Ymd-His' ) );
$lc_backup = array();
foreach ( $lc_changes as $lc_id => $lc_change ) {
	$lc_backup[ $lc_id ] = array(
		'post_type'    => $lc_change['post_type'],
		'post_name'    => $lc_change['post_name'],
		'post_content' => $lc_change['old'],
	);
}
if ( false === file_put_contents( $lc_file, wp_json_encode( $lc_backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) ) ) {
	WP_CLI::error( "Could not write backup $lc_file" );
}
WP_CLI::log( "Backup: $lc_file" );

foreach ( $lc_changes as $lc_id => $lc_change ) {
	// Only write if the content is still what we read (no concurrent edit).
	$lc_done = $wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->posts} SET post_content = %s WHERE ID = %d AND post_content = %s",
			$lc_change['new'],
			$lc_id,
			$lc_change['old']
		)
	);
	if ( 1 === $lc_done ) {
		clean_post_cache( $lc_id );
		WP_CLI::log( "  #$lc_id updated" );
	} else {
		WP_CLI::warning( "  #$lc_id not updated (content changed meanwhile or DB error)" );
	}
}
WP_CLI::success( 'Done. Purge the page cache (wp sg purge).' );
