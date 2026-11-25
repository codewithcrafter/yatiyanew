<?php
/**
 * YATIYA COUTURE - Luxury Order Dossier & Fulfillment Management
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

require_admin();

$orderId = (int)($_GET['id'] ?? 0);
$order = admin_get_order($orderId);

if (!$order) {
    set_flash('error', 'Order dossier not found.');
    redirect(ADMIN_URL . '/orders.php');
}

$adminPageTitle = 'Order Dossier: ' . $order['order_number'];
$activeMenu = 'orders';

// Handle Order Status or Payment Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . "/order-details.php?id=$orderId");
    }

    $action = $_POST['action'] ?? '';
    $adminId = (int)$currentAdmin['id'];

    if ($action === 'update_order_status') {
        $newStatus = clean_input($_POST['order_status'] ?? '');
        $adminNotes = clean_input($_POST['admin_notes'] ?? '');
        $res = admin_update_order_status($orderId, $newStatus, $adminNotes, $adminId);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('error', $res['message']);
        }
    } elseif ($action === 'update_payment_status') {
        $newPayStatus = clean_input($_POST['payment_status'] ?? '');
        $res = admin_update_payment_status($orderId, $newPayStatus, $adminId);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('error', $res['message']);
        }
    }

    redirect(ADMIN_URL . "/order-details.php?id=$orderId");
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="max-width: 1100px; margin: 0 auto;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <a href="<?= ADMIN_URL ?>/orders.php" style="color: var(--text-muted); font-size: 12px; text-decoration: none;">
                &larr; Back to Order Registry
            </a>
            <h2 style="font-family: var(--font-heading); font-size: 22px; font-weight: 600; margin-top: 4px;">
                Order Dossier <?= e($order['order_number']) ?>
            </h2>
            <div style="font-size: 12px; color: var(--text-muted);">
                Placed on <?= date('d F Y \a\t h:i A', strtotime($order['placed_at'])) ?>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <span class="status-badge <?= e($order['payment_status']) ?>" style="font-size: 13px; padding: 6px 12px;">
                Payment: <?= ucfirst(e($order['payment_status'])) ?>
            </span>
            <span class="status-badge <?= e($order['order_status']) ?>" style="font-size: 13px; padding: 6px 12px;">
                Status: <?= ucfirst(str_replace('_', ' ', e($order['order_status']))) ?>
            </span>
        </div>
    </div>

    <!-- Two-Column Layout -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
        <!-- Left: Line Items & Status Management -->
        <div>
            <!-- Purchased Creations Card -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3 class="card-title">Acquired Creations (<?= count($order['items']) ?> items)</h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Creation</th>
                                    <th>Unit Price</th>
                                    <th>Qty</th>
                                    <th>Line Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($order['items'] as $item): ?>
                                    <tr>
                                        <td>
                                            <div class="product-cell">
                                                <img src="<?= e($item['image_url'] ?: 'assets/images/placeholder.jpg') ?>" class="product-thumb" alt="">
                                                <div>
                                                    <div style="font-weight: 600;"><?= e($item['product_name']) ?></div>
                                                    <div style="font-size: 11px; color: var(--text-muted);">
                                                        <?= e($item['variant_name']) ?> &bull; SKU: <?= e($item['sku']) ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="font-weight: 500;">
                                            <?= format_price($item['unit_price']) ?>
                                        </td>
                                        <td style="font-weight: 600;">
                                            <?= (int)$item['quantity'] ?>
                                        </td>
                                        <td style="font-weight: 700;">
                                            <?= format_price($item['total_price']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Financial Summary -->
                    <div style="padding: 20px 24px; background: #FAF8F5; border-top: 1px solid var(--border-color);">
                        <div style="max-width: 320px; margin-left: auto;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                                <span style="color: var(--text-muted);">Creations Subtotal:</span>
                                <span style="font-weight: 600;"><?= format_price($order['subtotal']) ?></span>
                            </div>
                            <?php if ($order['discount_amount'] > 0): ?>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; color: #2E7D32;">
                                    <span>Privilege Discount (<?= e($order['coupon_code']) ?>):</span>
                                    <span>-<?= format_price($order['discount_amount']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px;">
                                <span style="color: var(--text-muted);">White-Glove Shipping:</span>
                                <span style="font-weight: 600;">
                                    <?= ($order['shipping_charge'] == 0) ? '<span style="color:#C6A15B;">COMPLIMENTARY</span>' : format_price($order['shipping_charge']) ?>
                                </span>
                            </div>
                            <div style="display: flex; justify-content: space-between; padding-top: 10px; margin-top: 6px; border-top: 2px solid var(--border-color); font-size: 16px;">
                                <span style="font-weight: 700;">Grand Total:</span>
                                <span style="font-family: var(--font-heading); font-size: 20px; font-weight: 700; color: var(--gold);">
                                    <?= format_price($order['grand_total']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status & Notes Control Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Order Status & Internal Atelier Notes</h3>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="update_order_status">

                        <div class="form-row" style="margin-bottom: 16px;">
                            <div>
                                <label class="form-label" for="orderStatus">Fulfillment Stage</label>
                                <select id="orderStatus" name="order_status" class="form-control" required>
                                    <option value="placed" <?= ($order['order_status'] === 'placed') ? 'selected' : '' ?>>Placed by Patron</option>
                                    <option value="confirmed" <?= ($order['order_status'] === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
                                    <option value="processing" <?= ($order['order_status'] === 'processing') ? 'selected' : '' ?>>Processing in Atelier</option>
                                    <option value="shipped" <?= ($order['order_status'] === 'shipped') ? 'selected' : '' ?>>Shipped / In Transit</option>
                                    <option value="out_for_delivery" <?= ($order['order_status'] === 'out_for_delivery') ? 'selected' : '' ?>>Out for Delivery</option>
                                    <option value="delivered" <?= ($order['order_status'] === 'delivered') ? 'selected' : '' ?>>Delivered to Client</option>
                                    <option value="cancelled" <?= ($order['order_status'] === 'cancelled') ? 'selected' : '' ?>>Cancelled (Restocks Inventory)</option>
                                    <option value="returned" <?= ($order['order_status'] === 'returned') ? 'selected' : '' ?>>Returned by Client</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="adminNotes">Internal Atelier Notes (Not visible to patron)</label>
                            <textarea id="adminNotes" name="admin_notes" class="form-control" rows="3" placeholder="Enter private staff or courier notes..."><?= e($order['admin_notes']) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-gold">Update Fulfillment Status</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Client & Destinations Dossier -->
        <div>
            <!-- Patron Profile Card -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3 class="card-title">Patron Profile</h3>
                </div>
                <div class="card-body">
                    <div style="font-weight: 700; font-size: 15px; margin-bottom: 4px;">
                        <?= e($order['shipping_full_name'] ?: ($order['first_name'] . ' ' . $order['last_name'])) ?>
                    </div>
                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 2px;">
                        Email: <?= e($order['customer_email']) ?>
                    </div>
                    <div style="font-size: 13px; color: var(--text-muted); margin-bottom: 12px;">
                        Phone: <?= e($order['shipping_phone'] ?: ($order['customer_phone'] ?? 'N/A')) ?>
                    </div>
                    <?php if (!empty($order['customer_id'])): ?>
                        <a href="<?= ADMIN_URL ?>/customer-details.php?id=<?= $order['customer_id'] ?>" class="btn btn-outline btn-sm">
                            View Patron Dossier &rarr;
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Information Card -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3 class="card-title">Payment Settlement</h3>
                </div>
                <div class="card-body">
                    <div style="font-size: 13px; margin-bottom: 8px;">
                        <strong>Method:</strong> <?= strtoupper(e($order['payment_method'])) ?>
                    </div>
                    <div style="font-size: 13px; margin-bottom: 8px;">
                        <strong>Status:</strong> <span class="status-badge <?= e($order['payment_status']) ?>"><?= ucfirst(e($order['payment_status'])) ?></span>
                    </div>

                    <?php if (!empty($order['payment'])): 
                        $p = $order['payment'];
                    ?>
                        <div style="background: #F7F4ED; border: 1px solid #E5E0D5; padding: 12px; border-radius: 3px; font-size: 12px; margin: 12px 0; line-height: 1.5;">
                            <div><strong>Gateway:</strong> <?= strtoupper(e($p['payment_method'])) ?></div>
                            <?php if (!empty($p['gateway_order_id'])): ?>
                                <div><strong>Gateway Order:</strong> <code><?= e($p['gateway_order_id']) ?></code></div>
                            <?php endif; ?>
                            <?php if (!empty($p['transaction_id'])): ?>
                                <div><strong>Transaction ID:</strong> <code><?= e($p['transaction_id']) ?></code></div>
                            <?php endif; ?>
                            <?php if (!empty($p['signature'])): ?>
                                <div style="color: #2E7D32; font-weight: 600; margin-top: 4px;">
                                    ✓ Cryptographic Signature Verified
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($p['payment_date'])): ?>
                                <div style="color: var(--text-muted); margin-top: 2px;">
                                    Settled: <?= date('d M Y, h:i A', strtotime($p['payment_date'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($p['payment_gateway_response'])): ?>
                                <details style="margin-top: 6px; cursor: pointer;">
                                    <summary style="color: var(--gold); font-size: 11px;">Gateway Payload Snapshot</summary>
                                    <pre style="font-size: 10px; background: #FFF; padding: 8px; border: 1px solid #DDD; border-radius: 2px; max-height: 120px; overflow-y: auto; margin-top: 4px;"><?= e(json_encode(json_decode($p['payment_gateway_response']), JSON_PRETTY_PRINT)) ?></pre>
                                </details>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form action="" method="POST" style="margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border-color);">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="action" value="update_payment_status">

                        <label class="form-label" for="payStatus">Change Payment Status</label>
                        <select id="payStatus" name="payment_status" class="form-control" style="margin-bottom: 10px;">
                            <option value="pending" <?= ($order['payment_status'] === 'pending') ? 'selected' : '' ?>>Pending</option>
                            <option value="paid" <?= ($order['payment_status'] === 'paid') ? 'selected' : '' ?>>Paid / Settled</option>
                            <option value="failed" <?= ($order['payment_status'] === 'failed') ? 'selected' : '' ?>>Failed</option>
                            <option value="refunded" <?= ($order['payment_status'] === 'refunded') ? 'selected' : '' ?>>Refunded</option>
                        </select>
                        <button type="submit" class="btn btn-dark btn-sm">Update Payment</button>
                    </form>
                </div>
            </div>

            <!-- Delivery Address Card -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3 class="card-title">Delivery Destination</h3>
                </div>
                <div class="card-body" style="font-size: 13px; line-height: 1.6;">
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
                    <strong>Phone:</strong> <?= e($order['shipping_phone']) ?>
                </div>
            </div>

            <!-- Billing Address Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Billing Destination</h3>
                </div>
                <div class="card-body" style="font-size: 13px; line-height: 1.6;">
                    <?php if (!empty($order['billing_full_name'])): ?>
                        <strong><?= e($order['billing_full_name']) ?></strong><br>
                        <?= e($order['billing_address_line1']) ?><br>
                        <?php if (!empty($order['billing_address_line2'])): ?>
                            <?= e($order['billing_address_line2']) ?><br>
                        <?php endif; ?>
                        <?= e($order['billing_city']) ?>, <?= e($order['billing_state']) ?> &ndash; <?= e($order['billing_postal_code']) ?><br>
                        <?= e($order['billing_country']) ?><br>
                        <strong>Phone:</strong> <?= e($order['billing_phone']) ?>
                    <?php else: ?>
                        <span style="color: var(--text-muted);">Same as delivery destination.</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

