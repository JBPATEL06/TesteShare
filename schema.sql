-- ====================================================================
-- TestShare Database Schema for Multi-Tenant Food Delivery Application
-- Handles User storefront, Seller/Merchant Panel, and Super Admin Panel
-- Compatibility: MySQL 5.7+ / 8.0+ & MariaDB (XAMPP Environment)
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `testshare_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `testshare_db`;

-- Set foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ==========================================
-- 1. AUTHENTICATION, USERS & PRIVILEGES
-- ==========================================

-- Users Table
-- Unified customer, seller manager, and super admin entity
CREATE TABLE IF NOT EXISTS `users` (
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


-- ==========================================
-- 2. STORES, ONBOARDING & SETTLEMENTS
-- ==========================================

-- Stores Table
-- Represents merchant storefront and onboarding registration attributes
CREATE TABLE IF NOT EXISTS `stores` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `owner_id` INT NOT NULL,
    `store_name` VARCHAR(150) NOT NULL,
    `category` VARCHAR(100) NOT NULL,
    `contact_email` VARCHAR(150) NOT NULL,
    `contact_phone` VARCHAR(20) NOT NULL,
    `address` TEXT NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `pincode` VARCHAR(10) NOT NULL,
    `country` VARCHAR(100) DEFAULT 'United States',
    `lat` DECIMAL(10,8) DEFAULT 40.7128,
    `lng` DECIMAL(11,8) DEFAULT -74.0060,
    `delivery_radius` DECIMAL(5,2) DEFAULT 5.00,
    `opening_time` TIME DEFAULT '09:00:00',
    `closing_time` TIME DEFAULT '22:00:00',
    
    -- Business Verification Details
    `gstin` VARCHAR(20) DEFAULT NULL,
    `pan` VARCHAR(15) DEFAULT NULL,
    `fssai` VARCHAR(20) DEFAULT NULL,
    
    -- Razorpay Route Settlements Linked Bank Details
    `bank_holder_name` VARCHAR(100) NOT NULL,
    `bank_name` VARCHAR(100) NOT NULL,
    `bank_account_number` VARCHAR(50) NOT NULL, -- Stored securely / masked during output
    `bank_ifsc` VARCHAR(20) NOT NULL,
    
    -- Platform Commissions & Acceptance Configuration
    `commission_rate` DECIMAL(5,2) DEFAULT 5.00, -- e.g. 5.00%
    `subscription_tier` VARCHAR(50) DEFAULT 'Starter', -- Starter, Premium, Ultra Premium
    `onboarding_status` ENUM('Under Review', 'Approved', 'Rejected') DEFAULT 'Under Review',
    `store_status` ENUM('Active', 'Deactivated') DEFAULT 'Active', -- Toggle public active store status
    
    -- Manage Accept Manage System (Order Acceptance Mode)
    `accept_system_status` ENUM('Auto Accept', 'Manual Accept') DEFAULT 'Auto Accept',

    -- Store Branding
    `store_logo` TEXT DEFAULT NULL,   -- base64 data URI or external URL
    `store_banner` TEXT DEFAULT NULL, -- base64 data URI or external URL
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    INDEX `idx_stores_onboarding` (`onboarding_status`),
    INDEX `idx_stores_status` (`store_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 3. MERCHANT BILLING SUBSCRIPTIONS
-- ==========================================

