<?php
/**
 * Learn Cardano -> Learn Crypto rebrand: database copy changes from a reviewed deck.
 *
 * Copy this folder (rebrand-db.php, targets.json, copy-deck.json, content/) to
 * the server, e.g. ~/claude-scripts/rebrand/, and run it with WP-CLI. All
 * arguments are positional (WP-CLI rejects unknown --flags on eval-file):
 *
 *   wp eval-file rebrand-db.php scan [out.json] [with-urls]                    --path=...
 *   wp eval-file rebrand-db.php export <targets.json> <out.json>               --path=...
 *   wp eval-file rebrand-db.php [dry-run [review-dir]]                         --path=...
 *   wp eval-file rebrand-db.php apply <backup-dir> <site>                      --path=...
 *   wp eval-file rebrand-db.php restore <journal.json> <site>                  --path=...
 *   wp eval-file rebrand-db.php sync-from <live-export.json> [backup-dir] <site> --path=...  (staging only)
 *   wp eval-file rebrand-db.php checksum <out.json>                            --path=...
 *
 * <site> must be the name of the site home_url() says this is: "live" for
 * https://learncardano.io, "staging" for https://staging2.learncardano.io.
 * Any other home_url() aborts before anything is read.
 *
 * The deck (copy-deck.json beside this file) lists one item per value to
 * change, each with the exact current value ("old" / "old_file" /
 * "old_sha256") and the new one ("new" / "new_file" under content/). The
 * token {{home}} in any deck value stands for home_url(). Nothing is written
 * unless every item is CHANGE, ALREADY or NOT_FOR_SITE.
 *
 * Writes: options through update_option() (one key at a time for serialized
 * arrays, never string replacement); posts, post meta, terms, term meta,
 * users and user meta straight to the tables with $wpdb, then the matching
 * clean_*_cache(), so no hooks, revisions or emails. Only pages, attachments,
 * nav menu items, reusable blocks and ACF post type definitions may be
 * written. post_modified is bumped only for pages whose title or content
 * changes.
 *
 * apply writes a journal (every op's "before" value and state planned /
 * running / done / failed, rewritten after each write, mode 600) and a
 * checksum manifest of every post and term before and after, then reports
 * anything outside the deck that changed. restore undoes a journal newest
 * first, and only where the value is still what apply wrote.
 *
 * tools/ is never deployed with the theme.
 *
 * @package CardanoPress Bootstrap
 */

if ( ! defined( 'ABSPATH' ) || ! class_exists( 'WP_CLI' ) ) {
	echo "Run with WP-CLI: wp eval-file rebrand-db.php <mode> [args] --path=...\n";
	return;
}

/**
 * One run of the rebrand tool.
 */
final class LC_Rebrand_DB {

	const VERSION = '1.0.0';

	const TOOL = 'learncardano-cardanopress-theme-child/tools/rebrand/rebrand-db.php';

	const BRAND_RE = '/Learn\s*Cardano|Cardano Podcast|LearnCardano|CardanoPress/i';

	const TOKEN = '{{home}}';

	/**
	 * The only two sites this tool runs on (untrailingslashed home_url() => name).
	 */
	const SITES = array(
		'https://learncardano.io'          => 'live',
		'https://staging2.learncardano.io' => 'staging',
	);

	/**
	 * Post types scanned in full (fields and all meta).
	 */
	const SCAN_TYPES = array( 'page', 'wp_block', 'nav_menu_item', 'attachment', 'acf-post-type', 'acf-taxonomy', 'wp_navigation' );

	/**
	 * Historical content: counted by scan, never written, must not change (checksum).
	 */
	const HISTORICAL_TYPES = array( 'podcasts', 'post', 'sfwd-courses', 'sfwd-lessons', 'sfwd-topic', 'sfwd-quiz', 'concept', 'comparison', 'pitfall', 'recipe', 'minidapp', 'track', 'lc_recipe' );

	/**
	 * The only post types a deck item may write.
	 */
	const WRITE_TYPES = array( 'page', 'attachment', 'nav_menu_item', 'wp_block', 'acf-post-type' );

	const ITEM_TYPES = array( 'option', 'option_key', 'post_field', 'post_content_edits', 'post_meta', 'term_field', 'term_meta', 'user_field', 'user_meta' );

	/**
	 * Users whose only roles are these are not scanned.
	 */
	const SKIP_ROLES = array( 'subscriber', 'student' );

	/**
	 * Post meta left out of the checksum (volatile, written by WordPress or Rank Math on their own).
	 */
	const CHECKSUM_SKIP_META = array( '_edit_lock', '_edit_last', '_oembed_*', '_oembed_time_*', 'rank_math_analytic*' );

	/** @var string */
	private $dir;
	/** @var string[] */
	private $args;
	/** @var string */
	private $mode;
	/** @var string Untrailingslashed home_url(). */
	private $home;
	/** @var string live|staging */
	private $site;
	/** @var array[] Deck items. */
	private $items = array();
	/** @var array<string, string> Coverage key => item id. */
	private $deck_keys = array();
	/** @var array<string, string> Leave key or pattern => id. */
	private $leave = array();
	/** @var array[] Planned writes (journal ops). */
	private $ops = array();
	/** @var string */
	private $journal_file = '';
	/** @var string */
	private $journal_mode = '';
	/** @var string */
	private $source = '';
	/** @var string */
	private $started_utc = '';
	/** @var string */
	private $review_dir = '';
	/** @var array[] Scan hits. */
	private $hits = array();
	/** @var int URL/slug-only matches not listed. */
	private $hidden = 0;
	/** @var bool */
	private $with_urls = false;

	/**
	 * Detect the site and read the mode. Aborts on any other site.
	 *
	 * @param string $dir  Folder holding copy-deck.json and content/.
	 * @param array  $args Positional arguments from WP-CLI.
	 */
	public function __construct( $dir, $args ) {
		$this->dir  = $dir;
		$this->args = array_values( array_map( 'strval', (array) $args ) );
		$this->mode = isset( $this->args[0] ) ? $this->args[0] : 'dry-run';
		$this->home = untrailingslashit( home_url() );
		$sites      = self::SITES;
		if ( ! isset( $sites[ $this->home ] ) ) {
			WP_CLI::error( sprintf( 'home_url() is %s. This tool only runs on %s. Nothing was read or written.', $this->home, implode( ' or ', array_keys( $sites ) ) ) );
		}
		$this->site = $sites[ $this->home ];
	}

	/**
	 * Run the requested mode.
	 */
	public function run() {
		$this->started_utc = gmdate( 'Y-m-d\TH:i:s\Z' );
		WP_CLI::log( sprintf( 'Rebrand DB tool %s: mode "%s" on %s (%s), WordPress at %s', self::VERSION, $this->mode, strtoupper( $this->site ), $this->home, ABSPATH ) );
		switch ( $this->mode ) {
			case 'scan':
				$this->mode_scan();
				break;
			case 'export':
				$this->mode_export();
				break;
			case 'dry-run':
				$this->mode_dry_run();
				break;
			case 'apply':
				$this->mode_apply();
				break;
			case 'restore':
				$this->mode_restore();
				break;
			case 'sync-from':
				$this->mode_sync_from();
				break;
			case 'checksum':
				$this->mode_checksum();
				break;
			default:
				$this->usage();
		}
	}

	/**
	 * Print usage and stop.
	 */
	private function usage() {
		WP_CLI::error(
			"Usage (all arguments positional):\n"
			. "  wp eval-file rebrand-db.php scan [out.json] [with-urls] --path=...\n"
			. "  wp eval-file rebrand-db.php export <targets.json> <out.json> --path=...\n"
			. "  wp eval-file rebrand-db.php dry-run [review-dir] --path=...\n"
			. "  wp eval-file rebrand-db.php apply <backup-dir> <site> --path=...\n"
			. "  wp eval-file rebrand-db.php restore <journal.json> <site> --path=...\n"
			. "  wp eval-file rebrand-db.php sync-from <live-export.json> [backup-dir] <site> --path=...\n"
			. "  wp eval-file rebrand-db.php checksum <out.json> --path=...\n"
			. '<site> is "live" or "staging" and must match home_url().'
		);
	}

	/**
	 * Arguments after the mode; with $with_site the last one must be this site's name (and is removed).
	 *
	 * @param bool $with_site Require the confirming site token.
	 * @return string[]
	 */
	private function params( $with_site ) {
		$p = array_slice( $this->args, 1 );
		if ( $with_site ) {
			if ( ! $p || end( $p ) !== $this->site ) {
				WP_CLI::error( sprintf( 'This is the %1$s site (%2$s). Add "%1$s" as the last argument to confirm. Nothing was written.', $this->site, $this->home ) );
			}
			array_pop( $p );
		}
		return $p;
	}

	/* ------------------------------------------------------------------ */
	/* Mode: scan                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Find old-brand text and mark each hit COVERED / COVERED-LEAVE / UNCOVERED.
	 */
	private function mode_scan() {
		$out = '';
		foreach ( $this->params( false ) as $a ) {
			if ( 'with-urls' === $a ) {
				$this->with_urls = true;
			} elseif ( '' === $out ) {
				$out = $this->path( $a );
			} else {
				$this->usage();
			}
		}
		$this->load_deck( false );

		WP_CLI::log( '' );
		WP_CLI::log( '== Options ==' );
		$skipped = $this->scan_options();
		WP_CLI::log( '== Posts: ' . implode( ', ', self::SCAN_TYPES ) . ' (fields and all meta) ==' );
		$this->scan_posts();
		WP_CLI::log( '== Terms and term meta ==' );
		$this->scan_terms();
		WP_CLI::log( '== Users (roles other than ' . implode( '/', self::SKIP_ROLES ) . ') ==' );
		$this->scan_users();

		$tally = array(
			'COVERED'       => 0,
			'COVERED-LEAVE' => 0,
			'UNCOVERED'     => 0,
		);
		$seen  = array();
		foreach ( $this->hits as $h ) {
			++$tally[ $h['coverage'] ];
			$seen[ $h['key'] ] = true;
			WP_CLI::log( sprintf( '[%s] %s  %s  (%d match%s%s)', $h['coverage'], $h['key'], $h['where'], $h['count'], 1 === $h['count'] ? '' : 'es', $h['ident'] ? sprintf( ', +%d in URLs/slugs', $h['ident'] ) : '' ) );
			foreach ( $h['snippets'] as $s ) {
				WP_CLI::log( '      "' . $s . '"' );
			}
		}

		WP_CLI::log( '' );
		WP_CLI::log( '== Historical content (counts only; never written) ==' );
		$hist = $this->scan_historical();

		$quiet = array();
		foreach ( $this->deck_keys as $key => $id ) {
			if ( ! isset( $seen[ $key ] ) ) {
				$quiet[] = $id . ' (' . $key . ')';
			}
		}

		WP_CLI::log( '' );
		WP_CLI::log( '== Summary ==' );
		WP_CLI::log( sprintf( 'Hits: %d COVERED, %d COVERED-LEAVE, %d UNCOVERED.', $tally['COVERED'], $tally['COVERED-LEAVE'], $tally['UNCOVERED'] ) );
		WP_CLI::log( sprintf( 'Options skipped (transients, rank_math_backups, oEmbed caches): %d.', $skipped ) );
		if ( $this->hidden ) {
			WP_CLI::log( sprintf( 'Matches only inside URLs, emails, file names or lower-case slugs (not listed; add "with-urls" to list them): %d.', $this->hidden ) );
		}
		if ( $quiet ) {
			WP_CLI::log( 'Deck items whose target has no old-brand text (fine if they change other copy): ' . implode( ', ', $quiet ) );
		}
		foreach ( $this->hits as $h ) {
			if ( 'UNCOVERED' === $h['coverage'] ) {
				WP_CLI::log( '  UNCOVERED: ' . $h['key'] . '  ' . $h['where'] );
			}
		}
		if ( '' !== $out ) {
			$this->write_json(
				$out,
				array(
					'tool'        => self::TOOL,
					'version'     => self::VERSION,
					'mode'        => 'scan',
					'site'        => $this->site,
					'home'        => $this->home,
					'created_utc' => $this->started_utc,
					'tally'       => $tally,
					'hidden'      => $this->hidden,
					'hits'        => $this->hits,
					'historical'  => $hist,
				),
				false
			);
			WP_CLI::log( 'Written: ' . $out );
		}
		WP_CLI::success( 'Scan done. Nothing was written to the database.' );
	}

