<?php
/**
 * YATIYA COUTURE - Atelier Brands & House Lines Management
 */

$adminPageTitle = 'Brands & Atelier Lines';
$activeMenu = 'brands';

require_once __DIR__ . '/includes/admin-header.php';

// Handle Brand Save / Delete POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/brands.php');
    }

    $action = $_POST['action'] ?? '';
    $adminId = (int)$currentAdmin['id'];

    if ($action === 'save_brand') {
        $brandId = !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null;
        $data = [
            'name' => clean_input($_POST['name'] ?? ''),
            'slug' => clean_input($_POST['slug'] ?? ''),
            'description' => clean_input($_POST['description'] ?? ''),
            'logo_url' => clean_input($_POST['logo_url'] ?? ''),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0
        ];

        $saveRes = admin_save_brand($data, $brandId, $adminId);
        if ($saveRes['success']) {
            set_flash('success', $saveRes['message']);
        } else {
            set_flash('error', $saveRes['message']);
        }
    } elseif ($action === 'delete_brand') {
        $brandId = (int)($_POST['brand_id'] ?? 0);
        $delRes = admin_delete_brand($brandId, $adminId);
        if ($delRes['success']) {
            set_flash('success', $delRes['message']);
        } else {
            set_flash('error', $delRes['message']);
        }
    }

    redirect(ADMIN_URL . '/brands.php');
}

$brands = admin_get_brands();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 style="font-family: var(--font-heading); font-size: 20px; font-weight: 600;">
            Atelier Brands & House Lines (<?= count($brands) ?> lines)
        </h2>
        <div style="font-size: 12px; color: var(--text-muted);">
            Manage internal luxury lines, couture ateliers, and brand partnerships.
        </div>
    </div>
    <button type="button" class="btn btn-gold btn-sm" data-modal-target="brandModal" id="btnCreateBrand">
        + Add New Brand Line
    </button>
</div>

<!-- Brands Table Card -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Brand / Atelier Line</th>
                        <th>URL Slug</th>
                        <th>Description</th>
                        <th>Creations</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($brands as $b): ?>
                        <tr>
                            <td>
                                <strong style="font-size: 13px;"><?= e($b['name']) ?></strong>
                            </td>
                            <td>
                                <span style="font-family: monospace; font-size: 12px; color: var(--text-muted);"><?= e($b['slug']) ?></span>
                            </td>
                            <td style="font-size: 12px; color: var(--text-muted); max-width: 320px;">
                                <?= e($b['description'] ?: 'House luxury couture line.') ?>
                            </td>
                            <td>
                                <span style="font-weight: 600;"><?= (int)$b['product_count'] ?></span>
                            </td>
                            <td>
                                <span class="status-badge <?= $b['is_active'] ? 'active' : 'archived' ?>">
                                    <?= $b['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-group">
                                    <button 
                                        type="button" 
                                        class="btn-action btn-edit-brand" 
                                        data-brand='<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>'
                                        title="Edit Brand"
                                    >
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>

                                    <?php if ($b['product_count'] == 0): ?>
                                        <form action="<?= ADMIN_URL ?>/brands.php" method="POST" style="margin:0;">
                                            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                            <input type="hidden" name="action" value="delete_brand">
                                            <input type="hidden" name="brand_id" value="<?= $b['id'] ?>">
                                            <button type="submit" class="btn-action danger" title="Delete Brand" data-confirm="Are you sure you want to delete this brand line?">
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

<!-- Brand Modal Dialog -->
<div class="modal-backdrop" id="brandModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="brandModalTitle">Add New Brand Line</h3>
            <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/brands.php" method="POST" id="brandForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_brand">
            <input type="hidden" name="brand_id" id="formBrandId" value="">

            <div class="modal-body">
                <div class="form-row">
                    <div>
                        <label class="form-label" for="brandName">Brand / Line Name *</label>
                        <input type="text" id="brandName" name="name" class="form-control" placeholder="e.g. Yatiya Heritage" required>
                    </div>
                    <div>
                        <label class="form-label" for="brandSlug">URL Slug</label>
                        <input type="text" id="brandSlug" name="slug" class="form-control" placeholder="Auto-generated if empty">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label class="form-label" for="brandDesc">Description</label>
                    <textarea id="brandDesc" name="description" class="form-control" rows="2" placeholder="Brief maison positioning..."></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="logoUrl">Brand Logo URL</label>
                    <input type="url" id="logoUrl" name="logo_url" class="form-control" placeholder="https://...">
                </div>

                <div style="margin-top: 14px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" id="brandActive" name="is_active" value="1" checked>
                        <strong>Active Status</strong>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold">Save Brand Line</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('brandForm');
    const title = document.getElementById('brandModalTitle');

    document.getElementById('btnCreateBrand')?.addEventListener('click', function () {
        form.reset();
        document.getElementById('formBrandId').value = '';
        title.textContent = 'Add New Brand Line';
    });

    document.querySelectorAll('.btn-edit-brand').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = JSON.parse(this.getAttribute('data-brand'));
            document.getElementById('formBrandId').value = d.id;
            document.getElementById('brandName').value = d.name;
            document.getElementById('brandSlug').value = d.slug;
            document.getElementById('brandDesc').value = d.description || '';
            document.getElementById('logoUrl').value = d.logo_url || '';
            document.getElementById('brandActive').checked = (parseInt(d.is_active) === 1);

            title.textContent = 'Edit Brand: ' + d.name;
            openModal('brandModal');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

