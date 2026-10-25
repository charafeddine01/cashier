<?php
declare(strict_types=1);

namespace OmniPOS\Controllers;

use OmniPOS\Core\Auth;
use OmniPOS\Core\Database;
use OmniPOS\Core\Response;

class ProductsController
{
    public static function list(array $payload): void
    {
        $pdo = Database::getInstance();
        $search = trim((string)($payload['search'] ?? ''));
        $categoryId = !empty($payload['category_id']) ? (int)$payload['category_id'] : null;
        $type = !empty($payload['type']) ? (string)$payload['type'] : null;

        $sql = 'SELECT p.*, c.name as category_name, c.color as category_color, u.code as unit_code, u.is_fractional, tr.rate_percent as tax_rate_percent, COALESCE(si.quantity, 0) as stock_quantity
                FROM `products` p
                LEFT JOIN `categories` c ON p.category_id = c.id
                LEFT JOIN `units` u ON p.unit_id = u.id
                LEFT JOIN `tax_rates` tr ON p.tax_rate_id = tr.id
                LEFT JOIN `stock_inventory` si ON p.id = si.product_id AND si.variant_id IS NULL AND si.warehouse_id = 1
                WHERE p.is_active = 1';

        $params = [];

        if (!empty($search)) {
            $sql .= ' AND (p.name LIKE :s1 OR p.name_ar LIKE :s2 OR p.sku LIKE :s3 OR p.barcode LIKE :s4 OR p.scale_plu LIKE :s5)';
            $like = "%{$search}%";
            $params[':s1'] = $like;
            $params[':s2'] = $like;
            $params[':s3'] = $like;
            $params[':s4'] = $like;
            $params[':s5'] = $like;
        }

        if ($categoryId) {
            $sql .= ' AND p.category_id = :cid';
            $params[':cid'] = $categoryId;
        }

        if ($type) {
            $sql .= ' AND p.type = :type';
            $params[':type'] = $type;
        }

        $sql .= ' ORDER BY p.id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();

        Response::success(['products' => $products]);
    }

