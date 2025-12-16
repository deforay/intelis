-- Migration file for version 5.4.0
-- Created on 2025-11-19 08:39:49


UPDATE `system_config` SET `value` = '5.4.0' WHERE `system_config`.`name` = 'sc_version';

-- Amit 16-Dec-2025
ALTER TABLE `form_tb` CHANGE `xpert_mtb_result` `xpert_mtb_result` VARCHAR(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `sample_code_format` `sample_code_format` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `remote_sample_code_format` `remote_sample_code_format` VARCHAR(16) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `is_specimen_reordered` `is_specimen_reordered` ENUM('yes','no','') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;
ALTER TABLE `form_tb` CHANGE `is_patient_initiated_on_tb_treatment` `is_patient_initiated_on_tb_treatment` ENUM('yes','no','') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL;

ALTER TABLE `form_tb` ADD `tb_lam_result` VARCHAR(64) NULL DEFAULT NULL AFTER `xpert_mtb_result`;
