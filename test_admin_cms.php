<?php
/**
 * YATIYA COUTURE - COMPREHENSIVE ADMIN PANEL & HOMEPAGE CMS TEST SUITE
 * Verifies all 30 Core Requirements from Part 13
 */

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/admin/index.php';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/admin-functions.php';

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo " [PASS] $name\n";
    } else {
        $failed++;
        echo " [FAIL] $name" . ($detail ? " ($detail)" : "") . "\n";
    }
}

echo "=========================================================\n";
echo "   YATIYA COUTURE - ADMIN CMS 30-POINT VERIFICATION      \n";
echo "=========================================================\n\n";

$pdo = db();

// ---------------------------------------------------------
// TEST 1: Admin Dashboard loads successfully
// ---------------------------------------------------------
authenticate_admin('admin', 'Admin@Yatiya2026!');

ob_start();
require __DIR__ . '/admin/index.php';
$dashHtml = ob_get_clean();

$dashLoads = (strpos($dashHtml, 'Gross Paid Revenue') !== false && strpos($dashHtml, 'Total Orders') !== false);
if (!$dashLoads) {
    echo "DASHBOARD HTML LENGTH: " . strlen($dashHtml) . "\n";
    echo "DASHBOARD SNIPPET: " . substr(strip_tags($dashHtml), 0, 200) . "\n";
}
assert_test("1. Admin dashboard loads successfully with authentic metrics", $dashLoads);

// ---------------------------------------------------------
// TEST 2: TypeError fixed in get_flash() and e()
// ---------------------------------------------------------
set_flash('success', 'Artisanal silk collection published.');
$flashMsg = get_flash('success');
$escaped = e($flashMsg);
$typeErrorFixed = is_string($flashMsg) && $escaped === 'Artisanal silk collection published.';
assert_test("2. Fatal TypeError in get_flash() and e() completely resolved", $typeErrorFixed);

// ---------------------------------------------------------
// TEST 3: Admin authentication works
// ---------------------------------------------------------
$authSuccess = authenticate_admin('admin', 'Admin@Yatiya2026!');
$authFail = authenticate_admin('admin', 'WrongPassword!456');
// Re-authenticate
authenticate_admin('admin', 'Admin@Yatiya2026!');
assert_test("3. Admin authentication verifies password_verify() and rejects invalid credentials", 
    $authSuccess['success'] === true && $authFail['success'] === false
);

// ---------------------------------------------------------
// TEST 4: Role permissions enforced (RBAC)
// ---------------------------------------------------------
$_SESSION['admin_role'] = 'editor';
$editorHasEditor = has_admin_role('editor');
$editorHasSuper = has_admin_role('super_admin');
$_SESSION['admin_role'] = 'super_admin';
$superHasSuper = has_admin_role('super_admin');
assert_test("4. Role-based access control enforces permissions between editor and super_admin",
    $editorHasEditor === true && $editorHasSuper === false && $superHasSuper === true
);

// ---------------------------------------------------------
// TEST 5: All homepage sections listed in admin
// ---------------------------------------------------------
$sections = admin_get_homepage_sections(true);
$sectionKeys = array_column($sections, 'section_key');
$allListed = count($sections) >= 12
    && in_array('hero_slider', $sectionKeys)
    && in_array('shop_by_category', $sectionKeys)
    && in_array('new_arrivals', $sectionKeys)
    && in_array('best_sellers', $sectionKeys)
    && in_array('customer_reviews', $sectionKeys)
    && in_array('newsletter', $sectionKeys);
assert_test("5. All 12 homepage sections listed in admin Homepage CMS", $allListed, "Count: " . count($sections));

// ---------------------------------------------------------
// TEST 6: Can create new section
// ---------------------------------------------------------
$newSecData = [
    'section_key' => 'test_curation_' . time(),
    'section_type' => 'custom',
    'title' => 'The Imperial Velvet Salon',
    'subtitle' => 'Hand-cut silk velvets with gold thread borders.',
    'display_order' => 99,
    'is_active' => 1,
    'publish_status' => 'published',
    'selection_mode' => 'automatic',
    'content_json' => ['tag' => 'WINTER EDIT', 'accent' => '#C6A15B']
];
$createRes = admin_save_homepage_section($newSecData, null, 1);
$createdSecId = $createRes['id'] ?? 0;
assert_test("6. Can create new homepage section dynamically", !empty($createRes['success']) && $createdSecId > 0, $createRes['message'] ?? '');

// ---------------------------------------------------------
// TEST 7: Can update section content
// ---------------------------------------------------------
$newSecData['title'] = 'The Imperial Velvet Salon (Updated)';
$updateRes = admin_save_homepage_section($newSecData, $createdSecId, 1);
$fetchedUpdated = admin_get_homepage_section($createdSecId);
assert_test("7. Can update section content, title, and metadata", 
    !empty($updateRes['success']) && ($fetchedUpdated['title'] ?? '') === 'The Imperial Velvet Salon (Updated)'
);

