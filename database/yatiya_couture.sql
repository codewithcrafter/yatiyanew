-- =====================================================================
-- YATIYA COUTURE - High-End Luxury Fashion E-Commerce Database Schema
-- Brand Vision: "ELEVATE YOUR EVERYDAY STYLE"
-- Storage Engine: InnoDB | Character Set: utf8mb4 | Collation: utf8mb4_unicode_ci
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables if they exist in reverse dependency order
DROP TABLE IF EXISTS `store_settings`;
DROP TABLE IF EXISTS `newsletter_subscribers`;
DROP TABLE IF EXISTS `return_requests`;
DROP TABLE IF EXISTS `banners`;
DROP TABLE IF EXISTS `product_reviews`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `coupon_redemptions`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `coupons`;
DROP TABLE IF EXISTS `wishlists`;
DROP TABLE IF EXISTS `cart_items`;
DROP TABLE IF EXISTS `carts`;
DROP TABLE IF EXISTS `customer_addresses`;
DROP TABLE IF EXISTS `inventory_movements`;
DROP TABLE IF EXISTS `product_variants`;
DROP TABLE IF EXISTS `product_images`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `brands`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `customers`;
DROP TABLE IF EXISTS `admin_users`;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- 1. Table: admin_users
-- Internal administrators with role-based access control
-- ---------------------------------------------------------------------
CREATE TABLE `admin_users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `role` ENUM('super_admin', 'admin', 'editor', 'support') NOT NULL DEFAULT 'admin',
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admin_username` (`username`),
    UNIQUE KEY `uq_admin_email` (`email`),
    INDEX `idx_admin_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Table: customers
-- Registered customer accounts with secure password hashes
-- ---------------------------------------------------------------------
CREATE TABLE `customers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `phone` VARCHAR(30) NULL DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    `email_verified_at` DATETIME NULL DEFAULT NULL,
    `reset_token_hash` VARCHAR(255) NULL DEFAULT NULL,
    `reset_token_expires_at` DATETIME NULL DEFAULT NULL,
    `remember_token` VARCHAR(255) NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_customer_email` (`email`),
    INDEX `idx_customer_phone` (`phone`),
    INDEX `idx_customer_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Table: categories
-- Hierarchical luxury fashion catalogue taxonomy
-- ---------------------------------------------------------------------
CREATE TABLE `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id` INT UNSIGNED NULL DEFAULT NULL,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `image_url` VARCHAR(255) NULL DEFAULT NULL,
    `banner_url` VARCHAR(255) NULL DEFAULT NULL,
    `display_order` INT NOT NULL DEFAULT 0,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_category_slug` (`slug`),
    INDEX `idx_category_parent` (`parent_id`),
    INDEX `idx_category_order` (`display_order`, `is_active`),
    CONSTRAINT `fk_cat_parent` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Table: brands
-- Brand labels and atelier collections (Single-brand with house lines)
-- ---------------------------------------------------------------------
CREATE TABLE `brands` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `slug` VARCHAR(120) NOT NULL,
    `logo_url` VARCHAR(255) NULL DEFAULT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_brand_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 5. Table: products
-- Master fashion products catalogue
-- ---------------------------------------------------------------------
CREATE TABLE `products` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NOT NULL,
    `brand_id` INT UNSIGNED NULL DEFAULT NULL,
    `name` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,
    `sku` VARCHAR(100) NOT NULL,
    `short_description` VARCHAR(500) NULL DEFAULT NULL,
    `description` LONGTEXT NULL DEFAULT NULL,
    `specifications` JSON NULL DEFAULT NULL,
    `original_price` DECIMAL(10,2) NOT NULL,
    `selling_price` DECIMAL(10,2) NOT NULL,
    `cost_price` DECIMAL(10,2) NULL DEFAULT NULL,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `low_stock_threshold` INT NOT NULL DEFAULT 5,
    `is_featured` TINYINT(1) NOT NULL DEFAULT 0,
    `is_new` TINYINT(1) NOT NULL DEFAULT 1,
    `is_bestseller` TINYINT(1) NOT NULL DEFAULT 0,
    `average_rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `review_count` INT NOT NULL DEFAULT 0,
    `status` ENUM('draft', 'active', 'archived') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_product_slug` (`slug`),
    UNIQUE KEY `uq_product_sku` (`sku`),
    INDEX `idx_prod_category` (`category_id`),
    INDEX `idx_prod_brand` (`brand_id`),
    INDEX `idx_prod_status` (`status`),
    INDEX `idx_prod_price` (`selling_price`),
    INDEX `idx_prod_flags` (`is_featured`, `is_new`, `is_bestseller`),
    CONSTRAINT `fk_prod_cat` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_prod_brand` FOREIGN KEY (`brand_id`) REFERENCES `brands` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Table: product_images
