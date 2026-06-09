-- Migration file for version 5.5.9
--
-- Stable per-test UUID for cross-instance Custom Test import/update.
--
-- r_test_types carried only the auto-increment test_type_id, which differs
-- between instances, so an exported Custom Test could never be recognised as
-- "the same test" elsewhere -- every import created a duplicate. Add a portable
-- test_type_uuid that travels inside the export; import-test-type.php matches on
-- it to offer an in-place update (falling back to a brand-new import when the
-- UUID is unknown locally). Existing rows are backfilled with MySQL UUID(); new
-- tests get a UUID stamped at creation time by addTestTypeHelper.php.

ALTER TABLE `r_test_types`
  ADD COLUMN `test_type_uuid` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL AFTER `test_type_id`;

UPDATE `r_test_types`
  SET `test_type_uuid` = UUID()
  WHERE `test_type_uuid` IS NULL OR TRIM(`test_type_uuid`) = '';

ALTER TABLE `r_test_types`
  ADD UNIQUE INDEX `idx_r_test_types_uuid` (`test_type_uuid`);

UPDATE `system_config` SET `value` = '5.5.9' WHERE `system_config`.`name` = 'sc_version';
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
