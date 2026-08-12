-- Migration file for version 5.6.3
-- Created on 2026-08-10 14:42:44
--
-- Cleans form_vl.result_value_log, which is a derived numeric field and the only
-- column either statement below writes.
--
-- Nothing here touches `result`, `result_value_absolute`, `result_value_text` or
-- `vl_result_category`. The result stays exactly as it was received from the
-- instrument or entered by the operator, including where that value is itself odd.
-- The log is not an as-received value: every path that writes it derives it, through
-- round(log10(...)) in the instrument parsers, a (float) cast in the Abbott and Roche
-- NHRL readers, or VlService::processViralLoadResultFromForm(). So it is the one place
-- a value can be tidied without altering what a lab reported.
--
-- Both statements are scoped to rows that are not already a plain number, so a
-- correctly stored log is never rewritten and a replay is a no-op. data_sync is reset
-- so the corrections reach STS; without it the local copies would be repaired while
-- the remote kept the old values.

-- 1. Spreadsheet error text. Excel's LOG10() over an empty or zero copies cell yields
-- these, and they reached the database either through a spreadsheet-to-database load
-- of historical data or through the Hologic Panther reader, which read its log column
-- straight off the sheet with no numeric check until this release.
--
-- NULL, not 0. 0 is a legitimate log value -- one copy per millilitre -- so coercing
-- this text to a number would turn an obviously broken cell into a plausible-looking
-- suppressed result. For the same reason this runs as an explicit statement now rather
-- than being left to a future change of the column's type, where MySQL would do that
-- coercion silently.
--
-- No information is lost: every row cleared here still carries its answer in `result`
-- -- "TND", "<40" or a copies figure -- and the discarded text remains recoverable
-- from audit_log, since these rows pass through the form_vl audit triggers.
UPDATE `form_vl`
SET `result_value_log` = NULL,
    `data_sync` = 0
WHERE TRIM(`result_value_log`) IN ('#NUM!', '#VALUE!', 'NaN');

-- 2. Logs holding a real number written in a form the column cannot be read as.
-- Observed variants, all from manual entry or a partial parse:
--
--   "<1,60", "< 1,60", "1,60"   decimal comma, francophone data entry
--   "<2.92", "> 7,00"           a comparison operator in a numeric column
--   " 1.60", non-breaking space  leading whitespace
--   "2.38);", "1.60);"          trailing punctuation left by the Roche
--                               parenthetical parse
--   "4,OO", "3,6O", "3,o"       letter O typed for zero
--
-- The rewrite strips the operators, punctuation and spaces, swaps the decimal comma
-- for a point and maps O to 0. The comparison operator is dropped rather than
-- preserved because the below-detection meaning it carries is already held by `result`
-- ("<40") and vl_result_category ("suppressed"), neither of which changes here, and
-- because a numeric column has nowhere to keep it.
--
-- The guard is the point of the statement: the WHERE clause requires the cleaned value
-- to be a plain number before anything is written, so a row is only rewritten when the
-- reading is unambiguous. On the DRC data this resolves every non-numeric value that
-- is not one of the three error strings above, with none left over -- but any variant
-- not anticipated here is simply left alone rather than guessed at.
--
-- Logs that hold a copies figure rather than a log (a bare " 20", " 40", " 839") are
-- deliberately left as entered. They come out of this statement as 20, 40 and 839,
-- still implausible as logs, because correcting them would mean deciding what the
-- operator meant to type.
UPDATE `form_vl`
SET `result_value_log` = REPLACE(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
        `result_value_log`, _utf8mb4 0xC2A0, ''), '<', ''), '>', ''), ')', ''), ';', ''), ',', '.'), 'O', '0'), 'o', '0')), ' ', ''),
    `data_sync` = 0
WHERE `result_value_log` IS NOT NULL
  AND TRIM(`result_value_log`) <> ''
  AND `result_value_log` NOT REGEXP '^-?[0-9]+(\\.[0-9]+)?$'
  AND REPLACE(TRIM(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
        `result_value_log`, _utf8mb4 0xC2A0, ''), '<', ''), '>', ''), ')', ''), ';', ''), ',', '.'), 'O', '0'), 'o', '0')), ' ', '')
      REGEXP '^-?[0-9]+(\\.[0-9]+)?$';

UPDATE `system_config` SET `value` = '5.6.3' WHERE `system_config`.`name` = 'sc_version';
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
