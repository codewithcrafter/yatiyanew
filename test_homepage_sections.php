<?php
/**
 * Yatiya Couture - Homepage Sections & Admin Management Verification Test
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/admin-functions.php';

echo "========================================================\n";
echo "   YATIYA COUTURE - HOMEPAGE SECTIONS & ADMIN SUITE\n";
echo "========================================================\n\n";

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] " . $name . "\n";
        $passed++;
    } else {
        echo "[FAIL] " . $name . ($detail ? " - " . $detail : "") . "\n";
        $failed++;
    }
}

// 1. Check Homepage Stories Table & Seeding
$stories = admin_get_homepage_stories();
assert_test("Homepage Stories seeded", count($stories) >= 8, "Found " . count($stories));
$titles = array_column($stories, 'title');
assert_test("Contains all 8 items from reference image",
    in_array('Dresses', $titles) &&
    in_array('Ethnic Coords', $titles) &&
    in_array('Saree', $titles) &&
    in_array('Suits', $titles) &&
    in_array('Maxi', $titles) &&
    in_array('Midi', $titles) &&
    in_array('Jumpsuit', $titles) &&
    in_array('Kurti', $titles)
);

// 2. Check Style Stories Table & Seeding
$styleStories = admin_get_style_stories();
assert_test("Style Stories seeded", count($styleStories) >= 3, "Found " . count($styleStories));

// 3. Test Flag Toggling for Sections
$testProductId = 1;
$res1 = admin_set_product_section_flag($testProductId, 'is_summer_midi', 1);
assert_test("Set is_summer_midi to 1", $res1);
$summerList = admin_get_section_products('is_summer_midi');
$summerIds = array_column($summerList, 'id');
assert_test("Product in Summer Midi list", in_array($testProductId, $summerIds));

$res2 = admin_set_product_section_flag($testProductId, 'is_summer_midi', 0);
assert_test("Set is_summer_midi to 0", $res2);
$summerListAfter = admin_get_section_products('is_summer_midi');
$summerIdsAfter = array_column($summerListAfter, 'id');
assert_test("Product removed from Summer Midi list", !in_array($testProductId, $summerIdsAfter));

// 4. Test Trending Co-ord Flag Toggling
$res3 = admin_set_product_section_flag($testProductId, 'is_trending_coord', 1);
assert_test("Set is_trending_coord to 1", $res3);
$coordList = admin_get_section_products('is_trending_coord');
$coordIds = array_column($coordList, 'id');
assert_test("Product in Trending Coords list", in_array($testProductId, $coordIds));

$res4 = admin_set_product_section_flag($testProductId, 'is_trending_coord', 0);
assert_test("Set is_trending_coord to 0", $res4);

// 5. Test Homepage Story CRUD
$saveStoryRes = admin_save_homepage_story([
    'title' => 'Temporary Automated Verification Story',
    'image_url' => 'https://example.com/test.jpg',
    'link_url' => 'shop.php?category=test',
    'display_order' => 999,
    'is_active' => 1
]);
assert_test("Create Homepage Story", !empty($saveStoryRes['success']) && !empty($saveStoryRes['id']));
if (!empty($saveStoryRes['id'])) {
    $delRes = admin_delete_homepage_story((int)$saveStoryRes['id']);
    assert_test("Delete Homepage Story", $delRes);
}
// Ensure no test stories left in DB
db()->query("DELETE FROM homepage_stories WHERE title LIKE '%Verification%' OR title LIKE '%Test%'");

// 6. Test Style Story CRUD
$saveStyleRes = admin_save_style_story([
    'title' => 'Temporary Automated Style Story',
    'tag' => 'Editorial',
    'excerpt' => 'A test story excerpt for verification.',
    'image_url' => 'https://example.com/style.jpg',
    'link_url' => 'shop.php',
    'read_time' => '3 min read',
    'display_order' => 999,
    'is_active' => 1
]);
assert_test("Create Style Story", !empty($saveStyleRes['success']) && !empty($saveStyleRes['id']));
if (!empty($saveStyleRes['id'])) {
    $delStyleRes = admin_delete_style_story((int)$saveStyleRes['id']);
    assert_test("Delete Style Story", $delStyleRes);
}
db()->query("DELETE FROM style_stories WHERE title LIKE '%Verification%' OR title LIKE '%Test%'");

// 7. Verify Homepage Render includes all 5 requested sections
ob_start();
include __DIR__ . '/index.php';
$html = ob_get_clean();

assert_test("Homepage renders Circular Stories Bar", strpos($html, 'circular-stories-section') !== false);
assert_test("Homepage renders NEW ARRIVALS", stripos($html, 'NEW ARRIVALS') !== false);
assert_test("Homepage renders BEST SELLERS", stripos($html, 'BEST SELLERS') !== false);
assert_test("Homepage renders SUMMER MIDI", stripos($html, 'SUMMER MIDI') !== false);
assert_test("Homepage renders TRENDING CO-ORD SETS", stripos($html, 'TRENDING CO-ORD SETS') !== false);
assert_test("Homepage renders Style Stories & Inspirations", stripos($html, 'Style Stories & Inspirations') !== false);

echo "\n========================================================\n";
echo "SUMMARY: Passed: $passed | Failed: $failed\n";
echo "========================================================\n";

if ($failed === 0) {
    echo ">>> ALL HOMEPAGE SECTIONS & ADMIN OPERATIONS VERIFIED! <<<\n";
    exit(0);
} else {
    exit(1);
}
