-- Viral Load
DROP TABLE IF EXISTS `audit_form_vl`;
CREATE TABLE `audit_form_vl` LIKE `form_vl`;

ALTER TABLE `audit_form_vl`
    DROP PRIMARY KEY, -- Drop the existing primary key
    MODIFY COLUMN `vl_sample_id` INT(11) NOT NULL,
    ENGINE = InnoDB,
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `audit_form_vl`
    ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
    ADD `revision` INT(6) NOT NULL AFTER `action`,
    ADD `dt_datetime` DATETIME NOT NULL AFTER `revision`,
    ADD PRIMARY KEY (`vl_sample_id`, `revision`);

DROP TRIGGER IF EXISTS form_vl_data__ai;
DROP TRIGGER IF EXISTS form_vl_data__au;
DROP TRIGGER IF EXISTS form_vl_data__bd;

CREATE TRIGGER form_vl_data__ai AFTER INSERT ON `form_vl`
FOR EACH ROW
    INSERT INTO `audit_form_vl` (`action`, `revision`, `dt_datetime`, `vl_sample_id`)
    VALUES ('insert', 1, NOW(), NEW.vl_sample_id);

CREATE TRIGGER form_vl_data__au AFTER UPDATE ON `form_vl`
FOR EACH ROW
    INSERT INTO `audit_form_vl` (`action`, `revision`, `dt_datetime`, `vl_sample_id`)
    SELECT 'update', COALESCE(MAX(`revision`), 0) + 1, NOW(), NEW.vl_sample_id
    FROM `audit_form_vl`
    WHERE `vl_sample_id` = NEW.vl_sample_id;

CREATE TRIGGER form_vl_data__bd BEFORE DELETE ON `form_vl`
FOR EACH ROW
    INSERT INTO `audit_form_vl` (`action`, `revision`, `dt_datetime`, `vl_sample_id`)
    SELECT 'delete', COALESCE(MAX(`revision`), 0) + 1, NOW(), OLD.vl_sample_id
    FROM `audit_form_vl`
    WHERE `vl_sample_id` = OLD.vl_sample_id;

-- EID
DROP TABLE IF EXISTS `audit_form_eid`;
CREATE TABLE `audit_form_eid` LIKE `form_eid`;

ALTER TABLE `audit_form_eid`
    DROP PRIMARY KEY,
    MODIFY COLUMN `eid_id` INT(11) NOT NULL,
    ENGINE = InnoDB,
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `audit_form_eid`
    ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
    ADD `revision` INT(6) NOT NULL AFTER `action`,
    ADD `dt_datetime` DATETIME NOT NULL AFTER `revision`,
    ADD PRIMARY KEY (`eid_id`, `revision`);

DROP TRIGGER IF EXISTS form_eid_data__ai;
DROP TRIGGER IF EXISTS form_eid_data__au;
DROP TRIGGER IF EXISTS form_eid_data__bd;

CREATE TRIGGER form_eid_data__ai AFTER INSERT ON `form_eid`
FOR EACH ROW
    INSERT INTO `audit_form_eid` (`action`, `revision`, `dt_datetime`, `eid_id`)
    VALUES ('insert', 1, NOW(), NEW.eid_id);

CREATE TRIGGER form_eid_data__au AFTER UPDATE ON `form_eid`
FOR EACH ROW
    INSERT INTO `audit_form_eid` (`action`, `revision`, `dt_datetime`, `eid_id`)
    SELECT 'update', COALESCE(MAX(`revision`), 0) + 1, NOW(), NEW.eid_id
    FROM `audit_form_eid`
    WHERE `eid_id` = NEW.eid_id;

CREATE TRIGGER form_eid_data__bd BEFORE DELETE ON `form_eid`
FOR EACH ROW
    INSERT INTO `audit_form_eid` (`action`, `revision`, `dt_datetime`, `eid_id`)
    SELECT 'delete', COALESCE(MAX(`revision`), 0) + 1, NOW(), OLD.eid_id
    FROM `audit_form_eid`
    WHERE `eid_id` = OLD.eid_id;

-- Covid-19
DROP TABLE IF EXISTS `audit_form_covid19`;
CREATE TABLE `audit_form_covid19` LIKE `form_covid19`;

ALTER TABLE `audit_form_covid19`
    DROP PRIMARY KEY,
    MODIFY COLUMN `covid19_id` INT(11) NOT NULL,
    ENGINE = InnoDB,
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `audit_form_covid19`
    ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
    ADD `revision` INT(6) NOT NULL AFTER `action`,
    ADD `dt_datetime` DATETIME NOT NULL AFTER `revision`,
    ADD PRIMARY KEY (`covid19_id`, `revision`);

DROP TRIGGER IF EXISTS form_covid19_data__ai;
DROP TRIGGER IF EXISTS form_covid19_data__au;
DROP TRIGGER IF EXISTS form_covid19_data__bd;

