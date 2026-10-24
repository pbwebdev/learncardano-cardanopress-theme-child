<?php
/**
 * Developer knowledge base: the six ACF post types (Concepts, Recipes,
 * Pitfalls, Comparisons, Mini-DApps, Tracks) and their taxonomies.
 *
 * Each type stores structured fields (TL;DR, prerequisites, symptom/cause/fix,
 * options, steps…) that the theme previously never displayed. These helpers
 * render them for single.php, archive.php, template-parts/kb-*.php and the
 * Knowledge base page template. Styles live in style.css under `kb__`.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * Knowledge base post types in hub order: plural label, singular label and
 * a one-line description.
 *
 * @return array<string, array{label: string, single: string, blurb: string}>
 */
function lc_kb_types() {
	$blurbs = array(
		'concept'    => 'Core Cardano ideas explained, with Ethereum comparisons and common misconceptions.',
		'recipe'     => 'Step-by-step builds with code, prerequisites and AI-ready prompts.',
		'pitfall'    => 'Errors builders hit, what causes them and how to fix them.',
		'comparison' => 'Side-by-side looks at tools and approaches, with a clear recommendation.',
		'minidapp'   => 'Small working dApps that combine recipes, with code and example transactions.',
		'track'      => 'Guided learning paths that put concepts, recipes and pitfalls in order.',
	);
	$types  = array();
	foreach ( $blurbs as $type => $blurb ) {
		$object = get_post_type_object( $type );
		if ( $object ) {
			$types[ $type ] = array(
				'label'  => $object->labels->name,
				'single' => $object->labels->singular_name,
				'blurb'  => $blurb,
			);
		}
	}
	return $types;
}

/**
 * Taxonomies shared by the knowledge base types.
 */
function lc_kb_taxonomies() {
	return array( 'difficulty', 'era', 'stack', 'audience', 'kbcategory' );
}

/**
 * Whether a post type (default: the current post's) is a knowledge base type.
 */
function lc_is_kb( $post_type = null ) {
	if ( ! function_exists( 'get_field' ) ) {
		return false;
	}
	$post_type = $post_type ? $post_type : get_post_type();
	return array_key_exists( (string) $post_type, lc_kb_types() );
}

/**
 * URL of the published page using the Knowledge base template, or ''.
 */
function lc_kb_hub_url() {
	$pages = get_pages(
		array(
			'meta_key'   => '_wp_page_template',
			'meta_value' => 'template-knowledge-base.php',
			'number'     => 1,
		)
	);
	return $pages ? get_permalink( $pages[0] ) : '';
}

/**
 * Filter pills for knowledge base archives: the hub (when published) and each
 * type's archive, with the given type marked active.
 *
 * @return array<int, array{0: string, 1: string, 2: bool}>
 */
function lc_kb_type_pills( $active = '' ) {
	$pills = array();
	$hub   = lc_kb_hub_url();
	if ( $hub ) {
		$pills[] = array( __( 'All resources', 'learncardano' ), $hub, false );
	}
	foreach ( lc_kb_types() as $type => $info ) {
		$pills[] = array( $info['label'], get_post_type_archive_link( $type ), $active === $type );
	}
	return $pills;
}

/**
 * A post's term names in a taxonomy, joined with " / " ('' when none).
 */
function lc_kb_term_names( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	return ( $terms && ! is_wp_error( $terms ) ) ? implode( ' / ', wp_list_pluck( $terms, 'name' ) ) : '';
}

/**
 * A post's term slugs in a taxonomy, space separated (for filter data attributes).
 */
function lc_kb_term_slugs( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	return ( $terms && ! is_wp_error( $terms ) ) ? implode( ' ', wp_list_pluck( $terms, 'slug' ) ) : '';
}

/**
 * Short label for a select field's stored value: the ACF choice label up to
 * its first em dash (U+2014), so "High [em dash] silent failure…" gives "High". '' when unset.
 */
function lc_kb_choice( $field, $post_id, $short = true ) {
	$value = get_field( $field, $post_id, false );
	if ( '' === $value || null === $value || false === $value ) {
		return '';
	}
	$object = get_field_object( $field, $post_id );
	$label  = ( $object && isset( $object['choices'][ $value ] ) ) ? $object['choices'][ $value ] : (string) $value;
	if ( $short ) {
		$parts = explode( "\u{2014}", $label );
		$label = trim( $parts[0] );
	}
	return $label;
}

/**
 * Escape a line of field text, turning `backticks` into <code>.
 */
