<?php
/**
 * YATIYA COUTURE - Checkout & Order Processing Engine
 * High-precision, server-side validated order creation with pessimistic locking,
 * tamper-proof recalculation, inventory ledger synchronization, and atomic transactions.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/cart-functions.php';

/* =====================================================================
   1. ORDER NUMBER GENERATION
   ===================================================================== */

/**
 * Generate a cryptographically secure, unique luxury order number
 * Format: YTY-YYYYMMDD-XXXXXX (e.g., YTY-20261118-A7B8C9)
 */
function generate_order_number(): string {
    $pdo = db();
    $prefix = 'YTY-' . date('Ymd') . '-';
    $attempts = 0;

    do {
        $attempts++;
        $randomPart = strtoupper(bin2hex(random_bytes(3))); // 6 hexadecimal characters
        $orderNumber = $prefix . $randomPart;

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE order_number = ?");
        $stmt->execute([$orderNumber]);
        $exists = (int)$stmt->fetchColumn() > 0;
    } while ($exists && $attempts < 10);

    if ($exists) {
        $orderNumber = $prefix . strtoupper(bin2hex(random_bytes(4)));
    }

    return $orderNumber;
}

/* =====================================================================
   2. ADDRESS & CONTACT VALIDATION
   ===================================================================== */

/**
 * Validate customer address fields with strict checks
 */
function validate_checkout_address(array $data, string $prefix = ''): array {
    $fullNameKey = $prefix ? "{$prefix}_full_name" : 'full_name';
    $phoneKey = $prefix ? "{$prefix}_phone" : 'phone';
    $line1Key = $prefix ? "{$prefix}_address_line1" : 'address_line1';
    $line2Key = $prefix ? "{$prefix}_address_line2" : 'address_line2';
    $landmarkKey = $prefix ? "{$prefix}_landmark" : 'landmark';
    $cityKey = $prefix ? "{$prefix}_city" : 'city';
    $stateKey = $prefix ? "{$prefix}_state" : 'state';
    $postalKey = $prefix ? "{$prefix}_postal_code" : 'postal_code';
    $countryKey = $prefix ? "{$prefix}_country" : 'country';

    $fullName = clean_input($data[$fullNameKey] ?? '');
    $phone = clean_input($data[$phoneKey] ?? '');
    $line1 = clean_input($data[$line1Key] ?? '');
    $line2 = clean_input($data[$line2Key] ?? '');
    $landmark = clean_input($data[$landmarkKey] ?? '');
    $city = clean_input($data[$cityKey] ?? '');
    $state = clean_input($data[$stateKey] ?? '');
    $postalCode = clean_input($data[$postalKey] ?? '');
    $country = clean_input($data[$countryKey] ?? 'India');

    if (empty($fullName) || strlen($fullName) < 3) {
        return ['success' => false, 'message' => 'Please provide the recipient full name (minimum 3 characters).'];
    }

    $cleanPhone = preg_replace('/[^\d]/', '', $phone);
    if (strlen($cleanPhone) < 10 || strlen($cleanPhone) > 15) {
        return ['success' => false, 'message' => 'Please provide a valid 10-digit mobile contact number.'];
    }

    if (empty($line1) || strlen($line1) < 5) {
        return ['success' => false, 'message' => 'Please provide a complete street address or atelier suite (minimum 5 characters).'];
    }

    if (empty($city) || strlen($city) < 2) {
        return ['success' => false, 'message' => 'Please specify the delivery city.'];
    }

    if (empty($state) || strlen($state) < 2) {
        return ['success' => false, 'message' => 'Please specify the delivery state/province.'];
    }

    $cleanPostal = preg_replace('/\s+/', '', $postalCode);
    if (empty($cleanPostal) || !preg_match('/^[0-9]{6}$/', $cleanPostal)) {
        return ['success' => false, 'message' => 'Please provide a valid 6-digit postal / PIN code.'];
    }

    return [
        'success' => true,
        'message' => 'Address is valid.',
        'data' => [
            'full_name' => $fullName,
            'phone' => $phone,
            'address_line1' => $line1,
            'address_line2' => $line2,
            'landmark' => $landmark,
            'city' => $city,
            'state' => $state,
            'postal_code' => $cleanPostal,
            'country' => $country ?: 'India'
        ]
    ];
}

