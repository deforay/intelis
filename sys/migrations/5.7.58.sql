-- Migration file for version 5.7.58
-- Created on 2026-09-05
--
-- Drops nine indexes that sql/init.sql declares twice.
--
-- An index carries a name, not a definition, so the same one can be declared
-- under two names and nothing objects. The seed does it nine times, which means
-- every installation ever created from it started life maintaining nine indexes
-- that answer no query another one cannot. Three of them are duplicates of a
-- primary key: r_countries, s_vlsm_instance and system_admin each declare
-- PRIMARY KEY over a column and then a UNIQUE KEY over the same column again.
--
-- init.sql is not edited for this. It is the seed rather than a schema change,
-- and a migration reaches both populations on its own: a fresh install creates
-- the pair and drops one here, an existing install has carried the pair for
-- years and drops one here. The cost on a fresh install is creating an index on
-- an empty table and removing it a moment later.
--
-- The copy that goes is the later name in each pair, and never a primary key.
-- None of them backs a foreign key -- form_vl's only constraint is on
-- result_status, which is untouched, and the tables that reference
-- facility_details do so through its primary key.
--
-- This clears what the seed itself declares, which is deterministic and can be
-- written out here. It does not clear what installs have accumulated since,
-- which cannot: MySQL appends _2, _3 and onward every time an unguarded ADD KEY
-- is re-run, and one DRC instance is carrying 63 copies of the s_app_menu index
-- and 41 of one on user_details, with 115 redundant in total. Those names differ
-- per install and are the job of `composer duplicate-indexes`.
--
-- Dropping an index on form_vl rebuilds a table of about 1.5M rows, which takes
-- minutes and wants a brief metadata lock at each end, so it waits behind any
-- long transaction. Every statement is written so re-running cannot fail.

ALTER TABLE `r_countries`      DROP INDEX `id`;
ALTER TABLE `s_vlsm_instance`  DROP INDEX `vl_instance_id`;
ALTER TABLE `system_admin`     DROP INDEX `user_admin_id`;
ALTER TABLE `facility_details` DROP INDEX `facility_name_2`;
ALTER TABLE `facility_details` DROP INDEX `other_id_2`;
ALTER TABLE `form_vl`          DROP INDEX `result_approved_by_2`;
ALTER TABLE `form_vl`          DROP INDEX `result_reviewed_by_2`;
ALTER TABLE `province_details` DROP INDEX `province_name_2`;
ALTER TABLE `s_app_menu`       DROP INDEX `parent_id_2`;

UPDATE `system_config` SET `value` = '5.7.58' WHERE `system_config`.`name` = 'sc_version';
