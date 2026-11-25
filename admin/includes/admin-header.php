<?php
/**
 * YATIYA COUTURE - Luxury Admin Management Console Header
 * Unified, Enterprise-Grade Operations Layout
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/admin-functions.php';

// Enforce admin authorization guard
require_admin();

$currentAdmin = current_admin();
$adminPageTitle = $adminPageTitle ?? 'Executive Dashboard';
$adminPageSubtitle = $adminPageSubtitle ?? '';
$activeMenu = $activeMenu ?? 'dashboard';

// Dynamic alert badges for navigation
try {
    $pendingOrdersBadge = (int)db()->query("SELECT COUNT(*) FROM orders WHERE order_status IN ('placed', 'confirmed', 'processing')")->fetchColumn();
    $lowStockBadge = (int)db()->query("SELECT COUNT(DISTINCT id) FROM products WHERE stock_quantity <= low_stock_threshold AND status = 'active'")->fetchColumn();
    $pendingReviewsBadge = (int)db()->query("SELECT COUNT(*) FROM product_reviews WHERE status = 'pending'")->fetchColumn();
    $pendingReturnsBadge = (int)db()->query("SELECT COUNT(*) FROM return_requests WHERE status IN ('requested', 'approved', 'pickup_scheduled')")->fetchColumn();
} catch (Exception $e) {
    $pendingOrdersBadge = 0;
    $lowStockBadge = 0;
    $pendingReviewsBadge = 0;
    $pendingReturnsBadge = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($adminPageTitle) ?> | <?= SITE_NAME ?> Atelier Operations</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= csrf_token() ?>">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= ADMIN_URL ?>/assets/css/admin.css">
</head>
<body>

<div class="admin-wrapper">
    <!-- Mobile Sidebar Backdrop -->
    <div class="modal-backdrop" id="sidebarBackdrop"></div>

    <!-- Master Admin Sidebar -->
    <?php require __DIR__ . '/admin-sidebar.php'; ?>

    <!-- Main Content Stage -->
    <div class="admin-main">
        <!-- Top Navigation Bar -->
        <header class="admin-header">
            <div class="header-left">
                <button type="button" class="mobile-menu-btn" id="mobileMenuToggle" aria-label="Toggle navigation drawer">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
                <div class="header-breadcrumbs">
                    <a href="<?= ADMIN_URL ?>/index.php" class="crumb-link">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                            <polyline points="9 22 9 12 15 12 15 22"></polyline>
                        </svg>
                        <span>Console</span>
                    </a>
                    <span class="crumb-separator">/</span>
                    <span class="crumb-current"><?= e($adminPageTitle) ?></span>
                </div>
            </div>

            <div class="header-right">
                <!-- Preview Mode Link -->
                <a href="<?= BASE_URL ?>/index.php?preview=1" target="_blank" class="btn-store-preview" title="Preview boutique with draft changes">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span>Store Preview</span>
                </a>

                <!-- Live Store Link -->
                <a href="<?= BASE_URL ?>/index.php" target="_blank" class="btn-live-store" title="Open live public boutique">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                        <polyline points="15 3 21 3 21 9"></polyline>
                        <line x1="10" y1="14" x2="21" y2="3"></line>
                    </svg>
                    <span>Live Store</span>
                </a>

                <div class="header-divider"></div>

                <!-- Admin User Profile Pill -->
                <div class="admin-profile-pill">
                    <div class="admin-avatar">
                        <?= strtoupper(substr($currentAdmin['full_name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div class="admin-info-inline">
                        <span class="name"><?= e($currentAdmin['full_name'] ?? 'Staff') ?></span>
                        <span class="role"><?= e(str_replace('_', ' ', $currentAdmin['role'] ?? 'admin')) ?></span>
                    </div>
                </div>

                <!-- Sign Out Action -->
                <form action="<?= ADMIN_URL ?>/logout.php" method="POST" style="margin:0;">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <button type="submit" class="btn-logout" title="Sign out of Atelier Console">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </header>

        <!-- Flash Alert Messages & Main Content Area -->
        <div class="admin-content">
            <?php
            $flashSuccess = get_flash('success');
            $flashError = get_flash('error');
            $flashWarning = get_flash('warning');
            $flashInfo = get_flash('info');
            ?>
            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success alert-dismissible" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span><?= e(is_array($flashSuccess) ? implode(' ', $flashSuccess) : $flashSuccess) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
                </div>
            <?php endif; ?>
            <?php if (!empty($flashError)): ?>
                <div class="alert alert-danger alert-dismissible" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?= e(is_array($flashError) ? implode(' ', $flashError) : $flashError) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
                </div>
            <?php endif; ?>
            <?php if (!empty($flashWarning)): ?>
                <div class="alert alert-warning alert-dismissible" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                    <span><?= e(is_array($flashWarning) ? implode(' ', $flashWarning) : $flashWarning) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
                </div>
            <?php endif; ?>
            <?php if (!empty($flashInfo)): ?>
                <div class="alert alert-info alert-dismissible" role="alert">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <span><?= e(is_array($flashInfo) ? implode(' ', $flashInfo) : $flashInfo) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.remove()" aria-label="Close">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Page Header Component -->
            <div class="admin-page-header">
                <div>
                    <h1 class="admin-page-heading"><?= e($adminPageTitle) ?></h1>
                    <?php if (!empty($adminPageSubtitle)): ?>
                        <p class="admin-page-desc"><?= e($adminPageSubtitle) ?></p>
                    <?php endif; ?>
                </div>
                <?php if (!empty($headerActions)): ?>
                    <div class="admin-header-actions">
                        <?= $headerActions ?>
                    </div>
                <?php endif; ?>
            </div>
