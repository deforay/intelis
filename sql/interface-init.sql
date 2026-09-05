
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

-- Give app_log the structured columns it logs through (mysql-migrations/004.sql)
ALTER TABLE `app_log` ADD COLUMN `log_type` VARCHAR(20) NULL;
ALTER TABLE `app_log` ADD COLUMN `log_message` TEXT NULL;
ALTER TABLE `app_log` ADD COLUMN `instrument_id` VARCHAR(255) NULL;

-- Free-text notes against an order (mysql-migrations/005.sql)
ALTER TABLE `orders` ADD COLUMN `notes` TEXT NULL;

-- Identify the ingestion that produced an order, so a repeat of the same
-- delivery cannot enter twice (mysql-migrations/006.sql)
ALTER TABLE `orders` ADD COLUMN `ingestion_id` VARCHAR(36) NULL;
CREATE UNIQUE INDEX `idx_orders_ingestion_id` ON `orders` (`ingestion_id`);

-- Separate operational log lines from the rest (mysql-migrations/007.sql)
ALTER TABLE `app_log` ADD COLUMN `category` VARCHAR(20) NOT NULL DEFAULT 'operational';

-- Instrument activity, one row per event (mysql-migrations/008.sql), with the
-- originating installation and its index folded in from 009.sql
CREATE TABLE IF NOT EXISTS `telemetry_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id` CHAR(36) NOT NULL,
  `event_type` VARCHAR(64) NOT NULL,
  `event_category` VARCHAR(32) NOT NULL,
  `occurred_at` DATETIME NOT NULL,
  `lab_id` VARCHAR(128) DEFAULT NULL,
  `source_installation_id` VARCHAR(128) DEFAULT NULL,
  `instrument_id` VARCHAR(128) DEFAULT NULL,
  `machine_type` VARCHAR(128) DEFAULT NULL,
  `protocol` VARCHAR(32) DEFAULT NULL,
  `connection_mode` VARCHAR(32) DEFAULT NULL,
  `test_type` VARCHAR(128) DEFAULT NULL,
  `outcome` VARCHAR(32) NOT NULL DEFAULT 'success',
  `failure_code` VARCHAR(64) DEFAULT NULL,
  `event_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `app_version` VARCHAR(32) DEFAULT NULL,
  `remote_uploaded_at` DATETIME DEFAULT NULL,
  `remote_batch_id` CHAR(36) DEFAULT NULL,
  `added_on` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_telemetry_events_event_id` (`event_id`),
  KEY `idx_telemetry_events_occurred_at` (`occurred_at`),
  KEY `idx_telemetry_events_type_time` (`event_type`, `occurred_at`),
  KEY `idx_telemetry_events_instrument_time` (`instrument_id`, `occurred_at`),
  KEY `idx_telemetry_events_source_time` (`source_installation_id`, `occurred_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Daily rollup of the above, one row per installation, instrument and test type
-- per day (mysql-migrations/009.sql)
CREATE TABLE IF NOT EXISTS `usage_statistics_daily` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `aggregate_id` CHAR(36) NOT NULL,
  `activity_date` DATE NOT NULL,
  `source_installation_id` VARCHAR(128) NOT NULL,
  `lab_id` VARCHAR(128) NOT NULL DEFAULT '',
  `instrument_id` VARCHAR(128) NOT NULL DEFAULT '',
  `machine_type` VARCHAR(128) NOT NULL DEFAULT '',
  `test_type` VARCHAR(128) NOT NULL DEFAULT '',
  `total_tests` INT UNSIGNED NOT NULL DEFAULT 0,
  `successful_tests` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed_tests` INT UNSIGNED NOT NULL DEFAULT 0,
  `first_test_at` DATETIME NOT NULL,
  `last_test_at` DATETIME NOT NULL,
  `revision` INT UNSIGNED NOT NULL DEFAULT 1,
  `remote_uploaded_revision` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_usage_statistics_daily_aggregate_id` (`aggregate_id`),
  KEY `idx_usage_statistics_daily_activity` (`activity_date`, `instrument_id`),
  KEY `idx_usage_statistics_daily_remote_pending` (`remote_uploaded_revision`, `revision`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
