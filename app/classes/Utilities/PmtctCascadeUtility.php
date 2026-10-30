<?php

namespace App\Utilities;

use App\Services\DatabaseService;

/**
 * The PMTCT Cascade Report's counting rules, shared by the report screen and
 * its Excel export so the two always give the same numbers.
 *
 * A child is linked to a mother when the Mother ID on the EID request matches
 * the ART number on a VL request (TRIM on both sides: real data carries stray
 * whitespace, and the collation does not pad). The EID filters pick the
 * children; each matched mother's VL history is used from any date.
 *
 * Counting rules:
 *   - Children are counted once each by Child ID, not once per EID sample.
 *   - A mother's VL status is her most recent VL result on record.
 *   - "High VL before birth" means a high result collected in the
 *     PREGNANCY_DAYS before the child's date of birth.
 */
final class PmtctCascadeUtility
{
    /** Length of a pregnancy, for the "high VL before birth" window. */
    public const PREGNANCY_DAYS = 280;

    /**
     * WHERE conditions (to AND together) for the EID records in the report.
     *
     * @param array<string, mixed> $post sampleCollectionDate, provinceId, labName
     * @return list<string>
     */
    public static function filterConditions(array $post, string $alias = 'e'): array
    {
        // A cancelled sample was called off before testing, so it is not a test
        // this cascade should count.
        $where = [
            "$alias.mother_id IS NOT NULL",
            "TRIM($alias.mother_id) != ''",
            SampleCountUtility::countableWhere($alias),
        ];

        if (!empty($post['sampleCollectionDate'])) {
            $range = explode("to", (string) $post['sampleCollectionDate']);
            if (count($range) === 2) {
                $startSql = date('Y-m-d', strtotime(trim($range[0])));
                $endSql   = date('Y-m-d', strtotime(trim($range[1])));
                $where[] = "DATE($alias.sample_collection_date) BETWEEN '$startSql' AND '$endSql'";
            }
        }
        if (!empty($post['provinceId']) && ctype_digit((string) $post['provinceId'])) {
            $where[] = "$alias.province_id = " . (int) $post['provinceId'];
        }
        if (!empty($post['labName']) && ctype_digit((string) $post['labName'])) {
            $where[] = "$alias.lab_id = " . (int) $post['labName'];
        }
        return $where;
    }

    /**
     * SQL fragment classifying an EID record as HIV-positive: the canonical
     * "positive" plus the descriptive "HIV-1 DETECTED ..." form.
     */
    public static function eidPositiveExpr(string $alias = 'e'): string
    {
        return "(LOWER(TRIM($alias.result)) = 'positive'"
            . " OR ($alias.result LIKE '%DETECTED%'"
            . " AND $alias.result NOT LIKE '%NOT DETECTED%'"
            . " AND $alias.result NOT LIKE '%Not Detected%'"
            . " AND $alias.result NOT LIKE '%not detected%'))";
    }

    /**
     * SQL fragment identifying a child across repeat EID tests. Prefers the
     * recorded Child ID; records without one fall back to their own eid_id.
     */
    public static function childKeyExpr(string $alias = 'e'): string
    {
        return "COALESCE(NULLIF(TRIM($alias.child_id), ''), CONCAT('eid:', $alias.eid_id))";
    }

    /** SQL fragment: the VL test has a categorised result (< or >= 1000 cp/mL). */
    public static function vlHasResultExpr(string $alias = 'v'): string
    {
        return "$alias.vl_result_category IN ('suppressed','not suppressed')";
    }

    /** SQL fragment: the VL result is high (>= 1000 cp/mL). */
    public static function highVlExpr(string $alias = 'v'): string
    {
        return "$alias.vl_result_category = 'not suppressed'";
    }

