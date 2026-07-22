-- Migration file for version 5.5.28
--
-- Prepares instrument activity and daily usage statistics to be relayed from a LIS
-- to STS, alongside the lab metadata that already syncs there.
--
-- The activity unique key becomes per-lab. It was globally unique, which is safe on
-- a LIS holding one lab but not on STS, where the table holds the whole fleet: two
-- installs generating the same identifier -- a cloned VM image is the realistic way
-- that happens -- would have one lab's events silently dropped, indistinguishable
-- from a retry. Daily usage statistics are already keyed per lab; this matches them.
--
-- The current key is stricter than the new one, so no duplicate (lab_id, event_uid)
-- can exist and the swap cannot fail on existing data.

-- One clause per statement: the migration runner rewrites single-clause ALTERs into
-- idempotent equivalents, and a combined statement would fall through to raw exec
-- and fail when the migration is replayed.
ALTER TABLE `instrument_activity_log` DROP INDEX `uniq_instrument_activity_event_uid`;

ALTER TABLE `instrument_activity_log`
  ADD UNIQUE KEY `uniq_instrument_activity_lab_event` (`lab_id`, `event_uid`);

-- Separate watermarks from last_lab_metadata_sync. The metadata tables are small and
-- bounded; activity is append-only and can spike when a connection flaps, so these
-- advance on their own and each run is capped rather than sending everything at once.
ALTER TABLE `s_vlsm_instance`
  ADD COLUMN `last_instrument_activity_sync` DATETIME NULL DEFAULT NULL AFTER `last_interface_sync`;

ALTER TABLE `s_vlsm_instance`
  ADD COLUMN `last_instrument_usage_sync` DATETIME NULL DEFAULT NULL AFTER `last_instrument_activity_sync`;

UPDATE `system_config` SET `value` = '5.5.28' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
