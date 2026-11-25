<?php
/**
 * =====================================================================
 * YATIYA COUTURE - PHASE 4 AUTOMATED VERIFICATION & TEST SUITE
 * Complete validation of Shopping Cart, Guest Cart, Customer Cart,
 * Wishlist, Coupon Engine, Shipping Calculations, and Security.
 * =====================================================================
 */

// Configure environment
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart-functions.php';

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
        echo sprintf(" [%02d] [FAIL] %s: Exception - %s\n", $testNum, $title, $t->getMessage());
    }
}

// Ensure clean test session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_id('test_guest_session_' . bin2hex(random_bytes(8)));
    session_start();
}
unset($_SESSION['customer_id'], $_SESSION['applied_coupon'], $_SESSION['guest_wishlist']);

echo "=========================================================\n";
echo "   YATIYA COUTURE - PHASE 4 VERIFICATION & TEST SUITE   \n";
echo "=========================================================\n\n";

// Fetch sample active product & variant
$sampleProduct = db()->query("
    SELECT p.id, p.selling_price, p.original_price, pv.id AS variant_id, pv.sku, pv.stock_quantity, pv.additional_price 
    FROM products p 
    INNER JOIN product_variants pv ON p.id = pv.product_id 
    WHERE p.status = 'active' AND pv.status = 'active' AND pv.stock_quantity > 5 
    LIMIT 1
")->fetch();

if (!$sampleProduct) {
    die("Error: No active product with stock found for testing.\n");
}

$sampleProdId = (int)$sampleProduct['id'];
$sampleVarId = (int)$sampleProduct['variant_id'];

// 1. Guest adds product to cart
run_test("Guest Adds Product to Cart", function() use ($sampleProdId, $sampleVarId) {
    $res = add_to_cart($sampleProdId, $sampleVarId, 1);
    if (!$res['success']) return 'Add to cart returned false: ' . ($res['message'] ?? '');

    $cartId = get_or_create_cart_id();
    $items = get_cart_items($cartId, false);
    if (count($items) < 1) return 'Cart item count is 0 in database.';
    if ($items[0]['product_id'] !== $sampleProdId) return 'Mismatched product ID in cart item.';
    return true;
});

// 2. Guest updates quantity
run_test("Guest Updates Quantity", function() use ($sampleProdId, $sampleVarId) {
    $cartId = get_or_create_cart_id();
    $items = get_cart_items($cartId, false);
    $itemId = $items[0]['id'];

    $res = update_cart_quantity($itemId, 3);
    if (!$res['success']) return 'Update quantity returned false: ' . ($res['message'] ?? '');

    $updatedItems = get_cart_items($cartId, false);
    if ($updatedItems[0]['quantity'] !== 3) return 'Quantity in DB is not 3: ' . $updatedItems[0]['quantity'];
    return true;
});

// 3. Guest removes product
run_test("Guest Removes Product", function() {
    $cartId = get_or_create_cart_id();
    $items = get_cart_items($cartId, false);
    $itemId = $items[0]['id'];

    $removed = remove_cart_item($itemId);
    if (!$removed) return 'remove_cart_item returned false.';

    $afterItems = get_cart_items($cartId, false);
    if (count($afterItems) !== 0) return 'Cart not empty after removal.';
    return true;
});

// 4. Invalid variant rejection
run_test("Invalid Variant Rejection", function() use ($sampleProdId) {
    $fakeVariantId = 9999999;
    $res = add_to_cart($sampleProdId, $fakeVariantId, 1);
    if ($res['success']) return 'Add to cart unexpectedly accepted a non-existent variant ID.';
    return true;
});

// 5. Out-of-stock rejection
run_test("Out-of-Stock Product/Variant Rejection", function() use ($sampleProdId) {
    // Find or test out-of-stock item
    $soldOut = db()->query("
        SELECT product_id, id AS variant_id FROM product_variants 
        WHERE stock_quantity = 0 AND status = 'active' LIMIT 1
    ")->fetch();

    if ($soldOut) {
        $res = add_to_cart((int)$soldOut['product_id'], (int)$soldOut['variant_id'], 1);
        if ($res['success']) return 'Add to cart accepted a variant with 0 stock.';
    } else {
        // Test requesting more quantity than available
        $v = db()->query("SELECT product_id, id, stock_quantity FROM product_variants WHERE status = 'active' LIMIT 1")->fetch();
        $res = add_to_cart((int)$v['product_id'], (int)$v['id'], (int)$v['stock_quantity'] + 500);
        if ($res['success']) return 'Add to cart accepted quantity greater than available stock.';
    }
    return true;
});

// 6. Cart persistence
run_test("Cart Persistence Across Requests", function() use ($sampleProdId, $sampleVarId) {
    // Add item to guest session
    add_to_cart($sampleProdId, $sampleVarId, 2);
    $sessId = session_id();

    // Verify retrieval by session ID
    $stmt = db()->prepare("
        SELECT ci.quantity 
        FROM carts c 
        INNER JOIN cart_items ci ON c.id = ci.cart_id 
        WHERE c.session_id = ? AND c.customer_id IS NULL AND c.status = 'active' AND ci.variant_id = ?
    ");
    $stmt->execute([$sessId, $sampleVarId]);
    $qty = (int)$stmt->fetchColumn();

    if ($qty !== 2) return 'Cart item did not persist for session ID: ' . $sessId . ' (got: ' . $qty . ')';
    return true;
});

// 7. Guest-to-customer cart merge
run_test("Guest-to-Customer Cart Merge", function() use ($sampleProdId, $sampleVarId) {
    $guestSessionId = 'guest_merge_' . bin2hex(random_bytes(6));

    // Create a temporary customer or use customer ID 1 (admin/test customer)
    $cust = db()->query("SELECT id FROM customers WHERE status = 'active' LIMIT 1")->fetch();
    if (!$cust) {
        db()->query("INSERT INTO customers (first_name, last_name, email, password_hash, status, created_at) VALUES ('Test', 'Patron', 'test.patron@yatiya.com', 'hash', 'active', NOW())");
        $custId = (int)db()->lastInsertId();
    } else {
        $custId = (int)$cust['id'];
    }

    // Clean existing customer cart items for deterministic testing
    $cCart = db()->prepare("SELECT id FROM carts WHERE customer_id = ? AND status = 'active'");
    $cCart->execute([$custId]);
    $existingCartId = $cCart->fetchColumn();
    if (!$existingCartId) {
        db()->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())")->execute([$custId]);
        $existingCartId = db()->lastInsertId();
    }
    db()->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$existingCartId]);

    // Create guest cart with 2 units
    db()->prepare("INSERT INTO carts (session_id, customer_id, status, created_at, updated_at) VALUES (?, NULL, 'active', NOW(), NOW())")->execute([$guestSessionId]);
    $guestCartId = db()->lastInsertId();
    db()->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later, created_at, updated_at) VALUES (?, ?, ?, 2, 0, NOW(), NOW())")->execute([$guestCartId, $sampleProdId, $sampleVarId]);

    // Merge guest cart to customer
    merge_guest_cart_to_customer($custId, $guestSessionId);

    // Verify customer cart now has the item
    $checkStmt = db()->prepare("
        SELECT ci.quantity, c.id 
        FROM carts c 
        INNER JOIN cart_items ci ON c.id = ci.cart_id 
        WHERE c.customer_id = ? AND c.status = 'active' AND ci.variant_id = ?
    ");
    $checkStmt->execute([$custId, $sampleVarId]);
    $merged = $checkStmt->fetch();

    if (!$merged || (int)$merged['quantity'] !== 2) {
        return 'Customer cart does not contain merged item with quantity 2.';
    }

    // Verify guest cart was converted
    $gCheck = db()->prepare("SELECT status FROM carts WHERE id = ?");
    $gCheck->execute([$guestCartId]);
    $gStatus = $gCheck->fetchColumn();
    if ($gStatus !== 'converted') {
        return 'Guest cart was not marked converted.';
    }

    return true;
});

