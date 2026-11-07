// Builds the public brand assets page files (assets/brand-kit/) from Pete's
// full brand kit. Only a short list of the kit is published: logos, icons,
// podcast cover and usage examples. Templates, AI notes and the style guide PDF
// stay private.
//
// Usage: node tools/brand-kit/build.mjs "<path to unzipped 'learn crypto podcast brand kit'>"
// Needs sharp: set SHARP_DIR to a node_modules folder that has it.
import { createRequire } from 'module';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const require = createRequire( import.meta.url );
const sharp = require( path.join( process.env.SHARP_DIR || '.', 'sharp', 'lib', 'index.js' ) );

const KIT = process.argv[ 2 ];
if ( ! KIT || ! fs.existsSync( path.join( KIT, 'logos' ) ) ) {
	console.error( 'Pass the unzipped brand kit folder.' );
	process.exit( 1 );
}
const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '../..' );
const OUT = path.join( ROOT, 'assets/brand-kit' );
const DL = path.join( OUT, 'download' );
const EX = path.join( OUT, 'examples' );
for ( const d of [ OUT, DL, EX ] ) fs.mkdirSync( d, { recursive: true } );

// Kit file -> published name (SVG and PNG of each).
const PAIRS = [
	[ 'logos/%s/primary-stacked-transparent', 'learn-crypto-logo-stacked-colour' ],
	[ 'logos/%s/primary-stacked-one-colour-white', 'learn-crypto-logo-stacked-white' ],
	[ 'logos/%s/primary-stacked-one-colour-navy', 'learn-crypto-logo-stacked-navy' ],
	[ 'logos/%s/horizontal-transparent', 'learn-crypto-logo-horizontal-colour' ],
	[ 'logos/%s/horizontal-one-colour-white', 'learn-crypto-logo-horizontal-white' ],
	[ 'logos/%s/horizontal-one-colour-navy', 'learn-crypto-logo-horizontal-navy' ],
	[ 'icons/microphone-gradient', 'learn-crypto-icon-colour' ],
	[ 'icons/microphone-white', 'learn-crypto-icon-white' ],
	[ 'icons/microphone-navy', 'learn-crypto-icon-navy' ],
	[ 'icons/avatar-gradient', 'learn-crypto-avatar' ],
];
for ( const [ src, name ] of PAIRS ) {
	for ( const ext of [ 'svg', 'png' ] ) {
		const from = path.join( KIT, src.replace( '%s', ext ) + '.' + ext );
		fs.copyFileSync( from, path.join( DL, name + '.' + ext ) );
	}
}
fs.copyFileSync( path.join( KIT, 'templates/podcast-cover-3000.png' ), path.join( DL, 'learn-crypto-podcast-cover-3000.png' ) );

