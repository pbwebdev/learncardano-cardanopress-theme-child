/*
  learncardano.io delegation panel — vanilla JS state machine, ported from
  cardanode.com.au (src/static/scripts/delegate-panel.js).

  Flow:
    idle → picking → connecting → connected → working → success
                                                      → already-adaoz
                                                      → error

  The Mesh SDK bundle (~5 MB, assets/vendor/mesh/mesh.js) is imported on the
  first click so the page stays fast for visitors who never delegate. The pool
  ID, bundle URL and API URL come from data attributes on #delegate-panel
  (template-delegate.php).

  Chain data comes from the theme's REST endpoint (includes/delegate.php),
  which asks Koios server-side because Koios sends no CORS headers: the
  wallet's registration and current pool, plus current protocol parameters
  for building the transaction. The wallet itself submits it (CIP-30).
*/

// Wallets shown first if installed (other detected wallets follow, A–Z).
const PRIORITY_WALLETS = ["lace", "eternl", "nami", "typhon", "nufi", "begin", "yoroi"];

const $ = (sel, root = document) => root.querySelector(sel);
const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

const panel = $("#delegate-panel");
if (panel) initPanel();
initCopyButtons();

function initPanel() {
  const POOL_ID = panel.dataset.pool;
  const MESH_URL = panel.dataset.meshUrl;
  const API_URL = panel.dataset.api;

  let mesh = null;
  let wallet = null;          // BrowserWallet instance once connected
  let stakeAddress = null;    // stake1...
  let changeAddress = null;
  let accountInfo = null;
  let protocolParams = null;  // current mainnet parameters, from the API

  setState("idle", false);

  // Event delegation for buttons.
  panel.addEventListener("click", async (e) => {
    const action = e.target.closest("[data-action]")?.dataset.action;
    if (!action) return;
    if (action === "open-picker") return openPicker();
    if (action === "cancel") return setState("idle");
    if (action === "disconnect") return disconnect();
    if (action === "delegate") return startDelegation();
    if (action === "reset") return reset();
    if (action === "retry") return setState(isConnected() ? "connected" : "idle");
  });

  // Connected = wallet enabled AND its account + protocol parameters loaded.
  function isConnected() {
    return Boolean(wallet && stakeAddress && changeAddress && accountInfo && protocolParams);
  }

  function clearConnection() {
    wallet = null;
    stakeAddress = null;
    changeAddress = null;
    accountInfo = null;
    protocolParams = null;
  }

  async function loadMesh() {
    if (!mesh) {
      setStatusBanner("Loading wallet SDK (~5 MB, one-time)…");
      try {
        mesh = await import(MESH_URL);
      } finally {
        setStatusBanner("");
      }
    }
    return mesh;
  }

  async function openPicker() {
    try {
      const m = await loadMesh();
      const installed = m.BrowserWallet.getInstalledWallets();
      if (!installed.length) {
        return showError(
          "No Cardano wallet detected. Install <a href='https://www.lace.io/' target='_blank' rel='noopener noreferrer'>Lace</a> or <a href='https://eternl.io/' target='_blank' rel='noopener noreferrer'>Eternl</a> and refresh.",
        );
      }
      // Each wallet has an id (its window.cardano key, which enable() needs)
      // and a display name. Stable ordering: priority ids first, then the rest
      // by name. Some wallets inject twice (e.g. typhon + typhoncip30), so keep
      // the first entry per name.
      const rank = (w) => PRIORITY_WALLETS.findIndex((p) => w.id.toLowerCase().startsWith(p));
      const byPriority = (a, b) => {
        const ai = rank(a);
        const bi = rank(b);
        if (ai === -1 && bi === -1) return pretty(a.name).localeCompare(pretty(b.name));
        if (ai === -1) return 1;
        if (bi === -1) return -1;
        return ai - bi;
      };
      const seen = new Set();
      const sorted = [...installed].sort(byPriority).filter((w) => {
        const key = pretty(w.name).toLowerCase();
        if (seen.has(key)) return false;
        seen.add(key);
        return true;
      });

      const list = $("#dp-wallets");
      list.innerHTML = "";
      for (const w of sorted) {
        const li = document.createElement("li");
        const btn = document.createElement("button");
        btn.className = "dlg__wallet";
        btn.type = "button";
        btn.dataset.walletId = w.id;
        btn.setAttribute("aria-label", `Connect ${pretty(w.name)} wallet`);
        btn.innerHTML = `
          ${w.icon ? `<img src="${escapeHtml(w.icon)}" alt="" width="28" height="28">` : `<span class="dlg__wallet-fallback" aria-hidden="true">${escapeHtml(pretty(w.name).charAt(0))}</span>`}
          <span class="dlg__wallet-name">${escapeHtml(pretty(w.name))}</span>
        `;
        btn.addEventListener("click", () => connect(w.id, pretty(w.name)));
        li.appendChild(btn);
        list.appendChild(li);
      }
      setState("picking");
    } catch (err) {
      console.error(err);
      showError("Couldn't load the wallet SDK. Refresh and try again.");
    }
  }

  async function connect(walletId, walletName) {
    clearConnection();
    setState("connecting");
    setStatusBanner(`Connecting to ${walletName}…`);
    try {
      const m = await loadMesh();
      // Work on locals and only keep them once every step has succeeded, so a
      // failure never leaves a half-connected panel behind.
      const w = await m.BrowserWallet.enable(walletId);
      const stake = (await w.getRewardAddresses())?.[0];
      const change = await w.getChangeAddress();
      if (!stake) throw new Error("Wallet didn't return a stake address.");
      if (stake.startsWith("stake_test")) {
        return showError("Your wallet is on a test network. Switch it to Mainnet, then connect again.");
      }

      const data = await fetchDelegationData(stake);
      let account = data.account;
      if (account.is_delegated_to_pool) {
        sessionClear(stake);
      } else if (sessionGet(stake)) {
        // Delegated from this browser in the last few minutes, but the chain
        // or the API's short cache doesn't show it yet: trust the submitted
        // transaction rather than offering to delegate again.
        account = { ...account, registered: true, is_delegated_to_pool: true, delegated_pool: POOL_ID, pending: true };
      }

      wallet = w;
      stakeAddress = stake;
      changeAddress = change;
      accountInfo = account;
      protocolParams = data.params;

      renderConnected();
      setState("connected");
    } catch (err) {
      console.error(err);
      clearConnection();
      showError(isCancellation(err)
        ? "Connection cancelled. No problem — click Connect when you're ready."
        : `Couldn't connect: ${escapeHtml(errorText(err))}`);
    } finally {
      setStatusBanner("");
    }
  }

  // { account: registration + current pool, params: protocol parameters }.
  async function fetchDelegationData(stake) {
    const url = new URL(API_URL, window.location.href);
    url.searchParams.set("stake", stake);
    const res = await fetch(url, { headers: { accept: "application/json" }, credentials: "same-origin" });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.account || !data.params) {
      throw new Error(data.message || `Account lookup failed (${res.status})`);
    }
    return data;
  }

  function renderConnected() {
    $("#dp-stake-addr").textContent = shortAddr(stakeAddress);
    $("#dp-stake-addr").title = stakeAddress;

    const reg = accountInfo.registered;
    const onPool = accountInfo.is_delegated_to_pool;
    const balance = accountInfo.total_balance_ada;
    const currentPool = accountInfo.delegated_pool;

    $("#dp-reg-status").innerHTML = reg
      ? "<span class='dlg__ok'>Registered</span>"
      : "<span class='dlg__warn'>Not registered (one-time 2 ₳ deposit required)</span>";

    $("#dp-current-pool").innerHTML = onPool
      ? (accountInfo.pending ? "<span class='dlg__ok'>ADAOZ (confirming on-chain)</span>" : "<span class='dlg__ok'>ADAOZ 🎉</span>")
      : currentPool
        ? `Another pool (<code>${escapeHtml(shortPool(currentPool))}</code>)`
        : "Not delegated yet";

    $("#dp-balance").textContent = balance != null
      ? balance.toLocaleString("en-US") + " ₳"
      : "—";

    // The delegate button adapts to the account's state.
    const btn = $("#dp-delegate");
    if (onPool) {
      btn.hidden = true;
    } else {
      btn.hidden = false;
      btn.textContent = reg ? "Delegate to ADAOZ" : "Register & delegate to ADAOZ";
    }
  }

  async function startDelegation() {
    if (accountInfo?.is_delegated_to_pool) {
      return setState("already-adaoz");
    }
    setState("working");
    setWorking("Building transaction", "Composing the delegation certificate…");
    // Which step failed decides the message: CIP-30 uses code 2 both for
    // "user declined to sign" and "wallet failed to submit".
    let step = "build";
    try {
      const m = await loadMesh();

      // The wallet supplies complete UTxOs, so no chain fetcher is needed;
      // fees and the deposit come from the current protocol parameters.
      const tx = new m.MeshTxBuilder({
        params: protocolParams,
        verbose: false,
      });

      // Stake-key registration first, only if needed (one-time 2 ₳ deposit).
      if (!accountInfo.registered) {
        tx.registerStakeCertificate(stakeAddress);
      }
      tx.delegateStakeCertificate(stakeAddress, POOL_ID);

      const utxos = await wallet.getUtxos();
      tx.changeAddress(changeAddress).selectUtxosFrom(utxos);
      const unsignedTx = await tx.complete();

      step = "sign";
      setWorking("Sign in your wallet", "Your wallet will pop up — review and approve.");
      const signedTx = await wallet.signTx(unsignedTx);

      step = "submit";
      setWorking("Submitting", "Sending the signed transaction to the network…");
      const txHash = await wallet.submitTx(signedTx);

      // Remember it for this browser session so a quick reconnect (before the
      // chain and the API's short cache catch up) doesn't offer it again.
      sessionSet(stakeAddress, txHash);
      accountInfo = { ...accountInfo, is_delegated_to_pool: true, delegated_pool: POOL_ID, registered: true, pending: true };

      const link = $("#dp-tx-link");
      link.href = `https://cardanoscan.io/transaction/${encodeURIComponent(txHash)}`;
      link.textContent = txHash;
      setState("success");
    } catch (err) {
      console.error(err);
      const msg = errorText(err);
      if (step === "sign" && isCancellation(err)) {
        showError("Signature cancelled. Nothing was sent.");
      } else if (step === "submit") {
        showError(`The network didn't accept the transaction, so nothing was spent. Wait a minute and try again. Details: ${escapeHtml(msg.slice(0, 200))}`);
      } else if (/insufficient|UTxO Balance|UTxO Fully Depleted|balance/i.test(msg)) {
        showError("Not enough ADA in this wallet to cover the deposit and fees (about 3 ₳ for first-time delegators, otherwise about 0.2 ₳).");
      } else {
        showError(`Delegation failed: ${escapeHtml(msg.slice(0, 200))}`);
      }
    } finally {
      setWorking("", "");
    }
  }

  function disconnect() {
    clearConnection();
    setState("idle");
  }

  // "Done" after a delegation: show the connected view, now reflecting ADAOZ.
  function reset() {
    if (isConnected()) {
      renderConnected();
      setState("connected");
    } else {
      setState("idle");
    }
  }

  /* ---- state helpers ---- */

  // `moveFocus` is false only for the initial render, so the page doesn't jump.
  function setState(name, moveFocus = true) {
    panel.dataset.state = name;
    $$("[data-show]", panel).forEach((el) => {
      el.hidden = el.dataset.show !== name;
    });
    if (!moveFocus) return;
    // Move focus to the new state's heading or first button, for keyboard and
    // screen-reader users (back to the Connect button when returning to idle).
    const visible = $(`[data-show="${name}"]`, panel);
    const focusTarget = visible?.querySelector(name === "idle" ? "button" : "h3, button, [tabindex]");
    if (focusTarget && document.activeElement !== focusTarget) {
      if (focusTarget.tagName === "H3") focusTarget.setAttribute("tabindex", "-1");
      focusTarget.focus({ preventScroll: false });
    }
  }

  // Delegations submitted from this browser, per stake address. Kept for this
  // session only and trusted for 15 minutes, after which the chain should
  // show it; a transaction that never landed then stops hiding the button.
  const SUBMITTED_TTL_MS = 15 * 60 * 1000;
  function sessionGet(stake) {
    try {
      const saved = JSON.parse(sessionStorage.getItem(`lc-delegated:${stake}`) || "null");
      if (saved && typeof saved.at === "number" && Date.now() - saved.at < SUBMITTED_TTL_MS) return saved.txHash;
      sessionClear(stake);
    } catch { /* storage unavailable or malformed */ }
    return null;
  }
  function sessionSet(stake, txHash) {
    try { sessionStorage.setItem(`lc-delegated:${stake}`, JSON.stringify({ txHash, at: Date.now() })); } catch { /* storage unavailable */ }
  }
  function sessionClear(stake) {
    try { sessionStorage.removeItem(`lc-delegated:${stake}`); } catch { /* storage unavailable */ }
  }

  function setStatusBanner(text) {
    const el = $("#dp-status-banner");
    if (!el) return;
    el.textContent = text;
    el.hidden = !text;
  }

  function setWorking(title, msg) {
    $("#dp-working-title").textContent = title;
    $("#dp-working-msg").textContent = msg;
  }

  function showError(htmlMsg) {
    $("#dp-error-msg").innerHTML = htmlMsg;
    setState("error");
  }
}

