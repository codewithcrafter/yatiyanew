<?php
/**
 * YATIYA COUTURE - Customer Order History
 * Lists all orders placed by the authenticated patron with status indicators
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

$orders = get_customer_orders($customerId);
$totalOrders = count($orders);

$pageTitle = 'My Haute Couture Orders';
$pageDescription = 'Track and view your luxury haute couture order history at Yatiya Couture.';
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
                <li><a href="<?= BASE_URL ?>/account.php">My Account</a></li>
                <li class="separator">/</li>
                <li aria-current="page">My Orders</li>
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
                    <li class="account-nav-item active">
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
                        <h1 class="account-view-title">My Orders</h1>
                        <p class="account-view-subtitle">Review your acquired luxury creations and track delivery dispatches.</p>
                    </div>
                </div>

                <?php if ($totalOrders === 0): ?>
                    <div class="dashboard-card empty-history-box">
                        <div class="empty-history-icon">🛍️</div>
                        <h2 class="empty-history-title">No Orders Placed Yet</h2>
                        <p class="empty-history-text">
                            You have not placed any orders yet. Discover our curated Autumn/Winter 2026 runway collections and bespoke handloom edits.
                        </p>
                        <a href="<?= BASE_URL ?>/shop.php" class="btn btn-gold btn-sm">Explore All Collections</a>
                    </div>
                <?php else: ?>
                    <div class="dashboard-card" style="padding: 0; overflow: hidden;">
                        <div class="orders-table-wrap">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order Number</th>
                                        <th>Date Placed</th>
                                        <th>Primary Creation</th>
                                        <th>Total Items</th>
                                        <th>Grand Total</th>
                                        <th>Order Status</th>
                                        <th>Payment</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $ord): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= BASE_URL ?>/order-details.php?id=<?= $ord['id'] ?>" class="order-num-link">
                                                    <?= e($ord['order_number']) ?>
                                                </a>
                                            </td>
                                            <td><?= date('d M Y', strtotime($ord['placed_at'])) ?></td>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <?php if (!empty($ord['sample_image_url'])): ?>
                                                        <img src="<?= e($ord['sample_image_url']) ?>" alt="" style="width: 36px; height: 48px; object-fit: cover; border-radius: 2px;">
                                                    <?php endif; ?>
                                                    <span><?= e($ord['sample_product_name'] ?? 'Bespoke Creation') ?></span>
                                                </div>
                                            </td>
                                            <td><?= (int)$ord['item_count'] ?> <?= (int)$ord['item_count'] === 1 ? 'item' : 'items' ?></td>
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
                                                <a href="<?= BASE_URL ?>/order-details.php?id=<?= $ord['id'] ?>" class="btn btn-outline btn-sm" style="padding: 0.3rem 0.75rem; font-size: 0.75rem;">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

        </div>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

