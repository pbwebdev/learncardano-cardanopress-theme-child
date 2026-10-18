// <!--
//
// ███╗   ███╗███████╗███████╗██╗  ██╗    ██╗    ██╗██╗████████╗██╗  ██╗    ██╗   ██╗███████╗
// ████╗ ████║██╔════╝██╔════╝██║  ██║    ██║    ██║██║╚══██╔══╝██║  ██║    ██║   ██║██╔════╝
// ██╔████╔██║█████╗  ███████╗███████║    ██║ █╗ ██║██║   ██║   ███████║    ██║   ██║███████╗
// ██║╚██╔╝██║██╔══╝  ╚════██║██╔══██║    ██║███╗██║██║   ██║   ██╔══██║    ██║   ██║╚════██║
// ██║ ╚═╝ ██║███████╗███████║██║  ██║    ╚███╔███╔╝██║   ██║   ██║  ██║    ╚██████╔╝███████║
// ╚═╝     ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝     ╚══╝╚══╝ ╚═╝   ╚═╝   ╚═╝  ╚═╝     ╚═════╝ ╚══════╝
//
// Built by Mesh With Us
// https://meshwithus.com.au
//
// -->
//
// Generates tools/auth/vectors.json: CIP-30 signData test vectors for
// includes/auth/cardano-crypto.php. Deterministic (fixed seeds) except the
// optional MeshWallet vector, which is deterministic too (Ed25519 is).
//
//   node tools/auth/vectors.mjs
//
// Dependencies are read from the Mesh bundle's node_modules (tools/mesh).
import { createRequire } from 'node:module';
import { createPrivateKey, createPublicKey, sign as edSign } from 'node:crypto';
import { writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const NM = process.env.LC_NODE_MODULES
  || 'D:/Claude/learncardano.io/learncardano-cardanopress-theme-child/tools/mesh/node_modules';
const req = createRequire(join(NM, 'x.js'));
const MS = req('@emurgo/cardano-message-signing-nodejs');
const { blake2b } = req('@noble/hashes/blake2b');

const hex = (u8) => Buffer.from(u8).toString('hex');
const b224 = (u8) => Buffer.from(blake2b(u8, { dkLen: 28 }));
const cat = (...p) => Buffer.concat(p.map((x) => Buffer.from(x)));

// ---------------------------------------------------------------- bech32 --
// Independent JS implementation (the PHP one is checked against these).
const CS = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
const polymod = (v) => {
  const G = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
  let c = 1;
  for (const x of v) {
    const t = c >>> 25;
    c = (((c & 0x1ffffff) << 5) ^ x) >>> 0;
    for (let i = 0; i < 5; i++) if ((t >>> i) & 1) c = (c ^ G[i]) >>> 0;
  }
  return c;
};
function bech32(hrp, bytes) {
  const d = [];
  let acc = 0, bits = 0;
  for (const b of bytes) {
    acc = (acc << 8) | b; bits += 8;
    while (bits >= 5) { bits -= 5; d.push((acc >> bits) & 31); }
  }
  if (bits) d.push((acc << (5 - bits)) & 31);
  const ex = [...[...hrp].map((c) => c.charCodeAt(0) >> 5), 0, ...[...hrp].map((c) => c.charCodeAt(0) & 31)];
  const m = polymod([...ex, ...d, 0, 0, 0, 0, 0, 0]) ^ 1;
  for (let i = 0; i < 6; i++) d.push((m >> (5 * (5 - i))) & 31);
  return hrp + '1' + d.map((x) => CS[x]).join('');
}

// ------------------------------------------------------------------ CBOR --
const head = (major, n) => {
  if (n < 24) return Buffer.from([(major << 5) | n]);
  if (n < 256) return Buffer.from([(major << 5) | 24, n]);
  if (n < 65536) return Buffer.from([(major << 5) | 25, n >> 8, n & 255]);
  throw new Error('too big');
};
const bstr = (b) => cat(head(2, b.length), b);
const tstr = (s) => { const b = Buffer.from(s, 'utf8'); return cat(head(3, b.length), b); };
const uint = (n) => head(0, n);
const nint = (n) => head(1, -1 - n);
const arr = (...items) => cat(head(4, items.length), ...items);
const map = (...pairs) => cat(head(5, pairs.length), ...pairs.flat());
const tag = (n, item) => cat(head(6, n), item);
const T = Buffer.from([0xf5]), F = Buffer.from([0xf4]), NUL = Buffer.from([0xf6]);

// ------------------------------------------------------------------ keys --
const PKCS8 = Buffer.from('302e020100300506032b657004220420', 'hex');
function keypair(seedByte) {
  const seed = Buffer.alloc(32, seedByte);
  const priv = createPrivateKey({ key: cat(PKCS8, seed), format: 'der', type: 'pkcs8' });
  const pub = createPublicKey(priv).export({ format: 'der', type: 'spki' }).subarray(-32);
  return { priv, pub: Buffer.from(pub) };
}
const VICTIM = keypair(0x11);   // owns the stake credential being logged in to
const ATTACK = keypair(0x22);   // attacker's own wallet key
const TESTER = keypair(0x33);   // testnet account
const reward = (hdr, kp) => cat([hdr], b224(kp.pub));

// ------------------------------------------------------------- COSE build --
const coseKey = (x, { kty = 1, crv = 6, alg = -8 } = {}) =>
  map([uint(1), uint(kty)], [uint(3), nint(alg)], [nint(-1), uint(crv)], [nint(-2), bstr(x)]);

const sigStructure = (protRaw, payload) =>
  cat([0x84], tstr('Signature1'), bstr(protRaw), bstr(Buffer.alloc(0)), bstr(payload));

/** Build a COSE_Sign1 hex. Options let negative vectors break exactly one thing. */
function cose(o) {
  const protRaw = o.protRaw ?? map([uint(1), nint(o.alg ?? -8)], [tstr('address'), bstr(o.addr)]);
  const payload = o.payload;
  const sig = o.sig ?? edSign(null, sigStructure(protRaw, payload), o.signer.priv);
  const unprot = o.unprot ?? map([tstr('hashed'), o.hashed ? T : F]);
  const payNode = o.nullPayload ? NUL : bstr(payload);
  let out = arr(bstr(protRaw), unprot, payNode, bstr(sig));
  if (o.tag18) out = tag(18, out);
  if (o.indef) out = cat([0x9f], bstr(protRaw), unprot, payNode, bstr(sig), [0xff]);
  if (o.trailing) out = cat(out, [0x00]);
  return hex(out);
}

const challenge = (stake, net, nonce) =>
  `learncardano.io wants you to sign in with your Cardano wallet.\n\nStake address: ${stake}\nNetwork: ${net}\nURI: https://learncardano.io\nPurpose: Sign in\nNonce: ${nonce}\nIssued At: 2026-10-18T00:00:00Z\nExpiration Time: 2026-10-18T00:10:00Z\n\nThis only proves you control this wallet. It is not a transaction and costs nothing.`;

// ---------------------------------------------------------------- vectors --
const vectors = [];
const add = (name, sig_hex, key_hex, message, reward_raw, expect) =>
  vectors.push({ name, sig_hex, key_hex, message, reward_hex: hex(reward_raw), expect });

const R_MAIN = reward(0xe1, VICTIM);
const R_TEST = reward(0xe0, TESTER);
const M_MAIN = challenge(bech32('stake', R_MAIN), 'Mainnet', '00112233445566778899aabbccddeeff');
const M_TEST = challenge(bech32('stake_test', R_TEST), 'Preprod', 'ffeeddccbbaa99887766554433221100');
const msg = (m) => Buffer.from(m, 'utf8');
const KEY_V = hex(coseKey(VICTIM.pub));

// Positives -----------------------------------------------------------------
add('mainnet raw payload', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN) }), KEY_V, M_MAIN, R_MAIN, true);
add('testnet raw payload', cose({ signer: TESTER, addr: R_TEST, payload: msg(M_TEST) }), hex(coseKey(TESTER.pub)), M_TEST, R_TEST, true);
add('hashed:true (Ledger style)', cose({ signer: VICTIM, addr: R_MAIN, payload: b224(msg(M_MAIN)), hashed: true }), KEY_V, M_MAIN, R_MAIN, true);
add('tag-18 wrapped', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN), tag18: true }), KEY_V, M_MAIN, R_MAIN, true);
add('COSE_Key without label 3', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN) }),
  hex(map([uint(1), uint(1)], [nint(-1), uint(6)], [nint(-2), bstr(VICTIM.pub)])), M_MAIN, R_MAIN, true);