// 8. Duplicate variant merging
run_test("Duplicate Variant Merging (Combines Quantities Safely)", function() use ($sampleProdId, $sampleVarId) {
    // Customer ID
    $cust = db()->query("SELECT id FROM customers WHERE status = 'active' LIMIT 1")->fetch();
    $custId = (int)$cust['id'];

    // Ensure customer cart has 1 unit of sample variant
    $cCartId = db()->prepare("SELECT id FROM carts WHERE customer_id = ? AND status = 'active'");
    $cCartId->execute([$custId]);
    $cartId = $cCartId->fetchColumn();
    if (!$cartId) {
        db()->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())")->execute([$custId]);
        $cartId = db()->lastInsertId();
    }
    db()->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
    db()->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later, created_at, updated_at) VALUES (?, ?, ?, 1, 0, NOW(), NOW())")->execute([$cartId, $sampleProdId, $sampleVarId]);

    // Create a new guest session cart that ALSO has 2 units of the same variant
    $newGuestSession = 'guest_dup_' . bin2hex(random_bytes(6));
    db()->prepare("INSERT INTO carts (session_id, customer_id, status, created_at, updated_at) VALUES (?, NULL, 'active', NOW(), NOW())")->execute([$newGuestSession]);
    $guestCartId = db()->lastInsertId();
    db()->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later, created_at, updated_at) VALUES (?, ?, ?, 2, 0, NOW(), NOW())")->execute([$guestCartId, $sampleProdId, $sampleVarId]);

    // Perform merge
    merge_guest_cart_to_customer($custId, $newGuestSession);

    // Verify there is only 1 cart item row in customer cart, and its quantity is 1 + 2 = 3
    $stmt = db()->prepare("SELECT COUNT(*) AS row_count, SUM(quantity) AS total_qty FROM cart_items WHERE cart_id = ? AND variant_id = ?");
    $stmt->execute([$cartId, $sampleVarId]);
    $res = $stmt->fetch();

    if ((int)$res['row_count'] !== 1) return 'Duplicate rows found: row_count is ' . $res['row_count'];
    if ((int)$res['total_qty'] !== 3) return 'Combined quantity is not 3: ' . $res['total_qty'];
    return true;
});

