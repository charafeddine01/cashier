# OmniPOS Pro - Universal Commercial Cashier & Point of Sale System

OmniPOS Pro is a production-ready, commercial-grade Point of Sale (POS) application designed to run on standard Shared Web Hosting (cPanel, Apache, LiteSpeed with PHP 8.2+ and MySQL) or locally as a desktop/mobile app (PWA & Capacitor for Android APK and iOS IPA).

---

## Key Features

1. **Shared Hosting Native:**
   - Pure PHP 8.2+ backend with strict typing and native `ext-sodium` (Ed25519) + `ext-openssl`.
   - Single-Page Application (React 18, TypeScript, Tailwind CSS, Vite) compiled to static assets in `dist/`.
   - Mobile-ready with PWA offline caching and Capacitor 6 configuration (`capacitor.config.ts`).

2. **100% HTTP POST JSON Protocol:**
   - Unified API gateway at `/api/index.php`.
   - Rejects GET, PUT, DELETE, and PATCH methods with HTTP 405.
   - Envelopes requests with `{ action, terminal_id, client_timestamp, auth_token, payload }`.

3. **Tamper-Resistant 4-Layer Licensing:**
   - Asymmetric Ed25519 digital signature verification (`license.key`).
   - Monotonic clock anti-rollback protection with HMAC integrity signature.
   - Central heartbeat lease support.
   - Standalone Vendor License Generator Tool in `/vendor-license-manager/`.

4. **Multi-Industry Switchable Modes:**
   - **SUPERMARKET:** Barcode scanner bursts (<30ms), EAN-13 scale barcodes (prefixes 20-29), multi-unit barcodes (Piece/Box/Carton), keyboard shortcuts.
   - **RESTAURANT:** Visual Floor Plan & Table Map, Kitchen Display System (KDS), Course & Modifiers, Split Bills.
   - **RETAIL:** Variant matrix (Size/Color), Serial Number / IMEI tracking, barcode label printer.
   - **PHARMACY:** Batch/Lot numbers, FEFO (First-Expired, First-Out) stock deduction, near-expiry alerts.

5. **Hardware & Offline Resilient:**
   - Global HID Barcode Gun listener (<30ms burst detection).
   - ESC/POS thermal printer engine (58mm/80mm, cash drawer kick `\x1B\x70\x00\x19\xFA`, paper cut `\x1D\x56\x42\x00`).
   - Customer Pole Display / Second Screen sync via `BroadcastChannel('omnipos_customer_display')`.
   - IndexedDB AES-GCM encrypted offline outbox with client-generated UUID idempotency keys.

6. **1-Click Shared Hosting Web Installer:**
   - Web GUI at `/install/index.php` with pre-flight checks, interactive database setup, license activation, store branding, and admin account setup.
   - Automatic security lockout via `/api/config/installed.lock`.

---

## Directory Structure

```
/home/charafeddine/Documents/cashier
├── api/                       # PHP 8.2+ Backend API
│   ├── config.php             # Core configuration
│   ├── index.php              # Unified 100% POST entrypoint
│   ├── config/                # Environment credentials & lock
│   │   ├── env.php            # Database & secrets (written by installer)
│   │   └── installed.lock     # Installer lock file
│   ├── controllers/           # Module controllers (Auth, Pos, Shifts, etc.)
│   └── core/                  # Database, Auth, LicenseGuard, Router
├── database/                  # MySQL schema and seeds
│   ├── schema.sql             # 31 normalized InnoDB tables (DECIMAL 15,4)
│   └── seed.sql               # Default catalog, taxes, and trial license
├── dist/                      # Compiled frontend static bundle
├── install/                   # 1-Click Web Installer
│   └── index.php
├── public/                    # Web manifest, icons, service worker
├── src/                       # React 18 + TypeScript + Tailwind source code
│   ├── components/            # UI components (POS, Restaurant, KDS, Shifts)
│   ├── services/              # Hardware, OfflineSync, API, I18n, Audio
│   ├── types/                 # TypeScript interfaces
│   ├── App.tsx
│   └── main.tsx
├── vendor-license-manager/    # Standalone Vendor License Generator Tool
├── capacitor.config.ts        # Mobile wrapper configuration (Android/iOS)
├── package-zip.sh             # 1-click cPanel release packager
├── router.php                 # Local development server router
└── vite.config.ts             # Vite configuration
```

---

## Quick Start (Development)

1. **Install dependencies & build frontend:**
   ```bash
   npm install
   npm run build
   ```

2. **Start the local PHP development server:**
   ```bash
   php -S 0.0.0.0:8000 router.php
   ```

3. **Open in browser:**
   - Cashier Terminal: `http://localhost:8000/`
   - Customer Pole Display: `http://localhost:8000/#/customer-display`
   - Web Installer: `http://localhost:8000/install/`
   - Vendor License Manager: `http://localhost:8000/vendor-license-manager/` (Password: `VendorMasterPassword2026!`)

---

## Default Staff Credentials

- **Admin:** `admin` / `admin123` (Quick PIN: `1234`)
- **Manager:** `manager` / `manager123` (Quick PIN: `2222`)
- **Cashier:** `cashier1` / `cashier123` (Quick PIN: `3333`)
- **Waiter:** `waiter1` / `waiter123` (Quick PIN: `4444`)
- **Chef:** `chef1` / `kitchen123` (Quick PIN: `5555`)

---

## 1-Click cPanel Shared Hosting Deployment

To build the production release archive:
```bash
./package-zip.sh
```
This generates `omnipos-pro-v2.0-shared-hosting.zip` (under 300 KB). Upload and extract this file directly into your cPanel `public_html` directory and browse to `https://yourdomain.com/install/`.
