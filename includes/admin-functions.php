<?php
/**
 * YATIYA COUTURE - Centralized Administrator Business Logic Subsystem
 * Encapsulates Dashboard Metrics, Product & Variant CRUD, Inventory Adjustments,
 * Order Management, Customer Administration, Coupon Engine, CMS, Review Moderation,
 * Store Settings, and Secure Audit Trails.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

/* =====================================================================
   1. DASHBOARD METRICS & ANALYTICS
   ===================================================================== */

/**
 * Get comprehensive live metrics for the executive admin dashboard
 */
function get_admin_dashboard_metrics(): array {
    $pdo = db();

    // 1. Total revenue from completed/paid orders
    $revStmt = $pdo->query("SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE payment_status = 'paid'");
    $totalRevenue = (float)$revStmt->fetchColumn();

    // 2. Total orders count
    $orderCount = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    // 3. Pending orders count (placed, confirmed, processing)
    $pendingStmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('placed', 'confirmed', 'processing')");
    $pendingOrders = (int)$pendingStmt->fetchColumn();

    // 4. Completed/Delivered orders
    $compStmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'delivered'");
    $completedOrders = (int)$compStmt->fetchColumn();

    // 5. Total registered customers
    $totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $newRegistrations = (int)$pdo->query("SELECT COUNT(*) FROM customers WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

    // 6. Total active/draft products
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status != 'archived'")->fetchColumn();

    // 7. Low-stock products (<= low_stock_threshold and > 0)
    $lowStockStmt = $pdo->query("
        SELECT COUNT(DISTINCT id) FROM products 
        WHERE stock_quantity <= low_stock_threshold AND stock_quantity > 0 AND status = 'active'
    ");
    $lowStockProducts = (int)$lowStockStmt->fetchColumn();

    // 8. Out-of-stock products (= 0)
    $outOfStockStmt = $pdo->query("SELECT COUNT(DISTINCT id) FROM products WHERE stock_quantity = 0 AND status = 'active'");
    $outOfStockProducts = (int)$outOfStockStmt->fetchColumn();

    // 9. Recent orders (latest 8)
    $recentOrdersStmt = $pdo->query("
        SELECT o.*, c.first_name, c.last_name, c.email AS customer_email
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        ORDER BY o.placed_at DESC
        LIMIT 8
    ");
    $recentOrders = $recentOrdersStmt->fetchAll();

    // 10. Best-selling products (based on actual order_items)
    $bestSellersStmt = $pdo->query("
        SELECT 
            p.id, p.name, p.slug, p.sku, p.selling_price,
            SUM(oi.quantity) AS total_sold,
            SUM(oi.total_price) AS total_revenue,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image_url
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        JOIN orders o ON oi.order_id = o.id
        WHERE o.order_status NOT IN ('cancelled', 'returned')
        GROUP BY p.id, p.name, p.slug, p.sku, p.selling_price
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $bestSellers = $bestSellersStmt->fetchAll();

    // 11. Recent customer registrations (latest 5)
    $recentCustomersStmt = $pdo->query("
        SELECT id, first_name, last_name, email, phone, status, created_at
        FROM customers
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recentCustomers = $recentCustomersStmt->fetchAll();

    // 12. Low-stock alerts
    $lowStockAlertsStmt = $pdo->query("
        SELECT id, name, sku, stock_quantity, low_stock_threshold
        FROM products
        WHERE stock_quantity <= low_stock_threshold AND status = 'active'
        ORDER BY stock_quantity ASC
        LIMIT 6
    ");
    $lowStockAlerts = $lowStockAlertsStmt->fetchAll();

    // 13. Sales overview chart points (last 7 days)
    $salesChart = [];
    for ($i = 6; $i >= 0; $i--) {
        $dayDate = date('Y-m-d', strtotime("-$i days"));
        $dayLabel = date('M d', strtotime("-$i days"));

        $dayStmt = $pdo->prepare("
            SELECT COALESCE(SUM(grand_total), 0) AS daily_rev, COUNT(id) AS daily_orders
            FROM orders
            WHERE DATE(placed_at) = ? AND payment_status = 'paid'
        ");
        $dayStmt->execute([$dayDate]);
        $dayData = $dayStmt->fetch();

        $salesChart[] = [
            'date' => $dayDate,
            'label' => $dayLabel,
            'revenue' => (float)$dayData['daily_rev'],
            'orders' => (int)$dayData['daily_orders']
        ];
    }

    // 14. Order status distribution
    $statusDistStmt = $pdo->query("
        SELECT order_status, COUNT(id) AS status_count
        FROM orders
        GROUP BY order_status
    ");
    $statusDistribution = [];
    while ($row = $statusDistStmt->fetch()) {
        $statusDistribution[$row['order_status']] = (int)$row['status_count'];
    }

    return [
        'total_revenue' => $totalRevenue,
        'total_orders' => $orderCount,
        'pending_orders' => $pendingOrders,
        'completed_orders' => $completedOrders,
        'total_customers' => $totalCustomers,
        'new_registrations' => $newRegistrations,
        'total_products' => $totalProducts,
        'low_stock_products' => $lowStockProducts,
        'out_of_stock_products' => $outOfStockProducts,
        'recent_orders' => $recentOrders,
        'best_sellers' => $bestSellers,
        'recent_customers' => $recentCustomers,
        'low_stock_alerts' => $lowStockAlerts,
        'sales_chart' => $salesChart,
        'status_distribution' => $statusDistribution
    ];
}

/* =====================================================================
   2. PRODUCT MANAGEMENT SUBSYSTEM
   ===================================================================== */

/**
 * Fetch paginated products for admin list with search and filters
 */
function admin_get_products(array $filters = [], int $page = 1, int $perPage = 15): array {
    $pdo = db();
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    $where = ["p.status != 'archived'"];
    $params = [];

    if (!empty($filters['status']) && in_array($filters['status'], ['active', 'draft', 'archived'])) {
        $where = ["p.status = :status"];
        $params[':status'] = $filters['status'];
    }

    if (!empty($filters['search'])) {
        $s = '%' . trim($filters['search']) . '%';
        $where[] = "(p.name LIKE :s1 OR p.sku LIKE :s2 OR p.slug LIKE :s3)";
        $params[':s1'] = $s;
        $params[':s2'] = $s;
        $params[':s3'] = $s;
    }

    if (!empty($filters['category_id'])) {
        $where[] = "p.category_id = :cat_id";
        $params[':cat_id'] = (int)$filters['category_id'];
    }

    if (!empty($filters['brand_id'])) {
        $where[] = "p.brand_id = :brand_id";
        $params[':brand_id'] = (int)$filters['brand_id'];
    }

    $whereSql = implode(' AND ', $where);

    // Count
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    // Data
    $dataStmt = $pdo->prepare("
        SELECT 
            p.*,
            c.name AS category_name,
            b.name AS brand_name,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS primary_image,
            (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) AS variant_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE $whereSql
        ORDER BY p.id DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $dataStmt->bindValue($k, $v);
    }
    $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $items = $dataStmt->fetchAll();

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => max(1, (int)ceil($total / $perPage))
    ];
}

/**
 * Fetch detailed single product record for editing
 */
function admin_get_product(int $productId): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name, b.name AS brand_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product) {
        return null;
    }

    // Variants
    $varStmt = $pdo->prepare("SELECT * FROM product_variants WHERE product_id = ? ORDER BY id ASC");
    $varStmt->execute([$productId]);
    $product['variants'] = $varStmt->fetchAll();

    // Images
    $imgStmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC");
    $imgStmt->execute([$productId]);
    $product['images'] = $imgStmt->fetchAll();

    return $product;
}

/**
 * Create a new product with variants and images
 */
function admin_create_product(array $data, array $variants = [], array $images = []): array {
    $pdo = db();

    // Validation
    $name = trim($data['name'] ?? '');
    if ($name === '') {
        return ['success' => false, 'message' => 'Product name is required.'];
    }

    $sku = strtoupper(trim($data['sku'] ?? ''));
    if ($sku === '') {
        return ['success' => false, 'message' => 'Product Master SKU is required.'];
    }

    // Check unique SKU
    $skuCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ?");
    $skuCheck->execute([$sku]);
    if ((int)$skuCheck->fetchColumn() > 0) {
        return ['success' => false, 'message' => "SKU '$sku' is already in use by another product."];
    }

    // Slug
    $slug = trim($data['slug'] ?? '');
    if ($slug === '') {
        $slug = generate_slug($name);
    } else {
        $slug = generate_slug($slug);
    }

    // Ensure slug unique
    $slugCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ?");
    $slugCheck->execute([$slug]);
    if ((int)$slugCheck->fetchColumn() > 0) {
        $slug .= '-' . bin2hex(random_bytes(2));
    }

    $categoryId = (int)($data['category_id'] ?? 0);
    if ($categoryId <= 0) {
        return ['success' => false, 'message' => 'A valid Category is required.'];
    }

    $brandId = !empty($data['brand_id']) ? (int)$data['brand_id'] : null;

    $originalPrice = (float)($data['original_price'] ?? 0);
    $sellingPrice = (float)($data['selling_price'] ?? 0);
    $costPrice = !empty($data['cost_price']) ? (float)$data['cost_price'] : null;

    if ($originalPrice <= 0 || $sellingPrice <= 0) {
        return ['success' => false, 'message' => 'Prices must be greater than zero.'];
    }
    if ($sellingPrice > $originalPrice) {
        return ['success' => false, 'message' => 'Selling price cannot exceed original price.'];
    }

    $lowStockThreshold = max(1, (int)($data['low_stock_threshold'] ?? 5));
    $status = in_array($data['status'] ?? '', ['draft', 'active', 'archived']) ? $data['status'] : 'active';
    $isFeatured = !empty($data['is_featured']) ? 1 : 0;
    $isNew = isset($data['is_new']) ? (!empty($data['is_new']) ? 1 : 0) : 1;
    $isBestseller = !empty($data['is_bestseller']) ? 1 : 0;
    $isSummerMidi = !empty($data['is_summer_midi']) ? 1 : 0;
    $isTrendingCoord = !empty($data['is_trending_coord']) ? 1 : 0;

    $shortDesc = trim($data['short_description'] ?? '');
    $desc = trim($data['description'] ?? '');
    $specs = !empty($data['specifications']) 
        ? (is_array($data['specifications']) ? json_encode($data['specifications']) : (string)$data['specifications']) 
        : '{}';
    if ($specs === '' || !json_validate($specs)) {
        $specs = '{}';
    }
    $gender = !empty($data['gender']) ? trim($data['gender']) : null;
    $seoTitle = !empty($data['seo_title']) ? trim($data['seo_title']) : null;
    $seoDesc = !empty($data['seo_description']) ? trim($data['seo_description']) : null;

    // Calculate total stock from variants if provided, else direct stock
    $totalStock = 0;
    if (!empty($variants)) {
        foreach ($variants as $v) {
            $totalStock += max(0, (int)($v['stock_quantity'] ?? 0));
        }
    } else {
        $totalStock = max(0, (int)($data['stock_quantity'] ?? 0));
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO products (
                category_id, brand_id, name, slug, sku, short_description, description, specifications,
                original_price, selling_price, cost_price, stock_quantity, low_stock_threshold,
                is_featured, is_new, is_bestseller, is_summer_midi, is_trending_coord, status,
                gender, seo_title, seo_description, created_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, NOW(), NOW()
            )
        ");

        $stmt->execute([
            $categoryId, $brandId, $name, $slug, $sku, $shortDesc, $desc, $specs,
            $originalPrice, $sellingPrice, $costPrice, $totalStock, $lowStockThreshold,
            $isFeatured, $isNew, $isBestseller, $isSummerMidi, $isTrendingCoord, $status,
            $gender, $seoTitle, $seoDesc
        ]);

        $productId = (int)$pdo->lastInsertId();

        // Insert variants
        if (!empty($variants)) {
            $varStmt = $pdo->prepare("
                INSERT INTO product_variants (
                    product_id, sku, size, color, color_code, additional_price, stock_quantity, image_url, status, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");

            foreach ($variants as $idx => $v) {
                $vSku = trim($v['sku'] ?? '');
                if ($vSku === '') {
                    $vSku = $sku . '-' . ($idx + 1);
                }
                $vSize = trim($v['size'] ?? '');
                $vColor = trim($v['color'] ?? '');
                $vColorCode = trim($v['color_code'] ?? '#000000');
                $vAddPrice = (float)($v['additional_price'] ?? 0);
                $vStock = max(0, (int)($v['stock_quantity'] ?? 0));
                $vImg = trim($v['image_url'] ?? '');
                $vStatus = in_array($v['status'] ?? '', ['active', 'inactive']) ? $v['status'] : 'active';

                $varStmt->execute([
                    $productId, $vSku, $vSize, $vColor, $vColorCode, $vAddPrice, $vStock, $vImg, $vStatus
                ]);

                $variantId = (int)$pdo->lastInsertId();

                // Initial inventory movement log
                if ($vStock > 0) {
                    $adminId = $_SESSION['admin_user_id'] ?? null;
                    $pdo->prepare("
                        INSERT INTO inventory_movements (product_id, variant_id, movement_type, quantity, balance_after, note, created_by, created_at)
                        VALUES (?, ?, 'initial', ?, ?, 'Initial creation stock', ?, NOW())
                    ")->execute([$productId, $variantId, $vStock, $vStock, $adminId]);
                }
            }
        } else {
            // Log direct stock if > 0
            if ($totalStock > 0) {
                $adminId = $_SESSION['admin_user_id'] ?? null;
                $pdo->prepare("
                    INSERT INTO inventory_movements (product_id, variant_id, movement_type, quantity, balance_after, note, created_by, created_at)
                    VALUES (?, NULL, 'initial', ?, ?, 'Initial creation stock', ?, NOW())
                ")->execute([$productId, $totalStock, $totalStock, $adminId]);
            }
        }

        // Insert images
        if (!empty($images)) {
            $imgStmt = $pdo->prepare("
                INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            foreach ($images as $i => $img) {
                $url = trim($img['image_url'] ?? '');
                if ($url === '') continue;
                $alt = trim($img['alt_text'] ?? $name);
                $sort = (int)($img['sort_order'] ?? $i);
                $isPrimary = ($i === 0 || !empty($img['is_primary'])) ? 1 : 0;

                $imgStmt->execute([$productId, $url, $alt, $sort, $isPrimary]);
            }
        }

        $adminId = $_SESSION['admin_user_id'] ?? null;
        log_admin_activity($adminId, 'product_create', 'products', $productId, "Created product '$name' ($sku)");

        $pdo->commit();
        return ['success' => true, 'product_id' => $productId, 'message' => 'Product created successfully.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Update an existing product, variants, and imagery
 */
function admin_update_product(int $productId, array $data, array $variants = [], array $images = []): array {
    $pdo = db();

    $existing = admin_get_product($productId);
    if (!$existing) {
        return ['success' => false, 'message' => 'Product not found.'];
    }

    $name = trim($data['name'] ?? $existing['name']);
    if ($name === '') {
        return ['success' => false, 'message' => 'Product name is required.'];
    }

    $sku = strtoupper(trim($data['sku'] ?? $existing['sku']));
    if ($sku === '') {
        return ['success' => false, 'message' => 'Product SKU is required.'];
    }

    // Check unique SKU excluding current product
    $skuCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE sku = ? AND id != ?");
    $skuCheck->execute([$sku, $productId]);
    if ((int)$skuCheck->fetchColumn() > 0) {
        return ['success' => false, 'message' => "SKU '$sku' is in use by another product."];
    }

    $slug = trim($data['slug'] ?? $existing['slug']);
    $slug = generate_slug($slug ?: $name);
    $slugCheck = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ? AND id != ?");
    $slugCheck->execute([$slug, $productId]);
    if ((int)$slugCheck->fetchColumn() > 0) {
        $slug .= '-' . bin2hex(random_bytes(2));
    }

    $categoryId = (int)($data['category_id'] ?? $existing['category_id']);
    $brandId = !empty($data['brand_id']) ? (int)$data['brand_id'] : null;

    $originalPrice = (float)($data['original_price'] ?? $existing['original_price']);
    $sellingPrice = (float)($data['selling_price'] ?? $existing['selling_price']);
    $costPrice = isset($data['cost_price']) && $data['cost_price'] !== '' ? (float)$data['cost_price'] : $existing['cost_price'];

    if ($originalPrice <= 0 || $sellingPrice <= 0) {
        return ['success' => false, 'message' => 'Prices must be greater than zero.'];
    }
    if ($sellingPrice > $originalPrice) {
        return ['success' => false, 'message' => 'Selling price cannot exceed original price.'];
    }

    $lowStockThreshold = max(1, (int)($data['low_stock_threshold'] ?? $existing['low_stock_threshold']));
    $status = in_array($data['status'] ?? '', ['draft', 'active', 'archived']) ? $data['status'] : $existing['status'];
    $isFeatured = isset($data['is_featured']) ? (!empty($data['is_featured']) ? 1 : 0) : $existing['is_featured'];
    $isNew = isset($data['is_new']) ? (!empty($data['is_new']) ? 1 : 0) : $existing['is_new'];
    $isBestseller = isset($data['is_bestseller']) ? (!empty($data['is_bestseller']) ? 1 : 0) : $existing['is_bestseller'];
    $isSummerMidi = isset($data['is_summer_midi']) ? (!empty($data['is_summer_midi']) ? 1 : 0) : ($existing['is_summer_midi'] ?? 0);
    $isTrendingCoord = isset($data['is_trending_coord']) ? (!empty($data['is_trending_coord']) ? 1 : 0) : ($existing['is_trending_coord'] ?? 0);

    $shortDesc = trim($data['short_description'] ?? $existing['short_description']);
    $desc = trim($data['description'] ?? $existing['description']);
    $specs = trim($data['specifications'] ?? $existing['specifications']);

    try {
        $pdo->beginTransaction();

        // Synchronize variants if supplied
        $totalStock = 0;
        if (!empty($variants)) {
            // Delete old variants that are not referenced in historical orders
            $existingVariantIds = array_column($existing['variants'], 'id');
            $submittedVariantIds = array_filter(array_column($variants, 'id'));

            foreach ($variants as $v) {
                $vId = !empty($v['id']) ? (int)$v['id'] : null;
                $vSku = trim($v['sku'] ?? '');
                $vSize = trim($v['size'] ?? '');
                $vColor = trim($v['color'] ?? '');
                $vColorCode = trim($v['color_code'] ?? '#000000');
                $vAddPrice = (float)($v['additional_price'] ?? 0);
                $vStock = max(0, (int)($v['stock_quantity'] ?? 0));
                $vImg = trim($v['image_url'] ?? '');
                $vStatus = in_array($v['status'] ?? '', ['active', 'inactive']) ? $v['status'] : 'active';
                $totalStock += $vStock;

                if ($vId && in_array($vId, $existingVariantIds)) {
                    // Update variant
                    $pdo->prepare("
                        UPDATE product_variants SET
                            sku = ?, size = ?, color = ?, color_code = ?, additional_price = ?,
                            stock_quantity = ?, image_url = ?, status = ?, updated_at = NOW()
                        WHERE id = ? AND product_id = ?
                    ")->execute([$vSku, $vSize, $vColor, $vColorCode, $vAddPrice, $vStock, $vImg, $vStatus, $vId, $productId]);
                } else {
                    // Insert new variant
                    if ($vSku === '') $vSku = $sku . '-' . bin2hex(random_bytes(2));
                    $pdo->prepare("
                        INSERT INTO product_variants (
                            product_id, sku, size, color, color_code, additional_price, stock_quantity, image_url, status, created_at, updated_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ")->execute([$productId, $vSku, $vSize, $vColor, $vColorCode, $vAddPrice, $vStock, $vImg, $vStatus]);
                }
            }
        } else {
            $totalStock = isset($data['stock_quantity']) ? max(0, (int)$data['stock_quantity']) : $existing['stock_quantity'];
        }

        // Update product master record
        $stmt = $pdo->prepare("
            UPDATE products SET
                category_id = ?, brand_id = ?, name = ?, slug = ?, sku = ?,
                short_description = ?, description = ?, specifications = ?,
                original_price = ?, selling_price = ?, cost_price = ?,
                stock_quantity = ?, low_stock_threshold = ?,
                is_featured = ?, is_new = ?, is_bestseller = ?, is_summer_midi = ?, is_trending_coord = ?, status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $categoryId, $brandId, $name, $slug, $sku,
            $shortDesc, $desc, $specs,
            $originalPrice, $sellingPrice, $costPrice,
            $totalStock, $lowStockThreshold,
            $isFeatured, $isNew, $isBestseller, $isSummerMidi, $isTrendingCoord, $status,
            $productId
        ]);

        // Add any new images
        if (!empty($images)) {
            $imgStmt = $pdo->prepare("
                INSERT INTO product_images (product_id, image_url, alt_text, sort_order, is_primary, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            foreach ($images as $img) {
                $url = trim($img['image_url'] ?? '');
                if ($url === '') continue;
                $alt = trim($img['alt_text'] ?? $name);
                $sort = (int)($img['sort_order'] ?? 0);
                $isPrimary = !empty($img['is_primary']) ? 1 : 0;
                $imgStmt->execute([$productId, $url, $alt, $sort, $isPrimary]);
            }
        }

        $adminId = $_SESSION['admin_user_id'] ?? null;
        log_admin_activity($adminId, 'product_update', 'products', $productId, "Updated product '$name' ($sku)");

        $pdo->commit();
        return ['success' => true, 'message' => 'Product updated successfully.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Toggle product status (active/draft/archived)
 */
function admin_toggle_product_status(int $productId, string $newStatus): bool {
    if (!in_array($newStatus, ['active', 'draft', 'archived'], true)) {
        return false;
    }

    try {
        $stmt = db()->prepare("UPDATE products SET status = ?, updated_at = NOW() WHERE id = ?");
        $ok = $stmt->execute([$newStatus, $productId]);
        if ($ok) {
            $adminId = $_SESSION['admin_user_id'] ?? null;
            log_admin_activity($adminId, 'product_status_toggle', 'products', $productId, "Status changed to '$newStatus'");
        }
        return $ok;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Delete product or soft-delete (archive) if referenced in historical orders
 */
function admin_delete_product(int $productId): array {
    $pdo = db();

    // Check if referenced in order_items
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
    $checkStmt->execute([$productId]);
    $orderCount = (int)$checkStmt->fetchColumn();

    if ($orderCount > 0) {
        // Soft delete / archive to preserve historical commerce data
        $pdo->prepare("UPDATE products SET status = 'archived', updated_at = NOW() WHERE id = ?")->execute([$productId]);
        $adminId = $_SESSION['admin_user_id'] ?? null;
        log_admin_activity($adminId, 'product_archive', 'products', $productId, "Archived product referenced in $orderCount historical orders");

        return [
            'success' => true,
            'archived' => true,
            'message' => "Product has $orderCount historical order reference(s). It has been safely archived instead of deleted to protect order integrity."
        ];
    }

    // Safe to delete completely
    try {
        $pdo->beginTransaction();

        $pdo->prepare("DELETE FROM product_variants WHERE product_id = ?")->execute([$productId]);
        $pdo->prepare("DELETE FROM product_images WHERE product_id = ?")->execute([$productId]);
        $pdo->prepare("DELETE FROM inventory_movements WHERE product_id = ?")->execute([$productId]);
        $pdo->prepare("DELETE FROM wishlists WHERE product_id = ?")->execute([$productId]);
        $pdo->prepare("DELETE FROM cart_items WHERE product_id = ?")->execute([$productId]);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$productId]);

        $adminId = $_SESSION['admin_user_id'] ?? null;
        log_admin_activity($adminId, 'product_delete', 'products', $productId, "Permanently deleted unreferenced product #$productId");

        $pdo->commit();
        return ['success' => true, 'archived' => false, 'message' => 'Product permanently removed.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/* =====================================================================
   3. SECURE IMAGE UPLOAD SUBSYSTEM
   ===================================================================== */

/**
 * Securely validate and store image uploads preventing executable attacks
 */
function admin_validate_image_upload(array $file, string $subfolder = 'products'): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'message' => 'Invalid file parameter.'];
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload error code: ' . $file['error']];
    }

    // Restrict size: 5 MB max
    $maxBytes = 5 * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return ['success' => false, 'message' => 'File size exceeds maximum permitted limit of 5 MB.'];
    }

    // Whitelist file extensions
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowedExts, true)) {
        return ['success' => false, 'message' => 'Invalid file extension. Allowed types: JPG, PNG, WEBP.'];
    }

    // Validate MIME type with finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes, true)) {
        return ['success' => false, 'message' => 'Invalid MIME type. Executable or non-image files are strictly rejected.'];
    }

    // Target directory
    $allowedSubfolders = ['products', 'banners'];
    if (!in_array($subfolder, $allowedSubfolders, true)) {
        $subfolder = 'products';
    }

    $destDir = PATH_UPLOADS . DIRECTORY_SEPARATOR . $subfolder;
    if (!is_dir($destDir)) {
        @mkdir($destDir, 0755, true);
    }

    // Generate cryptographically random filename
    $cleanExt = ($ext === 'jpeg') ? 'jpg' : $ext;
    $randomFilename = 'yty_' . bin2hex(random_bytes(16)) . '.' . $cleanExt;
    $destPath = $destDir . DIRECTORY_SEPARATOR . $randomFilename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['success' => false, 'message' => 'Failed to move uploaded file to target destination.'];
    }

    $relativeUrl = 'uploads/' . $subfolder . '/' . $randomFilename;
    return [
        'success' => true,
        'url' => $relativeUrl,
        'filename' => $randomFilename,
        'message' => 'Image securely uploaded.'
    ];
}

/* =====================================================================
   4. INVENTORY MANAGEMENT SUBSYSTEM
   ===================================================================== */

/**
 * Fetch inventory overview with search, low-stock, and out-of-stock filters
 */
function admin_get_inventory(array $filters = [], int $page = 1, int $perPage = 15): array {
    $pdo = db();
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    $where = ["p.status = 'active'"];
    $params = [];

    if (!empty($filters['filter'])) {
        if ($filters['filter'] === 'low_stock') {
            $where[] = "p.stock_quantity <= p.low_stock_threshold AND p.stock_quantity > 0";
        } elseif ($filters['filter'] === 'out_of_stock') {
            $where[] = "p.stock_quantity = 0";
        }
    }

    if (!empty($filters['search'])) {
        $s = '%' . trim($filters['search']) . '%';
        $where[] = "(p.name LIKE :s1 OR p.sku LIKE :s2)";
        $params[':s1'] = $s;
        $params[':s2'] = $s;
    }

    $whereSql = implode(' AND ', $where);

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT 
            p.id, p.name, p.sku, p.stock_quantity, p.low_stock_threshold, p.selling_price, p.status,
            c.name AS category_name,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS primary_image,
            (SELECT COUNT(*) FROM product_variants WHERE product_id = p.id) AS variant_count
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $whereSql
        ORDER BY p.stock_quantity ASC, p.id DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    // Populate variant details for each product
    foreach ($items as &$item) {
        $vStmt = $pdo->prepare("SELECT id, sku, size, color, stock_quantity, status FROM product_variants WHERE product_id = ? ORDER BY id ASC");
        $vStmt->execute([$item['id']]);
        $item['variants'] = $vStmt->fetchAll();
    }
    unset($item);

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => max(1, (int)ceil($total / $perPage))
    ];
}

/**
 * Adjust stock quantity atomically with audit movement history
 */
function admin_adjust_stock(int $productId, ?int $variantId, int $adjustmentQty, string $movementType, string $note, int $adminId): array {
    $pdo = db();

    $validMovements = ['adjustment', 'purchase', 'return_restock', 'order_cancel'];
    if (!in_array($movementType, $validMovements, true)) {
        $movementType = 'adjustment';
    }

    try {
        $pdo->beginTransaction();

        if ($variantId) {
            // Lock variant row
            $vStmt = $pdo->prepare("SELECT stock_quantity FROM product_variants WHERE id = ? AND product_id = ? FOR UPDATE");
            $vStmt->execute([$variantId, $productId]);
            $currentVarStock = $vStmt->fetchColumn();

            if ($currentVarStock === false) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Variant not found.'];
            }

            $newVarStock = (int)$currentVarStock + $adjustmentQty;
            if ($newVarStock < 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Adjustment would result in negative stock. Operation rejected.'];
            }

            // Update variant stock
            $pdo->prepare("UPDATE product_variants SET stock_quantity = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newVarStock, $variantId]);

            // Recalculate master product stock
            $sumStmt = $pdo->prepare("SELECT COALESCE(SUM(stock_quantity), 0) FROM product_variants WHERE product_id = ?");
            $sumStmt->execute([$productId]);
            $newProductStock = (int)$sumStmt->fetchColumn();

            $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newProductStock, $productId]);

            $balanceAfter = $newVarStock;
        } else {
            // Lock product row directly
            $pStmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE id = ? FOR UPDATE");
            $pStmt->execute([$productId]);
            $currentProductStock = $pStmt->fetchColumn();

            if ($currentProductStock === false) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Product not found.'];
            }

            $newProductStock = (int)$currentProductStock + $adjustmentQty;
            if ($newProductStock < 0) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Adjustment would result in negative stock. Operation rejected.'];
            }

            $pdo->prepare("UPDATE products SET stock_quantity = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$newProductStock, $productId]);

            $balanceAfter = $newProductStock;
        }

        // Record immutable inventory movement
        $pdo->prepare("
            INSERT INTO inventory_movements (
                product_id, variant_id, movement_type, quantity, balance_after, note, created_by, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ")->execute([
            $productId, $variantId, $movementType, $adjustmentQty, $balanceAfter, $note, $adminId
        ]);

        log_admin_activity($adminId, 'stock_adjustment', 'products', $productId, "Adjusted stock by $adjustmentQty ($movementType). New balance: $balanceAfter. Note: $note");

        $pdo->commit();
        return [
            'success' => true,
            'new_balance' => $balanceAfter,
            'message' => 'Stock adjusted successfully.'
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Fetch inventory movement history for a product or variant
 */
function admin_get_inventory_movements(int $productId, ?int $variantId = null, int $limit = 25): array {
    $pdo = db();
    $sql = "
        SELECT im.*, u.full_name AS admin_name, pv.sku AS variant_sku, pv.size, pv.color
        FROM inventory_movements im
        LEFT JOIN admin_users u ON im.created_by = u.id
        LEFT JOIN product_variants pv ON im.variant_id = pv.id
        WHERE im.product_id = ?
    ";
    $params = [$productId];

    if ($variantId !== null) {
        $sql .= " AND im.variant_id = ?";
        $params[] = $variantId;
    }

    $sql .= " ORDER BY im.created_at DESC, im.id DESC LIMIT " . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* =====================================================================
   5. ORDER MANAGEMENT SUBSYSTEM
   ===================================================================== */

/**
 * Fetch paginated orders with filters for admin list
 */
function admin_get_orders(array $filters = [], int $page = 1, int $perPage = 15): array {
    $pdo = db();
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    $where = ["1=1"];
    $params = [];

    if (!empty($filters['order_status'])) {
        $where[] = "o.order_status = :order_status";
        $params[':order_status'] = $filters['order_status'];
    }

    if (!empty($filters['payment_status'])) {
        $where[] = "o.payment_status = :payment_status";
        $params[':payment_status'] = $filters['payment_status'];
    }

    if (!empty($filters['search'])) {
        $s = '%' . trim($filters['search']) . '%';
        $where[] = "(o.order_number LIKE :s1 OR o.shipping_full_name LIKE :s2 OR o.shipping_phone LIKE :s3 OR c.email LIKE :s4)";
        $params[':s1'] = $s;
        $params[':s2'] = $s;
        $params[':s3'] = $s;
        $params[':s4'] = $s;
    }

    $whereSql = implode(' AND ', $where);

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o LEFT JOIN customers c ON o.customer_id = c.id WHERE $whereSql");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT 
            o.*,
            c.first_name, c.last_name, c.email AS customer_email,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE $whereSql
        ORDER BY o.placed_at DESC, o.id DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => max(1, (int)ceil($total / $perPage))
    ];
}

/**
 * Fetch complete order details including customer, line items, and payment
 */
function admin_get_order(int $orderId): ?array {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT o.*, c.first_name, c.last_name, c.email AS customer_email, c.phone AS customer_phone
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    // Line items with product images and variant specifics
    $itemStmt = $pdo->prepare("
        SELECT 
            oi.*,
            p.slug AS product_slug,
            (SELECT image_url FROM product_images WHERE product_id = oi.product_id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image_url
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $itemStmt->execute([$orderId]);
    $order['items'] = $itemStmt->fetchAll();

    // Payment record if available
    $payStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $payStmt->execute([$orderId]);
    $order['payment'] = $payStmt->fetch() ?: null;

    return $order;
}

/**
 * Update order status and admin internal notes
 */
function admin_update_order_status(int $orderId, string $newStatus, ?string $adminNotes = null, ?int $adminId = null): array {
    $pdo = db();

    $allowed = ['placed', 'confirmed', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'cancelled', 'returned'];
    if (!in_array($newStatus, $allowed, true)) {
        return ['success' => false, 'message' => "Invalid order status '$newStatus'."];
    }

    $order = admin_get_order($orderId);
    if (!$order) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    $oldStatus = $order['order_status'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE orders 
            SET order_status = ?, 
                admin_notes = COALESCE(?, admin_notes),
                updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$newStatus, $adminNotes, $orderId]);

        // If newly cancelled, restock inventory if not previously cancelled/returned
        if ($newStatus === 'cancelled' && !in_array($oldStatus, ['cancelled', 'returned'])) {
            foreach ($order['items'] as $item) {
                $qty = (int)$item['quantity'];
                $prodId = (int)$item['product_id'];
                $varId = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;

                if ($varId) {
                    $pdo->prepare("UPDATE product_variants SET stock_quantity = stock_quantity + ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$qty, $varId]);
                    $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$qty, $prodId]);
                } else {
                    $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$qty, $prodId]);
                }

                $pdo->prepare("
                    INSERT INTO inventory_movements (product_id, variant_id, movement_type, quantity, balance_after, reference_id, note, created_by, created_at)
                    VALUES (?, ?, 'order_cancel', ?, (SELECT stock_quantity FROM products WHERE id = ?), ?, 'Restock upon order cancellation', ?, NOW())
                ")->execute([$prodId, $varId, $qty, $prodId, $order['order_number'], $adminId]);
            }
        }

        log_admin_activity($adminId, 'order_status_update', 'orders', $orderId, "Status changed from '$oldStatus' to '$newStatus' for Order {$order['order_number']}");

        $pdo->commit();
        return ['success' => true, 'message' => "Order status updated to '$newStatus'."];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Update payment status for an order
 */
function admin_update_payment_status(int $orderId, string $paymentStatus, ?int $adminId = null): array {
    $pdo = db();
    $allowed = ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'];
    if (!in_array($paymentStatus, $allowed, true)) {
        return ['success' => false, 'message' => 'Invalid payment status.'];
    }

    try {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$paymentStatus, $orderId]);

        log_admin_activity($adminId, 'payment_status_update', 'orders', $orderId, "Payment status updated to '$paymentStatus'");
        return ['success' => true, 'message' => "Payment status updated to '$paymentStatus'."];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/* =====================================================================
   6. CUSTOMER MANAGEMENT SUBSYSTEM
   ===================================================================== */

/**
 * Fetch paginated customer registry with search and status filtering
 */
function admin_get_customers(array $filters = [], int $page = 1, int $perPage = 15): array {
    $pdo = db();
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    $where = ["1=1"];
    $params = [];

    if (!empty($filters['status']) && in_array($filters['status'], ['active', 'inactive', 'suspended'])) {
        $where[] = "c.status = :status";
        $params[':status'] = $filters['status'];
    }

    if (!empty($filters['search'])) {
        $s = '%' . trim($filters['search']) . '%';
        $where[] = "(c.first_name LIKE :s1 OR c.last_name LIKE :s2 OR c.email LIKE :s3 OR c.phone LIKE :s4)";
        $params[':s1'] = $s;
        $params[':s2'] = $s;
        $params[':s3'] = $s;
        $params[':s4'] = $s;
    }

    $whereSql = implode(' AND ', $where);

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM customers c WHERE $whereSql");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT 
            c.id, c.first_name, c.last_name, c.email, c.phone, c.status, c.created_at, c.last_login_at,
            (SELECT COUNT(*) FROM orders WHERE customer_id = c.id) AS total_orders,
            (SELECT COALESCE(SUM(grand_total), 0) FROM orders WHERE customer_id = c.id AND payment_status = 'paid') AS total_spent,
            (SELECT MAX(placed_at) FROM orders WHERE customer_id = c.id) AS last_order_date
        FROM customers c
        WHERE $whereSql
        ORDER BY c.id DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => max(1, (int)ceil($total / $perPage))
    ];
}

/**
 * Fetch customer dossier excluding password hashes and reset tokens
 */
function admin_get_customer_details(int $customerId): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, phone, status, email_verified_at, last_login_at, created_at, updated_at
        FROM customers
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$customerId]);
    $customer = $stmt->fetch();
    if (!$customer) {
        return null;
    }

    // Addresses
    $addrStmt = $pdo->prepare("SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default_shipping DESC, id ASC");
    $addrStmt->execute([$customerId]);
    $customer['addresses'] = $addrStmt->fetchAll();

    // Order history enriched with line items
    $ordStmt = $pdo->prepare("SELECT * FROM orders WHERE customer_id = ? ORDER BY placed_at DESC LIMIT 50");
    $ordStmt->execute([$customerId]);
    $orders = $ordStmt->fetchAll();

    foreach ($orders as &$ord) {
        $itemStmt = $pdo->prepare("
            SELECT product_name, variant_name, quantity, unit_price, total_price 
            FROM order_items 
            WHERE order_id = ?
        ");
        $itemStmt->execute([$ord['id']]);
        $ord['items'] = $itemStmt->fetchAll();
    }
    unset($ord);

    $customer['orders'] = $orders;

    return $customer;
}

/**
 * Toggle customer account status (active/suspended/inactive)
 */
function admin_toggle_customer_status(int $customerId, string $newStatus, ?int $adminId = null): bool {
    if (!in_array($newStatus, ['active', 'inactive', 'suspended'], true)) {
        return false;
    }

    try {
        $stmt = db()->prepare("UPDATE customers SET status = ?, updated_at = NOW() WHERE id = ?");
        $ok = $stmt->execute([$newStatus, $customerId]);
        if ($ok) {
            log_admin_activity($adminId, 'customer_status_toggle', 'customers', $customerId, "Account status changed to '$newStatus'");
        }
        return $ok;
    } catch (Exception $e) {
        return false;
    }
}

/* =====================================================================
   7. COUPON MANAGEMENT SUBSYSTEM
   ===================================================================== */

/**
 * Fetch all coupons with usage statistics
 */
function admin_get_coupons(int $page = 1, int $perPage = 20): array {
    $pdo = db();
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    $total = (int)$pdo->query("SELECT COUNT(*) FROM coupons")->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT * FROM coupons
        ORDER BY is_active DESC, id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => max(1, (int)ceil($total / $perPage))
    ];
}

/**
 * Create or update a coupon
 */
function admin_save_coupon(array $data, ?int $couponId = null, ?int $adminId = null): array {
    $pdo = db();

    $code = strtoupper(trim($data['code'] ?? ''));
    if ($code === '') {
        return ['success' => false, 'message' => 'Coupon code is required.'];
    }

    // Code uniqueness check
    if ($couponId) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM coupons WHERE code = ? AND id != ?");
        $check->execute([$code, $couponId]);
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM coupons WHERE code = ?");
        $check->execute([$code]);
    }
    if ((int)$check->fetchColumn() > 0) {
        return ['success' => false, 'message' => "Coupon code '$code' already exists."];
    }

    $discountType = ($data['discount_type'] ?? '') === 'fixed' ? 'fixed' : 'percentage';
    $discountValue = (float)($data['discount_value'] ?? 0);
    if ($discountValue <= 0) {
        return ['success' => false, 'message' => 'Discount value must be greater than zero.'];
    }
    if ($discountType === 'percentage' && $discountValue > 100) {
        return ['success' => false, 'message' => 'Percentage discount cannot exceed 100%.'];
    }

    $description = trim($data['description'] ?? '');
    $minSpend = max(0, (float)($data['min_spend'] ?? 0));
    $maxDiscount = !empty($data['max_discount']) ? (float)$data['max_discount'] : null;
    $startDate = !empty($data['start_date']) ? $data['start_date'] : null;
    $endDate = !empty($data['end_date']) ? $data['end_date'] : null;
    $usageLimit = !empty($data['usage_limit']) ? (int)$data['usage_limit'] : null;
    $perCustomerLimit = max(1, (int)($data['per_customer_limit'] ?? 1));
    $isActive = !empty($data['is_active']) ? 1 : 0;

    try {
        if ($couponId) {
            $stmt = $pdo->prepare("
                UPDATE coupons SET
                    code = ?, description = ?, discount_type = ?, discount_value = ?,
                    min_spend = ?, max_discount = ?, start_date = ?, end_date = ?,
                    usage_limit = ?, per_customer_limit = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $code, $description, $discountType, $discountValue,
                $minSpend, $maxDiscount, $startDate, $endDate,
                $usageLimit, $perCustomerLimit, $isActive, $couponId
            ]);
            log_admin_activity($adminId, 'coupon_update', 'coupons', $couponId, "Updated coupon '$code'");
            return ['success' => true, 'coupon_id' => $couponId, 'message' => 'Coupon updated successfully.'];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO coupons (
                    code, description, discount_type, discount_value, min_spend, max_discount,
                    start_date, end_date, usage_limit, usage_count, per_customer_limit, is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $code, $description, $discountType, $discountValue, $minSpend, $maxDiscount,
                $startDate, $endDate, $usageLimit, $perCustomerLimit, $isActive
            ]);
            $newId = (int)$pdo->lastInsertId();
            log_admin_activity($adminId, 'coupon_create', 'coupons', $newId, "Created coupon '$code'");
            return ['success' => true, 'coupon_id' => $newId, 'message' => 'Coupon created successfully.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Toggle coupon active/inactive state
 */
function admin_toggle_coupon_status(int $couponId, bool $isActive, ?int $adminId = null): bool {
    try {
        $stmt = db()->prepare("UPDATE coupons SET is_active = ?, updated_at = NOW() WHERE id = ?");
        $ok = $stmt->execute([$isActive ? 1 : 0, $couponId]);
        if ($ok) {
            log_admin_activity($adminId, 'coupon_toggle', 'coupons', $couponId, "Coupon " . ($isActive ? 'activated' : 'deactivated'));
        }
        return $ok;
    } catch (Exception $e) {
        return false;
    }
}

/* =====================================================================
   8. CATEGORIES & BRANDS MANAGEMENT SUBSYSTEM
   ===================================================================== */

/**
 * Fetch all categories including hierarchical parents
 */
function admin_get_categories(): array {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT c.*, p.name AS parent_name,
               (SELECT COUNT(*) FROM products WHERE category_id = c.id) AS product_count
        FROM categories c
        LEFT JOIN categories p ON c.parent_id = p.id
        ORDER BY c.parent_id ASC, c.display_order ASC, c.name ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Create or update a category
 */
function admin_save_category(array $data, ?int $categoryId = null, ?int $adminId = null): array {
    $pdo = db();

    $name = trim($data['name'] ?? '');
    if ($name === '') {
        return ['success' => false, 'message' => 'Category name is required.'];
    }

    $slug = trim($data['slug'] ?? '');
    $slug = generate_slug($slug ?: $name);

    // Slug uniqueness check
    if ($categoryId) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ? AND id != ?");
        $check->execute([$slug, $categoryId]);
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE slug = ?");
        $check->execute([$slug]);
    }
    if ((int)$check->fetchColumn() > 0) {
        return ['success' => false, 'message' => "Category slug '$slug' already exists."];
    }

    $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
    $description = trim($data['description'] ?? '');
    $imageUrl = trim($data['image_url'] ?? '');
    $bannerUrl = trim($data['banner_url'] ?? '');
    $seoTitle = trim($data['seo_title'] ?? '') ?: null;
    $seoDesc = trim($data['seo_description'] ?? '') ?: null;
    $displayOrder = (int)($data['display_order'] ?? 0);
    $isFeatured = !empty($data['is_featured']) ? 1 : 0;
    $isActive = !empty($data['is_active']) ? 1 : 0;

    try {
        if ($categoryId) {
            $stmt = $pdo->prepare("
                UPDATE categories SET
                    parent_id = ?, name = ?, slug = ?, description = ?, image_url = ?,
                    banner_url = ?, seo_title = ?, seo_description = ?, display_order = ?, is_featured = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $parentId, $name, $slug, $description, $imageUrl,
                $bannerUrl, $seoTitle, $seoDesc, $displayOrder, $isFeatured, $isActive, $categoryId
            ]);
            log_admin_activity($adminId, 'category_update', 'categories', $categoryId, "Updated category '$name'");
            return ['success' => true, 'category_id' => $categoryId, 'message' => 'Category updated successfully.'];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO categories (
                    parent_id, name, slug, description, image_url, banner_url, seo_title, seo_description, display_order, is_featured, is_active, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $parentId, $name, $slug, $description, $imageUrl, $bannerUrl, $seoTitle, $seoDesc, $displayOrder, $isFeatured, $isActive
            ]);
            $newId = (int)$pdo->lastInsertId();
            log_admin_activity($adminId, 'category_create', 'categories', $newId, "Created category '$name'");
            return ['success' => true, 'category_id' => $newId, 'message' => 'Category created successfully.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Delete category if no active products are assigned
 */
function admin_delete_category(int $categoryId, ?int $adminId = null): array {
    $pdo = db();

    $cnt = (int)$pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?")->execute([$categoryId]) ? 
        $pdo->query("SELECT COUNT(*) FROM products WHERE category_id = $categoryId")->fetchColumn() : 0;

    if ($cnt > 0) {
        return ['success' => false, 'message' => "Cannot delete category: $cnt product(s) are currently assigned to it."];
    }

    try {
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$categoryId]);
        log_admin_activity($adminId, 'category_delete', 'categories', $categoryId, "Deleted category #$categoryId");
        return ['success' => true, 'message' => 'Category deleted successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Fetch all brands
 */
function admin_get_brands(): array {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT b.*,
               (SELECT COUNT(*) FROM products WHERE brand_id = b.id) AS product_count
        FROM brands b
        ORDER BY b.name ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Create or update a brand
 */
function admin_save_brand(array $data, ?int $brandId = null, ?int $adminId = null): array {
    $pdo = db();

    $name = trim($data['name'] ?? '');
    if ($name === '') {
        return ['success' => false, 'message' => 'Brand name is required.'];
    }

    $slug = trim($data['slug'] ?? '');
    $slug = generate_slug($slug ?: $name);

    if ($brandId) {
        $check = $pdo->prepare("SELECT COUNT(*) FROM brands WHERE slug = ? AND id != ?");
        $check->execute([$slug, $brandId]);
    } else {
        $check = $pdo->prepare("SELECT COUNT(*) FROM brands WHERE slug = ?");
        $check->execute([$slug]);
    }
    if ((int)$check->fetchColumn() > 0) {
        return ['success' => false, 'message' => "Brand slug '$slug' already exists."];
    }

    $description = trim($data['description'] ?? '');
    $logoUrl = trim($data['logo_url'] ?? '');
    $isActive = !empty($data['is_active']) ? 1 : 0;

    try {
        if ($brandId) {
            $stmt = $pdo->prepare("UPDATE brands SET name = ?, slug = ?, description = ?, logo_url = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $slug, $description, $logoUrl, $isActive, $brandId]);
            log_admin_activity($adminId, 'brand_update', 'brands', $brandId, "Updated brand '$name'");
            return ['success' => true, 'brand_id' => $brandId, 'message' => 'Brand updated successfully.'];
        } else {
            $stmt = $pdo->prepare("INSERT INTO brands (name, slug, description, logo_url, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$name, $slug, $description, $logoUrl, $isActive]);
            $newId = (int)$pdo->lastInsertId();
            log_admin_activity($adminId, 'brand_create', 'brands', $newId, "Created brand '$name'");
            return ['success' => true, 'brand_id' => $newId, 'message' => 'Brand created successfully.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Delete brand if no active products are assigned
 */
function admin_delete_brand(int $brandId, ?int $adminId = null): array {
    $pdo = db();
    $cnt = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE brand_id = $brandId")->fetchColumn();
    if ($cnt > 0) {
        return ['success' => false, 'message' => "Cannot delete brand: $cnt product(s) are currently assigned to it."];
    }

    try {
        $pdo->prepare("DELETE FROM brands WHERE id = ?")->execute([$brandId]);
        log_admin_activity($adminId, 'brand_delete', 'brands', $brandId, "Deleted brand #$brandId");
        return ['success' => true, 'message' => 'Brand deleted successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/* =====================================================================
   9. BANNER MANAGEMENT SUBSYSTEM
   ===================================================================== */

/**
 * Fetch all homepage banners ordered by display position
 */
function admin_get_banners(): array {
    $pdo = db();
    return $pdo->query("SELECT * FROM banners ORDER BY position ASC, display_order ASC, id ASC")->fetchAll();
}

/**
 * Save or update banner
 */
function admin_save_banner(array $data, ?int $bannerId = null, ?int $adminId = null): array {
    $pdo = db();

    $title = trim($data['title'] ?? '');
    if ($title === '') {
        return ['success' => false, 'message' => 'Banner title is required.'];
    }

    $imageUrl = trim($data['image_url'] ?? '');
    if ($imageUrl === '') {
        return ['success' => false, 'message' => 'Banner desktop image URL is required.'];
    }

    $subtitle = trim($data['subtitle'] ?? '');
    $badgeText = trim($data['badge_text'] ?? '');
    $ctaText = trim($data['cta_text'] ?? 'Shop Collection');
    $ctaLink = trim($data['cta_link'] ?? '/shop.php');
    $mobileImageUrl = trim($data['mobile_image_url'] ?? '');
    $position = in_array($data['position'] ?? '', ['hero', 'promo', 'exclusive_offer', 'middle_banner']) ? $data['position'] : 'hero';
    $displayOrder = (int)($data['display_order'] ?? 0);
    $isActive = !empty($data['is_active']) ? 1 : 0;
    $startsAt = !empty($data['starts_at']) ? $data['starts_at'] : null;
    $endsAt = !empty($data['ends_at']) ? $data['ends_at'] : null;

    try {
        if ($bannerId) {
            $stmt = $pdo->prepare("
                UPDATE banners SET
                    title = ?, subtitle = ?, badge_text = ?, cta_text = ?, cta_link = ?,
                    image_url = ?, mobile_image_url = ?, position = ?, display_order = ?,
                    is_active = ?, starts_at = ?, ends_at = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $title, $subtitle, $badgeText, $ctaText, $ctaLink,
                $imageUrl, $mobileImageUrl, $position, $displayOrder,
                $isActive, $startsAt, $endsAt, $bannerId
            ]);
            log_admin_activity($adminId, 'banner_update', 'banners', $bannerId, "Updated banner '$title'");
            return ['success' => true, 'banner_id' => $bannerId, 'message' => 'Banner updated successfully.'];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO banners (
                    title, subtitle, badge_text, cta_text, cta_link,
                    image_url, mobile_image_url, position, display_order,
                    is_active, starts_at, ends_at, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([
                $title, $subtitle, $badgeText, $ctaText, $ctaLink,
                $imageUrl, $mobileImageUrl, $position, $displayOrder,
                $isActive, $startsAt, $endsAt
            ]);
            $newId = (int)$pdo->lastInsertId();
            log_admin_activity($adminId, 'banner_create', 'banners', $newId, "Created banner '$title'");
            return ['success' => true, 'banner_id' => $newId, 'message' => 'Banner created successfully.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Delete banner
 */
function admin_delete_banner(int $bannerId, ?int $adminId = null): bool {
    try {
        $stmt = db()->prepare("DELETE FROM banners WHERE id = ?");
        $ok = $stmt->execute([$bannerId]);
        if ($ok) {
            log_admin_activity($adminId, 'banner_delete', 'banners', $bannerId, "Deleted banner #$bannerId");
        }
        return $ok;
    } catch (Exception $e) {
        return false;
    }
}

/* =====================================================================
   10. REVIEW MODERATION SUBSYSTEM
   ===================================================================== */

/**
 * Fetch paginated customer reviews with status filters
 */
function admin_get_reviews(array $filters = [], int $page = 1, int $perPage = 15): array {
    $pdo = db();
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    $where = ["1=1"];
    $params = [];

    if (!empty($filters['status']) && in_array($filters['status'], ['pending', 'approved', 'rejected'])) {
        $where[] = "r.status = :status";
        $params[':status'] = $filters['status'];
    }

    $whereSql = implode(' AND ', $where);

    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM product_reviews r WHERE $whereSql");
    $cntStmt->execute($params);
    $total = (int)$cntStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            p.name AS product_name, p.slug AS product_slug,
            c.first_name, c.last_name, c.email AS customer_email
        FROM product_reviews r
        JOIN products p ON r.product_id = p.id
        JOIN customers c ON r.customer_id = c.id
        WHERE $whereSql
        ORDER BY r.created_at DESC
        LIMIT :limit OFFSET :offset
    ");

    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $items = $stmt->fetchAll();

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => max(1, (int)ceil($total / $perPage))
    ];
}

/**
 * Moderate customer review (approve / reject) and update product rating aggregates
 */
function admin_update_review_status(int $reviewId, string $status, ?int $adminId = null): array {
    $pdo = db();
    if (!in_array($status, ['approved', 'rejected', 'pending'], true)) {
        return ['success' => false, 'message' => 'Invalid review status.'];
    }

    try {
        $pdo->beginTransaction();

        $revStmt = $pdo->prepare("SELECT product_id FROM product_reviews WHERE id = ? FOR UPDATE");
        $revStmt->execute([$reviewId]);
        $productId = $revStmt->fetchColumn();

        if (!$productId) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Review not found.'];
        }

        $pdo->prepare("UPDATE product_reviews SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$status, $reviewId]);

        // Recalculate product review count and average rating
        $calcStmt = $pdo->prepare("
            SELECT COUNT(*) AS total_revs, COALESCE(AVG(rating), 0) AS avg_rating
            FROM product_reviews
            WHERE product_id = ? AND status = 'approved'
        ");
        $calcStmt->execute([$productId]);
        $stats = $calcStmt->fetch();

        $pdo->prepare("UPDATE products SET review_count = ?, average_rating = ?, updated_at = NOW() WHERE id = ?")
            ->execute([(int)$stats['total_revs'], round((float)$stats['avg_rating'], 2), $productId]);

        log_admin_activity($adminId, 'review_moderate', 'product_reviews', $reviewId, "Review marked as '$status'");

        $pdo->commit();
        return ['success' => true, 'message' => "Review marked as '$status'."];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/* =====================================================================
   11. STORE SETTINGS MANAGEMENT SUBSYSTEM
   ===================================================================== */

/**
 * Fetch all store settings grouped by category
 */
function admin_get_all_settings(): array {
    $pdo = db();
    $rows = $pdo->query("SELECT setting_key, setting_value, setting_group FROM store_settings ORDER BY setting_group ASC, id ASC")->fetchAll();
    $grouped = [];
    foreach ($rows as $r) {
        $grouped[$r['setting_group']][$r['setting_key']] = $r['setting_value'];
    }
    return $grouped;
}

/**
 * Update multiple store settings atomically
 */
function admin_update_store_settings(array $settings, ?int $adminId = null): array {
    $pdo = db();

    $groupMap = [
        'store_name' => 'general',
        'store_tagline' => 'general',
        'store_email' => 'general',
        'store_phone' => 'general',
        'store_address' => 'general',
        'currency_code' => 'localization',
        'currency_symbol' => 'localization',
        'tax_rate_percent' => 'finance',
        'free_shipping_threshold' => 'shipping',
        'default_shipping_charge' => 'shipping',
        'announcement_bar_text' => 'marketing',
        'announcement_bar_link' => 'marketing',
        'social_instagram' => 'social',
        'social_pinterest' => 'social',
        'social_facebook' => 'social',
        'social_twitter' => 'social',
        'meta_title' => 'seo',
        'meta_description' => 'seo',
        'meta_keywords' => 'seo',
        'og_image_url' => 'seo',
        'canonical_base_url' => 'seo',
    ];

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("
            INSERT INTO store_settings (setting_key, setting_value, setting_group, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");

        $updatedKeys = [];
        foreach ($settings as $key => $val) {
            $k = clean_input($key);
            $v = trim((string)$val);
            $grp = $groupMap[$k] ?? 'general';
            $stmt->execute([$k, $v, $grp]);
            $updatedKeys[] = $k;
        }

        log_admin_activity($adminId, 'settings_update', 'store_settings', null, "Updated settings: " . implode(', ', $updatedKeys));

        $pdo->commit();
        return ['success' => true, 'message' => 'Store settings updated successfully.'];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/* =====================================================================
   12. AUDIT TRAIL / ACTIVITY LOG VIEWER
   ===================================================================== */

/**
 * Fetch paginated admin activity logs
 */
function admin_get_activity_logs(int $limit = 50, ?int $adminId = null): array {
    $pdo = db();
    $sql = "
        SELECT al.*, u.full_name AS admin_name, u.username AS admin_username
        FROM admin_activity_logs al
        LEFT JOIN admin_users u ON al.admin_id = u.id
    ";
    $params = [];

    if ($adminId !== null) {
        $sql .= " WHERE al.admin_id = ?";
        $params[] = $adminId;
    }

    $sql .= " ORDER BY al.created_at DESC, al.id DESC LIMIT " . (int)$limit;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/* =====================================================================
   11. HOMEPAGE SECTIONS & CIRCULAR STORIES MANAGEMENT
   ===================================================================== */

/**
 * Fetch all circular category stories for admin management
 */
function admin_get_homepage_stories(): array {
    $pdo = db();
    return $pdo->query("SELECT * FROM homepage_stories ORDER BY display_order ASC, id ASC")->fetchAll();
}

/**
 * Save (create or update) a circular category story
 */
function admin_save_homepage_story(array $data, ?int $id = null): array {
    $pdo = db();
    $title = clean_input($data['title'] ?? '');
    $imageUrl = clean_input($data['image_url'] ?? '');
    $linkUrl = clean_input($data['link_url'] ?? '/shop.php');
    $displayOrder = (int)($data['display_order'] ?? 0);
    $isActive = !empty($data['is_active']) ? 1 : 0;

    if (empty($title)) {
        return ['success' => false, 'message' => 'Story title/category name is required.'];
    }
    if (empty($imageUrl)) {
        return ['success' => false, 'message' => 'Story image URL is required.'];
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE homepage_stories SET
                    title = ?, image_url = ?, link_url = ?, display_order = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $imageUrl, $linkUrl, $displayOrder, $isActive, $id]);
            return ['success' => true, 'id' => $id, 'message' => "Story '{$title}' updated successfully."];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO homepage_stories (title, image_url, link_url, display_order, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$title, $imageUrl, $linkUrl, $displayOrder, $isActive]);
            return ['success' => true, 'id' => (int)$pdo->lastInsertId(), 'message' => "Story '{$title}' created successfully."];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error saving story: ' . $e->getMessage()];
    }
}

/**
 * Delete a circular category story
 */
function admin_delete_homepage_story(int $id): bool {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM homepage_stories WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Fetch all style stories / editorial cards
 */
function admin_get_style_stories(): array {
    $pdo = db();
    return $pdo->query("SELECT * FROM style_stories ORDER BY display_order ASC, id ASC")->fetchAll();
}

/**
 * Save (create or update) a style story / inspiration card
 */
function admin_save_style_story(array $data, ?int $id = null): array {
    $pdo = db();
    $title = clean_input($data['title'] ?? '');
    $tag = clean_input($data['tag'] ?? 'STYLE EDITORIAL');
    $excerpt = clean_input($data['excerpt'] ?? '');
    $imageUrl = clean_input($data['image_url'] ?? '');
    $linkUrl = clean_input($data['link_url'] ?? '/shop.php');
    $readTime = clean_input($data['read_time'] ?? '4 min read');
    $displayOrder = (int)($data['display_order'] ?? 0);
    $isActive = !empty($data['is_active']) ? 1 : 0;

    if (empty($title)) {
        return ['success' => false, 'message' => 'Story title is required.'];
    }
    if (empty($imageUrl)) {
        return ['success' => false, 'message' => 'Story editorial image is required.'];
    }

    try {
        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE style_stories SET
                    title = ?, tag = ?, excerpt = ?, image_url = ?, link_url = ?, read_time = ?, display_order = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$title, $tag, $excerpt, $imageUrl, $linkUrl, $readTime, $displayOrder, $isActive, $id]);
            return ['success' => true, 'id' => $id, 'message' => "Style story '{$title}' updated successfully."];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO style_stories (title, tag, excerpt, image_url, link_url, read_time, display_order, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$title, $tag, $excerpt, $imageUrl, $linkUrl, $readTime, $displayOrder, $isActive]);
            return ['success' => true, 'id' => (int)$pdo->lastInsertId(), 'message' => "Style story '{$title}' created successfully."];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error saving style story: ' . $e->getMessage()];
    }
}

/**
 * Delete a style story
 */
function admin_delete_style_story(int $id): bool {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM style_stories WHERE id = ?");
    return $stmt->execute([$id]);
}

/**
 * Fetch products assigned to a specific homepage section
 */
function admin_get_section_products(string $flag): array {
    $allowed = ['is_new', 'is_bestseller', 'is_summer_midi', 'is_trending_coord'];
    if (!in_array($flag, $allowed, true)) {
        return [];
    }
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT p.*, c.name AS category_name,
               (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS primary_image
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.{$flag} = 1
        ORDER BY p.updated_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Toggle or set a product's presence in a homepage section
 */
function admin_set_product_section_flag(int $productId, string $flag, int $value): bool {
    $allowed = ['is_new', 'is_bestseller', 'is_summer_midi', 'is_trending_coord'];
    if (!in_array($flag, $allowed, true)) {
        return false;
    }
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE products SET {$flag} = ?, updated_at = NOW() WHERE id = ?");
    return $stmt->execute([$value ? 1 : 0, $productId]);
}

/* =====================================================================
   12. HOMEPAGE CMS & SECTIONS SUBSYSTEM
   ===================================================================== */

/**
 * Fetch all homepage sections in saved display order
 */
function admin_get_homepage_sections(bool $includeArchived = false): array {
    $pdo = db();
    $sql = "SELECT hs.*, 
                   (SELECT COUNT(*) FROM homepage_section_items WHERE section_id = hs.id) AS item_count,
                   u.full_name AS updated_by_name
            FROM homepage_sections hs
            LEFT JOIN admin_users u ON hs.updated_by = u.id";
    if (!$includeArchived) {
        $sql .= " WHERE hs.publish_status != 'archived'";
    }
    $sql .= " ORDER BY hs.display_order ASC, hs.id ASC";
    return $pdo->query($sql)->fetchAll();
}

/**
 * Fetch single homepage section by ID
 */
function admin_get_homepage_section(int $id): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT hs.*, u.full_name AS updated_by_name
        FROM homepage_sections hs
        LEFT JOIN admin_users u ON hs.updated_by = u.id
        WHERE hs.id = ?
    ");
    $stmt->execute([$id]);
    $section = $stmt->fetch();
    return $section ?: null;
}

/**
 * Fetch single homepage section by key
 */
function admin_get_homepage_section_by_key(string $key): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM homepage_sections WHERE section_key = ?");
    $stmt->execute([$key]);
    $section = $stmt->fetch();
    return $section ?: null;
}

/**
 * Save / Update a homepage section
 */
function admin_save_homepage_section(array $data, ?int $id = null, ?int $adminId = null): array {
    $pdo = db();
    $title = trim($data['title'] ?? '');
    $sectionType = trim($data['section_type'] ?? 'product_collection');
    $sectionKey = trim($data['section_key'] ?? '');

    if (empty($title)) {
        return ['success' => false, 'message' => 'Section title is required.'];
    }
    if (empty($sectionKey)) {
        $sectionKey = strtolower(preg_replace('/[^a-zA-Z0-9_]+/', '_', $title));
    }

    // Check unique section key
    if ($id) {
        $checkStmt = $pdo->prepare("SELECT id FROM homepage_sections WHERE section_key = ? AND id != ?");
        $checkStmt->execute([$sectionKey, $id]);
    } else {
        $checkStmt = $pdo->prepare("SELECT id FROM homepage_sections WHERE section_key = ?");
        $checkStmt->execute([$sectionKey]);
    }
    if ($checkStmt->fetch()) {
        $sectionKey .= '_' . time();
    }

    $eyebrow = trim($data['eyebrow'] ?? '');
    $subtitle = trim($data['subtitle'] ?? '');
    $description = trim($data['description'] ?? '');
    $ctaText = trim($data['cta_text'] ?? '');
    $ctaUrl = trim($data['cta_url'] ?? '');
    $bgStyle = trim($data['background_style'] ?? 'default');
    $layoutStyle = trim($data['layout_style'] ?? 'standard');
    $displayOrder = (int)($data['display_order'] ?? 0);
    $isActive = !empty($data['is_active']) ? 1 : 0;
    $publishStatus = in_array($data['publish_status'] ?? '', ['draft', 'published', 'archived'], true) ? $data['publish_status'] : 'published';
    $selectionMode = in_array($data['selection_mode'] ?? '', ['automatic', 'manual'], true) ? $data['selection_mode'] : 'automatic';
    $configData = is_array($data['config_data'] ?? null) ? json_encode($data['config_data']) : (string)($data['config_data'] ?? '');
    $publishedAt = ($publishStatus === 'published') ? date('Y-m-d H:i:s') : null;

    try {
        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE homepage_sections SET
                    section_key = :section_key,
                    title = :title,
                    section_type = :section_type,
                    eyebrow = :eyebrow,
                    subtitle = :subtitle,
                    description = :description,
                    cta_text = :cta_text,
                    cta_url = :cta_url,
                    background_style = :background_style,
                    layout_style = :layout_style,
                    display_order = :display_order,
                    is_active = :is_active,
                    publish_status = :publish_status,
                    selection_mode = :selection_mode,
                    config_data = :config_data,
                    updated_by = :updated_by,
                    published_at = CASE WHEN published_at IS NULL AND :pub_flag = 1 THEN NOW() ELSE published_at END,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':section_key' => $sectionKey,
                ':title' => $title,
                ':section_type' => $sectionType,
                ':eyebrow' => $eyebrow,
                ':subtitle' => $subtitle,
                ':description' => $description,
                ':cta_text' => $ctaText,
                ':cta_url' => $ctaUrl,
                ':background_style' => $bgStyle,
                ':layout_style' => $layoutStyle,
                ':display_order' => $displayOrder,
                ':is_active' => $isActive,
                ':publish_status' => $publishStatus,
                ':selection_mode' => $selectionMode,
                ':config_data' => $configData,
                ':updated_by' => $adminId,
                ':pub_flag' => ($publishStatus === 'published' ? 1 : 0),
                ':id' => $id
            ]);

            log_admin_activity($adminId, 'update_homepage_section', 'homepage_sections', $id, "Updated section: $title");
            return ['success' => true, 'id' => $id, 'message' => "Homepage section '{$title}' updated successfully."];
        } else {
            // Auto order to end if not specified
            if ($displayOrder <= 0) {
                $maxOrder = (int)$pdo->query("SELECT MAX(display_order) FROM homepage_sections")->fetchColumn();
                $displayOrder = $maxOrder + 1;
            }

            $stmt = $pdo->prepare("
                INSERT INTO homepage_sections (
                    section_key, title, section_type, eyebrow, subtitle, description,
                    cta_text, cta_url, background_style, layout_style, display_order,
                    is_active, publish_status, selection_mode, config_data,
                    published_at, created_by, updated_by
                ) VALUES (
                    :section_key, :title, :section_type, :eyebrow, :subtitle, :description,
                    :cta_text, :cta_url, :background_style, :layout_style, :display_order,
                    :is_active, :publish_status, :selection_mode, :config_data,
                    :published_at,
                    :created_by, :updated_by
                )
            ");
            $stmt->execute([
                ':section_key' => $sectionKey,
                ':title' => $title,
                ':section_type' => $sectionType,
                ':eyebrow' => $eyebrow,
                ':subtitle' => $subtitle,
                ':description' => $description,
                ':cta_text' => $ctaText,
                ':cta_url' => $ctaUrl,
                ':background_style' => $bgStyle,
                ':layout_style' => $layoutStyle,
                ':display_order' => $displayOrder,
                ':is_active' => $isActive,
                ':publish_status' => $publishStatus,
                ':selection_mode' => $selectionMode,
                ':config_data' => $configData,
                ':published_at' => $publishedAt,
                ':created_by' => $adminId,
                ':updated_by' => $adminId
            ]);
            $newId = (int)$pdo->lastInsertId();

            log_admin_activity($adminId, 'create_homepage_section', 'homepage_sections', $newId, "Created section: $title");
            return ['success' => true, 'id' => $newId, 'message' => "Homepage section '{$title}' created successfully."];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Reorder sections by ID => order array
 */
function admin_reorder_homepage_sections(array $orderMap): bool {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE homepage_sections SET display_order = ?, updated_at = NOW() WHERE id = ?");
    $pdo->beginTransaction();
    try {
        foreach ($orderMap as $id => $order) {
            $stmt->execute([(int)$order, (int)$id]);
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Toggle active status of a section
 */
function admin_toggle_homepage_section_status(int $id, int $isActive): bool {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE homepage_sections SET is_active = ?, updated_at = NOW() WHERE id = ?");
    return $stmt->execute([$isActive ? 1 : 0, $id]);
}

/**
 * Publish a section
 */
function admin_publish_homepage_section(int $id, ?int $adminId = null): bool {
    $pdo = db();
    $stmt = $pdo->prepare("
        UPDATE homepage_sections 
        SET publish_status = 'published', published_at = NOW(), updated_by = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $res = $stmt->execute([$adminId, $id]);
    if ($res) {
        log_admin_activity($adminId, 'publish_homepage_section', 'homepage_sections', $id, "Published homepage section #$id");
    }
    return $res;
}

/**
 * Archive a section (safe soft delete)
 */
function admin_archive_homepage_section(int $id, ?int $adminId = null): bool {
    $pdo = db();
    $stmt = $pdo->prepare("
        UPDATE homepage_sections 
        SET publish_status = 'archived', is_active = 0, updated_by = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $res = $stmt->execute([$adminId, $id]);
    if ($res) {
        log_admin_activity($adminId, 'archive_homepage_section', 'homepage_sections', $id, "Archived section #$id");
    }
    return $res;
}

/**
 * Permanently delete a homepage section
 */
function admin_delete_homepage_section(int $id, ?int $adminId = null): bool {
    $pdo = db();
    $pdo->prepare("DELETE FROM homepage_section_items WHERE section_id = ?")->execute([$id]);
    $stmt = $pdo->prepare("DELETE FROM homepage_sections WHERE id = ?");
    $res = $stmt->execute([$id]);
    if ($res) {
        log_admin_activity($adminId, 'delete_homepage_section', 'homepage_sections', $id, "Deleted homepage section #$id");
    }
    return $res;
}

/**
 * Duplicate a section
 */
function admin_duplicate_homepage_section(int $id, ?int $adminId = null): array {
    $section = admin_get_homepage_section($id);
    if (!$section) {
        return ['success' => false, 'message' => 'Source section not found.'];
    }

    $data = $section;
    unset($data['id']);
    $data['title'] = $section['title'] . ' (Copy)';
    $data['section_key'] = $section['section_key'] . '_copy_' . time();
    $data['publish_status'] = 'draft';
    $data['display_order'] = (int)$section['display_order'] + 1;

    $res = admin_save_homepage_section($data, null, $adminId);
    if ($res['success'] && !empty($res['id'])) {
        // Also duplicate items
        $pdo = db();
        $items = $pdo->query("SELECT * FROM homepage_section_items WHERE section_id = $id")->fetchAll();
        $insItem = $pdo->prepare("
            INSERT INTO homepage_section_items (
                section_id, item_type, item_id, title, subtitle, image_url,
                link_url, badge_text, display_order, is_active, custom_data
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($items as $it) {
            $insItem->execute([
                $res['id'], $it['item_type'], $it['item_id'], $it['title'],
                $it['subtitle'], $it['image_url'], $it['link_url'], $it['badge_text'],
                $it['display_order'], $it['is_active'], $it['custom_data']
            ]);
        }
    }
    return $res;
}

/**
 * Fetch items (curated products/categories/banners) for a section
 */
function admin_get_section_items(int $sectionId): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT hsi.*, 
               p.name AS product_name, p.sku AS product_sku, p.selling_price AS product_price,
               (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS product_image,
               c.name AS category_name, c.slug AS category_slug
        FROM homepage_section_items hsi
        LEFT JOIN products p ON hsi.item_type = 'product' AND hsi.item_id = p.id
        LEFT JOIN categories c ON hsi.item_type = 'category' AND hsi.item_id = c.id
        WHERE hsi.section_id = ?
        ORDER BY hsi.display_order ASC, hsi.id ASC
    ");
    $stmt->execute([$sectionId]);
    return $stmt->fetchAll();
}

/**
 * Assign curated products to a section with custom ordering
 */
function admin_assign_products_to_section(int $sectionId, array $productIds): bool {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Clear existing product items for this section
        $delStmt = $pdo->prepare("DELETE FROM homepage_section_items WHERE section_id = ? AND item_type = 'product'");
        $delStmt->execute([$sectionId]);

        $insStmt = $pdo->prepare("
            INSERT INTO homepage_section_items (section_id, item_type, item_id, display_order, is_active)
            VALUES (?, 'product', ?, ?, 1)
        ");
        $order = 1;
        foreach ($productIds as $pId) {
            $pId = (int)$pId;
            if ($pId > 0) {
                $insStmt->execute([$sectionId, $pId, $order++]);
            }
        }
        // Switch section to manual mode
        $pdo->prepare("UPDATE homepage_sections SET selection_mode = 'manual', updated_at = NOW() WHERE id = ?")->execute([$sectionId]);
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Remove an item from a section
 */
function admin_remove_item_from_section(int $itemId): bool {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM homepage_section_items WHERE id = ?");
    return $stmt->execute([$itemId]);
}

/* =====================================================================
   13. COLLECTIONS SUBSYSTEM
   ===================================================================== */

/**
 * Fetch all collections
 */
function admin_get_collections(): array {
    $pdo = db();
    return $pdo->query("
        SELECT c.*, 
               (SELECT COUNT(*) FROM collection_products WHERE collection_id = c.id) AS product_count
        FROM collections c
        ORDER BY c.display_order ASC, c.id ASC
    ")->fetchAll();
}

/**
 * Fetch single collection by ID
 */
function admin_get_collection(int $id): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM collections WHERE id = ?");
    $stmt->execute([$id]);
    $c = $stmt->fetch();
    return $c ?: null;
}

/**
 * Save / Update collection
 */
function admin_save_collection(array $data, ?int $id = null, ?int $adminId = null): array {
    $pdo = db();
    $name = trim($data['name'] ?? '');
    $slug = trim($data['slug'] ?? '');
    if (empty($name)) {
        return ['success' => false, 'message' => 'Collection name is required.'];
    }
    if (empty($slug)) {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    }

    $tagline = trim($data['tagline'] ?? '');
    $description = trim($data['description'] ?? '');
    $coverImage = trim($data['cover_image'] ?? '');
    $bannerImage = trim($data['banner_image'] ?? '');
    $seoTitle = trim($data['seo_title'] ?? '');
    $seoDesc = trim($data['seo_description'] ?? '');
    $displayOrder = (int)($data['display_order'] ?? 0);
    $isFeatured = !empty($data['is_featured']) ? 1 : 0;
    $isActive = !empty($data['is_active']) ? 1 : 0;

    try {
        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE collections SET
                    name = ?, slug = ?, tagline = ?, description = ?,
                    cover_image = ?, banner_image = ?, seo_title = ?, seo_description = ?,
                    display_order = ?, is_featured = ?, is_active = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$name, $slug, $tagline, $description, $coverImage, $bannerImage, $seoTitle, $seoDesc, $displayOrder, $isFeatured, $isActive, $id]);
            log_admin_activity($adminId, 'update_collection', 'collections', $id, "Updated collection $name");
            return ['success' => true, 'id' => $id, 'message' => "Collection '{$name}' updated successfully."];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO collections (
                    name, slug, tagline, description, cover_image, banner_image,
                    seo_title, seo_description, display_order, is_featured, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $slug, $tagline, $description, $coverImage, $bannerImage, $seoTitle, $seoDesc, $displayOrder, $isFeatured, $isActive]);
            $newId = (int)$pdo->lastInsertId();
            log_admin_activity($adminId, 'create_collection', 'collections', $newId, "Created collection $name");
            return ['success' => true, 'id' => $newId, 'message' => "Collection '{$name}' created successfully."];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Delete a collection
 */
function admin_delete_collection(int $id, ?int $adminId = null): array {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM collections WHERE id = ?");
    $res = $stmt->execute([$id]);
    if ($res) {
        log_admin_activity($adminId, 'delete_collection', 'collections', $id, "Deleted collection #$id");
        return ['success' => true, 'message' => 'Collection deleted successfully.'];
    }
    return ['success' => false, 'message' => 'Failed to delete collection.'];
}

/**
 * Get products assigned to a collection
 */
function admin_get_collection_products(int $collectionId): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT p.*, cp.display_order AS collection_order,
               c.name AS category_name,
               (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS primary_image
        FROM collection_products cp
        JOIN products p ON cp.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE cp.collection_id = ?
        ORDER BY cp.display_order ASC
    ");
    $stmt->execute([$collectionId]);
    return $stmt->fetchAll();
}

/**
 * Assign products to a collection
 */
function admin_assign_collection_products(int $collectionId, array $productIds): bool {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare("DELETE FROM collection_products WHERE collection_id = ?");
        $del->execute([$collectionId]);

        $ins = $pdo->prepare("INSERT INTO collection_products (collection_id, product_id, display_order) VALUES (?, ?, ?)");
        $order = 1;
        foreach ($productIds as $pId) {
            $pId = (int)$pId;
            if ($pId > 0) {
                $ins->execute([$collectionId, $pId, $order++]);
            }
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/* =====================================================================
   14. NAVIGATION & MEGA MENU SUBSYSTEM
   ===================================================================== */

/**
 * Fetch navigation menu items
 */
function admin_get_navigation_menus(string $type = 'header'): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT * FROM navigation_menus
        WHERE menu_type = ?
        ORDER BY display_order ASC, id ASC
    ");
    $stmt->execute([$type]);
    return $stmt->fetchAll();
}

/**
 * Save / Update navigation menu item
 */
function admin_save_navigation_menu(array $data, ?int $id = null, ?int $adminId = null): array {
    $pdo = db();
    $label = trim($data['label'] ?? '');
    $url = trim($data['url'] ?? '');
    if (empty($label) || empty($url)) {
        return ['success' => false, 'message' => 'Navigation label and URL are required.'];
    }

    $parentId = !empty($data['parent_id']) ? (int)$data['parent_id'] : null;
    $menuType = in_array($data['menu_type'] ?? '', ['header', 'mega_menu', 'footer_1', 'footer_2', 'footer_3', 'mobile'], true) ? $data['menu_type'] : 'header';
    $megaKey = trim($data['mega_category_key'] ?? '') ?: null;
    $badgeText = trim($data['badge_text'] ?? '') ?: null;
    $promoTitle = trim($data['promo_title'] ?? '') ?: null;
    $promoImage = trim($data['promo_image'] ?? '') ?: null;
    $promoLink = trim($data['promo_link'] ?? '') ?: null;
    $displayOrder = (int)($data['display_order'] ?? 0);
    $isActive = !empty($data['is_active']) ? 1 : 0;
    $target = in_array($data['target'] ?? '', ['_self', '_blank'], true) ? $data['target'] : '_self';

    try {
        if ($id) {
            $stmt = $pdo->prepare("
                UPDATE navigation_menus SET
                    parent_id = ?, label = ?, url = ?, menu_type = ?, mega_category_key = ?,
                    badge_text = ?, promo_title = ?, promo_image = ?, promo_link = ?,
                    display_order = ?, is_active = ?, target = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $parentId, $label, $url, $menuType, $megaKey,
                $badgeText, $promoTitle, $promoImage, $promoLink,
                $displayOrder, $isActive, $target, $id
            ]);
            log_admin_activity($adminId, 'update_navigation_menu', 'navigation_menus', $id, "Updated navigation item: $label");
            return ['success' => true, 'id' => $id, 'message' => "Navigation item '{$label}' updated successfully."];
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO navigation_menus (
                    parent_id, label, url, menu_type, mega_category_key,
                    badge_text, promo_title, promo_image, promo_link,
                    display_order, is_active, target
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $parentId, $label, $url, $menuType, $megaKey,
                $badgeText, $promoTitle, $promoImage, $promoLink,
                $displayOrder, $isActive, $target
            ]);
            $newId = (int)$pdo->lastInsertId();
            log_admin_activity($adminId, 'create_navigation_menu', 'navigation_menus', $newId, "Created navigation item: $label");
            return ['success' => true, 'id' => $newId, 'message' => "Navigation item '{$label}' created successfully."];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Delete a navigation menu item
 */
function admin_delete_navigation_menu(int $id, ?int $adminId = null): bool {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM navigation_menus WHERE id = ?");
    $res = $stmt->execute([$id]);
    if ($res) {
        log_admin_activity($adminId, 'delete_navigation_menu', 'navigation_menus', $id, "Deleted navigation menu item #$id");
    }
    return $res;
}

/**
 * Reorder navigation menus
 */
function admin_reorder_navigation_menus(array $orderMap): bool {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE navigation_menus SET display_order = ? WHERE id = ?");
    $pdo->beginTransaction();
    try {
        foreach ($orderMap as $id => $order) {
            $stmt->execute([(int)$order, (int)$id]);
        }
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/* =====================================================================
   15. MEDIA LIBRARY SUBSYSTEM
   ===================================================================== */

/**
 * Fetch paginated media library items
 */
function admin_get_media(array $filters = [], int $page = 1, int $perPage = 24): array {
    $pdo = db();
    $where = [];
    $params = [];

    if (!empty($filters['folder'])) {
        $where[] = "folder = ?";
        $params[] = $filters['folder'];
    }
    if (!empty($filters['search'])) {
        $where[] = "(file_name LIKE ? OR original_name LIKE ? OR alt_text LIKE ?)";
        $term = '%' . $filters['search'] . '%';
        $params[] = $term;
        $params[] = $term;
        $params[] = $term;
    }

    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM media_library $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT * FROM media_library $whereSql ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages
    ];
}

/**
 * Upload and register media file
 */
function admin_upload_media(array $file, string $folder = 'general', ?string $altText = null, ?int $adminId = null): array {
    $uploadRes = admin_validate_image_upload($file, $folder);
    if (!$uploadRes['success']) {
        return $uploadRes;
    }

    $pdo = db();
    $fileName = basename($uploadRes['path']);
    $origName = $file['name'];
    $mimeType = $uploadRes['mime'];
    $fileSize = (int)$file['size'];
    $url = $uploadRes['url'];
    $path = $uploadRes['path'];

    // Get image dimensions if available
    $width = null;
    $height = null;
    if (file_exists($path)) {
        $dim = @getimagesize($path);
        if ($dim) {
            $width = $dim[0];
            $height = $dim[1];
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO media_library (
            file_name, original_name, file_path, file_url,
            mime_type, file_size, width, height, alt_text, folder, uploaded_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $fileName, $origName, $path, $url,
        $mimeType, $fileSize, $width, $height,
        $altText ?: pathinfo($origName, PATHINFO_FILENAME),
        $folder, $adminId
    ]);
    $newId = (int)$pdo->lastInsertId();

    log_admin_activity($adminId, 'upload_media', 'media_library', $newId, "Uploaded media file: $origName");

    return [
        'success' => true,
        'id' => $newId,
        'url' => $url,
        'file_name' => $fileName,
        'message' => 'Media file uploaded successfully.'
    ];
}

/**
 * Check if media URL or file is referenced before deleting
 */
function admin_check_media_references(string $fileUrl): array {
    $pdo = db();
    $refs = [];

    // Check products images
    $pCount = (int)$pdo->prepare("SELECT COUNT(*) FROM product_images WHERE image_url LIKE ?")
                       ->execute(["%$fileUrl%"]) ? $pdo->query("SELECT COUNT(*) FROM product_images WHERE image_url LIKE '%" . addslashes($fileUrl) . "%'")->fetchColumn() : 0;
    if ($pCount > 0) $refs[] = "$pCount product image(s)";

    // Check banners
    $bCount = (int)$pdo->query("SELECT COUNT(*) FROM banners WHERE image_url LIKE '%" . addslashes($fileUrl) . "%'")->fetchColumn();
    if ($bCount > 0) $refs[] = "$bCount banner(s)";

    // Check categories
    $cCount = (int)$pdo->query("SELECT COUNT(*) FROM categories WHERE image_url LIKE '%" . addslashes($fileUrl) . "%' OR banner_url LIKE '%" . addslashes($fileUrl) . "%'")->fetchColumn();
    if ($cCount > 0) $refs[] = "$cCount category card(s)";

    // Check collections
    $colCount = (int)$pdo->query("SELECT COUNT(*) FROM collections WHERE cover_image LIKE '%" . addslashes($fileUrl) . "%' OR banner_image LIKE '%" . addslashes($fileUrl) . "%'")->fetchColumn();
    if ($colCount > 0) $refs[] = "$colCount collection(s)";

    // Check homepage section items
    $hsCount = (int)$pdo->query("SELECT COUNT(*) FROM homepage_section_items WHERE image_url LIKE '%" . addslashes($fileUrl) . "%'")->fetchColumn();
    if ($hsCount > 0) $refs[] = "$hsCount homepage section item(s)";

    return $refs;
}

/**
 * Safe deletion of media library item with reference protection
 */
function admin_delete_media(int $id, ?int $adminId = null): array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM media_library WHERE id = ?");
    $stmt->execute([$id]);
    $media = $stmt->fetch();
    if (!$media) {
        return ['success' => false, 'message' => 'Media item not found.'];
    }

    $refs = admin_check_media_references($media['file_url']);
    if (!empty($refs)) {
        return [
            'success' => false,
            'message' => 'Cannot delete media item. It is currently referenced by: ' . implode(', ', $refs) . '.'
        ];
    }

    // Safe delete record
    $pdo->prepare("DELETE FROM media_library WHERE id = ?")->execute([$id]);

    // Delete local disk file if exists
    if (!empty($media['file_path']) && file_exists($media['file_path'])) {
        @unlink($media['file_path']);
    }

    log_admin_activity($adminId, 'delete_media', 'media_library', $id, "Deleted media: " . $media['file_name']);
    return ['success' => true, 'message' => 'Media file deleted successfully.'];
}

/**
 * Update media alt text & metadata
 */
function admin_update_media_meta(int $id, string $altText, ?string $caption = null): bool {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE media_library SET alt_text = ?, caption = ?, updated_at = NOW() WHERE id = ?");
    return $stmt->execute([$altText, $caption, $id]);
}

/* =====================================================================
   16. RETURNS & REFUNDS SUBSYSTEM
   ===================================================================== */

/**
 * Fetch customer return requests with order and patron details
 */
function admin_get_returns(array $filters = [], int $page = 1, int $perPage = 15): array {
    $pdo = db();
    $where = [];
    $params = [];

    if (!empty($filters['status'])) {
        $where[] = "rr.status = ?";
        $params[] = $filters['status'];
    }

    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM return_requests rr $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $sql = "
        SELECT rr.*, o.order_number, o.grand_total,
               c.first_name, c.last_name, c.email AS customer_email,
               oi.product_name, oi.unit_price, oi.quantity AS item_quantity
        FROM return_requests rr
        JOIN orders o ON rr.order_id = o.id
        JOIN customers c ON rr.customer_id = c.id
        LEFT JOIN order_items oi ON rr.order_item_id = oi.id
        $whereSql
        ORDER BY rr.requested_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    return [
        'returns' => $items,
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages
    ];
}

/**
 * Update return request status and admin remarks
 */
function admin_update_return_status(int $id, string $status, ?string $remarks = null, ?int $adminId = null): bool {
    $allowed = ['requested', 'approved', 'pickup_scheduled', 'item_received', 'refund_processed', 'rejected'];
    if (!in_array($status, $allowed, true)) {
        return false;
    }
    $pdo = db();
    $stmt = $pdo->prepare("
        UPDATE return_requests 
        SET status = ?, admin_remarks = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $res = $stmt->execute([$status, $remarks, $id]);
    if ($res) {
        log_admin_activity($adminId, 'update_return_status', 'return_requests', $id, "Updated return status to $status");
    }
    return $res;
}

/* =====================================================================
   17. NEWSLETTER SUBSCRIBERS SUBSYSTEM
   ===================================================================== */

/**
 * Fetch paginated newsletter subscribers
 */
function admin_get_subscribers(int $page = 1, int $perPage = 25, string $search = ''): array {
    $pdo = db();
    $params = [];
    $where = "WHERE 1=1";
    if (!empty($search)) {
        $where .= " AND email LIKE ?";
        $params[] = '%' . $search . '%';
    }

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM newsletter_subscribers $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare("SELECT * FROM newsletter_subscribers $where ORDER BY subscribed_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);

    // Also get quick stats
    $activeCount = (int)$pdo->query("SELECT COUNT(*) FROM newsletter_subscribers WHERE status = 'subscribed'")->fetchColumn();

    return [
        'subscribers' => $stmt->fetchAll(),
        'total' => $total,
        'active_count' => $activeCount,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages
    ];
}

/**
 * Delete or unsubscribe newsletter subscriber
 */
function admin_delete_subscriber(int $id, ?int $adminId = null): array {
    $pdo = db();
    try {
        $stmt = $pdo->prepare("DELETE FROM newsletter_subscribers WHERE id = ?");
        $stmt->execute([$id]);
        log_admin_activity($adminId, 'delete_subscriber', 'newsletter_subscribers', $id, "Deleted newsletter subscriber #$id");
        return ['success' => true, 'message' => 'Subscriber record deleted successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/* =====================================================================
   18. ADMIN USERS & ROLES SUBSYSTEM
   ===================================================================== */

/**
 * Fetch all admin users
 */
function admin_get_users(): array {
    $pdo = db();
    return $pdo->query("
        SELECT id, username, email, full_name, role, status, last_login_at, created_at
        FROM admin_users
        ORDER BY id ASC
    ")->fetchAll();
}

/**
 * Delete an admin user with safety checks
 */
function admin_delete_user(int $id, int $currentAdminId): array {
    $pdo = db();
    if ($id === $currentAdminId) {
        return ['success' => false, 'message' => 'You cannot delete your own administrator account.'];
    }
    // Check if user is the last super_admin
    $stmt = $pdo->prepare("SELECT role FROM admin_users WHERE id = ?");
    $stmt->execute([$id]);
    $userRole = $stmt->fetchColumn();
    if ($userRole === 'super_admin') {
        $superCount = (int)$pdo->query("SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND status = 'active'")->fetchColumn();
        if ($superCount <= 1) {
            return ['success' => false, 'message' => 'Cannot delete the only active Super Administrator.'];
        }
    }
    try {
        $del = $pdo->prepare("DELETE FROM admin_users WHERE id = ?");
        $del->execute([$id]);
        log_admin_activity($currentAdminId, 'delete_admin_user', 'admin_users', $id, "Deleted staff account #$id");
        return ['success' => true, 'message' => 'Administrator account deleted successfully.'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Save / Update admin user
 */
function admin_save_user(array $data, ?int $id = null, ?int $adminId = null): array {
    $pdo = db();
    $username = clean_input($data['username'] ?? '');
    $email = clean_input($data['email'] ?? '');
    $fullName = clean_input($data['full_name'] ?? '');
    $role = in_array($data['role'] ?? '', ['super_admin', 'admin', 'editor', 'support'], true) ? $data['role'] : 'editor';
    $status = in_array($data['status'] ?? '', ['active', 'inactive', 'suspended'], true) ? $data['status'] : 'active';
    $password = $data['password'] ?? '';

    if (empty($username) || empty($email) || empty($fullName)) {
        return ['success' => false, 'message' => 'Username, email and full name are required.'];
    }

    try {
        if ($id) {
            if (!empty($password)) {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare("
                    UPDATE admin_users SET
                        username = ?, email = ?, full_name = ?, role = ?, status = ?, password_hash = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$username, $email, $fullName, $role, $status, $hash, $id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE admin_users SET
                        username = ?, email = ?, full_name = ?, role = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$username, $email, $fullName, $role, $status, $id]);
            }
            log_admin_activity($adminId, 'update_admin_user', 'admin_users', $id, "Updated staff user $username");
            return ['success' => true, 'id' => $id, 'message' => "Admin user '{$username}' updated successfully."];
        } else {
            if (empty($password)) {
                return ['success' => false, 'message' => 'Password is required for new staff accounts.'];
            }
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("
                INSERT INTO admin_users (username, email, password_hash, full_name, role, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$username, $email, $hash, $fullName, $role, $status]);
            $newId = (int)$pdo->lastInsertId();
            log_admin_activity($adminId, 'create_admin_user', 'admin_users', $newId, "Created staff user $username");
            return ['success' => true, 'id' => $newId, 'message' => "Admin user '{$username}' created successfully."];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Delete a product image
 */
function admin_delete_product_image(int $imageId): bool {
    $pdo = db();
    $stmt = $pdo->prepare("DELETE FROM product_images WHERE id = ?");
    return $stmt->execute([$imageId]);
}

/**
 * Set a product image as primary
 */
function admin_set_primary_product_image(int $productId, int $imageId): bool {
    $pdo = db();
    $pdo->prepare("UPDATE product_images SET is_primary = 0 WHERE product_id = ?")->execute([$productId]);
    $stmt = $pdo->prepare("UPDATE product_images SET is_primary = 1 WHERE id = ? AND product_id = ?");
    return $stmt->execute([$imageId, $productId]);
}

/**
 * Fetch all concise products for quick selector dropdowns/modals
 */
function admin_get_all_products_concise(): array {
    $pdo = db();
    return $pdo->query("
        SELECT p.id, p.name, p.sku, p.selling_price, p.stock_quantity, p.status,
               c.name AS category_name,
               (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image_url
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE p.status != 'archived'
        ORDER BY p.name ASC
    ")->fetchAll();
}



