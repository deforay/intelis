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
function pmtctBuildFilterConditions(array $post, DatabaseService $db): array
{
    $where = ["e.mother_id IS NOT NULL", "TRIM(e.mother_id) != ''"];

    if (!empty($post['sampleCollectionDate'])) {
        $range = explode("to", (string) $post['sampleCollectionDate']);
        if (count($range) === 2) {
            $start = trim($range[0]);
            $end   = trim($range[1]);
            $startSql = date('Y-m-d', strtotime($start));
            $endSql   = date('Y-m-d', strtotime($end));
            $where[] = "DATE(e.sample_collection_date) BETWEEN '" . $startSql . "' AND '" . $endSql . "'";
        }
    }

    if (!empty($post['provinceId']) && ctype_digit((string) $post['provinceId'])) {
        $where[] = "e.province_id = " . (int) $post['provinceId'];
    }

    if (!empty($post['labName']) && ctype_digit((string) $post['labName'])) {
        $where[] = "e.lab_id = " . (int) $post['labName'];
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
 * (>= 1000 cp/mL per WHO PMTCT thresholds).
 */
function pmtctHighVlExpr(string $alias = 'v'): string
{
    return "(($alias.result_value_absolute IS NOT NULL AND $alias.result_value_absolute >= 1000)"
        . " OR ($alias.result_value_log IS NOT NULL AND $alias.result_value_log >= 3))";
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

    // Children-side counts (one row per child)
    $childSql = "SELECT
            COUNT(DISTINCT e.eid_id) AS totalChildrenWithMotherId,
            COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NOT NULL THEN e.eid_id END) AS matchedChildren,
            COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NOT NULL
                                 AND e.result IS NOT NULL AND TRIM(e.result) != ''
                            THEN e.eid_id END) AS testedChildren,
            COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NOT NULL AND $positiveExpr
                            THEN e.eid_id END) AS positiveChildren,
            COUNT(DISTINCT CASE WHEN v.vl_sample_id IS NULL THEN e.eid_id END) AS unmatchedChildren
        FROM form_eid e
        LEFT JOIN form_vl v ON TRIM(e.mother_id) = TRIM(v.patient_art_no)
        WHERE $whereSql";

    // Mother / VL-side counts (one row per VL test). VL tests are NOT
    // constrained by the EID date filter on purpose: a mother may have
    // tested before the child's EID date window and we still want to
    // surface that history.
    $motherSql = "SELECT
            COUNT(DISTINCT v.patient_art_no) AS distinctMothers,
            COUNT(DISTINCT v.vl_sample_id) AS vlTests,
            COUNT(DISTINCT CASE WHEN v.result IS NOT NULL AND TRIM(v.result) != ''
                            THEN v.vl_sample_id END) AS vlTestsWithResult,
            COUNT(DISTINCT CASE WHEN v.result IS NOT NULL AND TRIM(v.result) != ''
                            THEN v.patient_art_no END) AS mothersWithResult,
            COUNT(DISTINCT CASE WHEN $highVlExpr THEN v.patient_art_no END) AS mothersHighVl
        FROM form_eid e
        INNER JOIN form_vl v ON TRIM(e.mother_id) = TRIM(v.patient_art_no)
        WHERE $whereSql";

    $childRow  = $db->rawQueryOne($childSql);
    $motherRow = $db->rawQueryOne($motherSql);

    $payload = [
        'totalChildrenWithMotherId' => (int) ($childRow['totalChildrenWithMotherId'] ?? 0),
        'matchedChildren'           => (int) ($childRow['matchedChildren'] ?? 0),
        'testedChildren'            => (int) ($childRow['testedChildren'] ?? 0),
        'positiveChildren'          => (int) ($childRow['positiveChildren'] ?? 0),
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
