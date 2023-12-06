-- MySQL dump 10.13  Distrib 5.7.39, for osx11.0 (x86_64)
--
-- Host: localhost    Database: vlsm-init
-- ------------------------------------------------------
-- Server version	5.7.39

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `activity_log`
--

DROP TABLE IF EXISTS `activity_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_type` varchar(255) DEFAULT NULL,
  `action` mediumtext,
  `resource` varchar(255) DEFAULT NULL,
  `user_id` varchar(256) DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  `ip_address` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activity_log`
--

LOCK TABLES `activity_log` WRITE;
/*!40000 ALTER TABLE `activity_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `activity_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_form_covid19`
--

DROP TABLE IF EXISTS `audit_form_covid19`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_form_covid19` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(6) NOT NULL AUTO_INCREMENT,
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
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
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
  `is_encrypted` varchar(10) DEFAULT 'no',
  `sync_patient_identifiers` varchar(10) DEFAULT 'yes',
  `system_patient_code` varchar(43) DEFAULT NULL,
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
  `test_requested_on` date DEFAULT NULL,
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
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
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
  `result_sent_to_source_datetime` datetime DEFAULT NULL,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `is_result_mail_sent` varchar(255) DEFAULT 'no',
  `app_sample_code` varchar(255) DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` varchar(255) DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`covid19_id`,`revision`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_form_covid19`
--

LOCK TABLES `audit_form_covid19` WRITE;
/*!40000 ALTER TABLE `audit_form_covid19` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_form_covid19` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_form_eid`
--

DROP TABLE IF EXISTS `audit_form_eid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_form_eid` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(6) NOT NULL AUTO_INCREMENT,
  `dt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `eid_id` int(11) NOT NULL,
  `unique_id` varchar(256) DEFAULT NULL,
  `vlsm_instance_id` varchar(100) NOT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(100) DEFAULT NULL,
  `sample_code` varchar(100) DEFAULT NULL,
  `sample_reordered` varchar(256) NOT NULL DEFAULT 'no',
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(100) DEFAULT NULL,
  `remote_sample_code` varchar(100) DEFAULT NULL,
  `external_sample_code` varchar(256) DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `is_sample_recollected` varchar(11) DEFAULT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `is_sample_rejected` varchar(10) DEFAULT NULL,
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
  `is_encrypted` varchar(10) DEFAULT 'no',
  `sync_patient_identifiers` varchar(10) DEFAULT 'yes',
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
  `system_patient_code` varchar(43) DEFAULT NULL,
  `child_id` text,
  `child_name` text,
  `child_surname` text,
  `child_dob` date DEFAULT NULL,
  `child_age` int(11) DEFAULT NULL,
  `child_gender` varchar(10) DEFAULT NULL,
  `child_weight` int(11) DEFAULT NULL,
  `child_prophylactic_arv` text,
  `child_prophylactic_arv_other` text,
  `mother_hiv_test_date` date DEFAULT NULL,
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
  `serological_test` varchar(11) DEFAULT NULL,
  `pcr_1_test_date` date DEFAULT NULL,
  `pcr_1_test_result` varchar(50) DEFAULT NULL,
  `pcr_2_test_date` date DEFAULT NULL,
  `pcr_2_test_result` varchar(50) DEFAULT NULL,
  `pcr_3_test_date` date DEFAULT NULL,
  `pcr_3_test_result` varchar(50) DEFAULT NULL,
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
  `app_sample_code` varchar(100) DEFAULT NULL,
  `manual_result_entry` varchar(10) DEFAULT NULL,
  `import_machine_name` text,
  `import_machine_file_name` text,
  `result_printed_datetime` datetime DEFAULT NULL,
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
  `test_requested_on` date DEFAULT NULL,
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
  `result_sent_to_source` varchar(10) DEFAULT 'pending',
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`eid_id`,`revision`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_form_eid`
--

LOCK TABLES `audit_form_eid` WRITE;
/*!40000 ALTER TABLE `audit_form_eid` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_form_eid` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_form_generic`
--

DROP TABLE IF EXISTS `audit_form_generic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_form_generic` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(6) NOT NULL AUTO_INCREMENT,
  `dt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sample_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `test_type` int(11) DEFAULT NULL,
  `sub_tests` text,
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
  `system_patient_code` varchar(43) DEFAULT NULL,
  `patient_first_name` text,
  `patient_middle_name` text,
  `patient_last_name` text,
  `patient_attendant` text,
  `patient_nationality` int(11) DEFAULT NULL,
  `patient_province` text,
  `patient_district` text,
  `patient_group` text,
  `patient_id` varchar(256) DEFAULT NULL,
  `laboratory_number` varchar(100) DEFAULT NULL,
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
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
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
  `result_status` int(11) NOT NULL,
  PRIMARY KEY (`sample_id`,`revision`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_form_generic`
--

LOCK TABLES `audit_form_generic` WRITE;
/*!40000 ALTER TABLE `audit_form_generic` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_form_generic` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_form_hepatitis`
--

DROP TABLE IF EXISTS `audit_form_hepatitis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_form_hepatitis` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(6) NOT NULL AUTO_INCREMENT,
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
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `sample_condition` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `is_encrypted` varchar(10) DEFAULT 'no',
  `sync_patient_identifiers` varchar(10) DEFAULT 'yes',
  `system_patient_code` varchar(43) DEFAULT NULL,
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
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
  `test_requested_on` date DEFAULT NULL,
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
  `result_sent_to_source_datetime` datetime DEFAULT NULL,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `is_result_mail_sent` varchar(255) DEFAULT 'no',
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` text,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`hepatitis_id`,`revision`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_form_hepatitis`
--

LOCK TABLES `audit_form_hepatitis` WRITE;
/*!40000 ALTER TABLE `audit_form_hepatitis` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_form_hepatitis` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_form_tb`
--

DROP TABLE IF EXISTS `audit_form_tb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_form_tb` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(6) NOT NULL AUTO_INCREMENT,
  `dt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tb_id` int(11) NOT NULL,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` mediumtext,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_reordered` varchar(1000) NOT NULL DEFAULT 'no',
  `sample_code_key` int(11) NOT NULL,
  `sample_code_format` mediumtext,
  `sample_code` varchar(500) DEFAULT NULL,
  `external_sample_code` varchar(100) DEFAULT NULL,
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
  `requesting_clinician` varchar(100) DEFAULT NULL,
  `system_patient_code` varchar(43) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `referring_unit` varchar(256) DEFAULT NULL,
  `other_referring_unit` mediumtext,
  `is_encrypted` varchar(10) DEFAULT 'no',
  `sync_patient_identifiers` varchar(10) DEFAULT 'yes',
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
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
  `test_requested_on` date DEFAULT NULL,
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
  `result_sent_to_source_datetime` datetime DEFAULT NULL,
  `form_attributes` json DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`tb_id`,`revision`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_form_tb`
--

LOCK TABLES `audit_form_tb` WRITE;
/*!40000 ALTER TABLE `audit_form_tb` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_form_tb` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_form_vl`
--

DROP TABLE IF EXISTS `audit_form_vl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_form_vl` (
  `action` varchar(8) DEFAULT 'insert',
  `revision` int(6) NOT NULL AUTO_INCREMENT,
  `dt_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `vl_sample_id` int(11) NOT NULL,
  `unique_id` varchar(256) DEFAULT NULL,
  `vlsm_instance_id` varchar(100) NOT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `remote_sample_code` varchar(100) DEFAULT NULL,
  `external_sample_code` varchar(100) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `facility_sample_id` varchar(100) DEFAULT NULL,
  `sample_batch_id` varchar(11) DEFAULT NULL,
  `sample_package_id` int(11) DEFAULT NULL,
  `sample_package_code` text,
  `sample_reordered` varchar(45) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(100) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(100) DEFAULT NULL,
  `sample_code_title` varchar(45) NOT NULL DEFAULT 'auto',
  `sample_code` varchar(100) DEFAULT NULL,
  `test_urgency` varchar(10) DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `community_sample` varchar(10) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `system_patient_code` varchar(43) DEFAULT NULL,
  `patient_first_name` varchar(512) DEFAULT NULL,
  `patient_middle_name` varchar(512) DEFAULT NULL,
  `patient_last_name` varchar(512) DEFAULT NULL,
  `patient_responsible_person` text,
  `patient_nationality` int(11) DEFAULT NULL,
  `patient_province` text,
  `patient_district` text,
  `patient_group` text,
  `patient_art_no` varchar(512) DEFAULT NULL,
  `is_encrypted` varchar(10) DEFAULT 'no',
  `sync_patient_identifiers` varchar(10) DEFAULT 'yes',
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
  `date_of_initiation_of_current_regimen` date DEFAULT NULL,
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
  `request_clinician_phone_number` varchar(32) DEFAULT NULL,
  `cv_number` varchar(20) DEFAULT NULL,
  `sample_testing_date` datetime DEFAULT NULL,
  `vl_focal_person` text,
  `vl_focal_person_phone_number` text,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `is_sample_rejected` varchar(10) DEFAULT NULL,
  `sample_rejection_facility` int(11) DEFAULT NULL,
  `reason_for_sample_rejection` int(11) DEFAULT NULL,
  `recommended_corrective_action` int(11) DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `request_created_by` varchar(500) NOT NULL,
  `request_created_datetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_modified_by` text,
  `last_modified_datetime` datetime DEFAULT NULL,
  `patient_other_id` text,
  `patient_age_in_years` int(11) DEFAULT NULL,
  `patient_age_in_months` int(11) DEFAULT NULL,
  `treatment_initiated_date` date DEFAULT NULL,
  `treatment_duration` text,
  `treatment_indication` text,
  `patient_anc_no` varchar(100) DEFAULT NULL,
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
  `result_value_log` varchar(32) DEFAULT NULL,
  `result_value_absolute` varchar(32) DEFAULT NULL,
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
  `result_value_hiv_detection` varchar(32) DEFAULT NULL,
  `cphl_vl_result` varchar(32) DEFAULT NULL,
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
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
  `is_request_mail_sent` varchar(10) NOT NULL DEFAULT 'no',
  `request_mail_datetime` datetime DEFAULT NULL,
  `is_result_mail_sent` varchar(10) NOT NULL DEFAULT 'no',
  `app_sample_code` varchar(100) DEFAULT NULL,
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
  `locked` varchar(10) DEFAULT 'no',
  `import_machine_file_name` text,
  `manual_result_entry` varchar(10) DEFAULT NULL,
  `consultation` text,
  `first_line` varchar(32) DEFAULT NULL,
  `second_line` varchar(32) DEFAULT NULL,
  `first_viral_load` varchar(10) DEFAULT NULL,
  `collection_type` varchar(100) DEFAULT NULL,
  `sample_processed` varchar(10) DEFAULT NULL,
  `vl_result_category` text,
  `vldash_sync` int(11) DEFAULT '0',
  `source_of_request` text,
  `source_data_dump` text,
  `result_sent_to_source` varchar(10) DEFAULT 'pending',
  `result_sent_to_source_datetime` datetime DEFAULT NULL,
  `form_attributes` json DEFAULT NULL,
  `source` varchar(100) DEFAULT 'manual',
  `ward` varchar(100) DEFAULT NULL,
  `art_cd_cells` varchar(100) DEFAULT NULL,
  `art_cd_date` date DEFAULT NULL,
  `who_clinical_stage` varchar(100) DEFAULT NULL,
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
  `failed_test_tech` varchar(100) DEFAULT NULL,
  `failed_vl_result` varchar(32) DEFAULT NULL,
  `reason_for_failure` int(11) DEFAULT NULL,
  `failed_batch_quality` varchar(32) DEFAULT NULL,
  `failed_sample_test_quality` varchar(32) DEFAULT NULL,
  `failed_batch_id` varchar(32) DEFAULT NULL,
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
  `collection_site` varchar(10) DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  `remote_sample` varchar(10) NOT NULL DEFAULT 'no',
  `recency_vl` varchar(10) NOT NULL DEFAULT 'no',
  `recency_sync` int(11) DEFAULT '0',
  PRIMARY KEY (`vl_sample_id`,`revision`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_form_vl`
--

LOCK TABLES `audit_form_vl` WRITE;
/*!40000 ALTER TABLE `audit_form_vl` DISABLE KEYS */;
/*!40000 ALTER TABLE `audit_form_vl` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `batch_details`
--

DROP TABLE IF EXISTS `batch_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `batch_details` (
  `batch_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `last_modified_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `batch_details`
--

LOCK TABLES `batch_details` WRITE;
/*!40000 ALTER TABLE `batch_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `batch_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `covid19_imported_controls`
--

DROP TABLE IF EXISTS `covid19_imported_controls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `covid19_imported_controls` (
  `control_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `imported_date_time` datetime DEFAULT NULL,
  `import_machine_file_name` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`control_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `covid19_imported_controls`
--

LOCK TABLES `covid19_imported_controls` WRITE;
/*!40000 ALTER TABLE `covid19_imported_controls` DISABLE KEYS */;
/*!40000 ALTER TABLE `covid19_imported_controls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `covid19_patient_comorbidities`
--

DROP TABLE IF EXISTS `covid19_patient_comorbidities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `covid19_patient_comorbidities` (
  `covid19_id` int(11) NOT NULL,
  `comorbidity_id` int(11) NOT NULL,
  `comorbidity_detected` varchar(255) NOT NULL,
  PRIMARY KEY (`covid19_id`,`comorbidity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `covid19_patient_comorbidities`
--

LOCK TABLES `covid19_patient_comorbidities` WRITE;
/*!40000 ALTER TABLE `covid19_patient_comorbidities` DISABLE KEYS */;
/*!40000 ALTER TABLE `covid19_patient_comorbidities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `covid19_patient_symptoms`
--

DROP TABLE IF EXISTS `covid19_patient_symptoms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `covid19_patient_symptoms` (
  `covid19_id` int(11) NOT NULL,
  `symptom_id` int(11) NOT NULL,
  `symptom_detected` varchar(255) NOT NULL,
  `symptom_details` mediumtext,
  PRIMARY KEY (`covid19_id`,`symptom_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `covid19_patient_symptoms`
--

LOCK TABLES `covid19_patient_symptoms` WRITE;
/*!40000 ALTER TABLE `covid19_patient_symptoms` DISABLE KEYS */;
/*!40000 ALTER TABLE `covid19_patient_symptoms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `covid19_positive_confirmation_manifest`
--

DROP TABLE IF EXISTS `covid19_positive_confirmation_manifest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `covid19_positive_confirmation_manifest` (
  `manifest_id` int(11) NOT NULL AUTO_INCREMENT,
  `manifest_code` varchar(255) NOT NULL,
  `added_by` varchar(255) NOT NULL,
  `manifest_status` varchar(255) DEFAULT NULL,
  `module` varchar(255) DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`manifest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `covid19_positive_confirmation_manifest`
--

LOCK TABLES `covid19_positive_confirmation_manifest` WRITE;
/*!40000 ALTER TABLE `covid19_positive_confirmation_manifest` DISABLE KEYS */;
/*!40000 ALTER TABLE `covid19_positive_confirmation_manifest` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `covid19_reasons_for_testing`
--

DROP TABLE IF EXISTS `covid19_reasons_for_testing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `covid19_reasons_for_testing` (
  `covid19_id` int(11) NOT NULL,
  `reasons_id` int(11) NOT NULL,
  `reasons_detected` varchar(50) DEFAULT NULL,
  `reason_details` text,
  PRIMARY KEY (`covid19_id`,`reasons_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `covid19_reasons_for_testing`
--

LOCK TABLES `covid19_reasons_for_testing` WRITE;
/*!40000 ALTER TABLE `covid19_reasons_for_testing` DISABLE KEYS */;
/*!40000 ALTER TABLE `covid19_reasons_for_testing` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `covid19_tests`
--

DROP TABLE IF EXISTS `covid19_tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `covid19_tests` (
  `test_id` int(11) NOT NULL AUTO_INCREMENT,
  `covid19_id` int(11) NOT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `test_name` varchar(500) NOT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime NOT NULL,
  `testing_platform` varchar(255) DEFAULT NULL,
  `kit_lot_no` varchar(256) DEFAULT NULL,
  `kit_expiry_date` date DEFAULT NULL,
  `result` varchar(500) NOT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_id`),
  KEY `covid19_id` (`covid19_id`),
  CONSTRAINT `covid19_tests_ibfk_1` FOREIGN KEY (`covid19_id`) REFERENCES `form_covid19` (`covid19_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `covid19_tests`
--

LOCK TABLES `covid19_tests` WRITE;
/*!40000 ALTER TABLE `covid19_tests` DISABLE KEYS */;
/*!40000 ALTER TABLE `covid19_tests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eid_imported_controls`
--

DROP TABLE IF EXISTS `eid_imported_controls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eid_imported_controls` (
  `control_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `imported_date_time` datetime DEFAULT NULL,
  `import_machine_file_name` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`control_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eid_imported_controls`
--

LOCK TABLES `eid_imported_controls` WRITE;
/*!40000 ALTER TABLE `eid_imported_controls` DISABLE KEYS */;
/*!40000 ALTER TABLE `eid_imported_controls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facility_details`
--

DROP TABLE IF EXISTS `facility_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `facility_details` (
  `facility_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `report_format` mediumtext,
  PRIMARY KEY (`facility_id`),
  UNIQUE KEY `facility_name` (`facility_name`),
  UNIQUE KEY `other_id` (`other_id`),
  UNIQUE KEY `facility_name_2` (`facility_name`),
  UNIQUE KEY `other_id_2` (`other_id`),
  UNIQUE KEY `facility_code` (`facility_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facility_details`
--

LOCK TABLES `facility_details` WRITE;
/*!40000 ALTER TABLE `facility_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `facility_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `facility_type`
--

DROP TABLE IF EXISTS `facility_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `facility_type` (
  `facility_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `facility_type_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`facility_type_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `facility_type`
--

LOCK TABLES `facility_type` WRITE;
/*!40000 ALTER TABLE `facility_type` DISABLE KEYS */;
INSERT INTO `facility_type` VALUES (1,'Health Facility'),(2,'Testing Lab'),(3,'Collection Site');
/*!40000 ALTER TABLE `facility_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_result_retest_tracker`
--

DROP TABLE IF EXISTS `failed_result_retest_tracker`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_result_retest_tracker` (
  `frrt_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_by` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`frrt_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_result_retest_tracker`
--

LOCK TABLES `failed_result_retest_tracker` WRITE;
/*!40000 ALTER TABLE `failed_result_retest_tracker` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_result_retest_tracker` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `form_covid19`
--

DROP TABLE IF EXISTS `form_covid19`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_covid19` (
  `covid19_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
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
  `is_encrypted` varchar(10) DEFAULT 'no',
  `sync_patient_identifiers` varchar(10) DEFAULT 'yes',
  `system_patient_code` varchar(43) DEFAULT NULL,
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
  `test_requested_on` date DEFAULT NULL,
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
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
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
  `result_sent_to_source_datetime` datetime DEFAULT NULL,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `is_result_mail_sent` varchar(255) DEFAULT 'no',
  `app_sample_code` varchar(255) DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` varchar(255) DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`covid19_id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  UNIQUE KEY `sample_code` (`sample_code`,`lab_id`),
  UNIQUE KEY `lab_id` (`lab_id`,`app_sample_code`),
  KEY `last_modified_datetime` (`last_modified_datetime`),
  KEY `sample_code_key` (`sample_code_key`),
  KEY `remote_sample_code_key` (`remote_sample_code_key`),
  KEY `sample_package_id` (`sample_package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_covid19`
--

LOCK TABLES `form_covid19` WRITE;
/*!40000 ALTER TABLE `form_covid19` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_covid19` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_covid19_data__ai AFTER INSERT ON `form_covid19` FOR EACH ROW
    INSERT INTO `audit_form_covid19` SELECT 'insert', NULL, NOW(), d.*
    FROM `form_covid19` AS d WHERE d.covid19_id = NEW.covid19_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_covid19_data__au AFTER UPDATE ON `form_covid19` FOR EACH ROW
    INSERT INTO `audit_form_covid19` SELECT 'update', NULL, NOW(), d.*
    FROM `form_covid19` AS d WHERE d.covid19_id = NEW.covid19_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_covid19_data__bd BEFORE DELETE ON `form_covid19` FOR EACH ROW
    INSERT INTO `audit_form_covid19` SELECT 'delete', NULL, NOW(), d.*
    FROM `form_covid19` AS d WHERE d.covid19_id = OLD.covid19_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `form_eid`
--

DROP TABLE IF EXISTS `form_eid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_eid` (
  `eid_id` int(11) NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(256) DEFAULT NULL,
  `vlsm_instance_id` varchar(100) NOT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(100) DEFAULT NULL,
  `sample_code` varchar(100) DEFAULT NULL,
  `sample_reordered` varchar(256) NOT NULL DEFAULT 'no',
  `remote_sample` varchar(255) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(100) DEFAULT NULL,
  `remote_sample_code` varchar(100) DEFAULT NULL,
  `external_sample_code` varchar(256) DEFAULT NULL,
  `sample_collection_date` datetime NOT NULL,
  `is_sample_recollected` varchar(11) DEFAULT NULL,
  `sample_dispatched_datetime` datetime DEFAULT NULL,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `is_sample_rejected` varchar(10) DEFAULT NULL,
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
  `is_encrypted` varchar(10) DEFAULT 'no',
  `sync_patient_identifiers` varchar(10) DEFAULT 'yes',
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
  `system_patient_code` varchar(43) DEFAULT NULL,
  `child_id` text,
  `child_name` text,
  `child_surname` text,
  `child_dob` date DEFAULT NULL,
  `child_age` int(11) DEFAULT NULL,
  `child_gender` varchar(10) DEFAULT NULL,
  `child_weight` int(11) DEFAULT NULL,
  `child_prophylactic_arv` text,
  `child_prophylactic_arv_other` text,
  `mother_hiv_test_date` date DEFAULT NULL,
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
  `serological_test` varchar(11) DEFAULT NULL,
  `pcr_1_test_date` date DEFAULT NULL,
  `pcr_1_test_result` varchar(50) DEFAULT NULL,
  `pcr_2_test_date` date DEFAULT NULL,
  `pcr_2_test_result` varchar(50) DEFAULT NULL,
  `pcr_3_test_date` date DEFAULT NULL,
  `pcr_3_test_result` varchar(50) DEFAULT NULL,
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
  `app_sample_code` varchar(100) DEFAULT NULL,
  `manual_result_entry` varchar(10) DEFAULT NULL,
  `import_machine_name` text,
  `import_machine_file_name` text,
  `result_printed_datetime` datetime DEFAULT NULL,
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
  `test_requested_on` date DEFAULT NULL,
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
  `result_sent_to_source` varchar(10) DEFAULT 'pending',
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`eid_id`),
  UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  UNIQUE KEY `sample_code` (`sample_code`,`lab_id`),
  UNIQUE KEY `lab_id` (`lab_id`,`app_sample_code`),
  KEY `last_modified_datetime` (`last_modified_datetime`),
  KEY `sample_code_key` (`sample_code_key`),
  KEY `remote_sample_code_key` (`remote_sample_code_key`),
  KEY `sample_package_id` (`sample_package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_eid`
--

LOCK TABLES `form_eid` WRITE;
/*!40000 ALTER TABLE `form_eid` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_eid` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_eid_data__ai AFTER INSERT ON `form_eid` FOR EACH ROW
    INSERT INTO `audit_form_eid` SELECT 'insert', NULL, NOW(), d.*
    FROM `form_eid` AS d WHERE d.eid_id = NEW.eid_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_eid_data__au AFTER UPDATE ON `form_eid` FOR EACH ROW
    INSERT INTO `audit_form_eid` SELECT 'update', NULL, NOW(), d.*
    FROM `form_eid` AS d WHERE d.eid_id = NEW.eid_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_eid_data__bd BEFORE DELETE ON `form_eid` FOR EACH ROW
    INSERT INTO `audit_form_eid` SELECT 'delete', NULL, NOW(), d.*
    FROM `form_eid` AS d WHERE d.eid_id = OLD.eid_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `form_generic`
--

DROP TABLE IF EXISTS `form_generic`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_generic` (
  `sample_id` int(11) NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(500) DEFAULT NULL,
  `test_type` int(11) DEFAULT NULL,
  `sub_tests` text,
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
  `system_patient_code` varchar(43) DEFAULT NULL,
  `patient_first_name` text,
  `patient_middle_name` text,
  `patient_last_name` text,
  `patient_attendant` text,
  `patient_nationality` int(11) DEFAULT NULL,
  `patient_province` text,
  `patient_district` text,
  `patient_group` text,
  `patient_id` varchar(256) DEFAULT NULL,
  `laboratory_number` varchar(100) DEFAULT NULL,
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
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
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
  `result_status` int(11) NOT NULL,
  PRIMARY KEY (`sample_id`),
  UNIQUE KEY `lab_id` (`lab_id`,`app_sample_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_generic`
--

LOCK TABLES `form_generic` WRITE;
/*!40000 ALTER TABLE `form_generic` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_generic` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `form_form_generic_data__ai` AFTER INSERT ON `form_generic` FOR EACH ROW
            INSERT INTO `audit_form_generic` SELECT 'ai', NULL, NOW(), d.*
            FROM `form_generic` AS d WHERE d.sample_id = NEW.sample_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_generic_data__ai AFTER INSERT ON `form_generic` FOR EACH ROW
    INSERT INTO `audit_form_generic` SELECT 'insert', NULL, NOW(), d.*
    FROM `form_generic` AS d WHERE d.sample_id = NEW.sample_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `form_form_generic_data__au` AFTER UPDATE ON `form_generic` FOR EACH ROW
            INSERT INTO `audit_form_generic` SELECT 'au', NULL, NOW(), d.*
            FROM `form_generic` AS d WHERE d.sample_id = NEW.sample_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_generic_data__au AFTER UPDATE ON `form_generic` FOR EACH ROW
    INSERT INTO `audit_form_generic` SELECT 'update', NULL, NOW(), d.*
    FROM `form_generic` AS d WHERE d.sample_id = NEW.sample_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_generic_data__bd BEFORE DELETE ON `form_generic` FOR EACH ROW
    INSERT INTO `audit_form_generic` SELECT 'delete', NULL, NOW(), d.*
    FROM `form_generic` AS d WHERE d.sample_id = OLD.sample_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `form_hepatitis`
--

DROP TABLE IF EXISTS `form_hepatitis`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_hepatitis` (
  `hepatitis_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `sample_condition` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `is_encrypted` varchar(10) DEFAULT 'no',
  `sync_patient_identifiers` varchar(10) DEFAULT 'yes',
  `system_patient_code` varchar(43) DEFAULT NULL,
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
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
  `test_requested_on` date DEFAULT NULL,
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
  `result_sent_to_source_datetime` datetime DEFAULT NULL,
  `form_attributes` json DEFAULT NULL,
  `lot_expiration_date` date DEFAULT NULL,
  `is_result_mail_sent` varchar(255) DEFAULT 'no',
  `last_modified_datetime` datetime DEFAULT NULL,
  `last_modified_by` text,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`hepatitis_id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  UNIQUE KEY `sample_code` (`sample_code`,`lab_id`),
  UNIQUE KEY `lab_id` (`lab_id`,`app_sample_code`),
  KEY `last_modified_datetime` (`last_modified_datetime`),
  KEY `sample_code_key` (`sample_code_key`),
  KEY `remote_sample_code_key` (`remote_sample_code_key`),
  KEY `sample_package_id` (`sample_package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_hepatitis`
--

LOCK TABLES `form_hepatitis` WRITE;
/*!40000 ALTER TABLE `form_hepatitis` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_hepatitis` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_hepatitis_data__ai AFTER INSERT ON `form_hepatitis` FOR EACH ROW
    INSERT INTO `audit_form_hepatitis` SELECT 'insert', NULL, NOW(), d.*
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = NEW.hepatitis_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_hepatitis_data__au AFTER UPDATE ON `form_hepatitis` FOR EACH ROW
    INSERT INTO `audit_form_hepatitis` SELECT 'update', NULL, NOW(), d.*
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = NEW.hepatitis_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_hepatitis_data__bd BEFORE DELETE ON `form_hepatitis` FOR EACH ROW
    INSERT INTO `audit_form_hepatitis` SELECT 'delete', NULL, NOW(), d.*
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = OLD.hepatitis_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `form_tb`
--

DROP TABLE IF EXISTS `form_tb`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_tb` (
  `tb_id` int(11) NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(500) DEFAULT NULL,
  `vlsm_instance_id` mediumtext,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `sample_reordered` varchar(1000) NOT NULL DEFAULT 'no',
  `sample_code_key` int(11) NOT NULL,
  `sample_code_format` mediumtext,
  `sample_code` varchar(500) DEFAULT NULL,
  `external_sample_code` varchar(100) DEFAULT NULL,
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
  `requesting_clinician` varchar(100) DEFAULT NULL,
  `system_patient_code` varchar(43) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `referring_unit` varchar(256) DEFAULT NULL,
  `other_referring_unit` mediumtext,
  `is_encrypted` varchar(10) DEFAULT 'no',
  `sync_patient_identifiers` varchar(10) DEFAULT 'yes',
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
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
  `test_requested_on` date DEFAULT NULL,
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
  `result_sent_to_source_datetime` datetime DEFAULT NULL,
  `form_attributes` json DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`tb_id`),
  UNIQUE KEY `sample_code` (`sample_code`,`lab_id`),
  UNIQUE KEY `unique_id` (`unique_id`),
  UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  UNIQUE KEY `lab_id_2` (`lab_id`,`app_sample_code`),
  KEY `facility_id` (`facility_id`),
  KEY `lab_id` (`lab_id`),
  KEY `sample_code_key` (`sample_code_key`),
  KEY `remote_sample_code_key` (`remote_sample_code_key`),
  KEY `sample_package_id` (`sample_package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_tb`
--

LOCK TABLES `form_tb` WRITE;
/*!40000 ALTER TABLE `form_tb` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_tb` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_tb_data__ai AFTER INSERT ON `form_tb` FOR EACH ROW
    INSERT INTO `audit_form_tb` SELECT 'insert', NULL, NOW(), d.*
    FROM `form_tb` AS d WHERE d.tb_id = NEW.tb_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_tb_data__au AFTER UPDATE ON `form_tb` FOR EACH ROW
    INSERT INTO `audit_form_tb` SELECT 'update', NULL, NOW(), d.*
    FROM `form_tb` AS d WHERE d.tb_id = NEW.tb_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_tb_data__bd BEFORE DELETE ON `form_tb` FOR EACH ROW
    INSERT INTO `audit_form_tb` SELECT 'delete', NULL, NOW(), d.*
    FROM `form_tb` AS d WHERE d.tb_id = OLD.tb_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `form_vl`
--

DROP TABLE IF EXISTS `form_vl`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `form_vl` (
  `vl_sample_id` int(11) NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(256) DEFAULT NULL,
  `vlsm_instance_id` varchar(100) NOT NULL,
  `vlsm_country_id` int(11) DEFAULT NULL,
  `remote_sample_code` varchar(100) DEFAULT NULL,
  `external_sample_code` varchar(100) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `province_id` int(11) DEFAULT NULL,
  `facility_sample_id` varchar(100) DEFAULT NULL,
  `sample_batch_id` varchar(11) DEFAULT NULL,
  `sample_package_id` int(11) DEFAULT NULL,
  `sample_package_code` text,
  `sample_reordered` varchar(45) NOT NULL DEFAULT 'no',
  `remote_sample_code_key` int(11) DEFAULT NULL,
  `remote_sample_code_format` varchar(100) DEFAULT NULL,
  `sample_code_key` int(11) DEFAULT NULL,
  `sample_code_format` varchar(100) DEFAULT NULL,
  `sample_code_title` varchar(45) NOT NULL DEFAULT 'auto',
  `sample_code` varchar(100) DEFAULT NULL,
  `test_urgency` varchar(10) DEFAULT NULL,
  `funding_source` int(11) DEFAULT NULL,
  `community_sample` varchar(10) DEFAULT NULL,
  `implementing_partner` int(11) DEFAULT NULL,
  `system_patient_code` varchar(43) DEFAULT NULL,
  `patient_first_name` varchar(512) DEFAULT NULL,
  `patient_middle_name` varchar(512) DEFAULT NULL,
  `patient_last_name` varchar(512) DEFAULT NULL,
  `patient_responsible_person` text,
  `patient_nationality` int(11) DEFAULT NULL,
  `patient_province` text,
  `patient_district` text,
  `patient_group` text,
  `patient_art_no` varchar(512) DEFAULT NULL,
  `is_encrypted` varchar(10) DEFAULT 'no',
  `sync_patient_identifiers` varchar(10) DEFAULT 'yes',
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
  `date_of_initiation_of_current_regimen` date DEFAULT NULL,
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
  `request_clinician_phone_number` varchar(32) DEFAULT NULL,
  `cv_number` varchar(20) DEFAULT NULL,
  `sample_testing_date` datetime DEFAULT NULL,
  `vl_focal_person` text,
  `vl_focal_person_phone_number` text,
  `sample_received_at_hub_datetime` datetime DEFAULT NULL,
  `sample_received_at_lab_datetime` datetime DEFAULT NULL,
  `result_dispatched_datetime` datetime DEFAULT NULL,
  `is_sample_rejected` varchar(10) DEFAULT NULL,
  `sample_rejection_facility` int(11) DEFAULT NULL,
  `reason_for_sample_rejection` int(11) DEFAULT NULL,
  `recommended_corrective_action` int(11) DEFAULT NULL,
  `rejection_on` date DEFAULT NULL,
  `request_created_by` varchar(500) NOT NULL,
  `request_created_datetime` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `last_modified_by` text,
  `last_modified_datetime` datetime DEFAULT NULL,
  `patient_other_id` text,
  `patient_age_in_years` int(11) DEFAULT NULL,
  `patient_age_in_months` int(11) DEFAULT NULL,
  `treatment_initiated_date` date DEFAULT NULL,
  `treatment_duration` text,
  `treatment_indication` text,
  `patient_anc_no` varchar(100) DEFAULT NULL,
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
  `result_value_log` varchar(32) DEFAULT NULL,
  `result_value_absolute` varchar(32) DEFAULT NULL,
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
  `result_value_hiv_detection` varchar(32) DEFAULT NULL,
  `cphl_vl_result` varchar(32) DEFAULT NULL,
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
  `result_printed_on_sts_datetime` datetime DEFAULT NULL,
  `result_printed_on_lis_datetime` datetime DEFAULT NULL,
  `is_request_mail_sent` varchar(10) NOT NULL DEFAULT 'no',
  `request_mail_datetime` datetime DEFAULT NULL,
  `is_result_mail_sent` varchar(10) NOT NULL DEFAULT 'no',
  `app_sample_code` varchar(100) DEFAULT NULL,
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
  `locked` varchar(10) DEFAULT 'no',
  `import_machine_file_name` text,
  `manual_result_entry` varchar(10) DEFAULT NULL,
  `consultation` text,
  `first_line` varchar(32) DEFAULT NULL,
  `second_line` varchar(32) DEFAULT NULL,
  `first_viral_load` varchar(10) DEFAULT NULL,
  `collection_type` varchar(100) DEFAULT NULL,
  `sample_processed` varchar(10) DEFAULT NULL,
  `vl_result_category` text,
  `vldash_sync` int(11) DEFAULT '0',
  `source_of_request` text,
  `source_data_dump` text,
  `result_sent_to_source` varchar(10) DEFAULT 'pending',
  `result_sent_to_source_datetime` datetime DEFAULT NULL,
  `form_attributes` json DEFAULT NULL,
  `source` varchar(100) DEFAULT 'manual',
  `ward` varchar(100) DEFAULT NULL,
  `art_cd_cells` varchar(100) DEFAULT NULL,
  `art_cd_date` date DEFAULT NULL,
  `who_clinical_stage` varchar(100) DEFAULT NULL,
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
  `failed_test_tech` varchar(100) DEFAULT NULL,
  `failed_vl_result` varchar(32) DEFAULT NULL,
  `reason_for_failure` int(11) DEFAULT NULL,
  `failed_batch_quality` varchar(32) DEFAULT NULL,
  `failed_sample_test_quality` varchar(32) DEFAULT NULL,
  `failed_batch_id` varchar(32) DEFAULT NULL,
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
  `collection_site` varchar(10) DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  `remote_sample` varchar(10) NOT NULL DEFAULT 'no',
  `recency_vl` varchar(10) NOT NULL DEFAULT 'no',
  `recency_sync` int(11) DEFAULT '0',
  PRIMARY KEY (`vl_sample_id`),
  UNIQUE KEY `remote_sample_code` (`remote_sample_code`),
  UNIQUE KEY `unique_id` (`unique_id`),
  UNIQUE KEY `lab_id_2` (`lab_id`,`app_sample_code`),
  KEY `facility_id` (`facility_id`),
  KEY `art_no` (`patient_art_no`),
  KEY `sample_id` (`sample_type`),
  KEY `created_by` (`request_created_by`),
  KEY `sample_collection_date` (`sample_collection_date`),
  KEY `sample_tested_datetime` (`sample_tested_datetime`),
  KEY `lab_id` (`lab_id`),
  KEY `result_status` (`result_status`),
  KEY `last_modified_datetime` (`last_modified_datetime`),
  KEY `sample_code_key` (`sample_code_key`),
  KEY `remote_sample_code_key` (`remote_sample_code_key`),
  KEY `result_approved_by` (`result_approved_by`),
  KEY `result_reviewed_by` (`result_reviewed_by`),
  KEY `sample_reordered` (`sample_reordered`),
  KEY `result_approved_by_2` (`result_approved_by`),
  KEY `result_reviewed_by_2` (`result_reviewed_by`),
  KEY `sample_package_id` (`sample_package_id`),
  KEY `patient_first_name` (`patient_first_name`),
  KEY `patient_middle_name` (`patient_middle_name`),
  KEY `patient_last_name` (`patient_last_name`),
  CONSTRAINT `form_vl_ibfk_5` FOREIGN KEY (`result_status`) REFERENCES `r_sample_status` (`status_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `form_vl`
--

LOCK TABLES `form_vl` WRITE;
/*!40000 ALTER TABLE `form_vl` DISABLE KEYS */;
/*!40000 ALTER TABLE `form_vl` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_vl_data__ai AFTER INSERT ON `form_vl` FOR EACH ROW
    INSERT INTO `audit_form_vl` SELECT 'insert', NULL, NOW(), d.*
    FROM `form_vl` AS d WHERE d.vl_sample_id = NEW.vl_sample_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_vl_data__au AFTER UPDATE ON `form_vl` FOR EACH ROW
    INSERT INTO `audit_form_vl` SELECT 'update', NULL, NOW(), d.*
    FROM `form_vl` AS d WHERE d.vl_sample_id = NEW.vl_sample_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER form_vl_data__bd BEFORE DELETE ON `form_vl` FOR EACH ROW
    INSERT INTO `audit_form_vl` SELECT 'delete', NULL, NOW(), d.*
    FROM `form_vl` AS d WHERE d.vl_sample_id = OLD.vl_sample_id */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `generic_sample_rejection_reason_map`
--

DROP TABLE IF EXISTS `generic_sample_rejection_reason_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `generic_sample_rejection_reason_map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `rejection_reason_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `generic_sample_rejection_reason_map`
--

LOCK TABLES `generic_sample_rejection_reason_map` WRITE;
/*!40000 ALTER TABLE `generic_sample_rejection_reason_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `generic_sample_rejection_reason_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `generic_test_failure_reason_map`
--

DROP TABLE IF EXISTS `generic_test_failure_reason_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `generic_test_failure_reason_map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_failure_reason_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  KEY `test_type_id` (`test_type_id`),
  KEY `test_reason_id` (`test_failure_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `generic_test_failure_reason_map`
--

LOCK TABLES `generic_test_failure_reason_map` WRITE;
/*!40000 ALTER TABLE `generic_test_failure_reason_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `generic_test_failure_reason_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `generic_test_methods_map`
--

DROP TABLE IF EXISTS `generic_test_methods_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `generic_test_methods_map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_method_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  KEY `test_type_id` (`test_type_id`),
  KEY `test_method_id` (`test_method_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `generic_test_methods_map`
--

LOCK TABLES `generic_test_methods_map` WRITE;
/*!40000 ALTER TABLE `generic_test_methods_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `generic_test_methods_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `generic_test_reason_map`
--

DROP TABLE IF EXISTS `generic_test_reason_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `generic_test_reason_map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_reason_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  KEY `test_type_id` (`test_type_id`),
  KEY `test_reason_id` (`test_reason_id`),
  CONSTRAINT `generic_test_reason_map_ibfk_1` FOREIGN KEY (`test_type_id`) REFERENCES `r_test_types` (`test_type_id`),
  CONSTRAINT `generic_test_reason_map_ibfk_2` FOREIGN KEY (`test_reason_id`) REFERENCES `r_generic_test_reasons` (`test_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `generic_test_reason_map`
--

LOCK TABLES `generic_test_reason_map` WRITE;
/*!40000 ALTER TABLE `generic_test_reason_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `generic_test_reason_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `generic_test_result_units_map`
--

DROP TABLE IF EXISTS `generic_test_result_units_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `generic_test_result_units_map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  KEY `test_type_id` (`test_type_id`),
  KEY `unit_id` (`unit_id`),
  CONSTRAINT `generic_test_result_units_map_ibfk_1` FOREIGN KEY (`test_type_id`) REFERENCES `r_test_types` (`test_type_id`),
  CONSTRAINT `generic_test_result_units_map_ibfk_2` FOREIGN KEY (`unit_id`) REFERENCES `r_generic_test_result_units` (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `generic_test_result_units_map`
--

LOCK TABLES `generic_test_result_units_map` WRITE;
/*!40000 ALTER TABLE `generic_test_result_units_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `generic_test_result_units_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `generic_test_results`
--

DROP TABLE IF EXISTS `generic_test_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `generic_test_results` (
  `test_id` int(11) NOT NULL AUTO_INCREMENT,
  `generic_id` int(11) NOT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `sub_test_name` varchar(256) DEFAULT NULL,
  `final_result_unit` varchar(256) DEFAULT NULL,
  `result_type` varchar(256) DEFAULT NULL,
  `test_name` varchar(500) NOT NULL,
  `tested_by` varchar(255) DEFAULT NULL,
  `sample_tested_datetime` datetime NOT NULL,
  `testing_platform` varchar(255) DEFAULT NULL,
  `kit_lot_no` varchar(256) DEFAULT NULL,
  `kit_expiry_date` date DEFAULT NULL,
  `result` varchar(500) NOT NULL,
  `final_result` varchar(256) DEFAULT NULL,
  `result_unit` int(11) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_id`),
  KEY `generic_id` (`generic_id`),
  CONSTRAINT `generic_test_results_ibfk_1` FOREIGN KEY (`generic_id`) REFERENCES `form_generic` (`sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `generic_test_results`
--

LOCK TABLES `generic_test_results` WRITE;
/*!40000 ALTER TABLE `generic_test_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `generic_test_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `generic_test_sample_type_map`
--

DROP TABLE IF EXISTS `generic_test_sample_type_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `generic_test_sample_type_map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `sample_type_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  KEY `sample_type_id` (`sample_type_id`),
  KEY `test_type_id` (`test_type_id`),
  CONSTRAINT `generic_test_sample_type_map_ibfk_1` FOREIGN KEY (`sample_type_id`) REFERENCES `r_generic_sample_types` (`sample_type_id`),
  CONSTRAINT `generic_test_sample_type_map_ibfk_2` FOREIGN KEY (`test_type_id`) REFERENCES `r_test_types` (`test_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `generic_test_sample_type_map`
--

LOCK TABLES `generic_test_sample_type_map` WRITE;
/*!40000 ALTER TABLE `generic_test_sample_type_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `generic_test_sample_type_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `generic_test_symptoms_map`
--

DROP TABLE IF EXISTS `generic_test_symptoms_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `generic_test_symptoms_map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `symptom_id` int(11) NOT NULL,
  `test_type_id` int(11) NOT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  KEY `symptom_id` (`symptom_id`),
  KEY `test_type_id` (`test_type_id`),
  CONSTRAINT `generic_test_symptoms_map_ibfk_1` FOREIGN KEY (`symptom_id`) REFERENCES `r_generic_symptoms` (`symptom_id`),
  CONSTRAINT `generic_test_symptoms_map_ibfk_2` FOREIGN KEY (`test_type_id`) REFERENCES `r_test_types` (`test_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `generic_test_symptoms_map`
--

LOCK TABLES `generic_test_symptoms_map` WRITE;
/*!40000 ALTER TABLE `generic_test_symptoms_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `generic_test_symptoms_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `geographical_divisions`
--

DROP TABLE IF EXISTS `geographical_divisions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `geographical_divisions` (
  `geo_id` int(11) NOT NULL AUTO_INCREMENT,
  `geo_name` varchar(256) DEFAULT NULL,
  `geo_code` varchar(256) DEFAULT NULL,
  `geo_parent` varchar(256) NOT NULL DEFAULT '0',
  `geo_status` varchar(256) DEFAULT NULL,
  `created_by` varchar(256) DEFAULT NULL,
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`geo_id`),
  UNIQUE KEY `geo_name` (`geo_name`,`geo_parent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `geographical_divisions`
--

LOCK TABLES `geographical_divisions` WRITE;
/*!40000 ALTER TABLE `geographical_divisions` DISABLE KEYS */;
/*!40000 ALTER TABLE `geographical_divisions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `global_config`
--

DROP TABLE IF EXISTS `global_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `global_config` (
  `display_name` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` longtext,
  `category` varchar(255) DEFAULT NULL,
  `remote_sync_needed` varchar(50) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  `updated_by` mediumtext,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `global_config`
--

LOCK TABLES `global_config` WRITE;
/*!40000 ALTER TABLE `global_config` DISABLE KEYS */;
INSERT INTO `global_config` VALUES ('App Locale/Language','app_locale','en_EN','common','no',NULL,NULL,'active'),('App Menu Name','app_menu_name','VLSM','app','no','2022-02-18 16:28:05',NULL,'active'),('Auto Approval','auto_approval','yes','general','no','2022-02-18 16:28:05',NULL,'inactive'),('Barcode Format','barcode_format','C39','general','no','2022-02-18 16:28:05','daemon','active'),('Barcode Printing','bar_code_printing','off','general','no','2022-02-18 16:28:05','daemon','active'),('COVID-19 Auto Approve API Results','covid19_auto_approve_api_results','no','covid19','no',NULL,NULL,'active'),('Generate Patient Code','covid19_generate_patient_code','no','covid19','no','2022-02-18 16:28:05',NULL,'active'),('Covid-19 Maximum Length','covid19_max_length','','covid19','no','2022-02-18 16:28:05',NULL,'active'),('Covid-19 Minimum Length','covid19_min_length','','covid19','no','2022-02-18 16:28:05',NULL,'active'),('Minimum Patient ID Length','covid19_min_patient_id_length',NULL,'covid19','no',NULL,NULL,'active'),('Patient Code Prefix','covid19_patient_code_prefix','P','covid19','no','2022-02-18 16:28:05',NULL,'active'),('Positive Confirmatory Tests Required By Central Lab','covid19_positive_confirmatory_tests_required_by_central_lab','yes','covid19','no','2022-02-18 16:28:05',NULL,'active'),('COVID-19 Report QR Code','covid19_report_qr_code','no',NULL,'no','2022-02-18 16:28:05',NULL,'active'),('Report Type','covid19_report_type','default','covid19','no','2022-02-18 16:28:05',NULL,'active'),('Covid-19 Sample Code Format','covid19_sample_code','MMYY','covid19','no','2022-02-18 16:28:05',NULL,'active'),('Covid-19 Sample Code Prefix','covid19_sample_code_prefix','C19','covid19','no','2022-02-18 16:28:05',NULL,'active'),('Covid19 Sample Expiry Days','covid19_sample_expiry_after_days','999','covid19','no','2022-02-18 16:28:05',NULL,'active'),('Covid19 Sample Lock Expiry Days','covid19_sample_lock_after_days','999','covid19','no','2022-02-18 16:28:05',NULL,'active'),('Show Participant Name in Manifest','covid19_show_participant_name_in_manifest','yes','COVID19','no',NULL,NULL,'active'),('Covid19 Tests Table in Results Pdf','covid19_tests_table_in_results_pdf','no','covid19','no','2022-02-18 16:28:05',NULL,'active'),('Data Sync Interval','data_sync_interval','30','general','no','2022-02-18 16:28:05',NULL,'active'),('CSV Delimiter','default_csv_delimiter',',','general','no',NULL,NULL,'active'),('CSV Enclosure','default_csv_enclosure','\"','general','no',NULL,NULL,'active'),('Default Phone Prefix','default_phone_prefix',NULL,'general','no',NULL,NULL,'active'),('Default Time Zone','default_time_zone','UTC','general','no','2022-02-18 16:28:05','daemon','active'),('Display Encrypt PII Option','display_encrypt_pii_option','no','general','no',NULL,NULL,'active'),('Edit Profile','edit_profile','yes','general','no','2022-02-18 16:28:05','daemon','active'),('EID Auto Approve API Results','eid_auto_approve_api_results','no','eid','no',NULL,NULL,'active'),('EID Maximum Length','eid_max_length','','eid','no','2022-02-18 16:28:05','daemon','active'),('EID Minimum Length','eid_min_length','','eid','no','2022-02-18 16:28:05','daemon','active'),('Minimum Patient ID Length','eid_min_patient_id_length',NULL,'eid','no',NULL,NULL,'active'),('EID Report QR Code','eid_report_qr_code','yes','EID','no',NULL,NULL,'active'),('EID Sample Code','eid_sample_code','MMYY','eid','no','2022-02-18 16:28:05','daemon','active'),('EID Sample Code Prefix','eid_sample_code_prefix','EID','eid','no','2022-02-18 16:28:05','daemon','active'),('EID Sample Expiry Days','eid_sample_expiry_after_days','999','eid','no','2022-02-18 16:28:05',NULL,'active'),('EID Sample Lock Expiry Days','eid_sample_lock_after_days','999','eid','no','2022-02-18 16:28:05',NULL,'active'),('Show Participant Name in Manifest','eid_show_participant_name_in_manifest','yes','EID','no',NULL,NULL,'active'),('Enable QR Code Mechanism','enable_qr_mechanism','no','general','no','2022-02-18 16:28:05',NULL,'inactive'),('Auto Approve API Results','generic_auto_approve_api_results',NULL,'generic-tests','yes','2021-11-02 17:48:32',NULL,'active'),('Generic Maximum Length','generic_max_length',NULL,'generic-tests','yes','2021-11-02 18:16:53',NULL,'active'),('Generic Minimum Length','generic_min_length',NULL,'generic-tests','yes','2021-11-02 18:16:53',NULL,'active'),('Minimum Patient ID Length','generic_min_patient_id_length',NULL,'generic','no',NULL,NULL,'active'),('Generic Sample Code Format','generic_sample_code','MMYY','generic-tests','yes','2021-11-02 17:48:32',NULL,'active'),('Sample Lock Expiry Days','generic_sample_lock_after_days',NULL,'generic-tests','yes','2021-11-02 17:48:32',NULL,'active'),('Lab Tests Show Participant Name in Manifest','generic_show_participant_name_in_manifest',NULL,'generic-tests','yes','2021-11-02 17:48:32',NULL,'active'),('Date Format','gui_date_format','d-M-Y','general','no',NULL,NULL,'active'),('Header','header','MINISTRY OF HEALTH','general','no','2022-02-18 16:28:05','daemon','active'),('Hepatitis Auto Approve API Results','hepatitis_auto_approve_api_results','no','hepatitis','no',NULL,NULL,'active'),('Minimum Patient ID Length','hepatitis_min_patient_id_length',NULL,'hepatitis','no',NULL,NULL,'active'),('Hepatitis Report QR Code','hepatitis_report_qr_code','yes',NULL,NULL,NULL,NULL,'active'),('Hepatitis Sample Code Format','hepatitis_sample_code','MMYY','hepatitis','no','2022-02-18 16:28:05',NULL,'active'),('Hepatitis Sample Code Prefix','hepatitis_sample_code_prefix','HEP','hepatitis','no','2022-02-18 16:28:05',NULL,'active'),('Hepatitis Sample Expiry Days','hepatitis_sample_expiry_after_days','999','hepatitis','no','2022-02-18 16:28:05',NULL,'active'),('Hepatitis Sample Lock Expiry Days','hepatitis_sample_lock_after_days','999','hepatitis','no','2022-02-18 16:28:05',NULL,'active'),('Show Participant Name in Manifest','hepatitis_show_participant_name_in_manifest','yes','HEPATITIS','no',NULL,NULL,'active'),('Result PDF High Viral Load Message','h_vl_msg','','vl','no','2022-02-18 16:28:05','daemon','active'),('Import Non matching Sample Results from Machine generated file','import_non_matching_sample','yes','general','no','2022-02-18 16:28:05','daemon','active'),('Instance Type ','instance_type','Both','general','no','2022-02-18 16:28:05','daemon','active'),('Key','key',NULL,'general','yes',NULL,NULL,'active'),('Lock Approved Covid-19 Samples','lock_approved_covid19_samples','no','covid19','no','2022-02-18 16:28:05',NULL,'active'),('Lock Approved EID Samples','lock_approved_eid_samples','no','eid','no','2022-02-18 16:28:05',NULL,'active'),('Lock Approved TB Samples','lock_approved_tb_samples','no','tb','no','2022-02-18 16:28:05',NULL,'active'),('Lock approved VL Samples','lock_approved_vl_samples','no','vl','no','2022-02-18 16:28:05',NULL,'active'),('Logo','logo',NULL,'general','no','2022-02-18 16:28:05','daemon','active'),('Low Viral Load (text results)','low_vl_text_results','Target Not Detected, TND, < 20, < 40','vl','yes','2022-02-18 16:28:05',NULL,'active'),('Result PDF Low Viral Load Message','l_vl_msg','','vl','yes','2022-02-18 16:28:05','daemon','active'),('Manager Email','manager_email','','general','no','2022-02-18 16:28:05','daemon','active'),('Maximum Length','max_length','','vl','no','2022-02-18 16:28:05','daemon','active'),('Maximum Length of Phone Number','max_phone_length',NULL,'general','no',NULL,NULL,'active'),('Minimum Length','min_length','','vl','no','2022-02-18 16:28:05','daemon','active'),('Minimum Length of Phone Number','min_phone_length',NULL,'general','no',NULL,NULL,'active'),('Patient Name in Result PDF','patient_name_pdf','flname','general','no','2022-02-18 16:28:05','daemon','active'),('Result PDF Mandatory Fields','r_mandatory_fields',NULL,'vl','yes','2022-02-18 16:28:05',NULL,'active'),('Sample Code','sample_code','MMYY','vl','no','2022-02-18 16:28:05','daemon','active'),('Sample Code Prefix','sample_code_prefix','VL','general','no','2022-02-18 16:28:05','daemon','active'),('Sample Type','sample_type','enabled',NULL,'no','2022-02-18 16:28:05',NULL,'active'),('Patient ART No. Date','show_date','no','vl','no','2022-02-18 16:28:05','daemon','active'),('Do you want to show emoticons on the result pdf?','show_smiley','yes','general','no','2022-02-18 16:28:05','daemon','active'),('Support Email','support_email','','general','no',NULL,'','active'),('TB Auto Approve API Results','tb_auto_approve_api_results','no','tb','no',NULL,NULL,'active'),('TB Maximum Length','tb_max_length',NULL,'tb','no','2022-02-18 16:28:05',NULL,'active'),('TB Minimum Length','tb_min_length',NULL,'tb','no','2022-02-18 16:28:05',NULL,'active'),('Minimum Patient ID Length','tb_min_patient_id_length',NULL,'tb','no',NULL,NULL,'active'),('TB Sample Code Format','tb_sample_code','MMYY','tb','no','2022-02-18 16:28:05',NULL,'active'),('TB Sample Code Prefix','tb_sample_code_prefix','TB','tb','no','2022-02-18 16:28:05',NULL,'active'),('TB Sample Expiry Days','tb_sample_expiry_after_days','999','tb','no','2022-02-18 16:28:05',NULL,'active'),('TB Sample Lock Expiry Days','tb_sample_lock_after_days','999','tb','no','2022-02-18 16:28:05',NULL,'active'),('Show Participant Name in Manifest','tb_show_participant_name_in_manifest','yes','TB','no',NULL,NULL,'active'),('Testing Status','testing_status','enabled','vl','no','2022-02-18 16:28:05',NULL,'active'),('Training Mode','training_mode','no','common','no','2023-10-16 17:03:43',NULL,'active'),('Training Mode Text','training_mode_text','TRAINING SERVER','common','no','2023-10-16 17:03:43',NULL,'active'),('Same user can Review and Approve','user_review_approve','yes','general','no','2022-02-18 16:28:05','daemon','active'),('Viral Load Threshold Limit','viral_load_threshold_limit','1000','vl','no','2022-02-18 16:28:05','daemon','active'),('Vldashboard Url','vldashboard_url',NULL,'general','yes','2022-02-18 16:28:05','daemon','active'),('VL Auto Approve API Results','vl_auto_approve_api_results','no','vl','no',NULL,NULL,'active'),('Viral Load Export Format','vl_excel_export_format','default','VL','no',NULL,'','active'),('Viral Load Form','vl_form',NULL,'general','no','2022-02-18 16:28:05','daemon','active'),('Interpret and Convert VL Results','vl_interpret_and_convert_results','no','VL','yes',NULL,NULL,'active'),('Minimum Patient ID Length','vl_min_patient_id_length',NULL,'vl','no',NULL,NULL,'active'),('VL Monthly Target','vl_monthly_target','no','vl','no','2022-02-18 16:28:05','','active'),('VL Report QR Code','vl_report_qr_code','yes','vl','no',NULL,NULL,'active'),('VL Sample Expiry Days','vl_sample_expiry_after_days','999','vl','no','2022-02-18 16:28:05',NULL,'active'),('VL Sample Lock Expiry Days','vl_sample_lock_after_days','999','vl','no','2022-02-18 16:28:05',NULL,'active'),('Show Participant Name in Manifest','vl_show_participant_name_in_manifest','yes','VL','no',NULL,NULL,'active');
/*!40000 ALTER TABLE `global_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `health_facilities`
--

DROP TABLE IF EXISTS `health_facilities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `health_facilities` (
  `test_type` enum('vl','eid','covid19','hepatitis','tb','generic-tests') NOT NULL,
  `facility_id` int(11) NOT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`test_type`,`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `health_facilities`
--

LOCK TABLES `health_facilities` WRITE;
/*!40000 ALTER TABLE `health_facilities` DISABLE KEYS */;
/*!40000 ALTER TABLE `health_facilities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hepatitis_patient_comorbidities`
--

DROP TABLE IF EXISTS `hepatitis_patient_comorbidities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hepatitis_patient_comorbidities` (
  `hepatitis_id` int(11) NOT NULL,
  `comorbidity_id` int(11) NOT NULL,
  `comorbidity_detected` varchar(255) NOT NULL,
  PRIMARY KEY (`hepatitis_id`,`comorbidity_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hepatitis_patient_comorbidities`
--

LOCK TABLES `hepatitis_patient_comorbidities` WRITE;
/*!40000 ALTER TABLE `hepatitis_patient_comorbidities` DISABLE KEYS */;
/*!40000 ALTER TABLE `hepatitis_patient_comorbidities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hepatitis_risk_factors`
--

DROP TABLE IF EXISTS `hepatitis_risk_factors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hepatitis_risk_factors` (
  `hepatitis_id` int(11) NOT NULL,
  `riskfactors_id` int(11) NOT NULL,
  `riskfactors_detected` varchar(255) NOT NULL,
  PRIMARY KEY (`hepatitis_id`,`riskfactors_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hepatitis_risk_factors`
--

LOCK TABLES `hepatitis_risk_factors` WRITE;
/*!40000 ALTER TABLE `hepatitis_risk_factors` DISABLE KEYS */;
/*!40000 ALTER TABLE `hepatitis_risk_factors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hold_sample_import`
--

DROP TABLE IF EXISTS `hold_sample_import`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hold_sample_import` (
  `hold_sample_id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`hold_sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hold_sample_import`
--

LOCK TABLES `hold_sample_import` WRITE;
/*!40000 ALTER TABLE `hold_sample_import` DISABLE KEYS */;
/*!40000 ALTER TABLE `hold_sample_import` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `instrument_controls`
--

DROP TABLE IF EXISTS `instrument_controls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instrument_controls` (
  `test_type` varchar(255) NOT NULL,
  `config_id` int(11) NOT NULL,
  `number_of_in_house_controls` int(11) DEFAULT NULL,
  `number_of_manufacturer_controls` int(11) DEFAULT NULL,
  `number_of_calibrators` int(11) DEFAULT NULL,
  PRIMARY KEY (`test_type`,`config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `instrument_controls`
--

LOCK TABLES `instrument_controls` WRITE;
/*!40000 ALTER TABLE `instrument_controls` DISABLE KEYS */;
INSERT INTO `instrument_controls` VALUES ('covid-19',3,0,0,0),('eid',2,0,0,0),('eid',3,0,2,0),('hepatitis',2,0,0,0),('hepatitis',3,0,0,0),('vl',2,0,0,0),('vl',3,0,3,0);
/*!40000 ALTER TABLE `instrument_controls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `instrument_machines`
--

DROP TABLE IF EXISTS `instrument_machines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instrument_machines` (
  `config_machine_id` int(11) NOT NULL AUTO_INCREMENT,
  `config_id` int(11) NOT NULL,
  `config_machine_name` varchar(255) NOT NULL,
  `date_format` text,
  `file_name` varchar(256) DEFAULT NULL,
  `poc_device` varchar(255) DEFAULT NULL,
  `latitude` varchar(255) DEFAULT NULL,
  `longitude` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`config_machine_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `instrument_machines`
--

LOCK TABLES `instrument_machines` WRITE;
/*!40000 ALTER TABLE `instrument_machines` DISABLE KEYS */;
INSERT INTO `instrument_machines` VALUES (1,1,'Roche 1',NULL,'roche-rwanda.php',NULL,NULL,NULL,NULL),(2,3,'Abbott',NULL,'abbott.php','no','','','2022-02-23 11:18:02');
/*!40000 ALTER TABLE `instrument_machines` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `instruments`
--

DROP TABLE IF EXISTS `instruments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `instruments` (
  `config_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`config_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `instruments`
--

LOCK TABLES `instruments` WRITE;
/*!40000 ALTER TABLE `instruments` DISABLE KEYS */;
INSERT INTO `instruments` VALUES (1,'Roche',NULL,NULL,'roche-rwanda.php',20,10000000,21,0,3,0,NULL,NULL,NULL,'active',NULL),(2,'Biomerieux',NULL,NULL,'biomerieux.php',30,10000000,10,2,3,1,'',NULL,NULL,'active',NULL),(3,'Abbott',NULL,'[\"vl\"]','abbott.php',20,10000000,96,0,3,0,'',NULL,NULL,'active',NULL);
/*!40000 ALTER TABLE `instruments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_report_signatories`
--

DROP TABLE IF EXISTS `lab_report_signatories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_report_signatories` (
  `signatory_id` int(11) NOT NULL AUTO_INCREMENT,
  `name_of_signatory` varchar(255) DEFAULT NULL,
  `designation` varchar(255) DEFAULT NULL,
  `signature` varchar(255) DEFAULT NULL,
  `test_types` varchar(255) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `display_order` varchar(50) DEFAULT NULL,
  `added_on` datetime DEFAULT NULL,
  `added_by` varchar(255) DEFAULT NULL,
  `signatory_status` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`signatory_id`),
  KEY `lab_id` (`lab_id`),
  CONSTRAINT `lab_report_signatories_ibfk_1` FOREIGN KEY (`lab_id`) REFERENCES `facility_details` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_report_signatories`
--

LOCK TABLES `lab_report_signatories` WRITE;
/*!40000 ALTER TABLE `lab_report_signatories` DISABLE KEYS */;
/*!40000 ALTER TABLE `lab_report_signatories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_result_updates`
--

DROP TABLE IF EXISTS `log_result_updates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `log_result_updates` (
  `result_log_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` text,
  `vl_sample_id` int(11) NOT NULL,
  `test_type` varchar(244) DEFAULT NULL COMMENT 'vl, eid, covid19, hepatitis, tb',
  `result_method` varchar(256) DEFAULT NULL,
  `file_name` varchar(256) DEFAULT NULL,
  `updated_on` datetime DEFAULT NULL,
  PRIMARY KEY (`result_log_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_result_updates`
--

LOCK TABLES `log_result_updates` WRITE;
/*!40000 ALTER TABLE `log_result_updates` DISABLE KEYS */;
/*!40000 ALTER TABLE `log_result_updates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `move_samples`
--

DROP TABLE IF EXISTS `move_samples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `move_samples` (
  `move_sample_id` int(11) NOT NULL AUTO_INCREMENT,
  `moved_from_lab_id` int(11) NOT NULL,
  `moved_to_lab_id` int(11) NOT NULL,
  `test_type` varchar(256) DEFAULT NULL,
  `moved_on` date DEFAULT NULL,
  `moved_by` varchar(255) DEFAULT NULL,
  `reason_for_moving` mediumtext,
  `move_approved_by` varchar(255) DEFAULT NULL,
  `list_request_created_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`move_sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `move_samples`
--

LOCK TABLES `move_samples` WRITE;
/*!40000 ALTER TABLE `move_samples` DISABLE KEYS */;
/*!40000 ALTER TABLE `move_samples` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `move_samples_map`
--

DROP TABLE IF EXISTS `move_samples_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `move_samples_map` (
  `sample_map_id` int(11) NOT NULL AUTO_INCREMENT,
  `move_sample_id` int(11) NOT NULL,
  `test_type_sample_id` int(11) DEFAULT NULL,
  `test_type` varchar(256) DEFAULT NULL,
  `move_sync_status` varchar(255) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sample_map_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `move_samples_map`
--

LOCK TABLES `move_samples_map` WRITE;
/*!40000 ALTER TABLE `move_samples_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `move_samples_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `other_config`
--

DROP TABLE IF EXISTS `other_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `other_config` (
  `type` varchar(45) DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `value` mediumtext,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `other_config`
--

LOCK TABLES `other_config` WRITE;
/*!40000 ALTER TABLE `other_config` DISABLE KEYS */;
INSERT INTO `other_config` VALUES ('request','Email Id','rq_email',NULL),('request','Email Fields','rq_field',NULL),('request','Password','rq_password',NULL),('result','Email Id','rs_email',NULL),('result','Email Fields','rs_field',NULL),('result','Password','rs_password',NULL);
/*!40000 ALTER TABLE `other_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `package_details`
--

DROP TABLE IF EXISTS `package_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `package_details` (
  `package_id` int(11) NOT NULL AUTO_INCREMENT,
  `package_code` varchar(255) NOT NULL,
  `added_by` varchar(255) NOT NULL,
  `package_status` varchar(255) DEFAULT NULL,
  `module` varchar(255) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `number_of_samples` int(11) DEFAULT NULL,
  `request_created_datetime` datetime DEFAULT NULL,
  `last_modified_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`package_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `package_details`
--

LOCK TABLES `package_details` WRITE;
/*!40000 ALTER TABLE `package_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `package_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patients`
--

DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patients` (
  `system_patient_code` varchar(43) NOT NULL,
  `is_encrypted` varchar(10) DEFAULT NULL,
  `patient_code_prefix` varchar(256) DEFAULT NULL,
  `patient_code_key` int(11) DEFAULT NULL,
  `patient_code` varchar(256) DEFAULT NULL,
  `patient_first_name` text,
  `patient_middle_name` text,
  `patient_last_name` text,
  `patient_gender` varchar(256) DEFAULT NULL,
  `patient_phone_number` varchar(50) DEFAULT NULL,
  `patient_age_in_years` int(11) DEFAULT NULL,
  `patient_age_in_months` int(11) DEFAULT NULL,
  `patient_dob` date DEFAULT NULL,
  `patient_address` text,
  `is_patient_pregnant` varchar(10) DEFAULT NULL,
  `is_patient_breastfeeding` varchar(10) DEFAULT NULL,
  `patient_province` int(11) DEFAULT NULL,
  `patient_district` int(11) DEFAULT NULL,
  `status` varchar(11) DEFAULT NULL,
  `patient_registered_on` datetime DEFAULT NULL,
  `patient_registered_by` text,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`system_patient_code`),
  UNIQUE KEY `patient_code_prefix` (`patient_code_prefix`,`patient_code_key`),
  UNIQUE KEY `single_patient` (`patient_code`,`patient_gender`,`patient_dob`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
/*!40000 ALTER TABLE `patients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `privileges`
--

DROP TABLE IF EXISTS `privileges`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `privileges` (
  `privilege_id` int(11) NOT NULL AUTO_INCREMENT,
  `resource_id` varchar(255) NOT NULL,
  `privilege_name` varchar(255) DEFAULT NULL,
  `shared_privileges` json DEFAULT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT NULL,
  `show_mode` varchar(32) DEFAULT 'always',
  PRIMARY KEY (`privilege_id`),
  UNIQUE KEY `resource` (`resource_id`,`privilege_name`)
) ENGINE=InnoDB AUTO_INCREMENT=420 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `privileges`
--

LOCK TABLES `privileges` WRITE;
/*!40000 ALTER TABLE `privileges` DISABLE KEYS */;
INSERT INTO `privileges` VALUES (1,'users','users.php',NULL,'Access',NULL,'always'),(2,'users','addUser.php',NULL,'Add',NULL,'always'),(3,'users','editUser.php',NULL,'Edit',NULL,'always'),(4,'facilities','facilities.php',NULL,'Access',NULL,'always'),(5,'facilities','addFacility.php','[\"mapTestType.php\"]','Add',NULL,'always'),(6,'facilities','editFacility.php',NULL,'Edit',NULL,'always'),(7,'global-config','globalConfig.php',NULL,'Access',NULL,'always'),(8,'global-config','editGlobalConfig.php',NULL,'Edit',NULL,'always'),(9,'instruments','/instruments/instruments.php',NULL,'Access',1,'always'),(10,'instruments','/instruments/add-instrument.php',NULL,'Add',2,'always'),(11,'instruments','/instruments/edit-instrument.php',NULL,'Edit',3,'always'),(12,'vl-requests','/vl/requests/vl-requests.php',NULL,'View',1,'always'),(13,'vl-requests','/vl/requests/addVlRequest.php',NULL,'Add',2,'always'),(14,'vl-requests','/vl/requests/editVlRequest.php',NULL,'Edit',3,'always'),(16,'vl-batch','/batch/batches.php?type=vl','[\"/batch/generate-batch-pdf.php?type=vl\"]','Access',1,'always'),(17,'vl-batch','/batch/add-batch.php?type=vl','[\"/batch/add-batch-position.php?type=vl\"]','Add',2,'always'),(18,'vl-batch','/batch/edit-batch.php?type=vl','[\"/batch/delete-batch.php?type=vl\", \"/batch/edit-batch-position.php?type=vl\"]','Edit',3,'always'),(20,'vl-results','/vl/results/vlPrintResult.php',NULL,'Print Result PDF',NULL,'always'),(21,'vl-results','/vl/results/vlTestResult.php','[\"/vl/results/updateVlTestResult.php\", \"/vl/results/vl-failed-results.php\"]','Enter Result Manually',NULL,'always'),(22,'vl-reports','/vl/program-management/vl-sample-status.php',NULL,'Sample Status Report',NULL,'always'),(23,'vl-reports','/vl/program-management/vl-export-data.php',NULL,'Export VL Data',NULL,'always'),(24,'home','index.php',NULL,'Access',NULL,'always'),(25,'roles','roles.php',NULL,'Access',NULL,'always'),(26,'roles','editRole.php',NULL,'Edit',NULL,'always'),(28,'test-request-email-config','testRequestEmailConfig.php',NULL,'Access',NULL,'always'),(31,'vl-results','/vl/results/vlResultApproval.php',NULL,'Manage VL Result Status (Approve/Reject)',NULL,'always'),(33,'vl-reports','/vl/program-management/highViralLoad.php',NULL,'High VL Report',NULL,'always'),(34,'vl-reports','/vl/program-management/addContactNotes.php',NULL,'Contact Notes (High VL Reports)',NULL,'always'),(39,'roles','addRole.php',NULL,'Add',NULL,'always'),(40,'vl-reports','/vl/program-management/vlTestResultStatus.php',NULL,'Dashboard',NULL,'always'),(43,'test-request-email-config','editTestRequestEmailConfig.php',NULL,'Edit',NULL,'always'),(48,'test-result-email-config','testResultEmailConfig.php',NULL,'Access',NULL,'always'),(49,'test-result-email-config','editTestResultEmailConfig.php',NULL,'Edit',NULL,'always'),(56,'vl-reports','/vl/program-management/vlWeeklyReport.php',NULL,'VL Weekly Report',NULL,'always'),(57,'vl-reports','/vl/program-management/sampleRejectionReport.php',NULL,'Sample Rejection Report',NULL,'always'),(59,'vl-reports','/vl/program-management/vlMonitoringReport.php',NULL,'Sample Monitoring Report',NULL,'always'),(63,'vl-reports','/vl/program-management/vlControlReport.php',NULL,'Controls Report',NULL,'always'),(64,'facilities','addVlFacilityMap.php',NULL,'Add Facility Map',NULL,'always'),(65,'facilities','facilityMap.php',NULL,'Access Facility Map',NULL,'always'),(66,'facilities','editVlFacilityMap.php',NULL,'Edit Facility Map',NULL,'always'),(70,'vl-reports','/vl/program-management/vlResultAllFieldExportInExcel.php',NULL,'Export VL Data in Excel',NULL,'always'),(74,'eid-requests','/eid/requests/eid-add-request.php','[\"/eid/requests/eid-bulk-import-request.php\"]','Add',2,'always'),(75,'eid-requests','/eid/requests/eid-edit-request.php',NULL,'Edit',3,'always'),(76,'eid-requests','/eid/requests/eid-requests.php',NULL,'View',1,'always'),(77,'eid-batches','/batch/batches.php?type=eid','[\"/batch/generate-batch-pdf.php?type=eid\"]','View Batches',1,'always'),(78,'eid-batches','/batch/add-batch.php?type=eid','[\"/batch/add-batch-position.php?type=eid\"]','Add Batch',2,'always'),(79,'eid-batches','/batch/edit-batch.php?type=eid','[\"/batch/delete-batch.php?type=eid\", \"/batch/edit-batch-position.php?type=eid\"]','Edit Batch',3,'always'),(80,'eid-results','/eid/results/eid-manual-results.php','[\"/eid/results/eid-update-result.php\", \"/eid/results/eid-failed-results.php\"]','Enter Result Manually',NULL,'always'),(84,'eid-results','/eid/results/eid-result-status.php',NULL,'Manage Result Status',NULL,'always'),(85,'eid-results','/eid/results/eid-print-results.php',NULL,'Print Results',NULL,'always'),(86,'eid-management','/eid/management/eid-export-data.php',NULL,'Export Data',NULL,'always'),(87,'eid-management','/eid/management/eid-sample-rejection-report.php',NULL,'Sample Rejection Report',NULL,'always'),(88,'eid-management','/eid/management/eid-sample-status.php',NULL,'Sample Status Report',NULL,'always'),(89,'vl-requests','/vl/requests/addSamplesFromManifest.php',NULL,'Add Samples from Manifest',6,'lis'),(91,'eid-requests','/eid/requests/addSamplesFromManifest.php',NULL,'Add Samples from Manifest',6,'lis'),(95,'covid-19-requests','/covid-19/requests/covid-19-add-request.php','[\"/covid-19/requests/covid-19-bulk-import-request.php\", \"/covid-19/requests/covid-19-quick-add.php\"]','Add',2,'always'),(96,'covid-19-requests','/covid-19/requests/covid-19-edit-request.php',NULL,'Edit',3,'always'),(97,'covid-19-requests','/covid-19/requests/covid-19-requests.php',NULL,'View',1,'always'),(98,'covid-19-results','/covid-19/results/covid-19-result-status.php',NULL,'Manage Result Status',NULL,'always'),(99,'covid-19-results','/covid-19/results/covid-19-print-results.php','[\"/covid-19/mail/mail-covid-19-results.php\", \"/covid-19/mail/covid-19-result-mail-confirm.php\"]','Print Results',NULL,'always'),(100,'covid-19-batches','/batch/batches.php?type=covid19','[\"/batch/generate-batch-pdf.php?type=covid19\"]','View Batches',1,'always'),(101,'covid-19-batches','/batch/add-batch.php?type=covid19','[\"/batch/add-batch-position.php?type=covid19\"]','Add Batch',2,'always'),(102,'covid-19-batches','/batch/edit-batch.php?type=covid19','[\"/batch/delete-batch.php?type=covid19\", \"/batch/edit-batch-position.php?type=covid19\"]','Edit Batch',3,'always'),(103,'covid-19-results','/covid-19/results/covid-19-manual-results.php','[\"/covid-19/results/covid-19-update-result.php\", \"/covid-19/results/covid-19-failed-results.php\"]','Enter Result Manually',NULL,'always'),(105,'covid-19-management','/covid-19/management/covid-19-export-data.php',NULL,'Export Data',NULL,'always'),(106,'covid-19-management','/covid-19/management/covid-19-sample-rejection-report.php',NULL,'Sample Rejection Report',NULL,'always'),(107,'covid-19-management','/covid-19/management/covid-19-sample-status.php',NULL,'Sample Status Report',NULL,'always'),(108,'covid-19-requests','/covid-19/requests/record-final-result.php',NULL,'Record Final Result',NULL,'always'),(109,'covid-19-requests','/covid-19/requests/can-record-confirmatory-tests.php',NULL,'Can Record Confirmatory Tests',NULL,'always'),(110,'covid-19-requests','/covid-19/requests/update-record-confirmatory-tests.php',NULL,'Update Record Confirmatory Tests',NULL,'always'),(111,'covid-19-batches','covid-19-confirmation-manifest.php',NULL,'Covid-19 Confirmation Manifest',NULL,'always'),(112,'covid-19-batches','covid-19-add-confirmation-manifest.php',NULL,'Add New Confirmation Manifest',NULL,'always'),(113,'covid-19-batches','generate-confirmation-manifest.php',NULL,'Generate Positive Confirmation Manifest',NULL,'always'),(114,'covid-19-batches','covid-19-edit-confirmation-manifest.php',NULL,'Edit Positive Confirmation Manifest',NULL,'always'),(121,'eid-management','/eid/management/eid-clinic-report.php',NULL,'EID Clinic Reports',NULL,'always'),(122,'covid-19-management','/covid-19/management/covid-19-clinic-report.php',NULL,'Covid-19 Clinic Reports',NULL,'always'),(123,'covid-19-reference','/covid-19/reference/covid19-sample-type.php','[\"/covid-19/reference/covid19-sample-rejection-reasons.php\", \"/covid-19/reference/add-covid19-sample-rejection-reason.php\", \"/covid-19/reference/covid19-comorbidities.php\", \"/covid-19/reference/add-covid19-comorbidities.php\", \"/covid-19/reference/covid19-symptoms.php\", \"/covid-19/reference/add-covid19-sample-type.php\", \"/covid-19/reference/covid19-test-symptoms.php\", \"/covid-19/reference/add-covid19-symptoms.php\", \"/covid-19/reference/covid19-test-reasons.php\", \"/covid-19/reference/add-covid19-test-reasons.php\", \"/covid-19/reference/covid19-results.php\", \"/covid-19/reference/add-covid19-results.php\", \"/covid-19/reference/covid19-qc-test-kits.php\", \"/covid-19/reference/add-covid19-qc-test-kit.php\", \"/covid-19/reference/edit-covid19-qc-test-kit.php\"]','Manage Reference',NULL,'always'),(124,'covid-19-reference','/covid-19/reference/covid19-comorbidities.php',NULL,'Manage Comorbidities',NULL,'always'),(125,'covid-19-reference','/covid-19/reference/addCovid19Comorbidities.php',NULL,'Add Comorbidities',NULL,'always'),(126,'covid-19-reference','/covid-19/reference/editCovid19Comorbidities.php',NULL,'Edit Comorbidities',NULL,'always'),(127,'covid-19-reference','/covid-19/reference/covid19-sample-rejection-reasons.php',NULL,'Manage Sample Rejection Reasons',NULL,'always'),(128,'covid-19-reference','/covid-19/reference/addCovid19SampleRejectionReason.php',NULL,'Add Sample Rejection Reason',NULL,'always'),(129,'covid-19-reference','/covid-19/reference/editCovid19SampleRejectionReason.php',NULL,'Edit Sample Rejection Reason',NULL,'always'),(130,'vl-reference','/vl/reference/vl-art-code-details.php','[\"/vl/reference/add-vl-art-code-details.php\", \"/vl/reference/edit-vl-art-code-details.php\", \"/vl/reference/add-vl-results.php\", \"/vl/reference/edit-vl-results.php\", \"/vl/reference/vl-sample-rejection-reasons.php\", \"/vl/reference/add-vl-sample-rejection-reasons.php\", \"/vl/reference/edit-vl-sample-rejection-reasons.php\", \"/vl/reference/vl-sample-type.php\", \"/vl/reference/edit-vl-sample-type.php\", \"/vl/reference/add-vl-sample-type.php\", \"/vl/reference/vl-test-reasons.php\", \"/vl/reference/add-vl-test-reasons.php\", \"/vl/reference/edit-vl-test-reasons.php\", \"/vl/reference/vl-test-failure-reasons.php\", \"/vl/referencea/dd-vl-test-failure-reason.php\", \"/vl/reference/edit-vl-test-failure-reason.php\"]','Manage VL Reference Tables',NULL,'always'),(131,'eid-reference','/eid/reference/eid-sample-type.php','[\"/eid/reference/eid-sample-rejection-reasons.php\", \"/eid/reference/add-eid-sample-rejection-reasons.php\", \"edit-eid-sample-rejection-reasons.php\", \"/eid/reference/add-eid-sample-type.php\", \"/eid/reference/edit-eid-sample-type.php\", \"/eid/reference/eid-test-reasons.php\", \"/eid/reference/add-eid-test-reasons.php\", \"/eid/reference/edit-eid-test-reasons.php\", \"/eid/reference/eid-results.php\", \"/eid/reference/add-eid-results.php\", \"/eid/reference/edit-eid-results.php\"]','Manage EID Reference Tables',NULL,'always'),(140,'vl-requests','/vl/requests/edit-locked-vl-samples',NULL,'Edit Locked VL Samples',5,'always'),(141,'eid-requests','/eid/requests/edit-locked-eid-samples',NULL,'Edit Locked EID Samples',5,'always'),(142,'covid-19-requests','/covid-19/requests/edit-locked-covid19-samples',NULL,'Edit Locked Covid-19 Samples',5,'always'),(143,'vl-reports','/vl/program-management/vlMonthlyThresholdReport.php','[\"/vl/program-management/vlTestingTargetReport.php\", \"/vl/program-management/vlSuppressedTargetReport.php\"]','Monthly Threshold Report',NULL,'always'),(144,'eid-management','/eid/management/eidMonthlyThresholdReport.php','[\"/eid/management/eidTestingTargetReport.php\", \"/eid/management/eidSuppressedTargetReport.php\"]','Monthly Threshold Report',NULL,'always'),(145,'covid-19-management','/covid-19/management/covid19MonthlyThresholdReport.php','[\"/covid-19/management/covid19TestingTargetReport.php\", \"/covid-19/management/covid19SuppressedTargetReport.php\"]','Monthly Threshold Report',NULL,'always'),(152,'hepatitis-requests','/hepatitis/requests/hepatitis-requests.php',NULL,'View',1,'always'),(153,'hepatitis-requests','/hepatitis/requests/hepatitis-add-request.php',NULL,'Add',2,'always'),(154,'hepatitis-requests','/hepatitis/requests/hepatitis-edit-request.php',NULL,'Edit',3,'always'),(164,'hepatitis-results','/hepatitis/results/hepatitis-manual-results.php','[\"/hepatitis/results/hepatitis-update-result.php\", \"/hepatitis/results/hepatitis-failed-results.php\"]','Enter Result Manually',NULL,'always'),(165,'hepatitis-results','/hepatitis/results/hepatitis-print-results.php','[\"/hepatitis/mail/mail-hepatitis-results.php\", \"hepatitis-result-mail-confirm.php\"]','Print Results',NULL,'always'),(166,'hepatitis-results','/hepatitis/results/hepatitis-result-status.php',NULL,'Manage Result Status',NULL,'always'),(167,'hepatitis-reference','/hepatitis/reference/hepatitis-sample-type.php','[\"/hepatitis/reference/hepatitis-sample-rejection-reasons.php\", \"/hepatitis/reference/add-hepatitis-sample-rejection-reasons.php\", \"/hepatitis/reference/hepatitis-comorbidities.php\", \"/hepatitis/reference/add-hepatitis-comorbidities.php\", \"/hepatitis/reference/add-hepatitis-sample-type.php\", \"/hepatitis/reference/hepatitis-results.php\", \"/hepatitis/reference/add-hepatitis-results.php\", \"/hepatitis/reference/hepatitis-risk-factors.php\", \"/hepatitis/reference/add-hepatitis-risk-factors.php\", \"/hepatitis/reference/hepatitis-test-reasons.php\", \"/hepatitis/reference/add-hepatitis-test-reasons.php\"]','Manage Hepatitis Reference',NULL,'always'),(168,'vl-reports','/vl/program-management/vlSuppressedTargetReport.php',NULL,'Suppressed Target report',NULL,'always'),(169,'hepatitis-batches','/batch/batches.php?type=hepatitis','[\"/batch/generate-batch-pdf.php?type=hepatitis\"]','View Batches',1,'always'),(170,'hepatitis-batches','/batch/add-batch.php?type=hepatitis','[\"/batch/add-batch-position.php?type=hepatitis\"]','Add Batch',2,'always'),(171,'hepatitis-batches','/batch/edit-batch.php?type=hepatitis','[\"/batch/delete-batch.php?type=hepatitis\", \"/batch/edit-batch-position.php?type=hepatitis\"]','Edit Batch',3,'always'),(174,'hepatitis-requests','/hepatitis/requests/add-samples-from-manifest.php',NULL,'Add Samples from Manifest',6,'lis'),(176,'hepatitis-management','/hepatitis/management/hepatitis-clinic-report.php',NULL,'Hepatitis Clinic Reports',NULL,'always'),(177,'hepatitis-management','/hepatitis/management/hepatitis-testing-target-report.php',NULL,'Hepatitis Testing Target Reports',NULL,'always'),(178,'hepatitis-management','/hepatitis/management/hepatitis-sample-rejection-report.php',NULL,'Hepatitis Sample Rejection Reports',NULL,'always'),(179,'hepatitis-management','/hepatitis/management/hepatitis-sample-status.php',NULL,'Hepatitis Sample Status Reports',NULL,'always'),(180,'covid-19-requests','/covid-19/requests/addSamplesFromManifest.php',NULL,'Add Samples from Manifest',6,'lis'),(181,'covid-19-requests','/covid-19/requests/covid-19-dhis2.php','[\"/covid-19/interop/dhis2/covid-19-init.php\", \"/covid-19/interop/dhis2/covid-19-send.php\", \"/covid-19/interop/dhis2/covid-19-receive.php\"]','DHIS2',NULL,'always'),(182,'covid-19-requests','/covid-19/requests/covid-19-sync-request.php',NULL,'Covid-19 Sync Request',NULL,'always'),(183,'common-reference','geographical-divisions-details.php','[\"implementation-partners.php\", \"add-implementation-partners.php\", \"edit-implementation-partners.php\", \"funding-sources.php\", \"add-funding-sources.php\", \"edit-funding-sources.php\"]','Manage Geographical Divisions',NULL,'always'),(184,'common-reference','add-geographical-divisions.php',NULL,'Add Geographical Divisions',NULL,'always'),(185,'common-reference','edit-geographical-divisions.php',NULL,'Edit Geographical Divisions',NULL,'always'),(186,'hepatitis-requests','/hepatitis/requests/hepatitis-dhis2.php','[\"/hepatitis/interop/dhis2/hepatitis-init.php\", \"/hepatitis/interop/dhis2/hepatitis-send.php\", \"/hepatitis/interop/dhis2/hepatitis-receive.php\"]','DHIS2',NULL,'always'),(187,'common-reference','sync-history.php',NULL,'Sync History',NULL,'always'),(188,'hepatitis-management','/hepatitis/management/hepatitis-export-data.php',NULL,'Hepatitis Export',NULL,'always'),(189,'tb-requests','/tb/requests/tb-requests.php',NULL,'View',1,'always'),(190,'tb-requests','/tb/requests/tb-add-request.php',NULL,'Add',2,'always'),(191,'move-samples','move-samples.php',NULL,'Access',NULL,'always'),(192,'move-samples','select-samples-to-move.php',NULL,'Add Move Samples',NULL,'always'),(193,'tb-requests','/tb/requests/tb-edit-request.php',NULL,'Edit',3,'always'),(194,'tb-results','/tb/results/tb-manual-results.php','[\"/tb/results/tb-update-result.php\", \"/tb/results/tb-failed-results.php\"]','Enter Result Manually',NULL,'always'),(195,'tb-results','/tb/results/tb-print-results.php',NULL,'Print Results',NULL,'always'),(196,'tb-results','/tb/results/tb-result-status.php',NULL,'Manage Result Status',NULL,'always'),(197,'tb-management','/tb/management/tb-sample-type.php',NULL,'Manage Reference',NULL,'always'),(198,'tb-management','/tb/management/tb-export-data.php',NULL,'Export Data',NULL,'always'),(199,'tb-management','/tb/management//batch/batches.php?type=tb',NULL,'View Batches',NULL,'always'),(200,'tb-management','/tb/management//batch/add-batch.php?type=tb',NULL,'Add Batch',NULL,'always'),(201,'tb-management','/tb/management//batch/edit-batch.php?type=tb',NULL,'Edit Batch',NULL,'always'),(204,'tb-requests','/tb/requests/addSamplesFromManifest.php',NULL,'Add Samples from Manifest',6,'lis'),(205,'tb-management','/tb/management/tb-sample-status.php',NULL,'Sample Status Report',NULL,'always'),(206,'tb-management','/tb/management/tb-sample-rejection-report.php',NULL,'Sample Rejection Report',NULL,'always'),(207,'tb-management','/tb/management/tb-clinic-report.php',NULL,'TB Clinic Report',NULL,'always'),(208,'common-reference','activity-log.php',NULL,'User Activity Log',NULL,'always'),(209,'vl-requests','/vl/requests/export-vl-requests.php',NULL,'Export VL Requests',4,'always'),(210,'eid-requests','/eid/requests/export-eid-requests.php',NULL,'Export EID Requests',4,'always'),(211,'covid-19-requests','/covid-19/requests/export-covid19-requests.php',NULL,'Export Covid-19 Requests ',4,'always'),(212,'hepatitis-requests','/hepatitis/requests/export-hepatitis-requests.php',NULL,'Export Hepatitis Requests',4,'always'),(213,'tb-requests','/tb/requests/export-tb-requests.php',NULL,'Export TB Requests',4,'always'),(219,'common-reference','api-sync-history.php',NULL,'API Sync History',NULL,'always'),(220,'common-reference','sources-of-requests.php',NULL,'Sources of Requests Report',NULL,'always'),(221,'covid-19-results','/covid-19/results/covid-19-qc-data.php',NULL,'Covid-19 QC Data',NULL,'always'),(222,'covid-19-results','/covid-19/results/add-covid-19-qc-data.php',NULL,'Add Covid-19 QC Data',NULL,'always'),(223,'covid-19-results','/covid-19/results/edit-covid-19-qc-data.php',NULL,'Edit Covid-19 QC Data',NULL,'always'),(224,'common-reference','audit-trail.php',NULL,'Audit Trail',NULL,'always'),(225,'vl-reference','/vl/reference/vl-results.php',NULL,'Manage VL Results',NULL,'always'),(226,'common-reference','sync-status.php',NULL,'Sync Status',NULL,'always'),(230,'test-type','testType.php',NULL,'Access',NULL,'always'),(231,'test-type','add-test-type.php',NULL,'Add',NULL,'always'),(232,'test-type','edit-test-type.php',NULL,'Edit Test Type',NULL,'always'),(236,'common-sample-type','addSampleType.php',NULL,'Add',NULL,'always'),(237,'common-sample-type','sampleType.php',NULL,'Access',NULL,'always'),(238,'common-sample-type','editSampleType.php',NULL,'Edit',NULL,'always'),(239,'common-testing-reason','testingReason.php',NULL,'Access',NULL,'always'),(240,'common-testing-reason','editTestingReason.php',NULL,'Edit',NULL,'always'),(241,'common-testing-reason','addTestingReason.php',NULL,'Add',NULL,'always'),(242,'common-symptoms','symptoms.php',NULL,'Access',NULL,'always'),(243,'common-symptoms','addSymptoms.php',NULL,'Add',NULL,'always'),(244,'common-symptoms','editSymptoms.php',NULL,'Edit',NULL,'always'),(245,'generic-requests','/generic-tests/requests/view-requests.php',NULL,'View Generic Tests',1,'always'),(246,'generic-requests','/generic-tests/requests/add-request.php',NULL,'Add Generic Tests',2,'always'),(247,'generic-requests','/generic-tests/requests/add-samples-from-manifest.php',NULL,'Add Samples From Manifest',6,'lis'),(252,'generic-requests','/generic-tests/requests/edit-request.php',NULL,'Edit Generic Tests',3,'always'),(277,'generic-results','/generic-tests/results/generic-test-results.php','[\"/generic-tests/results/update-generic-test-result.php\"]','Manage Test Results',NULL,'always'),(278,'generic-results','/generic-tests/results/generic-failed-results.php',NULL,'Manage Failed Results',NULL,'always'),(279,'generic-results','/generic-tests/results/generic-result-approval.php',NULL,'Approve Test Results',NULL,'always'),(280,'generic-management','/generic-tests/program-management/generic-sample-status.php',NULL,'Sample Status Report',NULL,'always'),(281,'generic-management','/generic-tests/program-management/generic-export-data.php',NULL,'Export Report in Excel',NULL,'always'),(282,'generic-management','/generic-tests/results/generic-print-result.php',NULL,'Export Report in PDF',NULL,'always'),(283,'generic-management','/generic-tests/program-management/sample-rejection-report.php',NULL,'Sample Rejection Report',NULL,'always'),(284,'generic-management','/generic-tests/program-management/generic-monthly-threshold-report.php',NULL,'Monthly Threshold Report',NULL,'always'),(300,'vl-reference','/vl/reference/add-vl-results.php',NULL,'Add VL Result Types',NULL,'always'),(301,'vl-reference','/vl/reference/edit-vl-results.php',NULL,'Edit VL Result Types',NULL,'always'),(317,'vl-results','/import-result/import-file.php?t=vl','[\"/import-result/imported-results.php?t=vl\", \"/import-result/importedStatistics.php?t=vl\"]','Import Result from Files',NULL,'always'),(318,'eid-results','/import-result/import-file.php?t=eid','[\"/import-result/imported-results.php?t=eid\", \"/import-result/importedStatistics.php?t=eid\"]','Import Result from Files',NULL,'always'),(319,'covid-19-results','/covid-19/results//import-result/import-file.php?t=covid19',NULL,'Import Result from Files',NULL,'always'),(320,'hepatitis-results','/import-result/import-file.php?t=hepatitis','[\"/import-result/imported-results.php?t=hepatitis\", \"/import-result/importedStatistics.php?t=hepatitis\"]','Import Result from Files',NULL,'always'),(321,'tb-results','/import-result/import-file.php?t=tb','[\"/import-result/imported-results.php?t=tb\", \"/import-result/importedStatistics.php?t=tb\"]','Import Result from Files',NULL,'always'),(322,'generic-results','/import-result/import-file.php?t=generic-tests','[\"/import-result/importedStatistics.php?t=generic-tests\"]','Import Result from Files',NULL,'always'),(323,'vl-requests','/specimen-referral-manifest/view-manifests.php?t=vl',NULL,'View VL Manifests',7,'sts'),(324,'eid-requests','/specimen-referral-manifest/view-manifests.php?t=eid',NULL,'View EID Manifests',7,'sts'),(325,'covid-19-requests','/specimen-referral-manifest/view-manifests.php?t=covid19',NULL,'View COVID-19 Manifests',7,'sts'),(326,'hepatitis-requests','/specimen-referral-manifest/view-manifests.php?t=hepatitis',NULL,'View Hepatitis Manifests',7,'sts'),(327,'tb-requests','/specimen-referral-manifest/view-manifests.php?t=tb',NULL,'View TB Manifests',7,'sts'),(328,'generic-requests','/specimen-referral-manifest/view-manifests.php?t=generic-tests',NULL,'View Lab Tests Manifests',7,'sts'),(329,'vl-requests','/specimen-referral-manifest/add-manifest.php?t=vl',NULL,'Add VL Manifests',8,'sts'),(330,'eid-requests','/specimen-referral-manifest/add-manifest.php?t=eid',NULL,'Add EID Manifests',8,'sts'),(331,'covid-19-requests','/specimen-referral-manifest/add-manifest.php?t=covid19',NULL,'Add COVID-19 Manifests',8,'sts'),(332,'hepatitis-requests','/specimen-referral-manifest/add-manifest.php?t=hepatitis',NULL,'Add Hepatitis Manifests',8,'sts'),(333,'tb-requests','/specimen-referral-manifest/add-manifest.php?t=tb',NULL,'Add TB Manifests',8,'sts'),(334,'generic-requests','/specimen-referral-manifest/add-manifest.php?t=generic-tests',NULL,'Add Lab Tests Manifests',8,'sts'),(335,'vl-requests','/specimen-referral-manifest/edit-manifest.php?t=vl',NULL,'Edit VL Manifests',9,'sts'),(336,'eid-requests','/specimen-referral-manifest/edit-manifest.php?t=eid',NULL,'Edit EID Manifests',9,'sts'),(337,'covid-19-requests','/specimen-referral-manifest/edit-manifest.php?t=covid19',NULL,'Edit COVID-19 Manifests',9,'sts'),(338,'hepatitis-requests','/specimen-referral-manifest/edit-manifest.php?t=hepatitis',NULL,'Edit Hepatitis Manifests',9,'sts'),(339,'tb-requests','/specimen-referral-manifest/edit-manifest.php?t=tb',NULL,'Edit TB Manifests',9,'sts'),(340,'generic-requests','/specimen-referral-manifest/edit-manifest.php?t=generic-tests',NULL,'Edit Lab Tests Manifests',9,'sts'),(347,'generic-tests-config','/generic-tests/configuration/test-type.php','[\"/generic-tests/configuration/add-test-type.php\", \"/generic-tests/configuration/edit-test-type.php\", \"/generic-tests/configuration/clone-test-type.php\"]','Add/Edit Test Types',NULL,'always'),(348,'generic-tests-config','/generic-tests/configuration/sample-types/generic-sample-type.php','[\"/generic-tests/configuration/sample-types/generic-add-sample-type.php\", \"/generic-tests/configuration/sample-types/generic-edit-sample-type.php\"]','Manage Sample Types',NULL,'always'),(349,'generic-tests-config','/generic-tests/configuration/testing-reasons/generic-testing-reason.php','[\"/generic-tests/configuration/testing-reasons/generic-add-testing-reason.php\", \"/generic-tests/configuration/testing-reasons/generic-edit-testing-reason.php\"]','Manage Testing Reasons',NULL,'always'),(350,'generic-tests-config','/generic-tests/configuration/symptoms/generic-symptoms.php','[\"/generic-tests/configuration/symptoms/generic-add-symptoms.php\", \"/generic-tests/configuration/symptoms/generic-edit-symptoms.php\"]','Manage Symptoms',NULL,'always'),(351,'generic-tests-config','/generic-tests/configuration/sample-rejection-reasons/generic-sample-rejection-reasons.php','[\"/generic-tests/configuration/sample-rejection-reasons/generic-add-sample-rejection-reasons.php\", \"/generic-tests/configuration/sample-rejection-reasons/generic-edit-sample-rejection-reasons.php\"]','Manage Sample Rejection Reasons',NULL,'always'),(352,'generic-tests-config','/generic-tests/configuration/test-failure-reasons/generic-test-failure-reason.php','[\"/generic-tests/configuration/test-failure-reasons/generic-add-test-failure-reason.php\", \"/generic-tests/configuration/test-failure-reasons/generic-edit-test-failure-reason.php\"]','Manage Test Failure Reasons',NULL,'always'),(353,'generic-tests-config','/generic-tests/configuration/test-result-units/generic-test-result-units.php','[\"/generic-tests/configuration/test-result-units/generic-add-test-result-units.php\", \"/generic-tests/configuration/test-result-units/generic-edit-test-result-units.php\"]','Manage Test Result Units',NULL,'always'),(354,'generic-tests-config','/generic-tests/configuration/test-methods/generic-test-methods.php','[\"/generic-tests/configuration/test-methods/generic-add-test-methods.php\", \"/generic-tests/configuration/test-methods/generic-edit-test-methods.php\"]','Manage Test Methods',NULL,'always'),(355,'generic-tests-config','/generic-tests/configuration/test-categories/generic-test-categories.php','[\"/generic-tests/configuration/test-categories/generic-add-test-categories.php\", \"/generic-tests/configuration/test-categories/generic-edit-test-categories.php\"]','Manage Test Categories',NULL,'always'),(356,'generic-tests-batches','/batch/batches.php?type=generic-tests','[\"/batch/generate-batch-pdf.php?type=generic-tests\"]','Manage Batch',1,'always'),(357,'generic-tests-batches','/batch/add-batch.php?type=generic-tests','[\"/batch/add-batch-position.php?type=generic-tests\"]','Add New Batch',2,'always'),(358,'generic-tests-batches','/batch/edit-batch.php?type=generic-tests','[\"/batch/delete-batch.php?type=generic-tests\", \"/batch/edit-batch-position.php?type=generic-tests\"]','Edit Batch',3,'always'),(411,'hepatitis-requests','/hepatitis/requests/edit-locked-hepatitis-samples',NULL,'Edit Locked Samples',5,'always'),(412,'tb-requests','/tb/requests/edit-locked-tb-samples',NULL,'Edit Locked Samples',5,'always'),(413,'generic-tests-requests','/generic-tests/requests/edit-locked-generic-tests-samples',NULL,'Edit Locked Samples',5,'always'),(414,'generic-tests-requests','/generic-tests/requests/export-generic-tests-requests.php',NULL,'Export Requests',4,'always'),(415,'facilities','upload-facilities.php',NULL,'Upload Facilities',NULL,'always'),(416,'generic-requests','/generic-tests/requests/clone-request.php',NULL,'Clone Generic Tests',7,'always'),(417,'patients','view-patients.php',NULL,'Manage Patients',NULL,'always'),(418,'patients','add-patient.php',NULL,'Add Patient',NULL,'always'),(419,'patients','edit-patient.php',NULL,'Edit Patient',NULL,'always');
/*!40000 ALTER TABLE `privileges` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `province_details`
--

DROP TABLE IF EXISTS `province_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `province_details` (
  `province_id` int(11) NOT NULL AUTO_INCREMENT,
  `province_name` varchar(255) DEFAULT NULL,
  `province_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`province_id`),
  UNIQUE KEY `province_name` (`province_name`),
  UNIQUE KEY `province_name_2` (`province_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `province_details`
--

LOCK TABLES `province_details` WRITE;
/*!40000 ALTER TABLE `province_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `province_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qc_covid19`
--

DROP TABLE IF EXISTS `qc_covid19`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qc_covid19` (
  `qc_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`qc_id`),
  UNIQUE KEY `qc_code` (`qc_code`),
  UNIQUE KEY `unique_id` (`unique_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qc_covid19`
--

LOCK TABLES `qc_covid19` WRITE;
/*!40000 ALTER TABLE `qc_covid19` DISABLE KEYS */;
/*!40000 ALTER TABLE `qc_covid19` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `qc_covid19_tests`
--

DROP TABLE IF EXISTS `qc_covid19_tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `qc_covid19_tests` (
  `qc_test_id` int(11) NOT NULL AUTO_INCREMENT,
  `qc_id` int(11) NOT NULL,
  `test_label` varchar(256) NOT NULL,
  `test_result` varchar(256) NOT NULL,
  PRIMARY KEY (`qc_test_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `qc_covid19_tests`
--

LOCK TABLES `qc_covid19_tests` WRITE;
/*!40000 ALTER TABLE `qc_covid19_tests` DISABLE KEYS */;
/*!40000 ALTER TABLE `qc_covid19_tests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_countries`
--

DROP TABLE IF EXISTS `r_countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_countries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `iso_name` varchar(255) NOT NULL,
  `iso2` varchar(2) NOT NULL,
  `iso3` varchar(3) NOT NULL,
  `numeric_code` smallint(6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=250 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_countries`
--

LOCK TABLES `r_countries` WRITE;
/*!40000 ALTER TABLE `r_countries` DISABLE KEYS */;
INSERT INTO `r_countries` VALUES (1,'Afghanistan','AF','AFG',4),(2,'Aland Islands','AX','ALA',248),(3,'Albania','AL','ALB',8),(4,'Algeria','DZ','DZA',12),(5,'American Samoa','AS','ASM',16),(6,'Andorra','AD','AND',20),(7,'Angola','AO','AGO',24),(8,'Anguilla','AI','AIA',660),(9,'Antarctica','AQ','ATA',10),(10,'Antigua and Barbuda','AG','ATG',28),(11,'Argentina','AR','ARG',32),(12,'Armenia','AM','ARM',51),(13,'Aruba','AW','ABW',533),(14,'Australia','AU','AUS',36),(15,'Austria','AT','AUT',40),(16,'Azerbaijan','AZ','AZE',31),(17,'Bahamas','BS','BHS',44),(18,'Bahrain','BH','BHR',48),(19,'Bangladesh','BD','BGD',50),(20,'Barbados','BB','BRB',52),(21,'Belarus','BY','BLR',112),(22,'Belgium','BE','BEL',56),(23,'Belize','BZ','BLZ',84),(24,'Benin','BJ','BEN',204),(25,'Bermuda','BM','BMU',60),(26,'Bhutan','BT','BTN',64),(27,'Bolivia, Plurinational State of','BO','BOL',68),(28,'Bonaire, Sint Eustatius and Saba','BQ','BES',535),(29,'Bosnia and Herzegovina','BA','BIH',70),(30,'Botswana','BW','BWA',72),(31,'Bouvet Island','BV','BVT',74),(32,'Brazil','BR','BRA',76),(33,'British Indian Ocean Territory','IO','IOT',86),(34,'Brunei Darussalam','BN','BRN',96),(35,'Bulgaria','BG','BGR',100),(36,'Burkina Faso','BF','BFA',854),(37,'Burundi','BI','BDI',108),(38,'Cambodia','KH','KHM',116),(39,'Cameroon','CM','CMR',120),(40,'Canada','CA','CAN',124),(41,'Cape Verde','CV','CPV',132),(42,'Cayman Islands','KY','CYM',136),(43,'Central African Republic','CF','CAF',140),(44,'Chad','TD','TCD',148),(45,'Chile','CL','CHL',152),(46,'China','CN','CHN',156),(47,'Christmas Island','CX','CXR',162),(48,'Cocos (Keeling) Islands','CC','CCK',166),(49,'Colombia','CO','COL',170),(50,'Comoros','KM','COM',174),(51,'Congo','CG','COG',178),(52,'Congo, the Democratic Republic of the','CD','COD',180),(53,'Cook Islands','CK','COK',184),(54,'Costa Rica','CR','CRI',188),(55,'Cote d\'Ivoire','CI','CIV',384),(56,'Croatia','HR','HRV',191),(57,'Cuba','CU','CUB',192),(58,'Cura','CW','CUW',531),(59,'Cyprus','CY','CYP',196),(60,'Czech Republic','CZ','CZE',203),(61,'Denmark','DK','DNK',208),(62,'Djibouti','DJ','DJI',262),(63,'Dominica','DM','DMA',212),(64,'Dominican Republic','DO','DOM',214),(65,'Ecuador','EC','ECU',218),(66,'Egypt','EG','EGY',818),(67,'El Salvador','SV','SLV',222),(68,'Equatorial Guinea','GQ','GNQ',226),(69,'Eritrea','ER','ERI',232),(70,'Estonia','EE','EST',233),(71,'Ethiopia','ET','ETH',231),(72,'Falkland Islands (Malvinas)','FK','FLK',238),(73,'Faroe Islands','FO','FRO',234),(74,'Fiji','FJ','FJI',242),(75,'Finland','FI','FIN',246),(76,'France','FR','FRA',250),(77,'French Guiana','GF','GUF',254),(78,'French Polynesia','PF','PYF',258),(79,'French Southern Territories','TF','ATF',260),(80,'Gabon','GA','GAB',266),(81,'Gambia','GM','GMB',270),(82,'Georgia','GE','GEO',268),(83,'Germany','DE','DEU',276),(84,'Ghana','GH','GHA',288),(85,'Gibraltar','GI','GIB',292),(86,'Greece','GR','GRC',300),(87,'Greenland','GL','GRL',304),(88,'Grenada','GD','GRD',308),(89,'Guadeloupe','GP','GLP',312),(90,'Guam','GU','GUM',316),(91,'Guatemala','GT','GTM',320),(92,'Guernsey','GG','GGY',831),(93,'Guinea','GN','GIN',324),(94,'Guinea-Bissau','GW','GNB',624),(95,'Guyana','GY','GUY',328),(96,'Haiti','HT','HTI',332),(97,'Heard Island and McDonald Islands','HM','HMD',334),(98,'Holy See (Vatican City State)','VA','VAT',336),(99,'Honduras','HN','HND',340),(100,'Hong Kong','HK','HKG',344),(101,'Hungary','HU','HUN',348),(102,'Iceland','IS','ISL',352),(103,'India','IN','IND',356),(104,'Indonesia','ID','IDN',360),(105,'Iran, Islamic Republic of','IR','IRN',364),(106,'Iraq','IQ','IRQ',368),(107,'Ireland','IE','IRL',372),(108,'Isle of Man','IM','IMN',833),(109,'Israel','IL','ISR',376),(110,'Italy','IT','ITA',380),(111,'Jamaica','JM','JAM',388),(112,'Japan','JP','JPN',392),(113,'Jersey','JE','JEY',832),(114,'Jordan','JO','JOR',400),(115,'Kazakhstan','KZ','KAZ',398),(116,'Kenya','KE','KEN',404),(117,'Kiribati','KI','KIR',296),(118,'Korea, Democratic People\'s Republic of','KP','PRK',408),(119,'Korea, Republic of','KR','KOR',410),(120,'Kuwait','KW','KWT',414),(121,'Kyrgyzstan','KG','KGZ',417),(122,'Lao People\'s Democratic Republic','LA','LAO',418),(123,'Latvia','LV','LVA',428),(124,'Lebanon','LB','LBN',422),(125,'Lesotho','LS','LSO',426),(126,'Liberia','LR','LBR',430),(127,'Libya','LY','LBY',434),(128,'Liechtenstein','LI','LIE',438),(129,'Lithuania','LT','LTU',440),(130,'Luxembourg','LU','LUX',442),(131,'Macao','MO','MAC',446),(132,'Macedonia, the former Yugoslav Republic of','MK','MKD',807),(133,'Madagascar','MG','MDG',450),(134,'Malawi','MW','MWI',454),(135,'Malaysia','MY','MYS',458),(136,'Maldives','MV','MDV',462),(137,'Mali','ML','MLI',466),(138,'Malta','MT','MLT',470),(139,'Marshall Islands','MH','MHL',584),(140,'Martinique','MQ','MTQ',474),(141,'Mauritania','MR','MRT',478),(142,'Mauritius','MU','MUS',480),(143,'Mayotte','YT','MYT',175),(144,'Mexico','MX','MEX',484),(145,'Micronesia, Federated States of','FM','FSM',583),(146,'Moldova, Republic of','MD','MDA',498),(147,'Monaco','MC','MCO',492),(148,'Mongolia','MN','MNG',496),(149,'Montenegro','ME','MNE',499),(150,'Montserrat','MS','MSR',500),(151,'Morocco','MA','MAR',504),(152,'Mozambique','MZ','MOZ',508),(153,'Myanmar','MM','MMR',104),(154,'Namibia','NA','NAM',516),(155,'Nauru','NR','NRU',520),(156,'Nepal','NP','NPL',524),(157,'Netherlands','NL','NLD',528),(158,'New Caledonia','NC','NCL',540),(159,'New Zealand','NZ','NZL',554),(160,'Nicaragua','NI','NIC',558),(161,'Niger','NE','NER',562),(162,'Nigeria','NG','NGA',566),(163,'Niue','NU','NIU',570),(164,'Norfolk Island','NF','NFK',574),(165,'Northern Mariana Islands','MP','MNP',580),(166,'Norway','NO','NOR',578),(167,'Oman','OM','OMN',512),(168,'Pakistan','PK','PAK',586),(169,'Palau','PW','PLW',585),(170,'Palestine, State of','PS','PSE',275),(171,'Panama','PA','PAN',591),(172,'Papua New Guinea','PG','PNG',598),(173,'Paraguay','PY','PRY',600),(174,'Peru','PE','PER',604),(175,'Philippines','PH','PHL',608),(176,'Pitcairn','PN','PCN',612),(177,'Poland','PL','POL',616),(178,'Portugal','PT','PRT',620),(179,'Puerto Rico','PR','PRI',630),(180,'Qatar','QA','QAT',634),(181,'Reunion','RE','REU',638),(182,'Romania','RO','ROU',642),(183,'Russian Federation','RU','RUS',643),(184,'Rwanda','RW','RWA',646),(185,'Saint Barthelemy','BL','BLM',652),(186,'Saint Helena, Ascension and Tristan da Cunha','SH','SHN',654),(187,'Saint Kitts and Nevis','KN','KNA',659),(188,'Saint Lucia','LC','LCA',662),(189,'Saint Martin (French part)','MF','MAF',663),(190,'Saint Pierre and Miquelon','PM','SPM',666),(191,'Saint Vincent and the Grenadines','VC','VCT',670),(192,'Samoa','WS','WSM',882),(193,'San Marino','SM','SMR',674),(194,'Sao Tome and Principe','ST','STP',678),(195,'Saudi Arabia','SA','SAU',682),(196,'Senegal','SN','SEN',686),(197,'Serbia','RS','SRB',688),(198,'Seychelles','SC','SYC',690),(199,'Sierra Leone','SL','SLE',694),(200,'Singapore','SG','SGP',702),(201,'Sint Maarten (Dutch part)','SX','SXM',534),(202,'Slovakia','SK','SVK',703),(203,'Slovenia','SI','SVN',705),(204,'Solomon Islands','SB','SLB',90),(205,'Somalia','SO','SOM',706),(206,'South Africa','ZA','ZAF',710),(207,'South Georgia and the South Sandwich Islands','GS','SGS',239),(208,'South Sudan','SS','SSD',728),(209,'Spain','ES','ESP',724),(210,'Sri Lanka','LK','LKA',144),(211,'Sudan','SD','SDN',729),(212,'Suriname','SR','SUR',740),(213,'Svalbard and Jan Mayen','SJ','SJM',744),(214,'Swaziland','SZ','SWZ',748),(215,'Sweden','SE','SWE',752),(216,'Switzerland','CH','CHE',756),(217,'Syrian Arab Republic','SY','SYR',760),(218,'Taiwan, Province of China','TW','TWN',158),(219,'Tajikistan','TJ','TJK',762),(220,'Tanzania, United Republic of','TZ','TZA',834),(221,'Thailand','TH','THA',764),(222,'Timor-Leste','TL','TLS',626),(223,'Togo','TG','TGO',768),(224,'Tokelau','TK','TKL',772),(225,'Tonga','TO','TON',776),(226,'Trinidad and Tobago','TT','TTO',780),(227,'Tunisia','TN','TUN',788),(228,'Turkey','TR','TUR',792),(229,'Turkmenistan','TM','TKM',795),(230,'Turks and Caicos Islands','TC','TCA',796),(231,'Tuvalu','TV','TUV',798),(232,'Uganda','UG','UGA',800),(233,'Ukraine','UA','UKR',804),(234,'United Arab Emirates','AE','ARE',784),(235,'United Kingdom','GB','GBR',826),(236,'United States','US','USA',840),(237,'United States Minor Outlying Islands','UM','UMI',581),(238,'Uruguay','UY','URY',858),(239,'Uzbekistan','UZ','UZB',860),(240,'Vanuatu','VU','VUT',548),(241,'Venezuela, Bolivarian Republic of','VE','VEN',862),(242,'Vietnam','VN','VNM',704),(243,'Virgin Islands, British','VG','VGB',92),(244,'Virgin Islands, U.S.','VI','VIR',850),(245,'Wallis and Futuna','WF','WLF',876),(246,'Western Sahara','EH','ESH',732),(247,'Yemen','YE','YEM',887),(248,'Zambia','ZM','ZMB',894),(249,'Zimbabwe','ZW','ZWE',716);
/*!40000 ALTER TABLE `r_countries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_covid19_comorbidities`
--

DROP TABLE IF EXISTS `r_covid19_comorbidities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_covid19_comorbidities` (
  `comorbidity_id` int(11) NOT NULL AUTO_INCREMENT,
  `comorbidity_name` varchar(255) DEFAULT NULL,
  `comorbidity_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`comorbidity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_covid19_comorbidities`
--

LOCK TABLES `r_covid19_comorbidities` WRITE;
/*!40000 ALTER TABLE `r_covid19_comorbidities` DISABLE KEYS */;
INSERT INTO `r_covid19_comorbidities` VALUES (1,'Cardiovascular Disease','active','2022-02-18 16:25:07'),(2,'Asthma','active','2022-02-18 16:25:07'),(3,'Chronic Respiratory Disease','active','2022-02-18 16:25:07'),(4,'Diabetes','active','2022-02-18 16:25:07'),(5,'Chronic Liver Disease','active','2022-02-18 16:25:07'),(6,'Chronic Kidney Disease','active','2022-02-18 16:25:07'),(7,'HIV','active','2022-02-18 16:25:07'),(8,'Hypertension','active','2022-02-18 16:25:07'),(9,'Cancer','active','2022-02-18 16:25:07');
/*!40000 ALTER TABLE `r_covid19_comorbidities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_covid19_qc_testkits`
--

DROP TABLE IF EXISTS `r_covid19_qc_testkits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_covid19_qc_testkits`
--

LOCK TABLES `r_covid19_qc_testkits` WRITE;
/*!40000 ALTER TABLE `r_covid19_qc_testkits` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_covid19_qc_testkits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_covid19_results`
--

DROP TABLE IF EXISTS `r_covid19_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_covid19_results` (
  `result_id` varchar(255) NOT NULL,
  `result` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_covid19_results`
--

LOCK TABLES `r_covid19_results` WRITE;
/*!40000 ALTER TABLE `r_covid19_results` DISABLE KEYS */;
INSERT INTO `r_covid19_results` VALUES ('indeterminate','Indeterminate','active','2022-02-18 16:25:07',0),('negative','Negative','active','2022-02-18 16:25:07',0),('positive','Positive','active','2022-02-18 16:25:07',0);
/*!40000 ALTER TABLE `r_covid19_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_covid19_sample_rejection_reasons`
--

DROP TABLE IF EXISTS `r_covid19_sample_rejection_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_covid19_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_covid19_sample_rejection_reasons`
--

LOCK TABLES `r_covid19_sample_rejection_reasons` WRITE;
/*!40000 ALTER TABLE `r_covid19_sample_rejection_reasons` DISABLE KEYS */;
INSERT INTO `r_covid19_sample_rejection_reasons` VALUES (1,'Poorly labelled specimen','general','active','Gen_PLSP','2022-02-18 16:25:07',0),(2,'Mismatched sample and form labeling','general','active','Gen_MMSP','2022-02-18 16:25:07',0),(3,'Missing labels on container or tracking form','general','active','Gen_MLTS','2022-02-18 16:25:07',0),(4,'Sample without request forms/Tracking forms','general','active','Gen_SMRT','2022-02-18 16:25:07',0),(5,'Name/Information of requester is missing','general','active','Gen_NIRM','2022-02-18 16:25:07',0),(6,'Missing information on request form - Age','general','active','Gen_MIRA','2022-02-18 16:25:07',0),(7,'Missing information on request form - Sex','general','active','Gen_MIRS','2022-02-18 16:25:07',0),(8,'Missing information on request form - Sample Collection Date','general','active','Gen_MIRD','2022-02-18 16:25:07',0),(9,'Missing information on request form - ART No','general','active','Gen_MIAN','2022-02-18 16:25:07',0),(10,'Inappropriate specimen packing','general','active','Gen_ISPK','2022-02-18 16:25:07',0),(11,'Inappropriate specimen for test request','general','active','Gen_ISTR','2022-02-18 16:25:07',0),(12,'Form received without Sample','general','active','Gen_NoSample','2022-02-18 16:25:07',0),(13,'VL Machine Flag','testing','active','FLG_','2022-02-18 16:25:07',0),(14,'CNTRL_FAIL','testing','active','FLG_AL00','2022-02-18 16:25:07',0),(15,'SYS_ERROR','testing','active','FLG_TM00','2022-02-18 16:25:07',0),(16,'A/D_ABORT','testing','active','FLG_TM17','2022-02-18 16:25:07',0),(17,'KIT_EXPIRY','testing','active','FLG_TMAP','2022-02-18 16:25:07',0),(18,'RUN_EXPIRY','testing','active','FLG_TM19','2022-02-18 16:25:07',0),(19,'DATA_ERROR','testing','active','FLG_TM20','2022-02-18 16:25:07',0),(20,'NC_INVALID','testing','active','FLG_TM24','2022-02-18 16:25:07',0),(21,'LPCINVALID','testing','active','FLG_TM25','2022-02-18 16:25:07',0),(22,'MPCINVALID','testing','active','FLG_TM26','2022-02-18 16:25:07',0),(23,'HPCINVALID','testing','active','FLG_TM27','2022-02-18 16:25:07',0),(24,'S_INVALID','testing','active','FLG_TM29','2022-02-18 16:25:07',0),(25,'MATH_ERROR','testing','active','FLG_TM31','2022-02-18 16:25:07',0),(26,'PRECHECK','testing','active','FLG_TM44 ','2022-02-18 16:25:07',0),(27,'QS_INVALID','testing','active','FLG_TM50','2022-02-18 16:25:07',0),(28,'POSTCHECK','testing','active','FLG_TM51','2022-02-18 16:25:07',0),(29,'REAG_ERROR','testing','active','FLG_AP02 ','2022-02-18 16:25:07',0),(30,'NO_SAMPLE','testing','active','FLG_AP12','2022-02-18 16:25:07',0),(31,'DISP_ERROR','testing','active','FLG_AP13 ','2022-02-18 16:25:07',0),(32,'TEMP_RANGE','testing','active','FLG_AP19 ','2022-02-18 16:25:07',0),(33,'PREP_ABORT','testing','active','FLG_AP24','2022-02-18 16:25:07',0),(34,'SAMPLECLOT','testing','active','FLG_AP25','2022-02-18 16:25:07',0);
/*!40000 ALTER TABLE `r_covid19_sample_rejection_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_covid19_sample_type`
--

DROP TABLE IF EXISTS `r_covid19_sample_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_covid19_sample_type` (
  `sample_id` int(11) NOT NULL AUTO_INCREMENT,
  `sample_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_covid19_sample_type`
--

LOCK TABLES `r_covid19_sample_type` WRITE;
/*!40000 ALTER TABLE `r_covid19_sample_type` DISABLE KEYS */;
INSERT INTO `r_covid19_sample_type` VALUES (1,'Nasopharyngeal (NP)','active','2022-02-18 16:25:07',0),(2,'Oral-pharyngeal (OP)','active','2022-02-18 16:25:07',0),(3,'Both NP and OP','active','2022-02-18 16:25:07',0),(4,'Sputum','active','2022-02-18 16:25:07',0),(5,'Tracheal aspirate','active','2022-02-18 16:25:07',0),(6,'Nasal wash','active','2022-02-18 16:25:07',0),(7,'Serum','active','2022-02-18 16:25:07',0),(8,'Lung Tissue','active','2022-02-18 16:25:07',0),(9,'Whole blood','active','2022-02-18 16:25:07',0),(10,'Urine','active','2022-02-18 16:25:07',0),(11,'Stool','active','2022-02-18 16:25:07',0),(12,'Bronchoalveolar lavage','active','2022-02-18 16:25:07',0);
/*!40000 ALTER TABLE `r_covid19_sample_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_covid19_symptoms`
--

DROP TABLE IF EXISTS `r_covid19_symptoms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_covid19_symptoms` (
  `symptom_id` int(11) NOT NULL AUTO_INCREMENT,
  `symptom_name` varchar(255) DEFAULT NULL,
  `parent_symptom` int(11) DEFAULT NULL,
  `symptom_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`symptom_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_covid19_symptoms`
--

LOCK TABLES `r_covid19_symptoms` WRITE;
/*!40000 ALTER TABLE `r_covid19_symptoms` DISABLE KEYS */;
INSERT INTO `r_covid19_symptoms` VALUES (1,'Cough',NULL,'active','2022-02-18 16:25:07'),(2,'Shortness of Breath',NULL,'active','2022-02-18 16:25:07'),(3,'Sore Throat',NULL,'active','2022-02-18 16:25:07'),(4,'Chills',NULL,'active','2022-02-18 16:25:07'),(5,'Headache',NULL,'active','2022-02-18 16:25:07'),(6,'Muscles ache',NULL,'active','2022-02-18 16:25:07'),(7,'Vomiting/Nausea',NULL,'active','2022-02-18 16:25:07'),(8,'Abdominal Pain',NULL,'active','2022-02-18 16:25:07'),(9,'Diarrhoea',NULL,'active','2022-02-18 16:25:07');
/*!40000 ALTER TABLE `r_covid19_symptoms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_covid19_test_reasons`
--

DROP TABLE IF EXISTS `r_covid19_test_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_covid19_test_reasons` (
  `test_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_reason_name` varchar(255) DEFAULT NULL,
  `parent_reason` int(11) DEFAULT NULL,
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_covid19_test_reasons`
--

LOCK TABLES `r_covid19_test_reasons` WRITE;
/*!40000 ALTER TABLE `r_covid19_test_reasons` DISABLE KEYS */;
INSERT INTO `r_covid19_test_reasons` VALUES (1,'Suspect Case',NULL,'active','2022-02-18 16:25:07'),(2,'Asymptomatic Person who has been in contact with suspect/confirmed case',NULL,'active','2022-02-18 16:25:07'),(3,'Asymptomatic Person who has travelled to a country/area with confirmed Covid-19 Cases',NULL,'active','2022-02-18 16:25:07'),(4,'General Screening',NULL,'active','2022-02-18 16:25:07'),(5,'Control Test',NULL,'active','2022-02-18 16:25:07');
/*!40000 ALTER TABLE `r_covid19_test_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_eid_results`
--

DROP TABLE IF EXISTS `r_eid_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_eid_results` (
  `result_id` varchar(256) NOT NULL,
  `result` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_eid_results`
--

LOCK TABLES `r_eid_results` WRITE;
/*!40000 ALTER TABLE `r_eid_results` DISABLE KEYS */;
INSERT INTO `r_eid_results` VALUES ('indeterminate','Indeterminate','active',NULL,0),('negative','Negative','active',NULL,0),('positive','Positive','active',NULL,0);
/*!40000 ALTER TABLE `r_eid_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_eid_sample_rejection_reasons`
--

DROP TABLE IF EXISTS `r_eid_sample_rejection_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_eid_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_eid_sample_rejection_reasons`
--

LOCK TABLES `r_eid_sample_rejection_reasons` WRITE;
/*!40000 ALTER TABLE `r_eid_sample_rejection_reasons` DISABLE KEYS */;
INSERT INTO `r_eid_sample_rejection_reasons` VALUES (1,'Poorly labelled specimen','general','active','Gen_PLSP','2022-02-18 16:25:07',0),(2,'Mismatched sample and form labeling','general','active','Gen_MMSP','2022-02-18 16:25:07',0),(3,'Missing labels on container or tracking form','general','active','Gen_MLTS','2022-02-18 16:25:07',0),(4,'Sample without request forms/Tracking forms','general','active','Gen_SMRT','2022-02-18 16:25:07',0),(5,'Name/Information of requester is missing','general','active','Gen_NIRM','2022-02-18 16:25:07',0),(6,'Missing information on request form - Age','general','active','Gen_MIRA','2022-02-18 16:25:07',0),(7,'Missing information on request form - Sex','general','active','Gen_MIRS','2022-02-18 16:25:07',0),(8,'Missing information on request form - Sample Collection Date','general','active','Gen_MIRD','2022-02-18 16:25:07',0),(9,'Missing information on request form - ART No','general','active','Gen_MIAN','2022-02-18 16:25:07',0),(10,'Inappropriate specimen packing','general','active','Gen_ISPK','2022-02-18 16:25:07',0),(11,'Inappropriate specimen for test request','general','active','Gen_ISTR','2022-02-18 16:25:07',0),(12,'Wrong container/anticoagulant used','whole blood','active','BLD_WCAU','2022-02-18 16:25:07',0),(13,'EDTA tube specimens that arrived hemolyzed','whole blood','active','BLD_HMLY','2022-02-18 16:25:07',0),(14,'ETDA tube that arrives more than 24 hours after specimen collection','whole blood','active','BLD_AASC','2022-02-18 16:25:07',0),(15,'Plasma that arrives at a temperature above 8 C','plasma','active','PLS_AATA','2022-02-18 16:25:07',0),(16,'Plasma tube contain less than 1.5 mL','plasma','active','PSL_TCLT','2022-02-18 16:25:07',0),(17,'DBS cards with insufficient blood spots','dbs','active','DBS_IFBS','2022-02-18 16:25:07',0),(18,'DBS card with clotting present in spots','dbs','active','DBS_CPIS','2022-02-18 16:25:07',0),(19,'DBS cards that have serum rings indicating contamination around spots','dbs','active','DBS_SRIC','2022-02-18 16:25:07',0),(20,'VL Machine Flag','testing','active','FLG_','2022-02-18 16:25:07',0),(21,'CNTRL_FAIL','testing','active','FLG_AL00','2022-02-18 16:25:07',0),(22,'SYS_ERROR','testing','active','FLG_TM00','2022-02-18 16:25:07',0),(23,'A/D_ABORT','testing','active','FLG_TM17','2022-02-18 16:25:07',0),(24,'KIT_EXPIRY','testing','active','FLG_TMAP','2022-02-18 16:25:07',0),(25,'RUN_EXPIRY','testing','active','FLG_TM19','2022-02-18 16:25:07',0),(26,'DATA_ERROR','testing','active','FLG_TM20','2022-02-18 16:25:07',0),(27,'NC_INVALID','testing','active','FLG_TM24','2022-02-18 16:25:07',0),(28,'LPCINVALID','testing','active','FLG_TM25','2022-02-18 16:25:07',0),(29,'MPCINVALID','testing','active','FLG_TM26','2022-02-18 16:25:07',0),(30,'HPCINVALID','testing','active','FLG_TM27','2022-02-18 16:25:07',0),(31,'S_INVALID','testing','active','FLG_TM29','2022-02-18 16:25:07',0),(32,'MATH_ERROR','testing','active','FLG_TM31','2022-02-18 16:25:07',0),(33,'PRECHECK','testing','active','FLG_TM44 ','2022-02-18 16:25:07',0),(34,'QS_INVALID','testing','active','FLG_TM50','2022-02-18 16:25:07',0),(35,'POSTCHECK','testing','active','FLG_TM51','2022-02-18 16:25:07',0),(36,'REAG_ERROR','testing','active','FLG_AP02 ','2022-02-18 16:25:07',0),(37,'NO_SAMPLE','testing','active','FLG_AP12','2022-02-18 16:25:07',0),(38,'DISP_ERROR','testing','active','FLG_AP13 ','2022-02-18 16:25:07',0),(39,'TEMP_RANGE','testing','active','FLG_AP19 ','2022-02-18 16:25:07',0),(40,'PREP_ABORT','testing','active','FLG_AP24','2022-02-18 16:25:07',0),(41,'SAMPLECLOT','testing','active','FLG_AP25','2022-02-18 16:25:07',0),(42,'Form received without Sample','general','active','Gen_NoSample','2022-02-18 16:25:07',0);
/*!40000 ALTER TABLE `r_eid_sample_rejection_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_eid_sample_type`
--

DROP TABLE IF EXISTS `r_eid_sample_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_eid_sample_type` (
  `sample_id` int(11) NOT NULL AUTO_INCREMENT,
  `sample_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_eid_sample_type`
--

LOCK TABLES `r_eid_sample_type` WRITE;
/*!40000 ALTER TABLE `r_eid_sample_type` DISABLE KEYS */;
INSERT INTO `r_eid_sample_type` VALUES (1,'DBS','active','2022-02-18 16:25:07',0),(2,'Whole Blood','active','2022-02-18 16:25:07',0);
/*!40000 ALTER TABLE `r_eid_sample_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_eid_test_reasons`
--

DROP TABLE IF EXISTS `r_eid_test_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_eid_test_reasons` (
  `test_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_reason_name` varchar(255) DEFAULT NULL,
  `parent_reason` int(11) DEFAULT '0',
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) DEFAULT '0',
  PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_eid_test_reasons`
--

LOCK TABLES `r_eid_test_reasons` WRITE;
/*!40000 ALTER TABLE `r_eid_test_reasons` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_eid_test_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_funding_sources`
--

DROP TABLE IF EXISTS `r_funding_sources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_funding_sources` (
  `funding_source_id` int(11) NOT NULL AUTO_INCREMENT,
  `funding_source_name` varchar(500) NOT NULL,
  `funding_source_status` varchar(45) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) DEFAULT '0',
  PRIMARY KEY (`funding_source_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_funding_sources`
--

LOCK TABLES `r_funding_sources` WRITE;
/*!40000 ALTER TABLE `r_funding_sources` DISABLE KEYS */;
INSERT INTO `r_funding_sources` VALUES (1,'USA Govt','active',NULL,0);
/*!40000 ALTER TABLE `r_funding_sources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_generic_sample_rejection_reasons`
--

DROP TABLE IF EXISTS `r_generic_sample_rejection_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_generic_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_generic_sample_rejection_reasons`
--

LOCK TABLES `r_generic_sample_rejection_reasons` WRITE;
/*!40000 ALTER TABLE `r_generic_sample_rejection_reasons` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_generic_sample_rejection_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_generic_sample_types`
--

DROP TABLE IF EXISTS `r_generic_sample_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_generic_sample_types` (
  `sample_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `sample_type_code` varchar(256) DEFAULT NULL,
  `sample_type_name` varchar(256) DEFAULT NULL,
  `sample_type_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sample_type_id`),
  UNIQUE KEY `sample_type_code` (`sample_type_code`),
  UNIQUE KEY `sample_type_name` (`sample_type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_generic_sample_types`
--

LOCK TABLES `r_generic_sample_types` WRITE;
/*!40000 ALTER TABLE `r_generic_sample_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_generic_sample_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_generic_symptoms`
--

DROP TABLE IF EXISTS `r_generic_symptoms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_generic_symptoms` (
  `symptom_id` int(11) NOT NULL AUTO_INCREMENT,
  `symptom_name` varchar(256) DEFAULT NULL,
  `symptom_code` varchar(256) DEFAULT NULL,
  `symptom_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`symptom_id`),
  UNIQUE KEY `symptom_code` (`symptom_code`),
  UNIQUE KEY `symptom_name` (`symptom_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_generic_symptoms`
--

LOCK TABLES `r_generic_symptoms` WRITE;
/*!40000 ALTER TABLE `r_generic_symptoms` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_generic_symptoms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_generic_test_categories`
--

DROP TABLE IF EXISTS `r_generic_test_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_generic_test_categories` (
  `test_category_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_category_name` varchar(256) DEFAULT NULL,
  `test_category_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_category_id`),
  UNIQUE KEY `test_category_name` (`test_category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_generic_test_categories`
--

LOCK TABLES `r_generic_test_categories` WRITE;
/*!40000 ALTER TABLE `r_generic_test_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_generic_test_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_generic_test_failure_reasons`
--

DROP TABLE IF EXISTS `r_generic_test_failure_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_generic_test_failure_reasons` (
  `test_failure_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_failure_reason_code` varchar(256) NOT NULL,
  `test_failure_reason` varchar(256) DEFAULT NULL,
  `test_failure_reason_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) DEFAULT NULL,
  PRIMARY KEY (`test_failure_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_generic_test_failure_reasons`
--

LOCK TABLES `r_generic_test_failure_reasons` WRITE;
/*!40000 ALTER TABLE `r_generic_test_failure_reasons` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_generic_test_failure_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_generic_test_methods`
--

DROP TABLE IF EXISTS `r_generic_test_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_generic_test_methods` (
  `test_method_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_method_name` varchar(256) DEFAULT NULL,
  `test_method_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_method_id`),
  UNIQUE KEY `test_method_name` (`test_method_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_generic_test_methods`
--

LOCK TABLES `r_generic_test_methods` WRITE;
/*!40000 ALTER TABLE `r_generic_test_methods` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_generic_test_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_generic_test_reasons`
--

DROP TABLE IF EXISTS `r_generic_test_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_generic_test_reasons` (
  `test_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_reason_code` varchar(256) DEFAULT NULL,
  `test_reason` varchar(256) DEFAULT NULL,
  `test_reason_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_reason_id`),
  UNIQUE KEY `test_reason_code` (`test_reason_code`),
  UNIQUE KEY `test_reason` (`test_reason`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_generic_test_reasons`
--

LOCK TABLES `r_generic_test_reasons` WRITE;
/*!40000 ALTER TABLE `r_generic_test_reasons` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_generic_test_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_generic_test_result_units`
--

DROP TABLE IF EXISTS `r_generic_test_result_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_generic_test_result_units` (
  `unit_id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_name` varchar(256) DEFAULT NULL,
  `unit_status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`unit_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_generic_test_result_units`
--

LOCK TABLES `r_generic_test_result_units` WRITE;
/*!40000 ALTER TABLE `r_generic_test_result_units` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_generic_test_result_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_hepatitis_comorbidities`
--

DROP TABLE IF EXISTS `r_hepatitis_comorbidities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_hepatitis_comorbidities` (
  `comorbidity_id` int(11) NOT NULL AUTO_INCREMENT,
  `comorbidity_name` varchar(255) DEFAULT NULL,
  `comorbidity_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`comorbidity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_hepatitis_comorbidities`
--

LOCK TABLES `r_hepatitis_comorbidities` WRITE;
/*!40000 ALTER TABLE `r_hepatitis_comorbidities` DISABLE KEYS */;
INSERT INTO `r_hepatitis_comorbidities` VALUES (1,'Diabetes','active','2020-11-17 16:32:11'),(2,'Chronic renal failure','active','2020-11-17 16:32:11'),(3,'Cancer','active','2020-11-17 16:32:11'),(4,'HIV infection','active','2020-11-17 16:32:11'),(5,'Cardiovascular disease','active','2020-11-17 16:32:11'),(6,'HPV','active','2020-11-17 16:32:11');
/*!40000 ALTER TABLE `r_hepatitis_comorbidities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_hepatitis_results`
--

DROP TABLE IF EXISTS `r_hepatitis_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_hepatitis_results` (
  `result_id` varchar(255) NOT NULL,
  `result` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_hepatitis_results`
--

LOCK TABLES `r_hepatitis_results` WRITE;
/*!40000 ALTER TABLE `r_hepatitis_results` DISABLE KEYS */;
INSERT INTO `r_hepatitis_results` VALUES ('indeterminate','Indeterminate','active','2021-02-18 00:00:00',0),('negative','Negative','active','2021-02-18 00:00:00',0),('positive','Positive','active','2021-02-18 00:00:00',0);
/*!40000 ALTER TABLE `r_hepatitis_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_hepatitis_risk_factors`
--

DROP TABLE IF EXISTS `r_hepatitis_risk_factors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_hepatitis_risk_factors` (
  `riskfactor_id` int(11) NOT NULL AUTO_INCREMENT,
  `riskfactor_name` varchar(255) DEFAULT NULL,
  `riskfactor_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`riskfactor_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_hepatitis_risk_factors`
--

LOCK TABLES `r_hepatitis_risk_factors` WRITE;
/*!40000 ALTER TABLE `r_hepatitis_risk_factors` DISABLE KEYS */;
INSERT INTO `r_hepatitis_risk_factors` VALUES (1,'Ever diagnosed with a liver disease','active','2020-11-17 16:35:09'),(2,'Viral hepatitis in the family','active','2020-11-17 16:35:09'),(3,'Ever been operated','active','2020-11-17 16:35:09'),(4,'Ever been traditionally operated (ibyinyo, ibirimi, indasago, scarification, tattoo)','active','2020-11-17 16:35:09'),(5,'Ever been transfused','active','2020-11-17 16:35:09'),(6,'Having more than one sexually partner','active','2020-11-17 16:35:09'),(7,'Ever experienced a physical trauma','active','2020-11-17 16:35:09');
/*!40000 ALTER TABLE `r_hepatitis_risk_factors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_hepatitis_sample_rejection_reasons`
--

DROP TABLE IF EXISTS `r_hepatitis_sample_rejection_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_hepatitis_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_hepatitis_sample_rejection_reasons`
--

LOCK TABLES `r_hepatitis_sample_rejection_reasons` WRITE;
/*!40000 ALTER TABLE `r_hepatitis_sample_rejection_reasons` DISABLE KEYS */;
INSERT INTO `r_hepatitis_sample_rejection_reasons` VALUES (1,'Poorly labelled specimen','general','active','Gen_PLSP','2021-02-22 15:27:49',0),(2,'Mismatched sample and form labeling','general','active','Gen_MMSP','2021-02-22 15:27:49',0),(3,'Missing labels on container or tracking form','general','active','Gen_MLTS','2021-02-22 15:27:49',0),(4,'Sample without request forms/Tracking forms','general','active','Gen_SMRT','2021-02-22 15:27:49',0),(5,'Name/Information of requester is missing','general','active','Gen_NIRM','2021-02-22 15:27:49',0),(6,'Missing information on request form - Age','general','active','Gen_MIRA','2021-02-22 15:27:49',0),(7,'Missing information on request form - Sex','general','active','Gen_MIRS','2021-02-22 15:27:49',0),(8,'Missing information on request form - Sample Collection Date','general','active','Gen_MIRD','2021-02-22 15:27:49',0),(9,'Missing information on request form - ART No','general','active','Gen_MIAN','2021-02-22 15:27:49',0),(10,'Inappropriate specimen packing','general','active','Gen_ISPK','2021-02-22 15:27:49',0),(11,'Inappropriate specimen for test request','general','active','Gen_ISTR','2021-02-22 15:27:49',0),(42,'Form received without Sample','general','active','Gen_NoSample','2021-02-22 15:27:49',0);
/*!40000 ALTER TABLE `r_hepatitis_sample_rejection_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_hepatitis_sample_type`
--

DROP TABLE IF EXISTS `r_hepatitis_sample_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_hepatitis_sample_type` (
  `sample_id` int(11) NOT NULL AUTO_INCREMENT,
  `sample_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_hepatitis_sample_type`
--

LOCK TABLES `r_hepatitis_sample_type` WRITE;
/*!40000 ALTER TABLE `r_hepatitis_sample_type` DISABLE KEYS */;
INSERT INTO `r_hepatitis_sample_type` VALUES (1,'Whole Blood','active','2021-02-22 15:13:21',0);
/*!40000 ALTER TABLE `r_hepatitis_sample_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_hepatitis_test_reasons`
--

DROP TABLE IF EXISTS `r_hepatitis_test_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_hepatitis_test_reasons` (
  `test_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_reason_name` varchar(255) DEFAULT NULL,
  `parent_reason` int(11) DEFAULT NULL,
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_hepatitis_test_reasons`
--

LOCK TABLES `r_hepatitis_test_reasons` WRITE;
/*!40000 ALTER TABLE `r_hepatitis_test_reasons` DISABLE KEYS */;
INSERT INTO `r_hepatitis_test_reasons` VALUES (1,'Follow up ',0,'active','2021-02-22 15:13:41'),(2,'Confirmation',0,'active','2021-02-22 15:13:41');
/*!40000 ALTER TABLE `r_hepatitis_test_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_implementation_partners`
--

DROP TABLE IF EXISTS `r_implementation_partners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_implementation_partners` (
  `i_partner_id` int(11) NOT NULL AUTO_INCREMENT,
  `i_partner_name` varchar(500) NOT NULL,
  `i_partner_status` varchar(45) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) DEFAULT '0',
  PRIMARY KEY (`i_partner_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_implementation_partners`
--

LOCK TABLES `r_implementation_partners` WRITE;
/*!40000 ALTER TABLE `r_implementation_partners` DISABLE KEYS */;
INSERT INTO `r_implementation_partners` VALUES (1,'USA Govt','active',NULL,0);
/*!40000 ALTER TABLE `r_implementation_partners` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_recommended_corrective_actions`
--

DROP TABLE IF EXISTS `r_recommended_corrective_actions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_recommended_corrective_actions` (
  `recommended_corrective_action_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_type` varchar(11) DEFAULT NULL,
  `recommended_corrective_action_name` varchar(256) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`recommended_corrective_action_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_recommended_corrective_actions`
--

LOCK TABLES `r_recommended_corrective_actions` WRITE;
/*!40000 ALTER TABLE `r_recommended_corrective_actions` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_recommended_corrective_actions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_sample_controls`
--

DROP TABLE IF EXISTS `r_sample_controls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_sample_controls` (
  `r_sample_control_id` int(11) NOT NULL AUTO_INCREMENT,
  `r_sample_control_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`r_sample_control_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_sample_controls`
--

LOCK TABLES `r_sample_controls` WRITE;
/*!40000 ALTER TABLE `r_sample_controls` DISABLE KEYS */;
INSERT INTO `r_sample_controls` VALUES (1,'NC'),(2,'LPC'),(3,'HPC'),(4,'S');
/*!40000 ALTER TABLE `r_sample_controls` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_sample_status`
--

DROP TABLE IF EXISTS `r_sample_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_sample_status` (
  `status_id` int(11) NOT NULL AUTO_INCREMENT,
  `status_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`status_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_sample_status`
--

LOCK TABLES `r_sample_status` WRITE;
/*!40000 ALTER TABLE `r_sample_status` DISABLE KEYS */;
INSERT INTO `r_sample_status` VALUES (1,'Hold','active'),(2,'Lost','active'),(3,'Sample Reordered','active'),(4,'Rejected','active'),(5,'Failed/Invalid','active'),(6,'Sample Registered at Testing Lab','active'),(7,'Accepted','active'),(8,'Awaiting Approval','active'),(9,'Sample Currently Registered at Health Center','active'),(10,'Expired','active'),(11,'No Result','active'),(12,'Cancelled','active');
/*!40000 ALTER TABLE `r_sample_status` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_tb_results`
--

DROP TABLE IF EXISTS `r_tb_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_tb_results` (
  `result_id` varchar(256) NOT NULL,
  `result` varchar(256) DEFAULT NULL,
  `result_type` varchar(256) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_tb_results`
--

LOCK TABLES `r_tb_results` WRITE;
/*!40000 ALTER TABLE `r_tb_results` DISABLE KEYS */;
INSERT INTO `r_tb_results` VALUES ('1','Positive',NULL,'active','2021-11-16 15:23:42',0),('10','TT (MTB detected (Trace) rifampicin resistance indeterminate)','x-pert','active','2021-11-16 15:25:26',0),('11','I (Invalid/Error/No result)','x-pert','active','2021-11-16 15:25:26',0),('2','Negative',NULL,'active','2021-11-16 15:23:42',0),('3','Negative','lam','active','2021-11-16 15:25:26',0),('4','Positive','lam','active','2021-11-16 15:25:26',0),('5','Invalid','lam','active','2021-11-16 15:25:26',0),('6','N (MTB not detected)','x-pert','active','2021-11-16 15:25:26',0),('7','T (MTB detected rifampicin resistance not detected)','x-pert','active','2021-11-16 15:25:26',0),('8','TI (MTB detected rifampicin resistance indeterminate)','x-pert','active','2021-11-16 15:25:26',0),('9','RR (MTB detected rifampicin resistance detected)','lam','active','2021-11-16 15:25:26',0);
/*!40000 ALTER TABLE `r_tb_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_tb_sample_rejection_reasons`
--

DROP TABLE IF EXISTS `r_tb_sample_rejection_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_tb_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `rejection_reason_name` varchar(256) DEFAULT NULL,
  `rejection_type` varchar(256) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(45) DEFAULT NULL,
  `rejection_reason_code` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_tb_sample_rejection_reasons`
--

LOCK TABLES `r_tb_sample_rejection_reasons` WRITE;
/*!40000 ALTER TABLE `r_tb_sample_rejection_reasons` DISABLE KEYS */;
INSERT INTO `r_tb_sample_rejection_reasons` VALUES (1,'Sample damaged','general','active',NULL,'2021-11-16 15:23:42',0);
/*!40000 ALTER TABLE `r_tb_sample_rejection_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_tb_sample_type`
--

DROP TABLE IF EXISTS `r_tb_sample_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_tb_sample_type` (
  `sample_id` int(11) NOT NULL AUTO_INCREMENT,
  `sample_name` varchar(256) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_tb_sample_type`
--

LOCK TABLES `r_tb_sample_type` WRITE;
/*!40000 ALTER TABLE `r_tb_sample_type` DISABLE KEYS */;
INSERT INTO `r_tb_sample_type` VALUES (1,'Serum','active','2021-11-16 15:23:42',0);
/*!40000 ALTER TABLE `r_tb_sample_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_tb_test_reasons`
--

DROP TABLE IF EXISTS `r_tb_test_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_tb_test_reasons` (
  `test_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_reason_name` varchar(256) DEFAULT NULL,
  `parent_reason` int(11) DEFAULT NULL,
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_tb_test_reasons`
--

LOCK TABLES `r_tb_test_reasons` WRITE;
/*!40000 ALTER TABLE `r_tb_test_reasons` DISABLE KEYS */;
INSERT INTO `r_tb_test_reasons` VALUES (1,'Case confirmed in TB',0,'active','2021-11-16 15:23:42');
/*!40000 ALTER TABLE `r_tb_test_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_test_types`
--

DROP TABLE IF EXISTS `r_test_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_test_types` (
  `test_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_standard_name` varchar(255) DEFAULT NULL,
  `test_generic_name` varchar(255) DEFAULT NULL,
  `test_short_code` varchar(255) DEFAULT NULL,
  `test_loinc_code` varchar(255) DEFAULT NULL,
  `test_category` varchar(256) DEFAULT NULL,
  `test_form_config` text,
  `test_results_config` text,
  `test_status` varchar(100) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`test_type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_test_types`
--

LOCK TABLES `r_test_types` WRITE;
/*!40000 ALTER TABLE `r_test_types` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_test_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_vl_art_regimen`
--

DROP TABLE IF EXISTS `r_vl_art_regimen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_vl_art_regimen` (
  `art_id` int(11) NOT NULL AUTO_INCREMENT,
  `art_code` varchar(255) DEFAULT NULL,
  `parent_art` int(11) NOT NULL,
  `headings` varchar(255) DEFAULT NULL,
  `nation_identifier` varchar(255) DEFAULT NULL,
  `art_status` varchar(45) NOT NULL DEFAULT 'active',
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`art_id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_vl_art_regimen`
--

LOCK TABLES `r_vl_art_regimen` WRITE;
/*!40000 ALTER TABLE `r_vl_art_regimen` DISABLE KEYS */;
INSERT INTO `r_vl_art_regimen` VALUES (1,'1a = TDF+3TC+DTG',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(2,'1b = TDF+3TC+EFV',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(3,'1c = ABC+3TC+EFV',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(4,'1d = ABC+3TC+NVP',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(5,'1e = TDF+3TC+NVP',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(6,'1f = ABC+3TC+DTG',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(7,'1g = AZT+3TC+EFV',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(8,'1h = AZT+3TC+NVP',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(9,'2a = AZT+3TC+ATV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(10,'2b = AZT+3TC+LPV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(11,'2c = AZT+3TC+DTG',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(12,'2d = TDF+3TC+ATV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(13,'2e = TDF+3TC+LPV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(14,'2f = ABC+3TC+ATV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(15,'2g = ABC+3TC+LPV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(16,'3a = RAL+ETV+DRV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(17,'4a = ABC+3TC+LPV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(18,'4b = ABC+3TC+EFV',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(19,'4c = AZT+3TC+LPV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(20,'4d = AZT+3TC+EFV',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(21,'4e = TDF+3TC+EFV',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(22,'4f = ABC+3TC+NVP',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(23,'4g = AZT+3TC+NVP',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(24,'5a = AZT+3TC+RAL',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(25,'5b = ABC+3TC+RAL',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(26,'5c = AZT+3TC+LPV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(27,'5d = ABC+3TC+LPV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0),(28,'5e = AZT + 3TC+ ATV/r',0,NULL,'rwd','active','2022-02-18 16:25:07',0);
/*!40000 ALTER TABLE `r_vl_art_regimen` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_vl_results`
--

DROP TABLE IF EXISTS `r_vl_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_vl_results` (
  `result_id` int(11) NOT NULL AUTO_INCREMENT,
  `result` varchar(255) NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `available_for_instruments` json DEFAULT NULL,
  `interpretation` varchar(25) NOT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`result_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_vl_results`
--

LOCK TABLES `r_vl_results` WRITE;
/*!40000 ALTER TABLE `r_vl_results` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_vl_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_vl_sample_rejection_reasons`
--

DROP TABLE IF EXISTS `r_vl_sample_rejection_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_vl_sample_rejection_reasons` (
  `rejection_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `rejection_reason_name` varchar(255) DEFAULT NULL,
  `rejection_type` varchar(255) NOT NULL DEFAULT 'general',
  `rejection_reason_status` varchar(255) DEFAULT NULL,
  `rejection_reason_code` varchar(255) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`rejection_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_vl_sample_rejection_reasons`
--

LOCK TABLES `r_vl_sample_rejection_reasons` WRITE;
/*!40000 ALTER TABLE `r_vl_sample_rejection_reasons` DISABLE KEYS */;
INSERT INTO `r_vl_sample_rejection_reasons` VALUES (1,'Poorly labelled specimen','general','active','Gen_PLSP','2022-02-18 16:25:07',1),(2,'Mismatched sample and form labeling','general','active','Gen_MMSP','2022-02-18 16:25:07',1),(3,'Missing labels on container or tracking form','general','active','Gen_MLTS','2022-02-18 16:25:07',1),(4,'Sample without request forms/Tracking forms','general','active','Gen_SMRT','2022-02-18 16:25:07',1),(5,'Name/Information of requester is missing','general','active','Gen_NIRM','2022-02-18 16:25:07',1),(6,'Missing information on request form - Age','general','active','Gen_MIRA','2022-02-18 16:25:07',1),(7,'Missing information on request form - Sex','general','active','Gen_MIRS','2022-02-18 16:25:07',1),(8,'Missing information on request form - Sample Collection Date','general','active','Gen_MIRD','2022-02-18 16:25:07',1),(9,'Missing information on request form - ART No','general','active','Gen_MIAN','2022-02-18 16:25:07',1),(10,'Inappropriate specimen packing','general','active','Gen_ISPK','2022-02-18 16:25:07',1),(11,'Inappropriate specimen for test request','general','active','Gen_ISTR','2022-02-18 16:25:07',1),(12,'Wrong container/anticoagulant used','whole blood','active','BLD_WCAU','2022-02-18 16:25:07',1),(13,'EDTA tube specimens that arrived hemolyzed','whole blood','active','BLD_HMLY','2022-02-18 16:25:07',1),(14,'ETDA tube that arrives more than 24 hours after specimen collection','whole blood','active','BLD_AASC','2022-02-18 16:25:07',1),(15,'Plasma that arrives at a temperature above 8 C','plasma','active','PLS_AATA','2022-02-18 16:25:07',1),(16,'Plasma tube contain less than 1.5 mL','plasma','active','PSL_TCLT','2022-02-18 16:25:07',1),(17,'DBS cards with insufficient blood spots','dbs','active','DBS_IFBS','2022-02-18 16:25:07',1),(18,'DBS card with clotting present in spots','dbs','active','DBS_CPIS','2022-02-18 16:25:07',1),(19,'DBS cards that have serum rings indicating contamination around spots','dbs','active','DBS_SRIC','2022-02-18 16:25:07',1),(20,'VL Machine Flag','testing','active','FLG_','2022-02-18 16:25:07',1),(21,'CNTRL_FAIL','testing','active','FLG_AL00','2022-02-18 16:25:07',1),(22,'SYS_ERROR','testing','active','FLG_TM00','2022-02-18 16:25:07',1),(23,'A/D_ABORT','testing','active','FLG_TM17','2022-02-18 16:25:07',1),(24,'KIT_EXPIRY','testing','active','FLG_TMAP','2022-02-18 16:25:07',1),(25,'RUN_EXPIRY','testing','active','FLG_TM19','2022-02-18 16:25:07',1),(26,'DATA_ERROR','testing','active','FLG_TM20','2022-02-18 16:25:07',1),(27,'NC_INVALID','testing','active','FLG_TM24','2022-02-18 16:25:07',1),(28,'LPCINVALID','testing','active','FLG_TM25','2022-02-18 16:25:07',1),(29,'MPCINVALID','testing','active','FLG_TM26','2022-02-18 16:25:07',1),(30,'HPCINVALID','testing','active','FLG_TM27','2022-02-18 16:25:07',1),(31,'S_INVALID','testing','active','FLG_TM29','2022-02-18 16:25:07',1),(32,'MATH_ERROR','testing','active','FLG_TM31','2022-02-18 16:25:07',1),(33,'PRECHECK','testing','active','FLG_TM44 ','2022-02-18 16:25:07',1),(34,'QS_INVALID','testing','active','FLG_TM50','2022-02-18 16:25:07',1),(35,'POSTCHECK','testing','active','FLG_TM51','2022-02-18 16:25:07',1),(36,'REAG_ERROR','testing','active','FLG_AP02 ','2022-02-18 16:25:07',1),(37,'NO_SAMPLE','testing','active','FLG_AP12','2022-02-18 16:25:07',1),(38,'DISP_ERROR','testing','active','FLG_AP13 ','2022-02-18 16:25:07',1),(39,'TEMP_RANGE','testing','active','FLG_AP19 ','2022-02-18 16:25:07',1),(40,'PREP_ABORT','testing','active','FLG_AP24','2022-02-18 16:25:07',1),(41,'SAMPLECLOT','testing','active','FLG_AP25','2022-02-18 16:25:07',1),(42,'Form received without Sample','general','active','Gen_NoSample','2022-02-18 16:25:07',0);
/*!40000 ALTER TABLE `r_vl_sample_rejection_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_vl_sample_type`
--

DROP TABLE IF EXISTS `r_vl_sample_type`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_vl_sample_type` (
  `sample_id` int(11) NOT NULL AUTO_INCREMENT,
  `sample_name` varchar(255) DEFAULT NULL,
  `status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sample_id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_vl_sample_type`
--

LOCK TABLES `r_vl_sample_type` WRITE;
/*!40000 ALTER TABLE `r_vl_sample_type` DISABLE KEYS */;
INSERT INTO `r_vl_sample_type` VALUES (1,'Plasma','inactive','2022-02-18 16:25:07',1),(2,'Venous blood (EDTA)','active','2022-02-18 16:25:07',1),(3,'DBS capillary (infants only)','inactive','2022-02-18 16:25:07',1),(4,'Dried Blood Spot','inactive','2022-08-10 10:47:17',1),(5,'PPT','inactive','2022-02-18 16:25:07',1);
/*!40000 ALTER TABLE `r_vl_sample_type` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_vl_test_failure_reasons`
--

DROP TABLE IF EXISTS `r_vl_test_failure_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_vl_test_failure_reasons` (
  `failure_id` int(11) NOT NULL AUTO_INCREMENT,
  `failure_reason` varchar(256) DEFAULT NULL,
  `status` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) DEFAULT NULL,
  PRIMARY KEY (`failure_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_vl_test_failure_reasons`
--

LOCK TABLES `r_vl_test_failure_reasons` WRITE;
/*!40000 ALTER TABLE `r_vl_test_failure_reasons` DISABLE KEYS */;
/*!40000 ALTER TABLE `r_vl_test_failure_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `r_vl_test_reasons`
--

DROP TABLE IF EXISTS `r_vl_test_reasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `r_vl_test_reasons` (
  `test_reason_id` int(11) NOT NULL AUTO_INCREMENT,
  `test_reason_name` varchar(255) DEFAULT NULL,
  `parent_reason` int(11) DEFAULT '0',
  `test_reason_status` varchar(45) DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `data_sync` int(11) DEFAULT '0',
  PRIMARY KEY (`test_reason_id`)
) ENGINE=InnoDB AUTO_INCREMENT=10000 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `r_vl_test_reasons`
--

LOCK TABLES `r_vl_test_reasons` WRITE;
/*!40000 ALTER TABLE `r_vl_test_reasons` DISABLE KEYS */;
INSERT INTO `r_vl_test_reasons` VALUES (1,'routine',0,'active','2022-02-18 16:25:07',0),(2,'Confirmation Of Treatment Failure(repeat VL at 3M)',0,'active','2022-02-18 16:25:07',0),(3,'failure',0,'active','2022-02-18 16:25:07',0),(4,'immunological failure',0,'active','2022-02-18 16:25:07',0),(5,'single drug substitution',0,'active','2022-02-18 16:25:07',0),(6,'Pregnant Mother',0,'active','2022-02-18 16:25:07',0),(7,'Lactating Mother',0,'active','2022-02-18 16:25:07',0),(8,'Baseline VL',0,'active','2022-02-18 16:25:07',0),(10,'suspect',0,'active','2022-02-18 16:25:07',0),(11,'Excol',0,'active','2022-02-18 16:25:07',0),(12,'result missing',0,'active','2022-02-18 16:25:07',0),(13,'value missed',0,'active','2022-02-18 16:25:07',0),(14,'routine',0,'active','2022-02-18 16:25:07',0),(15,'failure',0,'active','2022-02-18 16:25:07',0),(9999,'recency',0,'active','2022-02-18 16:25:07',0);
/*!40000 ALTER TABLE `r_vl_test_reasons` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `report_to_mail`
--

DROP TABLE IF EXISTS `report_to_mail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `report_to_mail` (
  `report_mail_id` int(11) NOT NULL AUTO_INCREMENT,
  `batch_id` int(11) NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `to_mail` varchar(255) DEFAULT NULL,
  `encrypt` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `comment` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`report_mail_id`),
  KEY `batch_id` (`batch_id`),
  CONSTRAINT `report_to_mail_ibfk_1` FOREIGN KEY (`batch_id`) REFERENCES `batch_details` (`batch_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report_to_mail`
--

LOCK TABLES `report_to_mail` WRITE;
/*!40000 ALTER TABLE `report_to_mail` DISABLE KEYS */;
/*!40000 ALTER TABLE `report_to_mail` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `resources`
--

DROP TABLE IF EXISTS `resources`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `resources` (
  `resource_id` varchar(255) NOT NULL,
  `module` varchar(255) NOT NULL,
  `display_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`resource_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `resources`
--

LOCK TABLES `resources` WRITE;
/*!40000 ALTER TABLE `resources` DISABLE KEYS */;
INSERT INTO `resources` VALUES ('common-reference','admin','Common Reference Tables'),('covid-19-batches','covid19','Covid-19 Batch Management'),('covid-19-management','covid19','Covid-19 Reports'),('covid-19-reference','admin','Covid-19 Reference Tables'),('covid-19-requests','covid19','Covid-19 Request Management'),('covid-19-results','covid19','Covid-19 Result Management'),('eid-batches','eid','EID Batch Management'),('eid-management','eid','EID Reports'),('eid-reference','admin','EID Reference Management'),('eid-requests','eid','EID Request Management'),('eid-results','eid','EID Result Management'),('facilities','admin','Manage Facility'),('generic-management','generic-tests','Lab Tests Report Management'),('generic-requests','generic-tests','Lab Tests Request Management'),('generic-results','generic-tests','Lab Tests Result Management'),('generic-tests-batches','generic-tests','Lab Tests Batch Management'),('generic-tests-config','admin','Configure Generic Lab Tests'),('global-config','admin','Manage General Config'),('hepatitis-batches','hepatitis','Hepatitis Batch Management'),('hepatitis-management','hepatitis','Hepatitis Reports'),('hepatitis-reference','admin','Hepatitis Reference Management'),('hepatitis-requests','hepatitis','Hepatitis Request Management'),('hepatitis-results','hepatitis','Hepatitis Results Management'),('home','common','Dashboard'),('instruments','admin','Manage Instruments'),('move-samples','common','Move Samples'),('patients','common','Manage Patients'),('roles','admin','Manage Roles'),('tb-batches','tb','TB Batch Management'),('tb-management','tb','TB Reports'),('tb-reference','admin','TB Reference'),('tb-requests','tb','TB Request Management'),('tb-results','tb','TB Result Management'),('test-request-email-config','admin','Manage Test Request Email Config'),('test-result-email-config','admin','Manage Test Result Email Config'),('test-type','admin','Manage Test Type'),('users','admin','Manage Users'),('vl-batch','vl','Manage VL Batch'),('vl-reference','admin','VL Reference Management'),('vl-reports','vl','VL Reports'),('vl-requests','vl','VL Requests'),('vl-results','vl','VL Results');
/*!40000 ALTER TABLE `resources` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `result_import_stats`
--

DROP TABLE IF EXISTS `result_import_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `result_import_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `imported_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `no_of_results_imported` int(11) DEFAULT NULL,
  `imported_by` varchar(1000) DEFAULT NULL,
  `import_mode` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `result_import_stats`
--

LOCK TABLES `result_import_stats` WRITE;
/*!40000 ALTER TABLE `result_import_stats` DISABLE KEYS */;
/*!40000 ALTER TABLE `result_import_stats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(255) DEFAULT NULL,
  `role_code` varchar(255) DEFAULT NULL,
  `status` varchar(255) DEFAULT NULL,
  `access_type` varchar(256) DEFAULT NULL,
  `landing_page` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Admin','AD','active','testing-lab','/dashboard/index.php'),(2,'Remote Order','REMOTEORDER','active','collection-site','/dashboard/index.php'),(3,'Lab Technician','LABTECH','active','testing-lab','/dashboard/index.php'),(4,'API User','API','active','testing-lab',NULL);
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles_privileges_map`
--

DROP TABLE IF EXISTS `roles_privileges_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles_privileges_map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `privilege_id` int(11) NOT NULL,
  PRIMARY KEY (`map_id`),
  KEY `role_id` (`role_id`),
  KEY `privilege_id` (`privilege_id`),
  CONSTRAINT `roles_privileges_map_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`),
  CONSTRAINT `roles_privileges_map_ibfk_2` FOREIGN KEY (`privilege_id`) REFERENCES `privileges` (`privilege_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5498 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles_privileges_map`
--

LOCK TABLES `roles_privileges_map` WRITE;
/*!40000 ALTER TABLE `roles_privileges_map` DISABLE KEYS */;
INSERT INTO `roles_privileges_map` VALUES (5288,1,183),(5289,1,184),(5290,1,185),(5291,1,187),(5292,1,208),(5293,1,219),(5294,1,220),(5295,1,224),(5296,1,226),(5297,1,347),(5298,1,348),(5299,1,349),(5300,1,350),(5301,1,351),(5302,1,352),(5303,1,353),(5304,1,354),(5305,1,355),(5306,1,123),(5307,1,124),(5308,1,125),(5309,1,126),(5310,1,127),(5311,1,128),(5312,1,129),(5313,1,131),(5314,1,167),(5315,1,4),(5316,1,5),(5317,1,6),(5318,1,64),(5319,1,65),(5320,1,66),(5321,1,415),(5322,1,7),(5323,1,8),(5324,1,9),(5325,1,10),(5326,1,11),(5327,1,25),(5328,1,26),(5329,1,39),(5330,1,28),(5331,1,43),(5332,1,48),(5333,1,49),(5334,1,230),(5335,1,231),(5336,1,232),(5337,1,1),(5338,1,2),(5339,1,3),(5340,1,300),(5341,1,301),(5342,1,130),(5343,1,225),(5344,1,24),(5345,1,191),(5346,1,192),(5347,1,111),(5348,1,112),(5349,1,113),(5350,1,114),(5351,1,100),(5352,1,101),(5353,1,102),(5354,1,105),(5355,1,106),(5356,1,107),(5357,1,122),(5358,1,145),(5359,1,108),(5360,1,109),(5361,1,110),(5362,1,181),(5363,1,182),(5364,1,97),(5365,1,95),(5366,1,96),(5367,1,211),(5368,1,142),(5369,1,180),(5370,1,319),(5371,1,98),(5372,1,99),(5373,1,103),(5374,1,221),(5375,1,222),(5376,1,223),(5377,1,77),(5378,1,78),(5379,1,79),(5380,1,86),(5381,1,87),(5382,1,88),(5383,1,121),(5384,1,144),(5385,1,76),(5386,1,74),(5387,1,75),(5388,1,210),(5389,1,141),(5390,1,91),(5391,1,318),(5392,1,80),(5393,1,84),(5394,1,85),(5395,1,16),(5396,1,17),(5397,1,18),(5398,1,22),(5399,1,23),(5400,1,33),(5401,1,34),(5402,1,40),(5403,1,56),(5404,1,57),(5405,1,59),(5406,1,63),(5407,1,70),(5408,1,143),(5409,1,168),(5410,1,12),(5411,1,13),(5412,1,14),(5413,1,209),(5414,1,140),(5415,1,89),(5416,1,20),(5417,1,21),(5418,1,31),(5419,1,317),(5420,3,4),(5421,3,28),(5422,3,43),(5423,3,48),(5424,3,24),(5425,3,108),(5426,3,181),(5427,3,182),(5428,3,97),(5429,3,95),(5430,3,96),(5431,3,211),(5432,3,180),(5433,3,319),(5434,3,99),(5435,3,77),(5436,3,78),(5437,3,79),(5438,3,76),(5439,3,74),(5440,3,75),(5441,3,210),(5442,3,91),(5443,3,318),(5444,3,80),(5445,3,84),(5446,3,85),(5447,3,16),(5448,3,17),(5449,3,18),(5450,3,22),(5451,3,23),(5452,3,33),(5453,3,34),(5454,3,40),(5455,3,56),(5456,3,57),(5457,3,59),(5458,3,70),(5459,3,12),(5460,3,13),(5461,3,14),(5462,3,209),(5463,3,89),(5464,3,20),(5465,3,21),(5466,3,31),(5467,3,317),(5468,2,24),(5469,2,86),(5470,2,87),(5471,2,88),(5472,2,121),(5473,2,144),(5474,2,76),(5475,2,74),(5476,2,75),(5477,2,210),(5478,2,141),(5479,2,91),(5480,2,80),(5481,2,84),(5482,2,85),(5483,2,22),(5484,2,23),(5485,2,33),(5486,2,34),(5487,2,40),(5488,2,56),(5489,2,57),(5490,2,59),(5491,2,70),(5492,2,12),(5493,2,13),(5494,2,14),(5495,2,209),(5496,2,89),(5497,2,20);
/*!40000 ALTER TABLE `roles_privileges_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `s_app_menu`
--

DROP TABLE IF EXISTS `s_app_menu`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `s_app_menu` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(256) NOT NULL,
  `sub_module` varchar(256) DEFAULT NULL,
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
  `updated_datetime` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `link` (`link`,`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=182 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `s_app_menu`
--

LOCK TABLES `s_app_menu` WRITE;
/*!40000 ALTER TABLE `s_app_menu` DISABLE KEYS */;
INSERT INTO `s_app_menu` VALUES (1,'dashboard',NULL,'no','DASHBOARD','/dashboard/index.php',NULL,'always','fa-solid fa-chart-pie','no','allMenu dashboardMenu',0,1,'active',NULL),(2,'admin',NULL,'no','ADMIN',NULL,NULL,'always','fa-solid fa-shield','yes',NULL,0,2,'active',NULL),(3,'admin',NULL,'no','Access Control','',NULL,'always','fa-solid fa-user','yes','treeview access-control-menu',2,3,'active',NULL),(4,'admin',NULL,'no','Roles','/roles/roles.php','/roles/addRole.php,/roles/editRole.php','always','fa-solid fa-caret-right','no','allMenu roleMenu',3,4,'active',NULL),(5,'admin',NULL,'no','Users','/users/users.php','/users/addUser.php,/users/editUser.php','always','fa-solid fa-caret-right','no','allMenu userMenu',3,5,'active',NULL),(6,'admin',NULL,'no','Facilities','/facilities/facilities.php','/facilities/addFacility.php,/facilities/editFacility.php,/facilities/mapTestType.php','always','fa-solid fa-hospital','no','treeview facility-config-menu',2,6,'active',NULL),(7,'admin',NULL,'no','Monitoring',NULL,NULL,'always','fa-solid fa-bullseye','yes','treeview monitoring-menu',2,7,'active',NULL),(8,'admin',NULL,'no','System Configuration',NULL,NULL,'always','fa-solid fa-gears','yes','treeview system-config-menu',2,8,'active',NULL),(9,'admin','generic-tests','no','Other Lab Tests Config',NULL,NULL,'always','fa-solid fa-vial-circle-check','yes','treeview generic-reference-manage',2,9,'active',NULL),(10,'admin','vl','no','VL Config',NULL,NULL,'always','fa-solid fa-flask-vial','yes','treeview vl-reference-manage',2,10,'active',NULL),(11,'admin','eid','no','EID Config',NULL,NULL,'always','fa-solid fa-vial-circle-check','yes','treeview generic-reference-manage',2,11,'active',NULL),(12,'admin','covid19','no','Covid-19 Config',NULL,NULL,'always','fa-solid fa-virus-covid','yes','treeview covid19-reference-manage',2,12,'active',NULL),(13,'admin','hepatitis','no','Hepatitis Config',NULL,NULL,'always','fa-solid fa-square-h','yes','treeview hepatitis-reference-manage',2,13,'active',NULL),(14,'admin','tb','no','TB Config',NULL,NULL,'always','fa-solid fa-heart-pulse','yes','treeview tb-reference-manage',2,14,'active',NULL),(15,'admin',NULL,'no','User Activity Log','/admin/monitoring/activity-log.php',NULL,'always','fa-solid fa-file-lines','no','allMenu treeview activity-log-menu',7,15,'active',NULL),(16,'admin',NULL,'no','Audit Trail','/admin/monitoring/audit-trail.php',NULL,'always','fa-solid fa-clock-rotate-left','no','allMenu treeview audit-trail-menu',7,16,'active',NULL),(17,'admin',NULL,'no','API History','/admin/monitoring/api-sync-history.php',NULL,'always','fa-solid fa-circle-nodes','no','allMenu treeview api-sync-history-menu',7,17,'active',NULL),(18,'admin',NULL,'no','Source of Requests','/admin/monitoring/sources-of-requests.php',NULL,'always','fa-solid fa-circle-notch','no','allMenu treeview sources-of-requests-report-menu',7,18,'active',NULL),(19,'admin',NULL,'no','General Configuration','/global-config/editGlobalConfig.php','/global-config/editGlobalConfig.php','always','fa-solid fa-caret-right','no','allMenu globalConfigMenu',8,19,'active',NULL),(20,'admin',NULL,'no','Instruments','/instruments/instruments.php','/instruments/add-instrument.php,/instruments/edit-instrument.php','always','fa-solid fa-caret-right','no','allMenu importConfigMenu',8,20,'active',NULL),(21,'admin',NULL,'no','Geographical Divisions','/common/reference/geographical-divisions-details.php','/common/reference/add-geographical-divisions.php,/common/reference/edit-geographical-divisions.php','always','fa-solid fa-caret-right','no','allMenu geographicalMenu',8,21,'active',NULL),(22,'admin',NULL,'no','Implementation Partners','/common/reference/implementation-partners.php','/common/reference/add-implementation-partners.php','always','fa-solid fa-caret-right','no','allMenu common-reference-implementation-partners',8,22,'active',NULL),(23,'admin',NULL,'no','Funding Sources','/common/reference/funding-sources.php','/common/reference/add-funding-sources.php','always','fa-solid fa-caret-right','no','allMenu common-reference-funding-sources',8,23,'active',NULL),(24,'admin',NULL,'no','Sample Types','/generic-tests/configuration/sample-types/generic-sample-type.php','/generic-tests/configuration/sample-types/generic-add-sample-type.php,/generic-tests/configuration/sample-types/generic-edit-sample-type.php','always','fa-solid fa-caret-right','no','allMenu genericSampleTypeMenu',9,24,'active',NULL),(25,'admin',NULL,'no','Testing Reasons','/generic-tests/configuration/testing-reasons/generic-testing-reason.php','/generic-tests/configuration/testing-reasons/generic-add-testing-reason.php,/generic-tests/configuration/testing-reasons/generic-edit-testing-reason.php','always','fa-solid fa-caret-right','no','allMenu genericTestingReasonMenu',9,25,'active',NULL),(26,'admin',NULL,'no','Test Failure Reasons','/generic-tests/configuration/test-failure-reasons/generic-test-failure-reason.php','/generic-tests/configuration/test-failure-reasons/generic-add-test-failure-reason.php,/generic-tests/configuration/test-failure-reasons/generic-edit-test-failure-reason.php','always','fa-solid fa-caret-right','no','allMenu genericTestFailureReasonMenu',9,26,'active',NULL),(27,'admin',NULL,'no','Symptoms','/generic-tests/configuration/symptoms/generic-symptoms.php','/generic-tests/configuration/symptoms/generic-add-symptoms.php,/generic-tests/configuration/symptoms/generic-edit-symptoms.php','always','fa-solid fa-caret-right','no','allMenu genericSymptomsMenu',9,27,'active',NULL),(28,'admin',NULL,'no','Sample Rejection Reasons','/generic-tests/configuration/sample-rejection-reasons/generic-sample-rejection-reasons.php','/generic-tests/configuration/sample-types/generic-add-sample-type.php,/generic-tests/configuration/sample-rejection-reasons/generic-edit-rejection-reasons.php,/generic-tests/configuration/sample-rejection-reasons/generic-add-rejection-reasons.php','always','fa-solid fa-caret-right','no','allMenu genericSampleRejectionReasonsMenu',9,28,'active',NULL),(29,'admin',NULL,'no','Test Result Units','/generic-tests/configuration/test-result-units/generic-test-result-units.php','/generic-tests/configuration/test-result-units/generic-add-test-result-units.php,/generic-tests/configuration/test-result-units/generic-edit-test-result-units.php','always','fa-solid fa-caret-right','no','allMenu genericTestResultUnitsMenu',9,29,'active',NULL),(30,'admin',NULL,'no','Test Methods','/generic-tests/configuration/test-methods/generic-test-methods.php','/generic-tests/configuration/test-methods/generic-add-test-methods.php,/generic-tests/configuration/test-methods/generic-edit-test-methods.php','always','fa-solid fa-caret-right','no','allMenu genericTestMethodsMenu',9,30,'active',NULL),(31,'admin',NULL,'no','Test Categories','/generic-tests/configuration/test-categories/generic-test-categories.php','/generic-tests/configuration/test-categories/generic-add-test-categories.php,/generic-tests/configuration/test-categories/generic-edit-test-categories.php','always','fa-solid fa-caret-right','no','allMenu genericTestCategoriesMenu',9,31,'active',NULL),(32,'admin',NULL,'no','Test Type Configuration','/generic-tests/configuration/test-type.php','/generic-tests/configuration/add-test-type.php,/generic-tests/configuration/edit-test-type.php,/generic-tests/configuration/clone-test-type.php','always','fa-solid fa-caret-right','no','allMenu testTypeConfigurationMenu',9,31,'active',NULL),(33,'admin',NULL,'no','ART Regimen','/vl/reference/vl-art-code-details.php','/vl/reference/add-vl-art-code-details.php,/vl/reference/edit-vl-art-code-details.php','always','fa-solid fa-caret-right','no','allMenu vl-art-code-details',10,26,'active',NULL),(34,'admin',NULL,'no','Rejection Reasons','/vl/reference/vl-sample-rejection-reasons.php','/vl/reference/add-vl-sample-rejection-reasons.php,/vl/reference/edit-vl-sample-rejection-reasons.php','always','fa-solid fa-caret-right','no','allMenu vl-sample-rejection-reasons',10,27,'active',NULL),(35,'admin',NULL,'no','Sample Type','/vl/reference/vl-sample-type.php','/vl/reference/add-vl-sample-type.php,/vl/reference/edit-vl-sample-type.php','always','fa-solid fa-caret-right','no','allMenu vl-sample-type',10,28,'active',NULL),(36,'admin',NULL,'no','Results','/vl/reference/vl-results.php','/vl/reference/add-vl-results.php,/vl/reference/edit-vl-results.php','always','fa-solid fa-caret-right','no','allMenu vl-results',10,29,'active',NULL),(37,'admin',NULL,'no','Test Reasons','/vl/reference/vl-test-reasons.php','/vl/reference/add-vl-test-reasons.php,/vl/reference/edit-vl-test-reasons.php','always','fa-solid fa-caret-right','no','allMenu vl-test-reasons',10,30,'active',NULL),(38,'admin',NULL,'no','Test Failure Reasons','/vl/reference/vl-test-failure-reasons.php','/vl/reference/add-vl-test-failure-reason.php,/vl/reference/edit-vl-test-failure-reason.php','always','fa-solid fa-caret-right','no','allMenu vl-test-failure-reasons',10,38,'active',NULL),(39,'admin',NULL,'no','Rejection Reasons','/eid/reference/eid-sample-rejection-reasons.php','/eid/reference/add-eid-sample-rejection-reasons.php,/eid/reference/edit-eid-sample-rejection-reasons.php','always','fa-solid fa-caret-right','no','allMenu eid-sample-rejection-reasons',11,38,'active',NULL),(40,'admin',NULL,'no','Sample Type','/eid/reference/eid-sample-type.php','/eid/reference/add-eid-sample-type.php,/eid/reference/edit-eid-sample-type.php','always','fa-solid fa-caret-right','no','allMenu eid-sample-type',11,39,'active',NULL),(41,'admin',NULL,'no','Test Reasons','/eid/reference/eid-test-reasons.php','/eid/reference/add-eid-test-reasons.php,/eid/reference/edit-eid-test-reasons.php','always','fa-solid fa-caret-right','no','allMenu eid-test-reasons',11,40,'active',NULL),(42,'admin',NULL,'no','Results','/eid/reference/eid-results.php','/eid/reference/add-eid-results.php,/eid/reference/edit-eid-results.php','always','fa-solid fa-caret-right','no','allMenu eid-results',11,41,'active',NULL),(43,'admin',NULL,'no','Co-morbidities','/covid-19/reference/covid19-comorbidities.php','/covid-19/reference/add-covid19-comorbidities.php,/covid-19/reference/edit-covid19-comorbidities.php','always','fa-solid fa-caret-right','no','allMenu covid19-comorbidities',12,42,'active',NULL),(44,'admin',NULL,'no','Rejection Reasons','/covid-19/reference/eid-sample-rejection-reasons.php','/covid-19/reference/add-covid-19-sample-rejection-reasons.php,/covid-19/reference/edit-covid-19-sample-rejection-reasons.php','always','fa-solid fa-caret-right','no','allMenu covid19-sample-rejection-reasons',12,43,'active',NULL),(45,'admin',NULL,'no','Sample Type','/covid-19/reference/eid-sample-type.php','/covid-19/reference/add-covid-19-sample-type.php,/covid-19/reference/edit-covid-19-sample-type.php','always','fa-solid fa-caret-right','no','allMenu covid19-sample-type',12,44,'active',NULL),(46,'admin',NULL,'no','Symptoms','/covid-19/reference/covid19-symptoms.php','/covid-19/reference/add-covid19-symptoms.php,/covid-19/reference/edit-covid19-symptoms.php','always','fa-solid fa-caret-right','no','allMenu covid19-symptoms',12,45,'active',NULL),(47,'admin',NULL,'no','Test Reasons','/covid-19/reference/covid-19-test-reasons.php','/covid-19/reference/add-covid-19-test-reasons.php,/covid-19/reference/edit-covid-19-test-reasons.php','always','fa-solid fa-caret-right','no','allMenu covid-19-test-reasons',12,46,'active',NULL),(48,'admin',NULL,'no','Results','/covid-19/reference/covid-19-results.php','/covid-19/reference/add-covid-19-results.php,/covid-19/reference/edit-covid-19-results.php','always','fa-solid fa-caret-right','no','allMenu covid19-results',12,47,'active',NULL),(49,'admin',NULL,'no','QC Test Kits','/covid-19/reference/covid19-qc-test-kits.php','/covid-19/reference/add-covid19-qc-test-kit.php,/covid-19/reference/edit-covid19-qc-test-kit.php','always','fa-solid fa-caret-right','no','allMenu covid19-qc-test-kits',12,48,'active',NULL),(50,'admin',NULL,'no','Co-morbidities','/hepatitis/reference/hepatitis-comorbidities.php','/hepatitis/reference/add-hepatitis-comorbidities.php,/hepatitis/reference/edit-hepatitis-comorbidities.php','always','fa-solid fa-caret-right','no','allMenu hepatitis-comorbidities',13,50,'active',NULL),(51,'admin',NULL,'no','Risk Factors','/hepatitis/reference/hepatitis-risk-factors.php','/hepatitis/reference/add-hepatitis-risk-factors.php,/hepatitis/reference/edit-hepatitis-risk-factors.php','always','fa-solid fa-caret-right','no','allMenu hepatitis-risk-factors',13,51,'active',NULL),(52,'admin',NULL,'no','Rejection Reasons','/hepatitis/reference/hepatitis-sample-rejection-reasons.php','/hepatitis/reference/add-hepatitis-sample-rejection-reasons.php,/hepatitis/reference/edit-hepatitis-sample-rejection-reasons.php','always','fa-solid fa-caret-right','no','allMenu hepatitis-sample-rejection-reasons',13,52,'active',NULL),(53,'admin',NULL,'no','Sample Type','/hepatitis/reference/hepatitis-sample-type.php','/hepatitis/reference/add-hepatitis-sample-type.php,/hepatitis/reference/edit-hepatitis-sample-type.php','always','fa-solid fa-caret-right','no','allMenu hepatitis-sample-type',13,53,'active',NULL),(54,'admin',NULL,'no','Results','/hepatitis/reference/hepatitis-results.php','/hepatitis/reference/add-hepatitis-results.php,/hepatitis/reference/edit-hepatitis-results.php','always','fa-solid fa-caret-right','no','allMenu hepatitis-results',13,54,'active',NULL),(55,'admin',NULL,'no','Test Reasons','/hepatitis/reference/hepatitis-test-reasons.php','/hepatitis/reference/add-hepatitis-test-reasons.php,/hepatitis/reference/edit-hepatitis-test-reasons.php','always','fa-solid fa-caret-right','no','allMenu hepatitis-test-reasons',13,55,'active',NULL),(56,'admin',NULL,'no','Rejection Reasons','/tb/reference/tb-sample-rejection-reasons.php','/tb/reference/add-tb-sample-rejection-reason.php','always','fa-solid fa-caret-right','no','allMenu tb-sample-rejection-reasons',14,56,'active',NULL),(57,'admin',NULL,'no','Sample Type','/tb/reference/tb-sample-type.php','/tb/reference/add-tb-sample-type.php,/tb/reference/edit-tb-sample-type.php','always','fa-solid fa-caret-right','no','allMenu tb-sample-type',14,57,'active',NULL),(58,'admin',NULL,'no','Test Reasons','/tb/reference/tb-test-reasons.php','/tb/reference/add-tb-test-reasons.php,/tb/reference/edit-tb-test-reasons.php','always','fa-solid fa-caret-right','no','allMenu tb-test-reasons',14,58,'active',NULL),(59,'admin',NULL,'no','Results','/tb/reference/tb-results.php','/tb/reference/add-tb-results.php','always','fa-solid fa-caret-right','no','allMenu tb-results',14,59,'active',NULL),(60,'generic-tests',NULL,'yes','OTHER LAB TESTS',NULL,NULL,'always',NULL,'yes','header',0,8,'active',NULL),(61,'generic-tests',NULL,'no','Request Management',NULL,NULL,'always','fa-solid fa-pen-to-square','yes','treeview allMenu generic-test-request-menu',60,61,'active',NULL),(62,'generic-tests',NULL,'no','Test Result Management',NULL,NULL,'always','fa-solid fa-list-check','yes','treeview allMenu generic-test-results-menu',60,62,'active',NULL),(63,'generic-tests',NULL,'no','Management',NULL,NULL,'always','fa-solid fa-book','yes','treeview allMenu generic-test-request-menu',60,63,'active',NULL),(64,'vl',NULL,'yes','HIV VIRAL LOAD',NULL,NULL,'always',NULL,'yes','header',0,3,'active',NULL),(65,'eid',NULL,'yes','EARLY INFANT DIAGNOSIS (EID)',NULL,NULL,'always',NULL,'yes','header',0,4,'active',NULL),(66,'covid19',NULL,'yes','COVID-19',NULL,NULL,'always',NULL,'yes','header',0,5,'active',NULL),(67,'hepatitis',NULL,'yes','HEPATITIS',NULL,NULL,'always',NULL,'yes','header',0,6,'active',NULL),(68,'tb',NULL,'yes','TUBERCULOSIS',NULL,NULL,'always',NULL,'yes','header',0,7,'active',NULL),(69,'vl',NULL,'no','Request Management',NULL,NULL,'always','fa-solid fa-pen-to-square','yes','treeview request',64,69,'active',NULL),(70,'vl',NULL,'no','Test Result Management',NULL,NULL,'always','fa-solid fa-list-check','yes','treeview test',64,70,'active',NULL),(71,'vl',NULL,'no','Management',NULL,NULL,'always','fa-solid fa-book','yes','treeview program',64,71,'active',NULL),(72,'covid19',NULL,'no','Request Management',NULL,NULL,'always','fa-solid fa-pen-to-square','yes','treeview covid19Request',66,72,'active',NULL),(73,'covid19',NULL,'no','Test Result Management',NULL,NULL,'always','fa-solid fa-list-check','yes','treeview covid19Results',66,73,'active',NULL),(74,'covid19',NULL,'no','Management',NULL,NULL,'always','fa-solid fa-book','yes','treeview covid19ProgramMenu',66,74,'active',NULL),(75,'eid',NULL,'no','Request Management',NULL,NULL,'always','fa-solid fa-pen-to-square','yes','treeview eidRequest',65,75,'active',NULL),(76,'eid',NULL,'no','Test Result Management',NULL,NULL,'always','fa-solid fa-list-check','yes','treeview eidResults',65,76,'active',NULL),(77,'eid',NULL,'no','Management',NULL,NULL,'always','fa-solid fa-book','yes','treeview eidProgramMenu',65,77,'active',NULL),(78,'hepatitis',NULL,'no','Request Management',NULL,NULL,'always','fa-solid fa-pen-to-square','yes','treeview hepatitisRequest',67,78,'active',NULL),(79,'hepatitis',NULL,'no','Test Result Management',NULL,NULL,'always','fa-solid fa-list-check','yes','treeview hepatitisResults',67,79,'active',NULL),(80,'hepatitis',NULL,'no','Management',NULL,NULL,'always','fa-solid fa-book','yes','treeview hepatitisProgramMenu',67,80,'active',NULL),(81,'tb',NULL,'no','Request Management',NULL,NULL,'always','fa-solid fa-pen-to-square','yes','treeview tbRequest',68,81,'active',NULL),(82,'tb',NULL,'no','Test Result Management',NULL,NULL,'always','fa-solid fa-list-check','yes','treeview tbResults',68,82,'active',NULL),(83,'tb',NULL,'no','Management',NULL,NULL,'always','fa-solid fa-book','yes','treeview tbProgramMenu',68,83,'active',NULL),(84,'generic-tests',NULL,'no','View Test Requests','/generic-tests/requests/view-requests.php','/generic-tests/requests/edit-request.php','always','fa-solid fa-caret-right','no','allMenu genericRequestMenu',61,84,'active',NULL),(85,'generic-tests',NULL,'no','Add New Request','/generic-tests/requests/add-request.php',NULL,'always','fa-solid fa-caret-right','no','allMenu addGenericRequestMenu',61,85,'active',NULL),(86,'generic-tests',NULL,'no','Add Samples from Manifest','/generic-tests/requests/add-samples-from-manifest.php','/generic-tests/requests/edit-request.php','lis','fa-solid fa-caret-right','no','allMenu addGenericSamplesFromManifestMenu',61,86,'active',NULL),(87,'generic-tests',NULL,'no','Manage Batch','/batch/batches.php?type=generic-tests','/batch/add-batch.php?type=generic-tests,/batch/edit-batch.php?type=generic-tests,/batch/add-batch-position.php?type=generic-tests,/batch/edit-batch-position.php?type=generic-tests','always','fa-solid fa-caret-right','no','allMenu batchGenericCodeMenu',61,87,'active',NULL),(88,'generic-tests',NULL,'no','Lab Test Manifest','/specimen-referral-manifest/view-manifests.php?t=generic-tests','/specimen-referral-manifest/add-manifest.php?t=generic-tests,/specimen-referral-manifest/edit-manifest.php?t=generic-tests,/specimen-referral-manifest/move-manifest.php?t=generic-tests','sts','fa-solid fa-caret-right','no','allMenu specimenGenericReferralManifestListMenu',61,88,'active',NULL),(89,'generic-tests',NULL,'no','Enter Result Manually','/generic-tests/results/generic-test-results.php','/generic-tests/results/update-generic-test-result.php','always','fa-solid fa-caret-right','no','allMenu genericTestResultMenu',62,88,'active',NULL),(90,'generic-tests',NULL,'no','Failed/Hold Samples','/generic-tests/results/generic-failed-results.php',NULL,'always','fa-solid fa-caret-right','no','allMenu genericFailedResultMenu',62,88,'active',NULL),(91,'generic-tests',NULL,'no','Manage Results Status','/generic-tests/results/generic-result-approval.php',NULL,'always','fa-solid fa-caret-right','no','allMenu genericResultApprovalMenu',62,88,'active',NULL),(92,'generic-tests',NULL,'no','Sample Status Report','/generic-tests/program-management/generic-sample-status.php',NULL,'always','fa-solid fa-caret-right','no','allMenu genericStatusReportMenu',63,88,'active',NULL),(93,'generic-tests',NULL,'no','Export Results','/generic-tests/program-management/generic-export-data.php',NULL,'always','fa-solid fa-caret-right','no','allMenu genericExportMenu',63,89,'active',NULL),(94,'generic-tests',NULL,'no','Print Result','/generic-tests/results/generic-print-result.php',NULL,'always','fa-solid fa-caret-right','no','allMenu genericPrintResultMenu',63,90,'active',NULL),(95,'generic-tests',NULL,'no','Sample Rejection Report','/generic-tests/program-management/sample-rejection-report.php',NULL,'always','fa-solid fa-caret-right','no','allMenu genericSampleRejectionReport',63,91,'active',NULL),(96,'vl',NULL,'no','View Test Requests','/vl/requests/vl-requests.php','/vl/requests/editVlRequest.php','always','fa-solid fa-caret-right','no','allMenu vlRequestMenu',69,92,'active',NULL),(97,'vl',NULL,'no','Add New Request','/vl/requests/addVlRequest.php',NULL,'always','fa-solid fa-caret-right','no','allMenu addVlRequestMenu',69,93,'active',NULL),(98,'vl',NULL,'no','Add Samples from Manifest','/vl/requests/addSamplesFromManifest.php',NULL,'lis','fa-solid fa-caret-right','no','allMenu addSamplesFromManifestMenu',69,94,'active',NULL),(99,'vl',NULL,'no','Manage Batch','/batch/batches.php?type=vl','/batch/add-batch.php?type=vl,/batch/edit-batch.php?type=vl,/batch/edit-batch-position.php?type=vl','always','fa-solid fa-caret-right','no','allMenu batchCodeMenu',69,95,'active',NULL),(100,'vl',NULL,'no','VL Manifest','/specimen-referral-manifest/view-manifests.php?t=vl','/specimen-referral-manifest/add-manifest.php?t=vl,/specimen-referral-manifest/edit-manifest.php?t=vl,/specimen-referral-manifest/move-manifest.php?t=vl','sts','fa-solid fa-caret-right','no','allMenu specimenReferralManifestListVLMenu',69,96,'active',NULL),(101,'vl',NULL,'no','Import Result From File','/import-result/import-file.php?t=vl','/import-result/imported-results.php?t=vl,/import-result/importedStatistics.php?t=vl','always','fa-solid fa-caret-right','no','allMenu importResultMenu',70,97,'active',NULL),(102,'vl',NULL,'no','Enter Result Manually','/vl/results/vlTestResult.php','/vl/results/updateVlTestResult.php','always','fa-solid fa-caret-right','no','allMenu vlTestResultMenu',70,98,'active',NULL),(103,'vl',NULL,'no','Failed/Hold Samples','/vl/results/vl-failed-results.php',NULL,'always','fa-solid fa-caret-right','no','allMenu vlFailedResultMenu',70,99,'active',NULL),(104,'vl',NULL,'no','Manage Results Status','/vl/results/vlResultApproval.php',NULL,'always','fa-solid fa-caret-right','no','allMenu batchCodeMenu',70,100,'active',NULL),(105,'vl',NULL,'no','Sample Status Report','/vl/program-management/vl-sample-status.php','/vl/requests/editVlRequest.php','always','fa-solid fa-caret-right','no','allMenu missingResultMenu',71,100,'active',NULL),(106,'vl',NULL,'no','Control Report','/vl/program-management/vlControlReport.php',NULL,'always','fa-solid fa-caret-right','no','allMenu vlResultMenu',71,101,'active',NULL),(107,'vl',NULL,'no','Export Results','/vl/program-management/vl-export-data.php',NULL,'always','fa-solid fa-caret-right','no','allMenu vlResultMenu',71,102,'active',NULL),(108,'vl',NULL,'no','Print Result','/vl/results/vlPrintResult.php',NULL,'always','fa-solid fa-caret-right','no','allMenu vlPrintResultMenu',71,103,'active',NULL),(109,'vl',NULL,'no','Clinic Reports','/vl/program-management/highViralLoad.php',NULL,'always','fa-solid fa-caret-right','no','allMenu vlHighMenu',71,104,'active',NULL),(110,'vl',NULL,'no','VL Lab Weekly Report','/vl/program-management/vlWeeklyReport.php',NULL,'always','fa-solid fa-caret-right','no','allMenu vlWeeklyReport',71,105,'active',NULL),(111,'vl',NULL,'no','Sample Rejection Report','/vl/program-management/sampleRejectionReport.php',NULL,'always','fa-solid fa-caret-right','no','allMenu sampleRejectionReport',71,106,'active',NULL),(112,'vl',NULL,'no','Sample Monitoring Report','/vl/program-management/vlMonitoringReport.php',NULL,'always','fa-solid fa-caret-right','no','allMenu vlMonitoringReport',71,107,'active',NULL),(113,'vl',NULL,'no','VL Testing Target Report','/vl/program-management/vlTestingTargetReport.php',NULL,'always','fa-solid fa-caret-right','no','allMenu vlMonthlyThresholdReport',71,108,'active',NULL),(114,'eid',NULL,'no','View Test Requests','/eid/requests/eid-requests.php','/eid/requests/eid-edit-request.php','always','fa-solid fa-caret-right','no','allMenu eidRequestMenu',75,109,'active',NULL),(115,'eid',NULL,'no','Add New Request','/eid/requests/eid-add-request.php',NULL,'always','fa-solid fa-caret-right','no','allMenu addEidRequestMenu',75,110,'active',NULL),(116,'eid',NULL,'no','Add Samples from Manifest','/eid/requests/addSamplesFromManifest.php',NULL,'lis','fa-solid fa-caret-right','no','allMenu addSamplesFromManifestEidMenu',75,111,'active',NULL),(117,'eid',NULL,'no','Manage Batch','/batch/batches.php?type=eid','/batch/add-batch.php?type=eid,/batch/edit-batch.php?type=eid,/batch/add-batch-position.php?type=eid,/batch/edit-batch-position.php?type=eid','always','fa-solid fa-caret-right','no','allMenu eidBatchCodeMenu',75,112,'active',NULL),(118,'eid',NULL,'no','EID Manifest','/specimen-referral-manifest/view-manifests.php?t=eid','/specimen-referral-manifest/add-manifest.php?t=eid,/specimen-referral-manifest/edit-manifest.php?t=eid,/specimen-referral-manifest/move-manifest.php?t=eid','sts','fa-solid fa-caret-right','no','allMenu specimenReferralManifestListEIDMenu',75,113,'active',NULL),(119,'eid',NULL,'no','Import Result From File','/import-result/import-file.php?t=eid','/import-result/imported-results.php?t=eid,/import-result/importedStatistics.php?t=eid','always','fa-solid fa-caret-right','no','allMenu eidImportResultMenu',76,114,'active',NULL),(120,'eid',NULL,'no','Enter Result Manually','/eid/results/eid-manual-results.php','/eid/results/eid-update-result.php','always','fa-solid fa-caret-right','no','allMenu eidResultsMenu',76,115,'active',NULL),(121,'eid',NULL,'no','Failed/Hold Samples','/eid/results/eid-failed-results.php',NULL,'always','fa-solid fa-caret-right','no','allMenu eidFailedResultsMenu',76,116,'active',NULL),(122,'eid',NULL,'no','Manage Results Status','/eid/results/eid-result-status.php',NULL,'always','fa-solid fa-caret-right','no','allMenu eidResultStatus',76,117,'active',NULL),(123,'eid',NULL,'no','Sample Status Report','/eid/management/eid-sample-status.php',NULL,'always','fa-solid fa-caret-right','no','allMenu eidSampleStatus',77,118,'active',NULL),(124,'eid',NULL,'no','Export Results','/eid/management/eid-export-data.php',NULL,'always','fa-solid fa-caret-right','no','allMenu eidExportResult',77,119,'active',NULL),(125,'eid',NULL,'no','Print Result','/eid/results/eid-print-results.php',NULL,'always','fa-solid fa-caret-right','no','allMenu eidPrintResults',77,120,'active',NULL),(126,'eid',NULL,'no','Sample Rejection Report','/eid/management/eid-sample-rejection-report.php',NULL,'always','fa-solid fa-caret-right','no','allMenu eidSampleRejectionReport',77,121,'active',NULL),(127,'eid',NULL,'no','Clinic Report','/eid/management/eid-clinic-report.php',NULL,'always','fa-solid fa-caret-right','no','allMenu eidClinicReport',77,122,'active',NULL),(128,'eid',NULL,'no','EID Testing Target Report','/eid/management/eidTestingTargetReport.php',NULL,'always','fa-solid fa-caret-right','no','allMenu eidMonthlyThresholdReport',77,123,'active',NULL),(129,'covid19',NULL,'no','View Test Requests','/covid-19/requests/covid-19-requests.php','/covid-19/requests/covid-19-edit-request.php,/covid-19/requests/covid-19-bulk-import-request.php,/covid-19/requests/covid-19-quick-add.php','always','fa-solid fa-caret-right','no','allMenu covid19RequestMenu',72,124,'active',NULL),(130,'covid19',NULL,'no','Add New Request','/covid-19/requests/covid-19-add-request.php',NULL,'always','fa-solid fa-caret-right','no','allMenu addCovid19RequestMenu',72,125,'active',NULL),(131,'covid19',NULL,'no','Add Samples from Manifest','/covid-19/requests/addSamplesFromManifest.php',NULL,'lis','fa-solid fa-caret-right','no','allMenu addSamplesFromManifestCovid19Menu',72,126,'active',NULL),(132,'covid19',NULL,'no','Manage Batch','/batch/batches.php?type=covid19','/batch/add-batch.php?type=covid19,/batch/edit-batch.php?type=covid19,/batch/add-batch-position.php?type=covid19,/batch/edit-batch-position.php?type=covid19','always','fa-solid fa-caret-right','no','allMenu covid19BatchCodeMenu',72,127,'active',NULL),(133,'covid19',NULL,'no','Covid-19 Manifest','/specimen-referral-manifest/view-manifests.php?t=covid19','/specimen-referral-manifest/add-manifest.php?t=covid19,/specimen-referral-manifest/edit-manifest.php?t=covid19,/specimen-referral-manifest/move-manifest.php?t=covid19','sts','fa-solid fa-caret-right','no','allMenu specimenReferralManifestListC19Menu',72,128,'active',NULL),(134,'covid19',NULL,'no','Import Result From File','/import-result/import-file.php?t=covid19','/import-result/imported-results.php?t=covid19,/import-result/importedStatistics.php?t=covid19','always','fa-solid fa-caret-right','no','allMenu covid19ImportResultMenu',73,129,'active',NULL),(135,'covid19',NULL,'no','Enter Result Manually','/covid-19/results/covid-19-manual-results.php','/covid-19/batch/covid-19-update-result.php','always','fa-solid fa-caret-right','no','allMenu covid19ResultsMenu',73,130,'active',NULL),(136,'covid19',NULL,'no','Failed/Hold Samples','/covid-19/results/covid-19-failed-results.php',NULL,'always','fa-solid fa-caret-right','no','allMenu covid19FailedResultsMenu',73,131,'active',NULL),(137,'covid19',NULL,'no','Confirmation Manifest','/covid-19/results/covid-19-confirmation-manifest.php',NULL,'lis','fa-solid fa-caret-right','no','allMenu covid19ResultsConfirmationMenu',73,132,'active',NULL),(138,'covid19',NULL,'no','Record Confirmatory Tests','/covid-19/results/can-record-confirmatory-tests.php',NULL,'always','fa-solid fa-caret-right','no','allMenu canRecordConfirmatoryTestsCovid19Menu',73,133,'active',NULL),(139,'covid19',NULL,'no','Manage Results Status','/covid-19/results/covid-19-result-status.php',NULL,'always','fa-solid fa-caret-right','no','allMenu covid19ResultStatus',73,134,'active',NULL),(140,'covid19',NULL,'no','Covid-19 QC Data','/covid-19/results/covid-19-qc-data.php',NULL,'always','fa-solid fa-caret-right','no','allMenu covid19QcDataMenu',73,135,'active',NULL),(141,'covid19',NULL,'no','Sample Status Report','/covid-19/management/covid-19-sample-status.php',NULL,'always','fa-solid fa-caret-right','no','allMenu covid19SampleStatus',74,136,'active',NULL),(142,'covid19',NULL,'no','Export Results','/covid-19/management/covid-19-export-data.php',NULL,'always','fa-solid fa-caret-right','no','allMenu covid19ExportResult',74,137,'active',NULL),(143,'covid19',NULL,'no','Print Result','/covid-19/results/covid-19-print-results.php',NULL,'always','fa-solid fa-caret-right','no','allMenu covid19PrintResults',74,138,'active',NULL),(144,'covid19',NULL,'no','Sample Rejection Report','/covid-19/management/covid-19-sample-rejection-report.php',NULL,'always','fa-solid fa-caret-right','no','allMenu covid19SampleRejectionReport',74,139,'active',NULL),(145,'covid19',NULL,'no','Clinic Reports','/covid-19/management/covid-19-clinic-report.php',NULL,'always','fa-solid fa-caret-right','no','allMenu covid19ClinicReportMenu',74,140,'active',NULL),(146,'covid19',NULL,'no','COVID-19 Testing Target Report','/covid-19/management/covid19TestingTargetReport.php',NULL,'always','fa-solid fa-caret-right','no','allMenu covid19MonthlyThresholdReport',74,141,'active',NULL),(147,'hepatitis',NULL,'no','View Test Requests','/hepatitis/requests/hepatitis-requests.php','/hepatitis/requests/hepatitis-edit-request.php','always','fa-solid fa-caret-right','no','allMenu hepatitisRequestMenu',78,142,'active',NULL),(148,'hepatitis',NULL,'no','Add New Request','/hepatitis/requests/hepatitis-add-request.php',NULL,'always','fa-solid fa-caret-right','no','allMenu addHepatitisRequestMenu',78,143,'active',NULL),(149,'hepatitis',NULL,'no','Add Samples from Manifest','/hepatitis/requests/add-samples-from-manifest.php',NULL,'lis','fa-solid fa-caret-right','no','allMenu addSamplesFromManifestHepatitisMenu',78,144,'active',NULL),(150,'hepatitis',NULL,'no','Manage Batch','/batch/batches.php?type=hepatitis','/batch/add-batch.php?type=hepatitis,/batch/edit-batch.php?type=hepatitis,/batch/add-batch-position.php?type=hepatitis,/batch/edit-batch-position.php?type=hepatitis','always','fa-solid fa-caret-right','no','allMenu hepatitisBatchCodeMenu',78,145,'active',NULL),(151,'hepatitis',NULL,'no','Hepatitis Manifest','/specimen-referral-manifest/view-manifests.php?t=hepatitis','/specimen-referral-manifest/add-manifest.php?t=hepatitis,/specimen-referral-manifest/edit-manifest.php?t=hepatitis,/specimen-referral-manifest/move-manifest.php?t=hepatitis','sts','fa-solid fa-caret-right','no','allMenu specimenReferralManifestListHepMenu',78,146,'active',NULL),(152,'hepatitis',NULL,'no','Import Result From File','/import-result/import-file.php?t=hepatitis','/import-result/imported-results.php?t=hepatitis,/import-result/importedStatistics.php?t=hepatitis','always','fa-solid fa-caret-right','no','allMenu hepatitisImportResultMenu',79,146,'active',NULL),(153,'hepatitis',NULL,'no','Enter Result Manually','/hepatitis/results/hepatitis-manual-results.php','/hepatitis/results/hepatitis-update-result.php','always','fa-solid fa-caret-right','no','allMenu hepatitisResultsMenu',79,147,'active',NULL),(154,'hepatitis',NULL,'no','Failed/Hold Samples','/hepatitis/results/hepatitis-failed-results.php',NULL,'always','fa-solid fa-caret-right','no','allMenu hepatitisFailedResultsMenu',79,148,'active',NULL),(155,'hepatitis',NULL,'no','Manage Results Status','/hepatitis/results/hepatitis-result-status.php',NULL,'always','fa-solid fa-caret-right','no','allMenu hepatitisResultStatus',79,149,'active',NULL),(156,'hepatitis',NULL,'no','Sample Status Report','/hepatitis/management/hepatitis-sample-status.php',NULL,'always','fa-solid fa-caret-right','no','allMenu hepatitisSampleStatus',80,150,'active',NULL),(157,'hepatitis',NULL,'no','Export Results','/hepatitis/management/hepatitis-export-data.php',NULL,'always','fa-solid fa-caret-right','no','allMenu hepatitisExportResult',80,151,'active',NULL),(158,'hepatitis',NULL,'no','Print Result','/hepatitis/results/hepatitis-print-results.php',NULL,'always','fa-solid fa-caret-right','no','allMenu hepatitisPrintResults',80,152,'active',NULL),(159,'hepatitis',NULL,'no','Sample Rejection Report','/hepatitis/management/hepatitis-sample-rejection-report.php',NULL,'always','fa-solid fa-caret-right','no','allMenu hepatitisSampleRejectionReport',80,153,'active',NULL),(160,'hepatitis',NULL,'no','Clinic Reports','/hepatitis/management/hepatitis-clinic-report.php',NULL,'always','fa-solid fa-caret-right','no','allMenu hepatitisClinicReportMenu',80,154,'active',NULL),(161,'hepatitis',NULL,'no','Hepatitis Testing Target Report','/hepatitis/management/hepatitis-testing-target-report.php',NULL,'always','fa-solid fa-caret-right','no','allMenu hepatitisMonthlyThresholdReport',80,155,'active',NULL),(162,'tb',NULL,'no','View Test Requests','/tb/requests/tb-requests.php','/tb/requests/tb-edit-request.php','always','fa-solid fa-caret-right','no','allMenu tbRequestMenu',81,156,'active',NULL),(163,'tb',NULL,'no','Add New Request','/tb/requests/tb-add-request.php',NULL,'always','fa-solid fa-caret-right','no','allMenu addTbRequestMenu',81,157,'active',NULL),(164,'tb',NULL,'no','Add Samples from Manifest','/tb/requests/addSamplesFromManifest.php',NULL,'lis','fa-solid fa-caret-right','no','allMenu addSamplesFromManifestTbMenu',81,158,'active',NULL),(165,'tb',NULL,'no','Manage Batch','/batch/batches.php?type=tb','/batch/add-batch.php?type=tb,/batch/edit-batch.php?type=tb,/batch/add-batch-position.php?type=tb,/batch/edit-batch-position.php?type=tb','always','fa-solid fa-caret-right','no','allMenu tbBatchCodeMenu',81,159,'active',NULL),(166,'tb',NULL,'no','TB Manifest','/specimen-referral-manifest/view-manifests.php?t=tb','/specimen-referral-manifest/add-manifest.php?t=tb,/specimen-referral-manifest/edit-manifest.php?t=tb,/specimen-referral-manifest/move-manifest.php?t=tb','sts','fa-solid fa-caret-right','no','allMenu specimenReferralManifestListTbMenu',81,160,'active',NULL),(167,'tb',NULL,'no','Import Result From File','/import-result/import-file.php?t=tb','/import-result/imported-results.php?t=tb,/import-result/importedStatistics.php?t=tb','always','fa-solid fa-caret-right','no','allMenu tbImportResultMenu',82,161,'active',NULL),(168,'tb',NULL,'no','Enter Result Manually','/tb/results/tb-manual-results.php','/tb/results/tb-update-result.php','always','fa-solid fa-caret-right','no','allMenu tbResultsMenu',82,162,'active',NULL),(169,'tb',NULL,'no','Failed/Hold Samples','/tb/results/tb-failed-results.php',NULL,'always','fa-solid fa-caret-right','no','allMenu tbFailedResultsMenu',82,163,'active',NULL),(170,'tb',NULL,'no','Manage Results Status','/tb/results/tb-result-status.php',NULL,'always','fa-solid fa-caret-right','no','allMenu tbResultStatus',82,164,'active',NULL),(171,'tb',NULL,'no','Sample Status Report','/tb/management/tb-sample-status.php',NULL,'always','fa-solid fa-caret-right','no','allMenu tbSampleStatus',83,165,'active',NULL),(172,'tb',NULL,'no','Print Result','/tb/results/tb-print-results.php',NULL,'always','fa-solid fa-caret-right','no','allMenu tbPrintResults',83,166,'active',NULL),(173,'tb',NULL,'no','Export Results','/tb/management/tb-export-data.php',NULL,'always','fa-solid fa-caret-right','no','allMenu tbExportResult',83,167,'active',NULL),(174,'tb',NULL,'no','Sample Rejection Report','/tb/management/tb-sample-rejection-report.php',NULL,'always','fa-solid fa-caret-right','no','allMenu tbSampleRejectionReport',83,168,'active',NULL),(175,'tb',NULL,'no','Clinic Reports','/tb/management/tb-clinic-report.php',NULL,'always','fa-solid fa-caret-right','no','allMenu tbClinicReport',83,169,'active',NULL),(176,'admin',NULL,'no','Lab Sync Status','/admin/monitoring/sync-status.php',NULL,'always','fa-solid fa-traffic-light','no','allMenu treeview api-sync-status-menu',7,18,'active',NULL),(177,'admin',NULL,'no','Recommended Corrective Actions','/vl/reference/vl-recommended-corrective-actions.php',NULL,'always','fa-solid fa-caret-right','no','allMenu vl-recommended-corrective-actions',10,39,'active','2023-08-02 14:27:09'),(178,'admin',NULL,'no','Recommended Corrective Actions','/common/reference/recommended-corrective-actions.php?testType=eid',NULL,'always','fa-solid fa-caret-right','no','allMenu common-recommended-corrective-actions\r\n',11,40,'active','2023-08-26 01:03:01'),(179,'admin',NULL,'no','Recommended Corrective Actions','/common/reference/recommended-corrective-actions.php?testType=eid',NULL,'always','fa-solid fa-caret-right','no','allMenu common-recommended-corrective-actions\r\n',12,41,'active','2023-08-26 01:03:01'),(180,'generic-tests',NULL,'no','Send Result Mail','/generic-tests/mail/mail-generic-tests-results.php','/generic-tests/mail/generic-tests-result-mail-confirm.php','always','fa-solid fa-caret-right','no','allMenu genericTestResultMenu',62,88,'active','2023-10-16 17:03:43'),(181,'vl',NULL,'no','E-mail Test Result','/mail/vlResultMail.php',NULL,'always','fa-solid fa-caret-right','no','allMenu vlResultMailMenu',70,101,'active','2023-11-07 12:38:20');
/*!40000 ALTER TABLE `s_app_menu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `s_available_country_forms`
--

DROP TABLE IF EXISTS `s_available_country_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `s_available_country_forms` (
  `vlsm_country_id` int(11) NOT NULL AUTO_INCREMENT,
  `form_name` varchar(255) DEFAULT NULL,
  `short_name` varchar(256) NOT NULL,
  PRIMARY KEY (`vlsm_country_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `s_available_country_forms`
--

LOCK TABLES `s_available_country_forms` WRITE;
/*!40000 ALTER TABLE `s_available_country_forms` DISABLE KEYS */;
INSERT INTO `s_available_country_forms` VALUES (1,'South Sudan ','ssudan'),(2,'Sierra Leone','sierra-leone'),(3,'Democratic Republic of the Congo','drc'),(4,'Republic of Cameroon','cameroon'),(5,'Papua New Guinea','png'),(6,'WHO ','who'),(7,'Rwanda ','rwanda');
/*!40000 ALTER TABLE `s_available_country_forms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `s_vlsm_instance`
--

DROP TABLE IF EXISTS `s_vlsm_instance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `last_remote_reference_data_sync` datetime DEFAULT NULL,
  `last_interface_sync` datetime DEFAULT NULL,
  PRIMARY KEY (`vlsm_instance_id`),
  UNIQUE KEY `vl_instance_id` (`vlsm_instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `s_vlsm_instance`
--

LOCK TABLES `s_vlsm_instance` WRITE;
/*!40000 ALTER TABLE `s_vlsm_instance` DISABLE KEYS */;
/*!40000 ALTER TABLE `s_vlsm_instance` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `scheduled_jobs`
--

DROP TABLE IF EXISTS `scheduled_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scheduled_jobs` (
  `job_id` int(11) NOT NULL AUTO_INCREMENT,
  `job` text,
  `requested_on` datetime DEFAULT CURRENT_TIMESTAMP,
  `requested_by` varchar(256) DEFAULT NULL,
  `scheduled_on` datetime DEFAULT NULL,
  `run_once` varchar(3) DEFAULT 'no',
  `completed_on` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  PRIMARY KEY (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `scheduled_jobs`
--

LOCK TABLES `scheduled_jobs` WRITE;
/*!40000 ALTER TABLE `scheduled_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `scheduled_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sequence_counter`
--

DROP TABLE IF EXISTS `sequence_counter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sequence_counter` (
  `test_type` varchar(255) NOT NULL,
  `year` int(11) NOT NULL,
  `code_type` varchar(255) NOT NULL COMMENT 'sample_code or remote_sample_code',
  `max_sequence_number` int(11) DEFAULT NULL,
  PRIMARY KEY (`test_type`,`year`,`code_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sequence_counter`
--

LOCK TABLES `sequence_counter` WRITE;
/*!40000 ALTER TABLE `sequence_counter` DISABLE KEYS */;
/*!40000 ALTER TABLE `sequence_counter` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `support`
--

DROP TABLE IF EXISTS `support`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `support` (
  `support_id` int(11) NOT NULL AUTO_INCREMENT,
  `feedback` varchar(500) DEFAULT NULL,
  `feedback_url` varchar(255) DEFAULT NULL,
  `upload_file_name` varchar(255) DEFAULT NULL,
  `attach_screenshot` varchar(100) DEFAULT NULL,
  `screenshot_file_name` varchar(255) DEFAULT NULL,
  `status` varchar(100) DEFAULT 'active',
  PRIMARY KEY (`support_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `support`
--

LOCK TABLES `support` WRITE;
/*!40000 ALTER TABLE `support` DISABLE KEYS */;
/*!40000 ALTER TABLE `support` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_admin`
--

DROP TABLE IF EXISTS `system_admin`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_admin` (
  `system_admin_id` int(11) NOT NULL AUTO_INCREMENT,
  `system_admin_name` mediumtext,
  `system_admin_email` varchar(255) DEFAULT NULL,
  `system_admin_login` mediumtext,
  `system_admin_password` mediumtext,
  PRIMARY KEY (`system_admin_id`),
  UNIQUE KEY `user_admin_id` (`system_admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_admin`
--

LOCK TABLES `system_admin` WRITE;
/*!40000 ALTER TABLE `system_admin` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_admin` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `system_config`
--

DROP TABLE IF EXISTS `system_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_config` (
  `display_name` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_config`
--

LOCK TABLES `system_config` WRITE;
/*!40000 ALTER TABLE `system_config` DISABLE KEYS */;
INSERT INTO `system_config` VALUES ('Testing Lab ID','sc_testing_lab_id',''),('User Type','sc_user_type','vluser'),('Version','sc_version','5.2.6'),('Email Id','sup_email',NULL),('Password','sup_password',NULL);
/*!40000 ALTER TABLE `system_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tb_tests`
--

DROP TABLE IF EXISTS `tb_tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tb_tests` (
  `tb_test_id` int(11) NOT NULL AUTO_INCREMENT,
  `tb_id` int(11) DEFAULT NULL,
  `actual_no` varchar(256) DEFAULT NULL,
  `test_result` varchar(256) DEFAULT NULL,
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_sync` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`tb_test_id`),
  KEY `tb_id` (`tb_id`),
  CONSTRAINT `tb_tests_ibfk_1` FOREIGN KEY (`tb_id`) REFERENCES `form_tb` (`tb_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tb_tests`
--

LOCK TABLES `tb_tests` WRITE;
/*!40000 ALTER TABLE `tb_tests` DISABLE KEYS */;
/*!40000 ALTER TABLE `tb_tests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `temp_sample_import`
--

DROP TABLE IF EXISTS `temp_sample_import`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temp_sample_import` (
  `temp_sample_id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(255) DEFAULT NULL,
  `facility_id` int(11) DEFAULT NULL,
  `lab_name` varchar(255) DEFAULT NULL,
  `lab_id` int(11) DEFAULT NULL,
  `lab_contact_person` varchar(255) DEFAULT NULL,
  `lab_phone_number` varchar(255) DEFAULT NULL,
  `cv_number` varchar(20) DEFAULT NULL,
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
  `imported_by` varchar(255) NOT NULL,
  PRIMARY KEY (`temp_sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `temp_sample_import`
--

LOCK TABLES `temp_sample_import` WRITE;
/*!40000 ALTER TABLE `temp_sample_import` DISABLE KEYS */;
/*!40000 ALTER TABLE `temp_sample_import` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `testing_lab_health_facilities_map`
--

DROP TABLE IF EXISTS `testing_lab_health_facilities_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_lab_health_facilities_map` (
  `facility_map_id` int(11) NOT NULL AUTO_INCREMENT,
  `vl_lab_id` int(11) NOT NULL,
  `facility_id` int(11) NOT NULL,
  PRIMARY KEY (`facility_map_id`),
  KEY `vl_lab_id` (`vl_lab_id`),
  KEY `facility_id` (`facility_id`),
  CONSTRAINT `testing_lab_health_facilities_map_ibfk_1` FOREIGN KEY (`vl_lab_id`) REFERENCES `facility_details` (`facility_id`),
  CONSTRAINT `testing_lab_health_facilities_map_ibfk_2` FOREIGN KEY (`facility_id`) REFERENCES `facility_details` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `testing_lab_health_facilities_map`
--

LOCK TABLES `testing_lab_health_facilities_map` WRITE;
/*!40000 ALTER TABLE `testing_lab_health_facilities_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `testing_lab_health_facilities_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `testing_labs`
--

DROP TABLE IF EXISTS `testing_labs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `testing_labs` (
  `test_type` enum('vl','eid','covid19','hepatitis','tb','generic-tests') NOT NULL,
  `facility_id` int(11) NOT NULL,
  `attributes` json DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  `monthly_target` varchar(255) DEFAULT NULL,
  `suppressed_monthly_target` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`test_type`,`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `testing_labs`
--

LOCK TABLES `testing_labs` WRITE;
/*!40000 ALTER TABLE `testing_labs` DISABLE KEYS */;
/*!40000 ALTER TABLE `testing_labs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `track_api_requests`
--

DROP TABLE IF EXISTS `track_api_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `track_api_requests` (
  `api_track_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `data_format` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`api_track_id`),
  KEY `requested_on` (`requested_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `track_api_requests`
--

LOCK TABLES `track_api_requests` WRITE;
/*!40000 ALTER TABLE `track_api_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `track_api_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `track_qr_code_page`
--

DROP TABLE IF EXISTS `track_qr_code_page`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `track_qr_code_page` (
  `tqcp_d` int(11) NOT NULL AUTO_INCREMENT,
  `test_type` varchar(256) NOT NULL COMMENT 'vl, eid, covid19 or hepatitis',
  `test_type_id` int(11) NOT NULL,
  `sample_code` varchar(256) DEFAULT NULL,
  `browser` varchar(256) DEFAULT NULL,
  `ip_address` varchar(256) DEFAULT NULL,
  `operating_system` varchar(256) DEFAULT NULL,
  `date_time` datetime DEFAULT NULL,
  PRIMARY KEY (`tqcp_d`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `track_qr_code_page`
--

LOCK TABLES `track_qr_code_page` WRITE;
/*!40000 ALTER TABLE `track_qr_code_page` DISABLE KEYS */;
/*!40000 ALTER TABLE `track_qr_code_page` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_details`
--

DROP TABLE IF EXISTS `user_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `updated_datetime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `user_details_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_details`
--

LOCK TABLES `user_details` WRITE;
/*!40000 ALTER TABLE `user_details` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_details` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_facility_map`
--

DROP TABLE IF EXISTS `user_facility_map`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_facility_map` (
  `user_facility_map_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(255) NOT NULL,
  `facility_id` int(11) NOT NULL,
  PRIMARY KEY (`user_facility_map_id`),
  KEY `user_id` (`user_id`),
  KEY `facility_id` (`facility_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_facility_map`
--

LOCK TABLES `user_facility_map` WRITE;
/*!40000 ALTER TABLE `user_facility_map` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_facility_map` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_login_history`
--

DROP TABLE IF EXISTS `user_login_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_login_history` (
  `history_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(1000) DEFAULT NULL,
  `login_id` varchar(1000) NOT NULL,
  `login_attempted_datetime` datetime DEFAULT NULL,
  `login_status` varchar(256) DEFAULT NULL,
  `ip_address` varchar(256) DEFAULT NULL,
  `browser` varchar(1000) DEFAULT NULL,
  `operating_system` varchar(1000) DEFAULT NULL,
  PRIMARY KEY (`history_id`),
  KEY `login_status_attempted_datetime_idx` (`login_status`,`login_attempted_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_login_history`
--

LOCK TABLES `user_login_history` WRITE;
/*!40000 ALTER TABLE `user_login_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_login_history` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vl_contact_notes`
--

DROP TABLE IF EXISTS `vl_contact_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vl_contact_notes` (
  `contact_notes_id` int(11) NOT NULL AUTO_INCREMENT,
  `treament_contact_id` int(11) DEFAULT NULL,
  `contact_notes` mediumtext,
  `collected_on` date DEFAULT NULL,
  `added_on` datetime DEFAULT NULL,
  PRIMARY KEY (`contact_notes_id`),
  KEY `treament_contact_id` (`treament_contact_id`),
  CONSTRAINT `vl_contact_notes_ibfk_1` FOREIGN KEY (`treament_contact_id`) REFERENCES `form_vl` (`vl_sample_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vl_contact_notes`
--

LOCK TABLES `vl_contact_notes` WRITE;
/*!40000 ALTER TABLE `vl_contact_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `vl_contact_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vl_imported_controls`
--

DROP TABLE IF EXISTS `vl_imported_controls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vl_imported_controls` (
  `control_id` int(11) NOT NULL AUTO_INCREMENT,
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
  `imported_date_time` datetime DEFAULT NULL,
  `import_machine_file_name` varchar(256) DEFAULT NULL,
  PRIMARY KEY (`control_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vl_imported_controls`
--

LOCK TABLES `vl_imported_controls` WRITE;
/*!40000 ALTER TABLE `vl_imported_controls` DISABLE KEYS */;
/*!40000 ALTER TABLE `vl_imported_controls` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2023-12-06 13:47:43
