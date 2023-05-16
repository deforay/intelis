-- Viral Load

CREATE TABLE `audit_form_vl` SELECT * from `form_vl` WHERE 1=0;

ALTER TABLE `audit_form_vl` 
   MODIFY COLUMN `vl_sample_id` int(11) NOT NULL, 
   ENGINE = MyISAM, 
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST, 
   ADD `revision` INT(6) NOT NULL AUTO_INCREMENT AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`vl_sample_id`, `revision`);

DROP TRIGGER IF EXISTS form_vl_data__ai;
DROP TRIGGER IF EXISTS form_vl_data__au;
DROP TRIGGER IF EXISTS form_vl_data__bd;

CREATE TRIGGER form_vl_data__ai AFTER INSERT ON `form_vl` FOR EACH ROW
    INSERT INTO `audit_form_vl` SELECT 'insert', NULL, NOW(), d.* 
    FROM `form_vl` AS d WHERE d.vl_sample_id = NEW.vl_sample_id;

CREATE TRIGGER form_vl_data__au AFTER UPDATE ON `form_vl` FOR EACH ROW
    INSERT INTO `audit_form_vl` SELECT 'update', NULL, NOW(), d.*
    FROM `form_vl` AS d WHERE d.vl_sample_id = NEW.vl_sample_id;

CREATE TRIGGER form_vl_data__bd BEFORE DELETE ON `form_vl` FOR EACH ROW
    INSERT INTO `audit_form_vl` SELECT 'delete', NULL, NOW(), d.* 
    FROM `form_vl` AS d WHERE d.vl_sample_id = OLD.vl_sample_id;




-- EID

CREATE TABLE `audit_form_eid` SELECT * from `form_eid` WHERE 1=0;

ALTER TABLE `audit_form_eid` 
   MODIFY COLUMN `eid_id` int(11) NOT NULL, 
   ENGINE = MyISAM, 
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST, 
   ADD `revision` INT(6) NOT NULL AUTO_INCREMENT AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`eid_id`, `revision`);

DROP TRIGGER IF EXISTS form_eid_data__ai;
DROP TRIGGER IF EXISTS form_eid_data__au;
DROP TRIGGER IF EXISTS form_eid_data__bd;

CREATE TRIGGER form_eid_data__ai AFTER INSERT ON `form_eid` FOR EACH ROW
    INSERT INTO `audit_form_eid` SELECT 'insert', NULL, NOW(), d.* 
    FROM `form_eid` AS d WHERE d.eid_id = NEW.eid_id;

CREATE TRIGGER form_eid_data__au AFTER UPDATE ON `form_eid` FOR EACH ROW
    INSERT INTO `audit_form_eid` SELECT 'update', NULL, NOW(), d.*
    FROM `form_eid` AS d WHERE d.eid_id = NEW.eid_id;

CREATE TRIGGER form_eid_data__bd BEFORE DELETE ON `form_eid` FOR EACH ROW
    INSERT INTO `audit_form_eid` SELECT 'delete', NULL, NOW(), d.* 
    FROM `form_eid` AS d WHERE d.eid_id = OLD.eid_id;


-- Covid-19


CREATE TABLE `audit_form_covid19` SELECT * from `form_covid19` WHERE 1=0;

ALTER TABLE `audit_form_covid19` 
   MODIFY COLUMN `covid19_id` int(11) NOT NULL, 
   ENGINE = MyISAM, 
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST, 
   ADD `revision` INT(6) NOT NULL AUTO_INCREMENT AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`covid19_id`, `revision`);

DROP TRIGGER IF EXISTS form_covid19_data__ai;
DROP TRIGGER IF EXISTS form_covid19_data__au;
DROP TRIGGER IF EXISTS form_covid19_data__bd;

CREATE TRIGGER form_covid19_data__ai AFTER INSERT ON `form_covid19` FOR EACH ROW
    INSERT INTO `audit_form_covid19` SELECT 'insert', NULL, NOW(), d.* 
    FROM `form_covid19` AS d WHERE d.covid19_id = NEW.covid19_id;

CREATE TRIGGER form_covid19_data__au AFTER UPDATE ON `form_covid19` FOR EACH ROW
    INSERT INTO `audit_form_covid19` SELECT 'update', NULL, NOW(), d.*
    FROM `form_covid19` AS d WHERE d.covid19_id = NEW.covid19_id;

CREATE TRIGGER form_covid19_data__bd BEFORE DELETE ON `form_covid19` FOR EACH ROW
    INSERT INTO `audit_form_covid19` SELECT 'delete', NULL, NOW(), d.* 
    FROM `form_covid19` AS d WHERE d.covid19_id = OLD.covid19_id;