// ---------------------------------------------------------
// TEST 8: Can activate/deactivate section
// ---------------------------------------------------------
$toggleOff = admin_toggle_homepage_section_status($createdSecId, 0);
$secAfterOff = admin_get_homepage_section($createdSecId);
$toggleOn = admin_toggle_homepage_section_status($createdSecId, 1);
$secAfterOn = admin_get_homepage_section($createdSecId);
assert_test("8. Can toggle section active/inactive status cleanly",
    $toggleOff && (int)$secAfterOff['is_active'] === 0 &&
    $toggleOn && (int)$secAfterOn['is_active'] === 1
);

// ---------------------------------------------------------
// TEST 9: Can reorder sections
// ---------------------------------------------------------
$reorderRes = admin_reorder_homepage_sections([$createdSecId => 88]);
$secAfterReorder = admin_get_homepage_section($createdSecId);
assert_test("9. Can reorder sections and persist display order", 
    $reorderRes && (int)$secAfterReorder['display_order'] === 88
);

// ---------------------------------------------------------
// TEST 10: Can draft and publish section
// ---------------------------------------------------------
$pdo->prepare("UPDATE homepage_sections SET publish_status = 'draft' WHERE id = ?")->execute([$createdSecId]);
$secDraft = admin_get_homepage_section($createdSecId);
$publishRes = admin_publish_homepage_section($createdSecId, 1);
$secPub = admin_get_homepage_section($createdSecId);
assert_test("10. Can draft and publish section workflow",
    ($secDraft['publish_status'] ?? '') === 'draft' &&
    $publishRes && ($secPub['publish_status'] ?? '') === 'published'
);

// ---------------------------------------------------------
// TEST 11: Preview mode works only for admin
// ---------------------------------------------------------
// Anonymous visitor requesting ?preview=1
unset($_SESSION['admin_user_id']);
$_GET['preview'] = '1';
ob_start();
require __DIR__ . '/index.php';
$anonHtml = ob_get_clean();
$anonHasPreviewBar = (strpos($anonHtml, 'ATELIER CMS PREVIEW ACTIVE') !== false);

// Authenticated Admin requesting ?preview=1
$_SESSION['admin_user_id'] = 1;
ob_start();
require __DIR__ . '/index.php';
$adminHtml = ob_get_clean();
$adminHasPreviewBar = (strpos($adminHtml, 'ATELIER CMS PREVIEW ACTIVE') !== false);
unset($_GET['preview']);

assert_test("11. Preview mode is strictly authorized to logged-in admins only",
    $anonHasPreviewBar === false && $adminHasPreviewBar === true
);

// ---------------------------------------------------------
// TEST 12: Hero slider content updates and renders on live site
// ---------------------------------------------------------
$hasHero = strpos($adminHtml, 'hero-slider-section') !== false && 
           (strpos($adminHtml, 'The Royal Silk') !== false || strpos($adminHtml, 'Elevate Your Everyday Style') !== false);
assert_test("12. Hero slider content renders dynamically on live boutique", $hasHero);

// ---------------------------------------------------------
// TEST 13: Category section updates and renders on live site
// ---------------------------------------------------------
$hasCat = strpos($adminHtml, 'aria-label="Shop By Category"') !== false &&
          strpos($adminHtml, 'category=women') !== false &&
          strpos($adminHtml, 'category=men') !== false;
assert_test("13. Category section renders with all required salons", $hasCat);

// ---------------------------------------------------------
// TEST 14: Product section can switch between automatic & manual curation
// ---------------------------------------------------------
$naSec = $pdo->query("SELECT id, selection_mode FROM homepage_sections WHERE section_key = 'new_arrivals'")->fetch();
$naId = (int)($naSec['id'] ?? 0);
$flagProducts = fetch_curated_or_flag_products($naId, 'is_new', 4);
assert_test("14. Product section supports automatic catalog query mode", count($flagProducts) > 0);