-- Billing Subscriptions Table
-- Audits merchant agreement plans (Basic, Pro, Enterprise)
CREATE TABLE IF NOT EXISTS `billing_subscriptions` (
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

-- Store Theme Customization Table
CREATE TABLE IF NOT EXISTS `store_theme_config` (
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
CREATE TABLE IF NOT EXISTS `first_order_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `discount_percentage` DECIMAL(5,2) DEFAULT 15.00,
    `max_discount_amount` DECIMAL(10,2) DEFAULT 10.00,
    `is_active` TINYINT(1) DEFAULT 1,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



-- ==========================================
-- 4. DISH STUDIO & MENU MANAGEMENT
-- ==========================================

-- Menu Items Table
CREATE TABLE IF NOT EXISTS `menu_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `category` VARCHAR(100) NOT NULL, -- e.g., Appetizers, Mains, Drinks, Desserts
    `image_url` VARCHAR(255) DEFAULT NULL,
    `prep_time` INT DEFAULT 20,
    `ingredients` TEXT DEFAULT NULL,
    `is_available` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    INDEX `idx_menu_store` (`store_id`, `category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 5. ORDERS & CHECKOUT ENGINE
-- ==========================================

-- Orders Table
CREATE TABLE IF NOT EXISTS `orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `store_id` INT NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `platform_commission` DECIMAL(10,2) NOT NULL, -- Split amount going to TestShare
    `store_net_amount` DECIMAL(10,2) NOT NULL, -- Net amount transferred to merchant via Route
    `payment_status` ENUM('Pending', 'Paid', 'Refunded', 'Failed') DEFAULT 'Pending',
    `refund_status` VARCHAR(50) DEFAULT NULL,
    `refund_amount` DECIMAL(10,2) DEFAULT 0.00,
    `refund_method` VARCHAR(100) DEFAULT NULL,
    `cancelled_by` VARCHAR(50) DEFAULT NULL,
    `cancellation_reason` TEXT DEFAULT NULL,
    `order_status` ENUM('Pending', 'Accepted', 'Preparing', 'Out For Delivery', 'Delivered', 'Completed', 'Cancelled') DEFAULT 'Pending',
    
    -- Customer Provided Notes & Special Instructions
    `notes` TEXT DEFAULT NULL,
    
    -- Razorpay Integration Parameters
    `razorpay_payment_id` VARCHAR(100) DEFAULT NULL,
    `razorpay_order_id` VARCHAR(100) DEFAULT NULL,
    `razorpay_route_transfer_id` VARCHAR(100) DEFAULT NULL,
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE RESTRICT,
    INDEX `idx_orders_status` (`order_status`, `payment_status`),
    INDEX `idx_orders_store` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order Items Table
CREATE TABLE IF NOT EXISTS `order_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL,
    `menu_item_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 6. DYNAMIC PROMOTIONS & GLOBAL OFFERS
-- ==========================================

-- Promotional Offers Table
-- Can represent merchant-specific offers or global platform coupons with subsidy percentages
CREATE TABLE IF NOT EXISTS `promotional_offers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT DEFAULT NULL, -- NULL represents a global level offer (funded by platform)
    `coupon_code` VARCHAR(50) UNIQUE NOT NULL,
    `offer_title` VARCHAR(150) NOT NULL,
    `offer_description` TEXT DEFAULT NULL,
    `discount_percentage` DECIMAL(5,2) NOT NULL,
    `min_order_value` DECIMAL(10,2) NOT NULL,
    
    -- Global Offer splits
    `admin_subsidy_percentage` DECIMAL(5,2) DEFAULT 0.00, -- e.g. 80.00% paid by admin to store
    `merchant_absorb_percentage` DECIMAL(5,2) DEFAULT 100.00, -- e.g. 20.00% absorbed by store
    
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `status` ENUM('Active', 'Expired', 'Draft') DEFAULT 'Active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    INDEX `idx_offers_active` (`coupon_code`, `status`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 7. REIMBURSEMENT PAYOUTS LEDGER
-- ==========================================

-- Payouts Ledger Table
-- Records the platform payouts to stores to reimburse global promo losses
CREATE TABLE IF NOT EXISTS `payouts_ledger` (
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

-- Reviews Table
CREATE TABLE IF NOT EXISTS `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `store_id` INT NOT NULL,
    `rating_stars` TINYINT NOT NULL CHECK (`rating_stars` BETWEEN 1 AND 5),
    `comment_text` TEXT DEFAULT NULL,
    `helpful_upvotes` INT DEFAULT 0,
    `is_popular` TINYINT(1) DEFAULT 0,
    
    -- Status flag for store-only deactivation moderation
    `status` ENUM('Public storefront', 'Deactivated for Store (User Active)') DEFAULT 'Public storefront',
    
    -- Special Title Nomination
    `chef_month_nominated` TINYINT(1) DEFAULT 0,
    
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
    INDEX `idx_reviews_stars` (`rating_stars`, `status`),
    INDEX `idx_reviews_store` (`store_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- 9. NOTIFICATIONS & CUSTOM CHANNELS
-- ==========================================

-- Notifications Table
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL, -- Target user (customer, seller_manager, or admin)
    `title` VARCHAR(150) NOT NULL,
    `message` TEXT NOT NULL,
    `is_read` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    INDEX `idx_notifications_user` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- User Notifications Table
CREATE TABLE IF NOT EXISTS `user_notifications` (
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

CREATE TABLE IF NOT EXISTS `custom_orders` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `customer_id` INT NOT NULL,
    `store_id` INT DEFAULT NULL,
    `dish_name` VARCHAR(150) NOT NULL,
    `ingredients` TEXT NOT NULL,
    `offered_price` DECIMAL(10,2) NOT NULL,
    `demanded_price` DECIMAL(10,2) DEFAULT NULL,
    
    -- Structured Attachments JSON Field:
    -- Schema: {
    --   "image": { "source_type": "url"|"upload", "url": "...", "path": "..." },
    --   "doc": { "source_type": "url"|"upload", "url": "...", "path": "..." },
    --   "video_url": "..."
    -- }
    `attachments` JSON DEFAULT NULL,
    
    -- Thread Notes JSON Field:
    -- Schema: [
    --   { "sender_role": "customer"|"seller", "note_text": "...", "created_at": "..." },
    --   ...
    -- ]
    `notes` JSON DEFAULT NULL,
    
    -- Fulfillment
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


CREATE TABLE IF NOT EXISTS `system_configurations` (
    `config_key` VARCHAR(100) PRIMARY KEY,
    `config_value` TEXT NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default system configurations
INSERT INTO `system_configurations` (`config_key`, `config_value`) VALUES
('global_payout_delay_days', '1'),
('razorpay_settlement_mode', 'automated_split'),
('platform_default_commission_rate', '5.00')
ON DUPLICATE KEY UPDATE `config_key` = `config_key`;


-- ==========================================
-- 12. RAW MATERIAL SALES
-- ==========================================

CREATE TABLE IF NOT EXISTS `raw_material_sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_id` INT NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `quantity` VARCHAR(100) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `status` VARCHAR(50) DEFAULT 'Available',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_material_orders` (
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
-- 9. USER PROFILE FEATURES
-- ==========================================

CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(50) NOT NULL,
    address_line1 VARCHAR(255) NOT NULL,
    address_line2 VARCHAR(255),
    city VARCHAR(100) NOT NULL,
    state VARCHAR(100),
    zip_code VARCHAR(20),
    lat DECIMAL(10,8) DEFAULT 40.7128,
    lng DECIMAL(11,8) DEFAULT -74.0060,
    is_default BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);



-- ==========================================
-- 10. PERFORMANCE OPTIMIZATION INDEXES
-- ==========================================
CREATE INDEX idx_stores_status ON stores(store_status, onboarding_status);
CREATE INDEX idx_menu_items_store ON menu_items(store_id, is_available);
CREATE INDEX idx_reviews_store_cust ON reviews(store_id, customer_id);
CREATE INDEX idx_orders_cust ON orders(customer_id, order_status);
CREATE INDEX idx_raw_materials_store ON raw_material_sales(store_id);

-- ==========================================
-- 11. ANALYTICS ROLLUP & RETENTION TABLES
-- ==========================================
CREATE TABLE IF NOT EXISTS `daily_revenue_rollups` (
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

CREATE TABLE IF NOT EXISTS `monthly_revenue_rollups` (
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

CREATE TABLE IF NOT EXISTS `yearly_revenue_rollups` (
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

-- Password Resets Table (Gmail SMTP Reset Verification)
CREATE TABLE IF NOT EXISTS `password_resets` (
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

