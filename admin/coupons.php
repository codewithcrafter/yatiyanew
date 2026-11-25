<?php
/**
 * YATIYA COUTURE - Privilege Coupon & Promotional Campaign Management
 */

$adminPageTitle = 'Privilege Coupons';
$activeMenu = 'coupons';

require_once __DIR__ . '/includes/admin-header.php';

// Handle Coupon Save / Toggle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/coupons.php');
    }

    $action = $_POST['action'] ?? '';
    $adminId = (int)$currentAdmin['id'];

    if ($action === 'save_coupon') {
        $couponId = !empty($_POST['coupon_id']) ? (int)$_POST['coupon_id'] : null;
        $data = [
            'code' => clean_input($_POST['code'] ?? ''),
            'description' => clean_input($_POST['description'] ?? ''),
            'discount_type' => clean_input($_POST['discount_type'] ?? 'percentage'),
            'discount_value' => (float)($_POST['discount_value'] ?? 0),
            'min_spend' => (float)($_POST['min_spend'] ?? 0),
            'max_discount' => !empty($_POST['max_discount']) ? (float)$_POST['max_discount'] : null,
            'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
            'usage_limit' => !empty($_POST['usage_limit']) ? (int)$_POST['usage_limit'] : null,
            'per_customer_limit' => max(1, (int)($_POST['per_customer_limit'] ?? 1)),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0
        ];

        $saveRes = admin_save_coupon($data, $couponId, $adminId);
        if ($saveRes['success']) {
            set_flash('success', $saveRes['message']);
        } else {
            set_flash('error', $saveRes['message']);
        }
    } elseif ($action === 'toggle_status') {
        $couponId = (int)($_POST['coupon_id'] ?? 0);
        $isActive = !empty($_POST['is_active']);
        if (admin_toggle_coupon_status($couponId, $isActive, $adminId)) {
            set_flash('success', "Coupon status " . ($isActive ? 'activated' : 'deactivated') . ".");
        } else {
            set_flash('error', 'Failed to toggle coupon status.');
        }
    }

    redirect(ADMIN_URL . '/coupons.php');
}

$page = max(1, (int)($_GET['page'] ?? 1));
$couponData = admin_get_coupons($page, 20);
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 style="font-family: var(--font-heading); font-size: 20px; font-weight: 600;">
            Privilege Coupons (<?= $couponData['total'] ?> campaigns)
        </h2>
        <div style="font-size: 12px; color: var(--text-muted);">
            Manage luxury client invitation codes and promotional privilege discounts.
        </div>
    </div>
    <button type="button" class="btn btn-gold btn-sm" data-modal-target="couponModal" id="btnCreateCoupon">
        + Create New Coupon
    </button>
</div>

