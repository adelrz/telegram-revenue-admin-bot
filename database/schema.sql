-- ایجاد دیتابیس
CREATE DATABASE IF NOT EXISTS `revenue_bot` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `revenue_bot`;

-- جدول کاربران (مالک و ادمین‌ها)
CREATE TABLE `users` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `telegram_id` BIGINT UNIQUE NOT NULL,
    `username` VARCHAR(255),
    `first_name` VARCHAR(255),
    `last_name` VARCHAR(255),
    `is_owner` BOOLEAN DEFAULT FALSE,
    `is_admin` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_telegram_id` (`telegram_id`),
    INDEX `idx_is_owner` (`is_owner`),
    INDEX `idx_is_admin` (`is_admin`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول پروژه‌ها
CREATE TABLE `projects` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `owner_id` INT NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `web_service_url` VARCHAR(500) NOT NULL,
    `status` ENUM('active', 'inactive', 'archived') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_owner_id` (`owner_id`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول ادمین‌های پروژه
CREATE TABLE `project_admins` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `admin_id` INT NOT NULL,
    `admin_name` VARCHAR(255) NOT NULL,
    `share_percentage` DECIMAL(5, 2) NOT NULL COMMENT 'درصد سهم از پروژه',
    `job_title` VARCHAR(255) NOT NULL COMMENT 'شغل در پروژه',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_project_admin` (`project_id`, `admin_id`),
    INDEX `idx_admin_id` (`admin_id`),
    INDEX `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول کارت‌های بانکی
CREATE TABLE `bank_cards` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `admin_id` INT NOT NULL,
    `card_number` VARCHAR(30) NOT NULL COMMENT 'شماره کارت یا شبا',
    `is_primary` BOOLEAN DEFAULT FALSE,
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_admin_id` (`admin_id`),
    INDEX `idx_is_primary` (`is_primary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول درامد روزانه
CREATE TABLE `daily_revenues` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `project_id` INT NOT NULL,
    `revenue_date` DATE NOT NULL,
    `total_revenue` DECIMAL(15, 2) NOT NULL COMMENT 'کل درامد روز',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_project_date` (`project_id`, `revenue_date`),
    INDEX `idx_revenue_date` (`revenue_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول محاسبات درامد ادمین
CREATE TABLE `admin_revenue_calculations` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `project_admin_id` INT NOT NULL,
    `revenue_date` DATE NOT NULL,
    `total_project_revenue` DECIMAL(15, 2) NOT NULL,
    `admin_share_percentage` DECIMAL(5, 2) NOT NULL,
    `calculated_amount` DECIMAL(15, 2) NOT NULL COMMENT 'مبلغ محاسبه شده برای ادمین',
    `status` ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`project_admin_id`) REFERENCES `project_admins`(`id`) ON DELETE CASCADE,
    INDEX `idx_revenue_date` (`revenue_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول تراکنش‌های پرداخت
CREATE TABLE `transactions` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `admin_id` INT NOT NULL,
    `project_id` INT NOT NULL,
    `amount` DECIMAL(15, 2) NOT NULL COMMENT 'مبلغ پرداختی',
    `card_id` INT NOT NULL,
    `payment_date` DATE NOT NULL,
    `payment_time` TIME NOT NULL,
    `revenue_calculation_id` INT NOT NULL,
    `status` ENUM('pending', 'confirmed', 'completed', 'failed') DEFAULT 'pending',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`card_id`) REFERENCES `bank_cards`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`revenue_calculation_id`) REFERENCES `admin_revenue_calculations`(`id`) ON DELETE CASCADE,
    INDEX `idx_admin_id` (`admin_id`),
    INDEX `idx_payment_date` (`payment_date`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول تاریخچه تغییرات
CREATE TABLE `audit_log` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `user_id` INT,
    `action` VARCHAR(255) NOT NULL,
    `table_name` VARCHAR(100),
    `record_id` INT,
    `old_value` JSON,
    `new_value` JSON,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جدول پیام‌های منتظر تأیید
CREATE TABLE `pending_approvals` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `owner_id` INT NOT NULL,
    `revenue_calculation_id` INT NOT NULL,
    `message_id` INT,
    `chat_id` BIGINT,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`revenue_calculation_id`) REFERENCES `admin_revenue_calculations`(`id`) ON DELETE CASCADE,
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
