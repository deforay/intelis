<?php

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\LabPerformanceIndicatorsService;

ini_set('memory_limit', '512M');
set_time_limit(300);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    /** @var LabPerformanceIndicatorsService $indicators */
    $indicators = ContainerRegistry::get(LabPerformanceIndicatorsService::class);

    // Filter validation and lab scoping both live in the service; this
    // endpoint is reachable directly like every AJAX endpoint.
    $filters = $indicators->resolveFilters($_POST);

    $section = (string) ($_POST['section'] ?? '');
    $format = (string) ($_POST['format'] ?? 'csv');
    if (!in_array($format, ['csv', 'xlsx', 'json'], true)) {
        $format = 'csv';
    }

    // The full bundle is only meaningful as structured data.
    if ($section === 'all') {
        $format = 'json';
    }
    if ($filters['testKey'] === 'all' && $section !== 'overview') {
        throw new \App\Exceptions\SystemException('Select a test type for this indicator');
    }

    $days = _translate('days');
    $tatStages = [
        'collectionToReceipt' => _translate('Collection to Lab Receipt'),
        'receiptToTested' => _translate('Lab Receipt to Tested'),
        'testedToReleased' => _translate('Tested to Result Released'),
        'collectionToReleased' => _translate('Collection to Result Released'),
    ];

    // [headings, rows, secondary sheet or null]
    [$headings, $rows, $secondary] = match ($section) {
        'overview' => [
            [
                _translate('Test'), _translate('Registered'), _translate('Resulted'),
                _translate('Manual Entry'), _translate('Analyzer Interface'), _translate('File Import'),
                _translate('Unclassified'), _translate('Failed'), _translate('Failure Rate (%)'),
                _translate('Rejected'), _translate('Rejection Rate (%)')
            ],
            array_map(static fn(array $r): array => [
                $r['testName'], $r['registered'], $r['resulted'], $r['manual'], $r['interface'],
                $r['fileImport'], $r['unclassified'], $r['failed'], $r['failureRate'],
                $r['rejected'], $r['rejectionRate']
            ], $indicators->getOverview($filters)),
            null
        ],
        'tat' => [
            array_merge(
                [_translate('Period'), _translate('Samples')],
                array_merge(...array_map(static fn(string $label): array => [
                    "$label ($days)", "$label (n)"
                ], array_values($tatStages)))
            ),
            array_map(static function (array $r) use ($tatStages): array {
                $row = [$r['period'], $r['samples']];
                foreach (array_keys($tatStages) as $stage) {
                    $row[] = $r[$stage];
                    $row[] = $r[$stage . 'N'];
                }
                return $row;
            }, $indicators->getTat($filters)),
            null
        ],
        'volume' => [
            [
                _translate('Period'), _translate('Lab'), _translate('Registered'), _translate('Resulted'),
                _translate('Manual Entry'), _translate('Analyzer Interface'), _translate('File Import'),
                _translate('Unclassified')
            ],
            array_map(static fn(array $r): array => [
                $r['period'], $r['lab'], $r['registered'], $r['resulted'], $r['manual'],
                $r['interface'], $r['fileImport'], $r['unclassified']
            ], $indicators->getVolume($filters)),
            null
        ],
        'failure' => [
            [
                _translate('Period'), _translate('Lab'), _translate('Tested'),
                _translate('Failed'), _translate('Failure Rate (%)')
            ],
            array_map(static fn(array $r): array => [
                $r['period'], $r['lab'], $r['tested'], $r['failed'], $r['failureRate']
            ], $indicators->getFailure($filters)),
            [
                'name' => _translate('Failure Reasons'),
                'headings' => [_translate('Reason'), _translate('Total')],
                'rows' => array_map(static fn(array $r): array => [$r['reason'], $r['total']],
                    $indicators->getFailureReasons($filters)),
            ]
        ],
        'rejection' => [
            [
                _translate('Period'), _translate('Lab'), _translate('Samples'),
                _translate('Rejected'), _translate('Rejection Rate (%)')
            ],
            array_map(static fn(array $r): array => [
                $r['period'], $r['lab'], $r['received'], $r['rejected'], $r['rejectionRate']
            ], $indicators->getRejection($filters)),
            [
                'name' => _translate('Rejection Reasons'),
                'headings' => [_translate('Reason'), _translate('Total')],
                'rows' => array_map(static fn(array $r): array => [$r['reason'], $r['total']],
                    $indicators->getRejectionReasons($filters)),
            ]
        ],
        'patients' => [
            [
                _translate('Patient'), _translate('Tests'), _translate('First Date'), _translate('First Result'),
                _translate('Latest Date'), _translate('Latest Result'), _translate('Result Change')
            ],
            array_map(static fn(array $r): array => [
                $r['patient'], $r['tests'], $r['firstDate'], $r['firstResult'],
                $r['lastDate'], $r['lastResult'],
                $r['changed'] ? _translate('Changed') : _translate('Unchanged')
            ], $indicators->getRepeatPatients($filters, 0, 100000)['rows']),
            null
        ],
        'all' => [[], [], null],
        default => throw new \App\Exceptions\SystemException('Invalid indicator section'),
    };

    $baseName = 'InteLIS-Lab-Performance-Indicators-' . $section . '-' . date('d-M-Y-H-i-s');
    $filePath = TEMP_PATH . DIRECTORY_SEPARATOR . $baseName . '.' . $format;

    if ($format === 'json') {
        $payload = $section === 'all'
            ? $indicators->getAllIndicators($filters)
            : [
                'section' => $section,
                'filters' => [
                    'testType' => $filters['testKey'],
                    'grouping' => $filters['grouping'],
                    'startDate' => $filters['startDate'],
                    'endDate' => $filters['endDate'],
                ],
                'headings' => $headings,
                'rows' => $rows,
                'secondary' => $secondary,
            ];
        $payload['generatedOn'] = date('Y-m-d H:i:s');
        file_put_contents($filePath, JsonUtility::encodeUtf8Json($payload));
    } else {
        $writer = $format === 'xlsx' ? new XlsxWriter() : new CsvWriter();
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues($headings));
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        if (!empty($secondary['rows'])) {
            if ($format === 'xlsx') {
                $sheet = $writer->addNewSheetAndMakeItCurrent();
                $sheet->setName(mb_substr($secondary['name'], 0, 31));
            } else {
                // CSV has no sheets; keep the companion table in the same
                // file, separated by a blank line and its own header row.
                $writer->addRow(Row::fromValues([]));
                $writer->addRow(Row::fromValues([$secondary['name']]));
            }
            $writer->addRow(Row::fromValues($secondary['headings']));
            foreach ($secondary['rows'] as $row) {
                $writer->addRow(Row::fromValues($row));
            }
        }
        $writer->close();
    }

    echo urlencode(basename($filePath));
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    echo '';
}
