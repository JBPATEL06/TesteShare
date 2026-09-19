-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 27, 2026 at 02:31 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `testshare_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `billing_subscriptions`
--

CREATE TABLE `billing_subscriptions` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `tier` enum('Starter','Premium','Ultra Premium') NOT NULL,
  `price_per_month` decimal(10,2) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('Active','Expired','Cancelled') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_subscriptions`
--

INSERT INTO `billing_subscriptions` (`id`, `store_id`, `tier`, `price_per_month`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 2, 'Premium', 49.00, '2026-07-24', '2026-08-24', 'Cancelled', '2026-07-24 10:44:08'),
(2, 2, 'Starter', 0.00, '2026-07-24', '2026-08-24', 'Cancelled', '2026-07-24 10:44:23'),
(3, 2, 'Premium', 49.00, '2026-07-24', '2026-08-24', 'Active', '2026-07-24 11:49:25'),
(4, 3, '', 49.00, '2026-07-27', '2026-08-27', 'Cancelled', '2026-07-27 05:40:48'),
(5, 3, 'Premium', 49.00, '2026-07-27', '2026-08-27', 'Cancelled', '2026-07-27 05:49:41'),
(6, 3, 'Ultra Premium', 98.37, '2026-07-27', '2026-08-27', 'Active', '2026-07-27 06:08:34');

-- --------------------------------------------------------

--
-- Table structure for table `custom_orders`
--

CREATE TABLE `custom_orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `dish_name` varchar(150) NOT NULL,
  `ingredients` text NOT NULL,
  `offered_price` decimal(10,2) NOT NULL,
  `demanded_price` decimal(10,2) DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `notes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`notes`)),
  `delivery_date` date NOT NULL,
  `delivery_time` time NOT NULL,
  `status` enum('Pending','Quote Sent','Accepted','Rejected') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `daily_revenue_rollups`
--

CREATE TABLE `daily_revenue_rollups` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `rollup_date` date NOT NULL,
  `total_revenue` decimal(10,2) DEFAULT 0.00,
  `total_commission` decimal(10,2) DEFAULT 0.00,
  `net_earnings` decimal(10,2) DEFAULT 0.00,
  `orders_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `daily_revenue_rollups`
--

INSERT INTO `daily_revenue_rollups` (`id`, `store_id`, `rollup_date`, `total_revenue`, `total_commission`, `net_earnings`, `orders_count`, `created_at`) VALUES
(1, 1, '2026-07-24', 165.00, 8.26, 156.76, 4, '2026-07-27 06:14:25'),
(2, 2, '2026-07-24', 12.00, 0.28, 11.72, 3, '2026-07-27 06:14:25'),
(3, 4, '2026-07-24', 22.00, 1.10, 20.90, 1, '2026-07-27 06:14:25'),
(29, 1, '2026-07-27', 40.63, 2.04, 38.60, 3, '2026-07-27 06:55:49'),
(39, 2, '2026-07-27', 16.00, 0.16, 15.84, 2, '2026-07-27 11:29:41');

-- --------------------------------------------------------

--
-- Table structure for table `first_order_config`
--

CREATE TABLE `first_order_config` (
  `id` int(11) NOT NULL,
  `discount_percentage` decimal(5,2) DEFAULT 15.00,
  `max_discount_amount` decimal(10,2) DEFAULT 10.00,
  `is_active` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `first_order_config`
--

INSERT INTO `first_order_config` (`id`, `discount_percentage`, `max_discount_amount`, `is_active`, `updated_at`) VALUES
(1, 15.00, 10.00, 1, '2026-07-27 05:47:59');

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `category` varchar(100) NOT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `prep_time` int(11) DEFAULT 20,
  `ingredients` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `store_id`, `name`, `description`, `price`, `category`, `image_url`, `is_available`, `created_at`, `prep_time`, `ingredients`) VALUES
(1, 1, 'Butter Chicken', 'Tender tandoori chicken cooked in creamy, buttery sweet tomato gravy.', 12.50, 'North Indian', 'images/butter_chicken.png', 1, '2026-07-24 08:00:07', 25, 'Chicken, Butter, Cream, Tomato Puree, Onion, Garlic, Ginger, Garam Masala, Kashmiri Chilli'),
(2, 1, 'Paneer Tikka Masala', 'Grilled paneer cubes cooked in spicy spiced tomato onion masala gravel.', 10.00, 'North Indian', 'images/paneer_tikka.png', 1, '2026-07-24 08:00:07', 20, 'Paneer, Tomatoes, Onion, Bell Peppers, Cream, Cumin, Coriander, Fenugreek Leaves'),
(3, 2, 'Mango Lassi Shake', 'Creamy blend of yogurt and sweet Alphonso mangoes, served chilled.', 4.00, 'Drinks', 'images/mango_lassi.png', 1, '2026-07-24 08:00:07', 5, 'Alphonso Mango Pulp, Full-Fat Yogurt, Sugar, Cardamom, Saffron, Ice'),
(4, 2, 'Veg Hakka Noodles', 'Indo-Chinese street style stir fried noodles with crisp fresh vegetables.', 6.00, 'Appetizers', 'images/hakka_noodles.png', 1, '2026-07-24 08:00:07', 15, 'Noodles, Cabbage, Carrot, Capsicum, Spring Onion, Soy Sauce, Vinegar, Garlic'),
(5, 3, 'Masala Dosa', 'Thin crispy golden rice crepe filled with spiced potato masala.', 5.00, 'South Indian', 'images/masala_dosa.png', 1, '2026-07-24 08:00:07', 20, 'Rice Batter, Urad Dal, Potato, Onion, Mustard Seeds, Curry Leaves, Green Chilli, Turmeric'),
(6, 3, 'Filter Coffee', 'Traditional South Indian filter coffee whipped with hot frothy milk.', 2.50, 'Drinks', 'images/filter_coffee.png', 1, '2026-07-24 08:00:07', 5, 'Coffee Powder, Chicory, Full-Fat Milk, Sugar'),
(7, 4, 'Hyderabadi Chicken Biryani', 'Layered basmati rice and marinated chicken cooked dum-style with spices.', 9.50, 'Biryani', 'images/chicken_biryani.png', 1, '2026-07-24 08:00:07', 40, 'Basmati Rice, Chicken, Yogurt, Fried Onions, Saffron, Whole Spices, Mint, Ghee'),
(8, 4, 'Double Ka Meetha', 'Golden bread pieces soaked in sweet cardamom saffron syrup and rich rabri.', 4.00, 'Dessert', 'images/double_ka_meetha.png', 1, '2026-07-24 08:00:07', 30, 'Bread, Milk, Khoya, Sugar, Cardamom, Saffron, Cashews, Almonds, Raisins');

-- --------------------------------------------------------

--
-- Table structure for table `monthly_revenue_rollups`
--

CREATE TABLE `monthly_revenue_rollups` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `year_month` varchar(7) NOT NULL,
  `total_revenue` decimal(10,2) DEFAULT 0.00,
  `total_commission` decimal(10,2) DEFAULT 0.00,
  `net_earnings` decimal(10,2) DEFAULT 0.00,
  `orders_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `monthly_revenue_rollups`
