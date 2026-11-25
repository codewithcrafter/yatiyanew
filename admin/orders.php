<?php
/**
 * YATIYA COUTURE - Luxury Order Operations Management
 */

$adminPageTitle = 'Orders & Client Acquisitions';
$activeMenu = 'orders';

require_once __DIR__ . '/includes/admin-header.php';

// Filters & Pagination
$search = clean_input($_GET['search'] ?? '');
$orderStatus = clean_input($_GET['order_status'] ?? '');
$paymentStatus = clean_input($_GET['payment_status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$filters = [
    'search' => $search,
    'order_status' => $orderStatus,
    'payment_status' => $paymentStatus
];

$orderData = admin_get_orders($filters, $page, 15);
?>

<!-- Filter Bar -->
<div class="filter-bar">
    <form action="<?= ADMIN_URL ?>/orders.php" method="GET" class="filter-group" style="flex: 1;">
        <input 
            type="search" 
            name="search" 
            class="form-control" 
            placeholder="Search Order #, patron name or email..." 
            value="<?= e($search) ?>" 
            style="max-width: 280px;"
        >

        <select name="order_status" class="form-control" style="max-width: 180px;">
            <option value="">All Order Statuses</option>
            <option value="placed" <?= ($orderStatus === 'placed') ? 'selected' : '' ?>>Placed</option>
            <option value="confirmed" <?= ($orderStatus === 'confirmed') ? 'selected' : '' ?>>Confirmed</option>
            <option value="processing" <?= ($orderStatus === 'processing') ? 'selected' : '' ?>>Processing in Atelier</option>
            <option value="shipped" <?= ($orderStatus === 'shipped') ? 'selected' : '' ?>>Shipped</option>
            <option value="out_for_delivery" <?= ($orderStatus === 'out_for_delivery') ? 'selected' : '' ?>>Out for Delivery</option>
            <option value="delivered" <?= ($orderStatus === 'delivered') ? 'selected' : '' ?>>Delivered</option>
            <option value="cancelled" <?= ($orderStatus === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
            <option value="returned" <?= ($orderStatus === 'returned') ? 'selected' : '' ?>>Returned</option>
        </select>

        <select name="payment_status" class="form-control" style="max-width: 170px;">
            <option value="">All Payment States</option>
            <option value="paid" <?= ($paymentStatus === 'paid') ? 'selected' : '' ?>>Paid</option>
            <option value="pending" <?= ($paymentStatus === 'pending') ? 'selected' : '' ?>>Pending</option>
            <option value="failed" <?= ($paymentStatus === 'failed') ? 'selected' : '' ?>>Failed</option>
            <option value="refunded" <?= ($paymentStatus === 'refunded') ? 'selected' : '' ?>>Refunded</option>
        </select>

        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
        <?php if (!empty($search) || !empty($orderStatus) || !empty($paymentStatus)): ?>
            <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Orders Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Client Acquisition Dossiers (<?= number_format($orderData['total']) ?> Orders)</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($orderData['items'])): ?>
            <div style="padding: 48px; text-align: center; color: var(--text-muted);">
                <p style="font-size: 14px; font-weight: 500;">No orders found matching the filter criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order Reference</th>
                            <th>Patron Client</th>
                            <th>Placement Date</th>
                            <th>Creations</th>
                            <th>Payment</th>
                            <th>Fulfillment Status</th>
                            <th>Grand Total</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderData['items'] as $ord): ?>
                            <tr>
                                <td>
                                    <a href="<?= ADMIN_URL ?>/order-details.php?id=<?= $ord['id'] ?>" style="font-weight: 600; color: var(--text-main); text-decoration: none;">
                                        <?= e($ord['order_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= e($ord['shipping_full_name'] ?: ($ord['first_name'] . ' ' . $ord['last_name'])) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($ord['customer_email']) ?></div>
                                </td>
                                <td style="white-space: nowrap; color: var(--text-muted); font-size: 12px;">
                                    <?= date('d M Y, h:i A', strtotime($ord['placed_at'])) ?>
                                </td>
                                <td>
                                    <span style="font-size: 12px; font-weight: 600;"><?= (int)$ord['item_count'] ?> piece(s)</span>
                                </td>
                                <td>
                                    <span class="status-badge <?= e($ord['payment_status']) ?>">
                                        <?= ucfirst(e($ord['payment_status'])) ?> (<?= strtoupper(e($ord['payment_method'])) ?>)
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= e($ord['order_status']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', e($ord['order_status']))) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="font-weight: 700; font-size: 14px;">
                                        <?= format_price($ord['grand_total']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?= ADMIN_URL ?>/order-details.php?id=<?= $ord['id'] ?>" class="btn btn-outline btn-sm" style="padding: 4px 10px;">
                                        View Dossier
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Numbered Pagination -->
            <?php if ($orderData['total_pages'] > 1): ?>
                <div style="padding: 16px 24px;">
                    <div class="pagination">
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Showing page <?= $orderData['page'] ?> of <?= $orderData['total_pages'] ?>
                        </div>
                        <div class="pagination-pages">
                            <?php for ($p = 1; $p <= $orderData['total_pages']; $p++): ?>
                                <a href="<?= ADMIN_URL ?>/orders.php?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>" 
                                   class="page-btn <?= ($p === $orderData['page']) ? 'active' : '' ?>">
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

