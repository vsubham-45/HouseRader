-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 23, 2026 at 09:48 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `houserader`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_actions`
--

CREATE TABLE `admin_actions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admin_user_id` int(10) UNSIGNED DEFAULT NULL,
  `action_type` varchar(80) NOT NULL,
  `target_table` varchar(80) DEFAULT NULL,
  `target_id` int(10) UNSIGNED DEFAULT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_actions`
--

INSERT INTO `admin_actions` (`id`, `admin_user_id`, `action_type`, `target_table`, `target_id`, `details`, `created_at`) VALUES
(1, NULL, 'reject', 'properties', 1, '{\"title\":\"Akash Ganga\",\"seller_id\":1,\"reason\":\"\"}', '2025-12-06 09:06:38'),
(2, NULL, 'approve', 'properties', 2, '{\"title\":\"Akash Ganga\",\"seller_id\":1}', '2025-12-06 09:10:06'),
(3, NULL, 'approve', 'properties', 3, '{\"property_id\":3}', '2025-12-15 21:13:13'),
(4, NULL, 'approve', 'properties', 3, '{\"property_id\":3}', '2025-12-16 03:46:50'),
(5, NULL, 'approve', 'properties', 4, '{\"property_id\":4}', '2025-12-23 03:46:21'),
(6, NULL, 'approve', 'properties', 5, '{\"property_id\":5}', '2026-01-27 06:56:30'),
(7, 1, 'approve', 'properties', 1, '{\"property_id\":1}', '2026-03-31 15:22:37'),
(8, 1, 'approve', 'properties', 18, '{\"property_id\":18}', '2026-04-06 04:08:20');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `property_id` bigint(20) UNSIGNED NOT NULL,
  `buyer_id` bigint(20) UNSIGNED NOT NULL,
  `seller_id` bigint(20) UNSIGNED NOT NULL,
  `last_message_at` datetime DEFAULT NULL,
  `unread_for_buyer` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `unread_for_seller` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `messages_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `conversation_id` bigint(20) UNSIGNED NOT NULL,
  `sender_type` enum('buyer','seller','system') NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `recipient_type` enum('buyer','seller','system') NOT NULL,
  `recipient_id` bigint(20) UNSIGNED NOT NULL,
  `body` text DEFAULT NULL,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `otps`
--

CREATE TABLE `otps` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `contact` varchar(255) NOT NULL,
  `channel` enum('phone','email') NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `attempts` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `payer_id` int(10) UNSIGNED NOT NULL,
  `payer_role` enum('user','seller','admin','system') NOT NULL,
  `provider` varchar(64) NOT NULL,
  `provider_txn_id` varchar(255) DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(8) DEFAULT 'INR',
  `status` enum('initiated','succeeded','failed','refunded') NOT NULL DEFAULT 'initiated',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `property_id`, `payer_id`, `payer_role`, `provider`, `provider_txn_id`, `amount`, `currency`, `status`, `created_at`, `expires_at`, `metadata`) VALUES
(37, 1, 1, 'seller', 'razorpay', 'pay_SXyv4PTtndkiun', 1499.00, 'INR', 'succeeded', '2026-03-31 21:29:31', '2026-03-31 23:59:31', '{\"tier\":1,\"days\":7,\"priority\":1}'),
(39, 7, 1, 'seller', 'razorpay', 'pay_SXywW6mAIzQTPG', 1499.00, 'INR', 'succeeded', '2026-03-31 21:31:01', '2026-04-01 00:01:01', '{\"tier\":1,\"days\":7,\"priority\":1}'),
(40, 2, 1, 'seller', 'razorpay', 'pay_SXyxACxy6iVQcf', 1499.00, 'INR', 'succeeded', '2026-03-31 21:31:38', '2026-04-01 00:01:38', '{\"tier\":1,\"days\":7,\"priority\":1}');

-- --------------------------------------------------------

--
-- Table structure for table `properties`
--

CREATE TABLE `properties` (
  `id` int(10) UNSIGNED NOT NULL,
  `seller_id` int(10) UNSIGNED NOT NULL,
  `owner_name` varchar(200) NOT NULL,
  `owner_phone` varchar(50) DEFAULT NULL,
  `owner_email` varchar(255) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `property_type` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `city` varchar(100) NOT NULL,
  `locality` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `config1` varchar(100) DEFAULT NULL,
  `config2` varchar(100) DEFAULT NULL,
  `config3` varchar(100) DEFAULT NULL,
  `config4` varchar(100) DEFAULT NULL,
  `config5` varchar(100) DEFAULT NULL,
  `config6` varchar(100) DEFAULT NULL,
  `price1` decimal(14,2) DEFAULT NULL,
  `price2` decimal(14,2) DEFAULT NULL,
  `price3` decimal(14,2) DEFAULT NULL,
  `price4` decimal(14,2) DEFAULT NULL,
  `price5` decimal(14,2) DEFAULT NULL,
  `price6` decimal(14,2) DEFAULT NULL,
  `builtup_area1` decimal(10,2) DEFAULT NULL,
  `builtup_area2` decimal(10,2) DEFAULT NULL,
  `builtup_area3` decimal(10,2) DEFAULT NULL,
  `builtup_area4` decimal(10,2) DEFAULT NULL,
  `builtup_area5` decimal(10,2) DEFAULT NULL,
  `builtup_area6` decimal(10,2) DEFAULT NULL,
  `carpet_area1` decimal(10,2) DEFAULT NULL,
  `carpet_area2` decimal(10,2) DEFAULT NULL,
  `carpet_area3` decimal(10,2) DEFAULT NULL,
  `carpet_area4` decimal(10,2) DEFAULT NULL,
  `carpet_area5` decimal(10,2) DEFAULT NULL,
  `carpet_area6` decimal(10,2) DEFAULT NULL,
  `rental_type` enum('rental','pg') DEFAULT 'rental',
  `rental_config` varchar(100) DEFAULT NULL,
  `rent` decimal(14,2) DEFAULT NULL,
  `rental_carpet_area` decimal(10,2) DEFAULT NULL,
  `furnishing` varchar(100) NOT NULL DEFAULT 'Not Furnished',
  `img1` varchar(255) DEFAULT NULL,
  `img2` varchar(255) DEFAULT NULL,
  `img3` varchar(255) DEFAULT NULL,
  `img4` varchar(255) DEFAULT NULL,
  `amenities` text DEFAULT NULL,
  `min_price` decimal(14,2) DEFAULT NULL,
  `min_rent` decimal(14,2) DEFAULT NULL,
  `status` enum('pending','live','rejected','inactive') DEFAULT 'pending',
  `is_featured` tinyint(1) NOT NULL DEFAULT 0,
  `featured_until` datetime DEFAULT NULL,
  `featured_priority` tinyint(3) NOT NULL DEFAULT 0,
  `featured_order` int(11) NOT NULL DEFAULT 0,
  `views_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `messages_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `properties`
--

INSERT INTO `properties` (`id`, `seller_id`, `owner_name`, `owner_phone`, `owner_email`, `title`, `property_type`, `description`, `city`, `locality`, `address`, `latitude`, `longitude`, `config1`, `config2`, `config3`, `config4`, `config5`, `config6`, `price1`, `price2`, `price3`, `price4`, `price5`, `price6`, `builtup_area1`, `builtup_area2`, `builtup_area3`, `builtup_area4`, `builtup_area5`, `builtup_area6`, `carpet_area1`, `carpet_area2`, `carpet_area3`, `carpet_area4`, `carpet_area5`, `carpet_area6`, `rental_type`, `rental_config`, `rent`, `rental_carpet_area`, `furnishing`, `img1`, `img2`, `img3`, `img4`, `amenities`, `min_price`, `min_rent`, `status`, `is_featured`, `featured_until`, `featured_priority`, `featured_order`, `views_count`, `messages_count`, `created_at`, `updated_at`) VALUES
(1, 1, 'Nirvaana Constructions LLP', '9820456789', 'sales@nirvaanaconstructions.com', 'Nirvaana Heights', 'upcoming', 'Nirvaana Heights by Nirvaana Constructions LLP is a thoughtfully designed residential project located in Vikhroli East, Mumbai. The development offers well-planned apartments with efficient layouts, modern interiors, and ample natural light, making it ideal for comfortable urban living. Situated close to the Eastern Express Highway, the project ensures excellent connectivity to Powai, Ghatkopar, Kanjurmarg, and major commercial hubs. Residents also benefit from proximity to schools, hospitals, supermarkets, and public transport. The project includes amenities such as 24x7 security, power backup, elevators, and dedicated parking, along with recreational spaces for families.', 'Mumbai', 'Vikhroli East', 'Near Eastern Express Highway, Vikhroli East, Mumbai - 400083', 19.1030000, 72.9285000, NULL, '1BHK', '2BHK', '3BHK', NULL, NULL, NULL, 8800000.00, 12800000.00, 18200000.00, NULL, NULL, NULL, 670.00, 980.00, 1380.00, NULL, NULL, NULL, 460.00, 720.00, 1020.00, NULL, NULL, 'rental', NULL, NULL, NULL, '', 'nirvaana1.jpg', 'nirvaana2.jpg', 'nirvaana3.jpg', 'nirvaana4.jpg', 'Lift, Parking, Security, CCTV, Power Backup, Children\'s Play Area, Garden', 8800000.00, NULL, 'live', 1, '2026-04-07 23:29:59', 1, 0, 0, 0, '2026-03-31 15:21:49', '2026-03-31 21:29:59'),
(2, 1, 'Dotom Constructions Pvt Ltd', '9819988776', 'sales@dotomconstructions.com', 'Dotom Desire', 'For Sale', 'Dotom Desire by Dotom Constructions Pvt Ltd is a premium residential development located in the heart of Dadar West, Mumbai. The project offers thoughtfully designed apartments with modern layouts and quality construction. Located near Shivaji Park, it provides excellent connectivity to Prabhadevi, Worli, Lower Parel, and Bandra. Residents enjoy proximity to railway stations, schools, hospitals, and entertainment hubs. The project includes amenities such as 24x7 security, elevators, power backup, and dedicated parking, making it ideal for urban families.', 'Mumbai', 'Dadar West', 'Near Shivaji Park, Dadar West, Mumbai - 400028', 19.0196000, 72.8410000, '2BHK', '3BHK', '4BHK', NULL, NULL, NULL, 22000000.00, 32000000.00, 48000000.00, NULL, NULL, NULL, 900.00, 1250.00, 1700.00, NULL, NULL, NULL, 650.00, 900.00, 1250.00, NULL, NULL, NULL, 'rental', NULL, NULL, NULL, 'Unfurnished', 'dotom1.jpg', 'dotom2.jpg', 'dotom3.jpg', 'dotom4.jpg', 'Lift, Parking, Security, CCTV, Power Backup, Intercom, Fire Safety, Terrace Garden', 22000000.00, NULL, 'live', 1, '2026-04-07 23:31:58', 1, 0, 0, 0, '2026-03-31 16:02:56', '2026-03-31 21:31:58'),
(7, 1, 'L&T Realty', '9898981234', 'sales@lntrealty.com', 'L&T Crescent Bay', 'For Sale', 'High-end residences in Parel with premium lifestyle amenities.', 'Mumbai', 'Parel', 'Parel, Mumbai', 18.9988000, 72.8377000, '2BHK', '3BHK', NULL, NULL, NULL, NULL, 25000000.00, 38000000.00, NULL, NULL, NULL, NULL, 1000.00, 1500.00, NULL, NULL, NULL, NULL, 750.00, 1100.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Semi-Furnished', 'Crescent1.jpg', 'Crescent2.jpg', 'Crescent3.jpg', 'Crescent4.jpg', 'Pool, Gym, Security', 25000000.00, NULL, 'live', 1, '2026-04-07 23:31:22', 1, 0, 0, 0, '2026-03-31 18:33:28', '2026-03-31 21:31:22'),
(10, 1, 'Rustomjee', '9823456789', 'sales@rustomjee.com', 'Rustomjee Urbania', 'For Sale', 'Integrated township in Thane with premium lifestyle.', 'Thane', 'Majiwada', 'Majiwada, Thane', 19.2155000, 72.9781000, '2BHK', '3BHK', NULL, NULL, NULL, NULL, 13000000.00, 18500000.00, NULL, NULL, NULL, NULL, 900.00, 1200.00, NULL, NULL, NULL, NULL, 650.00, 900.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Fully Furnished', 'Rustomjee1.jpg', 'Rustomjee2.jpg', 'Rustomjee3.jpg', 'Rustomjee4.jpg', 'Clubhouse, Gym, Pool', 13000000.00, NULL, 'live', 0, NULL, 0, 0, 0, 0, '2026-03-31 18:33:28', '2026-03-31 18:33:28'),
(13, 1, 'Tata Housing', '9877771111', 'sales@tatahousing.com', 'Tata Amantra', 'For Sale', 'Modern homes in Bhiwandi with connectivity to Thane.', 'Thane', 'Bhiwandi', 'Bhiwandi, Thane', 19.2963000, 73.0636000, '1BHK', '2BHK', NULL, NULL, NULL, NULL, 4500000.00, 7000000.00, NULL, NULL, NULL, NULL, 480.00, 750.00, NULL, NULL, NULL, NULL, 350.00, 550.00, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Unfurnished', 'Tata1.jpg', 'Tata2.jpg', 'Tata3.jpg', 'Tata4.jpg', 'Security, Parking', 4500000.00, NULL, 'live', 0, NULL, 0, 0, 0, 0, '2026-03-31 18:33:28', '2026-03-31 18:33:28'),
(14, 1, 'StayEasy PG', '9898989898', 'info@stayeasy.com', 'StayEasy PG', 'Rental', 'Affordable PG with modern facilities in Navi Mumbai.', 'Navi Mumbai', 'Nerul', 'Nerul, Navi Mumbai', 19.0330000, 73.0297000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'pg', NULL, 10000.00, 200.00, 'Fully Furnished', 'StayEasy1.jpg', 'StayEasy2.jpg', 'StayEasy3.jpg', 'StayEasy4.jpg', 'WiFi, Meals, Security', NULL, 10000.00, 'live', 0, NULL, 0, 0, 0, 0, '2026-03-31 18:33:28', '2026-03-31 18:33:28');

-- --------------------------------------------------------

--
-- Table structure for table `property_views`
--

CREATE TABLE `property_views` (
  `id` int(10) UNSIGNED NOT NULL,
  `property_id` int(10) UNSIGNED NOT NULL,
  `visitor_user_id` int(10) UNSIGNED DEFAULT NULL,
  `visitor_session` varchar(128) DEFAULT NULL,
  `visitor_ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `property_views`
