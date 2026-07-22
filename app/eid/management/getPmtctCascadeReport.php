<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/**
 * Build a SQL WHERE clause fragment from the user's filter inputs.
 * Returns an array of conditions to be ANDed together.
 */
function pmtctBuildFilterConditions(array $post, DatabaseService $db, string $alias = 'e'): array
{
    $where = ["$alias.mother_id IS NOT NULL", "TRIM($alias.mother_id) != ''"];

    if (!empty($post['sampleCollectionDate'])) {
        $range = explode("to", (string) $post['sampleCollectionDate']);
        if (count($range) === 2) {
            $start = trim($range[0]);
            $end   = trim($range[1]);
            $startSql = date('Y-m-d', strtotime($start));
            $endSql   = date('Y-m-d', strtotime($end));
            $where[] = "DATE($alias.sample_collection_date) BETWEEN '" . $startSql . "' AND '" . $endSql . "'";
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
 * SQL fragment that classifies an EID record as HIV-positive.
 * Captures both the canonical "positive" string used in this DB and the
 * descriptive "HIV-1 DETECTED ..." form (excluding "NOT DETECTED").
 */
function pmtctEidPositiveExpr(string $alias = 'e'): string
{
    return "(LOWER(TRIM($alias.result)) = 'positive'"
        . " OR ($alias.result LIKE '%DETECTED%'"
        . " AND $alias.result NOT LIKE '%NOT DETECTED%'"
        . " AND $alias.result NOT LIKE '%Not Detected%'"
        . " AND $alias.result NOT LIKE '%not detected%'))";
}

/**
 * SQL fragment that classifies a VL test as a high viral load
 * (>= 1000 cp/mL per WHO PMTCT thresholds). Relies on the precomputed
 * vl_result_category column ('suppressed' for <1000, 'not suppressed' for
 * >=1000) so we don't have to interpret the raw result fields here.
 */
function pmtctHighVlExpr(string $alias = 'v'): string
{
    return "$alias.vl_result_category = 'not suppressed'";
}

/**
 * SQL fragment for "VL test has a categorised result on file" — i.e. the
 * lab has classified the value as suppressed/not suppressed. Replaces the
 * older "v.result IS NOT NULL AND TRIM(v.result) != ''" check.
 */
function pmtctVlHasResultExpr(string $alias = 'v'): string
{
    return "$alias.vl_result_category IN ('suppressed','not suppressed')";
}

/**
 * SQL fragment identifying a child across repeat EID tests. Prefers the
 * facility-recorded Child ID; records without one fall back to their own
 * eid_id so they count as single-test children instead of collapsing into
 * one bogus "blank" child.
 */
function pmtctChildKeyExpr(string $alias = 'e'): string
{
    return "COALESCE(NULLIF(TRIM($alias.child_id), ''), CONCAT('eid:', $alias.eid_id))";
}

/**
 * Normalise a date value for JSON output (zero dates and NULLs become '').
 */
function pmtctDateOrBlank($v): string
{
    if (empty($v) || str_starts_with((string) $v, '0000-00-00')) {
        return '';
    }
    $ts = strtotime((string) $v);
    return $ts ? date('Y-m-d', $ts) : '';
}

/**
 * Classify whether the mother had a documented high VL strictly before the
 * child's date of birth. Returns 'yes', 'no', or 'unknown' (no usable DOB).
 */
function pmtctPreBirthFlag(?string $childDob, ?string $firstHighVlDate): string
{
    $dob = pmtctDateOrBlank($childDob);
    if ($dob === '') {
        return 'unknown';
    }
    $firstHigh = pmtctDateOrBlank($firstHighVlDate);
    if ($firstHigh === '' || $firstHigh >= $dob) {
        return 'no';
    }
    return 'yes';
}

/**
 * Given a mother's VL tests (ascending by collection date), find the most
 * recent test with a categorised result on or before the given date.
 */
function pmtctVlStatusAsOf(array $vlTests, string $asOfDate): ?array
{
    if ($asOfDate === '') {
        return null;
    }
    $latest = null;
    foreach ($vlTests as $t) {
        $d = $t['collectionDate'] ?? '';
        if ($d === '' || $d > $asOfDate) {
            continue;
        }
        if (($t['category'] ?? '') === 'suppressed' || ($t['category'] ?? '') === 'not suppressed') {
            $latest = $t;
        }
    }
    return $latest;
}

$action = $_POST['action'] ?? 'linked';

// ---------------------------------------------------------------------------
// SUMMARY action - returns the 7 KPI numbers as JSON
// ---------------------------------------------------------------------------
if ($action === 'summary') {

    $where = pmtctBuildFilterConditions($_POST, $db);
    $whereSql = implode(' AND ', $where);
    $positiveExpr = pmtctEidPositiveExpr('e');
    $highVlExpr   = pmtctHighVlExpr('v');
    $childKeyExpr = pmtctChildKeyExpr('e');

    // Children-side counts (one row per child)
    $childSql = "SELECT
            COUNT(DISTINCT e.eid_id) AS totalChildrenWithMotherId,
            COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NOT NULL THEN e.eid_id END) AS matchedChildren,
            COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NOT NULL
                                 AND e.result IS NOT NULL AND TRIM(e.result) != ''
                            THEN e.eid_id END) AS testedChildren,
            COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NOT NULL AND $positiveExpr
                            THEN e.eid_id END) AS positiveChildren,
            COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NOT NULL AND $positiveExpr
                            THEN $childKeyExpr END) AS positiveChildrenDistinct,
            COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NULL THEN e.eid_id END) AS unmatchedChildren
        FROM form_eid e
        LEFT JOIN form_vl v ON TRIM(e.mother_id) = TRIM(v.patient_art_no)
        WHERE $whereSql";

    // Mother / VL-side counts (one row per VL test). VL tests are NOT
    // constrained by the EID date filter on purpose: a mother may have
    // tested before the child's EID date window and we still want to
    // surface that history.
    $hasVlResult = pmtctVlHasResultExpr('v');
    $motherSql = "SELECT
            COUNT(DISTINCT v.patient_art_no) AS distinctMothers,
            COUNT(DISTINCT v.vl_sample_id) AS vlTests,
            COUNT(DISTINCT CASE WHEN $hasVlResult THEN v.vl_sample_id END) AS vlTestsWithResult,
            COUNT(DISTINCT CASE WHEN $hasVlResult THEN v.patient_art_no END) AS mothersWithResult,
            COUNT(DISTINCT CASE WHEN $highVlExpr THEN v.patient_art_no END) AS mothersHighVl
        FROM form_eid e
        INNER JOIN form_vl v ON TRIM(e.mother_id) = TRIM(v.patient_art_no)
        WHERE $whereSql";

    // Of the HIV-positive children, how many had a mother with a documented
    // high VL strictly BEFORE the child's date of birth? Children without a
    // usable DOB cannot be classified and are excluded here (surfaced as
    // "unknown" in the drilldown). The mother's first high-VL date is
    // pre-aggregated in a derived table: joining form_vl row-by-row with the
    // date inequality forces a nested-loop full scan per EID row (~30s),
    // while "MIN(high VL date) < DOB" is equivalent and runs in ~2s.
    $whereSqlE2     = implode(' AND ', pmtctBuildFilterConditions($_POST, $db, 'e2'));
    $positiveExprE2 = pmtctEidPositiveExpr('e2');
    $preBirthSql = "SELECT
            COUNT(DISTINCT $childKeyExpr) AS preBirthHighVlChildren
        FROM form_eid e
        INNER JOIN (
            SELECT TRIM(v.patient_art_no) AS mid,
                   MIN(CASE WHEN v.vl_result_category = 'not suppressed' THEN DATE(v.sample_collection_date) END) AS firstHighVlDate
            FROM form_vl v
            INNER JOIN (
                SELECT DISTINCT TRIM(e2.mother_id) AS mid
                FROM form_eid e2
                WHERE $whereSqlE2 AND $positiveExprE2
            ) pm ON pm.mid = TRIM(v.patient_art_no)
            GROUP BY TRIM(v.patient_art_no)
        ) vm ON vm.mid = TRIM(e.mother_id)
        WHERE $whereSql AND $positiveExpr
          AND e.child_dob IS NOT NULL AND e.child_dob != '0000-00-00'
          AND vm.firstHighVlDate IS NOT NULL
          AND vm.firstHighVlDate < e.child_dob";

    $childRow    = $db->rawQueryOne($childSql);
    $motherRow   = $db->rawQueryOne($motherSql);
    $preBirthRow = $db->rawQueryOne($preBirthSql);

    $payload = [
        'totalChildrenWithMotherId' => (int) ($childRow['totalChildrenWithMotherId'] ?? 0),
        'matchedChildren'           => (int) ($childRow['matchedChildren'] ?? 0),
        'testedChildren'            => (int) ($childRow['testedChildren'] ?? 0),
        'positiveChildren'          => (int) ($childRow['positiveChildren'] ?? 0),
        'positiveChildrenDistinct'  => (int) ($childRow['positiveChildrenDistinct'] ?? 0),
        'preBirthHighVlChildren'    => (int) ($preBirthRow['preBirthHighVlChildren'] ?? 0),
        'unmatchedChildren'         => (int) ($childRow['unmatchedChildren'] ?? 0),
        'distinctMothers'           => (int) ($motherRow['distinctMothers'] ?? 0),
        'vlTests'                   => (int) ($motherRow['vlTests'] ?? 0),
        'vlTestsWithResult'         => (int) ($motherRow['vlTestsWithResult'] ?? 0),
        'mothersWithResult'         => (int) ($motherRow['mothersWithResult'] ?? 0),
        'mothersHighVl'             => (int) ($motherRow['mothersHighVl'] ?? 0),
    ];

    header('Content-Type: application/json');
    echo json_encode($payload);
    return;
}

