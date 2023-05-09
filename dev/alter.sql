-- Version 3.0 ---------- Pal 21-Mar-2017
-- Version 3.2 ---------- Amit 29-Mar-2017

ALTER TABLE `vl_request_form` ADD `consultation` VARCHAR(255) NULL DEFAULT NULL AFTER `manual_result_entry`, ADD `first_line` VARCHAR(255) NULL DEFAULT NULL AFTER `consultation`, ADD `second_line` VARCHAR(255) NULL DEFAULT NULL AFTER `first_line`, ADD `first_viral_load` VARCHAR(255) NULL DEFAULT NULL AFTER `second_line`, ADD `collection_type` VARCHAR(255) NULL DEFAULT NULL AFTER `first_viral_load`, ADD `sample_processed` VARCHAR(255) NULL DEFAULT NULL AFTER `collection_type`;
ALTER TABLE `vl_request_form` ADD `vl_result_category` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_processed`;
ALTER TABLE `vl_request_form` ADD `sample_received_at_hub_datetime` DATETIME NULL DEFAULT NULL AFTER `vl_focal_person_phone_number`;

UPDATE `privileges` SET `privilege_name` = 'vlWeeklyReport.php' WHERE `privilege_name` = 'monthlyReport.php'; 

-- Version 3.3 ---------- Amit 06-May-2018

-- Version 3.4 ---------- Amit 23-May-2018

-- Version 3.5 ---------- Amit 08-Jun-2018



UPDATE `global_config` SET `value` = '5' WHERE `global_config`.`name` = 'data_sync_interval'; 
-- saravanan 12-jun-2018
INSERT INTO `global_config` (`display_name`, `name`, `value`) VALUES ('Edit Profile', 'edit_profile', 'no');

-- Version 3.7 ---------- Amit 24-Jul-2018

-- saravanan 26-july-2018

ALTER TABLE `vl_request_form` ADD `cphl_vl_result` VARCHAR(255) NULL DEFAULT NULL AFTER `vl_test_platform`; -- for png form

ALTER TABLE `temp_sample_import` CHANGE `sample_review_by` `sample_review_by` VARCHAR(255) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;


-- saravanana 02-Aug-2018

  ALTER TABLE `vl_request_form` CHANGE `remote_sample_code_key` `remote_sample_code_key` INT NULL DEFAULT NULL, CHANGE `sample_code_key` `sample_code_key` INT NULL DEFAULT NULL;

-- saravanan 16-aug-2018
ALTER TABLE `vl_request_form` ADD `province_id` VARCHAR(255) NULL DEFAULT NULL AFTER `facility_id`;
-- Version 3.8 ---------- Amit 28-Aug-2018

-- saravanan 03-sep-2018
ALTER TABLE `vl_request_form` ADD `reason_for_vl_testing_other` VARCHAR(255) NULL DEFAULT NULL AFTER `reason_for_vl_testing`;


-- Amit 05 Sep 2018

UPDATE vl_request_form INNER JOIN r_vl_test_reasons
    ON vl_request_form.reason_for_vl_testing = r_vl_test_reasons.test_reason_name
SET vl_request_form.reason_for_vl_testing = r_vl_test_reasons.test_reason_id;


-- Version 3.9 ---------- Amit 14-Sep-2018

-- Amit 25 Sep 2018
ALTER TABLE `activity_log` CHANGE `action` `action` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

-- Version 3.9.5 ------- saravanan 09-oct-2018

-- saravanan 25-oct-2018
ALTER TABLE `facility_details` ADD `facility_logo` VARCHAR(255) NULL DEFAULT NULL AFTER `facility_type`;

-- saravanan 30-oct-2018
INSERT INTO `resources` (`resource_id`, `resource_name`, `display_name`) VALUES (NULL, 'move-samples', 'Move Samples');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '24', 'sampleList.php', 'Access'), (NULL, '24', 'addSampleList.php', 'Add Samples List');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '24', 'editSampleList.php', 'Edit Sample List');

-- Version 3.9.6 ------- saravanan 01-nov-2018
ALTER TABLE `facility_details` ADD `header_text` VARCHAR(255) NULL DEFAULT NULL AFTER `facility_logo`;


-- Version 3.9.7 ---------- Amit 16-Nov-2018

-- Version 3.9.8 ---------- Amit 11-Dec-2018

-- Version 3.9.9 ---------- Amit 11-Jan-2018

-- saravanan 21-jan-2019
ALTER TABLE `activity_log` ADD `ip_address` VARCHAR(255) NULL DEFAULT NULL AFTER `date_time`;
UPDATE vl_request_form SET result_status = 7 WHERE result_status=6 AND (result is NOT null AND result != '');


-- Version 3.10 ---------- Amit 11-Feb-2018

-- Version 3.10.1 ---------- Saravanan 14-Feb-2018

-- Version 3.10.2 ---------- Saravanan 16-Feb-2018

-- Version 3.10.3 ---------- Amit 18-Feb-2018

UPDATE `r_sample_status` SET `status_name` = 'Sample Registered at VL Lab' WHERE `status_id` = 6;
UPDATE `r_sample_status` SET `status_name` = 'Awaiting Approval' WHERE `r_sample_status`.`status_id` = 8;

-- Version 3.10.4 ---------- Amit 24-Feb-2018


ALTER TABLE `vl_request_form` ADD INDEX(`sample_collection_date`);
ALTER TABLE `vl_request_form` ADD INDEX(`sample_tested_datetime`);
ALTER TABLE `vl_request_form` ADD INDEX(`lab_id`);
ALTER TABLE `vl_request_form` ADD INDEX(`result_status`);


-- Version 3.10.5 ---------- Amit 28-Feb-2018
-- Version 3.10.6 ---------- Amit 04-Mar-2019
ALTER TABLE `vl_request_form` ADD `sample_registered_at_lab` DATETIME NULL AFTER `lab_phone_number`;
UPDATE `vl_request_form` set sample_registered_at_lab = request_created_datetime where sample_registered_at_lab is NULL;
UPDATE `vl_request_form` set sample_received_at_vl_lab_datetime = request_created_datetime where sample_received_at_vl_lab_datetime is NULL;


CREATE TABLE `move_samples` (
  `move_sample_id` int(11) NOT NULL,
  `moved_from_lab_id` int(11) NOT NULL,
  `moved_to_lab_id` int(11) NOT NULL,
  `moved_on` date DEFAULT NULL,
  `moved_by` varchar(255) DEFAULT NULL,
  `reason_for_moving` text,
  `move_approved_by` varchar(255) DEFAULT NULL,
  `list_request_created_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `move_samples_map` (
  `sample_map_id` int(11) NOT NULL,
  `move_sample_id` int(11) NOT NULL,
  `vl_sample_id` int(11) NOT NULL,
  `move_sync_status` varchar(255) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


ALTER TABLE `move_samples`
  ADD PRIMARY KEY (`move_sample_id`);

ALTER TABLE `move_samples_map`
  ADD PRIMARY KEY (`sample_map_id`);

ALTER TABLE `move_samples`
  MODIFY `move_sample_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;

ALTER TABLE `move_samples_map`
  MODIFY `sample_map_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;



-- Version 3.10.7 ---------- Amit 23-Mar-2019



INSERT INTO `global_config` (`display_name`, `name`, `value`) VALUES ('Low Viral Load (text results)', 'low_vl_text_results', 'Target Not Detected, TND, < 20, < 40');
ALTER TABLE `import_config` ADD `low_vl_result_text` TEXT NULL DEFAULT NULL AFTER `number_of_calibrators`;



ALTER TABLE `resources` ADD `module` VARCHAR(255) NOT NULL AFTER `resource_id`;
UPDATE `resources` set `module` = 'vl';


-- Version 3.10.8 ---------- Amit 17-Apr-2019


CREATE TABLE `result_import_stats` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `imported_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `no_of_results_imported` int(11) DEFAULT NULL,
 `imported_by` varchar(1000) DEFAULT NULL,
 `import_mode` varchar(500) DEFAULT NULL,
 PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `global_config` (`display_name`, `name`, `value`) VALUES ('Barcode Format', 'barcode_format', 'C39');
		
CREATE TABLE `eid_form` (
  `eid_id` INTEGER NOT NULL AUTO_INCREMENT,
  `vlsm_instance_id` VARCHAR(255) NULL DEFAULT NULL,
  `vlsm_country_id` INTEGER NOT NULL,
  `sample_code_key` INTEGER NOT NULL,
  `sample_code_format` VARCHAR(255) NULL DEFAULT NULL,
  `sample_code` VARCHAR(255) NULL DEFAULT NULL,
  `remote_sample` VARCHAR(255) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` INTEGER NULL DEFAULT NULL,
  `remote_sample_code_format` VARCHAR(255) NULL DEFAULT NULL,
  `remote_sample_code` VARCHAR(255) NULL DEFAULT NULL,
  `sample_collection_date` DATETIME NOT NULL,
  `sample_received_at_hub_datetime` DATETIME NOT NULL,
  `sample_received_at_vl_lab_datetime` DATETIME NOT NULL,
  `sample_tested_datetime` DATETIME NULL DEFAULT NULL,
  `funding_source` INTEGER NULL DEFAULT NULL,
  `implementation_partner` INTEGER NULL DEFAULT NULL,
  `is_sample_rejected` VARCHAR(255) NOT NULL DEFAULT 'no',
  `sample_rejection_reason` INTEGER NOT NULL,
  `facility_id` INTEGER NOT NULL,
  `mother_id` VARCHAR(255) NULL DEFAULT NULL,
  `mother_name` VARCHAR(500) NULL DEFAULT NULL,
  `caretaker_phone_number` VARCHAR(255) NULL DEFAULT NULL,
  `caretaker_address` VARCHAR(1000) NULL DEFAULT NULL,
  `mother_dob` DATE NULL DEFAULT NULL,
  `mother_age_in_years` VARCHAR(255) NULL DEFAULT NULL,
  `mother_marital_status` VARCHAR(255) NULL DEFAULT NULL,
  `child_id` VARCHAR(255) NULL DEFAULT NULL,
  `child_name` VARCHAR(255) NULL DEFAULT NULL,
  `child_dob` DATE NULL DEFAULT NULL,
  `child_age` VARCHAR(255) NULL DEFAULT NULL,
  `child_gender` VARCHAR(255) NULL DEFAULT NULL,
  `mother_hiv_status` VARCHAR(255) NULL DEFAULT NULL,
  `mother_treatment` VARCHAR(255) NULL DEFAULT NULL,
  `mother_cd4` VARCHAR(255) NULL DEFAULT NULL,
  `mother_cd4_test_date` DATE NULL DEFAULT NULL,
  `mother_vl_result` VARCHAR(255) NULL DEFAULT NULL,
  `mother_vl_test_date` VARCHAR(255) NULL DEFAULT NULL,
  `child_treatment` VARCHAR(255) NULL DEFAULT NULL,
  `is_infant_receiving_treatment` VARCHAR(255) NULL DEFAULT NULL,
  `has_infant_stopped_breastfeeding` VARCHAR(255) NULL DEFAULT NULL,
  `age_breastfeeding_stopped_in_months` VARCHAR(255) NULL DEFAULT NULL,
  `choice_of_feeding` VARCHAR(255) NULL DEFAULT NULL,
  `is_cotrimoxazole_being_administered_to_the_infant` VARCHAR(255) NULL DEFAULT NULL,
  `sample_requestor_name` VARCHAR(255) NULL DEFAULT NULL,
  `sample_requestor_phone` VARCHAR(255) NULL DEFAULT NULL,
  `specimen_quality` VARCHAR(255) NULL DEFAULT NULL,
  `specimen_type` VARCHAR(255) NULL DEFAULT NULL,
  `last_pcr_id` VARCHAR(255) NULL DEFAULT NULL,
  `last_pcr_date` DATE NULL DEFAULT NULL,
  `reason_for_pcr` INTEGER NULL DEFAULT NULL,
  `rapid_test_performed` VARCHAR(255) NULL DEFAULT NULL,
  `rapid_test_date` DATE NULL DEFAULT NULL,
  `rapid_test_result` VARCHAR(255) NULL DEFAULT NULL,
  `lab_id` INTEGER NULL DEFAULT NULL,
  `lab_technician` VARCHAR(255) NULL DEFAULT NULL,
  `result_status` INTEGER NULL DEFAULT NULL,
  `result` VARCHAR(255) NOT NULL,
  `sample_printed_datetime` DATETIME NULL DEFAULT NULL,
  `created_on` DATETIME NULL DEFAULT NULL,
  `created_by` VARCHAR(255) NOT NULL,
  `last_modified_datetime` DATETIME NOT NULL,
  `last_modified_by` VARCHAR(255) NOT NULL,
  `data_sync` INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (`eid_id`)
);

-- ---
-- Foreign Keys 
-- ---


-- ---
-- Table Properties
-- ---

-- ALTER TABLE `eid_form` ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;


ALTER TABLE `vl_imported_controls` CHANGE `result_reviewed_by` `result_reviewed_by` VARCHAR(1000) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;

-- Version 3.10.9 ---------- Amit 6-May-2019

ALTER TABLE `user_details` ADD `user_signature` TEXT NULL DEFAULT NULL AFTER `role_id`;

INSERT INTO `resources` (`resource_id`, `module`, `resource_name`, `display_name`) VALUES (25, 'eid', 'eid-requests', 'EID Request Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '25', 'eid-add-request.php', 'Add Request');

ALTER TABLE `eid_form` CHANGE `created_on` `request_created_on` DATETIME NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `created_by` `request_created_by` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;



ALTER TABLE `eid_form` CHANGE `sample_received_at_hub_datetime` `sample_received_at_hub_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `sample_received_at_vl_lab_datetime` `sample_received_at_vl_lab_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `sample_rejection_reason` `sample_rejection_reason` INT(11) NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `facility_id` `facility_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `result` `result` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `request_created_by` `request_created_by` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `last_modified_datetime` `last_modified_datetime` DATETIME NULL DEFAULT NULL, CHANGE `last_modified_by` `last_modified_by` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;

ALTER TABLE `eid_form` ADD `mother_treatment_other` VARCHAR(1000) NULL DEFAULT NULL AFTER `mother_treatment`;
ALTER TABLE `eid_form` CHANGE `reason_for_pcr` `reason_for_pcr` VARCHAR(500) NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `sample_rejection_reason` `sample_rejection_reason` VARCHAR(500) NULL DEFAULT NULL;


INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '25', 'eid-edit-request.php', 'Edit Request');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '25', 'eid-requests.php', 'View Requests');

INSERT INTO `resources` (`resource_id`, `module`, `resource_name`, `display_name`) VALUES (26, 'eid', 'eid-batches', 'EID Batch Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '26', 'eid-batches.php', 'View Batches');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '26', 'eid-add-batch.php', 'Add Batch');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '26', 'eid-edit-batch.php', 'Edit Batch');


ALTER TABLE `batch_details` ADD `test_type` VARCHAR(255) NULL DEFAULT NULL AFTER `batch_code_key`;
ALTER TABLE `eid_form` ADD `sample_batch_id` INT NULL DEFAULT NULL AFTER `last_modified_by`;
ALTER TABLE `eid_form` ADD `lot_number` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_batch_id`;
ALTER TABLE `eid_form` ADD `lot_expiration_date` DATE NULL DEFAULT NULL AFTER `lot_number`;
ALTER TABLE `eid_form` ADD `result_reviewed_datetime` DATETIME NULL DEFAULT NULL AFTER `result`;
ALTER TABLE `eid_form` ADD `result_reviewed_by` VARCHAR(255) NULL DEFAULT NULL AFTER `result_reviewed_datetime`;
ALTER TABLE `eid_form` ADD `result_approved_datetime` DATETIME NULL DEFAULT NULL AFTER `result_reviewed_by`;
ALTER TABLE `eid_form` ADD `result_approved_by` VARCHAR(255) NULL DEFAULT NULL AFTER `result_approved_datetime`;


-- Version 3.11 ---------- Amit 28-May-2019


-- Thanaseelan 30-May-2019 For Betwwn Recency and VLSM API integration while Assay outcome is Assay Recent(VL lab test request)
ALTER TABLE `vl_request_form` ADD `recency_vl` VARCHAR(255) NOT NULL DEFAULT 'no' AFTER `remote_sample`;

-- Thanaseelan 03-May-2019 Create vl test resilt data send to recnecy or not status
ALTER TABLE `vl_request_form` ADD `recency_sync` INT(11) NULL DEFAULT NULL AFTER `recency_vl`;
ALTER TABLE `vl_request_form` CHANGE `recency_sync` `recency_sync` INT(11) NULL DEFAULT '0';


INSERT INTO `resources` (`resource_id`, `module`, `resource_name`, `display_name`) VALUES (27, 'eid', 'eid-results', 'EID Result Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '27', 'eid-manual-results.php', 'Enter Result Manually');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '27', 'eid-import-result.php', 'Import Result File');


-- Amit 17 June 2019

UPDATE `privileges` SET `privilege_name` = 'eid-manual-results.php' WHERE `privilege_name` = 'eid-results.php';
UPDATE `resources` SET `display_name` = 'Import Result' WHERE `resource_id` = 8;
UPDATE `privileges` SET `display_name` = 'Import Result from File' WHERE `privilege_id` = 19;
delete from roles_privileges_map where privilege_id = 53; 
delete from privileges where privilege_id = 53;

delete from roles_privileges_map where privilege_id = 54; 
delete from privileges where privilege_id = 54;


ALTER TABLE `eid_form` ADD `import_machine_file_name` VARCHAR(255) NULL AFTER `result_approved_by`;
ALTER TABLE `eid_form` ADD `sample_registered_at_lab` DATETIME NULL DEFAULT NULL AFTER `request_created_by`;
ALTER TABLE `temp_sample_import` ADD `module` VARCHAR(255) NULL DEFAULT NULL AFTER `temp_sample_id`;
ALTER TABLE `temp_sample_import` ADD `imported_by` VARCHAR(255) NOT NULL AFTER `sample_review_by`;


-- Amit 21 Jun 2019



CREATE TABLE `r_eid_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `r_eid_sample_rejection_reasons`
--
ALTER TABLE `r_eid_sample_rejection_reasons`
  ADD PRIMARY KEY (`rejection_reason_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `r_eid_sample_rejection_reasons`
--
ALTER TABLE `r_eid_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT;



CREATE TABLE `eid_imported_controls` (
 `control_id` int(11) NOT NULL AUTO_INCREMENT,
 `control_code` varchar(255) NOT NULL,
 `lab_id` int(11) DEFAULT NULL,
 `batch_id` int(11) DEFAULT NULL,
 `control_type` varchar(255) DEFAULT NULL,
 `lot_number` varchar(255) DEFAULT NULL,
 `lot_expiration_date` date DEFAULT NULL,
 `sample_tested_datetime` datetime DEFAULT NULL,
 `is_sample_rejected` varchar(255) DEFAULT NULL,
 `reason_for_sample_rejection` varchar(255) DEFAULT NULL,
 `result` varchar(255) DEFAULT NULL,
 `approver_comments` varchar(255) DEFAULT NULL,
 `result_approved_by` varchar(255) DEFAULT NULL,
 `result_approved_datetime` datetime DEFAULT NULL,
 `result_reviewed_by` varchar(1000) DEFAULT NULL,
 `result_reviewed_datetime` datetime DEFAULT NULL,
 `status` varchar(255) DEFAULT NULL,
 `vlsm_country_id` varchar(10) DEFAULT NULL,
 `file_name` varchar(255) DEFAULT NULL,
 `imported_date_time` datetime DEFAULT NULL,
 PRIMARY KEY (`control_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;  



ALTER TABLE `eid_form` ADD `eid_test_platform` VARCHAR(255) NULL DEFAULT NULL AFTER `lab_technician`;
ALTER TABLE `eid_form` ADD `result_dispatched_datetime` DATETIME NULL DEFAULT NULL AFTER `result_approved_by`;
ALTER TABLE `eid_form` ADD `approver_comments` VARCHAR(1000) NULL DEFAULT NULL AFTER `result_approved_by`;
ALTER TABLE `eid_form` ADD `import_machine_name` VARCHAR(255) NULL DEFAULT NULL AFTER `result_dispatched_datetime`;
ALTER TABLE `eid_form` ADD `manual_result_entry` VARCHAR(255) NULL DEFAULT 'no' AFTER `result_dispatched_datetime`;
ALTER TABLE `eid_form` CHANGE `request_created_on` `request_created_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `sample_rejection_reason` `reason_for_sample_rejection` VARCHAR(500) CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL;


INSERT INTO `global_config` (`display_name`,`name`, `value`) VALUES ('EID Positive','eid_positive', 'Positive');
INSERT INTO `global_config` (`display_name`,`name`, `value`) VALUES ('EID Negative','eid_negative', 'Negative');
INSERT INTO `global_config` (`display_name`,`name`, `value`) VALUES ('EID Indeterminate','eid_indeterminate', 'Indeterminate');

ALTER TABLE `global_config` ADD `status` VARCHAR(255) NOT NULL DEFAULT 'active' AFTER `value`;
UPDATE `global_config` SET `status` = 'inactive' WHERE `global_config`.`name` = 'auto_approval';
UPDATE `global_config` SET `status` = 'inactive' WHERE `global_config`.`name` = 'enable_qr_mechanism';
UPDATE `global_config` SET `status` = 'inactive' WHERE `global_config`.`name` = 'sync_path';


CREATE TABLE `r_eid_results` (
  `result_id` varchar(255) NOT NULL,
  `result` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `r_eid_results`
--

INSERT INTO `r_eid_results` (`result_id`, `result`, `status`, `data_sync`) VALUES
('indeterminate', 'Indeterminate', 'active', 0),
('negative', 'Negative', 'active', 0),
('positive', 'Positive', 'active', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `r_eid_results`
--
ALTER TABLE `r_eid_results`
  ADD PRIMARY KEY (`result_id`);

--

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '27', 'eid-result-status.php', 'Manage Result Status');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '27', 'eid-print-results.php', 'Print Results');

ALTER TABLE `eid_form` CHANGE `sample_printed_datetime` `result_printed_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `implementation_partner` `implementing_partner` INT(11) NULL DEFAULT NULL;


INSERT INTO `resources` (`resource_id`, `module`, `resource_name`, `display_name`) VALUES (28, 'eid', 'eid-management', 'EID Reports');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 28, 'eid-export-data.php', 'Export Data');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 28, 'eid-sample-rejection-report.php', 'Sample Rejection Report');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 28, 'eid-sample-status.php', 'Sample Status Report');

