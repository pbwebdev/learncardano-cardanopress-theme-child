<?php
/**
 * Accounts, part 4: what visitors see and use. Loaded on every request (it
 * holds the header link, the comment form changes and the members-only
 * helpers); the heavy parts only run for /account/* routes.
 *
 * - /account/            signed out: the sign-in panel (works without JS).
 *                        signed in: profile, email, sign-in methods, sessions,
 *                        delete account (template-parts/account/manage.php).
 * - /account/panel/      the same panel as JSON for the modal (assets/js/auth.js).
 * - POST /account/       every account action (lc_action + nonce), then 303.
 * - Header link, comment form, [lc_members_only], and an admin box on the
 *   user-edit screen (manage_options only) for support.
 *
 * @package CardanoPress Bootstrap
 */

/* ----------------------------------------------------------------- names */

/** Is this name machine-made (an md5 login, stake or payment address)? */
function lc_auth_name_is_machine( $name ) {
	return 1 === preg_match( '/^([0-9a-f]{32}|stake(_test)?1[0-9a-z]{20,}|addr(_test)?1[0-9a-z]{20,})$/', (string) $name );
}

/** The name shown publicly for a member. */
function lc_public_name( $user ) {
	$user = is_numeric( $user ) ? get_userdata( (int) $user ) : $user;
	if ( ! $user instanceof WP_User ) {
		return __( 'Former member', 'learncardano' );
	}
	$name = trim( (string) $user->display_name );
	return '' === $name || lc_auth_name_is_machine( $name ) ? __( 'Cardano member', 'learncardano' ) : $name;
}

/** Whether a member should be nudged to pick a name. */
function lc_auth_needs_name( WP_User $user ) {
	return lc_auth_name_is_machine( $user->display_name ) || 1 === preg_match( '/^(Cardano member|Member \d{4})$/', $user->display_name );
}

/** Names staff use (display name, login, nicename), lowercase. */
function lc_auth_staff_names() {
	static $names = null;
	if ( null === $names ) {
		$names = (array) apply_filters( 'lc_auth_reserved_names', array( 'admin', 'administrator', 'moderator', 'support', 'staff', 'learn cardano', 'learncardano', 'adaoz' ) );
		foreach ( get_users( array( 'capability__in' => array( 'edit_posts', 'moderate_comments', 'list_users' ), 'fields' => array( 'display_name', 'user_login', 'user_nicename' ) ) ) as $u ) {
			$names[] = $u->display_name;
			$names[] = $u->user_login;
			$names[] = $u->user_nicename;
		}
		$names = array_map( 'strtolower', array_map( 'trim', $names ) );
	}
	return $names;
}

/**
 * Display-name rules: 2 to 40 characters, no "@" or web address, nothing that
 * looks like a wallet address, never a staff member's name.
 *
 * @return string|false The cleaned name, or false.
 */
function lc_auth_validate_display_name( $raw ) {
	$name = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( (string) $raw ) ) );
	$len  = mb_strlen( $name );
	if ( $len < 2 || $len > 40 || 1 === preg_match( '/@|https?|www\.|:\/\//i', $name ) ) {
		return false;
	}
	if ( lc_auth_name_is_machine( $name ) || 1 === preg_match( '/(stake|addr)(_test)?1[0-9a-z]{20,}/i', $name ) || in_array( strtolower( $name ), lc_auth_staff_names(), true ) ) {
		return false;
	}
	return $name;
}

/* ------------------------------------------------------------ assets/config */

/** What forms.js needs to lazy-load the sign-in modal (null when sign-in is switched off). */
function lc_auth_client_config() {
	if ( ! lc_auth_enabled() ) {
		return null;
	}
	return array(
		'js'      => lc_theme_asset_url( 'assets/js/auth.js' ),
		'css'     => lc_theme_asset_url( 'assets/css/auth.css' ),
		'panel'   => lc_auth_account_url( 'panel/' ),
		'account' => lc_auth_account_url(),
	);
}