CREATE TRIGGER form_covid19_data__ai AFTER INSERT ON `form_covid19`
FOR EACH ROW
    INSERT INTO `audit_form_covid19` (`action`, `revision`, `dt_datetime`, `covid19_id`)
    VALUES ('insert', 1, NOW(), NEW.covid19_id);

CREATE TRIGGER form_covid19_data__au AFTER UPDATE ON `form_covid19`
FOR EACH ROW
    INSERT INTO `audit_form_covid19` (`action`, `revision`, `dt_datetime`, `covid19_id`)
    SELECT 'update', COALESCE(MAX(`revision`), 0) + 1, NOW(), NEW.covid19_id
    FROM `audit_form_covid19`
    WHERE `covid19_id` = NEW.covid19_id;

CREATE TRIGGER form_covid19_data__bd BEFORE DELETE ON `form_covid19`
FOR EACH ROW
    INSERT INTO `audit_form_covid19` (`action`, `revision`, `dt_datetime`, `covid19_id`)
    SELECT 'delete', COALESCE(MAX(`revision`), 0) + 1, NOW(), OLD.covid19_id
    FROM `audit_form_covid19`
    WHERE `covid19_id` = OLD.covid19_id;

-- Hepatitis
DROP TABLE IF EXISTS `audit_form_hepatitis`;
CREATE TABLE `audit_form_hepatitis` LIKE `form_hepatitis`;

ALTER TABLE `audit_form_hepatitis`
    DROP PRIMARY KEY,
    MODIFY COLUMN `hepatitis_id` INT(11) NOT NULL,
    ENGINE = InnoDB,
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `audit_form_hepatitis`
    ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
    ADD `revision` INT(6) NOT NULL AFTER `action`,
    ADD `dt_datetime` DATETIME NOT NULL AFTER `revision`,
    ADD PRIMARY KEY (`hepatitis_id`, `revision`);

DROP TRIGGER IF EXISTS form_hepatitis_data__ai;
DROP TRIGGER IF EXISTS form_hepatitis_data__au;
DROP TRIGGER IF EXISTS form_hepatitis_data__bd;

CREATE TRIGGER form_hepatitis_data__ai AFTER INSERT ON `form_hepatitis`
FOR EACH ROW
    INSERT INTO `audit_form_hepatitis` (`action`, `revision`, `dt_datetime`, `hepatitis_id`)
    VALUES ('insert', 1, NOW(), NEW.hepatitis_id);

CREATE TRIGGER form_hepatitis_data__au AFTER UPDATE ON `form_hepatitis`
FOR EACH ROW
    INSERT INTO `audit_form_hepatitis` (`action`, `revision`, `dt_datetime`, `hepatitis_id`)
    SELECT 'update', COALESCE(MAX(`revision`), 0) + 1, NOW(), NEW.hepatitis_id
    FROM `audit_form_hepatitis`
    WHERE `hepatitis_id` = NEW.hepatitis_id;

CREATE TRIGGER form_hepatitis_data__bd BEFORE DELETE ON `form_hepatitis`
FOR EACH ROW
    INSERT INTO `audit_form_hepatitis` (`action`, `revision`, `dt_datetime`, `hepatitis_id`)
    SELECT 'delete', COALESCE(MAX(`revision`), 0) + 1, NOW(), OLD.hepatitis_id
    FROM `audit_form_hepatitis`
    WHERE `hepatitis_id` = OLD.hepatitis_id;

-- TB
DROP TABLE IF EXISTS `audit_form_tb`;
CREATE TABLE `audit_form_tb` LIKE `form_tb`;

ALTER TABLE `audit_form_tb`
    DROP PRIMARY KEY,
    MODIFY COLUMN `tb_id` INT(11) NOT NULL,
    ENGINE = InnoDB,
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `audit_form_tb`
    ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
    ADD `revision` INT(6) NOT NULL AFTER `action`,
    ADD `dt_datetime` DATETIME NOT NULL AFTER `revision`,
    ADD PRIMARY KEY (`tb_id`, `revision`);

DROP TRIGGER IF EXISTS form_tb_data__ai;
DROP TRIGGER IF EXISTS form_tb_data__au;
DROP TRIGGER IF EXISTS form_tb_data__bd;

CREATE TRIGGER form_tb_data__ai AFTER INSERT ON `form_tb`
FOR EACH ROW
    INSERT INTO `audit_form_tb` (`action`, `revision`, `dt_datetime`, `tb_id`)
    VALUES ('insert', 1, NOW(), NEW.tb_id);

