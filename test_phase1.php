<?php
/**
 * YATIYA COUTURE - Phase 1 Verification & Test Suite
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

echo "=========================================================\n";
echo "   YATIYA COUTURE - PHASE 1 VERIFICATION & TEST SUITE   \n";
echo "=========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assert_test($description, $condition, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $description\n";
        $passCount++;
    } else {
        echo " [FAIL] $description\n";
        if ($details) {
            echo "        Details: $details\n";
        }
        $failCount++;
    }
}

// 1. Database Connection Test
try {
    $connTest = Database::testConnection();
    assert_test(
        "Database Connection via PDO",
        $connTest['status'] === 'connected',
        "DB: {$connTest['database']}, Server: {$connTest['version']}"
    );
} catch (Exception $e) {
    assert_test("Database Connection via PDO", false, $e->getMessage());
}

// 2. Required 22 Tables Verification
$expectedTables = [
    'admin_users',
    'customers',
    'categories',
    'brands',
    'products',
    'product_images',
    'product_variants',
    'inventory_movements',
    'customer_addresses',
    'carts',
    'cart_items',
    'wishlists',
    'coupons',
    'coupon_redemptions',
    'orders',
    'order_items',
    'payments',
    'product_reviews',
    'banners',
    'return_requests',
    'newsletter_subscribers',
    'store_settings'
];

try {
    $stmt = db()->query("SHOW TABLES");
    $actualTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $missing = array_diff($expectedTables, $actualTables);
    assert_test(
        "All 22 Required Database Tables Exist",
        count($missing) === 0 && count($actualTables) >= 22,
        count($missing) ? 'Missing: ' . implode(', ', $missing) : 'Found all 22 tables'
    );
} catch (Exception $e) {
    assert_test("Table Verification", false, $e->getMessage());
}

// 3. Foreign Key Referential Integrity Check
try {
    $fkStmt = db()->query("
        SELECT TABLE_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = 'yatiya_couture' AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $fks = $fkStmt->fetchAll();
    assert_test(
        "Foreign Key Constraints Configured",
        count($fks) >= 15,
        "Found " . count($fks) . " foreign key relationships"
    );
} catch (Exception $e) {
    assert_test("Foreign Key Check", false, $e->getMessage());
}

// 4. Default Super Admin Verification & Password Verification
try {
    $adminStmt = db()->prepare("SELECT * FROM admin_users WHERE username = 'admin'");
    $adminStmt->execute();
    $admin = $adminStmt->fetch();

    $adminOk = $admin && password_verify('Admin@Yatiya2026!', $admin['password_hash']);
    assert_test(
        "Default Admin Exists and Password Verifies via password_verify()",
        $adminOk,
        $admin ? "Username: {$admin['username']}, Role: {$admin['role']}" : "Admin not found"
    );
} catch (Exception $e) {
    assert_test("Admin Verification", false, $e->getMessage());
}

// 5. Initial Categories Verification (12 Categories)
try {
    $catStmt = db()->query("SELECT COUNT(*) FROM categories WHERE is_active = 1");
    $catCount = (int)$catStmt->fetchColumn();
    assert_test(
        "12 Core Luxury Categories Seeded",
        $catCount >= 12,
        "Total active categories found: $catCount"
    );
} catch (Exception $e) {
    assert_test("Category Verification", false, $e->getMessage());
}

// 6. House Brands Seed Verification
try {
    $brandStmt = db()->query("SELECT COUNT(*) FROM brands");
    $brandCount = (int)$brandStmt->fetchColumn();
    assert_test(
        "House Brands / Atelier Lines Seeded",
        $brandCount >= 1,
        "Total brands found: $brandCount"
    );
} catch (Exception $e) {
    assert_test("Brand Verification", false, $e->getMessage());
}

// 7. Store Settings Verification
try {
    $siteName = get_setting('store_name');
    $tagline = get_setting('store_tagline');
    assert_test(
        "Store Settings Seeded & Fetchable via get_setting()",
        $siteName === 'YATIYA COUTURE',
        "Store: $siteName | Tagline: $tagline"
    );
} catch (Exception $e) {
    assert_test("Store Settings Verification", false, $e->getMessage());
}

// 8. CSRF Token Generation & Validation
$token = csrf_token();
$validToken = verify_csrf_token($token);
$invalidToken = verify_csrf_token('invalid-token-xyz');
assert_test(
    "CSRF Token Generation and Hash Equals Validation",
    !empty($token) && $validToken === true && $invalidToken === false,
    "Generated Token: " . substr($token, 0, 10) . '...'
);

// 9. XSS Escaping Helper Test
$rawXSS = '<script>alert("xss")</script>';
$escaped = e($rawXSS);
assert_test(
    "XSS Escaping Function e()",
    $escaped === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
    "Escaped output: $escaped"
);

// 10. Price Formatter Test
$priceTest = format_price(4999.50);
assert_test(
    "Price Formatter format_price()",
    str_contains($priceTest, '4,999.50') || str_contains($priceTest, '₹'),
    "Output: $priceTest"
);

echo "\n---------------------------------------------------------\n";
echo "SUMMARY: Passed: $passCount | Failed: $failCount\n";
echo "---------------------------------------------------------\n";

if ($failCount === 0) {
    echo ">>> PHASE 1 FOUNDATION & DATABASE VALIDATION SUCCESSFUL <<<\n";
    exit(0);
} else {
    echo ">>> PHASE 1 VALIDATION ENCOUNTERED FAILURES <<<\n";
    exit(1);
}

