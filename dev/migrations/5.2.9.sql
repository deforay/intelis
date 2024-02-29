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
ALTER TABLE `form_cd4` CHANGE `result` `cd4_result` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;
ALTER TABLE `form_cd4` CHANGE `result_percentage` `cd4_result_percentage` VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;


-- Jeyabanu 13-Feb-2024
ALTER TABLE `testing_labs` CHANGE `test_type` `test_type` ENUM('vl','eid','covid19','hepatitis','tb','cd4','generic-tests') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;
ALTER TABLE `health_facilities` CHANGE `test_type` `test_type` ENUM('vl','eid','covid19','hepatitis','tb','cd4','generic-tests') CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL;

-- Jeyabanu 16-Feb-2024
ALTER TABLE `form_cd4` ADD `referring_lab_id` INT NULL DEFAULT NULL AFTER `samples_referred_datetime`;

-- Amit 20-Feb-2024
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Display VL Log Result', 'vl_display_signature_table', 'yes', 'vl', 'no', NULL, NULL, 'active');
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('Display VL Log Result', 'vl_display_page_no_in_footer', 'yes', 'vl', 'no', NULL, NULL, 'active');

-- Amit 21-Feb-2024
ALTER TABLE `form_eid` CHANGE COLUMN second_DBS_requested_reason second_dbs_requested_reason VARCHAR(256) NULL DEFAULT NULL
ALTER TABLE `form_eid` ADD `second_dbs_requested_reason` VARCHAR(256) NULL DEFAULT NULL AFTER `second_dbs_requested`;
ALTER TABLE `audit_form_eid`  ADD `second_dbs_requested_reason` VARCHAR(256) NULL DEFAULT NULL AFTER `second_dbs_requested`;

--Jeyabanu 23-Feb-2024
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('CD4 Maximum Length', 'cd4_max_length', '', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('CD4 Minimum Length', 'cd4_min_length', '', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('Minimum Patient ID Length', 'cd4_min_patient_id_length', '', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('CD4 Sample Code Format', 'cd4_sample_code', 'MMYY', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('CD4 Sample Code Prefix', 'cd4_sample_code_prefix', 'CD4', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('CD4 Sample Expiry Days', 'cd4_sample_expiry_after_days', '999', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('CD4 Sample Lock Expiry Days', 'cd4_sample_lock_after_days', '999', 'cd4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active'), ('Show Participant Name in Manifest', 'cd4_show_participant_name_in_manifest', 'yes', 'CD4', 'no', '2024-02-21 15:13:51', '456456amit2w343ersd3456t4yrgdfsew2', 'active');

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
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-requests', '/specimen-referral-manifest/add-manifest.php?t=cd4', NULL, 'Add CD4 Manifests', NULL, 'always'), (NULL, 'cd4-requests', '/specimen-referral-manifest/edit-manifest.php?t=cd4', NULL, 'Edit CD4 Manifests', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-batches', '/batch/batches.php?type=cd4', '[\r\n    \"/batch/generate-batch-pdf.php?type=cd4\"\r\n]', 'View Batches', NULL, 'always'), (NULL, 'cd4-batches', '/batch/add-batch.php?type=cd4', '[\r\n    \"/batch/add-batch-position.php?type=cd4\"\r\n]', 'Add Batch', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-batches', '/batch/edit-batch.php?type=cd4', '[ "/batch/delete-batch.php?type=cd4", "/batch/edit-batch-position.php?type=cd4" ]', 'Edit Batches', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-results', '/cd4/results/cd4-result-status.php', NULL, 'Manage Result Status', NULL, 'always'), (NULL, 'cd4-results', '/cd4/results/email-results.php', '[\"/cd4/results/email-results.php\",\"/cd4/results/email-results-confirm.php\"]', 'Email Test Results', NULL, 'always'), (NULL, 'cd4-results', '/import-result/import-file.php?t=cd4', '["/import-result/imported-results.php?t=cd4","/import-result/importedStatistics.php?t=cd4"]', 'Import Result from Files', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-management', '/cd4/management/cd4-clinic-report.php', NULL, 'CD4 Clinic Reports', NULL, 'always'), (NULL, 'cd4-management', '/cd4/management/cd4-export-data.php', NULL, 'Export Data', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-management', '/cd4/management/cd4-sample-rejection-report.php', NULL, 'Sample Rejection Report', NULL, 'always'), (NULL, 'cd4-management', '/cd4/management/cd4-sample-status.php', NULL, 'Sample Status Report', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-results', '/cd4/results/cd4-print-results.php', NULL, 'Print Results', NULL, 'always');
INSERT INTO `privileges` (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`) VALUES (NULL, 'cd4-requests', '/cd4/requests/add-samples-from-manifest.php', NULL, 'Add Samples from Manifest', '6', 'lis');

