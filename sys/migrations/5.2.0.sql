
-- ilahir 20-Apr-2023

ALTER TABLE `r_test_types` ADD `updated_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `test_status`;

INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('common-sample-type', 'admin', 'Common Sample Type Table');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-sample-type', 'addSampleType.php', 'Add'), (NULL, 'common-sample-type', 'sampleType.php', 'Access');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-sample-type', 'editSampleType.php', 'Edit');

CREATE TABLE `r_generic_sample_types` (
  `sample_type_id` int NOT NULL,
  `sample_type_code` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sample_type_name` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sample_type_status` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `r_generic_sample_types`
  ADD PRIMARY KEY (`sample_type_id`),
  ADD UNIQUE KEY `sample_type_code` (`sample_type_code`),
  ADD UNIQUE KEY `sample_type_name` (`sample_type_name`);


ALTER TABLE `r_generic_sample_types`
  MODIFY `sample_type_id` int NOT NULL AUTO_INCREMENT;

INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('common-testing-reason', 'admin', 'Common Testing Reason Table');

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-testing-reason', 'testingReason.php', 'Access'), (NULL, 'common-testing-reason', 'editTestingReason.php', 'Edit');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-testing-reason', 'addTestingReason.php', 'Add');


CREATE TABLE `r_generic_test_reasons` (
  `test_reason_id` int NOT NULL,
  `test_reason_code` varchar(256) DEFAULT NULL,
  `test_reason` varchar(256) DEFAULT NULL,
  `test_reason_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `r_generic_test_reasons`
  ADD PRIMARY KEY (`test_reason_id`),
  ADD UNIQUE KEY `test_reason_code` (`test_reason_code`),
  ADD UNIQUE KEY `test_reason` (`test_reason`);

ALTER TABLE `r_generic_test_reasons`
  MODIFY `test_reason_id` int NOT NULL AUTO_INCREMENT;


-- Amit 25-Apr-2023
ALTER TABLE `s_available_country_forms` ADD `short_name` VARCHAR(256) NOT NULL AFTER `form_name`;
UPDATE `s_available_country_forms` SET `short_name` = 'ssudan' WHERE `s_available_country_forms`.`vlsm_country_id` = 1; UPDATE `s_available_country_forms` SET `short_name` = 'sierra-leone' WHERE `s_available_country_forms`.`vlsm_country_id` = 2; UPDATE `s_available_country_forms` SET `short_name` = 'drc' WHERE `s_available_country_forms`.`vlsm_country_id` = 3; UPDATE `s_available_country_forms` SET `short_name` = 'zambia' WHERE `s_available_country_forms`.`vlsm_country_id` = 4; UPDATE `s_available_country_forms` SET `short_name` = 'png' WHERE `s_available_country_forms`.`vlsm_country_id` = 5; UPDATE `s_available_country_forms` SET `short_name` = 'who' WHERE `s_available_country_forms`.`vlsm_country_id` = 6; UPDATE `s_available_country_forms` SET `short_name` = 'rwanda' WHERE `s_available_country_forms`.`vlsm_country_id` = 7; UPDATE `s_available_country_forms` SET `short_name` = 'angola' WHERE `s_available_country_forms`.`vlsm_country_id` = 8;
-- ilahir 24-Apr-2023

INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('common-symptoms', 'admin', 'Common Symptoms Table');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-symptoms', 'symptoms.php', 'Access');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-symptoms', 'addSymptoms.php', 'Add');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'common-symptoms', 'editSymptoms.php', 'Edit');

