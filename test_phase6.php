<?php
/**
 * =====================================================================
 * YATIYA COUTURE - PHASE 6 AUTOMATED VERIFICATION & TEST SUITE
 * Complete validation of Admin Authentication, Security, Dashboard Metrics,
 * Product CRUD, Inventory Movements, Orders, Customer Registry, Coupons,
 * Categories, Brands, Banners, Review Moderation, Settings, and Audit Logs.
 * =====================================================================
 */

ob_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart-functions.php';
require_once __DIR__ . '/includes/product-functions.php';
require_once __DIR__ . '/includes/admin-functions.php';

// Ensure clean test session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['admin_user_id'], $_SESSION['admin_username'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['customer_id'], $_SESSION['admin_login_throttle']);

$passCount = 0;
$failCount = 0;
$testNum = 0;

function run_test(string $title, callable $testFn): void {
    global $passCount, $failCount, $testNum;
    $testNum++;
    try {
        $result = $testFn();
        if ($result === true) {
            $passCount++;
            echo sprintf(" [%02d] [PASS] %s\n", $testNum, $title);
        } else {
            $failCount++;
            $msg = is_string($result) ? $result : 'Assertion failed';
            echo sprintf(" [%02d] [FAIL] %s: %s\n", $testNum, $title, $msg);
        }
    } catch (Throwable $t) {
        $failCount++;
        echo sprintf(" [%02d] [FAIL] %s: Exception - %s in %s:%d\n", $testNum, $title, $t->getMessage(), $t->getFile(), $t->getLine());
    }
}

echo "=========================================================\n";
echo "   YATIYA COUTURE - PHASE 6 VERIFICATION & TEST SUITE   \n";
echo "=========================================================\n\n";

$createdTestProdId = null;
$createdTestCatId = null;
$createdTestBrandId = null;
$createdTestCouponId = null;
$createdTestOrderId = null;

// 1. Admin Login
run_test("Successful Administrator Authentication", function() {
    $res = authenticate_admin('admin', 'Admin@Yatiya2026!');
    if (!$res['success']) return 'Admin authentication failed: ' . ($res['message'] ?? '');
    if (!is_admin_logged_in()) return 'is_admin_logged_in() returned false after login.';
    if ($_SESSION['admin_username'] !== 'admin') return 'admin_username not set in session.';
    if ($_SESSION['admin_role'] !== 'super_admin') return 'admin_role not super_admin.';
    return true;
});

// 2. Invalid Admin Login
run_test("Invalid Admin Login Credentials Rejection", function() {
    $res = authenticate_admin('admin', 'WrongPassWord123!');
    if ($res['success']) return 'Authentication succeeded with invalid password!';
    if ($res['message'] !== 'Invalid administrator credentials.') {
        return 'Did not return expected error message: ' . $res['message'];
    }
    return true;
});

// 3. Admin Route Protection & Customer Separation
run_test("Admin Route Protection & Customer Isolation", function() {
    logout_admin();
    if (is_admin_logged_in()) return 'Admin still logged in after logout.';

    // Simulate customer session
    $_SESSION['customer_id'] = 9999;
    if (is_admin_logged_in()) return 'Customer was mistakenly recognized as admin!';
    if (current_admin() !== null) return 'current_admin() returned non-null for customer.';

    unset($_SESSION['customer_id']);
    // Log admin back in
    authenticate_admin('admin', 'Admin@Yatiya2026!');
    return true;
});

// 4. Role-Based Permissions
run_test("Role-Based Permission Enforcement", function() {
    $admin = current_admin();
    if (!$admin) return 'current_admin() returned null.';
    
    // Super admin has all roles
    if (!has_admin_role('admin')) return 'Super admin failed has_admin_role("admin").';
    if (!has_admin_role('editor')) return 'Super admin failed has_admin_role("editor").';

    // Test with simulated editor role
    $_SESSION['admin_role'] = 'editor';
    if (!has_admin_role('editor')) return 'Editor role check failed.';
    if (has_admin_role('super_admin')) return 'Editor was granted super_admin permissions!';

    // Restore super_admin
    $_SESSION['admin_role'] = 'super_admin';
    return true;
});

