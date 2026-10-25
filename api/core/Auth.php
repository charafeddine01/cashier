<?php
declare(strict_types=1);

namespace OmniPOS\Core;

use PDO;

class Auth
{
    private static string $jwtSecret = 'OMNIPOS_JWT_SECRET_KEY_8899AABBCC_2026';
    private static int $ttlHours = 24;
    private static ?array $currentUser = null;

    public static function init(array $config): void
    {
        if (!empty($config['jwt_secret'])) {
            self::$jwtSecret = $config['jwt_secret'];
        }
        if (!empty($config['jwt_ttl_hours'])) {
            self::$ttlHours = (int)$config['jwt_ttl_hours'];
        }
    }

    public static function createToken(array $user): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'sub'       => $user['id'],
            'username'  => $user['username'],
            'full_name' => $user['full_name'],
            'role'      => $user['role'],
            'iat'       => $now,
            'exp'       => $now + (self::$ttlHours * 3600),
        ];

        $encodedHeader = self::base64UrlEncode(json_encode($header));
        $encodedPayload = self::base64UrlEncode(json_encode($payload));
        $signature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", self::$jwtSecret, true);
        $encodedSignature = self::base64UrlEncode($signature);

        return "{$encodedHeader}.{$encodedPayload}.{$encodedSignature}";
    }

    public static function verifyToken(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $expectedSignature = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", self::$jwtSecret, true);
        $providedSignature = self::base64UrlDecode($encodedSignature);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($encodedPayload), true);
        if (!$payload || !isset($payload['exp']) || time() > $payload['exp']) {
            return null;
        }

        return $payload;
    }

    public static function authenticateRequest(?string $token): array
    {
        if (empty($token)) {
            Response::error('Authentication required. Missing auth_token in request envelope.', 401, null, 'UNAUTHENTICATED');
        }

        $claims = self::verifyToken($token);
        if (!$claims) {
            Response::error('Invalid or expired authentication session. Please log in again.', 401, null, 'SESSION_EXPIRED');
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT `id`, `username`, `full_name`, `email`, `role`, `is_active` FROM `users` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $claims['sub']]);
        $user = $stmt->fetch();

        if (!$user || empty($user['is_active'])) {
            Response::error('User account is inactive or disabled.', 403, null, 'ACCOUNT_DISABLED');
        }

        self::$currentUser = $user;
        return $user;
    }

    public static function getCurrentUser(): ?array
    {
        return self::$currentUser;
    }

    public static function requireRole(array $allowedRoles): array
    {
        if (!self::$currentUser) {
            Response::error('Authentication required.', 401, null, 'UNAUTHENTICATED');
        }

        $userRole = strtoupper(self::$currentUser['role'] ?? '');
        $allowed = array_map('strtoupper', $allowedRoles);

        if (!in_array($userRole, $allowed, true)) {
            Response::error("Access denied. Required role: " . implode('/', $allowedRoles), 403, null, 'FORBIDDEN');
        }

        return self::$currentUser;
    }

    public static function can(string $permission): bool
    {
        if (!self::$currentUser) {
            return false;
        }
        $role = strtoupper(self::$currentUser['role']);
        if ($role === 'ADMIN') {
            return true;
        }
        if ($role === 'MANAGER') {
            return in_array($permission, [
                'pos.sell', 'pos.discount', 'pos.override_price', 'pos.refund',
                'shifts.manage', 'inventory.manage', 'reports.view', 'customers.manage'
            ], true);
        }
        if ($role === 'CASHIER') {
            return in_array($permission, [
                'pos.sell', 'shifts.own', 'customers.view', 'customers.create'
            ], true);
        }
        if ($role === 'WAITER') {
            return in_array($permission, [
                'pos.sell', 'restaurant.tables', 'restaurant.orders'
            ], true);
        }
        if ($role === 'KITCHEN') {
            return in_array($permission, [
                'kds.view', 'kds.update'
            ], true);
        }
        return false;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
