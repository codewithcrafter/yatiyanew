<?php
/**
 * YATIYA COUTURE - Luxury Collection Management
 * Manage haute couture capsules, seasonal edits, and associated creations.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

require_admin();

$currentAdmin = current_admin();
$adminId = (int)$currentAdmin['id'];
$adminPageTitle = 'Curated Collections';
$adminPageSubtitle = 'Manage luxury couture capsules, wedding salons, and seasonal edits.';
$activeMenu = 'collections';

// Handle AJAX get assigned products
if (!empty($_GET['action']) && $_GET['action'] === 'get_assigned') {
    header('Content-Type: application/json');
    $colId = (int)($_GET['col_id'] ?? 0);
    $products = admin_get_collection_products($colId);
    echo json_encode(array_map('intval', array_column($products, 'id')));
    exit;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/collections.php');
    }

    $action = clean_input($_POST['action'] ?? '');

    // 1. Save Collection
    if ($action === 'save_collection') {
        $colId = !empty($_POST['collection_id']) ? (int)$_POST['collection_id'] : null;
        $coverUrl = clean_input($_POST['cover_image'] ?? '');
        $bannerUrl = clean_input($_POST['banner_image'] ?? '');

        // Upload Cover Image
        if (!empty($_FILES['cover_file']['name'])) {
            $up = admin_validate_image_upload($_FILES['cover_file'], 'banners');
            if ($up['success']) $coverUrl = $up['url'];
        }

        // Upload Banner Image
        if (!empty($_FILES['banner_file']['name'])) {
            $up2 = admin_validate_image_upload($_FILES['banner_file'], 'banners');
            if ($up2['success']) $bannerUrl = $up2['url'];
        }

        $data = [
            'name' => clean_input($_POST['name'] ?? ''),
            'slug' => clean_input($_POST['slug'] ?? ''),
            'tagline' => clean_input($_POST['tagline'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'cover_image' => $coverUrl,
            'banner_image' => $bannerUrl,
            'seo_title' => clean_input($_POST['seo_title'] ?? ''),
            'seo_description' => clean_input($_POST['seo_description'] ?? ''),
            'display_order' => (int)($_POST['display_order'] ?? 0),
            'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
            'is_active' => !empty($_POST['is_active']) ? 1 : 0
        ];

        $saveRes = admin_save_collection($data, $colId, $adminId);
        if ($saveRes['success']) {
            set_flash('success', $saveRes['message']);
        } else {
            set_flash('error', $saveRes['message']);
        }
        redirect(ADMIN_URL . '/collections.php');
    }

    // 2. Delete Collection
    if ($action === 'delete_collection') {
        $colId = (int)($_POST['collection_id'] ?? 0);
        $delRes = admin_delete_collection($colId, $adminId);
        if ($delRes['success']) {
            set_flash('success', $delRes['message']);
        } else {
            set_flash('error', $delRes['message']);
        }
        redirect(ADMIN_URL . '/collections.php');
    }

    // 3. Assign Products to Collection
    if ($action === 'assign_products') {
        $colId = (int)($_POST['collection_id'] ?? 0);
        $prodIds = !empty($_POST['product_ids']) && is_array($_POST['product_ids']) ? $_POST['product_ids'] : [];
        if ($colId > 0) {
            admin_assign_collection_products($colId, $prodIds);
            set_flash('success', 'Associated creations updated for this collection.');
        }
        redirect(ADMIN_URL . '/collections.php');
    }
}

// Fetch Collections
$collections = admin_get_collections();
$conciseProducts = admin_get_all_products_concise();

$headerActions = '
    <button type="button" class="btn btn-gold btn-sm" data-modal-target="collectionModal" id="btnCreateCollection">
        + Create New Collection
    </button>
';

require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-body" style="padding: 0;">
        <?php if (empty($collections)): ?>
            <div style="padding: 48px; text-align: center; color: var(--text-muted);">
                <p style="font-size: 14px;">No collections defined yet.</p>
                <button type="button" class="btn btn-gold btn-sm" style="margin-top: 12px;" data-modal-target="collectionModal">
                    Create First Collection
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Cover</th>
                            <th>Collection</th>
                            <th>URL Slug</th>
                            <th>Creations</th>
                            <th>Order</th>
                            <th>Featured</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($collections as $col): ?>
                            <tr>
                                <td>
                                    <img src="<?= e($col['cover_image'] ?: 'assets/images/placeholder.jpg') ?>" alt="" style="width: 48px; height: 60px; object-fit: cover; border-radius: 2px;">
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 13px; color: var(--text-main);"><?= e($col['name']) ?></div>
                                    <div style="font-size: 11px; color: var(--text-muted);"><?= e($col['tagline']) ?></div>
                                </td>
                                <td>
                                    <code style="color: var(--gold); font-size: 12px;"><?= e($col['slug']) ?></code>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-outline btn-sm btn-manage-products" data-collection-id="<?= $col['id'] ?>" data-collection-name="<?= e($col['name']) ?>" style="font-size: 11px; padding: 3px 8px;">
                                        <?= (int)$col['product_count'] ?> Pieces &rarr;
                                    </button>
                                </td>
                                <td><?= (int)$col['display_order'] ?></td>
                                <td>
                                    <?= $col['is_featured'] ? '<span class="status-badge active" style="font-size:10px;">Featured</span>' : '&ndash;' ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $col['is_active'] ? 'active' : 'archived' ?>">
                                        <?= $col['is_active'] ? 'Active' : 'Inactive' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <button 
                                            type="button" 
                                            class="btn-action btn-edit-collection" 
                                            data-collection='<?= htmlspecialchars(json_encode($col), ENT_QUOTES, 'UTF-8') ?>'
                                            title="Edit Collection"
                                        >
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>

                                        <form action="<?= ADMIN_URL ?>/collections.php" method="POST" style="margin: 0; display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_collection">
                                            <input type="hidden" name="collection_id" value="<?= $col['id'] ?>">
                                            <button type="submit" class="btn-action danger" title="Delete Collection" data-confirm="Are you sure you want to delete this collection?">
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
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Add / Edit Collection -->
<div class="modal-backdrop" id="collectionModal">
    <div class="modal-dialog" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title" id="colModalTitle">Add New Collection</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/collections.php" method="POST" enctype="multipart/form-data" id="colForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_collection">
            <input type="hidden" name="collection_id" id="formColId" value="">

            <div class="modal-body">
                <div class="form-row">
                    <div>
                        <label class="form-label">Collection Name *</label>
                        <input type="text" id="colName" name="name" class="form-control" placeholder="e.g. The Royal Wedding Salon" required>
                    </div>
                    <div>
                        <label class="form-label">URL Slug</label>
                        <input type="text" id="colSlug" name="slug" class="form-control" placeholder="royal-wedding-salon">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label class="form-label">Tagline / Eyebrow</label>
                    <input type="text" id="colTagline" name="tagline" class="form-control" placeholder="e.g. Bridal & Groom Imperial Ensembles">
                </div>

                <div class="form-group">
                    <label class="form-label">Editorial Description</label>
                    <textarea id="colDesc" name="description" class="form-control" rows="2"></textarea>
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label">Cover Image URL</label>
                        <input type="url" id="colCover" name="cover_image" class="form-control" placeholder="https://...">
                    </div>
                    <div>
                        <label class="form-label">Or Upload Cover File</label>
                        <input type="file" name="cover_file" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label">Banner Image URL</label>
                        <input type="url" id="colBanner" name="banner_image" class="form-control" placeholder="https://...">
                    </div>
                    <div>
                        <label class="form-label">Or Upload Banner File</label>
                        <input type="file" name="banner_file" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label">SEO Title</label>
                        <input type="text" id="colSeoTitle" name="seo_title" class="form-control">
                    </div>
                    <div>
                        <label class="form-label">Display Order</label>
                        <input type="number" id="colOrder" name="display_order" class="form-control" value="0">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label class="form-label">SEO Meta Description</label>
                    <textarea id="colSeoDesc" name="seo_description" class="form-control" rows="2"></textarea>
                </div>

                <div style="margin-top: 16px; display: flex; gap: 24px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" id="colFeatured" name="is_featured" value="1">
                        <strong>Feature on Homepage</strong>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" id="colActive" name="is_active" value="1" checked>
                        <strong>Active Status</strong>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold btn-sm">Save Collection</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Assign Products to Collection -->
<div class="modal-backdrop" id="assignProductsModal">
    <div class="modal-dialog" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title" id="assignModalTitle">Associated Creations</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/collections.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign_products">
            <input type="hidden" name="collection_id" id="assignColId" value="">

            <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                    Check creations to include in this curated collection dossier.
                </p>
                <table class="product-picker-table">
                    <thead>
                        <tr>
                            <th width="40"></th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conciseProducts as $cp): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="product_ids[]" value="<?= $cp['id'] ?>" class="col-prod-checkbox" data-pid="<?= $cp['id'] ?>">
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 12px;"><?= e($cp['name']) ?></div>
                                    <div style="font-size: 10px; color: var(--text-muted);"><?= e($cp['sku']) ?></div>
                                </td>
                                <td><?= e($cp['category_name']) ?></td>
                                <td><?= format_price($cp['selling_price']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold btn-sm">Save Associated Creations</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('colForm');
    const title = document.getElementById('colModalTitle');

    document.getElementById('btnCreateCollection')?.addEventListener('click', function () {
        form.reset();
        document.getElementById('formColId').value = '';
        title.textContent = 'Add New Collection';
    });

    document.querySelectorAll('.btn-edit-collection').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = JSON.parse(this.getAttribute('data-collection'));
            document.getElementById('formColId').value = d.id;
            document.getElementById('colName').value = d.name;
            document.getElementById('colSlug').value = d.slug;
            document.getElementById('colTagline').value = d.tagline || '';
            document.getElementById('colDesc').value = d.description || '';
            document.getElementById('colCover').value = d.cover_image || '';
            document.getElementById('colBanner').value = d.banner_image || '';
            document.getElementById('colSeoTitle').value = d.seo_title || '';
            document.getElementById('colSeoDesc').value = d.seo_description || '';
            document.getElementById('colOrder').value = d.display_order;
            document.getElementById('colFeatured').checked = (parseInt(d.is_featured) === 1);
            document.getElementById('colActive').checked = (parseInt(d.is_active) === 1);

            title.textContent = 'Edit Collection: ' + d.name;
            openModal('collectionModal');
        });
    });

    // Product assignment modal
    document.querySelectorAll('.btn-manage-products').forEach(btn => {
        btn.addEventListener('click', function () {
            const colId = this.getAttribute('data-collection-id');
            const colName = this.getAttribute('data-collection-name');
            document.getElementById('assignColId').value = colId;
            document.getElementById('assignModalTitle').textContent = 'Curated Creations: ' + colName;
            
            // Clear checks
            document.querySelectorAll('.col-prod-checkbox').forEach(cb => cb.checked = false);
            
            // Fetch assigned
            fetch('<?= ADMIN_URL ?>/collections.php?action=get_assigned&col_id=' + colId)
                .then(r => r.json())
                .then(data => {
                    if (Array.isArray(data)) {
                        data.forEach(id => {
                            const cb = document.querySelector('.col-prod-checkbox[data-pid="' + id + '"]');
                            if (cb) cb.checked = true;
                        });
                    }
                })
                .catch(() => {});

            openModal('assignProductsModal');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
