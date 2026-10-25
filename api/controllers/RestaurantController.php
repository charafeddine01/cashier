<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;

class RestaurantController
{
    public static function floorsAndTables(array $payload): void
    {
        $pdo = Database::getInstance();

        $floors = $pdo->query('SELECT * FROM `restaurant_floors` WHERE `is_active` = 1 ORDER BY `sort_order` ASC')->fetchAll();

        $sql = 'SELECT t.*, o.invoice_number, o.grand_total as current_order_total, o.created_at as order_created_at
                FROM `restaurant_tables` t
                LEFT JOIN `orders` o ON t.current_order_id = o.id
                ORDER BY t.floor_id ASC, t.table_number ASC';
        $tables = $pdo->query($sql)->fetchAll();

        Response::success([
            'floors' => $floors,
            'tables' => $tables,
        ]);
    }

    public static function saveFloorPlan(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);
        $tables = $payload['tables'] ?? [];
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare('UPDATE `restaurant_tables` SET
            `position_x` = :x, `position_y` = :y, `width` = :w, `height` = :h,
            `shape` = :shape, `capacity` = :cap
            WHERE `id` = :id
        ');

        foreach ($tables as $t) {
            $stmt->execute([
                ':x' => (int)($t['position_x'] ?? 50),
                ':y' => (int)($t['position_y'] ?? 50),
                ':w' => (int)($t['width'] ?? 100),
                ':h' => (int)($t['height'] ?? 80),
                ':shape' => $t['shape'] ?? 'RECTANGLE',
                ':cap' => (int)($t['capacity'] ?? 4),
                ':id' => (int)$t['id'],
            ]);
        }

        Response::success(null, 200, 'Floor plan layout saved.');
    }

    public static function updateTableStatus(array $payload): void
    {
        $tableId = (int)($payload['table_id'] ?? 0);
        $status = strtoupper((string)($payload['status'] ?? 'AVAILABLE')); // AVAILABLE, OCCUPIED, BILL_PRINTED

        if ($tableId <= 0 || !in_array($status, ['AVAILABLE', 'OCCUPIED', 'BILL_PRINTED'], true)) {
            Response::error('Invalid table status parameters.', 400);
        }

        $pdo = Database::getInstance();
        $clearOrder = $status === 'AVAILABLE' ? ', `current_order_id` = NULL' : '';
        $stmt = $pdo->prepare("UPDATE `restaurant_tables` SET `status` = :st {$clearOrder} WHERE `id` = :id");
        $stmt->execute([':st' => $status, ':id' => $tableId]);

        Response::success(null, 200, "Table status updated to {$status}.");
    }

    public static function kdsOrders(array $payload): void
    {
        $pdo = Database::getInstance();
        $station = !empty($payload['station']) ? trim((string)$payload['station']) : null;

        // Fetch orders that have pending kitchen items
        $sql = 'SELECT o.id as order_id, o.invoice_number, o.created_at, o.order_type,
                       t.table_number, u.full_name as waiter_name
                FROM `orders` o
                LEFT JOIN `restaurant_tables` t ON o.table_id = t.id
                LEFT JOIN `users` u ON o.cashier_id = u.id
                WHERE o.kitchen_status != "SERVED" AND o.status = "PAID"
                ORDER BY o.id ASC';
        $orders = $pdo->query($sql)->fetchAll();

        $stmtItems = $pdo->prepare('SELECT oi.*, p.kitchen_station
                                   FROM `order_items` oi
                                   JOIN `products` p ON oi.product_id = p.id
                                   WHERE oi.order_id = :oid AND p.is_kitchen_item = 1');

        $activeKdsOrders = [];
        foreach ($orders as $ord) {
            $stmtItems->execute([':oid' => $ord['order_id']]);
            $rawItems = $stmtItems->fetchAll();

            $filteredItems = [];
            foreach ($rawItems as $ri) {
                if ($station && $ri['kitchen_station'] !== $station) {
                    continue;
                }
                $ri['modifiers_parsed'] = json_decode($ri['modifiers'] ?? '[]', true) ?: [];
                $filteredItems[] = $ri;
            }

            if (!empty($filteredItems)) {
                $ord['items'] = $filteredItems;
                $ord['elapsed_seconds'] = time() - strtotime($ord['created_at']);
                $activeKdsOrders[] = $ord;
            }
        }

        Response::success(['kds_orders' => $activeKdsOrders]);
    }

    public static function kdsUpdateStatus(array $payload): void
    {
        $orderId = (int)($payload['order_id'] ?? 0);
        $orderItemId = !empty($payload['order_item_id']) ? (int)$payload['order_item_id'] : null;
        $status = strtoupper((string)($payload['status'] ?? 'PREPARING')); // PENDING, PREPARING, READY, SERVED

        $pdo = Database::getInstance();

        if ($orderItemId) {
            $stmt = $pdo->prepare('UPDATE `order_items` SET `kitchen_status` = :st WHERE `id` = :id');
            $stmt->execute([':st' => $status, ':id' => $orderItemId]);
        } else if ($orderId) {
            $stmt = $pdo->prepare('UPDATE `orders` SET `kitchen_status` = :st WHERE `id` = :oid');
            $stmt->execute([':st' => $status, ':oid' => $orderId]);

            $stmt2 = $pdo->prepare('UPDATE `order_items` SET `kitchen_status` = :st WHERE `order_id` = :oid');
            $stmt2->execute([':st' => $status, ':oid' => $orderId]);
        }

        Response::success(null, 200, "Kitchen order status updated to {$status}.");
    }

    public static function kitchenTicket(array $payload): void
    {
        $orderId = (int)($payload['order_id'] ?? 0);
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare('SELECT o.*, t.table_number, u.full_name as server_name
                               FROM `orders` o
                               LEFT JOIN `restaurant_tables` t ON o.table_id = t.id
                               LEFT JOIN `users` u ON o.cashier_id = u.id
                               WHERE o.id = :id LIMIT 1');
        $stmt->execute([':id' => $orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::error('Order not found.', 404);
        }

        $stmtItems = $pdo->prepare('SELECT oi.*, p.kitchen_station FROM `order_items` oi JOIN `products` p ON oi.product_id = p.id WHERE oi.order_id = :oid AND p.is_kitchen_item = 1');
        $stmtItems->execute([':oid' => $orderId]);
        $items = $stmtItems->fetchAll();

        foreach ($items as &$it) {
            $it['modifiers_list'] = json_decode($it['modifiers'] ?? '[]', true) ?: [];
        }

        Response::success([
            'ticket_type' => 'KITCHEN ORDER TICKET (KOT)',
            'order' => $order,
            'items' => $items,
            'printed_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
