<?php

use Psr\Http\Message\ServerRequestInterface;
use const COUNTRY\CAMEROON;
use App\Services\EidService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {



     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);

     /** @var EidService $eidService */
     $eidService = ContainerRegistry::get(EidService::class);
     $eidResults = $eidService->getEidResults();

     $sarr = $general->getSystemConfig();
     $key = (string) $general->getGlobalConfig('key');
     $formId = (int) $general->getGlobalConfig('vl_form');


     $tableName = "form_eid";
     $primaryKey = "eid_id";

     $aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.child_id', 'vl.child_name', 'f.facility_name', 'l_f.facility_name', 'vl.mother_id', 'vl.result', 'ts.status_name', 'funding_source_name', 'i_partner_name'];
     $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.child_id', 'vl.child_name', 'f.facility_name', 'l_f.facility_name', 'vl.mother_id', 'vl.result', 'ts.status_name', 'funding_source_name', 'i_partner_name'];
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
               if ($sWhereSub === "") {
                    $sWhereSub .= "(";
               } else {
                    $sWhereSub .= " AND (";
               }
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




     $sQuery = "SELECT SQL_CALC_FOUND_ROWS
                         vl.*,
                         b.batch_code,
                         ts.status_name,
                         f.facility_name,
                         l_f.facility_name as lab_name,
                         f.facility_code,
                         f.facility_state,
                         f.facility_district,
                         s.sample_name as sample_name,
                         u_d.user_name as reviewedBy,
                         a_u_d.user_name as approvedBy,
                         rs.rejection_reason_name,
                         tr.test_reason_name,
                         r_f_s.funding_source_name,
                         r_i_p.i_partner_name,
                         rs.rejection_reason_name as rejection_reason,
                         r_c_a.recommended_corrective_action_name

                         FROM form_eid as vl

                         INNER JOIN facility_details as f ON vl.facility_id=f.facility_id
                         INNER JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
                         LEFT JOIN r_eid_sample_type as s ON s.sample_id=vl.specimen_type
                         LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                         LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
                         LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
                         LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
                         LEFT JOIN r_eid_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
                         LEFT JOIN r_eid_test_reasons as tr ON tr.test_reason_id=vl.reason_for_eid_test
                         LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source
                         LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner
                         LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=vl.recommended_corrective_action";
     /* Sample collection date filter */
     [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
     /* Sample Received date filter */
     [$sReceivedDate, $eReceivedDate] = DateUtility::convertDateRange($_POST['sampleReceivedDate'] ?? '');
     /* Result dispatch date filter */
     [$sResultDispatchedDate, $eResultDispatchedDate] = DateUtility::convertDateRange($_POST['resultDispatchedOn'] ?? '');
     /* Sample tested date filter */
     [$sTestDate, $eTestDate] = DateUtility::convertDateRange($_POST['sampleTestDate'] ?? '');
     /* Print date filter */
     [$sPrintDate, $ePrintDate] = DateUtility::convertDateRange($_POST['printDate'] ?? '');
     /* Sample type filter */
     if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) !== '') {
          $sWhere[] = ' vl.specimen_type IN (' . $_POST['sampleType'] . ')';
     }
     if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
          $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
     }
     if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
          $sWhere[] = " f.facility_district_id = '" . $_POST['district'] . "' ";
     }
     /* Facility id filter */
     if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
          $sWhere[] = ' vl.facility_id = "' . $_POST['facilityName'] . '"';
     }
     /* Testing lab filter */
     if (isset($_POST['testingLab']) && trim((string) $_POST['testingLab']) !== '') {
          $sWhere[] = ' vl.lab_id = "' . $_POST['testingLab'] . '"';
     }
     /* Result filter */
     if (isset($_POST['result']) && trim((string) $_POST['result']) !== '') {
          $sWhere[] = ' vl.result like "' . $_POST['result'] . '"';
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

     if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
          $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
     }

     if (isset($_POST['childId']) && $_POST['childId'] != "") {
          $sWhere[] = ' vl.child_id like "%' . $_POST['childId'] . '%"';
     }
     if (isset($_POST['childName']) && $_POST['childName'] != "") {
          $sWhere[] = ' vl.child_name like "%' . $_POST['childName'] . '%"';
     }
     if (isset($_POST['motherId']) && $_POST['motherId'] != "") {
          $sWhere[] = ' vl.mother_id like "%' . $_POST['motherId'] . '%"';
     }
     if (isset($_POST['motherName']) && $_POST['motherName'] != "") {
          $sWhere[] = ' vl.mother_name like "%' . $_POST['motherName'] . '%"';
     }

     /* Date time filters */
     if (!empty($_POST['sampleCollectionDate'])) {
          if (trim((string) $start_date) === trim((string) $end_date)) {
               $sWhere[] = ' DATE(vl.sample_collection_date) like "' . $start_date . '"';
          } else {
               $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
          }
     }
     if (isset($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) !== '') {
          if (trim((string) $sReceivedDate) === trim((string) $eReceivedDate)) {
               $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) like "' . $sReceivedDate . '"';
          } else {
               $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) >= "' . $sReceivedDate . '" AND DATE(vl.sample_received_at_lab_datetime) <= "' . $eReceivedDate . '"';
          }
     }
     if (isset($_POST['resultDispatchedOn']) && trim((string) $_POST['resultDispatchedOn']) !== '') {
          if (trim((string) $sResultDispatchedDate) === trim((string) $eResultDispatchedDate)) {
               $sWhere[] = ' DATE(vl.result_dispatched_datetime) like "' . $sResultDispatchedDate . '"';
          } else {
               $sWhere[] = ' DATE(vl.result_dispatched_datetime) >= "' . $sResultDispatchedDate . '" AND DATE(vl.result_dispatched_datetime) <= "' . $eResultDispatchedDate . '"';
          }
     }
     if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
          if (!empty($sTestDate) && trim((string) $sTestDate) === trim((string) $eTestDate)) {
               $sWhere[] = ' DATE(vl.sample_tested_datetime) like "' . $sTestDate . '"';
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

     if ($sWhere !== []) {
          $sQuery = $sQuery . ' WHERE result_status is NOT NULL AND' . implode(" AND ", $sWhere);
     } else {
          $sQuery .= ' WHERE result_status is NOT NULL ';
     }

     if (!empty($sOrder) && $sOrder !== '') {
          $sOrder = preg_replace('/\s+/', ' ', $sOrder);
          $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
     }
     $_SESSION['eidExportResultQuery'] = $sQuery;

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
     }
     //$general->elog($sQuery);
     $rResult = $db->rawQuery($sQuery);
     /* Data set length after filtering */

     $aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
     $iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

     $_SESSION['eidExportResultQueryCount'] = $iTotal;


     $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

     foreach ($rResult as $aRow) {

          $patientFname = ($general->crypto('doNothing', $aRow['child_name'], $aRow['child_id']));

          $row = [];
          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
               $aRow['child_name'] = $general->crypto('decrypt', $aRow['child_name'], $key);
               $aRow['mother_id'] = $general->crypto('decrypt', $aRow['mother_id'], $key);
               // $aRow['mother_name'] = $general->crypto('decrypt', $aRow['mother_name'], $key);
          }
          $row[] = $aRow['batch_code'];
          $row[] = $aRow['child_id'];
          //$row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
          $row[] = $aRow['child_name'];
          $row[] = ($aRow['facility_name']);
          $row[] = ($aRow['lab_name']);
          if ($formId == CAMEROON) {
               $row[] = ($aRow['lab_assigned_code']);
          }

          $row[] = $aRow['mother_id'];
          $row[] = $eidResults[$aRow['result']] ?? $aRow['result'];
          $row[] = ($aRow['status_name']);
          $row[] = $aRow['funding_source_name'] ?? null;
          $row[] = $aRow['i_partner_name'] ?? null;
          $row[] = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("View") . '" onclick="convertSearchResultToPdf(' . $aRow['eid_id'] . ')"><em class="fa-solid fa-file-lines"></em> ' . _translate("Result PDF") . '</a>';

          $output['aaData'][] = $row;
     }

     echo JsonUtility::encodeUtf8Json($output);
} catch (Exception $exc) {
     LoggerUtility::logError($exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
