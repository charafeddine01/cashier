<?php
declare(strict_types=1);

namespace OmniPOS\Core;

use OmniPOS\Controllers\AuthController;
use OmniPOS\Controllers\CustomersController;
use OmniPOS\Controllers\InventoryController;
use OmniPOS\Controllers\LicenseController;
use OmniPOS\Controllers\PosController;
use OmniPOS\Controllers\ProductsController;
use OmniPOS\Controllers\PurchasesController;
use OmniPOS\Controllers\ReportsController;
use OmniPOS\Controllers\RestaurantController;
use OmniPOS\Controllers\SettingsController;
use OmniPOS\Controllers\ShiftsController;

class Router
{
    private static array $publicActions = [
        'auth.login',
        'auth.pinLogin',
        'auth.usersList',
        'license.getStatus',
        'license.activate',
        'settings.get',
    ];

    private static array $actionMap = [
        // Auth
        'auth.login'              => [AuthController::class, 'login'],
        'auth.pinLogin'           => [AuthController::class, 'pinLogin'],
        'auth.switchCashier'      => [AuthController::class, 'switchCashier'],
        'auth.me'                 => [AuthController::class, 'me'],
        'auth.usersList'          => [AuthController::class, 'usersList'],

        // License
        'license.getStatus'       => [LicenseController::class, 'getStatus'],
        'license.activate'        => [LicenseController::class, 'activate'],
        'license.heartbeat'       => [LicenseController::class, 'heartbeat'],

        // Settings & White-Label
        'settings.get'            => [SettingsController::class, 'get'],
        'settings.update'         => [SettingsController::class, 'update'],
        'settings.uploadLogo'     => [SettingsController::class, 'uploadLogo'],

        // POS Register
        'pos.catalog'             => [PosController::class, 'catalog'],
        'pos.order.create'        => [PosController::class, 'createOrder'],
        'pos.order.hold'          => [PosController::class, 'holdOrder'],
        'pos.order.getHeld'       => [PosController::class, 'getHeld'],
        'pos.order.recallHeld'    => [PosController::class, 'recallHeld'],
        'pos.order.deleteHeld'    => [PosController::class, 'deleteHeld'],
        'pos.order.syncBatch'     => [PosController::class, 'syncBatch'],
        'pos.invoice.lookup'      => [PosController::class, 'lookupInvoice'],

        // Shifts & Cash Drawer
        'shifts.getCurrent'       => [ShiftsController::class, 'getCurrent'],
        'shifts.open'             => [ShiftsController::class, 'open'],
        'shifts.cashMovement'     => [ShiftsController::class, 'cashMovement'],
        'shifts.getXReport'       => [ShiftsController::class, 'getXReport'],
        'shifts.closeZReport'     => [ShiftsController::class, 'closeZReport'],

        // Returns & Refunds
        'returns.lookupInvoice'   => [ReturnsController::class, 'lookupInvoice'],
        'returns.process'         => [ReturnsController::class, 'process'],

        // Products & Master Data
        'products.list'           => [ProductsController::class, 'list'],
        'products.get'            => [ProductsController::class, 'get'],
        'products.save'           => [ProductsController::class, 'save'],
        'products.delete'         => [ProductsController::class, 'delete'],
        'products.categories'     => [ProductsController::class, 'categories'],
        'products.units'          => [ProductsController::class, 'units'],
        'products.taxRates'       => [ProductsController::class, 'taxRates'],

        // Inventory Management
        'inventory.warehouses'    => [InventoryController::class, 'warehouses'],
        'inventory.stockLevels'   => [InventoryController::class, 'stockLevels'],
        'inventory.adjust'        => [InventoryController::class, 'adjust'],
        'inventory.transfer'      => [InventoryController::class, 'transfer'],
        'inventory.batches'       => [InventoryController::class, 'batches'],
        'inventory.nearExpiry'    => [InventoryController::class, 'nearExpiry'],

        // Restaurant & Table Map & KDS
        'restaurant.floorsAndTables' => [RestaurantController::class, 'floorsAndTables'],
        'restaurant.saveFloorPlan'   => [RestaurantController::class, 'saveFloorPlan'],
        'restaurant.updateTableStatus' => [RestaurantController::class, 'updateTableStatus'],
        'restaurant.kdsOrders'       => [RestaurantController::class, 'kdsOrders'],
        'restaurant.kdsUpdateStatus' => [RestaurantController::class, 'kdsUpdateStatus'],
        'restaurant.kitchenTicket'   => [RestaurantController::class, 'kitchenTicket'],

        // Customers & Ledger
        'customers.list'          => [CustomersController::class, 'list'],
        'customers.get'           => [CustomersController::class, 'get'],
        'customers.save'          => [CustomersController::class, 'save'],
        'customers.ledger'        => [CustomersController::class, 'ledger'],
        'customers.payDebt'       => [CustomersController::class, 'payDebt'],

        // Purchases & Expenses
        'purchases.suppliers'     => [PurchasesController::class, 'suppliers'],
        'purchases.saveSupplier'  => [PurchasesController::class, 'saveSupplier'],
        'purchases.list'          => [PurchasesController::class, 'list'],
        'purchases.create'        => [PurchasesController::class, 'create'],
        'purchases.receiveGoods'  => [PurchasesController::class, 'receiveGoods'],
        'purchases.expenses'      => [PurchasesController::class, 'expenses'],
        'purchases.addExpense'    => [PurchasesController::class, 'addExpense'],

        // Reports & Analytics
        'reports.dashboard'       => [ReportsController::class, 'dashboard'],
        'reports.salesHourly'     => [ReportsController::class, 'salesHourly'],
        'reports.profitAndCogs'   => [ReportsController::class, 'profitAndCogs'],
        'reports.topProducts'     => [ReportsController::class, 'topProducts'],
        'reports.taxSummary'      => [ReportsController::class, 'taxSummary'],
        'reports.cashierPerformance' => [ReportsController::class, 'cashierPerformance'],
        'reports.auditLogs'       => [ReportsController::class, 'auditLogs'],
    ];

    public static function dispatch(array $envelope): void
    {
        $action = trim((string)($envelope['action'] ?? ''));
        $payload = $envelope['payload'] ?? [];
        $authToken = $envelope['auth_token'] ?? null;

        if (empty($action)) {
            Response::error('Missing "action" field in request envelope.', 400);
        }

        if (!isset(self::$actionMap[$action])) {
            Response::error("Unknown action '{$action}'.", 404, null, 'ACTION_NOT_FOUND');
        }

        // License enforcement on non-license actions
        if (!in_array($action, ['license.getStatus', 'license.activate'], true)) {
            LicenseGuard::verifyOrAbort();
        }

        // Authentication enforcement on private actions
        if (!in_array($action, self::$publicActions, true)) {
            Auth::authenticateRequest($authToken);
        }

        [$class, $method] = self::$actionMap[$action];
        $class::$method($payload);
    }
}
