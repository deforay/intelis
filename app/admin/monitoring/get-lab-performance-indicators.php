<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\LabPerformanceIndicatorsService;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    /** @var LabPerformanceIndicatorsService $indicators */
    $indicators = ContainerRegistry::get(LabPerformanceIndicatorsService::class);

    // Filters are normalized by the service (test key must be a registry key,
    // grouping must be a known value). Lab scoping happens inside every query
    // because this endpoint is reachable directly and AJAX requests are not
    // covered by the access control layer.
    $filters = $indicators->resolveFilters($_POST);

    $section = (string) ($_POST['section'] ?? '');
    if ($filters['testKey'] === 'all' && $section !== 'overview') {
        throw new \App\Exceptions\SystemException('Select a test type for this indicator');
    }

    $output = match ($section) {
        'overview' => ['rows' => $indicators->getOverview($filters)],
        'tat' => ['rows' => $indicators->getTat($filters)],
        'volume' => ['rows' => $indicators->getVolume($filters)],
        'failure' => [
            'rows' => $indicators->getFailure($filters),
            'reasons' => $indicators->getFailureReasons($filters),
        ],
        'rejection' => [
            'rows' => $indicators->getRejection($filters),
            'reasons' => $indicators->getRejectionReasons($filters),
        ],
        'patients' => null, // handled below, needs the DataTables envelope
        default => throw new \App\Exceptions\SystemException('Invalid indicator section'),
    };

    if ($section === 'patients') {
        $offset = max(0, (int) ($_POST['iDisplayStart'] ?? 0));
        $limit = (int) ($_POST['iDisplayLength'] ?? 25);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 25;
        }
        $result = $indicators->getRepeatPatients(
            $filters,
            $offset,
            $limit,
            trim((string) ($_POST['sSearch'] ?? ''))
        );

        $escape = static fn(?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $aaData = [];
        foreach ($result['rows'] as $row) {
            $changePill = $row['changed']
                ? "<span class='lpi-pill lpi-pill-warn'>" . _translate('Changed') . "</span>"
                : "<span class='lpi-pill lpi-pill-muted'>" . _translate('Unchanged') . "</span>";
            $aaData[] = [
                $escape($row['patient']),
                (int) $row['tests'],
                $escape($row['firstDate']) . "<br><small>" . $escape($row['firstResult']) . "</small>",
                $escape($row['lastDate']) . "<br><small>" . $escape($row['lastResult']) . "</small>",
                $changePill,
            ];
        }
        $output = [
            'sEcho' => (int) ($_POST['sEcho'] ?? 0),
            'iTotalRecords' => $result['total'],
            'iTotalDisplayRecords' => $result['total'],
            'aaData' => $aaData,
            'summary' => $result['summary'],
        ];
    }

    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => _translate('Unable to load this indicator right now')]);
}
