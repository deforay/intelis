<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
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
    // AJAX requests bypass the access control layer, so the page's own
    // privilege is checked here.
    _requirePrivilege('/reports/interface-machine-activity.php');

    /** @var LabPerformanceIndicatorsService $indicators */
    $indicators = ContainerRegistry::get(LabPerformanceIndicatorsService::class);

    // Filter validation and lab scoping both live in the service.
    $filters = $indicators->resolveFilters($_POST);

    // The row's keys as the instrument figures returned them. Read raw: they are
    // compared as text, never printed, and the sanitizer would turn
    // "HIV-1 & HIV-2" into "HIV-1 &amp; HIV-2".
    $text = static fn(string $key): string => is_string($value = _rawInput($key, '')) ? $value : '';
    // Its own key: labId is the report's Lab filter, which the counts were made under.
    $rowLabId = $text('rowLabId');
    $outcome = $text('outcome');
    $row = [
        'labId' => ctype_digit($rowLabId) ? (int) $rowLabId : null,
        'instrument' => $text('instrument'),
        'serial' => $text('serial'),
        'assay' => $text('assay'),
    ];

    $samples = array_map(static fn(array $sample): array => $sample + [
        'testedOnDisplay' => DateUtility::humanReadableDateFormat($sample['testedOn'], true),
    ], $indicators->getInstrumentSamples($filters, $row, $outcome !== '' ? $outcome : null));
    echo JsonUtility::encodeUtf8Json([
        'samples' => $samples,
        'limited' => count($samples) >= LabPerformanceIndicatorsService::SAMPLE_LIST_LIMIT,
    ]);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => _translate('Unable to load the samples')]);
}
