/*
███╗   ███╗███████╗███████╗██╗  ██╗    ██╗    ██╗██╗████████╗██╗  ██╗    ██╗   ██╗███████╗
████╗ ████║██╔════╝██╔════╝██║  ██║    ██║    ██║██║╚══██╔══╝██║  ██║    ██║   ██║██╔════╝
██╔████╔██║█████╗  ███████╗███████║    ██║ █╗ ██║██║   ██║   ███████║    ██║   ██║███████╗
██║╚██╔╝██║██╔══╝  ╚════██║██╔══██║    ██║███╗██║██║   ██║   ██╔══██║    ██║   ██║╚════██║
██║ ╚═╝ ██║███████╗███████║██║  ██║    ╚███╔███╔╝██║   ██║   ██║  ██║    ╚██████╔╝███████║
╚═╝     ╚═╝╚══════╝╚══════╝╚═╝  ╚═╝     ╚══╝╚══╝ ╚═╝   ╚═╝   ╚═╝  ╚═╝     ╚═════╝ ╚══════╝

Built by Mesh With Us
https://meshwithus.com.au
*/
/**
 * End-to-end browser test for the account system, against STAGING only.
 *
 *   node tools/auth/e2e.mjs [https://staging2.learncardano.io]
 *
 * A fake CIP-30 wallet ("LC Test") is injected into the page; it signs in
 * Node with a fresh Ed25519 key, exactly as a wallet does (COSE_Sign1 over
 * the reward address). Sign-in links are read from the staging dev outbox
 * over SSH. Everything the test creates is deleted at the end.
 */
import { createRequire } from 'node:module';
import { createPrivateKey, createPublicKey, sign as edSign, randomBytes } from 'node:crypto';
import { execFileSync } from 'node:child_process';

const BASE = process.argv[2] || 'https://staging2.learncardano.io';
if (!/staging2\./.test(BASE)) { console.error('Refusing to run against anything but staging.'); process.exit(2); }
const WP = '$HOME/www/staging2.learncardano.io/public_html';
const NPX = process.env.LC_PUPPETEER_MODULES || 'C:/Users/Peter-PC/AppData/Local/npm-cache/_npx/0f94ee7615faf582/node_modules';
const NM = process.env.LC_NODE_MODULES || 'D:/Claude/learncardano.io/learncardano-cardanopress-theme-child/tools/mesh/node_modules';
const puppeteer = createRequire(NPX + '/x.js')('puppeteer-core');
const { Launcher } = createRequire(NPX + '/x.js')('chrome-launcher');
const { blake2b } = createRequire(NM + '/x.js')('@noble/hashes/blake2b');

