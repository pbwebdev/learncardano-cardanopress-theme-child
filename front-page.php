<?php

/**
 * The template for displaying pages
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

get_header();

?>
    <main class="content pt-5 w-100">
		
<!--
  Learn Cardano — Parallax Tag Cloud Hero (v6)
  ---------------------------------------------
  v6 RESPONSIVE FIX:
  - Mobile (<600px): all pills hidden, just headline + sub on clean bg
  - Tablet (600-1024px): pills desaturated to 40% so they don't compete
    with content; fade-radius widened to 420px so headline area stays clear
  - Desktop unchanged

  v5 FIX: Avatar images never fetched because img.loading='lazy' on an
  element not yet in the DOM creates a deadlock — browser waits for
  visibility, but visibility requires DOM insertion, which only happens
  on onload, which only fires after fetch. Removed lazy attribute and
  switched to createElement + opacity fade-in for a cleaner reveal.

  v4 FIX: The per-char span split was breaking the gradient text-fill.
  background-clip: text only works on the element that has the gradient;
  child spans inherited transparent fill with no gradient to clip = invisible.

  Solution: gradient + clip stays on the inner .lc-headline-text span.
  Per-char split-up animation removed; whole headline now fades + slides up
  as one block. Less fancy, but actually visible.

  v3 features retained:
  - !important rules to defeat theme CSS
  - Solid color fallback (#9333EA)
  - Inner span isolates gradient from theme h1 rules

  v2 features retained:
  - Hover enlarge (1.12×)
  - Center fade-out (depth of field)
  - Wider depth range + stronger parallax
  - Gradient CTA pills
  - Self-hosted avatars
-->

<div class="lc-hero">
  <div class="lc-scene" id="lcScene">
    <div class="lc-center">
      <h1 class="lc-headline" id="lcHeadline"><span class="lc-headline-text">Cardano Podcast for You</span></h1>
      <p class="lc-sub">Keep up to date with core Cardano news, projects, development and ideas coming from the Cardano Community. For beginners to experts wanting to know more about Cardano &amp; blockchain technology.</p>
    </div>
  </div>
</div>

<style>
.lc-hero {
  position: relative;
  width: 100%;
  padding: 0;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", sans-serif;
}
.lc-hero .lc-scene {
  position: relative;
  width: 100%;
  height: 620px;
  overflow: hidden;
  background: #ffffff;
}
.lc-hero .lc-center {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 10;
  pointer-events: none;
  gap: 20px;
  padding: 0 24px;
  text-align: center;
}

/* H1 shell — neutralised, no gradient or text-fill */
.lc-hero .lc-center .lc-headline {
  margin: 0 !important;
  padding: 0 !important;
  border: 0 !important;
  background: transparent !important;
  display: block !important;
  visibility: visible !important;
  max-width: 800px;
  width: auto;
  line-height: 1.05;
  text-align: center;
  color: inherit !important;
  -webkit-text-fill-color: currentColor !important; /* reset any inherited transparent */
  /* fade-up animation on the whole heading */
  opacity: 0;
  transform: translateY(20px);
  transition: opacity 0.8s ease, transform 0.8s cubic-bezier(0.22, 1, 0.36, 1);
}
.lc-hero .lc-scene.is-in .lc-center .lc-headline,
.lc-hero .lc-center .lc-headline.force-show {
  opacity: 1 !important;
  transform: translateY(0) !important;
}

