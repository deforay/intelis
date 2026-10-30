<?php

use App\Utilities\PmtctCascadeUtility;
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
 * Human label for the yes / no / N/A fields the VL request form records for
 * pregnancy and breastfeeding. Anything unrecognised (including blanks, which
 * is what a request saved before the field existed carries) reads as empty
 * rather than as a "No" the lab never entered.
 */
function pmtctYesNoLabel($value): string
{
    return match (strtolower(trim((string) $value))) {
        'yes'       => _translate('Yes'),
        'no'        => _translate('No'),
        'n/a', 'na' => _translate('N/A'),
        default     => '',
    };
}

$action = $_POST['action'] ?? 'linked';

// ---------------------------------------------------------------------------
// SUMMARY action - the snapshot numbers as JSON (shared with the export)
// ---------------------------------------------------------------------------
if ($action === 'summary') {
    header('Content-Type: application/json');
    echo json_encode(PmtctCascadeUtility::summary($db, $_POST));
    return;
}

// ---------------------------------------------------------------------------
// POSITIVE CHILDREN drilldown - one row per distinct HIV-positive matched
// child in the current filter window, with the mother's VL picture alongside.
// Optional preBirthOnly=1 restricts to children whose mother had a high VL
// result in the pregnancy window before the child's date of birth.
// ---------------------------------------------------------------------------
if ($action === 'positiveChildren') {

    $whereSql     = implode(' AND ', PmtctCascadeUtility::filterConditions($_POST));
    $whereSqlE2   = implode(' AND ', PmtctCascadeUtility::filterConditions($_POST, 'e2'));
    // The mother's VL rows carry no filter of their own.
    $vlCountable    = PmtctCascadeUtility::vlRowsWhere('v');
    $highVlExpr     = PmtctCascadeUtility::highVlExpr('v');
    $positiveExpr   = PmtctCascadeUtility::eidPositiveExpr('e');
    $positiveExprE2 = PmtctCascadeUtility::eidPositiveExpr('e2');
    $childKeyExpr   = PmtctCascadeUtility::childKeyExpr('e');

    // Aggregate each mother's full VL history once (all time, on purpose:
    // pre-birth tests usually predate the EID filter window), restricted to
    // mothers of positive children in the window so the scan stays bounded.
    // "Latest" columns come from her most recent test with a result.
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
            vm.latestVlCategory,
            vm.latestPregnant,
            vm.latestBreastfeeding
        FROM form_eid e
        INNER JOIN (
            SELECT TRIM(v.patient_art_no) AS mid,
                   COUNT(*) AS vlTests,
                   SUM($highVlExpr) AS highVlTests,
                   MIN(CASE WHEN $highVlExpr THEN DATE(v.sample_collection_date) END) AS firstHighVlDate,
                   " . PmtctCascadeUtility::latestResultExpr('DATE(v.sample_collection_date)') . " AS latestVlDate,
                   " . PmtctCascadeUtility::latestResultExpr('v.result') . " AS latestVlResult,
                   " . PmtctCascadeUtility::latestResultExpr('v.vl_result_category') . " AS latestVlCategory,
                   " . PmtctCascadeUtility::latestResultExpr('v.is_patient_pregnant') . " AS latestPregnant,
                   " . PmtctCascadeUtility::latestResultExpr('v.is_patient_breastfeeding') . " AS latestBreastfeeding
            FROM form_vl v
            INNER JOIN (
                SELECT DISTINCT TRIM(e2.mother_id) AS mid
                FROM form_eid e2
                WHERE $whereSqlE2 AND $positiveExprE2
            ) pm ON pm.mid = TRIM(v.patient_art_no)
            WHERE $vlCountable
            GROUP BY TRIM(v.patient_art_no)
        ) vm ON vm.mid = TRIM(e.mother_id)
        WHERE $whereSql AND $positiveExpr
        GROUP BY childKey, TRIM(e.mother_id)
        ORDER BY latestPositiveDate DESC";

    $preBirthOnly = !empty($_POST['preBirthOnly']) && $_POST['preBirthOnly'] !== '0';

    // Every high result of these mothers, one row each. A GROUP_CONCAT of the
    // dates would be cut at group_concat_max_len and could drop the one date
    // that falls in the pregnancy window.
    $highVlDatesByMother = [];
    $highVlSql = "SELECT DISTINCT TRIM(v.patient_art_no) AS mid, DATE(v.sample_collection_date) AS highDate
        FROM form_vl v
        INNER JOIN (
            SELECT DISTINCT TRIM(e2.mother_id) AS mid
            FROM form_eid e2
            WHERE $whereSqlE2 AND $positiveExprE2
        ) pm ON pm.mid = TRIM(v.patient_art_no)
        WHERE $vlCountable AND $highVlExpr";
    foreach ($db->rawQueryGenerator($highVlSql) as $h) {
        $highVlDatesByMother[PmtctCascadeUtility::idKey($h['mid'])][] = pmtctDateOrBlank($h['highDate'] ?? null);
    }

    $rows = [];
    foreach ($db->rawQuery($sql) as $r) {
        $preBirth = PmtctCascadeUtility::preBirthFlag(
            pmtctDateOrBlank($r['childDob'] ?? null),
            $highVlDatesByMother[PmtctCascadeUtility::idKey($r['motherId'] ?? '')] ?? []
        );
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
            'latestPregnant'        => $r['latestPregnant'] ?? '',
            'latestBreastfeeding'   => $r['latestBreastfeeding'] ?? '',
            'motherHighVlPreBirth'  => $preBirth,
        ];
    }

    header('Content-Type: application/json');
    echo json_encode(['rows' => $rows]);
    return;
}