-- High-resolution editorial gallery images for products
-- ---------------------------------------------------------------------
CREATE TABLE `product_images` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `image_url` VARCHAR(255) NOT NULL,
    `alt_text` VARCHAR(255) NULL DEFAULT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_pimg_product` (`product_id`, `is_primary`, `sort_order`),
    CONSTRAINT `fk_pimg_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Table: product_variants
-- Product size, color, SKU variants with discrete stock counts
-- ---------------------------------------------------------------------
CREATE TABLE `product_variants` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `sku` VARCHAR(100) NOT NULL,
    `size` VARCHAR(50) NULL DEFAULT NULL,
    `color` VARCHAR(50) NULL DEFAULT NULL,
    `color_code` VARCHAR(20) NULL DEFAULT NULL,
    `additional_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `stock_quantity` INT NOT NULL DEFAULT 0,
    `image_url` VARCHAR(255) NULL DEFAULT NULL,
    `status` ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_pvar_sku` (`sku`),
    INDEX `idx_pvar_product` (`product_id`, `status`),
    INDEX `idx_pvar_options` (`product_id`, `size`, `color`),
    CONSTRAINT `fk_pvar_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. Table: inventory_movements
-- Immutable ledger of all inventory transactions and adjustments
-- ---------------------------------------------------------------------
CREATE TABLE `inventory_movements` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NULL DEFAULT NULL,
    `movement_type` ENUM('initial', 'purchase', 'order_sale', 'order_cancel', 'return_restock', 'adjustment') NOT NULL,
    `quantity` INT NOT NULL COMMENT 'positive for inbound, negative for outbound',
    `balance_after` INT NOT NULL DEFAULT 0,
    `reference_id` VARCHAR(100) NULL DEFAULT NULL,
    `note` VARCHAR(255) NULL DEFAULT NULL,
    `created_by` INT UNSIGNED NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_inv_product` (`product_id`, `created_at`),
    INDEX `idx_inv_variant` (`variant_id`),
    CONSTRAINT `fk_inv_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_inv_var` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL,
    CONSTRAINT `fk_inv_admin` FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. Table: customer_addresses
-- Shipping and billing addresses associated with customers
-- ---------------------------------------------------------------------
CREATE TABLE `customer_addresses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `address_type` ENUM('home', 'work', 'billing', 'shipping', 'other') NOT NULL DEFAULT 'home',
    `full_name` VARCHAR(120) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `address_line1` VARCHAR(255) NOT NULL,
    `address_line2` VARCHAR(255) NULL DEFAULT NULL,
    `landmark` VARCHAR(150) NULL DEFAULT NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `postal_code` VARCHAR(20) NOT NULL,
    `country` VARCHAR(100) NOT NULL DEFAULT 'India',
    `is_default_shipping` TINYINT(1) NOT NULL DEFAULT 0,
    `is_default_billing` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_addr_customer` (`customer_id`),
    CONSTRAINT `fk_addr_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. Table: carts
-- Persistent shopping carts supporting both guests and authenticated users
-- ---------------------------------------------------------------------
CREATE TABLE `carts` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NULL DEFAULT NULL,
    `session_id` VARCHAR(128) NULL DEFAULT NULL,
    `status` ENUM('active', 'converted', 'abandoned') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cart_session` (`session_id`),
    INDEX `idx_cart_customer` (`customer_id`, `status`),
    CONSTRAINT `fk_cart_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. Table: cart_items
-- Individual line items in active shopping carts
-- ---------------------------------------------------------------------
CREATE TABLE `cart_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cart_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NULL DEFAULT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `saved_for_later` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ci_cart` (`cart_id`, `saved_for_later`),
    INDEX `idx_ci_product` (`product_id`),
    CONSTRAINT `fk_ci_cart` FOREIGN KEY (`cart_id`) REFERENCES `carts` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ci_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ci_var` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. Table: wishlists
