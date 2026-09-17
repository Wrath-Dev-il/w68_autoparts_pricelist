USE `core4_sales_order`;

CREATE TABLE IF NOT EXISTS `w68_customer_cart_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `login_id` BIGINT UNSIGNED NOT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `is_selected` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `w68_cart_login_product_unique` (`login_id`, `product_id`),
    KEY `w68_cart_customer_idx` (`customer_id`),
    KEY `w68_cart_product_idx` (`product_id`),
    KEY `w68_cart_selected_idx` (`login_id`, `is_selected`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `w68_portal_orders` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_code` VARCHAR(32) DEFAULT NULL,
    `login_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `sales_note_id` BIGINT UNSIGNED NOT NULL,
    `sales_number` VARCHAR(255) NOT NULL,
    `original_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount_total` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `total_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `portal_status` VARCHAR(50) NOT NULL DEFAULT 'Processed',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `w68_portal_orders_order_code_unique` (`order_code`),
    UNIQUE KEY `w68_portal_orders_sales_note_unique` (`sales_note_id`),
    KEY `w68_portal_orders_login_customer_idx` (`login_id`, `customer_id`),
    KEY `w68_portal_orders_customer_idx` (`customer_id`),
    KEY `w68_portal_orders_created_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `w68_portal_order_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `w68_portal_order_id` BIGINT UNSIGNED NOT NULL,
    `sales_note_item_id` BIGINT UNSIGNED DEFAULT NULL,
    `product_id` BIGINT UNSIGNED NOT NULL,
    `product_code` VARCHAR(255) NOT NULL,
    `part_number` VARCHAR(255) DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `application` VARCHAR(255) DEFAULT NULL,
    `position` VARCHAR(255) DEFAULT NULL,
    `brand` VARCHAR(255) DEFAULT NULL,
    `original_unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `discount_percent` DECIMAL(7,2) NOT NULL DEFAULT 0.00,
    `discounted_unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `quantity` INT UNSIGNED NOT NULL DEFAULT 1,
    `total_price` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `w68_portal_order_product_unique` (`w68_portal_order_id`, `product_id`),
    KEY `w68_portal_items_sales_note_item_idx` (`sales_note_item_id`),
    KEY `w68_portal_items_product_idx` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
