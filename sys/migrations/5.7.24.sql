-- Migration file for version 5.7.24
-- Created on 2026-08-19 00:09:45


UPDATE `system_config` SET `value` = '5.7.24' WHERE `system_config`.`name` = 'sc_version';



-- What a lab's own rejection reason id means on this STS.
--
-- rejection_reason_id is a per-install auto-increment, and reference data only
-- ever flowed STS -> LIS, so a reason a lab creates from the "Other" box on a
-- request or result form gets an id that exists nowhere else. Those ids arrive
-- here on the samples and point at nothing: on one country's data, 1,553 VL rows
-- across 11 labs, 91% of the rejections in a recent nine-month window.
--
-- The pair (lab_id, source_reason_id) is the only thing that can resolve them,
-- because two labs' id 26 are different reasons. A column on the reason table
-- cannot carry it: once two labs that typed the same wording are merged onto one
-- canonical row, that row would need to hold both labs' pairs.
--
-- source_reason_name is kept even though the canonical row has a name of its own.
-- It is the evidence for how a mapping was decided, and it is what someone
-- reviewing a near-duplicate ("Tube casse" vs "Tube cassé") needs to see.
CREATE TABLE IF NOT EXISTS `s_lab_rejection_reason_map` (
    `map_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `test_type` VARCHAR(64) NOT NULL,
    `lab_id` INT NOT NULL,
    `source_reason_id` INT NOT NULL,
    `rejection_reason_id` INT NOT NULL,
    `source_reason_name` VARCHAR(255) DEFAULT NULL,
    `created_datetime` DATETIME DEFAULT NULL,
    `updated_datetime` DATETIME DEFAULT NULL,
    PRIMARY KEY (`map_id`),
    UNIQUE KEY `uniq_lab_source_reason` (`test_type`, `lab_id`, `source_reason_id`),
    KEY `idx_canonical` (`test_type`, `rejection_reason_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Which lab contributed a reason, so the national list stays reviewable: a row
-- that arrived from one lab's "Other" box is not the same thing as one somebody
-- agreed on centrally. NULL means centrally authored, which is every row that
-- exists today.
ALTER TABLE `r_vl_sample_rejection_reasons` ADD COLUMN `contributed_by_lab_id` INT DEFAULT NULL;
ALTER TABLE `r_eid_sample_rejection_reasons` ADD COLUMN `contributed_by_lab_id` INT DEFAULT NULL;
ALTER TABLE `r_covid19_sample_rejection_reasons` ADD COLUMN `contributed_by_lab_id` INT DEFAULT NULL;
ALTER TABLE `r_hepatitis_sample_rejection_reasons` ADD COLUMN `contributed_by_lab_id` INT DEFAULT NULL;
ALTER TABLE `r_tb_sample_rejection_reasons` ADD COLUMN `contributed_by_lab_id` INT DEFAULT NULL;
ALTER TABLE `r_cd4_sample_rejection_reasons` ADD COLUMN `contributed_by_lab_id` INT DEFAULT NULL;
ALTER TABLE `r_generic_sample_rejection_reasons` ADD COLUMN `contributed_by_lab_id` INT DEFAULT NULL;
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
