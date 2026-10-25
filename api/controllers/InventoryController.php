<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;

class InventoryController
{
    public static function warehouses(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT * FROM `warehouses` WHERE `is_active` = 1 ORDER BY `id` ASC');
        Response::success(['warehouses' => $stmt->fetchAll()]);
    }

    public static function stockLevels(array $payload): void
    {
        $pdo = Database::getInstance();
        $warehouseId = (int)($payload['warehouse_id'] ?? 1);
        $lowStockOnly = !empty($payload['low_stock_only']);

        $sql = 'SELECT p.id as product_id, p.name as product_name, p.sku, p.barcode, p.alert_quantity, p.cost_price, p.selling_price,
                       c.name as category_name, u.code as unit_code,
                       COALESCE(si.quantity, 0) as current_quantity,
                       CASE WHEN COALESCE(si.quantity, 0) <= p.alert_quantity THEN 1 ELSE 0 END as is_low_stock
                FROM `products` p
                LEFT JOIN `categories` c ON p.category_id = c.id
                LEFT JOIN `units` u ON p.unit_id = u.id
                LEFT JOIN `stock_inventory` si ON p.id = si.product_id AND si.variant_id IS NULL AND si.warehouse_id = :wh
                WHERE p.is_active = 1 AND p.track_inventory = 1';

        if ($lowStockOnly) {
            $sql .= ' AND COALESCE(si.quantity, 0) <= p.alert_quantity';
        }

        $sql .= ' ORDER BY is_low_stock DESC, p.name ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':wh' => $warehouseId]);
        $levels = $stmt->fetchAll();

        Response::success(['stock_levels' => $levels]);
    }

    public static function adjust(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);
        $user = Auth::getCurrentUser();
        $userId = (int)($user['id'] ?? 1);

        $productId = (int)($payload['product_id'] ?? 0);
        $warehouseId = (int)($payload['warehouse_id'] ?? 1);
        $adjustmentQty = (float)($payload['quantity'] ?? 0); // e.g. +5 or -2
        $reason = trim((string)($payload['reason'] ?? 'Manual Stock Adjustment'));

        if ($productId <= 0 || $adjustmentQty == 0) {
            Response::error('Invalid product or adjustment quantity.', 400);
        }

        $pdo = Database::getInstance();
        Database::beginTransaction();