	/**
	 * Scan every option except transients, Rank Math backups and oEmbed caches.
	 *
	 * @return int Options skipped.
	 */
	private function scan_options() {
		global $wpdb;
		$last    = 0;
		$skipped = 0;
		do {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_id > %d ORDER BY option_id LIMIT 500", $last ) );
			foreach ( $rows as $row ) {
				$last = (int) $row->option_id;
				$name = (string) $row->option_name;
				if ( 0 === strpos( $name, '_transient_' ) || 0 === strpos( $name, '_site_transient_' ) || 'rank_math_backups' === $name || false !== stripos( $name, 'oembed' ) ) {
					++$skipped;
					continue;
				}
				$this->scan_text( 'option:' . $name, 'option ' . $name, $row->option_value );
			}
		} while ( count( $rows ) === 500 );
		return $skipped;
	}

	/**
	 * Scan the fields and all meta of the scanned post types.
	 */
	private function scan_posts() {
		global $wpdb;
		$types = "'" . implode( "','", array_map( 'esc_sql', self::SCAN_TYPES ) ) . "'";
		$ids   = array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ({$types}) AND post_status <> 'auto-draft' ORDER BY ID" ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( array_chunk( $ids, 100 ) as $chunk ) {
			$in    = implode( ',', $chunk );
			$where = array();
			$rows  = $wpdb->get_results( "SELECT ID, post_type, post_status, post_title, post_excerpt, post_content, post_name, post_content_filtered FROM {$wpdb->posts} WHERE ID IN ({$in}) ORDER BY ID" ); // phpcs:ignore WordPress.DB.PreparedSQL
			foreach ( $rows as $row ) {
				$id           = (int) $row->ID;
				$where[ $id ] = sprintf( '%s %d "%s" [%s]', $row->post_type, $id, $this->short( (string) $row->post_title, 40 ), $row->post_status );
				foreach ( array( 'post_title', 'post_excerpt', 'post_content', 'post_name', 'post_content_filtered' ) as $f ) {
					$this->scan_text( 'post:' . $id . ':' . $f, $where[ $id ], $row->$f );
				}
			}
			$meta = $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$in}) ORDER BY meta_id" ); // phpcs:ignore WordPress.DB.PreparedSQL
			foreach ( $meta as $m ) {
				if ( 0 === strpos( (string) $m->meta_key, '_oembed_' ) ) {
					continue;
				}
				$pid = (int) $m->post_id;
				$this->scan_text( 'postmeta:' . $pid . ':' . $m->meta_key, isset( $where[ $pid ] ) ? $where[ $pid ] : 'post ' . $pid, $m->meta_value );
			}
		}
	}

	/**
	 * Scan every term's name and description, and all term meta.
	 */
	private function scan_terms() {
		global $wpdb;
		$where = array();
		$rows  = $wpdb->get_results( "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.description FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id ORDER BY t.term_id" );
		foreach ( $rows as $row ) {
			$id           = (int) $row->term_id;
			$where[ $id ] = sprintf( '%s %d "%s"', $row->taxonomy, $id, $this->short( (string) $row->name, 40 ) );
			$this->scan_text( 'term:' . $id . ':name', $where[ $id ], $row->name );
			$this->scan_text( 'term:' . $id . ':description', $where[ $id ], $row->description );
		}
		$last = 0;
		do {
			$meta = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, term_id, meta_key, meta_value FROM {$wpdb->termmeta} WHERE meta_id > %d ORDER BY meta_id LIMIT 1000", $last ) );
			foreach ( $meta as $m ) {
				$last = (int) $m->meta_id;
				$tid  = (int) $m->term_id;
				$this->scan_text( 'termmeta:' . $tid . ':' . $m->meta_key, isset( $where[ $tid ] ) ? $where[ $tid ] : 'term ' . $tid, $m->meta_value );
			}
		} while ( count( $meta ) === 1000 );
	}

	/**
	 * Scan display_name, nickname and description of users with a role other than subscriber/student.
	 */
	private function scan_users() {
		global $wpdb;
		$cap_key = $wpdb->get_blog_prefix() . 'capabilities';
		$caps    = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s", $cap_key ) );
		$n       = 0;
		foreach ( $caps as $c ) {
			$roles = $this->unser( $c->meta_value );
			$roles = is_array( $roles ) ? array_keys( array_filter( $roles ) ) : array();
			if ( ! array_diff( $roles, self::SKIP_ROLES ) ) {
				continue;
			}
			$uid = (int) $c->user_id;
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT display_name FROM {$wpdb->users} WHERE ID = %d", $uid ) );
			if ( ! $row ) {
				continue;
			}
			++$n;
			$where = sprintf( 'user %d (%s)', $uid, implode( ',', $roles ) );
			$this->scan_text( 'user:' . $uid . ':display_name', $where, $row->display_name );
			foreach ( array( 'nickname', 'description' ) as $key ) {
				$vals = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s", $uid, $key ) );
				foreach ( $vals as $v ) {
					$this->scan_text( 'usermeta:' . $uid . ':' . $key, $where, $v );
				}
			}
		}
		WP_CLI::log( sprintf( '  %d users scanned.', $n ) );
	}

	/**
	 * Count old-brand text in historical post types (title, excerpt, content and meta).
	 *
	 * @return array<string, array>
	 */
	private function scan_historical() {
		global $wpdb;
		$out = array();
		foreach ( self::HISTORICAL_TYPES as $type ) {
			$c   = array(
				'posts'               => 0,
				'posts_with_hits'     => 0,
				'matches'             => 0,
				'url_slug_matches'    => 0,
				'meta_rows_with_hits' => 0,
			);
			$ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_status <> 'auto-draft' ORDER BY ID", $type ) ) );
			$c['posts'] = count( $ids );
			foreach ( array_chunk( $ids, 100 ) as $chunk ) {
				$in   = implode( ',', $chunk );
				$rows = $wpdb->get_results( "SELECT post_title, post_excerpt, post_content FROM {$wpdb->posts} WHERE ID IN ({$in})" ); // phpcs:ignore WordPress.DB.PreparedSQL
				foreach ( $rows as $row ) {
					$n = 0;
					foreach ( array( 'post_title', 'post_excerpt', 'post_content' ) as $f ) {
						if ( preg_match( self::BRAND_RE, (string) $row->$f ) ) {
							$m                      = $this->brand_matches( (string) $row->$f, 0 );
							$n                     += $m['count'];
							$c['url_slug_matches'] += $m['ident'];
						}
					}
					if ( $n ) {
						++$c['posts_with_hits'];
						$c['matches'] += $n;
					}
				}
				$meta = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$in}) AND meta_key NOT LIKE '\\_oembed\\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL
				foreach ( $meta as $v ) {
					if ( preg_match( self::BRAND_RE, (string) $v ) ) {
						$m = $this->brand_matches( (string) $v, 0 );
						if ( $m['count'] ) {
							++$c['meta_rows_with_hits'];
						}
					}
				}
			}
			$out[ $type ] = $c;
			WP_CLI::log( sprintf( '  %-14s %5d posts, %5d with old-brand text (%d matches), %d meta rows, %d URL/slug-only matches', $type, $c['posts'], $c['posts_with_hits'], $c['matches'], $c['meta_rows_with_hits'], $c['url_slug_matches'] ) );
		}
		return $out;
	}

	/**
	 * Record old-brand text in a value (recursing into serialized arrays).
	 *
	 * @param string $key   Coverage key.
	 * @param string $where Human label.
	 * @param mixed  $value Value.
	 */
	private function scan_text( $key, $where, $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			foreach ( (array) $value as $k => $v ) {
				$this->scan_text( $key . '/' . str_replace( "\0", '', (string) $k ), $where, $v );
			}
			return;
		}
		if ( ! is_string( $value ) || '' === $value || ! preg_match( self::BRAND_RE, $value ) ) {
			return;
		}
		if ( is_serialized( $value ) ) {
			$u = $this->unser( $value );
			if ( is_array( $u ) || is_object( $u ) ) {
				$this->scan_text( $key, $where, $u );
				return;
			}
		}
		$m = $this->brand_matches( $value, 3 );
		if ( ! $m['count'] && ! $this->with_urls ) {
			$this->hidden += $m['ident'];
			return;
		}
		if ( ! $this->with_urls ) {
			$this->hidden += $m['ident'];
		}
		$this->hits[] = array(
			'key'      => $key,
			'where'    => $where,
			'count'    => $m['count'],
			'ident'    => $m['ident'],
			'snippets' => $m['snippets'],
			'coverage' => $this->coverage( $key ),
		);
	}

	/**
	 * Count old-brand matches, separating those inside URLs, emails, file names and slugs.
	 *
	 * @param string $s   Text.
	 * @param int    $max Snippets to keep (0 = none, -1 = all).
	 * @return array{count: int, ident: int, snippets: string[]}
	 */
	private function brand_matches( $s, $max ) {
		$out = array(
			'count'    => 0,
			'ident'    => 0,
			'snippets' => array(),
		);
		if ( ! preg_match_all( self::BRAND_RE, $s, $m, PREG_OFFSET_CAPTURE ) ) {
			return $out;
		}
		foreach ( $m[0] as $hit ) {
			$text = $hit[0];
			$pos  = (int) $hit[1];
			if ( $this->is_identifier( $s, $text, $pos ) ) {
				++$out['ident'];
				if ( ! $this->with_urls ) {
					continue;
				}
			} else {
				++$out['count'];
			}
			if ( $max < 0 || count( $out['snippets'] ) < $max ) {
				$out['snippets'][] = $this->snippet( $s, $pos, strlen( $text ) );
			}
		}
		return $out;
	}

	/**
	 * Is this match part of a URL, email, file name or lower-case slug rather than visible copy?
	 *
	 * @param string $s    Whole text.
	 * @param string $text Matched text.
	 * @param int    $pos  Byte offset of the match.
	 * @return bool
	 */
	private function is_identifier( $s, $text, $pos ) {
		$len   = strlen( $s );
		$delim = " \t\r\n\"'<>()[]{},|\\";
		$a     = $pos;
		while ( $a > 0 && false === strpos( $delim, $s[ $a - 1 ] ) ) {
			--$a;
		}
		$b = $pos + strlen( $text );
		while ( $b < $len && false === strpos( $delim, $s[ $b ] ) ) {
			++$b;
		}
		$token = substr( $s, $a, $b - $a );
		if ( preg_match( '~://|^//|^www\.|@|\.(?:php|js|css|png|jpe?g|gif|webp|svg|mp3|json)\b~i', $token ) ) {
			return true;
		}
		return strtolower( $text ) === $text && ! preg_match( '/\s/', $text );
	}

	/**
	 * About 100 characters around a match, whitespace collapsed.
	 *
	 * @param string $s   Text.
	 * @param int    $pos Byte offset.
	 * @param int    $len Match length.
	 * @return string
	 */
	private function snippet( $s, $pos, $len ) {
		$start = max( 0, $pos - 45 );
		$size  = $len + 90;
		$frag  = function_exists( 'mb_strcut' ) ? mb_strcut( $s, $start, $size, 'UTF-8' ) : substr( $s, $start, $size );
		$clean = preg_replace( '/\s+/u', ' ', $frag );
		$frag  = null === $clean ? preg_replace( '/\s+/', ' ', $frag ) : $clean;
		return ( $start > 0 ? '...' : '' ) . trim( (string) $frag ) . ( $start + $size < strlen( $s ) ? '...' : '' );
	}

	/**
	 * COVERED (a deck item targets it), COVERED-LEAVE (listed in "leave") or UNCOVERED.
	 *
	 * @param string $key Coverage key.
	 * @return string
	 */
	private function coverage( $key ) {
		foreach ( $this->deck_keys as $k => $id ) {
			if ( $key === $k || 0 === strpos( $key, $k . '/' ) ) {
				return 'COVERED';
			}
		}
		foreach ( $this->leave as $k => $id ) {
			if ( $key === $k || 0 === strpos( $key, $k . '/' ) || ( false !== strpos( $k, '*' ) && ( fnmatch( $k, $key ) || fnmatch( $k . '/*', $key ) ) ) ) {
				return 'COVERED-LEAVE';
			}
		}
		return 'UNCOVERED';
	}

	/* ------------------------------------------------------------------ */
	/* Mode: export                                                        */
	/* ------------------------------------------------------------------ */

	/**
	 * Write a deck skeleton with the current exact values for every target.
	 */
	private function mode_export() {
		$p = $this->params( false );
		if ( 2 !== count( $p ) ) {
			$this->usage();
		}
		$tfile = $this->path( $p[0] );
		$out   = $this->path( $p[1] );
		$t     = $this->read_json( $tfile );
		if ( ! $t || empty( $t['targets'] ) || ! is_array( $t['targets'] ) ) {
			WP_CLI::error( 'No "targets" list in ' . $tfile );
		}
		$side  = dirname( $out ) . '/export-content-' . $this->site;
		$items = array();
		$ids   = array();
		foreach ( $t['targets'] as $i => $target ) {
			$where  = sprintf( 'targets[%d]', $i );
			$target = $this->check_locator( $target, $where );
			if ( empty( $target['id'] ) || isset( $ids[ $target['id'] ] ) ) {
				WP_CLI::error( $where . ': missing or duplicate "id".' );
			}
			$ids[ $target['id'] ] = true;
			$read                 = $this->read_loc( $this->loc_of( $target ) );
			$item                 = $target;
			if ( isset( $read['post_type'] ) ) {
				$item['post_type'] = $read['post_type'];
			}
			if ( ! $read['ok'] ) {
				$item['error'] = $read['why'];
				$item['old']   = null;
				$item['new']   = null;
				WP_CLI::warning( $target['id'] . ': ' . $read['why'] );
			} elseif ( 'post_content_edits' === $target['type'] ) {
				$old                        = $this->tok( (string) $read['value'] );
				$m                          = $this->brand_matches( (string) $read['value'], -1 );
				$item['content_old']        = $old;
				$item['content_old_sha256'] = hash( 'sha256', $old );
				$item['hits']               = $m['snippets'];
				$item['edits']              = array();
				$this->write_side( $side, $target['id'], $old );
			} elseif ( ! $read['exists'] ) {
				$item['old']    = null;
				$item['absent'] = true;
				$item['new']    = null;
			} else {
				$old                = $this->tok( $read['value'] );
				$item['old']        = $old;
				$item['old_sha256'] = $this->sha_of( $old );
				$item['new']        = null;
				if ( is_string( $read['value'] ) ) {
					$m            = $this->brand_matches( $read['value'], -1 );
					$item['hits'] = $m['snippets'];
					if ( strlen( $old ) > 400 ) {
						$this->write_side( $side, $target['id'], $old );
					}
				}
			}
			$items[] = $item;
			WP_CLI::log( sprintf( '  %-28s %s%s', $target['id'], $this->key_of( $target ), $read['ok'] ? ( $read['exists'] ? '' : '  (absent)' ) : '  ERROR: ' . $read['why'] ) );
		}
		$this->write_json(
			$out,
			array(
				'tool'         => self::TOOL,
				'version'      => self::VERSION,
				'mode'         => 'export',
				'site'         => $this->site,
				'home'         => $this->home,
				'exported_utc' => $this->started_utc,
				'note'         => 'Deck skeleton. Copy to copy-deck.json and fill every "new" (or "new_file", or "edits" for post_content_edits). "hits", "content_old" and "post_type" are for review only. {{home}} = home_url().',
				'items'        => $items,
				'leave'        => isset( $t['leave'] ) && is_array( $t['leave'] ) ? $t['leave'] : array(),
			),
			false
		);
		WP_CLI::success( sprintf( 'Exported %d items to %s (long values also in %s/).', count( $items ), $out, $side ) );
	}

	/**
	 * Write a long exported value to a side file for easier editing.
	 *
	 * @param string $dir  Folder.
	 * @param string $id   Item id.
	 * @param string $text Value.
	 */
	private function write_side( $dir, $id, $text ) {
		if ( ! wp_mkdir_p( $dir ) ) {
			return;
		}
		file_put_contents( $dir . '/' . $this->safe_name( $id ) . '.txt', $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/* ------------------------------------------------------------------ */
	/* Mode: dry-run                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Evaluate every deck item, write diffs for long fields, fail on any MISS.
	 */
	private function mode_dry_run() {
		$p = $this->params( false );
		if ( count( $p ) > 1 ) {
			$this->usage();
		}
		$this->review_dir = $p ? $this->path( $p[0] ) : $this->dir . '/review-' . $this->site . '-' . gmdate( 'Ymd\THis\Z' );
		$this->load_deck( true );
		$res = $this->evaluate_all( true );
		WP_CLI::log( '' );
		if ( is_dir( $this->review_dir ) ) {
			WP_CLI::log( 'Diffs of long fields: ' . $this->review_dir . '  (also <id>.old.txt / <id>.new.txt for `diff -u`)' );
		}
		if ( $res['tally']['MISS'] || $res['tally']['BLOCKS'] ) {
			WP_CLI::error( sprintf( '%d MISS, %d block-structure changes not allowed. apply would refuse. Nothing was written.', $res['tally']['MISS'], $res['tally']['BLOCKS'] ) );
		}
		WP_CLI::success( 'Dry run only. Nothing was written.' );
	}

	/**
	 * Evaluate and print every deck item.
	 *
	 * @param bool $review Write review diffs.
	 * @return array{tally: array, evals: array}
	 */
	private function evaluate_all( $review ) {
		$tally = array(
			'CHANGE'       => 0,
			'ALREADY'      => 0,
			'MISS'         => 0,
			'NOT_FOR_SITE' => 0,
			'BLOCKS'       => 0,
		);
		$evals = array();
		WP_CLI::log( '' );
		WP_CLI::log( sprintf( '== Deck: %d items ==', count( $this->items ) ) );
		foreach ( $this->items as $item ) {
			$e = $this->evaluate( $item );
			++$tally[ $e['state'] ];
			$evals[] = $e;
			$loc     = $this->key_of( $item );
			WP_CLI::log( sprintf( '[%s] %s  %s%s', $e['state'], $item['id'], $loc, '' !== $e['why'] ? '  -- ' . $e['why'] : '' ) );
			if ( 'CHANGE' === $e['state'] ) {
				$old = $e['read']['exists'] ? $e['read']['value'] : null;
				if ( $this->is_long( $old ) || $this->is_long( $e['new'] ) ) {
					$stats = $review ? $this->write_review( $item['id'], (string) $old, (string) $e['new'] ) : '';
					WP_CLI::log( sprintf( '      %d -> %d bytes%s', strlen( (string) $old ), strlen( (string) $e['new'] ), $stats ) );
				} else {
					WP_CLI::log( '      - ' . ( $e['read']['exists'] ? $this->display( $old ) : '(absent)' ) );
					WP_CLI::log( '      + ' . $this->display( $e['new'] ) );
				}
				if ( '' !== $e['blocks'] ) {
					if ( empty( $item['allow_block_change'] ) ) {
						++$tally['BLOCKS'];
						WP_CLI::log( '      ! BLOCK STRUCTURE CHANGES: ' . $e['blocks'] . ' (set "allow_block_change": true on the item if intended)' );
					} else {
						WP_CLI::log( '      block structure changes (allowed by the item): ' . $e['blocks'] );
					}
				}
			}
		}
		WP_CLI::log( '' );
		WP_CLI::log( sprintf( 'CHANGE %d, ALREADY %d, MISS %d, NOT_FOR_SITE %d, block-structure flags %d.', $tally['CHANGE'], $tally['ALREADY'], $tally['MISS'], $tally['NOT_FOR_SITE'], $tally['BLOCKS'] ) );
		return array(
			'tally' => $tally,
			'evals' => $evals,
		);
	}

	/**
	 * State of one deck item on this site.
	 *
	 * @param array $item Deck item.
	 * @return array{state: string, why: string, read: array|null, new: mixed, blocks: string, item: array}
	 */
	private function evaluate( $item ) {
		$r = array(
			'state'  => 'MISS',
			'why'    => '',
			'read'   => null,
			'new'    => null,
			'blocks' => '',
			'item'   => $item,
		);
		if ( ! empty( $item['sites'] ) && ! in_array( $this->site, (array) $item['sites'], true ) ) {
			$r['state'] = 'NOT_FOR_SITE';
			return $r;
		}
		$read      = $this->read_loc( $this->loc_of( $item ) );
		$r['read'] = $read;
		if ( ! $read['ok'] ) {
			$r['why'] = $read['why'];
			return $r;
		}
		if ( 'post_content_edits' === $item['type'] ) {
			return $this->evaluate_edits( $item, $read, $r );
		}
		if ( ! array_key_exists( 'new', $item ) || null === $item['new'] ) {
			$r['why'] = 'no "new" value yet';
			return $r;
		}
		list( $ok, $new ) = $this->resolve_new( $item['new'] );
		if ( ! $ok ) {
			$r['why'] = $new;
			return $r;
		}
		if ( $read['exists'] && is_int( $read['value'] ) && is_string( $new ) && ctype_digit( $new ) ) {
			$new = (int) $new; // Keep an integer an integer (e.g. Rank Math *_id keys).
		}
		$r['new'] = $new;
		if ( 'option' === $item['type'] && is_string( $new ) ) {
			$san = sanitize_option( $item['option'], $new );
			if ( $san !== $new ) {
				$r['why'] = 'WordPress would save this option as ' . $this->display( $san ) . '; put that in "new"';
				return $r;
			}
		}
		if ( $read['exists'] && $this->same( $read['value'], $new ) ) {
			$r['state'] = 'ALREADY';
			return $r;
		}
		$why = '';
		if ( ! $this->old_matches( $item, $read, $why ) ) {
			$r['why'] = $why;
			return $r;
		}
		$r['state'] = 'CHANGE';
		if ( 'post_field' === $item['type'] && 'post_content' === $item['field'] ) {
			$r['blocks'] = $this->blocks_differ( (string) $read['value'], (string) $new );
		}
		return $r;
	}

	/**
	 * State of a post_content_edits item: every find must occur exactly "count" times.
	 *
	 * @param array $item Deck item.
	 * @param array $read Current value.
	 * @param array $r    Result so far.
	 * @return array
	 */
	private function evaluate_edits( $item, $read, $r ) {
		$content = (string) $read['value'];
		$c       = $content;
		$bad     = array();
		foreach ( $item['edits'] as $k => $e ) {
			$find = $this->detok( $e['find'] );
			$n    = substr_count( $c, $find );
			if ( $n !== $e['count'] ) {
				$bad[] = sprintf( 'edit %d: "%s" occurs %d times, expected %d', $k + 1, $this->short( $find, 60 ), $n, $e['count'] );
				continue;
			}
			$c = str_replace( $find, $this->detok( $e['replace'] ), $c );
		}
		if ( ! $bad ) {
			$r['state']  = 'CHANGE';
			$r['new']    = $c;
			$r['blocks'] = $this->blocks_differ( $content, $c );
			return $r;
		}
		$done = true;
		foreach ( $item['edits'] as $e ) {
			if ( 0 !== substr_count( $content, $this->detok( $e['find'] ) ) || substr_count( $content, $this->detok( $e['replace'] ) ) < $e['count'] ) {
				$done = false;
				break;
			}
		}
		if ( $done ) {
			$r['state'] = 'ALREADY';
			$r['new']   = $content;
			return $r;
		}
		$r['why'] = implode( '; ', $bad );
		return $r;
	}

	/**
	 * Does the current value equal the item's old / old_file / old_sha256 (all that are given)?
	 *
	 * @param array  $item Deck item.
	 * @param array  $read Current value.
	 * @param string $why  Set to the reason on mismatch.
	 * @return bool
	 */
	private function old_matches( $item, $read, &$why ) {
		$has_old = array_key_exists( 'old', $item ) && null !== $item['old'];
		$has_sha = ! empty( $item['old_sha256'] );
		if ( ! $read['exists'] ) {
			if ( ! empty( $item['create'] ) && ! $has_old && ! $has_sha ) {
				return true;
			}
			$why = empty( $item['create'] ) ? 'target does not exist (set "create": true and "old": null to create it)' : 'target does not exist but the item gives an old value';
			return false;
		}
		if ( ! $has_old && ! $has_sha ) {
			$why = 'target exists but the item has no "old" / "old_sha256"';
			return false;
		}
		if ( $has_old && ! $this->same( $read['value'], $this->detok( $item['old'] ) ) ) {
			$why = 'current value is neither old nor new: ' . $this->display( $this->tok( $read['value'] ) );
			return false;
		}
		if ( $has_sha && $this->sha_of( $this->tok( $read['value'] ) ) !== strtolower( (string) $item['old_sha256'] ) ) {
			$why = 'current value is neither old (sha256) nor new';
			return false;
		}
		return true;
	}

	/**
	 * Resolve a "new" value: {{home}} token or a media reference.
	 *
	 * @param mixed $new Deck value.
	 * @return array{0: bool, 1: mixed} [ok, value or reason]
	 */
	private function resolve_new( $new ) {
		if ( is_array( $new ) && isset( $new['media'] ) ) {
			return $this->resolve_media( $new );
		}
		return array( true, $this->detok( $new ) );
	}

	/**
	 * Find exactly one attachment whose _wp_attached_file ends with the file name.
	 *
	 * @param array $ref {media, as}.
	 * @return array{0: bool, 1: mixed}
	 */
	private function resolve_media( $ref ) {
		global $wpdb;
		$name = ltrim( (string) $ref['media'], '/' );
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = '_wp_attached_file' AND p.post_type = 'attachment' AND pm.meta_value LIKE %s",
				'%' . $wpdb->esc_like( $name )
			)
		);
		$ids  = array();
		foreach ( $rows as $row ) {
			$file = (string) $row->meta_value;
			if ( $file === $name || substr( $file, -strlen( '/' . $name ) ) === '/' . $name ) {
				$ids[ (int) $row->post_id ] = true;
			}
		}
		if ( 1 !== count( $ids ) ) {
			return array( false, sprintf( 'media "%s" matches %d attachments (exactly one is required)', $name, count( $ids ) ) );
		}
		$id = (int) key( $ids );
		if ( 'id' === $ref['as'] ) {
			return array( true, (string) $id );
		}
		$url = wp_get_attachment_url( $id );
		return $url ? array( true, $url ) : array( false, sprintf( 'no URL for attachment %d (%s)', $id, $name ) );
	}

	/**
	 * Describe a change in the block-name sequence, or '' when identical.
	 *
	 * @param string $old Old content.
	 * @param string $new New content.
	 * @return string
	 */
	private function blocks_differ( $old, $new ) {
		$a = $this->block_names( $old );
		$b = $this->block_names( $new );
		if ( $a === $b ) {
			return '';
		}
		$i = 0;
		while ( isset( $a[ $i ], $b[ $i ] ) && $a[ $i ] === $b[ $i ] ) {
			++$i;
		}
		return sprintf( '%d -> %d blocks; first difference at #%d: %s -> %s', count( $a ), count( $b ), $i + 1, isset( $a[ $i ] ) ? $a[ $i ] : '(end)', isset( $b[ $i ] ) ? $b[ $i ] : '(end)' );
	}

	/**
	 * Depth-first block names; whitespace-only freeform chunks ignored.
	 *
	 * @param string $content Post content.
	 * @return string[]
	 */
	private function block_names( $content ) {
		$out  = array();
		$walk = function ( $blocks ) use ( &$walk, &$out ) {
			foreach ( $blocks as $b ) {
				if ( null === $b['blockName'] ) {
					if ( '' === trim( (string) $b['innerHTML'] ) ) {
						continue;
					}
					$out[] = '(freeform)';
				} else {
					$out[] = $b['blockName'];
				}
				if ( ! empty( $b['innerBlocks'] ) ) {
					$out[] = '[';
					$walk( $b['innerBlocks'] );
					$out[] = ']';
				}
			}
		};
		$walk( parse_blocks( (string) $content ) );
		return $out;
	}

	/**
	 * Write <id>.old.txt, <id>.new.txt and <id>.diff into the review folder.
	 *
	 * @param string $id  Item id.
	 * @param string $old Old value.
	 * @param string $new New value.
	 * @return string Short stats for the console.
	 */
	private function write_review( $id, $old, $new ) {
		if ( ! wp_mkdir_p( $this->review_dir ) ) {
			return ' (could not create ' . $this->review_dir . ')';
		}
		$base = $this->review_dir . '/' . $this->safe_name( $id );
		$diff = $this->line_diff( $old, $new );
		// phpcs:disable WordPress.WP.AlternativeFunctions
		file_put_contents( $base . '.old.txt', $old );
		file_put_contents( $base . '.new.txt', $new );
		file_put_contents( $base . '.diff', $diff['text'] );
		// phpcs:enable
		return sprintf( ', diff -%d +%d lines: %s.diff', $diff['minus'], $diff['plus'], $base );
	}

	/**
	 * A single-hunk unified-style line diff (block comments on their own lines for readability).
	 *
	 * @param string $a Old.
	 * @param string $b New.
	 * @return array{text: string, minus: int, plus: int}
	 */
	private function line_diff( $a, $b ) {
		$split = function ( $s ) {
			$s = str_replace( array( '<!-- ', ' -->' ), array( "\n<!-- ", " -->\n" ), $s );
			return preg_split( "/\r\n|\n|\r/", $s );
		};
		$x     = $split( $a );
		$y     = $split( $b );
		$nx    = count( $x );
		$ny    = count( $y );
		$pre   = 0;
		while ( $pre < $nx && $pre < $ny && $x[ $pre ] === $y[ $pre ] ) {
			++$pre;
		}
		$suf = 0;
		while ( $suf < $nx - $pre && $suf < $ny - $pre && $x[ $nx - 1 - $suf ] === $y[ $ny - 1 - $suf ] ) {
			++$suf;
		}
		$xm  = array_slice( $x, $pre, $nx - $pre - $suf );
		$ym  = array_slice( $y, $pre, $ny - $pre - $suf );
		$n   = count( $xm );
		$m   = count( $ym );
		$out = array();
		if ( $n * $m <= 250000 ) {
			$l = array_fill( 0, $n + 1, array_fill( 0, $m + 1, 0 ) );
			for ( $i = $n - 1; $i >= 0; $i-- ) {
				for ( $j = $m - 1; $j >= 0; $j-- ) {
					$l[ $i ][ $j ] = $xm[ $i ] === $ym[ $j ] ? $l[ $i + 1 ][ $j + 1 ] + 1 : max( $l[ $i + 1 ][ $j ], $l[ $i ][ $j + 1 ] );
				}
			}
			$i = 0;
			$j = 0;
			while ( $i < $n && $j < $m ) {
				if ( $xm[ $i ] === $ym[ $j ] ) {
					$out[] = ' ' . $xm[ $i ];
					++$i;
					++$j;
				} elseif ( $l[ $i + 1 ][ $j ] >= $l[ $i ][ $j + 1 ] ) {
					$out[] = '-' . $xm[ $i ];
					++$i;
				} else {
					$out[] = '+' . $ym[ $j ];
					++$j;
				}
			}
			for ( ; $i < $n; $i++ ) {
				$out[] = '-' . $xm[ $i ];
			}
			for ( ; $j < $m; $j++ ) {
				$out[] = '+' . $ym[ $j ];
			}
		} else {
			foreach ( $xm as $line ) {
				$out[] = '-' . $line;
			}
			foreach ( $ym as $line ) {
				$out[] = '+' . $line;
			}
		}
		$ctx_before = array_slice( $x, max( 0, $pre - 3 ), min( 3, $pre ) );
		$ctx_after  = array_slice( $x, $nx - $suf, min( 3, $suf ) );
		$minus      = 0;
		$plus       = 0;
		foreach ( $out as $line ) {
			if ( '-' === $line[0] ) {
				++$minus;
			} elseif ( '+' === $line[0] ) {
				++$plus;
			}
		}
		$start = $pre - count( $ctx_before ) + 1;
		$text  = "--- old\n+++ new\n";
		$text .= sprintf( "@@ -%d,%d +%d,%d @@\n", $start, count( $ctx_before ) + $n + count( $ctx_after ), $start, count( $ctx_before ) + $m + count( $ctx_after ) );
		foreach ( $ctx_before as $line ) {
			$text .= ' ' . $line . "\n";
		}
		$text .= implode( "\n", $out ) . ( $out ? "\n" : '' );
		foreach ( $ctx_after as $line ) {
			$text .= ' ' . $line . "\n";
		}
		return array(
			'text'  => $text,
			'minus' => $minus,
			'plus'  => $plus,
		);
	}

	/* ------------------------------------------------------------------ */
	/* Mode: apply                                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Checksum, journal, write every CHANGE item, checksum again and compare.
	 */
	private function mode_apply() {
		$p = $this->params( true );
		if ( 1 !== count( $p ) ) {
			$this->usage();
		}
		$dir = untrailingslashit( $this->path( $p[0] ) );
		$this->load_deck( true );
		$res = $this->evaluate_all( false );
		if ( $res['tally']['MISS'] ) {
			WP_CLI::error( sprintf( 'Refusing to apply: %d MISS (see above). Nothing was written.', $res['tally']['MISS'] ) );
		}
		if ( $res['tally']['BLOCKS'] ) {
			WP_CLI::error( sprintf( 'Refusing to apply: %d block-structure changes without "allow_block_change". Nothing was written.', $res['tally']['BLOCKS'] ) );
		}
		foreach ( $res['evals'] as $e ) {
			if ( 'CHANGE' !== $e['state'] ) {
				continue;
			}
			$loc = $this->loc_of( $e['item'] );
			$op  = array(
				'id'           => $e['item']['id'],
				'item_type'    => $e['item']['type'],
				'label'        => $e['item']['id'] . ': ' . $this->key_of( $loc ),
				'loc'          => $loc,
				'before'       => $this->snap( $e['read'] ),
				'after'        => $e['new'],
				'after_exists' => true,
				'state'        => 'planned',
				'message'      => '',
			);
			if ( 'post_field' === $loc['type'] && 'page' === $e['read']['post_type'] && in_array( $loc['field'], array( 'post_title', 'post_content' ), true ) ) {
				$op['modified'] = array(
					'post_modified'     => current_time( 'mysql' ),
					'post_modified_gmt' => current_time( 'mysql', true ),
				);
			}
			$this->ops[] = $op;
		}
		if ( ! $this->ops ) {
			WP_CLI::success( 'Nothing to change: every item is ALREADY or NOT_FOR_SITE.' );
			return;
		}
		if ( ! wp_mkdir_p( $dir ) ) {
			WP_CLI::error( 'Cannot create ' . $dir . '. Nothing was written.' );
		}
		chmod( $dir, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$stamp = gmdate( 'Ymd\THis\Z' );

		WP_CLI::log( '' );
		WP_CLI::log( 'Checksumming every post and term before writing...' );
		$before = $this->manifest( true );
		$this->write_json( $dir . '/manifest-before-' . $stamp . '.json', $before, true );

		$this->journal_mode = 'apply';
		$this->journal_file = sprintf( '%s/rebrand-journal-%s-%s.json', $dir, $this->site, $stamp );
		$this->write_journal();
		WP_CLI::log( 'Journal written before any change: ' . $this->journal_file );
		WP_CLI::log( sprintf( 'Undo with: wp eval-file %s/rebrand-db.php restore %s %s --path=%s', $this->dir, $this->journal_file, $this->site, untrailingslashit( ABSPATH ) ) );

		$tally = $this->execute();

		WP_CLI::log( '' );
		WP_CLI::log( 'Checksumming again...' );
		$after = $this->manifest( false );
		$this->write_json( $dir . '/manifest-after-' . $stamp . '.json', $after, true );
		$protected = $this->report_manifest_diff( $before, $after );

		WP_CLI::log( '' );
		WP_CLI::log( sprintf( 'Applied: %d done, %d failed. Journal: %s', $tally['done'], $tally['failed'], $this->journal_file ) );
		WP_CLI::log( 'Purge caches now: wp cache flush && wp sg purge (with the same --path).' );
		if ( $tally['failed'] || $protected ) {
			WP_CLI::error( 'Failures or protected content changed (see above). Undo with the restore command printed above.' );
		}
		WP_CLI::success( 'Done.' );
	}

	/**
	 * Print what changed between two manifests; return how many historical posts changed.
	 *
	 * @param array $before Manifest.
	 * @param array $after  Manifest.
	 * @return int
	 */
	private function report_manifest_diff( $before, $after ) {
		$post_ids = array();
		$term_ids = array();
		foreach ( $this->ops as $op ) {
			if ( isset( $op['loc']['post_id'] ) ) {
				$post_ids[ (int) $op['loc']['post_id'] ] = true;
			}
			if ( isset( $op['loc']['term_id'] ) ) {
				$term_ids[ (int) $op['loc']['term_id'] ] = true;
			}
		}
		$protected = 0;
		WP_CLI::log( '== Changed according to the checksums ==' );
		foreach ( $this->diff_manifests( $before, $after ) as $c ) {
			$in_deck = 'posts' === $c['kind'] ? isset( $post_ids[ $c['id'] ] ) : isset( $term_ids[ $c['id'] ] );
			$hist    = 'posts' === $c['kind'] && in_array( $c['type'], self::HISTORICAL_TYPES, true );
			if ( $hist && 'added' !== $c['how'] ) {
				++$protected;
			}
			WP_CLI::log( sprintf( '  %s%s %d (%s) %s%s', $hist && 'added' !== $c['how'] ? 'PROTECTED ' : '', 'posts' === $c['kind'] ? 'post' : 'term', $c['id'], $c['type'], $c['how'], $in_deck ? '' : '  [NOT IN DECK: someone else, or a side effect]' ) );
		}
		foreach ( $this->ops as $op ) {
			if ( ! isset( $op['loc']['post_id'] ) && ! isset( $op['loc']['term_id'] ) ) {
				WP_CLI::log( sprintf( '  %s [%s]', $op['label'], $op['state'] ) );
			}
		}
		WP_CLI::log( sprintf( 'PROTECTED: %d changed', $protected ) );
		return $protected;
	}

	/* ------------------------------------------------------------------ */
	/* Mode: restore                                                       */
	/* ------------------------------------------------------------------ */

	/**
	 * Undo a journal newest first, only where the value is still what was written.
	 */
	private function mode_restore() {
		$p = $this->params( true );
		if ( 1 !== count( $p ) ) {
			$this->usage();
		}
		$file = $this->path( $p[0] );
		$data = $this->read_json( $file );
		if ( ! $data || empty( $data['ops'] ) || ! is_array( $data['ops'] ) ) {
			WP_CLI::error( 'Journal missing, unreadable or without operations: ' . $file );
		}
		if ( ( isset( $data['tool'] ) ? $data['tool'] : '' ) !== self::TOOL ) {
			WP_CLI::error( 'Not a journal written by this tool: ' . $file );
		}
		if ( ( isset( $data['home'] ) ? $data['home'] : '' ) !== $this->home || ( isset( $data['abspath'] ) ? $data['abspath'] : '' ) !== ABSPATH ) {
			WP_CLI::error( sprintf( 'This journal belongs to %s (%s), not to this site %s (%s). Nothing was written.', isset( $data['home'] ) ? $data['home'] : '?', isset( $data['abspath'] ) ? $data['abspath'] : '?', $this->home, ABSPATH ) );
		}
		WP_CLI::log( sprintf( 'Journal of "%s" started %s, last updated %s, %d operations.', isset( $data['mode'] ) ? $data['mode'] : '?', isset( $data['started_utc'] ) ? $data['started_utc'] : '?', isset( $data['updated_utc'] ) ? $data['updated_utc'] : '?', count( $data['ops'] ) ) );
		$conflicts = 0;
		foreach ( array_reverse( $data['ops'] ) as $old ) {
			$state = isset( $old['state'] ) ? $old['state'] : 'planned';
			// "running": the run stopped during this write; the checks below cover both outcomes.
			if ( ! in_array( $state, array( 'done', 'failed', 'running' ), true ) || empty( $old['loc'] ) ) {
				continue;
			}
			$b     = isset( $old['before'] ) ? $old['before'] : array( 'exists' => false );
			$b_val = isset( $b['value'] ) ? $b['value'] : null;
			$cur   = $this->read_loc( $old['loc'] );
			if ( ! $cur['ok'] ) {
				++$conflicts;
				WP_CLI::log( sprintf( '  CONFLICT %s: %s', $old['label'], $cur['why'] ) );
				continue;
			}
			if ( $this->matches( $cur, ! empty( $b['exists'] ), $b_val ) ) {
				WP_CLI::log( sprintf( '  already as before: %s', $old['label'] ) );
				continue;
			}
			if ( ! $this->matches( $cur, ! empty( $old['after_exists'] ), isset( $old['after'] ) ? $old['after'] : null ) ) {
				++$conflicts;
				WP_CLI::log( sprintf( '  CONFLICT %s: changed since the run (now %s); skipped', $old['label'], $cur['exists'] ? $this->display( $cur['value'] ) : '(absent)' ) );
				continue;
			}
			$op = array(
				'id'           => $old['id'],
				'item_type'    => 'restore',
				'label'        => 'restore ' . $old['label'],
				'loc'          => $old['loc'],
				'before'       => $this->snap( $cur ),
				'after'        => $b_val,
				'after_exists' => ! empty( $b['exists'] ),
				'state'        => 'planned',
				'message'      => '',
			);
			if ( ! empty( $old['modified'] ) && isset( $b['post_modified'], $b['post_modified_gmt'] ) ) {
				$op['modified'] = array(
					'post_modified'     => $b['post_modified'],
					'post_modified_gmt' => $b['post_modified_gmt'],
				);
			}
			$this->ops[] = $op;
			WP_CLI::log( '  + ' . $op['label'] );
		}
		if ( ! $this->ops ) {
			if ( $conflicts ) {
				WP_CLI::error( sprintf( 'Nothing restored; %d conflicts.', $conflicts ) );
			}
			WP_CLI::success( 'Nothing to restore.' );
			return;
		}
		$this->source       = $file;
		$this->journal_mode = 'restore';
		$this->journal_file = sprintf( '%s/rebrand-restore-%s-%s.json', dirname( $file ), $this->site, gmdate( 'Ymd\THis\Z' ) );
		$this->write_journal();
		WP_CLI::log( 'Restore journal (itself restorable): ' . $this->journal_file );
		$tally = $this->execute();
		WP_CLI::log( sprintf( 'Restored: %d done, %d failed, %d conflicts skipped.', $tally['done'], $tally['failed'], $conflicts ) );
		WP_CLI::log( 'Purge caches now: wp cache flush && wp sg purge (with the same --path).' );
		if ( $tally['failed'] ) {
			WP_CLI::error( 'Some restores failed (see above).' );
		}
		if ( $conflicts ) {
			WP_CLI::warning( 'Conflicts were skipped: those values were edited after the run. Check them by hand.' );
		}
		WP_CLI::success( 'Done.' );
	}

	/* ------------------------------------------------------------------ */
	/* Mode: sync-from (staging only)                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Set staging targets to the live export's values, so the deck's old values match on both.
	 */
	private function mode_sync_from() {
		if ( 'staging' !== $this->site ) {
			WP_CLI::error( 'sync-from only runs on staging. Nothing was written.' );
		}
		$p = $this->params( true );
		if ( count( $p ) < 1 || count( $p ) > 2 ) {
			$this->usage();
		}
		$file = $this->path( $p[0] );
		$data = $this->read_json( $file );
		if ( ! $data || empty( $data['items'] ) || ! is_array( $data['items'] ) ) {
			WP_CLI::error( 'No "items" in ' . $file );
		}
		if ( ( isset( $data['site'] ) ? $data['site'] : '' ) !== 'live' || ( isset( $data['mode'] ) ? $data['mode'] : '' ) !== 'export' ) {
			WP_CLI::error( 'sync-from needs an export made on live (site "live", mode "export"): ' . $file );
		}
		foreach ( $data['items'] as $i => $item ) {
			$where = sprintf( 'items[%d]', $i );
			$item  = $this->check_locator( $item, $where );
			$this->guard_post( $item, $where );
			$id = isset( $item['id'] ) ? (string) $item['id'] : $where;
			if ( ! empty( $item['error'] ) ) {
				WP_CLI::log( sprintf( '  skip %s: live export had an error (%s)', $id, $item['error'] ) );
				continue;
			}
			if ( 'post_content_edits' === $item['type'] ) {
				$live_exists = isset( $item['content_old'] );
				$live        = $live_exists ? $item['content_old'] : null;
			} else {
				$live_exists = empty( $item['absent'] ) && array_key_exists( 'old', $item ) && null !== $item['old'];
				$live        = $live_exists ? $item['old'] : null;
			}
			$live = $this->detok( $live );
			$loc  = $this->loc_of( $item );
			$cur  = $this->read_loc( $loc );
			if ( ! $cur['ok'] ) {
				WP_CLI::log( sprintf( '  SKIP %s: %s', $id, $cur['why'] ) );
				continue;
			}
			if ( $this->matches( $cur, $live_exists, $live ) ) {
				WP_CLI::log( sprintf( '  same %s', $id ) );
				continue;
			}
			$op          = array(
				'id'           => $id,
				'item_type'    => 'sync-from',
				'label'        => $id . ': ' . $this->key_of( $loc ),
				'loc'          => $loc,
				'before'       => $this->snap( $cur ),
				'after'        => $live,
				'after_exists' => $live_exists,
				'state'        => 'planned',
				'message'      => '',
			);
			$this->ops[] = $op;
			WP_CLI::log( sprintf( '  + %s  (%s -> %s)', $op['label'], $cur['exists'] ? $this->display( $this->tok( $cur['value'] ) ) : '(absent)', $live_exists ? $this->display( $this->tok( $live ) ) : '(absent)' ) );
		}
		if ( ! $this->ops ) {
			WP_CLI::success( 'Staging already matches the live export.' );
			return;
		}
		$dir = isset( $p[1] ) ? untrailingslashit( $this->path( $p[1] ) ) : $this->home_dir() . '/claude-backups/rebrand-sync-' . gmdate( 'Ymd\THis\Z' );
		if ( '' === $this->home_dir() && ! isset( $p[1] ) ) {
			WP_CLI::error( 'HOME is not set; pass a backup dir. Nothing was written.' );
		}
		if ( ! wp_mkdir_p( $dir ) ) {
			WP_CLI::error( 'Cannot create ' . $dir . '. Nothing was written.' );
		}
		chmod( $dir, 0700 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$this->source       = $file;
		$this->journal_mode = 'sync-from';
		$this->journal_file = sprintf( '%s/rebrand-sync-%s-%s.json', $dir, $this->site, gmdate( 'Ymd\THis\Z' ) );
		$this->write_journal();
		WP_CLI::log( 'Journal written before any change: ' . $this->journal_file );
		WP_CLI::log( sprintf( 'Undo with: wp eval-file %s/rebrand-db.php restore %s staging --path=%s', $this->dir, $this->journal_file, untrailingslashit( ABSPATH ) ) );
		$tally = $this->execute();
		WP_CLI::log( sprintf( 'Synced: %d done, %d failed.', $tally['done'], $tally['failed'] ) );
		if ( $tally['failed'] ) {
			WP_CLI::error( 'Some writes failed (see above).' );
		}
		WP_CLI::success( 'Done. Run dry-run next.' );
	}

	/* ------------------------------------------------------------------ */
	/* Mode: checksum                                                      */
	/* ------------------------------------------------------------------ */

	/**
	 * Write a checksum manifest of every non-revision post and every term.
	 */
	private function mode_checksum() {
		$p = $this->params( false );
		if ( 1 !== count( $p ) ) {
			$this->usage();
		}
		$out = $this->path( $p[0] );
		$m   = $this->manifest( true );
		$this->write_json( $out, $m, true );
		WP_CLI::success( sprintf( 'Checksums of %d posts and %d terms written to %s', count( $m['posts'] ), count( $m['terms'] ), $out ) );
	}

	/**
	 * Per-post and per-term sha256 of everything a rebrand could touch.
	 *
	 * @param bool $verbose Print the excluded meta keys.
	 * @return array
	 */
	private function manifest( $verbose ) {
		global $wpdb;
		$excluded = array();
		$posts    = array();
		$ids      = array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type <> 'revision' ORDER BY ID" ) );
		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			$in   = implode( ',', $chunk );
			$rows = $wpdb->get_results( "SELECT ID, post_type, post_title, post_content, post_excerpt, post_name, post_status, post_parent, post_author, post_date_gmt, post_modified_gmt, menu_order FROM {$wpdb->posts} WHERE ID IN ({$in})", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL
			$meta = array();
			foreach ( $wpdb->get_results( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id IN ({$in}) ORDER BY meta_id", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL
				$pattern = $this->excluded_meta( $row['meta_key'] );
				if ( '' !== $pattern ) {
					if ( ! isset( $excluded[ $pattern ] ) ) {
						$excluded[ $pattern ] = array(
							'rows' => 0,
							'keys' => array(),
						);
					}
					++$excluded[ $pattern ]['rows'];
					$excluded[ $pattern ]['keys'][ $row['meta_key'] ] = true;
					continue;
				}
				$meta[ (int) $row['post_id'] ][] = array( $row['meta_key'], $row['meta_value'] );
			}
			$rel = array();
			foreach ( $wpdb->get_results( "SELECT object_id, term_taxonomy_id FROM {$wpdb->term_relationships} WHERE object_id IN ({$in}) ORDER BY object_id, term_taxonomy_id", ARRAY_A ) as $row ) { // phpcs:ignore WordPress.DB.PreparedSQL
				$rel[ (int) $row['object_id'] ][] = (int) $row['term_taxonomy_id'];
			}
			foreach ( $rows as $row ) {
				$id           = (int) $row['ID'];
				$blob         = array(
					'post'  => $row,
					'meta'  => isset( $meta[ $id ] ) ? $meta[ $id ] : array(),
					'terms' => isset( $rel[ $id ] ) ? $rel[ $id ] : array(),
				);
				$posts[ $id ] = array(
					'type'   => $row['post_type'],
					'status' => $row['post_status'],
					'sha256' => hash( 'sha256', serialize( $blob ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
				);
			}
		}

		$tmeta = array();
		foreach ( $wpdb->get_results( "SELECT term_id, meta_key, meta_value FROM {$wpdb->termmeta} ORDER BY meta_id", ARRAY_A ) as $row ) {
			$tmeta[ (int) $row['term_id'] ][] = array( $row['meta_key'], $row['meta_value'] );
		}
		$trows = array();
		foreach ( $wpdb->get_results( "SELECT t.term_id, t.name, t.slug, tt.taxonomy, tt.description, tt.parent FROM {$wpdb->terms} t LEFT JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id ORDER BY t.term_id, tt.term_taxonomy_id", ARRAY_A ) as $row ) {
			$trows[ (int) $row['term_id'] ][] = $row;
		}
		$terms = array();
		foreach ( $trows as $id => $rows ) {
			$terms[ $id ] = array(
				'type'   => implode( ',', array_unique( wp_list_pluck( $rows, 'taxonomy' ) ) ),
				'sha256' => hash(
					'sha256',
					serialize( // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
						array(
							'rows' => $rows,
							'meta' => isset( $tmeta[ $id ] ) ? $tmeta[ $id ] : array(),
						)
					)
				),
			);
		}

		$summary = array();
		foreach ( $excluded as $pattern => $e ) {
			$keys                = array_keys( $e['keys'] );
			$summary[ $pattern ] = array(
				'rows'          => $e['rows'],
				'distinct_keys' => count( $keys ),
				'sample'        => array_slice( $keys, 0, 5 ),
			);
		}
		if ( $verbose ) {
			WP_CLI::log( 'Post meta excluded from the checksums: ' . implode( ', ', self::CHECKSUM_SKIP_META ) );
			foreach ( $summary as $pattern => $s ) {
				WP_CLI::log( sprintf( '  %-20s %6d rows, %d distinct keys (e.g. %s)', $pattern, $s['rows'], $s['distinct_keys'], implode( ', ', $s['sample'] ) ) );
			}
			WP_CLI::log( sprintf( 'Hashed fields per post: post_title, post_content, post_excerpt, post_name, post_status, post_parent, post_author, post_date_gmt, post_modified_gmt, menu_order, post_type, all other post meta (by meta_id), term relationships. %d posts, %d terms.', count( $posts ), count( $terms ) ) );
		}
		return array(
			'tool'          => self::TOOL,
			'version'       => self::VERSION,
			'mode'          => 'checksum',
			'site'          => $this->site,
			'home'          => $this->home,
			'abspath'       => ABSPATH,
			'created_utc'   => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'excluded_meta' => $summary,
			'posts'         => $posts,
			'terms'         => $terms,
		);
	}

	/**
	 * The CHECKSUM_SKIP_META pattern a meta key falls under, or ''.
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	private function excluded_meta( $key ) {
		foreach ( self::CHECKSUM_SKIP_META as $pattern ) {
			if ( '*' === substr( $pattern, -1 ) ? 0 === strpos( $key, substr( $pattern, 0, -1 ) ) : $key === $pattern ) {
				// _oembed_time_* is reported under _oembed_* (checked first); both are excluded.
				return $pattern;
			}
		}
		return '';
	}

	/**
	 * Posts and terms that were added, removed or changed between two manifests.
	 *
	 * @param array $a Before.
	 * @param array $b After.
	 * @return array[]
	 */
	private function diff_manifests( $a, $b ) {
		$out = array();
		foreach ( array( 'posts', 'terms' ) as $kind ) {
			$ids = array_unique( array_merge( array_keys( $a[ $kind ] ), array_keys( $b[ $kind ] ) ) );
			sort( $ids );
			foreach ( $ids as $id ) {
				$x = isset( $a[ $kind ][ $id ] ) ? $a[ $kind ][ $id ] : null;
				$y = isset( $b[ $kind ][ $id ] ) ? $b[ $kind ][ $id ] : null;
				if ( $x && $y && $x['sha256'] === $y['sha256'] ) {
					continue;
				}
				$out[] = array(
					'kind' => $kind,
					'id'   => (int) $id,
					'type' => $y ? $y['type'] : $x['type'],
					'how'  => ! $x ? 'added' : ( ! $y ? 'removed' : 'changed' ),
				);
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Deck loading and validation                                         */
	/* ------------------------------------------------------------------ */

	/**
	 * Load and validate copy-deck.json. Aborts on any invalid item or forbidden post type.
	 *
	 * @param bool $required Abort if the deck is missing.
	 */
	private function load_deck( $required ) {
		$file = $this->dir . '/copy-deck.json';
		if ( ! is_readable( $file ) ) {
			if ( $required ) {
				WP_CLI::error( 'copy-deck.json not found: ' . $file );
			}
			WP_CLI::log( 'No copy-deck.json: every hit is UNCOVERED.' );
			return;
		}
		$data = $this->read_json( $file );
		if ( ! $data || ! isset( $data['items'] ) || ! is_array( $data['items'] ) ) {
			WP_CLI::error( 'copy-deck.json is not valid JSON with an "items" list.' );
		}
		$seen = array();
		foreach ( $data['items'] as $i => $item ) {
			$where = sprintf( 'copy-deck items[%d]', $i );
			$item  = $this->check_locator( $item, $where );
			if ( empty( $item['id'] ) || isset( $seen[ $item['id'] ] ) ) {
				WP_CLI::error( $where . ': missing or duplicate "id".' );
			}
			$item = $this->check_values( $item, $where . ' (' . $item['id'] . ')' );
			$this->guard_post( $item, $where . ' (' . $item['id'] . ')' );
			$seen[ $item['id'] ] = true;
			$this->items[]       = $item;
			$this->deck_keys[ $this->key_of( $item ) ] = $item['id'];
		}
		$leave = isset( $data['leave'] ) && is_array( $data['leave'] ) ? $data['leave'] : array();
		foreach ( $leave as $i => $l ) {
			if ( ! is_array( $l ) ) {
				continue;
			}
			$key                 = isset( $l['key'] ) ? (string) $l['key'] : $this->key_of( $this->check_locator( $l, sprintf( 'copy-deck leave[%d]', $i ) ) );
			$this->leave[ $key ] = isset( $l['id'] ) ? (string) $l['id'] : $key;
		}
		WP_CLI::log( sprintf( 'Deck: %d items, %d leave entries.', count( $this->items ), count( $this->leave ) ) );
	}

	/**
	 * Validate an item's type and locator fields.
	 *
	 * @param mixed  $item  Item.
	 * @param string $where Label for errors.
	 * @return array
	 */
	private function check_locator( $item, $where ) {
		if ( ! is_array( $item ) ) {
			WP_CLI::error( $where . ': not an object.' );
		}
		$type = isset( $item['type'] ) ? (string) $item['type'] : '';
		if ( ! in_array( $type, self::ITEM_TYPES, true ) ) {
			WP_CLI::error( sprintf( '%s: unknown type "%s" (one of %s).', $where, $type, implode( ', ', self::ITEM_TYPES ) ) );
		}
		$need = array(
			'option'             => array( 'option' ),
			'option_key'         => array( 'option', 'path' ),
			'post_field'         => array( 'post_id', 'field' ),
			'post_content_edits' => array( 'post_id' ),
			'post_meta'          => array( 'post_id', 'key' ),
			'term_field'         => array( 'taxonomy', 'term_id', 'field' ),
			'term_meta'          => array( 'term_id', 'key' ),
			'user_field'         => array( 'user_id', 'field' ),
			'user_meta'          => array( 'user_id', 'key' ),
		);
		foreach ( $need[ $type ] as $f ) {
			if ( ! isset( $item[ $f ] ) || '' === $item[ $f ] || array() === $item[ $f ] ) {
				WP_CLI::error( sprintf( '%s: %s needs "%s".', $where, $type, $f ) );
			}
		}
		foreach ( array( 'post_id', 'term_id', 'user_id' ) as $f ) {
			if ( isset( $item[ $f ] ) ) {
				$item[ $f ] = (int) $item[ $f ];
			}
		}
		if ( 'option_key' === $type ) {
			if ( ! is_array( $item['path'] ) ) {
				WP_CLI::error( $where . ': "path" must be a list of keys.' );
			}
			$item['path'] = array_values( array_map( 'strval', $item['path'] ) );
		}
		$fields = array(
			'post_field' => array( 'post_title', 'post_excerpt', 'post_content' ),
			'term_field' => array( 'name', 'description' ),
			'user_field' => array( 'display_name' ),
		);
		if ( isset( $fields[ $type ] ) && ! in_array( $item['field'], $fields[ $type ], true ) ) {
			WP_CLI::error( sprintf( '%s: %s field must be one of %s.', $where, $type, implode( ', ', $fields[ $type ] ) ) );
		}
		return $item;
	}

	/**
	 * Validate old/new values, load content files, check edits and media references.
	 *
	 * @param array  $item  Item.
	 * @param string $where Label for errors.
	 * @return array
	 */
	private function check_values( $item, $where ) {
		$keep_nl = ! empty( $item['keep_trailing_newline'] );
		if ( isset( $item['new_file'] ) ) {
			$item['new'] = $this->content_file( $item['new_file'], $where, $keep_nl );
		}
		if ( isset( $item['old_file'] ) ) {
			$item['old'] = $this->content_file( $item['old_file'], $where, $keep_nl );
		}
		if ( ! empty( $item['sites'] ) && array_diff( (array) $item['sites'], array( 'live', 'staging' ) ) ) {
			WP_CLI::error( $where . ': "sites" may only contain "live" and "staging".' );
		}
		if ( 'post_content_edits' === $item['type'] ) {
			if ( empty( $item['edits'] ) || ! is_array( $item['edits'] ) ) {
				WP_CLI::error( $where . ': post_content_edits needs a non-empty "edits" list.' );
			}
			foreach ( array_values( $item['edits'] ) as $k => $e ) {
				if ( ! is_array( $e ) || ! isset( $e['find'], $e['replace'], $e['count'] ) || ! is_string( $e['find'] ) || ! is_string( $e['replace'] ) || '' === $e['find'] || (int) $e['count'] < 1 ) {
					WP_CLI::error( sprintf( '%s: edit %d needs a non-empty "find", a "replace" and "count" >= 1.', $where, $k + 1 ) );
				}
				if ( false !== strpos( $e['replace'], $e['find'] ) ) {
					WP_CLI::error( sprintf( '%s: edit %d "replace" contains its own "find"; use a longer find so a re-run cannot apply it twice.', $where, $k + 1 ) );
				}
				$item['edits'][ $k ] = array(
					'find'    => $e['find'],
					'replace' => $e['replace'],
					'count'   => (int) $e['count'],
				);
			}
			$item['edits'] = array_values( $item['edits'] );
			return $item;
		}
		if ( array_key_exists( 'new', $item ) && null !== $item['new'] ) {
			$new = $item['new'];
			if ( is_array( $new ) && isset( $new['media'] ) ) {
				if ( ! is_string( $new['media'] ) || '' === $new['media'] || ! isset( $new['as'] ) || ! in_array( $new['as'], array( 'id', 'url' ), true ) ) {
					WP_CLI::error( $where . ': a media reference is {"media": "<file name>", "as": "id"|"url"}.' );
				}
			} elseif ( ! in_array( $item['type'], array( 'option', 'option_key' ), true ) && ! is_string( $new ) ) {
				WP_CLI::error( $where . ': "new" must be a string (or a media reference).' );
			}
		}
		return $item;
	}

	/**
	 * Abort when a post item targets a post type this tool must not write.
	 *
	 * @param array  $item  Item.
	 * @param string $where Label for errors.
	 */
	private function guard_post( $item, $where ) {
		global $wpdb;
		if ( ! isset( $item['post_id'] ) ) {
			return;
		}
		$type = $wpdb->get_var( $wpdb->prepare( "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d", $item['post_id'] ) );
		if ( null !== $type && ! in_array( $type, self::WRITE_TYPES, true ) ) {
			WP_CLI::error( sprintf( '%s: post %d is a "%s"; only %s may be written. Nothing was written.', $where, $item['post_id'], $type, implode( ', ', self::WRITE_TYPES ) ) );
		}
	}

	/**
	 * Read a file from content/ (one trailing newline removed unless asked to keep it).
	 *
	 * @param string $rel     Path relative to content/.
	 * @param string $where   Label for errors.
	 * @param bool   $keep_nl Keep the trailing newline.
	 * @return string
	 */
	private function content_file( $rel, $where, $keep_nl ) {
		$rel = (string) $rel;
		if ( '' === $rel || false !== strpos( $rel, '..' ) || '/' === $rel[0] || '\\' === $rel[0] ) {
			WP_CLI::error( $where . ': content file must be a relative path inside content/.' );
		}
		$file = $this->dir . '/content/' . $rel;
		if ( ! is_readable( $file ) ) {
			WP_CLI::error( $where . ': content file not found: ' . $file );
		}
		$text = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! $keep_nl ) {
			if ( "\r\n" === substr( $text, -2 ) ) {
				$text = substr( $text, 0, -2 );
			} elseif ( "\n" === substr( $text, -1 ) ) {
				$text = substr( $text, 0, -1 );
			}
		}
		return $text;
	}

	/**
	 * Coverage key of an item or locator ("option:name/key", "post:7:post_title", ...).
	 *
	 * @param array $item Item or locator.
	 * @return string
	 */
	private function key_of( $item ) {
		switch ( $item['type'] ) {
			case 'option':
				return 'option:' . $item['option'];
			case 'option_key':
				return 'option:' . $item['option'] . '/' . implode( '/', $item['path'] );
			case 'post_field':
				return 'post:' . $item['post_id'] . ':' . $item['field'];
			case 'post_content_edits':
				return 'post:' . $item['post_id'] . ':post_content';
			case 'post_meta':
				return 'postmeta:' . $item['post_id'] . ':' . $item['key'];
			case 'term_field':
				return 'term:' . $item['term_id'] . ':' . $item['field'];
			case 'term_meta':
				return 'termmeta:' . $item['term_id'] . ':' . $item['key'];
			case 'user_field':
				return 'user:' . $item['user_id'] . ':' . $item['field'];
			case 'user_meta':
				return 'usermeta:' . $item['user_id'] . ':' . $item['key'];
		}
		return '?';
	}

	/**
	 * The storage locator of an item (post_content_edits becomes post_field post_content).
	 *
	 * @param array $item Item.
	 * @return array
	 */
	private function loc_of( $item ) {
		$loc = array( 'type' => 'post_content_edits' === $item['type'] ? 'post_field' : $item['type'] );
		if ( 'post_content_edits' === $item['type'] ) {
			$loc['field'] = 'post_content';
		}
		foreach ( array( 'option', 'path', 'post_id', 'field', 'key', 'taxonomy', 'term_id', 'user_id' ) as $f ) {
			if ( isset( $item[ $f ] ) && ! isset( $loc[ $f ] ) ) {
				$loc[ $f ] = $item[ $f ];
			}
		}
		return $loc;
	}

	/* ------------------------------------------------------------------ */
	/* Reading and writing one locator                                     */
	/* ------------------------------------------------------------------ */

	/**
	 * Current raw value at a locator (straight from the tables, no filters or caches).
	 *
	 * @param array $loc Locator.
	 * @return array{ok: bool, why: string, exists: bool, value: mixed}
	 */
	private function read_loc( $loc ) {
		global $wpdb;
		switch ( $loc['type'] ) {
			case 'option':
				$raw = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $loc['option'] ) );
				return $raw ? $this->r_val( $this->unser( $raw->option_value ) ) : $this->r_absent();

			case 'option_key':
				$raw = $wpdb->get_row( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $loc['option'] ) );
				if ( ! $raw ) {
					return $this->r_fail( 'option ' . $loc['option'] . ' does not exist' );
				}
				$node = $this->unser( $raw->option_value );
				if ( ! is_array( $node ) ) {
					return $this->r_fail( 'option ' . $loc['option'] . ' is not an array' );
				}
				if ( $this->has_object( $node ) ) {
					return $this->r_fail( 'option ' . $loc['option'] . ' contains objects; not handled' );
				}
				foreach ( $loc['path'] as $k ) {
					if ( ! is_array( $node ) || ! array_key_exists( $k, $node ) ) {
						return $this->r_absent();
					}
					$node = $node[ $k ];
				}
				return $this->r_val( $node );

			case 'post_field':
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT post_type, post_title, post_excerpt, post_content, post_modified, post_modified_gmt FROM {$wpdb->posts} WHERE ID = %d", $loc['post_id'] ), ARRAY_A );
				if ( ! $row ) {
					return $this->r_fail( 'post ' . $loc['post_id'] . ' not found' );
				}
				return $this->r_val(
					(string) $row[ $loc['field'] ],
					array(
						'post_type'         => $row['post_type'],
						'post_modified'     => $row['post_modified'],
						'post_modified_gmt' => $row['post_modified_gmt'],
					)
				);

			case 'post_meta':
			case 'term_meta':
			case 'user_meta':
				$t     = $this->meta_table( $loc['type'] );
				$owner = (int) $loc[ $t['field'] ];
				if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t['owner']} WHERE {$t['owner_pk']} = %d", $owner ) ) ) { // phpcs:ignore WordPress.DB.PreparedSQL
					return $this->r_fail( sprintf( '%s %d not found', str_replace( '_meta', '', $loc['type'] ), $owner ) );
				}
				$rows = $wpdb->get_results( $wpdb->prepare( "SELECT {$t['pk']} AS mid, meta_value FROM {$t['table']} WHERE {$t['col']} = %d AND meta_key = %s ORDER BY {$t['pk']}", $owner, $loc['key'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL
				if ( ! $rows ) {
					return $this->r_absent();
				}
				if ( count( $rows ) > 1 ) {
					return $this->r_fail( sprintf( '%d rows for meta key %s (exactly one is required)', count( $rows ), $loc['key'] ) );
				}
				return $this->r_val( (string) $rows[0]->meta_value, array( 'meta_id' => (int) $rows[0]->mid ) );

			case 'term_field':
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT t.name, tt.description FROM {$wpdb->terms} t JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id WHERE t.term_id = %d AND tt.taxonomy = %s", $loc['term_id'], $loc['taxonomy'] ), ARRAY_A );
				if ( ! $row ) {
					return $this->r_fail( sprintf( 'term %d not found in taxonomy %s', $loc['term_id'], $loc['taxonomy'] ) );
				}
				return $this->r_val( (string) $row[ $loc['field'] ] );

			case 'user_field':
				$row = $wpdb->get_row( $wpdb->prepare( "SELECT display_name FROM {$wpdb->users} WHERE ID = %d", $loc['user_id'] ), ARRAY_A );
				if ( ! $row ) {
					return $this->r_fail( 'user ' . $loc['user_id'] . ' not found' );
				}
				return $this->r_val( (string) $row[ $loc['field'] ] );
		}
		return $this->r_fail( 'unknown locator type ' . $loc['type'] );
	}

	/**
	 * Write (or, with $exists false, remove) the value at a locator, then read it back.
	 *
	 * @param array      $loc      Locator.
	 * @param bool       $exists   False removes the option / key / meta row.
	 * @param mixed      $value    Value.
	 * @param array|null $modified post_modified / post_modified_gmt to set with a post_field write.
	 * @return array{state: string, message: string}
	 */
	private function write_loc( $loc, $exists, $value, $modified ) {
		global $wpdb;
		$result = array();
		switch ( $loc['type'] ) {
			case 'option':
				if ( $exists ) {
					update_option( $loc['option'], $value );
				} else {
					delete_option( $loc['option'] );
				}
				$this->flush_option( $loc['option'] );
				break;

			case 'option_key':
				$raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $loc['option'] ) );
				$arr = null === $raw ? null : $this->unser( $raw );
				if ( ! is_array( $arr ) ) {
					return $this->failed( 'option ' . $loc['option'] . ' is missing or not an array' );
				}
				$path = $loc['path'];
				$last = array_pop( $path );
				$ref  = &$arr;
				foreach ( $path as $k ) {
					if ( ! isset( $ref[ $k ] ) || ! is_array( $ref[ $k ] ) ) {
						if ( ! $exists ) {
							unset( $ref );
							return $this->failed( 'parent key missing' );
						}
						$ref[ $k ] = array();
					}
					$ref = &$ref[ $k ];
				}
				if ( $exists ) {
					$ref[ $last ] = $value;
				} else {
					unset( $ref[ $last ] );
				}
				unset( $ref );
				update_option( $loc['option'], $arr );
				$this->flush_option( $loc['option'] );
				break;

			case 'post_field':
				$set = array( $loc['field'] => (string) $value );
				if ( is_array( $modified ) ) {
					$set['post_modified']     = $modified['post_modified'];
					$set['post_modified_gmt'] = $modified['post_modified_gmt'];
				}
				$saved = $wpdb->update( $wpdb->posts, $set, array( 'ID' => (int) $loc['post_id'] ) );
				clean_post_cache( (int) $loc['post_id'] );
				if ( false === $saved ) {
					return $this->failed( 'database error: ' . $wpdb->last_error );
				}
				break;

			case 'post_meta':
			case 'term_meta':
			case 'user_meta':
				$t   = $this->meta_table( $loc['type'] );
				$cur = $this->read_loc( $loc );
				if ( ! $cur['ok'] ) {
					return $this->failed( $cur['why'] );
				}
				$owner = (int) $loc[ $t['field'] ];
				$saved = true;
				if ( $exists && $cur['exists'] ) {
					$saved = $wpdb->update( $t['table'], array( 'meta_value' => (string) $value ), array( $t['pk'] => $cur['meta_id'] ) );
				} elseif ( $exists ) {
					$saved = $wpdb->insert(
						$t['table'],
						array(
							$t['col']    => $owner,
							'meta_key'   => $loc['key'], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
							'meta_value' => (string) $value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						)
					);
					$result['meta_id'] = (int) $wpdb->insert_id;
				} elseif ( $cur['exists'] ) {
					$saved = $wpdb->delete( $t['table'], array( $t['pk'] => $cur['meta_id'] ) );
				}
				$this->clean_owner( $loc['type'], $owner, isset( $loc['taxonomy'] ) ? $loc['taxonomy'] : '' );
				if ( false === $saved ) {
					return $this->failed( 'database error: ' . $wpdb->last_error );
				}
				break;

			case 'term_field':
				if ( 'name' === $loc['field'] ) {
					$saved = $wpdb->update( $wpdb->terms, array( 'name' => (string) $value ), array( 'term_id' => (int) $loc['term_id'] ) );
				} else {
					$saved = $wpdb->update(
						$wpdb->term_taxonomy,
						array( 'description' => (string) $value ),
						array(
							'term_id'  => (int) $loc['term_id'],
							'taxonomy' => $loc['taxonomy'],
						)
					);
				}
				clean_term_cache( (int) $loc['term_id'], $loc['taxonomy'] );
				if ( false === $saved ) {
					return $this->failed( 'database error: ' . $wpdb->last_error );
				}
				break;

			case 'user_field':
				$saved = $wpdb->update( $wpdb->users, array( $loc['field'] => (string) $value ), array( 'ID' => (int) $loc['user_id'] ) );
				clean_user_cache( (int) $loc['user_id'] );
				if ( false === $saved ) {
					return $this->failed( 'database error: ' . $wpdb->last_error );
				}
				break;

			default:
				return $this->failed( 'unknown locator type' );
		}
		$after = $this->read_loc( $loc );
		if ( ! $after['ok'] ) {
			return $this->failed( 'read-back: ' . $after['why'] );
		}
		if ( ! $this->matches( $after, $exists, $value ) ) {
			return $this->failed( 'read-back differs: now ' . ( $after['exists'] ? $this->display( $after['value'] ) : '(absent)' ) );
		}
		$out = array(
			'state'   => 'done',
			'message' => '',
		);
		if ( $result ) {
			$out['result'] = $result;
		}
		return $out;
	}

	/**
	 * Table details for a meta locator type.
	 *
	 * @param string $type post_meta|term_meta|user_meta.
	 * @return array{table: string, col: string, pk: string, field: string, owner: string, owner_pk: string}
	 */
	private function meta_table( $type ) {
		global $wpdb;
		if ( 'term_meta' === $type ) {
			return array(
				'table'    => $wpdb->termmeta,
				'col'      => 'term_id',
				'pk'       => 'meta_id',
				'field'    => 'term_id',
				'owner'    => $wpdb->terms,
				'owner_pk' => 'term_id',
			);
		}
		if ( 'user_meta' === $type ) {
			return array(
				'table'    => $wpdb->usermeta,
				'col'      => 'user_id',
				'pk'       => 'umeta_id',
				'field'    => 'user_id',
				'owner'    => $wpdb->users,
				'owner_pk' => 'ID',
			);
		}
		return array(
			'table'    => $wpdb->postmeta,
			'col'      => 'post_id',
			'pk'       => 'meta_id',
			'field'    => 'post_id',
			'owner'    => $wpdb->posts,
			'owner_pk' => 'ID',
		);
	}

	/**
	 * Clear the object cache for the owner of a meta row.
	 *
	 * @param string $type     Meta locator type.
	 * @param int    $id       Owner ID.
	 * @param string $taxonomy Taxonomy (terms).
	 */
	private function clean_owner( $type, $id, $taxonomy ) {
		if ( 'term_meta' === $type ) {
			wp_cache_delete( $id, 'term_meta' );
			clean_term_cache( $id, $taxonomy );
		} elseif ( 'user_meta' === $type ) {
			wp_cache_delete( $id, 'user_meta' );
			clean_user_cache( $id );
		} else {
			wp_cache_delete( $id, 'post_meta' );
			clean_post_cache( $id );
		}
	}

	/**
	 * Drop cached copies of an option.
	 *
	 * @param string $name Option name.
	 */
	private function flush_option( $name ) {
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}

	/**
	 * Journal snapshot of a read result.
	 *
	 * @param array $read Read result.
	 * @return array
	 */
	private function snap( $read ) {
		$s = array(
			'exists' => (bool) $read['exists'],
			'value'  => $read['value'],
		);
		foreach ( array( 'meta_id', 'post_type', 'post_modified', 'post_modified_gmt' ) as $k ) {
			if ( isset( $read[ $k ] ) ) {
				$s[ $k ] = $read[ $k ];
			}
		}
		return $s;
	}

	/**
	 * Does a read result hold this value (or, with $exists false, nothing)?
	 *
	 * @param array $read   Read result.
	 * @param bool  $exists Expected existence.
	 * @param mixed $value  Expected value.
	 * @return bool
	 */
	private function matches( $read, $exists, $value ) {
		return $exists ? ( $read['exists'] && $this->same( $read['value'], $value ) ) : ! $read['exists'];
	}

	/* ------------------------------------------------------------------ */
	/* Journal and execution                                               */
	/* ------------------------------------------------------------------ */

	/**
	 * Run every planned op, journaling it as running before and its outcome after.
	 *
	 * @return array{done: int, failed: int}
	 */
	private function execute() {
		$tally = array(
			'done'   => 0,
			'failed' => 0,
		);
		WP_CLI::log( '' );
		WP_CLI::log( '== Writing ==' );
		foreach ( $this->ops as $i => $op ) {
			$this->ops[ $i ]['state'] = 'running';
			$this->write_journal();
			try {
				$cur = $this->read_loc( $op['loc'] );
				if ( ! $cur['ok'] ) {
					$res = $this->failed( $cur['why'] );
				} elseif ( ! $this->matches( $cur, ! empty( $op['before']['exists'] ), $op['before']['value'] ) ) {
					$res = $this->failed( 'value changed since the snapshot; not written' );
				} else {
					$res = $this->write_loc( $op['loc'], (bool) $op['after_exists'], $op['after'], isset( $op['modified'] ) ? $op['modified'] : null );
				}
			} catch ( \Throwable $e ) {
				$res = $this->failed( get_class( $e ) . ': ' . $e->getMessage() );
			}
			$this->ops[ $i ]['state']   = $res['state'];
			$this->ops[ $i ]['message'] = $res['message'];
			if ( isset( $res['result'] ) ) {
				$this->ops[ $i ]['result'] = $res['result'];
			}
			$this->write_journal();
			++$tally[ $res['state'] ];
			WP_CLI::log( sprintf( '  [%s] %s%s', 'done' === $res['state'] ? 'ok' : 'FAILED', $op['label'], '' !== $res['message'] ? ' (' . $res['message'] . ')' : '' ) );
		}
		return $tally;
	}

	/**
	 * Write the journal (rewritten after every operation, mode 600).
	 */
	private function write_journal() {
		$data = array(
			'tool'        => self::TOOL,
			'version'     => self::VERSION,
			'mode'        => $this->journal_mode,
			'site'        => $this->site,
			'home'        => $this->home,
			'abspath'     => ABSPATH,
			'started_utc' => $this->started_utc,
			'updated_utc' => gmdate( 'Y-m-d\TH:i:s\Z' ),
			'source'      => $this->source,
			'note'        => 'Each op holds "before" (value before the write), "after" (value written) and "state" (planned/running/done/failed). Undo: wp eval-file rebrand-db.php restore <this file> <site> --path=...',
			'ops'         => $this->ops,
		);
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json || false === file_put_contents( $this->journal_file, $json, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			$written = count( array_intersect( wp_list_pluck( $this->ops, 'state' ), array( 'done', 'failed' ) ) );
			WP_CLI::error( 'Could not write the journal ' . $this->journal_file . ( $written ? sprintf( ' (%d writes were already made; see output above)', $written ) : '. Nothing was written.' ) );
		}
		chmod( $this->journal_file, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/* ------------------------------------------------------------------ */
	/* Helpers                                                             */
	/* ------------------------------------------------------------------ */

	/**
	 * Read results.
	 *
	 * @param mixed $value Value.
	 * @param array $extra Extra fields.
	 * @return array
	 */
	private function r_val( $value, $extra = array() ) {
		return array_merge(
			array(
				'ok'     => true,
				'why'    => '',
				'exists' => true,
				'value'  => $value,
			),
			$extra
		);
	}

	/**
	 * Read result: nothing stored.
	 *
	 * @return array
	 */
	private function r_absent() {
		return array(
			'ok'     => true,
			'why'    => '',
			'exists' => false,
			'value'  => null,
		);
	}

	/**
	 * Read result: cannot be read (counts as MISS).
	 *
	 * @param string $why Reason.
	 * @return array
	 */
	private function r_fail( $why ) {
		return array(
			'ok'     => false,
			'why'    => $why,
			'exists' => false,
			'value'  => null,
		);
	}

	/**
	 * Failed write result.
	 *
	 * @param string $message Why.
	 * @return array
	 */
	private function failed( $message ) {
		return array(
			'state'   => 'failed',
			'message' => $message,
		);
	}

	/**
	 * Unserialize stored data without instantiating classes.
	 *
	 * @param mixed $raw Raw value.
	 * @return mixed
	 */
	private function unser( $raw ) {
		if ( ! is_string( $raw ) || ! is_serialized( $raw ) ) {
			return $raw;
		}
		$v = @unserialize( trim( $raw ), array( 'allowed_classes' => false ) ); // phpcs:ignore
		return ( false === $v && 'b:0;' !== trim( $raw ) ) ? $raw : $v;
	}

	/**
	 * Does a value contain any object?
	 *
	 * @param mixed $v Value.
	 * @return bool
	 */
	private function has_object( $v ) {
		if ( is_object( $v ) ) {
			return true;
		}
		if ( is_array( $v ) ) {
			foreach ( $v as $x ) {
				if ( $this->has_object( $x ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Scalars as strings, recursively (so 5 equals "5" when comparing).
	 *
	 * @param mixed $v Value.
	 * @return mixed
	 */
	private function norm( $v ) {
		if ( null === $v ) {
			return null;
		}
		if ( is_bool( $v ) ) {
			return $v ? '1' : '';
		}
		if ( is_scalar( $v ) ) {
			return (string) $v;
		}
		$out = array();
		foreach ( (array) $v as $k => $x ) {
			$out[ (string) $k ] = $this->norm( $x );
		}
		return $out;
	}

	/**
	 * Equal after normalising scalars to strings?
	 *
	 * @param mixed $a A.
	 * @param mixed $b B.
	 * @return bool
	 */
	private function same( $a, $b ) {
		return serialize( $this->norm( $a ) ) === serialize( $this->norm( $b ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * sha256 of a (tokenised) value; strings hash as-is.
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	private function sha_of( $v ) {
		return hash( 'sha256', is_string( $v ) ? $v : serialize( $this->norm( $v ) ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/**
	 * Replace home_url() with {{home}}, recursively.
	 *
	 * @param mixed $v Value.
	 * @return mixed
	 */
	private function tok( $v ) {
		if ( is_string( $v ) ) {
			return str_replace( $this->home, self::TOKEN, $v );
		}
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$v[ $k ] = $this->tok( $x );
			}
		}
		return $v;
	}

	/**
	 * Replace {{home}} with home_url(), recursively.
	 *
	 * @param mixed $v Value.
	 * @return mixed
	 */
	private function detok( $v ) {
		if ( is_string( $v ) ) {
			return str_replace( self::TOKEN, $this->home, $v );
		}
		if ( is_array( $v ) ) {
			foreach ( $v as $k => $x ) {
				$v[ $k ] = $this->detok( $x );
			}
		}
		return $v;
	}

	/**
	 * Long values get a review diff instead of inline output.
	 *
	 * @param mixed $v Value.
	 * @return bool
	 */
	private function is_long( $v ) {
		return is_string( $v ) && ( strlen( $v ) > 200 || false !== strpos( $v, "\n" ) );
	}

	/**
	 * Short printable form of a value.
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	private function display( $v ) {
		if ( is_string( $v ) ) {
			return '"' . $this->short( $v, 160 ) . '"';
		}
		return $this->short( (string) wp_json_encode( $v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), 160 );
	}

	/**
	 * Truncate to about $max characters on one line.
	 *
	 * @param string $s   Text.
	 * @param int    $max Length.
	 * @return string
	 */
	private function short( $s, $max ) {
		$s = str_replace( array( "\r", "\n", "\t" ), ' ', (string) $s );
		if ( strlen( $s ) <= $max ) {
			return $s;
		}
		$cut = function_exists( 'mb_strcut' ) ? mb_strcut( $s, 0, $max, 'UTF-8' ) : substr( $s, 0, $max );
		return $cut . '...';
	}

	/**
	 * File-name-safe version of an id.
	 *
	 * @param string $id Id.
	 * @return string
	 */
	private function safe_name( $id ) {
		return preg_replace( '/[^A-Za-z0-9._-]+/', '_', (string) $id );
	}

	/**
	 * Expand a leading ~/ to the home directory.
	 *
	 * @param string $p Path.
	 * @return string
	 */
	private function path( $p ) {
		return 0 === strpos( $p, '~/' ) ? $this->home_dir() . substr( $p, 1 ) : $p;
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
	 * Write pretty JSON (mode 600 when $private).
	 *
	 * @param string $file    Path.
	 * @param mixed  $data    Data.
	 * @param bool   $private Restrict permissions.
	 */
	private function write_json( $file, $data, $private ) {
		$dir = dirname( $file );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			WP_CLI::error( 'Cannot create ' . $dir );
		}
		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false === $json || false === file_put_contents( $file, $json, LOCK_EX ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			WP_CLI::error( 'Could not write ' . $file );
		}
		if ( $private ) {
			chmod( $file, 0600 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
}

$lc_rebrand_db = new LC_Rebrand_DB( __DIR__, isset( $args ) ? $args : array() );
$lc_rebrand_db->run();
