<?php
declare(strict_types=1);

/**
 * OmniPOS Pro - Solar Energy Showroom Database Generator
 * Copies base SQLite schema and seeds tailored data for Solar Showrooms.
 */

$baseDb = __DIR__ . '/omnipos.sqlite';
$targetDb = $argv[1] ?? __DIR__ . '/omnipos-solar.sqlite';

if (!file_exists($baseDb)) {
    die("Base database not found: {$baseDb}\n");
}

copy($baseDb, $targetDb);
echo "Cloned base schema from {$baseDb} to {$targetDb}\n";

$pdo = new PDO("sqlite:{$targetDb}");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Clean up transaction and catalog data
$tablesToClear = [
    'orders', 'order_items', 'order_returns', 'order_return_items',
    'product_serials', 'product_recipes', 'product_batches', 'stock_inventory',
    'stock_movements', 'cash_movements', 'shifts', 'products', 'categories',
    'customers', 'customer_ledger', 'expenses', 'suppliers', 'purchase_orders'
];

$pdo->exec("PRAGMA foreign_keys = OFF;");
foreach ($tablesToClear as $tbl) {
    $pdo->exec("DELETE FROM `{$tbl}`;");
}

// 1. Signed Ed25519 Commercial License for Solar Showroom
$vendorSecretKeyHex = 'b3106c9011e167d15a84ae3f12857256be7d4efc357cedfbb7d0604bb322415e7cba51469668791a660ee64c5815922b0349dc4e74ae39e2cc9c5d232dacbcf4';
$secKey = hex2bin($vendorSecretKeyHex);

$now = time();
$cert = [
    'license_id' => 'LIC-SOLAR-BAALBECK-2026-001',
    'client_name' => 'Al-Shams Solar Energy Solutions (Baalbeck)',
    'plan_tier' => 'ENTERPRISE',
    'max_terminals' => 10,
    'enabled_modules' => ['RETAIL', 'SUPERMARKET', 'RESTAURANT', 'PHARMACY', 'INVENTORY', 'SERIAL_TRACKING', 'WARRANTY', 'BOM_KITS', 'REPORTS', 'CUSTOMERS', 'QUOTATIONS', 'POLE_DISPLAY'],
    'allowed_domains' => ['*', 'localhost', '127.0.0.1', 'omnipos.local'],
    'issued_at' => $now,
    'expires_at' => $now + (3 * 365 * 86400), // 3 years
    'offline_grace_hours' => 8760,
    'installation_fingerprint' => '*'
];
ksort($cert);
$canonicalCert = json_encode($cert, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$sigHex = bin2hex(sodium_crypto_sign_detached($canonicalCert, $secKey));

$config = require __DIR__ . '/../api/config.php';
$securitySalt = $config['security_salt'] ?? 'OMNIPOS_CORE_SALT_2026_SECURE';
$clockHmac = hash_hmac('sha256', (string)$now, $securitySalt);

$pdo->exec("DELETE FROM `system_license_state`;");
$stmt = $pdo->prepare('INSERT INTO `system_license_state` (
    `id`, `license_key_data`, `signature_hex`, `max_observed_utc`, `clock_hmac`,
    `lease_token`, `lease_expires_at`, `installation_fingerprint`, `last_verified_at`, `is_tampered`
) VALUES (1, :lic_data, :sig, :now_ts, :hmac, :lease, :lexp, :fp, :now_ts2, 0)');

$stmt->execute([
    ':lic_data' => json_encode($cert),
    ':sig' => $sigHex,
    ':now_ts' => $now,
    ':hmac' => $clockHmac,
    ':lease' => 'STANDALONE_SOLAR_PREACTIVATED',
    ':lexp' => $now + (3 * 365 * 86400),
    ':fp' => '*',
    ':now_ts2' => $now,
]);

// 2. Company Settings for Solar Showroom
$pdo->exec("DELETE FROM `company_settings`;");
$pdo->prepare('INSERT INTO `company_settings` (
    `id`, `company_name`, `company_name_ar`, `tax_id`, `address`, `address_ar`,
    `phone`, `email`, `website`, `primary_color`, `secondary_color`, `theme_mode`,
    `business_mode`, `primary_currency_code`, `primary_currency_symbol`,
    `secondary_currency_code`, `secondary_currency_symbol`, `secondary_currency_rate`,
    `tax_enabled`, `tax_inclusive`, `default_tax_rate`, `receipt_header`, `receipt_footer`,
    `receipt_width`, `enable_customer_display`
) VALUES (
    1,
    :c_name, :c_name_ar, :tax_id, :addr, :addr_ar,
    :phone, :email, :website, :p_col, :s_col, :theme,
    :b_mode, :cur1, :sym1, :cur2, :sym2, :rate2,
    0, 0, 0.0, :rec_head, :rec_foot, "80mm", 1
)')->execute([
    ':c_name' => 'Al-Shams Solar Solutions',
    ':c_name_ar' => 'مؤسسة الشمس لحلول الطاقة الشمسية',
    ':tax_id' => 'SOLAR-LB-2026',
    ':addr' => 'Main Highway, Baalbeck, Bekaa, Lebanon',
    ':addr_ar' => 'طريق عام بعلبك، البقاع، لبنان',
    ':phone' => '+961 70 024 524',
    ':email' => 'solar@vendra.charafeddine.xyz',
    ':website' => 'https://vendra.charafeddine.xyz',
    ':p_col' => '#eab308', // Solar Amber
    ':s_col' => '#10b981', // Emerald
    ':theme' => 'dark',
    ':b_mode' => 'RETAIL',
    ':cur1' => 'USD',
    ':sym1' => '$',
    ':cur2' => 'LBP',
    ':sym2' => 'L.L.',
    ':rate2' => 89500.0000,
    ':rec_head' => "AL-SHAMS SOLAR ENERGY SOLUTIONS\nBaalbeck, Lebanon | +961 70 024 524\nSpecialists in Solar Inverters & Lithium Batteries",
    ':rec_foot' => "Official Warranty with Serial Registration.\nThank you for choosing renewable solar energy!\nPowered by Vendra Commercial POS",
]);

