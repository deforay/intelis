-- Migration file for version 5.7.65
-- Created on 2026-09-10
--
-- Folds Rwanda's per-test-card rejections up onto the request they belong to.
--
-- Rwanda is the only TB form that records more than one test per sample. When it
-- was converted to repeating test cards, "Is Sample Rejected?" moved with them,
-- so a rejection was stored per row in `tb_tests` and only mirrored onto
-- `form_tb` by whichever card happened to be last.
--
-- A TB sample is one specimen transferred from lab to lab, not several specimens
-- tested side by side, so a rejected specimen is rejected for the whole request.
-- The forms now carry one request-level rejection and `tb_tests` no longer
-- records one at all -- and because saving a Rwanda request deletes and reinserts
-- its cards, any rejection still living only on a card would be dropped the next
-- time someone opened that sample.
--
-- So copy it up first. Only rows where `form_tb` has no rejection of its own are
-- touched, and the earliest rejected card wins: rejection ends the sample, so the
-- first lab to reject it is the one that did.
--
-- Nothing reads the `tb_tests` rejection columns, so they are left in place
-- rather than dropped -- an ALTER on a large table is not worth it to remove
-- three columns no query mentions, and keeping them preserves the original
-- per-card record for anyone reconciling this change later.

UPDATE `form_tb` f
INNER JOIN (
    SELECT
        t.tb_id,
        SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(t.reason_for_sample_rejection, '') ORDER BY t.tb_test_id ASC), ',', 1) AS reason,
        SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(t.rejection_on, '') ORDER BY t.tb_test_id ASC), ',', 1) AS rejected_on
    FROM `tb_tests` t
    WHERE LOWER(TRIM(t.is_sample_rejected)) = 'yes'
    GROUP BY t.tb_id
) r ON r.tb_id = f.tb_id
SET
    f.is_sample_rejected = 'yes',
    f.reason_for_sample_rejection = COALESCE(NULLIF(f.reason_for_sample_rejection, 'N/A'), NULLIF(r.reason, '')),
    f.rejection_on = COALESCE(f.rejection_on, NULLIF(r.rejected_on, ''))
WHERE COALESCE(LOWER(TRIM(f.is_sample_rejected)), '') <> 'yes';
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
