<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;
use PDO;

class InstallmentsController
{
    /**
     * List all installment plans with customer info, progress, and next due date
     */
    public static function list(array $payload): void
    {
        $pdo = Database::getInstance();
        $status = strtoupper(trim((string)($payload['status'] ?? 'ALL')));
        $search = trim((string)($payload['search'] ?? ''));

        $sql = 'SELECT ip.*,
                       c.name as customer_name,
                       c.phone as customer_phone,
                       o.invoice_number,
                       o.created_at as order_date,
                       (SELECT COUNT(*) FROM `installment_schedule` s WHERE s.plan_id = ip.id AND s.status = "PAID") as paid_installments_count,
                       (SELECT s.due_date FROM `installment_schedule` s WHERE s.plan_id = ip.id AND s.status IN ("PENDING", "PARTIAL") ORDER BY s.installment_number ASC LIMIT 1) as next_due_date,
                       (SELECT s.remaining_amount FROM `installment_schedule` s WHERE s.plan_id = ip.id AND s.status IN ("PENDING", "PARTIAL") ORDER BY s.installment_number ASC LIMIT 1) as next_due_amount
                FROM `installment_plans` ip
                JOIN `customers` c ON ip.customer_id = c.id
                JOIN `orders` o ON ip.order_id = o.id
                WHERE 1=1';

        $params = [];

        if ($status !== 'ALL') {
            $sql .= ' AND ip.status = :st';
            $params[':st'] = $status;
        }

        if (!empty($search)) {
            $sql .= ' AND (c.name LIKE :s1 OR c.phone LIKE :s2 OR ip.plan_number LIKE :s3 OR o.invoice_number LIKE :s4)';
            $like = "%{$search}%";
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = $like;
            $params[':s4'] = $like;
        }

        $sql .= ' ORDER BY ip.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $plans = $stmt->fetchAll();

        Response::success(['plans' => $plans]);
    }

    /**
     * Get single installment plan schedule and history
     */
    public static function getSchedule(array $payload): void
    {
        $planId = (int)($payload['plan_id'] ?? 0);
        $orderId = (int)($payload['order_id'] ?? 0);

        if ($planId <= 0 && $orderId <= 0) {
            Response::error('plan_id or order_id is required.', 400);
        }

        $pdo = Database::getInstance();

        if ($planId > 0) {
            $stmtPlan = $pdo->prepare('SELECT ip.*, c.name as customer_name, c.phone as customer_phone, c.current_balance as customer_total_debt, o.invoice_number FROM `installment_plans` ip JOIN `customers` c ON ip.customer_id = c.id JOIN `orders` o ON ip.order_id = o.id WHERE ip.id = :pid LIMIT 1');
            $stmtPlan->execute([':pid' => $planId]);
        } else {
            $stmtPlan = $pdo->prepare('SELECT ip.*, c.name as customer_name, c.phone as customer_phone, c.current_balance as customer_total_debt, o.invoice_number FROM `installment_plans` ip JOIN `customers` c ON ip.customer_id = c.id JOIN `orders` o ON ip.order_id = o.id WHERE ip.order_id = :oid LIMIT 1');
            $stmtPlan->execute([':oid' => $orderId]);
        }

        $plan = $stmtPlan->fetch();
        if (!$plan) {
            Response::error('Installment plan not found.', 404);
        }

        $actualPlanId = (int)$plan['id'];

        // Get Schedule
        $stmtSched = $pdo->prepare('SELECT * FROM `installment_schedule` WHERE `plan_id` = :pid ORDER BY `installment_number` ASC');
        $stmtSched->execute([':pid' => $actualPlanId]);
        $schedule = $stmtSched->fetchAll();

        // Get Payment History
        $stmtPay = $pdo->prepare('SELECT p.*, u.full_name as cashier_name FROM `installment_payments` p LEFT JOIN `users` u ON p.cashier_id = u.id WHERE p.plan_id = :pid ORDER BY p.id DESC');
        $stmtPay->execute([':pid' => $actualPlanId]);
        $payments = $stmtPay->fetchAll();

        Response::success([
            'plan' => $plan,
            'schedule' => $schedule,
            'payments' => $payments,
        ]);
    }

