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
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
