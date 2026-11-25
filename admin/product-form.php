<?php
/**
 * YATIYA COUTURE - Add / Edit Product Dossier
 * Comprehensive Luxury Fashion Product Editor
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin-functions.php';

require_admin();

$productId = !empty($_GET['id']) ? (int)$_GET['id'] : null;
$product = null;
if ($productId) {
    $product = admin_get_product($productId);
    if (!$product) {
        set_flash('error', 'Creation not found in catalogue.');
        redirect(ADMIN_URL . '/products.php');
    }
}

$isEdit = ($product !== null);
$adminPageTitle = $isEdit ? 'Edit Creation: ' . $product['name'] : 'Create New Haute Couture Piece';
$activeMenu = 'products';

$error = null;

// Form Submission Handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $action = clean_input($_POST['action'] ?? 'save_product');

        // Delete Single Image Action
        if ($action === 'delete_image') {
            $imgId = (int)($_POST['image_id'] ?? 0);
            if ($imgId > 0 && admin_delete_product_image($imgId)) {
                set_flash('success', 'Product gallery image removed.');
            } else {
                set_flash('error', 'Failed to remove image.');
            }
            redirect(ADMIN_URL . "/product-form.php?id=$productId#sectionImages");
        }

        // Set Primary Image Action
        if ($action === 'set_primary_image') {
            $imgId = (int)($_POST['image_id'] ?? 0);
            if ($imgId > 0 && $productId && admin_set_primary_product_image($productId, $imgId)) {
                set_flash('success', 'Primary showcase image updated.');
            } else {
                set_flash('error', 'Failed to set primary image.');
            }
            redirect(ADMIN_URL . "/product-form.php?id=$productId#sectionImages");
        }

        // Save / Update Master Product
        if ($action === 'save_product') {
            // Process specifications into JSON if entered as separate fields or raw JSON
            $specsJson = trim($_POST['specifications'] ?? '');
            if (empty($specsJson) || json_decode($specsJson) === null) {
                $specsArray = [];
                if (!empty($_POST['spec_fabric'])) $specsArray['Fabric'] = clean_input($_POST['spec_fabric']);
                if (!empty($_POST['spec_fit'])) $specsArray['Fit'] = clean_input($_POST['spec_fit']);
                if (!empty($_POST['spec_care'])) $specsArray['Care'] = clean_input($_POST['spec_care']);
                if (!empty($_POST['spec_weave'])) $specsArray['Weave'] = clean_input($_POST['spec_weave']);
                if (!empty($_POST['spec_occasion'])) $specsArray['Occasion'] = clean_input($_POST['spec_occasion']);
                $specsJson = !empty($specsArray) ? json_encode($specsArray) : ($specsJson ?: null);
            }

            $data = [
                'name' => clean_input($_POST['name'] ?? ''),
                'sku' => strtoupper(clean_input($_POST['sku'] ?? '')),
                'slug' => clean_input($_POST['slug'] ?? ''),
                'category_id' => (int)($_POST['category_id'] ?? 0),
                'brand_id' => !empty($_POST['brand_id']) ? (int)$_POST['brand_id'] : null,
                'original_price' => (float)($_POST['original_price'] ?? 0),
                'selling_price' => (float)($_POST['selling_price'] ?? 0),
                'cost_price' => !empty($_POST['cost_price']) ? (float)$_POST['cost_price'] : null,
                'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
                'low_stock_threshold' => (int)($_POST['low_stock_threshold'] ?? 5),
                'status' => clean_input($_POST['status'] ?? 'active'),
                'is_featured' => !empty($_POST['is_featured']) ? 1 : 0,
                'is_new' => !empty($_POST['is_new']) ? 1 : 0,
                'is_bestseller' => !empty($_POST['is_bestseller']) ? 1 : 0,
                'is_summer_midi' => !empty($_POST['is_summer_midi']) ? 1 : 0,
                'is_trending_coord' => !empty($_POST['is_trending_coord']) ? 1 : 0,
                'short_description' => clean_input($_POST['short_description'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'specifications' => $specsJson
            ];

            // Process variant rows
            $variants = [];
            if (!empty($_POST['variants']) && is_array($_POST['variants'])) {
                foreach ($_POST['variants'] as $v) {
                    if (empty($v['size']) && empty($v['color'])) continue;
                    $variants[] = [
                        'id' => !empty($v['id']) ? (int)$v['id'] : null,
                        'sku' => clean_input($v['sku'] ?? ''),
                        'size' => clean_input($v['size'] ?? ''),
                        'color' => clean_input($v['color'] ?? ''),
                        'color_code' => clean_input($v['color_code'] ?? '#000000'),
                        'additional_price' => (float)($v['additional_price'] ?? 0),
                        'stock_quantity' => max(0, (int)($v['stock_quantity'] ?? 0)),
                        'image_url' => clean_input($v['image_url'] ?? ''),
                        'status' => clean_input($v['status'] ?? 'active')
                    ];
                }
            }

            // Process image upload if provided (single or multiple)
            $images = [];
            
            // 1. Single primary upload
            if (!empty($_FILES['image_file']['name'])) {
                $uploadRes = admin_validate_image_upload($_FILES['image_file'], 'products');
                if ($uploadRes['success']) {
                    $images[] = [
                        'image_url' => $uploadRes['url'],
                        'alt_text' => $data['name'],
                        'sort_order' => 0,
                        'is_primary' => 1
                    ];
                } else {
                    $error = 'Image Upload Error: ' . $uploadRes['message'];
                }
            }

            // 2. Direct Primary URL
            if (!empty($_POST['image_url'])) {
                $images[] = [
                    'image_url' => clean_input($_POST['image_url']),
                    'alt_text' => $data['name'],
                    'sort_order' => 0,
                    'is_primary' => empty($images) ? 1 : 0
                ];
            }

            // 3. Hover Image URL
            if (!empty($_POST['hover_image_url'])) {
                $images[] = [
                    'image_url' => clean_input($_POST['hover_image_url']),
                    'alt_text' => $data['name'] . ' Alternate Angle',
                    'sort_order' => 1,
                    'is_primary' => 0
                ];
            }

            // 4. Multiple Gallery Files Upload
            if (!empty($_FILES['gallery_files']['name']) && is_array($_FILES['gallery_files']['name'])) {
                $fileCount = count($_FILES['gallery_files']['name']);
                for ($i = 0; $i < $fileCount; $i++) {
                    if (!empty($_FILES['gallery_files']['name'][$i])) {
                        $singleFile = [
                            'name' => $_FILES['gallery_files']['name'][$i],
                            'type' => $_FILES['gallery_files']['type'][$i],
                            'tmp_name' => $_FILES['gallery_files']['tmp_name'][$i],
                            'error' => $_FILES['gallery_files']['error'][$i],
                            'size' => $_FILES['gallery_files']['size'][$i]
                        ];
                        $upRes = admin_validate_image_upload($singleFile, 'products');
                        if ($upRes['success']) {
                            $images[] = [
                                'image_url' => $upRes['url'],
                                'alt_text' => $data['name'] . ' Gallery Angle ' . ($i + 1),
                                'sort_order' => $i + 2,
                                'is_primary' => 0
                            ];
                        }
                    }
                }
            }

            if (!$error) {
                if ($isEdit) {
                    $res = admin_update_product($productId, $data, $variants, $images);
                } else {
                    $res = admin_create_product($data, $variants, $images);
                }

                if ($res['success']) {
                    set_flash('success', $res['message']);
                    redirect(ADMIN_URL . '/products.php');
                } else {
                    $error = $res['message'];
                }
            }
        }
    }
}

$categories = admin_get_categories();
$brands = admin_get_brands();

// Parse existing specifications if JSON
$existingSpecs = [];
if (!empty($product['specifications'])) {
    $decoded = json_decode($product['specifications'], true);
    if (is_array($decoded)) {
        $existingSpecs = $decoded;
    }
}

require_once __DIR__ . '/includes/admin-header.php';
?>

<div style="max-width: 1040px; margin: 0 auto;">
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px;">
        <div>
            <a href="<?= ADMIN_URL ?>/products.php" style="color: var(--text-muted); font-size: 12px; text-decoration: none;">
                &larr; Back to Catalogue
            </a>
            <h2 style="font-family: var(--font-heading); font-size: 22px; font-weight: 600; margin-top: 4px;">
                <?= $isEdit ? 'Refine Creation: ' . e($product['name']) : 'Register New Atelier Creation' ?>
            </h2>
        </div>
        <?php if ($isEdit): ?>
            <a href="<?= BASE_URL ?>/product.php?slug=<?= urlencode($product['slug']) ?>" target="_blank" class="btn btn-outline btn-sm">
                View in Boutique &rarr;
            </a>
        <?php endif; ?>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <span><?= e($error) ?></span>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_product">

        <!-- 1. Master Identification Card -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">1. Essential Identification & Hierarchy</h3>
            </div>
            <div class="card-body">
                <div class="form-row" style="margin-bottom: 16px;">
                    <div style="grid-column: span 2;">
                        <label class="form-label" for="prodName">Creation Title *</label>
                        <input type="text" id="prodName" name="name" class="form-control" placeholder="e.g. Royal Banarasi Silk Zari Saree" value="<?= e($_POST['name'] ?? ($product['name'] ?? '')) ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="prodSku">Master SKU *</label>
                        <input type="text" id="prodSku" name="sku" class="form-control" placeholder="e.g. YTY-WMN-SAR-001" value="<?= e($_POST['sku'] ?? ($product['sku'] ?? '')) ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label" for="prodCat">Category Salon *</label>
                        <select id="prodCat" name="category_id" class="form-control" required>
                            <option value="">Select Category...</option>
                            <?php 
                            $selectedCat = (int)($_POST['category_id'] ?? ($product['category_id'] ?? 0));
                            foreach ($categories as $cat): 
                            ?>
                                <option value="<?= $cat['id'] ?>" <?= ($selectedCat === (int)$cat['id']) ? 'selected' : '' ?>>
                                    <?= e($cat['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="prodBrand">Atelier Line / Brand</label>
                        <select id="prodBrand" name="brand_id" class="form-control">
                            <option value="">House Line (Yatiya Couture)</option>
                            <?php 
                            $selectedBrand = (int)($_POST['brand_id'] ?? ($product['brand_id'] ?? 0));
                            foreach ($brands as $b): 
                            ?>
                                <option value="<?= $b['id'] ?>" <?= ($selectedBrand === (int)$b['id']) ? 'selected' : '' ?>>
                                    <?= e($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="form-label" for="prodSlug">URL Slug (Auto-generated if empty)</label>
                        <input type="text" id="prodSlug" name="slug" class="form-control" placeholder="royal-banarasi-silk-zari-saree" value="<?= e($_POST['slug'] ?? ($product['slug'] ?? '')) ?>">
                    </div>

                    <div>
                        <label class="form-label" for="prodStatus">Catalogue Status</label>
                        <?php $selectedStatus = $_POST['status'] ?? ($product['status'] ?? 'active'); ?>
                        <select id="prodStatus" name="status" class="form-control">
                            <option value="active" <?= ($selectedStatus === 'active') ? 'selected' : '' ?>>Active (Visible in boutique)</option>
                            <option value="draft" <?= ($selectedStatus === 'draft') ? 'selected' : '' ?>>Draft (Hidden in boutique)</option>
                            <option value="archived" <?= ($selectedStatus === 'archived') ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Pricing & Base Inventory -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">2. Pricing Structure & Homepage Placement</h3>
            </div>
            <div class="card-body">
                <div class="form-row">
                    <div>
                        <label class="form-label" for="origPrice">Original Atelier Price (₹) *</label>
                        <input type="number" id="origPrice" name="original_price" step="0.01" min="0" class="form-control" placeholder="25000.00" value="<?= e($_POST['original_price'] ?? ($product['original_price'] ?? '')) ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="sellPrice">Selling Price (₹) *</label>
                        <input type="number" id="sellPrice" name="selling_price" step="0.01" min="0" class="form-control" placeholder="22500.00" value="<?= e($_POST['selling_price'] ?? ($product['selling_price'] ?? '')) ?>" required>
                    </div>
                    <div>
                        <label class="form-label" for="costPrice">Production Cost Price (₹)</label>
                        <input type="number" id="costPrice" name="cost_price" step="0.01" min="0" class="form-control" placeholder="Optional internal cost" value="<?= e($_POST['cost_price'] ?? ($product['cost_price'] ?? '')) ?>">
                    </div>
                    <div>
                        <label class="form-label" for="lowThreshold">Low-Stock Alert Level</label>
                        <input type="number" id="lowThreshold" name="low_stock_threshold" min="1" class="form-control" value="<?= e($_POST['low_stock_threshold'] ?? ($product['low_stock_threshold'] ?? '5')) ?>" required>
                    </div>
                </div>

                <div style="margin-top: 20px; padding: 16px; background: #FAF8F5; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                    <div style="font-weight: 600; font-size: 13px; color: var(--gold); margin-bottom: 10px;">
                        Homepage Section Curation Tags:
                    </div>
                    <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="is_featured" value="1" <?= (!empty($_POST['is_featured']) || !empty($product['is_featured'])) ? 'checked' : '' ?>>
                            <span>Feature on Homepage Showcase</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="is_new" value="1" <?= (isset($_POST['is_new']) ? !empty($_POST['is_new']) : ($product['is_new'] ?? 1)) ? 'checked' : '' ?>>
                            <span>New Arrivals Runway (Section 3)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="is_bestseller" value="1" <?= (!empty($_POST['is_bestseller']) || !empty($product['is_bestseller'])) ? 'checked' : '' ?>>
                            <span>House Best Sellers (Section 5)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="is_summer_midi" value="1" <?= (!empty($_POST['is_summer_midi']) || !empty($product['is_summer_midi'])) ? 'checked' : '' ?>>
                            <span>Summer Midi Capsule (Section 7)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer;">
                            <input type="checkbox" name="is_trending_coord" value="1" <?= (!empty($_POST['is_trending_coord']) || !empty($product['is_trending_coord'])) ? 'checked' : '' ?>>
                            <span>Trending Co-ord Sets (Section 4)</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>

        <!-- 3. Editorial Copy, Material & Care Details -->
        <div class="card" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">3. Editorial Narrative & Artisanal Specifications</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label" for="shortDesc">Brief Summary (Used in cards & listings)</label>
                    <textarea id="shortDesc" name="short_description" class="form-control" rows="2" placeholder="Handcrafted pure zari embroidery on imperial silk..."><?= e($_POST['short_description'] ?? ($product['short_description'] ?? '')) ?></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label" for="fullDesc">Full Atelier Description</label>
                    <textarea id="fullDesc" name="description" class="form-control" rows="4" placeholder="Detailed editorial narrative detailing the silhouette, weave, and heritage..."><?= e($_POST['description'] ?? ($product['description'] ?? '')) ?></textarea>
                </div>

                <!-- Structured Artisanal Specifications -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 16px; padding: 16px; background: #FAF8F5; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                    <div class="form-group">
                        <label class="form-label">Fabric / Weave</label>
                        <input type="text" name="spec_fabric" class="form-control" value="<?= e($existingSpecs['Fabric'] ?? '') ?>" placeholder="e.g. 100% Pure Mulberry Silk">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fit / Silhouette</label>
                        <input type="text" name="spec_fit" class="form-control" value="<?= e($existingSpecs['Fit'] ?? '') ?>" placeholder="e.g. Tailored Relaxed Fit">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Embroidery / Craft</label>
                        <input type="text" name="spec_weave" class="form-control" value="<?= e($existingSpecs['Weave'] ?? '') ?>" placeholder="e.g. Antique Gold Kadwa Zari">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Material &amp; Wash Care</label>
                        <input type="text" name="spec_care" class="form-control" value="<?= e($existingSpecs['Care'] ?? '') ?>" placeholder="e.g. Strictly Dry Clean Only">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 16px; margin-bottom: 0;">
                    <label class="form-label" for="specs">Raw Specifications (Optional JSON override)</label>
                    <textarea id="specs" name="specifications" class="form-control" rows="2" placeholder='{"Fabric": "Pure Silk", "Weave": "Banarasi"}'><?= e($_POST['specifications'] ?? ($product['specifications'] ?? '')) ?></textarea>
                </div>
            </div>
        </div>

        <!-- 4. Imagery & Gallery -->
        <div class="card" id="sectionImages" style="margin-bottom: 24px;">
            <div class="card-header">
                <h3 class="card-title">4. Imagery, Gallery Angles & Visual Assets</h3>
            </div>
            <div class="card-body">
                <?php if (!empty($product['images'])): ?>
                    <div style="margin-bottom: 24px;">
                        <label class="form-label" style="font-weight: 600;">Current Product Gallery (<?= count($product['images']) ?> angles)</label>
                        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <?php foreach ($product['images'] as $img): ?>
                                <div style="position: relative; border: 1px solid var(--border-color); border-radius: var(--radius-sm); padding: 6px; background: #fff; width: 110px; text-align: center;">
                                    <img src="<?= e($img['image_url']) ?>" style="width: 100%; height: 130px; object-fit: cover; border-radius: 2px;" alt="">
                                    <?php if ($img['is_primary']): ?>
                                        <div style="background: var(--gold); color: #1C1A17; font-size: 9px; font-weight: 700; margin-top: 4px; border-radius: 2px; padding: 2px;">PRIMARY</div>
                                    <?php else: ?>
                                        <button type="submit" form="setPrimForm_<?= $img['id'] ?>" class="btn btn-outline btn-sm" style="font-size: 9px; padding: 2px 4px; width: 100%; margin-top: 4px;">Make Primary</button>
                                    <?php endif; ?>
                                    <button type="submit" form="delImgForm_<?= $img['id'] ?>" class="btn btn-outline btn-sm" style="color: #D32F2F; border-color: #D32F2F; font-size: 9px; padding: 2px 4px; width: 100%; margin-top: 4px;" data-confirm="Remove this image?">Delete</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-row" style="margin-bottom: 16px;">
                    <div>
                        <label class="form-label" for="imageFile">Upload Primary High-Res Photo</label>
                        <input type="file" id="imageFile" name="image_file" class="form-control" accept="image/jpeg,image/png,image/webp" data-preview-target="uploadPreview">
                        <img id="uploadPreview" src="" alt="Upload Preview" style="display:none; width: 80px; height: 100px; object-fit: cover; margin-top: 10px; border-radius: 4px; border: 1px solid var(--gold);">
                    </div>

                    <div>
                        <label class="form-label" for="imageUrl">Or Provide Direct Primary Image URL</label>
                        <input type="url" id="imageUrl" name="image_url" class="form-control" placeholder="https://images.unsplash.com/photo-...">
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label class="form-label" for="hoverImg">Alternate Hover Image URL (Second Angle)</label>
                        <input type="url" id="hoverImg" name="hover_image_url" class="form-control" placeholder="https://images.unsplash.com/photo-...">
                    </div>
                    <div>
                        <label class="form-label">Upload Additional Gallery Angles (Multiple Files)</label>
                        <input type="file" name="gallery_files[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Product Variants (Sizes, Colors, Inventory) -->
        <div class="card" style="margin-bottom: 32px;">
            <div class="card-header">
                <h3 class="card-title">5. Variants, Sizes, Colors & Stock Quantities</h3>
                <button type="button" class="btn btn-outline btn-sm" id="btnAddVariantRow">+ Add Variant Row</button>
            </div>
            <div class="card-body">
                <div id="variantsContainer">
                    <?php 
                    $existingVariants = $product['variants'] ?? [];
                    if (empty($existingVariants)): 
                    ?>
                        <div class="variant-row form-row" style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #E8E2D5;">
                            <div>
                                <label class="form-label">Size</label>
                                <input type="text" name="variants[0][size]" class="form-control" placeholder="e.g. S, M, L, XL" value="Free Size">
                            </div>
                            <div>
                                <label class="form-label">Color</label>
                                <input type="text" name="variants[0][color]" class="form-control" placeholder="e.g. Royal Ivory" value="Gold / Ivory">
                            </div>
                            <div>
                                <label class="form-label">Color Code</label>
                                <input type="color" name="variants[0][color_code]" class="form-control" value="#C6A15B" style="height:38px;padding:2px;">
                            </div>
                            <div>
                                <label class="form-label">SKU</label>
                                <input type="text" name="variants[0][sku]" class="form-control" placeholder="Auto-generated if blank">
                            </div>
                            <div>
                                <label class="form-label">Stock Quantity</label>
                                <input type="number" name="variants[0][stock_quantity]" class="form-control" min="0" value="10" required>
                            </div>
                            <div>
                                <label class="form-label">Add'l Price (₹)</label>
                                <input type="number" name="variants[0][additional_price]" class="form-control" step="0.01" value="0.00">
                            </div>
                            <div style="display:flex;align-items:flex-end;">
                                <button type="button" class="btn btn-outline btn-sm btn-remove-variant" style="color:#D32F2F;border-color:#D32F2F;">Remove</button>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($existingVariants as $idx => $v): ?>
                            <div class="variant-row form-row" style="margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px dashed #E8E2D5;">
                                <input type="hidden" name="variants[<?= $idx ?>][id]" value="<?= $v['id'] ?>">
                                <div>
                                    <label class="form-label">Size</label>
                                    <input type="text" name="variants[<?= $idx ?>][size]" class="form-control" value="<?= e($v['size']) ?>" required>
                                </div>
                                <div>
                                    <label class="form-label">Color</label>
                                    <input type="text" name="variants[<?= $idx ?>][color]" class="form-control" value="<?= e($v['color']) ?>" required>
                                </div>
                                <div>
                                    <label class="form-label">Color Code</label>
                                    <input type="color" name="variants[<?= $idx ?>][color_code]" class="form-control" value="<?= e($v['color_code'] ?: '#C6A15B') ?>" style="height:38px;padding:2px;">
                                </div>
                                <div>
                                    <label class="form-label">SKU</label>
                                    <input type="text" name="variants[<?= $idx ?>][sku]" class="form-control" value="<?= e($v['sku']) ?>">
                                </div>
                                <div>
                                    <label class="form-label">Stock Quantity</label>
                                    <input type="number" name="variants[<?= $idx ?>][stock_quantity]" class="form-control" min="0" value="<?= (int)$v['stock_quantity'] ?>" required>
                                </div>
                                <div>
                                    <label class="form-label">Add'l Price (₹)</label>
                                    <input type="number" name="variants[<?= $idx ?>][additional_price]" class="form-control" step="0.01" value="<?= (float)$v['additional_price'] ?>">
                                </div>
                                <div style="display:flex;align-items:flex-end;">
                                    <button type="button" class="btn btn-outline btn-sm btn-remove-variant" style="color:#D32F2F;border-color:#D32F2F;">Remove</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Submit & Actions -->
        <div style="display: flex; gap: 16px; justify-content: flex-end; margin-bottom: 48px;">
            <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-outline">Cancel & Return</a>
            <button type="submit" class="btn btn-gold" style="padding: 12px 32px; font-size: 14px;">
                <?= $isEdit ? 'Update Atelier Creation' : 'Publish Creation to Boutique' ?>
            </button>
        </div>
    </form>
</div>

<!-- Auxiliary image action forms -->
<?php if (!empty($product['images'])): ?>
    <?php foreach ($product['images'] as $img): ?>
        <form id="delImgForm_<?= $img['id'] ?>" action="" method="POST" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_image">
            <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
        </form>
        <form id="setPrimForm_<?= $img['id'] ?>" action="" method="POST" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_primary_image">
            <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
        </form>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/admin-footer.php'; ?>
