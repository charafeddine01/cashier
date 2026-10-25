-- OmniPOS Pro - Normalized MySQL InnoDB Production Schema
-- Designed for Shared Web Hosting (cPanel/Apache/LiteSpeed with MySQL 8.0+ / MariaDB 10.5+)
-- Strict DECIMAL(15,4) precision for all monetary values and inventory quantities

SET FOREIGN_KEY_CHECKS = 0;

-- 1. System License & Monotonic Anti-Rollback State
CREATE TABLE IF NOT EXISTS `system_license_state` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `license_key_data` MEDIUMTEXT NOT NULL COMMENT 'Full JSON payload of signed Ed25519 license',
    `signature_hex` VARCHAR(256) NOT NULL COMMENT 'Ed25519 detached signature hex',
    `max_observed_utc` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Monotonic timestamp to prevent OS clock rollback',
    `clock_hmac` VARCHAR(128) NOT NULL COMMENT 'HMAC-SHA256 integrity tag for max_observed_utc',
    `lease_token` TEXT NULL COMMENT 'Short-lived signed lease token from central vendor server',
    `lease_expires_at` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `installation_fingerprint` VARCHAR(128) NOT NULL,
    `last_verified_at` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `is_tampered` TINYINT(1) NOT NULL DEFAULT 0,
    `tamper_reason` VARCHAR(255) NULL,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Dynamic Company & White-Label Settings
