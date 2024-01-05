<?php


use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    $db->beginReadOnlyTransaction();


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


$gconfig = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

$tableName = "form_vl";
$primaryKey = "vl_sample_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'lab_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');

$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'lab_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCode = 'remote_sample_code';
} elseif ($sarr['sc_user_type'] == 'standalone') {
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
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
    $searchArray = explode(" ", (string) $_POST['sSearch']);
    $sWhereSub = "";
    foreach ($searchArray as $search) {
        if ($sWhereSub == "") {
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
    vl.vl_sample_id,
    vl.sample_code,
    vl.remote_sample_code,
    vl.sample_collection_date,
    vl.last_modified_datetime,
    vl.patient_art_no,
    vl.patient_first_name,
    vl.patient_middle_name,
    vl.patient_last_name,
    vl.result,
    vl.result_status,
    vl.data_sync,
    f.facility_name,
    f.facility_state,
    f.facility_district,
    s.sample_name,
    ts.status_name,
    b.batch_code
    FROM form_vl as vl
    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
    LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type
    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
    LEFT JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id
    LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
    LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id=vl.implementing_partner
    LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing
    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";


if (!empty($_POST['sampleCollectionDate'])) {
    [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

    if (trim((string) $start_date) == trim((string) $end_date)) {
        $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
    } else {
        $sWhere[] =  ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
    }
}
if (isset($_POST['sampleType']) && $_POST['sampleType'] != '') {
    $sWhere[] =  ' s.sample_id = "' . $_POST['sampleType'] . '"';
}
if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
    $sWhere[] =  ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['district']) && trim((string) $_POST['district']) != '') {
    $sWhere[] =  " f.facility_district_id = '" . $_POST['district'] . "' ";
}
if (isset($_POST['state']) && trim((string) $_POST['state']) != '') {
    $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
}
if (isset($_POST['vlLab']) && trim((string) $_POST['vlLab']) != '') {
    $sWhere[] =  '  vl.lab_id IN (' . $_POST['vlLab'] . ')';
}
if (isset($_POST['status']) && $_POST['status'] != '') {
    $sWhere[] =  ' vl.result_status = "' . $_POST['status'] . '"';
}
if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
    $sWhere[] = ' vl.patient_art_no like "%' . $_POST['patientId'] . '%"';
}
if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
    $sWhere[] = " CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,'')) like '%" . $_POST['patientName'] . "%'";
}
/*
$whereResult = '';
if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'result') {
    $whereResult = 'vl.result != ""  ';
} else if (isset($_POST['reqSampleType']) && trim($_POST['reqSampleType']) == 'noresult') {
    $whereResult = '(vl.result IS NULL OR vl.result = "")  ';
}
if(!empty($whereResult))
    $sWhere[] = $whereResult;
    */
//$sFilter = '';

if (!empty($_SESSION['facilityMap'])) {
    $sWhere[] = "  vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
}

//  $sWhere[] = ' (vl.result_status= 1 OR LOWER(vl.result) IN ("failed", "fail", "invalid"))';
if (!empty($sWhere)) {
    $sWhere = implode(' AND ', $sWhere);
    $sQuery = $sQuery . ' WHERE ' . $sWhere;
}


//echo $sQuery; die;
if (!empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . " ORDER BY " . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
$rResult = $db->rawQuery($sQuery);

/* Data set length after filtering */
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $aResultFilterTotal['totalCount'];

$output = array(
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iTotal,
    "aaData" => []
);
$editRequest = false;
if ((_isAllowed("/vl/requests/editVlRequest.php"))) {
    $editRequest = true;
}

foreach ($rResult as $aRow) {
    if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
    } else {
        $aRow['sample_collection_date'] = '';
    }
    if (isset($aRow['last_modified_datetime']) && trim((string) $aRow['last_modified_datetime']) != '' && $aRow['last_modified_datetime'] != '0000-00-00 00:00:00') {
        $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);
    } else {
        $aRow['last_modified_datetime'] = '';
    }

    $patientFname = $aRow['patient_first_name'];
    $patientMname = $aRow['patient_middle_name'];
    $patientLname = $aRow['patient_last_name'];

    $row = [];
    $row[] = '<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['vl_sample_id'] . '"  value="' . $aRow['vl_sample_id'] . '" onchange="resetBtnShowHide();" onclick="toggleTest(this);"  />';
    $row[] = $aRow['sample_code'];
    if ($_SESSION['instanceType'] != 'standalone') {
        $row[] = $aRow['remote_sample_code'];
    }
    $row[] = $aRow['sample_collection_date'];
    $row[] = $aRow['batch_code'];
    $row[] = $aRow['patient_art_no'];
    $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['facility_state']);
    $row[] = ($aRow['facility_district']);
    $row[] = ($aRow['sample_name']);
    $row[] = $aRow['result'];
    $row[] = $aRow['last_modified_datetime'];
    $row[] = ($aRow['status_name']);
    if ($editRequest) {
        $row[] = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Failed result retest") . '" onclick="retestSample(\'' . trim(base64_encode((string) $aRow['vl_sample_id'])) . '\')"><em class="fa-solid fa-arrows-rotate"></em>' . _translate("Retest") . '</a>';
    }

    $output['aaData'][] = $row;
}
echo MiscUtility::convertToUtf8AndEncode($output);

$db->commitTransaction();
} catch (Exception $exc) {
     LoggerUtility::log('error', $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
