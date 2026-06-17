-- Migration file for version 5.5.15
--
-- Shrink facility_details.facility_code from varchar(255) to varchar(32).
--
-- Facility codes are short, letters-only identifiers (and on STS each testing lab's
-- code becomes the sample-code postfix, e.g. "RVL0626-NMC19244"), so a 255-char
-- column is wasteful. 32 is comfortably above any real code while keeping the field
-- tidy. The UNIQUE index on facility_code is preserved by MODIFY COLUMN.
--
-- Defensive pre-trim: clamp any stray over-length value to 32 chars before the
-- ALTER so the column change can't fail under strict SQL mode. In practice codes
-- are a handful of characters, so this touches nothing.
--
-- Idempotent: the UPDATE is a no-op once values fit, and re-running MODIFY on an
-- already varchar(32) column is harmless.

UPDATE `facility_details`
   SET `facility_code` = LEFT(`facility_code`, 32)
 WHERE `facility_code` IS NOT NULL
   AND CHAR_LENGTH(`facility_code`) > 32;

ALTER TABLE `facility_details` MODIFY `facility_code` varchar(32) DEFAULT NULL;

UPDATE `system_config` SET `value` = '5.5.15' WHERE `system_config`.`name` = 'sc_version';

-- END OF VERSION --
