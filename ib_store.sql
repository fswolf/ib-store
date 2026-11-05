-- phpMyAdmin SQL Dump
-- version 5.2.2deb1+deb13u1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Nov 05, 2026 at 09:22 AM
-- Server version: 11.8.6-MariaDB-0+deb13u1 from Debian
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ib_store`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` bigint(20) NOT NULL,
  `registered` datetime NOT NULL DEFAULT current_timestamp(),
  `username` varchar(32) NOT NULL,
  `email` varchar(50) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `verified` tinyint(1) NOT NULL DEFAULT 0,
  `notes` varchar(256) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `account_billing`
--

CREATE TABLE `account_billing` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `company` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address_1` varchar(255) NOT NULL,
  `address_2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(20) NOT NULL,
  `country` varchar(100) NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 1,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `account_shipping`
--

CREATE TABLE `account_shipping` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `company` varchar(150) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address_1` varchar(255) NOT NULL,
  `address_2` varchar(255) DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `province` varchar(100) NOT NULL,
  `postal_code` varchar(25) NOT NULL,
  `country` varchar(100) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(36) NOT NULL,
  `route` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `route`, `description`, `active`, `created`) VALUES
(1, 'Hardware', 'hardware', 'used hardware', 1, '2026-06-18 23:04:14'),
(2, 'Retro  Hardware', 'retro', 'older hardware', 1, '2026-06-18 23:10:29'),
(3, 'Software', 'software', 'random software ?', 1, '2026-06-18 23:11:26');

-- --------------------------------------------------------

--
-- Table structure for table `login_sessions`
--

CREATE TABLE `login_sessions` (
  `ip_address` varchar(40) DEFAULT NULL,
  `user_id` bigint(20) NOT NULL,
  `session_id` char(32) NOT NULL,
  `session_hash` char(32) NOT NULL,
  `updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL COMMENT 'public id used in urls',
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `currency` char(3) NOT NULL DEFAULT 'USD',
  `subtotal` decimal(10,2) NOT NULL DEFAULT 0.00,
  `shipping` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL DEFAULT 0.00,
  `has_physical` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = something needs shipping',
  `bill_first_name` varchar(100) NOT NULL,
  `bill_last_name` varchar(100) NOT NULL,
  `bill_company` varchar(255) DEFAULT NULL,
  `bill_phone` varchar(50) DEFAULT NULL,
  `bill_address_1` varchar(255) NOT NULL,
  `bill_address_2` varchar(255) DEFAULT NULL,
  `bill_city` varchar(100) NOT NULL,
  `bill_province` varchar(100) NOT NULL,
  `bill_postal_code` varchar(20) NOT NULL,
  `bill_country` varchar(100) NOT NULL,
  `ship_first_name` varchar(100) DEFAULT NULL,
  `ship_last_name` varchar(100) DEFAULT NULL,
  `ship_company` varchar(255) DEFAULT NULL,
  `ship_phone` varchar(50) DEFAULT NULL,
  `ship_address_1` varchar(255) DEFAULT NULL,
  `ship_address_2` varchar(255) DEFAULT NULL,
  `ship_city` varchar(100) DEFAULT NULL,
  `ship_province` varchar(100) DEFAULT NULL,
  `ship_postal_code` varchar(20) DEFAULT NULL,
  `ship_country` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL COMMENT 'customer order notes',
  `admin_notes` text DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL COMMENT 'filled in by the payment step',
  `payment_ref` varchar(255) DEFAULT NULL COMMENT 'gateway transaction id',
  `paid_at` datetime DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp(),
  `updated` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `order_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'null if the product was deleted later',
  `sku` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `qty` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `digital` tinyint(1) NOT NULL DEFAULT 0,
  `download_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `account_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires` datetime NOT NULL,
  `created` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `category_id` int(10) UNSIGNED NOT NULL,
  `sku` varchar(100) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL COMMENT 'for URLs',
  `con` tinyint(2) NOT NULL DEFAULT 0 COMMENT '0 New, 1 Open, 2 Used ',
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `digital` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = no shipping, download_url is handed out after payment',
  `download_url` varchar(255) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `newest` tinyint(1) NOT NULL DEFAULT 0,
  `featured` tinyint(1) NOT NULL DEFAULT 0,
  `badge` tinyint(2) NOT NULL DEFAULT 0 COMMENT '1 New, 2 Sale, 3 Disc',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `category_id`, `sku`, `name`, `slug`, `con`, `description`, `price`, `stock`, `digital`, `download_url`, `active`, `newest`, `featured`, `badge`, `created_at`, `updated_at`) VALUES
