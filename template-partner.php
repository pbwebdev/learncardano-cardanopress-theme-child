<?php
/**
 * Template Name: Partner / Media kit
 *
 * Full-width "partner with Learn Cardano" sales page: the media kit and rate
 * card as a scrolling page, then the free project-interview queue and the
 * Calendly booking embed. Assigned to /interview-booking/.
 *
 * Every figure, rate and package lives in the arrays below so the page can be
 * updated in one place; the markup further down only loops over them. The
 * page's own editor content is not used.
 *
 * Gated pricing: audience and performance figures are real and shown openly.
 * Only PRICING (rate card, CPM note's dollar figures, the dedicated-video
 * value box, package and à la carte prices, the rapid-response slot price) is
 * gated: the real prices are only in the media kit (DocSend, behind an email
 * wall). Every price in this file is a deliberately WRONG same-shape
 * placeholder, rendered blurred inside `.pk__gated` blocks with an overlay
 * linking to the media kit. Never put the real prices here: this file ships in
 * a public repo and in the page HTML. Strings with `%s` / `%1$s` placeholders
 * take their masked numbers from the matching `nums` array.
 *
 * Styles: assets/css/partner.css (`.pk__`). Assets and the Calendly script are
 * enqueued by includes/partner.php on this template only.
 *
 * @package CardanoPress Bootstrap
 * @since 0.1.0
 */

/* ------------------------------------------------------------------
 * Editable content
 * ---------------------------------------------------------------- */

// The media kit (DocSend, email-gated): where every real figure lives.
$lc_media_kit_url = 'https://link.learncardano.io/learncardano-media-kit';

// Hero stat strip. `count` drives the count-up; `text` is what is printed.
$lc_stats = array(
	// YouTube Studio, lifetime to 16 Oct 2026 (CTR and impressions from the 2026 media kit).
	array( 'text' => '32,434', 'count' => 32434, 'decimals' => 0, 'suffix' => '', 'label' => __( 'subscribers', 'learncardano' ) ),
	array( 'text' => '3,198,706', 'count' => 3198706, 'decimals' => 0, 'suffix' => '', 'label' => __( 'lifetime views', 'learncardano' ) ),
	array( 'text' => '265.1K', 'count' => 265.1, 'decimals' => 1, 'suffix' => 'K', 'label' => __( 'hours watched', 'learncardano' ) ),
	array( 'text' => '53.4K', 'count' => 53.4, 'decimals' => 1, 'suffix' => 'K', 'label' => __( 'monthly audience', 'learncardano' ) ),
	array( 'text' => '4.78%', 'count' => 4.78, 'decimals' => 2, 'suffix' => '%', 'label' => __( 'lifetime CTR · 32.5M impressions', 'learncardano' ) ),
);
$lc_distribution = __( 'Podcast: Spotify, Apple, iHeart, Spreaker · learncardano.io indexed archive · newsletter · Discord · X · Facebook', 'learncardano' );

// Why partner (four reasons + pull quote).
$lc_reasons = array(
	array( __( 'A regulated market', 'learncardano' ), __( 'Australia now licenses crypto businesses. Compliance is what customers ask about first, and it needs explaining, not advertising.', 'learncardano' ) ),
	array( __( 'A specific audience', 'learncardano' ), __( 'Not general crypto reach. Australian holders researching tax, structure, licensing and liquidity, people with assets and decisions to make.', 'learncardano' ) ),
	array( __( 'Compliance-aware by default', 'learncardano' ), __( 'Built around ASIC INFO 269 and full disclosure. A low-risk partner for a licensed or licence-seeking brand.', 'learncardano' ) ),
	array( __( 'Measurable in 30 days', 'learncardano' ), __( 'Australian regulatory content front-loads: most views land in week one, backed by a written delivery guarantee.', 'learncardano' ) ),
);
$lc_quote = __( 'Trust stories don’t sell themselves through performance marketing. They need explaining, by someone Australians already trust to explain Australian crypto rules.', 'learncardano' );

// Content pillars: title, description, tag.
$lc_pillars = array(
	array( __( 'Tax & ATO compliance', 'learncardano' ), __( 'CGT, lodgement deadlines, reporting, penalties, recordkeeping.', 'learncardano' ), __( 'Highest-performing pillar', 'learncardano' ) ),
	array( __( 'Regulation & licensing', 'learncardano' ), __( 'Exchange licensing, AUSTRAC, custody, court rulings and what they change.', 'learncardano' ), __( 'Rapid-response driver', 'learncardano' ) ),
	array( __( 'Portfolio & structure', 'learncardano' ), __( 'SMSF, company and trust structures, rebalancing, yield, liquidity options.', 'learncardano' ), __( 'High-intent viewers', 'learncardano' ) ),
	array( __( 'Weekly market brief', 'learncardano' ), __( 'Every Monday. Cardano, Midnight and the broader market pulse.', 'learncardano' ), __( 'Reliable weekly reach', 'learncardano' ) ),
	array( __( 'Conference coverage', 'learncardano' ), __( 'On-the-ground previews and recaps from major industry events.', 'learncardano' ), __( 'Event-tied moments', 'learncardano' ) ),
);
$lc_cadence = __( 'Publishing runs three times a week (Monday brief, Wednesday feature, Friday deep dive), so a campaign can be sequenced against a known calendar rather than hoping for a slot.', 'learncardano' );

// Audience comparison: metric, AU tax & ruling videos, everything else.
$lc_compare = array(
	array( __( 'Average views', 'learncardano' ), '26,413', '5,436' ),
	array( __( 'Median views', 'learncardano' ), '21,081', '3,952' ),
	array( __( 'Watch hours / video', 'learncardano' ), '1,462', '482' ),
	array( __( 'New subscribers / video', 'learncardano' ), '484', '41' ),
	array( __( 'Click-through rate', 'learncardano' ), '5.9%', '5.4%' ),
);
$lc_multipliers = array(
	array( '4.9×', __( 'the views', 'learncardano' ), false ),
	array( '3.0×', __( 'the watch hours', 'learncardano' ), false ),
	array( '11.8×', __( 'the subscribers', 'learncardano' ), true ),
);

// Demographics: figure, label, highlight.
$lc_demo = array(
	// YouTube Studio, views, lifetime to 16 Oct 2026.
	array( '80.5%', __( 'aged 25 to 54', 'learncardano' ) ),
	array( '57.5%', __( 'aged 35 to 54, the largest cohort', 'learncardano' ) ),
	array( '96.6%', __( 'male', 'learncardano' ) ),
	array( '0.2%', __( 'aged 13 to 17', 'learncardano' ) ),
);

// How they watch (YouTube Studio, 16 Oct 2026): figure, label.
$lc_habits = array(
	array( '17.1%', __( 'of subscribers have “All notifications” on (5.5K people), YouTube’s typical range is 10% to 30%', 'learncardano' ) ),
	array( '47.8%', __( 'of all watch time comes from subscribers', 'learncardano' ) ),
	array( '54.6%', __( 'of the monthly audience are new viewers, reach beyond the existing base', 'learncardano' ) ),
	array( '13.6%', __( 'of watch time is on TV screens; 35.3% on computers, 46.6% on mobile', 'learncardano' ) ),
);
$lc_also_watches = __( 'Viewers also watch Altcoin Daily, Paul Barron Network and Crypto Capital Venture.', 'learncardano' );

// Proof: title, date/context, views, average view duration, thumbnail (file, w, h) or null.
// Top videos by lifetime views, YouTube Studio 16 Oct 2026.
$lc_proof = array(
	array( __( 'Bitcoin Tax Free in Australia? Landmark Bitcoin Ruling', 'learncardano' ), __( 'May 2025', 'learncardano' ), '99,833', '3:12 (33.8%)', array( 'proof-bitcoin-tax-free.jpg', 1120, 630 ) ),
	array( __( 'Australia’s New Crypto Regulations: What AFSL & AUSTRAC Mean for You', 'learncardano' ), __( 'Sep 2026', 'learncardano' ), '56,244', '3:33 (33.0%)', array( 'proof-afsl-austrac.jpg', 640, 360 ) ),
	array( __( 'Why the ATO Is Panicking Over This Crypto Case', 'learncardano' ), __( 'Jun 2026', 'learncardano' ), '45,029', '2:43 (38.3%)', array( 'proof-ato-panicking.jpg', 640, 360 ) ),
	array( __( 'Elon Musk and Charles Are Up to Something… 3 Big Rumours', 'learncardano' ), __( 'Nov 2024', 'learncardano' ), '54,173', '6:00 (37.1%)', null ),
	array( __( 'Ledger Hardware Implant Confirmed: What Wallet Users Should Check', 'learncardano' ), __( 'Oct 2026 · published 12 Oct', 'learncardano' ), '36,747', '2:49 (33.5%)', null ),
);

// Click-through case: figure, explanation.
$lc_conversion = array(
	array( '68', __( 'link clicks on the day of publication, against a 2 to 5/day baseline', 'learncardano' ) ),
	array( '398', __( 'human clicks of 858 recorded. We quote the filtered number', 'learncardano' ) ),
	array( '5% to 10%', __( 'click-to-purchase in the 72-hour window. Typical e-commerce: 2% to 3%', 'learncardano' ) ),
	array( '72hrs', __( 'from publication to every recorded purchase', 'learncardano' ) ),
);