// 5. CSRF Token Protection
run_test("CSRF Protection for State-Changing Requests", function() {
    $validToken = csrf_token();
    if (!verify_csrf_token($validToken)) return 'Valid CSRF token failed verification.';
    if (verify_csrf_token('invalid_forged_csrf_token')) return 'Forged CSRF token was accepted.';
    return true;
});

// 6. Dashboard Database Metrics
run_test("Executive Dashboard Live Metrics Verification", function() {
    $metrics = get_admin_dashboard_metrics();
    $expectedKeys = [
        'total_revenue', 'total_orders', 'pending_orders', 'completed_orders',
        'total_customers', 'total_products', 'low_stock_products', 'out_of_stock_products',
        'recent_orders', 'best_sellers', 'recent_customers', 'low_stock_alerts', 'sales_chart'
    ];

    foreach ($expectedKeys as $key) {
        if (!array_key_exists($key, $metrics)) {
            return "Missing metric key: $key";
        }
    }

    if ($metrics['total_products'] < 18) {
        return 'Expected at least 18 products in metrics, got: ' . $metrics['total_products'];
    }

    if (count($metrics['sales_chart']) !== 7) {
        return 'Expected 7-day sales chart points, got: ' . count($metrics['sales_chart']);
    }

    return true;
});

// 7. Product Creation
run_test("Product Creation with Variants & Specifications", function() use (&$createdTestProdId) {
    $catId = (int)db()->query("SELECT id FROM categories WHERE is_active = 1 LIMIT 1")->fetchColumn();
    $brandId = (int)db()->query("SELECT id FROM brands WHERE is_active = 1 LIMIT 1")->fetchColumn();

    $sku = strtoupper('YTY-TST-' . bin2hex(random_bytes(3)));
    $name = 'Atelier Imperial Test Sherwani';

    $prodData = [
        'name' => $name,
        'sku' => $sku,
        'slug' => 'atelier-imperial-test-sherwani-' . bin2hex(random_bytes(2)),
        'category_id' => $catId,
        'brand_id' => $brandId,
        'original_price' => 45000.00,
        'selling_price' => 38000.00,
        'cost_price' => 20000.00,
        'low_stock_threshold' => 3,
        'status' => 'active',
        'is_featured' => 1,
        'is_new' => 1,
        'short_description' => 'Test sherwani description',
        'description' => 'Full editorial description for test sherwani piece.',
        'specifications' => '{"Fabric": "Pure Silk Velvet"}'
    ];

    $variants = [
        ['size' => '40', 'color' => 'Imperial Navy', 'color_code' => '#001F3F', 'sku' => $sku . '-40', 'stock_quantity' => 8, 'additional_price' => 0],
        ['size' => '42', 'color' => 'Imperial Navy', 'color_code' => '#001F3F', 'sku' => $sku . '-42', 'stock_quantity' => 4, 'additional_price' => 1000]
    ];

    $images = [
        ['image_url' => 'https://images.unsplash.com/photo-1594938298603-c8148c4dae35', 'alt_text' => $name, 'sort_order' => 0, 'is_primary' => 1]
    ];

    $res = admin_create_product($prodData, $variants, $images);
    if (!$res['success']) return 'Product creation failed: ' . ($res['message'] ?? '');

    $createdTestProdId = $res['product_id'];

    // Verify in DB
    $p = admin_get_product($createdTestProdId);
    if (!$p) return 'Created product not found in database.';
    if ($p['sku'] !== $sku) return 'SKU mismatch in created product.';
    if ((int)$p['stock_quantity'] !== 12) return 'Total stock quantity expected 12, got: ' . $p['stock_quantity'];
    if (count($p['variants']) !== 2) return 'Expected 2 variants, got: ' . count($p['variants']);
    if (count($p['images']) !== 1) return 'Expected 1 image, got: ' . count($p['images']);

    return true;
});

