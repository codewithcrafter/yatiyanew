<?php
/**
 * YATIYA COUTURE - Atelier Inventory & Stock Management
 */

$adminPageTitle = 'Inventory & Stock Management';
$activeMenu = 'inventory';

require_once __DIR__ . '/includes/admin-header.php';

// Handle Stock Adjustment POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/inventory.php');
    }

    $productId = (int)($_POST['product_id'] ?? 0);
    $variantId = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
    $adjustmentQty = (int)($_POST['adjustment_qty'] ?? 0);
    $movementType = clean_input($_POST['movement_type'] ?? 'adjustment');
    $note = clean_input($_POST['note'] ?? 'Manual stock adjustment');
    $adminId = (int)$currentAdmin['id'];

    if ($adjustmentQty === 0) {
        set_flash('error', 'Adjustment quantity cannot be zero.');
    } else {
        $adjRes = admin_adjust_stock($productId, $variantId, $adjustmentQty, $movementType, $note, $adminId);
        if ($adjRes['success']) {
            set_flash('success', $adjRes['message'] . " New stock balance: {$adjRes['new_balance']}.");
        } else {
            set_flash('error', $adjRes['message']);
        }
    }
    redirect(ADMIN_URL . '/inventory.php');
}

// Filters & Pagination
$filter = clean_input($_GET['filter'] ?? '');
$search = clean_input($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$filters = [
    'filter' => $filter,
    'search' => $search
];

$inventoryData = admin_get_inventory($filters, $page, 15);
?>

<!-- Filter Bar -->
<div class="filter-bar">
    <form action="<?= ADMIN_URL ?>/inventory.php" method="GET" class="filter-group">
        <input 
            type="search" 
            name="search" 
            class="form-control" 
            placeholder="Search creation name or SKU..." 
            value="<?= e($search) ?>" 
            style="max-width: 260px;"
        >

        <select name="filter" class="form-control" style="max-width: 180px;">
            <option value="">All Inventory Levels</option>
            <option value="low_stock" <?= ($filter === 'low_stock') ? 'selected' : '' ?>>Low Stock Only</option>
            <option value="out_of_stock" <?= ($filter === 'out_of_stock') ? 'selected' : '' ?>>Out of Stock Only</option>
        </select>

        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
        <?php if (!empty($search) || !empty($filter)): ?>
            <a href="<?= ADMIN_URL ?>/inventory.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>

    <button type="button" class="btn btn-gold btn-sm" data-modal-target="adjustStockModal">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="12" y1="5" x2="12" y2="19"></line>
            <line x1="5" y1="12" x2="19" y2="12"></line>
        </svg>
        <span>Record Stock Movement</span>
    </button>
</div>

<!-- Inventory Data Table -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Stock Overview (<?= number_format($inventoryData['total']) ?> Items)</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($inventoryData['items'])): ?>
            <div style="padding: 48px; text-align: center; color: var(--text-muted);">
                <p style="font-size: 14px; font-weight: 500;">No inventory records match the query.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Creation / Master SKU</th>
                            <th>Category</th>
                            <th>Total Stock</th>
                            <th>Threshold</th>
                            <th>Variant Breakdown</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventoryData['items'] as $item): ?>
                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <img src="<?= e($item['primary_image'] ?: 'assets/images/placeholder.jpg') ?>" class="product-thumb" alt="">
                                        <div>
                                            <div style="font-weight: 600; font-size: 13px;"><?= e($item['name']) ?></div>
                                            <div class="product-cell-sku">SKU: <?= e($item['sku']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($item['category_name'] ?: 'Uncategorized') ?></td>
                                <td>
                                    <span style="font-size: 15px; font-weight: 700; color: <?= ($item['stock_quantity'] == 0) ? '#D32F2F' : (($item['stock_quantity'] <= $item['low_stock_threshold']) ? '#ED6C02' : 'inherit') ?>;">
                                        <?= $item['stock_quantity'] ?> units
                                    </span>
                                </td>
                                <td><?= $item['low_stock_threshold'] ?> min</td>
                                <td>
                                    <?php if (empty($item['variants'])): ?>
                                        <span style="color: var(--text-muted); font-size: 11px;">Single piece</span>
                                    <?php else: ?>
                                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                            <?php foreach ($item['variants'] as $v): ?>
                                                <span style="display: inline-block; padding: 2px 6px; background: #FAF8F5; border: 1px solid var(--border-color); border-radius: 3px; font-size: 11px;">
                                                    <strong><?= e($v['size']) ?></strong> / <?= e($v['color']) ?>: 
                                                    <span style="font-weight: 600; color: <?= ($v['stock_quantity'] == 0) ? '#D32F2F' : 'inherit' ?>;"><?= $v['stock_quantity'] ?></span>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= ($item['stock_quantity'] == 0) ? 'archived' : (($item['stock_quantity'] <= $item['low_stock_threshold']) ? 'pending' : 'active') ?>">
                                        <?= ($item['stock_quantity'] == 0) ? 'Out of Stock' : (($item['stock_quantity'] <= $item['low_stock_threshold']) ? 'Low Stock' : 'Optimal') ?>
                                    </span>
                                </td>
                                <td>
                                    <button 
                                        type="button" 
                                        class="btn btn-outline btn-sm btn-quick-adjust" 
                                        data-product-id="<?= $item['id'] ?>" 
                                        data-product-name="<?= e($item['name']) ?>"
                                        data-modal-target="adjustStockModal"
                                    >
                                        Adjust
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Numbered Pagination -->
            <?php if ($inventoryData['total_pages'] > 1): ?>
                <div style="padding: 16px 24px;">
                    <div class="pagination">
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Showing page <?= $inventoryData['page'] ?> of <?= $inventoryData['total_pages'] ?>
                        </div>
                        <div class="pagination-pages">
                            <?php for ($p = 1; $p <= $inventoryData['total_pages']; $p++): ?>
                                <a href="<?= ADMIN_URL ?>/inventory.php?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>" 
                                   class="page-btn <?= ($p === $inventoryData['page']) ? 'active' : '' ?>">
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

<!-- Stock Adjustment Modal Dialog -->
<div class="modal-backdrop" id="adjustStockModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Record Stock Movement / Adjustment</h3>
            <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/inventory.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label" for="modalProdId">Select Creation *</label>
                    <select id="modalProdId" name="product_id" class="form-control" required>
                        <option value="">Select Creation...</option>
                        <?php 
                        $allProds = db()->query("SELECT id, name, sku, stock_quantity FROM products WHERE status = 'active' ORDER BY name ASC")->fetchAll();
                        foreach ($allProds as $p): 
                        ?>
                            <option value="<?= $p['id'] ?>">
                                <?= e($p['name']) ?> (<?= e($p['sku']) ?> &bull; Current: <?= $p['stock_quantity'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label" for="modalMovementType">Movement Reason *</label>
                        <select id="modalMovementType" name="movement_type" class="form-control" required>
                            <option value="adjustment">Manual Audit Adjustment</option>
                            <option value="purchase">Restock / New Purchase Shipment</option>
                            <option value="return_restock">Patron Privilege Return Restock</option>
                            <option value="order_cancel">Order Cancellation Restock</option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="modalAdjQty">Quantity Adjustment *</label>
                        <input type="number" id="modalAdjQty" name="adjustment_qty" class="form-control" placeholder="+10 or -5" required>
                        <div style="font-size: 11px; color: var(--text-muted); margin-top: 4px;">Positive to add stock, negative to reduce</div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 16px; margin-bottom: 0;">
                    <label class="form-label" for="modalNote">Reason / Reference Note *</label>
                    <input type="text" id="modalNote" name="note" class="form-control" placeholder="e.g. PO-2026-NOV-01 / Physical inventory count audit" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold">Confirm Movement</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Quick adjust button pre-populates product in modal
    document.querySelectorAll('.btn-quick-adjust').forEach(btn => {
        btn.addEventListener('click', function () {
            const prodId = this.getAttribute('data-product-id');
            const select = document.getElementById('modalProdId');
            if (select && prodId) {
                select.value = prodId;
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

