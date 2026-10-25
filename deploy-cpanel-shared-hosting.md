# OmniPOS Pro - cPanel & Shared Web Hosting Deployment Guide
**Native PHP 8.2+ & MySQL Architecture (No Node.js daemon required on server)**

---

## Architecture Overview
OmniPOS Pro is specifically architected for standard commercial shared hosting accounts (cPanel, DirectAdmin, Plesk, LiteSpeed, Apache) with:
1. **Frontend:** Pre-compiled static assets in `/dist/` served directly by the web server.
2. **Backend:** Pure PHP 8.2+ API in `/api/index.php` using standard PDO prepared statements and native `ext-sodium` (Ed25519) + `ext-openssl`.
3. **100% POST Protocol:** All client requests hit `POST /api/index.php` with a unified JSON envelope.
4. **Database:** Normalized MySQL InnoDB schema with `DECIMAL(15,4)` precision.

---

## 5-Minute Installation Steps on cPanel

### Step 1: Create the MySQL Database
1. Log into your cPanel account.
2. Go to **MySQL Databases**:
   - Create a new database: e.g. `cpaneluser_omnipos`.
   - Create a new user: e.g. `cpaneluser_posadmin` with a strong password.
   - Assign the user to the database with **ALL PRIVILEGES**.
3. Open **phpMyAdmin**:
   - Select `cpaneluser_omnipos`.
   - Go to **Import** and upload `/database/schema.sql`.
   - Go to **Import** and upload `/database/seed.sql`.

### Step 2: Configure Database Credentials
Edit `/api/config.php` (via cPanel File Manager or FTP):
```php
'db' => [
    'driver'    => 'mysql',
    'host'      => '127.0.0.1',
    'port'      => 3306,
    'database'  => 'cpaneluser_omnipos',
    'username'  => 'cpaneluser_posadmin',
    'password'  => 'your_strong_password_here',
    'charset'   => 'utf8mb4',
],
```

### Step 3: Upload Files to `public_html`
Upload the repository files into your domain's document root (e.g. `/public_html`):
```text
public_html/
├── .htaccess               # Handles routing and protects sensitive files
├── dist/                   # Static compiled React assets (JS, CSS, HTML)
├── api/                    # 100% POST PHP 8.2+ API
│   ├── .htaccess           # Protects includes
│   ├── config.php          # Database credentials & Vendor Public Key
│   ├── index.php           # Front controller
│   ├── core/               # Database, LicenseGuard, Auth, Response, Router
│   └── controllers/        # Business logic controllers
├── vendor-license-manager/ # Standalone Vendor Licensing Tool (Keep secure)
├── public/
│   ├── uploads/            # Company logo & receipt graphic storage
│   ├── manifest.json       # PWA manifest
│   └── sw.js               # Service Worker for offline app caching
└── database/               # (Protected from web access via .htaccess)
```

### Step 4: Verify PHP Extensions in cPanel
Under **Select PHP Version** in cPanel:
- Ensure PHP version is **8.2** or higher (8.2, 8.3, 8.4, 8.5).
- Ensure the following native extensions are checked:
  - `pdo` & `pdo_mysql`
  - `sodium` (for Ed25519 asymmetric cryptographic license verification)
  - `openssl` (for secure token signing)
  - `curl` (for vendor heartbeat communication)
  - `json` & `fileinfo`

### Step 5: Test Your Live System
Open your domain in any modern browser:
- **Default Admin Account:**
  - Username: `admin`
  - Password: `admin123`
  - PIN: `1234`
- **Default Manager:** PIN `2222` (password `manager123`)
- **Default Cashier:** PIN `3333` (password `cashier123`)
- **Default Waiter:** PIN `4444` (password `waiter123`)
- **Default Chef:** PIN `5555` (password `kitchen123`)

---

## Monotonic Clock Anti-Rollback Protection
If any user tries to extend an expired license by rolling back their hosting server's date/time, the system automatically detects that `current_time < max_observed_utc - 300` and locks the register with a `CLOCK_TAMPER_DETECTED` security alert until re-activated online.

---

## Standalone Vendor License Generator Tool
Access your standalone tool at:
`https://yourdomain.com/vendor-license-manager/`
- Default Master Password: `admin123` (Change hash in `vendor-license-manager/index.php`).
- Generate signed client licenses with custom expiration dates and download `license.key` files.
- Acts as the central license heartbeat authority.