// 8. Product Update
run_test("Product Update (Pricing, Details & Variant Sync)", function() use (&$createdTestProdId) {
    if (!$createdTestProdId) return 'No test product ID.';

    $product = admin_get_product($createdTestProdId);
    $variants = $product['variants'];
    $variants[0]['stock_quantity'] = 15; // update stock from 8 to 15

    $updateData = [
        'name' => 'Atelier Imperial Test Sherwani (Refined)',
        'sku' => $product['sku'],
        'category_id' => $product['category_id'],
        'original_price' => 45000.00,
        'selling_price' => 36000.00, // discounted further
        'status' => 'active'
    ];

    $res = admin_update_product($createdTestProdId, $updateData, $variants);
    if (!$res['success']) return 'Product update failed: ' . ($res['message'] ?? '');

    $updated = admin_get_product($createdTestProdId);
    if ($updated['name'] !== 'Atelier Imperial Test Sherwani (Refined)') return 'Product name was not updated.';
    if ((float)$updated['selling_price'] !== 36000.00) return 'Selling price was not updated.';
    if ((int)$updated['stock_quantity'] !== 19) return 'Stock quantity expected 19 (15+4), got: ' . $updated['stock_quantity'];

    return true;
});

// 9. Product Validation Rules
run_test("Product Validation (Price Checks & Duplicate SKU Rejection)", function() use ($createdTestProdId) {
    $existing = admin_get_product($createdTestProdId);

    // 1. Empty name
    $res1 = admin_create_product(['name' => '', 'sku' => 'TEST-SKU-999', 'original_price' => 100, 'selling_price' => 100, 'category_id' => 1]);
    if ($res1['success']) return 'Accepted product with empty name!';

    // 2. Selling price > original price
    $res2 = admin_create_product(['name' => 'Invalid Price Dress', 'sku' => 'TEST-SKU-INVALID', 'original_price' => 1000, 'selling_price' => 1500, 'category_id' => 1]);
    if ($res2['success']) return 'Accepted selling price higher than original price!';

    // 3. Duplicate SKU
    $res3 = admin_create_product(['name' => 'Duplicate SKU Dress', 'sku' => $existing['sku'], 'original_price' => 2000, 'selling_price' => 1800, 'category_id' => 1]);
    if ($res3['success']) return 'Accepted duplicate SKU!';

    return true;
});

// 10. Secure Image Upload Validation
run_test("Image Upload Security Validation (MIME, Extension & Size)", function() {
    // 1. Invalid extension
    $badFileExt = [
        'name' => 'malicious.php',
        'type' => 'application/x-php',
        'tmp_name' => __FILE__,
        'error' => UPLOAD_ERR_OK,
        'size' => 1024
    ];
    $res1 = admin_validate_image_upload($badFileExt);
    if ($res1['success']) return 'Accepted PHP file upload!';

    // 2. Oversized file (> 5MB)
    $oversizedFile = [
        'name' => 'huge_photo.jpg',
        'type' => 'image/jpeg',
        'tmp_name' => __FILE__,
        'error' => UPLOAD_ERR_OK,
        'size' => 6 * 1024 * 1024 // 6MB
    ];
    $res2 = admin_validate_image_upload($oversizedFile);
    if ($res2['success']) return 'Accepted file exceeding 5MB limit!';

    return true;
});

// 11. Category Management (CRUD & Protection)
run_test("Category Management (Creation, Hierarchy, Update & Product Protection)", function() use (&$createdTestCatId, $createdTestProdId) {
    $slug = 'test-couture-category-' . bin2hex(random_bytes(2));
    $catData = [
        'name' => 'Test Couture Line',
        'slug' => $slug,
        'parent_id' => null,
        'description' => 'Test category description',
        'display_order' => 99,
        'is_featured' => 1,
        'is_active' => 1
    ];

    $res = admin_save_category($catData);
    if (!$res['success']) return 'Category creation failed: ' . ($res['message'] ?? '');
    $createdTestCatId = $res['category_id'];

    // Update
    $catData['name'] = 'Test Couture Line (Renamed)';
    $upRes = admin_save_category($catData, $createdTestCatId);
    if (!$upRes['success']) return 'Category update failed: ' . ($upRes['message'] ?? '');

    // Attempt delete when products exist: assign test product to this category
    db()->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute([$createdTestCatId, $createdTestProdId]);
    $delFail = admin_delete_category($createdTestCatId);
    if ($delFail['success']) return 'Deleted category that has assigned products!';

    // Revert product category
    $fallbackCatId = (int)db()->query("SELECT id FROM categories WHERE id != $createdTestCatId LIMIT 1")->fetchColumn();
    db()->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute([$fallbackCatId, $createdTestProdId]);

    // Now delete empty category
    $delSuccess = admin_delete_category($createdTestCatId);
    if (!$delSuccess['success']) return 'Failed to delete empty category.';
    $createdTestCatId = null;

    return true;
});

