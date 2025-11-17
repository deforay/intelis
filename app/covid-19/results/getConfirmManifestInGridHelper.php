<?php


use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$sCode = 'sample_code';
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$aColumns = ['p.manifest_code', 'p.module', "DATE_FORMAT(p.request_created_datetime,'%d-%b-%Y %H:%i:%s')"];
$orderColumns = ['p.manifest_id', 'p.module', 'p.manifest_code', 'p.manifest_id', 'p.request_created_datetime'];

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
        if ($sWhereSub === "") {
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


if ($sWhere !== '' && $sWhere !== '0') {
    $sWhere = ' WHERE ' . $sWhere;
}
if (isset($vlfmResult[0]['facilityId'])) {
    $sWhere = $sWhere . " AND facility_id IN(" . $vlfmResult[0]['facilityId'] . ")";
    $facilityQuery = " AND facility_id IN(" . $vlfmResult[0]['facilityId'] . ")";
}
$sQuery = $sQuery . ' ' . $sWhere;
$sQuery .= ' GROUP BY p.manifest_code';
if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}
if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

[$rResult, $resultCount] = $db->getDataAndCount($sQuery);

$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $resultCount, "iTotalDisplayRecords" => $resultCount, "aaData" => []];
$package = false;
$edit = false;

$package = _isAllowed("generate-confirmation-manifest.php");
$edit = _isAllowed("covid-19-edit-confirmation-manifest.php");

foreach ($rResult as $aRow) {

    $printBarcode = '<a href="generate-confirmation-manifest.php?id=' . base64_encode((string) $aRow['manifest_code']) . '" class="btn btn-info btn-xs" style="margin-right: 2px;" title="Print Barcode" target="_blank"><em class="fa-solid fa-barcode"></em> Print Barcode</a>';
    $disable = '';
    $pointerEvent = '';
    if ($aRow['manifest_status'] == 'dispatch') {
        $pointerEvent = "pointer-events:none;";
        $disable = "disabled";
    }
    $row = [];
    $row[] = $aRow['manifest_code'];
    $row[] = strtoupper((string) $aRow['module']);
    $row[] = $aRow['sample_code'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'] ?? '');
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
