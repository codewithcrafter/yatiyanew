<?php
/**
 * YATIYA COUTURE - Razorpay Webhook Ingestion Endpoint
 * Verifies cryptographic webhook signature and provides idempotent order settlement.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/payment-functions.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit;
}

$rawPayload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

if (empty($rawPayload) || empty($signature)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing payload or signature.']);
    exit;
}

$result = process_payment_webhook($rawPayload, $signature);

http_response_code($result['code'] ?? 200);
echo json_encode($result);
exit;