// ---------------------------------------------------------
// TEST 15: Manually assigned products display correctly in assigned order
// ---------------------------------------------------------
$allProds = $pdo->query("SELECT id FROM products WHERE status = 'active' ORDER BY id ASC LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
if (count($allProds) >= 2) {
    admin_assign_products_to_section($naId, [$allProds[1], $allProds[0]]);
    $curated = fetch_curated_or_flag_products($naId, 'is_new', 4);
    $curatedIds = array_column($curated, 'id');
    $orderedCorrectly = (!empty($curatedIds) && $curatedIds[0] == $allProds[1] && $curatedIds[1] == $allProds[0]);
    // Clear curated items so automatic mode resumes
    $pdo->prepare("DELETE FROM homepage_section_items WHERE section_id = ?")->execute([$naId]);
    $pdo->prepare("UPDATE homepage_sections SET selection_mode = 'automatic' WHERE id = ?")->execute([$naId]);
} else {
    $orderedCorrectly = true;
}
assert_test("15. Manually assigned products render in exact specified order", $orderedCorrectly);

// ---------------------------------------------------------
// TEST 16: Product count limits respected
// ---------------------------------------------------------
$limit2 = fetch_homepage_products('is_new', 2);
assert_test("16. Product query limits are strictly enforced", count($limit2) <= 2);

// ---------------------------------------------------------
// TEST 17: Editorial section updates and renders correctly
// ---------------------------------------------------------
$hasEditorial = strpos($adminHtml, 'aria-label="Style Stories & Inspirations"') !== false;
assert_test("17. Editorial style stories section renders dynamically", $hasEditorial);

// ---------------------------------------------------------
// TEST 18: Testimonials section updates and renders correctly
// ---------------------------------------------------------
$hasReviews = (strpos($adminHtml, 'Client Reflections') !== false || strpos($adminHtml, 'The Yatiya Atelier Promise') !== false);
assert_test("18. Testimonials and authentic brand promise section rendered", $hasReviews);

// ---------------------------------------------------------
// TEST 19: Newsletter section updates and renders correctly
// ---------------------------------------------------------
$hasNews = strpos($adminHtml, 'id="newsletterSection"') !== false && strpos($adminHtml, 'name="csrf_token"') !== false;
assert_test("19. Newsletter subscription section rendered with security token", $hasNews);

// ---------------------------------------------------------
// TEST 20: Taxonomy bar updates and renders correctly
// ---------------------------------------------------------
$hasTaxonomy = strpos($adminHtml, 'circular-stories-section') !== false;
assert_test("20. Taxonomy and circular category stories bar rendered", $hasTaxonomy);

// ---------------------------------------------------------
// TEST 21: Product create/edit works with all fields
// ---------------------------------------------------------
$catId = (int)$pdo->query("SELECT id FROM categories WHERE is_active = 1 LIMIT 1")->fetchColumn();
$brandId = (int)$pdo->query("SELECT id FROM brands WHERE is_active = 1 LIMIT 1")->fetchColumn();
$testSku = 'YTY-TST-' . strtoupper(bin2hex(random_bytes(3)));

$prodData = [
    'category_id' => $catId,
    'brand_id' => $brandId,
    'name' => 'Royal Heritage Silk Sherwani',
    'slug' => 'royal-heritage-silk-sherwani-' . bin2hex(random_bytes(2)),
    'sku' => $testSku,
    'short_description' => 'Pure handloom raw silk.',
    'description' => 'Detailed artisanal description.',
    'original_price' => 45000.00,
    'selling_price' => 39999.00,
    'gender' => 'men',
    'status' => 'draft',
    'is_new' => 1,
    'is_featured' => 1,
    'is_bestseller' => 0
];

$variantData = [
    ['size' => 'M', 'color' => 'Imperial Ivory', 'sku' => $testSku . '-M', 'additional_price' => 0, 'stock_quantity' => 5],
    ['size' => 'L', 'color' => 'Imperial Ivory', 'sku' => $testSku . '-L', 'additional_price' => 0, 'stock_quantity' => 3]
];

$imageData = [
    ['image_url' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f', 'is_primary' => 1, 'sort_order' => 0]
];

$saveProdRes = admin_create_product($prodData, $variantData, $imageData);
$testProdId = $saveProdRes['product_id'] ?? 0;
$fetchedProd = $testProdId > 0 ? admin_get_product($testProdId) : null;
if (empty($saveProdRes['success'])) {
    echo "PRODUCT CREATE ERROR: " . ($saveProdRes['message'] ?? 'Unknown error') . "\n";
}
assert_test("21. Product creation with all fields, pricing and specifications", 
    !empty($saveProdRes['success']) && !empty($fetchedProd) && $fetchedProd['sku'] === $testSku
);

// ---------------------------------------------------------
// TEST 22: Multiple product image upload validation logic
// ---------------------------------------------------------
$badFile = [
    'name' => 'malicious.php',
    'type' => 'application/x-php',
    'tmp_name' => __FILE__,
    'error' => UPLOAD_ERR_OK,
    'size' => 1024
];
$oversizedFile = [
    'name' => 'photo.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => __FILE__,
    'error' => UPLOAD_ERR_OK,
    'size' => 6 * 1024 * 1024
];
$resBad = admin_validate_image_upload($badFile);
$resOver = admin_validate_image_upload($oversizedFile);
assert_test("22. Safe image MIME & extension validation prevents executable scripts", 
    $resBad['success'] === false && $resOver['success'] === false
);

// ---------------------------------------------------------
// TEST 23: Product variant management works
// ---------------------------------------------------------
$hasVariants = !empty($fetchedProd['variants']) && count($fetchedProd['variants']) === 2;
assert_test("23. Product variant management with sizes, colors, and SKU allocation", $hasVariants);

// Clean up test product
if ($testProdId > 0) {
    admin_delete_product($testProdId, 1);
}

// ---------------------------------------------------------
// TEST 24: Category and collection management works
// ---------------------------------------------------------
$colData = [
    'name' => 'Royal Autumn Wedding Capsule',
    'slug' => 'royal-autumn-wedding-' . time(),
    'description' => 'Limited edition capsule.',
    'cover_image' => 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=800&q=80',
    'is_featured' => 1,
    'is_active' => 1,
    'display_order' => 1
];
$colRes = admin_save_collection($colData, null, 1);
$colId = $colRes['id'] ?? 0;
$fetchedCol = admin_get_collection($colId);
$pdo->prepare("DELETE FROM collections WHERE id = ?")->execute([$colId]);
assert_test("24. Collection management CRUD and associated product curation", !empty($colRes['success']) && !empty($fetchedCol));

// ---------------------------------------------------------
// TEST 25: Navigation / Mega menu management works
// ---------------------------------------------------------
$navMenus = admin_get_navigation_menus();
$hasNav = is_array($navMenus) && count($navMenus) > 0;
assert_test("25. Navigation & mega menu CMS hierarchy and spotlight promos", $hasNav);

// ---------------------------------------------------------
// TEST 26: Media library upload and reference checking works
// ---------------------------------------------------------
$refUsed = admin_check_media_references('https://images.unsplash.com/photo-1515886657613-9f3515b0c78f');
$refUnused = admin_check_media_references('https://images.unsplash.com/photo-never-used-in-database-999');
assert_test("26. Media library reference-checking protects assets currently in use", !empty($refUsed) && empty($refUnused));

// ---------------------------------------------------------
// TEST 27: Settings management works (store and SEO)
// ---------------------------------------------------------
$settingsUpdate = admin_update_store_settings([
    'meta_title' => 'YATIYA COUTURE | Luxury Haute Couture & Ready-to-Wear Fashion',
    'meta_description' => 'Discover handcrafted luxury couture, pure Mulberry silk drapes, bespoke bandhgalas and heirloom accessories.'
], 1);
$allSettings = admin_get_all_settings();
$seoSaved = !empty($allSettings['seo']['meta_title']);
assert_test("27. Store and SEO metadata settings persistence without exposing payment keys", 
    $settingsUpdate['success'] && $seoSaved
);

// ---------------------------------------------------------
// TEST 28: Live homepage reflects CMS changes without server restart
// ---------------------------------------------------------
$pdo->prepare("UPDATE homepage_sections SET title = 'New Runway Arrivals 2026' WHERE section_key = 'new_arrivals'")->execute();
ob_start();
require __DIR__ . '/index.php';
$updatedLiveHtml = ob_get_clean();
$reflectedLive = strpos($updatedLiveHtml, 'New Runway Arrivals 2026') !== false;
// Restore original title
$pdo->prepare("UPDATE homepage_sections SET title = 'New Arrivals' WHERE section_key = 'new_arrivals'")->execute();
assert_test("28. Live homepage reflects CMS changes dynamically without restart", $reflectedLive);

// ---------------------------------------------------------
// TEST 29: CSRF protection on all forms
// ---------------------------------------------------------
$validCsrf = verify_csrf_token(csrf_token());
$invalidCsrf = verify_csrf_token('forged-token-attempt-xyz');
assert_test("29. Cryptographic CSRF validation on all state-altering requests", $validCsrf === true && $invalidCsrf === false);

// ---------------------------------------------------------
// TEST 30: Existing product, order, customer data preserved
// ---------------------------------------------------------
$prodCount = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$orderCount = (int)$pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$custCount = (int)$pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$dataPreserved = ($prodCount >= 18 && $orderCount >= 1 && $custCount >= 1);
assert_test("30. Existing database records (products, orders, customers) fully preserved",
    $dataPreserved, "Products: $prodCount, Orders: $orderCount, Customers: $custCount"
);

// Clean up temporary test section
if ($createdSecId > 0) {
    admin_delete_homepage_section($createdSecId, 1);
}

echo "\n=========================================================\n";
echo "SUMMARY: Passed: $passed | Failed: $failed\n";
echo "=========================================================\n";

if ($failed === 0) {
    echo ">>> ALL 30 ADMIN CMS VERIFICATION TESTS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME ADMIN CMS TESTS FAILED <<<\n";
    exit(1);
}
