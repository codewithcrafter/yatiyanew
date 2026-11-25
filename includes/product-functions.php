<?php
/**
 * YATIYA COUTURE - Product Catalogue Database & Filtering Engine
 * Strict Core PHP & PDO Prepared Statements (Zero Frameworks)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Fetch filtered, sorted and paginated products
 *
 * @param array $filters Query parameters
 * @param int $page Current page (1-based)
 * @param int $perPage Products per page
 * @return array Result containing products, pagination stats, and counts
 */
function get_filtered_products(array $filters = [], int $page = 1, int $perPage = 12): array {
    $pdo = db();

    // Whitelist Sort Column / Orders
    $sort = $filters['sort'] ?? 'newest';
    $sortClause = match ($sort) {
        'price_asc'   => 'p.selling_price ASC, p.id DESC',
        'price_desc'  => 'p.selling_price DESC, p.id DESC',
        'bestselling' => 'p.is_bestseller DESC, p.created_at DESC',
        'popular'     => 'p.is_featured DESC, p.created_at DESC',
        default       => 'p.created_at DESC, p.id DESC', // 'newest'
    };

    $conditions = ["p.status = 'active'"];
    $bindings = [];
    $joins = [];

    // 1. Category / Subcategory Filter
    if (!empty($filters['category'])) {
        $cat = clean_input($filters['category']);
        // Match either this category slug or parent category slug
        $conditions[] = "(c.slug = :cat OR c.parent_id = (SELECT id FROM categories WHERE slug = :cat_parent LIMIT 1))";
        $bindings[':cat'] = $cat;
        $bindings[':cat_parent'] = $cat;
    }

    if (!empty($filters['subcategory'])) {
        $subcat = clean_input($filters['subcategory']);
        $conditions[] = "c.slug = :subcat";
        $bindings[':subcat'] = $subcat;
    }

    // 2. Minimum Price
    if (isset($filters['min_price']) && is_numeric($filters['min_price']) && (float)$filters['min_price'] > 0) {
        $conditions[] = "p.selling_price >= :min_price";
        $bindings[':min_price'] = (float)$filters['min_price'];
    }

    // 3. Maximum Price
    if (isset($filters['max_price']) && is_numeric($filters['max_price']) && (float)$filters['max_price'] > 0) {
        $conditions[] = "p.selling_price <= :max_price";
        $bindings[':max_price'] = (float)$filters['max_price'];
    }

    // 4. Size Filter (Matches product_variants)
    if (!empty($filters['size'])) {
        $sizes = is_array($filters['size']) ? $filters['size'] : [$filters['size']];
        $sizes = array_filter(array_map('clean_input', $sizes));
        if (!empty($sizes)) {
            $joins['variants'] = "INNER JOIN product_variants pv ON p.id = pv.product_id AND pv.status = 'active'";
            $placeholders = [];
            foreach ($sizes as $idx => $s) {
                $param = ":sz_$idx";
                $placeholders[] = $param;
                $bindings[$param] = $s;
            }
            $conditions[] = "pv.size IN (" . implode(',', $placeholders) . ")";
        }
    }

    // 5. Color Filter
    if (!empty($filters['color'])) {
        $colors = is_array($filters['color']) ? $filters['color'] : [$filters['color']];
        $colors = array_filter(array_map('clean_input', $colors));
        if (!empty($colors)) {
            if (!isset($joins['variants'])) {
                $joins['variants'] = "INNER JOIN product_variants pv ON p.id = pv.product_id AND pv.status = 'active'";
            }
            $placeholders = [];
            foreach ($colors as $idx => $c) {
                $param = ":col_$idx";
                $placeholders[] = $param;
                $bindings[$param] = $c;
            }
            $conditions[] = "pv.color IN (" . implode(',', $placeholders) . ")";
        }
    }

    // 6. Availability Filter (In Stock Only)
    if (!empty($filters['availability']) && $filters['availability'] === 'in_stock') {
        $conditions[] = "p.stock_quantity > 0";
    }

    // 7. Gender Filter
    if (!empty($filters['gender'])) {
        $gender = clean_input($filters['gender']);
        if ($gender === 'women') {
            $conditions[] = "(c.slug = 'women' OR c.parent_id = (SELECT id FROM categories WHERE slug = 'women' LIMIT 1))";
        } elseif ($gender === 'men') {
            $conditions[] = "(c.slug = 'men' OR c.parent_id = (SELECT id FROM categories WHERE slug = 'men' LIMIT 1))";
        } elseif ($gender === 'kids') {
            $conditions[] = "(c.slug = 'kids' OR c.parent_id = (SELECT id FROM categories WHERE slug = 'kids' LIMIT 1))";
        }
    }

    // 8. Search Query (Name, SKU, Description, Category)
    if (!empty($filters['q'])) {
        $q = clean_input($filters['q']);
        $conditions[] = "(p.name LIKE :sq_1 OR p.sku LIKE :sq_2 OR p.short_description LIKE :sq_3 OR p.description LIKE :sq_4 OR c.name LIKE :sq_5)";
        $searchTerm = '%' . $q . '%';
        $bindings[':sq_1'] = $searchTerm;
        $bindings[':sq_2'] = $searchTerm;
        $bindings[':sq_3'] = $searchTerm;
        $bindings[':sq_4'] = $searchTerm;
        $bindings[':sq_5'] = $searchTerm;
    }

    // Construct Base SQL
    $whereSql = implode(' AND ', $conditions);
    $joinSql = implode(' ', array_values($joins));

    // A. Count Query
    $countSql = "
        SELECT COUNT(DISTINCT p.id) 
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        $joinSql
        WHERE $whereSql
    ";
    $countStmt = $pdo->prepare($countSql);
    foreach ($bindings as $key => $val) {
        $countStmt->bindValue($key, $val);
    }
    $countStmt->execute();
    $totalCount = (int)$countStmt->fetchColumn();

    // Calculate Pagination Bounds
    $perPage = max(1, min(48, $perPage));
    $totalPages = (int)ceil($totalCount / $perPage);
    $page = max(1, min($totalPages ?: 1, $page));
    $offset = ($page - 1) * $perPage;

    // B. Data Fetch Query
    $dataSql = "
        SELECT 
            p.id, p.category_id, p.brand_id, p.name, p.slug, p.sku, 
            p.short_description, p.original_price, p.selling_price, 
            p.stock_quantity, p.low_stock_threshold, p.is_featured, 
            p.is_new, p.is_bestseller, p.average_rating, p.review_count,
            c.name AS category_name, c.slug AS category_slug,
            b.name AS brand_name,
            (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
            (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 0 ORDER BY sort_order ASC LIMIT 1) AS hover_image,
            (SELECT GROUP_CONCAT(DISTINCT size ORDER BY FIELD(size, 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'Free Size', 'One Size') SEPARATOR ', ') FROM product_variants WHERE product_id = p.id AND status = 'active') AS available_sizes,
            (SELECT GROUP_CONCAT(DISTINCT color SEPARATOR ', ') FROM product_variants WHERE product_id = p.id AND status = 'active') AS available_colors
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        $joinSql
        WHERE $whereSql
        GROUP BY p.id
        ORDER BY $sortClause
        LIMIT :limit OFFSET :offset
    ";

    $dataStmt = $pdo->prepare($dataSql);
    foreach ($bindings as $key => $val) {
        $dataStmt->bindValue($key, $val);
    }
    $dataStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $products = $dataStmt->fetchAll();

    return [
        'products'       => $products,
        'total'          => $totalCount,
        'page'           => $page,
        'per_page'       => $perPage,
        'total_pages'    => $totalPages,
        'has_previous'   => ($page > 1),
        'has_next'       => ($page < $totalPages),
        'previous_page'  => $page - 1,
        'next_page'      => $page + 1,
        'sort'           => $sort,
        'filters'        => $filters
    ];
}

/**
 * Fetch a single product by slug along with all images, variants and specs
 */
function get_product_by_slug(string $slug): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT 
            p.*,
            c.name AS category_name, c.slug AS category_slug,
            parent_c.name AS parent_category_name, parent_c.slug AS parent_category_slug,
            b.name AS brand_name, b.slug AS brand_slug
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        LEFT JOIN categories parent_c ON c.parent_id = parent_c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        WHERE p.slug = ? AND p.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch();

    if (!$product) {
        return null;
    }

    // 1. Fetch Images
    $imgStmt = $pdo->prepare("
        SELECT id, image_url, alt_text, sort_order, is_primary
        FROM product_images
        WHERE product_id = ?
        ORDER BY is_primary DESC, sort_order ASC
    ");
    $imgStmt->execute([$product['id']]);
    $product['images'] = $imgStmt->fetchAll();

    // 2. Fetch Variants
    $varStmt = $pdo->prepare("
        SELECT id, sku, size, color, color_code, additional_price, stock_quantity, image_url, status
        FROM product_variants
        WHERE product_id = ? AND status = 'active'
        ORDER BY FIELD(size, 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'Free Size', 'One Size'), color ASC
    ");
    $varStmt->execute([$product['id']]);
    $product['variants'] = $varStmt->fetchAll();

    // 3. Parse JSON specifications if present
    if (!empty($product['specifications'])) {
        $decoded = json_decode($product['specifications'], true);
        $product['specs_array'] = is_array($decoded) ? $decoded : [];
    } else {
        $product['specs_array'] = [];
    }

    return $product;
}

/**
 * Fetch related products from same category or parent category
 */
function get_related_products(int $productId, int $categoryId, int $limit = 4): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT 
            p.id, p.name, p.slug, p.sku, p.selling_price, p.original_price, 
            p.stock_quantity, p.is_new, p.is_bestseller,
            c.name AS category_name,
            (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
            (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 0 ORDER BY sort_order ASC LIMIT 1) AS hover_image
        FROM products p
        INNER JOIN categories c ON p.category_id = c.id
        WHERE p.status = 'active' AND p.id != :prod_id AND (p.category_id = :cat_id_1 OR c.parent_id = :cat_id_2)
        ORDER BY p.is_bestseller DESC, p.created_at DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':prod_id', $productId, PDO::PARAM_INT);
    $stmt->bindValue(':cat_id_1', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':cat_id_2', $categoryId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Fetch aggregations for sidebar filters (counts, price range, sizes, colors)
 */
function get_filter_aggregates(): array {
    $pdo = db();

    // Categories with product counts
    $catStmt = $pdo->query("
        SELECT c.id, c.parent_id, c.name, c.slug, COUNT(p.id) AS product_count
        FROM categories c
        LEFT JOIN products p ON (p.category_id = c.id OR p.category_id IN (SELECT id FROM categories WHERE parent_id = c.id)) AND p.status = 'active'
        WHERE c.is_active = 1
        GROUP BY c.id
        ORDER BY c.display_order ASC, c.name ASC
    ");
    $categories = $catStmt->fetchAll();

    // Price bounds
    $priceStmt = $pdo->query("
        SELECT COALESCE(MIN(selling_price), 0) AS min_price, COALESCE(MAX(selling_price), 100000) AS max_price
        FROM products
        WHERE status = 'active'
    ");
    $priceRange = $priceStmt->fetch();

    // Distinct sizes
    $sizeStmt = $pdo->query("
        SELECT DISTINCT size
        FROM product_variants pv
        INNER JOIN products p ON pv.product_id = p.id
        WHERE pv.status = 'active' AND p.status = 'active' AND pv.size IS NOT NULL AND pv.size != ''
        ORDER BY FIELD(size, 'XS', 'S', 'M', 'L', 'XL', 'XXL', '38R', '40R', '42R', 'EU 37', 'EU 38', 'EU 39', 'EU 40 / UK 7', 'EU 41 / UK 8', 'EU 42 / UK 9', 'EU 43 / UK 10', '90 cm', '95 cm', '100 cm', 'Free Size', 'One Size', 'Age 2-3 Y')
    ");
    $sizes = $sizeStmt->fetchAll(PDO::FETCH_COLUMN);

    // Distinct colors
    $colorStmt = $pdo->query("
        SELECT DISTINCT color, color_code
        FROM product_variants pv
        INNER JOIN products p ON pv.product_id = p.id
        WHERE pv.status = 'active' AND p.status = 'active' AND pv.color IS NOT NULL AND pv.color != ''
        ORDER BY color ASC
    ");
    $colors = $colorStmt->fetchAll();

    return [
        'categories'  => $categories,
        'min_price'   => (float)$priceRange['min_price'],
        'max_price'   => (float)$priceRange['max_price'],
        'sizes'       => $sizes,
        'colors'      => $colors
    ];
}

/**
 * Validate a selected product variant and stock on server side
 */
function validate_variant_stock(int $variantId, int $requestedQty = 1): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT pv.*, p.name AS product_name, p.selling_price, p.status AS product_status
        FROM product_variants pv
        INNER JOIN products p ON pv.product_id = p.id
        WHERE pv.id = ?
        LIMIT 1
    ");
    $stmt->execute([$variantId]);
    $variant = $stmt->fetch();

    if (!$variant) {
        return [
            'valid' => false,
            'message' => 'The selected product variant does not exist.',
            'variant' => null
        ];
    }

    if ($variant['status'] !== 'active' || $variant['product_status'] !== 'active') {
        return [
            'valid' => false,
            'message' => 'This couture piece is currently not available for acquisition.',
            'variant' => $variant
        ];
    }

    if ($variant['stock_quantity'] <= 0) {
        return [
            'valid' => false,
            'message' => 'The selected size/color is currently out of stock.',
            'variant' => $variant
        ];
    }

    if ($requestedQty > $variant['stock_quantity']) {
        return [
            'valid' => false,
            'message' => sprintf('Only %d pieces are currently available in atelier stock.', $variant['stock_quantity']),
            'variant' => $variant
        ];
    }

    return [
        'valid' => true,
        'message' => 'Variant verified and in stock.',
        'variant' => $variant
    ];
}
