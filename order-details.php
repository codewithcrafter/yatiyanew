<?php
/**
 * YATIYA COUTURE - Order Dossier Details
 * Displays verified order receipt, line items, and delivery destinations with IDOR protection
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

$orderIdentifier = clean_input($_GET['id'] ?? $_GET['order'] ?? '');
$order = !empty($orderIdentifier) ? get_customer_order_details($orderIdentifier, $customerId) : null;

$pageTitle = $order ? ('Order ' . $order['order_number']) : 'Order Dossier';
$pageDescription = 'Review the detailed invoice and atelier shipping specifics for your order.';
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
                <li><a href="<?= BASE_URL ?>/orders.php">My Orders</a></li>
                <li class="separator">/</li>
                <li aria-current="page"><?= $order ? e($order['order_number']) : 'Not Found' ?></li>
            </ol>
        </nav>

        <?php if (!$order): ?>
            <!-- Order Not Found or Unauthorized Access -->
            <div class="dashboard-card empty-history-box">
                <div class="empty-history-icon">⚠️</div>
                <h1 class="empty-history-title">Order Dossier Not Found</h1>
                <p class="empty-history-text">
                    The requested order does not exist or you do not have privileged authorization to view this dossier.
                </p>
                <a href="<?= BASE_URL ?>/orders.php" class="btn btn-gold btn-sm">&larr; Return to My Orders</a>
            </div>
        <?php else: ?>
            <!-- Detailed Order Dossier View -->
            <div class="account-view-header">
                <div>
                    <span class="auth-brand-eyebrow">Atelier Dispatch Dossier</span>
                    <h1 class="account-view-title"><?= e($order['order_number']) ?></h1>
                    <p class="account-view-subtitle">Placed on <?= date('d F Y \a\t h:i A', strtotime($order['placed_at'])) ?></p>
                </div>
                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <span class="status-pill status-<?= e($order['order_status']) ?>" style="font-size: 0.8125rem; padding: 0.35rem 0.75rem;">
                        <?= ucfirst(str_replace('_', ' ', $order['order_status'])) ?>
                    </span>
                    <span class="status-pill payment-<?= e($order['payment_status']) ?>" style="font-size: 0.8125rem; padding: 0.35rem 0.75rem;">
                        Payment: <?= ucfirst(e($order['payment_status'])) ?> (<?= strtoupper(e($order['payment_method'])) ?>)
                    </span>
                </div>
            </div>

            <div class="account-layout-grid" style="grid-template-columns: 1fr 340px; margin-top: 1.5rem;">
                
                <!-- Left: Line Items & Shipping Info -->
                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    
                    <!-- Line Items Card -->
                    <div class="dashboard-card" style="padding: 0; overflow: hidden;">
                        <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); background: #FAF8F5;">
                            <h2 class="card-heading">Curated Creations (<?= count($order['items'] ?? []) ?>)</h2>
                        </div>

                        <div class="orders-table-wrap">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Piece</th>
                                        <th>Details</th>
                                        <th>Unit Price</th>
                                        <th>Qty</th>
                                        <th style="text-align: right;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order['items'] as $item): ?>
                                        <tr>
                                            <td style="width: 70px;">
                                                <?php if (!empty($item['image_url'])): ?>
                                                    <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['product_name']) ?>" style="width: 60px; height: 80px; object-fit: cover; border-radius: 2px;">
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <h3 style="font-size: 0.9375rem; margin: 0 0 0.25rem; font-family: var(--font-serif); font-weight: 500;">
                                                    <?php if (!empty($item['product_slug'])): ?>
                                                        <a href="<?= BASE_URL ?>/product.php?slug=<?= e($item['product_slug']) ?>" style="color:var(--text-primary); text-decoration:none;">
                                                            <?= e($item['product_name']) ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <?= e($item['product_name']) ?>
                                                    <?php endif; ?>
                                                </h3>
                                                <?php if (!empty($item['variant_name'])): ?>
                                                    <span style="font-size: 0.75rem; color: var(--text-secondary); display:block;">
                                                        Variant: <?= e($item['variant_name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                                <span style="font-size: 0.6875rem; color: #9C9589;">
                                                    SKU: <?= e($item['sku']) ?>
                                                </span>
                                            </td>
                                            <td><?= format_price($item['unit_price']) ?></td>
                                            <td>&times; <?= (int)$item['quantity'] ?></td>
                                            <td style="text-align: right;">
                                                <strong><?= format_price($item['total_price']) ?></strong>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Delivery & Billing Addresses Grid -->
                    <div class="address-cards-grid">
                        <!-- Shipping Destination -->
                        <div class="address-box">
                            <div class="address-badges-row">
                                <span class="address-badge badge-default">White-Glove Shipping Destination</span>
                            </div>
                            <h3 class="address-recipient-name"><?= e($order['shipping_full_name']) ?></h3>
                            <p class="address-text">
                                <?= e($order['shipping_address_line1']) ?><br>
                                <?php if (!empty($order['shipping_address_line2'])): ?>
                                    <?= e($order['shipping_address_line2']) ?><br>
                                <?php endif; ?>
                                <?php if (!empty($order['shipping_landmark'])): ?>
                                    Landmark: <?= e($order['shipping_landmark']) ?><br>
                                <?php endif; ?>
                                <?= e($order['shipping_city']) ?>, <?= e($order['shipping_state']) ?> — <?= e($order['shipping_postal_code']) ?><br>
                                <?= e($order['shipping_country']) ?>
                            </p>
                            <span class="address-phone">📞 <?= e($order['shipping_phone']) ?></span>
                        </div>

                        <!-- Billing Destination -->
                        <div class="address-box">
                            <div class="address-badges-row">
                                <span class="address-badge badge-type">Billing Address</span>
                            </div>
                            <h3 class="address-recipient-name"><?= e($order['billing_full_name'] ?: $order['shipping_full_name']) ?></h3>
                            <p class="address-text">
                                <?= e($order['billing_address_line1'] ?: $order['shipping_address_line1']) ?><br>
                                <?php if (!empty($order['billing_address_line2'])): ?>
                                    <?= e($order['billing_address_line2']) ?><br>
                                <?php endif; ?>
                                <?= e($order['billing_city'] ?: $order['shipping_city']) ?>, <?= e($order['billing_state'] ?: $order['shipping_state']) ?> — <?= e($order['billing_postal_code'] ?: $order['shipping_postal_code']) ?><br>
                                <?= e($order['billing_country'] ?: $order['shipping_country']) ?>
                            </p>
                            <span class="address-phone">📞 <?= e($order['billing_phone'] ?: $order['shipping_phone']) ?></span>
                        </div>
                    </div>

                </div>

                <!-- Right: Financial Summary Card -->
                <aside class="dashboard-card" style="height: fit-content;">
                    <div class="dashboard-card-header">
                        <h2 class="card-heading">Payment Breakdown</h2>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:0.75rem; font-size:0.875rem;">
                        <div style="display:flex; justify-content:space-between; color:var(--text-secondary);">
                            <span>Bag Subtotal</span>
                            <span style="color:var(--text-primary); font-weight:500;"><?= format_price($order['subtotal']) ?></span>
                        </div>

                        <?php if ((float)$order['discount_amount'] > 0): ?>
                            <div style="display:flex; justify-content:space-between; color:#047857;">
                                <span>Privilege Discount <?= !empty($order['coupon_code']) ? '(' . e($order['coupon_code']) . ')' : '' ?></span>
                                <span style="font-weight:600;">&minus; <?= format_price($order['discount_amount']) ?></span>
                            </div>
                        <?php endif; ?>

                        <div style="display:flex; justify-content:space-between; color:var(--text-secondary);">
                            <span>White-Glove Shipping</span>
                            <span>
                                <?php if ((float)$order['shipping_charge'] <= 0): ?>
                                    <strong style="color:var(--accent-gold);">COMPLIMENTARY</strong>
                                <?php else: ?>
                                    <?= format_price($order['shipping_charge']) ?>
                                <?php endif; ?>
                            </span>
                        </div>

                        <div style="height:1px; background:var(--border-subtle); margin:0.25rem 0;"></div>

                        <div style="display:flex; justify-content:space-between; font-size:1.125rem; font-weight:600; color:var(--text-primary);">
                            <span>Grand Total</span>
                            <span style="font-family:var(--font-serif); font-size:1.375rem;"><?= format_price($order['grand_total']) ?></span>
                        </div>

                        <p style="font-size:0.75rem; color:#9C9589; margin:0.25rem 0 0;">
                            Inclusive of all luxury taxes, hallmarking & White-Glove transit insurances.
                        </p>
                    </div>

                    <div style="margin-top:1.5rem; padding-top:1.25rem; border-top:1px solid var(--border-subtle); font-size:0.8125rem; color:var(--text-secondary); line-height:1.5;">
                        <strong style="color:var(--text-primary); display:block; margin-bottom:0.25rem;">Atelier Client Concierge</strong>
                        <span>Need adjustments or luxury assistance? Contact our concierge at <strong>support@yatiya.com</strong>.</span>
                    </div>

                    <div style="margin-top:1.5rem;">
                        <a href="<?= BASE_URL ?>/orders.php" class="btn btn-outline btn-block btn-sm">
                            &larr; Back to Order History
                        </a>
                    </div>
                </aside>

            </div>
        <?php endif; ?>

    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

