-- Migration file for version 5.5.3
-- Created on 2026-05-28 09:13:21
--
-- Audit Trail v2 — step 1 (additive, non-destructive).
--
-- Adds the two tables the new audit subsystem will use. NOTHING ELSE is changed
-- in this version: the legacy audit_form_* tables, their triggers,
-- AuditArchiveService and the audit-trail view all continue to work exactly as
-- before. Subsequent steps (trigger generator wiring, archive precision fix,
-- upgrade-flow swap, and the gated cutover with run-once/prune-legacy-audit-
-- tables.php) will land separately.
--
-- Design summary (full plan kept in the assistant's persistent memory at
-- ~/.claude/.../memory/project_audit_trail_v2.md — intentionally not committed
-- as a repo doc):
--   - audit_log replaces every per-form audit_form_* columnar table with one
--     fixed-schema JSON staging table. Triggers (added in a later step) capture
--     each form_* mutation as JSON_OBJECT(...) keyed by (form_table,record_id,
--     revision). Bounded by continuous self-pruning after archive-to-files.
--   - audit_column_aliases is the read-time rename map: a rename migration
--     registers (form_table, old_name, new_name) so historical audit rows
--     (stored under the old name in row_data / archived files) display under
--     the current column name. NO stored data is ever rewritten on a rename.


-- ----------------------------------------------------------------------------
-- 1. audit_log — single fixed-schema JSON staging table for ALL form audits.
--    UNIQUE (form_table, record_id, revision) makes the trigger insert and the
--    archive idempotent, and gives the archive/prune a precise 1:1 row identity
--    so delete-after-archive is provably lossless (fixes today's dt_datetime-
--    only de-dup which can strand same-second distinct revisions).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id`           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `form_table`   VARCHAR(64)      NOT NULL,
  `record_id`    VARCHAR(64)      NOT NULL,
  `revision`     INT              NOT NULL,
  `action`       ENUM('insert','update','delete') NOT NULL,
  `dt_datetime`  DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `row_data`     JSON             NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `u_rec_rev`  (`form_table`, `record_id`, `revision`),
  KEY         `k_drain`   (`form_table`, `id`),
  KEY         `k_dt`      (`form_table`, `dt_datetime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ----------------------------------------------------------------------------
-- 2. audit_column_aliases — read-time rename resolution map.
--    Populated by future rename migrations (one INSERT per CHANGE/RENAME COLUMN
--    on a tracked form table). The view/archive reader follows old -> ... ->
--    current so renamed columns show a continuous audit timeline without ever
--    rewriting stored audit data (the bulk of which lives in compressed CSV
--    files and would be prohibitively expensive to rewrite).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `audit_column_aliases` (
  `form_table`  VARCHAR(64) NOT NULL,
  `old_name`    VARCHAR(64) NOT NULL,
  `new_name`    VARCHAR(64) NOT NULL,
  `created_at`  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`form_table`, `old_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;



INSERT IGNORE INTO `global_config`
  (`name`, `display_name`, `value`, `category`, `remote_sync_needed`, `updated_datetime`, `status`)
VALUES
  ('allow_remote_upgrade', 'Allow Remote Upgrade', 'yes', 'general', 'yes', CURRENT_TIMESTAMP, 'active');

INSERT IGNORE INTO `global_config`
  (`name`, `display_name`, `value`, `category`, `remote_sync_needed`, `updated_datetime`, `status`)
VALUES
  ('remote_upgrade_window', 'Remote Upgrade Window (HH:MM-HH:MM)', '', 'general', 'yes', CURRENT_TIMESTAMP, 'active');


UPDATE `system_config` SET `value` = '5.5.3' WHERE `system_config`.`name` = 'sc_version';

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
