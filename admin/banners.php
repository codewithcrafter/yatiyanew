<?php
/**
 * YATIYA COUTURE - Homepage Banner & Editorial Showcase Management
 */

$adminPageTitle = 'Homepage Banners';
$activeMenu = 'banners';

require_once __DIR__ . '/includes/admin-header.php';

// Handle Banner Save / Delete POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/banners.php');
    }

    $action = $_POST['action'] ?? '';
    $adminId = (int)$currentAdmin['id'];

    if ($action === 'save_banner') {
        $bannerId = !empty($_POST['banner_id']) ? (int)$_POST['banner_id'] : null;

        $imageUrl = clean_input($_POST['image_url'] ?? '');
        // Check if file upload was provided
        if (!empty($_FILES['image_file']['name'])) {
            $up = admin_validate_image_upload($_FILES['image_file'], 'banners');
            if ($up['success']) {
                $imageUrl = $up['url'];
            } else {
                set_flash('error', 'Image Upload Error: ' . $up['message']);
                redirect(ADMIN_URL . '/banners.php');
            }
        }

        $data = [
            'title' => clean_input($_POST['title'] ?? ''),
            'subtitle' => clean_input($_POST['subtitle'] ?? ''),
            'badge_text' => clean_input($_POST['badge_text'] ?? ''),
            'cta_text' => clean_input($_POST['cta_text'] ?? 'Shop Collection'),
            'cta_link' => clean_input($_POST['cta_link'] ?? '/shop.php'),
            'image_url' => $imageUrl,
            'mobile_image_url' => clean_input($_POST['mobile_image_url'] ?? ''),
            'position' => clean_input($_POST['position'] ?? 'hero'),
            'display_order' => (int)($_POST['display_order'] ?? 0),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'starts_at' => !empty($_POST['starts_at']) ? $_POST['starts_at'] : null,
            'ends_at' => !empty($_POST['ends_at']) ? $_POST['ends_at'] : null
        ];

        $saveRes = admin_save_banner($data, $bannerId, $adminId);
        if ($saveRes['success']) {
            set_flash('success', $saveRes['message']);
        } else {
            set_flash('error', $saveRes['message']);
        }
    } elseif ($action === 'delete_banner') {
        $bannerId = (int)($_POST['banner_id'] ?? 0);
        if (admin_delete_banner($bannerId, $adminId)) {
            set_flash('success', 'Banner removed.');
        } else {
            set_flash('error', 'Failed to remove banner.');
        }
    }

    redirect(ADMIN_URL . '/banners.php');
}

$banners = admin_get_banners();
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
    <div>
        <h2 style="font-family: var(--font-heading); font-size: 20px; font-weight: 600;">
            Homepage Editorial Banners (<?= count($banners) ?> assets)
        </h2>
        <div style="font-size: 12px; color: var(--text-muted);">
            Curate hero slides, promotional spotlights, and editorial campaign banners.
        </div>
    </div>
    <button type="button" class="btn btn-gold btn-sm" data-modal-target="bannerModal" id="btnCreateBanner">
        + Add New Banner
    </button>
</div>

<!-- Banners Table Card -->
<div class="card">
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Visual Preview</th>
                        <th>Banner Heading</th>
                        <th>Position</th>
                        <th>CTA Link</th>
                        <th>Order</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banners as $b): ?>
                        <tr>
                            <td>
                                <img src="<?= e($b['image_url']) ?>" style="width: 100px; height: 50px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-color);" alt="">
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?= e($b['title']) ?></div>
                                <div style="font-size: 11px; color: var(--text-muted);"><?= e($b['subtitle']) ?></div>
                                <?php if (!empty($b['badge_text'])): ?>
                                    <span style="font-size: 10px; color: var(--gold); font-weight: 700; text-transform: uppercase;">
                                        <?= e($b['badge_text']) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-badge draft" style="font-size: 10px; text-transform: uppercase;">
                                    <?= e(str_replace('_', ' ', $b['position'])) ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?= e($b['cta_link']) ?>" target="_blank" style="font-size: 12px; color: var(--text-muted); text-decoration: none;">
                                    <?= e($b['cta_text']) ?> &rarr;
                                </a>
                            </td>
                            <td><?= (int)$b['display_order'] ?></td>
                            <td>
                                <span class="status-badge <?= $b['is_active'] ? 'active' : 'archived' ?>">
                                    <?= $b['is_active'] ? 'Active' : 'Hidden' ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-group">
                                    <button 
                                        type="button" 
                                        class="btn-action btn-edit-banner" 
                                        data-banner='<?= htmlspecialchars(json_encode($b), ENT_QUOTES, 'UTF-8') ?>'
                                        title="Edit Banner"
                                    >
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                        </svg>
                                    </button>

                                    <form action="<?= ADMIN_URL ?>/banners.php" method="POST" style="margin:0;">
                                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_banner">
                                        <input type="hidden" name="banner_id" value="<?= $b['id'] ?>">
                                        <button type="submit" class="btn-action danger" title="Delete Banner" data-confirm="Are you sure you want to remove this banner?">
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
    </div>
