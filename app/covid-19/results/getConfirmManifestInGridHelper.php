<?php



//system config
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
$sCode = 'sample_code';
$configQuery = "SELECT `value` FROM global_config WHERE name ='vl_form'";
$configResult = $db->query($configQuery);
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
/* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
$aColumns = array('p.manifest_code', 'p.module', "DATE_FORMAT(p.request_created_datetime,'%d-%b-%Y %H:%i:%s')");
$orderColumns = array('p.manifest_id', 'p.module', 'p.manifest_code', 'p.manifest_id', 'p.request_created_datetime');
/* Indexed column (used for fast and accurate table cardinality) */
// $sIndexColumn = "manifest_id";
// $sTable = "covid19_positive_confirmation_manifest";
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
$sWhere = "";
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
    $sWhere .= $sWhereSub;
}
/* Individual column filtering */
for ($i = 0; $i < count($aColumns); $i++) {
    if (isset($_POST['bSearchable_' . $i]) && $_POST['bSearchable_' . $i] == "true" && $_POST['sSearch_' . $i] != '') {
        if ($sWhere == "") {
            $sWhere .= $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
        } else {
            $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
        }
    }
}
/*
         * SQL queries
         * Get data to display
        */
$facilityQuery = '';

$tableName = "form_covid19";
$primaryKey = "covid19_id";
$sQuery = "select p.request_created_datetime, p.manifest_code, p.manifest_status, p.module, p.manifest_id,count(vl." . $sCode . ") as sample_code from form_covid19 vl right join covid19_positive_confirmation_manifest p on vl.positive_test_manifest_id = p.manifest_id";


if (isset($sWhere) && !empty($sWhere)) {
    $sWhere = ' WHERE ' . $sWhere;
}
if (isset($vlfmResult[0]['facilityId'])) {
    $sWhere = $sWhere . " AND facility_id IN(" . $vlfmResult[0]['facilityId'] . ")";
    $facilityQuery = " AND facility_id IN(" . $vlfmResult[0]['facilityId'] . ")";
}
$sQuery = $sQuery . ' ' . $sWhere;
$sQuery = $sQuery . ' GROUP BY p.manifest_code';
if (isset($sOrder) && !empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}
if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// echo($sQuery);die;
$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */
$aResultFilterTotal = $db->rawQuery("select p.request_created_datetime ,p.manifest_code,p.manifest_status,count(vl." . $sCode . ") as sample_code from $tableName vl right join covid19_positive_confirmation_manifest p on vl.positive_test_manifest_id = p.manifest_id $sWhere group by p.manifest_id order by $sOrder");
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
$aResultTotal =  $db->rawQuery("select p.request_created_datetime ,p.manifest_code,p.manifest_status,count(vl." . $sCode . ") as sample_code from $tableName vl right join covid19_positive_confirmation_manifest p on vl.positive_test_manifest_id = p.manifest_id where vl.vlsm_country_id ='" . $configResult[0]['value'] . "' $facilityQuery group by p.manifest_id");
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);
$iTotal = count($aResultTotal);
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
$edit = false;
if (isset($_SESSION['privileges']) && (in_array("generate-confirmation-manifest.php", $_SESSION['privileges']))) {
    $package = true;
}
if (isset($_SESSION['privileges']) && (in_array("covid-19-edit-confirmation-manifest.php", $_SESSION['privileges']))) {
    $edit = true;
}

foreach ($rResult as $aRow) {
    $humanDate = "";
    $printBarcode = '<a href="generate-confirmation-manifest.php?id=' . base64_encode($aRow['manifest_code']) . '" class="btn btn-info btn-xs" style="margin-right: 2px;" title="Print Barcode" target="_blank"><em class="fa-solid fa-barcode"></em> Print Barcode</a>';
    if (trim($aRow['request_created_datetime']) != "" && $aRow['request_created_datetime'] != '0000-00-00 00:00:00') {
        $date = $aRow['request_created_datetime'];
        $humanDate =  date("d-M-Y H:i:s", strtotime($date));
    }
    $disable = '';
    $pointerEvent = '';
    if ($aRow['manifest_status'] == 'dispatch') {
        $pointerEvent = "pointer-events:none;";
        $disable = "disabled";
    }
    $row = [];
    //$row[] = '<input type="checkbox" name="chkPackage[]" class="chkPackage" id="chkPackage' . $aRow['manifest_id'] . '"  value="' . $aRow['manifest_id'] . '" onclick="checkPackage(this);"  />';
    $row[] = $aRow['manifest_code'];
    $row[] = strtoupper($aRow['module']);
    $row[] = $aRow['sample_code'];
    $row[] = $humanDate;
    if ($package || $edit) {
        if ($edit) {
            $editBtn = '<a href="covid-19-edit-confirmation-manifest.php?id=' . base64_encode($aRow['manifest_id']) . '" class="btn btn-primary btn-xs" ' . $disable . ' style="margin-right: 2px;' . $pointerEvent . '" title="Edit"><em class="fa-solid fa-pen-to-square"></em> Edit</em></a>';
        } else {
            $editBtn = '';
        }
        $row[] = $editBtn . '&nbsp;&nbsp;' . $printBarcode;
    }
    $output['aaData'][] = $row;
}
echo json_encode($output);
