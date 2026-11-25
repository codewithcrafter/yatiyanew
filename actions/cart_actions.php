<?php
/**
 * YATIYA COUTURE - Cart Actions Controller
 * Handles Add to Bag, Update Quantity, Remove Item, Save for Later, and Move to Cart
 * Strictly POST with CSRF verification and server-side validation
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart-functions.php';

// Enforce POST method for all state-changing cart operations
if (!is_post()) {
    set_flash('error', 'Invalid request method. Cart operations require secure submission.');
    redirect(BASE_URL . '/cart.php');
}

// Enforce CSRF Protection
$submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verify_csrf_token($submittedToken)) {
    if (is_ajax() || isset($_POST['ajax'])) {
        json_response([
            'success' => false,
            'message' => 'Security token expired or invalid. Please refresh the page and try again.'
        ], 403);
    }
    set_flash('error', 'Security verification failed. Please try again.');
    redirect(BASE_URL . '/cart.php');
}

$action = clean_input($_POST['action'] ?? '');
$isAjax = is_ajax() || (isset($_POST['ajax']) && $_POST['ajax'] == '1');

switch ($action) {
    case 'add':
        $productId = (int)($_POST['product_id'] ?? 0);
        $variantId = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $intent = clean_input($_POST['intent'] ?? 'add_to_cart');

        if ($productId <= 0) {
            if ($isAjax) json_response(['success' => false, 'message' => 'Invalid product selection.'], 400);
            set_flash('error', 'Invalid product selected.');
            redirect(BASE_URL . '/shop.php');
        }

        $res = add_to_cart($productId, $variantId, $quantity);

        if ($res['success']) {
            if ($isAjax) {
                json_response([
                    'success' => true,
                    'message' => $res['message'],
                    'cart_count' => get_cart_count()
                ]);
            }
            set_flash('success', $res['message']);
            if ($intent === 'buy_now') {
                redirect(BASE_URL . '/cart.php');
            } else {
                // If referer is available and user stayed on product page, redirect to cart or referer
                redirect(BASE_URL . '/cart.php');
            }
        } else {
            if ($isAjax) {
                json_response(['success' => false, 'message' => $res['message']], 400);
            }
            set_flash('error', $res['message']);
            $referer = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/cart.php');
            redirect($referer);
        }
        break;

    case 'update_qty':
        $itemId = (int)($_POST['item_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);

        if ($itemId <= 0) {
            if ($isAjax) json_response(['success' => false, 'message' => 'Invalid item.'], 400);
            set_flash('error', 'Invalid cart item.');
            redirect(BASE_URL . '/cart.php');
        }

        $res = update_cart_quantity($itemId, $quantity);

        if ($isAjax) {
            $cartId = get_or_create_cart_id();
            $activeItems = get_cart_items($cartId, false);
            $totals = calculate_cart_totals($activeItems);

            json_response([
                'success' => $res['success'],
                'message' => $res['message'],
                'clamped' => $res['clamped'] ?? false,
                'new_quantity' => $res['quantity'] ?? 0,
                'cart_count' => get_cart_count(),
                'totals' => [
                    'subtotal_formatted' => format_price($totals['subtotal']),
                    'shipping_fee_formatted' => $totals['is_free_shipping'] ? 'COMPLIMENTARY' : format_price($totals['shipping_fee']),
                    'coupon_discount_formatted' => format_price($totals['coupon_discount']),
                    'grand_total_formatted' => format_price($totals['grand_total']),
                    'is_free_shipping' => $totals['is_free_shipping'],
                    'remaining_for_free_shipping' => format_price($totals['remaining_for_free_shipping'])
                ]
            ]);
        }

        if (!empty($res['clamped'])) {
            set_flash('warning', $res['message']);
        } else {
            set_flash($res['success'] ? 'success' : 'error', $res['message']);
        }
        redirect(BASE_URL . '/cart.php');
        break;

    case 'remove':
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId <= 0) {
            if ($isAjax) json_response(['success' => false, 'message' => 'Invalid item.'], 400);
            set_flash('error', 'Invalid cart item.');
            redirect(BASE_URL . '/cart.php');
        }

        $removed = remove_cart_item($itemId);

        if ($isAjax) {
            $cartId = get_or_create_cart_id();
            $activeItems = get_cart_items($cartId, false);
            $totals = calculate_cart_totals($activeItems);

            json_response([
                'success' => $removed,
                'message' => $removed ? 'Creation removed from Shopping Bag.' : 'Unable to remove creation.',
                'cart_count' => get_cart_count(),
                'totals' => [
                    'subtotal_formatted' => format_price($totals['subtotal']),
                    'shipping_fee_formatted' => $totals['is_free_shipping'] ? 'COMPLIMENTARY' : format_price($totals['shipping_fee']),
                    'coupon_discount_formatted' => format_price($totals['coupon_discount']),
                    'grand_total_formatted' => format_price($totals['grand_total'])
                ]
            ]);
        }

        set_flash($removed ? 'success' : 'error', $removed ? 'Creation removed from Shopping Bag.' : 'Unable to remove creation.');
        redirect(BASE_URL . '/cart.php');
        break;

    case 'save_for_later':
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId <= 0) {
            if ($isAjax) json_response(['success' => false, 'message' => 'Invalid item.'], 400);
            set_flash('error', 'Invalid cart item.');
            redirect(BASE_URL . '/cart.php');
        }

        $saved = save_item_for_later($itemId);

        if ($isAjax) {
            json_response([
                'success' => $saved,
                'message' => $saved ? 'Creation saved for later consideration.' : 'Unable to save creation.',
                'cart_count' => get_cart_count()
            ]);
        }

        set_flash($saved ? 'success' : 'error', $saved ? 'Creation saved for later consideration.' : 'Unable to save creation.');
        redirect(BASE_URL . '/cart.php');
        break;

    case 'move_to_cart':
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId <= 0) {
            if ($isAjax) json_response(['success' => false, 'message' => 'Invalid item.'], 400);
            set_flash('error', 'Invalid cart item.');
            redirect(BASE_URL . '/cart.php');
        }

        $res = move_to_cart_from_saved($itemId);

        if ($isAjax) {
            json_response([
                'success' => $res['success'],
                'message' => $res['message'],
                'cart_count' => get_cart_count()
            ]);
        }

        set_flash($res['success'] ? 'success' : 'error', $res['message']);
        redirect(BASE_URL . '/cart.php');
        break;

    default:
        if ($isAjax) {
            json_response(['success' => false, 'message' => 'Unrecognized cart action.'], 400);
        }
        set_flash('error', 'Unrecognized cart action.');
        redirect(BASE_URL . '/cart.php');
        break;
}

