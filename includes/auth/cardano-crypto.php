<?php
/**
 * Pure-PHP verifier for Cardano CIP-30 signData signatures (COSE_Sign1 +
 * COSE_Key), plus the small Bech32 and CBOR pieces it needs. No WordPress
 * calls: this file can be loaded and tested with plain `php`. Requires the
 * sodium extension (PHP 7.2+ core).
 *
 * Why this exists: a wallet login is only safe if the signature proves control
 * of the STAKE KEY of the address being claimed. CardanoPress only checked
 * that the signature was valid for the key in the payload, so an attacker
 * could sign with their own key while naming a victim's stake address. Every
 * check below is there to close that gap; see lc_cose_verify_cip30().
 *
 * @package CardanoPress Bootstrap
 */

/* ------------------------------------------------------------------ Bech32 */

/** Bech32 data alphabet (BIP-173). */
const LC_BECH32_CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

/**
 * BIP-173 checksum polymod over an array of 5-bit values.
 *
 * @param int[] $values 5-bit values.
 */
function lc_bech32_polymod( array $values ): int {
	$gen = array( 0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3 );
	$chk = 1;
	foreach ( $values as $v ) {
		$top = $chk >> 25;
		$chk = ( ( $chk & 0x1ffffff ) << 5 ) ^ $v;
		for ( $i = 0; $i < 5; $i++ ) {
			if ( ( $top >> $i ) & 1 ) {
				$chk ^= $gen[ $i ];
			}
		}
	}
	return $chk;
}

/**
 * Expand the human-readable part for checksum purposes.
 *
 * @return int[]
 */
function lc_bech32_hrp_expand( string $hrp ): array {
	$out = array();
	$len = strlen( $hrp );
	for ( $i = 0; $i < $len; $i++ ) {
		$out[] = ord( $hrp[ $i ] ) >> 5;
	}
	$out[] = 0;
	for ( $i = 0; $i < $len; $i++ ) {
		$out[] = ord( $hrp[ $i ] ) & 31;
	}
	return $out;
}

/**
 * Regroup bits (8 <-> 5). Returns null on invalid input or non-zero padding.
 *
 * @param int[] $data Input values.
 * @return int[]|null
 */
function lc_bech32_convert_bits( array $data, int $from, int $to, bool $pad ): ?array {
	$acc    = 0;
	$bits   = 0;
	$maxv   = ( 1 << $to ) - 1;
	$result = array();
	foreach ( $data as $value ) {
		if ( $value < 0 || ( $value >> $from ) !== 0 ) {
			return null;
		}
		$acc   = ( ( $acc << $from ) | $value ) & 0xffffff;
		$bits += $from;
		while ( $bits >= $to ) {
			$bits    -= $to;
			$result[] = ( $acc >> $bits ) & $maxv;
		}
	}
	if ( $pad ) {
		if ( $bits > 0 ) {
			$result[] = ( $acc << ( $to - $bits ) ) & $maxv;
		}
	} elseif ( $bits >= $from || ( ( $acc << ( $to - $bits ) ) & $maxv ) !== 0 ) {
		return null;
	}
	return $result;
}

/** Encode raw bytes as a Bech32 string (no 90-char limit: Cardano needs more). */
function lc_bech32_encode( string $hrp, string $bytes ): string {
	$data = $bytes === '' ? array() : array_values( unpack( 'C*', $bytes ) );
	$data = lc_bech32_convert_bits( $data, 8, 5, true );
	$mod  = lc_bech32_polymod( array_merge( lc_bech32_hrp_expand( $hrp ), $data, array( 0, 0, 0, 0, 0, 0 ) ) ) ^ 1;
	for ( $i = 0; $i < 6; $i++ ) {
		$data[] = ( $mod >> ( 5 * ( 5 - $i ) ) ) & 31;
	}
	$out = $hrp . '1';
	foreach ( $data as $d ) {
		$out .= LC_BECH32_CHARSET[ $d ];
	}
	return $out;
}

/**
 * Decode a Bech32 string. Lowercase only (mixed/upper case is rejected so one
 * address has one spelling), checksum verified.
 *
 * @return array{hrp:string,bytes:string}|null
 */
