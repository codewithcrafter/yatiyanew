<?php
/**
 * YATIYA COUTURE — Taxonomy Bar Marquee & Theme Verification Suite
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "=========================================================\n";
echo "   YATIYA COUTURE — TAXONOMY MARQUEE VERIFICATION SUITE  \n";
echo "=========================================================\n\n";

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] " . $name . "\n";
        $passed++;
    } else {
        echo " [FAIL] " . $name . ($detail ? " — " . $detail : "") . "\n";
        $failed++;
    }
}

// 1. Capture HTML from header render
ob_start();
include __DIR__ . '/includes/header.php';
$html = ob_get_clean();

// Check structural container & track elements
assert_test("Taxonomy Bar container present with ID", str_contains($html, 'id="luxuryTaxonomyBar"'));
assert_test("Taxonomy Bar Track present with ID", str_contains($html, 'id="taxonomyBarTrack"'));
assert_test("Taxonomy Bar Inner present with ID", str_contains($html, 'id="taxonomyBarInner"'));

// Check 14 curated category links
$categories = [
    'Heirloom Polki &amp; Kundan',
    'Imperial Bandhgalas',
    'Minaudi&egrave;res &amp; Evening Bags',
    'Women',
    'Handwoven Saris &amp; Drapes',
    'Kurta Sets &amp; Sherwanis',
    'Men',
    'Gowns &amp; Anarkalis',
    'Kids',
    'Luxury Linen &amp; Shirts',
    'Clothing',
    'Tailored Blazers &amp; Sets',
    'Footwear',
    'Bags'
];

foreach ($categories as $cat) {
    assert_test("Contains category link: " . html_entity_decode($cat), str_contains($html, $cat));
}

assert_test("Server-rendered link count is exactly 14", substr_count($html, 'class="taxonomy-link"') === 14);

// Check separators
assert_test("Atelier star separators present with aria-hidden", 
    str_contains($html, 'class="taxonomy-divider"') && 
    str_contains($html, '&#10022;')
);

// 2. Check CSS styling in style.css
$css = file_get_contents(__DIR__ . '/assets/css/style.css');

assert_test("CSS defines Deep Espresso #24211E background", str_contains($css, '#24211E'));
assert_test("CSS defines Warm Ivory #F7F4ED text color", str_contains($css, '#F7F4ED'));
assert_test("CSS defines Champagne Gold #D6BC7A accents", str_contains($css, '#D6BC7A'));
assert_test("CSS defines infinite marquee animation @keyframes taxonomyMarquee", str_contains($css, '@keyframes taxonomyMarquee'));
assert_test("CSS defines pause on hover (animation-play-state: paused)", str_contains($css, 'animation-play-state: paused'));
assert_test("CSS defines pause on keyboard focus-within", str_contains($css, ':focus-within .taxonomy-bar-track'));
assert_test("CSS defines edge vignette fade mask", str_contains($css, 'mask-image'));
assert_test("CSS defines gold shine sweep keyframe", str_contains($css, '@keyframes goldShineSweep'));
assert_test("CSS defines star twinkle keyframe", str_contains($css, '@keyframes starTwinkle'));
assert_test("CSS defines responsive speed tiers (991px and 768px)", 
    str_contains($css, 'max-width: 991px') && 
    str_contains($css, 'max-width: 768px')
);
assert_test("CSS defines prefers-reduced-motion fallback", str_contains($css, 'prefers-reduced-motion: reduce'));

// 3. Check JavaScript in motion.js
$js = file_get_contents(__DIR__ . '/assets/js/motion.js');

assert_test("JS implements initTaxonomyMarquee function", str_contains($js, 'function initTaxonomyMarquee'));
assert_test("JS clones content programmatically for seamless loop", str_contains($js, 'cloneNode(true)'));
assert_test("JS sets aria-hidden and tabindex=-1 on clone to prevent accessibility clutter", 
    str_contains($js, "setAttribute('aria-hidden', 'true')") && 
    str_contains($js, "setAttribute('tabindex', '-1')")
);
assert_test("JS handles pointer enter/leave pause and resume", 
    str_contains($js, "bar.addEventListener('mouseenter'") && 
    str_contains($js, "bar.addEventListener('mouseleave'")
);
assert_test("JS uses IntersectionObserver for viewport efficiency", str_contains($js, 'IntersectionObserver'));

echo "\n---------------------------------------------------------\n";
echo "SUMMARY: Passed: $passed | Failed: $failed\n";
echo "---------------------------------------------------------\n";

if ($failed === 0) {
    echo ">>> ALL TAXONOMY MARQUEE VERIFICATION TESTS PASSED! <<<\n";
    exit(0);
} else {
    echo ">>> TAXONOMY MARQUEE TESTS ENCOUNTERED FAILURES <<<\n";
    exit(1);
}

