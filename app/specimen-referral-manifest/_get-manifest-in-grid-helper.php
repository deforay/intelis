<?php

/**
 * Shared "manifest grid" DataTables data source.
 *
 * One engine for every test module. Each module's
 * /<module>/requests/getManifestInGridHelper.php is now a thin config that sets
 * a $manifestGrid array and requires this file; everything below -- request
 * sanitisation, the standalone remote-column toggle, sorting, searching, query
 * assembly, count and JSON output -- is identical across modules.
 *
 * $manifestGrid keys:
 *   select        string   the full "SELECT ... FROM <table> as vl <JOINs>".
 *   aColumns      string[] searchable columns (DataTables order; includes
 *                          'vl.remote_sample_code', dropped on standalone).
 *   orderColumns  string[] sortable columns, same order as aColumns.
 *   manifestWhere callable (string $escapedCode): string -- the per-module
 *                          manifest filter SQL. Receives an already-escaped code.
 *   referrable    bool     optional; true only for referral modules (tb,
 *                          generic-tests) whose table has referred_to_lab_id.
 *                          Gates the cloud-LIS "referred to my lab" admittance.
 *   rowMapper     callable (array $aRow): array -- builds one DataTables row from
 *                          a result row, reproducing the module's exact column
 *                          order, result decoding and name handling. It owns the
 *                          conditional remote-sample-code column.
 *
 * $db and $general are injected by LegacyRequestHandler (resolved defensively
 * here so the file also works if required directly).
 */

use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

// Force no caching
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

/** @var DatabaseService $db */
$db = $db ?? ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = $general ?? ContainerRegistry::get(CommonService::class);

/** @var array $manifestGrid */
$cfg = $manifestGrid ?? [];

// Sanitized values from $request object
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

if (empty($_POST['manifestCode'])) {
    throw new SystemException(_translate('Manifest code is required'));
}
$manifestCode = trim((string) $_POST['manifestCode']);

// Clear the query count cache for this manifest
if (CommonService::isSessionActive()) {
    unset($_SESSION['queryCounters']);
}

$aColumns = $cfg['aColumns'];
$orderColumns = $cfg['orderColumns'];
if ($general->isStandaloneInstance()) {
    $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
    $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
}

$sOffset = $sLimit = null;
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
    // Cast to int: these are interpolated raw into the LIMIT clause below.
    $sOffset = (int) $_POST['iDisplayStart'];
    $sLimit = (int) $_POST['iDisplayLength'];
}

$sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);
$columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? '', $aColumns);

$sWhere = [];
if (!empty($columnSearch) && $columnSearch != '') {
    $sWhere[] = $columnSearch;
}
$sWhere[] = $cfg['manifestWhere']($db->escape($manifestCode));

// Facility isolation: on a multi-lab STS, a mapped user only sees samples for
// the facilities in their facilityMap. Unmapped users (empty map) see all,
// matching the request-list behaviour. No-op on LIS/standalone.
//
// Cloud-LIS exceptions -- a manifest's samples keep their SENDING site's
// facility_id (a collection site, typically outside the testing-lab user's
// facilityMap), so the facilityMap filter alone would hide the very samples the
// user is here to activate. When the session carries a lab id (cloud-LIS only --
// null for every existing LIS/STS user, so this is a no-op for them):
//   1. If the manifest is OWNED by this lab (specimen_manifests.lab_id), every
//      sample in it belongs here. manifestWhere already pins the query to this
//      one code, so ownership lifts the facility filter entirely.
//   2. Referral modules (tb, generic-tests, gated on the 'referrable' flag since
//      only their tables carry referred_to_lab_id): also admit samples referred
//      TO this lab via an additive OR.
if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
    $facilityClause = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    $sessionLabId = (int) ($_SESSION['labId'] ?? 0);

    $labOwnsManifest = false;
    if ($sessionLabId > 0) {
        $owned = $db->rawQueryOne(
            "SELECT 1 FROM specimen_manifests WHERE manifest_code = ? AND lab_id = ? LIMIT 1",
            [$manifestCode, $sessionLabId]
        );
        $labOwnsManifest = !empty($owned);
    }

    if (!$labOwnsManifest) {
        if ($sessionLabId > 0 && !empty($cfg['referrable'])) {
            $sWhere[] = " ( $facilityClause OR vl.referred_to_lab_id = $sessionLabId ) ";
        } else {
            $sWhere[] = $facilityClause;
        }
    }
}

$sQuery = $cfg['select'] . ' WHERE ' . implode(' AND ', $sWhere);

if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
    $sQuery = "$sQuery ORDER BY $sOrder";
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
}

[$rResult, $resultCount] = $db->getDataAndCount($sQuery);

$output = [
    "sEcho" => (int) ($_POST['sEcho'] ?? 0),
    "iTotalRecords" => $resultCount,
    "iTotalDisplayRecords" => $resultCount,
    "aaData" => []
];

$rowMapper = $cfg['rowMapper'];
foreach ($rResult as $aRow) {
    $output['aaData'][] = $rowMapper($aRow);
}

echo JsonUtility::encodeUtf8Json($output);
