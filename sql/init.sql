-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Apr 18, 2023 at 10:52 AM
-- Server version: 5.7.39
-- PHP Version: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `vlsm`
--
CREATE DATABASE IF NOT EXISTS `vlsm` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `vlsm`;

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL,
  `event_type` varchar(255) DEFAULT NULL,
  `action` longtext,
  `resource` varchar(255) DEFAULT NULL,
  `user_id` varchar(256) DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `ip_address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `activity_log`
--

TRUNCATE TABLE `activity_log`;
-- --------------------------------------------------------

--
-- Table structure for table `audit_form_covid19`
--

CREATE TABLE `audit_form_covid19` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(11) NOT NULL,
  `dt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `covid19_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` mediumtext,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` mediumtext,
  `sample_code` varchar(500) DEFAULT NULL,
  `sample_reordered` varchar(256) NOT NULL DEFAULT 'no',
  `external_sample_code` mediumtext,
  `test_number` int(11) DEFAULT NULL,
  `remote_sample` varchar(256) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` mediumtext,
  `remote_sample_code` varchar(256) DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
  `sample_condition` varchar(255) DEFAULT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `lab_tech_comments` mediumtext,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `source_of_alert` varchar(255) DEFAULT NULL,
  `source_of_alert_other` varchar(255) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `patient_id` varchar(255) DEFAULT NULL,
  `patient_name` text,
  `patient_surname` text,
  `patient_dob` date DEFAULT NULL,
  `patient_age` varchar(255) DEFAULT NULL,
  `patient_gender` varchar(255) DEFAULT NULL,
  `is_patient_pregnant` varchar(255) DEFAULT NULL,
  `patient_phone_number` text,
  `patient_email` varchar(256) DEFAULT NULL,
  `patient_nationality` varchar(255) DEFAULT NULL,
  `patient_passport_number` text,
  `patient_occupation` varchar(255) DEFAULT NULL,
  `does_patient_smoke` text,
  `patient_address` varchar(1000) DEFAULT NULL,
  `flight_airline` text,
  `flight_seat_no` text,
  `flight_arrival_datetime` datetime DEFAULT NULL,
  `flight_airport_of_departure` text,
  `flight_transit` text,
  `reason_of_visit` varchar(500) DEFAULT NULL,
  `is_sample_collected` varchar(255) DEFAULT NULL,
  `reason_for_covid19_test` int(11) DEFAULT NULL,
  `type_of_test_requested` text,
  `patient_province` text,
  `patient_district` text,
  `patient_zone` text,
  `patient_city` text,
  `specimen_type` varchar(255) DEFAULT NULL,
  `is_sample_post_mortem` varchar(255) DEFAULT NULL,
  `priority_status` varchar(255) DEFAULT NULL,
  `number_of_days_sick` int(11) DEFAULT NULL,
  `asymptomatic` varchar(50) DEFAULT NULL,
  `date_of_symptom_onset` date DEFAULT NULL,
  `suspected_case` varchar(255) DEFAULT NULL,
  `date_of_initial_consultation` date DEFAULT NULL,
  `medical_history` text,
  `recent_hospitalization` varchar(255) DEFAULT NULL,
  `patient_lives_with_children` varchar(255) DEFAULT NULL,
  `patient_cares_for_children` varchar(255) DEFAULT NULL,
  `fever_temp` varchar(255) DEFAULT NULL,
  `temperature_measurement_method` varchar(255) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `oxygen_saturation` double DEFAULT NULL,
  `close_contacts` mediumtext,
  `contact_with_confirmed_case` text,
  `has_recent_travel_history` text,
  `travel_country_names` text,
  `travel_return_date` date DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `samples_referred_datetime` datetime DEFAULT NULL,
  `referring_lab_id` int(11) DEFAULT NULL,
  `lab_manager` text,
  `testing_point` varchar(255) DEFAULT NULL,
  `lab_technician` text,
  `investigator_name` text,
  `investigator_phone` text,
  `investigator_email` text,
  `clinician_name` text,
  `clinician_phone` mediumtext,
  `clinician_email` mediumtext,
  `health_outcome` mediumtext,
  `health_outcome_date` date DEFAULT NULL,
  `lab_reception_person` mediumtext,
  `covid19_test_platform` mediumtext,
  `covid19_test_name` varchar(500) DEFAULT NULL,
  `result_status` int(11) DEFAULT NULL,
  `locked` varchar(256) DEFAULT 'no',
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `reason_for_sample_rejection` text,
  `rejection_on` date DEFAULT NULL,
  `result` text,
  `if_have_other_diseases` varchar(50) DEFAULT NULL,
  `other_diseases` mediumtext,
  `is_result_authorised` varchar(255) DEFAULT NULL,
  `authorized_by` mediumtext,
  `authorized_on` date DEFAULT NULL,
  `revised_by` text,
  `revised_on` datetime DEFAULT NULL,
  `reason_for_changing` mediumtext,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_reviewed_by` mediumtext,
  `result_approved_datetime` datetime DEFAULT NULL,
  `result_approved_by` mediumtext,
  `approver_comments` text,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `manual_result_entry` varchar(255) DEFAULT 'no',
  `import_machine_name` mediumtext,
  `import_machine_file_name` mediumtext,
  `result_printed_datetime` datetime DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  `request_created_by` text,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `sample_batch_id` int(11) DEFAULT NULL,
  `sample_package_id` varchar(256) DEFAULT NULL,
  `sample_package_code` mediumtext,
  `positive_test_manifest_id` int(11) DEFAULT NULL,
  `positive_test_manifest_code` mediumtext,
  `lot_number` mediumtext,
  `source_of_request` text,
  `source_data_dump` mediumtext,
  `result_sent_to_source` mediumtext,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `is_result_mail_sent` varchar(256) DEFAULT 'no',
  `app_sample_code` varchar(255) DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` mediumtext,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `audit_form_covid19`
--

TRUNCATE TABLE `audit_form_covid19`;
-- --------------------------------------------------------

--
-- Table structure for table `audit_form_eid`
--

CREATE TABLE `audit_form_eid` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(11) NOT NULL,
  `dt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `eid_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` varchar(255) DEFAULT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_code_key` int(11) NOT NULL,
  `sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code` varchar(500) DEFAULT NULL,
  `sample_reordered` varchar(256) NOT NULL DEFAULT 'no',
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(255) DEFAULT NULL,
  `remote_sample_code` varchar(500) DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `reason_for_sample_rejection` varchar(500) DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `mother_id` text,
  `mother_name` text,
  `mother_surname` text,
  `caretaker_contact_consent` text,
  `caretaker_phone_number` text,
  `caretaker_address` text,
  `mother_dob` date DEFAULT NULL,
  `mother_age_in_years` varchar(255) DEFAULT NULL,
  `mother_marital_status` varchar(255) DEFAULT NULL,
  `child_id` text,
  `child_name` text,
  `child_surname` text,
  `child_dob` date DEFAULT NULL,
  `child_age` varchar(255) DEFAULT NULL,
  `child_gender` varchar(255) DEFAULT NULL,
  `mother_hiv_status` varchar(255) DEFAULT NULL,
  `mode_of_delivery` varchar(255) DEFAULT NULL,
  `mother_treatment` varchar(255) DEFAULT NULL,
  `mother_regimen` text,
  `mother_treatment_other` varchar(1000) DEFAULT NULL,
  `mother_treatment_initiation_date` date DEFAULT NULL,
  `mother_cd4` varchar(255) DEFAULT NULL,
  `mother_cd4_test_date` date DEFAULT NULL,
  `mother_vl_result` varchar(255) DEFAULT NULL,
  `mother_vl_test_date` date DEFAULT NULL,
  `child_treatment` varchar(255) DEFAULT NULL,
  `child_treatment_other` varchar(1000) DEFAULT NULL,
  `is_infant_receiving_treatment` varchar(255) DEFAULT NULL,
  `has_infant_stopped_breastfeeding` varchar(255) DEFAULT NULL,
  `infant_on_pmtct_prophylaxis` text,
  `infant_on_ctx_prophylaxis` text,
  `age_breastfeeding_stopped_in_months` varchar(255) DEFAULT NULL,
  `choice_of_feeding` varchar(255) DEFAULT NULL,
  `is_cotrimoxazole_being_administered_to_the_infant` varchar(255) DEFAULT NULL,
  `sample_requestor_name` text,
  `sample_requestor_phone` varchar(255) DEFAULT NULL,
  `specimen_quality` varchar(255) DEFAULT NULL,
  `specimen_type` varchar(255) DEFAULT NULL,
  `reason_for_eid_test` int(11) DEFAULT NULL,
  `pcr_test_performed_before` varchar(255) DEFAULT NULL,
  `pcr_test_number` int(11) DEFAULT NULL,
  `last_pcr_id` varchar(255) DEFAULT NULL,
  `previous_pcr_result` varchar(255) DEFAULT NULL,
  `last_pcr_date` date DEFAULT NULL,
  `reason_for_pcr` varchar(500) DEFAULT NULL,
  `reason_for_repeat_pcr_other` text,
  `rapid_test_performed` varchar(255) DEFAULT NULL,
  `rapid_test_date` date DEFAULT NULL,
  `rapid_test_result` varchar(255) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `samples_referred_datetime` datetime DEFAULT NULL,
  `referring_lab_id` int(11) DEFAULT NULL,
  `lab_testing_point` text,
  `lab_technician` text,
  `lab_reception_person` text,
  `eid_test_platform` varchar(255) DEFAULT NULL,
  `result_status` int(11) DEFAULT NULL,
  `locked` varchar(256) DEFAULT 'no',
  `result` varchar(255) DEFAULT NULL,
  `reason_for_changing` varchar(256) DEFAULT NULL,
  `tested_by` text,
  `lab_tech_comments` mediumtext,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_reviewed_by` text,
  `result_approved_datetime` datetime DEFAULT NULL,
  `revised_by` text,
  `revised_on` datetime DEFAULT NULL,
  `result_approved_by` text,
  `approver_comments` text,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `app_sample_code` varchar(256) DEFAULT NULL,
  `manual_result_entry` varchar(255) DEFAULT 'no',
  `import_machine_name` text,
  `import_machine_file_name` text,
  `result_printed_datetime` datetime DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  `request_created_by` text,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` text,
  `sample_batch_id` int(11) DEFAULT NULL,
  `sample_package_id` varchar(255) DEFAULT NULL,
  `sample_package_code` text,
  `lot_number` text,
  `source_of_request` text,
  `source_data_dump` mediumtext,
  `result_sent_to_source` mediumtext,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `audit_form_eid`
--

TRUNCATE TABLE `audit_form_eid`;
-- --------------------------------------------------------

--
-- Table structure for table `audit_form_hepatitis`
--

CREATE TABLE `audit_form_hepatitis` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(11) NOT NULL,
  `dt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `hepatitis_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` varchar(255) DEFAULT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code` varchar(500) DEFAULT NULL,
  `sample_reordered` varchar(256) NOT NULL DEFAULT 'no',
  `external_sample_code` varchar(255) DEFAULT NULL,
  `hepatitis_test_type` text,
  `test_number` int(11) DEFAULT NULL,
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(255) DEFAULT NULL,
  `remote_sample_code` varchar(500) DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
  `sample_condition` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `patient_id` text,
  `patient_name` text,
  `patient_surname` text,
  `patient_dob` date DEFAULT NULL,
  `patient_age` varchar(255) DEFAULT NULL,
  `patient_gender` varchar(255) DEFAULT NULL,
  `patient_phone_number` text,
  `patient_province` text,
  `patient_district` text,
  `patient_city` text,
  `patient_nationality` text,
  `patient_occupation` text,
  `patient_address` text,
  `patient_marital_status` text,
  `social_category` text,
  `patient_insurance` text,
  `hbv_vaccination` text,
  `is_sample_collected` varchar(255) DEFAULT NULL,
  `reason_for_hepatitis_test` int(11) DEFAULT NULL,
  `type_of_test_requested` text,
  `reason_for_vl_test` text,
  `specimen_type` varchar(255) DEFAULT NULL,
  `priority_status` text,
  `lab_id` int(11) DEFAULT NULL,
  `samples_referred_datetime` datetime DEFAULT NULL,
  `referring_lab_id` int(11) DEFAULT NULL,
  `lab_technician` text,
  `testing_point` varchar(255) DEFAULT NULL,
  `lab_reception_person` varchar(255) DEFAULT NULL,
  `hepatitis_test_platform` varchar(255) DEFAULT NULL,
  `result_status` int(11) DEFAULT NULL,
  `locked` varchar(256) DEFAULT 'no',
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `reason_for_sample_rejection` varchar(500) DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `result` text,
  `tested_by` text,
  `lab_tech_comments` mediumtext,
  `hbsag_result` varchar(255) DEFAULT NULL,
  `anti_hcv_result` varchar(255) DEFAULT NULL,
  `hcv_vl_result` varchar(255) DEFAULT NULL,
  `hbv_vl_result` varchar(255) DEFAULT NULL,
  `hcv_vl_count` varchar(255) DEFAULT NULL,
  `hbv_vl_count` varchar(255) DEFAULT NULL,
  `vl_testing_site` varchar(255) DEFAULT NULL,
  `is_result_authorised` varchar(255) DEFAULT NULL,
  `authorized_by` text,
  `authorized_on` date DEFAULT NULL,
  `revised_by` text,
  `revised_on` datetime DEFAULT NULL,
  `reason_for_changing` longtext,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_reviewed_by` text,
  `result_approved_datetime` datetime DEFAULT NULL,
  `result_approved_by` text,
  `approver_comments` varchar(1000) DEFAULT NULL,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `manual_result_entry` varchar(255) DEFAULT 'no',
  `import_machine_name` text,
  `import_machine_file_name` varchar(255) DEFAULT NULL,
  `imported_date_time` datetime DEFAULT NULL,
  `result_printed_datetime` datetime DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  `request_created_by` text,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `sample_batch_id` int(11) DEFAULT NULL,
  `sample_package_id` varchar(255) DEFAULT NULL,
  `sample_package_code` text,
  `positive_test_manifest_id` int(11) DEFAULT NULL,
  `positive_test_manifest_code` varchar(255) DEFAULT NULL,
  `lot_number` varchar(255) DEFAULT NULL,
  `source_of_request` text,
  `source_data_dump` mediumtext,
  `result_sent_to_source` mediumtext,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `is_result_mail_sent` varchar(255) DEFAULT 'no',
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` text,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `audit_form_hepatitis`
--

TRUNCATE TABLE `audit_form_hepatitis`;
-- --------------------------------------------------------

--
-- Table structure for table `audit_form_tb`
--

CREATE TABLE `audit_form_tb` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(11) NOT NULL,
  `dt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tb_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` mediumtext,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_reordered` varchar(1000) NOT NULL DEFAULT 'no',
  `sample_code_key` int(11) NOT NULL,
  `sample_code_format` mediumtext,
  `sample_code` varchar(500) DEFAULT NULL,
  `remote_sample` varchar(1000) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` mediumtext,
  `remote_sample_code` varchar(500) DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `referring_unit` varchar(256) DEFAULT NULL,
  `other_referring_unit` mediumtext,
  `patient_id` mediumtext,
  `patient_name` mediumtext,
  `patient_surname` mediumtext,
  `patient_dob` date DEFAULT NULL,
  `patient_age` mediumtext,
  `patient_gender` mediumtext,
  `patient_address` mediumtext,
  `patient_phone` mediumtext,
  `patient_type` json DEFAULT NULL,
  `other_patient_type` mediumtext,
  `hiv_status` mediumtext,
  `previously_treated_for_tb` text,
  `tests_requested` json DEFAULT NULL,
  `number_of_sputum_samples` int(11) DEFAULT NULL,
  `first_sputum_samples_collection_date` date DEFAULT NULL,
  `sample_requestor_name` mediumtext,
  `sample_requestor_phone` mediumtext,
  `specimen_quality` mediumtext,
  `specimen_type` mediumtext,
  `other_specimen_type` mediumtext,
  `reason_for_tb_test` json DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `lab_technician` mediumtext,
  `lab_reception_person` mediumtext,
  `is_sample_rejected` varchar(1000) NOT NULL DEFAULT 'no',
  `reason_for_sample_rejection` mediumtext,
  `rejection_on` date DEFAULT NULL,
  `tb_test_platform` mediumtext,
  `result_status` int(11) DEFAULT NULL,
  `locked` varchar(256) DEFAULT 'no',
  `result` mediumtext,
  `xpert_mtb_result` mediumtext,
  `reason_for_changing` varchar(256) DEFAULT NULL,
  `tested_by` mediumtext,
  `result_date` datetime DEFAULT NULL,
  `lab_tech_comments` mediumtext,
  `result_reviewed_by` mediumtext,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_approved_by` mediumtext,
  `result_approved_datetime` datetime DEFAULT NULL,
  `revised_by` mediumtext,
  `revised_on` datetime DEFAULT NULL,
  `approver_comments` mediumtext,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `app_sample_code` varchar(256) DEFAULT NULL,
  `manual_result_entry` varchar(255) DEFAULT 'no',
  `import_machine_name` mediumtext,
  `import_machine_file_name` mediumtext,
  `result_printed_datetime` datetime DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  `request_created_by` mediumtext,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` mediumtext,
  `sample_batch_id` int(11) DEFAULT NULL,
  `sample_package_id` mediumtext,
  `sample_package_code` mediumtext,
  `source_of_request` varchar(50) DEFAULT NULL,
  `source_data_dump` mediumtext,
  `result_sent_to_source` mediumtext,
  `form_attributes` json DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `audit_form_tb`
--

TRUNCATE TABLE `audit_form_tb`;
-- --------------------------------------------------------

--
-- Table structure for table `audit_form_vl`
--

CREATE TABLE `audit_form_vl` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(11) NOT NULL,
  `dt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `vl_sample_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` varchar(255) NOT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `remote_sample_code` varchar(500) DEFAULT NULL,
  `external_sample_code` varchar(256) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` varchar(255) DEFAULT NULL,
  `facility_sample_id` varchar(255) DEFAULT NULL,
  `sample_batch_id` varchar(11) DEFAULT NULL,
  `sample_package_id` varchar(11) DEFAULT NULL,
  `sample_package_code` text,
  `sample_reordered` varchar(45) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code_title` varchar(45) NOT NULL DEFAULT 'auto',
  `sample_code` varchar(500) DEFAULT NULL,
  `test_urgency` varchar(255) DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `community_sample` varchar(256) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `patient_first_name` text,
  `patient_middle_name` text,
  `patient_last_name` text,
  `patient_responsible_person` text,
  `patient_nationality` int(11) DEFAULT NULL,
  `patient_province` text,
  `patient_district` text,
  `patient_group` text,
  `patient_art_no` varchar(256) DEFAULT NULL,
  `patient_dob` date DEFAULT NULL,
  `patient_below_five_years` varchar(255) DEFAULT NULL,
  `patient_gender` text,
  `patient_mobile_number` text,
  `patient_location` text,
  `patient_address` mediumtext,
  `patient_art_date` date DEFAULT NULL,
  `patient_receiving_therapy` text,
  `patient_drugs_transmission` text,
  `patient_tb` text,
  `patient_tb_yes` text,
  `sample_collection_date` datetime DEFAULT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_type` int(11) DEFAULT NULL,
  `is_patient_new` varchar(45) DEFAULT NULL,
  `treatment_initiation` text,
  `line_of_treatment` int(11) DEFAULT NULL,
  `line_of_treatment_failure_assessed` text,
  `line_of_treatment_ref_type` text,
  `current_regimen` text,
  `date_of_initiation_of_current_regimen` varchar(255) DEFAULT NULL,
  `is_patient_pregnant` text,
  `is_patient_breastfeeding` text,
  `patient_has_active_tb` text,
  `patient_active_tb_phase` text,
  `pregnancy_trimester` int(11) DEFAULT NULL,
  `arv_adherance_percentage` text,
  `is_adherance_poor` text,
  `consent_to_receive_sms` text,
  `number_of_enhanced_sessions` text,
  `last_vl_date_routine` date DEFAULT NULL,
  `last_vl_result_routine` text,
  `last_vl_sample_type_routine` int(11) DEFAULT NULL,
  `last_vl_date_failure_ac` date DEFAULT NULL,
  `last_vl_result_failure_ac` text,
  `last_vl_sample_type_failure_ac` int(11) DEFAULT NULL,
  `last_vl_date_failure` date DEFAULT NULL,
  `last_vl_result_failure` text,
  `last_vl_sample_type_failure` int(11) DEFAULT NULL,
  `last_vl_date_ecd` date DEFAULT NULL,
  `last_vl_result_ecd` text,
  `last_vl_date_cf` date DEFAULT NULL,
  `last_vl_result_cf` text,
  `last_vl_date_if` date DEFAULT NULL,
  `last_vl_result_if` text,
  `request_clinician_name` text,
  `test_requested_on` date DEFAULT NULL,
  `request_clinician_phone_number` varchar(255) DEFAULT NULL,
  `sample_testing_date` datetime DEFAULT NULL,
  `vl_focal_person` text,
  `vl_focal_person_phone_number` text,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `sample_rejection_facility` int(11) DEFAULT NULL,
  `reason_for_sample_rejection` int(11) DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `request_created_by` varchar(500) NOT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  `last_modified_by` text,
  `last_modified_datetime` datetime DEFAULT NULL,
  `patient_other_id` text,
  `patient_age_in_years` varchar(255) DEFAULT NULL,
  `patient_age_in_months` varchar(255) DEFAULT NULL,
  `treatment_initiated_date` date DEFAULT NULL,
  `treatment_duration` text,
  `treatment_indication` text,
  `patient_anc_no` varchar(255) DEFAULT NULL,
  `treatment_details` mediumtext,
  `sample_visit_type` varchar(45) DEFAULT NULL,
  `vl_sample_suspected_treatment_failure_at` text,
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
  `result_value_log` varchar(255) DEFAULT NULL,
  `result_value_absolute` varchar(255) DEFAULT NULL,
  `result_value_text` text,
  `result_value_absolute_decimal` varchar(255) DEFAULT NULL,
  `result` text,
  `approver_comments` mediumtext,
  `reason_for_vl_result_changes` mediumtext,
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
  `contact_complete_status` text,
  `last_viral_load_date` date DEFAULT NULL,
  `last_viral_load_result` text,
  `last_vl_result_in_log` text,
  `reason_for_vl_testing` text,
  `reason_for_vl_testing_other` text,
  `drug_substitution` text,
  `sample_collected_by` text,
  `facility_comments` mediumtext,
  `vl_test_platform` text,
  `result_value_hiv_detection` varchar(256) DEFAULT NULL,
  `cphl_vl_result` varchar(255) DEFAULT NULL,
  `import_machine_name` int(11) DEFAULT NULL,
  `facility_support_partner` text,
  `has_patient_changed_regimen` varchar(45) DEFAULT NULL,
  `reason_for_regimen_change` text,
  `regimen_change_date` date DEFAULT NULL,
  `plasma_conservation_temperature` float DEFAULT NULL,
  `plasma_conservation_duration` text,
  `physician_name` text,
  `date_test_ordered_by_physician` date DEFAULT NULL,
  `vl_test_number` text,
  `date_dispatched_from_clinic_to_lab` datetime DEFAULT NULL,
  `result_printed_datetime` datetime DEFAULT NULL,
  `result_sms_sent_datetime` datetime DEFAULT NULL,
  `is_request_mail_sent` varchar(500) NOT NULL DEFAULT 'no',
  `request_mail_datetime` datetime DEFAULT NULL,
  `is_result_mail_sent` varchar(500) NOT NULL DEFAULT 'no',
  `app_sample_code` varchar(256) DEFAULT NULL,
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
  `result_status` int(11) NOT NULL,
  `locked` varchar(256) DEFAULT 'no',
  `import_machine_file_name` text,
  `manual_result_entry` varchar(255) DEFAULT NULL,
  `source` varchar(500) DEFAULT 'manual',
  `ward` varchar(256) DEFAULT NULL,
  `art_cd_cells` varchar(256) DEFAULT NULL,
  `art_cd_date` date DEFAULT NULL,
  `who_clinical_stage` varchar(256) DEFAULT NULL,
  `reason_testing_png` mediumtext,
  `tech_name_png` text,
  `qc_tech_name` text,
  `qc_tech_sign` text,
  `qc_date` text,
  `whole_blood_ml` text,
  `whole_blood_vial` text,
  `plasma_ml` text,
  `plasma_vial` text,
  `plasma_process_time` text,
  `plasma_process_tech` text,
  `batch_quality` text,
  `sample_test_quality` text,
  `repeat_sample_collection` text,
  `failed_test_date` datetime DEFAULT NULL,
  `failed_test_tech` varchar(256) DEFAULT NULL,
  `failed_vl_result` varchar(256) DEFAULT NULL,
  `reason_for_failure` int(11) DEFAULT NULL,
  `failed_batch_quality` varchar(256) DEFAULT NULL,
  `failed_sample_test_quality` varchar(256) DEFAULT NULL,
  `failed_batch_id` varchar(256) DEFAULT NULL,
  `clinic_date` date DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `sample_to_transport` text,
  `requesting_professional_number` text,
  `requesting_category` text,
  `requesting_vl_service_sector` text,
  `requesting_facility_id` int(11) DEFAULT NULL,
  `requesting_person` text,
  `requesting_phone` text,
  `requesting_date` date DEFAULT NULL,
  `collection_site` varchar(255) DEFAULT NULL,
  `data_sync` varchar(10) NOT NULL DEFAULT '0',
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `recency_vl` varchar(500) NOT NULL DEFAULT 'no',
  `recency_sync` int(11) DEFAULT '0',
  `file_name` varchar(255) DEFAULT NULL,
  `result_coming_from` varchar(255) DEFAULT NULL,
  `consultation` text,
  `first_line` varchar(255) DEFAULT NULL,
  `second_line` varchar(255) DEFAULT NULL,
  `first_viral_load` varchar(255) DEFAULT NULL,
  `collection_type` varchar(255) DEFAULT NULL,
  `sample_processed` varchar(255) DEFAULT NULL,
  `vl_result_category` text,
  `vldash_sync` int(11) DEFAULT '0',
  `source_of_request` text,
  `source_data_dump` text,
  `result_sent_to_source` varchar(256) DEFAULT 'pending',
  `form_attributes` json DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `audit_form_vl`
