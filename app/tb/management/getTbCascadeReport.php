<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/**
 * Emit a JSON error and log the actual failing SQL + DB error so we can
 * diagnose without exposing internals to the client. The framework's
 * generic 500 handler hides $db->getLastQuery(), which is what we need
 * to see for syntax errors like "near ''".
 */
function tbcReportError(Throwable $e, DatabaseService $db, string $action, ?string $attemptedSql = null): void
{
    $errorId = 'TBC-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    LoggerUtility::log('error', 'TB Cascade Report error', [
        'error_id'      => $errorId,
        'action'        => $action,
        'message'       => $e->getMessage(),
        'attempted_sql' => $attemptedSql,
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'post'          => $_POST,
        'file'          => $e->getFile(),
        'line'          => $e->getLine(),
        'trace'         => $e->getTraceAsString(),
    ]);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode([
        'error' => true,
        'action' => $action,
        'error_id' => $errorId,
        'message' => 'TB Cascade query failed. See server logs for ' . $errorId . '.',
    ]);
}

/**
 * Build the shared WHERE clause fragment from POSTed filters.
 * Operates against an aliased `form_tb f` plus optional `facility_details fl`
 * for the testing lab, `facility_details fc` for the collection site.
 */
function tbcBuildFilterConditions(array $post): array
{
    $where = [];

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
        $facilityCsv = preg_replace('/[^0-9,]/', '', (string) $post['facilityName']);
        if ($facilityCsv !== '') {
            $where[] = "f.facility_id IN (" . $facilityCsv . ")";
        }
    }

    if (isset($post['finalized']) && ($post['finalized'] === 'yes' || $post['finalized'] === 'no')) {
        $where[] = "f.is_result_finalized = '" . $post['finalized'] . "'";
    }

    // STS instance scoping (mirrors getSampleResult.php logic)
    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    if (!$general->isSTSInstance()) {
        // On non-STS, exclude RECEIVED_AT_CLINIC (status 9) and CANCELLED (12)
        $where[] = "f.result_status NOT IN (" . SAMPLE_STATUS\CANCELLED . ", " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . ")";
    } else {
        $where[] = "f.result_status != " . SAMPLE_STATUS\CANCELLED;
        if (!empty($_SESSION['facilityMap'])) {
            $where[] = "f.facility_id IN (" . $_SESSION['facilityMap'] . ")";
        }
    }

    return $where;
}

/**
 * Optional test_type filter applied against a JOIN on tb_tests.
 * Returns the SQL fragment to AND into the WHERE clause and the JOIN
 * needed (only added when a test_type filter is in play, to keep the
 * sample-grain queries fast when no filter is selected).
 */
function tbcTestTypeJoinAndWhere(array $post): array
{
    if (empty($post['testType'])) {
        return ['', ''];
    }
    $tt = $post['testType'];
    $ttEsc = str_replace("'", "''", (string) $tt);
    $join = " INNER JOIN tb_tests tt_filter ON tt_filter.tb_id = f.tb_id AND tt_filter.test_type = '" . $ttEsc . "' ";
    return [$join, ''];
}

$action = $_POST['action'] ?? 'summary';

