<?php
/**
 * ADAOZ stake pool data and the REST endpoint behind the self-contained
 * delegate page (template-delegate.php + assets/js/delegate-panel.js).
 *
 * All chain data comes from Koios (free public API) on the server: Koios
 * doesn't send CORS headers, so the browser can't call it directly.
 *
 * @package CardanoPress Bootstrap
 */

/**
 * The ADAOZ pool ID (bech32). The delegate panel delegates to this pool.
 */
function lc_adaoz_pool_id() {
	return 'pool1vev8z03vh7jwx3mfrgzrt9fltt97nupaxv8ffj4r5r8mgwts5ze';
}

/**
 * Call a Koios endpoint (GET, or POST when $body is given).
 *
 * @return array|null Decoded JSON, or null on any failure.
 */
function lc_koios( $path, $body = null ) {
	$url  = 'https://api.koios.rest/api/v1/' . ltrim( $path, '/' );
	$args = array(
		'timeout' => 10,
		'headers' => array( 'accept' => 'application/json' ),
	);
	if ( null === $body ) {
		$response = wp_remote_get( $url, $args );
	} else {
		$args['headers']['content-type'] = 'application/json';
		$args['body']                    = wp_json_encode( $body );
		$response                        = wp_remote_post( $url, $args );
	}
	if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	return is_array( $data ) ? $data : null;
}

/**
 * Live ADAOZ stats, cached for 6 hours. Returns null when Koios can't be
 * reached and nothing is cached, so the page simply leaves the stats out.
 *
 * @return array{live_stake: float, delegators: int, saturation: float, margin: float, fixed_cost: float, pledge: float, blocks: int}|null
 */
function lc_adaoz_pool_stats() {
	$cached = get_transient( 'lc_adaoz_pool_stats' );
	if ( false !== $cached ) {
		return $cached ? $cached : null;
	}

	$rows = lc_koios( 'pool_info', array( '_pool_bech32_ids' => array( lc_adaoz_pool_id() ) ) );
	$pool = $rows && isset( $rows[0] ) ? $rows[0] : null;
	if ( ! $pool ) {
		// Remember the failure briefly so a Koios outage doesn't slow every page view.
		set_transient( 'lc_adaoz_pool_stats', array(), 10 * MINUTE_IN_SECONDS );
		return null;
	}

	$stats = array(
		'live_stake' => (float) $pool['live_stake'] / 1000000,
		'delegators' => (int) $pool['live_delegators'],
		'saturation' => (float) $pool['live_saturation'],
		'margin'     => (float) $pool['margin'] * 100,
		'fixed_cost' => (float) $pool['fixed_cost'] / 1000000,
		'pledge'     => (float) $pool['pledge'] / 1000000,
		'blocks'     => (int) $pool['block_count'],
	);
	set_transient( 'lc_adaoz_pool_stats', $stats, 6 * HOUR_IN_SECONDS );
	return $stats;
}

/**
 * Current mainnet protocol parameters in Mesh SDK's Protocol shape (the same
 * mapping as Mesh's KoiosProvider::fetchProtocolParameters), cached for an
 * hour. The delegate panel builds its transaction with these so fees and
 * the stake-key deposit always match the chain.
 *
 * @return array|null
 */
function lc_protocol_params() {
	$cached = get_transient( 'lc_protocol_params' );
	if ( false !== $cached ) {
		return $cached;
	}
	$tip = lc_koios( 'tip' );
	if ( ! $tip || ! isset( $tip[0]['epoch_no'] ) ) {
		return null;
	}
	$rows = lc_koios( 'epoch_params?_epoch_no=' . (int) $tip[0]['epoch_no'] );
	$p    = $rows && isset( $rows[0] ) ? $rows[0] : null;
	if ( ! $p ) {
		return null;
	}
	$params = array(
		'epoch'                      => (int) $p['epoch_no'],
		'coinsPerUtxoSize'           => (float) $p['coins_per_utxo_size'],
		'priceMem'                   => (float) $p['price_mem'],
		'priceStep'                  => (float) $p['price_step'],
		'minFeeA'                    => (float) $p['min_fee_a'],
		'minFeeB'                    => (float) $p['min_fee_b'],
		'keyDeposit'                 => (float) $p['key_deposit'],
		'maxTxSize'                  => (int) $p['max_tx_size'],
		'maxValSize'                 => (int) $p['max_val_size'],
		'poolDeposit'                => (float) $p['pool_deposit'],
		'maxCollateralInputs'        => (int) $p['max_collateral_inputs'],
		'decentralisation'           => (float) $p['decentralisation'],
		'maxBlockSize'               => (int) $p['max_block_size'],
		'collateralPercent'          => (int) $p['collateral_percent'],
		'maxBlockHeaderSize'         => (int) $p['max_bh_size'],
		'minPoolCost'                => (string) $p['min_pool_cost'],
		'maxTxExMem'                 => (string) $p['max_tx_ex_mem'],
		'maxTxExSteps'               => (string) $p['max_tx_ex_steps'],
		'maxBlockExMem'              => (string) $p['max_block_ex_mem'],
		'maxBlockExSteps'            => (string) $p['max_block_ex_steps'],
		'minFeeRefScriptCostPerByte' => (float) $p['min_fee_ref_script_cost_per_byte'],
	);
	set_transient( 'lc_protocol_params', $params, HOUR_IN_SECONDS );
	return $params;
}

