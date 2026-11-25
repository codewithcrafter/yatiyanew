<?php
/**
 * YATIYA COUTURE - Privilege Coupon Engine Controller
 * Validates and applies or removes coupons against active MySQL store rules
 * Strictly server-side calculation and CSRF validation
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart-functions.php';

// Enforce POST method
if (!is_post()) {
    set_flash('error', 'Invalid request method. Coupon actions require secure form submission.');
    redirect(BASE_URL . '/cart.php');
}

// Enforce CSRF verification
$submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verify_csrf_token($submittedToken)) {
    if (is_ajax() || isset($_POST['ajax'])) {
        json_response([
            'success' => false,
            'message' => 'Security token expired or invalid. Please refresh and try again.'
        ], 403);
    }
    set_flash('error', 'Security verification failed. Please try again.');
    redirect(BASE_URL . '/cart.php');
}

$action = clean_input($_POST['action'] ?? 'apply');
$isAjax = is_ajax() || (isset($_POST['ajax']) && $_POST['ajax'] == '1');

if ($action === 'remove' || $action === 'remove_coupon') {
    remove_applied_coupon();

    if ($isAjax) {
        $cartId = get_or_create_cart_id();
        $activeItems = get_cart_items($cartId, false);
        $totals = calculate_cart_totals($activeItems);

        json_response([
            'success' => true,
            'message' => 'Privilege code removed from your order.',
            'totals' => [
                'subtotal_formatted' => format_price($totals['subtotal']),
                'coupon_discount_formatted' => format_price($totals['coupon_discount']),
                'shipping_fee_formatted' => $totals['is_free_shipping'] ? 'COMPLIMENTARY' : format_price($totals['shipping_fee']),
                'grand_total_formatted' => format_price($totals['grand_total']),
                'total_savings_formatted' => format_price($totals['total_savings'])
            ]
        ]);
    }

    set_flash('info', 'Privilege code removed from your order.');
    redirect(BASE_URL . '/cart.php');
}

// Apply coupon
$couponCode = clean_input($_POST['coupon_code'] ?? '');
if (empty($couponCode)) {
    if ($isAjax) json_response(['success' => false, 'message' => 'Please enter a coupon code.'], 400);
    set_flash('error', 'Please enter a valid coupon code.');
    redirect(BASE_URL . '/cart.php');
}

// Compute real server-side cart subtotal
$cartId = get_or_create_cart_id();
$activeItems = get_cart_items($cartId, false);
$cartTotals = calculate_cart_totals($activeItems);
$cartSubtotal = $cartTotals['subtotal'];

if ($cartSubtotal <= 0) {
    if ($isAjax) json_response(['success' => false, 'message' => 'Your Shopping Bag is empty. Add creations before applying a privilege code.'], 400);
    set_flash('warning', 'Your Shopping Bag is empty. Add creations before applying a privilege code.');
    redirect(BASE_URL . '/cart.php');
}

$customerId = $_SESSION['customer_id'] ?? null;
$result = validate_and_apply_coupon($couponCode, $cartSubtotal, $customerId);

if ($result['success']) {
    if ($isAjax) {
        // Re-calculate totals with coupon applied
        $updatedTotals = calculate_cart_totals($activeItems);

        json_response([
            'success' => true,
            'message' => $result['message'],
            'discount' => $result['discount'],
            'totals' => [
                'subtotal_formatted' => format_price($updatedTotals['subtotal']),
                'coupon_discount_formatted' => format_price($updatedTotals['coupon_discount']),
                'shipping_fee_formatted' => $updatedTotals['is_free_shipping'] ? 'COMPLIMENTARY' : format_price($updatedTotals['shipping_fee']),
                'grand_total_formatted' => format_price($updatedTotals['grand_total']),
                'total_savings_formatted' => format_price($updatedTotals['total_savings'])
            ]
        ]);
    }
    set_flash('success', $result['message']);
} else {
    if ($isAjax) {
        json_response(['success' => false, 'message' => $result['message']], 400);
    }
    set_flash('error', $result['message']);
}

redirect(BASE_URL . '/cart.php');

