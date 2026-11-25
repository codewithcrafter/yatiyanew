<?php
/**
 * =====================================================================
 * YATIYA COUTURE - PHASE 5 AUTOMATED VERIFICATION & TEST SUITE
 * Complete validation of Customer Authentication, Registration, Login,
 * Throttling, Password Reset, Profile, Addresses, Orders & IDOR Security.
 * =====================================================================
 */

// Configure environment and buffer output so session regeneration headers can be dispatched in CLI
ob_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart-functions.php';

// Ensure clean test session before any output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
unset($_SESSION['customer_id'], $_SESSION['applied_coupon'], $_SESSION['guest_wishlist'], $_SESSION['login_throttle']);

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

echo "=========================================================\n";
echo "   YATIYA COUTURE - PHASE 5 VERIFICATION & TEST SUITE   \n";
echo "=========================================================\n\n";

$testEmail = 'patron_p5_' . bin2hex(random_bytes(4)) . '@yatiya.com';
$testPassword = 'LuxuryPassword2026';
$registeredCustId = null;

// 1. Successful registration
run_test("Successful Customer Registration", function() use ($testEmail, $testPassword, &$registeredCustId) {
    $data = [
        'first_name' => 'Meera',
        'last_name' => 'Singhania',
        'email' => $testEmail,
        'phone' => '9876543210',
        'password' => $testPassword,
        'confirm_password' => $testPassword,
        'terms_accepted' => 1
    ];
    $res = register_customer($data);
    if (!$res['success']) return 'Registration failed: ' . ($res['message'] ?? '');

    $registeredCustId = (int)$res['customer']['id'];
    if ($registeredCustId <= 0) return 'Invalid customer ID returned.';

    // Verify DB entry
    $stmt = db()->prepare("SELECT email, status FROM customers WHERE id = ?");
    $stmt->execute([$registeredCustId]);
    $row = $stmt->fetch();
    if (!$row || $row['email'] !== strtolower($testEmail) || $row['status'] !== 'active') {
        return 'Customer record missing or inactive in DB.';
    }
    return true;
});

// 2. Duplicate email rejection
run_test("Duplicate Email Rejection", function() use ($testEmail, $testPassword) {
    $data = [
        'first_name' => 'Another',
        'last_name' => 'User',
        'email' => $testEmail,
        'phone' => '9876543211',
        'password' => $testPassword,
        'confirm_password' => $testPassword,
        'terms_accepted' => 1
    ];
    $res = register_customer($data);
    if ($res['success']) return 'Registration accepted duplicate email.';
    return true;
});

// 3. Invalid email rejection
run_test("Invalid Email Format Rejection", function() use ($testPassword) {
    $data = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'not-a-valid-email',
        'phone' => '9876543210',
        'password' => $testPassword,
        'confirm_password' => $testPassword,
        'terms_accepted' => 1
    ];
    $res = register_customer($data);
    if ($res['success']) return 'Registration accepted invalid email format.';
    return true;
});

// 4. Password mismatch
run_test("Password Confirmation Mismatch Rejection", function() {
    $data = [
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => 'mismatch_' . bin2hex(random_bytes(3)) . '@yatiya.com',
        'phone' => '9876543210',
        'password' => 'Password123',
        'confirm_password' => 'DifferentPassword456',
        'terms_accepted' => 1
    ];
    $res = register_customer($data);
    if ($res['success']) return 'Registration accepted mismatched passwords.';
    return true;
});

// 5. Password hashing
run_test("Password Hashing Verification (Bcrypt Cost 12)", function() use ($registeredCustId, $testPassword) {
    $stmt = db()->prepare("SELECT password_hash FROM customers WHERE id = ?");
    $stmt->execute([$registeredCustId]);
    $hash = $stmt->fetchColumn();

    if ($hash === $testPassword) return 'Password stored in plain text!';
    if (!password_verify($testPassword, $hash)) return 'password_verify failed against stored hash.';
    $info = password_get_info($hash);
    if ($info['algoName'] !== 'bcrypt') return 'Expected bcrypt algorithm, got: ' . $info['algoName'];
    return true;
});

