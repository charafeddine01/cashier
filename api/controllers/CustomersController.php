<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;

class CustomersController
{
    public static function list(array $payload): void
    {
        $pdo = Database::getInstance();
        $search = trim((string)($payload['search'] ?? ''));

        $sql = 'SELECT * FROM `customers` WHERE `is_active` = 1';
        $params = [];

        if (!empty($search)) {
            $sql .= ' AND (`name` LIKE :s1 OR `phone` LIKE :s2 OR `email` LIKE :s3)';
            $like = "%{$search}%";
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = $like;
        }

        $sql .= ' ORDER BY `id` ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::success(['customers' => $stmt->fetchAll()]);
    }

    public static function get(array $payload): void
    {
        $id = (int)($payload['id'] ?? 0);
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare('SELECT * FROM `customers` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $customer = $stmt->fetch();

        if (!$customer) {
            Response::error('Customer not found.', 404);
        }

        Response::success(['customer' => $customer]);
    }

    public static function save(array $payload): void
    {
        $pdo = Database::getInstance();
        $id = !empty($payload['id']) ? (int)$payload['id'] : null;

        $name = trim((string)($payload['name'] ?? ''));
        $phone = trim((string)($payload['phone'] ?? '')) ?: null;
        $email = trim((string)($payload['email'] ?? '')) ?: null;
        $address = trim((string)($payload['address'] ?? '')) ?: null;
        $taxNumber = trim((string)($payload['tax_number'] ?? '')) ?: null;
        $creditLimit = (float)($payload['credit_limit'] ?? 0);

        if (empty($name)) {
            Response::error('Customer name is required.', 400);
        }

        if ($id) {
            $stmt = $pdo->prepare('UPDATE `customers` SET `name` = :n, `phone` = :p, `email` = :e, `address` = :a, `tax_number` = :tn, `credit_limit` = :cl WHERE `id` = :id');
            $stmt->execute([
                ':n' => $name, ':p' => $phone, ':e' => $email, ':a' => $address, ':tn' => $taxNumber, ':cl' => $creditLimit, ':id' => $id
            ]);
            $customerId = $id;
        } else {
            $stmt = $pdo->prepare('INSERT INTO `customers` (`name`, `phone`, `email`, `address`, `tax_number`, `credit_limit`, `current_balance`, `loyalty_points`) VALUES (:n, :p, :e, :a, :tn, :cl, 0, 0)');
            $stmt->execute([
                ':n' => $name, ':p' => $phone, ':e' => $email, ':a' => $address, ':tn' => $taxNumber, ':cl' => $creditLimit
            ]);
            $customerId = (int)$pdo->lastInsertId();
        }

        Response::success(['customer_id' => $customerId], 200, 'Customer saved successfully.');
    }

    public static function ledger(array $payload): void
    {
        $customerId = (int)($payload['customer_id'] ?? 0);
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare('SELECT * FROM `customer_ledger` WHERE `customer_id` = :cid ORDER BY `id` DESC');
        $stmt->execute([':cid' => $customerId]);
        $ledger = $stmt->fetchAll();

        $stmtCust = $pdo->prepare('SELECT `id`, `name`, `current_balance`, `credit_limit`, `loyalty_points` FROM `customers` WHERE `id` = :cid LIMIT 1');
        $stmtCust->execute([':cid' => $customerId]);
        $customer = $stmtCust->fetch();

        Response::success([
            'customer' => $customer,
            'ledger' => $ledger,
        ]);
    }

    public static function payDebt(array $payload): void
    {
        $user = Auth::getCurrentUser();
        $cashierId = (int)($user['id'] ?? 1);
        $customerId = (int)($payload['customer_id'] ?? 0);
        $amount = (float)($payload['amount'] ?? 0);
        $paymentMethod = strtoupper((string)($payload['payment_method'] ?? 'CASH')); // CASH, CARD, BANK
        $notes = trim((string)($payload['notes'] ?? 'On-Account Debt Payment'));

        if ($customerId <= 0 || $amount <= 0) {
            Response::error('Invalid customer or payment amount.', 400);
        }

        $pdo = Database::getInstance();
        Database::beginTransaction();

        try {
            $stmtCust = $pdo->prepare('SELECT * FROM `customers` WHERE `id` = :id LIMIT 1');
            $stmtCust->execute([':id' => $customerId]);
            $customer = $stmtCust->fetch();

            if (!$customer) {
                Response::error('Customer not found.', 404);
            }

            $oldBalance = (float)$customer['current_balance'];
            $newBalance = $oldBalance - $amount;

            $stmtUpd = $pdo->prepare('UPDATE `customers` SET `current_balance` = :nb WHERE `id` = :id');
            $stmtUpd->execute([':nb' => $newBalance, ':id' => $customerId]);

            $stmtLedger = $pdo->prepare('INSERT INTO `customer_ledger` (`customer_id`, `transaction_type`, `amount`, `balance_after`, `reference_type`, `notes`) VALUES (:cid, "PAYMENT", :amt, :ba, "RECEIPT", :notes)');
            $stmtLedger->execute([
                ':cid' => $customerId,
                ':amt' => -$amount,
                ':ba' => $newBalance,
                ':notes' => "{$paymentMethod} payment: {$notes}",
            ]);

            // If cash and active shift, add to shift cash
            $stmtShift = $pdo->prepare('SELECT `id` FROM `shifts` WHERE `status` = "OPEN" AND `cashier_id` = :cid LIMIT 1');
            $stmtShift->execute([':cid' => $cashierId]);
            $shift = $stmtShift->fetch();
            if ($shift && $paymentMethod === 'CASH') {
                $stmtShiftUpd = $pdo->prepare('UPDATE `shifts` SET `total_sales_cash` = `total_sales_cash` + :amt WHERE `id` = :sid');
                $stmtShiftUpd->execute([':amt' => $amount, ':sid' => $shift['id']]);
            }

            Database::commit();

            Response::success([
                'customer_id' => $customerId,
                'amount_paid' => $amount,
                'previous_balance' => $oldBalance,
                'new_balance' => $newBalance,
            ], 200, "Payment of {$amount} recorded successfully.");
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::error('Failed to record customer payment: ' . $e->getMessage(), 500);
        }
    }
}
