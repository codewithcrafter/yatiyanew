<?php
/**
 * YATIYA COUTURE - Haute Couture Product Catalogue
 * Brand Vision: "ELEVATE YOUR EVERYDAY STYLE"
 * Phase 3 Complete Product Catalogue System
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/product-functions.php';

$activePage = 'shop';

// ---------------------------------------------------------------------
// 1. SANITIZE & VALIDATE FILTER INPUTS
// ---------------------------------------------------------------------
$filters = [];

// Category / Subcategory
if (!empty($_GET['category'])) {
    $filters['category'] = clean_input($_GET['category']);
}
if (!empty($_GET['subcategory'])) {
    $filters['subcategory'] = clean_input($_GET['subcategory']);
}

// Search Query
if (isset($_GET['q']) && trim($_GET['q']) !== '') {
    $filters['q'] = clean_input($_GET['q']);
}

// Price Range
if (isset($_GET['min_price']) && is_numeric($_GET['min_price'])) {
    $filters['min_price'] = max(0, (float)$_GET['min_price']);
}
if (isset($_GET['max_price']) && is_numeric($_GET['max_price'])) {
    $filters['max_price'] = max(0, (float)$_GET['max_price']);
}

// Sizes (support array or single)
if (!empty($_GET['size'])) {
    $rawSizes = is_array($_GET['size']) ? $_GET['size'] : [$_GET['size']];
    $filters['size'] = array_values(array_filter(array_map('clean_input', $rawSizes)));
}

// Colors
if (!empty($_GET['color'])) {
    $rawColors = is_array($_GET['color']) ? $_GET['color'] : [$_GET['color']];
    $filters['color'] = array_values(array_filter(array_map('clean_input', $rawColors)));
}

// Availability
if (!empty($_GET['availability']) && $_GET['availability'] === 'in_stock') {
    $filters['availability'] = 'in_stock';
}

// Gender
if (!empty($_GET['gender']) && in_array($_GET['gender'], ['women', 'men', 'kids'], true)) {
    $filters['gender'] = $_GET['gender'];
}

// Sorting
$sort = $_GET['sort'] ?? 'newest';
$allowedSorts = ['newest', 'price_asc', 'price_desc', 'bestselling', 'popular'];
if (!in_array($sort, $allowedSorts, true)) {
    $sort = 'newest';
}
$filters['sort'] = $sort;

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 12; // 12 creations per page

// ---------------------------------------------------------------------
// 2. QUERY PRODUCTS & AGGREGATES
// ---------------------------------------------------------------------
$catalogueResult = get_filtered_products($filters, $page, $perPage);
$products = $catalogueResult['products'];
$totalProducts = $catalogueResult['total'];
$totalPages = $catalogueResult['total_pages'];
$currentPage = $catalogueResult['page'];

// Fetch filter aggregates for the sidebar (categories, price bounds, sizes, colors)
$aggregates = get_filter_aggregates();

// Resolve active category metadata for editorial banner
$categoryTitle = 'All Collections';
$categoryDesc = 'Discover bespoke silks, handloom brocades, tailored bandhgalas and fine atelier adornments.';

if (!empty($filters['category'])) {
    foreach ($aggregates['categories'] as $c) {
        if ($c['slug'] === $filters['category']) {
            $categoryTitle = $c['name'];
            $categoryDesc = 'Impeccable creations crafted in pure silks and heirloom embroidery for ' . strtolower($c['name']) . '.';
            break;
        }
    }
} elseif (!empty($filters['q'])) {
    $categoryTitle = 'Search Results';
    $categoryDesc = sprintf('Displaying atelier creations matching "%s".', e($filters['q']));
}

$pageTitle = $categoryTitle . ' | Collections';

// Helper: build URL preserving other query parameters
function build_filter_url(array $paramsToMerge = [], array $paramsToRemove = []): string {
    $current = $_GET;
    foreach ($paramsToRemove as $rem) {
        unset($current[$rem]);
    }
    $merged = array_merge($current, $paramsToMerge);
    // Remove empty parameters
    $clean = array_filter($merged, function ($v) {
        return ($v !== '' && $v !== null && $v !== []);
    });
    return 'shop.php' . (!empty($clean) ? '?' . http_build_query($clean) : '');
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Shop Master Stylesheet -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/shop.css">

<div class="shop-page-wrapper">
    <!-- 1. EDITORIAL HEADER & BREADCRUMB -->
    <section class="shop-hero-header">
        <div class="container">
            <nav class="breadcrumb-nav" aria-label="Breadcrumb">
                <ol class="breadcrumb-list">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/shop.php">Collections</a></li>
                    <?php if (!empty($filters['category'])): ?>
                        <li class="breadcrumb-item active" aria-current="page"><?= e($categoryTitle) ?></li>
                    <?php elseif (!empty($filters['q'])): ?>
                        <li class="breadcrumb-item active" aria-current="page">Search: "<?= e($filters['q']) ?>"</li>
                    <?php else: ?>
                        <li class="breadcrumb-item active" aria-current="page">All Creations</li>
                    <?php endif; ?>
                </ol>
            </nav>

            <div class="shop-header-text">
                <span class="section-eyebrow">YATIYA COUTURE ATELIER</span>
                <h1 class="shop-category-title font-serif"><?= e($categoryTitle) ?></h1>
                <p class="shop-category-desc"><?= e($categoryDesc) ?></p>
            </div>
        </div>
    </section>

    <!-- 2. MAIN SHOP CONTENT -->
    <div class="container section-padding" style="padding-top: var(--space-xl);">
        
        <!-- Active Filter Bar & Controls -->
        <div class="shop-control-bar">
            <!-- Mobile Filter Toggle Button -->
            <button type="button" class="btn btn-outline btn-sm mobile-filter-btn" id="mobileFilterToggleBtn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="4" y1="21" x2="4" y2="14"></line>
                    <line x1="4" y1="10" x2="4" y2="3"></line>
                    <line x1="12" y1="21" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12" y2="3"></line>
                    <line x1="20" y1="21" x2="20" y2="16"></line>
                    <line x1="20" y1="12" x2="20" y2="3"></line>
                    <line x1="1" y1="14" x2="7" y2="14"></line>
                    <line x1="9" y1="8" x2="15" y2="8"></line>
                    <line x1="17" y1="16" x2="23" y2="16"></line>
                </svg>
                <span>Filter Creations</span>
            </button>

            <!-- Results Count -->
            <div class="shop-results-count">
                <?php if ($totalProducts > 0): ?>
                    Showing <strong><?= (($currentPage - 1) * $perPage) + 1 ?> &ndash; <?= min($totalProducts, $currentPage * $perPage) ?></strong> of <strong><?= $totalProducts ?></strong> creations
                <?php else: ?>
                    <strong>0</strong> creations found
                <?php endif; ?>
            </div>

            <!-- Sort By Select -->
            <div class="shop-sort-wrap">
                <label for="shopSortSelect" class="sort-label">Sort By:</label>
                <select id="shopSortSelect" class="shop-sort-select" onchange="window.location.href=this.value;">
                    <option value="<?= e(build_filter_url(['sort' => 'newest', 'page' => 1])) ?>" <?= ($sort === 'newest') ? 'selected' : '' ?>>Newest Arrivals</option>
                    <option value="<?= e(build_filter_url(['sort' => 'price_asc', 'page' => 1])) ?>" <?= ($sort === 'price_asc') ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="<?= e(build_filter_url(['sort' => 'price_desc', 'page' => 1])) ?>" <?= ($sort === 'price_desc') ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="<?= e(build_filter_url(['sort' => 'bestselling', 'page' => 1])) ?>" <?= ($sort === 'bestselling') ? 'selected' : '' ?>>Best Selling Signatures</option>
                    <option value="<?= e(build_filter_url(['sort' => 'popular', 'page' => 1])) ?>" <?= ($sort === 'popular') ? 'selected' : '' ?>>Most Popular</option>
                </select>
            </div>
        </div>

        <!-- Active Filter Chips -->
        <?php 
            $activeChips = [];
            if (!empty($filters['category'])) $activeChips[] = ['label' => 'Category: ' . $categoryTitle, 'remove' => build_filter_url([], ['category', 'subcategory', 'page'])];
            if (!empty($filters['q'])) $activeChips[] = ['label' => 'Search: "' . $filters['q'] . '"', 'remove' => build_filter_url([], ['q', 'page'])];
            if (!empty($filters['min_price'])) $activeChips[] = ['label' => 'Min: ' . format_price($filters['min_price']), 'remove' => build_filter_url([], ['min_price', 'page'])];
            if (!empty($filters['max_price'])) $activeChips[] = ['label' => 'Max: ' . format_price($filters['max_price']), 'remove' => build_filter_url([], ['max_price', 'page'])];
            if (!empty($filters['availability'])) $activeChips[] = ['label' => 'In Stock Only', 'remove' => build_filter_url([], ['availability', 'page'])];
            if (!empty($filters['gender'])) $activeChips[] = ['label' => 'Gender: ' . ucfirst($filters['gender']), 'remove' => build_filter_url([], ['gender', 'page'])];
            if (!empty($filters['size'])) {
                foreach ($filters['size'] as $sz) {
                    $activeChips[] = ['label' => 'Size: ' . $sz, 'remove' => build_filter_url([], ['size', 'page'])];
                }
            }
            if (!empty($filters['color'])) {
                foreach ($filters['color'] as $col) {
                    $activeChips[] = ['label' => 'Color: ' . $col, 'remove' => build_filter_url([], ['color', 'page'])];
                }
            }
        ?>
        <?php if (!empty($activeChips)): ?>
            <div class="active-filter-chips-wrap">
                <span class="active-filter-title">Active Filters:</span>
                <div class="active-chips-list">
                    <?php foreach ($activeChips as $chip): ?>
                        <a href="<?= e($chip['remove']) ?>" class="filter-chip" title="Remove filter">
                            <span><?= e($chip['label']) ?></span>
                            <span class="chip-remove-icon">&times;</span>
                        </a>
                    <?php endforeach; ?>
                    <a href="<?= BASE_URL ?>/shop.php" class="filter-clear-all-btn">Clear All</a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Layout: Sidebar + Product Grid -->
        <div class="shop-layout">
            
            <!-- 3. ADVANCED FILTER SIDEBAR -->
            <aside class="shop-sidebar" id="shopSidebar">
                <div class="sidebar-header-mobile">
                    <h3 class="font-serif">Filter Creations</h3>
                    <button type="button" class="sidebar-close-btn" id="sidebarCloseBtn">&times;</button>
                </div>

                <form action="<?= BASE_URL ?>/shop.php" method="GET" class="filter-form" id="filterForm">
                    <!-- Preserve search and sort -->
                    <?php if (!empty($filters['q'])): ?>
                        <input type="hidden" name="q" value="<?= e($filters['q']) ?>">
                    <?php endif; ?>
                    <?php if (!empty($sort)): ?>
                        <input type="hidden" name="sort" value="<?= e($sort) ?>">
                    <?php endif; ?>

                    <!-- Filter 1: Categories -->
                    <div class="filter-group">
                        <h4 class="filter-group-title">Categories</h4>
                        <ul class="filter-category-list">
                            <li>
                                <a href="<?= e(build_filter_url([], ['category', 'subcategory', 'page'])) ?>" 
                                   class="filter-cat-link <?= empty($filters['category']) ? 'active' : '' ?>">
                                    <span>All Categories</span>
                                </a>
                            </li>
                            <?php foreach ($aggregates['categories'] as $c): 
                                if ($c['parent_id'] !== null) continue; // Only parent categories in top list
                                $isActive = (!empty($filters['category']) && $filters['category'] === $c['slug']);
                            ?>
                                <li>
                                    <a href="<?= e(build_filter_url(['category' => $c['slug'], 'page' => 1], ['subcategory'])) ?>" 
                                       class="filter-cat-link <?= $isActive ? 'active' : '' ?>">
                                        <span><?= e($c['name']) ?></span>
                                        <span class="filter-count"><?= (int)$c['product_count'] ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Filter 2: Price Range -->
                    <div class="filter-group">
                        <h4 class="filter-group-title">Price Range</h4>
                        <div class="price-input-inputs">
                            <div class="price-field">
                                <span class="price-prefix">₹</span>
                                <input 
                                    type="number" 
                                    name="min_price" 
                                    id="minPriceInput"
                                    class="price-input" 
                                    placeholder="Min" 
                                    value="<?= isset($filters['min_price']) ? e($filters['min_price']) : '' ?>"
                                    min="0"
                                >
                            </div>
                            <span class="price-separator">&ndash;</span>
                            <div class="price-field">
                                <span class="price-prefix">₹</span>
                                <input 
                                    type="number" 
                                    name="max_price" 
                                    id="maxPriceInput"
                                    class="price-input" 
                                    placeholder="Max" 
                                    value="<?= isset($filters['max_price']) ? e($filters['max_price']) : '' ?>"
                                    min="0"
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Filter 3: Sizes -->
                    <?php if (!empty($aggregates['sizes'])): ?>
                        <div class="filter-group">
                            <h4 class="filter-group-title">Sizes</h4>
                            <div class="size-filter-grid">
                                <?php foreach ($aggregates['sizes'] as $sz): 
                                    $isSelected = !empty($filters['size']) && in_array($sz, $filters['size'], true);
                                ?>
                                    <label class="size-filter-pill <?= $isSelected ? 'selected' : '' ?>">
                                        <input 
                                            type="checkbox" 
                                            name="size[]" 
                                            value="<?= e($sz) ?>" 
                                            <?= $isSelected ? 'checked' : '' ?>
                                        >
                                        <span><?= e($sz) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Filter 4: Colors -->
                    <?php if (!empty($aggregates['colors'])): ?>
                        <div class="filter-group">
                            <h4 class="filter-group-title">Colors</h4>
                            <div class="color-filter-list">
                                <?php foreach ($aggregates['colors'] as $col): 
                                    $isSelected = !empty($filters['color']) && in_array($col['color'], $filters['color'], true);
                                    $swatch = $col['color_code'] ?: '#CCCCCC';
                                ?>
                                    <label class="color-filter-item <?= $isSelected ? 'selected' : '' ?>">
                                        <input 
                                            type="checkbox" 
                                            name="color[]" 
                                            value="<?= e($col['color']) ?>" 
                                            <?= $isSelected ? 'checked' : '' ?>
                                        >
                                        <span class="color-swatch-dot" style="background-color: <?= e($swatch) ?>;"></span>
                                        <span class="color-name"><?= e($col['color']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Filter 5: Gender / Line -->
                    <div class="filter-group">
                        <h4 class="filter-group-title">Atelier Wardrobe</h4>
                        <div class="gender-filter-radios">
                            <label class="gender-radio-item">
                                <input type="radio" name="gender" value="" <?= empty($filters['gender']) ? 'checked' : '' ?>>
                                <span>All Wardrobes</span>
                            </label>
                            <label class="gender-radio-item">
                                <input type="radio" name="gender" value="women" <?= (isset($filters['gender']) && $filters['gender'] === 'women') ? 'checked' : '' ?>>
                                <span>Women's Salon</span>
                            </label>
                            <label class="gender-radio-item">
                                <input type="radio" name="gender" value="men" <?= (isset($filters['gender']) && $filters['gender'] === 'men') ? 'checked' : '' ?>>
                                <span>Men's Atelier</span>
                            </label>
                            <label class="gender-radio-item">
                                <input type="radio" name="gender" value="kids" <?= (isset($filters['gender']) && $filters['gender'] === 'kids') ? 'checked' : '' ?>>
                                <span>Royal Heritage Kids</span>
                            </label>
                        </div>
                    </div>

                    <!-- Filter 6: Availability -->
                    <div class="filter-group">
                        <label class="availability-checkbox-item">
                            <input 
                                type="checkbox" 
                                name="availability" 
                                value="in_stock" 
                                <?= (!empty($filters['availability']) && $filters['availability'] === 'in_stock') ? 'checked' : '' ?>
                            >
                            <span>In Stock Only</span>
                        </label>
                    </div>

                    <!-- Apply / Reset Buttons -->
                    <div class="filter-action-buttons">
                        <button type="submit" class="btn btn-gold btn-block">Apply Filters</button>
                        <a href="<?= BASE_URL ?>/shop.php" class="btn btn-outline btn-block btn-sm">Clear All</a>
                    </div>
                </form>
            </aside>

            <!-- 4. PRODUCT GRID & RESULTS -->
            <main class="shop-grid-main">
                <?php if (!empty($products)): ?>
                    <div class="product-grid">
                        <?php foreach ($products as $prod): 
                            $title = e($prod['name']);
                            $slug = e($prod['slug']);
                            $url = BASE_URL . '/product.php?slug=' . urlencode($slug);
                            $price = (float)$prod['selling_price'];
                            $original = (float)$prod['original_price'];
                            $discount = calculate_discount_percentage($original, $price);
                            $primaryImg = e($prod['primary_image'] ?? 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=800&q=80');
                            $hoverImg = !empty($prod['hover_image']) ? e($prod['hover_image']) : '';
                            $categoryName = e($prod['category_name'] ?? 'Haute Couture');
                            $stock = (int)$prod['stock_quantity'];
                        ?>
                            <div class="product-card" data-id="<?= (int)$prod['id'] ?>">
                                <div class="product-media">
                                    <a href="<?= $url ?>">
                                        <img src="<?= $primaryImg ?>" alt="<?= $title ?>" class="product-img" loading="lazy">
                                        <?php if ($hoverImg): ?>
                                            <img src="<?= $hoverImg ?>" alt="<?= $title ?> Alternate View" class="product-hover-img" loading="lazy">
                                        <?php endif; ?>
                                    </a>

                                    <div class="product-badge-group">
                                        <?php if ($discount > 0): ?>
                                            <span class="product-badge badge-discount"><?= $discount ?>% OFF</span>
                                        <?php elseif (!empty($prod['is_new'])): ?>
                                            <span class="product-badge badge-new">NEW RUNWAY</span>
                                        <?php elseif (!empty($prod['is_bestseller'])): ?>
                                            <span class="product-badge badge-bestseller">BESTSELLER</span>
                                        <?php endif; ?>

                                        <?php if ($stock <= 0): ?>
                                            <span class="product-badge" style="background:#555; color:#FFF;">SOLD OUT</span>
                                        <?php elseif ($stock <= 3): ?>
                                            <span class="product-badge badge-discount" style="background:#B45309;">ONLY <?= $stock ?> LEFT</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Wishlist Heart Button -->
                                    <button type="button" class="product-wishlist-btn <?= is_product_in_wishlist((int)$prod['id']) ? 'active' : '' ?>" aria-label="Add <?= $title ?> to Wishlist" data-id="<?= (int)$prod['id'] ?>">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                        </svg>
                                    </button>

                                    <!-- Quick View Button -->
                                    <button 
                                        type="button" 
                                        class="product-quickview-btn"
                                        data-id="<?= (int)$prod['id'] ?>"
                                        data-title="<?= $title ?>"
                                        data-price="<?= format_price($price) ?>"
                                        data-original-price="<?= ($original > $price) ? format_price($original) : '' ?>"
                                        data-discount="<?= ($discount > 0) ? $discount . '% OFF' : '' ?>"
                                        data-image="<?= $primaryImg ?>"
                                        data-desc="<?= e($prod['short_description'] ?? '') ?>"
                                        data-link="<?= $url ?>"
                                        data-stock="<?= $stock ?>"
                                        data-sizes="<?= e($prod['available_sizes'] ?? '') ?>"
                                        data-colors="<?= e($prod['available_colors'] ?? '') ?>"
                                    >
                                        Quick View
                                    </button>
                                </div>

                                <div class="product-details">
                                    <div class="product-meta-row">
                                        <span class="product-category-tag"><?= $categoryName ?></span>
                                        <span class="product-sku-tag"><?= e($prod['sku']) ?></span>
                                    </div>
                                    <h3 class="product-title font-serif">
                                        <a href="<?= $url ?>"><?= $title ?></a>
                                    </h3>
                                    <div class="product-price-wrap">
                                        <span class="product-selling-price"><?= format_price($price) ?></span>
                                        <?php if ($original > $price): ?>
                                            <span class="product-original-price"><?= format_price($original) ?></span>
                                            <span class="product-discount-label"><?= $discount ?>% OFF</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- 5. PAGINATION CONTROLS -->
                    <?php if ($totalPages > 1): ?>
                        <nav class="pagination-nav" aria-label="Catalogue pagination">
                            <div class="pagination-list">
                                <!-- Previous -->
                                <?php if ($catalogueResult['has_previous']): ?>
                                    <a href="<?= e(build_filter_url(['page' => $catalogueResult['previous_page']])) ?>" class="pagination-btn pagination-prev" aria-label="Previous Page">
                                        &larr; Previous
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-btn pagination-disabled" aria-disabled="true">&larr; Previous</span>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <?php if ($p === $currentPage): ?>
                                        <span class="pagination-item current" aria-current="page"><?= $p ?></span>
                                    <?php elseif ($p === 1 || $p === $totalPages || abs($p - $currentPage) <= 2): ?>
                                        <a href="<?= e(build_filter_url(['page' => $p])) ?>" class="pagination-item"><?= $p ?></a>
                                    <?php elseif (abs($p - $currentPage) === 3): ?>
                                        <span class="pagination-ellipsis">&hellip;</span>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <!-- Next -->
                                <?php if ($catalogueResult['has_next']): ?>
                                    <a href="<?= e(build_filter_url(['page' => $catalogueResult['next_page']])) ?>" class="pagination-btn pagination-next" aria-label="Next Page">
                                        Next &rarr;
                                    </a>
                                <?php else: ?>
                                    <span class="pagination-btn pagination-disabled" aria-disabled="true">Next &rarr;</span>
                                <?php endif; ?>
                            </div>
                        </nav>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- NO-RESULTS EMPTY STATE -->
                    <div class="shop-empty-state">
                        <div class="empty-icon">❖</div>
                        <h3 class="empty-title font-serif">No Creations Found</h3>
                        <p class="empty-desc">
                            We could not find any atelier creations matching your selected criteria. Try adjusting your filters or search terms.
                        </p>
                        <div class="empty-actions">
                            <a href="<?= BASE_URL ?>/shop.php" class="btn btn-gold">Clear All Filters</a>
                            <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline">Return to Lookbook</a>
                        </div>
                    </div>
                <?php endif; ?>
            </main>

        </div>
    </div>
</div>

<!-- Shop JavaScript Interactions -->
<script src="<?= ASSETS_URL ?>/js/shop.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
