-- Migration file for version 5.4.3
-- Remote command plane: STS queues commands for LIS instances.
-- LIS pulls pending commands on its existing sync-sts tick and reports
-- status back on the same round-trip. See docs/remote-command-plane.md.


CREATE TABLE IF NOT EXISTS `s_lis_remote_commands` (
  `command_id`    CHAR(26)      NOT NULL,
  `lab_id`        INT           NOT NULL,
  `command`       VARCHAR(32)   NOT NULL,
  `params`        JSON          NULL,
  `status`        ENUM('pending','picked','running','preparing','prepared',
                       'applying','completed','failed','expired','cancelled')
                                NOT NULL DEFAULT 'pending',
  `requested_by`  INT           NULL,
  `requested_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `picked_at`     DATETIME      NULL,
  `completed_at`  DATETIME      NULL,
  `not_before`    DATETIME      NULL,
  `expires_at`    DATETIME      NULL,
  `depends_on`    CHAR(26)      NULL,
  `result`        JSON          NULL,
  `last_error`    TEXT          NULL,
  `nonce`         CHAR(26)      NOT NULL,
  PRIMARY KEY (`command_id`),
  KEY `idx_lab_status`        (`lab_id`, `status`),
  KEY `idx_status_requested`  (`status`, `requested_at`),
  KEY `idx_depends_on`        (`depends_on`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Privilege for queuing remote commands (STS admin only)
INSERT IGNORE INTO `privileges`
  (`privilege_id`, `resource_id`, `privilege_name`, `shared_privileges`, `display_name`, `display_order`, `show_mode`)
VALUES
  (NULL, 'monitoring', '/admin/monitoring/queue-lis-command.php', NULL, 'Queue Lab Command', '2', 'sts');


UPDATE `system_config` SET `value` = '5.4.3' WHERE `system_config`.`name` = 'sc_version';