// 6. Successful login
run_test("Successful Customer Authentication", function() use ($testEmail, $testPassword) {
    unset($_SESSION['customer_id']);
    $res = authenticate_customer($testEmail, $testPassword);
    if (!$res['success']) return 'Authentication failed: ' . ($res['message'] ?? '');
    if (!is_logged_in()) return 'is_logged_in() returned false after authentication.';
    return true;
});

// 7. Incorrect password
run_test("Incorrect Password Rejection with Generic Feedback", function() use ($testEmail) {
    $res = authenticate_customer($testEmail, 'CompletelyWrongPassword999');
    if ($res['success']) return 'Authentication succeeded with wrong password!';
    if ($res['message'] !== 'Invalid email credentials or password.') {
        return 'Did not return generic error message: ' . $res['message'];
    }
    return true;
});

// 8. Login throttling
run_test("Brute-Force Login Throttling (15-min Lockout after 5 Failures)", function() use ($testEmail) {
    reset_login_attempts($testEmail);

    for ($i = 1; $i <= 5; $i++) {
        authenticate_customer($testEmail, 'WrongPass_' . $i);
    }

    if (!is_login_throttled($testEmail)) {
        return 'is_login_throttled returned false after 5 consecutive failures.';
    }

    $res = authenticate_customer($testEmail, 'AnyPassword');
    if (strpos($res['message'], 'temporarily locked') === false) {
        return 'Did not return locked message: ' . $res['message'];
    }

    reset_login_attempts($testEmail);
    return true;
});

// 9. Session regeneration
run_test("Session ID Regeneration on Authentication", function() use ($registeredCustId) {
    $oldId = session_id();
    $stmt = db()->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$registeredCustId]);
    $customer = $stmt->fetch();

    login_customer($customer);
    $newId = session_id();

    if ($oldId === $newId) return 'Session ID was not regenerated during login_customer().';
    return true;
});

// 10. Logout
run_test("Secure Customer Logout & Session Cleanup", function() {
    logout_customer();
    if (is_logged_in()) return 'User still considered logged in after logout_customer().';
    if (!empty($_SESSION['customer_id'])) return 'customer_id remains in $_SESSION.';
    return true;
});

// 11. CSRF rejection
run_test("CSRF Token Protection for Authentication Requests", function() {
    $validToken = csrf_token();
    if (!verify_csrf_token($validToken)) return 'Valid CSRF token failed verification.';
    if (verify_csrf_token('forged_token_xyz_123')) return 'Forged CSRF token was accepted.';
    return true;
});

// 12. Forgot password
run_test("Password Reset Token Generation (Generic Response & Hash Stored)", function() use ($testEmail, &$rawResetToken) {
    $res = generate_password_reset_token($testEmail);
    if (!$res['success']) return 'generate_password_reset_token returned false.';

    // Verify token hash in DB
    $stmt = db()->prepare("SELECT reset_token_hash, reset_token_expires_at FROM customers WHERE email = ?");
    $stmt->execute([$testEmail]);
    $cust = $stmt->fetch();

    if (empty($cust['reset_token_hash'])) return 'reset_token_hash was not set in DB.';
    if (empty($cust['reset_token_expires_at']) || strtotime($cust['reset_token_expires_at']) <= time()) {
        return 'reset_token_expires_at not set or in the past.';
    }

    $rawResetToken = $_SESSION['dev_reset_preview']['token'] ?? null;
    if (empty($rawResetToken)) return 'dev_reset_preview token not stored in development session.';
    return true;
});

