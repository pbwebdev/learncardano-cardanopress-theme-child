<?php
/**
 * One-off clean-up of the accounts CardanoPress created (login = md5 of the
 * stake address). Run before R2 goes live:
 *
 *   wp --path=… eval-file tools/accounts/migrate-cardanopress.php                    dry run (default)
 *   wp --path=… eval-file tools/accounts/migrate-cardanopress.php apply <backup-dir> change, writing a JSON backup first
 *   wp --path=… eval-file tools/accounts/migrate-cardanopress.php restore <file.json> put the backed-up values back
 *
 * What it changes, on hex-login accounts only:
 * - display_name and nickname become "Cardano member" where display_name is
 *   still the hex login (expect ~433);
 * - user_nicename becomes member-<ID> (~438), so md5(stake) leaves comment
 *   CSS classes and author URLs;
 * - comment_author on their comments still showing a hex name becomes
 *   "Cardano member" (~17);
 * - any user_email moves to the lc_legacy_email meta and user_email is
 *   cleared (~7): those addresses may have been set by someone who used the
 *   CardanoPress flaw, and an empty email means nothing can be mailed to them.
 *
 * Writes go straight to the tables (then clean_user_cache()/clean_comment_cache()),
 * so no hooks run and no notification emails go out. Prints counts only.
 *
 * Re-running: BEFORE go-live a second apply finds nothing to do. AFTER go-live
 * (members have signed in, verified an email or linked a wallet) it still only
 * touches what is untouched: an email is moved only while it is not the
 * verified address (lc_email_verified) and the account has no lc_wallet; a
 * name is changed only while display_name is still the hex login and the
 * account has no lc_signup record. Restore puts a field back only while it
 * still holds what apply wrote ("Cardano member", member-<ID>, an empty
 * email), and never gives an address back that another account now owns. Even
 * so, restore after go-live is a last resort: prefer restoring the database.
 *
 * @package CardanoPress Bootstrap
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 1 );
}

global $wpdb;
$lc_args = isset( $args ) && is_array( $args ) ? $args : array();
$lc_mode = $lc_args[0] ?? 'dry-run';
$lc_hex  = '/^[0-9a-f]{32}$/';
$lc_name = 'Cardano member';

/** Insert or update one usermeta row (single-valued key) without hooks. */
function lc_migrate_set_meta( $user_id, $key, $value ) {
	global $wpdb;
	$id = $wpdb->get_var( $wpdb->prepare( "SELECT umeta_id FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id LIMIT 1", $user_id, $key ) );
	if ( $id ) {
		$wpdb->update( $wpdb->usermeta, array( 'meta_value' => $value ), array( 'umeta_id' => $id ) );
	} else {
		$wpdb->insert( $wpdb->usermeta, array( 'user_id' => $user_id, 'meta_key' => $key, 'meta_value' => $value ) ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	}
}

function lc_migrate_get_meta( $user_id, $key ) {
	global $wpdb;
	$v = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id LIMIT 1", $user_id, $key ) );
	return null === $v ? null : (string) $v;
}

/** Is $email the verified address? lc_email_verified is an HMAC now (plaintext before that change). */
function lc_migrate_email_verified( $user_id, $email ) {
	$v     = (string) lc_migrate_get_meta( $user_id, 'lc_email_verified' );
	$email = strtolower( trim( (string) $email ) );
	if ( '' === $v || '' === $email ) {
		return false;
	}
	if ( false !== strpos( $v, '@' ) ) {
		return strtolower( $v ) === $email;
	}
	return function_exists( 'lc_auth_hmac' ) && hash_equals( lc_auth_hmac( 'ev', $email ), $v );
}

if ( 'restore' === $lc_mode ) {
	$file = $lc_args[1] ?? '';
	if ( ! $file || ! is_readable( $file ) ) {
		WP_CLI::error( 'Usage: restore <backup.json>' );
	}
	$data = json_decode( (string) file_get_contents( $file ), true );
	if ( ! is_array( $data ) || empty( $data['users'] ) && empty( $data['comments'] ) ) {
		WP_CLI::error( 'Not a backup file from this script.' );
	}
	$users = 0;
	foreach ( $data['users'] as $u ) {
		$uid = (int) $u['ID'];
		$cur = $wpdb->get_row( $wpdb->prepare( "SELECT display_name, user_nicename, user_email FROM {$wpdb->users} WHERE ID = %d", $uid ) );
		if ( ! $cur ) {
			continue;
		}
		// Revert a field only while it still holds what apply wrote.
		$set = array();
		if ( $cur->display_name === $lc_name && $u['display_name'] !== $lc_name ) {
			$set['display_name'] = $u['display_name'];
			if ( lc_migrate_get_meta( $uid, 'nickname' ) === $lc_name ) {
				if ( null === $u['nickname'] ) {
					$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $uid, 'meta_key' => 'nickname' ) );
				} else {
					lc_migrate_set_meta( $uid, 'nickname', $u['nickname'] );
				}
			}
		}
		if ( 'member-' . $uid === $cur->user_nicename && $u['user_nicename'] !== $cur->user_nicename ) {
			$set['user_nicename'] = $u['user_nicename'];
		}
		if ( '' === (string) $cur->user_email && '' !== (string) $u['user_email'] ) {
			$owner = email_exists( $u['user_email'] );
			if ( ! $owner || (int) $owner === $uid ) {
				$set['user_email'] = $u['user_email'];
				if ( null === $u['legacy_email'] ) {
					$wpdb->delete( $wpdb->usermeta, array( 'user_id' => $uid, 'meta_key' => 'lc_legacy_email' ) );
				} else {
					lc_migrate_set_meta( $uid, 'lc_legacy_email', $u['legacy_email'] );
				}
			} else {
				WP_CLI::warning( sprintf( 'User %d: email now belongs to another account; not restored.', $uid ) );
			}
		}
		if ( $set ) {
			$wpdb->update( $wpdb->users, $set, array( 'ID' => $uid ) );
		}
		clean_user_cache( $uid );
		++$users;
	}
	$restored = array();
	foreach ( $data['comments'] as $c ) {
		if ( $wpdb->update( $wpdb->comments, array( 'comment_author' => $c['author'] ), array( 'comment_ID' => (int) $c['id'], 'comment_author' => $lc_name ) ) ) {
			$restored[] = (int) $c['id'];
		}
	}
	clean_comment_cache( $restored );
	WP_CLI::success( sprintf( 'Restored %d users and %d comments.', $users, count( $restored ) ) );
	return;
}