/** auth.js/auth.css are registered, and only enqueued on /account/* pages. */
add_action(
	'wp_enqueue_scripts',
	function () {
		$dir = get_stylesheet_directory();
		$uri = get_stylesheet_directory_uri();
		wp_register_style( 'lc-auth', $uri . '/assets/css/auth.css', array( 'lc-forms' ), file_exists( $dir . '/assets/css/auth.css' ) ? (string) filemtime( $dir . '/assets/css/auth.css' ) : null );
		wp_register_script( 'lc-auth', $uri . '/assets/js/auth.js', array(), file_exists( $dir . '/assets/js/auth.js' ) ? (string) filemtime( $dir . '/assets/js/auth.js' ) : null, true );
		wp_script_add_data( 'lc-auth', 'strategy', 'defer' );
		if ( lc_auth_view() ) {
			wp_enqueue_style( 'lc-auth' );
			wp_enqueue_script( 'lc-auth' );
		}
	},
	20
);

// SiteGround Optimizer must leave the auth assets alone (same as forms.php).
add_filter(
	'sgo_rqs_exclude',
	function ( $list ) {
		$list[] = 'assets/(css|js)/auth\.(css|js)';
		return $list;
	}
);
foreach ( array( 'sgo_css_minify_exclude', 'sgo_js_minify_exclude', 'sgo_javascript_combine_exclude', 'sgo_css_combine_exclude' ) as $lc_auth_sgo_filter ) {
	add_filter(
		$lc_auth_sgo_filter,
		function ( $list ) {
			$list[] = 'lc-auth';
			return $list;
		}
	);
}

/* --------------------------------------------------------- the sign-in panel */

/** Panel heading by where it was opened from. */
function lc_auth_panel_title( $context ) {
	$titles = array(
		'comment' => __( 'Sign in to comment', 'learncardano' ),
		'gate'    => __( 'Sign in to continue', 'learncardano' ),
		'header'  => __( 'Sign in', 'learncardano' ),
		'page'    => __( 'Sign in or create your account', 'learncardano' ),
	);
	return $titles[ $context ] ?? $titles['page'];
}

/**
 * The one sign-in panel, used by the /account/ page and the modal.
 *
 * @param array $args context (header|comment|gate|page), redirect.
 * @return string HTML.
 */
function lc_auth_render_panel( array $args = array() ) {
	$lc_panel = wp_parse_args(
		$args,
		array(
			'context'  => 'page',
			'redirect' => '',
		)
	);
	$lc_panel['title'] = lc_auth_panel_title( $lc_panel['context'] );
	$lc_panel['token'] = lc_auth_enabled() ? lc_auth_panel_token() : '';
	ob_start();
	include get_stylesheet_directory() . '/template-parts/auth-panel.php';
	return (string) ob_get_clean();
}

/** GET /account/panel/ : the panel as JSON for the modal. */
function lc_auth_get_panel() {
	lc_auth_no_cache();
	if ( is_user_logged_in() ) {
		wp_send_json( array( 'ok' => true, 'signed_in' => true ) );
	}
	$retry = lc_auth_rate( 'panel', null, 60, 5 * MINUTE_IN_SECONDS );
	if ( $retry ) {
		header( 'Retry-After: ' . $retry );
		wp_send_json( array( 'ok' => false, 'code' => 'rate', 'message' => lc_auth_text( 'rate' ) ), 429 );
	}
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only, public.
	$context  = isset( $_GET['context'] ) && is_string( $_GET['context'] ) ? sanitize_key( wp_unslash( $_GET['context'] ) ) : 'header';
	$redirect = lc_auth_safe_redirect( isset( $_GET['redirect_to'] ) && is_string( $_GET['redirect_to'] ) ? wp_unslash( $_GET['redirect_to'] ) : '' );
	// phpcs:enable
	$context = in_array( $context, array( 'header', 'comment', 'gate' ), true ) ? $context : 'header';
	lc_auth_flow( true );
	$html = lc_auth_render_panel( array( 'context' => $context, 'redirect' => $redirect ) );
	wp_send_json(
		array(
			'ok'    => true,
			'html'  => $html,
			'token' => lc_auth_enabled() ? lc_auth_panel_token() : '',
		)
	);
}

/* --------------------------------------------------------------- the pages */

/** GET /account/ */
function lc_auth_get_home() {
	if ( ! is_user_logged_in() ) {
		lc_auth_flow( true ); // The cookie has to go out before any HTML.
	}
	return array( 'view' => 'home' );
}

/**
 * A ?done= or ?error= code from a redirect, as array( type, text ), or null.
 *
 * Read through lc_auth_query_args(): WordPress drops $_GET['error'] before
 * our router runs.
 */
