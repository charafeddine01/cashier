<?php
declare(strict_types=1);

namespace OmniPOS\Core;

use PDO;
use RuntimeException;

class LicenseGuard
{
    private static ?array $cachedLicense = null;
    private static string $vendorPublicKeyHex = '7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4';
    private static string $securitySalt = 'OMNIPOS_CORE_SALT_2026_SECURE';
    private static string $centralLicenseUrl = 'https://vendra.charafeddine.xyz/license-api/index.php';

    public static function init(array $config): void
    {
        if (!empty($config['vendor_public_key_hex'])) {
            self::$vendorPublicKeyHex = $config['vendor_public_key_hex'];
        }
        if (!empty($config['security_salt'])) {
            self::$securitySalt = $config['security_salt'];
        }
        if (!empty($config['central_license_url'])) {
            self::$centralLicenseUrl = $config['central_license_url'];
        }
    }

    /**
     * Compute installation fingerprint from server and DB parameters
     */
    public static function getInstallationFingerprint(): string
    {
        $serverInfo = php_uname('s') . '-' . php_uname('n') . '-' . ($_SERVER['SERVER_ADDR'] ?? '127.0.0.1');
        return hash('sha256', $serverInfo . self::$securitySalt);
    }

    /**
     * Verify license and enforce monotonic anti-rollback clock protection
     */
    public static function verifyOrAbort(): array
    {
        $licenseState = self::getLicenseState();
        if (!$licenseState) {
            Response::error('No valid commercial license installed. Please activate your OmniPOS Pro license.', 403, null, 'LICENSE_NOT_FOUND');
        }

        // 1. Check if previously flagged as tampered
        if (!empty($licenseState['is_tampered'])) {
            Response::error(
                'Security Alert: POS locked due to ' . ($licenseState['tamper_reason'] ?? 'license tampering') . '. Re-activate license to resume.',
                403,
                ['reason' => $licenseState['tamper_reason']],
                'LICENSE_TAMPERED'
            );
        }

        // 2. Monotonic Anti-Rollback Clock Protection
        $now = time();
        $maxObserved = (int)($licenseState['max_observed_utc'] ?? 0);
        $expectedHmac = hash_hmac('sha256', (string)$maxObserved, self::$securitySalt);

        // Verify integrity of stored max_observed_utc
        if (!hash_equals($expectedHmac, (string)($licenseState['clock_hmac'] ?? ''))) {
            self::markTampered('Database clock signature mismatch (HMAC integrity failure)');
            Response::error('Security Alert: Monotonic clock integrity failure. License state corrupted.', 403, null, 'CLOCK_HMAC_INVALID');
        }

        // Check if server clock was rolled backwards by more than 5 minutes (300 seconds)
        if ($now < ($maxObserved - 300)) {
            $reason = sprintf('CLOCK_TAMPER_DETECTED: Server time (%d) is behind max observed time (%d)', $now, $maxObserved);
            self::markTampered($reason);
            Response::error('Security Alert: System clock rollback detected! POS locked for license integrity.', 403, [
                'current_time' => $now,
                'max_observed_time' => $maxObserved,
            ], 'CLOCK_TAMPER_DETECTED');
        }

        // Clock is valid. If time has advanced, update max_observed_utc
        if ($now > $maxObserved) {
            self::advanceMonotonicClock($now);
        }

        // 3. Asymmetric Ed25519 Certificate Verification
        $certJson = $licenseState['license_key_data'];
        $signatureHex = $licenseState['signature_hex'];

        $cert = json_decode($certJson, true);
        if (!$cert || !is_array($cert)) {
            Response::error('Corrupted license key data.', 403, null, 'LICENSE_INVALID');
        }

        // Canonical sorted JSON for signature check
        ksort($cert);
        $canonicalJson = json_encode($cert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!self::verifyEd25519($canonicalJson, $signatureHex, self::$vendorPublicKeyHex)) {
            self::markTampered('Ed25519 Cryptographic Signature Verification Failed');
            Response::error('Security Alert: Invalid or forged Ed25519 digital signature on license.', 403, null, 'LICENSE_SIGNATURE_INVALID');
        }

        // 4. Verify Expiration Time
        $expiresAt = (int)($cert['expires_at'] ?? 0);
        if ($now > $expiresAt) {
            Response::error(
                'OmniPOS Pro License Expired on ' . date('Y-m-d H:i:s T', $expiresAt) . '. Please renew your commercial license.',
                403,
                [
                    'expired_at' => $expiresAt,
                    'current_time' => $now,
                    'license_id' => $cert['license_id'] ?? 'UNKNOWN'
                ],
                'LICENSE_EXPIRED'
            );
        }

        // 5. Verify Domain Binding (unless wildcard '*')
        $allowedDomains = $cert['allowed_domains'] ?? ['*'];
        if (!in_array('*', $allowedDomains, true)) {
            $host = strtolower($_SERVER['HTTP_HOST'] ?? 'localhost');
            // Strip port if present
            $host = explode(':', $host)[0];
            $isDomainAllowed = in_array($host, $allowedDomains, true) || in_array('localhost', $allowedDomains, true);
            if (!$isDomainAllowed) {
                Response::error("Current domain '{$host}' is not authorized by this OmniPOS Pro license key.", 403, [
                    'allowed_domains' => $allowedDomains,
                    'current_host' => $host
                ], 'DOMAIN_UNAUTHORIZED');
            }
        }

        self::$cachedLicense = $cert;
        return $cert;
    }

