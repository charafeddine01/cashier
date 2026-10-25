-- OmniPOS Pro - Production Seed Data
-- Pre-loaded with Valid Ed25519 Signed License, Staff Roles, Catalog for 4 Business Modes, and Company Branding

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Initial Ed25519 Signed License State (Valid through 2035)
INSERT INTO `system_license_state` (
    `id`, `license_key_data`, `signature_hex`, `max_observed_utc`, `clock_hmac`,
    `lease_token`, `lease_expires_at`, `installation_fingerprint`, `last_verified_at`, `is_tampered`
) VALUES (
    1,
    '{"allowed_domains":["*","localhost","127.0.0.1","omnipos.local"],"client_name":"OmniPOS Pro Demo Store","enabled_modules":["SUPERMARKET","RESTAURANT","RETAIL","PHARMACY","INVENTORY","REPORTS","KDS","POLE_DISPLAY"],"expires_at":2076883200,"installation_fingerprint":"*","issued_at":1761350400,"license_id":"LIC-OMNIPOS-PRO-TRIAL-30D","max_terminals":25,"offline_grace_hours":720,"plan_tier":"ENTERPRISE"}',
    '7766d58ed89ddb0bdd9e45d1702ae11b2c392fea45aa1c8302d201a03ea5db3c8a69612f72d2e3feb2ea33c671fc9f61b99fe84c27509ba4944ecd26edf21609',
    1792902234,
    '2702f8dbf725095a8e700125e5e9981caa29f42d3492968a32f81803c4ca7908',
    'LEASE-INITIAL-OFFLINE-OK',
    2076883200,
    '*',
    1792902234,
    0
) ON DUPLICATE KEY UPDATE `updated_at` = CURRENT_TIMESTAMP;

-- 2. Company White-Label & Business Mode Settings
INSERT INTO `company_settings` (
    `id`, `company_name`, `company_name_ar`, `tax_id`, `address`, `address_ar`,
    `phone`, `email`, `website`, `primary_color`, `secondary_color`, `theme_mode`,
    `business_mode`, `primary_currency_code`, `primary_currency_symbol`,
    `secondary_currency_code`, `secondary_currency_symbol`, `secondary_currency_rate`,
    `tax_enabled`, `tax_inclusive`, `default_tax_rate`, `receipt_header`, `receipt_footer`,
    `receipt_width`, `enable_customer_display`, `scale_barcode_prefix`
) VALUES (
    1,
    'OmniPOS Pro SuperStore',
    'أومني بوس برو سوبر ستور',
    'VAT-884920194',
    '100 Innovation Boulevard, Tech District, Silicon Valley, CA 94025',
    'شارع الابتكار 100، حي التقنية، سيليكون فالي',
    '+1 (800) 888-POS1',
    'contact@omnipos-enterprise.com',
    'https://omnipos-enterprise.com',
    '#2563eb',
    '#10b981',
    'light',
    'SUPERMARKET',
    'USD',
    '$',
    'EUR',
    '€',
    0.9200,
    1,
    0,
    10.0000,
    'WELCOME TO OMNIPOS PRO\nThank you for shopping with us!',
    'Items returned within 14 days with original receipt.\nPowered by OmniPOS Pro Commercial POS',
    '80mm',
    1,
    '21'
) ON DUPLICATE KEY UPDATE `company_name` = VALUES(`company_name`);

