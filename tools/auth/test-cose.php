<?php
/**
 * <!--
 *
 * ███╗   ███╗███████╗███████╗██╗  ██╗    ██╗    ██╗██╗████████╗██╗  ██╗    ██╗   ██╗███████╗
 * ████╗ ████║██╔════╝██╔════╝██║  ██║    ██║    ██║██║╚══██╔══╝██║  ██║    ██║   ██║██╔════╝
 * ██╔████╔██║█████╗  ███████╗███████║    ██║ █╗ ██║██║   ██║   ███████║    ██║   ██║███████╗
 * ██║╚██╔╝██║██╔══╝  ╚════██║██╔══██║    ██║███╗██║██║   ██║   ██╔══██║    ██║   ██║╚════██║
 * ██║ ╚═╝ ██║███████╗███████║██║  ██║    ╚███╔███╔╝██║   ██║   ██║  ██║    ╚██████╔╝███████║
 * ╚═╝     ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝     ╚══╝╚══╝ ╚═╝   ╚═╝   ╚═╝  ╚═╝     ╚═════╝ ╚══════╝
 *
 * Built by Mesh With Us
 * https://meshwithus.com.au
 *
 * -->
 *
 * Tests for includes/auth/cardano-crypto.php. Plain PHP, no WordPress:
 *
 *   php test-cose.php <dir>      <dir> holds cardano-crypto.php, or includes/auth/cardano-crypto.php
 *
 * vectors.json is read from this script's directory. Exits non-zero on failure.
 *
 * @package CardanoPress Bootstrap
 */

$dir  = rtrim( $argv[1] ?? dirname( __DIR__, 2 ), '/\\' );
$file = null;
foreach ( array( $dir, "$dir/cardano-crypto.php", "$dir/includes/auth/cardano-crypto.php" ) as $cand ) {
	if ( is_file( $cand ) ) {
		$file = $cand;
		break;
	}
}
if ( ! $file ) {
	fwrite( STDERR, "cardano-crypto.php not found under $dir\n" );
	exit( 2 );
}
require $file;

$pass = 0;
$fail = 0;
function check( string $name, bool $ok, string $detail = '' ): void {
	global $pass, $fail;
	$ok ? $pass++ : $fail++;
	echo ( $ok ? 'PASS' : 'FAIL' ) . '  ' . $name . ( $ok || '' === $detail ? '' : "  ($detail)" ) . "\n";
}

/* ---- vectors.json (from vectors.mjs) ----------------------------------- */
$vectors = json_decode( file_get_contents( __DIR__ . '/vectors.json' ), true );
foreach ( $vectors as $v ) {
	$raw = hex2bin( $v['reward_hex'] );
	$res = lc_cose_verify_cip30( $v['sig_hex'], $v['key_hex'], $v['message'], $raw );
	if ( $v['expect'] ) {
		check( 'vector accept: ' . $v['name'], true === $res, (string) $res );
		check( 'vector reward parses + bech32 matches message: ' . $v['name'], lc_cardano_reward_bytes( $v['reward_hex'] ) === $raw
			&& false !== strpos( $v['message'], 'Stake address: ' . lc_cardano_stake_bech32( $raw ) . "\n" ) );
	} else {
		check( 'vector reject: ' . $v['name'], true !== $res, 'ACCEPTED' );
		echo '        reason: ' . ( is_string( $res ) ? $res : 'n/a' ) . "\n";
	}
}

