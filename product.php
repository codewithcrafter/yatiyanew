<?php
/**
 * YATIYA COUTURE - Haute Couture Product Details Dossier
 * Brand Vision: "ELEVATE YOUR EVERYDAY STYLE"
 * Phase 3 Complete Product Catalogue System
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/product-functions.php';

// ---------------------------------------------------------------------
// 1. RESOLVE PRODUCT BY SLUG OR ID
// ---------------------------------------------------------------------
$slug = clean_input($_GET['slug'] ?? '');
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

$product = null;
if (!empty($slug)) {
    $product = get_product_by_slug($slug);
} elseif ($id > 0) {
    // Lookup slug by ID and redirect or load
    $stmt = db()->prepare("SELECT slug FROM products WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$id]);
    $foundSlug = $stmt->fetchColumn();
    if ($foundSlug) {
        $product = get_product_by_slug($foundSlug);
    }
}

// Handle Invalid / Not Found Products gracefully
if (!$product) {
    http_response_code(404);
    $pageTitle = 'Piece Not Found | ' . SITE_NAME;
    require_once __DIR__ . '/includes/header.php';
    ?>
    <div class="container section-padding text-center">
        <div class="editorial-empty-state" style="margin: var(--space-3xl) auto;">
            <div class="empty-icon" style="font-size: 2.5rem; color: var(--gold-primary); margin-bottom: 12px;">❖</div>
            <span class="empty-state-badge">CREATION ARCHIVED OR NOT FOUND</span>
            <h1 class="empty-state-title font-serif">Atelier Piece Unavailable</h1>
            <p class="empty-state-desc">
                The couture creation you are seeking may have been acquired into private patron archives or relocated. Please explore our active collections.
            </p>
            <div class="empty-state-actions">
                <a href="<?= BASE_URL ?>/shop.php" class="btn btn-gold">Explore All Collections</a>
                <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline">Return to Atelier Home</a>
            </div>
        </div>
    </div>
    <?php
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// ---------------------------------------------------------------------
// 2. EXTRACT METADATA & DATA STRUCTURES
// ---------------------------------------------------------------------
$title = e($product['name']);
$sku = e($product['sku']);
$categoryName = e($product['category_name']);
$categorySlug = e($product['category_slug']);
$parentCatName = e($product['parent_category_name'] ?? '');
$parentCatSlug = e($product['parent_category_slug'] ?? '');
$brandName = e($product['brand_name'] ?? 'Yatiya Couture');
$sellingPrice = (float)$product['selling_price'];
$originalPrice = (float)$product['original_price'];
$discount = calculate_discount_percentage($originalPrice, $sellingPrice);
$totalStock = (int)$product['stock_quantity'];
$images = $product['images'];
$variants = $product['variants'];
$specs = $product['specs_array'];

// SEO Metadata
$pageTitle = $title . ' — ' . $brandName;
$pageDescription = !empty($product['short_description']) 
    ? e($product['short_description']) 
    : 'Acquire ' . $title . ' handcrafted by Yatiya Couture. Woven from pure silks with heirloom embroidery.';
$productUrl = BASE_URL . '/product.php?slug=' . urlencode($product['slug']);

// Fetch Related Products
$relatedProducts = get_related_products((int)$product['id'], (int)$product['category_id'], 4);

// Group Variants by Size & Color for interactive UI
$availableSizes = [];
$availableColors = [];
$variantsJson = [];

foreach ($variants as $v) {
    if ($v['size'] && !in_array($v['size'], $availableSizes, true)) {
        $availableSizes[] = $v['size'];
    }
    if ($v['color']) {
        $colorKey = $v['color'];
        if (!isset($availableColors[$colorKey])) {
            $availableColors[$colorKey] = [
                'name' => $v['color'],
                'code' => $v['color_code'] ?: '#C6A15B'
            ];
        }
    }

    $variantsJson[] = [
        'id'               => (int)$v['id'],
        'sku'              => $v['sku'],
        'size'             => $v['size'],
        'color'            => $v['color'],
        'stock'            => (int)$v['stock_quantity'],
        'additional_price' => (float)$v['additional_price']
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Product Details Master Stylesheet -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/product.css">

<!-- JSON-LD Structured Data for Product SEO -->
<script type="application/ld+json">
{
  "@context": "https://schema.org/",
  "@type": "Product",
  "name": <?= json_encode($product['name']) ?>,
  "image": <?= json_encode(!empty($images) ? $images[0]['image_url'] : '') ?>,
  "description": <?= json_encode($product['short_description'] ?? '') ?>,
  "sku": <?= json_encode($product['sku']) ?>,
  "brand": {
    "@type": "Brand",
    "name": <?= json_encode($brandName) ?>
  },
  "offers": {
    "@type": "Offer",
    "url": <?= json_encode($productUrl) ?>,
    "priceCurrency": "INR",
    "price": <?= json_encode($sellingPrice) ?>,
    "availability": <?= json_encode($totalStock > 0 ? "https://schema.org/InStock" : "https://schema.org/OutOfStock") ?>,
    "itemCondition": "https://schema.org/NewCondition"
  }
}
</script>

<div class="product-page-wrapper">
    <div class="container">
        
        <!-- 1. BREADCRUMB NAVIGATION -->
        <nav class="breadcrumb-nav" aria-label="Product Breadcrumb">
            <ol class="breadcrumb-list">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/shop.php">Collections</a></li>
                <?php if ($parentCatName): ?>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/shop.php?category=<?= urlencode($parentCatSlug) ?>"><?= $parentCatName ?></a></li>
                <?php endif; ?>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/shop.php?category=<?= urlencode($categorySlug) ?>"><?= $categoryName ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= $title ?></li>
            </ol>
        </nav>

        <!-- 2. MAIN DOSSIER: GALLERY (LEFT) + COMMERCE DETAILS (RIGHT) -->
        <div class="product-dossier-grid">
            
            <!-- LEFT: INTERACTIVE IMAGE GALLERY & ZOOM -->
            <div class="product-gallery-pane">
                <div class="gallery-wrapper">
                    <!-- Thumbnails Strip -->
                    <?php if (count($images) > 1): ?>
                        <div class="gallery-thumbnails" role="tablist" aria-label="Product alternate angles">
                            <?php foreach ($images as $idx => $img): ?>
                                <button 
                                    type="button" 
                                    class="gallery-thumb-btn <?= ($idx === 0) ? 'active' : '' ?>"
                                    data-full-image="<?= e($img['image_url']) ?>"
                                    data-zoom-image="<?= e($img['image_url']) ?>"
                                    aria-label="View Angle <?= $idx + 1 ?>"
                                >
                                    <img src="<?= e($img['image_url']) ?>" alt="Thumbnail <?= $idx + 1 ?>" loading="lazy">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Main Showcase Image with Zoom Lens -->
                    <div class="gallery-main-stage" id="galleryMainStage">
                        <?php 
                            $mainImgUrl = !empty($images) ? e($images[0]['image_url']) : 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1200&q=80';
                        ?>
                        <div class="zoom-image-container" id="zoomContainer">
                            <img 
                                src="<?= $mainImgUrl ?>" 
                                alt="<?= $title ?>" 
                                id="productMainImg" 
                                class="product-stage-image"
                                data-zoom="<?= $mainImgUrl ?>"
                            >
                            <div class="zoom-lens" id="zoomLens"></div>
                        </div>

                        <!-- Zoom Result Window (Desktop Hover) -->
                        <div class="zoom-result-window" id="zoomResult"></div>

                        <span class="zoom-instruction-badge hide-mobile">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                <line x1="11" y1="8" x2="11" y2="14"></line>
                                <line x1="8" y1="11" x2="14" y2="11"></line>
                            </svg>
                            Roll hover to inspect artisanal weave
                        </span>
                    </div>
                </div>
            </div>

            <!-- RIGHT: COMMERCE SPECIFICATIONS & ACTIONS -->
            <div class="product-commerce-pane">
                <!-- Brand & SKU -->
                <div class="product-header-meta">
                    <span class="product-brand-badge"><?= $brandName ?> &bull; HAUTE ATELIER</span>
                    <span class="product-sku-badge">SKU: <span id="displaySku"><?= $sku ?></span></span>
                </div>

                <!-- Product Name -->
                <h1 class="product-title-main font-serif"><?= $title ?></h1>

                <!-- Pricing Display -->
                <div class="product-price-section">
                    <div class="price-numbers-wrap">
                        <span class="price-current" id="displaySellingPrice"><?= format_price($sellingPrice) ?></span>
                        <?php if ($originalPrice > $sellingPrice): ?>
                            <span class="price-original" id="displayOriginalPrice"><?= format_price($originalPrice) ?></span>
                            <span class="price-discount-tag" id="displayDiscountTag"><?= $discount ?>% PRIVILEGE DISCOUNT</span>
                        <?php endif; ?>
                    </div>
                    <p class="price-inclusive-tax">Inclusive of all luxury taxes &bull; Insured white-glove delivery across India</p>
                </div>

                <!-- Editorial Short Description -->
                <?php if (!empty($product['short_description'])): ?>
                    <p class="product-short-desc"><?= e($product['short_description']) ?></p>
                <?php endif; ?>

                <!-- Form for Variant Selection & Cart Interaction -->
                <form action="<?= BASE_URL ?>/cart.php" method="POST" id="productOrderForm" class="product-order-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                    <input type="hidden" name="variant_id" id="selectedVariantId" value="<?= !empty($variants) ? (int)$variants[0]['id'] : 0 ?>">

                    <!-- Variant Selection: Colors -->
                    <?php if (!empty($availableColors)): ?>
                        <div class="option-block">
                            <div class="option-header">
                                <span class="option-title">Select Color:</span>
                                <span class="selected-option-value" id="selectedColorLabel"><?= e(array_key_first($availableColors)) ?></span>
                            </div>
                            <div class="color-swatch-selector" role="radiogroup" aria-label="Color Selection">
                                <?php $cIdx = 0; foreach ($availableColors as $colName => $cData): $cIdx++; ?>
                                    <button 
                                        type="button" 
                                        class="color-pill-btn <?= ($cIdx === 1) ? 'selected' : '' ?>"
                                        data-color="<?= e($colName) ?>"
                                        aria-label="<?= e($colName) ?>"
                                        title="<?= e($colName) ?>"
                                    >
                                        <span class="swatch-circle" style="background-color: <?= e($cData['code']) ?>;"></span>
                                        <span class="swatch-name"><?= e($colName) ?></span>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Variant Selection: Sizes -->
                    <?php if (!empty($availableSizes)): ?>
                        <div class="option-block">
                            <div class="option-header">
                                <span class="option-title">Select Atelier Size:</span>
                                <span class="selected-option-value" id="selectedSizeLabel"><?= e($availableSizes[0]) ?></span>
                            </div>
                            <div class="size-pill-selector" role="radiogroup" aria-label="Size Selection">
                                <?php foreach ($availableSizes as $sIdx => $sz): ?>
                                    <button 
                                        type="button" 
                                        class="size-choice-btn <?= ($sIdx === 0) ? 'selected' : '' ?>"
                                        data-size="<?= e($sz) ?>"
                                        aria-label="Size <?= e($sz) ?>"
                                    >
                                        <?= e($sz) ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Stock Availability Status -->
                    <div class="stock-status-wrapper" id="stockStatusWrapper">
                        <?php if ($totalStock > 0): ?>
                            <span class="stock-pill stock-available">
                                <span class="stock-dot"></span>
                                <span id="stockStatusText">
                                    <?= ($totalStock <= 3) ? "Limited Atelier Stock: Only $totalStock creations remaining" : "In Atelier Stock &bull; Ready for Expedited Dispatch" ?>
                                </span>
                            </span>
                        <?php else: ?>
                            <span class="stock-pill stock-out">
                                <span class="stock-dot"></span>
                                <span id="stockStatusText">Currently Sold Out from Salon Archive</span>
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Quantity Selector & Actions -->
                    <div class="quantity-actions-group">
                        <div class="quantity-control-wrap">
                            <label for="productQtyInput" class="qty-label">Quantity:</label>
                            <div class="qty-stepper">
                                <button type="button" class="qty-btn" id="qtyDecrementBtn" aria-label="Decrease Quantity">&minus;</button>
                                <input 
                                    type="number" 
                                    name="quantity" 
                                    id="productQtyInput" 
                                    class="qty-input" 
                                    value="1" 
                                    min="1" 
                                    max="<?= max(1, $totalStock) ?>"
                                    <?= ($totalStock <= 0) ? 'disabled' : '' ?>
                                >
                                <button type="button" class="qty-btn" id="qtyIncrementBtn" aria-label="Increase Quantity">&plus;</button>
                            </div>
                        </div>

                        <!-- Add to Bag & Buy Now Buttons -->
                        <div class="commerce-buttons-wrap">
                            <button 
                                type="submit" 
                                name="intent" 
                                value="add_to_cart" 
                                class="btn btn-gold btn-lg btn-block" 
                                id="addToBagBtn"
                                <?= ($totalStock <= 0) ? 'disabled' : '' ?>
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                    <line x1="3" y1="6" x2="21" y2="6"></line>
                                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                                </svg>
                                <span><?= ($totalStock > 0) ? 'Add to Shopping Bag' : 'Sold Out' ?></span>
                            </button>

                            <button 
                                type="submit" 
                                name="intent" 
                                value="buy_now" 
                                class="btn btn-primary btn-lg btn-block" 
                                id="buyNowBtn"
                                <?= ($totalStock <= 0) ? 'disabled' : '' ?>
                            >
                                <span>Acquire Now &bull; Immediate Checkout</span>
                            </button>

                            <!-- Wishlist Toggle -->
                            <?php $isFav = is_product_in_wishlist((int)$product['id']); ?>
                            <button 
                                type="button" 
                                class="btn btn-outline product-page-wishlist-btn <?= $isFav ? 'active' : '' ?>" 
                                id="pageWishlistBtn"
                                data-id="<?= (int)$product['id'] ?>"
                            >
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                </svg>
                                <span id="wishlistBtnLabel"><?= $isFav ? 'Saved in Private Wishlist' : 'Add to Private Wishlist' ?></span>
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Atelier Trust Pillars -->
                <div class="product-patron-reassurance">
                    <div class="reassurance-item">
                        <span class="reassurance-icon">⚜</span>
                        <div>
                            <strong>100% Certified Pure Handlooms</strong>
                            <span>Authenticated with Silk Mark & Handloom Board</span>
                        </div>
                    </div>
                    <div class="reassurance-item">
                        <span class="reassurance-icon">✦</span>
                        <div>
                            <strong>Complimentary White-Glove Delivery</strong>
                            <span>Insured express delivery in custom atelier garment boxes</span>
                        </div>
                    </div>
                    <div class="reassurance-item">
                        <span class="reassurance-icon">◈</span>
                        <div>
                            <strong>7-Day Privilege Exchange</strong>
                            <span>Hassle-free size adjustment & concierge returns</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <!-- 3. PRODUCT SPECIFICATIONS, CARE & POLICIES DOSSIER TABS -->
        <div class="product-dossier-tabs-wrap section-padding" style="padding-top: var(--space-2xl);">
            <div class="dossier-tab-headers" role="tablist">
                <button type="button" class="dossier-tab-btn active" data-tab="tab-desc" role="tab">Atelier Description</button>
                <button type="button" class="dossier-tab-btn" data-tab="tab-specs" role="tab">Specifications</button>
                <button type="button" class="dossier-tab-btn" data-tab="tab-care" role="tab">Material & Care</button>
                <button type="button" class="dossier-tab-btn" data-tab="tab-shipping" role="tab">White-Glove Shipping</button>
                <button type="button" class="dossier-tab-btn" data-tab="tab-returns" role="tab">Privilege Returns</button>
                <button type="button" class="dossier-tab-btn" data-tab="tab-reviews" role="tab">Patron Reflections</button>
            </div>

            <div class="dossier-tab-contents">
                <!-- Tab 1: Description -->
                <div class="tab-panel active" id="tab-desc" role="tabpanel">
                    <div class="tab-editorial-content">
                        <h3 class="font-serif">The Architecture of the Piece</h3>
                        <p><?= nl2br(e($product['description'] ?? '')) ?></p>
                    </div>
                </div>

                <!-- Tab 2: Specifications -->
                <div class="tab-panel" id="tab-specs" role="tabpanel">
                    <div class="specs-table-wrap">
                        <h3 class="font-serif">Artisanal Specifications</h3>
                        <table class="specs-table">
                            <tbody>
                                <tr>
                                    <th>Couture House</th>
                                    <td><?= $brandName ?></td>
                                </tr>
                                <tr>
                                    <th>Master SKU</th>
                                    <td><?= $sku ?></td>
                                </tr>
                                <tr>
                                    <th>Category</th>
                                    <td><?= $categoryName ?></td>
                                </tr>
                                <?php if (!empty($specs)): ?>
                                    <?php foreach ($specs as $key => $val): ?>
                                        <tr>
                                            <th><?= e($key) ?></th>
                                            <td><?= e($val) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab 3: Material & Care -->
                <div class="tab-panel" id="tab-care" role="tabpanel">
                    <div class="tab-editorial-content">
                        <h3 class="font-serif">Preserving Generational Luxury</h3>
                        <p>
                            Each Yatiya creation is crafted using natural, unadulterated fibers—pure Mulberry silk, handloom Katan, high-altitude Changthangi cashmere, and hand-beaten gold and silver zari. To protect its lustrous sheen and intricate embroidery:
                        </p>
                        <ul class="care-list">
                            <li>Always professional dry clean only by specialist couture cleaners.</li>
                            <li>Store folded within the complimentary unbleached muslin bag provided.</li>
                            <li>Avoid direct contact with perfumes, deodorants, or fine hair mists.</li>
                            <li>Periodically air the garment in shade to preserve the organic handloom fibers.</li>
                            <li>Iron on the reverse using low heat with a protective pressing cloth.</li>
                        </ul>
                    </div>
                </div>

                <!-- Tab 4: Shipping -->
                <div class="tab-panel" id="tab-shipping" role="tabpanel">
                    <div class="tab-editorial-content">
                        <h3 class="font-serif">White-Glove Delivery Experience</h3>
                        <p>
                            We believe the anticipation of haute couture should be as delightful as donning it. Every order is meticulously wrapped in acid-free tissue paper, cushioned in signature Yatiya Couture keepsake boxes, and sealed with custom wax emblems.
                        </p>
                        <p>
                            <strong>Domestic Delivery (India):</strong> Complimentary expedited shipping on all orders above ₹2,999. In-stock pieces dispatch within 24 to 48 hours. Bespoke customized items require 7–14 days for hand tailoring.
                        </p>
                        <p>
                            <strong>Courier Partners:</strong> Premium discreet carriage via Blue Dart Apex, DHL Express, and Sequel Secure Logistics with 100% transit insurance.
                        </p>
                    </div>
                </div>

                <!-- Tab 5: Returns -->
                <div class="tab-panel" id="tab-returns" role="tabpanel">
                    <div class="tab-editorial-content">
                        <h3 class="font-serif">7-Day Privilege Exchange Protocol</h3>
                        <p>
                            Patron satisfaction is our sacred covenant. If your acquired piece requires a size refinement or does not meet your discerning expectations:
                        </p>
                        <p>
                            You may request a complimentary exchange or return within <strong>7 days</strong> of delivery, provided the garment remains unworn, undamaged, and retains all original atelier security tags intact.
                        </p>
                        <p>
                            Our concierge will dispatch a white-glove courier to collect the package directly from your residence at your preferred hour.
                        </p>
                    </div>
                </div>

                <!-- Tab 6: Reviews / Brand Promise -->
                <div class="tab-panel" id="tab-reviews" role="tabpanel">
                    <div class="brand-promise-wrap" style="box-shadow: none; padding: var(--space-xl);">
                        <div class="promise-content">
                            <span class="section-eyebrow">Genuine Patron Voices</span>
                            <h3 class="font-serif">Authentic Patron Reflections</h3>
                            <p>
                                At Yatiya Couture, we uphold uncompromising integrity: reviews published in our salons originate strictly from authenticated patrons who have completed an acquisition.
                            </p>
                            <p>
                                When you acquire this creation, you will receive an invitation to share your verified review with fellow couture enthusiasts.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. RELATED COUTURE CREATIONS -->
        <?php if (!empty($relatedProducts)): ?>
            <section class="related-products-section section-padding" style="padding-top: var(--space-xl); border-top: 1px solid var(--border-subtle);">
                <div class="section-header text-center">
                    <span class="section-eyebrow">Complementary Elegance</span>
                    <h2 class="section-title">Related Atelier Creations</h2>
                    <p class="section-subtitle">Handcrafted pieces curated from the same salon edit to complete your ensemble.</p>
                </div>

                <div class="product-grid">
                    <?php foreach ($relatedProducts as $rel): 
                        $relTitle = e($rel['name']);
                        $relSlug = e($rel['slug']);
                        $relUrl = BASE_URL . '/product.php?slug=' . urlencode($relSlug);
                        $relPrice = (float)$rel['selling_price'];
                        $relOriginal = (float)$rel['original_price'];
                        $relDiscount = calculate_discount_percentage($relOriginal, $relPrice);
                        $relImg = e($rel['primary_image'] ?? 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=800&q=80');
                        $relHover = !empty($rel['hover_image']) ? e($rel['hover_image']) : '';
                    ?>
                        <div class="product-card">
                            <div class="product-media">
                                <a href="<?= $relUrl ?>">
                                    <img src="<?= $relImg ?>" alt="<?= $relTitle ?>" class="product-img" loading="lazy">
                                    <?php if ($relHover): ?>
                                        <img src="<?= $relHover ?>" alt="<?= $relTitle ?> Alternate" class="product-hover-img" loading="lazy">
                                    <?php endif; ?>
                                </a>

                                <div class="product-badge-group">
                                    <?php if ($relDiscount > 0): ?>
                                        <span class="product-badge badge-discount"><?= $relDiscount ?>% OFF</span>
                                    <?php elseif (!empty($rel['is_new'])): ?>
                                        <span class="product-badge badge-new">NEW</span>
                                    <?php endif; ?>
                                </div>

                                <button type="button" class="product-wishlist-btn <?= is_product_in_wishlist((int)$rel['id']) ? 'active' : '' ?>" aria-label="Add to Wishlist" data-id="<?= (int)$rel['id'] ?>">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                    </svg>
                                </button>
                            </div>

                            <div class="product-details">
                                <span class="product-category-tag"><?= e($rel['category_name']) ?></span>
                                <h3 class="product-title font-serif">
                                    <a href="<?= $relUrl ?>"><?= $relTitle ?></a>
                                </h3>
                                <div class="product-price-wrap">
                                    <span class="product-selling-price"><?= format_price($relPrice) ?></span>
                                    <?php if ($relOriginal > $relPrice): ?>
                                        <span class="product-original-price"><?= format_price($relOriginal) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    </div>
</div>

<!-- Raw Variants Data embedded for Vanilla JS dynamic switching -->
<script>
    window.YATIYA_VARIANTS_DATA = <?= json_encode($variantsJson, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="<?= ASSETS_URL ?>/js/product.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