function lc_bech32_decode( string $str ): ?array {
	$len = strlen( $str );
	if ( $len < 8 || $len > 256 || $str !== strtolower( $str ) ) {
		return null;
	}
	$pos = strrpos( $str, '1' );
	if ( false === $pos || $pos < 1 || $len - $pos - 1 < 6 ) {
		return null;
	}
	$hrp = substr( $str, 0, $pos );
	for ( $i = 0; $i < $pos; $i++ ) {
		$c = ord( $hrp[ $i ] );
		if ( $c < 33 || $c > 126 ) {
			return null;
		}
	}
	$data = array();
	for ( $i = $pos + 1; $i < $len; $i++ ) {
		$p = strpos( LC_BECH32_CHARSET, $str[ $i ] );
		if ( false === $p ) {
			return null;
		}
		$data[] = $p;
	}
	if ( 1 !== lc_bech32_polymod( array_merge( lc_bech32_hrp_expand( $hrp ), $data ) ) ) {
		return null;
	}
	$bytes = lc_bech32_convert_bits( array_slice( $data, 0, -6 ), 5, 8, false );
	if ( null === $bytes ) {
		return null;
	}
	return array(
		'hrp'   => $hrp,
		'bytes' => $bytes ? pack( 'C*', ...$bytes ) : '',
	);
}

/* ------------------------------------------------------------ Cardano addrs */

/**
 * Normalise a reward (stake) address to its 29 raw bytes. Accepts 58 hex chars
 * or bech32 stake1... / stake_test1.... Only key-hash credentials are valid:
 * header 0xE1 (mainnet) or 0xE0 (testnet). Script credentials (0xF0/0xF1) and
 * anything else return null, because a script has no key that can sign.
 */
function lc_cardano_reward_bytes( string $hex_or_bech32 ): ?string {
	$in = trim( $hex_or_bech32 );
	if ( 58 === strlen( $in ) && ctype_xdigit( $in ) ) {
		$raw = hex2bin( $in );
		$hrp = null;
	} else {
		$dec = lc_bech32_decode( $in );
		if ( null === $dec || ! in_array( $dec['hrp'], array( 'stake', 'stake_test' ), true ) ) {
			return null;
		}
		$raw = $dec['bytes'];
		$hrp = $dec['hrp'];
	}
	if ( 29 !== strlen( $raw ) ) {
		return null;
	}
	$header = ord( $raw[0] );
	if ( 0xE1 !== $header && 0xE0 !== $header ) {
		return null;
	}
	// The bech32 prefix must agree with the network nibble in the header.
	if ( null !== $hrp && ( ( 0xE1 === $header ) !== ( 'stake' === $hrp ) ) ) {
		return null;
	}
	return $raw;
}

/** 29 raw reward-address bytes to stake1... (network nibble 1) or stake_test1... (0). */
function lc_cardano_stake_bech32( string $raw29 ): string {
	return lc_bech32_encode( ( ord( $raw29[0] ) & 0x0F ) === 1 ? 'stake' : 'stake_test', $raw29 );
}

/* -------------------------------------------------------------------- CBOR */

/** Hard limits: this decoder only ever sees small, hostile-by-default input. */
const LC_CBOR_MAX_BYTES = 4096;
const LC_CBOR_MAX_DEPTH = 4;
const LC_CBOR_MAX_ITEMS = 16;

/**
 * Strictly decode one CBOR item. Returns a node:
 * ['k'=>'uint'|'nint'|'bytes'|'text'|'array'|'map'|'tag'|'simple','v'=>...].
 * array: v = list of nodes. map: v = list of [keyNode, valNode] pairs (kept as
 * pairs so integer 1 and text "1" can never be confused). tag: v = inner node,
 * plus 'tag' => number. simple: v = false|true|null|'undefined'.
 *
 * Rejects floats, indefinite lengths, ints beyond PHP_INT_MAX, trailing bytes,
 * duplicate map keys, deep nesting and oversize input. Throws
 * UnexpectedValueException on any of them.
 */