    public static function assertModule(string $module): void
    {
        $cert = self::$cachedLicense ?? self::verifyOrAbort();
        $enabledModules = $cert['enabled_modules'] ?? [];
        if (!in_array('*', $enabledModules, true) && !in_array(strtoupper($module), $enabledModules, true)) {
            Response::error("Module '{$module}' is not enabled in your OmniPOS Pro '{$cert['plan_tier']}' plan.", 403, [
                'required_module' => $module,
                'enabled_modules' => $enabledModules,
                'plan_tier' => $cert['plan_tier'] ?? 'STANDARD',
            ], 'MODULE_NOT_LICENSED');
        }
    }

    public static function verifyEd25519(string $canonicalMessage, string $signatureHex, string $publicKeyHex): bool
    {
        try {
            $sigBytes = hex2bin($signatureHex);
            $pubBytes = hex2bin($publicKeyHex);
            if ($sigBytes === false || $pubBytes === false || strlen($pubBytes) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                return false;
            }
            return sodium_crypto_sign_verify_detached($sigBytes, $canonicalMessage, $pubBytes);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function getLicenseState(): ?array
    {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->query('SELECT * FROM `system_license_state` ORDER BY `id` ASC LIMIT 1');
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function advanceMonotonicClock(int $timestamp): void
    {
        try {
            $hmac = hash_hmac('sha256', (string)$timestamp, self::$securitySalt);
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare('UPDATE `system_license_state` SET `max_observed_utc` = :ts, `clock_hmac` = :hmac, `last_verified_at` = :lv WHERE `id` = 1');
            $stmt->execute([
                ':ts' => $timestamp,
                ':hmac' => $hmac,
                ':lv' => $timestamp,
            ]);
        } catch (\Throwable $e) {
            // Log quietly
        }
    }

    public static function markTampered(string $reason): void
    {
        try {
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare('UPDATE `system_license_state` SET `is_tampered` = 1, `tamper_reason` = :reason WHERE `id` = 1');
            $stmt->execute([':reason' => $reason]);
        } catch (\Throwable $e) {
            // Log quietly
        }
    }

    public static function activateLicense(string $licenseKeyRaw): array
    {
        // Decode license raw string (base64 package or plain json)
        $raw = trim($licenseKeyRaw);
        $decoded = base64_decode($raw, true);
        if ($decoded !== false && (str_starts_with($decoded, '{') || str_starts_with($decoded, '['))) {
            $raw = $decoded;
        }

        $package = json_decode($raw, true);
        if (!$package || !isset($package['certificate'], $package['signature'])) {
            Response::error('Invalid license file structure. Missing certificate or signature.', 400, null, 'INVALID_LICENSE_FORMAT');
        }

        $cert = $package['certificate'];
        $signatureHex = $package['signature'];

        ksort($cert);
        $canonicalJson = json_encode($cert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (!self::verifyEd25519($canonicalJson, $signatureHex, self::$vendorPublicKeyHex)) {
            Response::error('License verification failed: cryptographic signature does not match Vendor Master Key.', 400, null, 'INVALID_SIGNATURE');
        }

        $now = time();
        if ($now > (int)($cert['expires_at'] ?? 0)) {
            Response::error('Cannot activate an expired license.', 400, null, 'LICENSE_EXPIRED');
        }

        $hmac = hash_hmac('sha256', (string)$now, self::$securitySalt);
        $fingerprint = self::getInstallationFingerprint();

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE `system_license_state` SET
            `license_key_data` = :cert_json,
            `signature_hex` = :sig,
            `max_observed_utc` = :now_ts,
            `clock_hmac` = :hmac,
            `lease_token` = :lease,
            `lease_expires_at` = :lexp,
            `installation_fingerprint` = :fp,
            `last_verified_at` = :now_ts2,
            `is_tampered` = 0,
            `tamper_reason` = NULL
            WHERE `id` = 1
        ');
        $stmt->execute([
            ':cert_json' => json_encode($cert),
            ':sig' => $signatureHex,
            ':now_ts' => $now,
            ':hmac' => $hmac,
            ':lease' => 'ACTIVATED_LOCAL',
            ':lexp' => (int)($cert['expires_at'] ?? 0),
            ':fp' => $fingerprint,
            ':now_ts2' => $now,
        ]);

        return [
            'license_id' => $cert['license_id'] ?? '',
            'client_name' => $cert['client_name'] ?? '',
            'plan_tier' => $cert['plan_tier'] ?? '',
            'max_terminals' => $cert['max_terminals'] ?? 1,
            'enabled_modules' => $cert['enabled_modules'] ?? [],
            'expires_at' => $cert['expires_at'] ?? 0,
            'days_remaining' => round(($cert['expires_at'] - $now) / 86400, 1),
        ];
    }
}
