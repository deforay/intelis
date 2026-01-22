-- Viral Load
DROP TABLE IF EXISTS `audit_form_vl`;
CREATE TABLE `audit_form_vl` SELECT * FROM `form_vl` WHERE 1=0;

ALTER TABLE `audit_form_vl`
   MODIFY COLUMN `vl_sample_id` int(11) NOT NULL,
   ENGINE = InnoDB,
   CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
   ADD `revision` INT(6) NOT NULL AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`vl_sample_id`, `revision`);

DROP TRIGGER IF EXISTS form_vl_data__ai;
DROP TRIGGER IF EXISTS form_vl_data__au;
DROP TRIGGER IF EXISTS form_vl_data__bd;

DELIMITER $$
CREATE TRIGGER form_vl_data__ai AFTER INSERT ON `form_vl` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_vl` WHERE `vl_sample_id` = NEW.`vl_sample_id`;
    INSERT INTO `audit_form_vl` SELECT 'insert', next_rev, NOW(), d.*
    FROM `form_vl` AS d WHERE d.vl_sample_id = NEW.vl_sample_id;
END$$

CREATE TRIGGER form_vl_data__au AFTER UPDATE ON `form_vl` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_vl` WHERE `vl_sample_id` = NEW.`vl_sample_id`;
    INSERT INTO `audit_form_vl` SELECT 'update', next_rev, NOW(), d.*
    FROM `form_vl` AS d WHERE d.vl_sample_id = NEW.vl_sample_id;
END$$

CREATE TRIGGER form_vl_data__bd BEFORE DELETE ON `form_vl` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_vl` WHERE `vl_sample_id` = OLD.`vl_sample_id`;
    INSERT INTO `audit_form_vl` SELECT 'delete', next_rev, NOW(), d.*
    FROM `form_vl` AS d WHERE d.vl_sample_id = OLD.vl_sample_id;
END$$
DELIMITER ;




-- EID

DROP TABLE IF EXISTS `audit_form_eid`;
CREATE TABLE `audit_form_eid` SELECT * FROM `form_eid` WHERE 1=0;

ALTER TABLE `audit_form_eid`
   MODIFY COLUMN `eid_id` int(11) NOT NULL,
   ENGINE = InnoDB,
   CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
   ADD `revision` INT(6) NOT NULL AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`eid_id`, `revision`);

DROP TRIGGER IF EXISTS form_eid_data__ai;
DROP TRIGGER IF EXISTS form_eid_data__au;
DROP TRIGGER IF EXISTS form_eid_data__bd;

DELIMITER $$
CREATE TRIGGER form_eid_data__ai AFTER INSERT ON `form_eid` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_eid` WHERE `eid_id` = NEW.`eid_id`;
    INSERT INTO `audit_form_eid` SELECT 'insert', next_rev, NOW(), d.*
    FROM `form_eid` AS d WHERE d.eid_id = NEW.eid_id;
END$$

CREATE TRIGGER form_eid_data__au AFTER UPDATE ON `form_eid` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_eid` WHERE `eid_id` = NEW.`eid_id`;
    INSERT INTO `audit_form_eid` SELECT 'update', next_rev, NOW(), d.*
    FROM `form_eid` AS d WHERE d.eid_id = NEW.eid_id;
END$$

CREATE TRIGGER form_eid_data__bd BEFORE DELETE ON `form_eid` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_eid` WHERE `eid_id` = OLD.`eid_id`;
    INSERT INTO `audit_form_eid` SELECT 'delete', next_rev, NOW(), d.*
    FROM `form_eid` AS d WHERE d.eid_id = OLD.eid_id;
END$$
DELIMITER ;


-- Covid-19

DROP TABLE IF EXISTS `audit_form_covid19`;
CREATE TABLE `audit_form_covid19` SELECT * from `form_covid19` WHERE 1=0;

ALTER TABLE `audit_form_covid19`
   MODIFY COLUMN `covid19_id` int(11) NOT NULL,
   ENGINE = InnoDB,
   CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
   ADD `revision` INT(6) NOT NULL AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`covid19_id`,`revision`);

DROP TRIGGER IF EXISTS form_covid19_data__ai;
DROP TRIGGER IF EXISTS form_covid19_data__au;
DROP TRIGGER IF EXISTS form_covid19_data__bd;