ALTER TABLE `eid_form` ADD `result_mail_datetime` DATETIME NULL DEFAULT NULL AFTER `result_dispatched_datetime`;

UPDATE `resources` SET `module` = 'eid' WHERE `resource_name` = 'eid-requests';
UPDATE `privileges` SET `privilege_name` = 'vl-sample-status.php' WHERE `privilege_name` = 'missingResult.php';


-- Version 3.12 ---------- Amit 25-June-2019 

-- Amit 28 June 2019

INSERT INTO `global_config` (`display_name`, `name`, `value`, `status`) VALUES ('EID Maximum Length', 'eid_max_length', '', 'active'), ('EID Minimum Length', 'eid_min_length', '', 'active'), ('EID Sample Code', 'eid_sample_code', 'MMYY', 'active'), ('EID Sample Code Prefix', 'eid_sample_code_prefix', 'EID', 'active');

-- Amit 04 July 2019

ALTER TABLE `eid_form` ADD `sample_package_id` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_batch_id`;


-- Amit 09 July 2019

ALTER TABLE `package_details` ADD `module` VARCHAR(255) NULL DEFAULT NULL AFTER `package_status`;
UPDATE `package_details` SET module = 'vl' where module is NULL;

-- Version 3.13 ---------- Amit 09 July 2019


-- Amit 19 August 2019
ALTER TABLE `vl_request_form` ADD `vldash_sync` INT NOT NULL DEFAULT '0' AFTER `vl_result_category`;

-- Version 3.14


-- Amit 15 October 2019

ALTER TABLE `eid_form` ADD `mother_surname` VARCHAR(255) NULL DEFAULT NULL AFTER `mother_name`;
ALTER TABLE `eid_form` ADD `child_surname` VARCHAR(255) NULL DEFAULT NULL AFTER `child_name`;
ALTER TABLE `eid_form` ADD `mode_of_delivery` VARCHAR(255) NULL DEFAULT NULL AFTER `mother_hiv_status`;
ALTER TABLE `eid_form` ADD `reason_for_eid_test` INT NULL DEFAULT NULL AFTER `specimen_type`;


CREATE TABLE `r_eid_test_reasons` (
 `test_reason_id` int(11) NOT NULL AUTO_INCREMENT,
 `test_reason_name` varchar(255) DEFAULT NULL,
 `test_reason_status` varchar(45) DEFAULT NULL,
 PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Version 3.15

RENAME TABLE `r_sample_type` TO `r_vl_sample_type`;

CREATE TABLE `r_eid_sample_type` (
 `sample_id` int(11) NOT NULL AUTO_INCREMENT,
 `sample_name` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
 `status` varchar(45) CHARACTER SET latin1 DEFAULT NULL,
 `data_sync` int(11) NOT NULL DEFAULT '0',
 PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `r_eid_sample_type` (`sample_id`, `sample_name`, `status`, `data_sync`) VALUES (NULL, 'DBS', 'active', '0'), (NULL, 'Whole Blood', 'active', '0');
ALTER TABLE `eid_form` ADD `lab_reception_person` VARCHAR(255) NULL DEFAULT NULL AFTER `lab_technician`;
ALTER TABLE `eid_form` ADD `child_treatment_other` VARCHAR(1000) NULL DEFAULT NULL AFTER `child_treatment`;
ALTER TABLE `eid_form` ADD `mother_treatment_initiation_date` DATE NULL DEFAULT NULL AFTER `mother_treatment_other`;
ALTER TABLE `eid_form` ADD `caretaker_contact_consent` VARCHAR(255) NULL DEFAULT NULL AFTER `mother_surname`;



-- Version 3.16 ---- Amit Nov 12 2019


-- Amit 14 Nov 2019

ALTER TABLE `eid_form` ADD `province_id` INT NULL DEFAULT NULL AFTER `facility_id`;


-- Version 3.17 ---- Amit Nov 14 2019

-- Amit 15 Nov 2019

ALTER TABLE `vl_request_form` ADD `sample_package_code` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_package_id`;
ALTER TABLE `eid_form` ADD `sample_package_code` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_package_id`;

UPDATE vl_request_form INNER JOIN package_details
    ON vl_request_form.sample_package_id = package_details.package_id
SET vl_request_form.sample_package_code = package_details.package_code 
WHERE vl_request_form.sample_package_code is NULL;

UPDATE eid_form INNER JOIN package_details
    ON eid_form.sample_package_id = package_details.package_id
SET eid_form.sample_package_code = package_details.package_code
WHERE eid_form.sample_package_code is NULL;

-- Thanaseelan 19-Nov-2019 for Add Samples from Manifest
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '6', 'addSamplesFromManifest.php', 'Add Samples from Manifest');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '25', 'addSamplesFromManifest.php', 'Add Samples from Manifest');

-- Version 3.18 ---- Amit Dec 11 2019

INSERT INTO `r_vl_test_reasons` (`test_reason_id`, `test_reason_name`, `test_reason_status`) VALUES ('9999', 'recency', 'active');

-- Version 3.19 ---- Amit Feb 24 2020


-- Amit 14 Apr 2020

INSERT INTO `resources` (`resource_id`, `module`, `resource_name`, `display_name`) 
          VALUES (29, 'covid-19', 'covid-19-requests', 'Covid-19 Request Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
          VALUES (NULL, '29', 'covid-19-add-request.php', 'Add Request');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
          VALUES (NULL, '29', 'covid-19-edit-request.php', 'Edit Request');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
          VALUES (NULL, '29', 'covid-19-requests.php', 'View Requests');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
              VALUES (NULL, '29', 'covid-19-result-status.php', 'Manage Result Status');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
              VALUES (NULL, '29', 'covid-19-print-results.php', 'Print Results');


INSERT INTO `resources` (`resource_id`, `module`, `resource_name`, `display_name`) 
  VALUES (30, 'covid-19', 'covid-19-batches', 'Covid-19 Batch Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
  VALUES (NULL, 30, 'covid-19-batches.php', 'View Batches');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
  VALUES (NULL, 30, 'covid-19-add-batch.php', 'Add Batch');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
  VALUES (NULL, 30, 'covid-19-edit-batch.php', 'Edit Batch');

  INSERT INTO `resources` (`resource_id`, `module`, `resource_name`, `display_name`) 
          VALUES (31, 'covid-19', 'covid-19-results', 'Covid-19 Result Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
        VALUES (NULL, 31, 'covid-19-manual-results.php', 'Enter Result Manually');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
        VALUES (NULL, 31, 'covid-19-import-result.php', 'Import Result File');




INSERT INTO `resources` (`resource_id`, `module`, `resource_name`, `display_name`) 
          VALUES (32, 'eid', 'covid-19-management', 'Covid-19 Reports');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
        VALUES (NULL, 32, 'covid-19-export-data.php', 'Export Data');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
      VALUES (NULL, 32, 'covid-19-sample-rejection-report.php', 'Sample Rejection Report');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) 
      VALUES (NULL, 32, 'covid-19-sample-status.php', 'Sample Status Report');


INSERT INTO `global_config` (`display_name`, `name`, `value`, `status`) 
      VALUES ('Covid-19 Maximum Length', 'covid19_max_length', '', 'active'), 
      ('Covid-19 Minimum Length', 'covid19_min_length', '', 'active'), 
      ('Covid-19 Sample Code Format', 'covid19_sample_code', 'MMYY', 'active'), 
      ('Covid-19 Sample Code Prefix', 'covid19_sample_code_prefix', 'C19', 'active');


CREATE TABLE `r_covid19_results` (
  `result_id` varchar(255) NOT NULL,
  `result` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT  CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `r_covid19_results` (`result_id`, `result`, `status`, `data_sync`) VALUES
('indeterminate', 'Indeterminate', 'active', 0),
('negative', 'Negative', 'active', 0),
('positive', 'Positive', 'active', 0);

ALTER TABLE `r_covid19_results`
  ADD PRIMARY KEY (`result_id`);



CREATE TABLE `r_covid19_sample_type` (
  `sample_id` int(11) NOT NULL,
  `sample_name` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `status` varchar(45) CHARACTER SET latin1 DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

INSERT INTO `r_covid19_sample_type` (`sample_id`, `sample_name`, `status`, `data_sync`) VALUES
(1, 'Nasopharyngeal and oropharyngeal swab', 'active', 0),
(2, 'Bronchoalveolar lavage', 'active', 0),
(3, 'Endotracheal aspirate', 'active', 0),
(4, 'Nasopharyngeal aspirate', 'active', 0),
(5, 'Nasal wash', 'active', 0),
(6, 'Sputum', 'active', 0),
(7, 'Lung tissue', 'active', 0),
(8, 'Serum', 'active', 0),
(9, 'Whole blood', 'active', 0),
(10, 'Urine', 'active', 0),
(11, 'Stool', 'active', 0);

ALTER TABLE `r_covid19_sample_type`
  ADD PRIMARY KEY (`sample_id`);

ALTER TABLE `r_covid19_sample_type`
  MODIFY `sample_id` int(11) NOT NULL AUTO_INCREMENT;  


CREATE TABLE `form_covid19` (
  `covid19_id` int(11) NOT NULL,
  `vlsm_instance_id` varchar(255) DEFAULT NULL,
  `vlsm_country_id` int(11) NOT NULL,
  `sample_code_key` int(11) NOT NULL,
  `sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code` varchar(255) DEFAULT NULL,
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(255) DEFAULT NULL,
  `remote_sample_code` varchar(255) DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `patient_id` varchar(255) DEFAULT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `patient_surname` varchar(255) DEFAULT NULL,
  `patient_dob` date DEFAULT NULL,
  `patient_age` varchar(255) DEFAULT NULL,
  `patient_gender` varchar(255) DEFAULT NULL,
  `patient_phone_number` varchar(255) DEFAULT NULL,
  `patient_address` varchar(1000) DEFAULT NULL,
  `specimen_type` varchar(255) DEFAULT NULL,
  `is_sample_post_mortem` varchar(255) DEFAULT NULL,
  `priority_status` varchar(255) DEFAULT NULL,
  `date_of_symptom_onset` date DEFAULT NULL,
  `contact_with_confirmed_case` varchar(255) DEFAULT NULL,
  `has_recent_travel_history` varchar(255) DEFAULT NULL,
  `travel_country_names` varchar(255) DEFAULT NULL,
  `travel_return_date` date DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `lab_technician` varchar(255) DEFAULT NULL,
  `lab_reception_person` varchar(255) DEFAULT NULL,
  `covid19_test_platform` varchar(255) DEFAULT NULL,
  `result_status` int(11) DEFAULT NULL,
  `is_sample_rejected` varchar(255) NOT NULL DEFAULT 'no',
  `reason_for_sample_rejection` varchar(500) DEFAULT NULL,
  `result` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_reviewed_by` varchar(255) DEFAULT NULL,
  `result_approved_datetime` datetime DEFAULT NULL,
  `result_approved_by` varchar(255) DEFAULT NULL,
  `approver_comments` varchar(1000) DEFAULT NULL,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `manual_result_entry` varchar(255) DEFAULT 'no',
  `import_machine_name` varchar(255) DEFAULT NULL,
  `import_machine_file_name` varchar(255) DEFAULT NULL,
  `result_printed_datetime` datetime DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  `request_created_by` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `sample_batch_id` int(11) DEFAULT NULL,
  `sample_package_id` varchar(255) DEFAULT NULL,
  `sample_package_code` varchar(255) DEFAULT NULL,
  `lot_number` varchar(255) DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` varchar(255) CHARACTER SET utf8 DEFAULT NULL,  
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;


ALTER TABLE `form_covid19`
  ADD PRIMARY KEY (`covid19_id`);

ALTER TABLE `form_covid19`
  MODIFY `covid19_id` int(11) NOT NULL AUTO_INCREMENT;

-- REJECTION REASONS TABLE  

CREATE TABLE `r_covid19_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;


