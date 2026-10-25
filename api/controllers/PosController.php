<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;
use PDO;

class PosController
{
    public static function catalog(array $payload): void
    {
        $pdo = Database::getInstance();

        // 1. Categories
        $categories = $pdo->query('SELECT * FROM `categories` WHERE `is_active` = 1 ORDER BY `sort_order` ASC')->fetchAll();

        // 2. Units
        $units = $pdo->query('SELECT * FROM `units` ORDER BY `id` ASC')->fetchAll();

        // 3. Tax Rates
        $taxRates = $pdo->query('SELECT * FROM `tax_rates` WHERE `is_active` = 1')->fetchAll();

        // 4. Products with Stock Levels (Main Warehouse 1)
        $warehouseId = (int)($payload['warehouse_id'] ?? 1);
        $sql = 'SELECT p.*,
                       COALESCE(si.quantity, 0) as stock_quantity,
                       u.code as unit_code, u.is_fractional,
                       tr.rate_percent as tax_rate_percent
                FROM `products` p
                LEFT JOIN `stock_inventory` si ON p.id = si.product_id AND si.variant_id IS NULL AND si.warehouse_id = :wh
                LEFT JOIN `units` u ON p.unit_id = u.id
                LEFT JOIN `tax_rates` tr ON p.tax_rate_id = tr.id
                WHERE p.is_active = 1
                ORDER BY p.name ASC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':wh' => $warehouseId]);
        $products = $stmt->fetchAll();

        // 5. Variants
        $variants = $pdo->query('SELECT * FROM `product_variants` ORDER BY `id` ASC')->fetchAll();

        // 6. Multi-Unit Barcodes
        $productUnits = $pdo->query('SELECT pu.*, u.code as unit_code, u.name as unit_name FROM `product_units` pu JOIN `units` u ON pu.unit_id = u.id')->fetchAll();

        // 7. Pharmacy Batches (FEFO sorted by expiry_date ASC)
        $batches = $pdo->query('SELECT * FROM `product_batches` WHERE `quantity_remaining` > 0 ORDER BY `expiry_date` ASC')->fetchAll();

        Response::success([
            'categories' => $categories,
            'units' => $units,
            'tax_rates' => $taxRates,
            'products' => $products,
            'variants' => $variants,
            'product_units' => $productUnits,
            'batches' => $batches,
        ]);
    }

    public static function createOrder(array $payload): void
    {
        $user = Auth::getCurrentUser();
        $cashierId = (int)($user['id'] ?? 1);
        $terminalId = (string)($payload['terminal_id'] ?? 'TERM-01');

        $orderData = $payload['order'] ?? $payload;
        $items = $orderData['items'] ?? [];

        if (empty($items)) {
            Response::error('Cannot create order with an empty cart.', 400);
        }

        $idempotencyKey = trim((string)($orderData['idempotency_key'] ?? ''));
        if (empty($idempotencyKey)) {
            $idempotencyKey = 'IDEM-' . bin2hex(random_bytes(16));
        }

        $pdo = Database::getInstance();

        // Idempotency check
        $stmtCheck = $pdo->prepare('SELECT `id`, `invoice_number`, `grand_total` FROM `orders` WHERE `idempotency_key` = :k LIMIT 1');
        $stmtCheck->execute([':k' => $idempotencyKey]);
        $existing = $stmtCheck->fetch();
        if ($existing) {
            Response::success([
                'order_id' => $existing['id'],
                'invoice_number' => $existing['invoice_number'],
                'grand_total' => $existing['grand_total'],
                'is_duplicate_sync' => true,
            ], 200, 'Order already recorded (idempotent duplicate skipped).');
            return;
        }

        // Active shift lookup
        $stmtShift = $pdo->prepare('SELECT `id` FROM `shifts` WHERE `status` = "OPEN" AND (`cashier_id` = :cid OR `terminal_id` = :tid) ORDER BY `id` DESC LIMIT 1');
        $stmtShift->execute([':cid' => $cashierId, ':tid' => $terminalId]);
        $activeShift = $stmtShift->fetch();
        $shiftId = $activeShift ? (int)$activeShift['id'] : null;

        $warehouseId = (int)($orderData['warehouse_id'] ?? 1);
        $customerId = !empty($orderData['customer_id']) ? (int)$orderData['customer_id'] : 1;
        $orderType = $orderData['order_type'] ?? 'RETAIL';
        $tableId = !empty($orderData['table_id']) ? (int)$orderData['table_id'] : null;
        $notes = $orderData['notes'] ?? null;

        $subtotal = (float)($orderData['subtotal'] ?? 0);
        $taxTotal = (float)($orderData['tax_total'] ?? 0);
        $discountTotal = (float)($orderData['discount_total'] ?? 0);
        $grandTotal = (float)($orderData['grand_total'] ?? ($subtotal + $taxTotal - $discountTotal));

        $exchangeRate = (float)($orderData['exchange_rate'] ?? 0.92);
        $grandTotalSecondary = (float)($orderData['grand_total_secondary'] ?? ($grandTotal * $exchangeRate));

        $paidCash = (float)($orderData['paid_cash'] ?? 0);
        $paidCard = (float)($orderData['paid_card'] ?? 0);
        $paidCredit = (float)($orderData['paid_credit'] ?? 0);
        $paidOnAccount = (float)($orderData['paid_on_account'] ?? 0);
        $changeGiven = (float)($orderData['change_given'] ?? 0);
        $changeCurrency = $orderData['change_currency'] ?? 'PRIMARY';

        // Generate invoice number e.g. INV-20261025-XXXX
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        Database::beginTransaction();

        try {
            // 1. Insert Order
            $stmtOrder = $pdo->prepare('INSERT INTO `orders` (
                `invoice_number`, `shift_id`, `cashier_id`, `customer_id`, `warehouse_id`,
                `order_type`, `status`, `subtotal`, `tax_total`, `discount_total`, `grand_total`,
                `grand_total_secondary`, `exchange_rate`, `paid_cash`, `paid_card`, `paid_credit`,
                `paid_on_account`, `change_given`, `change_currency`, `table_id`, `kitchen_status`,
                `idempotency_key`, `terminal_id`, `notes`
            ) VALUES (
                :inv, :shift_id, :cashier_id, :cust_id, :wh_id,
                :order_type, "PAID", :subtotal, :tax_total, :discount_total, :grand_total,
                :grand_sec, :rate, :cash, :card, :credit,
                :on_acc, :change, :change_curr, :tbl, "PENDING",
                :idem, :tid, :notes
            )');

            $stmtOrder->execute([
                ':inv' => $invoiceNumber,
                ':shift_id' => $shiftId,
                ':cashier_id' => $cashierId,
                ':cust_id' => $customerId,
                ':wh_id' => $warehouseId,
                ':order_type' => $orderType,
                ':subtotal' => $subtotal,
                ':tax_total' => $taxTotal,
                ':discount_total' => $discountTotal,
                ':grand_total' => $grandTotal,
                ':grand_sec' => $grandTotalSecondary,
                ':rate' => $exchangeRate,
                ':cash' => $paidCash,
                ':card' => $paidCard,
                ':credit' => $paidCredit,
                ':on_acc' => $paidOnAccount,
                ':change' => $changeGiven,
                ':change_curr' => $changeCurrency,
                ':tbl' => $tableId,
                ':idem' => $idempotencyKey,
                ':tid' => $terminalId,
                ':notes' => $notes,
            ]);

            $orderId = (int)$pdo->lastInsertId();

            // 2. Insert Line Items & Atomic Stock Deduction
            $stmtItem = $pdo->prepare('INSERT INTO `order_items` (
                `order_id`, `product_id`, `variant_id`, `batch_id`, `unit_id`,
                `product_name`, `sku`, `barcode`, `quantity`, `unit_cost`,
                `unit_price`, `discount_amount`, `tax_rate`, `tax_amount`, `line_total`,
                `modifiers`, `notes`, `serial_numbers`, `kitchen_status`
            ) VALUES (
                :oid, :pid, :vid, :bid, :uid,
                :pname, :sku, :barcode, :qty, :cost,
                :price, :disc, :tax_rate, :tax_amt, :total,
                :modifiers, :inotes, :serials, :kstatus
            )');

            foreach ($items as $item) {
                $productId = (int)$item['product_id'];
                $variantId = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;
                $batchId = !empty($item['batch_id']) ? (int)$item['batch_id'] : null;
                $unitId = (int)($item['unit_id'] ?? 1);
                $qty = (float)$item['quantity'];
                $unitCost = (float)($item['unit_cost'] ?? 0);
                $unitPrice = (float)$item['unit_price'];
                $discountAmt = (float)($item['discount_amount'] ?? 0);
                $taxRate = (float)($item['tax_rate'] ?? 0);
                $taxAmt = (float)($item['tax_amount'] ?? 0);
                $lineTotal = (float)$item['line_total'];
                $modifiers = !empty($item['modifiers']) ? json_encode($item['modifiers']) : null;
                $serials = !empty($item['serial_numbers']) ? json_encode($item['serial_numbers']) : null;
                $kitchenStatus = !empty($item['is_kitchen_item']) ? 'PENDING' : 'READY';

                $stmtItem->execute([
                    ':oid' => $orderId,
                    ':pid' => $productId,
                    ':vid' => $variantId,
                    ':bid' => $batchId,
                    ':uid' => $unitId,
                    ':pname' => $item['product_name'] ?? 'Product',
                    ':sku' => $item['sku'] ?? 'SKU',
                    ':barcode' => $item['barcode'] ?? null,
                    ':qty' => $qty,
                    ':cost' => $unitCost,
                    ':price' => $unitPrice,
                    ':disc' => $discountAmt,
                    ':tax_rate' => $taxRate,
                    ':tax_amt' => $taxAmt,
                    ':total' => $lineTotal,
                    ':modifiers' => $modifiers,
                    ':inotes' => $item['notes'] ?? null,
                    ':serials' => $serials,
                    ':kstatus' => $kitchenStatus,
                ]);

                // Stock deduction
                self::deductStock($pdo, $productId, $variantId, $batchId, $warehouseId, $qty, $orderId, $cashierId);
            }

            // 3. Customer Ledger for On-Account (Debt) Payment
            if ($paidOnAccount > 0 && $customerId > 1) {
                $stmtCust = $pdo->prepare('SELECT `current_balance`, `loyalty_points` FROM `customers` WHERE `id` = :cid');
                $stmtCust->execute([':cid' => $customerId]);
                $cust = $stmtCust->fetch();
                $newBalance = (float)($cust['current_balance'] ?? 0) + $paidOnAccount;

                // Add points: 1 point per $10 spent
                $pointsEarned = floor($grandTotal / 10);
                $newPoints = (float)($cust['loyalty_points'] ?? 0) + $pointsEarned;

                $stmtUpdateCust = $pdo->prepare('UPDATE `customers` SET `current_balance` = :bal, `loyalty_points` = :pts WHERE `id` = :cid');
                $stmtUpdateCust->execute([':bal' => $newBalance, ':pts' => $newPoints, ':cid' => $customerId]);

                $stmtLedger = $pdo->prepare('INSERT INTO `customer_ledger` (
                    `customer_id`, `transaction_type`, `amount`, `balance_after`, `reference_type`, `reference_id`, `notes`
                ) VALUES (
                    :cid, "INVOICE", :amt, :ba, "ORDER", :oid, :notes
                )');
                $stmtLedger->execute([
                    ':cid' => $customerId,
                    ':amt' => $paidOnAccount,
                    ':ba' => $newBalance,
                    ':oid' => $orderId,
                    ':notes' => "Invoice {$invoiceNumber} credit charge",
                ]);
            }

            // 4. Update Shift Totals if Shift is Open
            if ($shiftId) {
                $actualCashNet = max(0, $paidCash - $changeGiven);
                $stmtShiftUpdate = $pdo->prepare('UPDATE `shifts` SET
                    `total_sales_cash` = `total_sales_cash` + :cash,
                    `total_sales_card` = `total_sales_card` + :card,
                    `total_sales_credit` = `total_sales_credit` + :credit,
                    `total_sales_on_account` = `total_sales_on_account` + :on_acc
                    WHERE `id` = :sid
                ');
                $stmtShiftUpdate->execute([
                    ':cash' => $actualCashNet,
                    ':card' => $paidCard,
                    ':credit' => $paidCredit,
                    ':on_acc' => $paidOnAccount,
                    ':sid' => $shiftId,
                ]);
            }

            // 5. Update Restaurant Table Status
            if ($tableId) {
                $stmtTable = $pdo->prepare('UPDATE `restaurant_tables` SET `status` = "OCCUPIED", `current_order_id` = :oid WHERE `id` = :tid');
                $stmtTable->execute([':oid' => $orderId, ':tid' => $tableId]);
            }

            Database::commit();

            Response::success([
                'order_id' => $orderId,
                'invoice_number' => $invoiceNumber,
                'grand_total' => $grandTotal,
                'grand_total_secondary' => $grandTotalSecondary,
                'change_given' => $changeGiven,
                'shift_id' => $shiftId,
            ], 201, 'Order created successfully');
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::error('Failed to create order: ' . $e->getMessage(), 500);
        }
    }

    private static function deductStock(PDO $pdo, int $productId, ?int $variantId, ?int $batchId, int $warehouseId, float $qty, int $orderId, int $cashierId): void
    {
        // Check product type
        $stmtP = $pdo->prepare('SELECT `type`, `track_inventory`, `cost_price` FROM `products` WHERE `id` = :id');
        $stmtP->execute([':id' => $productId]);
        $prod = $stmtP->fetch();

        if (!$prod || empty($prod['track_inventory'])) {
            return;
        }

        $cost = (float)($prod['cost_price'] ?? 0);

        // If Combo / Recipe, deduct BOM ingredients instead
        if ($prod['type'] === 'COMBO') {
            $stmtRec = $pdo->prepare('SELECT `ingredient_product_id`, `quantity` FROM `product_recipes` WHERE `parent_product_id` = :pid');
            $stmtRec->execute([':pid' => $productId]);
            $ingredients = $stmtRec->fetchAll();

            foreach ($ingredients as $ing) {
                $ingQty = (float)$ing['quantity'] * $qty;
                self::deductStock($pdo, (int)$ing['ingredient_product_id'], null, null, $warehouseId, $ingQty, $orderId, $cashierId);
            }
            return;
        }

        // Deduct from stock_inventory
        if ($variantId) {
            $stmtUpd = $pdo->prepare('UPDATE `stock_inventory` SET `quantity` = `quantity` - :qty WHERE `product_id` = :pid AND `variant_id` = :vid AND `warehouse_id` = :wh');
            $stmtUpd->execute([':qty' => $qty, ':pid' => $productId, ':vid' => $variantId, ':wh' => $warehouseId]);
        } else {
            $stmtUpd = $pdo->prepare('UPDATE `stock_inventory` SET `quantity` = `quantity` - :qty WHERE `product_id` = :pid AND `variant_id` IS NULL AND `warehouse_id` = :wh');
            $stmtUpd->execute([':qty' => $qty, ':pid' => $productId, ':wh' => $warehouseId]);
        }

        // Record stock movement
        $stmtMov = $pdo->prepare('INSERT INTO `stock_movements` (
            `product_id`, `variant_id`, `warehouse_id`, `movement_type`, `quantity`,
            `cost_price`, `reference_type`, `reference_id`, `notes`, `created_by`
        ) VALUES (
            :pid, :vid, :wh, "SALE", :qty,
            :cost, "ORDER", :oid, :notes, :by
        )');
        $stmtMov->execute([
            ':pid' => $productId,
            ':vid' => $variantId,
            ':wh' => $warehouseId,
            ':qty' => -$qty,
            ':cost' => $cost,
            ':oid' => $orderId,
            ':notes' => "POS Order Line Deduction",
            ':by' => $cashierId,
        ]);

        // Pharmacy FEFO deduction if batch tracked
        if ($batchId) {
            $stmtBatch = $pdo->prepare('UPDATE `product_batches` SET `quantity_remaining` = `quantity_remaining` - :qty WHERE `id` = :bid');
            $stmtBatch->execute([':qty' => $qty, ':bid' => $batchId]);
        }
    }

    public static function holdOrder(array $payload): void
    {
        $user = Auth::getCurrentUser();
        $cashierId = (int)($user['id'] ?? 1);
        $orderData = $payload['order'] ?? $payload;

        $holdData = [
            'id' => 'HOLD-' . bin2hex(random_bytes(6)),
            'title' => $orderData['title'] ?? ('Order #' . date('H:i:s')),
            'cashier_id' => $cashierId,
            'customer_name' => $orderData['customer_name'] ?? 'Walk-in Customer',
            'order_type' => $orderData['order_type'] ?? 'RETAIL',
            'items' => $orderData['items'] ?? [],
            'created_at' => time(),
        ];

        // Store held orders in SQLite or file / dedicated table
        // We can store held orders in `orders` with status='HELD'
        $pdo = Database::getInstance();
        $invoiceNumber = 'HELD-' . date('Ymd-His') . '-' . rand(100, 999);
        $grandTotal = (float)($orderData['grand_total'] ?? 0);

        $stmt = $pdo->prepare('INSERT INTO `orders` (
            `invoice_number`, `cashier_id`, `warehouse_id`, `status`, `grand_total`,
            `idempotency_key`, `terminal_id`, `notes`
        ) VALUES (
            :inv, :cid, 1, "HELD", :gt, :idem, :tid, :notes
        )');
        $stmt->execute([
            ':inv' => $invoiceNumber,
            ':cid' => $cashierId,
            ':gt' => $grandTotal,
            ':idem' => 'HELD-' . bin2hex(random_bytes(16)),
            ':tid' => $payload['terminal_id'] ?? 'TERM-01',
            ':notes' => json_encode($holdData),
        ]);

        Response::success([
            'held_id' => $pdo->lastInsertId(),
            'invoice_number' => $invoiceNumber,
        ], 200, 'Order placed on hold');
    }

    public static function getHeld(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT `id`, `invoice_number`, `grand_total`, `notes`, `created_at` FROM `orders` WHERE `status` = "HELD" ORDER BY `id` DESC');
        $held = $stmt->fetchAll();

        $result = [];
        foreach ($held as $h) {
            $parsed = json_decode($h['notes'] ?? '{}', true) ?: [];
            $result[] = [
                'id' => $h['id'],
                'invoice_number' => $h['invoice_number'],
                'grand_total' => (float)$h['grand_total'],
                'created_at' => $h['created_at'],
                'order_data' => $parsed,
            ];
        }

        Response::success(['held_orders' => $result]);
    }

    public static function recallHeld(array $payload): void
    {
        $id = (int)($payload['held_id'] ?? 0);
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM `orders` WHERE `id` = :id AND `status` = "HELD" LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            Response::error('Held order not found.', 404);
        }

        $orderData = json_decode($row['notes'] ?? '{}', true) ?: [];

        // Delete or mark cancelled
        $stmtDel = $pdo->prepare('DELETE FROM `orders` WHERE `id` = :id');
        $stmtDel->execute([':id' => $id]);

        Response::success(['order' => $orderData], 200, 'Held order recalled');
    }

    public static function deleteHeld(array $payload): void
    {
        $id = (int)($payload['held_id'] ?? 0);
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('DELETE FROM `orders` WHERE `id` = :id AND `status` = "HELD"');
        $stmt->execute([':id' => $id]);

        Response::success(null, 200, 'Held order removed');
    }

    public static function syncBatch(array $payload): void
    {
        $orders = $payload['orders'] ?? [];
        $synced = 0;
        $duplicates = 0;
        $errors = [];

        foreach ($orders as $orderPayload) {
            try {
                // Call createOrder logic
                $idem = $orderPayload['idempotency_key'] ?? '';
                $pdo = Database::getInstance();
                $check = $pdo->prepare('SELECT `id` FROM `orders` WHERE `idempotency_key` = :k');
                $check->execute([':k' => $idem]);
                if ($check->fetch()) {
                    $duplicates++;
                    continue;
                }

                // If not duplicate, create it
                self::createOrderInternal($orderPayload, $payload['terminal_id'] ?? 'TERM-01');
                $synced++;
            } catch (\Throwable $e) {
                $errors[] = ['key' => $orderPayload['idempotency_key'] ?? 'unknown', 'error' => $e->getMessage()];
            }
        }

        Response::success([
            'synced_count' => $synced,
            'duplicate_count' => $duplicates,
            'errors' => $errors,
        ], 200, "Batch sync processed: {$synced} synced, {$duplicates} duplicate skips.");
    }

    private static function createOrderInternal(array $orderData, string $terminalId): void
    {
        $pdo = Database::getInstance();
        $items = $orderData['items'] ?? [];
        if (empty($items)) return;

        $idempotencyKey = $orderData['idempotency_key'] ?? ('IDEM-' . bin2hex(random_bytes(16)));
        $cashierId = (int)($orderData['cashier_id'] ?? 1);
        $warehouseId = (int)($orderData['warehouse_id'] ?? 1);
        $customerId = !empty($orderData['customer_id']) ? (int)$orderData['customer_id'] : 1;
        $orderType = $orderData['order_type'] ?? 'RETAIL';
        $subtotal = (float)($orderData['subtotal'] ?? 0);
        $taxTotal = (float)($orderData['tax_total'] ?? 0);
        $discountTotal = (float)($orderData['discount_total'] ?? 0);
        $grandTotal = (float)($orderData['grand_total'] ?? ($subtotal + $taxTotal - $discountTotal));
        $exchangeRate = (float)($orderData['exchange_rate'] ?? 0.92);
        $grandTotalSecondary = (float)($orderData['grand_total_secondary'] ?? ($grandTotal * $exchangeRate));
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        Database::beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO `orders` (
                `invoice_number`, `cashier_id`, `customer_id`, `warehouse_id`, `order_type`, `status`,
                `subtotal`, `tax_total`, `discount_total`, `grand_total`, `grand_total_secondary`,
                `exchange_rate`, `paid_cash`, `paid_card`, `idempotency_key`, `terminal_id`
            ) VALUES (
                :inv, :cid, :cust, :wh, :type, "PAID",
                :sub, :tax, :disc, :gt, :gts,
                :rate, :pcash, :pcard, :idem, :tid
            )');
            $stmt->execute([
                ':inv' => $invoiceNumber,
                ':cid' => $cashierId,
                ':cust' => $customerId,
                ':wh' => $warehouseId,
                ':type' => $orderType,
                ':sub' => $subtotal,
                ':tax' => $taxTotal,
                ':disc' => $discountTotal,
                ':gt' => $grandTotal,
                ':gts' => $grandTotalSecondary,
                ':rate' => $exchangeRate,
                ':pcash' => (float)($orderData['paid_cash'] ?? $grandTotal),
                ':pcard' => (float)($orderData['paid_card'] ?? 0),
                ':idem' => $idempotencyKey,
                ':tid' => $terminalId,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            foreach ($items as $item) {
                self::deductStock($pdo, (int)$item['product_id'], null, null, $warehouseId, (float)$item['quantity'], $orderId, $cashierId);
            }
            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public static function lookupInvoice(array $payload): void
    {
        $invoiceNumber = trim((string)($payload['invoice_number'] ?? ''));
        if (empty($invoiceNumber)) {
            Response::error('invoice_number is required.', 400);
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT o.*, u.full_name as cashier_name, c.name as customer_name, c.phone as customer_phone
                               FROM `orders` o
                               LEFT JOIN `users` u ON o.cashier_id = u.id
                               LEFT JOIN `customers` c ON o.customer_id = c.id
                               WHERE o.invoice_number = :inv LIMIT 1');
        $stmt->execute([':inv' => $invoiceNumber]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::error('Invoice not found.', 404);
        }

        $stmtItems = $pdo->prepare('SELECT oi.*, p.image_url FROM `order_items` oi LEFT JOIN `products` p ON oi.product_id = p.id WHERE oi.order_id = :oid');
        $stmtItems->execute([':oid' => $order['id']]);
        $items = $stmtItems->fetchAll();

        Response::success([
            'order' => $order,
            'items' => $items,
        ]);
    }
}
