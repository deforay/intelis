<?php

use App\Services\DatabaseService;
use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

ini_set('memory_limit', '512M');
set_time_limit(300);
ini_set('max_execution_time', 300);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$keyFromGlobalConfig = $general->getGlobalConfig('key');

$sQuery = "SELECT
               vl.patient_art_no,
               DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y') as sampleDate,
               f.facility_name,
               f.facility_code,
               s.sample_name,
               i.machine_name,
               l.facility_name as labName,
               vl.patient_age_in_years,
               UCASE(vl.patient_gender),
               UCASE(vl.is_patient_pregnant),
               UCASE(vl.is_patient_breastfeeding),
               vl.is_encrypted,
               DATE_FORMAT(vl.treatment_initiated_date,'%d-%b-%Y') as artStartDate,
               vl.current_regimen,
               DATE_FORMAT(vl.date_of_initiation_of_current_regimen,'%d-%b-%Y') as regStartDate,
               vl.result
          FROM form_vl as vl
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          LEFT JOIN r_vl_sample_type as s ON vl.specimen_type=s.sample_id
          LEFT JOIN instruments as i ON vl.instrument_id=i.instrument_id
          INNER JOIN facility_details as l ON vl.lab_id=l.facility_id";

$sWhere[] = " vl.vl_result_category = 'not suppressed' AND vl.patient_age_in_years IS NOT NULL AND vl.patient_gender IS NOT NULL AND vl.current_regimen IS NOT NULL ";

/* State filter */
if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
     $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
}

/* District filters */
if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
     $sWhere[] = " f.facility_district_id = '" . $_POST['district'] . "' ";
}
/* Facility filter */
if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
     $facilityIdsString = implode(',', $_POST['facilityName']);
     $sWhere[] = " f.facility_id IN ($facilityIdsString)";
}

if (isset($_POST['gender']) && $_POST['gender'] != '') {
     if (trim((string) $_POST['gender']) === "unreported") {
          $sWhere[] = ' (vl.patient_gender = "unreported" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
     } else {
          $sWhere[] = ' vl.patient_gender ="' . $_POST['gender'] . '"';
     }
}


if (isset($_POST['pregnancy']) && trim((string) $_POST['pregnancy']) !== '') {
     $sWhere[] = " vl.is_patient_pregnant = '" . $_POST['pregnancy'] . "' ";
}

if (isset($_POST['breastfeeding']) && trim((string) $_POST['breastfeeding']) !== '') {
     $sWhere[] = " vl.is_patient_breastfeeding = '" . $_POST['breastfeeding'] . "' ";
}

if (
     is_numeric($_POST['minAge']) &&
     is_numeric($_POST['maxAge']) &&
     $_POST['maxAge'] >= $_POST['minAge']
) {
     $sWhere[] = " vl.patient_age_in_years BETWEEN {$_POST['minAge']} AND {$_POST['maxAge']} ";
}

/* Sample collection date filter */
if (!in_array(trim((string) $_POST['sampleCollectionDate']), ['', '0'], true)) {
     [$sampleCollectionDateStart, $sampleCollectionDateEnd] = DateUtility::convertDateRange($_POST['sampleCollectionDate'], includeTime: true);
     $sWhere[] = "vl.sample_collection_date BETWEEN '$sampleCollectionDateStart' AND '$sampleCollectionDateEnd'";
}
/* Sample test date filter */

if (!in_array(trim((string) $_POST['sampleTestDate']), ['', '0'], true)) {
     [$sampleTestDateStart, $sampleTestDateEnd] = DateUtility::convertDateRange($_POST['sampleTestDate'], includeTime: true);
     $sWhere[] = "vl.sample_tested_datetime BETWEEN '$sampleTestDateStart' AND '$sampleTestDateEnd'";
}

if (!empty($_SESSION['facilityMap'])) {
     $sWhere[] = "vl.facility_id IN ({$_SESSION['facilityMap']})";
}

if (!empty($sWhere)) {
     $sQuery = $sQuery . " WHERE " . implode(" AND ", $sWhere);
}
$sQuery .= " ORDER BY f.facility_name asc, patient_art_no asc, sample_collection_date asc";

$resultSet = $db->rawQueryGenerator($sQuery);

// Group rows by patient ID — streamed from generator, no raw result array held
$grouped = [];
foreach ($resultSet as $aRow) {
     if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] === 'yes') {
          $aRow['patient_art_no'] = CommonService::decrypt($aRow['patient_art_no'], base64_decode((string) $keyFromGlobalConfig));
     }
     unset($aRow['is_encrypted']);
     $patientId = trim((string) $aRow['patient_art_no']);
     $grouped[$patientId][] = $aRow;
}

if (empty($grouped)) {
     return null;
}

$headings = [
     'Patient ID',
     'Sample Date',
     'Facility Name',
     'Facility Code',
     'Sample Name',
     'Testing Platform',
     'Lab Name',
     'Age',
     'Sex',
     'Pregnant',
     'Breastfeeding',
     'ART Start Date',
     'Regimen',
     'Current Regimen Start Date',
     'VL Result'
];

$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-HIGH-VL-AND-VIROLOGIC-FAILURE-REPORT-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomString(5) . '.xlsx';

$writer = new Writer();
$writer->openToFile($filename);

// Sheet 1: VL - Not Suppressed
$vlnsSheet = $writer->getCurrentSheet();
$vlnsSheet->setName('VL - Not Suppressed');
$writer->addRow(Row::fromValues($headings));

// Sheet 2: Virologic Failure
$vfSheet = $writer->addNewSheetAndMakeItCurrent();
$vfSheet->setName('Virologic Failure');
$writer->addRow(Row::fromValues($headings));

$rowCount = 0;
foreach ($grouped as $rows) {
     if (count($rows) > 1) {
          // Virologic Failure — show patient ID only on first row
          $writer->setCurrentSheet($vfSheet);
          $isFirst = true;
          foreach ($rows as $row) {
               if (!$isFirst) {
                    $row['patient_art_no'] = '';
               }
               $writer->addRow(Row::fromValues(array_values($row)));
               $isFirst = false;
          }
     } else {
          // VL - Not Suppressed
          $writer->setCurrentSheet($vlnsSheet);
          $writer->addRow(Row::fromValues(array_values($rows[0])));
     }

     $rowCount++;
     if ($rowCount % 5000 === 0) {
          gc_collect_cycles();
     }
}
unset($grouped);

$writer->close();

echo urlencode(basename($filename));
