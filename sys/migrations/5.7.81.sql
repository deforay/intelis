-- Migration file for version 5.7.81
-- Created on 2026-09-24

-- The infant's phone number was an INT: a leading zero was dropped and a number
-- written with a + or spaces was stored as 0 or a fragment of itself. Text, like
-- every other phone column in form_eid. The digits already stored carry over as they are.
ALTER TABLE `form_eid` MODIFY `infant_phone` VARCHAR(32) NULL DEFAULT NULL;

-- South Sudan's VL and EID forms hold a patient's whole name in one field, and the
-- app posted names in parts (childName and childSurName; first, middle and last).
-- A part kept in its own column is one the form never shows, and is lost on the next
-- save from the form: join each name into the column the form reads. The API joins
-- them as they arrive from 5.7.81 on. Rows whose names are encrypted are left alone,
-- and last_modified_datetime is not touched, so no row is re-sent or re-sorted.
UPDATE `form_eid`
SET `child_name` = NULLIF(CONCAT_WS(' ', NULLIF(TRIM(`child_name`), ''), NULLIF(TRIM(`child_surname`), '')), ''),
    `child_surname` = NULL
WHERE NULLIF(TRIM(`child_surname`), '') IS NOT NULL
  AND IFNULL(`is_encrypted`, 'no') <> 'yes'
  -- A joined name longer than the column is left in its parts, not cut short. The
  -- length differs between installs, so it is read from the schema.
  AND CHAR_LENGTH(CONCAT_WS(' ', NULLIF(TRIM(`child_name`), ''), NULLIF(TRIM(`child_surname`), ''))) <= IFNULL((
        SELECT `CHARACTER_MAXIMUM_LENGTH` FROM `information_schema`.`COLUMNS`
        WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'form_eid' AND `COLUMN_NAME` = 'child_name'), 0)
  AND (`vlsm_country_id` = 1
       OR (`vlsm_country_id` IS NULL
           AND EXISTS (SELECT 1 FROM `global_config` WHERE `name` = 'vl_form' AND `value` = '1')));

UPDATE `form_vl`
SET `patient_first_name` = NULLIF(CONCAT_WS(' ',
        NULLIF(TRIM(`patient_first_name`), ''),
        NULLIF(TRIM(`patient_middle_name`), ''),
        NULLIF(TRIM(`patient_last_name`), '')), ''),
    `patient_middle_name` = NULL,
    `patient_last_name` = NULL
WHERE (NULLIF(TRIM(`patient_middle_name`), '') IS NOT NULL OR NULLIF(TRIM(`patient_last_name`), '') IS NOT NULL)
  AND IFNULL(`is_encrypted`, 'no') <> 'yes'
  -- A joined name longer than the column is left in its parts, not cut short. The
  -- length differs between installs, so it is read from the schema.
  AND CHAR_LENGTH(CONCAT_WS(' ', NULLIF(TRIM(`patient_first_name`), ''), NULLIF(TRIM(`patient_middle_name`), ''),
        NULLIF(TRIM(`patient_last_name`), ''))) <= IFNULL((SELECT `CHARACTER_MAXIMUM_LENGTH`
          FROM `information_schema`.`COLUMNS`
          WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = 'form_vl' AND `COLUMN_NAME` = 'patient_first_name'), 0)
  AND (`vlsm_country_id` = 1
       OR (`vlsm_country_id` IS NULL
           AND EXISTS (SELECT 1 FROM `global_config` WHERE `name` = 'vl_form' AND `value` = '1')));

UPDATE `system_config` SET `value` = '5.7.81' WHERE `system_config`.`name` = 'sc_version';