    /**
     * Create a new installment plan linked to an order
     */
    public static function createPlan(array $payload): array
    {
        $pdo = Database::getInstance();

        $orderId = (int)($payload['order_id'] ?? 0);
        $customerId = (int)($payload['customer_id'] ?? 0);
        $totalAmount = (float)($payload['total_amount'] ?? 0);
        $downPayment = (float)($payload['down_payment'] ?? 0);
        $totalInstallments = max(1, (int)($payload['total_installments'] ?? 1));
        $frequency = strtoupper((string)($payload['frequency'] ?? 'MONTHLY')); // MONTHLY, WEEKLY
        $firstDueDate = trim((string)($payload['first_due_date'] ?? ''));
        $notes = trim((string)($payload['notes'] ?? ''));

        if ($orderId <= 0 || $customerId <= 0) {
            throw new \InvalidArgumentException('order_id and customer_id are required for installment plan.');
        }

        $remainingBalance = max(0, $totalAmount - $downPayment);
        $installmentAmount = round($remainingBalance / $totalInstallments, 2);

        $planNumber = 'PLAN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));

        // Insert Plan
        $stmtPlan = $pdo->prepare('INSERT INTO `installment_plans` (
            `order_id`, `customer_id`, `plan_number`, `total_amount`, `down_payment`,
            `remaining_balance`, `total_installments`, `installment_amount`, `frequency`,
            `status`, `notes`
        ) VALUES (
            :oid, :cid, :pnum, :tot, :down,
            :rem, :tinst, :iamt, :freq,
            "ACTIVE", :notes
        )');

        $stmtPlan->execute([
            ':oid' => $orderId,
            ':cid' => $customerId,
            ':pnum' => $planNumber,
            ':tot' => $totalAmount,
            ':down' => $downPayment,
            ':rem' => $remainingBalance,
            ':tinst' => $totalInstallments,
            ':iamt' => $installmentAmount,
            ':freq' => $frequency,
            ':notes' => $notes,
        ]);

        $planId = (int)$pdo->lastInsertId();

        // Generate Schedule
        $baseDate = !empty($firstDueDate) ? strtotime($firstDueDate) : strtotime('+1 month');
        if ($baseDate === false) {
            $baseDate = strtotime('+1 month');
        }

        $stmtSched = $pdo->prepare('INSERT INTO `installment_schedule` (
            `plan_id`, `installment_number`, `due_date`, `amount_due`, `amount_paid`,
            `remaining_amount`, `status`, `notes`
        ) VALUES (
            :pid, :num, :due, :due_amt, 0, :rem_amt, "PENDING", :notes
        )');

        $runningSum = 0;
        for ($i = 1; $i <= $totalInstallments; $i++) {
            // Adjust last installment for rounding pennies
            if ($i === $totalInstallments) {
                $currentDue = round($remainingBalance - $runningSum, 2);
            } else {
                $currentDue = $installmentAmount;
                $runningSum += $currentDue;
            }

            if ($frequency === 'WEEKLY') {
                $dueDate = date('Y-m-d', strtotime('+' . ($i - 1) . ' weeks', $baseDate));
            } else {
                $dueDate = date('Y-m-d', strtotime('+' . ($i - 1) . ' months', $baseDate));
            }

            $stmtSched->execute([
                ':pid' => $planId,
                ':num' => $i,
                ':due' => $dueDate,
                ':due_amt' => $currentDue,
                ':rem_amt' => $currentDue,
                ':notes' => "Installment {$i} of {$totalInstallments}",
            ]);
        }

        return [
            'plan_id' => $planId,
            'plan_number' => $planNumber,
            'remaining_balance' => $remainingBalance,
            'installment_amount' => $installmentAmount,
            'total_installments' => $totalInstallments,
        ];
    }

