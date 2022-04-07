
CREATE DATABASE IF NOT EXISTS audit_vlsm;

CREATE TABLE `audit_vlsm`.`form_vl` SELECT * from `vlsm`.`form_vl` WHERE 1=0;


-- DROP TRIGGER audit_form_vl;
DELIMITER //
CREATE TRIGGER `vlsm`.`audit_form_vl` AFTER UPDATE ON `vlsm`.`form_vl`
FOR EACH ROW BEGIN  
    INSERT INTO `audit_vlsm`.`form_vl` SELECT * FROM `vlsm`.`form_vl` where `vlsm`.form_vl.`vl_sample_id` = `audit_vlsm`.NEW.vl_sample_id;
END;//
DELIMITER ;