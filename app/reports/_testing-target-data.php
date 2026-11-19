<?php

// The data behind a Testing Target Report page, and the count behind its
// dashboard alert. Included by each test's own endpoint, which sets $testType
// and $reportPage.

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;
use App\Services\TestingTargetReportService;

require_once __DIR__ . '/_testing-target-common.php';

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

// AJAX requests bypass the access control layer, so the page's own privilege
// is checked here.
_requirePrivilege($reportPage);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
/** @var TestingTargetReportService $targets */
$targets = ContainerRegistry::get(TestingTargetReportService::class);

$filters = _testingTargetFilters($_POST, $general);

if (($_POST['labsBelowTarget'] ?? '') === 'yes') {
    echo json_encode(['labsBelowTarget' => $targets->labsBelowTarget(
        $testType,
        $filters['from'],
        $filters['to'],
        $filters['labIds'],
        $filters['scope'],
        null,
        $filters['fill']
    )]);
    return;
}

$rows = $targets->rows(
    $testType,
    $filters['from'],
    $filters['to'],
    $filters['labIds'],
    $filters['targetType'],
    $filters['scope'],
    $filters['fill']
);

$yes = _translate('Yes');
$no = _translate('No');
$aaData = [];
foreach ($rows as $row) {
    $aaData[] = [
        $row['labName'],
        $row['month'],
        $row['received'],
        $row['rejected'],
        $row['tested'],
        $row['target'] ?? '',
        $row['met'] === null ? '' : ($row['met'] ? $yes : $no),
    ];
}

// The grid's search box: a row stays when any cell shown contains the text.
$search = mb_strtolower(trim((string) ($_POST['sSearch'] ?? '')));
if ($search !== '') {
    $aaData = array_values(array_filter($aaData, static function (array $cells) use ($search): bool {
        $shown = $cells;
        $shown[0] = html_entity_decode((string) $shown[0], ENT_QUOTES, 'UTF-8');
        $shown[1] = _testingTargetMonth((string) $shown[1]);
        foreach ($shown as $cell) {
            if (str_contains(mb_strtolower((string) $cell), $search)) {
                return true;
            }
        }
        return false;
    }));
}

// Sorted by the column the reader chose. Month sorts on its Y-m value and is
// shown as Mon-YYYY afterwards, so Jan-2027 follows Dec-2026.
$column = (int) ($_POST['iSortCol_0'] ?? 0);
$descending = ($_POST['sSortDir_0'] ?? 'asc') === 'desc';
if ($column >= 0 && $column < 7) {
    usort($aaData, static function (array $a, array $b) use ($column, $descending): int {
        $order = [$a[$column], $a[0], $a[1]] <=> [$b[$column], $b[0], $b[1]];
        return $descending ? -$order : $order;
    });
}

$total = count($aaData);
$start = max(0, (int) ($_POST['iDisplayStart'] ?? 0));
$length = (int) ($_POST['iDisplayLength'] ?? -1);
if ($length > 0) {
    $aaData = array_slice($aaData, $start, $length);
}
foreach ($aaData as &$cells) {
    // Names are stored entity-encoded already: escape without doubling.
    $cells[0] = htmlspecialchars((string) $cells[0], ENT_QUOTES, 'UTF-8', false);
    $cells[1] = _testingTargetMonth($cells[1]);
}
unset($cells);

echo json_encode([
    'sEcho' => (int) ($_POST['sEcho'] ?? 0),
    'iTotalRecords' => $total,
    'iTotalDisplayRecords' => $total,
    'aaData' => $aaData,
]);
