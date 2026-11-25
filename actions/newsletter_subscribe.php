<?php
/**
 * YATIYA COUTURE - Newsletter Subscription Action Handler
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_post()) {
    redirect(BASE_URL . '/index.php');
}

$token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($token)) {
    set_flash('error', 'Security validation failed. Please try again.');
    redirect(BASE_URL . '/index.php');
}

$email = strtolower(clean_input($_POST['email'] ?? ''));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash('error', 'Please provide a valid email address.');
    redirect(BASE_URL . '/index.php');
}

try {
    $stmt = db()->prepare("
        INSERT INTO newsletter_subscribers (email, status, source, subscribed_at)
        VALUES (?, 'subscribed', 'footer', NOW())
        ON DUPLICATE KEY UPDATE status = 'subscribed', unsubscribed_at = NULL
    ");
    $stmt->execute([$email]);

    set_flash('success', 'Thank you for joining the Yatiya Salon. You will now receive private couture releases.');
} catch (Exception $e) {
    error_log('[Newsletter Error] ' . $e->getMessage());
    set_flash('error', 'Unable to complete your salon registration at this time. Please try again shortly.');
}

redirect(BASE_URL . '/index.php');

