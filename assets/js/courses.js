/**
 * Courses: browser-only progress, "Mark as done" buttons and the quiz app.
 *
 * There is no login and nothing is written to the server: progress and quiz
 * scores live in localStorage under `lc-course-progress` ({"<id>": ms}) and
 * `lc-quiz-scores` ({"<id>": {best, passed, at}}). Storage can be blocked
 * (privacy modes, sandboxed frames), so every access is guarded and state
 * falls back to memory for the current page.
 *
 * Markup contract: lc_course_render_outline() and the course templates
 * (includes/courses.php, template-parts/course-step.php, course-card.php).
 * Strings come from wp_localize_script( 'lc-courses', 'lcCourses' ).
 *
 * @package CardanoPress Bootstrap
 */
(function () {
	'use strict';

	const PROGRESS_KEY = 'lc-course-progress';
	const SCORES_KEY = 'lc-quiz-scores';

	// English fallbacks; the localized copies win when present.
	const DEFAULT_STRINGS = {
		start: 'Start course',
		continue: 'Continue course',
		review: 'Review course',
		markDone: 'Mark as done',
		done: 'Done',
		stepsDone: '%1$s of %2$s steps done',
		startQuiz: 'Start quiz',
		questionOf: 'Question %1$s of %2$s',
		check: 'Check answer',
		next: 'Next question',
		seeResults: 'See results',
		correct: 'Correct',
		incorrect: 'Incorrect',
		selectOne: 'Select one answer',
		selectAll: 'Select all that apply',
		chooseAnswer: 'Choose an answer first',
		yourScore: 'You scored %s%%',
		passed: 'You passed. Well done!',
		notPassed: 'Not quite. You need %s%% to pass.',
		retry: 'Try again',
		bestScore: 'Your best: %s%%',
		passMark: 'Pass mark %s%%',
		questions: '%s questions',
		reviewAnswers: 'Review your answers',
		yourAnswer: 'Your answer',
		correctAnswer: 'Correct answer',
	};

	/* ------------------------------------------------------------------
	 * Pure helpers (no DOM, no storage).
	 * ------------------------------------------------------------------ */

	/**
	 * Minimal sprintf for the localized strings: %s, %d, %1$s and %% only,
	 * because the PHP side passes the raw gettext templates through.
	 */
	function format(template, ...args) {
		let next = 0;
		return String(template).replace(/%(?:(\d+)\$)?([sd%])/g, (match, position, type) => {
			if ('%' === type) {
				return position ? match : '%';
			}
			const value = position ? args[Number(position) - 1] : args[next++];
			return undefined === value || null === value ? '' : String(value);
		});
	}

	function isPlainObject(value) {
		return !!value && 'object' === typeof value && !Array.isArray(value);
	}

	// Post IDs only: digits, no zero, no duplicates, order kept.
	function cleanId(value) {
		const id = String(undefined === value || null === value ? '' : value).trim();
		if (!/^\d+$/.test(id)) {
			return '';
		}
		const normalised = String(parseInt(id, 10));
		return '0' === normalised ? '' : normalised;
	}

	function parseIds(value) {
		const ids = [];
		String(value || '').split(',').forEach((part) => {
			const id = cleanId(part);
			if (id && -1 === ids.indexOf(id)) {
				ids.push(id);
			}
		});
		return ids;
	}

	function isDone(progress, id) {
		return !!id && isPlainObject(progress) && Object.prototype.hasOwnProperty.call(progress, id) && !!progress[id];
	}

	// 0 only when nothing is done and 100 only when everything is, so a bar
	// never looks finished (or empty) when it is not.
	function percentOf(done, total) {
		if (!total || done <= 0) {
			return 0;
		}
		if (done >= total) {
			return 100;
		}
		return Math.min(99, Math.max(1, Math.round((done / total) * 100)));
	}

	/**
	 * Progress over an ordered step list.
	 *
	 * @return {{total:number, done:number, percent:number, firstOpen:string, state:string}}
	 *         state is 'none', 'some' or 'all'.
	 */
	function summarise(ids, progress) {
		let done = 0;
		let firstOpen = '';
		ids.forEach((id) => {
			if (isDone(progress, id)) {
				done++;
			} else if (!firstOpen) {
				firstOpen = id;
			}
		});
		const total = ids.length;
		let state = 'some';
		if (0 === done) {
			state = 'none';
		} else if (done >= total) {
			state = 'all';
		}
		return { total, done, percent: percentOf(done, total), firstOpen, state };
	}

	function uniqueSorted(list) {
		return list
			.map(Number)
			.filter((value, index, all) => all.indexOf(value) === index)
			.sort((a, b) => a - b);
	}

	function sameSet(a, b) {
		const x = uniqueSorted(a);
		const y = uniqueSorted(b);
		return x.length === y.length && x.every((value, index) => value === y[index]);
	}

	function correctIndices(question) {
		const indices = [];
		question.answers.forEach((answer, index) => {
			if (answer.correct) {
				indices.push(index);
			}
		});
		return indices;
	}

	// Right only when the chosen set equals the correct set exactly.
	function isRight(question, chosen) {
		return chosen.length > 0 && sameSet(chosen, correctIndices(question));
	}

	function scoreOf(right, total) {
		return total > 0 ? Math.round((100 * right) / total) : 0;
	}

	// The message with the highest `min` the score reached, or null.
	function pickResult(results, score) {
		let match = null;
		(results || []).forEach((result) => {
			if (result.min <= score && (!match || result.min >= match.min)) {
				match = result;
			}
		});
		return match;
	}

	// Fisher-Yates on a copy; `random` is injectable for tests.
	function shuffle(list, random) {
		const rand = 'function' === typeof random ? random : Math.random;
		const out = list.slice();
		for (let i = out.length - 1; i > 0; i--) {
			const j = Math.min(i, Math.floor(rand() * (i + 1)));
			const swap = out[i];
			out[i] = out[j];
			out[j] = swap;
		}
		return out;
	}

	function clamp(value, min, max) {
		return Math.min(max, Math.max(min, value));
	}

	function toText(value) {
		return 'string' === typeof value ? value : '';
	}

	/**
	 * Defensive copy of the lc_quiz_data() JSON. Answerless questions are
	 * dropped, and a "single" question with several correct answers is
	 * treated as "multiple" because a radio group could never get it right.
	 */
	function normaliseQuiz(raw, fallbackId) {
		const data = isPlainObject(raw) ? raw : {};
		const pass = parseInt(data.passMark, 10);
		const questions = (Array.isArray(data.questions) ? data.questions : [])
			.map((item) => {
				const question = isPlainObject(item) ? item : {};
				const answers = (Array.isArray(question.answers) ? question.answers : [])
					.filter(isPlainObject)
					.map((answer) => ({
						text: undefined === answer.text || null === answer.text ? '' : String(answer.text),
						correct: true === answer.correct || 1 === answer.correct || '1' === answer.correct,
					}))
					.filter((answer) => '' !== answer.text.trim());
				const correctCount = answers.filter((answer) => answer.correct).length;
				return {
					html: toText(question.html),
					type: 'multiple' === question.type || correctCount > 1 ? 'multiple' : 'single',
					answers,
					explainCorrect: toText(question.explainCorrect),
					explainIncorrect: toText(question.explainIncorrect),
				};
			})
			.filter((question) => question.answers.length > 0);
		const results = (Array.isArray(data.results) ? data.results : [])
			.filter(isPlainObject)
			.map((result) => ({
				min: clamp(parseInt(result.min, 10) || 0, 0, 100),
				html: toText(result.html),
			}))
			.sort((a, b) => a.min - b.min);
		return {
			id: cleanId(fallbackId) || cleanId(data.id),
			passMark: isNaN(pass) ? 80 : clamp(pass, 0, 100),
			randomise: true === data.randomise || 1 === data.randomise || '1' === data.randomise,
			questions,
			results,
		};
	}

	// Keep the best score ever and remember a pass even after a worse retry.
	function mergeScore(previous, score, passed, now) {
		const before = isPlainObject(previous) ? previous : {};
		const best = Number(before.best);
		return {
			best: Math.max(isFinite(best) ? best : 0, score),
			passed: true === before.passed || !!passed,
			at: now,
		};
	}

	const api = {
		format,
		cleanId,
		parseIds,
		isDone,
		percentOf,
		summarise,
		sameSet,
		correctIndices,
		isRight,
		scoreOf,
		pickResult,
		shuffle,
		normaliseQuiz,
		mergeScore,
	};

	// Lets Node checks require() the pure helpers; browsers have no `module`,
	// so nothing is exposed on the page.
	if ('object' === typeof module && module && module.exports) {
		module.exports = api;
	}

	if ('undefined' === typeof window || 'undefined' === typeof document) {
		return;
	}

	/* ------------------------------------------------------------------
	 * Strings and storage.
	 * ------------------------------------------------------------------ */

	const S = Object.assign({}, DEFAULT_STRINGS);
	const localized = window.lcCourses && window.lcCourses.strings;
	if (isPlainObject(localized)) {
		Object.keys(DEFAULT_STRINGS).forEach((key) => {
			if ('string' === typeof localized[key] && '' !== localized[key]) {
				S[key] = localized[key];
			}
		});
	}

	let storageOk = true;
	const memory = {};

	// Re-read on every call so other tabs' changes are picked up; when
	// storage is unavailable the in-memory copy is the source of truth.
	function readStore(key) {
		if (storageOk) {
			let raw = null;
			try {
				raw = window.localStorage.getItem(key);
			} catch (error) {
				storageOk = false;
			}
			if (storageOk) {
				let parsed = null;
				try {
					parsed = raw ? JSON.parse(raw) : null;
				} catch (error) {
					parsed = null; // Corrupt value: start again rather than break the page.
				}
				memory[key] = isPlainObject(parsed) ? parsed : {};
			}
		}
		if (!isPlainObject(memory[key])) {
			memory[key] = {};
		}
		return memory[key];
	}

	function writeStore(key, value) {
		memory[key] = value;
		if (!storageOk) {
			return;
		}
		try {
			window.localStorage.setItem(key, JSON.stringify(value));
		} catch (error) {
			storageOk = false; // Quota or privacy mode: keep going in memory.
		}
	}

	function setDone(id, done) {
		const progress = readStore(PROGRESS_KEY);
		if (done && !isDone(progress, id)) {
			progress[id] = Date.now();
		} else if (!done) {
			delete progress[id];
		}
		writeStore(PROGRESS_KEY, progress);
	}

	/* ------------------------------------------------------------------
	 * Progress indicators.
	 * ------------------------------------------------------------------ */

	function el(tag, className, text) {
		const node = document.createElement(tag);
		if (className) {
			node.className = className;
		}
		if (undefined !== text) {
			node.textContent = text;
		}
		return node;
	}

	// The step's own link: the element itself, a direct child link, or failing
	// that its first link.
	function stepLink(item) {
		if ('A' === item.tagName) {
			return item;
		}
		const direct = Array.prototype.find.call(item.children, (child) => 'A' === child.tagName || child.classList.contains('lcc__link'));
		return direct || item.querySelector('a[href]');
	}

	function stepHref(id) {
		const scoped = Array.from(document.querySelectorAll('[data-lcc-outline] [data-lcc-step="' + id + '"]'));
		const anywhere = Array.from(document.querySelectorAll('[data-lcc-step="' + id + '"]'));
		const candidates = scoped.concat(anywhere);
		for (let i = 0; i < candidates.length; i++) {
			const link = stepLink(candidates[i]);
			const href = link ? link.getAttribute('href') : '';
			if (href) {
				return href;
			}
		}
		return '';
	}

	// The tick in .lcc__check is decorative (aria-hidden), so screen readers
	// get the same state as hidden text inside the link.
	function setSrDone(item, done) {
		const link = stepLink(item);
		if (!link) {
			return;
		}
		const existing = link.querySelector('.lcc__sr-done');
		if (done && !existing) {
			link.appendChild(el('span', 'lcc__sr-done visually-hidden', ' (' + S.done + ')'));
		} else if (!done && existing) {
			existing.remove();
		}
	}

	function updateContinue(button, ids, summary, progress) {
		if (!button.hasAttribute('data-lcc-href')) {
			button.setAttribute('data-lcc-href', button.getAttribute('href') || '');
		}
		const original = button.getAttribute('data-lcc-href');
		let label = S.start;
		let href = original;
		if ('some' === summary.state) {
			label = S.continue;
			for (let i = 0; i < ids.length; i++) {
				if (!isDone(progress, ids[i])) {
					const found = stepHref(ids[i]);
					if (found) {
						href = found;
						break;
					}
				}
			}
		} else if ('all' === summary.state) {
			label = S.review;
			href = stepHref(ids[0]) || original;
		}
		button.textContent = label;
		if (href) {
			button.setAttribute('href', href);
		}
	}

	function refresh() {
		const progress = readStore(PROGRESS_KEY);

		document.querySelectorAll('[data-lcc-step]').forEach((item) => {
			const done = isDone(progress, cleanId(item.getAttribute('data-lcc-step')));
			item.classList.toggle('is-done', done);
			setSrDone(item, done);
		});

		document.querySelectorAll('[data-lcc-progress]').forEach((box) => {
			const ids = parseIds(box.getAttribute('data-lcc-steps'));
			const summary = summarise(ids, progress);
			const text = box.querySelector('.lcc__progress-text');
			if (text) {
				text.textContent = format(S.stepsDone, summary.done, summary.total);
			}
			const bar = box.querySelector('.lcc__progress-bar > span');
			if (bar) {
				bar.style.width = summary.percent + '%';
			}
			box.classList.toggle('has-progress', summary.done > 0);
			box.classList.toggle('is-complete', 'all' === summary.state && summary.total > 0);
			const button = box.querySelector('[data-lcc-continue]');
			if (button) {
				updateContinue(button, ids, summary, progress);
			}
		});

		// Course cards (the progress box also carries data-lcc-steps).
		document.querySelectorAll('[data-lcc-steps]').forEach((card) => {
			if (card.hasAttribute('data-lcc-progress')) {
				return;
			}
			const out = card.querySelector('.lcc__card-progress');
			if (!out) {
				return;
			}
			const summary = summarise(parseIds(card.getAttribute('data-lcc-steps')), progress);
			out.textContent = summary.done > 0 ? format(S.stepsDone, summary.done, summary.total) : '';
			card.classList.toggle('has-progress', summary.done > 0);
		});

		document.querySelectorAll('[data-lcc-mark]').forEach((button) => {
			const done = isDone(progress, cleanId(button.getAttribute('data-lcc-mark')));
			button.setAttribute('aria-pressed', done ? 'true' : 'false');
			button.classList.toggle('is-done', done);
			button.textContent = done ? S.done : S.markDone;
			// A toggle keeps one name ("Mark as done, pressed"); the visible
			// "Done" is part of that name, so voice control still matches it.
			button.setAttribute('aria-label', S.markDone);
		});
	}

	function onClick(event) {
		const target = event.target;
		const button = target && target.closest ? target.closest('[data-lcc-mark]') : null;
		if (!button) {
			return;
		}
		const id = cleanId(button.getAttribute('data-lcc-mark'));
		if (!id) {
			return;
		}
		event.preventDefault();
		setDone(id, !isDone(readStore(PROGRESS_KEY), id));
		refresh();
	}

	/* ------------------------------------------------------------------
	 * Quiz app.
	 * ------------------------------------------------------------------ */

	let quizCount = 0;

	// Plain text from server-sanitised HTML, parsed in an inert document so
	// nothing loads or runs.
	function plainText(html) {
		try {
			const doc = new DOMParser().parseFromString(html, 'text/html');
			return (doc.body.textContent || '').replace(/\s+/g, ' ').trim();
		} catch (error) {
			return '';
		}
	}

	function makeButton(label, type) {
		const button = el('button', 'lcc__btn', label);
		button.type = type || 'button';
		return button;
	}

	function answerLine(className, label, texts) {
		const line = el('p', className);
		line.appendChild(el('strong', '', label + ':'));
		line.appendChild(document.createTextNode(' ' + (texts.length ? texts.join('; ') : 'n/a')));
		return line;
	}

	function initQuiz(root) {
		if (root.hasAttribute('data-lcc-ready')) {
			return;
		}
		const app = root.querySelector('.lcc__quiz-app');
		const source = root.querySelector('.lcc__quiz-data');
		if (!app || !source) {
			return;
		}
		let raw = null;
		try {
			raw = JSON.parse(source.textContent);
		} catch (error) {
			return; // Leave the container empty rather than show a broken quiz.
		}
		const quiz = normaliseQuiz(raw, root.getAttribute('data-lcc-quiz'));
		if (!quiz.questions.length) {
			return;
		}
		root.setAttribute('data-lcc-ready', '');
		const uid = 'lcc-quiz-' + (quiz.id || '0') + '-' + ++quizCount;
		const total = quiz.questions.length;
		let attempt = null;

		function storedRecord() {
			if (!quiz.id) {
				return null;
			}
			const record = readStore(SCORES_KEY)[quiz.id];
			return isPlainObject(record) && isFinite(Number(record.best)) ? record : null;
		}

		// Swap screens; focus follows so keyboard and screen reader users
		// land on the new content (not on first render, to leave page load alone).
		function render(screen, focusTarget) {
			app.textContent = '';
			app.appendChild(screen);
			if (focusTarget) {
				focusTarget.focus();
			}
		}

		function showStart() {
			const screen = el('div', 'lcc__quiz-start');
			screen.appendChild(el('p', 'lcc__quiz-meta', format(S.questions, total) + ' · ' + format(S.passMark, quiz.passMark)));
			const record = storedRecord();
			if (record) {
				screen.appendChild(el('p', 'lcc__quiz-best', format(S.bestScore, Number(record.best))));
				screen.classList.toggle('is-passed', true === record.passed);
			}
			const start = makeButton(S.startQuiz);
			start.addEventListener('click', startAttempt);
			screen.appendChild(start);
			render(screen, null);
		}

		function startAttempt() {
			const order = quiz.questions.map((question, index) => index);
			attempt = {
				order: quiz.randomise ? shuffle(order) : order,
				position: 0,
				answers: [],
			};
			showQuestion();
		}

		function showQuestion() {
			const position = attempt.position;
			const index = attempt.order[position];
			const question = quiz.questions[index];
			const multiple = 'multiple' === question.type;
			let checked = false;

			const form = el('form', 'lcc__quiz-question');
			form.noValidate = true;

			const head = el('div', 'lcc__quiz-head');
			const count = el('p', 'lcc__quiz-count', format(S.questionOf, position + 1, total));
			// Screen readers get the count inside the legend, which takes focus.
			count.setAttribute('aria-hidden', 'true');
			head.appendChild(count);
			const bar = el('div', 'lcc__quiz-bar');
			bar.setAttribute('aria-hidden', 'true');
			const fill = el('span');
			fill.style.width = percentOf(position, total) + '%';
			bar.appendChild(fill);
			head.appendChild(bar);
			form.appendChild(head);

			const fieldset = el('fieldset', 'lcc__q');
			const legend = el('legend', 'lcc__q-text');
			legend.tabIndex = -1;
			if (question.html.trim()) {
				legend.innerHTML = question.html; // Sanitised server-side (wp_kses_post).
				legend.insertBefore(el('span', 'lcc__sr', format(S.questionOf, position + 1, total) + ': '), legend.firstChild);
			} else {
				legend.textContent = format(S.questionOf, position + 1, total);
			}
			fieldset.appendChild(legend);

			const hint = el('p', 'lcc__q-hint' + (multiple ? ' lcc__q-hint--multiple' : ''), multiple ? S.selectAll : S.selectOne);
			hint.id = uid + '-hint-' + position;
			fieldset.setAttribute('aria-describedby', hint.id);
			fieldset.appendChild(hint);

			const name = 'lcc-q-' + (quiz.id || '0') + '-' + index;
			const inputs = question.answers.map((answer, answerIndex) => {
				const label = el('label', 'lcc__answer');
				const input = document.createElement('input');
				input.type = multiple ? 'checkbox' : 'radio';
				input.name = name;
				input.value = String(answerIndex);
				label.appendChild(input);
				label.appendChild(document.createTextNode(' '));
				label.appendChild(el('span', '', answer.text));
				fieldset.appendChild(label);
				return input;
			});
			form.appendChild(fieldset);

			const feedback = el('div', 'lcc__feedback');
			feedback.setAttribute('aria-live', 'polite');
			form.appendChild(feedback);

			const actions = el('div', 'lcc__quiz-actions');
			const action = makeButton(S.check, 'submit');
			actions.appendChild(action);
			form.appendChild(actions);

			// Clear the "choose an answer" nudge once they pick something.
			form.addEventListener('change', () => {
				if (!checked && feedback.classList.contains('is-notice')) {
					feedback.className = 'lcc__feedback';
					feedback.textContent = '';
				}
			});

			form.addEventListener('submit', (event) => {
				event.preventDefault();
				if (checked) {
					attempt.position++;
					if (attempt.position < total) {
						showQuestion();
					} else {
						showResults();
					}
					return;
				}

				const chosen = inputs.filter((input) => input.checked).map((input) => Number(input.value));
				if (!chosen.length) {
					// Empty first, then fill, so a repeat of the same text is announced again.
					feedback.className = 'lcc__feedback is-notice';
					feedback.textContent = '';
					window.setTimeout(() => {
						if (!checked) {
							feedback.textContent = S.chooseAnswer;
						}
					}, 60);
					return;
				}

				checked = true;
				const right = isRight(question, chosen);
				attempt.answers.push({ question: index, chosen, right });

				inputs.forEach((input) => {
					input.disabled = true;
				});
				question.answers.forEach((answer, answerIndex) => {
					const label = inputs[answerIndex].parentNode;
					const picked = -1 !== chosen.indexOf(answerIndex);
					label.classList.toggle('is-chosen', picked);
					label.classList.toggle('is-correct', answer.correct);
					label.classList.toggle('is-wrong', picked && !answer.correct);
					label.classList.toggle('is-missed', !picked && answer.correct);
					// Visible words as well as colour, so the outcome never relies on colour alone.
					if (answer.correct || picked) {
						label.appendChild(el('span', 'lcc__answer-tag', answer.correct ? S.correctAnswer : S.yourAnswer));
					}
				});
				fieldset.classList.add('is-answered');
				fill.style.width = percentOf(position + 1, total) + '%';

				feedback.className = 'lcc__feedback ' + (right ? 'is-correct' : 'is-wrong');
				feedback.textContent = '';
				const title = el('p', 'lcc__feedback-title');
				title.appendChild(el('strong', '', right ? S.correct : S.incorrect));
				feedback.appendChild(title);
				const explanation = right ? question.explainCorrect : question.explainIncorrect;
				if (explanation.trim()) {
					const box = el('div', 'lcc__explain');
					box.innerHTML = explanation; // Sanitised server-side (wp_kses_post).
					feedback.appendChild(box);
				}

				// Same button, new job: focus stays put and the live region speaks.
				action.textContent = attempt.position + 1 < total ? S.next : S.seeResults;
			});

			render(form, legend);
		}

		function showResults() {
			const right = attempt.answers.filter((entry) => entry.right).length;
			const score = scoreOf(right, total);
			const passed = score >= quiz.passMark;

			let record = null;
			if (quiz.id) {
				const scores = readStore(SCORES_KEY);
				record = mergeScore(scores[quiz.id], score, passed, Date.now());
				scores[quiz.id] = record;
				writeStore(SCORES_KEY, scores);
				if (passed) {
					setDone(quiz.id, true);
					refresh();
				}
			}

			const screen = el('div', 'lcc__result ' + (passed ? 'is-pass' : 'is-fail'));
			const heading = el('h2', 'lcc__score', format(S.yourScore, score));
			heading.tabIndex = -1;
			screen.appendChild(heading);
			screen.appendChild(el('p', 'lcc__verdict', passed ? S.passed : format(S.notPassed, quiz.passMark)));
			if (record && record.best > score) {
				screen.appendChild(el('p', 'lcc__quiz-best', format(S.bestScore, record.best)));
			}

			const message = pickResult(quiz.results, score);
			if (message && message.html.trim()) {
				const box = el('div', 'lcc__result-msg');
				box.innerHTML = message.html; // Sanitised server-side (wp_kses_post).
				screen.appendChild(box);
			}

			screen.appendChild(el('h3', 'lcc__review-title', S.reviewAnswers));
			const list = el('ol', 'lcc__review');
			attempt.answers.forEach((entry, position) => {
				const question = quiz.questions[entry.question];
				const item = el('li', 'lcc__review-item ' + (entry.right ? 'is-correct' : 'is-wrong'));
				const line = el('p', 'lcc__review-q');
				const mark = el('span', 'lcc__review-mark', entry.right ? '✓' : '✗');
				mark.setAttribute('role', 'img');
				mark.setAttribute('aria-label', entry.right ? S.correct : S.incorrect);
				line.appendChild(mark);
				line.appendChild(document.createTextNode(' ' + (plainText(question.html) || format(S.questionOf, position + 1, total))));
				item.appendChild(line);
				item.appendChild(answerLine('lcc__review-yours', S.yourAnswer, entry.chosen.map((i) => question.answers[i].text)));
				item.appendChild(answerLine('lcc__review-correct', S.correctAnswer, correctIndices(question).map((i) => question.answers[i].text)));
				list.appendChild(item);
			});
			screen.appendChild(list);

			const actions = el('div', 'lcc__quiz-actions');
			const retry = makeButton(S.retry);
			retry.addEventListener('click', startAttempt);
			actions.appendChild(retry);
			screen.appendChild(actions);

			render(screen, heading);
		}

		showStart();
	}

	/* ------------------------------------------------------------------
	 * Boot.
	 * ------------------------------------------------------------------ */

	// From 1200px the "In this course" panel scrolls inside its sticky box:
	// bring the current step into view (a third of the way down) unless the
	// visitor has already scrolled it. Never scrolls the page itself.
	function revealCurrentStep() {
		document.querySelectorAll('.lcc__panel').forEach((box) => {
			const current = box.querySelector('[aria-current="page"]');
			if (!current || box.scrollTop > 0 || box.scrollHeight <= box.clientHeight + 1) {
				return;
			}
			const offset = current.getBoundingClientRect().top - box.getBoundingClientRect().top;
			box.scrollTop = Math.max(0, offset - box.clientHeight / 3);
		});
	}

	function init() {
		refresh();
		document.querySelectorAll('[data-lcc-quiz]').forEach(initQuiz);
		revealCurrentStep();
	}

	document.addEventListener('click', onClick);

	// Another tab marked something done, or the page came back from the
	// back/forward cache with stale indicators.
	window.addEventListener('storage', (event) => {
		if (null === event.key || PROGRESS_KEY === event.key) {
			refresh();
		}
	});
	window.addEventListener('pageshow', (event) => {
		if (event.persisted) {
			refresh();
		}
	});

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
	// style.css (which caps the panel's height) is linked late in the page,
	// so measure the panel again once everything has loaded.
	if ('complete' !== document.readyState) {
		window.addEventListener('load', revealCurrentStep);
	}
})();
