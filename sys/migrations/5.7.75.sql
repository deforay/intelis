-- Migration file for version 5.7.75
-- Created on 2026-09-22 10:01:23


UPDATE `system_config` SET `value` = '5.7.75' WHERE `system_config`.`name` = 'sc_version';


-- Lab receipts for request sync.
--
-- A lab that asks for receipts tells the STS which requests it saved and which it
-- could not. Saved ones are confirmed synced; failed ones are recorded here with
-- the lab's reason and sent again on later pulls until they save, for a limited
-- time, then shown as needing attention. Used on the STS only.
CREATE TABLE IF NOT EXISTS `request_sync_failures` (
  `failure_id` bigint NOT NULL AUTO_INCREMENT,
  `lab_id` int NOT NULL,
  `test_type` varchar(32) NOT NULL,
  `unique_id` varchar(500) NOT NULL,
  `reason` varchar(1000) DEFAULT NULL,
  `attempts` int unsigned NOT NULL DEFAULT 1,
  `first_failed_datetime` datetime NOT NULL,
  `last_failed_datetime` datetime NOT NULL,
  PRIMARY KEY (`failure_id`),
  UNIQUE KEY `uniq_rsf_lab_test_unique_id` (`lab_id`, `test_type`, `unique_id`),
  KEY `idx_rsf_last_failed` (`last_failed_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Receipts a lab could not deliver yet. The pull does not wait on its receipt, so
-- one that fails to send is kept here and sent on the next run. Used on labs only.
CREATE TABLE IF NOT EXISTS `request_receipt_outbox` (
  `receipt_id` bigint NOT NULL AUTO_INCREMENT,
  `test_type` varchar(32) NOT NULL,
  `receipt` mediumtext NOT NULL,
  `created_datetime` datetime NOT NULL,
  `attempts` int unsigned NOT NULL DEFAULT 0,
  `last_attempt_datetime` datetime DEFAULT NULL,
  `last_error` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`receipt_id`),
  KEY `idx_rro_created` (`created_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