/* ---------- tiny CBOR / COSE (same shapes as tools/auth/vectors.mjs) ---------- */
const cat = (...p) => Buffer.concat(p.map((x) => Buffer.from(x)));
const b224 = (u8) => Buffer.from(blake2b(u8, { dkLen: 28 }));
const head = (m, n) => (n < 24 ? Buffer.from([(m << 5) | n]) : n < 256 ? Buffer.from([(m << 5) | 24, n]) : Buffer.from([(m << 5) | 25, n >> 8, n & 255]));
const bstr = (b) => cat(head(2, b.length), b);
const tstr = (s) => { const b = Buffer.from(s, 'utf8'); return cat(head(3, b.length), b); };
const uint = (n) => head(0, n);
const nint = (n) => head(1, -1 - n);
const arr = (...i) => cat(head(4, i.length), ...i);
const map = (...p) => cat(head(5, p.length), ...p.flat());
const PKCS8 = Buffer.from('302e020100300506032b657004220420', 'hex');
function keypair() {
  const priv = createPrivateKey({ key: cat(PKCS8, randomBytes(32)), format: 'der', type: 'pkcs8' });
  return { priv, pub: Buffer.from(createPublicKey(priv).export({ format: 'der', type: 'spki' }).subarray(-32)) };
}
function coseSign(signer, addrBytes, payload) {
  const prot = map([uint(1), nint(-8)], [tstr('address'), bstr(addrBytes)]);
  const sig = edSign(null, cat([0x84], tstr('Signature1'), bstr(prot), bstr(Buffer.alloc(0)), bstr(payload)), signer.priv);
  return {
    signature: arr(bstr(prot), map([tstr('hashed'), Buffer.from([0xf4])]), bstr(payload), bstr(sig)).toString('hex'),
    key: map([uint(1), uint(1)], [uint(3), nint(-8)], [nint(-1), uint(6)], [nint(-2), bstr(signer.pub)]).toString('hex'),
  };
}
const CS = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
function bech32(hrp, bytes) {
  const pm = (v) => { const G = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3]; let c = 1; for (const x of v) { const t = c >> 25; c = ((c & 0x1ffffff) << 5) ^ x; for (let i = 0; i < 5; i++) if ((t >> i) & 1) c ^= G[i]; } return c; };
  const d = []; let acc = 0, bits = 0;
  for (const b of bytes) { acc = (acc << 8) | b; bits += 8; while (bits >= 5) { bits -= 5; d.push((acc >> bits) & 31); } }
  if (bits) d.push((acc << (5 - bits)) & 31);
  const ex = [...[...hrp].map((c) => c.charCodeAt(0) >> 5), 0, ...[...hrp].map((c) => c.charCodeAt(0) & 31)];
  const m = pm([...ex, ...d, 0, 0, 0, 0, 0, 0]) ^ 1;
  for (let i = 0; i < 6; i++) d.push((m >> (5 * (5 - i))) & 31);
  return hrp + '1' + d.map((x) => CS[x]).join('');
}

/* ---------- wallets ---------- */
const GOOD = keypair();                        // a member's wallet
const VICTIM = keypair();                      // someone else's stake key
const ATTACK = keypair();                      // the attacker's own key
const reward = (kp) => cat([0xe1], b224(kp.pub));
const STAKES = [bech32('stake', reward(GOOD)), bech32('stake', reward(VICTIM))];
let mode = 'good';
function walletRewardHex() { return (mode === 'flaw' ? reward(VICTIM) : reward(GOOD)).toString('hex'); }
function walletSign(_addr, payloadHex) {
  const payload = Buffer.from(payloadHex, 'hex');
  if (mode === 'decline') return { error: { code: 3, info: 'user declined' } };
  if (mode === 'flaw') {
    // The CardanoPress trick: attacker's payment key + the victim's stake credential.
    const base = cat([0x01], b224(ATTACK.pub), reward(VICTIM).subarray(1));
    return coseSign(ATTACK, base, payload);
  }
  return coseSign(GOOD, reward(GOOD), payload);
}

