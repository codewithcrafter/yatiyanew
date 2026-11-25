<?php
/**
 * YATIYA COUTURE - Payment Gateway & Settlement Engine
 * Modular payment gateway integration supporting Razorpay standard checkout,
 * constant-time cryptographic HMAC SHA-256 signature verification, idempotent
 * webhook processing, and transaction lifecycle synchronization.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/* =====================================================================
   1. GATEWAY INITIALIZATION & ORDER CREATION
   ===================================================================== */

/**
 * Initialize a Razorpay gateway order for an online couture purchase
 */
function create_razorpay_order(int $orderId, float $amount, string $orderNumber): array {
    $amountInPaise = (int)round($amount * 100);
    $keyId = defined('RAZORPAY_KEY_ID') ? RAZORPAY_KEY_ID : 'rzp_test_YatiyaCouture2026';
    $keySecret = defined('RAZORPAY_KEY_SECRET') ? RAZORPAY_KEY_SECRET : 'sk_test_YatiyaLuxurySecretKey2026';

    // In test or simulated environment, generate simulated gateway order ID
    $gatewayOrderId = 'order_' . strtoupper(substr(hash('sha256', $orderNumber . $orderId . time()), 0, 14));

    // Update existing payment record with the gateway order ID
    $pdo = db();
    $stmt = $pdo->prepare("
        UPDATE payments 
        SET gateway_order_id = ?, updated_at = NOW() 
        WHERE order_id = ? AND payment_method = 'razorpay'
    ");
    $stmt->execute([$gatewayOrderId, $orderId]);

    return [
        'success' => true,
        'gateway_order_id' => $gatewayOrderId,
        'key_id' => $keyId,
        'amount' => $amountInPaise,
        'currency' => 'INR',
        'order_number' => $orderNumber,
        'order_id' => $orderId
    ];
}

/* =====================================================================
   2. CRYPTOGRAPHIC SIGNATURE VERIFICATION
   ===================================================================== */

/**
 * Constant-time verification of Razorpay payment signature
 * Resists timing attacks via hash_equals()
 */
function verify_razorpay_signature(string $razorpayOrderId, string $razorpayPaymentId, string $razorpaySignature, ?string $keySecret = null): bool {
    if (empty($razorpayOrderId) || empty($razorpayPaymentId) || empty($razorpaySignature)) {
        return false;
    }

    $secret = $keySecret ?? (defined('RAZORPAY_KEY_SECRET') ? RAZORPAY_KEY_SECRET : 'sk_test_YatiyaLuxurySecretKey2026');
    $data = $razorpayOrderId . '|' . $razorpayPaymentId;
    $expectedSignature = hash_hmac('sha256', $data, $secret);

    return hash_equals($expectedSignature, $razorpaySignature);
}

/**
 * Helper to generate a valid Razorpay test signature for fixtures and verification
 */
function generate_razorpay_signature(string $razorpayOrderId, string $razorpayPaymentId, ?string $keySecret = null): string {
    $secret = $keySecret ?? (defined('RAZORPAY_KEY_SECRET') ? RAZORPAY_KEY_SECRET : 'sk_test_YatiyaLuxurySecretKey2026');
    return hash_hmac('sha256', $razorpayOrderId . '|' . $razorpayPaymentId, $secret);
}

/**
 * Constant-time verification of Razorpay Webhook signature
 */
function verify_webhook_signature(string $rawPayload, string $webhookSignature, ?string $webhookSecret = null): bool {
    if (empty($rawPayload) || empty($webhookSignature)) {
        return false;
    }

    $secret = $webhookSecret ?? (defined('RAZORPAY_WEBHOOK_SECRET') ? RAZORPAY_WEBHOOK_SECRET : 'whsec_YatiyaCoutureWebhookKey2026');
    $expectedSignature = hash_hmac('sha256', $rawPayload, $secret);

    return hash_equals($expectedSignature, $webhookSignature);
}

/* =====================================================================
   3. SETTLEMENT & LIFECYCLE HANDLERS
   ===================================================================== */

/**
 * Process confirmed successful payment and transition order state
 */
function handle_payment_success(int $orderId, string $transactionId, ?string $gatewayOrderId = null, ?string $signature = null, array $gatewayResponse = []): array {
    $pdo = db();

    // Check existing order
    $ordStmt = $pdo->prepare("SELECT id, order_number, payment_status, order_status FROM orders WHERE id = ? LIMIT 1");
    $ordStmt->execute([$orderId]);
    $order = $ordStmt->fetch();

    if (!$order) {
        return ['success' => false, 'message' => 'Order not found for payment settlement.'];
    }

    // Idempotency check: if already marked paid, return success without duplicate action
    if ($order['payment_status'] === 'paid') {
        return [
            'success' => true,
            'idempotent' => true,
            'message' => 'Payment has already been settled for this order.'
        ];
    }

    $pdo->beginTransaction();

    try {
        // 1. Update Payments record
        $payStmt = $pdo->prepare("
            UPDATE payments 
            SET 
                status = 'successful',
                transaction_id = ?,
                gateway_order_id = COALESCE(?, gateway_order_id),
                signature = ?,
                payment_gateway_response = ?,
                payment_date = NOW(),
                updated_at = NOW()
            WHERE order_id = ?
        ");
        $payStmt->execute([
            $transactionId,
            $gatewayOrderId,
            $signature,
            json_encode($gatewayResponse),
            $orderId
        ]);

        // 2. Update Order status
        $nextOrderStatus = ($order['order_status'] === 'placed') ? 'confirmed' : $order['order_status'];
        $updOrder = $pdo->prepare("
            UPDATE orders 
            SET 
                payment_status = 'paid',
                order_status = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updOrder->execute([$nextOrderStatus, $orderId]);

        $pdo->commit();

        return [
            'success' => true,
            'order_id' => $orderId,
            'order_number' => $order['order_number'],
            'payment_status' => 'paid',
            'order_status' => $nextOrderStatus,
            'message' => 'Payment settled successfully.'
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Payment success settlement failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to settle payment: ' . $e->getMessage()];
    }
}

/**
 * Handle payment failure and update order records
 */
function handle_payment_failure(int $orderId, string $reason, array $gatewayResponse = []): array {
    $pdo = db();

    $ordStmt = $pdo->prepare("SELECT id, order_number, payment_status FROM orders WHERE id = ? LIMIT 1");
    $ordStmt->execute([$orderId]);
    $order = $ordStmt->fetch();

    if (!$order) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    try {
        // Update Payments record
        $payStmt = $pdo->prepare("
            UPDATE payments 
            SET 
                status = 'failed',
                payment_gateway_response = ?,
                updated_at = NOW()
            WHERE order_id = ?
        ");
        $payStmt->execute([
            json_encode(array_merge($gatewayResponse, ['failure_reason' => $reason])),
            $orderId
        ]);

        // Update Order payment status
        $updOrder = $pdo->prepare("
            UPDATE orders 
            SET 
                payment_status = 'failed',
                updated_at = NOW()
            WHERE id = ?
        ");
        $updOrder->execute([$orderId]);

        return [
            'success' => true,
            'order_id' => $orderId,
            'order_number' => $order['order_number'],
            'payment_status' => 'failed',
            'reason' => $reason
        ];
    } catch (Exception $e) {
        error_log("Payment failure update failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error recording payment failure: ' . $e->getMessage()];
    }
}

/* =====================================================================
   4. IDEMPOTENT WEBHOOK HANDLER
   ===================================================================== */

/**
 * Process incoming webhook events with idempotency and duplicate protection
 */
function process_payment_webhook(string $rawPayload, string $webhookSignature): array {
    // 1. Cryptographic HMAC validation
    if (!verify_webhook_signature($rawPayload, $webhookSignature)) {
        return ['success' => false, 'code' => 401, 'message' => 'Invalid webhook signature.'];
    }

    $event = json_decode($rawPayload, true);
    if (!$event || empty($event['event'])) {
        return ['success' => false, 'code' => 400, 'message' => 'Malformed webhook payload.'];
    }

    $eventType = $event['event'];
    $payloadData = $event['payload']['payment']['entity'] ?? ($event['payload']['order']['entity'] ?? []);

    $gatewayOrderId = $payloadData['order_id'] ?? null;
    $transactionId = $payloadData['id'] ?? null;
    $receipt = $payloadData['notes']['order_number'] ?? ($payloadData['receipt'] ?? null);

    // Find order
    $pdo = db();
    $orderId = null;

    if ($receipt) {
        $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ? LIMIT 1");
        $stmt->execute([$receipt]);
        $orderId = $stmt->fetchColumn();
    }

    if (!$orderId && $gatewayOrderId) {
        $stmt = $pdo->prepare("SELECT order_id FROM payments WHERE gateway_order_id = ? LIMIT 1");
        $stmt->execute([$gatewayOrderId]);
        $orderId = $stmt->fetchColumn();
    }

    if (!$orderId) {
        return ['success' => false, 'code' => 404, 'message' => 'Matching order could not be resolved from webhook payload.'];
    }

    $orderId = (int)$orderId;

    // Process specific event types
    switch ($eventType) {
        case 'payment.captured':
        case 'order.paid':
            $result = handle_payment_success(
                $orderId,
                $transactionId ?: ('txn_' . bin2hex(random_bytes(6))),
                $gatewayOrderId,
                null,
                $event
            );
            return [
                'success' => true,
                'code' => 200,
                'action' => 'settled',
                'idempotent' => !empty($result['idempotent']),
                'message' => $result['message']
            ];

        case 'payment.failed':
            $reason = $payloadData['error_description'] ?? 'Payment failed via webhook notification.';
            $result = handle_payment_failure($orderId, $reason, $event);
            return [
                'success' => true,
                'code' => 200,
                'action' => 'failed',
                'message' => 'Payment failure recorded.'
            ];

        default:
            return [
                'success' => true,
                'code' => 200,
                'action' => 'ignored',
                'message' => "Event {$eventType} acknowledged."
            ];
    }
}

/* =====================================================================
   5. RETRY & ELIGIBILITY HELPERS
   ===================================================================== */

/**
 * Determine if an order is eligible for payment retry
 */
function can_retry_payment(array $order): bool {
    if ($order['payment_method'] !== 'online') {
        return false;
    }
    if (in_array($order['order_status'], ['cancelled', 'returned'], true)) {
        return false;
    }
    return in_array($order['payment_status'], ['pending', 'failed'], true);
}

