<?php
declare(strict_types=1);

/**
 * OmniPOS Pro - Background Database Backup & Cloud Sync Worker
 * Usage:
 *   php backup-sync.php          # Evaluates schedule and runs if due
 *   php backup-sync.php --force  # Forces immediate sync to all destinations
 */

// Autoload Core Classes & Controllers
spl_autoload_register(function (string $class): void {
    $prefix = 'OmniPOS\\';
    $baseDir = __DIR__ . '/../';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $parts = explode('\\', $relativeClass);
    $className = array_pop($parts);
    $dir = strtolower(implode('/', $parts));
    $file = $baseDir . ($dir ? $dir . '/' : '') . $className . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

use OmniPOS\Core\Database;
use OmniPOS\Core\BackupService;

// Load Config
$config = require __DIR__ . '/../config.php';
Database::init($config);

$isForce = in_array('--force', $argv ?? [], true);

echo "[" . date('Y-m-d H:i:s') . "] Starting OmniPOS Pro Backup Worker...\n";

try {
    BackupService::ensureTablesExist();

    if ($isForce) {
        echo "Forcing immediate database sync...\n";
        $result = BackupService::runSync('CLI');
        echo "Status: {$result['status']} | Duration: {$result['duration_ms']}ms | Size: {$result['file_size_human']}\n";
        if (isset($result['usb'])) {
            echo "USB: " . ($result['usb']['success'] ? 'SUCCESS' : 'FAILED: ' . $result['usb']['message']) . "\n";
        }
        if (isset($result['gdrive'])) {
            echo "Google Drive: " . ($result['gdrive']['success'] ? 'SUCCESS' : 'FAILED: ' . $result['gdrive']['message']) . "\n";
        }
    } else {
        $result = BackupService::checkAndRunScheduled();
        if ($result['due']) {
            echo "Scheduled backup was due and completed. Status: " . ($result['result']['status'] ?? 'DONE') . "\n";
        } else {
            $reason = $result['reason'] ?? 'Not elapsed';
            echo "No backup due at this time ({$reason}).\n";
        }
    }
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Completed successfully.\n";
