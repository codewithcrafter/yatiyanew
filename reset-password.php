<?php
/**
 * YATIYA COUTURE - Reset Password Execution Portal
 * Validates single-use hash and updates credentials securely
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

$token = clean_input($_GET['token'] ?? $_POST['token'] ?? '');
$customer = verify_password_reset_token($token);

// Handle Form Submission
if (is_post()) {
    $csrf = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($csrf)) {
        set_flash('error', 'Security token expired. Please try again.');
        redirect(BASE_URL . '/reset-password.php?token=' . urlencode($token));
    }

    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    $res = reset_customer_password($token, $newPassword, $confirmPassword);

    if ($res['success']) {
        set_flash('success', $res['message']);
        redirect(BASE_URL . '/login.php');
    } else {
        set_flash('error', $res['message']);
        // Re-verify token
        $customer = verify_password_reset_token($token);
    }
}

$pageTitle = 'Choose a New Password';
$pageDescription = 'Set a new secure password for your Yatiya Couture luxury account.';
$activePage = 'account';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Authentication Stylesheet -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/auth.css">

<main class="auth-page-wrapper">
    <div class="auth-card-container">
        
        <!-- Flash Alert Messages -->
        <?= render_flash() ?>

        <div class="auth-card">
            <?php if (!$customer): ?>
                <!-- Invalid or Expired Token State -->
                <div class="auth-header">
                    <span class="auth-brand-eyebrow">Security Notice</span>
                    <h1 class="auth-title">Link Invalid or Expired</h1>
                    <p class="auth-subtitle">
                        This password reset token is invalid, has expired, or has already been redeemed for your protection.
                    </p>
                </div>

                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="<?= BASE_URL ?>/forgot-password.php" class="btn btn-gold btn-block btn-auth-submit">
                        Request a New Reset Link
                    </a>
                    <div style="margin-top: 1.25rem;">
                        <a href="<?= BASE_URL ?>/login.php" class="auth-switch-link">Return to Sign In</a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Valid Token Form -->
                <div class="auth-header">
                    <span class="auth-brand-eyebrow">Account Credentials</span>
                    <h1 class="auth-title">Choose New Password</h1>
                    <p class="auth-subtitle">
                        Create a strong password for your atelier account (<strong><?= e($customer['email']) ?></strong>).
                    </p>
                </div>

                <form action="<?= BASE_URL ?>/reset-password.php" method="POST" class="auth-form" id="resetPasswordForm" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <!-- New Password Input -->
                    <div class="form-group">
                        <label for="newPassword" class="form-label">New Password <span class="required">*</span></label>
                        <div class="form-input-wrap">
                            <input 
                                type="password" 
                                name="new_password" 
                                id="newPassword" 
                                class="form-control" 
                                placeholder="Min. 8 characters" 
                                required 
                                autocomplete="new-password"
                                autofocus
                            >
                            <button type="button" class="password-toggle-btn" data-target="newPassword" aria-label="Toggle password visibility">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Confirm New Password Input -->
                    <div class="form-group">
                        <label for="confirmNewPassword" class="form-label">Confirm New Password <span class="required">*</span></label>
                        <div class="form-input-wrap">
                            <input 
                                type="password" 
                                name="confirm_password" 
                                id="confirmNewPassword" 
                                class="form-control" 
                                placeholder="Re-enter new password" 
                                required 
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle-btn" data-target="confirmNewPassword" aria-label="Toggle password visibility">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Password Strength Meter -->
                    <div class="password-strength-meter">
                        <div class="strength-bar-track">
                            <div class="strength-bar-fill" id="strengthFill"></div>
                        </div>
                        <span class="strength-label" id="strengthLabel">At least 8 characters with letters & numbers</span>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-gold btn-block btn-auth-submit">
                        Update Password & Sign In
                    </button>
                </form>

                <div class="auth-footer">
                    Recalled your old credentials?
                    <a href="<?= BASE_URL ?>/login.php" class="auth-switch-link">Sign In</a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<script src="<?= ASSETS_URL ?>/js/auth.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

