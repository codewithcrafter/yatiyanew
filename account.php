<?php
/**
 * YATIYA COUTURE - Customer Account Portal (Dashboard Overview)
 * Central patron hub displaying order metrics, wishlist, addresses, and profile details
 * Pure Core PHP & Vanilla JavaScript
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart-functions.php';

// Authentication Guard
require_login();

$user = current_user();
$customerId = (int)$user['id'];

// Metrics Queries
$orders = get_customer_orders($customerId);
$totalOrders = count($orders);
$recentOrders = array_slice($orders, 0, 3);

$wishlistItems = get_wishlist_products();
$totalWishlist = count($wishlistItems);

$addresses = get_customer_addresses($customerId);
$totalAddresses = count($addresses);

// Locate defaults
$defaultShipping = null;
$defaultBilling = null;
foreach ($addresses as $addr) {
    if (!empty($addr['is_default_shipping']) && !$defaultShipping) $defaultShipping = $addr;
    if (!empty($addr['is_default_billing']) && !$defaultBilling) $defaultBilling = $addr;
}
if (!$defaultShipping && !empty($addresses)) $defaultShipping = $addresses[0];
if (!$defaultBilling && !empty($addresses)) $defaultBilling = $addresses[0];

$pageTitle = 'My Atelier Account';
$pageDescription = 'Customer account overview and luxury order tracking at Yatiya Couture.';
$activePage = 'account';

require_once __DIR__ . '/includes/header.php';
?>

<!-- Account Portal Stylesheet -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/account.css">

<main class="account-page-wrapper">
    <div class="container">
        
        <!-- Flash Alert Messages -->
        <?= render_flash() ?>

        <!-- Breadcrumbs -->
        <nav class="account-breadcrumb" aria-label="Breadcrumb">
            <ol>
                <li><a href="<?= BASE_URL ?>/index.php">Maison Home</a></li>
                <li class="separator">/</li>
                <li aria-current="page">My Atelier Account</li>
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
                    <li class="account-nav-item active">
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
                            My Orders (<?= $totalOrders ?>)
                        </a>
                    </li>
                    <li class="account-nav-item">
                        <a href="<?= BASE_URL ?>/wishlist.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                            Private Wishlist (<?= $totalWishlist ?>)
                        </a>
                    </li>
                    <li class="account-nav-item">
                        <a href="<?= BASE_URL ?>/addresses.php">
                            <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                            Address Book (<?= $totalAddresses ?>)
                        </a>
                    </li>
                    <li class="account-nav-item">
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
                        <h1 class="account-view-title">Welcome, <?= e($user['first_name']) ?></h1>
                        <p class="account-view-subtitle">Manage your bespoke orders, privileged addresses, and atelier security settings.</p>
                    </div>
                </div>

                <!-- 3 KPI Metric Cards -->
                <div class="account-kpi-grid">
                    <a href="<?= BASE_URL ?>/orders.php" class="kpi-card">
                        <div class="kpi-icon-wrap">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                <line x1="3" y1="6" x2="21" y2="6"></line>
                                <path d="M16 10a4 4 0 0 1-8 0"></path>
                            </svg>
                        </div>
                        <div class="kpi-data">
                            <span class="kpi-number"><?= $totalOrders ?></span>
                            <span class="kpi-label">Orders Placed</span>
                        </div>
                    </a>

                    <a href="<?= BASE_URL ?>/wishlist.php" class="kpi-card">
                        <div class="kpi-icon-wrap">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                        </div>
                        <div class="kpi-data">
                            <span class="kpi-number"><?= $totalWishlist ?></span>
                            <span class="kpi-label">Saved Creations</span>
                        </div>
                    </a>

                    <a href="<?= BASE_URL ?>/addresses.php" class="kpi-card">
                        <div class="kpi-icon-wrap">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                                <circle cx="12" cy="10" r="3"></circle>
                            </svg>
                        </div>
                        <div class="kpi-data">
                            <span class="kpi-number"><?= $totalAddresses ?></span>
                            <span class="kpi-label">Saved Addresses</span>
                        </div>
                    </a>
                </div>

                <!-- Recent Orders Section -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2 class="card-heading">Recent Haute Couture Orders</h2>
                        <?php if ($totalOrders > 0): ?>
                            <a href="<?= BASE_URL ?>/orders.php" class="card-header-link">View All Orders &rarr;</a>
                        <?php endif; ?>
                    </div>

                    <?php if ($totalOrders === 0): ?>
                        <div class="empty-history-box">
                            <div class="empty-history-icon">🛍️</div>
                            <h3 class="empty-history-title">No Orders Placed Yet</h3>
                            <p class="empty-history-text">
                                You have not acquired any bespoke creations yet. Discover our Autumn/Winter 2026 runway edit and experience white-glove luxury delivery.
                            </p>
                            <a href="<?= BASE_URL ?>/shop.php" class="btn btn-gold btn-sm">Explore Collections</a>
                        </div>
                    <?php else: ?>
                        <div class="orders-table-wrap">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Date</th>
                                        <th>Creations</th>
                                        <th>Total</th>
                                        <th>Status</th>
                                        <th>Payment</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentOrders as $ord): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= BASE_URL ?>/order-details.php?id=<?= $ord['id'] ?>" class="order-num-link">
                                                    <?= e($ord['order_number']) ?>
                                                </a>
                                            </td>
                                            <td><?= date('d M Y', strtotime($ord['placed_at'])) ?></td>
                                            <td><?= (int)$ord['item_count'] ?> item(s)</td>
                                            <td><strong><?= format_price($ord['grand_total']) ?></strong></td>
                                            <td>
                                                <span class="status-pill status-<?= e($ord['order_status']) ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $ord['order_status'])) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="status-pill payment-<?= e($ord['payment_status']) ?>">
                                                    <?= ucfirst($ord['payment_status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?= BASE_URL ?>/order-details.php?id=<?= $ord['id'] ?>" class="card-header-link">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Personal Profile & Address Summary Row -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2 class="card-heading">Patron Profile Dossier</h2>
                        <a href="<?= BASE_URL ?>/profile.php" class="card-header-link">Edit Profile &rarr;</a>
                    </div>

                    <div class="profile-details-grid">
                        <div class="detail-item">
                            <span class="detail-label">Client Name</span>
                            <span class="detail-value"><?= e($user['first_name'] . ' ' . $user['last_name']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email Address</span>
                            <span class="detail-value"><?= e($user['email']) ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Mobile Number</span>
                            <span class="detail-value"><?= e($user['phone'] ?: 'Not provided') ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Patron Member Since</span>
                            <span class="detail-value"><?= date('F Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                </div>

                <!-- Default Addresses Row -->
                <div class="dashboard-card">
                    <div class="dashboard-card-header">
                        <h2 class="card-heading">Default Delivery Destination</h2>
                        <a href="<?= BASE_URL ?>/addresses.php" class="card-header-link">Manage Address Book &rarr;</a>
                    </div>

                    <?php if (!$defaultShipping): ?>
                        <p style="color:var(--text-secondary); font-size:0.875rem; margin:0 0 1rem;">
                            You have not saved any addresses in your address book yet.
                        </p>
                        <a href="<?= BASE_URL ?>/addresses.php" class="btn btn-outline btn-sm">Add New Address</a>
                    <?php else: ?>
                        <div class="address-cards-grid">
                            <div class="address-box is-default">
                                <div class="address-badges-row">
                                    <span class="address-badge badge-default">Default Shipping</span>
                                    <span class="address-badge badge-type"><?= ucfirst(e($defaultShipping['address_type'])) ?></span>
                                </div>
                                <h3 class="address-recipient-name"><?= e($defaultShipping['full_name']) ?></h3>
                                <p class="address-text">
                                    <?= e($defaultShipping['address_line1']) ?><br>
                                    <?php if (!empty($defaultShipping['address_line2'])): ?>
                                        <?= e($defaultShipping['address_line2']) ?><br>
                                    <?php endif; ?>
                                    <?php if (!empty($defaultShipping['landmark'])): ?>
                                        Landmark: <?= e($defaultShipping['landmark']) ?><br>
                                    <?php endif; ?>
                                    <?= e($defaultShipping['city']) ?>, <?= e($defaultShipping['state']) ?> — <?= e($defaultShipping['postal_code']) ?><br>
                                    <?= e($defaultShipping['country']) ?>
                                </p>
                                <span class="address-phone">📞 <?= e($defaultShipping['phone']) ?></span>
                            </div>

                            <?php if ($defaultBilling && $defaultBilling['id'] !== $defaultShipping['id']): ?>
                                <div class="address-box is-default">
                                    <div class="address-badges-row">
                                        <span class="address-badge badge-default">Default Billing</span>
                                        <span class="address-badge badge-type"><?= ucfirst(e($defaultBilling['address_type'])) ?></span>
                                    </div>
                                    <h3 class="address-recipient-name"><?= e($defaultBilling['full_name']) ?></h3>
                                    <p class="address-text">
                                        <?= e($defaultBilling['address_line1']) ?><br>
                                        <?= e($defaultBilling['city']) ?>, <?= e($defaultBilling['state']) ?> — <?= e($defaultBilling['postal_code']) ?><br>
                                        <?= e($defaultBilling['country']) ?>
                                    </p>
                                    <span class="address-phone">📞 <?= e($defaultBilling['phone']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

            </div>

        </div>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

