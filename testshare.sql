-- ====================================================================
-- TestShare Database Schema & Seed Data for Multi-Vendor Food Application
-- Consolidated Single File Installation for XAMPP / MariaDB / MySQL
-- Database Name: testshare
-- Compatibility: MySQL 5.7+ / 8.0+ & MariaDB
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `testshare` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `testshare`;

SET FOREIGN_KEY_CHECKS = 0;

-- ==========================================
-- 1. AUTHENTICATION, USERS & PRIVILEGES
-- ==========================================

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `fullname` VARCHAR(100) NOT NULL,
    `email` VARCHAR(150) UNIQUE NOT NULL,
    `phone` VARCHAR(20) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('customer', 'seller_manager', 'super_admin') DEFAULT 'customer',
    `status` ENUM('Active', 'Suspended', 'Banned') DEFAULT 'Active',
    `profile_image` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_role_status` (`role`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `users` (`id`, `fullname`, `email`, `phone`, `password_hash`, `role`, `status`, `created_at`, `updated_at`, `profile_image`) VALUES
(1, 'Rohan Malhotra', 'customer@testshare.com', '9876543210', '$2y$10$3Q5l8Hymchpuni41u57nye33znCE6rP14/uqspLq663WJ7MwTGVnK', 'customer', 'Active', '2026-07-24 08:00:06', '2026-07-24 08:00:06', NULL),
(2, 'Chef Harpal Singh', 'seller@testshare.com', '8765432109', '$2y$10$by/GqqLRa.PUBc/mJ3A3b.KGhFEplo8dbJjACpFWBRLghDTgWXX3G', 'seller_manager', 'Active', '2026-07-24 08:00:06', '2026-07-24 08:00:06', NULL),
(3, 'System Admin', 'admin@testshare.com', '7654321098', '$2y$10$KQOTE0vD/wX0sgJRGEEWx./q4HeJLdohwJzEkBaKK5scrDpDB1Xs2', 'super_admin', 'Active', '2026-07-24 08:00:06', '2026-07-24 08:00:06', NULL),
(4, 'Aarav Sharma', 'aarav.sharma@testshare.in', '9812345670', '$2y$10$z4SP35rZAYnKvKuPQMN0L.YjJ/UIDP4lHyxAYXloWf2IPVBdDeupS', 'customer', 'Active', '2026-07-24 08:00:06', '2026-07-24 08:00:06', NULL),
(5, 'Ananya Patel', 'ananya.patel@gmail.com', '9812345671', '$2y$10$qdzIFACx9xpEEjpiQRqfBunRUM0Z6YAJGGBVbg7.BSrZ5HQar4J3i', 'customer', 'Active', '2026-07-24 08:00:06', '2026-07-24 08:00:06', NULL),
(6, 'Kabir Singh', 'kabir.singh@yahoo.com', '9812345672', '$2y$10$6ER6RtVrORKwuNoGWcCeQOQFoPkfgVdngZYreh.IOastXbGElQlR2', 'customer', 'Active', '2026-07-24 08:00:06', '2026-07-24 08:00:06', NULL),
(7, 'Diya Iyer', 'diya.iyer@gmail.com', '9812345673', '$2y$10$VhpV2TCjfvJqCSDCVSZ7PeZkm44iG4wAq1HKbcRtCToCtAFC3pcoi', 'customer', 'Active', '2026-07-24 08:00:06', '2026-07-24 08:00:06', NULL),
(8, 'Rahul Verma', 'rahul.verma@outlook.com', '9812345674', '$2y$10$8OOOxP1KmQFpuL181I0DRO7eog2qtntthBbmSCYEA8x3//qUDrXhK', 'customer', 'Suspended', '2026-07-24 08:00:06', '2026-07-24 08:00:06', NULL),
(9, 'Chef Sanjeev Kapoor', 'sanjeev@bakery88.in', '9924883921', '$2y$10$xGw/I.LiXNXZe6YJDXbSKO4AIamOgqxZ0KJV7UWfsngv8dLmBd0Py', 'seller_manager', 'Active', '2026-07-24 08:00:07', '2026-07-24 08:00:07', NULL),
(10, 'Chef Kunal Kapur', 'kunal@curryleaves.in', '9924883922', '$2y$10$gqfdftCteicO1cp.wrN4S.DRmytUBGha68goAyIwib5x/9MB82L4i', 'seller_manager', 'Active', '2026-07-24 08:00:07', '2026-07-24 08:00:07', NULL),
(11, 'Zeeshan Ali', 'owner@pistahouse.com', '9924883923', '$2y$10$UizcTiW37aH10GMvTMsByeiBb0i48k0KzRwkG.9p8S5/XtXfHy7Pq', 'seller_manager', 'Active', '2026-07-24 08:00:07', '2026-07-24 08:00:07', NULL);


-- ==========================================
-- 2. STORES, ONBOARDING & SETTLEMENTS
-- ==========================================

DROP TABLE IF EXISTS `stores`;
CREATE TABLE `stores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id` INT NOT NULL,
    `store_name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `contact_email` VARCHAR(150) NOT NULL,
    `contact_phone` VARCHAR(20) NOT NULL,
    `address` TEXT NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `pincode` VARCHAR(10) NOT NULL,
    `country` VARCHAR(100) DEFAULT 'India',
    `lat` DECIMAL(10,8) DEFAULT 22.29760000,
    `lng` DECIMAL(11,8) DEFAULT 70.78740000,
    `delivery_radius` DECIMAL(5,2) DEFAULT 50.00,
    `opening_time` TIME DEFAULT '00:00:00',
    `closing_time` TIME DEFAULT '23:59:59',
    `gstin` VARCHAR(20) DEFAULT NULL,
    `pan` VARCHAR(15) DEFAULT NULL,
    `fssai` VARCHAR(20) DEFAULT NULL,
    `bank_holder_name` VARCHAR(100) NOT NULL,
    `bank_name` VARCHAR(100) NOT NULL,
    `bank_account_number` VARCHAR(50) NOT NULL,
    `bank_ifsc` VARCHAR(20) NOT NULL,
    `commission_rate` DECIMAL(5,2) DEFAULT 5.00,
    `subscription_tier` VARCHAR(50) DEFAULT 'Starter',
    `onboarding_status` ENUM('Under Review', 'Approved', 'Rejected') DEFAULT 'Under Review',
    `store_status` ENUM('Active', 'Deactivated') DEFAULT 'Active',
    `accept_system_status` ENUM('Auto Accept', 'Manual Accept') DEFAULT 'Auto Accept',
    `store_logo` TEXT DEFAULT NULL,
    `store_banner` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    INDEX `idx_stores_onboarding` (`onboarding_status`),
    INDEX `idx_stores_status` (`store_status`, `onboarding_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stores` (`id`, `owner_id`, `store_name`, `category`, `contact_email`, `contact_phone`, `address`, `city`, `pincode`, `gstin`, `pan`, `fssai`, `bank_holder_name`, `bank_name`, `bank_account_number`, `bank_ifsc`, `commission_rate`, `subscription_tier`, `onboarding_status`, `store_status`, `accept_system_status`, `created_at`, `updated_at`, `lat`, `lng`, `delivery_radius`, `country`, `opening_time`, `closing_time`, `store_logo`, `store_banner`) VALUES
(1, 2, 'Royal Punjab Grill', 'North Indian & Tandoori', 'contact@royalpunjab.in', '8765432109', 'Shop 12, Connaught Place, Block E', 'New Delhi', '123456', '07AAAAA1234A1Z5', 'ABCDE1234F', '10023011000456', 'Royal Punjab Grill LLP', 'State Bank of India', '3009876543210', 'SBIN0001234', 5.00, 'Starter', 'Approved', 'Active', 'Auto Accept', '2026-07-24 08:00:07', '2026-07-27 05:36:22', 22.29760000, 70.78740000, 50.00, 'India', '00:00:00', '23:59:59', NULL, NULL),
(2, 9, 'Central Bakery', 'Bakery & Cafe', 'contact@centralbakery.in', '9924883921', '88 Hill Road, Bandra West', 'Rajkot', '360020', '27BBBBB1234B1Z5', 'ABCDE5678G', '10024022000819', 'Central Bakery Corp', 'HDFC Bank', '50100987654321', 'HDFC0000060', 1.00, 'Premium', 'Approved', 'Active', 'Auto Accept', '2026-07-24 08:00:07', '2026-07-27 11:22:19', 22.29760000, 70.78740000, 50.00, 'India', '00:00:00', '23:59:59', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALgAAACUCAMAAAAXgxO4AAAAaVBMVEX///8AAAD4+Pj19fXZ2dnT09P8/Pzc3Nzv7+94eHigoKCampqmpqY4ODiUlJRra2skJCTFxcVeXl4yMjK3t7exsbFJSUlDQ0N+fn5kZGRxcXEVFRVZWVni4uIMDAwrKyuJiYkdHR1RUVEFxxLtAAAEHUlEQVR4nO2c2ZaiMBBADQEMmxBWQWml//8jB9qxXUBJIkmFmdyneZiHeziVSlWl7M3GYDAYDAaDwWAwGAwGg+HfxLVwj+VCe3BBbFoEXp2mtRcU1CbQPmxgGjQZuiNrAoqhrWbBYXpCI05pqLe6G0Zj6wtRqG+4u/Sl9o861VSdBO+0BwItj+k2nvNGKN5CW46x34bJb7jY0J7P2CzaA5qZ21+s4l8OtOs9bcnqjVDZQtveIA27N0KNPrkl5PFGKIT2veLweSOkS5h/84p/QxtfyHm9EcqhnQeslF88taCte/KOX7zT4JNbHr83Qnv4T+4w1SjPRPCJReBoDoDHinUWEz9DxwoRipQ+VqDv/VbMGyHoUouKilNgcc766gZ0pbUTFd8BiwtdPwMesDhDaz9NbMTFWG2orPZwrjYdrvYC2oqKQ08RRYusDLrIWm1Zu9pGYuNk85ZjMvjWDQtdQR54pGw2xYHf+1BAW/fgI7/4UYvHw7WO4DYux1T/QqnJuyH3mFmbd6DZB85HAmjfXwhXOxFD3/Z3bCt27wq6vHrAWelzIccB1cx7s7En1lTGnLSKkws2w5NKqk0ivIfMNs47jfLJPWtdtOnBfvdKu/O1KKxegv0yGVsnpebaAyTfxQ8hE9VnqmlwP0OcPDw3w8Jkcw5zR3NrGjxEg4VJD35o0XAAPQZ6xm1/phTvAxn7w/85t/rkFkKv1WHi2y/cse1fD22sScRb9H4/KPEKZ+SOncK7zzUN1aDJ33rP6a+q98XtQBKn2NfPVW/igdcs4eSNczhlUVWVZVVF2WlyfNHBjplbgdHElSPgEy3laHzGVGC5sRAaG97IgOZZvsDs7ZGDD+HNOZOYRv2kwvWX8O4vW9X3aD5RvIqQKI5zh6k1ZjJX2/Z3S3n3V5FK73o5b4Rqdd7Cz8nTKLv9eSaFLKiaJrrCiwev2KnJicy/42BHyYDLXfRkXmhUfHIJH1zNJxdeZnqHgkUnwjzC5+FLfv+8eEq5IH3TCXP/koONb9mzRbFliXmkr1MsVIaPkdwMWXtZ4pJ/NbHlfrdnpZRbsNCPG+RXHOQOKwQ3sFiQugqyVIs8hdS2GUs7m/3plJnJiYTK8EotU9ySdOEP7KTmQyIvj0sus8R+kzePJ/8dVHCZ9j176do9Qbe0dqdm9ukWi83fLpwKVZNPsd9wvkLlgrC1YDshvYV4JFxqzKz89c1ZpNtvAHbLiP/xGT35MI/j9oeXUQO3WuZ80BCVsBuIedqJWCcp+Ao5zvfcCSbZ5zqsaGFnxzVsyXbjxRAgXEKZ/3JGSok+K0IDbh5HMzGTRHGul/RfMA28tOqmnLsq9fT+G3zD5l5wjo9lFSU9UVUe43MQUkezAJnGIm3bbu2ebf8PosEClsFgMBgM/zt/AISTOTGwsGWcAAAAAElFTkSuQmCC', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALgAAACUCAMAAAAXgxO4AAAAaVBMVEX///8AAAD4+Pj19fXZ2dnT09P8/Pzc3Nzv7+94eHigoKCampqmpqY4ODiUlJRra2skJCTFxcVeXl4yMjK3t7exsbFJSUlDQ0N+fn5kZGRxcXEVFRVZWVni4uIMDAwrKyuJiYkdHR1RUVEFxxLtAAAEHUlEQVR4nO2c2ZaiMBBADQEMmxBWQWml//8jB9qxXUBJIkmFmdyneZiHeziVSlWl7M3GYDAYDAaDwWAwGAwGg+HfxLVwj+VCe3BBbFoEXp2mtRcU1CbQPmxgGjQZuiNrAoqhrWbBYXpCI05pqLe6G0Zj6wtRqG+4u/Sl9o861VSdBO+0BwItj+k2nvNGKN5CW46x34bJb7jY0J7P2CzaA5qZ21+s4l8OtOs9bcnqjVDZQtveIA27N0KNPrkl5PFGKIT2veLweSOkS5h/84p/QxtfyHm9EcqhnQeslF88taCte/KOX7zT4JNbHr83Qnv4T+4w1SjPRPCJReBoDoDHinUWEz9DxwoRipQ+VqDv/VbMGyHoUouKilNgcc766gZ0pbUTFd8BiwtdPwMesDhDaz9NbMTFWG2orPZwrjYdrvYC2oqKQ08RRYusDLrIWm1Zu9pGYuNk85ZjMvjWDQtdQR54pGw2xYHf+1BAW/fgI7/4UYvHw7WO4DYux1T/QqnJuyH3mFmbd6DZB85HAmjfXwhXOxFD3/Z3bCt27wq6vHrAWelzIccB1cx7s7En1lTGnLSKkws2w5NKqk0ivIfMNs47jfLJPWtdtOnBfvdKu/O1KKxegv0yGVsnpebaAyTfxQ8hE9VnqmlwP0OcPDw3w8Jkcw5zR3NrGjxEg4VJD35o0XAAPQZ6xm1/phTvAxn7w/85t/rkFkKv1WHi2y/cse1fD22sScRb9H4/KPEKZ+SOncK7zzUN1aDJ33rP6a+q98XtQBKn2NfPVW/igdcs4eSNczhlUVWVZVVF2WlyfNHBjplbgdHElSPgEy3laHzGVGC5sRAaG97IgOZZvsDs7ZGDD+HNOZOYRv2kwvWX8O4vW9X3aD5RvIqQKI5zh6k1ZjJX2/Z3S3n3V5FK73o5b4Rqdd7Cz8nTKLv9eSaFLKiaJrrCiwev2KnJicy/42BHyYDLXfRkXmhUfHIJH1zNJxdeZnqHgkUnwjzC5+FLfv+8eEq5IH3TCXP/koONb9mzRbFliXmkr1MsVIaPkdwMWXtZ4pJ/NbHlfrdnpZRbsNCPG+RXHOQOKwQ3sFiQugqyVIs8hdS2GUs7m/3plJnJiYTK8EotU9ySdOEP7KTmQyIvj0sus8R+kzePJ/8dVHCZ9j176do9Qbe0dqdm9ukWi83fLpwKVZNPsd9wvkLlgrC1YDshvYV4JFxqzKz89c1ZpNtvAHbLiP/xGT35MI/j9oeXUQO3WuZ80BCVsBuIedqJWCcp+Ao5zvfcCSbZ5zqsaGFnxzVsyXbjxRAgXEKZ/3JGSok+K0IDbh5HMzGTRHGul/RfMA28tOqmnLsq9fT+G3zD5l5wjo9lFSU9UVUe43MQUkezAJnGIm3bbu2ebf8PosEClsFgMBgM/zt/AISTOTGwsGWcAAAAAElFTkSuQmCC'),
(3, 10, 'Curry Leaves', 'South Indian & Tiffin', 'contact@curryleaves.in', '9924883922', '99 Nungambakkam High Road', 'Chennai', '123456', '33CCCCC1234C1Z5', 'ABCDE9012H', '10024099000902', 'Kunal Kapur Hospitality', 'ICICI Bank', '000401987654', 'ICIC0000004', 0.00, 'Ultra Premium', 'Approved', 'Active', 'Auto Accept', '2026-07-24 08:00:07', '2026-07-27 11:17:42', 22.29760000, 70.78740000, 50.00, 'India', '00:00:00', '23:59:59', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALgAAACUCAMAAAAXgxO4AAAAaVBMVEX///8AAAD4+Pj19fXZ2dnT09P8/Pzc3Nzv7+94eHigoKCampqmpqY4ODiUlJRra2skJCTFxcVeXl4yMjK3t7exsbFJSUlDQ0N+fn5kZGRxcXEVFRVZWVni4uIMDAwrKyuJiYkdHR1RUVEFxxLtAAAEHUlEQVR4nO2c2ZaiMBBADQEMmxBWQWml//8jB9qxXUBJIkmFmdyneZiHeziVSlWl7M3GYDAYDAaDwWAwGAwGg+HfxLVwj+VCe3BBbFoEXp2mtRcU1CbQPmxgGjQZuiNrAoqhrWbBYXpCI05pqLe6G0Zj6wtRqG+4u/Sl9o861VSdBO+0BwItj+k2nvNGKN5CW46x34bJb7jY0J7P2CzaA5qZ21+s4l8OtOs9bcnqjVDZQtveIA27N0KNPrkl5PFGKIT2veLweSOkS5h/84p/QxtfyHm9EcqhnQeslF88taCte/KOX7zT4JNbHr83Qnv4T+4w1SjPRPCJReBoDoDHinUWEz9DxwoRipQ+VqDv/VbMGyHoUouKilNgcc766gZ0pbUTFd8BiwtdPwMesDhDaz9NbMTFWG2orPZwrjYdrvYC2oqKQ08RRYusDLrIWm1Zu9pGYuNk85ZjMvjWDQtdQR54pGw2xYHf+1BAW/fgI7/4UYvHw7WO4DYux1T/QqnJuyH3mFmbd6DZB85HAmjfXwhXOxFD3/Z3bCt27wq6vHrAWelzIccB1cx7s7En1lTGnLSKkws2w5NKqk0ivIfMNs47jfLJPWtdtOnBfvdKu/O1KKxegv0yGVsnpebaAyTfxQ8hE9VnqmlwP0OcPDw3w8Jkcw5zR3NrGjxEg4VJD35o0XAAPQZ6xm1/phTvAxn7w/85t/rkFkKv1WHi2y/cse1fD22sScRb9H4/KPEKZ+SOncK7zzUN1aDJ33rP6a+q98XtQBKn2NfPVW/igdcs4eSNczhlUVWVZVVF2WlyfNHBjplbgdHElSPgEy3laHzGVGC5sRAaG97IgOZZvsDs7ZGDD+HNOZOYRv2kwvWX8O4vW9X3aD5RvIqQKI5zh6k1ZjJX2/Z3S3n3V5FK73o5b4Rqdd7Cz8nTKLv9eSaFLKiaJrrCiwev2KnJicy/42BHyYDLXfRkXmhUfHIJH1zNJxdeZnqHgkUnwjzC5+FLfv+8eEq5IH3TCXP/koONb9mzRbFliXmkr1MsVIaPkdwMWXtZ4pJ/NbHlfrdnpZRbsNCPG+RXHOQOKwQ3sFiQugqyVIs8hdS2GUs7m/3plJnJiYTK8EotU9ySdOEP7KTmQyIvj0sus8R+kzePJ/8dVHCZ9j176do9Qbe0dqdm9ukWi83fLpwKVZNPsd9wvkLlgrC1YDshvYV4JFxqzKz89c1ZpNtvAHbLiP/xGT35MI/j9oeXUQO3WuZ80BCVsBuIedqJWCcp+Ao5zvfcCSbZ5zqsaGFnxzVsyXbjxRAgXEKZ/3JGSok+K0IDbh5HMzGTRHGul/RfMA28tOqmnLsq9fT+G3zD5l5wjo9lFSU9UVUe43MQUkezAJnGIm3bbu2ebf8PosEClsFgMBgM/zt/AISTOTGwsGWcAAAAAElFTkSuQmCC', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALgAAACUCAMAAAAXgxO4AAAAaVBMVEX///8AAAD4+Pj19fXZ2dnT09P8/Pzc3Nzv7+94eHigoKCampqmpqY4ODiUlJRra2skJCTFxcVeXl4yMjK3t7exsbFJSUlDQ0N+fn5kZGRxcXEVFRVZWVni4uIMDAwrKyuJiYkdHR1RUVEFxxLtAAAEHUlEQVR4nO2c2ZaiMBBADQEMmxBWQWml//8jB9qxXUBJIkmFmdyneZiHeziVSlWl7M3GYDAYDAaDwWAwGAwGg+HfxLVwj+VCe3BBbFoEXp2mtRcU1CbQPmxgGjQZuiNrAoqhrWbBYXpCI05pqLe6G0Zj6wtRqG+4u/Sl9o861VSdBO+0BwItj+k2nvNGKN5CW46x34bJb7jY0J7P2CzaA5qZ21+s4l8OtOs9bcnqjVDZQtveIA27N0KNPrkl5PFGKIT2veLweSOkS5h/84p/QxtfyHm9EcqhnQeslF88taCte/KOX7zT4JNbHr83Qnv4T+4w1SjPRPCJReBoDoDHinUWEz9DxwoRipQ+VqDv/VbMGyHoUouKilNgcc766gZ0pbUTFd8BiwtdPwMesDhDaz9NbMTFWG2orPZwrjYdrvYC2oqKQ08RRYusDLrIWm1Zu9pGYuNk85ZjMvjWDQtdQR54pGw2xYHf+1BAW/fgI7/4UYvHw7WO4DYux1T/QqnJuyH3mFmbd6DZB85HAmjfXwhXOxFD3/Z3bCt27wq6vHrAWelzIccB1cx7s7En1lTGnLSKkws2w5NKqk0ivIfMNs47jfLJPWtdtOnBfvdKu/O1KKxegv0yGVsnpebaAyTfxQ8hE9VnqmlwP0OcPDw3w8Jkcw5zR3NrGjxEg4VJD35o0XAAPQZ6xm1/phTvAxn7w/85t/rkFkKv1WHi2y/cse1fD22sScRb9H4/KPEKZ+SOncK7zzUN1aDJ33rP6a+q98XtQBKn2NfPVW/igdcs4eSNczhlUVWVZVVF2WlyfNHBjplbgdHElSPgEy3laHzGVGC5sRAaG97IgOZZvsDs7ZGDD+HNOZOYRv2kwvWX8O4vW9X3aD5RvIqQKI5zh6k1ZjJX2/Z3S3n3V5FK73o5b4Rqdd7Cz8nTKLv9eSaFLKiaJrrCiwev2KnJicy/42BHyYDLXfRkXmhUfHIJH1zNJxdeZnqHgkUnwjzC5+FLfv+8eEq5IH3TCXP/koONb9mzRbFliXmkr1MsVIaPkdwMWXtZ4pJ/NbHlfrdnpZRbsNCPG+RXHOQOKwQ3sFiQugqyVIs8hdS2GUs7m/3plJnJiYTK8EotU9ySdOEP7KTmQyIvj0sus8R+kzePJ/8dVHCZ9j176do9Qbe0dqdm9ukWi83fLpwKVZNPsd9wvkLlgrC1YDshvYV4JFxqzKz89c1ZpNtvAHbLiP/xGT35MI/j9oeXUQO3WuZ80BCVsBuIedqJWCcp+Ao5zvfcCSbZ5zqsaGFnxzVsyXbjxRAgXEKZ/3JGSok+K0IDbh5HMzGTRHGul/RfMA28tOqmnLsq9fT+G3zD5l5wjo9lFSU9UVUe43MQUkezAJnGIm3bbu2ebf8PosEClsFgMBgM/zt/AISTOTGwsGWcAAAAAElFTkSuQmCC'),
(4, 11, 'Pista House Biryani', 'Hyderabadi Haleem & Biryani', 'owner@pistahouse.com', '9924883923', 'Charminar Road, Shalibanda', 'Hyderabad', '360020', '36DDDDD1234D1Z5', 'ABCDE3456I', '10024033000123', 'Pista House Corp', 'State Bank of India', '200123456789', 'SBIN0004567', 5.00, 'Starter', 'Approved', 'Active', 'Auto Accept', '2026-07-24 08:00:07', '2026-07-27 11:17:43', 22.29760000, 70.78740000, 50.00, 'India', '00:00:00', '23:59:59', NULL, NULL);


-- ==========================================
-- 3. MERCHANT BILLING SUBSCRIPTIONS
-- ==========================================

DROP TABLE IF EXISTS `billing_subscriptions`;
CREATE TABLE `billing_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `tier` ENUM('Starter', 'Premium', 'Ultra Premium') NOT NULL,
    `price_per_month` DECIMAL(10,2) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `status` ENUM('Active', 'Expired', 'Cancelled') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    INDEX `idx_subscriptions_dates` (`end_date`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `billing_subscriptions` (`id`, `store_id`, `tier`, `price_per_month`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 1, 'Starter', 0.00, '2026-01-01', '2026-12-31', 'Active', '2026-07-24 10:44:08'),
(2, 2, 'Premium', 49.00, '2026-07-24', '2026-08-24', 'Active', '2026-07-24 11:49:25'),
(3, 3, 'Ultra Premium', 149.00, '2026-07-27', '2026-08-27', 'Active', '2026-07-27 06:08:34'),
(4, 4, 'Starter', 0.00, '2026-01-01', '2026-12-31', 'Active', '2026-07-27 06:08:34');


-- Store Theme Customization Table
DROP TABLE IF EXISTS `store_theme_config`;
CREATE TABLE `store_theme_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT UNIQUE NOT NULL,
    `accent_color` VARCHAR(20) DEFAULT '#ff9f0d',
    `secondary_color` VARCHAR(20) DEFAULT '#2d2d2d',
    `banner_style` VARCHAR(50) DEFAULT 'default',
    `custom_css` TEXT DEFAULT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- First Order Discount Configuration Table
DROP TABLE IF EXISTS `first_order_config`;
CREATE TABLE `first_order_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `discount_percentage` DECIMAL(5,2) DEFAULT 15.00,
    `max_discount_amount` DECIMAL(10,2) DEFAULT 10.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `first_order_config` (`id`, `discount_percentage`, `max_discount_amount`, `is_active`) VALUES
(1, 15.00, 10.00, 1);


-- ==========================================
-- 4. DISH STUDIO & MENU MANAGEMENT
-- ==========================================

DROP TABLE IF EXISTS `menu_items`;
CREATE TABLE `menu_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `image_url` VARCHAR(255) DEFAULT NULL,
    `prep_time` INT DEFAULT 20,
    `ingredients` TEXT DEFAULT NULL,
    `is_available` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    INDEX `idx_menu_store` (`store_id`, `category`, `is_available`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `menu_items` (`id`, `store_id`, `name`, `description`, `price`, `category`, `image_url`, `prep_time`, `ingredients`, `is_available`, `created_at`) VALUES
(1, 1, 'Butter Chicken', 'Tender tandoori chicken cooked in creamy, buttery sweet tomato gravy.', 12.50, 'North Indian', 'images/butter_chicken.png', 25, 'Chicken, Butter, Cream, Tomato Puree, Onion, Garlic, Ginger, Garam Masala, Kashmiri Chilli', 1, '2026-07-24 08:05:00'),
(2, 1, 'Paneer Tikka Masala', 'Grilled paneer cubes cooked in spicy spiced tomato onion masala gravel.', 10.00, 'North Indian', 'images/paneer_tikka.png', 20, 'Paneer, Tomatoes, Onion, Bell Peppers, Cream, Cumin, Coriander, Fenugreek Leaves', 1, '2026-07-24 08:05:00'),
(3, 2, 'Mango Lassi Shake', 'Creamy blend of yogurt and sweet Alphonso mangoes, served chilled.', 4.00, 'Drinks', 'images/mango_lassi.png', 5, 'Alphonso Mango Pulp, Full-Fat Yogurt, Sugar, Cardamom, Saffron, Ice', 1, '2026-07-24 08:05:00'),
(4, 2, 'Veg Hakka Noodles', 'Indo-Chinese street style stir fried noodles with crisp fresh vegetables.', 6.00, 'Appetizers', 'images/hakka_noodles.png', 15, 'Noodles, Cabbage, Carrot, Capsicum, Spring Onion, Soy Sauce, Vinegar, Garlic', 1, '2026-07-24 08:05:00'),
(5, 3, 'Masala Dosa', 'Thin crispy golden rice crepe filled with spiced potato masala.', 5.00, 'South Indian', 'images/masala_dosa.png', 20, 'Rice Batter, Urad Dal, Potato, Onion, Mustard Seeds, Curry Leaves, Green Chilli, Turmeric', 1, '2026-07-24 08:05:00'),
(6, 3, 'Filter Coffee', 'Traditional South Indian filter coffee whipped with hot frothy milk.', 2.50, 'Drinks', 'images/filter_coffee.png', 5, 'Coffee Powder, Chicory, Full-Fat Milk, Sugar', 1, '2026-07-24 08:05:00'),
(7, 4, 'Hyderabadi Chicken Biryani', 'Layered basmati rice and marinated chicken cooked dum-style with spices.', 9.50, 'Biryani', 'images/chicken_biryani.png', 40, 'Basmati Rice, Chicken, Yogurt, Fried Onions, Saffron, Whole Spices, Mint, Ghee', 1, '2026-07-24 08:05:00'),
(8, 4, 'Double Ka Meetha', 'Golden bread pieces soaked in sweet cardamom saffron syrup and rich rabri.', 4.00, 'Dessert', 'images/double_ka_meetha.png', 30, 'Bread, Milk, Khoya, Sugar, Cardamom, Saffron, Cashews, Almonds, Raisins', 1, '2026-07-24 08:05:00');


-- ==========================================
-- 5. ORDERS & CHECKOUT ENGINE
-- ==========================================

DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `store_id` INT NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `platform_commission` DECIMAL(10,2) NOT NULL,
    `store_net_amount` DECIMAL(10,2) NOT NULL,
    `payment_status` ENUM('Pending', 'Paid', 'Refunded', 'Failed') DEFAULT 'Pending',
    `refund_status` VARCHAR(50) DEFAULT NULL,
    `refund_amount` DECIMAL(10,2) DEFAULT 0.00,
    `refund_method` VARCHAR(100) DEFAULT NULL,
    `cancelled_by` VARCHAR(50) DEFAULT NULL,
    `cancellation_reason` TEXT DEFAULT NULL,
    `order_status` ENUM('Pending', 'Accepted', 'Preparing', 'Out For Delivery', 'Delivered', 'Completed', 'Cancelled') DEFAULT 'Pending',
    `notes` TEXT DEFAULT NULL,
    `razorpay_payment_id` VARCHAR(100) DEFAULT NULL,
    `razorpay_order_id` VARCHAR(100) DEFAULT NULL,
    `razorpay_route_transfer_id` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_orders_status` (`order_status`, `payment_status`),
    INDEX `idx_orders_store` (`store_id`),
    INDEX `idx_orders_cust` (`customer_id`, `order_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `orders` (`id`, `customer_id`, `store_id`, `total_amount`, `platform_commission`, `store_net_amount`, `payment_status`, `order_status`, `created_at`) VALUES
(1, 4, 1, 100.00, 5.00, 95.00, 'Paid', 'Completed', '2026-07-24 10:00:00'),
(2, 4, 1, 40.00, 2.00, 38.00, 'Paid', 'Completed', '2026-07-25 11:30:00');

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `menu_item_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `quantity`, `price`) VALUES
(1, 1, 1, 8, 12.50),
(2, 2, 2, 4, 10.00);


-- ==========================================
-- 6. DYNAMIC PROMOTIONS & GLOBAL OFFERS
-- ==========================================

DROP TABLE IF EXISTS `promotional_offers`;
CREATE TABLE `promotional_offers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT DEFAULT NULL,
    `coupon_code` VARCHAR(50) UNIQUE NOT NULL,
    `offer_title` VARCHAR(150) NOT NULL,
    `offer_description` TEXT DEFAULT NULL,
    `discount_percentage` DECIMAL(5,2) NOT NULL,
    `min_order_value` DECIMAL(10,2) NOT NULL,
    `admin_subsidy_percentage` DECIMAL(5,2) DEFAULT 0.00,
    `merchant_absorb_percentage` DECIMAL(5,2) DEFAULT 100.00,
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `status` ENUM('Active', 'Expired', 'Draft') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    INDEX `idx_offers_active` (`coupon_code`, `status`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `promotional_offers` (`id`, `store_id`, `coupon_code`, `offer_title`, `offer_description`, `discount_percentage`, `min_order_value`, `admin_subsidy_percentage`, `merchant_absorb_percentage`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, NULL, 'SUPER50', 'Super 50% Off Promo', '50% off all orders above $20', 50.00, 20.00, 80.00, 20.00, '2026-07-01 00:00:00', '2026-12-31 23:59:59', 'Active', '2026-07-24 08:00:00');


-- ==========================================
-- 7. REIMBURSEMENT PAYOUTS LEDGER
-- ==========================================

DROP TABLE IF EXISTS `payouts_ledger`;
CREATE TABLE `payouts_ledger` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `order_id` INT NOT NULL,
    `offer_id` INT DEFAULT NULL,
    `discharged_reimbursement` DECIMAL(10,2) NOT NULL,
    `settlement_status` ENUM('Escrow Hold', 'Transferred', 'Settled') DEFAULT 'Escrow Hold',
    `transfer_reference` VARCHAR(100) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`offer_id`) REFERENCES `promotional_offers` (`id`) ON DELETE SET NULL,
    INDEX `idx_payouts_status` (`store_id`, `settlement_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 8. RATINGS, REVIEWS & MODERATION
-- ==========================================

DROP TABLE IF EXISTS `reviews`;
CREATE TABLE `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `store_id` INT NOT NULL,
    `rating_stars` TINYINT NOT NULL CHECK (`rating_stars` BETWEEN 1 AND 5),
    `comment_text` TEXT DEFAULT NULL,
    `helpful_upvotes` INT DEFAULT 0,
    `is_popular` TINYINT(1) DEFAULT 0,
    `status` ENUM('Public storefront', 'Deactivated for Store (User Active)') DEFAULT 'Public storefront',
    `chef_month_nominated` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    INDEX `idx_reviews_stars` (`rating_stars`, `status`),
    INDEX `idx_reviews_store` (`store_id`),
    INDEX `idx_reviews_store_cust` (`store_id`, `customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `reviews` (`id`, `customer_id`, `store_id`, `rating_stars`, `comment_text`, `helpful_upvotes`, `is_popular`, `status`, `created_at`) VALUES
(1, 4, 1, 5, 'The Butter Chicken and Butter Naan were absolutely delicious! Perfectly cooked and spiced.', 14, 1, 'Public storefront', '2026-07-24 10:30:00');


-- ==========================================
-- 9. NOTIFICATIONS & CUSTOM CHANNELS
-- ==========================================

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_user` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_notifications`;
CREATE TABLE `user_notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('Order Update', 'Custom Order', 'System') DEFAULT 'System',
    `is_read` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 10. CUSTOM CULINARY ORDERS & NOTE THREADS
-- ==========================================

DROP TABLE IF EXISTS `custom_orders`;
CREATE TABLE `custom_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `store_id` INT DEFAULT NULL,
    `dish_name` VARCHAR(150) NOT NULL,
    `ingredients` TEXT NOT NULL,
    `offered_price` DECIMAL(10,2) NOT NULL,
    `demanded_price` DECIMAL(10,2) DEFAULT NULL,
    `attachments` JSON DEFAULT NULL,
    `notes` JSON DEFAULT NULL,
    `delivery_date` DATE NOT NULL,
    `delivery_time` TIME NOT NULL,
    `status` ENUM('Pending', 'Quote Sent', 'Accepted', 'Rejected') DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 11. SYSTEM CONFIGURATION & GLOBAL SETTINGS
-- ==========================================

DROP TABLE IF EXISTS `system_configurations`;
CREATE TABLE `system_configurations` (
    `config_key` VARCHAR(100) PRIMARY KEY,
    `config_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `system_configurations` (`config_key`, `config_value`) VALUES
('global_payout_delay_days', '1'),
('razorpay_settlement_mode', 'automated_split'),
('platform_default_commission_rate', '5.00')
ON DUPLICATE KEY UPDATE `config_value` = VALUES(`config_value`);


-- ==========================================
-- 12. RAW MATERIAL SALES
-- ==========================================

DROP TABLE IF EXISTS `raw_material_sales`;
CREATE TABLE `raw_material_sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `quantity` VARCHAR(100) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT 'Available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    INDEX `idx_raw_materials_store` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `store_material_orders`;
CREATE TABLE `store_material_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `buyer_store_id` INT NOT NULL,
    `seller_store_id` INT NOT NULL,
    `material_name` VARCHAR(150) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `quantity` VARCHAR(100) NOT NULL,
    `order_status` ENUM('Pending', 'Accepted', 'Out For Delivery', 'Delivered', 'Completed', 'Cancelled') DEFAULT 'Pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`buyer_store_id`) REFERENCES `stores` (`id`),
    FOREIGN KEY (`seller_store_id`) REFERENCES `stores` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 13. USER PROFILE FEATURES
-- ==========================================

DROP TABLE IF EXISTS `user_addresses`;
CREATE TABLE `user_addresses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `label` VARCHAR(50) NOT NULL,
    `address_line1` VARCHAR(255) NOT NULL,
    `address_line2` VARCHAR(255) DEFAULT NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) DEFAULT NULL,
    `zip_code` VARCHAR(20) DEFAULT NULL,
    `lat` DECIMAL(10,8) DEFAULT 22.29760000,
    `lng` DECIMAL(11,8) DEFAULT 70.78740000,
    `is_default` BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `user_addresses` (`id`, `user_id`, `label`, `address_line1`, `address_line2`, `city`, `state`, `zip_code`, `lat`, `lng`, `is_default`) VALUES
(1, 4, 'Home', '123 Main Street', 'Apt 4B', 'Rajkot', 'Gujarat', '360020', 22.29760000, 70.78740000, 1);


-- ==========================================
-- 14. ANALYTICS ROLLUP & RETENTION TABLES
-- ==========================================

DROP TABLE IF EXISTS `daily_revenue_rollups`;
CREATE TABLE `daily_revenue_rollups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `rollup_date` DATE NOT NULL,
    `total_revenue` DECIMAL(10,2) DEFAULT 0.00,
    `total_commission` DECIMAL(10,2) DEFAULT 0.00,
    `net_earnings` DECIMAL(10,2) DEFAULT 0.00,
    `orders_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `store_date_unique` (`store_id`, `rollup_date`),
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `daily_revenue_rollups` (`id`, `store_id`, `rollup_date`, `total_revenue`, `total_commission`, `net_earnings`, `orders_count`, `created_at`) VALUES
(1, 1, '2026-07-24', 165.00, 8.26, 156.76, 4, '2026-07-27 06:14:25'),
(2, 2, '2026-07-24', 12.00, 0.28, 11.72, 3, '2026-07-27 06:14:25'),
(3, 4, '2026-07-24', 22.00, 1.10, 20.90, 1, '2026-07-27 06:14:25');

DROP TABLE IF EXISTS `monthly_revenue_rollups`;
CREATE TABLE `monthly_revenue_rollups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `year_month` VARCHAR(7) NOT NULL,
    `total_revenue` DECIMAL(10,2) DEFAULT 0.00,
    `total_commission` DECIMAL(10,2) DEFAULT 0.00,
    `net_earnings` DECIMAL(10,2) DEFAULT 0.00,
    `orders_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `store_month_unique` (`store_id`, `year_month`),
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `yearly_revenue_rollups`;
CREATE TABLE `yearly_revenue_rollups` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `rollup_year` INT NOT NULL,
    `total_revenue` DECIMAL(10,2) DEFAULT 0.00,
    `total_commission` DECIMAL(10,2) DEFAULT 0.00,
    `net_earnings` DECIMAL(10,2) DEFAULT 0.00,
    `orders_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `store_year_unique` (`store_id`, `rollup_year`),
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 15. USER PAYMENT & RESET PREFERENCES
-- ==========================================

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(150) NOT NULL,
    `token` VARCHAR(100) NOT NULL,
    `otp` VARCHAR(10) DEFAULT NULL,
    `role` ENUM('user', 'seller', 'admin') DEFAULT 'user',
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_reset_token` (`token`),
    INDEX `idx_reset_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_payment_methods`;
CREATE TABLE `user_payment_methods` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `card_network` VARCHAR(50) NOT NULL,
    `last_four` VARCHAR(10) NOT NULL,
    `expiry_month` INT NOT NULL,
    `expiry_year` INT NOT NULL,
    `is_primary` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_payment_methods_user` (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `user_preferences`;
CREATE TABLE `user_preferences` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL UNIQUE,
    `dietary_restrictions` TEXT DEFAULT NULL,
    `favorite_cuisines` TEXT DEFAULT NULL,
    `contactless_delivery` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
