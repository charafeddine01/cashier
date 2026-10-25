<?php
declare(strict_types=1);

/**
 * OmniPOS Pro - Production Configuration
 * Compatible with Shared Hosting (cPanel / Apache / LiteSpeed)
 */

$envFile = __DIR__ . '/config/env.php';
$customEnv = file_exists($envFile) ? (require $envFile) : [];
$dbCustom = $customEnv['db'] ?? [];

return [
    // Database Configuration (Prioritize env.php written by Web Installer, fallback to getenv or sqlite)
    'db' => [
        'driver'    => $dbCustom['driver'] ?? (getenv('DB_DRIVER') ?: 'sqlite'),
        'host'      => $dbCustom['host'] ?? (getenv('DB_HOST') ?: '127.0.0.1'),
        'port'      => (int)($dbCustom['port'] ?? (getenv('DB_PORT') ?: 3306)),
        'database'  => $dbCustom['database'] ?? (getenv('DB_NAME') ?: __DIR__ . '/../database/omnipos.sqlite'),
        'username'  => $dbCustom['username'] ?? (getenv('DB_USER') ?: 'omnipos_user'),
        'password'  => $dbCustom['password'] ?? (getenv('DB_PASS') ?: 'omnipos_secure_pass'),
        'charset'   => $dbCustom['charset'] ?? 'utf8mb4',
    ],

    // Security Salt for System Clock Integrity HMAC and Token Signing
    'security_salt' => $customEnv['security_salt'] ?? 'OMNIPOS_CORE_SALT_2026_SECURE',
    'jwt_secret'    => $customEnv['jwt_secret'] ?? 'OMNIPOS_JWT_SECRET_KEY_8899AABBCC_2026',
    'jwt_ttl_hours' => 24,

    // Vendor Asymmetric Licensing (Ed25519)
    'vendor_public_key_hex' => $customEnv['vendor_public_key_hex'] ?? '7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4',
    'central_license_url'   => $customEnv['central_license_url'] ?? 'https://licensing.omnipos-pro.com/api/heartbeat',

    // Uploads
    'upload_dir' => __DIR__ . '/../public/uploads',
    'upload_url_prefix' => '/uploads',

    // CORS Allowed Origins
    'cors_allowed_origins' => ['*'],
];