    /**
     * Record an installment payment
     */
    public static function recordPayment(array $payload): void
    {
        $user = Auth::getCurrentUser();
        $cashierId = (int)($user['id'] ?? 1);

        $planId = (int)($payload['plan_id'] ?? 0);
        $scheduleId = !empty($payload['schedule_id']) ? (int)$payload['schedule_id'] : 0;
        $amountPaid = (float)($payload['amount_paid'] ?? 0);
        $paymentMethod = strtoupper(trim((string)($payload['payment_method'] ?? 'CASH'))); // CASH, CARD, BANK
        $currency = strtoupper(trim((string)($payload['currency'] ?? 'USD')));
        $exchangeRate = (float)($payload['exchange_rate'] ?? 1.0);
        $notes = trim((string)($payload['notes'] ?? 'Installment payment'));

        if ($planId <= 0 || $amountPaid <= 0) {
            Response::error('Valid plan_id and amount_paid are required.', 400);
        }

        $pdo = Database::getInstance();
        Database::beginTransaction();

        try {
            // Lookup plan
            $stmtPlan = $pdo->prepare('SELECT ip.*, c.name as customer_name, c.current_balance FROM `installment_plans` ip JOIN `customers` c ON ip.customer_id = c.id WHERE ip.id = :pid LIMIT 1');
            $stmtPlan->execute([':pid' => $planId]);
            $plan = $stmtPlan->fetch();

            if (!$plan) {
                Response::error('Installment plan not found.', 404);
            }

            $customerId = (int)$plan['customer_id'];

            // Find target schedule item (either specified or oldest pending)
            if ($scheduleId > 0) {
                $stmtSched = $pdo->prepare('SELECT * FROM `installment_schedule` WHERE `id` = :sid AND `plan_id` = :pid LIMIT 1');
                $stmtSched->execute([':sid' => $scheduleId, ':pid' => $planId]);
            } else {
                $stmtSched = $pdo->prepare('SELECT * FROM `installment_schedule` WHERE `plan_id` = :pid AND `status` IN ("PENDING", "PARTIAL") ORDER BY `installment_number` ASC LIMIT 1');
                $stmtSched->execute([':pid' => $planId]);
            }

            $schedule = $stmtSched->fetch();
            if (!$schedule) {
                Response::error('No pending installments found for this plan.', 400);
            }

            $actualScheduleId = (int)$schedule['id'];
            $oldPaid = (float)$schedule['amount_paid'];
            $newPaid = $oldPaid + $amountPaid;
            $amountDue = (float)$schedule['amount_due'];
            $newRemaining = max(0, $amountDue - $newPaid);
            $newStatus = $newRemaining <= 0 ? 'PAID' : 'PARTIAL';

            // Update schedule item
            $stmtUpdSched = $pdo->prepare('UPDATE `installment_schedule` SET `amount_paid` = :paid, `remaining_amount` = :rem, `status` = :st, `paid_at` = datetime("now") WHERE `id` = :sid');
            $stmtUpdSched->execute([
                ':paid' => $newPaid,
                ':rem' => $newRemaining,
                ':st' => $newStatus,
                ':sid' => $actualScheduleId,
            ]);

            // Update plan remaining balance
            $oldPlanBalance = (float)$plan['remaining_balance'];
            $newPlanBalance = max(0, $oldPlanBalance - $amountPaid);
            $newPlanStatus = $newPlanBalance <= 0 ? 'COMPLETED' : 'ACTIVE';

            $stmtUpdPlan = $pdo->prepare('UPDATE `installment_plans` SET `remaining_balance` = :rem, `status` = :st, `updated_at` = datetime("now") WHERE `id` = :pid');
            $stmtUpdPlan->execute([
                ':rem' => $newPlanBalance,
                ':st' => $newPlanStatus,
                ':pid' => $planId,
            ]);

            // Insert payment receipt record
            $receiptNumber = 'INST-REC-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            $stmtPay = $pdo->prepare('INSERT INTO `installment_payments` (
                `schedule_id`, `plan_id`, `customer_id`, `receipt_number`, `amount_paid`,
                `currency`, `exchange_rate`, `payment_method`, `cashier_id`, `notes`
            ) VALUES (
                :sid, :pid, :cid, :rec, :amt,
                :curr, :rate, :method, :cashier, :notes
            )');

            $stmtPay->execute([
                ':sid' => $actualScheduleId,
                ':pid' => $planId,
                ':cid' => $customerId,
                ':rec' => $receiptNumber,
                ':amt' => $amountPaid,
                ':curr' => $currency,
                ':rate' => $exchangeRate,
                ':method' => $paymentMethod,
                ':cashier' => $cashierId,
                ':notes' => $notes,
            ]);

            // Update customer debt ledger
            $oldCustBalance = (float)$plan['current_balance'];
            $newCustBalance = $oldCustBalance - $amountPaid;

            $stmtUpdCust = $pdo->prepare('UPDATE `customers` SET `current_balance` = :nb WHERE `id` = :cid');
            $stmtUpdCust->execute([':nb' => $newCustBalance, ':cid' => $customerId]);

            $stmtLedger = $pdo->prepare('INSERT INTO `customer_ledger` (
                `customer_id`, `transaction_type`, `amount`, `balance_after`, `reference_type`, `notes`
            ) VALUES (
                :cid, "PAYMENT", :amt, :ba, "INSTALLMENT", :notes
            )');

            $stmtLedger->execute([
                ':cid' => $customerId,
                ':amt' => -$amountPaid,
                ':ba' => $newCustBalance,
                ':notes' => "Installment Payment #{$schedule['installment_number']} ({$receiptNumber}): {$notes}",
            ]);

            // If cash and active shift, update shift cash
            if (str_starts_with($paymentMethod, 'CASH')) {
                $stmtShift = $pdo->prepare('SELECT `id` FROM `shifts` WHERE `status` = "OPEN" AND `cashier_id` = :cid LIMIT 1');
                $stmtShift->execute([':cid' => $cashierId]);
                $shift = $stmtShift->fetch();
                if ($shift) {
                    $stmtShiftUpd = $pdo->prepare('UPDATE `shifts` SET `total_sales_cash` = `total_sales_cash` + :amt WHERE `id` = :sid');
                    $stmtShiftUpd->execute([':amt' => $amountPaid, ':sid' => $shift['id']]);
                }
            }

            Database::commit();

            Response::success([
                'receipt_number' => $receiptNumber,
                'amount_paid' => $amountPaid,
                'installment_number' => $schedule['installment_number'],
                'plan_remaining_balance' => $newPlanBalance,
                'customer_new_debt' => $newCustBalance,
                'plan_status' => $newPlanStatus,
                'installment_status' => $newStatus,
            ], 200, "Installment payment of {$amountPaid} {$currency} recorded successfully.");
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::error('Failed to record installment payment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Overdue & collection summary for dashboard
     */
    public static function overdueSummary(): void
    {
        $pdo = Database::getInstance();
        $today = date('Y-m-d');
        $weekLater = date('Y-m-d', strtotime('+7 days'));
        $monthLater = date('Y-m-d', strtotime('+30 days'));

        // Overdue count and amount
        $stmtOverdue = $pdo->prepare('SELECT COUNT(*) as count, COALESCE(SUM(remaining_amount), 0) as total_amount FROM `installment_schedule` WHERE `status` IN ("PENDING", "PARTIAL") AND `due_date` < :today');
        $stmtOverdue->execute([':today' => $today]);
        $overdue = $stmtOverdue->fetch();

        // Due this week
        $stmtWeek = $pdo->prepare('SELECT COUNT(*) as count, COALESCE(SUM(remaining_amount), 0) as total_amount FROM `installment_schedule` WHERE `status` IN ("PENDING", "PARTIAL") AND `due_date` >= :today AND `due_date` <= :week');
        $stmtWeek->execute([':today' => $today, ':week' => $weekLater]);
        $dueWeek = $stmtWeek->fetch();

        // Total active portfolio balance
        $stmtTotal = $pdo->query('SELECT COUNT(*) as active_plans, COALESCE(SUM(remaining_balance), 0) as total_portfolio FROM `installment_plans` WHERE `status` = "ACTIVE"');
        $portfolio = $stmtTotal->fetch();

        Response::success([
            'overdue' => $overdue,
            'due_this_week' => $dueWeek,
            'portfolio' => $portfolio,
        ]);
    }
}
