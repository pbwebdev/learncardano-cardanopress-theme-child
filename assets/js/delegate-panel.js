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

  setState("idle");

  // Event delegation for buttons.
  panel.addEventListener("click", async (e) => {
    const action = e.target.closest("[data-action]")?.dataset.action;
    if (!action) return;
    if (action === "open-picker") return openPicker();
    if (action === "cancel") return setState("idle");
    if (action === "disconnect") return disconnect();
    if (action === "delegate") return startDelegation();
    if (action === "reset") return reset();
    if (action === "retry") return setState(wallet ? "connected" : "idle");
  });

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
      // Stable ordering: priority list first, then the rest alphabetically.
      const byPriority = (a, b) => {
        const ai = PRIORITY_WALLETS.indexOf(a.name.toLowerCase());
        const bi = PRIORITY_WALLETS.indexOf(b.name.toLowerCase());
        if (ai === -1 && bi === -1) return a.name.localeCompare(b.name);
        if (ai === -1) return 1;
        if (bi === -1) return -1;
        return ai - bi;
      };
      const sorted = [...installed].sort(byPriority);

      const list = $("#dp-wallets");
      list.innerHTML = "";
      for (const w of sorted) {
        const li = document.createElement("li");
        const btn = document.createElement("button");
        btn.className = "dlg__wallet";
        btn.type = "button";
        btn.dataset.walletId = w.name;
        btn.setAttribute("aria-label", `Connect ${pretty(w.name)} wallet`);
        btn.innerHTML = `
          ${w.icon ? `<img src="${escapeHtml(w.icon)}" alt="" width="28" height="28">` : `<span class="dlg__wallet-fallback" aria-hidden="true">${escapeHtml(pretty(w.name).charAt(0))}</span>`}
          <span class="dlg__wallet-name">${escapeHtml(pretty(w.name))}</span>
        `;
        btn.addEventListener("click", () => connect(w.name));
        li.appendChild(btn);
        list.appendChild(li);
      }
      setState("picking");
    } catch (err) {
      console.error(err);
      showError("Couldn't load the wallet SDK. Refresh and try again.");
    }
  }

  async function connect(walletId) {
    setState("connecting");
    setStatusBanner(`Connecting to ${pretty(walletId)}…`);
    try {
      const m = await loadMesh();
      wallet = await m.BrowserWallet.enable(walletId);
      const rewardAddresses = await wallet.getRewardAddresses();
      stakeAddress = rewardAddresses?.[0];
      changeAddress = await wallet.getChangeAddress();
      if (!stakeAddress) throw new Error("Wallet didn't return a stake address.");
      if (stakeAddress.startsWith("stake_test")) {
        return showError("Your wallet is on a test network. Switch it to Mainnet, then connect again.");
      }

      ({ account: accountInfo, params: protocolParams } = await fetchDelegationData(stakeAddress));

      renderConnected();
      setState("connected");
    } catch (err) {
      console.error(err);
      showError(/declined|denied|cancel/i.test(String(err?.message || err))
        ? "Connection cancelled. No problem — click Connect when you're ready."
        : `Couldn't connect: ${escapeHtml(err?.message || "unknown error")}`);
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
      ? "<span class='dlg__ok'>ADAOZ 🎉</span>"
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

      setWorking("Sign in your wallet", "Your wallet will pop up — review and approve.");
      const signedTx = await wallet.signTx(unsignedTx);

      setWorking("Submitting", "Sending the signed transaction to the network…");
      const txHash = await wallet.submitTx(signedTx);

      accountInfo = { ...accountInfo, is_delegated_to_pool: true, delegated_pool: POOL_ID, registered: true };

      const link = $("#dp-tx-link");
      link.href = `https://cardanoscan.io/transaction/${encodeURIComponent(txHash)}`;
      link.textContent = txHash;
      setState("success");
    } catch (err) {
      console.error(err);
      const msg = String(err?.message || err || "");
      if (/declined|user denied|cancel/i.test(msg)) {
        showError("Signature cancelled. Nothing was sent.");
      } else if (/insufficient|UTxO Balance|balance/i.test(msg)) {
        showError("Not enough ADA in this wallet to cover the deposit and fees (about 3 ₳ for first-time delegators, otherwise about 0.2 ₳).");
      } else {
        showError(`Delegation failed: ${escapeHtml(msg.slice(0, 200))}`);
      }
    } finally {
      setWorking("", "");
    }
  }

  function disconnect() {
    wallet = null;
    stakeAddress = null;
    changeAddress = null;
    accountInfo = null;
    protocolParams = null;
    setState("idle");
  }

  function reset() {
    setState(wallet ? "connected" : "idle");
  }

  /* ---- state helpers ---- */

  function setState(name) {
    panel.dataset.state = name;
    $$("[data-show]", panel).forEach((el) => {
      el.hidden = el.dataset.show !== name;
    });
    // Move focus to the new state's heading or first button, for screen readers.
    const visible = $(`[data-show="${name}"]`, panel);
    const focusTarget = visible?.querySelector("h3, button, [tabindex]");
    if (focusTarget && name !== "idle" && document.activeElement !== focusTarget) {
      if (focusTarget.tagName === "H3") focusTarget.setAttribute("tabindex", "-1");
      focusTarget.focus({ preventScroll: false });
    }
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
