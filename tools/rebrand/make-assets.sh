#!/usr/bin/env bash
# Learn Crypto rebrand: derived image assets, built on the server with ImageMagick 7.
#
#   bash make-assets.sh <src-dir> <out-dir>
#
# <src-dir> must hold:
#   podcast-cover-3000.png                  square podcast cover, 3000x3000
#   social-thumbnail-background-1920x1080.png  social/OG background
#   horizontal-one-colour-white.png         horizontal logo in white (reads on the gradient)
#   primary-stacked-light.png               stacked logo for light backgrounds
#
# Writes to <out-dir>:
#   podcast-cover-360.jpg / .webp, podcast-cover-856.jpg / .webp
#   og-default-1200x630.jpg   background cover-cropped to 1200x630, horizontal logo centred at ~60% width
#   logo-square-600.png       stacked logo centred on white 600x600 with a 60px margin
#
# Uses `magick` only (never the legacy `convert`). Metadata stripped, sRGB,
# Lanczos resampling, JPEG/WebP quality 82-85. Nothing outside <out-dir> is
# written; existing files there are overwritten.

set -euo pipefail

if [ "$#" -ne 2 ]; then
	echo "Usage: bash make-assets.sh <src-dir> <out-dir>" >&2
	exit 64
fi
SRC=${1%/}
OUT=${2%/}

if ! command -v magick >/dev/null 2>&1; then
	echo "ImageMagick 7 'magick' not found on PATH. (Do not fall back to 'convert'.)" >&2
	exit 69
fi

COVER="$SRC/podcast-cover-3000.png"
BG="$SRC/social-thumbnail-background-1920x1080.png"
HLOGO="$SRC/horizontal-one-colour-white.png"
SLOGO="$SRC/primary-stacked-light.png"
for f in "$COVER" "$BG" "$HLOGO" "$SLOGO"; do
	if [ ! -r "$f" ]; then
		echo "Missing source: $f" >&2
		exit 66
	fi
done
mkdir -p "$OUT"

echo "Sources:"
magick identify -format '  %f  %wx%h  %[colorspace]  alpha=%A  %b\n' "$COVER" "$BG" "$HLOGO" "$SLOGO"

# Common settings: sRGB, no metadata/profiles, Lanczos.
JPEG_OPTS=(-sampling-factor 4:2:0 -interlace Plane -quality 85)
WEBP_OPTS=(-quality 82 -define webp:method=6)

# --- podcast cover: 360 and 856 squares, JPEG + WebP ---------------------------
for size in 360 856; do
	magick "$COVER" -colorspace sRGB -background white -alpha remove -alpha off \
		-filter Lanczos -resize "${size}x${size}" -strip \
		"${JPEG_OPTS[@]}" "$OUT/podcast-cover-${size}.jpg"
	magick "$COVER" -colorspace sRGB -background white -alpha remove -alpha off \
		-filter Lanczos -resize "${size}x${size}" -strip \
		"${WEBP_OPTS[@]}" "$OUT/podcast-cover-${size}.webp"
done

# --- default OG image: 1200x630 --------------------------------------------------
# Background: fill 1200x630 then crop the centre (cover). Logo: fit inside
# 720x441 (60% of the width, at most 70% of the height), centred.
magick \
	\( "$BG" -colorspace sRGB -background white -alpha remove -alpha off \
		-filter Lanczos -resize '1200x630^' -gravity center -extent 1200x630 \) \
	\( "$HLOGO" -colorspace sRGB -filter Lanczos -resize '720x441' \) \
	-gravity center -compose over -composite \
	-strip "${JPEG_OPTS[@]}" "$OUT/og-default-1200x630.jpg"

# --- square logo: 600x600 white, 60px margin ------------------------------------
magick -size 600x600 xc:white \
	\( "$SLOGO" -colorspace sRGB -filter Lanczos -resize '480x480' \) \
	-gravity center -compose over -composite \
	-strip -define png:compression-level=9 -define png:color-type=2 "$OUT/logo-square-600.png"

echo
echo "Written to $OUT:"
for f in podcast-cover-360.jpg podcast-cover-360.webp podcast-cover-856.jpg podcast-cover-856.webp og-default-1200x630.jpg logo-square-600.png; do
	magick identify -format "  %f  %wx%h  %m  q=%Q  " "$OUT/$f"
	printf '%8s bytes\n' "$(wc -c < "$OUT/$f" | tr -d ' ')"
done
