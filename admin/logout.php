<?php
/**
 * YATIYA COUTURE - Luxury Administrator Sign-Out Controller
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Strict POST method and CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        logout_admin();
        set_flash('success', 'You have been securely signed out of Atelier Operations.');
    } else {
        set_flash('error', 'Invalid security token for sign-out request.');
    }
}

redirect(ADMIN_URL . '/login.php');