DELIMITER $$
CREATE TRIGGER form_covid19_data__ai AFTER INSERT ON `form_covid19` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_covid19` WHERE `covid19_id` = NEW.`covid19_id`;
    INSERT INTO `audit_form_covid19` SELECT 'insert', next_rev, NOW(), d.*
    FROM `form_covid19` AS d WHERE d.covid19_id = NEW.covid19_id;
END$$

CREATE TRIGGER form_covid19_data__au AFTER UPDATE ON `form_covid19` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_covid19` WHERE `covid19_id` = NEW.`covid19_id`;
    INSERT INTO `audit_form_covid19` SELECT 'update', next_rev, NOW(), d.*
    FROM `form_covid19` AS d WHERE d.covid19_id = NEW.covid19_id;
END$$

CREATE TRIGGER form_covid19_data__bd BEFORE DELETE ON `form_covid19` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_covid19` WHERE `covid19_id` = OLD.`covid19_id`;
    INSERT INTO `audit_form_covid19` SELECT 'delete', next_rev, NOW(), d.*
    FROM `form_covid19` AS d WHERE d.covid19_id = OLD.covid19_id;
END$$
DELIMITER ;

-- Hepatitis

DROP TABLE IF EXISTS `audit_form_hepatitis`;

CREATE TABLE `audit_form_hepatitis` SELECT * from `form_hepatitis` WHERE 1=0;

ALTER TABLE `audit_form_hepatitis`
   MODIFY COLUMN `hepatitis_id` int(11) NOT NULL,
   ENGINE = InnoDB,
   CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
   ADD `revision` INT(6) NOT NULL AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`hepatitis_id`, `revision`);

DROP TRIGGER IF EXISTS form_hepatitis_data__ai;
DROP TRIGGER IF EXISTS form_hepatitis_data__au;
DROP TRIGGER IF EXISTS form_hepatitis_data__bd;

DELIMITER $$
CREATE TRIGGER form_hepatitis_data__ai AFTER INSERT ON `form_hepatitis` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_hepatitis` WHERE `hepatitis_id` = NEW.`hepatitis_id`;
    INSERT INTO `audit_form_hepatitis` SELECT 'insert', next_rev, NOW(), d.*
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = NEW.hepatitis_id;
END$$

CREATE TRIGGER form_hepatitis_data__au AFTER UPDATE ON `form_hepatitis` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_hepatitis` WHERE `hepatitis_id` = NEW.`hepatitis_id`;
    INSERT INTO `audit_form_hepatitis` SELECT 'update', next_rev, NOW(), d.*
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = NEW.hepatitis_id;
END$$

CREATE TRIGGER form_hepatitis_data__bd BEFORE DELETE ON `form_hepatitis` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_hepatitis` WHERE `hepatitis_id` = OLD.`hepatitis_id`;
    INSERT INTO `audit_form_hepatitis` SELECT 'delete', next_rev, NOW(), d.*
    FROM `form_hepatitis` AS d WHERE d.hepatitis_id = OLD.hepatitis_id;
END$$
DELIMITER ;



-- TB

DROP TABLE IF EXISTS `audit_form_tb`;

CREATE TABLE `audit_form_tb` SELECT * from `form_tb` WHERE 1=0;

ALTER TABLE `audit_form_tb`
   MODIFY COLUMN `tb_id` int(11) NOT NULL,
   ENGINE = InnoDB,
   CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
   ADD `revision` INT(6) NOT NULL AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`tb_id`, `revision`);

DROP TRIGGER IF EXISTS form_tb_data__ai;
DROP TRIGGER IF EXISTS form_tb_data__au;
DROP TRIGGER IF EXISTS form_tb_data__bd;

DELIMITER $$
CREATE TRIGGER form_tb_data__ai AFTER INSERT ON `form_tb` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_tb` WHERE `tb_id` = NEW.`tb_id`;
    INSERT INTO `audit_form_tb` SELECT 'insert', next_rev, NOW(), d.*
    FROM `form_tb` AS d WHERE d.tb_id = NEW.tb_id;
END$$

CREATE TRIGGER form_tb_data__au AFTER UPDATE ON `form_tb` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_tb` WHERE `tb_id` = NEW.`tb_id`;
    INSERT INTO `audit_form_tb` SELECT 'update', next_rev, NOW(), d.*
    FROM `form_tb` AS d WHERE d.tb_id = NEW.tb_id;
