
ALTER TABLE `vl_request_form` ADD UNIQUE( `remote_sample_code`);
ALTER TABLE `vl_request_form` ADD UNIQUE( `sample_code`, `lab_id`);
ALTER TABLE `eid_form` ADD UNIQUE( `remote_sample_code`);
ALTER TABLE `eid_form` ADD UNIQUE( `sample_code`, `lab_id`);
ALTER TABLE `form_covid19` ADD UNIQUE( `remote_sample_code`);
ALTER TABLE `form_covid19` ADD UNIQUE( `sample_code`, `lab_id`);
ALTER TABLE `form_hepatitis` ADD UNIQUE( `remote_sample_code`);
ALTER TABLE `form_hepatitis` ADD UNIQUE( `sample_code`, `lab_id`);



-- Thana 29-Oct-2021
ALTER TABLE `move_samples` ADD `test_type` VARCHAR(256) NULL DEFAULT NULL AFTER `moved_to_lab_id`;
ALTER TABLE `move_samples_map` ADD `test_type` VARCHAR(256) NULL DEFAULT NULL AFTER `vl_sample_id`;
ALTER TABLE `move_samples_map` CHANGE `vl_sample_id` `test_type_sample_id` INT NULL DEFAULT NULL;

-- Thana 01-Nov-2021
ALTER TABLE `vl_request_form` CHANGE `app_local_test_req_id` `app_sample_code` VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `app_local_test_req_id` `app_sample_code` VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;
ALTER TABLE `form_covid19` CHANGE `app_local_test_req_id` `app_sample_code` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;

INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('tb-requests', 'tb', 'TB Request Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-requests', 'tb-requests.php', 'View Requests'), (NULL, 'tb-requests', 'tb-add-request.php', 'Add Request');

