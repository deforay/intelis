<?php

use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\ReferralNetworkService;
use Psr\Http\Message\ServerRequestInterface;

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var ReferralNetworkService $referralService */
$referralService = ContainerRegistry::get(ReferralNetworkService::class);

try {
    $filters = [
        'testTypes' => array_filter((array) ($_POST['testType'] ?? [])),
        'dateRange' => $_POST['dateRange'] ?? '',
        'provinceIds' => (array) ($_POST['state'] ?? []),
        'districtIds' => (array) ($_POST['district'] ?? []),
        'labIds' => (array) ($_POST['labName'] ?? []),
        'facilityIds' => (array) ($_POST['facilityId'] ?? []),
    ];

    $rows = $referralService->aggregate($filters);

    // Decorate rows with facility / lab names and the referring facility's geo.
    $ids = [];
    foreach ($rows as $r) {
        $ids[$r['facility_id']] = true;
        $ids[$r['lab_id']] = true;
    }
    $meta = $referralService->getFacilitiesMeta(array_keys($ids));

    $data = [];
    foreach ($rows as $r) {
        $f = $meta[$r['facility_id']] ?? null;
        $l = $meta[$r['lab_id']] ?? null;
        $data[] = [
            'facility' => $f['name'] ?? ('#' . $r['facility_id']),
            'district' => $f['district'] ?? '',
            'province' => $f['province'] ?? '',
            'lab' => $l['name'] ?? ('#' . $r['lab_id']),
            'test' => $r['test_name'],
            'samples' => $r['samples'],
            'latest' => $r['latest'],
        ];
    }

    // ----- DataTables server-side: search, sort, paginate (in PHP) -----
    $search = trim((string) ($_POST['sSearch'] ?? ''));
    $totalRecords = count($data);

    if ($search !== '') {
        $needle = mb_strtolower($search);
        $data = array_values(array_filter($data, static function ($row) use ($needle) {
            $hay = mb_strtolower($row['facility'] . ' ' . $row['district'] . ' ' . $row['province'] . ' ' . $row['lab'] . ' ' . $row['test']);
            return str_contains($hay, $needle);
        }));
    }
    $filteredRecords = count($data);

    // Sort. Column order must match the <thead> on the page.
    $columns = ['facility', 'district', 'province', 'lab', 'test', 'samples', 'latest'];
    $sortCol = isset($_POST['iSortCol_0']) ? (int) $_POST['iSortCol_0'] : 5;
    $sortDir = (($_POST['sSortDir_0'] ?? 'desc') === 'asc') ? 1 : -1;
    $sortKey = $columns[$sortCol] ?? 'samples';
    usort($data, static function ($a, $b) use ($sortKey, $sortDir) {
        if ($sortKey === 'samples') {
            return ($a['samples'] <=> $b['samples']) * $sortDir;
        }
        return strcasecmp((string) $a[$sortKey], (string) $b[$sortKey]) * $sortDir;
    });

    $offset = isset($_POST['iDisplayStart']) ? max(0, (int) $_POST['iDisplayStart']) : 0;
    $limit = (isset($_POST['iDisplayLength']) && (int) $_POST['iDisplayLength'] > 0) ? (int) $_POST['iDisplayLength'] : 25;
    $pageRows = array_slice($data, $offset, $limit);

    $aaData = [];
    foreach ($pageRows as $row) {
        $aaData[] = [
            htmlspecialchars($row['facility'], ENT_QUOTES),
            htmlspecialchars($row['district'], ENT_QUOTES),
            htmlspecialchars($row['province'], ENT_QUOTES),
            htmlspecialchars($row['lab'], ENT_QUOTES),
            htmlspecialchars($row['test'], ENT_QUOTES),
            number_format((float) $row['samples']),
            $row['latest'] ? DateUtility::humanReadableDateFormat($row['latest'], true) : '',
        ];
    }

    echo JsonUtility::encodeUtf8Json([
        'sEcho' => (int) ($_POST['sEcho'] ?? 0),
        'iTotalRecords' => $totalRecords,
        'iTotalDisplayRecords' => $filteredRecords,
        'aaData' => $aaData,
    ]);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
    ]);
    echo JsonUtility::encodeUtf8Json([
        'sEcho' => (int) ($_POST['sEcho'] ?? 0),
        'iTotalRecords' => 0,
        'iTotalDisplayRecords' => 0,
        'aaData' => [],
    ]);
}