-- Saved items for registered customers
-- ---------------------------------------------------------------------
CREATE TABLE `wishlists` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_wishlist_customer_product` (`customer_id`, `product_id`),
    INDEX `idx_wishlist_cust` (`customer_id`),
    CONSTRAINT `fk_wish_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wish_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 13. Table: coupons
-- Discount codes with percentage or fixed reductions and validity constraints
-- ---------------------------------------------------------------------
CREATE TABLE `coupons` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL,
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL,
    `min_spend` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `max_discount` DECIMAL(10,2) NULL DEFAULT NULL,
    `start_date` DATETIME NULL DEFAULT NULL,
    `end_date` DATETIME NULL DEFAULT NULL,
    `usage_limit` INT NULL DEFAULT NULL,
    `usage_count` INT NOT NULL DEFAULT 0,
    `per_customer_limit` INT NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_coupon_code` (`code`),
    INDEX `idx_coupon_active` (`is_active`, `start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14. Table: orders
-- Customer sales orders with comprehensive status tracking and delivery addresses
-- ---------------------------------------------------------------------
CREATE TABLE `orders` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_number` VARCHAR(50) NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `coupon_id` INT UNSIGNED NULL DEFAULT NULL,
    `coupon_code` VARCHAR(50) NULL DEFAULT NULL,
    `shipping_charge` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `grand_total` DECIMAL(10,2) NOT NULL,
    `payment_method` ENUM('cod', 'online') NOT NULL DEFAULT 'cod',
    `payment_status` ENUM('pending', 'paid', 'failed', 'refunded', 'partially_refunded') NOT NULL DEFAULT 'pending',
    `order_status` ENUM('placed', 'confirmed', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'cancelled', 'returned') NOT NULL DEFAULT 'placed',
    `shipping_full_name` VARCHAR(120) NOT NULL,
    `shipping_phone` VARCHAR(30) NOT NULL,
    `shipping_address_line1` VARCHAR(255) NOT NULL,
    `shipping_address_line2` VARCHAR(255) NULL DEFAULT NULL,
    `shipping_landmark` VARCHAR(150) NULL DEFAULT NULL,
    `shipping_city` VARCHAR(100) NOT NULL,
    `shipping_state` VARCHAR(100) NOT NULL,
    `shipping_postal_code` VARCHAR(20) NOT NULL,
    `shipping_country` VARCHAR(100) NOT NULL DEFAULT 'India',
    `billing_full_name` VARCHAR(120) NULL DEFAULT NULL,
    `billing_phone` VARCHAR(30) NULL DEFAULT NULL,
    `billing_address_line1` VARCHAR(255) NULL DEFAULT NULL,
    `billing_address_line2` VARCHAR(255) NULL DEFAULT NULL,
    `billing_city` VARCHAR(100) NULL DEFAULT NULL,
    `billing_state` VARCHAR(100) NULL DEFAULT NULL,
    `billing_postal_code` VARCHAR(20) NULL DEFAULT NULL,
    `billing_country` VARCHAR(100) NULL DEFAULT NULL,
    `customer_notes` TEXT NULL DEFAULT NULL,
    `admin_notes` TEXT NULL DEFAULT NULL,
    `tracking_number` VARCHAR(100) NULL DEFAULT NULL,
    `courier_name` VARCHAR(100) NULL DEFAULT NULL,
    `placed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_order_number` (`order_number`),
    INDEX `idx_ord_customer` (`customer_id`, `placed_at`),
    INDEX `idx_ord_status` (`order_status`, `payment_status`),
    CONSTRAINT `fk_ord_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_ord_coup` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 15. Table: order_items
-- Frozen line items snapshot for each completed order
-- ---------------------------------------------------------------------
CREATE TABLE `order_items` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `product_id` INT UNSIGNED NOT NULL,
    `variant_id` INT UNSIGNED NULL DEFAULT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `variant_name` VARCHAR(255) NULL DEFAULT NULL,
    `sku` VARCHAR(100) NOT NULL,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `quantity` INT NOT NULL,
    `total_price` DECIMAL(10,2) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_oi_order` (`order_id`),
    INDEX `idx_oi_product` (`product_id`),
    CONSTRAINT `fk_oi_ord` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_oi_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_oi_var` FOREIGN KEY (`variant_id`) REFERENCES `product_variants` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 16. Table: coupon_redemptions
-- Audit history of redeemed promo coupons per order
-- ---------------------------------------------------------------------
CREATE TABLE `coupon_redemptions` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `coupon_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `order_id` INT UNSIGNED NOT NULL,
    `discount_amount` DECIMAL(10,2) NOT NULL,
    `redeemed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cred_coupon_customer` (`coupon_id`, `customer_id`),
    INDEX `idx_cred_order` (`order_id`),
    CONSTRAINT `fk_cred_coup` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_cred_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_cred_ord` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 17. Table: payments
-- Payment transactions with gateway responses and signature verification
-- ---------------------------------------------------------------------
CREATE TABLE `payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `payment_method` ENUM('cod', 'razorpay', 'stripe', 'other') NOT NULL,
    `transaction_id` VARCHAR(191) NULL DEFAULT NULL,
    `gateway_order_id` VARCHAR(191) NULL DEFAULT NULL,
    `payment_gateway_response` LONGTEXT NULL DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'INR',
    `status` ENUM('initiated', 'pending', 'successful', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    `signature` VARCHAR(255) NULL DEFAULT NULL,
    `payment_date` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_pay_order` (`order_id`),
    INDEX `idx_pay_tx` (`transaction_id`),
    INDEX `idx_pay_status` (`status`),
    CONSTRAINT `fk_pay_ord` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 18. Table: product_reviews
-- Customer reviews and verified buyer ratings
-- ---------------------------------------------------------------------
CREATE TABLE `product_reviews` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `product_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `order_id` INT UNSIGNED NULL DEFAULT NULL,
    `rating` TINYINT NOT NULL COMMENT '1 to 5 stars',
    `review_title` VARCHAR(255) NOT NULL,
    `review_text` TEXT NOT NULL,
    `is_verified_buyer` TINYINT(1) NOT NULL DEFAULT 0,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `helpful_count` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_rev_product` (`product_id`, `status`),
    INDEX `idx_rev_customer` (`customer_id`),
    CONSTRAINT `fk_rev_prod` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rev_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_rev_ord` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 19. Table: banners
-- Editorial hero sliders, promo highlights and campaign banners
-- ---------------------------------------------------------------------
CREATE TABLE `banners` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `subtitle` VARCHAR(255) NULL DEFAULT NULL,
    `badge_text` VARCHAR(100) NULL DEFAULT NULL,
    `cta_text` VARCHAR(100) NOT NULL DEFAULT 'Shop Collection',
    `cta_link` VARCHAR(255) NOT NULL DEFAULT '/shop.php',
    `image_url` VARCHAR(255) NOT NULL,
    `mobile_image_url` VARCHAR(255) NULL DEFAULT NULL,
    `position` ENUM('hero', 'promo', 'exclusive_offer', 'middle_banner') NOT NULL DEFAULT 'hero',
    `display_order` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `starts_at` DATETIME NULL DEFAULT NULL,
    `ends_at` DATETIME NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_banner_pos` (`position`, `is_active`, `display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 20. Table: return_requests
-- Post-purchase order returns, replacement requests and refund audit
-- ---------------------------------------------------------------------
CREATE TABLE `return_requests` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` INT UNSIGNED NOT NULL,
    `order_item_id` INT UNSIGNED NOT NULL,
    `customer_id` INT UNSIGNED NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `reason_description` TEXT NULL DEFAULT NULL,
    `proof_images` TEXT NULL DEFAULT NULL COMMENT 'JSON array of image paths',
    `status` ENUM('requested', 'approved', 'pickup_scheduled', 'item_received', 'refund_processed', 'rejected') NOT NULL DEFAULT 'requested',
    `refund_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `admin_remarks` TEXT NULL DEFAULT NULL,
    `requested_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_ret_customer` (`customer_id`),
    INDEX `idx_ret_status` (`status`),
    CONSTRAINT `fk_ret_ord` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ret_oi` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_ret_cust` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 21. Table: newsletter_subscribers
-- Newsletter email signups for luxury editorial drops and private sales
-- ---------------------------------------------------------------------
CREATE TABLE `newsletter_subscribers` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email` VARCHAR(191) NOT NULL,
    `status` ENUM('subscribed', 'unsubscribed') NOT NULL DEFAULT 'subscribed',
    `source` VARCHAR(50) NOT NULL DEFAULT 'footer',
    `subscribed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `unsubscribed_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_nl_email` (`email`),
    INDEX `idx_nl_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 22. Table: store_settings
-- Global brand configuration, business thresholds and dynamic store flags
-- ---------------------------------------------------------------------
CREATE TABLE `store_settings` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key` VARCHAR(100) NOT NULL,
    `setting_value` TEXT NULL DEFAULT NULL,
    `setting_group` VARCHAR(50) NOT NULL DEFAULT 'general',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_st_key` (`setting_key`),
    INDEX `idx_st_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- SEED DATA INSERTION
-- Initial system users, brand labels, 12 core categories and store settings
-- =====================================================================

-- 1. Default Super Administrator (Password: Admin@Yatiya2026!)
INSERT INTO `admin_users` (`username`, `email`, `password_hash`, `full_name`, `role`, `status`)
VALUES (
    'admin',
    'admin@yatiyacouture.com',
    '$2y$10$k6Ne44kKbNR6vJn4i0.DQ.z9RSCD2QoUqaR0nowqrm/IPSj8pK3J2',
    'Yatiya Executive Atelier',
    'super_admin',
    'active'
);

-- 2. House Brand & Collections
INSERT INTO `brands` (`name`, `slug`, `logo_url`, `description`, `is_active`)
VALUES
('Yatiya Couture', 'yatiya-couture', '/assets/images/brand-logo.svg', 'The flagship luxury house defining bespoke craftsmanship, royal silks and timeless silhouettes.', 1),
('Yatiya Privé', 'yatiya-prive', '/assets/images/brand-prive.svg', 'Bespoke limited-run couture and red-carpet evening wear.', 1),
('Yatiya Atelier', 'yatiya-atelier', '/assets/images/brand-atelier.svg', 'Contemporary daily luxury essentials crafted from ethically sourced handlooms.', 1);

-- 3. Initial 12 Categories (as strictly specified in Section 3)
INSERT INTO `categories` (`id`, `parent_id`, `name`, `slug`, `description`, `image_url`, `display_order`, `is_featured`, `is_active`)
VALUES
(1, NULL, 'Women', 'women', 'Haute couture gowns, silk saris, tailored blazers and contemporary ensembles for the discerning woman.', '/assets/images/categories/women.jpg', 1, 1, 1),
(2, NULL, 'Men', 'men', 'Impeccably tailored bandhgalas, raw silk kurtas, bespoke suits and structured outerwear for men.', '/assets/images/categories/men.jpg', 2, 1, 1),
(3, NULL, 'Kids', 'kids', 'Delicate celebratory occasion-wear, pure cotton staples and royal miniature couture.', '/assets/images/categories/kids.jpg', 3, 0, 1),
(4, NULL, 'Clothing', 'clothing', 'Artisanal dresses, embroidered jackets, heirloom drapes and refined everyday separates.', '/assets/images/categories/clothing.jpg', 4, 1, 1),
(5, NULL, 'Footwear', 'footwear', 'Handcrafted leather juttis, embroidered mojaris, sculpted heels and classic dress shoes.', '/assets/images/categories/footwear.jpg', 5, 1, 1),
(6, NULL, 'Bags', 'bags', 'Fine leather totes, jewel-embellished potlis, minaudières and executive structured satchels.', '/assets/images/categories/bags.jpg', 6, 1, 1),
(7, NULL, 'Accessories', 'accessories', 'Pure cashmere shawls, silk pocket squares, monogrammed leather belts and handcrafted stoles.', '/assets/images/categories/accessories.jpg', 7, 1, 1),
(8, NULL, 'Jewellery', 'jewellery', 'Heirloom polki chokers, 24K gold-gilded statement earrings, kundan cuffs and baroque pearls.', '/assets/images/categories/jewellery.jpg', 8, 1, 1),
(9, NULL, 'Beauty & Lifestyle', 'beauty-lifestyle', 'Pure oud fragrances, botanical wellness elixirs, artisanal scented candles and luxury lifestyle accessories.', '/assets/images/categories/beauty.jpg', 9, 0, 1),
(10, NULL, 'New Arrivals', 'new-arrivals', 'The newest runway releases from the Autumn/Winter 2026 Haute Couture collection.', '/assets/images/categories/new-arrivals.jpg', 10, 1, 1),
(11, NULL, 'Best Sellers', 'best-sellers', 'Iconic signatures, enduring silhouettes and globally acclaimed couture pieces.', '/assets/images/categories/bestsellers.jpg', 11, 1, 1),
(12, NULL, 'Sale', 'sale', 'Rare private archive offerings and privileged curations for our couture patrons.', '/assets/images/categories/sale.jpg', 12, 1, 1);

-- 4. Store Settings
INSERT INTO `store_settings` (`setting_key`, `setting_value`, `setting_group`)
VALUES
('store_name', 'YATIYA COUTURE', 'general'),
('store_tagline', 'Elevate Your Everyday Style', 'general'),
('store_email', 'concierge@yatiyacouture.com', 'general'),
('store_phone', '+91 (0) 11 4987 6543', 'general'),
('store_address', 'The Haute Promenade, 42 Luxury Boulevard, New Delhi, 110001, India', 'general'),
('currency_code', 'INR', 'localization'),
('currency_symbol', '₹', 'localization'),
('tax_rate_percent', '12.00', 'finance'),
('free_shipping_threshold', '2999.00', 'shipping'),
('default_shipping_charge', '199.00', 'shipping'),
('announcement_bar_text', 'Complimentary White-Glove Delivery Across India on Orders Above ₹2,999 | Discover The Winter Silk Collection', 'marketing'),
('announcement_bar_link', 'shop.php?category=new-arrivals', 'marketing'),
('social_instagram', 'https://instagram.com/yatiyacouture', 'social'),
('social_pinterest', 'https://pinterest.com/yatiyacouture', 'social'),
('social_facebook', 'https://facebook.com/yatiyacouture', 'social');

-- 5. Promotional Banners
INSERT INTO `banners` (`title`, `subtitle`, `badge_text`, `cta_text`, `cta_link`, `image_url`, `position`, `display_order`, `is_active`)
VALUES
('The Royal Silk & Velvet Edit', 'Autumn / Winter Haute Couture 2026', 'NEW RUNWAY COLLECTION', 'Explore Collection', 'shop.php?category=clothing', 'https://images.unsplash.com/photo-1539109136881-3be0616acf4b?auto=format&fit=crop&w=1920&q=80', 'hero', 1, 1),
('Artisanal Footwear & Handcrafted Leather', 'Sculpted in full-grain calfskin with antique brass accents', 'SIGNATURE PIECES', 'Discover Leather', 'shop.php?category=bags', 'https://images.unsplash.com/photo-1490481651871-ab68de25d43d?auto=format&fit=crop&w=1920&q=80', 'hero', 2, 1),
('Heirloom Jewels & Atelier Accents', 'Gilded in 24K gold with untreated freshwater pearls', 'LIMITED EDITION', 'View Jewellery', 'shop.php?category=jewellery', 'https://images.unsplash.com/photo-1515562141207-7a88fb7ce338?auto=format&fit=crop&w=1920&q=80', 'hero', 3, 1),
('Bespoke Bridal & Red-Carpet Privé', 'Tailored to perfection by master artisans with over 400 hours of hand embroidery', 'HAUTE COUTURE', 'Book Atelier Appointment', 'contact.php', 'https://images.unsplash.com/photo-1509631179647-0177331693ae?auto=format&fit=crop&w=1600&q=80', 'exclusive_offer', 1, 1);

-- 6. Initial Welcome Promo Coupons
INSERT INTO `coupons` (`code`, `description`, `discount_type`, `discount_value`, `min_spend`, `max_discount`, `usage_limit`, `per_customer_limit`, `is_active`)
VALUES
('YATIYA10', 'Privileged Welcome - 10% Off On Your First Luxury Order', 'percentage', 10.00, 1999.00, 2500.00, 1000, 1, 1),
('HAUTE500', 'Flat ₹500 Privilege Privilege Discount On Orders Above ₹4,999', 'fixed', 500.00, 4999.00, 500.00, 500, 1, 1);