/* The styled text — gradient + clip live here and only here */
.lc-hero .lc-center .lc-headline .lc-headline-text {
  display: inline-block !important;
  visibility: visible !important;
  opacity: 1 !important;
  font-size: 64px !important;
  font-weight: 700 !important;
  letter-spacing: -2px !important;
  line-height: 1.05 !important;
  margin: 0 !important;
  padding: 0 !important;
  color: #9333EA !important; /* solid fallback */
  background: linear-gradient(90deg, #C026D3 0%, #9333EA 35%, #6366F1 65%, #2563EB 100%) !important;
  -webkit-background-clip: text !important;
  background-clip: text !important;
  -webkit-text-fill-color: transparent !important;
}

.lc-hero .lc-center .lc-sub {
  font-size: 16px;
  line-height: 1.6;
  color: #4B5563;
  margin: 0;
  max-width: 580px;
  opacity: 0;
  transform: translateY(20px);
  transition: opacity 0.8s ease 0.3s, transform 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.3s;
}
.lc-hero .lc-scene.is-in .lc-center .lc-sub {
  opacity: 1;
  transform: translateY(0);
}

/* Pills */
.lc-hero .lc-tag {
  position: absolute;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 7px 14px;
  background: #ffffff;
  border: 1px solid rgba(0,0,0,0.06);
  border-radius: 999px;
  font-size: 14px;
  font-weight: 500;
  color: #111827;
  text-decoration: none;
  white-space: nowrap;
  box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 4px 14px rgba(0,0,0,0.05);
  opacity: 0;
  will-change: transform, opacity;
  z-index: 5;
  cursor: pointer;
  transition: box-shadow 0.2s ease, border-color 0.2s ease, filter 0.2s ease;
}
.lc-hero .lc-tag.has-avatar { padding-left: 4px; }
.lc-hero .lc-tag:hover {
  border-color: rgba(147, 51, 234, 0.3);
  box-shadow: 0 4px 8px rgba(0,0,0,0.08), 0 12px 32px rgba(147, 51, 234, 0.18);
  z-index: 20 !important;
  color: #111827;
}

.lc-hero .lc-tag.is-cta {
  background: linear-gradient(90deg, #EC4899 0%, #C026D3 50%, #9333EA 100%);
  color: #ffffff;
  border-color: transparent;
  font-weight: 600;
  box-shadow: 0 1px 2px rgba(0,0,0,0.06), 0 6px 18px rgba(192, 38, 211, 0.25);
}
.lc-hero .lc-tag.is-cta:hover {
  color: #ffffff;
  border-color: transparent;
  filter: brightness(1.1);
  box-shadow: 0 4px 8px rgba(0,0,0,0.1), 0 14px 36px rgba(192, 38, 211, 0.45);
}

.lc-hero .lc-avatar {
  width: 28px;
  height: 28px;
  border-radius: 50%;
  background: #F3F4F6;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 11px;
  font-weight: 600;
  color: #6B7280;
  overflow: hidden;
  flex-shrink: 0;
}
.lc-hero .lc-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  display: block;
}

/* TABLET (600–1024px): pills become subtle ambient decoration */
@media (min-width: 601px) and (max-width: 1024px) {
  .lc-hero .lc-scene { height: 540px; }
  .lc-hero .lc-center .lc-headline .lc-headline-text { font-size: 52px !important; letter-spacing: -1.5px !important; }
  .lc-hero .lc-tag {
    font-size: 13px;
    padding: 6px 12px;
    /* Reduce visual weight — pills are atmosphere, not content */
    filter: saturate(0.4);
  }
  .lc-hero .lc-tag.has-avatar { padding-left: 3px; }
  .lc-hero .lc-avatar { width: 24px; height: 24px; font-size: 10px; }
}

/* MOBILE (< 600px): hide all the pill scenery entirely */
@media (max-width: 600px) {
  .lc-hero .lc-scene { height: auto; min-height: 320px; padding: 60px 20px; }
  .lc-hero .lc-center { position: relative; inset: auto; padding: 0; }
  .lc-hero .lc-center .lc-headline .lc-headline-text {
    font-size: 38px !important;
    letter-spacing: -1px !important;
  }
  .lc-hero .lc-center .lc-sub { font-size: 14px; }
  /* Nuke every pill on mobile — too cramped to be useful */
  .lc-hero .lc-tag { display: none !important; }
}
</style>

<script>
(function() {
  const AVATAR_BASE = '/wp-content/uploads/avatars/';
  const AVATAR_EXT  = '.webp';
  const PARALLAX_STRENGTH = 36;
  const PARALLAX_EASE     = 0.08;
  // Center fade-out radius is responsive: wider on tablet so pills don't crash the headline
  const FADE_RADIUS_DESKTOP = 320;
  const FADE_RADIUS_TABLET  = 420;
  const FADE_MIN_OPACITY  = 0.15;
  const HOVER_SCALE       = 1.12;

  const TAGS = [
    { text: 'Catalyst',       x: 67.6, y: 59, depth: 0.8, href: '#' },
    { text: 'DReps',          x: 12.7, y: 27, depth: 1.4, href: '#' },
    { text: 'Midnight',       x: 49.2, y: 21, depth: 1.3, href: '#' },
    { text: 'Plutus',         x: 78,   y: 48, depth: 0.5, href: '#' },
    { text: 'Hydra',          x: 10.8, y: 70, depth: 1.7, href: '#' },
    { text: 'Stake Pools',    x: 13.7, y: 15, depth: 1.2, href: '#' },
    { text: 'eUTxO',          x: 87.7, y: 48, depth: 1.8, href: '#' },
    { text: 'Governance',     x: 82,   y: 77, depth: 1.5, href: '#' },
    { text: 'CIPs',           x: 54,   y: 14, depth: 0.4, href: '#' },
    { text: 'Partner Chains', x: 89,   y: 38, depth: 1.9, href: '#' },
    { text: 'Ouroboros',      x: 6,    y: 50, depth: 0.6, href: '#' },
    { text: 'Aiken',          x: 27,   y: 84, depth: 1.4, href: '#' },
    { text: 'Podcast',    x: 35, y: 12, depth: 1.3, href: '/podcast' },
    { text: 'Courses',    x: 73, y: 14, depth: 0.9, href: '/courses' },
    { text: 'Interviews', x: 19, y: 84, depth: 1.0, href: '/interviews' },
    { text: 'Resources',  x: 85, y: 86, depth: 1.6, href: '/resources' },
    { text: 'Adam Dean',           handle: 'adamKDean',    x: 22, y: 22, depth: 1.3 },
    { text: 'Jillian | W3i CEO',   handle: 'W3i_CEO',      x: 60, y: 81, depth: 1.5 },
    { text: 'Linda',               handle: 'Cryptofly777', x: 8,  y: 38, depth: 0.7 },
    { text: 'Input | Output',      handle: 'IOGroup',      x: 76, y: 28, depth: 1.1 },
    { text: 'Cardano Foundation',  handle: 'Cardano_CF',   x: 44, y: 70, depth: 2.0 }
  ];

  const scene = document.getElementById('lcScene');
  const headline = document.getElementById('lcHeadline');
  if (!scene || !headline) return;

  const tagEls = [];

  TAGS.forEach(function(t) {
    const a = document.createElement('a');
    a.className = 'lc-tag';
    a.style.left = t.x + '%';
    a.style.top = t.y + '%';
    a.dataset.depth = t.depth;
    a.dataset.x = t.x;
    a.dataset.y = t.y;

    const href = t.href || (t.handle ? 'https://x.com/' + t.handle : '#');
    a.href = href;
    if (href !== '#' && !t.handle) a.classList.add('is-cta');
    if (/^https?:\/\//.test(href)) {
      a.target = '_blank';
      a.rel = 'noopener noreferrer';
    }

    if (t.handle) {
      a.classList.add('has-avatar');
      const avatar = document.createElement('span');
      avatar.className = 'lc-avatar';
      const initials = t.text.replace(/[|@]/g, '').trim().split(/\s+/).map(function(s){return s[0];}).slice(0,2).join('').toUpperCase();
      avatar.textContent = initials;

      // Add the img to the avatar container immediately (hidden until loaded)
      // then set src — this guarantees the browser fetches it.
      const img = document.createElement('img');
      img.alt = '';
      img.style.opacity = '0';
      img.style.transition = 'opacity 0.2s ease';
      img.onload = function() {
        avatar.textContent = ''; // remove initials
        avatar.appendChild(img);
        // small delay so the append + fade-in feels smooth
        requestAnimationFrame(function() { img.style.opacity = '1'; });
      };
      img.onerror = function() { /* keep initials fallback */ };
      img.src = AVATAR_BASE + t.handle + AVATAR_EXT;
      a.appendChild(avatar);
    }

    const label = document.createElement('span');
    label.textContent = t.text;
    a.appendChild(label);

    a.addEventListener('mouseenter', function() { a._hover = true; });
    a.addEventListener('mouseleave', function() { a._hover = false; });

    scene.appendChild(a);
    tagEls.push(a);
  });

  // NO per-char split anymore — the gradient handles itself on the single span.

  // Entrance
  requestAnimationFrame(function() {
    setTimeout(function() {
      scene.classList.add('is-in');
    }, 100);
  });

  // Safety: force headline visible after 1.5s no matter what
  setTimeout(function() {
    headline.classList.add('force-show');
  }, 1500);

  // Animation loop
  const isTouch = window.matchMedia('(hover: none)').matches || window.innerWidth < 600;
  let tx = 0, ty = 0, cx = 0, cy = 0;
  let sceneRect = scene.getBoundingClientRect();
  function updateRect() { sceneRect = scene.getBoundingClientRect(); }
  window.addEventListener('resize', updateRect);
  window.addEventListener('scroll', updateRect, { passive: true });

  if (!isTouch) {
    scene.addEventListener('mousemove', function(e) {
      const r = sceneRect;
      tx = ((e.clientX - r.left) / r.width - 0.5) * 2;
      ty = ((e.clientY - r.top) / r.height - 0.5) * 2;
    });
    scene.addEventListener('mouseleave', function() { tx = 0; ty = 0; });
  }

  const eased = new Map();

  function frame() {
    if (!isTouch) {
      cx += (tx - cx) * PARALLAX_EASE;
      cy += (ty - cy) * PARALLAX_EASE;
    }
    const sceneEntered = scene.classList.contains('is-in');
    const centerX = sceneRect.width / 2;
    const centerY = sceneRect.height / 2;
    // Wider exclusion zone on tablet
    const fadeRadius = window.innerWidth <= 1024 ? FADE_RADIUS_TABLET : FADE_RADIUS_DESKTOP;

    tagEls.forEach(function(tag) {
      const depth = parseFloat(tag.dataset.depth) || 1;
      const xPct = parseFloat(tag.dataset.x);
      const yPct = parseFloat(tag.dataset.y);

      const moveX = isTouch ? 0 : -cx * depth * PARALLAX_STRENGTH;
      const moveY = isTouch ? 0 : -cy * depth * PARALLAX_STRENGTH;

      const pillX = (xPct / 100) * sceneRect.width;
      const pillY = (yPct / 100) * sceneRect.height;
      const dx = pillX - centerX;
      const dy = pillY - centerY;
      const dist = Math.sqrt(dx*dx + dy*dy);

      let centerOpacity = 1;
      if (dist < fadeRadius) {
        const t = dist / fadeRadius;
        centerOpacity = FADE_MIN_OPACITY + (1 - FADE_MIN_OPACITY) * t;
      }
      if (tag._hover) centerOpacity = 1;

      const targetOpacity = sceneEntered ? centerOpacity : 0;
      let cur = eased.get(tag);
      if (cur === undefined) cur = 0;
      cur += (targetOpacity - cur) * 0.15;
      eased.set(tag, cur);

      const scale = tag._hover ? HOVER_SCALE : 1;
      tag.style.opacity = cur;
      tag.style.transform = 'translate3d(' + moveX + 'px, ' + moveY + 'px, 0) scale(' + scale + ')';
    });
    requestAnimationFrame(frame);
  }
  frame();
})();
</script>
		
        <div class="latest section">
            <div class="container">
                <div class="row latest-intro justify-content-md-center">
                    <div class="col-md-10">
                        <h2 class="my-1"><span class="fa-solid fa-podcast"></span> Latest Episodes</h2>
                        <div class="row my-3 ">
                            <div class="latest-intro col-md-6">
                                <p class="latest-episodes ">
                                    View all of our latest episodes anywhere you listen to your favour podcasts and on YouTube.
                                </p>
                            </div>
                            <div class="col-md-6 latest-cta text-center">
                                <a class="view-all btn btn-secondary float-md-end" href="/podcasts/">
                                    All Episodes
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="episode-list ">
                    <div class="row  justify-content-md-center">
                        <div class="col-md-10">
                            <?php get_template_part( 'template-parts/home', 'podcasts' ); ?>
                        </div>
                    </div>
                </div>
                <div class="episode-cta  text-center">
                    <a href="/podcasts/" class="btn-secondary btn">All episodes</a>
                </div>
            </div>
        </div>

    </main><!-- .content -->

<?php

get_footer();
