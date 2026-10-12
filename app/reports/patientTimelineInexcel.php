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
if (isset($_SESSION['timelineData']) && trim((string) $_SESSION['timelineData']) !== "") {

      $headings = ['PATIENT ID', 'PATIENT NAME', 'SEX', 'DATE OF BIRTH',  'FIRST SAMPLE', 'LAST SAMPLE','FACILITIES'];
   
      $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Patient-Timeline-Report-' . date('d-M-Y-H-i-s') . '.xlsx';

      $writer = new Writer();
      $writer->openToFile($filename);
      $writer->addRow(Row::fromValues($headings));
      $totalEvents = count($_SESSION['timelineData']['events']);
      $events = $_SESSION['timelineData']['events'];
      $row = $_SESSION['timelineData']['patient'];
      $out = [
               'patientId' => $row['id'],
               'name' => $row['names'][0],
               'sex' => $row['sexes'][0],
               'dob' => DateUtility::humanReadableDateFormat($row['dobs'][0]),
               'firstSample' => DateUtility::humanReadableDateFormat($events[0]['collected']),
               'lastSample' => DateUtility::humanReadableDateFormat($events[$totalEvents-1]['collected']),
               'facility' => $row['facilities'][0],
            ];
          
      $writer->addRow(Row::fromValues(array_values($out)));
      $writer->addRow(Row::fromValues([]));
      $writer->addRow(Row::fromValues(['Test History']));
      $historyHeadings = ['SAMPLE ID', 'SAMPLE TYPE','FACILITY', 'TESTING LAB', 'SAMPLE STATUS'];
      $writer->addRow(Row::fromValues($historyHeadings));

        foreach ($events as $row) {
         
            $out = [
               'sampleId' => $row['sampleId'],
               'sampleType' => $row['sampleType'],
               'facility' => $row['facility'],
               'lab' => $row['lab'],
               'status' => $row['statusName'],
            ];
          
            $writer->addRow(Row::fromValues(array_values($out)));

        }

     $writer->close();
     echo _downloadToken($filename);
}