/* ---- helpers: tiny CBOR encoder + sodium round trips -------------------- */
function c_head( int $major, int $n ): string {
	if ( $n < 24 ) {
		return chr( ( $major << 5 ) | $n );
	}
	return $n < 256 ? chr( ( $major << 5 ) | 24 ) . chr( $n ) : chr( ( $major << 5 ) | 25 ) . pack( 'n', $n );
}
function c_text( string $s ): string {
	return c_head( 3, strlen( $s ) ) . $s;
}
function c_bytes( string $s ): string {
	return lc_cbor_bstr( $s );
}
function make_cose( string $sk, string $reward, string $message, bool $hashed = false ): array {
	$pk      = sodium_crypto_sign_publickey_from_secretkey( $sk );
	$prot    = "\xa2\x01\x27" . c_text( 'address' ) . c_bytes( $reward );
	$payload = $hashed ? sodium_crypto_generichash( $message, '', 28 ) : $message;
	$sig     = sodium_crypto_sign_detached( "\x84\x6aSignature1" . c_bytes( $prot ) . "\x40" . c_bytes( $payload ), $sk );
	$unprot  = "\xa1" . c_text( 'hashed' ) . ( $hashed ? "\xf5" : "\xf4" );
	$cose    = "\x84" . c_bytes( $prot ) . $unprot . c_bytes( $payload ) . c_bytes( $sig );
	$key     = "\xa4\x01\x01\x03\x27\x20\x06\x21" . c_bytes( $pk );
	return array( bin2hex( $cose ), bin2hex( $key ), $pk );
}

for ( $i = 0; $i < 20; $i++ ) {
	$net     = $i % 2;
	$sk      = sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() );
	$pk      = sodium_crypto_sign_publickey_from_secretkey( $sk );
	$reward  = chr( 0xE0 | $net ) . sodium_crypto_generichash( $pk, '', 28 );
	$message = 'round trip ' . bin2hex( random_bytes( 16 ) );
	$hashed  = ( $i % 4 ) >= 2;
	list( $sig, $key ) = make_cose( $sk, $reward, $message, $hashed );
	check( "round trip #$i (net $net, hashed " . var_export( $hashed, true ) . ')', true === lc_cose_verify_cip30( $sig, $key, $message, $reward ) );
	check( "round trip #$i wrong message rejected", true !== lc_cose_verify_cip30( $sig, $key, $message . '!', $reward ) );
	$other = chr( 0xE0 | $net ) . random_bytes( 28 );
	check( "round trip #$i other account rejected", true !== lc_cose_verify_cip30( $sig, $key, $message, $other ) );
}

// Attack replay in PHP: attacker key, forged base address carrying the victim's stake hash.
$victim_hash = random_bytes( 28 );
$victim      = "\xE1" . $victim_hash;
$ask         = sodium_crypto_sign_secretkey( sodium_crypto_sign_keypair() );
$apk         = sodium_crypto_sign_publickey_from_secretkey( $ask );
list( $sig, $key ) = make_cose( $ask, "\x01" . sodium_crypto_generichash( $apk, '', 28 ) . $victim_hash, 'login' );
check( 'attack: forged base address rejected', true !== lc_cose_verify_cip30( $sig, $key, 'login', $victim ) );
list( $sig, $key ) = make_cose( $ask, $victim, 'login' );
$res = lc_cose_verify_cip30( $sig, $key, 'login', $victim );
check( 'attack: victim address, attacker key rejected by credential check', 'key does not match stake credential' === $res, (string) $res );

