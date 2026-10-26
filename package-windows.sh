#!/usr/bin/env bash
set -e

# OmniPOS / Vendra - Windows Portable Distribution Packager
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DIST_WIN="$ROOT_DIR/dist-windows"
APP_DIR="$DIST_WIN/VendraPOS/app"

echo "=== Packaging Vendra POS Portable Windows ==="

# 1. Compile fresh frontend
echo "-> Building frontend..."
npm run build

# 2. Sync app files
echo "-> Syncing app files..."
rm -rf "$APP_DIR/dist" "$APP_DIR/api" "$APP_DIR/public"
cp -r "$ROOT_DIR/dist" "$APP_DIR/"
cp -r "$ROOT_DIR/api" "$APP_DIR/"
cp "$ROOT_DIR/router.php" "$APP_DIR/"
mkdir -p "$APP_DIR/public/uploads"
cp -r "$ROOT_DIR/public/"* "$APP_DIR/public/" 2>/dev/null || true
cp "$DIST_WIN/VendraPOS/vendra.ico" "$APP_DIR/public/favicon.ico"
cp "$DIST_WIN/VendraPOS/vendra.ico" "$APP_DIR/dist/favicon.ico"

# 3. Create zip distribution
echo "-> Creating zip archive..."
cd "$DIST_WIN"
zip -r -9 -q VendraPOS-SolarShowroom-Windows.zip VendraPOS -x "*/logs/*"

echo "=== Done! Package created at $DIST_WIN/VendraPOS-SolarShowroom-Windows.zip ($(du -h VendraPOS-SolarShowroom-Windows.zip | cut -f1)) ==="