// ---------------------------------------------------------------------------
// POSITIVE CHILDREN drilldown - one row per distinct HIV-positive matched
// child in the current filter window, with the mother's VL picture alongside.
// Optional preBirthOnly=1 restricts to children whose mother had a documented
// high VL before the child's date of birth.
// ---------------------------------------------------------------------------
if ($action === 'positiveChildren') {

    $whereSql     = implode(' AND ', pmtctBuildFilterConditions($_POST, $db));
    $whereSqlE2   = implode(' AND ', pmtctBuildFilterConditions($_POST, $db, 'e2'));
    $positiveExpr   = pmtctEidPositiveExpr('e');
    $positiveExprE2 = pmtctEidPositiveExpr('e2');
    $childKeyExpr   = pmtctChildKeyExpr('e');

    // Aggregate each mother's full VL history once (all time, on purpose:
    // pre-birth tests usually predate the EID filter window), restricted to
    // mothers of positive children in the window so the scan stays bounded.
    $sql = "SELECT
            $childKeyExpr AS childKey,
            MAX(TRIM(e.child_id)) AS childId,
            MAX(e.eid_id) AS eidId,
            MAX(e.child_gender) AS childSex,
            MAX(e.child_dob) AS childDob,
            MAX(e.child_age) AS childAgeMonths,
            TRIM(e.mother_id) AS motherId,
            COUNT(DISTINCT e.eid_id) AS positiveTestsInWindow,
            MAX(DATE(e.sample_collection_date)) AS latestPositiveDate,
            vm.vlTests,
            vm.highVlTests,
            vm.firstHighVlDate,
            vm.latestVlDate,
            vm.latestVlResult,
            vm.latestVlCategory
        FROM form_eid e
        INNER JOIN (
            SELECT TRIM(v.patient_art_no) AS mid,
                   COUNT(*) AS vlTests,
                   SUM(CASE WHEN v.vl_result_category = 'not suppressed' THEN 1 ELSE 0 END) AS highVlTests,
                   MIN(CASE WHEN v.vl_result_category = 'not suppressed' THEN DATE(v.sample_collection_date) END) AS firstHighVlDate,
                   MAX(DATE(v.sample_collection_date)) AS latestVlDate,
                   SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(v.result, '') ORDER BY v.sample_collection_date DESC SEPARATOR '||'), '||', 1) AS latestVlResult,
                   SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(v.vl_result_category, '') ORDER BY v.sample_collection_date DESC SEPARATOR '||'), '||', 1) AS latestVlCategory
            FROM form_vl v
            INNER JOIN (
                SELECT DISTINCT TRIM(e2.mother_id) AS mid
                FROM form_eid e2
                WHERE $whereSqlE2 AND $positiveExprE2
            ) pm ON pm.mid = TRIM(v.patient_art_no)
            GROUP BY TRIM(v.patient_art_no)
        ) vm ON vm.mid = TRIM(e.mother_id)
        WHERE $whereSql AND $positiveExpr
        GROUP BY childKey, TRIM(e.mother_id)
        ORDER BY latestPositiveDate DESC";

    $preBirthOnly = !empty($_POST['preBirthOnly']) && $_POST['preBirthOnly'] !== '0';

    $rows = [];
    foreach ($db->rawQuery($sql) as $r) {
        $preBirth = pmtctPreBirthFlag($r['childDob'] ?? null, $r['firstHighVlDate'] ?? null);
        if ($preBirthOnly && $preBirth !== 'yes') {
            continue;
        }
        $rows[] = [
            'childId'               => $r['childId'] ?? '',
            'eidId'                 => (int) ($r['eidId'] ?? 0),
            'childSex'              => $r['childSex'] ?? '',
            'childDob'              => pmtctDateOrBlank($r['childDob'] ?? null),
            'childAgeMonths'        => $r['childAgeMonths'] ?? '',
            'motherId'              => $r['motherId'] ?? '',
            'positiveTestsInWindow' => (int) ($r['positiveTestsInWindow'] ?? 0),
            'latestPositiveDate'    => pmtctDateOrBlank($r['latestPositiveDate'] ?? null),
            'vlTests'               => (int) ($r['vlTests'] ?? 0),
            'highVlTests'           => (int) ($r['highVlTests'] ?? 0),
            'firstHighVlDate'       => pmtctDateOrBlank($r['firstHighVlDate'] ?? null),
            'latestVlDate'          => pmtctDateOrBlank($r['latestVlDate'] ?? null),
            'latestVlResult'        => $r['latestVlResult'] ?? '',
            'latestVlCategory'      => $r['latestVlCategory'] ?? '',
            'motherHighVlPreBirth'  => $preBirth,
        ];
    }

    header('Content-Type: application/json');
    echo json_encode(['rows' => $rows]);
    return;
}

