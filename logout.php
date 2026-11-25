<?php
/**
 * YATIYA COUTURE - Secure Customer Logout Controller
 * Handles POST-based session destruction and cookie revocation
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Verify POST method
if (is_post()) {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    if (!verify_csrf_token($token)) {
        set_flash('error', 'Security token expired. Please sign out again.');
        redirect(BASE_URL . '/account.php');
    }

    logout_customer();
    set_flash('success', 'You have been securely signed out from your atelier account.');
    redirect(BASE_URL . '/login.php');
} else {
    // If accessed directly via GET, show confirmation or redirect to account/home
    if (is_logged_in()) {
        $pageTitle = 'Sign Out Confirmation';
        require_once __DIR__ . '/includes/header.php';
        ?>
        <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/auth.css">
        <main class="auth-page-wrapper">
            <div class="auth-card-container">
                <div class="auth-card" style="text-align: center;">
                    <span class="auth-brand-eyebrow">Patron Session</span>
                    <h1 class="auth-title">Confirm Sign Out</h1>
                    <p class="auth-subtitle" style="margin-bottom: 2rem;">
                        Are you sure you wish to end your active luxury session?
                    </p>
                    <form action="<?= BASE_URL ?>/logout.php" method="POST">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-gold btn-block btn-auth-submit">
                            Yes, Sign Out Securely
                        </button>
                    </form>
                    <div style="margin-top: 1.5rem;">
                        <a href="<?= BASE_URL ?>/account.php" class="auth-switch-link">&larr; Return to Dashboard</a>
                    </div>
                </div>
            </div>
        </main>
        <?php
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }

    redirect(BASE_URL . '/login.php');
}