    /**
     * Inside a GROUP BY over one mother's VL tests: $valueSql taken from her
     * most recent test that has a result. Tests without a result are NULL in
     * the GROUP_CONCAT and so skipped; only the first element is read, so a
     * truncated long history loses only the tail that is discarded anyway.
     */
    public static function latestResultExpr(string $valueSql, string $alias = 'v'): string
    {
        return "SUBSTRING_INDEX(GROUP_CONCAT(CASE WHEN " . self::vlHasResultExpr($alias)
            . " THEN COALESCE($valueSql, '') END"
            . " ORDER BY $alias.sample_collection_date DESC, $alias.vl_sample_id DESC SEPARATOR '||'), '||', 1)";
    }

    /**
     * Did the mother have a high VL result in the PREGNANCY_DAYS before the
     * child's birth? 'yes', 'no', or 'unknown' when the child has no DOB.
     *
     * @param string $childDob Y-m-d, or '' when not recorded
     * @param list<string> $highVlDates Y-m-d collection dates of her high results
     */
    public static function preBirthFlag(string $childDob, array $highVlDates): string
    {
        if ($childDob === '') {
            return 'unknown';
        }
        $windowStart = date('Y-m-d', strtotime($childDob . ' -' . self::PREGNANCY_DAYS . ' days'));
        foreach ($highVlDates as $date) {
            if ($date !== '' && $date >= $windowStart && $date < $childDob) {
                return 'yes';
            }
        }
        return 'no';
    }

