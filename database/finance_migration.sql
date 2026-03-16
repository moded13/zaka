-- ============================================================
-- Finance Module Migration
-- Run this in phpMyAdmin or via MySQL CLI AFTER the main schema.sql
-- Adds tables: income_categories, expense_categories,
--              receipt_books, finance_income, finance_expenses
-- And alters: finance_income to add book_id FK
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ============================================================
-- income_categories
-- ============================================================
CREATE TABLE IF NOT EXISTS `income_categories` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(200) NOT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_income_cat_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- expense_categories
-- ============================================================
CREATE TABLE IF NOT EXISTS `expense_categories` (
  `id`         INT          NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(200) NOT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_expense_cat_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- receipt_books
-- ============================================================
CREATE TABLE IF NOT EXISTS `receipt_books` (
  `id`               INT          NOT NULL AUTO_INCREMENT,
  `book_no`          VARCHAR(50)  NOT NULL,
  `start_receipt_no` INT          NOT NULL,
  `end_receipt_no`   INT          NOT NULL,
  `total_receipts`   INT          NOT NULL DEFAULT 0,
  `received_date`    DATE         NOT NULL,
  `status`           ENUM('open','closed') NOT NULL DEFAULT 'open',
  `notes`            TEXT         DEFAULT NULL,
  `created_by`       INT          DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_book_no` (`book_no`),
  KEY `idx_rb_status`  (`status`),
  KEY `idx_rb_created_by` (`created_by`),
  CONSTRAINT `fk_rb_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- finance_income
-- ============================================================
CREATE TABLE IF NOT EXISTS `finance_income` (
  `id`             INT            NOT NULL AUTO_INCREMENT,
  `book_id`        INT            DEFAULT NULL,
  `receipt_no`     VARCHAR(50)    NOT NULL,
  `income_date`    DATE           NOT NULL,
  `donor_name`     VARCHAR(200)   DEFAULT NULL,
  `category_id`    INT            DEFAULT NULL,
  `amount`         DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `payment_method` VARCHAR(100)   DEFAULT NULL,
  `notes`          TEXT           DEFAULT NULL,
  `created_by`     INT            DEFAULT NULL,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receipt_no` (`receipt_no`),
  KEY `idx_fi_income_date`  (`income_date`),
  KEY `idx_fi_book_id`      (`book_id`),
  KEY `idx_fi_category_id`  (`category_id`),
  KEY `idx_fi_created_by`   (`created_by`),
  CONSTRAINT `fk_fi_book`     FOREIGN KEY (`book_id`)     REFERENCES `receipt_books`     (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fi_category` FOREIGN KEY (`category_id`) REFERENCES `income_categories`  (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fi_admin`    FOREIGN KEY (`created_by`)  REFERENCES `admins`            (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- finance_expenses
-- ============================================================
CREATE TABLE IF NOT EXISTS `finance_expenses` (
  `id`               INT            NOT NULL AUTO_INCREMENT,
  `voucher_no`       VARCHAR(50)    NOT NULL,
  `expense_date`     DATE           NOT NULL,
  `beneficiary_name` VARCHAR(200)   DEFAULT NULL,
  `category_id`      INT            DEFAULT NULL,
  `amount`           DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `payment_method`   VARCHAR(100)   DEFAULT NULL,
  `notes`            TEXT           DEFAULT NULL,
  `created_by`       INT            DEFAULT NULL,
  `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_voucher_no` (`voucher_no`),
  KEY `idx_fe_expense_date` (`expense_date`),
  KEY `idx_fe_category_id`  (`category_id`),
  KEY `idx_fe_created_by`   (`created_by`),
  CONSTRAINT `fk_fe_category` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_fe_admin`    FOREIGN KEY (`created_by`)  REFERENCES `admins`             (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Seed default categories (safe – uses ON DUPLICATE KEY UPDATE)
-- ============================================================
INSERT INTO `income_categories` (`name`, `is_active`) VALUES
  ('زكاة المال', 1),
  ('زكاة الفطر', 1),
  ('صدقات', 1),
  ('تبرعات', 1),
  ('منح وهبات', 1) AS new_vals(name, is_active)
ON DUPLICATE KEY UPDATE `name` = new_vals.`name`;

INSERT INTO `expense_categories` (`name`, `is_active`) VALUES
  ('مساعدات نقدية', 1),
  ('مستلزمات غذائية', 1),
  ('إيجارات', 1),
  ('رواتب', 1),
  ('مصاريف إدارية', 1) AS new_vals(name, is_active)
ON DUPLICATE KEY UPDATE `name` = new_vals.`name`;