--

INSERT INTO `monthly_revenue_rollups` (`id`, `store_id`, `year_month`, `total_revenue`, `total_commission`, `net_earnings`, `orders_count`, `created_at`) VALUES
(1, 1, '2026-07', 205.63, 10.30, 195.36, 7, '2026-07-27 06:17:48'),
(2, 2, '2026-07', 28.00, 0.44, 27.56, 5, '2026-07-27 06:17:48'),
(3, 4, '2026-07', 22.00, 1.10, 20.90, 1, '2026-07-27 06:17:48');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `is_read`, `created_at`) VALUES
(1, 2, '🛎 New Order Received — #UA-10', 'New order #UA-10: 1x Butter Chicken. Total: $12.50. Awaiting your acceptance.', 0, '2026-07-24 11:58:29'),
(2, 9, '🛎 New Order Received — #UA-11', 'New order #UA-11: 1x Mango Lassi Shake. Total: $4.00. Awaiting your acceptance.', 1, '2026-07-24 11:59:15'),
(3, 2, '🛎 New Order Received — #UA-12', 'New order #UA-12: 1x Butter Chicken. Total: $12.50. Awaiting your acceptance.', 0, '2026-07-24 12:06:34'),
(4, 9, '✅ Order Completed — #UA-11', 'Customer confirmed receipt for order #UA-11. Total: $4.00. Order is now marked as Completed.', 1, '2026-07-24 12:06:41'),
(5, 9, '🛎 New Order Received — #UA-13', 'New order #UA-13: 1x Mango Lassi Shake. Total: $4.00. Awaiting your acceptance.', 1, '2026-07-24 12:07:05'),
(6, 9, '✅ Order Completed — #UA-13', 'Customer confirmed receipt for order #UA-13. Total: $4.00. Order is now marked as Completed.', 1, '2026-07-24 12:07:36'),
(7, 2, '🛎 New Order Received — #UA-14', 'New order #UA-14: 1x Butter Chicken. Total: $12.50. Awaiting your acceptance.', 0, '2026-07-27 06:55:37'),
(8, 2, '🛎 New Order Received — #UA-15', 'New order #UA-15: 1x Butter Chicken. Total: $3.13. Awaiting your acceptance.', 0, '2026-07-27 11:07:09'),
(9, 2, '🛎 New Order Received — #UA-16', 'New order #UA-16: 2x Butter Chicken. Total: $25.00. Awaiting your acceptance.', 0, '2026-07-27 11:16:00'),
(10, 9, '🛎 New Order Received — #UA-17', 'New order #UA-17: 1x Mango Lassi Shake, 1x Veg Hakka Noodles. Total: $10.00. Awaiting your acceptance.', 1, '2026-07-27 11:23:06'),
(11, 9, '🛎 New Order Received — #UA-18', 'New order #UA-18: 1x Veg Hakka Noodles. Total: ₹6.00. Awaiting your acceptance.', 0, '2026-07-27 11:56:11');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `platform_commission` decimal(10,2) NOT NULL,
  `store_net_amount` decimal(10,2) NOT NULL,
  `payment_status` enum('Pending','Paid','Refunded','Failed') DEFAULT 'Pending',
  `refund_status` varchar(50) DEFAULT NULL,
  `refund_amount` decimal(10,2) DEFAULT 0.00,
  `refund_method` varchar(100) DEFAULT NULL,
  `cancelled_by` varchar(50) DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `order_status` enum('Pending','Accepted','Preparing','Out For Delivery','Delivered','Completed','Cancelled') DEFAULT 'Pending',
  `razorpay_payment_id` varchar(100) DEFAULT NULL,
  `razorpay_order_id` varchar(100) DEFAULT NULL,
  `razorpay_route_transfer_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `customer_id`, `store_id`, `total_amount`, `platform_commission`, `store_net_amount`, `payment_status`, `refund_status`, `refund_amount`, `refund_method`, `cancelled_by`, `cancellation_reason`, `order_status`, `razorpay_payment_id`, `razorpay_order_id`, `razorpay_route_transfer_id`, `created_at`, `updated_at`, `notes`) VALUES
(1, 4, 1, 100.00, 5.00, 95.00, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Completed', NULL, NULL, NULL, '2026-07-24 08:00:07', '2026-07-24 08:00:07', NULL),
(2, 4, 1, 40.00, 2.00, 38.00, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Completed', NULL, NULL, NULL, '2026-07-24 08:00:07', '2026-07-24 08:00:07', NULL),
(8, 12, 2, 4.00, 0.20, 3.80, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Completed', NULL, NULL, NULL, '2026-07-24 10:11:18', '2026-07-24 10:11:18', NULL),
(9, 12, 4, 22.00, 1.10, 20.90, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Pending', 'pay_test_UPI_1784893522813', NULL, NULL, '2026-07-24 11:45:22', '2026-07-24 11:45:22', NULL),
(10, 12, 1, 12.50, 0.63, 11.88, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Pending', 'pay_test_UPI_1784894309866', NULL, NULL, '2026-07-24 11:58:29', '2026-07-24 11:58:29', NULL),
(11, 12, 2, 4.00, 0.04, 3.96, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Completed', 'pay_test_UPI_1784894355144', NULL, NULL, '2026-07-24 11:59:15', '2026-07-24 12:06:41', NULL),
(12, 12, 1, 12.50, 0.63, 11.88, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Pending', 'pay_test_UPI_1784894794649', NULL, NULL, '2026-07-24 12:06:34', '2026-07-24 12:06:34', NULL),
(13, 12, 2, 4.00, 0.04, 3.96, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Completed', 'pay_test_UPI_1784894825127', NULL, NULL, '2026-07-24 12:07:05', '2026-07-24 12:07:36', NULL),
(14, 12, 1, 12.50, 0.63, 11.88, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Pending', 'pay_test_UPI_1785135337359', NULL, NULL, '2026-07-27 06:55:37', '2026-07-27 06:55:37', NULL),
(15, 12, 1, 3.13, 0.16, 2.97, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Pending', 'pay_test_UPI_1785150429517', NULL, NULL, '2026-07-27 11:07:09', '2026-07-27 11:07:09', NULL),
(16, 12, 1, 25.00, 1.25, 23.75, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Pending', 'pay_test_UPI_1785150960450', NULL, NULL, '2026-07-27 11:16:00', '2026-07-27 11:16:00', NULL),
(17, 12, 2, 10.00, 0.10, 9.90, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Cancelled', 'pay_test_UPI_1785151386216', NULL, NULL, '2026-07-27 11:23:06', '2026-07-27 11:44:23', NULL),
(18, 12, 2, 6.00, 0.06, 5.94, 'Paid', NULL, 0.00, NULL, NULL, NULL, 'Pending', 'pay_test_UPI_1785153371456', NULL, NULL, '2026-07-27 11:56:11', '2026-07-27 11:56:11', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `quantity`, `price`) VALUES
(1, 8, 3, 1, 4.00),
(2, 9, 1, 1, 12.50),
(3, 9, 7, 1, 9.50),
(4, 10, 1, 1, 12.50),
(5, 11, 3, 1, 4.00),
(6, 12, 1, 1, 12.50),
(7, 13, 3, 1, 4.00),
(8, 14, 1, 1, 12.50),
(9, 15, 1, 1, 12.50),
(10, 16, 1, 2, 12.50),
(11, 17, 3, 1, 4.00),
(12, 17, 4, 1, 6.00),
(13, 18, 4, 1, 6.00);

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `token` varchar(100) NOT NULL,
  `otp` varchar(10) DEFAULT NULL,
  `role` enum('user','seller','admin') DEFAULT 'user',
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payouts_ledger`
--

