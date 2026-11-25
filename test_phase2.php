<?php
/**
 * YATIYA COUTURE - Phase 2 Verification & Test Suite
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "=========================================================\n";
echo "   YATIYA COUTURE - PHASE 2 VERIFICATION & TEST SUITE   \n";
echo "=========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assert_test($description, $condition, $details = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $description\n";
        $passCount++;
    } else {
        echo " [FAIL] $description\n";
        if ($details) {
            echo "        Details: $details\n";
        }
        $failCount++;
    }
}

// Capture generated HTML from index.php
ob_start();
include __DIR__ . '/index.php';
$html = ob_get_clean();

// 1. Check generated HTML is not empty
assert_test("Homepage generates non-empty HTML", strlen($html) > 5000, "Length: " . strlen($html) . " bytes");

// 2. Section 1: Hero Slider
$hasSection1 = str_contains($html, 'hero-slider-section');
$hasHeroBanner = str_contains($html, 'The Royal Silk &amp; Velvet Edit') || str_contains($html, 'The Royal Silk');
assert_test("Section 1: Hero Slider rendered with DB banners", $hasSection1 && $hasHeroBanner);

// 3. Section 2: Shop by Category
$hasSection2 = str_contains($html, 'aria-label="Shop By Category"');
$hasWomen = str_contains($html, 'category=women');
$hasMen = str_contains($html, 'category=men');
$hasKids = str_contains($html, 'category=kids');
$hasFootwear = str_contains($html, 'category=footwear');
$hasBags = str_contains($html, 'category=bags');
$hasAccessories = str_contains($html, 'category=accessories');
assert_test("Section 2: Shop by Category renders 6 required categories", 
    $hasSection2 && $hasWomen && $hasMen && $hasKids && $hasFootwear && $hasBags && $hasAccessories
);

// 4. Section 3: New Arrivals
$hasSection3 = str_contains($html, 'aria-label="New Arrivals"');
assert_test("Section 3: New Arrivals section rendered", $hasSection3);

// 5. Section 4: Trending Now
$hasSection4 = str_contains($html, 'aria-label="Trending Now"');
assert_test("Section 4: Trending Now section rendered", $hasSection4);

// 6. Section 5: Best Sellers
$hasSection5 = str_contains($html, 'aria-label="Best Sellers"');
assert_test("Section 5: Best Sellers section rendered", $hasSection5);

// 7. Section 6: Exclusive Collection
$hasSection6 = str_contains($html, 'exclusive-collection-section');
$hasExclusiveTitle = str_contains($html, 'Bespoke Bridal &amp; Red-Carpet Privé') || str_contains($html, 'Bespoke Bridal');
assert_test("Section 6: Exclusive Collection banner rendered from DB", $hasSection6 && $hasExclusiveTitle);

// 8. Section 7: Why Yatiya Couture (4 Pillars)
$hasSection7 = str_contains($html, 'aria-label="Why Yatiya Couture"');
$hasPillar1 = str_contains($html, 'Premium Quality');
$hasPillar2 = str_contains($html, 'Curated Collections');
$hasPillar3 = str_contains($html, 'Secure Shopping');
$hasPillar4 = str_contains($html, 'Customer-first Experience');
assert_test("Section 7: Why Yatiya Couture with all 4 pillars", 
    $hasSection7 && $hasPillar1 && $hasPillar2 && $hasPillar3 && $hasPillar4
);

// 9. Section 8: Customer Reviews / Brand Promise (No Fake Reviews)
$hasSection8 = str_contains($html, 'The Yatiya Atelier Promise') || str_contains($html, 'Client Reflections');
assert_test("Section 8: Customer Reviews / Brand Promise without fake reviews", $hasSection8);

// 10. Section 9: Newsletter
$hasSection9 = str_contains($html, 'id="newsletterSection"');
$hasCsrf = str_contains($html, 'name="csrf_token"');
assert_test("Section 9: Newsletter subscription with CSRF token", $hasSection9 && $hasCsrf);

// 11. Section 10: Footer
$hasSection10 = str_contains($html, 'site-footer');
$hasConcierge = str_contains($html, 'Flagship Concierge');
assert_test("Section 10: Master Luxury Footer rendered", $hasSection10 && $hasConcierge);

// 12. Verification of Section Order in the HTML document
$pos1 = strpos($html, 'class="hero-slider-section"');
$pos2 = strpos($html, 'aria-label="Shop By Category"');
$pos3 = strpos($html, 'aria-label="New Arrivals"');
$pos4 = strpos($html, 'aria-label="Trending Now"');
$pos5 = strpos($html, 'aria-label="Best Sellers"');
$pos6 = strpos($html, 'class="exclusive-collection-section"');
$pos7 = strpos($html, 'aria-label="Why Yatiya Couture"');
$pos8 = strpos($html, 'aria-label="Patron Testimonials & Brand Promise"');
$pos9 = strpos($html, 'id="newsletterSection"');
$pos10 = strpos($html, 'class="site-footer"');

$orderValid = ($pos1 !== false && $pos2 !== false && $pos3 !== false && $pos4 !== false &&
               $pos5 !== false && $pos6 !== false && $pos7 !== false && $pos8 !== false &&
               $pos9 !== false && $pos10 !== false &&
               $pos1 < $pos2 && $pos2 < $pos3 && $pos3 < $pos4 && $pos4 < $pos5 && 
               $pos5 < $pos6 && $pos6 < $pos7 && $pos7 < $pos8 && $pos8 < $pos9 && $pos9 < $pos10);

assert_test("Exact Section Sequence 1 to 10 Confirmed", $orderValid, 
    "Positions: Hero($pos1) < Category($pos2) < New Arrivals($pos3) < Trending($pos4) < Best Sellers($pos5) < Exclusive($pos6) < Why($pos7) < Reviews($pos8) < Newsletter($pos9) < Footer($pos10)"
);

// 13. Mega Menu rendered for navigation
$hasMega = str_contains($html, 'mega-menu');
assert_test("Multi-Column Mega Menu rendered in header", $hasMega);

// 14. Quick View Modal and Wishlist Toast
$hasModal = str_contains($html, 'quickViewModal');
$hasToast = str_contains($html, 'wishlistToast');
$hasDrawer = str_contains($html, 'mobileDrawer');
assert_test("UI Components (Quick View Modal, Wishlist Toast, Mobile Drawer) Present", $hasModal && $hasToast && $hasDrawer);

echo "\n---------------------------------------------------------\n";
echo "PHASE 2 SUMMARY: Passed: $passCount | Failed: $failCount\n";
echo "---------------------------------------------------------\n";

if ($failCount === 0) {
    echo ">>> PHASE 2 PREMIUM UI & HOMEPAGE VALIDATION SUCCESSFUL <<<\n";
    exit(0);
} else {
    echo ">>> PHASE 2 VALIDATION ENCOUNTERED FAILURES <<<\n";
    exit(1);
}
