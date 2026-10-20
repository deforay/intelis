-- Migration file for version 5.7.90
-- Created on 2026-10-20 15:05:32

-- GeneXpert sputum pooling. A TB batch can put its samples into pools of up to four,
-- and each pool goes on one cartridge whose sample ID is the pool code. pool_code is
-- the pool a sample was put in. pool_result is what that pool read: 'negative', or
-- 'retest' when the pool was positive, trace, invalid or an error, and every sample
-- in it is to be tested on its own.
ALTER TABLE `form_tb` ADD COLUMN `pool_code` VARCHAR(64) NULL DEFAULT NULL AFTER `lab_assigned_code`;
ALTER TABLE `form_tb` ADD COLUMN `pool_result` VARCHAR(16) NULL DEFAULT NULL AFTER `pool_code`;
ALTER TABLE `form_tb` ADD INDEX `idx_pool_code` (`pool_code`);

-- Whether a negative pool is recorded on its samples is the programme's decision, so
-- the STS sets it for every lab. The value is left as it is: updated_datetime is not
-- touched, so an STS sends the setting only once its administrator changes it.
UPDATE `global_config` SET `remote_sync_needed` = 'yes'
    WHERE `name` = 'tb_interface_release_negative_pools';

UPDATE `system_config` SET `value` = '5.7.90' WHERE `system_config`.`name` = 'sc_version';
