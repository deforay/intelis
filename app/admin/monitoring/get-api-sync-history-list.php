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

     $db->beginReadOnlyTransaction();

     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);
     $primaryKey = "api_track_id";

     $aColumns = ['transaction_id', 'number_of_records', 'request_type', 'test_type', "api_url", "DATE_FORMAT(requested_on,'%d-%b-%Y')"];
     $orderColumns = ['transaction_id', 'number_of_records', 'request_type', 'test_type', 'api_url', 'requested_on'];

     /* Indexed column (used for fast and accurate table cardinality) */
     $sIndexColumn = $primaryKey;


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

     $aWhere = '';
     $sQuery = '';

     $sQuery = "SELECT a.* FROM track_api_requests as a";

     [$startDate, $endDate] = DateUtility::convertDateRange($_POST['dateRange'] ?? '');

     if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) != '') {
          $sWhere[] = " DATE(a.requested_on) BETWEEN '$startDate' AND '$endDate' ";
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

     if (!empty($sOrder) && $sOrder !== '') {
          $sOrder = preg_replace('/\s+/', ' ', $sOrder);
          $sQuery = "$sQuery ORDER BY $sOrder";
     }
     $_SESSION['auditLogQuery'] = $sQuery;

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
     }

     [$rResult, $resultCount] = $db->getQueryResultAndCount($sQuery);

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
          $row[] = $aRow['transaction_id'];
          $row[] = $aRow['number_of_records'];
          $row[] = strtoupper(str_replace("-", " ", (string) $aRow['request_type']));
          $row[] = strtoupper((string) $aRow['test_type']);
          $row[] = $aRow['api_url'];
          $row[] = DateUtility::humanReadableDateFormat($aRow['requested_on'], true);
          $row[] = '<a href="javascript:void(0);" class="btn btn-success btn-xs" style="margin-right: 2px;" title="Result" onclick="showModal(\'show-params.php?id=' . base64_encode((string) $aRow[$primaryKey]) . '\',1200,720);"> Show Params</a>';
          $output['aaData'][] = $row;
     }
     echo JsonUtility::encodeUtf8Json($output);

     $db->commitTransaction();
} catch (Throwable $exc) {
     LoggerUtility::log('error', $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
