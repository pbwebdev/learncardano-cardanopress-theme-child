/**
 * Sign-in dialog and Cardano wallet flow (CIP-30).
 *
 * Loaded on demand: assets/js/forms.js fetches this file (and auth.css) the
 * first time someone hovers, focuses or clicks a [data-lc-auth-open] link,
 * and /account/ pages enqueue it. Ordinary page views never load it.
 *
 *   window.lcAuth.open({ context, redirect, trigger })  open the dialog
 *   window.lcAuth.mountAccount(el)                       "Connect a wallet" on /account/
 *
 * The panel HTML comes from GET /account/panel/ (escaped on the server).
 * Every request is a same-origin fetch to an /account/* route that answers
 * JSON. Wallet names/icons come from window.cardano and are only ever set
 * with textContent / img.src, never innerHTML.
 *
 * @package CardanoPress Bootstrap
 */
(function () {
	'use strict';

	const cfg = (window.lcForms && window.lcForms.auth) || {};
	const PRIORITY = ['lace', 'eternl', 'vespr', 'typhon', 'yoroi', 'gerowallet', 'nufi', 'begin'];
	const T = {
		generic: 'Something went wrong. Please try again.',
		cancelled: 'You cancelled in your wallet.',
		network: 'Please switch your wallet to a Cardano mainnet account.',
		nostake: 'This wallet account has no stake key. Try another account.',
		pickAccount: 'This wallet account cannot sign. Try a different account.',
		opening: 'Opening your wallet…',
		approve: 'Approve the message in your wallet. It is free and not a transaction.',
		none: 'No Cardano wallet found in this browser. Install '
	};

	const $ = function (sel, root) { return (root || document).querySelector(sel); };

	function say(root, msg, bad) {
		const el = $('[data-lc-auth-status]', root);
		if (el) {
			el.textContent = msg || '';
			el.classList.toggle('is-error', !!bad);
		}
		return el;
	}

	function post(url, body) {
		return fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
			body: JSON.stringify(body)
		}).then(function (res) {
			return res.json().catch(function () { return {}; });
		});
	}

	/* Same page: reload (a "#respond" anchor alone would not refresh the form). */
	function go(url) {
		try {
			const u = new URL(url, location.href);
			if (u.origin === location.origin && u.pathname === location.pathname && u.search === location.search) {
				location.reload();
			} else {
				location.assign(u.href);
			}
		} catch (e) {
			location.reload();
		}
	}

	/* ---------- wallets ---------- */

	function wallets() {
		const all = window.cardano || {};
		const out = [];
		Object.keys(all).forEach(function (id) {
			const w = all[id];
			if (w && typeof w.enable === 'function' && w.name) {
				out.push({ id: id, name: String(w.name), icon: typeof w.icon === 'string' ? w.icon : '' });
			}
		});
		const rank = function (w) {
			const i = PRIORITY.findIndex(function (p) { return w.id.toLowerCase().indexOf(p) === 0; });
			return i === -1 ? 99 : i;
		};
		out.sort(function (a, b) { return rank(a) - rank(b) || a.name.localeCompare(b.name); });
		const seen = {};
		return out.filter(function (w) { // Some wallets inject twice (typhon + typhoncip30).
			const k = w.name.toLowerCase();
			return seen[k] ? false : (seen[k] = true);
		});
	}

	function hexOf(text) {
		return Array.from(new TextEncoder().encode(text), function (b) { return b.toString(16).padStart(2, '0'); }).join('');
	}

	function walletError(e) {
		const info = String((e && (e.info || e.message)) || '');
		if (e && (e.code === 3 || e.code === -3 || /declin|cancel|reject|refus|denied/i.test(info))) {
			return T.cancelled;
		}
		return e && e.code === 2 ? T.pickAccount : T.generic;
	}

	/* enable, challenge, sign, verify. o: root, intent, redirect, nonce. */
	async function walletFlow(id, o) {
		const fail = function (msg) { say(o.root, msg, true); return false; };
		try {
			say(o.root, T.opening);
			const api = await window.cardano[id].enable();
			if (typeof api.getNetworkId === 'function' && (await api.getNetworkId()) !== 1) {
				return fail(T.network);
			}
			const reward = ((await api.getRewardAddresses()) || [])[0];
			if (!reward) {
				return fail(T.nostake);
			}
			const ch = await post(cfg.account + 'wallet/challenge', { reward: reward, intent: o.intent, nonce: o.nonce || '' });
			if (!ch.ok) {
				return fail(ch.message || T.generic);
			}
			say(o.root, T.approve);
			let sig;
			try {
				sig = await api.signData(reward, hexOf(ch.message));
			} catch (e) {
				if (walletError(e) === T.cancelled) {
					throw e;
				}
				sig = await api.signData(ch.stake, hexOf(ch.message)); // CIP-30 wallets accept either form.
			}
			const res = await post(cfg.account + 'wallet/verify', { nonce: ch.nonce, signature: sig.signature, key: sig.key, redirect_to: o.redirect || '' });
			if (!res.ok) {
				return fail(res.message || T.generic);
			}
			go(o.intent === 'link' ? location.href : res.redirect || cfg.account);
			return true;
		} catch (e) {
			return fail(walletError(e));
		}
	}

	/* Reveal the wallet button and list installed wallets when it is pressed. */
	function initWallets(root, o) {
		const btn = $('[data-lc-auth-wallet]', root);
		const list = $('[data-lc-auth-wallets]', root);
		if (!btn || !list) {
			return;
		}
		const wrap = $('[data-lc-auth-wallet-wrap]', root);
		(wrap || btn).hidden = false;
		btn.hidden = false;
		btn.addEventListener('click', function () {
			const found = wallets();
			list.textContent = '';
			if (!found.length) {
				const el = say(root, T.none, true);
				[['Lace', 'https://www.lace.io/'], ['Eternl', 'https://eternl.io/']].forEach(function (l, i) {
					const a = document.createElement('a');
					a.href = l[1];
					a.target = '_blank';
					a.rel = 'noopener noreferrer';
					a.textContent = l[0];
					el.append(i ? ' or ' : '', a);
				});
				el.append('. On a phone, open this page inside your wallet app\'s browser.');
				return;
			}
			say(root, '');
			found.forEach(function (w) {
				const li = document.createElement('li');
				const b = document.createElement('button');
				b.type = 'button';
				b.className = 'lc-auth__wallet-btn';
				if (/^(data:image\/|https:)/.test(w.icon)) {
					const img = document.createElement('img');
					img.src = w.icon;
					img.alt = '';
					img.width = img.height = 24;
					b.appendChild(img);
				}
				const name = document.createElement('span');
				name.textContent = w.name;
				b.appendChild(name);
				b.addEventListener('click', function () {
					list.querySelectorAll('button').forEach(function (x) { x.disabled = true; });
					walletFlow(w.id, Object.assign({ root: root }, o)).then(function (ok) {
						if (!ok) {
							list.querySelectorAll('button').forEach(function (x) { x.disabled = false; });
						}
					});
				});
				li.appendChild(b);
				list.appendChild(li);
			});
			list.hidden = false;
		});
	}

	/* ---------- email form ---------- */

	function initEmail(root) {
		const form = $('form[data-lc-auth-email]', root);
		if (!form || !window.fetch || !window.FormData) {
			return;
		}
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			const button = form.querySelector('button[type="submit"]');
			if (button.disabled) {
				return;
			}
			button.disabled = true;
			fetch(form.action, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Accept': 'application/json' },
				body: new FormData(form)
			}).then(function (res) {
				return res.json();
			}).then(function (json) {
				say(root, json.message || T.generic, !json.ok);
				if (json.ok) {
					form.hidden = true;
				} else {
					button.disabled = false;
				}
			}).catch(function () {
				say(root, T.generic, true);
				button.disabled = false;
			});
		});
	}

	function initPanel(root) {
		if (root.dataset.lcInit) {
			return;
		}
		root.dataset.lcInit = '1';
		initEmail(root);
		initWallets(root, { intent: 'signin', redirect: root.dataset.redirect || '' });
	}

	/* ---------- the dialog ---------- */

	function open(o) {
		o = o || {};
		if ($('dialog.lc-auth[open]')) {
			return;
		}
		const popup = document.getElementById('lc-popup-27449');
		if (popup && popup.open) {
			popup.close();
		}
		const dlg = document.createElement('dialog');
		dlg.className = 'lc-popup lc-auth';
		dlg.setAttribute('aria-labelledby', 'lc-auth-title');
		dlg.innerHTML = '<div class="lc-popup__box"><button type="button" class="lc-popup__close" aria-label="Close"><span aria-hidden="true">&times;</span></button><div data-lc-auth-slot></div></div>';
		const slot = $('[data-lc-auth-slot]', dlg);
		slot.textContent = 'Loading…';
		document.body.appendChild(dlg);
		dlg.addEventListener('click', function (e) {
			if (e.target === dlg) {
				dlg.close();
			}
		});
		$('.lc-popup__close', dlg).addEventListener('click', function () { dlg.close(); });
		dlg.addEventListener('close', function () {
			dlg.remove();
			if (o.trigger && o.trigger.focus) {
				o.trigger.focus();
			}
		});
		dlg.showModal();

		fetch(cfg.panel + '?context=' + encodeURIComponent(o.context || 'header') + '&redirect_to=' + encodeURIComponent(o.redirect || location.href), {
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'Accept': 'application/json' }
		}).then(function (res) {
			return res.json();
		}).then(function (json) {
			if (json.signed_in) {
				return location.reload();
			}
			if (!json.ok) {
				slot.textContent = json.message || T.generic;
				return;
			}
			slot.innerHTML = json.html; // Escaped by template-parts/auth-panel.php.
			const panel = $('[data-lc-auth-panel]', slot);
			if (panel) {
				initPanel(panel);
				const email = $('input[type="email"]', panel);
				if (email) {
					email.focus();
				}
			}
		}).catch(function () {
			dlg.close();
			if (o.trigger && o.trigger.href) {
				location.assign(o.trigger.href);
			}
		});
	}

	function mountAccount(el) {
		if (!el.dataset.lcInit) {
			el.dataset.lcInit = '1';
			initWallets(el, { intent: 'link', nonce: el.dataset.nonce || '', redirect: '' });
		}
	}

	window.lcAuth = { open: open, mountAccount: mountAccount };

	/* /account/ pages: the panel and the wallet section are already in the page. */
	function boot() {
		document.querySelectorAll('[data-lc-auth-panel]').forEach(initPanel);
		document.querySelectorAll('[data-lc-account-wallets]').forEach(mountAccount);
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
