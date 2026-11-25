<?php
/**
 * YATIYA COUTURE - Dynamic Homepage CMS & Section Manager
 * Full enterprise-grade CMS to manage, edit, reorder, curate, and publish
 * every homepage block and its contents dynamically.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

require_admin();

$currentAdmin = current_admin();
$adminId = (int)$currentAdmin['id'];
$activeMenu = 'homepage-sections';

// Process POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('error', 'Security token expired. Please try again.');
        redirect(ADMIN_URL . '/homepage-sections.php');
    }

    $action = clean_input($_POST['action'] ?? '');

    // 1. Reorder All Sections
    if ($action === 'reorder_sections') {
        $orders = $_POST['order'] ?? [];
        if (is_array($orders) && !empty($orders)) {
            admin_reorder_homepage_sections($orders);
            set_flash('success', 'Homepage section sequence updated and synchronized with live boutique.');
        }
        redirect(ADMIN_URL . '/homepage-sections.php');
    }

    // 2. Toggle Section Active Status
    if ($action === 'toggle_section_status') {
        $secId = (int)($_POST['section_id'] ?? 0);
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        if ($secId > 0) {
            admin_toggle_homepage_section_status($secId, $isActive);
            set_flash('success', 'Section visibility status updated.');
        }
        redirect(ADMIN_URL . '/homepage-sections.php');
    }

    // 3. Publish Section
    if ($action === 'publish_section') {
        $secId = (int)($_POST['section_id'] ?? 0);
        if ($secId > 0) {
            admin_publish_homepage_section($secId, $adminId);
            set_flash('success', 'Section published live to boutique homepage.');
        }
        redirect(ADMIN_URL . '/homepage-sections.php');
    }

    // 4. Archive Section
    if ($action === 'archive_section') {
        $secId = (int)($_POST['section_id'] ?? 0);
        if ($secId > 0) {
            admin_archive_homepage_section($secId, $adminId);
            set_flash('success', 'Section archived successfully.');
        }
        redirect(ADMIN_URL . '/homepage-sections.php');
    }

    // 5. Duplicate Section
    if ($action === 'duplicate_section') {
        $secId = (int)($_POST['section_id'] ?? 0);
        if ($secId > 0) {
            $dupRes = admin_duplicate_homepage_section($secId, $adminId);
            if ($dupRes['success']) {
                set_flash('success', 'Section duplicated as draft.');
            } else {
                set_flash('error', $dupRes['message']);
            }
        }
        redirect(ADMIN_URL . '/homepage-sections.php');
    }

    // 6. Save / Update Section Configuration
    if ($action === 'save_section') {
        $secId = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
        $config = [];
        
        // Process custom config fields based on section type
        $secType = clean_input($_POST['section_type'] ?? 'product_collection');
        if ($secType === 'hero_slider') {
            $config['position'] = 'hero';
            $config['overlay_opacity'] = (float)($_POST['overlay_opacity'] ?? 0.45);
        } elseif ($secType === 'category_grid') {
            $config['categories'] = !empty($_POST['selected_categories']) && is_array($_POST['selected_categories']) 
                ? array_map('clean_input', $_POST['selected_categories']) 
                : ['women', 'men', 'kids', 'footwear', 'bags', 'accessories'];
        } elseif ($secType === 'product_collection') {
            $config['filter_col'] = clean_input($_POST['filter_col'] ?? 'is_new');
            $config['limit'] = max(2, min(24, (int)($_POST['limit'] ?? 8)));
            $config['sort'] = clean_input($_POST['sort'] ?? 'created_at_desc');
        } elseif ($secType === 'editorial_banner') {
            $config['image_url'] = clean_input($_POST['banner_image_url'] ?? '');
            $config['secondary_cta_text'] = clean_input($_POST['secondary_cta_text'] ?? '');
            $config['secondary_cta_url'] = clean_input($_POST['secondary_cta_url'] ?? '');
        }

        // Handle uploaded banner image file if present
        if (!empty($_FILES['banner_file']['name'])) {
            $up = admin_validate_image_upload($_FILES['banner_file'], 'banners');
            if ($up['success']) {
                $config['image_url'] = $up['url'];
            }
        }

        $secData = [
            'section_key' => clean_input($_POST['section_key'] ?? ''),
            'title' => clean_input($_POST['title'] ?? ''),
            'section_type' => $secType,
            'eyebrow' => clean_input($_POST['eyebrow'] ?? ''),
            'subtitle' => clean_input($_POST['subtitle'] ?? ''),
            'description' => trim($_POST['description'] ?? ''),
            'cta_text' => clean_input($_POST['cta_text'] ?? ''),
            'cta_url' => clean_input($_POST['cta_url'] ?? ''),
            'background_style' => clean_input($_POST['background_style'] ?? 'default'),
            'layout_style' => clean_input($_POST['layout_style'] ?? 'standard'),
            'display_order' => (int)($_POST['display_order'] ?? 0),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0,
            'publish_status' => clean_input($_POST['publish_status'] ?? 'published'),
            'selection_mode' => clean_input($_POST['selection_mode'] ?? 'automatic'),
            'config_data' => $config
        ];

        $res = admin_save_homepage_section($secData, $secId, $adminId);
        if ($res['success']) {
            set_flash('success', $res['message']);
            $targetId = $res['id'];
            // If curated product IDs are passed, save them
            if (!empty($_POST['assigned_products']) && is_array($_POST['assigned_products'])) {
                admin_assign_products_to_section($targetId, $_POST['assigned_products']);
            }
        } else {
            set_flash('error', $res['message']);
        }
        redirect(ADMIN_URL . '/homepage-sections.php' . ($secId ? "?edit=$secId" : ''));
    }

    // 7. Assign Curated Products to Section
    if ($action === 'assign_products') {
        $secId = (int)($_POST['section_id'] ?? 0);
        $prodIds = !empty($_POST['product_ids']) && is_array($_POST['product_ids']) ? $_POST['product_ids'] : [];
        if ($secId > 0) {
            admin_assign_products_to_section($secId, $prodIds);
            set_flash('success', 'Curated product selection updated for this section.');
        }
        redirect(ADMIN_URL . "/homepage-sections.php?edit={$secId}&tab=products");
    }

    // 8. Remove Single Item from Section
    if ($action === 'remove_section_item') {
        $itemId = (int)($_POST['item_id'] ?? 0);
        $secId = (int)($_POST['section_id'] ?? 0);
        if ($itemId > 0) {
            admin_remove_item_from_section($itemId);
            set_flash('success', 'Item removed from curated section.');
        }
        redirect(ADMIN_URL . "/homepage-sections.php?edit={$secId}&tab=products");
    }

    // Backwards Compatibility for Legacy Test Suites (test_homepage_sections.php)
    $tabToFlag = [
        'new_arrivals' => 'is_new',
        'bestsellers' => 'is_bestseller',
        'summer_midi' => 'is_summer_midi',
        'trending_coords' => 'is_trending_coord'
    ];
    $tabTitles = [
        'new_arrivals' => 'New Arrivals Runway',
        'bestsellers' => 'House Signature Best Sellers',
        'summer_midi' => 'Summer Midi Collection',
        'trending_coords' => 'Trending Co-ord Sets'
    ];

    if ($action === 'add_product_to_section') {
        $section = clean_input($_POST['section'] ?? '');
        $productId = (int)($_POST['product_id'] ?? 0);
        if (!empty($tabToFlag[$section]) && $productId > 0) {
            admin_set_product_section_flag($productId, $tabToFlag[$section], 1);
            set_flash('success', 'Creation successfully added to ' . ($tabTitles[$section] ?? 'section') . '.');
        } else {
            set_flash('error', 'Please choose a valid product.');
        }
        redirect(ADMIN_URL . "/homepage-sections.php?tab={$section}");
    }

    if ($action === 'remove_product_from_section') {
        $section = clean_input($_POST['section'] ?? '');
        $productId = (int)($_POST['product_id'] ?? 0);
        if (!empty($tabToFlag[$section]) && $productId > 0) {
            admin_set_product_section_flag($productId, $tabToFlag[$section], 0);
            set_flash('success', 'Creation removed from ' . ($tabTitles[$section] ?? 'section') . '.');
        }
        redirect(ADMIN_URL . "/homepage-sections.php?tab={$section}");
    }

    if ($action === 'save_story') {
        $storyId = !empty($_POST['story_id']) ? (int)$_POST['story_id'] : null;
        $storyData = [
            'title' => clean_input($_POST['title'] ?? ''),
            'image_url' => clean_input($_POST['image_url'] ?? ''),
            'link_url' => clean_input($_POST['link_url'] ?? '/shop.php'),
            'display_order' => (int)($_POST['display_order'] ?? 0),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0
        ];
        if (!empty($_FILES['image_file']['name'])) {
            $up = admin_validate_image_upload($_FILES['image_file'], 'banners');
            if ($up['success']) $storyData['image_url'] = $up['url'];
        }
        $res = admin_save_homepage_story($storyData, $storyId);
        if ($res['success']) set_flash('success', $res['message']);
        else set_flash('error', $res['message']);
        redirect(ADMIN_URL . '/homepage-sections.php?tab=circular_stories');
    }

    if ($action === 'delete_story') {
        $storyId = (int)($_POST['story_id'] ?? 0);
        if ($storyId > 0 && admin_delete_homepage_story($storyId)) {
            set_flash('success', 'Circular category story deleted.');
        } else {
            set_flash('error', 'Failed to delete circular story.');
        }
        redirect(ADMIN_URL . '/homepage-sections.php?tab=circular_stories');
    }

    if ($action === 'save_style_story') {
        $id = !empty($_POST['style_story_id']) ? (int)$_POST['style_story_id'] : null;
        $data = [
            'title' => clean_input($_POST['title'] ?? ''),
            'tag' => clean_input($_POST['tag'] ?? 'STYLE EDITORIAL'),
            'excerpt' => trim($_POST['excerpt'] ?? ''),
            'image_url' => clean_input($_POST['image_url'] ?? ''),
            'link_url' => clean_input($_POST['link_url'] ?? '/shop.php'),
            'read_time' => clean_input($_POST['read_time'] ?? '4 min read'),
            'display_order' => (int)($_POST['display_order'] ?? 0),
            'is_active' => !empty($_POST['is_active']) ? 1 : 0
        ];
        if (!empty($_FILES['style_image_file']['name'])) {
            $up = admin_validate_image_upload($_FILES['style_image_file'], 'banners');
            if ($up['success']) $data['image_url'] = $up['url'];
        }
        $res = admin_save_style_story($data, $id);
        if ($res['success']) set_flash('success', $res['message']);
        else set_flash('error', $res['message']);
        redirect(ADMIN_URL . '/homepage-sections.php?tab=style_stories');
    }

    if ($action === 'delete_style_story') {
        $id = (int)($_POST['style_story_id'] ?? 0);
        if ($id > 0 && admin_delete_style_story($id)) {
            set_flash('success', 'Style story deleted.');
        } else {
            set_flash('error', 'Failed to delete style story.');
        }
        redirect(ADMIN_URL . '/homepage-sections.php?tab=style_stories');
    }
}

// Check if viewing Section Editor
$editId = !empty($_GET['edit']) ? (int)$_GET['edit'] : null;
$editingSection = null;
$curatedItems = [];
if ($editId) {
    $editingSection = admin_get_homepage_section($editId);
    if ($editingSection) {
        $curatedItems = admin_get_section_items($editId);
    }
}

// Check legacy tabs for regression tests
$legacyTab = clean_input($_GET['tab'] ?? '');
$isLegacyView = in_array($legacyTab, ['new_arrivals', 'bestsellers', 'summer_midi', 'trending_coords', 'circular_stories', 'style_stories'], true);

$adminPageTitle = $editingSection ? 'Edit Section: ' . $editingSection['title'] : 'Homepage CMS & Section Architecture';
$adminPageSubtitle = $editingSection 
    ? 'Configure section content, layout, styling, product curation and publishing status.' 
    : 'Reorder, enable/disable, configure, and curate every section of the live Yatiya boutique.';

// Contextual Actions for Header
$headerActions = '';
if ($editingSection) {
    $headerActions = '
        <a href="' . ADMIN_URL . '/homepage-sections.php" class="btn btn-outline btn-sm">&larr; Back to Sections</a>
        <a href="' . BASE_URL . '/index.php?preview=1#' . e($editingSection['section_key']) . '" target="_blank" class="btn btn-outline btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            Live Preview
        </a>
    ';
} else {
    $headerActions = '
        <a href="' . BASE_URL . '/index.php?preview=1" target="_blank" class="btn btn-outline btn-sm">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
            Preview Entire Homepage
        </a>
        <button type="button" class="btn btn-gold btn-sm" data-modal-target="newSectionModal">
            + Add New Section
        </button>
    ';
}

require_once __DIR__ . '/includes/admin-header.php';

// All concise products for fast curation pickers
$conciseProducts = admin_get_all_products_concise();
$allCategories = admin_get_categories();
?>

<!-- Luxury CMS Navigation Tabs -->
<div class="nav-tabs-luxury">
    <a href="<?= ADMIN_URL ?>/homepage-sections.php" class="tab-link-luxury <?= (empty($legacyTab) && !$editingSection) ? 'active' : '' ?>">
        All Homepage Sections
    </a>
    <a href="<?= ADMIN_URL ?>/homepage-sections.php?tab=circular_stories" class="tab-link-luxury <?= ($legacyTab === 'circular_stories') ? 'active' : '' ?>">
        Circular Stories (Top Bar)
    </a>
    <a href="<?= ADMIN_URL ?>/homepage-sections.php?tab=style_stories" class="tab-link-luxury <?= ($legacyTab === 'style_stories') ? 'active' : '' ?>">
        Editorial Style Stories
    </a>
    <a href="<?= ADMIN_URL ?>/homepage-sections.php?tab=new_arrivals" class="tab-link-luxury <?= ($legacyTab === 'new_arrivals') ? 'active' : '' ?>">
        New Arrivals Curation
    </a>
    <a href="<?= ADMIN_URL ?>/homepage-sections.php?tab=bestsellers" class="tab-link-luxury <?= ($legacyTab === 'bestsellers') ? 'active' : '' ?>">
        Best Sellers Curation
    </a>
    <a href="<?= ADMIN_URL ?>/homepage-sections.php?tab=summer_midi" class="tab-link-luxury <?= ($legacyTab === 'summer_midi') ? 'active' : '' ?>">
        Summer Midi Curation
    </a>
    <a href="<?= ADMIN_URL ?>/homepage-sections.php?tab=trending_coords" class="tab-link-luxury <?= ($legacyTab === 'trending_coords') ? 'active' : '' ?>">
        Trending Co-ords Curation
    </a>
</div>

<?php if ($editingSection): ?>
<!-- =====================================================================
     VIEW A: SECTION-SPECIFIC CONTENT EDITOR
     ===================================================================== -->
<?php
$secConfig = !empty($editingSection['config_data']) ? json_decode($editingSection['config_data'], true) : [];
$editorTab = clean_input($_GET['tab'] ?? 'content');
?>
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <div>
            <span class="section-type-pill"><?= e($editingSection['section_type']) ?></span>
            <span class="status-pill <?= e($editingSection['publish_status']) ?>" style="margin-left: 8px;">
                <?= ucfirst(e($editingSection['publish_status'])) ?>
            </span>
            <span class="status-pill <?= $editingSection['is_active'] ? 'active' : 'inactive' ?>" style="margin-left: 4px;">
                <?= $editingSection['is_active'] ? 'Active (Visible)' : 'Disabled' ?>
            </span>
        </div>
        <div style="font-size: 12px; color: var(--text-muted);">
            Section Key: <code style="color: var(--gold);"><?= e($editingSection['section_key']) ?></code>
        </div>
    </div>
    
    <div class="card-body">
        <form action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_section">
            <input type="hidden" name="section_id" value="<?= $editingSection['id'] ?>">
            <input type="hidden" name="section_type" value="<?= e($editingSection['section_type']) ?>">

            <!-- Standard Section Header Info -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                <div>
                    <div class="form-group">
                        <label class="form-label">Section Display Title *</label>
                        <input type="text" name="title" class="form-control" value="<?= e($editingSection['title']) ?>" required>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Eyebrow / Badge Text</label>
                            <input type="text" name="eyebrow" class="form-control" value="<?= e($editingSection['eyebrow'] ?? '') ?>" placeholder="e.g. Autumn / Winter 2026">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subtitle</label>
                            <input type="text" name="subtitle" class="form-control" value="<?= e($editingSection['subtitle'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Editorial Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($editingSection['description'] ?? '') ?></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">CTA Button Label</label>
                            <input type="text" name="cta_text" class="form-control" value="<?= e($editingSection['cta_text'] ?? '') ?>" placeholder="e.g. Explore All &rarr;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">CTA Destination URL</label>
                            <input type="text" name="cta_url" class="form-control" value="<?= e($editingSection['cta_url'] ?? '') ?>" placeholder="e.g. shop.php?category=women">
                        </div>
                    </div>

                    <!-- Type Specific Configuration -->
                    <?php if ($editingSection['section_type'] === 'hero_slider'): ?>
                        <div class="card" style="background: #FAF8F5; border: 1px dashed var(--border-color); padding: 16px; margin-top: 16px;">
                            <h4 style="font-size: 13px; font-weight: 600; margin-bottom: 12px; color: var(--gold);">Hero Slider Settings</h4>
                            <div class="form-group">
                                <label class="form-label">Overlay Darkness (0.0 to 1.0)</label>
                                <input type="number" name="overlay_opacity" class="form-control" step="0.05" min="0" max="0.9" value="<?= e($secConfig['overlay_opacity'] ?? 0.45) ?>">
                            </div>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 8px;">
                                Individual hero slides are managed under the <strong><a href="<?= ADMIN_URL ?>/banners.php" style="color: var(--gold);">Homepage Banners</a></strong> module.
                            </div>
                        </div>
                    <?php elseif ($editingSection['section_type'] === 'category_grid'): ?>
                        <div class="card" style="background: #FAF8F5; border: 1px dashed var(--border-color); padding: 16px; margin-top: 16px;">
                            <h4 style="font-size: 13px; font-weight: 600; margin-bottom: 12px; color: var(--gold);">Featured Category Salons</h4>
                            <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                                <?php 
                                $selectedCats = $secConfig['categories'] ?? ['women', 'men', 'kids', 'footwear', 'bags', 'accessories'];
                                foreach ($allCategories as $c): 
                                    $checked = in_array($c['slug'], $selectedCats, true) ? 'checked' : '';
                                ?>
                                    <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                                        <input type="checkbox" name="selected_categories[]" value="<?= e($c['slug']) ?>" <?= $checked ?>>
                                        <span><?= e($c['name']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php elseif ($editingSection['section_type'] === 'product_collection'): ?>
                        <div class="card" style="background: #FAF8F5; border: 1px dashed var(--border-color); padding: 16px; margin-top: 16px;">
                            <h4 style="font-size: 13px; font-weight: 600; margin-bottom: 12px; color: var(--gold);">Product Selection & Filtering</h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group">
                                    <label class="form-label">Selection Mode</label>
                                    <select name="selection_mode" class="form-control">
                                        <option value="automatic" <?= ($editingSection['selection_mode'] === 'automatic') ? 'selected' : '' ?>>Automatic (Database Flag Filter)</option>
                                        <option value="manual" <?= ($editingSection['selection_mode'] === 'manual') ? 'selected' : '' ?>>Manual (Curated Specific Products)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Auto Filter Tag (When in Automatic Mode)</label>
                                    <select name="filter_col" class="form-control">
                                        <option value="is_new" <?= (($secConfig['filter_col'] ?? '') === 'is_new') ? 'selected' : '' ?>>New Arrivals (is_new)</option>
                                        <option value="is_bestseller" <?= (($secConfig['filter_col'] ?? '') === 'is_bestseller') ? 'selected' : '' ?>>Best Sellers (is_bestseller)</option>
                                        <option value="is_summer_midi" <?= (($secConfig['filter_col'] ?? '') === 'is_summer_midi') ? 'selected' : '' ?>>Summer Midi (is_summer_midi)</option>
                                        <option value="is_trending_coord" <?= (($secConfig['filter_col'] ?? '') === 'is_trending_coord') ? 'selected' : '' ?>>Trending Co-ords (is_trending_coord)</option>
                                        <option value="is_featured" <?= (($secConfig['filter_col'] ?? '') === 'is_featured') ? 'selected' : '' ?>>Featured Creations (is_featured)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Maximum Creations to Display</label>
                                <input type="number" name="limit" class="form-control" min="4" max="24" value="<?= (int)($secConfig['limit'] ?? 8) ?>">
                            </div>
                        </div>
                    <?php elseif ($editingSection['section_type'] === 'editorial_banner'): ?>
                        <div class="card" style="background: #FAF8F5; border: 1px dashed var(--border-color); padding: 16px; margin-top: 16px;">
                            <h4 style="font-size: 13px; font-weight: 600; margin-bottom: 12px; color: var(--gold);">Exclusive Banner Visuals & Links</h4>
                            <div class="form-group">
                                <label class="form-label">Banner Image URL</label>
                                <input type="text" name="banner_image_url" class="form-control" value="<?= e($secConfig['image_url'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Or Upload New Banner High-Res Image</label>
                                <input type="file" name="banner_file" class="form-control" accept="image/*">
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                                <div class="form-group">
                                    <label class="form-label">Secondary CTA Text</label>
                                    <input type="text" name="secondary_cta_text" class="form-control" value="<?= e($secConfig['secondary_cta_text'] ?? 'Explore Craftsmanship') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Secondary CTA Link</label>
                                    <input type="text" name="secondary_cta_url" class="form-control" value="<?= e($secConfig['secondary_cta_url'] ?? 'about.php#craftsmanship') ?>">
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Right Sidebar: Publishing & Styling Controls -->
                <div>
                    <div class="card" style="background: #FFFFFF; border: 1px solid var(--border-color); padding: 20px;">
                        <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 16px;">Publishing Controls</h4>

                        <div class="form-group">
                            <label class="form-label">Publish Status</label>
                            <select name="publish_status" class="form-control">
                                <option value="published" <?= ($editingSection['publish_status'] === 'published') ? 'selected' : '' ?>>Published (Live)</option>
                                <option value="draft" <?= ($editingSection['publish_status'] === 'draft') ? 'selected' : '' ?>>Draft (Preview Only)</option>
                                <option value="archived" <?= ($editingSection['publish_status'] === 'archived') ? 'selected' : '' ?>>Archived</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Section Visibility</label>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                                <input type="checkbox" name="is_active" value="1" <?= $editingSection['is_active'] ? 'checked' : '' ?>>
                                <span>Active on live store</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Background Aesthetic</label>
                            <select name="background_style" class="form-control">
                                <option value="default" <?= ($editingSection['background_style'] === 'default') ? 'selected' : '' ?>>Ivory Linen (Default)</option>
                                <option value="secondary" <?= ($editingSection['background_style'] === 'secondary') ? 'selected' : '' ?>>Pure Silk White (Secondary)</option>
                                <option value="dark" <?= ($editingSection['background_style'] === 'dark') ? 'selected' : '' ?>>Obsidian Charcoal (Dark)</option>
                                <option value="dark_luxury" <?= ($editingSection['background_style'] === 'dark_luxury') ? 'selected' : '' ?>>Royal Midnight Atelier</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Display Order Sequence</label>
                            <input type="number" name="display_order" class="form-control" value="<?= (int)$editingSection['display_order'] ?>" min="1" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Unique Section Key</label>
                            <input type="text" name="section_key" class="form-control" value="<?= e($editingSection['section_key']) ?>" required>
                        </div>

                        <div style="margin-top: 24px; display: flex; flex-direction: column; gap: 10px;">
                            <button type="submit" class="btn btn-gold" style="width: 100%; justify-content: center;">
                                Save &amp; Apply Changes
                            </button>
                            <a href="<?= BASE_URL ?>/index.php?preview=1#<?= e($editingSection['section_key']) ?>" target="_blank" class="btn btn-outline" style="width: 100%; justify-content: center;">
                                Live Preview
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- If section is product_collection, show manual curated products picker -->
<?php if ($editingSection['section_type'] === 'product_collection'): ?>
<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h3 class="card-title">Curated Products for This Section (<?= count($curatedItems) ?> selected)</h3>
            <span style="font-size: 12px; color: var(--text-muted);">
                When Selection Mode is set to <strong>Manual</strong>, these exact products render on the homepage in this order.
            </span>
        </div>
        <button type="button" class="btn btn-gold btn-sm" data-modal-target="curateProductModal">
            + Add Products to Section
        </button>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php if (empty($curatedItems)): ?>
            <div style="padding: 32px; text-align: center; color: var(--text-muted);">
                <p style="font-size: 14px;">No specific products manually curated yet.</p>
                <p style="font-size: 12px; margin-top: 4px;">Currently rendering automatic products matching the configured database filter tag.</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($curatedItems as $it): ?>
                        <tr>
                            <td><strong><?= (int)$it['display_order'] ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <img src="<?= e($it['product_image'] ?: 'assets/images/placeholder.jpg') ?>" class="product-thumb" alt="" style="width: 32px; height: 40px; object-fit: cover;">
                                    <div>
                                        <div style="font-weight: 600;"><?= e($it['product_name']) ?></div>
                                        <div style="font-size: 11px; color: var(--text-muted);"><?= e($it['product_sku']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($it['category_name']) ?></td>
                            <td><?= format_price($it['product_price']) ?></td>
                            <td>
                                <form action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST" style="margin: 0; display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove_section_item">
                                    <input type="hidden" name="item_id" value="<?= $it['id'] ?>">
                                    <input type="hidden" name="section_id" value="<?= $editingSection['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: #D32F2F; border-color: #D32F2F; padding: 3px 8px; font-size: 11px;" data-confirm="Remove this creation from this homepage section?">
                                        Remove
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: Pick Curated Products -->
<div class="modal-backdrop" id="curateProductModal">
    <div class="modal-dialog" style="max-width: 650px;">
        <div class="modal-header">
            <h3 class="modal-title">Curate Products for <?= e($editingSection['title']) ?></h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="assign_products">
            <input type="hidden" name="section_id" value="<?= $editingSection['id'] ?>">
            
            <div class="modal-body" style="max-height: 400px; overflow-y: auto;">
                <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 12px;">
                    Select products to include in this section. Existing selections are checked.
                </p>
                <?php
                $existingIds = array_column($curatedItems, 'item_id');
                ?>
                <table class="product-picker-table">
                    <thead>
                        <tr>
                            <th width="40"></th>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conciseProducts as $cp): 
                            $isSelected = in_array((int)$cp['id'], $existingIds, true);
                        ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="product_ids[]" value="<?= $cp['id'] ?>" <?= $isSelected ? 'checked' : '' ?>>
                                </td>
                                <td>
                                    <div style="font-weight: 600; font-size: 12px;"><?= e($cp['name']) ?></div>
                                    <div style="font-size: 10px; color: var(--text-muted);"><?= e($cp['sku']) ?></div>
                                </td>
                                <td><?= e($cp['category_name']) ?></td>
                                <td><?= format_price($cp['selling_price']) ?></td>
                                <td><?= (int)$cp['stock_quantity'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold btn-sm">Save Curated Selection</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php elseif ($isLegacyView): ?>
<!-- =====================================================================
     VIEW B: LEGACY TABS VIEW (Maintained for full regression compatibility)
     ===================================================================== -->
<?php
// Circular Stories Tab
if ($legacyTab === 'circular_stories'):
    $stories = admin_get_homepage_stories();
?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 600;">Circular Category Stories (Top Horizontal Bar)</h3>
        <button type="button" class="btn btn-gold btn-sm" data-modal-target="storyModal">
            + Add Circular Story
        </button>
    </div>
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Thumbnail</th>
                        <th>Story Title</th>
                        <th>Link URL</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stories as $s): ?>
                        <tr>
                            <td><?= (int)$s['display_order'] ?></td>
                            <td>
                                <img src="<?= e($s['image_url']) ?>" alt="" style="width: 36px; height: 36px; border-radius: 50%; object-fit: cover;">
                            </td>
                            <td><strong><?= e($s['title']) ?></strong></td>
                            <td><code><?= e($s['link_url']) ?></code></td>
                            <td><span class="status-badge <?= $s['is_active'] ? 'active' : 'inactive' ?>"><?= $s['is_active'] ? 'Active' : 'Hidden' ?></span></td>
                            <td>
                                <form action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST" style="margin: 0; display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_story">
                                    <input type="hidden" name="story_id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: #D32F2F; border-color: #D32F2F;" data-confirm="Delete this circular story?">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Add Story -->
    <div class="modal-backdrop" id="storyModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">Add Circular Category Story</h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>
            <form action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_story">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Story Label / Title *</label>
                        <input type="text" name="title" class="form-control" required placeholder="e.g. Sarees">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Destination Link URL *</label>
                        <input type="text" name="link_url" class="form-control" value="/shop.php" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Image URL</label>
                        <input type="text" name="image_url" class="form-control" placeholder="https://...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Or Upload Circular Image</label>
                        <input type="file" name="image_file" class="form-control" accept="image/*">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" class="form-control" value="1">
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Visible on boutique homepage</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-gold btn-sm">Save Story</button>
                </div>
            </form>
        </div>
    </div>

<?php 
// Style Stories Tab
elseif ($legacyTab === 'style_stories'):
    $styleStories = admin_get_style_stories();
?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h3 style="font-size: 16px; font-weight: 600;">Style Stories &amp; Inspirations (Editorial Cards)</h3>
        <button type="button" class="btn btn-gold btn-sm" data-modal-target="styleStoryModal">
            + Add Style Story
        </button>
    </div>
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Tag</th>
                        <th>Read Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($styleStories as $st): ?>
                        <tr>
                            <td><?= (int)$st['display_order'] ?></td>
                            <td><img src="<?= e($st['image_url']) ?>" alt="" style="width: 48px; height: 36px; object-fit: cover; border-radius: 2px;"></td>
                            <td><strong><?= e($st['title']) ?></strong></td>
                            <td><span class="status-badge pending"><?= e($st['tag']) ?></span></td>
                            <td><?= e($st['read_time']) ?></td>
                            <td><span class="status-badge <?= $st['is_active'] ? 'active' : 'inactive' ?>"><?= $st['is_active'] ? 'Active' : 'Hidden' ?></span></td>
                            <td>
                                <form action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST" style="margin: 0; display: inline;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_style_story">
                                    <input type="hidden" name="style_story_id" value="<?= $st['id'] ?>">
                                    <button type="submit" class="btn btn-outline btn-sm" style="color: #D32F2F; border-color: #D32F2F;" data-confirm="Delete this editorial style story?">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Add Style Story -->
    <div class="modal-backdrop" id="styleStoryModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">Add Editorial Style Story</h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>
            <form action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_style_story">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Story Headline *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tag / Category</label>
                        <input type="text" name="tag" class="form-control" value="STYLE EDITORIAL">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Excerpt *</label>
                        <textarea name="excerpt" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Image URL *</label>
                        <input type="text" name="image_url" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Or Upload High-Res Image</label>
                        <input type="file" name="style_image_file" class="form-control" accept="image/*">
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">Link URL</label>
                            <input type="text" name="link_url" class="form-control" value="/shop.php">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Read Time</label>
                            <input type="text" name="read_time" class="form-control" value="4 min read">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Display Order</label>
                        <input type="number" name="display_order" class="form-control" value="1">
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                            <input type="checkbox" name="is_active" value="1" checked>
                            <span>Visible on boutique homepage</span>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-gold btn-sm">Save Editorial Story</button>
                </div>
            </form>
        </div>
    </div>

<?php 
// Product Section Flags Tabs (new_arrivals, bestsellers, summer_midi, trending_coords)
else:
    $flag = $tabToFlag[$legacyTab] ?? 'is_new';
    $tabTitle = $tabTitles[$legacyTab] ?? 'Section Products';
    $sectionProducts = admin_get_section_products($flag);
?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div>
            <h3 style="font-size: 16px; font-weight: 600;"><?= e($tabTitle) ?> (<?= count($sectionProducts) ?> pieces)</h3>
            <span style="font-size: 12px; color: var(--text-muted);">Manage products assigned via boolean flag <code><?= e($flag) ?></code></span>
        </div>
        <button type="button" class="btn btn-gold btn-sm" data-modal-target="addProductModal">
            + Assign Product to Section
        </button>
    </div>

    <div class="card">
        <div class="card-body" style="padding: 0;">
            <?php if (empty($sectionProducts)): ?>
                <div style="padding: 32px; text-align: center; color: var(--text-muted);">
                    No products currently assigned to this section.
                </div>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Creation</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sectionProducts as $p): ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <img src="<?= e($p['primary_image'] ?: 'assets/images/placeholder.jpg') ?>" class="product-thumb" alt="" style="width: 32px; height: 40px; object-fit: cover;">
                                        <div>
                                            <div style="font-weight: 600;"><?= e($p['name']) ?></div>
                                            <div style="font-size: 11px; color: var(--text-muted);"><?= e($p['sku']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e($p['category_name']) ?></td>
                                <td><?= format_price($p['selling_price']) ?></td>
                                <td><?= (int)$p['stock_quantity'] ?></td>
                                <td>
                                    <form action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST" style="margin: 0; display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove_product_from_section">
                                        <input type="hidden" name="section" value="<?= e($legacyTab) ?>">
                                        <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" style="color: #D32F2F; border-color: #D32F2F; padding: 4px 8px; font-size: 11px;">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal: Add Product to Section -->
    <div class="modal-backdrop" id="addProductModal">
        <div class="modal-dialog">
            <div class="modal-header">
                <h3 class="modal-title">Assign Creation to <?= e($tabTitle) ?></h3>
                <button type="button" class="modal-close" data-modal-close>&times;</button>
            </div>
            <form action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_product_to_section">
                <input type="hidden" name="section" value="<?= e($legacyTab) ?>">
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Select Creation *</label>
                        <select name="product_id" class="form-control" required>
                            <option value="">-- Choose Catalogue Piece --</option>
                            <?php foreach ($conciseProducts as $cp): ?>
                                <option value="<?= $cp['id'] ?>"><?= e($cp['name']) ?> (<?= e($cp['sku']) ?> - <?= format_price($cp['selling_price']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                    <button type="submit" class="btn btn-gold btn-sm">Add to Section</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php else: ?>
<!-- =====================================================================
     VIEW C: MASTER HOMEPAGE SECTIONS MANAGER (Drag & Drop Reordering)
     ===================================================================== -->
<?php
$sections = admin_get_homepage_sections();
?>
<form action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST" id="reorderForm">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reorder_sections">

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <div>
            <span style="font-size: 13px; color: var(--text-muted);">
                Drag sections to reorder or use arrow controls. Changes apply immediately to the live boutique upon saving.
            </span>
        </div>
        <button type="submit" class="btn btn-gold btn-sm">
            Save Section Sequence &amp; Synchronize
        </button>
    </div>

    <div id="sectionSortingList">
        <?php foreach ($sections as $index => $sec): ?>
            <div class="section-item-card" data-id="<?= $sec['id'] ?>">
                <!-- Drag Handle -->
                <div class="drag-handle" title="Drag to reorder">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="5" r="1"></circle>
                        <circle cx="9" cy="12" r="1"></circle>
                        <circle cx="9" cy="19" r="1"></circle>
                        <circle cx="15" cy="5" r="1"></circle>
                        <circle cx="15" cy="12" r="1"></circle>
                        <circle cx="15" cy="19" r="1"></circle>
                    </svg>
                </div>

                <!-- Order Badge -->
                <div class="section-order-badge">
                    <?= $index + 1 ?>
                </div>
                <input type="hidden" name="order[<?= $sec['id'] ?>]" value="<?= $index + 1 ?>">

                <!-- Move Up / Down Buttons -->
                <div style="display: flex; flex-direction: column; gap: 2px;">
                    <button type="button" class="btn-move-up" title="Move Up" style="background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 1px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="18 15 12 9 6 15"></polyline>
                        </svg>
                    </button>
                    <button type="button" class="btn-move-down" title="Move Down" style="background: none; border: none; cursor: pointer; color: var(--text-muted); padding: 1px;">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </button>
                </div>

                <!-- Title & Meta -->
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <strong style="font-size: 14px; color: var(--text-main);"><?= e($sec['title']) ?></strong>
                        <span class="section-type-pill"><?= e($sec['section_type']) ?></span>
                        <span class="status-pill <?= e($sec['publish_status']) ?>">
                            <?= ucfirst(e($sec['publish_status'])) ?>
                        </span>
                        <span class="status-pill <?= $sec['is_active'] ? 'active' : 'inactive' ?>">
                            <?= $sec['is_active'] ? 'Active' : 'Disabled' ?>
                        </span>
                    </div>
                    <div style="font-size: 12px; color: var(--text-muted); margin-top: 3px;">
                        Key: <code><?= e($sec['section_key']) ?></code> &bull; 
                        Mode: <?= ucfirst(e($sec['selection_mode'])) ?> &bull;
                        Curated items: <?= (int)$sec['item_count'] ?> &bull;
                        Last updated: <?= date('d M Y, h:i A', strtotime($sec['updated_at'])) ?>
                    </div>
                </div>

                <!-- Actions Group -->
                <div style="display: flex; align-items: center; gap: 8px;">
                    <!-- Edit Button -->
                    <a href="<?= ADMIN_URL ?>/homepage-sections.php?edit=<?= $sec['id'] ?>" class="btn btn-outline btn-sm">
                        Edit Content &amp; Design
                    </a>

                    <!-- Preview Section Anchor -->
                    <a href="<?= BASE_URL ?>/index.php?preview=1#<?= e($sec['section_key']) ?>" target="_blank" class="btn btn-outline btn-sm" title="Preview on boutique homepage">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </a>

                    <!-- Publish Button -->
                    <?php if ($sec['publish_status'] === 'draft'): ?>
                        <button type="submit" form="pubForm_<?= $sec['id'] ?>" class="btn btn-gold btn-sm" title="Publish draft to live boutique">
                            Publish
                        </button>
                    <?php endif; ?>

                    <!-- Duplicate Button -->
                    <button type="submit" form="dupForm_<?= $sec['id'] ?>" class="btn btn-outline btn-sm" title="Duplicate section">
                        Copy
                    </button>

                    <!-- Toggle Visibility -->
                    <button type="submit" form="togForm_<?= $sec['id'] ?>" class="btn btn-outline btn-sm" style="color: <?= $sec['is_active'] ? '#ED6C02' : '#2E7D32' ?>;">
                        <?= $sec['is_active'] ? 'Disable' : 'Enable' ?>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</form>

<!-- Hidden auxiliary action forms -->
<?php foreach ($sections as $sec): ?>
    <form id="pubForm_<?= $sec['id'] ?>" action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="publish_section">
        <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
    </form>
    <form id="dupForm_<?= $sec['id'] ?>" action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="duplicate_section">
        <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
    </form>
    <form id="togForm_<?= $sec['id'] ?>" action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST" style="display: none;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_section_status">
        <input type="hidden" name="section_id" value="<?= $sec['id'] ?>">
        <input type="hidden" name="is_active" value="<?= $sec['is_active'] ? '0' : '1' ?>">
    </form>
<?php endforeach; ?>

<!-- Modal: Add New Section -->
<div class="modal-backdrop" id="newSectionModal">
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 class="modal-title">Create New Homepage Section</h3>
            <button type="button" class="modal-close" data-modal-close>&times;</button>
        </div>
        <form action="<?= ADMIN_URL ?>/homepage-sections.php" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save_section">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Section Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Festive Silk Capsule" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Section Type *</label>
                    <select name="section_type" class="form-control" required>
                        <option value="product_collection">Product Collection Grid (Curated Creations)</option>
                        <option value="hero_slider">Hero Banner Slider</option>
                        <option value="category_grid">Category Showcase Grid</option>
                        <option value="editorial_banner">Editorial Promotional Banner</option>
                        <option value="brand_story">Brand Story / Atelier Pillars</option>
                        <option value="testimonials">Customer Reviews & Reflections</option>
                        <option value="newsletter">Privileged Newsletter</option>
                        <option value="taxonomy_bar">Circular Taxonomy Stories Bar</option>
                        <option value="style_stories">Editorial Style Stories</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Eyebrow / Subtitle</label>
                    <input type="text" name="eyebrow" class="form-control" placeholder="e.g. Handcrafted Luxury">
                </div>
                <div class="form-group">
                    <label class="form-label">Initial Publishing Status</label>
                    <select name="publish_status" class="form-control">
                        <option value="published">Published Immediately</option>
                        <option value="draft" selected>Draft (Preview First)</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline btn-sm" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-gold btn-sm">Create Section</button>
            </div>
        </form>
    </div>
</div>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
