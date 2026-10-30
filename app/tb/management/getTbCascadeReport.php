<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\SampleCountUtility;
use App\Utilities\SampleRejectionUtility;
use App\Utilities\TestedSampleRule;
use Psr\Http\Message\ServerRequestInterface;

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/*
 * Counting rules, shared by every action below so the cards, the funnel and
 * the tables agree:
 *   - Final result entered: form_tb.result has text, or is_result_finalized is
 *     'yes'. The two are written by different code paths and can disagree, and
 *     in Rwanda a result awaiting approval sits at status 6 with the flag set.
 *   - Rejected: the flag or the status (SampleRejectionUtility).
 *   - Tested: TestedSampleRule, less rejected samples. A tb_tests row alone is
 *     not a test: a card with a lab and no result already creates one.
 *   - Stage: each sample is in exactly one stage (tbcStageExpr), so the parts of
 *     "In Pipeline" never overlap.
 */

/** SQL: the sample has a final result entered. */
function tbcFinalExpr(): string
{
    return "(NULLIF(TRIM(f.result), '') IS NOT NULL OR f.is_result_finalized = 'yes')";
}

/**
 * SQL: the sample is rejected. Never NULL: is_sample_rejected can be NULL, and
 * NOT of a NULL predicate would drop the sample from every stage it guards.
 */
function tbcRejectedExpr(): string
{
    return 'COALESCE(' . SampleRejectionUtility::sqlPredicate('f') . ', FALSE)';
}

/** SQL: the sample was tested. */
function tbcTestedExpr(): string
{
    return '(' . TestedSampleRule::sql('tb', 'f') . ' AND NOT ' . tbcRejectedExpr() . ')';
}