--

INSERT INTO `property_views` (`id`, `property_id`, `visitor_user_id`, `visitor_session`, `visitor_ip`, `user_agent`, `created_at`) VALUES
(36, 1, NULL, 'kvcc802vfns12fdp2p9io607e5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 18:25:38'),
(39, 1, 2, '0h2me8hufpvukbnagr3gqmoiq9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 18:45:16'),
(41, 1, 3, '95oc52uj674upg7lt56ljaicoi', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 20:46:22'),
(42, 14, 2, '5vbdr8gkaho4cus14sqo1j5jif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-03-31 21:27:42'),
(45, 1, NULL, 'oi1k02cqnlj7o53uh20qcuo0f5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-01 14:22:18'),
(54, 2, NULL, 'i2ibb1m90hep9met9v4ha7i8p4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-06 03:30:38');

-- --------------------------------------------------------

--
-- Table structure for table `sellers`
--

CREATE TABLE `sellers` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `business_name` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `about` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `auth_provider` enum('local','google') NOT NULL DEFAULT 'local'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sellers`
--

INSERT INTO `sellers` (`id`, `name`, `email`, `phone`, `password_hash`, `business_name`, `avatar`, `about`, `created_at`, `updated_at`, `auth_provider`) VALUES
(1, 'Demo Account', 'demoacc@gmail.com', NULL, '$2y$10$ISbPGkRuh5Kf9zz3ncsc4Orfjn0A2I.MMmkY51A2L0SERLzYX0u86', NULL, '💀', NULL, '2026-03-31 14:51:19', '2026-04-06 04:14:06', 'local');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(200) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `auth_provider` enum('local','google') NOT NULL DEFAULT 'local'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `avatar`, `created_at`, `updated_at`, `auth_provider`) VALUES
(1, 'Demo Account', 'demoacc@gmail.com', '', '$2y$10$ISbPGkRuh5Kf9zz3ncsc4Orfjn0A2I.MMmkY51A2L0SERLzYX0u86', NULL, '2026-03-31 14:51:19', '2026-03-31 14:51:19', 'local');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_admin_target` (`target_table`,`target_id`),
  ADD KEY `idx_admin_user` (`admin_user_id`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_property_buyer_seller` (`property_id`,`buyer_id`,`seller_id`),
  ADD KEY `idx_property_id` (`property_id`),
  ADD KEY `idx_buyer_id` (`buyer_id`),
  ADD KEY `idx_seller_id` (`seller_id`),
  ADD KEY `idx_last_message_at` (`last_message_at`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation_id` (`conversation_id`),
  ADD KEY `idx_sender` (`sender_type`,`sender_id`),
  ADD KEY `idx_recipient` (`recipient_type`,`recipient_id`);

--
-- Indexes for table `otps`
--
ALTER TABLE `otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contact` (`contact`),
  ADD KEY `channel` (`channel`),
  ADD KEY `expires_at` (`expires_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pay_property` (`property_id`),
  ADD KEY `idx_payer` (`payer_role`,`payer_id`);

--
-- Indexes for table `properties`
--
ALTER TABLE `properties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_properties_title` (`title`),
  ADD KEY `idx_properties_city` (`city`),
  ADD KEY `idx_properties_min_price` (`min_price`),
  ADD KEY `idx_properties_min_rent` (`min_rent`),
  ADD KEY `idx_properties_status` (`status`),
  ADD KEY `idx_properties_featured` (`is_featured`,`featured_priority`,`featured_order`),
  ADD KEY `idx_properties_created_at` (`created_at`),
  ADD KEY `fk_properties_seller` (`seller_id`);

--
-- Indexes for table `property_views`
--
ALTER TABLE `property_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_views_property` (`property_id`),
  ADD KEY `idx_views_property_user` (`property_id`,`visitor_user_id`),
  ADD KEY `idx_views_property_session` (`property_id`,`visitor_session`),
  ADD KEY `idx_prop_sess` (`property_id`,`visitor_session`),
  ADD KEY `idx_prop_user` (`property_id`,`visitor_user_id`);

--
-- Indexes for table `sellers`
--
ALTER TABLE `sellers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_actions`
--
ALTER TABLE `admin_actions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `otps`
--
ALTER TABLE `otps`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `properties`
--
ALTER TABLE `properties`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `property_views`
--
ALTER TABLE `property_views`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `sellers`
--
ALTER TABLE `sellers`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_actions`
--
ALTER TABLE `admin_actions`
  ADD CONSTRAINT `fk_admin_user` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_conv` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_pay_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `properties`
--
ALTER TABLE `properties`
  ADD CONSTRAINT `fk_properties_seller` FOREIGN KEY (`seller_id`) REFERENCES `sellers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `property_views`
--
ALTER TABLE `property_views`
  ADD CONSTRAINT `fk_views_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