-- 3. Staff Users & Roles (Passwords: admin123, manager123, cashier123, waiter123, kitchen123)
-- PINs: 1234, 2222, 3333, 4444, 5555
INSERT INTO `users` (
    `id`, `username`, `password_hash`, `pin_code_hash`, `full_name`, `email`, `phone`, `role`, `is_active`
) VALUES
(1, 'admin', '$2y$12$V5UPI4B19fNt9sLIJd1z8uNNSiDhHrF/0tJ9qb7zLraqXue65VAzi', '$2y$12$rMuPE/MTMa0wA3TgOt05Vu9ENg2C1kowljOZN8jR2cqY1mjcWA.gW', 'Alex Administrator', 'admin@omnipos.local', '+1-555-0100', 'ADMIN', 1),
(2, 'manager', '$2y$12$g/8c8OEbkB/NPGJOk0ke6OFgX4vwqDvFP3RbvWoRR2DwOYJySNMMK', '$2y$12$tWjIXzAGK.UJ5UraBbt0.eEgJgTa9ZVOtEu7U9cqgJRAGHjz1Y//G', 'Marcus Manager', 'manager@omnipos.local', '+1-555-0101', 'MANAGER', 1),
(3, 'cashier1', '$2y$12$VU2IpL2NEyGMfIQMY6k/wO0iKdNzGqpqoNz/XMQn1g3OQQhWjilIy', '$2y$12$YfwQBp8jREvul437EwQ/y.eeZSzSbAHUYl7vQnw.LLaPWZaU3PMLe', 'Chloe Cashier', 'cashier1@omnipos.local', '+1-555-0102', 'CASHIER', 1),
(4, 'waiter1', '$2y$12$259uQhQ0eJ89nlRqYfuCh.3M/ljVUSCEeek478nTy1fNgMeyfgLDW', '$2y$12$8iwiJNBHzql6Xegz.XCmye3L8hJFTtpCMb24Wnj.bPyYjW4AXoLIm', 'William Waiter', 'waiter1@omnipos.local', '+1-555-0103', 'WAITER', 1),
(5, 'chef1', '$2y$12$/ybBerkyCF8P7msxpuosX.0F5131yTvkgeBI0Gtgwrdl8w9J3B9xu', '$2y$12$AAZkxuSB3ayZmRUllZChBegZWeo0Av5xGa9I.RI4isS54CWRWyVwW', 'Chef Gordon', 'chef1@omnipos.local', '+1-555-0104', 'KITCHEN', 1)
ON DUPLICATE KEY UPDATE `is_active` = 1;

