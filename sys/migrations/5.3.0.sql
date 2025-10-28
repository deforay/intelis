
-- Amit 25-Oct-2024
UPDATE `system_config` SET `value` = '5.3.0' WHERE `system_config`.`name` = 'sc_version';

--Amit 28-Oct-2024
UPDATE facility_details
SET sts_token = CONCAT('sts_', REPLACE(UUID(), '-', ''))
WHERE facility_type = 2 AND sts_token IS NULL;

-- Jeyabanu 30-Oct-2024
ALTER TABLE `package_details` ADD `manifest_change_history` JSON NULL DEFAULT NULL AFTER `number_of_samples`;
ALTER TABLE `package_details` ADD `manifest_print_history` JSON NULL DEFAULT NULL AFTER `manifest_change_history`;


-- Jeyabanu 15-Nov-2024
ALTER TABLE `form_eid` ADD `eid_number` VARCHAR(20) NULL DEFAULT NULL AFTER `lab_reception_person`;
ALTER TABLE `audit_form_eid` ADD `eid_number` VARCHAR(20) NULL DEFAULT NULL AFTER `lab_reception_person`;


-- Jeyabanu 20-Dec-2024
ALTER TABLE `form_cd4` ADD `is_patient_initiated_on_art` VARCHAR(10) NULL DEFAULT NULL AFTER `is_patient_new`;

ALTER TABLE `form_vl` ADD `last_vl_date_recency` DATE NULL DEFAULT NULL AFTER `last_vl_result_failure`, ADD `last_vl_result_recency` TEXT NULL DEFAULT NULL AFTER `last_vl_date_recency`;
ALTER TABLE `audit_form_vl` ADD `last_vl_date_recency` DATE NULL DEFAULT NULL AFTER `last_vl_result_failure`, ADD `last_vl_result_recency` TEXT NULL DEFAULT NULL AFTER `last_vl_date_recency`;


-- Jeyabanu 13-Jan-2025
ALTER TABLE `form_cd4` ADD `crag_test_results` VARCHAR(50) NULL DEFAULT NULL AFTER `cd4_result_percentage`;

-- Jeyabanu 20-Jan-2025
ALTER TABLE `form_cd4` ADD `last_cd4_crag_result` VARCHAR(32) NULL DEFAULT NULL AFTER `last_cd4_result_percentage`;

-- Jeyabanu 21-Jan-2025
ALTER TABLE `r_tb_results` CHANGE `result_id` `result_id` INT NOT NULL AUTO_INCREMENT;
INSERT INTO `r_tb_results` (`result`, `result_type`, `status`, `updated_datetime`, `data_sync`) VALUES ('No growth after 6 weeks', 'culture', 'active', CURRENT_TIMESTAMP, '0'), ('Positive for AFB', 'culture', 'active', CURRENT_TIMESTAMP, '0'), ('Contaminated', 'culture', 'active', CURRENT_TIMESTAMP, '0');
INSERT INTO `r_tb_results` (`result`, `result_type`, `status`, `updated_datetime`, `data_sync`) VALUES ('MTBC', 'identification', 'active', CURRENT_TIMESTAMP, '0'), ('NTM', 'identification', 'active', CURRENT_TIMESTAMP, '0');
INSERT INTO `r_tb_results` (`result`, `result_type`, `status`, `updated_datetime`, `data_sync`) VALUES ('Streptomycin', 'drugMGIT', 'active', CURRENT_TIMESTAMP, '0'), ('Isonaizid', 'drugMGIT', 'active', CURRENT_TIMESTAMP, '0'), ('Rifampicin', 'drugMGIT', 'active', CURRENT_TIMESTAMP, '0'), ( 'Ethambutol', 'drugMGIT', 'active', CURRENT_TIMESTAMP, '0'), ('PZA', 'drugMGIT', 'active', CURRENT_TIMESTAMP, '0');
INSERT INTO `r_tb_results` (`result`, `result_type`, `status`, `updated_datetime`, `data_sync`) VALUES ('RIF(rpoB)', 'drugLPA', 'active', CURRENT_TIMESTAMP, '0'), ('INH(katG)', 'drugLPA', 'active', CURRENT_TIMESTAMP, '0'), ( 'INH(inhA)', 'drugLPA', 'active', CURRENT_TIMESTAMP, '0'), ('FLQ(gyrA)', 'drugLPA', 'active', CURRENT_TIMESTAMP, '0'), ('FLQ(gyrB)', 'drugLPA', 'active', CURRENT_TIMESTAMP, '0'), ( 'AG/CP(rrs)', 'drugLPA', 'active', CURRENT_TIMESTAMP, '0'), ('LL Kana(eis)', 'drugLPA', 'active', CURRENT_TIMESTAMP, '0');