--

TRUNCATE TABLE `audit_form_vl`;
-- --------------------------------------------------------

--
-- Table structure for table `batch_details`
--

CREATE TABLE `batch_details` (
  `batch_id` int(11) NOT NULL,
  `machine` int(11) NOT NULL,
  `batch_code` varchar(255) DEFAULT NULL,
  `batch_code_key` int(11) DEFAULT NULL,
  `test_type` varchar(255) DEFAULT NULL,
  `batch_status` varchar(255) NOT NULL DEFAULT 'completed',
  `sent_mail` varchar(100) NOT NULL DEFAULT 'no',
  `position_type` varchar(256) DEFAULT NULL,
  `label_order` longtext,
  `created_by` varchar(256) DEFAULT NULL,
  `request_created_datetime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `batch_details`
--

TRUNCATE TABLE `batch_details`;
-- --------------------------------------------------------

--
-- Table structure for table `covid19_imported_controls`
--

CREATE TABLE `covid19_imported_controls` (
  `control_id` int(11) NOT NULL,
  `control_code` varchar(255) NOT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `control_type` varchar(255) DEFAULT NULL,
  `lot_number` varchar(255) DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `reason_for_sample_rejection` varchar(255) DEFAULT NULL,
  `result` varchar(255) DEFAULT NULL,
  `approver_comments` varchar(255) DEFAULT NULL,
  `result_approved_by` varchar(255) DEFAULT NULL,
  `result_approved_datetime` datetime DEFAULT NULL,
  `result_reviewed_by` varchar(1000) DEFAULT NULL,
  `lab_tech_comments` mediumtext,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `vlsm_country_id` varchar(10) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `imported_date_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `covid19_imported_controls`
--

TRUNCATE TABLE `covid19_imported_controls`;
-- --------------------------------------------------------

--
-- Table structure for table `covid19_patient_comorbidities`
--

CREATE TABLE `covid19_patient_comorbidities` (
  `covid19_id` int(11) NOT NULL,
  `comorbidity_id` int(11) NOT NULL,
  `comorbidity_detected` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `covid19_patient_comorbidities`
--

TRUNCATE TABLE `covid19_patient_comorbidities`;
-- --------------------------------------------------------

--
-- Table structure for table `covid19_patient_symptoms`
--

CREATE TABLE `covid19_patient_symptoms` (
  `covid19_id` int(11) NOT NULL,
  `symptom_id` int(11) NOT NULL,
  `symptom_detected` varchar(255) NOT NULL,
  `symptom_details` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `covid19_patient_symptoms`
--

TRUNCATE TABLE `covid19_patient_symptoms`;
-- --------------------------------------------------------

--
-- Table structure for table `covid19_positive_confirmation_manifest`
--

CREATE TABLE `covid19_positive_confirmation_manifest` (
  `manifest_id` int(11) NOT NULL,
  `manifest_code` varchar(255) NOT NULL,
  `added_by` varchar(255) NOT NULL,
  `manifest_status` varchar(255) DEFAULT NULL,
  `module` varchar(255) DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `covid19_positive_confirmation_manifest`
--

TRUNCATE TABLE `covid19_positive_confirmation_manifest`;
-- --------------------------------------------------------

--
-- Table structure for table `covid19_reasons_for_testing`
--

CREATE TABLE `covid19_reasons_for_testing` (
  `covid19_id` int(11) NOT NULL,
  `reasons_id` int(11) NOT NULL,
  `reasons_detected` varchar(50) DEFAULT NULL,
  `reason_details` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `covid19_reasons_for_testing`
--

TRUNCATE TABLE `covid19_reasons_for_testing`;
-- --------------------------------------------------------

--
-- Table structure for table `covid19_tests`
--

CREATE TABLE `covid19_tests` (
  `test_id` int(11) NOT NULL,
  `covid19_id` int(11) NOT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `test_name` varchar(500) NOT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime NOT NULL,
  `testing_platform` varchar(255) DEFAULT NULL,
  `kit_lot_no` varchar(256) DEFAULT NULL,
  `kit_expiry_date` date DEFAULT NULL,
  `result` varchar(500) NOT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `covid19_tests`
--

TRUNCATE TABLE `covid19_tests`;
-- --------------------------------------------------------

--
-- Table structure for table `eid_imported_controls`
--

CREATE TABLE `eid_imported_controls` (
  `control_id` int(11) NOT NULL,
  `control_code` varchar(255) NOT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `control_type` varchar(255) DEFAULT NULL,
  `lot_number` varchar(255) DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `lab_tech_comments` mediumtext,
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
  `imported_date_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `eid_imported_controls`
--

TRUNCATE TABLE `eid_imported_controls`;
-- --------------------------------------------------------

--
-- Table structure for table `facility_details`
--

CREATE TABLE `facility_details` (
  `facility_id` int(11) NOT NULL,
  `facility_name` varchar(255) DEFAULT NULL,
  `facility_code` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `vlsm_instance_id` varchar(255) NOT NULL,
  `other_id` varchar(255) DEFAULT NULL,
  `facility_emails` varchar(255) DEFAULT NULL,
  `report_email` longtext,
  `contact_person` varchar(255) DEFAULT NULL,
  `facility_mobile_numbers` varchar(255) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `country` varchar(255) DEFAULT NULL,
  `facility_state_id` int(11) DEFAULT NULL,
  `facility_district_id` int(11) DEFAULT NULL,
  `facility_state` varchar(255) DEFAULT NULL,
  `facility_district` varchar(255) DEFAULT NULL,
  `facility_hub_name` varchar(255) DEFAULT NULL,
  `latitude` varchar(255) DEFAULT NULL,
  `longitude` varchar(255) DEFAULT NULL,
  `facility_type` int(11) DEFAULT NULL,
  `facility_attributes` json DEFAULT NULL,
  `testing_points` json DEFAULT NULL,
  `facility_logo` varchar(255) DEFAULT NULL,
  `header_text` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  `test_type` varchar(255) DEFAULT NULL,
  `report_format` longtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `facility_details`
--

TRUNCATE TABLE `facility_details`;
-- --------------------------------------------------------

--
-- Table structure for table `facility_type`
--

CREATE TABLE `facility_type` (
  `facility_type_id` int(11) NOT NULL,
  `facility_type_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `facility_type`
--

TRUNCATE TABLE `facility_type`;
--
-- Dumping data for table `facility_type`
--

INSERT INTO `facility_type` (`facility_type_id`, `facility_type_name`) VALUES
(1, 'Health Facility'),
(2, 'Testing Lab'),
(3, 'Collection Site');

-- --------------------------------------------------------

--
-- Table structure for table `failed_result_retest_tracker`
--

CREATE TABLE `failed_result_retest_tracker` (
  `frrt_id` int(11) NOT NULL,
  `test_type_pid` int(11) DEFAULT NULL,
  `test_type` varchar(256) DEFAULT NULL,
  `sample_code` varchar(256) DEFAULT NULL,
  `sample_data` mediumtext NOT NULL,
  `remote_sample_code` varchar(256) DEFAULT NULL,
  `batch_id` varchar(256) DEFAULT NULL,
  `facility_id` varchar(256) DEFAULT NULL,
  `result` varchar(256) DEFAULT NULL,
  `result_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `update_by` varchar(256) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `failed_result_retest_tracker`
--

TRUNCATE TABLE `failed_result_retest_tracker`;
-- --------------------------------------------------------

--
-- Table structure for table `form_covid19`
--

CREATE TABLE `form_covid19` (
  `covid19_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` mediumtext,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` mediumtext,
  `sample_code` varchar(500) DEFAULT NULL,
  `sample_reordered` varchar(256) NOT NULL DEFAULT 'no',
  `external_sample_code` mediumtext,
  `test_number` int(11) DEFAULT NULL,
  `remote_sample` varchar(256) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` mediumtext,
  `remote_sample_code` varchar(256) DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
  `sample_condition` varchar(255) DEFAULT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `lab_tech_comments` mediumtext,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `source_of_alert` varchar(255) DEFAULT NULL,
  `source_of_alert_other` varchar(255) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `patient_id` varchar(255) DEFAULT NULL,
  `patient_name` text,
  `patient_surname` text,
  `patient_dob` date DEFAULT NULL,
  `patient_age` varchar(255) DEFAULT NULL,
  `patient_gender` varchar(255) DEFAULT NULL,
  `is_patient_pregnant` varchar(255) DEFAULT NULL,
  `patient_phone_number` text,
  `patient_email` varchar(256) DEFAULT NULL,
  `patient_nationality` varchar(255) DEFAULT NULL,
  `patient_passport_number` text,
  `patient_occupation` varchar(255) DEFAULT NULL,
  `does_patient_smoke` text,
  `patient_address` varchar(1000) DEFAULT NULL,
  `flight_airline` text,
  `flight_seat_no` text,
  `flight_arrival_datetime` datetime DEFAULT NULL,
  `flight_airport_of_departure` text,
  `flight_transit` text,
  `reason_of_visit` varchar(500) DEFAULT NULL,
  `is_sample_collected` varchar(255) DEFAULT NULL,
  `reason_for_covid19_test` int(11) DEFAULT NULL,
  `type_of_test_requested` text,
  `patient_province` text,
  `patient_district` text,
  `patient_zone` text,
  `patient_city` text,
  `specimen_type` varchar(255) DEFAULT NULL,
  `is_sample_post_mortem` varchar(255) DEFAULT NULL,
  `priority_status` varchar(255) DEFAULT NULL,
  `number_of_days_sick` int(11) DEFAULT NULL,
  `asymptomatic` varchar(50) DEFAULT NULL,
  `date_of_symptom_onset` date DEFAULT NULL,
  `suspected_case` varchar(255) DEFAULT NULL,
  `date_of_initial_consultation` date DEFAULT NULL,
  `medical_history` text,
  `recent_hospitalization` varchar(255) DEFAULT NULL,
  `patient_lives_with_children` varchar(255) DEFAULT NULL,
  `patient_cares_for_children` varchar(255) DEFAULT NULL,
  `fever_temp` varchar(255) DEFAULT NULL,
  `temperature_measurement_method` varchar(255) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `oxygen_saturation` double DEFAULT NULL,
  `close_contacts` mediumtext,
  `contact_with_confirmed_case` text,
  `has_recent_travel_history` text,
  `travel_country_names` text,
  `travel_return_date` date DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `samples_referred_datetime` datetime DEFAULT NULL,
  `referring_lab_id` int(11) DEFAULT NULL,
  `lab_manager` text,
  `testing_point` varchar(255) DEFAULT NULL,
  `lab_technician` text,
  `investigator_name` text,
  `investigator_phone` text,
  `investigator_email` text,
  `clinician_name` text,
  `clinician_phone` mediumtext,
  `clinician_email` mediumtext,
  `health_outcome` mediumtext,
  `health_outcome_date` date DEFAULT NULL,
  `lab_reception_person` mediumtext,
  `covid19_test_platform` mediumtext,
  `covid19_test_name` varchar(500) DEFAULT NULL,
  `result_status` int(11) DEFAULT NULL,
  `locked` varchar(256) DEFAULT 'no',
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `reason_for_sample_rejection` int(11) DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `result` text,
  `if_have_other_diseases` varchar(50) DEFAULT NULL,
  `other_diseases` mediumtext,
  `is_result_authorised` varchar(255) DEFAULT NULL,
  `authorized_by` mediumtext,
  `authorized_on` date DEFAULT NULL,
  `revised_by` text,
  `revised_on` datetime DEFAULT NULL,
  `reason_for_changing` mediumtext,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_reviewed_by` mediumtext,
  `result_approved_datetime` datetime DEFAULT NULL,
  `result_approved_by` mediumtext,
  `approver_comments` text,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `manual_result_entry` varchar(255) DEFAULT 'no',
  `import_machine_name` mediumtext,
  `import_machine_file_name` mediumtext,
  `result_printed_datetime` datetime DEFAULT NULL,
  `request_created_datetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `request_created_by` text,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `sample_batch_id` int(11) DEFAULT NULL,
  `sample_package_id` varchar(256) DEFAULT NULL,
  `sample_package_code` mediumtext,
  `positive_test_manifest_id` int(11) DEFAULT NULL,
  `positive_test_manifest_code` mediumtext,
  `lot_number` mediumtext,
  `source_of_request` text,
  `source_data_dump` mediumtext,
  `result_sent_to_source` mediumtext,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `is_result_mail_sent` varchar(256) DEFAULT 'no',
  `app_sample_code` varchar(255) DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` mediumtext,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `form_covid19`
--

TRUNCATE TABLE `form_covid19`;
--
-- Triggers `form_covid19`
--
DELIMITER $$
CREATE TRIGGER `form_covid19_data__ai` AFTER INSERT ON `form_covid19` FOR EACH ROW INSERT INTO `audit_form_covid19` SELECT 'insert', NULL, NOW(), d.* 
    FROM `form_covid19` AS d WHERE d.covid19_id = NEW.covid19_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_covid19_data__au` AFTER UPDATE ON `form_covid19` FOR EACH ROW INSERT INTO `audit_form_covid19` SELECT 'update', NULL, NOW(), d.*
    FROM `form_covid19` AS d WHERE d.covid19_id = NEW.covid19_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_covid19_data__bd` BEFORE DELETE ON `form_covid19` FOR EACH ROW INSERT INTO `audit_form_covid19` SELECT 'delete', NULL, NOW(), d.* 
    FROM `form_covid19` AS d WHERE d.covid19_id = OLD.covid19_id
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `form_eid`
--

CREATE TABLE `form_eid` (
  `eid_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` varchar(255) DEFAULT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_code_key` int(11) NOT NULL,
  `sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code` varchar(500) DEFAULT NULL,
  `sample_reordered` varchar(256) NOT NULL DEFAULT 'no',
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(255) DEFAULT NULL,
  `remote_sample_code` varchar(500) DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `reason_for_sample_rejection` varchar(500) DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `mother_id` text,
  `mother_name` text,
  `mother_surname` text,
  `caretaker_contact_consent` text,
  `caretaker_phone_number` text,
  `caretaker_address` text,
  `mother_dob` date DEFAULT NULL,
  `mother_age_in_years` varchar(255) DEFAULT NULL,
  `mother_marital_status` varchar(255) DEFAULT NULL,
  `child_id` text,
  `child_name` text,
  `child_surname` text,
  `child_dob` date DEFAULT NULL,
  `child_age` varchar(255) DEFAULT NULL,
  `child_gender` varchar(255) DEFAULT NULL,
  `mother_hiv_status` varchar(255) DEFAULT NULL,
  `mode_of_delivery` varchar(255) DEFAULT NULL,
  `mother_treatment` varchar(255) DEFAULT NULL,
  `mother_regimen` text,
  `mother_treatment_other` varchar(1000) DEFAULT NULL,
  `mother_treatment_initiation_date` date DEFAULT NULL,
  `mother_cd4` varchar(255) DEFAULT NULL,
  `mother_cd4_test_date` date DEFAULT NULL,
  `mother_vl_result` varchar(255) DEFAULT NULL,
  `mother_vl_test_date` date DEFAULT NULL,
  `child_treatment` varchar(255) DEFAULT NULL,
  `child_treatment_other` varchar(1000) DEFAULT NULL,
  `is_infant_receiving_treatment` varchar(255) DEFAULT NULL,
  `has_infant_stopped_breastfeeding` varchar(255) DEFAULT NULL,
  `infant_on_pmtct_prophylaxis` text,
  `infant_on_ctx_prophylaxis` text,
  `age_breastfeeding_stopped_in_months` varchar(255) DEFAULT NULL,
  `choice_of_feeding` varchar(255) DEFAULT NULL,
  `is_cotrimoxazole_being_administered_to_the_infant` varchar(255) DEFAULT NULL,
  `sample_requestor_name` text,
  `sample_requestor_phone` varchar(255) DEFAULT NULL,
  `specimen_quality` varchar(255) DEFAULT NULL,
  `specimen_type` varchar(255) DEFAULT NULL,
  `reason_for_eid_test` int(11) DEFAULT NULL,
  `pcr_test_performed_before` varchar(255) DEFAULT NULL,
  `pcr_test_number` int(11) DEFAULT NULL,
  `last_pcr_id` varchar(255) DEFAULT NULL,
  `previous_pcr_result` varchar(255) DEFAULT NULL,
  `last_pcr_date` date DEFAULT NULL,
  `reason_for_pcr` varchar(500) DEFAULT NULL,
  `reason_for_repeat_pcr_other` text,
  `rapid_test_performed` varchar(255) DEFAULT NULL,
  `rapid_test_date` date DEFAULT NULL,
  `rapid_test_result` varchar(255) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `samples_referred_datetime` datetime DEFAULT NULL,
  `referring_lab_id` int(11) DEFAULT NULL,
  `lab_testing_point` text,
  `lab_technician` text,
  `lab_reception_person` text,
  `eid_test_platform` varchar(255) DEFAULT NULL,
  `result_status` int(11) DEFAULT NULL,
  `locked` varchar(256) DEFAULT 'no',
  `result` varchar(255) DEFAULT NULL,
  `reason_for_changing` varchar(256) DEFAULT NULL,
  `tested_by` text,
  `lab_tech_comments` mediumtext,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_reviewed_by` text,
  `result_approved_datetime` datetime DEFAULT NULL,
  `revised_by` text,
  `revised_on` datetime DEFAULT NULL,
  `result_approved_by` text,
  `approver_comments` text,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `app_sample_code` varchar(256) DEFAULT NULL,
  `manual_result_entry` varchar(255) DEFAULT 'no',
  `import_machine_name` text,
  `import_machine_file_name` text,
  `result_printed_datetime` datetime DEFAULT NULL,
  `request_created_datetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `request_created_by` text,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` text,
  `sample_batch_id` int(11) DEFAULT NULL,
  `sample_package_id` varchar(255) DEFAULT NULL,
  `sample_package_code` text,
  `lot_number` text,
  `source_of_request` text,
  `source_data_dump` mediumtext,
  `result_sent_to_source` mediumtext,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `form_eid`
--

TRUNCATE TABLE `form_eid`;
--
-- Triggers `form_eid`
--
DELIMITER $$
CREATE TRIGGER `form_eid_data__ai` AFTER INSERT ON `form_eid` FOR EACH ROW INSERT INTO `audit_form_eid` SELECT 'insert', NULL, NOW(), d.* 
    FROM `form_eid` AS d WHERE d.eid_id = NEW.eid_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_eid_data__au` AFTER UPDATE ON `form_eid` FOR EACH ROW INSERT INTO `audit_form_eid` SELECT 'update', NULL, NOW(), d.*
    FROM `form_eid` AS d WHERE d.eid_id = NEW.eid_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_eid_data__bd` BEFORE DELETE ON `form_eid` FOR EACH ROW INSERT INTO `audit_form_eid` SELECT 'delete', NULL, NOW(), d.* 
    FROM `form_eid` AS d WHERE d.eid_id = OLD.eid_id
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `form_hepatitis`
--

CREATE TABLE `form_hepatitis` (
  `hepatitis_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` varchar(255) DEFAULT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code` varchar(500) DEFAULT NULL,
  `sample_reordered` varchar(256) NOT NULL DEFAULT 'no',
  `external_sample_code` varchar(255) DEFAULT NULL,
  `hepatitis_test_type` text,
  `test_number` int(11) DEFAULT NULL,
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(255) DEFAULT NULL,
  `remote_sample_code` varchar(500) DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
  `sample_condition` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `patient_id` text,
  `patient_name` text,
  `patient_surname` text,
  `patient_dob` date DEFAULT NULL,
  `patient_age` varchar(255) DEFAULT NULL,
  `patient_gender` varchar(255) DEFAULT NULL,
  `patient_phone_number` text,
  `patient_province` text,
  `patient_district` text,
  `patient_city` text,
  `patient_nationality` text,
  `patient_occupation` text,
  `patient_address` text,
  `patient_marital_status` text,
  `social_category` text,
  `patient_insurance` text,
  `hbv_vaccination` text,
  `is_sample_collected` varchar(255) DEFAULT NULL,
  `reason_for_hepatitis_test` int(11) DEFAULT NULL,
  `type_of_test_requested` text,
  `reason_for_vl_test` text,
  `specimen_type` varchar(255) DEFAULT NULL,
  `priority_status` text,
  `lab_id` int(11) DEFAULT NULL,
  `samples_referred_datetime` datetime DEFAULT NULL,
  `referring_lab_id` int(11) DEFAULT NULL,
  `lab_technician` text,
  `testing_point` varchar(255) DEFAULT NULL,
  `lab_reception_person` varchar(255) DEFAULT NULL,
  `hepatitis_test_platform` varchar(255) DEFAULT NULL,
  `result_status` int(11) DEFAULT NULL,
  `locked` varchar(256) DEFAULT 'no',
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `reason_for_sample_rejection` varchar(500) DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `result` text,
  `tested_by` text,
  `lab_tech_comments` mediumtext,
  `hbsag_result` varchar(255) DEFAULT NULL,
  `anti_hcv_result` varchar(255) DEFAULT NULL,
  `hcv_vl_result` varchar(255) DEFAULT NULL,
  `hbv_vl_result` varchar(255) DEFAULT NULL,
  `hcv_vl_count` varchar(255) DEFAULT NULL,
  `hbv_vl_count` varchar(255) DEFAULT NULL,
  `vl_testing_site` varchar(255) DEFAULT NULL,
  `is_result_authorised` varchar(255) DEFAULT NULL,
  `authorized_by` text,
  `authorized_on` date DEFAULT NULL,
  `revised_by` text,
  `revised_on` datetime DEFAULT NULL,
  `reason_for_changing` longtext,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_reviewed_by` text,
  `result_approved_datetime` datetime DEFAULT NULL,
  `result_approved_by` text,
  `approver_comments` varchar(1000) DEFAULT NULL,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `manual_result_entry` varchar(255) DEFAULT 'no',
  `import_machine_name` text,
  `import_machine_file_name` varchar(255) DEFAULT NULL,
  `imported_date_time` datetime DEFAULT NULL,
  `result_printed_datetime` datetime DEFAULT NULL,
  `request_created_datetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `request_created_by` text,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `sample_batch_id` int(11) DEFAULT NULL,
  `sample_package_id` varchar(255) DEFAULT NULL,
  `sample_package_code` text,
  `positive_test_manifest_id` int(11) DEFAULT NULL,
  `positive_test_manifest_code` varchar(255) DEFAULT NULL,
  `lot_number` varchar(255) DEFAULT NULL,
  `source_of_request` text,
  `source_data_dump` mediumtext,
  `result_sent_to_source` mediumtext,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `is_result_mail_sent` varchar(255) DEFAULT 'no',
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` text,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `form_hepatitis`
--

TRUNCATE TABLE `form_hepatitis`;
--
-- Triggers `form_hepatitis`
--
DELIMITER $$
CREATE TRIGGER `form_hepatitis_data__ai` AFTER INSERT ON `form_hepatitis` FOR EACH ROW INSERT INTO `audit_form_hepatitis` SELECT 'insert', NULL, NOW(), d.* 
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = NEW.hepatitis_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_hepatitis_data__au` AFTER UPDATE ON `form_hepatitis` FOR EACH ROW INSERT INTO `audit_form_hepatitis` SELECT 'update', NULL, NOW(), d.*
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = NEW.hepatitis_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_hepatitis_data__bd` BEFORE DELETE ON `form_hepatitis` FOR EACH ROW INSERT INTO `audit_form_hepatitis` SELECT 'delete', NULL, NOW(), d.* 
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = OLD.hepatitis_id
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `form_tb`
--

CREATE TABLE `form_tb` (
  `tb_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` mediumtext,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_reordered` varchar(1000) NOT NULL DEFAULT 'no',
  `sample_code_key` int(11) NOT NULL,
  `sample_code_format` mediumtext,
  `sample_code` varchar(500) DEFAULT NULL,
  `remote_sample` varchar(1000) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` mediumtext,
  `remote_sample_code` varchar(500) DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `referring_unit` varchar(256) DEFAULT NULL,
  `other_referring_unit` mediumtext,
  `patient_id` mediumtext,
  `patient_name` mediumtext,
  `patient_surname` mediumtext,
  `patient_dob` date DEFAULT NULL,
  `patient_age` mediumtext,
  `patient_gender` mediumtext,
  `patient_address` mediumtext,
  `patient_phone` mediumtext,
  `patient_type` json DEFAULT NULL,
  `other_patient_type` mediumtext,
  `hiv_status` mediumtext,
  `previously_treated_for_tb` text,
  `tests_requested` json DEFAULT NULL,
  `number_of_sputum_samples` int(11) DEFAULT NULL,
  `first_sputum_samples_collection_date` date DEFAULT NULL,
  `sample_requestor_name` mediumtext,
  `sample_requestor_phone` mediumtext,
  `specimen_quality` mediumtext,
  `specimen_type` mediumtext,
  `other_specimen_type` mediumtext,
  `reason_for_tb_test` json DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `lab_technician` mediumtext,
  `lab_reception_person` mediumtext,
  `is_sample_rejected` varchar(1000) NOT NULL DEFAULT 'no',
  `reason_for_sample_rejection` mediumtext,
  `rejection_on` date DEFAULT NULL,
  `tb_test_platform` mediumtext,
  `result_status` int(11) DEFAULT NULL,
  `locked` varchar(256) DEFAULT 'no',
  `result` mediumtext,
  `xpert_mtb_result` mediumtext,
  `reason_for_changing` varchar(256) DEFAULT NULL,
  `tested_by` mediumtext,
  `result_date` datetime DEFAULT NULL,
  `lab_tech_comments` mediumtext,
  `result_reviewed_by` mediumtext,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `result_approved_by` mediumtext,
  `result_approved_datetime` datetime DEFAULT NULL,
  `revised_by` mediumtext,
  `revised_on` datetime DEFAULT NULL,
  `approver_comments` mediumtext,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `result_mail_datetime` datetime DEFAULT NULL,
  `app_sample_code` varchar(256) DEFAULT NULL,
  `manual_result_entry` varchar(255) DEFAULT 'no',
  `import_machine_name` mediumtext,
  `import_machine_file_name` mediumtext,
  `result_printed_datetime` datetime DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  `request_created_by` mediumtext,
  `sample_registered_at_lab` datetime DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` mediumtext,
  `sample_batch_id` int(11) DEFAULT NULL,
  `sample_package_id` int(11) DEFAULT NULL,
  `sample_package_code` mediumtext,
  `source_of_request` varchar(50) DEFAULT NULL,
  `source_data_dump` mediumtext,
  `result_sent_to_source` mediumtext,
  `form_attributes` json DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `form_tb`
--

TRUNCATE TABLE `form_tb`;
--
-- Triggers `form_tb`
--
DELIMITER $$
CREATE TRIGGER `form_tb_data__ai` AFTER INSERT ON `form_tb` FOR EACH ROW INSERT INTO `audit_form_tb` SELECT 'insert', NULL, NOW(), d.* 
    FROM `form_tb` AS d WHERE d.tb_id = NEW.tb_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_tb_data__au` AFTER UPDATE ON `form_tb` FOR EACH ROW INSERT INTO `audit_form_tb` SELECT 'update', NULL, NOW(), d.*
    FROM `form_tb` AS d WHERE d.tb_id = NEW.tb_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_tb_data__bd` BEFORE DELETE ON `form_tb` FOR EACH ROW INSERT INTO `audit_form_tb` SELECT 'delete', NULL, NOW(), d.* 
    FROM `form_tb` AS d WHERE d.tb_id = OLD.tb_id
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `form_vl`
--

CREATE TABLE `form_vl` (
  `vl_sample_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` varchar(255) NOT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `remote_sample_code` varchar(500) DEFAULT NULL,
  `external_sample_code` varchar(256) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` varchar(255) DEFAULT NULL,
  `facility_sample_id` varchar(255) DEFAULT NULL,
  `sample_batch_id` varchar(11) DEFAULT NULL,
  `sample_package_id` varchar(11) DEFAULT NULL,
  `sample_package_code` text,
  `sample_reordered` varchar(45) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code_title` varchar(45) NOT NULL DEFAULT 'auto',
  `sample_code` varchar(500) DEFAULT NULL,
  `test_urgency` varchar(255) DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `community_sample` varchar(256) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `patient_first_name` text,
  `patient_middle_name` text,
  `patient_last_name` text,
  `patient_responsible_person` text,
  `patient_nationality` int(11) DEFAULT NULL,
  `patient_province` text,
  `patient_district` text,
  `patient_group` text,
  `patient_art_no` varchar(256) DEFAULT NULL,
  `patient_dob` date DEFAULT NULL,
  `patient_below_five_years` varchar(255) DEFAULT NULL,
  `patient_gender` text,
  `patient_mobile_number` text,
  `patient_location` text,
  `patient_address` mediumtext,
  `patient_art_date` date DEFAULT NULL,
  `patient_receiving_therapy` text,
  `patient_drugs_transmission` text,
  `patient_tb` text,
  `patient_tb_yes` text,
  `sample_collection_date` datetime DEFAULT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_type` int(11) DEFAULT NULL,
  `is_patient_new` varchar(45) DEFAULT NULL,
  `treatment_initiation` text,
  `line_of_treatment` int(11) DEFAULT NULL,
  `line_of_treatment_failure_assessed` text,
  `line_of_treatment_ref_type` text,
  `current_regimen` text,
  `date_of_initiation_of_current_regimen` varchar(255) DEFAULT NULL,
  `is_patient_pregnant` text,
  `is_patient_breastfeeding` text,
  `patient_has_active_tb` text,
  `patient_active_tb_phase` text,
  `pregnancy_trimester` int(11) DEFAULT NULL,
  `arv_adherance_percentage` text,
  `is_adherance_poor` text,
  `consent_to_receive_sms` text,
  `number_of_enhanced_sessions` text,
  `last_vl_date_routine` date DEFAULT NULL,
  `last_vl_result_routine` text,
  `last_vl_sample_type_routine` int(11) DEFAULT NULL,
  `last_vl_date_failure_ac` date DEFAULT NULL,
  `last_vl_result_failure_ac` text,
  `last_vl_sample_type_failure_ac` int(11) DEFAULT NULL,
  `last_vl_date_failure` date DEFAULT NULL,
  `last_vl_result_failure` text,
  `last_vl_sample_type_failure` int(11) DEFAULT NULL,
  `last_vl_date_ecd` date DEFAULT NULL,
  `last_vl_result_ecd` text,
  `last_vl_date_cf` date DEFAULT NULL,
  `last_vl_result_cf` text,
  `last_vl_date_if` date DEFAULT NULL,
  `last_vl_result_if` text,
  `request_clinician_name` text,
  `test_requested_on` date DEFAULT NULL,
  `request_clinician_phone_number` varchar(255) DEFAULT NULL,
  `sample_testing_date` datetime DEFAULT NULL,
  `vl_focal_person` text,
  `vl_focal_person_phone_number` text,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` datetime DEFAULT NULL,
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
  `treatment_duration` text,
  `treatment_indication` text,
  `patient_anc_no` varchar(255) DEFAULT NULL,
  `treatment_details` mediumtext,
  `sample_visit_type` varchar(45) DEFAULT NULL,
  `vl_sample_suspected_treatment_failure_at` text,
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
  `result_value_log` varchar(255) DEFAULT NULL,
  `result_value_absolute` varchar(255) DEFAULT NULL,
  `result_value_text` text,
  `result_value_absolute_decimal` varchar(255) DEFAULT NULL,
  `result` text,
  `approver_comments` mediumtext,
  `reason_for_vl_result_changes` mediumtext,
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
  `contact_complete_status` text,
  `last_viral_load_date` date DEFAULT NULL,
  `last_viral_load_result` text,
  `last_vl_result_in_log` text,
  `reason_for_vl_testing` text,
  `reason_for_vl_testing_other` text,
  `drug_substitution` text,
  `sample_collected_by` text,
  `facility_comments` mediumtext,
  `vl_test_platform` text,
  `result_value_hiv_detection` varchar(256) DEFAULT NULL COMMENT '\r\n',
  `cphl_vl_result` varchar(255) DEFAULT NULL,
  `import_machine_name` int(11) DEFAULT NULL,
  `facility_support_partner` text,
  `has_patient_changed_regimen` varchar(45) DEFAULT NULL,
  `reason_for_regimen_change` text,
  `regimen_change_date` date DEFAULT NULL,
  `plasma_conservation_temperature` float DEFAULT NULL,
  `plasma_conservation_duration` text,
  `physician_name` text,
  `date_test_ordered_by_physician` date DEFAULT NULL,
  `vl_test_number` text,
  `date_dispatched_from_clinic_to_lab` datetime DEFAULT NULL,
  `result_printed_datetime` datetime DEFAULT NULL,
  `result_sms_sent_datetime` datetime DEFAULT NULL,
  `is_request_mail_sent` varchar(500) NOT NULL DEFAULT 'no',
  `request_mail_datetime` datetime DEFAULT NULL,
  `is_result_mail_sent` varchar(500) NOT NULL DEFAULT 'no',
  `app_sample_code` varchar(256) DEFAULT NULL,
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
  `result_status` int(11) NOT NULL,
  `locked` varchar(256) DEFAULT 'no',
  `import_machine_file_name` text,
  `manual_result_entry` varchar(255) DEFAULT NULL,
  `source` varchar(500) DEFAULT 'manual',
  `ward` varchar(256) DEFAULT NULL,
  `art_cd_cells` varchar(256) DEFAULT NULL,
  `art_cd_date` date DEFAULT NULL,
  `who_clinical_stage` varchar(256) DEFAULT NULL,
  `reason_testing_png` mediumtext,
  `tech_name_png` text,
  `qc_tech_name` text,
  `qc_tech_sign` text,
  `qc_date` text,
  `whole_blood_ml` text,
  `whole_blood_vial` text,
  `plasma_ml` text,
  `plasma_vial` text,
  `plasma_process_time` text,
  `plasma_process_tech` text,
  `batch_quality` text,
  `sample_test_quality` text,
  `repeat_sample_collection` text,
  `failed_test_date` datetime DEFAULT NULL,
  `failed_test_tech` varchar(256) DEFAULT NULL,
  `failed_vl_result` varchar(256) DEFAULT NULL,
  `reason_for_failure` int(11) DEFAULT NULL,
  `failed_batch_quality` varchar(256) DEFAULT NULL,
  `failed_sample_test_quality` varchar(256) DEFAULT NULL,
  `failed_batch_id` varchar(256) DEFAULT NULL,
  `clinic_date` date DEFAULT NULL,
  `report_date` date DEFAULT NULL,
  `sample_to_transport` text,
  `requesting_professional_number` text,
  `requesting_category` text,
  `requesting_vl_service_sector` text,
  `requesting_facility_id` int(11) DEFAULT NULL,
  `requesting_person` text,
  `requesting_phone` text,
  `requesting_date` date DEFAULT NULL,
  `collection_site` varchar(255) DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `recency_vl` varchar(500) NOT NULL DEFAULT 'no',
  `recency_sync` int(11) DEFAULT '0',
  `file_name` varchar(255) DEFAULT NULL,
  `result_coming_from` varchar(255) DEFAULT NULL,
  `consultation` text,
  `first_line` varchar(255) DEFAULT NULL,
  `second_line` varchar(255) DEFAULT NULL,
  `first_viral_load` varchar(255) DEFAULT NULL,
  `collection_type` varchar(255) DEFAULT NULL,
  `sample_processed` varchar(255) DEFAULT NULL,
  `vl_result_category` text,
  `vldash_sync` int(11) DEFAULT '0',
  `source_of_request` text,
  `source_data_dump` text,
  `result_sent_to_source` varchar(256) DEFAULT 'pending',
  `form_attributes` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `form_vl`
--

TRUNCATE TABLE `form_vl`;
--
-- Triggers `form_vl`
--
DELIMITER $$
CREATE TRIGGER `form_vl_data__ai` AFTER INSERT ON `form_vl` FOR EACH ROW INSERT INTO `audit_form_vl` SELECT 'insert', NULL, NOW(), d.* 
    FROM `form_vl` AS d WHERE d.vl_sample_id = NEW.vl_sample_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_vl_data__au` AFTER UPDATE ON `form_vl` FOR EACH ROW INSERT INTO `audit_form_vl` SELECT 'update', NULL, NOW(), d.*
    FROM `form_vl` AS d WHERE d.vl_sample_id = NEW.vl_sample_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_vl_data__bd` BEFORE DELETE ON `form_vl` FOR EACH ROW INSERT INTO `audit_form_vl` SELECT 'delete', NULL, NOW(), d.* 
    FROM `form_vl` AS d WHERE d.vl_sample_id = OLD.vl_sample_id
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `geographical_divisions`
--

CREATE TABLE `geographical_divisions` (
  `geo_id` int(11) NOT NULL,
  `geo_name` varchar(256) DEFAULT NULL,
  `geo_code` varchar(256) DEFAULT NULL,
  `geo_parent` varchar(256) NOT NULL DEFAULT '0',
  `geo_status` varchar(256) DEFAULT NULL,
  `created_by` varchar(256) DEFAULT NULL,
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `geographical_divisions`
--

TRUNCATE TABLE `geographical_divisions`;
-- --------------------------------------------------------

--
-- Table structure for table `global_config`
--

CREATE TABLE `global_config` (
  `display_name` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` longtext,
  `category` varchar(255) DEFAULT NULL,
  `remote_sync_needed` varchar(50) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` longtext,
  `status` varchar(255) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `global_config`
--

TRUNCATE TABLE `global_config`;
--
-- Dumping data for table `global_config`
--

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES
('App Menu Name', 'app_menu_name', 'VLSM', 'app', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Auto Approval', 'auto_approval', 'yes', 'general', 'no', '2020-10-06 15:04:45', NULL, 'inactive'),
('Barcode Format', 'barcode_format', 'C39+', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Barcode Printing', 'bar_code_printing', 'off', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('COVID-19 Auto Approve API Results', 'covid19_auto_approve_api_results', 'no', 'covid19', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Generate Patient Code', 'covid19_generate_patient_code', 'no', 'covid19', 'no', NULL, NULL, 'active'),
('Covid-19 Maximum Length', 'covid19_max_length', '', 'covid19', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Covid-19 Minimum Length', 'covid19_min_length', '', 'covid19', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Patient Code Prefix', 'covid19_patient_code_prefix', 'P', 'covid19', 'no', NULL, NULL, 'active'),
('Positive Confirmatory Tests Required By Central Lab', 'covid19_positive_confirmatory_tests_required_by_central_lab', 'no', 'covid19', 'no', '2020-10-06 15:04:46', NULL, 'active'),
('COVID-19 Report QR Code', 'covid19_report_qr_code', 'yes', NULL, 'no', '2021-07-09 17:32:23', NULL, 'active'),
('Report Type', 'covid19_report_type', 'rwanda', 'covid19', 'no', '2020-10-06 15:04:46', NULL, 'active'),
('Covid-19 Sample Code Format', 'covid19_sample_code', 'YY', 'covid19', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Covid-19 Sample Code Prefix', 'covid19_sample_code_prefix', 'C19', 'covid19', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Covid19 Sample Expiry Days', 'covid19_sample_expiry_after_days', '999', 'covid19', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Covid19 Sample Lock Expiry Days', 'covid19_sample_lock_after_days', '999', 'covid19', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Show Participant Name in Manifest', 'covid19_show_participant_name_in_manifest', 'yes', 'COVID19', 'no', NULL, NULL, 'active'),
('Covid19 Tests Table in Results Pdf', 'covid19_tests_table_in_results_pdf', 'no', 'covid19', 'no', '2020-10-06 15:04:46', NULL, 'active'),
('Data Sync Interval', 'data_sync_interval', '30', 'general', 'no', '2020-10-06 15:04:46', NULL, 'active'),
('Default Time Zone', 'default_time_zone', 'Africa/Juba', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Edit Profile', 'edit_profile', 'yes', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('EID Auto Approve API Results', 'eid_auto_approve_api_results', 'no', 'eid', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('EID Maximum Length', 'eid_max_length', '', 'eid', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('EID Minimum Length', 'eid_min_length', '', 'eid', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('EID Report QR Code', 'eid_report_qr_code', 'yes', 'EID', 'no', NULL, NULL, 'active'),
('EID Sample Code', 'eid_sample_code', 'MMYY', 'eid', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('EID Sample Code Prefix', 'eid_sample_code_prefix', 'EID', 'eid', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('EID Sample Expiry Days', 'eid_sample_expiry_after_days', '999', 'eid', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('EID Sample Lock Expiry Days', 'eid_sample_lock_after_days', '999', 'eid', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Show Participant Name in Manifest', 'eid_show_participant_name_in_manifest', 'yes', 'EID', 'no', NULL, NULL, 'active'),
('Enable QR Code Mechanism', 'enable_qr_mechanism', 'no', 'general', 'no', '2020-10-06 15:04:48', NULL, 'inactive'),
('Header', 'header', 'MINISTRY OF HEALTH', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Hepatitis Auto Approve API Results', 'hepatitis_auto_approve_api_results', 'no', 'hepatitis', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Hepatitis Report QR Code', 'hepatitis_report_qr_code', 'yes', NULL, NULL, NULL, NULL, 'active'),
('Hepatitis Sample Code Format', 'hepatitis_sample_code', 'MMYY', 'hepatitis', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Hepatitis Sample Code Prefix', 'hepatitis_sample_code_prefix', 'VLHEP', 'hepatitis', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Hepatitis Sample Expiry Days', 'hepatitis_sample_expiry_after_days', '999', 'hepatitis', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Hepatitis Sample Lock Expiry Days', 'hepatitis_sample_lock_after_days', '999', 'hepatitis', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Show Participant Name in Manifest', 'hepatitis_show_participant_name_in_manifest', 'yes', 'HEPATITIS', 'no', NULL, NULL, 'active'),
('Result PDF High Viral Load Message', 'h_vl_msg', '', 'vl', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Import Non matching Sample Results from Machine generated file', 'import_non_matching_sample', 'no', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Instance Type ', 'instance_type', 'Viral Load Lab', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Key', 'key', NULL, 'general', 'yes', NULL, NULL, 'active'),
('Lock Approved Covid-19 Samples', 'lock_approved_covid19_samples', 'no', 'covid19', 'no', NULL, NULL, 'active'),
('Lock Approved EID Samples', 'lock_approved_eid_samples', 'yes', 'eid', 'no', NULL, NULL, 'active'),
('Lock Approved TB Samples', 'lock_approved_tb_samples', 'no', 'tb', 'no', NULL, NULL, 'active'),
('Lock approved VL Samples', 'lock_approved_vl_samples', 'yes', 'vl', 'no', NULL, NULL, 'active'),
('Logo', 'logo', 'logoniawqm.png', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Low Viral Load (text results)', 'low_vl_text_results', 'Target Not Detected, TND, < 20, < 40', 'vl', 'yes', '2020-10-06 15:04:48', NULL, 'active'),
('Result PDF Low Viral Load Message', 'l_vl_msg', '', 'vl', 'yes', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Manager Email', 'manager_email', '', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Maximum Length', 'max_length', '', 'vl', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Minimum Length', 'min_length', '', 'vl', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Patient Name in Result PDF', 'patient_name_pdf', 'flname', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Result PDF Mandatory Fields', 'r_mandatory_fields', NULL, 'vl', 'yes', '2020-10-06 15:04:49', NULL, 'active'),
('Sample Code', 'sample_code', 'MMYY', 'vl', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Sample Code Prefix', 'sample_code_prefix', 'VL', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Sample Type', 'sample_type', 'enabled', NULL, 'no', NULL, NULL, 'active'),
('Patient ART No. Date', 'show_date', 'no', 'vl', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Do you want to show emoticons on the result pdf?', 'show_smiley', 'yes', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Support Email', 'support_email', '', 'general', 'no', NULL, '', 'active'),
('Sync Path', 'sync_path', '', 'general', 'no', '2020-10-06 15:04:50', NULL, 'inactive'),
('TB Auto Approve API Results', 'tb_auto_approve_api_results', 'no', 'tb', 'no', NULL, NULL, 'active'),
('TB Maximum Length', 'tb_max_length', NULL, 'tb', 'no', '2021-11-02 18:16:53', NULL, 'active'),
('TB Minimum Length', 'tb_min_length', NULL, 'tb', 'no', '2021-11-02 18:16:53', NULL, 'active'),
('TB Sample Code Format', 'tb_sample_code', 'MMYY', 'tb', 'no', '2021-11-02 17:48:32', NULL, 'active'),
('TB Sample Code Prefix', 'tb_sample_code_prefix', 'TB', 'tb', 'no', '2021-11-02 17:48:32', NULL, 'active'),
('TB Sample Expiry Days', 'tb_sample_expiry_after_days', '999', 'tb', 'no', NULL, NULL, 'active'),
('TB Sample Lock Expiry Days', 'tb_sample_lock_after_days', '999', 'tb', 'no', NULL, NULL, 'active'),
('Show Participant Name in Manifest', 'tb_show_participant_name_in_manifest', 'yes', 'TB', 'no', NULL, NULL, 'active'),
('Testing Status', 'testing_status', 'enabled', 'vl', 'no', '2020-10-06 15:04:50', NULL, 'active'),
('Same user can Review and Approve', 'user_review_approve', 'yes', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Viral Load Threshold Limit', 'viral_load_threshold_limit', '1000', 'vl', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Vldashboard Url', 'vldashboard_url', 'https://dashboard.nphl-moh.com.ss', 'general', 'yes', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('VL Auto Approve API Results', 'vl_auto_approve_api_results', 'no', 'vl', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Viral Load Form', 'vl_form', '3', 'general', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Interpret and Convert VL Results', 'vl_interpret_and_convert_results', 'no', 'VL', 'yes', NULL, NULL, 'active'),
('VL Monthly Target', 'vl_monthly_target', 'no', 'vl', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('VL Report QR Code', 'vl_report_qr_code', 'yes', 'vl', 'no', NULL, NULL, 'active'),
('VL Sample Expiry Days', 'vl_sample_expiry_after_days', '999', 'vl', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('VL Sample Lock Expiry Days', 'vl_sample_lock_after_days', '999', 'vl', 'no', '2022-10-06 13:07:46', 'h3svl3u4-4don-1qo9-hf27-d9ahg2fr73jq', 'active'),
('Show Participant Name in Manifest', 'vl_show_participant_name_in_manifest', 'yes', 'VL', 'no', NULL, NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `health_facilities`
--

CREATE TABLE `health_facilities` (
  `test_type` enum('vl','eid','covid19','hepatitis','tb') NOT NULL,
  `facility_id` int(11) NOT NULL,
  `updated_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `health_facilities`
--

TRUNCATE TABLE `health_facilities`;
-- --------------------------------------------------------

--
-- Table structure for table `hepatitis_patient_comorbidities`
--

CREATE TABLE `hepatitis_patient_comorbidities` (
  `hepatitis_id` int(11) NOT NULL,
  `comorbidity_id` int(11) NOT NULL,
  `comorbidity_detected` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `hepatitis_patient_comorbidities`
--

TRUNCATE TABLE `hepatitis_patient_comorbidities`;
-- --------------------------------------------------------

--
-- Table structure for table `hepatitis_risk_factors`
--

CREATE TABLE `hepatitis_risk_factors` (
  `hepatitis_id` int(11) NOT NULL,
  `riskfactors_id` int(11) NOT NULL,
  `riskfactors_detected` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `hepatitis_risk_factors`
--

TRUNCATE TABLE `hepatitis_risk_factors`;
-- --------------------------------------------------------

--
-- Table structure for table `hold_sample_import`
--

CREATE TABLE `hold_sample_import` (
  `hold_sample_id` int(11) NOT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `lab_name` varchar(255) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `lab_contact_person` varchar(255) DEFAULT NULL,
  `lab_phone_number` varchar(255) DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` varchar(255) DEFAULT NULL,
  `result_dispatched_datetime` varchar(255) DEFAULT NULL,
  `result_reviewed_datetime` varchar(255) DEFAULT NULL,
  `result_reviewed_by` varchar(255) DEFAULT NULL,
  `lab_tech_comments` mediumtext,
  `approver_comments` varchar(255) DEFAULT NULL,
  `lot_number` varchar(255) DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `sample_code` varchar(255) DEFAULT NULL,
  `batch_code` varchar(255) DEFAULT NULL,
  `sample_type` varchar(255) DEFAULT NULL,
  `order_number` varchar(255) DEFAULT NULL,
  `result_value_log` varchar(255) DEFAULT NULL,
  `result_value_absolute` varchar(255) DEFAULT NULL,
  `result_value_text` varchar(255) DEFAULT NULL,
  `result_value_absolute_decimal` varchar(255) DEFAULT NULL,
  `result` varchar(255) DEFAULT NULL,
  `sample_details` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `import_batch_tracking` int(11) DEFAULT NULL,
  `vl_test_platform` varchar(255) DEFAULT NULL,
  `import_machine_name` int(11) DEFAULT NULL,
  `import_machine_file_name` varchar(255) DEFAULT NULL,
  `manual_result_entry` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `hold_sample_import`
--

TRUNCATE TABLE `hold_sample_import`;
-- --------------------------------------------------------

--
-- Table structure for table `instruments`
--

CREATE TABLE `instruments` (
  `config_id` int(11) NOT NULL,
  `machine_name` varchar(255) DEFAULT NULL,
  `supported_tests` json DEFAULT NULL,
  `import_machine_file_name` varchar(255) DEFAULT NULL,
  `lower_limit` int(11) DEFAULT NULL,
  `higher_limit` int(11) DEFAULT NULL,
  `max_no_of_samples_in_a_batch` int(11) NOT NULL,
  `number_of_in_house_controls` int(11) DEFAULT NULL,
  `number_of_manufacturer_controls` int(11) DEFAULT NULL,
  `number_of_calibrators` int(11) DEFAULT NULL,
  `low_vl_result_text` longtext,
  `approved_by` json DEFAULT NULL,
  `reviewed_by` json DEFAULT NULL,
  `status` varchar(45) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `instruments`
--

TRUNCATE TABLE `instruments`;
--
-- Dumping data for table `instruments`
--

INSERT INTO `instruments` (`config_id`, `machine_name`, `supported_tests`, `import_machine_file_name`, `lower_limit`, `higher_limit`, `max_no_of_samples_in_a_batch`, `number_of_in_house_controls`, `number_of_manufacturer_controls`, `number_of_calibrators`, `low_vl_result_text`, `approved_by`, `reviewed_by`, `status`) VALUES
(1, 'Roche', NULL, 'roche.php', 20, 10000000, 21, 0, 3, 0, '', '{\"vl\": \"\", \"eid\": \"\", \"hepatitis\": \"\"}', '{\"vl\": \"\", \"eid\": \"\", \"hepatitis\": \"\"}', 'active'),
(2, 'Biomerieux', NULL, 'biomerieux.php', 0, 0, 10, 2, 3, 1, NULL, NULL, NULL, 'inactive'),
(3, 'Abbott', NULL, 'abbott-ssudan.php', 839, 10000000, 93, 0, 3, 0, '', NULL, NULL, 'active'),
(4, 'ABI7500', '[\"covid19\"]', 'abi7500.php', 0, 0, 21, NULL, NULL, NULL, '', NULL, NULL, 'active'),
(5, 'GeneXpert', '[\"vl\", \"eid\", \"covid19\"]', 'genexpert.php', 0, 0, 21, NULL, NULL, NULL, '', NULL, NULL, 'active'),
(6, 'BioRad PCR', '[\"covid19\"]', 'biorad-pcr.php', 0, 0, 21, NULL, NULL, NULL, '', NULL, NULL, 'active'),
(7, 'Rotor Gene', '[\"covid19\"]', 'rotor-gene.php', 0, 0, 21, NULL, NULL, NULL, '', NULL, NULL, 'active'),
(8, 'GeneXpert.', NULL, 'abbot-0r-genexpert.php', 0, 0, 21, NULL, NULL, NULL, '', NULL, NULL, 'inactive');

-- --------------------------------------------------------

--
-- Table structure for table `instrument_controls`
--

CREATE TABLE `instrument_controls` (
  `test_type` varchar(255) NOT NULL,
  `config_id` int(11) NOT NULL,
  `number_of_in_house_controls` int(11) DEFAULT NULL,
  `number_of_manufacturer_controls` int(11) DEFAULT NULL,
  `number_of_calibrators` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `instrument_controls`
--

TRUNCATE TABLE `instrument_controls`;
-- --------------------------------------------------------

--
-- Table structure for table `instrument_machines`
--

CREATE TABLE `instrument_machines` (
  `config_machine_id` int(11) NOT NULL,
  `config_id` int(11) NOT NULL,
  `config_machine_name` varchar(255) NOT NULL,
  `date_format` text,
  `file_name` varchar(256) DEFAULT NULL,
  `poc_device` varchar(255) DEFAULT NULL,
  `latitude` varchar(255) DEFAULT NULL,
  `longitude` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `instrument_machines`
--

TRUNCATE TABLE `instrument_machines`;
--
-- Dumping data for table `instrument_machines`
--

INSERT INTO `instrument_machines` (`config_machine_id`, `config_id`, `config_machine_name`, `date_format`, `file_name`, `poc_device`, `latitude`, `longitude`, `updated_datetime`) VALUES
(1, 1, 'Roche', NULL, 'roche.php', 'no', '', '', '2022-08-31 16:57:54'),
(2, 3, 'Abbott m2000', NULL, 'abbott-ssudan.php', NULL, NULL, NULL, NULL),
(3, 4, 'ABI7500', NULL, 'abi7500.php', NULL, NULL, NULL, NULL),
(4, 5, 'GeneXpert', NULL, 'genexpert.php', 'no', '', '', '2021-03-25 15:08:38'),
(5, 6, 'BioRad PCR', NULL, 'biorad-pcr.php', NULL, NULL, NULL, NULL),
(6, 7, 'Rotor Gene', NULL, 'rotor-gene.php', NULL, NULL, NULL, NULL),
(7, 8, 'GeneXpert', NULL, 'abbot-0r-genexpert.php', 'no', '', '', '2021-03-25 15:09:36');

-- --------------------------------------------------------

--
-- Table structure for table `lab_report_signatories`
--

CREATE TABLE `lab_report_signatories` (
  `signatory_id` int(11) NOT NULL,
  `name_of_signatory` varchar(255) DEFAULT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `test_types` varchar(255) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `display_order` varchar(50) DEFAULT NULL,
  `added_on` datetime DEFAULT NULL,
  `added_by` varchar(255) DEFAULT NULL,
  `signatory_status` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `lab_report_signatories`
--

TRUNCATE TABLE `lab_report_signatories`;
-- --------------------------------------------------------

--
-- Table structure for table `log_result_updates`
--

CREATE TABLE `log_result_updates` (
  `result_log_id` int(11) NOT NULL,
  `user_id` text,
  `vl_sample_id` int(11) NOT NULL,
  `test_type` varchar(244) DEFAULT NULL COMMENT 'vl, eid, covid19, hepatitis, tb',
  `result_method` varchar(256) DEFAULT NULL,
  `file_name` varchar(256) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `log_result_updates`
--

TRUNCATE TABLE `log_result_updates`;
-- --------------------------------------------------------

--
-- Table structure for table `move_samples`
--

CREATE TABLE `move_samples` (
  `move_sample_id` int(11) NOT NULL,
  `moved_from_lab_id` int(11) NOT NULL,
  `moved_to_lab_id` int(11) NOT NULL,
  `test_type` varchar(256) DEFAULT NULL,
  `moved_on` date DEFAULT NULL,
  `moved_by` varchar(255) DEFAULT NULL,
  `reason_for_moving` longtext,
  `move_approved_by` varchar(255) DEFAULT NULL,
  `list_request_created_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `move_samples`
--

TRUNCATE TABLE `move_samples`;
-- --------------------------------------------------------

--
-- Table structure for table `move_samples_map`
--

CREATE TABLE `move_samples_map` (
  `sample_map_id` int(11) NOT NULL,
  `move_sample_id` int(11) NOT NULL,
  `test_type_sample_id` int(11) DEFAULT NULL,
  `test_type` varchar(256) DEFAULT NULL,
  `move_sync_status` varchar(255) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `move_samples_map`
--

TRUNCATE TABLE `move_samples_map`;
-- --------------------------------------------------------

--
-- Table structure for table `other_config`
--

CREATE TABLE `other_config` (
  `type` varchar(45) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `value` longtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `other_config`
--

TRUNCATE TABLE `other_config`;
--
-- Dumping data for table `other_config`
--

INSERT INTO `other_config` (`type`, `display_name`, `name`, `value`) VALUES
('request', 'Email Id', 'rq_email', 'vlsm.southsudan@gmail.com'),
('request', 'Email Fields', 'rq_field', 'Sample ID,Province,Clinic Name,Sample Collection Date,Sample Received Date,Gender,Age in years,Age in months,Patient OI/ART Number,Result Of Last Viral Load,Specimen type,Sample Testing Date,Viral Load Result(copiesl/ml),Log Value,If no result,Rejection Reason,Reviewed By,Approved By,Status'),
('request', 'Password', 'rq_password', '#mko)(*&^%$123'),
('result', 'Email Id', 'rs_email', 'vlsm.southsudan@gmail.com'),
('result', 'Email Fields', 'rs_field', 'Sample ID,Clinic Name,Patient OI/ART Number,Viral Load Log,Lab Name,Sample Testing Date,Viral Load Result(copiesl/ml),Log Value,Rejection Reason,Reviewed By,Approved By,Laboratory Scientist Comments'),
('result', 'Password', 'rs_password', '#mko)(*&^%$123');

-- --------------------------------------------------------

--
-- Table structure for table `package_details`
--

CREATE TABLE `package_details` (
  `package_id` int(11) NOT NULL,
  `package_code` varchar(255) NOT NULL,
  `added_by` varchar(255) NOT NULL,
  `package_status` varchar(255) DEFAULT NULL,
  `module` varchar(255) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `number_of_samples` int(11) DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `package_details`
--

TRUNCATE TABLE `package_details`;
--
-- Dumping data for table `package_details`
--
-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `patient_id` int(11) NOT NULL,
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
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `patients`
--

TRUNCATE TABLE `patients`;
--
-- Dumping data for table `patients`
--
-- --------------------------------------------------------

--
-- Table structure for table `privileges`
--

CREATE TABLE `privileges` (
  `privilege_id` int(11) NOT NULL,
  `resource_id` varchar(255) NOT NULL,
  `privilege_name` varchar(255) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `privileges`
--

TRUNCATE TABLE `privileges`;
--
-- Dumping data for table `privileges`
--

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
(12, 'vl-requests', 'vlRequest.php', 'View'),
(13, 'vl-requests', 'addVlRequest.php', 'Add'),
(14, 'vl-requests', 'editVlRequest.php', 'Edit'),
(16, 'vl-batch', 'batchcode.php', 'Access'),
(17, 'vl-batch', 'addBatch.php', 'Add'),
(18, 'vl-batch', 'editBatch.php', 'Edit'),
(19, 'import-results', 'addImportResult.php', 'Import Results from File'),
(20, 'vl-results', 'vlPrintResult.php', 'Print Result PDF'),
(21, 'vl-results', 'vlTestResult.php', 'Enter Result Manually'),
(22, 'vl-reports', 'vl-sample-status.php', 'Sample Status Report'),
(23, 'vl-reports', 'vl-export-data.php', 'Export VL Data'),
(24, 'home', 'index.php', 'Access'),
(25, 'roles', 'roles.php', 'Access'),
(26, 'roles', 'editRole.php', 'Edit'),
(27, 'vl-requests', 'vlRequestMail.php', 'Email Test Request'),
(28, 'test-request-email-config', 'testRequestEmailConfig.php', 'Access'),
(29, 'vl-requests', 'sendRequestToMail.php', 'Send Request to Mail'),
(31, 'vl-results', 'vlResultApproval.php', 'Manage VL Result Status (Approve/Reject)'),
(33, 'vl-reports', 'highViralLoad.php', 'High VL Report'),
(34, 'vl-reports', 'addContactNotes.php', 'Contact Notes (High VL Reports)'),
(39, 'roles', 'addRole.php', 'Add'),
(40, 'vl-reports', 'vlTestResultStatus.php', 'Dashboard'),
(41, 'vl-requests', 'patientList.php', 'Export Patient List'),
(43, 'test-request-email-config', 'editTestRequestEmailConfig.php', 'Edit'),
(45, 'vl-requests', 'vlResultMail.php', 'Email Test Result'),
(46, 'vl-batch', 'editBatchControlsPosition.php', 'Edit Controls Position'),
(47, 'vl-batch', 'addBatchControlsPosition.php', 'Add Controls Position'),
(48, 'test-result-email-config', 'testResultEmailConfig.php', 'Access'),
(49, 'test-result-email-config', 'editTestResultEmailConfig.php', 'Edit'),
(50, 'vl-requests', 'vlRequestMailConfirm.php', 'Email Test Request Confirm'),
(51, 'vl-requests', 'vlResultMailConfirm.php', 'Email Test Result Confirm'),
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
(74, 'eid-requests', 'eid-add-request.php', 'Add'),
(75, 'eid-requests', 'eid-edit-request.php', 'Edit'),
(76, 'eid-requests', 'eid-requests.php', 'View'),
(77, 'eid-batches', 'eid-batches.php', 'View Batches'),
(78, 'eid-batches', 'eid-add-batch.php', 'Add Batch'),
(79, 'eid-batches', 'eid-edit-batch.php', 'Edit Batch'),
(80, 'eid-results', 'eid-manual-results.php', 'Enter Result Manually'),
(81, 'eid-results', 'eid-import-result.php', 'Import Result File'),
(84, 'eid-results', 'eid-result-status.php', 'Manage Result Status'),
(85, 'eid-results', 'eid-print-results.php', 'Print Results'),
(86, 'eid-management', 'eid-export-data.php', 'Export Data'),
(87, 'eid-management', 'eid-sample-rejection-report.php', 'Sample Rejection Report'),
(88, 'eid-management', 'eid-sample-status.php', 'Sample Status Report'),
(89, 'vl-requests', 'addSamplesFromManifest.php', 'Add Samples from Manifest'),
(91, 'eid-requests', 'addSamplesFromManifest.php', 'Add Samples from Manifest'),
(95, 'covid-19-requests', 'covid-19-add-request.php', 'Add'),
(96, 'covid-19-requests', 'covid-19-edit-request.php', 'Edit'),
(97, 'covid-19-requests', 'covid-19-requests.php', 'View'),
(98, 'covid-19-results', 'covid-19-result-status.php', 'Manage Result Status'),
(99, 'covid-19-results', 'covid-19-print-results.php', 'Print Results'),
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
(139, 'common-reference', 'province-details.php', 'Manage Common Reference Tables'),
(140, 'vl-requests', 'edit-locked-vl-samples', 'Edit Locked VL Samples'),
(141, 'eid-requests', 'edit-locked-eid-samples', 'Edit Locked EID Samples'),
(142, 'covid-19-requests', 'edit-locked-covid19-samples', 'Edit Locked Covid-19 Samples'),
(143, 'vl-reports', 'vlMonthlyThresholdReport.php', 'Monthly Threshold Report'),
(144, 'eid-management', 'eidMonthlyThresholdReport.php', 'Monthly Threshold Report'),
(145, 'covid-19-management', 'covid19MonthlyThresholdReport.php', 'Monthly Threshold Report'),
(147, 'hepatitis-results', 'hepatitis-manual-results.php', 'Enter Result Manually'),
(148, 'hepatitis-requests', 'hepatitis-print-results.php', 'Print Results'),
(149, 'hepatitis-requests', 'hepatitis-result-status.php', 'Manage Result Status'),
(150, 'hepatitis-reference', 'hepatitis-sample-type.php', 'Manage Hepatitis Reference'),
(151, 'vl-reports', 'vlSuppressedTargetReport.php', 'Suppressed Target report'),
(152, 'hepatitis-batches', 'hepatitis-batches.php', 'View Batches'),
(153, 'hepatitis-batches', 'hepatitis-add-batch.php', 'Add Batch'),
(154, 'hepatitis-batches', 'hepatitis-edit-batch.php', 'Edit Batch'),
(155, 'hepatitis-batches', 'hepatitis-add-batch-position.php', 'Add Batch Position'),
(156, 'hepatitis-results', 'hepatitis-edit-batch-position.php', 'Edit Batch Position'),
(157, 'hepatitis-results', 'add-samples-from-manifest.php', 'Add Samples from Manifest'),
(158, 'hepatitis-management', 'hepatitis-clinic-report.php', 'Hepatitis Clinic Reports'),
(159, 'hepatitis-management', 'hepatitis-testing-target-report.php', 'Hepatitis Testing Target Reports'),
(160, 'hepatitis-management', 'hepatitis-sample-rejection-report.php', 'Hepatitis Sample Rejection Reports'),
(161, 'hepatitis-management', 'hepatitis-sample-status.php', 'Hepatitis Sample Status Reports'),
(162, 'covid-19-requests', 'covid-19-dhis2.php', 'DHIS2'),
(163, 'covid-19-requests', 'covid-19-sync-request.php', 'Covid-19 Sync Request'),
(165, 'common-reference', 'geographical-divisions-details.php', 'Manage Geographical Divisions'),
(166, 'common-reference', 'add-geographical-divisions.php', 'Add Geographical Divisions'),
(167, 'common-reference', 'edit-geographical-divisions.php', 'Edit Geographical Divisions'),
(168, 'common-reference', 'sync-history.php', 'Sync History'),
(169, 'hepatitis-management', 'hepatitis-export-data.php', 'Hepatitis Export'),
(170, 'tb-requests', 'tb-requests.php', 'View'),
(171, 'tb-requests', 'tb-add-request.php', 'Add'),
(172, 'move-samples', 'move-samples.php', 'Access'),
(173, 'move-samples', 'select-samples-to-move.php', 'Add Move Samples'),
(174, 'tb-requests', 'tb-edit-request.php', 'Edit'),
(175, 'tb-results', 'tb-manual-results.php', 'Enter Result Manually'),
(176, 'tb-results', 'tb-print-results.php', 'Print Results'),
(177, 'tb-results', 'tb-result-status.php', 'Manage Result Status'),
(178, 'tb-reference', 'tb-sample-type.php', 'Manage Reference'),
(179, 'tb-results', 'tb-export-data.php', 'Export Data'),
(180, 'tb-batches', 'tb-batches.php', 'View Batches'),
(181, 'tb-batches', 'tb-add-batch.php', 'Add Batch'),
(182, 'tb-batches', 'tb-edit-batch.php', 'Edit Batch'),
(183, 'tb-batches', 'tb-add-batch-position.php', 'Add Batch Position'),
(184, 'tb-batches', 'tb-edit-batch-position.php', 'Edit Batch Position'),
(185, 'tb-requests', 'addSamplesFromManifest.php', 'Add Samples from Manifest'),
(186, 'tb-results', 'tb-sample-status.php', 'Sample Status Report'),
(187, 'tb-results', 'tb-sample-rejection-report.php', 'Sample Rejection Report'),
(188, 'tb-results', 'tb-clinic-report.php', 'TB Clinic Report'),
(211, 'common-reference', 'activity-log.php', 'User Activity Log'),
(213, 'common-reference', 'sources-of-requests.php', 'Sources of Requests'),
(216, 'vl-requests', 'export-vl-requests.php', 'Export VL Requests'),
(217, 'eid-requests', 'export-eid-requests.php', 'Export EID Requests'),
(218, 'covid-19-requests', 'export-covid19-requests.php', 'Export Covid-19 Requests '),
(219, 'hepatitis-requests', 'export-hepatitis-requests.php', 'Export Hepatitis Requests'),
(220, 'tb-requests', 'export-tb-requests.php', 'Export TB Requests'),
(221, 'common-reference', 'api-sync-history.php', 'API Sync History'),
(224, 'covid-19-results', 'covid-19-qc-data.php', 'Covid-19 QC Data'),
(225, 'covid-19-results', 'add-covid-19-qc-data.php', 'Add Covid-19 QC Data'),
(226, 'covid-19-results', 'edit-covid-19-qc-data.php', 'Edit Covid-19 QC Data'),
(227, 'common-reference', 'audit-trail.php', 'Audit Trail'),
(228, 'hepatitis-requests', 'hepatitis-requests.php', 'Access'),
(229, 'hepatitis-requests', 'hepatitis-add-request.php', 'Add'),
(230, 'hepatitis-requests', 'hepatitis-edit-request.php', 'Edit'),
(231, 'test-type', 'testType.php', 'Access'),
(232, 'test-type', 'addTestType.php', 'Add'),
(233, 'test-type', 'editTestType.php', 'Edit'),
(234, 'specimen-referral-manifest', 'move-manifest.php', 'Move Samples'),
(235, 'common-reference', 'sync-status.php', 'Sync Status'),
(236, 'vl-reference', 'vl-results.php', 'Manage VL Results');

-- --------------------------------------------------------

--
-- Table structure for table `province_details`
--

CREATE TABLE `province_details` (
  `province_id` int(11) NOT NULL,
  `province_name` varchar(255) DEFAULT NULL,
  `province_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `province_details`
--

TRUNCATE TABLE `province_details`;
--
-- Dumping data for table `province_details`
--

-- --------------------------------------------------------

--
-- Table structure for table `qc_covid19`
--

CREATE TABLE `qc_covid19` (
  `qc_id` int(11) NOT NULL,
  `unique_id` varchar(500) NOT NULL,
  `qc_code` varchar(256) NOT NULL,
  `qc_code_key` int(11) NOT NULL,
  `testkit` int(11) NOT NULL,
  `lot_no` varchar(256) NOT NULL,
  `expiry_date` date NOT NULL,
  `lab_id` int(11) NOT NULL,
  `testing_point` varchar(256) DEFAULT NULL,
  `qc_received_datetime` datetime DEFAULT NULL,
  `tested_by` text NOT NULL,
  `qc_tested_datetime` datetime NOT NULL,
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `qc_covid19`
--

TRUNCATE TABLE `qc_covid19`;
-- --------------------------------------------------------

--
-- Table structure for table `qc_covid19_tests`
--

CREATE TABLE `qc_covid19_tests` (
  `qc_test_id` int(11) NOT NULL,
  `qc_id` int(11) NOT NULL,
  `test_label` varchar(256) NOT NULL,
  `test_result` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `qc_covid19_tests`
--

TRUNCATE TABLE `qc_covid19_tests`;
-- --------------------------------------------------------

--
-- Table structure for table `report_to_mail`
--

CREATE TABLE `report_to_mail` (
  `report_mail_id` int(11) NOT NULL,
  `batch_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `to_mail` varchar(255) DEFAULT NULL,
  `encrypt` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `report_to_mail`
--

TRUNCATE TABLE `report_to_mail`;
-- --------------------------------------------------------

--
-- Table structure for table `resources`
--

CREATE TABLE `resources` (
  `resource_id` varchar(255) NOT NULL,
  `module` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `resources`
--

TRUNCATE TABLE `resources`;
--
-- Dumping data for table `resources`
--

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
('hepatitis-batches', 'hepatitis', 'Hepatitis Batch Management'),
('hepatitis-management', 'hepatitis', 'Hepatitis Reports'),
('hepatitis-reference', 'admin', 'Hepatitis Reference Management'),
('hepatitis-requests', 'hepatitis', 'Hepatitis Request Management'),
('hepatitis-results', 'hepatitis', 'Hepatitis Results Management'),
('home', 'common', 'Dashboard'),
('import-config', 'admin', 'Manage Import Config'),
('import-results', 'common', 'Import Results using file Import'),
('move-samples', 'common', 'Move Samples'),
('roles', 'admin', 'Manage Roles'),
('specimen-referral-manifest', 'vl', 'Manage Specimen Referral Manifests'),
('tb-batches', 'tb', 'TB Batch Management'),
('tb-management', 'tb', 'TB Reports'),
('tb-reference', 'admin', 'TB Reference'),
('tb-requests', 'tb', 'TB Request Management'),
('tb-results', 'tb', 'TB Result Management'),
('test-request-email-config', 'admin', 'Manage Test Request Email Config'),
('test-result-email-config', 'admin', 'Manage Test Result Email Config'),
('test-type', 'admin', 'Manage Test Type'),
('users', 'admin', 'Manage Users'),
('vl-batch', 'vl', 'Manage VL Batch'),
('vl-reference', 'admin', 'VL Reference Management'),
('vl-reports', 'vl', 'VL Reports'),
('vl-requests', 'vl', 'VL Requests'),
('vl-results', 'vl', 'VL Results');

-- --------------------------------------------------------

--
-- Table structure for table `result_import_stats`
--

CREATE TABLE `result_import_stats` (
  `id` int(11) NOT NULL,
  `imported_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `no_of_results_imported` int(11) DEFAULT NULL,
  `imported_by` varchar(1000) DEFAULT NULL,
  `import_mode` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `result_import_stats`
--

TRUNCATE TABLE `result_import_stats`;
-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL,
  `role_name` varchar(255) DEFAULT NULL,
  `role_code` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `access_type` varchar(256) DEFAULT NULL,
  `landing_page` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `roles`
--

TRUNCATE TABLE `roles`;
--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_code`, `status`, `access_type`, `landing_page`) VALUES
(1, 'Admin', 'AD', 'active', 'testing-lab', ''),
(2, 'Lab Technicians  ', 'LAB', 'active', 'testing-lab', '/vl/requests/addVlRequest.php'),
(3, 'Data Entry', 'DE', 'active', 'collection-site', ''),
(4, 'API User', 'API', 'active', NULL, NULL),
(5, 'COVID-19 RESULTS DOWNLOAD', 'C19RESULTS', 'active', 'collection-site', 'dashboard/index.php'),
(6, 'Antigen-RDT ', 'RDT ', 'active', 'testing-lab', '/vl/requests/addVlRequest.php'),
(7, 'Testing', 'TL', 'active', 'testing-lab', '/vl/requests/addVlRequest.php'),
(8, 'Field Officer Central', 'FO', 'active', 'collection-site', ''),
(9, 'POINT OF CARE TESTING ADVISOR', 'POCA', 'active', 'testing-lab', '');

-- --------------------------------------------------------

--
-- Table structure for table `roles_privileges_map`
--

CREATE TABLE `roles_privileges_map` (
  `map_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  `privilege_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `roles_privileges_map`
--

TRUNCATE TABLE `roles_privileges_map`;
--
-- Dumping data for table `roles_privileges_map`
--

INSERT INTO `roles_privileges_map` (`map_id`, `role_id`, `privilege_id`) VALUES
(2796, 4, 24),
(2797, 4, 95),
(2798, 4, 96),
(2799, 4, 97),
(3093, 5, 24),
(3094, 5, 99),
(4570, 7, 95),
(4571, 7, 163),
(4572, 7, 108),
(4573, 7, 97),
(4574, 7, 103),
(4575, 7, 104),
(4576, 7, 98),
(4577, 7, 99),
(4578, 7, 74),
(4579, 7, 76),
(4580, 7, 80),
(4581, 7, 84),
(4582, 7, 13),
(4583, 7, 12),
(4584, 7, 21),
(4585, 7, 20),
(4962, 6, 168),
(4963, 6, 105),
(4964, 6, 107),
(4965, 6, 95),
(4966, 6, 163),
(4967, 6, 97),
(4968, 6, 103),
(4969, 6, 98),
(4990, 3, 24),
(4991, 3, 122),
(4992, 3, 95),
(4993, 3, 96),
(4994, 3, 97),
(4995, 3, 99),
(4996, 3, 121),
(4997, 3, 86),
(4998, 3, 74),
(4999, 3, 91),
(5000, 3, 75),
(5001, 3, 141),
(5002, 3, 76),
(5003, 3, 18),
(5004, 3, 23),
(5005, 3, 70),
(5006, 3, 33),
(5007, 3, 13),
(5008, 3, 14),
(5009, 3, 12),
(5024, 8, 4),
(5025, 8, 65),
(5026, 8, 24),
(5027, 8, 122),
(5028, 8, 105),
(5029, 8, 145),
(5030, 8, 106),
(5031, 8, 107),
(5032, 8, 162),
(5033, 8, 97),
(5034, 8, 121),
(5035, 8, 86),
(5036, 8, 144),
(5037, 8, 87),
(5038, 8, 88),
(5039, 8, 217),
(5040, 8, 76),
(5041, 8, 34),
(5042, 8, 63),
(5043, 8, 40),
(5044, 8, 23),
(5045, 8, 70),
(5046, 8, 33),
(5047, 8, 62),
(5048, 8, 143),
(5049, 8, 59),
(5050, 8, 57),
(5051, 8, 22),
(5052, 8, 151),
(5053, 8, 56),
(5054, 8, 216),
(5055, 8, 12),
(5056, 2, 5),
(5057, 2, 28),
(5058, 2, 43),
(5059, 2, 48),
(5060, 2, 49),
(5061, 2, 24),
(5062, 2, 19),
(5063, 2, 78),
(5064, 2, 79),
(5065, 2, 77),
(5066, 2, 121),
(5067, 2, 86),
(5068, 2, 144),
(5069, 2, 87),
(5070, 2, 88),
(5071, 2, 74),
(5072, 2, 91),
(5073, 2, 75),
(5074, 2, 76),
(5075, 2, 80),
(5076, 2, 81),
(5077, 2, 84),
(5078, 2, 85),
(5079, 2, 16),
(5080, 2, 17),
(5081, 2, 47),
(5082, 2, 18),
(5083, 2, 46),
(5084, 2, 34),
(5085, 2, 40),
(5086, 2, 23),
(5087, 2, 33),
(5088, 2, 62),
(5089, 2, 59),
(5090, 2, 57),
(5091, 2, 22),
(5092, 2, 56),
(5093, 2, 13),
(5094, 2, 14),
(5095, 2, 140),
(5096, 2, 12),
(5097, 2, 21),
(5098, 2, 31),
(5099, 2, 20),
(5100, 9, 24),
(5101, 9, 121),
(5102, 9, 86),
(5103, 9, 87),
(5104, 9, 88),
(5105, 9, 217),
(5106, 9, 76),
(5107, 9, 84),
(5108, 9, 85),
(5109, 9, 34),
(5110, 9, 63),
(5111, 9, 40),
(5112, 9, 23),
(5113, 9, 70),
(5114, 9, 33),
(5115, 9, 22),
(5116, 9, 56),
(5117, 9, 12),
(5118, 9, 20),
(5721, 1, 166),
(5722, 1, 221),
(5723, 1, 227),
(5724, 1, 167),
(5725, 1, 139),
(5726, 1, 165),
(5727, 1, 213),
(5728, 1, 168),
(5729, 1, 211),
(5730, 1, 125),
(5731, 1, 128),
(5732, 1, 126),
(5733, 1, 129),
(5734, 1, 124),
(5735, 1, 123),
(5736, 1, 127),
(5737, 1, 131),
(5738, 1, 150),
(5739, 1, 4),
(5740, 1, 65),
(5741, 1, 5),
(5742, 1, 64),
(5743, 1, 6),
(5744, 1, 66),
(5745, 1, 7),
(5746, 1, 8),
(5747, 1, 9),
(5748, 1, 10),
(5749, 1, 11),
(5750, 1, 25),
(5751, 1, 39),
(5752, 1, 26),
(5753, 1, 28),
(5754, 1, 43),
(5755, 1, 48),
(5756, 1, 49),
(5757, 1, 1),
(5758, 1, 2),
(5759, 1, 3),
(5760, 1, 178),
(5761, 1, 130),
(5762, 1, 24),
(5763, 1, 19),
(5764, 1, 172),
(5765, 1, 173),
(5766, 1, 101),
(5767, 1, 112),
(5768, 1, 111),
(5769, 1, 102),
(5770, 1, 114),
(5771, 1, 113),
(5772, 1, 100),
(5773, 1, 122),
(5774, 1, 105),
(5775, 1, 145),
(5776, 1, 106),
(5777, 1, 107),
(5778, 1, 95),
(5779, 1, 109),
(5780, 1, 163),
(5781, 1, 162),
(5782, 1, 96),
(5783, 1, 142),
(5784, 1, 218),
(5785, 1, 108),
(5786, 1, 110),
(5787, 1, 97),
(5788, 1, 225),
(5789, 1, 224),
(5790, 1, 226),
(5791, 1, 103),
(5792, 1, 104),
(5793, 1, 98),
(5794, 1, 99),
(5795, 1, 78),
(5796, 1, 79),
(5797, 1, 77),
(5798, 1, 121),
(5799, 1, 86),
(5800, 1, 144),
(5801, 1, 87),
(5802, 1, 88),
(5803, 1, 74),
(5804, 1, 91),
(5805, 1, 75),
(5806, 1, 141),
(5807, 1, 217),
(5808, 1, 76),
(5809, 1, 80),
(5810, 1, 81),
(5811, 1, 84),
(5812, 1, 85),
(5813, 1, 153),
(5814, 1, 155),
(5815, 1, 154),
(5816, 1, 152),
(5817, 1, 158),
(5818, 1, 169),
(5819, 1, 160),
(5820, 1, 161),
(5821, 1, 159),
(5822, 1, 228),
(5823, 1, 229),
(5824, 1, 230),
(5825, 1, 219),
(5826, 1, 149),
(5827, 1, 148),
(5828, 1, 157),
(5829, 1, 156),
(5830, 1, 147),
(5831, 1, 69),
(5832, 1, 67),
(5833, 1, 68),
(5834, 1, 16),
(5835, 1, 17),
(5836, 1, 47),
(5837, 1, 18),
(5838, 1, 46),
(5839, 1, 34),
(5840, 1, 63),
(5841, 1, 40),
(5842, 1, 23),
(5843, 1, 70),
(5844, 1, 33),
(5845, 1, 62),
(5846, 1, 143),
(5847, 1, 59),
(5848, 1, 57),
(5849, 1, 22),
(5850, 1, 151),
(5851, 1, 56),
(5852, 1, 13),
(5853, 1, 89),
(5854, 1, 14),
(5855, 1, 140),
(5856, 1, 27),
(5857, 1, 50),
(5858, 1, 45),
(5859, 1, 51),
(5860, 1, 41),
(5861, 1, 216),
(5862, 1, 29),
(5863, 1, 12),
(5864, 1, 21),
(5865, 1, 31),
(5866, 1, 20);

-- --------------------------------------------------------

--
-- Table structure for table `r_countries`
--

CREATE TABLE `r_countries` (
  `id` int(10) UNSIGNED NOT NULL,
  `iso_name` varchar(255) NOT NULL,
  `iso2` varchar(2) NOT NULL,
  `iso3` varchar(3) NOT NULL,
  `numeric_code` smallint(6) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_countries`
--

TRUNCATE TABLE `r_countries`;
--
-- Dumping data for table `r_countries`
--

INSERT INTO `r_countries` (`id`, `iso_name`, `iso2`, `iso3`, `numeric_code`) VALUES
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
(55, 'Cote d\'Ivoire', 'CI', 'CIV', 384),
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
(118, 'Korea, Democratic People\'s Republic of', 'KP', 'PRK', 408),
(119, 'Korea, Republic of', 'KR', 'KOR', 410),
(120, 'Kuwait', 'KW', 'KWT', 414),
(121, 'Kyrgyzstan', 'KG', 'KGZ', 417),
(122, 'Lao People\'s Democratic Republic', 'LA', 'LAO', 418),
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

-- --------------------------------------------------------

--
-- Table structure for table `r_covid19_comorbidities`
--

CREATE TABLE `r_covid19_comorbidities` (
  `comorbidity_id` int(11) NOT NULL,
  `comorbidity_name` varchar(255) DEFAULT NULL,
  `comorbidity_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_covid19_comorbidities`
--

TRUNCATE TABLE `r_covid19_comorbidities`;
-- --------------------------------------------------------

--
-- Table structure for table `r_covid19_qc_testkits`
--

CREATE TABLE `r_covid19_qc_testkits` (
  `testkit_id` int(11) NOT NULL,
  `testkit_name` varchar(256) DEFAULT NULL,
  `no_of_tests` int(11) DEFAULT NULL,
  `labels_and_expected_results` json DEFAULT NULL,
  `status` varchar(256) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_covid19_qc_testkits`
--

TRUNCATE TABLE `r_covid19_qc_testkits`;
--
-- Dumping data for table `r_covid19_qc_testkits`
--

INSERT INTO `r_covid19_qc_testkits` (`testkit_id`, `testkit_name`, `no_of_tests`, `labels_and_expected_results`, `status`, `updated_datetime`) VALUES
(1, 'Abbott Panbio Ag RDT Test', NULL, '{\"label\": [\"QC Positive\", \"QC Negative\"], \"expected\": [\"positive\", \"negative\"]}', 'active', '2022-04-06 15:26:32'),
(2, 'SD-Biosensor  Ag RDT Test', NULL, '{\"label\": [\"QC Retest\", \"QC Retest\"], \"expected\": [\"positive\", \"negative\"]}', 'active', '2022-04-06 15:24:19');

-- --------------------------------------------------------

--
-- Table structure for table `r_covid19_results`
--

CREATE TABLE `r_covid19_results` (
  `result_id` varchar(255) NOT NULL,
  `result` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_covid19_results`
--

TRUNCATE TABLE `r_covid19_results`;
--
-- Dumping data for table `r_covid19_results`
--

INSERT INTO `r_covid19_results` (`result_id`, `result`, `status`, `updated_datetime`, `data_sync`) VALUES
('indeterminate', 'Indeterminate', 'inactive', '2022-04-05 09:49:10', 1),
('Invalid', 'Invalid', 'active', '2022-04-05 09:49:26', 0),
('negative', 'Negative', 'active', '2020-12-01 14:30:36', 1),
('positive', 'Positive', 'active', '2020-12-01 14:30:36', 1);

-- --------------------------------------------------------

--
-- Table structure for table `r_covid19_sample_rejection_reasons`
--

CREATE TABLE `r_covid19_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_covid19_sample_rejection_reasons`
--

TRUNCATE TABLE `r_covid19_sample_rejection_reasons`;
--
-- Dumping data for table `r_covid19_sample_rejection_reasons`
--

INSERT INTO `r_covid19_sample_rejection_reasons` (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`, `rejection_reason_status`, `rejection_reason_code`, `updated_datetime`, `data_sync`) VALUES
(1, 'Wrong specimen bottle', 'general', 'active', 'WSB', '2020-12-01 14:30:36', 1),
(2, 'Mis-match between specimen and request form', 'general', 'active', 'MMBSRF', '2020-12-01 14:30:36', 1),
(3, 'Poorly collected specimen', 'general', 'active', 'PCS', '2020-12-01 14:30:36', 1),
(4, 'Incomplete request form', 'general', 'active', 'IRF', '2020-12-01 14:30:36', 1),
(5, 'Specimen collected in expired container', 'general', 'active', 'EXP', '2020-12-01 14:30:36', 1),
(6, 'Poorly stored specimen', 'general', 'active', 'POORS', '2020-12-01 14:30:36', 1),
(7, 'Missing information on request form - Sex', 'general', 'active', 'Gen_MIRS', '2020-12-01 14:30:36', 1),
(8, 'Missing information on request form - Sample Collection Date', 'general', 'active', 'Gen_MIRD', '2020-12-01 14:30:36', 1),
(9, 'Missing information on request form - ART No', 'general', 'active', 'Gen_MIAN', '2020-12-01 14:30:36', 1),
(10, 'Inappropriate specimen packing', 'general', 'active', 'Gen_ISPK', '2020-12-01 14:30:36', 1),
(11, 'Inappropriate specimen for test request', 'general', 'active', 'Gen_ISTR', '2020-12-01 14:30:36', 1),
(12, 'Form received without Sample', 'general', 'active', 'Gen_NoSample', '2020-12-01 14:30:36', 1),
(13, 'VL Machine Flag', 'testing', 'active', 'FLG_', '2020-12-01 14:30:36', 1),
(14, 'CNTRL_FAIL', 'testing', 'active', 'FLG_AL00', '2020-12-01 14:30:36', 1),
(15, 'SYS_ERROR', 'testing', 'active', 'FLG_TM00', '2020-12-01 14:30:36', 1),
(16, 'A/D_ABORT', 'testing', 'active', 'FLG_TM17', '2020-12-01 14:30:36', 1),
(17, 'KIT_EXPIRY', 'testing', 'active', 'FLG_TMAP', '2020-12-01 14:30:36', 1),
(18, 'RUN_EXPIRY', 'testing', 'active', 'FLG_TM19', '2020-12-01 14:30:36', 1),
(19, 'DATA_ERROR', 'testing', 'active', 'FLG_TM20', '2020-12-01 14:30:36', 1),
(20, 'NC_INVALID', 'testing', 'active', 'FLG_TM24', '2020-12-01 14:30:36', 1),
(21, 'LPCINVALID', 'testing', 'active', 'FLG_TM25', '2020-12-01 14:30:36', 1),
(22, 'MPCINVALID', 'testing', 'active', 'FLG_TM26', '2020-12-01 14:30:36', 1),
(23, 'HPCINVALID', 'testing', 'active', 'FLG_TM27', '2020-12-01 14:30:36', 1),
(24, 'S_INVALID', 'testing', 'active', 'FLG_TM29', '2020-12-01 14:30:36', 1),
(25, 'MATH_ERROR', 'testing', 'active', 'FLG_TM31', '2020-12-01 14:30:36', 1),
(26, 'PRECHECK', 'testing', 'active', 'FLG_TM44 ', '2020-12-01 14:30:36', 1),
(27, 'QS_INVALID', 'testing', 'active', 'FLG_TM50', '2020-12-01 14:30:36', 1),
(28, 'POSTCHECK', 'testing', 'active', 'FLG_TM51', '2020-12-01 14:30:36', 1),
(29, 'REAG_ERROR', 'testing', 'active', 'FLG_AP02 ', '2020-12-01 14:30:36', 1),
(30, 'NO_SAMPLE', 'testing', 'active', 'FLG_AP12', '2020-12-01 14:30:36', 1),
(31, 'DISP_ERROR', 'testing', 'active', 'FLG_AP13 ', '2020-12-01 14:30:36', 1),
(32, 'TEMP_RANGE', 'testing', 'active', 'FLG_AP19 ', '2020-12-01 14:30:36', 1),
(33, 'PREP_ABORT', 'testing', 'active', 'FLG_AP24', '2020-12-01 14:30:36', 1),
(34, 'SAMPLECLOT', 'testing', 'active', 'FLG_AP25', '2020-12-01 14:30:36', 1);

-- --------------------------------------------------------

--
-- Table structure for table `r_covid19_sample_type`
--

CREATE TABLE `r_covid19_sample_type` (
  `sample_id` int(11) NOT NULL,
  `sample_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_covid19_sample_type`
--

TRUNCATE TABLE `r_covid19_sample_type`;
--
-- Dumping data for table `r_covid19_sample_type`
--

INSERT INTO `r_covid19_sample_type` (`sample_id`, `sample_name`, `status`, `updated_datetime`, `data_sync`) VALUES
(1, 'Oropharyngeal swab', 'active', '2020-12-01 14:30:36', 1),
(2, 'Nasopharyngeal  swab', 'active', '2020-12-01 14:30:36', 1),
(3, 'Serum', 'active', '2020-12-01 14:30:36', 1),
(4, 'Oraphargeal/Nasophargeal swab', 'active', '2022-03-11 14:51:20', 0),
(5, ' Nasal swab', 'active', '2022-03-11 14:51:54', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_covid19_symptoms`
--

CREATE TABLE `r_covid19_symptoms` (
  `symptom_id` int(11) NOT NULL,
  `symptom_name` varchar(255) DEFAULT NULL,
  `parent_symptom` int(11) DEFAULT NULL,
  `symptom_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_covid19_symptoms`
--

TRUNCATE TABLE `r_covid19_symptoms`;
--
-- Dumping data for table `r_covid19_symptoms`
--

INSERT INTO `r_covid19_symptoms` (`symptom_id`, `symptom_name`, `parent_symptom`, `symptom_status`, `updated_datetime`) VALUES
(1, 'Fever', 0, 'active', '2021-12-01 16:09:13'),
(2, 'Cough', 0, 'active', '2021-12-01 16:09:25'),
(3, 'Tiredness', 0, 'active', '2021-12-01 16:09:36'),
(4, 'Loss of taste or smell', 0, 'active', '2021-12-01 16:09:49');

-- --------------------------------------------------------

--
-- Table structure for table `r_covid19_test_reasons`
--

CREATE TABLE `r_covid19_test_reasons` (
  `test_reason_id` int(11) NOT NULL,
  `test_reason_name` varchar(255) DEFAULT NULL,
  `parent_reason` int(11) DEFAULT NULL,
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_covid19_test_reasons`
--

TRUNCATE TABLE `r_covid19_test_reasons`;
--
-- Dumping data for table `r_covid19_test_reasons`
--

INSERT INTO `r_covid19_test_reasons` (`test_reason_id`, `test_reason_name`, `parent_reason`, `test_reason_status`, `updated_datetime`) VALUES
(1, 'Suspect', NULL, 'active', '2020-12-01 14:30:36'),
(2, 'Contact', NULL, 'active', '2020-12-01 14:30:36'),
(3, 'Postmortem', NULL, 'active', '2020-12-01 14:30:36'),
(4, 'Treatment Discharge', NULL, 'active', '2020-12-01 14:30:36'),
(5, 'Follow up', NULL, 'active', '2020-12-01 14:30:36'),
(6, 'Alert', NULL, 'active', '2020-12-01 14:30:36'),
(7, 'Screening', NULL, 'active', '2020-12-01 14:30:36'),
(8, 'Others', NULL, 'active', '2021-10-06 14:17:37');

-- --------------------------------------------------------

--
-- Table structure for table `r_eid_results`
--

CREATE TABLE `r_eid_results` (
  `result_id` varchar(256) NOT NULL,
  `result` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_eid_results`
--

TRUNCATE TABLE `r_eid_results`;
--
-- Dumping data for table `r_eid_results`
--

INSERT INTO `r_eid_results` (`result_id`, `result`, `status`, `updated_datetime`, `data_sync`) VALUES
('error', 'Error', 'active', NULL, 0),
('indeterminate', 'Indeterminate', 'inactive', '2022-07-06 15:23:21', 0),
('invalid', 'Invalid', 'active', NULL, 0),
('negative', 'Negative', 'active', NULL, 0),
('no-result', 'No Result', 'active', NULL, 0),
('positive', 'Positive', 'active', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_eid_sample_rejection_reasons`
--

CREATE TABLE `r_eid_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_eid_sample_rejection_reasons`
--

TRUNCATE TABLE `r_eid_sample_rejection_reasons`;
--
-- Dumping data for table `r_eid_sample_rejection_reasons`
--

INSERT INTO `r_eid_sample_rejection_reasons` (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`, `rejection_reason_status`, `rejection_reason_code`, `updated_datetime`, `data_sync`) VALUES
(1, 'Poorly labelled specimen', 'general', 'active', 'Gen_PLSP', '2020-12-01 14:30:36', 1),
(2, 'Mismatched sample and form labeling', 'general', 'active', 'Gen_MMSP', '2020-12-01 14:30:36', 1),
(3, 'Missing labels on container or tracking form', 'general', 'active', 'Gen_MLTS', '2020-12-01 14:30:36', 1),
(4, 'Sample without request forms/Tracking forms', 'general', 'active', 'Gen_SMRT', '2020-12-01 14:30:36', 1),
(5, 'Name/Information of requester is missing', 'general', 'active', 'Gen_NIRM', '2020-12-01 14:30:36', 1),
(6, 'Missing information on request form - Age', 'general', 'active', 'Gen_MIRA', '2020-12-01 14:30:36', 1),
(7, 'Missing information on request form - Sex', 'general', 'active', 'Gen_MIRS', '2020-12-01 14:30:36', 1),
(8, 'Missing information on request form - Sample Collection Date', 'general', 'active', 'Gen_MIRD', '2020-12-01 14:30:36', 1),
(9, 'Missing information on request form - ART No', 'general', 'active', 'Gen_MIAN', '2020-12-01 14:30:36', 1),
(10, 'Inappropriate specimen packing', 'general', 'active', 'Gen_ISPK', '2020-12-01 14:30:36', 1),
(11, 'Inappropriate specimen for test request', 'general', 'active', 'Gen_ISTR', '2020-12-01 14:30:36', 1),
(12, 'Wrong container/anticoagulant used', 'whole blood', 'active', 'BLD_WCAU', '2020-12-01 14:30:36', 1),
(13, 'EDTA tube specimens that arrived hemolyzed', 'whole blood', 'active', 'BLD_HMLY', '2020-12-01 14:30:36', 1),
(14, 'ETDA tube that arrives more than 24 hours after specimen collection', 'whole blood', 'active', 'BLD_AASC', '2020-12-01 14:30:36', 1),
(15, 'Plasma that arrives at a temperature above 8 C', 'plasma', 'active', 'PLS_AATA', '2020-12-01 14:30:36', 1),
(16, 'Plasma tube contain less than 1.5 mL', 'plasma', 'active', 'PSL_TCLT', '2020-12-01 14:30:36', 1),
(17, 'DBS cards with insufficient blood spots', 'dbs', 'active', 'DBS_IFBS', '2020-12-01 14:30:36', 1),
(18, 'DBS card with clotting present in spots', 'dbs', 'active', 'DBS_CPIS', '2020-12-01 14:30:36', 1),
(19, 'DBS cards that have serum rings indicating contamination around spots', 'dbs', 'active', 'DBS_SRIC', '2020-12-01 14:30:36', 1),
(20, 'VL Machine Flag', 'testing', 'active', 'FLG_', '2020-12-01 14:30:36', 1),
(21, 'CNTRL_FAIL', 'testing', 'active', 'FLG_AL00', '2020-12-01 14:30:36', 1),
(22, 'SYS_ERROR', 'testing', 'active', 'FLG_TM00', '2020-12-01 14:30:36', 1),
(23, 'A/D_ABORT', 'testing', 'active', 'FLG_TM17', '2020-12-01 14:30:36', 1),
(24, 'KIT_EXPIRY', 'testing', 'active', 'FLG_TMAP', '2020-12-01 14:30:36', 1),
(25, 'RUN_EXPIRY', 'testing', 'active', 'FLG_TM19', '2020-12-01 14:30:36', 1),
(26, 'DATA_ERROR', 'testing', 'active', 'FLG_TM20', '2020-12-01 14:30:36', 1),
(27, 'NC_INVALID', 'testing', 'active', 'FLG_TM24', '2020-12-01 14:30:36', 1),
(28, 'LPCINVALID', 'testing', 'active', 'FLG_TM25', '2020-12-01 14:30:36', 1),
(29, 'MPCINVALID', 'testing', 'active', 'FLG_TM26', '2020-12-01 14:30:36', 1),
(30, 'HPCINVALID', 'testing', 'active', 'FLG_TM27', '2020-12-01 14:30:36', 1),
(31, 'S_INVALID', 'testing', 'active', 'FLG_TM29', '2020-12-01 14:30:36', 1),
(32, 'MATH_ERROR', 'testing', 'active', 'FLG_TM31', '2020-12-01 14:30:36', 1),
(33, 'PRECHECK', 'testing', 'active', 'FLG_TM44 ', '2020-12-01 14:30:36', 1),
(34, 'QS_INVALID', 'testing', 'active', 'FLG_TM50', '2020-12-01 14:30:36', 1),
(35, 'POSTCHECK', 'testing', 'active', 'FLG_TM51', '2020-12-01 14:30:36', 1),
(36, 'REAG_ERROR', 'testing', 'active', 'FLG_AP02 ', '2020-12-01 14:30:36', 1),
(37, 'NO_SAMPLE', 'testing', 'active', 'FLG_AP12', '2020-12-01 14:30:36', 1),
(38, 'DISP_ERROR', 'testing', 'active', 'FLG_AP13 ', '2020-12-01 14:30:36', 1),
(39, 'TEMP_RANGE', 'testing', 'active', 'FLG_AP19 ', '2020-12-01 14:30:36', 1),
(40, 'PREP_ABORT', 'testing', 'active', 'FLG_AP24', '2020-12-01 14:30:36', 1),
(41, 'SAMPLECLOT', 'testing', 'active', 'FLG_AP25', '2020-12-01 14:30:36', 1),
(42, 'Form received without Sample', 'general', 'active', 'Gen_NoSample', '2020-12-01 14:30:36', 1),
(43, 'Duplicate Exposed Infant ID', 'general', 'active', 'DEII', '2021-08-23 19:40:53', 0),
(44, 'An infant Older than 18 Months', 'general', 'active', 'AIM', '2021-10-25 13:12:37', 0),
(45, 'Sample overstayed at the facility for one month and above', 'general', 'active', 'Gen_SPOSF', '2021-11-16 18:27:34', 0),
(46, 'DBS Sample Received  without Desiccants ', 'dbs', 'active', 'gd', '2022-02-25 16:20:06', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_eid_sample_type`
--

CREATE TABLE `r_eid_sample_type` (
  `sample_id` int(11) NOT NULL,
  `sample_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_eid_sample_type`
--

TRUNCATE TABLE `r_eid_sample_type`;
--
-- Dumping data for table `r_eid_sample_type`
--

INSERT INTO `r_eid_sample_type` (`sample_id`, `sample_name`, `status`, `updated_datetime`, `data_sync`) VALUES
(1, 'DBS', 'active', '2021-11-24 14:24:30', 1),
(2, 'Whole Blood', 'active', '2021-11-24 14:24:30', 1);

-- --------------------------------------------------------

--
-- Table structure for table `r_eid_test_reasons`
--

CREATE TABLE `r_eid_test_reasons` (
  `test_reason_id` int(11) NOT NULL,
  `test_reason_name` varchar(255) DEFAULT NULL,
  `parent_reason` int(11) DEFAULT '0',
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_eid_test_reasons`
--

TRUNCATE TABLE `r_eid_test_reasons`;
-- --------------------------------------------------------

--
-- Table structure for table `r_funding_sources`
--

CREATE TABLE `r_funding_sources` (
  `funding_source_id` int(11) NOT NULL,
  `funding_source_name` varchar(500) NOT NULL,
  `funding_source_status` varchar(45) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_funding_sources`
--

TRUNCATE TABLE `r_funding_sources`;
--
-- Dumping data for table `r_funding_sources`
--

INSERT INTO `r_funding_sources` (`funding_source_id`, `funding_source_name`, `funding_source_status`, `updated_datetime`, `data_sync`) VALUES
(1, 'MOH', 'active', '2021-07-05 12:55:17', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_hepatitis_comorbidities`
--

CREATE TABLE `r_hepatitis_comorbidities` (
  `comorbidity_id` int(11) NOT NULL,
  `comorbidity_name` varchar(255) DEFAULT NULL,
  `comorbidity_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_hepatitis_comorbidities`
--

TRUNCATE TABLE `r_hepatitis_comorbidities`;
--
-- Dumping data for table `r_hepatitis_comorbidities`
--

INSERT INTO `r_hepatitis_comorbidities` (`comorbidity_id`, `comorbidity_name`, `comorbidity_status`, `updated_datetime`) VALUES
(1, 'Diabetes', 'active', '2020-11-17 16:32:11'),
(2, 'Chronic renal failure', 'active', '2020-11-17 16:32:11'),
(3, 'Cancer', 'active', '2020-11-17 16:32:11'),
(4, 'HIV infection', 'active', '2020-11-17 16:32:11'),
(5, 'Cardiovascular disease', 'active', '2020-11-17 16:32:11'),
(6, 'HPV', 'active', '2020-11-17 16:32:11');

-- --------------------------------------------------------

--
-- Table structure for table `r_hepatitis_results`
--

CREATE TABLE `r_hepatitis_results` (
  `result_id` varchar(255) NOT NULL,
  `result` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_hepatitis_results`
--

TRUNCATE TABLE `r_hepatitis_results`;
-- --------------------------------------------------------

--
-- Table structure for table `r_hepatitis_risk_factors`
--

CREATE TABLE `r_hepatitis_risk_factors` (
  `riskfactor_id` int(11) NOT NULL,
  `riskfactor_name` varchar(255) DEFAULT NULL,
  `riskfactor_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_hepatitis_risk_factors`
--

TRUNCATE TABLE `r_hepatitis_risk_factors`;
--
-- Dumping data for table `r_hepatitis_risk_factors`
--

INSERT INTO `r_hepatitis_risk_factors` (`riskfactor_id`, `riskfactor_name`, `riskfactor_status`, `updated_datetime`) VALUES
(1, 'Ever diagnosed with a liver disease', 'active', '2020-11-17 16:35:09'),
(2, 'Viral hepatitis in the family', 'active', '2020-11-17 16:35:09'),
(3, 'Ever been operated', 'active', '2020-11-17 16:35:09'),
(4, 'Ever been traditionally operated (ibyinyo, ibirimi, indasago, scarification, tattoo)', 'active', '2020-11-17 16:35:09'),
(5, 'Ever been transfused', 'active', '2020-11-17 16:35:09'),
(6, 'Having more than one sexually partner', 'active', '2020-11-17 16:35:09'),
(7, 'Ever experienced a physical trauma', 'active', '2020-11-17 16:35:09');

-- --------------------------------------------------------

--
-- Table structure for table `r_hepatitis_sample_rejection_reasons`
--

CREATE TABLE `r_hepatitis_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_hepatitis_sample_rejection_reasons`
--

TRUNCATE TABLE `r_hepatitis_sample_rejection_reasons`;
-- --------------------------------------------------------

--
-- Table structure for table `r_hepatitis_sample_type`
--

CREATE TABLE `r_hepatitis_sample_type` (
  `sample_id` int(11) NOT NULL,
  `sample_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_hepatitis_sample_type`
--

TRUNCATE TABLE `r_hepatitis_sample_type`;
-- --------------------------------------------------------

--
-- Table structure for table `r_hepatitis_test_reasons`
--

CREATE TABLE `r_hepatitis_test_reasons` (
  `test_reason_id` int(11) NOT NULL,
  `test_reason_name` varchar(255) DEFAULT NULL,
  `parent_reason` int(11) DEFAULT NULL,
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_hepatitis_test_reasons`
--

TRUNCATE TABLE `r_hepatitis_test_reasons`;
-- --------------------------------------------------------

--
-- Table structure for table `r_implementation_partners`
--

CREATE TABLE `r_implementation_partners` (
  `i_partner_id` int(11) NOT NULL,
  `i_partner_name` varchar(500) NOT NULL,
  `i_partner_status` varchar(45) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_implementation_partners`
--

TRUNCATE TABLE `r_implementation_partners`;
--
-- Dumping data for table `r_implementation_partners`
--

INSERT INTO `r_implementation_partners` (`i_partner_id`, `i_partner_name`, `i_partner_status`, `updated_datetime`, `data_sync`) VALUES
(1, 'MOH', 'active', '2021-07-05 12:55:18', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_sample_controls`
--

CREATE TABLE `r_sample_controls` (
  `r_sample_control_id` int(11) NOT NULL,
  `r_sample_control_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_sample_controls`
--

TRUNCATE TABLE `r_sample_controls`;
--
-- Dumping data for table `r_sample_controls`
--

INSERT INTO `r_sample_controls` (`r_sample_control_id`, `r_sample_control_name`) VALUES
(1, 'S'),
(2, 'Control'),
(3, 'HPC'),
(4, 'LPC'),
(5, 'NC'),
(6, 'Calibrator'),
(7, 'HIV1.0mlDBS'),
(8, ''),
(9, 'dd/MM/yyyy'),
(10, 'SAMPLE TYPE');

-- --------------------------------------------------------

--
-- Table structure for table `r_sample_status`
--

CREATE TABLE `r_sample_status` (
  `status_id` int(11) NOT NULL,
  `status_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_sample_status`
--

TRUNCATE TABLE `r_sample_status`;
--
-- Dumping data for table `r_sample_status`
--

INSERT INTO `r_sample_status` (`status_id`, `status_name`, `status`) VALUES
(1, 'Hold', 'active'),
(2, 'Lost', 'active'),
(3, 'Sample Reordered', 'active'),
(4, 'Rejected', 'active'),
(5, 'Failed/Invalid', 'active'),
(6, 'Sample Registered at Testing Lab', 'active'),
(7, 'Accepted', 'active'),
(8, 'Awaiting Approval', 'active'),
(9, 'Sample Registered at Health Center', 'active'),
(10, 'Sample Expired', 'active'),
(11, 'No Result', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `r_tb_results`
--

CREATE TABLE `r_tb_results` (
  `result_id` int(11) NOT NULL,
  `result` varchar(256) DEFAULT NULL,
  `result_type` varchar(256) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_tb_results`
--

TRUNCATE TABLE `r_tb_results`;
--
-- Dumping data for table `r_tb_results`
--

INSERT INTO `r_tb_results` (`result_id`, `result`, `result_type`, `status`, `updated_datetime`, `data_sync`) VALUES
(1, 'Positive', NULL, 'active', '2021-11-12 08:37:26', 0),
(2, 'Negative', NULL, 'active', '2021-11-12 08:37:26', 0),
(3, 'Negative', 'lam', 'active', '2021-11-12 08:38:20', 0),
(4, 'Positive', 'lam', 'active', '2021-11-12 08:38:20', 0),
(5, 'Invalid', 'lam', 'active', '2021-11-12 08:38:20', 0),
(6, 'N (MTB not detected)', 'x-pert', 'active', '2021-11-12 08:38:20', 0),
(7, 'T (MTB detected rifampicin resistance not detected)', 'x-pert', 'active', '2021-11-12 08:38:20', 0),
(8, 'TI (MTB detected rifampicin resistance indeterminate)', 'x-pert', 'active', '2021-11-12 08:38:20', 0),
(9, 'RR (MTB detected rifampicin resistance detected)', 'lam', 'active', '2021-11-12 08:38:20', 0),
(10, 'TT (MTB detected (Trace) rifampicin resistance indeterminate)', 'x-pert', 'active', '2021-11-12 08:38:20', 0),
(11, 'I (Invalid/Error/No result)', 'x-pert', 'active', '2021-11-12 08:38:20', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_tb_sample_rejection_reasons`
--

CREATE TABLE `r_tb_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL,
  `rejection_reason_name` varchar(256) DEFAULT NULL,
  `rejection_type` varchar(256) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(45) DEFAULT NULL,
  `rejection_reason_code` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_tb_sample_rejection_reasons`
--

TRUNCATE TABLE `r_tb_sample_rejection_reasons`;
--
-- Dumping data for table `r_tb_sample_rejection_reasons`
--

INSERT INTO `r_tb_sample_rejection_reasons` (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`, `rejection_reason_status`, `rejection_reason_code`, `updated_datetime`, `data_sync`) VALUES
(1, 'Sample damaged', 'general', 'active', NULL, '2021-11-12 08:37:26', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_tb_sample_type`
--

CREATE TABLE `r_tb_sample_type` (
  `sample_id` int(11) NOT NULL,
  `sample_name` varchar(256) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_tb_sample_type`
--

TRUNCATE TABLE `r_tb_sample_type`;
--
-- Dumping data for table `r_tb_sample_type`
--

INSERT INTO `r_tb_sample_type` (`sample_id`, `sample_name`, `status`, `updated_datetime`, `data_sync`) VALUES
(1, 'Serum', 'active', '2021-11-12 08:37:26', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_tb_test_reasons`
--

CREATE TABLE `r_tb_test_reasons` (
  `test_reason_id` int(11) NOT NULL,
  `test_reason_name` varchar(256) DEFAULT NULL,
  `parent_reason` int(11) DEFAULT NULL,
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_tb_test_reasons`
--

TRUNCATE TABLE `r_tb_test_reasons`;
--
-- Dumping data for table `r_tb_test_reasons`
--

INSERT INTO `r_tb_test_reasons` (`test_reason_id`, `test_reason_name`, `parent_reason`, `test_reason_status`, `updated_datetime`) VALUES
(1, 'Case confirmed in TB', 0, 'active', '2021-11-12 08:37:26');

-- --------------------------------------------------------

--
-- Table structure for table `r_test_types`
--

CREATE TABLE `r_test_types` (
  `test_type_id` int(11) NOT NULL,
  `test_standard_name` varchar(256) DEFAULT NULL,
  `test_generic_name` varchar(256) DEFAULT NULL,
  `test_short_code` varchar(256) DEFAULT NULL,
  `test_loinc_code` varchar(256) DEFAULT NULL,
  `test_form_config` text,
  `test_results_config` text,
  `test_status` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_test_types`
--

TRUNCATE TABLE `r_test_types`;
-- --------------------------------------------------------

--
-- Table structure for table `r_vl_art_regimen`
--

CREATE TABLE `r_vl_art_regimen` (
  `art_id` int(11) NOT NULL,
  `art_code` varchar(255) DEFAULT NULL,
  `parent_art` int(11) NOT NULL,
  `headings` varchar(255) DEFAULT NULL,
  `nation_identifier` varchar(255) DEFAULT NULL,
  `art_status` varchar(45) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_vl_art_regimen`
--

TRUNCATE TABLE `r_vl_art_regimen`;
--
-- Dumping data for table `r_vl_art_regimen`
--

INSERT INTO `r_vl_art_regimen` (`art_id`, `art_code`, `parent_art`, `headings`, `nation_identifier`, `art_status`, `updated_datetime`, `data_sync`) VALUES
(1, '1a = AZT+3TC+EFV', 0, 'Adult 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(2, '1b = AZT+3TC+NVP', 0, 'Adult 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(3, '1c = TDF+3TC+DTG', 0, 'Adult 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(4, '1d = ABC+3TC (600/300)+DTG', 0, 'Adult 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(5, '1f  = TDF+3TC+EFV', 0, 'Adult 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(6, '1g = TDF+3TC+NVP', 0, 'Adult 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(7, '1h = TDF +FTC+ EFV', 0, 'Adult 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(8, '1j = TDF+FTC+NVP', 0, 'Adult 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(9, '1k=ABC+3TC+EFV', 0, 'Adult 1st Line Regimens', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(10, '2j=ABC/3TC+ATV/r', 0, 'Adult 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(11, '2i=ABC/3TC+LPV/r', 0, 'Adult 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(12, '1m=ABC+3TC+NVP', 0, 'Adult 1st Line Regimens', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(13, '2a = AZT+3TC+DTG', 0, 'Adult 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(14, '2b = ABC+3TC+DTG', 0, 'Adult 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(15, '2c = TDF+3TC+LPV/r', 0, 'Adult 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(16, '2d = TDF+3TC+ATV/r', 0, 'Adult 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(17, '2e = TDF+3TC-LPV/r', 0, 'Adult 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(18, '2f = TDF/FTC-ATV/r', 0, 'Adult 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(19, '2g = AZT+3TC+LPV/r', 0, 'Adult 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(20, '2h = AZT+3TC+ATV/r', 0, 'Adult 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(21, '4a = AZT+3TC+NVP', 1, 'Child 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(22, '4b = AZT+3TC+EFV', 2, 'Child 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(23, '4c = ABC+3TC (120/60)+LPV/r', 0, 'Child 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(24, '4d = ABC+3TC (120/60)+DTG', 0, 'Child 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(25, '4f  = ABC+3TC+NVP', 5, 'Child 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(26, '4g = ABC+3TC (120/60)+EFV (200mg)', 0, 'Child 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(27, '4h = TDF+3TC+EFV', 7, 'Child 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(28, '5a = AZT+3TC+LPV/r', 0, 'Child 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(29, '5b = AZT/3TC+RAL', 0, 'Child 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(30, '5c = ABC/3TC (120/60) +RAL', 0, 'Child 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(31, '5d = AZT/3TC+ATV/r', 0, 'Child 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(32, 'TDF/3TC/DTG', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(33, 'TLD120', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(34, 'ABC/3TC/DTG', 1, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(35, 'TLD90', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(36, '2k= TDF+3TC+DTG', 0, 'Adult 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(37, '1E', 1, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(38, '4i=ABC/3TC+LPV/r', 0, 'Child 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(39, '1C+CPT', 1, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(40, 'TLD', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(41, '5i', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(42, '4I = ABC/3TC + AZT', 0, 'Child 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(43, '1C/90', 1, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(44, 'TDF/3TC/DTG/90', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(45, 'TLIS', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(46, '1C/180', 1, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(47, '5h=ABC/3TC+DTG', 0, 'Child 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(48, '1F+CPT', 1, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(49, '4j=AZT/3TC(60/30)+LPV/r', 1, 'Child 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(50, '5i/90', 1, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(51, 'DTG/1C', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(52, 'ABH/ICLLPVIR', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(53, 'IC(TLD)', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(54, 'IF/90', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(55, 'IF/180', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(56, '5g=AZT/3TC+DTG', 0, 'Child 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(57, '1C/CTX', 1, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(58, '1K', 1, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(59, 'TDF/3TC/EF2', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(60, '5e=ABC/3TC+ATV/r', 0, 'Child 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(61, 'TLD180', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(62, 'TLE-IF', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(63, '1e = AZT/3TC+ DTG', 0, 'Adult 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(64, '5i=ABC/3TC+LPV/r', 0, 'Child 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(65, 'AZT/3TC/EFZ', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(66, '4k=TDF/3TC+NVP', 1, 'Child 1st Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(67, 'IF+IC', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(68, 'TLD/1C', 0, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(69, '11', 1, '', 'sudan', 'inactive', '2020-12-01 14:31:15', 1),
(70, '5f=TDF/3TC+ATV/r', 0, 'Child 2nd Line Regimens', 'sudan', 'active', '2020-12-01 14:31:15', 1),
(71, '4e=ABC/3TC (120/60)+ DTG10', 0, 'Child 1st Line Regimens', NULL, 'active', '2022-06-06 16:07:44', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_vl_results`
--

CREATE TABLE `r_vl_results` (
  `result_id` int(11) NOT NULL,
  `result` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `available_for_instruments` json DEFAULT NULL,
  `interpretation` varchar(25) NOT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_vl_results`
--

TRUNCATE TABLE `r_vl_results`;
--
-- Dumping data for table `r_vl_results`
--

INSERT INTO `r_vl_results` (`result_id`, `result`, `status`, `available_for_instruments`, `interpretation`, `updated_datetime`, `data_sync`) VALUES
(1, 'Below Detection Level', 'active', '[\"3\", \"4\", \"6\", \"5\", \"1\", \"7\"]', 'suppressed', '2022-11-07 09:35:05', 0),
(2, 'Failed', 'active', '[\"3\", \"4\", \"6\", \"5\", \"1\", \"7\"]', 'failed', '2022-11-07 09:35:37', 0),
(3, 'Error', 'active', '[\"3\", \"4\", \"6\", \"5\", \"1\", \"7\"]', 'error', '2022-11-07 09:35:37', 0),
(4, 'No Result', 'active', '[\"3\", \"4\", \"6\", \"5\", \"1\", \"7\"]', 'no result', '2022-11-07 09:35:37', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_vl_sample_rejection_reasons`
--

CREATE TABLE `r_vl_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_vl_sample_rejection_reasons`
--

TRUNCATE TABLE `r_vl_sample_rejection_reasons`;
--
-- Dumping data for table `r_vl_sample_rejection_reasons`
--

INSERT INTO `r_vl_sample_rejection_reasons` (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`, `rejection_reason_status`, `rejection_reason_code`, `updated_datetime`, `data_sync`) VALUES
(1, 'Samples older than 30 days before receipt at the laboratory', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(2, 'Incorrectly labelled DBS card/ Unreadable Details on the card', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(3, 'Missing or duplicated unique ART number on DBS card or Lab requisition form', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(4, 'Mismatch of unique ART number on DBS card and lab requisition form', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(5, 'Less than 4 dry blood spots on the card', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(6, 'Improperly dried blood spots', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(7, 'Insufficient blood for testing (small spots) or no blood spots on DBS card', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(8, 'Damaged blood spots', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(9, 'Clotted, layered and/or haemolysed blood spots', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(10, 'DBS sample cards stacked together in one glassine bag', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(11, 'DBS sample received without laboratory requisition form and vice versa', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(12, 'DBS samples packaged without desiccants', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(13, 'DBS collected on an expired Card/filter paper', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(14, 'Patient Not yet 6 months ON ART', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(15, 'Insufficient sample ', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(16, 'Duplicate entry into VLSM', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(17, 'REQUEST AND DISPATCH FORM WITH OUT ACCOMPANYING DBS SAMPLE', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(18, 'NO SAMPLE AND REQUEST FORM', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(19, 'Dispatch with no accampaning DBS sample and Request form', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(20, 'Dispatch form with no accompanying DBS Sample and Requisition form ', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(21, 'Duplicated unique  ART number  on DBS card ', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(22, 'NO SAMPLE ID', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(23, 'BLOOD SPOTS EATEN BY RAT OR DAMAGED BLOOD SPOTS', 'general', 'inactive', 'inactive', '2022-06-22 18:06:04', 1),
(24, 'Duplicated unique ART number ', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(25, 'Interval time between first and second vl tests is less than 6 month ', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(26, '4457 Internal control failed ,insufficient sample please collect new sample ', 'general', 'active', NULL, '2022-06-22 18:06:04', 1),
(27, 'Samples overstayed at the facility', 'dbs', 'inactive', 'inactive', '2022-06-22 18:06:04', 1),
(28, 'Missing Age', 'general', 'active', NULL, '2022-06-22 18:06:04', 0),
(29, 'Duplicate Specimen Sent for Testing', 'general', 'active', NULL, '2022-06-22 18:06:04', 0),
(30, 'Missing Duplicated Unique ART Number', 'general', 'inactive', 'active', '2022-06-22 18:06:04', 0),
(31, 'Sample run out of spots ', 'dbs', 'inactive', 'active', '2022-06-22 18:06:04', 0),
(32, 'DBS Sample Run Out Of Spots ', 'general', 'inactive', 'active', '2022-06-22 18:06:04', 0),
(33, 'Mismatch of facility name between Requisition Form and dispatched form', 'dbs', 'inactive', 'active', '2022-06-22 18:06:04', 0),
(34, 'Mismatch Of Facility Name in the Requisition Form and Dispatched Form', 'general', 'active', 'active', '2022-06-27 16:38:49', 0),
(35, 'Invalid Results', 'plasma', 'active', 'IR', '2022-07-28 11:22:41', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_vl_sample_type`
--

CREATE TABLE `r_vl_sample_type` (
  `sample_id` int(11) NOT NULL,
  `sample_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_vl_sample_type`
--

TRUNCATE TABLE `r_vl_sample_type`;
--
-- Dumping data for table `r_vl_sample_type`
--

INSERT INTO `r_vl_sample_type` (`sample_id`, `sample_name`, `status`, `updated_datetime`, `data_sync`) VALUES
(1, 'Plasma', 'active', NULL, 1),
(2, 'Whole Blood', 'active', '2022-07-12 17:53:47', 1),
(3, 'DBS', 'active', NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `r_vl_test_failure_reasons`
--

CREATE TABLE `r_vl_test_failure_reasons` (
  `failure_id` int(11) NOT NULL,
  `failure_reason` varchar(256) DEFAULT NULL,
  `status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_vl_test_failure_reasons`
--

TRUNCATE TABLE `r_vl_test_failure_reasons`;
--
-- Dumping data for table `r_vl_test_failure_reasons`
--

INSERT INTO `r_vl_test_failure_reasons` (`failure_id`, `failure_reason`, `status`, `updated_datetime`, `data_sync`) VALUES
(1, 'Reason 1', 'active', '2022-10-05 02:23:47', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `r_vl_test_reasons`
--

CREATE TABLE `r_vl_test_reasons` (
  `test_reason_id` int(11) NOT NULL,
  `test_reason_name` varchar(255) DEFAULT NULL,
  `parent_reason` int(11) DEFAULT '0',
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `r_vl_test_reasons`
--

TRUNCATE TABLE `r_vl_test_reasons`;
--
-- Dumping data for table `r_vl_test_reasons`
--

INSERT INTO `r_vl_test_reasons` (`test_reason_id`, `test_reason_name`, `parent_reason`, `test_reason_status`, `updated_datetime`, `data_sync`) VALUES
(1, 'routine VL', 0, 'active', NULL, 0),
(2, 'Confirmation Of Treatment Failure(repeat VL at 3M)', 0, 'active', NULL, 0),
(3, 'clinical failure', 0, 'active', NULL, 0),
(4, 'immunological failure', 0, 'active', NULL, 0),
(5, 'single drug substitution', 0, 'active', NULL, 0),
(6, 'Pregnant Mother', 0, 'active', NULL, 0),
(7, 'Lactating Mother', 0, 'active', NULL, 0),
(8, 'Baseline VL', 0, 'active', NULL, 0),
(9, 'routine', 0, 'active', NULL, 0),
(10, 'suspect', 0, 'active', NULL, 0),
(11, 'failure', 0, 'active', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `support`
--

CREATE TABLE `support` (
  `support_id` int(11) NOT NULL,
  `feedback` varchar(500) DEFAULT NULL,
  `feedback_url` varchar(255) DEFAULT NULL,
  `upload_file_name` varchar(255) DEFAULT NULL,
  `attach_screenshot` varchar(100) DEFAULT NULL,
  `screenshot_file_name` varchar(255) DEFAULT NULL,
  `status` varchar(100) DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `support`
--

TRUNCATE TABLE `support`;
-- --------------------------------------------------------

--
-- Table structure for table `system_admin`
--

CREATE TABLE `system_admin` (
  `system_admin_id` int(11) NOT NULL,
  `system_admin_name` mediumtext,
  `system_admin_email` varchar(255) DEFAULT NULL,
  `system_admin_login` mediumtext,
  `system_admin_password` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `system_admin`
--

TRUNCATE TABLE `system_admin`;
-- --------------------------------------------------------

--
-- Table structure for table `system_config`
--

CREATE TABLE `system_config` (
  `display_name` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `system_config`
--

TRUNCATE TABLE `system_config`;
--
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`display_name`, `name`, `value`) VALUES
('Testing Lab ID', 'sc_testing_lab_id', NULL),
('User Type', 'sc_user_type', 'vluser'),
('Version', 'sc_version', '5.1.3'),
('Email Id', 'sup_email', NULL),
('Password', 'sup_password', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `s_available_country_forms`
--

CREATE TABLE `s_available_country_forms` (
  `vlsm_country_id` int(11) NOT NULL,
  `form_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `s_available_country_forms`
--

TRUNCATE TABLE `s_available_country_forms`;
--
-- Dumping data for table `s_available_country_forms`
--

INSERT INTO `s_available_country_forms` (`vlsm_country_id`, `form_name`) VALUES
(1, 'South Sudan '),
(2, 'Sierra Leone'),
(3, 'Democratic Republic of the Congo'),
(4, 'Zambia '),
(5, 'Papua New Guinea'),
(6, 'WHO '),
(7, 'Rwanda '),
(8, 'Angola ');

-- --------------------------------------------------------

--
-- Table structure for table `s_vlsm_instance`
--

CREATE TABLE `s_vlsm_instance` (
  `vlsm_instance_id` varchar(255) NOT NULL,
  `instance_facility_name` varchar(255) DEFAULT NULL,
  `instance_facility_code` varchar(255) DEFAULT NULL,
  `instance_facility_type` varchar(255) DEFAULT NULL,
  `instance_facility_logo` varchar(255) DEFAULT NULL,
  `instance_added_on` datetime DEFAULT NULL,
  `instance_update_on` datetime DEFAULT NULL,
  `instance_mac_address` varchar(255) DEFAULT NULL,
  `vl_last_dash_sync` datetime DEFAULT NULL,
  `eid_last_dash_sync` datetime DEFAULT NULL,
  `covid19_last_dash_sync` datetime DEFAULT NULL,
  `last_vldash_sync` datetime DEFAULT NULL,
  `last_remote_requests_sync` datetime DEFAULT NULL,
  `last_remote_results_sync` datetime DEFAULT NULL,
  `last_remote_reference_data_sync` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `s_vlsm_instance`
--

TRUNCATE TABLE `s_vlsm_instance`;
-- --------------------------------------------------------

--
-- Table structure for table `tb_tests`
--

CREATE TABLE `tb_tests` (
  `tb_test_id` int(11) NOT NULL,
  `tb_id` int(11) DEFAULT NULL,
  `actual_no` varchar(256) DEFAULT NULL,
  `test_result` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `tb_tests`
--

TRUNCATE TABLE `tb_tests`;
-- --------------------------------------------------------

--
-- Table structure for table `temp_sample_import`
--

CREATE TABLE `temp_sample_import` (
  `temp_sample_id` int(11) NOT NULL,
  `module` varchar(255) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `lab_name` varchar(255) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `lab_contact_person` varchar(255) DEFAULT NULL,
  `lab_phone_number` varchar(255) DEFAULT NULL,
  `sample_received_at_vl_lab_datetime` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` varchar(255) DEFAULT NULL,
  `result_dispatched_datetime` varchar(255) DEFAULT NULL,
  `result_reviewed_datetime` varchar(255) DEFAULT NULL,
  `result_reviewed_by` varchar(255) DEFAULT NULL,
  `lab_tech_comments` mediumtext,
  `approver_comments` varchar(255) DEFAULT NULL,
  `lot_number` varchar(255) DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `sample_code` varchar(255) DEFAULT NULL,
  `batch_code` varchar(255) DEFAULT NULL,
  `batch_code_key` int(11) DEFAULT NULL,
  `sample_type` varchar(255) DEFAULT NULL,
  `test_type` varchar(255) DEFAULT NULL,
  `order_number` varchar(255) DEFAULT NULL,
  `result_value_log` varchar(255) DEFAULT NULL,
  `result_value_absolute` varchar(255) DEFAULT NULL,
  `result_value_text` varchar(255) DEFAULT NULL,
  `result_value_absolute_decimal` varchar(255) DEFAULT NULL,
  `result` varchar(255) DEFAULT NULL,
  `sample_details` varchar(255) DEFAULT NULL,
  `result_status` varchar(255) DEFAULT NULL,
  `import_machine_file_name` varchar(255) DEFAULT NULL,
  `vl_test_platform` varchar(255) DEFAULT NULL,
  `import_machine_name` int(11) DEFAULT NULL,
  `request_exported_datetime` datetime DEFAULT NULL,
  `request_imported_datetime` datetime DEFAULT NULL,
  `result_exported_datetime` datetime DEFAULT NULL,
  `result_imported_datetime` datetime DEFAULT NULL,
  `temp_sample_status` int(11) NOT NULL DEFAULT '0',
  `sample_review_by` varchar(255) DEFAULT NULL,
  `imported_by` varchar(255) NOT NULL,
  `file_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `temp_sample_import`
--

TRUNCATE TABLE `temp_sample_import`;
-- --------------------------------------------------------

--
-- Table structure for table `testing_labs`
--

CREATE TABLE `testing_labs` (
  `test_type` enum('vl','eid','covid19','hepatitis','tb') NOT NULL,
  `facility_id` int(11) NOT NULL,
  `attributes` json DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `monthly_target` varchar(255) DEFAULT NULL,
  `suppressed_monthly_target` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `testing_labs`
--

TRUNCATE TABLE `testing_labs`;
-- --------------------------------------------------------

--
-- Table structure for table `testing_lab_health_facilities_map`
--

CREATE TABLE `testing_lab_health_facilities_map` (
  `facility_map_id` int(11) NOT NULL,
  `vl_lab_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `testing_lab_health_facilities_map`
--

TRUNCATE TABLE `testing_lab_health_facilities_map`;
-- --------------------------------------------------------

--
-- Table structure for table `track_api_requests`
--

CREATE TABLE `track_api_requests` (
  `api_track_id` int(11) NOT NULL,
  `transaction_id` varchar(256) DEFAULT NULL,
  `requested_by` varchar(255) DEFAULT NULL,
  `requested_on` datetime DEFAULT NULL,
  `number_of_records` varchar(50) DEFAULT NULL,
  `request_type` varchar(50) DEFAULT NULL,
  `test_type` varchar(255) DEFAULT NULL,
  `api_url` mediumtext,
  `api_params` text,
  `request_data` text,
  `response_data` text,
  `facility_id` varchar(256) DEFAULT NULL,
  `data_format` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `track_api_requests`
--

TRUNCATE TABLE `track_api_requests`;
-- --------------------------------------------------------

--
-- Table structure for table `track_qr_code_page`
--

CREATE TABLE `track_qr_code_page` (
  `tqcp_d` int(11) NOT NULL,
  `test_type` varchar(256) NOT NULL COMMENT 'vl, eid, covid19 or hepatitis',
  `test_type_id` int(11) NOT NULL,
  `sample_code` varchar(256) DEFAULT NULL,
  `browser` varchar(256) DEFAULT NULL,
  `ip_address` varchar(256) DEFAULT NULL,
  `operating_system` varchar(256) DEFAULT NULL,
  `date_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `track_qr_code_page`
--

TRUNCATE TABLE `track_qr_code_page`;
-- --------------------------------------------------------

--
-- Table structure for table `user_details`
--

CREATE TABLE `user_details` (
  `user_id` varchar(255) NOT NULL,
  `user_name` varchar(500) DEFAULT NULL,
  `interface_user_name` json DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone_number` varchar(255) DEFAULT NULL,
  `login_id` varchar(255) DEFAULT NULL,
  `password` varchar(500) DEFAULT NULL,
  `role_id` int(11) DEFAULT NULL,
  `user_signature` longtext,
  `api_token` longtext,
  `api_token_generated_datetime` datetime DEFAULT NULL,
  `api_token_exipiration_days` int(11) DEFAULT NULL,
  `force_password_reset` int(11) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `app_access` varchar(50) DEFAULT 'no',
  `hash_algorithm` varchar(256) NOT NULL DEFAULT 'sha1',
  `data_sync` int(11) DEFAULT '0',
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `user_details`
--

TRUNCATE TABLE `user_details`;
-- --------------------------------------------------------

--
-- Table structure for table `user_facility_map`
--

CREATE TABLE `user_facility_map` (
  `user_facility_map_id` int(11) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `facility_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `user_facility_map`
--

TRUNCATE TABLE `user_facility_map`;
-- --------------------------------------------------------

--
-- Table structure for table `user_login_history`
--

CREATE TABLE `user_login_history` (
  `history_id` int(11) NOT NULL,
  `user_id` varchar(1000) DEFAULT NULL,
  `login_id` varchar(1000) NOT NULL,
  `login_attempted_datetime` datetime DEFAULT NULL,
  `login_status` varchar(1000) DEFAULT NULL,
  `ip_address` varchar(1000) DEFAULT NULL,
  `browser` varchar(1000) DEFAULT NULL,
  `operating_system` varchar(1000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `user_login_history`
--

TRUNCATE TABLE `user_login_history`;
-- --------------------------------------------------------

--
-- Table structure for table `vl_contact_notes`
--

CREATE TABLE `vl_contact_notes` (
  `contact_notes_id` int(11) NOT NULL,
  `treament_contact_id` int(11) DEFAULT NULL,
  `contact_notes` longtext,
  `collected_on` date DEFAULT NULL,
  `added_on` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `vl_contact_notes`
--

TRUNCATE TABLE `vl_contact_notes`;
-- --------------------------------------------------------

--
-- Table structure for table `vl_imported_controls`
--

CREATE TABLE `vl_imported_controls` (
  `control_id` int(11) NOT NULL,
  `control_code` varchar(255) NOT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `control_type` varchar(255) DEFAULT NULL,
  `lot_number` varchar(255) DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `is_sample_rejected` varchar(255) DEFAULT NULL,
  `reason_for_sample_rejection` varchar(255) DEFAULT NULL,
  `result_value_absolute` varchar(255) DEFAULT NULL,
  `result_value_log` varchar(255) DEFAULT NULL,
  `result_value_text` varchar(255) DEFAULT NULL,
  `result_value_absolute_decimal` varchar(255) DEFAULT NULL,
  `result` varchar(255) DEFAULT NULL,
  `approver_comments` varchar(255) DEFAULT NULL,
  `result_approved_by` varchar(255) DEFAULT NULL,
  `result_approved_datetime` datetime DEFAULT NULL,
  `result_reviewed_by` varchar(1000) DEFAULT NULL,
  `lab_tech_comments` mediumtext,
  `result_reviewed_datetime` datetime DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `vlsm_country_id` varchar(10) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `imported_date_time` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Truncate table before insert `vl_imported_controls`
--

TRUNCATE TABLE `vl_imported_controls`;
--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`);

--
-- Indexes for table `audit_form_covid19`
--
ALTER TABLE `audit_form_covid19`
  ADD PRIMARY KEY (`covid19_id`,`revision`);

--
-- Indexes for table `audit_form_eid`
--
ALTER TABLE `audit_form_eid`
  ADD PRIMARY KEY (`eid_id`,`revision`);

--
-- Indexes for table `audit_form_hepatitis`
--
ALTER TABLE `audit_form_hepatitis`
  ADD PRIMARY KEY (`hepatitis_id`,`revision`);

--
-- Indexes for table `audit_form_tb`
--
ALTER TABLE `audit_form_tb`
  ADD PRIMARY KEY (`tb_id`,`revision`);

--
-- Indexes for table `audit_form_vl`
--
ALTER TABLE `audit_form_vl`
  ADD PRIMARY KEY (`vl_sample_id`,`revision`);

--
-- Indexes for table `batch_details`
--
ALTER TABLE `batch_details`
  ADD PRIMARY KEY (`batch_id`);

--
-- Indexes for table `covid19_imported_controls`
--
ALTER TABLE `covid19_imported_controls`
  ADD PRIMARY KEY (`control_id`);

--
-- Indexes for table `covid19_patient_comorbidities`
--
ALTER TABLE `covid19_patient_comorbidities`
  ADD PRIMARY KEY (`covid19_id`,`comorbidity_id`);

--
-- Indexes for table `covid19_patient_symptoms`
--
ALTER TABLE `covid19_patient_symptoms`
  ADD PRIMARY KEY (`covid19_id`,`symptom_id`);

--
-- Indexes for table `covid19_positive_confirmation_manifest`
--
ALTER TABLE `covid19_positive_confirmation_manifest`
  ADD PRIMARY KEY (`manifest_id`);

--
-- Indexes for table `covid19_reasons_for_testing`
--
ALTER TABLE `covid19_reasons_for_testing`
  ADD PRIMARY KEY (`covid19_id`,`reasons_id`);

--
-- Indexes for table `covid19_tests`
--
ALTER TABLE `covid19_tests`
  ADD PRIMARY KEY (`test_id`),
  ADD KEY `covid19_id` (`covid19_id`);

--
-- Indexes for table `eid_imported_controls`
--
ALTER TABLE `eid_imported_controls`
  ADD PRIMARY KEY (`control_id`);

--
-- Indexes for table `facility_details`
--
ALTER TABLE `facility_details`
  ADD PRIMARY KEY (`facility_id`),
  ADD UNIQUE KEY `facility_code` (`facility_code`),
  ADD UNIQUE KEY `facility_name` (`facility_name`),
  ADD UNIQUE KEY `other_id` (`other_id`);

--
-- Indexes for table `facility_type`
--
ALTER TABLE `facility_type`
  ADD PRIMARY KEY (`facility_type_id`);

--
-- Indexes for table `failed_result_retest_tracker`
--
ALTER TABLE `failed_result_retest_tracker`
  ADD PRIMARY KEY (`frrt_id`);

--
-- Indexes for table `form_covid19`
--
ALTER TABLE `form_covid19`
  ADD PRIMARY KEY (`covid19_id`),
  ADD UNIQUE KEY `unique_sample_code` (`sample_code`),
  ADD UNIQUE KEY `unique_id` (`unique_id`),
  ADD KEY `sample_code_key` (`sample_code_key`),
  ADD KEY `remote_sample_code_key` (`remote_sample_code_key`),
  ADD KEY `sample_package_id` (`sample_package_id`);

--
-- Indexes for table `form_eid`
--
ALTER TABLE `form_eid`
  ADD PRIMARY KEY (`eid_id`),
  ADD UNIQUE KEY `sample_code` (`sample_code`),
  ADD UNIQUE KEY `unique_id` (`unique_id`),
  ADD KEY `sample_code_key` (`sample_code_key`),
  ADD KEY `remote_sample_code_key` (`remote_sample_code_key`),
  ADD KEY `sample_package_id` (`sample_package_id`);

--
-- Indexes for table `form_hepatitis`
--
ALTER TABLE `form_hepatitis`
  ADD PRIMARY KEY (`hepatitis_id`),
  ADD UNIQUE KEY `unique_id` (`unique_id`),
  ADD KEY `last_modified_datetime` (`last_modified_datetime`),
  ADD KEY `sample_code_key` (`sample_code_key`),
  ADD KEY `remote_sample_code_key` (`remote_sample_code_key`),
  ADD KEY `sample_package_id` (`sample_package_id`);

--
-- Indexes for table `form_tb`
--
ALTER TABLE `form_tb`
  ADD PRIMARY KEY (`tb_id`),
  ADD UNIQUE KEY `sample_code` (`sample_code`,`lab_id`),
  ADD UNIQUE KEY `unique_id` (`unique_id`),
  ADD UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  ADD KEY `facility_id` (`facility_id`),
  ADD KEY `lab_id` (`lab_id`),
  ADD KEY `sample_code_key` (`sample_code_key`),
  ADD KEY `remote_sample_code_key` (`remote_sample_code_key`),
  ADD KEY `sample_package_id` (`sample_package_id`);

--
-- Indexes for table `form_vl`
--
ALTER TABLE `form_vl`
  ADD PRIMARY KEY (`vl_sample_id`),
  ADD UNIQUE KEY `sample_code` (`sample_code`),
  ADD UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  ADD UNIQUE KEY `sample_code_2` (`sample_code`,`lab_id`),
  ADD UNIQUE KEY `unique_id` (`unique_id`),
  ADD KEY `facility_id` (`facility_id`),
  ADD KEY `art_no` (`patient_art_no`),
  ADD KEY `sample_id` (`sample_type`),
  ADD KEY `created_by` (`request_created_by`),
  ADD KEY `funding_source` (`funding_source`),
  ADD KEY `sample_collection_date` (`sample_collection_date`),
  ADD KEY `sample_tested_datetime` (`sample_tested_datetime`),
  ADD KEY `lab_id` (`lab_id`),
  ADD KEY `result_status` (`result_status`),
  ADD KEY `sample_code_key` (`sample_code_key`),
  ADD KEY `remote_sample_code_key` (`remote_sample_code_key`),
  ADD KEY `result_approved_by` (`result_approved_by`),
  ADD KEY `result_reviewed_by` (`result_reviewed_by`),
  ADD KEY `sample_package_id` (`sample_package_id`);

--
-- Indexes for table `geographical_divisions`
--
ALTER TABLE `geographical_divisions`
  ADD PRIMARY KEY (`geo_id`);

--
-- Indexes for table `global_config`
--
ALTER TABLE `global_config`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `health_facilities`
--
ALTER TABLE `health_facilities`
  ADD PRIMARY KEY (`test_type`,`facility_id`);

--
-- Indexes for table `hepatitis_patient_comorbidities`
--
ALTER TABLE `hepatitis_patient_comorbidities`
  ADD PRIMARY KEY (`hepatitis_id`,`comorbidity_id`);

--
-- Indexes for table `hepatitis_risk_factors`
--
ALTER TABLE `hepatitis_risk_factors`
  ADD PRIMARY KEY (`hepatitis_id`,`riskfactors_id`);

--
-- Indexes for table `hold_sample_import`
--
ALTER TABLE `hold_sample_import`
  ADD PRIMARY KEY (`hold_sample_id`);

--
-- Indexes for table `instruments`
--
ALTER TABLE `instruments`
  ADD PRIMARY KEY (`config_id`);

--
-- Indexes for table `instrument_controls`
--
ALTER TABLE `instrument_controls`
  ADD PRIMARY KEY (`test_type`,`config_id`);

--
-- Indexes for table `instrument_machines`
--
ALTER TABLE `instrument_machines`
  ADD PRIMARY KEY (`config_machine_id`);

--
-- Indexes for table `lab_report_signatories`
--
ALTER TABLE `lab_report_signatories`
  ADD PRIMARY KEY (`signatory_id`),
  ADD KEY `lab_id` (`lab_id`);

--
-- Indexes for table `log_result_updates`
--
ALTER TABLE `log_result_updates`
  ADD PRIMARY KEY (`result_log_id`);

--
-- Indexes for table `move_samples`
--
ALTER TABLE `move_samples`
  ADD PRIMARY KEY (`move_sample_id`);

--
-- Indexes for table `move_samples_map`
--
ALTER TABLE `move_samples_map`
  ADD PRIMARY KEY (`sample_map_id`);

--
-- Indexes for table `other_config`
--
ALTER TABLE `other_config`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `package_details`
--
ALTER TABLE `package_details`
  ADD PRIMARY KEY (`package_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`patient_id`),
  ADD UNIQUE KEY `patient_code` (`patient_code`),
  ADD UNIQUE KEY `patient_code_prefix` (`patient_code_prefix`,`patient_code_key`);

--
-- Indexes for table `privileges`
--
ALTER TABLE `privileges`
  ADD PRIMARY KEY (`privilege_id`),
  ADD UNIQUE KEY `resource` (`resource_id`,`privilege_name`);

--
-- Indexes for table `province_details`
--
ALTER TABLE `province_details`
  ADD PRIMARY KEY (`province_id`),
  ADD UNIQUE KEY `province_name` (`province_name`);

--
-- Indexes for table `qc_covid19`
--
ALTER TABLE `qc_covid19`
  ADD PRIMARY KEY (`qc_id`),
  ADD UNIQUE KEY `qc_code` (`qc_code`),
  ADD UNIQUE KEY `unique_id` (`unique_id`);

--
-- Indexes for table `qc_covid19_tests`
--
ALTER TABLE `qc_covid19_tests`
  ADD PRIMARY KEY (`qc_test_id`);

--
-- Indexes for table `report_to_mail`
--
ALTER TABLE `report_to_mail`
  ADD PRIMARY KEY (`report_mail_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `resources`
--
ALTER TABLE `resources`
  ADD PRIMARY KEY (`resource_id`);

--
-- Indexes for table `result_import_stats`
--
ALTER TABLE `result_import_stats`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`role_id`);

--
-- Indexes for table `roles_privileges_map`
--
ALTER TABLE `roles_privileges_map`
  ADD PRIMARY KEY (`map_id`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `privilege_id` (`privilege_id`);

--
-- Indexes for table `r_countries`
--
ALTER TABLE `r_countries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `id` (`id`);

--
-- Indexes for table `r_covid19_comorbidities`
--
ALTER TABLE `r_covid19_comorbidities`
  ADD PRIMARY KEY (`comorbidity_id`);

--
-- Indexes for table `r_covid19_qc_testkits`
--
ALTER TABLE `r_covid19_qc_testkits`
  ADD PRIMARY KEY (`testkit_id`),
  ADD UNIQUE KEY `testkit_name` (`testkit_name`);

--
-- Indexes for table `r_covid19_results`
--
ALTER TABLE `r_covid19_results`
  ADD PRIMARY KEY (`result_id`);

--
-- Indexes for table `r_covid19_sample_rejection_reasons`
--
ALTER TABLE `r_covid19_sample_rejection_reasons`
  ADD PRIMARY KEY (`rejection_reason_id`);

--
-- Indexes for table `r_covid19_sample_type`
--
ALTER TABLE `r_covid19_sample_type`
  ADD PRIMARY KEY (`sample_id`);

--
-- Indexes for table `r_covid19_symptoms`
--
ALTER TABLE `r_covid19_symptoms`
  ADD PRIMARY KEY (`symptom_id`);

--
-- Indexes for table `r_covid19_test_reasons`
--
ALTER TABLE `r_covid19_test_reasons`
  ADD PRIMARY KEY (`test_reason_id`);

--
-- Indexes for table `r_eid_results`
--
ALTER TABLE `r_eid_results`
  ADD PRIMARY KEY (`result_id`);

--
-- Indexes for table `r_eid_sample_rejection_reasons`
--
ALTER TABLE `r_eid_sample_rejection_reasons`
  ADD PRIMARY KEY (`rejection_reason_id`);

--
-- Indexes for table `r_eid_sample_type`
--
ALTER TABLE `r_eid_sample_type`
  ADD PRIMARY KEY (`sample_id`);

--
-- Indexes for table `r_eid_test_reasons`
--
ALTER TABLE `r_eid_test_reasons`
  ADD PRIMARY KEY (`test_reason_id`);

--
-- Indexes for table `r_funding_sources`
--
ALTER TABLE `r_funding_sources`
  ADD PRIMARY KEY (`funding_source_id`);

--
-- Indexes for table `r_hepatitis_comorbidities`
--
ALTER TABLE `r_hepatitis_comorbidities`
  ADD PRIMARY KEY (`comorbidity_id`);

--
-- Indexes for table `r_hepatitis_results`
--
ALTER TABLE `r_hepatitis_results`
  ADD PRIMARY KEY (`result_id`);

--
-- Indexes for table `r_hepatitis_risk_factors`
--
ALTER TABLE `r_hepatitis_risk_factors`
  ADD PRIMARY KEY (`riskfactor_id`);

--
-- Indexes for table `r_hepatitis_sample_rejection_reasons`
--
ALTER TABLE `r_hepatitis_sample_rejection_reasons`
  ADD PRIMARY KEY (`rejection_reason_id`);

--
-- Indexes for table `r_hepatitis_sample_type`
--
ALTER TABLE `r_hepatitis_sample_type`
  ADD PRIMARY KEY (`sample_id`);

--
-- Indexes for table `r_hepatitis_test_reasons`
--
ALTER TABLE `r_hepatitis_test_reasons`
  ADD PRIMARY KEY (`test_reason_id`);

--
-- Indexes for table `r_implementation_partners`
--
ALTER TABLE `r_implementation_partners`
  ADD PRIMARY KEY (`i_partner_id`);

--
-- Indexes for table `r_sample_controls`
--
ALTER TABLE `r_sample_controls`
  ADD PRIMARY KEY (`r_sample_control_id`);

--
-- Indexes for table `r_sample_status`
--
ALTER TABLE `r_sample_status`
  ADD PRIMARY KEY (`status_id`);

--
-- Indexes for table `r_tb_results`
--
ALTER TABLE `r_tb_results`
  ADD PRIMARY KEY (`result_id`);

--
-- Indexes for table `r_tb_sample_rejection_reasons`
--
ALTER TABLE `r_tb_sample_rejection_reasons`
  ADD PRIMARY KEY (`rejection_reason_id`);

--
-- Indexes for table `r_tb_sample_type`
--
ALTER TABLE `r_tb_sample_type`
  ADD PRIMARY KEY (`sample_id`);

--
-- Indexes for table `r_tb_test_reasons`
--
ALTER TABLE `r_tb_test_reasons`
  ADD PRIMARY KEY (`test_reason_id`);

--
-- Indexes for table `r_test_types`
--
ALTER TABLE `r_test_types`
  ADD PRIMARY KEY (`test_type_id`);

--
-- Indexes for table `r_vl_art_regimen`
--
ALTER TABLE `r_vl_art_regimen`
  ADD PRIMARY KEY (`art_id`);

--
-- Indexes for table `r_vl_results`
--
ALTER TABLE `r_vl_results`
  ADD PRIMARY KEY (`result_id`);

--
-- Indexes for table `r_vl_sample_rejection_reasons`
--
ALTER TABLE `r_vl_sample_rejection_reasons`
  ADD PRIMARY KEY (`rejection_reason_id`);

--
-- Indexes for table `r_vl_sample_type`
--
ALTER TABLE `r_vl_sample_type`
  ADD PRIMARY KEY (`sample_id`);

--
-- Indexes for table `r_vl_test_failure_reasons`
--
ALTER TABLE `r_vl_test_failure_reasons`
  ADD PRIMARY KEY (`failure_id`);

--
-- Indexes for table `r_vl_test_reasons`
--
ALTER TABLE `r_vl_test_reasons`
  ADD PRIMARY KEY (`test_reason_id`);

--
-- Indexes for table `support`
--
ALTER TABLE `support`
  ADD PRIMARY KEY (`support_id`);

--
-- Indexes for table `system_admin`
--
ALTER TABLE `system_admin`
  ADD PRIMARY KEY (`system_admin_id`),
  ADD UNIQUE KEY `user_admin_id` (`system_admin_id`);

--
-- Indexes for table `system_config`
--
ALTER TABLE `system_config`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `s_available_country_forms`
--
ALTER TABLE `s_available_country_forms`
  ADD PRIMARY KEY (`vlsm_country_id`);

--
-- Indexes for table `s_vlsm_instance`
--
ALTER TABLE `s_vlsm_instance`
  ADD PRIMARY KEY (`vlsm_instance_id`),
  ADD UNIQUE KEY `vl_instance_id` (`vlsm_instance_id`);

--
-- Indexes for table `tb_tests`
--
ALTER TABLE `tb_tests`
  ADD PRIMARY KEY (`tb_test_id`),
  ADD KEY `tb_id` (`tb_id`);

--
-- Indexes for table `temp_sample_import`
--
ALTER TABLE `temp_sample_import`
  ADD PRIMARY KEY (`temp_sample_id`);

--
-- Indexes for table `testing_labs`
--
ALTER TABLE `testing_labs`
  ADD PRIMARY KEY (`test_type`,`facility_id`);

--
-- Indexes for table `testing_lab_health_facilities_map`
--
ALTER TABLE `testing_lab_health_facilities_map`
  ADD PRIMARY KEY (`facility_map_id`),
  ADD KEY `vl_lab_id` (`vl_lab_id`),
  ADD KEY `facility_id` (`facility_id`);

--
-- Indexes for table `track_api_requests`
--
ALTER TABLE `track_api_requests`
  ADD PRIMARY KEY (`api_track_id`),
  ADD KEY `requested_on` (`requested_on`);

--
-- Indexes for table `track_qr_code_page`
--
ALTER TABLE `track_qr_code_page`
  ADD PRIMARY KEY (`tqcp_d`);

--
-- Indexes for table `user_details`
--
ALTER TABLE `user_details`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indexes for table `user_facility_map`
--
ALTER TABLE `user_facility_map`
  ADD PRIMARY KEY (`user_facility_map_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `facility_id` (`facility_id`);

--
-- Indexes for table `user_login_history`
--
ALTER TABLE `user_login_history`
  ADD PRIMARY KEY (`history_id`);

--
-- Indexes for table `vl_contact_notes`
--
ALTER TABLE `vl_contact_notes`
  ADD PRIMARY KEY (`contact_notes_id`),
  ADD KEY `treament_contact_id` (`treament_contact_id`);

--
-- Indexes for table `vl_imported_controls`
--
ALTER TABLE `vl_imported_controls`
  ADD PRIMARY KEY (`control_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_form_covid19`
--
ALTER TABLE `audit_form_covid19`
  MODIFY `revision` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_form_eid`
--
ALTER TABLE `audit_form_eid`
  MODIFY `revision` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_form_hepatitis`
--
ALTER TABLE `audit_form_hepatitis`
  MODIFY `revision` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_form_tb`
--
ALTER TABLE `audit_form_tb`
  MODIFY `revision` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_form_vl`
--
ALTER TABLE `audit_form_vl`
  MODIFY `revision` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `batch_details`
--
ALTER TABLE `batch_details`
  MODIFY `batch_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `covid19_imported_controls`
--
ALTER TABLE `covid19_imported_controls`
  MODIFY `control_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `covid19_positive_confirmation_manifest`
--
ALTER TABLE `covid19_positive_confirmation_manifest`
  MODIFY `manifest_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `covid19_tests`
--
ALTER TABLE `covid19_tests`
  MODIFY `test_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `eid_imported_controls`
--
ALTER TABLE `eid_imported_controls`
  MODIFY `control_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facility_details`
--
ALTER TABLE `facility_details`
  MODIFY `facility_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `facility_type`
--
ALTER TABLE `facility_type`
  MODIFY `facility_type_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `failed_result_retest_tracker`
--
ALTER TABLE `failed_result_retest_tracker`
  MODIFY `frrt_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `form_covid19`
--
ALTER TABLE `form_covid19`
  MODIFY `covid19_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `form_eid`
--
ALTER TABLE `form_eid`
  MODIFY `eid_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `form_hepatitis`
--
ALTER TABLE `form_hepatitis`
  MODIFY `hepatitis_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `form_tb`
--
ALTER TABLE `form_tb`
  MODIFY `tb_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `form_vl`
--
ALTER TABLE `form_vl`
  MODIFY `vl_sample_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `geographical_divisions`
--
ALTER TABLE `geographical_divisions`
  MODIFY `geo_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hold_sample_import`
--
ALTER TABLE `hold_sample_import`
  MODIFY `hold_sample_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `instruments`
--
ALTER TABLE `instruments`
  MODIFY `config_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `instrument_machines`
--
ALTER TABLE `instrument_machines`
  MODIFY `config_machine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `lab_report_signatories`
--
ALTER TABLE `lab_report_signatories`
  MODIFY `signatory_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `log_result_updates`
--
ALTER TABLE `log_result_updates`
  MODIFY `result_log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `move_samples`
--
ALTER TABLE `move_samples`
  MODIFY `move_sample_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `move_samples_map`
--
ALTER TABLE `move_samples_map`
  MODIFY `sample_map_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `package_details`
--
ALTER TABLE `package_details`
  MODIFY `package_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `privileges`
--
ALTER TABLE `privileges`
  MODIFY `privilege_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=237;

--
-- AUTO_INCREMENT for table `province_details`
--
ALTER TABLE `province_details`
  MODIFY `province_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

--
-- AUTO_INCREMENT for table `qc_covid19`
--
ALTER TABLE `qc_covid19`
  MODIFY `qc_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qc_covid19_tests`
--
ALTER TABLE `qc_covid19_tests`
  MODIFY `qc_test_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_to_mail`
--
ALTER TABLE `report_to_mail`
  MODIFY `report_mail_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `result_import_stats`
--
ALTER TABLE `result_import_stats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `roles_privileges_map`
--
ALTER TABLE `roles_privileges_map`
  MODIFY `map_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5867;

--
-- AUTO_INCREMENT for table `r_countries`
--
ALTER TABLE `r_countries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=250;

--
-- AUTO_INCREMENT for table `r_covid19_comorbidities`
--
ALTER TABLE `r_covid19_comorbidities`
  MODIFY `comorbidity_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_covid19_qc_testkits`
--
ALTER TABLE `r_covid19_qc_testkits`
  MODIFY `testkit_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `r_covid19_sample_rejection_reasons`
--
ALTER TABLE `r_covid19_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `r_covid19_sample_type`
--
ALTER TABLE `r_covid19_sample_type`
  MODIFY `sample_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `r_covid19_symptoms`
--
ALTER TABLE `r_covid19_symptoms`
  MODIFY `symptom_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `r_covid19_test_reasons`
--
ALTER TABLE `r_covid19_test_reasons`
  MODIFY `test_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `r_eid_sample_rejection_reasons`
--
ALTER TABLE `r_eid_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `r_eid_sample_type`
--
ALTER TABLE `r_eid_sample_type`
  MODIFY `sample_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `r_eid_test_reasons`
--
ALTER TABLE `r_eid_test_reasons`
  MODIFY `test_reason_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_funding_sources`
--
ALTER TABLE `r_funding_sources`
  MODIFY `funding_source_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `r_hepatitis_comorbidities`
--
ALTER TABLE `r_hepatitis_comorbidities`
  MODIFY `comorbidity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `r_hepatitis_risk_factors`
--
ALTER TABLE `r_hepatitis_risk_factors`
  MODIFY `riskfactor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `r_hepatitis_sample_rejection_reasons`
--
ALTER TABLE `r_hepatitis_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_hepatitis_sample_type`
--
ALTER TABLE `r_hepatitis_sample_type`
  MODIFY `sample_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_hepatitis_test_reasons`
--
ALTER TABLE `r_hepatitis_test_reasons`
  MODIFY `test_reason_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_implementation_partners`
--
ALTER TABLE `r_implementation_partners`
  MODIFY `i_partner_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `r_sample_controls`
--
ALTER TABLE `r_sample_controls`
  MODIFY `r_sample_control_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `r_sample_status`
--
ALTER TABLE `r_sample_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `r_tb_results`
--
ALTER TABLE `r_tb_results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `r_tb_sample_rejection_reasons`
--
ALTER TABLE `r_tb_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `r_tb_sample_type`
--
ALTER TABLE `r_tb_sample_type`
  MODIFY `sample_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `r_tb_test_reasons`
--
ALTER TABLE `r_tb_test_reasons`
  MODIFY `test_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `r_test_types`
--
ALTER TABLE `r_test_types`
  MODIFY `test_type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_vl_art_regimen`
--
ALTER TABLE `r_vl_art_regimen`
  MODIFY `art_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `r_vl_results`
--
ALTER TABLE `r_vl_results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `r_vl_sample_rejection_reasons`
--
ALTER TABLE `r_vl_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `r_vl_sample_type`
--
ALTER TABLE `r_vl_sample_type`
  MODIFY `sample_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `r_vl_test_failure_reasons`
--
ALTER TABLE `r_vl_test_failure_reasons`
  MODIFY `failure_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `r_vl_test_reasons`
--
ALTER TABLE `r_vl_test_reasons`
  MODIFY `test_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `support`
--
ALTER TABLE `support`
  MODIFY `support_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_admin`
--
ALTER TABLE `system_admin`
  MODIFY `system_admin_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `s_available_country_forms`
--
ALTER TABLE `s_available_country_forms`
  MODIFY `vlsm_country_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `tb_tests`
--
ALTER TABLE `tb_tests`
  MODIFY `tb_test_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `temp_sample_import`
--
ALTER TABLE `temp_sample_import`
  MODIFY `temp_sample_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `testing_lab_health_facilities_map`
--
ALTER TABLE `testing_lab_health_facilities_map`
  MODIFY `facility_map_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `track_api_requests`
--
ALTER TABLE `track_api_requests`
  MODIFY `api_track_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `track_qr_code_page`
--
ALTER TABLE `track_qr_code_page`
  MODIFY `tqcp_d` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_facility_map`
--
ALTER TABLE `user_facility_map`
  MODIFY `user_facility_map_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_login_history`
--
ALTER TABLE `user_login_history`
  MODIFY `history_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vl_contact_notes`
--
ALTER TABLE `vl_contact_notes`
  MODIFY `contact_notes_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vl_imported_controls`
--
ALTER TABLE `vl_imported_controls`
  MODIFY `control_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `covid19_tests`
--
ALTER TABLE `covid19_tests`
  ADD CONSTRAINT `covid19_tests_ibfk_1` FOREIGN KEY (`covid19_id`) REFERENCES `form_covid19` (`covid19_id`);

--
-- Constraints for table `form_vl`
--
ALTER TABLE `form_vl`
  ADD CONSTRAINT `form_vl_ibfk_5` FOREIGN KEY (`result_status`) REFERENCES `r_sample_status` (`status_id`),
  ADD CONSTRAINT `form_vl_ibfk_6` FOREIGN KEY (`funding_source`) REFERENCES `r_funding_sources` (`funding_source_id`);

--
-- Constraints for table `lab_report_signatories`
--
ALTER TABLE `lab_report_signatories`
  ADD CONSTRAINT `lab_report_signatories_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `facility_details` (`facility_id`);

--
-- Constraints for table `report_to_mail`
--
ALTER TABLE `report_to_mail`
  ADD CONSTRAINT `report_to_mail_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batch_details` (`batch_id`);

--
-- Constraints for table `roles_privileges_map`
--
ALTER TABLE `roles_privileges_map`
  ADD CONSTRAINT `roles_privileges_map_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  ADD CONSTRAINT `roles_privileges_map_ibfk_2` FOREIGN KEY (`privilege_id`) REFERENCES `privileges` (`privilege_id`);

--
-- Constraints for table `tb_tests`
--
ALTER TABLE `tb_tests`
  ADD CONSTRAINT `tb_tests_ibfk_1` FOREIGN KEY (`tb_id`) REFERENCES `form_tb` (`tb_id`);

--
-- Constraints for table `testing_lab_health_facilities_map`
--
ALTER TABLE `testing_lab_health_facilities_map`
  ADD CONSTRAINT `testing_lab_health_facilities_map_ibfk_1` FOREIGN KEY (`vl_lab_id`) REFERENCES `facility_details` (`facility_id`),
  ADD CONSTRAINT `testing_lab_health_facilities_map_ibfk_2` FOREIGN KEY (`facility_id`) REFERENCES `facility_details` (`facility_id`);

--
-- Constraints for table `user_details`
--
ALTER TABLE `user_details`
  ADD CONSTRAINT `user_details_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`);

--
-- Constraints for table `user_facility_map`
--
ALTER TABLE `user_facility_map`
  ADD CONSTRAINT `user_facility_map_ibfk_2` FOREIGN KEY (`facility_id`) REFERENCES `facility_details` (`facility_id`);

--
-- Constraints for table `vl_contact_notes`
--
ALTER TABLE `vl_contact_notes`
  ADD CONSTRAINT `vl_contact_notes_ibfk_1` FOREIGN KEY (`treament_contact_id`) REFERENCES `form_vl` (`vl_sample_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
