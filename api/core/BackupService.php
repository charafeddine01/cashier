<?php
declare(strict_types=1);

namespace OmniPOS\Core;

use PDO;
use RuntimeException;

class BackupService
{
    private static bool $tablesChecked = false;

    /**
     * Ensure backup_settings and backup_logs tables exist and default record is initialized.
     */
    public static function ensureTablesExist(): void
    {
        if (self::$tablesChecked) {
            return;
        }

        $pdo = Database::getInstance();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec('CREATE TABLE IF NOT EXISTS `backup_settings` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `sync_mode` TEXT NOT NULL DEFAULT "SYNC",
                `frequency` TEXT NOT NULL DEFAULT "ON_SHIFT_CLOSE",
                `daily_time` TEXT NOT NULL DEFAULT "23:00",
                `keep_snapshots_count` INTEGER NOT NULL DEFAULT 7,
                `compress_backups` INTEGER NOT NULL DEFAULT 1,
                `usb_enabled` INTEGER NOT NULL DEFAULT 1,
                `usb_path` TEXT NOT NULL DEFAULT "/media/usb/omnipos_backups",
                `last_usb_sync_at` TEXT NULL,
                `last_usb_sync_status` TEXT NULL DEFAULT "PENDING",
                `last_usb_sync_message` TEXT NULL,
                `last_usb_sync_size` INTEGER NOT NULL DEFAULT 0,
                `gdrive_enabled` INTEGER NOT NULL DEFAULT 0,
                `gdrive_auth_type` TEXT NOT NULL DEFAULT "SERVICE_ACCOUNT",
                `gdrive_folder_id` TEXT NULL DEFAULT "",
                `gdrive_folder_name` TEXT NOT NULL DEFAULT "OmniPOS_Cloud_Sync",
                `gdrive_client_email` TEXT NULL,
                `gdrive_private_key` TEXT NULL,
                `gdrive_refresh_token` TEXT NULL,
                `gdrive_client_id` TEXT NULL,
                `gdrive_client_secret` TEXT NULL,
                `gdrive_access_token` TEXT NULL,
                `gdrive_token_expires_at` INTEGER NOT NULL DEFAULT 0,
                `gdrive_synced_file_id` TEXT NULL,
                `gdrive_local_path` TEXT NULL,
                `last_gdrive_sync_at` TEXT NULL,
                `last_gdrive_sync_status` TEXT NULL DEFAULT "PENDING",
                `last_gdrive_sync_message` TEXT NULL,
                `last_gdrive_sync_size` INTEGER NOT NULL DEFAULT 0,
                `last_sync_at` TEXT NULL,
                `last_sync_hash` TEXT NULL,
                `updated_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );');

            $pdo->exec('CREATE TABLE IF NOT EXISTS `backup_logs` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `trigger_type` TEXT NOT NULL DEFAULT "MANUAL",
                `action_type` TEXT NOT NULL DEFAULT "SYNC",
                `destination` TEXT NOT NULL DEFAULT "ALL",
                `status` TEXT NOT NULL DEFAULT "SUCCESS",
                `file_name` TEXT NULL,
                `file_size_bytes` INTEGER NOT NULL DEFAULT 0,
                `duration_ms` INTEGER NOT NULL DEFAULT 0,
                `details` TEXT NULL,
                `created_at` TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );');
        } else {
            // MySQL
            $pdo->exec('CREATE TABLE IF NOT EXISTS `backup_settings` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `sync_mode` VARCHAR(32) NOT NULL DEFAULT "SYNC",
                `frequency` VARCHAR(32) NOT NULL DEFAULT "ON_SHIFT_CLOSE",
                `daily_time` VARCHAR(8) NOT NULL DEFAULT "23:00",
                `keep_snapshots_count` INT UNSIGNED NOT NULL DEFAULT 7,
                `compress_backups` TINYINT(1) NOT NULL DEFAULT 1,
                `usb_enabled` TINYINT(1) NOT NULL DEFAULT 1,
                `usb_path` VARCHAR(255) NOT NULL DEFAULT "/media/usb/omnipos_backups",
                `last_usb_sync_at` DATETIME NULL,
                `last_usb_sync_status` VARCHAR(32) NULL DEFAULT "PENDING",
                `last_usb_sync_message` TEXT NULL,
                `last_usb_sync_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `gdrive_enabled` TINYINT(1) NOT NULL DEFAULT 0,
                `gdrive_auth_type` VARCHAR(32) NOT NULL DEFAULT "SERVICE_ACCOUNT",
                `gdrive_folder_id` VARCHAR(128) NULL DEFAULT "",
                `gdrive_folder_name` VARCHAR(128) NOT NULL DEFAULT "OmniPOS_Cloud_Sync",
                `gdrive_client_email` VARCHAR(255) NULL,
                `gdrive_private_key` TEXT NULL,
                `gdrive_refresh_token` TEXT NULL,
                `gdrive_client_id` VARCHAR(255) NULL,
                `gdrive_client_secret` VARCHAR(255) NULL,
                `gdrive_access_token` TEXT NULL,
                `gdrive_token_expires_at` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `gdrive_synced_file_id` VARCHAR(128) NULL,
                `gdrive_local_path` VARCHAR(255) NULL,
                `last_gdrive_sync_at` DATETIME NULL,
                `last_gdrive_sync_status` VARCHAR(32) NULL DEFAULT "PENDING",
                `last_gdrive_sync_message` TEXT NULL,
                `last_gdrive_sync_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `last_sync_at` DATETIME NULL,
                `last_sync_hash` VARCHAR(64) NULL,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');

            $pdo->exec('CREATE TABLE IF NOT EXISTS `backup_logs` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `trigger_type` VARCHAR(32) NOT NULL DEFAULT "MANUAL",
                `action_type` VARCHAR(32) NOT NULL DEFAULT "SYNC",
                `destination` VARCHAR(32) NOT NULL DEFAULT "ALL",
                `status` VARCHAR(32) NOT NULL DEFAULT "SUCCESS",
                `file_name` VARCHAR(255) NULL,
                `file_size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
                `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
                `details` TEXT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
        }

        // Ensure row id = 1 exists
        $stmt = $pdo->query('SELECT COUNT(*) FROM `backup_settings` WHERE `id` = 1');
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec('INSERT INTO `backup_settings` (`id`, `sync_mode`, `frequency`, `usb_enabled`, `usb_path`, `gdrive_enabled`)
                VALUES (1, "SYNC", "ON_SHIFT_CLOSE", 1, "/media/usb/omnipos_backups", 0)');
        }

        self::$tablesChecked = true;
    }

    /**
     * Get current backup settings and system state.
     */
    public static function getSettings(): array
    {
        self::ensureTablesExist();
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT * FROM `backup_settings` WHERE `id` = 1 LIMIT 1');
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$settings) {
            $settings = [
                'id' => 1,
                'sync_mode' => 'SYNC',
                'frequency' => 'ON_SHIFT_CLOSE',
                'daily_time' => '23:00',
                'keep_snapshots_count' => 7,
                'compress_backups' => 1,
                'usb_enabled' => 1,
                'usb_path' => '/media/usb/omnipos_backups',
                'gdrive_enabled' => 0,
                'gdrive_auth_type' => 'SERVICE_ACCOUNT',
                'gdrive_folder_name' => 'OmniPOS_Cloud_Sync',
            ];
        }

        // Mask sensitive private keys and secrets in responses
        $sanitized = $settings;
        if (!empty($sanitized['gdrive_private_key'])) {
            $sanitized['has_gdrive_private_key'] = true;
            $sanitized['gdrive_private_key'] = '******** [CONFIGURED] ********';
        } else {
            $sanitized['has_gdrive_private_key'] = false;
        }

        if (!empty($sanitized['gdrive_client_secret'])) {
            $sanitized['has_gdrive_client_secret'] = true;
            $sanitized['gdrive_client_secret'] = '******** [CONFIGURED] ********';
        } else {
            $sanitized['has_gdrive_client_secret'] = false;
        }

        if (!empty($sanitized['gdrive_refresh_token'])) {
            $sanitized['has_gdrive_refresh_token'] = true;
            $sanitized['gdrive_refresh_token'] = '******** [CONFIGURED] ********';
        } else {
            $sanitized['has_gdrive_refresh_token'] = false;
        }

        return $sanitized;
    }

    /**
     * Get raw settings including secrets for internal execution.
     */
    public static function getRawSettings(): array
    {
        self::ensureTablesExist();
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT * FROM `backup_settings` WHERE `id` = 1 LIMIT 1');
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Update backup settings.
     */
    public static function updateSettings(array $payload): array
    {
        self::ensureTablesExist();
        $pdo = Database::getInstance();

        $allowedFields = [
            'sync_mode', 'frequency', 'daily_time', 'keep_snapshots_count',
            'compress_backups', 'usb_enabled', 'usb_path', 'gdrive_enabled',
            'gdrive_auth_type', 'gdrive_folder_id', 'gdrive_folder_name',
            'gdrive_client_email', 'gdrive_private_key', 'gdrive_refresh_token',
            'gdrive_client_id', 'gdrive_client_secret', 'gdrive_access_token',
            'gdrive_local_path'
        ];

        $updates = [];
        $params = [':id' => 1];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $payload)) {
                $val = $payload[$field];
                // Do not overwrite secrets if dummy masked string was passed back
                if (in_array($field, ['gdrive_private_key', 'gdrive_client_secret', 'gdrive_refresh_token'], true) && is_string($val) && str_contains($val, '********')) {
                    continue;
                }
                $updates[] = "`{$field}` = :{$field}";
                $params[":{$field}"] = $val;
            }
        }

        if (!empty($updates)) {
            $sql = 'UPDATE `backup_settings` SET ' . implode(', ', $updates) . ', `updated_at` = CURRENT_TIMESTAMP WHERE `id` = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
        }

        return self::getSettings();
    }

    /**
     * Detect connected external USB / removable drives.
     */
    public static function detectUsbDrives(): array
    {
        $drives = [];

        if (PHP_OS_FAMILY === 'Windows') {
            // Windows drive letters check
            foreach (range('D', 'Z') as $letter) {
                $drivePath = "{$letter}:\\";
                if (is_dir($drivePath)) {
                    $free = @disk_free_space($drivePath);
                    $total = @disk_total_space($drivePath);
                    $isWritable = @is_writable($drivePath);
                    $drives[] = [
                        'path' => $drivePath,
                        'name' => "Removable Drive ({$letter}:)",
                        'free_bytes' => $free !== false ? $free : 0,
                        'total_bytes' => $total !== false ? $total : 0,
                        'is_writable' => $isWritable,
                        'suggested_backup_dir' => "{$drivePath}OmniPOS_Backups",
                    ];
                }
            }
        } else {
            // Linux / Unix detection
            $candidates = [];

            // 1. Check /media and subdirectories
            if (is_dir('/media')) {
                $subdirs = glob('/media/*/*', GLOB_ONLYDIR) ?: [];
                $rootMedia = glob('/media/*', GLOB_ONLYDIR) ?: [];
                $candidates = array_merge($candidates, $subdirs, $rootMedia);
            }

            // 2. Check /run/media
            if (is_dir('/run/media')) {
                $runDirs = glob('/run/media/*/*', GLOB_ONLYDIR) ?: [];
                $candidates = array_merge($candidates, $runDirs);
            }

            // 3. Check /mnt
            if (is_dir('/mnt')) {
                $mntDirs = glob('/mnt/*', GLOB_ONLYDIR) ?: [];
                $candidates = array_merge($candidates, $mntDirs, ['/mnt']);
            }

            // 4. Check /proc/mounts for removable filesystems (vfat, ntfs, exfat, fuseblk)
            if (is_readable('/proc/mounts')) {
                $lines = file('/proc/mounts', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                foreach ($lines as $line) {
                    $parts = preg_split('/\s+/', $line);
                    if (count($parts) >= 3) {
                        $mountPoint = $parts[1];
                        $fsType = $parts[2];
                        if (in_array($fsType, ['vfat', 'exfat', 'ntfs', 'fuseblk', 'msdos'], true) ||
                            str_starts_with($mountPoint, '/media') ||
                            str_starts_with($mountPoint, '/run/media')) {
                            $candidates[] = $mountPoint;
                        }
                    }
                }
            }

            $uniqueCandidates = array_unique(array_filter($candidates, 'is_dir'));
            foreach ($uniqueCandidates as $path) {
                // Skip system virtual mounts and boot partitions
                if (in_array($path, ['/dev', '/proc', '/sys', '/run', '/boot', '/boot/efi', '/etc', '/var'], true) ||
                    str_starts_with($path, '/boot') ||
                    str_starts_with($path, '/sys') ||
                    str_starts_with($path, '/proc')) {
                    continue;
                }
                $free = @disk_free_space($path);
                $total = @disk_total_space($path);
                $isWritable = @is_writable($path);
                $basename = basename($path);

                $drives[] = [
                    'path' => $path,
                    'name' => "Drive: {$basename} (" . ($isWritable ? 'Writable' : 'Read-Only') . ")",
                    'free_bytes' => (float)($free !== false ? $free : 0),
                    'total_bytes' => (float)($total !== false ? $total : 0),
                    'free_human' => self::formatBytes((float)($free !== false ? $free : 0)),
                    'is_writable' => $isWritable,
                    'suggested_backup_dir' => rtrim($path, '/') . '/omnipos_backups',
                ];
            }
        }

        return $drives;
    }

    /**
     * Test write and read permissions on specified USB path.
     */
    public static function testUsbPath(string $path): array
    {
        $path = trim($path);
        if (empty($path)) {
            return [
                'success' => false,
                'message' => 'USB directory path cannot be empty.',
            ];
        }

        // Try creating directory if not present
        if (!is_dir($path)) {
            if (!@mkdir($path, 0755, true) && !is_dir($path)) {
                return [
                    'success' => false,
                    'message' => "Target directory '{$path}' does not exist and could not be created. Check USB mount and permissions.",
                ];
            }
        }

        if (!is_writable($path)) {
            return [
                'success' => false,
                'message' => "Directory '{$path}' is not writable. Please verify USB drive write lock or file system permissions.",
            ];
        }

        // Write a test validation token
        $testFile = rtrim($path, '/') . '/.omnipos_write_test_' . time() . '.tmp';
        $token = 'OMNIPOS_USB_VERIFY_' . bin2hex(random_bytes(8));

        $bytes = @file_put_contents($testFile, $token);
        if ($bytes === false) {
            return [
                'success' => false,
                'message' => "Failed to write test file to '{$path}'.",
            ];
        }

        $readBack = @file_get_contents($testFile);
        @unlink($testFile);

        if ($readBack !== $token) {
            return [
                'success' => false,
                'message' => 'Read verification failed after test write.',
            ];
        }

        $freeBytes = @disk_free_space($path) ?: 0;
        $totalBytes = @disk_total_space($path) ?: 0;

        return [
            'success' => true,
            'message' => "USB directory '{$path}' is verified and ready for database sync.",
            'free_bytes' => $freeBytes,
            'total_bytes' => $totalBytes,
            'free_human' => self::formatBytes($freeBytes),
        ];
    }

    /**
     * Get Google OAuth2 access token based on configured auth type.
     */
    private static function getGoogleAccessToken(array $settings): string
    {
        $authType = $settings['gdrive_auth_type'] ?? 'SERVICE_ACCOUNT';
        $now = time();

        // Check if existing access token is still valid (with 60s safety buffer)
        $cachedToken = $settings['gdrive_access_token'] ?? '';
        $expiresAt = (int)($settings['gdrive_token_expires_at'] ?? 0);
        if (!empty($cachedToken) && $expiresAt > ($now + 60)) {
            return $cachedToken;
        }

        if ($authType === 'SERVICE_ACCOUNT') {
            $clientEmail = trim((string)($settings['gdrive_client_email'] ?? ''));
            $privateKey = trim((string)($settings['gdrive_private_key'] ?? ''));

            if (empty($clientEmail) || empty($privateKey)) {
                throw new RuntimeException('Google Drive Service Account Email and Private Key are required.');
            }

            // Fix escaped newlines if passed from json/env string
            $privateKey = str_replace('\n', "\n", $privateKey);

            $jwtHeader = self::base64UrlEncode((string)json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $jwtClaim = self::base64UrlEncode((string)json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/drive.file https://www.googleapis.com/auth/drive',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ]));

            $signInput = "{$jwtHeader}.{$jwtClaim}";
            $signature = '';
            $pkeyResource = openssl_pkey_get_private($privateKey);
            if (!$pkeyResource) {
                throw new RuntimeException('Invalid Google Service Account RSA Private Key.');
            }

            if (!openssl_sign($signInput, $signature, $pkeyResource, OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Failed to sign Google OAuth2 assertion token with RSA private key.');
            }

            $jwtSignature = self::base64UrlEncode($signature);
            $assertion = "{$signInput}.{$jwtSignature}";

            $postData = http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);

            $res = self::curlRequest('https://oauth2.googleapis.com/token', 'POST', [
                'Content-Type: application/x-www-form-urlencoded',
            ], $postData);

            $tokenData = json_decode($res['body'], true);
            if (!isset($tokenData['access_token'])) {
                $err = $tokenData['error_description'] ?? ($tokenData['error'] ?? 'Unknown OAuth failure');
                throw new RuntimeException("Google Service Account Token Error: {$err}");
            }

            $accessToken = $tokenData['access_token'];
            $newExpiry = $now + (int)($tokenData['expires_in'] ?? 3600);

            // Cache token
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare('UPDATE `backup_settings` SET `gdrive_access_token` = :tk, `gdrive_token_expires_at` = :exp WHERE `id` = 1');
            $stmt->execute([':tk' => $accessToken, ':exp' => $newExpiry]);

            return $accessToken;
        }

        if ($authType === 'OAUTH_TOKEN') {
            $clientId = trim((string)($settings['gdrive_client_id'] ?? ''));
            $clientSecret = trim((string)($settings['gdrive_client_secret'] ?? ''));
            $refreshToken = trim((string)($settings['gdrive_refresh_token'] ?? ''));

            if (empty($clientId) || empty($clientSecret) || empty($refreshToken)) {
                throw new RuntimeException('Google Drive Client ID, Client Secret, and Refresh Token are required for OAuth2.');
            }

            $postData = http_build_query([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
            ]);

            $res = self::curlRequest('https://oauth2.googleapis.com/token', 'POST', [
                'Content-Type: application/x-www-form-urlencoded',
            ], $postData);

            $tokenData = json_decode($res['body'], true);
            if (!isset($tokenData['access_token'])) {
                $err = $tokenData['error_description'] ?? ($tokenData['error'] ?? 'OAuth token refresh failed');
                throw new RuntimeException("Google OAuth2 Refresh Error: {$err}");
            }

            $accessToken = $tokenData['access_token'];
            $newExpiry = $now + (int)($tokenData['expires_in'] ?? 3600);

            $pdo = Database::getInstance();
            $stmt = $pdo->prepare('UPDATE `backup_settings` SET `gdrive_access_token` = :tk, `gdrive_token_expires_at` = :exp WHERE `id` = 1');
            $stmt->execute([':tk' => $accessToken, ':exp' => $newExpiry]);

            return $accessToken;
        }

        throw new RuntimeException("Unsupported Google Drive auth type '{$authType}'.");
    }

    /**
     * Test Google Drive connection and permissions.
     */
    public static function testGoogleDriveConnection(?array $customSettings = null): array
    {
        $settings = $customSettings ?: self::getRawSettings();
        $authType = $settings['gdrive_auth_type'] ?? 'SERVICE_ACCOUNT';

        // Local desktop client mode
        if ($authType === 'LOCAL_FOLDER') {
            $localPath = trim((string)($settings['gdrive_local_path'] ?? ''));
            if (empty($localPath)) {
                return ['success' => false, 'message' => 'Local Google Drive folder path is empty.'];
            }
            return self::testUsbPath($localPath);
        }

        try {
            $token = self::getGoogleAccessToken($settings);

            // Ping Google Drive API to verify token and read quota
            $aboutRes = self::curlRequest(
                'https://www.googleapis.com/drive/v3/about?fields=user,storageQuota',
                'GET',
                ["Authorization: Bearer {$token}"]
            );

            if ($aboutRes['status'] !== 200) {
                return [
                    'success' => false,
                    'message' => 'Failed to query Google Drive API: HTTP ' . $aboutRes['status'] . ' ' . $aboutRes['body'],
                ];
            }

            $aboutData = json_decode($aboutRes['body'], true);
            $userEmail = $aboutData['user']['emailAddress'] ?? 'Google Service Account';
            $quota = $aboutData['storageQuota'] ?? [];
            $limit = isset($quota['limit']) ? (int)$quota['limit'] : 0;
            $usage = isset($quota['usage']) ? (int)$quota['usage'] : 0;

            // Ensure destination folder exists or can be created
            $folderId = self::ensureGoogleDriveFolder($token, $settings);

            return [
                'success' => true,
                'message' => "Successfully connected to Google Drive account ({$userEmail}).",
                'account_email' => $userEmail,
                'folder_id' => $folderId,
                'folder_name' => $settings['gdrive_folder_name'] ?? 'OmniPOS_Cloud_Sync',
                'storage_used' => self::formatBytes($usage),
                'storage_limit' => $limit > 0 ? self::formatBytes($limit) : 'Unlimited / Shared',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Google Drive connection error: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Ensure Google Drive backup folder exists, returning folder ID.
     */
    private static function ensureGoogleDriveFolder(string $token, array $settings): string
    {
        // If explicit folder ID already provided, verify it exists
        $configuredFolderId = trim((string)($settings['gdrive_folder_id'] ?? ''));
        if (!empty($configuredFolderId)) {
            $checkRes = self::curlRequest(
                "https://www.googleapis.com/drive/v3/files/{$configuredFolderId}?fields=id,name,trashed",
                'GET',
                ["Authorization: Bearer {$token}"]
            );
            if ($checkRes['status'] === 200) {
                $fData = json_decode($checkRes['body'], true);
                if (empty($fData['trashed'])) {
                    return $configuredFolderId;
                }
            }
        }

        // Search for existing folder by name
        $folderName = trim((string)($settings['gdrive_folder_name'] ?? 'OmniPOS_Cloud_Sync'));
        if (empty($folderName)) {
            $folderName = 'OmniPOS_Cloud_Sync';
        }

        $query = urlencode("name = '{$folderName}' and mimeType = 'application/vnd.google-apps.folder' and trashed = false");
        $searchRes = self::curlRequest(
            "https://www.googleapis.com/drive/v3/files?q={$query}&fields=files(id,name)",
            'GET',
            ["Authorization: Bearer {$token}"]
        );

        if ($searchRes['status'] === 200) {
            $searchData = json_decode($searchRes['body'], true);
            if (!empty($searchData['files'][0]['id'])) {
                $foundId = $searchData['files'][0]['id'];
                // Save found ID
                $pdo = Database::getInstance();
                $pdo->prepare('UPDATE `backup_settings` SET `gdrive_folder_id` = :fid WHERE `id` = 1')
                    ->execute([':fid' => $foundId]);
                return $foundId;
            }
        }

        // Create folder
        $createRes = self::curlRequest(
            'https://www.googleapis.com/drive/v3/files?fields=id,name',
            'POST',
            [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json; charset=UTF-8',
            ],
            (string)json_encode([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
            ])
        );

        if ($createRes['status'] !== 200) {
            throw new RuntimeException("Failed to create Google Drive folder: HTTP {$createRes['status']} {$createRes['body']}");
        }

        $createdData = json_decode($createRes['body'], true);
        $newFolderId = $createdData['id'];

        $pdo = Database::getInstance();
        $pdo->prepare('UPDATE `backup_settings` SET `gdrive_folder_id` = :fid WHERE `id` = 1')
            ->execute([':fid' => $newFolderId]);

        return $newFolderId;
    }

    /**
     * Generate an atomic, consistent database snapshot in a temporary file.
     */
    public static function createDatabaseSnapshot(string $actionType, bool $compress): array
    {
        $pdo = Database::getInstance();
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $timestamp = date('Ymd_His');

        $tmpDir = sys_get_temp_dir() . '/omnipos_backups';
        if (!is_dir($tmpDir)) {
            @mkdir($tmpDir, 0755, true);
        }

        if ($driver === 'sqlite') {
            // Safe online atomic snapshot using SQLite VACUUM INTO
            $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE);');

            $tempSnapshot = $tmpDir . '/omnipos_atomic_' . bin2hex(random_bytes(6)) . '.sqlite';
            @unlink($tempSnapshot);

            $stmt = $pdo->prepare('VACUUM INTO :target');
            $stmt->execute([':target' => $tempSnapshot]);

            if (!file_exists($tempSnapshot) || filesize($tempSnapshot) === 0) {
                throw new RuntimeException('SQLite VACUUM INTO snapshot failed to generate database image.');
            }

            $sourcePath = $tempSnapshot;
            $extension = 'sqlite';

            // Optional compression for snapshot backups
            if ($actionType === 'BACKUP' && $compress) {
                $compressedPath = $tempSnapshot . '.gz';
                $data = file_get_contents($tempSnapshot);
                $gzData = gzencode($data, 9);
                file_put_contents($compressedPath, $gzData);
                @unlink($tempSnapshot);
                $sourcePath = $compressedPath;
                $extension = 'sqlite.gz';
            }

            $size = filesize($sourcePath);
            $sha256 = hash_file('sha256', $sourcePath);

            return [
                'tmp_path' => $sourcePath,
                'extension' => $extension,
                'size' => $size,
                'sha256' => $sha256,
                'timestamp' => $timestamp,
            ];
        } else {
            // MySQL / MariaDB Dump generator
            $tempSql = $tmpDir . '/omnipos_dump_' . bin2hex(random_bytes(6)) . '.sql';
            $fh = fopen($tempSql, 'wb');
            if (!$fh) {
                throw new RuntimeException('Failed to create temporary SQL dump file.');
            }

            fwrite($fh, "-- OmniPOS Pro Production Database Backup\n");
            fwrite($fh, "-- Generated At: " . date('Y-m-d H:i:s') . "\n");
            fwrite($fh, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

            // Fetch all tables
            $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $tbl) {
                // Table schema
                $createSql = $pdo->query("SHOW CREATE TABLE `{$tbl}`")->fetch(PDO::FETCH_ASSOC)['Create Table'] ?? '';
                fwrite($fh, "DROP TABLE IF EXISTS `{$tbl}`;\n");
                fwrite($fh, "{$createSql};\n\n");

                // Table rows
                $rows = $pdo->query("SELECT * FROM `{$tbl}`")->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $keys = array_map(fn($k) => "`{$k}`", array_keys($row));
                    $vals = array_map(function ($v) use ($pdo) {
                        return $v === null ? 'NULL' : $pdo->quote((string)$v);
                    }, array_values($row));
                    fwrite($fh, "INSERT INTO `{$tbl}` (" . implode(',', $keys) . ") VALUES (" . implode(',', $vals) . ");\n");
                }
                fwrite($fh, "\n");
            }
            fwrite($fh, "SET FOREIGN_KEY_CHECKS = 1;\n");
            fclose($fh);

            $sourcePath = $tempSql;
            $extension = 'sql';

            if ($actionType === 'BACKUP' && $compress) {
                $compressedPath = $tempSql . '.gz';
                $data = file_get_contents($tempSql);
                $gzData = gzencode($data, 9);
                file_put_contents($compressedPath, $gzData);
                @unlink($tempSql);
                $sourcePath = $compressedPath;
                $extension = 'sql.gz';
            }

            $size = filesize($sourcePath);
            $sha256 = hash_file('sha256', $sourcePath);

            return [
                'tmp_path' => $sourcePath,
                'extension' => $extension,
                'size' => $size,
                'sha256' => $sha256,
                'timestamp' => $timestamp,
            ];
        }
    }

    /**
     * Synchronize or copy database snapshot to external USB target.
     */
    public static function syncToUsb(string $sourcePath, array $snapshotInfo, array $settings, string $actionType): array
    {
        $usbPath = trim((string)($settings['usb_path'] ?? ''));
        if (empty($usbPath)) {
            return ['success' => false, 'message' => 'USB target directory path is empty.'];
        }

        if (!is_dir($usbPath) && !@mkdir($usbPath, 0755, true)) {
            return ['success' => false, 'message' => "Target USB path '{$usbPath}' is not accessible or mounted."];
        }

        if (!is_writable($usbPath)) {
            return ['success' => false, 'message' => "Target USB path '{$usbPath}' is read-only."];
        }

        $ext = $snapshotInfo['extension'];
        $cleanExt = str_starts_with($ext, 'sqlite') ? 'sqlite' : 'sql';

        if ($actionType === 'SYNC') {
            // Live Mirror Sync Mode: 1 up-to-date replica file on USB without endless archives
            $targetFinalFile = rtrim($usbPath, '/') . "/omnipos_synced.{$cleanExt}";
            $targetTempFile = rtrim($usbPath, '/') . "/.omnipos_synced.tmp";
            $targetPrevFile = rtrim($usbPath, '/') . "/omnipos_synced.prev.{$cleanExt}";

            // Write to atomic temp file first
            if (!copy($sourcePath, $targetTempFile)) {
                return ['success' => false, 'message' => "Failed writing mirror sync file to USB."];
            }

            // If an older replica existed, keep 1 previous fail-safe backup
            if (file_exists($targetFinalFile)) {
                @copy($targetFinalFile, $targetPrevFile);
            }

            // Atomic rename
            if (!rename($targetTempFile, $targetFinalFile)) {
                return ['success' => false, 'message' => "Failed finalizing mirror replica on USB."];
            }

            // Write sync metadata descriptor
            $metaFile = rtrim($usbPath, '/') . '/omnipos_sync_meta.json';
            $metaData = [
                'application' => 'OmniPOS Pro',
                'sync_mode' => 'MIRROR_SYNC',
                'synced_at' => date('Y-m-d H:i:s'),
                'sha256' => $snapshotInfo['sha256'],
                'file_size' => $snapshotInfo['size'],
                'file_name' => basename($targetFinalFile),
            ];
            @file_put_contents($metaFile, json_encode($metaData, JSON_PRETTY_PRINT));

            return [
                'success' => true,
                'mode' => 'SYNC',
                'file_path' => $targetFinalFile,
                'file_size' => $snapshotInfo['size'],
                'message' => "Database synchronized successfully to USB (1 clean mirror replica: omnipos_synced.{$cleanExt}).",
            ];
        } else {
            // Versioned Snapshot Backup Mode
            $targetFile = rtrim($usbPath, '/') . "/omnipos_backup_{$snapshotInfo['timestamp']}.{$ext}";
            if (!copy($sourcePath, $targetFile)) {
                return ['success' => false, 'message' => "Failed writing backup snapshot to USB."];
            }

            // Prune snapshots exceeding retention count
            $keepCount = max(1, (int)($settings['keep_snapshots_count'] ?? 7));
            self::pruneLocalSnapshots($usbPath, $keepCount);

            return [
                'success' => true,
                'mode' => 'BACKUP',
                'file_path' => $targetFile,
                'file_size' => $snapshotInfo['size'],
                'message' => "Database backup snapshot saved to USB (" . basename($targetFile) . ").",
            ];
        }
    }

    /**
     * Synchronize or copy database snapshot to Google Drive target.
     */
    public static function syncToGoogleDrive(string $sourcePath, array $snapshotInfo, array $settings, string $actionType): array
    {
        $authType = $settings['gdrive_auth_type'] ?? 'SERVICE_ACCOUNT';

        // Local Google Drive Desktop Folder mode
        if ($authType === 'LOCAL_FOLDER') {
            $localPath = trim((string)($settings['gdrive_local_path'] ?? ''));
            $settings['usb_path'] = $localPath;
            $res = self::syncToUsb($sourcePath, $snapshotInfo, $settings, $actionType);
            if ($res['success']) {
                $res['message'] = str_replace('USB', 'Google Drive Desktop Folder', $res['message']);
            }
            return $res;
        }

        $token = self::getGoogleAccessToken($settings);
        $folderId = self::ensureGoogleDriveFolder($token, $settings);
        $ext = $snapshotInfo['extension'];
        $cleanExt = str_starts_with($ext, 'sqlite') ? 'sqlite' : 'sql';
        $fileBytes = file_get_contents($sourcePath);

        if ($actionType === 'SYNC') {
            // Live Mirror Sync Mode: updates the existing file in Google Drive!
            $syncedFileId = trim((string)($settings['gdrive_synced_file_id'] ?? ''));

            if (!empty($syncedFileId)) {
                // Try updating existing file in Google Drive
                $updateRes = self::curlRequest(
                    "https://www.googleapis.com/upload/drive/v3/files/{$syncedFileId}?uploadType=media",
                    'PATCH',
                    [
                        "Authorization: Bearer {$token}",
                        'Content-Type: application/octet-stream',
                    ],
                    $fileBytes
                );

                if ($updateRes['status'] === 200) {
                    return [
                        'success' => true,
                        'mode' => 'SYNC',
                        'file_id' => $syncedFileId,
                        'message' => "Database synchronized successfully to Google Drive (Updated existing cloud replica).",
                    ];
                }
            }

            // If file does not exist yet or update failed, create initial mirror file
            $createRes = self::uploadGoogleDriveMultipart(
                $token,
                "omnipos_synced.{$cleanExt}",
                $folderId,
                $fileBytes,
                'application/octet-stream'
            );

            if (!$createRes['success']) {
                return ['success' => false, 'message' => "Google Drive Sync failed: {$createRes['message']}"];
            }

            // Save new synced file ID
            $newFileId = $createRes['file_id'];
            $pdo = Database::getInstance();
            $pdo->prepare('UPDATE `backup_settings` SET `gdrive_synced_file_id` = :fid WHERE `id` = 1')
                ->execute([':fid' => $newFileId]);

            return [
                'success' => true,
                'mode' => 'SYNC',
                'file_id' => $newFileId,
                'message' => "Database synchronized successfully to Google Drive (Created cloud mirror: omnipos_synced.{$cleanExt}).",
            ];
        } else {
            // Versioned Snapshot Backup Mode
            $backupFilename = "omnipos_backup_{$snapshotInfo['timestamp']}.{$ext}";
            $uploadRes = self::uploadGoogleDriveMultipart(
                $token,
                $backupFilename,
                $folderId,
                $fileBytes,
                'application/octet-stream'
            );

            if (!$uploadRes['success']) {
                return ['success' => false, 'message' => "Google Drive Backup failed: {$uploadRes['message']}"];
            }

            return [
                'success' => true,
                'mode' => 'BACKUP',
                'file_id' => $uploadRes['file_id'],
                'message' => "Database snapshot saved to Google Drive ({$backupFilename}).",
            ];
        }
    }

    /**
     * Upload file using Google Drive REST API multipart upload.
     */
    private static function uploadGoogleDriveMultipart(
        string $token,
        string $filename,
        string $folderId,
        string $fileContent,
        string $mimeType
    ): array {
        $boundary = '-------OmniPOSBoundary' . bin2hex(random_bytes(8));
        $metadata = json_encode([
            'name' => $filename,
            'parents' => [$folderId],
        ]);

        $body = "--{$boundary}\r\n" .
                "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
                "{$metadata}\r\n" .
                "--{$boundary}\r\n" .
                "Content-Type: {$mimeType}\r\n\r\n" .
                "{$fileContent}\r\n" .
                "--{$boundary}--";

        $res = self::curlRequest(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart',
            'POST',
            [
                "Authorization: Bearer {$token}",
                "Content-Type: multipart/related; boundary={$boundary}",
                "Content-Length: " . strlen($body),
            ],
            $body
        );

        if ($res['status'] !== 200) {
            return [
                'success' => false,
                'message' => "Google Drive upload failed: HTTP {$res['status']} {$res['body']}",
            ];
        }

        $resData = json_decode($res['body'], true);
        return [
            'success' => true,
            'file_id' => $resData['id'] ?? '',
        ];
    }

    /**
     * Execute full backup / sync pipeline according to active settings.
     */
    public static function runSync(string $triggerType = 'MANUAL', array $options = []): array
    {
        $startTime = microtime(true);
        self::ensureTablesExist();
        $settings = self::getRawSettings();

        $actionType = $options['mode'] ?? ($settings['sync_mode'] ?? 'SYNC');
        $targetDestination = $options['destination'] ?? 'ALL'; // 'ALL', 'USB', 'GDRIVE'
        $compress = !empty($settings['compress_backups']);

        $usbEnabled = !empty($settings['usb_enabled']) && in_array($targetDestination, ['ALL', 'USB'], true);
        $gdriveEnabled = !empty($settings['gdrive_enabled']) && in_array($targetDestination, ['ALL', 'GDRIVE'], true);

        if (!$usbEnabled && !$gdriveEnabled) {
            return [
                'success' => false,
                'message' => 'No backup destinations are enabled. Please enable either External USB or Google Drive.',
            ];
        }

        // 1. Generate clean atomic database snapshot
        $snapshot = self::createDatabaseSnapshot($actionType, $compress);
        $sourcePath = $snapshot['tmp_path'];

        $usbResult = null;
        $gdriveResult = null;
        $now = date('Y-m-d H:i:s');
        $pdo = Database::getInstance();

        // 2. Sync to Destination 1: External USB
        if ($usbEnabled) {
            try {
                $usbResult = self::syncToUsb($sourcePath, $snapshot, $settings, $actionType);
                $statusStr = $usbResult['success'] ? 'SUCCESS' : 'FAILED';
                $pdo->prepare('UPDATE `backup_settings` SET
                    `last_usb_sync_at` = :now,
                    `last_usb_sync_status` = :st,
                    `last_usb_sync_message` = :msg,
                    `last_usb_sync_size` = :sz
                    WHERE `id` = 1
                ')->execute([
                    ':now' => $now,
                    ':st' => $statusStr,
                    ':msg' => $usbResult['message'],
                    ':sz' => $usbResult['success'] ? $snapshot['size'] : 0,
                ]);
            } catch (\Throwable $e) {
                $usbResult = ['success' => false, 'message' => 'USB sync exception: ' . $e->getMessage()];
                $pdo->prepare('UPDATE `backup_settings` SET
                    `last_usb_sync_at` = :now,
                    `last_usb_sync_status` = "FAILED",
                    `last_usb_sync_message` = :msg
                    WHERE `id` = 1
                ')->execute([':now' => $now, ':msg' => $e->getMessage()]);
            }
        }

        // 3. Sync to Destination 2: Google Drive
        if ($gdriveEnabled) {
            try {
                $gdriveResult = self::syncToGoogleDrive($sourcePath, $snapshot, $settings, $actionType);
                $statusStr = $gdriveResult['success'] ? 'SUCCESS' : 'FAILED';
                $pdo->prepare('UPDATE `backup_settings` SET
                    `last_gdrive_sync_at` = :now,
                    `last_gdrive_sync_status` = :st,
                    `last_gdrive_sync_message` = :msg,
                    `last_gdrive_sync_size` = :sz
                    WHERE `id` = 1
                ')->execute([
                    ':now' => $now,
                    ':st' => $statusStr,
                    ':msg' => $gdriveResult['message'],
                    ':sz' => $gdriveResult['success'] ? $snapshot['size'] : 0,
                ]);
            } catch (\Throwable $e) {
                $gdriveResult = ['success' => false, 'message' => 'Google Drive sync exception: ' . $e->getMessage()];
                $pdo->prepare('UPDATE `backup_settings` SET
                    `last_gdrive_sync_at` = :now,
                    `last_gdrive_sync_status` = "FAILED",
                    `last_gdrive_sync_message` = :msg
                    WHERE `id` = 1
                ')->execute([':now' => $now, ':msg' => $e->getMessage()]);
            }
        }

        // Clean up temporary snapshot file
        @unlink($sourcePath);

        $durationMs = (int)round((microtime(true) - $startTime) * 1000);

        // Overall status
        $successCount = ($usbResult['success'] ?? false ? 1 : 0) + ($gdriveResult['success'] ?? false ? 1 : 0);
        $totalTargets = ($usbEnabled ? 1 : 0) + ($gdriveEnabled ? 1 : 0);

        $overallStatus = ($successCount === $totalTargets) ? 'SUCCESS' : ($successCount > 0 ? 'PARTIAL' : 'FAILED');

        // Update last overall sync
        if ($overallStatus !== 'FAILED') {
            $pdo->prepare('UPDATE `backup_settings` SET
                `last_sync_at` = :now,
                `last_sync_hash` = :h
                WHERE `id` = 1
            ')->execute([':now' => $now, ':h' => $snapshot['sha256']]);
        }

        // Insert into backup_logs
        $logDetails = json_encode([
            'usb' => $usbResult,
            'gdrive' => $gdriveResult,
            'sha256' => $snapshot['sha256'],
        ]);

        $pdo->prepare('INSERT INTO `backup_logs`
            (`trigger_type`, `action_type`, `destination`, `status`, `file_name`, `file_size_bytes`, `duration_ms`, `details`)
            VALUES (:trig, :act, :dest, :st, :fn, :sz, :dur, :det)
        ')->execute([
            ':trig' => $triggerType,
            ':act' => $actionType,
            ':dest' => $targetDestination,
            ':st' => $overallStatus,
            ':fn' => "omnipos_{$actionType}.{$snapshot['extension']}",
            ':sz' => $snapshot['size'],
            ':dur' => $durationMs,
            ':det' => $logDetails,
        ]);

        return [
            'success' => $overallStatus !== 'FAILED',
            'status' => $overallStatus,
            'action_type' => $actionType,
            'duration_ms' => $durationMs,
            'file_size' => $snapshot['size'],
            'file_size_human' => self::formatBytes($snapshot['size']),
            'sha256' => $snapshot['sha256'],
            'usb' => $usbResult,
            'gdrive' => $gdriveResult,
            'timestamp' => $now,
        ];
    }

    /**
     * Check if a scheduled backup is due based on settings and run it.
     */
    public static function checkAndRunScheduled(): array
    {
        self::ensureTablesExist();
        $settings = self::getRawSettings();
        $freq = $settings['frequency'] ?? 'MANUAL';

        if ($freq === 'MANUAL' || $freq === 'ON_SHIFT_CLOSE') {
            return ['due' => false, 'reason' => "Frequency is {$freq}."];
        }

        $lastSyncStr = $settings['last_sync_at'] ?? null;
        $lastSync = $lastSyncStr ? strtotime($lastSyncStr) : 0;
        $now = time();
        $isDue = false;

        switch ($freq) {
            case 'EVERY_15_MIN':
                $isDue = ($now - $lastSync) >= 900;
                break;
            case 'EVERY_30_MIN':
                $isDue = ($now - $lastSync) >= 1800;
                break;
            case 'HOURLY':
                $isDue = ($now - $lastSync) >= 3600;
                break;
            case 'EVERY_6_HOURS':
                $isDue = ($now - $lastSync) >= 21600;
                break;
            case 'DAILY':
                $dailyTarget = strtotime(date('Y-m-d ') . ($settings['daily_time'] ?? '23:00') . ':00');
                $syncedToday = $lastSyncStr && (date('Y-m-d', $lastSync) === date('Y-m-d'));
                $isDue = !$syncedToday && ($now >= $dailyTarget);
                break;
        }

        if ($isDue) {
            $result = self::runSync('SCHEDULED');
            return ['due' => true, 'result' => $result];
        }

        return [
            'due' => false,
            'last_sync_at' => $lastSyncStr,
            'next_check' => 'Pending schedule interval',
        ];
    }

    /**
     * Trigger automatic sync on shift close (Z-Report).
     */
    public static function triggerShiftClose(): void
    {
        try {
            self::ensureTablesExist();
            $settings = self::getRawSettings();
            $freq = $settings['frequency'] ?? 'ON_SHIFT_CLOSE';

            // Run if frequency is set to ON_SHIFT_CLOSE or if USB / Google Drive are enabled
            if ($freq === 'ON_SHIFT_CLOSE' || !empty($settings['usb_enabled']) || !empty($settings['gdrive_enabled'])) {
                self::runSync('SHIFT_CLOSE');
            }
        } catch (\Throwable $e) {
            error_log('[OmniPOS Backup] Shift close sync error: ' . $e->getMessage());
        }
    }

    /**
     * Get recent backup logs.
     */
    public static function getLogs(int $limit = 20): array
    {
        self::ensureTablesExist();
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM `backup_logs` ORDER BY `id` DESC LIMIT :lim');
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as &$log) {
            $log['file_size_human'] = self::formatBytes((int)$log['file_size_bytes']);
            $log['details_parsed'] = !empty($log['details']) ? json_decode($log['details'], true) : null;
        }

        return $logs;
    }

    /**
     * Prune older snapshot files keeping only the newest $keepCount files.
     */
    private static function pruneLocalSnapshots(string $dir, int $keepCount): void
    {
        $files = glob(rtrim($dir, '/') . '/omnipos_backup_*.*');
        if (!$files || count($files) <= $keepCount) {
            return;
        }

        // Sort by modification time descending
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

        $toDelete = array_slice($files, $keepCount);
        foreach ($toDelete as $f) {
            @unlink($f);
        }
    }

    private static function formatBytes(float|int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int)floor(log((float)$bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / pow(1024, $i), 2) . ' ' . ($units[$i] ?? 'B');
    }

    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function curlRequest(string $url, string $method = 'GET', array $headers = [], ?string $postBody = null): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        if ($postBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("CURL Network Request Failed: {$error}");
        }

        return [
            'status' => $status,
            'body' => $response,
        ];
    }
}
