-- Vendra Central License Authority & Showcase Portal Database Schema
-- Compatible with MySQL 8.0+ / MariaDB 10.5+ and SQLite on hosting.com / cPanel

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Client Issued Licenses
CREATE TABLE IF NOT EXISTS `portal_licenses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `license_id` VARCHAR(64) NOT NULL UNIQUE,
    `customer_name` VARCHAR(150) NOT NULL,
    `allowed_domains` JSON NOT NULL,
    `installation_fingerprint` VARCHAR(128) NOT NULL DEFAULT '*',
    `plan_tier` ENUM('STARTER', 'BUSINESS_PRO', 'ENTERPRISE') NOT NULL DEFAULT 'BUSINESS_PRO',
    `max_terminals` INT UNSIGNED NOT NULL DEFAULT 1,
    `features` JSON NOT NULL,
    `status` ENUM('ACTIVE', 'SUSPENDED', 'REVOKED') NOT NULL DEFAULT 'ACTIVE',
    `issued_at` BIGINT UNSIGNED NOT NULL,
    `expires_at` BIGINT UNSIGNED NOT NULL,
    `offline_grace_hours` INT UNSIGNED NOT NULL DEFAULT 72,
    `license_key_data` MEDIUMTEXT NOT NULL,
    `signature_hex` VARCHAR(256) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_lic_status` (`status`),
    INDEX `idx_lic_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Client Heartbeats (6-Hour Automated Pings)
CREATE TABLE IF NOT EXISTS `portal_heartbeats` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `license_id` VARCHAR(64) NOT NULL,
    `domain` VARCHAR(150) NOT NULL,
    `client_ip` VARCHAR(45) NOT NULL,
    `client_timestamp` BIGINT UNSIGNED NOT NULL,
    `server_utc_time` BIGINT UNSIGNED NOT NULL,
    `installation_fingerprint` VARCHAR(128) NOT NULL,
    `max_observed_utc` BIGINT UNSIGNED NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'OK',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_heartbeats_license` (`license_id`),
    INDEX `idx_heartbeats_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Inbound Customer Leads & Demo Requests
CREATE TABLE IF NOT EXISTS `portal_leads` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `shop_name` VARCHAR(150) NOT NULL,
    `contact_name` VARCHAR(150) NOT NULL,
    `email` VARCHAR(128) NOT NULL,
    `phone` VARCHAR(64) NOT NULL,
    `industry` VARCHAR(64) NOT NULL DEFAULT 'SUPERMARKET',
    `plan_tier` VARCHAR(32) NOT NULL DEFAULT 'BUSINESS_PRO',
    `terminals` INT UNSIGNED NOT NULL DEFAULT 1,
    `notes` TEXT NULL,
    `status` ENUM('NEW', 'CONTACTED', 'CLOSED', 'ARCHIVED') NOT NULL DEFAULT 'NEW',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_leads_status` (`status`),
    INDEX `idx_leads_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Custom Screenshots Showcase Gallery
CREATE TABLE IF NOT EXISTS `portal_screenshots` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(150) NOT NULL,
    `title_ar` VARCHAR(150) NULL,
    `category` VARCHAR(64) NOT NULL DEFAULT 'SUPERMARKET',
    `image_url` VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Vendor Master Keypair & Portal Settings
CREATE TABLE IF NOT EXISTS `portal_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `vendor_public_key_hex` VARCHAR(128) NOT NULL,
    `vendor_secret_key_hex` VARCHAR(256) NOT NULL,
    `admin_password_hash` VARCHAR(255) NOT NULL,
    `admin_email` VARCHAR(128) NOT NULL DEFAULT 'charafeddine@vendra.xyz',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pre-seed Vendor Master Ed25519 Keypair and Demo Admin (Password: VendorMasterPassword2026!)
INSERT INTO `portal_settings` (
    `id`, `vendor_public_key_hex`, `vendor_secret_key_hex`, `admin_password_hash`, `admin_email`
) VALUES (
    1,
    '7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4',
    'b3106c9011e167d15a84ae3f12857256be7d4efc357cedfbb7d0604bb322415e7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4',
    '$2y$12$V5UPI4B19fNt9sLIJd1z8uNNSiDhHrF/0tJ9qb7zLraqXue65VAzi',
    'charafeddine@vendra.xyz'
) ON DUPLICATE KEY UPDATE `vendor_public_key_hex` = VALUES(`vendor_public_key_hex`);