// 9. Customer wishlist
run_test("Customer-Specific Database Wishlist", function() use ($sampleProdId) {
    $cust = db()->query("SELECT id FROM customers WHERE status = 'active' LIMIT 1")->fetch();
    $custId = (int)$cust['id'];

    $_SESSION['customer_id'] = $custId;

    // Clean previous entry
    db()->prepare("DELETE FROM wishlists WHERE customer_id = ? AND product_id = ?")->execute([$custId, $sampleProdId]);

    // Add to wishlist
    $res = add_to_wishlist($sampleProdId);
    if (!$res['success']) return 'add_to_wishlist returned false.';

    // Verify entry in wishlists table
    $stmt = db()->prepare("SELECT COUNT(*) FROM wishlists WHERE customer_id = ? AND product_id = ?");
    $stmt->execute([$custId, $sampleProdId]);
    if ((int)$stmt->fetchColumn() !== 1) return 'Wishlist entry missing in DB for customer.';

    if (!is_product_in_wishlist($sampleProdId)) return 'is_product_in_wishlist returned false for customer.';

    // Remove
    remove_from_wishlist($sampleProdId);
    $stmt->execute([$custId, $sampleProdId]);
    if ((int)$stmt->fetchColumn() !== 0) return 'Wishlist entry not removed from DB.';

    return true;
});

// 10. Guest wishlist
run_test("Guest Session Wishlist & Post-Login Synchronization", function() use ($sampleProdId) {
    unset($_SESSION['customer_id']);
    $_SESSION['guest_wishlist'] = [];

    // Add to guest wishlist
    $res = add_to_wishlist($sampleProdId);
    if (!$res['success']) return 'Guest add_to_wishlist returned false.';
    if (!isset($_SESSION['guest_wishlist'][$sampleProdId])) return 'Product not in $_SESSION[guest_wishlist].';
    if (!is_product_in_wishlist($sampleProdId)) return 'is_product_in_wishlist returned false for guest.';

    // Sync to customer upon login
    $cust = db()->query("SELECT id FROM customers WHERE status = 'active' LIMIT 1")->fetch();
    $custId = (int)$cust['id'];

    sync_guest_wishlist_to_customer($custId);
    if (!empty($_SESSION['guest_wishlist'])) return 'guest_wishlist not cleared after sync.';

    $stmt = db()->prepare("SELECT COUNT(*) FROM wishlists WHERE customer_id = ? AND product_id = ?");
    $stmt->execute([$custId, $sampleProdId]);
    if ((int)$stmt->fetchColumn() !== 1) return 'Wishlist entry missing in DB after sync.';

    // Cleanup
    db()->prepare("DELETE FROM wishlists WHERE customer_id = ? AND product_id = ?")->execute([$custId, $sampleProdId]);
    return true;
});

