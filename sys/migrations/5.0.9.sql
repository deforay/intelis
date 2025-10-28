-- Amit 11-Jul-2022 version 5.0.0
UPDATE `system_config` SET `value` = '5.0.0' WHERE `system_config`.`name` = 'sc_version';

-- Amit 13-Jul-2022 version 5.0.0
UPDATE  `global_config` set value = 999 WHERE `name` LIKE '%sample_expiry_after_days';
UPDATE  `global_config` set value = 999 WHERE `name` LIKE '%sample_lock_after_days';

-- Amit 18-Jul-2022 version 5.0.1
UPDATE `system_config` SET `value` = '5.0.1' WHERE `system_config`.`name` = 'sc_version';


-- Amit 19-Jul-2022
ALTER TABLE `facility_details` ADD `facility_attributes` JSON NULL DEFAULT NULL AFTER `facility_type`;
UPDATE `facility_details` SET `facility_attributes` = '{\"allow_results_file_upload\": \"yes\"}' WHERE `facility_type` = 2;

-- Thana 20-Jul-2022
ALTER TABLE `form_vl` ADD `community_sample` VARCHAR(256) NULL DEFAULT NULL AFTER `funding_source`;
ALTER TABLE `audit_form_vl` ADD `community_sample` VARCHAR(256) NULL DEFAULT NULL AFTER `funding_source`;


-- Amit 22-Jul-2022 version 5.0.2
UPDATE `system_config` SET `value` = '5.0.2' WHERE `system_config`.`name` = 'sc_version';

-- Amit 22-Jul-2022 version 5.0.3
UPDATE `system_config` SET `value` = '5.0.3' WHERE `system_config`.`name` = 'sc_version';

-- Amit 28-Jul-2022
ALTER TABLE `form_vl` ADD `form_attributes` JSON NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `form_eid` ADD `form_attributes` JSON NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `form_covid19` ADD `form_attributes` JSON NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `form_hepatitis` ADD `form_attributes` JSON NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `form_tb` ADD `form_attributes` JSON NULL DEFAULT NULL AFTER `result_sent_to_source`;

-- Amit 29-Jul-2022
ALTER TABLE `audit_form_vl` ADD `form_attributes` JSON NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `audit_form_eid` ADD `form_attributes` JSON NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `audit_form_covid19` ADD `form_attributes` JSON NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `audit_form_hepatitis` ADD `form_attributes` JSON NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `audit_form_tb` ADD `form_attributes` JSON NULL DEFAULT NULL AFTER `result_sent_to_source`;

-- Amit 29-Jul-2022 version 5.0.4
UPDATE `system_config` SET `value` = '5.0.4' WHERE `system_config`.`name` = 'sc_version';


-- Amit 09-Aug-2022
ALTER TABLE `form_hepatitis` ADD `app_sample_code` VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `external_sample_code`;
ALTER TABLE `audit_form_hepatitis` ADD `app_sample_code` VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `external_sample_code`;

-- Amit 11-Aug-2022 version 5.0.5
UPDATE `system_config` SET `value` = '5.0.5' WHERE `system_config`.`name` = 'sc_version';


-- Amit 16-Aug-2022
ALTER TABLE `track_api_requests` ADD INDEX(`requested_on`);

-- Amit 17-Aug-2022
ALTER TABLE `form_covid19` CHANGE `patient_gender` `patient_gender` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `audit_form_covid19` CHANGE `patient_gender` `patient_gender` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

-- Thana 17-Aug-2022
ALTER TABLE `track_api_requests` ADD `request_data` TEXT NULL DEFAULT NULL AFTER `api_params`, ADD `response_data` TEXT NULL DEFAULT NULL AFTER `request_data`;

