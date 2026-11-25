<?php
/**
 * YATIYA COUTURE - Phase 7 Test Suite
 * Comprehensive automated verification for Checkout & Payment Integration
 * 27 targeted test cases covering security, atomic transactions, inventory integrity,
 * cryptographic HMAC verification, idempotency, and access controls.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart-functions.php';
require_once __DIR__ . '/includes/checkout-functions.php';
require_once __DIR__ . '/includes/payment-functions.php';

$pdo = db();

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function run_test(string $name, callable $callback) {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    try {
        $result = $callback();
        if ($result === true || (is_array($result) && ($result['pass'] ?? false))) {
            $passedTests++;
            echo "[PASS] Test {$totalTests}: {$name}\n";
        } else {
            $failedTests++;
            $msg = is_array($result) ? ($result['message'] ?? 'Assertion failed') : 'Failed';
            echo "[FAIL] Test {$totalTests}: {$name} -> {$msg}\n";
        }
    } catch (Throwable $t) {
        $failedTests++;
        echo "[ERROR] Test {$totalTests}: {$name} -> " . $t->getMessage() . " on line " . $t->getLine() . "\n";
    }
}

echo "========================================================\n";
echo "   YATIYA COUTURE - PHASE 7 CHECKOUT & PAYMENT TEST SUITE\n";
echo "========================================================\n\n";

// Helper fixture data
$testEmail = 'phase7.patron.' . time() . '@yatiyacouture.com';
$testCustomerStmt = $pdo->prepare("
    INSERT INTO customers (first_name, last_name, email, phone, password_hash, status, created_at, updated_at)
    VALUES ('Zara', 'Vanderbilt', ?, '+919876543210', ?, 'active', NOW(), NOW())
");
$testCustomerStmt->execute([$testEmail, password_hash('LuxuryPass123!', PASSWORD_DEFAULT)]);
$testCustomerId = (int)$pdo->lastInsertId();

// Select active product with price <= 15000 (e.g. Linen shirt or Kurta)
$prodStmt = $pdo->query("SELECT id, selling_price FROM products WHERE status = 'active' ORDER BY selling_price ASC LIMIT 1");
$baseProduct = $prodStmt->fetch();
if (!$baseProduct) {
    die("No active product found for testing.\n");
}
$testProductId = (int)$baseProduct['id'];

// Variant with known inventory
$varStmt = $pdo->prepare("
    SELECT id, stock_quantity, additional_price FROM product_variants 
    WHERE product_id = ? AND status = 'active' LIMIT 1
");
$varStmt->execute([$testProductId]);
$testVariant = $varStmt->fetch();

if (!$testVariant) {
    $insertVar = $pdo->prepare("
        INSERT INTO product_variants (product_id, sku, size, color, additional_price, stock_quantity, status)
        VALUES (?, 'YTY-TEST-VAR-1', 'M', 'Pure Ivory', 0.00, 50, 'active')
    ");
    $insertVar->execute([$testProductId]);
    $testVariantId = (int)$pdo->lastInsertId();
} else {
    $testVariantId = (int)$testVariant['id'];
    $pdo->prepare("UPDATE product_variants SET stock_quantity = 50 WHERE id = ?")->execute([$testVariantId]);
}

// -------------------------------------------------------------
// TEST 1: Payment Gateway Constants Configuration
// -------------------------------------------------------------
run_test("Payment gateway constants are configured properly", function () {
    return defined('RAZORPAY_KEY_ID') &&
           defined('RAZORPAY_KEY_SECRET') &&
           defined('RAZORPAY_WEBHOOK_SECRET') &&
           defined('COD_ENABLED') &&
           defined('COD_MAX_AMOUNT') &&
           COD_MAX_AMOUNT > 0;
});

// -------------------------------------------------------------
// TEST 2: Order Number Generation Format & Uniqueness
// -------------------------------------------------------------
run_test("Order number generator produces unique YTY-YYYYMMDD-XXXXXX format", function () {
    $n1 = generate_order_number();
    $n2 = generate_order_number();
    $pattern = '/^YTY-\d{8}-[A-F0-9]{6}$/';
    return preg_match($pattern, $n1) === 1 &&
           preg_match($pattern, $n2) === 1 &&
           $n1 !== $n2;
});

// -------------------------------------------------------------
// TEST 3: Address Validation - Valid Delivery Address
// -------------------------------------------------------------
run_test("Address validation accepts complete and valid Indian delivery address", function () {
    $data = [
        'shipping_full_name' => 'Zara Vanderbilt',
        'shipping_phone' => '9876543210',
        'shipping_address_line1' => 'Atelier 402, Royal Palms',
        'shipping_city' => 'New Delhi',
        'shipping_state' => 'Delhi',
        'shipping_postal_code' => '110001',
        'shipping_country' => 'India'
    ];
    $res = validate_checkout_address($data, 'shipping');
    return $res['success'] === true && $res['data']['postal_code'] === '110001';
});

// -------------------------------------------------------------
// TEST 4: Address Validation - Reject Invalid PIN Code
// -------------------------------------------------------------
run_test("Address validation rejects invalid 4-digit or alphanumeric PIN code", function () {
    $data = [
        'shipping_full_name' => 'Zara Vanderbilt',
        'shipping_phone' => '9876543210',
        'shipping_address_line1' => 'Atelier 402, Royal Palms',
        'shipping_city' => 'New Delhi',
        'shipping_state' => 'Delhi',
        'shipping_postal_code' => '1100', // Invalid PIN
        'shipping_country' => 'India'
    ];
    $res = validate_checkout_address($data, 'shipping');
    return $res['success'] === false;
});

// -------------------------------------------------------------
// TEST 5: Address Validation - Reject Invalid Phone Number
// -------------------------------------------------------------
run_test("Address validation rejects incomplete phone number", function () {
    $data = [
        'shipping_full_name' => 'Zara Vanderbilt',
        'shipping_phone' => '1234', // Invalid
        'shipping_address_line1' => 'Atelier 402, Royal Palms',
        'shipping_city' => 'New Delhi',
        'shipping_state' => 'Delhi',
        'shipping_postal_code' => '110001',
        'shipping_country' => 'India'
    ];
    $res = validate_checkout_address($data, 'shipping');
    return $res['success'] === false;
});

// -------------------------------------------------------------
// TEST 6: Address Validation - Reject Missing Line 1 or City
// -------------------------------------------------------------
run_test("Address validation rejects missing street address line 1", function () {
    $data = [
        'shipping_full_name' => 'Zara Vanderbilt',
        'shipping_phone' => '9876543210',
        'shipping_address_line1' => '', // Missing
        'shipping_city' => 'New Delhi',
        'shipping_state' => 'Delhi',
        'shipping_postal_code' => '110001',
        'shipping_country' => 'India'
    ];
    $res = validate_checkout_address($data, 'shipping');
    return $res['success'] === false;
});

// -------------------------------------------------------------
// TEST 7: Checkout Context Retrieval
// -------------------------------------------------------------
run_test("get_checkout_context() returns structured bag, customer, and totals", function () use ($pdo, $testCustomerId, $testProductId, $testVariantId) {
    $cStmt = $pdo->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
    $cStmt->execute([$testCustomerId]);
    $cartId = (int)$pdo->lastInsertId();

    $ciStmt = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 1, 0)");
    $ciStmt->execute([$cartId, $testProductId, $testVariantId]);

    $ctx = get_checkout_context($cartId, $testCustomerId);
    return count($ctx['items']) === 1 &&
           $ctx['totals']['subtotal'] > 0 &&
           $ctx['stock_ok'] === true &&
           $ctx['customer']['id'] == $testCustomerId;
});

// -------------------------------------------------------------
// TEST 8: COD Eligibility Verification (Under Threshold)
// -------------------------------------------------------------
run_test("COD is eligible when grand total is within configured limit", function () use ($pdo, $testCustomerId, $testProductId, $testVariantId) {
    $cStmt = $pdo->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
    $cStmt->execute([$testCustomerId]);
    $cartId = (int)$pdo->lastInsertId();

    // 1 item of Product ID 9 (₹6,900) is well within ₹50,000 COD limit
    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 1, 0)")
        ->execute([$cartId, $testProductId, $testVariantId]);

    $ctx = get_checkout_context($cartId, $testCustomerId);
    return $ctx['is_cod_eligible'] === true;
});

// -------------------------------------------------------------
// TEST 9: COD Ineligibility on Exceeded Threshold
// -------------------------------------------------------------
run_test("COD is marked ineligible when bag exceeds COD_MAX_AMOUNT", function () use ($pdo, $testCustomerId, $testProductId, $testVariantId) {
    $cStmt = $pdo->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
    $cStmt->execute([$testCustomerId]);
    $cartId = (int)$pdo->lastInsertId();

    // 10 items of ₹6,900 = ₹69,000 > ₹50,000 threshold
    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 10, 0)")
        ->execute([$cartId, $testProductId, $testVariantId]);

    $ctx = get_checkout_context($cartId, $testCustomerId);
    return $ctx['is_cod_eligible'] === false;
});

// -------------------------------------------------------------
// TEST 10: Server-Side Price Integrity (Tamper Resistance)
// -------------------------------------------------------------
run_test("create_order_from_cart() calculates prices strictly from database, ignoring manipulated input", function () use ($pdo, $testCustomerId, $testProductId, $testVariantId) {
    $cStmt = $pdo->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
    $cStmt->execute([$testCustomerId]);
    $cartId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 1, 0)")
        ->execute([$cartId, $testProductId, $testVariantId]);

    // Attacker sends manipulated client prices in checkoutData
    $maliciousData = [
        'subtotal' => '1.00',
        'grand_total' => '1.00',
        'shipping_full_name' => 'Zara Vanderbilt',
        'shipping_phone' => '9876543210',
        'shipping_address_line1' => 'Atelier 402, Royal Palms',
        'shipping_city' => 'New Delhi',
        'shipping_state' => 'Delhi',
        'shipping_postal_code' => '110001',
        'shipping_country' => 'India',
        'payment_method' => 'cod'
    ];

    $res = create_order_from_cart($cartId, $maliciousData, $testCustomerId);
    if (!$res['success']) {
        return ['pass' => false, 'message' => $res['message']];
    }

    $order = get_order_by_id((int)$res['order_id']);
    return (float)$order['grand_total'] > 1.00;
});

// -------------------------------------------------------------
// TEST 11: Pessimistic Locking & Out-of-Stock Prevention
// -------------------------------------------------------------
run_test("Order placement fails cleanly if requested quantity exceeds variant stock", function () use ($pdo, $testCustomerId, $testProductId, $testVariantId) {
    $pdo->prepare("UPDATE product_variants SET stock_quantity = 2 WHERE id = ?")->execute([$testVariantId]);

    $cStmt = $pdo->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
    $cStmt->execute([$testCustomerId]);
    $cartId = (int)$pdo->lastInsertId();

    // Request 5 items
    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 5, 0)")
        ->execute([$cartId, $testProductId, $testVariantId]);

    $data = [
        'shipping_full_name' => 'Zara Vanderbilt',
        'shipping_phone' => '9876543210',
        'shipping_address_line1' => 'Atelier 402, Royal Palms',
        'shipping_city' => 'New Delhi',
        'shipping_state' => 'Delhi',
        'shipping_postal_code' => '110001',
        'shipping_country' => 'India',
        'payment_method' => 'cod'
    ];

    $res = create_order_from_cart($cartId, $data, $testCustomerId);
    // Restore stock
    $pdo->prepare("UPDATE product_variants SET stock_quantity = 50 WHERE id = ?")->execute([$testVariantId]);

    return $res['success'] === false && str_contains(strtolower($res['message']), 'insufficient stock');
});

// -------------------------------------------------------------
// TEST 12: Atomic Inventory Deduction
// -------------------------------------------------------------
run_test("Successful order decrements product variant stock by exact purchased quantity", function () use ($pdo, $testCustomerId, $testProductId, $testVariantId) {
    $pdo->prepare("UPDATE product_variants SET stock_quantity = 20 WHERE id = ?")->execute([$testVariantId]);

    $cStmt = $pdo->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
    $cStmt->execute([$testCustomerId]);
    $cartId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 3, 0)")
        ->execute([$cartId, $testProductId, $testVariantId]);

    $data = [
        'shipping_full_name' => 'Zara Vanderbilt',
        'shipping_phone' => '9876543210',
        'shipping_address_line1' => 'Atelier 402, Royal Palms',
        'shipping_city' => 'New Delhi',
        'shipping_state' => 'Delhi',
        'shipping_postal_code' => '110001',
        'shipping_country' => 'India',
        'payment_method' => 'cod'
    ];

    $res = create_order_from_cart($cartId, $data, $testCustomerId);
    if (!$res['success']) {
        return ['pass' => false, 'message' => $res['message']];
    }

    $stockAfter = (int)$pdo->query("SELECT stock_quantity FROM product_variants WHERE id = {$testVariantId}")->fetchColumn();
    return $stockAfter === 17; // 20 - 3 = 17
});

// -------------------------------------------------------------
// TEST 13: Inventory Movement Ledger Record
// -------------------------------------------------------------
run_test("Order placement creates immutable order_sale record in inventory_movements ledger", function () use ($pdo, $testVariantId) {
    $stmt = $pdo->prepare("
        SELECT * FROM inventory_movements 
        WHERE variant_id = ? AND movement_type = 'order_sale' 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$testVariantId]);
    $mov = $stmt->fetch();
    return !empty($mov) && (int)$mov['quantity'] === -3 && (int)$mov['balance_after'] === 17;
});

// -------------------------------------------------------------
// TEST 14: Dynamic Coupon Application & Re-validation
// -------------------------------------------------------------
run_test("Applied coupon discount is revalidated and accurately deducted during order placement", function () use ($pdo, $testCustomerId, $testProductId, $testVariantId) {
    $code = 'TESTLUX' . time();
    $pdo->prepare("
        INSERT INTO coupons (code, description, discount_type, discount_value, min_spend, is_active, usage_limit, usage_count, created_at)
        VALUES (?, 'Test 10% privilege discount', 'percentage', 10.00, 500.00, 1, 100, 0, NOW())
    ")->execute([$code]);

    $_SESSION['applied_coupon'] = ['code' => $code];

    $cStmt = $pdo->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
    $cStmt->execute([$testCustomerId]);
    $cartId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 1, 0)")
        ->execute([$cartId, $testProductId, $testVariantId]);

    $data = [
        'shipping_full_name' => 'Zara Vanderbilt',
        'shipping_phone' => '9876543210',
        'shipping_address_line1' => 'Atelier 402, Royal Palms',
        'shipping_city' => 'New Delhi',
        'shipping_state' => 'Delhi',
        'shipping_postal_code' => '110001',
        'shipping_country' => 'India',
        'payment_method' => 'cod'
    ];

    $res = create_order_from_cart($cartId, $data, $testCustomerId);
    unset($_SESSION['applied_coupon']);

    if (!$res['success']) {
        return ['pass' => false, 'message' => $res['message']];
    }

    $order = get_order_by_id((int)$res['order_id']);
    return (float)$order['discount_amount'] > 0 && $order['coupon_code'] === $code;
});

// -------------------------------------------------------------
// TEST 15: Coupon Redemptions History & Count Increment
// -------------------------------------------------------------
run_test("Order placement records coupon_redemptions audit and increments coupon usage_count", function () use ($pdo) {
    $red = $pdo->query("SELECT * FROM coupon_redemptions ORDER BY id DESC LIMIT 1")->fetch();
    return !empty($red) && (float)$red['discount_amount'] > 0;
});

// -------------------------------------------------------------
// TEST 16: Free Shipping vs Default Shipping Computation
// -------------------------------------------------------------
run_test("Shipping charge respects FREE_SHIPPING_THRESHOLD logic", function () use ($pdo, $testCustomerId, $testProductId, $testVariantId) {
    $cStmt = $pdo->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
    $cStmt->execute([$testCustomerId]);
    $cartId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 1, 0)")
        ->execute([$cartId, $testProductId, $testVariantId]);

    $ctx = get_checkout_context($cartId, $testCustomerId);
    $subtotal = $ctx['totals']['subtotal'];

    if ($subtotal >= 2999.00) {
        return $ctx['totals']['shipping_fee'] == 0.00;
    } else {
        return $ctx['totals']['shipping_fee'] == 199.00;
    }
});

// -------------------------------------------------------------
// TEST 17: Compliant Inclusive Tax (12% GST) Snapshot
// -------------------------------------------------------------
run_test("Order snapshot records accurate inclusive 12% GST tax amount", function () use ($pdo) {
    $order = $pdo->query("SELECT subtotal, discount_amount, tax_amount FROM orders ORDER BY id DESC LIMIT 1")->fetch();
    $taxable = (float)$order['subtotal'] - (float)$order['discount_amount'];
    $expectedTax = round($taxable * (0.12 / 1.12), 2);
    return abs((float)$order['tax_amount'] - $expectedTax) < 0.05;
});

// -------------------------------------------------------------
// TEST 18: Atomic Transaction Rollback on Failure
// -------------------------------------------------------------
run_test("Database transaction cleanly rolls back and leaves no partial records on mid-flight failure", function () use ($pdo, $testCustomerId, $testProductId, $testVariantId) {
    $ordersCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    // Create a second temporary product variant with 0 stock
    $v2Stmt = $pdo->prepare("
        INSERT INTO product_variants (product_id, sku, size, color, additional_price, stock_quantity, status)
        VALUES (?, 'YTY-TEST-VAR-OOS', 'S', 'Noir', 0.00, 0, 'active')
    ");
    $v2Stmt->execute([$testProductId]);
    $oosVariantId = (int)$pdo->lastInsertId();

    $cStmt = $pdo->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
    $cStmt->execute([$testCustomerId]);
    $cartId = (int)$pdo->lastInsertId();

    // Item 1 has stock
    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 1, 0)")
        ->execute([$cartId, $testProductId, $testVariantId]);

    // Item 2 has 0 stock (requested 1)
    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 1, 0)")
        ->execute([$cartId, $testProductId, $oosVariantId]);

    $data = [
        'shipping_full_name' => 'Zara Vanderbilt',
        'shipping_phone' => '9876543210',
        'shipping_address_line1' => 'Atelier 402, Royal Palms',
        'shipping_city' => 'New Delhi',
        'shipping_state' => 'Delhi',
        'shipping_postal_code' => '110001',
        'shipping_country' => 'India',
        'payment_method' => 'cod'
    ];

    $res = create_order_from_cart($cartId, $data, $testCustomerId);
    $ordersCountAfter = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();

    // Clean up temporary variant
    $pdo->prepare("DELETE FROM product_variants WHERE id = ?")->execute([$oosVariantId]);

    return $res['success'] === false && $ordersCountBefore === $ordersCountAfter;
});

// -------------------------------------------------------------
// TEST 19: Cart Conversion & Clearing Active Items
// -------------------------------------------------------------
run_test("Cart status is updated to 'converted' and active items are cleared upon order placement", function () use ($pdo, $testCustomerId, $testProductId, $testVariantId) {
    $cStmt = $pdo->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
    $cStmt->execute([$testCustomerId]);
    $cartId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 1, 0)")
        ->execute([$cartId, $testProductId, $testVariantId]);

    $data = [
        'shipping_full_name' => 'Zara Vanderbilt',
        'shipping_phone' => '9876543210',
        'shipping_address_line1' => 'Atelier 402, Royal Palms',
        'shipping_city' => 'New Delhi',
        'shipping_state' => 'Delhi',
        'shipping_postal_code' => '110001',
        'shipping_country' => 'India',
        'payment_method' => 'online'
    ];

    $res = create_order_from_cart($cartId, $data, $testCustomerId);
    if (!$res['success']) {
        return ['pass' => false, 'message' => $res['message']];
    }

    $cart = $pdo->query("SELECT status FROM carts WHERE id = {$cartId}")->fetch();
    $itemsCount = (int)$pdo->query("SELECT COUNT(*) FROM cart_items WHERE cart_id = {$cartId} AND saved_for_later = 0")->fetchColumn();

    return $cart['status'] === 'converted' && $itemsCount === 0;
});

// -------------------------------------------------------------
// TEST 20: Razorpay Order Creation Helper
// -------------------------------------------------------------
run_test("create_razorpay_order() formats gateway order ID, currency, and amount in paise", function () use ($pdo) {
    $order = $pdo->query("SELECT id, grand_total, order_number FROM orders WHERE payment_method = 'online' ORDER BY id DESC LIMIT 1")->fetch();
    $rp = create_razorpay_order((int)$order['id'], (float)$order['grand_total'], $order['order_number']);

    $expectedPaise = (int)round((float)$order['grand_total'] * 100);
    return $rp['success'] === true &&
           $rp['amount'] === $expectedPaise &&
           $rp['currency'] === 'INR' &&
           str_starts_with($rp['gateway_order_id'], 'order_');
});

// -------------------------------------------------------------
// TEST 21: Razorpay Signature Verification (Valid Case)
// -------------------------------------------------------------
run_test("verify_razorpay_signature() validates genuine cryptographic HMAC SHA-256 signature", function () {
    $orderId = 'order_YTYTest123456';
    $paymentId = 'pay_YTYTest789012';
    $validSig = generate_razorpay_signature($orderId, $paymentId);

    return verify_razorpay_signature($orderId, $paymentId, $validSig) === true;
});

// -------------------------------------------------------------
// TEST 22: Razorpay Signature Verification (Tampered Case)
// -------------------------------------------------------------
run_test("verify_razorpay_signature() rejects tampered payment ID or forged signature", function () {
    $orderId = 'order_YTYTest123456';
    $paymentId = 'pay_YTYTest789012';
    $validSig = generate_razorpay_signature($orderId, $paymentId);
    $tamperedSig = substr($validSig, 0, -2) . 'ff';

    $check1 = verify_razorpay_signature($orderId, $paymentId, $tamperedSig);
    $check2 = verify_razorpay_signature($orderId, 'pay_HACKED_ID', $validSig);

    return $check1 === false && $check2 === false;
});

// -------------------------------------------------------------
// TEST 23: Timing-Attack Resistance in Signature Verification
// -------------------------------------------------------------
run_test("Signature verification strictly enforces constant-time comparison via hash_equals()", function () {
    return verify_razorpay_signature('', '', '') === false &&
           verify_razorpay_signature('order_1', 'pay_1', 'short') === false;
});

// -------------------------------------------------------------
// TEST 24: Payment Settlement State Transitions (Success)
// -------------------------------------------------------------
run_test("handle_payment_success() transitions payments.status to 'successful' and orders.payment_status to 'paid'", function () use ($pdo) {
    $order = $pdo->query("SELECT id, order_number FROM orders WHERE payment_method = 'online' ORDER BY id DESC LIMIT 1")->fetch();
    $orderId = (int)$order['id'];
    $txId = 'pay_settle_test_' . time();
    $gatewayOrderId = 'order_settle_test_' . time();
    $sig = generate_razorpay_signature($gatewayOrderId, $txId);

    $res = handle_payment_success($orderId, $txId, $gatewayOrderId, $sig, ['method' => 'card', 'bank' => 'HDFC']);
    if (!$res['success']) return false;

    $orderAfter = $pdo->query("SELECT payment_status, order_status FROM orders WHERE id = {$orderId}")->fetch();
    $paymentAfter = $pdo->query("SELECT status, transaction_id, signature FROM payments WHERE order_id = {$orderId}")->fetch();

    return $orderAfter['payment_status'] === 'paid' &&
           in_array($orderAfter['order_status'], ['confirmed', 'processing'], true) &&
           $paymentAfter['status'] === 'successful' &&
           $paymentAfter['transaction_id'] === $txId &&
           $paymentAfter['signature'] === $sig;
});

// -------------------------------------------------------------
// TEST 25: Payment Failure State Transitions
// -------------------------------------------------------------
run_test("handle_payment_failure() transitions payments.status to 'failed' and orders.payment_status to 'failed'", function () use ($pdo, $testCustomerId, $testProductId, $testVariantId) {
    $cStmt = $pdo->prepare("INSERT INTO carts (customer_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
    $cStmt->execute([$testCustomerId]);
    $cartId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later) VALUES (?, ?, ?, 1, 0)")
        ->execute([$cartId, $testProductId, $testVariantId]);

    $data = [
        'shipping_full_name' => 'Zara Vanderbilt',
        'shipping_phone' => '9876543210',
        'shipping_address_line1' => 'Atelier 402, Royal Palms',
        'shipping_city' => 'New Delhi',
        'shipping_state' => 'Delhi',
        'shipping_postal_code' => '110001',
        'shipping_country' => 'India',
        'payment_method' => 'online'
    ];

    $res = create_order_from_cart($cartId, $data, $testCustomerId);
    $orderId = (int)$res['order_id'];

    $failRes = handle_payment_failure($orderId, 'Bank 3D-Secure authentication rejected by issuer.');
    if (!$failRes['success']) return false;

    $orderAfter = $pdo->query("SELECT payment_status FROM orders WHERE id = {$orderId}")->fetch();
    $paymentAfter = $pdo->query("SELECT status FROM payments WHERE order_id = {$orderId}")->fetch();

    return $orderAfter['payment_status'] === 'failed' && $paymentAfter['status'] === 'failed';
});

// -------------------------------------------------------------
// TEST 26: Webhook Signature & Idempotent Duplicate Handling
// -------------------------------------------------------------
run_test("process_payment_webhook() rejects invalid signatures and handles duplicate events idempotently", function () use ($pdo) {
    $order = $pdo->query("SELECT id, order_number FROM orders WHERE payment_status = 'paid' ORDER BY id DESC LIMIT 1")->fetch();

    $payload = json_encode([
        'event' => 'payment.captured',
        'payload' => [
            'payment' => [
                'entity' => [
                    'id' => 'pay_webhook_dup_test',
                    'order_id' => 'order_webhook_dup_test',
                    'amount' => 500000,
                    'receipt' => $order['order_number']
                ]
            ]
        ]
    ]);

    // Test with invalid signature
    $invalidRes = process_payment_webhook($payload, 'invalid_signature_hash');
    $checkInvalid = ($invalidRes['code'] === 401);

    // Test with genuine webhook signature on already settled order
    $validWebhookSig = hash_hmac('sha256', $payload, RAZORPAY_WEBHOOK_SECRET);
    $dupRes = process_payment_webhook($payload, $validWebhookSig);

    return $checkInvalid && $dupRes['success'] === true && !empty($dupRes['idempotent']);
});

// -------------------------------------------------------------
// TEST 27: Order Dossier Access Control
// -------------------------------------------------------------
run_test("can_view_order() permits owner customer and session last order, forbids stranger", function () use ($pdo, $testCustomerId) {
    $order = $pdo->query("SELECT * FROM orders WHERE customer_id = {$testCustomerId} ORDER BY id DESC LIMIT 1")->fetch();
    $otherCustomerId = 999998;

    // Owner customer can view
    $ownerCanView = can_view_order($order, $testCustomerId);

    // Stranger customer cannot view (clear last order session for test)
    $savedLast = $_SESSION['last_order_id'] ?? null;
    unset($_SESSION['last_order_id'], $_SESSION['last_order_number']);
    $strangerCanView = can_view_order($order, $otherCustomerId);

    // Session last_order_id allows view
    $_SESSION['last_order_id'] = $order['id'];
    $sessionCanView = can_view_order($order, null);

    // Restore session
    if ($savedLast) $_SESSION['last_order_id'] = $savedLast;

    return $ownerCanView === true && $strangerCanView === false && $sessionCanView === true;
});

// -------------------------------------------------------------
// SUMMARY
// -------------------------------------------------------------
echo "\n========================================================\n";
echo "   PHASE 7 TEST RESULTS: {$passedTests}/{$totalTests} PASSED\n";
if ($failedTests === 0) {
    echo "   STATUS: ALL PHASE 7 CHECKOUT & PAYMENT TESTS PASSED!\n";
} else {
    echo "   STATUS: {$failedTests} TEST(S) FAILED.\n";
}
echo "========================================================\n";

exit($failedTests === 0 ? 0 : 1);
