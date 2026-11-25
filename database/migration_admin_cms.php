<?php
/**
 * YATIYA COUTURE - Dynamic Admin CMS Database Migration
 * Additive migration strictly preserving existing orders, customers, and catalogue.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

echo "========================================================\n";
echo "   YATIYA COUTURE - ADMIN CMS DATABASE MIGRATION\n";
echo "========================================================\n\n";

// 1. Create homepage_sections table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `homepage_sections` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `section_key` VARCHAR(64) NOT NULL,
        `title` VARCHAR(150) NOT NULL,
        `section_type` ENUM(
            'hero_slider',
            'category_grid',
            'product_collection',
            'editorial_banner',
            'brand_story',
            'testimonials',
            'newsletter',
            'taxonomy_bar',
            'style_stories',
            'custom'
        ) NOT NULL,
        `eyebrow` VARCHAR(150) NULL,
        `subtitle` VARCHAR(255) NULL,
        `description` TEXT NULL,
        `cta_text` VARCHAR(100) NULL,
        `cta_url` VARCHAR(255) NULL,
        `background_style` VARCHAR(50) NOT NULL DEFAULT 'default',
        `layout_style` VARCHAR(50) NOT NULL DEFAULT 'standard',
        `display_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `publish_status` ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'published',
        `selection_mode` ENUM('automatic', 'manual') NOT NULL DEFAULT 'automatic',
        `config_data` LONGTEXT NULL,
        `published_at` DATETIME NULL,
        `created_by` INT UNSIGNED NULL,
        `updated_by` INT UNSIGNED NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_section_key` (`section_key`),
        INDEX `idx_hs_display` (`display_order`, `is_active`),
        INDEX `idx_hs_status` (`publish_status`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[+] Ensured `homepage_sections` table exists.\n";

// 2. Create homepage_section_items table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `homepage_section_items` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `section_id` INT UNSIGNED NOT NULL,
        `item_type` ENUM('product', 'category', 'banner', 'story', 'custom') NOT NULL,
        `item_id` INT UNSIGNED NULL,
        `title` VARCHAR(255) NULL,
        `subtitle` VARCHAR(255) NULL,
        `image_url` VARCHAR(500) NULL,
        `link_url` VARCHAR(255) NULL,
        `badge_text` VARCHAR(100) NULL,
        `display_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `custom_data` TEXT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_hsi_section` (`section_id`, `display_order`),
        INDEX `idx_hsi_item` (`item_type`, `item_id`),
        CONSTRAINT `fk_hsi_section` FOREIGN KEY (`section_id`) 
            REFERENCES `homepage_sections` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[+] Ensured `homepage_section_items` table exists.\n";

// 3. Create collections table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `collections` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(150) NOT NULL,
        `slug` VARCHAR(160) NOT NULL,
        `tagline` VARCHAR(255) NULL,
        `description` TEXT NULL,
        `cover_image` VARCHAR(500) NULL,
        `banner_image` VARCHAR(500) NULL,
        `seo_title` VARCHAR(255) NULL,
        `seo_description` TEXT NULL,
        `display_order` INT NOT NULL DEFAULT 0,
        `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_collection_slug` (`slug`),
        INDEX `idx_col_active_order` (`is_active`, `display_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[+] Ensured `collections` table exists.\n";

// 4. Create collection_products table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `collection_products` (
        `collection_id` INT UNSIGNED NOT NULL,
        `product_id` INT UNSIGNED NOT NULL,
        `display_order` INT NOT NULL DEFAULT 0,
        PRIMARY KEY (`collection_id`, `product_id`),
        INDEX `idx_cp_product` (`product_id`),
        CONSTRAINT `fk_cp_collection` FOREIGN KEY (`collection_id`) 
            REFERENCES `collections` (`id`) ON DELETE CASCADE,
        CONSTRAINT `fk_cp_product` FOREIGN KEY (`product_id`) 
            REFERENCES `products` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[+] Ensured `collection_products` table exists.\n";

// 5. Create navigation_menus table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `navigation_menus` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `parent_id` INT UNSIGNED NULL,
        `label` VARCHAR(100) NOT NULL,
        `url` VARCHAR(255) NOT NULL,
        `menu_type` ENUM('header', 'mega_menu', 'footer_1', 'footer_2', 'footer_3', 'mobile') NOT NULL DEFAULT 'header',
        `mega_category_key` VARCHAR(50) NULL,
        `badge_text` VARCHAR(50) NULL,
        `promo_title` VARCHAR(150) NULL,
        `promo_image` VARCHAR(500) NULL,
        `promo_link` VARCHAR(255) NULL,
        `display_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `target` VARCHAR(20) NOT NULL DEFAULT '_self',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_nav_type_parent` (`menu_type`, `parent_id`, `display_order`),
        INDEX `idx_nav_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[+] Ensured `navigation_menus` table exists.\n";

// 6. Create media_library table
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `media_library` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `file_name` VARCHAR(255) NOT NULL,
        `original_name` VARCHAR(255) NOT NULL,
        `file_path` VARCHAR(500) NOT NULL,
        `file_url` VARCHAR(500) NOT NULL,
        `mime_type` VARCHAR(100) NOT NULL,
        `file_size` INT UNSIGNED NOT NULL,
        `width` INT UNSIGNED NULL,
        `height` INT UNSIGNED NULL,
        `alt_text` VARCHAR(255) NULL,
        `caption` VARCHAR(255) NULL,
        `folder` VARCHAR(50) NOT NULL DEFAULT 'general',
        `uploaded_by` INT UNSIGNED NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_media_folder` (`folder`),
        INDEX `idx_media_created` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[+] Ensured `media_library` table exists.\n";

// 7. Add columns to categories if missing
$catCols = $pdo->query("SHOW COLUMNS FROM categories LIKE 'seo_title'")->fetchAll();
if (empty($catCols)) {
    $pdo->exec("ALTER TABLE categories ADD COLUMN seo_title VARCHAR(255) NULL, ADD COLUMN seo_description TEXT NULL");
    echo "[+] Added SEO columns to `categories`.\n";
} else {
    echo "[.] SEO columns on `categories` already exist.\n";
}

// 8. Add columns to products if missing
$prodCols = $pdo->query("SHOW COLUMNS FROM products LIKE 'seo_title'")->fetchAll();
if (empty($prodCols)) {
    $pdo->exec("ALTER TABLE products ADD COLUMN seo_title VARCHAR(255) NULL, ADD COLUMN seo_description TEXT NULL, ADD COLUMN gender VARCHAR(50) NULL");
    echo "[+] Added SEO and gender columns to `products`.\n";
} else {
    echo "[.] SEO columns on `products` already exist.\n";
}

// 9. Seed default homepage sections matching the 12 existing sections of index.php
$secCount = (int)$pdo->query("SELECT COUNT(*) FROM homepage_sections")->fetchColumn();
if ($secCount === 0) {
    $sections = [
        [
            'section_key' => 'circular_stories',
            'title' => 'Circular Category Stories',
            'section_type' => 'taxonomy_bar',
            'eyebrow' => 'Explore Categories',
            'subtitle' => 'Curated salon departments and story highlights',
            'description' => 'Horizontal interactive story circles for immediate mobile and desktop category access.',
            'cta_text' => null,
            'cta_url' => null,
            'background_style' => 'default',
            'layout_style' => 'carousel',
            'display_order' => 1,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode(['table' => 'homepage_stories', 'limit' => 12])
        ],
        [
            'section_key' => 'hero_slider',
            'title' => 'Hero Banner Slider',
            'section_type' => 'hero_slider',
            'eyebrow' => 'Haute Couture Campaign',
            'subtitle' => 'The pinnacle of luxury fashion, crafted with pure handwoven silks.',
            'description' => 'Full-bleed luxury runway banner slider with call-to-actions and lookbook links.',
            'cta_text' => 'Explore Collections',
            'cta_url' => 'shop.php',
            'background_style' => 'default',
            'layout_style' => 'slider',
            'display_order' => 2,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode(['position' => 'hero', 'overlay_opacity' => 0.45])
        ],
        [
            'section_key' => 'shop_by_category',
            'title' => 'Shop by Category',
            'section_type' => 'category_grid',
            'eyebrow' => 'The Atelier Taxonomy',
            'subtitle' => 'Shop by Category',
            'description' => 'Exemplary craftsmanship spanning tailored menswear, couture silhouettes, fine leather and heirloom accessories.',
            'cta_text' => 'Explore All Categories',
            'cta_url' => 'shop.php',
            'background_style' => 'default',
            'layout_style' => 'grid_6',
            'display_order' => 3,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode(['categories' => ['women', 'men', 'kids', 'footwear', 'bags', 'accessories']])
        ],
        [
            'section_key' => 'new_arrivals',
            'title' => 'New Arrivals Runway',
            'section_type' => 'product_collection',
            'eyebrow' => 'Autumn / Winter 2026',
            'subtitle' => 'New Arrivals',
            'description' => 'Fresh off the runway: handcrafted drapes, raw silk separates and tailored suits.',
            'cta_text' => 'View All New Arrivals',
            'cta_url' => 'shop.php?category=new-arrivals',
            'background_style' => 'secondary',
            'layout_style' => 'grid_4',
            'display_order' => 4,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode(['filter_col' => 'is_new', 'limit' => 8, 'sort' => 'created_at_desc'])
        ],
        [
            'section_key' => 'trending_now',
            'title' => 'Trending Co-ord Sets',
            'section_type' => 'product_collection',
            'eyebrow' => '✨ Runway & Modern Ensembles',
            'subtitle' => 'Trending Co-ord Sets',
            'description' => 'Handcrafted monochrome separates, raw silk pairings, and tailored co-ords curated for timeless style.',
            'cta_text' => 'Explore All Co-ords',
            'cta_url' => 'shop.php?category=women-blazers',
            'background_style' => 'default',
            'layout_style' => 'grid_4',
            'display_order' => 5,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode(['filter_col' => 'is_trending_coord', 'fallback_col' => 'is_featured', 'limit' => 8])
        ],
        [
            'section_key' => 'best_sellers',
            'title' => 'House Signature Best Sellers',
            'section_type' => 'product_collection',
            'eyebrow' => 'House Signatures',
            'subtitle' => 'Best Sellers',
            'description' => 'Enduring classics and globally acclaimed pieces that define the Yatiya aesthetic.',
            'cta_text' => 'View All Best Sellers',
            'cta_url' => 'shop.php?category=best-sellers',
            'background_style' => 'secondary',
            'layout_style' => 'grid_4',
            'display_order' => 6,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode(['filter_col' => 'is_bestseller', 'limit' => 8])
        ],
        [
            'section_key' => 'summer_midi',
            'title' => 'Summer Midi Collection',
            'section_type' => 'product_collection',
            'eyebrow' => '☀️ Sunlit Resort & Daytime Glamour',
            'subtitle' => 'Summer Midi',
            'description' => 'Breezy handwoven silhouettes, tiered chanderi slips, and daytime midi creations designed for effortless elegance.',
            'cta_text' => 'View Summer Midi Edit',
            'cta_url' => 'shop.php?search=midi',
            'background_style' => 'default',
            'layout_style' => 'grid_4',
            'display_order' => 7,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode(['filter_col' => 'is_summer_midi', 'limit' => 8])
        ],
        [
            'section_key' => 'exclusive_collection',
            'title' => 'Exclusive Collection Banner',
            'section_type' => 'editorial_banner',
            'eyebrow' => 'HAUTE COUTURE',
            'subtitle' => 'Bespoke Bridal & Red-Carpet Privé',
            'description' => 'Tailored to perfection by master artisans with over 400 hours of hand embroidery.',
            'cta_text' => 'Book Atelier Appointment',
            'cta_url' => 'contact.php',
            'background_style' => 'dark',
            'layout_style' => 'full_banner',
            'display_order' => 8,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode([
                'image_url' => 'https://images.unsplash.com/photo-1509631179647-0177331693ae?auto=format&fit=crop&w=1600&q=80',
                'secondary_cta_text' => 'Explore Craftsmanship',
                'secondary_cta_url' => 'about.php#craftsmanship'
            ])
        ],
        [
            'section_key' => 'style_stories',
            'title' => 'Style Stories & Inspirations',
            'section_type' => 'style_stories',
            'eyebrow' => 'The Atelier Journal',
            'subtitle' => 'Style Stories & Inspirations',
            'description' => 'Styling masterclasses, heritage craft perspectives, and seasonal wardrobe curation from our creative salon.',
            'cta_text' => 'Read Full Journal',
            'cta_url' => 'shop.php',
            'background_style' => 'secondary',
            'layout_style' => 'grid_3',
            'display_order' => 9,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode(['table' => 'style_stories', 'limit' => 3])
        ],
        [
            'section_key' => 'why_yatiya',
            'title' => 'Why Yatiya Couture Pillars',
            'section_type' => 'brand_story',
            'eyebrow' => 'The Atelier Standards',
            'subtitle' => 'Why Yatiya Couture',
            'description' => 'Upholding uncompromising excellence across every stitch, thread, and client interaction.',
            'cta_text' => 'Our Heritage',
            'cta_url' => 'about.php',
            'background_style' => 'default',
            'layout_style' => 'pillars_4',
            'display_order' => 10,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode([
                'pillars' => [
                    ['icon' => '✦', 'title' => 'Premium Quality', 'desc' => 'Woven from 100% pure Mulberry, Banarasi, and Chanderi silks with genuine 24K gold zari threads, crafted to endure across generations.'],
                    ['icon' => '❖', 'title' => 'Curated Collections', 'desc' => 'Limited edition runs and bespoke couture capsules engineered to ensure rarity, individuality, and timeless sartorial distinction.'],
                    ['icon' => '⚜', 'title' => 'Secure Shopping', 'desc' => 'Bank-grade 256-bit encryption, strict server-side transaction signing, transparent tracking, and reliable Cash on Delivery verification.'],
                    ['icon' => '◈', 'title' => 'Customer-first Experience', 'desc' => 'Complimentary insured white-glove delivery, dedicated personal atelier stylists, and effortless exchange protocols.']
                ]
            ])
        ],
        [
            'section_key' => 'customer_reviews',
            'title' => 'Customer Reviews & Patron Promise',
            'section_type' => 'testimonials',
            'eyebrow' => 'Patron Accolades',
            'subtitle' => 'Client Reflections',
            'description' => 'Authentic reviews from verified patrons who have experienced the Yatiya atelier.',
            'cta_text' => 'Write a Reflection',
            'cta_url' => 'account.php',
            'background_style' => 'default',
            'layout_style' => 'grid_3',
            'display_order' => 11,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode(['limit' => 3, 'fallback_mode' => 'brand_promise'])
        ],
        [
            'section_key' => 'newsletter',
            'title' => 'Privileged Newsletter Subscription',
            'section_type' => 'newsletter',
            'eyebrow' => 'Privileged Access',
            'subtitle' => 'Join The Yatiya Couture',
            'description' => 'Receive private atelier invitations, seasonal lookbook previews, and privileged early access to limited edition couture releases.',
            'cta_text' => 'Subscribe',
            'cta_url' => 'actions/newsletter_subscribe.php',
            'background_style' => 'dark_luxury',
            'layout_style' => 'centered',
            'display_order' => 12,
            'is_active' => 1,
            'publish_status' => 'published',
            'selection_mode' => 'automatic',
            'config_data' => json_encode(['sparkle_icon' => '✨', 'placeholder' => 'Enter your privileged email address'])
        ]
    ];

    $insStmt = $pdo->prepare("
        INSERT INTO homepage_sections (
            section_key, title, section_type, eyebrow, subtitle, description,
            cta_text, cta_url, background_style, layout_style, display_order,
            is_active, publish_status, selection_mode, config_data, published_at
        ) VALUES (
            :section_key, :title, :section_type, :eyebrow, :subtitle, :description,
            :cta_text, :cta_url, :background_style, :layout_style, :display_order,
            :is_active, :publish_status, :selection_mode, :config_data, NOW()
        )
    ");

    foreach ($sections as $s) {
        $insStmt->execute($s);
    }
    echo "[+] Seeded all 12 default homepage sections.\n";
} else {
    echo "[.] `homepage_sections` already populated with $secCount sections.\n";
}

// 10. Seed initial collections if empty
$colCount = (int)$pdo->query("SELECT COUNT(*) FROM collections")->fetchColumn();
if ($colCount === 0) {
    $collections = [
        [
            'name' => 'The Royal Wedding Salon',
            'slug' => 'wedding-salon',
            'tagline' => 'Bridal & Groom Imperial Ensembles',
            'description' => 'Handloom brocades, raw silk kurtas, and handcrafted minaudières for festive evenings.',
            'cover_image' => 'https://images.unsplash.com/photo-1509631179647-0177331693ae?auto=format&fit=crop&w=1200&q=80',
            'banner_image' => 'https://images.unsplash.com/photo-1509631179647-0177331693ae?auto=format&fit=crop&w=1600&q=80',
            'display_order' => 1,
            'is_featured' => 1,
            'is_active' => 1
        ],
        [
            'name' => 'Festive Cocktail Soirée',
            'slug' => 'cocktail-soiree',
            'tagline' => 'Contemporary Silk Evening Silhouettes',
            'description' => 'Silk chiffon gowns, structured capes, and metallic tissue separates designed for twilight affairs.',
            'cover_image' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1200&q=80',
            'banner_image' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=1600&q=80',
            'display_order' => 2,
            'is_featured' => 1,
            'is_active' => 1
        ],
        [
            'name' => 'Winter Silk & Velvet Edit',
            'slug' => 'winter-velvet',
            'tagline' => 'Imperial Velvets & Cashmere Weaves',
            'description' => 'Heavy velvet sherwanis, embroidered bandhgalas, and warm pashmina stoles.',
            'cover_image' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=1200&q=80',
            'banner_image' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?auto=format&fit=crop&w=1600&q=80',
            'display_order' => 3,
            'is_featured' => 1,
            'is_active' => 1
        ],
        [
            'name' => 'Artisanal Handloom Archive',
            'slug' => 'handloom-archive',
            'tagline' => 'Rare Weaves from Banaras and Kanchipuram',
            'description' => 'Generational heirlooms woven by master craftsmen with authentic 24K real gold zari.',
            'cover_image' => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=1200&q=80',
            'banner_image' => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=1600&q=80',
            'display_order' => 4,
            'is_featured' => 1,
            'is_active' => 1
        ]
    ];

    $colStmt = $pdo->prepare("
        INSERT INTO collections (name, slug, tagline, description, cover_image, banner_image, display_order, is_featured, is_active)
        VALUES (:name, :slug, :tagline, :description, :cover_image, :banner_image, :display_order, :is_featured, :is_active)
    ");
    foreach ($collections as $c) {
        $colStmt->execute($c);
    }
    echo "[+] Seeded 4 luxury collections.\n";

    // Associate initial products with collections
    $allProductIds = $pdo->query("SELECT id FROM products ORDER BY id ASC LIMIT 12")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($allProductIds)) {
        $cpStmt = $pdo->prepare("INSERT IGNORE INTO collection_products (collection_id, product_id, display_order) VALUES (?, ?, ?)");
        $collectionsInDb = $pdo->query("SELECT id FROM collections ORDER BY id ASC")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($collectionsInDb as $cId) {
            $slice = array_slice($allProductIds, 0, 4);
            foreach ($slice as $idx => $pId) {
                $cpStmt->execute([$cId, $pId, $idx + 1]);
            }
        }
        echo "[+] Seeded initial collection-product relations.\n";
    }
} else {
    echo "[.] `collections` already populated with $colCount records.\n";
}

// 11. Seed navigation_menus matching header.php
$navCount = (int)$pdo->query("SELECT COUNT(*) FROM navigation_menus")->fetchColumn();
if ($navCount === 0) {
    $navItems = [
        ['label' => 'New Arrivals', 'url' => 'shop.php?category=new-arrivals', 'menu_type' => 'header', 'display_order' => 1],
        ['label' => 'Women', 'url' => 'shop.php?category=women', 'menu_type' => 'header', 'mega_category_key' => 'women', 'display_order' => 2],
        ['label' => 'Men', 'url' => 'shop.php?category=men', 'menu_type' => 'header', 'mega_category_key' => 'men', 'display_order' => 3],
        ['label' => 'Clothing', 'url' => 'shop.php?category=clothing', 'menu_type' => 'header', 'mega_category_key' => 'clothing', 'display_order' => 4],
        ['label' => 'Collections', 'url' => 'shop.php?collection=wedding-salon', 'menu_type' => 'header', 'mega_category_key' => 'collections', 'display_order' => 5],
        ['label' => 'Accessories', 'url' => 'shop.php?category=accessories', 'menu_type' => 'header', 'mega_category_key' => 'accessories', 'display_order' => 6],
        ['label' => 'The Atelier', 'url' => 'about.php', 'menu_type' => 'header', 'display_order' => 7],
    ];

    $navStmt = $pdo->prepare("
        INSERT INTO navigation_menus (label, url, menu_type, mega_category_key, display_order, is_active)
        VALUES (:label, :url, :menu_type, :mega_category_key, :display_order, 1)
    ");
    foreach ($navItems as $item) {
        $navStmt->execute([
            ':label' => $item['label'],
            ':url' => $item['url'],
            ':menu_type' => $item['menu_type'],
            ':mega_category_key' => $item['mega_category_key'] ?? null,
            ':display_order' => $item['display_order']
        ]);
    }
    echo "[+] Seeded primary navigation menus.\n";
} else {
    echo "[.] `navigation_menus` already populated with $navCount items.\n";
}

// 12. Seed initial media_library entries from existing banners & stories
$mediaCount = (int)$pdo->query("SELECT COUNT(*) FROM media_library")->fetchColumn();
if ($mediaCount === 0) {
    $bannerImages = $pdo->query("SELECT DISTINCT image_url, title FROM banners WHERE image_url IS NOT NULL AND image_url != ''")->fetchAll();
    $medStmt = $pdo->prepare("
        INSERT INTO media_library (file_name, original_name, file_path, file_url, mime_type, file_size, alt_text, folder)
        VALUES (:file_name, :original_name, :file_path, :file_url, :mime_type, :file_size, :alt_text, :folder)
    ");
    foreach ($bannerImages as $b) {
        $url = $b['image_url'];
        $baseName = basename(parse_url($url, PHP_URL_PATH) ?? 'image.jpg');
        $medStmt->execute([
            ':file_name' => $baseName,
            ':original_name' => $baseName,
            ':file_path' => $url,
            ':file_url' => $url,
            ':mime_type' => 'image/jpeg',
            ':file_size' => 150000,
            ':alt_text' => $b['title'],
            ':folder' => 'banners'
        ]);
    }
    echo "[+] Seeded media library with initial banner images.\n";
} else {
    echo "[.] `media_library` already has $mediaCount entries.\n";
}

echo "\n>>> ADMIN CMS DATABASE MIGRATION COMPLETED SUCCESSFULLY <<<\n";