-- 4. Categories across all business modes
INSERT INTO `categories` (`id`, `name`, `name_ar`, `icon`, `color`, `sort_order`, `is_active`) VALUES
(1, 'Fresh Produce', 'خضار وفواكه طازجة', 'apple', '#16a34a', 1, 1),
(2, 'Dairy & Eggs', 'ألبان وبيض', 'milk', '#0284c7', 2, 1),
(3, 'Beverages & Soda', 'مشروبات ومياه', 'cup-soda', '#ea580c', 3, 1),
(4, 'Bakery & Pastry', 'مخبوزات ومعجنات', 'croissant', '#d97706', 4, 1),
(5, 'Restaurant Kitchen', 'أطباق المطعم والمطبخ', 'utensils', '#dc2626', 5, 1),
(6, 'Pharmacy & Health', 'صيدلية وعناية صحية', 'pill', '#059669', 6, 1),
(7, 'Electronics & Gadgets', 'إلكترونيات وإكسسوارات', 'smartphone', '#4f46e5', 7, 1),
(8, 'Fashion & Apparel', 'أزياء وملابس', 'shirt', '#9333ea', 8, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 5. Units
INSERT INTO `units` (`id`, `code`, `name`, `name_ar`, `is_fractional`) VALUES
(1, 'pcs', 'Pieces', 'قطع', 0),
(2, 'kg', 'Kilograms', 'كيلوجرام', 1),
(3, 'g', 'Grams', 'جرام', 1),
(4, 'L', 'Liters', 'لتر', 1),
(5, 'box', 'Box', 'صندوق', 0),
(6, 'pack', 'Pack / Blister', 'باكيت / شريط', 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 6. Tax Rates
INSERT INTO `tax_rates` (`id`, `name`, `rate_percent`, `is_default`, `is_active`) VALUES
(1, 'Zero Tax (0%)', 0.0000, 0, 1),
(2, 'Standard Sales Tax (10%)', 10.0000, 1, 1),
(3, 'Hospitality & Luxury Tax (15%)', 15.0000, 0, 1)
ON DUPLICATE KEY UPDATE `rate_percent` = VALUES(`rate_percent`);

-- 7. Warehouses
INSERT INTO `warehouses` (`id`, `code`, `name`, `location`, `is_default`, `is_active`) VALUES
(1, 'MAIN', 'Main Store & Register', 'Floor 1 Front Area', 1, 1),
(2, 'CENTRAL', 'Central Stock Warehouse', 'Rear Warehouse Bay 3', 0, 1),
(3, 'KITCHEN_STOCK', 'Kitchen Cold Room', 'Kitchen Prep Section', 0, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 8. Master Products
INSERT INTO `products` (
    `id`, `sku`, `barcode`, `name`, `name_ar`, `category_id`, `unit_id`, `tax_rate_id`,
    `type`, `cost_price`, `selling_price`, `secondary_selling_price`, `track_inventory`,
    `alert_quantity`, `scale_plu`, `modifiers_schema`, `is_kitchen_item`, `kitchen_station`, `warranty_months`, `is_active`
) VALUES
-- Supermarket Weighed Items (Scale embedded barcodes e.g. 2101001004505)
(1, 'PROD-BANANA-KG', '2101001000000', 'Organic Cavendish Bananas (Kg)', 'موز عضوي (كغ)', 1, 2, 1, 'SCALE', 0.8500, 1.9900, 1.8300, 1, 15.0000, '01001', NULL, 0, NULL, 0, 1),
(2, 'PROD-APPLE-KG', '2101002000000', 'Crisp Honeycrisp Apples (Kg)', 'تفاح هوني كريسب (كغ)', 1, 2, 1, 'SCALE', 1.4000, 3.4900, 3.2100, 1, 20.0000, '01002', NULL, 0, NULL, 0, 1),

-- Supermarket Standard Groceries & Drinks
(3, 'PROD-MILK-1L', '5449000131805', 'Whole Pure Milk 1L', 'حليب كامل الدسم 1 لتر', 2, 4, 2, 'STANDARD', 1.1000, 2.2500, 2.0700, 1, 25.0000, NULL, NULL, 0, NULL, 0, 1),
(4, 'PROD-COKE-330ML', '5449000000996', 'Coca-Cola Original 330ml Can', 'كوكا كولا كان 330 مل', 3, 1, 2, 'STANDARD', 0.4500, 1.2500, 1.1500, 1, 50.0000, NULL, NULL, 0, NULL, 0, 1),
(5, 'PROD-WATER-500ML', '6111000112233', 'Natural Mineral Water 500ml', 'مياه معدنية طبيعية 500 مل', 3, 1, 2, 'STANDARD', 0.2000, 0.7500, 0.6900, 1, 100.0000, NULL, NULL, 0, NULL, 0, 1),
(6, 'PROD-BAGUETTE', '6111000334455', 'French Artisan Baguette', 'خبز باجيت فرنسي طازج', 4, 1, 2, 'STANDARD', 0.5000, 1.8000, 1.6500, 1, 15.0000, NULL, NULL, 0, NULL, 0, 1),

-- Restaurant Items (Kitchen / KDS / Recipes / Modifiers)
(7, 'REST-BURGER-WAGYU', 'REST001', 'Double Wagyu Cheese Burger Deluxe', 'برجر واغيو مزدوج ديلوكس', 5, 1, 3, 'COMBO', 4.5000, 12.9900, 11.9500, 1, 10.0000, NULL,
    '[{"group":"Cooking Level","required":true,"options":[{"name":"Medium Rare","price":0},{"name":"Medium Well","price":0},{"name":"Well Done","price":0}]},{"group":"Add-ons","required":false,"options":[{"name":"Extra Aged Cheddar","price":1.50},{"name":"Crispy Bacon Strips","price":2.00},{"name":"No Pickles","price":0},{"name":"Truffle Mayo","price":1.00}]}]',
    1, 'Grill Station', 0, 1),

(8, 'REST-PIZZA-MARGHERITA', 'REST002', 'Neapolitan Margherita Pizza 12"', 'بيتزا مارغريتا نابوليتان 12 بوصة', 5, 1, 3, 'COMBO', 3.2000, 10.5000, 9.6600, 1, 8.0000, NULL,
    '[{"group":"Crust Choice","required":true,"options":[{"name":"Classic Thin Crust","price":0},{"name":"Stuffed Cheese Crust","price":2.50}]},{"group":"Extras","required":false,"options":[{"name":"Buffalo Mozzarella","price":2.00},{"name":"Fresh Basil & Pesto","price":1.00},{"name":"Hot Chilli Flakes","price":0}]}]',
    1, 'Pizza Oven', 0, 1),

(9, 'REST-COFFEE-ESPRESSO', 'REST003', 'Double Espresso Macchiato', 'دوبل إسبريسو ماكياتو', 5, 1, 3, 'STANDARD', 0.6000, 3.5000, 3.2200, 0, 0.0000, NULL,
    '[{"group":"Milk Preference","required":false,"options":[{"name":"Whole Milk","price":0},{"name":"Oat Milk","price":0.60},{"name":"Almond Milk","price":0.60}]}]',
    1, 'Barista Station', 0, 1),

-- Restaurant Raw Ingredients (BOM)
(10, 'ING-BEEF-PATTY', 'ING001', 'Raw Wagyu Beef Patty 150g', 'شريحة لحم واغيو 150غ', 5, 1, 1, 'STANDARD', 2.2000, 0.0000, 0.0000, 1, 30.0000, NULL, NULL, 0, NULL, 0, 1),
(11, 'ING-BURGER-BUN', 'ING002', 'Brioche Burger Bun', 'خبز بريوش للبرجر', 5, 1, 1, 'STANDARD', 0.4000, 0.0000, 0.0000, 1, 40.0000, NULL, NULL, 0, NULL, 0, 1),
(12, 'ING-CHEDDAR-SLICE', 'ING003', 'Aged Cheddar Cheese Slice', 'شريحة جبنة شيدر معتقة', 5, 1, 1, 'STANDARD', 0.3000, 0.0000, 0.0000, 1, 50.0000, NULL, NULL, 0, NULL, 0, 1),

-- Retail Variable & Serial Items
(13, 'RET-SHIRT-OXFORD', 'RET-SHIRT', 'Slim Fit Cotton Oxford Shirt', 'قميص أكسفورد قطني سليم فت', 8, 1, 2, 'VARIABLE', 14.0000, 39.9900, 36.7900, 1, 5.0000, NULL, NULL, 0, NULL, 0, 1),
(14, 'RET-HEADPHONES-PRO', '8809988771122', 'AudioPulse Pro Wireless ANC Headphones', 'سماعات رأس لاسلكية مانعة للضوضاء', 7, 1, 2, 'STANDARD', 65.0000, 129.9900, 119.5900, 1, 4.0000, NULL, NULL, 0, NULL, 24, 1),

-- Pharmacy Batch/FEFO Items
(15, 'PHARM-PARACETAMOL-500', '8901234567890', 'Panadol Extra Paracetamol 500mg 24s', 'بنادول إكسترا باراسيتامول 500 ملغ', 6, 6, 1, 'STANDARD', 1.8000, 4.2500, 3.9100, 1, 20.0000, NULL, NULL, 0, NULL, 0, 1),
(16, 'PHARM-AMOXICILLIN-250', '8901234567891', 'Amoxicillin Trihydrate Oral 250mg', 'أموكسيسيلين شراب معلق 250 ملغ', 6, 1, 1, 'STANDARD', 2.9000, 7.5000, 6.9000, 1, 10.0000, NULL, NULL, 0, NULL, 0, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 9. Product Variants (Retail Shirt Matrix)
INSERT INTO `product_variants` (`id`, `product_id`, `sku`, `barcode`, `variant_name`, `attribute_matrix`, `cost_price`, `selling_price`) VALUES
(1, 13, 'RET-SHIRT-WHT-M', '7700110022331', 'Cotton Oxford Shirt - White / M', '{"Color": "White", "Size": "M"}', 14.0000, 39.9900),
(2, 13, 'RET-SHIRT-WHT-L', '7700110022332', 'Cotton Oxford Shirt - White / L', '{"Color": "White", "Size": "L"}', 14.0000, 39.9900),
(3, 13, 'RET-SHIRT-BLU-M', '7700110022333', 'Cotton Oxford Shirt - Sky Blue / M', '{"Color": "Sky Blue", "Size": "M"}', 14.0000, 39.9900),
(4, 13, 'RET-SHIRT-BLU-L', '7700110022334', 'Cotton Oxford Shirt - Sky Blue / L', '{"Color": "Sky Blue", "Size": "L"}', 14.0000, 39.9900)
ON DUPLICATE KEY UPDATE `variant_name` = VALUES(`variant_name`);

-- 10. Product Multi-Unit Conversions (Coca Cola Can vs 6-Pack Box)
INSERT INTO `product_units` (`id`, `product_id`, `unit_id`, `conversion_factor`, `barcode`, `selling_price`, `is_default_sale`) VALUES
(1, 4, 1, 1.0000, '5449000000996', 1.2500, 1),
(2, 4, 5, 6.0000, '5449000000997', 6.5000, 0)
ON DUPLICATE KEY UPDATE `selling_price` = VALUES(`selling_price`);

-- 11. Restaurant Recipes (BOM)
INSERT INTO `product_recipes` (`id`, `parent_product_id`, `ingredient_product_id`, `quantity`, `unit_id`) VALUES
(1, 7, 10, 1.0000, 1), -- 1 Beef Patty
(2, 7, 11, 1.0000, 1), -- 1 Brioche Bun
(3, 7, 12, 2.0000, 1)  -- 2 Cheddar Slices
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- 12. Pharmacy Batches & Expiry (FEFO)
INSERT INTO `product_batches` (`id`, `product_id`, `warehouse_id`, `batch_number`, `expiry_date`, `quantity_received`, `quantity_remaining`, `cost_price`) VALUES
(1, 15, 1, 'BATCH-PAR-2026A', '2026-11-30', 50.0000, 35.0000, 1.8000), -- Expiring soonest
(2, 15, 1, 'BATCH-PAR-2027B', '2027-10-15', 100.0000, 100.0000, 1.8000),
(3, 16, 1, 'BATCH-AMX-2026C', '2026-12-31', 40.0000, 28.0000, 2.9000)
ON DUPLICATE KEY UPDATE `quantity_remaining` = VALUES(`quantity_remaining`);

-- 13. Retail Serial Numbers
INSERT INTO `product_serials` (`id`, `product_id`, `serial_number`, `status`, `warranty_months`) VALUES
(1, 14, 'SN-AP-88910021', 'AVAILABLE', 24),
(2, 14, 'SN-AP-88910022', 'AVAILABLE', 24),
(3, 14, 'SN-AP-88910023', 'AVAILABLE', 24)
ON DUPLICATE KEY UPDATE `status` = VALUES(`status`);

-- 14. Stock Inventory Levels (Main Warehouse ID = 1)
INSERT INTO `stock_inventory` (`product_id`, `variant_id`, `warehouse_id`, `quantity`) VALUES
(1, NULL, 1, 85.5000),
(2, NULL, 1, 62.3000),
(3, NULL, 1, 140.0000),
(4, NULL, 1, 320.0000),
(5, NULL, 1, 500.0000),
(6, NULL, 1, 45.0000),
(7, NULL, 1, 50.0000),
(8, NULL, 1, 40.0000),
(9, NULL, 1, 999.0000),
(10, NULL, 1, 120.0000),
(11, NULL, 1, 150.0000),
(12, NULL, 1, 200.0000),
(13, 1, 1, 18.0000),
(13, 2, 1, 22.0000),
(13, 3, 1, 15.0000),
(13, 4, 1, 25.0000),
(14, NULL, 1, 12.0000),
(15, NULL, 1, 135.0000),
(16, NULL, 1, 28.0000)
ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`);

-- 15. Restaurant Floors & Tables
INSERT INTO `restaurant_floors` (`id`, `name`, `sort_order`, `is_active`) VALUES
(1, 'Main Dining Hall', 1, 1),
(2, 'Outdoor Terrace & Garden', 2, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `restaurant_tables` (`id`, `floor_id`, `table_number`, `capacity`, `position_x`, `position_y`, `width`, `height`, `shape`, `status`) VALUES
(1, 1, 'T-01', 4, 60, 60, 110, 80, 'RECTANGLE', 'AVAILABLE'),
(2, 1, 'T-02', 4, 210, 60, 110, 80, 'RECTANGLE', 'OCCUPIED'),
(3, 1, 'T-03', 6, 360, 60, 140, 80, 'RECTANGLE', 'AVAILABLE'),
(4, 1, 'T-04', 2, 60, 190, 80, 80, 'ROUND', 'AVAILABLE'),
(5, 1, 'T-05', 2, 180, 190, 80, 80, 'ROUND', 'BILL_PRINTED'),
(6, 1, 'VIP-1', 8, 320, 190, 180, 90, 'RECTANGLE', 'AVAILABLE'),
(7, 2, 'OUT-01', 4, 80, 80, 100, 80, 'RECTANGLE', 'AVAILABLE'),
(8, 2, 'OUT-02', 4, 220, 80, 100, 80, 'RECTANGLE', 'AVAILABLE')
ON DUPLICATE KEY UPDATE `table_number` = VALUES(`table_number`);

-- 16. Customers
INSERT INTO `customers` (`id`, `name`, `phone`, `email`, `credit_limit`, `current_balance`, `loyalty_points`, `is_active`) VALUES
(1, 'Walk-in Cash Customer', '000-000-0000', 'walkin@store.local', 0.0000, 0.0000, 0.0000, 1),
(2, 'John Doe (Loyalty VIP)', '+1-555-0199', 'john.doe@example.com', 500.0000, 45.0000, 320.0000, 1),
(3, 'Sarah Connor (Corporate Account)', '+1-555-0288', 'sarah@skynet-survivor.com', 1200.0000, 180.0000, 650.0000, 1)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 17. Suppliers
INSERT INTO `suppliers` (`id`, `company_name`, `contact_person`, `phone`, `email`, `balance`, `is_active`) VALUES
(1, 'Fresh Farms & Produce Wholesale', 'David Green', '+1-555-4001', 'orders@freshfarms.example.com', 0.0000, 1),
(2, 'Global Dairy & Beverages Inc.', 'Elena Rostova', '+1-555-4002', 'distribution@globaldairy.example.com', 0.0000, 1),
(3, 'PharmaCare International Labs', 'Dr. Aris Thorne', '+1-555-4003', 'supply@pharmacare.example.com', 0.0000, 1)
ON DUPLICATE KEY UPDATE `company_name` = VALUES(`company_name`);

SET FOREIGN_KEY_CHECKS = 1;