CREATE TABLE `payouts_ledger` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `offer_id` int(11) DEFAULT NULL,
  `discharged_reimbursement` decimal(10,2) NOT NULL,
  `settlement_status` enum('Escrow Hold','Transferred','Settled') DEFAULT 'Escrow Hold',
  `transfer_reference` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payouts_ledger`
--

INSERT INTO `payouts_ledger` (`id`, `store_id`, `order_id`, `offer_id`, `discharged_reimbursement`, `settlement_status`, `transfer_reference`, `created_at`) VALUES
(1, 1, 1, 1, 40.00, 'Escrow Hold', NULL, '2026-07-27 06:11:49'),
(2, 1, 2, 1, 16.00, 'Escrow Hold', NULL, '2026-07-27 06:11:49'),
(3, 2, 8, 1, 1.60, 'Escrow Hold', NULL, '2026-07-27 06:11:49'),
(4, 4, 9, 1, 8.80, 'Escrow Hold', NULL, '2026-07-27 06:11:49'),
(5, 1, 10, 1, 5.00, 'Escrow Hold', NULL, '2026-07-27 06:11:49'),
(6, 2, 11, 1, 1.60, 'Escrow Hold', NULL, '2026-07-27 06:11:49'),
(7, 1, 12, 1, 5.00, 'Escrow Hold', NULL, '2026-07-27 06:11:49'),
(8, 2, 13, 1, 1.60, 'Settled', 'TXN-6A674E070A7B7', '2026-07-27 06:11:49');

-- --------------------------------------------------------

--
-- Table structure for table `promotional_offers`
--

CREATE TABLE `promotional_offers` (
  `id` int(11) NOT NULL,
  `store_id` int(11) DEFAULT NULL,
  `coupon_code` varchar(50) NOT NULL,
  `offer_title` varchar(150) NOT NULL,
  `offer_description` text DEFAULT NULL,
  `discount_percentage` decimal(5,2) NOT NULL,
  `min_order_value` decimal(10,2) NOT NULL,
  `admin_subsidy_percentage` decimal(5,2) DEFAULT 0.00,
  `merchant_absorb_percentage` decimal(5,2) DEFAULT 100.00,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('Active','Expired','Draft') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `promotional_offers`
--

INSERT INTO `promotional_offers` (`id`, `store_id`, `coupon_code`, `offer_title`, `offer_description`, `discount_percentage`, `min_order_value`, `admin_subsidy_percentage`, `merchant_absorb_percentage`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, NULL, 'SUPER50', 'Super 50% Off Promo', '50% off all orders above $20', 50.00, 0.00, 80.00, 20.00, '2026-07-24 13:30:07', '2026-08-23 13:30:07', 'Active', '2026-07-24 08:00:07'),
(2, 2, 'RPG50', 'rapid', 'Promotional discount coupon by Central Bakery', 75.00, 10.00, 0.00, 100.00, '2026-07-27 16:21:29', '2026-08-26 16:21:29', 'Active', '2026-07-27 10:51:29'),
(3, 2, 'RPG', 'rapid', 'Promotional discount coupon by Central Bakery', 75.00, 10.00, 0.00, 100.00, '2026-07-27 16:35:08', '2026-08-26 16:35:08', 'Active', '2026-07-27 11:05:08'),
(4, NULL, 'MEGA50', 'Global 50% Off', 'Platform subsidized discount', 50.00, 0.00, 60.00, 40.00, '2026-07-27 17:54:48', '2026-08-26 17:54:48', 'Active', '2026-07-27 12:24:48');

-- --------------------------------------------------------

--
-- Table structure for table `raw_material_sales`
--

CREATE TABLE `raw_material_sales` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `quantity` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` varchar(50) DEFAULT 'Available'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `raw_material_sales`
--

