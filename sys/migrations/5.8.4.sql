-- Migration file for version 5.8.4
-- Created on 2026-10-27 20:33:42


UPDATE `system_config` SET `value` = '5.8.4' WHERE `system_config`.`name` = 'sc_version';


-- Where a lab's request pulls have got to, per test type: the last pull and the
-- page markers of a window still being paged. The receiver read the last pull out of
-- track_api_requests, a log of every call, and one lab spent 102 seconds per test type
-- on that read before its pull started. The markers were kept in var/cache, which a
-- cache purge empties. Used on labs only.
CREATE TABLE IF NOT EXISTS `request_pull_state` (
  `state_key` varchar(255) NOT NULL,
  `state_value` varchar(64) NOT NULL DEFAULT '',
  `updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`state_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The last pull of one type and test type, for a lab updated before the table above
-- was filled, and for the API activity pages.
ALTER TABLE `track_api_requests` ADD INDEX `idx_track_api_type_test_requested` (`request_type`, `test_type`, `requested_on`);
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
-- END OF VERSION --
