<?php
/**
 * YATIYA COUTURE - Customer Registration Portal
 * Pure Core PHP & Vanilla JavaScript adhering to luxury architectural standards
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

// If already logged in, redirect to intended destination or account dashboard
if (is_logged_in()) {
    $redirectUrl = get_safe_redirect_url($redirectParam ?: ($_SESSION['intended_url'] ?? null), '/account.php');
    redirect($redirectUrl);
}

$errors = [];
$formData = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => ''
];

// Handle Registration Form Submission
if (is_post()) {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($token)) {
        set_flash('error', 'Security token expired or invalid. Please try submitting again.');
        redirect(BASE_URL . '/register.php' . ($redirectParam ? '?redirect=' . urlencode($redirectParam) : ''));
    }

    $formData = [
        'first_name' => clean_input($_POST['first_name'] ?? ''),
        'last_name' => clean_input($_POST['last_name'] ?? ''),
        'email' => clean_input($_POST['email'] ?? ''),
        'phone' => clean_input($_POST['phone'] ?? ''),
        'password' => (string)($_POST['password'] ?? ''),
        'confirm_password' => (string)($_POST['confirm_password'] ?? ''),
        'terms_accepted' => !empty($_POST['terms_accepted']) ? 1 : 0
    ];

    $result = register_customer($formData);

    if ($result['success']) {
        set_flash('success', $result['message']);
        $redirectUrl = get_safe_redirect_url($redirectParam ?: ($_SESSION['intended_url'] ?? null), '/account.php');
        unset($_SESSION['intended_url']);
        redirect($redirectUrl);
    } else {
        set_flash('error', $result['message']);
    }
}

$pageTitle = 'Create Atelier Account';
$pageDescription = 'Register for a privileged bespoke client account at Yatiya Couture.';
$activePage = 'account';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Authentication Stylesheet -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/auth.css">

<main class="auth-page-wrapper">
    <div class="auth-card-container auth-card-wide">
        
        <!-- Flash Alert Messages -->
        <?= render_flash() ?>

        <div class="auth-card">
            <!-- Dual Option Navigation Tabs -->
            <div class="auth-tabs">
                <a href="<?= BASE_URL ?>/login.php<?= !empty($redirectParam) ? '?redirect=' . urlencode($redirectParam) : '' ?>" class="auth-tab">
                    Sign In
                </a>
                <a href="<?= BASE_URL ?>/register.php<?= !empty($redirectParam) ? '?redirect=' . urlencode($redirectParam) : '' ?>" class="auth-tab active">
                    Create Account
                </a>
            </div>

            <?php if (!empty($redirectParam) && strpos($redirectParam, 'checkout') !== false): ?>
                <div class="auth-checkout-notice">
                    <span style="font-size: 16px;">✦</span>
                    <div>
                        <strong>Patron Authentication Required:</strong> Create your atelier account below to finalize your acquisition with white-glove delivery.
                    </div>
                </div>
            <?php endif; ?>

            <div class="auth-header">
                <span class="auth-brand-eyebrow">Haute Couture Privé</span>
                <h1 class="auth-title">Create Your Atelier Account</h1>
                <p class="auth-subtitle">
                    Join Yatiya Couture to experience private wardrobe curations, bespoke bridal consultations, and white-glove doorstep delivery.
                </p>
            </div>

            <form action="<?= BASE_URL ?>/register.php" method="POST" class="auth-form" id="registerForm" novalidate>
                <?= csrf_field() ?>
                <?php if (!empty($redirectParam)): ?>
                    <input type="hidden" name="redirect" value="<?= e($redirectParam) ?>">
                <?php endif; ?>

                <!-- Name Row (2 Columns) -->
                <div class="form-row-2col">
                    <div class="form-group">
                        <label for="regFirstName" class="form-label">First Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            name="first_name" 
                            id="regFirstName" 
                            class="form-control" 
                            value="<?= e($formData['first_name']) ?>" 
                            placeholder="e.g. Radhika" 
                            required 
                            autocomplete="given-name"
                        >
                    </div>

                    <div class="form-group">
                        <label for="regLastName" class="form-label">Last Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            name="last_name" 
                            id="regLastName" 
                            class="form-control" 
                            value="<?= e($formData['last_name']) ?>" 
                            placeholder="e.g. Kapoor" 
                            required 
                            autocomplete="family-name"
                        >
                    </div>
                </div>

                <!-- Email Address -->
                <div class="form-group">
                    <label for="regEmail" class="form-label">Email Address <span class="required">*</span></label>
                    <input 
                        type="email" 
                        name="email" 
                        id="regEmail" 
                        class="form-control" 
                        value="<?= e($formData['email']) ?>" 
                        placeholder="e.g. radhika.kapoor@domain.com" 
                        required 
                        autocomplete="email"
                    >
                    <span class="input-hint">Your order dossiers and luxury invoices will be dispatched to this address.</span>
                </div>

                <!-- Mobile Phone -->
                <div class="form-group">
                    <label for="regPhone" class="form-label">Mobile Number (Optional)</label>
                    <input 
                        type="tel" 
                        name="phone" 
                        id="regPhone" 
                        class="form-control" 
                        value="<?= e($formData['phone']) ?>" 
                        placeholder="+91 98765 43210" 
                        autocomplete="tel"
                    >
                    <span class="input-hint">For white-glove delivery coordination and fitting updates.</span>
                </div>

                <!-- Password Row (2 Columns) -->
                <div class="form-row-2col">
                    <div class="form-group">
                        <label for="regPassword" class="form-label">Password <span class="required">*</span></label>
                        <div class="form-input-wrap">
                            <input 
                                type="password" 
                                name="password" 
                                id="regPassword" 
                                class="form-control" 
                                placeholder="Min. 8 characters" 
                                required 
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle-btn" data-target="regPassword" aria-label="Toggle password visibility">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="regConfirmPassword" class="form-label">Confirm Password <span class="required">*</span></label>
                        <div class="form-input-wrap">
                            <input 
                                type="password" 
                                name="confirm_password" 
                                id="regConfirmPassword" 
                                class="form-control" 
                                placeholder="Re-enter password" 
                                required 
                                autocomplete="new-password"
                            >
                            <button type="button" class="password-toggle-btn" data-target="regConfirmPassword" aria-label="Toggle password visibility">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Password Strength Meter -->
                <div class="password-strength-meter">
                    <div class="strength-bar-track">
                        <div class="strength-bar-fill" id="strengthFill"></div>
                    </div>
                    <span class="strength-label" id="strengthLabel">At least 8 characters with letters & numbers</span>
                </div>

                <!-- Terms and Privacy Acceptance -->
                <label class="auth-checkbox-group">
                    <input type="checkbox" name="terms_accepted" value="1" required>
                    <span>
                        I accept the Yatiya Couture <a href="#">Terms of Privilege Service</a> and <a href="#">Privacy Charter</a>.
                    </span>
                </label>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-gold btn-block btn-auth-submit">
                    Complete Atelier Registration
                </button>
            </form>

            <div class="auth-footer">
                Already hold a luxury atelier account?
                <a href="<?= BASE_URL ?>/login.php" class="auth-switch-link">Sign In to Your Account</a>
            </div>
        </div>

    </div>
</main>

<!-- Authentication Scripts -->
<script src="<?= ASSETS_URL ?>/js/auth.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