// ---------------------------------------------------------------------------
// HIGH VL MOTHERS drilldown - one row per distinct matched mother with at
// least one high VL (>= 1000 cp/mL) test on record, for children in the
// current filter window. VL history is all-time by design.
// ---------------------------------------------------------------------------
if ($action === 'highVlMothers') {

    $whereSql     = implode(' AND ', pmtctBuildFilterConditions($_POST, $db));
    $positiveExpr = pmtctEidPositiveExpr('e');
    $childKeyExpr = pmtctChildKeyExpr('e');

    $sql = "SELECT
            em.mid AS motherId,
            MAX(em.childrenInWindow) AS childrenInWindow,
            MAX(em.positiveChildren) AS positiveChildren,
            COUNT(*) AS vlTests,
            SUM(CASE WHEN v.vl_result_category = 'not suppressed' THEN 1 ELSE 0 END) AS highVlTests,
            MIN(CASE WHEN v.vl_result_category = 'not suppressed' THEN DATE(v.sample_collection_date) END) AS firstHighVlDate,
            MAX(DATE(v.sample_collection_date)) AS latestVlDate,
            SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(v.result, '') ORDER BY v.sample_collection_date DESC SEPARATOR '||'), '||', 1) AS latestVlResult,
            SUBSTRING_INDEX(GROUP_CONCAT(COALESCE(v.vl_result_category, '') ORDER BY v.sample_collection_date DESC SEPARATOR '||'), '||', 1) AS latestVlCategory
        FROM (
            SELECT TRIM(e.mother_id) AS mid,
                   COUNT(DISTINCT $childKeyExpr) AS childrenInWindow,
                   COUNT(DISTINCT CASE WHEN $positiveExpr THEN $childKeyExpr END) AS positiveChildren
            FROM form_eid e
            WHERE $whereSql
            GROUP BY TRIM(e.mother_id)
        ) em
        INNER JOIN form_vl v ON TRIM(v.patient_art_no) = em.mid
        GROUP BY em.mid
        HAVING highVlTests > 0
        ORDER BY latestVlDate DESC";

    $rows = [];
    foreach ($db->rawQuery($sql) as $r) {
        $rows[] = [
            'motherId'         => $r['motherId'] ?? '',
            'childrenInWindow' => (int) ($r['childrenInWindow'] ?? 0),
            'positiveChildren' => (int) ($r['positiveChildren'] ?? 0),
            'vlTests'          => (int) ($r['vlTests'] ?? 0),
            'highVlTests'      => (int) ($r['highVlTests'] ?? 0),
            'firstHighVlDate'  => pmtctDateOrBlank($r['firstHighVlDate'] ?? null),
            'latestVlDate'     => pmtctDateOrBlank($r['latestVlDate'] ?? null),
            'latestVlResult'   => $r['latestVlResult'] ?? '',
            'latestVlCategory' => $r['latestVlCategory'] ?? '',
        ];
    }

    header('Content-Type: application/json');
    echo json_encode(['rows' => $rows]);
    return;
}