/* ---------- harness ---------- */
let pass = 0, fail = 0;
const t = (name, ok, extra = '') => { ok ? pass++ : fail++; console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${ok || !extra ? '' : '  (' + extra + ')'}`); };
const ssh = (cmd) => execFileSync('ssh', ['learncardano', cmd], { encoding: 'utf8' }).trim();
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
const POST_PATH = process.env.LC_E2E_POST || '/courses/using-the-minswap-dex/';

// Start from clean rate-limit counters (staging only): repeated runs from one IP trip the limits.
ssh(`wp --path=${WP} eval-file ~/lc-tools/r2/auth/reset-limits.php`);
const browser = await puppeteer.launch({ executablePath: Launcher.getInstallations()[0], headless: 'new', args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: 1280, height: 900 });
await page.exposeFunction('lcTestReward', () => walletRewardHex());
await page.exposeFunction('lcTestSign', (a, p) => walletSign(a, p));
await page.evaluateOnNewDocument(() => {
  window.cardano = window.cardano || {};
  window.cardano.lctest = {
    name: 'LC Test', apiVersion: '0.1.0',
    icon: 'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 1 1%22/%3E',
    isEnabled: async () => true,
    enable: async () => ({
      getNetworkId: async () => 1,
      getRewardAddresses: async () => [await window.lcTestReward()],
      getUsedAddresses: async () => [],
      getChangeAddress: async () => '',
      signData: async (addr, payload) => { const r = await window.lcTestSign(addr, payload); if (r.error) throw r.error; return r; },
    }),
  };
});
const requests = [];
page.on('request', (r) => requests.push(r.url()));
const errors = [];
page.on('pageerror', (e) => { if (!/_x_dataStack/.test(e.message)) errors.push(e.message); });

const signedIn = () => page.evaluate(() => !!document.querySelector('header a[href$="/account/"]') && !document.querySelector('[data-lc-auth-open="header"]'));
async function openDialog(selector = '[data-lc-auth-open="header"]') {
  await page.click(selector);
  await page.waitForSelector('dialog.lc-auth[open] [data-lc-auth-panel]', { timeout: 15000 });
}
async function walletClick() {
  await page.waitForSelector('dialog[open] [data-lc-auth-wallet]:not([hidden])', { visible: true, timeout: 10000 });
  await page.click('dialog[open] [data-lc-auth-wallet]');
  await page.waitForSelector('dialog[open] .lc-auth__wallet-btn', { visible: true, timeout: 10000 });
  await page.click('dialog[open] .lc-auth__wallet-btn');
}

try {
  /* 1. Nothing auth-related loads on a normal page until someone interacts. */
  await page.goto(BASE + POST_PATH + '?e2e=' + Date.now(), { waitUntil: 'networkidle2' });
  t('no auth.js / auth.css / /account/ requests on page load', !requests.some((u) => /auth\.(js|css)|\/account\//.test(u)), requests.filter((u) => /auth\.|\/account\//.test(u)).join(' '));
  t('header shows "Sign in"', !!(await page.$('[data-lc-auth-open="header"]')));
  t('comment form shows "Sign in to comment"', !!(await page.$('[data-lc-auth-open="comment"]')));

  /* 2. The CardanoPress trick is refused in the browser too. */
  mode = 'flaw';
  await openDialog();
  t('dialog opens with the sign-in panel', true);
  await walletClick();
  await sleep(2500);
  const flawStatus = await page.$eval('dialog[open] [data-lc-auth-status]', (e) => e.textContent.trim()).catch(() => '');
  t('forged-address signature is refused with a message', flawStatus.length > 0 && !(await signedIn()), flawStatus);
  await page.keyboard.press('Escape');
  await sleep(300);
  t('Escape closes the dialog', !(await page.$('dialog.lc-auth[open]')));

  /* 3. A cancelled signature is reported, nobody signed in. */
  mode = 'decline';
  await openDialog();
  await walletClick();
  await sleep(1500);
  const declineStatus = await page.$eval('dialog[open] [data-lc-auth-status]', (e) => e.textContent.trim()).catch(() => '');
  t('declined signature shows "cancelled"', /cancel/i.test(declineStatus), declineStatus);
  await page.keyboard.press('Escape');

  /* 4. A real wallet signature signs in and creates a wallet-only member. */
  mode = 'good';
  await openDialog();
  await walletClick();
  await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => {});
  t('wallet sign-in: page reloads signed in', await signedIn());
  t('still on the same page after sign-in', page.url().includes(POST_PATH));
  t('comment textarea is shown when signed in', !!(await page.$('textarea#comment')));
  const firstId = ssh(`wp --path=${WP} eval 'echo implode(",", get_users(array("meta_key"=>"lc_wallet","meta_value"=>"${STAKES[0]}","fields"=>"ID")));'`);
  t('exactly one account holds the new wallet', /^\d+$/.test(firstId), firstId);

  /* 5. /account/ lists the wallet; sign-out is a POST. */
  await page.goto(BASE + '/account/', { waitUntil: 'networkidle2' });
  const shown = await page.$$eval('code', (els) => els.map((e) => e.textContent));
  t('/account/ lists the wallet, masked on screen', shown.some((c) => c.startsWith(STAKES[0].slice(0, 7)) && c.includes('…') && c !== STAKES[0]), shown.join(' | '));
  const signoutForm = await page.$('form input[name="lc_action"][value="signout"]');
  t('sign-out is a form POST', !!signoutForm);
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.evaluate(() => document.querySelector('form input[name="lc_action"][value="signout"]').form.submit())]);
  await page.goto(BASE + POST_PATH, { waitUntil: 'networkidle2' });
  t('signed out after the POST', !(await signedIn()));

  /* 6. The same wallet signs back in to the same account. */
  await openDialog();
  await walletClick();
  await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 20000 }).catch(() => {});
  const againId = ssh(`wp --path=${WP} eval 'echo implode(",", get_users(array("meta_key"=>"lc_wallet","meta_value"=>"${STAKES[0]}","fields"=>"ID")));'`);
  t('second wallet sign-in lands on the same account', (await signedIn()) && againId === firstId, `${firstId} vs ${againId}`);
  await page.goto(BASE + '/account/', { waitUntil: 'networkidle2' });
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), page.evaluate(() => document.querySelector('form input[name="lc_action"][value="signout"]').form.submit())]);

  /* 7. Email magic link, end to end. */
  const email = 'e2e' + randomBytes(4).toString('hex') + '@lc-test.invalid';
  await page.goto(BASE + POST_PATH, { waitUntil: 'networkidle2' });
  await openDialog('[data-lc-auth-open="comment"]');
  await sleep(2300); // the panel token must be at least 2 s old
  await page.type('dialog[open] #lc-auth-email', email);
  await page.click('dialog[open] [data-lc-auth-email] button[type="submit"]');
  await sleep(2000);
  const sentStatus = await page.$eval('dialog[open] [data-lc-auth-status]', (e) => e.textContent.trim()).catch(() => '');
  t('email request shows the generic "on its way" message', /on its way|sign-in link/i.test(sentStatus), sentStatus);
  const link = ssh(`wp --path=${WP} eval-file ~/lc-tools/r2/auth/outbox.php email=${email}`);
  t('a sign-in link was mailed', /\/account\/email-link\/\?t=/.test(link), link.slice(0, 60));
  await page.goto(link, { waitUntil: 'networkidle2' });
  t('the token leaves the address bar', !/[?&]t=/.test(page.url()), page.url());
  const cont = await page.$('form button[type="submit"]');
  t('confirm page asks before signing in', !!cont && !(await signedIn()));
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle2' }), cont.click()]);
  t('email link signs in', await signedIn());
  t('lands back on the article', page.url().includes(POST_PATH), page.url());
  await page.goto(link, { waitUntil: 'networkidle2' });
  const reused = await page.content();
  t('a used link does not work again', /expired|already used/i.test(reused));

  t('no JavaScript errors (besides CardanoPress, if still present)', errors.length === 0, errors.join(' | '));
} catch (e) {
  t('test run', false, e.message);
} finally {
  await browser.close();
  const cleanup = ssh(`wp --path=${WP} eval '
    require_once ABSPATH."wp-admin/includes/user.php"; $n=0;
    foreach (array("${STAKES[0]}","${STAKES[1]}") as $s) { foreach (get_users(array("meta_key"=>"lc_wallet","meta_value"=>$s,"fields"=>"ID")) as $id) { wp_delete_user((int)$id); $n++; } }
    foreach (get_users(array("search"=>"*@lc-test.invalid","search_columns"=>array("user_email"),"fields"=>"ID")) as $id) { if (strpos(get_userdata($id)->user_email,"e2e")===0) { wp_delete_user((int)$id); $n++; } }
    echo $n;'`);
  console.log(`\nCleaned up ${cleanup} test accounts.\n${pass} passed, ${fail} failed`);
  process.exit(fail ? 1 : 0);
}
