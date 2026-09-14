-- Migration file for version 5.7.69
-- Created on 2026-09-14 12:14:11
--
-- The EID, TB, CD4, Hepatitis and Custom Tests Sample Status Report pies now
-- drill down to /reports/sample-status-details.php, as VL has since 5.7.68.
-- Anyone who can open a module's report can open its drilldown, so the page
-- is added to that report privilege's shared privileges, one entry per test
-- type; the access check grants a shared path by its leading query parameter.
--
-- Written for any existing value: NULL, an empty array, or an array that
-- already holds other paths. Re-running adds nothing twice.

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY()
 WHERE `privilege_name` = '/eid/management/eid-sample-status.php'
   AND (`shared_privileges` IS NULL OR JSON_TYPE(`shared_privileges`) <> 'ARRAY');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY_APPEND(`shared_privileges`, '$', '/reports/sample-status-details.php?testType=eid')
 WHERE `privilege_name` = '/eid/management/eid-sample-status.php'
   AND NOT JSON_CONTAINS(`shared_privileges`, '"/reports/sample-status-details.php?testType=eid"');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY()
 WHERE `privilege_name` = '/tb/management/tb-sample-status.php'
   AND (`shared_privileges` IS NULL OR JSON_TYPE(`shared_privileges`) <> 'ARRAY');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY_APPEND(`shared_privileges`, '$', '/reports/sample-status-details.php?testType=tb')
 WHERE `privilege_name` = '/tb/management/tb-sample-status.php'
   AND NOT JSON_CONTAINS(`shared_privileges`, '"/reports/sample-status-details.php?testType=tb"');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY()
 WHERE `privilege_name` = '/cd4/management/cd4-sample-status.php'
   AND (`shared_privileges` IS NULL OR JSON_TYPE(`shared_privileges`) <> 'ARRAY');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY_APPEND(`shared_privileges`, '$', '/reports/sample-status-details.php?testType=cd4')
 WHERE `privilege_name` = '/cd4/management/cd4-sample-status.php'
   AND NOT JSON_CONTAINS(`shared_privileges`, '"/reports/sample-status-details.php?testType=cd4"');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY()
 WHERE `privilege_name` = '/hepatitis/management/hepatitis-sample-status.php'
   AND (`shared_privileges` IS NULL OR JSON_TYPE(`shared_privileges`) <> 'ARRAY');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY_APPEND(`shared_privileges`, '$', '/reports/sample-status-details.php?testType=hepatitis')
 WHERE `privilege_name` = '/hepatitis/management/hepatitis-sample-status.php'
   AND NOT JSON_CONTAINS(`shared_privileges`, '"/reports/sample-status-details.php?testType=hepatitis"');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY()
 WHERE `privilege_name` = '/generic-tests/program-management/generic-sample-status.php'
   AND (`shared_privileges` IS NULL OR JSON_TYPE(`shared_privileges`) <> 'ARRAY');

UPDATE `privileges`
   SET `shared_privileges` = JSON_ARRAY_APPEND(`shared_privileges`, '$', '/reports/sample-status-details.php?testType=generic-tests')
 WHERE `privilege_name` = '/generic-tests/program-management/generic-sample-status.php'
   AND NOT JSON_CONTAINS(`shared_privileges`, '"/reports/sample-status-details.php?testType=generic-tests"');

UPDATE `system_config` SET `value` = '5.7.69' WHERE `system_config`.`name` = 'sc_version';
