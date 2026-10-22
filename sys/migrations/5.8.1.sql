-- Migration file for version 5.8.1
-- Created on 2026-10-22 10:42:59

-- Show the Sample Ageing Report in the menu again. 5.7.50 hid its entries (the
-- REPORTS item and the links under VL and EID Management); the page and its
-- privilege stayed, so roles that already hold it see it again with no new grant.
UPDATE `s_app_menu`
   SET `status` = 'active',
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `link` LIKE '/reports/sample-ageing.php%';

UPDATE `system_config` SET `value` = '5.8.1' WHERE `system_config`.`name` = 'sc_version';

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
