<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
//system config
$sarr = $general->getSystemConfig();
$facilityMap = null;
if ($_SESSION['instanceType'] == 'remoteuser') {
    $sCode = 'remote_sample_code';
    $facilityMap = $facilitiesService->getUserFacilityMap($_SESSION['userId']);
} else if ($sarr['sc_user_type'] == 'vluser' || $sarr['sc_user_type'] == 'standalone') {
    $sCode = 'sample_code';
}


// in case module is not set, we pick vl as the default one
if ($_POST['module'] == 'vl' || empty($_POST['module'])) {
    $module = 'vl';
    $tableName = "form_vl";
    $primaryKey = "vl_sample_id";
} else if ($_POST['module'] == 'eid') {
    $module = 'eid';
    $tableName = "form_eid";
    $primaryKey = "eid_id";
} else if ($_POST['module'] == 'C19' || $_POST['module'] == 'covid19') {
    $module = 'covid19';
    $tableName = "form_covid19";
    $primaryKey = "covid19_id";
} else if ($_POST['module'] == 'hepatitis') {
    $module = 'hepatitis';
    $tableName = "form_hepatitis";
    $primaryKey = "hepatitis_id";
} else if ($_POST['module'] == 'tb') {
    $module = 'tb';
    $tableName = "form_tb";
    $primaryKey = "tb_id";
} else if ($_POST['module'] == 'generic-tests') {
    $module = 'generic-tests';
    $tableName = "form_generic";
    $primaryKey = "sample_id";
}


$vlForm = $general->getGlobalConfig('vl_form');

/* Array of database columns which should be read and sent back to DataTables. Use a space where
        * you want to insert a non-database field (for example a counter or static image)
    */
$aColumns = array('p.package_code', 'p.module', 'facility_name', "DATE_FORMAT(p.request_created_datetime,'%d-%b-%Y %H:%i:%s')");
$orderColumns = array('p.package_id', 'p.module', 'facility_name', 'p.package_code', 'p.package_id', 'p.request_created_datetime');
/* Indexed column (used for fast and accurate table cardinality) */
// $sIndexColumn = "package_id";
// $sTable = "package_details";
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
$sWhere[] = "p.module = '$module'";
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
/*
        * SQL queries
        * Get data to display
    */


$sQuery = "SELECT SQL_CALC_FOUND_ROWS p.request_created_datetime,
            p.package_code, p.package_status,
            p.module, p.package_id, p.number_of_samples,
            lab.facility_name as labName
            FROM package_details p
            INNER JOIN facility_details lab on lab.facility_id = p.lab_id";

if (!empty($facilityMap)) {
    $sQuery .= " INNER JOIN $tableName t on t.sample_package_id = p.package_id ";
    $sWhere[] = " t.facility_id IN(" . $facilityMap . ") ";
    //$sWhere[] = " lab.facility_id IN(" . $facilityMap . ") ";
}

if (!empty($sWhere)) {
    $sWhere = ' WHERE ' . implode(' AND ', $sWhere);
} else {
    $sWhere = '';
}

$sQuery = $sQuery . ' ' . $sWhere;
$sQuery = $sQuery . ' GROUP BY p.package_id';
if (isset($sOrder) && !empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}
if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// echo($sQuery);die;
//error_log($sQuery);
$rResult = $db->rawQuery($sQuery);

/* Data set length after filtering */
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];


/*
        * Output
    */
$output = array(
    "sEcho" => intval($_POST['sEcho']),
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => array()
);
$package = false;
if (isset($_SESSION['privileges']) && (in_array("editSpecimenReferralManifest.php", $_SESSION['privileges']))) {
    $package = true;
}

foreach ($rResult as $aRow) {
    $humanDate = "";
    $printBarcode = '<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="Print bar code" onclick="generateManifestPDF(\'' . base64_encode($aRow['package_id']) . '\',\'pk1\');"><em class="fa-solid fa-barcode"></em> Print Barcode</a>';
    if (trim($aRow['request_created_datetime']) != "" && $aRow['request_created_datetime'] != '0000-00-00 00:00:00') {
        $date = $aRow['request_created_datetime'];
        $humanDate =  date("d-M-Y H:i:s", strtotime($date));
    }
    $disable = '';
    $pointerEvent = '';
    if ($aRow['package_status'] == 'dispatch') {
        $pointerEvent = "pointer-events:none;";
        $disable = "disabled";
    }
    if ($module == 'generic-tests') {
        $aRow['module'] = "LAB TESTS ";
    }
    $row = [];
    //$row[] = '<input type="checkbox" name="chkPackage[]" class="chkPackage" id="chkPackage' . $aRow['package_id'] . '"  value="' . $aRow['package_id'] . '" onclick="checkPackage(this);"  />';
    $row[] = $aRow['package_code'];
    $row[] = strtoupper($aRow['module']);
    $row[] = $aRow['labName'];
    $row[] = $aRow['number_of_samples'];
    $row[] = $humanDate;
    if ($package) {
        if ($_SESSION['roleCode'] == 'AD' || $_SESSION['roleCode'] == 'ad') {
            $editBtn = '<a href="editSpecimenReferralManifest.php?t=' . base64_encode($_POST['module']) . '&id=' . base64_encode($aRow['package_id']) . '" class="btn btn-primary btn-xs" ' . $disable . ' style="margin-right: 2px;' . $pointerEvent . '" title="Edit"><em class="fa-solid fa-pen-to-square"></em> Edit</em></a>';
        } else {
            $editBtn = '';
        }
        $row[] = $editBtn . '&nbsp;&nbsp;' . $printBarcode;
    }
    $output['aaData'][] = $row;
}
echo json_encode($output);
