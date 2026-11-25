<?php
/**
 * YATIYA COUTURE - Customer Returns & Exchanges Management
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

require_admin();

$currentAdmin = current_admin();
$adminId = (int)$currentAdmin['id'];
$adminPageTitle = 'Returns & Privilege Exchanges';
$adminPageSubtitle = 'Process client return dossiers, manage approvals, and record exchange refunds.';
$activeMenu = 'returns';

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/returns.php');
    }

    $action = clean_input($_POST['action'] ?? '');
    if ($action === 'update_return_status') {
        $returnId = (int)($_POST['return_id'] ?? 0);
        $newStatus = clean_input($_POST['status'] ?? '');
        $remarks = trim($_POST['admin_remarks'] ?? '');

        if ($returnId > 0 && admin_update_return_status($returnId, $newStatus, $remarks, $adminId)) {
            set_flash('success', "Return dossier #{$returnId} updated to '{$newStatus}'.");
        } else {
            set_flash('error', 'Failed to update return request status.');
        }
        redirect(ADMIN_URL . '/returns.php');
    }
}

$statusFilter = clean_input($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$returnData = admin_get_returns(['status' => $statusFilter], $page, 15);

require_once __DIR__ . '/includes/admin-header.php';
?>

<!-- Filter Bar -->
<div class="filter-bar">
    <form action="<?= ADMIN_URL ?>/returns.php" method="GET" class="filter-group">
        <select name="status" class="form-control" style="max-width: 200px;">
            <option value="">All Return Statuses</option>
            <option value="requested" <?= ($statusFilter === 'requested') ? 'selected' : '' ?>>Requested</option>
            <option value="approved" <?= ($statusFilter === 'approved') ? 'selected' : '' ?>>Approved</option>
            <option value="pickup_scheduled" <?= ($statusFilter === 'pickup_scheduled') ? 'selected' : '' ?>>Pickup Scheduled</option>
            <option value="item_received" <?= ($statusFilter === 'item_received') ? 'selected' : '' ?>>Item Received</option>
            <option value="refund_processed" <?= ($statusFilter === 'refund_processed') ? 'selected' : '' ?>>Refund Processed</option>
            <option value="rejected" <?= ($statusFilter === 'rejected') ? 'selected' : '' ?>>Rejected</option>
        </select>
        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
        <?php if (!empty($statusFilter)): ?>
            <a href="<?= ADMIN_URL ?>/returns.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Returns Table Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Return Dossiers (<?= number_format($returnData['total']) ?> requests)</h3>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($returnData['returns'])): ?>
            <div style="padding: 48px; text-align: center; color: var(--text-muted);">
                <p style="font-size: 14px;">No return requests matching criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Dossier #</th>
                            <th>Order #</th>
                            <th>Patron</th>
                            <th>Item &amp; Value</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returnData['returns'] as $r): ?>
                            <tr>
                                <td><strong>#RET-<?= str_pad($r['id'], 5, '0', STR_PAD_LEFT) ?></strong></td>
                                <td>
                                    <a href="<?= ADMIN_URL ?>/order-details.php?id=<?= $r['order_id'] ?>" style="font-weight: 600; color: var(--text-main);">
                                        <?= e($r['order_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <div style="font-weight: 500;"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($r['customer_email']) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 12px;"><?= e($r['product_name'] ?: 'Order Creation') ?></div>
                                    <div style="font-size: 11px; color: var(--gold);"><?= format_price($r['refund_amount']) ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 500;"><?= e($r['reason']) ?></div>
                                    <?php if (!empty($r['reason_description'])): ?>
                                        <div style="font-size: 11px; color: var(--text-muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                            <?= e($r['reason_description']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= e($r['status']) ?>">
                                        <?= ucfirst(str_replace('_', ' ', e($r['status']))) ?>
                                    </span>
                                </td>
                                <td style="font-size: 12px; color: var(--text-muted);">
                                    <?= date('d M Y', strtotime($r['requested_at'])) ?>
                                </td>
                                <td>
                                    <button 
                                        type="button" 
                                        class="btn btn-outline btn-sm btn-update-return"
                                        data-id="<?= $r['id'] ?>"
                                        data-status="<?= e($r['status']) ?>"
                                        data-remarks="<?= e($r['admin_remarks'] ?? '') ?>"
                                        style="font-size: 11px; padding: 3px 8px;"
                                    >
                                        Manage
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($returnData['total_pages'] > 1): ?>
                <div style="margin: 20px 0; display: flex; justify-content: center; gap: 8px;">
                    <?php for ($p = 1; $p <= $returnData['total_pages']; $p++): ?>
                        <a href="<?= ADMIN_URL ?>/returns.php?page=<?= $p ?>&status=<?= urlencode($statusFilter) ?>" class="btn <?= ($p === $returnData['page']) ? 'btn-gold' : 'btn-outline' ?> btn-sm">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Update Return Status -->
<div class="modal-backdrop" id="returnModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="returnModalTitle">Manage Return Request</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/returns.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_return_status">
            <input type="hidden" name="return_id" id="returnModalId" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Return Lifecycle Status *</label>
                    <select name="status" id="returnSelectStatus" class="form-control" required>
                        <option value="requested">Requested (Pending Staff Review)</option>
                        <option value="approved">Approved (Awaiting Concierge Pickup)</option>
                        <option value="pickup_scheduled">Pickup Scheduled with Courier</option>
                        <option value="item_received">Item Received at Atelier Quality Inspection</option>
                        <option value="refund_processed">Refund Processed / Settled</option>
                        <option value="rejected">Rejected (Does Not Meet Policy)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Atelier Staff Remarks / Tracking Notes</label>
                    <textarea name="admin_remarks" id="returnRemarks" class="form-control" rows="3" placeholder="Inspection notes, courier details, or reason for decision..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold btn-sm">Update Return Dossier</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-update-return').forEach(btn => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-id');
            const status = this.getAttribute('data-status');
            const remarks = this.getAttribute('data-remarks') || '';

            document.getElementById('returnModalId').value = id;
            document.getElementById('returnModalTitle').textContent = 'Manage Return #RET-' + id.padStart(5, '0');
            document.getElementById('returnSelectStatus').value = status;
            document.getElementById('returnRemarks').value = remarks;

            openModal('returnModal');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

