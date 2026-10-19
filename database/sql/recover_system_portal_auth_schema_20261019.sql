-- W68_SYSTEM_PORTAL_AUTH_SCHEMA_RECOVERY_20261019
-- Schema only. No customer credentials or private backup rows are included.
-- Intended for an EMPTY replacement core4_system_proposal database.

CREATE TABLE IF NOT EXISTS `logins` (
  `login_ID` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_type` int NOT NULL DEFAULT 3,
  `User_ID` varchar(255) NOT NULL,
  `Email` varchar(255) DEFAULT NULL,
  `OTP_CODE` int DEFAULT NULL,
  `Password` varchar(255) NOT NULL,
  `User_First_Name` varchar(255) NOT NULL,
  `User_Middle_Name` varchar(255) DEFAULT NULL,
  `User_Last_Name` varchar(255) NOT NULL,
  `Gender` varchar(255) NOT NULL,
  `profile_picture_mime` varchar(255) DEFAULT NULL,
  `profile_picture` longblob DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`login_ID`),
  UNIQUE KEY `logins_user_id_unique` (`User_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `customer_portal_accounts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `customer_id` bigint unsigned NOT NULL,
  `login_id` bigint unsigned NOT NULL,
  `authorization_id` bigint unsigned DEFAULT NULL,
  `linked_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `customer_portal_accounts_customer_id_unique` (`customer_id`),
  UNIQUE KEY `customer_portal_accounts_login_id_unique` (`login_id`),
  KEY `customer_portal_accounts_authorization_id_index` (`authorization_id`),
  CONSTRAINT `customer_portal_accounts_login_id_foreign`
    FOREIGN KEY (`login_id`) REFERENCES `logins` (`login_ID`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
