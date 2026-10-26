<?php
declare(strict_types=1);

/**
 * Vendra Vault - Central License Authority & Portal Gateway API
 * Official Endpoint: https://vendra.charafeddine.xyz/license-api/index.php
 * Strictly enforces 100% POST JSON-RPC Protocol.
 */

// Global Error Handling
error_reporting(E_ALL);
ini_set('display_errors', '0');

// CORS Preflight & Global Headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header("Access-Control-Allow-Origin: {$origin}");
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Terminal-Id');
header('Access-Control-Max-Age: 86400');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// STRICT CONSTRAINT: 100% POST Protocol
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode([
        'success'   => false,
        'message'   => 'Method Not Allowed. Vendra License Authority strictly enforces 100% POST protocol.',
        'error'     => ['code' => 'METHOD_NOT_ALLOWED'],
        'timestamp' => time(),
    ]);
    exit;
}

header('Content-Type: application/json; charset=UTF-8');

// Load Configuration
$config = require __DIR__ . '/../config/config.php';

// Helper: JSON Response
function respond(bool $success, mixed $data = null, string $message = 'Success', int $code = 200, ?array $error = null): void {
    http_response_code($code);
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'data'      => $data,
        'error'     => $error,
        'code'      => $code,
        'timestamp' => time(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// Helper: Database Singleton
function getDB(array $config): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dbCfg = $config['db'];
    $driver = $dbCfg['driver'] ?? 'sqlite';

    if ($driver === 'sqlite') {
        $dbPath = $dbCfg['database'];
        $dbDir = dirname($dbPath);
        if (!is_dir($dbDir)) @mkdir($dbDir, 0775, true);
        $isNew = !file_exists($dbPath) || filesize($dbPath) === 0;

        $pdo = new PDO("sqlite:{$dbPath}", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        if ($isNew) {
            initPortalSqliteDatabase($pdo);
        }
    } else {
        $dsn = "mysql:host={$dbCfg['host']};port={$dbCfg['port']};dbname={$dbCfg['database']};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbCfg['username'], $dbCfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        try {
            $check = $pdo->query("SHOW TABLES LIKE 'portal_licenses'")->fetchColumn();
            if (!$check) {
                initPortalMysqlDatabase($pdo);
            }
        } catch (\Throwable $e) {}
    }

    return $pdo;
}

function initPortalMysqlDatabase(PDO $pdo): void {
    $schemaFile = __DIR__ . '/../database/vendra_portal_schema.sql';
    if (!file_exists($schemaFile)) return;

    $sql = file_get_contents($schemaFile);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            try { $pdo->exec($stmt); } catch (\Throwable $t) {}
        }
    }
}