CREATE TABLE `r_generic_symptoms` (
  `symptom_id` int NOT NULL,
  `symptom_name` varchar(256) DEFAULT NULL,
  `symptom_code` varchar(256) DEFAULT NULL,
  `symptom_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `r_generic_symptoms`
  ADD PRIMARY KEY (`symptom_id`),
  ADD UNIQUE KEY `symptom_code` (`symptom_code`),
  ADD UNIQUE KEY `symptom_name` (`symptom_name`);

ALTER TABLE `r_generic_symptoms`
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
  ADD CONSTRAINT `generic_test_sample_type_map_ibfk_1` FOREIGN KEY (`sample_type_id`) REFERENCES `r_generic_sample_types` (`sample_type_id`),
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
  ADD CONSTRAINT `generic_test_reason_map_ibfk_2` FOREIGN KEY (`test_reason_id`) REFERENCES `r_generic_test_reasons` (`test_reason_id`);


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
  ADD CONSTRAINT `generic_test_symptoms_map_ibfk_1` FOREIGN KEY (`symptom_id`) REFERENCES `r_generic_symptoms` (`symptom_id`),
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

-- RENAME TABLE `r_sample_types` TO `r_generic_sample_types`;
-- RENAME TABLE `r_symptoms` TO `r_generic_symptoms`;
-- RENAME TABLE `r_testing_reasons` TO `r_generic_test_reasons`;

CREATE TABLE `r_generic_sample_rejection_reasons` (
  `rejection_reason_id` int NOT NULL AUTO_INCREMENT,
  `rejection_reason_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rejection_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rejection_reason_code` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

UPDATE `privileges` SET `privilege_name` = 'add-test-type.php' WHERE `privileges`.`privilege_name` = 'addTestType.php';
UPDATE `privileges` SET `privilege_name` = 'edit-test-type.php' WHERE `privileges`.`privilege_name` = 'editTestType.php';
UPDATE `privileges` SET `display_name` = 'Edit Test Type' WHERE `privileges`.`privilege_name` = 'edit-test-type.php';
UPDATE `privileges` SET `display_name` = 'Add New Sample Type' WHERE `privileges`.`privilege_name` = 'generic-add-sample-type.php';

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES
(NULL, 'generic-test-reference', 'generic-sample-type.php', 'Manage Sample Type'),
(NULL, 'generic-test-reference', 'generic-add-sample-type.php', 'Add New Sample Type'),
(NULL, 'generic-test-reference', 'generic-edit-sample-type.php', 'Edit Sample Type'),
(NULL, 'generic-test-reference', 'generic-testing-reason.php', 'Manage Testing Reason'),
(NULL, 'generic-test-reference', 'generic-add-testing-reason.php', 'Add New Test Reason'),
(NULL, 'generic-test-reference', 'generic-edit-testing-reason.php', 'Edit Test Reason'),
(NULL, 'generic-test-reference', 'generic-symptoms.php', 'Manage Symptoms'),
(NULL, 'generic-test-reference', 'generic-add-symptoms.php', 'Add New Symptom'),
(NULL, 'generic-test-reference', 'generic-edit-symptoms.php', 'Edit Symptom'),
(NULL, 'generic-test-reference', 'generic-sample-rejection-reasons.php', 'Manage Sample Rejection Reasons'),
(NULL, 'generic-test-reference', 'generic-add-rejection-reasons.php', 'Add New Rejection Reasons'),
(NULL, 'generic-test-reference', 'generic-edit-rejection-reasons.php', 'Edit Rejection Reasons');
ALTER TABLE `health_facilities` CHANGE `test_type` `test_type` ENUM('vl','eid','covid19','hepatitis','tb','generic-tests') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE `testing_labs` CHANGE `test_type` `test_type` ENUM('vl','eid','covid19','hepatitis','tb','generic-tests') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

-- Thana 10-May-2023
CREATE TABLE `generic_test_results` (
  `test_id` int NOT NULL AUTO_INCREMENT,
  `generic_id` int NOT NULL,
  `facility_id` int DEFAULT NULL,
  `test_name` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `tested_by` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `sample_tested_datetime` datetime NOT NULL,
  `testing_platform` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kit_lot_no` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kit_expiry_date` date DEFAULT NULL,
  `result` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_id`),
  KEY `generic_id` (`generic_id`),
  CONSTRAINT `generic_test_results_ibfk_1` FOREIGN KEY (`generic_id`) REFERENCES `form_generic` (`sample_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Thana 11-May-2023
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('generic-results', 'generic-tests', 'Generic Tests Result Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES
(NULL, 'generic-results', 'generic-test-results.php', 'Manage Test Results'),
(NULL, 'generic-results', 'generic-failed-results.php', 'Manage Failed Results'),
(NULL, 'generic-results', 'generic-result-approval.php', 'Approve Test Results');

-- Amit 12-May-2023
CREATE TEMPORARY TABLE temp_table_form_vl AS (
    SELECT lab_id, app_sample_code
    FROM form_vl
    GROUP BY lab_id, app_sample_code
    HAVING COUNT(*) > 1
);

DELETE FROM form_vl WHERE (lab_id, app_sample_code) IN (
    SELECT lab_id, app_sample_code
    FROM temp_table_form_vl
);

CREATE TEMPORARY TABLE temp_table_form_eid AS (
    SELECT lab_id, app_sample_code
    FROM form_eid
    GROUP BY lab_id, app_sample_code
    HAVING COUNT(*) > 1
);

DELETE FROM form_eid WHERE (lab_id, app_sample_code) IN (
    SELECT lab_id, app_sample_code
    FROM temp_table_form_eid
);

CREATE TEMPORARY TABLE temp_table_form_covid19 AS (
    SELECT lab_id, app_sample_code
    FROM form_covid19
    GROUP BY lab_id, app_sample_code
    HAVING COUNT(*) > 1
);

DELETE FROM form_covid19 WHERE (lab_id, app_sample_code) IN (
    SELECT lab_id, app_sample_code
    FROM temp_table_form_covid19
);
ALTER TABLE `form_vl` CHANGE `app_sample_code` `app_sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_eid` CHANGE `app_sample_code` `app_sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_covid19` CHANGE `app_sample_code` `app_sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `app_sample_code` `app_sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_generic` CHANGE `app_sample_code` `app_sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_hepatitis` CHANGE `app_sample_code` `app_sample_code` VARCHAR(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;



ALTER TABLE `form_vl` ADD UNIQUE( `lab_id`, `app_sample_code`);
ALTER TABLE `form_eid` ADD UNIQUE( `lab_id`, `app_sample_code`);
ALTER TABLE `form_covid19` ADD UNIQUE( `lab_id`, `app_sample_code`);
ALTER TABLE `form_tb` ADD UNIQUE( `lab_id`, `app_sample_code`);
ALTER TABLE `form_hepatitis` ADD UNIQUE( `lab_id`, `app_sample_code`);
ALTER TABLE `form_generic` ADD UNIQUE( `lab_id`, `app_sample_code`);

-- Thana 12-May-2023
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('generic-management', 'generic-tests', 'Lab Test Management');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES
(NULL, 'generic-management', 'generic-sample-status.php', 'Sample Status Report'),
(NULL, 'generic-management', 'generic-export-data.php', 'Export Report in Excel'),
(NULL, 'generic-management', 'generic-print-result.php', 'Export Report in PDF'),
(NULL, 'generic-management', 'sample-rejection-report.php', 'Sample Rejection Report'),
(NULL, 'generic-management', 'generic-monthly-threshold-report.php', 'Monthly Threshold Report');
UPDATE `resources` SET `display_name` = 'Lab Tests Request Management' WHERE `resources`.`resource_id` = 'generic-requests';
UPDATE `resources` SET `display_name` = 'Lab Tests Result Management' WHERE `resources`.`resource_id` = 'generic-results';
UPDATE `resources` SET `display_name` = 'Lab Tests Reference Management' WHERE `resources`.`resource_id` = 'generic-test-reference';
UPDATE `resources` SET `display_name` = 'Lab Tests Report Management' WHERE `resources`.`resource_id` = 'generic-management';


-- Amit 12-May-2023 version 5.1.5
UPDATE `system_config` SET `value` = '5.1.5' WHERE `system_config`.`name` = 'sc_version';

-- Amit 13-May-2023
ALTER TABLE `form_covid19` ADD `vaccination_status` TEXT NULL DEFAULT NULL AFTER `patient_passport_number`;
ALTER TABLE `form_covid19` ADD `vaccination_dosage` TEXT NULL DEFAULT NULL AFTER `vaccination_status`;
ALTER TABLE `form_covid19` ADD `vaccination_type` TEXT NULL DEFAULT NULL AFTER `vaccination_dosage`;
ALTER TABLE `form_covid19` ADD `vaccination_type_other` TEXT NULL DEFAULT NULL AFTER `vaccination_type`;
ALTER TABLE `form_covid19` ADD `specimen_taken_before_antibiotics` TEXT NULL AFTER `patient_city`;

ALTER TABLE `audit_form_covid19` ADD `vaccination_status` TEXT NULL DEFAULT NULL AFTER `patient_passport_number`;
ALTER TABLE `audit_form_covid19` ADD `vaccination_dosage` TEXT NULL DEFAULT NULL AFTER `vaccination_status`;
ALTER TABLE `audit_form_covid19` ADD `vaccination_type` TEXT NULL DEFAULT NULL AFTER `vaccination_dosage`;
ALTER TABLE `audit_form_covid19` ADD `vaccination_type_other` TEXT NULL DEFAULT NULL AFTER `vaccination_type`;
ALTER TABLE `audit_form_covid19` ADD `specimen_taken_before_antibiotics` TEXT NULL AFTER `patient_city`;

-- Amit 16-May-2023
ALTER TABLE `user_login_history` CHANGE `login_status` `login_status` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `user_login_history` CHANGE `ip_address` `ip_address` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

-- Thana 16-May-2023
ALTER TABLE user_login_history ADD INDEX login_status_attempted_datetime_idx (login_status, login_attempted_datetime);
-- Thana 15-May-2023
DELETE p, rpm FROM `privileges` AS p INNER JOIN `roles_privileges_map` AS rpm ON rpm.privilege_id = p.privilege_id WHERE privilege_name IN ('generic-weekly-report.php', 'generic-monitoring-report.php');

-- ilahir 16-May-2023
ALTER TABLE `form_eid` CHANGE `sample_code_key` `sample_code_key` INT NULL DEFAULT NULL;

-- Jeyabanu 16-May-2023

CREATE TABLE `r_generic_test_failure_reasons` (
  `test_failure_reason_id` int NOT NULL AUTO_INCREMENT,
  `test_failure_reason_code` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `test_failure_reason` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `test_failure_reason_status` varchar(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int DEFAULT NULL,
  PRIMARY KEY (`test_failure_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `generic_test_failure_reason_map` (
  `map_id` int NOT NULL AUTO_INCREMENT,
  `test_failure_reason_id` int NOT NULL,
  `test_type_id` int NOT NULL,
  PRIMARY KEY (`map_id`),
  KEY `test_type_id` (`test_type_id`),
  KEY `test_reason_id` (`test_failure_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `instruments` ADD `lab_id` INT NULL DEFAULT NULL AFTER `machine_name`;

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'generic-test-reference', 'generic-test-failure-reason.php', 'Manage Test Failure Reason'), (NULL, 'generic-test-reference', 'generic-add-test-failure-reason.php', 'Add New Test Failure Reason');

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'generic-test-reference', 'generic-edit-test-failure-reason.php', 'Edit Test Failure Reason');

CREATE TABLE `generic_sample_rejection_reason_map` (
  `map_id` int NOT NULL,
  `rejection_reason_id` int NOT NULL,
  `test_type_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Amit 25-May-2023
INSERT INTO `privileges` ( `resource_id`, `privilege_name`, `display_name`) VALUES ( 'vl-reference', 'add-vl-results.php', 'Add VL Result Types');
INSERT INTO `privileges` ( `resource_id`, `privilege_name`, `display_name`) VALUES ( 'vl-reference', 'edit-vl-results.php', 'Edit VL Result Types');

-- Jeyabanu 30-May-2023
CREATE TABLE `r_generic_test_result_units` (
  `unit_id` int NOT NULL AUTO_INCREMENT,
  `unit_name` varchar(256) DEFAULT NULL,
  `unit_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `generic_test_result_units_map` (
  `map_id` int NOT NULL AUTO_INCREMENT,
  `unit_id` int NOT NULL,
  `test_type_id` int NOT NULL,
  PRIMARY KEY (`map_id`),
  KEY `test_type_id` (`test_type_id`),
  KEY `unit_id` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'generic-test-reference', 'generic-test-result-units.php', 'Manage Test Result Units');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'generic-test-reference', 'generic-add-test-result-units.php', 'Add Test Result Units');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'generic-test-reference', 'generic-edit-test-result-units.php', 'Edit Test Result Units');


-- Jeyabanu 31-May-2023

CREATE TABLE `r_generic_test_methods` (
  `test_method_id` int NOT NULL AUTO_INCREMENT,
  `test_method_name` varchar(256) DEFAULT NULL,
  `test_method_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
  PRIMARY KEY (`test_method_id`),
  UNIQUE KEY `test_method_name` (`test_method_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `generic_test_methods_map` (
  `map_id` int NOT NULL AUTO_INCREMENT,
  `test_method_id` int NOT NULL,
  `test_type_id` int NOT NULL,
  PRIMARY KEY (`map_id`),
  KEY `test_type_id` (`test_type_id`),
  KEY `test_method_id` (`test_method_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES
(NULL, 'generic-test-reference', 'generic-test-methods.php', 'Manage Test Methods'),
(NULL, 'generic-test-reference', 'generic-add-test-methods.php', 'Add Test Method'),
(NULL, 'generic-test-reference', 'generic-edit-test-methods.php', 'Edit Test Method');


CREATE TABLE `r_generic_test_categories` (
  `test_category_id` int NOT NULL AUTO_INCREMENT,
  `test_category_name` varchar(256) DEFAULT NULL,
  `test_category_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
  PRIMARY KEY (`test_category_id`),
  UNIQUE KEY `test_category_name` (`test_category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES
(NULL, 'generic-test-reference', 'generic-test-categories.php', 'Manage Test Categories'),
(NULL, 'generic-test-reference', 'generic-add-test-categories.php', 'Add Test Category'),
(NULL, 'generic-test-reference', 'generic-edit-test-categories.php', 'Edit Test Category');

-- Thana 31-May-2023
ALTER TABLE `r_test_types` ADD `test_category` VARCHAR(256) NULL DEFAULT NULL AFTER `test_loinc_code`;


-- Amit 1-Jun-2023 version 5.1.6
UPDATE `system_config` SET `value` = '5.1.6' WHERE `system_config`.`name` = 'sc_version';


-- Jeyabanu 02-06-2023

ALTER TABLE `generic_test_results` ADD `result_unit` INT NULL DEFAULT NULL AFTER `result`;
ALTER TABLE `form_generic` ADD `result_unit` INT NULL DEFAULT NULL AFTER `result`;


-- Amit 8-Jun-2023 version 5.1.7
UPDATE `system_config` SET `value` = '5.1.7' WHERE `system_config`.`name` = 'sc_version';

-- Jeyabanu 08-06-2023
ALTER TABLE `form_eid` ADD `second_dbs_requested` VARCHAR(256) NULL DEFAULT NULL AFTER `result_approved_by`, ADD `second_dbs_requested_reason` VARCHAR(256) NULL DEFAULT NULL AFTER `second_dbs_requested`;
ALTER TABLE `audit_form_eid` ADD `second_dbs_requested` VARCHAR(256) NULL DEFAULT NULL AFTER `result_approved_by`, ADD `second_dbs_requested_reason` VARCHAR(256) NULL DEFAULT NULL AFTER `second_dbs_requested`;

-- Thana 09-Jun-2023

UPDATE `privileges` SET `privilege_name` = '/batch/batches.php?type=generic-tests' WHERE `privilege_name` = 'batch-code.php';
UPDATE `privileges` SET `display_name` = 'Manage Batch' WHERE `resource_id` = '/batch/batches.php?type=generic-tests';

UPDATE `privileges` SET `privilege_name` = '/batch/batches.php?type=vl' WHERE `privilege_name` = 'batchcode.php';
UPDATE `privileges` SET `privilege_name` = '/batch/add-batch.php?type=vl' WHERE `privilege_name` = 'addBatch.php';
UPDATE `privileges` SET `privilege_name` = '/batch/edit-batch.php?type=vl' WHERE `privilege_name` = 'editBatch.php';
UPDATE `privileges` SET `privilege_name` = '/batch/add-batch-position.php?type=vl' WHERE `privilege_name` = 'addBatchControlsPosition.php';
UPDATE `privileges` SET `privilege_name` = '/batch/edit-batch-position.php?type=vl' WHERE `privilege_name` = 'editBatchControlsPosition.php';

UPDATE `privileges` SET `privilege_name` = '/batch/batches.php?type=eid' WHERE `privilege_name` = 'eid-batches.php';
UPDATE `privileges` SET `privilege_name` = '/batch/add-batch.php?type=eid' WHERE `privilege_name` = 'eid-add-batch.php';
UPDATE `privileges` SET `privilege_name` = '/batch/edit-batch.php?type=eid' WHERE `privilege_name` = 'eid-edit-batch.php';

UPDATE `privileges` SET `privilege_name` = '/batch/batches.php?type=covid19' WHERE `privilege_name` = 'covid-19-batches.php';
UPDATE `privileges` SET `privilege_name` = '/batch/add-batch.php?type=covid19' WHERE `privilege_name` = 'covid-19-add-batch.php';
UPDATE `privileges` SET `privilege_name` = '/batch/edit-batch.php?type=covid19' WHERE `privilege_name` = 'covid-19-edit-batch.php';

UPDATE `privileges` SET `privilege_name` = '/batch/batches.php?type=hepatitis' WHERE `privilege_name` = 'hepatitis-batches.php';
UPDATE `privileges` SET `privilege_name` = '/batch/add-batch.php?type=hepatitis' WHERE `privilege_name` = 'hepatitis-add-batch.php';
UPDATE `privileges` SET `privilege_name` = '/batch/edit-batch.php?type=hepatitis' WHERE `privilege_name` = 'hepatitis-edit-batch.php';

UPDATE `privileges` SET `privilege_name` = '/batch/batches.php?type=tb' WHERE `privilege_name` = 'tb-batches.php';
UPDATE `privileges` SET `privilege_name` = '/batch/add-batch.php?type=tb' WHERE `privilege_name` = 'tb-add-batch.php';
UPDATE `privileges` SET `privilege_name` = '/batch/edit-batch.php?type=tb' WHERE `privilege_name` = 'tb-edit-batch.php';

-- Jeyabanu 09-06-2023
ALTER TABLE `form_eid` ADD `previous_sample_code` VARCHAR(256) NULL DEFAULT NULL AFTER `caretaker_address`, ADD `clinical_assessment` VARCHAR(256) NULL DEFAULT NULL AFTER `previous_sample_code`, ADD `clinician_name` VARCHAR(256) NULL DEFAULT NULL AFTER `clinical_assessment`;
ALTER TABLE `audit_form_eid` ADD `previous_sample_code` VARCHAR(256) NULL DEFAULT NULL AFTER `caretaker_address`, ADD `clinical_assessment` VARCHAR(256) NULL DEFAULT NULL AFTER `previous_sample_code`, ADD `clinician_name` VARCHAR(256) NULL DEFAULT NULL AFTER `clinical_assessment`;

ALTER TABLE `form_eid` ADD `mode_of_delivery_other` VARCHAR(256) NULL DEFAULT NULL AFTER `mode_of_delivery`;
ALTER TABLE `audit_form_eid` ADD `mode_of_delivery_other` VARCHAR(256) NULL DEFAULT NULL AFTER `mode_of_delivery`;

-- Thana 12-Jun-2023
ALTER TABLE `batch_details` ADD `last_modified_by` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `request_created_datetime`, ADD `last_modified_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `last_modified_by`;

UPDATE `batch_details` SET `last_modified_by` = `created_by` WHERE `last_modified_by` IS NULL;
UPDATE `batch_details` SET `last_modified_datetime` = `request_created_datetime` WHERE `last_modified_datetime` IS NULL;

-- Thana 13-Jun-2023
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('generic-tests-batches', 'generic-tests', 'Lab Tests Batch Management');
UPDATE `privileges` SET `resource_id` = 'generic-tests-batches' WHERE `privileges`.`resource_id` = 'generic-requests' AND `privileges`.`privilege_name` = '/batch/batches.php?type=generic-tests';

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES
(NULL, 'generic-tests-batches', '/batch/add-batch.php?type=generic-tests', 'Add New Batch'),
(NULL, 'generic-tests-batches', '/batch/edit-batch.php?type=generic-tests', 'Edit Batch');

ALTER TABLE `generic_sample_rejection_reason_map` ADD PRIMARY KEY(`map_id`);
ALTER TABLE `generic_sample_rejection_reason_map` CHANGE `map_id` `map_id` INT NOT NULL AUTO_INCREMENT;
ALTER TABLE `generic_test_methods_map` ADD `updated_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `test_type_id`;
ALTER TABLE `generic_test_sample_type_map` ADD `updated_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `test_type_id`;
ALTER TABLE `generic_test_reason_map` ADD `updated_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `test_type_id`;
ALTER TABLE `generic_test_failure_reason_map` ADD `updated_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `test_type_id`;
ALTER TABLE `generic_sample_rejection_reason_map` ADD `updated_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `test_type_id`;
ALTER TABLE `generic_test_symptoms_map` ADD `updated_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `test_type_id`;
ALTER TABLE `generic_test_result_units_map` ADD `updated_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `test_type_id`;

-- Thana 14-Jun-2023
-- ALTER TABLE `form_generic` DROP `community_sample`;


-- Amit 14-Jun-2023
ALTER TABLE `user_details` ADD `user_locale` VARCHAR(256) NULL DEFAULT NULL AFTER `role_id`;


-- Jeyabanu 15-06-2023
ALTER TABLE `form_eid` ADD `mother_art_status` VARCHAR(32) NULL DEFAULT NULL AFTER `mode_of_delivery_other`;
ALTER TABLE `audit_form_eid` ADD `mother_art_status` VARCHAR(32) NULL DEFAULT NULL AFTER `mode_of_delivery_other`;

ALTER TABLE `form_eid` ADD `infant_art_status` VARCHAR(32) NULL DEFAULT NULL AFTER `age_breastfeeding_stopped_in_months`, ADD `infant_art_status_other` VARCHAR(32) NULL DEFAULT NULL AFTER `infant_art_status`;
ALTER TABLE `audit_form_eid` ADD `infant_art_status` VARCHAR(32) NULL DEFAULT NULL AFTER `age_breastfeeding_stopped_in_months`, ADD `infant_art_status_other` VARCHAR(32) NULL DEFAULT NULL AFTER `infant_art_status`;

ALTER TABLE `form_eid` ADD `started_art_date` DATE NULL DEFAULT NULL AFTER `mother_regimen`, ADD `mother_mtct_risk` VARCHAR(32) NULL DEFAULT NULL AFTER `started_art_date`;
ALTER TABLE `audit_form_eid` ADD `started_art_date` DATE NULL DEFAULT NULL AFTER `mother_regimen`, ADD `mother_mtct_risk` VARCHAR(32) NULL DEFAULT NULL AFTER `started_art_date`;

ALTER TABLE `form_eid` ADD `test_1_date` DATE NULL DEFAULT NULL AFTER `is_sample_rejected`, ADD `test_1_batch` INT NULL DEFAULT NULL AFTER `test_1_date`, ADD `test_1_assay` TEXT NULL DEFAULT NULL AFTER `test_1_batch`, ADD `test_1_ct_qs` INT NULL DEFAULT NULL AFTER `test_1_assay`, ADD `test_1_result` TEXT NULL DEFAULT NULL AFTER `test_1_ct_qs`, ADD `test_1_repeated` TEXT NULL DEFAULT NULL AFTER `test_1_result`, ADD `test_1_repeat_reason` TEXT NULL DEFAULT NULL AFTER `test_1_repeated`;
ALTER TABLE `audit_form_eid` ADD `test_1_date` DATE NULL DEFAULT NULL AFTER `is_sample_rejected`, ADD `test_1_batch` INT NULL DEFAULT NULL AFTER `test_1_date`, ADD `test_1_assay` TEXT NULL DEFAULT NULL AFTER `test_1_batch`, ADD `test_1_ct_qs` INT NULL DEFAULT NULL AFTER `test_1_assay`, ADD `test_1_result` TEXT NULL DEFAULT NULL AFTER `test_1_ct_qs`, ADD `test_1_repeated` TEXT NULL DEFAULT NULL AFTER `test_1_result`, ADD `test_1_repeat_reason` TEXT NULL DEFAULT NULL AFTER `test_1_repeated`;

ALTER TABLE `form_eid` ADD `test_2_date` DATE NULL DEFAULT NULL AFTER `test_1_repeat_reason`, ADD `test_2_batch` INT NULL DEFAULT NULL AFTER `test_2_date`, ADD `test_2_assay` TEXT NULL DEFAULT NULL AFTER `test_2_batch`, ADD `test_2_ct_qs` INT NULL DEFAULT NULL AFTER `test_2_assay`, ADD `test_2_result` TEXT NULL DEFAULT NULL AFTER `test_2_ct_qs`;
ALTER TABLE `audit_form_eid` ADD `test_2_date` DATE NULL DEFAULT NULL AFTER `test_1_repeat_reason`, ADD `test_2_batch` INT NULL DEFAULT NULL AFTER `test_2_date`, ADD `test_2_assay` TEXT NULL DEFAULT NULL AFTER `test_2_batch`, ADD `test_2_ct_qs` INT NULL DEFAULT NULL AFTER `test_2_assay`, ADD `test_2_result` TEXT NULL DEFAULT NULL AFTER `test_2_ct_qs`;


-- Amit 19-Jun-2023
ALTER TABLE `form_eid` CHANGE `previous_sample_code` `previous_sample_code` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `clinician_name` `clinician_name` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_age_in_years` `mother_age_in_years` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_marital_status` `mother_marital_status` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_id` `child_id` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_name` `child_name` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_surname` `child_surname` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_age` `child_age` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_gender` `child_gender` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_hiv_status` `mother_hiv_status` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `sample_requestor_phone` `sample_requestor_phone` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `pcr_test_performed_before` `pcr_test_performed_before` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_pcr_id` `last_pcr_id` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `previous_pcr_result` `previous_pcr_result` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `rapid_test_result` `rapid_test_result` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `eid_test_platform` `eid_test_platform` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `locked` `locked` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';
ALTER TABLE `audit_form_eid` CHANGE `previous_sample_code` `previous_sample_code` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `clinician_name` `clinician_name` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_age_in_years` `mother_age_in_years` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_marital_status` `mother_marital_status` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_id` `child_id` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_name` `child_name` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_surname` `child_surname` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_age` `child_age` VARCHAR(3) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `child_gender` `child_gender` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `mother_hiv_status` `mother_hiv_status` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `sample_requestor_phone` `sample_requestor_phone` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `pcr_test_performed_before` `pcr_test_performed_before` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `last_pcr_id` `last_pcr_id` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `previous_pcr_result` `previous_pcr_result` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `rapid_test_result` `rapid_test_result` VARCHAR(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `eid_test_platform` `eid_test_platform` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `locked` `locked` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

DELETE FROM s_available_country_forms WHERE `vlsm_country_id` = 8;
UPDATE `s_available_country_forms` SET `form_name` = 'Republic of Cameroon', `short_name` = 'cameroon' WHERE `s_available_country_forms`.`vlsm_country_id` = 4;

ALTER TABLE `form_vl` CHANGE `sample_package_id` `sample_package_id` INT NULL DEFAULT NULL;
ALTER TABLE `form_eid` CHANGE `sample_package_id` `sample_package_id` INT NULL DEFAULT NULL;
ALTER TABLE `form_covid19` CHANGE `sample_package_id` `sample_package_id` INT NULL DEFAULT NULL;
ALTER TABLE `form_hepatitis` CHANGE `sample_package_id` `sample_package_id` INT NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `sample_package_id` `sample_package_id` INT NULL DEFAULT NULL;
ALTER TABLE `form_generic` CHANGE `sample_package_id` `sample_package_id` INT NULL DEFAULT NULL;

-- Jeyabanu 19-06-2023
DROP TABLE IF EXISTS `s_app_menu`;
CREATE TABLE `s_app_menu` (
  `id` int(11) NOT NULL,
  `module` varchar(256) NOT NULL,
  `is_header` varchar(256) DEFAULT NULL,
  `display_text` varchar(256) NOT NULL,
  `link` varchar(256) DEFAULT NULL,
  `inner_pages` varchar(256) DEFAULT NULL,
  `show_mode` varchar(32) NOT NULL DEFAULT 'always',
  `icon` varchar(256) DEFAULT NULL,
  `has_children` varchar(256) DEFAULT NULL,
  `additional_class_names` varchar(256) DEFAULT NULL,
  `parent_id` int(11) DEFAULT '0',
  `display_order` int(11) NOT NULL,
  `status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


INSERT INTO `s_app_menu` (`id`, `module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES
(1, 'dashboard', 'no', 'DASHBOARD', '/dashboard/index.php', NULL, 'always', 'fa-solid fa-chart-pie', 'no', 'allMenu dashboardMenu', 0, 1, 'active', NULL),
(2, 'admin', 'no', 'ADMIN', NULL, NULL, 'always', 'fa-solid fa-shield', 'yes', NULL, 0, 2, 'active', NULL),
(3, 'admin', 'no', 'Access Control', '', NULL, 'always', 'fa-solid fa-user', 'yes', 'treeview access-control-menu', 2, 3, 'active', NULL),
(4, 'admin', 'no', 'Roles', '/roles/roles.php', '/roles/addRole.php,/roles/editRole.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu roleMenu', 3, 4, 'active', NULL),
(5, 'admin', 'no', 'Users', '/users/users.php', '/users/addUser.php,/users/editUser.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu userMenu', 3, 5, 'active', NULL),
(6, 'admin', 'no', 'Facilities', '/facilities/facilities.php', '/facilities/addFacility.php,/facilities/editFacility.php,/facilities/mapTestType.php', 'always', 'fa-solid fa-hospital', 'no', 'treeview facility-config-menu', 2, 6, 'active', NULL),
(7, 'admin', 'no', 'Monitoring', NULL, NULL, 'always', 'fa-solid fa-bullseye', 'yes', 'treeview monitoring-menu', 2, 7, 'active', NULL),
(8, 'admin', 'no', 'System Configuration', NULL, NULL, 'always', 'fa-solid fa-gears', 'yes', 'treeview system-config-menu', 2, 8, 'active', NULL),
(9, 'admin', 'no', 'Other Lab Tests Config', NULL, NULL, 'always', 'fa-solid fa-vial-circle-check', 'yes', 'treeview generic-reference-manage', 2, 9, 'active', NULL),
(10, 'admin', 'no', 'VL Config', NULL, NULL, 'always', 'fa-solid fa-flask-vial', 'yes', 'treeview vl-reference-manage', 2, 10, 'active', NULL),
(11, 'admin', 'no', 'EID Config', NULL, NULL, 'always', 'fa-solid fa-vial-circle-check', 'yes', 'treeview generic-reference-manage', 2, 11, 'active', NULL),
(12, 'admin', 'no', 'Covid-19 Config', NULL, NULL, 'always', 'fa-solid fa-virus-covid', 'yes', 'treeview covid19-reference-manage', 2, 12, 'active', NULL),
(13, 'admin', 'no', 'Hepatitis Config', NULL, NULL, 'always', 'fa-solid fa-square-h', 'yes', 'treeview hepatitis-reference-manage', 2, 13, 'active', NULL),
(14, 'admin', 'no', 'TB Config', NULL, NULL, 'always', 'fa-solid fa-heart-pulse', 'yes', 'treeview tb-reference-manage', 2, 14, 'active', NULL),
(15, 'admin', 'no', 'User Activity Log', '/admin/monitoring/activity-log.php', NULL, 'always', 'fa-solid fa-file-lines', 'no', 'allMenu treeview activity-log-menu', 7, 15, 'active', NULL),
(16, 'admin', 'no', 'Audit Trail', '/admin/monitoring/audit-trail.php', NULL, 'always', 'fa-solid fa-clock-rotate-left', 'no', 'allMenu treeview audit-trail-menu', 7, 16, 'active', NULL),
(17, 'admin', 'no', 'API History', '/admin/monitoring/api-sync-history.php', NULL, 'always', 'fa-solid fa-circle-nodes', 'no', 'allMenu treeview api-sync-history-menu', 7, 17, 'active', NULL),
(18, 'admin', 'no', 'Source of Requests', '/admin/monitoring/sources-of-requests.php', NULL, 'always', 'fa-solid fa-circle-notch', 'no', 'allMenu treeview sources-of-requests-report-menu', 7, 18, 'active', NULL),
(19, 'admin', 'no', 'General Configuration', '/global-config/editGlobalConfig.php', '/global-config/editGlobalConfig.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu globalConfigMenu', 8, 19, 'active', NULL),
(20, 'admin', 'no', 'Instruments', '/instruments/instruments.php', '/instruments/add-instrument.php,/instruments/edit-instrument.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu importConfigMenu', 8, 20, 'active', NULL),
(21, 'admin', 'no', 'Geographical Divisions', '/common/reference/geographical-divisions-details.php', '/common/reference/add-geographical-divisions.php,/common/reference/edit-geographical-divisions.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu geographicalMenu', 8, 21, 'active', NULL),
(22, 'admin', 'no', 'Implementation Partners', '/common/reference/implementation-partners.php', '/common/reference/add-implementation-partners.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu common-reference-implementation-partners', 8, 22, 'active', NULL),
(23, 'admin', 'no', 'Funding Sources', '/common/reference/funding-sources.php', '/common/reference/add-funding-sources.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu common-reference-funding-sources', 8, 23, 'active', NULL),
(24, 'admin', 'no', 'Sample Types', '/generic-tests/configuration/sample-types/generic-sample-type.php', '/generic-tests/configuration/sample-types/generic-add-sample-type.php,/generic-tests/configuration/sample-types/generic-edit-sample-type.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericSampleTypeMenu', 9, 24, 'active', NULL),
(25, 'admin', 'no', 'Testing Reasons', '/generic-tests/configuration/testing-reasons/generic-testing-reason.php', '/generic-tests/configuration/testing-reasons/generic-add-testing-reason.php,/generic-tests/configuration/testing-reasons/generic-edit-testing-reason.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericTestingReasonMenu', 9, 25, 'active', NULL),
(26, 'admin', 'no', 'Test Failure Reasons', '/generic-tests/configuration/test-failure-reasons/generic-test-failure-reason.php', '/generic-tests/configuration/test-failure-reasons/generic-add-test-failure-reason.php,/generic-tests/configuration/test-failure-reasons/generic-edit-test-failure-reason.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericTestFailureReasonMenu', 9, 26, 'active', NULL),
(27, 'admin', 'no', 'Symptoms', '/generic-tests/configuration/symptoms/generic-symptoms.php', '/generic-tests/configuration/symptoms/generic-add-symptoms.php,/generic-tests/configuration/symptoms/generic-edit-symptoms.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericSymptomsMenu', 9, 27, 'active', NULL),
(28, 'admin', 'no', 'Sample Rejection Reasons', '/generic-tests/configuration/sample-rejection-reasons/generic-sample-rejection-reasons.php', '/generic-tests/configuration/sample-types/generic-add-sample-type.php,/generic-tests/configuration/sample-rejection-reasons/generic-edit-rejection-reasons.php,/generic-tests/configuration/sample-rejection-reasons/generic-add-rejection-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericSampleRejectionReasonsMenu', 9, 28, 'active', NULL),
(29, 'admin', 'no', 'Test Result Units', '/generic-tests/configuration/test-result-units/generic-test-result-units.php', '/generic-tests/configuration/test-result-units/generic-add-test-result-units.php,/generic-tests/configuration/test-result-units/generic-edit-test-result-units.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericTestResultUnitsMenu', 9, 29, 'active', NULL),
(30, 'admin', 'no', 'Test Methods', '/generic-tests/configuration/test-methods/generic-test-methods.php', '/generic-tests/configuration/test-methods/generic-add-test-methods.php,/generic-tests/configuration/test-methods/generic-edit-test-methods.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericTestMethodsMenu', 9, 30, 'active', NULL),
(31, 'admin', 'no', 'Test Categories', '/generic-tests/configuration/test-categories/generic-test-categories.php', '/generic-tests/configuration/test-categories/generic-add-test-categories.php,/generic-tests/configuration/test-categories/generic-edit-test-categories.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericTestCategoriesMenu', 9, 31, 'active', NULL),
(32, 'admin', 'no', 'Test Type Configuration', '/generic-tests/configuration/test-type.php', '/generic-tests/configuration/add-test-type.php,/generic-tests/configuration/edit-test-type.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu testTypeConfigurationMenu', 9, 31, 'active', NULL),
(33, 'admin', 'no', 'ART Regimen', '/vl/reference/vl-art-code-details.php', '/vl/reference/add-vl-art-code-details.php,/vl/reference/edit-vl-art-code-details.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vl-art-code-details', 10, 26, 'active', NULL),
(34, 'admin', 'no', 'Rejection Reasons', '/vl/reference/vl-sample-rejection-reasons.php', '/vl/reference/add-vl-sample-rejection-reasons.php,/vl/reference/edit-vl-sample-rejection-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vl-sample-rejection-reasons', 10, 27, 'active', NULL),
(35, 'admin', 'no', 'Sample Type', '/vl/reference/vl-sample-type.php', '/vl/reference/add-vl-sample-type.php,/vl/reference/edit-vl-sample-type.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vl-sample-type', 10, 28, 'active', NULL),
(36, 'admin', 'no', 'Results', '/vl/reference/vl-results.php', '/vl/reference/add-vl-results.php,/vl/reference/edit-vl-results.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vl-results', 10, 29, 'active', NULL),
(37, 'admin', 'no', 'Test Reasons', '/vl/reference/vl-test-reasons.php', '/vl/reference/add-vl-test-reasons.php,/vl/reference/edit-vl-test-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vl-test-reasons', 10, 30, 'active', NULL),
(38, 'admin', 'no', 'Test Failure Reasons', '/vl/reference/vl-test-failure-reasons.php', '/vl/reference/add-vl-test-failure-reason.php,/vl/reference/edit-vl-test-failure-reason.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vl-test-failure-reasons', 10, 38, 'active', NULL),
(39, 'admin', 'no', 'Rejection Reasons', '/eid/reference/eid-sample-rejection-reasons.php', '/eid/reference/add-eid-sample-rejection-reasons.php,/eid/reference/edit-eid-sample-rejection-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eid-sample-rejection-reasons', 11, 38, 'active', NULL),
(40, 'admin', 'no', 'Sample Type', '/eid/reference/eid-sample-type.php', '/eid/reference/add-eid-sample-type.php,/eid/reference/edit-eid-sample-type.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eid-sample-type', 11, 39, 'active', NULL),
(41, 'admin', 'no', 'Test Reasons', '/eid/reference/eid-test-reasons.php', '/eid/reference/add-eid-test-reasons.php,/eid/reference/edit-eid-test-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eid-test-reasons', 11, 40, 'active', NULL),
(42, 'admin', 'no', 'Results', '/eid/reference/eid-results.php', '/eid/reference/add-eid-results.php,/eid/reference/edit-eid-results.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eid-results', 11, 41, 'active', NULL),
(43, 'admin', 'no', 'Co-morbidities', '/covid-19/reference/covid19-comorbidities.php', '/covid-19/reference/add-covid19-comorbidities.php,/covid-19/reference/edit-covid19-comorbidities.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19-comorbidities', 12, 42, 'active', NULL),
(44, 'admin', 'no', 'Rejection Reasons', '/covid-19/reference/eid-sample-rejection-reasons.php', '/covid-19/reference/add-covid-19-sample-rejection-reasons.php,/covid-19/reference/edit-covid-19-sample-rejection-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19-sample-rejection-reasons', 12, 43, 'active', NULL),
(45, 'admin', 'no', 'Sample Type', '/covid-19/reference/eid-sample-type.php', '/covid-19/reference/add-covid-19-sample-type.php,/covid-19/reference/edit-covid-19-sample-type.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19-sample-type', 12, 44, 'active', NULL),
(46, 'admin', 'no', 'Symptoms', '/covid-19/reference/covid19-symptoms.php', '/covid-19/reference/add-covid19-symptoms.php,/covid-19/reference/edit-covid19-symptoms.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19-symptoms', 12, 45, 'active', NULL),
(47, 'admin', 'no', 'Test Reasons', '/covid-19/reference/covid-19-test-reasons.php', '/covid-19/reference/add-covid-19-test-reasons.php,/covid-19/reference/edit-covid-19-test-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid-19-test-reasons', 12, 46, 'active', NULL),
(48, 'admin', 'no', 'Results', '/covid-19/reference/covid-19-results.php', '/covid-19/reference/add-covid-19-results.php,/covid-19/reference/edit-covid-19-results.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19-results', 12, 47, 'active', NULL),
(49, 'admin', 'no', 'QC Test Kits', '/covid-19/reference/covid19-qc-test-kits.php', '/covid-19/reference/add-covid19-qc-test-kit.php,/covid-19/reference/edit-covid19-qc-test-kit.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19-qc-test-kits', 12, 48, 'active', NULL),
(50, 'admin', 'no', 'Co-morbidities', '/hepatitis/reference/hepatitis-comorbidities.php', '/hepatitis/reference/add-hepatitis-comorbidities.php,/hepatitis/reference/edit-hepatitis-comorbidities.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitis-comorbidities', 13, 50, 'active', NULL),
(51, 'admin', 'no', 'Risk Factors', '/hepatitis/reference/hepatitis-risk-factors.php', '/hepatitis/reference/add-hepatitis-risk-factors.php,/hepatitis/reference/edit-hepatitis-risk-factors.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitis-risk-factors', 13, 51, 'active', NULL),
(52, 'admin', 'no', 'Rejection Reasons', '/hepatitis/reference/hepatitis-sample-rejection-reasons.php', '/hepatitis/reference/add-hepatitis-sample-rejection-reasons.php,/hepatitis/reference/edit-hepatitis-sample-rejection-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitis-sample-rejection-reasons', 13, 52, 'active', NULL),
(53, 'admin', 'no', 'Sample Type', '/hepatitis/reference/hepatitis-sample-type.php', '/hepatitis/reference/add-hepatitis-sample-type.php,/hepatitis/reference/edit-hepatitis-sample-type.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitis-sample-type', 13, 53, 'active', NULL),
(54, 'admin', 'no', 'Results', '/hepatitis/reference/hepatitis-results.php', '/hepatitis/reference/add-hepatitis-results.php,/hepatitis/reference/edit-hepatitis-results.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitis-results', 13, 54, 'active', NULL),
(55, 'admin', 'no', 'Test Reasons', '/hepatitis/reference/hepatitis-test-reasons.php', '/hepatitis/reference/add-hepatitis-test-reasons.php,/hepatitis/reference/edit-hepatitis-test-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitis-test-reasons', 13, 55, 'active', NULL),
(56, 'admin', 'no', 'Rejection Reasons', '/tb/reference/tb-sample-rejection-reasons.php', '/tb/reference/add-tb-sample-rejection-reason.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tb-sample-rejection-reasons', 14, 56, 'active', NULL),
(57, 'admin', 'no', 'Sample Type', '/tb/reference/tb-sample-type.php', '/tb/reference/add-tb-sample-type.php,/tb/reference/edit-tb-sample-type.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tb-sample-type', 14, 57, 'active', NULL),
(58, 'admin', 'no', 'Test Reasons', '/tb/reference/tb-test-reasons.php', '/tb/reference/add-tb-test-reasons.php,/tb/reference/edit-tb-test-reasons.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tb-test-reasons', 14, 58, 'active', NULL),
(59, 'admin', 'no', 'Results', '/tb/reference/tb-results.php', '/tb/reference/add-tb-results.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tb-results', 14, 59, 'active', NULL),
(60, 'generic-tests', 'yes', 'OTHER LAB TESTS', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 8, 'active', NULL),
(61, 'generic-tests', 'no', 'Request Management', NULL, NULL, 'always', 'fa-solid fa-pen-to-square', 'yes', 'treeview allMenu generic-test-request-menu', 60, 61, 'active', NULL),
(62, 'generic-tests', 'no', 'Test Result Management', NULL, NULL, 'always', 'fa-solid fa-list-check', 'yes', 'treeview allMenu generic-test-results-menu', 60, 62, 'active', NULL),
(63, 'generic-tests', 'no', 'Management', NULL, NULL, 'always', 'fa-solid fa-book', 'yes', 'treeview allMenu generic-test-request-menu', 60, 63, 'active', NULL),
(64, 'vl', 'yes', 'HIV VIRAL LOAD', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 3, 'active', NULL),
(65, 'eid', 'yes', 'EARLY INFANT DIAGNOSIS (EID)', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 4, 'active', NULL),
(66, 'covid19', 'yes', 'COVID-19', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 5, 'active', NULL),
(67, 'hepatitis', 'yes', 'HEPATITIS', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 6, 'active', NULL),
(68, 'tb', 'yes', 'TUBERCULOSIS', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 7, 'active', NULL),
(69, 'vl', 'no', 'Request Management', NULL, NULL, 'always', 'fa-solid fa-pen-to-square', 'yes', 'treeview request', 64, 69, 'active', NULL),
(70, 'vl', 'no', 'Test Result Management', NULL, NULL, 'always', 'fa-solid fa-list-check', 'yes', 'treeview test', 64, 70, 'active', NULL),
(71, 'vl', 'no', 'Management', NULL, NULL, 'always', 'fa-solid fa-book', 'yes', 'treeview program', 64, 71, 'active', NULL),
(72, 'covid19', 'no', 'Request Management', NULL, NULL, 'always', 'fa-solid fa-pen-to-square', 'yes', 'treeview covid19Request', 66, 72, 'active', NULL),
(73, 'covid19', 'no', 'Test Result Management', NULL, NULL, 'always', 'fa-solid fa-list-check', 'yes', 'treeview covid19Results', 66, 73, 'active', NULL),
(74, 'covid19', 'no', 'Management', NULL, NULL, 'always', 'fa-solid fa-book', 'yes', 'treeview covid19ProgramMenu', 66, 74, 'active', NULL),
(75, 'eid', 'no', 'Request Management', NULL, NULL, 'always', 'fa-solid fa-pen-to-square', 'yes', 'treeview eidRequest', 65, 75, 'active', NULL),
(76, 'eid', 'no', 'Test Result Management', NULL, NULL, 'always', 'fa-solid fa-list-check', 'yes', 'treeview eidResults', 65, 76, 'active', NULL),
(77, 'eid', 'no', 'Management', NULL, NULL, 'always', 'fa-solid fa-book', 'yes', 'treeview eidProgramMenu', 65, 77, 'active', NULL),
(78, 'hepatitis', 'no', 'Request Management', NULL, NULL, 'always', 'fa-solid fa-pen-to-square', 'yes', 'treeview hepatitisRequest', 67, 78, 'active', NULL),
(79, 'hepatitis', 'no', 'Test Result Management', NULL, NULL, 'always', 'fa-solid fa-list-check', 'yes', 'treeview hepatitisResults', 67, 79, 'active', NULL),
(80, 'hepatitis', 'no', 'Management', NULL, NULL, 'always', 'fa-solid fa-book', 'yes', 'treeview hepatitisProgramMenu', 67, 80, 'active', NULL),
(81, 'tb', 'no', 'Request Management', NULL, NULL, 'always', 'fa-solid fa-pen-to-square', 'yes', 'treeview tbRequest', 68, 81, 'active', NULL),
(82, 'tb', 'no', 'Test Result Management', NULL, NULL, 'always', 'fa-solid fa-list-check', 'yes', 'treeview tbResults', 68, 82, 'active', NULL),
(83, 'tb', 'no', 'Management', NULL, NULL, 'always', 'fa-solid fa-book', 'yes', 'treeview tbProgramMenu', 68, 83, 'active', NULL),
(84, 'generic-tests', 'no', 'View Test Requests', '/generic-tests/requests/view-requests.php', '/generic-tests/requests/edit-request.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericRequestMenu', 61, 84, 'active', NULL),
(85, 'generic-tests', 'no', 'Add New Request', '/generic-tests/requests/add-request.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu addGenericRequestMenu', 61, 85, 'active', NULL),
(86, 'generic-tests', 'no', 'Add Samples from Manifest', '/generic-tests/requests/add-samples-from-manifest.php', '/generic-tests/requests/edit-request.php', 'lis', 'fa-solid fa-caret-right', 'no', 'allMenu addGenericSamplesFromManifestMenu', 61, 86, 'active', NULL),
(87, 'generic-tests', 'no', 'Manage Batch', '/batch/batches.php?type=generic-tests', '/batch/add-batch.php?type=generic-tests,/batch/edit-batch.php?type=generic-tests,/batch/add-batch-position.php?type=generic-tests,/batch/edit-batch-position.php?type=generic-tests', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu batchGenericCodeMenu', 61, 87, 'active', NULL),
(88, 'generic-tests', 'no', 'Lab Test Manifest', '/specimen-referral-manifest/view-manifests.php?t=generic-tests', '/specimen-referral-manifest/add-manifest.php?t=generic-tests,/specimen-referral-manifest/edit-manifest.php?t=generic-tests,/specimen-referral-manifest/move-manifest.php?t=generic-tests', 'sts', 'fa-solid fa-caret-right', 'no', 'allMenu specimenGenericReferralManifestListMenu', 61, 88, 'active', NULL),
(89, 'generic-tests', 'no', 'Enter Result Manually', '/generic-tests/results/generic-test-results.php', '/generic-tests/results/update-generic-test-result.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericTestResultMenu', 62, 88, 'active', NULL),
(90, 'generic-tests', 'no', 'Failed/Hold Samples', '/generic-tests/results/generic-failed-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericFailedResultMenu', 62, 88, 'active', NULL),
(91, 'generic-tests', 'no', 'Manage Results Status', '/generic-tests/results/generic-result-approval.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericResultApprovalMenu', 62, 88, 'active', NULL),
(92, 'generic-tests', 'no', 'Sample Status Report', '/generic-tests/program-management/generic-sample-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericStatusReportMenu', 63, 88, 'active', NULL),
(93, 'generic-tests', 'no', 'Export Results', '/generic-tests/program-management/generic-export-data.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericExportMenu', 63, 89, 'active', NULL),
(94, 'generic-tests', 'no', 'Print Result', '/generic-tests/results/generic-print-result.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericPrintResultMenu', 63, 90, 'active', NULL),
(95, 'generic-tests', 'no', 'Sample Rejection Report', '/generic-tests/program-management/sample-rejection-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericSampleRejectionReport', 63, 91, 'active', NULL),
(96, 'vl', 'no', 'View Test Requests', '/vl/requests/vl-requests.php', '/vl/requests/editVlRequest.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlRequestMenu', 69, 92, 'active', NULL),
(97, 'vl', 'no', 'Add New Request', '/vl/requests/addVlRequest.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu addVlRequestMenu', 69, 93, 'active', NULL),
(98, 'vl', 'no', 'Add Samples from Manifest', '/vl/requests/addSamplesFromManifest.php', NULL, 'lis', 'fa-solid fa-caret-right', 'no', 'allMenu addSamplesFromManifestMenu', 69, 94, 'active', NULL),
(99, 'vl', 'no', 'Manage Batch', '/batch/batches.php?type=vl', '/batch/add-batch.php?type=vl,/batch/edit-batch.php?type=vl,/batch/edit-batch-position.php?type=vl', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu batchCodeMenu', 69, 95, 'active', NULL),
(100, 'vl', 'no', 'VL Manifest', '/specimen-referral-manifest/view-manifests.php?t=vl', '/specimen-referral-manifest/add-manifest.php?t=vl,/specimen-referral-manifest/edit-manifest.php?t=vl,/specimen-referral-manifest/move-manifest.php?t=vl', 'sts', 'fa-solid fa-caret-right', 'no', 'allMenu specimenReferralManifestListVLMenu', 69, 96, 'active', NULL),
(101, 'vl', 'no', 'Import Result From File', '/import-result/import-file.php?t=vl', '/import-result/imported-results.php?t=vl,/import-result/importedStatistics.php?t=vl', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu importResultMenu', 70, 97, 'active', NULL),
(102, 'vl', 'no', 'Enter Result Manually', '/vl/results/vlTestResult.php', '/vl/results/updateVlTestResult.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlTestResultMenu', 70, 98, 'active', NULL),
(103, 'vl', 'no', 'Failed/Hold Samples', '/vl/results/vl-failed-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlFailedResultMenu', 70, 99, 'active', NULL),
(104, 'vl', 'no', 'Manage Results Status', '/vl/results/vlResultApproval.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu batchCodeMenu', 70, 100, 'active', NULL),
(105, 'vl', 'no', 'Sample Status Report', '/vl/program-management/vl-sample-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu missingResultMenu', 71, 100, 'active', NULL),
(106, 'vl', 'no', 'Control Report', '/vl/program-management/vlControlReport.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlResultMenu', 71, 101, 'active', NULL),
(107, 'vl', 'no', 'Export Results', '/vl/program-management/vl-export-data.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlResultMenu', 71, 102, 'active', NULL),
(108, 'vl', 'no', 'Print Result', '/vl/results/vlPrintResult.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlPrintResultMenu', 71, 103, 'active', NULL),
(109, 'vl', 'no', 'Clinic Reports', '/vl/program-management/highViralLoad.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlHighMenu', 71, 104, 'active', NULL),
(110, 'vl', 'no', 'VL Lab Weekly Report', '/vl/program-management/vlWeeklyReport.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlWeeklyReport', 71, 105, 'active', NULL),
(111, 'vl', 'no', 'Sample Rejection Report', '/vl/program-management/sampleRejectionReport.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu sampleRejectionReport', 71, 106, 'active', NULL),
(112, 'vl', 'no', 'Sample Monitoring Report', '/vl/program-management/vlMonitoringReport.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlMonitoringReport', 71, 107, 'active', NULL),
(113, 'vl', 'no', 'VL Testing Target Report', '/vl/program-management/vlTestingTargetReport.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlMonthlyThresholdReport', 71, 108, 'active', NULL),
(114, 'eid', 'no', 'View Test Requests', '/eid/requests/eid-requests.php', '/eid/requests/eid-edit-request.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidRequestMenu', 75, 109, 'active', NULL),
(115, 'eid', 'no', 'Add New Request', '/eid/requests/eid-add-request.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu addEidRequestMenu', 75, 110, 'active', NULL),
(116, 'eid', 'no', 'Add Samples from Manifest', '/eid/requests/addSamplesFromManifest.php', NULL, 'lis', 'fa-solid fa-caret-right', 'no', 'allMenu addSamplesFromManifestEidMenu', 75, 111, 'active', NULL),
(117, 'eid', 'no', 'Manage Batch', '/batch/batches.php?type=eid', '/batch/add-batch.php?type=eid,/batch/edit-batch.php?type=eid,/batch/add-batch-position.php?type=eid,/batch/edit-batch-position.php?type=eid', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidBatchCodeMenu', 75, 112, 'active', NULL),
(118, 'eid', 'no', 'EID Manifest', '/specimen-referral-manifest/view-manifests.php?t=eid', '/specimen-referral-manifest/add-manifest.php?t=eid,/specimen-referral-manifest/edit-manifest.php?t=eid,/specimen-referral-manifest/move-manifest.php?t=eid', 'sts', 'fa-solid fa-caret-right', 'no', 'allMenu specimenReferralManifestListEIDMenu', 75, 113, 'active', NULL),
(119, 'eid', 'no', 'Import Result From File', '/import-result/import-file.php?t=eid', '/import-result/imported-results.php?t=eid,/import-result/importedStatistics.php?t=eid', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidImportResultMenu', 76, 114, 'active', NULL),
(120, 'eid', 'no', 'Enter Result Manually', '/eid/results/eid-manual-results.php', '/eid/results/eid-update-result.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidResultsMenu', 76, 115, 'active', NULL),
(121, 'eid', 'no', 'Failed/Hold Samples', '/eid/results/eid-failed-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidFailedResultsMenu', 76, 116, 'active', NULL),
(122, 'eid', 'no', 'Manage Results Status', '/eid/results/eid-result-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidResultStatus', 76, 117, 'active', NULL),
(123, 'eid', 'no', 'Sample Status Report', '/eid/management/eid-sample-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidSampleStatus', 77, 118, 'active', NULL),
(124, 'eid', 'no', 'Export Results', '/eid/management/eid-export-data.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidExportResult', 77, 119, 'active', NULL),
(125, 'eid', 'no', 'Print Result', '/eid/results/eid-print-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidPrintResults', 77, 120, 'active', NULL),
(126, 'eid', 'no', 'Sample Rejection Report', '/eid/management/eid-sample-rejection-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidSampleRejectionReport', 77, 121, 'active', NULL),
(127, 'eid', 'no', 'Clinic Report', '/eid/management/eid-clinic-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidClinicReport', 77, 122, 'active', NULL),
(128, 'eid', 'no', 'EID Testing Target Report', '/eid/management/eidTestingTargetReport.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidMonthlyThresholdReport', 77, 123, 'active', NULL),
(129, 'covid19', 'no', 'View Test Requests', '/covid-19/requests/covid-19-requests.php', '/covid-19/requests/covid-19-edit-request.php,/covid-19/requests/covid-19-bulk-import-request.php,/covid-19/requests/covid-19-quick-add.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19RequestMenu', 72, 124, 'active', NULL),
(130, 'covid19', 'no', 'Add New Request', '/covid-19/requests/covid-19-add-request.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu addCovid19RequestMenu', 72, 125, 'active', NULL),
(131, 'covid19', 'no', 'Add Samples from Manifest', '/covid-19/requests/addSamplesFromManifest.php', NULL, 'lis', 'fa-solid fa-caret-right', 'no', 'allMenu addSamplesFromManifestCovid19Menu', 72, 126, 'active', NULL),
(132, 'covid19', 'no', 'Manage Batch', '/batch/batches.php?type=covid19', '/batch/add-batch.php?type=covid19,/batch/edit-batch.php?type=covid19,/batch/add-batch-position.php?type=covid19,/batch/edit-batch-position.php?type=covid19', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19BatchCodeMenu', 72, 127, 'active', NULL),
(133, 'covid19', 'no', 'Covid-19 Manifest', '/specimen-referral-manifest/view-manifests.php?t=covid19', '/specimen-referral-manifest/add-manifest.php?t=covid19,/specimen-referral-manifest/edit-manifest.php?t=covid19,/specimen-referral-manifest/move-manifest.php?t=covid19', 'sts', 'fa-solid fa-caret-right', 'no', 'allMenu specimenReferralManifestListC19Menu', 72, 128, 'active', NULL),
(134, 'covid19', 'no', 'Import Result From File', '/import-result/import-file.php?t=covid19', '/import-result/imported-results.php?t=covid19,/import-result/importedStatistics.php?t=covid19', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19ImportResultMenu', 73, 129, 'active', NULL),
(135, 'covid19', 'no', 'Enter Result Manually', '/covid-19/results/covid-19-manual-results.php', '/covid-19/batch/covid-19-update-result.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19ResultsMenu', 73, 130, 'active', NULL),
(136, 'covid19', 'no', 'Failed/Hold Samples', '/covid-19/results/covid-19-failed-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19FailedResultsMenu', 73, 131, 'active', NULL),
(137, 'covid19', 'no', 'Confirmation Manifest', '/covid-19/results/covid-19-confirmation-manifest.php', NULL, 'lis', 'fa-solid fa-caret-right', 'no', 'allMenu covid19ResultsConfirmationMenu', 73, 132, 'active', NULL),
(138, 'covid19', 'no', 'Record Confirmatory Tests', '/covid-19/results/can-record-confirmatory-tests.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu canRecordConfirmatoryTestsCovid19Menu', 73, 133, 'active', NULL),
(139, 'covid19', 'no', 'Manage Results Status', '/covid-19/results/covid-19-result-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19ResultStatus', 73, 134, 'active', NULL),
(140, 'covid19', 'no', 'Covid-19 QC Data', '/covid-19/results/covid-19-qc-data.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19QcDataMenu', 73, 135, 'active', NULL),
(141, 'covid19', 'no', 'Sample Status Report', '/covid-19/management/covid-19-sample-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19SampleStatus', 74, 136, 'active', NULL),
(142, 'covid19', 'no', 'Export Results', '/covid-19/management/covid-19-export-data.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19ExportResult', 74, 137, 'active', NULL),
(143, 'covid19', 'no', 'Print Result', '/covid-19/results/covid-19-print-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19PrintResults', 74, 138, 'active', NULL),
(144, 'covid19', 'no', 'Sample Rejection Report', '/covid-19/management/covid-19-sample-rejection-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19SampleRejectionReport', 74, 139, 'active', NULL),
(145, 'covid19', 'no', 'Clinic Reports', '/covid-19/management/covid-19-clinic-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19ClinicReportMenu', 74, 140, 'active', NULL),
(146, 'covid19', 'no', 'COVID-19 Testing Target Report', '/covid-19/management/covid19TestingTargetReport.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19MonthlyThresholdReport', 74, 141, 'active', NULL),
(147, 'hepatitis', 'no', 'View Test Requests', '/hepatitis/requests/hepatitis-requests.php', '/hepatitis/requests/hepatitis-edit-request.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisRequestMenu', 78, 142, 'active', NULL),
(148, 'hepatitis', 'no', 'Add New Request', '/hepatitis/requests/hepatitis-add-request.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu addHepatitisRequestMenu', 78, 143, 'active', NULL),
(149, 'hepatitis', 'no', 'Add Samples from Manifest', '/hepatitis/requests/add-samples-from-manifest.php', NULL, 'lis', 'fa-solid fa-caret-right', 'no', 'allMenu addSamplesFromManifestHepatitisMenu', 78, 144, 'active', NULL),
(150, 'hepatitis', 'no', 'Manage Batch', '/batch/batches.php?type=hepatitis', '/batch/add-batch.php?type=hepatitis,/batch/edit-batch.php?type=hepatitis,/batch/add-batch-position.php?type=hepatitis,/batch/edit-batch-position.php?type=hepatitis', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisBatchCodeMenu', 78, 145, 'active', NULL),
(151, 'hepatitis', 'no', 'Hepatitis Manifest', '/specimen-referral-manifest/view-manifests.php?t=hepatitis', '/specimen-referral-manifest/add-manifest.php?t=hepatitis,/specimen-referral-manifest/edit-manifest.php?t=hepatitis,/specimen-referral-manifest/move-manifest.php?t=hepatitis', 'sts', 'fa-solid fa-caret-right', 'no', 'allMenu specimenReferralManifestListHepMenu', 78, 146, 'active', NULL),
(152, 'hepatitis', 'no', 'Import Result From File', '/import-result/import-file.php?t=hepatitis', '/import-result/imported-results.php?t=hepatitis,/import-result/importedStatistics.php?t=hepatitis', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisImportResultMenu', 79, 146, 'active', NULL),
(153, 'hepatitis', 'no', 'Enter Result Manually', '/hepatitis/results/hepatitis-manual-results.php', '/hepatitis/results/hepatitis-update-result.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisResultsMenu', 79, 147, 'active', NULL),
(154, 'hepatitis', 'no', 'Failed/Hold Samples', '/hepatitis/results/hepatitis-failed-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisFailedResultsMenu', 79, 148, 'active', NULL),
(155, 'hepatitis', 'no', 'Manage Results Status', '/hepatitis/results/hepatitis-result-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisResultStatus', 79, 149, 'active', NULL),
(156, 'hepatitis', 'no', 'Sample Status Report', '/hepatitis/management/hepatitis-sample-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisSampleStatus', 80, 150, 'active', NULL),
(157, 'hepatitis', 'no', 'Export Results', '/hepatitis/management/hepatitis-export-data.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisExportResult', 80, 151, 'active', NULL),
(158, 'hepatitis', 'no', 'Print Result', '/hepatitis/results/hepatitis-print-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisPrintResults', 80, 152, 'active', NULL),
(159, 'hepatitis', 'no', 'Sample Rejection Report', '/hepatitis/management/hepatitis-sample-rejection-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisSampleRejectionReport', 80, 153, 'active', NULL),
(160, 'hepatitis', 'no', 'Clinic Reports', '/hepatitis/management/hepatitis-clinic-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisClinicReportMenu', 80, 154, 'active', NULL),
(161, 'hepatitis', 'no', 'Hepatitis Testing Target Report', '/hepatitis/management/hepatitis-testing-target-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisMonthlyThresholdReport', 80, 155, 'active', NULL),
(162, 'tb', 'no', 'View Test Requests', '/tb/requests/tb-requests.php', '/tb/requests/tb-edit-request.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbRequestMenu', 81, 156, 'active', NULL),
(163, 'tb', 'no', 'Add New Request', '/tb/requests/tb-add-request.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu addTbRequestMenu', 81, 157, 'active', NULL),
(164, 'tb', 'no', 'Add Samples from Manifest', '/tb/requests/addSamplesFromManifest.php', NULL, 'lis', 'fa-solid fa-caret-right', 'no', 'allMenu addSamplesFromManifestTbMenu', 81, 158, 'active', NULL),
(165, 'tb', 'no', 'Manage Batch', '/batch/batches.php?type=tb', '/batch/add-batch.php?type=tb,/batch/edit-batch.php?type=tb,/batch/add-batch-position.php?type=tb,/batch/edit-batch-position.php?type=tb', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbBatchCodeMenu', 81, 159, 'active', NULL),
(166, 'tb', 'no', 'TB Manifest', '/specimen-referral-manifest/view-manifests.php?t=tb', '/specimen-referral-manifest/add-manifest.php?t=tb,/specimen-referral-manifest/edit-manifest.php?t=tb,/specimen-referral-manifest/move-manifest.php?t=tb', 'sts', 'fa-solid fa-caret-right', 'no', 'allMenu specimenReferralManifestListTbMenu', 81, 160, 'active', NULL),
(167, 'tb', 'no', 'Import Result From File', '/import-result/import-file.php?t=tb', '/import-result/imported-results.php?t=tb,/import-result/importedStatistics.php?t=tb', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbImportResultMenu', 82, 161, 'active', NULL),
(168, 'tb', 'no', 'Enter Result Manually', '/tb/results/tb-manual-results.php', '/tb/results/tb-update-result.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbResultsMenu', 82, 162, 'active', NULL),
(169, 'tb', 'no', 'Failed/Hold Samples', '/tb/results/tb-failed-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbFailedResultsMenu', 82, 163, 'active', NULL),
(170, 'tb', 'no', 'Manage Results Status', '/tb/results/tb-result-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbResultStatus', 82, 164, 'active', NULL),
(171, 'tb', 'no', 'Sample Status Report', '/tb/management/tb-sample-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbSampleStatus', 83, 165, 'active', NULL),
(172, 'tb', 'no', 'Print Result', '/tb/results/tb-print-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbPrintResults', 83, 166, 'active', NULL),
(173, 'tb', 'no', 'Export Results', '/tb/management/tb-export-data.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbExportResult', 83, 167, 'active', NULL),
(174, 'tb', 'no', 'Sample Rejection Report', '/tb/management/tb-sample-rejection-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbSampleRejectionReport', 83, 168, 'active', NULL),
(175, 'tb', 'no', 'Clinic Reports', '/tb/management/tb-clinic-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbClinicReport', 83, 169, 'active', NULL),
(176, 'admin', 'no', 'Lab Sync Status', '/admin/monitoring/sync-status.php', NULL, 'always', 'fa-solid fa-traffic-light', 'no', 'allMenu treeview api-sync-status-menu', 7, 18, 'active', NULL),
(177, 'admin', 'no', 'Recommended Corrective Actions', '/vl/reference/vl-recommended-corrective-actions.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vl-recommended-corrective-actions', 10, 39, 'active', '2023-08-02 14:27:09');


ALTER TABLE `s_app_menu`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `link` (`link`,`parent_id`);

ALTER TABLE `s_app_menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=177;




-- FIXING COMMON PRIVILEGES
DELETE FROM privileges WHERE `privilege_name` like "batches.php";
DELETE FROM privileges WHERE `privilege_name` like "add-batch.php";
DELETE FROM privileges WHERE `privilege_name` like "edit-batch.php";
DELETE FROM privileges WHERE `privilege_name` like "delete-batch-code.php";
DELETE FROM privileges WHERE `privilege_name` like "add-batch-position.php";
DELETE FROM privileges WHERE `privilege_name` like "edit-batch-position.php";
DELETE FROM privileges WHERE `privilege_name` like "batch-code.php";
DELETE FROM privileges WHERE `privilege_name` like "eid-import-result.php";
DELETE FROM privileges WHERE `privilege_name` like "covid-19-import-result.php";
DELETE FROM privileges WHERE `privilege_name` like "%addImportResult.php";
DELETE FROM privileges WHERE `privilege_name` like "%specimenReferralManifestList.php%";
DELETE FROM privileges WHERE `privilege_name` like "%addSpecimenReferralManifest.php%";
DELETE FROM privileges WHERE `privilege_name` like "%editSpecimenReferralManifest.php%";
DELETE FROM privileges WHERE `privilege_name` like "%move-manifest.php%";
UPDATE `privileges` set privilege_name = CONCAT("/import-configs/",privilege_name) WHERE resource_id like "import-config" and privilege_name NOT LIKE "/import-config/%";

-- FIXING VL PRIVILEGES
DELETE FROM privileges WHERE `privilege_name` like "vlRequestMail.php";
DELETE FROM privileges WHERE `privilege_name` like "vlResultMail.php";
DELETE FROM privileges WHERE `privilege_name` like "/batch/edit-batch-position.php?type=vl";
DELETE FROM privileges WHERE `privilege_name` like "/batch/add-batch-position.php?type=vl";

UPDATE `privileges` set privilege_name = CONCAT("/vl/requests/",privilege_name) WHERE resource_id like "vl-requests" and privilege_name NOT LIKE "/vl/requests/%" and privilege_name NOT LIKE "/specimen-referral-manifest/%";
UPDATE `privileges` set privilege_name = CONCAT("/vl/results/",privilege_name) WHERE resource_id like "vl-results" and privilege_name NOT LIKE "/vl/results/%" and privilege_name NOT LIKE '/import-result/%';
UPDATE `privileges` set privilege_name = CONCAT("/vl/program-management/",privilege_name) WHERE resource_id like "vl-reports" and privilege_name NOT LIKE "/vl/program-management/%";
UPDATE `privileges` set privilege_name = CONCAT("/vl/reference/",privilege_name) WHERE resource_id like "vl-reference" and privilege_name NOT LIKE "/vl/reference/%";

-- FIXING EID PRIVILEGES
UPDATE `privileges` set privilege_name = CONCAT("/eid/requests/",privilege_name) WHERE resource_id like "eid-requests" and privilege_name NOT LIKE "/eid/requests/%" and privilege_name NOT LIKE "/specimen-referral-manifest/%";
UPDATE `privileges` set privilege_name = CONCAT("/eid/results/",privilege_name) WHERE resource_id like "eid-results" and privilege_name NOT LIKE "/eid/results/%" and privilege_name NOT LIKE '/import-result/%';
UPDATE `privileges` set privilege_name = CONCAT("/eid/management/",privilege_name) WHERE resource_id like "eid-management" and privilege_name NOT LIKE "/eid/management/%";
UPDATE `privileges` set privilege_name = CONCAT("/eid/reference/",privilege_name) WHERE resource_id like "eid-reference" and privilege_name NOT LIKE "/eid/reference/%";

-- FIXING COVID-19 PRIVILEGES
UPDATE `privileges` set privilege_name = CONCAT("/covid-19/requests/",privilege_name) WHERE resource_id like "covid-19-requests" and privilege_name NOT LIKE "/covid-19/requests/%" and privilege_name NOT LIKE "/specimen-referral-manifest/%";
UPDATE `privileges` set privilege_name = CONCAT("/covid-19/results/",privilege_name) WHERE resource_id like "covid-19-results" and privilege_name NOT LIKE "/covid-19/results/%" and privilege_name NOT LIKE '/import-result/%';
UPDATE `privileges` set privilege_name = CONCAT("/covid-19/management/",privilege_name) WHERE resource_id like "covid-19-management" and privilege_name NOT LIKE "/covid-19/management/%";
UPDATE `privileges` set privilege_name = CONCAT("/covid-19/reference/",privilege_name) WHERE resource_id like "covid-19-reference" and privilege_name NOT LIKE "/covid-19/reference/%";
INSERT IGNORE INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES (NULL, 'covid-19-requests', '/covid-19/requests/addSamplesFromManifest.php', 'Add Samples from Manifest');

-- FIXING HEPATITIS PRIVILEGES
DELETE FROM privileges WHERE `privilege_name` like "hepatitis-add-batch-position.php";
DELETE FROM privileges WHERE `privilege_name` like "hepatitis-edit-batch-position.php";

UPDATE `privileges` SET `resource_id` = 'hepatitis-results' WHERE `privilege_name` like "%hepatitis-result-status.php";
UPDATE `privileges` SET `resource_id` = 'hepatitis-results' WHERE `privilege_name` like "%hepatitis-print-results.php";
UPDATE `privileges` set privilege_name = CONCAT("/hepatitis/requests/",privilege_name) WHERE resource_id like "hepatitis-requests" and privilege_name NOT LIKE "/hepatitis/requests/%" and privilege_name NOT LIKE "/specimen-referral-manifest/%";
UPDATE `privileges` set privilege_name = CONCAT("/hepatitis/results/",privilege_name) WHERE resource_id like "hepatitis-results" and privilege_name NOT LIKE "/hepatitis/results/%" and privilege_name NOT LIKE '/import-result/%';
UPDATE `privileges` set privilege_name = CONCAT("/hepatitis/management/",privilege_name) WHERE resource_id like "hepatitis-management" and privilege_name NOT LIKE "/hepatitis/management/%";
UPDATE `privileges` set privilege_name = CONCAT("/hepatitis/reference/",privilege_name) WHERE resource_id like "hepatitis-reference" and privilege_name NOT LIKE "/hepatitis/reference/%";

-- FIXING GENERIC TESTS PRIVILEGES
UPDATE `privileges` SET `resource_id` = 'hepatitis-results' WHERE `privilege_name` like "%hepatitis-result-status.php";
UPDATE `privileges` SET `resource_id` = 'hepatitis-results' WHERE `privilege_name` like "%hepatitis-print-results.php";
UPDATE `privileges` set privilege_name = CONCAT("/generic-tests/requests/",privilege_name) WHERE resource_id like "generic-requests" and privilege_name NOT LIKE "/generic-tests/requests/%" and privilege_name NOT LIKE "/specimen-referral-manifest/%";
UPDATE `privileges` set privilege_name = CONCAT("/generic-tests/results/",privilege_name) WHERE resource_id like "generic-results" and privilege_name NOT LIKE "/generic-tests/results/%" and privilege_name NOT LIKE '/import-result/%';
UPDATE `privileges` set privilege_name = CONCAT("/generic-tests/program-management/",privilege_name) WHERE resource_id like "generic-management" and privilege_name NOT LIKE "/generic-tests/program-management/%";
UPDATE `privileges` set privilege_name = CONCAT("/generic-tests/reference/",privilege_name) WHERE resource_id like "generic-test-reference" and privilege_name NOT LIKE "/generic-tests/reference/%";

-- FIXING TB PRIVILEGES
DELETE FROM privileges WHERE `privilege_name` like "tb-add-batch-position.php";
DELETE FROM privileges WHERE `privilege_name` like "tb-edit-batch-position.php";
UPDATE `privileges` SET `resource_id` = 'tb-management' WHERE `privilege_name` like "%tb-sample-status.php";
UPDATE `privileges` SET `resource_id` = 'tb-management' WHERE `privilege_name` like "%tb-clinic-report.php";
UPDATE `privileges` SET `resource_id` = 'tb-management' WHERE `privilege_name` like "%tb-sample-rejection-report.php";
UPDATE `privileges` SET `resource_id` = 'tb-management' WHERE `privilege_name` like "%tb-export-data.php";

UPDATE `privileges` set privilege_name = CONCAT("/tb/requests/",privilege_name) WHERE resource_id like "tb-requests" and privilege_name NOT LIKE "/tb/requests/%" and privilege_name NOT LIKE "/specimen-referral-manifest/%";
UPDATE `privileges` set privilege_name = CONCAT("/tb/results/",privilege_name) WHERE resource_id like "tb-results" and privilege_name NOT LIKE "/tb/results/%" and privilege_name NOT LIKE '/import-result/%';
UPDATE `privileges` set privilege_name = CONCAT("/tb/management/",privilege_name) WHERE resource_id like "tb-management" and privilege_name NOT LIKE "/tb/management/%";
UPDATE `privileges` set privilege_name = CONCAT("/tb/reference/",privilege_name) WHERE resource_id like "tb-reference" and privilege_name NOT LIKE "/tb/reference/%";


-- Import Result from File
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`)
VALUES
(NULL, 'vl-results', '/import-result/import-file.php?t=vl', 'Import VL Results from Files'),
(NULL, 'eid-results', '/import-result/import-file.php?t=eid', 'Import EID Results from Files'),
(NULL, 'covid-19-results', '/import-result/import-file.php?t=covid19', 'Import COVID-19 Results from Files'),
(NULL, 'hepatitis-results', '/import-result/import-file.php?t=hepatitis', 'Import Hepatitis Results from Files'),
(NULL, 'tb-results', '/import-result/import-file.php?t=tb', 'Import TB Results from Files'),
(NULL, 'generic-results', '/import-result/import-file.php?t=generic-tests', 'Import Results from Files');


-- Create Manifest
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`)
VALUES
(NULL, 'vl-requests', '/specimen-referral-manifest/view-manifests.php?t=vl', 'View VL Manifests'),
(NULL, 'eid-requests', '/specimen-referral-manifest/view-manifests.php?t=eid', 'View EID Manifests'),
(NULL, 'covid-19-requests', '/specimen-referral-manifest/view-manifests.php?t=covid19', 'View COVID-19 Manifests'),
(NULL, 'hepatitis-requests', '/specimen-referral-manifest/view-manifests.php?t=hepatitis', 'View Hepatitis Manifests'),
(NULL, 'tb-requests', '/specimen-referral-manifest/view-manifests.php?t=tb', 'View TB Manifests'),
(NULL, 'generic-requests', '/specimen-referral-manifest/view-manifests.php?t=generic-tests', 'View Lab Tests Manifests'),
(NULL, 'vl-requests', '/specimen-referral-manifest/add-manifest.php?t=vl', 'Add VL Manifests'),
(NULL, 'eid-requests', '/specimen-referral-manifest/add-manifest.php?t=eid', 'Add EID Manifests'),
(NULL, 'covid-19-requests', '/specimen-referral-manifest/add-manifest.php?t=covid19', 'Add COVID-19 Manifests'),
(NULL, 'hepatitis-requests', '/specimen-referral-manifest/add-manifest.php?t=hepatitis', 'Add Hepatitis Manifests'),
(NULL, 'tb-requests', '/specimen-referral-manifest/add-manifest.php?t=tb', 'Add TB Manifests'),
(NULL, 'generic-requests', '/specimen-referral-manifest/add-manifest.php?t=generic-tests', 'Add Lab Tests Manifests'),
(NULL, 'vl-requests', '/specimen-referral-manifest/edit-manifest.php?t=vl', 'Edit VL Manifests'),
(NULL, 'eid-requests', '/specimen-referral-manifest/edit-manifest.php?t=eid', 'Edit EID Manifests'),
(NULL, 'covid-19-requests', '/specimen-referral-manifest/edit-manifest.php?t=covid19', 'Edit COVID-19 Manifests'),
(NULL, 'hepatitis-requests', '/specimen-referral-manifest/edit-manifest.php?t=hepatitis', 'Edit Hepatitis Manifests'),
(NULL, 'tb-requests', '/specimen-referral-manifest/edit-manifest.php?t=tb', 'Edit TB Manifests'),
(NULL, 'generic-requests', '/specimen-referral-manifest/edit-manifest.php?t=generic-tests', 'Edit Lab Tests Manifests'),
(NULL, 'vl-requests', '/specimen-referral-manifest/move-manifest.php?t=vl', 'Move VL Manifests'),
(NULL, 'eid-requests', '/specimen-referral-manifest/move-manifest.php?t=eid', 'Move EID Manifests'),
(NULL, 'covid-19-requests', '/specimen-referral-manifest/move-manifest.php?t=covid19', 'Move COVID-19 Manifests'),
(NULL, 'hepatitis-requests', '/specimen-referral-manifest/move-manifest.php?t=hepatitis', 'Move Hepatitis Manifests'),
(NULL, 'tb-requests', '/specimen-referral-manifest/move-manifest.php?t=tb', 'Move TB Manifests'),
(NULL, 'generic-requests', '/specimen-referral-manifest/move-manifest.php?t=generic-tests', 'Move Lab Tests Manifests');


DELETE FROM roles_privileges_map where privilege_id not in (select privilege_id from privileges);
DELETE FROM resources WHERE `resource_id` = 'specimen-referral-manifest';
DELETE FROM privileges WHERE `resource_id` = 'generic-test-batches';

UPDATE `resources` SET `module` = 'generic-tests' WHERE `resources`.`resource_id` = 'generic-tests-batches';

INSERT IGNORE INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES
(NULL, 'generic-tests-batches', '/batch/batches.php?type=generic-tests', 'Manage Batch');

INSERT IGNORE INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES
(NULL, 'generic-tests-batches', '/batch/add-batch.php?type=generic-tests', 'Add New Batch');

INSERT IGNORE INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`) VALUES
(NULL, 'generic-tests-batches', '/batch/edit-batch.php?type=generic-tests', 'Edit Batch');



-- Jeyabanu 26-Jun-2023
ALTER TABLE `form_vl` ADD `no_of_pregnancy_weeks` INT NULL DEFAULT NULL AFTER `is_patient_pregnant`;
ALTER TABLE `audit_form_vl` ADD `no_of_pregnancy_weeks` INT NULL DEFAULT NULL AFTER `is_patient_pregnant`;
ALTER TABLE `form_vl` ADD `no_of_breastfeeding_weeks` INT NULL DEFAULT NULL AFTER `is_patient_breastfeeding`;
ALTER TABLE `audit_form_vl` ADD `no_of_breastfeeding_weeks` INT NULL DEFAULT NULL AFTER `is_patient_breastfeeding`;
ALTER TABLE `form_vl` ADD `current_arv_protocol` TEXT NULL DEFAULT NULL AFTER `line_of_treatment_ref_type`;
ALTER TABLE `audit_form_vl` ADD `current_arv_protocol` TEXT NULL DEFAULT NULL AFTER `line_of_treatment_ref_type`;

ALTER TABLE `form_vl` ADD `control_vl_testing_type` TEXT NULL DEFAULT NULL AFTER `reason_for_vl_testing_other`, ADD `coinfection_type` TEXT NULL DEFAULT NULL AFTER `control_vl_testing_type`;
ALTER TABLE `audit_form_vl` ADD `control_vl_testing_type` TEXT NULL DEFAULT NULL AFTER `reason_for_vl_testing_other`, ADD `coinfection_type` TEXT NULL DEFAULT NULL AFTER `control_vl_testing_type`;

ALTER TABLE `form_eid` ADD `child_weight` INT NULL DEFAULT NULL AFTER `child_gender`;
ALTER TABLE `audit_form_eid` ADD `child_weight` INT NULL DEFAULT NULL AFTER `child_gender`;

ALTER TABLE `form_eid` ADD `child_prophylactic_arv` TEXT NULL DEFAULT NULL AFTER `child_weight`;
ALTER TABLE `form_eid` ADD `child_prophylactic_arv_other` TEXT NULL DEFAULT NULL AFTER `child_prophylactic_arv`;

ALTER TABLE `audit_form_eid` ADD `child_prophylactic_arv` TEXT NULL DEFAULT NULL AFTER `child_weight`;
ALTER TABLE `audit_form_eid` ADD `child_prophylactic_arv_other` TEXT NULL DEFAULT NULL AFTER `child_prophylactic_arv`;

ALTER TABLE `form_eid` ADD `child_treatment_initiation_date` DATE NULL DEFAULT NULL AFTER `child_treatment_other`;
ALTER TABLE `audit_form_eid` ADD `child_treatment_initiation_date` DATE NULL DEFAULT NULL AFTER `child_treatment_other`;
ALTER TABLE `form_eid` ADD `next_appointment_date` DATE NULL DEFAULT NULL AFTER `mother_hiv_status`;
ALTER TABLE `audit_form_eid` ADD `next_appointment_date` DATE NULL DEFAULT NULL AFTER `mother_hiv_status`;
ALTER TABLE `form_eid` ADD `no_of_exposed_children` INT NULL DEFAULT NULL AFTER `next_appointment_date`;
ALTER TABLE `audit_form_eid` ADD `no_of_exposed_children` INT NULL DEFAULT NULL AFTER `next_appointment_date`;
ALTER TABLE `form_eid` ADD `no_of_infected_children` INT NULL DEFAULT NULL AFTER `no_of_exposed_children`;
ALTER TABLE `audit_form_eid` ADD `no_of_infected_children` INT NULL DEFAULT NULL AFTER `no_of_exposed_children`;
ALTER TABLE `form_eid` ADD `mother_arv_protocol` INT NULL DEFAULT NULL AFTER `no_of_infected_children`;
ALTER TABLE `audit_form_eid` ADD `mother_arv_protocol` INT NULL DEFAULT NULL AFTER `no_of_infected_children`;
ALTER TABLE `form_eid` ADD `is_child_symptomatic` INT NULL DEFAULT NULL AFTER `mother_vl_test_date`;
ALTER TABLE `audit_form_eid` ADD `is_child_symptomatic` INT NULL DEFAULT NULL AFTER `mother_vl_test_date`;
ALTER TABLE `form_eid` ADD `date_of_weaning` DATE NULL DEFAULT NULL AFTER `is_child_symptomatic`;
ALTER TABLE `audit_form_eid` ADD `date_of_weaning` DATE NULL DEFAULT NULL AFTER `is_child_symptomatic`;
ALTER TABLE `form_eid` ADD `was_child_breastfed` TEXT NULL DEFAULT NULL AFTER `date_of_weaning`;
ALTER TABLE `audit_form_eid` ADD `was_child_breastfed` TEXT NULL DEFAULT NULL AFTER `date_of_weaning`;
ALTER TABLE `form_eid` ADD `is_child_on_cotrim` TEXT NULL DEFAULT NULL AFTER `choice_of_feeding`;
ALTER TABLE `audit_form_eid` ADD `is_child_on_cotrim` TEXT NULL DEFAULT NULL AFTER `choice_of_feeding`;
ALTER TABLE `form_eid` ADD `child_started_cotrim_date` TEXT NULL DEFAULT NULL AFTER `is_child_on_cotrim`;
ALTER TABLE `audit_form_eid` ADD `child_started_cotrim_date` TEXT NULL DEFAULT NULL AFTER `is_child_on_cotrim`;
ALTER TABLE `form_eid` ADD `sample_collection_reason` TEXT NULL DEFAULT NULL AFTER `rapid_test_result`;
ALTER TABLE `audit_form_eid` ADD `sample_collection_reason` TEXT NULL DEFAULT NULL AFTER `rapid_test_result`;
ALTER TABLE `form_eid` ADD `child_started_art_date` TEXT NULL DEFAULT NULL AFTER `infant_art_status_other`;
ALTER TABLE `audit_form_eid` ADD `child_started_art_date` TEXT NULL DEFAULT NULL AFTER `infant_art_status_other`;
ALTER TABLE `form_eid` ADD `lab_testing_point_other` TEXT NULL DEFAULT NULL AFTER `lab_testing_point`;
ALTER TABLE `audit_form_eid` ADD `lab_testing_point_other` TEXT NULL DEFAULT NULL AFTER `lab_testing_point`;


-- Amit 29-Jun-2023

DELETE FROM `privileges` WHERE `resource_id` = 'generic-test-reference';
DELETE FROM `resources` WHERE `resource_id` = 'generic-test-reference';
DELETE FROM `resources` WHERE `resource_id` = 'common-sample-type';
DELETE FROM `resources` WHERE `resource_id` = 'common-symptoms';
DELETE FROM `resources` WHERE `resource_id` = 'common-testing-reason';
INSERT IGNORE INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('generic-tests-config', 'admin' ,'Configure Generic Lab Tests');
INSERT IGNORE  INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`)
VALUES
(NULL, 'generic-tests-config', '/generic-tests/configuration/test-type.php', 'Add/Edit Test Types'),
(NULL, 'generic-tests-config', '/generic-tests/configuration/sample-types/generic-sample-type.php', 'Manage Sample Types'),
(NULL, 'generic-tests-config', '/generic-tests/configuration/testing-reasons/generic-testing-reason.php', 'Manage Testing Reasons'),
(NULL, 'generic-tests-config', '/generic-tests/configuration/symptoms/generic-symptoms.php', 'Manage Symptoms'),
(NULL, 'generic-tests-config', '/generic-tests/configuration/sample-rejection-reasons/generic-sample-rejection-reasons.php', 'Manage Sample Rejection Reasons'),
(NULL, 'generic-tests-config', '/generic-tests/configuration/test-failure-reasons/generic-test-failure-reason.php', 'Manage Test Failure Reasons'),
(NULL, 'generic-tests-config', '/generic-tests/configuration/test-result-units/generic-test-result-units.php', 'Manage Test Result Units'),
(NULL, 'generic-tests-config', '/generic-tests/configuration/test-methods/generic-test-methods.php', 'Manage Test Methods'),
(NULL, 'generic-tests-config', '/generic-tests/configuration/test-categories/generic-test-categories.php', 'Manage Test Categories');

UPDATE `privileges` SET `privilege_name` = '/generic-tests/results/generic-print-result.php' WHERE `privileges`.`privilege_name` like  "%generic-print-result.php";


DELETE FROM roles_privileges_map where privilege_id not in (select privilege_id from privileges);
UPDATE s_app_menu
SET link = REPLACE(link, '/generic-tests/reference/', '/generic-tests/configuration/')
WHERE link LIKE '%/generic-tests/reference/%';

UPDATE s_app_menu
SET inner_pages = REPLACE(inner_pages, '/generic-tests/reference/', '/generic-tests/configuration/')
WHERE inner_pages LIKE '%/generic-tests/reference/%';


-- Amit 03-Jul-2023
UPDATE s_app_menu SET `module` ="generic-tests"  WHERE `module` LIKE "genericTests";


-- Amit 04-Jul-2023 version 5.1.8
UPDATE `system_config` SET `value` = '5.1.8' WHERE `system_config`.`name` = 'sc_version';

-- Amit 05-Jul-2023

ALTER TABLE `privileges` ADD `display_order` INT NULL DEFAULT NULL AFTER `display_name`;
ALTER TABLE `privileges` ADD `show_mode` VARCHAR (32) NULL DEFAULT 'always' AFTER `display_order`;

DELETE FROM `privileges` WHERE `privilege_name` = '/vl/requests/patientList.php';
DELETE FROM `privileges` WHERE `privilege_name` like '%vlRequestRwdForm.php';
DELETE FROM `privileges` WHERE `privilege_name` = '/vl/requests/sendRequestToMail.php';
DELETE FROM `privileges` WHERE `privilege_name` = '/vl/requests/vlRequestMailConfirm.php';
DELETE FROM `privileges` WHERE `privilege_name` = '/vl/requests/vlResultMailConfirm.php';
UPDATE `privileges` SET `privilege_name` = '/vl/requests/vl-requests.php' WHERE `privilege_name` = "/vl/requests/vlRequest.php";

DELETE FROM roles_privileges_map where privilege_id not in (select privilege_id from privileges);

UPDATE s_app_menu
SET link = '/vl/requests/vl-requests.php'
WHERE link LIKE '%/vlRequest.php';


-- VL REQUESTS AND BATCH PRIVILEGES
UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/vl/requests/vl-requests.php';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/vl/requests/addVlRequest.php';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/vl/requests/editVlRequest.php';
UPDATE `privileges` SET `display_order` = '4' WHERE `privilege_name` = '/vl/requests/export-vl-requests.php';
UPDATE `privileges` SET `display_order` = '5' WHERE `privilege_name` = '/vl/requests/edit-locked-vl-samples';
UPDATE `privileges` SET `display_order` = '6' WHERE `privilege_name` = '/vl/requests/addSamplesFromManifest.php';
UPDATE `privileges` SET `display_order` = '7' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=vl';
UPDATE `privileges` SET `display_order` = '8' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=vl';
UPDATE `privileges` SET `display_order` = '9' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=vl';
UPDATE `privileges` SET `display_order` = '10' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=vl';

UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/batch/batches.php?type=vl';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/batch/add-batch.php?type=vl';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/batch/edit-batch.php?type=vl';

UPDATE `privileges` SET `show_mode` = 'lis' WHERE `privilege_name` = '/vl/requests/addSamplesFromManifest.php';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=vl';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=vl';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=vl';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=vl';


-- EID REQUESTS AND BATCH PRIVILEGES
UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/eid/requests/eid-requests.php';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/eid/requests/eid-add-request.php';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/eid/requests/eid-edit-request.php';
UPDATE `privileges` SET `display_order` = '4' WHERE `privilege_name` = '/eid/requests/export-eid-requests.php';
UPDATE `privileges` SET `display_order` = '5' WHERE `privilege_name` = '/eid/requests/edit-locked-eid-samples';
UPDATE `privileges` SET `display_order` = '6' WHERE `privilege_name` = '/eid/requests/addSamplesFromManifest.php';
UPDATE `privileges` SET `display_order` = '7' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=eid';
UPDATE `privileges` SET `display_order` = '8' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=eid';
UPDATE `privileges` SET `display_order` = '9' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=eid';
UPDATE `privileges` SET `display_order` = '10' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=eid';

UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/batch/batches.php?type=eid';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/batch/add-batch.php?type=eid';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/batch/edit-batch.php?type=eid';

UPDATE `privileges` SET `show_mode` = 'lis' WHERE `privilege_name` = '/eid/requests/addSamplesFromManifest.php';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=eid';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=eid';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=eid';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=eid';

-- COVID-19 REQUESTS AND BATCH PRIVILEGES
UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/covid-19/requests/covid-19-requests.php';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/covid-19/requests/covid-19-add-request.php';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/covid-19/requests/covid-19-edit-request.php';
UPDATE `privileges` SET `display_order` = '4' WHERE `privilege_name` = '/covid-19/requests/export-covid19-requests.php';
UPDATE `privileges` SET `display_order` = '5' WHERE `privilege_name` = '/covid-19/requests/edit-locked-covid19-samples';
UPDATE `privileges` SET `display_order` = '6' WHERE `privilege_name` = '/covid-19/requests/addSamplesFromManifest.php';
UPDATE `privileges` SET `display_order` = '7' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=covid19';
UPDATE `privileges` SET `display_order` = '8' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=covid19';
UPDATE `privileges` SET `display_order` = '9' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=covid19';
UPDATE `privileges` SET `display_order` = '10' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=covid19';

UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/batch/batches.php?type=covid19';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/batch/add-batch.php?type=covid19';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/batch/edit-batch.php?type=covid19';

UPDATE `privileges` SET `show_mode` = 'lis' WHERE `privilege_name` = '/covid-19/requests/addSamplesFromManifest.php';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=covid19';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=covid19';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=covid19';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=covid19';

-- Hepatitis REQUESTS AND BATCH PRIVILEGES
INSERT IGNORE INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'hepatitis-requests', '/hepatitis/requests/edit-locked-hepatitis-samples', 'Edit Locked Samples', '1', 'always');

UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/hepatitis/requests/hepatitis-requests.php';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/hepatitis/requests/hepatitis-add-request.php';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/hepatitis/requests/hepatitis-edit-request.php';
UPDATE `privileges` SET `display_order` = '4' WHERE `privilege_name` = '/hepatitis/requests/export-hepatitis-requests.php';
UPDATE `privileges` SET `display_order` = '5' WHERE `privilege_name` = '/hepatitis/requests/edit-locked-hepatitis-samples';
UPDATE `privileges` SET `display_order` = '6' WHERE `privilege_name` = '/hepatitis/requests/add-samples-from-manifest.php';
UPDATE `privileges` SET `display_order` = '7' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=hepatitis';
UPDATE `privileges` SET `display_order` = '8' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=hepatitis';
UPDATE `privileges` SET `display_order` = '9' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=hepatitis';
UPDATE `privileges` SET `display_order` = '10' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=hepatitis';

UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/batch/batches.php?type=hepatitis';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/batch/add-batch.php?type=hepatitis';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/batch/edit-batch.php?type=hepatitis';

UPDATE `privileges` SET `show_mode` = 'lis' WHERE `privilege_name` = '/hepatitis/requests/add-samples-from-manifest.php';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=hepatitis';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=hepatitis';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=hepatitis';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=hepatitis';

-- TB REQUESTS AND BATCH PRIVILEGES
INSERT IGNORE INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'tb-requests', '/tb/requests/edit-locked-tb-samples', 'Edit Locked Samples', '1', 'always');

UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/tb/requests/tb-requests.php';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/tb/requests/tb-add-request.php';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/tb/requests/tb-edit-request.php';
UPDATE `privileges` SET `display_order` = '4' WHERE `privilege_name` = '/tb/requests/export-tb-requests.php';
UPDATE `privileges` SET `display_order` = '5' WHERE `privilege_name` = '/tb/requests/edit-locked-tb-samples';
UPDATE `privileges` SET `display_order` = '6' WHERE `privilege_name` = '/tb/requests/addSamplesFromManifest.php';
UPDATE `privileges` SET `display_order` = '7' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=tb';
UPDATE `privileges` SET `display_order` = '8' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=tb';
UPDATE `privileges` SET `display_order` = '9' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=tb';
UPDATE `privileges` SET `display_order` = '10' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=tb';

UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/batch/batches.php?type=tb';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/batch/add-batch.php?type=tb';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/batch/edit-batch.php?type=tb';

UPDATE `privileges` SET `show_mode` = 'lis' WHERE `privilege_name` = '/tb/requests/addSamplesFromManifest.php';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=tb';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=tb';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=tb';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=tb';



-- Generic Tests REQUESTS AND BATCH PRIVILEGES
INSERT IGNORE INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'generic-tests-requests', '/generic-tests/requests/edit-locked-generic-tests-samples', 'Edit Locked Samples', null, 'always');
INSERT IGNORE INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'generic-tests-requests', '/generic-tests/requests/export-generic-tests-requests.php', 'Export Requests', null, 'always');

UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/generic-tests/requests/view-requests.php';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/generic-tests/requests/add-request.php';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/generic-tests/requests/edit-request.php';
UPDATE `privileges` SET `display_order` = '4' WHERE `privilege_name` = '/generic-tests/requests/export-generic-tests-requests.php';
UPDATE `privileges` SET `display_order` = '5' WHERE `privilege_name` = '/generic-tests/requests/edit-locked-generic-tests-samples';
UPDATE `privileges` SET `display_order` = '6' WHERE `privilege_name` = '/generic-tests/requests/add-samples-from-manifest.php';
UPDATE `privileges` SET `display_order` = '7' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=generic-tests';
UPDATE `privileges` SET `display_order` = '8' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=generic-tests';
UPDATE `privileges` SET `display_order` = '9' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=generic-tests';
UPDATE `privileges` SET `display_order` = '10' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=generic-tests';

UPDATE `privileges` SET `display_order` = '1' WHERE `privilege_name` = '/batch/batches.php?type=generic-tests';
UPDATE `privileges` SET `display_order` = '2' WHERE `privilege_name` = '/batch/add-batch.php?type=generic-tests';
UPDATE `privileges` SET `display_order` = '3' WHERE `privilege_name` = '/batch/edit-batch.php?type=generic-tests';

UPDATE `privileges` SET `show_mode` = 'lis' WHERE `privilege_name` = '/generic-tests/requests/add-samples-from-manifest.php';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=generic-tests';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/add-manifest.php?t=generic-tests';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/edit-manifest.php?t=generic-tests';
UPDATE `privileges` SET `show_mode` = 'sts' WHERE `privilege_name` = '/specimen-referral-manifest/move-manifest.php?t=generic-tests';


-- ALTER TABLE `privileges` ADD `privilege_id2` VARCHAR(256) NULL DEFAULT NULL AFTER `resource_id`;
UPDATE `resources` SET `resource_id` = 'facilities' WHERE `resource_id` = 'facility';
UPDATE `privileges` SET `resource_id` = 'facilities' WHERE `resource_id` = 'facility';
UPDATE `resources` SET `display_name`='Manage Instruments', `resource_id` = 'instruments' WHERE `resource_id` = 'import-config';
UPDATE `privileges` SET `resource_id` = 'instruments' WHERE `resource_id` = 'import-config';


UPDATE s_app_menu SET link = '/instruments/instruments.php' WHERE link LIKE '%/importConfig.php';

UPDATE s_app_menu SET inner_pages = "/instruments/add-instrument.php,/instruments/edit-instrument.php"
WHERE link = '/instruments/instruments.php';

UPDATE `privileges` SET `display_order` = '1' , `privilege_name` ='/instruments/instruments.php' WHERE `privilege_name` like '%/importConfig.php';
UPDATE `privileges` SET `display_order` = '2' , `privilege_name` ='/instruments/add-instrument.php' WHERE `privilege_name` like '%/addImportConfig.php';
UPDATE `privileges` SET `display_order` = '3' ,`privilege_name` ='/instruments/edit-instrument.php' WHERE `privilege_name` like '%/editImportConfig.php';


--

ALTER TABLE `form_vl` CHANGE `patient_first_name` `patient_first_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_middle_name` `patient_middle_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_last_name` `patient_last_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_vl` ADD INDEX(`patient_first_name`);
ALTER TABLE `form_vl` ADD INDEX(`patient_middle_name`);
ALTER TABLE `form_vl` ADD INDEX(`patient_last_name`);
ALTER TABLE `audit_form_vl` CHANGE `patient_first_name` `patient_first_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_middle_name` `patient_middle_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL, CHANGE `patient_last_name` `patient_last_name` VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;


-- Amit 06-Jul-2023 version 5.1.9
UPDATE `system_config` SET `value` = '5.1.9' WHERE `system_config`.`name` = 'sc_version';

-- Jeyabanu 07-Jul-2023
ALTER TABLE `form_tb` ADD `requesting_clinician` VARCHAR(100) NULL DEFAULT NULL AFTER `facility_id`;
ALTER TABLE `audit_form_tb` ADD `requesting_clinician` VARCHAR(100) NULL DEFAULT NULL AFTER `facility_id`;

-- Amit 07-Jul-2023
ALTER TABLE `failed_result_retest_tracker` CHANGE `update_by` `updated_by` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

-- Amit 10-Jul-2023
UPDATE s_app_menu set s_app_menu.inner_pages = null where s_app_menu.link like '%/generic-result-approval.php' or  s_app_menu.link like '%/generic-failed-results.php';

-- Thana 11-Jul-2023
UPDATE `s_app_menu` SET `parent_id` = '63' WHERE `s_app_menu`.`id` = 92;

-- Jeyabanu 12-Jul-2023
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Generic Sample Code Format', 'generic_sample_code', 'MMYY', 'generic-tests', 'yes', '2021-11-02 17:48:32', NULL, 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Generic Minimum Length', 'generic_min_length', NULL, 'generic-tests', 'yes', '2021-11-02 18:16:53', NULL, 'active'), ('Generic Maximum Length', 'generic_max_length', NULL, 'generic-tests', 'yes', '2021-11-02 18:16:53', NULL, 'active');

UPDATE `global_config` SET `category` = 'generic-tests' WHERE `global_config`.`name` = 'generic_interpret_and_convert_results';

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Sample Lock Expiry Days', 'generic_sample_lock_after_days', NULL, 'generic-tests', 'yes', '2021-11-02 17:48:32', NULL, 'active'),
('Auto Approve API Results', 'generic_auto_approve_api_results', NULL, 'generic-tests', 'yes', '2021-11-02 17:48:32', NULL, 'active'),
('Lab Tests Show Participant Name in Manifest', 'generic_show_participant_name_in_manifest', NULL, 'generic-tests', 'yes', '2021-11-02 17:48:32', NULL, 'active');

UPDATE `system_config` SET `value` = '5.2.0' WHERE `system_config`.`name` = 'sc_version';
