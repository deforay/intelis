-- Migration file for version 5.7.64
-- Created on 2026-09-08
--
-- Renames the two clinic report pages on disk and points the privilege and the
-- menu entry at the new paths.
--
--   /vl/program-management/highViralLoad.php  ->  /vl/program-management/vl-clinic-reports.php
--   /eid/management/eid-clinic-report.php     ->  /eid/management/eid-clinic-reports.php
--
-- The old names were both wrong about what the pages are. Neither is a single
-- report: the VL page carries seven tabs (high viral load, virologic failure,
-- rejection, results not available, data quality, sample testing, patient test
-- history) and the EID page six, and "highViralLoad" names only the first tab
-- of one of them. Both pages already call themselves "Clinic Reports" in their
-- heading and breadcrumb, so the file names now match what a user is looking at.
--
-- The rows are updated in place rather than inserted and the old ones deleted:
-- a privilege_id is referenced by roles_privileges_map, so replacing the row
-- would silently revoke the page from every role that has it, and a menu row's
-- menu_id is referenced by its children's parent_id.
--
-- Each statement is keyed on the old path, so re-running the migration is a
-- no-op once it has been applied.

UPDATE `privileges`
   SET `privilege_name` = '/vl/program-management/vl-clinic-reports.php',
       `display_name`   = 'VL Clinic Reports'
 WHERE `privilege_name` = '/vl/program-management/highViralLoad.php';

UPDATE `privileges`
   SET `privilege_name` = '/eid/management/eid-clinic-reports.php'
 WHERE `privilege_name` = '/eid/management/eid-clinic-report.php';

UPDATE `s_app_menu`
   SET `link` = '/vl/program-management/vl-clinic-reports.php'
 WHERE `link` = '/vl/program-management/highViralLoad.php';

UPDATE `s_app_menu`
   SET `link`         = '/eid/management/eid-clinic-reports.php',
       `display_text` = 'Clinic Reports'
 WHERE `link` = '/eid/management/eid-clinic-report.php';

-- 5.7.49 recorded the two old paths inside a lookup used to seed the Sample Flow
-- privilege. That statement has already run everywhere it applies and is keyed
-- on nothing this migration touches, so it is left alone; no other table stores
-- either path.
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
