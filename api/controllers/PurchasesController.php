<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;

class PurchasesController
{
    public static function suppliers(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT * FROM `suppliers` WHERE `is_active` = 1 ORDER BY `company_name` ASC');
        Response::success(['suppliers' => $stmt->fetchAll()]);
    }

    public static function saveSupplier(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);
        $pdo = Database::getInstance();
        $id = !empty($payload['id']) ? (int)$payload['id'] : null;

        $name = trim((string)($payload['company_name'] ?? ''));
        $contact = $payload['contact_person'] ?? null;
        $phone = $payload['phone'] ?? null;
        $email = $payload['email'] ?? null;
        $address = $payload['address'] ?? null;
        $taxId = $payload['tax_id'] ?? null;

        if (empty($name)) {
            Response::error('Supplier company name is required.', 400);
        }

        if ($id) {
            $stmt = $pdo->prepare('UPDATE `suppliers` SET `company_name` = :n, `contact_person` = :c, `phone` = :p, `email` = :e, `address` = :a, `tax_id` = :t WHERE `id` = :id');
            $stmt->execute([':n' => $name, ':c' => $contact, ':p' => $phone, ':e' => $email, ':a' => $address, ':t' => $taxId, ':id' => $id]);
            $supplierId = $id;
        } else {
            $stmt = $pdo->prepare('INSERT INTO `suppliers` (`company_name`, `contact_person`, `phone`, `email`, `address`, `tax_id`) VALUES (:n, :c, :p, :e, :a, :t)');
            $stmt->execute([':n' => $name, ':c' => $contact, ':p' => $phone, ':e' => $email, ':a' => $address, ':t' => $taxId]);
            $supplierId = (int)$pdo->lastInsertId();
        }

