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

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL,
  `event_type` varchar(255) DEFAULT NULL,
  `action` mediumtext,
  `resource` varchar(255) DEFAULT NULL,
  `user_id` varchar(256) DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `ip_address` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `vlsm_instance_id` varchar(255) DEFAULT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code` varchar(500) DEFAULT NULL,
  `sample_reordered` varchar(256) NOT NULL DEFAULT 'no',
  `external_sample_code` varchar(255) DEFAULT NULL,
  `test_number` int(11) DEFAULT NULL,
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(255) DEFAULT NULL,
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
  `patient_gender` varchar(256) DEFAULT NULL,
  `is_patient_pregnant` varchar(255) DEFAULT NULL,
  `patient_phone_number` text,
  `patient_email` varchar(256) DEFAULT NULL,
  `patient_nationality` varchar(255) DEFAULT NULL,
  `patient_passport_number` text,
  `vaccination_status` text,
  `vaccination_dosage` text,
  `vaccination_type` text,
  `vaccination_type_other` text,
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
  `specimen_taken_before_antibiotics` text,
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
  `positive_test_manifest_code` varchar(255) DEFAULT NULL,
  `lot_number` varchar(255) DEFAULT NULL,
  `source_of_request` text,
  `source_data_dump` mediumtext,
  `result_sent_to_source` mediumtext,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `is_result_mail_sent` varchar(255) DEFAULT 'no',
  `app_sample_code` varchar(255) DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` varchar(255) DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

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
  `sample_code_key` int(11) DEFAULT NULL,
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
  `test_1_date` date DEFAULT NULL,
  `test_1_batch` int(11) DEFAULT NULL,
  `test_1_assay` text,
  `test_1_ct_qs` int(11) DEFAULT NULL,
  `test_1_result` text,
  `test_1_repeated` text,
  `test_1_repeat_reason` text,
  `test_2_date` date DEFAULT NULL,
  `test_2_batch` int(11) DEFAULT NULL,
  `test_2_assay` text,
  `test_2_ct_qs` int(11) DEFAULT NULL,
  `test_2_result` text,
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
  `previous_sample_code` varchar(256) DEFAULT NULL,
  `clinical_assessment` varchar(256) DEFAULT NULL,
  `clinician_name` varchar(256) DEFAULT NULL,
  `mother_dob` date DEFAULT NULL,
  `mother_age_in_years` varchar(255) DEFAULT NULL,
  `mother_marital_status` varchar(255) DEFAULT NULL,
  `child_id` text,
  `child_name` text,
  `child_surname` text,
  `child_dob` date DEFAULT NULL,
  `child_age` varchar(255) DEFAULT NULL,
  `child_gender` varchar(255) DEFAULT NULL,
  `child_weight` int(11) DEFAULT NULL,
  `child_prophylactic_arv` text,
  `child_prophylactic_arv_other` text,
  `mother_hiv_status` varchar(255) DEFAULT NULL,
  `next_appointment_date` date DEFAULT NULL,
  `no_of_exposed_children` int(11) DEFAULT NULL,
  `no_of_infected_children` int(11) DEFAULT NULL,
  `mother_arv_protocol` int(11) DEFAULT NULL,
  `mode_of_delivery` varchar(255) DEFAULT NULL,
  `mode_of_delivery_other` varchar(256) DEFAULT NULL,
  `mother_art_status` varchar(256) DEFAULT NULL,
  `mother_treatment` varchar(255) DEFAULT NULL,
  `mother_regimen` text,
  `started_art_date` date DEFAULT NULL,
  `mother_mtct_risk` varchar(32) DEFAULT NULL,
  `mother_treatment_other` varchar(1000) DEFAULT NULL,
  `mother_treatment_initiation_date` date DEFAULT NULL,
  `mother_cd4` varchar(255) DEFAULT NULL,
  `mother_cd4_test_date` date DEFAULT NULL,
  `mother_vl_result` varchar(255) DEFAULT NULL,
  `mother_vl_test_date` date DEFAULT NULL,
  `is_child_symptomatic` int(11) DEFAULT NULL,
  `date_of_weaning` date DEFAULT NULL,
  `was_child_breastfed` text,
  `child_treatment` varchar(255) DEFAULT NULL,
  `child_treatment_other` varchar(1000) DEFAULT NULL,
  `child_treatment_initiation_date` date DEFAULT NULL,
  `is_infant_receiving_treatment` varchar(255) DEFAULT NULL,
  `has_infant_stopped_breastfeeding` varchar(255) DEFAULT NULL,
  `infant_on_pmtct_prophylaxis` text,
  `infant_on_ctx_prophylaxis` text,
  `age_breastfeeding_stopped_in_months` varchar(255) DEFAULT NULL,
  `infant_art_status` varchar(256) DEFAULT NULL,
  `infant_art_status_other` varchar(256) DEFAULT NULL,
  `child_started_art_date` text,
  `choice_of_feeding` varchar(255) DEFAULT NULL,
  `is_child_on_cotrim` text,
  `child_started_cotrim_date` text,
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
  `sample_collection_reason` text,
  `lab_id` int(11) DEFAULT NULL,
  `lab_testing_point` text,
  `lab_testing_point_other` text,
  `samples_referred_datetime` datetime DEFAULT NULL,
  `referring_lab_id` int(11) DEFAULT NULL,
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
  `second_dbs_requested` varchar(256) DEFAULT NULL,
  `second_DBS_requested_reason` varchar(256) DEFAULT NULL,
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `audit_form_generic`
--

CREATE TABLE `audit_form_generic` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(11) NOT NULL,
  `dt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sample_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `test_type` int(11) DEFAULT NULL,
  `test_type_form` json DEFAULT NULL,
  `vlsm_instance_id` varchar(255) NOT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

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
  `app_sample_code` varchar(256) DEFAULT NULL,
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
  `reason_for_changing` mediumtext,
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

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
  `sample_package_id` int(11) DEFAULT NULL,
  `sample_package_code` mediumtext,
  `source_of_request` varchar(50) DEFAULT NULL,
  `source_data_dump` mediumtext,
  `result_sent_to_source` mediumtext,
  `form_attributes` json DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

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
  `current_arv_protocol` text,
  `current_regimen` text,
  `date_of_initiation_of_current_regimen` varchar(255) DEFAULT NULL,
  `is_patient_pregnant` text,
  `no_of_pregnancy_weeks` int(11) DEFAULT NULL,
  `is_patient_breastfeeding` text,
  `no_of_breastfeeding_weeks` int(11) DEFAULT NULL,
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
  `control_vl_testing_type` text,
  `coinfection_type` text,
  `drug_substitution` text,
  `sample_collected_by` text,
  `facility_comments` mediumtext,
  `vl_test_platform` text,
  `result_value_hiv_detection` int(11) DEFAULT NULL,
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
  `form_attributes` json DEFAULT NULL,
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
  `recency_sync` int(11) DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;

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
  `label_order` mediumtext,
  `created_by` varchar(256) DEFAULT NULL,
  `request_created_datetime` datetime NOT NULL,
  `last_modified_by` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_modified_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- --------------------------------------------------------

--
-- Table structure for table `covid19_patient_comorbidities`
--

CREATE TABLE `covid19_patient_comorbidities` (
  `covid19_id` int(11) NOT NULL,
  `comorbidity_id` int(11) NOT NULL,
  `comorbidity_detected` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- --------------------------------------------------------

--
-- Table structure for table `covid19_reasons_for_testing`
--

CREATE TABLE `covid19_reasons_for_testing` (
  `covid19_id` int(11) NOT NULL,
  `reasons_id` int(11) NOT NULL,
  `reasons_detected` varchar(50) DEFAULT NULL,
  `reason_details` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- --------------------------------------------------------

--
-- Table structure for table `facility_details`
--

CREATE TABLE `facility_details` (
  `facility_id` int(11) NOT NULL,
  `facility_name` varchar(255) DEFAULT NULL,
  `facility_code` varchar(255) DEFAULT NULL,
  `vlsm_instance_id` varchar(255) NOT NULL,
  `other_id` varchar(255) DEFAULT NULL,
  `facility_emails` varchar(255) DEFAULT NULL,
  `report_email` mediumtext,
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
  `report_format` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `facility_type`
--

CREATE TABLE `facility_type` (
  `facility_type_id` int(11) NOT NULL,
  `facility_type_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- --------------------------------------------------------

--
-- Table structure for table `form_covid19`
--