CREATE TABLE IF NOT EXISTS `company_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(150) NOT NULL DEFAULT 'OmniPOS Enterprise',
    `company_name_ar` VARCHAR(150) NULL DEFAULT 'أومني بوس إنتربرايز',
    `tax_id` VARCHAR(64) NULL DEFAULT 'TAX-99887766',
    `address` TEXT NULL,
    `address_ar` TEXT NULL,
    `phone` VARCHAR(64) NULL DEFAULT '+1 (800) 555-POS1',
    `email` VARCHAR(128) NULL DEFAULT 'store@omnipos-enterprise.com',
    `website` VARCHAR(128) NULL DEFAULT 'https://omnipos-enterprise.com',
    `primary_logo_url` MEDIUMTEXT NULL,
    `receipt_logo_url` MEDIUMTEXT NULL COMMENT 'Monochrome 1-bit or high-contrast receipt graphic',
    `favicon_url` MEDIUMTEXT NULL,
    `primary_color` VARCHAR(32) NOT NULL DEFAULT '#2563eb',
    `secondary_color` VARCHAR(32) NOT NULL DEFAULT '#10b981',
    `theme_mode` ENUM('light', 'dark', 'system') NOT NULL DEFAULT 'light',
    `business_mode` ENUM('SUPERMARKET', 'RESTAURANT', 'RETAIL', 'PHARMACY') NOT NULL DEFAULT 'SUPERMARKET',
    `primary_currency_code` VARCHAR(8) NOT NULL DEFAULT 'USD',
    `primary_currency_symbol` VARCHAR(8) NOT NULL DEFAULT '$',
    `secondary_currency_code` VARCHAR(8) NOT NULL DEFAULT 'EUR',
    `secondary_currency_symbol` VARCHAR(8) NOT NULL DEFAULT '€',
    `secondary_currency_rate` DECIMAL(15,4) NOT NULL DEFAULT 0.9200,
    `tax_enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `tax_inclusive` TINYINT(1) NOT NULL DEFAULT 0,
    `default_tax_rate` DECIMAL(15,4) NOT NULL DEFAULT 10.0000,
    `receipt_header` TEXT NULL,
    `receipt_footer` TEXT NULL,
    `receipt_width` ENUM('58mm', '80mm', 'A4') NOT NULL DEFAULT '80mm',
    `enable_customer_display` TINYINT(1) NOT NULL DEFAULT 1,
    `scale_barcode_prefix` VARCHAR(8) NOT NULL DEFAULT '21',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Staff Users, Roles & PIN Authentication
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(64) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `pin_code_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(128) NOT NULL,
    `email` VARCHAR(128) NULL,
    `phone` VARCHAR(64) NULL,
    `role` ENUM('ADMIN', 'MANAGER', 'CASHIER', 'WAITER', 'KITCHEN') NOT NULL DEFAULT 'CASHIER',
    `permissions` JSON NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_login_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`),
    INDEX `idx_users_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Product Categories
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `name_ar` VARCHAR(100) NULL,
    `icon` VARCHAR(64) NOT NULL DEFAULT 'package',
    `color` VARCHAR(32) NOT NULL DEFAULT '#3b82f6',
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cat_sort` (`sort_order`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Measurement Units (Multi-unit support)
CREATE TABLE IF NOT EXISTS `units` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(16) NOT NULL UNIQUE,
    `name` VARCHAR(64) NOT NULL,
    `name_ar` VARCHAR(64) NULL,
    `is_fractional` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 allows decimals (e.g. Kg), 0 integers only (e.g. Pcs)',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tax Rates
CREATE TABLE IF NOT EXISTS `tax_rates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(64) NOT NULL,
    `rate_percent` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Warehouses / Inventory Locations
CREATE TABLE IF NOT EXISTS `warehouses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `code` VARCHAR(32) NOT NULL UNIQUE,
    `name` VARCHAR(128) NOT NULL,
    `location` TEXT NULL,
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Master Products
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(64) NOT NULL UNIQUE,
    `barcode` VARCHAR(64) NULL,
    `name` VARCHAR(255) NOT NULL,
    `name_ar` VARCHAR(255) NULL,
    `category_id` INT UNSIGNED NULL,
    `unit_id` INT UNSIGNED NOT NULL,
    `tax_rate_id` INT UNSIGNED NULL,
    `type` ENUM('STANDARD', 'VARIABLE', 'COMBO', 'SCALE') NOT NULL DEFAULT 'STANDARD',
    `cost_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `selling_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `secondary_selling_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `track_inventory` TINYINT(1) NOT NULL DEFAULT 1,
    `alert_quantity` DECIMAL(15,4) NOT NULL DEFAULT 5.0000,
    `scale_plu` VARCHAR(16) NULL COMMENT '5-digit PLU for supermarket scales',
    `image_url` MEDIUMTEXT NULL,
    `modifiers_schema` JSON NULL COMMENT 'Restaurant modifier options (Extra cheese, etc.)',
    `is_kitchen_item` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Print on KOT / display on KDS',
    `kitchen_station` VARCHAR(64) NULL DEFAULT 'General Kitchen',
    `warranty_months` INT NOT NULL DEFAULT 0 COMMENT 'Retail warranty period',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_prod_barcode` (`barcode`),
    INDEX `idx_prod_plu` (`scale_plu`),
    INDEX `idx_prod_cat` (`category_id`),
    INDEX `idx_prod_type` (`type`),
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`),
    FOREIGN KEY (`tax_rate_id`) REFERENCES `tax_rates`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Product Variants (Retail Size/Color Matrix)
CREATE TABLE IF NOT EXISTS `product_variants` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `sku` VARCHAR(64) NOT NULL UNIQUE,
    `barcode` VARCHAR(64) NULL,
    `variant_name` VARCHAR(150) NOT NULL,
    `attribute_matrix` JSON NOT NULL COMMENT '{"Size": "L", "Color": "Navy"}',
    `cost_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `selling_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_var_barcode` (`barcode`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Multi-Unit Barcode & Conversions (Piece / Box / Carton)
CREATE TABLE IF NOT EXISTS `product_units` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `unit_id` INT UNSIGNED NOT NULL,
    `conversion_factor` DECIMAL(15,4) NOT NULL DEFAULT 1.0000 COMMENT 'E.g., Box of 12 pcs = 12.0000',
    `barcode` VARCHAR(64) NOT NULL,
    `selling_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `is_default_sale` TINYINT(1) NOT NULL DEFAULT 0,
    UNIQUE KEY `uk_prod_unit_barcode` (`barcode`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Bill of Materials (BOM) / Restaurant Recipes
CREATE TABLE IF NOT EXISTS `product_recipes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `parent_product_id` INT UNSIGNED NOT NULL,
    `ingredient_product_id` INT UNSIGNED NOT NULL,
    `quantity` DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    `unit_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`parent_product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ingredient_product_id`) REFERENCES `products`(`id`),
    FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Pharmacy Batch / Lot & Expiry Tracking (FEFO)
CREATE TABLE IF NOT EXISTS `product_batches` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `batch_number` VARCHAR(64) NOT NULL,
    `expiry_date` DATE NOT NULL,
    `quantity_received` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `quantity_remaining` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `cost_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_batch_expiry` (`product_id`, `expiry_date`, `quantity_remaining`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Retail Serial Numbers / IMEI Tracking
CREATE TABLE IF NOT EXISTS `product_serials` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `serial_number` VARCHAR(128) NOT NULL UNIQUE,
    `status` ENUM('AVAILABLE', 'SOLD', 'DEFECTIVE') NOT NULL DEFAULT 'AVAILABLE',
    `order_id` INT UNSIGNED NULL,
    `warranty_months` INT NOT NULL DEFAULT 12,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_serial_status` (`product_id`, `status`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Stock Inventory Levels
CREATE TABLE IF NOT EXISTS `stock_inventory` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `quantity` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_prod_var_wh` (`product_id`, `variant_id`, `warehouse_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Stock Ledger & Audit Movements
CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `movement_type` ENUM('SALE', 'RETURN', 'PURCHASE', 'ADJUSTMENT', 'TRANSFER_IN', 'TRANSFER_OUT', 'RECIPE_DEDUCTION') NOT NULL,
    `quantity` DECIMAL(15,4) NOT NULL,
    `cost_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `reference_type` VARCHAR(64) NULL,
    `reference_id` INT UNSIGNED NULL,
    `notes` TEXT NULL,
    `created_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_stock_mov_ref` (`reference_type`, `reference_id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`),
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 16. Cashier Shifts & Drawer Sessions
CREATE TABLE IF NOT EXISTS `shifts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cashier_id` INT UNSIGNED NOT NULL,
    `terminal_id` VARCHAR(64) NOT NULL DEFAULT 'TERM-01',
    `opened_at` DATETIME NOT NULL,
    `closed_at` DATETIME NULL,
    `opening_float` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `expected_cash` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `counted_cash` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `cash_difference` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `total_sales_cash` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `total_sales_card` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `total_sales_credit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `total_sales_on_account` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `total_returns` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `total_cash_in` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `total_cash_out` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `status` ENUM('OPEN', 'CLOSED') NOT NULL DEFAULT 'OPEN',
    `notes` TEXT NULL,
    `closing_breakdown` JSON NULL COMMENT 'Breakdown of counted bills/coins',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_shift_status` (`cashier_id`, `status`),
    FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 17. Cash In / Cash Out (Petty Cash Movements)
CREATE TABLE IF NOT EXISTS `cash_movements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `shift_id` INT UNSIGNED NOT NULL,
    `cashier_id` INT UNSIGNED NOT NULL,
    `type` ENUM('CASH_IN', 'CASH_OUT') NOT NULL,
    `amount` DECIMAL(15,4) NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 18. Customers & Credit Ledger
CREATE TABLE IF NOT EXISTS `customers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(64) NULL,
    `email` VARCHAR(128) NULL,
    `address` TEXT NULL,
    `tax_number` VARCHAR(64) NULL,
    `credit_limit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `current_balance` DECIMAL(15,4) NOT NULL DEFAULT 0.0000 COMMENT 'Positive = customer owes money (debt)',
    `loyalty_points` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cust_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 19. Customer Debt & Payment Ledger
CREATE TABLE IF NOT EXISTS `customer_ledger` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT UNSIGNED NOT NULL,
    `transaction_type` ENUM('INVOICE', 'PAYMENT', 'REFUND_CREDIT', 'ADJUSTMENT') NOT NULL,
    `amount` DECIMAL(15,4) NOT NULL,
    `balance_after` DECIMAL(15,4) NOT NULL,
    `reference_type` VARCHAR(64) NULL,
    `reference_id` INT UNSIGNED NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 20. Restaurant Floors & Visual Table Layout
CREATE TABLE IF NOT EXISTS `restaurant_floors` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(64) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `restaurant_tables` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `floor_id` INT UNSIGNED NOT NULL,
    `table_number` VARCHAR(32) NOT NULL,
    `capacity` INT NOT NULL DEFAULT 4,
    `position_x` INT NOT NULL DEFAULT 50,
    `position_y` INT NOT NULL DEFAULT 50,
    `width` INT NOT NULL DEFAULT 100,
    `height` INT NOT NULL DEFAULT 100,
    `shape` ENUM('RECTANGLE', 'ROUND') NOT NULL DEFAULT 'RECTANGLE',
    `status` ENUM('AVAILABLE', 'OCCUPIED', 'BILL_PRINTED') NOT NULL DEFAULT 'AVAILABLE',
    `current_order_id` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`floor_id`) REFERENCES `restaurant_floors`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 21. POS Orders & Sales Transactions
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `invoice_number` VARCHAR(64) NOT NULL UNIQUE,
    `shift_id` INT UNSIGNED NULL,
    `cashier_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `order_type` ENUM('DINE_IN', 'TAKEAWAY', 'DELIVERY', 'RETAIL') NOT NULL DEFAULT 'RETAIL',
    `status` ENUM('PAID', 'HELD', 'CANCELLED', 'REFUNDED', 'PARTIALLY_REFUNDED') NOT NULL DEFAULT 'PAID',
    `subtotal` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `tax_total` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `discount_total` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `grand_total` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `grand_total_secondary` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `exchange_rate` DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    `paid_cash` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `paid_card` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `paid_credit` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `paid_on_account` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `change_given` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `change_currency` ENUM('PRIMARY', 'SECONDARY') NOT NULL DEFAULT 'PRIMARY',
    `table_id` INT UNSIGNED NULL,
    `seat_number` VARCHAR(32) NULL,
    `kitchen_status` ENUM('PENDING', 'PREPARING', 'READY', 'SERVED') NOT NULL DEFAULT 'PENDING',
    `idempotency_key` VARCHAR(64) NOT NULL UNIQUE,
    `terminal_id` VARCHAR(64) NOT NULL DEFAULT 'TERM-01',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_order_shift` (`shift_id`),
    INDEX `idx_order_date` (`created_at`),
    INDEX `idx_order_cust` (`customer_id`),
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`),
    FOREIGN KEY (`table_id`) REFERENCES `restaurant_tables`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 22. POS Order Line Items
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NULL,
    `batch_id` INT UNSIGNED NULL,
    `unit_id` INT UNSIGNED NOT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `sku` VARCHAR(64) NOT NULL,
    `barcode` VARCHAR(64) NULL,
    `quantity` DECIMAL(15,4) NOT NULL DEFAULT 1.0000,
    `unit_cost` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `unit_price` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `discount_amount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `tax_rate` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `tax_amount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `line_total` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `modifiers` JSON NULL COMMENT '[{"name": "No Onions", "price": 0.00}, {"name": "Extra Bacon", "price": 1.50}]',
    `notes` TEXT NULL,
    `serial_numbers` JSON NULL,
    `kitchen_status` ENUM('PENDING', 'PREPARING', 'READY', 'SERVED') NOT NULL DEFAULT 'PENDING',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`),
    FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`unit_id`) REFERENCES `units`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 23. Order Returns & Refunds
