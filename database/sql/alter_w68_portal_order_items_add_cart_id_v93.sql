USE `core4_sales_order`;

SET @w68_db := DATABASE();

SET @w68_add_cart_id := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @w68_db
          AND TABLE_NAME = 'w68_portal_order_items'
          AND COLUMN_NAME = 'cart_id'
    ),
    'SELECT 1',
    'ALTER TABLE `w68_portal_order_items` ADD COLUMN `cart_id` BIGINT UNSIGNED NULL AFTER `w68_portal_order_id`'
);
PREPARE w68_stmt FROM @w68_add_cart_id;
EXECUTE w68_stmt;
DEALLOCATE PREPARE w68_stmt;

SET @w68_add_cart_idx := IF(
    EXISTS(
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @w68_db
          AND TABLE_NAME = 'w68_portal_order_items'
          AND INDEX_NAME = 'w68_portal_items_cart_idx'
    ),
    'SELECT 1',
    'ALTER TABLE `w68_portal_order_items` ADD INDEX `w68_portal_items_cart_idx` (`cart_id`)'
);
PREPARE w68_stmt FROM @w68_add_cart_idx;
EXECUTE w68_stmt;
DEALLOCATE PREPARE w68_stmt;

SELECT
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @w68_db
  AND TABLE_NAME = 'w68_portal_order_items'
  AND COLUMN_NAME = 'cart_id';
