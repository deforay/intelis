-- Migration file for version 5.6.4
--
-- Lets an ART regimen arriving over the API be recognised, instead of stored and hidden.
--
-- api/v1.1/vl/save-request.php wrote `artRegimen` into form_vl.current_regimen verbatim,
-- with no check that the value corresponds to anything in r_vl_art_regimen. The request
-- form is the one surface that resolves the column against that table -- it preselects an
-- <option> only where current_regimen equals an art_code exactly -- so a value from a
-- system with its own regimen vocabulary is stored, reported and printed correctly but
-- renders as an empty dropdown.
--
-- Observed with an EMR posting over the API, which sends its own catalogue labels:
--
--   "08 - TDF/3TC/DTG 300 mg/300 mg/50 mg"   vs   "1a = TDF+3TC+DTG"
--
-- Same regimen, different numbering scheme, different separators, strengths included.
-- The API answered 'success' either way and nothing logged the mismatch.
--
-- Two consequences, only the second of which loses anything:
--
--   * The dropdown is blank on the request form, and on STS the result form marks the
--     field required, so an operator is pushed to pick an arbitrary regimen to proceed.
--   * Saving the request form writes the blank select over the stored value
--     (editVlRequestHelper.php). The value then disappears from the result PDF and the
--     request/result exports too, all of which read current_regimen directly and have
--     been displaying it correctly all along.
--
-- The approach here is to fail open. A regimen is reference data attached to a test
-- request, not a reason to reject one, so an unrecognised value is registered rather
-- than refused: VlService::resolveArtRegimen() matches art_code, and failing that inserts
-- the incoming string as a new regimen. The value is then in the table, so the dropdown
-- can render it, and the blank-select overwrite stops being reachable -- an empty post
-- now genuinely means the operator cleared the field.
--
-- Auto-registration alone would degrade the reference data over time, which is what the
-- alias table is for. "08 - TDF/3TC/DTG 300 mg/300 mg/50 mg" and "1a = TDF+3TC+DTG" name
-- the same regimen, so left unmapped they split one cohort across two codes in every
-- regimen breakdown. Mapping the external string onto the canonical art_id lets reporting
-- group them as one.
--
-- The mapping is read-time only, and resolveArtRegimen() deliberately does not consult it.
-- What a request stores is what the caller sent, before and after any mapping is made;
-- reporting that groups by regimen joins through this table. Resolving on write would mean
-- the same incoming value was stored one way before an administrator created the mapping
-- and another way after, so what a row meant would depend on when it was saved. That is
-- the hazard that keeps an Edit button off the ART Regimen reference page: current_regimen
-- holds the code text rather than a reference to art_id, so changing a code -- or a stored
-- value -- silently reinterprets every historical row carrying it. Nothing here rewrites
-- form_vl for the same reason.
--
-- A mapped code must also stay active. The request and result forms populate the dropdown
-- from art_status = 'active', so retiring a code that samples still reference puts those
-- samples back to the blank dropdown this migration exists to fix.
--
-- No fuzzy matching. Normalising away prefixes, separators and strengths would appear to
-- match the pair above, but r_vl_art_regimen holds regimens that are drug-identical under
-- different line codes -- 2b, 4c and 5c are all AZT+3TC+LPV/r -- so normalisation returns
-- three candidates with no basis for choosing between them. Declining and letting an
-- administrator map it once is the only answer that cannot be silently wrong.

-- External regimen strings mapped onto a canonical regimen.
--
-- external_code is what the sending system transmits and is matched verbatim, so the
-- mapping is exact and auditable rather than inferred. It is UNIQUE because one incoming
-- string resolves to one regimen; re-mapping means updating the row, not adding a second.
--
-- The join to r_vl_art_regimen is on art_id, an integer, deliberately. r_vl_art_regimen
-- had its table default collation changed to utf8mb4_general_ci in 4.4.9 while form_vl
-- remained utf8mb4_0900_ai_ci, and whether any given instance's art_code column followed
-- depends on its upgrade history. Every string comparison in the resolver is therefore
-- confined to a single table and passed as a bound parameter, and nothing joins art_code
-- to current_regimen across tables, where an illegal mix of collations (errno 1267) would
-- be a live possibility on part of the fleet.
CREATE TABLE `r_vl_art_regimen_alias` (
  `alias_id` int NOT NULL AUTO_INCREMENT,
  `external_code` varchar(255) NOT NULL,
  `art_id` int NOT NULL,
  `alias_source` varchar(64) DEFAULT NULL,
  `mapped_by` int DEFAULT NULL,
  `updated_datetime` datetime DEFAULT NULL,
  PRIMARY KEY (`alias_id`),
  UNIQUE KEY `uniq_alias_external_code` (`external_code`),
  KEY `idx_alias_art_id` (`art_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Marks where a regimen came from, so auto-registered rows can be found and mapped.
--
-- Without this an administrator has no way to tell a regimen the programme defined from
-- one the API invented, and the backlog of external codes is indistinguishable from the
-- real catalogue. NULL for everything that already exists: rows predating this column
-- were entered through the admin screens or shipped in the seed, and claiming a source
-- for them would be a guess. The resolver writes 'api-auto' on the rows it creates.
ALTER TABLE `r_vl_art_regimen` ADD COLUMN `art_source` varchar(45) DEFAULT NULL AFTER `art_status`;

-- Existing rows are backfilled by run-once/backfill-api-art-regimens.php rather than
-- here. The backfill has to compare form_vl.current_regimen against art_code, which is
-- exactly the cross-table string comparison the collation note above rules out, and it
-- should register each distinct value through the same resolver the API uses so the two
-- paths cannot drift. That is PHP's job, not this file's.

UPDATE `system_config` SET `value` = '5.6.4' WHERE `system_config`.`name` = 'sc_version';
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
