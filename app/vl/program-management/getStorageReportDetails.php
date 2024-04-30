<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use Laminas\Filter\StringTrim;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use Laminas\Filter\FilterChain;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

     $db->beginReadOnlyTransaction();

     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);
     $key = (string) $general->getGlobalConfig('key');


if($_POST['reportType']=='storageData'){
     $sampleCode = 'sample_code';
     $aColumns = array('vl.sample_code', 'h.volume', 'h.rack', 'h.box', 'h.position', 'h.sample_status');
     $orderColumns = array('vl.sample_code', 'h.volume', 'h.rack', 'h.box', 'h.position', 'h.sample_status');

     $sOffset = $sLimit = null;
     if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
          $sOffset = $_POST['iDisplayStart'];
          $sLimit = $_POST['iDisplayLength'];
     }

     $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);


     $sQuery = "SELECT h.*, s.storage_code, vl.sample_code
     FROM lab_storage_history as h
     INNER JOIN lab_storage as s ON h.freezer_id = s.storage_id
     INNER JOIN form_vl as vl ON vl.unique_id = h.sample_unique_id ";

     $sWhere[] = ' h.freezer_id = "' . $_POST['freezerId'] . '"';
    
     if (!empty($sWhere)) {
          $sQuery = $sQuery . ' WHERE' . implode(" AND ", $sWhere);
     }
  
     $_SESSION['storageDataQuery'] = $sQuery;

     if (!empty($sOrder)) {
          $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
          $sQuery = $sQuery . ' order by ' . $sOrder;
     }

     [$rResult, $resultCount] = $general->getQueryResultAndCount($sQuery, null, $sLimit, $sOffset, true);

     $_SESSION['storageDataQueryCount'] = $resultCount;

     $output = array(
          "sEcho" => (int) $_POST['sEcho'],
          "iTotalRecords" => $resultCount,
          "iTotalDisplayRecords" => $resultCount,
          "aaData" => []
     );

     foreach ($rResult as $aRow) {
          $row = [];
          $row[] = $aRow['sample_code'];
          $row[] = DateUtility::humanReadableDateFormat($aRow['updated_datetime'] ?? '', true);
          $row[] = ($aRow['volume']);
          $row[] = ($aRow['rack']);
          $row[] = ($aRow['box']);
          $row[] = ($aRow['position']);
          $row[] = ($aRow['sample_status']);

          $output['aaData'][] = $row;
     }
}
else if($_POST['reportType']=='historyData'){

     $aColumns = array('vl.patient_art_no', 'vl.sample_collection_date', 's.storage_code', 'h.box', 'h.position', 'h.sample_status');
     $orderColumns = array('vl.patient_art_no', 'vl.sample_collection_date', 's.storage_code', 'h.box', 'h.position', 'h.sample_status');

     $sOffset = $sLimit = null;
     if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
          $sOffset = $_POST['iDisplayStart'];
          $sLimit = $_POST['iDisplayLength'];
     }

     $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

     $sQuery = "SELECT h.*, s.storage_code, vl.sample_collection_date,vl.is_encrypted,vl.patient_art_no, rr.removal_reason_name
               FROM lab_storage_history as h
               LEFT JOIN r_reasons_for_sample_removal as rr ON rr.removal_reason_id = sample_removal_reason
               LEFT JOIN lab_storage as s ON h.freezer_id = s.storage_id
               LEFT JOIN form_vl as vl ON vl.unique_id = h.sample_unique_id ";

     $sWhere[] = ' vl.sample_code = "' . $_POST['sampleCode'] . '"';

     if(isset($_POST['labId']) && $_POST['labId'] != "")
     {
          $sWhere[] = ' vl.lab_id = "' . $_POST['labId'] . '"';
     }
    
     if (!empty($sWhere)) {
          $sQuery = $sQuery . ' WHERE' . implode(" AND ", $sWhere);
     }
    
     $_SESSION['storageHistoryDataQuery'] = $sQuery;

     if (!empty($sOrder)) {
          $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
          $sQuery = $sQuery . ' order by ' . $sOrder;
     }

     [$rResult, $resultCount] = $general->getQueryResultAndCount($sQuery, null, $sLimit, $sOffset, true);

     $_SESSION['storageHistoryDataQueryCount'] = $resultCount;

     $output = array(
          "sEcho" => (int) $_POST['sEcho'],
          "iTotalRecords" => $resultCount,
          "iTotalDisplayRecords" => $resultCount,
          "aaData" => []
     );

     foreach ($rResult as $aRow) {
          $row = [];
          //$row[] = $aRow['patient_first_name'];
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
          }
   
          $row[] = $aRow['patient_art_no'];
          $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
          $row[] = $aRow['storage_code'];
          $row[] = ($aRow['volume']);
          $row[] = ($aRow['rack']);
          $row[] = ($aRow['box']);
          $row[] = ($aRow['position']);
          $row[] = DateUtility::humanReadableDateFormat($aRow['date_out']);
          $row[] = ($aRow['comments']);
          $row[] = ($aRow['sample_status']);
          $row[] = ($aRow['removal_reason_name']);

          $output['aaData'][] = $row;
     }
}

     echo MiscUtility::convertToUtf8AndEncode($output);

     $db->commitTransaction();
} catch (Exception $exc) {
     LoggerUtility::log('error', $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