-- Thana 18-Aug-2022
CREATE TABLE `r_vl_test_failure_reasons` (
 `failure_id` int NOT NULL AUTO_INCREMENT,
 `failure_reason` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
 `status` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
 `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`failure_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE `form_vl` ADD `reason_for_failure` INT(11) NULL DEFAULT NULL AFTER `failed_vl_result`, ADD `result_value_hiv_detection` VARCHAR(256) NULL DEFAULT NULL AFTER `vl_test_platform`;
ALTER TABLE `audit_form_vl` ADD `reason_for_failure` INT(11) NULL DEFAULT NULL AFTER `failed_vl_result`, ADD `result_value_hiv_detection` VARCHAR(256) NULL DEFAULT NULL AFTER `vl_test_platform`;

-- Thana 19-Aug-2022
ALTER TABLE `r_vl_test_failure_reasons` ADD `data_sync` INT NULL DEFAULT NULL AFTER `updated_datetime`;


-- Amit 20-Aug-2022 version 5.0.6
UPDATE `system_config` SET `value` = '5.0.6' WHERE `system_config`.`name` = 'sc_version';


-- Amit 22-Aug-2022
UPDATE `r_sample_status` SET `status_name` = 'Failed/Invalid' WHERE `r_sample_status`.`status_id` = 5;

-- Amit 31-Aug-2022 version 5.0.7
UPDATE `system_config` SET `value` = '5.0.7' WHERE `system_config`.`name` = 'sc_version';


-- Amit 2-Sep-2022 version 5.0.8
UPDATE `system_config` SET `value` = '5.0.8' WHERE `system_config`.`name` = 'sc_version';

UPDATE form_vl set is_sample_rejected = 'yes' where result_status = 4 and (is_sample_rejected is null or is_sample_rejected = 'no' or is_sample_rejected = '');

-- UPDATE form_eid set is_sample_rejected = 'yes' where result_status = 4 and (is_sample_rejected is null or is_sample_rejected = 'no' or is_sample_rejected = '');
-- UPDATE form_covid19 set is_sample_rejected = 'yes' where result_status = 4 and (is_sample_rejected is null or is_sample_rejected = 'no' or is_sample_rejected = '');

-- Amit 13-Sep-2022
UPDATE form_vl set result_sent_to_source = 'sent' where source_of_request = 'vlsts' AND result_status in (4,7) and (result_sent_to_source is null or result_sent_to_source = 'not-sent' or result_sent_to_source = '');
UPDATE form_eid set result_sent_to_source = 'sent' where source_of_request = 'vlsts' AND result_status in (4,7) and (result_sent_to_source is null or result_sent_to_source = 'not-sent' or result_sent_to_source = '');
UPDATE form_covid19 set result_sent_to_source = 'sent' where source_of_request = 'vlsts' AND result_status in (4,7) and (result_sent_to_source is null or result_sent_to_source = 'not-sent' or result_sent_to_source = '');
UPDATE form_hepatitis set result_sent_to_source = 'sent' where source_of_request = 'vlsts' AND result_status in (4,7) and (result_sent_to_source is null or result_sent_to_source = 'not-sent' or result_sent_to_source = '');
UPDATE form_tb set result_sent_to_source = 'sent' where source_of_request = 'vlsts' AND result_status in (4,7) and (result_sent_to_source is null or result_sent_to_source = 'not-sent' or result_sent_to_source = '');


-- Amit 14-Sep-2022
ALTER TABLE `track_api_requests` ADD `transaction_id` VARCHAR(256) NULL DEFAULT NULL AFTER `api_track_id`;

-- Amit 30-Sep-2022
INSERT INTO `r_sample_status` (`status_id`, `status_name`, `status`) VALUES (11, 'No Result', 'active');

-- Jeyabanu 04-Nov-2022
CREATE TABLE `r_vl_results` (
 `result_id` int NOT NULL AUTO_INCREMENT,
 `result` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
 `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'active',
 `available_for_instruments` json DEFAULT NULL,
 `interpretation` varchar(25) COLLATE utf8mb4_general_ci NOT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`result_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `r_tb_results` CHANGE `result_id` `result_id` INT(11) NOT NULL AUTO_INCREMENT;

INSERT INTO `privileges` ( `resource_id`, `privilege_name`, `display_name`) VALUES ( 'vl-reference', 'vl-results.php', 'Manage VL Results');
ALTER TABLE `form_vl` ADD `samples_referred_datetime` DATETIME NULL DEFAULT NULL AFTER `lab_id`, ADD `referring_lab_id` INT NULL DEFAULT NULL AFTER `samples_referred_datetime`;
ALTER TABLE `form_eid` ADD `samples_referred_datetime` DATETIME NULL DEFAULT NULL AFTER `lab_id`, ADD `referring_lab_id` INT NULL DEFAULT NULL AFTER `samples_referred_datetime`;
ALTER TABLE `form_covid19` ADD `samples_referred_datetime` DATETIME NULL DEFAULT NULL AFTER `lab_id`, ADD `referring_lab_id` INT NULL DEFAULT NULL AFTER `samples_referred_datetime`;
ALTER TABLE `form_hepatitis` ADD `samples_referred_datetime` DATETIME NULL DEFAULT NULL AFTER `lab_id`, ADD `referring_lab_id` INT NULL DEFAULT NULL AFTER `samples_referred_datetime`;
ALTER TABLE `audit_form_vl` ADD `samples_referred_datetime` DATETIME NULL DEFAULT NULL AFTER `lab_id`, ADD `referring_lab_id` INT NULL DEFAULT NULL AFTER `samples_referred_datetime`;
ALTER TABLE `audit_form_eid` ADD `samples_referred_datetime` DATETIME NULL DEFAULT NULL AFTER `lab_id`, ADD `referring_lab_id` INT NULL DEFAULT NULL AFTER `samples_referred_datetime`;
ALTER TABLE `audit_form_covid19` ADD `samples_referred_datetime` DATETIME NULL DEFAULT NULL AFTER `lab_id`, ADD `referring_lab_id` INT NULL DEFAULT NULL AFTER `samples_referred_datetime`;
ALTER TABLE `audit_form_hepatitis` ADD `samples_referred_datetime` DATETIME NULL DEFAULT NULL AFTER `lab_id`, ADD `referring_lab_id` INT NULL DEFAULT NULL AFTER `samples_referred_datetime`;


-- Amit 09-Nov-2022
UPDATE `form_vl` set result_status = 4 where vl_result_category like 'reject%';
UPDATE `form_vl` set result_status = 5 where vl_result_category like 'fail%';


-- Amit 10-Nov-2022 version 5.0.9
UPDATE `system_config` SET `value` = '5.0.9' WHERE `system_config`.`name` = 'sc_version';

-- Thana 11-Nov-2022
-- ALTER TABLE `import_config` ADD `date_time` TEXT NULL DEFAULT NULL AFTER `low_vl_result_text`;

-- Thana 14-Nov-2022
-- ALTER TABLE `import_config` DROP `date_time`;

RENAME TABLE `import_config` TO `instruments`;
RENAME TABLE `import_config_machines` TO `instrument_machines`;
RENAME TABLE `import_config_controls` TO `instrument_controls`;

ALTER TABLE `instrument_machines` ADD `date_format` TEXT NULL DEFAULT NULL AFTER `config_machine_name`, ADD `file_name` VARCHAR(256) NULL DEFAULT NULL AFTER `date_format`;

UPDATE `instrument_machines` INNER JOIN `instruments`
    ON `instrument_machines`.`config_id` = `instruments`.`config_id`
SET `instrument_machines`.`file_name` = `instruments`.`import_machine_file_name`;

-- Jeyabanu 17-Nov-2022
ALTER TABLE `form_covid19` CHANGE `reason_for_sample_rejection` `reason_for_sample_rejection` INT NULL DEFAULT NULL;

-- Thana 21-Nov-2022
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-reference', 'sync-status.php', 'Sync Status');

-- ilahir 29-Nov-2022
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Support Email', 'support_email', '', 'general', 'no', NULL, '', 'active');


CREATE TABLE `support` (
  `support_id` int NOT NULL,
  `feedback` varchar(500) DEFAULT NULL,
  `feedback_url` varchar(255) DEFAULT NULL,
  `upload_file_name` varchar(255) DEFAULT NULL,
  `attach_screenshot` varchar(100) DEFAULT NULL,
  `screenshot_file_name` varchar(255) DEFAULT NULL,
  `status` varchar(100) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `support`  ADD PRIMARY KEY (`support_id`);

ALTER TABLE `support`  MODIFY `support_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;


INSERT INTO `system_config` (`display_name`, `name`, `value`) VALUES ('Email Id', 'sup_email', NULL);
INSERT INTO `system_config` (`display_name`, `name`, `value`) VALUES ('Password', 'sup_password', NULL);

-- Amit
ALTER TABLE `eid_imported_controls` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `tested_by`;
