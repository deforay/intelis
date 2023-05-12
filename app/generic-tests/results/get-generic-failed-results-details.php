<?php


use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$facilityMap = $facilitiesService->getUserFacilityMap($_SESSION['userId']);

$gconfig = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

$tableName = "form_generic";
$primaryKey = "sample_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'lab_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');

$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'lab_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');
if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCode = 'remote_sample_code';
} elseif ($sarr['sc_user_type'] == 'standalone') {
    if (($key = array_search('vl.remote_sample_code', $aColumns)) !== false) {
        unset($aColumns[$key]);
    }
    if (($key = array_search('vl.remote_sample_code', $orderColumns)) !== false) {
        unset($orderColumns[$key]);
    }
}
/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;
/*
* Paging
*/
$sLimit = "";
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
     for ($i = 0; $i < intval($_POST['iSortingCols']); $i++) {
          if ($_POST['bSortable_' . intval($_POST['iSortCol_' . $i])] == "true") {
               if (!empty($orderColumns[intval($_POST['iSortCol_' . $i])]))
                    $sOrder .= $orderColumns[intval($_POST['iSortCol_' . $i])] . "
				 	" . ($_POST['sSortDir_' . $i]) . ", ";
          }
     }
     $sOrder = substr_replace($sOrder, "", -2);
}
/*
* Filtering
* NOTE this does not match the built-in DataTables filtering which does it
* word by word on any field. It's possible to do here, but concerned about efficiency
* on very large tables, and MySQL's regex functionality is very limited
*/

$sWhere = [];
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
    $searchArray = explode(" ", $_POST['sSearch']);
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

/* Individual column filtering */
for ($i = 0; $i < count($aColumns); $i++) {
    if (isset($_POST['bSearchable_' . $i]) && $_POST['bSearchable_' . $i] == "true" && $_POST['sSearch_' . $i] != '') {
        $sWhere[] = $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
    }
}

$sQuery = "SELECT SQL_CALC_FOUND_ROWS 
    vl.sample_id, 
    vl.sample_code, 
    vl.remote_sample_code, 
    vl.sample_collection_date, 
    vl.last_modified_datetime, 
    vl.patient_id, 
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
    FROM form_generic as vl    
    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
    LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type 
    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
    LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection 
    LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id=vl.implementing_partner
    LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_testing 
    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

$start_date = '';
$end_date = '';
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $s_c_date = explode("to", $_POST['sampleCollectionDate']);
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
    }
}

if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    if (trim($start_date) == trim($end_date)) {
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
if (isset($_POST['district']) && trim($_POST['district']) != '') {
    $sWhere[] =  " f.facility_district_id = '" . $_POST['district'] . "' ";
}
if (isset($_POST['state']) && trim($_POST['state']) != '') {
    $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
}
if (isset($_POST['vlLab']) && trim($_POST['vlLab']) != '') {
    $sWhere[] =  '  vl.lab_id IN (' . $_POST['vlLab'] . ')';
}
if (isset($_POST['status']) && $_POST['status'] != '') {
    $sWhere[] =  ' vl.result_status = "' . $_POST['status'] . '"';
}
if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
    $sWhere[] = ' vl.patient_id like "%' . $_POST['patientId'] . '%"';
}
if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
    $sWhere[] = " CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,'')) like '%" . $_POST['patientName'] . "%'";
}
//$sFilter = '';
if ($_SESSION['instanceType'] == 'remoteuser') {
    if (!empty($facilityMap)) {
        $sWhere[] = "  vl.facility_id IN (" . $facilityMap . ")  ";
    }
}
if (isset($sWhere) && count($sWhere) > 0) {
    $sWhere = implode(' AND ', $sWhere);
    $sQuery = $sQuery . ' WHERE ' . $sWhere;
}


if (isset($sOrder) && $sOrder != "") {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . " ORDER BY " . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// die($sQuery);
$rResult = $db->rawQuery($sQuery);

/* Data set length after filtering */
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $aResultFilterTotal['totalCount'];

$output = array(
    "sEcho" => intval($_POST['sEcho']),
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iTotal,
    "aaData" => array()
);
$editRequest = false;
if (isset($_SESSION['privileges']) && (in_array("editVlRequest.php", $_SESSION['privileges']))) {
    $editRequest = true;
}

foreach ($rResult as $aRow) {
    if (isset($aRow['sample_collection_date']) && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
    } else {
        $aRow['sample_collection_date'] = '';
    }
    if (isset($aRow['last_modified_datetime']) && trim($aRow['last_modified_datetime']) != '' && $aRow['last_modified_datetime'] != '0000-00-00 00:00:00') {
        $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);
    } else {
        $aRow['last_modified_datetime'] = '';
    }

    $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_id']));
    $patientMname = ($general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_id']));
    $patientLname = ($general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_id']));

    $row = [];
    $row[] = '<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['sample_id'] . '"  value="' . $aRow['sample_id'] . '" onchange="resetBtnShowHide();" onclick="toggleTest(this);"  />';
    $row[] = $aRow['sample_code'];
    if ($_SESSION['instanceType'] != 'standalone') {
        $row[] = $aRow['remote_sample_code'];
    }
    $row[] = $aRow['sample_collection_date'];
    $row[] = $aRow['batch_code'];
    $row[] = $aRow['patient_id'];
    $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['facility_state']);
    $row[] = ($aRow['facility_district']);
    $row[] = ($aRow['sample_name']);
    $row[] = $aRow['result'];
    $row[] = $aRow['last_modified_datetime'];
    $row[] = ($aRow['status_name']);
    if ($editRequest) {
        $row[] = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _("Failed result retest") . '" onclick="retestSample(\'' . trim(base64_encode($aRow['sample_id'])) . '\')"><em class="fa-solid fa-arrows-rotate"></em>' . _("Retest") . '</a>';
    }

    $output['aaData'][] = $row;
}
echo json_encode($output);
