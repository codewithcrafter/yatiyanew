<?php
/**
 * YATIYA COUTURE - Patron Profile & Security Management
 * Allows updating personal information and changing password with current password verification
 * Pure Core PHP & Vanilla JavaScript
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Authentication Guard
require_login();

$user = current_user();
$customerId = (int)$user['id'];

// Handle POST actions
if (is_post()) {
    $token = $_POST['csrf_token'] ?? null;
    if (!verify_csrf_token($token)) {
        set_flash('error', 'Security token expired. Please try again.');
        redirect(BASE_URL . '/profile.php');
    }

    $action = clean_input($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $result = update_customer_profile($customerId, $_POST);
        set_flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(BASE_URL . '/profile.php');
    } elseif ($action === 'change_password') {
        $currPass = (string)($_POST['current_password'] ?? '');
        $newPass = (string)($_POST['new_password'] ?? '');
        $confirmPass = (string)($_POST['confirm_password'] ?? '');

        $result = change_customer_password($customerId, $currPass, $newPass, $confirmPass);
        set_flash($result['success'] ? 'success' : 'error', $result['message']);
        redirect(BASE_URL . '/profile.php');
    }
}

// Refresh user record
$user = current_user();

$pageTitle = 'Profile & Security';
$pageDescription = 'Manage your personal atelier details and security credentials at Yatiya Couture.';
$activePage = 'account';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Account Portal Stylesheet -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/account.css">
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/auth.css">

<main class="account-page-wrapper">
    <div class="container">
        
        <!-- Flash Alert Messages -->
        <?= render_flash() ?>

        <!-- Breadcrumbs -->
        <nav class="account-breadcrumb" aria-label="Breadcrumb">
            <ol>
                <li><a href="<?= BASE_URL ?>/index.php">Maison Home</a></li>
                <li class="separator">/</li>
                <li><a href="<?= BASE_URL ?>/account.php">My Account</a></li>
                <li class="separator">/</li>
                <li aria-current="page">Profile & Security</li>
            </ol>
        </nav>

        <div class="account-layout-grid">
            
            <!-- Left Navigation Sidebar -->
            <aside class="account-sidebar-card">
                <div class="account-user-badge">
                    <div class="user-avatar-circle">
                        <?= strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)) ?>
                    </div>
                    <h2 class="user-fullname"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></h2>
                    <p class="user-email-text"><?= e($user['email']) ?></p>
                    <span class="user-tier-pill">Privileged Patron</span>
                </div>

                <ul class="account-nav-list">
                    <li class="account-nav-item">
                        <a href="<?= BASE_URL ?>/account.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <rect x="3" y="3" width="7" height="7"></rect>
                                <rect x="14" y="3" width="7" height="7"></rect>
                                <rect x="14" y="14" width="7" height="7"></rect>
                                <rect x="3" y="14" width="7" height="7"></rect>
                            </svg>
                            Dashboard Overview
                        </a>
                    </li>
                    <li class="account-nav-item">
                        <a href="<?= BASE_URL ?>/orders.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <path d="M16 10a4 4 0 0 1-8 0"></path>
                            </svg>
                            My Orders
                        </a>
                    </li>
                    <li class="account-nav-item">
                        <a href="<?= BASE_URL ?>/wishlist.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                            Private Wishlist
                        </a>
                    </li>
                    <li class="account-nav-item">
                        <a href="<?= BASE_URL ?>/addresses.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            Address Book
                        </a>
                    </li>
                    <li class="account-nav-item active">
                        <a href="<?= BASE_URL ?>/profile.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            Profile & Security
                        </a>
                    </li>
                    <li class="account-nav-item signout-item">
                        <form action="<?= BASE_URL ?>/logout.php" method="POST" style="margin:0;">
                            <?= csrf_field() ?>
                            <button type="submit" onclick="return confirm('Sign out of your atelier account?');">
                                <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                    <polyline points="16 17 21 12 16 7"></polyline>
                                    <line x1="21" y1="12" x2="9" y2="12"></line>
                                </svg>
                                Secure Sign Out
                            </button>
                        </form>
                    </li>
                </ul>
            </aside>

            <!-- Main Content Area -->
            <div class="account-content-area">
                
                <div class="account-view-header">
                    <div>
                        <h1 class="account-view-title">Personal Profile & Security</h1>
                        <p class="account-view-subtitle">Update your personal client profile and maintain strict authentication security.</p>
                    </div>
                </div>

                <!-- Form 1: Personal Details -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2 class="card-heading">Atelier Personal Details</h2>
                    </div>

                    <form action="<?= BASE_URL ?>/profile.php" method="POST" class="auth-form" style="max-width: 650px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_profile">

                        <div class="form-row-2col">
                            <div class="form-group">
                                <label for="profFirstName" class="form-label">First Name <span class="required">*</span></label>
                                <input 
                                    type="text" 
                                    name="first_name" 
                                    id="profFirstName" 
                                    class="form-control" 
                                    value="<?= e($user['first_name']) ?>" 
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="profLastName" class="form-label">Last Name <span class="required">*</span></label>
                                <input 
                                    type="text" 
                                    name="last_name" 
                                    id="profLastName" 
                                    class="form-control" 
                                    value="<?= e($user['last_name']) ?>" 
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-row-2col">
                            <div class="form-group">
                                <label for="profEmail" class="form-label">Email Address <span class="required">*</span></label>
                                <input 
                                    type="email" 
                                    name="email" 
                                    id="profEmail" 
                                    class="form-control" 
                                    value="<?= e($user['email']) ?>" 
                                    required
                                >
                            </div>

                            <div class="form-group">
                                <label for="profPhone" class="form-label">Mobile Number</label>
                                <input 
                                    type="tel" 
                                    name="phone" 
                                    id="profPhone" 
                                    class="form-control" 
                                    value="<?= e($user['phone']) ?>" 
                                    placeholder="+91 98765 43210"
                                >
                            </div>
                        </div>

                        <div>
                            <button type="submit" class="btn btn-gold btn-sm">
                                Save Profile Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Form 2: Sensitive Password Change -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2 class="card-heading">Change Password</h2>
                    </div>

                    <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0 0 1.5rem;">
                        For your security, you must provide your active account password to establish a new password.
                    </p>

                    <form action="<?= BASE_URL ?>/profile.php" method="POST" class="auth-form" style="max-width: 650px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="change_password">

                        <!-- Current Password Input -->
                        <div class="form-group">
                            <label for="currentPassword" class="form-label">Current Password <span class="required">*</span></label>
                            <div class="form-input-wrap">
                                <input 
                                    type="password" 
                                    name="current_password" 
                                    id="currentPassword" 
                                    class="form-control" 
                                    placeholder="Enter active password" 
                                    required
                                >
                                <button type="button" class="password-toggle-btn" data-target="currentPassword" aria-label="Toggle password visibility">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <div class="form-row-2col">
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
                                <label for="confirmPassword" class="form-label">Confirm New Password <span class="required">*</span></label>
                                <div class="form-input-wrap">
                                    <input 
                                        type="password" 
                                        name="confirm_password" 
                                        id="confirmPassword" 
                                        class="form-control" 
                                        placeholder="Re-enter new password" 
                                        required
                                    >
                                    <button type="button" class="password-toggle-btn" data-target="confirmPassword" aria-label="Toggle password visibility">
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

                        <div>
                            <button type="submit" class="btn btn-gold btn-sm">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>

            </div>

        </div>

    </div>
</main>

<script src="<?= ASSETS_URL ?>/js/auth.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