// Inner markup of a kit SVG (everything inside the root <svg>), with ids
// prefixed so two logos can sit in one file.
function inner( file, prefix ) {
	const s = fs.readFileSync( path.join( KIT, file ), 'utf8' );
	const body = s.replace( /^[\s\S]*?<svg[^>]*>/, '' ).replace( /<\/svg>\s*$/, '' );
	return body.replace( /id="(\w+)"/g, `id="${ prefix }$1"` ).replace( /url\(#(\w+)\)/g, `url(#${ prefix }$1)` );
}

// Flat T-shirt mockup: front view, soft shading, artwork placed on the chest.
function tee( { shirt, shade, seam, art } ) {
	return `<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="1200" viewBox="0 0 1000 1000">
<rect width="1000" height="1000" fill="#F5F6FB"/>
<ellipse cx="500" cy="935" rx="300" ry="22" fill="#10134B" opacity=".08"/>
<defs><linearGradient id="fold" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="${ shade }" stop-opacity=".55"/><stop offset=".2" stop-color="${ shade }" stop-opacity="0"/><stop offset=".8" stop-color="${ shade }" stop-opacity="0"/><stop offset="1" stop-color="${ shade }" stop-opacity=".55"/></linearGradient></defs>
<path d="M398 110 C440 128 560 128 602 110 L604 132 C560 150 440 150 396 132 Z" fill="${ shade }"/>
<path id="body" d="M392 108 C430 150 570 150 608 108 L752 160 C800 178 832 210 852 252 L906 372 L782 428 L744 352 C736 520 738 720 748 892 C749 908 736 918 720 918 L280 918 C264 918 251 908 252 892 C262 720 264 520 256 352 L218 428 L94 372 L148 252 C168 210 200 178 248 160 Z" fill="${ shirt }" stroke="${ seam }" stroke-width="3"/>
<path d="M392 108 C430 150 570 150 608 108 L752 160 C800 178 832 210 852 252 L906 372 L782 428 L744 352 C736 520 738 720 748 892 C749 908 736 918 720 918 L280 918 C264 918 251 908 252 892 C262 720 264 520 256 352 L218 428 L94 372 L148 252 C168 210 200 178 248 160 Z" fill="url(#fold)"/>
<path d="M256 352 C262 300 258 230 248 160 M744 352 C738 300 742 230 752 160" fill="none" stroke="${ seam }" stroke-width="3"/>
<path d="M392 108 C430 150 570 150 608 108" fill="none" stroke="${ seam }" stroke-width="14" stroke-linecap="round"/>
<path d="M212 416 L104 368 M788 416 L896 368" stroke="${ seam }" stroke-width="4" stroke-linecap="round"/>
${ art }
</svg>`;
}

const examples = [
	{
		name: 'tee-navy-front',
		svg: tee( {
			shirt: '#10134B', shade: '#070927', seam: '#1c2160',
			art: `<svg x="370" y="200" width="260" height="260" viewBox="0 0 1200 1200">${ inner( 'logos/svg/primary-stacked-one-colour-white.svg', 'a' ) }</svg>`,
		} ),
	},
	{
		name: 'tee-white-chest',
		svg: tee( {
			shirt: '#FFFFFF', shade: '#d5d8e8', seam: '#d9dcea',
			art: `<svg x="565" y="215" width="105" height="105" viewBox="0 0 1024 1024">${ inner( 'icons/microphone-gradient.svg', 'b' ) }</svg>`,
		} ),
	},
	{
		name: 'tee-white-front',
		svg: tee( {
			shirt: '#FFFFFF', shade: '#d5d8e8', seam: '#d9dcea',
			art: `<svg x="360" y="190" width="280" height="280" viewBox="0 0 1200 1200">${ inner( 'logos/svg/primary-stacked-transparent.svg', 'c' ) }</svg>`,
		} ),
	},
];

for ( const ex of examples ) {
	for ( const w of [ 600, 1000 ] ) {
		await sharp( Buffer.from( ex.svg ) ).resize( w ).webp( { quality: 86 } ).toFile( path.join( EX, `${ ex.name }-${ w }.webp` ) );
	}
}

// Photo and digital examples from the kit.
const raster = [
	[ 'reference/approved-cap-mockup-reference.png', 'cap', [ 700, 1400 ] ],
	[ 'templates/podcast-cover-3000.png', 'podcast-cover', [ 600, 1000 ] ],
	[ 'templates/youtube-banner-2560x1440.png', 'youtube-banner', [ 800, 1600 ] ],
];
for ( const [ src, name, widths ] of raster ) {
	for ( const w of widths ) {
		await sharp( path.join( KIT, src ) ).resize( w ).webp( { quality: 84 } ).toFile( path.join( EX, `${ name }-${ w }.webp` ) );
	}
}

// Usage notes that go inside the zip.
fs.writeFileSync( path.join( OUT, 'README.txt' ), `Learn Crypto brand assets
https://learncardano.io/brand-assets/

Logos: stacked and horizontal, in full colour, white and navy (SVG and PNG).
Icon: the microphone mark in colour, white and navy, plus the round social avatar.
Podcast cover: 3000 x 3000 PNG.

Colours
Magenta  #E312F1
Violet   #7834F7
Cyan     #05B9F2
Navy     #10134B
White    #FFFFFF

Using the logo
- Use the files as supplied. Don't redraw, stretch, recolour, rotate or add effects.
- Keep clear space around the logo of at least 15% of the microphone's width.
- Smallest sizes: icon 24 px, stacked logo 180 px wide.
- Full colour logo on white or light backgrounds. White logo on navy or dark photos.
- The name is Learn Crypto. The show is Learn Crypto Podcast.
- Please don't use the logo in a way that suggests Learn Crypto endorses, sponsors or
  partners with you, unless we have agreed to it in writing.

Questions: https://learncardano.io/contact/
`.replace( /\n/g, '\r\n' ) );
console.log( 'Built', fs.readdirSync( DL ).length, 'downloads and', fs.readdirSync( EX ).length, 'example images.' );