INSERT INTO `r_covid19_sample_rejection_reasons` (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`, `rejection_reason_status`, `rejection_reason_code`, `updated_datetime`, `data_sync`) VALUES
(1, 'Poorly labelled specimen', 'general', 'active', 'Gen_PLSP', '2019-12-17 12:44:29', 0),
(2, 'Mismatched sample and form labeling', 'general', 'active', 'Gen_MMSP', '2019-12-17 12:44:29', 0),
(3, 'Missing labels on container or tracking form', 'general', 'active', 'Gen_MLTS', '2019-12-17 12:44:29', 0),
(4, 'Sample without request forms/Tracking forms', 'general', 'active', 'Gen_SMRT', '2019-12-17 12:44:29', 0),
(5, 'Name/Information of requester is missing', 'general', 'active', 'Gen_NIRM', '2019-12-17 12:44:29', 0),
(6, 'Missing information on request form - Age', 'general', 'active', 'Gen_MIRA', '2019-12-17 12:44:29', 0),
(7, 'Missing information on request form - Sex', 'general', 'active', 'Gen_MIRS', '2019-12-17 12:44:29', 0),
(8, 'Missing information on request form - Sample Collection Date', 'general', 'active', 'Gen_MIRD', '2019-12-17 12:44:29', 0),
(9, 'Missing information on request form - ART No', 'general', 'active', 'Gen_MIAN', '2019-12-17 12:44:29', 0),
(10, 'Inappropriate specimen packing', 'general', 'active', 'Gen_ISPK', '2019-12-17 12:44:29', 0),
(11, 'Inappropriate specimen for test request', 'general', 'active', 'Gen_ISTR', '2019-12-17 12:44:29', 0),
(12, 'Form received without Sample', 'general', 'active', 'Gen_NoSample', '2019-12-17 12:44:29', 0),
(13, 'VL Machine Flag', 'testing', 'active', 'FLG_', '2019-12-17 12:44:29', 0),
(14, 'CNTRL_FAIL', 'testing', 'active', 'FLG_AL00', '2019-12-17 12:44:29', 0),
(15, 'SYS_ERROR', 'testing', 'active', 'FLG_TM00', '2019-12-17 12:44:29', 0),
(16, 'A/D_ABORT', 'testing', 'active', 'FLG_TM17', '2019-12-17 12:44:29', 0),
(17, 'KIT_EXPIRY', 'testing', 'active', 'FLG_TMAP', '2019-12-17 12:44:29', 0),
(18, 'RUN_EXPIRY', 'testing', 'active', 'FLG_TM19', '2019-12-17 12:44:29', 0),
(19, 'DATA_ERROR', 'testing', 'active', 'FLG_TM20', '2019-12-17 12:44:29', 0),
(20, 'NC_INVALID', 'testing', 'active', 'FLG_TM24', '2019-12-17 12:44:29', 0),
(21, 'LPCINVALID', 'testing', 'active', 'FLG_TM25', '2019-12-17 12:44:29', 0),
(22, 'MPCINVALID', 'testing', 'active', 'FLG_TM26', '2019-12-17 12:44:29', 0),
(23, 'HPCINVALID', 'testing', 'active', 'FLG_TM27', '2019-12-17 12:44:29', 0),
(24, 'S_INVALID', 'testing', 'active', 'FLG_TM29', '2019-12-17 12:44:29', 0),
(25, 'MATH_ERROR', 'testing', 'active', 'FLG_TM31', '2019-12-17 12:44:29', 0),
(26, 'PRECHECK', 'testing', 'active', 'FLG_TM44 ', '2019-12-17 12:44:29', 0),
(27, 'QS_INVALID', 'testing', 'active', 'FLG_TM50', '2019-12-17 12:44:29', 0),
(28, 'POSTCHECK', 'testing', 'active', 'FLG_TM51', '2019-12-17 12:44:29', 0),
(29, 'REAG_ERROR', 'testing', 'active', 'FLG_AP02 ', '2019-12-17 12:44:29', 0),
(30, 'NO_SAMPLE', 'testing', 'active', 'FLG_AP12', '2019-12-17 12:44:29', 0),
(31, 'DISP_ERROR', 'testing', 'active', 'FLG_AP13 ', '2019-12-17 12:44:29', 0),
(32, 'TEMP_RANGE', 'testing', 'active', 'FLG_AP19 ', '2019-12-17 12:44:29', 0),
(33, 'PREP_ABORT', 'testing', 'active', 'FLG_AP24', '2019-12-17 12:44:29', 0),
(34, 'SAMPLECLOT', 'testing', 'active', 'FLG_AP25', '2019-12-17 12:44:29', 0);


ALTER TABLE `r_covid19_sample_rejection_reasons`
  ADD PRIMARY KEY (`rejection_reason_id`);


ALTER TABLE `r_covid19_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;


CREATE TABLE `covid19_imported_controls` (
 `control_id` int(11) NOT NULL AUTO_INCREMENT,
 `control_code` varchar(255) NOT NULL,
 `lab_id` int(11) DEFAULT NULL,
 `batch_id` int(11) DEFAULT NULL,
 `control_type` varchar(255) DEFAULT NULL,
 `lot_number` varchar(255) DEFAULT NULL,
 `lot_expiration_date` date DEFAULT NULL,
 `sample_tested_datetime` datetime DEFAULT NULL,
 `is_sample_rejected` varchar(255) DEFAULT NULL,
 `reason_for_sample_rejection` varchar(255) DEFAULT NULL,
 `result` varchar(255) DEFAULT NULL,
 `approver_comments` varchar(255) DEFAULT NULL,
 `result_approved_by` varchar(255) DEFAULT NULL,
 `result_approved_datetime` datetime DEFAULT NULL,
 `result_reviewed_by` varchar(1000) DEFAULT NULL,
 `result_reviewed_datetime` datetime DEFAULT NULL,
 `status` varchar(255) DEFAULT NULL,
 `vlsm_country_id` varchar(10) DEFAULT NULL,
 `file_name` varchar(255) DEFAULT NULL,
 `imported_date_time` datetime DEFAULT NULL,
 PRIMARY KEY (`control_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci; 

ALTER TABLE `form_covid19` ADD `is_result_mail_sent` VARCHAR(255) NULL DEFAULT 'no' AFTER `lot_expiration_date`;

-- ------------------------------------------------------------
-- Version 3.20 ---- Amit April 22 2020
-- ------------------------------------------------------------
-- Thanaseelan April 27, 2020

CREATE TABLE `import_config_controls` (
 `test_type` varchar(255) NOT NULL,
 `config_id` int(11) NOT NULL,
 `number_of_in_house_controls` int(11) DEFAULT NULL,
 `number_of_manufacturer_controls` int(11) DEFAULT NULL,
 `number_of_calibrators` int(11) DEFAULT NULL,
 PRIMARY KEY (`test_type`,`config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
--  Thanaseelan April 28, 2020
-- ALTER TABLE `import_config` DROP `number_of_in_house_controls`, DROP `number_of_manufacturer_controls`, DROP `number_of_calibrators`;

-- Amit April 30, 2020
ALTER TABLE `form_covid19` CHANGE `sample_code_key` `sample_code_key` INT(11) NULL;
-- Thanaseelan May 7, 2020
ALTER TABLE `form_covid19` ADD `patient_province` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_address`, ADD `patient_district` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_province`;
-- From Amit May 7, 2020
CREATE TABLE `covid19_tests` (
 `test_id` int(11) NOT NULL AUTO_INCREMENT,
 `covid19_id` int(11) NOT NULL,
 `test_name` varchar(500) NOT NULL,
 `sample_tested_datetime` datetime NOT NULL,
 `result` varchar(500) NOT NULL,
 PRIMARY KEY (`test_id`),
 KEY `covid19_id` (`covid19_id`),
 CONSTRAINT `covid19_tests_ibfk_1` FOREIGN KEY (`covid19_id`) REFERENCES `form_covid19` (`covid19_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `form_covid19` ADD `is_result_authorised` VARCHAR(255) NULL DEFAULT NULL AFTER `result`, ADD `authorized_by` VARCHAR(255) NULL DEFAULT NULL AFTER `is_result_authorised`, ADD `authorized_on` DATE NULL DEFAULT NULL AFTER `authorized_by`;
-- Thanaseelan May 8, 2020
INSERT INTO `global_config` (`display_name`, `name`, `value`, `status`) VALUES ('Covid19 Tests Table in Results Pdf', 'covid19_tests_table_in_results_pdf', 'no', 'active');
-- Thanaseelan May 11, 2020
ALTER TABLE `form_covid19` ADD `reason_for_changing` TEXT NULL DEFAULT NULL AFTER `authorized_on`;

-- ------------------------------------------------------------
-- Version 3.21 ---- Amit April 22 2020
-- ------------------------------------------------------------



-- Amit 24 May, 2020




CREATE TABLE `r_covid19_test_reasons` (
 `test_reason_id` int(11) NOT NULL AUTO_INCREMENT,
 `test_reason_name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
 `test_reason_status` varchar(45) CHARACTER SET utf8 DEFAULT NULL,
 PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


CREATE TABLE `r_covid19_comorbidities` (
 `comorbidity_id` int(11) NOT NULL AUTO_INCREMENT,
 `comorbidity_name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
 `comorbidity_status` varchar(45) CHARACTER SET utf8 DEFAULT NULL,
 PRIMARY KEY (`comorbidity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `r_covid19_symptoms` (
 `symptom_id` int(11) NOT NULL AUTO_INCREMENT,
 `symptom_name` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
 `symptom_status` varchar(45) CHARACTER SET utf8 DEFAULT NULL,
 PRIMARY KEY (`symptom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `form_covid19` ADD `patient_occupation` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_phone_number`;
ALTER TABLE `form_covid19` ADD `patient_nationality` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_phone_number`;
-- ALTER TABLE `form_covid19` ADD `patient_province` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_address`, ADD `patient_district` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_province`;

ALTER TABLE `form_covid19` ADD `flight_airline` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_address`, 
                           ADD `flight_seat_no` VARCHAR(255) NULL DEFAULT NULL AFTER `flight_airline`, 
                           ADD `flight_arrival_datetime` DATETIME NULL DEFAULT NULL AFTER `flight_seat_no`, 
                           ADD `flight_airport_of_departure` VARCHAR(255) NULL DEFAULT NULL AFTER `flight_arrival_datetime`, 
                           ADD `flight_transit` VARCHAR(255) NULL DEFAULT NULL AFTER `flight_airport_of_departure`, 
                           ADD `reason_of_visit` VARCHAR(500) NULL DEFAULT NULL AFTER `flight_transit`;

ALTER TABLE `form_covid19`  ADD `date_of_initial_consultation` DATE NULL DEFAULT NULL  AFTER `date_of_symptom_onset`;

ALTER TABLE `form_covid19` ADD `fever_temp` VARCHAR(255) NULL DEFAULT NULL AFTER `date_of_initial_consultation`;
ALTER TABLE `form_covid19` ADD `close_contacts` TEXT NULL DEFAULT NULL AFTER `fever_temp`;


ALTER TABLE `covid19_tests` ADD `facility_id` INT NULL DEFAULT NULL AFTER `covid19_id`;
ALTER TABLE `form_covid19` ADD `is_sample_collected` VARCHAR(255) NULL DEFAULT NULL AFTER `reason_of_visit`;
ALTER TABLE `form_covid19` ADD `reason_for_covid19_test` INT NULL DEFAULT NULL AFTER `is_sample_collected`;


CREATE TABLE `covid19_patient_symptoms` (
 `form_id` int(11) NOT NULL,
 `symptom_id` int(11) NOT NULL,
 `symptom_detected` varchar(255) NOT NULL, -- yes, no, unknown
 PRIMARY KEY (`form_id`,`symptom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `covid19_patient_comorbidities` (
 `form_id` int(11) NOT NULL,
 `comorbidity_id` int(11) NOT NULL,
 `comorbidity_detected` varchar(255) NOT NULL, -- yes, no, unknown
 PRIMARY KEY (`form_id`,`comorbidity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
-- Thanaseelan 25 May, 2020
INSERT INTO `global_config` (`display_name`, `name`, `value`, `status`) VALUES ('Report Type', 'covid19_report_type', 'standard', 'active');
ALTER TABLE `form_covid19` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
-- Thanaseelan 26 May, 2020
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '29', 'record-final-result.php', 'Record Final Result'), (NULL, '29', 'can-record-confirmatory-tests.php', 'Can Record Confirmatory Tests'), (NULL, '29', 'update-record-confirmatory-tests.php', 'Update Record Confirmatory Tests');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `status`) VALUES ('Positive Confirmatory Tests Required By Central Lab', 'covid19_positive_confirmatory_tests_required_by_central_lab', 'yes', 'active');

-- Amit 27 may, 2020

ALTER TABLE `covid19_patient_symptoms` CHANGE `form_id` `covid19_id` INT(11) NOT NULL;
ALTER TABLE `covid19_patient_comorbidities` CHANGE `form_id` `covid19_id` INT(11) NOT NULL;

-- Amit 28 May, 2020
ALTER TABLE `log_result_updates` ADD `test_type` VARCHAR(244) NULL DEFAULT NULL COMMENT 'vl, eid, covid19' AFTER `vl_sample_id`;


-- ------------------------------------------------------------
-- Version 3.22 ---- Amit May 29 2020
-- ------------------------------------------------------------
-- Thanaseelan 02 Jun, 2020
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '30', 'covid-19-confirmation-manifest.php', 'Covid-19 Confirmation Manifest'), (NULL, '30', 'covid-19-add-confirmation-manifest.php', 'Add New Confirmation Manifest');

ALTER TABLE `form_covid19` ADD `positive_test_manifest_id` INT(11) NULL DEFAULT NULL AFTER `sample_package_code`, ADD `positive_test_manifest_code` VARCHAR(255) NULL DEFAULT NULL AFTER `positive_test_manifest_id`;
CREATE TABLE `covid19_positive_confirmation_manifest` (
 `manifest_id` int NOT NULL AUTO_INCREMENT,
 `manifest_code` varchar(255) NOT NULL,
 `added_by` varchar(255) NOT NULL,
 `manifest_status` varchar(255) DEFAULT NULL,
 `module` varchar(255) DEFAULT NULL,
 `request_created_datetime` datetime DEFAULT NULL,
 PRIMARY KEY (`manifest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Thanaseelan 09 Jun, 2020
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '30', 'generate-confirmation-manifest.php', 'Generate Positive Confirmation Manifest');
-- Thanaseelan 10 Jun, 2020
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '30', 'covid-19-edit-confirmation-manifest.php', 'Edit Positive Confirmation Manifest');

-- Amit 22 June 2020

ALTER TABLE `eid_form` ADD `pcr_test_performed_before` VARCHAR(255) NULL DEFAULT NULL AFTER `reason_for_eid_test`;
-- Thana 16 Jul, 2020
-- ALTER TABLE `s_vlsm_instance` DROP `last_vldash_sync`;
ALTER TABLE `s_vlsm_instance` ADD `vl_last_dash_sync` DATETIME NULL DEFAULT NULL AFTER `instance_mac_address`, ADD `eid_last_dash_sync` DATETIME NULL DEFAULT NULL AFTER `vl_last_dash_sync`, ADD `covid19_last_dash_sync` DATETIME NULL DEFAULT NULL AFTER `eid_last_dash_sync`;

-- Version 4.0 -- Amit -- 17-July-2020

-- Version 4.0.1 -- Amit -- 17-July-2020

-- Thana 22-Jul-2020
ALTER TABLE `r_covid19_symptoms` ADD `parent_symptom` INT NULL DEFAULT NULL AFTER `symptom_name`;


ALTER TABLE `form_covid19` ADD `is_patient_pregnant` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_gender`, ADD `temperature_measurement_method` VARCHAR(255) NULL DEFAULT NULL AFTER `fever_temp`, ADD `respiratory_rate` INT NULL DEFAULT NULL AFTER `temperature_measurement_method`, ADD `oxygen_saturation` DOUBLE NULL DEFAULT NULL AFTER `respiratory_rate`, ADD `number_of_days_sick` INT NULL DEFAULT NULL AFTER `priority_status`, ADD `medical_history` VARCHAR(255) NULL DEFAULT NULL AFTER `date_of_initial_consultation`, ADD `recent_hospitalization` VARCHAR(255) NULL DEFAULT NULL AFTER `medical_history`, ADD `patient_lives_with_children` VARCHAR(255) NULL DEFAULT NULL AFTER `recent_hospitalization`, ADD `patient_cares_for_children` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_lives_with_children`, ADD `sample_condition` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_received_at_vl_lab_datetime`;
-- Thana 27-Jul-2020
ALTER TABLE `r_covid19_test_reasons` ADD `parent_reason` INT(11) NULL DEFAULT NULL AFTER `test_reason_name`;


-- Version 4.0.2 -- Amit -- 27-July-2020


  -- Amit 28-Jul-2020
  ALTER TABLE `vl_request_form` ADD `remote_sample_code_format` VARCHAR(255) NULL DEFAULT NULL AFTER `remote_sample_code_key`;
  UPDATE `vl_request_form` SET serial_no = null;


-- Version 4.0.3 -- Amit -- 28-July-2020

INSERT INTO `system_config` (`display_name`, `name`, `value`) VALUES ('Version', 'version', '4.0.3');
-- Thana 04-Aug-2020
ALTER TABLE `form_covid19` ADD `test_number` INT(11) NULL DEFAULT NULL AFTER `sample_code`;
-- Thana 05-Aug-2020
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '28', 'eid-clinic-report.php', 'EID Clinic Reports');

-- Amit 06 Aug 2020
UPDATE `global_config` SET `display_name` = 'Do you want to show emoticons on the result pdf?' WHERE `global_config`.`name` = 'show_smiley';



UPDATE `system_config` SET `value` = '4.0.4' WHERE `system_config`.`name` = 'version';
-- Version 4.0.4 -- Amit -- 06-Aug-2020

-- Thana 07-Aug-2020
ALTER TABLE `form_covid19` ADD `other_diseases` TEXT NULL DEFAULT NULL AFTER `result`;

-- Amit 13 Aug 2020

ALTER TABLE `r_vl_sample_type` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `r_eid_sample_type` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `r_covid19_sample_type` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `r_covid19_comorbidities` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `comorbidity_status`;
ALTER TABLE `r_covid19_results` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `r_covid19_symptoms` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `symptom_status`;
ALTER TABLE `r_covid19_test_reasons` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `test_reason_status`;



-- Amit 17 August 2020

ALTER TABLE `vl_request_form` ADD `tested_by` VARCHAR(255) NULL DEFAULT NULL AFTER `lot_expiration_date`;
ALTER TABLE `eid_form` ADD `tested_by` VARCHAR(255) NULL DEFAULT NULL AFTER `result`;
-- ALTER TABLE `form_covid19` ADD `final_result_entered_by` VARCHAR(255) NULL DEFAULT NULL AFTER `result`;
ALTER TABLE `covid19_tests` ADD `tested_by` VARCHAR(255) NULL DEFAULT NULL AFTER `test_name`;

CREATE TABLE `covid19_reasons_for_testing` (
  `covid19_id` int NOT NULL,
  `reasons_id` int DEFAULT NULL,
  `reasons_detected` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- Amit 24 August 2020
ALTER TABLE `vl_request_form` ADD INDEX(`last_modified_datetime`);
ALTER TABLE `eid_form` ADD INDEX(`last_modified_datetime`);
ALTER TABLE `form_covid19` ADD INDEX(`last_modified_datetime`);

-- Amit 26 August 2020

ALTER TABLE `r_vl_test_reasons` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `test_reason_status`;
ALTER TABLE `r_eid_test_reasons` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `test_reason_status`;


-- UPDATE facility_details set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_vl_sample_type set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_vl_test_reasons set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_vl_art_regimen set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_vl_sample_rejection_reasons set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_eid_sample_type set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_eid_sample_rejection_reasons set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_covid19_sample_type set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_covid19_sample_rejection_reasons set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_covid19_comorbidities set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_covid19_results set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_covid19_symptoms set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_covid19_test_reasons set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_vl_test_reasons set updated_datetime = CURRENT_TIMESTAMP;
-- UPDATE r_eid_test_reasons set updated_datetime = CURRENT_TIMESTAMP;


UPDATE `system_config` SET `value` = '4.0.5' WHERE `system_config`.`name` = 'version';
-- Version 4.0.5 -- Amit -- 28-Aug-2020


-- Amit 02-Sep-2020

ALTER TABLE `vl_request_form` ADD INDEX( `sample_code_key`);
ALTER TABLE `vl_request_form` ADD INDEX( `remote_sample_code_key`);
ALTER TABLE `eid_form` ADD INDEX( `sample_code_key`);
ALTER TABLE `eid_form` ADD INDEX( `remote_sample_code_key`);
ALTER TABLE `form_covid19` ADD INDEX( `sample_code_key`);
ALTER TABLE `form_covid19` ADD INDEX( `remote_sample_code_key`);


-- Thana 10-Sept-2020

ALTER TABLE `form_covid19` ADD `type_of_test_requested` VARCHAR(255) NULL DEFAULT NULL AFTER `reason_for_covid19_test`;
ALTER TABLE `form_covid19` ADD `patient_city` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_district`;


UPDATE `system_config` SET `value` = '4.1.0' WHERE `system_config`.`name` = 'version';
-- Version 4.1.0 -- Amit -- 28-Aug-2020

-- Thana 14-Sep-2020
ALTER TABLE `global_config` ADD `category` VARCHAR(255) NULL DEFAULT NULL AFTER `value`, ADD `remote_sync_needed` VARCHAR(50) NULL DEFAULT NULL AFTER `category`, ADD `updated_on` DATETIME NULL DEFAULT NULL AFTER `remote_sync_needed`, ADD `updated_by` TEXT NULL DEFAULT NULL AFTER `updated_on`;

-- Amit 15 Sep 2020

CREATE TABLE `global_config_temp` (
  `name` varchar(255) CHARACTER SET latin1 NOT NULL,
  `category` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
  `remote_sync_needed` varchar(50) CHARACTER SET latin1 DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `global_config_temp` (`name`, `category`, `remote_sync_needed`) VALUES
('auto_approval', 'general', 'no'), ('barcode_format', 'general', 'yes'), ('bar_code_printing', 'general', 'no'), ('covid19_indeterminate', 'covid19', 'yes'), ('covid19_max_length', 'covid19', 'yes'),
('covid19_min_length', 'covid19', 'yes'), ('covid19_negative', 'covid19', 'yes'), ('covid19_positive', 'covid19', 'yes'),
('covid19_positive_confirmatory_tests_required_by_central_lab', 'covid19', 'yes'), ('covid19_report_type', 'covid19', 'yes'), ('covid19_sample_code', 'covid19', 'yes'), ('covid19_sample_code_prefix', 'covid19', 'yes'), ('covid19_tests_table_in_results_pdf', 'covid19', 'yes'), ('data_sync_interval', 'general', 'yes'), ('default_time_zone', 'general', 'no'), ('edit_profile', 'general', 'yes'), ('eid_indeterminate', 'eid', 'yes'), ('eid_max_length', 'eid', 'yes'), ('eid_min_length', 'eid', 'yes'), ('eid_negative', 'eid', 'yes'), ('eid_positive', 'eid', 'yes'), ('eid_sample_code', 'eid', 'yes'), ('eid_sample_code_prefix', 'eid', 'yes'), ('enable_qr_mechanism', 'general', 'yes'), ('header', 'general', 'no'), ('h_vl_msg', 'vl', 'yes'), ('import_non_matching_sample', 'general', 'no'), ('instance_type', 'general', 'no'),
('logo', 'general', 'no'), ('low_vl_text_results', 'vl', 'yes'), ('l_vl_msg', 'vl', 'yes'), ('manager_email', 'general', 'no'), ('max_length', 'vl', 'yes'), ('min_length', 'vl', 'yes'), ('patient_name_pdf', 'general', 'yes'), ('r_mandatory_fields', 'vl', 'yes'), ('sample_code', 'vl', 'yes'), ('sample_code_prefix', 'general', 'yes'), ('show_date', 'vl', 'yes'), ('show_smiley', 'general', 'yes'), ('sync_path', 'general', 'no'), ('testing_status', 'vl', 'yes'), ('user_review_approve', 'general', 'no'), ('viral_load_threshold_limit', 'vl', 'yes'), ('vldashboard_url', 'general', 'yes'), ('vl_form', 'general', 'yes');

UPDATE global_config INNER JOIN global_config_temp
    ON global_config_temp.name = global_config.name
SET global_config.category = global_config_temp.category, global_config.remote_sync_needed = global_config_temp.remote_sync_needed;

DROP TABLE global_config_temp;


UPDATE `system_config` SET `value` = '4.1.1' WHERE `system_config`.`name` = 'version';
-- Version 4.1.0 -- Amit -- 16-Sep-2020


-- Amit 20 Sep 2020


CREATE TABLE `health_facilities` (
 `test_type` enum('vl','eid','covid19') NOT NULL,
 `facility_id` int(11) NOT NULL,
 PRIMARY KEY (`test_type`,`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- INSERT INTO health_facilities (SELECT 'vl' AS `test_type`,facility_id FROM facility_details);
-- INSERT INTO health_facilities (SELECT 'eid' AS `test_type`,facility_id FROM facility_details);
-- INSERT INTO health_facilities (SELECT 'covid19' AS `test_type`,facility_id FROM facility_details);

CREATE TABLE `testing_labs` (
 `test_type` enum('vl','eid','covid19') NOT NULL,
 `facility_id` int(11) NOT NULL,
 PRIMARY KEY (`test_type`,`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- INSERT INTO testing_labs (SELECT 'vl' AS `test_type`,facility_id FROM facility_details WHERE facility_type=2);
-- INSERT INTO testing_labs (SELECT 'eid' AS `test_type`,facility_id FROM facility_details WHERE facility_type=2);
-- INSERT INTO testing_labs (SELECT 'covid19' AS `test_type`,facility_id FROM facility_details WHERE facility_type=2);

UPDATE `system_config` SET `value` = '4.2.0' WHERE `system_config`.`name` = 'version';
-- Version 4.2.0 -- Amit -- 20-Sep-2020


/* Thana 21-Sep-2020 */
ALTER TABLE `covid19_tests` ADD `testing_platform` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_tested_datetime`;


-- Amit 21 Sep 2020
ALTER TABLE `facility_details` ADD `testing_points` JSON NULL DEFAULT NULL AFTER `facility_type`;
ALTER TABLE `form_covid19` ADD `testing_point` VARCHAR(255) NULL DEFAULT NULL AFTER `lab_id`;

ALTER TABLE `form_covid19` ADD `patient_passport_number` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_nationality`;
-- UPDATE `resources` SET `module` = 'covid-19' WHERE `resources`.`resource_id` = 32;
-- INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '32', 'covid-19-clinic-report.php', 'Covid-19 Clinic Reports');


UPDATE `system_config` SET `value` = '4.2.1' WHERE `system_config`.`name` = 'version';
-- Version 4.2.1 -- Amit -- 23-Sep-2020



-- Amit 24 Sep 2020
ALTER TABLE `import_config` ADD `supported_tests` JSON NULL DEFAULT NULL AFTER `machine_name`;

-- Thana 25 Sep 2020
ALTER TABLE `health_facilities` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `facility_id`;
ALTER TABLE `testing_labs` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `facility_id`;
ALTER TABLE `covid19_reasons_for_testing` ADD `reason_details` TEXT NULL DEFAULT NULL AFTER `reasons_detected`;
ALTER TABLE `covid19_patient_symptoms` ADD `symptom_details` TEXT NULL DEFAULT NULL AFTER `symptom_detected`;

-- Amit 27 Sep 2020

-- -- You may have to uncheck "Enable foreign key checks" checkbox on phpMyAdmin
-- SET FOREIGN_KEY_CHECKS=0;
-- DELETE FROM privileges WHERE privilege_id IN (
--   SELECT calc_id FROM ( SELECT MAX(privilege_id) AS calc_id FROM privileges GROUP BY `resource_id`, `privilege_name` HAVING COUNT(privilege_id) > 1 ) as temp_privileges
-- );
-- SET FOREIGN_KEY_CHECKS=1;

-- DELETE FROM roles_privileges_map WHERE privilege_id not in (select privilege_id from privileges);

-- ALTER TABLE `privileges` ADD UNIQUE( `resource_id`, `privilege_name`);
-- ALTER TABLE `resources` ADD UNIQUE( `module`, `resource_name`);

-- Amit 28-Sep-2020
ALTER TABLE `form_covid19` ADD `external_sample_code` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_covid19` ADD `does_patient_smoke` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_occupation`;


UPDATE `system_config` SET `value` = '4.2.2' WHERE `system_config`.`name` = 'version';
-- Version 4.2.2 -- Amit -- 30-Sep-2020

-- Thana 01-Oct-2020
-- INSERT INTO `resources` (`resource_id`, `module`, `resource_name`, `display_name`) VALUES (NULL, 'covid-19', 'covid-19-reference', 'Covid-19 Reference Management');
-- INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '33', 'covid19-sample-type.php', 'Manage Reference');
CREATE TABLE `countries` (
 `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
 `iso_name` varchar(255) CHARACTER SET utf8 NOT NULL,
 `iso2` varchar(2) COLLATE utf8_bin NOT NULL,
 `iso3` varchar(3) COLLATE utf8_bin NOT NULL,
 `numeric_code` smallint(6) NOT NULL,
 PRIMARY KEY (`id`),
 UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=250 DEFAULT CHARSET=utf8;

INSERT INTO `countries` (`id`, `iso_name`, `iso2`, `iso3`, `numeric_code`) VALUES
(1, 'Afghanistan', 'AF', 'AFG', 4),
(2, 'Aland Islands', 'AX', 'ALA', 248),
(3, 'Albania', 'AL', 'ALB', 8),
(4, 'Algeria', 'DZ', 'DZA', 12),
(5, 'American Samoa', 'AS', 'ASM', 16),
(6, 'Andorra', 'AD', 'AND', 20),
(7, 'Angola', 'AO', 'AGO', 24),
(8, 'Anguilla', 'AI', 'AIA', 660),
(9, 'Antarctica', 'AQ', 'ATA', 10),
(10, 'Antigua and Barbuda', 'AG', 'ATG', 28),
(11, 'Argentina', 'AR', 'ARG', 32),
(12, 'Armenia', 'AM', 'ARM', 51),
(13, 'Aruba', 'AW', 'ABW', 533),
(14, 'Australia', 'AU', 'AUS', 36),
(15, 'Austria', 'AT', 'AUT', 40),
(16, 'Azerbaijan', 'AZ', 'AZE', 31),
(17, 'Bahamas', 'BS', 'BHS', 44),
(18, 'Bahrain', 'BH', 'BHR', 48),
(19, 'Bangladesh', 'BD', 'BGD', 50),
(20, 'Barbados', 'BB', 'BRB', 52),
(21, 'Belarus', 'BY', 'BLR', 112),
(22, 'Belgium', 'BE', 'BEL', 56),
(23, 'Belize', 'BZ', 'BLZ', 84),
(24, 'Benin', 'BJ', 'BEN', 204),
(25, 'Bermuda', 'BM', 'BMU', 60),
(26, 'Bhutan', 'BT', 'BTN', 64),
(27, 'Bolivia, Plurinational State of', 'BO', 'BOL', 68),
(28, 'Bonaire, Sint Eustatius and Saba', 'BQ', 'BES', 535),
(29, 'Bosnia and Herzegovina', 'BA', 'BIH', 70),
(30, 'Botswana', 'BW', 'BWA', 72),
(31, 'Bouvet Island', 'BV', 'BVT', 74),
(32, 'Brazil', 'BR', 'BRA', 76),
(33, 'British Indian Ocean Territory', 'IO', 'IOT', 86),
(34, 'Brunei Darussalam', 'BN', 'BRN', 96),
(35, 'Bulgaria', 'BG', 'BGR', 100),
(36, 'Burkina Faso', 'BF', 'BFA', 854),
(37, 'Burundi', 'BI', 'BDI', 108),
(38, 'Cambodia', 'KH', 'KHM', 116),
(39, 'Cameroon', 'CM', 'CMR', 120),
(40, 'Canada', 'CA', 'CAN', 124),
(41, 'Cape Verde', 'CV', 'CPV', 132),
(42, 'Cayman Islands', 'KY', 'CYM', 136),
(43, 'Central African Republic', 'CF', 'CAF', 140),
(44, 'Chad', 'TD', 'TCD', 148),
(45, 'Chile', 'CL', 'CHL', 152),
(46, 'China', 'CN', 'CHN', 156),
(47, 'Christmas Island', 'CX', 'CXR', 162),
(48, 'Cocos (Keeling) Islands', 'CC', 'CCK', 166),
(49, 'Colombia', 'CO', 'COL', 170),
(50, 'Comoros', 'KM', 'COM', 174),
(51, 'Congo', 'CG', 'COG', 178),
(52, 'Congo, the Democratic Republic of the', 'CD', 'COD', 180),
(53, 'Cook Islands', 'CK', 'COK', 184),
(54, 'Costa Rica', 'CR', 'CRI', 188),
(55, "Cote d\'Ivoire", 'CI', 'CIV', 384),
(56, 'Croatia', 'HR', 'HRV', 191),
(57, 'Cuba', 'CU', 'CUB', 192),
(58, 'Cura', 'CW', 'CUW', 531),
(59, 'Cyprus', 'CY', 'CYP', 196),
(60, 'Czech Republic', 'CZ', 'CZE', 203),
(61, 'Denmark', 'DK', 'DNK', 208),
(62, 'Djibouti', 'DJ', 'DJI', 262),
(63, 'Dominica', 'DM', 'DMA', 212),
(64, 'Dominican Republic', 'DO', 'DOM', 214),
(65, 'Ecuador', 'EC', 'ECU', 218),
(66, 'Egypt', 'EG', 'EGY', 818),
(67, 'El Salvador', 'SV', 'SLV', 222),
(68, 'Equatorial Guinea', 'GQ', 'GNQ', 226),
(69, 'Eritrea', 'ER', 'ERI', 232),
(70, 'Estonia', 'EE', 'EST', 233),
(71, 'Ethiopia', 'ET', 'ETH', 231),
(72, 'Falkland Islands (Malvinas)', 'FK', 'FLK', 238),
(73, 'Faroe Islands', 'FO', 'FRO', 234),
(74, 'Fiji', 'FJ', 'FJI', 242),
(75, 'Finland', 'FI', 'FIN', 246),
(76, 'France', 'FR', 'FRA', 250),
(77, 'French Guiana', 'GF', 'GUF', 254),
(78, 'French Polynesia', 'PF', 'PYF', 258),
(79, 'French Southern Territories', 'TF', 'ATF', 260),
(80, 'Gabon', 'GA', 'GAB', 266),
(81, 'Gambia', 'GM', 'GMB', 270),
(82, 'Georgia', 'GE', 'GEO', 268),
(83, 'Germany', 'DE', 'DEU', 276),
(84, 'Ghana', 'GH', 'GHA', 288),
(85, 'Gibraltar', 'GI', 'GIB', 292),
(86, 'Greece', 'GR', 'GRC', 300),
(87, 'Greenland', 'GL', 'GRL', 304),
(88, 'Grenada', 'GD', 'GRD', 308),
(89, 'Guadeloupe', 'GP', 'GLP', 312),
(90, 'Guam', 'GU', 'GUM', 316),
(91, 'Guatemala', 'GT', 'GTM', 320),
(92, 'Guernsey', 'GG', 'GGY', 831),
(93, 'Guinea', 'GN', 'GIN', 324),
(94, 'Guinea-Bissau', 'GW', 'GNB', 624),
(95, 'Guyana', 'GY', 'GUY', 328),
(96, 'Haiti', 'HT', 'HTI', 332),
(97, 'Heard Island and McDonald Islands', 'HM', 'HMD', 334),
(98, 'Holy See (Vatican City State)', 'VA', 'VAT', 336),
(99, 'Honduras', 'HN', 'HND', 340),
(100, 'Hong Kong', 'HK', 'HKG', 344),
(101, 'Hungary', 'HU', 'HUN', 348),
(102, 'Iceland', 'IS', 'ISL', 352),
(103, 'India', 'IN', 'IND', 356),
(104, 'Indonesia', 'ID', 'IDN', 360),
(105, 'Iran, Islamic Republic of', 'IR', 'IRN', 364),
(106, 'Iraq', 'IQ', 'IRQ', 368),
(107, 'Ireland', 'IE', 'IRL', 372),
(108, 'Isle of Man', 'IM', 'IMN', 833),
(109, 'Israel', 'IL', 'ISR', 376),
(110, 'Italy', 'IT', 'ITA', 380),
(111, 'Jamaica', 'JM', 'JAM', 388),
(112, 'Japan', 'JP', 'JPN', 392),
(113, 'Jersey', 'JE', 'JEY', 832),
(114, 'Jordan', 'JO', 'JOR', 400),
(115, 'Kazakhstan', 'KZ', 'KAZ', 398),
(116, 'Kenya', 'KE', 'KEN', 404),
(117, 'Kiribati', 'KI', 'KIR', 296),
(118, "Korea, Democratic People\'s Republic of", 'KP', 'PRK', 408),
(119, 'Korea, Republic of', 'KR', 'KOR', 410),
(120, 'Kuwait', 'KW', 'KWT', 414),
(121, 'Kyrgyzstan', 'KG', 'KGZ', 417),
(122, "Lao People\'s Democratic Republic", 'LA', 'LAO', 418),
(123, 'Latvia', 'LV', 'LVA', 428),
(124, 'Lebanon', 'LB', 'LBN', 422),
(125, 'Lesotho', 'LS', 'LSO', 426),
(126, 'Liberia', 'LR', 'LBR', 430),
(127, 'Libya', 'LY', 'LBY', 434),
(128, 'Liechtenstein', 'LI', 'LIE', 438),
(129, 'Lithuania', 'LT', 'LTU', 440),
(130, 'Luxembourg', 'LU', 'LUX', 442),
(131, 'Macao', 'MO', 'MAC', 446),
(132, 'Macedonia, the former Yugoslav Republic of', 'MK', 'MKD', 807),
(133, 'Madagascar', 'MG', 'MDG', 450),
(134, 'Malawi', 'MW', 'MWI', 454),
(135, 'Malaysia', 'MY', 'MYS', 458),
(136, 'Maldives', 'MV', 'MDV', 462),
(137, 'Mali', 'ML', 'MLI', 466),
(138, 'Malta', 'MT', 'MLT', 470),
(139, 'Marshall Islands', 'MH', 'MHL', 584),
(140, 'Martinique', 'MQ', 'MTQ', 474),
(141, 'Mauritania', 'MR', 'MRT', 478),
(142, 'Mauritius', 'MU', 'MUS', 480),
(143, 'Mayotte', 'YT', 'MYT', 175),
(144, 'Mexico', 'MX', 'MEX', 484),
(145, 'Micronesia, Federated States of', 'FM', 'FSM', 583),
(146, 'Moldova, Republic of', 'MD', 'MDA', 498),
(147, 'Monaco', 'MC', 'MCO', 492),
(148, 'Mongolia', 'MN', 'MNG', 496),
(149, 'Montenegro', 'ME', 'MNE', 499),
(150, 'Montserrat', 'MS', 'MSR', 500),
(151, 'Morocco', 'MA', 'MAR', 504),
(152, 'Mozambique', 'MZ', 'MOZ', 508),
(153, 'Myanmar', 'MM', 'MMR', 104),
(154, 'Namibia', 'NA', 'NAM', 516),
(155, 'Nauru', 'NR', 'NRU', 520),
(156, 'Nepal', 'NP', 'NPL', 524),
(157, 'Netherlands', 'NL', 'NLD', 528),
(158, 'New Caledonia', 'NC', 'NCL', 540),
(159, 'New Zealand', 'NZ', 'NZL', 554),
(160, 'Nicaragua', 'NI', 'NIC', 558),
(161, 'Niger', 'NE', 'NER', 562),
(162, 'Nigeria', 'NG', 'NGA', 566),
(163, 'Niue', 'NU', 'NIU', 570),
(164, 'Norfolk Island', 'NF', 'NFK', 574),
(165, 'Northern Mariana Islands', 'MP', 'MNP', 580),
(166, 'Norway', 'NO', 'NOR', 578),
(167, 'Oman', 'OM', 'OMN', 512),
(168, 'Pakistan', 'PK', 'PAK', 586),
(169, 'Palau', 'PW', 'PLW', 585),
(170, 'Palestine, State of', 'PS', 'PSE', 275),
(171, 'Panama', 'PA', 'PAN', 591),
(172, 'Papua New Guinea', 'PG', 'PNG', 598),
(173, 'Paraguay', 'PY', 'PRY', 600),
(174, 'Peru', 'PE', 'PER', 604),
(175, 'Philippines', 'PH', 'PHL', 608),
(176, 'Pitcairn', 'PN', 'PCN', 612),
(177, 'Poland', 'PL', 'POL', 616),
(178, 'Portugal', 'PT', 'PRT', 620),
(179, 'Puerto Rico', 'PR', 'PRI', 630),
(180, 'Qatar', 'QA', 'QAT', 634),
(181, 'Reunion', 'RE', 'REU', 638),
(182, 'Romania', 'RO', 'ROU', 642),
(183, 'Russian Federation', 'RU', 'RUS', 643),
(184, 'Rwanda', 'RW', 'RWA', 646),
(185, 'Saint Barthelemy', 'BL', 'BLM', 652),
(186, 'Saint Helena, Ascension and Tristan da Cunha', 'SH', 'SHN', 654),
(187, 'Saint Kitts and Nevis', 'KN', 'KNA', 659),
(188, 'Saint Lucia', 'LC', 'LCA', 662),
(189, 'Saint Martin (French part)', 'MF', 'MAF', 663),
(190, 'Saint Pierre and Miquelon', 'PM', 'SPM', 666),
(191, 'Saint Vincent and the Grenadines', 'VC', 'VCT', 670),
(192, 'Samoa', 'WS', 'WSM', 882),
(193, 'San Marino', 'SM', 'SMR', 674),
(194, 'Sao Tome and Principe', 'ST', 'STP', 678),
(195, 'Saudi Arabia', 'SA', 'SAU', 682),
(196, 'Senegal', 'SN', 'SEN', 686),
(197, 'Serbia', 'RS', 'SRB', 688),
(198, 'Seychelles', 'SC', 'SYC', 690),
(199, 'Sierra Leone', 'SL', 'SLE', 694),
(200, 'Singapore', 'SG', 'SGP', 702),
(201, 'Sint Maarten (Dutch part)', 'SX', 'SXM', 534),
(202, 'Slovakia', 'SK', 'SVK', 703),
(203, 'Slovenia', 'SI', 'SVN', 705),
(204, 'Solomon Islands', 'SB', 'SLB', 90),
(205, 'Somalia', 'SO', 'SOM', 706),
(206, 'South Africa', 'ZA', 'ZAF', 710),
(207, 'South Georgia and the South Sandwich Islands', 'GS', 'SGS', 239),
(208, 'South Sudan', 'SS', 'SSD', 728),
(209, 'Spain', 'ES', 'ESP', 724),
(210, 'Sri Lanka', 'LK', 'LKA', 144),
(211, 'Sudan', 'SD', 'SDN', 729),
(212, 'Suriname', 'SR', 'SUR', 740),
(213, 'Svalbard and Jan Mayen', 'SJ', 'SJM', 744),
(214, 'Swaziland', 'SZ', 'SWZ', 748),
(215, 'Sweden', 'SE', 'SWE', 752),
(216, 'Switzerland', 'CH', 'CHE', 756),
(217, 'Syrian Arab Republic', 'SY', 'SYR', 760),
(218, 'Taiwan, Province of China', 'TW', 'TWN', 158),
(219, 'Tajikistan', 'TJ', 'TJK', 762),
(220, 'Tanzania, United Republic of', 'TZ', 'TZA', 834),
(221, 'Thailand', 'TH', 'THA', 764),
(222, 'Timor-Leste', 'TL', 'TLS', 626),
(223, 'Togo', 'TG', 'TGO', 768),
(224, 'Tokelau', 'TK', 'TKL', 772),
(225, 'Tonga', 'TO', 'TON', 776),
(226, 'Trinidad and Tobago', 'TT', 'TTO', 780),
(227, 'Tunisia', 'TN', 'TUN', 788),
(228, 'Turkey', 'TR', 'TUR', 792),
(229, 'Turkmenistan', 'TM', 'TKM', 795),
(230, 'Turks and Caicos Islands', 'TC', 'TCA', 796),
(231, 'Tuvalu', 'TV', 'TUV', 798),
(232, 'Uganda', 'UG', 'UGA', 800),
(233, 'Ukraine', 'UA', 'UKR', 804),
(234, 'United Arab Emirates', 'AE', 'ARE', 784),
(235, 'United Kingdom', 'GB', 'GBR', 826),
(236, 'United States', 'US', 'USA', 840),
(237, 'United States Minor Outlying Islands', 'UM', 'UMI', 581),
(238, 'Uruguay', 'UY', 'URY', 858),
(239, 'Uzbekistan', 'UZ', 'UZB', 860),
(240, 'Vanuatu', 'VU', 'VUT', 548),
(241, 'Venezuela, Bolivarian Republic of', 'VE', 'VEN', 862),
(242, 'Vietnam', 'VN', 'VNM', 704),
(243, 'Virgin Islands, British', 'VG', 'VGB', 92),
(244, 'Virgin Islands, U.S.', 'VI', 'VIR', 850),
(245, 'Wallis and Futuna', 'WF', 'WLF', 876),
(246, 'Western Sahara', 'EH', 'ESH', 732),
(247, 'Yemen', 'YE', 'YEM', 887),
(248, 'Zambia', 'ZM', 'ZMB', 894),
(249, 'Zimbabwe', 'ZW', 'ZWE', 716);


-- Amit 08 Oct 2020

-- UPDATE `resources` SET `module` = 'covid19' WHERE `resources`.`module` = 'covid-19';
-- UPDATE `resources` SET `module` = 'admin' WHERE `resources`.`resource_id` = 1; UPDATE `resources` SET `module` = 'admin' WHERE `resources`.`resource_id` = 2; UPDATE `resources` SET `module` = 'admin' WHERE `resources`.`resource_id` = 3; UPDATE `resources` SET `module` = 'admin' WHERE `resources`.`resource_id` = 4; UPDATE `resources` SET `module` = 'admin' WHERE `resources`.`resource_id` = 5; UPDATE `resources` SET `module` = 'admin' WHERE `resources`.`resource_id` = 14; UPDATE `resources` SET `module` = 'admin' WHERE `resources`.`resource_id` = 17; UPDATE `resources` SET `module` = 'admin' WHERE `resources`.`resource_id` = 21;
-- UPDATE `resources` SET `module` = 'common' WHERE `resources`.`resource_id` = 13;
-- UPDATE `resources` SET `module` = 'common' WHERE `resources`.`resource_id` = 24;
-- UPDATE `resources` SET `display_name` = 'Dashboard' WHERE `resources`.`resource_id` = 13;
-- UPDATE `resources` SET `display_name` = 'Import VL Test Results' WHERE `resources`.`resource_id` = 8;
-- UPDATE `resources` SET `display_name` = 'Manage VL Batch' WHERE `resources`.`resource_id` = 7;
-- UPDATE `resources` SET `display_name` = 'Enter VL Result Manually' WHERE `resources`.`resource_id` = 10;
-- UPDATE `resources` SET `display_name` = 'Print VL Result' WHERE `resources`.`resource_id` = 9;
-- UPDATE `resources` SET `display_name` = 'VL Requests' WHERE `resources`.`resource_id` = 6;
-- UPDATE `resources` SET `display_name` = 'Export VL Data' WHERE `resources`.`resource_id` = 12;
-- UPDATE `resources` SET `display_name` = 'VL Sample Status Report' WHERE `resources`.`resource_id` = 11;
-- UPDATE `resources` SET `display_name` = 'Covid-19 Reference Tables' WHERE `resources`.`resource_id` = 33;

-- DELETE FROM `roles_privileges_map` WHERE privilege_id in (60,61);
-- DELETE FROM `privileges` WHERE `privileges`.`privilege_id` = 60;
-- DELETE FROM `privileges` WHERE `privileges`.`privilege_id` = 61;
-- DELETE FROM resources WHERE resource_id = 21;

-- UPDATE `resources` SET `display_name` = 'VL Reports', `resource_name` = 'vl_reports' WHERE `resources`.`resource_id` = 11;
-- UPDATE `resources` SET `display_name` = 'VL Results', `resource_name` = 'vl_results' WHERE `resources`.`resource_id` = 10;

-- UPDATE `privileges` SET `resource_id`= 11 where resource_id in (11, 12, 16,18,19,20,22);
-- UPDATE `privileges` SET `resource_id`= 10 where resource_id in (8, 9, 10,15);



-- DELETE FROM `roles_privileges_map` WHERE privilege_id in (32);
-- DELETE FROM `privileges` WHERE `privileges`.`privilege_id` = 32;


-- UPDATE `privileges` SET `display_name` = 'Contact Notes (High VL Reports)' WHERE `privileges`.`privilege_id` = 34; UPDATE `privileges` SET `display_name` = 'High VL Report' WHERE `privileges`.`privilege_id` = 33; UPDATE `privileges` SET `display_name` = 'Sample Rejection Report' WHERE `privileges`.`privilege_id` = 57; UPDATE `privileges` SET `display_name` = 'Sample Status Report' WHERE `privileges`.`privilege_id` = 22; UPDATE `privileges` SET `display_name` = 'Controls Report' WHERE `privileges`.`privilege_id` = 63; UPDATE `privileges` SET `display_name` = 'Access Export VL Data' WHERE `privileges`.`privilege_id` = 23; UPDATE `privileges` SET `display_name` = 'Export VL Data in Excel' WHERE `privileges`.`privilege_id` = 70; UPDATE `privileges` SET `display_name` = 'Dashboard' WHERE `privileges`.`privilege_id` = 40; UPDATE `privileges` SET `display_name` = 'VL Weekly Report' WHERE `privileges`.`privilege_id` = 56;
-- UPDATE `privileges` SET `display_name` = 'Import VL Results from File' WHERE `privileges`.`privilege_id` = 19; UPDATE `privileges` SET `display_name` = 'Print Result PDF' WHERE `privileges`.`privilege_id` = 20; UPDATE `privileges` SET `display_name` = 'Manage VL Result Status (Approve/Reject)' WHERE `privileges`.`privilege_id` = 31;

-- DELETE FROM `resources` WHERE resource_id not in (SELECT DISTINCT resource_id FROM privileges);


UPDATE `system_config` SET `value` = '4.2.3' WHERE `system_config`.`name` = 'version';
-- Version 4.2.3 -- Amit -- 8-Oct-2020

-- Thana 09-Oct-2020
-- INSERT INTO `resources` (`resource_id`, `module`, `resource_name`, `display_name`) VALUES (NULL, 'vl', 'vl-reference', 'VL Reference Management'), (NULL, 'eid', 'eid-reference', 'EID Reference Management');
-- INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '34', 'vl-art-code-details.php', 'Manage Reference'), (NULL, '35', 'eid-sample-type.php', 'Manage Reference');
-- UPDATE `resources` SET `module`='covid19' WHERE `module`='covid-19';

ALTER TABLE `form_covid19` ADD `source_of_alert` VARCHAR(255) NULL DEFAULT NULL AFTER `implementing_partner`, ADD `source_of_alert_other` VARCHAR(255) NULL DEFAULT NULL AFTER `source_of_alert`;
-- Thana 13-Oct-2020
ALTER TABLE `r_eid_test_reasons` ADD `parent_reason` INT NULL DEFAULT '0' AFTER `test_reason_name`;
ALTER TABLE `r_eid_test_reasons` ADD `data_sync` INT NULL DEFAULT '0' AFTER `updated_datetime`;
ALTER TABLE `r_vl_test_reasons` ADD `parent_reason` INT(11) NULL DEFAULT '0' AFTER `test_reason_name`;
ALTER TABLE `r_vl_test_reasons` ADD `data_sync` INT NULL DEFAULT '0' AFTER `updated_datetime`;
-- INSERT INTO `resources` (`resource_id`, `module`, `resource_name`, `display_name`) VALUES (NULL, 'vl', 'common-reference', 'Common Reference');
-- INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, '36', 'province-details.php', 'Manage common Reference');
ALTER TABLE `r_implementation_partners` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `i_partner_status`, ADD `data_sync` INT(11) NULL DEFAULT '0' AFTER `updated_datetime`;
ALTER TABLE `r_funding_sources` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `funding_source_status`, ADD `data_sync` INT(11) NULL DEFAULT '0' AFTER `updated_datetime`;


-- AMIT 13-OCT-2020

-- You may have to uncheck "Enable foreign key checks" checkbox on phpMyAdmin
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE privileges;
DROP TABLE resources;
SET FOREIGN_KEY_CHECKS=1;

ALTER TABLE roles_privileges_map DROP FOREIGN KEY roles_privileges_map_ibfk_2;

CREATE TABLE `privileges` (
  `privilege_id` int(11) NOT NULL,
  `resource_id` varchar(255) NOT NULL,
  `privilege_name` varchar(255) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES
(1, 'users', 'users.php', 'Access'),
(2, 'users', 'addUser.php', 'Add'),
(3, 'users', 'editUser.php', 'Edit'),
(4, 'facility', 'facilities.php', 'Access'),
(5, 'facility', 'addFacility.php', 'Add'),
(6, 'facility', 'editFacility.php', 'Edit'),
(7, 'global-config', 'globalConfig.php', 'Access'),
(8, 'global-config', 'editGlobalConfig.php', 'Edit'),
(9, 'import-config', 'importConfig.php', 'Access'),
(10, 'import-config', 'addImportConfig.php', 'Add'),
(11, 'import-config', 'editImportConfig.php', 'Edit'),
(12, 'vl-test-request', 'vlRequest.php', 'Access'),
(13, 'vl-test-request', 'addVlRequest.php', 'Add'),
(14, 'vl-test-request', 'editVlRequest.php', 'Edit'),
(15, 'vl-test-request', 'viewVlRequest.php', 'View Vl Request'),
(16, 'vl-batch', 'batchcode.php', 'Access'),
(17, 'vl-batch', 'addBatch.php', 'Add'),
(18, 'vl-batch', 'editBatch.php', 'Edit'),
(19, 'vl-results', 'addImportResult.php', 'Import VL Results from File'),
(20, 'vl-results', 'vlPrintResult.php', 'Print Result PDF'),
(21, 'vl-results', 'vlTestResult.php', 'Access'),
(22, 'vl-reports', 'vl-sample-status.php', 'Sample Status Report'),
(23, 'vl-reports', 'vlResult.php', 'Access Export VL Data'),
(24, 'home', 'index.php', 'Access'),
(25, 'roles', 'roles.php', 'Access'),
(26, 'roles', 'editRole.php', 'Edit'),
(27, 'vl-test-request', 'vlRequestMail.php', 'Email Test Request'),
(28, 'test-request-email-config', 'testRequestEmailConfig.php', 'Access'),
(29, 'vl-test-request', 'sendRequestToMail.php', 'Send Request to Mail'),
(31, 'vl-results', 'vlResultApproval.php', 'Manage VL Result Status (Approve/Reject)'),
(33, 'vl-reports', 'highViralLoad.php', 'High VL Report'),
(34, 'vl-reports', 'addContactNotes.php', 'Contact Notes (High VL Reports)'),
(39, 'roles', 'addRole.php', 'Add'),
(40, 'vl-reports', 'vlTestResultStatus.php', 'Dashboard'),
(41, 'vl-test-request', 'patientList.php', 'Export Patient List'),
(43, 'test-request-email-config', 'editTestRequestEmailConfig.php', 'Edit'),
(45, 'vl-test-request', 'vlResultMail.php', 'Email Test Result'),
(46, 'vl-batch', 'editBatchControlsPosition.php', 'Edit Controls Position'),
(47, 'vl-batch', 'addBatchControlsPosition.php', 'Add Controls Position'),
(48, 'test-result-email-config', 'testResultEmailConfig.php', 'Access'),
(49, 'test-result-email-config', 'editTestResultEmailConfig.php', 'Edit'),
(50, 'vl-test-request', 'vlRequestMailConfirm.php', 'Email Test Request Confirm'),
(51, 'vl-test-request', 'vlResultMailConfirm.php', 'Email Test Result Confirm'),
(56, 'vl-reports', 'vlWeeklyReport.php', 'VL Weekly Report'),
(57, 'vl-reports', 'sampleRejectionReport.php', 'Sample Rejection Report'),
(59, 'vl-reports', 'vlMonitoringReport.php', 'Sample Monitoring Report'),
(62, 'vl-reports', 'vlRequestRwdForm.php', 'Manage QR Code Rwd Form'),
(63, 'vl-reports', 'vlControlReport.php', 'Controls Report'),
(64, 'facility', 'addVlFacilityMap.php', 'Add Facility Map'),
(65, 'facility', 'facilityMap.php', 'Access Facility Map'),
(66, 'facility', 'editVlFacilityMap.php', 'Edit Facility Map'),
(67, 'specimen-referral-manifest', 'addSpecimenReferralManifest.php', 'Add'),
(68, 'specimen-referral-manifest', 'editSpecimenReferralManifest.php', 'Edit'),
(69, 'specimen-referral-manifest', 'specimenReferralManifestList.php', 'Access'),
(70, 'vl-reports', 'vlResultAllFieldExportInExcel.php', 'Export VL Data in Excel'),
(71, 'move-samples', 'sampleList.php', 'Access'),
(72, 'move-samples', 'addSampleList.php', 'Add Samples List'),
(73, 'move-samples', 'editSampleList.php', 'Edit Sample List'),
(74, 'eid-requests', 'eid-add-request.php', 'Add Request'),
(75, 'eid-requests', 'eid-edit-request.php', 'Edit Request'),
(76, 'eid-requests', 'eid-requests.php', 'View Requests'),
(77, 'eid-batches', 'eid-batches.php', 'View Batches'),
(78, 'eid-batches', 'eid-add-batch.php', 'Add Batch'),
(79, 'eid-batches', 'eid-edit-batch.php', 'Edit Batch'),
(80, 'eid-results', 'eid-manual-results.php', 'Enter Result'),
(81, 'eid-results', 'eid-import-result.php', 'Import Result File'),
(84, 'eid-results', 'eid-result-status.php', 'Manage Result Status'),
(85, 'eid-results', 'eid-print-results.php', 'Print Results'),
(86, 'eid-management', 'eid-export-data.php', 'Export Data'),
(87, 'eid-management', 'eid-sample-rejection-report.php', 'Sample Rejection Report'),
(88, 'eid-management', 'eid-sample-status.php', 'Sample Status Report'),
(89, 'vl-test-request', 'addSamplesFromManifest.php', 'Add Samples from Manifest'),
(91, 'eid-requests', 'addSamplesFromManifest.php', 'Add Samples from Manifest'),
(95, 'covid-19-requests', 'covid-19-add-request.php', 'Add Request'),
(96, 'covid-19-requests', 'covid-19-edit-request.php', 'Edit Request'),
(97, 'covid-19-requests', 'covid-19-requests.php', 'View Requests'),
(98, 'covid-19-requests', 'covid-19-result-status.php', 'Manage Result Status'),
(99, 'covid-19-requests', 'covid-19-print-results.php', 'Print Results'),
(100, 'covid-19-batches', 'covid-19-batches.php', 'View Batches'),
(101, 'covid-19-batches', 'covid-19-add-batch.php', 'Add Batch'),
(102, 'covid-19-batches', 'covid-19-edit-batch.php', 'Edit Batch'),
(103, 'covid-19-results', 'covid-19-manual-results.php', 'Enter Result Manually'),
(104, 'covid-19-results', 'covid-19-import-result.php', 'Import Result File'),
(105, 'covid-19-management', 'covid-19-export-data.php', 'Export Data'),
(106, 'covid-19-management', 'covid-19-sample-rejection-report.php', 'Sample Rejection Report'),
(107, 'covid-19-management', 'covid-19-sample-status.php', 'Sample Status Report'),
(108, 'covid-19-requests', 'record-final-result.php', 'Record Final Result'),
(109, 'covid-19-requests', 'can-record-confirmatory-tests.php', 'Can Record Confirmatory Tests'),
(110, 'covid-19-requests', 'update-record-confirmatory-tests.php', 'Update Record Confirmatory Tests'),
(111, 'covid-19-batches', 'covid-19-confirmation-manifest.php', 'Covid-19 Confirmation Manifest'),
(112, 'covid-19-batches', 'covid-19-add-confirmation-manifest.php', 'Add New Confirmation Manifest'),
(113, 'covid-19-batches', 'generate-confirmation-manifest.php', 'Generate Positive Confirmation Manifest'),
(114, 'covid-19-batches', 'covid-19-edit-confirmation-manifest.php', 'Edit Positive Confirmation Manifest'),
(121, 'eid-management', 'eid-clinic-report.php', 'EID Clinic Reports'),
(122, 'covid-19-management', 'covid-19-clinic-report.php', 'Covid-19 Clinic Reports'),
(123, 'covid-19-reference', 'covid19-sample-type.php', 'Manage Reference'),
(124, 'covid-19-reference', 'covid19-comorbidities.php', 'Manage Comorbidities'),
(125, 'covid-19-reference', 'addCovid19Comorbidities.php', 'Add Comorbidities'),
(126, 'covid-19-reference', 'editCovid19Comorbidities.php', 'Edit Comorbidities'),
(127, 'covid-19-reference', 'covid19-sample-rejection-reasons.php', 'Manage Sample Rejection Reasons'),
(128, 'covid-19-reference', 'addCovid19SampleRejectionReason.php', 'Add Sample Rejection Reason'),
(129, 'covid-19-reference', 'editCovid19SampleRejectionReason.php', 'Edit Sample Rejection Reason'),
(130, 'vl-reference', 'vl-art-code-details.php', 'Manage VL Reference Tables'),
(131, 'eid-reference', 'eid-sample-type.php', 'Manage EID Reference Tables'),
(139, 'common-reference', 'province-details.php', 'Manage Common Reference Tables');

ALTER TABLE `privileges`
  ADD PRIMARY KEY (`privilege_id`),
  ADD UNIQUE KEY `resource` (`resource_id`,`privilege_name`);

CREATE TABLE `resources` (
  `resource_id` varchar(255) NOT NULL,
  `module` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES
('common-reference', 'admin', 'Common Reference Tables'),
('covid-19-batches', 'covid19', 'Covid-19 Batch Management'),
('covid-19-management', 'covid19', 'Covid-19 Reports'),
('covid-19-reference', 'admin', 'Covid-19 Reference Tables'),
('covid-19-requests', 'covid19', 'Covid-19 Request Management'),
('covid-19-results', 'covid19', 'Covid-19 Result Management'),
('eid-batches', 'eid', 'EID Batch Management'),
('eid-management', 'eid', 'EID Reports'),
('eid-reference', 'admin', 'EID Reference Management'),
('eid-requests', 'eid', 'EID Request Management'),
('eid-results', 'eid', 'EID Result Management'),
('facility', 'admin', 'Manage Facility'),
('global-config', 'admin', 'Manage General Config'),
('home', 'common', 'Dashboard'),
('import-config', 'admin', 'Manage Import Config'),
('move-samples', 'common', 'Move Samples'),
('roles', 'admin', 'Manage Roles'),
('specimen-referral-manifest', 'vl', 'Manage Specimen Referral Manifests'),
('test-request-email-config', 'admin', 'Manage Test Request Email Config'),
('test-result-email-config', 'admin', 'Manage Test Result Email Config'),
('users', 'admin', 'Manage Users'),
('vl-batch', 'vl', 'Manage VL Batch'),
('vl-reference', 'admin', 'VL Reference Management'),
('vl-reports', 'vl', 'VL Reports'),
('vl-results', 'vl', 'VL Results'),
('vl-test-request', 'vl', 'VL Requests');

ALTER TABLE `resources`
  ADD PRIMARY KEY (`resource_id`);

ALTER TABLE `privileges`
  MODIFY `privilege_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=140;

ALTER TABLE roles_privileges_map
ADD FOREIGN KEY (privilege_id) REFERENCES privileges(privilege_id);



-- Amit 14 Oct 2020
RENAME TABLE `form_details` TO `s_available_country_forms`;
RENAME TABLE `countries` TO `r_countries`;
RENAME TABLE `r_art_code_details` TO `r_vl_art_regimen`;
RENAME TABLE `r_sample_rejection_reasons` TO `r_vl_sample_rejection_reasons`;


CREATE TABLE `form_hepatitis` (
  `hepatitis_id` int(11) NOT NULL,
  `vlsm_instance_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `vlsm_country_id` int(11) NOT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `sample_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `external_sample_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `remote_sample` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `remote_sample_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `patient_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `patient_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `patient_surname` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `patient_dob` date DEFAULT NULL,
  `patient_age` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `patient_gender` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `patient_phone_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `patient_nationality` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `patient_address` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_sample_collected` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `reason_for_testing` int(11) DEFAULT NULL,
  `patient_province` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `patient_district` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `patient_city` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `specimen_type` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `testing_point` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lab_technician` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lab_reception_person` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `test_platform` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `result_status` int(11) DEFAULT NULL,
  `is_sample_rejected` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'no',
  `reason_for_sample_rejection` varchar(500) COLLATE utf8_unicode_ci DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `result` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `is_result_authorised` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `authorized_by` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `authorized_on` date DEFAULT NULL,
  `reason_for_changing` text COLLATE utf8_unicode_ci,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_reviewed_by` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `result_approved_datetime` datetime DEFAULT NULL,
  `result_approved_by` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `approver_comments` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `manual_result_entry` varchar(255) COLLATE utf8_unicode_ci DEFAULT 'no',
  `import_machine_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `import_machine_file_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `result_printed_datetime` datetime DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  `request_created_by` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `sample_batch_id` int(11) DEFAULT NULL,
  `sample_package_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `sample_package_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_result_mail_sent` varchar(255) COLLATE utf8_unicode_ci DEFAULT 'no',
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` varchar(255) CHARACTER SET utf8 DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `form_hepatitis`
  ADD PRIMARY KEY (`hepatitis_id`),
  ADD KEY `sample_code_key` (`sample_code_key`),
  ADD KEY `remote_sample_code_key` (`remote_sample_code_key`);

ALTER TABLE `form_hepatitis`
  MODIFY `hepatitis_id` int(11) NOT NULL AUTO_INCREMENT;


UPDATE `system_config` SET `value` = '4.2.4' WHERE `system_config`.`name` = 'version';
-- Version 4.2.4 -- Amit -- 14-Oct-2020

-- Thana 15-Oct-2020
INSERT INTO `roles` (`role_id`, `role_name`, `role_code`, `status`, `landing_page`) VALUES (NULL, 'API User', 'API', 'active', NULL);
ALTER TABLE `user_details` ADD `api_token` TEXT NULL DEFAULT NULL AFTER `user_signature`, ADD `api_token_generated_datetime` DATETIME NULL DEFAULT NULL AFTER `api_token`;

-- Thana 20-Oct-2020
ALTER TABLE `package_details` ADD `lab_id` INT(11) NULL DEFAULT NULL AFTER `module`;

UPDATE `system_config` SET `value` = '4.2.5' WHERE `system_config`.`name` = 'version';
-- Version 4.2.5 -- Amit -- 22-Oct-2020

-- Thana 30-Oct-2020
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) 
VALUES 
('Lock approved VL Samples', 'lock_approved_vl_samples', 'no', 'vl', 'yes', NULL, NULL, 'active'), 
('Lock Approved EID Samples', 'lock_approved_eid_samples', 'no', 'eid', 'yes', NULL, NULL, 'active'), 
('Lock Approved Covid-19 Samples', 'lock_approved_covid19_samples', 'no', 'covid19', 'yes', NULL, NULL, 'active');

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'vl-test-request', 'edit-locked-vl-samples', 'Edit Locked VL Samples'), (NULL, 'eid-requests', 'edit-locked-eid-samples', 'Edit Locked EID Samples'), (NULL, 'covid-19-requests', 'edit-locked-covid19-samples', 'Edit Locked Covid-19 Samples');

ALTER TABLE `vl_request_form` ADD `locked` VARCHAR(50) NOT NULL DEFAULT 'no' AFTER `result_status`;
ALTER TABLE `eid_form` ADD `locked` VARCHAR(50) NOT NULL DEFAULT 'no' AFTER `result_status`;
ALTER TABLE `form_covid19` ADD `locked` VARCHAR(50) NOT NULL DEFAULT 'no' AFTER `result_status`;

-- Prasath 04-Nov-2020
ALter table import_config_machines add column poc_device Varchar(255) NULL;
ALter table import_config_machines add column latitude Varchar(255) NULL;
ALter table import_config_machines add column longitude Varchar(255) NULL;
-- Thana 05-Nov-2020
ALTER TABLE `import_config_machines` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `longitude`;

-- Prasath M 06-Nov-2020
ALTER TABLE `testing_labs` ADD `monthly_target`Varchar(255) NULL DEFAULT NULL ;

-- Thana 09-Nov-2020
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'vl-reports', 'vlMonthlyThresholdReport.php', 'Monthly Threshold Report'), (NULL, 'eid-management', 'eidMonthlyThresholdReport.PHP', 'Monthly Threshold Report'), (NULL, 'covid-19-management', 'covid19MonthlyThresholdReport.PHP', 'Monthly Threshold Report');
 
-- Thana 10-Nov-2020

DROP TABLE IF EXISTS `form_hepatitis`;
CREATE TABLE `form_hepatitis` (
 `hepatitis_id` int(11) NOT NULL AUTO_INCREMENT,
 `vlsm_instance_id` varchar(255) DEFAULT NULL,
 `vlsm_country_id` int(11) NOT NULL,
 `sample_code_key` int(11) DEFAULT NULL,
 `sample_code_format` varchar(255) DEFAULT NULL,
 `sample_code` varchar(255) DEFAULT NULL,
 `external_sample_code` varchar(255) DEFAULT NULL,
 `test_number` int(11) DEFAULT NULL,
 `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
 `remote_sample_code_key` int(11) DEFAULT NULL,
 `remote_sample_code_format` varchar(255) DEFAULT NULL,
 `remote_sample_code` varchar(255) DEFAULT NULL,
 `sample_collection_date` datetime NOT NULL,
 `sample_received_at_hub_datetime` datetime DEFAULT NULL,
 `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
 `sample_condition` varchar(255) DEFAULT NULL,
 `sample_tested_datetime` datetime DEFAULT NULL,
 `funding_source` int(11) DEFAULT NULL,
 `implementing_partner` int(11) DEFAULT NULL,
 `facility_id` int(11) DEFAULT NULL,
 `province_id` int(11) DEFAULT NULL,
 `patient_id` varchar(255) DEFAULT NULL,
 `patient_name` varchar(255) DEFAULT NULL,
 `patient_surname` varchar(255) DEFAULT NULL,
 `patient_dob` date DEFAULT NULL,
 `patient_age` varchar(255) DEFAULT NULL,
 `patient_gender` varchar(255) DEFAULT NULL,
 `patient_phone_number` varchar(255) DEFAULT NULL,
 `patient_province` varchar(255) DEFAULT NULL,
 `patient_district` varchar(255) DEFAULT NULL,
 `patient_city` varchar(255) DEFAULT NULL,
 `patient_nationality` varchar(255) DEFAULT NULL,
 `patient_occupation` varchar(255) DEFAULT NULL,
 `patient_address` varchar(1000) DEFAULT NULL,
 `patient_marital_status` varchar(50) DEFAULT NULL,
 `patient_insurance` varchar(50) DEFAULT NULL,
 `is_sample_collected` varchar(255) DEFAULT NULL,
 `reason_for_hepatitis_test` int(11) DEFAULT NULL,
 `type_of_test_requested` varchar(255) DEFAULT NULL,
 `specimen_type` varchar(255) DEFAULT NULL,
 `priority_status` varchar(255) DEFAULT NULL,
 `lab_id` int(11) DEFAULT NULL,
 `testing_point` varchar(255) DEFAULT NULL,
 `lab_reception_person` varchar(255) DEFAULT NULL,
 `hepatitis_test_platform` varchar(255) DEFAULT NULL,
 `result_status` int(11) DEFAULT NULL,
 `locked` varchar(50) NOT NULL DEFAULT 'no',
 `is_sample_rejected` varchar(255) NOT NULL DEFAULT 'no',
 `reason_for_sample_rejection` varchar(500) DEFAULT NULL,
 `rejection_on` date DEFAULT NULL,
 `result` varchar(255) DEFAULT NULL,
 `hbsag_result` varchar(255) DEFAULT NULL,
 `anti_hcv_result` varchar(255) DEFAULT NULL,
 `hcv_vl_result` varchar(255) DEFAULT NULL,
 `hbv_vl_result` varchar(255) DEFAULT NULL,
 `hcv_vl_count` varchar(255) DEFAULT NULL,
 `hbv_vl_count` varchar(255) DEFAULT NULL,
 `is_result_authorised` varchar(255) DEFAULT NULL,
 `authorized_by` varchar(255) DEFAULT NULL,
 `authorized_on` date DEFAULT NULL,
 `reason_for_changing` text,
 `result_reviewed_datetime` datetime DEFAULT NULL,
 `result_reviewed_by` varchar(255) DEFAULT NULL,
 `result_approved_datetime` datetime DEFAULT NULL,
 `result_approved_by` varchar(255) DEFAULT NULL,
 `approver_comments` varchar(1000) DEFAULT NULL,
 `result_dispatched_datetime` datetime DEFAULT NULL,
 `result_mail_datetime` datetime DEFAULT NULL,
 `manual_result_entry` varchar(255) DEFAULT 'no',
 `import_machine_name` varchar(255) DEFAULT NULL,
 `import_machine_file_name` varchar(255) DEFAULT NULL,
 `result_printed_datetime` datetime DEFAULT NULL,
 `request_created_datetime` datetime DEFAULT NULL,
 `request_created_by` varchar(255) DEFAULT NULL,
 `sample_registered_at_lab` datetime DEFAULT NULL,
 `sample_batch_id` int(11) DEFAULT NULL,
 `sample_package_id` varchar(255) DEFAULT NULL,
 `sample_package_code` varchar(255) DEFAULT NULL,
 `positive_test_manifest_id` int(11) DEFAULT NULL,
 `positive_test_manifest_code` varchar(255) DEFAULT NULL,
 `lot_number` varchar(255) DEFAULT NULL,
 `lot_expiration_date` date DEFAULT NULL,
 `is_result_mail_sent` varchar(255) DEFAULT 'no',
 `last_modified_datetime` datetime DEFAULT NULL,
 `last_modified_by` varchar(255) DEFAULT NULL,
 `data_sync` int(11) NOT NULL DEFAULT '0',
 PRIMARY KEY (`hepatitis_id`),
 KEY `last_modified_datetime` (`last_modified_datetime`),
 KEY `sample_code_key` (`sample_code_key`),
 KEY `remote_sample_code_key` (`remote_sample_code_key`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

-- Module Hepatitis Start
-- Thana 12-Nov-2020
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('hepatitis-requests', 'hepatitis', 'Hepatitis Request Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'hepatitis-requests', 'hepatitis-requests.php', 'Access'), (NULL, 'hepatitis-requests', 'hepatitis-add-request.php', 'Add'), (NULL, 'hepatitis-requests', 'hepatitis-edit-request.php', 'Edit');
-- Module Hepatitis End

-- Thana 12-Nov-2020
ALTER TABLE `eid_form` ADD `previous_pcr_result` VARCHAR(255) NULL DEFAULT NULL AFTER `last_pcr_id`;


UPDATE `system_config` SET `value` = '4.2.6' WHERE `system_config`.`name` = 'version';
-- Version 4.2.6 -- Amit -- 12-Nov-2020

-- Thana 17-Nov-2020
CREATE TABLE `r_hepatitis_comorbidities` (
 `comorbidity_id` int NOT NULL AUTO_INCREMENT,
 `comorbidity_name` varchar(255) DEFAULT NULL,
 `comorbidity_status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 PRIMARY KEY (`comorbidity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

CREATE TABLE `r_hepatitis_risk_factors` (
 `riskfactor_id` int NOT NULL AUTO_INCREMENT,
 `riskfactor_name` varchar(255) DEFAULT NULL,
 `riskfactor_status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 PRIMARY KEY (`riskfactor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

INSERT INTO `r_hepatitis_comorbidities` (`comorbidity_id`, `comorbidity_name`, `comorbidity_status`, `updated_datetime`) VALUES (NULL, 'Diabetes', 'active', '2020-11-17 16:32:11'), (NULL, 'Chronic renal failure', 'active', '2020-11-17 16:32:11'), (NULL, 'Cancer', 'active', '2020-11-17 16:32:11'), (NULL, 'HIV infection', 'active', '2020-11-17 16:32:11'), (NULL, 'Cardiovascular disease', 'active', '2020-11-17 16:32:11'), (NULL, 'HPV', 'active', '2020-11-17 16:32:11');

INSERT INTO `r_hepatitis_risk_factors` (`riskfactor_id`, `riskfactor_name`, `riskfactor_status`, `updated_datetime`) VALUES (NULL, 'Ever diagnosed with a liver disease', 'active', '2020-11-17 16:35:09'), (NULL, 'Viral hepatitis in the family', 'active', '2020-11-17 16:35:09'), (NULL, 'Ever been operated', 'active', '2020-11-17 16:35:09'), (NULL, 'Ever been traditionally operated (ibyinyo, ibirimi, indasago, scarification, tattoo)', 'active', '2020-11-17 16:35:09'), (NULL, 'Ever been transfused', 'active', '2020-11-17 16:35:09'), (NULL, 'Having more than one sexually partner', 'active', '2020-11-17 16:35:09'), (NULL, 'Ever experienced a physical trauma', 'active', '2020-11-17 16:35:09');

CREATE TABLE `hepatitis_patient_comorbidities` (
 `hepatitis_id` int NOT NULL,
 `comorbidity_id` int NOT NULL,
 `comorbidity_detected` varchar(255) NOT NULL,
 PRIMARY KEY (`hepatitis_id`,`comorbidity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE `hepatitis_risk_factors` (
 `hepatitis_id` int NOT NULL,
 `riskfactors_id` int NOT NULL,
 `riskfactors_detected` varchar(255) NOT NULL,
 PRIMARY KEY (`hepatitis_id`,`riskfactors_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Hepatitis Sample Code Format', 'hepatitis_sample_code', 'MMYY', 'hepatitis', 'yes', '2020-11-17 18:47:05', NULL, 'active'), ('Hepatitis Sample Code Prefix', 'hepatitis_sample_code_prefix', 'HEP', 'hepatitis', 'yes', '2020-11-17 18:47:05', NULL, 'active');

-- Thana 19-Nov-2020
ALTER TABLE `form_hepatitis` ADD `hbv_vaccination` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_insurance`, ADD `vl_testing_site` VARCHAR(255) NULL DEFAULT NULL AFTER `hbv_vl_count`;
ALTER TABLE `health_facilities` CHANGE `test_type` `test_type` ENUM('vl','eid','covid19','hepatitis') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('hepatitis-results', 'hepatitis', 'Hepatitis Results Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'hepatitis-results', 'hepatitis-manual-results.php', 'Enter Result Manually');



UPDATE `system_config` SET `value` = '4.2.7' WHERE `system_config`.`name` = 'version';
-- Version 4.2.7  -- Amit -- 20-Nov-2020

-- Prasath M 23-Nov-2020
ALTER TABLE `testing_labs` ADD `suppressed_monthly_target`Varchar(255) NULL DEFAULT NULL ;
-- Thana 23-Nov-2020
CREATE TABLE `r_hepatitis_sample_rejection_reasons` (
 `rejection_reason_id` int NOT NULL AUTO_INCREMENT,
 `rejection_reason_name` varchar(255) DEFAULT NULL,
 `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
 `rejection_reason_status` varchar(255) DEFAULT NULL,
 `rejection_reason_code` varchar(255) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `r_hepatitis_sample_type` (
 `sample_id` int NOT NULL AUTO_INCREMENT,
 `sample_name` varchar(255) CHARACTER SET latin1 DEFAULT NULL,
 `status` varchar(45) CHARACTER SET latin1 DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `r_hepatitis_test_reasons` (
 `test_reason_id` int NOT NULL AUTO_INCREMENT,
 `test_reason_name` varchar(255) DEFAULT NULL,
 `parent_reason` int DEFAULT NULL,
 `test_reason_status` varchar(45) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `r_hepatitis_results` (
 `result_id` varchar(255) NOT NULL,
 `result` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
 `status` varchar(255) NOT NULL DEFAULT 'active',
 `updated_datetime` datetime DEFAULT NULL,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `form_hepatitis` ADD `reason_for_vl_test` VARCHAR(255) NULL DEFAULT NULL AFTER `type_of_test_requested`;

ALTER TABLE `testing_labs` CHANGE `test_type` `test_type` ENUM('vl','eid','covid19','hepatitis') CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;
-- Thana 24-Nov-2020
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'hepatitis-requests', 'hepatitis-print-results.php', 'Print Results');
-- Thana 25-Nov-2020
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'hepatitis-requests', 'hepatitis-result-status.php', 'Manage Result Status');

-- Sudarmathi 25 Nov 2020
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('hepatitis-reference', 'admin', 'Hepatitis Reference Tables');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'hepatitis-reference', 'hepatitis-sample-type.php', 'Manage Hepatitis Reference');
-- Thana 26-Nov-2020
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Report Type', 'hepatitis_report_type', 'who', 'hepatitis', 'yes', '2020-11-26 17:35:16', NULL, 'active');


-- Prasath M 27-Nov-2020
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'vl-reports', 'vlSuppressedTargetReport.php', 'Monthly Threshold Report');
-- Thana 30-Nov-2020
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('hepatitis-batches', 'hepatitis', 'Hepatitis Batch Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'hepatitis-batches', 'hepatitis-batches.php', 'View Batches'), (NULL, 'hepatitis-batches', 'hepatitis-add-batch.php', 'Add Batch'), (NULL, 'hepatitis-batches', 'hepatitis-edit-batch.php', 'Edit Batch'), (NULL, 'hepatitis-batches', 'hepatitis-add-batch-position.php', 'Add Batch Position'), (NULL, 'hepatitis-batches', 'hepatitis-edit-batch-position.php', 'Edit Batch Position');

-- Thana 03-Dec-2020
UPDATE `resources` SET `display_name` = 'Hepatitis Reference Management' WHERE `resources`.`resource_id` = 'hepatitis-reference';

-- Prasath 08-Dec-2020
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('hepatitis-reports', 'hepatitis', 'Hepatitis Reports');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'hepatitis-reports', 'hepatitis-clinic-report.php', 'Hepatitis Clinic Reports'), (NULL, 'hepatitis-reports', 'hepatitis-testing-target-report.php', 'Hepatitis Testing Target Reports'), (NULL, 'hepatitis-reports', 'hepatitis-sample-rejection-report.php', 'Hepatitis Sample Rejection Reports'), (NULL, 'hepatitis-reports', 'hepatitis-sample-status.php', 'Hepatitis Sample Status Reports');
-- Thana 04-Dec-2020
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'hepatitis-requests', 'add-samples-from-manifest.php', 'Add Samples from Manifest');
DELETE FROM `global_config` WHERE name = 'hepatitis_report_type';

-- Prasath 09-Dec-2020
UPDATE `privileges` SET  `display_name` = "Suppressed Target report"  where `privilege_name` = 'vlSuppressedTargetReport.php';
-- Thana 10-Dec-2020
ALTER TABLE `vl_imported_controls` ADD `tested_by` VARCHAR(255) NULL DEFAULT NULL AFTER `lot_expiration_date`;

-- Amit 11-Dec-2020

ALTER TABLE `eid_imported_controls` ADD `tested_by` VARCHAR(255) NULL DEFAULT NULL AFTER `lot_expiration_date`;
ALTER TABLE `covid19_imported_controls` ADD `tested_by` VARCHAR(255) NULL DEFAULT NULL AFTER `lot_expiration_date`;
-- Thana 11-Dec-2020
ALTER TABLE `form_covid19` ADD `tested_by` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_condition`;



UPDATE `system_config` SET `value` = '4.2.8' WHERE `system_config`.`name` = 'version';
-- Version 4.2.8  -- Amit -- 14-Dec-2020

-- Thana 22-Dec-2020
ALTER TABLE `form_hepatitis` ADD `hepatitis_test_type` VARCHAR(255) NULL DEFAULT NULL AFTER `external_sample_code`;


-- Amit Dec 24 2020
ALTER TABLE `covid19_reasons_for_testing` ADD PRIMARY KEY( `covid19_id`, `reasons_id`);
ALTER TABLE `s_vlsm_instance` ADD PRIMARY KEY(`vlsm_instance_id`);
ALTER TABLE `user_admin_details` ADD PRIMARY KEY(`user_admin_id`);


-- Amit Jan 19 2021

ALTER TABLE `form_covid19` CHANGE `is_sample_rejected` `is_sample_rejected` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL;



UPDATE `system_config` SET `value` = '4.2.9' WHERE `system_config`.`name` = 'version';
-- Version 4.2.9  -- Amit -- 19-Jan-2021

-- Thana 09-02-2021
UPDATE `privileges` SET `privilege_name` = 'covid19MonthlyThresholdReport.php' WHERE `privileges`.`privilege_id` = 145;
UPDATE `privileges` SET `privilege_name` = 'eidMonthlyThresholdReport.php' WHERE `privileges`.`privilege_id` = 144;


-- Prastah M 11-Feb-2021
ALTER TABLE facility_details add column test_type VARCHAR(255) NULL;


-- Amit 18-Feb-2021
RENAME TABLE `r_hepatitis_rick_factors` TO `r_hepatitis_risk_factors`;


UPDATE `system_config` SET `value` = '4.3.0' WHERE `system_config`.`name` = 'version';
-- Version 4.3.0  -- Amit -- 24-Feb-2021

-- Amit 01-Mar-2021
ALTER TABLE `vl_request_form` CHANGE `vlsm_country_id` `vlsm_country_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `eid_form` CHANGE `vlsm_country_id` `vlsm_country_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `form_covid19` CHANGE `vlsm_country_id` `vlsm_country_id` INT(11) NULL DEFAULT NULL;
ALTER TABLE `form_hepatitis` CHANGE `vlsm_country_id` `vlsm_country_id` INT(11) NULL DEFAULT NULL;

-- Amit 13-Mar-2021
ALTER TABLE `form_hepatitis` ADD `tested_by` VARCHAR(255) NULL DEFAULT NULL AFTER `result`;

-- Thanaseelan 23-Mar-2021
ALTER TABLE `form_covid19` ADD `source_of_request` VARCHAR(255) NULL DEFAULT NULL AFTER `lot_number`;



INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('import-results', 'common', 'Import Results using file Import');

UPDATE `privileges` SET `resource_id` = 'import-results', `display_name` = 'Import Results from File' WHERE `privileges`.`privilege_id` = 19; 

-- Amit 26-Mar-2021

ALTER TABLE `temp_sample_import` ADD `test_type` VARCHAR(255) NULL DEFAULT NULL AFTER `sample_type`;
ALTER TABLE `form_hepatitis` ADD `imported_date_time` DATETIME NULL DEFAULT NULL AFTER `import_machine_file_name`;

-- Thana 26-Mar-2021
ALTER TABLE `eid_form` ADD `source_of_request` VARCHAR(50) NULL DEFAULT NULL AFTER `lot_number`;
ALTER TABLE `vl_request_form` ADD `source_of_request` VARCHAR(50) NULL DEFAULT NULL AFTER `vldash_sync`;

-- Thana 05-Apr-2021
CREATE TABLE `lab_report_signatories` (
 `signatory_id` int NOT NULL AUTO_INCREMENT,
 `name_of_signatory` varchar(255) DEFAULT NULL,
 `designation` varchar(255) DEFAULT NULL,
 `signature` varchar(255) DEFAULT NULL,
 `test_types` varchar(255) DEFAULT NULL,
 `lab_id` int DEFAULT NULL,
 `display_order` varchar(50) DEFAULT NULL,
 `added_on` datetime DEFAULT NULL,
 `added_by` varchar(255) DEFAULT NULL,
 `signatory_status` varchar(255) DEFAULT NULL,
 PRIMARY KEY (`signatory_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `lab_report_signatories` ADD FOREIGN KEY (`lab_id`) REFERENCES `facility_details`(`facility_id`) ON DELETE RESTRICT ON UPDATE RESTRICT; 
-- Thana 16-Apr-2021
ALTER TABLE `form_covid19` ADD `investigator_name` VARCHAR(255) NULL DEFAULT NULL AFTER `lab_technician`, ADD `investigator_phone` VARCHAR(255) NULL DEFAULT NULL AFTER `investigator_name`, ADD `investigator_email` VARCHAR(255) NULL DEFAULT NULL AFTER `investigator_phone`, ADD `clinician_name` VARCHAR(255) NULL DEFAULT NULL AFTER `investigator_email`, ADD `clinician_phone` VARCHAR(255) NULL DEFAULT NULL AFTER `clinician_name`, ADD `clinician_email` VARCHAR(255) NULL DEFAULT NULL AFTER `clinician_phone`, ADD `health_outcome` VARCHAR(255) NULL DEFAULT NULL AFTER `clinician_email`, ADD `health_outcome_date` DATE NULL DEFAULT NULL AFTER `health_outcome`; 
-- Thana 19-Apr-2021
ALTER TABLE `form_covid19` ADD `suspected_case` VARCHAR(255) NULL DEFAULT NULL AFTER `date_of_symptom_onset`; 
-- Prasath M 22-Apr-2021
ALTER TABLE `form_covid19` ADD `patient_zone` VARCHAR(255) NULL DEFAULT NULL AFTER `patient_district`;

-- Thana 22-Apr-2021
ALTER TABLE `form_covid19` ADD `if_have_other_diseases` VARCHAR(50) NULL DEFAULT NULL AFTER `result`; 

UPDATE `system_config` SET `value` = '4.3.1' WHERE `system_config`.`name` = 'version';
-- Version 4.3.1  -- Amit -- 26-Apr-2021

-- Thana 27-Apr-2021
CREATE TABLE `track_api_requests` (
 `api_track_id` int NOT NULL AUTO_INCREMENT,
 `requested_by` varchar(255) DEFAULT NULL,
 `requested_on` datetime DEFAULT NULL,
 `number_of_records` varchar(50) DEFAULT NULL,
 `request_type` varchar(50) DEFAULT NULL,
 `test_type` varchar(255) DEFAULT NULL,
 `api_url` text,
 `api_params` text,
 `data_format` varchar(255) DEFAULT NULL,
 PRIMARY KEY (`api_track_id`)
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Amit 02-May-2021

ALTER TABLE `form_hepatitis` ADD `source_of_request` TEXT NULL DEFAULT NULL AFTER `lot_number`;
ALTER TABLE `form_hepatitis` ADD `source_data_dump` TEXT NULL DEFAULT NULL AFTER `source_of_request`;
ALTER TABLE `form_covid19` ADD `source_data_dump` TEXT NULL DEFAULT NULL AFTER `source_of_request`;
ALTER TABLE `eid_form` ADD `source_data_dump` TEXT NULL DEFAULT NULL AFTER `source_of_request`;
ALTER TABLE `vl_request_form` ADD `source_data_dump` TEXT NULL DEFAULT NULL AFTER `source_of_request`;

ALTER TABLE `form_hepatitis` ADD `result_sent_to_source` TEXT NULL DEFAULT NULL AFTER `source_data_dump`;
ALTER TABLE `form_covid19` ADD `result_sent_to_source` TEXT NULL DEFAULT NULL AFTER `source_data_dump`;
ALTER TABLE `eid_form` ADD `result_sent_to_source` TEXT NULL DEFAULT NULL AFTER `source_data_dump`;
ALTER TABLE `vl_request_form` ADD `result_sent_to_source` TEXT NULL DEFAULT NULL AFTER `source_data_dump`;



-- Amit 08 May 2021

UPDATE `resources` SET `module` = 'common' WHERE `resource_id` = 'specimen-referral-manifest';
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'covid-19-requests', 'addSamplesFromManifest.php', 'Add Samples from Manifest');

-- Thana 11-May-2021
ALTER TABLE `form_covid19` CHANGE `sample_code_format` `sample_code_format` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `tested_by` `tested_by` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `source_of_alert` `source_of_alert` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `source_of_alert_other` `source_of_alert_other` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `patient_id` `patient_id` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `patient_name` `patient_name` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `patient_surname` `patient_surname` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `patient_passport_number` `patient_passport_number` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `patient_occupation` `patient_occupation` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `patient_address` `patient_address` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `flight_airline` `flight_airline` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `flight_airport_of_departure` `flight_airport_of_departure` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `reason_of_visit` `reason_of_visit` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `type_of_test_requested` `type_of_test_requested` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `is_result_mail_sent` `is_result_mail_sent` VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT 'no', CHANGE `last_modified_by` `last_modified_by` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_covid19` ADD `app_local_test_req_id` TEXT NULL DEFAULT NULL AFTER `is_result_mail_sent`; 
ALTER TABLE `r_eid_results` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `status`;

-- Prasath M 12-May-2021
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('VL Monthly Target', 'vl_monthly_target', 'no', 'vl', 'enable', null, null, 'active');
-- Thana 12-May-2021
ALTER TABLE `user_details` ADD `testing_user` TEXT NULL DEFAULT NULL AFTER `user_signature`; 


UPDATE `system_config` SET `value` = '4.3.2' WHERE `system_config`.`name` = 'version';
-- Version 4.3.2  -- Amit -- 13-May-2021

-- Amit 24 May 2021
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'covid-19-requests', 'covid-19-dhis2.php', 'DHIS2');


UPDATE `system_config` SET `value` = '4.3.3' WHERE `system_config`.`name` = 'version';
-- Version 4.3.3  -- Amit -- 24-May-2021


-- Amit -- 30-May-2021
UPDATE `system_config` SET `value` = '4.3.4' WHERE `system_config`.`name` = 'version';



-- Amit -- 08-Jun-2021
UPDATE `system_config` SET `value` = '4.3.5' WHERE `system_config`.`name` = 'version';

-- Thana 14-Jun-2021
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('App Menu Name', 'app_menu_name', 'VLSM', 'app', 'no', '2021-06-14 18:47:11', NULL, 'active');
-- Thana 28-Jun-2021
ALTER TABLE `user_details` ADD `app_access` VARCHAR(50) NULL DEFAULT 'no' AFTER `status`; 

-- Thana 29-Jun-2021
ALTER TABLE `roles` ADD `access_type` VARCHAR(256) NULL DEFAULT NULL AFTER `status`;
ALTER TABLE `user_details` DROP COLUMN `testing_user`;
-- Thana 30-Jun-2021
ALTER TABLE `facility_details` ADD `report_format` VARCHAR(256) NULL DEFAULT NULL AFTER `test_type`; 
-- Thana 07-Jul-2021
ALTER TABLE `facility_details` CHANGE `report_format` `report_format` TEXT CHARACTER SET latin1 COLLATE latin1_swedish_ci NULL DEFAULT NULL; 
-- Thana 08-Jul-2021
ALTER TABLE `form_covid19` ADD `asymptomatic` VARCHAR(50) NULL DEFAULT NULL AFTER `number_of_days_sick`; 
-- Thana 09-Jul-2021
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('COVID-19 Report QR Code', 'covid19_report_qr_code', 'no', NULL, 'yes', '2021-07-09 17:32:23', NULL, 'active');

-- Amit -- 15-Jul-2021
UPDATE `system_config` SET `value` = '4.3.6' WHERE `system_config`.`name` = 'version';

-- Amit -- 19-Jul-2021
CREATE TABLE `track_qr_code_page` (
 `tqcp_d` int NOT NULL AUTO_INCREMENT,
 `test_type` varchar(256) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL COMMENT 'vl, eid, covid19 or hepatitis',
 `test_type_id` int NOT NULL,
 `browser` varchar(256) DEFAULT NULL,
 `ip_address` varchar(256) DEFAULT NULL,
 `operating_system` varchar(256) DEFAULT NULL,
 `date_time` datetime DEFAULT NULL,
 PRIMARY KEY (`tqcp_d`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
ALTER TABLE `track_qr_code_page` ADD `sample_code` VARCHAR(256) NULL AFTER `test_type_id`; 


-- Amit -- 22-Jul-2021
UPDATE `system_config` SET `value` = '4.3.7' WHERE `system_config`.`name` = 'version';


UPDATE `system_config` SET `display_name` = 'Testing Lab ID', `name` = 'sc_testing_lab_id' WHERE `system_config`.`name` = 'lab_name'; 
UPDATE `system_config` SET `name` = 'sc_user_type' WHERE `system_config`.`name` = 'user_type'; 
UPDATE `system_config` SET `name` = 'sc_version' WHERE `system_config`.`name` = 'version';


-- Amit -- 25-Jul-2021
UPDATE `system_config` SET `value` = '4.3.8' WHERE `system_config`.`name` = 'sc_version';

-- Thana 28-Jul-2021
ALTER TABLE `form_hepatitis` ADD `lab_technician` VARCHAR(256) NULL DEFAULT NULL AFTER `lab_id`; 
ALTER TABLE `form_hepatitis` ADD `social_category` VARCHAR(256) NULL DEFAULT NULL AFTER `patient_marital_status`; 
ALTER TABLE `s_vlsm_instance` ADD `last_remote_requests_sync` DATETIME NULL DEFAULT NULL AFTER `last_vldash_sync`, ADD `last_remote_results_sync` DATETIME NULL DEFAULT NULL AFTER `last_remote_requests_sync`, ADD `last_remote_reference_data_sync` DATETIME NULL DEFAULT NULL AFTER `last_remote_results_sync`; 


-- Amit -- 28-Jul-2021
UPDATE `system_config` SET `value` = '4.3.9' WHERE `system_config`.`name` = 'sc_version';

-- Thana 29-Jul-2021
CREATE TABLE `geographical_divisions` (
 `geo_id` int NOT NULL AUTO_INCREMENT,
 `geo_name` varchar(256) DEFAULT NULL,
 `geo_code` varchar(256) DEFAULT NULL,
 `geo_parent` varchar(256) NOT NULL DEFAULT '0',
 `geo_status` varchar(256) DEFAULT NULL,
 `created_by` varchar(256) DEFAULT NULL,
 `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
 `data_sync` int NOT NULL DEFAULT '0',
 PRIMARY KEY (`geo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-reference', 'geographical-divisions-details.php', 'Manage Geographical Divisions'), (NULL, 'common-reference', 'add-geographical-divisions.php', 'Add Geographical Divisions'), (NULL, 'common-reference', 'edit-geographical-divisions.php', 'Edit Geographical Divisions');

ALTER TABLE `facility_details` ADD `facility_state_id` VARCHAR(256) NULL DEFAULT NULL AFTER `country`, ADD `facility_district_id` VARCHAR(256) NULL DEFAULT NULL AFTER `facility_state_id`; 

-- Thana 17-Aug-2021
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-reference', 'sync-history.php', 'Sync History');


-- Amit 23 Aug 2021
ALTER TABLE `user_details` ADD `api_token_exipiration_days` INT NULL DEFAULT NULL AFTER `api_token_generated_datetime`;

-- Amit -- 24-Aug-2021
UPDATE `system_config` SET `value` = '4.4.0' WHERE `system_config`.`name` = 'sc_version';

ALTER TABLE `facility_details` CHANGE `facility_state_id` `facility_state_id` INT NULL DEFAULT NULL, CHANGE `facility_district_id` `facility_district_id` INT NULL DEFAULT NULL;
-- Thana 24-Aug-2021
ALTER TABLE `track_api_requests` ADD `facility_id` VARCHAR(256) NULL DEFAULT NULL AFTER `api_params`;
-- Thana 25-Aug-2021
ALTER TABLE `eid_form` ADD `app_local_test_req_id` VARCHAR(256) NULL DEFAULT NULL AFTER `result_mail_datetime`;

-- Thana 06-Sep-2021
ALTER TABLE `covid19_tests` ADD `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `result`;

-- Amit 17 Sep 2021
UPDATE `global_config` SET `remote_sync_needed` = 'yes', `value` = 'no' WHERE `name` = 'vl_monthly_target';


-- Thana 22-Sep-2021
ALTER TABLE `vl_request_form` ADD `app_local_test_req_id` VARCHAR(256) NULL DEFAULT NULL AFTER `is_result_mail_sent`;

-- Amit 24 Sep 2021
ALTER TABLE `vl_request_form` CHANGE `vldash_sync` `vldash_sync` INT(11) NULL DEFAULT '0';
ALTER TABLE `vl_request_form` CHANGE `vlsm_country_id` `vlsm_country_id` INT(11) NULL DEFAULT NULL;

UPDATE `system_config` SET `value` = '4.4.1' WHERE `system_config`.`name` = 'sc_version';


-- Amit 27 Sep 2021
ALTER TABLE `facility_details` ADD UNIQUE(`facility_name`);
ALTER TABLE `facility_details` ADD UNIQUE(`facility_code`);
ALTER TABLE `facility_details` ADD UNIQUE(`other_id`);
-- ALTER TABLE `form_hepatitis` ADD UNIQUE(`source_of_request`);
-- Thana 28-Sep-2021
CREATE TABLE `failed_result_retest_tracker` (
 `frrt_id` int NOT NULL AUTO_INCREMENT,
 `test_type_pid` int DEFAULT NULL,
 `test_type` varchar(256) DEFAULT NULL,
 `sample_code` varchar(256) DEFAULT NULL,
 `result` varchar(256) DEFAULT NULL,
 `result_status` varchar(256) DEFAULT NULL,
 `updated_datetime` datetime DEFAULT NULL,
 `update_by` varchar(256) DEFAULT NULL,
 PRIMARY KEY (`frrt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Amit 30 Sep 2021
ALTER TABLE `form_hepatitis` CHANGE `source_of_request` `source_of_request` VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_covid19` CHANGE `source_of_request` `source_of_request` VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
-- ALTER TABLE `form_hepatitis` ADD UNIQUE(`source_of_request`);
-- ALTER TABLE `form_covid19` ADD UNIQUE(`source_of_request`);

-- Thana 30-Sep-2021
ALTER TABLE `failed_result_retest_tracker` ADD `remote_sample_code` VARCHAR(256) NULL DEFAULT NULL AFTER `sample_code`, ADD `batch_id` VARCHAR(256) NULL DEFAULT NULL AFTER `remote_sample_code`, ADD `facility_id` VARCHAR(256) NULL DEFAULT NULL AFTER `batch_id`;
UPDATE `resources` SET `resource_id` = 'hepatitis-management' WHERE `resources`.`resource_id` = 'hepatitis-reports';
UPDATE `privileges` SET `resource_id` = 'hepatitis-management' WHERE `privileges`.`resource_id` = 'hepatitis-reports';
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'hepatitis-management', 'hepatitis-export-data.php', 'Hepatitis Export');


-- Amit 01-Oct-2021
ALTER TABLE `form_covid19` CHANGE `clinician_phone` `clinician_phone` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `clinician_email` `clinician_email` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `health_outcome` `health_outcome` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `lab_reception_person` `lab_reception_person` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `covid19_test_platform` `covid19_test_platform` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `authorized_by` `authorized_by` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `result_reviewed_by` `result_reviewed_by` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `result_approved_by` `result_approved_by` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `import_machine_name` `import_machine_name` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `import_machine_file_name` `import_machine_file_name` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `sample_package_id` `sample_package_id` VARCHAR(256) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `sample_package_code` `sample_package_code` TEXT CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `source_of_request` `source_of_request` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL, CHANGE `app_local_test_req_id` `app_local_test_req_id` VARCHAR(1000) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_covid19` ADD `covid19_test_name` VARCHAR(500) NULL DEFAULT NULL AFTER `covid19_test_platform`;

-- Amit 06-Oct-2021
ALTER TABLE `vl_request_form` ADD `unique_id` VARCHAR(500) NULL AFTER `vl_sample_id`;
UPDATE `vl_request_form` set unique_id = sha1(remote_sample_code) WHERE remote_sample_code is not null and unique_id is null;
UPDATE `vl_request_form` set unique_id = sha1(CONCAT(`facility_id`, `sample_code`)) WHERE remote_sample_code is null and sample_code is not null and unique_id is null;
ALTER TABLE `vl_request_form` ADD UNIQUE(`unique_id`);

ALTER TABLE `eid_form` ADD `unique_id` VARCHAR(500) NULL AFTER `eid_id`;
UPDATE `eid_form` set unique_id = sha1(remote_sample_code) WHERE remote_sample_code is not null and unique_id is null;
UPDATE `eid_form` set unique_id = sha1(CONCAT(`facility_id`, `sample_code`)) WHERE remote_sample_code is null and sample_code is not null and unique_id is null;
ALTER TABLE `eid_form` ADD UNIQUE(`unique_id`);


ALTER TABLE `form_covid19` ADD `unique_id` VARCHAR(500) NULL AFTER `covid19_id`;
UPDATE `form_covid19` set unique_id = sha1(remote_sample_code) WHERE remote_sample_code is not null and unique_id is null;
UPDATE `form_covid19` set unique_id = sha1(CONCAT(`facility_id`, `sample_code`)) WHERE remote_sample_code is null and sample_code is not null and unique_id is null;
ALTER TABLE `form_covid19` ADD UNIQUE(`unique_id`);


ALTER TABLE `form_hepatitis` ADD `unique_id` VARCHAR(500) NULL AFTER `hepatitis_id`;
UPDATE `form_hepatitis` set unique_id = sha1(remote_sample_code) WHERE remote_sample_code is not null and unique_id is null;
UPDATE `form_hepatitis` set unique_id = sha1(CONCAT(`facility_id`, `sample_code`)) WHERE remote_sample_code is null and sample_code is not null and unique_id is null;
ALTER TABLE `form_hepatitis` ADD UNIQUE(`unique_id`);



ALTER TABLE `form_covid19` DROP INDEX `source_of_request`;
ALTER TABLE `form_hepatitis` DROP INDEX `source_of_request`;


-- Thana 06-Oct-2021
ALTER TABLE `vl_request_form` ADD `revised_by` VARCHAR(500) NULL DEFAULT NULL AFTER `result_approved_datetime`, ADD `revised_on` DATETIME NULL DEFAULT NULL AFTER `revised_by`;
ALTER TABLE `eid_form` ADD `revised_by` VARCHAR(500) NULL DEFAULT NULL AFTER `result_approved_datetime`, ADD `revised_on` DATETIME NULL DEFAULT NULL AFTER `revised_by`;
ALTER TABLE `form_covid19` ADD `revised_by` VARCHAR(500) NULL DEFAULT NULL AFTER `authorized_on`, ADD `revised_on` DATETIME NULL DEFAULT NULL AFTER `revised_by`;
ALTER TABLE `form_hepatitis` ADD `revised_by` VARCHAR(500) NULL DEFAULT NULL AFTER `authorized_on`, ADD `revised_on` DATETIME NULL DEFAULT NULL AFTER `revised_by`;
-- Thana 07-Oct-2021
ALTER TABLE `eid_form` ADD `reason_for_changing` VARCHAR(256) NULL DEFAULT NULL AFTER `result`;

-- Amit 18 Oct 2021 version 4.4.2
UPDATE `system_config` SET `value` = '4.4.2' WHERE `system_config`.`name` = 'sc_version';

-- Thana 19-Oct-2021
ALTER TABLE `failed_result_retest_tracker` ADD `sample_data` TEXT NOT NULL AFTER `sample_code`;
-- ALTER TABLE `vl_request_form` ADD `sample_reordered` VARCHAR(256) NOT NULL DEFAULT 'no' AFTER `sample_code`;
ALTER TABLE `eid_form` ADD `sample_reordered` VARCHAR(256) NOT NULL DEFAULT 'no' AFTER `sample_code`;
ALTER TABLE `form_covid19` ADD `sample_reordered` VARCHAR(256) NOT NULL DEFAULT 'no' AFTER `sample_code`;
ALTER TABLE `form_hepatitis` ADD `sample_reordered` VARCHAR(256) NOT NULL DEFAULT 'no' AFTER `sample_code`;

-- Thana 21-Oct-2021
ALTER TABLE `vl_request_form` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `eid_form` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

-- Amit 23-Oct-2021
UPDATE vl_request_form set vl_result_category = null;

-- Amit 25-Oct-2021
ALTER TABLE `geographical_divisions` ADD UNIQUE( `geo_name`, `geo_parent`);

-- Amit 28 Oct 2021 version 4.4.3
UPDATE `system_config` SET `value` = '4.4.3' WHERE `system_config`.`name` = 'sc_version';



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


ALTER DATABASE vlsm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
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
  `patient_code_prefix` varchar(256) NOT NULL,
  `patient_code_key` int(11) NOT NULL,
  `patient_code` varchar(256) NOT NULL,
  `patient_first_name` text,
  `patient_middle_name` text,
  `patient_last_name` text,
  `patient_gender` varchar(256) DEFAULT NULL,
  `patient_province` int(11) DEFAULT NULL,
  `patient_district` int(11) DEFAULT NULL,
  `patient_registered_on` datetime DEFAULT NULL,
  `patient_registered_by` text,
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


-- Amit 12-Apr-2022 version 4.5.0
UPDATE `system_config` SET `value` = '4.5.0' WHERE `system_config`.`name` = 'sc_version';


-- Thana 18-Apr-2022 version 4.5.0
ALTER TABLE `form_eid` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `tested_by`;

-- Amit 28-Apr-2022
UPDATE `privileges` SET `privilege_name` = 'vl-export-data.php', `display_name` = 'Export VL Data' WHERE `privileges`.`privilege_id` = 23;
ALTER TABLE `form_vl` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `tested_by`;

-- Amit 02-May-2022
ALTER TABLE `form_covid19` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `tested_by`;
ALTER TABLE `form_hepatitis` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `tested_by`;
ALTER TABLE `form_tb` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `tested_by`;
ALTER TABLE `temp_sample_import` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `result_reviewed_by`;
ALTER TABLE `hold_sample_import` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `result_reviewed_by`;
ALTER TABLE `covid19_imported_controls` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `result_reviewed_by`;
ALTER TABLE `vl_imported_controls` ADD `lab_tech_comments` MEDIUMTEXT NULL DEFAULT NULL AFTER `result_reviewed_by`;
UPDATE `form_vl` SET `lab_tech_comments` = approver_comments;
UPDATE `form_eid` SET `lab_tech_comments` = approver_comments;
UPDATE `form_covid19` SET `lab_tech_comments` = approver_comments;
UPDATE `form_hepatitis` SET `lab_tech_comments` = approver_comments;
UPDATE `form_tb` SET `lab_tech_comments` = approver_comments;
UPDATE `temp_sample_import` SET `lab_tech_comments` = approver_comments;
UPDATE `hold_sample_import` SET `lab_tech_comments` = approver_comments;
UPDATE `covid19_imported_controls` SET `lab_tech_comments` = approver_comments;


-- Amit 02-May-2022 version 4.5.1
UPDATE `system_config` SET `value` = '4.5.1' WHERE `system_config`.`name` = 'sc_version';

-- Amit 09-May-2022
INSERT INTO `global_config` 
(`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) 
VALUES 
('VL Auto Approve API Results', 'vl_auto_approve_api_results', 'no', 'vl', 'no', NULL, NULL, 'active'), 
('EID Auto Approve API Results', 'eid_auto_approve_api_results', 'no', 'eid', 'no', NULL, NULL, 'active'), 
('COVID-19 Auto Approve API Results', 'covid19_auto_approve_api_results', 'no', 'covid19', 'no', NULL, NULL, 'active'), 
('Hepatitis Auto Approve API Results', 'hepatitis_auto_approve_api_results', 'no', 'hepatitis', 'no', NULL, NULL, 'active'), 
('TB Auto Approve API Results', 'tb_auto_approve_api_results', 'no', 'tb', 'no', NULL, NULL, 'active');

-- Amit 21-Jun-2022 version 4.5.2
UPDATE `system_config` SET `value` = '4.5.2' WHERE `system_config`.`name` = 'sc_version';


-- Amit 27-Jun-2022 version 4.5.3
UPDATE `system_config` SET `value` = '4.5.3' WHERE `system_config`.`name` = 'sc_version';


-- Jeyabanu 1-July-2022
UPDATE `privileges` SET `privilege_name` = 'activity-log.php', `display_name` = 'User Activity Log' WHERE `privileges`.`privilege_name` = 'audit-trail.php';
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES 
(NULL, 'common-reference', 'audit-trail.php', 'Audit Trail');

-- Thana 06-Jul-2022
ALTER TABLE `user_details` ADD `hash_algorithm` VARCHAR(256) NOT NULL DEFAULT 'sha1' AFTER `app_access`;

-- Amit 08-Jul-2022
ALTER TABLE `user_login_history` CHANGE `login_id` `login_id` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE `user_login_history` ADD `user_id` VARCHAR(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL AFTER `history_id`;

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

-- Amit 20-Jan-2023 version 5.1.0
UPDATE `system_config` SET `value` = '5.1.0' WHERE `system_config`.`name` = 'sc_version';

-- Thana 31-Jan-2023
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'specimen-referral-manifest', 'move-manifest.php', 'Move Samples');

-- Amit 06-Feb-2023
ALTER TABLE `package_details` ADD `number_of_samples` INT NULL DEFAULT NULL AFTER `lab_id`;
ALTER TABLE `package_details` ADD `last_modified_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `request_created_datetime`;

UPDATE package_details A 
INNER JOIN (SELECT sample_package_code, COUNT(*) sampleCount FROM form_vl GROUP BY sample_package_code) as B
  ON B.sample_package_code = A.package_code 
SET A.number_of_samples = B.sampleCount;

UPDATE package_details A 
INNER JOIN (SELECT sample_package_code, COUNT(*) sampleCount FROM form_eid GROUP BY sample_package_code) as B
  ON B.sample_package_code = A.package_code 
SET A.number_of_samples = B.sampleCount;

UPDATE package_details A 
INNER JOIN (SELECT sample_package_code, COUNT(*) sampleCount FROM form_covid19 GROUP BY sample_package_code) as B
  ON B.sample_package_code = A.package_code 
SET A.number_of_samples = B.sampleCount;

UPDATE package_details A 
INNER JOIN (SELECT sample_package_code, COUNT(*) sampleCount FROM form_hepatitis GROUP BY sample_package_code) as B
  ON B.sample_package_code = A.package_code 
SET A.number_of_samples = B.sampleCount;

-- Amit 13-Feb-2023
ALTER TABLE `form_vl` ADD INDEX(`sample_package_id`);
ALTER TABLE `form_eid` ADD INDEX(`sample_package_id`);
ALTER TABLE `form_covid19` ADD INDEX(`sample_package_id`);
ALTER TABLE `form_hepatitis` ADD INDEX(`sample_package_id`);
ALTER TABLE `form_tb` CHANGE `sample_package_id` `sample_package_id` INT NULL DEFAULT NULL;
ALTER TABLE `form_tb` ADD INDEX(`sample_package_id`);


-- Amit 16-Feb-2023 version 5.1.1
UPDATE `system_config` SET `value` = '5.1.1' WHERE `system_config`.`name` = 'sc_version';


-- Amit 01-Mar-2023 version 5.1.2
UPDATE `system_config` SET `value` = '5.1.2' WHERE `system_config`.`name` = 'sc_version';


-- Jeyabanu 10-03-2023
ALTER TABLE `r_eid_results` CHANGE `result_id` `result_id` varchar(256) NOT NULL;

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) 
VALUES ('Show Participant Name in Manifest', 'vl_show_participant_name_in_manifest', 'yes', 'VL', 'no', null, null, 'active');

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) 
VALUES ('Show Participant Name in Manifest', 'eid_show_participant_name_in_manifest', 'yes', 'EID', 'no', null, null, 'active');

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) 
VALUES ('Show Participant Name in Manifest', 'covid19_show_participant_name_in_manifest', 'yes', 'COVID19', 'no', null, null, 'active');

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) 
VALUES ('Show Participant Name in Manifest', 'hepatitis_show_participant_name_in_manifest', 'yes', 'HEPATITIS', 'no', null, null, 'active');

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) 
VALUES ('Show Participant Name in Manifest', 'tb_show_participant_name_in_manifest', 'yes', 'TB', 'no', null, null, 'active');

ALTER TABLE `form_vl` CHANGE `request_created_datetime` `request_created_datetime` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `form_eid` CHANGE `request_created_datetime` `request_created_datetime` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `form_covid19` CHANGE `request_created_datetime` `request_created_datetime` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `form_hepatitis` CHANGE `request_created_datetime` `request_created_datetime` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP;


-- Amit 17-Mar-2023
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) 
VALUES ('Interpret and Convert VL Results', 'vl_interpret_and_convert_results', 'no', 'VL', 'yes', null, null, 'active');


-- Jeyabanu 21-Mar-2023
ALTER TABLE `batch_details` ADD `created_by` VARCHAR(256) NULL DEFAULT NULL AFTER `label_order`;

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('VL Report QR Code', 'vl_report_qr_code', 'yes', 'vl', 'no', NULL, NULL, 'active');

ALTER TABLE `form_vl` CHANGE `data_sync` `data_sync` INT NOT NULL DEFAULT 0;

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Key', 'key', null, 'general', 'yes', NULL, NULL, 'active');

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES 
            ('EID Report QR Code', 'eid_report_qr_code', 'yes', 'EID', 'no', NULL, NULL, 'active'),
            ('Hepatitis Report QR Code', 'hepatitis_report_qr_code', 'yes', NULL, NULL, NULL, NULL, 'active');

-- Amit 23-Mar-2023
ALTER TABLE `r_eid_results` CHANGE `result_id` `result_id` varchar(256) NOT NULL;
ALTER TABLE `r_tb_results` CHANGE `result_id` `result_id` varchar(256) NOT NULL;

-- Amit 29-Mar-2023
UPDATE `s_available_country_forms` SET `form_name` = 'Sierra Leone' WHERE `vlsm_country_id` = 2;
UPDATE `s_available_country_forms` SET `form_name` = 'Democratic Republic of the Congo' WHERE `vlsm_country_id` = 3;
UPDATE `s_available_country_forms` SET `form_name` = REPLACE(form_name, "Form", "");
UPDATE `s_available_country_forms` SET `form_name` = REPLACE(form_name, "FORM", "");

-- Jeyabanu 30-Mar-2023
ALTER TABLE `form_vl` ADD `treatment_duration` TEXT NULL DEFAULT NULL AFTER `treatment_initiated_date`;
ALTER TABLE `form_vl` ADD `treatment_indication` TEXT NULL DEFAULT NULL AFTER `treatment_duration`;
ALTER TABLE `form_vl` ADD `patient_has_active_tb` TEXT NULL DEFAULT NULL AFTER `is_patient_breastfeeding`;
ALTER TABLE `form_vl` ADD `patient_active_tb_phase` TEXT NULL DEFAULT NULL AFTER `patient_has_active_tb`;
ALTER TABLE `form_vl` ADD `line_of_treatment_failure_assessed` TEXT NULL DEFAULT NULL AFTER `line_of_treatment`;

ALTER TABLE `audit_form_vl` ADD `treatment_duration` TEXT NULL DEFAULT NULL AFTER `treatment_initiated_date`;
ALTER TABLE `audit_form_vl` ADD `treatment_indication` TEXT NULL DEFAULT NULL AFTER `treatment_duration`;
ALTER TABLE `audit_form_vl` ADD `patient_has_active_tb` TEXT NULL DEFAULT NULL AFTER `is_patient_breastfeeding`;
ALTER TABLE `audit_form_vl` ADD `patient_active_tb_phase` TEXT NULL DEFAULT NULL AFTER `patient_has_active_tb`;
ALTER TABLE `audit_form_vl` ADD `line_of_treatment_failure_assessed` TEXT NULL DEFAULT NULL AFTER `line_of_treatment`;

ALTER TABLE `form_eid` ADD `lab_testing_point` TEXT NULL DEFAULT NULL AFTER `lab_id`;
ALTER TABLE `audit_form_eid` ADD `lab_testing_point` TEXT NULL DEFAULT NULL AFTER `lab_id`;

ALTER TABLE `form_eid` ADD `infant_on_pmtct_prophylaxis` TEXT NULL DEFAULT NULL AFTER `has_infant_stopped_breastfeeding`, ADD `infant_on_ctx_prophylaxis` TEXT NULL DEFAULT NULL AFTER `infant_on_pmtct_prophylaxis`;
ALTER TABLE `audit_form_eid` ADD `infant_on_pmtct_prophylaxis` TEXT NULL DEFAULT NULL AFTER `has_infant_stopped_breastfeeding`, ADD `infant_on_ctx_prophylaxis` TEXT NULL DEFAULT NULL AFTER `infant_on_pmtct_prophylaxis`;

ALTER TABLE `form_eid` ADD `pcr_test_number` INT NULL DEFAULT NULL AFTER `pcr_test_performed_before`;
ALTER TABLE `audit_form_eid` ADD `pcr_test_number` INT NULL DEFAULT NULL AFTER `pcr_test_performed_before`;

ALTER TABLE `form_eid` ADD `reason_for_repeat_pcr_other` TEXT NULL DEFAULT NULL AFTER `reason_for_pcr`;
ALTER TABLE `audit_form_eid` ADD `reason_for_repeat_pcr_other` TEXT NULL DEFAULT NULL AFTER `reason_for_pcr`;

ALTER TABLE `form_eid` ADD `mother_regimen` TEXT NULL DEFAULT NULL AFTER `mother_treatment`;
ALTER TABLE `audit_form_eid` ADD `mother_regimen` TEXT NULL DEFAULT NULL AFTER `mother_treatment`;

ALTER TABLE `form_tb` ADD `previously_treated_for_tb` TEXT NULL DEFAULT NULL AFTER `hiv_status`;
ALTER TABLE `audit_form_tb` ADD `previously_treated_for_tb` TEXT NULL DEFAULT NULL AFTER `hiv_status`;

ALTER TABLE `form_tb` ADD `number_of_sputum_samples` INT NULL DEFAULT NULL AFTER `tests_requested`;
ALTER TABLE `audit_form_tb` ADD `number_of_sputum_samples` INT NULL DEFAULT NULL AFTER `tests_requested`;

ALTER TABLE `form_tb` ADD `first_sputum_samples_collection_date` DATE NULL DEFAULT NULL AFTER `number_of_sputum_samples`;
ALTER TABLE `audit_form_tb` ADD `first_sputum_samples_collection_date` DATE NULL DEFAULT NULL AFTER `number_of_sputum_samples`;

ALTER TABLE `form_tb` ADD `result_date` DATETIME NULL DEFAULT NULL AFTER `tested_by`;
ALTER TABLE `audit_form_tb` ADD `result_date` DATETIME NULL DEFAULT NULL AFTER `tested_by`;

-- Amit 03-Apr-2023
ALTER TABLE `log_result_updates` CHANGE `user_id` `user_id` TEXT NULL DEFAULT NULL;

-- Amit 12-Apr-2023
ALTER TABLE `form_vl` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `form_eid` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `form_covid19` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `form_hepatitis` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `form_tb` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

ALTER TABLE `audit_form_vl` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_eid` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_covid19` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_hepatitis` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_tb` CHANGE `locked` `locked` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

UPDATE `form_vl` SET locked = 'no' WHERE locked is null or locked not like 'yes';
UPDATE `form_eid` SET locked = 'no' WHERE locked is null or locked not like 'yes';
UPDATE `form_covid19` SET locked = 'no' WHERE locked is null or locked not like 'yes';
UPDATE `form_hepatitis` SET locked = 'no' WHERE locked is null or locked not like 'yes';
UPDATE `form_tb` SET locked = 'no' WHERE locked is null or locked not like 'yes';

-- ilahir 13-Apr-2023

CREATE TABLE `r_test_types` (
  `test_type_id` int NOT NULL,
  `test_standard_name` varchar(256) DEFAULT NULL,
  `test_generic_name` varchar(256) DEFAULT NULL,
  `test_short_code` varchar(256) DEFAULT NULL,
  `test_loinc_code` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `test_form_config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `test_results_config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci,
  `test_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `r_test_types`
  ADD PRIMARY KEY (`test_type_id`);

ALTER TABLE `r_test_types`
  MODIFY `test_type_id` int NOT NULL AUTO_INCREMENT;

INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('test-type', 'admin', 'Manage Test Type');

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'test-type', 'testType.php', 'Access'), (NULL, 'test-type', 'addTestType.php', 'Add');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'test-type', 'editTestType.php', 'Edit');



-- Amit 18-Apr-2023 version 5.1.3
UPDATE `system_config` SET `value` = '5.1.3' WHERE `system_config`.`name` = 'sc_version';


-- ilahir 20-Apr-2023

ALTER TABLE `r_test_types` ADD `updated_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `test_status`;

INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('common-sample-type', 'admin', 'Common Sample Type Table');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-sample-type', 'addSampleType.php', 'Add'), (NULL, 'common-sample-type', 'sampleType.php', 'Access');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-sample-type', 'editSampleType.php', 'Edit');

CREATE TABLE `r_sample_types` (
  `sample_type_id` int NOT NULL,
  `sample_type_code` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sample_type_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sample_type_status` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `r_sample_types`
  ADD PRIMARY KEY (`sample_type_id`),
  ADD UNIQUE KEY `sample_type_code` (`sample_type_code`),
  ADD UNIQUE KEY `sample_type_name` (`sample_type_name`);


ALTER TABLE `r_sample_types`
  MODIFY `sample_type_id` int NOT NULL AUTO_INCREMENT;

INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('common-testing-reason', 'admin', 'Common Testing Reason Table');

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-testing-reason', 'testingReason.php', 'Access'), (NULL, 'common-testing-reason', 'editTestingReason.php', 'Edit');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-testing-reason', 'addTestingReason.php', 'Add');


CREATE TABLE `r_testing_reasons` (
  `test_reason_id` int NOT NULL,
  `test_reason_code` varchar(256) DEFAULT NULL,
  `test_reason` varchar(256) DEFAULT NULL,
  `test_reason_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `r_testing_reasons`
  ADD PRIMARY KEY (`test_reason_id`),
  ADD UNIQUE KEY `test_reason_code` (`test_reason_code`),
  ADD UNIQUE KEY `test_reason` (`test_reason`);

ALTER TABLE `r_testing_reasons`
  MODIFY `test_reason_id` int NOT NULL AUTO_INCREMENT;


-- Amit 25-Apr-2023
ALTER TABLE `s_available_country_forms` ADD `short_name` VARCHAR(256) NOT NULL AFTER `form_name`;
UPDATE `s_available_country_forms` SET `short_name` = 'ssudan' WHERE `s_available_country_forms`.`vlsm_country_id` = 1; UPDATE `s_available_country_forms` SET `short_name` = 'sierra-leone' WHERE `s_available_country_forms`.`vlsm_country_id` = 2; UPDATE `s_available_country_forms` SET `short_name` = 'drc' WHERE `s_available_country_forms`.`vlsm_country_id` = 3; UPDATE `s_available_country_forms` SET `short_name` = 'zambia' WHERE `s_available_country_forms`.`vlsm_country_id` = 4; UPDATE `s_available_country_forms` SET `short_name` = 'png' WHERE `s_available_country_forms`.`vlsm_country_id` = 5; UPDATE `s_available_country_forms` SET `short_name` = 'who' WHERE `s_available_country_forms`.`vlsm_country_id` = 6; UPDATE `s_available_country_forms` SET `short_name` = 'rwanda' WHERE `s_available_country_forms`.`vlsm_country_id` = 7; UPDATE `s_available_country_forms` SET `short_name` = 'angola' WHERE `s_available_country_forms`.`vlsm_country_id` = 8;
-- ilahir 24-Apr-2023

INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('common-symptoms', 'admin', 'Common Symptoms Table');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-symptoms', 'symptoms.php', 'Access');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-symptoms', 'addSymptoms.php', 'Add');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-symptoms', 'editSymptoms.php', 'Edit');

CREATE TABLE `r_symptoms` (
  `symptom_id` int NOT NULL,
  `symptom_name` varchar(256) DEFAULT NULL,
  `symptom_code` varchar(256) DEFAULT NULL,
  `symptom_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `r_symptoms`
  ADD PRIMARY KEY (`symptom_id`),
  ADD UNIQUE KEY `symptom_code` (`symptom_code`),
  ADD UNIQUE KEY `symptom_name` (`symptom_name`);

ALTER TABLE `r_symptoms`
  MODIFY `symptom_id` int NOT NULL AUTO_INCREMENT;


-- ilahir 25-Apr-2023

CREATE TABLE `generic_test_sample_type_map` (
  `map_id` int NOT NULL,
  `sample_type_id` int NOT NULL,
  `test_type_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `generic_test_sample_type_map`
  ADD PRIMARY KEY (`map_id`),
  ADD KEY `sample_type_id` (`sample_type_id`),
  ADD KEY `test_type_id` (`test_type_id`);

ALTER TABLE `generic_test_sample_type_map`
  MODIFY `map_id` int NOT NULL AUTO_INCREMENT;


ALTER TABLE `generic_test_sample_type_map`
  ADD CONSTRAINT `generic_test_sample_type_map_ibfk_1` FOREIGN KEY (`sample_type_id`) REFERENCES `r_sample_types` (`sample_type_id`),
  ADD CONSTRAINT `generic_test_sample_type_map_ibfk_2` FOREIGN KEY (`test_type_id`) REFERENCES `r_test_types` (`test_type_id`);


CREATE TABLE `generic_test_reason_map` (
  `map_id` int NOT NULL,
  `test_reason_id` int NOT NULL,
  `test_type_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `generic_test_reason_map`
  ADD PRIMARY KEY (`map_id`),
  ADD KEY `test_type_id` (`test_type_id`),
  ADD KEY `test_reason_id` (`test_reason_id`);

ALTER TABLE `generic_test_reason_map`
  MODIFY `map_id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `generic_test_reason_map`
  ADD CONSTRAINT `generic_test_reason_map_ibfk_1` FOREIGN KEY (`test_type_id`) REFERENCES `r_test_types` (`test_type_id`),
  ADD CONSTRAINT `generic_test_reason_map_ibfk_2` FOREIGN KEY (`test_reason_id`) REFERENCES `r_testing_reasons` (`test_reason_id`);


CREATE TABLE `generic_test_symptoms_map` (
  `map_id` int NOT NULL,
  `symptom_id` int NOT NULL,
  `test_type_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `generic_test_symptoms_map`
  ADD PRIMARY KEY (`map_id`),
  ADD KEY `symptom_id` (`symptom_id`),
  ADD KEY `test_type_id` (`test_type_id`);

ALTER TABLE `generic_test_symptoms_map`
  MODIFY `map_id` int NOT NULL AUTO_INCREMENT;

ALTER TABLE `generic_test_symptoms_map`
  ADD CONSTRAINT `generic_test_symptoms_map_ibfk_1` FOREIGN KEY (`symptom_id`) REFERENCES `r_symptoms` (`symptom_id`),
  ADD CONSTRAINT `generic_test_symptoms_map_ibfk_2` FOREIGN KEY (`test_type_id`) REFERENCES `r_test_types` (`test_type_id`);

-- Thana 28-Apr-2023
ALTER TABLE `instruments` ADD `updated_datetime` DATETIME NULL DEFAULT NULL AFTER `status`;

-- Jeyabanu 29-Apr-2023

CREATE TABLE `form_generic` (
  `sample_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `test_type` int(11) DEFAULT NULL,
  `vlsm_instance_id` varchar(255) NOT NULL,
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `remote_sample_code` varchar(500) DEFAULT NULL,
  `remote_sample_code_format` varchar(255) DEFAULT NULL,
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `sample_code` varchar(500) DEFAULT NULL,
  `sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `external_sample_code` varchar(256) DEFAULT NULL,
  `app_sample_code` varchar(256) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` varchar(255) DEFAULT NULL,
  `facility_sample_id` varchar(255) DEFAULT NULL,
  `sample_batch_id` varchar(11) DEFAULT NULL,
  `sample_package_id` varchar(11) DEFAULT NULL,
  `sample_package_code` text,
  `sample_reordered` varchar(45) NOT NULL DEFAULT 'no',
  `test_urgency` varchar(255) DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `patient_first_name` text,
  `patient_middle_name` text,
  `patient_last_name` text,
  `patient_attendant` text,
  `patient_nationality` int(11) DEFAULT NULL,
  `patient_province` text,
  `patient_district` text,
  `patient_group` text,
  `patient_id` varchar(256) DEFAULT NULL,
  `patient_dob` date DEFAULT NULL,
  `patient_gender` text,
  `patient_mobile_number` text,
  `patient_location` text,
  `patient_address` mediumtext,
  `sample_collection_date` datetime DEFAULT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_type` int(11) DEFAULT NULL,
  `treatment_initiation` text,
  `is_patient_pregnant` text,
  `is_patient_breastfeeding` text,
  `pregnancy_trimester` int(11) DEFAULT NULL,
  `consent_to_receive_sms` text,
  `request_clinician_name` text,
  `test_requested_on` date DEFAULT NULL,
  `request_clinician_phone_number` varchar(255) DEFAULT NULL,
  `sample_testing_date` datetime DEFAULT NULL,
  `testing_lab_focal_person` text,
  `testing_lab_focal_person_phone_number` text,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_testing_lab_datetime` datetime DEFAULT NULL,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `sample_rejection_facility` int(11) DEFAULT NULL,
  `reason_for_sample_rejection` int(11) DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `request_created_by` varchar(500) NOT NULL,
  `request_created_datetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_modified_by` text,
  `last_modified_datetime` datetime DEFAULT NULL,
  `patient_other_id` text,
  `patient_age_in_years` varchar(255) DEFAULT NULL,
  `patient_age_in_months` varchar(255) DEFAULT NULL,
  `treatment_initiated_date` date DEFAULT NULL,
  `treatment_indication` text,
  `treatment_details` mediumtext,
  `lab_name` text,
  `lab_id` int(11) DEFAULT NULL,
  `samples_referred_datetime` datetime DEFAULT NULL,
  `referring_lab_id` int(11) DEFAULT NULL,
  `lab_code` int(11) DEFAULT NULL,
  `lab_technician` text,
  `lab_contact_person` text,
  `lab_phone_number` text,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `result` text,
  `final_result_interpretation` text,
  `approver_comments` mediumtext,
  `reason_for_test_result_changes` mediumtext,
  `lot_number` text,
  `lot_expiration_date` date DEFAULT NULL,
  `tested_by` text,
  `lab_tech_comments` mediumtext,
  `result_approved_by` varchar(256) DEFAULT NULL,
  `result_approved_datetime` datetime DEFAULT NULL,
  `revised_by` text,
  `revised_on` datetime DEFAULT NULL,
  `result_reviewed_by` varchar(256) DEFAULT NULL,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `test_methods` text,
  `reason_for_testing` text,
  `reason_for_testing_other` text,
  `sample_collected_by` text,
  `facility_comments` mediumtext,
  `test_platform` text,
  `import_machine_name` int(11) DEFAULT NULL,
  `physician_name` text,
  `date_test_ordered_by_physician` date DEFAULT NULL,
  `test_number` text,
  `result_printed_datetime` datetime DEFAULT NULL,
  `result_sms_sent_datetime` datetime DEFAULT NULL,
  `is_request_mail_sent` varchar(500) NOT NULL DEFAULT 'no',
  `request_mail_datetime` datetime DEFAULT NULL,
  `is_result_mail_sent` varchar(500) NOT NULL DEFAULT 'no',
  `result_mail_datetime` datetime DEFAULT NULL,
  `is_result_sms_sent` varchar(45) NOT NULL DEFAULT 'no',
  `test_request_export` int(11) NOT NULL DEFAULT '0',
  `test_request_import` int(11) NOT NULL DEFAULT '0',
  `test_result_export` int(11) NOT NULL DEFAULT '0',
  `test_result_import` int(11) NOT NULL DEFAULT '0',
  `request_exported_datetime` datetime DEFAULT NULL,
  `request_imported_datetime` datetime DEFAULT NULL,
  `result_exported_datetime` datetime DEFAULT NULL,
  `result_imported_datetime` datetime DEFAULT NULL,
  `import_machine_file_name` text,
  `manual_result_entry` varchar(255) DEFAULT NULL,
  `source` varchar(500) DEFAULT 'manual',
  `qc_tech_name` text,
  `qc_tech_sign` text,
  `qc_date` text,
  `repeat_sample_collection` text,
  `clinic_date` date DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `requesting_professional_number` text,
  `requesting_category` text,
  `requesting_facility_id` int(11) DEFAULT NULL,
  `requesting_person` text,
  `requesting_phone` text,
  `requesting_date` date DEFAULT NULL,
  `result_coming_from` varchar(255) DEFAULT NULL,
  `sample_processed` varchar(255) DEFAULT NULL,
  `vldash_sync` int(11) DEFAULT '0',
  `source_of_request` text,
  `source_data_dump` text,
  `result_sent_to_source` varchar(256) DEFAULT 'pending',
  `test_specific_attributes` json DEFAULT NULL,
  `form_attributes` json DEFAULT NULL,
  `locked` varchar(50) NOT NULL DEFAULT 'no',
  `data_sync` varchar(10) NOT NULL DEFAULT '0',
  `result_status` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Amit 1-May-2023 version 5.1.4
UPDATE `system_config` SET `value` = '5.1.4' WHERE `system_config`.`name` = 'sc_version';


-- Jeyabanu 2-May-2023

ALTER TABLE `form_generic` ADD `vlsm_country_id` INT NULL DEFAULT NULL AFTER `vlsm_instance_id`;

-- ilahir 04-May-2023
ALTER TABLE `form_generic` ADD `test_type_form` TEXT NULL DEFAULT NULL AFTER `test_type`;

-- Thana 08-May-2023
ALTER TABLE `form_generic` CHANGE `test_type_form` `test_type_form` JSON NULL DEFAULT NULL;
ALTER TABLE `form_generic` ADD PRIMARY KEY(`sample_id`);
ALTER TABLE `form_generic` CHANGE `sample_id` `sample_id` INT NOT NULL AUTO_INCREMENT;
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('generic-requests', 'generic-tests', 'Generic Tests Request Management'), ('generic-test-reference', 'generic-tests', 'Generic Tests Reference Tables');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES 
(NULL, 'generic-requests', 'view-requests.php', 'View Generic Tests'),  
(NULL, 'generic-requests', 'add-request.php', 'Add Generic Tests'), 
(NULL, 'generic-requests', 'add-samples-from-manifest.php', 'Add Samples From Manifest'), 
(NULL, 'generic-requests', 'batch-code.php', 'Add Batch Code'),
(NULL, 'generic-test-reference', 'test-type.php', 'Test Type Configuration'),
(NULL, 'generic-test-reference', 'addTestType.php', 'Add New Test Type'),
(NULL, 'generic-test-reference', 'editTestType.php', 'Edit New Test Type');
UPDATE `privileges` SET `privilege_name` = 'batch-code.php' WHERE `privileges`.`privilege_id` = 291;

-- Thana 09-May-2023
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'generic-requests', 'edit-request.php', 'Edit Generic Tests');

CREATE TABLE `r_generic_sample_types` (
  `sample_type_id` int NOT NULL AUTO_INCREMENT,
  `sample_type_code` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sample_type_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sample_type_status` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sample_type_id`),
  UNIQUE KEY `sample_type_code` (`sample_type_code`),
  UNIQUE KEY `sample_type_name` (`sample_type_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `r_generic_symptoms` (
  `symptom_id` int NOT NULL AUTO_INCREMENT,
  `symptom_name` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `symptom_code` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `symptom_status` varchar(256) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`symptom_id`),
  UNIQUE KEY `symptom_code` (`symptom_code`),
  UNIQUE KEY `symptom_name` (`symptom_name`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `r_generic_test_reasons` (
  `test_reason_id` int NOT NULL AUTO_INCREMENT,
  `test_reason_code` varchar(256) DEFAULT NULL,
  `test_reason` varchar(256) DEFAULT NULL,
  `test_reason_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_reason_id`),
  UNIQUE KEY `test_reason_code` (`test_reason_code`),
  UNIQUE KEY `test_reason` (`test_reason`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `r_generic_sample_rejection_reasons` (
  `rejection_reason_id` int NOT NULL AUTO_INCREMENT,
  `rejection_reason_name` varchar(255) CHARACTER SET latin1 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rejection_type` varchar(255) CHARACTER SET latin1 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) CHARACTER SET latin1 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rejection_reason_code` varchar(255) CHARACTER SET latin1 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;