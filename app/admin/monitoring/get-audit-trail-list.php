<?php

use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);
     $tableName = "activity_log";
     $primaryKey = "log_id";


     $aColumns = ['action', 'event_type', 'r.display_name', "DATE_FORMAT(date_time,'%d-%b-%Y')"];
     $orderColumns = ['action', 'event_type', 'r.display_name', 'date_time'];

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

     $sQuery = "SELECT a.*, r.display_name,
                    DATE_FORMAT(a.date_time,'%d-%b-%Y %H:%i:%s') AS createdOn
                    FROM activity_log as a
                    LEFT JOIN resources as r ON a.resource = r.resource_id";


     [$start_date, $end_date] = DateUtility::convertDateRange($_POST['dateRange'] ?? '');

     if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) != '') {
          $sWhere[] = ' DATE(date_time) BETWEEN "' . $start_date . '" AND "' . $end_date . '"';
     }
     if (isset($_POST['userName']) && trim((string) $_POST['userName']) != '') {
          $sWhere[] = ' user_id like "' . $_POST['userName'] . '"';
     }

     if (isset($_POST['typeOfAction']) && trim((string) $_POST['typeOfAction']) != '') {
          $sWhere[] = ' event_type like "' . $_POST['typeOfAction'] . '"';
     }
     /* Implode all the where fields for filtering the data */
     if (!empty($sWhere)) {
          $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
     }

     if (!empty($sOrder) && $sOrder !== '') {
          $sOrder = preg_replace('/\s+/', ' ', $sOrder);
          $sQuery = $sQuery . " ORDER BY " . $sOrder;
     }
     $_SESSION['auditLogQuery'] = $sQuery;


     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
     }

     [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

     /*
     * Output
     */
     $output = [
          "sEcho" => (int) $_POST['sEcho'],
          "iTotalRecords" => $resultCount,
          "iTotalDisplayRecords" => $resultCount,
          "aaData" => []
     ];
     foreach ($rResult as $key => $aRow) {
          $row = [];
          $row[] = $aRow['action'];
          $row[] = $aRow['event_type'];
          $row[] = $aRow['ip_address'];
          $row[] = $aRow['createdOn'];

          $output['aaData'][] = $row;
     }
     echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
     LoggerUtility::logError($e->getMessage(), [
          'trace' => $e->getTraceAsString(),
          'file' => $e->getFile(),
          'line' => $e->getLine(),
          'last_db_error' => $db->getLastError(),
          'last_db_query' => $db->getLastQuery()
     ]);
}