function lc_kb_inline( $text ) {
	return preg_replace( '/`([^`]+)`/', '<code>$1</code>', esc_html( $text ) );
}

/**
 * Split field text into list items: one per line (and per comma when asked,
 * for pros/cons stored as "a,b,c"), dropping bullets and numbering.
 *
 * @return string[]
 */
function lc_kb_lines( $text, $split_commas = false ) {
	$pattern = $split_commas ? '/\r\n|\r|\n|,(?=\S)/' : '/\r\n|\r|\n/';
	$lines   = array();
	foreach ( preg_split( $pattern, (string) $text ) as $line ) {
		$line = trim( preg_replace( '/^\s*(?:[-*•]|\d+[.)])\s+/u', '', $line ) );
		if ( '' !== $line ) {
			$lines[] = $line;
		}
	}
	return $lines;
}

/**
 * Field text as a <ul> (see lc_kb_lines), or '' when empty.
 */
function lc_kb_list( $text, $split_commas = false ) {
	$lines = lc_kb_lines( $text, $split_commas );
	if ( ! $lines ) {
		return '';
	}
	$items = '';
	foreach ( $lines as $line ) {
		$items .= '<li>' . lc_kb_inline( $line ) . '</li>';
	}
	return '<ul class="kb__list">' . $items . '</ul>';
}

/**
 * Field text as paragraphs: fenced ```code``` becomes a code block, blank
 * lines split paragraphs, single newlines become line breaks, and a
 * multi-line block where every line is bulleted or numbered becomes a list.
 */
function lc_kb_prose( $text ) {
	$text   = str_replace( "\r", '', trim( (string) $text ) );
	$blocks = array();
	$text   = preg_replace_callback(
		'/```([\w-]*)\n(.*?)\n?```/s',
		function ( $m ) use ( &$blocks ) {
			$lang     = $m[1] ? ' class="language-' . esc_attr( $m[1] ) . '"' : '';
			$blocks[] = '<pre class="wp-block-code"><code' . $lang . '>' . esc_html( $m[2] ) . '</code></pre>';
			return "\n\n@@lc-kb-code-" . ( count( $blocks ) - 1 ) . "@@\n\n";
		},
		$text
	);

	$out = '';
	foreach ( preg_split( '/\n\s*\n/', trim( $text ) ) as $para ) {
		if ( preg_match( '/^@@lc-kb-code-(\d+)@@$/', trim( $para ), $placeholder ) ) {
			$out .= $blocks[ (int) $placeholder[1] ];
			continue;
		}
		$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $para ) ) ) );
		if ( ! $lines ) {
			continue;
		}
		$numbered = count( preg_grep( '/^\d+[.)]\s/', $lines ) );
		$bulleted = count( preg_grep( '/^[-*•]\s/u', $lines ) );
		if ( count( $lines ) > 1 && ( count( $lines ) === $numbered || count( $lines ) === $bulleted ) ) {
			$tag = count( $lines ) === $numbered ? 'ol' : 'ul';
			$out .= '<' . $tag . '>';
			foreach ( $lines as $line ) {
				$out .= '<li>' . lc_kb_inline( preg_replace( '/^(?:\d+[.)]|[-*•])\s+/u', '', $line ) ) . '</li>';
			}
			$out .= '</' . $tag . '>';
		} else {
			$out .= '<p>' . implode( '<br>', array_map( 'lc_kb_inline', $lines ) ) . '</p>';
		}
	}
	return $out;
}

/**
 * Format a Y-m-d field value as "Nov 2025" ('' when empty or invalid).
 */
function lc_kb_month( $ymd ) {
	$time = $ymd ? strtotime( $ymd ) : false;
	return $time ? date_i18n( 'M Y', $time ) : '';
}

/**
 * Short facts shown as chips under an entry's title: difficulty, era and
 * the type's own details (time, severity, status, verified date…).
 *
 * @return string[]
 */
