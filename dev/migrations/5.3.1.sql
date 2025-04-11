-- Migration file for version 5.3.1
-- Created on 2025-03-21 12:41:17

-- Amit 10-Apr-2025
UPDATE `s_app_menu` SET `show_mode` = 'sts' WHERE link like '/admin/monitoring/sync-status.php';
