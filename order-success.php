<?php
/**
 * YATIYA COUTURE - Luxury Order Confirmation & Receipt Dossier
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/checkout-functions.php';

$orderNumber = clean_input($_GET['order'] ?? ($_SESSION['last_order_number'] ?? ''));

if (empty($orderNumber)) {
    redirect(BASE_URL . '/index.php');
}

$order = get_order_by_number($orderNumber);
$customerId = is_logged_in() ? (int)$_SESSION['customer_id'] : null;

if (!$order || !can_view_order($order, $customerId)) {
    set_flash('error', 'The requested order dossier could not be found or you are not authorized to view it.');
    redirect(BASE_URL . '/index.php');
}

$pageTitle = 'Order Confirmed: ' . $order['order_number'] . ' | ' . SITE_NAME;
$extraCss = ['assets/css/checkout.css'];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Printable Letterhead (visible only when printing) -->
<div class="print-only-header">
    <h1 style="font-family: 'Playfair Display', serif; font-size: 26px; margin: 0; letter-spacing: 2px;">YATIYA COUTURE</h1>
    <div style="font-size: 11px; letter-spacing: 1px; color: #555; text-transform: uppercase;">Official Atelier Sales Dossier & Tax Invoice</div>
    <div style="font-size: 10px; color: #777; margin-top: 4px;">Concierge: concierge@yatiyacouture.com &bull; New Delhi, India</div>
</div>

<div class="checkout-wrapper">
    <div class="checkout-container" style="max-width: 1000px;">
        <!-- Success Banner -->
        <div class="success-banner">
            <div class="success-icon">&#10003;</div>
            <h1 class="success-title">Thank You For Your Acquisition</h1>
            <p class="success-subtitle">
                Your order has been formally placed and is being curated by our atelier master artisans.
                A confirmation dossier has been dispatched to <strong><?= e($order['customer_email']) ?></strong>.
            </p>
            <div class="order-meta-pill">
                Order Reference: <?= e($order['order_number']) ?>
            </div>
            <div style="font-size: 12px; color: var(--color-muted); margin-top: 8px;">
                Placed on <?= date('d F Y \a\t h:i A', strtotime($order['placed_at'])) ?>
            </div>
        </div>

        <div class="checkout-grid" style="grid-template-columns: 1.6fr 1fr;">
            <!-- Left: Line Items -->
            <div>
                <div class="checkout-card" style="padding: 24px;">
                    <h2 class="checkout-card-title" style="font-size: 18px; margin-bottom: 20px;">
                        Acquired Creations (<?= count($order['items']) ?> items)
                    </h2>

                    <div style="display: flex; flex-direction: column; gap: 16px;">
                        <?php foreach ($order['items'] as $item): ?>
                            <div style="display: flex; gap: 16px; padding-bottom: 16px; border-bottom: 1px solid #ECE7DE;">
                                <img src="<?= e($item['image_url'] ?: 'assets/images/placeholder.jpg') ?>" 
                                     style="width: 72px; height: 90px; object-fit: cover; border-radius: 2px; background: #E5E0D5;" 
                                     alt="<?= e($item['product_name']) ?>">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; font-size: 14px; margin-bottom: 4px;"><?= e($item['product_name']) ?></div>
                                    <div style="font-size: 12px; color: var(--color-muted); margin-bottom: 4px;">
                                        <?= e($item['variant_name']) ?> &bull; SKU: <?= e($item['sku']) ?>
                                    </div>
                                    <div style="font-size: 13px;">
                                        Qty: <strong><?= (int)$item['quantity'] ?></strong> &times; <?= format_price($item['unit_price']) ?>
                                    </div>
                                </div>
                                <div style="font-weight: 700; font-size: 15px; color: var(--color-dark);">
                                    <?= format_price($item['total_price']) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Financial Summary Table -->
                    <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid #ECE7DE;">
                        <div class="financial-row">
                            <span>Subtotal:</span>
                            <span><?= format_price($order['subtotal']) ?></span>
                        </div>
                        <?php if ($order['discount_amount'] > 0): ?>
                            <div class="financial-row discount-row">
                                <span>Privilege Code Discount (<?= e($order['coupon_code']) ?>):</span>
                                <span>-<?= format_price($order['discount_amount']) ?></span>
                            </div>
                        <?php endif; ?>
                        <div class="financial-row">
                            <span>White-Glove Shipping:</span>
                            <span>
                                <?= ($order['shipping_charge'] == 0) ? '<span style="color:#C6A15B; font-weight:600;">COMPLIMENTARY</span>' : format_price($order['shipping_charge']) ?>
                            </span>
                        </div>
                        <div class="financial-row total-row" style="font-size: 20px;">
                            <span>Grand Total:</span>
                            <span style="color: var(--color-gold);">
                                <?= format_price($order['grand_total']) ?>
                            </span>
                        </div>
                        <div style="font-size: 11px; color: var(--color-muted); text-align: right; margin-top: -6px;">
                            Inclusive of 12% GST (<?= format_price($order['tax_amount']) ?>)
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Delivery Destination & Payment Dossier -->
            <div>
                <!-- Delivery Destination Card -->
                <div class="checkout-card" style="padding: 24px; margin-bottom: 24px;">
                    <h3 style="font-family: var(--font-heading); font-size: 16px; font-weight: 600; margin-bottom: 12px; border-bottom: 1px solid #ECE7DE; padding-bottom: 8px;">
                        Delivery Destination
                    </h3>
                    <div style="font-size: 13px; line-height: 1.6; color: var(--color-dark);">
                        <strong><?= e($order['shipping_full_name']) ?></strong><br>
                        <?= e($order['shipping_address_line1']) ?><br>
                        <?php if (!empty($order['shipping_address_line2'])): ?>
                            <?= e($order['shipping_address_line2']) ?><br>
                        <?php endif; ?>
                        <?php if (!empty($order['shipping_landmark'])): ?>
                            <em>Landmark: <?= e($order['shipping_landmark']) ?></em><br>
                        <?php endif; ?>
                        <?= e($order['shipping_city']) ?>, <?= e($order['shipping_state']) ?> &ndash; <?= e($order['shipping_postal_code']) ?><br>
                        <?= e($order['shipping_country']) ?><br>
                        <strong>Contact:</strong> <?= e($order['shipping_phone']) ?>
                    </div>
                </div>

                <!-- Payment Status Card -->
                <div class="checkout-card" style="padding: 24px; margin-bottom: 24px;">
                    <h3 style="font-family: var(--font-heading); font-size: 16px; font-weight: 600; margin-bottom: 12px; border-bottom: 1px solid #ECE7DE; padding-bottom: 8px;">
                        Payment Settlement
                    </h3>
                    <div style="font-size: 13px; line-height: 1.6;">
                        <div style="margin-bottom: 6px;">
                            <strong>Method:</strong> <?= ($order['payment_method'] === 'cod') ? 'Cash on Delivery (White-Glove Collection)' : 'Online Payment (Razorpay)' ?>
                        </div>
                        <div style="margin-bottom: 6px;">
                            <strong>Payment Status:</strong> 
                            <span style="display: inline-block; padding: 2px 8px; border-radius: 2px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: <?= ($order['payment_status'] === 'paid') ? '#E8F5E9; color:#2E7D32;' : '#FFF9C4; color:#F57F17;' ?>">
                                <?= e($order['payment_status']) ?>
                            </span>
                        </div>
                        <?php if (!empty($order['payment']['transaction_id'])): ?>
                            <div style="margin-bottom: 6px; font-size: 12px; color: var(--color-muted);">
                                <strong>Transaction Ref:</strong> <?= e($order['payment']['transaction_id']) ?>
                            </div>
                        <?php endif; ?>
                        <div style="font-size: 12px; color: var(--color-muted);">
                            <strong>Fulfillment Stage:</strong> <?= ucfirst(str_replace('_', ' ', e($order['order_status']))) ?>
                        </div>
                    </div>
                </div>

                <!-- Action Controls (no-print) -->
                <div class="no-print" style="display: flex; flex-direction: column; gap: 10px;">
                    <button onclick="window.print()" class="btn" style="width: 100%; padding: 12px; background: #FFFFFF; border: 1.5px solid var(--color-dark); color: var(--color-dark); font-weight: 600; cursor: pointer;">
                        🖨 Print Official Invoice / Receipt
                    </button>
                    <a href="<?= BASE_URL ?>/shop.php" class="btn" style="width: 100%; padding: 12px; background: var(--color-dark); border: 1px solid var(--color-dark); color: #FFFFFF; text-align: center; font-weight: 600; text-decoration: none;">
                        Continue Exploring Creations &rarr;
                    </a>
                    <?php if (is_logged_in()): ?>
                        <a href="<?= BASE_URL ?>/account.php" style="text-align: center; font-size: 12px; color: var(--color-muted); text-decoration: underline; margin-top: 4px;">
                            View in Your Atelier Orders
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

