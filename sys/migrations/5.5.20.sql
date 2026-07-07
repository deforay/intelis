-- Migration file for version 5.5.20
-- Created on 2026-07-07 13:49:50
--
-- Drop the UNIQUE constraint on user_details.user_name.
--
-- user_name is a person's display / full name, not an identity key: two real
-- people can share a name. Enforcing uniqueness on it blocked legitimate adds
-- and surfaced only as a silently-swallowed duplicate-key error on user
-- add/edit. The genuine identity keys (login_id, email) keep their uniqueness.
-- The constraint was added long ago in 4.4.9 (`ADD UNIQUE(user_name)`); its
-- rationale is unknown and no longer wanted.
--
-- Fresh installs (init.sql seeds sc_version 5.3.2, so 4.4.9 never replays)
-- never had this index. The migrate runner routes DROP INDEX through
-- drop_index_if_exists(), so this is a clean no-op where the index is absent.

ALTER TABLE `user_details` DROP INDEX `user_name`;


UPDATE `system_config` SET `value` = '5.5.20' WHERE `system_config`.`name` = 'sc_version';