/* =====================================================================
   3. CHECKOUT CONTEXT & BAG VERIFICATION
   ===================================================================== */

/**
 * Retrieve comprehensive checkout context for display and pre-flight validation
 */
function get_checkout_context(int $cartId, ?int $customerId = null): array {
    $items = get_cart_items($cartId, false);
    $totals = calculate_cart_totals($items);

    $customer = null;
    $addresses = [];
    $defaultShipping = null;
    $defaultBilling = null;

    if ($customerId) {
        $stmt = db()->prepare("SELECT id, first_name, last_name, email, phone FROM customers WHERE id = ?");
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch() ?: null;

        if (function_exists('get_customer_addresses')) {
            $addresses = get_customer_addresses($customerId);
            foreach ($addresses as $addr) {
                if ($addr['is_default_shipping']) {
                    $defaultShipping = $addr;
                }
                if ($addr['is_default_billing']) {
                    $defaultBilling = $addr;
                }
            }
            if (!$defaultShipping && !empty($addresses)) {
                $defaultShipping = $addresses[0];
            }
        }
    }

    $stockOk = true;
    $stockIssues = [];
    foreach ($items as $item) {
        if ($item['stock_quantity'] < $item['quantity']) {
            $stockOk = false;
            $stockIssues[] = "{$item['product_name']} ({$item['size']}) only has {$item['stock_quantity']} pieces remaining.";
        }
    }

    $codMax = defined('COD_MAX_AMOUNT') ? (float)COD_MAX_AMOUNT : 50000.00;
    $codEnabled = defined('COD_ENABLED') ? COD_ENABLED : true;
    $isCodEligible = $codEnabled && ($totals['grand_total'] <= $codMax) && ($totals['grand_total'] > 0);

    return [
        'cart_id' => $cartId,
        'customer' => $customer,
        'addresses' => $addresses,
        'default_shipping' => $defaultShipping,
        'default_billing' => $defaultBilling,
        'items' => $items,
        'totals' => $totals,
        'stock_ok' => $stockOk,
        'stock_issues' => $stockIssues,
        'is_cod_eligible' => $isCodEligible,
        'cod_max_amount' => $codMax,
        'cod_enabled' => $codEnabled
    ];
}

/* =====================================================================
   4. ATOMIC ORDER CREATION & TRANSACTION
   ===================================================================== */

/**
 * Execute server-side validated atomic order creation with pessimistic locking
 */
