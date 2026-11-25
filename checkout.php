<?php
/**
 * YATIYA COUTURE - Luxury Multi-Step Checkout
 * Brand Aesthetics: Minimal, Ivory, Gold, Editorial
 * Mandatory Customer Authentication Enforced
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/cart-functions.php';
require_once __DIR__ . '/includes/checkout-functions.php';
require_once __DIR__ . '/includes/payment-functions.php';

$cartId = get_or_create_cart_id();
$customerId = is_logged_in() ? (int)$_SESSION['customer_id'] : null;
$context = get_checkout_context($cartId, $customerId);

// 1. If shopping bag is empty, redirect back to cart
if (empty($context['items'])) {
    set_flash('info', 'Your luxury shopping bag is currently empty. Please select creations to proceed.');
    redirect(BASE_URL . '/cart.php');
}

// 2. MANDATORY CUSTOMER AUTHENTICATION GUARD
// Unauthenticated customers must register or sign in before checkout
if (!is_logged_in() || empty($customerId)) {
    $_SESSION['intended_url'] = BASE_URL . '/checkout.php';
    set_flash('info', 'Please sign in or create an atelier account to proceed with secure checkout.');
    redirect(BASE_URL . '/login.php?redirect=checkout.php');
}

$pageTitle = 'Luxury Checkout | ' . SITE_NAME;
$activePage = 'checkout';
$hideSiteLoader = true; // Dismiss full-screen site loader immediately on transactional page
$extraCss = ['assets/css/checkout.css'];
$extraJs = [
    'https://checkout.razorpay.com/v1/checkout.js',
    'assets/js/checkout.js'
];

require_once __DIR__ . '/includes/header.php';

$totals = $context['totals'];
$customer = $context['customer'];
$addresses = $context['addresses'];
$defShip = $context['default_shipping'];
?>

<div class="checkout-wrapper">
    <div class="checkout-container">
        
        <!-- 5-Step Horizontal Progress Stepper -->
        <div class="checkout-stepper-wrap">
            <div class="checkout-stepper">
                <div class="stepper-progress-fill" style="width: 50%;"></div>
                
                <!-- 1. Bag (Completed) -->
                <a href="<?= BASE_URL ?>/cart.php" class="step-item completed" style="text-decoration: none;">
                    <div class="step-circle">&#10003;</div>
                    <div class="step-label">1. Bag</div>
                </a>

                <!-- 2. Account (Completed) -->
                <div class="step-item completed">
                    <div class="step-circle">&#10003;</div>
                    <div class="step-label">2. Account</div>
                </div>

                <!-- 3. Delivery (Active) -->
                <div class="step-item active">
                    <div class="step-circle">3</div>
                    <div class="step-label">3. Delivery</div>
                </div>

                <!-- 4. Payment (Pending) -->
                <div class="step-item">
                    <div class="step-circle">4</div>
                    <div class="step-label">4. Payment</div>
                </div>

                <!-- 5. Complete (Pending) -->
                <div class="step-item">
                    <div class="step-circle">5</div>
                    <div class="step-label">5. Complete</div>
                </div>
            </div>
        </div>

        <!-- Real-time Alert Notification Box -->
        <div id="checkoutAlertBox" class="alert" style="display: none; max-width: 900px; margin: 0 auto 24px;"></div>

        <!-- Inventory Warning Notice -->
        <?php if (!empty($context['stock_issues'])): ?>
            <div class="alert alert-danger" style="margin-bottom: 24px;">
                <strong>Inventory Notice:</strong><br>
                <?php foreach ($context['stock_issues'] as $issue): ?>
                    <div>&bull; <?= e($issue) ?></div>
                <?php endforeach; ?>
                <div style="margin-top: 8px;">
                    <a href="<?= BASE_URL ?>/cart.php" style="color: inherit; text-decoration: underline;">Return to Bag to adjust quantities &rarr;</a>
                </div>
            </div>
        <?php endif; ?>

        <form id="checkoutForm" method="POST" data-action-url="<?= BASE_URL ?>/actions/checkout_actions.php">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="checkout-grid">
                <!-- =======================================================
                     LEFT COLUMN: CHECKOUT STAGES
                     ======================================================= -->
                <div class="checkout-main">
                    
                    <!-- STAGE 2: Patron Identity & Account Verification -->
                    <div class="checkout-card" id="step-patron-account">
                        <div class="checkout-card-header">
                            <h2 class="checkout-card-title">
                                <span class="badge-num completed">&#10003;</span> Patron Identity & Account
                            </h2>
                            <span class="patron-verified-badge">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="margin-right:2px;"><path d="M20 6L9 17l-5-5"></path></svg>
                                Verified Client
                            </span>
                        </div>

                        <div class="patron-identity-bar">
                            <div class="patron-avatar">
                                <?= strtoupper(substr($customer['first_name'] ?? 'Y', 0, 1) . substr($customer['last_name'] ?? 'C', 0, 1)) ?>
                            </div>
                            <div class="patron-details">
                                <div class="patron-name"><?= e(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')) ?></div>
                                <div class="patron-meta">
                                    <span><?= e($customer['email'] ?? '') ?></span>
                                    <?php if (!empty($customer['phone'])): ?>
                                        &bull; <span><?= e($customer['phone']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <a href="<?= BASE_URL ?>/logout.php" class="patron-switch-link" title="Sign out to switch account">
                                Switch Account
                            </a>
                        </div>
                        <input type="hidden" name="customer_email" value="<?= e($customer['email'] ?? '') ?>">
                    </div>

                    <!-- STAGE 3: Delivery Destination -->
                    <div class="checkout-card" id="step-shipping">
                        <div class="checkout-card-header">
                            <h2 class="checkout-card-title">
                                <span class="badge-num">3</span> Delivery Destination
                            </h2>
                            <?php if (!empty($addresses)): ?>
                                <a href="#" id="toggleNewAddressBtn" class="address-toggle-link">
                                    + Add New Atelier Address
                                </a>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($addresses)): ?>
                            <!-- Saved Addresses Selection Cards -->
                            <div class="address-grid" id="savedAddressesGrid">
                                <?php foreach ($addresses as $addr): 
                                    $isSelected = ($defShip && $defShip['id'] === $addr['id']);
                                ?>
                                    <div class="address-card-choice <?= $isSelected ? 'selected' : '' ?>"
                                         data-id="<?= $addr['id'] ?>"
                                         data-name="<?= e($addr['full_name']) ?>"
                                         data-phone="<?= e($addr['phone']) ?>"
                                         data-line1="<?= e($addr['address_line1']) ?>"
                                         data-line2="<?= e($addr['address_line2']) ?>"
                                         data-landmark="<?= e($addr['landmark']) ?>"
                                         data-city="<?= e($addr['city']) ?>"
                                         data-state="<?= e($addr['state']) ?>"
                                         data-postal="<?= e($addr['postal_code']) ?>">
                                        
                                        <input type="radio" name="selected_address_id" value="<?= $addr['id'] ?>" <?= $isSelected ? 'checked' : '' ?>>
                                        <span class="address-type-tag"><?= strtoupper(e($addr['address_type'])) ?></span>
                                        <div class="address-recipient-name"><?= e($addr['full_name']) ?></div>
                                        <div class="address-details-body">
                                            <?= e($addr['address_line1']) ?><br>
                                            <?php if (!empty($addr['address_line2'])): ?>
                                                <?= e($addr['address_line2']) ?><br>
                                            <?php endif; ?>
                                            <?= e($addr['city']) ?>, <?= e($addr['state']) ?> &ndash; <?= e($addr['postal_code']) ?>
                                        </div>
                                        <div class="address-phone-line">
                                            Phone: <?= e($addr['phone']) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Detailed Shipping Address Form -->
                        <div id="newAddressForm" style="<?= (!empty($addresses)) ? 'display: none;' : '' ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required" for="shipping_full_name">Recipient Full Name</label>
                                    <input type="text" id="shipping_full_name" name="shipping_full_name" class="form-control" 
                                           value="<?= e($defShip['full_name'] ?? (($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? ''))) ?>" 
                                           placeholder="e.g. Maharani Gayatri Devi" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required" for="shipping_phone">Contact Phone (For Courier Coordination)</label>
                                    <input type="tel" id="shipping_phone" name="shipping_phone" class="form-control" 
                                           value="<?= e($defShip['phone'] ?? ($customer['phone'] ?? '')) ?>" 
                                           placeholder="+91 98765 43210" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label required" for="shipping_address_line1">Street Address / Suite / Atelier</label>
                                <input type="text" id="shipping_address_line1" name="shipping_address_line1" class="form-control" 
                                       placeholder="House/Apt No., Building Name, Street" 
                                       value="<?= e($defShip['address_line1'] ?? '') ?>" required>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="shipping_address_line2">Apartment / Floor / Wing (Optional)</label>
                                    <input type="text" id="shipping_address_line2" name="shipping_address_line2" class="form-control" 
                                           placeholder="Suite 4B, Floor 2" value="<?= e($defShip['address_line2'] ?? '') ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="shipping_landmark">Prominent Landmark</label>
                                    <input type="text" id="shipping_landmark" name="shipping_landmark" class="form-control" 
                                           placeholder="E.g., Adjacent to Diplomatic Enclave" value="<?= e($defShip['landmark'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required" for="shipping_city">City</label>
                                    <input type="text" id="shipping_city" name="shipping_city" class="form-control" 
                                           placeholder="New Delhi" value="<?= e($defShip['city'] ?? '') ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required" for="shipping_state">State / Province</label>
                                    <input type="text" id="shipping_state" name="shipping_state" class="form-control" 
                                           placeholder="Delhi" value="<?= e($defShip['state'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label required" for="shipping_postal_code">Postal / PIN Code</label>
                                    <input type="text" id="shipping_postal_code" name="shipping_postal_code" class="form-control" 
                                           maxlength="6" placeholder="110001" value="<?= e($defShip['postal_code'] ?? '') ?>" required>
                                    <div id="pinCodeStatus" class="pin-status-text"></div>
                                </div>
                                <div class="form-group">
                                    <label class="form-label required" for="shipping_country">Country</label>
                                    <input type="text" id="shipping_country" name="shipping_country" class="form-control" value="India" readonly style="background:#FBF9F5; cursor:not-allowed;">
                                </div>
                            </div>

                            <!-- Save Address to Profile Checkbox -->
                            <div style="margin-top: 12px;">
                                <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; color: #24211E;">
                                    <input type="checkbox" name="save_address" value="1" checked style="accent-color: #C6A15B; width: 16px; height: 16px;">
                                    <span>Save this delivery address to my atelier address book for future curations</span>
                                </label>
                            </div>
                        </div>

                        <!-- Separate Billing Address Option -->
                        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #ECE7DE;">
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; cursor: pointer; color: #24211E;">
                                <input type="checkbox" id="billingSameAsShipping" name="billing_same_as_shipping" value="1" checked style="accent-color: #C6A15B; width: 16px; height: 16px;">
                                <span>Billing address is identical to delivery destination</span>
                            </label>
                        </div>

                        <div id="billingAddressSection" style="display: none; margin-top: 20px; padding: 20px; background: #FCFBF9; border: 1px solid #E5E0D5; border-radius: 4px;">
                            <h4 style="font-size: 14px; font-weight: 700; margin-bottom: 14px; color: #24211E;">Billing Address Details</h4>
                            <div class="form-group">
                                <label class="form-label" for="billing_full_name">Billing Full Name</label>
                                <input type="text" id="billing_full_name" name="billing_full_name" class="form-control" placeholder="Tax / Invoice Recipient Name">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="billing_address_line1">Billing Street Address</label>
                                <input type="text" id="billing_address_line1" name="billing_address_line1" class="form-control" placeholder="House/Apt No., Building, Street">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="billing_city">City</label>
                                    <input type="text" id="billing_city" name="billing_city" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="billing_state">State</label>
                                    <input type="text" id="billing_state" name="billing_state" class="form-control">
                                </div>
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label class="form-label" for="billing_postal_code">Postal Code</label>
                                    <input type="text" id="billing_postal_code" name="billing_postal_code" class="form-control" maxlength="6">
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="billing_country">Country</label>
                                    <input type="text" id="billing_country" name="billing_country" class="form-control" value="India" readonly style="background:#FBF9F5;">
                                </div>
                            </div>
                        </div>

                        <!-- Atelier Special Delivery Instructions -->
                        <div class="form-group" style="margin-top: 20px;">
                            <label class="form-label" for="customer_notes">Special Instructions for Concierge / White-Glove Courier</label>
                            <textarea id="customer_notes" name="customer_notes" class="form-control" rows="2" 
                                      placeholder="Gate access code, preferred delivery hour, luxury gift wrapping inscription..."></textarea>
                        </div>
                    </div>

                    <!-- STAGE 4: Payment Method Selection -->
                    <div class="checkout-card" id="step-payment">
                        <div class="checkout-card-header">
                            <h2 class="checkout-card-title">
                                <span class="badge-num">4</span> Payment Settlement
                            </h2>
                            <span style="font-size: 11px; color: #777168; display: flex; align-items: center; gap: 4px; font-weight: 600;">
                                🔒 256-bit Bank Grade Encryption
                            </span>
                        </div>

                        <div class="payment-methods-list">
                            <!-- Option 1: Razorpay Standard Online -->
                            <div class="payment-method-card selected" id="method-online">
                                <input type="radio" name="payment_method" value="online" class="payment-radio" checked>
                                <div class="payment-details">
                                    <div class="payment-title">
                                        Online Payment via Razorpay
                                        <span class="payment-badge instant">Instant Dispatch</span>
                                    </div>
                                    <div class="payment-desc">
                                        Pay securely using UPI (Google Pay, PhonePe, Paytm), Credit & Debit Cards (Visa, Mastercard, Amex), Net Banking, or Luxury EMI.
                                    </div>
                                </div>
                            </div>

                            <!-- Option 2: Cash on Delivery (COD) -->
                            <?php $codDisabled = !$context['is_cod_eligible']; ?>
                            <div class="payment-method-card <?= $codDisabled ? 'disabled' : '' ?>" id="method-cod">
                                <input type="radio" name="payment_method" value="cod" class="payment-radio" <?= $codDisabled ? 'disabled' : '' ?>>
                                <div class="payment-details">
                                    <div class="payment-title">
                                        Cash on Delivery (White-Glove Collection)
                                        <?php if ($codDisabled): ?>
                                            <span class="payment-badge" style="background:#FFEBEE; color:#C62828;">
                                                Not Available for orders over <?= format_price($context['cod_max_amount']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="payment-badge">Verified Delivery</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="payment-desc">
                                        Pay comfortably in cash or via mobile UPI upon hand delivery by our bonded logistics partner.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- =======================================================
                     RIGHT COLUMN: STICKY ORDER SUMMARY
                     ======================================================= -->
                <div class="checkout-sidebar">
                    <div class="checkout-card summary-card">
                        <div class="summary-heading">
                            <span>Bag Summary</span>
                            <span style="font-size: 13px; color: #777168; font-weight: normal;">
                                <?= count($context['items']) ?> <?= count($context['items']) === 1 ? 'Creation' : 'Creations' ?>
                            </span>
                        </div>

                        <!-- Line Items Miniature Previews -->
                        <div class="summary-items-list">
                            <?php foreach ($context['items'] as $item): ?>
                                <div class="summary-item">
                                    <img src="<?= e($item['primary_image'] ?: 'assets/images/placeholder.jpg') ?>" class="summary-item-img" alt="<?= e($item['product_name']) ?>">
                                    <div class="summary-item-details">
                                        <div class="summary-item-name"><?= e($item['product_name']) ?></div>
                                        <div class="summary-item-variant">
                                            Size: <?= e($item['size']) ?>
                                            <?php if (!empty($item['color'])): ?>
                                                &bull; Color: <?= e($item['color']) ?>
                                            <?php endif; ?>
                                            &bull; Qty: <?= (int)$item['quantity'] ?>
                                        </div>
                                        <div class="summary-item-price"><?= format_price($item['line_total']) ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Financial Breakdown -->
                        <div class="financial-row">
                            <span>Subtotal:</span>
                            <span><?= format_price($totals['subtotal']) ?></span>
                        </div>

                        <?php if ($totals['coupon_discount'] > 0): ?>
                            <div class="financial-row discount-row">
                                <span>Privilege Discount (<?= e($totals['applied_coupon']['code'] ?? '') ?>):</span>
                                <span>-<?= format_price($totals['coupon_discount']) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="financial-row">
                            <span>White-Glove Shipping:</span>
                            <span>
                                <?= ($totals['shipping_fee'] == 0) ? '<span style="color:#C6A15B; font-weight:700;">COMPLIMENTARY</span>' : format_price($totals['shipping_fee']) ?>
                            </span>
                        </div>

                        <div class="financial-row total-row">
                            <span>Grand Total:</span>
                            <span style="color: #C6A15B; font-size: 22px;">
                                <?= format_price($totals['grand_total']) ?>
                            </span>
                        </div>
                        <div class="tax-inclusive-notice">
                            Inclusive of all applicable luxury taxes & GST (12%)
                        </div>

                        <!-- Place Order Button -->
                        <button type="submit" id="placeOrderBtn" class="btn-place-order" <?= empty($context['stock_ok']) ? 'disabled' : '' ?>>
                            Place Order & Pay <span style="font-size: 16px;">&rarr;</span>
                        </button>

                        <!-- Trust Guarantees -->
                        <div class="trust-badges-row">
                            <div class="trust-badge-item">
                                <span class="trust-badge-icon">✦</span>
                                <span>100% Authentic Atelier</span>
                            </div>
                            <div class="trust-badge-item">
                                <span class="trust-badge-icon">❖</span>
                                <span>7-Day Complimentary Exchange</span>
                            </div>
                            <div class="trust-badge-item">
                                <span class="trust-badge-icon">🛡</span>
                                <span>Bonded Secure Transit</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