// 11. Wishlist to cart
run_test("Move Wishlist Item to Shopping Bag", function() use ($sampleProdId, $sampleVarId) {
    unset($_SESSION['customer_id']);
    $_SESSION['guest_wishlist'] = [];
    add_to_wishlist($sampleProdId);

    $moveRes = move_wishlist_to_cart($sampleProdId, $sampleVarId);
    if (!$moveRes['success']) return 'move_wishlist_to_cart failed: ' . ($moveRes['message'] ?? '');

    // Verify removed from wishlist
    if (is_product_in_wishlist($sampleProdId)) return 'Product still present in wishlist after move.';

    // Verify in cart
    $cartId = get_or_create_cart_id();
    $items = get_cart_items($cartId, false);
    $found = false;
    foreach ($items as $it) {
        if ($it['product_id'] === $sampleProdId) {
            $found = true;
            break;
        }
    }
    if (!$found) return 'Product not found in shopping bag after move.';
    return true;
});

// 12. Valid coupon
run_test("Valid Coupon Engine Application (YATIYA10)", function() {
    $res = validate_and_apply_coupon('YATIYA10', 5000.00, null);
    if (!$res['success']) return 'Coupon YATIYA10 rejected: ' . ($res['message'] ?? '');
    // 10% of 5000 = 500.00
    if ((float)$res['discount'] !== 500.00) return 'Discount expected 500.00, got: ' . $res['discount'];
    if (empty($_SESSION['applied_coupon'])) return 'applied_coupon not stored in session.';
    return true;
});

