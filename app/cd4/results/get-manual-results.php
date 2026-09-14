<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\ACCEPTED;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Utilities\DataTableUtility;
use App\Registries\ContainerRegistry;
use App\Utilities\ListingFilterClauseBuilder;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {


     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);

     $sarr = $general->getSystemConfig();
     $key = (string) $general->getGlobalConfig('key');


     /** @var FacilitiesService $facilitiesService */
     $facilitiesService = ContainerRegistry::get(FacilitiesService::class);


     $tableName = "form_cd4";
     $primaryKey = "cd4_id";

     $sampleCode = 'sample_code';
     $aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 's.sample_name', 'vl.cd4_result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name'];
     $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 's.sample_name', 'vl.cd4_result', "vl.last_modified_datetime", 'ts.status_name'];
     if ($general->isSTSInstance()) {
          $sampleCode = 'remote_sample_code';
     } elseif ($general->isStandaloneInstance()) {
          $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
          $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
     }

     /* Indexed column (used for fast and accurate table cardinality) */
     $sIndexColumn = $primaryKey;

     $sTable = $tableName;

     [$sOffset, $sLimit] = DataTableUtility::paging($_POST);


     $sOrder = "";
     if (isset($_POST['iSortCol_0'])) {
          $sOrder = "";
          for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
               if ($_POST['bSortable_' . (int) $_POST['iSortCol_' . $i]] == "true" && !empty($orderColumns[(int) $_POST['iSortCol_' . $i]])) {
                    $sOrder .= $orderColumns[(int) $_POST['iSortCol_' . $i]] . "
				 	" . (strtolower(trim((string) ($_POST['sSortDir_' . $i] ?? ''))) === 'desc' ? 'DESC' : 'ASC') . ", ";
               }
          }
          $sOrder = substr_replace($sOrder, "", -2);
     }
     //echo $sOrder;


     $sWhere = [];
     if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
          $searchArray = explode(" ", (string) $_POST['sSearch']);
          $sWhereSub = "";
          foreach ($searchArray as $search) {
               if ($sWhereSub === "") {
                    $sWhereSub .= " (";
               } else {
                    $sWhereSub .= " AND (";
               }
               $colSize = count($aColumns);

               for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                         $sWhereSub .= $aColumns[$i] . " LIKE '%" . $db->escape($search) . "%' OR ";
                    } else {
                         $sWhereSub .= $aColumns[$i] . " LIKE '%" . $db->escape($search) . "%' ";
                    }
               }
               $sWhereSub .= ") ";
          }
          $sWhere[] = $sWhereSub;
     }



     $sQuery = "SELECT vl.cd4_id,
               vl.sample_code,
               vl.remote_sample,
               vl.remote_sample_code,
               b.batch_code,
               vl.sample_collection_date,
               vl.sample_tested_datetime,
               vl.patient_art_no,
               vl.patient_first_name,
               vl.patient_middle_name,
               vl.patient_last_name,
               f.facility_name,
               f.facility_district,
               f.facility_state,
               testingLab.facility_name as lab_name,
               s.sample_name as sample_name,
               vl.cd4_result,
               vl.reason_for_cd4_testing,
               vl.last_modified_datetime,
               vl.cd4_test_platform,
               vl.result_status,
               vl.request_clinician_name,
               vl.requesting_phone,
               vl.patient_responsible_person,
               vl.patient_mobile_number,
               vl.consent_to_receive_sms,
               vl.lab_technician,
               vl.patient_gender,
               vl.locked,
               ts.status_name,
               vl.result_approved_datetime,
               vl.result_reviewed_datetime,
               vl.sample_received_at_hub_datetime,
               vl.sample_received_at_lab_datetime,
               vl.result_dispatched_datetime,
               vl.result_printed_datetime,
               vl.result_approved_by,
               vl.is_encrypted
               FROM form_cd4 as vl
               LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
               LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
               LEFT JOIN facility_details as testingLab ON vl.lab_id=testingLab.facility_id
               LEFT JOIN r_cd4_sample_types as s ON s.sample_id=vl.specimen_type
               INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status ";


     [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
     [$t_start_date, $t_end_date] = DateUtility::convertDateRange($_POST['sampleTestDate'] ?? '');

     $sWhere = [...$sWhere, ...ListingFilterClauseBuilder::clauses($db, $_POST, [
          'batchCode' => ['b.batch_code', ListingFilterClauseBuilder::EQUALS],
          'manifestCode' => ['vl.sample_package_code', ListingFilterClauseBuilder::EQUALS],
          'district' => ['f.facility_district_id', ListingFilterClauseBuilder::EQUALS],
          'state' => ['f.facility_state_id', ListingFilterClauseBuilder::EQUALS],
          'patientId' => ['vl.patient_art_no', ListingFilterClauseBuilder::CONTAINS],
          'patientName' => ["CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", ListingFilterClauseBuilder::CONTAINS],
          'sampleType' => ['s.sample_id', ListingFilterClauseBuilder::EQUALS],
          'facilityName' => ['f.facility_id', ListingFilterClauseBuilder::INT_LIST],
          'vlLab' => ['vl.lab_id', ListingFilterClauseBuilder::INT_LIST],
          'artNo' => ['vl.patient_art_no', ListingFilterClauseBuilder::CONTAINS],
     ])];


     if (!empty($_POST['sampleCollectionDate'])) {

          if (trim((string) $start_date) === trim((string) $end_date)) {
               $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
          } else {
               $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
          }
     }

     if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
          if (trim((string) $t_start_date) === trim((string) $t_end_date)) {
               $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $t_start_date . '"';
          } else {
               $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $t_start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $t_end_date . '"';
          }
     }
     if (isset($_POST['status']) && trim((string) $_POST['status']) !== '') {
          if ($_POST['status'] == 'no_result') {
               $statusCondition = '  (vl.cd4_result is NULL OR vl.cd4_result = "") AND vl.result_status = ' . RECEIVED_AT_TESTING_LAB;
          } elseif ($_POST['status'] == 'result') {
               $statusCondition = ' (vl.cd4_result is NOT NULL AND vl.cd4_result != "") ';
          } else {
               $statusCondition = ' vl.is_sample_rejected = "yes" AND vl.result_status = ' . REJECTED;
          }
          $sWhere[] = $statusCondition;
     } else {      // Only approved results can be printed

          $sWhere[] = ' ((vl.result_status = ' . ACCEPTED . ' AND vl.cd4_result is NOT NULL AND vl.cd4_result !="") OR (vl.result_status = ' . REJECTED . ' AND (vl.cd4_result is NULL OR vl.cd4_result = ""))) AND result_printed_datetime is NULL';
     }

     if (isset($_POST['gender']) && trim((string) $_POST['gender']) !== '') {
          if (trim((string) $_POST['gender']) === "unreported") {
               $sWhere[] = ' (vl.patient_gender = "unreported" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
          } else {
               $sWhere[] = ' vl.patient_gender ="' . $db->escape((string) $_POST['gender']) . '"';
          }
     }
     if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) !== '') {
          $sWhere[] = ' vl.funding_source ="' . $db->escape(base64_decode((string) $_POST['fundingSource'])) . '"';
     }
     if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) !== '') {
          $sWhere[] = ' vl.implementing_partner ="' . $db->escape(base64_decode((string) $_POST['implementingPartner'])) . '"';
     }

     if (!empty($_SESSION['facilityMap'])) {
          $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")";
     }

     if ($labScope = $general->labScopeWhere('vl')) {
         $sWhere[] = $labScope;
     }

     if ($sWhere !== []) {
          $sQuery = $sQuery . ' WHERE' . implode(" AND ", $sWhere);
     }
     $_SESSION['vlResultQuery'] = $sQuery;

     if (!empty($sOrder) && $sOrder !== '') {
          $sOrder = preg_replace('/\s+/', ' ', $sOrder);
          $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
     }

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
     }

     [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

     $_SESSION['vlResultQueryCount'] = $resultCount;


     $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $resultCount, "iTotalDisplayRecords" => $resultCount, "aaData" => []];

     foreach ($rResult as $aRow) {
          $row = [];
          if (isset($_POST['vlPrint'])) {
               if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'not-print') {
                    $row[] = '<input type="checkbox" name="chk[]" class="checkRows" id="chk' . $aRow['cd4_id'] . '"  value="' . $aRow['cd4_id'] . '" onclick="checkedRow(this);"  />';
               } else {
                    $row[] = '<input type="checkbox" name="chkPrinted[]" class="checkPrintedRows" id="chkPrinted' . $aRow['cd4_id'] . '"  value="' . $aRow['cd4_id'] . '" onclick="checkedPrintedRow(this);"  />';
               }
               $print = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Print") . '" onclick="generateResultPDF(' . $aRow['cd4_id'] . ')"><em class="fa-solid fa-print"></em> ' . _translate("Print") . '</a>';
          } else {
               $print = '<a href="cd4-update-result.php?id=' . base64_encode((string) $aRow['cd4_id']) . '" class="btn btn-success btn-xs" style="margin-right: 2px;" title="' . _translate("Result") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Enter Result") . '</a>';
               if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes' && !_isAllowed("/cd4/requests/edit-locked-cd4-samples")) {
                    $print = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Locked") . '" disabled><em class="fa-solid fa-lock"></em>' . _translate("Locked") . '</a>';
               }
          }

          $patientFname = $aRow['patient_first_name'] ?? '';
          $patientMname = $aRow['patient_middle_name'] ?? '';
          $patientLname = $aRow['patient_last_name'] ?? '';
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
               $patientMname = $general->crypto('decrypt', $patientMname, $key);
               $patientLname = $general->crypto('decrypt', $patientLname, $key);
          }

          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          $row[] = $aRow['batch_code'];
          $row[] = $aRow['patient_art_no'];
          $row[] = trim("$patientFname $patientMname $patientLname");
          $row[] = $aRow['facility_name'];
          $row[] = $aRow['lab_name'];
          $row[] = $aRow['sample_name'];
          $row[] = $aRow['cd4_result'];
          $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');

          $row[] = $aRow['last_modified_datetime'];
          $row[] = $aRow['status_name'];
          $row[] = $print;
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
     throw $e;
}
