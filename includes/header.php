<?php
/**
 * YATIYA COUTURE - Luxury Master Header & Navigation System
 * Brand Vision: "ELEVATE YOUR EVERYDAY STYLE"
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Dynamic page meta variables
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME . ' — ' . SITE_TAGLINE;
$pageDescription = $pageDescription ?? 'Experience the pinnacle of luxury fashion with Yatiya Couture. Discover bespoke silks, royal lehengas, artisanal footwear, fine jewellery, and modern ready-to-wear.';
$activePage = $activePage ?? '';

// Dynamic Store Configuration
$announcementText = get_setting('announcement_bar_text', 'Complimentary White-Glove Delivery Across India on Orders Above ₹2,999 | Discover Autumn / Winter 2026');
$announcementLink = get_setting('announcement_bar_link', 'shop.php?category=new-arrivals');
$categories = get_active_categories(20);
$cartCount = get_cart_count();
$wishlistCount = get_wishlist_count();
$currentUser = current_user();

// SEO & Metadata settings
$defaultMetaTitle = get_setting('meta_title', SITE_NAME . ' — ' . SITE_TAGLINE);
$defaultMetaDesc = get_setting('meta_description', 'Experience the pinnacle of luxury fashion with Yatiya Couture. Discover bespoke silks, royal lehengas, artisanal footwear, fine jewellery, and modern ready-to-wear.');
$defaultOgImage = get_setting('og_image_url', 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1200&q=80');
$canonicalBase = get_setting('canonical_base_url', BASE_URL);

if (!isset($pageTitle)) {
    $pageTitle = $defaultMetaTitle;
}
$pageDescription = $pageDescription ?? $defaultMetaDesc;

// Curated Mega Menu Definitions matching brand taxonomy
$megaMenus = [
    'women' => [
        'title' => 'Women\'s Atelier',
        'columns' => [
            [
                'heading' => 'Haute Couture & Heritage',
                'links' => [
                    ['title' => 'Handwoven Silk Saris', 'url' => 'shop.php?category=women&type=saris'],
                    ['title' => 'Bridal & Occasion Lehengas', 'url' => 'shop.php?category=women&type=lehengas'],
                    ['title' => 'Embroidered Anarkalis', 'url' => 'shop.php?category=women&type=anarkalis'],
                    ['title' => 'Chanderi & Banarasi Drapes', 'url' => 'shop.php?category=women&type=handlooms'],
                    ['title' => 'Royal Sharara Sets', 'url' => 'shop.php?category=women&type=shararas'],
                ]
            ],
            [
                'heading' => 'Contemporary Ready-To-Wear',
                'links' => [
                    ['title' => 'Tailored Atelier Blazers', 'url' => 'shop.php?category=women&type=blazers'],
                    ['title' => 'Silk Chiffon Evening Gowns', 'url' => 'shop.php?category=women&type=gowns'],
                    ['title' => 'Pure Linen Trousers & Shirts', 'url' => 'shop.php?category=women&type=linen'],
                    ['title' => 'Resort Silk Kaftans', 'url' => 'shop.php?category=women&type=kaftans'],
                    ['title' => 'Atelier Co-ord Ensembles', 'url' => 'shop.php?category=women&type=coords'],
                ]
            ],
            [
                'heading' => 'The Curated Occasion',
                'links' => [
                    ['title' => 'The Royal Wedding Salon', 'url' => 'shop.php?collection=wedding-salon'],
                    ['title' => 'Festive Cocktail Soirée', 'url' => 'shop.php?collection=cocktail-soiree'],
                    ['title' => 'Winter Silk & Velvet Edit', 'url' => 'shop.php?collection=winter-velvet'],
                    ['title' => 'Artisanal Handloom Archive', 'url' => 'shop.php?collection=handloom-archive'],
                ]
            ]
        ],
        'spotlight' => [
            'tag' => 'AUTUMN / WINTER 2026',
            'title' => 'The Royal Silk Edit',
            'desc' => 'Pure handwoven Mulberry & Banarasi silks.',
            'image' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=600&q=80',
            'link' => 'shop.php?category=women'
        ]
    ],
    'men' => [
        'title' => 'Men\'s Atelier',
        'columns' => [
            [
                'heading' => 'Imperial Tailoring',
                'links' => [
                    ['title' => 'Bespoke Bandhgalas', 'url' => 'shop.php?category=men&type=bandhgalas'],
                    ['title' => 'Royal Sherwani Ensembles', 'url' => 'shop.php?category=men&type=sherwanis'],
                    ['title' => 'Handcrafted Kurta Sets', 'url' => 'shop.php?category=men&type=kurtas'],
                    ['title' => 'Embroidered Waistcoats', 'url' => 'shop.php?category=men&type=waistcoats'],
                    ['title' => 'Modern Velvet Tuxedos', 'url' => 'shop.php?category=men&type=tuxedos'],
                ]
            ],
            [
                'heading' => 'Everyday Luxury',
                'links' => [
                    ['title' => 'Pure Linen Dress Shirts', 'url' => 'shop.php?category=men&type=shirts'],
                    ['title' => 'Pleated Atelier Trousers', 'url' => 'shop.php?category=men&type=trousers'],
                    ['title' => 'Pure Cashmere Sweaters', 'url' => 'shop.php?category=men&type=cashmere'],
                    ['title' => 'Casual Silk Polos', 'url' => 'shop.php?category=men&type=polos'],
                ]
            ],
            [
                'heading' => 'Atelier Accents',
                'links' => [
                    ['title' => 'Pure Silk Pocket Squares', 'url' => 'shop.php?category=accessories&type=pocket-squares'],
                    ['title' => 'Handcrafted Leather Mojaris', 'url' => 'shop.php?category=footwear&gender=men'],
                    ['title' => '24K Gold-Plated Cufflinks', 'url' => 'shop.php?category=accessories&type=cufflinks'],
                    ['title' => 'Kashmiri Embroidered Stoles', 'url' => 'shop.php?category=accessories&type=stoles'],
                ]
            ]
        ],
        'spotlight' => [
            'tag' => 'SIGNATURE BESPOKE',
            'title' => 'The Imperial Bandhgala',
            'desc' => 'Structured in premium merino wool with antique brass buttons.',
            'image' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=600&q=80',
            'link' => 'shop.php?category=men'
        ]
    ],
    'clothing' => [
        'title' => 'Clothing Collections',
        'columns' => [
            [
                'heading' => 'By Silhouette',
                'links' => [
                    ['title' => 'Couture Evening Gowns', 'url' => 'shop.php?category=clothing&silhouette=gowns'],
                    ['title' => 'Drapes & Saris', 'url' => 'shop.php?category=clothing&silhouette=drapes'],
                    ['title' => 'Structured Outerwear & Capes', 'url' => 'shop.php?category=clothing&silhouette=capes'],
                    ['title' => 'Tailored Separates', 'url' => 'shop.php?category=clothing&silhouette=separates'],
                ]
            ],
            [
                'heading' => 'Artisanal Handlooms',
                'links' => [
                    ['title' => 'Mulberry & Muga Silk', 'url' => 'shop.php?fabric=mulberry-silk'],
                    ['title' => 'Handloom Chanderi Weaves', 'url' => 'shop.php?fabric=chanderi'],
                    ['title' => 'Zari Embroidered Velvet', 'url' => 'shop.php?fabric=velvet'],
                    ['title' => 'Organic Certified Linen', 'url' => 'shop.php?fabric=linen'],
                ]
            ],
            [
                'heading' => 'Seasonal Edits',
                'links' => [
                    ['title' => 'The Autumn Runway Edit', 'url' => 'shop.php?collection=autumn-runway'],
                    ['title' => 'The Heirloom Monogram', 'url' => 'shop.php?collection=monogram'],
                    ['title' => 'Minimalist Everyday Luxe', 'url' => 'shop.php?collection=everyday-luxe'],
                ]
            ]
        ],
        'spotlight' => [
            'tag' => 'HANDCRAFTED HERITAGE',
            'title' => '400 Hours of Zari Craft',
            'desc' => 'Preserving India\'s finest heritage embroidery techniques.',
            'image' => 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=600&q=80',
            'link' => 'shop.php?category=clothing'
        ]
    ],
    'jewellery' => [
        'title' => 'Fine Jewellery & Accents',
        'columns' => [
            [
                'heading' => 'Fine Heirloom Jewels',
                'links' => [
                    ['title' => 'Heritage Polki Chokers', 'url' => 'shop.php?category=jewellery&type=chokers'],
                    ['title' => 'Kundan Statement Necklaces', 'url' => 'shop.php?category=jewellery&type=necklaces'],
                    ['title' => 'Baroque Pearl Pendants', 'url' => 'shop.php?category=jewellery&type=pearls'],
                    ['title' => '24K Gold-Gilded Chandbalis', 'url' => 'shop.php?category=jewellery&type=earrings'],
                ]
            ],
            [
                'heading' => 'Modern Atelier Adornments',
                'links' => [
                    ['title' => 'Sculpted Gold Cuffs', 'url' => 'shop.php?category=jewellery&type=cuffs'],
                    ['title' => 'Precious Stone Rings', 'url' => 'shop.php?category=jewellery&type=rings'],
                    ['title' => 'Handcrafted Hathphool', 'url' => 'shop.php?category=jewellery&type=hathphool'],
                    ['title' => 'Maang Tikkas & Mathapattis', 'url' => 'shop.php?category=jewellery&type=headpieces'],
                ]
            ],
            [
                'heading' => 'Jewellery Services',
                'links' => [
                    ['title' => 'Bespoke Bridal Jewellery Consultation', 'url' => 'contact.php?topic=jewellery'],
                    ['title' => 'Jewel Care & Lifetime Guarantee', 'url' => 'about.php#jewel-care'],
                ]
            ]
        ],
        'spotlight' => [
            'tag' => 'LIMITED ATELIER RUN',
            'title' => 'Heirloom Polki Solitaires',
            'desc' => 'Crafted in 24K gold foil with uncut natural gemstones.',
            'image' => 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=600&q=80',
            'link' => 'shop.php?category=jewellery'
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="canonical" href="<?= e(BASE_URL . ($_SERVER['REQUEST_URI'] ?? '')) ?>">

    <!-- Open Graph Metadata -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:image" content="<?= e($defaultOgImage) ?>">
    <meta property="og:url" content="<?= e(BASE_URL . ($_SERVER['REQUEST_URI'] ?? '')) ?>">

    <!-- CSRF Token for Secure AJAX Requests -->
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">

    <!-- Luxury Haute-Couture Typography (Cormorant Garamond, Montserrat, Cinzel) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700;800;900&family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Master Luxury Stylesheet -->
    <link rel="stylesheet" href="<?= ASSETS_URL ?>/css/style.css">

    <!-- Extra Page-Specific Stylesheets -->
    <?php if (!empty($extraCss) && is_array($extraCss)): ?>
        <?php foreach ($extraCss as $cssPath): ?>
            <?php 
                $cssHref = (strpos($cssPath, 'http') === 0 || strpos($cssPath, '//') === 0) 
                    ? $cssPath 
                    : BASE_URL . '/' . ltrim($cssPath, '/');
            ?>
            <link rel="stylesheet" href="<?= e($cssHref) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body class="<?= (!empty($hideSiteLoader) || ($activePage ?? '') === 'checkout') ? 'page-loaded' : '' ?>">

    <!-- 0. Cinematic Website Loader -->
    <?php if (empty($hideSiteLoader) && ($activePage ?? '') !== 'checkout'): ?>
    <div id="siteLoader" class="site-loader" aria-hidden="true">
        <div class="loader-content">
            <div class="loader-monogram">Y</div>
            <div class="loader-brand-name">YATIYA</div>
            <div class="loader-tagline">HAUTE COUTURE &bull; ATELIER</div>
            <div class="loader-progress-track">
                <div class="loader-progress-bar"></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scroll-Linked Progress Bar -->
    <div id="scrollProgressBar" class="scroll-progress-bar" aria-hidden="true"></div>

    <!-- Luxury Cursor Aura (Desktop Only) -->
    <div id="luxuryCursorAura" class="luxury-cursor-aura" aria-hidden="true"></div>

    <!-- 1. Top Announcement Bar -->
    <aside class="announcement-bar" aria-label="Announcement">
        <a href="<?= e(BASE_URL . '/' . ltrim($announcementLink, '/')) ?>">
            <span>✨ <?= e($announcementText) ?></span>
        </a>
    </aside>

    <!-- 2. Main Luxury Header -->
    <header class="site-header" id="siteHeader">
        <div class="container">
            <div class="header-inner">
                <!-- Mobile Menu Hamburger Button (Hidden on Desktop) -->
                <button type="button" class="mobile-menu-toggle show-mobile-only" id="mobileMenuOpenBtn" aria-label="Open Navigation Menu" aria-expanded="false" aria-controls="mobileDrawer">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <!-- Brand Logo (YATIYA COUTURE) -->
                <div class="brand-logo-container">
                    <a href="<?= BASE_URL ?>/index.php" class="brand-logo" aria-label="Yatiya Couture Home">
                        <span class="brand-name">YATIYA</span>
                        <span class="brand-tagline">COUTURE &bull; ATELIER</span>
                    </a>
                </div>

                <!-- Central Search Bar with Input -->
                <div class="header-search-wrapper">
                    <form action="<?= BASE_URL ?>/shop.php" method="GET" class="header-search-form" role="search" id="headerSearchForm">
                        <input 
                            type="search" 
                            name="q" 
                            id="headerSearchInput"
                            class="header-search-input" 
                            placeholder="Search silks, lehengas, footwear, jewellery..." 
                            value="<?= isset($_GET['q']) ? e($_GET['q']) : '' ?>"
                            autocomplete="off"
                            required
                        >
                        <button type="submit" class="header-search-btn" aria-label="Search Catalog">
                            <svg class="header-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                        </button>
                    </form>
                </div>

                <!-- Action Icons: Account, Wishlist, Cart -->
                <div class="header-actions">
                    <!-- Mobile Search Trigger -->
                    <button type="button" class="header-action-item show-mobile-only" id="mobileSearchToggleBtn" aria-label="Search Products">
                        <svg class="header-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>

                    <!-- Customer Account / Login -->
                    <?php if ($currentUser): ?>
                        <a href="<?= BASE_URL ?>/account.php" class="header-action-item" title="My Account" aria-label="Customer Account">
                            <svg class="header-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span class="hide-mobile user-first-name"><?= e($currentUser['first_name']) ?></span>
                        </a>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php" class="header-action-item" title="Sign In" aria-label="Customer Login">
                            <svg class="header-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <span class="hide-mobile">Sign In</span>
                        </a>
                    <?php endif; ?>

                    <!-- Wishlist with Counter Badge -->
                    <a href="<?= BASE_URL ?>/wishlist.php" class="header-action-item" title="Wishlist" aria-label="Saved Wishlist Items">
                        <svg class="header-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                        </svg>
                        <span class="badge-count" id="headerWishlistCount" style="<?= ($wishlistCount > 0) ? '' : 'display:none;' ?>"><?= $wishlistCount ?></span>
                    </a>

                    <!-- Shopping Cart with Counter Badge -->
                    <a href="<?= BASE_URL ?>/cart.php" class="header-action-item" title="Shopping Bag" aria-label="Shopping Bag">
                        <svg class="header-action-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                        <span class="badge-count" id="headerCartCount"><?= $cartCount ?></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Mobile Expandable Search Bar -->
        <div class="mobile-search-dropdown" id="mobileSearchDropdown" style="display: none;">
            <div class="container">
                <form action="<?= BASE_URL ?>/shop.php" method="GET" class="mobile-search-form" role="search">
                    <input 
                        type="search" 
                        name="q" 
                        class="mobile-search-input" 
                        placeholder="Search silks, lehengas, footwear, jewellery..." 
                        value="<?= isset($_GET['q']) ? e($_GET['q']) : '' ?>"
                        autocomplete="off"
                        required
                    >
                    <button type="submit" class="btn btn-gold btn-sm">Search</button>
                </form>
            </div>
        </div>

        <!-- 3. Desktop Navigation Bar with Mega Menus (Curated Ethnic Haute Couture Navigation) -->
        <nav class="nav-bar hide-mobile" aria-label="Primary Navigation">
            <div class="container">
                <ul class="nav-menu" role="menubar">
                    <!-- Home -->
                    <li class="nav-item" role="none">
                        <a href="<?= BASE_URL ?>/index.php" class="nav-link <?= ($activePage === 'home') ? 'active' : '' ?>" role="menuitem"><span class="nav-label-text">HOME</span></a>
                    </li>

                    <!-- Women's Atelier (Mega Menu) -->
                    <li class="nav-item has-mega-menu" role="none">
                        <a href="<?= BASE_URL ?>/shop.php?category=women" class="nav-link <?= (isset($_GET['category']) && $_GET['category'] === 'women') ? 'active' : '' ?>" role="menuitem" aria-haspopup="true" aria-expanded="false"><span class="nav-label-text">WOMEN'S ATELIER</span><svg class="chevron-down-icon" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg></a>
                        <?php if (isset($megaMenus['women'])): $menu = $megaMenus['women']; ?>
                            <div class="mega-menu" role="menu" aria-label="Women's Atelier Submenu">
                                <div class="mega-menu-inner">
                                    <div class="mega-columns-grid">
                                        <?php foreach ($menu['columns'] as $col): ?>
                                            <div class="mega-column">
                                                <h4 class="mega-column-title"><?= e($col['heading']) ?></h4>
                                                <ul class="mega-link-list">
                                                    <?php foreach ($col['links'] as $item): ?>
                                                        <li>
                                                            <a href="<?= BASE_URL ?>/<?= ltrim(e($item['url']), '/') ?>" role="menuitem">
                                                                <?= e($item['title']) ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (!empty($menu['spotlight'])): $spot = $menu['spotlight']; ?>
                                        <div class="mega-spotlight">
                                            <div class="mega-spotlight-card">
                                                <img src="<?= e($spot['image']) ?>" alt="<?= e($spot['title']) ?>" loading="lazy">
                                                <div class="mega-spotlight-content">
                                                    <span class="spotlight-tag"><?= e($spot['tag']) ?></span>
                                                    <h5 class="spotlight-title"><?= e($spot['title']) ?></h5>
                                                    <p class="spotlight-desc"><?= e($spot['desc']) ?></p>
                                                    <a href="<?= BASE_URL ?>/<?= ltrim(e($spot['link']), '/') ?>" class="spotlight-link">Explore Collection &rarr;</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </li>

                    <!-- Men's Atelier (Mega Menu) -->
                    <li class="nav-item has-mega-menu" role="none">
                        <a href="<?= BASE_URL ?>/shop.php?category=men" class="nav-link <?= (isset($_GET['category']) && $_GET['category'] === 'men') ? 'active' : '' ?>" role="menuitem" aria-haspopup="true" aria-expanded="false"><span class="nav-label-text">MEN'S ATELIER</span><svg class="chevron-down-icon" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg></a>
                        <?php if (isset($megaMenus['men'])): $menu = $megaMenus['men']; ?>
                            <div class="mega-menu" role="menu" aria-label="Men's Atelier Submenu">
                                <div class="mega-menu-inner">
                                    <div class="mega-columns-grid">
                                        <?php foreach ($menu['columns'] as $col): ?>
                                            <div class="mega-column">
                                                <h4 class="mega-column-title"><?= e($col['heading']) ?></h4>
                                                <ul class="mega-link-list">
                                                    <?php foreach ($col['links'] as $item): ?>
                                                        <li>
                                                            <a href="<?= BASE_URL ?>/<?= ltrim(e($item['url']), '/') ?>" role="menuitem">
                                                                <?= e($item['title']) ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (!empty($menu['spotlight'])): $spot = $menu['spotlight']; ?>
                                        <div class="mega-spotlight">
                                            <div class="mega-spotlight-card">
                                                <img src="<?= e($spot['image']) ?>" alt="<?= e($spot['title']) ?>" loading="lazy">
                                                <div class="mega-spotlight-content">
                                                    <span class="spotlight-tag"><?= e($spot['tag']) ?></span>
                                                    <h5 class="spotlight-title"><?= e($spot['title']) ?></h5>
                                                    <p class="spotlight-desc"><?= e($spot['desc']) ?></p>
                                                    <a href="<?= BASE_URL ?>/<?= ltrim(e($spot['link']), '/') ?>" class="spotlight-link">Explore Collection &rarr;</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </li>

                    <!-- Clothing & Silhouettes (Mega Menu) -->
                    <li class="nav-item has-mega-menu" role="none">
                        <a href="<?= BASE_URL ?>/shop.php?category=clothing" class="nav-link <?= (isset($_GET['category']) && $_GET['category'] === 'clothing') ? 'active' : '' ?>" role="menuitem" aria-haspopup="true" aria-expanded="false"><span class="nav-label-text">HAUTE CLOTHING</span><svg class="chevron-down-icon" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg></a>
                        <?php if (isset($megaMenus['clothing'])): $menu = $megaMenus['clothing']; ?>
                            <div class="mega-menu" role="menu" aria-label="Clothing Submenu">
                                <div class="mega-menu-inner">
                                    <div class="mega-columns-grid">
                                        <?php foreach ($menu['columns'] as $col): ?>
                                            <div class="mega-column">
                                                <h4 class="mega-column-title"><?= e($col['heading']) ?></h4>
                                                <ul class="mega-link-list">
                                                    <?php foreach ($col['links'] as $item): ?>
                                                        <li>
                                                            <a href="<?= BASE_URL ?>/<?= ltrim(e($item['url']), '/') ?>" role="menuitem">
                                                                <?= e($item['title']) ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (!empty($menu['spotlight'])): $spot = $menu['spotlight']; ?>
                                        <div class="mega-spotlight">
                                            <div class="mega-spotlight-card">
                                                <img src="<?= e($spot['image']) ?>" alt="<?= e($spot['title']) ?>" loading="lazy">
                                                <div class="mega-spotlight-content">
                                                    <span class="spotlight-tag"><?= e($spot['tag']) ?></span>
                                                    <h5 class="spotlight-title"><?= e($spot['title']) ?></h5>
                                                    <p class="spotlight-desc"><?= e($spot['desc']) ?></p>
                                                    <a href="<?= BASE_URL ?>/<?= ltrim(e($spot['link']), '/') ?>" class="spotlight-link">Explore Collection &rarr;</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </li>

                    <!-- Heirloom Jewellery (Mega Menu) -->
                    <li class="nav-item has-mega-menu" role="none">
                        <a href="<?= BASE_URL ?>/shop.php?category=jewellery" class="nav-link <?= (isset($_GET['category']) && $_GET['category'] === 'jewellery') ? 'active' : '' ?>" role="menuitem" aria-haspopup="true" aria-expanded="false"><span class="nav-label-text">HEIRLOOM JEWELS</span><svg class="chevron-down-icon" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg></a>
                        <?php if (isset($megaMenus['jewellery'])): $menu = $megaMenus['jewellery']; ?>
                            <div class="mega-menu" role="menu" aria-label="Jewellery Submenu">
                                <div class="mega-menu-inner">
                                    <div class="mega-columns-grid">
                                        <?php foreach ($menu['columns'] as $col): ?>
                                            <div class="mega-column">
                                                <h4 class="mega-column-title"><?= e($col['heading']) ?></h4>
                                                <ul class="mega-link-list">
                                                    <?php foreach ($col['links'] as $item): ?>
                                                        <li>
                                                            <a href="<?= BASE_URL ?>/<?= ltrim(e($item['url']), '/') ?>" role="menuitem">
                                                                <?= e($item['title']) ?>
                                                            </a>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if (!empty($menu['spotlight'])): $spot = $menu['spotlight']; ?>
                                        <div class="mega-spotlight">
                                            <div class="mega-spotlight-card">
                                                <img src="<?= e($spot['image']) ?>" alt="<?= e($spot['title']) ?>" loading="lazy">
                                                <div class="mega-spotlight-content">
                                                    <span class="spotlight-tag"><?= e($spot['tag']) ?></span>
                                                    <h5 class="spotlight-title"><?= e($spot['title']) ?></h5>
                                                    <p class="spotlight-desc"><?= e($spot['desc']) ?></p>
                                                    <a href="<?= BASE_URL ?>/<?= ltrim(e($spot['link']), '/') ?>" class="spotlight-link">Explore Collection &rarr;</a>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </li>

                    <!-- Trending Co-ord Sets -->
                    <li class="nav-item" role="none">
                        <a href="<?= BASE_URL ?>/shop.php?category=women-blazers" class="nav-link" role="menuitem"><span class="nav-label-text">CO-ORDS &amp; SETS</span></a>
                    </li>

                    <!-- Summer Midi -->
                    <li class="nav-item" role="none">
                        <a href="<?= BASE_URL ?>/shop.php?search=midi" class="nav-link" role="menuitem"><span class="nav-label-text">SUMMER MIDI</span></a>
                    </li>

                    <!-- All Collections -->
                    <li class="nav-item" role="none">
                        <a href="<?= BASE_URL ?>/shop.php" class="nav-link <?= ($activePage === 'shop' && empty($_GET['category'])) ? 'active' : '' ?>" role="menuitem"><span class="nav-label-text">ALL COLLECTIONS</span></a>
                    </li>
                </ul>
            </div>
        </nav>

        <!-- Secondary Luxury Category Navigation Strip (Continuous Fashion Marquee) -->
        <nav class="luxury-taxonomy-bar" id="luxuryTaxonomyBar" aria-label="Curated Atelier Categories">
            <div class="taxonomy-bar-track" id="taxonomyBarTrack">
                <div class="taxonomy-bar-inner" id="taxonomyBarInner">
                    <a href="<?= BASE_URL ?>/shop.php?category=jewellery&type=polki-kundan" class="taxonomy-link">Heirloom Polki &amp; Kundan</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=men&type=bandhgalas" class="taxonomy-link">Imperial Bandhgalas</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=accessories&type=bags" class="taxonomy-link">Minaudi&egrave;res &amp; Evening Bags</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=women" class="taxonomy-link">Women</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=women&type=saris" class="taxonomy-link">Handwoven Saris &amp; Drapes</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=men&type=sherwanis" class="taxonomy-link">Kurta Sets &amp; Sherwanis</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=men" class="taxonomy-link">Men</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=women&type=gowns" class="taxonomy-link">Gowns &amp; Anarkalis</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=kids" class="taxonomy-link">Kids</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=men&type=shirts" class="taxonomy-link">Luxury Linen &amp; Shirts</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=clothing" class="taxonomy-link">Clothing</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=women&type=blazers" class="taxonomy-link">Tailored Blazers &amp; Sets</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=footwear" class="taxonomy-link">Footwear</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                    <a href="<?= BASE_URL ?>/shop.php?category=accessories&type=bags" class="taxonomy-link">Bags</a>
                    <span class="taxonomy-divider" aria-hidden="true">&#10022;</span>
                </div>
            </div>
        </nav>
    </header>

    <!-- 4. Flash Notifications Container -->
    <?= render_flash() ?>

    <main id="mainContent">
