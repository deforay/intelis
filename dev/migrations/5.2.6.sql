-- Amit 22-Nov-2023 version 5.2.6
UPDATE `system_config` SET `value` = '5.2.6' WHERE `system_config`.`name` = 'sc_version';

-- Jeyabanu 24-Nov-2023
ALTER TABLE `patients` CHANGE `patient_code_key` `patient_code_key` INT NULL DEFAULT NULL;
ALTER TABLE `patients` CHANGE `patient_code_prefix` `patient_code_prefix` VARCHAR(256) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT NULL;

-- Jeyabanu 27-Nov-2023
INSERT INTO `global_config` (`display_name`, `name`, `value`, `category`, `remote_sync_needed`, `updated_on`, `updated_by`, `status`) VALUES ('CSV Delimiter', 'default_csv_delimiter', ',', 'general', 'no', NULL, NULL, 'active'), ('CSV Enclosure', 'default_csv_enclosure', '"', 'general', 'no', NULL, NULL, 'active');

-- Amit 28-Nov-2023
DROP TABLE IF EXISTS `sequence_counter`;
CREATE TABLE sequence_counter (
    test_type VARCHAR(255),
    year INT,
    code_type VARCHAR(255) COMMENT 'sample_code or remote_sample_code',
    max_sequence_number INT,
    PRIMARY KEY (test_type, year, code_type)
);


INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'vl' AS test_type, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_vl
GROUP BY YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL;

INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'vl' AS test_type, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_vl
GROUP BY YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL;

INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'eid' AS test_type, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_eid
GROUP BY YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL;

INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'eid' AS test_type, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_eid
GROUP BY YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL;


INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'tb' AS test_type, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_tb
GROUP BY YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL;

INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'tb' AS test_type, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_tb
GROUP BY YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL;

INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'covid19' AS test_type, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_covid19
GROUP BY YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL;

INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'covid19' AS test_type, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_covid19
GROUP BY YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL;

INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'hepatitis' AS test_type, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_hepatitis
GROUP BY YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL;

INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT 'hepatitis' AS test_type, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_hepatitis
GROUP BY YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL;

INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT test_type, YEAR(sample_collection_date) AS year, 'sample_code' AS code_type, MAX(sample_code_key) AS max_sequence_number
FROM form_generic
GROUP BY YEAR(sample_collection_date)
HAVING MAX(sample_code_key) IS NOT NULL;

INSERT IGNORE INTO sequence_counter (test_type, year, code_type, max_sequence_number)
SELECT test_type, YEAR(sample_collection_date) AS year, 'remote_sample_code' AS code_type, MAX(remote_sample_code_key) AS max_sequence_number
FROM form_generic
GROUP BY YEAR(sample_collection_date)
HAVING MAX(remote_sample_code_key) IS NOT NULL;