function lc_kb_facts( $post_id ) {
	$facts = array();
	$level = lc_kb_term_names( $post_id, 'difficulty' );
	$era   = lc_kb_term_names( $post_id, 'era' );
	if ( $level ) {
		$facts[] = $level;
	}
	if ( $era ) {
		/* translators: %s: Cardano era name(s) */
		$facts[] = sprintf( __( '%s era', 'learncardano' ), $era );
	}

	switch ( get_post_type( $post_id ) ) {
		case 'concept':
			$facts[] = lc_kb_choice( 'concept_type', $post_id );
			break;
		case 'recipe':
			$minutes = trim( (string) get_field( 'est_time_minutes', $post_id, false ) );
			if ( $minutes ) {
				/* translators: %s: minutes */
				$facts[] = sprintf( __( '%s min build', 'learncardano' ), $minutes );
			}
			if ( get_field( 'ai_ready', $post_id, false ) ) {
				$facts[] = __( 'AI-ready', 'learncardano' );
			}
			$verified = lc_kb_month( get_field( 'last_verified', $post_id, false ) );
			if ( $verified ) {
				/* translators: %s: month and year */
				$facts[] = sprintf( __( 'Verified %s', 'learncardano' ), $verified );
			}
			break;
		case 'pitfall':
			$severity = lc_kb_choice( 'severity', $post_id );
			if ( $severity ) {
				/* translators: %s: Low, Medium or High */
				$facts[] = sprintf( __( '%s severity', 'learncardano' ), $severity );
			}
			if ( get_field( 'still_present', $post_id, false ) ) {
				$facts[] = __( 'Still present', 'learncardano' );
			}
			break;
		case 'comparison':
			$facts[] = lc_kb_choice( 'confidence', $post_id );
			$updated = lc_kb_month( get_field( 'last_updated', $post_id, false ) );
			if ( $updated ) {
				/* translators: %s: month and year */
				$facts[] = sprintf( __( 'Updated %s', 'learncardano' ), $updated );
			}
			break;
		case 'minidapp':
			$facts[] = lc_kb_choice( 'dapp_status', $post_id );
			$version = trim( (string) get_field( 'version', $post_id, false ) );
			if ( $version ) {
				$facts[] = 'v' . ltrim( $version, 'vV' );
			}
			$verified = lc_kb_month( get_field( 'last_verified', $post_id, false ) );
			if ( $verified ) {
				/* translators: %s: month and year */
				$facts[] = sprintf( __( 'Verified %s', 'learncardano' ), $verified );
			}
			break;
		case 'track':
			$facts[] = lc_kb_choice( 'primary_audience', $post_id );
			$hours   = get_field( 'est_total_hours', $post_id, false );
			if ( $hours ) {
				/* translators: %s: number of hours */
				$facts[] = sprintf( _n( '%s hour', '%s hours', (float) $hours, 'learncardano' ), $hours );
			}
			$status = get_field( 'track_status', $post_id, false );
			if ( $status && 'active' !== $status ) {
				$facts[] = lc_kb_choice( 'track_status', $post_id );
			}
			break;
	}

	return array_values( array_filter( $facts ) );
}

/**
 * Buttons for the external links a recipe or mini-DApp stores.
 */
function lc_kb_links( $post_id ) {
	$links = array(
		'github_url'       => __( 'View code on GitHub', 'learncardano' ),
		'demo_url'         => __( 'Live demo', 'learncardano' ),
		'sandbox_url'      => __( 'Open sandbox', 'learncardano' ),
		'video_url'        => __( 'Watch the video', 'learncardano' ),
		'video_series_url' => __( 'Watch the series', 'learncardano' ),
	);
	$out = '';
	foreach ( $links as $field => $label ) {
		$url = get_field( $field, $post_id, false );
		if ( $url ) {
			$out .= '<a class="kb__link' . ( 'github_url' === $field ? ' kb__link--primary' : '' ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $label ) . ' <span aria-hidden="true">&nearr;</span></a>';
		}
	}
	return $out ? '<div class="kb__links">' . $out . '</div>' : '';
}

/**
 * A titled box of field content (skipped when the content is empty).
 */
function lc_kb_box( $title, $html, $modifier = '' ) {
	if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
		return '';
	}
	return '<section class="kb__box' . ( $modifier ? ' kb__box--' . esc_attr( $modifier ) : '' ) . '"><h2 class="kb__box-title">' . esc_html( $title ) . '</h2>' . $html . '</section>';
}

/**
 * A copyable code block for prompts and file lists.
 */
function lc_kb_code( $title, $text ) {
	$text = trim( (string) $text );
	if ( '' === $text ) {
		return '';
	}
	return '<div class="kb__code"><div class="kb__code-head"><span>' . esc_html( $title ) . '</span><button type="button" class="kb__copy">' . esc_html__( 'Copy', 'learncardano' ) . '</button></div><pre><code>' . esc_html( $text ) . '</code></pre></div>';
}

/**
 * Everything shown above an entry's main content: the comparison question,
 * TL;DR, "also known as", and the type's key panels.
 */
