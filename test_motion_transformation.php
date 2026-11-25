<?php
/**
 * YATIYA COUTURE — Complete Motion & Animation Verification Suite
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

echo "=========================================================\n";
echo "   YATIYA COUTURE — MOTION TRANSFORMATION TEST SUITE     \n";
echo "=========================================================\n\n";

$passed = 0;
$failed = 0;

function assert_check(string $title, bool $condition, string $detail = ''): void {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] " . $title . "\n";
        $passed++;
    } else {
        echo " [FAIL] " . $title . ($detail ? " — " . $detail : "") . "\n";
        $failed++;
    }
}

// 1. Capture index.php output
ob_start();
include __DIR__ . '/index.php';
$html = ob_get_clean();

// Check Cinematic Loader
assert_check("Cinematic Site Loader present", str_contains($html, 'id="siteLoader"'));
assert_check("Site Loader wordmark and progress bar rendered", 
    str_contains($html, 'loader-brand-name') && 
    str_contains($html, 'loader-progress-bar')
);

// Check Scroll Progress Bar
assert_check("Scroll Progress Bar element present", str_contains($html, 'id="scrollProgressBar"'));

// Check Luxury Cursor Aura
assert_check("Luxury Cursor Aura element present", str_contains($html, 'id="luxuryCursorAura"'));

// Check Secondary Luxury Taxonomy Bar (Matching Reference Image 2)
assert_check("Secondary Luxury Taxonomy Bar present", str_contains($html, 'class="luxury-taxonomy-bar"'));
assert_check("Taxonomy Bar contains 14 curated luxury categories", 
    substr_count($html, 'class="taxonomy-link"') === 14,
    "Found " . substr_count($html, 'class="taxonomy-link"')
);
assert_check("Taxonomy Bar contains required items from Image 2",
    str_contains($html, 'Heirloom Polki') &&
    str_contains($html, 'Imperial Bandhgalas') &&
    str_contains($html, 'Handwoven Saris') &&
    str_contains($html, 'Kurta Sets') &&
    str_contains($html, 'Gowns &amp; Anarkalis') &&
    str_contains($html, 'Luxury Linen') &&
    str_contains($html, 'Tailored Blazers')
);

// Check Circular Categories Stories Carousel (Matching Reference Image 1)
assert_check("Circular Categories Section present", str_contains($html, 'class="circular-stories-section"'));
assert_check("Circular Categories has 8 active items from Image 1", 
    substr_count($html, 'class="story-item"') === 8,
    "Found " . substr_count($html, 'class="story-item"')
);
assert_check("No broken story or truncated text", 
    str_contains($html, 'Ethnic Coords') && 
    !str_contains($html, 'Ethnic Coor...') &&
    !str_contains($html, 'Test Story')
);
assert_check("Story carousel arrow buttons present",
    str_contains($html, 'id="storyPrevBtn"') &&
    str_contains($html, 'id="storyNextBtn"')
);

// Check Motion Engine JavaScript
assert_check("Motion Engine JS script loaded", str_contains($html, 'motion.js'));
assert_check("motion.js file exists and is populated", 
    file_exists(__DIR__ . '/assets/js/motion.js') && 
    filesize(__DIR__ . '/assets/js/motion.js') > 1000
);

// Check Motion CSS in style.css
$css = file_get_contents(__DIR__ . '/assets/css/style.css');
assert_check("Motion design tokens defined in style.css", 
    str_contains($css, '--duration-cinematic') && 
    str_contains($css, '--ease-out-luxury') &&
    str_contains($css, '--ease-spring')
);
assert_check("Cinematic loader styles defined in style.css", str_contains($css, '.site-loader'));
assert_check("Luxury cursor aura styles defined in style.css", str_contains($css, '.luxury-cursor-aura'));
assert_check("Secondary taxonomy bar styles defined in style.css", str_contains($css, '.luxury-taxonomy-bar'));
assert_check("Clean circular stories styles defined without gaudy gold rings", 
    str_contains($css, '.circular-stories-section') &&
    !str_contains($css, 'animation: goldShimmer')
);
assert_check("Scroll reveal utilities defined in style.css", str_contains($css, '[data-reveal]'));
assert_check("Reduced motion media query defined in style.css", str_contains($css, 'prefers-reduced-motion: reduce'));

echo "\n---------------------------------------------------------\n";
echo "SUMMARY: Passed: $passed | Failed: $failed\n";
echo "---------------------------------------------------------\n";

if ($failed === 0) {
    echo ">>> ALL MOTION TRANSFORMATION CHECKS PASSED SUCCESSFULLY! <<<\n";
    exit(0);
} else {
    echo ">>> SOME MOTION TRANSFORMATION CHECKS FAILED <<<\n";
    exit(1);
}

