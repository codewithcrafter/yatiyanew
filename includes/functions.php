<?php
/**
 * YATIYA COUTURE - Global Helper Functions
 * Clean, safe, reusable utilities strictly using Core PHP
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Escape HTML output for XSS prevention
 */
function e(?string $string): string {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Clean & trim standard user input
 */
function clean_input($data): string {
    if (is_array($data)) {
        return '';
    }
    return trim((string)$data);
}

/**
 * Generate or retrieve CSRF token
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF token input field
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Validate a CSRF token
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Set a session flash message
 */
function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'error', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Retrieve and clear session flash messages
 * If $type is specified, returns the string message(s) for that type, or null if none.
 * If $type is null, returns all flash message arrays.
 */
function get_flash(?string $type = null) {
    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        return $type !== null ? null : [];
    }

    if ($type === null) {
        $messages = $_SESSION['flash_messages'];
        unset($_SESSION['flash_messages']);
        return $messages;
    }

    $found = [];
    $remaining = [];
    foreach ($_SESSION['flash_messages'] as $msg) {
        if (is_array($msg) && isset($msg['type']) && $msg['type'] === $type) {
            $found[] = (string)($msg['message'] ?? '');
        } else {
            $remaining[] = $msg;
        }
    }

    if (empty($remaining)) {
        unset($_SESSION['flash_messages']);
    } else {
        $_SESSION['flash_messages'] = $remaining;
    }

    if (empty($found)) {
        return null;
    }

    return implode(' ', $found);
}

/**
 * Render HTML flash messages
 */
function render_flash(): string {
    $messages = get_flash();
    if (empty($messages)) {
        return '';
    }

    $html = '<div class="flash-messages-container" id="flashMessages">';
    foreach ($messages as $msg) {
        $type = e($msg['type']);
        $text = e($msg['message']);
        $icon = ($type === 'success') ? '✓' : (($type === 'error') ? '✕' : 'ℹ');
        
        $html .= '<div class="alert alert-' . $type . '" role="alert">';
        $html .= '<span class="alert-icon">' . $icon . '</span>';
        $html .= '<span class="alert-text">' . $text . '</span>';
        $html .= '<button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Dismiss">&times;</button>';
        $html .= '</div>';
    }
    $html .= '</div>';

    return $html;
}

/**
 * Format currency price
 */
function format_price(float|string $amount, bool $showDecimals = true): string {
    $num = (float)$amount;
    $decimals = $showDecimals ? 2 : 0;
    return CURRENCY_SYMBOL . ' ' . number_format($num, $decimals, '.', ',');
}

/**
 * Calculate discount percentage
 */
function calculate_discount_percentage(float $original, float $selling): int {
    if ($original <= 0 || $selling >= $original) {
        return 0;
    }
    return (int)round((($original - $selling) / $original) * 100);
}

/**
 * Validate and sanitize an internal redirect URL to prevent Open Redirect attacks.
 * Only permits relative paths within the application domain.
 */
function get_safe_redirect_url(?string $url, string $default = '/account.php'): string {
    $fallback = BASE_URL . '/' . ltrim($default, '/');
    if (empty($url)) {
        return $fallback;
    }

    $url = trim($url);

    // If absolute URL provided, verify it begins with BASE_URL
    if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) {
        if (strpos($url, BASE_URL . '/') === 0 || $url === BASE_URL) {
            return $url;
        }
        return $fallback;
    }

    // Reject protocol-relative URLs (//evil.com), javascript:, data:, and invalid schemes
    if (strpos($url, '//') === 0 || preg_match('/^[a-z]+:/i', $url)) {
        return $fallback;
    }

    // Strip leading slashes to prevent root traversal or scheme spoofing
    $parsedPath = parse_url($url, PHP_URL_PATH) ?? '';
    $cleanPath = ltrim($parsedPath, '/');
    $query = parse_url($url, PHP_URL_QUERY);

    if (empty($cleanPath)) {
        return $fallback;
    }

    $target = BASE_URL . '/' . $cleanPath;
    if (!empty($query)) {
        $target .= '?' . $query;
    }

    return $target;
}

/**
 * Safe HTTP Redirect
 */
function redirect(string $url): void {
    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }
    echo '<script>window.location.href = ' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . e($url) . '"></noscript>';
    exit;
}

/**
 * Return JSON response and terminate
 */
function json_response(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

/**
 * Check if current HTTP request is POST
 */
function is_post(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

/**
 * Check if request is AJAX
 */
function is_ajax(): bool {
    return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
}

/**
 * Generate URL-friendly slug
 */
function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

/**
 * Alias for slugify matching modern e-commerce naming
 */
function generate_slug(string $text): string {
    return slugify($text);
}

/**
 * Retrieve cached store settings
 */
function get_setting(string $key, string $default = '', bool $fresh = false): string {
    static $settingsCache = null;

    if ($settingsCache === null || $fresh) {
        $settingsCache = [];
        try {
            $stmt = db()->query("SELECT setting_key, setting_value FROM store_settings");
            while ($row = $stmt->fetch()) {
                $settingsCache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Fail gracefully if DB query fails during early bootstrap
        }
    }

    return $settingsCache[$key] ?? $default;
}

/**
 * Fetch active categories for navigation and mega menu
 */
function get_active_categories(int $limit = 20): array {
    static $cachedCategories = null;

    if ($cachedCategories === null) {
        try {
            $stmt = db()->prepare("
                SELECT id, parent_id, name, slug, description, image_url, display_order, is_featured
                FROM categories
                WHERE is_active = 1
                ORDER BY display_order ASC, name ASC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $cachedCategories = $stmt->fetchAll();
        } catch (Exception $e) {
            $cachedCategories = [];
        }
    }

    return $cachedCategories;
}

/**
 * Get active cart items count (supports guest session and logged-in customer)
 */
function get_cart_count(): int {
    try {
        $customerId = $_SESSION['customer_id'] ?? null;
        $sessionId = session_id();

        if ($customerId) {
            $stmt = db()->prepare("
                SELECT COALESCE(SUM(ci.quantity), 0) AS total_count
                FROM carts c
                INNER JOIN cart_items ci ON c.id = ci.cart_id
                WHERE c.customer_id = ? AND c.status = 'active' AND ci.saved_for_later = 0
            ");
            $stmt->execute([$customerId]);
        } else {
            $stmt = db()->prepare("
                SELECT COALESCE(SUM(ci.quantity), 0) AS total_count
                FROM carts c
                INNER JOIN cart_items ci ON c.id = ci.cart_id
                WHERE c.session_id = ? AND c.status = 'active' AND ci.saved_for_later = 0
            ");
            $stmt->execute([$sessionId]);
        }
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Get wishlist items count (supports both authenticated customer and guest session)
 */
function get_wishlist_count(): int {
    $customerId = $_SESSION['customer_id'] ?? null;
    if ($customerId) {
        try {
            $stmt = db()->prepare("SELECT COUNT(*) FROM wishlists WHERE customer_id = ?");
            $stmt->execute([$customerId]);
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            return 0;
        }
    }

    if (!empty($_SESSION['guest_wishlist']) && is_array($_SESSION['guest_wishlist'])) {
        return count($_SESSION['guest_wishlist']);
    }

    return 0;
}

// Load luxury shopping cart, wishlist and coupon engine
require_once __DIR__ . '/cart-functions.php';
require_once __DIR__ . '/admin-functions.php';



