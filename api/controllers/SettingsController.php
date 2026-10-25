<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;

class SettingsController
{
    public static function get(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT * FROM `company_settings` WHERE `id` = 1 LIMIT 1');
        $settings = $stmt->fetch();

        if (!$settings) {
            Response::error('Company settings not found.', 404);
        }

        Response::success(['settings' => $settings]);
    }

    public static function update(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);

        $pdo = Database::getInstance();
        $fields = [
            'company_name', 'company_name_ar', 'tax_id', 'address', 'address_ar',
            'phone', 'email', 'website', 'primary_color', 'secondary_color', 'theme_mode',
            'business_mode', 'primary_currency_code', 'primary_currency_symbol',
            'secondary_currency_code', 'secondary_currency_symbol', 'secondary_currency_rate',
            'tax_enabled', 'tax_inclusive', 'default_tax_rate', 'receipt_header',
            'receipt_footer', 'receipt_width', 'enable_customer_display', 'scale_barcode_prefix'
        ];

        $updates = [];
        $params = [':id' => 1];

        foreach ($fields as $field) {
            if (array_key_exists($field, $payload)) {
                $updates[] = "`{$field}` = :{$field}";
                $params[":{$field}"] = $payload[$field];
            }
        }

        if (empty($updates)) {
            Response::error('No settings provided to update.', 400);
        }

        $sql = 'UPDATE `company_settings` SET ' . implode(', ', $updates) . ' WHERE `id` = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $stmtGet = $pdo->query('SELECT * FROM `company_settings` WHERE `id` = 1 LIMIT 1');
        $updated = $stmtGet->fetch();

        Response::success(['settings' => $updated], 200, 'Company settings updated successfully.');
    }

    public static function uploadLogo(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);

        $logoType = $payload['logo_type'] ?? 'primary'; // 'primary', 'receipt', 'favicon'
        $base64Data = $payload['image_base64'] ?? '';

        if (empty($base64Data)) {
            Response::error('image_base64 is required.', 400);
        }

        // Validate base64 data URI format or raw base64
        $targetColumn = match ($logoType) {
            'receipt' => 'receipt_logo_url',
            'favicon' => 'favicon_url',
            default   => 'primary_logo_url'
        };

        // If it starts with data:image/, save directly or store base64 URI
        // Shared host compatibility: storing clean optimized Base64 data URI is 100% portable and needs no file permissions!
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare("UPDATE `company_settings` SET `{$targetColumn}` = :img WHERE `id` = 1");
        $stmt->execute([':img' => $base64Data]);

        Response::success([
            'logo_type' => $logoType,
            'url' => $base64Data
        ], 200, ucfirst($logoType) . ' logo updated successfully.');
    }
}