    /**
     * Every snapshot number for the given filters.
     *
     * @param array<string, mixed> $post the report filters
     * @return array<string, int>
     */
    public static function summary(DatabaseService $db, array $post): array
    {
        $whereSql   = implode(' AND ', self::filterConditions($post));
        $whereSqlE2 = implode(' AND ', self::filterConditions($post, 'e2'));
        $vlCountable  = SampleCountUtility::countableWhere('v');
        $positiveExpr = self::eidPositiveExpr('e');
        $childKey     = self::childKeyExpr('e');
        $hasResult    = self::vlHasResultExpr('v');
        $highVl       = self::highVlExpr('v');
        $validDob     = "e.child_dob IS NOT NULL AND e.child_dob != '0000-00-00'";

        // Mothers of the children in the window, as a small derived set. Joining
        // form_vl to it (rather than to form_eid row by row) keeps the TRIM join,
        // which cannot use the art_no index, bounded.
        $windowMothers = "SELECT DISTINCT TRIM(e2.mother_id) AS mid FROM form_eid e2 WHERE $whereSqlE2";

        $childRow = $db->rawQueryOne("SELECT
                COUNT(DISTINCT $childKey) AS totalChildren,
                COUNT(DISTINCT CASE WHEN vm.mid IS NOT NULL THEN $childKey END) AS matchedChildren,
                COUNT(DISTINCT CASE WHEN vm.mid IS NOT NULL AND e.result IS NOT NULL AND TRIM(e.result) != ''
                                THEN $childKey END) AS testedChildren,
                COUNT(DISTINCT CASE WHEN vm.mid IS NOT NULL AND $positiveExpr
                                THEN $childKey END) AS positiveChildren,
                COUNT(DISTINCT CASE WHEN vm.mid IS NOT NULL AND $positiveExpr AND NOT ($validDob)
                                THEN $childKey END) AS positiveChildrenNoDob
            FROM form_eid e
            LEFT JOIN (
                SELECT DISTINCT TRIM(v.patient_art_no) AS mid
                FROM form_vl v
                INNER JOIN ($windowMothers) wm ON wm.mid = TRIM(v.patient_art_no)
                WHERE $vlCountable
            ) vm ON vm.mid = TRIM(e.mother_id)
            WHERE $whereSql") ?: [];

        // One row per matched mother, then totals over those rows. Status is her
        // latest result; pregnant / breastfeeding are as recorded on that request.
        $motherRow = $db->rawQueryOne("SELECT
                COUNT(*) AS distinctMothers,
                SUM(vm.vlTests) AS vlTests,
                SUM(vm.vlTestsWithResult) AS vlTestsWithResult,
                SUM(vm.vlTestsWithResult > 0) AS mothersWithResult,
                SUM(vm.latestCategory = 'not suppressed') AS mothersHighVl,
                SUM(vm.latestCategory = 'suppressed' AND vm.highVlTests > 0) AS mothersSuppressedAfterHighVl,
                SUM(vm.latestCategory = 'not suppressed' AND LOWER(TRIM(vm.latestPregnant)) = 'yes')
                    AS mothersHighVlPregnant,
                SUM(vm.latestCategory = 'not suppressed' AND LOWER(TRIM(vm.latestBreastfeeding)) = 'yes')
                    AS mothersHighVlBreastfeeding
            FROM (
                SELECT TRIM(v.patient_art_no) AS mid,
                       COUNT(*) AS vlTests,
                       SUM($hasResult) AS vlTestsWithResult,
                       SUM($highVl) AS highVlTests,
                       " . self::latestResultExpr('v.vl_result_category') . " AS latestCategory,
                       " . self::latestResultExpr('v.is_patient_pregnant') . " AS latestPregnant,
                       " . self::latestResultExpr('v.is_patient_breastfeeding') . " AS latestBreastfeeding
                FROM form_vl v
                INNER JOIN ($windowMothers) wm ON wm.mid = TRIM(v.patient_art_no)
                WHERE $vlCountable
                GROUP BY TRIM(v.patient_art_no)
            ) vm") ?: [];

        // Positive children whose mother had a high result in the pregnancy
        // window. Only high results of mothers of positive children are joined.
        $positiveExprE2 = self::eidPositiveExpr('e2');
        $preBirthRow = $db->rawQueryOne("SELECT COUNT(DISTINCT $childKey) AS preBirthHighVlChildren
            FROM form_eid e
            INNER JOIN (
                SELECT TRIM(v.patient_art_no) AS mid, DATE(v.sample_collection_date) AS highDate
                FROM form_vl v
                INNER JOIN (
                    SELECT DISTINCT TRIM(e2.mother_id) AS mid
                    FROM form_eid e2
                    WHERE $whereSqlE2 AND $positiveExprE2
                ) pm ON pm.mid = TRIM(v.patient_art_no)
                WHERE $vlCountable AND $highVl
            ) vh ON vh.mid = TRIM(e.mother_id)
            WHERE $whereSql AND $positiveExpr AND $validDob
              AND vh.highDate < e.child_dob
              AND vh.highDate >= DATE_SUB(e.child_dob, INTERVAL " . self::PREGNANCY_DAYS . " DAY)") ?: [];

        $total   = (int) ($childRow['totalChildren'] ?? 0);
        $matched = (int) ($childRow['matchedChildren'] ?? 0);

        return [
            'totalChildrenWithMotherId'    => $total,
            'matchedChildren'              => $matched,
            'unmatchedChildren'            => max(0, $total - $matched),
            'testedChildren'               => (int) ($childRow['testedChildren'] ?? 0),
            'positiveChildren'             => (int) ($childRow['positiveChildren'] ?? 0),
            'positiveChildrenNoDob'        => (int) ($childRow['positiveChildrenNoDob'] ?? 0),
            'preBirthHighVlChildren'       => (int) ($preBirthRow['preBirthHighVlChildren'] ?? 0),
            'distinctMothers'              => (int) ($motherRow['distinctMothers'] ?? 0),
            'vlTests'                      => (int) ($motherRow['vlTests'] ?? 0),
            'vlTestsWithResult'            => (int) ($motherRow['vlTestsWithResult'] ?? 0),
            'mothersWithResult'            => (int) ($motherRow['mothersWithResult'] ?? 0),
            'mothersHighVl'                => (int) ($motherRow['mothersHighVl'] ?? 0),
            'mothersSuppressedAfterHighVl' => (int) ($motherRow['mothersSuppressedAfterHighVl'] ?? 0),
            'mothersHighVlPregnant'        => (int) ($motherRow['mothersHighVlPregnant'] ?? 0),
            'mothersHighVlBreastfeeding'   => (int) ($motherRow['mothersHighVlBreastfeeding'] ?? 0),
        ];
    }
}
