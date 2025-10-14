<?php

use App\Services\EidService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sarr = $general->getSystemConfig();
$key = (string) $general->getGlobalConfig('key');


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();


$tableName = "form_eid";
$primaryKey = "eid_id";


$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.child_id', 'vl.child_name', 'vl.mother_id', 'vl.mother_name', 'f.facility_name', 'f.facility_code', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.child_id', 'vl.child_name', 'vl.mother_id', 'vl.mother_name', 'f.facility_name', 'f.facility_code',  'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
if ($general->isSTSInstance()) {
    $sampleCode = 'remote_sample_code';
} else if ($general->isStandaloneInstance()) {
    $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
    $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
}

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;

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




$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*,
                f.facility_name,
                b.batch_code
            FROM form_eid as vl
            INNER JOIN facility_details as f ON vl.facility_id=f.facility_id
            INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";


$start_date = '';
$end_date = '';
if (!empty($_POST['sampleCollectionDate'])) {
    $s_c_date = explode("to", (string) $_POST['sampleCollectionDate']);

    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
    }
}

if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
    $sWhere[] =  '  b.batch_code LIKE "%' . $_POST['batchCode'] . '%"';
}
if (!empty($_POST['sampleCollectionDate'])) {
    if (trim((string) $start_date) == trim((string) $end_date)) {
        $sWhere[] =  '  DATE(vl.sample_collection_date) = "' . $start_date . '"';
    } else {
        $sWhere[] =  '  DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
    }
}
if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
    $sWhere[] =  '  f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['statusFilter']) && $_POST['statusFilter'] != '') {
    if ($_POST['statusFilter'] == 'approvedOrRejected') {
        $sWhere[] =  ' vl.result_status IN (4,7)';
    } else if ($_POST['statusFilter'] == 'notApprovedOrRejected') {
        //$sWhere[] = ' vl.result_status NOT IN (4,7)';
        $sWhere[] = ' vl.result_status IN (6,8)';
    }
}

if (!empty($_SESSION['facilityMap'])) {
    $sWhere[] = "  vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
}

$sWhere[] =  ' vl.result not like "" AND vl.result is not null ';
if (!empty($_POST['sampleCollectionDate'])) {
    if (trim((string) $start_date) == trim((string) $end_date)) {
        $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
    } else {
        $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
    }
}
if (!empty($sWhere)) {
    $sWhere = ' where ' . implode(' AND ', $sWhere);
} else {
    $sWhere = "";
}
$sQuery = $sQuery . ' ' . $sWhere;


if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// echo $sQuery;
$_SESSION['eidRequestSearchResultQuery'] = $sQuery;
$rResult = $db->rawQuery($sQuery);

$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

$_SESSION['eidRequestSearchResultQueryCount'] = $iTotal;


$output = array(
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => []
);
$vlRequest = false;
$vlView = false;
if ((_isAllowed("/eid/requests/eid-edit-request.php"))) {
    $vlRequest = true;
}
if ((_isAllowed("/eid/requests/eid-requests.phpp"))) {
    $vlView = true;
}

foreach ($rResult as $aRow) {
    if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
    } else {
        $aRow['sample_collection_date'] = '';
    }

    $childName = ($general->crypto('doNothing', $aRow['child_name'], $aRow['child_id']));


    $status = '<select class="form-control"  name="status[]" id="' . $aRow['eid_id'] . '" title="' . _translate("Please select status") . '" onchange="updateStatus(this,' . $aRow['status_id'] . ')">
               <option value="">' . _translate("-- Select --") . '</option>
               <option value="7" ' . ($aRow['status_id'] == "7" ? "selected=selected" : "") . '>' . _translate("Accepted") . '</option>
               <option value="4" ' . ($aRow['status_id'] == "4"  ? "selected=selected" : "") . '>' . _translate("Rejected") . '</option>
               <option value="2" ' . ($aRow['status_id'] == "2"  ? "selected=selected" : "") . '>' . _translate("Lost") . '</option>
               </select><br><br>';

    $row = [];
    $row[] = '<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['eid_id'] . '"  value="' . $aRow['eid_id'] . '" onclick="toggleTest(this);"  />';
    $row[] = $aRow['sample_code'];
    if (!$general->isStandaloneInstance()) {
        $row[] = $aRow['remote_sample_code'];
    }
    if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
        $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
        $aRow['child_name'] = $general->crypto('decrypt', $aRow['child_name'], $key);
        $aRow['mother_id'] = $general->crypto('decrypt', $aRow['mother_id'], $key);
        $aRow['mother_name'] = $general->crypto('decrypt', $aRow['mother_name'], $key);
    }
    $row[] = $aRow['sample_collection_date'];
    $row[] = $aRow['batch_code'];
    $row[] = $aRow['child_id'];
    $row[] = $aRow['child_name'];
    $row[] = $aRow['mother_id'];
    $row[] = $aRow['mother_name'];
    $row[] = ($aRow['facility_name']);
    $row[] = $eidResults[$aRow['result']] ?? $aRow['result'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');
    $row[] = $status;

    $output['aaData'][] = $row;
}

echo json_encode($output);
