<?php
/**
 * YATIYA COUTURE - Authentication & Authorization Management
 * Handles customer and admin sessions, registration, login throttling,
 * password resets, profile management, addresses, and order queries.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/* =====================================================================
   1. CUSTOMER AUTHENTICATION STATE & SESSION
   ===================================================================== */

/**
 * Check if a customer is logged in
 */
function is_logged_in(): bool {
    if (empty($_SESSION['customer_id']) && !empty($_COOKIE['yty_remember'])) {
        check_remember_me();
    }
    return !empty($_SESSION['customer_id']);
}

/**
 * Get currently authenticated customer record
 */
function current_user(): ?array {
    static $user = null;

    if (!is_logged_in()) {
        return null;
    }

    if ($user === null) {
        try {
            $stmt = db()->prepare("
                SELECT id, first_name, last_name, email, phone, status, email_verified_at, created_at, updated_at
                FROM customers
                WHERE id = ? AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['customer_id']]);
            $user = $stmt->fetch() ?: null;

            // If account was suspended or deleted, forcibly log out
            if (!$user) {
                logout_customer();
            }
        } catch (Exception $e) {
            $user = null;
        }
    }

    return $user;
}

/**
 * Guard route requiring customer authentication
 */
function require_login(?string $redirectUrl = null): void {
    if (!is_logged_in()) {
        $target = $redirectUrl ?? $_SERVER['REQUEST_URI'] ?? (BASE_URL . '/account.php');
        $_SESSION['intended_url'] = $target;
        set_flash('error', 'Please sign in to access your luxury couture account.');
        redirect(BASE_URL . '/login.php');
    }
}

/**
 * Log in customer and regenerate session ID to prevent session fixation
 * Seamlessly merges guest session cart and wishlist into customer account
 */
function login_customer(array $customer, bool $rememberMe = false): void {
    $guestSessionId = session_id();
    $customerId = (int)$customer['id'];

    // Merge guest cart & wishlist before session ID regeneration
    if (function_exists('merge_guest_cart_to_customer')) {
        merge_guest_cart_to_customer($customerId, $guestSessionId);
    }
    if (function_exists('sync_guest_wishlist_to_customer')) {
        sync_guest_wishlist_to_customer($customerId);
    }

    // Regenerate session ID to prevent session fixation attacks
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['customer_id'] = $customerId;
    $_SESSION['customer_name'] = $customer['first_name'] . ' ' . $customer['last_name'];
    $_SESSION['customer_email'] = $customer['email'];
    $_SESSION['logged_in_time'] = time();

    // Update last login timestamp in database
    try {
        $stmt = db()->prepare("UPDATE customers SET last_login_at = NOW() WHERE id = ?");
        $stmt->execute([$customerId]);
    } catch (Exception $e) {
        // Silently continue
    }

    // Secure Remember-Me implementation
    if ($rememberMe) {
        set_remember_me_cookie($customerId);
    }
}

/**
 * Log out customer securely, destroying session and clearing cookies
 */
