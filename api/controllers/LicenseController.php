<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Database;
use OmniPOS\Core\LicenseGuard;
use OmniPOS\Core\Response;

class LicenseController
{
    public static function getStatus(array $payload): void
    {
        $state = LicenseGuard::getLicenseState();
        if (!$state) {
            Response::success([
                'is_active' => false,
                'status' => 'UNLICENSED',
                'message' => 'No license installed',
                'installation_fingerprint' => LicenseGuard::getInstallationFingerprint(),
            ]);
        }

        $cert = json_decode($state['license_key_data'] ?? '{}', true) ?: [];
        $now = time();
        $expiresAt = (int)($cert['expires_at'] ?? 0);
        $daysRemaining = max(0, round(($expiresAt - $now) / 86400, 1));
        $isExpired = $now > $expiresAt;
        $isTampered = !empty($state['is_tampered']);

        Response::success([
            'is_active' => !$isExpired && !$isTampered,
            'status' => $isTampered ? 'TAMPERED' : ($isExpired ? 'EXPIRED' : 'ACTIVE'),
            'license_id' => $cert['license_id'] ?? '',
            'client_name' => $cert['client_name'] ?? '',
            'plan_tier' => $cert['plan_tier'] ?? 'STANDARD',
            'max_terminals' => $cert['max_terminals'] ?? 1,
            'enabled_modules' => $cert['enabled_modules'] ?? [],
            'allowed_domains' => $cert['allowed_domains'] ?? ['*'],
            'issued_at' => $cert['issued_at'] ?? 0,
            'expires_at' => $expiresAt,
            'days_remaining' => $daysRemaining,
            'is_tampered' => $isTampered,
            'tamper_reason' => $state['tamper_reason'] ?? null,
            'installation_fingerprint' => $state['installation_fingerprint'] ?? LicenseGuard::getInstallationFingerprint(),
            'server_time' => $now,
            'max_observed_utc' => (int)($state['max_observed_utc'] ?? 0),
        ]);
    }

    public static function activate(array $payload): void
    {
        $licenseKeyRaw = trim($payload['license_key'] ?? '');
        if (empty($licenseKeyRaw)) {
            Response::error('license_key is required.', 400);
        }

        $result = LicenseGuard::activateLicense($licenseKeyRaw);
        Response::success($result, 200, 'License successfully activated and verified.');
    }

    public static function heartbeat(array $payload): void
    {
        // Advance clock and verify license
        $cert = LicenseGuard::verifyOrAbort();
        $state = LicenseGuard::getLicenseState();

        Response::success([
            'status' => 'HEALTHY',
            'lease_token' => $state['lease_token'] ?? 'LEASE_OK',
            'lease_expires_at' => $state['lease_expires_at'] ?? 0,
            'server_time' => time(),
        ]);
    }
}