// 3. Staff Users
$passHashAdmin = password_hash('admin123', PASSWORD_BCRYPT);
$passHashManager = password_hash('manager123', PASSWORD_BCRYPT);
$passHashCashier = password_hash('cashier123', PASSWORD_BCRYPT);

$pinHashAdmin = password_hash('1234', PASSWORD_BCRYPT);
$pinHashManager = password_hash('2222', PASSWORD_BCRYPT);
$pinHashCashier = password_hash('0000', PASSWORD_BCRYPT);

$pdo->exec("DELETE FROM `users`;");
$pdo->prepare('INSERT INTO `users` (`id`, `username`, `password_hash`, `pin_code_hash`, `full_name`, `email`, `phone`, `role`, `is_active`) VALUES
(1, "admin", :p1, :pin1, "Showroom Owner (Admin)", "owner@solar-baalbeck.com", "+961 70 024 524", "ADMIN", 1),
(2, "manager", :p2, :pin2, "Sales Engineer", "sales@solar-baalbeck.com", "+961 70 024 524", "MANAGER", 1),
(3, "cashier", :p3, :pin3, "Front Desk Cashier", "cashier@solar-baalbeck.com", "+961 70 024 524", "CASHIER", 1)
')->execute([
    ':p1' => $passHashAdmin, ':pin1' => $pinHashAdmin,
    ':p2' => $passHashManager, ':pin2' => $pinHashManager,
    ':p3' => $passHashCashier, ':pin3' => $pinHashCashier,
]);

