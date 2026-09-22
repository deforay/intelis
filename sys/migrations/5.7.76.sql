-- Migration file for version 5.7.76
-- Created on 2026-09-22 18:00:00

-- Menu links that pointed at pages that do not exist, or at the wrong list.

-- VL Config > Recommended Corrective Actions opened a missing page.
UPDATE `s_app_menu`
   SET `link` = '/common/reference/recommended-corrective-actions.php?testType=vl'
 WHERE `id` = 177
   AND `link` = '/vl/reference/vl-recommended-corrective-actions.php';

-- The EID corrective-actions entry sat under Covid-19 Config. COVID-19 request
-- forms do not use corrective actions; EID forms do, so it belongs under EID Config.
UPDATE `s_app_menu`
   SET `parent_id` = 11
 WHERE `id` = 179
   AND `link` LIKE '/common/reference/recommended-corrective-actions.php?testType=eid%'
   AND `parent_id` = 12;

-- Covid-19 Config > Test Reasons and Results used file names that do not exist.
UPDATE `s_app_menu`
   SET `link` = '/covid-19/reference/covid19-test-reasons.php',
       `inner_pages` = '/covid-19/reference/add-covid19-test-reasons.php'
 WHERE `id` = 47
   AND `link` = '/covid-19/reference/covid-19-test-reasons.php';

UPDATE `s_app_menu`
   SET `link` = '/covid-19/reference/covid19-results.php',
       `inner_pages` = '/covid-19/reference/add-covid19-results.php'
 WHERE `id` = 48
   AND `link` = '/covid-19/reference/covid-19-results.php';

UPDATE `system_config` SET `value` = '5.7.76' WHERE `system_config`.`name` = 'sc_version';
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