INSERT INTO `raw_material_sales` (`id`, `store_id`, `name`, `quantity`, `price`, `description`, `created_at`, `status`) VALUES
(1, 2, 'dfdf', 'df', 122.00, '1q', '2026-07-24 09:53:15', 'Sold Out');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `rating_stars` tinyint(4) NOT NULL CHECK (`rating_stars` between 1 and 5),
  `comment_text` text DEFAULT NULL,
  `helpful_upvotes` int(11) DEFAULT 0,
  `is_popular` tinyint(1) DEFAULT 0,
  `status` enum('Public storefront','Deactivated for Store (User Active)') DEFAULT 'Public storefront',
  `chef_month_nominated` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `customer_id`, `store_id`, `rating_stars`, `comment_text`, `helpful_upvotes`, `is_popular`, `status`, `chef_month_nominated`, `created_at`) VALUES
(1, 4, 1, 5, 'The Butter Chicken and Butter Naan were absolutely delicious! Perfectly cooked and spiced.', 14, 1, 'Public storefront', 0, '2026-07-24 08:00:07'),
(2, 12, 1, 3, 'yuy', 0, 0, 'Public storefront', 0, '2026-07-24 10:06:32'),
(3, 12, 2, 5, 'errer', 0, 0, 'Deactivated for Store (User Active)', 1, '2026-07-24 12:07:48');

-- --------------------------------------------------------

--
-- Table structure for table `stores`
--