function lc_auth_notice() {
	$args = lc_auth_query_args(); // Display only; the codes map to fixed text.
	foreach ( array( 'error', 'done' ) as $type ) {
		if ( isset( $args[ $type ] ) && is_string( $args[ $type ] ) && 1 === preg_match( '/^[a-z_]{3,20}$/', $args[ $type ] ) ) {
			$code = $args[ $type ];
			$text = lc_auth_text( $code );
			if ( 'unknown' === $code || $text !== lc_auth_text( 'unknown' ) ) {
				return array( 'error' === $type ? 'error' : 'ok', $text );
			}
		}
	}
	return null;
}

/** Open a POST form to /account/ for an action (adds the nonce). */
function lc_auth_form_open( $action, $class = 'lc-auth__form' ) {
	echo '<form method="post" action="' . esc_url( lc_auth_account_url() ) . '" class="' . esc_attr( $class ) . '">';
	echo '<input type="hidden" name="lc_action" value="' . esc_attr( $action ) . '">';
	wp_nonce_field( 'lc_account_' . $action, '_wpnonce', false );
}

/** Seconds since this session was signed in (large when unknown). */
function lc_auth_session_age() {
	$session = WP_Session_Tokens::get_instance( get_current_user_id() )->get( wp_get_session_token() );
	return isset( $session['login'] ) ? time() - (int) $session['login'] : PHP_INT_MAX;
}

/**
 * Anonymise a member's comments, then delete the account. Legacy CardanoPress
 * accounts that were merged into it (lc_merged_into, no role left) go too:
 * they only hold the old wallet's name, address and comment history, which
 * is personal data the member just asked us to erase.
 */