// Editorial calendar: month, arc, topics.
$lc_calendar = array(
	array( __( 'September', 'learncardano' ), __( 'Licensing arc', 'learncardano' ), array( __( 'Australia licenses crypto exchanges', 'learncardano' ), __( 'Is your exchange actually licensed?', 'learncardano' ), __( 'Coins held on trust', 'learncardano' ), __( 'Selling without selling', 'learncardano' ) ) ),
	array( __( 'October', 'learncardano' ), __( 'Tax season peak', 'learncardano' ), array( __( 'The ATO already knows', 'learncardano' ), __( 'Reporting at Question 18', 'learncardano' ), __( 'The 50% CGT discount', 'learncardano' ), __( 'Last-minute checklist before 31 Oct', 'learncardano' ), __( 'Token2049 recap', 'learncardano' ) ) ),
	array( __( 'November', 'learncardano' ), __( 'Structure & strategy', 'learncardano' ), array( __( 'Post-tax portfolio reset', 'learncardano' ), __( 'DeFi yields vs TradFi cash', 'learncardano' ), __( 'Crypto inside an SMSF, company or trust', 'learncardano' ), __( 'AusCryptoCon preview', 'learncardano' ) ) ),
	array( __( 'December', 'learncardano' ), __( 'Industry read', 'learncardano' ), array( __( 'What I learned at AusCryptoCon: where the Australian industry lands post-licensing', 'learncardano' ) ) ),
);

// Where a sponsor fits: title, description, best for.
$lc_fits = array(
	array( __( 'Own a pillar', 'learncardano' ), __( 'Attach to a recurring theme for the term: tax, licensing or structure. Dedicated videos plus integrations inside every video in that pillar, so you become the default brand on the subject.', 'learncardano' ), __( 'Best for category ownership', 'learncardano' ) ),
	array( __( 'Own a moment', 'learncardano' ), __( 'Book against a dated peak already on the calendar: the lodgement deadline, a conference, the post-tax reset. Highest intent, and the date is known months ahead.', 'learncardano' ), __( 'Best for launches & seasonal offers', 'learncardano' ) ),
	array( __( 'Hold a rapid-response slot', 'learncardano' ), __( 'Retain capacity for the unscheduled. When a ruling, court decision or Treasury announcement lands, we publish within 48 hours with you already integrated, where the biggest numbers come from.', 'learncardano' ), __( 'Best for share of voice', 'learncardano' ) ),
);

// Rate card (placeholder prices): placement, detail, price, row style ('' | 'feature' | 'hot').
$lc_rates_single = array(
	array( __( 'Dedicated video', 'learncardano' ), __( 'AU regulation format, 6 to 8 min, sponsor is the subject', 'learncardano' ), '$8,800', 'feature' ),
	array( __( 'Rapid-response slot', 'learncardano' ), __( 'published within 48h of a qualifying event', 'learncardano' ), '$5,900', 'hot' ),
	array( __( 'Mid-roll integration, 60 to 90 sec', 'learncardano' ), __( 'inside an AU regulation / tax video', 'learncardano' ), '$3,650', '' ),
	array( __( 'Mid-roll integration, 60 to 90 sec', 'learncardano' ), __( 'inside standard weekly content', 'learncardano' ), '$1,400', '' ),
	array( __( 'Opening mention, 20 to 30 sec', 'learncardano' ), __( 'plus pinned comment & description link', 'learncardano' ), '$1,150', '' ),
	array( __( 'YouTube Short, dedicated', 'learncardano' ), '', '$650', '' ),
	array( __( 'Podcast audio read', 'learncardano' ), __( 'all feeds', 'learncardano' ), '$720', '' ),
	array( __( 'Sponsored article on learncardano.io', 'learncardano' ), __( 'permanent & indexed', 'learncardano' ), '$1,950', '' ),
	array( __( 'Newsletter feature', 'learncardano' ), __( 'dedicated section', 'learncardano' ), '$680', '' ),
	array( __( 'Social amplification', 'learncardano' ), __( 'X and Facebook', 'learncardano' ), '$480', '' ),
);
$lc_rates_addons = array(
	array( __( 'Category exclusivity', 'learncardano' ), __( 'crypto credit & lending, per month', 'learncardano' ), '$1,900', '' ),
	array( __( 'Paid usage & whitelisting rights, 90 days', 'learncardano' ), '', '+55%', '' ),
	array( __( 'Usage extended beyond 12 months', 'learncardano' ), '', '+35%', '' ),
	array( __( 'Rush turnaround on non rapid-response', 'learncardano' ), __( 'under 7 days', 'learncardano' ), '+30%', '' ),
	array( __( 'Additional script revision round', 'learncardano' ), __( 'beyond the two included', 'learncardano' ), '$390', '' ),
	array( __( 'On-camera talent for your own channels', 'learncardano' ), __( 'per day', 'learncardano' ), '$3,900', '' ),
	array( __( 'Live appearance, webinar or event', 'learncardano' ), __( 'per session', 'learncardano' ), '$2,350', '' ),
);
/* translators: 1: integration price, 2: AUD CPM, 3: USD CPM */
$lc_cpm_note      = __( 'Pricing is calculated on average views for the specific content category being bought, not subscriber count. At the category median of 21,081 views, the %1$s integration equates to an AUD CPM of %2$s (~USD %3$s), inside the published 2026 finance-sponsorship band, before any premium for CTR, watch time or subscriber acquisition.', 'learncardano' );
$lc_cpm_note_nums = array( '$3,650', '$123', '$80' ); // placeholder prices

// "Why $X" value box (placeholder prices): every sum and result is price
// arithmetic, so the whole table is placeholder and gated.
$lc_dedicated_price = '$8,800';
$lc_value_math      = array(
	array( __( 'CPM at the category median', 'learncardano' ), '$8,800 ÷ 29,654 views × 1,000', '≈ $297' ),
	array( __( 'CPM at the 30-day planning figure', 'learncardano' ), '$8,800 ÷ 28,000 views × 1,000', '≈ $314' ),
	array( __( 'CPM at the cohort average', 'learncardano' ), '$8,800 ÷ 38,276 views × 1,000', '≈ $230' ),
	array( __( 'Cost per watch hour', 'learncardano' ), '$8,800 ÷ 2,318 hours', '≈ $3.80' ),
	array( __( 'Cost per new subscriber', 'learncardano' ), '$8,800 ÷ 693 subscribers', '≈ $12.70' ),
);

// Campaign packages.
$lc_packages = array(
	array(
		'letter'      => 'A',
		'name'        => __( 'Market Test', 'learncardano' ),
		'term'        => __( '4 weeks', 'learncardano' ),
		'items'       => array( __( '1 dedicated video', 'learncardano' ), __( '1 integration', 'learncardano' ), __( '1 Short', 'learncardano' ), __( '1 podcast read', 'learncardano' ), __( '1 newsletter feature', 'learncardano' ), __( 'social amplification', 'learncardano' ) ),
		'a_la_carte'  => '$14,650',
		'price'       => '$12,400',
		'recommended' => false,
	),
	array(
		'letter'      => 'B',
		'name'        => __( 'Australian Launch', 'learncardano' ),
		'term'        => __( '12 weeks', 'learncardano' ),
		'items'       => array( __( '3 dedicated videos', 'learncardano' ), __( '2 rapid-response slots', 'learncardano' ), __( '3 integrations', 'learncardano' ), __( '4 Shorts', 'learncardano' ), __( '2 articles', 'learncardano' ), __( '3 podcast reads', 'learncardano' ), __( '3 newsletter features', 'learncardano' ), __( '6 social posts', 'learncardano' ), __( 'category exclusivity', 'learncardano' ) ),
		'a_la_carte'  => '$66,900',
		'price'       => '$56,800',
		'recommended' => true,
	),
	array(
		'letter'      => 'C',
		'name'        => __( 'Always On', 'learncardano' ),
		'term'        => __( '6 months', 'learncardano' ),
		'items'       => array( __( '4 dedicated videos', 'learncardano' ), __( '4 rapid-response slots', 'learncardano' ), __( '6 integrations', 'learncardano' ), __( '6 Shorts', 'learncardano' ), __( '3 articles', 'learncardano' ), __( '6 podcast reads', 'learncardano' ), __( '6 newsletter features', 'learncardano' ), __( '12 social posts', 'learncardano' ), __( 'category exclusivity', 'learncardano' ), __( 'quarterly reporting', 'learncardano' ) ),
		'a_la_carte'  => '$112,600',
		'price'       => '$94,500',
		'recommended' => false,
	),
);
// Rapid-response box (placeholder price): slot price.
$lc_rr_price = '$5,900';

// Compliance regimes: label, text.
$lc_regimes = array(
	array( __( 'Credit · NCCP s8', 'learncardano' ), __( 'Where a credit product is involved, content explains how it works as a category and identifies the sponsor, but won’t tell viewers to apply, carry an application link, or walk through an application. A direct CTA means appointing Learn Crypto an authorised credit representative: a separate conversation, a separate rate.', 'learncardano' ) ),
	array( __( 'Financial services · INFO 269', 'learncardano' ), __( 'ASIC issued warning notices to four finfluencers in April 2026 and is reviewing licensee supervision. No content here will recommend borrowing, recommend a target asset for the borrowed capital, or project returns.', 'learncardano' ) ),
	array( __( 'Tax', 'learncardano' ), __( 'The disposal-vs-collateral point is a factual statement about how the two transactions differ, not tax advice, delivered as such, with a standing direction to see a registered tax agent.', 'learncardano' ) ),
);
$lc_practice = array(
	__( 'Paid-promotion disclosure, verbal in the first 30 sec and written in the description', 'learncardano' ),
	__( 'Your compliance team reviews and signs off the script before production. Two revision rounds included', 'learncardano' ),
	__( 'No “guaranteed”, “risk-free”, “safe” or “passive income” language, and no return projections', 'learncardano' ),
	__( 'Liquidation and margin-call risk stated plainly in every placement that mentions borrowing', 'learncardano' ),
	__( 'The standing “not financial, legal or tax advice” disclaimer already fixed on every video', 'learncardano' ),
);