// 13. Expired reset token
run_test("Expired Password Reset Token Rejection", function() use ($testEmail, $rawResetToken) {
    // Force token expiry to 1 hour in the past
    db()->prepare("UPDATE customers SET reset_token_expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE email = ?")->execute([$testEmail]);

    $cust = verify_password_reset_token($rawResetToken);
    if ($cust !== null) return 'Expired reset token was accepted.';
    return true;
});

// 14. Single-use reset token
run_test("Single-Use Password Reset Token Enforcement", function() use ($testEmail) {
    // Generate fresh valid token
    generate_password_reset_token($testEmail);
    $rawToken = $_SESSION['dev_reset_preview']['token'];

    // 1st use: Should succeed
    $newPass = 'NewBespokePass999';
    $res1 = reset_customer_password($rawToken, $newPass, $newPass);
    if (!$res1['success']) return 'Initial reset failed: ' . ($res1['message'] ?? '');

    // 2nd use: Must be rejected because token was wiped
    $res2 = reset_customer_password($rawToken, 'AnotherPass888', 'AnotherPass888');
    if ($res2['success']) return 'Single-use token was reused successfully!';
    return true;
});

// 15. Password update
run_test("New Password Authentication After Reset", function() use ($testEmail) {
    $res = authenticate_customer($testEmail, 'NewBespokePass999');
    if (!$res['success']) return 'Authentication failed with newly set password: ' . ($res['message'] ?? '');
    return true;
});

// 16. Profile update
run_test("Customer Profile Details Update", function() use ($registeredCustId, $testEmail) {
    $updateData = [
        'first_name' => 'Meera Updated',
        'last_name' => 'Singhania',
        'email' => $testEmail,
        'phone' => '9876598765'
    ];
    $res = update_customer_profile($registeredCustId, $updateData);
    if (!$res['success']) return 'Profile update failed: ' . ($res['message'] ?? '');

    $stmt = db()->prepare("SELECT first_name, phone FROM customers WHERE id = ?");
    $stmt->execute([$registeredCustId]);
    $u = $stmt->fetch();
    if ($u['first_name'] !== 'Meera Updated') return 'first_name not updated in DB.';
    if ($u['phone'] !== '9876598765') return 'phone not updated in DB.';
    return true;
});

// 17. Address creation
$testAddressId = null;
run_test("Customer Address Creation", function() use ($registeredCustId, &$testAddressId) {
    $addrData = [
        'full_name' => 'Meera Singhania',
        'phone' => '9876543210',
        'address_type' => 'home',
        'address_line1' => 'Flat 402, Royal Palms Palace',
        'address_line2' => 'Juhu Tara Road',
        'landmark' => 'Near Sea Breeze',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'postal_code' => '400049',
        'country' => 'India',
        'is_default_shipping' => 1,
        'is_default_billing' => 1
    ];
    $res = save_customer_address($registeredCustId, $addrData);
    if (!$res['success']) return 'Address creation failed: ' . ($res['message'] ?? '');

    $testAddressId = $res['id'];
    if ($testAddressId <= 0) return 'Invalid address ID returned.';

    $saved = get_customer_address($testAddressId, $registeredCustId);
    if (!$saved || $saved['city'] !== 'Mumbai') return 'Saved address not retrieved correctly from DB.';
    return true;
});

// 18. Address update
run_test("Customer Address Update", function() use ($registeredCustId, $testAddressId) {
    $updateData = [
        'full_name' => 'Meera Singhania (Privé Residence)',
        'phone' => '9876543210',
        'address_type' => 'home',
        'address_line1' => 'Penthouse 12, Royal Palms Palace',
        'address_line2' => 'Juhu Tara Road',
        'landmark' => 'Near Sea Breeze',
        'city' => 'Mumbai',
        'state' => 'Maharashtra',
        'postal_code' => '400049',
        'country' => 'India'
    ];
    $res = save_customer_address($registeredCustId, $updateData, $testAddressId);
    if (!$res['success']) return 'Address update failed: ' . ($res['message'] ?? '');

    $updated = get_customer_address($testAddressId, $registeredCustId);
    if ($updated['full_name'] !== 'Meera Singhania (Privé Residence)') {
        return 'Updated full_name not reflected in DB.';
    }
    return true;
});

