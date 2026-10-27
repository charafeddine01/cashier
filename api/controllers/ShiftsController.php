<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;
use OmniPOS\Core\BackupService;

class ShiftsController
{
    public static function getCurrent(array $payload): void
    {
        $user = Auth::getCurrentUser();
        $cashierId = (int)($user['id'] ?? 1);
        $terminalId = (string)($payload['terminal_id'] ?? 'TERM-01');

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT s.*, u.full_name as cashier_name
                               FROM `shifts` s
                               JOIN `users` u ON s.cashier_id = u.id
                               WHERE s.status = "OPEN" AND (s.cashier_id = :cid OR s.terminal_id = :tid)
                               ORDER BY s.id DESC LIMIT 1');
        $stmt->execute([':cid' => $cashierId, ':tid' => $terminalId]);
        $shift = $stmt->fetch();

        if (!$shift) {
            Response::success([
                'has_open_shift' => false,
                'shift' => null,
            ]);
        }

        // Fetch cash movements for this shift
        $stmtMov = $pdo->prepare('SELECT * FROM `cash_movements` WHERE `shift_id` = :sid ORDER BY `id` DESC');
        $stmtMov->execute([':sid' => $shift['id']]);
        $movements = $stmtMov->fetchAll();

        // Calculate expected drawer cash right now
        $openingFloat = (float)$shift['opening_float'];
        $cashSales = (float)$shift['total_sales_cash'];
        $cashIn = (float)$shift['total_cash_in'];
        $cashOut = (float)$shift['total_cash_out'];
        $returns = (float)$shift['total_returns'];

        $expectedDrawerCash = $openingFloat + $cashSales + $cashIn - $cashOut - $returns;

        Response::success([
            'has_open_shift' => true,
            'shift' => $shift,
            'movements' => $movements,
            'expected_drawer_cash' => $expectedDrawerCash,
        ]);
    }

    public static function open(array $payload): void
    {
        $user = Auth::getCurrentUser();
        $cashierId = (int)($user['id'] ?? 1);
        $terminalId = (string)($payload['terminal_id'] ?? 'TERM-01');
        $openingFloat = (float)($payload['opening_float'] ?? 0);
        $notes = $payload['notes'] ?? 'Morning shift opening';

        $pdo = Database::getInstance();

        // Check if shift already open
        $stmtCheck = $pdo->prepare('SELECT `id` FROM `shifts` WHERE `status` = "OPEN" AND `cashier_id` = :cid LIMIT 1');
        $stmtCheck->execute([':cid' => $cashierId]);
        if ($stmtCheck->fetch()) {
            Response::error('You already have an active open shift.', 400);
        }

        $now = date('Y-m-d H:i:s');
        $stmt = $pdo->prepare('INSERT INTO `shifts` (
            `cashier_id`, `terminal_id`, `opened_at`, `opening_float`, `expected_cash`,
            `status`, `notes`
        ) VALUES (
            :cid, :tid, :now, :float, :float, "OPEN", :notes
        )');
        $stmt->execute([
            ':cid' => $cashierId,
            ':tid' => $terminalId,
            ':now' => $now,
            ':float' => $openingFloat,
            ':notes' => $notes,
        ]);

        $shiftId = (int)$pdo->lastInsertId();

        Response::success([
            'shift_id' => $shiftId,
            'cashier_id' => $cashierId,
            'terminal_id' => $terminalId,
            'opened_at' => $now,
            'opening_float' => $openingFloat,
        ], 201, 'Shift opened successfully.');
    }

    public static function cashMovement(array $payload): void
    {
        $user = Auth::getCurrentUser();
        $cashierId = (int)($user['id'] ?? 1);
        $shiftId = (int)($payload['shift_id'] ?? 0);
        $type = strtoupper((string)($payload['type'] ?? ''));
        $amount = (float)($payload['amount'] ?? 0);
        $reason = trim((string)($payload['reason'] ?? ''));

        if (!in_array($type, ['CASH_IN', 'CASH_OUT'], true) || $amount <= 0 || empty($reason)) {
            Response::error('Invalid cash movement parameters.', 400);
        }

        $pdo = Database::getInstance();
        Database::beginTransaction();

        try {
            $stmtMov = $pdo->prepare('INSERT INTO `cash_movements` (`shift_id`, `cashier_id`, `type`, `amount`, `reason`) VALUES (:sid, :cid, :type, :amt, :reason)');
            $stmtMov->execute([
                ':sid' => $shiftId,
                ':cid' => $cashierId,
                ':type' => $type,
                ':amt' => $amount,
                ':reason' => $reason,
            ]);

            $column = $type === 'CASH_IN' ? 'total_cash_in' : 'total_cash_out';
            $stmtShift = $pdo->prepare("UPDATE `shifts` SET `{$column}` = `{$column}` + :amt WHERE `id` = :sid");
            $stmtShift->execute([':amt' => $amount, ':sid' => $shiftId]);

            Database::commit();

            Response::success(null, 200, "Cash movement {$type} of {$amount} recorded.");
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::error('Failed to record cash movement: ' . $e->getMessage(), 500);
        }
    }

