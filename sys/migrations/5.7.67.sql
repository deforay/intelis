-- Migration file for version 5.7.67
-- Created on 2026-09-11 18:02:27
--
-- Page usage kept one row per user, page and day, so the session hash on a row
-- named only the first login of that day, and a later login's opens and time
-- were added to it. The row is now per session as well, which is what the Page
-- Usage report and its link to the Activity Log need.
--
-- The wider unique key is added before the old one is dropped, so no insert in
-- between can create a duplicate. Every statement is safe to re-run.

UPDATE `user_page_usage` SET `session_hash` = '' WHERE `session_hash` IS NULL;
ALTER TABLE `user_page_usage` MODIFY `session_hash` varchar(20) NOT NULL DEFAULT '';
ALTER TABLE `user_page_usage` ADD UNIQUE KEY `uniq_user_page_day_session` (`user_id`, `page_url`, `usage_date`, `session_hash`);
ALTER TABLE `user_page_usage` DROP INDEX `uniq_user_page_day`;

UPDATE `system_config` SET `value` = '5.7.67' WHERE `system_config`.`name` = 'sc_version';