// ---------------------------------------------------------------------------
// SUMMARY action — sample-grain KPIs + funnel counts
// ---------------------------------------------------------------------------
if ($action === 'summary') { try {
    $where = tbcBuildFilterConditions($_POST);
    [$ttJoin, $_unused] = tbcTestTypeJoinAndWhere($_POST);
    $whereSql = !empty($where) ? implode(' AND ', $where) : '1=1';

    // Cascade stage definitions (per business rules):
    //   Tested            = sample has at least one row in tb_tests
    //   Result Entered    = a final result is available — either form_tb.result has text,
    //                       OR is_result_finalized='yes'. Reading both signals is more
    //                       honest than just the flag: the two are written by different
    //                       code paths and can disagree (status-machine vs UI flag).
    //   Awaiting Approval = result available + status not yet ACCEPTED and not rejected/failed
    //   Accepted          = result available + status = ACCEPTED
    // In Rwanda PENDING_APPROVAL (8) is unused — awaiting-approval samples live
    // at status=6 plus the flag, so reading the flag alone is unreliable.
    $sql = "SELECT
            COUNT(DISTINCT f.tb_id) AS total,
            SUM(CASE WHEN f.result_status = " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . " THEN 1 ELSE 0 END) AS atCollectionSite,
            SUM(CASE WHEN f.result_status = " . SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB . " THEN 1 ELSE 0 END) AS atTestingLab,
            SUM(CASE WHEN EXISTS (SELECT 1 FROM tb_tests tt WHERE tt.tb_id = f.tb_id) THEN 1 ELSE 0 END) AS tested,
            SUM(CASE WHEN ((f.result IS NOT NULL AND TRIM(f.result) <> '') OR f.is_result_finalized = 'yes')
                          AND f.result_status NOT IN (" . SAMPLE_STATUS\ACCEPTED . ", " . SAMPLE_STATUS\REJECTED . ", " . SAMPLE_STATUS\TEST_FAILED . ")
                          THEN 1 ELSE 0 END) AS awaitingApproval,
            SUM(CASE WHEN (f.result IS NOT NULL AND TRIM(f.result) <> '') OR f.is_result_finalized = 'yes' THEN 1 ELSE 0 END) AS resultEntered,
            SUM(CASE WHEN ((f.result IS NOT NULL AND TRIM(f.result) <> '') OR f.is_result_finalized = 'yes')
                          AND f.result_status = " . SAMPLE_STATUS\ACCEPTED . " THEN 1 ELSE 0 END) AS accepted,
            SUM(CASE WHEN f.result_status = " . SAMPLE_STATUS\REJECTED . " THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN f.result_status = " . SAMPLE_STATUS\TEST_FAILED . " THEN 1 ELSE 0 END) AS testFailed,
            SUM(CASE WHEN lr.last_ref IS NOT NULL OR f.referred_to_lab_id IS NOT NULL THEN 1 ELSE 0 END) AS referred,
            SUM(CASE WHEN f.result_status = " . SAMPLE_STATUS\ACCEPTED . "
                          AND (f.result IS NULL OR TRIM(f.result) = '')
                          AND f.is_result_finalized <> 'yes' THEN 1 ELSE 0 END) AS acceptedWithoutResultEntered,
            SUM(CASE WHEN f.result_dispatched_datetime IS NOT NULL THEN 1 ELSE 0 END) AS dispatched,
            SUM(CASE WHEN f.result_printed_datetime IS NOT NULL THEN 1 ELSE 0 END) AS printed,
            AVG(CASE WHEN f.sample_received_at_lab_datetime IS NOT NULL AND f.sample_collection_date IS NOT NULL
                     THEN GREATEST(DATEDIFF(f.sample_received_at_lab_datetime, f.sample_collection_date), 0)
                     END) AS avgDaysCollToRecv,
            AVG(CASE WHEN f.sample_tested_datetime IS NOT NULL AND f.sample_received_at_lab_datetime IS NOT NULL
                     THEN GREATEST(DATEDIFF(f.sample_tested_datetime, f.sample_received_at_lab_datetime), 0)
                     END) AS avgDaysRecvToTested,
            COUNT(DISTINCT f.lab_id) AS distinctLabs,
            COUNT(DISTINCT f.facility_id) AS distinctFacilities,
            SUM(CASE WHEN (lr.last_ref IS NOT NULL OR f.referred_to_lab_id IS NOT NULL)
                          AND f.sample_received_at_lab_datetime IS NOT NULL
                          AND f.sample_received_at_lab_datetime >= COALESCE(lr.last_ref, f.samples_referred_datetime)
                     THEN 1 ELSE 0 END) AS referralReceived,
            SUM(CASE WHEN (lr.last_ref IS NOT NULL OR f.referred_to_lab_id IS NOT NULL)
                          AND f.sample_tested_datetime IS NOT NULL
                          AND f.sample_tested_datetime >= COALESCE(lr.last_ref, f.samples_referred_datetime)
                     THEN 1 ELSE 0 END) AS referralTested,
            SUM(CASE WHEN (lr.last_ref IS NOT NULL OR f.referred_to_lab_id IS NOT NULL)
                          AND f.result_status = " . SAMPLE_STATUS\ACCEPTED . "
                     THEN 1 ELSE 0 END) AS referralAccepted
        FROM form_tb f $ttJoin
        LEFT JOIN (
            SELECT tb_id, MAX(referred_on_datetime) AS last_ref
            FROM tb_referral_history
            GROUP BY tb_id
        ) lr ON lr.tb_id = f.tb_id
        WHERE $whereSql";

    $row = $db->rawQueryOne($sql);

    $toNum = function ($v) {
        if ($v === null) return null;
        return is_numeric($v) ? (float) $v : null;
    };

    $payload = [
        'total'              => (int) ($row['total'] ?? 0),
        'atCollectionSite'   => (int) ($row['atCollectionSite'] ?? 0),
        'atTestingLab'       => (int) ($row['atTestingLab'] ?? 0),
        'tested'             => (int) ($row['tested'] ?? 0),
        'resultEntered'      => (int) ($row['resultEntered'] ?? 0),
        'awaitingApproval'   => (int) ($row['awaitingApproval'] ?? 0),
        'accepted'           => (int) ($row['accepted'] ?? 0),
        'rejected'           => (int) ($row['rejected'] ?? 0),
        'testFailed'         => (int) ($row['testFailed'] ?? 0),
        'referred'           => (int) ($row['referred'] ?? 0),
        'acceptedWithoutResultEntered' => (int) ($row['acceptedWithoutResultEntered'] ?? 0),
        'dispatched'         => (int) ($row['dispatched'] ?? 0),
        'printed'            => (int) ($row['printed'] ?? 0),
        'avgDaysCollToRecv'  => $toNum($row['avgDaysCollToRecv'] ?? null),
        'avgDaysRecvToTested' => $toNum($row['avgDaysRecvToTested'] ?? null),
        'distinctLabs'       => (int) ($row['distinctLabs'] ?? 0),
        'distinctFacilities' => (int) ($row['distinctFacilities'] ?? 0),
        'referralReceived'   => (int) ($row['referralReceived'] ?? 0),
        'referralTested'     => (int) ($row['referralTested'] ?? 0),
        'referralAccepted'   => (int) ($row['referralAccepted'] ?? 0),
    ];

    header('Content-Type: application/json');
    echo json_encode($payload);
    return;
} catch (Throwable $e) { tbcReportError($e, $db, 'summary', $sql ?? null); return; } }