// ---------------------------------------------------------------------------
// HIGH VL MOTHERS drilldown - one row per distinct matched mother whose most
// recent VL result is high (>= 1000 cp/mL), for children in the current
// filter window. VL history is all-time by design.
// ---------------------------------------------------------------------------
if ($action === 'highVlMothers') {

    $whereSql     = implode(' AND ', PmtctCascadeUtility::filterConditions($_POST));
    $vlCountable  = PmtctCascadeUtility::vlRowsWhere('v');
    $highVlExpr   = PmtctCascadeUtility::highVlExpr('v');
    $positiveExpr = PmtctCascadeUtility::eidPositiveExpr('e');
    $childKeyExpr = PmtctCascadeUtility::childKeyExpr('e');

    $sql = "SELECT
            em.mid AS motherId,
            MAX(em.childrenInWindow) AS childrenInWindow,
            MAX(em.positiveChildren) AS positiveChildren,
            COUNT(*) AS vlTests,
            SUM($highVlExpr) AS highVlTests,
            MIN(CASE WHEN $highVlExpr THEN DATE(v.sample_collection_date) END) AS firstHighVlDate,
            " . PmtctCascadeUtility::latestResultExpr('DATE(v.sample_collection_date)') . " AS latestVlDate,
            " . PmtctCascadeUtility::latestResultExpr('v.result') . " AS latestVlResult,
            " . PmtctCascadeUtility::latestResultExpr('v.vl_result_category') . " AS latestVlCategory,
            " . PmtctCascadeUtility::latestResultExpr('v.is_patient_pregnant') . " AS latestPregnant,
            " . PmtctCascadeUtility::latestResultExpr('v.is_patient_breastfeeding') . " AS latestBreastfeeding
        FROM (
            SELECT TRIM(e.mother_id) AS mid,
                   COUNT(DISTINCT $childKeyExpr) AS childrenInWindow,
                   COUNT(DISTINCT CASE WHEN $positiveExpr THEN $childKeyExpr END) AS positiveChildren
            FROM form_eid e
            WHERE $whereSql
            GROUP BY TRIM(e.mother_id)
        ) em
        INNER JOIN form_vl v ON TRIM(v.patient_art_no) = em.mid
        WHERE $vlCountable
        GROUP BY em.mid
        HAVING latestVlCategory = 'not suppressed'
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
            'latestPregnant'   => $r['latestPregnant'] ?? '',
            'latestBreastfeeding' => $r['latestBreastfeeding'] ?? '',
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
                v.is_patient_pregnant, v.is_patient_breastfeeding,
                fvl.facility_name AS testing_lab,
                fv.facility_name AS facility_name
        FROM form_vl v
        LEFT JOIN facility_details fvl ON fvl.facility_id = v.lab_id
        LEFT JOIN facility_details fv ON fv.facility_id = v.facility_id
        WHERE TRIM(v.patient_art_no) = ?
          AND " . PmtctCascadeUtility::vlRowsWhere('v') . "
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
            'pregnant'       => $r['is_patient_pregnant'] ?? '',
            'breastfeeding'  => $r['is_patient_breastfeeding'] ?? '',
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
        WHERE ($whereClause)
          " . (PmtctCascadeUtility::labScope('e') !== '' ? 'AND ' . PmtctCascadeUtility::labScope('e') : '') . "
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
            'childId'        => trim((string) ($r['child_id'] ?? ''), ' '),
            'childSex'       => $r['child_gender'] ?? '',
            'childDob'       => pmtctDateOrBlank($r['child_dob'] ?? null),
            'childAgeMonths' => $r['child_age'] ?? '',
            'motherId'       => trim((string) ($r['mother_id'] ?? ''), ' '),
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
// CHILD HISTORY - every EID test for one child (all-time), plus the mother's
// full VL timeline. Keyed by childId; records without a Child ID fall back to
// the single EID record (eidId).
// ---------------------------------------------------------------------------
if ($action === 'childHistory') {

    // Spaces only, as SQL TRIM() removes, so the IDs compare like the join.
    $childId = trim((string) ($_POST['childId'] ?? ''), ' ');
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

    $highVlDates = [];
    foreach ($vlTests as $t) {
        if ($t['category'] === 'not suppressed' && $t['collectionDate'] !== '') {
            $highVlDates[] = $t['collectionDate'];
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'childId'              => $childId,
        'childDob'             => $childDob,
        'motherId'             => $motherId,
        'motherHighVlPreBirth' => PmtctCascadeUtility::preBirthFlag($childDob, $highVlDates),
        'eidTests'             => $eidTests,
        'vlTests'              => $vlTests,
    ]);
    return;
}

// ---------------------------------------------------------------------------
// MOTHER HISTORY - every VL test for one mother (all-time), plus every EID
// test of her linked children, grouped per child in the modal.
// ---------------------------------------------------------------------------
if ($action === 'motherHistory') {

    $motherId = trim((string) ($_POST['motherId'] ?? ''), ' ');

    $vlTests  = pmtctLoadMotherVlTests($db, $motherId);
    $eidTests = ($motherId !== '')
        ? pmtctLoadEidTests($db, "TRIM(e.mother_id) = ?", [$motherId])
        : [];

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

$where = PmtctCascadeUtility::filterConditions($_POST);
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
    'v.is_patient_pregnant',
    'v.is_patient_breastfeeding',
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
    'v.is_patient_pregnant',
    'v.is_patient_breastfeeding',
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
        v.is_patient_pregnant,
        v.is_patient_breastfeeding,
        v.result AS vl_result,
        v.result_value_log,
        v.result_value_absolute,
        v.vl_test_platform,
        fvl.facility_name AS vl_testing_lab";

// Cancelled VL samples are not a match, the same rule the snapshot counts use.
$baseFrom = "FROM form_eid e
        $joinType JOIN form_vl v ON TRIM(e.mother_id) = TRIM(v.patient_art_no)
            AND " . PmtctCascadeUtility::vlRowsWhere('v') . "
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
        pmtctYesNoLabel($r['is_patient_pregnant'] ?? null),
        pmtctYesNoLabel($r['is_patient_breastfeeding'] ?? null),
        $r['vl_result'] ?? '',
        $r['vl_test_platform'] ?? '',
        $r['vl_testing_lab'] ?? '',
    ];
}

header('Content-Type: application/json');
echo json_encode($out);
