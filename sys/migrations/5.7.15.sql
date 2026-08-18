-- Migration file for version 5.7.15
-- Created on 2026-08-18 16:13:45


UPDATE `system_config` SET `value` = '5.7.15' WHERE `system_config`.`name` = 'sc_version';


-- Records whose own columns contradict each other, found by bin/flag-data-issues.php
-- and read by the "Needs attention" card on the request lists.
--
-- One row per (record, issue) rather than a count, for two reasons: the card can
-- show the offending samples rather than only a number, and the scan can be
-- incremental. It re-examines only rows touched since its last run, so the
-- nightly cost is proportional to the day's edits and not to the table, which
-- reaches millions of rows.
--
-- lab_id is denormalised so a cloud-LIS operator scoped to one lab can be shown
-- their own figures without joining back to the form table; 0 means no lab
-- assigned, a NOT NULL default so the unique key can rely on it.
CREATE TABLE IF NOT EXISTS `s_data_issues` (
    `issue_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `test_type` VARCHAR(64) NOT NULL,
    `record_id` BIGINT UNSIGNED NOT NULL,
    `issue_key` VARCHAR(64) NOT NULL,
    `lab_id` INT NOT NULL DEFAULT 0,
    `sample_code` VARCHAR(255) DEFAULT NULL,
    `flagged_on` DATETIME DEFAULT NULL,
    PRIMARY KEY (`issue_id`),
    UNIQUE KEY `uniq_record_issue` (`test_type`, `record_id`, `issue_key`),
    KEY `idx_lookup` (`test_type`, `issue_key`, `lab_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- How far each test type has been scanned. The watermark is the point the next
-- run starts from, so the work is bounded by what changed rather than by how
-- much data exists.
--
-- full_scan_cursor is where the next chunk starts. Work is done in primary
-- key ranges of a fixed size and bounded per run, so a first scan of a table
-- with millions of rows spreads over several nights instead of holding the
-- database for one long pass.
--
-- last_full_scan_datetime is kept separately because the incremental pass can
-- only see rows whose last_modified_datetime moved. A correction applied
-- straight in SQL, or by anything that does not touch that column, would leave
-- a stale flag behind, so a full pass is forced periodically to catch those.
CREATE TABLE IF NOT EXISTS `s_data_issues_scan` (
    `test_type` VARCHAR(64) NOT NULL,
    `last_checked_datetime` DATETIME DEFAULT NULL,
    `last_full_scan_datetime` DATETIME DEFAULT NULL,
    `full_scan_cursor` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`test_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