/**
 * GET /wp-json/learncardano/v1/delegation?stake=stake1...
 * A wallet's registration and current pool, plus protocol parameters.
 */
add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'learncardano/v1',
			'/delegation',
			array(
				'methods'             => 'GET',
				'callback'            => 'lc_rest_delegation',
				'permission_callback' => '__return_true',
				'args'                => array(
					'stake' => array(
						'required'          => true,
						'validate_callback' => function ( $value ) {
							// Mainnet stake address: stake1 + 53 bech32 characters.
							return (bool) preg_match( '/^stake1[qpzry9x8gf2tvdw0s3jn54khce6mua7l]{53}$/', (string) $value );
						},
					),
				),
			)
		);
	}
);

function lc_rest_delegation( WP_REST_Request $request ) {
	$stake  = (string) $request['stake'];
	$params = lc_protocol_params();
	if ( ! $params ) {
		return new WP_Error( 'chain_unavailable', 'Could not reach the Cardano network. Try again in a minute.', array( 'status' => 503 ) );
	}

	// Short cache (30s): someone may check again right after delegating; the
	// panel also remembers delegations it just submitted (sessionStorage).
	$key     = 'lc_account_' . md5( $stake );
	$account = get_transient( $key );
	if ( false === $account ) {
		$rows = lc_koios( 'account_info', array( '_stake_addresses' => array( $stake ) ) );
		if ( null === $rows ) {
			return new WP_Error( 'chain_unavailable', 'Could not reach the Cardano network. Try again in a minute.', array( 'status' => 503 ) );
		}
		// Koios returns nothing (or status "not registered") for a stake key
		// that has never been registered on-chain.
		$acc     = isset( $rows[0] ) ? $rows[0] : array();
		$pool    = ! empty( $acc['delegated_pool'] ) ? $acc['delegated_pool'] : null;
		$account = array(
			'registered'           => isset( $acc['status'] ) && 'registered' === $acc['status'],
			'delegated_pool'       => $pool,
			'is_delegated_to_pool' => lc_adaoz_pool_id() === $pool,
			'total_balance_ada'    => isset( $acc['total_balance'] ) ? (int) round( (float) $acc['total_balance'] / 1000000 ) : null,
		);
		set_transient( $key, $account, 30 );
	}

	$response = rest_ensure_response(
		array(
			'account' => $account,
			'params'  => $params,
		)
	);
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

/**
 * Send Retry-After with the delegation endpoint's 503 (Koios unreachable).
 * 503 rather than 502: Cloudflare replaces 502 responses with its own page.
 */
add_filter(
	'rest_post_dispatch',
	function ( $response, $server, $request ) {
		if ( '/learncardano/v1/delegation' === $request->get_route() && 503 === $response->get_status() ) {
			$response->header( 'Retry-After', '60' );
			$response->header( 'Cache-Control', 'no-store' );
		}
		return $response;
	},
	10,
	3
);

/**
 * URL of the delegation endpoint. Inside the Customizer's theme preview it
 * carries the preview parameters (and a REST nonce), so a branch previewed
 * there talks to its own copy of the endpoint.
 */
function lc_delegation_api_url() {
	$url = rest_url( 'learncardano/v1/delegation' );
	global $wp_customize;
	if ( is_customize_preview() && $wp_customize ) {
		$url = add_query_arg(
			array(
				'customize_changeset_uuid'    => $wp_customize->changeset_uuid(),
				'customize_theme'             => $wp_customize->get_stylesheet(),
				'customize_messenger_channel' => $wp_customize->get_messenger_channel(),
				'_wpnonce'                    => wp_create_nonce( 'wp_rest' ),
			),
			$url
		);
	}
	return $url;
}

/**
 * URL of the Mesh SDK bundle, versioned by the content hash that
 * tools/mesh/build.mjs writes next to it. Deploys reset file mtimes, so an
 * mtime version would make returning visitors re-download ~1 MB each deploy.
 */
function lc_mesh_bundle_url() {
	$version_file = get_stylesheet_directory() . '/assets/vendor/mesh/mesh.version';
	$version      = file_exists( $version_file ) ? trim( (string) file_get_contents( $version_file ) ) : '';
	if ( ! preg_match( '/^[a-f0-9]{8,64}$/', $version ) ) {
		return lc_theme_asset_url( 'assets/vendor/mesh/mesh.js' );
	}
	return add_query_arg( 'ver', $version, get_stylesheet_directory_uri() . '/assets/vendor/mesh/mesh.js' );
}

/**
 * Compact ADA amount: 71458269 → "71.46M ₳", 60000 → "60,000 ₳".
 */
function lc_format_ada( $ada ) {
	if ( $ada >= 1000000 ) {
		return number_format_i18n( $ada / 1000000, 2 ) . 'M ₳';
	}
	return number_format_i18n( $ada ) . ' ₳';
}
