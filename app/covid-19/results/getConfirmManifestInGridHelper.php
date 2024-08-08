<?php



//system config
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

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
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$aColumns = array('p.manifest_code', 'p.module', "DATE_FORMAT(p.request_created_datetime,'%d-%b-%Y %H:%i:%s')");
$orderColumns = array('p.manifest_id', 'p.module', 'p.manifest_code', 'p.manifest_id', 'p.request_created_datetime');
/* Indexed column (used for fast and accurate table cardinality) */
// $sIndexColumn = "manifest_id";
// $sTable = "covid19_positive_confirmation_manifest";
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
$sWhere = "";
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
    $sWhere .= $sWhereSub;
}

$facilityQuery = '';

$tableName = "form_covid19";
$primaryKey = "covid19_id";
$sQuery = "select p.request_created_datetime, p.manifest_code, p.manifest_status, p.module, p.manifest_id,count(vl." . $sCode . ") as sample_code from form_covid19 vl right join covid19_positive_confirmation_manifest p on vl.positive_test_manifest_id = p.manifest_id";


if (!empty($sWhere)) {
    $sWhere = ' WHERE ' . $sWhere;
}
if (isset($vlfmResult[0]['facilityId'])) {
    $sWhere = $sWhere . " AND facility_id IN(" . $vlfmResult[0]['facilityId'] . ")";
    $facilityQuery = " AND facility_id IN(" . $vlfmResult[0]['facilityId'] . ")";
}
$sQuery = $sQuery . ' ' . $sWhere;
$sQuery = $sQuery . ' GROUP BY p.manifest_code';
if (!empty($sOrder) && $sOrder !== '') {
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
$aResultTotal = $db->rawQuery("select p.request_created_datetime ,p.manifest_code,p.manifest_status,count(vl." . $sCode . ") as sample_code from $tableName vl right join covid19_positive_confirmation_manifest p on vl.positive_test_manifest_id = p.manifest_id where vl.vlsm_country_id ='" . $configResult[0]['value'] . "' $facilityQuery group by p.manifest_id");
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);
$iTotal = count($aResultTotal);
/*
 * Output
 */
$output = array(
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => []
);
$package = false;
$edit = false;
if ((_isAllowed("generate-confirmation-manifest.php"))) {
    $package = true;
}
if ((_isAllowed("covid-19-edit-confirmation-manifest.php"))) {
    $edit = true;
}

foreach ($rResult as $aRow) {
    $humanDate = "";
    $printBarcode = '<a href="generate-confirmation-manifest.php?id=' . base64_encode((string) $aRow['manifest_code']) . '" class="btn btn-info btn-xs" style="margin-right: 2px;" title="Print Barcode" target="_blank"><em class="fa-solid fa-barcode"></em> Print Barcode</a>';
    if (trim((string) $aRow['request_created_datetime']) != "" && $aRow['request_created_datetime'] != '0000-00-00 00:00:00') {
        $date = $aRow['request_created_datetime'];
        $humanDate = date("d-M-Y H:i:s", strtotime((string) $date));
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
    $row[] = strtoupper((string) $aRow['module']);
    $row[] = $aRow['sample_code'];
    $row[] = $humanDate;
    if ($package || $edit) {
        if ($edit) {
            $editBtn = '<a href="covid-19-edit-confirmation-manifest.php?id=' . base64_encode((string) $aRow['manifest_id']) . '" class="btn btn-primary btn-xs" ' . $disable . ' style="margin-right: 2px;' . $pointerEvent . '" title="Edit"><em class="fa-solid fa-pen-to-square"></em> Edit</em></a>';
        } else {
            $editBtn = '';
        }
        $row[] = $editBtn . '&nbsp;&nbsp;' . $printBarcode;
    }
    $output['aaData'][] = $row;
}
echo json_encode($output);
