-- Migration file for version 5.5.6
-- Created on 2026-05-28 12:55:45
--
-- API facility-scope hardening (final stage of the STS-as-LIS rollout).
--
-- Adds a feature flag controlling whether the new per-endpoint
-- facilityMap intersection on the API write paths (save-request,
-- cancel-requests, instruments) is enforced or merely observed.
--
--   'no'  (default): the check runs and LOGS every out-of-scope API
--                    request, but does NOT reject it. Existing flows
--                    keep working while operators verify the logs are
--                    clean.
--   'yes':          enforce -- out-of-scope facility/lab ids are
--                    rejected on save, instrument lookups, and cancels.
--
-- Flip to 'yes' once the logs confirm no legitimate flow is
-- mis-flagged. LIS installs are no-op regardless of the flag.

INSERT IGNORE INTO `global_config` (`name`, `display_name`, `value`, `category`, `status`)
VALUES ('api_facility_scope_enforce', 'Enforce API facility scope (vs observe-only)', 'no', 'api', 'active');


UPDATE `system_config` SET `value` = '5.5.6' WHERE `system_config`.`name` = 'sc_version';
