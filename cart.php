<?php
/**
 * YATIYA COUTURE - Master Shopping Cart Experience
 * Single-brand luxury fashion e-commerce bag supporting guests & authenticated customers
 * Pure Core PHP & Vanilla JavaScript
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart-functions.php';

// If POST request is submitted directly to cart.php, forward to actions controller
if (is_post()) {
    require __DIR__ . '/actions/cart_actions.php';
    exit;
}

$pageTitle = 'Shopping Bag';
$pageDescription = 'Review your curated haute couture selections in your Yatiya Couture shopping bag.';
$activePage = 'cart';

// Fetch Active Cart
$cartId = get_or_create_cart_id();
$activeItems = get_cart_items($cartId, false);
$savedItems = get_cart_items($cartId, true);
$totals = calculate_cart_totals($activeItems);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Cart Stylesheet -->
<link rel="stylesheet" href="<?= ASSETS_URL ?>/css/cart.css">

<main class="cart-main-wrapper" id="cartApp">
    <div class="container">
        
        <!-- Flash Alert Messages -->
        <?= render_flash() ?>

        <!-- Luxury Breadcrumb Navigation -->
        <nav class="cart-breadcrumb" aria-label="Breadcrumb">
            <ol>
                <li><a href="<?= BASE_URL ?>/index.php">Maison Home</a></li>
                <li class="separator">/</li>
                <li aria-current="page">Shopping Bag</li>
            </ol>
        </nav>

        <?php if (empty($activeItems) && empty($savedItems)): ?>
            <!-- =========================================================
                 1. LUXURY EMPTY CART STATE
                 ========================================================= -->
            <section class="empty-cart-section" aria-label="Empty Shopping Bag">
                <div class="empty-cart-inner">
                    <div class="empty-cart-icon-wrap">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                    </div>
                    <span class="empty-cart-eyebrow">Atelier Wardrobe</span>
                    <h1 class="empty-cart-heading">Your Shopping Bag is Currently Empty</h1>
                    <p class="empty-cart-text">
                        Discover the rare artistry of bespoke handwoven silks, royal lehengas, hand-embroidered bandhgalas, and fine atelier accessories crafted exclusively by Yatiya Couture.
                    </p>
                    <div class="empty-cart-actions">
                        <a href="<?= BASE_URL ?>/shop.php" class="btn btn-gold btn-lg">Explore All Collections</a>
                        <a href="<?= BASE_URL ?>/wishlist.php" class="btn btn-outline btn-lg">View Saved Wishlist</a>
                    </div>

                    <!-- Curated Categories Quick Jump -->
                    <div class="empty-cart-curations">
                        <span class="curation-label">Explore by Haute Couture Salon:</span>
                        <div class="curation-tags">
                            <a href="<?= BASE_URL ?>/shop.php?category=women">Women's Salon</a>
                            <a href="<?= BASE_URL ?>/shop.php?category=men">Men's Privé</a>
                            <a href="<?= BASE_URL ?>/shop.php?category=footwear">Artisanal Footwear</a>
                            <a href="<?= BASE_URL ?>/shop.php?category=jewellery">Fine Jewellery</a>
                            <a href="<?= BASE_URL ?>/shop.php?category=new-arrivals">New Autumn / Winter</a>
                        </div>
                    </div>
                </div>
            </section>

        <?php else: ?>
            <!-- =========================================================
                 2. ACTIVE SHOPPING BAG (ITEMS LIST + ORDER SUMMARY)
                 ========================================================= -->
            <div class="cart-header-row">
                <h1 class="cart-title">
                    Shopping Bag
                    <span class="cart-items-counter">(<?= $totals['item_count'] ?> <?= $totals['item_count'] === 1 ? 'Creation' : 'Creations' ?>)</span>
                </h1>
                <a href="<?= BASE_URL ?>/shop.php" class="continue-shopping-link">
                    &larr; Continue Exploring Collections
                </a>
            </div>

            <!-- Free Shipping Progress Indicator -->
            <div class="free-shipping-bar-card">
                <div class="free-shipping-icon">✨</div>
                <div class="free-shipping-content">
                    <?php if ($totals['is_free_shipping']): ?>
                        <div class="shipping-status-line">
                            <strong>Complimentary White-Glove Delivery Unlocked!</strong>
                            <span>Your order qualifies for insured, zero-fee doorstep delivery anywhere in India.</span>
                        </div>
                        <div class="shipping-progress-track">
                            <div class="shipping-progress-fill unlocked" style="width: 100%;"></div>
                        </div>
                    <?php else: ?>
                        <?php 
                            $pct = min(100, round(($totals['subtotal'] / $totals['free_shipping_threshold']) * 100)); 
                        ?>
                        <div class="shipping-status-line">
                            <span>Add <strong><?= format_price($totals['remaining_for_free_shipping']) ?></strong> more to enjoy <strong>Complimentary White-Glove Delivery</strong> (Threshold: <?= format_price($totals['free_shipping_threshold'], false) ?>).</span>
                        </div>
                        <div class="shipping-progress-track">
                            <div class="shipping-progress-fill" style="width: <?= $pct ?>%;"></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="cart-layout-grid">
                
                <!-- Left Column: Line Items List -->
                <div class="cart-items-column">
                    <?php if (!empty($activeItems)): ?>
                        <div class="cart-items-table-wrap">
                            <div class="cart-table-header">
                                <span class="col-item">Creations (<?= count($activeItems) ?>)</span>
                                <span class="col-price">Price</span>
                                <span class="col-qty">Quantity</span>
                                <span class="col-total">Subtotal</span>
                            </div>

                            <div class="cart-items-list" id="cartItemsList">
                                <?php foreach ($activeItems as $item): ?>
                                    <div class="cart-item-row" data-item-id="<?= $item['id'] ?>" id="cartRow_<?= $item['id'] ?>">
                                        
                                        <!-- Item Image & Details -->
                                        <div class="item-primary-col">
                                            <a href="<?= BASE_URL ?>/product.php?slug=<?= e($item['product_slug']) ?>" class="item-thumb-link">
                                                <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['product_name']) ?>" class="item-thumb" loading="lazy">
                                            </a>
                                            <div class="item-meta">
                                                <span class="item-atelier-tag">Atelier Selection</span>
                                                <h3 class="item-title">
                                                    <a href="<?= BASE_URL ?>/product.php?slug=<?= e($item['product_slug']) ?>">
                                                        <?= e($item['product_name']) ?>
                                                    </a>
                                                </h3>
                                                <div class="item-variant-spec">
                                                    <span class="spec-pill">
                                                        <strong>Size:</strong> <?= e($item['size']) ?>
                                                    </span>
                                                    <span class="spec-pill">
                                                        <span class="color-dot" style="background-color: <?= e($item['color_code']) ?>;"></span>
                                                        <strong>Color:</strong> <?= e($item['color']) ?>
                                                    </span>
                                                    <span class="spec-sku">SKU: <?= e($item['sku']) ?></span>
                                                </div>

                                                <!-- Stock Feedback -->
                                                <div class="item-stock-feedback">
                                                    <?php if ($item['is_in_stock']): ?>
                                                        <?php if ($item['stock_available'] <= 3): ?>
                                                            <span class="stock-pill stock-limited">
                                                                <span class="stock-dot"></span> Only <?= $item['stock_available'] ?> available
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="stock-pill stock-available">
                                                                <span class="stock-dot"></span> In Stock &bull; Ready to Ship
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="stock-pill stock-out">
                                                            <span class="stock-dot"></span> Sold Out from Atelier
                                                        </span>
                                                    <?php endif; ?>
                                                </div>

                                                <!-- Secondary Actions: Save for Later & Remove (Mobile Visible) -->
                                                <div class="item-secondary-actions">
                                                    <form action="<?= BASE_URL ?>/actions/cart_actions.php" method="POST" class="inline-action-form">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="save_for_later">
                                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                        <button type="submit" class="link-btn save-for-later-btn">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                                                            </svg>
                                                            Save for Later
                                                        </button>
                                                    </form>

                                                    <span class="action-divider">&bull;</span>

                                                    <form action="<?= BASE_URL ?>/actions/cart_actions.php" method="POST" class="inline-action-form">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="remove">
                                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                        <button type="submit" class="link-btn remove-item-btn" onclick="return confirm('Remove this luxury creation from your shopping bag?');">
                                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                            </svg>
                                                            Remove
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Unit Price -->
                                        <div class="item-price-col">
                                            <span class="col-mobile-label">Unit Price:</span>
                                            <span class="price-current"><?= format_price($item['unit_price']) ?></span>
                                            <?php if ($item['unit_original_price'] > $item['unit_price']): ?>
                                                <span class="price-original"><?= format_price($item['unit_original_price']) ?></span>
                                                <span class="price-discount-tag"><?= $item['discount_percentage'] ?>% OFF</span>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Quantity Stepper -->
                                        <div class="item-qty-col">
                                            <span class="col-mobile-label">Quantity:</span>
                                            <form action="<?= BASE_URL ?>/actions/cart_actions.php" method="POST" class="cart-qty-form">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="update_qty">
                                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                <div class="qty-stepper">
                                                    <button type="button" class="qty-btn cart-qty-decrement" aria-label="Decrease">&minus;</button>
                                                    <input 
                                                        type="number" 
                                                        name="quantity" 
                                                        class="qty-input cart-qty-input" 
                                                        value="<?= $item['quantity'] ?>" 
                                                        min="1" 
                                                        max="<?= max(1, $item['stock_available']) ?>" 
                                                        data-item-id="<?= $item['id'] ?>"
                                                        readonly
                                                    >
                                                    <button type="button" class="qty-btn cart-qty-increment" aria-label="Increase">&plus;</button>
                                                </div>
                                            </form>
                                        </div>

                                        <!-- Line Total -->
                                        <div class="item-total-col">
                                            <span class="col-mobile-label">Line Total:</span>
                                            <span class="line-total-amount" id="lineTotal_<?= $item['id'] ?>">
                                                <?= format_price($item['line_total']) ?>
                                            </span>
                                        </div>

                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-active-cart-notice">
                            <p>All items in your bag are currently saved for later.</p>
                            <a href="<?= BASE_URL ?>/shop.php" class="btn btn-outline">Explore Products</a>
                        </div>
                    <?php endif; ?>

                    <!-- Continue Shopping Link -->
                    <div class="cart-bottom-actions">
                        <a href="<?= BASE_URL ?>/shop.php" class="btn btn-outline">
                            &larr; Discover More Haute Couture
                        </a>
                    </div>

                    <!-- =========================================================
                         SAVED FOR LATER SECTION
                         ========================================================= -->
                    <?php if (!empty($savedItems)): ?>
                        <section class="saved-for-later-section" aria-label="Saved For Later">
                            <div class="saved-header">
                                <h2 class="saved-title">
                                    Saved for Later
                                    <span class="saved-counter">(<?= count($savedItems) ?> <?= count($savedItems) === 1 ? 'Creation' : 'Creations' ?>)</span>
                                </h2>
                                <p class="saved-subtitle">These luxury selections are stored in your session and will not be purchased in this checkout.</p>
                            </div>

                            <div class="saved-items-grid">
                                <?php foreach ($savedItems as $sItem): ?>
                                    <div class="saved-item-card">
                                        <a href="<?= BASE_URL ?>/product.php?slug=<?= e($sItem['product_slug']) ?>" class="saved-thumb-link">
                                            <img src="<?= e($sItem['image_url']) ?>" alt="<?= e($sItem['product_name']) ?>" class="saved-thumb" loading="lazy">
                                        </a>
                                        <div class="saved-card-body">
                                            <h3 class="saved-product-name">
                                                <a href="<?= BASE_URL ?>/product.php?slug=<?= e($sItem['product_slug']) ?>">
                                                    <?= e($sItem['product_name']) ?>
                                                </a>
                                            </h3>
                                            <div class="saved-variants">
                                                <span>Size: <?= e($sItem['size']) ?></span> &bull; 
                                                <span>Color: <?= e($sItem['color']) ?></span>
                                            </div>
                                            <div class="saved-price">
                                                <?= format_price($sItem['unit_price']) ?>
                                            </div>

                                            <div class="saved-card-actions">
                                                <!-- Move to Cart Form -->
                                                <form action="<?= BASE_URL ?>/actions/cart_actions.php" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="move_to_cart">
                                                    <input type="hidden" name="item_id" value="<?= $sItem['id'] ?>">
                                                    <button type="submit" class="btn btn-gold btn-sm btn-block">
                                                        Move to Bag
                                                    </button>
                                                </form>

                                                <!-- Remove Form -->
                                                <form action="<?= BASE_URL ?>/actions/cart_actions.php" method="POST">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="action" value="remove">
                                                    <input type="hidden" name="item_id" value="<?= $sItem['id'] ?>">
                                                    <button type="submit" class="link-btn remove-saved-btn">
                                                        Remove
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endif; ?>

                </div>

                <!-- Right Column: Order Summary Card -->
                <div class="cart-summary-column">
                    <div class="order-summary-card">
                        <h2 class="summary-card-title">Order Summary</h2>

                        <!-- Financial Breakdown -->
                        <div class="summary-lines-wrap">
                            <div class="summary-line">
                                <span class="summary-label">Bag Subtotal (<?= $totals['item_count'] ?> items)</span>
                                <span class="summary-value" id="summarySubtotal"><?= format_price($totals['subtotal']) ?></span>
                            </div>

                            <!-- Applied Coupon Discount -->
                            <div class="summary-line coupon-line" id="summaryCouponRow" style="<?= ($totals['coupon_discount'] > 0) ? '' : 'display:none;' ?>">
                                <span class="summary-label">
                                    Privilege Discount
                                    <?php if (!empty($totals['applied_coupon']['code'])): ?>
                                        <span class="applied-coupon-code">(<?= e($totals['applied_coupon']['code']) ?>)</span>
                                    <?php endif; ?>
                                </span>
                                <span class="summary-value discount-text" id="summaryCouponDiscount">
                                    &minus; <?= format_price($totals['coupon_discount']) ?>
                                </span>
                            </div>

                            <!-- White-Glove Shipping -->
                            <div class="summary-line">
                                <span class="summary-label">
                                    White-Glove Shipping
                                    <span class="shipping-info-tooltip" title="Free insured delivery on orders over ₹2,999">ℹ</span>
                                </span>
                                <span class="summary-value" id="summaryShipping">
                                    <?php if ($totals['is_free_shipping']): ?>
                                        <span class="complimentary-tag">COMPLIMENTARY</span>
                                    <?php else: ?>
                                        <?= format_price($totals['shipping_fee']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="summary-divider"></div>

                            <!-- Grand Payable Total -->
                            <div class="summary-line total-line">
                                <span class="summary-label">Grand Total</span>
                                <span class="summary-value grand-total-value" id="summaryGrandTotal">
                                    <?= format_price($totals['grand_total']) ?>
                                </span>
                            </div>

                            <p class="summary-tax-note">Inclusive of all luxury taxes & atelier dispatch insurances.</p>

                            <?php if ($totals['total_savings'] > 0): ?>
                                <div class="savings-callout" id="summarySavingsBox">
                                    ✨ You are saving <strong><?= format_price($totals['total_savings']) ?></strong> on this bespoke order.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- =========================================================
                             COUPON ENGINE FORM
                             ========================================================= -->
                        <div class="coupon-box-wrapper">
                            <h3 class="coupon-box-title">Apply Privilege Code</h3>

                            <?php if (!empty($totals['applied_coupon'])): ?>
                                <!-- Active Coupon Display -->
                                <div class="active-coupon-badge">
                                    <div class="coupon-badge-info">
                                        <span class="coupon-tag-icon">🏷️</span>
                                        <div>
                                            <strong class="applied-code"><?= e($totals['applied_coupon']['code']) ?></strong>
                                            <p class="coupon-desc"><?= e($totals['applied_coupon']['description'] ?? 'Privilege benefit active.') ?></p>
                                        </div>
                                    </div>
                                    <form action="<?= BASE_URL ?>/actions/apply_coupon.php" method="POST">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="remove">
                                        <button type="submit" class="coupon-remove-btn" title="Remove code">&times;</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <!-- Coupon Input Form -->
                                <form action="<?= BASE_URL ?>/actions/apply_coupon.php" method="POST" class="coupon-form" id="couponForm">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="apply">
                                    <div class="coupon-input-group">
                                        <input 
                                            type="text" 
                                            name="coupon_code" 
                                            id="couponCodeInput" 
                                            class="coupon-input" 
                                            placeholder="Enter Code (e.g. YATIYA10)" 
                                            autocomplete="off"
                                            required
                                        >
                                        <button type="submit" class="btn btn-gold btn-coupon-apply" id="applyCouponBtn">
                                            Apply
                                        </button>
                                    </div>
                                </form>
                                <div class="available-coupons-hint">
                                    <span class="hint-eyebrow">Available Privileges:</span>
                                    <div class="hint-tags">
                                        <span class="coupon-chip" onclick="document.getElementById('couponCodeInput').value='YATIYA10'">YATIYA10 (10% Off)</span>
                                        <span class="coupon-chip" onclick="document.getElementById('couponCodeInput').value='HAUTE500'">HAUTE500 (₹500 Off)</span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($totals['coupon_notice'])): ?>
                                <p class="coupon-notice-msg"><?= e($totals['coupon_notice']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Checkout CTA -->
                        <div class="checkout-cta-wrap">
                            <?php if (!empty($activeItems)): ?>
                                <a href="<?= BASE_URL ?>/checkout.php" class="btn btn-gold btn-lg btn-block checkout-btn">
                                    Proceed to Secure Checkout
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="5" y1="12" x2="19" y2="12"></line>
                                        <polyline points="12 5 19 12 12 19"></polyline>
                                    </svg>
                                </a>
                            <?php else: ?>
                                <button type="button" class="btn btn-gold btn-lg btn-block checkout-btn" disabled>
                                    Shopping Bag Empty
                                </button>
                            <?php endif; ?>
                        </div>

                        <!-- Trust Guarantees -->
                        <div class="summary-trust-grid">
                            <div class="trust-pill">
                                <span class="trust-icon">🛡️</span>
                                <span>Silk Mark 100% Certified Authentic</span>
                            </div>
                            <div class="trust-pill">
                                <span class="trust-icon">🔒</span>
                                <span>256-Bit Encrypted Secure Checkout</span>
                            </div>
                            <div class="trust-pill">
                                <span class="trust-icon">✈️</span>
                                <span>White-Glove Insured Delivery</span>
                            </div>
                            <div class="trust-pill">
                                <span class="trust-icon">🔄</span>
                                <span>7-Day Complimentary Atelier Exchange</span>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        <?php endif; ?>

    </div>
</main>

<!-- Cart Specific Vanilla JavaScript -->
<script src="<?= ASSETS_URL ?>/js/cart.js"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