/* ---- reward address parsing -------------------------------------------- */
$cred = random_bytes( 28 );
foreach ( array( 0xE1 => 'stake1', 0xE0 => 'stake_test1' ) as $hdr => $prefix ) {
	$raw = chr( $hdr ) . $cred;
	$b32 = lc_cardano_stake_bech32( $raw );
	check( "stake bech32 prefix $prefix", 0 === strpos( $b32, $prefix ) );
	check( "md5 of encoded matches ($prefix)", md5( lc_cardano_stake_bech32( $raw ) ) === md5( $b32 ) );
	check( "bech32 round trip ($prefix)", lc_cardano_reward_bytes( $b32 ) === $raw );
	check( "hex accepted ($prefix)", lc_cardano_reward_bytes( bin2hex( $raw ) ) === $raw );
	check( "uppercase bech32 rejected ($prefix)", null === lc_cardano_reward_bytes( strtoupper( $b32 ) ) );
	$bad = $b32;
	$bad[ strlen( $bad ) - 1 ] = 'q' === $bad[ strlen( $bad ) - 1 ] ? 'p' : 'q';
	check( "bad checksum rejected ($prefix)", null === lc_cardano_reward_bytes( $bad ) );
}
for ( $i = 0; $i < 200; $i++ ) {
	$raw = chr( 0xE0 | ( $i & 1 ) ) . random_bytes( 28 );
	if ( lc_cardano_reward_bytes( lc_cardano_stake_bech32( $raw ) ) !== $raw ) {
		check( 'bulk bech32 round trip', false, bin2hex( $raw ) );
		break;
	}
	if ( 199 === $i ) {
		check( 'bulk bech32 round trip (200 random)', true );
	}
}
// Mismatched prefix/header, script creds, wrong size, other address types.
check( 'stake_test prefix with 0xE1 header rejected', null === lc_cardano_reward_bytes( lc_bech32_encode( 'stake_test', "\xE1" . $cred ) ) );
check( 'stake prefix with 0xE0 header rejected', null === lc_cardano_reward_bytes( lc_bech32_encode( 'stake', "\xE0" . $cred ) ) );
check( 'script credential 0xF1 rejected (hex)', null === lc_cardano_reward_bytes( bin2hex( "\xF1" . $cred ) ) );
check( 'script credential 0xF0 rejected (bech32)', null === lc_cardano_reward_bytes( lc_bech32_encode( 'stake_test', "\xF0" . $cred ) ) );
check( 'base address header 0x01 rejected', null === lc_cardano_reward_bytes( bin2hex( "\x01" . $cred ) ) );
check( 'wrong hrp rejected', null === lc_cardano_reward_bytes( lc_bech32_encode( 'addr', "\xE1" . $cred ) ) );
check( '28-byte payload rejected', null === lc_cardano_reward_bytes( lc_bech32_encode( 'stake', "\xE1" . substr( $cred, 1 ) ) ) );
check( 'garbage rejected', null === lc_cardano_reward_bytes( 'not an address' ) );
// BIP-173 published vectors.
check( 'BIP-173 valid "a12uel5l"', array( 'hrp' => 'a', 'bytes' => '' ) === lc_bech32_decode( 'a12uel5l' ) );
check( 'BIP-173 valid long string', null !== lc_bech32_decode( 'an83characterlonghumanreadablepartthatcontainsthenumber1andtheexcludedcharactersbio1tt5tgs' ) );
check( 'BIP-173 valid split + altered checksum rejected', null !== lc_bech32_decode( 'split1checkupstagehandshakeupstreamerranterredcaperred2y9e3w' ) && null === lc_bech32_decode( 'split1checkupstagehandshakeupstreamerranterredcaperred2y9e3q' ) );
check( 'BIP-173 invalid mixed case', null === lc_bech32_decode( 'A12UEL5L' ) && null === lc_bech32_decode( 'A12uEL5L' ) );
check( 'encode/decode empty + long data', 'a12uel5l' === lc_bech32_encode( 'a', '' ) && ( $d = random_bytes( 120 ) ) && lc_bech32_decode( lc_bech32_encode( 'x', $d ) )['bytes'] === $d );

