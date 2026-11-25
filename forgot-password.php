<?php
/**
 * YATIYA COUTURE - Forgot Password Portal
 * Initiates secure password recovery with cryptographically random tokens
 * Pure Core PHP & Vanilla JavaScript
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// If already logged in, redirect to account dashboard
if (is_logged_in()) {
    redirect(BASE_URL . '/account.php');
}

$devNotice = null;

if (is_post()) {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($token)) {
        set_flash('error', 'Security token expired or invalid. Please try again.');
        redirect(BASE_URL . '/forgot-password.php');
    }

    $email = clean_input($_POST['email'] ?? '');
    $res = generate_password_reset_token($email);

    set_flash('success', $res['message']);

    // Capture dev simulation preview if available
    if (!empty($_SESSION['dev_reset_preview'])) {
        $devNotice = $_SESSION['dev_reset_preview'];
    }
}

$pageTitle = 'Forgot Password';
$pageDescription = 'Request a password reset link for your Yatiya Couture luxury account.';
$activePage = 'account';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Authentication Stylesheet -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/auth.css">

<main class="auth-page-wrapper">
    <div class="auth-card-container">
        
        <!-- Flash Alert Messages -->
        <?= render_flash() ?>

        <?php if (!empty($devNotice)): ?>
            <!-- Development Mode Token Delivery Simulator -->
            <div class="dev-token-notice">
                <strong>🛠️ Development Environment Notice:</strong>
                <span>Since real SMTP email dispatch is not configured in this local environment, your cryptographically secure reset link is simulated below:</span>
                <div>
                    <a href="<?= e($devNotice['reset_link']) ?>" class="dev-token-link">
                        Click here to Reset Password &rarr;
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="auth-card">
            <div class="auth-header">
                <span class="auth-brand-eyebrow">Security Concierge</span>
                <h1 class="auth-title">Reset Your Password</h1>
                <p class="auth-subtitle">
                    Enter the email address associated with your atelier account. We will dispatch a secure recovery link.
                </p>
            </div>

            <form action="<?= BASE_URL ?>/forgot-password.php" method="POST" class="auth-form" id="forgotForm" novalidate>
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="forgotEmail" class="form-label">Email Address <span class="required">*</span></label>
                    <input 
                        type="email" 
                        name="email" 
                        id="forgotEmail" 
                        class="form-control" 
                        placeholder="e.g. client@domain.com" 
                        required 
                        autocomplete="email"
                        autofocus
                    >
                </div>

                <button type="submit" class="btn btn-gold btn-block btn-auth-submit">
                    Send Password Reset Link
                </button>
            </form>

            <div class="auth-footer">
                Remembered your credentials?
                <a href="<?= BASE_URL ?>/login.php" class="auth-switch-link">Return to Sign In</a>
            </div>
        </div>

    </div>
</main>

<script src="<?= ASSETS_URL ?>/js/auth.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

