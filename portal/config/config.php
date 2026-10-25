<?php
declare(strict_types=1);

/**
 * Vendra Portal & Central License Authority Configuration
 * Compatible with hosting.com / cPanel shared hosting
 */

$envFile = __DIR__ . '/env.php';
$customEnv = file_exists($envFile) ? (require $envFile) : [];
$dbCustom = $customEnv['db'] ?? [];

return [
    'db' => [
        'driver'   => $dbCustom['driver'] ?? (getenv('PORTAL_DB_DRIVER') ?: 'sqlite'),
        'host'     => $dbCustom['host'] ?? (getenv('PORTAL_DB_HOST') ?: '127.0.0.1'),
        'port'     => (int)($dbCustom['port'] ?? (getenv('PORTAL_DB_PORT') ?: 3306)),
        'database' => $dbCustom['database'] ?? (getenv('PORTAL_DB_NAME') ?: __DIR__ . '/../database/vendra_portal.sqlite'),
        'username' => $dbCustom['username'] ?? (getenv('PORTAL_DB_USER') ?: 'vendra_user'),
        'password' => $dbCustom['password'] ?? (getenv('PORTAL_DB_PASS') ?: 'vendra_pass'),
        'charset'  => 'utf8mb4',
    ],
    'security_salt' => $customEnv['security_salt'] ?? 'VENDRA_VAULT_CENTRAL_SALT_2026',
    'jwt_secret'    => $customEnv['jwt_secret'] ?? 'VENDRA_VAULT_JWT_SECRET_KEY_998811',
    'admin_password' => $customEnv['admin_password'] ?? 'VendorMasterPassword2026!',
    'vendor_public_key_hex' => $customEnv['vendor_public_key_hex'] ?? '7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4',
    'vendor_secret_key_hex' => $customEnv['vendor_secret_key_hex'] ?? 'b3106c9011e167d15a84ae3f12857256be7d4efc357cedfbb7d0604bb322415e7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4',
];
