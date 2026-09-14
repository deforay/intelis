-- Migration file for version 5.7.68
-- Created on 2026-09-14 10:41:48
--
-- The VL Sample Status Report's pie now drills down to
-- /reports/sample-status-details.php, which lists the samples in one status
-- with columns chosen for that status. Anyone who can open the report can open
-- the drilldown, so the page is added to that privilege's shared privileges
-- instead of getting a row of its own that every role would need granting.
--
-- The access check grants a shared path by its leading query parameter, so
-- one entry per test type covers every status and filter the link carries.
--
-- Written for any existing value: NULL, an empty array, or an array that
-- already holds other paths. Re-running adds nothing twice.

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY()
 WHERE `privilege_name` = '/vl/program-management/vl-sample-status.php'
   AND (`shared_privileges` IS NULL OR JSON_TYPE(`shared_privileges`) <> 'ARRAY');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY_APPEND(`shared_privileges`, '$', '/reports/sample-status-details.php?testType=vl')
 WHERE `privilege_name` = '/vl/program-management/vl-sample-status.php'
   AND NOT JSON_CONTAINS(`shared_privileges`, '"/reports/sample-status-details.php?testType=vl"');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY_APPEND(`shared_privileges`, '$', '/reports/sample-status-details.php?testType=recency')
 WHERE `privilege_name` = '/vl/program-management/vl-sample-status.php'
   AND NOT JSON_CONTAINS(`shared_privileges`, '"/reports/sample-status-details.php?testType=recency"');

UPDATE `system_config` SET `value` = '5.7.68' WHERE `system_config`.`name` = 'sc_version';