// Commercial terms, grouped for the accordions: group title => terms.
$lc_terms = array(
	__( 'Pricing & payment', 'learncardano' )       => array(
		__( 'All rates AUD, ex GST. Invoiced via PP Ventures Pty Ltd; 10% GST applies to invoices to Australian entities.', 'learncardano' ),
		__( '50% on signature, 50% on publication. Payment terms 14 days.', 'learncardano' ),
		__( 'Organic placement only. Paid usage & whitelisting priced separately.', 'learncardano' ),
	),
	__( 'Content & editorial control', 'learncardano' ) => array(
		__( 'Content stays published for 12 months minimum, then remains up by default.', 'learncardano' ),
		__( 'Editorial control stays with Learn Crypto. The sponsor approves all factual claims made about its own products.', 'learncardano' ),
		__( 'Learn Crypto declines any brief that would require unlicensed financial-product advice or credit assistance under NCCP s8.', 'learncardano' ),
	),
	__( 'Dates, reporting & rapid response', 'learncardano' ) => array(
		__( 'Publication dates locked at signature. Miss an agreed date by 7+ days and that placement is credited at 50%.', 'learncardano' ),
		__( 'Performance report at 14 and 30 days after each placement, against the 30-day guarantee.', 'learncardano' ),
		__( 'Rapid-response slots are retained, not scheduled. Learn Crypto determines whether an event qualifies, in consultation with the sponsor. Unused slots roll forward.', 'learncardano' ),
	),
);

// Who I am.
$lc_bio = array(
	__( 'Cardano Ambassador', 'learncardano' ),
	__( 'Midnight Ambassador, Cohort 1', 'learncardano' ),
	__( 'Operator, ADAOZ stake pool', 'learncardano' ),
	__( 'Author of CardanoPress (WordPress × Cardano plugin)', 'learncardano' ),
	__( 'Former elected DRep, Cardano on-chain governance', 'learncardano' ),
	__( 'Runs Mesh With Us, a Gold Coast web-dev agency', 'learncardano' ),
);

// Glossary: group => term => definition.
$lc_glossary = array(
	__( 'Placement types', 'learncardano' )      => array(
		__( 'Dedicated video', 'learncardano' )        => __( 'a full standalone episode, 6 to 8 min, where the sponsor is the subject.', 'learncardano' ),
		__( 'Rapid-response slot', 'learncardano' )    => __( 'a pre-booked video published within 48h of a breaking Australian regulatory event, with the sponsor already integrated.', 'learncardano' ),
		__( 'Mid-roll integration', 'learncardano' )   => __( 'a 60 to 90 sec sponsor segment placed inside a longer video.', 'learncardano' ),
		__( 'Opening mention', 'learncardano' )        => __( 'a 20 to 30 sec sponsor callout at the top of a video, plus a pinned comment and description link.', 'learncardano' ),
		__( 'YouTube Short', 'learncardano' )          => __( 'a dedicated vertical video under 60 seconds.', 'learncardano' ),
		__( 'Podcast audio read', 'learncardano' )     => __( 'a scripted sponsor read across all podcast feeds (Spotify, Apple, iHeart, Spreaker).', 'learncardano' ),
		__( 'Sponsored article', 'learncardano' )      => __( 'a permanent, search-indexed written piece on learncardano.io.', 'learncardano' ),
		__( 'Newsletter feature', 'learncardano' )     => __( 'a dedicated sponsor section in the Learn Crypto email newsletter.', 'learncardano' ),
	),
	__( 'Commercial & metrics', 'learncardano' ) => array(
		__( 'Social amplification', 'learncardano' )     => __( 'supporting posts on X and Facebook that drive traffic to the placement.', 'learncardano' ),
		__( 'Category exclusivity', 'learncardano' )     => __( 'for a monthly fee, no competing brand in your category appears on the channel.', 'learncardano' ),
		__( 'Paid usage / whitelisting', 'learncardano' ) => __( 'rights for the sponsor to run the content as its own paid advertising.', 'learncardano' ),
		__( 'Views & impressions', 'learncardano' )      => __( 'views are video plays; impressions are the number of times a thumbnail was shown.', 'learncardano' ),
		__( 'Click-through rate (CTR)', 'learncardano' ) => __( 'the % of impressions that became a view; higher means a more compelling thumbnail and title.', 'learncardano' ),
		__( 'Watch hours', 'learncardano' )              => __( 'total hours audiences spent watching; a measure of genuine attention, not just clicks.', 'learncardano' ),
		__( 'CPM', 'learncardano' )                      => __( 'cost per thousand views; the standard way to compare sponsorship value.', 'learncardano' ),
		__( 'Evergreen search traffic', 'learncardano' ) => __( 'the ongoing views a video keeps earning from search for years after publishing.', 'learncardano' ),
	),
);

// Project interviews (the free editorial queue): what's included.
$lc_interview_includes = array(
	array( __( 'Full video interview', 'learncardano' ), __( 'Published on YouTube, edited for pacing: we handle all editing, thumbnails and publishing.', 'learncardano' ) ),
	array( __( 'Short-form clips', 'learncardano' ), __( 'At least 3 clips across YouTube Shorts, Instagram Reels, TikTok and X, released over the two weeks after launch.', 'learncardano' ) ),
	array( __( 'Article on learncardano.io', 'learncardano' ), __( 'A write-up of the key discussion points, with links to your project and socials, published alongside the video.', 'learncardano' ) ),
	array( __( 'Podcast episode', 'learncardano' ), __( 'Full audio on Spotify and the major podcast platforms, 7 days after the YouTube release.', 'learncardano' ) ),
	array( __( 'Social & newsletter', 'learncardano' ), __( 'An announcement post plus one post per clip on X, and a mention in the weekly newsletter.', 'learncardano' ) ),
	array( __( 'Your raw recording', 'learncardano' ), __( 'A copy of the raw video for your own use. Everything stays live on every platform indefinitely.', 'learncardano' ) ),
);
$lc_interview_standards = array(
	__( 'Token launches, token sales or pre-sale announcements of any kind', 'learncardano' ),
	__( 'Financial advice or price speculation (buying, selling or investment recommendations)', 'learncardano' ),
	__( 'Projects in early development without mainnet deployment or demonstrable traction', 'learncardano' ),
	__( 'Undisclosed paid promotion: any paid placement on the channel is clearly labelled as such', 'learncardano' ),
	__( 'Projects that lack transparency about team, funding or technical implementation', 'learncardano' ),
	__( 'Pump-and-dump schemes, Ponzi structures or other financially exploitative models', 'learncardano' ),
	__( 'Unvetted or anonymous team members without verifiable track records', 'learncardano' ),
	__( 'Rebrand attempts of failed projects under new names', 'learncardano' ),
);

// Next steps.
$lc_steps = array(
	__( 'Confirm the package and window', 'learncardano' ),
	__( 'Compliance kickoff call: Learn Crypto and your compliance team, 30 minutes', 'learncardano' ),
	__( 'Script development and sign-off', 'learncardano' ),
	__( 'Production and publication', 'learncardano' ),
	__( 'Reporting', 'learncardano' ),
);

// Peter's personal contact / follow route (replaces the old mailto links).
$lc_chatsoon_url = 'https://chatsoon.app/id/peter-bui-5ec50167';
$lc_calendly_url = 'https://calendly.com/chatwithpete/crypto-chat?hide_event_type_details=1&hide_gdpr_banner=1&primary_color=dc10ff';

/* ------------------------------------------------------------------
 * Helpers
 * ---------------------------------------------------------------- */

/**
 * An <img> from images/partner/ with intrinsic size (no layout shift) and
 * lazy loading unless it is above the fold.
 */
$lc_img = function ( $file, $width, $height, $alt, $class = '', $eager = false ) {
	return sprintf(
		'<img src="%1$s" width="%2$d" height="%3$d" alt="%4$s" class="%5$s" decoding="async" %6$s>',
		esc_url( get_stylesheet_directory_uri() . '/images/partner/' . $file ),
		(int) $width,
		(int) $height,
		esc_attr( $alt ),
		esc_attr( $class ),
		$eager ? 'fetchpriority="high"' : 'loading="lazy"'
	);
};

/**
 * A masked (placeholder) number: blurred by CSS inside `.pk__gated` and hidden
 * from screen readers, which get the block's "in the media kit" line instead.
 */
$lc_num = function ( $text ) {
	return '<span class="pk__num" aria-hidden="true">' . esc_html( $text ) . '</span>';
};

/**
 * A translatable string with `%s` / `%1$s` slots filled by masked numbers.
 * Only <strong> survives in the string itself.
 */
$lc_mask = function ( $text, $nums = array() ) use ( $lc_num ) {
	return vsprintf( wp_kses( $text, array( 'strong' => array() ) ), array_map( $lc_num, $nums ) );
};

/**
 * Opens a gated pricing block: blurred placeholder prices plus a media-kit
 * overlay (closed by $lc_gate_close). $mods are `pk__gated--` modifiers:
 * dark, value.
 */
