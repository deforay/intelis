-- Migration file for version 5.8.8
-- Created on 2026-11-16 14:06:46


UPDATE `system_config` SET `value` = '5.8.8' WHERE `system_config`.`name` = 'sc_version';

-- The COVID-19 Sample Status Report pie now drills down to
-- /reports/sample-status-details.php, as the other modules have since 5.7.69.
-- Anyone who can open the report can open its drilldown, so the page is added
-- to that report privilege's shared privileges; the access check grants a
-- shared path by its leading query parameter.
--
-- Written for any existing value: NULL, an empty array, or an array that
-- already holds other paths. Re-running adds nothing twice.

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY()
 WHERE `privilege_name` = '/covid-19/management/covid-19-sample-status.php'
   AND (`shared_privileges` IS NULL OR JSON_TYPE(`shared_privileges`) <> 'ARRAY');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY_APPEND(`shared_privileges`, '$', '/reports/sample-status-details.php?testType=covid19')
 WHERE `privilege_name` = '/covid-19/management/covid-19-sample-status.php'
   AND NOT JSON_CONTAINS(`shared_privileges`, '"/reports/sample-status-details.php?testType=covid19"');