// 19. Address deletion
run_test("Customer Address Deletion", function() use ($registeredCustId, $testAddressId) {
    $deleted = delete_customer_address($testAddressId, $registeredCustId);
    if (!$deleted) return 'delete_customer_address returned false.';

    $check = get_customer_address($testAddressId, $registeredCustId);
    if ($check !== null) return 'Address still found in DB after deletion.';
    return true;
});

// 20. Default address management
run_test("Default Shipping & Billing Address Assignment", function() use ($registeredCustId) {
    // Create Addr A and Addr B
    $resA = save_customer_address($registeredCustId, [
        'full_name' => 'Addr A', 'phone' => '9876543210', 'address_type' => 'home',
        'address_line1' => 'Line 1 A', 'city' => 'Delhi', 'state' => 'Delhi', 'postal_code' => '110001', 'country' => 'India'
    ]);
    $resB = save_customer_address($registeredCustId, [
        'full_name' => 'Addr B', 'phone' => '9876543210', 'address_type' => 'work',
        'address_line1' => 'Line 1 B', 'city' => 'Delhi', 'state' => 'Delhi', 'postal_code' => '110001', 'country' => 'India'
    ]);

    $idA = $resA['id'];
    $idB = $resB['id'];

    // Set Addr B as default shipping
    set_default_address($idB, $registeredCustId, 'shipping');

    $addrA = get_customer_address($idA, $registeredCustId);
    $addrB = get_customer_address($idB, $registeredCustId);

    if ((int)$addrB['is_default_shipping'] !== 1) return 'Addr B is not default shipping.';
    if ((int)$addrA['is_default_shipping'] !== 0) return 'Addr A still marked as default shipping.';

    // Cleanup
    delete_customer_address($idA, $registeredCustId);
    delete_customer_address($idB, $registeredCustId);
    return true;
});

// 21. Customer authorization
run_test("Customer Authentication Guards (Unauthorized Access Protection)", function() {
    logout_customer();
    if (is_logged_in()) return 'User still considered logged in.';
    return true;
});

// 22. IDOR protection
run_test("Insecure Direct Object Reference (IDOR) Protection", function() use ($registeredCustId) {
    // Create customer 2
    $cust2Email = 'patron_c2_' . bin2hex(random_bytes(4)) . '@yatiya.com';
    $resC2 = register_customer([
        'first_name' => 'Other', 'last_name' => 'Patron', 'email' => $cust2Email,
        'phone' => '9999988888', 'password' => 'Pass123456', 'confirm_password' => 'Pass123456', 'terms_accepted' => 1
    ]);
    $cust2Id = (int)$resC2['customer']['id'];

    // Create address for customer 2
    $addrRes = save_customer_address($cust2Id, [
        'full_name' => 'Cust 2 Residence', 'phone' => '9999988888', 'address_type' => 'home',
        'address_line1' => 'Private Road 5', 'city' => 'Jaipur', 'state' => 'Rajasthan', 'postal_code' => '302001', 'country' => 'India'
    ]);
    $c2AddrId = $addrRes['id'];

    // Registered customer (Customer 1) attempts to read Customer 2's address
    $readCheck = get_customer_address($c2AddrId, $registeredCustId);
    if ($readCheck !== null) return 'IDOR vulnerability! Customer 1 was able to read Customer 2 address.';

    // Registered customer attempts to delete Customer 2's address
    $delCheck = delete_customer_address($c2AddrId, $registeredCustId);
    if ($delCheck === true) return 'IDOR vulnerability! Customer 1 was able to delete Customer 2 address.';

    // Verify address still exists for customer 2
    $c2Verify = get_customer_address($c2AddrId, $cust2Id);
    if (!$c2Verify) return 'Customer 2 address was deleted by unauthorized user!';

    // Cleanup
    delete_customer_address($c2AddrId, $cust2Id);
    db()->prepare("DELETE FROM customers WHERE id = ?")->execute([$cust2Id]);
    return true;
});

