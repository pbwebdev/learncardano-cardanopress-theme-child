/**
 * Site forms and the newsletter popup (includes/forms.php,
 * includes/newsletter-popup.php, template-parts/form-*.php).
 *
 * Forms work without JS (admin-post + redirect). With JS they:
 * - fetch a fresh nonce and start time when someone starts filling them in,
 *   because the HTML (and its nonce) can come from the page cache;
 * - show/hide conditional fields (data-lc-show-if);
 * - load reCAPTCHA only once a form is near the viewport or in focus;
 * - submit through admin-ajax and show errors or the confirmation inline.
 *
 * The popup opens at 50% scroll unless its closing (7 days) or conversion
 * (30 days) cookie exists, and only then renders the form from its <template>
 * and loads forms.css and reCAPTCHA.
 *
 * Config comes from wp_localize_script( 'lc-forms', 'lcForms' ).
 *
 * @package CardanoPress Bootstrap
 */
(function () {
	'use strict';

	const cfg = window.lcForms || {};
	const strings = cfg.strings || {};

	/* ---------- cookies ---------- */

	function getCookie(name) {
		return document.cookie.split('; ').some(function (part) {
			return part.indexOf(name + '=') === 0;
		});
	}

	function setCookie(name, days) {
		const expires = new Date(Date.now() + days * 864e5).toUTCString();
		const secure = location.protocol === 'https:' ? '; Secure' : '';
		document.cookie = name + '=true; expires=' + expires + '; path=/; SameSite=Lax' + secure;
	}

	/* ---------- assets loaded on demand ---------- */

	let cssPromise = null;
	function loadCss() {
		if (cssPromise) {
			return cssPromise;
		}
		cssPromise = new Promise(function (resolve) {
			if (!cfg.css || document.getElementById('lc-forms-css')) {
				resolve();
				return;
			}
			const link = document.createElement('link');
			link.id = 'lc-forms-css';
			link.rel = 'stylesheet';
			link.href = cfg.css;
			link.onload = link.onerror = function () { resolve(); };
			document.head.appendChild(link);
			// Never keep the popup waiting on a slow stylesheet.
			setTimeout(resolve, 2000);
		});
		return cssPromise;
	}

	let recaptchaPromise = null;
	function loadRecaptcha() {
		if (!cfg.recaptcha) {
			return Promise.resolve(null);
		}
		if (recaptchaPromise) {
			return recaptchaPromise;
		}
		recaptchaPromise = new Promise(function (resolve, reject) {
			window.lcFormsRecaptchaReady = function () { resolve(window.grecaptcha); };
			const script = document.createElement('script');
			script.src = 'https://www.google.com/recaptcha/api.js?onload=lcFormsRecaptchaReady&render=explicit';
			script.async = true;
			script.defer = true;
			script.onerror = reject;
			document.head.appendChild(script);
		});
		return recaptchaPromise;
	}

	function renderCaptcha(form) {
		const box = form.querySelector('.lc-form__captcha[data-sitekey]');
		if (!box || box.dataset.widget !== undefined) {
			return;
		}
		box.dataset.widget = 'pending';
		loadRecaptcha().then(function (grecaptcha) {
			if (!grecaptcha) {
				return;
			}
			box.dataset.widget = String(grecaptcha.render(box, { sitekey: box.dataset.sitekey }));
		}).catch(function () {
			delete box.dataset.widget;
		});
	}

	function resetCaptcha(form) {
		const box = form.querySelector('.lc-form__captcha[data-widget]');
		if (box && window.grecaptcha && /^\d+$/.test(box.dataset.widget)) {
			window.grecaptcha.reset(Number(box.dataset.widget));
		}
	}

	/* ---------- conditional fields ---------- */

	function applyShowIf(form) {
		form.querySelectorAll('[data-lc-show-if]').forEach(function (wrap) {
			let rule;
			try {
				rule = JSON.parse(wrap.dataset.lcShowIf);
			} catch (e) {
				return;
			}
			const control = form.elements['lc_' + rule.field];
			const value = control ? control.value : '';
			const inList = rule.values.indexOf(value) !== -1;
			const show = rule.op === 'in' ? inList : !inList;
			wrap.hidden = !show;
			// Hidden fields are neither required nor sent.
			wrap.querySelectorAll('input, select, textarea').forEach(function (el) {
				el.disabled = !show;
			});
		});
	}

	/* ---------- errors ---------- */

	function clearErrors(form) {
		const summary = form.querySelector('.lc-form__summary');
		if (summary) {
			summary.hidden = true;
			summary.textContent = '';
		}
		form.querySelectorAll('.lc-form__field.is-invalid').forEach(function (el) {
			el.classList.remove('is-invalid');
		});
		form.querySelectorAll('[aria-invalid]').forEach(function (el) {
			el.removeAttribute('aria-invalid');
		});
		form.querySelectorAll('.lc-form__error').forEach(function (el) {
			el.hidden = true;
		});
	}

	function showErrors(form, data) {
		const summary = form.querySelector('.lc-form__summary');
		const errors = (data && data.errors) || {};
		let first = null;
		Object.keys(errors).forEach(function (key) {
			const wrap = form.querySelector('[data-lc-field="' + key + '"]');
			if (!wrap) {
				return;
			}
			wrap.classList.add('is-invalid');
			const msg = wrap.querySelector('.lc-form__error');
			if (msg) {
				msg.textContent = errors[key];
				msg.hidden = false;
			}
			wrap.querySelectorAll('input, select, textarea').forEach(function (el) {
				el.setAttribute('aria-invalid', 'true');
				if (!first) {
					first = el;
				}
			});
		});
		if (summary) {
			summary.textContent = (data && data.message) || strings.failed || '';
			summary.hidden = false;
		}
		if (first && first.focus && !first.matches('textarea[name="g-recaptcha-response"]')) {
			first.focus();
		} else if (summary) {
			summary.focus();
		}
	}

	/* ---------- fresh nonce + start time ---------- */

	function post(params) {
		const body = new URLSearchParams(params);
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		}).then(function (res) {
			return res.json();
		});
	}

	function refreshToken(form) {
		if (form.lcToken) {
			return form.lcToken;
		}
		form.lcToken = post({ action: cfg.token, form: form.dataset.lcForm }).then(function (json) {
			if (json && json.success) {
				form.elements._lc_nonce.value = json.data.nonce;
				form.elements.lc_ts.value = json.data.ts;
			}
		}).catch(function () {
			// Keep the values from the page.
		});
		return form.lcToken;
	}

	/* ---------- forms ---------- */

	function initForm(form, onSuccess) {
		if (form.dataset.lcReady) {
			return;
		}
		form.dataset.lcReady = '1';

		applyShowIf(form);
		form.addEventListener('change', function () { applyShowIf(form); });

		const start = function () {
			refreshToken(form);
			renderCaptcha(form);
		};
		form.addEventListener('focusin', start, { once: true });
		form.addEventListener('pointerdown', start, { once: true });

		if ('IntersectionObserver' in window) {
			const io = new IntersectionObserver(function (entries) {
				if (entries.some(function (e) { return e.isIntersecting; })) {
					io.disconnect();
					renderCaptcha(form);
				}
			}, { rootMargin: '300px' });
			io.observe(form);
		} else {
			renderCaptcha(form);
		}

		form.addEventListener('submit', function (event) {
			if (!window.fetch || !window.FormData) {
				return; // Plain POST to admin-post.php.
			}
			event.preventDefault();
			if (form.dataset.lcBusy) {
				return;
			}
			form.dataset.lcBusy = '1';
			const button = form.querySelector('.lc-form__submit');
			const label = button ? button.textContent : '';
			if (button) {
				button.disabled = true;
				button.textContent = strings.sending || label;
			}
			clearErrors(form);

			refreshToken(form).then(function () {
				return fetch(cfg.ajaxUrl, {
					method: 'POST',
					credentials: 'same-origin',
					body: new FormData(form)
				});
			}).then(function (res) {
				return res.json();
			}).then(function (json) {
				if (json && json.success) {
					const wrap = form.closest('.lc-form-wrap') || form.parentNode;
					const done = document.createElement('div');
					done.className = 'lc-form__confirmation';
					done.setAttribute('role', 'status');
					done.tabIndex = -1;
					done.innerHTML = json.data.message; // Server-escaped confirmation text.
					form.replaceWith(done);
					done.focus();
					if (onSuccess) {
						onSuccess(form.dataset.lcForm);
					}
					document.dispatchEvent(new CustomEvent('lc-form:success', { detail: { form: form.dataset.lcForm, wrap: wrap } }));
					return;
				}
				showErrors(form, json && json.data);
				resetCaptcha(form);
				// An expired nonce needs a fresh one for the next attempt.
				if (json && json.data && json.data.code === 'expired') {
					form.lcToken = null;
				}
			}).catch(function () {
				showErrors(form, { message: strings.failed });
				resetCaptcha(form);
			}).then(function () {
				delete form.dataset.lcBusy;
				if (button) {
					button.disabled = false;
					button.textContent = label;
				}
			});
		});
	}

	// The newsletter signup counts as a popup conversion wherever it happens.
	function markConversion(formId) {
		const popup = document.getElementById('lc-popup-27449');
		if (formId === 'newsletter' && popup) {
			setCookie(popup.dataset.conversionCookie, Number(popup.dataset.conversionDays) || 30);
		}
	}

	/* ---------- newsletter popup ---------- */

	function initPopup() {
		const dialog = document.getElementById('lc-popup-27449');
		if (!dialog || typeof dialog.showModal !== 'function') {
			return;
		}
		const closingCookie = dialog.dataset.closingCookie;
		const conversionCookie = dialog.dataset.conversionCookie;
		if (getCookie(closingCookie) || getCookie(conversionCookie)) {
			return;
		}
		const percent = Number(dialog.dataset.triggerPercent) || 50;
		let opened = false;
		let converted = false;

		function open() {
			if (opened || getCookie(closingCookie) || getCookie(conversionCookie)) {
				return;
			}
			opened = true;
			window.removeEventListener('scroll', onScroll);
			loadCss().then(function () {
				const slot = dialog.querySelector('[data-lc-popup-slot]');
				const tpl = dialog.querySelector('template[data-lc-popup-template]');
				if (slot && tpl && !slot.firstChild) {
					slot.appendChild(tpl.content.cloneNode(true));
				}
				if (document.querySelector('dialog[open]')) {
					return; // Don't stack on top of another modal.
				}
				dialog.showModal();
				const form = slot && slot.querySelector('form[data-lc-form]');
				if (form) {
					initForm(form, function (formId) {
						converted = true;
						markConversion(formId);
						// WP Popups closed the popup on conversion.
						setTimeout(function () { dialog.close(); }, 3000);
					});
					renderCaptcha(form);
				}
			});
		}

		function onScroll() {
			const doc = document.documentElement;
			const scrollable = doc.scrollHeight - window.innerHeight;
			if (scrollable > 0 && window.scrollY / scrollable >= percent / 100) {
				open();
			}
		}

		dialog.addEventListener('close', function () {
			if (!converted) {
				setCookie(closingCookie, Number(dialog.dataset.closingDays) || 7);
			}
		});
		// The box fills the dialog, so a click on the dialog itself is the backdrop.
		dialog.addEventListener('click', function (event) {
			if (event.target === dialog) {
				dialog.close();
			}
		});
		dialog.querySelectorAll('[data-lc-popup-close]').forEach(function (btn) {
			btn.addEventListener('click', function () { dialog.close(); });
		});

		window.addEventListener('scroll', onScroll, { passive: true });
	}

	function init() {
		document.querySelectorAll('form[data-lc-form]').forEach(function (form) {
			// Forms inside the popup's <template> are not in the document yet.
			initForm(form, markConversion);
		});
		const done = document.querySelector('.lc-form__confirmation[tabindex]');
		if (done && /[?&]lc_status=success/.test(location.search)) {
			done.focus();
			const id = (location.search.match(/[?&]lc_form=([a-z-]+)/) || [])[1];
			markConversion(id);
		}
		initPopup();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
