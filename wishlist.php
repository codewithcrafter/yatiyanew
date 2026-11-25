<?php
/**
 * YATIYA COUTURE - Private Wishlist Experience
 * Curated luxury creations saved for future wardrobe acquisition
 * Supports both authenticated customers (DB-backed) and guest sessions
 * Pure Core PHP & Vanilla JavaScript
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart-functions.php';

// If POST request submitted directly to wishlist.php, forward to actions controller
if (is_post()) {
    require __DIR__ . '/actions/wishlist_actions.php';
    exit;
}

$pageTitle = 'Private Wishlist';
$pageDescription = 'Review your saved haute couture favorites in your Yatiya Couture private wishlist.';
$activePage = 'wishlist';

$wishlistItems = get_wishlist_products();
$itemCount = count($wishlistItems);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Wishlist Stylesheet -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/wishlist.css">

<main class="wishlist-main-wrapper" id="wishlistApp">
    <div class="container">
        
        <!-- Flash Alert Messages -->
        <?= render_flash() ?>

        <!-- Luxury Breadcrumb Navigation -->
        <nav class="wishlist-breadcrumb" aria-label="Breadcrumb">
            <ol>
                <li><a href="<?= BASE_URL ?>/index.php">Maison Home</a></li>
                <li class="separator">/</li>
                <li aria-current="page">Private Wishlist</li>
            </ol>
        </nav>

        <div class="wishlist-header-row">
            <div>
                <span class="wishlist-eyebrow">Personal Atelier Curation</span>
                <h1 class="wishlist-title">
                    Private Wishlist
                    <span class="wishlist-counter">(<?= $itemCount ?> <?= $itemCount === 1 ? 'Creation' : 'Creations' ?>)</span>
                </h1>
            </div>
            <?php if ($itemCount > 0): ?>
                <a href="<?= BASE_URL ?>/shop.php" class="continue-shopping-link">
                    &larr; Discover More Collections
                </a>
            <?php endif; ?>
        </div>

        <?php if ($itemCount === 0): ?>
            <!-- =========================================================
                 1. LUXURY EMPTY WISHLIST STATE
                 ========================================================= -->
            <section class="empty-wishlist-section" aria-label="Empty Wishlist">
                <div class="empty-wishlist-inner">
                    <div class="empty-wishlist-icon-wrap">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                    </div>
                    <span class="empty-wishlist-eyebrow">Atelier Curation</span>
                    <h2 class="empty-wishlist-heading">Your Private Wishlist is Empty</h2>
                    <p class="empty-wishlist-text">
                        Save the handcrafted lehengas, banarasi drapes, artisanal footwear, and fine jewellery that captivate your senses. Your selections will remain reserved here for your future private fitting.
                    </p>
                    <div class="empty-wishlist-actions">
                        <a href="<?= BASE_URL ?>/shop.php" class="btn btn-gold btn-lg">Explore Haute Couture Collections</a>
                    </div>

                    <!-- Curated Categories -->
                    <div class="empty-wishlist-curations">
                        <span class="curation-label">Explore by Haute Couture Salon:</span>
                        <div class="curation-tags">
                            <a href="<?= BASE_URL ?>/shop.php?category=women">Women's Salon</a>
                            <a href="<?= BASE_URL ?>/shop.php?category=men">Men's Privé</a>
                            <a href="<?= BASE_URL ?>/shop.php?category=footwear">Artisanal Footwear</a>
                            <a href="<?= BASE_URL ?>/shop.php?category=jewellery">Fine Jewellery</a>
                            <a href="<?= BASE_URL ?>/shop.php?category=new-arrivals">New Autumn / Winter</a>
                        </div>
                    </div>
                </div>
            </section>

        <?php else: ?>
            <!-- =========================================================
                 2. WISHLIST PRODUCT GRID
                 ========================================================= -->
            <div class="wishlist-grid" id="wishlistGrid">
                <?php foreach ($wishlistItems as $item): ?>
                    <?php 
                        $prodId = (int)$item['id'];
                        $sellingPrice = (float)$item['selling_price'];
                        $originalPrice = (float)($item['original_price'] ?? $item['base_price'] ?? $item['selling_price']);
                        $discount = calculate_discount_percentage($originalPrice, $sellingPrice);
                        $primaryImg = e($item['primary_image'] ?? 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=600&q=80');
                        $hoverImg = !empty($item['hover_image']) ? e($item['hover_image']) : '';
                        $stock = (int)($item['total_stock'] ?? 0);
                        $isSoldOut = ($stock <= 0);
                    ?>
                    <article class="wishlist-card" id="wishlistCard_<?= $prodId ?>" data-product-id="<?= $prodId ?>">
                        
                        <!-- Media Showcase -->
                        <div class="wishlist-card-media">
                            <a href="<?= BASE_URL ?>/product.php?slug=<?= e($item['slug']) ?>" class="wishlist-img-link">
                                <img src="<?= $primaryImg ?>" alt="<?= e($item['name']) ?>" class="wishlist-primary-img" loading="lazy">
                                <?php if ($hoverImg): ?>
                                    <img src="<?= $hoverImg ?>" alt="<?= e($item['name']) ?> Alternate View" class="wishlist-hover-img" loading="lazy">
                                <?php endif; ?>
                            </a>

                            <!-- Remove Button Floating -->
                            <form action="<?= BASE_URL ?>/actions/wishlist_actions.php" method="POST" class="wishlist-remove-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?= $prodId ?>">
                                <button type="submit" class="wishlist-card-remove-btn" title="Remove from Wishlist" aria-label="Remove <?= e($item['name']) ?> from Wishlist">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="18" y1="6" x2="6" y2="18"></line>
                                        <line x1="6" y1="6" x2="18" y2="18"></line>
                                    </svg>
                                </button>
                            </form>

                            <!-- Stock Indicator Badge -->
                            <div class="wishlist-stock-badge">
                                <?php if ($isSoldOut): ?>
                                    <span class="stock-pill stock-out">Sold Out</span>
                                <?php elseif ($stock <= 3): ?>
                                    <span class="stock-pill stock-limited">Only <?= $stock ?> Left</span>
                                <?php else: ?>
                                    <span class="stock-pill stock-available">In Stock</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="wishlist-card-body">
                            <span class="wishlist-brand-tag">Yatiya Atelier</span>
                            <h2 class="wishlist-product-title">
                                <a href="<?= BASE_URL ?>/product.php?slug=<?= e($item['slug']) ?>">
                                    <?= e($item['name']) ?>
                                </a>
                            </h2>

                            <div class="wishlist-price-wrap">
                                <span class="wishlist-selling-price"><?= format_price($sellingPrice) ?></span>
                                <?php if ($originalPrice > $sellingPrice): ?>
                                    <span class="wishlist-original-price"><?= format_price($originalPrice) ?></span>
                                    <span class="wishlist-discount-badge"><?= $discount ?>% OFF</span>
                                <?php endif; ?>
                            </div>

                            <!-- Move to Cart Action -->
                            <div class="wishlist-card-actions">
                                <form action="<?= BASE_URL ?>/actions/wishlist_actions.php" method="POST" class="wishlist-move-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move_to_cart">
                                    <input type="hidden" name="product_id" value="<?= $prodId ?>">
                                    <button 
                                        type="submit" 
                                        class="btn btn-gold btn-block move-to-bag-btn"
                                        <?= $isSoldOut ? 'disabled' : '' ?>
                                    >
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                                            <line x1="3" y1="6" x2="21" y2="6"></line>
                                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                                        </svg>
                                        <span><?= $isSoldOut ? 'Sold Out' : 'Move to Shopping Bag' ?></span>
                                    </button>
                                </form>
                            </div>
                        </div>

                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </div>
</main>

<!-- Wishlist Specific Vanilla JavaScript -->
<script src="<?= ASSETS_URL ?>/js/wishlist.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
