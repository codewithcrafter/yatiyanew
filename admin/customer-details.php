<?php
/**
 * YATIYA COUTURE - Luxury Customer Profile & History Dossier
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

require_admin();

$customerId = (int)($_GET['id'] ?? 0);
$customer = admin_get_customer_details($customerId);

if (!$customer) {
    set_flash('error', 'Patron dossier not found in registry.');
    redirect(ADMIN_URL . '/customers.php');
}

$adminPageTitle = 'Patron Dossier: ' . $customer['first_name'] . ' ' . $customer['last_name'];
$activeMenu = 'customers';

// Handle Status Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . "/customer-details.php?id=$customerId");
    }

    $newStatus = clean_input($_POST['status'] ?? 'active');
    $adminId = (int)$currentAdmin['id'];

    if (admin_toggle_customer_status($customerId, $newStatus, $adminId)) {
        set_flash('success', "Patron account status updated to '$newStatus'.");
    } else {
        set_flash('error', 'Failed to update patron status.');
    }
    redirect(ADMIN_URL . "/customer-details.php?id=$customerId");
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="max-width: 1040px; margin: 0 auto;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <a href="<?= ADMIN_URL ?>/customers.php" style="color: var(--text-muted); font-size: 12px; text-decoration: none;">
                &larr; Back to Patron Registry
            </a>
            <h2 style="font-family: var(--font-heading); font-size: 22px; font-weight: 600; margin-top: 4px;">
                <?= e($customer['first_name'] . ' ' . $customer['last_name']) ?>
            </h2>
            <div style="font-size: 12px; color: var(--text-muted);">
                Patron since <?= date('d F Y', strtotime($customer['created_at'])) ?>
            </div>
        </div>
        <div>
            <span class="status-badge <?= e($customer['status']) ?>" style="font-size: 13px; padding: 6px 14px;">
                Account: <?= ucfirst(e($customer['status'])) ?>
            </span>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px;">
        <!-- Left: Personal Details & Status Form -->
        <div>
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3 class="card-title">Patron Identity</h3>
                </div>
                <div class="card-body" style="font-size: 13px; line-height: 1.8;">
                    <div><strong>Full Name:</strong> <?= e($customer['first_name'] . ' ' . $customer['last_name']) ?></div>
                    <div><strong>Email:</strong> <?= e($customer['email']) ?></div>
                    <div><strong>Phone:</strong> <?= e($customer['phone'] ?: 'None registered') ?></div>
                    <div><strong>Joined:</strong> <?= date('d M Y, h:i A', strtotime($customer['created_at'])) ?></div>
                    <div><strong>Last Login:</strong> <?= !empty($customer['last_login_at']) ? date('d M Y, h:i A', strtotime($customer['last_login_at'])) : 'Never' ?></div>
                </div>
            </div>

            <!-- Manage Account Status -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Account Governance</h3>
                </div>
                <div class="card-body">
                    <form action="" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <div class="form-group">
                            <label class="form-label" for="custStatus">Account Standing</label>
                            <select id="custStatus" name="status" class="form-control" style="margin-bottom: 14px;">
                                <option value="active" <?= ($customer['status'] === 'active') ? 'selected' : '' ?>>Active Patron</option>
                                <option value="suspended" <?= ($customer['status'] === 'suspended') ? 'selected' : '' ?>>Suspended (Access Revoked)</option>
                                <option value="inactive" <?= ($customer['status'] === 'inactive') ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-dark btn-sm" style="width: 100%;">Update Standing</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right: Order History & Saved Addresses -->
        <div>
            <!-- Order History Card -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h3 class="card-title">Acquisition History (<?= count($customer['orders']) ?> Orders)</h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($customer['orders'])): ?>
                        <div style="padding: 32px; text-align: center; color: var(--text-muted); font-size: 13px;">
                            No historical orders found for this patron.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Order # / Items</th>
                                        <th>Date</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Total</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($customer['orders'] as $ord): ?>
                                        <tr>
                                            <td>
                                                <a href="<?= ADMIN_URL ?>/order-details.php?id=<?= $ord['id'] ?>" style="font-weight: 600; color: var(--text-main); text-decoration: none;">
                                                    <?= e($ord['order_number']) ?>
                                                </a>
                                                <?php if (!empty($ord['items'])): ?>
                                                    <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px; line-height: 1.4;">
                                                        <?php foreach ($ord['items'] as $it): ?>
                                                            <div>&bull; <?= e($it['product_name']) ?><?php if (!empty($it['variant_name'])): ?> (<?= e($it['variant_name']) ?>)<?php endif; ?> &times; <?= (int)$it['quantity'] ?></div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td style="font-size: 12px; color: var(--text-muted); white-space: nowrap;">
                                                <?= date('d M Y', strtotime($ord['placed_at'])) ?>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= e($ord['payment_status']) ?>">
                                                    <?= ucfirst(e($ord['payment_status'])) ?>
                                                </span>
                                                <div style="font-size: 10px; color: var(--text-muted); text-transform: uppercase; margin-top: 2px;">
                                                    <?= e($ord['payment_method']) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge <?= e($ord['order_status']) ?>">
                                                    <?= ucfirst(str_replace('_', ' ', e($ord['order_status']))) ?>
                                                </span>
                                            </td>
                                            <td style="font-weight: 700;">
                                                <?= format_price($ord['grand_total']) ?>
                                            </td>
                                            <td>
                                                <a href="<?= ADMIN_URL ?>/order-details.php?id=<?= $ord['id'] ?>" class="btn-action" title="View Order">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                        <circle cx="12" cy="12" r="3"></circle>
                                                    </svg>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Saved Destinations Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Saved Delivery Destinations (<?= count($customer['addresses']) ?>)</h3>
                </div>
                <div class="card-body" style="padding: 16px;">
                    <?php if (empty($customer['addresses'])): ?>
                        <div style="padding: 20px; text-align: center; color: var(--text-muted); font-size: 13px;">
                            No delivery destinations saved in address book.
                        </div>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
                            <?php foreach ($customer['addresses'] as $addr): ?>
                                <div style="border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 14px; font-size: 12px; line-height: 1.6; background: #FAF8F5;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                        <strong><?= e($addr['full_name']) ?></strong>
                                        <span style="font-size: 10px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gold); font-weight: 700;">
                                            <?= strtoupper(e($addr['address_type'])) ?>
                                        </span>
                                    </div>
                                    <?= e($addr['address_line1']) ?><br>
                                    <?php if (!empty($addr['address_line2'])): ?>
                                        <?= e($addr['address_line2']) ?><br>
                                    <?php endif; ?>
                                    <?= e($addr['city']) ?>, <?= e($addr['state']) ?> &ndash; <?= e($addr['postal_code']) ?><br>
                                    <?= e($addr['country']) ?><br>
                                    <strong>Phone:</strong> <?= e($addr['phone']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