    public static function getXReport(array $payload): void
    {
        $shiftId = (int)($payload['shift_id'] ?? 0);
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare('SELECT s.*, u.full_name as cashier_name FROM `shifts` s JOIN `users` u ON s.cashier_id = u.id WHERE s.id = :sid LIMIT 1');
        $stmt->execute([':sid' => $shiftId]);
        $shift = $stmt->fetch();

        if (!$shift) {
            Response::error('Shift not found.', 404);
        }

        // Get total orders count
        $stmtOrders = $pdo->prepare('SELECT COUNT(*) as total_orders, COALESCE(SUM(discount_total), 0) as total_discounts, COALESCE(SUM(tax_total), 0) as total_taxes FROM `orders` WHERE `shift_id` = :sid AND `status` = "PAID"');
        $stmtOrders->execute([':sid' => $shiftId]);
        $stats = $stmtOrders->fetch();

        $openingFloat = (float)$shift['opening_float'];
        $cashSales = (float)$shift['total_sales_cash'];
        $cashIn = (float)$shift['total_cash_in'];
        $cashOut = (float)$shift['total_cash_out'];
        $returns = (float)$shift['total_returns'];

        $expectedDrawerCash = $openingFloat + $cashSales + $cashIn - $cashOut - $returns;

        Response::success([
            'report_type' => 'X-REPORT (MID-SHIFT AUDIT)',
            'shift' => $shift,
            'summary' => [
                'opened_at' => $shift['opened_at'],
                'current_time' => date('Y-m-d H:i:s'),
                'cashier_name' => $shift['cashier_name'],
                'terminal_id' => $shift['terminal_id'],
                'opening_float' => $openingFloat,
                'total_sales_cash' => $cashSales,
                'total_sales_card' => (float)$shift['total_sales_card'],
                'total_sales_credit' => (float)$shift['total_sales_credit'],
                'total_sales_on_account' => (float)$shift['total_sales_on_account'],
                'total_gross_sales' => $cashSales + (float)$shift['total_sales_card'] + (float)$shift['total_sales_credit'] + (float)$shift['total_sales_on_account'],
                'total_cash_in' => $cashIn,
                'total_cash_out' => $cashOut,
                'total_returns' => $returns,
                'expected_cash_in_drawer' => $expectedDrawerCash,
                'orders_count' => (int)($stats['total_orders'] ?? 0),
                'total_discounts' => (float)($stats['total_discounts'] ?? 0),
                'total_taxes' => (float)($stats['total_taxes'] ?? 0),
            ]
        ]);
    }

    public static function closeZReport(array $payload): void
    {
        $shiftId = (int)($payload['shift_id'] ?? 0);
        $countedCash = (float)($payload['counted_cash'] ?? 0);
        $notes = $payload['notes'] ?? '';
        $denominations = !empty($payload['denominations']) ? json_encode($payload['denominations']) : null;

        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM `shifts` WHERE `id` = :sid AND `status` = "OPEN" LIMIT 1');
        $stmt->execute([':sid' => $shiftId]);
        $shift = $stmt->fetch();

        if (!$shift) {
            Response::error('Open shift not found.', 404);
        }

        $openingFloat = (float)$shift['opening_float'];
        $cashSales = (float)$shift['total_sales_cash'];
        $cashIn = (float)$shift['total_cash_in'];
        $cashOut = (float)$shift['total_cash_out'];
        $returns = (float)$shift['total_returns'];

        $expectedCash = $openingFloat + $cashSales + $cashIn - $cashOut - $returns;
        $variance = $countedCash - $expectedCash; // positive = overage, negative = shortage

        $now = date('Y-m-d H:i:s');
        $stmtClose = $pdo->prepare('UPDATE `shifts` SET
            `closed_at` = :now,
            `expected_cash` = :exp,
            `counted_cash` = :counted,
            `cash_difference` = :diff,
            `status` = "CLOSED",
            `notes` = :notes,
            `closing_breakdown` = :denoms
            WHERE `id` = :sid
        ');

        $stmtClose->execute([
            ':now' => $now,
            ':exp' => $expectedCash,
            ':counted' => $countedCash,
            ':diff' => $variance,
            ':notes' => $notes,
            ':denoms' => $denominations,
            ':sid' => $shiftId,
        ]);

        // Trigger automatic database backup & cloud sync on shift close
        BackupService::triggerShiftClose();

        Response::success([
            'report_type' => 'Z-REPORT (END OF SHIFT CLOSING)',
            'shift_id' => $shiftId,
            'closed_at' => $now,
            'opening_float' => $openingFloat,
            'cash_sales' => $cashSales,
            'card_sales' => (float)$shift['total_sales_card'],
            'expected_cash' => $expectedCash,
            'counted_cash' => $countedCash,
            'variance' => $variance,
            'status' => 'CLOSED',
        ], 200, 'Shift closed and Z-Report recorded.');
    }
}
