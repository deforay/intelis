<?php

use App\Services\DatabaseService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Utilities\DateUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


//system config
$sarr = $general->getSystemConfig();

if ($general->isSTSInstance()) {
    $sCode = 'remote_sample_code';
} elseif ($general->isLISInstance() || $general->isStandaloneInstance()) {
    $sCode = 'sample_code';
}


// in case module is not set, we pick vl as the default one
switch ($_POST['module']) {
    case 'eid':
        $module = 'eid';
        $tableName = "form_eid";
        $primaryKey = "eid_id";
        break;
    case 'cd4':
        $module = 'cd4';
        $tableName = "form_cd4";
        $primaryKey = "cd4_id";
        break;
    case 'covid19':
        $module = 'covid19';
        $tableName = "form_covid19";
        $primaryKey = "covid19_id";
        break;
    case 'hepatitis':
        $module = 'hepatitis';
        $tableName = "form_hepatitis";
        $primaryKey = "hepatitis_id";
        break;
    case 'tb':
        $module = 'tb';
        $tableName = "form_tb";
        $primaryKey = "tb_id";
        break;
    case 'generic-tests':
        $module = 'generic-tests';
        $tableName = "form_generic";
        $primaryKey = "sample_id";
        break;
    default:
        $module = 'vl';
        $tableName = "form_vl";
        $primaryKey = "vl_sample_id";
        break;
}

$vlForm = (int) $general->getGlobalConfig('vl_form');

$aColumns = array('p.package_code', 'p.module', 'facility_name', "DATE_FORMAT(p.request_created_datetime,'%d-%b-%Y %H:%i:%s')");
$orderColumns = array('p.package_id', 'p.module', 'facility_name', 'p.package_code', 'p.package_id', 'p.request_created_datetime');



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
$sWhere = [];
$sWhere[] = "p.module = '$module'";
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

/*
 * SQL queries
 * Get data to display
 */


$sQuery = "SELECT p.request_created_datetime,
            p.package_code, p.package_status,
            p.module, p.package_id, p.number_of_samples,
            lab.facility_name as labName
            FROM package_details p
            INNER JOIN facility_details lab on lab.facility_id = p.lab_id";

if (!empty($_SESSION['facilityMap'])) {
    $sQuery .= " INNER JOIN $tableName t on t.sample_package_id = p.package_id ";
    $sWhere[] = " t.facility_id IN(" . $_SESSION['facilityMap'] . ") ";
}

if (!empty($sWhere)) {
    $sWhere = ' WHERE ' . implode(' AND ', $sWhere);
} else {
    $sWhere = '';
}

$sQuery = $sQuery . ' ' . $sWhere;
$sQuery = $sQuery . ' GROUP BY p.package_id';
if (!empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

[$rResult, $resultCount] = $db->getQueryResultAndCount($sQuery);

/*
 * Output
 */
$output = array(
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $resultCount,
    "iTotalDisplayRecords" => $resultCount,
    "aaData" => []
);

$editUrl = '/specimen-referral-manifest/edit-manifest.php?t=' . $_POST['module'];

$editAllowed = false;
if (_isAllowed($editUrl)) {
    $editAllowed = true;
}

foreach ($rResult as $aRow) {

    $packageId = base64_encode($aRow['package_id']);
    //$packageCode = ($aRow['package_code']);
    $printManifestPdfText = _translate("Print Manifest PDF");

    $printBarcode = <<<BARCODEBUTTON
    <a href="javascript:void(0);" onclick="generateManifestPDF('{$packageId}', 'pk1');" class="btn btn-info btn-xs print-manifest" data-package-id="{$packageId}" title="{$printManifestPdfText}">
        <em class="fa-solid fa-barcode"></em> {$printManifestPdfText}
    </a>
    BARCODEBUTTON;

    $editBtn = '';
    if ($editAllowed) {
        $editBtn = '<a href="' . $editUrl . '&id=' . base64_encode((string) $aRow['package_id']) . '" class="btn btn-primary btn-xs" ' . $disable . ' style="margin-right: 2px;' . $pointerEvent . '" title="Edit"><em class="fa-solid fa-pen-to-square"></em> Edit</em></a>';
    }

    $disable = '';
    $pointerEvent = '';
    if ($aRow['package_status'] == 'dispatch') {
        $pointerEvent = "pointer-events:none;";
        $disable = "disabled";
    }
    if ($module == 'generic-tests') {
        $aRow['module'] = "OTHER LAB TESTS ";
    }
    $row = [];

    $row[] = $aRow['package_code'];
    $row[] = strtoupper((string) $aRow['module']);
    $row[] = $aRow['labName'];
    $row[] = $aRow['number_of_samples'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'] ?? '', true);
    $row[] = $editBtn . '&nbsp;&nbsp;' . $printBarcode;

    $output['aaData'][] = $row;
}
echo json_encode($output);
