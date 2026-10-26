# Deploying Vendra Portal to hosting.com (vendra.charafeddine.xyz)

This guide walks you through deploying the **Vendra Commercial Showcase Website** and **Central License Authority API** to `vendra.charafeddine.xyz` on **hosting.com** (cPanel/Apache/LiteSpeed shared hosting with PHP 8.2+ and MySQL).

---

## 📦 Deployment Archive

The ready-to-upload archive is located on your machine at:
`/home/charafeddine/Documents/cashier/portal/vendra-showcase-and-license-server.zip` (36 KB)

It contains:
- `index.html` (Commercial Showcase, Live POS Simulator, White-Label Customizer, Lead Capture Modal)
- `.htaccess` (Apache/LiteSpeed routing and security headers)
- `config/` (Configuration engine & protected `.htaccess`)
- `database/vendra_portal_schema.sql` (Database schema)
- `install/index.php` (1-Click Web Installer)
- `license-api/index.php` (100% POST Central License Authority)
- `uploads/` (Screenshots & media storage)

---

## Step 1: Create the Subdomain in cPanel

1. Log into your **hosting.com cPanel** account.
2. Under the **Domains** section, click **Domains** (or **Subdomains**).
3. Click **Create A New Domain**:
   - **Domain:** `vendra.charafeddine.xyz`
   - **Document Root:** Enter a directory name (e.g. `public_html/vendra` or `vendra.charafeddine.xyz`).
4. Click **Submit**.

---

## Step 2: Create the MySQL Database

1. In cPanel, navigate to **MySQL Database Wizard** (or **MySQL Databases**).
2. **Step 1 - Create Database:** e.g., `vendra_portal` (full name will look like `username_vendra_portal`). Click **Next Step**.
3. **Step 2 - Create Database User:**
   - **Username:** e.g., `vendra_user`
   - **Password:** Generate a strong password and save it in a safe place.
   - Click **Create User**.
4. **Step 3 - Add User to Database:**
   - Check the box **ALL PRIVILEGES**.
   - Click **Make Changes**.

---

## Step 3: Verify PHP 8.2+ and Sodium Extension

1. In cPanel, find **Select PHP Version** (or **MultiPHP Manager**).
2. Ensure your subdomain `vendra.charafeddine.xyz` is running **PHP 8.2** or **PHP 8.3**.
3. Under **Extensions**, ensure the following standard extensions are active:
   - `pdo`
   - `pdo_mysql`
   - `sodium` (required for Ed25519 digital signatures)
   - `openssl`
   - `json`

*(Almost all modern cPanel hosts enable these by default).*

---

## Step 4: Upload and Extract the Zip File

1. In cPanel, open **File Manager**.
2. Navigate to the Document Root directory of your subdomain (e.g. `/public_html/vendra`).
3. Click **Upload** in the top toolbar.
4. Select `/home/charafeddine/Documents/cashier/portal/vendra-showcase-and-license-server.zip`.
5. Once the upload shows 100% (green), return to the File Manager.
6. Right-click `vendra-showcase-and-license-server.zip` and select **Extract** -> **Extract Files**.
7. Confirm that `index.html`, `.htaccess`, `config/`, `database/`, `install/`, and `license-api/` are located in that folder.
8. *(Optional)* Delete `vendra-showcase-and-license-server.zip` to keep the directory clean.

---

## Step 5: Run the 1-Click Web Installer

1. Open your browser and go to:
   ```
   https://vendra.charafeddine.xyz/install/
   ```
2. The pre-flight check will verify PHP, PDO, Sodium, and folder write permissions.
3. Fill in your MySQL details from Step 2:
   - **Database Host:** `localhost` (standard on hosting.com)
   - **Database Name:** your full cPanel database name (e.g. `username_vendra_portal`)
   - **Database Username:** your full cPanel user (e.g. `username_vendra_user`)
   - **Database Password:** the password you created
4. Click **Test Connection** — it will verify the credentials.
5. Set your **Vendor Admin Email** and **Master Password**.
6. Keep the checkbox **"Use standard Vendra Vendor Master Keypair"** checked (this ensures licenses generated match the pre-configured Vendra POS client).
7. Click **Install & Lock Vendra Portal**.

---

## Step 6: Verify Everything Is Live!

Once installed:
- **Commercial Showcase Website:** [https://vendra.charafeddine.xyz/](https://vendra.charafeddine.xyz/)
- **Central Licensing API:** [https://vendra.charafeddine.xyz/license-api/index.php](https://vendra.charafeddine.xyz/license-api/index.php)
- **Vendor Master Vault:** Click the **Vendor Vault** or Admin login icon in the showcase header and enter your Master Admin credentials to:
  - Generate signed commercial `license.key` files for new shops.
  - View incoming buyer demo leads.
  - Monitor live 6-hour shop heartbeats.
  - Revoke or extend client licenses remotely.
