#!/usr/bin/env bash
set -e

echo "=================================================="
echo "  OmniPOS Pro - Production Build & Packager"
echo "=================================================="

# 1. Compile React TypeScript SPA
echo "[1/3] Building React 18 TypeScript frontend with Vite..."
npm run build

# 2. Set Directory Permissions
echo "[2/3] Setting directory permissions..."
chmod -R 755 dist/
chmod -R 755 api/
chmod -R 755 vendor-license-manager/
mkdir -p public/uploads
chmod -R 777 public/uploads database/

# 3. Complete
echo "[3/3] Build complete!"
echo "Production files are ready in dist/ and api/."
echo "Deploy directly to public_html on cPanel or run:"
echo "  php -S 0.0.0.0:8000 router.php"
