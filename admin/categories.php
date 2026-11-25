<?php
/**
 * YATIYA COUTURE - Category Salon Management
 */

$adminPageTitle = 'Category Salons';
$activeMenu = 'categories';

require_once __DIR__ . '/includes/admin-header.php';

// Handle Category Save / Delete POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/categories.php');
    }

    $action = $_POST['action'] ?? '';
    $adminId = (int)$currentAdmin['id'];

    if ($action === 'save_category') {
        $catId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        $data = [
            'name' => clean_input($_POST['name'] ?? ''),
            'slug' => clean_input($_POST['slug'] ?? ''),
            'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
            'description' => clean_input($_POST['description'] ?? ''),
            'image_url' => clean_input($_POST['image_url'] ?? ''),
            'banner_url' => clean_input($_POST['banner_url'] ?? ''),
            'seo_title' => clean_input($_POST['seo_title'] ?? ''),
            'seo_description' => clean_input($_POST['seo_description'] ?? ''),
            'display_order' => (int)($_POST['display_order'] ?? 0),
            'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0
        ];

        $saveRes = admin_save_category($data, $catId, $adminId);
        if ($saveRes['success']) {
            set_flash('success', $saveRes['message']);
        } else {
            set_flash('error', $saveRes['message']);
        }
    } elseif ($action === 'delete_category') {
        $catId = (int)($_POST['category_id'] ?? 0);
        $delRes = admin_delete_category($catId, $adminId);
        if ($delRes['success']) {
            set_flash('success', $delRes['message']);
        } else {
            set_flash('error', $delRes['message']);
        }
    }

    redirect(ADMIN_URL . '/categories.php');
}

$categories = admin_get_categories();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 style="font-family: var(--font-heading); font-size: 20px; font-weight: 600;">
            Category Salons & Hierarchy (<?= count($categories) ?> categories)
        </h2>
        <div style="font-size: 12px; color: var(--text-muted);">
            Define department salons, artisanal collections, and navigation categories.
        </div>
    </div>
    <button type="button" class="btn btn-gold btn-sm" data-modal-target="categoryModal" id="btnCreateCategory">
        + Add New Category
    </button>
</div>

<!-- Categories Table Card -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Parent Salon</th>
                        <th>URL Slug</th>
                        <th>Order</th>
                        <th>Featured</th>
                        <th>Creations</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td>
                                <strong style="font-size: 13px;"><?= e($cat['name']) ?></strong>
                            </td>
                            <td>
                                <?= e($cat['parent_name'] ?: 'Root Category') ?>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-size: 12px; color: var(--text-muted);"><?= e($cat['slug']) ?></span>
                            </td>
                            <td><?= (int)$cat['display_order'] ?></td>
                            <td>
                                <?= $cat['is_featured'] ? '<span class="status-badge active" style="font-size:10px;">Featured</span>' : '&ndash;' ?>
                            </td>
                            <td>
                                <span style="font-weight: 600;"><?= (int)$cat['product_count'] ?></span>
                            </td>
                            <td>
                                <span class="status-badge <?= $cat['is_active'] ? 'active' : 'archived' ?>">
                                    <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-group">
                                    <button 
                                        type="button" 
                                        class="btn-action btn-edit-category" 
                                        data-category='<?= htmlspecialchars(json_encode($cat), ENT_QUOTES, 'UTF-8') ?>'
                                        title="Edit Category"
                                    >
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>

                                    <?php if ($cat['product_count'] == 0): ?>
                                        <form action="<?= ADMIN_URL ?>/categories.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete_category">
                                            <input type="hidden" name="category_id" value="<?= $cat['id'] ?>">
                                            <button type="submit" class="btn-action danger" title="Delete Empty Category" data-confirm="Are you sure you want to delete this category?">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polyline points="3 6 5 6 21 6"></polyline>
                                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Category Create / Edit Modal Dialog -->
<div class="modal-backdrop" id="categoryModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="catModalTitle">Add New Category</h3>
            <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/categories.php" method="POST" id="catForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_category">
            <input type="hidden" name="category_id" id="formCatId" value="">

            <div class="modal-body">
                <div class="form-row">
                    <div>
                        <label class="form-label" for="catName">Category Name *</label>
                        <input type="text" id="catName" name="name" class="form-control" placeholder="e.g. Silk Saris" required>
                    </div>
                    <div>
                        <label class="form-label" for="catSlug">URL Slug</label>
                        <input type="text" id="catSlug" name="slug" class="form-control" placeholder="Auto-generated if empty">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label" for="parentCat">Parent Category (Optional)</label>
                        <select id="parentCat" name="parent_id" class="form-control">
                            <option value="">None (Top-Level Category)</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label" for="displayOrder">Display Sort Order</label>
                        <input type="number" id="displayOrder" name="display_order" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label class="form-label" for="catDesc">Description</label>
                    <textarea id="catDesc" name="description" class="form-control" rows="2" placeholder="Editorial description for shop header..."></textarea>
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label" for="imgUrl">Category Image URL</label>
                        <input type="url" id="imgUrl" name="image_url" class="form-control" placeholder="https://images.unsplash.com/...">
                    </div>
                    <div>
                        <label class="form-label" for="bannerUrl">Category Banner URL</label>
                        <input type="url" id="bannerUrl" name="banner_url" class="form-control" placeholder="https://images.unsplash.com/...">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label" for="catSeoTitle">SEO Title</label>
                        <input type="text" id="catSeoTitle" name="seo_title" class="form-control" placeholder="Luxury Silk Saris & Drapes | Yatiya Couture">
                    </div>
                    <div>
                        <label class="form-label" for="catSeoDesc">SEO Description</label>
                        <input type="text" id="catSeoDesc" name="seo_description" class="form-control" placeholder="Meta description for search engine listings...">
                    </div>
                </div>

                <div style="margin-top: 16px; display: flex; gap: 24px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" id="catFeatured" name="is_featured" value="1">
                        <strong>Feature on Homepage</strong>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" id="catActive" name="is_active" value="1" checked>
                        <strong>Active Status</strong>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold">Save Category</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('catForm');
    const title = document.getElementById('catModalTitle');

    document.getElementById('btnCreateCategory')?.addEventListener('click', function () {
        form.reset();
        document.getElementById('formCatId').value = '';
        title.textContent = 'Add New Category';
    });

    document.querySelectorAll('.btn-edit-category').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = JSON.parse(this.getAttribute('data-category'));
            document.getElementById('formCatId').value = d.id;
            document.getElementById('catName').value = d.name;
            document.getElementById('catSlug').value = d.slug;
            document.getElementById('parentCat').value = d.parent_id || '';
            document.getElementById('displayOrder').value = d.display_order;
            document.getElementById('catDesc').value = d.description || '';
            document.getElementById('imgUrl').value = d.image_url || '';
            document.getElementById('bannerUrl').value = d.banner_url || '';
            document.getElementById('catSeoTitle').value = d.seo_title || '';
            document.getElementById('catSeoDesc').value = d.seo_description || '';
            document.getElementById('catFeatured').checked = (parseInt(d.is_featured) === 1);
            document.getElementById('catActive').checked = (parseInt(d.is_active) === 1);

            title.textContent = 'Edit Category: ' + d.name;
            openModal('categoryModal');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