END$$

CREATE TRIGGER form_tb_data__bd BEFORE DELETE ON `form_tb` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_tb` WHERE `tb_id` = OLD.`tb_id`;
    INSERT INTO `audit_form_tb` SELECT 'delete', next_rev, NOW(), d.*
    FROM `form_tb` AS d WHERE d.tb_id = OLD.tb_id;
END$$
DELIMITER ;


-- Generic Tests

DROP TABLE IF EXISTS `audit_form_generic`;
CREATE TABLE `audit_form_generic` SELECT * from `form_generic` WHERE 1=0;

ALTER TABLE `audit_form_generic`
   MODIFY COLUMN `sample_id` int(11) NOT NULL,
   ENGINE = InnoDB,
   CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
   ADD `revision` INT(6) NOT NULL AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`sample_id`, `revision`);

DROP TRIGGER IF EXISTS form_generic_data__ai;
DROP TRIGGER IF EXISTS form_generic_data__au;
DROP TRIGGER IF EXISTS form_generic_data__bd;

DELIMITER $$
CREATE TRIGGER form_generic_data__ai AFTER INSERT ON `form_generic` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_generic` WHERE `sample_id` = NEW.`sample_id`;
    INSERT INTO `audit_form_generic` SELECT 'insert', next_rev, NOW(), d.*
    FROM `form_generic` AS d WHERE d.sample_id = NEW.sample_id;
END$$

CREATE TRIGGER form_generic_data__au AFTER UPDATE ON `form_generic` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_generic` WHERE `sample_id` = NEW.`sample_id`;
    INSERT INTO `audit_form_generic` SELECT 'update', next_rev, NOW(), d.*
    FROM `form_generic` AS d WHERE d.sample_id = NEW.sample_id;
END$$

CREATE TRIGGER form_generic_data__bd BEFORE DELETE ON `form_generic` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_generic` WHERE `sample_id` = OLD.`sample_id`;
    INSERT INTO `audit_form_generic` SELECT 'delete', next_rev, NOW(), d.*
    FROM `form_generic` AS d WHERE d.sample_id = OLD.sample_id;
END$$
DELIMITER ;



-- CD4 Tests

DROP TABLE IF EXISTS `audit_form_cd4`;
CREATE TABLE `audit_form_cd4` SELECT * from `form_cd4` WHERE 1=0;

ALTER TABLE `audit_form_cd4`
   MODIFY COLUMN `cd4_id` int(11) NOT NULL,
   ENGINE = InnoDB,
   CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci,
   ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
   ADD `revision` INT(6) NOT NULL AFTER `action`,
   ADD `dt_datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `revision`,
   ADD PRIMARY KEY (`cd4_id`, `revision`);

DROP TRIGGER IF EXISTS form_cd4_data__ai;
DROP TRIGGER IF EXISTS form_cd4_data__au;
DROP TRIGGER IF EXISTS form_cd4_data__bd;

DELIMITER $$
CREATE TRIGGER form_cd4_data__ai AFTER INSERT ON `form_cd4` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_cd4` WHERE `cd4_id` = NEW.`cd4_id`;
    INSERT INTO `audit_form_cd4` SELECT 'insert', next_rev, NOW(), d.*
    FROM `form_cd4` AS d WHERE d.cd4_id = NEW.cd4_id;
END$$

CREATE TRIGGER form_cd4_data__au AFTER UPDATE ON `form_cd4` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_cd4` WHERE `cd4_id` = NEW.`cd4_id`;
    INSERT INTO `audit_form_cd4` SELECT 'update', next_rev, NOW(), d.*
    FROM `form_cd4` AS d WHERE d.cd4_id = NEW.cd4_id;
END$$

CREATE TRIGGER form_cd4_data__bd BEFORE DELETE ON `form_cd4` FOR EACH ROW
BEGIN
    DECLARE next_rev INT;
    SELECT COALESCE(MAX(`revision`), 0) + 1 INTO next_rev
      FROM `audit_form_cd4` WHERE `cd4_id` = OLD.`cd4_id`;
    INSERT INTO `audit_form_cd4` SELECT 'delete', next_rev, NOW(), d.*
    FROM `form_cd4` AS d WHERE d.cd4_id = OLD.cd4_id;
END$$
DELIMITER ;
