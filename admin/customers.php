<?php
/**
 * YATIYA COUTURE - Luxury Customer Registry Management
 */

$adminPageTitle = 'Patron Registry';
$activeMenu = 'customers';

require_once __DIR__ . '/includes/admin-header.php';

// Handle Customer Status Toggle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/customers.php');
    }

    $customerId = (int)($_POST['customer_id'] ?? 0);
    $newStatus = clean_input($_POST['new_status'] ?? 'active');
    $adminId = (int)$currentAdmin['id'];

    if (admin_toggle_customer_status($customerId, $newStatus, $adminId)) {
        set_flash('success', "Customer account status updated to '$newStatus'.");
    } else {
        set_flash('error', 'Failed to update customer status.');
    }
    redirect(ADMIN_URL . '/customers.php');
}

// Filters & Pagination
$search = clean_input($_GET['search'] ?? '');
$status = clean_input($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$filters = [
    'search' => $search,
    'status' => $status
];

$customerData = admin_get_customers($filters, $page, 15);
?>

<!-- Filter Bar -->
<div class="filter-bar">
    <form action="<?= ADMIN_URL ?>/customers.php" method="GET" class="filter-group">
        <input 
            type="search" 
            name="search" 
            class="form-control" 
            placeholder="Search patron name, email or phone..." 
            value="<?= e($search) ?>" 
            style="max-width: 280px;"
        >

        <select name="status" class="form-control" style="max-width: 170px;">
            <option value="">All Account Statuses</option>
            <option value="active" <?= ($status === 'active') ? 'selected' : '' ?>>Active Patrons</option>
            <option value="inactive" <?= ($status === 'inactive') ? 'selected' : '' ?>>Inactive</option>
            <option value="suspended" <?= ($status === 'suspended') ? 'selected' : '' ?>>Suspended</option>
        </select>

        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
        <?php if (!empty($search) || !empty($status)): ?>
            <a href="<?= ADMIN_URL ?>/customers.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Customers Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Registered Patrons (<?= number_format($customerData['total']) ?> Clients)</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($customerData['items'])): ?>
            <div style="padding: 48px; text-align: center; color: var(--text-muted);">
                <p style="font-size: 14px; font-weight: 500;">No registered patrons match the query.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Patron Client</th>
                            <th>Contact Phone</th>
                            <th>Registration Date</th>
                            <th>Orders</th>
                            <th>Total Spent</th>
                            <th>Last Order</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customerData['items'] as $c): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; font-size: 13px;">
                                        <a href="<?= ADMIN_URL ?>/customer-details.php?id=<?= $c['id'] ?>" style="color: var(--text-main); text-decoration: none;">
                                            <?= e($c['first_name'] . ' ' . $c['last_name']) ?>
                                        </a>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($c['email']) ?></div>
                                </td>
                                <td>
                                    <?= e($c['phone'] ?: 'N/A') ?>
                                </td>
                                <td style="white-space: nowrap; color: var(--text-muted); font-size: 12px;">
                                    <?= date('d M Y', strtotime($c['created_at'])) ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600;"><?= (int)$c['total_orders'] ?></span>
                                </td>
                                <td style="font-weight: 700;">
                                    <?= format_price($c['total_spent']) ?>
                                </td>
                                <td style="white-space: nowrap; color: var(--text-muted); font-size: 12px;">
                                    <?= !empty($c['last_order_date']) ? date('d M Y', strtotime($c['last_order_date'])) : '&ndash;' ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= e($c['status']) ?>">
                                        <?= ucfirst(e($c['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <a href="<?= ADMIN_URL ?>/customer-details.php?id=<?= $c['id'] ?>" class="btn-action" title="View Patron Profile">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>

                                        <!-- Toggle Status Form -->
                                        <form action="<?= ADMIN_URL ?>/customers.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="customer_id" value="<?= $c['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= ($c['status'] === 'active') ? 'suspended' : 'active' ?>">
                                            <button 
                                                type="submit" 
                                                class="btn-action <?= ($c['status'] === 'active') ? 'danger' : '' ?>" 
                                                title="<?= ($c['status'] === 'active') ? 'Suspend Account' : 'Activate Account' ?>"
                                                data-confirm="Are you sure you want to change this patron account status to <?= ($c['status'] === 'active') ? 'suspended' : 'active' ?>?"
                                            >
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <?php if ($c['status'] === 'active'): ?>
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                                    <?php else: ?>
                                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                    <?php endif; ?>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Numbered Pagination -->
            <?php if ($customerData['total_pages'] > 1): ?>
                <div style="padding: 16px 24px;">
                    <div class="pagination">
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Showing page <?= $customerData['page'] ?> of <?= $customerData['total_pages'] ?>
                        </div>
                        <div class="pagination-pages">
                            <?php for ($p = 1; $p <= $customerData['total_pages']; $p++): ?>
                                <a href="<?= ADMIN_URL ?>/customers.php?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>" 
                                   class="page-btn <?= ($p === $customerData['page']) ? 'active' : '' ?>">
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

