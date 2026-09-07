-- Migration file for version 5.7.61
-- Created on 2026-09-07
--
-- Amit 07-Sep-2026
--
-- Re-assert everything 5.2.9 adds, so an installation stranded part-way through
-- it is repaired wherever it stopped.
--
-- 5.2.9 stamps its own version on line 2 of 750 and then carries seven months
-- of changes appended to an already-closed version. An installation that
-- upgraded while the file was shorter ran what existed then, recorded 5.2.9 as
-- done, and can never replay it: the runner does not re-run a version it has
-- recorded. Everything appended afterwards is missing there permanently, with
-- nothing to say so until the application fails on an unknown column.
--
-- There is no cut-off to repair up to. Every installation stopped wherever its
-- own upgrade happened to fall, so the only safe assumption is that any of it
-- may be missing.
--
-- The tables come first: 5.2.9 CREATEs nine of them partway through its own
-- run, so an instance that stopped before one has no table for these columns to
-- be added to -- a 1146, which is fatal, so the upgrade would halt here and
-- never reach any later version.
--
-- Their definitions are taken from sql/init.sql, NOT from 5.2.9. Those tables
-- have moved on through thirty-odd migrations since, and an instance stranded
-- before 5.2.9 created them has already recorded every one of those as run, so
-- nothing would ever grow the 2024 shape into the current one. Rebuilding
-- form_cd4 the way 5.2.9 wrote it would leave it missing eleven columns the
-- application reads -- cd4_result among them -- with thirty-two more at the
-- wrong type, and lab_storage would come back carrying lab_storage_status
-- after 5.7.59 has already renamed it. init.sql is what a fresh install has,
-- which is the shape the application is written against.
--
-- The columns are grouped one ALTER per table, NOT one per column. MySQL
-- rebuilds the table for each ALTER it runs, and form_vl is a gigabyte of data
-- on a working instance. The runner takes a multi-action ALTER apart by itself
-- when part of it is already applied, and only then.
--
-- No column carries the AFTER clause 5.2.9 wrote it with. Position is cosmetic,
-- and an anchor is exactly the kind of column a stranded instance is missing --
-- which is a 1054, which is fatal. The runner drops a dead AFTER for a single
-- ADD but cannot for a grouped one, because the statement fails before it can
-- be taken apart.
--
-- Reading 5.2.9 needs more than a split on semicolons: five of its statements
-- are never terminated, and a splitter that joins each to the one after it
-- loses both of them. That is how form_vl.health_insurance_code and
-- batch_details.created_by went missing from an earlier draft of this file.
-- Its longest ALTER also runs to 27 lines, so a line-at-a-time reading drops
-- sixteen more.
--
-- Left out: the audit_form_* copies, whose tables audit_log replaced in 5.5.3,
-- and the two columns 5.7.59 renames, which re-adding would restore under their
-- old names --
--   form_generic.sample_received_at_testing_lab_datetime -> sample_received_at_lab_datetime
--   lab_storage.lab_storage_status                       -> storage_status