/* ---- CBOR decoder strictness ------------------------------------------- */
function rejects( string $bin ): bool {
	try {
		lc_cbor_decode( $bin );
		return false;
	} catch ( UnexpectedValueException $e ) {
		return true;
	}
}
check( 'cbor: float rejected', rejects( "\xfb\x3f\xf0\x00\x00\x00\x00\x00\x00" ) && rejects( "\xf9\x3c\x00" ) );
check( 'cbor: indefinite bstr/array/map rejected', rejects( "\x5f\x41a\xff" ) && rejects( "\x9f\x01\xff" ) && rejects( "\xbf\xff" ) );
check( 'cbor: uint > PHP_INT_MAX rejected', rejects( "\x1b\xff\xff\xff\xff\xff\xff\xff\xff" ) && rejects( "\x1b\x80\x00\x00\x00\x00\x00\x00\x00" ) );
check( 'cbor: PHP_INT_MAX accepted', 9223372036854775807 === lc_cbor_decode( "\x1b\x7f\xff\xff\xff\xff\xff\xff\xff" )['v'] );
check( 'cbor: trailing bytes rejected', rejects( "\x01\x02" ) );
check( 'cbor: duplicate map keys rejected', rejects( "\xa2\x01\x01\x01\x02" ) && rejects( "\xa2\x61a\x01\x61a\x02" ) );
check( 'cbor: int 1 and text "1" are distinct keys', ! rejects( "\xa2\x01\x01\x61" . '1' . "\x02" ) );
check( 'cbor: depth 4 ok, depth 5 rejected', ! rejects( "\x81\x81\x81\x81\x01" ) && rejects( "\x81\x81\x81\x81\x81\x01" ) );
check( 'cbor: 16 items ok, 17 rejected', ! rejects( "\x90" . str_repeat( "\x01", 16 ) ) && rejects( "\x91" . str_repeat( "\x01", 17 ) ) );
check( 'cbor: length beyond buffer rejected', rejects( "\x45abc" ) && rejects( "\x5a\xff\xff\xff\xff" ) );
check( 'cbor: oversize (4097 bytes) rejected', rejects( "\x59\x0f\xfe" . str_repeat( 'a', 4094 ) ) );
check( 'cbor: 4096 bytes accepted', ! rejects( "\x59\x0f\xfd" . str_repeat( 'a', 4093 ) ) );
check( 'cbor: truncated / empty rejected', rejects( '' ) && rejects( "\x18" ) && rejects( "\x82\x01" ) );
check( 'cbor: simple false/true/null/undefined', array( false, true, null, 'undefined' ) === array_map( fn( $b ) => lc_cbor_decode( $b )['v'], array( "\xf4", "\xf5", "\xf6", "\xf7" ) ) );
check( 'cbor: other simple values rejected', rejects( "\xf0" ) && rejects( "\xf8\x20" ) && rejects( "\xff" ) );
check( 'cbor: nint', -8 === lc_cbor_decode( "\x27" )['v'] && -1 - 255 === lc_cbor_decode( "\x38\xff" )['v'] );
check( 'cbor: invalid utf8 text rejected', rejects( "\x62\xc3\x28" ) );
check( 'cbor: map_get kind-aware', null !== lc_cbor_map_get( lc_cbor_decode( "\xa1\x01\x02" ), 'uint', 1 ) && null === lc_cbor_map_get( lc_cbor_decode( "\xa1\x01\x02" ), 'text', '1' ) );
check( 'cbor: bstr header sizes', "\x40" === lc_cbor_bstr( '' ) && "\x58\x18" === substr( lc_cbor_bstr( str_repeat( 'a', 24 ) ), 0, 2 ) && "\x59\x01\x00" === substr( lc_cbor_bstr( str_repeat( 'a', 256 ) ), 0, 3 ) );

/* ---- verifier input bounds / never throws ------------------------------- */
$ok_sig = $vectors[0]['sig_hex'];
$ok_key = $vectors[0]['key_hex'];
$ok_msg = $vectors[0]['message'];
$ok_rwd = hex2bin( $vectors[0]['reward_hex'] );
check( 'verifier: baseline vector valid', true === lc_cose_verify_cip30( $ok_sig, $ok_key, $ok_msg, $ok_rwd ) );
check( 'verifier: empty/odd hex rejected', true !== lc_cose_verify_cip30( '', $ok_key, $ok_msg, $ok_rwd ) && true !== lc_cose_verify_cip30( $ok_sig . 'a', $ok_key, $ok_msg, $ok_rwd ) );
check( 'verifier: oversize key hex rejected', true !== lc_cose_verify_cip30( $ok_sig, str_repeat( 'aa', 4200 ), $ok_msg, $ok_rwd ) );
check( 'verifier: short reward bytes rejected', true !== lc_cose_verify_cip30( $ok_sig, $ok_key, $ok_msg, substr( $ok_rwd, 1 ) ) );
check( 'verifier: random garbage never throws', (function () use ( $ok_key, $ok_msg, $ok_rwd ) {
	for ( $i = 0; $i < 300; $i++ ) {
		if ( true === lc_cose_verify_cip30( bin2hex( random_bytes( random_int( 1, 200 ) ) ), $ok_key, $ok_msg, $ok_rwd ) ) {
			return false;
		}
	}
	return true;
})() );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
