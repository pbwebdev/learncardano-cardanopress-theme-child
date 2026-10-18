<?php
/**
 * Primary navigation: a Bootstrap 5 navbar walker.
 *
 * Replaces the parent's Clean_Navbar walker and its filters. The filters that
 * only apply to our walker are keyed on instanceof LC_Navbar_Walker; the
 * parent also leaves nav_menu_item_id and wp_nav_menu_args global, so those
 * two are global here as well (menus rendered by other code keep working the
 * same way).
 *
 * @package learncardano
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'LC_Navbar_Walker' ) ) {
	class LC_Navbar_Walker extends Walker_Nav_Menu {}
}

add_filter(
	'nav_menu_submenu_css_class',
	function ( $classes, $args ) {
		if ( ! $args->walker instanceof LC_Navbar_Walker ) {
			return $classes;
		}

		return array( 'dropdown-menu' );
	},
	PHP_INT_MAX,
	2
);

add_filter(
	'nav_menu_css_class',
	function ( $classes, $menu_item, $args ) {
		if ( ! $args->walker instanceof LC_Navbar_Walker ) {
			return $classes;
		}

		$classes = array( 'nav-item' );

		if ( $args->walker->has_children ) {
			$classes[] = 'dropdown';
		}

		return $classes;
	},
	PHP_INT_MAX,
	3
);

// Global, as in the parent: no ids on menu items.
add_filter(
	'nav_menu_item_id',
	function () {
		return '';
	}
);

add_filter(
	'nav_menu_link_attributes',
	function ( $atts, $menu_item, $args ) {
		if ( ! $args->walker instanceof LC_Navbar_Walker ) {
			return $atts;
		}

		$atts['class'] = 'nav-link';

		if ( $args->walker->has_children ) {
			$atts['class']         .= ' dropdown-toggle';
			$atts['data-bs-toggle'] = 'dropdown';
			$atts['aria-haspopup']  = 'true';
		}

		if ( $menu_item->current ) {
			$atts['class'] .= ' active';
		}

		return $atts;
	},
	PHP_INT_MAX,
	3
);

// Global, as in the parent: fallback "add a menu" links and a <ul> without an id.
add_filter(
	'wp_nav_menu_args',
	function ( $args ) {
		if ( 'wp_page_menu' === $args['fallback_cb'] ) {
			$args['fallback_cb'] = function ( $args ) {
				$texts = array(
					'Click here',
					'to add',
					'a menu',
				);

				$nav_menu = '<ul class="nav nav-pills"><li class="nav-item">' . implode(
					'</li><li class="nav-item">',
					array_map(
						function ( $text ) {
							return '<a href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '" class="nav-link">' . $text . '</a>';
						},
						$texts
					)
				) . '</li></ul>';

				if ( $args['echo'] ) {
					echo $nav_menu; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static text and an escaped URL.
				} else {
					return $nav_menu;
				}
			};
		}

		if ( '<ul id="%1$s" class="%2$s">%3$s</ul>' === $args['items_wrap'] ) {
			$args['items_wrap'] = '<ul class="%2$s">%3$s</ul>';
		}

		return $args;
	},
	PHP_INT_MAX
);

/**
 * Print the primary menu. Same wp_nav_menu() call as the parent's
 * cardanopress_bootstrap_primary_menu(), with our walker and no fallback
 * (nothing is printed when no menu is assigned).
 *
 * @param string $class The <ul> class.
 */
function lc_primary_menu( $class = 'navbar-nav' ) {
	wp_nav_menu(
		array(
			'theme_location' => 'primary',
			'menu_class'     => $class,
			'walker'         => new LC_Navbar_Walker(),
			'depth'          => 0,
			'fallback_cb'    => false,
		)
	);
}
