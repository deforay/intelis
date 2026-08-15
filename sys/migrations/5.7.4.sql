-- Migration file for version 5.7.4
-- Created on 2026-08-15 14:53:33


-- Amit 15-Aug-2026 - Let save-request recognise records that arrive unchanged.
-- Seeded 'no', which is the behaviour the endpoint already had. 'shadow' measures how
-- many records a payload re-posts unchanged without acting on it; 'yes' skips their
-- writes. Run a lab in 'shadow' first: the pass is logged with the columns that kept
-- records out of the unchanged set, which is what says the comparison is tuned right
-- before any write is skipped on the strength of it.
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`)
VALUES ('Skip Unchanged API Updates (no/shadow/yes)', 'api_skip_unchanged_updates', 'no', 'general')
ON DUPLICATE KEY UPDATE `display_name` = VALUES(`display_name`);

UPDATE `system_config` SET `value` = '5.7.4' WHERE `system_config`.`name` = 'sc_version';

