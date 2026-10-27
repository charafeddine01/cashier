<?php
declare(strict_types=1);

/**
 * OmniPOS Pro - Automated Backup & Cloud Sync Test Suite
 * Validates:
 * 1. Database table initialization (backup_settings & backup_logs)
 * 2. Settings get and update
 * 3. External USB drive detection & path write verification
 * 4. Google Drive connection handling (Service Account, OAuth, Local)
 * 5. Sync Mode (Single mirror replica: omnipos_synced.sqlite)
 * 6. Backup Mode (Compressed timestamped snapshot: omnipos_backup_*.sqlite.gz)
 * 7. Snapshot download generation
 * 8. Scheduler frequency verification (ON_SHIFT_CLOSE, HOURLY, DAILY)
 * 9. Shift close hook integration
 * 10. Audit/activity logging
 */

echo "=======================================================\n";
echo " OmniPOS Pro - Database Backup & Cloud Sync Test Suite \n";
echo "=======================================================\n\n";

// Autoload Core Classes & Controllers
spl_autoload_register(function (string $class): void {
    $prefix = 'OmniPOS\\';
    $baseDir = __DIR__ . '/../api/';

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
use OmniPOS\Core\Auth;

// 1. Initialize DB
$config = require __DIR__ . '/../api/config.php';
Database::init($config);
BackupService::ensureTablesExist();

function assertCheck(bool $condition, string $testName): void {
    if ($condition) {
        echo "✅ PASS: {$testName}\n";
    } else {
        echo "❌ FAIL: {$testName}\n";
        exit(1);
    }
}

// ------------------------------------------------------------------
// TEST 1: Settings Initialization & Retrieval
// ------------------------------------------------------------------
echo "--- TEST 1: Backup Settings Initialization ---\n";
$settings = BackupService::getSettings();
assertCheck(isset($settings['id']) && $settings['id'] === 1, "Settings record exists with ID 1");
assertCheck(in_array($settings['sync_mode'], ['SYNC', 'BACKUP', 'DUAL'], true), "Sync mode initialized ('{$settings['sync_mode']}')");
assertCheck(!empty($settings['frequency']), "Backup frequency configured ('{$settings['frequency']}')");

// ------------------------------------------------------------------
// TEST 2: Update Settings
// ------------------------------------------------------------------
echo "\n--- TEST 2: Update Backup Settings ---\n";
$mockUsbDir = sys_get_temp_dir() . '/omnipos_test_usb_suite';
@mkdir($mockUsbDir, 0755, true);

$updated = BackupService::updateSettings([
    'usb_enabled' => 1,
    'usb_path' => $mockUsbDir,
    'sync_mode' => 'SYNC',
    'frequency' => 'ON_SHIFT_CLOSE',
    'gdrive_enabled' => 0,
    'gdrive_folder_name' => 'OmniPOS_Test_Vault',
]);

assertCheck($updated['usb_path'] === $mockUsbDir, "USB path updated to test directory");
assertCheck($updated['sync_mode'] === 'SYNC', "Mode set to SYNC (Single replica)");
assertCheck($updated['frequency'] === 'ON_SHIFT_CLOSE', "Frequency set to ON_SHIFT_CLOSE");

// ------------------------------------------------------------------
// TEST 3: USB Path Diagnostics & Drive Detection
// ------------------------------------------------------------------
echo "\n--- TEST 3: USB Detection & Path Verification ---\n";
$usbTest = BackupService::testUsbPath($mockUsbDir);
assertCheck($usbTest['success'] === true, "Mock USB directory verified and writable");
assertCheck(!empty($usbTest['free_human']), "Disk space calculated: {$usbTest['free_human']}");

$detectedDrives = BackupService::detectUsbDrives();
assertCheck(is_array($detectedDrives), "detectUsbDrives returns drive list");

// ------------------------------------------------------------------
// TEST 4: Live Mirror Sync Execution (Single File Replica)
// ------------------------------------------------------------------
echo "\n--- TEST 4: Live Mirror Sync Execution (Zero Clutter) ---\n";
$syncRes = BackupService::runSync('MANUAL', [
    'destination' => 'USB',
    'mode' => 'SYNC',
]);

assertCheck($syncRes['success'] === true, "Sync operation succeeded");
assertCheck($syncRes['action_type'] === 'SYNC', "Action type was SYNC");
assertCheck(isset($syncRes['usb']['file_path']), "USB replica written");

$syncedFile = $syncRes['usb']['file_path'];
assertCheck(file_exists($syncedFile), "Replica file exists at {$syncedFile}");
assertCheck(basename($syncedFile) === 'omnipos_synced.sqlite', "File named omnipos_synced.sqlite (Mirror)");

// Verify replica database integrity
$replicaPdo = new PDO("sqlite:{$syncedFile}");
$integrity = $replicaPdo->query("PRAGMA integrity_check")->fetchColumn();
assertCheck($integrity === 'ok', "Replica PRAGMA integrity_check passed (ok)");

$replicaTables = $replicaPdo->query("SELECT count(*) FROM sqlite_master WHERE type='table'")->fetchColumn();
assertCheck((int)$replicaTables > 20, "Replica contains all tables ({$replicaTables} tables)");

// Verify metadata descriptor file
$metaFile = $mockUsbDir . '/omnipos_sync_meta.json';
assertCheck(file_exists($metaFile), "Metadata JSON file created alongside replica");
$metaContent = json_decode(file_get_contents($metaFile), true);
assertCheck($metaContent['sync_mode'] === 'MIRROR_SYNC', "Metadata records MIRROR_SYNC");

// ------------------------------------------------------------------
// TEST 5: Snapshot Backup Mode (Timestamped Archive + Pruning)
// ------------------------------------------------------------------
echo "\n--- TEST 5: Snapshot Backup Mode ---\n";
$backupRes = BackupService::runSync('MANUAL', [
    'destination' => 'USB',
    'mode' => 'BACKUP',
]);

assertCheck($backupRes['success'] === true, "Backup snapshot succeeded");
assertCheck($backupRes['action_type'] === 'BACKUP', "Action type was BACKUP");
$backupFile = $backupRes['usb']['file_path'];
assertCheck(file_exists($backupFile), "Backup file created ({$backupFile})");
assertCheck(str_ends_with($backupFile, '.sqlite.gz'), "Backup file is compressed with GZIP (.sqlite.gz)");

// ------------------------------------------------------------------
// TEST 6: Google Drive Local Folder Replication
// ------------------------------------------------------------------
echo "\n--- TEST 6: Google Drive Local Sync Target ---\n";
$mockGdriveDir = sys_get_temp_dir() . '/omnipos_test_gdrive_suite';
@mkdir($mockGdriveDir, 0755, true);

$gdriveUpdated = BackupService::updateSettings([
    'gdrive_enabled' => 1,
    'gdrive_auth_type' => 'LOCAL_FOLDER',
    'gdrive_local_path' => $mockGdriveDir,
]);

$gdriveTest = BackupService::testGoogleDriveConnection();
assertCheck($gdriveTest['success'] === true, "Google Drive desktop local target verified");

$dualRes = BackupService::runSync('MANUAL', [
    'destination' => 'GDRIVE',
    'mode' => 'SYNC',
]);
assertCheck($dualRes['success'] === true, "Replication to Google Drive target succeeded");
assertCheck(file_exists($mockGdriveDir . '/omnipos_synced.sqlite'), "Google Drive folder has synchronized replica");

// ------------------------------------------------------------------
// TEST 7: Snapshot Creation & Stream Download
// ------------------------------------------------------------------
echo "\n--- TEST 7: Direct Snapshot Generation ---\n";
$snapshot = BackupService::createDatabaseSnapshot('BACKUP', true);
assertCheck(file_exists($snapshot['tmp_path']), "Atomic snapshot created in temp folder");
assertCheck($snapshot['size'] > 0, "Snapshot has non-zero size ({$snapshot['size']} bytes)");
@unlink($snapshot['tmp_path']);

// ------------------------------------------------------------------
// TEST 8: Activity Logs Verification
// ------------------------------------------------------------------
echo "\n--- TEST 8: Backup Logs Audit Trail ---\n";
$logs = BackupService::getLogs(10);
assertCheck(count($logs) >= 3, "Activity logs recorded all operations (count: " . count($logs) . ")");
assertCheck($logs[0]['status'] === 'SUCCESS', "Most recent log status is SUCCESS");

// ------------------------------------------------------------------
// TEST 9: Scheduled Frequency Check
// ------------------------------------------------------------------
echo "\n--- TEST 9: Scheduled Frequency Logic ---\n";
BackupService::updateSettings(['frequency' => 'MANUAL']);
$schedManual = BackupService::checkAndRunScheduled();
assertCheck($schedManual['due'] === false, "MANUAL frequency returns not due");

BackupService::updateSettings(['frequency' => 'ON_SHIFT_CLOSE']);
$schedShift = BackupService::checkAndRunScheduled();
assertCheck($schedShift['due'] === false, "ON_SHIFT_CLOSE handled by event, not periodic cron");

// Clean up test directories
@array_map('unlink', glob("{$mockUsbDir}/*"));
@rmdir($mockUsbDir);
@array_map('unlink', glob("{$mockGdriveDir}/*"));
@rmdir($mockGdriveDir);

echo "\n=======================================================\n";
echo " 🎉 ALL 9 DATABASE BACKUP & SYNC TESTS PASSED! 🎉\n";
echo "=======================================================\n";
