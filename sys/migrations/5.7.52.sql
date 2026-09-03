-- Migration file for version 5.7.52
-- Created on 2026-09-03 13:34:53
--
-- Renames the Sample Flow page to the Sample Ageing Report and moves it to
-- /reports/, which is where common reports will live from here on.
--
-- The privilege row is updated in place rather than replaced, so every role
-- grant made by 5.7.49 keeps pointing at it and nobody loses access.
--
-- Every statement is written so re-running the migration cannot create duplicates.

UPDATE `privileges`
   SET `privilege_name` = '/reports/sample-ageing.php',
       `display_name` = 'Sample Ageing Report'
 WHERE `privilege_name` = '/sample-flow/sample-flow.php';

-- The three menu rows are still inactive from 5.7.50; this only corrects the
-- link and the label they will carry when they are switched back on. The
-- label is assigned before the link, because MySQL evaluates SET clauses left
-- to right and a later clause would otherwise test the already-rewritten link.
-- The top-level entry is upper case by convention, the module entries are not.
UPDATE `s_app_menu`
   SET `display_text` = CASE
           WHEN `link` = '/sample-flow/sample-flow.php' THEN 'SAMPLE AGEING REPORT'
           ELSE 'Sample Ageing Report'
       END,
       `link` = REPLACE(`link`, '/sample-flow/sample-flow.php', '/reports/sample-ageing.php'),
       `additional_class_names` = REPLACE(`additional_class_names`, 'sample-flow-menu', 'sample-ageing-menu'),
       `updated_datetime` = CURRENT_TIMESTAMP
 WHERE `link` LIKE '/sample-flow/sample-flow.php%';

UPDATE `system_config` SET `value` = '5.7.52' WHERE `system_config`.`name` = 'sc_version';
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
