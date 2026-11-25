<?php
/**
 * YATIYA COUTURE - Navigation & Mega Menu CMS
 * Configure primary boutique navigation, mega menus, promotional spotlights, and ordering.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

require_admin();

$currentAdmin = current_admin();
$adminId = (int)$currentAdmin['id'];
$adminPageTitle = 'Navigation & Mega Menu CMS';
$adminPageSubtitle = 'Manage primary boutique header menus, mega menu taxonomies, spotlights, and ordering.';
$activeMenu = 'navigation';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/navigation.php');
    }

    $action = clean_input($_POST['action'] ?? '');

    // 1. Save Menu Item
    if ($action === 'save_menu') {
        $menuId = !empty($_POST['menu_id']) ? (int)$_POST['menu_id'] : null;
        $promoImg = clean_input($_POST['promo_image'] ?? '');

        if (!empty($_FILES['promo_file']['name'])) {
            $up = admin_validate_image_upload($_FILES['promo_file'], 'banners');
            if ($up['success']) $promoImg = $up['url'];
        }

        $data = [
            'parent_id' => !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null,
            'label' => clean_input($_POST['label'] ?? ''),
            'url' => clean_input($_POST['url'] ?? ''),
            'menu_type' => clean_input($_POST['menu_type'] ?? 'header'),
            'mega_category_key' => clean_input($_POST['mega_category_key'] ?? ''),
            'badge_text' => clean_input($_POST['badge_text'] ?? ''),
            'promo_title' => clean_input($_POST['promo_title'] ?? ''),
            'promo_image' => $promoImg,
            'promo_link' => clean_input($_POST['promo_link'] ?? ''),
            'display_order' => (int)($_POST['display_order'] ?? 0),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'target' => clean_input($_POST['target'] ?? '_self')
        ];

        $res = admin_save_navigation_menu($data, $menuId, $adminId);
        if ($res['success']) {
            set_flash('success', $res['message']);
        } else {
            set_flash('error', $res['message']);
        }
        redirect(ADMIN_URL . '/navigation.php');
    }

    // 2. Delete Menu Item
    if ($action === 'delete_menu') {
        $menuId = (int)($_POST['menu_id'] ?? 0);
        if ($menuId > 0 && admin_delete_navigation_menu($menuId, $adminId)) {
            set_flash('success', 'Navigation item deleted successfully.');
        } else {
            set_flash('error', 'Failed to delete navigation item.');
        }
        redirect(ADMIN_URL . '/navigation.php');
    }

    // 3. Reorder Menus
    if ($action === 'reorder_menus') {
        $orders = $_POST['order'] ?? [];
        if (is_array($orders) && !empty($orders)) {
            admin_reorder_navigation_menus($orders);
            set_flash('success', 'Navigation menu order updated.');
        }
        redirect(ADMIN_URL . '/navigation.php');
    }
}

// Fetch header navigation items
$navItems = admin_get_navigation_menus('header');

$headerActions = '
    <button type="button" class="btn btn-gold btn-sm" data-modal-target="navModal" id="btnCreateNav">
        + Add Navigation Link
    </button>
';

require_once __DIR__ . '/includes/admin-header.php';
?>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 class="card-title">Primary Header Navigation Menu (<?= count($navItems) ?> links)</h3>
            <span style="font-size: 12px; color: var(--text-muted);">
                Changes reflect in real-time across the customer-facing boutique header and mobile drawer.
            </span>
        </div>
        <button type="submit" form="reorderNavForm" class="btn btn-dark btn-sm">
            Save Menu Ordering
        </button>
    </div>

    <div class="card-body" style="padding: 0;">
        <form action="<?= ADMIN_URL ?>/navigation.php" method="POST" id="reorderNavForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reorder_menus">

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th width="70">Order</th>
                            <th>Menu Label</th>
                            <th>URL Destination</th>
                            <th>Mega Menu Type</th>
                            <th>Badge</th>
                            <th>Spotlight Promo</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($navItems as $index => $item): ?>
                            <tr>
                                <td>
                                    <input type="number" name="order[<?= $item['id'] ?>]" value="<?= $item['display_order'] ?>" class="form-control" style="width: 60px; padding: 4px 6px; font-size: 12px;">
                                </td>
                                <td>
                                    <strong style="font-size: 13px;"><?= e($item['label']) ?></strong>
                                </td>
                                <td>
                                    <code style="color: var(--text-muted); font-size: 12px;"><?= e($item['url']) ?></code>
                                </td>
                                <td>
                                    <?php if (!empty($item['mega_category_key'])): ?>
                                        <span class="status-badge active" style="font-size: 10px;">
                                            Mega: <?= ucfirst(e($item['mega_category_key'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 12px;">Direct Link</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['badge_text'])): ?>
                                        <span class="status-badge pending" style="font-size: 10px;"><?= e($item['badge_text']) ?></span>
                                    <?php else: ?>
                                        &ndash;
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['promo_title']) || !empty($item['promo_image'])): ?>
                                        <span style="font-size: 11px; color: var(--gold); font-weight: 600;">Configured</span>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted); font-size: 11px;">Default</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $item['is_active'] ? 'active' : 'archived' ?>">
                                        <?= $item['is_active'] ? 'Active' : 'Hidden' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-group">
                                        <button 
                                            type="button" 
                                            class="btn-action btn-edit-nav" 
                                            data-item='<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>'
                                            title="Edit Menu Item"
                                        >
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                            </svg>
                                        </button>

                                        <form action="<?= ADMIN_URL ?>/navigation.php" method="POST" style="margin: 0; display: inline;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="delete_menu">
                                            <input type="hidden" name="menu_id" value="<?= $item['id'] ?>">
                                            <button type="submit" class="btn-action danger" title="Delete Menu" data-confirm="Remove this navigation menu item?">
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
        </form>
    </div>
</div>

<!-- Modal: Add / Edit Navigation Item -->
<div class="modal-backdrop" id="navModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="navModalTitle">Add Navigation Link</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/navigation.php" method="POST" enctype="multipart/form-data" id="navForm">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_menu">
            <input type="hidden" name="menu_id" id="formNavId" value="">

            <div class="modal-body">
                <div class="form-row">
                    <div>
                        <label class="form-label">Menu Label *</label>
                        <input type="text" id="navLabel" name="label" class="form-control" placeholder="e.g. Bridal Lehengas" required>
                    </div>
                    <div>
                        <label class="form-label">Destination URL *</label>
                        <input type="text" id="navUrl" name="url" class="form-control" placeholder="e.g. shop.php?category=women" required>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label">Mega Menu Taxonomy</label>
                        <select id="navMega" name="mega_category_key" class="form-control">
                            <option value="">None (Standard Link)</option>
                            <option value="women">Women Atelier Columns</option>
                            <option value="men">Men Atelier Columns</option>
                            <option value="clothing">Clothing Silhouette Columns</option>
                            <option value="collections">Curated Collections Columns</option>
                            <option value="accessories">Heirloom Accessories Columns</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">Badge Text (Optional)</label>
                        <input type="text" id="navBadge" name="badge_text" class="form-control" placeholder="e.g. NEW or HAUTE">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label class="form-label">Spotlight Promo Title (For Mega Menus)</label>
                    <input type="text" id="navPromoTitle" name="promo_title" class="form-control" placeholder="e.g. The Royal Silk Edit">
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label">Spotlight Image URL</label>
                        <input type="url" id="navPromoImg" name="promo_image" class="form-control" placeholder="https://...">
                    </div>
                    <div>
                        <label class="form-label">Or Upload Promo File</label>
                        <input type="file" name="promo_file" class="form-control" accept="image/*">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label">Spotlight Link URL</label>
                        <input type="text" id="navPromoLink" name="promo_link" class="form-control" placeholder="e.g. shop.php?category=women">
                    </div>
                    <div>
                        <label class="form-label">Display Order</label>
                        <input type="number" id="navOrder" name="display_order" class="form-control" value="0">
                    </div>
                </div>

                <div style="margin-top: 16px; display: flex; gap: 24px;">
                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                        <input type="checkbox" id="navActive" name="is_active" value="1" checked>
                        <strong>Active (Visible on Header)</strong>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold btn-sm">Save Menu Link</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('navForm');
    const title = document.getElementById('navModalTitle');

    document.getElementById('btnCreateNav')?.addEventListener('click', function () {
        form.reset();
        document.getElementById('formNavId').value = '';
        title.textContent = 'Add Navigation Link';
    });

    document.querySelectorAll('.btn-edit-nav').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = JSON.parse(this.getAttribute('data-item'));
            document.getElementById('formNavId').value = d.id;
            document.getElementById('navLabel').value = d.label;
            document.getElementById('navUrl').value = d.url;
            document.getElementById('navMega').value = d.mega_category_key || '';
            document.getElementById('navBadge').value = d.badge_text || '';
            document.getElementById('navPromoTitle').value = d.promo_title || '';
            document.getElementById('navPromoImg').value = d.promo_image || '';
            document.getElementById('navPromoLink').value = d.promo_link || '';
            document.getElementById('navOrder').value = d.display_order;
            document.getElementById('navActive').checked = (parseInt(d.is_active) === 1);

            title.textContent = 'Edit Navigation Link: ' + d.label;
            openModal('navModal');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

