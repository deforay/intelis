<?php

use App\Services\DatabaseService;
use App\Services\VlService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\SampleCountUtility;

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
               DATE_FORMAT(vl.sample_tested_datetime,'%d-%b-%Y') as sampleTestDate,
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
               vl.result,
               r_i_p.i_partner_name
          FROM form_vl as vl
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          LEFT JOIN r_vl_sample_type as s ON vl.specimen_type=s.sample_id
          LEFT JOIN instruments as i ON vl.instrument_id=i.instrument_id
          LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner
          INNER JOIN facility_details as l ON vl.lab_id=l.facility_id";

$sWhere[] = " vl.vl_result_category = 'not suppressed' AND vl.patient_age_in_years IS NOT NULL AND vl.patient_gender IS NOT NULL AND vl.current_regimen IS NOT NULL ";

/* State filter */
if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
     $sWhere[] = ' f.facility_state_id = ' . (int) $_POST['state'];
}

/* District filters */
if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
     $sWhere[] = ' f.facility_district_id = ' . (int) $_POST['district'];
}
/* Facility filter */
if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
     $sWhere[] = ' f.facility_id IN (' . $db->inIntList($_POST['facilityName']) . ')';
}

if (isset($_POST['gender']) && $_POST['gender'] != '') {
     if (trim((string) $_POST['gender']) === "unreported") {
          $sWhere[] = ' (vl.patient_gender = "unreported" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
     } else {
          $sWhere[] = ' vl.patient_gender ="' . $db->escape((string) $_POST['gender']) . '"';
     }
}


if (isset($_POST['pregnancy']) && trim((string) $_POST['pregnancy']) !== '') {
     $sWhere[] = " vl.is_patient_pregnant = '" . $db->escape((string) $_POST['pregnancy']) . "' ";
}

if (isset($_POST['breastfeeding']) && trim((string) $_POST['breastfeeding']) !== '') {
     $sWhere[] = " vl.is_patient_breastfeeding = '" . $db->escape((string) $_POST['breastfeeding']) . "' ";
}

if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) !== '') {
     $sWhere[] = ' vl.implementing_partner = "' . $db->escape(base64_decode((string) $_POST['implementingPartner'])) . '"';
}

if (
     is_numeric($_POST['minAge']) &&
     is_numeric($_POST['maxAge']) &&
     $_POST['maxAge'] >= $_POST['minAge']
) {
     $sWhere[] = ' vl.patient_age_in_years BETWEEN ' . (int) $_POST['minAge'] . ' AND ' . (int) $_POST['maxAge'] . ' ';
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

if ($labScope = $general->labScopeWhere('vl')) {
     $sWhere[] = $labScope;
}

if (!empty($sWhere)) {
     // A cancelled sample was called off before testing, so it is not work
     // this report should count.
     $sWhere[] = SampleCountUtility::countableWhere('vl');
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
     if ($patientId === '') {
          // A blank patient ID is not one patient. Grouped together, such rows
          // would clump into a single fake "virologic failure" patient.
          $grouped['blank-id-' . count($grouped)][] = $aRow;
     } else {
          $grouped[$patientId][] = $aRow;
     }
}

if (empty($grouped)) {
     return null;
}

$headings = [
     _translate('Patient ID'),
     _translate('Sample Date'),
     _translate('Sample Test Date'),
     _translate('Facility Name'),
     _translate('Facility Code'),
     _translate('Sample Name'),
     _translate('Testing Platform'),
     _translate('Lab Name'),
     _translate('Age'),
     _translate('Sex'),
     _translate('Pregnant'),
     _translate('Breastfeeding'),
     _translate('ART Start Date'),
     _translate('Regimen'),
     _translate('Current Regimen Start Date'),
     _translate('VL Result'),
     _translate('Implementing Partner')
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

echo _downloadToken($filename);