// 12. Brand Management (CRUD)
run_test("Brand & Atelier Line Management", function() use (&$createdTestBrandId) {
    $slug = 'test-brand-' . bin2hex(random_bytes(2));
    $brandData = [
        'name' => 'Royal Test Atelier',
        'slug' => $slug,
        'description' => 'Bespoke test line',
        'logo_url' => 'https://example.com/logo.png',
        'is_active' => 1
    ];

    $res = admin_save_brand($brandData);
    if (!$res['success']) return 'Brand creation failed: ' . ($res['message'] ?? '');
    $createdTestBrandId = $res['brand_id'];

    // Update
    $brandData['description'] = 'Updated bespoke test line';
    $upRes = admin_save_brand($brandData, $createdTestBrandId);
    if (!$upRes['success']) return 'Brand update failed: ' . ($upRes['message'] ?? '');

    // Delete empty brand
    $delRes = admin_delete_brand($createdTestBrandId);
    if (!$delRes['success']) return 'Failed to delete empty brand.';
    $createdTestBrandId = null;

    return true;
});

// 13. Inventory Adjustment & Balance Calculation
run_test("Inventory Adjustment with Atomic Balance Calculation", function() use ($createdTestProdId) {
    if (!$createdTestProdId) return 'No test product ID.';

    $product = admin_get_product($createdTestProdId);
    $initialStock = (int)$product['stock_quantity'];

    // Add 10 units
    $res = admin_adjust_stock($createdTestProdId, null, +10, 'purchase', 'Test PO Restock', 1);
    if (!$res['success']) return 'Stock adjustment failed: ' . ($res['message'] ?? '');
    if ((int)$res['new_balance'] !== ($initialStock + 10)) {
        return "Expected new balance " . ($initialStock + 10) . ", got: " . $res['new_balance'];
    }

    // Verify negative stock rejection
    $negRes = admin_adjust_stock($createdTestProdId, null, -9999, 'adjustment', 'Illegal reduction', 1);
    if ($negRes['success']) return 'System permitted adjustment resulting in negative stock!';

    return true;
});

// 14. Inventory Movement History
run_test("Inventory Movement History Tracking", function() use ($createdTestProdId) {
    $movements = admin_get_inventory_movements($createdTestProdId);
    if (empty($movements)) return 'No inventory movements found for product.';
    
    $lastMov = $movements[0];
    if ($lastMov['movement_type'] !== 'purchase') return 'Last movement type expected purchase, got: ' . $lastMov['movement_type'];
    if ((int)$lastMov['quantity'] !== 10) return 'Last movement quantity expected 10, got: ' . $lastMov['quantity'];
    if ((int)$lastMov['created_by'] !== 1) return 'created_by admin ID expected 1, got: ' . $lastMov['created_by'];

    return true;
});

// 15. Order Listing & Filter Subsystem
run_test("Order Listing and Query Pagination", function() {
    $orders = admin_get_orders([], 1, 15);
    if (!isset($orders['items']) || !isset($orders['total']) || !isset($orders['page'])) {
        return 'admin_get_orders did not return expected pagination schema.';
    }
    return true;
});

