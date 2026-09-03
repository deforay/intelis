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
    // AJAX requests bypass the access control layer, so the page's own
    // privilege is checked here.
    _requirePrivilege('/reports/lab-performance-indicators.php');

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
        'testedToPrinted' => _translate('Tested to Result Printed'),
        'testedToReleased' => _translate('Tested to Result Released'),
        'collectionToReleased' => _translate('Collection to Result Released'),
    ];

    // Fetched once here rather than inside each arm below: the flat export rows
    // drop the denominators the totals need (the overview keeps no `outcomes`
    // column, turnaround time needs each stage's own n), so totals are computed
    // from these and not from the rows that get written out.
    $rawRows = match ($section) {
        'overview' => $indicators->getOverview($filters),
        'tat' => $indicators->getTat($filters),
        'volume' => $indicators->getVolume($filters),
        'failure' => $indicators->getFailure($filters),
        'rejection' => $indicators->getRejection($filters),
        'patients' => $indicators->getRepeatPatients($filters, 0, 100000)['rows'],
        default => [],
    };

    // [headings, rows, secondary sheet or null]
    [$headings, $rows, $secondary] = match ($section) {
        'overview' => [
            [
                _translate('Test'), _translate('Registered'), _translate('Samples Tested'),
                _translate('Results Available'), _translate('Awaiting a Result'),
                _translate('Manual Entry'), _translate('Analyzer Interface'), _translate('File Import'),
                _translate('Unclassified'), _translate('Failed'), _translate('Failure Rate (%)'),
                _translate('Re-tested'), _translate('Re-test Rate (%)'),
                _translate('Rejected'), _translate('Rejection Rate (%)')
            ],
            array_map(static fn(array $r): array => [
                $r['testName'], $r['registered'], $r['sampleTested'], $r['resulted'],
                $r['testedPending'], $r['manual'], $r['interface'],
                $r['fileImport'], $r['unclassified'], $r['failed'], $r['failureRate'],
                $r['retested'], $r['retestRate'],
                $r['rejected'], $r['rejectionRate']
            ], $rawRows),
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
            }, $rawRows),
            null
        ],
        'volume' => [
            [
                _translate('Period'), _translate('Lab'), _translate('Registered'),
                _translate('Samples Tested'), _translate('Results Available'),
                _translate('Awaiting a Result'),
                _translate('Manual Entry'), _translate('Analyzer Interface'), _translate('File Import'),
                _translate('Unclassified')
            ],
            array_map(static fn(array $r): array => [
                $r['period'], $r['lab'], $r['registered'], $r['sampleTested'], $r['resulted'],
                $r['testedPending'], $r['manual'], $r['interface'], $r['fileImport'], $r['unclassified']
            ], $rawRows),
            null
        ],
        'failure' => [
            [
                _translate('Period'), _translate('Lab'), _translate('Tests with an Outcome'),
                _translate('Failed'), _translate('Failure Rate (%)'),
                _translate('Re-tested'), _translate('Re-test Rate (%)')
            ],
            array_map(static fn(array $r): array => [
                $r['period'], $r['lab'], $r['tested'], $r['failed'], $r['failureRate'],
                $r['retested'], $r['retestRate']
            ], $rawRows),
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
            ], $rawRows),
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
            ], $rawRows),
            null
        ],
        'all' => [[], [], null],
        default => throw new \App\Exceptions\SystemException('Invalid indicator section'),
    };

    // Totals, on the same terms as the on-screen tables: counts are summed and
    // every rate is recomputed from the totalled numerator and denominator. A
    // rate is never the average of the rates above it -- a period with three
    // samples would weigh as much as one with three thousand.
    $total = static fn(string $field): int => (int) array_sum(array_column($rawRows, $field));
    $rate = static fn(int $numerator, int $denominator): ?float
        => $denominator > 0 ? round($numerator * 100 / $denominator, 2) : null;
    $label = _translate('Total');

    $totals = [];
    if (count($rawRows) > 1) {
        // Under a single row the total is just that row again.
        $totals = match ($section) {
            'overview' => [
                $label, $total('registered'), $total('sampleTested'), $total('resulted'),
                $total('testedPending'), $total('manual'), $total('interface'),
                $total('fileImport'), $total('unclassified'), $total('failed'),
                $rate($total('failed'), $total('outcomes')),
                $total('retested'), $rate($total('retested'), $total('outcomes')),
                $total('rejected'), $rate($total('rejected'), $total('registered')),
            ],
            'volume' => [
                $label, '', $total('registered'), $total('sampleTested'), $total('resulted'),
                $total('testedPending'), $total('manual'), $total('interface'),
                $total('fileImport'), $total('unclassified'),
            ],
            'failure' => [
                $label, '', $total('tested'), $total('failed'),
                $rate($total('failed'), $total('tested')),
                $total('retested'), $rate($total('retested'), $total('tested')),
            ],
            'rejection' => [
                $label, '', $total('received'), $total('rejected'),
                $rate($total('rejected'), $total('received')),
            ],
            // Each stage is averaged over the samples that actually reached it,
            // so the overall figure is weighted by those counts rather than by
            // how many periods happen to be in the range.
            'tat' => (static function () use ($rawRows, $tatStages, $label, $total): array {
                $row = [$label, $total('samples')];
                foreach (array_keys($tatStages) as $stage) {
                    $days = 0.0;
                    $n = 0;
                    foreach ($rawRows as $r) {
                        if ($r[$stage] !== null && (int) $r[$stage . 'N'] > 0) {
                            $days += (float) $r[$stage] * (int) $r[$stage . 'N'];
                            $n += (int) $r[$stage . 'N'];
                        }
                    }
                    $row[] = $n > 0 ? round($days / $n, 2) : null;
                    $row[] = $n;
                }
                return $row;
            })(),
            default => [],
        };
    }

    // The reasons sheets are a plain count, so they total the same way.
    if (!empty($secondary['rows']) && count($secondary['rows']) > 1) {
        $secondary['totals'] = [
            $label,
            (int) array_sum(array_column($secondary['rows'], 1)),
        ];
    }

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
                'totals' => $totals,
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
        if ($totals !== []) {
            $writer->addRow(Row::fromValues($totals));
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
            if (!empty($secondary['totals'])) {
                $writer->addRow(Row::fromValues($secondary['totals']));
            }
        }
        $writer->close();
    }

    echo _downloadToken($filePath);
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