-- Tables 5.2.9 creates partway through itself, in their current shape.
CREATE TABLE IF NOT EXISTS `form_cd4` (
  `cd4_id` int NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(64) DEFAULT NULL,
  `vlsm_instance_id` varchar(64) NOT NULL,
  `vlsm_country_id` int DEFAULT NULL,
  `remote_sample` varchar(10) NOT NULL DEFAULT 'no',
  `remote_sample_code` varchar(64) DEFAULT NULL,
  `external_sample_code` varchar(64) DEFAULT NULL,
  `facility_id` int DEFAULT NULL,
  `province_id` int DEFAULT NULL,
  `facility_sample_id` varchar(64) DEFAULT NULL,
  `sample_batch_id` varchar(11) DEFAULT NULL,
  `sample_package_id` int DEFAULT NULL,
  `sample_package_code` varchar(64) DEFAULT NULL,
  `sample_reordered` varchar(3) DEFAULT 'no',
  `remote_sample_code_key` int DEFAULT NULL,
  `remote_sample_code_format` varchar(64) DEFAULT NULL,
  `sample_code_key` int DEFAULT NULL,
  `sample_code_format` varchar(64) DEFAULT NULL,
  `sample_code` varchar(64) DEFAULT NULL,
  `lab_assigned_code` varchar(32) DEFAULT NULL,
  `funding_source` int DEFAULT NULL,
  `implementing_partner` int DEFAULT NULL,
  `system_patient_code` varchar(64) DEFAULT NULL,
  `patient_first_name` varchar(64) DEFAULT NULL,
  `patient_middle_name` varchar(64) DEFAULT NULL,
  `patient_last_name` varchar(64) DEFAULT NULL,
  `patient_responsible_person` varchar(64) DEFAULT NULL,
  `patient_nationality` int DEFAULT NULL,
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
  `specimen_type` int DEFAULT NULL,
  `is_patient_new` varchar(45) DEFAULT NULL,
  `is_patient_initiated_on_art` varchar(10) DEFAULT NULL,
  `line_of_treatment` int DEFAULT NULL,
  `current_regimen` varchar(64) DEFAULT NULL,
  `date_of_initiation_of_current_regimen` date DEFAULT NULL,
  `is_patient_pregnant` varchar(3) DEFAULT NULL,
  `no_of_pregnancy_weeks` int DEFAULT NULL,
  `is_patient_breastfeeding` varchar(3) DEFAULT NULL,
  `no_of_breastfeeding_weeks` int DEFAULT NULL,
  `pregnancy_trimester` int DEFAULT NULL,
  `arv_adherance_percentage` varchar(64) DEFAULT NULL,
  `consent_to_receive_sms` varchar(64) DEFAULT NULL,
  `last_cd4_date` date DEFAULT NULL,
  `last_cd4_result` varchar(64) DEFAULT NULL,
  `last_cd4_result_percentage` varchar(64) DEFAULT NULL,
  `last_cd4_crag_result` varchar(32) DEFAULT NULL,
  `request_clinician_name` varchar(64) DEFAULT NULL,
  `test_requested_on` date DEFAULT NULL,
  `request_clinician_phone_number` varchar(32) DEFAULT NULL,
  `sample_testing_date` datetime DEFAULT NULL,
  `cd4_focal_person` varchar(64) DEFAULT NULL,
  `cd4_focal_person_phone_number` varchar(64) DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `is_sample_rejected` enum('yes','no') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci DEFAULT 'no',
  `sample_rejection_facility` int DEFAULT NULL,
  `reason_for_sample_rejection` int DEFAULT NULL,
  `recommended_corrective_action` int DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `request_created_by` varchar(50) DEFAULT NULL,
  `request_created_datetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_modified_by` varchar(64) DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `patient_other_id` text,
  `patient_age_in_years` int DEFAULT NULL,
  `patient_age_in_months` int DEFAULT NULL,
  `treatment_initiated_date` date DEFAULT NULL,
  `lab_id` int DEFAULT NULL,
  `samples_referred_datetime` datetime DEFAULT NULL,
  `referring_lab_id` int DEFAULT NULL,
  `lab_technician` varchar(64) DEFAULT NULL,
  `lab_contact_person` varchar(64) DEFAULT NULL,
  `lab_phone_number` varchar(64) DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `cd4_result` varchar(64) DEFAULT NULL,
  `cd4_result_percentage` varchar(255) DEFAULT NULL,
  `crag_test_results` varchar(50) DEFAULT NULL,
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
  `reason_for_cd4_testing` int DEFAULT NULL,
  `reason_for_cd4_testing_other` text,
  `sample_collected_by` varchar(64) DEFAULT NULL,
  `facility_comments` mediumtext,
  `cd4_test_platform` varchar(64) DEFAULT NULL,
  `instrument_id` varchar(50) DEFAULT NULL,
  `import_machine_name` int DEFAULT NULL,
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
  `test_request_export` int NOT NULL DEFAULT '0',
  `test_request_import` int NOT NULL DEFAULT '0',
  `test_result_export` int NOT NULL DEFAULT '0',
  `test_result_import` int NOT NULL DEFAULT '0',
  `request_exported_datetime` datetime DEFAULT NULL,
  `request_imported_datetime` datetime DEFAULT NULL,
  `result_exported_datetime` datetime DEFAULT NULL,
  `result_imported_datetime` datetime DEFAULT NULL,
  `result_status` int NOT NULL,
  `locked` varchar(10) DEFAULT 'no',
  `import_machine_file_name` text,
  `manual_result_entry` varchar(10) DEFAULT NULL,
  `requesting_facility_id` int DEFAULT NULL,
  `requesting_person` text,
  `requesting_phone` text,
  `requesting_date` date DEFAULT NULL,
  `data_sync` int NOT NULL DEFAULT '0',
  `file_name` varchar(255) DEFAULT NULL,
  `result_coming_from` varchar(255) DEFAULT NULL,
  `first_line` varchar(32) DEFAULT NULL,
  `second_line` varchar(32) DEFAULT NULL,
  `vldash_sync` int DEFAULT '0',
  `source_of_request` text,
  `source_data_dump` text,
  `result_sent_to_source` varchar(10) DEFAULT 'pending',
  `result_sent_to_source_datetime` datetime DEFAULT NULL,
  `result_pulled_via_api_datetime` datetime DEFAULT NULL,
  `form_attributes` json DEFAULT NULL,
  `reason_for_failure` int DEFAULT NULL,
  `lot_number` text,
  `lot_expiration_date` date DEFAULT NULL,
  PRIMARY KEY (`cd4_id`),
  UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  UNIQUE KEY `sample_code_2` (`sample_code`,`lab_id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  UNIQUE KEY `lab_id_2` (`lab_id`,`app_sample_code`),
  KEY `facility_id` (`facility_id`),
  KEY `art_no` (`patient_art_no`),
  KEY `sample_id` (`specimen_type`),
  KEY `created_by` (`request_created_by`),
  KEY `funding_source` (`funding_source`),
  KEY `sample_collection_date` (`sample_collection_date`),
  KEY `sample_tested_datetime` (`sample_tested_datetime`),
  KEY `lab_id` (`lab_id`),
  KEY `result_status` (`result_status`),
  KEY `result_approved_by` (`result_approved_by`),
  KEY `result_reviewed_by` (`result_reviewed_by`),
  KEY `sample_package_id` (`sample_package_id`),
  KEY `patient_first_name` (`patient_first_name`),
  KEY `patient_middle_name` (`patient_middle_name`),
  KEY `patient_last_name` (`patient_last_name`),
  KEY `reason_for_cd4_testing` (`reason_for_cd4_testing`),
  KEY `sample_batch_id` (`sample_batch_id`),
  KEY `implementing_partner` (`implementing_partner`),
  KEY `reason_for_sample_rejection` (`reason_for_sample_rejection`),
  KEY `idx_sample_code_facility` (`sample_code`,`facility_id`),
  KEY `idx_result_pulled_via_api` (`result_pulled_via_api_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `r_cd4_sample_rejection_reasons` (
  `rejection_reason_id` int NOT NULL AUTO_INCREMENT,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int NOT NULL DEFAULT '0',
  `contributed_by_lab_id` int DEFAULT NULL,
  PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `r_cd4_sample_types` (
  `sample_id` int NOT NULL AUTO_INCREMENT,
  `sample_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `r_cd4_test_reasons` (
  `test_reason_id` int NOT NULL AUTO_INCREMENT,
  `test_reason_name` varchar(255) DEFAULT NULL,
  `parent_reason` int DEFAULT '0',
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int DEFAULT '0',
  PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `lab_storage` (
  `storage_id` char(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `storage_code` varchar(255) NOT NULL,
  `lab_id` int NOT NULL,
  `storage_status` varchar(10) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `data_sync` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`storage_id`),
  KEY `lab_id` (`lab_id`),
  CONSTRAINT `lab_storage_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `facility_details` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

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
  `date_out` date DEFAULT NULL,
  `comments` text,
  `sample_removal_reason` int DEFAULT NULL,
  `updated_datetime` timestamp NOT NULL,
  `updated_by` varchar(100) NOT NULL,
  PRIMARY KEY (`history_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `r_reasons_for_sample_removal` (
  `removal_reason_id` int NOT NULL AUTO_INCREMENT,
  `removal_reason_name` varchar(255) DEFAULT NULL,
  `removal_reason_status` varchar(10) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`removal_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `user_preferences` (
  `user_id` varchar(50) NOT NULL,
  `page_id` varchar(100) NOT NULL,
  `preferences` json DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`page_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `queue_sample_code_generation` (
  `id` int NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(255) NOT NULL,
  `test_type` varchar(32) NOT NULL,
  `access_type` varchar(32) NOT NULL,
  `sample_collection_date` date NOT NULL,
  `province_code` varchar(32) DEFAULT NULL,
  `sample_code_format` varchar(32) DEFAULT NULL,
  `prefix` varchar(32) DEFAULT NULL,
  `lab_id` int DEFAULT NULL,
  `created_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `processed` tinyint(1) DEFAULT '0',
  `processing_error` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Columns 5.2.9 adds, one ALTER per table so each costs one rebuild.
ALTER TABLE `form_cd4`
  ADD COLUMN `referring_lab_id` INT NULL DEFAULT NULL,
  ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL;

ALTER TABLE `form_eid`
  ADD COLUMN `second_dbs_requested_reason` VARCHAR(256) NULL DEFAULT NULL,
  ADD COLUMN `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `is_mother_alive` VARCHAR(50) NULL DEFAULT NULL,
  ADD COLUMN `child_age_in_weeks` INT NULL DEFAULT NULL,
  ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL;

ALTER TABLE `form_vl`
  ADD COLUMN `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `treatment_duration_precise` VARCHAR(50) NULL DEFAULT NULL,
  ADD COLUMN `last_cd4_result` VARCHAR(50) NULL DEFAULT NULL,
  ADD COLUMN `last_cd4_percentage` VARCHAR(50) NULL DEFAULT NULL,
  ADD COLUMN `last_cd8_result` VARCHAR(50) NULL DEFAULT NULL,
  ADD COLUMN `last_cd4_date` DATE NULL DEFAULT NULL,
  ADD COLUMN `last_cd8_date` VARCHAR(50) NULL DEFAULT NULL,
  ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL,
  ADD COLUMN `result_sent_to_external` TEXT NULL DEFAULT NULL,
  ADD COLUMN `result_sent_to_external_datetime` TEXT NULL DEFAULT NULL;

ALTER TABLE `form_covid19`
  ADD COLUMN `health_insurance_code` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL;

ALTER TABLE `lab_storage`
  ADD COLUMN `data_sync` INT NOT NULL DEFAULT '0';

ALTER TABLE `batch_details`
  ADD COLUMN `control_names` JSON NULL DEFAULT NULL,
  ADD COLUMN `created_by` VARCHAR(500) NULL DEFAULT NULL,
  ADD COLUMN `batch_attributes` JSON NULL DEFAULT NULL,
  ADD COLUMN `lab_assigned_batch_code` VARCHAR(64) NULL DEFAULT NULL,
  ADD COLUMN `printed_datetime` DATETIME NULL DEFAULT NULL;

ALTER TABLE `r_generic_test_reasons`
  ADD COLUMN `parent_reason` INT NULL DEFAULT NULL;

ALTER TABLE `instrument_controls`
  ADD COLUMN `updated_datetime` DATETIME NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `s_vlsm_instance`
  ADD COLUMN `last_vldash_sync` DATETIME NULL DEFAULT NULL,
  ADD COLUMN `last_lab_metadata_sync` DATETIME NULL DEFAULT NULL,
  ADD COLUMN `sts_token` VARCHAR(64) NULL DEFAULT NULL;

ALTER TABLE `lab_storage_history`
  ADD COLUMN `date_out` DATE NULL DEFAULT NULL,
  ADD COLUMN `comments` TEXT NULL DEFAULT NULL,
  ADD COLUMN `sample_removal_reason` INT NULL DEFAULT NULL;

ALTER TABLE `form_tb`
  ADD COLUMN `patient_weight` DECIMAL(5,2) NULL DEFAULT NULL,
  ADD COLUMN `is_displaced_population` VARCHAR(5) NULL DEFAULT NULL,
  ADD COLUMN `is_referred_by_community_actor` VARCHAR(5) NULL DEFAULT NULL,
  ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `is_patient_pregnant` VARCHAR(3) CHARACTER SET utf8mb4 NULL DEFAULT NULL,
  ADD COLUMN `is_patient_breastfeeding` VARCHAR(3) CHARACTER SET utf8mb4 NULL DEFAULT NULL;

ALTER TABLE `global_config`
  ADD COLUMN `instance_id` VARCHAR(50) NULL DEFAULT NULL;

ALTER TABLE `user_details`
  ADD COLUMN `user_attributes` JSON NULL DEFAULT NULL;

ALTER TABLE `form_generic`
  ADD COLUMN `is_encrypted` varchar(10) DEFAULT 'no',
  ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL;

ALTER TABLE `form_hepatitis`
  ADD COLUMN `lab_assigned_code` VARCHAR(32) NULL DEFAULT NULL,
  ADD COLUMN `rejection_on` DATE NULL DEFAULT NULL;

ALTER TABLE `facility_details`
  ADD COLUMN `sts_token` VARCHAR(64) NULL DEFAULT NULL,
  ADD COLUMN `sts_token_expiry` DATETIME NULL DEFAULT NULL;

UPDATE `system_config` SET `value` = '5.7.61' WHERE `system_config`.`name` = 'sc_version';
