<?php
/**
 * YATIYA COUTURE - Phase 3 Verification & Test Suite
 * Comprehensive automated testing covering all 17 catalogue test cases
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/product-functions.php';

echo "=========================================================\n";
echo "   YATIYA COUTURE - PHASE 3 VERIFICATION & TEST SUITE   \n";
echo "=========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assert_test($testNum, $description, $condition, $details = '') {
    global $passCount, $failCount;
    $prefix = sprintf("[%02d] [PASS]", $testNum);
    $failPrefix = sprintf("[%02d] [FAIL]", $testNum);

    if ($condition) {
        echo " $prefix $description\n";
        $passCount++;
    } else {
        echo " $failPrefix $description\n";
        if ($details) {
            echo "        Details: $details\n";
        }
        $failCount++;
    }
}

// ---------------------------------------------------------------------
// TEST 1: Active Product Listing
// ---------------------------------------------------------------------
$res1 = get_filtered_products([], 1, 12);
assert_test(
    1, "Active Product Listing",
    $res1['total'] >= 18 && count($res1['products']) === 12,
    "Total in DB: {$res1['total']}, Page 1 count: " . count($res1['products'])
);

// ---------------------------------------------------------------------
// TEST 2: Product Category Filtering
// ---------------------------------------------------------------------
$res2 = get_filtered_products(['category' => 'women'], 1, 12);
$allWomen = true;
foreach ($res2['products'] as $p) {
    if ($p['category_slug'] !== 'women') {
        // Check if parent category is women
        $check = db()->prepare("SELECT parent_id FROM categories WHERE slug = ?");
        $check->execute([$p['category_slug']]);
        $parent = $check->fetchColumn();
        if ($parent != 1) {
            $allWomen = false;
            break;
        }
    }
}
assert_test(
    2, "Product Category Filtering (Women's Salon)",
    $res2['total'] >= 5 && $allWomen,
    "Total Women pieces found: {$res2['total']}"
);

// ---------------------------------------------------------------------
// TEST 3: Price Filtering (Min & Max Bounds)
// ---------------------------------------------------------------------
$res3 = get_filtered_products(['min_price' => 15000, 'max_price' => 35000], 1, 12);
$pricesInRange = true;
foreach ($res3['products'] as $p) {
    $price = (float)$p['selling_price'];
    if ($price < 15000 || $price > 35000) {
        $pricesInRange = false;
        break;
    }
}
assert_test(
    3, "Price Filtering (₹15,000 to ₹35,000)",
    $res3['total'] > 0 && $pricesInRange,
    "Found: {$res3['total']} pieces within range"
);

// ---------------------------------------------------------------------
// TEST 4: Size Filtering (e.g. Size 'M')
// ---------------------------------------------------------------------
$res4 = get_filtered_products(['size' => 'M'], 1, 12);
assert_test(
    4, "Size Filtering (Size 'M')",
    $res4['total'] >= 5,
    "Creations available in Size M: {$res4['total']}"
);

// ---------------------------------------------------------------------
// TEST 5: Color Filtering (e.g. 'Royal Crimson')
// ---------------------------------------------------------------------
$res5 = get_filtered_products(['color' => 'Royal Crimson'], 1, 12);
assert_test(
    5, "Color Filtering ('Royal Crimson')",
    $res5['total'] >= 1,
    "Creations matching Royal Crimson: {$res5['total']}"
);

// ---------------------------------------------------------------------
// TEST 6: Search by Name (e.g. 'Bandhgala')
// ---------------------------------------------------------------------
$res6 = get_filtered_products(['q' => 'Bandhgala'], 1, 12);
$matchesName = false;
foreach ($res6['products'] as $p) {
    if (stripos($p['name'], 'Bandhgala') !== false) {
        $matchesName = true;
        break;
    }
}
assert_test(
    6, "Search by Product Name ('Bandhgala')",
    $res6['total'] >= 1 && $matchesName,
    "Results for 'Bandhgala': {$res6['total']}"
);

// ---------------------------------------------------------------------
// TEST 7: Search by SKU (e.g. 'YTY-WMN-LHG-001')
// ---------------------------------------------------------------------
$res7 = get_filtered_products(['q' => 'YTY-WMN-LHG-001'], 1, 12);
$foundSku = false;
foreach ($res7['products'] as $p) {
    if ($p['sku'] === 'YTY-WMN-LHG-001') {
        $foundSku = true;
        break;
    }
}
assert_test(
    7, "Search by SKU ('YTY-WMN-LHG-001')",
    $res7['total'] >= 1 && $foundSku,
    "SKU search found: " . ($foundSku ? 'Exact Match' : 'Not found')
);

// ---------------------------------------------------------------------
// TEST 8: Sorting (Price: Low to High & Price: High to Low)
// ---------------------------------------------------------------------
$res8Asc = get_filtered_products(['sort' => 'price_asc'], 1, 12);
$res8Desc = get_filtered_products(['sort' => 'price_desc'], 1, 12);
$ascOk = true;
for ($i = 0; $i < count($res8Asc['products']) - 1; $i++) {
    if ((float)$res8Asc['products'][$i]['selling_price'] > (float)$res8Asc['products'][$i + 1]['selling_price']) {
        $ascOk = false;
        break;
    }
}
$descOk = true;
for ($i = 0; $i < count($res8Desc['products']) - 1; $i++) {
    if ((float)$res8Desc['products'][$i]['selling_price'] < (float)$res8Desc['products'][$i + 1]['selling_price']) {
        $descOk = false;
        break;
    }
}
assert_test(
    8, "Sorting (Price Low-to-High & High-to-Low)",
    $ascOk && $descOk,
    "Asc lowest: ₹{$res8Asc['products'][0]['selling_price']}, Desc highest: ₹{$res8Desc['products'][0]['selling_price']}"
);

// ---------------------------------------------------------------------
// TEST 9: Pagination (Page 1 vs Page 2 with 12 items/page)
// ---------------------------------------------------------------------
$res9P1 = get_filtered_products([], 1, 12);
$res9P2 = get_filtered_products([], 2, 12);
$differentItems = ($res9P1['products'][0]['id'] !== $res9P2['products'][0]['id']);
assert_test(
    9, "Pagination (Page 1 & Page 2 Distinct Slices)",
    $res9P1['total_pages'] >= 2 && $differentItems && count($res9P2['products']) >= 6,
    "Total Pages: {$res9P1['total_pages']}, P1 Count: " . count($res9P1['products']) . ", P2 Count: " . count($res9P2['products'])
);

// ---------------------------------------------------------------------
// TEST 10: Product Image Gallery (Multiple Images)
// ---------------------------------------------------------------------
$prodLehenga = get_product_by_slug('maharani-zardozi-velvet-lehenga');
$hasGallery = $prodLehenga && count($prodLehenga['images']) >= 2;
assert_test(
    10, "Product Image Gallery (Multiple High-Res Angles)",
    $hasGallery,
    "Gallery image count for Lehenga: " . ($prodLehenga ? count($prodLehenga['images']) : 0)
);

// ---------------------------------------------------------------------
// TEST 11: Product Variant Selection
// ---------------------------------------------------------------------
$hasVariants = $prodLehenga && count($prodLehenga['variants']) >= 3;
$firstVariant = $prodLehenga ? $prodLehenga['variants'][0] : null;
assert_test(
    11, "Product Variant Selection (Sizes, Colors, SKUs)",
    $hasVariants && !empty($firstVariant['size']) && !empty($firstVariant['color']),
    "Total variants for piece: " . count($prodLehenga['variants'])
);

// ---------------------------------------------------------------------
// TEST 12: Invalid Product ID Handling (Returns 404 / null)
// ---------------------------------------------------------------------
$invalidSlug = get_product_by_slug('non-existent-luxury-dress-99999');
assert_test(
    12, "Invalid Product Identifier Handling (Graceful Null)",
    $invalidSlug === null,
    "Query for non-existent item returned null safely"
);

// ---------------------------------------------------------------------
// TEST 13: Out-of-Stock Products
// ---------------------------------------------------------------------
$outOfStock = get_product_by_slug('prince-of-awadh-pure-silk-kurta-ensemble');
$stockValidation = $outOfStock ? validate_variant_stock((int)$outOfStock['variants'][0]['id'], 1) : ['valid' => true];
assert_test(
    13, "Out-of-Stock Product Detection & Validation",
    $outOfStock && $outOfStock['stock_quantity'] === 0 && $stockValidation['valid'] === false,
    "Status message: " . $stockValidation['message']
);

// ---------------------------------------------------------------------
// TEST 14: Invalid Filter Inputs (Safe Coercion & Zero Crash)
// ---------------------------------------------------------------------
$invalidFilter = get_filtered_products([
    'min_price' => 'invalid_string',
    'max_price' => -500,
    'size' => "'; DROP TABLE test; --",
    'sort' => 'malicious_order_by_injection'
], 1, 12);
assert_test(
    14, "Invalid Filter Inputs Safe Coercion",
    is_array($invalidFilter) && isset($invalidFilter['products']),
    "Safely handled invalid price/sort inputs without SQL crash"
);

// ---------------------------------------------------------------------
// TEST 15: SQL Injection Resistance
// ---------------------------------------------------------------------
$sqlInjectionQuery = "' OR 1=1 --";
$res15 = get_filtered_products(['q' => $sqlInjectionQuery], 1, 12);
// If injection succeeded, it would return all products. In prepared statement, it matches literally.
assert_test(
    15, "SQL Injection Resistance (Prepared Parameter Binding)",
    $res15['total'] === 0,
    "Search for injection string safely returned 0 records"
);

// ---------------------------------------------------------------------
// TEST 16: XSS Sanitization in Output
// ---------------------------------------------------------------------
$xssInput = '<script>alert("hacked")</script>';
$escapedOutput = e($xssInput);
assert_test(
    16, "XSS Prevention & HTML Entity Escaping",
    !str_contains($escapedOutput, '<script>') && str_contains($escapedOutput, '&lt;script&gt;'),
    "Escaped representation: $escapedOutput"
);

// ---------------------------------------------------------------------
// TEST 17: Responsive Layout Assets
// ---------------------------------------------------------------------
$shopCssExists = file_exists(__DIR__ . '/assets/css/shop.css');
$productCssExists = file_exists(__DIR__ . '/assets/css/product.css');
$shopJsExists = file_exists(__DIR__ . '/assets/js/shop.js');
$productJsExists = file_exists(__DIR__ . '/assets/js/product.js');
assert_test(
    17, "Modular Responsive Layout Assets Exist",
    $shopCssExists && $productCssExists && $shopJsExists && $productJsExists,
    "shop.css, product.css, shop.js, product.js all created and verified"
);

echo "\n---------------------------------------------------------\n";
echo "PHASE 3 SUMMARY: Passed: $passCount | Failed: $failCount\n";
echo "---------------------------------------------------------\n";

if ($failCount === 0) {
    echo ">>> ALL 17 PHASE 3 PRODUCT CATALOGUE TESTS PASSED <<<\n";
    exit(0);
} else {
    echo ">>> PHASE 3 TESTS ENCOUNTERED FAILURES <<<\n";
    exit(1);
}