// ---------------------------------------------------------------------------
// PER-LAB action — DataTables server-side, one row per lab
// Sample-grain aggregation (originator samples), plus test-grain counts
// from tb_tests for tests-performed columns.
// ---------------------------------------------------------------------------
if ($action === 'per-lab') { try {
    $where = tbcBuildFilterConditions($_POST);
    [$ttJoin, $_unused] = tbcTestTypeJoinAndWhere($_POST);
    $whereSql = !empty($where) ? implode(' AND ', $where) : '1=1';

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

    // Inner subquery: one row per sample × lab in scope, with classifications.
    // Originator = the sample's primary lab_id. Referred-in = where the sample
    // was referred to that lab (referred_to_lab_id), which today is always 0
    // but the column is wired so it lights up once referrals are recorded.
    $perLabSql = "
        SELECT
            fl.facility_id AS lab_id,
            fl.facility_name,
            SUM(CASE WHEN x.lab_role = 'originator' THEN 1 ELSE 0 END) AS originatorSamples,
            SUM(CASE WHEN x.lab_role = 'referred-in' THEN 1 ELSE 0 END) AS referredInSamples,
            COALESCE(MAX(tt.testsPerformed), 0) AS testsPerformed,
            SUM(CASE WHEN x.result_status = " . SAMPLE_STATUS\ACCEPTED . " THEN 1 ELSE 0 END) AS accepted,
            SUM(CASE WHEN x.result_status = " . SAMPLE_STATUS\REJECTED . " THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN x.result_status = " . SAMPLE_STATUS\TEST_FAILED . " THEN 1 ELSE 0 END) AS failed,
            SUM(CASE WHEN x.result_status IN (" . SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB . ", " . SAMPLE_STATUS\PENDING_APPROVAL . ") THEN 1 ELSE 0 END) AS pendingAtLab,
            AVG(x.daysCollToRecv) AS avgDaysCollToRecv,
            AVG(x.daysRecvToTested) AS avgDaysRecvToTested
        FROM (
            SELECT
                f.tb_id,
                f.lab_id AS lab_id,
                'originator' AS lab_role,
                f.result_status,
                CASE WHEN f.sample_received_at_lab_datetime IS NOT NULL AND f.sample_collection_date IS NOT NULL
                     THEN GREATEST(DATEDIFF(f.sample_received_at_lab_datetime, f.sample_collection_date), 0) END AS daysCollToRecv,
                CASE WHEN f.sample_tested_datetime IS NOT NULL AND f.sample_received_at_lab_datetime IS NOT NULL
                     THEN GREATEST(DATEDIFF(f.sample_tested_datetime, f.sample_received_at_lab_datetime), 0) END AS daysRecvToTested
            FROM form_tb f $ttJoin
            WHERE $whereSql AND f.lab_id IS NOT NULL
            UNION
            SELECT DISTINCT
                f.tb_id,
                hop.to_lab_id AS lab_id,
                'referred-in' AS lab_role,
                f.result_status,
                NULL AS daysCollToRecv,
                CASE WHEN f.sample_tested_datetime IS NOT NULL AND hop.referred_on IS NOT NULL
                     THEN GREATEST(DATEDIFF(f.sample_tested_datetime, hop.referred_on), 0) END AS daysRecvToTested
            FROM form_tb f $ttJoin
            INNER JOIN (
                SELECT tb_id, to_lab_id, referred_on_datetime AS referred_on
                FROM tb_referral_history
                UNION
                SELECT tb_id, referred_to_lab_id, samples_referred_datetime
                FROM form_tb
                WHERE referred_to_lab_id IS NOT NULL
            ) hop ON hop.tb_id = f.tb_id AND hop.to_lab_id IS NOT NULL
            WHERE $whereSql
        ) x
        INNER JOIN facility_details fl ON fl.facility_id = x.lab_id
        LEFT JOIN (
            SELECT t.lab_id, COUNT(*) AS testsPerformed
            FROM tb_tests t
            INNER JOIN form_tb f2 ON f2.tb_id = t.tb_id
            WHERE t.lab_id IS NOT NULL
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
            $r['facility_name'] ?? '',
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
} catch (Throwable $e) { tbcReportError($e, $db, 'per-lab', ($countQuery ?? null) . "\n---\n" . ($dataQuery ?? '')); return; } }

// ---------------------------------------------------------------------------
// REFERRAL-MATRIX action — DataTables server-side, one row per (from_lab, to_lab)
// pair. Empty whenever the period has no referrals.
// ---------------------------------------------------------------------------
if ($action === 'referral-matrix') { try {
    $where = tbcBuildFilterConditions($_POST);
    [$ttJoin, $_unused] = tbcTestTypeJoinAndWhere($_POST);
    $whereSql = implode(' AND ', $where);

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
       fallback only fires for samples that have a referral pair set but
       no history rows yet (legacy data path). */
    $matrixSql = "
        SELECT
            frm.facility_id AS from_lab_id,
            frm.facility_name AS from_lab_name,
            tol.facility_id AS to_lab_id,
            tol.facility_name AS to_lab_name,
            COUNT(DISTINCT hop.tb_id) AS referred,
            COUNT(DISTINCT CASE WHEN f.sample_received_at_lab_datetime IS NULL THEN f.tb_id END) AS awaitingReceipt,
            COUNT(DISTINCT CASE WHEN f.sample_received_at_lab_datetime IS NOT NULL AND f.sample_tested_datetime IS NULL THEN f.tb_id END) AS receivedNotTested,
            COUNT(DISTINCT CASE WHEN f.result_status = " . SAMPLE_STATUS\ACCEPTED . " THEN f.tb_id END) AS acceptedAtTo,
            COUNT(DISTINCT CASE WHEN f.result_status IN (" . SAMPLE_STATUS\REJECTED . ", " . SAMPLE_STATUS\TEST_FAILED . ") THEN f.tb_id END) AS rejFailAtTo
        FROM (
            SELECT tb_id, from_lab_id, to_lab_id FROM tb_referral_history
            UNION
            SELECT tb_id, referred_by_lab_id, referred_to_lab_id
            FROM form_tb
            WHERE referred_by_lab_id IS NOT NULL AND referred_to_lab_id IS NOT NULL
        ) hop
        INNER JOIN form_tb f ON f.tb_id = hop.tb_id $ttJoin
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
            $r['from_lab_name'] ?? '',
            $r['to_lab_name'] ?? '',
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
} catch (Throwable $e) { tbcReportError($e, $db, 'referral-matrix', $countQuery ?? ($matrixSql ?? null)); return; } }

// ---------------------------------------------------------------------------
// DETAIL action — DataTables server-side, one row per (sample × test × lab)
// Joins form_tb to tb_tests; samples without tb_tests rows still appear once
// using the sample's primary lab so the explorer mirrors the dashboard total.
// ---------------------------------------------------------------------------
if ($action === 'detail') { try {
    $where = tbcBuildFilterConditions($_POST);
    [$ttJoin, $_unused] = tbcTestTypeJoinAndWhere($_POST);
    $whereSql = !empty($where) ? implode(' AND ', $where) : '1=1';

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

    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? '', $aColumns);
    if (!empty($columnSearch) && $columnSearch !== '') {
        $whereSql .= ' AND (' . $columnSearch . ')';
    }

    // Performing lab is the tb_tests.lab_id when present, else falls back to
    // the sample's primary lab_id. Lab role: "Originator" when performing
    // matches form_tb.lab_id; "Referral" otherwise. lab_role_sort is a
    // hidden numeric for sortable ordering.
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
            WHEN t.lab_id IS NULL THEN 'Originator'
            WHEN t.lab_id = f.lab_id THEN 'Originator'
            ELSE 'Referral'
        END AS lab_role,
        CASE
            WHEN t.lab_id IS NULL THEN 0
            WHEN t.lab_id = f.lab_id THEN 0
            ELSE 1
        END AS lab_role_sort
    ";

    $baseFrom = "
        FROM form_tb f $ttJoin
        LEFT JOIN tb_tests t ON t.tb_id = f.tb_id
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

    foreach ($rows as $r) {
        $out['aaData'][] = [
            $r['sample_code'] ?? '',
            $r['collection_site'] ?? '',
            $r['primary_lab'] ?? '',
            $r['performing_lab'] ?? '',
            $r['lab_role'] ?? '',
            $r['test_type'] ?? '',
            $r['test_result'] ?? '',
            $r['status_name'] ?? '',
            $fmtDate($r['sample_collection_date'] ?? null),
            $fmtDate($r['t_received'] ?? ($r['f_received'] ?? null)),
            $fmtDate($r['t_tested'] ?? ($r['f_tested'] ?? null)),
            $r['final_result'] ?? '',
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($out);
    return;
} catch (Throwable $e) { tbcReportError($e, $db, 'detail', $countQuery ?? null); return; } }

// ---------------------------------------------------------------------------
// STUCK action — counts and average age (days) of samples currently sitting
// between cascade stages. Answers "where are samples stuck?" rather than the
// cumulative funnel question. Always allows status=9 (RECEIVED_AT_CLINIC) into
// the calculation so the "stuck at CS" row is visible on instances that have
// the data — otherwise the panel would falsely show 0.
// ---------------------------------------------------------------------------
if ($action === 'stuck') { try {
    // Reuse the standard filter conditions but strip out the non-STS exclusion
    // of RECEIVED_AT_CLINIC so the CS rows can be counted when the data exists.
    $where = tbcBuildFilterConditions($_POST);
    $where = array_values(array_filter($where, function ($cond) {
        return strpos($cond, 'RECEIVED_AT_CLINIC') === false
            && strpos($cond, 'NOT IN (' . SAMPLE_STATUS\CANCELLED . ', ' . SAMPLE_STATUS\RECEIVED_AT_CLINIC . ')') === false;
    }));
    $where[] = "f.result_status != " . SAMPLE_STATUS\CANCELLED;
    [$ttJoin, $_unused] = tbcTestTypeJoinAndWhere($_POST);
    $whereSql = implode(' AND ', $where);

    $sql = "SELECT
            SUM(CASE WHEN f.result_status = " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                          AND f.sample_dispatched_datetime IS NULL THEN 1 ELSE 0 END) AS stuckCsCount,
            AVG(CASE WHEN f.result_status = " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                          AND f.sample_dispatched_datetime IS NULL
                          AND f.sample_collection_date IS NOT NULL
                     THEN GREATEST(DATEDIFF(NOW(), f.sample_collection_date), 0) END) AS stuckCsAvgDays,

            SUM(CASE WHEN f.result_status = " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                          AND f.sample_dispatched_datetime IS NOT NULL
                          AND f.sample_received_at_lab_datetime IS NULL THEN 1 ELSE 0 END) AS inTransitCount,
            AVG(CASE WHEN f.result_status = " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "
                          AND f.sample_dispatched_datetime IS NOT NULL
                          AND f.sample_received_at_lab_datetime IS NULL
                     THEN GREATEST(DATEDIFF(NOW(), f.sample_dispatched_datetime), 0) END) AS inTransitAvgDays,

            SUM(CASE WHEN f.result_status = " . SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB . " THEN 1 ELSE 0 END) AS atLabCount,
            AVG(CASE WHEN f.result_status = " . SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB . "
                          AND f.sample_received_at_lab_datetime IS NOT NULL
                     THEN GREATEST(DATEDIFF(NOW(), f.sample_received_at_lab_datetime), 0) END) AS atLabAvgDays,

            SUM(CASE WHEN f.is_result_finalized = 'yes'
                          AND f.result_status NOT IN (" . SAMPLE_STATUS\ACCEPTED . ", " . SAMPLE_STATUS\REJECTED . ", " . SAMPLE_STATUS\TEST_FAILED . ")
                          THEN 1 ELSE 0 END) AS pendingApprovalCount,
            AVG(CASE WHEN f.is_result_finalized = 'yes'
                          AND f.result_status NOT IN (" . SAMPLE_STATUS\ACCEPTED . ", " . SAMPLE_STATUS\REJECTED . ", " . SAMPLE_STATUS\TEST_FAILED . ")
                          AND f.sample_tested_datetime IS NOT NULL
                     THEN GREATEST(DATEDIFF(NOW(), f.sample_tested_datetime), 0) END) AS pendingApprovalAvgDays,

            SUM(CASE WHEN f.result_status = " . SAMPLE_STATUS\REFERRED . " THEN 1 ELSE 0 END) AS referralInFlightCount,
            AVG(CASE WHEN f.result_status = " . SAMPLE_STATUS\REFERRED . "
                          AND f.samples_referred_datetime IS NOT NULL
                     THEN GREATEST(DATEDIFF(NOW(), f.samples_referred_datetime), 0) END) AS referralInFlightAvgDays,

            SUM(CASE WHEN f.is_result_finalized = 'yes'
                          AND f.result_dispatched_datetime IS NULL THEN 1 ELSE 0 END) AS finalizedNotDispatchedCount,
            AVG(CASE WHEN f.is_result_finalized = 'yes'
                          AND f.result_dispatched_datetime IS NULL
                          AND COALESCE(f.result_approved_datetime, f.sample_tested_datetime) IS NOT NULL
                     THEN GREATEST(DATEDIFF(NOW(), COALESCE(f.result_approved_datetime, f.sample_tested_datetime)), 0) END) AS finalizedNotDispatchedAvgDays
        FROM form_tb f $ttJoin
        WHERE $whereSql";

    $row = $db->rawQueryOne($sql);
    $toNum = function ($v) {
        return ($v === null || $v === '') ? null : (float) $v;
    };

    $rows = [
        ['stage' => _translate('At Collection Site, not dispatched'),       'count' => (int) ($row['stuckCsCount'] ?? 0),                  'avgDays' => $toNum($row['stuckCsAvgDays'] ?? null)],
        ['stage' => _translate('Dispatched, not received at Lab'),         'count' => (int) ($row['inTransitCount'] ?? 0),                'avgDays' => $toNum($row['inTransitAvgDays'] ?? null)],
        ['stage' => _translate('At Lab, not yet tested'),                  'count' => (int) ($row['atLabCount'] ?? 0),                    'avgDays' => $toNum($row['atLabAvgDays'] ?? null)],
        ['stage' => _translate('Tested, awaiting approval'),               'count' => (int) ($row['pendingApprovalCount'] ?? 0),          'avgDays' => $toNum($row['pendingApprovalAvgDays'] ?? null)],
        ['stage' => _translate('Referred, not received at target lab'),    'count' => (int) ($row['referralInFlightCount'] ?? 0),         'avgDays' => $toNum($row['referralInFlightAvgDays'] ?? null)],
        ['stage' => _translate('Final result entered, not dispatched'),    'count' => (int) ($row['finalizedNotDispatchedCount'] ?? 0),   'avgDays' => $toNum($row['finalizedNotDispatchedAvgDays'] ?? null)],
    ];

    header('Content-Type: application/json');
    echo json_encode(['rows' => $rows]);
    return;
} catch (Throwable $e) { tbcReportError($e, $db, 'stuck', $sql ?? null); return; } }

// Unknown action — empty payload.
header('Content-Type: application/json');
echo json_encode([]);