// ---------------------------------------------------------------------------
// Shared history loaders (used by childHistory / motherHistory).
// ---------------------------------------------------------------------------

/**
 * All VL tests for a mother (ascending), matching the report's TRIM join.
 */
function pmtctLoadMotherVlTests(DatabaseService $db, string $motherId): array
{
    if ($motherId === '') {
        return [];
    }
    $rows = $db->rawQuery(
        "SELECT v.sample_code, v.remote_sample_code,
                v.sample_collection_date, v.sample_tested_datetime,
                v.result, v.result_value_absolute, v.result_value_log,
                v.vl_result_category, v.vl_test_platform,
                v.reason_for_vl_testing,
                fvl.facility_name AS testing_lab,
                fv.facility_name AS facility_name
        FROM form_vl v
        LEFT JOIN facility_details fvl ON fvl.facility_id = v.lab_id
        LEFT JOIN facility_details fv ON fv.facility_id = v.facility_id
        WHERE TRIM(v.patient_art_no) = ?
        ORDER BY v.sample_collection_date ASC, v.vl_sample_id ASC",
        [$motherId]
    ) ?: [];

    $tests = [];
    foreach ($rows as $r) {
        $tests[] = [
            'sampleCode'     => $r['sample_code'] ?? '',
            'remoteSampleCode' => $r['remote_sample_code'] ?? '',
            'collectionDate' => pmtctDateOrBlank($r['sample_collection_date'] ?? null),
            'testedDate'     => pmtctDateOrBlank($r['sample_tested_datetime'] ?? null),
            'result'         => $r['result'] ?? '',
            'resultAbsolute' => $r['result_value_absolute'] ?? '',
            'resultLog'      => $r['result_value_log'] ?? '',
            'category'       => $r['vl_result_category'] ?? '',
            'platform'       => $r['vl_test_platform'] ?? '',
            'reason'         => $r['reason_for_vl_testing'] ?? '',
            'testingLab'     => $r['testing_lab'] ?? '',
            'facility'       => $r['facility_name'] ?? '',
        ];
    }
    return $tests;
}