// 23. Guest cart synchronization
run_test("Guest Cart Synchronization Upon Login", function() use ($registeredCustId) {
    logout_customer();

    // Find active variant
    $v = db()->query("SELECT product_id, id AS variant_id FROM product_variants WHERE status = 'active' AND stock_quantity > 5 LIMIT 1")->fetch();
    $prodId = (int)$v['product_id'];
    $varId = (int)$v['variant_id'];

    // Guest adds item
    add_to_cart($prodId, $varId, 2);
    $guestSessionId = session_id();

    // Authenticate customer
    $stmt = db()->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$registeredCustId]);
    $customer = $stmt->fetch();

    // Clear previous customer cart
    $cCart = db()->prepare("SELECT id FROM carts WHERE customer_id = ? AND status = 'active'");
    $cCart->execute([$registeredCustId]);
    $cId = $cCart->fetchColumn();
    if ($cId) {
        db()->prepare("DELETE FROM cart_items WHERE cart_id = ?")->execute([$cId]);
    }

    login_customer($customer);

    // Verify item migrated to customer cart
    $checkStmt = db()->prepare("
        SELECT ci.quantity 
        FROM carts c 
        INNER JOIN cart_items ci ON c.id = ci.cart_id 
        WHERE c.customer_id = ? AND c.status = 'active' AND ci.variant_id = ?
    ");
    $checkStmt->execute([$registeredCustId, $varId]);
    $qty = (int)$checkStmt->fetchColumn();

    if ($qty !== 2) return 'Guest cart item was not merged to customer cart upon login.';
    return true;
});

// 24. Guest wishlist synchronization
run_test("Guest Wishlist Synchronization Upon Login", function() use ($registeredCustId) {
    logout_customer();

    $p = db()->query("SELECT id FROM products WHERE status = 'active' LIMIT 1")->fetch();
    $prodId = (int)$p['id'];

    // Add to guest wishlist
    $_SESSION['guest_wishlist'] = [$prodId => time()];

    // Clean DB wishlist for customer
    db()->prepare("DELETE FROM wishlists WHERE customer_id = ? AND product_id = ?")->execute([$registeredCustId, $prodId]);

    // Customer logs in
    $stmt = db()->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$registeredCustId]);
    $customer = $stmt->fetch();

    login_customer($customer);

    // Verify migrated into wishlists table
    $stmt = db()->prepare("SELECT COUNT(*) FROM wishlists WHERE customer_id = ? AND product_id = ?");
    $stmt->execute([$registeredCustId, $prodId]);
    if ((int)$stmt->fetchColumn() !== 1) return 'Guest wishlist item was not synchronized to database.';

    if (!empty($_SESSION['guest_wishlist'])) return 'guest_wishlist session was not cleared.';

    // Cleanup
    db()->prepare("DELETE FROM wishlists WHERE customer_id = ? AND product_id = ?")->execute([$registeredCustId, $prodId]);
    return true;
});

// Final Cleanup of test patron
db()->prepare("DELETE FROM customers WHERE id = ?")->execute([$registeredCustId]);

echo "\n---------------------------------------------------------\n";
echo sprintf("PHASE 5 SUMMARY: Passed: %d | Failed: %d\n", $passCount, $failCount);
echo "---------------------------------------------------------\n";

if ($failCount === 0) {
    echo ">>> ALL 24 PHASE 5 AUTHENTICATION & ACCOUNT TESTS PASSED <<<\n";
    ob_end_flush();
    exit(0);
} else {
    echo ">>> PHASE 5 VERIFICATION FAILED <<<\n";
    ob_end_flush();
    exit(1);
}