    public static function get(array $payload): void
    {
        $id = (int)($payload['id'] ?? 0);
        $pdo = Database::getInstance();

        $stmt = $pdo->prepare('SELECT * FROM `products` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $product = $stmt->fetch();

        if (!$product) {
            Response::error('Product not found.', 404);
        }

        // Fetch variants
        $stmtVar = $pdo->prepare('SELECT * FROM `product_variants` WHERE `product_id` = :pid');
        $stmtVar->execute([':pid' => $id]);
        $variants = $stmtVar->fetchAll();

        // Fetch recipes
        $stmtRec = $pdo->prepare('SELECT pr.*, p.name as ingredient_name, u.code as unit_code FROM `product_recipes` pr JOIN `products` p ON pr.ingredient_product_id = p.id JOIN `units` u ON pr.unit_id = u.id WHERE pr.parent_product_id = :pid');
        $stmtRec->execute([':pid' => $id]);
        $recipes = $stmtRec->fetchAll();

        // Fetch multi-units
        $stmtUnits = $pdo->prepare('SELECT pu.*, u.name as unit_name FROM `product_units` pu JOIN `units` u ON pu.unit_id = u.id WHERE pu.product_id = :pid');
        $stmtUnits->execute([':pid' => $id]);
        $units = $stmtUnits->fetchAll();

        Response::success([
            'product' => $product,
            'variants' => $variants,
            'recipes' => $recipes,
            'units' => $units,
        ]);
    }

    public static function save(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);

        $pdo = Database::getInstance();
        $id = !empty($payload['id']) ? (int)$payload['id'] : null;

        $sku = trim((string)($payload['sku'] ?? ''));
        $name = trim((string)($payload['name'] ?? ''));
        $nameAr = $payload['name_ar'] ?? null;
        $barcode = trim((string)($payload['barcode'] ?? '')) ?: null;
        $categoryId = !empty($payload['category_id']) ? (int)$payload['category_id'] : null;
        $unitId = (int)($payload['unit_id'] ?? 1);
        $taxRateId = !empty($payload['tax_rate_id']) ? (int)$payload['tax_rate_id'] : null;
        $type = $payload['type'] ?? 'STANDARD';
        $costPrice = (float)($payload['cost_price'] ?? 0);
        $sellingPrice = (float)($payload['selling_price'] ?? 0);
        $secPrice = (float)($payload['secondary_selling_price'] ?? ($sellingPrice * 0.92));
        $trackInventory = isset($payload['track_inventory']) ? (int)$payload['track_inventory'] : 1;
        $alertQty = (float)($payload['alert_quantity'] ?? 5);
        $scalePlu = trim((string)($payload['scale_plu'] ?? '')) ?: null;
        $imageUrl = $payload['image_url'] ?? null;
        $modifiers = !empty($payload['modifiers_schema']) ? (is_string($payload['modifiers_schema']) ? $payload['modifiers_schema'] : json_encode($payload['modifiers_schema'])) : null;
        $isKitchen = !empty($payload['is_kitchen_item']) ? 1 : 0;
        $kitchenStation = $payload['kitchen_station'] ?? 'Kitchen';
        $warrantyMonths = (int)($payload['warranty_months'] ?? 0);

        if (empty($name)) {
            Response::error('Product name is required.', 400);
        }

        if (empty($sku)) {
            $sku = 'SKU-' . strtoupper(bin2hex(random_bytes(4)));
        }

        Database::beginTransaction();

        try {
            if ($id) {
                $sql = 'UPDATE `products` SET
                    `sku` = :sku, `barcode` = :bc, `name` = :name, `name_ar` = :name_ar,
                    `category_id` = :cid, `unit_id` = :uid, `tax_rate_id` = :trid, `type` = :type,
                    `cost_price` = :cost, `selling_price` = :price, `secondary_selling_price` = :sec_price,
                    `track_inventory` = :track, `alert_quantity` = :alert, `scale_plu` = :plu,
                    `image_url` = :img, `modifiers_schema` = :modifiers, `is_kitchen_item` = :kitch,
                    `kitchen_station` = :station, `warranty_months` = :warr
                    WHERE `id` = :id';
                $params = [
                    ':sku' => $sku, ':bc' => $barcode, ':name' => $name, ':name_ar' => $nameAr,
                    ':cid' => $categoryId, ':uid' => $unitId, ':trid' => $taxRateId, ':type' => $type,
                    ':cost' => $costPrice, ':price' => $sellingPrice, ':sec_price' => $secPrice,
                    ':track' => $trackInventory, ':alert' => $alertQty, ':plu' => $scalePlu,
                    ':img' => $imageUrl, ':modifiers' => $modifiers, ':kitch' => $isKitchen,
                    ':station' => $kitchenStation, ':warr' => $warrantyMonths, ':id' => $id,
                ];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $productId = $id;
            } else {
                $sql = 'INSERT INTO `products` (
                    `sku`, `barcode`, `name`, `name_ar`, `category_id`, `unit_id`, `tax_rate_id`,
                    `type`, `cost_price`, `selling_price`, `secondary_selling_price`,
                    `track_inventory`, `alert_quantity`, `scale_plu`, `image_url`,
                    `modifiers_schema`, `is_kitchen_item`, `kitchen_station`, `warranty_months`
                ) VALUES (
                    :sku, :bc, :name, :name_ar, :cid, :uid, :trid,
                    :type, :cost, :price, :sec_price,
                    :track, :alert, :plu, :img,
                    :modifiers, :kitch, :station, :warr
                )';
                $params = [
                    ':sku' => $sku, ':bc' => $barcode, ':name' => $name, ':name_ar' => $nameAr,
                    ':cid' => $categoryId, ':uid' => $unitId, ':trid' => $taxRateId,
                    ':type' => $type, ':cost' => $costPrice, ':price' => $sellingPrice, ':sec_price' => $secPrice,
                    ':track' => $trackInventory, ':alert' => $alertQty, ':plu' => $scalePlu, ':img' => $imageUrl,
                    ':modifiers' => $modifiers, ':kitch' => $isKitchen, ':station' => $kitchenStation, ':warr' => $warrantyMonths,
                ];
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $productId = (int)$pdo->lastInsertId();

                // Initialize stock record
                $initialStock = (float)($payload['initial_stock'] ?? 10);
                $stmtStock = $pdo->prepare('INSERT INTO `stock_inventory` (`product_id`, `warehouse_id`, `quantity`) VALUES (:pid, 1, :qty)');
                $stmtStock->execute([':pid' => $productId, ':qty' => $initialStock]);
            }

            // Save variants if variable
            if ($type === 'VARIABLE' && !empty($payload['variants'])) {
                foreach ($payload['variants'] as $v) {
                    $vSku = $v['sku'] ?? ($sku . '-' . strtoupper(bin2hex(random_bytes(2))));
                    $vName = $v['variant_name'] ?? 'Variant';
                    $vCost = (float)($v['cost_price'] ?? $costPrice);
                    $vPrice = (float)($v['selling_price'] ?? $sellingPrice);
                    $vBc = $v['barcode'] ?? null;
                    $vMatrix = json_encode($v['attribute_matrix'] ?? []);

                    if (!empty($v['id'])) {
                        $stmtUpdVar = $pdo->prepare('UPDATE `product_variants` SET `sku` = :sku, `barcode` = :bc, `variant_name` = :vn, `cost_price` = :cp, `selling_price` = :sp WHERE `id` = :id');
                        $stmtUpdVar->execute([':sku' => $vSku, ':bc' => $vBc, ':vn' => $vName, ':cp' => $vCost, ':sp' => $vPrice, ':id' => $v['id']]);
                    } else {
                        $stmtInsVar = $pdo->prepare('INSERT INTO `product_variants` (`product_id`, `sku`, `barcode`, `variant_name`, `attribute_matrix`, `cost_price`, `selling_price`) VALUES (:pid, :sku, :bc, :vn, :mat, :cp, :sp)');
                        $stmtInsVar->execute([':pid' => $productId, ':sku' => $vSku, ':bc' => $vBc, ':vn' => $vName, ':mat' => $vMatrix, ':cp' => $vCost, ':sp' => $vPrice]);
                    }
                }
            }

            // Save recipes if combo
            if ($type === 'COMBO' && isset($payload['recipes'])) {
                $pdo->prepare('DELETE FROM `product_recipes` WHERE `parent_product_id` = :pid')->execute([':pid' => $productId]);
                $stmtInsRec = $pdo->prepare('INSERT INTO `product_recipes` (`parent_product_id`, `ingredient_product_id`, `quantity`, `unit_id`) VALUES (:ppid, :ipid, :qty, :uid)');
                foreach ($payload['recipes'] as $rec) {
                    $stmtInsRec->execute([
                        ':ppid' => $productId,
                        ':ipid' => (int)$rec['ingredient_product_id'],
                        ':qty' => (float)$rec['quantity'],
                        ':uid' => (int)($rec['unit_id'] ?? 1),
                    ]);
                }
            }

            Database::commit();

            Response::success(['product_id' => $productId], 200, 'Product saved successfully.');
        } catch (\Throwable $e) {
            Database::rollBack();
            Response::error('Failed to save product: ' . $e->getMessage(), 500);
        }
    }

    public static function delete(array $payload): void
    {
        Auth::requireRole(['ADMIN', 'MANAGER']);
        $id = (int)($payload['id'] ?? 0);
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE `products` SET `is_active` = 0 WHERE `id` = :id');
        $stmt->execute([':id' => $id]);

        Response::success(null, 200, 'Product deactivated.');
    }

    public static function categories(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT * FROM `categories` WHERE `is_active` = 1 ORDER BY `sort_order` ASC');
        Response::success(['categories' => $stmt->fetchAll()]);
    }

    public static function units(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT * FROM `units` ORDER BY `id` ASC');
        Response::success(['units' => $stmt->fetchAll()]);
    }

    public static function taxRates(array $payload): void
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT * FROM `tax_rates` WHERE `is_active` = 1');
        Response::success(['tax_rates' => $stmt->fetchAll()]);
    }
}