function initPortalSqliteDatabase(PDO $pdo): void {
    $schemaFile = __DIR__ . '/../database/vendra_portal_schema.sql';
    if (!file_exists($schemaFile)) return;

    $sql = file_get_contents($schemaFile);
    $sql = preg_replace('/ENGINE=\w+ DEFAULT CHARSET=\w+ COLLATE=[\w_]+/i', '', $sql);
    $sql = preg_replace('/SET FOREIGN_KEY_CHECKS\s*=\s*[01];/i', '', $sql);
    $sql = preg_replace('/INT UNSIGNED AUTO_INCREMENT PRIMARY KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
    $sql = preg_replace('/AUTO_INCREMENT/i', '', $sql);
    $sql = preg_replace('/INT UNSIGNED/i', 'INTEGER', $sql);
    $sql = preg_replace('/BIGINT UNSIGNED/i', 'INTEGER', $sql);
    $sql = preg_replace('/MEDIUMTEXT/i', 'TEXT', $sql);
    $sql = preg_replace('/ENUM\([^)]+\)/i', 'TEXT', $sql);
    $sql = preg_replace('/JSON/i', 'TEXT', $sql);
    $sql = preg_replace('/ON UPDATE CURRENT_TIMESTAMP/i', '', $sql);
    $sql = preg_replace('/ON DUPLICATE KEY UPDATE[^;]+/i', '', $sql);
    $sql = preg_replace('/,\s*INDEX\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);

    $statements = array_filter(array_map('trim', explode(';', $sql)));
    foreach ($statements as $stmt) {
        if (!empty($stmt)) {
            try { $pdo->exec($stmt); } catch (\Throwable $t) {}
        }
    }
}

// Parse Envelope Body
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true);
if (!is_array($body)) {
    $body = $_POST;
}

$action = $body['action'] ?? '';
$payload = $body['payload'] ?? $body;
$authToken = $body['auth_token'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (str_starts_with($authToken, 'Bearer ')) {
    $authToken = substr($authToken, 7);
}

try {
    $pdo = getDB($config);
} catch (\Throwable $e) {
    respond(false, null, 'Portal Database Connection Error: ' . $e->getMessage(), 500);
}

// Route Central License Authority Actions
switch ($action) {

    // ==========================================
    // 1. INBOUND LEAD CREATION (LANDING PAGE)
    // ==========================================
    case 'lead.create':
        $shopName = trim((string)($payload['shop_name'] ?? ''));
        $contactName = trim((string)($payload['contact_name'] ?? ''));
        $email = trim((string)($payload['email'] ?? ''));
        $phone = trim((string)($payload['phone'] ?? ''));
        $industry = strtoupper(trim((string)($payload['industry'] ?? 'SUPERMARKET')));
        $planTier = strtoupper(trim((string)($payload['plan_tier'] ?? 'BUSINESS_PRO')));
        $terminals = max(1, (int)($payload['terminals'] ?? 1));
        $notes = trim((string)($payload['notes'] ?? ''));

        if (empty($shopName) || empty($phone)) {
            respond(false, null, 'Shop name and contact phone number are required.', 400);
        }

        $stmt = $pdo->prepare('INSERT INTO `portal_leads` (
            `shop_name`, `contact_name`, `email`, `phone`, `industry`, `plan_tier`, `terminals`, `notes`, `status`
        ) VALUES (
            :sn, :cn, :em, :ph, :ind, :pt, :term, :notes, "NEW"
        )');
        $stmt->execute([
            ':sn' => $shopName,
            ':cn' => $contactName ?: $shopName,
            ':em' => $email,
            ':ph' => $phone,
            ':ind' => $industry,
            ':pt' => $planTier,
            ':term' => $terminals,
            ':notes' => $notes,
        ]);

        respond(true, [
            'lead_id'   => $pdo->lastInsertId(),
            'shop_name' => $shopName,
        ], 'Your demo request has been submitted successfully! A Vendra specialist will reach out shortly.');
        break;

    // ==========================================
    // 2. CENTRAL LICENSE HEARTBEAT (CLIENT SHOPS)
    // ==========================================
    case 'license.verifyHeartbeat':
        $licenseId = trim((string)($payload['license_id'] ?? ''));
        $clientDomain = trim((string)($payload['domain'] ?? ($_SERVER['HTTP_HOST'] ?? 'unknown')));
        $fingerprint = trim((string)($payload['installation_fingerprint'] ?? '*'));
        $maxObservedUtc = (int)($payload['max_observed_utc'] ?? 0);
        $clientIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $serverUtc = time();

        if (empty($licenseId)) {
            respond(false, null, 'license_id parameter is required for heartbeat.', 400);
        }

        // Query License Status
        $stmt = $pdo->prepare('SELECT * FROM `portal_licenses` WHERE `license_id` = :id LIMIT 1');
        $stmt->execute([':id' => $licenseId]);
        $lic = $stmt->fetch();

        if (!$lic) {
            respond(false, ['status' => 'REVOKED', 'reason' => 'License not found in Vendra Vault registry.'], 'License Revoked or Unknown', 403);
        }

        // Check Revocation or Suspension
        if ($lic['status'] === 'REVOKED') {
            respond(false, ['status' => 'REVOKED', 'reason' => 'License has been permanently revoked by vendor.'], 'License Revoked', 403);
        }
        if ($lic['status'] === 'SUSPENDED') {
            respond(false, ['status' => 'SUSPENDED', 'reason' => 'License temporarily suspended pending subscription renewal.'], 'License Suspended', 403);
        }

        // Check Server UTC Expiration (Client cannot tamper with this)
        if ($serverUtc > (int)$lic['expires_at']) {
            respond(false, [
                'status'     => 'EXPIRED',
                'expires_at' => (int)$lic['expires_at'],
                'server_utc' => $serverUtc,
                'reason'     => 'Commercial license expired on ' . date('Y-m-d H:i:s', (int)$lic['expires_at']) . ' UTC.',
            ], 'License Expired', 403);
        }

        // Domain Authorization Check
        $allowedDomains = json_decode($lic['allowed_domains'] ?? '[]', true) ?: [];
        $domainMatches = in_array('*', $allowedDomains, true);
        if (!$domainMatches) {
            foreach ($allowedDomains as $d) {
                if ($d === $clientDomain || str_ends_with($clientDomain, '.' . $d)) {
                    $domainMatches = true;
                    break;
                }
            }
        }

        if (!$domainMatches) {
            respond(false, [
                'status'         => 'DOMAIN_MISMATCH',
                'client_domain'  => $clientDomain,
                'allowed'        => $allowedDomains,
            ], "Unauthorized domain: {$clientDomain} is not licensed for this key.", 403);
        }

        // Issue Signed Lease Token (Ed25519)
        $graceHours = max(24, (int)$lic['offline_grace_hours']);
        $leaseExpiresAt = min((int)$lic['expires_at'], $serverUtc + ($graceHours * 3600));

        $leasePayload = [
            'license_id'       => $licenseId,
            'client_domain'    => $clientDomain,
            'fingerprint'      => $fingerprint,
            'lease_issued_at'  => $serverUtc,
            'lease_expires_at' => $leaseExpiresAt,
            'server_utc'       => $serverUtc,
        ];
        ksort($leasePayload);
        $canonicalLease = json_encode($leasePayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Sign with Vendor Secret Key
        $secKeyHex = $config['vendor_secret_key_hex'];
        $leaseSignatureHex = bin2hex(sodium_crypto_sign_detached($canonicalLease, hex2bin($secKeyHex)));

        // Record Heartbeat
        $stmtLog = $pdo->prepare('INSERT INTO `portal_heartbeats` (
            `license_id`, `domain`, `client_ip`, `client_timestamp`, `server_utc_time`, `installation_fingerprint`, `max_observed_utc`, `status`
        ) VALUES (
            :lid, :dom, :ip, :cts, :sutc, :fp, :mobs, "OK"
        )');
        $stmtLog->execute([
            ':lid'  => $licenseId,
            ':dom'  => $clientDomain,
            ':ip'   => $clientIp,
            ':cts'  => (int)($payload['client_timestamp'] ?? $serverUtc),
            ':sutc' => $serverUtc,
            ':fp'   => $fingerprint,
            ':mobs' => $maxObservedUtc,
        ]);

        respond(true, [
            'status'           => 'ACTIVE',
            'lease_token'      => base64_encode(json_encode([
                'payload'   => $leasePayload,
                'signature' => $leaseSignatureHex,
            ])),
            'lease_expires_at' => $leaseExpiresAt,
            'server_utc_time'  => $serverUtc,
            'days_remaining'   => round(($lic['expires_at'] - $serverUtc) / 86400, 1),
            'features'         => json_decode($lic['features'] ?? '[]', true) ?: [],
            'max_terminals'    => (int)$lic['max_terminals'],
        ], 'Heartbeat verified successfully. Signed lease token issued.');
        break;

    // ==========================================
    // 3. VENDOR ADMIN LOGIN
    // ==========================================
    case 'admin.login':
        $pass = (string)($payload['password'] ?? '');
        $expected = $config['admin_password'];

        if ($pass !== $expected && !password_verify($pass, $expected)) {
            respond(false, null, 'Invalid vendor master credentials.', 401);
        }

        // Return token
        $adminToken = base64_encode(json_encode([
            'role' => 'VENDOR_MASTER',
            'time' => time(),
            'hash' => hash_hmac('sha256', (string)time(), $config['security_salt']),
        ]));

        respond(true, [
            'token'     => $adminToken,
            'role'      => 'VENDOR_MASTER',
            'public_key'=> $config['vendor_public_key_hex'],
        ], 'Vendor Admin authenticated.');
        break;

    // ==========================================
    // 4. VENDOR ADMIN: GENERATE SIGNED LICENSE KEY
    // ==========================================
    case 'license.generate':
        $customerName = trim((string)($payload['customer_name'] ?? 'Client Store'));
        $daysValid = max(1, (int)($payload['days_valid'] ?? 365));
        $allowedDomains = $payload['allowed_domains'] ?? ['*'];
        if (is_string($allowedDomains)) {
            $allowedDomains = array_filter(array_map('trim', explode(',', $allowedDomains)));
        }
        if (empty($allowedDomains)) $allowedDomains = ['*'];

        $planTier = strtoupper(trim((string)($payload['plan_tier'] ?? 'BUSINESS_PRO')));
        $maxTerminals = max(1, (int)($payload['max_terminals'] ?? 5));
        $features = $payload['features'] ?? ['SUPERMARKET', 'RESTAURANT', 'RETAIL', 'PHARMACY'];
        $fingerprint = trim((string)($payload['installation_fingerprint'] ?? '*'));

        $licenseId = 'VND-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $now = time();
        $expiresAt = $now + ($daysValid * 86400);

        $cert = [
            'product'                  => 'VENDRA_POS',
            'issuer'                   => 'vendra.charafeddine.xyz',
            'license_id'               => $licenseId,
            'customer_name'            => $customerName,
            'allowed_domains'          => $allowedDomains,
            'installation_fingerprint' => $fingerprint,
            'plan_tier'                => $planTier,
            'max_terminals'            => $maxTerminals,
            'features'                 => $features,
            'issued_at'                => $now,
            'expires_at'               => $expiresAt,
            'offline_grace_hours'      => 72,
        ];

        ksort($cert);
        $canonicalJson = json_encode($cert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Sign with Vendor Master Ed25519 Secret Key
        $secKeyHex = $config['vendor_secret_key_hex'];
        $sigHex = bin2hex(sodium_crypto_sign_detached($canonicalJson, hex2bin($secKeyHex)));

        $licensePackage = [
            'certificate' => $cert,
            'signature'   => $sigHex,
        ];
        $licenseKeyBase64 = base64_encode(json_encode($licensePackage, JSON_PRETTY_PRINT));

        // Save to portal_licenses
        $stmt = $pdo->prepare('INSERT INTO `portal_licenses` (
            `license_id`, `customer_name`, `allowed_domains`, `installation_fingerprint`,
            `plan_tier`, `max_terminals`, `features`, `status`, `issued_at`, `expires_at`,
            `license_key_data`, `signature_hex`
        ) VALUES (
            :lid, :cname, :doms, :fp, :tier, :terms, :feats, "ACTIVE", :iat, :exp, :kdata, :sig
        )');
        $stmt->execute([
            ':lid'   => $licenseId,
            ':cname' => $customerName,
            ':doms'  => json_encode($allowedDomains),
            ':fp'    => $fingerprint,
            ':tier'  => $planTier,
            ':terms' => $maxTerminals,
            ':feats' => json_encode($features),
            ':iat'   => $now,
            ':exp'   => $expiresAt,
            ':kdata' => json_encode($cert),
            ':sig'   => $sigHex,
        ]);

        respond(true, [
            'license_id'         => $licenseId,
            'customer_name'      => $customerName,
            'expires_at'         => $expiresAt,
            'license_package'    => $licensePackage,
            'license_key_base64' => $licenseKeyBase64,
        ], 'Ed25519 Asymmetric Commercial License Certificate Issued.');
        break;

    // ==========================================
    // 5. VENDOR ADMIN: LIST LICENSES & LEADS
    // ==========================================
    case 'license.list':
        $stmt = $pdo->query('SELECT `id`, `license_id`, `customer_name`, `plan_tier`, `max_terminals`, `status`, `issued_at`, `expires_at`, `installation_fingerprint` FROM `portal_licenses` ORDER BY `id` DESC');
        $licenses = $stmt->fetchAll();
        $now = time();
        foreach ($licenses as &$l) {
            $l['days_remaining'] = max(0, round(($l['expires_at'] - $now) / 86400, 1));
        }
        respond(true, ['licenses' => $licenses]);
        break;

    case 'license.revoke':
        $id = trim((string)($payload['license_id'] ?? ''));
        $stmt = $pdo->prepare('UPDATE `portal_licenses` SET `status` = "REVOKED" WHERE `license_id` = :id');
        $stmt->execute([':id' => $id]);
        respond(true, null, "License {$id} successfully revoked.");
        break;

    case 'license.extend':
        $id = trim((string)($payload['license_id'] ?? ''));
        $days = (int)($payload['days'] ?? 30);
        $stmt = $pdo->prepare('SELECT * FROM `portal_licenses` WHERE `license_id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) respond(false, null, 'License not found', 404);

        $newExpiry = max(time(), (int)$row['expires_at']) + ($days * 86400);

        // Re-sign with new expiry
        $cert = json_decode($row['license_key_data'] ?? '{}', true) ?: [];
        $cert['expires_at'] = $newExpiry;
        ksort($cert);
        $canonicalJson = json_encode($cert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $secKeyHex = $config['vendor_secret_key_hex'];
        $sigHex = bin2hex(sodium_crypto_sign_detached($canonicalJson, hex2bin($secKeyHex)));

        $stmtUp = $pdo->prepare('UPDATE `portal_licenses` SET `expires_at` = :exp, `status` = "ACTIVE", `license_key_data` = :kdata, `signature_hex` = :sig WHERE `license_id` = :id');
        $stmtUp->execute([
            ':exp'   => $newExpiry,
            ':kdata' => json_encode($cert),
            ':sig'   => $sigHex,
            ':id'    => $id,
        ]);

        respond(true, [
            'license_id' => $id,
            'new_expiry' => $newExpiry,
            'days_added' => $days,
        ], "License extended by {$days} days.");
        break;

    case 'leads.list':
        $stmt = $pdo->query('SELECT * FROM `portal_leads` ORDER BY `id` DESC LIMIT 100');
        respond(true, ['leads' => $stmt->fetchAll()]);
        break;

    case 'heartbeats.list':
        $stmt = $pdo->query('SELECT * FROM `portal_heartbeats` ORDER BY `id` DESC LIMIT 50');
        respond(true, ['heartbeats' => $stmt->fetchAll()]);
        break;

    default:
        respond(false, null, 'Unknown action: ' . htmlspecialchars($action), 400);
}
