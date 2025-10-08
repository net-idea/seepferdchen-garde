#!/usr/bin/env bash
# Generate favicon & PWA icon set from base SVG.
# (See previous header comments for details.)
set -euo pipefail

# Resolve script directory to make it runnable from anywhere
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$SCRIPT_DIR"
PUBLIC_DIR="$PROJECT_ROOT/public"

FORCE=false
if [[ ${1:-} == "--force" ]]; then
  FORCE=true
fi

SRC="$PUBLIC_DIR/favicon.svg"
OUT_DIR="$PUBLIC_DIR"
if [[ ! -f "$SRC" ]]; then
  echo "Source $SRC not found" >&2
  exit 1
fi

if ! command -v magick >/dev/null 2>&1; then
  echo "ImageMagick (magick) not found. Install with: brew install imagemagick" >&2
  exit 2
fi

SIZES=(16 32 48 64 96 128 192 256 384 512)

make_png() {
  local size=$1
  local target="${OUT_DIR}/favicon-${size}x${size}.png"
  if [[ "$size" == 192 ]]; then
    target="${OUT_DIR}/web-app-manifest-192x192.png"
  elif [[ "$size" == 512 ]]; then
    target="${OUT_DIR}/web-app-manifest-512x512.png"
  fi
  if $FORCE || [[ ! -f "$target" || "$SRC" -nt "$target" ]]; then
    echo "Generating $target" >&2
    magick "$SRC" -background none -resize ${size}x${size} "$target"
  else
    echo "Up-to-date: $target" >&2
  fi
  if [[ "$size" == 192 ]]; then
    local aliasPng="${OUT_DIR}/favicon-192x192.png"
    if $FORCE || [[ ! -f "$aliasPng" || "$OUT_DIR/web-app-manifest-192x192.png" -nt "$aliasPng" ]]; then
      cp "$OUT_DIR/web-app-manifest-192x192.png" "$aliasPng"
    fi
  elif [[ "$size" == 512 ]]; then
    local aliasPng="${OUT_DIR}/favicon-512x512.png"
    if $FORCE || [[ ! -f "$aliasPng" || "$OUT_DIR/web-app-manifest-512x512.png" -nt "$aliasPng" ]]; then
      cp "$OUT_DIR/web-app-manifest-512x512.png" "$aliasPng"
    fi
  fi
}

for s in "${SIZES[@]}"; do
  make_png "$s"
done

APPLE_ICON="${OUT_DIR}/apple-touch-icon.png"
if $FORCE || [[ ! -f "$APPLE_ICON" || "$SRC" -nt "$APPLE_ICON" ]]; then
  echo "Generating $APPLE_ICON" >&2
  magick "$SRC" -background none -resize 180x180 "$APPLE_ICON"
else
  echo "Up-to-date: $APPLE_ICON" >&2
fi

ICO="${OUT_DIR}/favicon.ico"
if $FORCE || [[ ! -f "$ICO" || "$SRC" -nt "$ICO" ]]; then
  echo "Generating $ICO" >&2
  magick "$SRC" -background none -resize 16x16 PNG32:tmp-16.png
  magick "$SRC" -background none -resize 32x32 PNG32:tmp-32.png
  magick "$SRC" -background none -resize 48x48 PNG32:tmp-48.png
  magick tmp-16.png tmp-32.png tmp-48.png "$ICO"
  rm -f tmp-16.png tmp-32.png tmp-48.png
else
  echo "Up-to-date: $ICO" >&2
fi

echo "Done."
