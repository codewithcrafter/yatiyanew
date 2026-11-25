<?php
/**
 * YATIYA COUTURE - Checkout Action Controller
 * Handles order placement, Razorpay verification, payment failure callbacks,
 * and coupon operations during the luxury checkout flow.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart-functions.php';
require_once __DIR__ . '/../includes/checkout-functions.php';
require_once __DIR__ . '/../includes/payment-functions.php';

header('Content-Type: application/json; charset=utf-8');

// Ensure request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Verify CSRF Token
$token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf_token($token)) {
    echo json_encode(['success' => false, 'message' => 'Security validation token expired. Please refresh the page and try again.']);
    exit;
}

$action = clean_input($_POST['action'] ?? '');
$customerId = is_logged_in() ? (int)$_SESSION['customer_id'] : null;
$cartId = get_or_create_cart_id();

switch ($action) {
    case 'place_order':
        // Mandatory Customer Authentication Guard
        if (!is_logged_in() || empty($customerId)) {
            echo json_encode([
                'success' => false,
                'message' => 'Patron authentication required. Please sign in or create an account to proceed with checkout.',
                'redirect' => BASE_URL . '/login.php?redirect=checkout.php'
            ]);
            exit;
        }

        $result = create_order_from_cart($cartId, $_POST, $customerId);

        if (!$result['success']) {
            echo json_encode($result);
            exit;
        }

        $orderId = (int)$result['order_id'];
        $orderNumber = $result['order_number'];
        $paymentMethod = $result['payment_method'];
        $grandTotal = (float)$result['grand_total'];

        if ($paymentMethod === 'cod') {
            echo json_encode([
                'success' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'payment_method' => 'cod',
                'redirect' => BASE_URL . '/order-success.php?order=' . urlencode($orderNumber)
            ]);
            exit;
        } else {
            // Online Razorpay Payment Initialization
            $razorpayData = create_razorpay_order($orderId, $grandTotal, $orderNumber);

            $custName = clean_input($_POST['shipping_full_name'] ?? '');
            $custEmail = clean_input($_POST['customer_email'] ?? ($_SESSION['customer_email'] ?? ''));
            $custPhone = clean_input($_POST['shipping_phone'] ?? '');

            echo json_encode([
                'success' => true,
                'requires_payment' => true,
                'order_id' => $orderId,
                'order_number' => $orderNumber,
                'payment_method' => 'online',
                'razorpay' => [
                    'key' => $razorpayData['key_id'],
                    'amount' => $razorpayData['amount'],
                    'currency' => $razorpayData['currency'],
                    'name' => SITE_NAME,
                    'description' => 'Order ' . $orderNumber,
                    'order_id' => $razorpayData['gateway_order_id'],
                    'prefill' => [
                        'name' => $custName,
                        'email' => $custEmail,
                        'contact' => $custPhone
                    ],
                    'notes' => [
                        'order_id' => (string)$orderId,
                        'order_number' => $orderNumber
                    ],
                    'theme' => [
                        'color' => '#C6A15B'
                    ]
                ]
            ]);
            exit;
        }

    case 'verify_payment':
        $orderId = (int)($_POST['order_id'] ?? 0);
        $razorpayOrderId = clean_input($_POST['razorpay_order_id'] ?? '');
        $razorpayPaymentId = clean_input($_POST['razorpay_payment_id'] ?? '');
        $razorpaySignature = clean_input($_POST['razorpay_signature'] ?? '');

        $order = get_order_by_id($orderId);
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
            exit;
        }

        // Verify cryptographic signature
        $isValid = verify_razorpay_signature($razorpayOrderId, $razorpayPaymentId, $razorpaySignature);

        if ($isValid) {
            $settleRes = handle_payment_success($orderId, $razorpayPaymentId, $razorpayOrderId, $razorpaySignature, $_POST);
            echo json_encode([
                'success' => true,
                'message' => 'Payment settled successfully.',
                'order_number' => $order['order_number'],
                'redirect' => BASE_URL . '/order-success.php?order=' . urlencode($order['order_number'])
            ]);
        } else {
            handle_payment_failure($orderId, 'Cryptographic signature verification mismatch', $_POST);
            echo json_encode([
                'success' => false,
                'message' => 'Payment verification failed. If your account was debited, our concierge team will reconcile it promptly.',
                'order_number' => $order['order_number'],
                'redirect' => BASE_URL . '/order-failed.php?order=' . urlencode($order['order_number']) . '&reason=' . urlencode('Signature verification failed')
            ]);
        }
        exit;

    case 'record_failure':
        $orderId = (int)($_POST['order_id'] ?? 0);
        $reason = clean_input($_POST['reason'] ?? 'Payment was dismissed or declined by patron.');

        $order = get_order_by_id($orderId);
        if ($order) {
            handle_payment_failure($orderId, $reason, $_POST);
            echo json_encode([
                'success' => true,
                'order_number' => $order['order_number'],
                'redirect' => BASE_URL . '/order-failed.php?order=' . urlencode($order['order_number']) . '&reason=' . urlencode($reason)
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Order not found.']);
        }
        exit;

    case 'retry_payment':
        $orderId = (int)($_POST['order_id'] ?? 0);
        $order = get_order_by_id($orderId);

        if (!$order || !can_retry_payment($order)) {
            echo json_encode(['success' => false, 'message' => 'This order is not eligible for payment retry.']);
            exit;
        }

        $razorpayData = create_razorpay_order($orderId, (float)$order['grand_total'], $order['order_number']);

        echo json_encode([
            'success' => true,
            'requires_payment' => true,
            'order_id' => $orderId,
            'order_number' => $order['order_number'],
            'razorpay' => [
                'key' => $razorpayData['key_id'],
                'amount' => $razorpayData['amount'],
                'currency' => $razorpayData['currency'],
                'name' => SITE_NAME,
                'description' => 'Retry Payment - Order ' . $order['order_number'],
                'order_id' => $razorpayData['gateway_order_id'],
                'prefill' => [
                    'name' => $order['shipping_full_name'],
                    'email' => $order['customer_email'],
                    'contact' => $order['shipping_phone']
                ],
                'notes' => [
                    'order_id' => (string)$orderId,
                    'order_number' => $order['order_number']
                ],
                'theme' => [
                    'color' => '#C6A15B'
                ]
            ]
        ]);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Unrecognized checkout action.']);
        exit;
}