function lc_cbor_decode( string $bin ): array {
	if ( strlen( $bin ) > LC_CBOR_MAX_BYTES ) {
		throw new UnexpectedValueException( 'cbor too large' );
	}
	$pos  = 0;
	$node = lc_cbor_read( $bin, $pos, 0 );
	if ( strlen( $bin ) !== $pos ) {
		throw new UnexpectedValueException( 'cbor trailing bytes' );
	}
	return $node;
}

/** Read one item at $pos (advanced by reference). Internal to lc_cbor_decode(). */
function lc_cbor_read( string $b, int &$pos, int $depth ): array {
	$len = strlen( $b );
	if ( $pos >= $len ) {
		throw new UnexpectedValueException( 'cbor truncated' );
	}
	$ib    = ord( $b[ $pos++ ] );
	$major = $ib >> 5;
	$info  = $ib & 31;

	if ( 7 === $major ) { // Simple values only; floats (25-27) and break (31) are rejected.
		if ( 20 === $info || 21 === $info ) {
			return array( 'k' => 'simple', 'v' => 21 === $info );
		}
		if ( 22 === $info ) {
			return array( 'k' => 'simple', 'v' => null );
		}
		if ( 23 === $info ) {
			return array( 'k' => 'simple', 'v' => 'undefined' );
		}
		throw new UnexpectedValueException( 'cbor unsupported simple/float' );
	}

	// Argument: inline (<24) or 1/2/4/8 following bytes. 28-31 incl. indefinite are rejected.
	if ( $info < 24 ) {
		$arg = $info;
	} elseif ( $info <= 27 ) {
		$n = array( 24 => 1, 25 => 2, 26 => 4, 27 => 8 )[ $info ];
		if ( $pos + $n > $len ) {
			throw new UnexpectedValueException( 'cbor truncated' );
		}
		$chunk = substr( $b, $pos, $n );
		$pos  += $n;
		if ( 1 === $n ) {
			$arg = ord( $chunk );
		} elseif ( 2 === $n ) {
			$arg = unpack( 'n', $chunk )[1];
		} elseif ( 4 === $n ) {
			$arg = unpack( 'N', $chunk )[1];
		} else {
			$arg = unpack( 'J', $chunk )[1];
			if ( $arg < 0 ) { // Above PHP_INT_MAX.
				throw new UnexpectedValueException( 'cbor int too large' );
			}
		}
	} else {
		throw new UnexpectedValueException( 'cbor indefinite/reserved' );
	}

	switch ( $major ) {
		case 0:
			return array( 'k' => 'uint', 'v' => $arg );
		case 1:
			return array( 'k' => 'nint', 'v' => -1 - $arg );
		case 2:
		case 3:
			if ( $arg > $len - $pos ) {
				throw new UnexpectedValueException( 'cbor length beyond buffer' );
			}
			$s    = substr( $b, $pos, $arg );
			$pos += $arg;
			if ( 3 === $major && ! preg_match( '//u', $s ) ) {
				throw new UnexpectedValueException( 'cbor bad utf8' );
			}
			return array( 'k' => 2 === $major ? 'bytes' : 'text', 'v' => $s );
		default: // 4 array, 5 map, 6 tag.
			if ( $depth >= LC_CBOR_MAX_DEPTH ) {
				throw new UnexpectedValueException( 'cbor too deep' );
			}
			if ( 6 === $major ) {
				return array( 'k' => 'tag', 'tag' => $arg, 'v' => lc_cbor_read( $b, $pos, $depth + 1 ) );
			}
			if ( $arg > LC_CBOR_MAX_ITEMS ) {
				throw new UnexpectedValueException( 'cbor too many items' );
			}
			$items = array();
			if ( 4 === $major ) {
				for ( $i = 0; $i < $arg; $i++ ) {
					$items[] = lc_cbor_read( $b, $pos, $depth + 1 );
				}
				return array( 'k' => 'array', 'v' => $items );
			}
			$seen = array();
			for ( $i = 0; $i < $arg; $i++ ) {
				$key = lc_cbor_read( $b, $pos, $depth + 1 );
				if ( ! in_array( $key['k'], array( 'uint', 'nint', 'bytes', 'text' ), true ) ) {
					throw new UnexpectedValueException( 'cbor unsupported map key' );
				}
				$id = $key['k'] . ':' . $key['v'];
				if ( isset( $seen[ $id ] ) ) {
					throw new UnexpectedValueException( 'cbor duplicate map key' );
				}
				$seen[ $id ] = true;
				$items[]     = array( $key, lc_cbor_read( $b, $pos, $depth + 1 ) );
			}
			return array( 'k' => 'map', 'v' => $items );
	}
}

