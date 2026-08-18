-- Migration file for version 5.7.18
-- Created on 2026-08-18 17:32:12


UPDATE `system_config` SET `value` = '5.7.18' WHERE `system_config`.`name` = 'sc_version';


-- The "marked lost or expired, but carrying a result" check no longer covers
-- expired samples. Expiry is a deliberate, time-based decision the nightly task
-- makes about a sample nobody worked on -- an expired sample is simply expired,
-- and 823 of them on one instance is a backlog, not a contradiction to work
-- through. Lost or missing is a different claim: someone recorded that the
-- sample never arrived, while a result for it exists.
--
-- The issue key changes with the meaning, so the old flags are cleared and the
-- watermark with them, making the next nightly run a full rescan. The scan is
-- idempotent; the cost is one full pass.
DELETE FROM `s_data_issues`;
DELETE FROM `s_data_issues_scan`;
