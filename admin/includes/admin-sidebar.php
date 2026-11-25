<?php
/**
 * YATIYA COUTURE - Luxury Admin Management Console Sidebar
 * Complete, Enterprise-Grade Navigation Structure
 */

require_once __DIR__ . '/../../includes/config.php';
?>
<aside class="admin-sidebar" id="adminSidebar">
    <!-- Brand Wordmark -->
    <div class="sidebar-brand">
        <a href="<?= ADMIN_URL ?>/index.php" style="text-decoration: none; color: inherit;">
            <div class="sidebar-brand-title">YATIYA</div>
            <div class="sidebar-brand-badge">Atelier Operations</div>
        </a>
    </div>

    <!-- Navigation Menu with Collapsible Groups -->
    <nav class="sidebar-nav">
        <!-- GROUP 1: DASHBOARD -->
        <div class="nav-section-title">DASHBOARD</div>
        
        <a href="<?= ADMIN_URL ?>/index.php" class="nav-item <?= ($activeMenu === 'dashboard') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>Executive Overview</span>
            </div>
        </a>

        <!-- GROUP 2: STORE MANAGEMENT -->
        <div class="nav-section-title">STORE MANAGEMENT</div>

        <a href="<?= ADMIN_URL ?>/homepage-sections.php" class="nav-item <?= ($activeMenu === 'homepage-sections') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                    <path d="M3 9h18"></path>
                    <path d="M9 21V9"></path>
                </svg>
                <span>Homepage CMS</span>
            </div>
            <span class="nav-badge gold">CMS</span>
        </a>

        <a href="<?= ADMIN_URL ?>/navigation.php" class="nav-item <?= ($activeMenu === 'navigation') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <line x1="4" y1="6" x2="20" y2="6"></line>
                    <line x1="4" y1="12" x2="20" y2="12"></line>
                    <line x1="4" y1="18" x2="14" y2="18"></line>
                </svg>
                <span>Navigation & Mega Menu</span>
            </div>
        </a>

        <a href="<?= ADMIN_URL ?>/products.php" class="nav-item <?= ($activeMenu === 'products') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <span>Creations & Products</span>
            </div>
        </a>

        <a href="<?= ADMIN_URL ?>/categories.php" class="nav-item <?= ($activeMenu === 'categories') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <line x1="8" y1="6" x2="21" y2="6"></line>
                    <line x1="8" y1="12" x2="21" y2="12"></line>
                    <line x1="8" y1="18" x2="21" y2="18"></line>
                    <line x1="3" y1="6" x2="3.01" y2="6"></line>
                    <line x1="3" y1="12" x2="3.01" y2="12"></line>
                    <line x1="3" y1="18" x2="3.01" y2="18"></line>
                </svg>
                <span>Categories & Salons</span>
            </div>
        </a>

        <a href="<?= ADMIN_URL ?>/collections.php" class="nav-item <?= ($activeMenu === 'collections') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                    <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                </svg>
                <span>Curated Collections</span>
            </div>
        </a>

        <a href="<?= ADMIN_URL ?>/brands.php" class="nav-item <?= ($activeMenu === 'brands') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                <span>Brands / Atelier Lines</span>
            </div>
        </a>

        <a href="<?= ADMIN_URL ?>/reviews.php" class="nav-item <?= ($activeMenu === 'reviews') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <span>Product Reviews</span>
            </div>
            <?php if (!empty($pendingReviewsBadge)): ?>
                <span class="nav-badge"><?= $pendingReviewsBadge ?></span>
            <?php endif; ?>
        </a>

        <!-- GROUP 3: SALES & CUSTOMERS -->
        <div class="nav-section-title">SALES & CUSTOMERS</div>

        <a href="<?= ADMIN_URL ?>/orders.php" class="nav-item <?= ($activeMenu === 'orders') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <span>Orders Dossier</span>
            </div>
            <?php if (!empty($pendingOrdersBadge)): ?>
                <span class="nav-badge"><?= $pendingOrdersBadge ?></span>
            <?php endif; ?>
        </a>

        <a href="<?= ADMIN_URL ?>/customers.php" class="nav-item <?= ($activeMenu === 'customers') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Patrons Registry</span>
            </div>
        </a>

        <a href="<?= ADMIN_URL ?>/coupons.php" class="nav-item <?= ($activeMenu === 'coupons') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="8.5" y1="15.5" x2="15.5" y2="8.5"></line>
                    <circle cx="10" cy="10" r="1.5" fill="currentColor"></circle>
                    <circle cx="14" cy="14" r="1.5" fill="currentColor"></circle>
                </svg>
                <span>Privilege Coupons</span>
            </div>
        </a>

        <a href="<?= ADMIN_URL ?>/returns.php" class="nav-item <?= ($activeMenu === 'returns') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <polyline points="1 4 1 10 7 10"></polyline>
                    <path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"></path>
                </svg>
                <span>Returns & Exchanges</span>
            </div>
            <?php if (!empty($pendingReturnsBadge)): ?>
                <span class="nav-badge danger"><?= $pendingReturnsBadge ?></span>
            <?php endif; ?>
        </a>

        <!-- GROUP 4: INVENTORY -->
        <div class="nav-section-title">INVENTORY</div>

        <a href="<?= ADMIN_URL ?>/inventory.php" class="nav-item <?= ($activeMenu === 'inventory') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <polyline points="22 12 16 12 14 15 10 15 8 12 2 12"></polyline>
                    <path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"></path>
                </svg>
                <span>Stock Overview & Ledger</span>
            </div>
            <?php if (!empty($lowStockBadge)): ?>
                <span class="nav-badge danger"><?= $lowStockBadge ?></span>
            <?php endif; ?>
        </a>

        <!-- GROUP 5: CONTENT MANAGEMENT -->
        <div class="nav-section-title">CONTENT MANAGEMENT</div>

        <a href="<?= ADMIN_URL ?>/banners.php" class="nav-item <?= ($activeMenu === 'banners') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
                <span>Homepage Banners</span>
            </div>
        </a>

        <a href="<?= ADMIN_URL ?>/media.php" class="nav-item <?= ($activeMenu === 'media') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                    <polyline points="21 15 16 10 5 21"></polyline>
                </svg>
                <span>Media Library</span>
            </div>
        </a>

        <a href="<?= ADMIN_URL ?>/subscribers.php" class="nav-item <?= ($activeMenu === 'subscribers') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
                <span>Newsletter Subscribers</span>
            </div>
        </a>

        <!-- GROUP 6: CONFIGURATION -->
        <div class="nav-section-title">CONFIGURATION</div>

        <a href="<?= ADMIN_URL ?>/settings.php" class="nav-item <?= ($activeMenu === 'settings') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                <span>Store & SEO Settings</span>
            </div>
        </a>

        <a href="<?= ADMIN_URL ?>/users.php" class="nav-item <?= ($activeMenu === 'users') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <polyline points="17 11 19 13 23 9"></polyline>
                </svg>
                <span>Admin Users & Roles</span>
            </div>
        </a>

        <a href="<?= ADMIN_URL ?>/activity-logs.php" class="nav-item <?= ($activeMenu === 'activity') ? 'active' : '' ?>">
            <div class="nav-item-content">
                <svg class="nav-item-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Staff Activity Logs</span>
            </div>
        </a>
    </nav>

    <!-- Sidebar Footer / Current Admin Details -->
    <div class="sidebar-footer">
        <div class="sidebar-admin-user">
            <div class="admin-avatar">
                <?= strtoupper(substr($currentAdmin['full_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div class="admin-user-info">
                <div class="admin-user-name"><?= e($currentAdmin['full_name'] ?? 'Staff') ?></div>
                <div class="admin-user-role"><?= e(str_replace('_', ' ', $currentAdmin['role'] ?? 'admin')) ?></div>
            </div>
        </div>
    </div>
</aside>
