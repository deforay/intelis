<?php

namespace App\Utilities;

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

ini_set('memory_limit', '512M');
set_time_limit(300);
ini_set('max_execution_time', 300);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);


/*
 * Get data from your service/database.
 *
 * Replace this with your existing report query/service.
 */
if (isset($_SESSION['sampleTestingReport']) && trim((string) $_SESSION['sampleTestingReport']) !== "") {

     $headings = ['Facility', 'State', 'District', 'Samples Collected', 'Awaiting Approval', 'Tested'];
    
     $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-CD4-Sample-Testing-Report-' . date('d-M-Y-H-i-s') . '.xlsx';

     $writer = new Writer();
     $writer->openToFile($filename);
     $writer->addRow(Row::fromValues($headings));

    $resultSet = $db->rawQueryGenerator($_SESSION['sampleTestingReport']);

     $rows = [];
        foreach ($resultSet as $row) {
            $out = [
                'facility' => (string) $row['facility_name'],
                'state' => (string) ($row['facility_state'] ?? ''),
                'district' => (string) ($row['facility_district'] ?? ''),
                'total' => (int) $row['total'],
                'approval' => $row['awaitingApproval'],
                'tested' => $row['tested'],

            ];
          
            $writer->addRow(Row::fromValues(array_values($out)));

        }

     $writer->close();
     echo _downloadToken($filename);
}

