<?php

use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);

     /** @var TestRequestsService $testRequestsService */
     $testRequestsService = ContainerRegistry::get(TestRequestsService::class);
     $testRequestsService->processSampleCodeQueue();

     $barCodePrinting = $general->getGlobalConfig('bar_code_printing');


     $aColumns = ['vl.sample_code', 'ty.test_standard_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'l.facility_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_type_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name'];
     $orderColumns = ['vl.sample_code', 'ty.test_standard_name', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'l.facility_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_type_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'];
     if ($_SESSION['instance']['type'] !=  'standalone') {
          array_splice($aColumns, 1, 0, ['vl.remote_sample_code']);
          array_splice($orderColumns, 1, 0, ['vl.remote_sample_code']);
     }

     $sOffset = $sLimit = null;
     if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
          $sOffset = $_POST['iDisplayStart'];
          $sLimit = $_POST['iDisplayLength'];
     }

     $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

     $columnSearch = $general->multipleColumnSearch($_POST['sSearch'], $aColumns);
     $sWhere = [];
     if (!empty($columnSearch) && $columnSearch != '') {
          $sWhere[] = $columnSearch;
     }

     if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
          $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
     }

     $sQuery = "SELECT vl.*,
          ty.test_standard_name,
          s.sample_type_name,
          b.batch_code,
          ts.status_name,
          f.facility_name,
          l.facility_name as lab_name,
          f.facility_code,
          f.facility_state,
          f.facility_district,
          fs.funding_source_name,
          i.i_partner_name

          FROM form_generic as vl

          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          LEFT JOIN facility_details as l ON vl.lab_id=l.facility_id
          LEFT JOIN r_generic_sample_types as s ON s.sample_type_id=vl.specimen_type
          LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
          LEFT JOIN r_funding_sources as fs ON fs.funding_source_id=vl.funding_source
          LEFT JOIN r_test_types as ty ON vl.test_type=ty.test_type_id
          LEFT JOIN r_implementation_partners as i ON i.i_partner_id=vl.implementing_partner";

     if (isset($_POST['testType']) && $_POST['testType'] != "") {
          $sWhere[] = " vl.test_type like " . $_POST['testType'];
     }

     if (!empty($sWhere)) {
          $sWhere = ' WHERE ' . implode(' AND ', $sWhere);
          $sQuery = "$sQuery $sWhere";
     }

     if (!empty($sOrder) && $sOrder !== '') {
          $_SESSION['vlRequestData']['sOrder'] = $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
          $sQuery = "$sQuery ORDER BY $sOrder";
     }

     $_SESSION['genericRequestQuery'] = $sQuery;

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
     }

     [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

     $_SESSION['genericRequestQueryCount'] = $resultCount;

     $output = [
          "sEcho" => (int) $_POST['sEcho'],
          "iTotalRecords" => $resultCount,
          "iTotalDisplayRecords" => $resultCount,
          "aaData" => []
     ];

     $editRequest = false;
     if (_isAllowed("/generic-tests/requests/edit-request.php")) {
          $editRequest = true;
     }

     $sampleCodeColumn = $general->isSTSInstance() ? 'remote_sample_code' : 'sample_code';

     foreach ($rResult as $aRow) {
          $row = [];

          $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
          $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);

          $patientFname = $general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_id']);
          $patientMname = $general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_id']);
          $patientLname = $general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_id']);

          if (empty($aRow[$sampleCodeColumn]) && empty($aRow['sample_code'])) {
               $aRow[$sampleCodeColumn] = _translate("Generating...");
          }

          $sampleCodeTooltip = '';
          $patientTooltip = '';
          if (!empty($aRow['sample_package_code'])) {
               $sampleCodeTooltip .= _translate("Manifest Code", true) . " : " . $aRow['sample_package_code'] . '<br>';
          }
          if (!empty($aRow['batch_code'])) {
               $sampleCodeTooltip .= _translate("Batch Code", true) . " : " . $aRow['batch_code'];
          }
          if (!empty($aRow['patient_dob'])) {
               $patientTooltip .= _translate("Patient DoB", true) . " : " . DateUtility::humanReadableDateFormat($aRow['patient_dob']) . '<br>';
          }
          if (!empty($aRow['patient_age_in_years'])) {
               $patientTooltip .= _translate("Patient Age", true) . " : " . $aRow['patient_age_in_years'] . '<br>';
          }
          if (!empty($aRow['patient_gender'])) {
               $patientTooltip .= _translate("Patient Sex", true) . " : " . $aRow['patient_gender'] . '<br>';
          }

          if ($sampleCodeTooltip !== '' && $sampleCodeTooltip !== '0') {
               $row[] = '<span class="top-tooltip" title="' . $sampleCodeTooltip . '">' . $aRow['sample_code'] . '</span>';
          } else {
               $row[] = '<span>' . $aRow['sample_code'] . '</span>';
          }
          if (!$general->isStandaloneInstance()) {
               if ($sampleCodeTooltip !== '' && $sampleCodeTooltip !== '0') {
                    $row[] = '<span class="top-tooltip" title="' . $sampleCodeTooltip . '">' . $aRow['remote_sample_code'] . '</span>';
               } else {
                    $row[] = '<span>' . $aRow['remote_sample_code'] . '</span>';
               }
          }
          $row[] = $aRow['test_standard_name'];
          $row[] = $aRow['sample_collection_date'];
          $row[] = $aRow['batch_code'];
          if ($patientTooltip !== '' && $patientTooltip !== '0') {
               $row[] = '<span class="top-tooltip" title="' . $patientTooltip . '">' . $aRow['patient_id'] . '</span>';
          } else {
               $row[] = '<span>' . $aRow['patient_id'] . '</span>';
          }
          $row[] = trim(implode(" ", [$patientFname, $patientMname, $patientLname]));
          $row[] = $aRow['lab_name'];
          $row[] = $aRow['facility_name'];
          $row[] = $aRow['facility_state'];
          $row[] = $aRow['facility_district'];
          $row[] = $aRow['sample_type_name'];
          $row[] = $aRow['result'];
          $row[] = $aRow['last_modified_datetime'];
          $row[] = $aRow['status_name'];

          if ($editRequest) {
               $row[] = '<a href="/generic-tests/requests/edit-request.php?id=' . base64_encode((string) $aRow['sample_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
          } else {
               $row[] = "";
          }

          $output['aaData'][] = $row;
     }
     echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
     LoggerUtility::logError($e->getMessage(), [
          'trace' => $e->getTraceAsString(),
          'file' => $e->getFile(),
          'line' => $e->getLine(),
          'last_db_error' => $db->getLastError(),
          'last_db_query' => $db->getLastQuery(),
     ]);
}
