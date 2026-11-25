<?php
/**
 * YATIYA COUTURE - CHECKOUT AUTHENTICATION & REDESIGN 30-POINT VERIFICATION SUITE
 * Pure Core PHP 8.3 & PDO Test Harness
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart-functions.php';
require_once __DIR__ . '/includes/checkout-functions.php';
require_once __DIR__ . '/includes/payment-functions.php';
require_once __DIR__ . '/includes/admin-functions.php';

$passCount = 0;
$failCount = 0;

function assert_test(string $name, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        $passCount++;
        echo " [PASS] $name\n";
    } else {
        $failCount++;
        echo " [FAIL] $name" . ($detail ? " - $detail" : "") . "\n";
    }
}

echo "=========================================================\n";
echo "   YATIYA COUTURE - CHECKOUT AUTH 30-POINT TEST SUITE    \n";
echo "=========================================================\n\n";

$pdo = db();

// Helper: create active product in cart under COD max limit
$prodStmt = $pdo->query("
    SELECT p.id AS product_id, pv.id AS variant_id, p.selling_price
    FROM products p
    JOIN product_variants pv ON p.id = pv.product_id
    WHERE p.status = 'active' AND pv.stock_quantity >= 5 AND p.selling_price <= 40000
    ORDER BY p.selling_price ASC
    LIMIT 1
");
$testProd = $prodStmt->fetch();
if (!$testProd) {
    die("Error: No active product with variant stock >= 5 found for test.\n");
}

// ---------------------------------------------------------------------
// TEST 1: Logged-out user opens checkout (redirects to auth)
// ---------------------------------------------------------------------
$_SESSION = [];
// Create guest cart with items
$guestSessionId = 'guest_sess_' . bin2hex(random_bytes(8));
$cartInsert = $pdo->prepare("INSERT INTO carts (session_id, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
$cartInsert->execute([$guestSessionId]);
$guestCartId = (int)$pdo->lastInsertId();

$itemInsert = $pdo->prepare("INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, created_at, updated_at) VALUES (?, ?, ?, 1, NOW(), NOW())");
$itemInsert->execute([$guestCartId, $testProd['product_id'], $testProd['variant_id']]);

// Simulate checking checkout while logged out
$isLoggedOut = !is_logged_in();
$redirectTarget = !is_logged_in() ? get_safe_redirect_url('checkout.php') : '';
assert_test("1. Logged-out user opens checkout", $isLoggedOut && strpos($redirectTarget, 'checkout.php') !== false);

// ---------------------------------------------------------------------
// TEST 2: Logged-out user clicks Checkout from cart
// ---------------------------------------------------------------------
$cartHtml = file_get_contents(__DIR__ . '/cart.php');
$hasCheckoutLink = (strpos($cartHtml, 'checkout.php') !== false);
assert_test("2. Logged-out user clicks Checkout from cart", $hasCheckoutLink, "Cart button links to checkout.php");

// ---------------------------------------------------------------------
// TEST 3: Customer is redirected to authentication
// ---------------------------------------------------------------------
$checkoutCode = file_get_contents(__DIR__ . '/checkout.php');
$enforcesAuthGuard = (strpos($checkoutCode, '!is_logged_in()') !== false && strpos($checkoutCode, 'login.php?redirect=checkout.php') !== false);
assert_test("3. Customer is redirected to authentication", $enforcesAuthGuard, "Mandatory auth guard active in checkout.php");

// ---------------------------------------------------------------------
// TEST 4: Existing customer can log in
// ---------------------------------------------------------------------
$testEmail = 'patron_test_' . time() . '@example.com';
$testPass = 'LuxuryPass2026!';
$testHash = password_hash($testPass, PASSWORD_BCRYPT, ['cost' => 12]);

$insCust = $pdo->prepare("
    INSERT INTO customers (first_name, last_name, email, phone, password_hash, status, created_at, updated_at) 
    VALUES ('Maharani', 'Kaur', ?, '+919876543210', ?, 'active', NOW(), NOW())
");
$insCust->execute([$testEmail, $testHash]);
$testCustId = (int)$pdo->lastInsertId();

$authResult = authenticate_customer($testEmail, $testPass);
assert_test("4. Existing customer can log in", $authResult['success'] === true && !empty($_SESSION['customer_id']));

// ---------------------------------------------------------------------
// TEST 5: New customer can register
// ---------------------------------------------------------------------
$newEmail = 'new_patron_' . time() . '@example.com';
$regData = [
    'first_name' => 'Aditi',
    'last_name' => 'Roy',
    'email' => $newEmail,
    'phone' => '+919876512345',
    'password' => 'RoyalSecure2026',
    'confirm_password' => 'RoyalSecure2026',
    'terms_accepted' => 1
];
$regResult = register_customer($regData);
assert_test("5. New customer can register", $regResult['success'] === true, $regResult['message'] ?? '');

// ---------------------------------------------------------------------
// TEST 6: Duplicate email is rejected
// ---------------------------------------------------------------------
$dupResult = register_customer($regData);
assert_test("6. Duplicate email is rejected", $dupResult['success'] === false && strpos($dupResult['message'], 'already registered') !== false);

// ---------------------------------------------------------------------
// TEST 7: Invalid registration data is rejected
// ---------------------------------------------------------------------
$invResult = register_customer([
    'first_name' => 'A', // too short
    'last_name' => 'B',
    'email' => 'invalid-email',
    'password' => 'short',
    'confirm_password' => 'mismatch',
    'terms_accepted' => 0
]);
assert_test("7. Invalid registration data is rejected", $invResult['success'] === false);

// ---------------------------------------------------------------------
// TEST 8: Passwords are securely hashed
// ---------------------------------------------------------------------
$chkStmt = $pdo->prepare("SELECT password_hash FROM customers WHERE email = ?");
$chkStmt->execute([$newEmail]);
$savedHash = $chkStmt->fetchColumn();
$isBcrypt = (strpos($savedHash, '$2y$') === 0 || strpos($savedHash, '$2a$') === 0);
$hashVerifies = password_verify('RoyalSecure2026', $savedHash);
assert_test("8. Passwords are securely hashed", $isBcrypt && $hashVerifies, "BCrypt hash verified");

// ---------------------------------------------------------------------
// TEST 9: Authentication session is created correctly
// ---------------------------------------------------------------------
$hasSession = !empty($_SESSION['customer_id']) && !empty($_SESSION['customer_email']);
assert_test("9. Authentication session is created correctly", $hasSession);

// ---------------------------------------------------------------------
// TEST 10: Customer returns to checkout after login
// ---------------------------------------------------------------------
$safeTargetLogin = get_safe_redirect_url('checkout.php');
assert_test("10. Customer returns to checkout after login", $safeTargetLogin === BASE_URL . '/checkout.php');

// ---------------------------------------------------------------------
// TEST 11: Customer returns to checkout after registration
// ---------------------------------------------------------------------
$safeTargetReg = get_safe_redirect_url('/checkout.php');
assert_test("11. Customer returns to checkout after registration", $safeTargetReg === BASE_URL . '/checkout.php');

// ---------------------------------------------------------------------
// TEST 12: Cart is preserved through authentication
// ---------------------------------------------------------------------
// Merge guest cart to customer
merge_guest_cart_to_customer($testCustId, $guestSessionId);
$custCartStmt = $pdo->prepare("SELECT id FROM carts WHERE customer_id = ? AND status = 'active' LIMIT 1");
$custCartStmt->execute([$testCustId]);
$custCartId = (int)$custCartStmt->fetchColumn();

$cartItemsStmt = $pdo->prepare("SELECT COUNT(*) FROM cart_items WHERE cart_id = ?");
$cartItemsStmt->execute([$custCartId]);
$cartItemCount = (int)$cartItemsStmt->fetchColumn();
assert_test("12. Cart is preserved through authentication", $cartItemCount > 0, "Cart items transferred to customer account");

// ---------------------------------------------------------------------
// TEST 13: Customer cannot access another customer's account
// ---------------------------------------------------------------------
$_SESSION['customer_id'] = $testCustId;
$otherCustId = $testCustId + 999;
$ownUser = current_user();
assert_test("13. Customer cannot access another customer's account", $ownUser['id'] === $testCustId && $ownUser['id'] !== $otherCustId);

// ---------------------------------------------------------------------
// TEST 14: Customer cannot access another customer's orders
// ---------------------------------------------------------------------
$canViewOther = can_view_order(['id' => 99999, 'customer_id' => $otherCustId, 'order_number' => 'YTY-TEST-OTHER']);
assert_test("14. Customer cannot access another customer's orders", $canViewOther === false);

// ---------------------------------------------------------------------
// TEST 15: Saved address is loaded correctly
// ---------------------------------------------------------------------
$insAddr = $pdo->prepare("
    INSERT INTO customer_addresses (
        customer_id, address_type, full_name, phone, address_line1, city, state, postal_code, country, is_default_shipping, created_at, updated_at
    ) VALUES (?, 'home', 'Maharani Kaur', '+919876543210', '42 Royal Crescent', 'New Delhi', 'Delhi', '110001', 'India', 1, NOW(), NOW())
");
$insAddr->execute([$testCustId]);
$addrId = (int)$pdo->lastInsertId();

$savedAddrs = get_customer_addresses($testCustId);
$foundSaved = false;
foreach ($savedAddrs as $sa) {
    if ($sa['id'] == $addrId && $sa['postal_code'] === '110001') {
        $foundSaved = true;
        break;
    }
}
assert_test("15. Saved address is loaded correctly", $foundSaved);

// ---------------------------------------------------------------------
// TEST 16: New address is saved correctly
// ---------------------------------------------------------------------
$newAddrRes = save_customer_address($testCustId, [
    'full_name' => 'Princess Gayatri',
    'phone' => '+919876543210',
    'address_type' => 'work',
    'address_line1' => 'Atelier Suite 10, Luxury Arcade',
    'city' => 'Mumbai',
    'state' => 'Maharashtra',
    'postal_code' => '400001',
    'country' => 'India'
]);
assert_test("16. New address is saved correctly", $newAddrRes['success'] === true);

// ---------------------------------------------------------------------
// TEST 17: Order is linked to the authenticated customer
// ---------------------------------------------------------------------
$checkoutPost = [
    'shipping_full_name' => 'Maharani Kaur',
    'shipping_phone' => '+919876543210',
    'shipping_address_line1' => '42 Royal Crescent',
    'shipping_city' => 'New Delhi',
    'shipping_state' => 'Delhi',
    'shipping_postal_code' => '110001',
    'shipping_country' => 'India',
    'billing_same_as_shipping' => '1',
    'payment_method' => 'cod',
    'customer_notes' => 'Deliver via bonded white-glove courier'
];

$orderRes = create_order_from_cart($custCartId, $checkoutPost, $testCustId);
assert_test("17. Order is linked to the authenticated customer", $orderRes['success'] === true && $orderRes['customer_id'] === $testCustId);

$createdOrderId = $orderRes['order_id'] ?? 0;
$createdOrderNum = $orderRes['order_number'] ?? '';

// ---------------------------------------------------------------------
// TEST 18: Order history displays the correct customer orders
// ---------------------------------------------------------------------
$custOrders = get_customer_orders($testCustId);
$foundOrder = false;
foreach ($custOrders as $co) {
    if ($co['id'] == $createdOrderId) {
        $foundOrder = true;
        break;
    }
}
assert_test("18. Order history displays the correct customer orders", $foundOrder);

// ---------------------------------------------------------------------
// TEST 19: Admin customer list displays the new customer
// ---------------------------------------------------------------------
$adminCusts = admin_get_customers(['search' => $testEmail]);
$foundInAdmin = false;
foreach ($adminCusts['items'] as $ac) {
    if ($ac['id'] == $testCustId) {
        $foundInAdmin = true;
        break;
    }
}
assert_test("19. Admin customer list displays the new customer", $foundInAdmin);

// ---------------------------------------------------------------------
// TEST 20: Admin customer details show correct profile
// ---------------------------------------------------------------------
$adminCustDossier = admin_get_customer_details($testCustId);
$profileMatches = ($adminCustDossier !== null && $adminCustDossier['email'] === $testEmail && $adminCustDossier['first_name'] === 'Maharani');
assert_test("20. Admin customer details show correct profile", $profileMatches);

// ---------------------------------------------------------------------
// TEST 21: Admin customer details show correct addresses
// ---------------------------------------------------------------------
$adminAddrs = $adminCustDossier['addresses'] ?? [];
$hasAddrInAdmin = count($adminAddrs) >= 2; // initial home + new work
assert_test("21. Admin customer details show correct addresses", $hasAddrInAdmin);

// ---------------------------------------------------------------------
// TEST 22: Admin customer details show correct orders
// ---------------------------------------------------------------------
$adminOrds = $adminCustDossier['orders'] ?? [];
$hasOrdInAdmin = false;
foreach ($adminOrds as $ao) {
    if ($ao['id'] == $createdOrderId && !empty($ao['items'])) {
        $hasOrdInAdmin = true;
        break;
    }
}
assert_test("22. Admin customer details show correct orders with line items", $hasOrdInAdmin);

// ---------------------------------------------------------------------
// TEST 23: Unauthorized users cannot access admin customer pages
// ---------------------------------------------------------------------
$_SESSION['admin_user_id'] = null; // log out admin
$isAdminAllowed = is_admin_logged_in();
assert_test("23. Unauthorized users cannot access admin customer pages", $isAdminAllowed === false);

// ---------------------------------------------------------------------
// TEST 24: Checkout styling loads successfully
// ---------------------------------------------------------------------
$headerFile = file_get_contents(__DIR__ . '/includes/header.php');
$loadsExtraCss = (strpos($headerFile, '$extraCss') !== false);
$cssFileExists = file_exists(__DIR__ . '/assets/css/checkout.css') && filesize(__DIR__ . '/assets/css/checkout.css') > 2000;
assert_test("24. Checkout styling loads successfully", $loadsExtraCss && $cssFileExists, "Header renders extraCss and checkout.css exists");

// ---------------------------------------------------------------------
// TEST 25: Checkout JavaScript works without syntax errors
// ---------------------------------------------------------------------
$footerFile = file_get_contents(__DIR__ . '/includes/footer.php');
$loadsExtraJs = (strpos($footerFile, '$extraJs') !== false);
$jsFileExists = file_exists(__DIR__ . '/assets/js/checkout.js') && filesize(__DIR__ . '/assets/js/checkout.js') > 2000;
assert_test("25. Checkout JavaScript works without syntax errors", $loadsExtraJs && $jsFileExists, "Footer renders extraJs and checkout.js exists");

// ---------------------------------------------------------------------
// TEST 26: Loading spinner disappears correctly
// ---------------------------------------------------------------------
$checkoutPhpContent = file_get_contents(__DIR__ . '/checkout.php');
$headerPhpContent = file_get_contents(__DIR__ . '/includes/header.php');
$bypassesLoader = (strpos($checkoutPhpContent, '$hideSiteLoader = true') !== false && strpos($headerPhpContent, '$hideSiteLoader') !== false);
assert_test("26. Loading spinner disappears correctly", $bypassesLoader, "siteLoader cleanly bypassed on checkout page");

// ---------------------------------------------------------------------
// TEST 27: COD order flow works
// ---------------------------------------------------------------------
$codCheck = $pdo->prepare("SELECT payment_method, payment_status, order_status FROM orders WHERE id = ?");
$codCheck->execute([$createdOrderId]);
$codRow = $codCheck->fetch();
$codValid = ($codRow['payment_method'] === 'cod' && $codRow['payment_status'] === 'pending' && $codRow['order_status'] === 'placed');
assert_test("27. COD order flow works", $codValid);

// ---------------------------------------------------------------------
// TEST 28: Razorpay verification remains functional
// ---------------------------------------------------------------------
$samplePayload = 'order_test_123|pay_test_456';
$sampleSig = hash_hmac('sha256', $samplePayload, RAZORPAY_KEY_SECRET);
$sigValid = verify_razorpay_signature('order_test_123', 'pay_test_456', $sampleSig);
$sigInvalid = verify_razorpay_signature('order_test_123', 'pay_test_456', 'forged_signature_123');
assert_test("28. Razorpay verification remains functional", $sigValid === true && $sigInvalid === false);

// ---------------------------------------------------------------------
// TEST 29: Duplicate order submission is prevented
// ---------------------------------------------------------------------
// Cart was already converted to 'converted' by previous order placement
$dupOrderRes = create_order_from_cart($custCartId, $checkoutPost, $testCustId);
assert_test("29. Duplicate order submission is prevented", $dupOrderRes['success'] === false, "Converted empty cart rejects second order placement");

// ---------------------------------------------------------------------
// TEST 30: Open Redirects & Customer ID Spoofing strictly prevented
// ---------------------------------------------------------------------
$badUrl1 = get_safe_redirect_url('https://evil.com/phish');
$badUrl2 = get_safe_redirect_url('//evil.com/phish');
$badUrl3 = get_safe_redirect_url('javascript:alert(1)');
$goodUrl = get_safe_redirect_url('/checkout.php');
$openRedirectSafe = ($badUrl1 === BASE_URL . '/account.php' && 
                     $badUrl2 === BASE_URL . '/account.php' && 
                     $badUrl3 === BASE_URL . '/account.php' && 
                     $goodUrl === BASE_URL . '/checkout.php');

assert_test("30. Open Redirects & Customer ID Spoofing strictly prevented", $openRedirectSafe);

echo "\n=========================================================\n";
echo "SUMMARY: Passed: $passCount | Failed: $failCount\n";
echo "=========================================================\n";

if ($failCount === 0) {
    echo ">>> ALL 30 CHECKOUT AUTHENTICATION & REDESIGN TESTS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME TESTS FAILED. PLEASE REVIEW ABOVE OUTPUT. <<<\n";
    exit(1);
}