$lc_gate_open = function ( $mods = array() ) {
	$class = 'pk__gated';
	foreach ( (array) $mods as $mod ) {
		$class .= ' pk__gated--' . $mod;
	}
	printf( '<div class="%s"><div class="pk__gated-body"><p class="pk__sr">%s</p>', esc_attr( $class ), esc_html__( 'Pricing available in the media kit.', 'learncardano' ) );
};

/**
 * Closes a gated pricing block with its "Unlock pricing" overlay card.
 */
$lc_gate_close = function () use ( $lc_media_kit_url ) {
	$lock = '<svg class="pk__unlock-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><rect x="4" y="11" width="16" height="10" rx="2.5"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>';
	$link = sprintf(
		'<a class="pk__btn pk__btn--kit pk__unlock" href="%1$s" target="_blank" rel="noopener">%2$s <span aria-hidden="true">→</span><span class="pk__sr"> %3$s</span></a>',
		esc_url( $lc_media_kit_url ),
		esc_html__( 'Get the media kit', 'learncardano' ),
		esc_html__( '(opens in a new tab)', 'learncardano' )
	);
	echo '</div>'; // .pk__gated-body
	echo '<div class="pk__unlock-card">' . $lock . '<div class="pk__unlock-copy"><p class="pk__unlock-title">' . esc_html__( 'Unlock pricing', 'learncardano' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG.
	echo '<p class="pk__unlock-text">' . esc_html__( 'Rates and package pricing are in the media kit: enter your email to view it.', 'learncardano' ) . '</p>';
	echo '</div>' . $link . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
	echo '</div>'; // .pk__gated
};

/**
 * A rate-card table (single placements or add-ons).
 */
$lc_rate_table = function ( $caption, $rows ) use ( $lc_num ) {
	?>
	<table class="pk__rates">
		<caption><?php echo esc_html( $caption ); ?></caption>
		<thead class="pk__sr">
			<tr><th scope="col"><?php esc_html_e( 'Placement', 'learncardano' ); ?></th><th scope="col"><?php esc_html_e( 'Price (AUD, ex GST)', 'learncardano' ); ?></th></tr>
		</thead>
		<tbody>
			<?php foreach ( $rows as $row ) : ?>
				<tr<?php echo $row[3] ? ' class="pk__rate--' . esc_attr( $row[3] ) . '"' : ''; ?>>
					<th scope="row">
						<?php echo esc_html( $row[0] ); ?>
						<?php if ( $row[1] ) : ?>
							<span class="pk__rate-detail">(<?php echo esc_html( $row[1] ); ?>)</span>
						<?php endif; ?>
					</th>
					<td><?php echo $lc_num( $row[2] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_num. ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
};

// Check-mark and arrow icons used in lists (static SVG).
$lc_check = '<svg class="pk__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M20 6 9 17l-5-5"/></svg>';
$lc_cross = '<svg class="pk__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M18 6 6 18M6 6l12 12"/></svg>';

get_header();
?>

<main class="pk" id="partner">

	<?php /* 1. Hero ------------------------------------------------ */ ?>
	<section class="pk__section pk__section--dark pk__hero" aria-labelledby="pk-title">
		<div class="pk__container pk__hero-grid">
			<div class="pk__hero-copy">
				<p class="pk__kicker"><?php esc_html_e( 'Media kit & rate card', 'learncardano' ); ?></p>
				<h1 id="pk-title" class="pk__h1"><span class="pk__grad"><?php esc_html_e( 'Learn Crypto', 'learncardano' ); ?></span> <?php esc_html_e( 'Partnership Opportunities', 'learncardano' ); ?></h1>
				<p class="pk__hero-sub"><?php esc_html_e( 'Australia’s crypto education channel: audience, rates and packages', 'learncardano' ); ?></p>
				<p class="pk__ctas">
					<a class="pk__btn pk__btn--kit" href="<?php echo esc_url( $lc_media_kit_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get the media kit', 'learncardano' ); ?><span class="pk__sr"> <?php esc_html_e( '(opens in a new tab)', 'learncardano' ); ?></span></a>
					<a class="pk__btn" href="#book"><?php esc_html_e( 'Book a 15-min call', 'learncardano' ); ?></a>
					<a class="pk__btn pk__btn--ghost" href="#rates"><?php esc_html_e( 'See rates', 'learncardano' ); ?></a>
				</p>
			</div>
			<figure class="pk__device">
				<?php echo $lc_img( 'youtube-channel-learn-crypto.jpg', 1090, 860, __( 'The Learn Crypto YouTube channel page, showing the channel banner, subscriber count and recent videos on Australian crypto regulation, AI agents and borrowing against crypto.', 'learncardano' ), 'pk__device-img', true ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_img. ?>
			</figure>
		</div>

		<div class="pk__container">
			<h2 class="pk__sr"><?php esc_html_e( 'The channel in numbers', 'learncardano' ); ?></h2>
			<ul class="pk__stats">
				<?php foreach ( $lc_stats as $lc_stat ) : ?>
					<li class="pk__stat">
						<span class="pk__stat-num">
							<span class="pk__sr"><?php echo esc_html( $lc_stat['text'] ); ?></span>
							<span aria-hidden="true" data-pk-count="<?php echo esc_attr( $lc_stat['count'] ); ?>" data-pk-decimals="<?php echo esc_attr( $lc_stat['decimals'] ); ?>" data-pk-suffix="<?php echo esc_attr( $lc_stat['suffix'] ); ?>"><?php echo esc_html( $lc_stat['text'] ); ?></span>
						</span>
						<span class="pk__stat-label"><?php echo esc_html( $lc_stat['label'] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="pk__distribution"><strong><?php esc_html_e( 'Distribution beyond YouTube:', 'learncardano' ); ?></strong> <?php echo esc_html( $lc_distribution ); ?></p>
		</div>
	</section>

	<?php /* 2. Why partner ---------------------------------------- */ ?>
	<section class="pk__section pk__section--white" aria-labelledby="pk-why">
		<div class="pk__container">
			<header class="pk__head">
				<p class="pk__kicker"><?php esc_html_e( 'Why partner', 'learncardano' ); ?></p>
				<h2 id="pk-why" class="pk__h2"><?php esc_html_e( 'Australian crypto brands have a trust problem. Explanation solves it.', 'learncardano' ); ?></h2>
			</header>
			<div class="pk__grid pk__grid--4">
				<?php foreach ( $lc_reasons as $lc_i => $lc_reason ) : ?>
					<div class="pk__card">
						<span class="pk__card-num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $lc_i + 1 ) ); ?></span>
						<h3 class="pk__h3"><?php echo esc_html( $lc_reason[0] ); ?></h3>
						<p><?php echo esc_html( $lc_reason[1] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<blockquote class="pk__quote"><p><?php echo esc_html( $lc_quote ); ?></p></blockquote>
		</div>
	</section>

	<?php /* 3. What the channel covers ----------------------------- */ ?>
	<section class="pk__section pk__section--light" aria-labelledby="pk-covers">
		<div class="pk__container">
			<header class="pk__head">
				<p class="pk__kicker"><?php esc_html_e( 'What the channel covers', 'learncardano' ); ?></p>
				<h2 id="pk-covers" class="pk__h2"><?php esc_html_e( 'Five recurring pillars. Sponsors buy a pillar, not a one-off.', 'learncardano' ); ?></h2>
			</header>
			<div class="pk__grid pk__grid--5">
				<?php foreach ( $lc_pillars as $lc_i => $lc_pillar ) : ?>
					<div class="pk__card pk__card--pillar<?php echo 0 === $lc_i ? ' pk__card--top' : ''; ?>">
						<h3 class="pk__h3"><?php echo esc_html( $lc_pillar[0] ); ?></h3>
						<p><?php echo esc_html( $lc_pillar[1] ); ?></p>
						<span class="pk__tag"><?php echo esc_html( $lc_pillar[2] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="pk__note pk__note--center"><?php echo esc_html( $lc_cadence ); ?></p>
		</div>
	</section>

	<?php /* 4. The audience you're buying -------------------------- */ ?>
	<section class="pk__section pk__section--white" aria-labelledby="pk-audience">
		<div class="pk__container">
			<header class="pk__head pk__head--split">
				<div>
					<p class="pk__kicker"><?php esc_html_e( 'The audience you’re buying', 'learncardano' ); ?></p>
					<h2 id="pk-audience" class="pk__h2"><?php esc_html_e( 'Not the channel. This slice of it.', 'learncardano' ); ?></h2>
				</div>
				<p class="pk__lede"><?php esc_html_e( '9 long-form videos on Australian crypto tax & court rulings, measured against the other 181 published in the same period.', 'learncardano' ); ?></p>
			</header>
			<div class="pk__table-wrap">
				<table class="pk__table pk__compare">
					<caption class="pk__sr"><?php esc_html_e( 'Australian tax and ruling videos compared with everything else published in the same period', 'learncardano' ); ?></caption>
					<thead>
						<tr>
							<td></td>
							<th scope="col" class="pk__compare-hi"><?php esc_html_e( 'AU tax & ruling videos', 'learncardano' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Everything else', 'learncardano' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $lc_compare as $lc_row ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $lc_row[0] ); ?></th>
								<td class="pk__compare-hi"><?php echo esc_html( $lc_row[1] ); ?></td>
								<td><?php echo esc_html( $lc_row[2] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<ul class="pk__multis">
				<?php foreach ( $lc_multipliers as $lc_multi ) : ?>
					<li class="pk__multi<?php echo $lc_multi[2] ? ' pk__multi--hi' : ''; ?>">
						<span class="pk__multi-num"><?php echo esc_html( $lc_multi[0] ); ?></span>
						<span class="pk__multi-label"><?php echo esc_html( $lc_multi[1] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="pk__note">
				<?php
				echo wp_kses(
					__( 'Those nine have delivered <strong>237,714 views, 13,159 watch hours and 4,356 new subscribers</strong>. The subscriber multiple is the one to sit with: this content converts strangers into an audience at nearly twelve times the rate of anything else on the channel.', 'learncardano' ),
					array( 'strong' => array() )
				);
				?>
			</p>
		</div>
	</section>

	<?php /* 5. Where the audience is + who they are ----------------- */ ?>
	<section class="pk__section pk__section--light" aria-labelledby="pk-where">
		<div class="pk__container">
			<header class="pk__head">
				<p class="pk__kicker"><?php esc_html_e( 'Where the audience actually is', 'learncardano' ); ?></p>
				<h2 id="pk-where" class="pk__h2"><?php esc_html_e( 'Australia is now the channel’s #1 market, and has been for six months.', 'learncardano' ); ?></h2>
			</header>
			<div class="pk__split">
				<figure class="pk__chart">
					<?php
					// Drawn as SVG (not the deck image) so the labels stay legible and the scale is true.
					$lc_au_points = array(
						array( __( 'Lifetime', 'learncardano' ), 16.8 ),
						array( __( 'Last 12 mo', 'learncardano' ), 27.1 ),
						array( __( 'Last 6 mo', 'learncardano' ), 34.6 ),
						array( __( 'Last 90 days', 'learncardano' ), 42.4 ),
					);
					$lc_y      = function ( $v ) {
						return 290 - $v / 60 * 250;
					};
					$lc_coords = array();
					foreach ( $lc_au_points as $lc_i => $lc_pt ) {
						$lc_coords[] = array( 110 + $lc_i * 180, $lc_y( $lc_pt[1] ) );
					}
					$lc_line = implode( ' ', array_map( fn( $c ) => $c[0] . ',' . round( $c[1], 1 ), $lc_coords ) );
					?>
					<svg class="pk__chart-svg" viewBox="0 0 700 340" role="img" aria-label="<?php esc_attr_e( 'Chart of the Australian share of channel views, rising from 16.8% lifetime to 42.4% over the last 90 days.', 'learncardano' ); ?>">
						<defs>
							<linearGradient id="pk-au-fill" x1="0" y1="0" x2="0" y2="1">
								<stop offset="0" stop-color="#a855f7" stop-opacity="0.95"/>
								<stop offset="1" stop-color="#7b2cbf" stop-opacity="0.75"/>
							</linearGradient>
						</defs>
						<rect width="700" height="340" rx="14" fill="#0b0d1a"/>
						<?php foreach ( array( 0, 25, 50 ) as $lc_g ) : ?>
							<line x1="70" x2="670" y1="<?php echo esc_attr( $lc_y( $lc_g ) ); ?>" y2="<?php echo esc_attr( $lc_y( $lc_g ) ); ?>" stroke="#ffffff" stroke-opacity="0.18"/>
							<text x="58" y="<?php echo esc_attr( $lc_y( $lc_g ) + 4 ); ?>" text-anchor="end" fill="#b9b9c6" font-size="13"><?php echo esc_html( $lc_g . '%' ); ?></text>
						<?php endforeach; ?>
						<polygon points="<?php echo esc_attr( '110,' . $lc_y( 0 ) . ' ' . $lc_line . ' 650,' . $lc_y( 0 ) ); ?>" fill="url(#pk-au-fill)"/>
						<polyline points="<?php echo esc_attr( $lc_line ); ?>" fill="none" stroke="#e9d5ff" stroke-width="3"/>
						<?php foreach ( $lc_au_points as $lc_i => $lc_pt ) : ?>
							<circle cx="<?php echo esc_attr( $lc_coords[ $lc_i ][0] ); ?>" cy="<?php echo esc_attr( round( $lc_coords[ $lc_i ][1], 1 ) ); ?>" r="7" fill="#ffffff" stroke="#7b2cbf" stroke-width="3"/>
							<text x="<?php echo esc_attr( $lc_coords[ $lc_i ][0] ); ?>" y="<?php echo esc_attr( round( $lc_coords[ $lc_i ][1] - 16, 1 ) ); ?>" text-anchor="middle" fill="#ffffff" font-size="22" font-weight="800" font-family="Chivo, sans-serif"><?php echo esc_html( $lc_pt[1] . '%' ); ?></text>
							<text x="<?php echo esc_attr( $lc_coords[ $lc_i ][0] ); ?>" y="320" text-anchor="middle" fill="<?php echo 3 === $lc_i ? '#e9d5ff' : '#b9b9c6'; ?>" font-size="14" font-weight="<?php echo 3 === $lc_i ? '700' : '400'; ?>"><?php echo esc_html( $lc_pt[0] ); ?></text>
						<?php endforeach; ?>
					</svg>
					<figcaption><?php esc_html_e( 'Australian share of views: 16.8% lifetime → 42.4% in the last 90 days.', 'learncardano' ); ?></figcaption>
				</figure>
				<div>
					<p class="pk__lede"><?php esc_html_e( 'And during the exact content a sponsor would appear in, it goes further:', 'learncardano' ); ?></p>
					<div class="pk__callouts">
						<div class="pk__callout">
							<span class="pk__big">52.1%</span>
							<span><strong><?php esc_html_e( 'Australian', 'learncardano' ); ?></strong> · <?php esc_html_e( '18 Jun to 18 Jul 2026', 'learncardano' ); ?><br><?php esc_html_e( 'ATO case + High Court ruling', 'learncardano' ); ?></span>
						</div>
						<div class="pk__callout">
							<span class="pk__big">71.9%</span>
							<span><strong><?php esc_html_e( 'Australian', 'learncardano' ); ?></strong> · <?php esc_html_e( '22 May to 21 Jun 2025', 'learncardano' ); ?><br><?php esc_html_e( 'Landmark Bitcoin tax ruling', 'learncardano' ); ?></span>
						</div>
					</div>
					<p class="pk__note"><?php esc_html_e( 'Channel-wide the Australian share is smaller, but it climbs sharply during exactly the content a sponsor would appear in. You wouldn’t be buying a global channel and hoping for Australian spill, you’d be buying the slot where Australians are the majority of the room.', 'learncardano' ); ?></p>				</div>
			</div>

			<h3 class="pk__subhead" id="pk-who"><?php esc_html_e( 'Who they are', 'learncardano' ); ?></h3>
			<ul class="pk__grid pk__grid--4 pk__demo">
				<?php foreach ( $lc_demo as $lc_d ) : ?>
					<li class="pk__card pk__card--stat">
						<span class="pk__big"><?php echo esc_html( $lc_d[0] ); ?></span>
						<span><?php echo esc_html( $lc_d[1] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="pk__cols-2">
				<p><?php esc_html_e( 'The age profile is the point. This is an audience with assets to borrow against and income to service a facility: the 35 to 54 bracket, the biggest block of this audience by both views and watch time.', 'learncardano' ); ?></p>
				<p><?php esc_html_e( 'The gender skew is stated plainly rather than buried: a narrow, precisely-targeted audience, not a broad one. And effectively no under-18 viewership, which matters for any regulated financial advertiser.', 'learncardano' ); ?></p>
			</div>

			<h3 class="pk__subhead"><?php esc_html_e( 'How they watch', 'learncardano' ); ?></h3>
			<ul class="pk__grid pk__grid--4 pk__demo">
				<?php foreach ( $lc_habits as $lc_h ) : ?>
					<li class="pk__card pk__card--stat">
						<span class="pk__big"><?php echo esc_html( $lc_h[0] ); ?></span>
						<span><?php echo esc_html( $lc_h[1] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="pk__note"><?php echo esc_html( $lc_also_watches ); ?></p>
		</div>
	</section>

	<?php /* 6. Proof ---------------------------------------------- */ ?>
	<section class="pk__section pk__section--white" aria-labelledby="pk-proof">
		<div class="pk__container">
			<header class="pk__head">
				<p class="pk__kicker"><?php esc_html_e( 'Proof', 'learncardano' ); ?></p>
				<h2 id="pk-proof" class="pk__h2"><?php esc_html_e( 'The videos a sponsor would sit inside', 'learncardano' ); ?></h2>
			</header>
			<ul class="pk__grid pk__grid--3 pk__thumbs">
				<?php foreach ( $lc_proof as $lc_p ) : ?>
					<?php
					if ( ! $lc_p[4] ) {
						continue;
					}
					?>
					<li class="pk__thumb">
						<?php
						/* translators: %s: video title */
						echo $lc_img( $lc_p[4][0], $lc_p[4][1], $lc_p[4][2], sprintf( __( 'YouTube thumbnail for “%s”', 'learncardano' ), $lc_p[0] ), 'pk__thumb-img' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_img.
						?>
						<p><span class="pk__thumb-title"><?php echo esc_html( $lc_p[0] ); ?></span> <strong>
						<?php
						/* translators: %s: view count */
						echo esc_html( sprintf( __( '%s views', 'learncardano' ), $lc_p[2] ) );
						?>
						</strong></p>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="pk__table-wrap">
				<table class="pk__table pk__proof">
					<caption class="pk__sr"><?php esc_html_e( 'Top videos by lifetime views, with average view duration', 'learncardano' ); ?></caption>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Video', 'learncardano' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Views', 'learncardano' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Avg view duration', 'learncardano' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $lc_proof as $lc_p ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $lc_p[0] ); ?> <span class="pk__muted">· <?php echo esc_html( $lc_p[1] ); ?></span></th>
								<td class="pk__strong"><?php echo esc_html( $lc_p[2] ); ?></td>
								<td><?php echo esc_html( $lc_p[3] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="pk__note">
				<?php
				echo wp_kses(
					__( 'In June & July 2026 (the ATO case, the High Court ruling and the new tax rules), the channel recorded <strong>141,331</strong> and <strong>131,299</strong> monthly views, against a Jan to May average near 36,000.', 'learncardano' ),
					array( 'strong' => array() )
				);
				?>
			</p>
		</div>
	</section>

	<?php /* 7. Click-through & conversion -------------------------- */ ?>
	<section class="pk__section pk__section--dark" aria-labelledby="pk-ctr">
		<div class="pk__container">
			<header class="pk__head">
				<p class="pk__kicker"><?php esc_html_e( 'Click-through & conversion', 'learncardano' ); ?></p>
				<h2 id="pk-ctr" class="pk__h2"><?php esc_html_e( 'One documented case. The rate and the speed are the story, not the volume.', 'learncardano' ); ?></h2>
			</header>
			<ul class="pk__grid pk__grid--4 pk__bigstats">
				<?php foreach ( $lc_conversion as $lc_c ) : ?>
					<li>
						<span class="pk__big"><?php echo esc_html( $lc_c[0] ); ?></span>
						<span><?php echo esc_html( $lc_c[1] ); ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
			<div class="pk__grid pk__grid--3 pk__case">
				<div class="pk__card pk__card--dark">
					<h3 class="pk__label"><?php esc_html_e( 'The case', 'learncardano' ); ?></h3>
					<p>
						<?php
						echo wp_kses(
							__( 'July 2026. The Cardano DeFi exploits broke and we published coverage the same week, with hardware wallet links in the description. Click-throughs went from a two-to-five-a-day baseline to <strong>68 in a single day</strong>. Five verified purchases landed at the merchant inside 72 hours: USD $868 in order value, average order USD $157.', 'learncardano' ),
							array( 'strong' => array() )
						);
						?>
					</p>
				</div>
				<div class="pk__card pk__card--dark">
					<h3 class="pk__label"><?php esc_html_e( 'What it does show', 'learncardano' ); ?></h3>
					<p><?php esc_html_e( 'Given a reason to act during a news moment, this audience acts within days, not weeks. It is the clearest available evidence for the rapid-response slot: the same 48-hour window that produces the biggest view numbers also produces the buying.', 'learncardano' ); ?></p>
				</div>
				<div class="pk__card pk__card--dark pk__card--caveat">
					<h3 class="pk__label"><?php esc_html_e( 'What this is not', 'learncardano' ); ?></h3>
					<p><?php esc_html_e( 'A campaign. There was no brief, no budget, no on-screen mention and no call to action beyond a URL in a description. It is one product category and a low-consideration purchase. Treat it as a directional signal on audience behaviour, not a forecast.', 'learncardano' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<?php /* 8. These videos land fast ----------------------------- */ ?>
	<section class="pk__section pk__section--light" aria-labelledby="pk-fast">
		<div class="pk__container">
			<header class="pk__head">
				<p class="pk__kicker"><?php esc_html_e( 'These videos land fast', 'learncardano' ); ?></p>
				<h2 id="pk-fast" class="pk__h2"><?php esc_html_e( 'Australian regulatory news front-loads harder than anything else on the channel.', 'learncardano' ); ?></h2>
			</header>
			<div class="pk__split">
				<figure class="pk__chart">
					<?php echo $lc_img( 'views-front-load-chart.jpg', 1400, 683, __( 'Cumulative share of total views by day since publication: “Why the ATO Is Panicking” reached 62% of its views by day 7 and “Bitcoin Tax Free” 65%.', 'learncardano' ), 'pk__chart-img' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_img. ?>
					<figcaption><?php esc_html_e( 'Cumulative share of total views: ATO Is Panicking: 62% at day 7 · Bitcoin Tax Free: 65% at day 7.', 'learncardano' ); ?></figcaption>
				</figure>
				<ol class="pk__points">
					<li><?php esc_html_e( 'Results are measurable inside 30 days, which is why the delivery guarantee is written against a 30-day window.', 'learncardano' ); ?></li>
					<li><?php esc_html_e( 'The biggest numbers come from reacting to news, not a fixed calendar, which is why this proposal includes retained rapid-response slots.', 'learncardano' ); ?></li>
				</ol>
			</div>
		</div>
	</section>

	<?php /* 9. Editorial calendar --------------------------------- */ ?>
	<section class="pk__section pk__section--white" aria-labelledby="pk-calendar">
		<div class="pk__container">
			<header class="pk__head pk__head--split">
				<div>
					<p class="pk__kicker"><?php esc_html_e( 'Upcoming editorial calendar', 'learncardano' ); ?></p>
					<h2 id="pk-calendar" class="pk__h2"><?php esc_html_e( 'The calendar is already set. You can pick your moment in advance.', 'learncardano' ); ?></h2>
				</div>
				<p class="pk__lede"><?php esc_html_e( 'Planned to 2 December · 32 posts', 'learncardano' ); ?></p>
			</header>
			<div class="pk__grid pk__grid--4">
				<?php foreach ( $lc_calendar as $lc_month ) : ?>
					<div class="pk__card pk__month">
						<h3 class="pk__h3"><?php echo esc_html( $lc_month[0] ); ?></h3>
						<p class="pk__tag"><?php echo esc_html( $lc_month[1] ); ?></p>
						<ul>
							<?php foreach ( $lc_month[2] as $lc_topic ) : ?>
								<li><?php echo esc_html( $lc_topic ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="pk__note">
				<?php
				echo wp_kses(
					__( 'Peak intent windows are known in advance: <strong>the 31 October lodgement deadline</strong>, and the November structure arc when viewers are actively reorganising holdings.', 'learncardano' ),
					array( 'strong' => array() )
				);
				?>
			</p>
		</div>
	</section>

	<?php /* 10. Where a sponsor fits ------------------------------- */ ?>
	<section class="pk__section pk__section--light" aria-labelledby="pk-fits">
		<div class="pk__container">
			<header class="pk__head">
				<p class="pk__kicker"><?php esc_html_e( 'Where a sponsor fits', 'learncardano' ); ?></p>
				<h2 id="pk-fits" class="pk__h2"><?php esc_html_e( 'Three ways to appear. Most campaigns use all three.', 'learncardano' ); ?></h2>
			</header>
			<div class="pk__grid pk__grid--3">
				<?php foreach ( $lc_fits as $lc_i => $lc_fit ) : ?>
					<div class="pk__card pk__card--fit">
						<span class="pk__card-num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $lc_i + 1 ) ); ?></span>
						<h3 class="pk__h3"><?php echo esc_html( $lc_fit[0] ); ?></h3>
						<p><?php echo esc_html( $lc_fit[1] ); ?></p>
						<span class="pk__tag"><?php echo esc_html( $lc_fit[2] ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="pk__note pk__note--center">
				<?php
				echo wp_kses(
					__( 'Every placement is scripted around <strong>explaining how something works</strong>, with your product as the worked example, never a recommendation, never a projection.', 'learncardano' ),
					array( 'strong' => array() )
				);
				?>
			</p>
		</div>
	</section>

	<?php /* 11. Rate card ------------------------------------------ */ ?>
	<section class="pk__section pk__section--white" id="rates" aria-labelledby="pk-rates">
		<div class="pk__container">
			<header class="pk__head pk__head--split">
				<div>
					<p class="pk__kicker"><?php esc_html_e( 'Rate card', 'learncardano' ); ?></p>
					<h2 id="pk-rates" class="pk__h2"><?php esc_html_e( 'Single placements and add-ons', 'learncardano' ); ?></h2>
				</div>
				<p class="pk__lede pk__strong"><?php esc_html_e( 'All rates AUD, excluding GST', 'learncardano' ); ?></p>
			</header>
			<?php $lc_gate_open(); ?>
				<div class="pk__rate-cols">
					<?php
					$lc_rate_table( __( 'Single placements', 'learncardano' ), $lc_rates_single );
					$lc_rate_table( __( 'Add-ons', 'learncardano' ), $lc_rates_addons );
					?>
				</div>
				<p class="pk__fineprint"><?php echo $lc_mask( $lc_cpm_note, $lc_cpm_note_nums ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_mask. ?></p>
			<?php $lc_gate_close(); ?>

			<?php $lc_gate_open( array( 'dark', 'value' ) ); ?>
				<div class="pk__value">
					<div class="pk__value-intro">
						<p class="pk__kicker"><?php esc_html_e( 'The dedicated video', 'learncardano' ); ?></p>
						<h3 id="pk-value" class="pk__h3">
							<?php
							/* translators: %s: dedicated video price */
							echo $lc_mask( __( 'Why %s is worth it', 'learncardano' ), array( $lc_dedicated_price ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_mask.
							?>
						</h3>
						<p><?php esc_html_e( 'A 6 to 8 minute video where your brand is the subject (not a 60-second segment), planned against the category median of roughly 20 to 21K views in the first 30 days. Here is the arithmetic, done in the open.', 'learncardano' ); ?></p>
						<ul class="pk__ticks">
							<li><?php echo $lc_check; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php esc_html_e( 'Cohort averages: 26,413 views, 1,462 watch hours and 484 new subscribers per video', 'learncardano' ); ?></li>
							<li><?php echo $lc_check; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Evergreen search traffic keeps adding views for years after the 30-day window', 'learncardano' ); ?></li>
							<li><?php echo $lc_check; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Your compliance team signs off the script, with two revision rounds included', 'learncardano' ); ?></li>
							<li><?php echo $lc_check; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php esc_html_e( 'Backed by the written 30-day delivery guarantee', 'learncardano' ); ?></li>
						</ul>
					</div>
					<div class="pk__table-wrap">
						<table class="pk__table pk__math">
							<caption>
								<?php
								/* translators: %s: dedicated video price */
								echo $lc_mask( __( 'What %s buys, per unit (AUD)', 'learncardano' ), array( $lc_dedicated_price ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_mask.
								?>
							</caption>
							<thead>
								<tr>
									<th scope="col"><?php esc_html_e( 'Measure', 'learncardano' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Sum', 'learncardano' ); ?></th>
									<th scope="col"><?php esc_html_e( 'Result', 'learncardano' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $lc_value_math as $lc_m ) : ?>
									<tr>
										<th scope="row"><?php echo esc_html( $lc_m[0] ); ?></th>
										<td class="pk__muted"><?php echo $lc_num( $lc_m[1] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_num. ?></td>
										<td class="pk__strong"><?php echo $lc_num( $lc_m[2] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_num. ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<p class="pk__fineprint"><?php esc_html_e( 'Derived from the media kit figures above (Australian tax & ruling cohort averages and medians); results rounded. Past performance of the cohort, not a forecast for any single video. The delivery guarantee is the commitment.', 'learncardano' ); ?></p>
					</div>
				</div>
			<?php $lc_gate_close(); ?>
		</div>
	</section>

	<?php /* 12. Campaign packages ----------------------------------- */ ?>
	<section class="pk__section pk__section--light" aria-labelledby="pk-packages">
		<div class="pk__container">
			<header class="pk__head">
				<p class="pk__kicker"><?php esc_html_e( 'Campaign packages', 'learncardano' ); ?></p>
				<h2 id="pk-packages" class="pk__h2"><?php esc_html_e( 'Three packages, each priced below à la carte.', 'learncardano' ); ?></h2>
			</header>
			<?php $lc_gate_open(); ?>
				<div class="pk__grid pk__grid--3 pk__packages">
					<?php foreach ( $lc_packages as $lc_pkg ) : ?>
						<div class="pk__pkg<?php echo $lc_pkg['recommended'] ? ' pk__pkg--hi' : ''; ?>">
							<?php if ( $lc_pkg['recommended'] ) : ?>
								<span class="pk__badge"><?php esc_html_e( 'Recommended', 'learncardano' ); ?></span>
							<?php endif; ?>
							<p class="pk__pkg-letter">
								<?php
								/* translators: %s: package letter */
								echo esc_html( sprintf( __( 'Package %s', 'learncardano' ), $lc_pkg['letter'] ) );
								?>
							</p>
							<h3 class="pk__pkg-name"><?php echo esc_html( $lc_pkg['name'] ); ?></h3>
							<p class="pk__pkg-term"><?php echo esc_html( $lc_pkg['term'] ); ?></p>
							<ul class="pk__ticks">
								<?php foreach ( $lc_pkg['items'] as $lc_item ) : ?>
									<li><?php echo $lc_check; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php echo esc_html( $lc_item ); ?></li>
								<?php endforeach; ?>
							</ul>
							<p class="pk__pkg-price">
								<span class="pk__pkg-was"><?php esc_html_e( 'À la carte', 'learncardano' ); ?> <s><?php echo $lc_num( $lc_pkg['a_la_carte'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_num. ?></s></span>
								<span class="pk__pkg-now"><?php echo $lc_num( $lc_pkg['price'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_num. ?></span>
							</p>
						</div>
					<?php endforeach; ?>
				</div>
			<?php $lc_gate_close(); ?>

			<div class="pk__grid pk__grid--2 pk__terms-boxes">
				<div class="pk__rr">
					<h3 class="pk__label">
						<?php esc_html_e( 'The rapid-response slot', 'learncardano' ); ?>
						<a class="pk__rr-price pk__rr-lock" href="<?php echo esc_url( $lc_media_kit_url ); ?>" target="_blank" rel="noopener"><span class="pk__num pk__num--locked" aria-hidden="true"><?php echo esc_html( $lc_rr_price ); ?></span><span class="pk__sr"><?php esc_html_e( 'Price in the media kit (opens in a new tab)', 'learncardano' ); ?></span></a>
					</h3>
					<p>
						<?php
						echo wp_kses(
							__( 'The highest-value item on the card, and no other creator in this market sells it. When the next ATO ruling or Treasury announcement lands, Learn Crypto publishes <strong>within 48 hours</strong> with the sponsor already integrated, not waiting on a content calendar. The ATO case took 27,628 views in 7 days. Slots are retained, not scheduled; unused slots roll forward.', 'learncardano' ),
							array( 'strong' => array() )
						);
						?>
					</p>
				</div>
				<div class="pk__guarantee">
					<h3 class="pk__label"><?php esc_html_e( 'Delivery guarantee', 'learncardano' ); ?></h3>
					<p>
						<?php
						echo wp_kses(
							__( 'Dedicated placements are planned against <strong>20,000 views in the first 30 days</strong> (the category median). Across a package, Learn Crypto guarantees <strong>12,000 views per dedicated placement in aggregate over the term</strong>, within 30 days of each publication, or supplies additional dedicated content at no charge until it is met. A floor almost no creator at this size will put in writing.', 'learncardano' ),
							array( 'strong' => array() )
						);
						?>
					</p>
				</div>
			</div>
		</div>
	</section>

	<?php /* 13. Compliance & commercial terms ----------------------- */ ?>
	<section class="pk__section pk__section--dark" aria-labelledby="pk-compliance">
		<div class="pk__container">
			<header class="pk__head">
				<p class="pk__kicker"><?php esc_html_e( 'Compliance & disclosure', 'learncardano' ); ?></p>
				<h2 id="pk-compliance" class="pk__h2"><?php esc_html_e( 'Built for regulated advertisers. This is a reason to sign, not a disclaimer.', 'learncardano' ); ?></h2>
			</header>
			<div class="pk__grid pk__grid--3">
				<?php foreach ( $lc_regimes as $lc_reg ) : ?>
					<div class="pk__card pk__card--dark">
						<h3 class="pk__label"><?php echo esc_html( $lc_reg[0] ); ?></h3>
						<p><?php echo esc_html( $lc_reg[1] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="pk__practice">
				<h3 class="pk__label"><?php esc_html_e( 'Standing practice on every placement', 'learncardano' ); ?></h3>
				<ul class="pk__ticks">
					<?php foreach ( $lc_practice as $lc_item ) : ?>
						<li><?php echo $lc_check; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php echo esc_html( $lc_item ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>

			<h3 class="pk__subhead" id="pk-terms"><?php esc_html_e( 'Commercial terms', 'learncardano' ); ?></h3>
			<div class="pk__accordions">
				<?php foreach ( $lc_terms as $lc_group => $lc_items ) : ?>
					<details class="pk__details">
						<summary><?php echo esc_html( $lc_group ); ?></summary>
						<ul>
							<?php foreach ( $lc_items as $lc_item ) : ?>
								<li><?php echo esc_html( $lc_item ); ?></li>
							<?php endforeach; ?>
						</ul>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<?php /* 14. Who I am -------------------------------------------- */ ?>
	<section class="pk__section pk__section--white" aria-labelledby="pk-who-am-i">
		<div class="pk__container pk__bio">
			<div class="pk__bio-photo">
				<?php echo $lc_img( 'peter-bui-portrait.jpg', 600, 600, __( 'Portrait of Peter Bui, founder of Learn Crypto', 'learncardano' ), 'pk__portrait' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $lc_img. ?>
			</div>
			<div>
				<p class="pk__kicker"><?php esc_html_e( 'Who I am', 'learncardano' ); ?></p>
				<h2 id="pk-who-am-i" class="pk__h2"><?php esc_html_e( 'Peter Bui', 'learncardano' ); ?></h2>
				<p class="pk__bio-meta"><?php esc_html_e( 'Gold Coast, Queensland · Podcasting since 2013 · Learn Crypto (formerly Learn Cardano) since Feb 2021 · 853 videos', 'learncardano' ); ?></p>
				<ul class="pk__ticks pk__ticks--2">
					<?php foreach ( $lc_bio as $lc_item ) : ?>
						<li><?php echo $lc_check; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php echo esc_html( $lc_item ); ?></li>
					<?php endforeach; ?>
				</ul>
				<?php // Keep this to the personal training fact (owner's decision, Oct 2026). ?>
				<p class="pk__note"><?php esc_html_e( 'Completed founder training at Draper University (Cardano Genesis program).', 'learncardano' ); ?></p>
				<p class="pk__statement"><?php esc_html_e( 'Not a trader. Not a signals account. An educator with a decade of published work and a public track record.', 'learncardano' ); ?></p>
				<p class="pk__follow"><a href="<?php echo esc_url( $lc_chatsoon_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Follow Peter on Chatsoon', 'learncardano' ); ?> <span aria-hidden="true">→</span><span class="pk__sr"> <?php esc_html_e( '(opens in a new tab)', 'learncardano' ); ?></span></a></p>
			</div>
		</div>
	</section>

	<?php /* 15. Glossary ------------------------------------------- */ ?>
	<section class="pk__section pk__section--light pk__section--tight" aria-labelledby="pk-glossary">
		<div class="pk__container">
			<h2 id="pk-glossary" class="pk__h3"><?php esc_html_e( 'Glossary: what each term means', 'learncardano' ); ?></h2>
			<details class="pk__details pk__details--big">
				<summary><?php esc_html_e( 'Show all definitions', 'learncardano' ); ?></summary>
				<div class="pk__glossary">
					<?php foreach ( $lc_glossary as $lc_group => $lc_items ) : ?>
						<div>
							<h3 class="pk__label"><?php echo esc_html( $lc_group ); ?></h3>
							<dl>
								<?php foreach ( $lc_items as $lc_term => $lc_def ) : ?>
									<div><dt><?php echo esc_html( $lc_term ); ?></dt><dd><?php echo esc_html( $lc_def ); ?></dd></div>
								<?php endforeach; ?>
							</dl>
						</div>
					<?php endforeach; ?>
				</div>
			</details>
		</div>
	</section>

	<?php /* 16. Project interviews (free editorial queue) ----------- */ ?>
	<section class="pk__section pk__section--white" id="interviews" aria-labelledby="pk-interviews">
		<div class="pk__container">
			<header class="pk__head pk__head--split">
				<div>
					<p class="pk__kicker"><?php esc_html_e( 'Project interviews', 'learncardano' ); ?></p>
					<h2 id="pk-interviews" class="pk__h2"><?php esc_html_e( 'Building in crypto, on Cardano, Midnight or beyond?', 'learncardano' ); ?></h2>
				</div>
				<p class="pk__lede"><?php esc_html_e( 'Separate from sponsorship: credible projects with a track record of delivering real value can join our free editorial interview queue. We publish roughly 3 a month, and vetting takes 1 to 2 weeks. The Interviews playlist has drawn 497.2K views, and the Cardano News & Crypto Update podcast 1.8M.', 'learncardano' ); ?></p>
			</header>

			<h3 class="pk__subhead"><?php esc_html_e( 'What’s included', 'learncardano' ); ?></h3>
			<div class="pk__grid pk__grid--3">
				<?php foreach ( $lc_interview_includes as $lc_inc ) : ?>
					<div class="pk__card pk__card--mini">
						<h4 class="pk__h4"><?php echo esc_html( $lc_inc[0] ); ?></h4>
						<p><?php echo esc_html( $lc_inc[1] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="pk__accordions pk__accordions--light">
				<details class="pk__details">
					<summary><?php esc_html_e( 'Editorial standards: what we don’t cover', 'learncardano' ); ?></summary>
					<ul class="pk__crosses">
						<?php foreach ( $lc_interview_standards as $lc_item ) : ?>
							<li><?php echo $lc_cross; // phpcs:ignore WordPress.Security.EscapeOutput -- static SVG. ?><?php echo esc_html( $lc_item ); ?></li>
						<?php endforeach; ?>
					</ul>
					<p><?php esc_html_e( 'Learn Crypto exists to surface real innovation across crypto, with Cardano and Midnight at its core. Our audience trusts us to filter signal from noise, so every editorial interview has to represent genuine value, not marketing spend. Paid placements are a separate product and are always disclosed (see Compliance & disclosure above).', 'learncardano' ); ?></p>
				</details>
				<details class="pk__details">
					<summary><?php esc_html_e( 'How we vet projects', 'learncardano' ); ?></summary>
					<p><?php esc_html_e( 'Every submission goes through editorial review. We assess technical merit, team credibility, ecosystem contribution and alignment with the values of the wider crypto community, including Cardano and Midnight, and we prioritise projects that are shipping, not talking. If your project doesn’t fit, we’ll tell you within 1 to 2 weeks with transparent feedback.', 'learncardano' ); ?></p>
				</details>
				<details class="pk__details">
					<summary><?php esc_html_e( 'Recording & logistics', 'learncardano' ); ?></summary>
					<ul>
						<li><?php esc_html_e( 'Interviews run 30 to 60 minutes.', 'learncardano' ); ?></li>
						<li>
							<?php
							echo wp_kses(
								__( 'Recorded on <a href="https://riverside.com/" rel="noopener">Riverside</a>, we handle setup and recording.', 'learncardano' ),
								array(
									'a' => array(
										'href' => array(),
										'rel'  => array(),
									),
								)
							);
							?>
						</li>
						<li><?php esc_html_e( 'You receive a copy of the raw video for your own use.', 'learncardano' ); ?></li>
						<li><?php esc_html_e( 'We handle all editing, thumbnails and publishing.', 'learncardano' ); ?></li>
					</ul>
				</details>
				<details class="pk__details">
					<summary><?php esc_html_e( 'Exclusivity & republishing', 'learncardano' ); ?></summary>
					<p><?php esc_html_e( 'Interviews are exclusive to Learn Crypto for 7 days after the YouTube release. After that you’re welcome to republish them on your own channels and platforms.', 'learncardano' ); ?></p>
				</details>
				<details class="pk__details">
					<summary><?php esc_html_e( 'Cancellations & rescheduling', 'learncardano' ); ?></summary>
					<p><?php esc_html_e( 'We understand things happen. Free interviews can be rescheduled with 6 hours’ notice.', 'learncardano' ); ?></p>
				</details>
				<details class="pk__details">
					<summary><?php esc_html_e( 'Prefer to message?', 'learncardano' ); ?></summary>
					<p>
						<?php
						printf(
							/* translators: %s: Chatsoon profile link */
							esc_html__( '%s with:', 'learncardano' ),
							'<a href="' . esc_url( $lc_chatsoon_url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Contact Peter on Chatsoon', 'learncardano' ) . '<span class="pk__sr"> ' . esc_html__( '(opens in a new tab)', 'learncardano' ) . '</span></a>'
						);
						?>
					</p>
					<ul>
						<li><?php esc_html_e( 'A brief overview of your project', 'learncardano' ); ?></li>
						<li><?php esc_html_e( 'Why an interview would benefit the wider crypto community, including Cardano and Midnight', 'learncardano' ); ?></li>
						<li><?php esc_html_e( 'Proposed topics or angles', 'learncardano' ); ?></li>
					</ul>
					<p><?php esc_html_e( 'We’ll respond within 3 business days.', 'learncardano' ); ?></p>
				</details>
			</div>
			<p class="pk__note">
				<?php
				echo wp_kses(
					__( '<strong>Support us while you wait:</strong> buy us a coffee or become a member on <a href="https://buymeacoffee.com/learncardano" rel="noopener">Buy Me a Coffee</a>.', 'learncardano' ),
					array(
						'strong' => array(),
						'a'      => array(
							'href' => array(),
							'rel'  => array(),
						),
					)
				);
				?>
			</p>
		</div>
	</section>

	<?php /* 17. Next steps + booking -------------------------------- */ ?>
	<section class="pk__section pk__section--dark" id="book" aria-labelledby="pk-book">
		<div class="pk__container">
			<header class="pk__head">
				<p class="pk__kicker"><?php esc_html_e( 'Next steps', 'learncardano' ); ?></p>
				<h2 id="pk-book" class="pk__h2"><?php esc_html_e( 'From first call to published campaign', 'learncardano' ); ?></h2>
			</header>
			<ol class="pk__steps">
				<?php foreach ( $lc_steps as $lc_step ) : ?>
					<li><?php echo esc_html( $lc_step ); ?></li>
				<?php endforeach; ?>
			</ol>
			<div class="pk__booking">
				<div class="pk__booking-copy">
					<h3 class="pk__h3"><?php esc_html_e( 'Book a 15-min call', 'learncardano' ); ?></h3>
					<p><?php esc_html_e( 'Sponsors and projects alike: pick a time and tell us what you want to achieve. We’ll come prepared.', 'learncardano' ); ?></p>
					<div class="pk__kit-cta">
						<p><?php esc_html_e( 'Want rates and package pricing first? They’re in the media kit.', 'learncardano' ); ?></p>
						<a class="pk__btn pk__btn--kit" href="<?php echo esc_url( $lc_media_kit_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get the media kit', 'learncardano' ); ?> <span aria-hidden="true">→</span><span class="pk__sr"> <?php esc_html_e( '(opens in a new tab)', 'learncardano' ); ?></span></a>
					</div>
					<p class="pk__contact">
						<strong><?php esc_html_e( 'Peter Bui', 'learncardano' ); ?></strong><br>
						<a href="<?php echo esc_url( $lc_chatsoon_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Contact Peter on Chatsoon', 'learncardano' ); ?><span class="pk__sr"> <?php esc_html_e( '(opens in a new tab)', 'learncardano' ); ?></span></a> · <a href="<?php echo esc_url( home_url( '/' ) ); ?>">learncardano.io</a>
					</p>
					<p class="pk__fineprint">
						<a href="<?php echo esc_url( $lc_calendly_url ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Calendar not loading? Open it in a new tab', 'learncardano' ); ?><span class="pk__sr"> <?php esc_html_e( '(opens in a new tab)', 'learncardano' ); ?></span></a>
					</p>
				</div>
				<div class="pk__calendly calendly-inline-widget" data-url="<?php echo esc_url( $lc_calendly_url ); ?>"></div>
			</div>
			<p class="pk__disclaimer"><?php esc_html_e( 'Not financial, legal or tax advice. The compliance approach here is a starting position, not a legal opinion.', 'learncardano' ); ?></p>
		</div>
	</section>

</main><!-- .pk -->

<?php

get_footer();
