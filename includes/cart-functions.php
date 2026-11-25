<?php
/**
 * YATIYA COUTURE - Shopping Cart, Wishlist & Coupon Engine
 * Pure Core PHP & PDO implementation adhering to strict luxury e-commerce architecture
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/* =====================================================================
   1. CART IDENTIFICATION & LIFECYCLE
   ===================================================================== */

/**
 * Retrieve or initialize active shopping cart ID for guest or logged-in customer
 */
function get_or_create_cart_id(): int {
    $customerId = $_SESSION['customer_id'] ?? null;
    $sessionId = session_id();

    if ($customerId) {
        $stmt = db()->prepare("
            SELECT id FROM carts 
            WHERE customer_id = ? AND status = 'active' 
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->execute([$customerId]);
        $cartId = $stmt->fetchColumn();

        if ($cartId) {
            return (int)$cartId;
        }

        // Create new active cart for authenticated customer
        $insert = db()->prepare("
            INSERT INTO carts (customer_id, session_id, status, created_at, updated_at) 
            VALUES (?, ?, 'active', NOW(), NOW())
        ");
        $insert->execute([$customerId, $sessionId]);
        return (int)db()->lastInsertId();
    } else {
        $stmt = db()->prepare("
            SELECT id FROM carts 
            WHERE session_id = ? AND customer_id IS NULL AND status = 'active' 
            ORDER BY updated_at DESC LIMIT 1
        ");
        $stmt->execute([$sessionId]);
        $cartId = $stmt->fetchColumn();

        if ($cartId) {
            return (int)$cartId;
        }

        // Create new active cart for guest session
        $insert = db()->prepare("
            INSERT INTO carts (customer_id, session_id, status, created_at, updated_at) 
            VALUES (NULL, ?, 'active', NOW(), NOW())
        ");
        $insert->execute([$sessionId]);
        return (int)db()->lastInsertId();
    }
}

/**
 * Fetch all items in a cart with enriched product, variant, image, and pricing details
 */
function get_cart_items(int $cartId, bool $savedForLater = false): array {
    $stmt = db()->prepare("
        SELECT 
            ci.id AS cart_item_id,
            ci.cart_id,
            ci.product_id,
            ci.variant_id,
            ci.quantity,
            ci.saved_for_later,
            ci.created_at AS item_added_at,
            p.name AS product_name,
            p.slug AS product_slug,
            p.original_price AS product_base_price,
            p.selling_price AS product_selling_price,
            p.status AS product_status,
            pv.sku AS variant_sku,
            pv.size AS variant_size,
            pv.color AS variant_color,
            pv.color_code AS variant_color_code,
            pv.additional_price AS variant_additional_price,
            pv.stock_quantity AS variant_stock,
            pv.status AS variant_status,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS primary_image
        FROM cart_items ci
        INNER JOIN products p ON ci.product_id = p.id
        LEFT JOIN product_variants pv ON ci.variant_id = pv.id
        WHERE ci.cart_id = ? AND ci.saved_for_later = ?
        ORDER BY ci.created_at DESC
    ");
    $stmt->execute([$cartId, $savedForLater ? 1 : 0]);
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $row) {
        $addPrice = (float)($row['variant_additional_price'] ?? 0);
        $sellingPrice = (float)$row['product_selling_price'] + $addPrice;
        $originalPrice = (float)$row['product_base_price'] + $addPrice;
        $qty = (int)$row['quantity'];
        $stock = (int)($row['variant_stock'] ?? 0);

        $lineTotal = $sellingPrice * $qty;
        $lineOriginalTotal = $originalPrice * $qty;
        $discountPct = calculate_discount_percentage($originalPrice, $sellingPrice);

        $items[] = [
            'id' => (int)$row['cart_item_id'],
            'cart_id' => (int)$row['cart_id'],
            'product_id' => (int)$row['product_id'],
            'variant_id' => $row['variant_id'] ? (int)$row['variant_id'] : null,
            'product_name' => $row['product_name'],
            'product_slug' => $row['product_slug'],
            'sku' => $row['variant_sku'] ?? ('YTY-PROD-' . $row['product_id']),
            'size' => $row['variant_size'] ?? 'Standard Atelier',
            'color' => $row['variant_color'] ?? 'Atelier Palette',
            'color_code' => $row['variant_color_code'] ?? '#C6A15B',
            'quantity' => $qty,
            'stock_quantity' => $stock,
            'is_in_stock' => ($stock >= $qty && $row['product_status'] === 'active' && ($row['variant_status'] ?? 'active') === 'active'),
            'stock_available' => $stock,
            'unit_price' => $sellingPrice,
            'unit_original_price' => $originalPrice,
            'line_total' => $lineTotal,
            'line_original_total' => $lineOriginalTotal,
            'discount_percentage' => $discountPct,
            'image_url' => !empty($row['primary_image']) ? $row['primary_image'] : 'https://images.unsplash.com/photo-1515886657613-9f3515b0c78f?auto=format&fit=crop&w=600&q=80',
            'saved_for_later' => (bool)$row['saved_for_later']
        ];
    }

    return $items;
}

/**
 * Calculate comprehensive financial breakdown including dynamic coupons and shipping
 */
function calculate_cart_totals(array $activeItems): array {
    $subtotal = 0.0;
    $originalSubtotal = 0.0;
    $totalCount = 0;

    foreach ($activeItems as $item) {
        $subtotal += $item['line_total'];
        $originalSubtotal += $item['line_original_total'];
        $totalCount += $item['quantity'];
    }

    // Shipping calculations from store settings
    $freeShippingThreshold = (float)get_setting('free_shipping_threshold', '2999.00');
    $defaultShippingFee = (float)get_setting('default_shipping_charge', '199.00');

    if ($subtotal <= 0) {
        $shippingFee = 0.0;
        $isFreeShipping = false;
        $remainingForFreeShipping = $freeShippingThreshold;
    } elseif ($subtotal >= $freeShippingThreshold) {
        $shippingFee = 0.0;
        $isFreeShipping = true;
        $remainingForFreeShipping = 0.0;
    } else {
        $shippingFee = $defaultShippingFee;
        $isFreeShipping = false;
        $remainingForFreeShipping = max(0.0, $freeShippingThreshold - $subtotal);
    }

    // Dynamic Coupon Validation & Calculation
    $couponDiscount = 0.0;
    $appliedCoupon = $_SESSION['applied_coupon'] ?? null;
    $couponNotice = '';
    $couponValid = false;

    if ($appliedCoupon && $subtotal > 0) {
        $code = $appliedCoupon['code'] ?? '';
        // Verify from database on each calculation to enforce limits & rules
        $stmt = db()->prepare("
            SELECT * FROM coupons 
            WHERE code = ? AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();

        if ($coupon) {
            $now = time();
            $startDateValid = empty($coupon['start_date']) || $now >= strtotime($coupon['start_date']);
            $endDateValid = empty($coupon['end_date']) || $now <= strtotime($coupon['end_date']);
            $usageValid = ($coupon['usage_limit'] === null) || ($coupon['usage_count'] < $coupon['usage_limit']);

            if ($startDateValid && $endDateValid && $usageValid) {
                $minSpend = (float)$coupon['min_spend'];
                if ($subtotal >= $minSpend) {
                    $couponValid = true;
                    if ($coupon['discount_type'] === 'percentage') {
                        $rawDisc = ($subtotal * ((float)$coupon['discount_value'] / 100));
                        if ($coupon['max_discount'] !== null && (float)$coupon['max_discount'] > 0) {
                            $couponDiscount = min($rawDisc, (float)$coupon['max_discount']);
                        } else {
                            $couponDiscount = $rawDisc;
                        }
                    } else { // fixed
                        $couponDiscount = min((float)$coupon['discount_value'], $subtotal);
                    }
                    $couponDiscount = round($couponDiscount, 2);
                    $_SESSION['applied_coupon']['calculated_discount'] = $couponDiscount;
                } else {
                    $couponNotice = 'Privilege code ' . e($code) . ' requires a minimum bag value of ' . format_price($minSpend) . '.';
                    $_SESSION['applied_coupon']['calculated_discount'] = 0.0;
                }
            } else {
                $couponNotice = 'Privilege code ' . e($code) . ' is no longer eligible.';
                unset($_SESSION['applied_coupon']);
            }
        } else {
            $couponNotice = 'Privilege code ' . e($code) . ' is invalid or inactive.';
            unset($_SESSION['applied_coupon']);
        }
    } elseif ($subtotal <= 0) {
        $couponDiscount = 0.0;
    }

    $grandTotal = max(0.0, $subtotal - $couponDiscount + $shippingFee);
    $totalSavings = max(0.0, ($originalSubtotal - $subtotal) + $couponDiscount);

    return [
        'subtotal' => $subtotal,
        'original_subtotal' => $originalSubtotal,
        'item_count' => $totalCount,
        'shipping_fee' => $shippingFee,
        'free_shipping_threshold' => $freeShippingThreshold,
        'default_shipping_fee' => $defaultShippingFee,
        'is_free_shipping' => $isFreeShipping,
        'remaining_for_free_shipping' => $remainingForFreeShipping,
        'coupon_discount' => $couponDiscount,
        'applied_coupon' => $couponValid ? ($_SESSION['applied_coupon'] ?? null) : null,
        'coupon_notice' => $couponNotice,
        'grand_total' => $grandTotal,
        'total_savings' => $totalSavings
    ];
}

/* =====================================================================
   2. CART MUTATION OPERATIONS (SERVER-SIDE VALIDATED)
   ===================================================================== */

/**
 * Add a product variant to the cart with strict server-side validation
 */
function add_to_cart(int $productId, ?int $variantId, int $quantity = 1): array {
    if ($quantity < 1) {
        return ['success' => false, 'message' => 'Quantity must be at least 1 piece.'];
    }

    // 1. Verify Product exists and is active
    $pStmt = db()->prepare("SELECT id, name, status, selling_price FROM products WHERE id = ? LIMIT 1");
    $pStmt->execute([$productId]);
    $product = $pStmt->fetch();

    if (!$product || $product['status'] !== 'active') {
        return ['success' => false, 'message' => 'The selected luxury creation is currently unavailable.'];
    }

    // 2. Validate Variant
    $variant = null;
    if ($variantId) {
        $vStmt = db()->prepare("
            SELECT id, product_id, sku, size, color, stock_quantity, status 
            FROM product_variants 
            WHERE id = ? AND product_id = ? LIMIT 1
        ");
        $vStmt->execute([$variantId, $productId]);
        $variant = $vStmt->fetch();

        if (!$variant || $variant['status'] !== 'active') {
            return ['success' => false, 'message' => 'Selected atelier size/color variant is inactive or invalid.'];
        }
    } else {
        // Fallback to primary active variant
        $vStmt = db()->prepare("
            SELECT id, product_id, sku, size, color, stock_quantity, status 
            FROM product_variants 
            WHERE product_id = ? AND status = 'active' 
            ORDER BY id ASC LIMIT 1
        ");
        $vStmt->execute([$productId]);
        $variant = $vStmt->fetch();

        if (!$variant) {
            return ['success' => false, 'message' => 'No active atelier variant found for this creation.'];
        }
        $variantId = (int)$variant['id'];
    }

    $availableStock = (int)$variant['stock_quantity'];
    if ($availableStock <= 0) {
        return ['success' => false, 'message' => 'This atelier creation is currently sold out.'];
    }

    $cartId = get_or_create_cart_id();

    // 3. Check if already in cart (active item, not saved for later)
    $checkStmt = db()->prepare("
        SELECT id, quantity FROM cart_items 
        WHERE cart_id = ? AND product_id = ? AND variant_id = ? AND saved_for_later = 0 
        LIMIT 1
    ");
    $checkStmt->execute([$cartId, $productId, $variantId]);
    $existingItem = $checkStmt->fetch();

    if ($existingItem) {
        $newQty = (int)$existingItem['quantity'] + $quantity;
        if ($newQty > $availableStock) {
            return [
                'success' => false, 
                'message' => 'Atelier stock limit reached. Only ' . $availableStock . ' pieces available.'
            ];
        }

        $upd = db()->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$newQty, $existingItem['id']]);
    } else {
        if ($quantity > $availableStock) {
            return [
                'success' => false, 
                'message' => 'Requested quantity exceeds available atelier stock of ' . $availableStock . ' pieces.'
            ];
        }

        $ins = db()->prepare("
            INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, saved_for_later, created_at, updated_at) 
            VALUES (?, ?, ?, ?, 0, NOW(), NOW())
        ");
        $ins->execute([$cartId, $productId, $variantId, $quantity]);
    }

    // Touch cart updated_at
    db()->prepare("UPDATE carts SET updated_at = NOW() WHERE id = ?")->execute([$cartId]);

    return [
        'success' => true,
        'message' => 'Creation successfully added to your Shopping Bag.',
        'cart_count' => get_cart_count()
    ];
}

/**
 * Update quantity of a line item with bounds checking
 */
function update_cart_quantity(int $cartItemId, int $newQuantity): array {
    $cartId = get_or_create_cart_id();

    // Verify item belongs to this cart
    $stmt = db()->prepare("
        SELECT ci.id, ci.variant_id, ci.quantity, pv.stock_quantity, pv.status AS variant_status
        FROM cart_items ci
        LEFT JOIN product_variants pv ON ci.variant_id = pv.id
        WHERE ci.id = ? AND ci.cart_id = ?
        LIMIT 1
    ");
    $stmt->execute([$cartItemId, $cartId]);
    $item = $stmt->fetch();

    if (!$item) {
        return ['success' => false, 'message' => 'Bag item not found.'];
    }

    if ($newQuantity <= 0) {
        $del = db()->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?");
        $del->execute([$cartItemId, $cartId]);
        return ['success' => true, 'message' => 'Creation removed from Shopping Bag.', 'removed' => true];
    }

    $availableStock = (int)($item['stock_quantity'] ?? 0);
    if ($newQuantity > $availableStock) {
        // Clamp to max available stock
        $cappedQty = max(1, $availableStock);
        $upd = db()->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?");
        $upd->execute([$cappedQty, $cartItemId]);

        return [
            'success' => true, 
            'clamped' => true,
            'quantity' => $cappedQty,
            'message' => 'Atelier stock limit reached. Quantity adjusted to available maximum (' . $cappedQty . ').'
        ];
    }

    $upd = db()->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([$newQuantity, $cartItemId]);

    return ['success' => true, 'message' => 'Bag updated successfully.', 'quantity' => $newQuantity];
}

/**
 * Remove an item from the shopping cart
 */
function remove_cart_item(int $cartItemId): bool {
    $cartId = get_or_create_cart_id();
    $stmt = db()->prepare("DELETE FROM cart_items WHERE id = ? AND cart_id = ?");
    $stmt->execute([$cartItemId, $cartId]);
    return $stmt->rowCount() > 0;
}

/**
 * Toggle Save for Later status for an item
 */
function save_item_for_later(int $cartItemId): bool {
    $cartId = get_or_create_cart_id();
    $stmt = db()->prepare("
        UPDATE cart_items 
        SET saved_for_later = 1, updated_at = NOW() 
        WHERE id = ? AND cart_id = ?
    ");
    $stmt->execute([$cartItemId, $cartId]);
    return $stmt->rowCount() > 0;
}

/**
 * Move a saved item back to the active shopping cart
 */
function move_to_cart_from_saved(int $cartItemId): array {
    $cartId = get_or_create_cart_id();

    $stmt = db()->prepare("
        SELECT ci.id, ci.product_id, ci.variant_id, ci.quantity, pv.stock_quantity, pv.status AS variant_status, p.status AS product_status
        FROM cart_items ci
        INNER JOIN products p ON ci.product_id = p.id
        LEFT JOIN product_variants pv ON ci.variant_id = pv.id
        WHERE ci.id = ? AND ci.cart_id = ? AND ci.saved_for_later = 1
        LIMIT 1
    ");
    $stmt->execute([$cartItemId, $cartId]);
    $item = $stmt->fetch();

    if (!$item) {
        return ['success' => false, 'message' => 'Saved item not found.'];
    }

    if ($item['product_status'] !== 'active' || ($item['variant_status'] ?? 'active') !== 'active') {
        return ['success' => false, 'message' => 'This creation is no longer available.'];
    }

    $stock = (int)($item['stock_quantity'] ?? 0);
    if ($stock <= 0) {
        return ['success' => false, 'message' => 'This creation is currently sold out from the atelier.'];
    }

    // Check if item already exists in active bag
    $dupStmt = db()->prepare("
        SELECT id, quantity FROM cart_items 
        WHERE cart_id = ? AND product_id = ? AND variant_id = ? AND saved_for_later = 0 
        LIMIT 1
    ");
    $dupStmt->execute([$cartId, $item['product_id'], $item['variant_id']]);
    $activeItem = $dupStmt->fetch();

    if ($activeItem) {
        $combinedQty = min((int)$activeItem['quantity'] + (int)$item['quantity'], $stock);
        db()->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$combinedQty, $activeItem['id']]);
        db()->prepare("DELETE FROM cart_items WHERE id = ?")->execute([$cartItemId]);
    } else {
        $safeQty = min((int)$item['quantity'], $stock);
        db()->prepare("UPDATE cart_items SET saved_for_later = 0, quantity = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$safeQty, $cartItemId]);
    }

    return ['success' => true, 'message' => 'Creation moved to your active Shopping Bag.'];
}

/* =====================================================================
   3. GUEST-TO-CUSTOMER CART MERGING
   ===================================================================== */

/**
 * Safely merge guest session cart into authenticated customer cart upon login
 * Avoids duplicate variant rows, combines quantities bounded by stock, preserves customer data
 */
function merge_guest_cart_to_customer(int $customerId, string $guestSessionId): void {
    if (empty($guestSessionId) || $customerId <= 0) {
        return;
    }

    // Find active guest cart
    $gStmt = db()->prepare("
        SELECT id FROM carts 
        WHERE session_id = ? AND customer_id IS NULL AND status = 'active' 
        LIMIT 1
    ");
    $gStmt->execute([$guestSessionId]);
    $guestCartId = $gStmt->fetchColumn();

    if (!$guestCartId) {
        return;
    }

    // Find or create customer cart
    $cStmt = db()->prepare("
        SELECT id FROM carts 
        WHERE customer_id = ? AND status = 'active' 
        LIMIT 1
    ");
    $cStmt->execute([$customerId]);
    $customerCartId = $cStmt->fetchColumn();

    if (!$customerCartId) {
        // Re-assign the guest cart directly to the authenticated customer
        $assign = db()->prepare("
            UPDATE carts 
            SET customer_id = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $assign->execute([$customerId, $guestCartId]);
        return;
    }

    if ($guestCartId == $customerCartId) {
        return;
    }

    // Begin atomic merge transaction
    db()->beginTransaction();
    try {
        // Fetch all items from guest cart
        $itemsStmt = db()->prepare("
            SELECT ci.id, ci.product_id, ci.variant_id, ci.quantity, ci.saved_for_later,
                   pv.stock_quantity, pv.status AS variant_status, p.status AS product_status
            FROM cart_items ci
            INNER JOIN products p ON ci.product_id = p.id
            LEFT JOIN product_variants pv ON ci.variant_id = pv.id
            WHERE ci.cart_id = ?
        ");
        $itemsStmt->execute([$guestCartId]);
        $guestItems = $itemsStmt->fetchAll();

        foreach ($guestItems as $gItem) {
            // Only merge if product and variant are active and in stock
            $stock = (int)($gItem['stock_quantity'] ?? 0);
            if ($gItem['product_status'] !== 'active' || ($gItem['variant_status'] ?? 'active') !== 'active' || $stock <= 0) {
                continue;
            }

            // Check if customer cart has matching item with same saved_for_later status
            $mStmt = db()->prepare("
                SELECT id, quantity FROM cart_items 
                WHERE cart_id = ? AND product_id = ? AND variant_id = ? AND saved_for_later = ? 
                LIMIT 1
            ");
            $mStmt->execute([$customerCartId, $gItem['product_id'], $gItem['variant_id'], $gItem['saved_for_later']]);
            $existing = $mStmt->fetch();

            if ($existing) {
                // Combine quantities safely up to stock limit
                $newQty = min((int)$existing['quantity'] + (int)$gItem['quantity'], $stock);
                db()->prepare("UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$newQty, $existing['id']]);
                // Delete the guest item row
                db()->prepare("DELETE FROM cart_items WHERE id = ?")->execute([$gItem['id']]);
            } else {
                // Move item to customer cart with safe quantity
                $newQty = min((int)$gItem['quantity'], $stock);
                db()->prepare("UPDATE cart_items SET cart_id = ?, quantity = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$customerCartId, $newQty, $gItem['id']]);
            }
        }

        // Mark guest cart as converted
        db()->prepare("UPDATE carts SET status = 'converted', updated_at = NOW() WHERE id = ?")
            ->execute([$guestCartId]);

        db()->commit();
    } catch (Exception $e) {
        db()->rollBack();
    }
}

/* =====================================================================
   4. WISHLIST ENGINE (GUEST & AUTHENTICATED CUSTOMER)
   ===================================================================== */

/**
 * Add product to customer database wishlist or guest session wishlist
 */
function add_to_wishlist(int $productId): array {
    // Validate product exists and is active
    $stmt = db()->prepare("SELECT id, name FROM products WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$productId]);
    $prod = $stmt->fetch();

    if (!$prod) {
        return ['success' => false, 'message' => 'Luxury creation unavailable.'];
    }

    $customerId = $_SESSION['customer_id'] ?? null;

    if ($customerId) {
        $ins = db()->prepare("
            INSERT IGNORE INTO wishlists (customer_id, product_id, created_at) 
            VALUES (?, ?, NOW())
        ");
        $ins->execute([$customerId, $productId]);
    } else {
        if (!isset($_SESSION['guest_wishlist']) || !is_array($_SESSION['guest_wishlist'])) {
            $_SESSION['guest_wishlist'] = [];
        }
        $_SESSION['guest_wishlist'][$productId] = time();
    }

    return [
        'success' => true,
        'message' => 'Creation added to your Private Wishlist.',
        'wishlist_count' => get_wishlist_count()
    ];
}

/**
 * Remove product from customer or guest wishlist
 */
function remove_from_wishlist(int $productId): array {
    $customerId = $_SESSION['customer_id'] ?? null;

    if ($customerId) {
        $del = db()->prepare("DELETE FROM wishlists WHERE customer_id = ? AND product_id = ?");
        $del->execute([$customerId, $productId]);
    } else {
        if (isset($_SESSION['guest_wishlist'][$productId])) {
            unset($_SESSION['guest_wishlist'][$productId]);
        }
    }

    return [
        'success' => true,
        'message' => 'Creation removed from your Private Wishlist.',
        'wishlist_count' => get_wishlist_count()
    ];
}

/**
 * Check if a product is in the current user's wishlist
 */
function is_product_in_wishlist(int $productId): bool {
    $customerId = $_SESSION['customer_id'] ?? null;

    if ($customerId) {
        $stmt = db()->prepare("SELECT id FROM wishlists WHERE customer_id = ? AND product_id = ? LIMIT 1");
        $stmt->execute([$customerId, $productId]);
        return (bool)$stmt->fetchColumn();
    }

    return isset($_SESSION['guest_wishlist'][$productId]);
}

/**
 * Retrieve enriched list of products currently in customer or guest wishlist
 */
function get_wishlist_products(): array {
    $customerId = $_SESSION['customer_id'] ?? null;

    if ($customerId) {
        $stmt = db()->prepare("
            SELECT 
                w.id AS wishlist_id,
                w.created_at AS saved_at,
                p.id,
                p.name,
                p.slug,
                p.original_price,
                p.selling_price,
                p.status,
                (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS primary_image,
                (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 0 ORDER BY sort_order ASC LIMIT 1) AS hover_image,
                (SELECT SUM(stock_quantity) FROM product_variants WHERE product_id = p.id AND status = 'active') AS total_stock
            FROM wishlists w
            INNER JOIN products p ON w.product_id = p.id
            WHERE w.customer_id = ? AND p.status = 'active'
            ORDER BY w.created_at DESC
        ");
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    } else {
        $guestIds = array_keys($_SESSION['guest_wishlist'] ?? []);
        if (empty($guestIds)) {
            return [];
        }

        // Safe parameter binding for variable IN clause
        $placeholders = implode(',', array_fill(0, count($guestIds), '?'));
        $stmt = db()->prepare("
            SELECT 
                p.id,
                p.name,
                p.slug,
                p.original_price,
                p.selling_price,
                p.status,
                (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS primary_image,
                (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 0 ORDER BY sort_order ASC LIMIT 1) AS hover_image,
                (SELECT SUM(stock_quantity) FROM product_variants WHERE product_id = p.id AND status = 'active') AS total_stock
            FROM products p
            WHERE p.id IN ($placeholders) AND p.status = 'active'
            ORDER BY p.id DESC
        ");
        $stmt->execute($guestIds);
        return $stmt->fetchAll();
    }
}

/**
 * Move a wishlist creation directly to the shopping bag
 */
function move_wishlist_to_cart(int $productId, ?int $variantId = null): array {
    // Add to cart with quantity 1
    $addRes = add_to_cart($productId, $variantId, 1);
    if ($addRes['success']) {
        // Remove from wishlist
        remove_from_wishlist($productId);
        $addRes['message'] = 'Creation moved seamlessly to your Shopping Bag.';
    }
    return $addRes;
}

/**
 * Synchronize guest session wishlist items to customer account upon login
 */
function sync_guest_wishlist_to_customer(int $customerId): void {
    if (empty($_SESSION['guest_wishlist']) || !is_array($_SESSION['guest_wishlist']) || $customerId <= 0) {
        return;
    }

    $productIds = array_keys($_SESSION['guest_wishlist']);
    $stmt = db()->prepare("
        INSERT IGNORE INTO wishlists (customer_id, product_id, created_at) 
        VALUES (?, ?, NOW())
    ");

    foreach ($productIds as $pid) {
        $stmt->execute([$customerId, (int)$pid]);
    }

    unset($_SESSION['guest_wishlist']);
}

/* =====================================================================
   5. COUPON ENGINE (REAL MYSQL DATA VALIDATION)
   ===================================================================== */

/**
 * Validate and apply promotional or privilege coupon code
 */
function validate_and_apply_coupon(string $code, float $cartSubtotal, ?int $customerId = null): array {
    $cleanCode = strtoupper(trim($code));

    if (empty($cleanCode)) {
        return ['success' => false, 'message' => 'Please provide a valid privilege coupon code.'];
    }

    $stmt = db()->prepare("SELECT * FROM coupons WHERE code = ? LIMIT 1");
    $stmt->execute([$cleanCode]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        return ['success' => false, 'message' => 'Invalid privilege code. The entered code does not exist.'];
    }

    if ((int)$coupon['is_active'] !== 1) {
        return ['success' => false, 'message' => 'This privilege code is currently inactive.'];
    }

    $now = time();
    if (!empty($coupon['start_date']) && $now < strtotime($coupon['start_date'])) {
        return ['success' => false, 'message' => 'This privilege campaign has not yet commenced.'];
    }

    if (!empty($coupon['end_date']) && $now > strtotime($coupon['end_date'])) {
        return ['success' => false, 'message' => 'This privilege code has expired.'];
    }

    // Check overall usage limit
    if ($coupon['usage_limit'] !== null && (int)$coupon['usage_count'] >= (int)$coupon['usage_limit']) {
        return ['success' => false, 'message' => 'Privilege code quota has been fully exhausted.'];
    }

    // Check per-customer limit if user is authenticated
    if ($customerId && (int)$coupon['per_customer_limit'] > 0) {
        $redStmt = db()->prepare("
            SELECT COUNT(*) FROM coupon_redemptions 
            WHERE coupon_id = ? AND customer_id = ?
        ");
        $redStmt->execute([$coupon['id'], $customerId]);
        $customerRedemptions = (int)$redStmt->fetchColumn();

        if ($customerRedemptions >= (int)$coupon['per_customer_limit']) {
            return [
                'success' => false, 
                'message' => 'You have already redeemed this exclusive privilege code the maximum permitted times.'
            ];
        }
    }

    // Minimum order value validation
    $minSpend = (float)$coupon['min_spend'];
    if ($cartSubtotal < $minSpend) {
        return [
            'success' => false,
            'message' => 'Code requires a minimum bag value of ' . format_price($minSpend) . '. Your current bag total is ' . format_price($cartSubtotal) . '.'
        ];
    }

    // Calculate preliminary discount
    $discount = 0.0;
    if ($coupon['discount_type'] === 'percentage') {
        $discount = round(($cartSubtotal * ((float)$coupon['discount_value'] / 100)), 2);
        if ($coupon['max_discount'] !== null && (float)$coupon['max_discount'] > 0) {
            $discount = min($discount, (float)$coupon['max_discount']);
        }
    } else {
        $discount = min((float)$coupon['discount_value'], $cartSubtotal);
    }

    // Store in session (Notice: We do NOT increment usage_count or insert into coupon_redemptions here; that occurs upon final order placement)
    $_SESSION['applied_coupon'] = [
        'id' => (int)$coupon['id'],
        'code' => $coupon['code'],
        'description' => $coupon['description'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => (float)$coupon['discount_value'],
        'min_spend' => $minSpend,
        'max_discount' => $coupon['max_discount'] !== null ? (float)$coupon['max_discount'] : null,
        'calculated_discount' => $discount
    ];

    return [
        'success' => true,
        'message' => 'Privilege code ' . e($coupon['code']) . ' applied! You saved ' . format_price($discount) . '.',
        'discount' => $discount,
        'coupon' => $_SESSION['applied_coupon']
    ];
}

/**
 * Remove any active coupon from session
 */
function remove_applied_coupon(): void {
    unset($_SESSION['applied_coupon']);
}