CREATE TABLE `stores` (
  `id` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `store_name` varchar(150) NOT NULL,
  `category` varchar(100) NOT NULL,
  `contact_email` varchar(150) NOT NULL,
  `contact_phone` varchar(20) NOT NULL,
  `address` text NOT NULL,
  `city` varchar(100) NOT NULL,
  `pincode` varchar(10) NOT NULL,
  `gstin` varchar(20) DEFAULT NULL,
  `pan` varchar(15) DEFAULT NULL,
  `fssai` varchar(20) DEFAULT NULL,
  `bank_holder_name` varchar(100) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `bank_account_number` varchar(50) NOT NULL,
  `bank_ifsc` varchar(20) NOT NULL,
  `commission_rate` decimal(5,2) DEFAULT 5.00,
  `subscription_tier` varchar(50) DEFAULT 'Starter',
  `onboarding_status` enum('Under Review','Approved','Rejected') DEFAULT 'Under Review',
  `store_status` enum('Active','Deactivated') DEFAULT 'Active',
  `accept_system_status` enum('Auto Accept','Manual Accept') DEFAULT 'Auto Accept',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `lat` decimal(10,8) DEFAULT 40.71280000,
  `lng` decimal(11,8) DEFAULT -74.00600000,
  `delivery_radius` decimal(5,2) DEFAULT 5.00,
  `country` varchar(100) DEFAULT 'United States',
  `opening_time` time DEFAULT '09:00:00',
  `closing_time` time DEFAULT '22:00:00',
  `store_logo` text DEFAULT NULL,
  `store_banner` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `stores`
--

INSERT INTO `stores` (`id`, `owner_id`, `store_name`, `category`, `contact_email`, `contact_phone`, `address`, `city`, `pincode`, `gstin`, `pan`, `fssai`, `bank_holder_name`, `bank_name`, `bank_account_number`, `bank_ifsc`, `commission_rate`, `subscription_tier`, `onboarding_status`, `store_status`, `accept_system_status`, `created_at`, `updated_at`, `lat`, `lng`, `delivery_radius`, `country`, `opening_time`, `closing_time`, `store_logo`, `store_banner`) VALUES
(1, 2, 'Royal Punjab Grill', 'North Indian & Tandoori', 'contact@royalpunjab.in', '8765432109', 'Shop 12, Connaught Place, Block E', 'New Delhi', '110001', '07AAAAA1234A1Z5', 'ABCDE1234F', '10023011000456', 'Royal Punjab Grill LLP', 'State Bank of India', '3009876543210', 'SBIN0001234', 5.00, 'Starter', 'Approved', 'Active', 'Auto Accept', '2026-07-24 08:00:07', '2026-07-27 05:36:22', 40.71280000, -74.00600000, 5.00, 'United States', '09:00:00', '22:00:00', NULL, NULL),
(2, 9, 'Central Bakery', 'Bakery & Cafe', 'contact@centralbakery.in', '9924883921', '88 Hill Road, Bandra West', 'Rajkot', '360001', '27BBBBB1234B1Z5', 'ABCDE5678G', '10024022000819', 'Central Bakery Corp', 'HDFC Bank', '50100987654321', 'HDFC0000060', 1.00, 'Premium', 'Approved', 'Active', 'Auto Accept', '2026-07-24 08:00:07', '2026-07-27 11:22:19', 22.29760000, 70.78740000, 25.00, 'India', '09:00:00', '22:00:00', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALgAAACUCAMAAAAXgxO4AAAAaVBMVEX///8AAAD4+Pj19fXZ2dnT09P8/Pzc3Nzv7+94eHigoKCampqmpqY4ODiUlJRra2skJCTFxcVeXl4yMjK3t7exsbFJSUlDQ0N+fn5kZGRxcXEVFRVZWVni4uIMDAwrKyuJiYkdHR1RUVEFxxLtAAAEHUlEQVR4nO2c2ZaiMBBADQEMmxBWQWml//8jB9qxXUBJIkmFmdyneZiHeziVSlWl7M3GYDAYDAaDwWAwGAwGg+HfxLVwj+VCe3BBbFoEXp2mtRcU1CbQPmxgGjQZuiNrAoqhrWbBYXpCI05pqLe6G0Zj6wtRqG+4u/Sl9o861VSdBO+0BwItj+k2nvNGKN5CW46x34bJb7jY0J7P2CzaA5qZ21+s4l8OtOs9bcnqjVDZQtveIA27N0KNPrkl5PFGKIT2veLweSOkS5h/84p/QxtfyHm9EcqhnQeslF88taCte/KOX7zT4JNbHr83Qnv4T+4w1SjPRPCJReBoDoDHinUWEz9DxwoRipQ+VqDv/VbMGyHoUouKilNgcc766gZ0pbUTFd8BiwtdPwMesDhDaz9NbMTFWG2orPZwrjYdrvYC2oqKQ08RRYusDLrIWm1Zu9pGYuNk85ZjMvjWDQtdQR54pGw2xYHf+1BAW/fgI7/4UYvHw7WO4DYux1T/QqnJuyH3mFmbd6DZB85HAmjfXwhXOxFD3/Z3bCt27wq6vHrAWelzIccB1cx7s7En1lTGnLSKkws2w5NKqk0ivIfMNs47jfLJPWtdtOnBfvdKu/O1KKxegv0yGVsnpebaAyTfxQ8hE9VnqmlwP0OcPDw3w8Jkcw5zR3NrGjxEg4VJD35o0XAAPQZ6xm1/phTvAxn7w/85t/rkFkKv1WHi2y/cse1fD22sScRb9H4/KPEKZ+SOncK7zzUN1aDJ33rP6a+q98XtQBKn2NfPVW/igdcs4eSNczhlUVWVZVVF2WlyfNHBjplbgdHElSPgEy3laHzGVGC5sRAaG97IgOZZvsDs7ZGDD+HNOZOYRv2kwvWX8O4vW9X3aD5RvIqQKI5zh6k1ZjJX2/Z3S3n3V5FK73o5b4Rqdd7Cz8nTKLv9eSaFLKiaJrrCiwev2KnJicy/42BHyYDLXfRkXmhUfHIJH1zNJxdeZnqHgkUnwjzC5+FLfv+8eEq5IH3TCXP/koONb9mzRbFliXmkr1MsVIaPkdwMWXtZ4pJ/NbHlfrdnpZRbsNCPG+RXHOQOKwQ3sFiQugqyVIs8hdS2GUs7m/3plJnJiYTK8EotU9ySdOEP7KTmQyIvj0sus8R+kzePJ/8dVHCZ9j176do9Qbe0dqdm9ukWi83fLpwKVZNPsd9wvkLlgrC1YDshvYV4JFxqzKz89c1ZpNtvAHbLiP/xGT35MI/j9oeXUQO3WuZ80BCVsBuIedqJWCcp+Ao5zvfcCSbZ5zqsaGFnxzVsyXbjxRAgXEKZ/3JGSok+K0IDbh5HMzGTRHGul/RfMA28tOqmnLsq9fT+G3zD5l5wjo9lFSU9UVUe43MQUkezAJnGIm3bbu2ebf8PosEClsFgMBgM/zt/AISTOTGwsGWcAAAAAElFTkSuQmCC', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALgAAACUCAMAAAAXgxO4AAAAaVBMVEX///8AAAD4+Pj19fXZ2dnT09P8/Pzc3Nzv7+94eHigoKCampqmpqY4ODiUlJRra2skJCTFxcVeXl4yMjK3t7exsbFJSUlDQ0N+fn5kZGRxcXEVFRVZWVni4uIMDAwrKyuJiYkdHR1RUVEFxxLtAAAEHUlEQVR4nO2c2ZaiMBBADQEMmxBWQWml//8jB9qxXUBJIkmFmdyneZiHeziVSlWl7M3GYDAYDAaDwWAwGAwGg+HfxLVwj+VCe3BBbFoEXp2mtRcU1CbQPmxgGjQZuiNrAoqhrWbBYXpCI05pqLe6G0Zj6wtRqG+4u/Sl9o861VSdBO+0BwItj+k2nvNGKN5CW46x34bJb7jY0J7P2CzaA5qZ21+s4l8OtOs9bcnqjVDZQtveIA27N0KNPrkl5PFGKIT2veLweSOkS5h/84p/QxtfyHm9EcqhnQeslF88taCte/KOX7zT4JNbHr83Qnv4T+4w1SjPRPCJReBoDoDHinUWEz9DxwoRipQ+VqDv/VbMGyHoUouKilNgcc766gZ0pbUTFd8BiwtdPwMesDhDaz9NbMTFWG2orPZwrjYdrvYC2oqKQ08RRYusDLrIWm1Zu9pGYuNk85ZjMvjWDQtdQR54pGw2xYHf+1BAW/fgI7/4UYvHw7WO4DYux1T/QqnJuyH3mFmbd6DZB85HAmjfXwhXOxFD3/Z3bCt27wq6vHrAWelzIccB1cx7s7En1lTGnLSKkws2w5NKqk0ivIfMNs47jfLJPWtdtOnBfvdKu/O1KKxegv0yGVsnpebaAyTfxQ8hE9VnqmlwP0OcPDw3w8Jkcw5zR3NrGjxEg4VJD35o0XAAPQZ6xm1/phTvAxn7w/85t/rkFkKv1WHi2y/cse1fD22sScRb9H4/KPEKZ+SOncK7zzUN1aDJ33rP6a+q98XtQBKn2NfPVW/igdcs4eSNczhlUVWVZVVF2WlyfNHBjplbgdHElSPgEy3laHzGVGC5sRAaG97IgOZZvsDs7ZGDD+HNOZOYRv2kwvWX8O4vW9X3aD5RvIqQKI5zh6k1ZjJX2/Z3S3n3V5FK73o5b4Rqdd7Cz8nTKLv9eSaFLKiaJrrCiwev2KnJicy/42BHyYDLXfRkXmhUfHIJH1zNJxdeZnqHgkUnwjzC5+FLfv+8eEq5IH3TCXP/koONb9mzRbFliXmkr1MsVIaPkdwMWXtZ4pJ/NbHlfrdnpZRbsNCPG+RXHOQOKwQ3sFiQugqyVIs8hdS2GUs7m/3plJnJiYTK8EotU9ySdOEP7KTmQyIvj0sus8R+kzePJ/8dVHCZ9j176do9Qbe0dqdm9ukWi83fLpwKVZNPsd9wvkLlgrC1YDshvYV4JFxqzKz89c1ZpNtvAHbLiP/xGT35MI/j9oeXUQO3WuZ80BCVsBuIedqJWCcp+Ao5zvfcCSbZ5zqsaGFnxzVsyXbjxRAgXEKZ/3JGSok+K0IDbh5HMzGTRHGul/RfMA28tOqmnLsq9fT+G3zD5l5wjo9lFSU9UVUe43MQUkezAJnGIm3bbu2ebf8PosEClsFgMBgM/zt/AISTOTGwsGWcAAAAAElFTkSuQmCC'),
(3, 10, 'Curry Leaves', 'South Indian & Tiffin', 'contact@curryleaves.in', '9924883922', '99 Nungambakkam High Road', 'Chennai', '600034', '33CCCCC1234C1Z5', 'ABCDE9012H', '10024099000902', 'Kunal Kapur Hospitality', 'ICICI Bank', '000401987654', 'ICIC0000004', 0.00, 'Ultra Premium', 'Approved', 'Active', 'Auto Accept', '2026-07-24 08:00:07', '2026-07-27 11:17:42', 40.71280000, -74.00600000, 5.00, 'United States', '09:00:00', '22:00:00', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALgAAACUCAMAAAAXgxO4AAAAaVBMVEX///8AAAD4+Pj19fXZ2dnT09P8/Pzc3Nzv7+94eHigoKCampqmpqY4ODiUlJRra2skJCTFxcVeXl4yMjK3t7exsbFJSUlDQ0N+fn5kZGRxcXEVFRVZWVni4uIMDAwrKyuJiYkdHR1RUVEFxxLtAAAEHUlEQVR4nO2c2ZaiMBBADQEMmxBWQWml//8jB9qxXUBJIkmFmdyneZiHeziVSlWl7M3GYDAYDAaDwWAwGAwGg+HfxLVwj+VCe3BBbFoEXp2mtRcU1CbQPmxgGjQZuiNrAoqhrWbBYXpCI05pqLe6G0Zj6wtRqG+4u/Sl9o861VSdBO+0BwItj+k2nvNGKN5CW46x34bJb7jY0J7P2CzaA5qZ21+s4l8OtOs9bcnqjVDZQtveIA27N0KNPrkl5PFGKIT2veLweSOkS5h/84p/QxtfyHm9EcqhnQeslF88taCte/KOX7zT4JNbHr83Qnv4T+4w1SjPRPCJReBoDoDHinUWEz9DxwoRipQ+VqDv/VbMGyHoUouKilNgcc766gZ0pbUTFd8BiwtdPwMesDhDaz9NbMTFWG2orPZwrjYdrvYC2oqKQ08RRYusDLrIWm1Zu9pGYuNk85ZjMvjWDQtdQR54pGw2xYHf+1BAW/fgI7/4UYvHw7WO4DYux1T/QqnJuyH3mFmbd6DZB85HAmjfXwhXOxFD3/Z3bCt27wq6vHrAWelzIccB1cx7s7En1lTGnLSKkws2w5NKqk0ivIfMNs47jfLJPWtdtOnBfvdKu/O1KKxegv0yGVsnpebaAyTfxQ8hE9VnqmlwP0OcPDw3w8Jkcw5zR3NrGjxEg4VJD35o0XAAPQZ6xm1/phTvAxn7w/85t/rkFkKv1WHi2y/cse1fD22sScRb9H4/KPEKZ+SOncK7zzUN1aDJ33rP6a+q98XtQBKn2NfPVW/igdcs4eSNczhlUVWVZVVF2WlyfNHBjplbgdHElSPgEy3laHzGVGC5sRAaG97IgOZZvsDs7ZGDD+HNOZOYRv2kwvWX8O4vW9X3aD5RvIqQKI5zh6k1ZjJX2/Z3S3n3V5FK73o5b4Rqdd7Cz8nTKLv9eSaFLKiaJrrCiwev2KnJicy/42BHyYDLXfRkXmhUfHIJH1zNJxdeZnqHgkUnwjzC5+FLfv+8eEq5IH3TCXP/koONb9mzRbFliXmkr1MsVIaPkdwMWXtZ4pJ/NbHlfrdnpZRbsNCPG+RXHOQOKwQ3sFiQugqyVIs8hdS2GUs7m/3plJnJiYTK8EotU9ySdOEP7KTmQyIvj0sus8R+kzePJ/8dVHCZ9j176do9Qbe0dqdm9ukWi83fLpwKVZNPsd9wvkLlgrC1YDshvYV4JFxqzKz89c1ZpNtvAHbLiP/xGT35MI/j9oeXUQO3WuZ80BCVsBuIedqJWCcp+Ao5zvfcCSbZ5zqsaGFnxzVsyXbjxRAgXEKZ/3JGSok+K0IDbh5HMzGTRHGul/RfMA28tOqmnLsq9fT+G3zD5l5wjo9lFSU9UVUe43MQUkezAJnGIm3bbu2ebf8PosEClsFgMBgM/zt/AISTOTGwsGWcAAAAAElFTkSuQmCC', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAALgAAACUCAMAAAAXgxO4AAAAaVBMVEX///8AAAD4+Pj19fXZ2dnT09P8/Pzc3Nzv7+94eHigoKCampqmpqY4ODiUlJRra2skJCTFxcVeXl4yMjK3t7exsbFJSUlDQ0N+fn5kZGRxcXEVFRVZWVni4uIMDAwrKyuJiYkdHR1RUVEFxxLtAAAEHUlEQVR4nO2c2ZaiMBBADQEMmxBWQWml//8jB9qxXUBJIkmFmdyneZiHeziVSlWl7M3GYDAYDAaDwWAwGAwGg+HfxLVwj+VCe3BBbFoEXp2mtRcU1CbQPmxgGjQZuiNrAoqhrWbBYXpCI05pqLe6G0Zj6wtRqG+4u/Sl9o861VSdBO+0BwItj+k2nvNGKN5CW46x34bJb7jY0J7P2CzaA5qZ21+s4l8OtOs9bcnqjVDZQtveIA27N0KNPrkl5PFGKIT2veLweSOkS5h/84p/QxtfyHm9EcqhnQeslF88taCte/KOX7zT4JNbHr83Qnv4T+4w1SjPRPCJReBoDoDHinUWEz9DxwoRipQ+VqDv/VbMGyHoUouKilNgcc766gZ0pbUTFd8BiwtdPwMesDhDaz9NbMTFWG2orPZwrjYdrvYC2oqKQ08RRYusDLrIWm1Zu9pGYuNk85ZjMvjWDQtdQR54pGw2xYHf+1BAW/fgI7/4UYvHw7WO4DYux1T/QqnJuyH3mFmbd6DZB85HAmjfXwhXOxFD3/Z3bCt27wq6vHrAWelzIccB1cx7s7En1lTGnLSKkws2w5NKqk0ivIfMNs47jfLJPWtdtOnBfvdKu/O1KKxegv0yGVsnpebaAyTfxQ8hE9VnqmlwP0OcPDw3w8Jkcw5zR3NrGjxEg4VJD35o0XAAPQZ6xm1/phTvAxn7w/85t/rkFkKv1WHi2y/cse1fD22sScRb9H4/KPEKZ+SOncK7zzUN1aDJ33rP6a+q98XtQBKn2NfPVW/igdcs4eSNczhlUVWVZVVF2WlyfNHBjplbgdHElSPgEy3laHzGVGC5sRAaG97IgOZZvsDs7ZGDD+HNOZOYRv2kwvWX8O4vW9X3aD5RvIqQKI5zh6k1ZjJX2/Z3S3n3V5FK73o5b4Rqdd7Cz8nTKLv9eSaFLKiaJrrCiwev2KnJicy/42BHyYDLXfRkXmhUfHIJH1zNJxdeZnqHgkUnwjzC5+FLfv+8eEq5IH3TCXP/koONb9mzRbFliXmkr1MsVIaPkdwMWXtZ4pJ/NbHlfrdnpZRbsNCPG+RXHOQOKwQ3sFiQugqyVIs8hdS2GUs7m/3plJnJiYTK8EotU9ySdOEP7KTmQyIvj0sus8R+kzePJ/8dVHCZ9j176do9Qbe0dqdm9ukWi83fLpwKVZNPsd9wvkLlgrC1YDshvYV4JFxqzKz89c1ZpNtvAHbLiP/xGT35MI/j9oeXUQO3WuZ80BCVsBuIedqJWCcp+Ao5zvfcCSbZ5zqsaGFnxzVsyXbjxRAgXEKZ/3JGSok+K0IDbh5HMzGTRHGul/RfMA28tOqmnLsq9fT+G3zD5l5wjo9lFSU9UVUe43MQUkezAJnGIm3bbu2ebf8PosEClsFgMBgM/zt/AISTOTGwsGWcAAAAAElFTkSuQmCC'),
(4, 11, 'Pista House Biryani', 'Hyderabadi Haleem & Biryani', 'owner@pistahouse.com', '9924883923', 'Charminar Road, Shalibanda', 'Hyderabad', '500002', '36DDDDD1234D1Z5', 'ABCDE3456I', '10024033000123', 'Pista House Corp', 'State Bank of India', '200123456789', 'SBIN0004567', 5.00, 'Starter', 'Approved', 'Active', 'Auto Accept', '2026-07-24 08:00:07', '2026-07-27 11:17:43', 40.71280000, -74.00600000, 5.00, 'United States', '09:00:00', '22:00:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `store_material_orders`
--

CREATE TABLE `store_material_orders` (
  `id` int(11) NOT NULL,
  `buyer_store_id` int(11) NOT NULL,
  `seller_store_id` int(11) NOT NULL,
  `material_name` varchar(150) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `quantity` varchar(100) NOT NULL,
  `order_status` enum('Pending','Accepted','Out For Delivery','Delivered','Completed','Cancelled') DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `store_material_orders`
--

INSERT INTO `store_material_orders` (`id`, `buyer_store_id`, `seller_store_id`, `material_name`, `price`, `quantity`, `order_status`, `created_at`) VALUES
(1, 3, 2, 'dfdf', 122.00, 'df', 'Pending', '2026-07-27 06:08:48'),
(2, 3, 2, 'dfdf', 122.00, 'df', 'Pending', '2026-07-27 06:13:33'),
(3, 3, 2, 'dfdf', 122.00, 'df', 'Pending', '2026-07-27 06:15:50');

-- --------------------------------------------------------

--
-- Table structure for table `store_theme_config`
--

CREATE TABLE `store_theme_config` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `accent_color` varchar(20) DEFAULT '#ff9f0d',
  `secondary_color` varchar(20) DEFAULT '#2d2d2d',
  `banner_style` varchar(50) DEFAULT 'default',
  `custom_css` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `store_theme_config`
--

INSERT INTO `store_theme_config` (`id`, `store_id`, `accent_color`, `secondary_color`, `banner_style`, `custom_css`, `updated_at`) VALUES
(1, 3, '#e63946', '#2d2d2d', 'default', '', '2026-07-27 05:53:01'),
(2, 2, '#e63946', '#2d2d2d', 'default', '', '2026-07-27 11:55:11');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('customer','seller_manager','super_admin') DEFAULT 'customer',
  `status` enum('Active','Suspended','Banned') DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

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
(11, 'Zeeshan Ali', 'owner@pistahouse.com', '9924883923', '$2y$10$UizcTiW37aH10GMvTMsByeiBb0i48k0KzRwkG.9p8S5/XtXfHy7Pq', 'seller_manager', 'Active', '2026-07-24 08:00:07', '2026-07-24 08:00:07', NULL),
(12, 'bhanderi jeel', 'bhanderijeel8@gmail.com', '1234567890', '$2y$10$2YNvrMeQ9ap8OXmUkpaxL.wRWJnmsgfBZF8MEg8cdXaeFeg/WY7qO', 'customer', 'Active', '2026-07-24 10:02:37', '2026-07-27 12:19:21', 'uploads/jeel_avatar.png');

-- --------------------------------------------------------

--
-- Table structure for table `user_addresses`
--

CREATE TABLE `user_addresses` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `label` varchar(50) NOT NULL,
  `address_line1` varchar(255) NOT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `state` varchar(100) DEFAULT NULL,
  `zip_code` varchar(20) DEFAULT NULL,
  `is_default` tinyint(1) DEFAULT 0,
  `lat` decimal(10,8) DEFAULT 40.71280000,
  `lng` decimal(11,8) DEFAULT -74.00600000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_addresses`
--

INSERT INTO `user_addresses` (`id`, `user_id`, `label`, `address_line1`, `address_line2`, `city`, `state`, `zip_code`, `is_default`, `lat`, `lng`) VALUES
(5, 12, 'Home', 'RER', NULL, 'Rajkot', 'NY', '360001', 1, 22.29760000, 70.78740000);

-- --------------------------------------------------------

--
-- Table structure for table `user_notifications`
--

CREATE TABLE `user_notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `type` enum('Order Update','Custom Order','System') DEFAULT 'System',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `yearly_revenue_rollups`
--

CREATE TABLE `yearly_revenue_rollups` (
  `id` int(11) NOT NULL,
  `store_id` int(11) NOT NULL,
  `rollup_year` int(11) NOT NULL,
  `total_revenue` decimal(10,2) DEFAULT 0.00,
  `total_commission` decimal(10,2) DEFAULT 0.00,
  `net_earnings` decimal(10,2) DEFAULT 0.00,
  `orders_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `yearly_revenue_rollups`
--

INSERT INTO `yearly_revenue_rollups` (`id`, `store_id`, `rollup_year`, `total_revenue`, `total_commission`, `net_earnings`, `orders_count`, `created_at`) VALUES
(1, 1, 2026, 205.63, 10.30, 195.36, 7, '2026-07-27 06:17:48'),
(2, 2, 2026, 28.00, 0.44, 27.56, 5, '2026-07-27 06:17:48'),
(3, 4, 2026, 22.00, 1.10, 20.90, 1, '2026-07-27 06:17:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `billing_subscriptions`
--
ALTER TABLE `billing_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `idx_subscriptions_dates` (`end_date`,`status`);

--
-- Indexes for table `custom_orders`
--
ALTER TABLE `custom_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `daily_revenue_rollups`
--
ALTER TABLE `daily_revenue_rollups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_date_unique` (`store_id`,`rollup_date`);

--
-- Indexes for table `first_order_config`
--
ALTER TABLE `first_order_config`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_menu_store` (`store_id`,`category`);

--
-- Indexes for table `monthly_revenue_rollups`
--
ALTER TABLE `monthly_revenue_rollups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_month_unique` (`store_id`,`year_month`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notifications_user` (`user_id`,`is_read`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_orders_status` (`order_status`,`payment_status`),
  ADD KEY `idx_orders_store` (`store_id`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `menu_item_id` (`menu_item_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_reset_token` (`token`),
  ADD KEY `idx_reset_email` (`email`);

--
-- Indexes for table `payouts_ledger`
--
ALTER TABLE `payouts_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `offer_id` (`offer_id`),
  ADD KEY `idx_payouts_status` (`store_id`,`settlement_status`);

--
-- Indexes for table `promotional_offers`
--
ALTER TABLE `promotional_offers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `coupon_code` (`coupon_code`),
  ADD KEY `store_id` (`store_id`),
  ADD KEY `idx_offers_active` (`coupon_code`,`status`,`end_date`);

--
-- Indexes for table `raw_material_sales`
--
ALTER TABLE `raw_material_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `store_id` (`store_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_reviews_stars` (`rating_stars`,`status`),
  ADD KEY `idx_reviews_store` (`store_id`);

--
-- Indexes for table `stores`
--
ALTER TABLE `stores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `owner_id` (`owner_id`),
  ADD KEY `idx_stores_onboarding` (`onboarding_status`),
  ADD KEY `idx_stores_status` (`store_status`);

--
-- Indexes for table `store_material_orders`
--
ALTER TABLE `store_material_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `buyer_store_id` (`buyer_store_id`),
  ADD KEY `seller_store_id` (`seller_store_id`);

--
-- Indexes for table `store_theme_config`
--
ALTER TABLE `store_theme_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_id` (`store_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_email` (`email`),
  ADD KEY `idx_users_role_status` (`role`,`status`);

--
-- Indexes for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `yearly_revenue_rollups`
--
ALTER TABLE `yearly_revenue_rollups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `store_year_unique` (`store_id`,`rollup_year`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `billing_subscriptions`
--
ALTER TABLE `billing_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `custom_orders`
--
ALTER TABLE `custom_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `daily_revenue_rollups`
--
ALTER TABLE `daily_revenue_rollups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `first_order_config`
--
ALTER TABLE `first_order_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `monthly_revenue_rollups`
--
ALTER TABLE `monthly_revenue_rollups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payouts_ledger`
--
ALTER TABLE `payouts_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `promotional_offers`
--
ALTER TABLE `promotional_offers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `raw_material_sales`
--
ALTER TABLE `raw_material_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `stores`
--
ALTER TABLE `stores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `store_material_orders`
--
ALTER TABLE `store_material_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `store_theme_config`
--
ALTER TABLE `store_theme_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_addresses`
--
ALTER TABLE `user_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `user_notifications`
--
ALTER TABLE `user_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `yearly_revenue_rollups`
--
ALTER TABLE `yearly_revenue_rollups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `billing_subscriptions`
--
ALTER TABLE `billing_subscriptions`
  ADD CONSTRAINT `billing_subscriptions_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `custom_orders`
--
ALTER TABLE `custom_orders`
  ADD CONSTRAINT `custom_orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `custom_orders_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `daily_revenue_rollups`
--
ALTER TABLE `daily_revenue_rollups`
  ADD CONSTRAINT `daily_revenue_rollups_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD CONSTRAINT `menu_items_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `monthly_revenue_rollups`
--
ALTER TABLE `monthly_revenue_rollups`
  ADD CONSTRAINT `monthly_revenue_rollups_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`);

--
-- Constraints for table `payouts_ledger`
--
ALTER TABLE `payouts_ledger`
  ADD CONSTRAINT `payouts_ledger_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `payouts_ledger_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  ADD CONSTRAINT `payouts_ledger_ibfk_3` FOREIGN KEY (`offer_id`) REFERENCES `promotional_offers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `promotional_offers`
--
ALTER TABLE `promotional_offers`
  ADD CONSTRAINT `promotional_offers_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `raw_material_sales`
--
ALTER TABLE `raw_material_sales`
  ADD CONSTRAINT `raw_material_sales_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stores`
--
ALTER TABLE `stores`
  ADD CONSTRAINT `stores_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `store_material_orders`
--
ALTER TABLE `store_material_orders`
  ADD CONSTRAINT `store_material_orders_ibfk_1` FOREIGN KEY (`buyer_store_id`) REFERENCES `stores` (`id`),
  ADD CONSTRAINT `store_material_orders_ibfk_2` FOREIGN KEY (`seller_store_id`) REFERENCES `stores` (`id`);

--
-- Constraints for table `store_theme_config`
--
ALTER TABLE `store_theme_config`
  ADD CONSTRAINT `store_theme_config_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_addresses`
--
ALTER TABLE `user_addresses`
  ADD CONSTRAINT `user_addresses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_notifications`
--
ALTER TABLE `user_notifications`
  ADD CONSTRAINT `user_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `yearly_revenue_rollups`
--
ALTER TABLE `yearly_revenue_rollups`
  ADD CONSTRAINT `yearly_revenue_rollups_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