        try {
            // Check existing stock
            $stmtCheck = $pdo->prepare('SELECT `id`, `quantity` FROM `stock_inventory` WHERE `product_id` = :pid AND `variant_id` IS NULL AND `warehouse_id` = :wh');
            $stmtCheck->execute([':pid' => $productId, ':wh' => $warehouseId]);
            $existing = $stmtCheck->fetch();

            if ($existing) {
                $newQty = (float)$existing['quantity'] + $adjustmentQty;
                $stmtUpd = $pdo->prepare('UPDATE `stock_inventory` SET `quantity` = :q WHERE `id` = :id');
                $stmtUpd->execute([':q' => $newQty, ':id' => $existing['id']]);
            } else {
                $newQty = max(0, $adjustmentQty);
                $stmtIns = $pdo->prepare('INSERT INTO `stock_inventory` (`product_id`, `warehouse_id`, `quantity`) VALUES (:pid, :wh, :q)');
                $stmtIns->execute([':pid' => $productId, ':wh' => $warehouseId, ':q' => $newQty]);
            }

            // Record movement
            $stmtMov = $pdo->prepare('INSERT INTO `stock_movements` (
                `product_id`, `warehouse_id`, `movement_type`, `quantity`, `reference_type`, `notes`, `created_by`
            ) VALUES (
                :pid, :wh, "ADJUSTMENT", :qty, "ADJUSTMENT", :notes, :by
            )');
            $stmtMov->execute([
                ':pid' => $productId,
                ':wh' => $warehouseId,
                ':qty' => $adjustmentQty,
                ':notes' => $reason,
                ':by' => $userId,
            ]);

            Database::commit();

            Response::success([
                'product_id' => $productId,
                'new_quantity' => $newQty,
                'adjusted_by' => $adjustmentQty,
            ], 200, 'Stock adjusted successfully.');
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::error('Failed to adjust stock: ' . $e->getMessage(), 500);
        }
    }

    public static function transfer(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);
        $user = Auth::getCurrentUser();
        $userId = (int)($user['id'] ?? 1);

        $productId = (int)($payload['product_id'] ?? 0);
        $fromWh = (int)($payload['from_warehouse_id'] ?? 0);
        $toWh = (int)($payload['to_warehouse_id'] ?? 0);
        $qty = (float)($payload['quantity'] ?? 0);
        $notes = trim((string)($payload['notes'] ?? 'Inter-warehouse transfer'));

        if ($productId <= 0 || $fromWh <= 0 || $toWh <= 0 || $fromWh === $toWh || $qty <= 0) {
            Response::error('Invalid transfer parameters.', 400);
        }

        $pdo = Database::getInstance();
        Database::beginTransaction();

        try {
            // Deduct from origin
            $stmtFrom = $pdo->prepare('UPDATE `stock_inventory` SET `quantity` = `quantity` - :q WHERE `product_id` = :pid AND `warehouse_id` = :wh');
            $stmtFrom->execute([':q' => $qty, ':pid' => $productId, ':wh' => $fromWh]);

            // Add to destination
            $stmtTo = $pdo->prepare('INSERT INTO `stock_inventory` (`product_id`, `warehouse_id`, `quantity`) VALUES (:pid, :wh, :q) ON DUPLICATE KEY UPDATE `quantity` = `quantity` + :q2');
            // For SQLite compatibility
            $checkTo = $pdo->prepare('SELECT `id` FROM `stock_inventory` WHERE `product_id` = :pid AND `warehouse_id` = :wh');
            $checkTo->execute([':pid' => $productId, ':wh' => $toWh]);
            if ($checkTo->fetch()) {
                $updTo = $pdo->prepare('UPDATE `stock_inventory` SET `quantity` = `quantity` + :q WHERE `product_id` = :pid AND `warehouse_id` = :wh');
                $updTo->execute([':q' => $qty, ':pid' => $productId, ':wh' => $toWh]);
            } else {
                $insTo = $pdo->prepare('INSERT INTO `stock_inventory` (`product_id`, `warehouse_id`, `quantity`) VALUES (:pid, :wh, :q)');
                $insTo->execute([':pid' => $productId, ':wh' => $toWh, ':q' => $qty]);
            }

            // Ledger
            $stmtMov = $pdo->prepare('INSERT INTO `stock_movements` (`product_id`, `warehouse_id`, `movement_type`, `quantity`, `reference_type`, `notes`, `created_by`) VALUES (:pid, :wh, :type, :q, "TRANSFER", :notes, :by)');
            $stmtMov->execute([':pid' => $productId, ':wh' => $fromWh, ':type' => 'TRANSFER_OUT', ':q' => -$qty, ':notes' => "Transfer to WH #{$toWh}: {$notes}", ':by' => $userId]);
            $stmtMov->execute([':pid' => $productId, ':wh' => $toWh, ':type' => 'TRANSFER_IN', ':q' => $qty, ':notes' => "Transfer from WH #{$fromWh}: {$notes}", ':by' => $userId]);

            Database::commit();

            Response::success(null, 200, "Stock transfer of {$qty} units completed.");
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::error('Failed to transfer stock: ' . $e->getMessage(), 500);
        }
    }

    public static function batches(array $payload): void
    {
        $pdo = Database::getInstance();
        $productId = !empty($payload['product_id']) ? (int)$payload['product_id'] : null;

        $sql = 'SELECT pb.*, p.name as product_name, p.sku, w.name as warehouse_name
                FROM `product_batches` pb
                JOIN `products` p ON pb.product_id = p.id
                JOIN `warehouses` w ON pb.warehouse_id = w.id';

        $params = [];
        if ($productId) {
            $sql .= ' WHERE pb.product_id = :pid';
            $params[':pid'] = $productId;
        }

        $sql .= ' ORDER BY pb.expiry_date ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::success(['batches' => $stmt->fetchAll()]);
    }

    public static function nearExpiry(array $payload): void
    {
        $pdo = Database::getInstance();
        $days = (int)($payload['days'] ?? 90);
        $cutoffDate = date('Y-m-d', strtotime("+{$days} days"));

        $stmt = $pdo->prepare('SELECT pb.*, p.name as product_name, p.sku, w.name as warehouse_name,
                                      julianday(pb.expiry_date) - julianday("now") as days_to_expiry
                               FROM `product_batches` pb
                               JOIN `products` p ON pb.product_id = p.id
                               JOIN `warehouses` w ON pb.warehouse_id = w.id
                               WHERE pb.quantity_remaining > 0 AND pb.expiry_date <= :cutoff
                               ORDER BY pb.expiry_date ASC');
        $stmt->execute([':cutoff' => $cutoffDate]);
        $batches = $stmt->fetchAll();

        Response::success([
            'near_expiry_batches' => $batches,
            'days_threshold' => $days,
        ]);
    }
}