// "Copy pool ID" and similar buttons: <button data-copy="#element-id">.
function initCopyButtons() {
  $$("[data-copy]").forEach((button) => {
    button.addEventListener("click", () => {
      const source = $(button.dataset.copy);
      if (!source) return;
      const label = button.textContent;
      navigator.clipboard.writeText(source.textContent.trim()).then(() => {
        button.textContent = "Copied";
        setTimeout(() => { button.textContent = label; }, 2000);
      });
    });
  });
}

/* ---- pure helpers ---- */

// Readable text from any error: Error objects, CIP-30 {code, info} objects
// (APIError / TxSignError), Mesh's wrapped errors, or plain strings.
function errorText(err) {
  if (!err) return "unknown error";
  if (typeof err === "string") return err;
  if (err.info) return String(err.info);
  if (err.message) return String(err.message);
  try { return JSON.stringify(err); } catch { return String(err); }
}

// The user said no in their wallet: CIP-30 TxSignError.UserDeclined (code 2,
// only meaningful while signing), APIError.Refused (code -3), or a wallet/Mesh
// message saying so.
function isCancellation(err) {
  if (err && (err.code === 2 || err.code === -3)) return true;
  return /declined|denied|refused|cancel/i.test(errorText(err));
}

function pretty(name) {
  return String(name || "")
    .replace(/^./, (c) => c.toUpperCase())
    .replace(/_/g, " ");
}

function shortAddr(s) {
  if (!s) return "";
  return s.length > 22 ? `${s.slice(0, 12)}…${s.slice(-8)}` : s;
}

function shortPool(s) {
  if (!s) return "";
  return s.length > 22 ? `${s.slice(0, 10)}…${s.slice(-6)}` : s;
}

function escapeHtml(s = "") {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
