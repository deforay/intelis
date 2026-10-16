-- Migration file for version 5.7.89
-- Created on 2026-10-16 09:19:37

-- The API sync history list filters by transaction ID. Without an index that is a
-- full scan of track_api_requests, which on an STS holds millions of rows.
ALTER TABLE `track_api_requests` ADD INDEX `idx_track_api_transaction_id` (`transaction_id`);

UPDATE `system_config` SET `value` = '5.7.89' WHERE `system_config`.`name` = 'sc_version';

