-- Migration file for version 5.5.14
--
-- Make the backup-key feature flags propagate STS -> LIS.
--
-- The STS metadata sender only ships global_config rows where
-- remote_sync_needed = 'yes' (app/remote/remote/sts-metadata-sender.php). 5.5.11
-- created `backup_key_recovery_enabled` and `backup_encryption_enabled` with
-- remote_sync_needed = 'no', so flipping them on the STS would NOT reach the LIS
-- fleet. Flip them to 'yes' (and stamp updated_datetime so they are in a syncable
-- state) so Phase C enablement propagates on the next metadata sync. The values
-- themselves stay 'no' here -- this only wires up the channel, it does not enable
-- anything.
--
-- Idempotent: plain UPDATEs, safe to re-run.

UPDATE `global_config`
   SET `remote_sync_needed` = 'yes', `updated_datetime` = NOW()
 WHERE `name` IN ('backup_key_recovery_enabled', 'backup_encryption_enabled');

UPDATE `system_config` SET `value` = '5.5.14' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