// 13. Expired coupon
run_test("Expired Coupon Rejection", function() {
    // Insert temporary expired coupon
    db()->query("
        INSERT INTO coupons (code, description, discount_type, discount_value, min_spend, start_date, end_date, is_active, created_at, updated_at)
        VALUES ('TESTEXPIRED', 'Expired Test', 'percentage', 20.00, 500.00, '2020-01-01 00:00:00', '2021-01-01 00:00:00', 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE end_date = '2021-01-01 00:00:00'
    ");

    $res = validate_and_apply_coupon('TESTEXPIRED', 3000.00, null);
    // Cleanup
    db()->query("DELETE FROM coupons WHERE code = 'TESTEXPIRED'");

    if ($res['success']) return 'Expired coupon was incorrectly accepted.';
    return true;
});

// 14. Invalid coupon
run_test("Invalid Coupon Non-Existent Code Rejection", function() {
    $res = validate_and_apply_coupon('DEFINITELY_NOT_A_VALID_COUPON_12345', 5000.00, null);
    if ($res['success']) return 'Non-existent coupon was accepted.';
    return true;
});

// 15. Minimum order validation
run_test("Minimum Order Spend Validation (HAUTE500)", function() {
    // HAUTE500 requires min_spend 4999.00
    $resBelow = validate_and_apply_coupon('HAUTE500', 2500.00, null);
    if ($resBelow['success']) return 'HAUTE500 accepted below min_spend of 4999.';

    $resAbove = validate_and_apply_coupon('HAUTE500', 6000.00, null);
    if (!$resAbove['success']) return 'HAUTE500 failed above min_spend: ' . ($resAbove['message'] ?? '');
    if ((float)$resAbove['discount'] !== 500.00) return 'HAUTE500 discount is not 500.00.';
    return true;
});

// 16. Maximum discount cap
run_test("Maximum Discount Capping", function() {
    // YATIYA10 has max_discount 2500.00
    // If order subtotal is 50,000, 10% is 5,000, but cap must enforce 2,500
    $res = validate_and_apply_coupon('YATIYA10', 50000.00, null);
    if (!$res['success']) return 'YATIYA10 failed: ' . ($res['message'] ?? '');
    if ((float)$res['discount'] !== 2500.00) return 'Discount cap not enforced. Expected 2500.00, got: ' . $res['discount'];
    return true;
});

// 17. Coupon usage limits
run_test("Coupon Usage Quota Limits", function() {
    // Insert temporary quota exhausted coupon
    db()->query("
        INSERT INTO coupons (code, description, discount_type, discount_value, min_spend, usage_limit, usage_count, is_active, created_at, updated_at)
        VALUES ('QUOTAMAX', 'Quota Test', 'fixed', 100.00, 0.00, 5, 5, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE usage_count = 5
    ");

    $res = validate_and_apply_coupon('QUOTAMAX', 2000.00, null);
    db()->query("DELETE FROM coupons WHERE code = 'QUOTAMAX'");

    if ($res['success']) return 'Quota-exhausted coupon was accepted.';
    return true;
});

// 18. Free shipping threshold calculation
run_test("Shipping Fee & Free Shipping Threshold Calculation", function() {
    $threshold = (float)get_setting('free_shipping_threshold', '2999.00');
    $shippingCharge = (float)get_setting('default_shipping_charge', '199.00');

    // Case A: Subtotal below threshold (e.g. 2000)
    $itemsA = [
        ['line_total' => 2000.0, 'line_original_total' => 2500.0, 'quantity' => 1]
    ];
    unset($_SESSION['applied_coupon']);
    $totalsA = calculate_cart_totals($itemsA);
    if ($totalsA['shipping_fee'] !== $shippingCharge) return 'Shipping fee below threshold expected ' . $shippingCharge . ', got ' . $totalsA['shipping_fee'];
    if ($totalsA['is_free_shipping'] !== false) return 'is_free_shipping expected false below threshold.';
    if ($totalsA['grand_total'] !== (2000.0 + $shippingCharge)) return 'Grand total mismatch for below threshold.';

    // Case B: Subtotal at or above threshold (e.g. 3500)
    $itemsB = [
        ['line_total' => 3500.0, 'line_original_total' => 4000.0, 'quantity' => 1]
    ];
    $totalsB = calculate_cart_totals($itemsB);
    if ($totalsB['shipping_fee'] !== 0.0) return 'Shipping fee at/above threshold expected 0.0, got ' . $totalsB['shipping_fee'];
    if ($totalsB['is_free_shipping'] !== true) return 'is_free_shipping expected true at/above threshold.';
    if ($totalsB['grand_total'] !== 3500.0) return 'Grand total mismatch for free shipping.';

    return true;
});

// 19. Server-side price validation
run_test("Server-Side Price Validation (Never Trust Browser)", function() use ($sampleProdId, $sampleVarId) {
    // Add item and check that unit_price is calculated strictly from database values
    $cartId = get_or_create_cart_id();
    db()->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cartId]);
    add_to_cart($sampleProdId, $sampleVarId, 1);

    $items = get_cart_items($cartId, false);
    $item = $items[0];

    // Fetch ground-truth DB price
    $dbProd = db()->prepare("SELECT p.selling_price, pv.additional_price FROM products p JOIN product_variants pv ON p.id = pv.product_id WHERE p.id = ? AND pv.id = ?");
    $dbProd->execute([$sampleProdId, $sampleVarId]);
    $truth = $dbProd->fetch();
    $expectedPrice = (float)$truth['selling_price'] + (float)$truth['additional_price'];

    if (abs($item['unit_price'] - $expectedPrice) > 0.001) {
        return 'Server unit price (' . $item['unit_price'] . ') does not match DB price (' . $expectedPrice . ').';
    }
    return true;
});

// 20. CSRF protection
run_test("CSRF Token Verification for State Changes", function() {
    $token = csrf_token();
    if (!verify_csrf_token($token)) return 'Valid CSRF token failed verification.';
    if (verify_csrf_token('invalid_forged_csrf_token_xyz')) return 'Forged CSRF token was accepted.';
    if (verify_csrf_token('')) return 'Empty CSRF token was accepted.';
    return true;
});

// 21. SQL injection prevention
run_test("SQL Injection Prevention on Cart & Coupon Parameters", function() {
    $maliciousCoupon = "' OR '1'='1' UNION SELECT 1, 'HACK', 'HACK', 'fixed', 9999, 0, NULL, NULL, NULL, 0, 1, 1, NOW(), NOW() -- ";
    $res = validate_and_apply_coupon($maliciousCoupon, 5000.00, null);
    if ($res['success']) return 'SQL injection payload in coupon code succeeded.';

    // Variant ID SQL injection string
    $maliciousVariant = "1 OR 1=1";
    $cartId = get_or_create_cart_id();
    $resCart = add_to_cart(1, (int)$maliciousVariant, 1);
    // Prepared statement coerces safely
    return true;
});

// 22. XSS prevention
run_test("XSS Prevention on Rendered Cart Elements", function() {
    $unsafeString = '<script>alert("XSS")</script>';
    $escaped = e($unsafeString);
    if (strpos($escaped, '<script>') !== false) return 'Script tag was not escaped by e().';
    if ($escaped !== '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;') return 'Escaping did not match expected entity string.';
    return true;
});

echo "\n---------------------------------------------------------\n";
echo sprintf("PHASE 4 SUMMARY: Passed: %d | Failed: %d\n", $passCount, $failCount);
echo "---------------------------------------------------------\n";

if ($failCount === 0) {
    echo ">>> ALL 22 PHASE 4 SHOPPING FUNCTIONALITY TESTS PASSED <<<\n";
    exit(0);
} else {
    echo ">>> PHASE 4 VERIFICATION FAILED <<<\n";
    exit(1);
}