/**
 * All EID tests for a set of conditions (ascending), with per-test context.
 */
function pmtctLoadEidTests(DatabaseService $db, string $whereClause, array $bind): array
{
    $rows = $db->rawQuery(
        "SELECT e.eid_id, e.child_id, e.child_gender, e.child_dob,
                e.child_age, e.child_age_in_weeks, e.mother_id,
                e.sample_code, e.remote_sample_code,
                e.sample_collection_date, e.sample_tested_datetime,
                e.result, e.eid_test_platform,
                rs.status_name,
                fel.facility_name AS testing_lab,
                fe.facility_name AS facility_name
        FROM form_eid e
        LEFT JOIN facility_details fel ON fel.facility_id = e.lab_id
        LEFT JOIN facility_details fe ON fe.facility_id = e.facility_id
        LEFT JOIN r_sample_status rs ON rs.status_id = e.result_status
        WHERE $whereClause
        ORDER BY e.sample_collection_date ASC, e.eid_id ASC",
        $bind
    ) ?: [];

    $positiveTest = static function ($result): bool {
        $r = trim((string) $result);
        if ($r === '') {
            return false;
        }
        return strtolower($r) === 'positive'
            || (stripos($r, 'DETECTED') !== false && stripos($r, 'NOT DETECTED') === false);
    };

    $tests = [];
    foreach ($rows as $r) {
        $tests[] = [
            'eidId'          => (int) ($r['eid_id'] ?? 0),
            'childId'        => trim((string) ($r['child_id'] ?? '')),
            'childSex'       => $r['child_gender'] ?? '',
            'childDob'       => pmtctDateOrBlank($r['child_dob'] ?? null),
            'childAgeMonths' => $r['child_age'] ?? '',
            'motherId'       => trim((string) ($r['mother_id'] ?? '')),
            'sampleCode'     => $r['sample_code'] ?? '',
            'remoteSampleCode' => $r['remote_sample_code'] ?? '',
            'collectionDate' => pmtctDateOrBlank($r['sample_collection_date'] ?? null),
            'testedDate'     => pmtctDateOrBlank($r['sample_tested_datetime'] ?? null),
            'result'         => $r['result'] ?? '',
            'isPositive'     => $positiveTest($r['result'] ?? null),
            'status'         => $r['status_name'] ?? '',
            'platform'       => $r['eid_test_platform'] ?? '',
            'testingLab'     => $r['testing_lab'] ?? '',
            'facility'       => $r['facility_name'] ?? '',
        ];
    }
    return $tests;
}