add('unprotected map without hashed key', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN), unprot: map() }), KEY_V, M_MAIN, R_MAIN, true);

// Same structure produced by the real COSE library (cross-checks my encoder).
{
  const prot = MS.HeaderMap.new();
  prot.set_algorithm_id(MS.Label.from_algorithm_id(MS.AlgorithmId.EdDSA));
  prot.set_header(MS.Label.new_text('address'), MS.CBORValue.new_bytes(R_MAIN));
  const unprot = MS.HeaderMap.new();
  unprot.set_header(MS.Label.new_text('hashed'), MS.CBORValue.new_special(MS.CBORSpecial.new_bool(false)));
  const headers = MS.Headers.new(MS.ProtectedHeaderMap.new(prot), unprot);
  const builder = MS.COSESign1Builder.new(headers, msg(M_MAIN), false);
  const tbs = builder.make_data_to_sign().to_bytes();
  const sig = edSign(null, Buffer.from(tbs), VICTIM.priv);
  const built = Buffer.from(builder.build(sig).to_bytes());
  const key = MS.COSEKey.new(MS.Label.from_key_type(MS.KeyType.OKP));
  key.set_algorithm_id(MS.Label.from_algorithm_id(MS.AlgorithmId.EdDSA));
  key.set_header(MS.Label.new_int(MS.Int.new_negative(MS.BigNum.from_str('1'))), MS.CBORValue.from_label(MS.Label.from_curve_type(MS.CurveType.Ed25519)));
  key.set_header(MS.Label.new_int(MS.Int.new_negative(MS.BigNum.from_str('2'))), MS.CBORValue.new_bytes(VICTIM.pub));
  add('emurgo cardano-message-signing build', hex(built), hex(key.to_bytes()), M_MAIN, R_MAIN, true);
  // My own encoder produces an equivalent structure (same signed bytes => same sig).
  const mine = cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN) });
  console.log('encoder matches emurgo bytes:', mine === hex(built));
}

