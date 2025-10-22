<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$excel = new Spreadsheet();
$sheet = $excel->getActiveSheet();
$sheet->setTitle('Lab Sync Status');

// Use the query and parameters stored in session from get-sync-status.php
$query = $_SESSION['labSyncStatus'] ?? '';
$params = $_SESSION['labSyncStatusParams'] ?? [];

if (empty($query)) {
    echo '';
    exit;
}

// Execute query using session data
$resultSet = $db->rawQueryGenerator($query, $params);

// Calculate thresholds
$twoWeeksAgo = strtotime('-2 weeks');
$fourWeeksAgo = strtotime('-4 weeks');

// Set up spreadsheet headers and styling
$sheet->setCellValue('A1', 'Lab Sync Status Report');
$sheet->setCellValue('A2', 'Generated on: ' . DateUtility::humanReadableDateFormat(date('Y-m-d H:i:s'), true));

// Note: Filters are already applied in the query stored in session
$headerRow = 4;

// Headers
$headings = [
    _translate("Lab Name"),
    _translate("Last Sync Done On"),
    _translate("Latest Results Sync from Lab"),
    _translate("Latest Requests Sync from STS"),
    _translate("LIS Version"),
    _translate("Sync Status"),
    _translate("Days Since Last Sync")
];

$sheet->fromArray($headings, null, 'A' . $headerRow);

// Style headers
$headerRange = 'A' . $headerRow . ':I' . $headerRow;
$sheet->getStyle($headerRange)->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['argb' => Color::COLOR_WHITE],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => '336699'],
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
        ],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
]);

// Data rows
$output = [];
$statusSummary = ['active' => 0, 'warning' => 0, 'critical' => 0];

if (!empty($resultSet)) {
    foreach ($resultSet as $aRow) {
        $row = [];

        // Determine sync status
        $latestSync = (int)$aRow['latest_timestamp'];
        if ($latestSync > $twoWeeksAgo) {
            $syncStatus = 'Active';
            $color = 'green';
            $statusSummary['active']++;
        } elseif ($latestSync > $fourWeeksAgo) {
            $syncStatus = 'Warning';
            $color = 'yellow';
            $statusSummary['warning']++;
        } else {
            $syncStatus = 'Critical';
            $color = 'red';
            $statusSummary['critical']++;
        }

        // Calculate days since last sync
        $daysSinceSync = $latestSync ? floor((time() - $latestSync) / 86400) : 'Never';

        $row[] = $aRow['facility_name'] ?? '';
        $row[] = $latestSync ? DateUtility::humanReadableDateFormat(date('Y-m-d H:i:s', $latestSync), true) : 'Never';
        $row[] = DateUtility::humanReadableDateFormat($aRow['lastResultsSync'] ?? '', true) ?: 'Never';
        $row[] = DateUtility::humanReadableDateFormat($aRow['lastRequestsSync'] ?? '', true) ?: 'Never';
        $row[] = $aRow['version'] ?? '-';
        $row[] = $syncStatus;
        $row[] = $daysSinceSync;

        $output[] = ['data' => $row, 'color' => $color];
    }
}

// Add data to spreadsheet
$currentRow = $headerRow + 1;
foreach ($output as $rowData) {
    $sheet->fromArray($rowData['data'], null, 'A' . $currentRow);

    // Apply row coloring based on sync status
    $rowRange = 'A' . $currentRow . ':I' . $currentRow;
    $fillColor = '';

    switch ($rowData['color']) {
        case 'green':
            $fillColor = 'C8E6C9'; // Light green
            break;
        case 'yellow':
            $fillColor = 'FFF3E0'; // Light orange
            break;
        case 'red':
            $fillColor = 'FFCDD2'; // Light red
            break;
    }

    if ($fillColor) {
        $sheet->getStyle($rowRange)->applyFromArray([
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => $fillColor],
            ],
        ]);
    }

    // Add borders to all cells
    $sheet->getStyle($rowRange)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => Color::COLOR_BLACK],
            ],
        ],
    ]);

    $currentRow++;
}

// Add summary section
$summaryStartRow = $currentRow + 2;
$sheet->setCellValue('A' . $summaryStartRow, 'Summary:');
$sheet->setCellValue('A' . ($summaryStartRow + 1), 'Active Labs (Green): ' . $statusSummary['active']);
$sheet->setCellValue('A' . ($summaryStartRow + 2), 'Warning Labs (Yellow): ' . $statusSummary['warning']);
$sheet->setCellValue('A' . ($summaryStartRow + 3), 'Critical Labs (Red): ' . $statusSummary['critical']);
$sheet->setCellValue('A' . ($summaryStartRow + 4), 'Total Labs: ' . (count($output)));

// Style summary section
$summaryRange = 'A' . $summaryStartRow . ':A' . ($summaryStartRow + 4);
$sheet->getStyle($summaryRange)->applyFromArray([
    'font' => ['bold' => true],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'F5F5F5'],
    ],
]);

// Auto-size columns
foreach (range('A', 'I') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}

// Set page orientation and margins for better printing
$sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
$sheet->getPageMargins()->setTop(0.5);
$sheet->getPageMargins()->setRight(0.25);
$sheet->getPageMargins()->setLeft(0.25);
$sheet->getPageMargins()->setBottom(0.5);

// Add header and footer for printing
$sheet->getHeaderFooter()->setOddHeader('&C&HLab Sync Status Report');
$sheet->getHeaderFooter()->setOddFooter('&L&D &T&RPage &P of &N');

// Freeze panes at header row
$sheet->freezePane('A' . ($headerRow + 1));

// Apply additional styling to title
$sheet->getStyle('A1')->applyFromArray([
    'font' => [
        'bold' => true,
        'size' => 16,
        'color' => ['argb' => '336699'],
    ],
]);

$sheet->getStyle('A2')->applyFromArray([
    'font' => [
        'italic' => true,
        'size' => 10,
    ],
]);

// Create the file
$writer = IOFactory::createWriter($excel, 'Xlsx');
$filename = 'InteLIS-LAB-SYNC-STATUS-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomNumber(6) . '.xlsx';
$writer->save(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);

echo urlencode(basename($filename));
