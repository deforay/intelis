<?php

use App\Services\TestsService;
use App\Services\STS\RequestReceiptsService;
use App\Registries\AppRegistry;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\CANCELLED;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$requestPages = [
    'vl' => '/vl/requests/vl-requests.php',
    'eid' => '/eid/requests/eid-requests.php',
    'covid19' => '/covid-19/requests/covid-19-requests.php',
    'hepatitis' => '/hepatitis/requests/hepatitis-requests.php',
    'tb' => '/tb/requests/tb-requests.php',
    'cd4' => '/cd4/requests/cd4-requests.php',
    'generic-tests' => '/generic-tests/requests/view-requests.php',
];

// testType also names the facility_attributes JSON key below, so only known modules pass
$testType = (string) ($_POST['testType'] ?? 'vl');
if (!isset($requestPages[$testType]) || !in_array($testType, TestsService::getActiveTests(), true)) {
    $testType = 'vl';
}
$url = $requestPages[$testType];
$testName = TestsService::getTestName($testType);
$table = TestsService::getTestTableName($testType);
$labId = (int) base64_decode((string) ($_POST['labId'] ?? ''));
// The key the sync endpoints stamp in facility_attributes (CommonService::updateSyncDateTime)
$syncKey = $testType === 'generic-tests' ? 'genericTests' : $testType;

// The lab comes from the request and AJAX skips the ACL check, so a user tied to
// one lab (cloud-LIS) must not read another lab's counts by changing it
$labScope = $general->labAdminScopeWhere('lab_id');
$labScope = $labScope === '' ? '' : " AND $labScope";

[$startDate, $endDate] = DateUtility::convertDateRange($_POST['dateRange'] ?? '');
$countDateClause = '';
$countParams = [$labId];
if ($startDate !== '' && $endDate !== '') {
    $countDateClause = ' AND sample_collection_date BETWEEN ? AND ?';
    $countParams[] = "$startDate 00:00:00";
    $countParams[] = "$endDate 23:59:59";
}

// Requests sent = entered on the STS and pulled by the lab (data_sync = 1).
// Awaiting confirmation = pulled by a lab that sends receipts (data_sync = 2) and not
// yet confirmed saved; always 0 for a lab on a release without receipts.
// Results received = the lab reported an outcome (accepted or rejected); the results
// receiver sets data_sync = 1, so a sample rejected on the STS alone is not counted.
$sQuery = "SELECT f.facility_id,
            f.facility_name, GREATEST(
                    COALESCE(facility_attributes->>'$." . $syncKey . "RemoteResultsSync', 0),
                    COALESCE(facility_attributes->>'$." . $syncKey . "RemoteRequestsSync', 0)
                ) as latestSync,
                (f.facility_attributes->>'$." . $syncKey . "RemoteResultsSync') as lastResultsSync,
                (f.facility_attributes->>'$." . $syncKey . "RemoteRequestsSync') as lastRequestsSync,
                g_d_s.geo_name as province, g_d_d.geo_name as district,
                COALESCE(counts.requestsSent, 0) AS requestsSent,
                COALESCE(counts.awaitingConfirmation, 0) AS awaitingConfirmation,
                COALESCE(counts.resultsReceived, 0) AS resultsReceived
            FROM facility_details AS f
                LEFT JOIN geographical_divisions as g_d_s ON g_d_s.geo_id = f.facility_state_id
                LEFT JOIN geographical_divisions as g_d_d ON g_d_d.geo_id = f.facility_district_id
                LEFT JOIN (
                    SELECT facility_id,
                        SUM(remote_sample = 'yes' AND data_sync = 1) AS requestsSent,
                        SUM(remote_sample = 'yes' AND data_sync = " . RequestReceiptsService::IN_FLIGHT . ")
                            AS awaitingConfirmation,
                        SUM(data_sync = 1 AND result_status IN (" . ACCEPTED . ", " . REJECTED . ")) AS resultsReceived
                    FROM $table
                    WHERE lab_id = ?
                        AND IFNULL(result_status, 0) != " . CANCELLED . "
                        $labScope
                        $countDateClause
                    GROUP BY facility_id
                ) AS counts ON counts.facility_id = f.facility_id ";
$params = $countParams;

$sWhere = [];
$sWhere[] = " f.facility_id IN (SELECT DISTINCT facility_id FROM $table WHERE lab_id = ? $labScope) ";
$params[] = $labId;
if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
    $sWhere[] = ' f.facility_id IN (' . $db->inIntList($_POST['facilityName']) . ')';
}
if (isset($_POST['province']) && trim((string) $_POST['province']) !== '') {
    $sWhere[] = ' f.facility_state_id = ' . (int) $_POST['province'];
}
if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
    $sWhere[] = ' f.facility_district_id = ' . (int) $_POST['district'];
}
$sQuery .= " WHERE " . implode(" AND ", $sWhere);
$sQuery .= " ORDER BY latestSync DESC, f.facility_name ASC";

$_SESSION['labSyncStatusDetails'] = [
    'query' => $sQuery,
    'params' => $params,
    'testName' => $testName,
];

$rResult = $db->rawQuery($sQuery, $params);
foreach ($rResult as $aRow) { ?>
    <tr data-facilityId="<?= base64_encode((string) $aRow['facility_id']); ?>"
        data-labId="<?= htmlspecialchars((string) $_POST['labId']); ?>" data-url="<?= htmlspecialchars($url); ?>">
        <td>
            <?= htmlspecialchars((string) $aRow['facility_name']); ?>
        </td>
        <td>
            <?= htmlspecialchars($testName); ?>
        </td>
        <td>
            <?= htmlspecialchars((string) $aRow['province']); ?>
        </td>
        <td>
            <?= htmlspecialchars((string) $aRow['district']); ?>
        </td>
        <td class="text-right">
            <?= (int) $aRow['requestsSent']; ?>
        </td>
        <td class="text-right">
            <?= (int) $aRow['awaitingConfirmation']; ?>
        </td>
        <td class="text-right">
            <?= (int) $aRow['resultsReceived']; ?>
        </td>
        <td>
            <?= DateUtility::humanReadableDateFormat($aRow['lastRequestsSync'], true); ?>
        </td>
        <td>
            <?= DateUtility::humanReadableDateFormat($aRow['lastResultsSync'], true); ?>
        </td>
    </tr>
<?php }
