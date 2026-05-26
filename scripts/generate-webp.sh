#!/usr/bin/env bash
# Generate .webp siblings for every JPG/PNG under images/ that doesn't have one.
# Run on the Linux box where cwebp is available. Commit the resulting .webp
# files alongside the originals; deploy.yml will rsync them up.
#
# Requires: cwebp (apt install webp)
#
# Usage: scripts/generate-webp.sh [--quality N] [--min-size BYTES]

set -euo pipefail

QUALITY=82          # cwebp default 75 → 82 gives a bit more fidelity at minor size cost
MIN_SIZE=8192       # don't bother converting anything under 8KB

while [[ $# -gt 0 ]]; do
    case "$1" in
        --quality) QUALITY="$2"; shift 2 ;;
        --min-size) MIN_SIZE="$2"; shift 2 ;;
        *) echo "Unknown arg: $1" >&2; exit 1 ;;
    esac
done

if ! command -v cwebp >/dev/null 2>&1; then
    echo "cwebp not found. Install with: sudo apt install webp" >&2
    exit 1
fi

cd "$(dirname "$0")/.."

converted=0
skipped=0
saved_bytes=0

while IFS= read -r -d '' src; do
    dst="${src%.*}.webp"

    if [[ -f "$dst" ]]; then
        skipped=$((skipped + 1))
        continue
    fi

    src_size=$(stat -c%s "$src")
    if (( src_size < MIN_SIZE )); then
        skipped=$((skipped + 1))
        continue
    fi

    cwebp -quiet -q "$QUALITY" "$src" -o "$dst"
    dst_size=$(stat -c%s "$dst")

    if (( dst_size >= src_size )); then
        # WebP came out larger than the source — keep the original, drop the webp
        rm -f "$dst"
        echo "skip (webp larger): $src"
        skipped=$((skipped + 1))
        continue
    fi

    saved=$((src_size - dst_size))
    saved_bytes=$((saved_bytes + saved))
    converted=$((converted + 1))
    printf '  %-50s %7d -> %7d (-%d%%)\n' "$src" "$src_size" "$dst_size" "$((100 * saved / src_size))"
done < <(find images -type f \( -iname '*.jpg' -o -iname '*.jpeg' -o -iname '*.png' \) -print0)

echo ""
echo "converted: $converted, skipped: $skipped, saved: $saved_bytes bytes"
