<?php

// The Excel export of a Testing Target Report. Included by each test's export
// script after it has queued itself, with $testType, $reportPage and
// $reportTitle set. It writes the rows the page shows for the same filters.

use App\Registries\AppRegistry;
use App\Services\CommonService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\ExportJobUtility;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;
use App\Services\TestingTargetReportService;

require_once __DIR__ . '/_testing-target-common.php';

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

_requirePrivilege($reportPage);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
/** @var TestingTargetReportService $targets */
$targets = ContainerRegistry::get(TestingTargetReportService::class);

$filters = _testingTargetFilters($_POST, $general);
$rows = $targets->rows(
    $testType,
    $filters['from'],
    $filters['to'],
    $filters['labIds'],
    $filters['targetType'],
    $filters['scope'],
    $filters['fill']
);

$filename = 'InteLIS-' . strtoupper($testType) . '-Testing-Target-Report-' . date('d-M-Y-H-i-s') . '.xlsx';

$writer = new Writer();
$writer->openToFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
$writer->addRow(Row::fromValues([$reportTitle]));
$writer->addRow(Row::fromValues([
    _translate('Date Range') . ': ' . date('d-M-Y', strtotime($filters['from']))
        . ' - ' . date('d-M-Y', strtotime($filters['to'])),
]));
$writer->addRow(Row::fromValues(['']));
$writer->addRow(Row::fromValues([
    _translate('Testing Lab'),
    _translate('Month'),
    _translate('Number of Samples Received'),
    _translate('Number of Samples Rejected'),
    _translate('Number of Samples Tested'),
    _translate('Monthly Test Target'),
    _translate('Target Met'),
]));
$yes = _translate('Yes');
$no = _translate('No');
foreach ($rows as $row) {
    ExportJobUtility::tick();
    $writer->addRow(Row::fromValues([
        html_entity_decode($row['labName'], ENT_QUOTES, 'UTF-8'),
        _testingTargetMonth($row['month']),
        $row['received'],
        $row['rejected'],
        $row['tested'],
        $row['target'] ?? '',
        $row['met'] === null ? '' : ($row['met'] ? $yes : $no),
    ]));
}
$writer->close();

echo _downloadToken($filename);
