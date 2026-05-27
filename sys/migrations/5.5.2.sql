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


-- ----------------------------------------------------------------------------
-- 3. Enroll instances into the remote command plane by default.
--    The courier (app/tasks/remote/pending-commands.php) is gated behind
--    global_config.remote_commands_enabled, which was never seeded — so it read
--    as off on every lab and the STS "Queue" actions stayed disabled fleet-wide
--    (no capability report / command-plane poll was ever recorded). Seed it so
--    every instance enrolls on its next upgrade without per-lab SQL.
--
--    INSERT IGNORE: only creates the row when absent (PRIMARY KEY is `name`), so
--    a lab that has deliberately set 'no' keeps its value and re-runs never
--    override an operator's choice.
--
--    Scope: this enables the command plane + the safe non-root commands only.
--    Root / upgrade commands stay separately gated behind allow_remote_upgrade
--    (also default off), which this migration deliberately does NOT touch.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `global_config`
  (`name`, `display_name`, `value`, `category`, `remote_sync_needed`, `updated_datetime`, `status`)
VALUES
  ('remote_commands_enabled', 'Remote Commands Enabled', 'yes', 'general', 'yes', CURRENT_TIMESTAMP, 'active');


-- ----------------------------------------------------------------------------
-- 4. Sample Referral Network report (Admin -> Monitoring).
--    A Leaflet/OpenStreetMap map of which facility refers samples to which
--    testing lab, plus a per-lab / per-test-type breakdown table. Wire up the
--    privilege, role grants and sidebar menu item. All statements are
--    idempotent so re-running the migration cannot create duplicates.
--
--    Facility coordinates are populated separately (and country-agnostically)
--    by `php bin/geocode-facilities.php`, which uses OpenStreetMap Nominatim
--    with a district/province centroid + sunflower-spread fallback.
-- ----------------------------------------------------------------------------
INSERT IGNORE INTO `privileges` (`resource_id`, `privilege_name`, `display_name`, `show_mode`)
VALUES ('monitoring', '/admin/monitoring/sample-referral-network.php', 'Sample Referral Network', 'always');

-- Grant the new report to every role that can already see the Sources of Requests report.
INSERT INTO `roles_privileges_map` (`role_id`, `privilege_id`)
SELECT rp.`role_id`, np.`privilege_id`
  FROM `roles_privileges_map` rp
  JOIN `privileges` sp ON sp.`privilege_id` = rp.`privilege_id`
                      AND sp.`privilege_name` = '/admin/monitoring/sources-of-requests.php'
  JOIN `privileges` np ON np.`privilege_name` = '/admin/monitoring/sample-referral-network.php'
 WHERE NOT EXISTS (
     SELECT 1 FROM `roles_privileges_map` x
      WHERE x.`role_id` = rp.`role_id` AND x.`privilege_id` = np.`privilege_id`
 );

INSERT IGNORE INTO `s_app_menu`
  (`module`, `sub_module`, `is_header`, `display_text`, `link`, `inner_pages`, `show_mode`, `icon`, `has_children`, `additional_class_names`, `parent_id`, `display_order`, `status`, `updated_datetime`)
VALUES
  ('admin', NULL, 'no', 'Sample Referral Network', '/admin/monitoring/sample-referral-network.php', NULL, 'always', 'fa-solid fa-diagram-project', 'no', 'allMenu  sample-referral-network-menu', 7, 21, 'active', CURRENT_TIMESTAMP);


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