/** SQL: the one stage a sample is in now. */
function tbcStageExpr(): string
{
    return "CASE
            WHEN " . tbcRejectedExpr() . " THEN 'rejected'
            WHEN f.result_status = " . SAMPLE_STATUS\TEST_FAILED . " THEN 'failed'
            WHEN f.result_status = " . SAMPLE_STATUS\ACCEPTED . " THEN 'accepted'
            WHEN f.result_status IN (" . SAMPLE_STATUS\EXPIRED . ", " . SAMPLE_STATUS\LOST_OR_MISSING . ", "
        . SAMPLE_STATUS\NO_RESULT . ") THEN 'closed'
            WHEN f.result_status = " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . " THEN 'atCollectionSite'
            WHEN f.result_status = " . SAMPLE_STATUS\REFERRED . " THEN 'referred'
            WHEN " . tbcFinalExpr() . " THEN 'awaitingApproval'
            ELSE 'atLab'
        END";
}

/**
 * WHERE conditions from the POSTed filters, against `form_tb f`.
 */
function tbcBuildFilterConditions(array $post, DatabaseService $db): array
{
    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    $where = [SampleCountUtility::countableWhere('f')];

    // A LIS has not received a sample still at the collection site.
    if (!$general->isSTSInstance()) {
        $where[] = "f.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
    } elseif (!empty($_SESSION['facilityMap'])) {
        $where[] = "f.facility_id IN (" . $_SESSION['facilityMap'] . ")";
    }

    if ($labScope = $general->labScopeWhere('f')) {
        $where[] = $labScope;
    }

    if (!empty($post['sampleCollectionDate'])) {
        $range = explode("to", (string) $post['sampleCollectionDate']);
        if (count($range) === 2) {
            $startSql = date('Y-m-d', strtotime(trim($range[0])));
            $endSql   = date('Y-m-d', strtotime(trim($range[1])));
            $where[] = "DATE(f.sample_collection_date) BETWEEN '" . $startSql . "' AND '" . $endSql . "'";
        }
    }

    if (!empty($post['provinceId']) && ctype_digit((string) $post['provinceId'])) {
        $where[] = "f.province_id = " . (int) $post['provinceId'];
    }

    if (!empty($post['labName']) && ctype_digit((string) $post['labName'])) {
        $where[] = "f.lab_id = " . (int) $post['labName'];
    }

    if (!empty($post['facilityName'])) {
        $facilityCsv = preg_replace('/[^0-9,]/', '', implode(',', (array) $post['facilityName']));
        $facilityCsv = trim((string) preg_replace('/,+/', ',', (string) $facilityCsv), ',');
        if ($facilityCsv !== '') {
            $where[] = "f.facility_id IN (" . $facilityCsv . ")";
        }
    }

    // The same rule as the Final Result Entered stage, not the flag alone.
    if (($post['finalized'] ?? '') === 'yes') {
        $where[] = tbcFinalExpr();
    } elseif (($post['finalized'] ?? '') === 'no') {
        $where[] = "NOT " . tbcFinalExpr();
    }

    // EXISTS, not a join: a sample with two tests of the type counts once.
    $testType = tbcTestTypeFilter($post);
    if ($testType !== null) {
        $where[] = "EXISTS (SELECT 1 FROM tb_tests tt_filter WHERE tt_filter.tb_id = f.tb_id"
            . " AND tt_filter.test_type = '" . $db->escape($testType) . "')";
    }

    return $where;
}

/** The selected test type, or null for any. */
function tbcTestTypeFilter(array $post): ?string
{
    $testType = trim((string) ($post['testType'] ?? ''));
    return $testType === '' ? null : $testType;
}

$action = $_POST['action'] ?? 'summary';

// ---------------------------------------------------------------------------
// SUMMARY action — sample-grain KPIs + funnel counts
// ---------------------------------------------------------------------------
if ($action === 'summary') {
    $whereSql = implode(' AND ', tbcBuildFilterConditions($_POST, $db));
    $referred = "(lr.last_ref IS NOT NULL OR f.referred_to_lab_id IS NOT NULL)";
    $referredOn = "COALESCE(lr.last_ref, f.samples_referred_datetime)";
    $receivedAfterReferral = "(f.sample_received_at_lab_datetime IS NOT NULL
        AND ($referredOn IS NULL OR f.sample_received_at_lab_datetime >= $referredOn))";
    $testedAfterReferral = "(f.sample_tested_datetime IS NOT NULL
        AND ($referredOn IS NULL OR f.sample_tested_datetime >= $referredOn))";

    // One row per sample with its classifications, then totals. Each funnel
    // stage is part of the one before it.
    $sql = "SELECT
            COUNT(*) AS total,
            SUM(s.receivedAtLab) AS receivedAtLab,
            SUM(s.tested) AS tested,
            SUM(s.resultEntered) AS resultEntered,
            SUM(s.resultEntered AND s.stage = 'accepted') AS accepted,
            SUM(s.resultEntered AND s.stage = 'accepted' AND s.sentOut) AS dispatchedOrPrinted,
            SUM(s.stage = 'atCollectionSite') AS atCollectionSite,
            SUM(s.stage = 'atLab') AS atTestingLab,
            SUM(s.stage = 'awaitingApproval') AS awaitingApproval,
            SUM(s.stage = 'referred') AS referredInTransit,
            SUM(s.stage = 'rejected') AS rejected,
            SUM(s.stage = 'failed') AS testFailed,
            SUM(s.stage = 'closed') AS closed,
            SUM(s.stage = 'accepted' AND NOT s.resultEntered) AS acceptedWithoutResultEntered,
            SUM(s.dispatched) AS dispatched,
            SUM(s.printed) AS printed,
            SUM(s.referred) AS referred,
            SUM(s.referred AND s.receivedAfterReferral) AS referralReceived,
            SUM(s.referred AND s.receivedAfterReferral AND s.testedAfterReferral) AS referralTested,
            SUM(s.referred AND s.receivedAfterReferral AND s.testedAfterReferral
                AND s.resultEntered AND s.stage = 'accepted') AS referralAccepted,
            AVG(s.daysCollToRecv) AS avgDaysCollToRecv,
            AVG(s.daysRecvToTested) AS avgDaysRecvToTested,
            COUNT(DISTINCT s.lab_id) AS distinctLabs,
            COUNT(DISTINCT s.facility_id) AS distinctFacilities
        FROM (
            SELECT
                f.lab_id,
                f.facility_id,
                " . tbcStageExpr() . " AS stage,
                (f.sample_received_at_lab_datetime IS NOT NULL OR " . tbcTestedExpr() . ") AS receivedAtLab,
                " . tbcTestedExpr() . " AS tested,
                (" . tbcTestedExpr() . " AND " . tbcFinalExpr() . ") AS resultEntered,
                (f.result_dispatched_datetime IS NOT NULL) AS dispatched,
                (f.result_printed_datetime IS NOT NULL) AS printed,
                (f.result_dispatched_datetime IS NOT NULL OR f.result_printed_datetime IS NOT NULL) AS sentOut,
                $referred AS referred,
                $receivedAfterReferral AS receivedAfterReferral,
                $testedAfterReferral AS testedAfterReferral,
                CASE WHEN f.sample_received_at_lab_datetime IS NOT NULL AND f.sample_collection_date IS NOT NULL
                     THEN GREATEST(DATEDIFF(f.sample_received_at_lab_datetime, f.sample_collection_date), 0)
                END AS daysCollToRecv,
                CASE WHEN f.sample_tested_datetime IS NOT NULL AND f.sample_received_at_lab_datetime IS NOT NULL
                     THEN GREATEST(DATEDIFF(f.sample_tested_datetime, f.sample_received_at_lab_datetime), 0)
                END AS daysRecvToTested
            FROM form_tb f
            LEFT JOIN (
                SELECT tb_id, MAX(referred_on_datetime) AS last_ref
                FROM tb_referral_history
                GROUP BY tb_id
            ) lr ON lr.tb_id = f.tb_id
            WHERE $whereSql
        ) s";

    $row = $db->rawQueryOne($sql) ?: [];

    $toNum = function ($v) {
        return ($v === null || !is_numeric($v)) ? null : (float) $v;
    };
    $int = fn(string $key): int => (int) ($row[$key] ?? 0);

    $payload = [
        'total'              => $int('total'),
        'receivedAtLab'      => $int('receivedAtLab'),
        'tested'             => $int('tested'),
        'resultEntered'      => $int('resultEntered'),
        'accepted'           => $int('accepted'),
        'dispatchedOrPrinted' => $int('dispatchedOrPrinted'),
        'atCollectionSite'   => $int('atCollectionSite'),
        'atTestingLab'       => $int('atTestingLab'),
        'awaitingApproval'   => $int('awaitingApproval'),
        'referredInTransit'  => $int('referredInTransit'),
        'inPipeline'         => $int('atCollectionSite') + $int('atTestingLab')
            + $int('awaitingApproval') + $int('referredInTransit'),
        'rejected'           => $int('rejected'),
        'testFailed'         => $int('testFailed'),
        'closed'             => $int('closed'),
        'acceptedWithoutResultEntered' => $int('acceptedWithoutResultEntered'),
        'dispatched'         => $int('dispatched'),
        'printed'            => $int('printed'),
        'referred'           => $int('referred'),
        'referralReceived'   => $int('referralReceived'),
        'referralTested'     => $int('referralTested'),
        'referralAccepted'   => $int('referralAccepted'),
        'avgDaysCollToRecv'  => $toNum($row['avgDaysCollToRecv'] ?? null),
        'avgDaysRecvToTested' => $toNum($row['avgDaysRecvToTested'] ?? null),
        'distinctLabs'       => $int('distinctLabs'),
        'distinctFacilities' => $int('distinctFacilities'),
    ];

    header('Content-Type: application/json');
    echo json_encode($payload);
    return;
}