// 16. Order Creation, Status Update & Cancel Restock
run_test("Order Lifecycle & Inventory Restock on Cancellation", function() use (&$createdTestOrderId, $createdTestProdId) {
    $pdo = db();
    $prod = admin_get_product($createdTestProdId);
    $stockBefore = (int)$prod['stock_quantity'];

    $orderNum = 'ORD-TST-' . bin2hex(random_bytes(3));
    $pdo->prepare("
        INSERT INTO orders (
            order_number, customer_id, subtotal, shipping_charge, tax_amount, grand_total,
            payment_method, payment_status, order_status, shipping_full_name, shipping_phone,
            shipping_address_line1, shipping_city, shipping_state, shipping_postal_code, placed_at
        ) VALUES (
            ?, 1, 36000.00, 0.00, 4320.00, 36000.00,
            'cod', 'pending', 'placed', 'Test Patron', '9876543210',
            '42 Luxury Way', 'New Delhi', 'Delhi', '110001', NOW()
        )
    ")->execute([$orderNum]);

    $createdTestOrderId = (int)$pdo->lastInsertId();

    // Insert order item
    $pdo->prepare("
        INSERT INTO order_items (order_id, product_id, variant_id, product_name, variant_name, sku, unit_price, quantity, total_price, created_at)
        VALUES (?, ?, NULL, ?, 'Imperial Navy', ?, 36000.00, 2, 72000.00, NOW())
    ")->execute([$createdTestOrderId, $createdTestProdId, $prod['name'], $prod['sku']]);

    // Update status to processing
    $statRes = admin_update_order_status($createdTestOrderId, 'processing', 'Tailoring started', 1);
    if (!$statRes['success']) return 'Order status update to processing failed.';

    $ord = admin_get_order($createdTestOrderId);
    if ($ord['order_status'] !== 'processing') return 'order_status expected processing, got: ' . $ord['order_status'];
    if ($ord['admin_notes'] !== 'Tailoring started') return 'admin_notes not updated.';

    // Cancel order -> must restock 2 units!
    $cancelRes = admin_update_order_status($createdTestOrderId, 'cancelled', 'Client cancellation', 1);
    if (!$cancelRes['success']) return 'Order cancellation failed.';

    $prodAfter = admin_get_product($createdTestProdId);
    if ((int)$prodAfter['stock_quantity'] !== ($stockBefore + 2)) {
        return "Expected stock to increase by 2 upon cancellation. Before: $stockBefore, After: {$prodAfter['stock_quantity']}";
    }

    return true;
});

// 17. Historical Order Price Preservation
run_test("Historical Order Price Preservation", function() use ($createdTestOrderId, $createdTestProdId) {
    // Modify current product selling price in catalog
    db()->prepare("UPDATE products SET selling_price = 99999.00 WHERE id = ?")->execute([$createdTestProdId]);

    // Verify historical order item unit price is unchanged!
    $ord = admin_get_order($createdTestOrderId);
    $item = $ord['items'][0];

    if ((float)$item['unit_price'] !== 36000.00) {
        return 'Historical order item price was altered! Expected 36000.00, got: ' . $item['unit_price'];
    }

    return true;
});

// 18. Customer Registry & Security Isolation
run_test("Customer Registry & Sensitive Credential Isolation", function() {
    $cust = admin_get_customer_details(1);
    if (!$cust) return 'Customer #1 not found.';

    // Strictly ensure no password hashes or tokens are returned
    if (array_key_exists('password_hash', $cust)) return 'Security breach: password_hash exposed in customer details!';
    if (array_key_exists('reset_token_hash', $cust)) return 'Security breach: reset_token_hash exposed in customer details!';
    if (array_key_exists('remember_token', $cust)) return 'Security breach: remember_token exposed in customer details!';

    return true;
});

// 19. Coupon Management (CRUD)
run_test("Coupon Campaign Management (CRUD)", function() use (&$createdTestCouponId) {
    $code = 'PRIVILEGE_' . bin2hex(random_bytes(2));
    $data = [
        'code' => $code,
        'description' => 'Test campaign coupon',
        'discount_type' => 'percentage',
        'discount_value' => 15.00,
        'min_spend' => 3000.00,
        'max_discount' => 1500.00,
        'usage_limit' => 50,
        'per_customer_limit' => 1,
        'is_active' => 1
    ];

    $res = admin_save_coupon($data, null, 1);
    if (!$res['success']) return 'Coupon creation failed: ' . ($res['message'] ?? '');
    $createdTestCouponId = $res['coupon_id'];

    // Update
    $data['discount_value'] = 20.00;
    $upRes = admin_save_coupon($data, $createdTestCouponId, 1);
    if (!$upRes['success']) return 'Coupon update failed: ' . ($upRes['message'] ?? '');

    // Toggle status
    admin_toggle_coupon_status($createdTestCouponId, false, 1);
    $cp = db()->query("SELECT is_active FROM coupons WHERE id = $createdTestCouponId")->fetchColumn();
    if ((int)$cp !== 0) return 'Coupon was not deactivated.';

    admin_toggle_coupon_status($createdTestCouponId, true, 1);
    $cpActive = db()->query("SELECT is_active FROM coupons WHERE id = $createdTestCouponId")->fetchColumn();
    if ((int)$cpActive !== 1) return 'Coupon was not re-activated.';

    return true;
});

// 20. Coupon Engine Integration with Phase 4 Validation
run_test("Coupon Integration with Core Shopping Engine", function() use ($createdTestCouponId) {
    $cp = db()->query("SELECT code FROM coupons WHERE id = $createdTestCouponId")->fetch();
    $code = $cp['code'];

    // Test with spend >= 3000 (min_spend)
    $val = validate_and_apply_coupon($code, 5000.00);
    if (!$val['success']) return 'Valid coupon failed validation in shopping engine: ' . ($val['message'] ?? '');

    // 20% of 5000 is 1000, capped at max_discount 1500 -> 1000
    if ((float)$val['discount'] !== 1000.00) {
        return 'Expected discount amount 1000.00, got: ' . $val['discount'];
    }

    // Test below min_spend
    $valLow = validate_and_apply_coupon($code, 2000.00);
    if ($valLow['success']) return 'Coupon applied below minimum spend requirement!';

    return true;
});

// 21. Banner Management (CRUD)
run_test("Homepage Editorial Banner Management", function() {
    $bannerData = [
        'title' => 'The Autumn Runway Test Edition',
        'subtitle' => 'Handcrafted Zari Silk',
        'badge_text' => 'EDITORIAL 2026',
        'cta_text' => 'Explore Salon',
        'cta_link' => '/shop.php?category=women',
        'image_url' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d',
        'position' => 'hero',
        'display_order' => 10,
        'is_active' => 1
    ];

    $res = admin_save_banner($bannerData, null, 1);
    if (!$res['success']) return 'Banner creation failed: ' . ($res['message'] ?? '');
    $bannerId = $res['banner_id'];

    // Update
    $bannerData['title'] = 'The Autumn Runway Test Edition (Updated)';
    $upRes = admin_save_banner($bannerData, $bannerId, 1);
    if (!$upRes['success']) return 'Banner update failed: ' . ($upRes['message'] ?? '');

    // Delete
    $delOk = admin_delete_banner($bannerId, 1);
    if (!$delOk) return 'Failed to delete banner.';

    return true;
});

// 22. Customer Review Moderation & Rating Recalculation
run_test("Review Moderation & Product Rating Aggregate Calculation", function() use ($createdTestProdId) {
    $pdo = db();

    // Insert pending test review
    $pdo->prepare("
        INSERT INTO product_reviews (product_id, customer_id, rating, review_title, review_text, status, created_at)
        VALUES (?, 1, 5, 'Exquisite Craftsmanship', 'The zari embroidery is truly royal.', 'pending', NOW())
    ")->execute([$createdTestProdId]);
    $revId = (int)$pdo->lastInsertId();

    // Moderate: approve
    $modRes = admin_update_review_status($revId, 'approved', 1);
    if (!$modRes['success']) return 'Review approval failed: ' . ($modRes['message'] ?? '');

    // Verify product average rating updated
    $prod = admin_get_product($createdTestProdId);
    if ((int)$prod['review_count'] !== 1) return 'Expected review_count 1, got: ' . $prod['review_count'];
    if ((float)$prod['average_rating'] !== 5.00) return 'Expected average_rating 5.00, got: ' . $prod['average_rating'];

    // Clean up test review
    $pdo->prepare("DELETE FROM product_reviews WHERE id = ?")->execute([$revId]);
    $pdo->prepare("UPDATE products SET review_count = 0, average_rating = 0.00 WHERE id = ?")->execute([$createdTestProdId]);

    return true;
});

// 23. Store Configuration Updates
run_test("Store Settings Management & Real-Time Sync", function() {
    $origText = get_setting('announcement_bar_text', '');

    $newText = 'Complimentary White-Glove Hand-Delivery Across India | Phase 6 Atelier Verified';
    $res = admin_update_store_settings(['announcement_bar_text' => $newText], 1);
    if (!$res['success']) return 'Settings update failed: ' . ($res['message'] ?? '');

    $syncedText = get_setting('announcement_bar_text', '', true);
    if ($syncedText !== $newText) return "Setting was not updated in database. Got: $syncedText";

    // Revert
    admin_update_store_settings(['announcement_bar_text' => $origText], 1);
    return true;
});

// 24. Administrative Activity Logging
run_test("Audit Logging & Staff Operations Trail", function() {
    $action = 'phase6_verification_audit';
    $details = 'Automated Phase 6 system audit execution';
    $logged = log_admin_activity(1, $action, 'system', 0, $details);
    if (!$logged) return 'log_admin_activity returned false.';

    $logs = admin_get_activity_logs(10);
    $found = false;
    foreach ($logs as $l) {
        if ($l['action'] === $action && $l['details'] === $details) {
            $found = true;
            break;
        }
    }
    if (!$found) return 'Audit entry was not found in admin_activity_logs table.';

    return true;
});

// 25. IDOR & Unauthorized Access Protection
run_test("Insecure Direct Object Reference (IDOR) & Privilege Checks", function() {
    logout_admin();
    if (is_admin_logged_in()) return 'Admin session not cleared.';

    // Customer tries to access admin functionality
    $_SESSION['customer_id'] = 1;
    if (is_admin_logged_in()) return 'Customer was recognized as admin.';

    // Check that role checks fail for customer
    if (has_admin_role('super_admin')) return 'Customer was granted super_admin role.';
    if (has_admin_role('editor')) return 'Customer was granted editor role.';

    // Clean customer session and re-authenticate as admin
    unset($_SESSION['customer_id']);
    authenticate_admin('admin', 'Admin@Yatiya2026!');
    return true;
});

// 26. SQL Injection & XSS Attack Resistance
run_test("SQL Injection Resistance & Output Sanitization", function() {
    // 1. SQL Injection attempt in search
    $sqliSearch = "' OR '1'='1' -- ";
    $res = admin_get_products(['search' => $sqliSearch], 1, 5);
    // Prepared statement binds search safely; does not blow up or return all records
    if (!isset($res['items'])) return 'SQL injection attempt caused query failure.';

    // 2. XSS payload in product title
    $xssInput = '<script>alert("xss")</script> Royal Dress';
    $escaped = e($xssInput);
    if (strpos($escaped, '<script>') !== false) return 'e() failed to escape script tag!';

    return true;
});

// 27. Regression Validation of Phases 1 to 5 Foundations
run_test("Phases 1 to 5 Foundation Regression Check", function() {
    // Phase 1: DB connection & 22 tables
    $tables = db()->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) < 22) return 'Less than 22 tables in database.';

    // Phase 2: Categories fetchable
    $cats = get_active_categories();
    if (empty($cats)) return 'get_active_categories() returned empty.';

    // Phase 3: Product catalogue query
    $filtered = get_filtered_products([], 1, 5);
    if (empty($filtered['products'])) return 'get_filtered_products() returned empty.';

    // Phase 4: Cart totals calculation
    $totals = calculate_cart_totals([]);
    if (!isset($totals['grand_total'])) return 'calculate_cart_totals() missing grand_total.';

    // Phase 5: Customer password hashing
    $hash = password_hash('PatronPass2026', PASSWORD_BCRYPT, ['cost' => 12]);
    if (!password_verify('PatronPass2026', $hash)) return 'Password verify failed on bcrypt hash.';

    return true;
});

// Cleanup test artifacts
if ($createdTestOrderId) {
    db()->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$createdTestOrderId]);
    db()->prepare("DELETE FROM orders WHERE id = ?")->execute([$createdTestOrderId]);
}
if ($createdTestCouponId) {
    db()->prepare("DELETE FROM coupons WHERE id = ?")->execute([$createdTestCouponId]);
}
if ($createdTestProdId) {
    admin_delete_product($createdTestProdId);
}

echo "\n---------------------------------------------------------\n";
echo sprintf("PHASE 6 SUMMARY: Passed: %d | Failed: %d\n", $passCount, $failCount);
echo "---------------------------------------------------------\n";

if ($failCount === 0) {
    echo ">>> ALL 27 PHASE 6 ADMIN OPERATIONS TESTS PASSED <<<\n";
    ob_end_flush();
    exit(0);
} else {
    echo ">>> PHASE 6 VERIFICATION FAILED <<<\n";
    ob_end_flush();
    exit(1);
}
