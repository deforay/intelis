<?php

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

header('Content-Type: application/json; charset=utf-8');

try {
    $filters = [
        'testTypes' => array_filter((array) ($_POST['testType'] ?? [])),
        'dateRange' => $_POST['dateRange'] ?? '',
        'provinceIds' => (array) ($_POST['state'] ?? []),
        'districtIds' => (array) ($_POST['district'] ?? []),
        'labIds' => (array) ($_POST['labName'] ?? []),
        'facilityIds' => (array) ($_POST['facilityId'] ?? []),
    ];

    // A "Refresh" click bypasses the cached aggregate and recomputes from the DB.
    $forceRefresh = !empty($_POST['refresh']) && $_POST['refresh'] !== 'false';
    $rows = $referralService->aggregate($filters, $forceRefresh);

    // Collapse per-test rows into one directed flow per (facility -> lab).
    $flows = [];
    $facilityIds = [];
    foreach ($rows as $r) {
        $key = $r['facility_id'] . '-' . $r['lab_id'];
        if (!isset($flows[$key])) {
            $flows[$key] = [
                'from' => $r['facility_id'],
                'to' => $r['lab_id'],
                'count' => 0,
                'latest' => null,
            ];
        }
        $flows[$key]['count'] += $r['samples'];
        if ($r['latest'] !== null && ($flows[$key]['latest'] === null || $r['latest'] > $flows[$key]['latest'])) {
            $flows[$key]['latest'] = $r['latest'];
        }
        $facilityIds[$r['facility_id']] = true;
        $facilityIds[$r['lab_id']] = true;
    }

    $meta = $referralService->getFacilitiesMeta(array_keys($facilityIds));

    // Tally sent / received volume per node so the UI can size markers.
    $sent = $received = [];
    foreach ($flows as $f) {
        $sent[$f['from']] = ($sent[$f['from']] ?? 0) + $f['count'];
        $received[$f['to']] = ($received[$f['to']] ?? 0) + $f['count'];
    }

    // Identify lab nodes first so we can pull their configured instruments in one query.
    $labIds = [];
    foreach ($meta as $id => $m) {
        if ($m['lat'] === null || $m['lng'] === null) {
            continue;
        }
        if ($m['type'] === 2 || ($received[$id] ?? 0) > 0) {
            $labIds[] = $id;
        }
    }
    $instrumentsByLab = $referralService->getInstrumentsByLab($labIds);

    $nodes = [];
    $unmapped = 0;
    foreach ($meta as $id => $m) {
        if ($m['lat'] === null || $m['lng'] === null) {
            $unmapped++;
            continue;
        }
        $isLab = $m['type'] === 2 || ($received[$id] ?? 0) > 0;
        $nodes[] = [
            'id' => $id,
            'name' => $m['name'],
            'district' => $m['district'],
            'province' => $m['province'],
            'lat' => $m['lat'],
            'lng' => $m['lng'],
            'isLab' => $isLab,
            'samplesSent' => $sent[$id] ?? 0,
            'samplesReceived' => $received[$id] ?? 0,
            'instruments' => $isLab ? ($instrumentsByLab[$id] ?? []) : [],
        ];
    }

    // Only keep flows whose both endpoints are mappable.
    $mappable = [];
    foreach ($nodes as $n) {
        $mappable[$n['id']] = true;
    }
    $outFlows = [];
    foreach ($flows as $f) {
        if (isset($mappable[$f['from']], $mappable[$f['to']]) && $f['from'] !== $f['to']) {
            $outFlows[] = $f;
        }
    }
    // Heaviest flows first so the client can cap rendering while keeping the busiest links.
    usort($outFlows, static fn($a, $b) => $b['count'] <=> $a['count']);

    echo JsonUtility::encodeUtf8Json([
        'nodes' => $nodes,
        'flows' => array_values($outFlows),
        'unmappedCount' => $unmapped,
    ]);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
    ]);
    http_response_code(500);
    echo JsonUtility::encodeUtf8Json(['nodes' => [], 'flows' => [], 'unmappedCount' => 0, 'error' => 'Unable to build referral map data']);
}
