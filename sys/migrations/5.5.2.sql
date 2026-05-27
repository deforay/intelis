-- Migration file for version 5.5.2
-- Created on 2026-05-27 13:17:54
--
-- Remote command plane fixes (s_lis_remote_commands):
--   1. Widen the identifier columns from CHAR(26) to CHAR(36). command_id,
--      depends_on and nonce hold 36-char RFC 4122 ULIDs
--      (MiscUtility::generateULID -> toRfc4122). The original CHAR(26) sizing
--      assumed canonical 26-char ULIDs, so on any server with a non-strict
--      sql_mode every id was silently truncated to 26 chars on insert. The
--      truncated ids then failed the UUID-format validation in the cancel /
--      replay / details endpoints, surfacing as "Invalid commandId".
--   2. Retire dead legacy rows that still carry a truncated id — they can
--      never be cancelled or completed through the UI, so cancel them.


-- ----------------------------------------------------------------------------
-- 1. Widen the identifier columns to fit a full 36-char RFC 4122 string.
--    Idempotent: re-running MODIFY to the same type is a no-op.
-- ----------------------------------------------------------------------------
ALTER TABLE `s_lis_remote_commands`
  MODIFY COLUMN `command_id` CHAR(36) NOT NULL,
  MODIFY COLUMN `depends_on` CHAR(36) NULL,
  MODIFY COLUMN `nonce`      CHAR(36) NOT NULL;


-- ----------------------------------------------------------------------------
-- 2. Retire dead rows whose ids were truncated under the old CHAR(26) schema.
--    After the widen above, any non-terminal row whose command_id is not a
--    full 36-char id is un-actionable (cancel/replay reject it) -> mark it
--    cancelled so the sync-status UI stops showing a permanent ghost badge.
-- ----------------------------------------------------------------------------
UPDATE `s_lis_remote_commands`
SET `status` = 'cancelled',
    `completed_at` = CURRENT_TIMESTAMP,
    `last_error` = 'Auto-cancelled: legacy truncated command_id (pre-5.5.2 CHAR(26) schema)'
WHERE `status` NOT IN ('completed', 'failed', 'expired', 'cancelled')
  AND CHAR_LENGTH(`command_id`) <> 36;


UPDATE `system_config` SET `value` = '5.5.2' WHERE `system_config`.`name` = 'sc_version';

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
