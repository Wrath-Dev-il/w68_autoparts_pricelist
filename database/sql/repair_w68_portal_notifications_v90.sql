USE `core4_sales_order`;

CREATE TABLE IF NOT EXISTS `w68_portal_order_notifications` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `w68_portal_order_id` BIGINT UNSIGNED NOT NULL,
    `login_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `sales_note_id` BIGINT UNSIGNED DEFAULT NULL,
    `order_code` VARCHAR(32) DEFAULT NULL,
    `sales_number` VARCHAR(255) DEFAULT NULL,
    `event_type` VARCHAR(50) NOT NULL,
    `event_value` VARCHAR(255) DEFAULT NULL,
    `title` VARCHAR(191) NOT NULL,
    `message` TEXT DEFAULT NULL,
    `event_at` TIMESTAMP NULL DEFAULT NULL,
    `is_read` TINYINT(1) NOT NULL DEFAULT 0,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `w68_portal_notification_event_unique` (`w68_portal_order_id`, `event_type`),
    KEY `w68_portal_notification_owner_unread_idx` (`login_id`, `customer_id`, `is_read`),
    KEY `w68_portal_notification_order_idx` (`w68_portal_order_id`),
    KEY `w68_portal_notification_note_idx` (`sales_note_id`),
    KEY `w68_portal_notification_event_at_idx` (`event_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `w68_portal_orders`
    ADD COLUMN IF NOT EXISTS `notification_is_read` TINYINT(1) NOT NULL DEFAULT 1 AFTER `portal_status`;

ALTER TABLE `w68_portal_orders`
    ADD COLUMN IF NOT EXISTS `notification_read_at` TIMESTAMP NULL DEFAULT NULL AFTER `notification_is_read`;