CREATE TABLE `form_covid19` (
  `covid19_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` varchar(255) DEFAULT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(255) DEFAULT NULL,
  `sample_code` varchar(500) DEFAULT NULL,
  `sample_reordered` varchar(256) NOT NULL DEFAULT 'no',
  `external_sample_code` varchar(255) DEFAULT NULL,
  `test_number` int(11) DEFAULT NULL,
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(255) DEFAULT NULL,
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
  `patient_gender` varchar(256) DEFAULT NULL,
  `is_patient_pregnant` varchar(255) DEFAULT NULL,
  `patient_phone_number` text,
  `patient_email` varchar(256) DEFAULT NULL,
  `patient_nationality` varchar(255) DEFAULT NULL,
  `patient_passport_number` text,
  `vaccination_status` text,
  `vaccination_dosage` text,
  `vaccination_type` text,
  `vaccination_type_other` text,
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
  `specimen_taken_before_antibiotics` text,
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
  `sample_package_id` int(11) DEFAULT NULL,
  `sample_package_code` mediumtext,
  `positive_test_manifest_id` int(11) DEFAULT NULL,
  `positive_test_manifest_code` varchar(255) DEFAULT NULL,
  `lot_number` varchar(255) DEFAULT NULL,
  `source_of_request` text,
  `source_data_dump` mediumtext,
  `result_sent_to_source` mediumtext,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `is_result_mail_sent` varchar(255) DEFAULT 'no',
  `app_sample_code` varchar(255) DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` varchar(255) DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `sample_code_key` int(11) DEFAULT NULL,
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
  `test_1_date` date DEFAULT NULL,
  `test_1_batch` int(11) DEFAULT NULL,
  `test_1_assay` text,
  `test_1_ct_qs` int(11) DEFAULT NULL,
  `test_1_result` text,
  `test_1_repeated` text,
  `test_1_repeat_reason` text,
  `test_2_date` date DEFAULT NULL,
  `test_2_batch` int(11) DEFAULT NULL,
  `test_2_assay` text,
  `test_2_ct_qs` int(11) DEFAULT NULL,
  `test_2_result` text,
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
  `previous_sample_code` varchar(32) DEFAULT NULL,
  `clinical_assessment` varchar(256) DEFAULT NULL,
  `clinician_name` varchar(64) DEFAULT NULL,
  `mother_dob` date DEFAULT NULL,
  `mother_age_in_years` varchar(3) DEFAULT NULL,
  `mother_marital_status` varchar(10) DEFAULT NULL,
  `child_id` varchar(32) DEFAULT NULL,
  `child_name` varchar(64) DEFAULT NULL,
  `child_surname` varchar(64) DEFAULT NULL,
  `child_dob` date DEFAULT NULL,
  `child_age` varchar(3) DEFAULT NULL,
  `child_gender` varchar(10) DEFAULT NULL,
  `child_weight` int(11) DEFAULT NULL,
  `child_prophylactic_arv` text,
  `child_prophylactic_arv_other` text,
  `mother_hiv_status` varchar(16) DEFAULT NULL,
  `next_appointment_date` date DEFAULT NULL,
  `no_of_exposed_children` int(11) DEFAULT NULL,
  `no_of_infected_children` int(11) DEFAULT NULL,
  `mother_arv_protocol` int(11) DEFAULT NULL,
  `mode_of_delivery` varchar(255) DEFAULT NULL,
  `mode_of_delivery_other` varchar(32) DEFAULT NULL,
  `mother_art_status` varchar(32) DEFAULT NULL,
  `mother_treatment` varchar(255) DEFAULT NULL,
  `mother_regimen` text,
  `started_art_date` date DEFAULT NULL,
  `mother_mtct_risk` varchar(256) DEFAULT NULL,
  `mother_treatment_other` varchar(1000) DEFAULT NULL,
  `mother_treatment_initiation_date` date DEFAULT NULL,
  `mother_cd4` varchar(255) DEFAULT NULL,
  `mother_cd4_test_date` date DEFAULT NULL,
  `mother_vl_result` varchar(255) DEFAULT NULL,
  `mother_vl_test_date` date DEFAULT NULL,
  `is_child_symptomatic` int(11) DEFAULT NULL,
  `date_of_weaning` date DEFAULT NULL,
  `was_child_breastfed` text,
  `child_treatment` varchar(255) DEFAULT NULL,
  `child_treatment_other` varchar(1000) DEFAULT NULL,
  `child_treatment_initiation_date` date DEFAULT NULL,
  `is_infant_receiving_treatment` varchar(255) DEFAULT NULL,
  `has_infant_stopped_breastfeeding` varchar(255) DEFAULT NULL,
  `infant_on_pmtct_prophylaxis` text,
  `infant_on_ctx_prophylaxis` text,
  `age_breastfeeding_stopped_in_months` varchar(255) DEFAULT NULL,
  `infant_art_status` varchar(32) DEFAULT NULL,
  `infant_art_status_other` varchar(32) DEFAULT NULL,
  `child_started_art_date` text,
  `choice_of_feeding` varchar(255) DEFAULT NULL,
  `is_child_on_cotrim` text,
  `child_started_cotrim_date` text,
  `is_cotrimoxazole_being_administered_to_the_infant` varchar(255) DEFAULT NULL,
  `sample_requestor_name` text,
  `sample_requestor_phone` varchar(16) DEFAULT NULL,
  `specimen_quality` varchar(255) DEFAULT NULL,
  `specimen_type` varchar(255) DEFAULT NULL,
  `reason_for_eid_test` int(11) DEFAULT NULL,
  `pcr_test_performed_before` varchar(10) DEFAULT NULL,
  `pcr_test_number` int(11) DEFAULT NULL,
  `last_pcr_id` varchar(32) DEFAULT NULL,
  `previous_pcr_result` varchar(16) DEFAULT NULL,
  `last_pcr_date` date DEFAULT NULL,
  `reason_for_pcr` varchar(500) DEFAULT NULL,
  `reason_for_repeat_pcr_other` text,
  `rapid_test_performed` varchar(255) DEFAULT NULL,
  `rapid_test_date` date DEFAULT NULL,
  `rapid_test_result` varchar(32) DEFAULT NULL,
  `sample_collection_reason` text,
  `lab_id` int(11) DEFAULT NULL,
  `lab_testing_point` text,
  `lab_testing_point_other` text,
  `samples_referred_datetime` datetime DEFAULT NULL,
  `referring_lab_id` int(11) DEFAULT NULL,
  `lab_technician` text,
  `lab_reception_person` text,
  `eid_test_platform` varchar(64) DEFAULT NULL,
  `result_status` int(11) DEFAULT NULL,
  `locked` varchar(10) DEFAULT 'no',
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
  `second_dbs_requested` varchar(256) DEFAULT NULL,
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
  `sample_package_id` int(11) DEFAULT NULL,
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
-- Table structure for table `form_generic`
--

CREATE TABLE `form_generic` (
  `sample_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `test_type` int(11) DEFAULT NULL,
  `test_type_form` json DEFAULT NULL,
  `vlsm_instance_id` varchar(255) NOT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
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
  `sample_package_id` int(11) DEFAULT NULL,
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
  `result_unit` int(11) DEFAULT NULL,
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

--
-- Triggers `form_generic`
--
DELIMITER $$
CREATE TRIGGER `form_generic_data__ai` AFTER INSERT ON `form_generic` FOR EACH ROW INSERT INTO `audit_form_generic` SELECT 'insert', NULL, NOW(), d.*
    FROM `form_generic` AS d WHERE d.sample_id = NEW.sample_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_generic_data__au` AFTER UPDATE ON `form_generic` FOR EACH ROW INSERT INTO `audit_form_generic` SELECT 'update', NULL, NOW(), d.*
    FROM `form_generic` AS d WHERE d.sample_id = NEW.sample_id
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `form_generic_data__bd` BEFORE DELETE ON `form_generic` FOR EACH ROW INSERT INTO `audit_form_generic` SELECT 'delete', NULL, NOW(), d.*
    FROM `form_generic` AS d WHERE d.sample_id = OLD.sample_id
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
  `app_sample_code` varchar(256) DEFAULT NULL,
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
  `reason_for_changing` mediumtext,
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
  `sample_package_id` int(11) DEFAULT NULL,
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
  `sample_package_id` int(11) DEFAULT NULL,
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
  `current_arv_protocol` text,
  `current_regimen` text,
  `date_of_initiation_of_current_regimen` varchar(255) DEFAULT NULL,
  `is_patient_pregnant` text,
  `no_of_pregnancy_weeks` int(11) DEFAULT NULL,
  `is_patient_breastfeeding` text,
  `no_of_breastfeeding_weeks` int(11) DEFAULT NULL,
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
  `control_vl_testing_type` text,
  `coinfection_type` text,
  `drug_substitution` text,
  `sample_collected_by` text,
  `facility_comments` mediumtext,
  `vl_test_platform` text,
  `result_value_hiv_detection` int(11) DEFAULT NULL,
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
  `form_attributes` json DEFAULT NULL,
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
  `recency_sync` int(11) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
-- Table structure for table `generic_sample_rejection_reason_map`
--

CREATE TABLE `generic_sample_rejection_reason_map` (
  `map_id` int(11) NOT NULL,
  `rejection_reason_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `generic_test_failure_reason_map`
--

CREATE TABLE `generic_test_failure_reason_map` (
  `map_id` int(11) NOT NULL,
  `test_failure_reason_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `generic_test_methods_map`
--

CREATE TABLE `generic_test_methods_map` (
  `map_id` int(11) NOT NULL,
  `test_method_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `generic_test_reason_map`
--

CREATE TABLE `generic_test_reason_map` (
  `map_id` int(11) NOT NULL,
  `test_reason_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `generic_test_results`
--

CREATE TABLE `generic_test_results` (
  `test_id` int(11) NOT NULL,
  `generic_id` int(11) NOT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `test_name` varchar(500) NOT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime NOT NULL,
  `testing_platform` varchar(255) DEFAULT NULL,
  `kit_lot_no` varchar(256) DEFAULT NULL,
  `kit_expiry_date` date DEFAULT NULL,
  `result` varchar(500) NOT NULL,
  `result_unit` int(11) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `generic_test_result_units_map`
--

CREATE TABLE `generic_test_result_units_map` (
  `map_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `generic_test_sample_type_map`
--

CREATE TABLE `generic_test_sample_type_map` (
  `map_id` int(11) NOT NULL,
  `sample_type_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `generic_test_symptoms_map`
--

CREATE TABLE `generic_test_symptoms_map` (
  `map_id` int(11) NOT NULL,
  `symptom_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `updated_by` mediumtext,
  `status` varchar(255) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `global_config`
--

INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES
('App Locale/Language', 'app_locale', 'en_US', 'common', 'no', NULL, NULL, 'active'),
('App Menu Name', 'app_menu_name', 'VLSM', 'app', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Auto Approval', 'auto_approval', 'yes', 'general', 'no', '2022-02-18 16:28:05', NULL, 'inactive'),
('Barcode Format', 'barcode_format', 'C39', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Barcode Printing', 'bar_code_printing', 'off', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('COVID-19 Auto Approve API Results', 'covid19_auto_approve_api_results', 'no', 'covid19', 'no', NULL, NULL, 'active'),
('Generate Patient Code', 'covid19_generate_patient_code', 'no', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Covid-19 Maximum Length', 'covid19_max_length', '', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Covid-19 Minimum Length', 'covid19_min_length', '', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Patient Code Prefix', 'covid19_patient_code_prefix', 'P', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Positive Confirmatory Tests Required By Central Lab', 'covid19_positive_confirmatory_tests_required_by_central_lab', 'yes', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('COVID-19 Report QR Code', 'covid19_report_qr_code', 'no', NULL, 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Report Type', 'covid19_report_type', 'default', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Covid-19 Sample Code Format', 'covid19_sample_code', 'MMYY', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Covid-19 Sample Code Prefix', 'covid19_sample_code_prefix', 'C19', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Covid19 Sample Expiry Days', 'covid19_sample_expiry_after_days', '999', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Covid19 Sample Lock Expiry Days', 'covid19_sample_lock_after_days', '999', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Show Participant Name in Manifest', 'covid19_show_participant_name_in_manifest', 'yes', 'COVID19', 'no', NULL, NULL, 'active'),
('Covid19 Tests Table in Results Pdf', 'covid19_tests_table_in_results_pdf', 'no', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Data Sync Interval', 'data_sync_interval', '30', 'general', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Default Time Zone', 'default_time_zone', 'UTC', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Edit Profile', 'edit_profile', 'yes', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('EID Auto Approve API Results', 'eid_auto_approve_api_results', 'no', 'eid', 'no', NULL, NULL, 'active'),
('EID Maximum Length', 'eid_max_length', '', 'eid', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('EID Minimum Length', 'eid_min_length', '', 'eid', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('EID Report QR Code', 'eid_report_qr_code', 'yes', 'EID', 'no', NULL, NULL, 'active'),
('EID Sample Code', 'eid_sample_code', 'MMYY', 'eid', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('EID Sample Code Prefix', 'eid_sample_code_prefix', 'EID', 'eid', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('EID Sample Expiry Days', 'eid_sample_expiry_after_days', '999', 'eid', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('EID Sample Lock Expiry Days', 'eid_sample_lock_after_days', '999', 'eid', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Show Participant Name in Manifest', 'eid_show_participant_name_in_manifest', 'yes', 'EID', 'no', NULL, NULL, 'active'),
('Enable QR Code Mechanism', 'enable_qr_mechanism', 'no', 'general', 'no', '2022-02-18 16:28:05', NULL, 'inactive'),
('Header', 'header', 'MINISTRY OF HEALTH', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Hepatitis Auto Approve API Results', 'hepatitis_auto_approve_api_results', 'no', 'hepatitis', 'no', NULL, NULL, 'active'),
('Hepatitis Report QR Code', 'hepatitis_report_qr_code', 'yes', NULL, NULL, NULL, NULL, 'active'),
('Hepatitis Sample Code Format', 'hepatitis_sample_code', 'MMYY', 'hepatitis', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Hepatitis Sample Code Prefix', 'hepatitis_sample_code_prefix', 'HEP', 'hepatitis', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Hepatitis Sample Expiry Days', 'hepatitis_sample_expiry_after_days', '999', 'hepatitis', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Hepatitis Sample Lock Expiry Days', 'hepatitis_sample_lock_after_days', '999', 'hepatitis', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Show Participant Name in Manifest', 'hepatitis_show_participant_name_in_manifest', 'yes', 'HEPATITIS', 'no', NULL, NULL, 'active'),
('Result PDF High Viral Load Message', 'h_vl_msg', '', 'vl', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Import Non matching Sample Results from Machine generated file', 'import_non_matching_sample', 'yes', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Instance Type ', 'instance_type', 'Viral Load Lab', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Key', 'key', NULL, 'general', 'yes', NULL, NULL, 'active'),
('Lock Approved Covid-19 Samples', 'lock_approved_covid19_samples', 'no', 'covid19', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Lock Approved EID Samples', 'lock_approved_eid_samples', 'no', 'eid', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Lock Approved TB Samples', 'lock_approved_tb_samples', 'no', 'tb', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Lock approved VL Samples', 'lock_approved_vl_samples', 'no', 'vl', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Logo', 'logo', NULL, 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Low Viral Load (text results)', 'low_vl_text_results', 'Target Not Detected, TND, < 20, < 40', 'vl', 'yes', '2022-02-18 16:28:05', NULL, 'active'),
('Result PDF Low Viral Load Message', 'l_vl_msg', '', 'vl', 'yes', '2022-02-18 16:28:05', 'daemon', 'active'),
('Manager Email', 'manager_email', '', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Maximum Length', 'max_length', '', 'vl', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Minimum Length', 'min_length', '', 'vl', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Patient Name in Result PDF', 'patient_name_pdf', 'flname', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Result PDF Mandatory Fields', 'r_mandatory_fields', NULL, 'vl', 'yes', '2022-02-18 16:28:05', NULL, 'active'),
('Sample Code', 'sample_code', 'MMYY', 'vl', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Sample Code Prefix', 'sample_code_prefix', 'VL', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Sample Type', 'sample_type', 'enabled', NULL, 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Patient ART No. Date', 'show_date', 'no', 'vl', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Do you want to show emoticons on the result pdf?', 'show_smiley', 'yes', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Support Email', 'support_email', '', 'general', 'no', NULL, '', 'active'),
('Sync Path', 'sync_path', '', 'general', 'no', '2022-02-18 16:28:05', NULL, 'inactive'),
('TB Auto Approve API Results', 'tb_auto_approve_api_results', 'no', 'tb', 'no', NULL, NULL, 'active'),
('TB Maximum Length', 'tb_max_length', NULL, 'tb', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('TB Minimum Length', 'tb_min_length', NULL, 'tb', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('TB Sample Code Format', 'tb_sample_code', 'MMYY', 'tb', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('TB Sample Code Prefix', 'tb_sample_code_prefix', 'TB', 'tb', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('TB Sample Expiry Days', 'tb_sample_expiry_after_days', '999', 'tb', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('TB Sample Lock Expiry Days', 'tb_sample_lock_after_days', '999', 'tb', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Show Participant Name in Manifest', 'tb_show_participant_name_in_manifest', 'yes', 'TB', 'no', NULL, NULL, 'active'),
('Testing Status', 'testing_status', 'enabled', 'vl', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Same user can Review and Approve', 'user_review_approve', 'yes', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Viral Load Threshold Limit', 'viral_load_threshold_limit', '1000', 'vl', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Vldashboard Url', 'vldashboard_url', NULL, 'general', 'yes', '2022-02-18 16:28:05', 'daemon', 'active'),
('VL Auto Approve API Results', 'vl_auto_approve_api_results', 'no', 'vl', 'no', NULL, NULL, 'active'),
('Viral Load Form', 'vl_form', '1', 'general', 'no', '2022-02-18 16:28:05', 'daemon', 'active'),
('Interpret and Convert VL Results', 'vl_interpret_and_convert_results', 'no', 'VL', 'yes', NULL, NULL, 'active'),
('VL Monthly Target', 'vl_monthly_target', 'no', 'vl', 'no', '2022-02-18 16:28:05', '', 'active'),
('VL Report QR Code', 'vl_report_qr_code', 'yes', 'vl', 'no', NULL, NULL, 'active'),
('VL Sample Expiry Days', 'vl_sample_expiry_after_days', '999', 'vl', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('VL Sample Lock Expiry Days', 'vl_sample_lock_after_days', '999', 'vl', 'no', '2022-02-18 16:28:05', NULL, 'active'),
('Show Participant Name in Manifest', 'vl_show_participant_name_in_manifest', 'yes', 'VL', 'no', NULL, NULL, 'active');

-- --------------------------------------------------------

--
-- Table structure for table `health_facilities`
--

CREATE TABLE `health_facilities` (
  `test_type` enum('vl','eid','covid19','hepatitis','tb','generic-tests') NOT NULL,
  `facility_id` int(11) NOT NULL,
  `updated_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `hepatitis_patient_comorbidities`
--

CREATE TABLE `hepatitis_patient_comorbidities` (
  `hepatitis_id` int(11) NOT NULL,
  `comorbidity_id` int(11) NOT NULL,
  `comorbidity_detected` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `hepatitis_risk_factors`
--

CREATE TABLE `hepatitis_risk_factors` (
  `hepatitis_id` int(11) NOT NULL,
  `riskfactors_id` int(11) NOT NULL,
  `riskfactors_detected` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `manual_result_entry` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `instruments`
--

CREATE TABLE `instruments` (
  `config_id` int(11) NOT NULL,
  `machine_name` varchar(255) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `supported_tests` json DEFAULT NULL,
  `import_machine_file_name` varchar(255) DEFAULT NULL,
  `lower_limit` int(11) DEFAULT NULL,
  `higher_limit` int(11) DEFAULT NULL,
  `max_no_of_samples_in_a_batch` int(11) NOT NULL,
  `number_of_in_house_controls` int(11) DEFAULT NULL,
  `number_of_manufacturer_controls` int(11) DEFAULT NULL,
  `number_of_calibrators` int(11) DEFAULT NULL,
  `low_vl_result_text` mediumtext,
  `approved_by` json DEFAULT NULL,
  `reviewed_by` json DEFAULT NULL,
  `status` varchar(45) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `instruments`
--

INSERT INTO `instruments` (`config_id`, `machine_name`, `lab_id`, `supported_tests`, `import_machine_file_name`, `lower_limit`, `higher_limit`, `max_no_of_samples_in_a_batch`, `number_of_in_house_controls`, `number_of_manufacturer_controls`, `number_of_calibrators`, `low_vl_result_text`, `approved_by`, `reviewed_by`, `status`, `updated_datetime`) VALUES
(1, 'Roche', NULL, NULL, 'roche-rwanda.php', 20, 10000000, 21, 0, 3, 0, NULL, NULL, NULL, 'active', NULL),
(2, 'Biomerieux', NULL, NULL, 'biomerieux.php', 30, 10000000, 10, 2, 3, 1, '', NULL, NULL, 'active', NULL),
(3, 'Abbott', NULL, '[\"vl\"]', 'abbott.php', 20, 10000000, 96, 0, 3, 0, '', NULL, NULL, 'active', NULL);

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
-- Dumping data for table `instrument_controls`
--

INSERT INTO `instrument_controls` (`test_type`, `config_id`, `number_of_in_house_controls`, `number_of_manufacturer_controls`, `number_of_calibrators`) VALUES
('covid-19', 3, 0, 0, 0),
('eid', 2, 0, 0, 0),
('eid', 3, 0, 2, 0),
('hepatitis', 2, 0, 0, 0),
('hepatitis', 3, 0, 0, 0),
('vl', 2, 0, 0, 0),
('vl', 3, 0, 3, 0);

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
-- Dumping data for table `instrument_machines`
--

INSERT INTO `instrument_machines` (`config_machine_id`, `config_id`, `config_machine_name`, `date_format`, `file_name`, `poc_device`, `latitude`, `longitude`, `updated_datetime`) VALUES
(1, 1, 'Roche 1', NULL, 'roche-rwanda.php', NULL, NULL, NULL, NULL),
(2, 3, 'Abbott', NULL, 'abbott.php', 'no', '', '', '2022-02-23 11:18:02');

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
  `reason_for_moving` mediumtext,
  `move_approved_by` varchar(255) DEFAULT NULL,
  `list_request_created_datetime` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- --------------------------------------------------------

--
-- Table structure for table `other_config`
--

CREATE TABLE `other_config` (
  `type` varchar(45) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `value` mediumtext
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `other_config`
--

INSERT INTO `other_config` (`type`, `display_name`, `name`, `value`) VALUES
('request', 'Email Id', 'rq_email', 'chublaboratoire@gmail.com'),
('request', 'Email Fields', 'rq_field', 'Sample ID,Urgency,Province,District Name,Clinic Name,Clinician Name,Sample Collection Date,Sample Received Date,Collected by (Initials),Gender,Date Of Birth,Age in years,Age in months,Is Patient Pregnant?,Is Patient Breastfeeding?,Patient OI/ART Number,Date Of ART Initiation,ART Regimen,Patient consent to SMS Notification?,Patient Mobile Number,Date Of Last Viral Load Test,Result Of Last Viral Load,Viral Load Log,Reason For VL Test,Lab Name,LAB No,VL Testing Platform,Specimen type,Sample Testing Date,Viral Load Result(copiesl/ml),Log Value,If no result,Rejection Reason,Reviewed By,Approved By,Laboratory Scientist Comments,Status'),
('request', 'Password', 'rq_password', 'chublabo@123'),
('result', 'Email Id', 'rs_email', 'chublaboratoire@gmail.com'),
('result', 'Email Fields', 'rs_field', 'Sample ID,Urgency,Province,District Name,Clinic Name,Clinician Name,Sample Collection Date,Sample Received Date,Collected by (Initials),Gender,Date Of Birth,Age in years,Age in months,Is Patient Pregnant?,Is Patient Breastfeeding?,Patient OI/ART Number,Date Of ART Initiation,ART Regimen,Patient consent to SMS Notification?,Patient Mobile Number,Date Of Last Viral Load Test,Result Of Last Viral Load,Viral Load Log,Reason For VL Test,Lab Name,LAB No,VL Testing Platform,Specimen type,Sample Testing Date,Viral Load Result(copiesl/ml),Log Value,If no result,Rejection Reason,Reviewed By,Approved By,Laboratory Scientist Comments,Status'),
('result', 'Password', 'rs_password', 'chublabo@123');

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
(9, 'import-config', '/import-configs//import-configs/importConfig.php', 'Access'),
(10, 'import-config', '/import-configs//import-configs/addImportConfig.php', 'Add'),
(11, 'import-config', '/import-configs//import-configs/editImportConfig.php', 'Edit'),
(12, 'vl-requests', '/vl/requests/vlRequest.php', 'View'),
(13, 'vl-requests', '/vl/requests/addVlRequest.php', 'Add'),
(14, 'vl-requests', '/vl/requests/editVlRequest.php', 'Edit'),
(16, 'vl-batch', '/batch/batches.php?type=vl', 'Access'),
(17, 'vl-batch', '/batch/add-batch.php?type=vl', 'Add'),
(18, 'vl-batch', '/batch/edit-batch.php?type=vl', 'Edit'),
(20, 'vl-results', '/vl/results/vlPrintResult.php', 'Print Result PDF'),
(21, 'vl-results', '/vl/results/vlTestResult.php', 'Enter Result Manually'),
(22, 'vl-reports', '/vl/program-management/vl-sample-status.php', 'Sample Status Report'),
(23, 'vl-reports', '/vl/program-management/vl-export-data.php', 'Export VL Data'),
(24, 'home', 'index.php', 'Access'),
(25, 'roles', 'roles.php', 'Access'),
(26, 'roles', 'editRole.php', 'Edit'),
(28, 'test-request-email-config', 'testRequestEmailConfig.php', 'Access'),
(29, 'vl-requests', '/vl/requests/sendRequestToMail.php', 'Send Request to Mail'),
(31, 'vl-results', '/vl/results/vlResultApproval.php', 'Manage VL Result Status (Approve/Reject)'),
(33, 'vl-reports', '/vl/program-management/highViralLoad.php', 'High VL Report'),
(34, 'vl-reports', '/vl/program-management/addContactNotes.php', 'Contact Notes (High VL Reports)'),
(39, 'roles', 'addRole.php', 'Add'),
(40, 'vl-reports', '/vl/program-management/vlTestResultStatus.php', 'Dashboard'),
(41, 'vl-requests', '/vl/requests/patientList.php', 'Export Patient List'),
(43, 'test-request-email-config', 'editTestRequestEmailConfig.php', 'Edit'),
(48, 'test-result-email-config', 'testResultEmailConfig.php', 'Access'),
(49, 'test-result-email-config', 'editTestResultEmailConfig.php', 'Edit'),
(50, 'vl-requests', '/vl/requests/vlRequestMailConfirm.php', 'Email Test Request Confirm'),
(51, 'vl-requests', '/vl/requests/vlResultMailConfirm.php', 'Email Test Result Confirm'),
(56, 'vl-reports', '/vl/program-management/vlWeeklyReport.php', 'VL Weekly Report'),
(57, 'vl-reports', '/vl/program-management/sampleRejectionReport.php', 'Sample Rejection Report'),
(59, 'vl-reports', '/vl/program-management/vlMonitoringReport.php', 'Sample Monitoring Report'),
(62, 'vl-reports', '/vl/program-management/vlRequestRwdForm.php', 'Manage QR Code Rwd Form'),
(63, 'vl-reports', '/vl/program-management/vlControlReport.php', 'Controls Report'),
(64, 'facility', 'addVlFacilityMap.php', 'Add Facility Map'),
(65, 'facility', 'facilityMap.php', 'Access Facility Map'),
(66, 'facility', 'editVlFacilityMap.php', 'Edit Facility Map'),
(70, 'vl-reports', '/vl/program-management/vlResultAllFieldExportInExcel.php', 'Export VL Data in Excel'),
(74, 'eid-requests', '/eid/requests/eid-add-request.php', 'Add'),
(75, 'eid-requests', '/eid/requests/eid-edit-request.php', 'Edit'),
(76, 'eid-requests', '/eid/requests/eid-requests.php', 'View'),
(77, 'eid-batches', '/batch/batches.php?type=eid', 'View Batches'),
(78, 'eid-batches', '/batch/add-batch.php?type=eid', 'Add Batch'),
(79, 'eid-batches', '/batch/edit-batch.php?type=eid', 'Edit Batch'),
(80, 'eid-results', '/eid/results/eid-manual-results.php', 'Enter Result Manually'),
(84, 'eid-results', '/eid/results/eid-result-status.php', 'Manage Result Status'),
(85, 'eid-results', '/eid/results/eid-print-results.php', 'Print Results'),
(86, 'eid-management', '/eid/management/eid-export-data.php', 'Export Data'),
(87, 'eid-management', '/eid/management/eid-sample-rejection-report.php', 'Sample Rejection Report'),
(88, 'eid-management', '/eid/management/eid-sample-status.php', 'Sample Status Report'),
(89, 'vl-requests', '/vl/requests/addSamplesFromManifest.php', 'Add Samples from Manifest'),
(91, 'eid-requests', '/eid/requests/addSamplesFromManifest.php', 'Add Samples from Manifest'),
(95, 'covid-19-requests', '/covid-19/requests/covid-19-add-request.php', 'Add'),
(96, 'covid-19-requests', '/covid-19/requests/covid-19-edit-request.php', 'Edit'),
(97, 'covid-19-requests', '/covid-19/requests/covid-19-requests.php', 'View'),
(98, 'covid-19-results', '/covid-19/results/covid-19-result-status.php', 'Manage Result Status'),
(99, 'covid-19-results', '/covid-19/results/covid-19-print-results.php', 'Print Results'),
(100, 'covid-19-batches', '/batch/batches.php?type=covid19', 'View Batches'),
(101, 'covid-19-batches', '/batch/add-batch.php?type=covid19', 'Add Batch'),
(102, 'covid-19-batches', '/batch/edit-batch.php?type=covid19', 'Edit Batch'),
(103, 'covid-19-results', '/covid-19/results/covid-19-manual-results.php', 'Enter Result Manually'),
(105, 'covid-19-management', '/covid-19/management/covid-19-export-data.php', 'Export Data'),
(106, 'covid-19-management', '/covid-19/management/covid-19-sample-rejection-report.php', 'Sample Rejection Report'),
(107, 'covid-19-management', '/covid-19/management/covid-19-sample-status.php', 'Sample Status Report'),
(108, 'covid-19-requests', '/covid-19/requests/record-final-result.php', 'Record Final Result'),
(109, 'covid-19-requests', '/covid-19/requests/can-record-confirmatory-tests.php', 'Can Record Confirmatory Tests'),
(110, 'covid-19-requests', '/covid-19/requests/update-record-confirmatory-tests.php', 'Update Record Confirmatory Tests'),
(111, 'covid-19-batches', 'covid-19-confirmation-manifest.php', 'Covid-19 Confirmation Manifest'),
(112, 'covid-19-batches', 'covid-19-add-confirmation-manifest.php', 'Add New Confirmation Manifest'),
(113, 'covid-19-batches', 'generate-confirmation-manifest.php', 'Generate Positive Confirmation Manifest'),
(114, 'covid-19-batches', 'covid-19-edit-confirmation-manifest.php', 'Edit Positive Confirmation Manifest'),
(121, 'eid-management', '/eid/management/eid-clinic-report.php', 'EID Clinic Reports'),
(122, 'covid-19-management', '/covid-19/management/covid-19-clinic-report.php', 'Covid-19 Clinic Reports'),
(123, 'covid-19-reference', '/covid-19/reference/covid19-sample-type.php', 'Manage Reference'),
(124, 'covid-19-reference', '/covid-19/reference/covid19-comorbidities.php', 'Manage Comorbidities'),
(125, 'covid-19-reference', '/covid-19/reference/addCovid19Comorbidities.php', 'Add Comorbidities'),
(126, 'covid-19-reference', '/covid-19/reference/editCovid19Comorbidities.php', 'Edit Comorbidities'),
(127, 'covid-19-reference', '/covid-19/reference/covid19-sample-rejection-reasons.php', 'Manage Sample Rejection Reasons'),
(128, 'covid-19-reference', '/covid-19/reference/addCovid19SampleRejectionReason.php', 'Add Sample Rejection Reason'),
(129, 'covid-19-reference', '/covid-19/reference/editCovid19SampleRejectionReason.php', 'Edit Sample Rejection Reason'),
(130, 'vl-reference', '/vl/reference/vl-art-code-details.php', 'Manage VL Reference Tables'),
(131, 'eid-reference', '/eid/reference/eid-sample-type.php', 'Manage EID Reference Tables'),
(139, 'common-reference', 'province-details.php', 'Manage Common Reference Tables'),
(140, 'vl-requests', '/vl/requests/edit-locked-vl-samples', 'Edit Locked VL Samples'),
(141, 'eid-requests', '/eid/requests/edit-locked-eid-samples', 'Edit Locked EID Samples'),
(142, 'covid-19-requests', '/covid-19/requests/edit-locked-covid19-samples', 'Edit Locked Covid-19 Samples'),
(143, 'vl-reports', '/vl/program-management/vlMonthlyThresholdReport.php', 'Monthly Threshold Report'),
(144, 'eid-management', '/eid/management/eidMonthlyThresholdReport.php', 'Monthly Threshold Report'),
(145, 'covid-19-management', '/covid-19/management/covid19MonthlyThresholdReport.php', 'Monthly Threshold Report'),
(152, 'hepatitis-requests', '/hepatitis/requests/hepatitis-requests.php', 'View'),
(153, 'hepatitis-requests', '/hepatitis/requests/hepatitis-add-request.php', 'Add'),
(154, 'hepatitis-requests', '/hepatitis/requests/hepatitis-edit-request.php', 'Edit'),
(164, 'hepatitis-results', '/hepatitis/results/hepatitis-manual-results.php', 'Enter Result Manually'),
(165, 'hepatitis-results', '/hepatitis/results/hepatitis-print-results.php', 'Print Results'),
(166, 'hepatitis-results', '/hepatitis/results/hepatitis-result-status.php', 'Manage Result Status'),
(167, 'hepatitis-reference', '/hepatitis/reference/hepatitis-sample-type.php', 'Manage Hepatitis Reference'),
(168, 'vl-reports', '/vl/program-management/vlSuppressedTargetReport.php', 'Suppressed Target report'),
(169, 'hepatitis-batches', '/batch/batches.php?type=hepatitis', 'View Batches'),
(170, 'hepatitis-batches', '/batch/add-batch.php?type=hepatitis', 'Add Batch'),
(171, 'hepatitis-batches', '/batch/edit-batch.php?type=hepatitis', 'Edit Batch'),
(174, 'hepatitis-requests', '/hepatitis/requests/add-samples-from-manifest.php', 'Add Samples from Manifest'),
(176, 'hepatitis-management', '/hepatitis/management/hepatitis-clinic-report.php', 'Hepatitis Clinic Reports'),
(177, 'hepatitis-management', '/hepatitis/management/hepatitis-testing-target-report.php', 'Hepatitis Testing Target Reports'),
(178, 'hepatitis-management', '/hepatitis/management/hepatitis-sample-rejection-report.php', 'Hepatitis Sample Rejection Reports'),
(179, 'hepatitis-management', '/hepatitis/management/hepatitis-sample-status.php', 'Hepatitis Sample Status Reports'),
(180, 'covid-19-requests', '/covid-19/requests/addSamplesFromManifest.php', 'Add Samples from Manifest'),
(181, 'covid-19-requests', '/covid-19/requests/covid-19-dhis2.php', 'DHIS2'),
(182, 'covid-19-requests', '/covid-19/requests/covid-19-sync-request.php', 'Covid-19 Sync Request'),
(183, 'common-reference', 'geographical-divisions-details.php', 'Manage Geographical Divisions'),
(184, 'common-reference', 'add-geographical-divisions.php', 'Add Geographical Divisions'),
(185, 'common-reference', 'edit-geographical-divisions.php', 'Edit Geographical Divisions'),
(186, 'hepatitis-requests', '/hepatitis/requests/hepatitis-dhis2.php', 'DHIS2'),
(187, 'common-reference', 'sync-history.php', 'Sync History'),
(188, 'hepatitis-management', '/hepatitis/management/hepatitis-export-data.php', 'Hepatitis Export'),
(189, 'tb-requests', '/tb/requests/tb-requests.php', 'View'),
(190, 'tb-requests', '/tb/requests/tb-add-request.php', 'Add'),
(191, 'move-samples', 'move-samples.php', 'Access'),
(192, 'move-samples', 'select-samples-to-move.php', 'Add Move Samples'),
(193, 'tb-requests', '/tb/requests/tb-edit-request.php', 'Edit'),
(194, 'tb-results', '/tb/results/tb-manual-results.php', 'Enter Result Manually'),
(195, 'tb-results', '/tb/results/tb-print-results.php', 'Print Results'),
(196, 'tb-results', '/tb/results/tb-result-status.php', 'Manage Result Status'),
(197, 'tb-management', '/tb/management/tb-sample-type.php', 'Manage Reference'),
(198, 'tb-management', '/tb/management/tb-export-data.php', 'Export Data'),
(199, 'tb-management', '/tb/management//batch/batches.php?type=tb', 'View Batches'),
(200, 'tb-management', '/tb/management//batch/add-batch.php?type=tb', 'Add Batch'),
(201, 'tb-management', '/tb/management//batch/edit-batch.php?type=tb', 'Edit Batch'),
(204, 'tb-requests', '/tb/requests/addSamplesFromManifest.php', 'Add Samples from Manifest'),
(205, 'tb-management', '/tb/management/tb-sample-status.php', 'Sample Status Report'),
(206, 'tb-management', '/tb/management/tb-sample-rejection-report.php', 'Sample Rejection Report'),
(207, 'tb-management', '/tb/management/tb-clinic-report.php', 'TB Clinic Report'),
(208, 'common-reference', 'activity-log.php', 'User Activity Log'),
(209, 'vl-requests', '/vl/requests/export-vl-requests.php', 'Export VL Requests'),
(210, 'eid-requests', '/eid/requests/export-eid-requests.php', 'Export EID Requests'),
(211, 'covid-19-requests', '/covid-19/requests/export-covid19-requests.php', 'Export Covid-19 Requests '),
(212, 'hepatitis-requests', '/hepatitis/requests/export-hepatitis-requests.php', 'Export Hepatitis Requests'),
(213, 'tb-requests', '/tb/requests/export-tb-requests.php', 'Export TB Requests'),
(219, 'common-reference', 'api-sync-history.php', 'API Sync History'),
(220, 'common-reference', 'sources-of-requests.php', 'Sources of Requests Report'),
(221, 'covid-19-results', '/covid-19/results/covid-19-qc-data.php', 'Covid-19 QC Data'),
(222, 'covid-19-results', '/covid-19/results/add-covid-19-qc-data.php', 'Add Covid-19 QC Data'),
(223, 'covid-19-results', '/covid-19/results/edit-covid-19-qc-data.php', 'Edit Covid-19 QC Data'),
(224, 'common-reference', 'audit-trail.php', 'Audit Trail'),
(225, 'vl-reference', '/vl/reference/vl-results.php', 'Manage VL Results'),
(226, 'common-reference', 'sync-status.php', 'Sync Status'),
(230, 'test-type', 'testType.php', 'Access'),
(231, 'test-type', 'add-test-type.php', 'Add'),
(232, 'test-type', 'edit-test-type.php', 'Edit Test Type'),
(236, 'common-sample-type', 'addSampleType.php', 'Add'),
(237, 'common-sample-type', 'sampleType.php', 'Access'),
(238, 'common-sample-type', 'editSampleType.php', 'Edit'),
(239, 'common-testing-reason', 'testingReason.php', 'Access'),
(240, 'common-testing-reason', 'editTestingReason.php', 'Edit'),
(241, 'common-testing-reason', 'addTestingReason.php', 'Add'),
(242, 'common-symptoms', 'symptoms.php', 'Access'),
(243, 'common-symptoms', 'addSymptoms.php', 'Add'),
(244, 'common-symptoms', 'editSymptoms.php', 'Edit'),
(245, 'generic-requests', '/generic-tests/requests/view-requests.php', 'View Generic Tests'),
(246, 'generic-requests', '/generic-tests/requests/add-request.php', 'Add Generic Tests'),
(247, 'generic-requests', '/generic-tests/requests/add-samples-from-manifest.php', 'Add Samples From Manifest'),
(252, 'generic-requests', '/generic-tests/requests/edit-request.php', 'Edit Generic Tests'),
(277, 'generic-results', '/generic-tests/results/generic-test-results.php', 'Manage Test Results'),
(278, 'generic-results', '/generic-tests/results/generic-failed-results.php', 'Manage Failed Results'),
(279, 'generic-results', '/generic-tests/results/generic-result-approval.php', 'Approve Test Results'),
(280, 'generic-management', '/generic-tests/program-management/generic-sample-status.php', 'Sample Status Report'),
(281, 'generic-management', '/generic-tests/program-management/generic-export-data.php', 'Export Report in Excel'),
(282, 'generic-management', '/generic-tests/results/generic-print-result.php', 'Export Report in PDF'),
(283, 'generic-management', '/generic-tests/program-management/sample-rejection-report.php', 'Sample Rejection Report'),
(284, 'generic-management', '/generic-tests/program-management/generic-monthly-threshold-report.php', 'Monthly Threshold Report'),
(300, 'vl-reference', '/vl/reference/add-vl-results.php', 'Add VL Result Types'),
(301, 'vl-reference', '/vl/reference/edit-vl-results.php', 'Edit VL Result Types'),
(317, 'vl-results', '/import-result/import-file.php?t=vl', 'Import Result from Files'),
(318, 'eid-results', '/import-result/import-file.php?t=eid', 'Import Result from Files'),
(319, 'covid-19-results', '/covid-19/results//import-result/import-file.php?t=covid19', 'Import Result from Files'),
(320, 'hepatitis-results', '/import-result/import-file.php?t=hepatitis', 'Import Result from Files'),
(321, 'tb-results', '/import-result/import-file.php?t=tb', 'Import Result from Files'),
(322, 'generic-results', '/import-result/import-file.php?t=generic-tests', 'Import Result from Files'),
(323, 'vl-requests', '/specimen-referral-manifest/view-manifests.php?t=vl', 'View VL Manifests'),
(324, 'eid-requests', '/specimen-referral-manifest/view-manifests.php?t=eid', 'View EID Manifests'),
(325, 'covid-19-requests', '/specimen-referral-manifest/view-manifests.php?t=covid19', 'View COVID-19 Manifests'),
(326, 'hepatitis-requests', '/specimen-referral-manifest/view-manifests.php?t=hepatitis', 'View Hepatitis Manifests'),
(327, 'tb-requests', '/specimen-referral-manifest/view-manifests.php?t=tb', 'View TB Manifests'),
(328, 'generic-requests', '/specimen-referral-manifest/view-manifests.php?t=generic-tests', 'View Lab Tests Manifests'),
(329, 'vl-requests', '/specimen-referral-manifest/add-manifest.php?t=vl', 'Add VL Manifests'),
(330, 'eid-requests', '/specimen-referral-manifest/add-manifest.php?t=eid', 'Add EID Manifests'),
(331, 'covid-19-requests', '/specimen-referral-manifest/add-manifest.php?t=covid19', 'Add COVID-19 Manifests'),
(332, 'hepatitis-requests', '/specimen-referral-manifest/add-manifest.php?t=hepatitis', 'Add Hepatitis Manifests'),
(333, 'tb-requests', '/specimen-referral-manifest/add-manifest.php?t=tb', 'Add TB Manifests'),
(334, 'generic-requests', '/specimen-referral-manifest/add-manifest.php?t=generic-tests', 'Add Lab Tests Manifests'),
(335, 'vl-requests', '/specimen-referral-manifest/edit-manifest.php?t=vl', 'Edit VL Manifests'),
(336, 'eid-requests', '/specimen-referral-manifest/edit-manifest.php?t=eid', 'Edit EID Manifests'),
(337, 'covid-19-requests', '/specimen-referral-manifest/edit-manifest.php?t=covid19', 'Edit COVID-19 Manifests'),
(338, 'hepatitis-requests', '/specimen-referral-manifest/edit-manifest.php?t=hepatitis', 'Edit Hepatitis Manifests'),
(339, 'tb-requests', '/specimen-referral-manifest/edit-manifest.php?t=tb', 'Edit TB Manifests'),
(340, 'generic-requests', '/specimen-referral-manifest/edit-manifest.php?t=generic-tests', 'Edit Lab Tests Manifests'),
(341, 'vl-requests', '/specimen-referral-manifest/move-manifest.php?t=vl', 'Move VL Manifests'),
(342, 'eid-requests', '/specimen-referral-manifest/move-manifest.php?t=eid', 'Move EID Manifests'),
(343, 'covid-19-requests', '/specimen-referral-manifest/move-manifest.php?t=covid19', 'Move COVID-19 Manifests'),
(344, 'hepatitis-requests', '/specimen-referral-manifest/move-manifest.php?t=hepatitis', 'Move Hepatitis Manifests'),
(345, 'tb-requests', '/specimen-referral-manifest/move-manifest.php?t=tb', 'Move TB Manifests'),
(346, 'generic-requests', '/specimen-referral-manifest/move-manifest.php?t=generic-tests', 'Move Lab Tests Manifests'),
(347, 'generic-tests-config', '/generic-tests/configuration/test-type.php', 'Add/Edit Test Types'),
(348, 'generic-tests-config', '/generic-tests/configuration/sample-types/generic-sample-type.php', 'Manage Sample Types'),
(349, 'generic-tests-config', '/generic-tests/configuration/testing-reasons/generic-testing-reason.php', 'Manage Testing Reasons'),
(350, 'generic-tests-config', '/generic-tests/configuration/symptoms/generic-symptoms.php', 'Manage Symptoms'),
(351, 'generic-tests-config', '/generic-tests/configuration/sample-rejection-reasons/generic-sample-rejection-reasons.php', 'Manage Sample Rejection Reasons'),
(352, 'generic-tests-config', '/generic-tests/configuration/test-failure-reasons/generic-test-failure-reason.php', 'Manage Test Failure Reasons'),
(353, 'generic-tests-config', '/generic-tests/configuration/test-result-units/generic-test-result-units.php', 'Manage Test Result Units'),
(354, 'generic-tests-config', '/generic-tests/configuration/test-methods/generic-test-methods.php', 'Manage Test Methods'),
(355, 'generic-tests-config', '/generic-tests/configuration/test-categories/generic-test-categories.php', 'Manage Test Categories'),
(356, 'generic-tests-batches', '/batch/batches.php?type=generic-tests', 'Manage Batch'),
(357, 'generic-tests-batches', '/batch/add-batch.php?type=generic-tests', 'Add New Batch'),
(358, 'generic-tests-batches', '/batch/edit-batch.php?type=generic-tests', 'Edit Batch');

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
-- Dumping data for table `province_details`
--

INSERT INTO `province_details` (`province_id`, `province_name`, `province_code`, `updated_datetime`, `data_sync`) VALUES
(3, 'Eastern', 'ES', '2018-01-17 15:31:19', 1),
(7, 'Northern', 'NR', '2018-01-17 15:31:19', 1),
(9, 'Southern', 'SO', '2018-01-17 15:31:19', 1),
(10, 'Western', 'WE', '2018-01-17 15:31:19', 1),
(11, 'Kigali City', 'KC', '2019-05-23 12:32:22', 0);

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
('generic-management', 'generic-tests', 'Lab Tests Report Management'),
('generic-requests', 'generic-tests', 'Lab Tests Request Management'),
('generic-results', 'generic-tests', 'Lab Tests Result Management'),
('generic-tests-batches', 'generic-tests', 'Lab Tests Batch Management'),
('generic-tests-config', 'admin', 'Configure Generic Lab Tests'),
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
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`role_id`, `role_name`, `role_code`, `status`, `access_type`, `landing_page`) VALUES
(1, 'Admin', 'AD', 'active', 'testing-lab', '/dashboard/index.php'),
(2, 'Remote Order', 'REMOTEORDER', 'active', 'collection-site', '/dashboard/index.php'),
(3, 'Lab Technician', 'LABTECH', 'active', NULL, '/dashboard/index.php'),
(4, 'API User', 'API', 'active', 'testing-lab', NULL);

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
-- Dumping data for table `roles_privileges_map`
--

INSERT INTO `roles_privileges_map` (`map_id`, `role_id`, `privilege_id`) VALUES
(3181, 3, 4),
(3182, 3, 28),
(3183, 3, 43),
(3184, 3, 48),
(3185, 3, 24),
(3186, 3, 80),
(3188, 3, 16),
(3189, 3, 17),
(3191, 3, 18),
(3193, 3, 23),
(3194, 3, 34),
(3195, 3, 40),
(3196, 3, 33),
(3197, 3, 59),
(3198, 3, 57),
(3199, 3, 22),
(3200, 3, 56),
(3201, 3, 12),
(3202, 3, 13),
(3203, 3, 14),
(3205, 3, 50),
(3207, 3, 51),
(3208, 3, 41),
(3209, 3, 29),
(3211, 3, 21),
(3213, 3, 31),
(3214, 3, 20),
(4555, 2, 24),
(4559, 2, 121),
(4560, 2, 86),
(4561, 2, 144),
(4562, 2, 87),
(4563, 2, 88),
(4564, 2, 74),
(4565, 2, 91),
(4566, 2, 75),
(4567, 2, 141),
(4568, 2, 210),
(4569, 2, 76),
(4570, 2, 80),
(4572, 2, 84),
(4573, 2, 85),
(4574, 2, 176),
(4575, 2, 178),
(4576, 2, 179),
(4577, 2, 153),
(4578, 2, 166),
(4579, 2, 165),
(4580, 2, 152),
(4581, 2, 18),
(4582, 2, 34),
(4583, 2, 40),
(4584, 2, 23),
(4585, 2, 70),
(4586, 2, 33),
(4587, 2, 143),
(4588, 2, 59),
(4589, 2, 57),
(4590, 2, 22),
(4591, 2, 56),
(4592, 2, 13),
(4593, 2, 89),
(4594, 2, 14),
(4595, 2, 140),
(4596, 2, 12),
(4597, 2, 20),
(5024, 1, 184),
(5025, 1, 219),
(5026, 1, 224),
(5027, 1, 185),
(5028, 1, 139),
(5029, 1, 183),
(5030, 1, 220),
(5031, 1, 187),
(5032, 1, 226),
(5033, 1, 208),
(5034, 1, 125),
(5035, 1, 128),
(5036, 1, 126),
(5037, 1, 129),
(5038, 1, 124),
(5039, 1, 123),
(5040, 1, 127),
(5041, 1, 131),
(5042, 1, 167),
(5043, 1, 4),
(5044, 1, 65),
(5045, 1, 5),
(5046, 1, 64),
(5047, 1, 6),
(5048, 1, 66),
(5049, 1, 7),
(5050, 1, 8),
(5051, 1, 9),
(5052, 1, 10),
(5053, 1, 11),
(5054, 1, 25),
(5055, 1, 39),
(5056, 1, 26),
(5057, 1, 28),
(5058, 1, 43),
(5059, 1, 48),
(5060, 1, 49),
(5061, 1, 230),
(5062, 1, 231),
(5063, 1, 232),
(5064, 1, 1),
(5065, 1, 2),
(5066, 1, 3),
(5067, 1, 130),
(5068, 1, 225),
(5069, 1, 24),
(5075, 1, 191),
(5076, 1, 192),
(5077, 1, 78),
(5078, 1, 79),
(5079, 1, 77),
(5080, 1, 121),
(5081, 1, 86),
(5082, 1, 144),
(5083, 1, 87),
(5084, 1, 88),
(5085, 1, 74),
(5086, 1, 91),
(5087, 1, 75),
(5088, 1, 141),
(5089, 1, 210),
(5090, 1, 76),
(5091, 1, 80),
(5093, 1, 84),
(5094, 1, 85),
(5095, 1, 170),
(5097, 1, 171),
(5099, 1, 169),
(5100, 1, 176),
(5101, 1, 188),
(5102, 1, 178),
(5103, 1, 179),
(5104, 1, 177),
(5105, 1, 153),
(5106, 1, 174),
(5107, 1, 186),
(5108, 1, 154),
(5109, 1, 212),
(5110, 1, 166),
(5111, 1, 165),
(5112, 1, 152),
(5113, 1, 164),
(5114, 1, 16),
(5115, 1, 17),
(5117, 1, 18),
(5119, 1, 34),
(5120, 1, 63),
(5121, 1, 40),
(5122, 1, 23),
(5123, 1, 70),
(5124, 1, 33),
(5125, 1, 62),
(5126, 1, 143),
(5127, 1, 59),
(5128, 1, 57),
(5129, 1, 22),
(5130, 1, 168),
(5131, 1, 56),
(5132, 1, 13),
(5133, 1, 89),
(5134, 1, 14),
(5135, 1, 140),
(5137, 1, 50),
(5139, 1, 51),
(5140, 1, 41),
(5141, 1, 209),
(5142, 1, 29),
(5143, 1, 12),
(5144, 1, 21),
(5145, 1, 31),
(5146, 1, 20);

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
-- Dumping data for table `r_covid19_comorbidities`
--

INSERT INTO `r_covid19_comorbidities` (`comorbidity_id`, `comorbidity_name`, `comorbidity_status`, `updated_datetime`) VALUES
(1, 'Cardiovascular Disease', 'active', '2022-02-18 16:25:07'),
(2, 'Asthma', 'active', '2022-02-18 16:25:07'),
(3, 'Chronic Respiratory Disease', 'active', '2022-02-18 16:25:07'),
(4, 'Diabetes', 'active', '2022-02-18 16:25:07'),
(5, 'Chronic Liver Disease', 'active', '2022-02-18 16:25:07'),
(6, 'Chronic Kidney Disease', 'active', '2022-02-18 16:25:07'),
(7, 'HIV', 'active', '2022-02-18 16:25:07'),
(8, 'Hypertension', 'active', '2022-02-18 16:25:07'),
(9, 'Cancer', 'active', '2022-02-18 16:25:07');

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
-- Dumping data for table `r_covid19_results`
--

INSERT INTO `r_covid19_results` (`result_id`, `result`, `status`, `updated_datetime`, `data_sync`) VALUES
('indeterminate', 'Indeterminate', 'active', '2022-02-18 16:25:07', 0),
('negative', 'Negative', 'active', '2022-02-18 16:25:07', 0),
('positive', 'Positive', 'active', '2022-02-18 16:25:07', 0);

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
-- Dumping data for table `r_covid19_sample_rejection_reasons`
--

INSERT INTO `r_covid19_sample_rejection_reasons` (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`, `rejection_reason_status`, `rejection_reason_code`, `updated_datetime`, `data_sync`) VALUES
(1, 'Poorly labelled specimen', 'general', 'active', 'Gen_PLSP', '2022-02-18 16:25:07', 0),
(2, 'Mismatched sample and form labeling', 'general', 'active', 'Gen_MMSP', '2022-02-18 16:25:07', 0),
(3, 'Missing labels on container or tracking form', 'general', 'active', 'Gen_MLTS', '2022-02-18 16:25:07', 0),
(4, 'Sample without request forms/Tracking forms', 'general', 'active', 'Gen_SMRT', '2022-02-18 16:25:07', 0),
(5, 'Name/Information of requester is missing', 'general', 'active', 'Gen_NIRM', '2022-02-18 16:25:07', 0),
(6, 'Missing information on request form - Age', 'general', 'active', 'Gen_MIRA', '2022-02-18 16:25:07', 0),
(7, 'Missing information on request form - Sex', 'general', 'active', 'Gen_MIRS', '2022-02-18 16:25:07', 0),
(8, 'Missing information on request form - Sample Collection Date', 'general', 'active', 'Gen_MIRD', '2022-02-18 16:25:07', 0),
(9, 'Missing information on request form - ART No', 'general', 'active', 'Gen_MIAN', '2022-02-18 16:25:07', 0),
(10, 'Inappropriate specimen packing', 'general', 'active', 'Gen_ISPK', '2022-02-18 16:25:07', 0),
(11, 'Inappropriate specimen for test request', 'general', 'active', 'Gen_ISTR', '2022-02-18 16:25:07', 0),
(12, 'Form received without Sample', 'general', 'active', 'Gen_NoSample', '2022-02-18 16:25:07', 0),
(13, 'VL Machine Flag', 'testing', 'active', 'FLG_', '2022-02-18 16:25:07', 0),
(14, 'CNTRL_FAIL', 'testing', 'active', 'FLG_AL00', '2022-02-18 16:25:07', 0),
(15, 'SYS_ERROR', 'testing', 'active', 'FLG_TM00', '2022-02-18 16:25:07', 0),
(16, 'A/D_ABORT', 'testing', 'active', 'FLG_TM17', '2022-02-18 16:25:07', 0),
(17, 'KIT_EXPIRY', 'testing', 'active', 'FLG_TMAP', '2022-02-18 16:25:07', 0),
(18, 'RUN_EXPIRY', 'testing', 'active', 'FLG_TM19', '2022-02-18 16:25:07', 0),
(19, 'DATA_ERROR', 'testing', 'active', 'FLG_TM20', '2022-02-18 16:25:07', 0),
(20, 'NC_INVALID', 'testing', 'active', 'FLG_TM24', '2022-02-18 16:25:07', 0),
(21, 'LPCINVALID', 'testing', 'active', 'FLG_TM25', '2022-02-18 16:25:07', 0),
(22, 'MPCINVALID', 'testing', 'active', 'FLG_TM26', '2022-02-18 16:25:07', 0),
(23, 'HPCINVALID', 'testing', 'active', 'FLG_TM27', '2022-02-18 16:25:07', 0),
(24, 'S_INVALID', 'testing', 'active', 'FLG_TM29', '2022-02-18 16:25:07', 0),
(25, 'MATH_ERROR', 'testing', 'active', 'FLG_TM31', '2022-02-18 16:25:07', 0),
(26, 'PRECHECK', 'testing', 'active', 'FLG_TM44 ', '2022-02-18 16:25:07', 0),
(27, 'QS_INVALID', 'testing', 'active', 'FLG_TM50', '2022-02-18 16:25:07', 0),
(28, 'POSTCHECK', 'testing', 'active', 'FLG_TM51', '2022-02-18 16:25:07', 0),
(29, 'REAG_ERROR', 'testing', 'active', 'FLG_AP02 ', '2022-02-18 16:25:07', 0),
(30, 'NO_SAMPLE', 'testing', 'active', 'FLG_AP12', '2022-02-18 16:25:07', 0),
(31, 'DISP_ERROR', 'testing', 'active', 'FLG_AP13 ', '2022-02-18 16:25:07', 0),
(32, 'TEMP_RANGE', 'testing', 'active', 'FLG_AP19 ', '2022-02-18 16:25:07', 0),
(33, 'PREP_ABORT', 'testing', 'active', 'FLG_AP24', '2022-02-18 16:25:07', 0),
(34, 'SAMPLECLOT', 'testing', 'active', 'FLG_AP25', '2022-02-18 16:25:07', 0);

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
-- Dumping data for table `r_covid19_sample_type`
--

INSERT INTO `r_covid19_sample_type` (`sample_id`, `sample_name`, `status`, `updated_datetime`, `data_sync`) VALUES
(1, 'Nasopharyngeal (NP)', 'active', '2022-02-18 16:25:07', 0),
(2, 'Oral-pharyngeal (OP)', 'active', '2022-02-18 16:25:07', 0),
(3, 'Both NP and OP', 'active', '2022-02-18 16:25:07', 0),
(4, 'Sputum', 'active', '2022-02-18 16:25:07', 0),
(5, 'Tracheal aspirate', 'active', '2022-02-18 16:25:07', 0),
(6, 'Nasal wash', 'active', '2022-02-18 16:25:07', 0),
(7, 'Serum', 'active', '2022-02-18 16:25:07', 0),
(8, 'Lung Tissue', 'active', '2022-02-18 16:25:07', 0),
(9, 'Whole blood', 'active', '2022-02-18 16:25:07', 0),
(10, 'Urine', 'active', '2022-02-18 16:25:07', 0),
(11, 'Stool', 'active', '2022-02-18 16:25:07', 0),
(12, 'Bronchoalveolar lavage', 'active', '2022-02-18 16:25:07', 0);

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
-- Dumping data for table `r_covid19_symptoms`
--

INSERT INTO `r_covid19_symptoms` (`symptom_id`, `symptom_name`, `parent_symptom`, `symptom_status`, `updated_datetime`) VALUES
(1, 'Cough', NULL, 'active', '2022-02-18 16:25:07'),
(2, 'Shortness of Breath', NULL, 'active', '2022-02-18 16:25:07'),
(3, 'Sore Throat', NULL, 'active', '2022-02-18 16:25:07'),
(4, 'Chills', NULL, 'active', '2022-02-18 16:25:07'),
(5, 'Headache', NULL, 'active', '2022-02-18 16:25:07'),
(6, 'Muscles ache', NULL, 'active', '2022-02-18 16:25:07'),
(7, 'Vomiting/Nausea', NULL, 'active', '2022-02-18 16:25:07'),
(8, 'Abdominal Pain', NULL, 'active', '2022-02-18 16:25:07'),
(9, 'Diarrhoea', NULL, 'active', '2022-02-18 16:25:07');

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
-- Dumping data for table `r_covid19_test_reasons`
--

INSERT INTO `r_covid19_test_reasons` (`test_reason_id`, `test_reason_name`, `parent_reason`, `test_reason_status`, `updated_datetime`) VALUES
(1, 'Suspect Case', NULL, 'active', '2022-02-18 16:25:07'),
(2, 'Asymptomatic Person who has been in contact with suspect/confirmed case', NULL, 'active', '2022-02-18 16:25:07'),
(3, 'Asymptomatic Person who has travelled to a country/area with confirmed Covid-19 Cases', NULL, 'active', '2022-02-18 16:25:07'),
(4, 'General Screening', NULL, 'active', '2022-02-18 16:25:07'),
(5, 'Control Test', NULL, 'active', '2022-02-18 16:25:07');

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
-- Dumping data for table `r_eid_results`
--

INSERT INTO `r_eid_results` (`result_id`, `result`, `status`, `updated_datetime`, `data_sync`) VALUES
('indeterminate', 'Indeterminate', 'active', NULL, 0),
('negative', 'Negative', 'active', NULL, 0),
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
-- Dumping data for table `r_eid_sample_rejection_reasons`
--

INSERT INTO `r_eid_sample_rejection_reasons` (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`, `rejection_reason_status`, `rejection_reason_code`, `updated_datetime`, `data_sync`) VALUES
(1, 'Poorly labelled specimen', 'general', 'active', 'Gen_PLSP', '2022-02-18 16:25:07', 0),
(2, 'Mismatched sample and form labeling', 'general', 'active', 'Gen_MMSP', '2022-02-18 16:25:07', 0),
(3, 'Missing labels on container or tracking form', 'general', 'active', 'Gen_MLTS', '2022-02-18 16:25:07', 0),
(4, 'Sample without request forms/Tracking forms', 'general', 'active', 'Gen_SMRT', '2022-02-18 16:25:07', 0),
(5, 'Name/Information of requester is missing', 'general', 'active', 'Gen_NIRM', '2022-02-18 16:25:07', 0),
(6, 'Missing information on request form - Age', 'general', 'active', 'Gen_MIRA', '2022-02-18 16:25:07', 0),
(7, 'Missing information on request form - Sex', 'general', 'active', 'Gen_MIRS', '2022-02-18 16:25:07', 0),
(8, 'Missing information on request form - Sample Collection Date', 'general', 'active', 'Gen_MIRD', '2022-02-18 16:25:07', 0),
(9, 'Missing information on request form - ART No', 'general', 'active', 'Gen_MIAN', '2022-02-18 16:25:07', 0),
(10, 'Inappropriate specimen packing', 'general', 'active', 'Gen_ISPK', '2022-02-18 16:25:07', 0),
(11, 'Inappropriate specimen for test request', 'general', 'active', 'Gen_ISTR', '2022-02-18 16:25:07', 0),
(12, 'Wrong container/anticoagulant used', 'whole blood', 'active', 'BLD_WCAU', '2022-02-18 16:25:07', 0),
(13, 'EDTA tube specimens that arrived hemolyzed', 'whole blood', 'active', 'BLD_HMLY', '2022-02-18 16:25:07', 0),
(14, 'ETDA tube that arrives more than 24 hours after specimen collection', 'whole blood', 'active', 'BLD_AASC', '2022-02-18 16:25:07', 0),
(15, 'Plasma that arrives at a temperature above 8 C', 'plasma', 'active', 'PLS_AATA', '2022-02-18 16:25:07', 0),
(16, 'Plasma tube contain less than 1.5 mL', 'plasma', 'active', 'PSL_TCLT', '2022-02-18 16:25:07', 0),
(17, 'DBS cards with insufficient blood spots', 'dbs', 'active', 'DBS_IFBS', '2022-02-18 16:25:07', 0),
(18, 'DBS card with clotting present in spots', 'dbs', 'active', 'DBS_CPIS', '2022-02-18 16:25:07', 0),
(19, 'DBS cards that have serum rings indicating contamination around spots', 'dbs', 'active', 'DBS_SRIC', '2022-02-18 16:25:07', 0),
(20, 'VL Machine Flag', 'testing', 'active', 'FLG_', '2022-02-18 16:25:07', 0),
(21, 'CNTRL_FAIL', 'testing', 'active', 'FLG_AL00', '2022-02-18 16:25:07', 0),
(22, 'SYS_ERROR', 'testing', 'active', 'FLG_TM00', '2022-02-18 16:25:07', 0),
(23, 'A/D_ABORT', 'testing', 'active', 'FLG_TM17', '2022-02-18 16:25:07', 0),
(24, 'KIT_EXPIRY', 'testing', 'active', 'FLG_TMAP', '2022-02-18 16:25:07', 0),
(25, 'RUN_EXPIRY', 'testing', 'active', 'FLG_TM19', '2022-02-18 16:25:07', 0),
(26, 'DATA_ERROR', 'testing', 'active', 'FLG_TM20', '2022-02-18 16:25:07', 0),
(27, 'NC_INVALID', 'testing', 'active', 'FLG_TM24', '2022-02-18 16:25:07', 0),
(28, 'LPCINVALID', 'testing', 'active', 'FLG_TM25', '2022-02-18 16:25:07', 0),
(29, 'MPCINVALID', 'testing', 'active', 'FLG_TM26', '2022-02-18 16:25:07', 0),
(30, 'HPCINVALID', 'testing', 'active', 'FLG_TM27', '2022-02-18 16:25:07', 0),
(31, 'S_INVALID', 'testing', 'active', 'FLG_TM29', '2022-02-18 16:25:07', 0),
(32, 'MATH_ERROR', 'testing', 'active', 'FLG_TM31', '2022-02-18 16:25:07', 0),
(33, 'PRECHECK', 'testing', 'active', 'FLG_TM44 ', '2022-02-18 16:25:07', 0),
(34, 'QS_INVALID', 'testing', 'active', 'FLG_TM50', '2022-02-18 16:25:07', 0),
(35, 'POSTCHECK', 'testing', 'active', 'FLG_TM51', '2022-02-18 16:25:07', 0),
(36, 'REAG_ERROR', 'testing', 'active', 'FLG_AP02 ', '2022-02-18 16:25:07', 0),
(37, 'NO_SAMPLE', 'testing', 'active', 'FLG_AP12', '2022-02-18 16:25:07', 0),
(38, 'DISP_ERROR', 'testing', 'active', 'FLG_AP13 ', '2022-02-18 16:25:07', 0),
(39, 'TEMP_RANGE', 'testing', 'active', 'FLG_AP19 ', '2022-02-18 16:25:07', 0),
(40, 'PREP_ABORT', 'testing', 'active', 'FLG_AP24', '2022-02-18 16:25:07', 0),
(41, 'SAMPLECLOT', 'testing', 'active', 'FLG_AP25', '2022-02-18 16:25:07', 0),
(42, 'Form received without Sample', 'general', 'active', 'Gen_NoSample', '2022-02-18 16:25:07', 0);

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
-- Dumping data for table `r_eid_sample_type`
--

INSERT INTO `r_eid_sample_type` (`sample_id`, `sample_name`, `status`, `updated_datetime`, `data_sync`) VALUES
(1, 'DBS', 'active', '2022-02-18 16:25:07', 0),
(2, 'Whole Blood', 'active', '2022-02-18 16:25:07', 0);

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
-- Dumping data for table `r_funding_sources`
--

INSERT INTO `r_funding_sources` (`funding_source_id`, `funding_source_name`, `funding_source_status`, `updated_datetime`, `data_sync`) VALUES
(1, 'USA Govt', 'active', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_generic_sample_rejection_reasons`
--

CREATE TABLE `r_generic_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `r_generic_sample_types`
--

CREATE TABLE `r_generic_sample_types` (
  `sample_type_id` int(11) NOT NULL,
  `sample_type_code` varchar(256) DEFAULT NULL,
  `sample_type_name` varchar(256) DEFAULT NULL,
  `sample_type_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `r_generic_symptoms`
--

CREATE TABLE `r_generic_symptoms` (
  `symptom_id` int(11) NOT NULL,
  `symptom_name` varchar(256) DEFAULT NULL,
  `symptom_code` varchar(256) DEFAULT NULL,
  `symptom_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `r_generic_test_categories`
--

CREATE TABLE `r_generic_test_categories` (
  `test_category_id` int(11) NOT NULL,
  `test_category_name` varchar(256) DEFAULT NULL,
  `test_category_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `r_generic_test_failure_reasons`
--

CREATE TABLE `r_generic_test_failure_reasons` (
  `test_failure_reason_id` int(11) NOT NULL,
  `test_failure_reason_code` varchar(256) NOT NULL,
  `test_failure_reason` varchar(256) DEFAULT NULL,
  `test_failure_reason_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `r_generic_test_methods`
--

CREATE TABLE `r_generic_test_methods` (
  `test_method_id` int(11) NOT NULL,
  `test_method_name` varchar(256) DEFAULT NULL,
  `test_method_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `r_generic_test_reasons`
--

CREATE TABLE `r_generic_test_reasons` (
  `test_reason_id` int(11) NOT NULL,
  `test_reason_code` varchar(256) DEFAULT NULL,
  `test_reason` varchar(256) DEFAULT NULL,
  `test_reason_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `r_generic_test_result_units`
--

CREATE TABLE `r_generic_test_result_units` (
  `unit_id` int(11) NOT NULL,
  `unit_name` varchar(256) DEFAULT NULL,
  `unit_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
-- Dumping data for table `r_hepatitis_results`
--

INSERT INTO `r_hepatitis_results` (`result_id`, `result`, `status`, `updated_datetime`, `data_sync`) VALUES
('indeterminate', 'Indeterminate', 'active', '2021-02-18 00:00:00', 0),
('negative', 'Negative', 'active', '2021-02-18 00:00:00', 0),
('positive', 'Positive', 'active', '2021-02-18 00:00:00', 0);

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
-- Dumping data for table `r_hepatitis_sample_rejection_reasons`
--

INSERT INTO `r_hepatitis_sample_rejection_reasons` (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`, `rejection_reason_status`, `rejection_reason_code`, `updated_datetime`, `data_sync`) VALUES
(1, 'Poorly labelled specimen', 'general', 'active', 'Gen_PLSP', '2021-02-22 15:27:49', 0),
(2, 'Mismatched sample and form labeling', 'general', 'active', 'Gen_MMSP', '2021-02-22 15:27:49', 0),
(3, 'Missing labels on container or tracking form', 'general', 'active', 'Gen_MLTS', '2021-02-22 15:27:49', 0),
(4, 'Sample without request forms/Tracking forms', 'general', 'active', 'Gen_SMRT', '2021-02-22 15:27:49', 0),
(5, 'Name/Information of requester is missing', 'general', 'active', 'Gen_NIRM', '2021-02-22 15:27:49', 0),
(6, 'Missing information on request form - Age', 'general', 'active', 'Gen_MIRA', '2021-02-22 15:27:49', 0),
(7, 'Missing information on request form - Sex', 'general', 'active', 'Gen_MIRS', '2021-02-22 15:27:49', 0),
(8, 'Missing information on request form - Sample Collection Date', 'general', 'active', 'Gen_MIRD', '2021-02-22 15:27:49', 0),
(9, 'Missing information on request form - ART No', 'general', 'active', 'Gen_MIAN', '2021-02-22 15:27:49', 0),
(10, 'Inappropriate specimen packing', 'general', 'active', 'Gen_ISPK', '2021-02-22 15:27:49', 0),
(11, 'Inappropriate specimen for test request', 'general', 'active', 'Gen_ISTR', '2021-02-22 15:27:49', 0),
(42, 'Form received without Sample', 'general', 'active', 'Gen_NoSample', '2021-02-22 15:27:49', 0);

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
-- Dumping data for table `r_hepatitis_sample_type`
--

INSERT INTO `r_hepatitis_sample_type` (`sample_id`, `sample_name`, `status`, `updated_datetime`, `data_sync`) VALUES
(1, 'Whole Blood', 'active', '2021-02-22 15:13:21', 0);

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
-- Dumping data for table `r_hepatitis_test_reasons`
--

INSERT INTO `r_hepatitis_test_reasons` (`test_reason_id`, `test_reason_name`, `parent_reason`, `test_reason_status`, `updated_datetime`) VALUES
(1, 'Follow up ', 0, 'active', '2021-02-22 15:13:41'),
(2, 'Confirmation', 0, 'active', '2021-02-22 15:13:41');

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
-- Dumping data for table `r_implementation_partners`
--

INSERT INTO `r_implementation_partners` (`i_partner_id`, `i_partner_name`, `i_partner_status`, `updated_datetime`, `data_sync`) VALUES
(1, 'USA Govt', 'active', NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_sample_controls`
--

CREATE TABLE `r_sample_controls` (
  `r_sample_control_id` int(11) NOT NULL,
  `r_sample_control_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `r_sample_controls`
--

INSERT INTO `r_sample_controls` (`r_sample_control_id`, `r_sample_control_name`) VALUES
(1, 'NC'),
(2, 'LPC'),
(3, 'HPC'),
(4, 'S');

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
(9, 'Sample Currently Registered at Health Center', 'active'),
(10, 'Sample Expired', 'active'),
(11, 'No Result', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `r_tb_results`
--

CREATE TABLE `r_tb_results` (
  `result_id` varchar(256) NOT NULL,
  `result` varchar(256) DEFAULT NULL,
  `result_type` varchar(256) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `r_tb_results`
--

INSERT INTO `r_tb_results` (`result_id`, `result`, `result_type`, `status`, `updated_datetime`, `data_sync`) VALUES
('1', 'Positive', NULL, 'active', '2021-11-16 15:23:42', 0),
('10', 'TT (MTB detected (Trace) rifampicin resistance indeterminate)', 'x-pert', 'active', '2021-11-16 15:25:26', 0),
('11', 'I (Invalid/Error/No result)', 'x-pert', 'active', '2021-11-16 15:25:26', 0),
('2', 'Negative', NULL, 'active', '2021-11-16 15:23:42', 0),
('3', 'Negative', 'lam', 'active', '2021-11-16 15:25:26', 0),
('4', 'Positive', 'lam', 'active', '2021-11-16 15:25:26', 0),
('5', 'Invalid', 'lam', 'active', '2021-11-16 15:25:26', 0),
('6', 'N (MTB not detected)', 'x-pert', 'active', '2021-11-16 15:25:26', 0),
('7', 'T (MTB detected rifampicin resistance not detected)', 'x-pert', 'active', '2021-11-16 15:25:26', 0),
('8', 'TI (MTB detected rifampicin resistance indeterminate)', 'x-pert', 'active', '2021-11-16 15:25:26', 0),
('9', 'RR (MTB detected rifampicin resistance detected)', 'lam', 'active', '2021-11-16 15:25:26', 0);

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
-- Dumping data for table `r_tb_sample_rejection_reasons`
--

INSERT INTO `r_tb_sample_rejection_reasons` (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`, `rejection_reason_status`, `rejection_reason_code`, `updated_datetime`, `data_sync`) VALUES
(1, 'Sample damaged', 'general', 'active', NULL, '2021-11-16 15:23:42', 0);

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
-- Dumping data for table `r_tb_sample_type`
--

INSERT INTO `r_tb_sample_type` (`sample_id`, `sample_name`, `status`, `updated_datetime`, `data_sync`) VALUES
(1, 'Serum', 'active', '2021-11-16 15:23:42', 0);

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
-- Dumping data for table `r_tb_test_reasons`
--

INSERT INTO `r_tb_test_reasons` (`test_reason_id`, `test_reason_name`, `parent_reason`, `test_reason_status`, `updated_datetime`) VALUES
(1, 'Case confirmed in TB', 0, 'active', '2021-11-16 15:23:42');

-- --------------------------------------------------------

--
-- Table structure for table `r_test_types`
--

CREATE TABLE `r_test_types` (
  `test_type_id` int(11) NOT NULL,
  `test_standard_name` varchar(255) DEFAULT NULL,
  `test_generic_name` varchar(255) DEFAULT NULL,
  `test_short_code` varchar(255) DEFAULT NULL,
  `test_loinc_code` varchar(255) DEFAULT NULL,
  `test_category` varchar(256) DEFAULT NULL,
  `test_form_config` text,
  `test_results_config` text,
  `test_status` varchar(100) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
-- Dumping data for table `r_vl_art_regimen`
--

INSERT INTO `r_vl_art_regimen` (`art_id`, `art_code`, `parent_art`, `headings`, `nation_identifier`, `art_status`, `updated_datetime`, `data_sync`) VALUES
(1, '1a = TDF+3TC+DTG', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(2, '1b = TDF+3TC+EFV', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(3, '1c = ABC+3TC+EFV', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(4, '1d = ABC+3TC+NVP', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(5, '1e = TDF+3TC+NVP', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(6, '1f = ABC+3TC+DTG', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(7, '1g = AZT+3TC+EFV', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(8, '1h = AZT+3TC+NVP', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(9, '2a = AZT+3TC+ATV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(10, '2b = AZT+3TC+LPV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(11, '2c = AZT+3TC+DTG', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(12, '2d = TDF+3TC+ATV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(13, '2e = TDF+3TC+LPV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(14, '2f = ABC+3TC+ATV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(15, '2g = ABC+3TC+LPV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(16, '3a = RAL+ETV+DRV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(17, '4a = ABC+3TC+LPV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(18, '4b = ABC+3TC+EFV', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(19, '4c = AZT+3TC+LPV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(20, '4d = AZT+3TC+EFV', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(21, '4e = TDF+3TC+EFV', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(22, '4f = ABC+3TC+NVP', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(23, '4g = AZT+3TC+NVP', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(24, '5a = AZT+3TC+RAL', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(25, '5b = ABC+3TC+RAL', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(26, '5c = AZT+3TC+LPV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(27, '5d = ABC+3TC+LPV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0),
(28, '5e = AZT + 3TC+ ATV/r', 0, NULL, 'rwd', 'active', '2022-02-18 16:25:07', 0);

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
-- Dumping data for table `r_vl_sample_rejection_reasons`
--

INSERT INTO `r_vl_sample_rejection_reasons` (`rejection_reason_id`, `rejection_reason_name`, `rejection_type`, `rejection_reason_status`, `rejection_reason_code`, `updated_datetime`, `data_sync`) VALUES
(1, 'Poorly labelled specimen', 'general', 'active', 'Gen_PLSP', '2022-02-18 16:25:07', 1),
(2, 'Mismatched sample and form labeling', 'general', 'active', 'Gen_MMSP', '2022-02-18 16:25:07', 1),
(3, 'Missing labels on container or tracking form', 'general', 'active', 'Gen_MLTS', '2022-02-18 16:25:07', 1),
(4, 'Sample without request forms/Tracking forms', 'general', 'active', 'Gen_SMRT', '2022-02-18 16:25:07', 1),
(5, 'Name/Information of requester is missing', 'general', 'active', 'Gen_NIRM', '2022-02-18 16:25:07', 1),
(6, 'Missing information on request form - Age', 'general', 'active', 'Gen_MIRA', '2022-02-18 16:25:07', 1),
(7, 'Missing information on request form - Sex', 'general', 'active', 'Gen_MIRS', '2022-02-18 16:25:07', 1),
(8, 'Missing information on request form - Sample Collection Date', 'general', 'active', 'Gen_MIRD', '2022-02-18 16:25:07', 1),
(9, 'Missing information on request form - ART No', 'general', 'active', 'Gen_MIAN', '2022-02-18 16:25:07', 1),
(10, 'Inappropriate specimen packing', 'general', 'active', 'Gen_ISPK', '2022-02-18 16:25:07', 1),
(11, 'Inappropriate specimen for test request', 'general', 'active', 'Gen_ISTR', '2022-02-18 16:25:07', 1),
(12, 'Wrong container/anticoagulant used', 'whole blood', 'active', 'BLD_WCAU', '2022-02-18 16:25:07', 1),
(13, 'EDTA tube specimens that arrived hemolyzed', 'whole blood', 'active', 'BLD_HMLY', '2022-02-18 16:25:07', 1),
(14, 'ETDA tube that arrives more than 24 hours after specimen collection', 'whole blood', 'active', 'BLD_AASC', '2022-02-18 16:25:07', 1),
(15, 'Plasma that arrives at a temperature above 8 C', 'plasma', 'active', 'PLS_AATA', '2022-02-18 16:25:07', 1),
(16, 'Plasma tube contain less than 1.5 mL', 'plasma', 'active', 'PSL_TCLT', '2022-02-18 16:25:07', 1),
(17, 'DBS cards with insufficient blood spots', 'dbs', 'active', 'DBS_IFBS', '2022-02-18 16:25:07', 1),
(18, 'DBS card with clotting present in spots', 'dbs', 'active', 'DBS_CPIS', '2022-02-18 16:25:07', 1),
(19, 'DBS cards that have serum rings indicating contamination around spots', 'dbs', 'active', 'DBS_SRIC', '2022-02-18 16:25:07', 1),
(20, 'VL Machine Flag', 'testing', 'active', 'FLG_', '2022-02-18 16:25:07', 1),
(21, 'CNTRL_FAIL', 'testing', 'active', 'FLG_AL00', '2022-02-18 16:25:07', 1),
(22, 'SYS_ERROR', 'testing', 'active', 'FLG_TM00', '2022-02-18 16:25:07', 1),
(23, 'A/D_ABORT', 'testing', 'active', 'FLG_TM17', '2022-02-18 16:25:07', 1),
(24, 'KIT_EXPIRY', 'testing', 'active', 'FLG_TMAP', '2022-02-18 16:25:07', 1),
(25, 'RUN_EXPIRY', 'testing', 'active', 'FLG_TM19', '2022-02-18 16:25:07', 1),
(26, 'DATA_ERROR', 'testing', 'active', 'FLG_TM20', '2022-02-18 16:25:07', 1),
(27, 'NC_INVALID', 'testing', 'active', 'FLG_TM24', '2022-02-18 16:25:07', 1),
(28, 'LPCINVALID', 'testing', 'active', 'FLG_TM25', '2022-02-18 16:25:07', 1),
(29, 'MPCINVALID', 'testing', 'active', 'FLG_TM26', '2022-02-18 16:25:07', 1),
(30, 'HPCINVALID', 'testing', 'active', 'FLG_TM27', '2022-02-18 16:25:07', 1),
(31, 'S_INVALID', 'testing', 'active', 'FLG_TM29', '2022-02-18 16:25:07', 1),
(32, 'MATH_ERROR', 'testing', 'active', 'FLG_TM31', '2022-02-18 16:25:07', 1),
(33, 'PRECHECK', 'testing', 'active', 'FLG_TM44 ', '2022-02-18 16:25:07', 1),
(34, 'QS_INVALID', 'testing', 'active', 'FLG_TM50', '2022-02-18 16:25:07', 1),
(35, 'POSTCHECK', 'testing', 'active', 'FLG_TM51', '2022-02-18 16:25:07', 1),
(36, 'REAG_ERROR', 'testing', 'active', 'FLG_AP02 ', '2022-02-18 16:25:07', 1),
(37, 'NO_SAMPLE', 'testing', 'active', 'FLG_AP12', '2022-02-18 16:25:07', 1),
(38, 'DISP_ERROR', 'testing', 'active', 'FLG_AP13 ', '2022-02-18 16:25:07', 1),
(39, 'TEMP_RANGE', 'testing', 'active', 'FLG_AP19 ', '2022-02-18 16:25:07', 1),
(40, 'PREP_ABORT', 'testing', 'active', 'FLG_AP24', '2022-02-18 16:25:07', 1),
(41, 'SAMPLECLOT', 'testing', 'active', 'FLG_AP25', '2022-02-18 16:25:07', 1),
(42, 'Form received without Sample', 'general', 'active', 'Gen_NoSample', '2022-02-18 16:25:07', 0);

-- --------------------------------------------------------

--
-- Table structure for table `r_vl_sample_type`
--

CREATE TABLE `r_vl_sample_type` (
  `sample_id` int(11) NOT NULL,
  `sample_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `r_vl_sample_type`
--

INSERT INTO `r_vl_sample_type` (`sample_id`, `sample_name`, `status`, `updated_datetime`, `data_sync`) VALUES
(1, 'Plasma', 'inactive', '2022-02-18 16:25:07', 1),
(2, 'Venous blood (EDTA)', 'active', '2022-02-18 16:25:07', 1),
(3, 'DBS capillary (infants only)', 'inactive', '2022-02-18 16:25:07', 1),
(4, 'Dried Blood Spot', 'inactive', '2022-08-10 10:47:17', 1),
(5, 'PPT', 'inactive', '2022-02-18 16:25:07', 1);

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
-- Dumping data for table `r_vl_test_reasons`
--

INSERT INTO `r_vl_test_reasons` (`test_reason_id`, `test_reason_name`, `parent_reason`, `test_reason_status`, `updated_datetime`, `data_sync`) VALUES
(1, 'routine', 0, 'active', '2022-02-18 16:25:07', 0),
(2, 'Confirmation Of Treatment Failure(repeat VL at 3M)', 0, 'active', '2022-02-18 16:25:07', 0),
(3, 'failure', 0, 'active', '2022-02-18 16:25:07', 0),
(4, 'immunological failure', 0, 'active', '2022-02-18 16:25:07', 0),
(5, 'single drug substitution', 0, 'active', '2022-02-18 16:25:07', 0),
(6, 'Pregnant Mother', 0, 'active', '2022-02-18 16:25:07', 0),
(7, 'Lactating Mother', 0, 'active', '2022-02-18 16:25:07', 0),
(8, 'Baseline VL', 0, 'active', '2022-02-18 16:25:07', 0),
(10, 'suspect', 0, 'active', '2022-02-18 16:25:07', 0),
(11, 'Excol', 0, 'active', '2022-02-18 16:25:07', 0),
(12, 'result missing', 0, 'active', '2022-02-18 16:25:07', 0),
(13, 'value missed', 0, 'active', '2022-02-18 16:25:07', 0),
(14, 'routine', 0, 'active', '2022-02-18 16:25:07', 0),
(15, 'failure', 0, 'active', '2022-02-18 16:25:07', 0),
(9999, 'recency', 0, 'active', '2022-02-18 16:25:07', 0);

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
-- Dumping data for table `system_admin`
--

INSERT INTO `system_admin` (`system_admin_id`, `system_admin_name`, `system_admin_email`, `system_admin_login`, `system_admin_password`) VALUES
(1, 'rwadmin', NULL, 'rwadmin', '123');

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
-- Dumping data for table `system_config`
--

INSERT INTO `system_config` (`display_name`, `name`, `value`) VALUES
('Testing Lab ID', 'sc_testing_lab_id', ''),
('User Type', 'sc_user_type', 'vluser'),
('Version', 'sc_version', '5.1.7'),
('Email Id', 'sup_email', NULL),
('Password', 'sup_password', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `s_app_menu`
--

CREATE TABLE `s_app_menu` (
  `id` int(11) NOT NULL,
  `module` varchar(256) NOT NULL,
  `is_header` varchar(256) DEFAULT NULL,
  `display_text` varchar(256) NOT NULL,
  `link` varchar(256) DEFAULT NULL,
  `inner_pages` varchar(256) DEFAULT NULL,
  `show_mode` varchar(132) NOT NULL DEFAULT 'always',
  `icon` varchar(256) DEFAULT NULL,
  `has_children` varchar(256) DEFAULT NULL,
  `additional_class_names` varchar(256) DEFAULT NULL,
  `parent_id` int(11) DEFAULT '0',
  `display_order` int(11) NOT NULL,
  `status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `s_app_menu`
--

INSERT INTO `s_app_menu` (`id`, `module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`) VALUES
(1, 'dashboard', 'no', 'Dashboard', '/dashboard/index.php', NULL, 'always', 'fa-solid fa-chart-pie', 'no', 'allMenu dashboardMenu', 0, 1, 'active', NULL),
(2, 'admin', 'no', 'Admin', NULL, NULL, 'always', 'fa-solid fa-shield', 'yes', NULL, 0, 2, 'active', NULL),
(3, 'admin', 'no', 'Access Control', '', NULL, 'always', 'fa-solid fa-user', 'yes', 'treeview access-control-menu', 2, 3, 'active', NULL),
(4, 'admin', 'no', 'Roles', '/roles/roles.php', '/roles/addRole.php,/roles/editRole.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu roleMenu', 3, 4, 'active', NULL),
(5, 'admin', 'no', 'Users', '/users/users.php', '/users/addUser.php,/users/editUser.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu userMenu', 3, 5, 'active', NULL),
(6, 'admin', 'no', 'Facilities', '/facilities/facilities.php', '/facilities/addFacility.php,/facilities/editFacility.php,/facilities/mapTestType.php', 'always', 'fa-solid fa-hospital', 'no', 'treeview facility-config-menu', 2, 6, 'active', NULL),
(7, 'admin', 'no', 'Monitoring', NULL, NULL, 'always', 'fa-solid fa-bullseye', 'yes', 'treeview monitoring-menu', 2, 7, 'active', NULL),
(8, 'admin', 'no', 'System Configuration', NULL, NULL, 'always', 'fa-solid fa-gears', 'yes', 'treeview system-config-menu', 2, 8, 'active', NULL),
(9, 'admin', 'no', 'Lab Tests Config', NULL, NULL, 'always', 'fa-solid fa-vial-circle-check', 'yes', 'treeview generic-reference-manage', 2, 9, 'active', NULL),
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
(20, 'admin', 'no', 'Instruments', '/import-configs/importConfig.php', '/import-configs/addImportConfig.php,/import-configs/editImportConfig.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu importConfigMenu', 8, 20, 'active', NULL),
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
(60, 'genericTests', 'yes', 'LAB TESTS', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 60, 'active', NULL),
(61, 'genericTests', 'no', 'Request Management', NULL, NULL, 'always', 'fa-solid fa-pen-to-square', 'yes', 'treeview allMenu generic-test-request-menu', 60, 61, 'active', NULL),
(62, 'genericTests', 'no', 'Test Result Management', NULL, NULL, 'always', 'fa-solid fa-list-check', 'yes', 'treeview allMenu generic-test-results-menu', 60, 62, 'active', NULL),
(63, 'genericTests', 'no', 'Management', NULL, NULL, 'always', 'fa-solid fa-book', 'yes', 'treeview allMenu generic-test-request-menu', 60, 63, 'active', NULL),
(64, 'vl', 'yes', 'HIV VIRAL LOAD', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 64, 'active', NULL),
(65, 'eid', 'yes', 'EARLY INFANT DIAGNOSIS (EID)', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 65, 'active', NULL),
(66, 'covid19', 'yes', 'COVID-19', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 66, 'active', NULL),
(67, 'hepatitis', 'yes', 'HEPATITIS', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 67, 'active', NULL),
(68, 'tb', 'yes', 'TUBERCULOSIS', NULL, NULL, 'always', NULL, 'yes', 'header', 0, 68, 'active', NULL),
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
(84, 'genericTests', 'no', 'View Test Requests', '/generic-tests/requests/view-requests.php', '/generic-tests/requests/edit-request.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericRequestMenu', 61, 84, 'active', NULL),
(85, 'genericTests', 'no', 'Add New Request', '/generic-tests/requests/add-request.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu addGenericRequestMenu', 61, 85, 'active', NULL),
(86, 'genericTests', 'no', 'Add Samples from Manifest', '/generic-tests/requests/add-samples-from-manifest.php', '/generic-tests/requests/edit-request.php', 'lis', 'fa-solid fa-caret-right', 'no', 'allMenu addGenericSamplesFromManifestMenu', 61, 86, 'active', NULL),
(87, 'genericTests', 'no', 'Manage Batch', '/batch/batches.php?type=generic-tests', '/batch/add-batch.php?type=generic-tests,/batch/edit-batch.php?type=generic-tests,/batch/add-batch-position.php?type=generic-tests,/batch/edit-batch-position.php?type=generic-tests', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu batchGenericCodeMenu', 61, 87, 'active', NULL),
(88, 'genericTests', 'no', 'Lab Test Manifest', '/specimen-referral-manifest/view-manifests.php?t=generic-tests', '/specimen-referral-manifest/add-manifest.php?t=generic-tests,/specimen-referral-manifest/edit-manifest.php?t=generic-tests,/specimen-referral-manifest/move-manifest.php?t=generic-tests', 'sts', 'fa-solid fa-caret-right', 'no', 'allMenu specimenGenericReferralManifestListMenu', 61, 88, 'active', NULL),
(89, 'genericTests', 'no', 'Enter Result Manually', '/generic-tests/results/generic-test-results.php', '/generic-tests/results/update-generic-test-result.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericTestResultMenu', 62, 88, 'active', NULL),
(90, 'genericTests', 'no', 'Failed/Hold Samples', '/generic-tests/results/generic-failed-results.php', '/generic-tests/results/update-generic-test-result.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericFailedResultMenu', 62, 88, 'active', NULL),
(91, 'genericTests', 'no', 'Manage Results Status', '/generic-tests/results/generic-result-approval.php', '/generic-tests/results/update-generic-test-result.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericResultApprovalMenu', 62, 88, 'active', NULL),
(92, 'genericTests', 'no', 'Sample Status Report', '/generic-tests/program-management/generic-sample-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericStatusReportMenu', 62, 88, 'active', NULL),
(93, 'genericTests', 'no', 'Export Results', '/generic-tests/program-management/generic-export-data.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericExportMenu', 63, 89, 'active', NULL),
(94, 'genericTests', 'no', 'Print Result', '/generic-tests/results/generic-print-result.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericPrintResultMenu', 63, 90, 'active', NULL),
(95, 'genericTests', 'no', 'Sample Rejection Report', '/generic-tests/program-management/sample-rejection-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu genericSampleRejectionReport', 63, 91, 'active', NULL),
(96, 'vl', 'no', 'View Test Requests', '/vl/requests/vlRequest.php', '/vl/requests/editVlRequest.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlRequestMenu', 69, 92, 'active', NULL),
(97, 'vl', 'no', 'Add New Request', '/vl/requests/addVlRequest.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu addVlRequestMenu', 69, 93, 'active', NULL),
(98, 'vl', 'no', 'Add Samples from Manifest', '/vl/requests/addSamplesFromManifest.php', NULL, 'lis', 'fa-solid fa-caret-right', 'no', 'allMenu addSamplesFromManifestMenu', 69, 94, 'active', NULL),
(99, 'vl', 'no', 'Manage Batch', '/batch/batches.php?type=vl', '/batch/add-batch.php?type=vl,/batch/edit-batch.php?type=vl,/batch/edit-batch-position.php?type=vl', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu batchCodeMenu', 69, 95, 'active', NULL),
(100, 'vl', 'no', 'VL Manifest', '/specimen-referral-manifest/view-manifests.php?t=vl', '/specimen-referral-manifest/add-manifest.php?t=vl,/specimen-referral-manifest/edit-manifest.php?t=vl,/specimen-referral-manifest/move-manifest.php?t=vl', 'sts', 'fa-solid fa-caret-right', 'no', 'allMenu specimenReferralManifestListVLMenu', 69, 96, 'active', NULL),
(101, 'vl', 'no', 'Import Result From File', '/import-result/import-file.php?t=vl', '/vl/requests/editVlRequest.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu importResultMenu', 70, 97, 'active', NULL),
(102, 'vl', 'no', 'Enter Result Manually', '/vl/results/vlTestResult.php', '/vl/results/updateVlTestResult.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlTestResultMenu', 70, 98, 'active', NULL),
(103, 'vl', 'no', 'Failed/Hold Samples', '/vl/results/vl-failed-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu vlFailedResultMenu', 70, 99, 'active', NULL),
(104, 'vl', 'no', 'Manage Results Status', '/vl/results/vlResultApproval.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu batchCodeMenu', 70, 100, 'active', NULL),
(105, 'vl', 'no', 'Sample Status Report', '/vl/program-management/vl-sample-status.php', '/vl/requests/editVlRequest.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu missingResultMenu', 71, 100, 'active', NULL),
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
(119, 'eid', 'no', 'Import Result From File', '/import-result/import-file.php?t=eid', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu eidImportResultMenu', 76, 114, 'active', NULL),
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
(134, 'covid19', 'no', 'Import Result From File', '/import-result/import-file.php?t=covid19', '/vl/requests/editVlRequest.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu covid19ImportResultMenu', 73, 129, 'active', NULL),
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
(152, 'hepatitis', 'no', 'Import Result From File', '/import-result/import-file.php?t=hepatitis', '/vl/requests/editVlRequest.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu hepatitisImportResultMenu', 79, 146, 'active', NULL),
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
(167, 'tb', 'no', 'Import Result From File', '/import-result/import-file.php?t=tb', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbImportResultMenu', 82, 161, 'active', NULL),
(168, 'tb', 'no', 'Enter Result Manually', '/tb/results/tb-manual-results.php', '/tb/results/tb-update-result.php', 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbResultsMenu', 82, 162, 'active', NULL),
(169, 'tb', 'no', 'Failed/Hold Samples', '/tb/results/tb-failed-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbFailedResultsMenu', 82, 163, 'active', NULL),
(170, 'tb', 'no', 'Manage Results Status', '/tb/results/tb-result-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbResultStatus', 82, 164, 'active', NULL),
(171, 'tb', 'no', 'Sample Status Report', '/tb/management/tb-sample-status.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbSampleStatus', 83, 165, 'active', NULL),
(172, 'tb', 'no', 'Print Result', '/tb/results/tb-print-results.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbPrintResults', 83, 166, 'active', NULL),
(173, 'tb', 'no', 'Export Results', '/tb/management/tb-export-data.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbExportResult', 83, 167, 'active', NULL),
(174, 'tb', 'no', 'Sample Rejection Report', '/tb/management/tb-sample-rejection-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbSampleRejectionReport', 83, 168, 'active', NULL),
(175, 'tb', 'no', 'Clinic Reports', '/tb/management/tb-clinic-report.php', NULL, 'always', 'fa-solid fa-caret-right', 'no', 'allMenu tbClinicReport', 83, 169, 'active', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `s_available_country_forms`
--

CREATE TABLE `s_available_country_forms` (
  `vlsm_country_id` int(11) NOT NULL,
  `form_name` varchar(255) DEFAULT NULL,
  `short_name` varchar(256) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `s_available_country_forms`
--

INSERT INTO `s_available_country_forms` (`vlsm_country_id`, `form_name`, `short_name`) VALUES
(1, 'South Sudan ', 'ssudan'),
(2, 'Sierra Leone', 'sierra-leone'),
(3, 'Democratic Republic of the Congo', 'drc'),
(4, 'Republic of Cameroon', 'cameroon'),
(5, 'Papua New Guinea', 'png'),
(6, 'WHO ', 'who'),
(7, 'Rwanda ', 'rwanda');

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
  `batch_code_key` varchar(255) DEFAULT NULL,
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
  `imported_by` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `testing_labs`
--

CREATE TABLE `testing_labs` (
  `test_type` enum('vl','eid','covid19','hepatitis','tb','generic-tests') NOT NULL,
  `facility_id` int(11) NOT NULL,
  `attributes` json DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `monthly_target` varchar(255) DEFAULT NULL,
  `suppressed_monthly_target` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `testing_lab_health_facilities_map`
--

CREATE TABLE `testing_lab_health_facilities_map` (
  `facility_map_id` int(11) NOT NULL,
  `vl_lab_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `user_locale` varchar(256) DEFAULT NULL,
  `user_signature` mediumtext,
  `api_token` mediumtext,
  `api_token_generated_datetime` datetime DEFAULT NULL,
  `api_token_exipiration_days` int(11) DEFAULT NULL,
  `force_password_reset` int(11) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `app_access` varchar(50) DEFAULT 'no',
  `hash_algorithm` varchar(256) NOT NULL DEFAULT 'sha1',
  `data_sync` int(11) DEFAULT '0',
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `user_facility_map`
--

CREATE TABLE `user_facility_map` (
  `user_facility_map_id` int(11) NOT NULL,
  `user_id` varchar(255) NOT NULL,
  `facility_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `user_login_history`
--

CREATE TABLE `user_login_history` (
  `history_id` int(11) NOT NULL,
  `user_id` varchar(1000) DEFAULT NULL,
  `login_id` varchar(1000) NOT NULL,
  `login_attempted_datetime` datetime DEFAULT NULL,
  `login_status` varchar(256) DEFAULT NULL,
  `ip_address` varchar(256) DEFAULT NULL,
  `browser` varchar(1000) DEFAULT NULL,
  `operating_system` varchar(1000) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `vl_contact_notes`
--

CREATE TABLE `vl_contact_notes` (
  `contact_notes_id` int(11) NOT NULL,
  `treament_contact_id` int(11) DEFAULT NULL,
  `contact_notes` mediumtext,
  `collected_on` date DEFAULT NULL,
  `added_on` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
-- Indexes for table `audit_form_generic`
--
ALTER TABLE `audit_form_generic`
  ADD PRIMARY KEY (`sample_id`,`revision`);

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
  ADD UNIQUE KEY `facility_name` (`facility_name`),
  ADD UNIQUE KEY `other_id` (`other_id`),
  ADD UNIQUE KEY `facility_name_2` (`facility_name`),
  ADD UNIQUE KEY `other_id_2` (`other_id`),
  ADD UNIQUE KEY `facility_code` (`facility_code`);

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
  ADD UNIQUE KEY `unique_id` (`unique_id`),
  ADD UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  ADD UNIQUE KEY `sample_code` (`sample_code`,`lab_id`),
  ADD UNIQUE KEY `lab_id` (`lab_id`,`app_sample_code`),
  ADD KEY `last_modified_datetime` (`last_modified_datetime`),
  ADD KEY `sample_code_key` (`sample_code_key`),
  ADD KEY `remote_sample_code_key` (`remote_sample_code_key`),
  ADD KEY `sample_package_id` (`sample_package_id`);

--
-- Indexes for table `form_eid`
--
ALTER TABLE `form_eid`
  ADD PRIMARY KEY (`eid_id`),
  ADD UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  ADD UNIQUE KEY `sample_code` (`sample_code`,`lab_id`),
  ADD UNIQUE KEY `lab_id` (`lab_id`,`app_sample_code`),
  ADD KEY `last_modified_datetime` (`last_modified_datetime`),
  ADD KEY `sample_code_key` (`sample_code_key`),
  ADD KEY `remote_sample_code_key` (`remote_sample_code_key`),
  ADD KEY `sample_package_id` (`sample_package_id`);

--
-- Indexes for table `form_generic`
--
ALTER TABLE `form_generic`
  ADD PRIMARY KEY (`sample_id`),
  ADD UNIQUE KEY `lab_id` (`lab_id`,`app_sample_code`);

--
-- Indexes for table `form_hepatitis`
--
ALTER TABLE `form_hepatitis`
  ADD PRIMARY KEY (`hepatitis_id`),
  ADD UNIQUE KEY `unique_id` (`unique_id`),
  ADD UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  ADD UNIQUE KEY `sample_code` (`sample_code`,`lab_id`),
  ADD UNIQUE KEY `lab_id` (`lab_id`,`app_sample_code`),
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
  ADD UNIQUE KEY `lab_id_2` (`lab_id`,`app_sample_code`),
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
  ADD UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  ADD UNIQUE KEY `unique_id` (`unique_id`),
  ADD UNIQUE KEY `lab_id_2` (`lab_id`,`app_sample_code`),
  ADD KEY `facility_id` (`facility_id`),
  ADD KEY `art_no` (`patient_art_no`),
  ADD KEY `sample_id` (`sample_type`),
  ADD KEY `created_by` (`request_created_by`),
  ADD KEY `sample_collection_date` (`sample_collection_date`),
  ADD KEY `sample_tested_datetime` (`sample_tested_datetime`),
  ADD KEY `lab_id` (`lab_id`),
  ADD KEY `result_status` (`result_status`),
  ADD KEY `last_modified_datetime` (`last_modified_datetime`),
  ADD KEY `sample_code_key` (`sample_code_key`),
  ADD KEY `remote_sample_code_key` (`remote_sample_code_key`),
  ADD KEY `result_approved_by` (`result_approved_by`),
  ADD KEY `result_reviewed_by` (`result_reviewed_by`),
  ADD KEY `sample_reordered` (`sample_reordered`),
  ADD KEY `result_approved_by_2` (`result_approved_by`),
  ADD KEY `result_reviewed_by_2` (`result_reviewed_by`),
  ADD KEY `sample_package_id` (`sample_package_id`);

--
-- Indexes for table `generic_sample_rejection_reason_map`
--
ALTER TABLE `generic_sample_rejection_reason_map`
  ADD PRIMARY KEY (`map_id`);

--
-- Indexes for table `generic_test_failure_reason_map`
--
ALTER TABLE `generic_test_failure_reason_map`
  ADD PRIMARY KEY (`map_id`),
  ADD KEY `test_type_id` (`test_type_id`),
  ADD KEY `test_reason_id` (`test_failure_reason_id`);

--
-- Indexes for table `generic_test_methods_map`
--
ALTER TABLE `generic_test_methods_map`
  ADD PRIMARY KEY (`map_id`),
  ADD KEY `test_type_id` (`test_type_id`),
  ADD KEY `test_method_id` (`test_method_id`);

--
-- Indexes for table `generic_test_reason_map`
--
ALTER TABLE `generic_test_reason_map`
  ADD PRIMARY KEY (`map_id`),
  ADD KEY `test_type_id` (`test_type_id`),
  ADD KEY `test_reason_id` (`test_reason_id`);

--
-- Indexes for table `generic_test_results`
--
ALTER TABLE `generic_test_results`
  ADD PRIMARY KEY (`test_id`),
  ADD KEY `generic_id` (`generic_id`);

--
-- Indexes for table `generic_test_result_units_map`
--
ALTER TABLE `generic_test_result_units_map`
  ADD PRIMARY KEY (`map_id`),
  ADD KEY `test_type_id` (`test_type_id`),
  ADD KEY `unit_id` (`unit_id`);

--
-- Indexes for table `generic_test_sample_type_map`
--
ALTER TABLE `generic_test_sample_type_map`
  ADD PRIMARY KEY (`map_id`),
  ADD KEY `sample_type_id` (`sample_type_id`),
  ADD KEY `test_type_id` (`test_type_id`);

--
-- Indexes for table `generic_test_symptoms_map`
--
ALTER TABLE `generic_test_symptoms_map`
  ADD PRIMARY KEY (`map_id`),
  ADD KEY `symptom_id` (`symptom_id`),
  ADD KEY `test_type_id` (`test_type_id`);

--
-- Indexes for table `geographical_divisions`
--
ALTER TABLE `geographical_divisions`
  ADD PRIMARY KEY (`geo_id`),
  ADD UNIQUE KEY `geo_name` (`geo_name`,`geo_parent`);

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
  ADD UNIQUE KEY `province_name` (`province_name`),
  ADD UNIQUE KEY `province_name_2` (`province_name`);

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
-- Indexes for table `r_generic_sample_rejection_reasons`
--
ALTER TABLE `r_generic_sample_rejection_reasons`
  ADD PRIMARY KEY (`rejection_reason_id`);

--
-- Indexes for table `r_generic_sample_types`
--
ALTER TABLE `r_generic_sample_types`
  ADD PRIMARY KEY (`sample_type_id`),
  ADD UNIQUE KEY `sample_type_code` (`sample_type_code`),
  ADD UNIQUE KEY `sample_type_name` (`sample_type_name`);

--
-- Indexes for table `r_generic_symptoms`
--
ALTER TABLE `r_generic_symptoms`
  ADD PRIMARY KEY (`symptom_id`),
  ADD UNIQUE KEY `symptom_code` (`symptom_code`),
  ADD UNIQUE KEY `symptom_name` (`symptom_name`);

--
-- Indexes for table `r_generic_test_categories`
--
ALTER TABLE `r_generic_test_categories`
  ADD PRIMARY KEY (`test_category_id`),
  ADD UNIQUE KEY `test_category_name` (`test_category_name`);

--
-- Indexes for table `r_generic_test_failure_reasons`
--
ALTER TABLE `r_generic_test_failure_reasons`
  ADD PRIMARY KEY (`test_failure_reason_id`);

--
-- Indexes for table `r_generic_test_methods`
--
ALTER TABLE `r_generic_test_methods`
  ADD PRIMARY KEY (`test_method_id`),
  ADD UNIQUE KEY `test_method_name` (`test_method_name`);

--
-- Indexes for table `r_generic_test_reasons`
--
ALTER TABLE `r_generic_test_reasons`
  ADD PRIMARY KEY (`test_reason_id`),
  ADD UNIQUE KEY `test_reason_code` (`test_reason_code`),
  ADD UNIQUE KEY `test_reason` (`test_reason`);

--
-- Indexes for table `r_generic_test_result_units`
--
ALTER TABLE `r_generic_test_result_units`
  ADD PRIMARY KEY (`unit_id`);

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
-- Indexes for table `s_app_menu`
--
ALTER TABLE `s_app_menu`
  ADD PRIMARY KEY (`id`);

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
  ADD PRIMARY KEY (`history_id`),
  ADD KEY `login_status_attempted_datetime_idx` (`login_status`,`login_attempted_datetime`);

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
-- AUTO_INCREMENT for table `audit_form_generic`
--
ALTER TABLE `audit_form_generic`
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
-- AUTO_INCREMENT for table `form_generic`
--
ALTER TABLE `form_generic`
  MODIFY `sample_id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `generic_sample_rejection_reason_map`
--
ALTER TABLE `generic_sample_rejection_reason_map`
  MODIFY `map_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generic_test_failure_reason_map`
--
ALTER TABLE `generic_test_failure_reason_map`
  MODIFY `map_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generic_test_methods_map`
--
ALTER TABLE `generic_test_methods_map`
  MODIFY `map_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generic_test_reason_map`
--
ALTER TABLE `generic_test_reason_map`
  MODIFY `map_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generic_test_results`
--
ALTER TABLE `generic_test_results`
  MODIFY `test_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generic_test_result_units_map`
--
ALTER TABLE `generic_test_result_units_map`
  MODIFY `map_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generic_test_sample_type_map`
--
ALTER TABLE `generic_test_sample_type_map`
  MODIFY `map_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `generic_test_symptoms_map`
--
ALTER TABLE `generic_test_symptoms_map`
  MODIFY `map_id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `config_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `instrument_machines`
--
ALTER TABLE `instrument_machines`
  MODIFY `config_machine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

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
  MODIFY `package_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `patient_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `privileges`
--
ALTER TABLE `privileges`
  MODIFY `privilege_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=389;

--
-- AUTO_INCREMENT for table `province_details`
--
ALTER TABLE `province_details`
  MODIFY `province_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
  MODIFY `role_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `roles_privileges_map`
--
ALTER TABLE `roles_privileges_map`
  MODIFY `map_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5147;

--
-- AUTO_INCREMENT for table `r_countries`
--
ALTER TABLE `r_countries`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=250;

--
-- AUTO_INCREMENT for table `r_covid19_comorbidities`
--
ALTER TABLE `r_covid19_comorbidities`
  MODIFY `comorbidity_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `r_covid19_qc_testkits`
--
ALTER TABLE `r_covid19_qc_testkits`
  MODIFY `testkit_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_covid19_sample_rejection_reasons`
--
ALTER TABLE `r_covid19_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `r_covid19_sample_type`
--
ALTER TABLE `r_covid19_sample_type`
  MODIFY `sample_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `r_covid19_symptoms`
--
ALTER TABLE `r_covid19_symptoms`
  MODIFY `symptom_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `r_covid19_test_reasons`
--
ALTER TABLE `r_covid19_test_reasons`
  MODIFY `test_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `r_eid_sample_rejection_reasons`
--
ALTER TABLE `r_eid_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

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
-- AUTO_INCREMENT for table `r_generic_sample_rejection_reasons`
--
ALTER TABLE `r_generic_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_generic_sample_types`
--
ALTER TABLE `r_generic_sample_types`
  MODIFY `sample_type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_generic_symptoms`
--
ALTER TABLE `r_generic_symptoms`
  MODIFY `symptom_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_generic_test_categories`
--
ALTER TABLE `r_generic_test_categories`
  MODIFY `test_category_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_generic_test_failure_reasons`
--
ALTER TABLE `r_generic_test_failure_reasons`
  MODIFY `test_failure_reason_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_generic_test_methods`
--
ALTER TABLE `r_generic_test_methods`
  MODIFY `test_method_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_generic_test_reasons`
--
ALTER TABLE `r_generic_test_reasons`
  MODIFY `test_reason_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_generic_test_result_units`
--
ALTER TABLE `r_generic_test_result_units`
  MODIFY `unit_id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `r_hepatitis_sample_type`
--
ALTER TABLE `r_hepatitis_sample_type`
  MODIFY `sample_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `r_hepatitis_test_reasons`
--
ALTER TABLE `r_hepatitis_test_reasons`
  MODIFY `test_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `r_implementation_partners`
--
ALTER TABLE `r_implementation_partners`
  MODIFY `i_partner_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `r_sample_controls`
--
ALTER TABLE `r_sample_controls`
  MODIFY `r_sample_control_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `r_sample_status`
--
ALTER TABLE `r_sample_status`
  MODIFY `status_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
  MODIFY `art_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `r_vl_results`
--
ALTER TABLE `r_vl_results`
  MODIFY `result_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_vl_sample_rejection_reasons`
--
ALTER TABLE `r_vl_sample_rejection_reasons`
  MODIFY `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `r_vl_sample_type`
--
ALTER TABLE `r_vl_sample_type`
  MODIFY `sample_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `r_vl_test_failure_reasons`
--
ALTER TABLE `r_vl_test_failure_reasons`
  MODIFY `failure_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `r_vl_test_reasons`
--
ALTER TABLE `r_vl_test_reasons`
  MODIFY `test_reason_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10000;

--
-- AUTO_INCREMENT for table `support`
--
ALTER TABLE `support`
  MODIFY `support_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_admin`
--
ALTER TABLE `system_admin`
  MODIFY `system_admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `s_app_menu`
--
ALTER TABLE `s_app_menu`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=176;

--
-- AUTO_INCREMENT for table `s_available_country_forms`
--
ALTER TABLE `s_available_country_forms`
  MODIFY `vlsm_country_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
  ADD CONSTRAINT `form_vl_ibfk_5` FOREIGN KEY (`result_status`) REFERENCES `r_sample_status` (`status_id`);

--
-- Constraints for table `generic_test_reason_map`
--
ALTER TABLE `generic_test_reason_map`
  ADD CONSTRAINT `generic_test_reason_map_ibfk_1` FOREIGN KEY (`test_type_id`) REFERENCES `r_test_types` (`test_type_id`),
  ADD CONSTRAINT `generic_test_reason_map_ibfk_2` FOREIGN KEY (`test_reason_id`) REFERENCES `r_generic_test_reasons` (`test_reason_id`);

--
-- Constraints for table `generic_test_results`
--
ALTER TABLE `generic_test_results`
  ADD CONSTRAINT `generic_test_results_ibfk_1` FOREIGN KEY (`generic_id`) REFERENCES `form_generic` (`sample_id`);

--
-- Constraints for table `generic_test_sample_type_map`
--
ALTER TABLE `generic_test_sample_type_map`
  ADD CONSTRAINT `generic_test_sample_type_map_ibfk_1` FOREIGN KEY (`sample_type_id`) REFERENCES `r_generic_sample_types` (`sample_type_id`),
  ADD CONSTRAINT `generic_test_sample_type_map_ibfk_2` FOREIGN KEY (`test_type_id`) REFERENCES `r_test_types` (`test_type_id`);

--
-- Constraints for table `generic_test_symptoms_map`
--
ALTER TABLE `generic_test_symptoms_map`
  ADD CONSTRAINT `generic_test_symptoms_map_ibfk_1` FOREIGN KEY (`symptom_id`) REFERENCES `r_generic_symptoms` (`symptom_id`),
  ADD CONSTRAINT `generic_test_symptoms_map_ibfk_2` FOREIGN KEY (`test_type_id`) REFERENCES `r_test_types` (`test_type_id`);

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
-- Constraints for table `vl_contact_notes`
--
ALTER TABLE `vl_contact_notes`
  ADD CONSTRAINT `vl_contact_notes_ibfk_1` FOREIGN KEY (`treament_contact_id`) REFERENCES `form_vl` (`vl_sample_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
