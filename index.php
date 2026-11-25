<?php
/**
 * YATIYA COUTURE - Luxury Homepage
 * Brand Vision: "ELEVATE YOUR EVERYDAY STYLE"
 * Dynamic CMS-Driven Engine with Admin Preview Mode
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';

$pageTitle = 'Luxury Haute Couture & Ready-to-Wear Fashion';
$activePage = 'home';

// Check for authorized admin preview mode
$isPreviewMode = (!empty($_GET['preview']) && is_admin_logged_in());

// ---------------------------------------------------------------------
// DATABASE DATA FETCHING & SECTION ENGINE
// ---------------------------------------------------------------------

// 1. Fetch Hero Banners
try {
    $bannerStmt = db()->prepare("
        SELECT id, title, subtitle, badge_text, cta_text, cta_link, image_url, mobile_image_url
        FROM banners
        WHERE is_active = 1 AND position = 'hero'
        ORDER BY display_order ASC
    ");
    $bannerStmt->execute();
    $heroBanners = $bannerStmt->fetchAll();
} catch (Exception $e) {
    $heroBanners = [];
}

// 2. Fetch Shop by Category
try {
    $catStmt = db()->prepare("
        SELECT id, name, slug, description, image_url
        FROM categories
        WHERE is_active = 1 AND slug IN ('women', 'men', 'kids', 'footwear', 'bags', 'accessories')
        ORDER BY FIELD(slug, 'women', 'men', 'kids', 'footwear', 'bags', 'accessories')
    ");
    $catStmt->execute();
    $shopCategories = $catStmt->fetchAll();
} catch (Exception $e) {
    $shopCategories = [];
}

$categoryImages = [
    'women' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=800&q=80',
    'men' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=800&q=80',
    'kids' => 'https://images.unsplash.com/photo-1622290291468-a28f7a7dc6a8?auto=format&fit=crop&w=800&q=80',
    'footwear' => 'https://images.unsplash.com/photo-1543163521-1bf539c55dd2?auto=format&fit=crop&w=800&q=80',
    'bags' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?auto=format&fit=crop&w=800&q=80',
    'accessories' => 'https://images.unsplash.com/photo-1608256246200-53e635b5b65f?auto=format&fit=crop&w=800&q=80'
];

// Helper: Query products with images and category
if (!function_exists('fetch_homepage_products')) {
    function fetch_homepage_products(string $filterCol, int $limit = 8): array {
        try {
            $allowed = ['is_new', 'is_featured', 'is_bestseller', 'is_summer_midi', 'is_trending_coord'];
            if (!in_array($filterCol, $allowed, true)) {
                return [];
            }
            $sql = "
                SELECT p.*, c.name AS category_name,
                       (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
                       (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 0 ORDER BY sort_order ASC LIMIT 1) AS hover_image,
                       (SELECT GROUP_CONCAT(DISTINCT size ORDER BY FIELD(size, 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'Free Size', 'One Size') SEPARATOR ', ') FROM product_variants WHERE product_id = p.id AND status = 'active') AS available_sizes,
                       (SELECT GROUP_CONCAT(DISTINCT color SEPARATOR ', ') FROM product_variants WHERE product_id = p.id AND status = 'active') AS available_colors
                FROM products p
                INNER JOIN categories c ON p.category_id = c.id
                WHERE p.$filterCol = 1 AND p.status = 'active'
                ORDER BY p.created_at DESC
                LIMIT :lim
            ";
            $stmt = db()->prepare($sql);
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            return [];
        }
    }
}

// Helper: Query curated or flag-based products for a homepage section
if (!function_exists('fetch_curated_or_flag_products')) {
    function fetch_curated_or_flag_products(int $sectionId, string $filterCol, int $limit = 8): array {
        try {
            $curated = db()->prepare("
                SELECT p.*, c.name AS category_name,
                       (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
                       (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 0 ORDER BY sort_order ASC LIMIT 1) AS hover_image,
                       (SELECT GROUP_CONCAT(DISTINCT size ORDER BY FIELD(size, 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'Free Size', 'One Size') SEPARATOR ', ') FROM product_variants WHERE product_id = p.id AND status = 'active') AS available_sizes,
                       (SELECT GROUP_CONCAT(DISTINCT color SEPARATOR ', ') FROM product_variants WHERE product_id = p.id AND status = 'active') AS available_colors
                FROM homepage_section_items hsi
                INNER JOIN products p ON (hsi.item_type = 'product' AND hsi.item_id = p.id)
                INNER JOIN categories c ON p.category_id = c.id
                WHERE hsi.section_id = ? AND hsi.is_active = 1 AND p.status = 'active'
                ORDER BY hsi.display_order ASC
            ");
            $curated->execute([$sectionId]);
            $rows = $curated->fetchAll();
            if (!empty($rows)) {
                return $rows;
            }
        } catch (Exception $e) {
            // Fallback to flag query
        }

        return fetch_homepage_products($filterCol, $limit);
    }
}

// 0. Circular Category Stories
try {
    $storiesStmt = db()->query("SELECT * FROM homepage_stories WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
    $homepageStories = $storiesStmt->fetchAll();
} catch (Exception $e) {
    $homepageStories = [];
}

// 7b. Style Stories & Inspirations
try {
    $styleStmt = db()->query("SELECT * FROM style_stories WHERE is_active = 1 ORDER BY display_order ASC, id ASC");
    $styleStories = $styleStmt->fetchAll();
} catch (Exception $e) {
    $styleStories = [];
}

// 6. Exclusive Collection Banner
try {
    $exclusiveStmt = db()->prepare("
        SELECT id, title, subtitle, badge_text, cta_text, cta_link, image_url
        FROM banners
        WHERE is_active = 1 AND position = 'exclusive_offer'
        ORDER BY display_order ASC
        LIMIT 1
    ");
    $exclusiveStmt->execute();
    $exclusiveBanner = $exclusiveStmt->fetch();
} catch (Exception $e) {
    $exclusiveBanner = null;
}

// 8. Customer Reviews
try {
    $reviewsStmt = db()->prepare("
        SELECT pr.*, c.first_name, c.last_name, p.name AS product_name
        FROM product_reviews pr
        INNER JOIN customers c ON pr.customer_id = c.id
        LEFT JOIN products p ON pr.product_id = p.id
        WHERE pr.status = 'approved'
        ORDER BY pr.rating DESC, pr.created_at DESC
        LIMIT 3
    ");
    $reviewsStmt->execute();
    $customerReviews = $reviewsStmt->fetchAll();
} catch (Exception $e) {
    $customerReviews = [];
}

// Fetch active sections from CMS table
try {
    $whereClause = $isPreviewMode ? "WHERE publish_status != 'archived'" : "WHERE is_active = 1 AND publish_status = 'published'";
    $sectionsStmt = db()->query("SELECT * FROM homepage_sections $whereClause ORDER BY display_order ASC, id ASC");
    $cmsSections = $sectionsStmt->fetchAll();
} catch (Exception $e) {
    $cmsSections = [];
}

// If no sections in DB, create standard fallback
if (empty($cmsSections)) {
    $cmsSections = [
        ['section_key' => 'circular_stories', 'title' => 'Explore Categories', 'is_active' => 1],
        ['section_key' => 'hero_slider', 'title' => 'Hero Slider', 'is_active' => 1],
        ['section_key' => 'shop_by_category', 'title' => 'Shop by Category', 'is_active' => 1],
        ['section_key' => 'new_arrivals', 'title' => 'New Arrivals', 'is_active' => 1],
        ['section_key' => 'trending_now', 'title' => 'Trending Co-ord Sets', 'is_active' => 1],
        ['section_key' => 'best_sellers', 'title' => 'Best Sellers', 'is_active' => 1],
        ['section_key' => 'summer_midi', 'title' => 'Summer Midi', 'is_active' => 1],
        ['section_key' => 'exclusive_collection', 'title' => 'Exclusive Collection', 'is_active' => 1],
        ['section_key' => 'style_stories', 'title' => 'Style Stories & Inspirations', 'is_active' => 1],
        ['section_key' => 'why_yatiya', 'title' => 'Why Yatiya Couture', 'is_active' => 1],
        ['section_key' => 'customer_reviews', 'title' => 'Patron Testimonials', 'is_active' => 1],
        ['section_key' => 'newsletter', 'title' => 'Privileged Access', 'is_active' => 1]
    ];
}

// Helper: Render Reusable Product Card
if (!function_exists('render_product_card_ui')) {
    function render_product_card_ui(array $product): void {
        $title = e($product['name']);
        $slug = e($product['slug']);
        $url = BASE_URL . '/product.php?slug=' . urlencode($slug);
        $price = (float)$product['selling_price'];
        $original = (float)$product['original_price'];
        $discount = calculate_discount_percentage($original, $price);
        $primaryImg = e($product['primary_image'] ?? 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=800&q=80');
        $hoverImg = !empty($product['hover_image']) ? e($product['hover_image']) : '';
        $categoryName = e($product['category_name'] ?? 'Haute Couture');
        ?>
        <div class="product-card luxury-card-interactive">
            <div class="product-media">
                <a href="<?= $url ?>" aria-label="<?= $title ?>">
                    <img src="<?= $primaryImg ?>" alt="<?= $title ?>" class="product-img" loading="lazy">
                    <?php if ($hoverImg): ?>
                        <img src="<?= $hoverImg ?>" alt="<?= $title ?> Alternate" class="product-hover-img" loading="lazy">
                    <?php endif; ?>
                </a>

                <div class="product-badge-group">
                    <?php if ($discount > 0): ?>
                        <span class="product-badge badge-discount shiny-badge"><?= $discount ?>% OFF</span>
                    <?php elseif (!empty($product['is_new'])): ?>
                        <span class="product-badge badge-new pulse-badge">NEW RUNWAY</span>
                    <?php elseif (!empty($product['is_bestseller'])): ?>
                        <span class="product-badge badge-bestseller gold-badge">BESTSELLER</span>
                    <?php endif; ?>
                </div>

                <button type="button" class="product-wishlist-btn <?= is_product_in_wishlist((int)$product['id']) ? 'active' : '' ?>" aria-label="Add to Wishlist" data-id="<?= (int)$product['id'] ?>" title="Save to Wishlist">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                    </svg>
                </button>

                <button type="button" 
                        class="product-quickview-btn glass-quickview" 
                        data-id="<?= (int)$product['id'] ?>"
                        data-title="<?= $title ?>"
                        data-price="<?= format_price($price) ?>"
                        data-original-price="<?= ($original > $price) ? format_price($original) : '' ?>"
                        data-discount="<?= ($discount > 0) ? $discount . '% OFF' : '' ?>"
                        data-image="<?= $primaryImg ?>"
                        data-desc="<?= e($product['short_description'] ?? '') ?>"
                        data-link="<?= $url ?>"
                        data-stock="<?= (int)$product['stock_quantity'] ?>"
                        data-sizes="<?= e($product['available_sizes'] ?? '') ?>"
                        data-colors="<?= e($product['available_colors'] ?? '') ?>">
                    Quick View
                </button>
            </div>

            <div class="product-details">
                <span class="product-category-tag"><?= $categoryName ?></span>
                <h4 class="product-title"><a href="<?= $url ?>"><?= $title ?></a></h4>
                <div class="product-price-wrap">
                    <span class="product-selling-price"><?= format_price($price) ?></span>
                    <?php if ($original > $price): ?>
                        <span class="product-original-price"><?= format_price($original) ?></span>
                        <span class="product-discount-label"><?= $discount ?>% OFF</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<?php if ($isPreviewMode): ?>
    <!-- Luxury Authoritative Admin Preview Banner -->
    <div style="position: sticky; top: 0; z-index: 99999; background: #24211E; color: #D6BC7A; padding: 10px 24px; font-size: 13px; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #C6A15B; box-shadow: 0 4px 12px rgba(0,0,0,0.35);">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #C6A15B;"></span>
            <strong>ATELIER CMS PREVIEW ACTIVE:</strong>
            <span style="color: #FFF;">Viewing unpublished drafts &amp; customized display order as administrator.</span>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <a href="<?= ADMIN_URL ?>/homepage-sections.php" class="btn btn-gold btn-xs" style="padding: 4px 12px; font-size: 11px;">
                &larr; Back to CMS
            </a>
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline btn-xs" style="padding: 4px 12px; font-size: 11px; color: #FFF; border-color: rgba(255,255,255,0.4);">
                Exit Preview Mode
            </a>
        </div>
    </div>
<?php endif; ?>

<?php
// ---------------------------------------------------------------------
// DYNAMIC SECTION RENDERING LOOP
// ---------------------------------------------------------------------
foreach ($cmsSections as $section):
    $secKey = $section['section_key'];
    $secTitle = $section['title'] ?? '';
    $secSubtitle = $section['subtitle'] ?? '';
    $secId = (int)($section['id'] ?? 0);
    $content = !empty($section['content_json']) ? json_decode($section['content_json'], true) : [];

    switch ($secKey):
        case 'circular_stories':
            if (!empty($homepageStories)):
?>
                <!-- CIRCULAR CATEGORY STORIES CAROUSEL -->
                <section class="circular-stories-section" aria-label="Explore Categories" data-reveal="fade-up">
                    <div class="stories-carousel-wrap">
                        <button type="button" class="story-arrow-btn story-prev-btn" id="storyPrevBtn" aria-label="Previous Stories" data-magnetic>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </button>

                        <div class="stories-track" id="storiesTrack">
                            <?php foreach ($homepageStories as $story): ?>
                                <a href="<?= BASE_URL ?>/<?= ltrim(e($story['link_url']), '/') ?>" class="story-item" aria-label="<?= e($story['title']) ?>">
                                    <div class="story-avatar-wrap">
                                        <img src="<?= e($story['image_url']) ?>" alt="<?= e($story['title']) ?>" class="story-avatar-img" loading="lazy" onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=600&q=80';">
                                    </div>
                                    <span class="story-title"><?= e($story['title']) ?></span>
                                </a>
                            <?php endforeach; ?>
                        </div>

                        <button type="button" class="story-arrow-btn story-next-btn" id="storyNextBtn" aria-label="Next Stories" data-magnetic>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                                <polyline points="9 18 15 12 9 6"></polyline>
                            </svg>
                        </button>
                    </div>
                </section>
<?php
            endif;
            break;

        case 'hero_slider':
?>
            <!-- SECTION 1: HERO SLIDER -->
            <section class="hero-slider-section" aria-label="Haute Couture Campaign">
                <div class="hero-slider-container">
                    <?php if (!empty($heroBanners)): ?>
                        <?php foreach ($heroBanners as $index => $banner): ?>
                            <div class="hero-slide <?= ($index === 0) ? 'active' : '' ?>">
                                <img src="<?= e($banner['image_url']) ?>" alt="<?= e($banner['title']) ?>" class="hero-slide-bg" loading="<?= ($index === 0) ? 'eager' : 'lazy' ?>">
                                <div class="hero-slide-overlay"></div>
                                <div class="container" style="position: relative; z-index: 2;">
                                    <div class="hero-content">
                                        <?php if (!empty($banner['badge_text'])): ?>
                                            <span class="hero-badge"><?= e($banner['badge_text']) ?></span>
                                        <?php endif; ?>
                                        <h1 class="hero-title"><?= e($banner['title']) ?></h1>
                                        <?php if (!empty($banner['subtitle'])): ?>
                                            <p class="hero-subtitle"><?= e($banner['subtitle']) ?></p>
                                        <?php endif; ?>
                                        <div class="hero-cta-group">
                                            <a href="<?= BASE_URL ?>/<?= ltrim(e($banner['cta_link']), '/') ?>" class="btn btn-gold">
                                                <?= e($banner['cta_text']) ?>
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                                    <polyline points="12 5 19 12 12 19"></polyline>
                                                </svg>
                                            </a>
                                            <a href="<?= BASE_URL ?>/shop.php" class="btn btn-outline" style="border-color: rgba(255,255,255,0.7); color: #FFF;">
                                                View Lookbook
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="hero-slide active">
                            <div class="container">
                                <div class="hero-content">
                                    <span class="hero-badge">NEW RUNWAY EDIT</span>
                                    <h1 class="hero-title"><?= e($secTitle ?: 'Elevate Your Everyday Style') ?></h1>
                                    <p class="hero-subtitle"><?= e($secSubtitle ?: 'The pinnacle of luxury fashion, crafted with pure handwoven silks and timeless silhouettes.') ?></p>
                                    <a href="<?= BASE_URL ?>/shop.php" class="btn btn-gold">Explore Collections</a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (count($heroBanners) > 1): ?>
                        <div class="hero-controls">
                            <button type="button" class="hero-arrow-btn hero-prev-btn" aria-label="Previous Slide">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="15 18 9 12 15 6"></polyline>
                                </svg>
                            </button>
                            <div class="hero-dots"></div>
                            <button type="button" class="hero-arrow-btn hero-next-btn" aria-label="Next Slide">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 18 15 12 9 6"></polyline>
                                </svg>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
<?php
            break;

        case 'shop_by_category':
?>
            <!-- SECTION 2: SHOP BY CATEGORY -->
            <section class="section-padding" aria-label="Shop By Category">
                <div class="container">
                    <div class="section-header text-center">
                        <span class="section-eyebrow"><?= e($content['eyebrow'] ?? 'The Atelier Taxonomy') ?></span>
                        <h2 class="section-title"><?= e($secTitle ?: 'Shop by Category') ?></h2>
                        <p class="section-subtitle"><?= e($secSubtitle ?: 'Exemplary craftsmanship spanning tailored menswear, couture silhouettes, fine leather and heirloom accessories.') ?></p>
                    </div>

                    <div class="category-showcase-grid">
                        <?php if (!empty($shopCategories)): ?>
                            <?php foreach ($shopCategories as $cat): 
                                $catImage = $categoryImages[$cat['slug']] ?? 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=800&q=80';
                            ?>
                                <a href="<?= BASE_URL ?>/shop.php?category=<?= urlencode($cat['slug']) ?>" class="category-card" aria-label="Explore <?= e($cat['name']) ?>">
                                    <div class="category-image-wrap">
                                        <img src="<?= $catImage ?>" alt="<?= e($cat['name']) ?>" loading="lazy">
                                    </div>
                                    <div class="category-info">
                                        <h3 class="category-name"><?= e($cat['name']) ?></h3>
                                        <span class="category-link-text">Explore &rarr;</span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </section>
<?php
            break;

        case 'new_arrivals':
            $newArrivals = fetch_curated_or_flag_products($secId, 'is_new', 8);
?>
            <!-- SECTION 3: NEW ARRIVALS -->
            <section class="section-padding" style="background-color: var(--bg-secondary); border-top: 1px solid var(--border-subtle); border-bottom: 1px solid var(--border-subtle);" aria-label="New Arrivals">
                <div class="container">
                    <div class="section-header-flex">
                        <div>
                            <span class="section-eyebrow"><?= e($content['eyebrow'] ?? 'Autumn / Winter 2026') ?></span>
                            <h2 class="section-title"><?= e($secTitle ?: 'New Arrivals') ?></h2>
                            <p class="section-subtitle" style="margin: 0; text-align: left;"><?= e($secSubtitle ?: 'Fresh off the runway: handcrafted drapes, raw silk separates and tailored suits.') ?></p>
                        </div>
                        <a href="<?= BASE_URL ?>/shop.php?category=new-arrivals" class="btn btn-outline btn-sm">
                            View All New Arrivals &rarr;
                        </a>
                    </div>

                    <?php if (!empty($newArrivals)): ?>
                        <div class="product-grid">
                            <?php foreach ($newArrivals as $prod): ?>
                                <?php render_product_card_ui($prod); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="editorial-empty-state">
                            <span class="empty-state-badge">AUTUMN / WINTER 2026 RUNWAY DROP</span>
                            <h3 class="empty-state-title font-serif">Curating the New Season Arrivals</h3>
                            <p class="empty-state-desc">
                                Our master karigars in Varanasi, Kashmir and Jaipur are completing the final hand-embroidery on the upcoming runway release. Patrons may explore all atelier collections or join the Private Salon for privileged first-access allocation.
                            </p>
                            <div class="empty-state-actions">
                                <a href="<?= BASE_URL ?>/shop.php" class="btn btn-gold">Explore All Collections</a>
                                <a href="#newsletterSection" class="btn btn-outline">Join Private Salon For Priority Access</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
<?php
            break;

        case 'trending_now':
            $trendingCoords = fetch_curated_or_flag_products($secId, 'is_trending_coord', 8);
            if (empty($trendingCoords)) {
                $trendingCoords = fetch_homepage_products('is_featured', 4);
            }
?>
            <!-- SECTION 4: TRENDING CO-ORD SETS (Trending Now) -->
            <section class="section-padding trending-coords-section" aria-label="Trending Now">
                <div class="container">
                    <div class="section-header-flex trending-header-custom">
                        <div>
                            <span class="section-eyebrow eyebrow-trending"><span class="animated-flame">✨</span> Runway &amp; Modern Ensembles <span class="live-pulse"></span></span>
                            <h2 class="section-title title-trending"><?= e($secTitle ?: 'Trending Co-ord Sets') ?></h2>
                            <p class="section-subtitle" style="margin: 0; text-align: left;"><?= e($secSubtitle ?: 'Handcrafted monochrome separates, raw silk pairings, and tailored co-ords curated for timeless style.') ?></p>
                        </div>
                        <a href="<?= BASE_URL ?>/shop.php?category=women-blazers" class="btn btn-outline btn-sm btn-pill-gold">
                            Explore All Co-ords &rarr;
                        </a>
                    </div>

                    <?php if (!empty($trendingCoords)): ?>
                        <div class="product-grid coords-custom-grid">
                            <?php foreach ($trendingCoords as $prod): ?>
                                <?php render_product_card_ui($prod); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="trending-showcase-wrap">
                            <div class="trending-feature-banner">
                                <img src="https://images.unsplash.com/photo-1509631179647-0177331693ae?auto=format&fit=crop&w=1200&q=80" alt="Trending Editorial Collection" loading="lazy">
                                <div class="trending-banner-overlay">
                                    <span class="hero-badge" style="width: fit-content;">TRENDING EDITORIAL</span>
                                    <h3 class="font-serif" style="color: #FFFFFF; font-size: 1.8rem; margin-bottom: 6px;">The Royal Wedding Curation</h3>
                                    <p style="font-size: 0.88rem; color: rgba(255,255,255,0.8); margin-bottom: 16px;">Handloom brocades, raw silk kurtas, and handcrafted minaudières for festive evenings.</p>
                                    <a href="<?= BASE_URL ?>/shop.php?collection=wedding-salon" class="btn btn-gold btn-sm" style="width: fit-content;">
                                        Discover Editorial &rarr;
                                    </a>
                                </div>
                            </div>

                            <div class="trending-products-side">
                                <div class="editorial-empty-state" style="box-shadow: none; border-color: var(--border-subtle); padding: var(--space-xl);">
                                    <span class="empty-state-badge">CURATED SALON SPOTLIGHT</span>
                                    <h4 class="empty-state-title font-serif" style="font-size: 1.4rem;">Trending Runway Selections</h4>
                                    <p class="empty-state-desc" style="font-size: 0.88rem; margin-bottom: 20px;">
                                        Trending catalog pieces will populate dynamically in Phase 3. Experience our full runway lookbook or schedule a bespoke appointment with an atelier consultant.
                                    </p>
                                    <div class="empty-state-actions">
                                        <a href="<?= BASE_URL ?>/shop.php" class="btn btn-primary btn-sm">Explore All Pieces</a>
                                        <a href="<?= BASE_URL ?>/contact.php" class="btn btn-outline btn-sm">Book Atelier Appointment</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
<?php
            break;

        case 'best_sellers':
            $bestSellers = fetch_curated_or_flag_products($secId, 'is_bestseller', 8);
?>
            <!-- SECTION 5: BEST SELLERS -->
            <section class="section-padding" style="background-color: var(--bg-secondary); border-top: 1px solid var(--border-subtle); border-bottom: 1px solid var(--border-subtle);" aria-label="Best Sellers">
                <div class="container">
                    <div class="section-header-flex">
                        <div>
                            <span class="section-eyebrow"><?= e($content['eyebrow'] ?? 'House Signatures') ?></span>
                            <h2 class="section-title"><?= e($secTitle ?: 'Best Sellers') ?></h2>
                            <p class="section-subtitle" style="margin: 0; text-align: left;"><?= e($secSubtitle ?: 'Enduring classics and globally acclaimed pieces that define the Yatiya aesthetic.') ?></p>
                        </div>
                        <a href="<?= BASE_URL ?>/shop.php?category=best-sellers" class="btn btn-outline btn-sm">
                            View All Best Sellers &rarr;
                        </a>
                    </div>

                    <?php if (!empty($bestSellers)): ?>
                        <div class="product-grid">
                            <?php foreach ($bestSellers as $prod): ?>
                                <?php render_product_card_ui($prod); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="editorial-empty-state">
                            <span class="empty-state-badge">ENDURING HOUSE CLASSICS</span>
                            <h3 class="empty-state-title font-serif">The Signature Best Sellers Archive</h3>
                            <p class="empty-state-desc">
                                From our iconic Banarasi tissue saris to imperial velvet bandhgalas, our best-selling signature pieces represent the highest standard of Indian artisanal luxury.
                            </p>
                            <div class="empty-state-actions">
                                <a href="<?= BASE_URL ?>/shop.php?category=best-sellers" class="btn btn-gold">Browse Signature Icons</a>
                                <a href="<?= BASE_URL ?>/about.php" class="btn btn-outline">Our Craftsmanship Heritage</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
<?php
            break;

        case 'summer_midi':
            $summerMidi = fetch_curated_or_flag_products($secId, 'is_summer_midi', 8);
?>
            <!-- SECTION 5B: SUMMER MIDI COLLECTION -->
            <section class="section-padding summer-midi-section" aria-label="Summer Midi Collection">
                <div class="container">
                    <div class="section-header-flex summer-header-custom">
                        <div>
                            <span class="section-eyebrow eyebrow-resort"><span class="animated-sun">☀️</span> Sunlit Resort &amp; Daytime Glamour</span>
                            <h2 class="section-title title-summer"><?= e($secTitle ?: 'Summer Midi') ?></h2>
                            <p class="section-subtitle" style="margin: 0; text-align: left;"><?= e($secSubtitle ?: 'Breezy handwoven silhouettes, tiered chanderi slips, and daytime midi creations designed for effortless elegance.') ?></p>
                        </div>
                        <a href="<?= BASE_URL ?>/shop.php?search=midi" class="btn btn-outline btn-sm btn-pill-resort">
                            View Summer Midi Edit &rarr;
                        </a>
                    </div>

                    <?php if (!empty($summerMidi)): ?>
                        <div class="product-grid summer-custom-grid">
                            <?php foreach ($summerMidi as $prod): ?>
                                <?php render_product_card_ui($prod); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="editorial-empty-state">
                            <span class="empty-state-badge">SUMMER RESORT CAPSULE</span>
                            <h3 class="empty-state-title font-serif">Curating the Summer Midi Capsule</h3>
                            <p class="empty-state-desc">
                                Breezy chanderi silk midis and floral handcrafted tiers are arriving from our ateliers.
                            </p>
                            <div class="empty-state-actions">
                                <a href="<?= BASE_URL ?>/shop.php" class="btn btn-gold">Explore All Collections</a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
<?php
            break;

        case 'exclusive_collection':
            $exImage = !empty($content['image_url']) ? e($content['image_url']) : (!empty($exclusiveBanner['image_url']) ? e($exclusiveBanner['image_url']) : 'https://images.unsplash.com/photo-1509631179647-0177331693ae?auto=format&fit=crop&w=1600&q=80');
            $exBadge = !empty($content['badge_text']) ? e($content['badge_text']) : (!empty($exclusiveBanner['badge_text']) ? e($exclusiveBanner['badge_text']) : 'HAUTE COUTURE');
            $exTitle = !empty($secTitle) ? e($secTitle) : (!empty($exclusiveBanner['title']) ? e($exclusiveBanner['title']) : 'Bespoke Bridal & Red-Carpet Privé');
            $exSubtitle = !empty($secSubtitle) ? e($secSubtitle) : (!empty($exclusiveBanner['subtitle']) ? e($exclusiveBanner['subtitle']) : 'Tailored to perfection by master artisans with over 400 hours of hand embroidery.');
            $exCta = !empty($content['cta_text']) ? e($content['cta_text']) : (!empty($exclusiveBanner['cta_text']) ? e($exclusiveBanner['cta_text']) : 'Book Atelier Appointment');
            $exLink = !empty($content['cta_link']) ? e($content['cta_link']) : (!empty($exclusiveBanner['cta_link']) ? e($exclusiveBanner['cta_link']) : 'contact.php');
?>
            <!-- SECTION 6: EXCLUSIVE COLLECTION -->
            <section class="exclusive-collection-section" aria-label="Exclusive Collection">
                <div class="exclusive-banner-inner">
                    <img src="<?= $exImage ?>" alt="<?= $exTitle ?>" class="exclusive-banner-bg" loading="lazy">
                    <div class="container">
                        <div class="exclusive-banner-content">
                            <span class="section-eyebrow"><?= $exBadge ?></span>
                            <h2 class="font-serif"><?= $exTitle ?></h2>
                            <p><?= $exSubtitle ?></p>
                            <div class="hero-cta-group">
                                <a href="<?= BASE_URL ?>/<?= ltrim($exLink, '/') ?>" class="btn btn-gold">
                                    <?= $exCta ?>
                                </a>
                                <a href="<?= BASE_URL ?>/about.php#craftsmanship" class="btn btn-outline" style="border-color: rgba(255,255,255,0.7); color: #FFF;">
                                    Explore Craftsmanship
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
<?php
            break;

        case 'style_stories':
            if (!empty($styleStories)):
?>
                <!-- SECTION 6B: STYLE STORIES & INSPIRATIONS -->
                <section class="section-padding" style="background-color: var(--bg-secondary); border-top: 1px solid var(--border-subtle); border-bottom: 1px solid var(--border-subtle);" aria-label="Style Stories & Inspirations">
                    <div class="container">
                        <div class="section-header text-center">
                            <span class="section-eyebrow">The Atelier Journal</span>
                            <h2 class="section-title"><?= e($secTitle ?: 'Style Stories & Inspirations') ?></h2>
                            <p class="section-subtitle"><?= e($secSubtitle ?: 'Styling masterclasses, heritage craft perspectives, and seasonal wardrobe curation from our creative salon.') ?></p>
                        </div>

                        <div class="style-stories-grid">
                            <?php foreach ($styleStories as $story): ?>
                                <article class="style-story-card">
                                    <div class="style-story-media">
                                        <img src="<?= e($story['image_url']) ?>" alt="<?= e($story['title']) ?>" class="style-story-img" loading="lazy">
                                    </div>
                                    <div class="style-story-content">
                                        <div class="style-story-meta">
                                            <span class="style-story-tag"><?= e($story['tag'] ?: 'STYLE EDITORIAL') ?></span>
                                            <span><?= e($story['read_time'] ?: '4 min read') ?></span>
                                        </div>
                                        <h3 class="style-story-title"><?= e($story['title']) ?></h3>
                                        <p class="style-story-excerpt"><?= e($story['excerpt']) ?></p>
                                        <a href="<?= BASE_URL ?>/<?= ltrim(e($story['link_url']), '/') ?>" class="style-story-link">
                                            Explore Story &rarr;
                                        </a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
<?php
            endif;
            break;

        case 'why_yatiya':
?>
            <!-- SECTION 7: WHY YATIYA COUTURE -->
            <section class="section-padding" aria-label="Why Yatiya Couture">
                <div class="container">
                    <div class="section-header text-center">
                        <span class="section-eyebrow">The Atelier Standards</span>
                        <h2 class="section-title"><?= e($secTitle ?: 'Why Yatiya Couture') ?></h2>
                        <p class="section-subtitle"><?= e($secSubtitle ?: 'Upholding uncompromising excellence across every stitch, thread, and client interaction.') ?></p>
                    </div>

                    <div class="why-yatiya-grid">
                        <div class="why-yatiya-card">
                            <div class="why-card-icon">✦</div>
                            <h3 class="why-card-title">Premium Quality</h3>
                            <p class="why-card-desc">
                                Woven from 100% pure Mulberry, Banarasi, and Chanderi silks with genuine 24K gold zari threads, crafted to endure across generations.
                            </p>
                        </div>
                        <div class="why-yatiya-card">
                            <div class="why-card-icon">❖</div>
                            <h3 class="why-card-title">Curated Collections</h3>
                            <p class="why-card-desc">
                                Limited edition runs and bespoke couture capsules engineered to ensure rarity, individuality, and timeless sartorial distinction.
                            </p>
                        </div>
                        <div class="why-yatiya-card">
                            <div class="why-card-icon">⚜</div>
                            <h3 class="why-card-title">Secure Shopping</h3>
                            <p class="why-card-desc">
                                Bank-grade 256-bit encryption, strict server-side transaction signing, transparent tracking, and reliable Cash on Delivery verification.
                            </p>
                        </div>
                        <div class="why-yatiya-card">
                            <div class="why-card-icon">◈</div>
                            <h3 class="why-card-title">Customer-first Experience</h3>
                            <p class="why-card-desc">
                                Complimentary insured white-glove delivery, dedicated personal atelier stylists, and effortless exchange protocols.
                            </p>
                        </div>
                    </div>
                </div>
            </section>
<?php
            break;

        case 'customer_reviews':
?>
            <!-- SECTION 8: CUSTOMER REVIEWS / BRAND PROMISE -->
            <section class="section-padding reviews-section" aria-label="Patron Testimonials & Brand Promise">
                <div class="container">
                    <?php if (!empty($customerReviews)): ?>
                        <div class="section-header text-center">
                            <span class="section-eyebrow">Patron Accolades</span>
                            <h2 class="section-title"><?= e($secTitle ?: 'Client Reflections') ?></h2>
                            <p class="section-subtitle"><?= e($secSubtitle ?: 'Authentic reviews from verified patrons who have experienced the Yatiya atelier.') ?></p>
                        </div>

                        <div class="reviews-grid">
                            <?php foreach ($customerReviews as $rev): ?>
                                <div class="review-card">
                                    <div class="review-stars">
                                        <?= str_repeat('★', (int)$rev['rating']) . str_repeat('☆', 5 - (int)$rev['rating']) ?>
                                    </div>
                                    <h4 class="review-title"><?= e($rev['review_title']) ?></h4>
                                    <p class="review-text">&ldquo;<?= e($rev['review_text']) ?>&rdquo;</p>
                                    <div class="review-author">
                                        <?= e($rev['first_name'] . ' ' . substr($rev['last_name'], 0, 1) . '.') ?> 
                                        <span class="text-gold">&bull; Verified Patron</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="brand-promise-wrap">
                            <div class="promise-content">
                                <span class="section-eyebrow">The Patron Commitment</span>
                                <h2 class="section-title font-serif">The Yatiya Atelier Promise</h2>
                                <p>
                                    We hold our creations to an unyielding standard of purity, authenticity, and sartorial dignity. Because every couture piece represents hundreds of hours of generational weaving, our relationship with each patron is built upon absolute transparency.
                                </p>
                                <p>
                                    Customer reviews displayed in our salons are submitted solely by authenticated purchasers who have experienced our garments firsthand.
                                </p>

                                <div class="promise-features">
                                    <div class="promise-feature-item">
                                        <strong>Certified Pure Handlooms</strong>
                                        <span>Silk Mark & Handloom Board Authenticated</span>
                                    </div>
                                    <div class="promise-feature-item">
                                        <strong>White-Glove Care</strong>
                                        <span>Personal Concierge for Bespoke Tailoring</span>
                                    </div>
                                    <div class="promise-feature-item">
                                        <strong>Complimentary Insured Shipping</strong>
                                        <span>Protected Delivery Across India on ₹2,999+</span>
                                    </div>
                                    <div class="promise-feature-item">
                                        <strong>Transparent Returns</strong>
                                        <span>Guaranteed 7-Day Privilege Exchange</span>
                                    </div>
                                </div>
                            </div>

                            <div class="promise-visual-wrap">
                                <img src="https://images.unsplash.com/photo-1445205170230-053b83016050?auto=format&fit=crop&w=1000&q=80" alt="Yatiya Couture Atelier Handcrafting" loading="lazy">
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
<?php
            break;

        case 'newsletter':
?>
            <!-- SECTION 9: NEWSLETTER SUBSCRIPTION -->
            <section class="section-padding newsletter-section" id="newsletterSection" aria-label="Newsletter Subscription">
                <div class="container">
                    <div class="newsletter-container">
                        <div class="newsletter-icon-wrap"><span class="newsletter-sparkle-anim">✨</span></div>
                        <span class="section-eyebrow" style="color: var(--gold-champagne);"><?= e($content['eyebrow'] ?? 'Privileged Access') ?></span>
                        <h2 class="newsletter-title font-serif"><?= e($secTitle ?: 'Join The Yatiya Couture') ?></h2>
                        <p class="newsletter-subtitle">
                            <?= e($secSubtitle ?: 'Receive private atelier invitations, seasonal lookbook previews, and privileged early access to limited edition couture releases.') ?>
                        </p>
                        <form action="<?= BASE_URL ?>/actions/newsletter_subscribe.php" method="POST" class="newsletter-form">
                            <?= csrf_field() ?>
                            <input 
                                type="email" 
                                name="email" 
                                class="newsletter-input" 
                                placeholder="Enter your privileged email address" 
                                autocomplete="email"
                                required
                            >
                            <button type="submit" class="btn btn-gold">Subscribe</button>
                        </form>
                        <p class="newsletter-note">By subscribing, you agree to our Privacy Policy. You may withdraw consent at any time.</p>
                    </div>
                </div>
            </section>
<?php
            break;

    endswitch;
endforeach;
?>

<!-- =====================================================================
     SECTION 10: MASTER FOOTER
     ===================================================================== -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>