        Response::success(['supplier_id' => $supplierId], 200, 'Supplier saved.');
    }

    public static function list(array $payload): void
    {
        $pdo = Database::getInstance();
        $sql = 'SELECT po.*, s.company_name as supplier_name, w.name as warehouse_name
                FROM `purchase_orders` po
                JOIN `suppliers` s ON po.supplier_id = s.id
                JOIN `warehouses` w ON po.warehouse_id = w.id
                ORDER BY po.id DESC';
        $stmt = $pdo->query($sql);
        Response::success(['purchase_orders' => $stmt->fetchAll()]);
    }

    public static function create(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);
        $supplierId = (int)($payload['supplier_id'] ?? 0);
        $warehouseId = (int)($payload['warehouse_id'] ?? 1);
        $items = $payload['items'] ?? [];
        $notes = $payload['notes'] ?? '';

        if ($supplierId <= 0 || empty($items)) {
            Response::error('Supplier and items are required.', 400);
        }

        $pdo = Database::getInstance();
        Database::beginTransaction();

        try {
            $poNumber = 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $totalAmount = 0.0;

            foreach ($items as $it) {
                $totalAmount += ((float)$it['quantity'] * (float)$it['unit_cost']);
            }

            $stmt = $pdo->prepare('INSERT INTO `purchase_orders` (`po_number`, `supplier_id`, `warehouse_id`, `status`, `total_amount`, `notes`) VALUES (:po, :sid, :wid, "PENDING", :tot, :notes)');
            $stmt->execute([
                ':po' => $poNumber,
                ':sid' => $supplierId,
                ':wid' => $warehouseId,
                ':tot' => $totalAmount,
                ':notes' => $notes,
            ]);
            $poId = (int)$pdo->lastInsertId();

            $stmtItem = $pdo->prepare('INSERT INTO `purchase_order_items` (`purchase_order_id`, `product_id`, `quantity_ordered`, `unit_cost`, `total_cost`) VALUES (:poid, :pid, :qty, :cost, :tot)');
            foreach ($items as $it) {
                $q = (float)$it['quantity'];
                $c = (float)$it['unit_cost'];
                $stmtItem->execute([
                    ':poid' => $poId,
                    ':pid' => (int)$it['product_id'],
                    ':qty' => $q,
                    ':cost' => $c,
                    ':tot' => $q * $c,
                ]);
            }

            Database::commit();

            Response::success(['purchase_order_id' => $poId, 'po_number' => $poNumber], 201, 'Purchase Order created.');
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::error('Failed to create purchase order: ' . $e->getMessage(), 500);
        }
    }

    public static function receiveGoods(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);
        $user = Auth::getCurrentUser();
        $userId = (int)($user['id'] ?? 1);
        $poId = (int)($payload['purchase_order_id'] ?? 0);

        $pdo = Database::getInstance();
        Database::beginTransaction();

        try {
            $stmtPo = $pdo->prepare('SELECT * FROM `purchase_orders` WHERE `id` = :id LIMIT 1');
            $stmtPo->execute([':id' => $poId]);
            $po = $stmtPo->fetch();

            if (!$po) {
                Response::error('Purchase order not found.', 404);
            }

            $warehouseId = (int)$po['warehouse_id'];

            $stmtItems = $pdo->prepare('SELECT * FROM `purchase_order_items` WHERE `purchase_order_id` = :id');
            $stmtItems->execute([':id' => $poId]);
            $items = $stmtItems->fetchAll();

            $stmtUpdItem = $pdo->prepare('UPDATE `purchase_order_items` SET `quantity_received` = `quantity_ordered` WHERE `id` = :id');
            $stmtStock = $pdo->prepare('UPDATE `stock_inventory` SET `quantity` = `quantity` + :q WHERE `product_id` = :pid AND `warehouse_id` = :wh');
            $stmtMov = $pdo->prepare('INSERT INTO `stock_movements` (`product_id`, `warehouse_id`, `movement_type`, `quantity`, `cost_price`, `reference_type`, `reference_id`, `notes`, `created_by`) VALUES (:pid, :wh, "PURCHASE", :q, :cost, "PO", :poid, "Goods Received from PO", :by)');

            foreach ($items as $item) {
                $pid = (int)$item['product_id'];
                $qty = (float)$item['quantity_ordered'];
                $cost = (float)$item['unit_cost'];

                $stmtUpdItem->execute([':id' => $item['id']]);

                // Check if stock_inventory exists
                $check = $pdo->prepare('SELECT `id` FROM `stock_inventory` WHERE `product_id` = :pid AND `warehouse_id` = :wh');
                $check->execute([':pid' => $pid, ':wh' => $warehouseId]);
                if ($check->fetch()) {
                    $stmtStock->execute([':q' => $qty, ':pid' => $pid, ':wh' => $warehouseId]);
                } else {
                    $ins = $pdo->prepare('INSERT INTO `stock_inventory` (`product_id`, `warehouse_id`, `quantity`) VALUES (:pid, :wh, :q)');
                    $ins->execute([':pid' => $pid, ':wh' => $warehouseId, ':q' => $qty]);
                }

                $stmtMov->execute([
                    ':pid' => $pid,
                    ':wh' => $warehouseId,
                    ':q' => $qty,
                    ':cost' => $cost,
                    ':poid' => $poId,
                    ':by' => $userId,
                ]);
            }

            $stmtPoUpd = $pdo->prepare('UPDATE `purchase_orders` SET `status` = "RECEIVED", `received_at` = datetime("now") WHERE `id` = :id');
            $stmtPoUpd->execute([':id' => $poId]);

            Database::commit();

            Response::success(null, 200, "Purchase Order #{$po['po_number']} marked as fully received into stock.");
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::error('Failed to receive goods: ' . $e->getMessage(), 500);
        }
    }

    public static function expenses(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT e.*, u.full_name as recorder_name FROM `expenses` e JOIN `users` u ON e.recorded_by = u.id ORDER BY e.id DESC LIMIT 100');
        Response::success(['expenses' => $stmt->fetchAll()]);
    }

    public static function addExpense(array $payload): void
    {
        $user = Auth::getCurrentUser();
        $userId = (int)($user['id'] ?? 1);

        $category = trim((string)($payload['category'] ?? 'General Expense'));
        $amount = (float)($payload['amount'] ?? 0);
        $payee = trim((string)($payload['payee'] ?? 'Vendor / Service'));
        $method = strtoupper((string)($payload['payment_method'] ?? 'CASH')); // CASH, CARD, BANK
        $notes = $payload['notes'] ?? '';

        if ($amount <= 0 || empty($payee)) {
            Response::error('Invalid amount or payee.', 400);
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('INSERT INTO `expenses` (`category`, `amount`, `payment_method`, `payee`, `notes`, `recorded_by`) VALUES (:cat, :amt, :meth, :payee, :notes, :by)');
        $stmt->execute([
            ':cat' => $category,
            ':amt' => $amount,
            ':meth' => $method,
            ':payee' => $payee,
            ':notes' => $notes,
            ':by' => $userId,
        ]);

        Response::success(null, 201, 'Expense recorded.');
    }
}
