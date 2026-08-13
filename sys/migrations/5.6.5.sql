-- Migration file for version 5.6.5
--
-- Corrects two column comments that no longer describe what the columns hold.
--
-- `installation_id` on both instrument telemetry tables was documented as "Set when the
-- event arrived over the API", and until now that was accurate: the API was told which
-- installation it was talking to by the credential, and the importer and the relay were
-- told nothing and stored NULL. Everything those two paths delivered was unattributed,
-- so a lab running more than one Interface Tool could not tell its installations apart
-- for any event that came in by importer.
--
-- InstrumentInstallationResolver now works the installation out on those paths too, so
-- the comment is wrong in the direction that matters: it reads as a statement of intent
-- and would talk the next reader out of a value that is supposed to be there.
--
-- Worth spelling out what the column is, because two identifiers are involved and they
-- are easy to confuse. The Interface Tool stamps every event with a source identifier of
-- its own making -- `interface-<uuid>`, up to 128 characters, held in the tool's own
-- database and in interface_installations.source_installation_id. This column is not
-- that. It is the 36-character identity this server assigns at activation, and the two
-- are related only by a lookup through interface_installations. Putting the tool's
-- identifier here would overflow CHAR(36) and match nothing it was later joined against,
-- which is the mistake the comment should now steer a reader away from.
--
-- `received_via` was documented as "api or importer" and has accepted a third value,
-- 'relay', since 5.5.24 -- a row forwarded by a LIS to STS, where it was first stored by
-- one of the other two. The comment simply predates it.
--
-- Comments only. No data is read, written or moved, and both columns keep the exact type,
-- nullability and default they already had.

ALTER TABLE `instrument_activity_log`
  MODIFY COLUMN `installation_id` CHAR(36) NULL DEFAULT NULL
    COMMENT 'Identity this server assigned at activation, not the tool own source identifier; null when the installation is unknown here',
  MODIFY COLUMN `received_via` VARCHAR(16) NOT NULL DEFAULT 'api'
    COMMENT 'api, importer or relay';

ALTER TABLE `instrument_usage_statistics_daily`
  MODIFY COLUMN `installation_id` CHAR(36) NULL DEFAULT NULL
    COMMENT 'Identity this server assigned at activation, not the tool own source identifier; null when the installation is unknown here',
  MODIFY COLUMN `received_via` VARCHAR(16) NOT NULL DEFAULT 'api'
    COMMENT 'api, importer or relay';

UPDATE `system_config` SET `value` = '5.6.5' WHERE `system_config`.`name` = 'sc_version';

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
