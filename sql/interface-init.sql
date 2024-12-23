

-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Dec 20, 2022 at 04:55 PM
-- Server version: 5.7.34
-- PHP Version: 7.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


CREATE DATABASE IF NOT EXISTS interfacing;

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `interfacing`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_log`
--

CREATE TABLE IF NOT EXISTS `app_log` (
  `id` int(11) NOT NULL,
  `log` text NOT NULL,
  `added_on` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL,
  `order_id` varchar(255) NOT NULL,
  `test_id` varchar(255) DEFAULT NULL,
  `test_type` varchar(255) NOT NULL,
  `created_date` date DEFAULT NULL,
  `test_unit` varchar(255) DEFAULT NULL,
  `results` varchar(255) DEFAULT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `analysed_date_time` datetime DEFAULT NULL,
  `specimen_date_time` datetime DEFAULT NULL,
  `authorised_date_time` datetime DEFAULT NULL,
  `result_accepted_date_time` datetime DEFAULT NULL,
  `machine_used` varchar(40) DEFAULT NULL,
  `test_location` varchar(40) DEFAULT NULL,
  `created_at` int(11) NOT NULL DEFAULT '0',
  `result_status` int(11) NOT NULL DEFAULT '0',
  `lims_sync_status` int(11) DEFAULT '0',
  `lims_sync_date_time` datetime DEFAULT NULL,
  `repeated` int(11) DEFAULT '0',
  `test_description` varchar(40) DEFAULT NULL,
  `is_printed` int(11) DEFAULT NULL,
  `printed_at` int(11) DEFAULT NULL,
  `raw_text` mediumtext,
  `added_on` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `raw_data`
--

CREATE TABLE IF NOT EXISTS `raw_data` (
  `id` int(11) NOT NULL,
  `data` mediumtext NOT NULL,
  `machine` varchar(500) NOT NULL,
  `added_on` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_log`
--
ALTER TABLE `app_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `result_status` (`result_status`) USING BTREE;

--
-- Indexes for table `raw_data`
--
ALTER TABLE `raw_data`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `app_log`
--
ALTER TABLE `app_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `raw_data`
--
ALTER TABLE `raw_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