// Negatives -----------------------------------------------------------------
// CardanoPress flaw: attacker's own key, BASE address (0x01 || H(attacker) || victim stake hash).
const BASE_FORGED = cat([0x01], b224(ATTACK.pub), R_MAIN.subarray(1));
add('CARDANOPRESS FLAW: attacker key + forged base address', cose({ signer: ATTACK, addr: BASE_FORGED, payload: msg(M_MAIN) }), hex(coseKey(ATTACK.pub)), M_MAIN, R_MAIN, false);
add('variant 2: victim reward addr in header, attacker key', cose({ signer: ATTACK, addr: R_MAIN, payload: msg(M_MAIN) }), hex(coseKey(ATTACK.pub)), M_MAIN, R_MAIN, false);
add('variant 3: attacker signs, COSE_Key claims victim key', cose({ signer: ATTACK, addr: R_MAIN, payload: msg(M_MAIN) }), KEY_V, M_MAIN, R_MAIN, false);
add('payload tampered', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN + ' ') }), KEY_V, M_MAIN, R_MAIN, false);
add('different nonce (replay of other challenge)', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN.replace('00112233', '99999999')) }), KEY_V, M_MAIN, R_MAIN, false);
add('protected alg changed (-7)', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN), alg: -7 }), KEY_V, M_MAIN, R_MAIN, false);
{
  const good = Buffer.from(cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN) }), 'hex');
  good[good.length - 5] ^= 0x01; // inside the 64-byte signature
  add('signature bit flipped', hex(good), KEY_V, M_MAIN, R_MAIN, false);
}
add('COSE_Key x is not the signer', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN) }), hex(coseKey(ATTACK.pub)), M_MAIN, R_MAIN, false);
add('COSE_Key wrong kty (2)', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN) }), hex(coseKey(VICTIM.pub, { kty: 2 })), M_MAIN, R_MAIN, false);
add('COSE_Key wrong crv (7)', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN) }), hex(coseKey(VICTIM.pub, { crv: 7 })), M_MAIN, R_MAIN, false);
add('COSE_Key label 3 not EdDSA', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN) }), hex(coseKey(VICTIM.pub, { alg: -7 })), M_MAIN, R_MAIN, false);
{
  const R_E0 = cat([0xe0], R_MAIN.subarray(1)); // same credential, testnet header
  add('network mismatch (0xE0 signed, 0xE1 challenge)', cose({ signer: VICTIM, addr: R_E0, payload: msg(M_MAIN) }), KEY_V, M_MAIN, R_MAIN, false);
}
add('hashed:true with raw payload', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN), hashed: true }), KEY_V, M_MAIN, R_MAIN, false);
add('hashed:false with hashed payload', cose({ signer: VICTIM, addr: R_MAIN, payload: b224(msg(M_MAIN)), hashed: false }), KEY_V, M_MAIN, R_MAIN, false);
add('null (detached) payload', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN), nullPayload: true }), KEY_V, M_MAIN, R_MAIN, false);
add('trailing bytes after COSE_Sign1', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN), trailing: true }), KEY_V, M_MAIN, R_MAIN, false);
add('indefinite-length array', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN), indef: true }), KEY_V, M_MAIN, R_MAIN, false);
{
  // Duplicate label 1 in the protected map (validly signed over those bytes).
  const protDup = map([uint(1), nint(-8)], [uint(1), nint(-8)], [tstr('address'), bstr(R_MAIN)]);
  add('duplicate map key in protected headers', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN), protRaw: protDup }), KEY_V, M_MAIN, R_MAIN, false);
  const dupUnprot = map([tstr('hashed'), F], [tstr('hashed'), T]);
  add('duplicate hashed key in unprotected', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN), unprot: dupUnprot }), KEY_V, M_MAIN, R_MAIN, false);
  // Integer label 1 vs text "1" must not be confused: alg under text key "1".
  const protText = map([tstr('1'), nint(-8)], [tstr('address'), bstr(R_MAIN)]);
  add('alg under text key "1" not int 1', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN), protRaw: protText }), KEY_V, M_MAIN, R_MAIN, false);
  // Protected headers with no address at all.
  const protNoAddr = map([uint(1), nint(-8)]);
  add('protected headers without address', cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN), protRaw: protNoAddr }), KEY_V, M_MAIN, R_MAIN, false);
}
add('oversize input (8400 hex chars)', 'aa'.repeat(4200), KEY_V, M_MAIN, R_MAIN, false);
add('non-hex input', 'zz' + cose({ signer: VICTIM, addr: R_MAIN, payload: msg(M_MAIN) }).slice(2), KEY_V, M_MAIN, R_MAIN, false);
{
  const R_SCRIPT = cat([0xf1], R_MAIN.subarray(1));
  const M_SCRIPT = challenge(bech32('stake', R_SCRIPT), 'Mainnet', '0123456789abcdef0123456789abcdef');
  add('script-credential reward header 0xF1', cose({ signer: VICTIM, addr: R_SCRIPT, payload: msg(M_SCRIPT) }), KEY_V, M_SCRIPT, R_SCRIPT, false);
}

