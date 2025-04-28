-- Migration file for version 5.3.1
-- Created on 2025-03-21 12:41:17

-- Amit 10-Apr-2025
UPDATE `s_app_menu` SET `show_mode` = 'sts' WHERE link like '/admin/monitoring/sync-status.php';

-- Amit 24-Apr-2025
ALTER TABLE `form_vl` CHANGE `recency_vl` `recency_vl` VARCHAR(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NULL DEFAULT 'no';

-- Amit 28-Apr-2025
INSERT IGNORE INTO roles_privileges_map (role_id, privilege_id)
SELECT 1, privilege_id FROM privileges;
