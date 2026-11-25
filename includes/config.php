<?php
/**
 * YATIYA COUTURE - Global Configuration
 * Brand Vision: "ELEVATE YOUR EVERYDAY STYLE"
 */

// Prevent direct script execution if accessed outside root context where appropriate
defined('YATIYA_APP') or define('YATIYA_APP', true);

// Set default timezone
date_default_timezone_set('Asia/Kolkata');

// Error reporting settings (display errors in local dev, hide in production)
$isLocalEnv = (php_sapi_name() === 'cli')
    || (isset($_SERVER['HTTP_HOST']) && (str_contains($_SERVER['HTTP_HOST'], 'localhost') || str_contains($_SERVER['HTTP_HOST'], '127.0.0.1')));
if ($isLocalEnv) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
error_reporting(E_ALL);

// Session Security Configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    // If running under HTTPS, enable secure cookie
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}

// Database Credentials (Hostinger Live Database)
define('DB_HOST', 'srv1328.hstgr.io');
define('DB_PORT', '3306');
define('DB_NAME', 'u555726154_yatiya');
define('DB_USER', 'u555726154_yatiyac');
define('DB_PASS', 'Yatiya@2026#');
define('DB_CHARSET', 'utf8mb4');

// Brand & Store Constants
define('SITE_NAME', 'YATIYA COUTURE');
define('SITE_TAGLINE', 'Elevate Your Everyday Style');
define('SITE_EMAIL', 'concierge@yatiyacouture.com');
define('SITE_PHONE', '+91 (0) 11 4987 6543');
define('CURRENCY_SYMBOL', '₹');
define('CURRENCY_CODE', 'INR');
define('FREE_SHIPPING_THRESHOLD', 2999.00);
define('DEFAULT_SHIPPING_FEE', 199.00);
define('DEFAULT_TAX_RATE', 0.12); // 12% GST

// Payment Gateway Configuration (Razorpay & COD)
define('PAYMENT_MODE_TEST', true);
define('RAZORPAY_KEY_ID', 'rzp_test_YatiyaCouture2026');
define('RAZORPAY_KEY_SECRET', 'sk_test_YatiyaLuxurySecretKey2026');
define('RAZORPAY_WEBHOOK_SECRET', 'whsec_YatiyaCoutureWebhookKey2026');
define('COD_ENABLED', true);
define('COD_MAX_AMOUNT', 50000.00); // COD capped at ₹50,000 for luxury purchases

// Filesystem Paths
define('PATH_ROOT', dirname(__DIR__));
define('PATH_INCLUDES', PATH_ROOT . DIRECTORY_SEPARATOR . 'includes');
define('PATH_UPLOADS', PATH_ROOT . DIRECTORY_SEPARATOR . 'uploads');

// Dynamic Base URL calculation (Supports HTTP, HTTPS, Reverse Proxies & Cloudflare)
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
$protocol = $isHttps ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
$rawDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$scriptDir = trim(str_replace('\\', '/', $rawDir), '/.');
$baseUrl = $protocol . $host . ($scriptDir !== '' ? '/' . $scriptDir : '');

// If scriptDir is in a subdirectory like /admin or /actions, determine base root
if (basename($baseUrl) === 'admin' || basename($baseUrl) === 'actions') {
    $baseUrl = dirname($baseUrl);
}
if (!defined('BASE_URL')) {
    define('BASE_URL', rtrim($baseUrl, '/'));
}
if (!defined('ADMIN_URL')) {
    define('ADMIN_URL', BASE_URL . '/admin');
}
if (!defined('ASSETS_URL')) {
    define('ASSETS_URL', BASE_URL . '/assets');
}
if (!defined('UPLOADS_URL')) {
    define('UPLOADS_URL', BASE_URL . '/uploads');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', BASE_URL);
}