// Real wallet-library output (MeshWallet, fixed 24-word test mnemonic) -------
let meshNote = 'skipped';
try {
  const mesh = await import(pathToFileURL(join(NM, '@meshsdk/core/dist/index.js')).href);
  const words = ('abandon '.repeat(23) + 'art').split(' ');
  for (const [networkId, label, hrpName] of [[1, 'mainnet', 'Mainnet'], [0, 'testnet', 'Preprod']]) {
    const w = new mesh.MeshWallet({ networkId, key: { type: 'mnemonic', words } });
    await w.init();
    const stake = (await w.getRewardAddresses())[0];
    // Decode bech32 stake address to raw bytes using the JS reference bech32 decoder.
    const dec = [...stake.slice(stake.lastIndexOf('1') + 1, -6)].map((c) => CS.indexOf(c));
    const bytes = []; let acc = 0, bits = 0;
    for (const v of dec) { acc = (acc << 5) | v; bits += 5; while (bits >= 8) { bits -= 8; bytes.push((acc >> bits) & 255); } }
    const raw = Buffer.from(bytes);
    if (bech32(stake.slice(0, stake.lastIndexOf('1')), raw) !== stake) throw new Error('bech32 self-check failed');
    const m = challenge(stake, hrpName, networkId ? 'abcdef0123456789abcdef0123456789' : '0123456789abcdef0123456789abcdef');
    const out = await w.signData(m, stake);
    add(`MeshWallet signData ${label}`, out.signature, out.key, m, raw, true);
  }
  meshNote = 'included (mainnet + testnet)';
} catch (e) {
  meshNote = 'skipped: ' + e.message;
}
console.log('MeshWallet vectors:', meshNote);

writeFileSync(join(HERE, 'vectors.json'), JSON.stringify(vectors, null, 2) + '\n');
console.log(`wrote ${vectors.length} vectors (${vectors.filter((v) => v.expect).length} positive)`);