function create_order_from_cart(int $cartId, array $checkoutData, ?int $customerId = null): array {
    $pdo = db();

    // 1. Identify or auto-provision customer (referential integrity requires orders.customer_id NOT NULL)
    if (!$customerId) {
        $email = clean_input($checkoutData['email'] ?? ($checkoutData['customer_email'] ?? ''));
        $firstName = clean_input($checkoutData['first_name'] ?? '');
        $lastName = clean_input($checkoutData['last_name'] ?? '');
        $phone = clean_input($checkoutData['phone'] ?? ($checkoutData['shipping_phone'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Please provide a valid email address for order notifications.'];
        }

        // Check if customer already exists with this email
        $custStmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? LIMIT 1");
        $custStmt->execute([$email]);
        $existingCustId = $custStmt->fetchColumn();

        if ($existingCustId) {
            $customerId = (int)$existingCustId;
        } else {
            // Provision guest patron account
            if (empty($firstName)) {
                $firstName = 'Patron';
            }
            $randomPass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $newCustStmt = $pdo->prepare("
                INSERT INTO customers (first_name, last_name, email, phone, password_hash, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            $newCustStmt->execute([$firstName, $lastName, $email, $phone, $randomPass]);
            $customerId = (int)$pdo->lastInsertId();
        }
    }

    // 2. Validate Payment Method Selection
    $paymentMethod = clean_input($checkoutData['payment_method'] ?? 'cod');
    if (!in_array($paymentMethod, ['cod', 'online'], true)) {
        return ['success' => false, 'message' => 'Please choose a valid payment method (Cash on Delivery or Online Payment).'];
    }

    // 3. Validate Delivery Address
    $shipVal = validate_checkout_address($checkoutData, 'shipping');
    if (!$shipVal['success']) {
        return $shipVal;
    }
    $ship = $shipVal['data'];

    // 4. Validate or Fallback Billing Address
    $billingSame = !empty($checkoutData['billing_same_as_shipping']) || empty($checkoutData['billing_address_line1']);
    if ($billingSame) {
        $bill = $ship;
    } else {
        $billVal = validate_checkout_address($checkoutData, 'billing');
        if (!$billVal['success']) {
            return $billVal;
        }
        $bill = $billVal['data'];
    }

    $customerNotes = clean_input($checkoutData['customer_notes'] ?? '');

    // 5. Begin Atomic Transaction
    $pdo->beginTransaction();

    try {
        // Fetch active cart items
        $cartStmt = $pdo->prepare("
            SELECT ci.id AS cart_item_id, ci.product_id, ci.variant_id, ci.quantity
            FROM cart_items ci
            WHERE ci.cart_id = ? AND ci.saved_for_later = 0
        ");
        $cartStmt->execute([$cartId]);
        $activeCartItems = $cartStmt->fetchAll();

        if (empty($activeCartItems)) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'Your shopping bag is currently empty.'];
        }

        // Recalculate and Lock each item using SELECT ... FOR UPDATE
        $computedSubtotal = 0.0;
        $orderItemsSnapshot = [];

        foreach ($activeCartItems as $cItem) {
            $pId = (int)$cItem['product_id'];
            $vId = $cItem['variant_id'] ? (int)$cItem['variant_id'] : null;
            $qty = (int)$cItem['quantity'];

            // Fetch and lock product
            $pStmt = $pdo->prepare("
                SELECT id, name, sku, selling_price, original_price, status 
                FROM products 
                WHERE id = ? FOR UPDATE
            ");
            $pStmt->execute([$pId]);
            $product = $pStmt->fetch();

            if (!$product || $product['status'] !== 'active') {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'One of the creations in your bag is no longer available.'];
            }

            $variantSku = $product['sku'];
            $variantName = 'Standard Atelier';
            $unitPrice = (float)$product['selling_price'];
            $currentStock = 0;

            if ($vId) {
                // Fetch and lock variant
                $vStmt = $pdo->prepare("
                    SELECT id, sku, size, color, additional_price, stock_quantity, status 
                    FROM product_variants 
                    WHERE id = ? AND product_id = ? FOR UPDATE
                ");
                $vStmt->execute([$vId, $pId]);
                $variant = $vStmt->fetch();

                if (!$variant || $variant['status'] !== 'active') {
                    $pdo->rollBack();
                    return ['success' => false, 'message' => "The selected creation size/variant is currently discontinued."];
                }

                $currentStock = (int)$variant['stock_quantity'];
                if ($currentStock < $qty) {
                    $pdo->rollBack();
                    return [
                        'success' => false,
                        'message' => "Insufficient stock for '{$product['name']} ({$variant['size']})'. Only {$currentStock} available."
                    ];
                }

                $variantSku = $variant['sku'];
                $variantName = trim(($variant['size'] ?? '') . ' / ' . ($variant['color'] ?? ''), ' /');
                $unitPrice += (float)$variant['additional_price'];
            }

            $lineTotal = round($unitPrice * $qty, 2);
            $computedSubtotal += $lineTotal;

            $orderItemsSnapshot[] = [
                'product_id' => $pId,
                'variant_id' => $vId,
                'product_name' => $product['name'],
                'variant_name' => $variantName,
                'sku' => $variantSku,
                'unit_price' => $unitPrice,
                'quantity' => $qty,
                'total_price' => $lineTotal,
                'stock_before' => $currentStock
            ];
        }

        // Revalidate Coupon Code from Database
        $couponId = null;
        $couponCode = null;
        $discountAmount = 0.0;
        $appliedCoupon = $_SESSION['applied_coupon'] ?? null;

        if ($appliedCoupon && !empty($appliedCoupon['code'])) {
            $cCheckStmt = $pdo->prepare("
                SELECT * FROM coupons 
                WHERE code = ? AND is_active = 1 
                FOR UPDATE
            ");
            $cCheckStmt->execute([$appliedCoupon['code']]);
            $couponDb = $cCheckStmt->fetch();

            if ($couponDb) {
                $now = time();
                $startValid = empty($couponDb['start_date']) || $now >= strtotime($couponDb['start_date']);
                $endValid = empty($couponDb['end_date']) || $now <= strtotime($couponDb['end_date']);
                $usageValid = ($couponDb['usage_limit'] === null) || ($couponDb['usage_count'] < $couponDb['usage_limit']);
                $minSpendMet = $computedSubtotal >= (float)$couponDb['min_spend'];

                if ($startValid && $endValid && $usageValid && $minSpendMet) {
                    $couponId = (int)$couponDb['id'];
                    $couponCode = $couponDb['code'];

                    if ($couponDb['discount_type'] === 'percentage') {
                        $rawDisc = $computedSubtotal * ((float)$couponDb['discount_value'] / 100);
                        if ($couponDb['max_discount'] !== null && (float)$couponDb['max_discount'] > 0) {
                            $discountAmount = min($rawDisc, (float)$couponDb['max_discount']);
                        } else {
                            $discountAmount = $rawDisc;
                        }
                    } else {
                        $discountAmount = min((float)$couponDb['discount_value'], $computedSubtotal);
                    }
                    $discountAmount = round($discountAmount, 2);
                }
            }
        }

        // Calculate Shipping Fee
        $freeShippingThreshold = (float)get_setting('free_shipping_threshold', '2999.00');
        $defaultShippingFee = (float)get_setting('default_shipping_charge', '199.00');
        $shippingCharge = ($computedSubtotal >= $freeShippingThreshold) ? 0.00 : $defaultShippingFee;

        // Calculate Tax Amount (12% GST inclusive snapshot for compliance)
        $taxRate = (float)get_setting('default_tax_rate', '0.12');
        $taxableBase = max(0.0, $computedSubtotal - $discountAmount);
        $taxAmount = round($taxableBase * ($taxRate / (1 + $taxRate)), 2);

        // Grand Total
        $grandTotal = round(max(0.0, $computedSubtotal - $discountAmount + $shippingCharge), 2);

        // Verify COD Rules
        if ($paymentMethod === 'cod') {
            $codMax = defined('COD_MAX_AMOUNT') ? (float)COD_MAX_AMOUNT : 50000.00;
            $codEnabled = defined('COD_ENABLED') ? COD_ENABLED : true;
            if (!$codEnabled) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Cash on Delivery is currently disabled. Please select online payment.'];
            }
            if ($grandTotal > $codMax) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Cash on Delivery is limited to orders up to ' . format_price($codMax) . '. Please use online payment for this luxury order.'];
            }
        }

        // Generate Unique Order Number
        $orderNumber = generate_order_number();

        // Initial Payment and Order Status
        $initialPayStatus = 'pending';
        $initialOrderStatus = 'placed';

        // Insert Order Record
        $ordStmt = $pdo->prepare("
            INSERT INTO orders (
                order_number, customer_id, subtotal, discount_amount, coupon_id, coupon_code,
                shipping_charge, tax_amount, grand_total, payment_method, payment_status, order_status,
                shipping_full_name, shipping_phone, shipping_address_line1, shipping_address_line2,
                shipping_landmark, shipping_city, shipping_state, shipping_postal_code, shipping_country,
                billing_full_name, billing_phone, billing_address_line1, billing_address_line2,
                billing_city, billing_state, billing_postal_code, billing_country,
                customer_notes, placed_at, updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, NOW(), NOW()
            )
        ");

        $ordStmt->execute([
            $orderNumber,
            $customerId,
            $computedSubtotal,
            $discountAmount,
            $couponId,
            $couponCode,
            $shippingCharge,
            $taxAmount,
            $grandTotal,
            $paymentMethod,
            $initialPayStatus,
            $initialOrderStatus,
            $ship['full_name'],
            $ship['phone'],
            $ship['address_line1'],
            $ship['address_line2'],
            $ship['landmark'],
            $ship['city'],
            $ship['state'],
            $ship['postal_code'],
            $ship['country'],
            $bill['full_name'],
            $bill['phone'],
            $bill['address_line1'],
            $bill['address_line2'],
            $bill['city'],
            $bill['state'],
            $bill['postal_code'],
            $bill['country'],
            $customerNotes
        ]);

        $orderId = (int)$pdo->lastInsertId();

        // Insert Order Line Items & Decrement Stock
        $itemInsert = $pdo->prepare("
            INSERT INTO order_items (
                order_id, product_id, variant_id, product_name, variant_name,
                sku, unit_price, quantity, total_price, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $stockDeduct = $pdo->prepare("
            UPDATE product_variants 
            SET stock_quantity = stock_quantity - ?, updated_at = NOW() 
            WHERE id = ?
        ");

        $invMovement = $pdo->prepare("
            INSERT INTO inventory_movements (
                product_id, variant_id, movement_type, quantity, balance_after,
                reference_id, note, created_at
            ) VALUES (?, ?, 'order_sale', ?, ?, ?, ?, NOW())
        ");

        foreach ($orderItemsSnapshot as $sItem) {
            $itemInsert->execute([
                $orderId,
                $sItem['product_id'],
                $sItem['variant_id'],
                $sItem['product_name'],
                $sItem['variant_name'],
                $sItem['sku'],
                $sItem['unit_price'],
                $sItem['quantity'],
                $sItem['total_price']
            ]);

            if ($sItem['variant_id']) {
                $stockDeduct->execute([$sItem['quantity'], $sItem['variant_id']]);
                $balanceAfter = $sItem['stock_before'] - $sItem['quantity'];

                $invMovement->execute([
                    $sItem['product_id'],
                    $sItem['variant_id'],
                    -$sItem['quantity'],
                    $balanceAfter,
                    $orderNumber,
                    "Order sale deduction for {$orderNumber}"
                ]);
            }
        }

        // Record Coupon Redemption
        if ($couponId && $discountAmount > 0) {
            $redStmt = $pdo->prepare("
                INSERT INTO coupon_redemptions (coupon_id, customer_id, order_id, discount_amount, redeemed_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $redStmt->execute([$couponId, $customerId, $orderId, $discountAmount]);

            $pdo->prepare("UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?")->execute([$couponId]);
        }

        // Create Initial Payments Record
        $payGatewayMethod = ($paymentMethod === 'cod') ? 'cod' : 'razorpay';
        $payInsert = $pdo->prepare("
            INSERT INTO payments (
                order_id, payment_method, gateway_order_id, amount, currency, status, created_at, updated_at
            ) VALUES (?, ?, NULL, ?, 'INR', 'pending', NOW(), NOW())
        ");
        $payInsert->execute([$orderId, $payGatewayMethod, $grandTotal]);
        $paymentRecordId = (int)$pdo->lastInsertId();

        // Clear active items from cart & mark cart converted
        $pdo->prepare("DELETE FROM cart_items WHERE cart_id = ? AND saved_for_later = 0")->execute([$cartId]);
        $pdo->prepare("UPDATE carts SET status = 'converted', updated_at = NOW() WHERE id = ?")->execute([$cartId]);

        // Commit Transaction
        $pdo->commit();

        // Clear session coupon and store order reference
        // Auto-persist new address to customer address book if requested or if first address
        if ($customerId) {
            try {
                $checkAddr = $pdo->prepare("
                    SELECT id FROM customer_addresses 
                    WHERE customer_id = ? AND address_line1 = ? AND postal_code = ? 
                    LIMIT 1
                ");
                $checkAddr->execute([$customerId, $ship['address_line1'], $ship['postal_code']]);
                if (!$checkAddr->fetch()) {
                    $cntAddr = $pdo->prepare("SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?");
                    $cntAddr->execute([$customerId]);
                    $isFirst = ((int)$cntAddr->fetchColumn() === 0) ? 1 : 0;

                    $insAddr = $pdo->prepare("
                        INSERT INTO customer_addresses (
                            customer_id, address_type, full_name, phone, address_line1, address_line2,
                            landmark, city, state, postal_code, country, is_default_shipping, created_at, updated_at
                        ) VALUES (?, 'shipping', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $insAddr->execute([
                        $customerId,
                        $ship['full_name'],
                        $ship['phone'],
                        $ship['address_line1'],
                        $ship['address_line2'] ?? null,
                        $ship['landmark'] ?? null,
                        $ship['city'],
                        $ship['state'],
                        $ship['postal_code'],
                        $ship['country'] ?? 'India',
                        $isFirst
                    ]);
                }
            } catch (Exception $addrEx) {
                // Address persistence is non-blocking to order completion
            }
        }

        unset($_SESSION['applied_coupon']);
        $_SESSION['last_order_id'] = $orderId;
        $_SESSION['last_order_number'] = $orderNumber;

        return [
            'success' => true,
            'message' => 'Order created successfully.',
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'payment_id' => $paymentRecordId,
            'grand_total' => $grandTotal,
            'payment_method' => $paymentMethod,
            'customer_id' => $customerId
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Order creation failure: " . $e->getMessage());
        return [
            'success' => false,
            'message' => 'An error occurred while placing your order. Please try again. ' . $e->getMessage()
        ];
    }
}

/* =====================================================================
   5. ORDER RETRIEVAL & ACCESS CONTROL
   ===================================================================== */

/**
 * Retrieve comprehensive order dossier by order ID
 */
function get_order_by_id(int $orderId): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT o.*, c.first_name, c.last_name, c.email AS customer_email, c.phone AS customer_phone
        FROM orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?
        LIMIT 1
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        return null;
    }

    // Line items with product images
    $itemStmt = $pdo->prepare("
        SELECT 
            oi.*,
            p.slug AS product_slug,
            (SELECT image_url FROM product_images WHERE product_id = oi.product_id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image_url
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");
    $itemStmt->execute([$orderId]);
    $order['items'] = $itemStmt->fetchAll();

    // Payment record
    $payStmt = $pdo->prepare("SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1");
    $payStmt->execute([$orderId]);
    $order['payment'] = $payStmt->fetch() ?: null;

    return $order;
}

/**
 * Retrieve comprehensive order dossier by order number
 */
function get_order_by_number(string $orderNumber): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ? LIMIT 1");
    $stmt->execute([$orderNumber]);
    $id = $stmt->fetchColumn();

    if (!$id) {
        return null;
    }

    return get_order_by_id((int)$id);
}

/**
 * Check if the current visitor is authorized to view this order dossier
 */
function can_view_order(array $order, ?int $currentCustomerId = null): bool {
    // 1. Authenticated customer owns the order
    if ($currentCustomerId && (int)$order['customer_id'] === (int)$currentCustomerId) {
        return true;
    }

    // 2. Just completed in this exact browser session
    if (!empty($_SESSION['last_order_id']) && (int)$_SESSION['last_order_id'] === (int)$order['id']) {
        return true;
    }

    if (!empty($_SESSION['last_order_number']) && $_SESSION['last_order_number'] === $order['order_number']) {
        return true;
    }

    // 3. Admin user is logged in
    if (!empty($_SESSION['admin_id'])) {
        return true;
    }

    return false;
}

