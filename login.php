<?php
/**
 * YATIYA COUTURE - Customer Authentication Portal
 * Handles secure patron sign-in with login attempt throttling and CSRF protection
 * Pure Core PHP & Vanilla JavaScript
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart-functions.php';

$redirectParam = clean_input($_GET['redirect'] ?? ($_POST['redirect'] ?? ''));
if (!empty($redirectParam)) {
    $_SESSION['intended_url'] = get_safe_redirect_url($redirectParam);
}

// Check remember-me cookie if not logged in
check_remember_me();

// If already logged in, redirect to intended destination or account dashboard
if (is_logged_in()) {
    $redirectUrl = get_safe_redirect_url($redirectParam ?: ($_SESSION['intended_url'] ?? null), '/account.php');
    redirect($redirectUrl);
}

$emailValue = '';

// Handle Login Form Submission
if (is_post()) {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($token)) {
        set_flash('error', 'Security token expired or invalid. Please try signing in again.');
        redirect(BASE_URL . '/login.php' . ($redirectParam ? '?redirect=' . urlencode($redirectParam) : ''));
    }

    $email = clean_input($_POST['email'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $rememberMe = !empty($_POST['remember_me']);

    $emailValue = $email;

    $result = authenticate_customer($email, $password, $rememberMe);

    if ($result['success']) {
        set_flash('success', $result['message']);
        $redirectUrl = get_safe_redirect_url($redirectParam ?: ($_SESSION['intended_url'] ?? null), '/account.php');
        unset($_SESSION['intended_url']);
        redirect($redirectUrl);
    } else {
        set_flash('error', $result['message']);
    }
}

$pageTitle = 'Sign In to Your Atelier Account';
$pageDescription = 'Sign in to your Yatiya Couture luxury client account to view orders, wishlist, and bespoke curations.';
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
            <!-- Dual Option Navigation Tabs -->
            <div class="auth-tabs">
                <a href="<?= BASE_URL ?>/login.php<?= !empty($redirectParam) ? '?redirect=' . urlencode($redirectParam) : '' ?>" class="auth-tab active">
                    Sign In
                </a>
                <a href="<?= BASE_URL ?>/register.php<?= !empty($redirectParam) ? '?redirect=' . urlencode($redirectParam) : '' ?>" class="auth-tab">
                    Create Account
                </a>
            </div>

            <?php if (!empty($redirectParam) && strpos($redirectParam, 'checkout') !== false): ?>
                <div class="auth-checkout-notice">
                    <span style="font-size: 16px;">✦</span>
                    <div>
                        <strong>Patron Authentication Required:</strong> Please sign in with your credentials or create an account to proceed with your luxury checkout.
                    </div>
                </div>
            <?php endif; ?>

            <div class="auth-header">
                <span class="auth-brand-eyebrow">Patron Concierge</span>
                <h1 class="auth-title">Sign In to Atelier</h1>
                <p class="auth-subtitle">
                    Enter your email credentials to access your private orders, saved wishlist creations, and bespoke privileges.
                </p>
            </div>

            <form action="<?= BASE_URL ?>/login.php" method="POST" class="auth-form" id="loginForm" novalidate>
                <?= csrf_field() ?>
                <?php if (!empty($redirectParam)): ?>
                    <input type="hidden" name="redirect" value="<?= e($redirectParam) ?>">
                <?php endif; ?>

                <!-- Email Input -->
                <div class="form-group">
                    <label for="loginEmail" class="form-label">Email Address <span class="required">*</span></label>
                    <input 
                        type="email" 
                        name="email" 
                        id="loginEmail" 
                        class="form-control" 
                        value="<?= e($emailValue) ?>" 
                        placeholder="e.g. client@domain.com" 
                        required 
                        autocomplete="email"
                        autofocus
                    >
                </div>

                <!-- Password Input -->
                <div class="form-group">
                    <label for="loginPassword" class="form-label">Password <span class="required">*</span></label>
                    <div class="form-input-wrap">
                        <input 
                            type="password" 
                            name="password" 
                            id="loginPassword" 
                            class="form-control" 
                            placeholder="Enter your account password" 
                            required 
                            autocomplete="current-password"
                        >
                        <button type="button" class="password-toggle-btn" data-target="loginPassword" aria-label="Toggle password visibility">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember Me & Forgot Password Row -->
                <div class="form-actions-row">
                    <label class="auth-checkbox-group">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Remember for 30 days</span>
                    </label>

                    <a href="<?= BASE_URL ?>/forgot-password.php" class="forgot-password-link">
                        Forgot Password?
                    </a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-gold btn-block btn-auth-submit">
                    Sign In to Atelier Account
                </button>
            </form>

            <div class="auth-footer">
                New to Yatiya Couture?
                <a href="<?= BASE_URL ?>/register.php" class="auth-switch-link">Create an Account</a>
            </div>
        </div>

    </div>
</main>

<!-- Authentication Scripts -->
<script src="<?= ASSETS_URL ?>/js/auth.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

