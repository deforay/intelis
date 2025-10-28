-- Amit 07-Feb-2024 version 5.2.9
UPDATE `system_config` SET `value` = '5.2.9' WHERE `system_config`.`name` = 'sc_version';


-- Amit 09-Feb-2024

CREATE TABLE IF NOT EXISTS `form_cd4` (
  `cd4_id` int(11) NOT NULL,
  `unique_id` varchar(64) DEFAULT NULL,
  `vlsm_instance_id` varchar(64) NOT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `remote_sample` varchar(10) NOT NULL DEFAULT 'no',
  `remote_sample_code` varchar(64) DEFAULT NULL,
  `external_sample_code` varchar(64) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `facility_sample_id` varchar(64) DEFAULT NULL,
  `sample_batch_id` varchar(11) DEFAULT NULL,
  `sample_package_id` int(11) DEFAULT NULL,
  `sample_package_code` varchar(64) DEFAULT NULL,
  `sample_reordered` varchar(3) DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(64) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(64) DEFAULT NULL,
  `sample_code` varchar(64) DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `system_patient_code` varchar(64) DEFAULT NULL,
  `patient_first_name` varchar(64) DEFAULT NULL,
  `patient_middle_name` varchar(64) DEFAULT NULL,
  `patient_last_name` varchar(64) DEFAULT NULL,
  `patient_responsible_person` varchar(64) DEFAULT NULL,
  `patient_nationality` int(11) DEFAULT NULL,
  `patient_province` varchar(64) DEFAULT NULL,
  `patient_district` varchar(64) DEFAULT NULL,
  `patient_art_no` varchar(64) DEFAULT NULL,
  `is_encrypted` varchar(10) DEFAULT 'no',
  `patient_dob` date DEFAULT NULL,
  `patient_below_five_years` varchar(255) DEFAULT NULL,
  `patient_gender` varchar(10) DEFAULT NULL,
  `patient_mobile_number` varchar(20) DEFAULT NULL,
  `patient_address` mediumtext,
  `sample_collection_date` datetime DEFAULT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `specimen_type` int(11) DEFAULT NULL,
  `is_patient_new` varchar(45) DEFAULT NULL,
  `line_of_treatment` int(11) DEFAULT NULL,
  `current_regimen` varchar(64) DEFAULT NULL,
  `date_of_initiation_of_current_regimen` date DEFAULT NULL,
  `is_patient_pregnant` varchar(3) DEFAULT NULL,
  `no_of_pregnancy_weeks` int(11) DEFAULT NULL,
  `is_patient_breastfeeding` varchar(3) DEFAULT NULL,
  `no_of_breastfeeding_weeks` int(11) DEFAULT NULL,
  `pregnancy_trimester` int(11) DEFAULT NULL,
  `arv_adherance_percentage` varchar(64) DEFAULT NULL,
  `consent_to_receive_sms` varchar(64) DEFAULT NULL,
  `last_cd4_date` date DEFAULT NULL,
  `last_cd4_result` varchar(64) DEFAULT NULL,
  `last_cd4_result_percentage` varchar(64) DEFAULT NULL,
  `request_clinician_name` varchar(64) DEFAULT NULL,
  `test_requested_on` date DEFAULT NULL,
  `request_clinician_phone_number` varchar(32) DEFAULT NULL,
  `sample_testing_date` datetime DEFAULT NULL,
  `cd4_focal_person` varchar(64) DEFAULT NULL,
  `cd4_focal_person_phone_number` varchar(64) DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `is_sample_rejected` varchar(10) DEFAULT NULL,
  `sample_rejection_facility` int(11) DEFAULT NULL,
  `reason_for_sample_rejection` int(11) DEFAULT NULL,
  `recommended_corrective_action` int(11) DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `request_created_by` varchar(50) DEFAULT NULL,
  `request_created_datetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_modified_by` varchar(64) DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `patient_other_id` text,
  `patient_age_in_years` int(11) DEFAULT NULL,
  `patient_age_in_months` int(11) DEFAULT NULL,
  `treatment_initiated_date` date DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `samples_referred_datetime` datetime DEFAULT NULL,
  `lab_technician` varchar(64) DEFAULT NULL,
  `lab_contact_person` varchar(64) DEFAULT NULL,
  `lab_phone_number` varchar(64) DEFAULT NULL,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `result` varchar(64) DEFAULT NULL,
  `result_percentage` varchar(255) DEFAULT NULL,
  `approver_comments` mediumtext,
  `result_modified` varchar(3) DEFAULT NULL,
  `reason_for_result_changes` text,
  `tested_by` varchar(50) DEFAULT NULL,
  `lab_tech_comments` mediumtext,
  `result_approved_by` varchar(64) DEFAULT NULL,
  `result_approved_datetime` datetime DEFAULT NULL,
  `revised_by` varchar(64) DEFAULT NULL,
  `revised_on` datetime DEFAULT NULL,
  `result_reviewed_by` varchar(64) DEFAULT NULL,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `contact_complete_status` text,
  `reason_for_cd4_testing` int(11) DEFAULT NULL,
  `reason_for_cd4_testing_other` text,
  `sample_collected_by` varchar(64) DEFAULT NULL,
  `facility_comments` mediumtext,
  `cd4_test_platform` varchar(64) DEFAULT NULL,
  `instrument_id` varchar(50) DEFAULT NULL,
  `import_machine_name` int(11) DEFAULT NULL,
  `facility_support_partner` varchar(64) DEFAULT NULL,
  `has_patient_changed_regimen` varchar(45) DEFAULT NULL,
  `reason_for_regimen_change` varchar(64) DEFAULT NULL,
  `regimen_change_date` date DEFAULT NULL,
  `physician_name` varchar(64) DEFAULT NULL,
  `date_test_ordered_by_physician` date DEFAULT NULL,
  `date_dispatched_from_clinic_to_lab` datetime DEFAULT NULL,
  `result_printed_datetime` datetime DEFAULT NULL,
  `result_sms_sent_datetime` datetime DEFAULT NULL,
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
  `is_request_mail_sent` varchar(3) DEFAULT 'no',
  `request_mail_datetime` datetime DEFAULT NULL,
  `is_result_mail_sent` varchar(10) NOT NULL DEFAULT 'no',
  `app_sample_code` varchar(64) DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `is_result_sms_sent` varchar(3) DEFAULT 'no',
  `test_request_export` int(11) NOT NULL DEFAULT '0',
  `test_request_import` int(11) NOT NULL DEFAULT '0',
  `test_result_export` int(11) NOT NULL DEFAULT '0',
  `test_result_import` int(11) NOT NULL DEFAULT '0',
  `request_exported_datetime` datetime DEFAULT NULL,
  `request_imported_datetime` datetime DEFAULT NULL,
  `result_exported_datetime` datetime DEFAULT NULL,
  `result_imported_datetime` datetime DEFAULT NULL,
  `result_status` int(11) NOT NULL,
  `locked` varchar(10) DEFAULT 'no',
  `import_machine_file_name` text,
  `manual_result_entry` varchar(10) DEFAULT NULL,
  `requesting_facility_id` int(11) DEFAULT NULL,
  `requesting_person` text,
  `requesting_phone` text,
  `requesting_date` date DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  `file_name` varchar(255) DEFAULT NULL,
  `result_coming_from` varchar(255) DEFAULT NULL,
  `first_line` varchar(32) DEFAULT NULL,
  `second_line` varchar(32) DEFAULT NULL,
  `vldash_sync` int(11) DEFAULT '0',
  `source_of_request` text,
  `source_data_dump` text,
  `result_sent_to_source` varchar(10) DEFAULT 'pending',
  `result_sent_to_source_datetime` datetime DEFAULT NULL,
  `form_attributes` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for table `form_cd4`
--
ALTER TABLE `form_cd4`
  ADD PRIMARY KEY (`cd4_id`),
  ADD UNIQUE KEY `sample_code` (`sample_code`),
  ADD UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  ADD UNIQUE KEY `sample_code_2` (`sample_code`,`lab_id`),
  ADD UNIQUE KEY `unique_id` (`unique_id`),
  ADD UNIQUE KEY `lab_id_2` (`lab_id`,`app_sample_code`),
  ADD KEY `facility_id` (`facility_id`),
  ADD KEY `art_no` (`patient_art_no`),
  ADD KEY `sample_id` (`specimen_type`),
  ADD KEY `created_by` (`request_created_by`),
  ADD KEY `funding_source` (`funding_source`),
  ADD KEY `sample_collection_date` (`sample_collection_date`),
  ADD KEY `sample_tested_datetime` (`sample_tested_datetime`),
  ADD KEY `lab_id` (`lab_id`),
  ADD KEY `result_status` (`result_status`),
  ADD KEY `result_approved_by` (`result_approved_by`),
  ADD KEY `result_reviewed_by` (`result_reviewed_by`),
  ADD KEY `sample_package_id` (`sample_package_id`),
  ADD KEY `patient_first_name` (`patient_first_name`),
  ADD KEY `patient_middle_name` (`patient_middle_name`),
  ADD KEY `patient_last_name` (`patient_last_name`),
  ADD KEY `reason_for_cd4_testing` (`reason_for_cd4_testing`),
  ADD KEY `sample_batch_id` (`sample_batch_id`),
  ADD KEY `implementing_partner` (`implementing_partner`),
  ADD KEY `reason_for_sample_rejection` (`reason_for_sample_rejection`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `form_cd4`
--
ALTER TABLE `form_cd4`
  MODIFY `cd4_id` int(11) NOT NULL AUTO_INCREMENT;

CREATE TABLE IF NOT EXISTS `r_cd4_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS `r_cd4_sample_types` (
  `sample_id` int(11) NOT NULL AUTO_INCREMENT,
  `sample_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `r_cd4_test_reasons` (
  `test_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_reason_name` varchar(255) DEFAULT NULL,
  `parent_reason` int(11) DEFAULT '0',
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) DEFAULT '0',
  PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Brindha 08-Feb-2024 version 5.2.9
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Display VL Log Result', 'vl_display_log_result', 'yes', 'vl', 'no', NULL, NULL, 'active');

-- Jeyabanu 12-Feb-2024
ALTER TABLE `form_cd4` CHANGE `result` `cd4_result` VARCHAR(64) CHARACTER SET utf8mb4 NULL DEFAULT NULL;
ALTER TABLE `form_cd4` CHANGE `result_percentage` `cd4_result_percentage` VARCHAR(255) CHARACTER SET utf8mb4 NULL DEFAULT NULL;


-- Jeyabanu 13-Feb-2024
ALTER TABLE `testing_labs` CHANGE `test_type` `test_type` ENUM('vl','eid','covid19','hepatitis','tb','cd4','generic-tests') CHARACTER SET utf8mb4 NOT NULL;
ALTER TABLE `health_facilities` CHANGE `test_type` `test_type` ENUM('vl','eid','covid19','hepatitis','tb','cd4','generic-tests') CHARACTER SET utf8mb4 NOT NULL;

-- Jeyabanu 16-Feb-2024
ALTER TABLE `form_cd4` ADD `referring_lab_id` INT NULL DEFAULT NULL AFTER `samples_referred_datetime`;

-- Amit 20-Feb-2024
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Display VL Log Result', 'vl_display_signature_table', 'yes', 'vl', 'no', NULL, NULL, 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Display VL Log Result', 'vl_display_page_no_in_footer', 'yes', 'vl', 'no', NULL, NULL, 'active');

-- Amit 21-Feb-2024
ALTER TABLE `form_eid` CHANGE COLUMN second_DBS_requested_reason second_dbs_requested_reason VARCHAR(256) NULL DEFAULT NULL;
ALTER TABLE `form_eid` ADD `second_dbs_requested_reason` VARCHAR(256) NULL DEFAULT NULL AFTER `second_dbs_requested`;
ALTER TABLE `audit_form_eid`  ADD `second_dbs_requested_reason` VARCHAR(256) NULL DEFAULT NULL AFTER `second_dbs_requested`;

--Jeyabanu 23-Feb-2024
-- INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('CD4 Maximum Length', 'cd4_max_length', '', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('CD4 Minimum Length', 'cd4_min_length', '', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('Minimum Patient ID Length', 'cd4_min_patient_id_length', '', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('CD4 Sample Code Format', 'cd4_sample_code', 'MMYY', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('CD4 Sample Code Prefix', 'cd4_sample_code_prefix', 'CD4', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('CD4 Sample Expiry Days', 'cd4_sample_expiry_after_days', '999', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('CD4 Sample Lock Expiry Days', 'cd4_sample_lock_after_days', '999', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('Show Participant Name in Manifest', 'cd4_show_participant_name_in_manifest', 'yes', 'CD4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active');

--Brindha 29-Feb-2024
UPDATE `privileges` SET `resource_id` = 'cd4-management' WHERE `privileges`.`privilege_name` = "/cd4/results/cd4-print-results.php";

UPDATE `resources` SET `resource_id` = 'cd4-management' WHERE `resources`.`resource_id` = 'cd4-reports';

-- Jeyabanu 09-Feb-2024
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('cd4-requests', 'cd4', 'CD4 Requests'), ('cd4-results', 'cd4', 'CD4 Results'), ('cd4-management', 'cd4', 'CD4 Reports');
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('cd4-batches', 'cd4', 'CD4 Batch Management');



INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-requests', '/cd4/requests/cd4-add-request.php', NULL, 'Add', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-requests', '/cd4/requests/cd4-edit-request.php', NULL, 'Edit', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-results', '/cd4/results/cd4-manual-results.php', '[\"/cd4/results/cd4-update-result.php\", \"/cd4/results/cd4-failed-results.php\"]', 'Enter Result Manually', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-requests', '/cd4/requests/cd4-requests.php', NULL, 'View', NULL, 'always'), (NULL, 'cd4-requests', '/cd4/requests/export-cd4-requests.php', NULL, 'Export CD4 Requests', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-requests', '/specimen-referral-manifest/add-manifest.php?t=cd4', NULL, 'Add CD4 Manifests', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-requests', '/specimen-referral-manifest/edit-manifest.php?t=cd4', NULL, 'Edit CD4 Manifests', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-requests', '/specimen-referral-manifest/view-manifests.php?t=cd4', NULL, 'View CD4 Manifests', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-batches', '/batch/batches.php?type=cd4', '[\r\n    \"/batch/generate-batch-pdf.php?type=cd4\"\r\n]', 'View Batches', NULL, 'always'), (NULL, 'cd4-batches', '/batch/add-batch.php?type=cd4', '[\r\n    \"/batch/add-batch-position.php?type=cd4\"\r\n]', 'Add Batch', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-batches', '/batch/edit-batch.php?type=cd4', '[ "/batch/delete-batch.php?type=cd4", "/batch/edit-batch-position.php?type=cd4" ]', 'Edit Batches', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-results', '/cd4/results/cd4-result-status.php', NULL, 'Manage Result Status', NULL, 'always'), (NULL, 'cd4-results', '/cd4/results/email-results.php', '[\"/cd4/results/email-results.php\",\"/cd4/results/email-results-confirm.php\"]', 'Email Test Results', NULL, 'always'), (NULL, 'cd4-results', '/import-result/import-file.php?t=cd4', '["/import-result/imported-results.php?t=cd4","/import-result/importedStatistics.php?t=cd4"]', 'Import Result from Files', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-management', '/cd4/management/cd4-clinic-report.php', NULL, 'CD4 Clinic Reports', NULL, 'always'), (NULL, 'cd4-management', '/cd4/management/cd4-export-data.php', NULL, 'Export Data', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-management', '/cd4/management/cd4-sample-rejection-report.php', NULL, 'Sample Rejection Report', NULL, 'always'), (NULL, 'cd4-management', '/cd4/management/cd4-sample-status.php', NULL, 'Sample Status Report', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-results', '/cd4/results/cd4-print-results.php', NULL, 'Print Results', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-requests', '/cd4/requests/add-samples-from-manifest.php', NULL, 'Add Samples from Manifest', '6', 'lis');

-- Jeyabanu 01-Mar-2024
INSERT INTO `resources` (`resource_id`, `module`, `display_name`) VALUES ('cd4-reference', 'cd4', 'CD4 Reference Management');

INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-reference', '/cd4/reference/cd4-sample-type.php', '[\"/cd4/reference/cd4-sample-rejection-reasons.php\", \"/cd4/reference/add-cd4-sample-rejection-reasons.php\", \"edit-cd4-sample-rejection-reasons.php\", \"/cd4/reference/add-cd4-sample-type.php\", \"/cd4/reference/edit-cd4-sample-type.php\", \"/cd4/reference/cd4-test-reasons.php\", \"/cd4/reference/add-cd4-test-reasons.php\", \"/cd4/reference/edit-cd4-test-reasons.php\", \"/cd4/reference/cd4-results.php\", \"/cd4/reference/add-cd4-results.php\", \"/cd4/reference/edit-cd4-results.php\"]', 'Manage CD4 Reference Tables', NULL, 'always');

-- Amit 07-Mar-2024
DELETE s1 FROM s_app_menu s1
JOIN (
    SELECT link, parent_id, MIN(id) as min_id
    FROM s_app_menu
    GROUP BY link, parent_id
) s2 ON s1.link = s2.link AND s1.parent_id = s2.parent_id
WHERE s1.id > s2.min_id;


-- Jeyabanu 08-Mar-2024
UPDATE `s_app_menu` SET `link` = '/covid-19/reference/covid19-sample-type.php' WHERE `s_app_menu`.`link` = '/covid-19/reference/eid-sample-type.php';


-- Jeyabanu 14-Mar-2024
CREATE TABLE `lab_storage` (
  `storage_id` char(36) NOT NULL,
  `storage_code` varchar(255) NOT NULL,
  `lab_id` int NOT NULL,
  `lab_storage_status` varchar(10) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`storage_id`),
  KEY `lab_id` (`lab_id`),
  CONSTRAINT `lab_storage_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `facility_details` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci


INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'common-reference', '/common/reference/lab-storage.php', '[\"/common/reference/add-lab-storage.php\", \"/common/reference/edit-lab-storage.php\"]', 'Manage Lab Storage', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'common-reference', '/common/reference/add-lab-storage.php', NULL, 'Add Lab Storage', NULL, 'always'), (NULL, 'common-reference', '/common/reference/edit-lab-storage.php', NULL, 'Edit Lab Storage', NULL, 'always');


-- Brindha 18-Mar-2024
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Batch PDF Layout', 'batch_pdf_layout', 'standard', 'general', 'no', NULL, NULL, 'active');

-- Jeyabanu 20-Mar-2024
UPDATE `privileges` SET `shared_privileges` = '[\"/vl/requests/upload-storage.php\"]' WHERE `privileges`.`privilege_name` = "/vl/requests/vl-requests.php"

-- Jeyabanu 22-Mar-2024
ALTER TABLE `form_vl` ADD `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL AFTER `patient_gender`;
ALTER TABLE `audit_form_vl` ADD `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL AFTER `patient_gender`;

ALTER TABLE `form_eid` ADD `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL AFTER `child_gender`;
ALTER TABLE `audit_form_eid` ADD `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL AFTER `child_gender`;

ALTER TABLE `form_covid19` ADD `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL AFTER `patient_gender`;
ALTER TABLE `audit_form_covid19` ADD `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL AFTER `patient_gender`;

-- Jeyabanu 26-Mar-2024
ALTER TABLE `lab_storage` ADD `data_sync` INT NOT NULL DEFAULT '0' AFTER `updated_datetime`;
ALTER TABLE `lab_storage` RENAME COLUMN `lab_storage_status` TO `storage_status`;
ALTER TABLE `lab_storage` CHANGE `storage_id` `storage_id` CHAR(50) CHARACTER SET utf8mb4 NOT NULL;

-- Thana 26-Mar-2024
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`,`updated_on`, `updated_by`, `status`) VALUES ('Other Tests Table in Results Pdf', 'generic_tests_table_in_results_pdf', 'no', 'generic-tests', 'yes', '2024-03-26 20:15:07', NULL, 'active');

-- Jeyabanu 27-Mar-2024
UPDATE `s_app_menu` SET `inner_pages` = NULL WHERE `module` = 'generic-tests' AND `link` = '/generic-tests/requests/add-samples-from-manifest.php';

-- Thana 29-Mar-2024
UPDATE `s_app_menu` SET `inner_pages` = null WHERE `s_app_menu`.`link` = '/vl/program-management/vl-sample-status.php' AND `s_app_menu`.`module` = 'vl';

-- Jeyabanu 28-Mar-2024
DELETE s1 FROM generic_test_sample_type_map s1
JOIN (
    SELECT sample_type_id, test_type_id, MIN(map_id) as min_id
    FROM generic_test_sample_type_map
    GROUP BY sample_type_id, test_type_id
) s2 ON s1.sample_type_id = s2.sample_type_id AND s1.test_type_id = s2.test_type_id
WHERE s1.map_id > s2.min_id;

ALTER TABLE generic_test_sample_type_map ADD UNIQUE INDEX idx_sample_type_id_test_type_id (sample_type_id, test_type_id);

DELETE s1 FROM generic_test_reason_map s1 JOIN ( SELECT test_reason_id, test_type_id, MIN(map_id) as min_id FROM generic_test_reason_map GROUP BY test_reason_id, test_type_id ) s2 ON s1.test_reason_id = s2.test_reason_id AND s1.test_type_id = s2.test_type_id WHERE s1.map_id > s2.min_id;

ALTER TABLE generic_test_reason_map ADD UNIQUE INDEX idx_test_reason_id_test_type_id (test_reason_id, test_type_id);

--Jeyabanu 02-Apr-2024
UPDATE `privileges` SET `show_mode` = 'lis' WHERE `privileges`.`privilege_name` = '/common/reference/lab-storage.php';
UPDATE `privileges` SET `show_mode` = 'lis' WHERE `privileges`.`privilege_name` = '/common/reference/add-lab-storage.php';
UPDATE `privileges` SET `show_mode` = 'lis' WHERE `privileges`.`privilege_name` = '/common/reference/edit-lab-storage.php';

UPDATE `s_app_menu` SET `show_mode` = 'lis' WHERE `s_app_menu`.`display_text` = 'Lab Storage';

--Jeyabanu 05-Apr-2024
ALTER TABLE `batch_details` ADD `control_names` JSON NULL DEFAULT NULL AFTER `label_order`;


-- Amit 08-Apr-2024
ALTER TABLE `r_generic_test_reasons` ADD `parent_reason` INT NULL DEFAULT NULL AFTER `test_reason`;


  -- Jeyabanu 09-Apr-2024
  CREATE TABLE IF NOT EXISTS `lab_storage_history` (
    `history_id` int NOT NULL AUTO_INCREMENT,
    `test_type` varchar(20) NOT NULL,
    `sample_unique_id` varchar(256) NOT NULL,
    `volume` decimal(10,2) NOT NULL,
    `freezer_id` char(50) NOT NULL,
    `rack` int NOT NULL,
    `box` int NOT NULL,
    `position` int NOT NULL,
    `sample_status` varchar(50) NOT NULL,
    `updated_datetime` timestamp NOT NULL,
    `updated_by` varchar(100) NOT NULL,
    PRIMARY KEY (`history_id`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Amit 09-Apr-2024
ALTER TABLE `instrument_controls` ADD `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER `number_of_calibrators`;
ALTER TABLE `s_vlsm_instance` ADD `last_vldash_sync` DATETIME NULL DEFAULT NULL;
ALTER TABLE `s_vlsm_instance` ADD `last_lab_metadata_sync` DATETIME NULL DEFAULT NULL AFTER `last_vldash_sync`;

-- Amit 10-Apr-2024
INSERT INTO `s_available_country_forms` (`vlsm_country_id`, `form_name`, `short_name`) VALUES ('8', 'Burkina Faso', 'burkina-faso');

-- Jeyabanu 11-Apr-2024
UPDATE `privileges` SET `shared_privileges` = '[\"/vl/requests/upload-storage.php\",\"/vl/requests/sample-storage.php\"]' WHERE `privileges`.`privilege_name` = '/vl/requests/vl-requests.php';

ALTER TABLE `lab_storage_history` ADD `date_out` DATE NULL DEFAULT NULL AFTER `sample_status`;
ALTER TABLE `lab_storage_history` ADD `comments` TEXT NULL DEFAULT NULL AFTER `date_out`;

-- Thana 15-Apr-2024
ALTER TABLE `form_vl`
ADD `treatment_duration_precise` VARCHAR(50) NULL DEFAULT NULL AFTER `treatment_duration`,
ADD `last_cd4_result` VARCHAR(50) NULL DEFAULT NULL AFTER `treatment_duration_precise`,
ADD `last_cd4_percentage` VARCHAR(50) NULL DEFAULT NULL AFTER `last_cd4_result`,
ADD `last_cd8_result` VARCHAR(50) NULL DEFAULT NULL AFTER `last_cd4_percentage`,
ADD `last_cd4_date` DATE NULL DEFAULT NULL AFTER `last_cd8_result`,
ADD `last_cd8_date` VARCHAR(50) NULL DEFAULT NULL AFTER `last_cd4_date`;

ALTER TABLE `audit_form_vl`
ADD `treatment_duration_precise` VARCHAR(50) NULL DEFAULT NULL AFTER `treatment_duration`,
ADD `last_cd4_result` VARCHAR(50) NULL DEFAULT NULL AFTER `treatment_duration_precise`,
ADD `last_cd4_percentage` VARCHAR(50) NULL DEFAULT NULL AFTER `last_cd4_result`,
ADD `last_cd8_result` VARCHAR(50) NULL DEFAULT NULL AFTER `last_cd4_percentage`,
ADD `last_cd4_date` DATE NULL DEFAULT NULL AFTER `last_cd8_result`,
ADD `last_cd8_date` VARCHAR(50) NULL DEFAULT NULL AFTER `last_cd4_date`;

-- Thana 16-Apr-2024
ALTER TABLE `form_eid` ADD `is_mother_alive` VARCHAR(50) NULL DEFAULT NULL AFTER `request_clinician_phone_number`;
ALTER TABLE `audit_form_eid` ADD `is_mother_alive` VARCHAR(50) NULL DEFAULT NULL AFTER `request_clinician_phone_number`;

-- Amit 18-Apr-2024
UPDATE `roles` SET `landing_page` = '/dashboard/index.php';
-- Thana 18-Apr-2024
ALTER TABLE `form_tb`
ADD `patient_weight` DECIMAL(5,2) NULL DEFAULT NULL AFTER `patient_age`,
ADD `is_displaced_population` VARCHAR(5) NULL DEFAULT NULL AFTER `patient_address`,
ADD `is_referred_by_community_actor` VARCHAR(5) NULL DEFAULT NULL AFTER `is_displaced_population`;

ALTER TABLE `audit_form_tb`
ADD `patient_weight` DECIMAL(5,2) NULL DEFAULT NULL AFTER `patient_age`,
ADD `is_displaced_population` VARCHAR(5) NULL DEFAULT NULL AFTER `patient_address`,
ADD `is_referred_by_community_actor` VARCHAR(5) NULL DEFAULT NULL AFTER `is_displaced_population`;

-- Thana 22-Apr-2024
UPDATE privileges SET resource_id = 'tb-batches' WHERE privilege_id IN(199,200,201);
UPDATE privileges SET privilege_name = '/batch/batches.php?type=tb' WHERE privilege_id = 199;
UPDATE privileges SET privilege_name = '/batch/add-batch.php?type=tb' WHERE privilege_id = 200;
UPDATE privileges SET privilege_name = '/batch/edit-batch.php?type=tb' WHERE privilege_id = 201;

UPDATE privileges SET shared_privileges = '["/batch/generate-batch-pdf.php?type=tb"]' WHERE privilege_id = 199;
UPDATE privileges SET shared_privileges = '["/batch/add-batch-position.php?type=tb"]' WHERE privilege_id = 200;
UPDATE privileges SET shared_privileges = '["/batch/delete-batch.php?type=tb","/batch/edit-batch-position.php?type=tb"]' WHERE privilege_id = 201;

-- Jeyabanu 17-Apr-2024
CREATE TABLE `r_reasons_for_sample_removal` (
  `removal_reason_id` int NOT NULL AUTO_INCREMENT,
  `removal_reason_name` varchar(255) DEFAULT NULL,
  `removal_reason_status` varchar(10) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`removal_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `lab_storage_history` ADD `sample_removal_reason` INT NULL DEFAULT NULL AFTER `comments`;

--Jeyabanu 23-Apr-2024
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'vl-reports', '/vl/program-management/sample-storage-reports.php', NULL, 'Freezer/Storage Reports', NULL, 'lis');

-- Jeyabanu 24-Apr-2024
ALTER TABLE `form_eid` ADD `child_age_in_weeks` INT NULL DEFAULT NULL AFTER `child_age`;
ALTER TABLE `audit_form_eid` ADD `child_age_in_weeks` INT NULL DEFAULT NULL AFTER `child_age`;

-- Amit 25-Apr-2024
ALTER TABLE `global_config` ADD `instance_id` VARCHAR(50) NULL DEFAULT NULL AFTER `value`;
UPDATE global_config AS gc SET gc.instance_id = (SELECT vlsm_instance_id FROM s_vlsm_instance) WHERE instance_id IS NULL;

-- Amit 26-Apr-2024
DELETE a FROM s_app_menu a
JOIN (
    SELECT parent_id, link, MAX(id) as max_id
    FROM s_app_menu
    GROUP BY module, link
    HAVING COUNT(*) > 1
) b ON a.parent_id = b.parent_id AND a.link = b.link
WHERE a.id < b.max_id;

ALTER TABLE `s_app_menu` ADD UNIQUE(`parent_id`, `link`);

-- Amit 28-Apr-2024
ALTER TABLE `global_config` CHANGE `updated_on` `updated_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `log_result_updates` CHANGE `updated_on` `updated_datetime` DATETIME NULL DEFAULT NULL;
ALTER TABLE `testing_labs` CHANGE `test_type` `test_type` VARCHAR(24) CHARACTER SET utf8mb4 NOT NULL;
ALTER TABLE `health_facilities` CHANGE `test_type` `test_type` VARCHAR(24) CHARACTER SET utf8mb4 NOT NULL;

-- Jeyabanu 29-Apr-2024
UPDATE `s_app_menu` SET `show_mode` = 'always' WHERE `s_app_menu`.`link` = '/vl/program-management/sample-storage-reports.php';

-- Thana 29-Apr-2024
ALTER TABLE `form_eid` CHANGE `reason_for_changing` `reason_for_changing` TEXT CHARACTER SET utf8mb4 NULL DEFAULT NULL;
ALTER TABLE `form_covid19` CHANGE `reason_for_changing` `reason_for_changing` TEXT CHARACTER SET utf8mb4 NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `reason_for_changing` `reason_for_changing` TEXT CHARACTER SET utf8mb4 NULL DEFAULT NULL;
ALTER TABLE `form_generic` CHANGE `reason_for_test_result_changes` `reason_for_test_result_changes` TEXT CHARACTER SET utf8mb4 NULL DEFAULT NULL;

-- Thana 30-Apr-2024
ALTER TABLE `user_details` ADD `user_attributes` JSON NULL DEFAULT NULL AFTER `user_signature`;
ALTER TABLE `instruments` CHANGE `additional_text` `additional_text` LONGTEXT CHARACTER SET utf8mb4 NULL DEFAULT NULL;
-- Thana 06-May-2024
ALTER TABLE `r_hepatitis_results` CHANGE `result_id` `result_id` INT NOT NULL AUTO_INCREMENT;
-- Thana 07-May-2024
ALTER TABLE `generic_test_results` CHANGE `sample_tested_datetime` `sample_tested_datetime` DATETIME NULL DEFAULT NULL;


-- Amit 27-May-2024
UPDATE `privileges` SET `privilege_name` = '/specimen-referral-manifest/view-manifests.php?t=cd4' WHERE `privilege_name` LIKE '/specimen-referral-manifest/view-manifest.php?t=cd4';
UPDATE `s_app_menu` SET `display_text` = 'CD4' WHERE `link` LIKE '#cd4'

-- Thana 28-May-2024
ALTER TABLE `batch_details` ADD `created_by` VARCHAR(500) NULL DEFAULT NULL AFTER `control_names`;
-- Thana 29-May-2024
ALTER TABLE `form_vl` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `audit_form_vl` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;

--Jeyabanu 29-May-2024
ALTER TABLE `form_eid` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `audit_form_eid` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;

--Thana 31-May-2024
UPDATE `s_app_menu` SET `link` = '/covid-19/reference/covid19-sample-rejection-reasons.php' WHERE `s_app_menu`.`id` = 44;

--Thana 03-Jun-2024
UPDATE privileges SET shared_privileges = '["/tb/reference/tb-sample-rejection-reasons.php","/tb/reference/add-tb-sample-rejection-reason.php","/tb/reference/add-tb-sample-type.php","/tb/reference/tb-test-reasons.php","/tb/reference/add-tb-test-reasons.php","/tb/reference/tb-results.php","/tb/reference/add-tb-results.php"]' WHERE privilege_name = '/tb/management/tb-sample-type.php';
INSERT INTO `global_config` (`display_name`, `name`, `value`, `instance_id`, `category`, `remote_sync_needed`, `updated_datetime`, `updated_by`, `status`)
VALUES
('Copy Request On Save and Next Form', 'vl_copy_request_save_and_next', 'no', '2ef06893-f8ab-4c72-8946-3ad6c8bd36d1-mq6t', 'vl', 'yes', NULL, NULL, 'active'),
('Copy Request On Save and Next Form', 'eid_copy_request_save_and_next', 'no', '2ef06893-f8ab-4c72-8946-3ad6c8bd36d1-mq6t', 'eid', 'yes', NULL, NULL, 'active'),
('Copy Request On Save and Next Form', 'covid19_copy_request_save_and_next', 'no', '2ef06893-f8ab-4c72-8946-3ad6c8bd36d1-mq6t', 'covid19', 'yes', NULL, NULL, 'active'),
('Copy Request On Save and Next Form', 'hepatitis_copy_request_save_and_next', 'no', '2ef06893-f8ab-4c72-8946-3ad6c8bd36d1-mq6t', 'hepatitis', 'yes', NULL, NULL, 'active'),
('Copy Request On Save and Next Form', 'tb_copy_request_save_and_next', 'no', '2ef06893-f8ab-4c72-8946-3ad6c8bd36d1-mq6t', 'tb', 'yes', NULL, NULL, 'active'),
('Copy Request On Save and Next Form', 'generic_copy_request_save_and_next', 'no', '2ef06893-f8ab-4c72-8946-3ad6c8bd36d1-mq6t', 'generic-tests', 'yes', NULL, NULL, 'active'),
('Copy Request On Save and Next Form', 'cd4_copy_request_save_and_next', 'no', '2ef06893-f8ab-4c72-8946-3ad6c8bd36d1-mq6t', 'cd4', 'yes', NULL, NULL, 'active');

--Brindha 03-Jun-2024
 ALTER TABLE `form_generic` ADD `is_encrypted` varchar(10) DEFAULT 'no' AFTER `patient_address`;
 ALTER TABLE `audit_form_generic` ADD `is_encrypted` VARCHAR(10) DEFAULT 'no' AFTER `patient_address`;

 --Jeyabanu 06-Jun-2024
 ALTER TABLE `batch_details` ADD `batch_attributes` JSON NULL DEFAULT NULL AFTER `batch_status`;


-- Amit 17-Jun-2024
UPDATE `s_app_menu` SET `inner_pages` = '/import-result/imported-results.php?t=generic-tests,/import-result/importedStatistics.php?t=generic-tests' WHERE `link` like '/import-result/import-file.php?t=generic-tests';

-- Amit 25-Jun-2024
CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INT NOT NULL,
    page_id VARCHAR(100) NOT NULL,
    preferences JSON,
    updated_datetime DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, page_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


--Jeyabanu 02-Jun-2024
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'common-reference', 'log-files.php', NULL, 'Log File Viewer', NULL, 'always');

INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'admin', NULL, 'no', 'Log File Viewer', '/admin/monitoring/log-files.php', NULL, 'always', 'fa-solid fa-gears', 'no', 'allMenu treeview log-file-viewer-menu', '7', '19', 'active', CURRENT_TIMESTAMP);


-- Jeyabanu 04-Jul-2024
UPDATE `s_app_menu` SET `inner_pages` = '/facilities/addFacility.php,/facilities/editFacility.php,/facilities/mapTestType.php,/facilities/upload-facilities.php' WHERE `s_app_menu`.`link` = '/facilities/facilities.php';

-- Amit 10-Jul-2024
ALTER TABLE `form_hepatitis` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `audit_form_hepatitis` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_covid19` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `audit_form_covid19` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_tb` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `audit_form_tb` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_cd4` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `audit_form_cd4` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `form_generic` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;
ALTER TABLE `audit_form_generic` ADD `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL AFTER `sample_code`;


-- Jeyabanu 23-Jul-2024
ALTER TABLE `batch_details` ADD `lab_assigned_batch_code` VARCHAR(64) NULL DEFAULT NULL AFTER `machine`;

-- Brindha 23-Jul-2024
INSERT INTO `global_config` (`display_name`, `name`, `value`, `instance_id`, `category`, `remote_sync_needed`, `updated_datetime`, `updated_by`, `status`) VALUES ('VL Lab', 'vl_lab_id', '', '', 'vl', 'no', NULL, NULL, 'active');

-- Brindha 24-Jul-2024
ALTER TABLE `batch_details` CHANGE `last_modified_by` `last_modified_by` VARCHAR(256) CHARACTER SET utf8mb4 NULL;


-- Jeyabanu 31-Jul-2024
DELETE FROM `global_config` WHERE name IN ('lockApprovedVlSamples','vl_sample_expiry_after_days','vl_sample_lock_after_days','eid_min_length','lockApprovedEidSamples','eid_sample_expiry_after_days','eid_sample_lock_after_days','covid19ReportType','covid19_min_length','covid19TestsTableInResultsPdf','lockApprovedCovid19Samples','lockApprovedCovid19Samples','covid19ReportQrCode','covid19_sample_expiry_after_days','covid19_sample_lock_after_days','hepatitis_min_length','hepatitis_sample_expiry_after_days','hepatitis_sample_lock_after_days','hepatitis_auto_approve_api_results','tb_min_length','tb_sample_expiry_after_days','tb_sample_lock_after_days','cd4_sample_expiry_after_days','cd4_sample_lock_after_days','cd4_auto_approve_api_results','generic_min_length','generic_max_length','genericTestsTableInResultsPdf','generic_sample_lock_after_days','generic_auto_approve_api_results' , 'cd4_min_length', 'cd4_max_length', 'covid19_max_length', 'eid_max_length', 'hepatitis_max_length', 'tb_max_length');


-- Amit 03-Aug-2024
CREATE TABLE IF NOT EXISTS queue_sample_code_generation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unique_id VARCHAR(255) NOT NULL,
    test_type VARCHAR(32) NOT NULL,
    access_type VARCHAR(32) NOT NULL,
    sample_collection_date DATE NOT NULL,
    province_code VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL,
    sample_code_format VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL,
    prefix VARCHAR(32) CHARACTER SET utf8mb4 DEFAULT NULL,
    created_datetime DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_datetime DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    processed TINYINT(1) DEFAULT 0
) CHARACTER SET utf8mb4;

-- Brindha 09-Aug-2024
INSERT INTO `global_config` (`display_name`, `name`, `value`, `instance_id`, `category`, `remote_sync_needed`, `updated_datetime`, `updated_by`, `status`) VALUES ('Sample Expiry After Days', 'sample_expiry_after_days', '365', NULL, NULL, 'no', NULL, NULL, 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `instance_id`, `category`, `remote_sync_needed`, `updated_datetime`, `updated_by`, `status`) VALUES ('Sample Lock After Days', 'sample_lock_after_days', '14', NULL, NULL, 'no', NULL, NULL, 'active');

--Jeyabanu 11-Aug-2024
ALTER TABLE `form_tb` ADD `is_patient_pregnant` VARCHAR(3) CHARACTER SET utf8mb4 NULL DEFAULT NULL AFTER `patient_gender`, ADD `is_patient_breastfeeding` VARCHAR(3) CHARACTER SET utf8mb4 NULL DEFAULT NULL AFTER `is_patient_pregnant`;
ALTER TABLE `audit_form_tb` ADD `is_patient_pregnant` VARCHAR(3) CHARACTER SET utf8mb4 NULL DEFAULT NULL AFTER `patient_gender`, ADD `is_patient_breastfeeding` VARCHAR(3) CHARACTER SET utf8mb4 NULL DEFAULT NULL AFTER `is_patient_pregnant`;

-- Brindha 12-Aug-2024
ALTER TABLE batch_details ADD COLUMN printed_datetime DATETIME NULL DEFAULT NULL AFTER control_names;

-- Amit 20-Aug-2024
-- Check if system_config.value is 'vluser' where system_config.name = 'sc_user_type'
UPDATE `roles`
SET `access_type` = 'testing-lab'
WHERE `access_type` IS NULL
  OR `access_type` = ''
  AND EXISTS (
      SELECT 1
      FROM `system_config`
      WHERE `name` = 'sc_user_type'
        AND `value` = 'vluser'
  );

-- Check if system_config.value is 'remoteuser' where system_config.name = 'sc_user_type'
UPDATE `roles`
SET `access_type` = 'collection-site'
WHERE `access_type` IS NULL
  OR `access_type` = ''
  AND EXISTS (
      SELECT 1
      FROM `system_config`
      WHERE `name` = 'sc_user_type'
        AND `value` = 'remoteuser'
  );


ALTER TABLE `form_vl` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_vl` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

ALTER TABLE `form_eid` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_eid` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

ALTER TABLE `form_covid19` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_covid19` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

ALTER TABLE `form_cd4` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_cd4` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

ALTER TABLE `form_hepatitis` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_hepatitis` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;

ALTER TABLE `form_generic` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;
ALTER TABLE `audit_form_generic` ADD `rejection_on` DATE NULL DEFAULT NULL AFTER `reason_for_sample_rejection`;


-- Jeyabanu 22-Aug-2024
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'common-reference', 'test-results-metadata.php', NULL, 'Test Results Metadata', NULL, 'always');
INSERT INTO `s_app_menu` (`id`, `module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES (NULL, 'admin', NULL, 'no', 'Test Results Metadata', '/admin/monitoring/test-results-metadata.php', NULL, 'always', 'fa-solid fa-meta', 'no', 'allMenu treeview test-result-metadata-menu', '7', '20', 'active', CURRENT_TIMESTAMP);

UPDATE `privileges` SET `show_mode` = 'always' WHERE `privileges`.`privilege_name` = '/vl/program-management/sample-storage-reports.php';

-- Jeyabanu 28-Aug-2024
ALTER TABLE `form_vl` ADD `result_sent_to_external` TEXT NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `audit_form_vl` ADD `result_sent_to_external` TEXT NULL DEFAULT NULL AFTER `result_sent_to_source`;
ALTER TABLE `form_vl` ADD `result_sent_to_external_datetime` TEXT NULL DEFAULT NULL AFTER `result_sent_to_external`;
ALTER TABLE `audit_form_vl` ADD `result_sent_to_external_datetime` TEXT NULL DEFAULT NULL AFTER `result_sent_to_external`;

-- Jeyabanu 12-Sep-2024
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`)
VALUES (NULL, 'common-reference', '/admin/monitoring/system-settings.php', NULL, 'System Settings', NULL, 'always');

-- Amit 25-Sep-2024
UPDATE form_vl SET result_status = 5  WHERE result in ('fail%', 'failed');
UPDATE form_vl SET result_status =7 WHERE result is not null and result_status IN (6,10);
UPDATE form_eid SET result_status =7 WHERE result is not null and result_status IN (6,10);
UPDATE form_covid19 SET result_status =7 WHERE result is not null and result_status IN (6,10);
ALTER TABLE `form_cd4` DROP INDEX `sample_code`;

-- Amit 03-Oct-2024
UPDATE form_vl SET result = null WHERE result like '';
UPDATE form_vl SET is_sample_rejected = null WHERE is_sample_rejected like '';
ALTER TABLE form_vl CHANGE is_sample_rejected is_sample_rejected ENUM('yes','no') CHARACTER SET utf8mb4 NULL DEFAULT 'no';
UPDATE form_vl SET result_status = 6 WHERE result_status = 7 and result is null and sample_tested_datetime is null and IFNULL(is_sample_rejected, 'no') = 'no';

UPDATE form_eid SET result = null WHERE result like '';
UPDATE form_eid SET is_sample_rejected = null WHERE is_sample_rejected like '';
ALTER TABLE form_eid CHANGE is_sample_rejected is_sample_rejected ENUM('yes','no') CHARACTER SET utf8mb4 NULL DEFAULT 'no';
UPDATE form_eid SET result_status = 6 WHERE result_status = 7 and result is null and sample_tested_datetime is null and IFNULL(is_sample_rejected, 'no') = 'no';

UPDATE form_hepatitis SET is_sample_rejected = null WHERE is_sample_rejected like '';
ALTER TABLE form_hepatitis CHANGE is_sample_rejected is_sample_rejected ENUM('yes','no') CHARACTER SET utf8mb4 NULL DEFAULT 'no';

UPDATE form_covid19 SET result = null WHERE result like '';
UPDATE form_covid19 SET is_sample_rejected = null WHERE is_sample_rejected like '';
ALTER TABLE form_covid19 CHANGE is_sample_rejected is_sample_rejected ENUM('yes','no') CHARACTER SET utf8mb4 NULL DEFAULT 'no';
UPDATE form_covid19 SET result_status = 6 WHERE result_status = 7 and result is null and sample_tested_datetime is null and IFNULL(is_sample_rejected, 'no') = 'no';

UPDATE form_tb SET result = null WHERE result like '';
UPDATE form_tb SET is_sample_rejected = null WHERE is_sample_rejected like '';
ALTER TABLE form_tb CHANGE is_sample_rejected is_sample_rejected ENUM('yes','no') CHARACTER SET utf8mb4 NULL DEFAULT 'no';
UPDATE form_tb SET result_status = 6 WHERE result_status = 7 and result is null and sample_tested_datetime is null and IFNULL(is_sample_rejected, 'no') = 'no';

UPDATE form_cd4 SET cd4_result = null WHERE cd4_result like '';
UPDATE form_cd4 SET is_sample_rejected = null WHERE is_sample_rejected like '';
ALTER TABLE form_cd4 CHANGE is_sample_rejected is_sample_rejected ENUM('yes','no') CHARACTER SET utf8mb4 NULL DEFAULT 'no';
UPDATE form_cd4 SET result_status = 6 WHERE result_status = 7 and cd4_result is null and sample_tested_datetime is null and IFNULL(is_sample_rejected, 'no') = 'no';

UPDATE form_generic SET result = null WHERE result like '';
UPDATE form_generic SET is_sample_rejected = null WHERE is_sample_rejected like '';
ALTER TABLE form_generic CHANGE is_sample_rejected is_sample_rejected ENUM('yes','no') CHARACTER SET utf8mb4 NULL DEFAULT 'no';
UPDATE form_generic SET result_status = 6 WHERE result_status = 7 and result is null and sample_tested_datetime is null and IFNULL(is_sample_rejected, 'no') = 'no';

-- Jeyabanu 07-Oct-2024
ALTER TABLE `form_generic` CHANGE `sample_received_at_testing_lab_datetime` `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL;

-- Brindha 22-Oct-2024
UPDATE `privileges` SET `shared_privileges` = '[\"/vl/program-management/upload-vl-control.php\"]' WHERE `privileges`.`privilege_name` = '/vl/program-management/vlControlReport.php';


-- Amit 25-Oct-2024
ALTER TABLE `facility_details` ADD `sts_token` VARCHAR(64) NULL DEFAULT NULL AFTER `facility_type`, ADD `sts_token_expiry` DATETIME NULL DEFAULT NULL AFTER `sts_token`;


-- Jeyabanu
ALTER TABLE `s_vlsm_instance` ADD `sts_token` VARCHAR(64) NULL DEFAULT NULL AFTER `instance_facility_logo`;

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