<!-- Coupons Table Card -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($couponData['items'])): ?>
            <div style="padding: 48px; text-align: center; color: var(--text-muted);">
                <p style="font-size: 14px; font-weight: 500;">No promotional coupons registered.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Coupon Code</th>
                            <th>Discount</th>
                            <th>Minimum Spend</th>
                            <th>Max Discount</th>
                            <th>Redemptions</th>
                            <th>Validity Period</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($couponData['items'] as $cp): ?>
                            <tr>
                                <td>
                                    <div style="font-family: monospace; font-size: 14px; font-weight: 700; color: var(--text-main);">
                                        <?= e($cp['code']) ?>
                                    </div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($cp['description']) ?></div>
                                </td>
                                <td>
                                    <span style="font-weight: 700; color: var(--gold);">
                                        <?= ($cp['discount_type'] === 'percentage') ? ((float)$cp['discount_value'] . '%') : format_price($cp['discount_value']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= ($cp['min_spend'] > 0) ? format_price($cp['min_spend']) : 'No min' ?>
                                </td>
                                <td>
                                    <?= ($cp['max_discount'] > 0) ? format_price($cp['max_discount']) : 'Uncapped' ?>
                                </td>
                                <td>
                                    <span style="font-weight: 600;"><?= (int)$cp['usage_count'] ?></span>
                                    <?php if (!empty($cp['usage_limit'])): ?>
                                        <span style="color: var(--text-muted); font-size: 11px;">/ <?= (int)$cp['usage_limit'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 11px; color: var(--text-muted); white-space: nowrap;">
                                    <?php if (!empty($cp['start_date']) || !empty($cp['end_date'])): ?>
                                        <?= !empty($cp['start_date']) ? date('d M Y', strtotime($cp['start_date'])) : 'Anytime' ?> &rarr; 
                                        <?= !empty($cp['end_date']) ? date('d M Y', strtotime($cp['end_date'])) : 'Permanent' ?>
                                    <?php else: ?>
                                        Permanent
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $cp['is_active'] ? 'active' : 'archived' ?>">
                                        <?= $cp['is_active'] ? 'Active' : 'Disabled' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <!-- Edit button triggers pre-filled modal -->
                                        <button 
                                            type="button" 
                                            class="btn-action btn-edit-coupon" 
                                            data-coupon='<?= htmlspecialchars(json_encode($cp), ENT_QUOTES, 'UTF-8') ?>'
                                            title="Edit Coupon"
                                        >
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>

                                        <!-- Toggle Status Form -->
                                        <form action="<?= ADMIN_URL ?>/coupons.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="coupon_id" value="<?= $cp['id'] ?>">
                                            <input type="hidden" name="is_active" value="<?= $cp['is_active'] ? '0' : '1' ?>">
                                            <button type="submit" class="btn-action <?= $cp['is_active'] ? 'danger' : '' ?>" title="<?= $cp['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <?php if ($cp['is_active']): ?>
                                                        <rect x="6" y="4" width="4" height="16"></rect>
                                                        <rect x="14" y="4" width="4" height="16"></rect>
                                                    <?php else: ?>
                                                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
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
        <?php endif; ?>
    </div>
</div>

<!-- Coupon Create / Edit Modal Dialog -->
<div class="modal-backdrop" id="couponModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="couponModalTitle">Create Privilege Coupon</h3>
            <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/coupons.php" method="POST" id="couponForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_coupon">
            <input type="hidden" name="coupon_id" id="formCouponId" value="">

            <div class="modal-body">
                <div class="form-row">
                    <div>
                        <label class="form-label" for="couponCode">Coupon Code *</label>
                        <input type="text" id="couponCode" name="code" class="form-control" placeholder="e.g. LUXURY2026" required style="text-transform: uppercase;">
                    </div>
                    <div>
                        <label class="form-label" for="discountType">Discount Type</label>
                        <select id="discountType" name="discount_type" class="form-control">
                            <option value="percentage">Percentage Off (%)</option>
                            <option value="fixed">Fixed Amount Off (₹)</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label" for="discountValue">Discount Value *</label>
                        <input type="number" id="discountValue" name="discount_value" step="0.01" min="0.01" class="form-control" placeholder="10 or 500" required>
                    </div>
                    <div>
                        <label class="form-label" for="minSpend">Minimum Order Spend (₹)</label>
                        <input type="number" id="minSpend" name="min_spend" step="0.01" min="0" class="form-control" value="0.00">
                    </div>
                    <div>
                        <label class="form-label" for="maxDiscount">Maximum Discount Cap (₹)</label>
                        <input type="number" id="maxDiscount" name="max_discount" step="0.01" min="0" class="form-control" placeholder="Optional cap">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label class="form-label" for="couponDesc">Description / Campaign Purpose</label>
                    <input type="text" id="couponDesc" name="description" class="form-control" placeholder="10% off inaugural atelier acquisitions">
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label" for="startDate">Starts At (Optional)</label>
                        <input type="datetime-local" id="startDate" name="start_date" class="form-control">
                    </div>
                    <div>
                        <label class="form-label" for="endDate">Expires At (Optional)</label>
                        <input type="datetime-local" id="endDate" name="end_date" class="form-control">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label" for="usageLimit">Total Usage Limit</label>
                        <input type="number" id="usageLimit" name="usage_limit" min="1" class="form-control" placeholder="Unlimited if empty">
                    </div>
                    <div>
                        <label class="form-label" for="perCustomerLimit">Limit Per Client</label>
                        <input type="number" id="perCustomerLimit" name="per_customer_limit" min="1" class="form-control" value="1">
                    </div>
                    <div style="display:flex;align-items:center;padding-top:20px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;">
                            <input type="checkbox" id="couponActive" name="is_active" value="1" checked>
                            <strong>Active Coupon</strong>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold">Save Privilege Campaign</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('couponModal');
    const form = document.getElementById('couponForm');
    const title = document.getElementById('couponModalTitle');

    // Create button reset
    document.getElementById('btnCreateCoupon')?.addEventListener('click', function () {
        form.reset();
        document.getElementById('formCouponId').value = '';
        title.textContent = 'Create Privilege Coupon';
    });

    // Edit button click handler
    document.querySelectorAll('.btn-edit-coupon').forEach(btn => {
        btn.addEventListener('click', function () {
            const data = JSON.parse(this.getAttribute('data-coupon'));
            document.getElementById('formCouponId').value = data.id;
            document.getElementById('couponCode').value = data.code;
            document.getElementById('discountType').value = data.discount_type;
            document.getElementById('discountValue').value = data.discount_value;
            document.getElementById('minSpend').value = data.min_spend;
            document.getElementById('maxDiscount').value = data.max_discount || '';
            document.getElementById('couponDesc').value = data.description || '';
            document.getElementById('startDate').value = data.start_date ? data.start_date.replace(' ', 'T').substring(0, 16) : '';
            document.getElementById('endDate').value = data.end_date ? data.end_date.replace(' ', 'T').substring(0, 16) : '';
            document.getElementById('usageLimit').value = data.usage_limit || '';
            document.getElementById('perCustomerLimit').value = data.per_customer_limit;
            document.getElementById('couponActive').checked = (parseInt(data.is_active) === 1);

            title.textContent = 'Edit Coupon: ' + data.code;
            openModal('couponModal');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