function lc_auth_delete_account( WP_User $user ) {
	global $wpdb;
	$ids = array( $user->ID );
	foreach ( get_users( array( 'meta_key' => 'lc_merged_into', 'meta_value' => $user->ID, 'fields' => 'ID' ) ) as $merged_id ) {
		$merged = get_userdata( (int) $merged_id );
		if ( $merged && lc_is_legacy_wallet_account( $merged ) && ! $merged->roles ) {
			$ids[] = $merged->ID;
		}
	}
	$comments = array();
	foreach ( $ids as $id ) {
		$comments = array_merge( $comments, array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE user_id = %d", $id ) ) ) );
		$wpdb->update(
			$wpdb->comments,
			array(
				'user_id'              => 0,
				'comment_author'       => 'Former member',
				'comment_author_email' => '',
				'comment_author_url'   => '',
				'comment_author_IP'    => '',
				'comment_agent'        => '',
			),
			array( 'user_id' => $id )
		);
	}
	clean_comment_cache( $comments );
	$email = lc_auth_email_verified( $user ) ? $user->user_email : '';
	require_once ABSPATH . 'wp-admin/includes/user.php';
	$reassign = defined( 'LC_PUBLISHING_AUTHOR_ID' ) ? (int) LC_PUBLISHING_AUTHOR_ID : null;
	foreach ( array_slice( $ids, 1 ) as $merged_id ) {
		wp_delete_user( $merged_id, $reassign );
	}
	wp_delete_user( $user->ID, $reassign );
	if ( '' !== $email ) {
		wp_mail( $email, __( 'Your Learn Cardano account was deleted', 'learncardano' ), __( "Your Learn Cardano account has been deleted, and your comments no longer carry your name.

If this wasn't you, reply to this email.", 'learncardano' ) );
	}
	lc_auth_log( 'account_deleted', array( 'user' => $user->ID, 'comments' => count( $comments ), 'merged' => count( $ids ) - 1 ) );
}

/** POST /account/ : every account action. */
function lc_auth_post_home() {
	$in     = lc_auth_input();
	$action = isset( $in['lc_action'] ) ? sanitize_key( $in['lc_action'] ) : '';

	// Signed out: only "email me a link".
	if ( 'email_link' === $action ) {
		$redirect = lc_auth_safe_redirect( (string) ( $in['redirect_to'] ?? '' ) );
		$result   = lc_auth_email_request( $in );
		$url      = lc_auth_account_url() === $redirect ? lc_auth_account_url() : lc_auth_signin_url( $redirect );
		lc_auth_respond( $result['ok'], $result['code'], $url, array(), $result['status'] ?? 200, $result['retry'] ?? 0 );
	}

	if ( ! is_user_logged_in() ) {
		lc_auth_respond( false, 'forbidden', '', array(), 401 );
	}
	$user = wp_get_current_user();
	if ( ! wp_verify_nonce( (string) ( $in['_wpnonce'] ?? '' ), 'lc_account_' . $action ) ) {
		lc_auth_respond( false, 'reload', '', array(), 403 );
	}
	$retry = lc_auth_rate( 'account_post', (string) $user->ID, 30, HOUR_IN_SECONDS );
	if ( $retry ) {
		lc_auth_respond( false, 'rate', '', array(), 429, $retry );
	}
	if ( lc_auth_is_privileged( $user ) && ! in_array( $action, array( 'signout', 'signout_others' ), true ) ) {
		lc_auth_respond( false, 'password_only', '', array(), 403 );
	}

	switch ( $action ) {
		case 'signout':
			wp_logout();
			wp_safe_redirect( home_url( '/' ), 303 );
			exit;

		case 'signout_others':
			WP_Session_Tokens::get_instance( $user->ID )->destroy_others( wp_get_session_token() );
			lc_auth_respond( true, 'others_out' );
			break;

		case 'profile':
			$name = lc_auth_validate_display_name( (string) ( $in['display_name'] ?? '' ) );
			if ( false === $name ) {
				lc_auth_respond( false, 'name_invalid', '', array(), 400 );
			}
			wp_update_user( array( 'ID' => $user->ID, 'display_name' => $name ) );
			update_user_meta( $user->ID, 'nickname', $name );
			lc_auth_respond( true, 'name_saved' );
			break;

		case 'email_change':
			if ( lc_auth_session_age() > 30 * MINUTE_IN_SECONDS ) {
				lc_auth_respond( false, 'delete_recent', '', array(), 403 ); // Changing the sign-in address needs a recent sign-in.
			}
			$result = lc_auth_email_change_request( $user, (string) ( $in['email'] ?? '' ) );
			lc_auth_respond( $result['ok'], $result['code'], '', array(), 400, $result['retry'] ?? 0 );
			break;

		case 'email_verify':
			$result = lc_auth_email_verify_request( $user );
			lc_auth_respond( $result['ok'], $result['code'], '', array(), 400, $result['retry'] ?? 0 );
			break;

		case 'password_link':
			$result = lc_auth_password_link( $user );
			lc_auth_respond( $result['ok'], $result['code'], '', array(), 400, $result['retry'] ?? 0 );
			break;

		case 'link_oauth':
			// Connect Google/Discord: nonce and staff were checked above; the start function checks session recency.
			lc_auth_load( 'oauth' );
			$res = lc_auth_oauth_start( sanitize_key( (string) ( $in['provider'] ?? '' ) ), 'link', lc_auth_account_url() );
			if ( is_wp_error( $res ) ) {
				$data = $res->get_error_data();
				lc_auth_respond( false, $res->get_error_code(), '', array(), isset( $data['status'] ) ? (int) $data['status'] : 400, isset( $data['retry'] ) ? (int) $data['retry'] : 0 );
			}
			if ( lc_auth_wants_json() ) {
				wp_send_json( array( 'ok' => true, 'redirect' => $res['url'] ) );
			}
			header( 'Referrer-Policy: no-referrer' );
			wp_redirect( $res['url'], 303 ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the provider's fixed authorize URL.
			exit;

		case 'unlink':
			$method = (string) ( $in['method'] ?? '' );
			if ( 0 === strpos( $method, 'idp:' ) ) {
				lc_auth_load( 'oauth' );
				$provider = substr( $method, 4 );
				if ( ! isset( lc_auth_idps( $user->ID )[ $provider ] ) ) {
					lc_auth_respond( false, 'forbidden', '', array(), 403 );
				}
				if ( ! lc_auth_can_remove( $user, $method ) ) {
					lc_auth_respond( false, 'last_method', '', array(), 409 );
				}
				lc_auth_idp_remove( $user->ID, $provider );
				lc_auth_log( 'idp_unlink', array( 'user' => $user->ID, 'provider' => $provider ) );
				lc_auth_idp_notify( $user, $provider, 'idp_unlinked' );
				lc_auth_respond( true, 'idp_removed' );
			}
			$stake = (string) ( $in['stake'] ?? '' );
			if ( ! in_array( $stake, lc_auth_wallets( $user->ID ), true ) ) {
				lc_auth_respond( false, 'forbidden', '', array(), 403 );
			}
			if ( ! lc_auth_can_remove( $user, 'wallet:' . $stake ) ) {
				lc_auth_respond( false, 'last_method', '', array(), 409 );
			}
			lc_auth_wallet_remove( $user->ID, $stake );
			lc_auth_log( 'wallet_unlink', array( 'user' => $user->ID ) );
			lc_auth_respond( true, 'wallet_removed' );
			break;

		case 'delete':
			if ( 'DELETE' !== trim( (string) ( $in['confirm'] ?? '' ) ) ) {
				lc_auth_respond( false, 'delete_confirm', '', array(), 400 );
			}
			if ( lc_auth_session_age() > 30 * MINUTE_IN_SECONDS ) {
				lc_auth_respond( false, 'delete_recent', '', array(), 403 );
			}
			lc_auth_delete_account( $user );
			wp_clear_auth_cookie();
			wp_set_current_user( 0 );
			wp_safe_redirect( home_url( '/' ), 303 );
			exit;

		default:
			lc_auth_respond( false, 'unknown', '', array(), 400 );
	}
}

/* --------------------------------------------------------- header + comments */

/**
 * The header's sign-in/account link (replaces the CardanoPress shortcodes).
 * Signed out it is a plain link to /account/ that the modal takes over, and
 * the markup is the same for everyone so cached pages stay valid.
 *
 * @param string $which desktop|mobile (the two header wrappers).
 */
function lc_auth_header_slot( $which = 'desktop' ) {
	unset( $which );
	if ( is_user_logged_in() ) {
		echo '<a class="btn btn-outline-primary btn-sm" href="' . esc_url( lc_auth_account_url() ) . '">' . esc_html__( 'Account', 'learncardano' ) . '</a>';
		return;
	}
	if ( ! lc_auth_enabled() ) {
		echo '<a class="btn btn-primary btn-sm" href="' . esc_url( wp_login_url() ) . '" rel="nofollow">' . esc_html__( 'Sign in', 'learncardano' ) . '</a>';
		return;
	}
	echo '<a class="btn btn-primary btn-sm" href="' . esc_url( lc_auth_signin_url( lc_auth_current_url() ) ) . '" data-lc-auth-open="header" rel="nofollow">' . esc_html__( 'Sign in', 'learncardano' ) . '</a>';
}

/**
 * Comment form: "Sign in to comment" opens the modal; signed-in members see
 * who they comment as. Always returns the defaults.
 */
add_filter(
	'comment_form_defaults',
	function ( $defaults ) {
		if ( ! is_user_logged_in() ) {
			$url                     = get_permalink() . '#respond';
			$defaults['must_log_in'] = '<p class="must-log-in">' . esc_html__( 'Sign in to join the discussion.', 'learncardano' ) . ' <a class="btn btn-primary" href="' . esc_url( lc_auth_signin_url( $url ) ) . '" data-lc-auth-open="comment" data-lc-auth-redirect="' . esc_url( $url ) . '" rel="nofollow">' . esc_html__( 'Sign in to comment', 'learncardano' ) . '</a></p>';
			return $defaults;
		}
		$user  = wp_get_current_user();
		$links = '<a href="' . esc_url( lc_auth_account_url() ) . '">' . esc_html__( 'Account', 'learncardano' ) . '</a>';
		if ( lc_auth_needs_name( $user ) && ! lc_auth_is_privileged( $user ) ) {
			$links .= ' &middot; <a href="' . esc_url( lc_auth_account_url() . '#display-name' ) . '">' . esc_html__( 'Choose a display name', 'learncardano' ) . '</a>';
		}
		$defaults['logged_in_as'] = '<p class="logged-in-as">' . sprintf(
			/* translators: 1: public name, 2: account links, 3: required fields note */
			esc_html__( 'Commenting as %1$s. %2$s %3$s', 'learncardano' ),
			'<strong>' . esc_html( lc_public_name( $user ) ) . '</strong>',
			$links . '.',
			isset( $defaults['required_field_message'] ) ? $defaults['required_field_message'] : ''
		) . '</p>';
		return $defaults;
	}
);

/** "Log in to Reply" opens the modal too. */
add_filter(
	'comment_reply_link',
	function ( $link, $args, $comment ) {
		if ( false === strpos( $link, 'comment-reply-login' ) ) {
			return $link;
		}
		// No literal replytocom: Rank Math rewrites those links. Land on the comment instead.
		$url = get_permalink( $comment->comment_post_ID ) . '#comment-' . $comment->comment_ID;
		return preg_replace( '/<a rel="nofollow" class="comment-reply-login" href="[^"]*"/', '<a rel="nofollow" class="comment-reply-login" href="' . esc_url( lc_auth_signin_url( $url ) ) . '" data-lc-auth-open="comment" data-lc-auth-redirect="' . esc_url( $url ) . '"', $link );
	},
	20,
	3
);

/**
 * Comment posts from a signed-in member carry the login cookie, so a page on
 * another site could post as them. Browsers send Origin/Sec-Fetch-Site on
 * that cross-site POST; refuse it. (Visitors have no cookie to abuse.)
 */
add_action(
	'pre_comment_on_post',
	function () {
		if ( is_user_logged_in() && ! lc_auth_same_origin() ) {
			wp_die( esc_html( lc_auth_text( 'blocked' ) ), '', array( 'response' => 403 ) );
		}
	},
	1
);

/* ---------------------------------------------------------- members only */

/** Is the visitor (or $user) a signed-in member? Filter: lc_is_member. */
function lc_is_member( $user = null ) {
	$user = $user instanceof WP_User ? $user : wp_get_current_user();
	return (bool) apply_filters( 'lc_is_member', $user->exists() && ! get_user_meta( $user->ID, 'lc_merged_into', true ), $user );
}

/** The box shown instead of members-only content. */
function lc_members_gate( array $args = array() ) {
	$args = wp_parse_args( $args, array( 'message' => __( 'This is for members. Signing in is free.', 'learncardano' ) ) );
	$url  = is_singular() ? get_permalink() : lc_auth_current_url();
	$out  = '<div class="lc-members-gate"><p>' . esc_html( $args['message'] ) . '</p>';
	if ( ! is_feed() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		$out .= '<a class="btn btn-primary" href="' . esc_url( lc_auth_signin_url( $url ) ) . '" data-lc-auth-open="gate" data-lc-auth-redirect="' . esc_url( $url ) . '" rel="nofollow">' . esc_html__( 'Sign in to continue', 'learncardano' ) . '</a>';
	}
	return $out . '</div>';
}

/** $content for members, the gate for everyone else. */
function lc_members_only( $content, array $args = array() ) {
	return lc_is_member() ? $content : lc_members_gate( $args );
}

/** [lc_members_only message="…"]…[/lc_members_only] (strip_shortcodes drops the content from excerpts). */
add_shortcode(
	'lc_members_only',
	function ( $atts, $content = '' ) {
		$atts = shortcode_atts( array( 'message' => '' ), $atts, 'lc_members_only' );
		return lc_members_only( do_shortcode( (string) $content ), '' === $atts['message'] ? array() : array( 'message' => $atts['message'] ) );
	}
);

/* ---------------------------------------------------------------- admin box */

/** Support box on the user-edit screen: identities and wallets. */
function lc_auth_admin_box( $user ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$wallets = lc_auth_wallets( $user->ID );
	echo '<h2>' . esc_html__( 'Sign-in methods (Learn Cardano)', 'learncardano' ) . '</h2><table class="form-table" role="presentation">';
	wp_nonce_field( 'lc_auth_admin_' . $user->ID, 'lc_auth_admin_nonce' );
	echo '<tr><th>' . esc_html__( 'Email', 'learncardano' ) . '</th><td>' . esc_html( $user->user_email ?: '—' ) . ' &middot; ';
	if ( lc_auth_email_verified( $user ) ) {
		echo esc_html__( 'verified', 'learncardano' );
	} elseif ( '' !== $user->user_email ) {
		echo '<label><input type="checkbox" name="lc_auth_mark_verified" value="1"> ' . esc_html__( 'Mark email verified', 'learncardano' ) . '</label>';
	} else {
		echo esc_html__( 'none', 'learncardano' );
	}
	echo '</td></tr>';
	$legacy = (string) get_user_meta( $user->ID, 'lc_legacy_email', true );
	if ( '' !== $legacy ) {
		echo '<tr><th>' . esc_html__( 'Legacy email (unverified)', 'learncardano' ) . '</th><td>' . esc_html( $legacy ) . '</td></tr>';
	}
	echo '<tr><th>' . esc_html__( 'Wallets', 'learncardano' ) . '</th><td>';
	foreach ( $wallets as $stake ) {
		echo '<label style="display:block"><input type="checkbox" name="lc_auth_remove_wallet[]" value="' . esc_attr( $stake ) . '"> ' . esc_html__( 'Remove', 'learncardano' ) . ' <code>' . esc_html( $stake ) . '</code></label>';
	}
	if ( ! $wallets ) {
		echo esc_html__( 'None linked.', 'learncardano' );
	}
	foreach ( lc_auth_idps( $user->ID ) as $provider => $subject ) {
		unset( $subject ); // The subject stays out of the page; the checkbox names the provider.
		echo '<label style="display:block"><input type="checkbox" name="lc_auth_remove_idp[]" value="' . esc_attr( $provider ) . '"> ' . esc_html__( 'Disconnect', 'learncardano' ) . ' <code>' . esc_html( ucfirst( $provider ) ) . '</code></label>';
	}
	if ( lc_is_legacy_wallet_account( $user ) ) {
		echo '<p class="description">' . esc_html__( 'CardanoPress-era account (login is md5 of the stake address); its wallet is adopted at the first wallet sign-in.', 'learncardano' ) . '</p>';
	}
	echo '</td></tr>';
	$merged = (int) get_user_meta( $user->ID, 'lc_merged_into', true );
	if ( $merged ) {
		echo '<tr><th>' . esc_html__( 'Merged into', 'learncardano' ) . '</th><td>#' . (int) $merged . '</td></tr>';
	}
	echo '</table>';
}
add_action( 'show_user_profile', 'lc_auth_admin_box' );
add_action( 'edit_user_profile', 'lc_auth_admin_box' );

function lc_auth_admin_save( $user_id ) {
	if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'edit_user', $user_id )
		|| empty( $_POST['lc_auth_admin_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lc_auth_admin_nonce'] ) ), 'lc_auth_admin_' . $user_id ) ) {
		return;
	}
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return;
	}
	if ( ! empty( $_POST['lc_auth_mark_verified'] ) && '' !== $user->user_email ) {
		lc_auth_mark_email_verified( $user_id, $user->user_email );
		lc_auth_log( 'admin_mark_verified', array( 'user' => $user_id, 'by' => get_current_user_id() ) );
	}
	if ( ! empty( $_POST['lc_auth_remove_wallet'] ) && is_array( $_POST['lc_auth_remove_wallet'] ) ) {
		lc_auth_load( 'wallet' );
		foreach ( wp_unslash( $_POST['lc_auth_remove_wallet'] ) as $stake ) {
			if ( is_string( $stake ) && in_array( $stake, lc_auth_wallets( $user_id ), true ) ) {
				lc_auth_wallet_remove( $user_id, $stake );
				lc_auth_log( 'admin_wallet_removed', array( 'user' => $user_id, 'by' => get_current_user_id() ) );
			}
		}
	}
	if ( ! empty( $_POST['lc_auth_remove_idp'] ) && is_array( $_POST['lc_auth_remove_idp'] ) ) {
		lc_auth_load( 'oauth' );
		foreach ( wp_unslash( $_POST['lc_auth_remove_idp'] ) as $provider ) {
			if ( is_string( $provider ) && isset( lc_auth_idps( $user_id )[ $provider ] ) ) {
				lc_auth_idp_remove( $user_id, $provider );
				lc_auth_log( 'admin_idp_removed', array( 'user' => $user_id, 'by' => get_current_user_id(), 'provider' => $provider ) );
			}
		}
	}
}
add_action( 'personal_options_update', 'lc_auth_admin_save' );
add_action( 'edit_user_profile_update', 'lc_auth_admin_save' );

/**
 * wp-admin's user-edit screen refuses to save a user with no email, but
 * wallet-only members and migrated CardanoPress accounts have none. Let a
 * staff edit through for a non-staff user whose stored email is already
 * empty (nothing is being blanked).
 *
 * @param WP_Error $errors Validation errors.
 * @param bool     $update True when updating an existing user.
 * @param stdClass $user   The user data being saved.
 */
add_action(
	'user_profile_update_errors',
	function ( $errors, $update, $user ) {
		if ( ! $update || ! $errors->get_error_message( 'empty_email' ) || empty( $user->ID ) ) {
			return;
		}
		$stored = get_userdata( (int) $user->ID );
		if ( $stored && '' === (string) $stored->user_email && ! lc_auth_is_privileged( $stored ) ) {
			$errors->remove( 'empty_email' );
		}
	},
	10,
	3
);
