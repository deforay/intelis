-- Migration file for version 5.7.77
-- Created on 2026-09-22 20:00:00

-- Recommended Corrective Actions: one menu entry per module that uses them.
-- The ids of these rows differ between installs (older migrations inserted them
-- without an id), so every statement matches on the link, not the id. 5.7.76
-- matched on ids and missed installs where the EID entry is not id 179.

-- VL Config: the entry opened a page that does not exist.
UPDATE IGNORE `s_app_menu`
   SET `link` = '/common/reference/recommended-corrective-actions.php?testType=vl'
 WHERE `link` = '/vl/reference/vl-recommended-corrective-actions.php';

-- EID Config: the EID list sat under Covid-19 Config.
UPDATE IGNORE `s_app_menu`
   SET `parent_id` = 11,
       `display_order` = (SELECT `next_order` FROM (SELECT IFNULL(MAX(`display_order`), 0) + 1 AS `next_order` FROM `s_app_menu` WHERE `parent_id` = 11) AS `eid_children`)
 WHERE `link` = '/common/reference/recommended-corrective-actions.php?testType=eid'
   AND `parent_id` = 12;
-- If both rows existed, the one left under Covid-19 Config is a duplicate.
DELETE FROM `s_app_menu`
 WHERE `link` = '/common/reference/recommended-corrective-actions.php?testType=eid'
   AND `parent_id` = 12;

-- Covid-19 Config and TB Config had no entry, though their forms use the list.
INSERT INTO `s_app_menu`
    (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`,
     `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`)
SELECT 'admin', NULL, 'no', 'Recommended Corrective Actions',
       '/common/reference/recommended-corrective-actions.php?testType=covid19', NULL, 'always', 'fa-solid fa-caret-right',
       'no', 'allMenu covid19-recommended-corrective-actions', 12,
       (SELECT IFNULL(MAX(`display_order`), 0) + 1 FROM `s_app_menu` AS `siblings` WHERE `siblings`.`parent_id` = 12),
       'active', CURRENT_TIMESTAMP
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `s_app_menu` AS `existing`
                    WHERE `existing`.`link` = '/common/reference/recommended-corrective-actions.php?testType=covid19');

INSERT INTO `s_app_menu`
    (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`,
     `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`)
SELECT 'admin', NULL, 'no', 'Recommended Corrective Actions',
       '/common/reference/recommended-corrective-actions.php?testType=tb', NULL, 'always', 'fa-solid fa-caret-right',
       'no', 'allMenu tb-recommended-corrective-actions', 14,
       (SELECT IFNULL(MAX(`display_order`), 0) + 1 FROM `s_app_menu` AS `siblings` WHERE `siblings`.`parent_id` = 14),
       'active', CURRENT_TIMESTAMP
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM `s_app_menu` AS `existing`
                    WHERE `existing`.`link` = '/common/reference/recommended-corrective-actions.php?testType=tb');

UPDATE `system_config` SET `value` = '5.7.77' WHERE `system_config`.`name` = 'sc_version';
