<?php
/**
 * Template Name: Delegate to ADAOZ
 *
 * Self-contained delegation page, ported from the cardanode.com.au #delegate
 * section: a wallet panel that connects any CIP-30 wallet and builds, signs and
 * submits the stake delegation (plus the one-off stake-key registration when
 * needed) with a self-hosted Mesh SDK bundle loaded on first click. It replaces
 * the CardanoPress plugin's delegation template, which went through the
 * plugin's legacy wallet login. Chain data (pool stats, the wallet's account,
 * protocol parameters) comes from Koios via includes/delegate.php.
 *
 * Panel logic: assets/js/delegate-panel.js. Styles: `.dlg__` in style.css.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

wp_enqueue_script_module(
	'lc-delegate-panel',
	lc_theme_asset_url( 'assets/js/delegate-panel.js' ),
	array(),
	null
);

get_header();

$lc_pool_id = lc_adaoz_pool_id();
$lc_stats   = lc_adaoz_pool_stats();
$lc_check   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.801 10A10 10 0 1 1 17 3.335"/><path d="m9 11 3 3L22 4"/></svg>';
?>

<main class="dlg">
	<?php while ( have_posts() ) : ?>
		<?php the_post(); ?>
		<div class="dlg__container">

			<div class="dlg__main">
				<div class="dlg__eyebrow"><?php esc_html_e( 'Stake ADA · ADAOZ', 'learncardano' ); ?></div>
				<h1 class="dlg__title"><?php the_title(); ?></h1>
				<p class="dlg__lede">
					<?php esc_html_e( 'Connect your Cardano wallet and delegate to ADAOZ on-chain in two clicks. First-time delegators pay a one-off', 'learncardano' ); ?>
					<strong><?php esc_html_e( '2 ₳ stake-key registration deposit', 'learncardano' ); ?></strong>
					<?php esc_html_e( '— refunded if you ever de-register.', 'learncardano' ); ?>
				</p>

				<?php if ( trim( get_the_content() ) ) : ?>
					<div class="dlg__intro"><?php the_content(); ?></div>
				<?php endif; ?>

				<div id="delegate-panel" class="dlg__panel" aria-live="polite" data-state="idle"
					data-pool="<?php echo esc_attr( $lc_pool_id ); ?>"
					data-api="<?php echo esc_url( lc_delegation_api_url() ); ?>"
					data-mesh-url="<?php echo esc_url( lc_mesh_bundle_url() ); ?>">
					<div id="dp-status-banner" class="dlg__banner" role="status" hidden></div>

					<div data-show="idle">
						<button type="button" class="dlg__btn dlg__btn--lg" data-action="open-picker">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/></svg>
							<?php esc_html_e( 'Connect wallet', 'learncardano' ); ?>
						</button>
						<p class="dlg__hint"><?php echo wp_kses_post( __( 'Works with <strong>Lace</strong>, <strong>Eternl</strong>, Nami, Typhon, NuFi, Begin, Yoroi — any CIP-30 wallet. <strong>We never see your keys.</strong>', 'learncardano' ) ); ?></p>
					</div>

					<div data-show="picking" hidden>
						<h3><?php esc_html_e( 'Choose a wallet', 'learncardano' ); ?></h3>
						<ul id="dp-wallets" class="dlg__wallets" aria-label="<?php esc_attr_e( 'Installed Cardano wallets', 'learncardano' ); ?>"></ul>
						<button type="button" class="dlg__btn dlg__btn--ghost" data-action="cancel"><?php esc_html_e( 'Cancel', 'learncardano' ); ?></button>
					</div>

					<div data-show="connecting" hidden>
						<h3><?php esc_html_e( 'Connecting…', 'learncardano' ); ?></h3>
						<p><?php esc_html_e( 'Approve the connection in your wallet.', 'learncardano' ); ?></p>
					</div>

					<div data-show="connected" hidden>
						<h3><?php esc_html_e( 'Wallet connected', 'learncardano' ); ?></h3>
						<dl class="dlg__kv">
							<dt><?php esc_html_e( 'Stake address', 'learncardano' ); ?></dt><dd><code id="dp-stake-addr"></code></dd>
							<dt><?php esc_html_e( 'Wallet balance', 'learncardano' ); ?></dt><dd id="dp-balance">—</dd>
							<dt><?php esc_html_e( 'Stake key', 'learncardano' ); ?></dt><dd id="dp-reg-status">—</dd>
							<dt><?php esc_html_e( 'Currently delegated', 'learncardano' ); ?></dt><dd id="dp-current-pool">—</dd>
						</dl>
						<p class="dlg__actions">
							<button type="button" id="dp-delegate" class="dlg__btn dlg__btn--lg" data-action="delegate"><?php esc_html_e( 'Delegate to ADAOZ', 'learncardano' ); ?></button>
							<button type="button" class="dlg__btn dlg__btn--ghost" data-action="disconnect"><?php esc_html_e( 'Disconnect', 'learncardano' ); ?></button>
						</p>
					</div>

					<div data-show="working" hidden>
						<h3 id="dp-working-title"><?php esc_html_e( 'Working…', 'learncardano' ); ?></h3>
						<p id="dp-working-msg" class="dlg__hint"></p>
					</div>

					<div data-show="success" hidden>
						<h3><?php esc_html_e( 'Transaction submitted 🎉', 'learncardano' ); ?></h3>
						<p><?php esc_html_e( 'Your delegation to ADAOZ is on the way. Rewards start flowing in 2–3 epochs (around 10–15 days). Thank you for supporting Learn Cardano.', 'learncardano' ); ?></p>
						<p><?php esc_html_e( 'Transaction:', 'learncardano' ); ?> <a id="dp-tx-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'view on Cardanoscan', 'learncardano' ); ?></a></p>
						<button type="button" class="dlg__btn dlg__btn--ghost" data-action="reset"><?php esc_html_e( 'Done', 'learncardano' ); ?></button>
					</div>

					<div data-show="already-adaoz" hidden>
						<h3><?php esc_html_e( 'You’re already delegated to ADAOZ 🎉', 'learncardano' ); ?></h3>
						<p><?php esc_html_e( 'Thanks for your support. Nothing more to do — rewards keep coming every epoch.', 'learncardano' ); ?></p>
						<button type="button" class="dlg__btn dlg__btn--ghost" data-action="disconnect"><?php esc_html_e( 'Disconnect', 'learncardano' ); ?></button>
					</div>

					<div data-show="error" hidden>
						<h3><?php esc_html_e( 'Something went wrong', 'learncardano' ); ?></h3>
						<p id="dp-error-msg" class="dlg__error"></p>
						<button type="button" class="dlg__btn" data-action="retry"><?php esc_html_e( 'Try again', 'learncardano' ); ?></button>
					</div>
				</div>

				<a class="dlg__calc" href="https://cardanode.com.au/cardano-staking-calculator-rewards-ada/">
					<span class="dlg__calc-icon" aria-hidden="true">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="18" rx="2"/><line x1="8" y1="7" x2="16" y2="7"/><line x1="8" y1="11" x2="10" y2="11"/><line x1="12" y1="11" x2="14" y2="11"/><line x1="16" y1="11" x2="16" y2="11.01"/><line x1="8" y1="15" x2="10" y2="15"/><line x1="12" y1="15" x2="14" y2="15"/><line x1="16" y1="15" x2="16" y2="15.01"/><line x1="8" y1="19" x2="10" y2="19"/><line x1="12" y1="19" x2="14" y2="19"/><line x1="16" y1="19" x2="16" y2="19.01"/></svg>
					</span>
					<span class="dlg__calc-text">
						<strong><?php esc_html_e( 'Curious what you could earn?', 'learncardano' ); ?></strong>
						<?php esc_html_e( 'Use the Cardano staking calculator to project your ADA rewards over time — including weekly DCA.', 'learncardano' ); ?>
					</span>
					<span class="dlg__calc-arrow" aria-hidden="true">&rarr;</span>
				</a>

				<details class="dlg__manual">
					<summary><?php esc_html_e( 'Prefer to delegate from inside your wallet?', 'learncardano' ); ?></summary>
					<ol>
						<li><?php esc_html_e( 'Open your Cardano wallet (Lace or Eternl recommended).', 'learncardano' ); ?></li>
						<li><?php esc_html_e( 'Go to the staking or delegation section.', 'learncardano' ); ?></li>
						<li><?php echo wp_kses_post( __( 'Search for the ticker <strong>ADAOZ</strong>, or paste the pool ID below, and select the pool.', 'learncardano' ) ); ?></li>
						<li><?php esc_html_e( 'Confirm. First-time delegators pay a one-off 2 ₳ stake-key registration deposit, which you get back if you ever de-register.', 'learncardano' ); ?></li>
					</ol>
					<div class="dlg__pool-id">
						<code id="dlg-pool-id"><?php echo esc_html( $lc_pool_id ); ?></code>
						<button type="button" class="dlg__btn dlg__btn--ghost dlg__btn--sm" data-copy="#dlg-pool-id"><?php esc_html_e( 'Copy pool ID', 'learncardano' ); ?></button>
					</div>
				</details>

				<div class="dlg__links">
					<a href="https://cardanode.com.au/blog/category/staking/"><?php esc_html_e( 'Read the staking guides', 'learncardano' ); ?></a>
					<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Questions? Get in touch', 'learncardano' ); ?></a>
				</div>
			</div>

			<aside class="dlg__aside">
				<?php if ( $lc_stats ) : ?>
					<div class="dlg__stats">
						<h2 class="dlg__aside-title"><?php esc_html_e( 'ADAOZ at a glance', 'learncardano' ); ?></h2>
						<dl>
							<div><dt><?php esc_html_e( 'Live stake', 'learncardano' ); ?></dt><dd><?php echo esc_html( lc_format_ada( $lc_stats['live_stake'] ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Delegators', 'learncardano' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $lc_stats['delegators'] ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Margin', 'learncardano' ); ?></dt><dd><?php echo esc_html( rtrim( rtrim( number_format_i18n( $lc_stats['margin'], 2 ), '0' ), '.' ) . '%' ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Fixed fee', 'learncardano' ); ?></dt><dd><?php echo esc_html( lc_format_ada( $lc_stats['fixed_cost'] ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Pledge', 'learncardano' ); ?></dt><dd><?php echo esc_html( lc_format_ada( $lc_stats['pledge'] ) ); ?></dd></div>
							<div><dt><?php esc_html_e( 'Blocks minted', 'learncardano' ); ?></dt><dd><?php echo esc_html( number_format_i18n( $lc_stats['blocks'] ) ); ?></dd></div>
						</dl>
						<p class="dlg__stats-note"><?php esc_html_e( 'Live from the Cardano blockchain via Koios, refreshed every few hours.', 'learncardano' ); ?></p>
					</div>
				<?php endif; ?>

				<h2 class="dlg__aside-title"><?php esc_html_e( 'What to expect', 'learncardano' ); ?></h2>
				<ul class="dlg__expect">
					<li><span class="dlg__icon"><?php echo $lc_check; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?></span><div><h3><?php esc_html_e( 'Non-custodial', 'learncardano' ); ?></h3><p><?php esc_html_e( 'Your ADA never leaves your wallet. Delegating only points your stake — we can’t move or spend it.', 'learncardano' ); ?></p></div></li>
					<li><span class="dlg__icon"><?php echo $lc_check; // phpcs:ignore WordPress.Security.EscapeOutput ?></span><div><h3><?php esc_html_e( 'Two clicks', 'learncardano' ); ?></h3><p><?php esc_html_e( 'Connect your wallet, sign one transaction. That’s it.', 'learncardano' ); ?></p></div></li>
					<li><span class="dlg__icon"><?php echo $lc_check; // phpcs:ignore WordPress.Security.EscapeOutput ?></span><div><h3><?php esc_html_e( 'One-off 2 ₳ deposit', 'learncardano' ); ?></h3><p><?php esc_html_e( 'A refundable stake-key registration deposit — you get it back if you ever de-register.', 'learncardano' ); ?></p></div></li>
					<li><span class="dlg__icon"><?php echo $lc_check; // phpcs:ignore WordPress.Security.EscapeOutput ?></span><div><h3><?php esc_html_e( 'Rewards every epoch', 'learncardano' ); ?></h3><p><?php esc_html_e( 'Earn roughly every 5 days, automatically compounding. No claiming needed.', 'learncardano' ); ?></p></div></li>
				</ul>
			</aside>

		</div>
	<?php endwhile; ?>
</main><!-- .dlg -->

<?php

get_footer();