function lc_kb_render_intro( $post_id ) {
	$type = get_post_type( $post_id );

	if ( 'comparison' === $type ) {
		$question = get_field( 'question', $post_id, false );
		if ( $question ) {
			echo '<p class="kb__question">' . lc_kb_inline( $question ) . '</p>';
		}
	}

	$tldr = get_field( 'tldr', $post_id, false );
	if ( $tldr ) {
		echo '<div class="kb__tldr"><span class="kb__tldr-label">TL;DR</span>' . lc_kb_prose( $tldr ) . '</div>';
	}

	switch ( $type ) {
		case 'concept':
			$aka = get_field( 'concept_aka', $post_id, false );
			if ( $aka ) {
				echo '<p class="kb__aka"><strong>' . esc_html__( 'Also known as:', 'learncardano' ) . '</strong> ' . lc_kb_inline( $aka ) . '</p>';
			}
			break;

		case 'recipe':
			echo lc_kb_links( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in lc_kb_links().
			echo '<div class="kb__pair">';
			echo lc_kb_box( __( 'Before you start', 'learncardano' ), lc_kb_list( get_field( 'prerequisites', $post_id, false ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo lc_kb_box( __( 'You will learn', 'learncardano' ), lc_kb_list( get_field( 'learning_outcomes', $post_id, false ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			break;

		case 'pitfall':
			echo '<div class="kb__scf">';
			echo lc_kb_box( __( 'Symptom', 'learncardano' ), lc_kb_prose( get_field( 'symptom', $post_id, false ) ), 'symptom' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo lc_kb_box( __( 'Cause', 'learncardano' ), lc_kb_prose( get_field( 'cause', $post_id, false ) ), 'cause' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo lc_kb_box( __( 'Fix', 'learncardano' ), lc_kb_prose( get_field( 'fix', $post_id, false ) ), 'fix' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
			$signatures = lc_kb_lines( get_field( 'error_signatures', $post_id, false ) );
			if ( $signatures ) {
				echo '<div class="kb__code"><div class="kb__code-head"><span>' . esc_html__( 'Error messages that point here', 'learncardano' ) . '</span></div><pre><code>' . esc_html( implode( "\n", $signatures ) ) . '</code></pre></div>';
			}
			break;

		case 'comparison':
			$matrix = get_field( 'use_case_matrix', $post_id );
			if ( $matrix ) {
				echo '<section class="kb__box kb__box--table"><h2 class="kb__box-title">' . esc_html__( 'Quick pick', 'learncardano' ) . '</h2><div class="kb__table-wrap"><table class="kb__table"><thead><tr><th>' . esc_html__( 'If you need', 'learncardano' ) . '</th><th>' . esc_html__( 'Choose', 'learncardano' ) . '</th><th>' . esc_html__( 'Why', 'learncardano' ) . '</th></tr></thead><tbody>';
				foreach ( $matrix as $row ) {
					echo '<tr><td>' . lc_kb_inline( $row['use_case'] ) . '</td><td><strong>' . lc_kb_inline( $row['recommended_option'] ) . '</strong></td><td>' . lc_kb_inline( $row['why'] ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
				echo '</tbody></table></div></section>';
			}
			break;

		case 'minidapp':
			echo lc_kb_links( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;

		case 'track':
			echo lc_kb_box( __( 'By the end of this track', 'learncardano' ), lc_kb_prose( get_field( 'outcome_statement', $post_id, false ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput
			$steps = array();
			foreach ( (array) get_field( 'track_steps', $post_id, false ) as $step ) {
				if ( ! is_array( $step ) ) {
					continue;
				}
				foreach ( $step as $value ) {
					$step_id = is_numeric( $value ) ? (int) $value : 0;
					if ( $step_id && 'publish' === get_post_status( $step_id ) ) {
						$steps[] = $step_id;
						break;
					}
				}
			}
			$capstone = (int) get_field( 'capstone_dapp', $post_id, false );
			if ( $capstone && 'publish' === get_post_status( $capstone ) && ! in_array( $capstone, $steps, true ) ) {
				$steps[] = $capstone;
			}
			if ( $steps ) {
				$types = lc_kb_types();
				echo '<section class="kb__box"><h2 class="kb__box-title">' . esc_html__( 'The steps', 'learncardano' ) . '</h2><ol class="kb__steps">';
				foreach ( $steps as $step_id ) {
					$step_type = get_post_type( $step_id );
					echo '<li><a href="' . esc_url( get_permalink( $step_id ) ) . '"><span class="kb__step-type">' . esc_html( isset( $types[ $step_type ] ) ? $types[ $step_type ]['single'] : '' ) . '</span> ' . esc_html( get_the_title( $step_id ) ) . '</a></li>';
				}
				echo '</ol></section>';
			}
			break;
	}
}

/**
 * Everything shown below an entry's main content: the type's detail panels.
 */
function lc_kb_render_details( $post_id ) {
	switch ( get_post_type( $post_id ) ) {
		case 'concept':
			echo lc_kb_box( __( 'Coming from Ethereum?', 'learncardano' ), lc_kb_prose( get_field( 'analogy_evm', $post_id, false ) ), 'evm' ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo lc_kb_box( __( 'Common misconceptions', 'learncardano' ), lc_kb_prose( get_field( 'common_misconceptions', $post_id, false ) ), 'warn' ); // phpcs:ignore WordPress.Security.EscapeOutput
			break;

		case 'recipe':
			$prompts = lc_kb_code( __( 'System prompt', 'learncardano' ), get_field( 'ai_system_prompt', $post_id, false ) )
				. lc_kb_code( __( 'Starter prompt', 'learncardano' ), get_field( 'ai_starter_prompt', $post_id, false ) )
				. lc_kb_code( __( 'Files to give the assistant', 'learncardano' ), get_field( 'ai_context_files', $post_id, false ) );
			if ( $prompts ) {
				echo '<section class="kb__box kb__box--ai"><h2 class="kb__box-title">' . esc_html__( 'Build it with an AI assistant', 'learncardano' ) . '</h2><p>' . esc_html__( 'Paste these into Claude, ChatGPT or your coding assistant to extend this recipe.', 'learncardano' ) . '</p>' . $prompts . '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			break;

		case 'comparison':
			$options = get_field( 'options', $post_id );
			if ( $options ) {
				echo '<section class="kb__options"><h2 class="kb__box-title">' . esc_html__( 'The options', 'learncardano' ) . '</h2><div class="kb__options-grid">';
				foreach ( $options as $option ) {
					echo '<div class="kb__option"><h3>' . esc_html( $option['option_name'] ) . '</h3>';
					if ( $option['option_summary'] ) {
						echo '<p>' . lc_kb_inline( $option['option_summary'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
					}
					$pros = lc_kb_list( $option['option_pros'], true );
					$cons = lc_kb_list( $option['option_cons'], true );
					if ( $pros ) {
						echo '<p class="kb__option-label kb__option-label--pro">' . esc_html__( 'Pros', 'learncardano' ) . '</p>' . $pros; // phpcs:ignore WordPress.Security.EscapeOutput
					}
					if ( $cons ) {
						echo '<p class="kb__option-label kb__option-label--con">' . esc_html__( 'Cons', 'learncardano' ) . '</p>' . $cons; // phpcs:ignore WordPress.Security.EscapeOutput
					}
					if ( $option['option_pick_when'] ) {
						echo '<p class="kb__option-pick"><strong>' . esc_html__( 'Pick it when:', 'learncardano' ) . '</strong> ' . lc_kb_inline( $option['option_pick_when'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput
					}
					if ( $option['option_url'] ) {
						echo '<a class="kb__option-link" href="' . esc_url( $option['option_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Website', 'learncardano' ) . ' <span aria-hidden="true">&nearr;</span></a>';
					}
					echo '</div>';
				}
				echo '</div></section>';
			}
			break;

		case 'minidapp':
			$stack = '';
			foreach (
				array(
					'on_chain_lang'   => __( 'On-chain', 'learncardano' ),
					'off_chain_stack' => __( 'Off-chain', 'learncardano' ),
					'frontend_stack'  => __( 'Frontend', 'learncardano' ),
				) as $field => $label
			) {
				$value = get_field( $field, $post_id, false );
				if ( $value ) {
					$stack .= '<div><span class="kb__stack-label">' . esc_html( $label ) . '</span>' . lc_kb_prose( $value ) . '</div>';
				}
			}
			$architecture = lc_kb_prose( get_field( 'architecture_summary', $post_id, false ) ) . ( $stack ? '<div class="kb__stack">' . $stack . '</div>' : '' );
			echo lc_kb_box( __( 'Architecture', 'learncardano' ), $architecture ); // phpcs:ignore WordPress.Security.EscapeOutput

			$txs = get_field( 'tx_examples', $post_id );
			if ( $txs ) {
				$explorers = array(
					'preview' => 'https://preview.cardanoscan.io/transaction/',
					'preprod' => 'https://preprod.cardanoscan.io/transaction/',
					'mainnet' => 'https://cardanoscan.io/transaction/',
				);
				$rows = '';
				foreach ( $txs as $tx ) {
					$hash = trim( (string) $tx['tx_hash'] );
					// Only real transaction ids (64 hex chars) get an explorer link.
					if ( ! preg_match( '/^[0-9a-f]{64}$/i', $hash ) ) {
						continue;
					}
					$network = isset( $explorers[ $tx['tx_network'] ] ) ? $tx['tx_network'] : 'mainnet';
					$rows   .= '<tr><td>' . esc_html( $tx['tx_label'] ) . '</td><td>' . esc_html( ucfirst( $network ) ) . '</td><td><a href="' . esc_url( $explorers[ $network ] . $hash ) . '" target="_blank" rel="noopener"><code>' . esc_html( substr( $hash, 0, 12 ) ) . '…</code></a></td></tr>';
				}
				if ( $rows ) {
					echo '<section class="kb__box kb__box--table"><h2 class="kb__box-title">' . esc_html__( 'Example transactions', 'learncardano' ) . '</h2><div class="kb__table-wrap"><table class="kb__table"><thead><tr><th>' . esc_html__( 'Transaction', 'learncardano' ) . '</th><th>' . esc_html__( 'Network', 'learncardano' ) . '</th><th>' . esc_html__( 'View on Cardanoscan', 'learncardano' ) . '</th></tr></thead><tbody>' . $rows . '</tbody></table></div></section>'; // phpcs:ignore WordPress.Security.EscapeOutput
				}
			}
			break;
	}
}

/**
 * Linked entries to show after an entry, grouped under headings, from its
 * relationship fields; falls back to more entries of the same type.
 *
 * @return array<string, int[]>
 */
function lc_kb_related_groups( $post_id ) {
	$map = array(
		'concept'    => array(
			'prerequisite_concepts' => __( 'Read these first', 'learncardano' ),
			'related_recipes'       => __( 'Put it into practice', 'learncardano' ),
			'related_pitfalls'      => __( 'Pitfalls to watch for', 'learncardano' ),
		),
		'recipe'     => array(
			'related_concepts' => __( 'Concepts behind this recipe', 'learncardano' ),
			'related_pitfalls' => __( 'Pitfalls to watch for', 'learncardano' ),
			'related_recipes'  => __( 'Related recipes', 'learncardano' ),
		),
		'pitfall'    => array(
			'related_concepts' => __( 'Understand why', 'learncardano' ),
			'related_recipes'  => __( 'Recipes that get it right', 'learncardano' ),
		),
		'minidapp'   => array(
			'composed_of_recipes' => __( 'Built from these recipes', 'learncardano' ),
			'related_pitfalls'    => __( 'Pitfalls to watch for', 'learncardano' ),
		),
		'comparison' => array(),
		'track'      => array(),
	);
	$type   = get_post_type( $post_id );
	$groups = array();
	$seen   = array( $post_id );
	foreach ( isset( $map[ $type ] ) ? $map[ $type ] : array() as $field => $title ) {
		$ids = array();
		foreach ( (array) get_field( $field, $post_id, false ) as $id ) {
			$id = (int) $id;
			if ( $id && ! in_array( $id, $seen, true ) && 'publish' === get_post_status( $id ) ) {
				$ids[]  = $id;
				$seen[] = $id;
			}
		}
		if ( $ids ) {
			$groups[ $title ] = $ids;
		}
	}
	if ( ! $groups ) {
		$more = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'post__not_in'   => array( $post_id ),
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		if ( $more ) {
			$types = lc_kb_types();
			/* translators: %s: plural type name, e.g. Concepts */
			$groups[ sprintf( __( 'More %s', 'learncardano' ), strtolower( $types[ $type ]['label'] ) ) ] = $more;
		}
	}
	return $groups;
}

/**
 * Copy-to-clipboard for .kb__copy buttons (prompts and file lists).
 */
function lc_kb_copy_script() {
	?>
	<script>
		document.querySelectorAll('.kb__copy').forEach(function (button) {
			button.addEventListener('click', function () {
				var code = button.closest('.kb__code').querySelector('code');
				navigator.clipboard.writeText(code.textContent).then(function () {
					button.textContent = '<?php echo esc_js( __( 'Copied', 'learncardano' ) ); ?>';
					setTimeout(function () { button.textContent = '<?php echo esc_js( __( 'Copy', 'learncardano' ) ); ?>'; }, 2000);
				});
			});
		});
	</script>
	<?php
}
