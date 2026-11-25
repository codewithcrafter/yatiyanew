<?php
/**
 * YATIYA COUTURE - Luxury Master Footer & Modals
 * Brand Vision: "ELEVATE YOUR EVERYDAY STYLE"
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$storePhone = get_setting('store_phone', '+91 (0) 11 4987 6543');
$storeEmail = get_setting('store_email', 'concierge@yatiyacouture.com');
$storeAddress = get_setting('store_address', 'The Haute Promenade, 42 Luxury Boulevard, New Delhi, India 110001');
$socialInstagram = get_setting('social_instagram', 'https://instagram.com/yatiyacouture');
$socialPinterest = get_setting('social_pinterest', 'https://pinterest.com/yatiyacouture');
$socialFacebook = get_setting('social_facebook', 'https://facebook.com/yatiyacouture');
$categories = $categories ?? get_active_categories(20);
?>
    </main>

    <!-- Quick View Modal Component (Vanilla JS Powered) -->
    <div class="modal-backdrop" id="quickViewModal" role="dialog" aria-modal="true" aria-labelledby="quickViewTitle" style="display: none;">
        <div class="modal-dialog quickview-dialog">
            <button type="button" class="modal-close-btn" id="quickViewCloseBtn" aria-label="Close modal">&times;</button>
            <div class="quickview-body">
                <div class="quickview-gallery">
                    <img src="" alt="" id="quickViewImage" class="quickview-main-image">
                </div>
                <div class="quickview-details">
                    <span class="quickview-brand">YATIYA COUTURE ATELIER</span>
                    <h3 class="quickview-title font-serif" id="quickViewTitle">Product Name</h3>
                    <div class="quickview-price-wrap">
                        <span class="quickview-selling-price" id="quickViewPrice">₹ 0.00</span>
                        <span class="quickview-original-price" id="quickViewOriginalPrice"></span>
                        <span class="quickview-discount-badge" id="quickViewDiscount"></span>
                    </div>
                    <p class="quickview-description" id="quickViewDesc">
                        Handcrafted with pure silks and artisan embroidery by master karigars.
                    </p>

                    <div class="quickview-options">
                        <!-- Colors -->
                        <div class="quickview-option-group" id="quickViewColorsGroup" style="display: none;">
                            <label class="quickview-option-label">Available Colors:</label>
                            <div class="quickview-colors-selector" id="quickViewColors"></div>
                        </div>

                        <!-- Sizes -->
                        <div class="quickview-option-group" id="quickViewSizesGroup">
                            <label class="quickview-option-label">Select Size:</label>
                            <div class="quickview-size-selector" id="quickViewSizes">
                                <button type="button" class="size-pill active">S</button>
                                <button type="button" class="size-pill">M</button>
                                <button type="button" class="size-pill">L</button>
                                <button type="button" class="size-pill">XL</button>
                            </div>
                        </div>

                        <!-- Stock Status -->
                        <div class="quickview-stock-status" id="quickViewStock">
                            <span class="stock-pill stock-available"><span class="stock-dot"></span> In Atelier Stock</span>
                        </div>
                    </div>

                    <div class="quickview-actions">
                        <a href="<?= BASE_URL ?>/cart.php" class="btn btn-gold btn-block" id="quickViewAddToCartBtn">
                            Add to Shopping Bag
                        </a>
                        <a href="<?= BASE_URL ?>/product.php" class="btn btn-outline btn-block" id="quickViewDetailsLink">
                            View Full Product Dossier &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Micro-interaction Wishlist Feedback Toast -->
    <div class="wishlist-toast" id="wishlistToast" role="status" aria-live="polite" style="display: none;">
        <span class="wishlist-toast-icon">♥</span>
        <span class="wishlist-toast-msg" id="wishlistToastMsg">Saved to your Private Wishlist</span>
    </div>

    <!-- Responsive Mobile Navigation Drawer -->
    <aside class="mobile-drawer" id="mobileDrawer" aria-label="Mobile Navigation" style="display:none;">
        <div class="mobile-drawer-overlay" id="mobileDrawerOverlay"></div>
        <div class="mobile-drawer-content">
            <div class="mobile-drawer-header">
                <div class="brand-logo">
                    <span class="brand-name">YATIYA</span>
                    <span class="brand-tagline">COUTURE &bull; ATELIER</span>
                </div>
                <button type="button" class="mobile-drawer-close" id="mobileDrawerCloseBtn" aria-label="Close Navigation">&times;</button>
            </div>

            <!-- Mobile Search Bar inside Drawer -->
            <div class="mobile-drawer-search">
                <form action="<?= BASE_URL ?>/shop.php" method="GET" class="mobile-drawer-search-form">
                    <input type="search" name="q" placeholder="Search luxury silks, gowns..." required>
                    <button type="submit" aria-label="Search">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                </form>
            </div>

            <div class="mobile-drawer-body">
                <ul class="mobile-nav-list">
                    <li><a href="<?= BASE_URL ?>/index.php" class="mobile-nav-link">Home</a></li>
                    <li><a href="<?= BASE_URL ?>/shop.php" class="mobile-nav-link">All Collections</a></li>
                    
                    <?php if (!empty($categories)): ?>
                        <?php foreach ($categories as $cat): ?>
                            <li class="mobile-nav-item">
                                <a href="<?= BASE_URL ?>/shop.php?category=<?= urlencode($cat['slug']) ?>" 
                                   class="mobile-nav-link <?= ($cat['slug'] === 'sale') ? 'sale-highlight' : '' ?>">
                                    <?= e($cat['name']) ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <li class="mobile-nav-divider"></li>
                    <li><a href="<?= BASE_URL ?>/account.php" class="mobile-nav-link">Client Profile</a></li>
                    <li><a href="<?= BASE_URL ?>/orders.php" class="mobile-nav-link">Track Your Order</a></li>
                    <li><a href="<?= BASE_URL ?>/wishlist.php" class="mobile-nav-link">Private Wishlist</a></li>
                    <li><a href="<?= BASE_URL ?>/cart.php" class="mobile-nav-link">Shopping Bag</a></li>
                    <li><a href="<?= BASE_URL ?>/contact.php" class="mobile-nav-link">Book Atelier Appointment</a></li>
                </ul>

                <div class="mobile-drawer-footer">
                    <p class="concierge-line"><strong>Client Concierge:</strong> <?= e($storePhone) ?></p>
                    <p class="concierge-line"><?= e($storeEmail) ?></p>
                </div>
            </div>
        </div>
    </aside>

    <!-- Comprehensive Luxury Footer -->
    <footer class="site-footer">
        <div class="container">
            <div class="footer-top">
                <!-- Column 1: Brand & Atelier Story -->
                <div class="footer-col footer-brand">
                    <h3 class="font-serif">YATIYA</h3>
                    <span class="footer-tagline">Haute Couture & Atelier</span>
                    <p class="footer-about">
                        Dedicated to the pursuit of artisanal perfection. Every creation reflects centuries-old Indian handloom mastery reinterpreted for the international luxury stage.
                    </p>
                    <div class="footer-social-links">
                        <a href="<?= e($socialInstagram) ?>" target="_blank" rel="noopener noreferrer" class="footer-social-link" title="Instagram" aria-label="Instagram">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="2" y="2" width="20" height="20" rx="5" ry="5"></rect>
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"></path>
                                <line x1="17.5" y1="6.5" x2="17.51" y2="6.5"></line>
                            </svg>
                        </a>
                        <a href="<?= e($socialPinterest) ?>" target="_blank" rel="noopener noreferrer" class="footer-social-link" title="Pinterest" aria-label="Pinterest">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M12 2C6.48 2 2 6.48 2 12c0 4.24 2.66 7.86 6.42 9.27-.09-.79-.17-2 .04-2.86.19-.77 1.25-5.3 1.25-5.3s-.32-.64-.32-1.58c0-1.48.86-2.58 1.93-2.58.91 0 1.35.68 1.35 1.5 0 .91-.58 2.28-.88 3.55-.25 1.06.53 1.93 1.58 1.93 1.9 0 3.36-2 3.36-4.89 0-2.56-1.84-4.35-4.47-4.35-3.05 0-4.84 2.29-4.84 4.65 0 .92.35 1.91.8 2.45.09.11.1.2.07.31-.08.33-.26 1.05-.29 1.19-.05.2-.16.24-.37.15-1.39-.65-2.26-2.67-2.26-4.3 0-3.5 2.54-6.72 7.34-6.72 3.86 0 6.85 2.75 6.85 6.42 0 3.83-2.42 6.92-5.77 6.92-1.13 0-2.19-.59-2.55-1.28l-.69 2.65c-.25.96-.92 2.16-1.38 2.89.84.26 1.73.4 2.66.4 5.52 0 10-4.48 10-10S17.52 2 12 2z"></path>
                            </svg>
                        </a>
                        <a href="<?= e($socialFacebook) ?>" target="_blank" rel="noopener noreferrer" class="footer-social-link" title="Facebook" aria-label="Facebook">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"></path>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Column 2: Client Services -->
                <div class="footer-col">
                    <h4 class="footer-col-title">Client Concierge</h4>
                    <ul class="footer-nav-list">
                        <li><a href="<?= BASE_URL ?>/account.php">Client Profile</a></li>
                        <li><a href="<?= BASE_URL ?>/orders.php">Order Tracking</a></li>
                        <li><a href="<?= BASE_URL ?>/wishlist.php">Private Wishlist</a></li>
                        <li><a href="<?= BASE_URL ?>/contact.php">Book Atelier Appointment</a></li>
                        <li><a href="<?= BASE_URL ?>/faqs.php">Frequently Asked Questions</a></li>
                    </ul>
                </div>

                <!-- Column 3: The House -->
                <div class="footer-col">
                    <h4 class="footer-col-title">The House</h4>
                    <ul class="footer-nav-list">
                        <li><a href="<?= BASE_URL ?>/about.php">Heritage & Atelier</a></li>
                        <li><a href="<?= BASE_URL ?>/shop.php?category=new-arrivals">Runway Autumn 2026</a></li>
                        <li><a href="<?= BASE_URL ?>/shop.php?category=best-sellers">Signature Icons</a></li>
                        <li><a href="<?= BASE_URL ?>/contact.php">Private Salons & Boutiques</a></li>
                        <li><a href="<?= BASE_URL ?>/about.php#craftsmanship">Artisanal Craftsmanship</a></li>
                    </ul>
                </div>

                <!-- Column 4: Policies -->
                <div class="footer-col">
                    <h4 class="footer-col-title">Policies</h4>
                    <ul class="footer-nav-list">
                        <li><a href="<?= BASE_URL ?>/shipping-policy.php">Shipping & White-Glove Delivery</a></li>
                        <li><a href="<?= BASE_URL ?>/return-refund-policy.php">Returns & Exchanges</a></li>
                        <li><a href="<?= BASE_URL ?>/cancellation-policy.php">Cancellation Policy</a></li>
                        <li><a href="<?= BASE_URL ?>/privacy-policy.php">Privacy & Patrons Notice</a></li>
                        <li><a href="<?= BASE_URL ?>/terms-conditions.php">Terms & Conditions</a></li>
                    </ul>
                </div>

                <!-- Column 5: Concierge Contact -->
                <div class="footer-col footer-concierge">
                    <h4 class="footer-col-title">Flagship Concierge</h4>
                    <p>
                        <strong>Direct:</strong> <?= e($storePhone) ?>
                    </p>
                    <p>
                        <strong>Inquiries:</strong> <?= e($storeEmail) ?>
                    </p>
                    <p>
                        <strong>Boutique:</strong><br>
                        <?= nl2br(e($storeAddress)) ?>
                    </p>
                    <p style="font-size: 0.75rem; color: var(--gold-champagne); margin-top: 8px;">
                        Mon &ndash; Sat: 10:00 AM &ndash; 8:00 PM IST
                    </p>
                </div>
            </div>

            <!-- Footer Bottom: Legal & Payment Badges -->
            <div class="footer-bottom">
                <div class="footer-copy">
                    &copy; <?= date('Y') ?> <?= e(SITE_NAME) ?>. All Rights Reserved. Single-Brand Luxury Fashion.
                </div>
                <div class="footer-payment-icons">
                    <span class="payment-badge">UPI</span>
                    <span class="payment-badge">VISA</span>
                    <span class="payment-badge">MASTERCARD</span>
                    <span class="payment-badge">AMEX</span>
                    <span class="payment-badge">NET BANKING</span>
                    <span class="payment-badge">CASH ON DELIVERY</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Master Vanilla JS Scripts -->
    <script src="<?= ASSETS_URL ?>/js/main.js"></script>
    <script src="<?= ASSETS_URL ?>/js/motion.js"></script>

    <!-- Extra Page-Specific Scripts -->
    <?php if (!empty($extraJs) && is_array($extraJs)): ?>
        <?php foreach ($extraJs as $jsPath): ?>
            <?php 
                $jsSrc = (strpos($jsPath, 'http') === 0 || strpos($jsPath, '//') === 0) 
                    ? $jsPath 
                    : BASE_URL . '/' . ltrim($jsPath, '/');
            ?>
            <script src="<?= e($jsSrc) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
