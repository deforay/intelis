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


UPDATE `system_config` SET `value` = '5.7.31' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
