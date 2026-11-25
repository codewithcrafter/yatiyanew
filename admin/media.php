<?php
/**
 * YATIYA COUTURE - Centralized Media Library
 * Upload, search, manage, and safely reference brand imagery.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

require_admin();

$currentAdmin = current_admin();
$adminId = (int)$currentAdmin['id'];
$adminPageTitle = 'Media Library';
$adminPageSubtitle = 'Centralized high-resolution imagery assets, campaign photography, and reference protection.';
$activeMenu = 'media';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security session expired. Please refresh and try again.');
        redirect(ADMIN_URL . '/media.php');
    }

    $action = clean_input($_POST['action'] ?? '');

    // 1. Upload Media
    if ($action === 'upload_media') {
        if (!empty($_FILES['media_file']['name'])) {
            $folder = clean_input($_POST['folder'] ?? 'general');
            $altText = clean_input($_POST['alt_text'] ?? '');
            $res = admin_upload_media($_FILES['media_file'], $folder, $altText, $adminId);
            if ($res['success']) {
                set_flash('success', $res['message']);
            } else {
                set_flash('error', $res['message']);
            }
        } else {
            set_flash('error', 'Please select a valid image file to upload.');
        }
        redirect(ADMIN_URL . '/media.php');
    }

    // 2. Delete Media with Reference Protection
    if ($action === 'delete_media') {
        $mediaId = (int)($_POST['media_id'] ?? 0);
        if ($mediaId > 0) {
            $delRes = admin_delete_media($mediaId, $adminId);
            if ($delRes['success']) {
                set_flash('success', $delRes['message']);
            } else {
                set_flash('error', $delRes['message']);
            }
        }
        redirect(ADMIN_URL . '/media.php');
    }

    // 3. Update Alt Text
    if ($action === 'update_meta') {
        $mediaId = (int)($_POST['media_id'] ?? 0);
        $altText = clean_input($_POST['alt_text'] ?? '');
        $caption = clean_input($_POST['caption'] ?? '');
        if ($mediaId > 0 && admin_update_media_meta($mediaId, $altText, $caption)) {
            set_flash('success', 'Media metadata updated.');
        } else {
            set_flash('error', 'Failed to update metadata.');
        }
        redirect(ADMIN_URL . '/media.php');
    }
}

// Filters & Pagination
$folder = clean_input($_GET['folder'] ?? '');
$search = clean_input($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));

$mediaData = admin_get_media(['folder' => $folder, 'search' => $search], $page, 20);

$headerActions = '
    <button type="button" class="btn btn-gold btn-sm" data-modal-target="uploadModal">
        + Upload Media Asset
    </button>
';

require_once __DIR__ . '/includes/admin-header.php';
?>

<!-- Filter & Search Toolbar -->
<div class="filter-bar">
    <form action="<?= ADMIN_URL ?>/media.php" method="GET" class="filter-group" style="flex: 1;">
        <input 
            type="search" 
            name="search" 
            class="form-control" 
            placeholder="Search filenames, alt text..." 
            value="<?= e($search) ?>" 
            style="max-width: 280px;"
        >

        <select name="folder" class="form-control" style="max-width: 180px;">
            <option value="">All Categories / Folders</option>
            <option value="banners" <?= ($folder === 'banners') ? 'selected' : '' ?>>Hero &amp; Banners</option>
            <option value="products" <?= ($folder === 'products') ? 'selected' : '' ?>>Creations &amp; Catalog</option>
            <option value="general" <?= ($folder === 'general') ? 'selected' : '' ?>>General Editorial</option>
        </select>

        <button type="submit" class="btn btn-dark btn-sm">Filter</button>
        <?php if (!empty($search) || !empty($folder)): ?>
            <a href="<?= ADMIN_URL ?>/media.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Media Grid -->
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 class="card-title">Media Assets (<?= number_format($mediaData['total']) ?> items)</h3>
        <span style="font-size: 12px; color: var(--text-muted);">
            Protected from deletion if actively in use by products, banners, or homepage sections.
        </span>
    </div>

    <div class="card-body">
        <?php if (empty($mediaData['items'])): ?>
            <div style="padding: 48px; text-align: center; color: var(--text-muted);">
                <p style="font-size: 14px;">No media assets found matching the filter.</p>
                <button type="button" class="btn btn-gold btn-sm" style="margin-top: 12px;" data-modal-target="uploadModal">
                    Upload First Asset
                </button>
            </div>
        <?php else: ?>
            <div class="media-grid-luxury">
                <?php foreach ($mediaData['items'] as $med): ?>
                    <div class="media-card-luxury">
                        <div class="media-thumb-wrap">
                            <img src="<?= e($med['file_url']) ?>" alt="<?= e($med['alt_text'] ?: $med['file_name']) ?>" loading="lazy" onerror="this.src='assets/images/placeholder.jpg'">
                        </div>
                        <div class="media-meta-wrap">
                            <div class="media-meta-title" title="<?= e($med['original_name']) ?>">
                                <?= e($med['original_name']) ?>
                            </div>
                            <div class="media-meta-sub">
                                <span><?= strtoupper(substr(e($med['mime_type']), 6)) ?> &bull; <?= round($med['file_size'] / 1024) ?> KB</span>
                                <?php if (!empty($med['width']) && !empty($med['height'])): ?>
                                    <span><?= $med['width'] ?>&times;<?= $med['height'] ?></span>
                                <?php endif; ?>
                            </div>
                            <div style="margin-top: 8px; display: flex; gap: 6px;">
                                <button type="button" class="btn btn-outline btn-sm btn-edit-media" 
                                    data-id="<?= $med['id'] ?>"
                                    data-alt="<?= e($med['alt_text']) ?>"
                                    data-caption="<?= e($med['caption']) ?>"
                                    data-url="<?= e($med['file_url']) ?>"
                                    data-name="<?= e($med['file_name']) ?>"
                                    style="flex: 1; font-size: 10px; padding: 3px;">
                                    Inspect / Alt
                                </button>
                                <form action="<?= ADMIN_URL ?>/media.php" method="POST" style="margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_media">
                                    <input type="hidden" name="media_id" value="<?= $med['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: #D32F2F; border-color: #D32F2F; font-size: 10px; padding: 3px 6px;" title="Safe Delete" data-confirm="Delete this media asset? (References will be checked)">
                                        &times;
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($mediaData['total_pages'] > 1): ?>
                <div style="margin-top: 24px; display: flex; justify-content: center; gap: 8px;">
                    <?php for ($p = 1; $p <= $mediaData['total_pages']; $p++): ?>
                        <a href="<?= ADMIN_URL ?>/media.php?page=<?= $p ?>&folder=<?= urlencode($folder) ?>&search=<?= urlencode($search) ?>" class="btn <?= ($p === $mediaData['page']) ? 'btn-gold' : 'btn-outline' ?> btn-sm">
                            <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Upload Media Asset -->
<div class="modal-backdrop" id="uploadModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Upload New High-Res Asset</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/media.php" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="upload_media">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Image File (JPG, PNG, WEBP &bull; Max 5MB) *</label>
                    <input type="file" name="media_file" class="form-control" accept="image/jpeg,image/png,image/webp" required data-preview-target="modalUploadPreview">
                    <img id="modalUploadPreview" src="" alt="Preview" style="display:none; width: 100px; height: 120px; object-fit: cover; margin-top: 10px; border-radius: 4px; border: 1px solid var(--gold);">
                </div>

                <div class="form-group">
                    <label class="form-label">Storage Folder / Section</label>
                    <select name="folder" class="form-control">
                        <option value="banners">Banners &amp; Editorial Hero</option>
                        <option value="products">Creations &amp; Catalog</option>
                        <option value="general">General Editorial &amp; Stories</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Accessibility Alt Text</label>
                    <input type="text" name="alt_text" class="form-control" placeholder="Descriptive label for screen readers & SEO">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold btn-sm">Upload to Library</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Inspect / Edit Media Metadata -->
<div class="modal-backdrop" id="inspectMediaModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title" id="inspectModalTitle">Media Asset Details</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/media.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_meta">
            <input type="hidden" name="media_id" id="inspectMediaId" value="">

            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 16px;">
                    <img id="inspectImg" src="" style="max-height: 180px; max-width: 100%; object-fit: contain; border-radius: 4px; border: 1px solid var(--border-color);">
                </div>

                <div class="form-group">
                    <label class="form-label">Public CDN / File URL</label>
                    <input type="text" id="inspectUrl" class="form-control" readonly onclick="this.select();">
                </div>

                <div class="form-group">
                    <label class="form-label">Alt Text (Accessibility &amp; SEO)</label>
                    <input type="text" id="inspectAlt" name="alt_text" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Caption / Description</label>
                    <textarea id="inspectCaption" name="caption" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Close</button>
                <button type="submit" class="btn btn-gold btn-sm">Save Metadata</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-edit-media').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('inspectMediaId').value = this.getAttribute('data-id');
            document.getElementById('inspectModalTitle').textContent = this.getAttribute('data-name');
            document.getElementById('inspectImg').src = this.getAttribute('data-url');
            document.getElementById('inspectUrl').value = this.getAttribute('data-url');
            document.getElementById('inspectAlt').value = this.getAttribute('data-alt');
            document.getElementById('inspectCaption').value = this.getAttribute('data-caption') || '';
            openModal('inspectMediaModal');
        });
    });
});
</script>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>