(1, 1, '200-222', 'Gaming Mouse', 'gaming-mouse', 2, NULL, 20.00, 98, 0, NULL, 1, 0, 1, 1, '2026-06-28 10:09:21', '2026-11-05 05:15:20'),
(2, 1, '222-333', 'Smart Watch', 'smart-watch', 1, NULL, 22.00, 1000, 0, NULL, 1, 0, 1, 0, '2026-06-29 04:27:41', '2026-06-29 05:44:32'),
(3, 1, '333-444', 'Wireless Headphones', 'wireless-headphones', 0, NULL, 0.00, 999, 0, NULL, 1, 0, 1, 0, '2026-06-29 04:41:02', '2026-07-02 05:11:14'),
(4, 1, '555-666', 'RGB Keyboard', 'rgn-keyboard', 0, NULL, 32.00, 999, 0, NULL, 1, 1, 0, 0, '2026-06-29 05:37:21', '2026-06-29 05:37:21'),
(5, 1, '666-777', 'USB-C Hub', 'usb-hub', 1, NULL, 0.00, 999, 0, NULL, 1, 1, 0, 2, '2026-06-30 05:46:12', '2026-06-30 05:46:12'),
(6, 1, '777-888', 'Desk Lamp', 'desk-lamp', 1, NULL, 44.00, 9999, 0, NULL, 1, 1, 0, 0, '2026-06-30 05:47:11', '2026-06-30 05:47:11');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `image` varchar(255) NOT NULL,
  `sort_order` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image`, `sort_order`) VALUES
(1, 1, 'assets/images/items/mouse.webp', 1),
(2, 2, 'assets/images/items/watch.webp', 1),
(3, 4, 'assets/images/items/keyboard.webp', 1),
(4, 3, 'assets/images/items/wireless-headphones-1.png', 1),
(5, 6, 'assets/images/items/desk-lamp-1.png', 1),
(6, 5, 'assets/images/items/usb-hub-1.png', 1);

-- --------------------------------------------------------

--
-- Table structure for table `store_info`
--

CREATE TABLE `store_info` (
  `id` int(10) UNSIGNED NOT NULL,
  `store_name` varchar(150) NOT NULL,
  `company_name` varchar(150) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state_province` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `currency` char(3) DEFAULT 'USD',
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'percent, 5.00 = 5%',
  `shipping_flat` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'per order, physical items only',
  `free_shipping_over` decimal(10,2) DEFAULT NULL COMMENT 'subtotal that waives shipping, null = never',
  `timezone` varchar(100) DEFAULT 'UTC',
  `logo` varchar(255) DEFAULT NULL,
  `favicon` varchar(255) DEFAULT NULL COMMENT 'tab icon, empty = the default bored face',
  `hero_image` varchar(255) DEFAULT NULL COMMENT 'empty = no hero on the home page',
  `hero_heading` varchar(150) DEFAULT NULL,
  `hero_text` varchar(255) DEFAULT NULL,
  `hero_button` varchar(50) DEFAULT NULL COMMENT 'button label, empty = no button',
  `hero_link` varchar(255) DEFAULT NULL COMMENT 'where the button goes, /items for the shop',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `store_info`
--

INSERT INTO `store_info` (`id`, `store_name`, `company_name`, `email`, `phone`, `address_line1`, `address_line2`, `city`, `state_province`, `postal_code`, `country`, `website`, `currency`, `tax_rate`, `shipping_flat`, `free_shipping_over`, `timezone`, `logo`, `favicon`, `hero_image`, `hero_heading`, `hero_text`, `hero_button`, `hero_link`, `created_at`, `updated_at`) VALUES
(1, 'iB Store', 'My Company', 'store@example.com', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'https://example.com/', 'CAD', 0.00, 0.00, NULL, 'UTC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-06-22 23:50:34', '2026-11-05 08:37:17');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `account_billing`
--
ALTER TABLE `account_billing`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_account_id` (`account_id`);

--
-- Indexes for table `account_shipping`
--
ALTER TABLE `account_shipping`
  ADD PRIMARY KEY (`id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`route`);

--
-- Indexes for table `login_sessions`
--
ALTER TABLE `login_sessions`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `session_hash` (`session_hash`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uuid` (`uuid`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`account_id`),
  ADD KEY `token_hash` (`token_hash`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `store_info`
--
ALTER TABLE `store_info`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `account_billing`
--
ALTER TABLE `account_billing`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `account_shipping`
--
ALTER TABLE `account_shipping`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `store_info`
--
ALTER TABLE `store_info`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`);

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
