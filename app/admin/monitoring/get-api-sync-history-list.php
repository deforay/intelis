<?php

use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {

     $db->beginReadOnlyTransaction();


     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);
     $primaryKey = "api_track_id";


     $aColumns = array('transaction_id', 'number_of_records', 'request_type', 'test_type', "api_url", "DATE_FORMAT(requested_on,'%d-%b-%Y')");
     $orderColumns = array('transaction_id', 'number_of_records', 'request_type', 'test_type', 'api_url', 'requested_on');

     /* Indexed column (used for fast and accurate table cardinality) */
     $sIndexColumn = $primaryKey;

     /*
     * Paging
     */
     $sOffset = $sLimit = null;
     if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
          $sOffset = $_POST['iDisplayStart'];
          $sLimit = $_POST['iDisplayLength'];
     }

     /*
     * Ordering
     */

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
     $sWhere[] = ' number_of_records > 0 ';
     if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
          $searchArray = explode(" ", (string) $_POST['sSearch']);
          $sWhereSub = "";
          foreach ($searchArray as $search) {
               $sWhereSub .= " (";
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

     /*
     * SQL queries
     * Get data to display
     */
     $aWhere = '';
     $sQuery = '';

     $sQuery = "SELECT a.* FROM track_api_requests as a";

     [$startDate, $endDate] = DateUtility::convertDateRange($_POST['dateRange'] ?? '');

     if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) != '') {
          $sWhere[] = ' DATE(a.requested_on) >= "' . $startDate . '" AND DATE(a.requested_on) <= "' . $endDate . '"';
     }

     if (isset($_POST['syncedType']) && trim((string) $_POST['syncedType']) != '') {
          $sWhere[] = ' a.request_type like "' . $_POST['syncedType'] . '"';
     }
     if (isset($_POST['testType']) && trim((string) $_POST['testType']) != '') {
          $sWhere[] = ' a.test_type like "' . $_POST['testType'] . '"';
     }

     /* Implode all the where fields for filtering the data */
     if (!empty($sWhere)) {
          $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
     }

     if (!empty($sOrder)) {
          $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
          $sQuery = $sQuery . " ORDER BY " . $sOrder;
     }
     $_SESSION['auditLogQuery'] = $sQuery;

     [$rResult, $resultCount] = $general->getQueryResultAndCount($sQuery, null, $sLimit, $sOffset, true);

     /*
     * Output
     */
     $output = array(
          "sEcho" => (int) $_POST['sEcho'],
          "iTotalRecords" => $resultCount,
          "iTotalDisplayRecords" => $resultCount,
          "aaData" => []
     );
     foreach ($rResult as $key => $aRow) {
          $row = [];
          $row[] = $aRow['transaction_id'];
          $row[] = $aRow['number_of_records'];
          $row[] = str_replace("-", " ", ((string) $aRow['request_type']));
          $row[] = strtoupper((string) $aRow['test_type']);
          $row[] = $aRow['api_url'];
          $row[] = DateUtility::humanReadableDateFormat($aRow['requested_on'], true);
          $row[] = '<a href="javascript:void(0);" class="btn btn-success btn-xs" style="margin-right: 2px;" title="Result" onclick="showModal(\'show-params.php?id=' . base64_encode((string) $aRow[$primaryKey]) . '\',1200,720);"> Show Params</a>';
          $output['aaData'][] = $row;
     }
     echo MiscUtility::convertToUtf8AndEncode($output);

     $db->commitTransaction();
} catch (Exception $exc) {
     LoggerUtility::log('error', $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
