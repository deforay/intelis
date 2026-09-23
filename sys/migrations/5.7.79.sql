-- Migration file for version 5.7.79
-- Created on 2026-09-23 21:31:15

-- "RR (MTB detected rifampicin resistance detected)" was seeded as a TB LAM result in
-- 4.4.9, so the Xpert result lists never offered it and the result PDFs could not name
-- it. It is an Xpert result.
UPDATE `r_tb_results` SET `result_type` = 'x-pert', `updated_datetime` = CURRENT_TIMESTAMP
    WHERE `result` LIKE 'RR (%' AND `result_type` = 'lam';

-- A GeneXpert pool that reads NOT DETECTED says every sample in it is negative only if
-- the programme accepts pooled testing for release. Until a lab turns this on, a
-- negative pool is left for the lab to enter by hand.
INSERT IGNORE INTO `global_config`
(`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_datetime`, `updated_by`, `status`)
VALUES
('Record a negative GeneXpert pool on every sample in it', 'tb_interface_release_negative_pools', 'no', 'tb', 'no', CURRENT_TIMESTAMP, NULL, 'active');

UPDATE `system_config` SET `value` = '5.7.79' WHERE `system_config`.`name` = 'sc_version';
