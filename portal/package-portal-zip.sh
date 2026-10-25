#!/usr/bin/env bash
set -e

# Vendra Showcase & Central License Server - Release Packaging Script
# For hosting on hosting.com (cPanel/Apache/LiteSpeed) at vendra.charafeddine.xyz

PORTAL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
OUTPUT_ZIP="${PORTAL_DIR}/vendra-showcase-and-license-server.zip"

echo "=== Packaging Vendra Showcase & Central License Server ==="
cd "$PORTAL_DIR"

rm -f "$OUTPUT_ZIP"

# Create clean distribution zip
zip -r -q "$OUTPUT_ZIP" \
    index.html \
    .htaccess \
    config/config.php \
    config/.htaccess \
    database/vendra_portal_schema.sql \
    install/index.php \
    license-api/index.php \
    license-api/.htaccess \
    uploads/

echo "=== Release Package Created Successfully ==="
echo "Zip File: ${OUTPUT_ZIP}"
echo "Size: $(du -h "$OUTPUT_ZIP" | cut -f1)"
echo "Ready to upload & extract directly into cPanel /public_html (or subdomain root for vendra.charafeddine.xyz)"