/**
 * Look up a value node in a decoded map node by key kind ('uint'|'nint'|'text'|
 * 'bytes') and value. Null when absent or when $mapNode is not a map.
 */
function lc_cbor_map_get( array $mapNode, string $kind, $key ): ?array {
	if ( 'map' !== ( $mapNode['k'] ?? null ) ) {
		return null;
	}
	foreach ( $mapNode['v'] as $pair ) {
		if ( $pair[0]['k'] === $kind && $pair[0]['v'] === $key ) {
			return $pair[1];
		}
	}
	return null;
}

/** CBOR byte string (major type 2) with the shortest header. */
function lc_cbor_bstr( string $raw ): string {
	$n = strlen( $raw );
	if ( $n < 24 ) {
		return chr( 0x40 + $n ) . $raw;
	}
	if ( $n < 256 ) {
		return "\x58" . chr( $n ) . $raw;
	}
	if ( $n < 65536 ) {
		return "\x59" . pack( 'n', $n ) . $raw;
	}
	return "\x5a" . pack( 'N', $n ) . $raw;
}

/* ------------------------------------------------------------- CIP-30 verify */

/**
 * Verify a CIP-30 signData result for a login challenge.
 *
 * @param string $sig_hex    COSE_Sign1 as hex (wallet's `signature`).
 * @param string $key_hex    COSE_Key as hex (wallet's `key`).
 * @param string $message    The exact challenge text the user was asked to sign.
 * @param string $reward_raw 29 raw bytes from lc_cardano_reward_bytes(): the account being logged in.
 * @return true|string True when valid, otherwise a short reason (never throws).
 */
function lc_cose_verify_cip30( string $sig_hex, string $key_hex, string $message, string $reward_raw ) {
	try {
		return lc_cose_verify_cip30_inner( $sig_hex, $key_hex, $message, $reward_raw );
	} catch ( Throwable $e ) {
		return 'error: ' . $e->getMessage();
	}
}

