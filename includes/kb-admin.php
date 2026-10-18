<?php
/**
 * One "Knowledge Base" admin menu for the six knowledge base post types and
 * their shared taxonomies, instead of six top-level menus.
 *
 * The post types join it through "admin_menu_parent": "lc-knowledge-base" in
 * their acf-json files; WordPress then lists each type's "All …" screen here.
 * Taxonomy screens and "Add new" links are added below, since WordPress only
 * shows those under a post type's own top-level menu.
 *
 * @package CardanoPress Bootstrap
 */

const LC_KB_MENU = 'lc-knowledge-base';

/**
 * Top-level menu with an overview page. Priority 9 so it exists before
 * WordPress adds the post type screens to it (admin_menu, priority 10).
 */
add_action(
	'admin_menu',
	function () {
		add_menu_page(
			__( 'Knowledge Base', 'learncardano' ),
			__( 'Knowledge Base', 'learncardano' ),
			'edit_posts',
			LC_KB_MENU,
			'lc_kb_admin_overview',
			'dashicons-book-alt',
			6
		);
		// The automatic first item repeats the menu title; call it Overview.
		add_submenu_page( LC_KB_MENU, __( 'Knowledge Base', 'learncardano' ), __( 'Overview', 'learncardano' ), 'edit_posts', LC_KB_MENU, 'lc_kb_admin_overview' );
	},
	9
);

/**
 * After WordPress has added the post type screens: name them by type
 * ("Concepts", not "All Concepts"), put them in hub order, then add the
 * taxonomy screens.
 */
add_action(
	'admin_menu',
	function () {
		global $submenu;
		if ( isset( $submenu[ LC_KB_MENU ] ) ) {
			$items = array();
			$rest  = array();
			foreach ( $submenu[ LC_KB_MENU ] as $item ) {
				if ( 0 === strpos( $item[2], 'edit.php?post_type=' ) ) {
					$items[ substr( $item[2], strlen( 'edit.php?post_type=' ) ) ] = $item;
				} else {
					$rest[] = $item;
				}
			}
			$ordered = $rest;
			foreach ( lc_kb_types() as $type => $info ) {
				if ( isset( $items[ $type ] ) ) {
					$items[ $type ][0] = $info['label'];
					$ordered[]         = $items[ $type ];
				}
			}
			$submenu[ LC_KB_MENU ] = $ordered; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reordering our own menu.
		}

		foreach ( lc_kb_taxonomies() as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );
			if ( ! $object ) {
				continue;
			}
			add_submenu_page(
				LC_KB_MENU,
				$object->labels->name,
				$object->labels->name,
				$object->cap->manage_terms,
				'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=concept'
			);
		}
	},
	20
);

/**
 * Keep the Knowledge Base menu open on the taxonomy and editor screens.
 */
add_filter(
	'parent_file',
	function ( $parent_file ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return $parent_file;
		}
		if ( in_array( $screen->taxonomy, lc_kb_taxonomies(), true ) ) {
			return LC_KB_MENU;
		}
		if ( $screen->post_type && array_key_exists( $screen->post_type, lc_kb_types() ) ) {
			return LC_KB_MENU;
		}
		return $parent_file;
	}
);

add_filter(
	'submenu_file',
	function ( $submenu_file ) {
		$screen = get_current_screen();
		if ( $screen && in_array( $screen->taxonomy, lc_kb_taxonomies(), true ) ) {
			return 'edit-tags.php?taxonomy=' . $screen->taxonomy . '&post_type=concept';
		}
		if ( $screen && 'post' === $screen->base && array_key_exists( $screen->post_type, lc_kb_types() ) ) {
			return 'edit.php?post_type=' . $screen->post_type;
		}
		return $submenu_file;
	}
);

/**
 * Overview: each type with its counts and quick links, then the taxonomies.
 */
function lc_kb_admin_overview() {
	$hub = lc_kb_hub_url();
	?>
	<div class="wrap">
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Knowledge Base', 'learncardano' ); ?></h1>
		<?php if ( $hub ) : ?>
			<a href="<?php echo esc_url( $hub ); ?>" class="page-title-action" target="_blank" rel="noopener"><?php esc_html_e( 'View on site', 'learncardano' ); ?></a>
		<?php endif; ?>
		<hr class="wp-header-end">

		<table class="widefat striped" style="max-width:900px;margin-top:16px">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Type', 'learncardano' ); ?></th>
					<th><?php esc_html_e( 'Published', 'learncardano' ); ?></th>
					<th><?php esc_html_e( 'Drafts', 'learncardano' ); ?></th>
					<th><?php esc_html_e( 'URL', 'learncardano' ); ?></th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( lc_kb_types() as $type => $info ) : ?>
					<?php
					$counts  = wp_count_posts( $type );
					$archive = get_post_type_archive_link( $type );
					?>
					<tr>
						<td><strong><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . $type ) ); ?>"><?php echo esc_html( $info['label'] ); ?></a></strong><br><span class="description"><?php echo esc_html( $info['blurb'] ); ?></span></td>
						<td><?php echo esc_html( (string) (int) $counts->publish ); ?></td>
						<td><?php echo esc_html( (string) (int) $counts->draft ); ?></td>
						<td><?php if ( $archive ) : ?><a href="<?php echo esc_url( $archive ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( wp_make_link_relative( $archive ) ); ?></code></a><?php endif; ?></td>
						<td><a class="button button-small" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . $type ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: singular type name */ __( 'Add %s', 'learncardano' ), $info['single'] ) ); ?></a></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'Shared taxonomies', 'learncardano' ); ?></h2>
		<p>
			<?php
			$links = array();
			foreach ( lc_kb_taxonomies() as $taxonomy ) {
				$object = get_taxonomy( $taxonomy );
				if ( $object ) {
					$links[] = '<a href="' . esc_url( admin_url( 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=concept' ) ) . '">' . esc_html( $object->labels->name ) . '</a> (' . (int) wp_count_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) . ')';
				}
			}
			echo implode( ' &middot; ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
			?>
		</p>
	</div>
	<?php
}