// 4. Units & Tax Rates
$pdo->exec("DELETE FROM `units`;");
$pdo->exec("INSERT INTO `units` (`id`, `code`, `name`, `name_ar`, `is_fractional`) VALUES
(1, 'PCS', 'Piece', 'قطعة', 0),
(2, 'MTR', 'Meter', 'متر', 1),
(3, 'SET', 'Set / Kit', 'طقم / نظام متكامل', 0),
(4, 'ROLL', 'Roll / Reel', 'بكرة', 0)
");

$pdo->exec("DELETE FROM `tax_rates`;");
$pdo->exec("INSERT INTO `tax_rates` (`id`, `name`, `rate_percent`, `is_default`, `is_active`) VALUES
(1, 'Zero Tax', 0.0000, 1, 1),
(2, 'VAT 11%', 11.0000, 0, 1)
");

// 5. Warehouses
$pdo->exec("DELETE FROM `warehouses`;");
$pdo->exec("INSERT INTO `warehouses` (`id`, `code`, `name`, `location`, `is_default`, `is_active`) VALUES
(1, 'SHOWROOM-01', 'Baalbeck Main Showroom', 'Baalbeck Highway', 1, 1),
(2, 'CENTRAL-WH', 'Central Storage Warehouse', 'Baalbeck Industrial Area', 0, 1)
");

// 6. Categories for Solar Showroom
$pdo->exec("INSERT INTO `categories` (`id`, `name`, `name_ar`, `icon`, `color`, `sort_order`, `is_active`) VALUES
(1, 'Hybrid & Solar Inverters', 'مبدلات طاقة وإنفرترات', 'zap', '#f59e0b', 1, 1),
(2, 'Lithium & Gel Batteries', 'بطاريات ليثيوم وجل', 'battery-charging', '#10b981', 2, 1),
(3, 'Photovoltaic Solar Panels', 'ألواح الطاقة الشمسية', 'sun', '#3b82f6', 3, 1),
(4, 'DC Protection & Breakers', 'حمايات وقواطع دي سي', 'shield-check', '#ef4444', 4, 1),
(5, 'Solar Cables & Mounting', 'كابلات وشواسي ألمنيوم', 'cable', '#8b5cf6', 5, 1),
(6, 'Complete Solar Kits', 'أنظمة طاقة متكاملة', 'box', '#ec4899', 6, 1)
");

// 7. Products
$products = [
    // Inverters (with serials and warranty)
    [1, 'INV-DEYE-5KW', '697123456001', 'Deye 5kW Hybrid Inverter (SUN-5K-SG03LP1-EU)', 'إنفرتر داي هايبرد 5 كيلو واط', 1, 1, 1, 'STANDARD', 780.00, 890.00, 79655000.00, 60, 5],
    [2, 'INV-DEYE-8KW', '697123456002', 'Deye 8kW Hybrid Inverter (SUN-8K-SG01LP1-EU)', 'إنفرتر داي هايبرد 8 كيلو واط', 1, 1, 1, 'STANDARD', 1280.00, 1450.00, 129775000.00, 60, 4],
    [3, 'INV-FELICITY-5K', '697123456003', 'Felicity Solar 5kVA 48V Off-Grid Inverter', 'إنفرتر فيليسيتي 5 كيلو 48 فولت', 1, 1, 1, 'STANDARD', 430.00, 510.00, 45645000.00, 24, 8],
    [4, 'INV-GROWATT-6K', '697123456004', 'Growatt SPF 6000T DVM 6kW Inverter', 'إنفرتر غرووات 6 كيلو واط', 1, 1, 1, 'STANDARD', 620.00, 730.00, 65335000.00, 24, 6],

    // Lithium & Gel Batteries (with serials and warranty)
    [5, 'BAT-FEL-5KWH', '697123456005', 'Felicity LiFePO4 5.12kWh 48V 100Ah (Wall Mount)', 'بطارية ليثيوم فيليسيتي 5.12 كيلو واط', 2, 1, 1, 'STANDARD', 980.00, 1150.00, 102925000.00, 60, 10],
    [6, 'BAT-PYLON-48K', '697123456006', 'Pylontech US5000 4.8kWh Lithium Battery', 'بطارية ليثيوم بايلونتك 4.8 كيلو واط', 2, 1, 1, 'STANDARD', 1100.00, 1280.00, 114560000.00, 120, 6],
    [7, 'BAT-DYNESS-10K', '697123456007', 'Dyness Powerbox F-10 10.24kWh LiFePO4', 'بطارية ليثيوم داينس 10.24 كيلو واط', 2, 1, 1, 'STANDARD', 1980.00, 2300.00, 205850000.00, 120, 3],
    [8, 'BAT-TUBULAR-200', '697123456008', 'Tubular Gel Deep Cycle 200Ah 12V Battery', 'بطارية جل أنبوبية 200 أمبير 12 فولت', 2, 1, 1, 'STANDARD', 165.00, 195.00, 174525000.00, 12, 20],

    // Solar PV Panels
    [9, 'PNL-JINKO-585', '697123456009', 'Jinko Solar 585W N-Type TopCon Bifacial Panel', 'لوح طاقة شمسية جينكو 585 واط وجهين', 3, 1, 1, 'STANDARD', 98.00, 115.00, 10292500.00, 144, 120],
    [10, 'PNL-JASOLAR-570', '697123456010', 'JA Solar 570W Mono PERC Half-Cell Panel', 'لوح طاقة شمسية جي ايه 570 واط', 3, 1, 1, 'STANDARD', 89.00, 105.00, 9397500.00, 144, 80],
    [11, 'PNL-CANADIAN-650', '697123456011', 'Canadian Solar 650W HiKu7 Mono Panel', 'لوح كانيديان سولار 650 واط', 3, 1, 1, 'STANDARD', 115.00, 135.00, 12082500.00, 144, 60],

    // DC Protection & Switchgear
    [12, 'PRT-BREAKER-32A', '697123456012', 'Suntree DC Circuit Breaker 2P 500V 32A', 'قاطع دي سي سنتري 32 أمبير 500 فولت', 4, 1, 1, 'STANDARD', 8.00, 12.00, 1074000.00, 12, 60],
    [13, 'PRT-SURGE-600V', '697123456013', 'Suntree DC Surge Protector SPD 600V 40kA', 'حماية مانع صواعق دي سي سنتري 600 فولت', 4, 1, 1, 'STANDARD', 12.00, 18.00, 1611000.00, 12, 45],
    [14, 'PRT-FUSE-15A', '697123456014', 'Suntree 1000V DC Fuse 15A with Base', 'فيوز دي سي سنتري 15 أمبير مع قاعدة', 4, 1, 1, 'STANDARD', 3.50, 6.00, 537000.00, 0, 150],
    [15, 'PRT-ISOLATOR-250', '697123456015', 'Battery Heavy-Duty Isolator Switch 250A', 'مفتاح فصل بطاريات رئيسي 250 أمبير', 4, 1, 1, 'STANDARD', 22.00, 35.00, 3132500.00, 24, 30],

    // Cables & Mounting
    [16, 'CBL-SOLAR-6MM', '697123456016', 'TUV Certified Solar DC Cable 6mm² (Per Meter)', 'كابل طاقة شمسية 6 ملم أصلي (بالمتر)', 5, 2, 1, 'STANDARD', 0.85, 1.20, 107400.00, 0, 1200],
    [17, 'MNT-ALUM-42M', '697123456017', 'Aluminum Roof Mounting Rail Kit 4.2m', 'سكة تثبيت ألمنيوم 4.2 متر مع براغي', 5, 1, 1, 'STANDARD', 20.00, 28.00, 2506000.00, 120, 40],
    [18, 'CON-MC4-PAIR', '697123456018', 'MC4 Waterproof Connectors Pair IP68', 'كونكتر MC4 زوج ذكر وأنثى أصلي', 5, 1, 1, 'STANDARD', 0.80, 1.50, 134250.00, 0, 250],

    // Complete Solar System Kits
    [19, 'KIT-HYBRID-5KW', '697123456019', '5kW Residential Hybrid Solar Kit (Complete)', 'نظام هايبرد 5 كيلو متكامل مع بطارية ليثيوم و 8 ألواح', 6, 3, 1, 'RECIPE', 2450.00, 2850.00, 255075000.00, 60, 5],
    [20, 'KIT-VILLA-8KW', '697123456020', '8kW Villa Hybrid Solar Kit (Complete)', 'نظام فيلا 8 كيلو متكامل مع بطارية 10 كيلو و 14 لوح', 6, 3, 1, 'RECIPE', 4100.00, 4750.00, 425125000.00, 120, 3],
];

$stmtProd = $pdo->prepare('INSERT INTO `products` (
    `id`, `sku`, `barcode`, `name`, `name_ar`, `category_id`, `unit_id`, `tax_rate_id`,
    `type`, `cost_price`, `selling_price`, `secondary_selling_price`, `warranty_months`,
    `track_inventory`, `alert_quantity`, `is_active`
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 1)');

$stmtStock = $pdo->prepare('INSERT INTO `stock_inventory` (
    `warehouse_id`, `product_id`, `quantity`
) VALUES (1, ?, ?)');

foreach ($products as $p) {
    $stmtProd->execute([
        $p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6], $p[7],
        $p[8], $p[9], $p[10], $p[11], $p[12], $p[13]
    ]);
    $stmtStock->execute([$p[0], $p[13]]);
}

// 8. Pre-Seed Tracked Serial Numbers for Inverters and Lithium Batteries
$serials = [
    // Deye 5kW (Product ID 1)
    [1, 'DEYE5K-2026-9901', 60],
    [1, 'DEYE5K-2026-9902', 60],
    [1, 'DEYE5K-2026-9903', 60],
    [1, 'DEYE5K-2026-9904', 60],
    [1, 'DEYE5K-2026-9905', 60],

    // Deye 8kW (Product ID 2)
    [2, 'DEYE8K-2026-8801', 60],
    [2, 'DEYE8K-2026-8802', 60],
    [2, 'DEYE8K-2026-8803', 60],
    [2, 'DEYE8K-2026-8804', 60],

    // Felicity 5.12kWh Battery (Product ID 5)
    [5, 'FEL-BAT5K-1001-LB', 60],
    [5, 'FEL-BAT5K-1002-LB', 60],
    [5, 'FEL-BAT5K-1003-LB', 60],
    [5, 'FEL-BAT5K-1004-LB', 60],
    [5, 'FEL-BAT5K-1005-LB', 60],

    // Pylontech US5000 (Product ID 6)
    [6, 'PYLON-US5K-4001', 120],
    [6, 'PYLON-US5K-4002', 120],
    [6, 'PYLON-US5K-4003', 120],
    [6, 'PYLON-US5K-4004', 120],

    // Dyness Powerbox F-10 (Product ID 7)
    [7, 'DYNESS-F10-7701', 120],
    [7, 'DYNESS-F10-7702', 120],
    [7, 'DYNESS-F10-7703', 120],
];

$stmtSerial = $pdo->prepare('INSERT INTO `product_serials` (
    `product_id`, `serial_number`, `status`, `warranty_months`
) VALUES (?, ?, "AVAILABLE", ?)');

foreach ($serials as $s) {
    $stmtSerial->execute($s);
}

// 9. Pre-Seed Bill of Materials (BOM) for Complete Kits
$recipes = [
    // Kit 19 (5kW Kit)
    [19, 1, 1.0, 1],   // Deye 5kW Inverter
    [19, 5, 1.0, 1],   // Felicity Lithium Battery
    [19, 9, 8.0, 1],   // 8x Jinko 585W Panels
    [19, 12, 2.0, 1],  // 2x DC Breaker
    [19, 13, 1.0, 1],  // 1x Surge SPD
    [19, 16, 50.0, 2], // 50m DC Cable

    // Kit 20 (8kW Kit)
    [20, 2, 1.0, 1],   // Deye 8kW Inverter
    [20, 7, 1.0, 1],   // Dyness 10.24kWh Lithium
    [20, 9, 14.0, 1],  // 14x Jinko 585W Panels
    [20, 12, 3.0, 1],  // 3x DC Breaker
    [20, 13, 2.0, 1],  // 2x Surge SPD
    [20, 16, 100.0, 2],// 100m DC Cable
];

$stmtRecipe = $pdo->prepare('INSERT INTO `product_recipes` (
    `parent_product_id`, `ingredient_product_id`, `quantity`, `unit_id`
) VALUES (?, ?, ?, ?)');

foreach ($recipes as $r) {
    $stmtRecipe->execute($r);
}

// 10. Customers
$customers = [
    ['Ali Mortada (Solar Contractor)', '+961 71 112 233', 'ali.mortada@gmail.com', 'Douris, Baalbeck', 1500.00, 5000.00],
    ['Hussein Chamas (Villa System)', '+961 70 334 455', 'h.chamas@hotmail.com', 'Ras Al-Ain, Baalbeck', 0.00, 2000.00],
    ['Green Energy Bekaa', '+961 8 370 120', 'info@greenenergy-bekaa.com', 'Zahle - Baalbeck Highway', 2800.00, 10000.00],
    ['Cash Client (Showroom)', '+961 70 000 000', '', 'Baalbeck', 0.00, 0.00],
];

$stmtCust = $pdo->prepare('INSERT INTO `customers` (
    `name`, `phone`, `email`, `address`, `current_balance`, `credit_limit`, `is_active`
) VALUES (?, ?, ?, ?, ?, ?, 1)');

foreach ($customers as $c) {
    $stmtCust->execute($c);
}

// 11. Initial Open Shift
$pdo->prepare('INSERT INTO `shifts` (
    `id`, `cashier_id`, `terminal_id`, `opened_at`, `opening_float`, `status`
) VALUES (1, 1, "TERM-01", datetime("now"), 200.00, "OPEN")')->execute();

$pdo->exec("PRAGMA foreign_keys = ON;");

echo "Successfully generated Solar Showroom SQLite database!\n";
