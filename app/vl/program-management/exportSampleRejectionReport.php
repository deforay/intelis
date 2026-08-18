<?php

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\SampleRejectionUtility;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
     $saved = $_SESSION['vlRejectedSamplesQuery'] ?? null;
     if (empty($saved['query'])) {
          echo '';
          return;
     }

     $rResult = $db->rawQuery($saved['query'], $saved['params'] ?? []);

     $headings = [
          _translate("Lab Name"),
          _translate("Facility Name"),
          _translate("Rejection Reason"),
          _translate("Reason Category"),
          _translate("No. of Rejected Samples")
     ];

     // Filters the report was run with, as one line above the table. Arrays
     // (the multi-select clinic list) are flattened rather than cast to string.
     $filterLabels = [
          'sampleCollectionDate' => _translate("Sample Collection Date"),
          'lab_name' => _translate("Lab"),
          'clinic_name' => _translate("Clinic Name"),
          'sample_type' => _translate("Sample Type"),
     ];
     $appliedFilters = [];
     foreach ($filterLabels as $key => $label) {
          $value = $_POST[$key] ?? null;
          if (is_array($value)) {
               $value = implode(', ', array_filter(array_map('strval', $value)));
          }
          $value = trim((string) $value);
          if ($value !== '' && $value !== '-- Select --') {
               $appliedFilters[] = "$label : $value";
          }
     }

     $filename = 'InteLIS-Rejected-Data-report' . date('d-M-Y-H-i-s') . '.xlsx';
     $filePath = TEMP_PATH . DIRECTORY_SEPARATOR . $filename;

     $writer = new Writer();
     $writer->openToFile($filePath);
     if ($appliedFilters !== []) {
          $writer->addRow(Row::fromValues([implode('   ', $appliedFilters)]));
          $writer->addRow(Row::fromValues([]));
     }
     $writer->addRow(Row::fromValues($headings));

     $totalRejected = 0;
     foreach ($rResult as $aRow) {
          $totalRejected += (int) $aRow['total'];
          $writer->addRow(Row::fromValues([
               $aRow['labname'] ?? '',
               $aRow['facility_name'] ?? '',
               SampleRejectionUtility::reasonLabel($aRow['rejection_reason_name'] ?? null),
               trim((string) $aRow['rejection_type']) ?: _translate("Unspecified"),
               (int) $aRow['total']
          ]));
     }
     $writer->addRow(Row::fromValues([]));
     $writer->addRow(Row::fromValues(['', '', '', _translate("Total"), $totalRejected]));
     $writer->close();

     echo basename($filePath);
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
