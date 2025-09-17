#!/bin/bash

# Exit on error
set -e

# Check dependencies
command -v fontforge >/dev/null 2>&1 || { echo >&2 "FontForge is required but not installed. Aborting."; exit 1; }
command -v woff2_compress >/dev/null 2>&1 || { echo >&2 "woff2 tools are required but not installed. Aborting."; exit 1; }

# Create output directory
OUTPUT_DIR="webfonts"
mkdir -p "$OUTPUT_DIR"

# Loop through all TTF files
for font in *.ttf; do
  if [ -f "$font" ]; then
    base=$(basename "$font" .ttf)

    echo "Converting $font -> $OUTPUT_DIR/$base.woff ..."
    fontforge -lang=ff -c "Open(\"$font\"); Generate(\"$OUTPUT_DIR/$base.woff\")"

    echo "Converting $font -> $OUTPUT_DIR/$base.woff2 ..."
    cp "$font" "$OUTPUT_DIR/$base.ttf"
    (cd "$OUTPUT_DIR" && woff2_compress "$base.ttf" && rm "$base.ttf")

    echo "✅ Done: $base"
  fi
done

echo "All fonts converted! Check the '$OUTPUT_DIR' folder."