ALTER TABLE `form_tb` ADD `culture_result` INT NULL AFTER `xpert_mtb_result`;
ALTER TABLE `form_tb` ADD `identification_result` INT NULL AFTER `culture_result`;
ALTER TABLE `form_tb` ADD `drug_mgit_result` INT NULL AFTER `identification_result`;
ALTER TABLE `form_tb` ADD `drug_lpa_result` INT NULL AFTER `drug_mgit_result`;

ALTER TABLE `audit_form_tb` ADD `culture_result` INT NULL AFTER `xpert_mtb_result`;
ALTER TABLE `audit_form_tb` ADD `identification_result` INT NULL AFTER `culture_result`;
ALTER TABLE `audit_form_tb` ADD `drug_mgit_result` INT NULL AFTER `identification_result`;
ALTER TABLE `audit_form_tb` ADD `drug_lpa_result` INT NULL AFTER `drug_mgit_result`;


-- Jeyabanu 30-Jan-2025
ALTER TABLE `form_tb` ADD `xpert_result_date` DATE NULL DEFAULT NULL AFTER `result_date`;
ALTER TABLE `form_tb` ADD `tblam_result_date` DATE NULL DEFAULT NULL AFTER `xpert_result_date`;
ALTER TABLE `form_tb` ADD `culture_result_date` DATE NULL DEFAULT NULL AFTER `tblam_result_date`;
ALTER TABLE `form_tb` ADD `identification_result_date` DATE NULL DEFAULT NULL AFTER `culture_result_date`;
ALTER TABLE `form_tb` ADD `drug_mgit_result_date` DATE NULL DEFAULT NULL AFTER `identification_result_date`;
ALTER TABLE `form_tb` ADD `drug_lpa_result_date` DATE NULL DEFAULT NULL AFTER `drug_mgit_result_date`;

ALTER TABLE `audit_form_tb` ADD `xpert_result_date` DATE NULL DEFAULT NULL AFTER `result_date`;
ALTER TABLE `audit_form_tb` ADD `tblam_result_date` DATE NULL DEFAULT NULL AFTER `xpert_result_date`;
ALTER TABLE `audit_form_tb` ADD `culture_result_date` DATE NULL DEFAULT NULL AFTER `tblam_result_date`;
ALTER TABLE `audit_form_tb` ADD `identification_result_date` DATE NULL DEFAULT NULL AFTER `culture_result_date`;
ALTER TABLE `audit_form_tb` ADD `drug_mgit_result_date` DATE NULL DEFAULT NULL AFTER `identification_result_date`;
ALTER TABLE `audit_form_tb` ADD `drug_lpa_result_date` DATE NULL DEFAULT NULL AFTER `drug_mgit_result_date`;


-- Amit 05-Mar-2025
ALTER TABLE queue_sample_code_generation  ADD COLUMN processing_error TEXT NULL;

-- Amit 21-Mar-2025
UPDATE `s_app_menu` SET `display_text` = 'Import Results From File' WHERE `display_text` LIKE 'Import result from file';

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