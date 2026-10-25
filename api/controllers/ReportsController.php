<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;

class ReportsController
{
    public static function dashboard(array $payload): void
    {
        $pdo = Database::getInstance();

        // Today's Sales
        $stmtSales = $pdo->query('SELECT
            COUNT(*) as orders_count,
            COALESCE(SUM(grand_total), 0) as total_revenue,
            COALESCE(SUM(tax_total), 0) as total_tax,
            COALESCE(SUM(discount_total), 0) as total_discount,
            COALESCE(AVG(grand_total), 0) as avg_order_value
            FROM `orders`
            WHERE `status` = "PAID" AND DATE(created_at) = DATE("now")
        ');
        $salesToday = $stmtSales->fetch();

        // Low stock products count
        $stmtLowStock = $pdo->query('SELECT COUNT(*) as low_stock_count FROM `products` p LEFT JOIN `stock_inventory` si ON p.id = si.product_id AND si.warehouse_id = 1 WHERE p.is_active = 1 AND p.track_inventory = 1 AND COALESCE(si.quantity, 0) <= p.alert_quantity');
        $lowStock = $stmtLowStock->fetch();

        // Active shifts count
        $stmtShifts = $pdo->query('SELECT COUNT(*) as open_shifts FROM `shifts` WHERE `status` = "OPEN"');
        $shifts = $stmtShifts->fetch();

        // Customers count & total outstanding debt
        $stmtCust = $pdo->query('SELECT COUNT(*) as total_customers, COALESCE(SUM(current_balance), 0) as total_debt FROM `customers` WHERE `is_active` = 1');
        $cust = $stmtCust->fetch();

        Response::success([
            'sales_today' => [
                'revenue' => (float)$salesToday['total_revenue'],
                'orders_count' => (int)$salesToday['orders_count'],
                'tax' => (float)$salesToday['total_tax'],
                'discount' => (float)$salesToday['total_discount'],
                'avg_order_value' => (float)$salesToday['avg_order_value'],
            ],
            'low_stock_count' => (int)($lowStock['low_stock_count'] ?? 0),
            'open_shifts_count' => (int)($shifts['open_shifts'] ?? 0),
            'customers_count' => (int)($cust['total_customers'] ?? 0),
            'total_customer_debt' => (float)($cust['total_debt'] ?? 0),
        ]);
    }

    public static function salesHourly(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT
            strftime("%H", created_at) as hour,
            COUNT(*) as order_count,
            COALESCE(SUM(grand_total), 0) as hourly_revenue
            FROM `orders`
            WHERE `status` = "PAID" AND DATE(created_at) = DATE("now")
            GROUP BY hour
            ORDER BY hour ASC
        ');
        $rows = $stmt->fetchAll();

        // Fill 24-hour array
        $hourlyMap = [];
        for ($i = 0; $i < 24; $i++) {
            $h = sprintf('%02d', $i);
            $hourlyMap[$h] = ['hour' => "{$h}:00", 'order_count' => 0, 'revenue' => 0.0];
        }
        foreach ($rows as $r) {
            $h = $r['hour'];
            if (isset($hourlyMap[$h])) {
                $hourlyMap[$h]['order_count'] = (int)$r['order_count'];
                $hourlyMap[$h]['revenue'] = (float)$r['hourly_revenue'];
            }
        }

        Response::success(['hourly_sales' => array_values($hourlyMap)]);
    }

    public static function profitAndCogs(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);
        $pdo = Database::getInstance();

        $stmt = $pdo->query('SELECT
            COALESCE(SUM(oi.line_total), 0) as total_revenue,
            COALESCE(SUM(oi.quantity * oi.unit_cost), 0) as total_cogs
            FROM `order_items` oi
            JOIN `orders` o ON oi.order_id = o.id
            WHERE o.status = "PAID"
        ');
        $stats = $stmt->fetch();

        $revenue = (float)$stats['total_revenue'];
        $cogs = (float)$stats['total_cogs'];
        $profit = $revenue - $cogs;
        $marginPercent = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;

        Response::success([
            'total_revenue' => $revenue,
            'total_cogs' => $cogs,
            'gross_profit' => $profit,
            'profit_margin_percent' => $marginPercent,
        ]);
    }

    public static function topProducts(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT
            oi.product_name, oi.sku,
            COALESCE(SUM(oi.quantity), 0) as total_quantity_sold,
            COALESCE(SUM(oi.line_total), 0) as total_sales_revenue
            FROM `order_items` oi
            JOIN `orders` o ON oi.order_id = o.id
            WHERE o.status = "PAID"
            GROUP BY oi.product_id, oi.product_name, oi.sku
            ORDER BY total_sales_revenue DESC
            LIMIT 10
        ');
        Response::success(['top_products' => $stmt->fetchAll()]);
    }

    public static function taxSummary(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT
            oi.tax_rate,
            COALESCE(SUM(oi.line_total), 0) as taxable_amount,
            COALESCE(SUM(oi.tax_amount), 0) as tax_collected
            FROM `order_items` oi
            JOIN `orders` o ON oi.order_id = o.id
            WHERE o.status = "PAID"
            GROUP BY oi.tax_rate
            ORDER BY oi.tax_rate DESC
        ');
        Response::success(['tax_summary' => $stmt->fetchAll()]);
    }

    public static function cashierPerformance(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT
            u.id as user_id, u.full_name as cashier_name, u.role,
            COUNT(o.id) as orders_served,
            COALESCE(SUM(o.grand_total), 0) as total_sales_amount
            FROM `users` u
            LEFT JOIN `orders` o ON u.id = o.cashier_id AND o.status = "PAID"
            WHERE u.is_active = 1
            GROUP BY u.id, u.full_name, u.role
            ORDER BY total_sales_amount DESC
        ');
        Response::success(['cashier_performance' => $stmt->fetchAll()]);
    }

    public static function auditLogs(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT al.*, u.full_name as user_name FROM `audit_logs` al LEFT JOIN `users` u ON al.user_id = u.id ORDER BY al.id DESC LIMIT 100');
        Response::success(['audit_logs' => $stmt->fetchAll()]);
    }
}
