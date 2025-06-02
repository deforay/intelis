
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


CREATE DATABASE IF NOT EXISTS interfacing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE interfacing;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `raw_data`
--

CREATE TABLE IF NOT EXISTS `raw_data` (
  `id` int(11) NOT NULL,
  `data` mediumtext NOT NULL,
  `machine` varchar(500) NOT NULL,
  `added_on` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_unicode_ci;

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



ALTER TABLE `orders` ADD `instrument_id` VARCHAR(128) NULL DEFAULT NULL AFTER `id`;

CREATE TABLE IF NOT EXISTS versions (id INT AUTO_INCREMENT PRIMARY KEY, version INT NOT NULL)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4  COLLATE utf8mb4_unicode_ci;

-- Add instrument_id column to raw_data table
ALTER TABLE `raw_data` ADD COLUMN `instrument_id` VARCHAR(128) NULL AFTER `machine`;

-- Update existing records to set instrument_id equal to machine
UPDATE `raw_data` SET `instrument_id` = `machine` WHERE `instrument_id` IS NULL;

-- Add an index for better query performance
CREATE INDEX `idx_raw_data_instrument_id` ON `raw_data` (`instrument_id`);