CREATE TABLE `form_tb` (
  `tb_id` INT NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(1000) NULL DEFAULT NULL,
  `vlsm_instance_id` TEXT NULL DEFAULT NULL,
  `vlsm_country_id` INT NULL DEFAULT NULL,
  `sample_reordered` varchar(1000) NOT NULL DEFAULT 'no',
  `sample_code_key` int(11) NOT NULL,
  `sample_code_format` TEXT NULL DEFAULT NULL,
  `sample_code` varchar(1000) NULL DEFAULT NULL,
  `remote_sample` varchar(1000) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` INT NULL DEFAULT NULL,
  `remote_sample_code_format` TEXT NULL DEFAULT NULL,
  `remote_sample_code` varchar(1000) NULL DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` INT NULL DEFAULT NULL,
  `implementing_partner` INT NULL DEFAULT NULL,
  `facility_id` INT NULL DEFAULT NULL,
  `province_id` INT NULL DEFAULT NULL,
  `patient_id` TEXT NULL DEFAULT NULL,
  `patient_name` TEXT NULL DEFAULT NULL,
  `patient_surname` TEXT NULL DEFAULT NULL,
  `patient_dob` date DEFAULT NULL,
  `patient_age` TEXT NULL DEFAULT NULL,
  `patient_gender` TEXT NULL DEFAULT NULL,
  `patient_address` TEXT NULL DEFAULT NULL,
  `patient_phone` TEXT NULL DEFAULT NULL,
  `patient_type` JSON NULL DEFAULT NULL,
  `hiv_status` TEXT NULL DEFAULT NULL,
  `tests_requested` JSON NULL DEFAULT NULL,
  `sample_requestor_name` TEXT NULL DEFAULT NULL,
  `sample_requestor_phone` TEXT NULL DEFAULT NULL,
  `specimen_quality` TEXT NULL DEFAULT NULL,
  `specimen_type` TEXT NULL DEFAULT NULL,
  `reason_for_tb_test` JSON NULL DEFAULT NULL,
  `lab_id` INT NULL DEFAULT NULL,
  `lab_technician` TEXT NULL DEFAULT NULL,
  `lab_reception_person` TEXT NULL DEFAULT NULL,
  `is_sample_rejected` varchar(1000) NOT NULL DEFAULT 'no',
  `reason_for_sample_rejection` TEXT NULL DEFAULT NULL,
  `tb_test_platform` TEXT NULL DEFAULT NULL,
  `result_status` INT NULL DEFAULT NULL,
  `locked` varchar(50) NOT NULL DEFAULT 'no',
  `result` TEXT NULL DEFAULT NULL,
  `reason_for_changing` varchar(256) DEFAULT NULL,
  `tested_by` TEXT NULL DEFAULT NULL,
  `result_reviewed_by` TEXT NULL DEFAULT NULL,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_approved_by` TEXT NULL DEFAULT NULL,
  `result_approved_datetime` datetime DEFAULT NULL,
  `revised_by` TEXT NULL DEFAULT NULL,
  `revised_on` datetime DEFAULT NULL,
  `approver_comments` TEXT NULL DEFAULT NULL,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `app_sample_code` varchar(256) DEFAULT NULL,
  `manual_result_entry` varchar(255) DEFAULT 'no',
  `import_machine_name` TEXT NULL DEFAULT NULL,
  `import_machine_file_name` TEXT NULL DEFAULT NULL,
  `result_printed_datetime` datetime DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  `request_created_by` TEXT NULL DEFAULT NULL,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` TEXT NULL DEFAULT NULL,
  `sample_batch_id` INT NULL DEFAULT NULL,
  `sample_package_id` TEXT NULL DEFAULT NULL,
  `sample_package_code` TEXT NULL DEFAULT NULL,
  `source_of_request` varchar(50) DEFAULT NULL,
  `source_data_dump` text,
  `result_sent_to_source` text,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`tb_id`),
  UNIQUE KEY `sample_code` (`sample_code`,`lab_id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  KEY `facility_id` (`facility_id`),
  KEY `lab_id` (`lab_id`),
  KEY `sample_code_key` (`sample_code_key`),
  KEY `remote_sample_code_key` (`remote_sample_code_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8;

CREATE TABLE `r_tb_sample_type` (
 `sample_id` int NOT NULL AUTO_INCREMENT,
 `sample_name` varchar(256) DEFAULT NULL,
 `status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `r_tb_sample_type` (`sample_id`, `sample_name`, `status`, `updated_datetime`, `data_sync`) VALUES (NULL, 'Serum', 'active', CURRENT_TIMESTAMP, '0');

CREATE TABLE `r_tb_sample_rejection_reasons` (
 `rejection_reason_id` int NOT NULL AUTO_INCREMENT,
 `rejection_reason_name` varchar(256) DEFAULT NULL,
 `rejection_type` varchar(256) NOT NULL DEFAULT 'general',
 `rejection_reason_status` varchar(45) DEFAULT NULL,
 `rejection_reason_code` varchar(256) DEFAULT NULL,
 `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `r_tb_sample_rejection_reasons` (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`, `rejection_reason_status`, `rejection_reason_code`, `updated_datetime`, `data_sync`) VALUES (NULL, 'Sample damaged', 'general', 'active', NULL, CURRENT_TIMESTAMP, '0');

CREATE TABLE `r_tb_test_reasons` (
 `test_reason_id` int NOT NULL AUTO_INCREMENT,
 `test_reason_name` varchar(256) DEFAULT NULL,
 `parent_reason` int DEFAULT NULL,
 `test_reason_status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `r_tb_test_reasons` (`test_reason_id`, `test_reason_name`, `parent_reason`, `test_reason_status`, `updated_datetime`) VALUES (NULL, 'Case confirmed in TB', NULL, 'active', CURRENT_TIMESTAMP);

CREATE TABLE `r_tb_results` (
 `result_id` int NOT NULL AUTO_INCREMENT,
 `result` varchar(256) DEFAULT NULL,
 `status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `r_tb_results` (`result_id`, `result`, `status`, `updated_datetime`, `data_sync`) VALUES (NULL, 'Positive', 'active', CURRENT_TIMESTAMP, '0'), (NULL, 'Negative', 'active', CURRENT_TIMESTAMP, '0');

-- Thana 02-Oct-2021
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('TB Sample Code Format', 'tb_sample_code', 'MMYY', 'tb', 'yes', '2021-11-02 17:48:32', NULL, 'active'), ('TB Sample Code Prefix', 'tb_sample_code_prefix', 'TB', 'tb', 'yes', '2021-11-02 17:48:32', NULL, 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('TB Minimum Length', 'tb_min_length', NULL, 'tb', 'yes', '2021-11-02 18:16:53', NULL, 'active'), ('TB Maximum Length', 'tb_max_length', NULL, 'tb', 'yes', '2021-11-02 18:16:53', NULL, 'active');

-- Thana 03-Oct-2021
DELETE FROM `resources` WHERE `resources`.`resource_id` = 'move-samples';
DELETE FROM `privileges` WHERE `privileges`.`resource_id` = 'move-samples';
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('move-samples', 'common', 'Move Samples');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'move-samples', 'move-samples.php', 'Access'), (NULL, 'move-samples', 'select-samples-to-move.php', 'Add Move Samples');

-- Amit 04 Nov 2021
DELETE FROM roles_privileges_map where roles_privileges_map.privilege_id NOT IN (SELECT privileges.privilege_id from privileges);

-- Thana 05-Oct-2021
ALTER TABLE `r_tb_results` ADD `result_type` VARCHAR(256) NULL DEFAULT NULL AFTER `result`;
INSERT INTO `r_tb_results` (`result_id`, `result`, `result_type`, `status`, `updated_datetime`, `data_sync`) VALUES (NULL, 'Negative', 'lam', 'active', CURRENT_TIMESTAMP, '0'), (NULL, 'Positive', 'lam', 'active', CURRENT_TIMESTAMP, '0'), (NULL, 'Invalid', 'lam', 'active', CURRENT_TIMESTAMP, '0'), (NULL, 'N (MTB not detected)', 'x-pert', 'active', CURRENT_TIMESTAMP, '0'), (NULL, 'T (MTB detected rifampicin resistance not detected)', 'x-pert', 'active', CURRENT_TIMESTAMP, '0'), (NULL, 'TI (MTB detected rifampicin resistance indeterminate)', 'x-pert', 'active', CURRENT_TIMESTAMP, '0'), (NULL, 'RR (MTB detected rifampicin resistance detected)', 'lam', 'active', CURRENT_TIMESTAMP, '0'), (NULL, 'TT (MTB detected (Trace) rifampicin resistance indeterminate)', 'x-pert', 'active', CURRENT_TIMESTAMP, '0'), (NULL, 'I (Invalid/Error/No result)', 'x-pert', 'active', CURRENT_TIMESTAMP, '0');
ALTER TABLE `testing_labs` CHANGE `test_type` `test_type` ENUM('vl','eid','covid19','hepatitis','tb') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;
ALTER TABLE `health_facilities` CHANGE `test_type` `test_type` ENUM('vl','eid','covid19','hepatitis','tb') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL;
CREATE TABLE `tb_tests` (
 `tb_test_id` int NOT NULL AUTO_INCREMENT,
 `tb_id` int DEFAULT NULL,
 `actual_no` varchar(256) DEFAULT NULL,
 `test_result` varchar(256) DEFAULT NULL,
 `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`tb_test_id`),
 KEY `tb_id` (`tb_id`),
 CONSTRAINT `tb_tests_ibfk_1` FOREIGN KEY (`tb_id`) REFERENCES `form_tb` (`tb_id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- Sakthivel 05-11-2021

CREATE TABLE `user_login_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `login_id` varchar (1000) DEFAULT NULL,
  `login_attempted_datetime` datetime DEFAULT NULL,
  `login_status` varchar (1000) DEFAULT NULL,
  `ip_address` varchar (1000) DEFAULT NULL,
  `browser` varchar (1000),
  `operating_system` varchar (1000) DEFAULT NULL,
   PRIMARY KEY (`history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



-- Thana 08-Nov-2021
ALTER TABLE `covid19_tests` ADD `kit_lot_no` VARCHAR(256) NULL DEFAULT NULL AFTER `testing_platform`, ADD `kit_expiry_date` DATE NULL DEFAULT NULL AFTER `kit_lot_no`;

-- Amit 09-Nov-2021
UPDATE `resources` SET `resource_id` = 'vl-requests' WHERE `resource_id` = 'vl-test-request';
UPDATE `privileges` SET `resource_id` = 'vl-requests' WHERE `resource_id` = 'vl-test-request';
UPDATE `privileges` SET `display_name` = 'Enter Result Manually' WHERE `privilege_name` = 'vlTestResult.php';
UPDATE `privileges` SET `display_name` = 'Enter Result Manually' WHERE `privilege_name` = 'eid-manual-results.php';
DELETE FROM roles_privileges_map where privilege_id in (SELECT privilege_id from privileges WHERE privilege_name like 'viewVlRequest.php');
DELETE  FROM `privileges` WHERE privilege_name like 'viewVlRequest.php';
UPDATE `privileges` SET `display_name` = 'View' WHERE `privilege_name` = 'vlRequest.php';
UPDATE `privileges` SET `display_name` = 'Add' WHERE `privilege_name` = 'eid-add-request.php';
UPDATE `privileges` SET `display_name` = 'Edit' WHERE `privilege_name` = 'eid-edit-request.php';
UPDATE `privileges` SET `display_name` = 'View' WHERE `privilege_name` = 'eid-requests.php';
UPDATE `privileges` SET `display_name` = 'Add' WHERE `privilege_name` = 'covid-19-add-request.php';
UPDATE `privileges` SET `display_name` = 'Edit' WHERE `privilege_name` = 'covid-19-edit-request.php';
UPDATE `privileges` SET `display_name` = 'View' WHERE `privilege_name` = 'covid-19-requests.php';
UPDATE `privileges` SET `display_name` = 'Add' WHERE `privilege_name` = 'hepatitis-add-request.php';
UPDATE `privileges` SET `display_name` = 'Edit' WHERE `privilege_name` = 'hepatitis-edit-request.php';
UPDATE `privileges` SET `display_name` = 'View' WHERE `privilege_name` = 'hepatitis-requests.php';
UPDATE `privileges` SET `display_name` = 'Add' WHERE `privilege_name` = 'tb-add-request.php';
UPDATE `privileges` SET `display_name` = 'View' WHERE `privilege_name` = 'tb-requests.php';

-- Amit 13 Nov 2021 version 4.4.4
UPDATE `system_config` SET `value` = '4.4.4' WHERE `system_config`.`name` = 'sc_version';

-- Amit 15 Nov 2021
DELETE FROM `facility_type` WHERE `facility_type`.`facility_type_id` = 3;
DELETE FROM `facility_type` WHERE `facility_type`.`facility_type_id` = 4;
UPDATE `facility_type` SET `facility_type_name` = 'Health Facility' WHERE `facility_type_id` = 1;
UPDATE `facility_type` SET `facility_type_name` = 'Testing Lab' WHERE `facility_type_id` = 2;


-- Amit 16 Nov 2021
UPDATE facility_details set facility_type = 1 WHERE facility_type in (3,4);

-- Amit 17 Nov 2021

RENAME TABLE `user_admin_details` TO `system_admin`;
ALTER TABLE `system_admin` CHANGE `user_admin_id` `system_admin_id` INT(11) NOT NULL AUTO_INCREMENT, CHANGE `user_admin_name` `system_admin_name` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `user_admin_login` `system_admin_login` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `user_admin_password` `system_admin_password` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `user_details` ADD `interface_user_name` JSON NULL DEFAULT NULL AFTER `user_name`;
ALTER TABLE `user_details` CHANGE `user_name` `user_name` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `user_details` ADD UNIQUE(`user_name`);

-- Thana 18-Nov-2021
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES
('VL Sample Expiry Days', 'vl_sample_expiry_after_days', '9999', 'vl', 'yes', NULL, NULL, 'active'),
('EID Sample Expiry Days', 'eid_sample_expiry_after_days', '9999', 'eid', 'yes', NULL, NULL, 'active'),
('Covid19 Sample Expiry Days', 'covid19_sample_expiry_after_days', '9999', 'covid19', 'yes', NULL, NULL, 'active'),
('Hepatitis Sample Expiry Days', 'hepatitis_sample_expiry_after_days', '9999', 'hepatitis', 'yes', NULL, NULL, 'active'),
('TB Sample Expiry Days', 'tb_sample_expiry_after_days', '9999', 'tb', 'yes', NULL, NULL, 'active');

-- Thana 19-Nov-2021
INSERT INTO `r_sample_status` (`status_id`, `status_name`, `status`) VALUES (10, 'Sample Expired', 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES
('VL Sample Lock Expiry Days', 'vl_sample_lock_after_days', NULL, 'vl', 'yes', NULL, NULL, 'active'),
('EID Sample Lock Expiry Days', 'eid_sample_lock_after_days', NULL, 'eid', 'yes', NULL, NULL, 'active'),
('Covid19 Sample Lock Expiry Days', 'covid19_sample_lock_after_days', NULL, 'covid19', 'yes', NULL, NULL, 'active'),
('Hepatitis Sample Lock Expiry Days', 'hepatitis_sample_lock_after_days', NULL, 'hepatitis', 'yes', NULL, NULL, 'active'),
('TB Sample Lock Expiry Days', 'tb_sample_lock_after_days', NULL, 'tb', 'yes', NULL, NULL, 'active');

-- Thana 22-Nov-2021
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-requests', 'tb-edit-request.php', 'Edit');
ALTER TABLE `form_tb` ADD `xpert_mtb_result` TEXT NULL DEFAULT NULL AFTER `result`;

-- Thana 23-Nov-2021
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('tb-results', 'tb', 'TB Result Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-results', 'tb-manual-results.php', 'Enter Result Manually');

-- Sakthivel 25-Nov-2021

ALTER TABLE `vl_request_form` ADD `sample_dispatched_datetime` datetime DEFAULT NULL AFTER `sample_collection_date`;
ALTER TABLE `eid_form` ADD `sample_dispatched_datetime` datetime DEFAULT NULL AFTER `sample_collection_date`;
ALTER TABLE `form_covid19` ADD `sample_dispatched_datetime` datetime DEFAULT NULL AFTER `sample_collection_date`;
ALTER TABLE `form_tb` ADD `sample_dispatched_datetime` datetime DEFAULT NULL AFTER `sample_collection_date`;
ALTER TABLE `form_hepatitis` ADD `sample_dispatched_datetime` datetime DEFAULT NULL AFTER `sample_collection_date`;

-- Thana 25-Nov-2021
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('tb-management', 'tb', 'TB Reports');
UPDATE `privileges` SET `resource_id` = 'covid-19-results' WHERE `privileges`.`privilege_id` = 99;
UPDATE `privileges` SET `resource_id` = 'hepatitis-results' WHERE `privileges`.`privilege_id` = 156;
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-results', 'tb-print-results.php', 'Print Results');
UPDATE `privileges` SET `resource_id` = 'covid-19-results' WHERE `privileges`.`privilege_id` = 98;
UPDATE `privileges` SET `resource_id` = 'hepatitis-results' WHERE `privileges`.`privilege_id` = 157;
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-results', 'tb-result-status.php', 'Manage Result Status');

-- Sakthivel 29-Nov-2021
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('tb-reference', 'admin', 'TB Reference');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-reference', 'tb-sample-type.php', 'Manage Reference');

-- Amit 1 Dec 2021 version 4.4.5
UPDATE `system_config` SET `value` = '4.4.5' WHERE `system_config`.`name` = 'sc_version';

-- Sakthivel 01-Dec-2021
-- ALTER TABLE `r_covid19_results`
--   MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

-- Sakthivel 01-Dec-2021
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-results', 'tb-export-data.php', 'Export Data');

-- Thana 01-Dec-2021
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('tb-batches', 'tb', 'TB Batch Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-batches', 'tb-batches.php', 'View Batches'), (NULL, 'tb-batches', 'tb-add-batch.php', 'Add Batch'), (NULL, 'tb-batches', 'tb-edit-batch.php', 'Edit Batch'), (NULL, 'tb-batches', 'tb-add-batch-position.php', 'Add Batch Position'), (NULL, 'tb-batches', 'tb-edit-batch-position.php', 'Edit Batch Position');
ALTER TABLE `tb_tests` ADD `sample_tested_datetime` DATETIME NULL DEFAULT NULL AFTER `test_result`, ADD `testing_platform` VARCHAR(256) NULL DEFAULT NULL AFTER `sample_tested_datetime`;

-- Sakthivel P 03-Dec-2021
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-requests', 'addSamplesFromManifest.php', 'Add Samples from Manifest');

-- Sakthivel P 06-Dec-2021
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-results', 'tb-sample-status.php', 'Sample Status Report');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-results', 'tb-sample-rejection-report.php', 'Sample Rejection Report');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'tb-results', 'tb-clinic-report.php', 'TB Clinic Report');

-- Sakthivel P 07-Dec-2021
UPDATE `privileges` SET `resource_id` = 'tb-management' WHERE `privilege_id` = 197;
UPDATE `privileges` SET `resource_id` = 'tb-management' WHERE `privilege_id` = 199;
UPDATE `privileges` SET `resource_id` = 'tb-management' WHERE `privilege_id` = 200;
UPDATE `privileges` SET `resource_id` = 'tb-management' WHERE `privilege_id` = 201;

-- Thana 07-Dec-2021
ALTER TABLE `form_tb` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `form_tb` ADD `referring_unit` VARCHAR(256) NULL DEFAULT NULL AFTER `province_id`;
ALTER TABLE `tb_tests` DROP `sample_tested_datetime`, DROP `testing_platform`;

-- Sakthivel P 07-Dec-2021
ALTER TABLE `testing_labs` ADD `attributes` JSON NULL DEFAULT NULL AFTER `facility_id`;

-- Sakthivel P 08-Dec-2021
ALTER TABLE `form_tb` ADD `other_specimen_type` TEXT NULL DEFAULT NULL AFTER `specimen_type`;
ALTER TABLE `form_tb` ADD `other_referring_unit` TEXT NULL DEFAULT NULL AFTER `referring_unit`;
ALTER TABLE `form_tb` ADD `other_patient_type` TEXT NULL DEFAULT NULL AFTER `patient_type`;
-- Thana 10-Dec-2021
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES
('Lock Approved TB Samples', 'lock_approved_tb_samples', 'no', 'tb', 'yes', NULL, NULL, 'active');

-- Sakthivel 13-Dec-2021
ALTER TABLE `r_covid19_results` MODIFY `result_id` varchar(255) NOT NULL;
ALTER TABLE `r_tb_results` MODIFY `result_id` varchar(255) NOT NULL;

-- Sakthivel 14-Dec-2021
ALTER TABLE `testing_labs` CHANGE `attributes` `attributes` JSON NULL DEFAULT NULL;

-- Amit 16-Dec-2021
ALTER TABLE `eid_form` CHANGE `mother_id` `mother_id` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_name` `mother_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_surname` `mother_surname` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `caretaker_contact_consent` `caretaker_contact_consent` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `caretaker_phone_number` `caretaker_phone_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `caretaker_address` `caretaker_address` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_id` `child_id` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_name` `child_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_surname` `child_surname` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_vl_test_date` `mother_vl_test_date` DATE NULL DEFAULT NULL, CHANGE `sample_requestor_name` `sample_requestor_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lab_technician` `lab_technician` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lab_reception_person` `lab_reception_person` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `tested_by` `tested_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result_reviewed_by` `result_reviewed_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `revised_by` `revised_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result_approved_by` `result_approved_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `approver_comments` `approver_comments` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `import_machine_name` `import_machine_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `import_machine_file_name` `import_machine_file_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `request_created_by` `request_created_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_modified_by` `last_modified_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `sample_package_code` `sample_package_code` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lot_number` `lot_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `source_of_request` `source_of_request` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `unique_id` `unique_id` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `sample_code` `sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `remote_sample_code` `remote_sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_covid19` CHANGE `remote_sample_code` `remote_sample_code` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_name` `patient_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_surname` `patient_surname` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_phone_number` `patient_phone_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_passport_number` `patient_passport_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `does_patient_smoke` `does_patient_smoke` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `flight_airline` `flight_airline` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `flight_seat_no` `flight_seat_no` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `flight_airport_of_departure` `flight_airport_of_departure` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `flight_transit` `flight_transit` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `type_of_test_requested` `type_of_test_requested` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_province` `patient_province` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_district` `patient_district` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_zone` `patient_zone` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_city` `patient_city` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `medical_history` `medical_history` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `contact_with_confirmed_case` `contact_with_confirmed_case` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `has_recent_travel_history` `has_recent_travel_history` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `travel_country_names` `travel_country_names` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lab_technician` `lab_technician` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `investogator_name` `investogator_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `investigator_phone` `investigator_phone` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `investigator_email` `investigator_email` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `clinician_name` `clinician_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `reason_for_sample_rejection` `reason_for_sample_rejection` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result` `result` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `revised_by` `revised_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `approver_comments` `approver_comments` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `request_created_by` `request_created_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `source_of_request` `source_of_request` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_covid19` CHANGE `sample_code` `sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_covid19` CHANGE `unique_id` `unique_id` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `unique_id` `unique_id` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `remote_sample_code` `remote_sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `sample_code` `sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_hepatitis` CHANGE `hepatitis_test_type` `hepatitis_test_type` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_id` `patient_id` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_name` `patient_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_surname` `patient_surname` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_phone_number` `patient_phone_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_province` `patient_province` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_district` `patient_district` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_city` `patient_city` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_nationality` `patient_nationality` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_occupation` `patient_occupation` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_address` `patient_address` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_marital_status` `patient_marital_status` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `social_category` `social_category` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_insurance` `patient_insurance` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `hbv_vaccination` `hbv_vaccination` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `type_of_test_requested` `type_of_test_requested` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `reason_for_vl_test` `reason_for_vl_test` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `priority_status` `priority_status` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lab_technician` `lab_technician` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result` `result` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `tested_by` `tested_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `authorized_by` `authorized_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `revised_by` `revised_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result_reviewed_by` `result_reviewed_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result_approved_by` `result_approved_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `import_machine_name` `import_machine_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `request_created_by` `request_created_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `sample_package_code` `sample_package_code` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `source_of_request` `source_of_request` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_modified_by` `last_modified_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_hepatitis` CHANGE `unique_id` `unique_id` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_hepatitis` CHANGE `remote_sample_code` `remote_sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_hepatitis` CHANGE `sample_code` `sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `vl_request_form` CHANGE `unique_id` `unique_id` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `vl_request_form` CHANGE `remote_sample_code` `remote_sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `vl_request_form` CHANGE `sample_code` `sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `user_details` CHANGE `user_name` `user_name` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `vl_request_form` CHANGE `sample_package_code` `sample_package_code` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_first_name` `patient_first_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_middle_name` `patient_middle_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_last_name` `patient_last_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_responsible_person` `patient_responsible_person` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_province` `patient_province` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_district` `patient_district` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_group` `patient_group` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_gender` `patient_gender` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_mobile_number` `patient_mobile_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_location` `patient_location` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_receiving_therapy` `patient_receiving_therapy` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_drugs_transmission` `patient_drugs_transmission` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_tb` `patient_tb` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `line_of_treatment_ref_type` `line_of_treatment_ref_type` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `current_regimen` `current_regimen` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `is_adherance_poor` `is_adherance_poor` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `number_of_enhanced_sessions` `number_of_enhanced_sessions` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_vl_result_routine` `last_vl_result_routine` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_vl_result_failure_ac` `last_vl_result_failure_ac` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_vl_result_failure` `last_vl_result_failure` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_vl_result_ecd` `last_vl_result_ecd` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_vl_result_cf` `last_vl_result_cf` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_vl_result_if` `last_vl_result_if` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `request_clinician_name` `request_clinician_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_modified_by` `last_modified_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lab_name` `lab_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lab_technician` `lab_technician` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lab_contact_person` `lab_contact_person` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result_value_text` `result_value_text` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result` `result` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `tested_by` `tested_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result_approved_by` `result_approved_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `revised_by` `revised_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result_reviewed_by` `result_reviewed_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `test_methods` `test_methods` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `requesting_category` `requesting_category` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `requesting_person` `requesting_person` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `vl_result_category` `vl_result_category` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `source_of_request` `source_of_request` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `vl_request_form` CHANGE `patient_tb_yes` `patient_tb_yes` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `treatment_initiation` `treatment_initiation` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `is_patient_pregnant` `is_patient_pregnant` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `is_patient_breastfeeding` `is_patient_breastfeeding` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `arv_adherance_percentage` `arv_adherance_percentage` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `consent_to_receive_sms` `consent_to_receive_sms` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `vl_focal_person` `vl_focal_person` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `vl_focal_person_phone_number` `vl_focal_person_phone_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `request_created_by` `request_created_by` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL, CHANGE `patient_other_id` `patient_other_id` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `vl_sample_suspected_treatment_failure_at` `vl_sample_suspected_treatment_failure_at` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lab_phone_number` `lab_phone_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `lot_number` `lot_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `contact_complete_status` `contact_complete_status` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_viral_load_result` `last_viral_load_result` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_vl_result_in_log` `last_vl_result_in_log` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `reason_for_vl_testing` `reason_for_vl_testing` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `reason_for_vl_testing_other` `reason_for_vl_testing_other` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `drug_substitution` `drug_substitution` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `sample_collected_by` `sample_collected_by` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `vl_test_platform` `vl_test_platform` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `facility_support_partner` `facility_support_partner` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `reason_for_regimen_change` `reason_for_regimen_change` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `plasma_conservation_duration` `plasma_conservation_duration` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `physician_name` `physician_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `vl_test_number` `vl_test_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `is_request_mail_sent` `is_request_mail_sent` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'no', CHANGE `is_result_mail_sent` `is_result_mail_sent` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'no', CHANGE `import_machine_file_name` `import_machine_file_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `tech_name_png` `tech_name_png` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `qc_tech_name` `qc_tech_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `qc_tech_sign` `qc_tech_sign` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `qc_date` `qc_date` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `whole_blood_ml` `whole_blood_ml` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `whole_blood_vial` `whole_blood_vial` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `plasma_ml` `plasma_ml` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `plasma_vial` `plasma_vial` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `plasma_process_time` `plasma_process_time` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `plasma_process_tech` `plasma_process_tech` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `batch_quality` `batch_quality` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `sample_test_quality` `sample_test_quality` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `repeat_sample_collection` `repeat_sample_collection` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `sample_to_transport` `sample_to_transport` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `requesting_professional_number` `requesting_professional_number` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `requesting_vl_service_sector` `requesting_vl_service_sector` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `requesting_phone` `requesting_phone` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `recency_vl` `recency_vl` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'no', CHANGE `consultation` `consultation` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;


ALTER DATABASE vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `activity_log`  COLLATE utf8mb4_general_ci;
ALTER TABLE `batch_details`  COLLATE utf8mb4_general_ci;
ALTER TABLE `contact_notes_details`  COLLATE utf8mb4_general_ci;
ALTER TABLE `covid19_imported_controls`  COLLATE utf8mb4_general_ci;
ALTER TABLE `covid19_patient_comorbidities`  COLLATE utf8mb4_general_ci;
ALTER TABLE `covid19_patient_symptoms`  COLLATE utf8mb4_general_ci;
ALTER TABLE `covid19_positive_confirmation_manifest`  COLLATE utf8mb4_general_ci;
ALTER TABLE `covid19_reasons_for_testing`  COLLATE utf8mb4_general_ci;
ALTER TABLE `covid19_tests`  COLLATE utf8mb4_general_ci;
ALTER TABLE `eid_form`  COLLATE utf8mb4_general_ci;
ALTER TABLE `eid_imported_controls`  COLLATE utf8mb4_general_ci;
ALTER TABLE `facility_details`  COLLATE utf8mb4_general_ci;
ALTER TABLE `facility_type`  COLLATE utf8mb4_general_ci;
ALTER TABLE `failed_result_retest_tracker`  COLLATE utf8mb4_general_ci;
ALTER TABLE `form_covid19`  COLLATE utf8mb4_general_ci;
ALTER TABLE `form_hepatitis`  COLLATE utf8mb4_general_ci;
ALTER TABLE `form_tb`  COLLATE utf8mb4_general_ci;
ALTER TABLE `geographical_divisions`  COLLATE utf8mb4_general_ci;
ALTER TABLE `global_config`  COLLATE utf8mb4_general_ci;
ALTER TABLE `health_facilities`  COLLATE utf8mb4_general_ci;
ALTER TABLE `hepatitis_patient_comorbidities`  COLLATE utf8mb4_general_ci;
ALTER TABLE `hepatitis_risk_factors`  COLLATE utf8mb4_general_ci;
ALTER TABLE `hold_sample_import`  COLLATE utf8mb4_general_ci;
ALTER TABLE `import_config`  COLLATE utf8mb4_general_ci;
ALTER TABLE `import_config_controls`  COLLATE utf8mb4_general_ci;
ALTER TABLE `import_config_machines`  COLLATE utf8mb4_general_ci;
ALTER TABLE `lab_report_signatories`  COLLATE utf8mb4_general_ci;
ALTER TABLE `log_result_updates`  COLLATE utf8mb4_general_ci;
ALTER TABLE `move_samples`  COLLATE utf8mb4_general_ci;
ALTER TABLE `move_samples_map`  COLLATE utf8mb4_general_ci;
ALTER TABLE `other_config`  COLLATE utf8mb4_general_ci;
ALTER TABLE `package_details`  COLLATE utf8mb4_general_ci;
ALTER TABLE `privileges`  COLLATE utf8mb4_general_ci;
ALTER TABLE `province_details`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_countries`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_covid19_comorbidities`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_covid19_results`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_covid19_sample_rejection_reasons`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_covid19_sample_type`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_covid19_symptoms`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_covid19_test_reasons`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_eid_results`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_eid_sample_rejection_reasons`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_eid_sample_type`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_eid_test_reasons`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_funding_sources`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_hepatitis_comorbidities`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_hepatitis_results`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_hepatitis_risk_factors`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_hepatitis_sample_rejection_reasons`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_hepatitis_sample_type`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_hepatitis_test_reasons`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_implementation_partners`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_sample_controls`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_sample_status`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_tb_results`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_tb_sample_rejection_reasons`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_tb_sample_type`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_tb_test_reasons`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_vl_art_regimen`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_vl_sample_rejection_reasons`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_vl_sample_type`  COLLATE utf8mb4_general_ci;
ALTER TABLE `r_vl_test_reasons`  COLLATE utf8mb4_general_ci;
ALTER TABLE `report_to_mail`  COLLATE utf8mb4_general_ci;
ALTER TABLE `resources`  COLLATE utf8mb4_general_ci;
ALTER TABLE `result_import_stats`  COLLATE utf8mb4_general_ci;
ALTER TABLE `roles`  COLLATE utf8mb4_general_ci;
ALTER TABLE `roles_privileges_map`  COLLATE utf8mb4_general_ci;
ALTER TABLE `s_available_country_forms`  COLLATE utf8mb4_general_ci;
ALTER TABLE `s_vlsm_instance`  COLLATE utf8mb4_general_ci;
ALTER TABLE `system_admin`  COLLATE utf8mb4_general_ci;
ALTER TABLE `system_config`  COLLATE utf8mb4_general_ci;
ALTER TABLE `tb_tests`  COLLATE utf8mb4_general_ci;
ALTER TABLE `temp_sample_import`  COLLATE utf8mb4_general_ci;
ALTER TABLE `testing_labs`  COLLATE utf8mb4_general_ci;
ALTER TABLE `track_api_requests`  COLLATE utf8mb4_general_ci;
ALTER TABLE `track_qr_code_page`  COLLATE utf8mb4_general_ci;
ALTER TABLE `user_details`  COLLATE utf8mb4_general_ci;
ALTER TABLE `user_login_history`  COLLATE utf8mb4_general_ci;
ALTER TABLE `vl_facility_map`  COLLATE utf8mb4_general_ci;
ALTER TABLE `vl_imported_controls`  COLLATE utf8mb4_general_ci;
ALTER TABLE `vl_request_form`  COLLATE utf8mb4_general_ci;
ALTER TABLE `vl_user_facility_map`  COLLATE utf8mb4_general_ci;

-- Thana 17-Dec-2021
ALTER TABLE `form_covid19` ADD `lab_manager` TEXT NULL DEFAULT NULL AFTER `lab_id`;

-- Amit 21 Dec 2021 version 4.4.6
UPDATE `system_config` SET `value` = '4.4.6' WHERE `system_config`.`name` = 'sc_version';

-- Thana 22-Dec-2021
INSERT INTO `facility_type` (`facility_type_id`, `facility_type_name`) VALUES ('3', 'Collection Site');

-- Sakthivel 22 Dec 2021
ALTER TABLE `system_admin` ADD `system_admin_email` VARCHAR(255) NULL DEFAULT NULL AFTER `system_admin_name`;

-- Thana 24-Dec-2021
ALTER TABLE `user_details` ADD `data_sync` INT(11) NULL DEFAULT '0' AFTER `app_access`;

-- Amit 11-Jan-2021
ALTER TABLE `batch_details` CHANGE `batch_code_key` `batch_code_key` INT NULL DEFAULT NULL;

-- Thana 11-Jan-2022
ALTER TABLE `batch_details` ADD `position_type` VARCHAR(256) NULL DEFAULT NULL AFTER `sent_mail`;

-- Amit 24 Jan 2022 version 4.4.7
UPDATE `system_config` SET `value` = '4.4.7' WHERE `system_config`.`name` = 'sc_version';

-- Amit 27 Jan 2022
ALTER TABLE `form_covid19` CHANGE `investogator_name` `investigator_name` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

-- Amit 29 Jan 2022
ALTER TABLE `user_details` CHANGE `role_id` `role_id` INT NULL DEFAULT NULL;

-- Thana 31-Jan-2022
ALTER TABLE `log_result_updates` CHANGE `test_type` `test_type` VARCHAR(244) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL COMMENT 'vl, eid, covid19, hepatitis, tb';
ALTER TABLE `log_result_updates` ADD `result_method` VARCHAR(256) NULL DEFAULT NULL AFTER `test_type`;
ALTER TABLE `log_result_updates` ADD `file_name` VARCHAR(256) NULL DEFAULT NULL AFTER `result_method`;
-- Amit 31 Jan 2022
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES
('Generate Patient Code', 'covid19_generate_patient_code', 'no', 'covid19', 'no', NULL, NULL, 'active'),
('Patient Code Prefix', 'covid19_patient_code_prefix', 'P', 'covid19', 'no', NULL, NULL, 'active');

-- Amit 01-Feb-2022
CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_code_prefix` varchar(256) DEFAULT NULL,
  `patient_code_key` int(11) DEFAULT NULL,
  `patient_code` varchar(256) DEFAULT NULL,
  `patient_first_name` text DEFAULT NULL,
  `patient_middle_name` text DEFAULT NULL,
  `patient_last_name` text DEFAULT NULL,
  `patient_gender` varchar(256) DEFAULT NULL,
  `patient_province` int(11) DEFAULT NULL,
  `patient_district` int(11) DEFAULT NULL,
  `patient_registered_on` datetime DEFAULT NULL,
  `patient_registered_by` text DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`patient_id`),
  UNIQUE KEY `patient_code` (`patient_code`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4;
ALTER TABLE `patients` ADD UNIQUE(`patient_code_prefix`, `patient_code_key`);

-- Thana 03-Feb-2022
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-reference', 'audit-trail.php', 'Audit Trail Details');

-- Thana 04-Feb-2022
ALTER TABLE `form_covid19` ADD `patient_email` VARCHAR(256) NULL DEFAULT NULL AFTER `patient_phone_number`;

-- Thana 07-Feb-2022
ALTER TABLE `activity_log` ADD `user_id` VARCHAR(256) NULL DEFAULT NULL AFTER `resource`;

-- Amit 10-Feb-2022
UPDATE `global_config` SET `remote_sync_needed` = 'no';
UPDATE `global_config` SET `remote_sync_needed` = 'yes' WHERE `global_config`.`name` = 'r_mandatory_fields';
UPDATE `global_config` SET `remote_sync_needed` = 'yes' WHERE `global_config`.`name` = 'l_vl_msg';
UPDATE `global_config` SET `remote_sync_needed` = 'yes' WHERE `global_config`.`name` = 'l_vl_msg';
UPDATE `global_config` SET `remote_sync_needed` = 'yes' WHERE `global_config`.`name` = 'low_vl_text_results';
UPDATE `global_config` SET `remote_sync_needed` = 'yes' WHERE `global_config`.`name` = 'vldashboard_url';
DELETE FROM `global_config`  WHERE `global_config`.`name` = 'eid_positive'
OR`global_config`.`name` = 'eid_negative'
OR `global_config`.`name` = 'eid_indeterminate'
OR `global_config`.`name` = 'covid19_positive'
OR `global_config`.`name` = 'covid19_negative'
OR `global_config`.`name` = 'covid19_indeterminate';


UPDATE `r_sample_status` SET `status_name` = 'Sample Registered at Testing Lab' WHERE `r_sample_status`.`status_id` = 6;


-- Amit 15-Feb-2022
DELETE FROM hepatitis_patient_comorbidities WHERE hepatitis_id IN (SELECT hepatitis_id FROM form_hepatitis WHERE source_of_request LIKE 'dhis2%' AND hbsag_result != 'positive' AND anti_hcv_result != 'positive');
DELETE FROM hepatitis_risk_factors WHERE hepatitis_id IN (SELECT hepatitis_id FROM form_hepatitis WHERE source_of_request LIKE 'dhis2%' AND hbsag_result != 'positive' AND anti_hcv_result != 'positive');
DELETE FROM form_hepatitis WHERE source_of_request LIKE 'dhis2%' AND hbsag_result != 'positive' AND anti_hcv_result != 'positive';
DELETE FROM hepatitis_patient_comorbidities WHERE hepatitis_id NOT IN (SELECT hepatitis_id FROM form_hepatitis);
DELETE FROM hepatitis_risk_factors WHERE hepatitis_id NOT IN (SELECT hepatitis_id FROM form_hepatitis);

-- Amit 21 Feb 2022
ALTER TABLE `user_details` ADD `force_password_reset` INT NULL DEFAULT NULL AFTER `api_token_exipiration_days`;

-- Amit 24-Feb-2022

INSERT INTO `global_config`
(`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`)
VALUES
('App Locale/Language', 'app_locale', 'en_US', 'common', 'no', NULL, NULL, 'active');

-- Amit 24-Feb-2022 version 4.4.8
UPDATE `system_config` SET `value` = '4.4.8' WHERE `system_config`.`name` = 'sc_version';

-- Thana 28-Feb-2022
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'vl-requests', 'export-vl-requests.php', 'Export VL Requests'), (NULL, 'eid-requests', 'export-eid-requests.php', 'Export EID Requests'), (NULL, 'covid-19-requests', 'export-covid19-requests.php', 'Export Covid-19 Requests '), (NULL, 'hepatitis-requests', 'export-hepatitis-requests.php', 'Export Hepatitis Requests'), (NULL, 'tb-requests', 'export-tb-requests.php', 'Export TB Requests');

-- Thana 04-Mar-2022
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-reference', 'api-sync-history.php', 'API Sync History');

-- Thana 10-Mar-2022
ALTER TABLE `user_details` DROP `user_alpnum_id`;
ALTER TABLE `user_details` ADD `updated_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `data_sync`;

-- Thana 16-Mar-2022
ALTER TABLE `track_api_requests` CHANGE `api_params` `api_params` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

-- Thana 17-Mar-2022
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-reference', 'sources-of-requests.php', 'Sources of Requests Report');


-- Amit 22-Mar-2022

ALTER TABLE `form_hepatitis` ADD `app_sample_code` VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `external_sample_code`;
-- ALTER TABLE `audit_form_hepatitis` ADD `app_sample_code` VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL AFTER `external_sample_code`;


UPDATE vl_request_form SET source_of_request = 'vlsm' WHERE remote_sample = 'no' and (source_of_request is null OR source_of_request like '' OR source_of_request like 'web');
UPDATE vl_request_form SET source_of_request = 'vlsts' WHERE remote_sample = 'yes' and (source_of_request is null OR source_of_request like '' OR source_of_request like 'web' OR source_of_request like 'vlsm');
UPDATE vl_request_form SET source_of_request = 'app' WHERE (source_of_request like 'api' or app_sample_code is not null);

UPDATE eid_form SET source_of_request = 'vlsm' WHERE remote_sample = 'no' and (source_of_request is null OR source_of_request like '' OR source_of_request like 'web');
UPDATE eid_form SET source_of_request = 'vlsts' WHERE remote_sample = 'yes' and (source_of_request is null OR source_of_request like '' OR source_of_request like 'web' OR source_of_request like 'vlsm');
UPDATE eid_form SET source_of_request = 'app' WHERE (source_of_request like 'api' or app_sample_code is not null);

UPDATE form_covid19 SET source_of_request = 'vlsm' WHERE remote_sample = 'no' and (source_of_request is null OR source_of_request like '' OR source_of_request like 'web');
UPDATE form_covid19 SET source_of_request = 'vlsts' WHERE remote_sample = 'yes' and (source_of_request is null OR source_of_request like '' OR source_of_request like 'web' OR source_of_request like 'vlsm');
UPDATE form_covid19 SET source_of_request = 'app' WHERE (source_of_request like 'api' or app_sample_code is not null);

UPDATE form_hepatitis SET source_of_request = 'vlsm' WHERE remote_sample = 'no' and (source_of_request is null OR source_of_request like '' OR source_of_request like 'web');
UPDATE form_hepatitis SET source_of_request = 'vlsts' WHERE remote_sample = 'yes' and (source_of_request is null OR source_of_request like '' OR source_of_request like 'web' OR source_of_request like 'vlsm');
UPDATE form_hepatitis SET source_of_request = 'app' WHERE (source_of_request like 'api' or app_sample_code is not null);

UPDATE form_tb SET source_of_request = 'vlsm' WHERE remote_sample = 'no' and (source_of_request is null OR source_of_request like '' OR source_of_request like 'web');
UPDATE form_tb SET source_of_request = 'vlsts' WHERE remote_sample = 'yes' and (source_of_request is null OR source_of_request like '' OR source_of_request like 'web' OR source_of_request like 'vlsm');
UPDATE form_tb SET source_of_request = 'app' WHERE (source_of_request like 'api' or app_sample_code is not null);

-- Thana 22-Mar-2022
ALTER TABLE `import_config` ADD `approved_by` JSON NULL DEFAULT NULL AFTER `low_vl_result_text`,
ADD `reviewed_by` JSON NULL DEFAULT NULL AFTER `approved_by`;

-- Amit 25-Mar-2022
ALTER TABLE `province_details` ADD UNIQUE(`province_name`);

-- Amit 29-Mar-2022 version 4.4.9
UPDATE `system_config` SET `value` = '4.4.9' WHERE `system_config`.`name` = 'sc_version';


-- Amit 31-Mar-2022
ALTER TABLE `form_hepatitis` CHANGE `is_sample_rejected` `is_sample_rejected` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;


-- Thana 01-04-2022
CREATE TABLE `r_covid19_qc_testkits` (
  `testkit_id` int(11) NOT NULL AUTO_INCREMENT,
  `testkit_name` varchar(256) DEFAULT NULL,
  `no_of_tests` int(11) DEFAULT NULL,
  `labels_and_expected_results` json DEFAULT NULL,
  `status` varchar(256) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`testkit_id`),
  UNIQUE KEY `testkit_name` (`testkit_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `qc_covid19` (
  `qc_id` int(11) NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(500) NOT NULL,
  `qc_code` varchar(256) NOT NULL,
  `qc_code_key` int(11) NOT NULL,
  `testkit` int(11) NOT NULL,
  `lot_no` varchar(256) NOT NULL,
  `expiry_date` date NOT NULL,
  `lab_id` int(11) NOT NULL,
  `tested_by` text NOT NULL,
  `qc_tested_datetime` datetime NOT NULL,
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`qc_id`),
  UNIQUE KEY `qc_code` (`qc_code`),
  UNIQUE KEY `unique_id` (`unique_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `qc_covid19_tests` (
  `qc_test_id` int(11) NOT NULL AUTO_INCREMENT,
  `qc_id` int(11) NOT NULL,
  `test_label` varchar(256) NOT NULL,
  `test_result` varchar(256) NOT NULL,
  PRIMARY KEY (`qc_test_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'covid-19-results', 'covid-19-qc-data.php', 'Covid-19 QC Data'), (NULL, 'covid-19-results', 'add-covid-19-qc-data.php', 'Add Covid-19 QC Data'), (NULL, 'covid-19-results', 'edit-covid-19-qc-data.php', 'Edit Covid-19 QC Data');

-- Amit 31-Mar-2022
ALTER TABLE `vl_request_form` CHANGE `is_sample_rejected` `is_sample_rejected` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `is_sample_rejected` `is_sample_rejected` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_covid19` CHANGE `is_sample_rejected` `is_sample_rejected` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_hepatitis` CHANGE `is_sample_rejected` `is_sample_rejected` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

-- Amit 03-Apr-2022
ALTER TABLE `vl_request_form` CHANGE `serial_no` `external_sample_code` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `vl_request_form` CHANGE `patient_art_no` `patient_art_no` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `vl_request_form` CHANGE `source_data_dump` `source_data_dump` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `result_sent_to_source` `result_sent_to_source` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'pending', CHANGE `ward` `ward` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `art_cd_cells` `art_cd_cells` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `who_clinical_stage` `who_clinical_stage` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `failed_test_tech` `failed_test_tech` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `failed_vl_result` `failed_vl_result` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `failed_batch_quality` `failed_batch_quality` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `failed_sample_test_quality` `failed_sample_test_quality` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `failed_batch_id` `failed_batch_id` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;


-- Amit 06-Apr-2022
ALTER TABLE `qc_covid19` ADD `testing_point` VARCHAR(256) NULL DEFAULT NULL AFTER `lab_id`;
ALTER TABLE `qc_covid19` ADD `qc_received_datetime` DATETIME NULL DEFAULT NULL AFTER `testing_point`;
-- ALTER TABLE `qc_covid19` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `created_on`;



-- Amit 07-Apr-2022
RENAME TABLE `vl_request_form` TO `form_vl`;
RENAME TABLE `eid_form` TO `form_eid`;
ALTER TABLE `form_eid` DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;
ALTER TABLE `form_vl` DEFAULT CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;

RENAME TABLE `vl_user_facility_map` TO `user_facility_map`;
RENAME TABLE `vl_facility_map` TO `testing_lab_health_facilities_map`;
RENAME TABLE `contact_notes_details` TO `vl_contact_notes`;


ALTER TABLE `form_vl` DROP INDEX `status`;
ALTER TABLE `form_vl` CHANGE `result_approved_by` `result_approved_by` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_vl` ADD INDEX(`result_approved_by`);
ALTER TABLE `form_vl` CHANGE `result_reviewed_by` `result_reviewed_by` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_vl` ADD INDEX(`result_reviewed_by`);

ALTER TABLE `form_generic` ADD `is_encrypted` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no' AFTER `patient_id`;