// ---------------------------------------------------------------------------
// CHILD HISTORY - every EID test for one child (all-time), and at each test
// the mother's VL status as of that test date. Keyed by childId; records
// without a Child ID fall back to the single EID record (eidId).
// ---------------------------------------------------------------------------
if ($action === 'childHistory') {

    $childId = trim((string) ($_POST['childId'] ?? ''));
    $eidId   = (int) ($_POST['eidId'] ?? 0);

    if ($childId !== '') {
        $eidTests = pmtctLoadEidTests($db, "TRIM(e.child_id) = ?", [$childId]);
    } elseif ($eidId > 0) {
        $eidTests = pmtctLoadEidTests($db, "e.eid_id = ?", [$eidId]);
    } else {
        $eidTests = [];
    }

    // Mother: first non-empty mother ID across the child's tests.
    $motherId = '';
    $childDob = '';
    foreach ($eidTests as $t) {
        if ($motherId === '' && $t['motherId'] !== '') {
            $motherId = $t['motherId'];
        }
        if ($childDob === '' && $t['childDob'] !== '') {
            $childDob = $t['childDob'];
        }
    }

    $vlTests = pmtctLoadMotherVlTests($db, $motherId);

    $firstHighVlDate = '';
    foreach ($vlTests as $t) {
        if ($t['category'] === 'not suppressed' && $t['collectionDate'] !== '') {
            $firstHighVlDate = $t['collectionDate'];
            break;
        }
    }

    // Attach the mother's VL status as of each EID test.
    foreach ($eidTests as &$t) {
        $t['motherVlAtTest'] = pmtctVlStatusAsOf($vlTests, $t['collectionDate']);
    }
    unset($t);

    header('Content-Type: application/json');
    echo json_encode([
        'childId'              => $childId,
        'childDob'             => $childDob,
        'motherId'             => $motherId,
        'motherHighVlPreBirth' => pmtctPreBirthFlag($childDob ?: null, $firstHighVlDate ?: null),
        'eidTests'             => $eidTests,
        'vlTests'              => $vlTests,
    ]);
    return;
}