// ---------------------------------------------------------------------------
// PER-LAB action — DataTables server-side, one row per lab
// Sample-grain aggregation (originator samples), plus test-grain counts
// from tb_tests for tests-performed columns.
// ---------------------------------------------------------------------------
if ($action === 'per-lab') {
    $whereSql = implode(' AND ', tbcBuildFilterConditions($_POST, $db));

    $aColumns = [
        'fl.facility_name',
        'originatorSamples',
        'referredInSamples',
        'testsPerformed',
        'accepted',
        'rejected',
        'failed',
        'pendingAtLab',
        'avgDaysCollToRecv',
        'avgDaysRecvToTested',
    ];
    $orderColumns = $aColumns;

    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);
    $sOrderSql = (!empty($sOrder)) ? ' ORDER BY ' . preg_replace('/\s+/', ' ', (string) $sOrder) : ' ORDER BY originatorSamples DESC';

    $sLimit = '';
    if (isset($_POST['iDisplayStart']) && ($_POST['iDisplayLength'] ?? '-1') != '-1') {
        $sLimit = " LIMIT " . (int) $_POST['iDisplayStart'] . "," . (int) $_POST['iDisplayLength'];
    }

    // Tests with a result, by the lab that ran them, for the samples in scope.
    $testTypeSql = '';
    $testType = tbcTestTypeFilter($_POST);
    if ($testType !== null) {
        $testTypeSql = " AND t.test_type = '" . $db->escape($testType) . "'";
    }

    // Inner subquery: one row per sample × lab in scope, with its stage.
    // Originator = the sample's primary lab_id. Referred-in = a lab the sample
    // was referred to.
    $stage = tbcStageExpr();
    $final = tbcFinalExpr();
    $perLabSql = "
        SELECT
            fl.facility_id AS lab_id,
            fl.facility_name,
            SUM(CASE WHEN x.lab_role = 'originator' THEN 1 ELSE 0 END) AS originatorSamples,
            SUM(CASE WHEN x.lab_role = 'referred-in' THEN 1 ELSE 0 END) AS referredInSamples,
            COALESCE(MAX(tt.testsPerformed), 0) AS testsPerformed,
            SUM(CASE WHEN x.stage = 'accepted' AND x.isFinal THEN 1 ELSE 0 END) AS accepted,
            SUM(CASE WHEN x.stage = 'rejected' THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN x.stage = 'failed' THEN 1 ELSE 0 END) AS failed,
            SUM(CASE WHEN x.stage IN ('atLab', 'awaitingApproval') THEN 1 ELSE 0 END) AS pendingAtLab,
            AVG(x.daysCollToRecv) AS avgDaysCollToRecv,
            AVG(x.daysRecvToTested) AS avgDaysRecvToTested
        FROM (
            SELECT
                f.tb_id,
                f.lab_id AS lab_id,
                'originator' AS lab_role,
                $stage AS stage,
                $final AS isFinal,
                CASE WHEN f.sample_received_at_lab_datetime IS NOT NULL AND f.sample_collection_date IS NOT NULL
                     THEN GREATEST(DATEDIFF(f.sample_received_at_lab_datetime, f.sample_collection_date), 0) END AS daysCollToRecv,
                CASE WHEN f.sample_tested_datetime IS NOT NULL AND f.sample_received_at_lab_datetime IS NOT NULL
                     THEN GREATEST(DATEDIFF(f.sample_tested_datetime, f.sample_received_at_lab_datetime), 0) END AS daysRecvToTested
            FROM form_tb f
            WHERE $whereSql AND f.lab_id IS NOT NULL
            UNION
            SELECT DISTINCT
                f.tb_id,
                hop.to_lab_id AS lab_id,
                'referred-in' AS lab_role,
                $stage AS stage,
                $final AS isFinal,
                NULL AS daysCollToRecv,
                CASE WHEN f.sample_tested_datetime IS NOT NULL AND hop.referred_on IS NOT NULL
                     THEN GREATEST(DATEDIFF(f.sample_tested_datetime, hop.referred_on), 0) END AS daysRecvToTested
            FROM form_tb f
            INNER JOIN (
                SELECT tb_id, to_lab_id, referred_on_datetime AS referred_on
                FROM tb_referral_history
                UNION
                SELECT ft.tb_id, ft.referred_to_lab_id, ft.samples_referred_datetime
                FROM form_tb ft
                WHERE ft.referred_to_lab_id IS NOT NULL
                  AND NOT EXISTS (SELECT 1 FROM tb_referral_history h WHERE h.tb_id = ft.tb_id)
            ) hop ON hop.tb_id = f.tb_id AND hop.to_lab_id IS NOT NULL
            WHERE $whereSql
        ) x
        INNER JOIN facility_details fl ON fl.facility_id = x.lab_id
        LEFT JOIN (
            SELECT t.lab_id, COUNT(*) AS testsPerformed
            FROM tb_tests t
            INNER JOIN form_tb f ON f.tb_id = t.tb_id
            WHERE $whereSql
              AND t.lab_id IS NOT NULL
              AND NULLIF(TRIM(t.test_result), '') IS NOT NULL
              $testTypeSql
            GROUP BY t.lab_id
        ) tt ON tt.lab_id = fl.facility_id
        GROUP BY fl.facility_id, fl.facility_name
    ";

    $countQuery = "SELECT COUNT(*) AS cnt FROM ( $perLabSql ) c";
    $totalRow = $db->rawQueryOne($countQuery);
    $iTotal = (int) ($totalRow['cnt'] ?? 0);

    $dataQuery = $perLabSql . $sOrderSql . $sLimit;
    $rows = $db->rawQuery($dataQuery);

    $out = [
        'sEcho'                => isset($_POST['sEcho']) ? (int) $_POST['sEcho'] : 0,
        'iTotalRecords'        => $iTotal,
        'iTotalDisplayRecords' => $iTotal,
        'aaData'               => [],
    ];

    $fmtAvg = function ($v) {
        return ($v === null || $v === '') ? '' : number_format((float) $v, 1);
    };

    foreach ($rows as $r) {
        $out['aaData'][] = [
            htmlspecialchars((string) ($r['facility_name'] ?? '')),
            (int) ($r['originatorSamples'] ?? 0),
            (int) ($r['referredInSamples'] ?? 0),
            (int) ($r['testsPerformed'] ?? 0),
            (int) ($r['accepted'] ?? 0),
            (int) ($r['rejected'] ?? 0),
            (int) ($r['failed'] ?? 0),
            (int) ($r['pendingAtLab'] ?? 0),
            $fmtAvg($r['avgDaysCollToRecv'] ?? null),
            $fmtAvg($r['avgDaysRecvToTested'] ?? null),
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($out);
    return;
}

// ---------------------------------------------------------------------------
// REFERRAL-MATRIX action — DataTables server-side, one row per (from_lab, to_lab)
// pair. Empty whenever the period has no referrals.
// ---------------------------------------------------------------------------
if ($action === 'referral-matrix') {
    $whereSql = implode(' AND ', tbcBuildFilterConditions($_POST, $db));

    $aColumns = [
        'frm.facility_name',
        'tol.facility_name',
        'referred',
        'awaitingReceipt',
        'receivedNotTested',
        'acceptedAtTo',
        'rejFailAtTo',
    ];
    $orderColumns = $aColumns;
    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);
    $sOrderSql = (!empty($sOrder)) ? ' ORDER BY ' . preg_replace('/\s+/', ' ', (string) $sOrder) : ' ORDER BY referred DESC';

    $sLimit = '';
    if (isset($_POST['iDisplayStart']) && ($_POST['iDisplayLength'] ?? '-1') != '-1') {
        $sLimit = " LIMIT " . (int) $_POST['iDisplayStart'] . "," . (int) $_POST['iDisplayLength'];
    }

    /* Each row in tb_referral_history is one hop; samples that traverse
       Lab A -> B -> C contribute two pairs (A-B and B-C). The form_tb
       pair covers only samples with no history rows: those referred before
       the history table existed.
       Received and tested at the receiving lab mean on or after the
       referral date, the same rule as the summary's referral branch. */
    $stage = tbcStageExpr();
    $final = tbcFinalExpr();
    $received = "(f.sample_received_at_lab_datetime IS NOT NULL
        AND (hop.referred_on IS NULL OR f.sample_received_at_lab_datetime >= hop.referred_on))";
    $tested = "(f.sample_tested_datetime IS NOT NULL
        AND (hop.referred_on IS NULL OR f.sample_tested_datetime >= hop.referred_on))";
    $matrixSql = "
        SELECT
            frm.facility_id AS from_lab_id,
            frm.facility_name AS from_lab_name,
            tol.facility_id AS to_lab_id,
            tol.facility_name AS to_lab_name,
            COUNT(DISTINCT hop.tb_id) AS referred,
            COUNT(DISTINCT CASE WHEN NOT $received THEN f.tb_id END) AS awaitingReceipt,
            COUNT(DISTINCT CASE WHEN $received AND NOT $tested
                AND ($stage) NOT IN ('accepted', 'rejected', 'failed') THEN f.tb_id END) AS receivedNotTested,
            COUNT(DISTINCT CASE WHEN ($stage) = 'accepted' AND $final THEN f.tb_id END) AS acceptedAtTo,
            COUNT(DISTINCT CASE WHEN ($stage) IN ('rejected', 'failed') THEN f.tb_id END) AS rejFailAtTo
        FROM (
            SELECT tb_id, from_lab_id, to_lab_id, referred_on_datetime AS referred_on FROM tb_referral_history
            UNION
            SELECT ft.tb_id, ft.referred_by_lab_id, ft.referred_to_lab_id, ft.samples_referred_datetime
            FROM form_tb ft
            WHERE ft.referred_by_lab_id IS NOT NULL AND ft.referred_to_lab_id IS NOT NULL
              AND NOT EXISTS (SELECT 1 FROM tb_referral_history h WHERE h.tb_id = ft.tb_id)
        ) hop
        INNER JOIN form_tb f ON f.tb_id = hop.tb_id
        INNER JOIN facility_details frm ON frm.facility_id = hop.from_lab_id
        INNER JOIN facility_details tol ON tol.facility_id = hop.to_lab_id
        WHERE $whereSql
        GROUP BY frm.facility_id, frm.facility_name, tol.facility_id, tol.facility_name
    ";

    $countQuery = "SELECT COUNT(*) AS cnt FROM ( $matrixSql ) c";
    $totalRow = $db->rawQueryOne($countQuery);
    $iTotal = (int) ($totalRow['cnt'] ?? 0);

    $rows = $db->rawQuery($matrixSql . $sOrderSql . $sLimit);

    $out = [
        'sEcho'                => isset($_POST['sEcho']) ? (int) $_POST['sEcho'] : 0,
        'iTotalRecords'        => $iTotal,
        'iTotalDisplayRecords' => $iTotal,
        'aaData'               => [],
    ];
    foreach ($rows as $r) {
        $out['aaData'][] = [
            htmlspecialchars((string) ($r['from_lab_name'] ?? '')),
            htmlspecialchars((string) ($r['to_lab_name'] ?? '')),
            (int) ($r['referred'] ?? 0),
            (int) ($r['awaitingReceipt'] ?? 0),
            (int) ($r['receivedNotTested'] ?? 0),
            (int) ($r['acceptedAtTo'] ?? 0),
            (int) ($r['rejFailAtTo'] ?? 0),
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($out);
    return;
}

// ---------------------------------------------------------------------------
// DETAIL action — DataTables server-side, one row per (sample × test × lab)
// Joins form_tb to tb_tests; samples without tb_tests rows still appear once
// using the sample's primary lab so the explorer mirrors the dashboard total.
// ---------------------------------------------------------------------------
if ($action === 'detail') {
    $whereSql = implode(' AND ', tbcBuildFilterConditions($_POST, $db));

    // With a test type selected, show only that type's test rows.
    $testJoinSql = '';
    $testType = tbcTestTypeFilter($_POST);
    if ($testType !== null) {
        $testJoinSql = " AND t.test_type = '" . $db->escape($testType) . "'";
    }

    $aColumns = [
        'f.sample_code',
        'fc.facility_name',
        'fl.facility_name',
        'fp.facility_name',
        'lab_role_sort',
        't.test_type',
        't.test_result',
        'rs.status_name',
        "DATE_FORMAT(f.sample_collection_date,'%d-%b-%Y')",
        "DATE_FORMAT(COALESCE(t.sample_received_at_lab_datetime, f.sample_received_at_lab_datetime),'%d-%b-%Y')",
        "DATE_FORMAT(COALESCE(t.sample_tested_datetime, f.sample_tested_datetime),'%d-%b-%Y')",
        'f.result',
    ];
    $orderColumns = [
        'f.sample_code',
        'fc.facility_name',
        'fl.facility_name',
        'fp.facility_name',
        'lab_role_sort',
        't.test_type',
        't.test_result',
        'rs.status_name',
        'f.sample_collection_date',
        'COALESCE(t.sample_received_at_lab_datetime, f.sample_received_at_lab_datetime)',
        'COALESCE(t.sample_tested_datetime, f.sample_tested_datetime)',
        'f.result',
    ];

    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);
    $sOrderSql = (!empty($sOrder)) ? ' ORDER BY ' . preg_replace('/\s+/', ' ', (string) $sOrder) : ' ORDER BY f.sample_collection_date DESC';

    // lab_role_sort is not a searchable text column.
    $searchColumns = array_values(array_filter($aColumns, fn($c) => $c !== 'lab_role_sort'));
    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? '', $searchColumns);
    if (!empty($columnSearch) && $columnSearch !== '') {
        $whereSql .= ' AND (' . $columnSearch . ')';
    }

    // Performing lab is the tb_tests.lab_id when present, else falls back to
    // the sample's primary lab_id. Lab role: originator when performing
    // matches form_tb.lab_id, referral otherwise.
    $selectCols = "
        f.tb_id, f.sample_code,
        f.sample_collection_date,
        f.sample_received_at_lab_datetime AS f_received,
        f.sample_tested_datetime AS f_tested,
        f.result AS final_result,
        f.result_status,
        fc.facility_name AS collection_site,
        fl.facility_name AS primary_lab,
        COALESCE(t.lab_id, f.lab_id) AS performing_lab_id,
        fp.facility_name AS performing_lab,
        t.test_type, t.test_result,
        t.sample_received_at_lab_datetime AS t_received,
        t.sample_tested_datetime AS t_tested,
        rs.status_name,
        CASE
            WHEN t.lab_id IS NULL THEN 0
            WHEN t.lab_id = f.lab_id THEN 0
            ELSE 1
        END AS lab_role_sort
    ";

    $baseFrom = "
        FROM form_tb f
        LEFT JOIN tb_tests t ON t.tb_id = f.tb_id $testJoinSql
        LEFT JOIN facility_details fc ON fc.facility_id = f.facility_id
        LEFT JOIN facility_details fl ON fl.facility_id = f.lab_id
        LEFT JOIN facility_details fp ON fp.facility_id = COALESCE(t.lab_id, f.lab_id)
        LEFT JOIN r_sample_status rs ON rs.status_id = f.result_status
        WHERE $whereSql
    ";

    $countQuery = "SELECT COUNT(*) AS cnt $baseFrom";
    $totalRow = $db->rawQueryOne($countQuery);
    $iTotal = (int) ($totalRow['cnt'] ?? 0);

    $sLimit = '';
    if (isset($_POST['iDisplayStart']) && ($_POST['iDisplayLength'] ?? '-1') != '-1') {
        $sLimit = " LIMIT " . (int) $_POST['iDisplayStart'] . "," . (int) $_POST['iDisplayLength'];
    }

    $rows = $db->rawQuery("SELECT $selectCols $baseFrom $sOrderSql $sLimit");

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
    $roleLabels = [0 => _translate('Originator'), 1 => _translate('Referral')];
    $esc = fn($v): string => htmlspecialchars((string) ($v ?? ''));

    foreach ($rows as $r) {
        $out['aaData'][] = [
            $esc($r['sample_code'] ?? ''),
            $esc($r['collection_site'] ?? ''),
            $esc($r['primary_lab'] ?? ''),
            $esc($r['performing_lab'] ?? ''),
            $esc($roleLabels[(int) ($r['lab_role_sort'] ?? 0)] ?? ''),
            $esc($r['test_type'] ?? ''),
            $esc($r['test_result'] ?? ''),
            $esc($r['status_name'] ?? ''),
            $fmtDate($r['sample_collection_date'] ?? null),
            $fmtDate($r['t_received'] ?? ($r['f_received'] ?? null)),
            $fmtDate($r['t_tested'] ?? ($r['f_tested'] ?? null)),
            $esc($r['final_result'] ?? ''),
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($out);
    return;
}

// Unknown action — empty payload.
header('Content-Type: application/json');
echo json_encode([]);
