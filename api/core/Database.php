<?php
declare(strict_types=1);

namespace OmniPOS\Core;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;
    private static array $config = [];

    public static function init(array $config): void
    {
        self::$config = $config;
    }

    public static function getInstance(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $dbConfig = self::$config['db'] ?? [
            'driver' => 'sqlite',
            'database' => __DIR__ . '/../../database/omnipos.sqlite'
        ];

        try {
            if (($dbConfig['driver'] ?? 'mysql') === 'sqlite') {
                $dbPath = $dbConfig['database'];
                $dbDir = dirname($dbPath);
                if (!is_dir($dbDir)) {
                    mkdir($dbDir, 0755, true);
                }
                $isNewDb = !file_exists($dbPath) || filesize($dbPath) === 0;

                $dsn = "sqlite:{$dbPath}";
                $pdo = new PDO($dsn, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec('PRAGMA foreign_keys = ON;');
                self::$instance = $pdo;

                if ($isNewDb) {
                    self::initializeSqliteDatabase($pdo);
                }
            } else {
                // MySQL / MariaDB for production shared hosting
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                    $dbConfig['host'],
                    $dbConfig['port'],
                    $dbConfig['database'],
                    $dbConfig['charset'] ?? 'utf8mb4'
                );

                $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);

                self::$instance = $pdo;
            }

            return self::$instance;
        } catch (PDOException $e) {
            // If MySQL failed and driver was mysql, we can log and fail gracefully
            throw new RuntimeException("Database connection failure: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    private static function initializeSqliteDatabase(PDO $pdo): void
    {
        $schemaFile = __DIR__ . '/../../database/schema.sql';
        $seedFile = __DIR__ . '/../../database/seed.sql';

        if (file_exists($schemaFile)) {
            $sql = file_get_contents($schemaFile);
            $sql = preg_replace('/ENGINE=\w+ DEFAULT CHARSET=\w+ COLLATE=[\w_]+/i', '', $sql);
            $sql = preg_replace('/SET FOREIGN_KEY_CHECKS\s*=\s*[01];/i', '', $sql);
            $sql = preg_replace('/INT UNSIGNED AUTO_INCREMENT PRIMARY KEY/i', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
            $sql = preg_replace('/AUTO_INCREMENT/i', '', $sql);
            $sql = preg_replace('/INT UNSIGNED/i', 'INTEGER', $sql);
            $sql = preg_replace('/BIGINT UNSIGNED/i', 'INTEGER', $sql);
            $sql = preg_replace('/TINYINT\(1\)/i', 'INTEGER', $sql);
            $sql = preg_replace('/MEDIUMTEXT/i', 'TEXT', $sql);
            $sql = preg_replace('/ENUM\([^)]+\)/i', 'TEXT', $sql);
            $sql = preg_replace('/JSON/i', 'TEXT', $sql);
            $sql = preg_replace('/ON UPDATE CURRENT_TIMESTAMP/i', '', $sql);
            $sql = preg_replace('/COMMENT\s+\'[^\']*\'/i', '', $sql);
            $sql = preg_replace('/,\s*INDEX\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);
            $sql = preg_replace('/,\s*UNIQUE\s+KEY\s+`[^`]+`\s*\([^)]+\)/i', '', $sql);

            $statements = array_filter(array_map('trim', explode(';', $sql)));
            foreach ($statements as $stmt) {
                if (!empty($stmt)) {
                    try {
                        $pdo->exec($stmt);
                    } catch (\Throwable $t) {
                        // Ignore individual index parse warnings
                    }
                }
            }
        }

        if (file_exists($seedFile)) {
            $seedSql = file_get_contents($seedFile);
            $seedSql = preg_replace('/SET FOREIGN_KEY_CHECKS\s*=\s*[01];/i', '', $seedSql);
            $seedSql = preg_replace('/ON DUPLICATE KEY UPDATE[^;]+/i', '', $seedSql);

            $statements = array_filter(array_map('trim', explode(';', $seedSql)));
            foreach ($statements as $stmt) {
                if (!empty($stmt)) {
                    try {
                        $pdo->exec($stmt);
                    } catch (\Throwable $t) {
                        // Ignore duplicate key warnings on seed
                    }
                }
            }
        }
    }

    public static function beginTransaction(): bool
    {
        $pdo = self::getInstance();
        return $pdo->inTransaction() ? true : $pdo->beginTransaction();
    }

    public static function commit(): bool
    {
        $pdo = self::getInstance();
        return $pdo->inTransaction() ? $pdo->commit() : true;
    }

    public static function rollBack(): bool
    {
        $pdo = self::getInstance();
        return $pdo->inTransaction() ? $pdo->rollBack() : true;
    }
}
