#!/usr/bin/env bash
#
# Build the local Tailwind stylesheet by scanning the server-rendered views.
# The layouts prefer public/assets/tailwind.css when present (offline-capable),
# and fall back to the Tailwind Play CDN otherwise. Run after changing views.
#
#   bin/build-assets.sh
#
set -euo pipefail
cd "$(dirname "$0")/.."

TMP="$(mktemp -d)"
printf '@tailwind base;\n@tailwind components;\n@tailwind utilities;\n' > "$TMP/in.css"

npx --yes tailwindcss@3.4.17 \
  -i "$TMP/in.css" \
  -o public/assets/tailwind.css \
  --content './resources/views/**/*.php' \
  --minify

echo "Built public/assets/tailwind.css ($(wc -c < public/assets/tailwind.css) bytes)"
