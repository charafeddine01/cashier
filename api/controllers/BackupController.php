<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\BackupService;
use OmniPOS\Core\Response;

class BackupController
{
    /**
     * Get backup settings, detected USB drives, and recent logs.
     */
    public static function getSettings(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);

        $settings = BackupService::getSettings();
        $usbDrives = BackupService::detectUsbDrives();
        $recentLogs = BackupService::getLogs(15);

        Response::success([
            'settings' => $settings,
            'detected_usb_drives' => $usbDrives,
            'recent_logs' => $recentLogs,
        ]);
    }

    /**
     * Update backup configuration.
     */
    public static function updateSettings(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);

        $updated = BackupService::updateSettings($payload);
        Response::success([
            'settings' => $updated,
        ], 200, 'Backup and sync configuration saved successfully.');
    }

    /**
     * Auto-detect connected USB drives.
     */
    public static function detectUsb(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);

        $drives = BackupService::detectUsbDrives();
        Response::success([
            'drives' => $drives,
        ]);
    }

    /**
     * Test write access to specified USB path.
     */
    public static function testUsb(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);

        $path = $payload['path'] ?? '';
        $res = BackupService::testUsbPath($path);

        if (!$res['success']) {
            Response::error($res['message'], 400, $res);
        }

        Response::success($res, 200, $res['message']);
    }

    /**
     * Test Google Drive connection and credentials.
     */
    public static function testGoogleDrive(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);

        $customSettings = !empty($payload['settings']) ? $payload['settings'] : null;
        $res = BackupService::testGoogleDriveConnection($customSettings);

        if (!$res['success']) {
            Response::error($res['message'], 400, $res);
        }

        Response::success($res, 200, $res['message']);
    }

    /**
     * Manually trigger database sync / backup.
     */
    public static function run(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);

        $destination = $payload['destination'] ?? 'ALL'; // 'ALL', 'USB', 'GDRIVE'
        $mode = $payload['mode'] ?? null; // 'SYNC', 'BACKUP'

        $result = BackupService::runSync('MANUAL', [
            'destination' => $destination,
            'mode' => $mode,
        ]);

        if (!$result['success']) {
            Response::error($result['message'] ?? 'Database backup/sync failed.', 500, $result);
        }

        Response::success($result, 200, 'Database synchronized successfully.');
    }

    /**
     * Check if a scheduled backup is due and run it if necessary.
     */
    public static function checkAndRunScheduled(array $payload): void
    {
        $result = BackupService::checkAndRunScheduled();
        Response::success($result);
    }

    /**
     * Retrieve recent backup history logs.
     */
    public static function getLogs(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);

        $limit = max(1, min(100, (int)($payload['limit'] ?? 20)));
        $logs = BackupService::getLogs($limit);
        Response::success(['logs' => $logs]);
    }

    /**
     * Direct database snapshot download for browser export.
     */
    public static function download(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);

        $compress = !empty($payload['compress']);
        $snapshot = BackupService::createDatabaseSnapshot('BACKUP', $compress);
        $content = file_get_contents($snapshot['tmp_path']);
        @unlink($snapshot['tmp_path']);

        $filename = "omnipos_export_" . date('Y-m-d_His') . ".{$snapshot['extension']}";

        Response::success([
            'filename' => $filename,
            'file_size' => $snapshot['size'],
            'base64' => base64_encode($content),
            'mime_type' => str_ends_with($snapshot['extension'], 'gz') ? 'application/gzip' : (str_starts_with($snapshot['extension'], 'sqlite') ? 'application/x-sqlite3' : 'application/sql'),
        ], 200, 'Snapshot generated successfully.');
    }
}
