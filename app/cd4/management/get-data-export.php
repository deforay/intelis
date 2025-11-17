<?php

use Laminas\Diactoros\ServerRequest;
use App\Services\CD4Service;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {

     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);


     /** @var CD4Service $cd4Service */
     $cd4Service = ContainerRegistry::get(CD4Service::class);

     $key = (string) $general->getGlobalConfig('key');


     $tableName = "form_cd4";
     $primaryKey = "cd4_id";

     $aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', 'CONCAT(COALESCE(vl.patient_first_name,""), COALESCE(vl.patient_last_name,""))', 'f.facility_name', 'l_f.facility_name', 'vl.cd4_result', 'ts.status_name', 'funding_source_name', 'i_partner_name'];
     $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'l_f.facility_name', 'vl.cd4_result', 'ts.status_name', 'funding_source_name', 'i_partner_name'];
     $sampleCode = 'sample_code';
     if ($general->isSTSInstance()) {
         $sampleCode = 'remote_sample_code';
     } elseif ($general->isStandaloneInstance()) {
         $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
         $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
     }
     /* Indexed column (used for fast and accurate table cardinality) */
     $sIndexColumn = $primaryKey;
     $sTable = $tableName;
     /*
     * Paging
     */
     $sOffset = $sLimit = null;
     if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
          $sOffset = $_POST['iDisplayStart'];
          $sLimit = $_POST['iDisplayLength'];
     }



     $sOrder = "";
     if (isset($_POST['iSortCol_0'])) {
          $sOrder = "";
          for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
               if ($_POST['bSortable_' . (int) $_POST['iSortCol_' . $i]] == "true") {
                    $sOrder .= $orderColumns[(int) $_POST['iSortCol_' . $i]] . "
                    " . ($_POST['sSortDir_' . $i]) . ", ";
               }
          }
          $sOrder = substr_replace($sOrder, "", -2);
     }



     $sWhere = [];
     if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
          $searchArray = explode(" ", (string) $_POST['sSearch']);
          $sWhereSub = "";
          foreach ($searchArray as $search) {
               $sWhereSub .= "(";
               $colSize = count($aColumns);

               for ($i = 0; $i < $colSize; $i++) {
                    if ($i < $colSize - 1) {
                         $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                    } else {
                         $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                    }
               }
               $sWhereSub .= ")";
          }
          $sWhere[] = $sWhereSub;
     }




     $sQuery = "SELECT vl.*,
                    rtr.test_reason_name,
                    b.batch_code,
                    ts.status_name,
                    rst.sample_name,
                    f.facility_name,
                    l_f.facility_name as lab_name,
                    f.facility_code,
                    f.facility_state,
                    f.facility_district,
                    u_d.user_name as reviewedBy,
                    a_u_d.user_name as approvedBy,
                    lt_u_d.user_name as labTechnician,
                    rs.rejection_reason_name,
                    r_f_s.funding_source_name,
                    c.iso_name as nationality,
                    r_i_p.i_partner_name,
                    rs.rejection_reason_name as rejection_reason,
                    r_c_a.recommended_corrective_action_name

                    FROM form_cd4 as vl

                    LEFT JOIN r_countries as c ON vl.patient_nationality=c.id
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
                    LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
                    LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
                    LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
                    LEFT JOIN user_details as lt_u_d ON lt_u_d.user_id=vl.tested_by
                    LEFT JOIN r_cd4_test_reasons as rtr ON rtr.test_reason_id=vl.reason_for_cd4_testing
                    LEFT JOIN r_cd4_sample_types as rst ON rst.sample_id=vl.specimen_type
                    LEFT JOIN r_cd4_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
                    LEFT JOIN r_recommended_corrective_actions as r_c_a ON vl.recommended_corrective_action=r_c_a.recommended_corrective_action_id
                    LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source
                    LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";
     /* Sample collection date filter */
     [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
     /* Sample recieved date filter */
     [$rstart_date, $rend_date] = DateUtility::convertDateRange($_POST['sampleRecievedDate'] ?? '');
     /* Sample tested date filter */
     [$sTestDate, $eTestDate] = DateUtility::convertDateRange($_POST['sampleTestDate'] ?? '');
     /* Sample print date filter */
     [$sPrintDate, $ePrintDate] = DateUtility::convertDateRange($_POST['printDate'] ?? '');
     if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
          $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
     }
     if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
          $sWhere[] = " f.facility_district_id = '" . $_POST['district'] . "' ";
     }
     /* Facility Id filter */
     if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
          $sWhere[] = ' vl.facility_id = "' . $_POST['facilityName'] . '"';
     }
     /* Testing lab filter */
     if (isset($_POST['testingLab']) && trim((string) $_POST['testingLab']) !== '') {
          $sWhere[] = ' vl.lab_id = "' . $_POST['testingLab'] . '"';
     }
     /* Result filter */
     if (isset($_POST['result']) && trim((string) $_POST['result']) !== '') {
          $sWhere[] = ' vl.cd4_result like "' . $_POST['result'] . '"';
     }
     /* Status filter */
     if (isset($_POST['status']) && trim((string) $_POST['status']) !== '') {
          $sWhere[] = ' vl.result_status =' . $_POST['status'];
     }
     /* Funding src filter */
     if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) !== '') {
          $sWhere[] = ' vl.funding_source ="' . base64_decode((string) $_POST['fundingSource']) . '"';
     }
     /* Implementing partner filter */
     if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) !== '') {
          $sWhere[] = ' vl.implementing_partner ="' . base64_decode((string) $_POST['implementingPartner']) . '"';
     }
     /* Batch code filter */
     if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
          $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
     }
     if (isset($_POST['patientId']) && trim((string) $_POST['patientId']) !== '') {
          $sWhere[] = " vl.patient_art_no LIKE '%" . $_POST['patientId'] . "%' ";
     }
     if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
          $sWhere[] = " CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_last_name,'')) like '%" . $_POST['patientName'] . "%'";
     }
     /* Date time filtering */
     if (!empty($_POST['sampleCollectionDate'])) {
          if (trim((string) $start_date) === trim((string) $end_date)) {
               $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
          } else {
               $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
          }
     }
     if (isset($_POST['sampleRecievedDate']) && trim((string) $_POST['sampleRecievedDate']) !== '') {
          if (trim((string) $rstart_date) === trim((string) $rend_date)) {
               $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) = "' . $rstart_date . '"';
          } else {
               $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) >= "' . $rstart_date . '" AND DATE(vl.sample_received_at_lab_datetime) <= "' . $rend_date . '"';
          }
     }
     if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
          if (trim((string) $sTestDate) === trim((string) $eTestDate)) {
               $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
          } else {
               $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
          }
     }
     if (isset($_POST['printDate']) && trim((string) $_POST['printDate']) !== '') {
          if (trim((string) $sPrintDate) === trim((string) $eTestDate)) {
               $sWhere[] = ' DATE(vl.result_printed_datetime) like "' . $sPrintDate . '"';
          } else {
               $sWhere[] = ' DATE(vl.result_printed_datetime) >= "' . $sPrintDate . '" AND DATE(vl.result_printed_datetime) <= "' . $ePrintDate . '"';
          }
     }

     if (!empty($_SESSION['facilityMap'])) {
          $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
     }

     $sWhere[] = " result_status is NOT NULL";

     if ($sWhere !== []) {
          $sWhere = ' where ' . implode(" AND ", $sWhere);
     }
     $sQuery = $sQuery . ' ' . $sWhere;



     if (!empty($sOrder) && $sOrder !== '') {
          $sOrder = preg_replace('/\s+/', ' ', $sOrder);
          $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
     }

     $_SESSION['cd4ResultQuery'] = $sQuery;

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
     }

     [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

     $_SESSION['cd4ResultQueryCount'] = $resultCount;

     /*
     * Output
     */
     $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $resultCount, "iTotalDisplayRecords" => $resultCount, "aaData" => []];

     foreach ($rResult as $aRow) {

          $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_art_no']));
          $patientLname = ($general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_art_no']));

          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
               $patientLname = $general->crypto('decrypt', $patientLname, $key);
          }

          $row = [];
          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          $row[] = $aRow['batch_code'];
          $row[] = $aRow['patient_art_no'];
          $row[] = ($patientFname . " " . $patientLname);
          $row[] = ($aRow['facility_name']);
          $row[] = ($aRow['lab_name']);
          $row[] = $covid19Results[$aRow['result']] ?? $aRow['result'];
          $row[] = ($aRow['status_name']);
          $row[] = $aRow['funding_source_name'] ?? null;
          $row[] = $aRow['i_partner_name'] ?? null;
          if ($aRow['is_result_authorised'] == 'yes') {
               $row[] = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("View") . '" onclick="convertSearchResultToPdf(' . $aRow['cd4_id'] . ')"><em class="fa-solid fa-file-lines"></em> ' . _translate("Result PDF") . '</a>';
          } else {
               $row[] = '<a href="javascript:void(0);" class="btn btn-default btn-xs disabled" style="margin-right: 2px;" title="' . _translate("View") . '"><em class="fa-solid fa-ban"></em> ' . _translate("Not Authorized") . '</a>';
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
