-- Amit 26-Dec-2023 version 5.2.8
UPDATE `system_config` SET `value` = '5.2.8' WHERE `system_config`.`name` = 'sc_version';

-- Jeyabanu 26-Dec-2023
CREATE TABLE `temp_mail` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `test_type` varchar(10) DEFAULT NULL,
  `samples` varchar(256) DEFAULT NULL,
  `to_mail` varchar(255) DEFAULT NULL,
  `report_email` varchar(256) DEFAULT NULL,
  `subject` varchar(200) DEFAULT NULL,
  `text_message` varchar(255) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL,
  `status` varchar(11) DEFAULT NULL,
  `created_on` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Amit 27-Dec-2023
UPDATE form_vl
SET patient_art_code = REPLACE(patient_art_code, 'string', '')
WHERE patient_art_code like '%string';
