<?php
/**
 * YATIYA COUTURE - Luxury Product Catalogue Management
 */

$adminPageTitle = 'Creations & Catalogue';
$activeMenu = 'products';

require_once __DIR__ . '/includes/admin-header.php';

// Handle Action Requests (Status Toggle, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/products.php');
    }

    $action = $_POST['action'] ?? '';
    $productId = (int)($_POST['product_id'] ?? 0);

    if ($action === 'toggle_status') {
        $newStatus = clean_input($_POST['new_status'] ?? 'draft');
        if (admin_toggle_product_status($productId, $newStatus)) {
            set_flash('success', "Product status changed to '$newStatus'.");
        } else {
            set_flash('error', 'Failed to update product status.');
        }
        redirect(ADMIN_URL . '/products.php');
    }

    if ($action === 'delete') {
        $delRes = admin_delete_product($productId);
        if ($delRes['success']) {
            set_flash('success', $delRes['message']);
        } else {
            set_flash('error', $delRes['message']);
        }
        redirect(ADMIN_URL . '/products.php');
    }
}

// Filters & Pagination
$search = clean_input($_GET['search'] ?? '');
$categoryId = !empty($_GET['category_id']) ? (int)$_GET['category_id'] : null;
$brandId = !empty($_GET['brand_id']) ? (int)$_GET['brand_id'] : null;
$status = clean_input($_GET['status'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$filters = [
    'search' => $search,
    'category_id' => $categoryId,
    'brand_id' => $brandId,
    'status' => $status
];

$productData = admin_get_products($filters, $page, 15);
$categories = admin_get_categories();
$brands = admin_get_brands();
?>

<!-- Action Bar & Filter Bar -->
<div class="filter-bar">
    <form action="<?= ADMIN_URL ?>/products.php" method="GET" class="filter-group" style="flex: 1;">
        <input 
            type="search" 
            name="search" 
            class="form-control" 
            placeholder="Search creation name, SKU or slug..." 
            value="<?= e($search) ?>" 
            style="max-width: 260px;"
        >

        <select name="category_id" class="form-control" style="max-width: 180px;">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($categoryId === (int)$cat['id']) ? 'selected' : '' ?>>
                    <?= e($cat['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="brand_id" class="form-control" style="max-width: 160px;">
            <option value="">All Lines</option>
            <?php foreach ($brands as $b): ?>
                <option value="<?= $b['id'] ?>" <?= ($brandId === (int)$b['id']) ? 'selected' : '' ?>>
                    <?= e($b['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="status" class="form-control" style="max-width: 140px;">
            <option value="">All Statuses</option>
            <option value="active" <?= ($status === 'active') ? 'selected' : '' ?>>Active</option>
            <option value="draft" <?= ($status === 'draft') ? 'selected' : '' ?>>Draft</option>
            <option value="archived" <?= ($status === 'archived') ? 'selected' : '' ?>>Archived</option>
        </select>

        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
        <?php if (!empty($search) || !empty($categoryId) || !empty($brandId) || !empty($status)): ?>
            <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>

    <div>
        <a href="<?= ADMIN_URL ?>/product-form.php" class="btn btn-gold">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            <span>Add New Creation</span>
        </a>
    </div>
</div>

<!-- Products Table Card -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">Atelier Catalogue (<?= number_format($productData['total']) ?> Pieces)</h2>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($productData['items'])): ?>
            <div style="padding: 48px; text-align: center; color: var(--text-muted);">
                <p style="font-size: 14px; font-weight: 500;">No creations found matching the selected filters.</p>
                <a href="<?= ADMIN_URL ?>/product-form.php" class="btn btn-gold btn-sm" style="margin-top: 12px;">Create First Piece</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Creation & SKU</th>
                            <th>Category</th>
                            <th>Pricing</th>
                            <th>Stock</th>
                            <th>Variants</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($productData['items'] as $item): ?>
                            <tr>
                                <td>
                                    <div class="product-cell">
                                        <img src="<?= e($item['primary_image'] ?: 'assets/images/placeholder.jpg') ?>" class="product-thumb" alt="">
                                        <div>
                                            <a href="<?= ADMIN_URL ?>/product-form.php?id=<?= $item['id'] ?>" class="product-cell-name">
                                                <?= e($item['name']) ?>
                                            </a>
                                            <div class="product-cell-sku">SKU: <?= e($item['sku']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500;"><?= e($item['category_name'] ?: 'Uncategorized') ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($item['brand_name'] ?: 'Yatiya Atelier') ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 700; color: var(--text-main);"><?= format_price($item['selling_price']) ?></div>
                                    <?php if ($item['original_price'] > $item['selling_price']): ?>
                                        <div style="font-size: 11px; text-decoration: line-through; color: var(--text-muted);">
                                            <?= format_price($item['original_price']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= ($item['stock_quantity'] == 0) ? 'archived' : (($item['stock_quantity'] <= $item['low_stock_threshold']) ? 'pending' : 'active') ?>">
                                        <?= $item['stock_quantity'] ?> in stock
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 12px; font-weight: 600;"><?= (int)$item['variant_count'] ?></span>
                                </td>
                                <td>
                                    <span class="status-badge <?= e($item['status']) ?>">
                                        <?= ucfirst(e($item['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <!-- Edit -->
                                        <a href="<?= ADMIN_URL ?>/product-form.php?id=<?= $item['id'] ?>" class="btn-action" title="Edit Creation">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </a>

                                        <!-- Preview Public Product Page -->
                                        <a href="<?= BASE_URL ?>/product.php?slug=<?= urlencode($item['slug']) ?>" target="_blank" class="btn-action" title="View in Boutique">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                                <circle cx="12" cy="12" r="3"></circle>
                                            </svg>
                                        </a>

                                        <!-- Toggle Status Form -->
                                        <form action="<?= ADMIN_URL ?>/products.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                            <input type="hidden" name="new_status" value="<?= ($item['status'] === 'active') ? 'draft' : 'active' ?>">
                                            <button type="submit" class="btn-action" title="<?= ($item['status'] === 'active') ? 'Deactivate (Draft)' : 'Activate' ?>">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <?php if ($item['status'] === 'active'): ?>
                                                        <rect x="6" y="4" width="4" height="16"></rect>
                                                        <rect x="14" y="4" width="4" height="16"></rect>
                                                    <?php else: ?>
                                                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                                                    <?php endif; ?>
                                                </svg>
                                            </button>
                                        </form>

                                        <!-- Delete / Archive Form -->
                                        <form action="<?= ADMIN_URL ?>/products.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn-action danger" title="Delete or Archive Product" data-confirm="Are you sure you want to delete or archive this creation?">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
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
            <?php if ($productData['total_pages'] > 1): ?>
                <div style="padding: 16px 24px;">
                    <div class="pagination">
                        <div style="font-size: 12px; color: var(--text-muted);">
                            Showing page <?= $productData['page'] ?> of <?= $productData['total_pages'] ?>
                        </div>
                        <div class="pagination-pages">
                            <?php for ($p = 1; $p <= $productData['total_pages']; $p++): ?>
                                <a href="<?= ADMIN_URL ?>/products.php?<?= http_build_query(array_merge($filters, ['page' => $p])) ?>" 
                                   class="page-btn <?= ($p === $productData['page']) ? 'active' : '' ?>">
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

