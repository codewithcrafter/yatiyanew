<?php
/**
 * Migration: Homepage Sections & Circular Stories Enhancement
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';

$pdo = db();

echo "Running Migration for Homepage Sections & Stories...\n";

// 1. Add is_summer_midi and is_trending_coord columns to products table if they don't exist
$cols = $pdo->query("SHOW COLUMNS FROM products LIKE 'is_summer_midi'")->fetchAll();
if (empty($cols)) {
    $pdo->exec("ALTER TABLE products ADD COLUMN is_summer_midi TINYINT(1) NOT NULL DEFAULT 0 AFTER is_bestseller");
    echo "[+] Added column `is_summer_midi` to `products`.\n";
} else {
    echo "[.] Column `is_summer_midi` already exists.\n";
}

$cols2 = $pdo->query("SHOW COLUMNS FROM products LIKE 'is_trending_coord'")->fetchAll();
if (empty($cols2)) {
    $pdo->exec("ALTER TABLE products ADD COLUMN is_trending_coord TINYINT(1) NOT NULL DEFAULT 0 AFTER is_summer_midi");
    echo "[+] Added column `is_trending_coord` to `products`.\n";
} else {
    echo "[.] Column `is_trending_coord` already exists.\n";
}

// 2. Create homepage_stories table (Circular Category Stories from user image)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `homepage_stories` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(100) NOT NULL,
        `image_url` VARCHAR(255) NOT NULL,
        `link_url` VARCHAR(255) NOT NULL,
        `display_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_stories_active` (`is_active`, `display_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[+] Ensured table `homepage_stories` exists.\n";

// 3. Create style_stories table (Style Stories & Inspirations editorial cards)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS `style_stories` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `title` VARCHAR(255) NOT NULL,
        `tag` VARCHAR(100) NULL DEFAULT 'STYLE EDITORIAL',
        `excerpt` TEXT NOT NULL,
        `image_url` VARCHAR(255) NOT NULL,
        `link_url` VARCHAR(255) NOT NULL DEFAULT '/shop.php',
        `read_time` VARCHAR(50) NOT NULL DEFAULT '4 min read',
        `display_order` INT NOT NULL DEFAULT 0,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        INDEX `idx_style_stories_active` (`is_active`, `display_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo "[+] Ensured table `style_stories` exists.\n";

// 4. Seed homepage_stories with the exact 8 categories from the user's reference image
$existingStoriesCount = (int)$pdo->query("SELECT COUNT(*) FROM homepage_stories")->fetchColumn();
if ($existingStoriesCount === 0) {
    $stories = [
        [
            'title' => 'Dresses',
            'image_url' => 'https://images.unsplash.com/photo-1595777457583-95e059d581b8?auto=format&fit=crop&w=600&q=80',
            'link_url' => '/shop.php?search=dress',
            'display_order' => 1
        ],
        [
            'title' => 'Ethnic Coords',
            'image_url' => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=600&q=80',
            'link_url' => '/shop.php?category=women-blazers',
            'display_order' => 2
        ],
        [
            'title' => 'Saree',
            'image_url' => 'https://images.unsplash.com/photo-1617627143750-d86bc21e42bb?auto=format&fit=crop&w=600&q=80',
            'link_url' => '/shop.php?category=women-saris',
            'display_order' => 3
        ],
        [
            'title' => 'Suits',
            'image_url' => 'https://images.unsplash.com/photo-1583391733956-3750e0ff4e8b?auto=format&fit=crop&w=600&q=80',
            'link_url' => '/shop.php?category=women-gowns',
            'display_order' => 4
        ],
        [
            'title' => 'Maxi',
            'image_url' => 'https://images.unsplash.com/photo-1566174053879-31528523f8ae?auto=format&fit=crop&w=600&q=80',
            'link_url' => '/shop.php?search=maxi',
            'display_order' => 5
        ],
        [
            'title' => 'Midi',
            'image_url' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=600&q=80',
            'link_url' => '/shop.php?search=midi',
            'display_order' => 6
        ],
        [
            'title' => 'Jumpsuit',
            'image_url' => 'https://images.unsplash.com/photo-1509631179647-0177331693ae?auto=format&fit=crop&w=600&q=80',
            'link_url' => '/shop.php?search=jumpsuit',
            'display_order' => 7
        ],
        [
            'title' => 'Kurti',
            'image_url' => 'https://images.unsplash.com/photo-1617137984095-74e4e5e3613f?auto=format&fit=crop&w=600&q=80',
            'link_url' => '/shop.php?category=men-kurtas',
            'display_order' => 8
        ]
    ];

    $storyInsert = $pdo->prepare("
        INSERT INTO homepage_stories (title, image_url, link_url, display_order, is_active)
        VALUES (?, ?, ?, ?, 1)
    ");
    foreach ($stories as $s) {
        $storyInsert->execute([$s['title'], $s['image_url'], $s['link_url'], $s['display_order']]);
    }
    echo "[+] Seeded 8 circular category stories from user reference.\n";
} else {
    echo "[.] Circular stories already exist ({$existingStoriesCount} items).\n";
}

// 5. Seed style_stories if empty
$existingStyleCount = (int)$pdo->query("SELECT COUNT(*) FROM style_stories")->fetchColumn();
if ($existingStyleCount === 0) {
    $styleData = [
        [
            'title' => 'The Art of Modern Draping: Banarasi Silks Reimagined',
            'tag' => 'EDITORIAL GUIDE',
            'excerpt' => 'Discover how heirloom handloom weaves translate into effortless evening silhouettes, styled with contemporary gold jewellery and tailored structure.',
            'image_url' => 'https://images.unsplash.com/photo-1610030469983-98e550d6193c?auto=format&fit=crop&w=800&q=80',
            'link_url' => '/shop.php?category=women-saris',
            'read_time' => '4 min read',
            'display_order' => 1
        ],
        [
            'title' => 'Summer Midi Magic: Breezy Silhouettes for Sun-Drenched Soirées',
            'tag' => 'SEASONAL LOOKBOOK',
            'excerpt' => 'From artisanal block-prints to hand-spun chanderi, explore the midi dresses captivating luxury resort-goers this season.',
            'image_url' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=800&q=80',
            'link_url' => '/shop.php?category=women-gowns',
            'read_time' => '3 min read',
            'display_order' => 2
        ],
        [
            'title' => 'The Power of Co-ords: Effortless Monochromes from Atelier to Evening',
            'tag' => 'STYLE FORECAST',
            'excerpt' => 'Structured raw silk co-ords and tailored separates that redefine Indian festive elegance with sharp global tailoring.',
            'image_url' => 'https://images.unsplash.com/photo-1548624149-f7b3eb404a60?auto=format&fit=crop&w=800&q=80',
            'link_url' => '/shop.php?category=women-blazers',
            'read_time' => '5 min read',
            'display_order' => 3
        ]
    ];

    $styleInsert = $pdo->prepare("
        INSERT INTO style_stories (title, tag, excerpt, image_url, link_url, read_time, display_order, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, 1)
    ");
    foreach ($styleData as $st) {
        $styleInsert->execute([$st['title'], $st['tag'], $st['excerpt'], $st['image_url'], $st['link_url'], $st['read_time'], $st['display_order']]);
    }
    echo "[+] Seeded 3 Style Stories & Inspirations articles.\n";
} else {
    echo "[.] Style stories already exist ({$existingStyleCount} items).\n";
}

// 6. Assign sample products to is_summer_midi and is_trending_coord
// For summer midi: products 3, 5, 18
$pdo->exec("UPDATE products SET is_summer_midi = 1 WHERE id IN (3, 5, 18)");
// For trending co-ord: products 4, 8, 9
$pdo->exec("UPDATE products SET is_trending_coord = 1 WHERE id IN (4, 8, 9)");
echo "[+] Initialized sample product assignments for Summer Midi and Trending Co-ord Sets.\n";

echo "Migration finished successfully!\n";

