#!/usr/bin/env bash
set -e

# OmniPOS Pro - Release Packaging Script for Shared Hosting
# Packages production build into a single uploadable .zip for cPanel public_html

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUTPUT_ZIP="${PROJECT_DIR}/omnipos-pro-v2.0-shared-hosting.zip"

echo "=== 1. Building Production Frontend Bundle ==="
export PATH="$HOME/.local/bin:$PATH"
npm run build

echo "=== 2. Preparing Distribution Package ==="
rm -f "$OUTPUT_ZIP"

# Temporary exclusion list
zip -r -q "$OUTPUT_ZIP" . \
  -x "node_modules/*" \
  -x ".git/*" \
  -x "tests/*" \
  -x "api/config/installed.lock" \
  -x "api/config/env.php" \
  -x "database/omnipos.sqlite*" \
  -x "portal/*" \
  -x "*.zip"

echo "=== 3. Release Package Created Successfully ==="
echo "Zip File: ${OUTPUT_ZIP}"
echo "Size: $(du -h "$OUTPUT_ZIP" | cut -f1)"
echo "Ready to upload & extract directly into cPanel /public_html/"