</div>

<!-- Banner Create / Edit Modal Dialog -->
<div class="modal-backdrop" id="bannerModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="bannerModalTitle">Add Homepage Banner</h3>
            <button type="button" class="modal-close-btn" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/banners.php" method="POST" enctype="multipart/form-data" id="bannerForm">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="action" value="save_banner">
            <input type="hidden" name="banner_id" id="formBannerId" value="">

            <div class="modal-body">
                <div class="form-row">
                    <div>
                        <label class="form-label" for="banTitle">Headline *</label>
                        <input type="text" id="banTitle" name="title" class="form-control" placeholder="e.g. Autumn / Winter Haute Couture" required>
                    </div>
                    <div>
                        <label class="form-label" for="banSubtitle">Subtitle / Tagline</label>
                        <input type="text" id="banSubtitle" name="subtitle" class="form-control" placeholder="The Heritage Monogram Edition">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label" for="banBadge">Badge Text</label>
                        <input type="text" id="banBadge" name="badge_text" class="form-control" placeholder="HAUTE COUTURE 2026">
                    </div>
                    <div>
                        <label class="form-label" for="banPosition">Placement Position</label>
                        <select id="banPosition" name="position" class="form-control">
                            <option value="hero">Hero Main Slider</option>
                            <option value="promo">Promo Spotlight</option>
                            <option value="exclusive_offer">Exclusive Collection Banner</option>
                            <option value="middle_banner">Middle Page Break</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label" for="banCtaText">CTA Button Text</label>
                        <input type="text" id="banCtaText" name="cta_text" class="form-control" value="Shop Collection" required>
                    </div>
                    <div>
                        <label class="form-label" for="banCtaLink">CTA Target URL</label>
                        <input type="text" id="banCtaLink" name="cta_link" class="form-control" value="/shop.php" required>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label class="form-label" for="banImgUrl">Desktop Image URL *</label>
                    <input type="url" id="banImgUrl" name="image_url" class="form-control" placeholder="https://images.unsplash.com/...">
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label" for="banImgFile">Or Upload Desktop Banner Image</label>
                        <input type="file" id="banImgFile" name="image_file" class="form-control" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div>
                        <label class="form-label" for="banMobImg">Mobile Banner URL (Optional)</label>
                        <input type="url" id="banMobImg" name="mobile_image_url" class="form-control" placeholder="https://...">
                    </div>
                </div>

                <div class="form-row" style="margin-top: 14px;">
                    <div>
                        <label class="form-label" for="banOrder">Sort Display Order</label>
                        <input type="number" id="banOrder" name="display_order" class="form-control" value="0">
                    </div>
                    <div style="display:flex;align-items:center;padding-top:20px;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" id="banActive" name="is_active" value="1" checked>
                            <strong>Active on Boutique</strong>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold">Save Banner</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('bannerForm');
    const title = document.getElementById('bannerModalTitle');

    document.getElementById('btnCreateBanner')?.addEventListener('click', function () {
        form.reset();
        document.getElementById('formBannerId').value = '';
        title.textContent = 'Add Homepage Banner';
    });

    document.querySelectorAll('.btn-edit-banner').forEach(btn => {
        btn.addEventListener('click', function () {
            const d = JSON.parse(this.getAttribute('data-banner'));
            document.getElementById('formBannerId').value = d.id;
            document.getElementById('banTitle').value = d.title;
            document.getElementById('banSubtitle').value = d.subtitle || '';
            document.getElementById('banBadge').value = d.badge_text || '';
            document.getElementById('banPosition').value = d.position;
            document.getElementById('banCtaText').value = d.cta_text;
            document.getElementById('banCtaLink').value = d.cta_link;
            document.getElementById('banImgUrl').value = d.image_url;
            document.getElementById('banMobImg').value = d.mobile_image_url || '';
            document.getElementById('banOrder').value = d.display_order;
            document.getElementById('banActive').checked = (parseInt(d.is_active) === 1);

            title.textContent = 'Edit Banner: ' + d.title;
            openModal('bannerModal');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