/** The checks, in order. May throw; lc_cose_verify_cip30() turns that into a reason string. */
function lc_cose_verify_cip30_inner( string $sig_hex, string $key_hex, string $message, string $reward_raw ) {
	// h. Only a key-hash reward address (0xE0/0xE1) can be proven with a signature.
	if ( 29 !== strlen( $reward_raw ) || ( ord( $reward_raw[0] ) & 0xF0 ) !== 0xE0 || ord( $reward_raw[0] ) > 0xE1 ) {
		return 'bad reward address';
	}

	// a. Bounded hex only, before anything is decoded.
	foreach ( array( $sig_hex, $key_hex ) as $h ) {
		if ( '' === $h || strlen( $h ) > 8192 || strlen( $h ) % 2 || ! ctype_xdigit( $h ) ) {
			return 'bad hex';
		}
	}
	$sig_bin = hex2bin( $sig_hex );
	$key_bin = hex2bin( $key_hex );

	// b. COSE_Sign1 = [protected bstr, unprotected map, payload bstr, signature bstr], optional tag 18.
	$root = lc_cbor_decode( $sig_bin );
	if ( 'tag' === $root['k'] ) {
		if ( 18 !== $root['tag'] ) {
			return 'bad tag';
		}
		$root = $root['v'];
	}
	if ( 'array' !== $root['k'] || 4 !== count( $root['v'] ) ) {
		return 'not COSE_Sign1';
	}
	list( $prot_n, $unprot_n, $payload_n, $sig_n ) = $root['v'];
	if ( 'bytes' !== $prot_n['k'] || 'map' !== $unprot_n['k'] || 'bytes' !== $sig_n['k'] || 64 !== strlen( $sig_n['v'] ) ) {
		return 'bad COSE_Sign1 shape';
	}
	if ( 'bytes' !== $payload_n['k'] ) { // null (detached) payload: we need the signed text itself.
		return 'no payload';
	}
	$protected_raw = $prot_n['v'];
	$payload       = $payload_n['v'];
	$sig           = $sig_n['v'];

	// c. Protected headers: alg = EdDSA, and the address the wallet says it signed with.
	$prot = lc_cbor_decode( $protected_raw );
	$alg  = lc_cbor_map_get( $prot, 'uint', 1 );
	if ( null === $alg || 'nint' !== $alg['k'] || -8 !== $alg['v'] ) {
		return 'bad alg';
	}
	$addr = lc_cbor_map_get( $prot, 'text', 'address' );
	if ( null === $addr || 'bytes' !== $addr['k'] || $addr['v'] !== $reward_raw ) {
		// A base address (0x01...) here is the CardanoPress flaw: it carries the victim's stake hash
		// next to the attacker's payment key. We only accept the reward address itself, byte for byte.
		return 'address mismatch';
	}

	// d. Payload must be exactly the challenge (or its blake2b-224 for Ledger's "hashed" mode).
	$hashed   = false;
	$hashed_n = lc_cbor_map_get( $unprot_n, 'text', 'hashed' );
	if ( null !== $hashed_n ) {
		if ( 'simple' !== $hashed_n['k'] || ! is_bool( $hashed_n['v'] ) ) {
			return 'bad hashed flag';
		}
		$hashed = $hashed_n['v'];
	}
	$expected = $hashed ? sodium_crypto_generichash( $message, '', 28 ) : $message;
	if ( ! hash_equals( $expected, $payload ) ) {
		return 'payload mismatch';
	}

	// e. COSE_Key: OKP / Ed25519, public key in label -2.
	$ck = lc_cbor_decode( $key_bin );
	if ( 'map' !== $ck['k'] ) {
		return 'bad key';
	}
	$kty = lc_cbor_map_get( $ck, 'uint', 1 );
	$crv = lc_cbor_map_get( $ck, 'nint', -1 );
	$x_n = lc_cbor_map_get( $ck, 'nint', -2 );
	// Cardano wallets encode Ed25519 as kty 1 (OKP), crv 6; crv is a positive int so it is 'uint', not 'nint'.
	if ( null === $kty || 'uint' !== $kty['k'] || 1 !== $kty['v'] || null === $crv || 'uint' !== $crv['k'] || 6 !== $crv['v'] ) {
		return 'bad key type';
	}
	if ( null === $x_n || 'bytes' !== $x_n['k'] || 32 !== strlen( $x_n['v'] ) ) {
		return 'bad public key';
	}
	$key_alg = lc_cbor_map_get( $ck, 'uint', 3 );
	if ( null !== $key_alg && ( 'nint' !== $key_alg['k'] || -8 !== $key_alg['v'] ) ) {
		return 'bad key alg';
	}
	$x = $x_n['v'];

	// f. Signature over the COSE Sig_structure ["Signature1", protected-as-received, h'', payload].
	$sig_structure = "\x84\x6aSignature1" . lc_cbor_bstr( $protected_raw ) . "\x40" . lc_cbor_bstr( $payload );
	if ( ! sodium_crypto_sign_verify_detached( $sig, $sig_structure, $x ) ) {
		return 'bad signature';
	}

	// g. THE CRITICAL CHECK. A valid signature only proves the holder of $x signed; it says nothing
	// about whose account that is. Bind it: the key must hash to the stake credential in the
	// challenge's reward address (bytes 1..28). Without this anyone can log in as anyone.
	if ( ! hash_equals( substr( $reward_raw, 1, 28 ), sodium_crypto_generichash( $x, '', 28 ) ) ) {
		return 'key does not match stake credential';
	}

	return true;
}
