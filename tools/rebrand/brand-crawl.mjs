#!/usr/bin/env node
// Old-brand crawler for the Learn Cardano -> Learn Crypto rebrand.
//
//   node brand-crawl.mjs <base-url> <out.json> [--compare before.json] [--allowlist file.json]
//
// Node 22, no dependencies (global fetch). Reads <base>/sitemap_index.xml and
// every sub-sitemap, then:
// - page-sitemap URLs and the EXTRAS below: the whole document is checked;
//   every old-brand match that the allowlist does not cover is a FAIL.
// - the first 15 URLs of every other sitemap: only the site chrome is checked
//   (<head> meta, JSON-LD, site header/footer, .about-home, .delegation,
//   .lc-spreaker, #lc-popup-*). Matches elsewhere in the body are INFO.
// The og:image of each page is fetched once and its HTTP status, type, size
// and (JPEG/PNG/GIF/WebP) pixel dimensions recorded.
//
// Exit code: 1 if any FAIL, else 2 if any URL could not be fetched, else 0.
// Purge Cloudflare/SG caches before a verification run, or the crawl sees
// cached pages.

import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const BRAND_RE = /Learn\s*Cardano|Cardano Podcast|LearnCardano|CardanoPress/gi;
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36';
const CONCURRENCY = 4;
const TIMEOUT_MS = 15000;
const SAMPLE_PER_SITEMAP = 15;
const EXTRAS = [
	'/', '/podcasts/', '/resources/', '/courses/', '/kb/', '/delegate/', '/interview-booking/',
	'/?s=cardano', '/feed/', '/feed/?post_type=podcasts', '/llms.txt', '/account/panel/',
	'/category/learn-cardano-podcast/',
];
const EXTRAS_IF_EXISTS = ['/podcast-category/learn-cardano/'];

// ---- arguments -------------------------------------------------------------

const argv = process.argv.slice(2);
const pos = [];
let comparePath = '';
let allowPath = join(HERE, 'allowlist.json');
for (let i = 0; i < argv.length; i++) {
	if (argv[i] === '--compare') comparePath = argv[++i] || '';
	else if (argv[i] === '--allowlist') allowPath = argv[++i] || '';
	else pos.push(argv[i]);
}
if (pos.length !== 2 || !/^https?:\/\//.test(pos[0])) {
	console.error('Usage: node brand-crawl.mjs <base-url> <out.json> [--compare before.json] [--allowlist file.json]');
	process.exit(64);
}
const BASE = pos[0].replace(/\/+$/, '');
const OUT = pos[1];

const allow = loadAllowlist(allowPath);

function loadAllowlist(file) {
	let data;
	try {
		data = JSON.parse(readFileSync(file, 'utf8'));
	} catch (e) {
		console.error(`Cannot read allowlist ${file}: ${e.message}`);
		process.exit(64);
	}
	return (data.patterns || []).map((p) => ({
		id: p.id || p.re,
		re: new RegExp(p.re, (p.flags ?? 'i').replace(/g/g, '') + 'g'),
		window: Number.isInteger(p.window) ? p.window : 400,
		urls: p.urls ? new RegExp(p.urls, 'i') : null,
		why: p.why || '',
	}));
}

// ---- fetching ----------------------------------------------------------------