function logout_customer(): void {
    $customerId = $_SESSION['customer_id'] ?? null;
    if ($customerId) {
        try {
            db()->prepare("UPDATE customers SET remember_token = NULL WHERE id = ?")->execute([$customerId]);
        } catch (Exception $e) {}
    }

    // Clear remember cookie
    if (isset($_COOKIE['yty_remember'])) {
        $params = session_get_cookie_params();
        setcookie('yty_remember', '', [
            'expires' => time() - 3600,
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'],
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        unset($_COOKIE['yty_remember']);
    }

    unset($_SESSION['customer_id'], $_SESSION['customer_name'], $_SESSION['customer_email'], $_SESSION['logged_in_time']);
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
}

/**
 * Set secure remember-me cookie with cryptographically strong token hash
 */
function set_remember_me_cookie(int $customerId): void {
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);

    try {
        db()->prepare("UPDATE customers SET remember_token = ? WHERE id = ?")
            ->execute([$tokenHash, $customerId]);

        $cookieVal = $customerId . ':' . $rawToken;
        $params = session_get_cookie_params();
        setcookie('yty_remember', $cookieVal, [
            'expires' => time() + (30 * 86400), // 30 days
            'path' => $params['path'] ?: '/',
            'domain' => $params['domain'],
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } catch (Exception $e) {}
}

/**
 * Check and authenticate user via remember-me cookie if session is expired
 */
function check_remember_me(): void {
    if (is_logged_in() || empty($_COOKIE['yty_remember'])) {
        return;
    }

    $parts = explode(':', $_COOKIE['yty_remember'], 2);
    if (count($parts) !== 2) {
        return;
    }

    $custId = (int)$parts[0];
    $rawToken = $parts[1];
    $tokenHash = hash('sha256', $rawToken);

    try {
        $stmt = db()->prepare("
            SELECT id, first_name, last_name, email, status 
            FROM customers 
            WHERE id = ? AND remember_token = ? AND status = 'active' 
            LIMIT 1
        ");
        $stmt->execute([$custId, $tokenHash]);
        $customer = $stmt->fetch();

        if ($customer) {
            login_customer($customer, true);
        }
    } catch (Exception $e) {}
}

/* =====================================================================
   2. LOGIN ATTEMPT THROTTLING (BRUTE-FORCE MITIGATION)
   ===================================================================== */

/**
 * Check if login attempts for an email/IP are currently throttled
 */
function is_login_throttled(string $email): bool {
    $key = strtolower(trim($email));
    if (empty($_SESSION['login_throttle'][$key])) {
        return false;
    }

    $throttle = $_SESSION['login_throttle'][$key];
    if (!empty($throttle['lockout_until'])) {
        if (time() < $throttle['lockout_until']) {
            return true;
        }
        // Lockout expired, reset
        unset($_SESSION['login_throttle'][$key]);
        return false;
    }

    return false;
}

/**
 * Record a failed login attempt; enforces 15-min lockout after 5 consecutive failures
 */
function record_failed_login_attempt(string $email): void {
    $key = strtolower(trim($email));
    if (!isset($_SESSION['login_throttle'])) {
        $_SESSION['login_throttle'] = [];
    }

    if (!isset($_SESSION['login_throttle'][$key])) {
        $_SESSION['login_throttle'][$key] = [
            'count' => 0,
            'last_attempt' => time(),
            'lockout_until' => 0
        ];
    }

    $_SESSION['login_throttle'][$key]['count']++;
    $_SESSION['login_throttle'][$key]['last_attempt'] = time();

    // After 5 failed attempts, apply a 15-minute (900 seconds) lockout
    if ($_SESSION['login_throttle'][$key]['count'] >= 5) {
        $_SESSION['login_throttle'][$key]['lockout_until'] = time() + 900;
    }
}

/**
 * Reset login attempts upon successful authentication
 */
function reset_login_attempts(string $email): void {
    $key = strtolower(trim($email));
    if (isset($_SESSION['login_throttle'][$key])) {
        unset($_SESSION['login_throttle'][$key]);
    }
}

/**
 * Get remaining lockout seconds
 */
function get_throttle_remaining_time(string $email): int {
    $key = strtolower(trim($email));
    if (empty($_SESSION['login_throttle'][$key]['lockout_until'])) {
        return 0;
    }
    return max(0, $_SESSION['login_throttle'][$key]['lockout_until'] - time());
}

/* =====================================================================
   3. REGISTRATION & AUTHENTICATION ENGINES
   ===================================================================== */

/**
 * Register a new customer account with strict server-side validation
 */
function register_customer(array $data): array {
    $firstName = clean_input($data['first_name'] ?? '');
    $lastName = clean_input($data['last_name'] ?? '');
    $email = strtolower(clean_input($data['email'] ?? ''));
    $phone = clean_input($data['phone'] ?? '');
    $password = (string)($data['password'] ?? '');
    $confirmPassword = (string)($data['confirm_password'] ?? '');
    $terms = !empty($data['terms_accepted']) || !empty($data['terms']);

    // 1. Name validation
    if (empty($firstName) || strlen($firstName) < 2 || strlen($firstName) > 100) {
        return ['success' => false, 'message' => 'Please enter a valid first name (2-100 characters).'];
    }
    if (empty($lastName) || strlen($lastName) < 1 || strlen($lastName) > 100) {
        return ['success' => false, 'message' => 'Please enter a valid last name (1-100 characters).'];
    }

    // 2. Email format validation
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 191) {
        return ['success' => false, 'message' => 'Please provide a valid and deliverable email address.'];
    }

    // 3. Unique email verification
    $checkStmt = db()->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
    $checkStmt->execute([$email]);
    if ($checkStmt->fetch()) {
        return ['success' => false, 'message' => 'An account is already registered with this email address. Please sign in instead.'];
    }

    // 4. Phone validation (optional or 10-15 digits)
    $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
    if (!empty($phone) && (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 15)) {
        return ['success' => false, 'message' => 'Please enter a valid mobile number (10-15 digits).'];
    }

    // 5. Password validation
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must contain at least 8 characters.'];
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return ['success' => false, 'message' => 'Password must contain both letters and at least one number for luxury security.'];
    }
    if ($password !== $confirmPassword) {
        return ['success' => false, 'message' => 'Password and confirmation password do not match.'];
    }

    // 6. Terms acceptance
    if (!$terms) {
        return ['success' => false, 'message' => 'You must accept the Yatiya Couture Terms of Service and Privacy Policy.'];
    }

    // 7. Secure password hash
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    try {
        $insertStmt = db()->prepare("
            INSERT INTO customers (first_name, last_name, email, phone, password_hash, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
        ");
        $insertStmt->execute([$firstName, $lastName, $email, $cleanPhone ?: null, $passwordHash]);
        $newCustomerId = (int)db()->lastInsertId();

        $newCustomer = [
            'id' => $newCustomerId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'phone' => $cleanPhone
        ];

        // Automatically log in the newly registered customer
        login_customer($newCustomer);

        return [
            'success' => true,
            'message' => 'Welcome to Yatiya Couture. Your bespoke atelier account has been created.',
            'customer' => $newCustomer
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Unable to complete registration. Please try again later.'];
    }
}

/**
 * Authenticate customer credentials with throttling and timing attack protection
 */
function authenticate_customer(string $email, string $password, bool $rememberMe = false): array {
    $cleanEmail = strtolower(trim($email));

    if (empty($cleanEmail) || empty($password)) {
        return ['success' => false, 'message' => 'Please provide both your email address and password.'];
    }

    // Check throttling
    if (is_login_throttled($cleanEmail)) {
        $rem = get_throttle_remaining_time($cleanEmail);
        $minutes = ceil($rem / 60);
        return [
            'success' => false,
            'message' => "Too many failed login attempts. For your security, this account is temporarily locked for {$minutes} minute(s)."
        ];
    }

    // Fetch customer record
    $stmt = db()->prepare("
        SELECT id, first_name, last_name, email, phone, password_hash, status 
        FROM customers 
        WHERE email = ? 
        LIMIT 1
    ");
    $stmt->execute([$cleanEmail]);
    $customer = $stmt->fetch();

    if ($customer && $customer['status'] === 'active') {
        if (password_verify($password, $customer['password_hash'])) {
            // Password verified successfully
            reset_login_attempts($cleanEmail);

            // Rehash password if cost factor or algorithm has upgraded
            if (password_needs_rehash($customer['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
                $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                db()->prepare("UPDATE customers SET password_hash = ? WHERE id = ?")->execute([$newHash, $customer['id']]);
            }

            login_customer($customer, $rememberMe);

            return [
                'success' => true,
                'message' => 'Welcome back, ' . e($customer['first_name']) . '.',
                'customer' => $customer
            ];
        } else {
            record_failed_login_attempt($cleanEmail);
            return ['success' => false, 'message' => 'Invalid email credentials or password.'];
        }
    } else {
        // Dummy verification to protect against timing attack email enumeration
        password_verify($password, '$2y$12$e8wG62/Q9M.hLq5kK41cmeR2uMv4w2vC4yH.K6b7UjK4p4C7Q4z3q');
        record_failed_login_attempt($cleanEmail);
        return ['success' => false, 'message' => 'Invalid email credentials or password.'];
    }
}

/* =====================================================================
   4. FORGOT & RESET PASSWORD WORKFLOW
   ===================================================================== */

/**
 * Generate cryptographically secure password reset token
 * Returns a generic success message to prevent user enumeration
 */
function generate_password_reset_token(string $email): array {
    $cleanEmail = strtolower(trim($email));

    // Always prepare a generic user-facing response to prevent email probing
    $genericResponse = [
        'success' => true,
        'message' => 'If an atelier account is associated with this email address, password reset instructions have been dispatched.'
    ];

    if (empty($cleanEmail) || !filter_var($cleanEmail, FILTER_VALIDATE_EMAIL)) {
        return $genericResponse;
    }

    $stmt = db()->prepare("SELECT id, first_name, status FROM customers WHERE email = ? LIMIT 1");
    $stmt->execute([$cleanEmail]);
    $customer = $stmt->fetch();

    if (!$customer || $customer['status'] !== 'active') {
        return $genericResponse;
    }

    // Generate 32-byte cryptographically secure random token
    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $rawToken);
    $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 Hour Expiry

    db()->prepare("
        UPDATE customers 
        SET reset_token_hash = ?, reset_token_expires_at = ?, updated_at = NOW() 
        WHERE id = ?
    ")->execute([$tokenHash, $expiresAt, $customer['id']]);

    // Secure development-only token delivery (Since SMTP is not configured)
    $resetLink = BASE_URL . '/reset-password.php?token=' . $rawToken;
    $_SESSION['dev_reset_preview'] = [
        'email' => $cleanEmail,
        'token' => $rawToken,
        'reset_link' => $resetLink,
        'created_at' => time()
    ];

    return $genericResponse;
}

/**
 * Verify validity and single-use status of a reset token
 */
function verify_password_reset_token(string $rawToken): ?array {
    if (empty($rawToken)) {
        return null;
    }

    $tokenHash = hash('sha256', $rawToken);

    $stmt = db()->prepare("
        SELECT id, first_name, last_name, email, reset_token_expires_at 
        FROM customers 
        WHERE reset_token_hash = ? AND reset_token_expires_at > NOW() AND status = 'active' 
        LIMIT 1
    ");
    $stmt->execute([$tokenHash]);
    return $stmt->fetch() ?: null;
}

/**
 * Reset customer password using single-use token and invalidate token
 */
function reset_customer_password(string $rawToken, string $newPassword, string $confirmPassword): array {
    $customer = verify_password_reset_token($rawToken);

    if (!$customer) {
        return [
            'success' => false,
            'message' => 'This password reset link is invalid, expired, or has already been used. Please request a new one.'
        ];
    }

    if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'New password must contain at least 8 characters.'];
    }
    if (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        return ['success' => false, 'message' => 'New password must contain both letters and at least one number.'];
    }
    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'Password confirmation does not match new password.'];
    }

    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    // Atomic update: update password and immediately clear reset token so it cannot be reused
    db()->prepare("
        UPDATE customers 
        SET password_hash = ?, reset_token_hash = NULL, reset_token_expires_at = NULL, remember_token = NULL, updated_at = NOW() 
        WHERE id = ?
    ")->execute([$passwordHash, $customer['id']]);

    unset($_SESSION['dev_reset_preview']);

    return [
        'success' => true,
        'message' => 'Your password has been successfully updated. Please sign in with your new credentials.'
    ];
}

/* =====================================================================
   5. PROFILE & CREDENTIAL MANAGEMENT
   ===================================================================== */

/**
 * Update personal customer profile details
 */
function update_customer_profile(int $customerId, array $data): array {
    $firstName = clean_input($data['first_name'] ?? '');
    $lastName = clean_input($data['last_name'] ?? '');
    $phone = clean_input($data['phone'] ?? '');
    $email = strtolower(clean_input($data['email'] ?? ''));

    if (empty($firstName) || strlen($firstName) < 2) {
        return ['success' => false, 'message' => 'Please enter a valid first name.'];
    }
    if (empty($lastName)) {
        return ['success' => false, 'message' => 'Please enter a valid last name.'];
    }

    // Email update verification
    if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $checkStmt = db()->prepare("SELECT id FROM customers WHERE email = ? AND id != ? LIMIT 1");
        $checkStmt->execute([$email, $customerId]);
        if ($checkStmt->fetch()) {
            return ['success' => false, 'message' => 'This email address is already registered to another account.'];
        }
    } else {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }

    $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
    if (!empty($phone) && (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 15)) {
        return ['success' => false, 'message' => 'Please enter a valid mobile number.'];
    }

    db()->prepare("
        UPDATE customers 
        SET first_name = ?, last_name = ?, phone = ?, email = ?, updated_at = NOW() 
        WHERE id = ?
    ")->execute([$firstName, $lastName, $cleanPhone ?: null, $email, $customerId]);

    $_SESSION['customer_name'] = $firstName . ' ' . $lastName;
    $_SESSION['customer_email'] = $email;

    return ['success' => true, 'message' => 'Your personal atelier profile has been updated.'];
}

/**
 * Change customer password verifying current password
 */
function change_customer_password(int $customerId, string $currentPassword, string $newPassword, string $confirmPassword): array {
    $stmt = db()->prepare("SELECT password_hash FROM customers WHERE id = ? LIMIT 1");
    $stmt->execute([$customerId]);
    $hash = $stmt->fetchColumn();

    if (!$hash || !password_verify($currentPassword, $hash)) {
        return ['success' => false, 'message' => 'Your current password is incorrect.'];
    }

    if (strlen($newPassword) < 8) {
        return ['success' => false, 'message' => 'New password must contain at least 8 characters.'];
    }
    if (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        return ['success' => false, 'message' => 'New password must contain both letters and at least one number.'];
    }
    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'message' => 'New password and confirmation do not match.'];
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
    db()->prepare("UPDATE customers SET password_hash = ?, remember_token = NULL, updated_at = NOW() WHERE id = ?")
        ->execute([$newHash, $customerId]);

    return ['success' => true, 'message' => 'Your password has been changed successfully.'];
}

/* =====================================================================
   6. CUSTOMER ADDRESS BOOK MANAGEMENT (IDOR PROTECTED)
   ===================================================================== */

/**
 * Retrieve all addresses belonging strictly to the customer
 */
function get_customer_addresses(int $customerId): array {
    $stmt = db()->prepare("
        SELECT * FROM customer_addresses 
        WHERE customer_id = ? 
        ORDER BY is_default_shipping DESC, is_default_billing DESC, id DESC
    ");
    $stmt->execute([$customerId]);
    return $stmt->fetchAll();
}

/**
 * Retrieve a single address with IDOR ownership validation
 */
function get_customer_address(int $addressId, int $customerId): ?array {
    $stmt = db()->prepare("
        SELECT * FROM customer_addresses 
        WHERE id = ? AND customer_id = ? 
        LIMIT 1
    ");
    $stmt->execute([$addressId, $customerId]);
    return $stmt->fetch() ?: null;
}

/**
 * Save (create or update) customer address with strict validation & IDOR check
 */
function save_customer_address(int $customerId, array $data, ?int $addressId = null): array {
    $fullName = clean_input($data['full_name'] ?? '');
    $phone = clean_input($data['phone'] ?? '');
    $type = clean_input($data['address_type'] ?? 'home');
    $line1 = clean_input($data['address_line1'] ?? '');
    $line2 = clean_input($data['address_line2'] ?? '');
    $landmark = clean_input($data['landmark'] ?? '');
    $city = clean_input($data['city'] ?? '');
    $state = clean_input($data['state'] ?? '');
    $postalCode = clean_input($data['postal_code'] ?? '');
    $country = clean_input($data['country'] ?? 'India');
    $isDefaultShipping = !empty($data['is_default_shipping']) ? 1 : 0;
    $isDefaultBilling = !empty($data['is_default_billing']) ? 1 : 0;

    // Field Validations
    if (empty($fullName) || strlen($fullName) < 3) {
        return ['success' => false, 'message' => 'Please enter the recipient full name.'];
    }
    $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
    if (empty($cleanPhone) || strlen($cleanPhone) < 10) {
        return ['success' => false, 'message' => 'Please enter a valid 10-digit mobile number for delivery coordination.'];
    }
    if (empty($line1) || strlen($line1) < 5) {
        return ['success' => false, 'message' => 'Please enter your street address / apartment details.'];
    }
    if (empty($city)) {
        return ['success' => false, 'message' => 'Please specify your city.'];
    }
    if (empty($state)) {
        return ['success' => false, 'message' => 'Please specify your state or province.'];
    }
    if (empty($postalCode) || strlen(preg_replace('/\s+/', '', $postalCode)) < 5) {
        return ['success' => false, 'message' => 'Please enter a valid postal / PIN code.'];
    }

    if (!in_array($type, ['home', 'work', 'billing', 'shipping', 'other'])) {
        $type = 'home';
    }

    // Check if this is the user's first address
    $countStmt = db()->prepare("SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?");
    $countStmt->execute([$customerId]);
    $addressCount = (int)$countStmt->fetchColumn();

    if ($addressCount === 0) {
        $isDefaultShipping = 1;
        $isDefaultBilling = 1;
    }

    // Default flags management
    if ($isDefaultShipping) {
        db()->prepare("UPDATE customer_addresses SET is_default_shipping = 0 WHERE customer_id = ?")->execute([$customerId]);
    }
    if ($isDefaultBilling) {
        db()->prepare("UPDATE customer_addresses SET is_default_billing = 0 WHERE customer_id = ?")->execute([$customerId]);
    }

    if ($addressId) {
        // IDOR Check: Ensure address belongs to this customer
        $existing = get_customer_address($addressId, $customerId);
        if (!$existing) {
            return ['success' => false, 'message' => 'Address not found or unauthorized access.'];
        }

        $stmt = db()->prepare("
            UPDATE customer_addresses 
            SET full_name = ?, phone = ?, address_type = ?, address_line1 = ?, address_line2 = ?, 
                landmark = ?, city = ?, state = ?, postal_code = ?, country = ?, 
                is_default_shipping = ?, is_default_billing = ?, updated_at = NOW() 
            WHERE id = ? AND customer_id = ?
        ");
        $stmt->execute([
            $fullName, $cleanPhone, $type, $line1, $line2 ?: null,
            $landmark ?: null, $city, $state, $postalCode, $country,
            $isDefaultShipping, $isDefaultBilling, $addressId, $customerId
        ]);

        return ['success' => true, 'message' => 'Address updated successfully.', 'id' => $addressId];
    } else {
        $stmt = db()->prepare("
            INSERT INTO customer_addresses (
                customer_id, full_name, phone, address_type, address_line1, address_line2,
                landmark, city, state, postal_code, country, is_default_shipping, is_default_billing, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([
            $customerId, $fullName, $cleanPhone, $type, $line1, $line2 ?: null,
            $landmark ?: null, $city, $state, $postalCode, $country, $isDefaultShipping, $isDefaultBilling
        ]);
        $newId = (int)db()->lastInsertId();

        return ['success' => true, 'message' => 'New address saved to your address book.', 'id' => $newId];
    }
}

/**
 * Delete customer address with strict IDOR ownership check
 */
function delete_customer_address(int $addressId, int $customerId): bool {
    $existing = get_customer_address($addressId, $customerId);
    if (!$existing) {
        return false;
    }

    $stmt = db()->prepare("DELETE FROM customer_addresses WHERE id = ? AND customer_id = ?");
    $stmt->execute([$addressId, $customerId]);

    // If default shipping was deleted, reassign default to the next address if available
    if (!empty($existing['is_default_shipping'])) {
        db()->prepare("
            UPDATE customer_addresses 
            SET is_default_shipping = 1 
            WHERE customer_id = ? 
            ORDER BY id DESC LIMIT 1
        ")->execute([$customerId]);
    }

    return true;
}

/**
 * Set default address for shipping or billing
 */
function set_default_address(int $addressId, int $customerId, string $type = 'shipping'): bool {
    $existing = get_customer_address($addressId, $customerId);
    if (!$existing) {
        return false;
    }

    if ($type === 'shipping') {
        db()->prepare("UPDATE customer_addresses SET is_default_shipping = 0 WHERE customer_id = ?")->execute([$customerId]);
        db()->prepare("UPDATE customer_addresses SET is_default_shipping = 1 WHERE id = ? AND customer_id = ?")->execute([$addressId, $customerId]);
    } else {
        db()->prepare("UPDATE customer_addresses SET is_default_billing = 0 WHERE customer_id = ?")->execute([$customerId]);
        db()->prepare("UPDATE customer_addresses SET is_default_billing = 1 WHERE id = ? AND customer_id = ?")->execute([$addressId, $customerId]);
    }

    return true;
}

/* =====================================================================
   7. CUSTOMER ORDERS QUERY ENGINE (IDOR PROTECTED)
   ===================================================================== */

/**
 * Get all orders placed by the customer
 */
function get_customer_orders(int $customerId): array {
    $stmt = db()->prepare("
        SELECT 
            o.*,
            (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS item_count,
            (SELECT product_name FROM order_items WHERE order_id = o.id ORDER BY id ASC LIMIT 1) AS sample_product_name,
            (SELECT p.slug FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = o.id LIMIT 1) AS sample_product_slug,
            (SELECT pi.image_url FROM order_items oi JOIN product_images pi ON oi.product_id = pi.product_id WHERE oi.order_id = o.id ORDER BY pi.is_primary DESC LIMIT 1) AS sample_image_url
        FROM orders o
        WHERE o.customer_id = ?
        ORDER BY o.placed_at DESC
    ");
    $stmt->execute([$customerId]);
    return $stmt->fetchAll();
}

/**
 * Get detailed single order record with IDOR ownership validation
 */
function get_customer_order_details(mixed $orderIdentifier, int $customerId): ?array {
    $isNumeric = is_numeric($orderIdentifier);

    if ($isNumeric) {
        $stmt = db()->prepare("SELECT * FROM orders WHERE id = ? AND customer_id = ? LIMIT 1");
        $stmt->execute([(int)$orderIdentifier, $customerId]);
    } else {
        $stmt = db()->prepare("SELECT * FROM orders WHERE order_number = ? AND customer_id = ? LIMIT 1");
        $stmt->execute([clean_input($orderIdentifier), $customerId]);
    }

    $order = $stmt->fetch();
    if (!$order) {
        return null;
    }

    // Fetch line items
    $itemStmt = db()->prepare("
        SELECT 
            oi.*,
            p.slug AS product_slug,
            (SELECT image_url FROM product_images WHERE product_id = oi.product_id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image_url
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $itemStmt->execute([$order['id']]);
    $order['items'] = $itemStmt->fetchAll();

    return $order;
}

/* =====================================================================
   8. ADMINISTRATOR AUTHENTICATION
   ===================================================================== */

/**
 * Check if admin is logged in
 */
function is_admin_logged_in(): bool {
    return !empty($_SESSION['admin_user_id']);
}

/**
 * Get current authenticated administrator
 */
function current_admin(): ?array {
    static $admin = null;

    if (!is_admin_logged_in()) {
        return null;
    }

    if ($admin === null) {
        try {
            $stmt = db()->prepare("
                SELECT id, username, email, full_name, role, status
                FROM admin_users
                WHERE id = ? AND status = 'active'
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['admin_user_id']]);
            $admin = $stmt->fetch() ?: null;

            if (!$admin) {
                logout_admin();
            }
        } catch (Exception $e) {
            $admin = null;
        }
    }

    return $admin;
}

/**
 * Guard admin route requiring admin authentication and optional role permission
 */
function require_admin(string|array|null $allowedRoles = null): void {
    if (!is_admin_logged_in()) {
        $_SESSION['admin_intended_url'] = $_SERVER['REQUEST_URI'] ?? (ADMIN_URL . '/index.php');
        set_flash('error', 'Administrator authorization required.');
        redirect(ADMIN_URL . '/login.php');
    }

    if ($allowedRoles !== null && !has_admin_role($allowedRoles)) {
        set_flash('error', 'Unauthorized: Insufficient administrative privileges.');
        redirect(ADMIN_URL . '/index.php');
    }
}

/**
 * Log in admin user and regenerate session ID
 */
function login_admin(array $adminUser): void {
    if (!headers_sent()) {
        session_regenerate_id(true);
    }

    $_SESSION['admin_user_id'] = (int)$adminUser['id'];
    $_SESSION['admin_username'] = $adminUser['username'];
    $_SESSION['admin_name'] = $adminUser['full_name'];
    $_SESSION['admin_role'] = $adminUser['role'];
    $_SESSION['admin_logged_in_time'] = time();

    try {
        $stmt = db()->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = ?");
        $stmt->execute([$adminUser['id']]);
    } catch (Exception $e) {
        // Silently continue
    }
}

/**
 * Log out admin securely
 */
function logout_admin(): void {
    $adminId = $_SESSION['admin_user_id'] ?? null;
    if ($adminId) {
        log_admin_activity($adminId, 'admin_logout', 'admin_users', $adminId, 'Admin session ended');
    }

    unset($_SESSION['admin_user_id'], $_SESSION['admin_username'], $_SESSION['admin_name'], $_SESSION['admin_role'], $_SESSION['admin_logged_in_time']);
    if (!headers_sent()) {
        session_regenerate_id(true);
    }
}

/**
 * Check if admin login attempts are throttled
 */
function is_admin_login_throttled(string $identifier): bool {
    $key = strtolower(trim($identifier));
    if (empty($_SESSION['admin_login_throttle'][$key])) {
        return false;
    }

    $throttle = $_SESSION['admin_login_throttle'][$key];
    if (!empty($throttle['lockout_until'])) {
        if (time() < $throttle['lockout_until']) {
            return true;
        }
        unset($_SESSION['admin_login_throttle'][$key]);
        return false;
    }

    return false;
}

/**
 * Record a failed admin login attempt (15-min lockout after 5 consecutive failures)
 */
function record_failed_admin_login_attempt(string $identifier): void {
    $key = strtolower(trim($identifier));
    if (!isset($_SESSION['admin_login_throttle'])) {
        $_SESSION['admin_login_throttle'] = [];
    }

    if (!isset($_SESSION['admin_login_throttle'][$key])) {
        $_SESSION['admin_login_throttle'][$key] = [
            'count' => 0,
            'last_attempt' => time(),
            'lockout_until' => 0
        ];
    }

    $_SESSION['admin_login_throttle'][$key]['count']++;
    $_SESSION['admin_login_throttle'][$key]['last_attempt'] = time();

    if ($_SESSION['admin_login_throttle'][$key]['count'] >= 5) {
        $_SESSION['admin_login_throttle'][$key]['lockout_until'] = time() + 900;
    }
}

/**
 * Reset admin login attempts upon successful login
 */
function reset_admin_login_attempts(string $identifier): void {
    $key = strtolower(trim($identifier));
    if (isset($_SESSION['admin_login_throttle'][$key])) {
        unset($_SESSION['admin_login_throttle'][$key]);
    }
}

/**
 * Authenticate administrator with username/email and password
 */
function authenticate_admin(string $usernameOrEmail, string $password): array {
    $cleanId = clean_input($usernameOrEmail);

    if (is_admin_login_throttled($cleanId)) {
        return [
            'success' => false,
            'message' => 'Too many failed administrator login attempts. Access is locked for 15 minutes.'
        ];
    }

    try {
        $stmt = db()->prepare("
            SELECT * FROM admin_users 
            WHERE (username = ? OR email = ?) AND status = 'active' 
            LIMIT 1
        ");
        $stmt->execute([$cleanId, $cleanId]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password_hash'])) {
            record_failed_admin_login_attempt($cleanId);
            return [
                'success' => false,
                'message' => 'Invalid administrator credentials.'
            ];
        }

        reset_admin_login_attempts($cleanId);
        login_admin($admin);
        log_admin_activity((int)$admin['id'], 'admin_login', 'admin_users', (int)$admin['id'], 'Admin logged in successfully');

        return [
            'success' => true,
            'admin' => $admin
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'An unexpected administrative authentication error occurred.'
        ];
    }
}

/**
 * Log administrative activity to database audit trail
 */
function log_admin_activity(?int $adminId, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): bool {
    try {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = db()->prepare("
            INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$adminId, $action, $entityType, $entityId, $details, $ip]);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Verify admin has required role permission
 */
function has_admin_role(string|array $allowedRoles): bool {
    $admin = current_admin();
    if (!$admin) {
        return false;
    }
    $role = $_SESSION['admin_role'] ?? $admin['role'];
    if ($role === 'super_admin') {
        return true;
    }
    if (is_array($allowedRoles)) {
        return in_array($role, $allowedRoles, true);
    }
    return $role === $allowedRoles;
}