-- Hepatitis


CREATE TABLE `audit_form_hepatitis` SELECT * from `form_hepatitis` WHERE 1=0;

ALTER TABLE `audit_form_hepatitis` 
   MODIFY COLUMN `hepatitis_id` int(11) NOT NULL, 
   ENGINE = MyISAM, 
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST, 
   ADD `revision` INT(6) NOT NULL AUTO_INCREMENT AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`hepatitis_id`, `revision`);

DROP TRIGGER IF EXISTS form_hepatitis_data__ai;
DROP TRIGGER IF EXISTS form_hepatitis_data__au;
DROP TRIGGER IF EXISTS form_hepatitis_data__bd;

CREATE TRIGGER form_hepatitis_data__ai AFTER INSERT ON `form_hepatitis` FOR EACH ROW
    INSERT INTO `audit_form_hepatitis` SELECT 'insert', NULL, NOW(), d.* 
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = NEW.hepatitis_id;

CREATE TRIGGER form_hepatitis_data__au AFTER UPDATE ON `form_hepatitis` FOR EACH ROW
    INSERT INTO `audit_form_hepatitis` SELECT 'update', NULL, NOW(), d.*
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = NEW.hepatitis_id;

CREATE TRIGGER form_hepatitis_data__bd BEFORE DELETE ON `form_hepatitis` FOR EACH ROW
    INSERT INTO `audit_form_hepatitis` SELECT 'delete', NULL, NOW(), d.* 
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = OLD.hepatitis_id;



-- TB


CREATE TABLE `audit_form_tb` SELECT * from `form_tb` WHERE 1=0;

ALTER TABLE `audit_form_tb` 
   MODIFY COLUMN `tb_id` int(11) NOT NULL, 
   ENGINE = MyISAM, 
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST, 
   ADD `revision` INT(6) NOT NULL AUTO_INCREMENT AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`tb_id`, `revision`);

DROP TRIGGER IF EXISTS form_tb_data__ai;
DROP TRIGGER IF EXISTS form_tb_data__au;
DROP TRIGGER IF EXISTS form_tb_data__bd;

CREATE TRIGGER form_tb_data__ai AFTER INSERT ON `form_tb` FOR EACH ROW
    INSERT INTO `audit_form_tb` SELECT 'insert', NULL, NOW(), d.* 
    FROM `form_tb` AS d WHERE d.tb_id = NEW.tb_id;

CREATE TRIGGER form_tb_data__au AFTER UPDATE ON `form_tb` FOR EACH ROW
    INSERT INTO `audit_form_tb` SELECT 'update', NULL, NOW(), d.*
    FROM `form_tb` AS d WHERE d.tb_id = NEW.tb_id;

CREATE TRIGGER form_tb_data__bd BEFORE DELETE ON `form_tb` FOR EACH ROW
    INSERT INTO `audit_form_tb` SELECT 'delete', NULL, NOW(), d.* 
    FROM `form_tb` AS d WHERE d.tb_id = OLD.tb_id;


-- Generic Tests

CREATE TABLE `audit_form_generic` SELECT * from `form_generic` WHERE 1=0;

ALTER TABLE `audit_form_generic` 
   MODIFY COLUMN `sample_id` int(11) NOT NULL, 
   ENGINE = MyISAM, 
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST, 
   ADD `revision` INT(6) NOT NULL AUTO_INCREMENT AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`sample_id`, `revision`);

DROP TRIGGER IF EXISTS form_generic_data__ai;
DROP TRIGGER IF EXISTS form_generic_data__au;
DROP TRIGGER IF EXISTS form_generic_data__bd;

CREATE TRIGGER form_generic_data__ai AFTER INSERT ON `form_generic` FOR EACH ROW
    INSERT INTO `audit_form_generic` SELECT 'insert', NULL, NOW(), d.* 
    FROM `form_generic` AS d WHERE d.sample_id = NEW.sample_id;

CREATE TRIGGER form_generic_data__au AFTER UPDATE ON `form_generic` FOR EACH ROW
    INSERT INTO `audit_form_generic` SELECT 'update', NULL, NOW(), d.*
    FROM `form_generic` AS d WHERE d.sample_id = NEW.sample_id;

CREATE TRIGGER form_generic_data__bd BEFORE DELETE ON `form_generic` FOR EACH ROW
    INSERT INTO `audit_form_generic` SELECT 'delete', NULL, NOW(), d.* 
    FROM `form_generic` AS d WHERE d.sample_id = OLD.sample_id;

