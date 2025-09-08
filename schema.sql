
CREATE DATABASE IF NOT EXISTS `expense_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `expense_db`;

CREATE TABLE IF NOT EXISTS `transactions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tx_date` DATE NOT NULL,
  `type` ENUM('income','expense') NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL CHECK (`amount` >= 0),
  `note` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tx_date_idx` (`tx_date`),
  KEY `type_idx` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
