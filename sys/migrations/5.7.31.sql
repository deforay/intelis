-- Migration file for version 5.7.31
-- Created on 2026-08-21

-- Smart Connect's v2 API replaces the derived STS key with a token the
-- installation enrolls for itself: it POSTs its instance UUID and the
-- deployment-wide enrollment key to /api/v2/enroll, and gets back a per-lab
-- token that it then sends as a Bearer credential on every upload.
--
-- That token has to survive restarts, so it lives beside the credential it
-- replaces rather than in a cache. It is issued once and shown once -- calling
-- enroll again mints a new one and kills the old -- so losing this column means
-- re-enrolling, not looking the value up somewhere else.
--
-- Kept separate from `sts_token`: an installation can talk to an STS and to a
-- Smart Connect deployment at the same time, and the two credentials are issued
-- by different servers and revoked independently.
--
-- 64 characters holds the 32-byte token the enroll endpoint returns, hex-encoded.
ALTER TABLE `s_vlsm_instance`
  ADD COLUMN `sc_api_token` VARCHAR(64) NULL DEFAULT NULL AFTER `sts_token`;


-- The other half of the enroll call: the key that proves this installation is
-- allowed to enroll at all. One key covers every laboratory in a Smart Connect
-- deployment, which is why it belongs in global_config with
-- remote_sync_needed = 'yes' -- it is set once centrally and carried down to the
-- whole fleet by the metadata sync, so a rotation does not mean touching each
-- installation by hand.
--
-- Ships empty. Until it is filled in, enrollment simply does not run.
--
-- Used once, on the POST to /api/v2/enroll, and never as a Bearer credential --
-- that is what the per-lab token above is for.
--
-- IGNORE, not ON DUPLICATE KEY UPDATE: `name` is the primary key, and migrations
-- replay -- on a fresh install the whole series runs from the 5.3.2 baseline. An
-- upsert here would write the empty string back over a key that had already been
-- set, silently un-enrolling the installation on its next upgrade. Seed the row
-- if it is missing, leave it alone if it is not.
INSERT IGNORE INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_datetime`, `status`)
VALUES ('Smart Connect Enrollment Key', 'smart_connect_enrollment_key', '', 'general', 'yes', CURRENT_TIMESTAMP, 'active');


-- The "Needs attention" card no longer asks about a rejected sample that carries
-- a result. Rejection is the lab's own decision and it stands, so the leftover
-- result is stale data rather than a ruling anyone owes -- and both write paths
-- that produced these are closed, so nothing new arrives. The flags already
-- raised would otherwise sit in the table until each row happens to be scanned
-- again, so they go now.
DELETE FROM `s_data_issues` WHERE `issue_key` = 'rejectedWithResult';

UPDATE `system_config` SET `value` = '5.7.31' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --