<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\ApiTrackingStorageUtility;
use App\Utilities\DataTableUtility;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
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

     $aColumns = ['transaction_id', 'number_of_records', 'request_type', 'test_type', "api_url", "DATE_FORMAT(requested_on,'%d-%b-%Y')"];
     $orderColumns = ['transaction_id', 'number_of_records', 'request_type', 'test_type', 'api_url', 'requested_on'];



     [$sOffset, $sLimit] = DataTableUtility::paging($_POST);

     $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

     $columnSearch = $general->multipleColumnSearch($_POST['sSearch'], $aColumns);
     $sWhere = [];
     $sParams = [];
     if (!empty($columnSearch) && $columnSearch != '') {
          $sWhere[] = $columnSearch;
     }

     $aWhere = '';
     $sQuery = '';

     $sQuery = "SELECT a.api_track_id, a.transaction_id,
                         a.number_of_records,
                         a.request_type,
                         a.test_type,
                         a.api_url,
                         a.requested_on
                    FROM track_api_requests as a";

     if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) !== '') {
          [$startDate, $endDate] = DateUtility::convertDateRange($_POST['dateRange'] ?? '', includeTime: true);
          $sWhere[] = " a.requested_on BETWEEN '$startDate' AND '$endDate' ";
     }

     if (isset($_POST['syncedType']) && trim((string) $_POST['syncedType']) !== '') {
          $sWhere[] = ' a.request_type like "' . $db->escape((string) $_POST['syncedType']) . '"';
     }
     if (isset($_POST['testType']) && trim((string) $_POST['testType']) !== '') {
          $sWhere[] = ' a.test_type like "' . $db->escape((string) $_POST['testType']) . '"';
     }
     if (isset($_POST['apiToken']) && trim((string) $_POST['apiToken']) !== '') {
          $sWhere[] = ' a.api_token like "%' . $db->escape($_POST['apiToken']) . '%"';
     }
     if (isset($_POST['transactionId']) && trim((string) $_POST['transactionId']) !== '') {
          // Exact match, so the transaction_id index is used: people paste the whole ID.
          $sWhere[] = ' a.transaction_id = ?';
          $sParams[] = trim((string) $_POST['transactionId']);
     }

     /* Implode all the where fields for filtering the data */
     if (!empty($sWhere)) {
          $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
     }

     if (!empty($sOrder) && $sOrder !== '') {
          $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
          $sQuery = "$sQuery ORDER BY $sOrder";
     }

     $_SESSION['auditLogQuery'] = $sQuery;

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
     }

     [$rResult, $resultCount] = $db->getDataAndCount($sQuery, $sParams);

     /*
      * Output
      */
     $output = [
          "sEcho" => (int) $_POST['sEcho'],
          "iTotalRecords" => $resultCount,
          "iTotalDisplayRecords" => $resultCount,
          "aaData" => []
     ];
     $today = new DateTimeImmutable();
     $expiredTitle = sprintf(_translate("Request and response bodies are kept for %d days"), ApiTrackingStorageUtility::RETENTION_DAYS);
     foreach ($rResult as $key => $aRow) {
          $row = [];
          $row[] = $aRow['transaction_id'];
          $row[] = $aRow['number_of_records'];
          $row[] = strtoupper(str_replace("-", " ", (string) $aRow['request_type']));
          $row[] = strtoupper((string) $aRow['test_type']);
          $row[] = $aRow['api_url'];
          $row[] = DateUtility::humanReadableDateFormat($aRow['requested_on'], true);
          if (ApiTrackingStorageUtility::bodyExpired($aRow['requested_on'], $today)) {
               $row[] = "<button type='button' class='btn btn-default btn-xs' style='margin-right: 2px;' disabled title='" . htmlspecialchars($expiredTitle, ENT_QUOTES) . "'>" . _translate("Body expired") . "</button>";
          } else {
               $row[] = "<a href='javascript:void(0);' class='btn btn-success btn-xs' style='margin-right: 2px;' title='Result' onclick=\"showModal('/admin/monitoring/show-params.php?id=" . MiscUtility::sqid($aRow['api_track_id']) . "',1200,720);\"> Show Params</a>";
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
          'last_db_query' => $db->getLastQuery()
     ]);
     throw $e;
}