CREATE TRIGGER form_tb_data__au AFTER UPDATE ON `form_tb`
FOR EACH ROW
    INSERT INTO `audit_form_tb` (`action`, `revision`, `dt_datetime`, `tb_id`)
    SELECT 'update', COALESCE(MAX(`revision`), 0) + 1, NOW(), NEW.tb_id
    FROM `audit_form_tb`
    WHERE `tb_id` = NEW.tb_id;

CREATE TRIGGER form_tb_data__bd BEFORE DELETE ON `form_tb`
FOR EACH ROW
    INSERT INTO `audit_form_tb` (`action`, `revision`, `dt_datetime`, `tb_id`)
    SELECT 'delete', COALESCE(MAX(`revision`), 0) + 1, NOW(), OLD.tb_id
    FROM `audit_form_tb`
    WHERE `tb_id` = OLD.tb_id;

-- Generic Tests
DROP TABLE IF EXISTS `audit_form_generic`;
CREATE TABLE `audit_form_generic` LIKE `form_generic`;

ALTER TABLE `audit_form_generic`
    DROP PRIMARY KEY,
    MODIFY COLUMN `sample_id` INT(11) NOT NULL,
    ENGINE = InnoDB,
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `audit_form_generic`
    ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
    ADD `revision` INT(6) NOT NULL AFTER `action`,
    ADD `dt_datetime` DATETIME NOT NULL AFTER `revision`,
    ADD PRIMARY KEY (`sample_id`, `revision`);

DROP TRIGGER IF EXISTS form_generic_data__ai;
DROP TRIGGER IF EXISTS form_generic_data__au;
DROP TRIGGER IF EXISTS form_generic_data__bd;

CREATE TRIGGER form_generic_data__ai AFTER INSERT ON `form_generic`
FOR EACH ROW
    INSERT INTO `audit_form_generic` (`action`, `revision`, `dt_datetime`, `sample_id`)
    VALUES ('insert', 1, NOW(), NEW.sample_id);

CREATE TRIGGER form_generic_data__au AFTER UPDATE ON `form_generic`
FOR EACH ROW
    INSERT INTO `audit_form_generic` (`action`, `revision`, `dt_datetime`, `sample_id`)
    SELECT 'update', COALESCE(MAX(`revision`), 0) + 1, NOW(), NEW.sample_id
    FROM `audit_form_generic`
    WHERE `sample_id` = NEW.sample_id;

CREATE TRIGGER form_generic_data__bd BEFORE DELETE ON `form_generic`
FOR EACH ROW
    INSERT INTO `audit_form_generic` (`action`, `revision`, `dt_datetime`, `sample_id`)
    SELECT 'delete', COALESCE(MAX(`revision`), 0) + 1, NOW(), OLD.sample_id
    FROM `audit_form_generic`
    WHERE `sample_id` = OLD.sample_id;

-- CD4 Tests
DROP TABLE IF EXISTS `audit_form_cd4`;
CREATE TABLE `audit_form_cd4` LIKE `form_cd4`;

ALTER TABLE `audit_form_cd4`
    DROP PRIMARY KEY,
    MODIFY COLUMN `cd4_id` INT(11) NOT NULL,
    ENGINE = InnoDB,
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

ALTER TABLE `audit_form_cd4`
    ADD `action` VARCHAR(8) DEFAULT 'insert' FIRST,
    ADD `revision` INT(6) NOT NULL AFTER `action`,
    ADD `dt_datetime` DATETIME NOT NULL AFTER `revision`,
    ADD PRIMARY KEY (`cd4_id`, `revision`);

DROP TRIGGER IF EXISTS form_cd4_data__ai;
DROP TRIGGER IF EXISTS form_cd4_data__au;
DROP TRIGGER IF EXISTS form_cd4_data__bd;

CREATE TRIGGER form_cd4_data__ai AFTER INSERT ON `form_cd4`
FOR EACH ROW
    INSERT INTO `audit_form_cd4` (`action`, `revision`, `dt_datetime`, `cd4_id`)
    VALUES ('insert', 1, NOW(), NEW.cd4_id);

CREATE TRIGGER form_cd4_data__au AFTER UPDATE ON `form_cd4`
FOR EACH ROW
    INSERT INTO `audit_form_cd4` (`action`, `revision`, `dt_datetime`, `cd4_id`)
    SELECT 'update', COALESCE(MAX(`revision`), 0) + 1, NOW(), NEW.cd4_id
    FROM `audit_form_cd4`
    WHERE `cd4_id` = NEW.cd4_id;

CREATE TRIGGER form_cd4_data__bd BEFORE DELETE ON `form_cd4`
FOR EACH ROW
    INSERT INTO `audit_form_cd4` (`action`, `revision`, `dt_datetime`, `cd4_id`)
    SELECT 'delete', COALESCE(MAX(`revision`), 0) + 1, NOW(), OLD.cd4_id
    FROM `audit_form_cd4`
    WHERE `cd4_id` = OLD.cd4_id;