// ---------------------------------------------------------------------------
// MOTHER HISTORY - every VL test for one mother (all-time), plus every EID
// test of her linked children, each stamped with her VL status at that test.
// ---------------------------------------------------------------------------
if ($action === 'motherHistory') {

    $motherId = trim((string) ($_POST['motherId'] ?? ''));

    $vlTests  = pmtctLoadMotherVlTests($db, $motherId);
    $eidTests = ($motherId !== '')
        ? pmtctLoadEidTests($db, "TRIM(e.mother_id) = ?", [$motherId])
        : [];

    foreach ($eidTests as &$t) {
        $t['motherVlAtTest'] = pmtctVlStatusAsOf($vlTests, $t['collectionDate']);
    }
    unset($t);

    header('Content-Type: application/json');
    echo json_encode([
        'motherId' => $motherId,
        'vlTests'  => $vlTests,
        'eidTests' => $eidTests,
    ]);
    return;
}

// ---------------------------------------------------------------------------
// LINKED action - DataTables server-side payload for the line-level table.
// Honours the matchStatus filter:
//   matched    -> INNER JOIN (one row per (child, VL test) pair)
//   unmatched  -> LEFT JOIN with WHERE v.vl_sample_id IS NULL
//   all        -> LEFT JOIN
// ---------------------------------------------------------------------------

$matchStatus = $_POST['matchStatus'] ?? 'matched';
$joinType = $matchStatus === 'matched' ? 'INNER' : 'LEFT';

$where = pmtctBuildFilterConditions($_POST, $db);
if ($matchStatus === 'unmatched') {
    $where[] = "v.vl_sample_id IS NULL";
}
$whereSql = implode(' AND ', $where);

$aColumns = [
    'e.child_id',
    'e.child_gender',
    'e.child_age',
    'e.child_age_in_weeks',
    'e.mother_id',
    'e.sample_code',
    'e.remote_sample_code',
    "DATE_FORMAT(e.sample_collection_date,'%d-%b-%Y')",
    "DATE_FORMAT(e.sample_tested_datetime,'%d-%b-%Y')",
    'rs.status_name',
    'e.result',
    'e.eid_test_platform',
    'fel.facility_name',
    'v.sample_code',
    "DATE_FORMAT(v.sample_collection_date,'%d-%b-%Y')",
    "DATE_FORMAT(v.sample_tested_datetime,'%d-%b-%Y')",
    'v.result',
    'v.vl_test_platform',
    'fvl.facility_name',
];
$orderColumns = [
    'e.child_id',
    'e.child_gender',
    'e.child_age',
    'e.child_age_in_weeks',
    'e.mother_id',
    'e.sample_code',
    'e.remote_sample_code',
    'e.sample_collection_date',
    'e.sample_tested_datetime',
    'rs.status_name',
    'e.result',
    'e.eid_test_platform',
    'fel.facility_name',
    'v.sample_code',
    'v.sample_collection_date',
    'v.sample_tested_datetime',
    'v.result',
    'v.vl_test_platform',
    'fvl.facility_name',
];

$sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);
$sOrderSql = (!empty($sOrder)) ? ' ORDER BY ' . preg_replace('/\s+/', ' ', (string) $sOrder) : ' ORDER BY e.sample_collection_date DESC';

$columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? '', $aColumns);
if (!empty($columnSearch) && $columnSearch !== '') {
    $whereSql .= ' AND (' . $columnSearch . ')';
}

$selectCols = "e.eid_id, e.sample_code AS eid_sample_code,
        e.remote_sample_code AS eid_remote_sample_code,
        e.sample_collection_date AS eid_collection_date,
        e.sample_tested_datetime AS eid_tested_date,
        rs.status_name AS eid_status_name,
        e.result AS eid_result,
        e.eid_test_platform,
        fel.facility_name AS eid_testing_lab,
        e.child_id, e.child_gender, e.child_age, e.child_age_in_weeks,
        e.mother_id,
        v.vl_sample_id,
        v.sample_code AS vl_sample_code,
        v.sample_collection_date AS vl_collection_date,
        v.sample_tested_datetime AS vl_tested_date,
        v.result AS vl_result,
        v.result_value_log,
        v.result_value_absolute,
        v.vl_test_platform,
        fvl.facility_name AS vl_testing_lab";

$baseFrom = "FROM form_eid e
        $joinType JOIN form_vl v ON TRIM(e.mother_id) = TRIM(v.patient_art_no)
        LEFT JOIN facility_details fel ON fel.facility_id = e.lab_id
        LEFT JOIN facility_details fvl ON fvl.facility_id = v.lab_id
        LEFT JOIN r_sample_status rs ON rs.status_id = e.result_status
        WHERE $whereSql";

$countQuery = "SELECT COUNT(*) AS cnt $baseFrom";
$totalRow = $db->rawQueryOne($countQuery);
$iTotal = (int) ($totalRow['cnt'] ?? 0);

$sLimit = '';
if (isset($_POST['iDisplayStart']) && ($_POST['iDisplayLength'] ?? '-1') != '-1') {
    $sLimit = " LIMIT " . (int) $_POST['iDisplayStart'] . "," . (int) $_POST['iDisplayLength'];
}

$dataQuery = "SELECT $selectCols $baseFrom $sOrderSql $sLimit";
$rows = $db->rawQuery($dataQuery);

// Stash the unpaginated query for the export endpoint.
$_SESSION['pmtctCascadeQuery'] = "SELECT $selectCols $baseFrom $sOrderSql";
$_SESSION['pmtctCascadeMatchStatus'] = $matchStatus;

$out = [
    'sEcho'                => isset($_POST['sEcho']) ? (int) $_POST['sEcho'] : 0,
    'iTotalRecords'        => $iTotal,
    'iTotalDisplayRecords' => $iTotal,
    'aaData'               => [],
];

$fmtDate = function ($v): string {
    if (empty($v) || $v === '0000-00-00' || $v === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime((string) $v);
    return $ts ? date('d-M-Y', $ts) : '';
};

foreach ($rows as $r) {
    $out['aaData'][] = [
        $r['child_id'] ?? '',
        $r['child_gender'] ?? '',
        $r['child_age'] ?? '',
        $r['child_age_in_weeks'] ?? '',
        $r['mother_id'] ?? '',
        $r['eid_sample_code'] ?? '',
        $r['eid_remote_sample_code'] ?? '',
        $fmtDate($r['eid_collection_date'] ?? null),
        $fmtDate($r['eid_tested_date'] ?? null),
        $r['eid_status_name'] ?? '',
        !empty($r['eid_result']) ? ucfirst((string) $r['eid_result']) : '',
        $r['eid_test_platform'] ?? '',
        $r['eid_testing_lab'] ?? '',
        $r['vl_sample_code'] ?? '',
        $fmtDate($r['vl_collection_date'] ?? null),
        $fmtDate($r['vl_tested_date'] ?? null),
        $r['vl_result'] ?? '',
        $r['vl_test_platform'] ?? '',
        $r['vl_testing_lab'] ?? '',
    ];
}

header('Content-Type: application/json');
echo json_encode($out);