if ( ! in_array( $lc_mode, array( 'dry-run', 'apply' ), true ) ) {
	WP_CLI::error( 'Mode must be dry-run, apply <backup-dir> or restore <backup.json>.' );
}

/* ---- work out what would change ---------------------------------------- */
$lc_rows  = $wpdb->get_results( "SELECT ID, user_login, user_nicename, user_email, display_name FROM {$wpdb->users}" );
$lc_plan  = array();
$lc_count = array( 'hex' => 0, 'names' => 0, 'nicenames' => 0, 'emails' => 0, 'comments' => 0 );
$lc_ids   = array();
foreach ( $lc_rows as $row ) {
	if ( ! preg_match( $lc_hex, $row->user_login ) ) {
		continue;
	}
	++$lc_count['hex'];
	$lc_ids[] = (int) $row->ID;
	$item     = array(
		'ID'            => (int) $row->ID,
		'display_name'  => $row->display_name,
		'user_nicename' => $row->user_nicename,
		'user_email'    => $row->user_email,
		'nickname'      => lc_migrate_get_meta( (int) $row->ID, 'nickname' ),
		'legacy_email'  => lc_migrate_get_meta( (int) $row->ID, 'lc_legacy_email' ),
		// Skip what a signed-in-since member already owns: a new-system signup, a verified email, a linked wallet.
		'do_name'       => $row->display_name === $row->user_login && null === lc_migrate_get_meta( (int) $row->ID, 'lc_signup' ),
		'do_nicename'   => 'member-' . $row->ID !== $row->user_nicename,
		'do_email'      => '' !== $row->user_email
			&& ! lc_migrate_email_verified( (int) $row->ID, $row->user_email )
			&& null === lc_migrate_get_meta( (int) $row->ID, 'lc_wallet' ),
	);
	$lc_count['names']     += (int) $item['do_name'];
	$lc_count['nicenames'] += (int) $item['do_nicename'];
	$lc_count['emails']    += (int) $item['do_email'];
	if ( $item['do_name'] || $item['do_nicename'] || $item['do_email'] ) {
		$lc_plan[] = $item;
	}
}
$lc_comments = array();
if ( $lc_ids ) {
	$found = $wpdb->get_results( 'SELECT comment_ID, comment_author FROM ' . $wpdb->comments . ' WHERE user_id IN (' . implode( ',', $lc_ids ) . ')' ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integers only.
	foreach ( $found as $c ) {
		if ( preg_match( $lc_hex, $c->comment_author ) ) {
			$lc_comments[] = array( 'id' => (int) $c->comment_ID, 'author' => $c->comment_author );
		}
	}
}
$lc_count['comments'] = count( $lc_comments );

WP_CLI::log( sprintf( 'hex accounts: %d', $lc_count['hex'] ) );
WP_CLI::log( sprintf( 'display names to "%s": %d', $lc_name, $lc_count['names'] ) );
WP_CLI::log( sprintf( 'nicenames to member-<ID>: %d', $lc_count['nicenames'] ) );
WP_CLI::log( sprintf( 'emails to move to lc_legacy_email: %d', $lc_count['emails'] ) );
WP_CLI::log( sprintf( 'comment author names to rewrite: %d', $lc_count['comments'] ) );

if ( 'dry-run' === $lc_mode ) {
	WP_CLI::success( 'Dry run only. Nothing was changed.' );
	return;
}

/* ---- apply -------------------------------------------------------------- */
$lc_dir = $lc_args[1] ?? '';
if ( ! $lc_dir ) {
	WP_CLI::error( 'Usage: apply <backup-dir>' );
}
if ( ! $lc_plan && ! $lc_comments ) {
	WP_CLI::success( 'Nothing to do (already migrated).' );
	return;
}
if ( ! is_dir( $lc_dir ) && ! wp_mkdir_p( $lc_dir ) ) {
	WP_CLI::error( 'Could not create the backup directory.' );
}
$lc_file = rtrim( $lc_dir, '/\\' ) . '/cardanopress-migration-' . gmdate( 'Ymd-His' ) . '.json';
$lc_json = wp_json_encode(
	array(
		'created'  => gmdate( 'c' ),
		'users'    => array_map(
			function ( $i ) {
				return array_diff_key( $i, array_flip( array( 'do_name', 'do_nicename', 'do_email' ) ) );
			},
			$lc_plan
		),
		'comments' => $lc_comments,
	)
);
if ( false === file_put_contents( $lc_file, $lc_json ) ) {
	WP_CLI::error( 'Could not write the backup; nothing was changed.' );
}
chmod( $lc_file, 0600 );

foreach ( $lc_plan as $item ) {
	$set = array();
	if ( $item['do_name'] ) {
		$set['display_name'] = $lc_name;
		lc_migrate_set_meta( $item['ID'], 'nickname', $lc_name );
	}
	if ( $item['do_nicename'] ) {
		$set['user_nicename'] = 'member-' . $item['ID'];
	}
	if ( $item['do_email'] ) {
		lc_migrate_set_meta( $item['ID'], 'lc_legacy_email', $item['user_email'] );
		$set['user_email'] = '';
	}
	if ( $set ) {
		$wpdb->update( $wpdb->users, $set, array( 'ID' => $item['ID'] ) );
	}
	clean_user_cache( $item['ID'] );
}
foreach ( $lc_comments as $c ) {
	$wpdb->update( $wpdb->comments, array( 'comment_author' => $lc_name ), array( 'comment_ID' => $c['id'] ) );
}
clean_comment_cache( wp_list_pluck( $lc_comments, 'id' ) );
WP_CLI::success( sprintf( 'Applied. Backup: %s', $lc_file ) );
