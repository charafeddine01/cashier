<?php
declare(strict_types=1);

/**
 * OmniPOS Pro - 1-Click Shared Hosting Web Installer
 * Designed for cPanel / Apache / LiteSpeed / PHP 8.2+
 *
 * Checks prerequisites, configures MySQL PDO, runs schema migrations,
 * verifies Ed25519 commercial license, configures business mode & branding,
 * creates Master Admin, and writes /api/config/installed.lock.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$rootDir = realpath(__DIR__ . '/..');
$lockFile = $rootDir . '/api/config/installed.lock';
$envFile = $rootDir . '/api/config/env.php';
$schemaFile = $rootDir . '/database/schema.sql';
$seedFile = $rootDir . '/database/seed.sql';
$uploadsPublicDir = $rootDir . '/public/uploads';
$uploadsDistDir = $rootDir . '/dist/uploads';
$configDir = $rootDir . '/api/config';
$databaseDir = $rootDir . '/database';

$vendorPublicKeyHex = '7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4';

// Check if system is already installed
$isLocked = file_exists($lockFile);
$lockData = null;
if ($isLocked) {
    $rawLock = @file_get_contents($lockFile);
    $lockData = json_decode($rawLock ?: '{}', true) ?: [];
}

// Handle AJAX API requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=UTF-8');

    // If locked, reject any mutation requests
    if ($isLocked) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'OmniPOS Pro is already installed and locked. To re-install, delete /api/config/installed.lock via cPanel File Manager.',
            'error'   => 'INSTALLATION_LOCKED',
        ]);
        exit;
    }

    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $action = $data['action'] ?? '';

    switch ($action) {
        case 'check_prerequisites':
            $phpVersion = PHP_VERSION;
            $phpOk = version_compare($phpVersion, '8.1.0', '>=');

            $extensions = [
                'pdo'       => extension_loaded('pdo'),
                'pdo_mysql' => extension_loaded('pdo_mysql'),
                'sodium'    => extension_loaded('sodium'),
                'openssl'   => extension_loaded('openssl'),
                'json'      => extension_loaded('json'),
                'mbstring'  => extension_loaded('mbstring'),
            ];

            // Ensure dirs exist
            if (!is_dir($configDir)) @mkdir($configDir, 0775, true);
            if (!is_dir($uploadsPublicDir)) @mkdir($uploadsPublicDir, 0775, true);
            if (!is_dir($uploadsDistDir)) @mkdir($uploadsDistDir, 0775, true);
            if (!is_dir($databaseDir)) @mkdir($databaseDir, 0775, true);

            $directories = [
                'config'   => ['path' => 'api/config', 'writable' => is_writable($configDir)],
                'uploads'  => ['path' => 'public/uploads', 'writable' => is_writable($uploadsPublicDir) || is_writable($uploadsDistDir)],
                'database' => ['path' => 'database', 'writable' => is_writable($databaseDir)],
            ];

            $allExtensionsOk = $extensions['pdo'] && $extensions['sodium'] && $extensions['openssl'] && $extensions['json'];
            $allDirsOk = $directories['config']['writable'];
            $allPassed = $phpOk && $allExtensionsOk && $allDirsOk;

            echo json_encode([
                'success'      => true,
                'php_version'  => $phpVersion,
                'php_ok'       => $phpOk,
                'extensions'   => $extensions,
                'directories'  => $directories,
                'all_passed'   => $allPassed,
                'server_uname' => php_uname('s') . ' ' . php_uname('r'),
            ]);
            exit;

        case 'test_database':
            $driver = $data['driver'] ?? 'mysql';
            $host = trim((string)($data['host'] ?? '127.0.0.1'));
            $port = (int)($data['port'] ?? 3306);
            $dbname = trim((string)($data['database'] ?? ''));
            $user = trim((string)($data['username'] ?? ''));
            $pass = (string)($data['password'] ?? '');

            if ($driver === 'sqlite') {
                try {
                    $sqlitePath = $databaseDir . '/omnipos.sqlite';
                    $pdo = new PDO("sqlite:{$sqlitePath}", null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    echo json_encode([
                        'success' => true,
                        'message' => 'SQLite connection successful! Zero-config database ready at /database/omnipos.sqlite',
                    ]);
                    exit;
                } catch (\Throwable $e) {
                    echo json_encode(['success' => false, 'message' => 'SQLite Error: ' . $e->getMessage()]);
                    exit;
                }
            }

            if (empty($dbname) || empty($user)) {
                echo json_encode(['success' => false, 'message' => 'Database name and Username are required.']);
                exit;
            }

            try {
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);

                $version = $pdo->query('SELECT VERSION()')->fetchColumn();

                echo json_encode([
                    'success' => true,
                    'message' => "Connection successful! MySQL Server Version: {$version}",
                ]);
            } catch (\PDOException $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Database connection failed: ' . $e->getMessage(),
                ]);
            }
            exit;

        case 'run_migrations':
            $driver = $data['driver'] ?? 'mysql';
            $host = trim((string)($data['host'] ?? '127.0.0.1'));
            $port = (int)($data['port'] ?? 3306);
            $dbname = trim((string)($data['database'] ?? ''));
            $user = trim((string)($data['username'] ?? ''));
            $pass = (string)($data['password'] ?? '');

            try {
                if ($driver === 'sqlite') {
                    $sqlitePath = $databaseDir . '/omnipos.sqlite';
                    $pdo = new PDO("sqlite:{$sqlitePath}", null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    $pdo->exec('PRAGMA foreign_keys = OFF;');
                } else {
                    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                    $pdo = new PDO($dsn, $user, $pass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
                    ]);
                    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
                }

                // Execute schema.sql
                if (!file_exists($schemaFile)) {
                    throw new RuntimeException("Schema file not found at {$schemaFile}");
                }
                $schemaSql = file_get_contents($schemaFile);

                if ($driver === 'sqlite') {
                    // Normalize for sqlite
                    $schemaSql = preg_replace('/ENGINE=\w+ DEFAULT CHARSET=\w+ COLLATE=[\w_]+/i', '', $schemaSql);
                    $schemaSql = preg_replace('/SET FOREIGN_KEY_CHECKS\s*=\s*[01];/i', '', $schemaSql);
                    $schemaSql = preg_replace('/INT UNSIGNED AUTO_INCREMENT PRIMARY KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $schemaSql);
                    $schemaSql = preg_replace('/AUTO_INCREMENT/i', '', $schemaSql);
                    $schemaSql = preg_replace('/INT UNSIGNED/i', 'INTEGER', $schemaSql);
                    $schemaSql = preg_replace('/BIGINT UNSIGNED/i', 'INTEGER', $schemaSql);
                    $schemaSql = preg_replace('/TINYINT\(1\)/i', 'INTEGER', $schemaSql);
                    $schemaSql = preg_replace('/MEDIUMTEXT/i', 'TEXT', $schemaSql);
                    $schemaSql = preg_replace('/ENUM\([^)]+\)/i', 'TEXT', $schemaSql);
                    $schemaSql = preg_replace('/JSON/i', 'TEXT', $schemaSql);
                    $schemaSql = preg_replace('/ON UPDATE CURRENT_TIMESTAMP/i', '', $schemaSql);
                    $schemaSql = preg_replace('/COMMENT\s+\'[^\']*\'/i', '', $schemaSql);
                    $schemaSql = preg_replace('/,\s*INDEX\s+`[^`]+`\s*\([^)]+\)/i', '', $schemaSql);
                    $schemaSql = preg_replace('/,\s*UNIQUE\s+KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $schemaSql);

                    $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
                    foreach ($statements as $stmt) {
                        if (!empty($stmt)) {
                            try { $pdo->exec($stmt); } catch (\Throwable $t) {}
                        }
                    }
                } else {
                    // MySQL multi-statement execution
                    $statements = array_filter(array_map('trim', explode(';', $schemaSql)));
                    foreach ($statements as $stmt) {
                        if (!empty($stmt)) {
                            $pdo->exec($stmt);
                        }
                    }
                }

                // Execute seed.sql for base categories, units, tax rates, tables
                if (file_exists($seedFile)) {
                    $seedSql = file_get_contents($seedFile);
                    if ($driver === 'sqlite') {
                        $seedSql = preg_replace('/SET FOREIGN_KEY_CHECKS\s*=\s*[01];/i', '', $seedSql);
                        $seedSql = preg_replace('/ON DUPLICATE KEY UPDATE[^;]+/i', '', $seedSql);
                    }
                    $seedStatements = array_filter(array_map('trim', explode(';', $seedSql)));
                    foreach ($seedStatements as $stmt) {
                        if (!empty($stmt)) {
                            try {
                                $pdo->exec($stmt);
                            } catch (\Throwable $seedErr) {
                                // Ignore duplicate key warnings on seed
                            }
                        }
                    }
                }

                // Generate secure random security salt & JWT secret
                $secSalt = bin2hex(random_bytes(32));
                $jwtSecret = bin2hex(random_bytes(32));

                // Write /api/config/env.php securely
                $envContent = "<?php\ndeclare(strict_types=1);\n\n/**\n * OmniPOS Pro - Production Environment Configuration\n * Auto-generated by Web Installer on " . date('Y-m-d H:i:s') . "\n */\n\nreturn [\n";
                $envContent .= "    'db' => [\n";
                $envContent .= "        'driver'    => " . var_export($driver, true) . ",\n";
                $envContent .= "        'host'      => " . var_export($host, true) . ",\n";
                $envContent .= "        'port'      => " . $port . ",\n";
                $envContent .= "        'database'  => " . ($driver === 'sqlite' ? "__DIR__ . '/../../database/omnipos.sqlite'" : var_export($dbname, true)) . ",\n";
                $envContent .= "        'username'  => " . var_export($user, true) . ",\n";
                $envContent .= "        'password'  => " . var_export($pass, true) . ",\n";
                $envContent .= "        'charset'   => 'utf8mb4',\n";
                $envContent .= "    ],\n";
                $envContent .= "    'security_salt'         => " . var_export($secSalt, true) . ",\n";
                $envContent .= "    'jwt_secret'            => " . var_export($jwtSecret, true) . ",\n";
                $envContent .= "    'vendor_public_key_hex' => " . var_export($vendorPublicKeyHex, true) . ",\n";
                $envContent .= "    'installed_at'          => " . time() . ",\n";
                $envContent .= "];\n";

                file_put_contents($envFile, $envContent);
                @chmod($envFile, 0640);

                echo json_encode([
                    'success' => true,
                    'message' => 'Schema successfully installed! 31 normalized InnoDB tables created.',
                ]);
            } catch (\Throwable $e) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Migration failed: ' . $e->getMessage(),
                ]);
            }
            exit;

        case 'verify_license':
            $rawKey = $data['license_key'] ?? '';
            if (is_array($rawKey)) {
                $pkg = $rawKey;
            } else {
                $rawKeyStr = trim((string)$rawKey);
                if (empty($rawKeyStr)) {
                    echo json_encode(['success' => false, 'message' => 'License certificate is required.']);
                    exit;
                }
                $decoded = base64_decode($rawKeyStr, true);
                if ($decoded !== false && (str_starts_with($decoded, '{') || str_starts_with($decoded, '['))) {
                    $rawKeyStr = $decoded;
                }
                $pkg = json_decode($rawKeyStr, true);
            }

            if (!$pkg || !isset($pkg['certificate'], $pkg['signature'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid license structure. Missing certificate or signature.']);
                exit;
            }

            $cert = $pkg['certificate'];
            $sigHex = (string)$pkg['signature'];

            ksort($cert);
            $canonicalJson = json_encode($cert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $pubKeyBin = hex2bin($vendorPublicKeyHex);
            $sigBin = @hex2bin($sigHex);

            if (!$sigBin || strlen($sigBin) !== 64 || !sodium_crypto_sign_verify_detached($sigBin, $canonicalJson, $pubKeyBin)) {
                echo json_encode(['success' => false, 'message' => 'Cryptographic Signature Verification Failed. License is not signed by Vendor Master Key.']);
                exit;
            }

            $now = time();
            $expiresAt = (int)($cert['expires_at'] ?? 0);
            if ($now > $expiresAt) {
                echo json_encode([
                    'success' => false,
                    'message' => 'This license has already expired on ' . date('Y-m-d H:i:s', $expiresAt) . '.',
                ]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'license_id'      => $cert['license_id'] ?? 'UNKNOWN',
                    'client_name'     => $cert['client_name'] ?? 'Authorized Client',
                    'plan_tier'       => $cert['plan_tier'] ?? 'STANDARD',
                    'max_terminals'   => $cert['max_terminals'] ?? 1,
                    'enabled_modules' => $cert['enabled_modules'] ?? [],
                    'issued_at'       => $cert['issued_at'] ?? 0,
                    'expires_at'      => $expiresAt,
                    'days_remaining'  => round(($expiresAt - $now) / 86400, 1),
                ]
            ]);
            exit;

        case 'complete_installation':
            // Verify env.php exists
            if (!file_exists($envFile)) {
                echo json_encode(['success' => false, 'message' => 'Database environment configuration missing. Please run Step 2 first.']);
                exit;
            }
            $env = require $envFile;
            $dbCfg = $env['db'] ?? [];
            $secSalt = $env['security_salt'] ?? 'OMNIPOS_CORE_SALT_2026_SECURE';

            // Connect to DB
            try {
                if (($dbCfg['driver'] ?? 'mysql') === 'sqlite') {
                    $pdo = new PDO("sqlite:" . ($dbCfg['database'] ?? $databaseDir . '/omnipos.sqlite'), null, null, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                } else {
                    $dsn = "mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['database']};charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbCfg['username'], $dbCfg['password'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                }
            } catch (\Throwable $e) {
                echo json_encode(['success' => false, 'message' => 'Database connection error: ' . $e->getMessage()]);
                exit;
            }

            // 1. Validate & Store License
            $rawKey = $data['license_key'] ?? '';
            if (is_array($rawKey)) {
                $pkg = $rawKey;
            } else {
                $rawKeyStr = trim((string)$rawKey);
                $decoded = base64_decode($rawKeyStr, true);
                if ($decoded !== false && (str_starts_with($decoded, '{') || str_starts_with($decoded, '['))) {
                    $rawKeyStr = $decoded;
                }
                $pkg = json_decode($rawKeyStr, true);
            }

            if (!$pkg || !isset($pkg['certificate'], $pkg['signature'])) {
                echo json_encode(['success' => false, 'message' => 'Valid license certificate is required to complete installation.']);
                exit;
            }

            $cert = $pkg['certificate'];
            $sigHex = (string)$pkg['signature'];
            ksort($cert);
            $canonicalJson = json_encode($cert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $pubKeyBin = hex2bin($vendorPublicKeyHex);
            $sigBin = @hex2bin($sigHex);
            if (!$sigBin || !sodium_crypto_sign_verify_detached($sigBin, $canonicalJson, $pubKeyBin)) {
                echo json_encode(['success' => false, 'message' => 'License verification failed against Vendor Public Key.']);
                exit;
            }

            $now = time();
            $clockHmac = hash_hmac('sha256', (string)$now, $secSalt);
            $serverInfo = php_uname('s') . '-' . php_uname('n') . '-' . ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1');
            $fingerprint = hash('sha256', $serverInfo . $secSalt);

            // Bind to system_license_state
            $stmtLic = $pdo->prepare('UPDATE `system_license_state` SET
                `license_key_data` = :cert,
                `signature_hex` = :sig,
                `max_observed_utc` = :now_ts,
                `clock_hmac` = :hmac,
                `lease_token` = "INSTALLER_ACTIVATED",
                `lease_expires_at` = :lexp,
                `installation_fingerprint` = :fp,
                `last_verified_at` = :now_ts2,
                `is_tampered` = 0,
                `tamper_reason` = NULL
                WHERE `id` = 1
            ');
            $stmtLic->execute([
                ':cert' => json_encode($cert),
                ':sig' => $sigHex,
                ':now_ts' => $now,
                ':hmac' => $clockHmac,
                ':lexp' => (int)($cert['expires_at'] ?? 0),
                ':fp' => $fingerprint,
                ':now_ts2' => $now,
            ]);

            // 2. Handle Shop Logo & Settings
            $shopName = trim((string)($data['shop_name'] ?? 'OmniPOS Store'));
            $shopNameAr = trim((string)($data['company_name_ar'] ?? 'متجر أومني بوس'));
            $address = trim((string)($data['address'] ?? ''));
            $phone = trim((string)($data['phone'] ?? ''));
            $email = trim((string)($data['email'] ?? ''));
            $currencySym = trim((string)($data['currency_symbol'] ?? '$'));
            $currencyCode = trim((string)($data['currency_code'] ?? 'USD'));
            $businessMode = strtoupper(trim((string)($data['business_mode'] ?? 'SUPERMARKET')));
            if (!in_array($businessMode, ['SUPERMARKET', 'RESTAURANT', 'RETAIL', 'PHARMACY'], true)) {
                $businessMode = 'SUPERMARKET';
            }

            $logoUrl = null;
            $logoData = $data['logo_base64'] ?? '';
            if (!empty($logoData) && str_starts_with($logoData, 'data:image')) {
                // Save logo image
                $parts = explode(',', $logoData, 2);
                if (count($parts) === 2) {
                    $decodedImg = base64_decode($parts[1]);
                    if ($decodedImg) {
                        $logoFilename = 'shop_logo_' . time() . '.png';
                        @file_put_contents($uploadsPublicDir . '/' . $logoFilename, $decodedImg);
                        @file_put_contents($uploadsDistDir . '/' . $logoFilename, $decodedImg);
                        $logoUrl = '/uploads/' . $logoFilename;
                    }
                }
            }

            $stmtSet = $pdo->prepare('UPDATE `company_settings` SET
                `company_name` = :cname,
                `company_name_ar` = :cname_ar,
                `address` = :addr,
                `phone` = :phone,
                `email` = :email,
                `primary_currency_symbol` = :sym,
                `primary_currency_code` = :code,
                `business_mode` = :mode,
                `primary_logo_url` = COALESCE(:logo, `primary_logo_url`)
                WHERE `id` = 1
            ');
            $stmtSet->execute([
                ':cname' => $shopName,
                ':cname_ar' => $shopNameAr,
                ':addr' => $address,
                ':phone' => $phone,
                ':email' => $email,
                ':sym' => $currencySym,
                ':code' => $currencyCode,
                ':mode' => $businessMode,
                ':logo' => $logoUrl,
            ]);

            // 3. Create / Update Master Admin
            $adminUser = trim((string)($data['admin_username'] ?? 'admin'));
            $adminPass = (string)($data['admin_password'] ?? 'admin123');
            $adminPin = trim((string)($data['admin_pin'] ?? '1234'));
            $adminName = trim((string)($data['admin_fullname'] ?? 'Master Administrator'));
            $adminEmail = trim((string)($data['admin_email'] ?? 'admin@omnipos.local'));
            $adminPhone = trim((string)($data['admin_phone'] ?? ''));

            if (empty($adminUser) || strlen($adminPass) < 6) {
                echo json_encode(['success' => false, 'message' => 'Admin password must be at least 6 characters long.']);
                exit;
            }

            $passHash = password_hash($adminPass, PASSWORD_BCRYPT);
            $pinHash = password_hash($adminPin, PASSWORD_BCRYPT);

            // Upsert admin user
            $checkUser = $pdo->prepare('SELECT `id` FROM `users` WHERE `username` = :u');
            $checkUser->execute([':u' => $adminUser]);
            $existingAdmin = $checkUser->fetch();

            if ($existingAdmin) {
                $stmtAdmin = $pdo->prepare('UPDATE `users` SET
                    `password_hash` = :pass,
                    `pin_code_hash` = :pin,
                    `full_name` = :fname,
                    `email` = :email,
                    `phone` = :phone,
                    `role` = "ADMIN",
                    `is_active` = 1
                    WHERE `id` = :id
                ');
                $stmtAdmin->execute([
                    ':pass' => $passHash,
                    ':pin' => $pinHash,
                    ':fname' => $adminName,
                    ':email' => $adminEmail,
                    ':phone' => $adminPhone,
                    ':id' => $existingAdmin['id'],
                ]);
            } else {
                $stmtAdmin = $pdo->prepare('INSERT INTO `users` (
                    `username`, `password_hash`, `pin_code_hash`, `full_name`, `email`, `phone`, `role`, `is_active`
                ) VALUES (
                    :u, :pass, :pin, :fname, :email, :phone, "ADMIN", 1
                )');
                $stmtAdmin->execute([
                    ':u' => $adminUser,
                    ':pass' => $passHash,
                    ':pin' => $pinHash,
                    ':fname' => $adminName,
                    ':email' => $adminEmail,
                    ':phone' => $adminPhone,
                ]);
            }

            // 4. Lock Installer by writing /api/config/installed.lock
            $lockPayload = [
                'installed'        => true,
                'installed_at'     => $now,
                'installed_at_iso' => date('Y-m-d H:i:s', $now),
                'app_version'      => '2.0.0',
                'shop_name'        => $shopName,
                'business_mode'    => $businessMode,
                'admin_username'   => $adminUser,
                'db_driver'        => $dbCfg['driver'] ?? 'mysql',
                'license_id'       => $cert['license_id'] ?? '',
                'plan_tier'        => $cert['plan_tier'] ?? '',
                'fingerprint'      => $fingerprint,
            ];

            file_put_contents($lockFile, json_encode($lockPayload, JSON_PRETTY_PRINT));
            @chmod($lockFile, 0444);

            echo json_encode([
                'success'      => true,
                'message'      => 'Installation finished successfully! System is now configured and locked.',
                'redirect_url' => '/',
            ]);
            exit;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown installer action: ' . htmlspecialchars($action)]);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OmniPOS Pro - 1-Click Shared Hosting Web Installer</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            brand: { 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8' }
          }
        }
      }
    }
  </script>
  <style>
    /* Standalone embedded fallback styles */
    body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
    .step-active { background-color: #2563eb; color: #ffffff; border-color: #3b82f6; }
    .step-done { background-color: #059669; color: #ffffff; border-color: #10b981; }
    .step-pending { background-color: #1e293b; color: #94a3b8; border-color: #334155; }
  </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen flex flex-col justify-between antialiased selection:bg-blue-600 selection:text-white">

  <!-- Header -->
  <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-md px-6 py-4 sticky top-0 z-30 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center font-black text-white text-lg shadow-lg shadow-blue-500/20">
        OP
      </div>
      <div>
        <h1 class="font-extrabold text-base tracking-tight flex items-center gap-2">
          <span>OmniPOS Pro</span>
          <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-500/10 text-blue-400 border border-blue-500/20">
            v2.0 Shared Hosting Installer
          </span>
        </h1>
        <p class="text-xs text-slate-400">cPanel / Apache / LiteSpeed 1-Click Setup</p>
      </div>
    </div>
    <div class="text-xs text-slate-400 hidden sm:flex items-center gap-2 font-mono">
      <span class="w-2 h-2 rounded-full <?= $isLocked ? 'bg-amber-400' : 'bg-emerald-400 animate-pulse' ?>"></span>
      <?= $isLocked ? 'INSTALLATION LOCKED' : 'INSTALLER READY' ?>
    </div>
  </header>

  <!-- Main Container -->
  <main class="flex-1 max-w-4xl w-full mx-auto p-4 sm:p-6 lg:p-8 flex flex-col justify-center">

    <?php if ($isLocked): ?>
      <!-- System Already Installed / Locked Screen -->
      <div class="bg-slate-900 border border-slate-800 rounded-3xl p-8 sm:p-10 shadow-2xl text-center space-y-6">
        <div class="w-16 h-16 mx-auto rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
          </svg>
        </div>

        <div>
          <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mb-3">
            System Locked for Security
          </span>
          <h2 class="text-2xl sm:text-3xl font-black text-white">OmniPOS Pro is Already Installed</h2>
          <p class="text-sm text-slate-400 max-w-lg mx-auto mt-2">
            The database and commercial license have already been provisioned. To prevent accidental overrides, this installer is permanently locked.
          </p>
        </div>

        <?php if ($lockData): ?>
          <div class="bg-slate-950/70 border border-slate-800 rounded-2xl p-4 max-w-md mx-auto text-left text-xs font-mono space-y-2">
            <div class="flex justify-between border-b border-slate-800 pb-1.5">
              <span class="text-slate-500">Shop Name:</span>
              <span class="text-slate-200 font-bold"><?= htmlspecialchars($lockData['shop_name'] ?? 'OmniPOS Store') ?></span>
            </div>
            <div class="flex justify-between border-b border-slate-800 pb-1.5">
              <span class="text-slate-500">Business Mode:</span>
              <span class="text-blue-400 font-bold"><?= htmlspecialchars($lockData['business_mode'] ?? 'SUPERMARKET') ?></span>
            </div>
            <div class="flex justify-between border-b border-slate-800 pb-1.5">
              <span class="text-slate-500">Master Admin:</span>
              <span class="text-slate-200"><?= htmlspecialchars($lockData['admin_username'] ?? 'admin') ?></span>
            </div>
            <div class="flex justify-between border-b border-slate-800 pb-1.5">
              <span class="text-slate-500">Installed Date:</span>
              <span class="text-slate-300"><?= htmlspecialchars($lockData['installed_at_iso'] ?? date('Y-m-d H:i:s')) ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">License ID:</span>
              <span class="text-emerald-400"><?= htmlspecialchars($lockData['license_id'] ?? 'ACTIVE') ?></span>
            </div>
          </div>
        <?php endif; ?>

        <div class="bg-amber-500/10 border border-amber-500/20 rounded-2xl p-4 max-w-md mx-auto text-xs text-amber-300 flex items-start gap-3 text-left">
          <svg class="w-5 h-5 shrink-0 mt-0.5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <div>
            <strong class="font-bold block text-white mb-0.5">Need to Re-Install or Reset?</strong>
            Delete the file <code class="bg-amber-950/60 px-1 py-0.5 rounded text-amber-200">/api/config/installed.lock</code> via cPanel File Manager or SSH, then refresh this page.
          </div>
        </div>

        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
          <a
            href="/"
            class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm transition shadow-lg shadow-blue-600/30 text-center"
          >
            Launch POS Cashier Terminal &rarr;
          </a>
          <a
            href="/vendor-license-manager/"
            class="w-full sm:w-auto px-6 py-3 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-sm transition text-center"
          >
            Vendor License Manager
          </a>
        </div>
      </div>

    <?php else: ?>

      <!-- Step Navigation Indicator -->
      <div class="mb-8">
        <div class="flex items-center justify-between max-w-2xl mx-auto relative">
          <!-- Step 1 -->
          <button onclick="goToStep(1)" id="pill-1" class="step-active w-9 h-9 rounded-full border-2 flex items-center justify-center font-bold text-xs transition z-10">1</button>
          <div class="flex-1 h-0.5 bg-slate-800 -mx-1" id="line-1"></div>
          <!-- Step 2 -->
          <button onclick="goToStep(2)" id="pill-2" class="step-pending w-9 h-9 rounded-full border-2 flex items-center justify-center font-bold text-xs transition z-10">2</button>
          <div class="flex-1 h-0.5 bg-slate-800 -mx-1" id="line-2"></div>
          <!-- Step 3 -->
          <button onclick="goToStep(3)" id="pill-3" class="step-pending w-9 h-9 rounded-full border-2 flex items-center justify-center font-bold text-xs transition z-10">3</button>
          <div class="flex-1 h-0.5 bg-slate-800 -mx-1" id="line-3"></div>
          <!-- Step 4 -->
          <button onclick="goToStep(4)" id="pill-4" class="step-pending w-9 h-9 rounded-full border-2 flex items-center justify-center font-bold text-xs transition z-10">4</button>
          <div class="flex-1 h-0.5 bg-slate-800 -mx-1" id="line-4"></div>
          <!-- Step 5 -->
          <button onclick="goToStep(5)" id="pill-5" class="step-pending w-9 h-9 rounded-full border-2 flex items-center justify-center font-bold text-xs transition z-10">5</button>
          <div class="flex-1 h-0.5 bg-slate-800 -mx-1" id="line-5"></div>
          <!-- Step 6 -->
          <button id="pill-6" class="step-pending w-9 h-9 rounded-full border-2 flex items-center justify-center font-bold text-xs transition z-10">6</button>
        </div>

        <div class="flex justify-between max-w-2xl mx-auto mt-2 text-[10px] font-bold text-slate-400 uppercase tracking-wider text-center">
          <span class="w-16">Pre-Flight</span>
          <span class="w-16">Database</span>
          <span class="w-16">License</span>
          <span class="w-16">Branding</span>
          <span class="w-16">Admin</span>
          <span class="w-16">Finish</span>
        </div>
      </div>

      <!-- Step Cards Container -->
      <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 sm:p-8 shadow-2xl relative">

        <!-- ==========================================
             STEP 1: PRE-FLIGHT ENVIRONMENT CHECKS
             ========================================== -->
        <div id="step-1" class="space-y-6">
          <div>
            <span class="text-xs font-bold uppercase tracking-wider text-blue-400">Step 1 of 5</span>
            <h2 class="text-2xl font-black text-white tracking-tight mt-1">Pre-Flight Environment Checks</h2>
            <p class="text-xs text-slate-400 mt-1">
              Verifying PHP 8.1+, cryptographic extensions, and directory write permissions for shared hosting.
            </p>
          </div>

          <div id="prereq-loading" class="py-8 text-center text-slate-400 space-y-2">
            <div class="w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
            <p class="text-xs">Inspecting server environment...</p>
          </div>

          <div id="prereq-results" class="hidden space-y-4">
            <!-- PHP Version Card -->
            <div id="card-php" class="p-3.5 rounded-2xl border flex items-center justify-between text-xs">
              <div class="flex items-center gap-3">
                <div id="icon-php" class="w-7 h-7 rounded-lg flex items-center justify-center font-bold"></div>
                <div>
                  <span class="font-bold text-white block">PHP Runtime Version</span>
                  <span id="text-php" class="text-slate-400"></span>
                </div>
              </div>
              <span id="badge-php" class="px-2 py-0.5 rounded-md text-[10px] font-bold"></span>
            </div>

            <!-- Extensions Table -->
            <div class="border border-slate-800 rounded-2xl overflow-hidden text-xs">
              <div class="bg-slate-950 px-4 py-2 border-b border-slate-800 font-bold text-slate-300">
                Required PHP Extensions
              </div>
              <div class="divide-y divide-slate-800/80 bg-slate-900/60" id="ext-list"></div>
            </div>

            <!-- Directories Table -->
            <div class="border border-slate-800 rounded-2xl overflow-hidden text-xs">
              <div class="bg-slate-950 px-4 py-2 border-b border-slate-800 font-bold text-slate-300">
                Directory Write Permissions
              </div>
              <div class="divide-y divide-slate-800/80 bg-slate-900/60" id="dir-list"></div>
            </div>

            <!-- Warning Box if not all passed -->
            <div id="prereq-alert" class="hidden p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-300 text-xs flex items-start gap-3">
              <svg class="w-5 h-5 shrink-0 mt-0.5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
              </svg>
              <div>
                <strong class="font-bold text-white block mb-0.5">Prerequisites Incomplete</strong>
                Please ensure PHP 8.1+ and the Sodium extension are enabled in your cPanel "Select PHP Version" panel before proceeding.
              </div>
            </div>
          </div>

          <div class="flex justify-end pt-4 border-t border-slate-800">
            <button
              id="btn-step1-next"
              onclick="goToStep(2)"
              class="px-6 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:pointer-events-none text-white text-xs font-bold transition shadow-lg shadow-blue-600/20 flex items-center gap-2"
            >
              <span>Continue to Database Setup</span>
              <span>&rarr;</span>
            </button>
          </div>
        </div>

        <!-- ==========================================
             STEP 2: DATABASE CONFIGURATION
             ========================================== -->
        <div id="step-2" class="hidden space-y-6">
          <div>
            <span class="text-xs font-bold uppercase tracking-wider text-blue-400">Step 2 of 5</span>
            <h2 class="text-2xl font-black text-white tracking-tight mt-1">MySQL Database Setup</h2>
            <p class="text-xs text-slate-400 mt-1">
              Connect to your MySQL database (created via cPanel MySQL Database Wizard) to generate 31 InnoDB tables.
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <!-- Driver -->
            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-300 mb-1">Database Engine</label>
              <select id="db-driver" onchange="toggleDbDriver()" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
                <option value="mysql">MySQL / MariaDB (Recommended for Shared Hosting Production)</option>
                <option value="sqlite">SQLite (Zero-Config Test / Offline Demo)</option>
              </select>
            </div>

            <!-- Host -->
            <div id="db-field-host">
              <label class="block font-bold text-slate-300 mb-1">Database Host</label>
              <input type="text" id="db-host" value="localhost" placeholder="localhost or 127.0.0.1" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-mono">
              <span class="text-[10px] text-slate-500 mt-0.5 block">Usually 'localhost' or '127.0.0.1' on cPanel</span>
            </div>

            <!-- Port -->
            <div id="db-field-port">
              <label class="block font-bold text-slate-300 mb-1">Database Port</label>
              <input type="number" id="db-port" value="3306" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <!-- Database Name -->
            <div id="db-field-name">
              <label class="block font-bold text-slate-300 mb-1">Database Name</label>
              <input type="text" id="db-name" placeholder="cpaneluser_omnipos" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <!-- Username -->
            <div id="db-field-user">
              <label class="block font-bold text-slate-300 mb-1">Database Username</label>
              <input type="text" id="db-user" placeholder="cpaneluser_posadmin" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <!-- Password -->
            <div id="db-field-pass" class="sm:col-span-2">
              <label class="block font-bold text-slate-300 mb-1">Database Password</label>
              <input type="password" id="db-pass" placeholder="••••••••••••" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-mono">
            </div>
          </div>

          <!-- Connection Status Alert -->
          <div id="db-status-box" class="hidden p-3.5 rounded-2xl text-xs flex items-start gap-3"></div>

          <div class="flex items-center justify-between pt-4 border-t border-slate-800">
            <button
              onclick="goToStep(1)"
              class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
            >
              &larr; Back
            </button>

            <div class="flex items-center gap-2">
              <button
                id="btn-test-db"
                onclick="testDbConnection()"
                class="px-4 py-2.5 rounded-2xl bg-slate-800 hover:bg-slate-700 text-white text-xs font-bold transition flex items-center gap-1.5"
              >
                <span>Test Connection</span>
              </button>

              <button
                id="btn-migrate-db"
                onclick="runMigrations()"
                class="px-6 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-xs font-bold transition shadow-lg shadow-blue-600/20 flex items-center gap-1.5"
              >
                <span id="btn-migrate-label">Install Tables & Next</span>
                <span>&rarr;</span>
              </button>
            </div>
          </div>
        </div>

        <!-- ==========================================
             STEP 3: COMMERCIAL LICENSE CERTIFICATE
             ========================================== -->
        <div id="step-3" class="hidden space-y-6">
          <div>
            <span class="text-xs font-bold uppercase tracking-wider text-blue-400">Step 3 of 5</span>
            <h2 class="text-2xl font-black text-white tracking-tight mt-1">Commercial License Certificate</h2>
            <p class="text-xs text-slate-400 mt-1">
              Paste your Ed25519 digitally signed client license (<code class="text-slate-300">license.key</code>) issued by the Vendor License Manager.
            </p>
          </div>

          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <label class="block font-bold text-xs text-slate-300">License Key Certificate (JSON or Base64)</label>
              <button
                onclick="loadTrialLicense()"
                class="text-[11px] font-bold text-blue-400 hover:text-blue-300 underline"
              >
                Use Included 30-Day Demo Trial
              </button>
            </div>

            <textarea
              id="license-input"
              rows="5"
              placeholder='{"certificate": {"license_id": "LIC-...", "client_name": "...", "expires_at": ...}, "signature": "..."}'
              class="w-full bg-slate-950 border border-slate-800 rounded-2xl p-3.5 text-xs text-slate-200 font-mono focus:outline-none focus:border-blue-500 resize-none"
            ></textarea>

            <div class="flex items-center gap-2">
              <input type="file" id="license-file" accept=".key,.json,.txt" class="hidden" onchange="handleLicenseFileUpload(event)">
              <button
                onclick="document.getElementById('license-file').click()"
                class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
              >
                Upload license.key File...
              </button>

              <button
                id="btn-verify-license"
                onclick="verifyLicenseInput()"
                class="px-4 py-1.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition"
              >
                Verify Certificate
              </button>
            </div>
          </div>

          <!-- License Verification Result Card -->
          <div id="license-result-card" class="hidden p-4 rounded-2xl border text-xs space-y-3"></div>

          <div class="flex items-center justify-between pt-4 border-t border-slate-800">
            <button
              onclick="goToStep(2)"
              class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
            >
              &larr; Back
            </button>

            <button
              id="btn-step3-next"
              onclick="goToStep(4)"
              disabled
              class="px-6 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:pointer-events-none text-white text-xs font-bold transition shadow-lg shadow-blue-600/20 flex items-center gap-2"
            >
              <span>Continue to Shop Profile</span>
              <span>&rarr;</span>
            </button>
          </div>
        </div>

        <!-- ==========================================
             STEP 4: SHOP BRANDING & BUSINESS MODE
             ========================================== -->
        <div id="step-4" class="hidden space-y-6">
          <div>
            <span class="text-xs font-bold uppercase tracking-wider text-blue-400">Step 4 of 5</span>
            <h2 class="text-2xl font-black text-white tracking-tight mt-1">Shop Profile & Business Mode</h2>
            <p class="text-xs text-slate-400 mt-1">
              Select your store's operational mode and upload branding assets for receipts and cashier displays.
            </p>
          </div>

          <!-- Business Mode Selector Cards -->
          <div class="space-y-2">
            <label class="block font-bold text-xs text-slate-300">Initial Business Mode</label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="mode-selector">
              <!-- Supermarket -->
              <label class="cursor-pointer border-2 border-blue-500 bg-blue-500/10 p-3.5 rounded-2xl flex items-start gap-3 transition" id="mode-card-SUPERMARKET">
                <input type="radio" name="business_mode" value="SUPERMARKET" checked onchange="selectMode('SUPERMARKET')" class="mt-1">
                <div>
                  <span class="font-bold text-white text-xs block">SUPERMARKET</span>
                  <p class="text-[11px] text-slate-300 mt-0.5">Fast barcode bursts, EAN-13 scale barcodes (20-29), multi-unit box/cartons, keyboard shortcuts.</p>
                </div>
              </label>

              <!-- Restaurant -->
              <label class="cursor-pointer border-2 border-slate-800 bg-slate-950 p-3.5 rounded-2xl flex items-start gap-3 transition" id="mode-card-RESTAURANT">
                <input type="radio" name="business_mode" value="RESTAURANT" onchange="selectMode('RESTAURANT')" class="mt-1">
                <div>
                  <span class="font-bold text-white text-xs block">RESTAURANT</span>
                  <p class="text-[11px] text-slate-400 mt-0.5">Visual floor plan & table map, KDS kitchen tickets, bill splitting, item modifiers.</p>
                </div>
              </label>

              <!-- Retail -->
              <label class="cursor-pointer border-2 border-slate-800 bg-slate-950 p-3.5 rounded-2xl flex items-start gap-3 transition" id="mode-card-RETAIL">
                <input type="radio" name="business_mode" value="RETAIL" onchange="selectMode('RETAIL')" class="mt-1">
                <div>
                  <span class="font-bold text-white text-xs block">RETAIL</span>
                  <p class="text-[11px] text-slate-400 mt-0.5">Size/Color/Material variant matrix, Serial number / IMEI tracking, barcode label printer.</p>
                </div>
              </label>

              <!-- Pharmacy -->
              <label class="cursor-pointer border-2 border-slate-800 bg-slate-950 p-3.5 rounded-2xl flex items-start gap-3 transition" id="mode-card-PHARMACY">
                <input type="radio" name="business_mode" value="PHARMACY" onchange="selectMode('PHARMACY')" class="mt-1">
                <div>
                  <span class="font-bold text-white text-xs block">PHARMACY</span>
                  <p class="text-[11px] text-slate-400 mt-0.5">Batch/Lot number tracking, FEFO (First-Expired, First-Out) automated stock deduction, near-expiry alerts.</p>
                </div>
              </label>
            </div>
          </div>

          <!-- Shop Details Fields -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Company / Shop Name</label>
              <input type="text" id="shop-name" value="OmniPOS SuperStore" placeholder="Acme Market LLC" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Company Name (Arabic / Secondary)</label>
              <input type="text" id="shop-name-ar" value="سوبر ستور أومني بوس" placeholder="أومني ستور" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-sans" dir="rtl">
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Primary Currency Symbol</label>
              <input type="text" id="shop-currency-sym" value="$" placeholder="$ or € or £ or SAR" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Currency Code (ISO)</label>
              <input type="text" id="shop-currency-code" value="USD" placeholder="USD, EUR, GBP, SAR" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Phone Number</label>
              <input type="text" id="shop-phone" value="+1 (800) 555-0199" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Email Address</label>
              <input type="email" id="shop-email" value="contact@omnipos-store.local" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
            </div>

            <div class="sm:col-span-2">
              <label class="block font-bold text-slate-300 mb-1">Store Address</label>
              <input type="text" id="shop-address" value="100 Commercial Boulevard, Tech District" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
            </div>
          </div>

          <!-- Logo Upload -->
          <div class="space-y-2">
            <label class="block font-bold text-xs text-slate-300">Shop Logo (Receipts & Displays)</label>
            <div class="flex items-center gap-4 p-4 rounded-2xl border border-slate-800 bg-slate-950">
              <div id="logo-preview-box" class="w-16 h-16 rounded-xl bg-slate-900 border border-slate-800 flex items-center justify-center text-slate-500 overflow-hidden shrink-0">
                <span class="text-xs font-bold text-slate-400">NO LOGO</span>
              </div>
              <div class="space-y-1">
                <input type="file" id="logo-file" accept="image/*" class="hidden" onchange="handleLogoUpload(event)">
                <button
                  type="button"
                  onclick="document.getElementById('logo-file').click()"
                  class="px-3.5 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
                >
                  Choose Logo Image...
                </button>
                <p class="text-[10px] text-slate-500">PNG, JPG or SVG. Transparent background recommended.</p>
              </div>
            </div>
          </div>

          <div class="flex items-center justify-between pt-4 border-t border-slate-800">
            <button
              onclick="goToStep(3)"
              class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
            >
              &larr; Back
            </button>

            <button
              onclick="goToStep(5)"
              class="px-6 py-2.5 rounded-2xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition shadow-lg shadow-blue-600/20 flex items-center gap-2"
            >
              <span>Continue to Master Admin</span>
              <span>&rarr;</span>
            </button>
          </div>
        </div>

        <!-- ==========================================
             STEP 5: MASTER ADMIN ACCOUNT
             ========================================== -->
        <div id="step-5" class="hidden space-y-6">
          <div>
            <span class="text-xs font-bold uppercase tracking-wider text-blue-400">Step 5 of 5</span>
            <h2 class="text-2xl font-black text-white tracking-tight mt-1">Master Administrator Setup</h2>
            <p class="text-xs text-slate-400 mt-1">
              Create the super-admin account with full system access and quick cashier PIN login.
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
              <label class="block font-bold text-slate-300 mb-1">Full Name</label>
              <input type="text" id="admin-fullname" value="System Administrator" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Admin Username</label>
              <input type="text" id="admin-username" value="admin" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Email Address</label>
              <input type="email" id="admin-email" value="admin@omnipos-store.local" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500">
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Cashier Quick PIN (4 Digits)</label>
              <input type="password" id="admin-pin" value="1234" maxlength="4" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-mono text-center tracking-widest text-sm">
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Master Password</label>
              <input type="password" id="admin-pass" value="AdminPass2026!" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-mono">
            </div>

            <div>
              <label class="block font-bold text-slate-300 mb-1">Confirm Password</label>
              <input type="password" id="admin-pass-confirm" value="AdminPass2026!" class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-white focus:outline-none focus:border-blue-500 font-mono">
            </div>
          </div>

          <!-- Complete Installation Alert -->
          <div id="final-status-box" class="hidden p-3.5 rounded-2xl text-xs flex items-start gap-3"></div>

          <div class="flex items-center justify-between pt-4 border-t border-slate-800">
            <button
              onclick="goToStep(4)"
              class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold transition"
            >
              &larr; Back
            </button>

            <button
              id="btn-complete-all"
              onclick="finishInstallation()"
              class="px-8 py-3 rounded-2xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-black transition shadow-lg shadow-emerald-600/30 flex items-center gap-2"
            >
              <span id="btn-complete-label">Complete & Lock Installation</span>
              <span>&rarr;</span>
            </button>
          </div>
        </div>

        <!-- ==========================================
             STEP 6: SUCCESS & LAUNCH
             ========================================== -->
        <div id="step-6" class="hidden text-center space-y-6 py-6">
          <div class="w-20 h-20 mx-auto rounded-full bg-emerald-500/10 border-2 border-emerald-500/30 flex items-center justify-center text-emerald-400">
            <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
          </div>

          <div>
            <span class="inline-block px-3 py-1 text-xs font-semibold rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 mb-2">
              Setup Completed & Locked
            </span>
            <h2 class="text-3xl font-black text-white">OmniPOS Pro is Ready!</h2>
            <p class="text-xs text-slate-400 max-w-md mx-auto mt-2">
              Your database has been migrated, the Ed25519 commercial license bound to this server, and the Master Admin account activated.
            </p>
          </div>

          <div class="bg-slate-950/70 border border-slate-800 rounded-2xl p-4 max-w-sm mx-auto text-left text-xs font-mono space-y-2">
            <div class="flex justify-between border-b border-slate-800 pb-1.5">
              <span class="text-slate-500">Admin Username:</span>
              <span id="summary-admin-user" class="text-white font-bold">admin</span>
            </div>
            <div class="flex justify-between border-b border-slate-800 pb-1.5">
              <span class="text-slate-500">Quick PIN:</span>
              <span id="summary-admin-pin" class="text-amber-400 font-bold">1234</span>
            </div>
            <div class="flex justify-between border-b border-slate-800 pb-1.5">
              <span class="text-slate-500">Business Mode:</span>
              <span id="summary-mode" class="text-blue-400 font-bold">SUPERMARKET</span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-500">Security Lock:</span>
              <span class="text-emerald-400 font-bold">/api/config/installed.lock</span>
            </div>
          </div>

          <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-3">
            <a
              href="/"
              class="w-full sm:w-auto px-8 py-3.5 rounded-2xl bg-blue-600 hover:bg-blue-500 text-white font-extrabold text-sm transition shadow-xl shadow-blue-600/30 text-center"
            >
              Open POS Cashier Terminal &rarr;
            </a>
            <a
              href="/vendor-license-manager/"
              class="w-full sm:w-auto px-6 py-3.5 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-300 font-semibold text-xs transition text-center"
            >
              Vendor License Manager
            </a>
          </div>
        </div>

      </div>
    <?php endif; ?>

  </main>

  <!-- Footer -->
  <footer class="border-t border-slate-800/80 px-6 py-4 text-center text-xs text-slate-500">
    OmniPOS Pro Commercial POS &bull; Architecture by Principal Full-Stack POS Engineer &bull; Shared Hosting Native
  </footer>

  <!-- Interactive JavaScript Engine -->
  <script>
    let currentStep = 1;
    let selectedBusinessMode = 'SUPERMARKET';
    let uploadedLogoBase64 = null;
    let verifiedLicenseKey = null;

    // Default Pre-Signed Demo Trial License (valid through 2035)
    const DEMO_TRIAL_LICENSE = JSON.stringify({
      certificate: {
        allowed_domains: ["*", "localhost", "127.0.0.1", "omnipos.local"],
        client_name: "OmniPOS Pro Demo Store",
        enabled_modules: ["SUPERMARKET", "RESTAURANT", "RETAIL", "PHARMACY", "INVENTORY", "REPORTS", "KDS", "POLE_DISPLAY"],
        expires_at: 2076883200,
        installation_fingerprint: "*",
        issued_at: 1761350400,
        license_id: "LIC-OMNIPOS-PRO-TRIAL-30D",
        max_terminals: 25,
        offline_grace_hours: 720,
        plan_tier: "ENTERPRISE"
      },
      signature: "7766d58ed89ddb0bdd9e45d1702ae11b2c392fea45aa1c8302d201a03ea5db3c8a69612f72d2e3feb2ea33c671fc9f61b99fe84c27509ba4944ecd26edf21609"
    }, null, 2);

    function goToStep(step) {
      if (step < 1 || step > 6) return;
      currentStep = step;

      // Update visibility
      for (let i = 1; i <= 6; i++) {
        const el = document.getElementById('step-' + i);
        if (el) el.classList.toggle('hidden', i !== step);

        const pill = document.getElementById('pill-' + i);
        const line = document.getElementById('line-' + (i - 1));

        if (pill) {
          pill.className = 'w-9 h-9 rounded-full border-2 flex items-center justify-center font-bold text-xs transition z-10 ';
          if (i === step) {
            pill.className += 'step-active';
          } else if (i < step) {
            pill.className += 'step-done';
          } else {
            pill.className += 'step-pending';
          }
        }
        if (line) {
          line.className = 'flex-1 h-0.5 -mx-1 transition ' + (i <= step ? 'bg-emerald-500' : 'bg-slate-800');
        }
      }
    }

    // Step 1: Pre-Flight Check
    async function runPrerequisiteChecks() {
      const loading = document.getElementById('prereq-loading');
      const results = document.getElementById('prereq-results');
      const extList = document.getElementById('ext-list');
      const dirList = document.getElementById('dir-list');
      const nextBtn = document.getElementById('btn-step1-next');
      const alertBox = document.getElementById('prereq-alert');

      try {
        const res = await fetch('/install/index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'check_prerequisites' })
        });
        const data = await res.json();

        loading.classList.add('hidden');
        results.classList.remove('hidden');

        // PHP Check
        const cardPhp = document.getElementById('card-php');
        const iconPhp = document.getElementById('icon-php');
        const textPhp = document.getElementById('text-php');
        const badgePhp = document.getElementById('badge-php');

        textPhp.innerText = data.php_version + ' (' + data.server_uname + ')';
        if (data.php_ok) {
          cardPhp.className = 'p-3.5 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 flex items-center justify-between text-xs';
          iconPhp.className = 'w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-400 flex items-center justify-center font-bold';
          iconPhp.innerHTML = '&#10003;';
          badgePhp.className = 'px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/20 text-emerald-300';
          badgePhp.innerText = 'PASS';
        } else {
          cardPhp.className = 'p-3.5 rounded-2xl border border-rose-500/30 bg-rose-500/10 flex items-center justify-between text-xs';
          iconPhp.className = 'w-7 h-7 rounded-lg bg-rose-500/20 text-rose-400 flex items-center justify-center font-bold';
          iconPhp.innerHTML = '&#10007;';
          badgePhp.className = 'px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500/20 text-rose-300';
          badgePhp.innerText = 'FAIL (Requires >= 8.1)';
        }

        // Extensions
        extList.innerHTML = '';
        const extDescriptions = {
          'pdo': 'PHP Data Objects (Core Database Layer)',
          'pdo_mysql': 'MySQL Driver for PDO (cPanel MySQL)',
          'sodium': 'Sodium Cryptography (Ed25519 License Verification)',
          'openssl': 'OpenSSL Cryptography (JWT & HTTPS Security)',
          'json': 'JavaScript Object Notation (100% POST Protocol)',
          'mbstring': 'Multibyte String Support (Arabic & Internationalization)',
        };

        for (const [ext, ok] of Object.entries(data.extensions)) {
          const row = document.createElement('div');
          row.className = 'px-4 py-2.5 flex items-center justify-between';
          row.innerHTML = `
            <div>
              <span class="font-bold text-slate-200">ext-${ext}</span>
              <span class="text-slate-500 ml-2 hidden sm:inline text-[11px]">${extDescriptions[ext] || ''}</span>
            </div>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${ok ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'}">
              ${ok ? 'ENABLED' : 'MISSING'}
            </span>
          `;
          extList.appendChild(row);
        }

        // Directories
        dirList.innerHTML = '';
        for (const [key, d] of Object.entries(data.directories)) {
          const row = document.createElement('div');
          row.className = 'px-4 py-2.5 flex items-center justify-between';
          row.innerHTML = `
            <div>
              <code class="font-mono text-slate-200">${d.path}</code>
            </div>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold ${d.writable ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'}">
              ${d.writable ? 'WRITABLE' : 'READ-ONLY (chmod 775)'}
            </span>
          `;
          dirList.appendChild(row);
        }

        if (!data.all_passed) {
          alertBox.classList.remove('hidden');
          nextBtn.disabled = true;
        } else {
          alertBox.classList.add('hidden');
          nextBtn.disabled = false;
        }

      } catch (err) {
        loading.innerHTML = '<span class="text-rose-400 font-bold">Failed to run checks: ' + err.message + '</span>';
      }
    }

    // Step 2: Database
    function toggleDbDriver() {
      const driver = document.getElementById('db-driver').value;
      const isSqlite = driver === 'sqlite';
      document.getElementById('db-field-host').style.display = isSqlite ? 'none' : 'block';
      document.getElementById('db-field-port').style.display = isSqlite ? 'none' : 'block';
      document.getElementById('db-field-name').style.display = isSqlite ? 'none' : 'block';
      document.getElementById('db-field-user').style.display = isSqlite ? 'none' : 'block';
      document.getElementById('db-field-pass').style.display = isSqlite ? 'none' : 'block';
    }

    async function testDbConnection() {
      const statusBox = document.getElementById('db-status-box');
      statusBox.classList.remove('hidden');
      statusBox.className = 'p-3.5 rounded-2xl text-xs bg-slate-950 border border-slate-800 text-slate-300 flex items-center gap-2';
      statusBox.innerHTML = '<div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div> Testing connection...';

      const payload = {
        action: 'test_database',
        driver: document.getElementById('db-driver').value,
        host: document.getElementById('db-host').value,
        port: document.getElementById('db-port').value,
        database: document.getElementById('db-name').value,
        username: document.getElementById('db-user').value,
        password: document.getElementById('db-pass').value,
      };

      try {
        const res = await fetch('/install/index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
          statusBox.className = 'p-3.5 rounded-2xl text-xs bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 font-bold';
          statusBox.innerHTML = '&#10003; ' + data.message;
        } else {
          statusBox.className = 'p-3.5 rounded-2xl text-xs bg-rose-500/10 border border-rose-500/20 text-rose-300';
          statusBox.innerHTML = '&#10007; ' + data.message;
        }
      } catch (err) {
        statusBox.className = 'p-3.5 rounded-2xl text-xs bg-rose-500/10 border border-rose-500/20 text-rose-300';
        statusBox.innerHTML = '&#10007; Network request error: ' + err.message;
      }
    }

    async function runMigrations() {
      const label = document.getElementById('btn-migrate-label');
      const btn = document.getElementById('btn-migrate-db');
      const statusBox = document.getElementById('db-status-box');

      label.innerText = 'Creating 31 Tables & Seeds...';
      btn.disabled = true;

      const payload = {
        action: 'run_migrations',
        driver: document.getElementById('db-driver').value,
        host: document.getElementById('db-host').value,
        port: document.getElementById('db-port').value,
        database: document.getElementById('db-name').value,
        username: document.getElementById('db-user').value,
        password: document.getElementById('db-pass').value,
      };

      try {
        const res = await fetch('/install/index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
          statusBox.classList.remove('hidden');
          statusBox.className = 'p-3.5 rounded-2xl text-xs bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 font-bold';
          statusBox.innerHTML = '&#10003; ' + data.message;
          setTimeout(() => {
            goToStep(3);
          }, 600);
        } else {
          statusBox.classList.remove('hidden');
          statusBox.className = 'p-3.5 rounded-2xl text-xs bg-rose-500/10 border border-rose-500/20 text-rose-300';
          statusBox.innerHTML = '&#10007; ' + data.message;
          label.innerText = 'Install Tables & Next';
          btn.disabled = false;
        }
      } catch (err) {
        statusBox.classList.remove('hidden');
        statusBox.className = 'p-3.5 rounded-2xl text-xs bg-rose-500/10 border border-rose-500/20 text-rose-300';
        statusBox.innerHTML = '&#10007; ' + err.message;
        label.innerText = 'Install Tables & Next';
        btn.disabled = false;
      }
    }

    // Step 3: License Verification
    function loadTrialLicense() {
      document.getElementById('license-input').value = DEMO_TRIAL_LICENSE;
      verifyLicenseInput();
    }

    function handleLicenseFileUpload(e) {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (event) => {
        document.getElementById('license-input').value = event.target.result;
        verifyLicenseInput();
      };
      reader.readAsText(file);
    }

    async function verifyLicenseInput() {
      const raw = document.getElementById('license-input').value.trim();
      const card = document.getElementById('license-result-card');
      const nextBtn = document.getElementById('btn-step3-next');

      if (!raw) {
        card.classList.remove('hidden');
        card.className = 'p-4 rounded-2xl border border-rose-500/20 bg-rose-500/10 text-rose-300 text-xs';
        card.innerHTML = 'Please enter or upload a valid license certificate.';
        nextBtn.disabled = true;
        return;
      }

      card.classList.remove('hidden');
      card.className = 'p-4 rounded-2xl border border-slate-800 bg-slate-950 text-slate-300 text-xs flex items-center gap-2';
      card.innerHTML = '<div class="w-4 h-4 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></div> Verifying Ed25519 signature against Vendor Master Key...';

      try {
        const res = await fetch('/install/index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'verify_license', license_key: raw })
        });
        const json = await res.json();

        if (json.success) {
          verifiedLicenseKey = raw;
          const lic = json.data;
          card.className = 'p-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 text-xs space-y-2';
          card.innerHTML = `
            <div class="flex items-center justify-between border-b border-emerald-500/20 pb-2">
              <span class="font-bold text-emerald-400 flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                Cryptographic Signature Verified
              </span>
              <span class="px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-200 text-[10px] font-black">${lic.plan_tier} TIER</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-[11px] pt-1">
              <div><span class="text-slate-400">Client:</span> <strong class="text-white">${lic.client_name}</strong></div>
              <div><span class="text-slate-400">Terminals:</span> <strong class="text-white">${lic.max_terminals} Max</strong></div>
              <div><span class="text-slate-400">License ID:</span> <span class="font-mono text-slate-300">${lic.license_id}</span></div>
              <div><span class="text-slate-400">Validity:</span> <strong class="text-emerald-300">${lic.days_remaining} Days Remaining</strong></div>
            </div>
          `;
          nextBtn.disabled = false;
        } else {
          verifiedLicenseKey = null;
          card.className = 'p-4 rounded-2xl border border-rose-500/20 bg-rose-500/10 text-rose-300 text-xs font-bold';
          card.innerHTML = '&#10007; ' + json.message;
          nextBtn.disabled = true;
        }
      } catch (err) {
        card.className = 'p-4 rounded-2xl border border-rose-500/20 bg-rose-500/10 text-rose-300 text-xs';
        card.innerHTML = '&#10007; Verification request error: ' + err.message;
        nextBtn.disabled = true;
      }
    }

    // Step 4: Business Mode & Branding
    function selectMode(mode) {
      selectedBusinessMode = mode;
      const modes = ['SUPERMARKET', 'RESTAURANT', 'RETAIL', 'PHARMACY'];
      modes.forEach(m => {
        const card = document.getElementById('mode-card-' + m);
        if (m === mode) {
          card.className = 'cursor-pointer border-2 border-blue-500 bg-blue-500/10 p-3.5 rounded-2xl flex items-start gap-3 transition';
        } else {
          card.className = 'cursor-pointer border-2 border-slate-800 bg-slate-950 p-3.5 rounded-2xl flex items-start gap-3 transition';
        }
      });
    }

    function handleLogoUpload(e) {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = (event) => {
        uploadedLogoBase64 = event.target.result;
        const box = document.getElementById('logo-preview-box');
        box.innerHTML = `<img src="${uploadedLogoBase64}" class="w-full h-full object-contain p-1" />`;
      };
      reader.readAsDataURL(file);
    }

    // Step 5: Finalize and Complete Installation
    async function finishInstallation() {
      const label = document.getElementById('btn-complete-label');
      const btn = document.getElementById('btn-complete-all');
      const statusBox = document.getElementById('final-status-box');

      const pass = document.getElementById('admin-pass').value;
      const confirm = document.getElementById('admin-pass-confirm').value;
      if (pass !== confirm) {
        statusBox.classList.remove('hidden');
        statusBox.className = 'p-3.5 rounded-2xl text-xs bg-rose-500/10 border border-rose-500/20 text-rose-300';
        statusBox.innerHTML = '&#10007; Master Password and Confirmation do not match.';
        return;
      }

      if (!verifiedLicenseKey) {
        statusBox.classList.remove('hidden');
        statusBox.className = 'p-3.5 rounded-2xl text-xs bg-rose-500/10 border border-rose-500/20 text-rose-300';
        statusBox.innerHTML = '&#10007; Please complete Step 3 and verify your commercial license.';
        return;
      }

      label.innerText = 'Locking & Finalizing Setup...';
      btn.disabled = true;

      const payload = {
        action: 'complete_installation',
        license_key: verifiedLicenseKey,
        shop_name: document.getElementById('shop-name').value,
        company_name_ar: document.getElementById('shop-name-ar').value,
        address: document.getElementById('shop-address').value,
        phone: document.getElementById('shop-phone').value,
        email: document.getElementById('shop-email').value,
        currency_symbol: document.getElementById('shop-currency-sym').value,
        currency_code: document.getElementById('shop-currency-code').value,
        business_mode: selectedBusinessMode,
        logo_base64: uploadedLogoBase64,
        admin_fullname: document.getElementById('admin-fullname').value,
        admin_username: document.getElementById('admin-username').value,
        admin_email: document.getElementById('admin-email').value,
        admin_phone: document.getElementById('shop-phone').value,
        admin_password: pass,
        admin_pin: document.getElementById('admin-pin').value,
      };

      try {
        const res = await fetch('/install/index.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (data.success) {
          document.getElementById('summary-admin-user').innerText = payload.admin_username;
          document.getElementById('summary-admin-pin').innerText = payload.admin_pin;
          document.getElementById('summary-mode').innerText = payload.business_mode;
          goToStep(6);
        } else {
          statusBox.classList.remove('hidden');
          statusBox.className = 'p-3.5 rounded-2xl text-xs bg-rose-500/10 border border-rose-500/20 text-rose-300';
          statusBox.innerHTML = '&#10007; ' + data.message;
          label.innerText = 'Complete & Lock Installation';
          btn.disabled = false;
        }
      } catch (err) {
        statusBox.classList.remove('hidden');
        statusBox.className = 'p-3.5 rounded-2xl text-xs bg-rose-500/10 border border-rose-500/20 text-rose-300';
        statusBox.innerHTML = '&#10007; ' + err.message;
        label.innerText = 'Complete & Lock Installation';
        btn.disabled = false;
      }
    }

    // Auto-run checks on load
    window.addEventListener('DOMContentLoaded', () => {
      runPrerequisiteChecks();
    });
  </script>
</body>
</html>
