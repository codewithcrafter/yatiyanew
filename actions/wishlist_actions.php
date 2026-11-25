<?php
/**
 * YATIYA COUTURE - Wishlist Actions Controller
 * Handles Add, Remove, Toggle and Move to Cart for Customer & Guest Sessions
 * Strictly POST with CSRF verification and server-side validation
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart-functions.php';

// Enforce POST method for all state-changing wishlist operations
if (!is_post()) {
    set_flash('error', 'Invalid request method. Wishlist operations require secure submission.');
    redirect(BASE_URL . '/wishlist.php');
}

// Enforce CSRF Protection
$submittedToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!verify_csrf_token($submittedToken)) {
    if (is_ajax() || isset($_POST['ajax'])) {
        json_response([
            'success' => false,
            'message' => 'Security token expired or invalid. Please refresh and try again.'
        ], 403);
    }
    set_flash('error', 'Security verification failed. Please try again.');
    redirect(BASE_URL . '/wishlist.php');
}

$action = clean_input($_POST['action'] ?? '');
$productId = (int)($_POST['product_id'] ?? 0);
$isAjax = is_ajax() || (isset($_POST['ajax']) && $_POST['ajax'] == '1');

if ($productId <= 0) {
    if ($isAjax) json_response(['success' => false, 'message' => 'Invalid product identifier.'], 400);
    set_flash('error', 'Invalid creation selected.');
    redirect(BASE_URL . '/wishlist.php');
}

switch ($action) {
    case 'add':
        $res = add_to_wishlist($productId);
        if ($isAjax) json_response($res);
        set_flash($res['success'] ? 'success' : 'error', $res['message']);
        $referer = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/wishlist.php');
        redirect($referer);
        break;

    case 'remove':
        $res = remove_from_wishlist($productId);
        if ($isAjax) json_response($res);
        set_flash($res['success'] ? 'success' : 'error', $res['message']);
        redirect(BASE_URL . '/wishlist.php');
        break;

    case 'toggle':
        if (is_product_in_wishlist($productId)) {
            $res = remove_from_wishlist($productId);
            $res['status'] = 'removed';
            $res['is_favorited'] = false;
        } else {
            $res = add_to_wishlist($productId);
            $res['status'] = 'added';
            $res['is_favorited'] = true;
        }
        if ($isAjax) json_response($res);
        set_flash($res['success'] ? 'success' : 'error', $res['message']);
        $referer = $_SERVER['HTTP_REFERER'] ?? (BASE_URL . '/wishlist.php');
        redirect($referer);
        break;

    case 'move_to_cart':
        $variantId = !empty($_POST['variant_id']) ? (int)$_POST['variant_id'] : null;
        $res = move_wishlist_to_cart($productId, $variantId);

        if ($isAjax) {
            json_response([
                'success' => $res['success'],
                'message' => $res['message'],
                'cart_count' => get_cart_count(),
                'wishlist_count' => get_wishlist_count()
            ]);
        }

        set_flash($res['success'] ? 'success' : 'error', $res['message']);
        redirect(BASE_URL . '/cart.php');
        break;

    default:
        if ($isAjax) json_response(['success' => false, 'message' => 'Unrecognized wishlist action.'], 400);
        set_flash('error', 'Unrecognized wishlist action.');
        redirect(BASE_URL . '/wishlist.php');
        break;
}