CREATE TABLE IF NOT EXISTS `order_returns` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `return_number` VARCHAR(64) NOT NULL UNIQUE,
    `original_order_id` INT UNSIGNED NOT NULL,
    `shift_id` INT UNSIGNED NULL,
    `cashier_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NULL,
    `total_refund` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `refund_method` ENUM('CASH', 'CARD', 'STORE_CREDIT') NOT NULL DEFAULT 'CASH',
    `reason` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`original_order_id`) REFERENCES `orders`(`id`),
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`cashier_id`) REFERENCES `users`(`id`),
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_return_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `return_id` INT UNSIGNED NOT NULL,
    `order_item_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity` DECIMAL(15,4) NOT NULL,
    `unit_price` DECIMAL(15,4) NOT NULL,
    `refund_amount` DECIMAL(15,4) NOT NULL,
    `restock_action` ENUM('RESTOCK', 'WASTE_DAMAGED') NOT NULL DEFAULT 'RESTOCK',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`return_id`) REFERENCES `order_returns`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`order_item_id`) REFERENCES `order_items`(`id`),
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 24. Suppliers & Purchase Orders (Goods Receiving)
CREATE TABLE IF NOT EXISTS `suppliers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `company_name` VARCHAR(150) NOT NULL,
    `contact_person` VARCHAR(128) NULL,
    `phone` VARCHAR(64) NULL,
    `email` VARCHAR(128) NULL,
    `address` TEXT NULL,
    `tax_id` VARCHAR(64) NULL,
    `balance` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_orders` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `po_number` VARCHAR(64) NOT NULL UNIQUE,
    `supplier_id` INT UNSIGNED NOT NULL,
    `warehouse_id` INT UNSIGNED NOT NULL,
    `status` ENUM('PENDING', 'RECEIVED', 'PARTIAL', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    `total_amount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `paid_amount` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `received_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`),
    FOREIGN KEY (`warehouse_id`) REFERENCES `warehouses`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `purchase_order_items` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `purchase_order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `quantity_ordered` DECIMAL(15,4) NOT NULL,
    `quantity_received` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `unit_cost` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `total_cost` DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    `batch_number` VARCHAR(64) NULL,
    `expiry_date` DATE NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `products`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 25. Operational Expenses
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `shift_id` INT UNSIGNED NULL,
    `category` VARCHAR(64) NOT NULL,
    `amount` DECIMAL(15,4) NOT NULL,
    `payment_method` ENUM('CASH', 'CARD', 'BANK') NOT NULL DEFAULT 'CASH',
    `payee` VARCHAR(150) NOT NULL,
    `notes` TEXT NULL,
    `recorded_by` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`recorded_by`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 26. Security & Audit Logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(64) NOT NULL,
    `module` VARCHAR(64) NOT NULL,
    `record_id` INT UNSIGNED NULL,
    `details` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_audit_mod` (`module`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