async function get(url, binary = false) {
	let lastErr;
	for (let attempt = 0; attempt < 2; attempt++) {
		try {
			const res = await fetch(url, {
				redirect: 'follow',
				headers: { 'User-Agent': UA, Accept: binary ? 'image/avif,image/webp,image/*,*/*;q=0.8' : 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' },
				signal: AbortSignal.timeout(TIMEOUT_MS),
			});
			const body = binary ? Buffer.from(await res.arrayBuffer()) : await res.text();
			return { ok: true, status: res.status, finalUrl: res.url, type: res.headers.get('content-type') || '', body };
		} catch (e) {
			lastErr = e;
		}
	}
	return { ok: false, status: 0, finalUrl: url, type: '', body: binary ? Buffer.alloc(0) : '', error: String(lastErr && lastErr.message ? lastErr.message : lastErr) };
}

async function pool(items, fn) {
	const out = new Array(items.length);
	let next = 0;
	const workers = Array.from({ length: Math.min(CONCURRENCY, items.length) }, async () => {
		while (next < items.length) {
			const i = next++;
			out[i] = await fn(items[i], i);
		}
	});
	await Promise.all(workers);
	return out;
}

function locs(xml) {
	return [...xml.matchAll(/<loc>\s*(?:<!\[CDATA\[)?(.*?)(?:\]\]>)?\s*<\/loc>/gis)].map((m) => decodeEntities(m[1].trim()));
}

function decodeEntities(s) {
	return s.replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#0?39;/g, "'");
}

// ---- HTML regions ------------------------------------------------------------

function hasClass(attrs, cls) {
	const m = attrs.match(/\bclass\s*=\s*("([^"]*)"|'([^']*)')/i);
	if (!m) return false;
	return (m[2] ?? m[3] ?? '').split(/\s+/).includes(cls);
}

function attr(attrs, name) {
	const m = attrs.match(new RegExp(`\\b${name}\\s*=\\s*("([^"]*)"|'([^']*)'|([^\\s>]+))`, 'i'));
	return m ? (m[2] ?? m[3] ?? m[4] ?? '') : null;
}

// Spans [start, end) of elements whose opening tag passes test(tag, attrs), matched to their closing tag.
function elementSpans(html, test, label, max = 50) {
	const spans = [];
	const open = /<([a-zA-Z][a-zA-Z0-9-]*)\b([^>]*)>/g;
	let m;
	while ((m = open.exec(html)) && spans.length < max) {
		const tag = m[1].toLowerCase();
		if (!test(tag, m[2])) continue;
		const start = m.index;
		const re = new RegExp(`<(/?)${tag}\\b[^>]*>`, 'gi');
		re.lastIndex = open.lastIndex;
		let depth = 1;
		let end = html.length;
		let t;
		while ((t = re.exec(html))) {
			depth += t[1] ? -1 : 1;
			if (depth === 0) {
				end = re.lastIndex;
				break;
			}
		}
		spans.push({ label, start, end });
		open.lastIndex = Math.max(open.lastIndex, start + 1);
	}
	return spans;
}

function regionSpans(html) {
	const spans = [];
	const head = html.match(/<head\b[^>]*>[\s\S]*?<\/head>/i);
	if (head) spans.push({ label: 'head', start: head.index, end: head.index + head[0].length });
	for (const m of html.matchAll(/<script\b[^>]*type\s*=\s*["']application\/ld\+json["'][^>]*>[\s\S]*?<\/script>/gi)) {
		spans.push({ label: 'jsonld', start: m.index, end: m.index + m[0].length });
	}
	spans.push(...elementSpans(html, (t, a) => t === 'header' && hasClass(a, 'site-header'), 'header', 1));
	spans.push(...elementSpans(html, (t, a) => t === 'footer' && hasClass(a, 'site-footer'), 'footer', 1));
	spans.push(...elementSpans(html, (t, a) => hasClass(a, 'about-home'), '.about-home'));
	spans.push(...elementSpans(html, (t, a) => hasClass(a, 'delegation'), '.delegation'));
	spans.push(...elementSpans(html, (t, a) => hasClass(a, 'lc-spreaker'), '.lc-spreaker'));
	spans.push(...elementSpans(html, (t, a) => /^lc-popup-/.test(attr(a, 'id') || ''), '#lc-popup'));
	return spans;
}

function regionAt(spans, offset) {
	let best = null;
	for (const s of spans) {
		if (offset >= s.start && offset < s.end && (!best || s.end - s.start < best.end - best.start)) best = s;
	}
	return best ? best.label : 'body';
}

// ---- head meta and JSON-LD ---------------------------------------------------

function headInfo(html) {
	const info = { title: null, ogSiteName: null, ogTitle: null, ogImage: null, twitter: {}, jsonldNames: [] };
	const head = (html.match(/<head\b[^>]*>([\s\S]*?)<\/head>/i) || [])[1] || '';
	const t = head.match(/<title\b[^>]*>([\s\S]*?)<\/title>/i);
	if (t) info.title = decodeEntities(t[1].trim());
	for (const m of head.matchAll(/<meta\b([^>]*)>/gi)) {
		const key = attr(m[1], 'property') || attr(m[1], 'name') || '';
		const val = attr(m[1], 'content');
		if (val === null) continue;
		if (key === 'og:site_name') info.ogSiteName = decodeEntities(val);
		else if (key === 'og:title') info.ogTitle = decodeEntities(val);
		else if (key === 'og:image' && !info.ogImage) info.ogImage = decodeEntities(val);
		else if (key.startsWith('twitter:')) info.twitter[key] = decodeEntities(val);
	}
	for (const m of html.matchAll(/<script\b[^>]*type\s*=\s*["']application\/ld\+json["'][^>]*>([\s\S]*?)<\/script>/gi)) {
		try {
			walkLd(JSON.parse(m[1]), info.jsonldNames, '');
		} catch {
			info.jsonldNames.push({ path: '(invalid JSON-LD)', type: '', name: '' });
		}
	}
	return info;
}

const LD_TYPES = /^(Organization|WebSite|PodcastSeries|NewsMediaOrganization|Corporation|Person)$/;

function walkLd(node, out, path) {
	if (Array.isArray(node)) {
		node.forEach((n, i) => walkLd(n, out, `${path}[${i}]`));
		return;
	}
	if (!node || typeof node !== 'object') return;
	const types = [].concat(node['@type'] || []);
	const isPublisher = /publisher|author|partOfSeries|isPartOf/.test(path.split('.').pop() || '');
	if ((types.some((t) => LD_TYPES.test(t)) || isPublisher) && (node.name || node.alternateName)) {
		out.push({ path: path || '(root)', type: types.join(','), name: node.name || '', alternateName: node.alternateName || '' });
	}
	for (const [k, v] of Object.entries(node)) {
		if (v && typeof v === 'object') walkLd(v, out, path ? `${path}.${k}` : k);
	}
}

// ---- matching ----------------------------------------------------------------

function allowedBy(doc, pos, len, url) {
	for (const p of allow) {
		if (p.urls && !p.urls.test(url)) continue;
		// window 0 = search the whole document (for patterns spanning whole elements).
		const a = p.window ? Math.max(0, pos - p.window) : 0;
		const b = p.window ? Math.min(doc.length, pos + len + p.window) : doc.length;
		const win = doc.slice(a, b);
		p.re.lastIndex = 0;
		for (const m of win.matchAll(p.re)) {
			const s = a + m.index;
			const e = s + m[0].length;
			if (s <= pos && e >= pos + len) return p.id;
			if (m[0].length === 0) break;
		}
	}
	return null;
}

function snippet(doc, pos, len) {
	const a = Math.max(0, pos - 45);
	const b = Math.min(doc.length, pos + len + 45);
	return (a > 0 ? '...' : '') + doc.slice(a, b).replace(/\s+/g, ' ').trim() + (b < doc.length ? '...' : '');
}

function check(doc, url, full, isHtml) {
	const norm = doc.replace(/&nbsp;|&#160;|&#xa0;/gi, ' ');
	const spans = isHtml ? regionSpans(norm) : [];
	const hits = [];
	const counts = { fail: 0, allowed: 0, info: 0 };
	for (const m of norm.matchAll(BRAND_RE)) {
		const region = isHtml ? regionAt(spans, m.index) : 'document';
		const by = allowedBy(norm, m.index, m[0].length, url);
		let severity;
		if (by) severity = 'allowed';
		else if (full || region !== 'body') severity = 'FAIL';
		else severity = 'INFO';
		counts[severity === 'FAIL' ? 'fail' : severity === 'INFO' ? 'info' : 'allowed']++;
		hits.push({ region, severity, match: m[0], snippet: snippet(norm, m.index, m[0].length), ...(by ? { allowedBy: by } : {}) });
	}
	return { counts, hits };
}

// ---- image dimensions --------------------------------------------------------

function imageInfo(buf) {
	if (buf.length >= 24 && buf.readUInt32BE(0) === 0x89504e47) {
		return { format: 'png', width: buf.readUInt32BE(16), height: buf.readUInt32BE(20) };
	}
	if (buf.length >= 10 && buf.toString('ascii', 0, 3) === 'GIF') {
		return { format: 'gif', width: buf.readUInt16LE(6), height: buf.readUInt16LE(8) };
	}
	if (buf.length >= 30 && buf.toString('ascii', 0, 4) === 'RIFF' && buf.toString('ascii', 8, 12) === 'WEBP') {
		const chunk = buf.toString('ascii', 12, 16);
		if (chunk === 'VP8X') return { format: 'webp', width: 1 + buf.readUIntLE(24, 3), height: 1 + buf.readUIntLE(27, 3) };
		if (chunk === 'VP8 ') return { format: 'webp', width: buf.readUInt16LE(26) & 0x3fff, height: buf.readUInt16LE(28) & 0x3fff };
		if (chunk === 'VP8L') {
			const bits = buf.readUInt32LE(21);
			return { format: 'webp', width: (bits & 0x3fff) + 1, height: ((bits >> 14) & 0x3fff) + 1 };
		}
		return { format: 'webp', width: null, height: null };
	}
	if (buf.length >= 4 && buf[0] === 0xff && buf[1] === 0xd8) {
		let i = 2;
		while (i + 9 < buf.length) {
			if (buf[i] !== 0xff) {
				i++;
				continue;
			}
			const marker = buf[i + 1];
			if (marker === 0xd8 || marker === 0x01 || (marker >= 0xd0 && marker <= 0xd7) || marker === 0xff) {
				i += marker === 0xff ? 1 : 2;
				continue;
			}
			const size = buf.readUInt16BE(i + 2);
			// SOF0..SOF15 except DHT (C4), JPG (C8), DAC (CC).
			if (marker >= 0xc0 && marker <= 0xcf && ![0xc4, 0xc8, 0xcc].includes(marker)) {
				return { format: 'jpeg', width: buf.readUInt16BE(i + 7), height: buf.readUInt16BE(i + 5) };
			}
			i += 2 + size;
		}
		return { format: 'jpeg', width: null, height: null };
	}
	return { format: null, width: null, height: null };
}

const ogCache = new Map();
function ogImage(url) {
	if (!ogCache.has(url)) {
		ogCache.set(url, (async () => {
			const r = await get(url, true);
			const info = r.ok ? imageInfo(r.body) : { format: null, width: null, height: null };
			return { status: r.status, finalUrl: r.finalUrl, type: r.type, bytes: r.body.length, ...info, ...(r.error ? { error: r.error } : {}) };
		})());
	}
	return ogCache.get(url);
}

// ---- main ----------------------------------------------------------------------

async function main() {
	const started = new Date().toISOString();
	const targets = new Map(); // url -> { kind, source }
	const add = (url, kind, source) => {
		const prev = targets.get(url);
		if (!prev || (prev.kind === 'chrome' && kind === 'full')) targets.set(url, { kind, source });
	};

	const index = await get(`${BASE}/sitemap_index.xml`);
	const sitemaps = [];
	if (!index.ok || index.status !== 200) {
		console.error(`sitemap_index.xml: HTTP ${index.status} ${index.error || ''}`);
	} else if (/<sitemapindex/i.test(index.body)) {
		sitemaps.push(...locs(index.body));
	} else {
		sitemaps.push(`${BASE}/sitemap_index.xml`);
	}
	const subs = await pool(sitemaps, async (sm) => ({ sm, res: sm === `${BASE}/sitemap_index.xml` ? index : await get(sm) }));
	const sitemapReport = [];
	for (const { sm, res } of subs) {
		const urls = res.ok && res.status === 200 ? locs(res.body) : [];
		const name = sm.split('/').pop();
		const isPage = /^page-sitemap\d*\.xml$/i.test(name);
		sitemapReport.push({ sitemap: sm, status: res.status, urls: urls.length, mode: isPage ? 'full' : `chrome (first ${SAMPLE_PER_SITEMAP})` });
		(isPage ? urls : urls.slice(0, SAMPLE_PER_SITEMAP)).forEach((u) => add(u, isPage ? 'full' : 'chrome', name));
	}
	for (const p of EXTRAS) add(BASE + p, 'full', 'extra');

	const optional = await pool(EXTRAS_IF_EXISTS, async (p) => ({ p, res: await get(BASE + p) }));
	const skipped = [];
	for (const { p, res } of optional) {
		if (res.ok && res.status === 200) add(BASE + p, 'full', 'extra-if-exists');
		else skipped.push({ url: BASE + p, status: res.status });
	}

	const list = [...targets.entries()];
	console.log(`Crawling ${list.length} URLs from ${BASE} (${sitemaps.length} sitemaps, ${CONCURRENCY} at a time)...`);

	const pages = await pool(list, async ([url, t]) => {
		const r = await get(url);
		const page = { url, kind: t.kind, source: t.source, status: r.status, finalUrl: r.finalUrl, contentType: r.type };
		if (!r.ok) {
			page.error = r.error;
			return page;
		}
		const isHtml = /html/i.test(r.type) || /^\s*<!doctype html|<html[\s>]/i.test(r.body.slice(0, 500));
		if (isHtml) {
			const h = headInfo(r.body);
			Object.assign(page, h);
			if (h.ogImage) page.ogImageInfo = await ogImage(new URL(h.ogImage, url).href);
		}
		const c = check(r.body, url, t.kind === 'full', isHtml);
		page.counts = c.counts;
		page.hits = c.hits.filter((h) => h.severity !== 'allowed');
		page.allowedSample = c.hits.filter((h) => h.severity === 'allowed').slice(0, 10);
		return page;
	});

	// ---- report ----------------------------------------------------------------
	let fails = 0;
	let infos = 0;
	let errors = 0;
	for (const p of pages) {
		if (p.error || p.status >= 400) errors++;
		const c = p.counts || { fail: 0, allowed: 0, info: 0 };
		fails += c.fail;
		infos += c.info;
		const flag = c.fail ? 'FAIL' : p.error || p.status >= 400 ? 'ERR ' : 'ok  ';
		console.log(`${flag} ${String(p.status).padEnd(3)} [${p.kind}] ${p.url}  fail=${c.fail} info=${c.info} allowed=${c.allowed}${p.error ? '  ' + p.error : ''}`);
		for (const h of p.hits || []) {
			if (h.severity === 'FAIL') console.log(`       FAIL ${h.region}: "${h.snippet}"`);
		}
		if (p.kind === 'chrome' && c.info) console.log(`       INFO ${c.info} body matches (historical content; not failing)`);
	}

	const og = {};
	for (const [u, pr] of ogCache) og[u] = await pr;
	console.log('\nog:image');
	for (const [u, i] of Object.entries(og)) {
		console.log(`  ${i.status} ${i.format || '?'} ${i.width ?? '?'}x${i.height ?? '?'} ${i.bytes} bytes  ${u}`);
	}

	const titles = {};
	for (const p of pages) {
		if (p.ogSiteName) titles[`og:site_name = ${p.ogSiteName}`] = (titles[`og:site_name = ${p.ogSiteName}`] || 0) + 1;
		for (const n of p.jsonldNames || []) {
			const k = `JSON-LD ${n.type || n.path}: ${n.name}`;
			titles[k] = (titles[k] || 0) + 1;
		}
	}
	console.log('\nBrand names seen in meta/JSON-LD (pages):');
	for (const [k, n] of Object.entries(titles).sort((a, b) => b[1] - a[1])) console.log(`  ${String(n).padStart(4)}  ${k}`);

	const result = {
		tool: 'tools/rebrand/brand-crawl.mjs',
		base: BASE,
		started,
		finished: new Date().toISOString(),
		userAgent: UA,
		allowlist: allowPath,
		sitemaps: sitemapReport,
		skippedOptional: skipped,
		summary: { urls: pages.length, fail: fails, info: infos, errors },
		ogImages: og,
		pages,
	};

	if (comparePath) {
		result.compare = compare(JSON.parse(readFileSync(comparePath, 'utf8')), result);
		const cmp = result.compare;
		console.log(`\nCompared with ${comparePath}: FAIL ${cmp.before.fail} -> ${cmp.after.fail}, INFO ${cmp.before.info} -> ${cmp.after.info}`);
		for (const d of cmp.pages) {
			if (d.newFails.length || d.fixed || d.ogImageChanged) {
				console.log(`  ${d.url}: fail ${d.before} -> ${d.after}${d.ogImageChanged ? `, og:image ${d.ogImageBefore} -> ${d.ogImageAfter}` : ''}`);
				for (const s of d.newFails) console.log(`     NEW "${s}"`);
			}
		}
		for (const u of cmp.missing) console.log(`  not crawled this time: ${u}`);
	}

	writeFileSync(OUT, JSON.stringify(result, null, 2));
	console.log(`\n${pages.length} URLs, ${fails} FAIL, ${infos} INFO, ${errors} errors. Written: ${OUT}`);
	process.exit(fails ? 1 : errors ? 2 : 0);
}

function compare(before, after) {
	const map = new Map((before.pages || []).map((p) => [p.url, p]));
	const pages = [];
	for (const p of after.pages) {
		const b = map.get(p.url);
		const bFails = new Set(((b && b.hits) || []).filter((h) => h.severity === 'FAIL').map((h) => h.snippet));
		const aFails = (p.hits || []).filter((h) => h.severity === 'FAIL').map((h) => h.snippet);
		const before_ = b && b.counts ? b.counts.fail : null;
		const after_ = p.counts ? p.counts.fail : null;
		pages.push({
			url: p.url,
			before: before_,
			after: after_,
			fixed: before_ !== null && after_ !== null && after_ < before_,
			newFails: aFails.filter((s) => !bFails.has(s)),
			ogImageChanged: !!b && (b.ogImage || null) !== (p.ogImage || null),
			ogImageBefore: b ? b.ogImage || null : null,
			ogImageAfter: p.ogImage || null,
		});
		map.delete(p.url);
	}
	return {
		before: before.summary || {},
		after: after.summary,
		pages,
		missing: [...map.keys()],
	};
}

main().catch((e) => {
	console.error(e);
	process.exit(3);
});
