<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;

class ReturnsController
{
    public static function lookupInvoice(array $payload): void
    {
        $invoiceNumber = trim((string)($payload['invoice_number'] ?? ''));
        if (empty($invoiceNumber)) {
            Response::error('invoice_number is required.', 400);
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT o.*, u.full_name as cashier_name, c.name as customer_name
                               FROM `orders` o
                               LEFT JOIN `users` u ON o.cashier_id = u.id
                               LEFT JOIN `customers` c ON o.customer_id = c.id
                               WHERE o.invoice_number = :inv LIMIT 1');
        $stmt->execute([':inv' => $invoiceNumber]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::error('Invoice not found.', 404);
        }

        $stmtItems = $pdo->prepare('SELECT * FROM `order_items` WHERE `order_id` = :oid');
        $stmtItems->execute([':oid' => $order['id']]);
        $items = $stmtItems->fetchAll();

        // Check already returned quantities
        $stmtPrev = $pdo->prepare('SELECT `order_item_id`, SUM(`quantity`) as returned_qty FROM `order_return_items` ori JOIN `order_returns` orr ON ori.return_id = orr.id WHERE orr.original_order_id = :oid GROUP BY `order_item_id`');
        $stmtPrev->execute([':oid' => $order['id']]);
        $prevReturns = $stmtPrev->fetchAll();
        $returnedMap = [];
        foreach ($prevReturns as $pr) {
            $returnedMap[$pr['order_item_id']] = (float)$pr['returned_qty'];
        }

        foreach ($items as &$item) {
            $alreadyReturned = $returnedMap[$item['id']] ?? 0.0;
            $item['already_returned_qty'] = $alreadyReturned;
            $item['remaining_returnable_qty'] = max(0.0, (float)$item['quantity'] - $alreadyReturned);
        }

        Response::success([
            'order' => $order,
            'items' => $items,
        ]);
    }

    public static function process(array $payload): void
    {
        $user = Auth::getCurrentUser();
        $cashierId = (int)($user['id'] ?? 1);
        $orderId = (int)($payload['order_id'] ?? 0);
        $returnItems = $payload['items'] ?? [];
        $refundMethod = strtoupper((string)($payload['refund_method'] ?? 'CASH')); // CASH, CARD, STORE_CREDIT
        $reason = trim((string)($payload['reason'] ?? 'Customer Return'));

        if (empty($returnItems)) {
            Response::error('No items selected for return.', 400);
        }

        $pdo = Database::getInstance();

        $stmtOrder = $pdo->prepare('SELECT * FROM `orders` WHERE `id` = :id LIMIT 1');
        $stmtOrder->execute([':id' => $orderId]);
        $order = $stmtOrder->fetch();
        if (!$order) {
            Response::error('Original order not found.', 404);
        }

        $customerId = (int)($order['customer_id'] ?? 1);
        $warehouseId = (int)($order['warehouse_id'] ?? 1);

        // Active shift lookup
        $stmtShift = $pdo->prepare('SELECT `id` FROM `shifts` WHERE `status` = "OPEN" AND `cashier_id` = :cid ORDER BY `id` DESC LIMIT 1');
        $stmtShift->execute([':cid' => $cashierId]);
        $shift = $stmtShift->fetch();
        $shiftId = $shift ? (int)$shift['id'] : null;

        $returnNumber = 'RET-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $totalRefund = 0.0;

        Database::beginTransaction();

        try {
            // Calculate total refund
            foreach ($returnItems as $ri) {
                $qty = (float)($ri['quantity'] ?? 0);
                $unitPrice = (float)($ri['unit_price'] ?? 0);
                $totalRefund += ($qty * $unitPrice);
            }

            // Insert order_return record
            $stmtRet = $pdo->prepare('INSERT INTO `order_returns` (
                `return_number`, `original_order_id`, `shift_id`, `cashier_id`, `customer_id`,
                `total_refund`, `refund_method`, `reason`
            ) VALUES (
                :ret_num, :orig_id, :sid, :cid, :cust_id,
                :total, :method, :reason
            )');
            $stmtRet->execute([
                ':ret_num' => $returnNumber,
                ':orig_id' => $orderId,
                ':sid' => $shiftId,
                ':cid' => $cashierId,
                ':cust_id' => $customerId,
                ':total' => $totalRefund,
                ':method' => $refundMethod,
                ':reason' => $reason,
            ]);
            $returnId = (int)$pdo->lastInsertId();

            $stmtRetItem = $pdo->prepare('INSERT INTO `order_return_items` (
                `return_id`, `order_item_id`, `product_id`, `quantity`, `unit_price`, `refund_amount`, `restock_action`
            ) VALUES (
                :rid, :oiid, :pid, :qty, :price, :amt, :action
            )');

            foreach ($returnItems as $ri) {
                $orderItemId = (int)$ri['order_item_id'];
                $productId = (int)$ri['product_id'];
                $qty = (float)$ri['quantity'];
                $unitPrice = (float)$ri['unit_price'];
                $lineRefund = $qty * $unitPrice;
                $restockAction = ($ri['restock_action'] ?? 'RESTOCK') === 'WASTE_DAMAGED' ? 'WASTE_DAMAGED' : 'RESTOCK';

                $stmtRetItem->execute([
                    ':rid' => $returnId,
                    ':oiid' => $orderItemId,
                    ':pid' => $productId,
                    ':qty' => $qty,
                    ':price' => $unitPrice,
                    ':amt' => $lineRefund,
                    ':action' => $restockAction,
                ]);

                // Restock inventory if chosen
                if ($restockAction === 'RESTOCK') {
                    $stmtStock = $pdo->prepare('UPDATE `stock_inventory` SET `quantity` = `quantity` + :qty WHERE `product_id` = :pid AND `warehouse_id` = :wh');
                    $stmtStock->execute([':qty' => $qty, ':pid' => $productId, ':wh' => $warehouseId]);

                    $stmtMov = $pdo->prepare('INSERT INTO `stock_movements` (
                        `product_id`, `warehouse_id`, `movement_type`, `quantity`, `reference_type`, `reference_id`, `notes`, `created_by`
                    ) VALUES (
                        :pid, :wh, "RETURN", :qty, "RETURN", :rid, :notes, :by
                    )');
                    $stmtMov->execute([
                        ':pid' => $productId,
                        ':wh' => $warehouseId,
                        ':qty' => $qty,
                        ':rid' => $returnId,
                        ':notes' => "Customer return restocked",
                        ':by' => $cashierId,
                    ]);
                }
            }

            // If Store Credit, update customer ledger
            if ($refundMethod === 'STORE_CREDIT' && $customerId > 1) {
                $stmtCust = $pdo->prepare('SELECT `current_balance` FROM `customers` WHERE `id` = :cid');
                $stmtCust->execute([':cid' => $customerId]);
                $cust = $stmtCust->fetch();
                $newBalance = (float)($cust['current_balance'] ?? 0) - $totalRefund; // reduced debt or store credit

                $stmtUpd = $pdo->prepare('UPDATE `customers` SET `current_balance` = :nb WHERE `id` = :cid');
                $stmtUpd->execute([':nb' => $newBalance, ':cid' => $customerId]);

                $stmtLedger = $pdo->prepare('INSERT INTO `customer_ledger` (`customer_id`, `transaction_type`, `amount`, `balance_after`, `reference_type`, `reference_id`, `notes`) VALUES (:cid, "REFUND_CREDIT", :amt, :ba, "RETURN", :rid, :notes)');
                $stmtLedger->execute([
                    ':cid' => $customerId,
                    ':amt' => -$totalRefund,
                    ':ba' => $newBalance,
                    ':rid' => $returnId,
                    ':notes' => "Refund {$returnNumber} credited to store account",
                ]);
            }

            // If Cash refund and shift is active, record in shift returns
            if ($shiftId && $refundMethod === 'CASH') {
                $stmtShiftUpd = $pdo->prepare('UPDATE `shifts` SET `total_returns` = `total_returns` + :ret WHERE `id` = :sid');
                $stmtShiftUpd->execute([':ret' => $totalRefund, ':sid' => $shiftId]);
            }

            // Update order status
            $stmtOrderUpd = $pdo->prepare('UPDATE `orders` SET `status` = "PARTIALLY_REFUNDED" WHERE `id` = :oid');
            $stmtOrderUpd->execute([':oid' => $orderId]);

            Database::commit();

            Response::success([
                'return_id' => $returnId,
                'return_number' => $returnNumber,
                'total_refund' => $totalRefund,
                'refund_method' => $refundMethod,
            ], 200, "Refund of {$totalRefund} processed successfully.");
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::error('Failed to process refund: ' . $e->getMessage(), 500);
        }
    }
}
