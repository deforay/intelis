<?php

use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

if ($general->isSTSInstance()) {
    $sCode = 'remote_sample_code';
} elseif ($general->isLISInstance() || $general->isStandaloneInstance()) {
    $sCode = 'sample_code';
}

if (empty($_POST['module'])) {
    echo json_encode([]);
    exit;
}

$testType = $_POST['module'];
$tableName = TestsService::getTestTableName($testType);
$primaryKey = TestsService::getPrimaryColumn($testType);

$sql = "UPDATE specimen_manifests
                SET lab_id = (SELECT lab_id FROM $tableName WHERE $tableName.sample_package_id = specimen_manifests.manifest_id and lab_id > 0 limit 1)
                WHERE specimen_manifests.lab_id is null OR specimen_manifests.lab_id = 0";

$db->rawQuery($sql);

// $sql = "UPDATE $tableName
//         SET lab_id = (SELECT lab_id
//                         FROM specimen_manifests
//                         WHERE lab_id > 0
//                         AND specimen_manifests.manifest_code = $tableName.sample_package_code
//                         LIMIT 1)
//         WHERE lab_id IS NULL OR lab_id = 0";

// $db->rawQuery($sql);


$vlForm = (int) $general->getGlobalConfig('vl_form');

$aColumns = array('p.manifest_code', 'p.module', 'facility_name', "DATE_FORMAT(p.request_created_datetime,'%d-%b-%Y %H:%i:%s')");
$orderColumns = array('p.manifest_code', 'p.module', 'facility_name', 'p.number_of_samples', 'p.request_created_datetime');

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
$sWhere[] = "p.module = '$testType'";
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




$sQuery = "SELECT p.request_created_datetime,
            p.manifest_code, p.manifest_status,
            p.module, p.manifest_id, p.number_of_samples,
            lab.facility_name as labName
            FROM specimen_manifests p
            LEFT JOIN facility_details lab on lab.facility_id = p.lab_id";

if (!empty($_SESSION['facilityMap'])) {
    $sQuery .= " INNER JOIN $tableName t on t.sample_package_code = p.manifest_code ";
    $sWhere[] = " t.facility_id IN(" . $_SESSION['facilityMap'] . ") ";
}

if (!empty($sWhere)) {
    $sWhere = ' WHERE ' . implode(' AND ', $sWhere);
} else {
    $sWhere = '';
}

$sQuery = $sQuery . ' ' . $sWhere;
if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
[$rResult, $resultCount] = $db->getDataAndCount($sQuery);


$output = [
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $resultCount,
    "iTotalDisplayRecords" => $resultCount,
    "aaData" => []
];

$editUrl = '/specimen-referral-manifest/edit-manifest.php?t=' . $_POST['module'];

$editAllowed = false;
if (_isAllowed($editUrl)) {
    $editAllowed = true;
}

foreach ($rResult as $aRow) {

    $packageId = base64_encode($aRow['manifest_id']);
    //$packageCode = ($aRow['manifest_code']);
    $printManifestPdfText = _translate("Print Manifest PDF");

    $printBarcode = <<<BARCODEBUTTON
    <a href="javascript:void(0);" onclick="generateManifestPDF('{$packageId}');" class="btn btn-info btn-xs print-manifest" data-package-id="{$packageId}" title="{$printManifestPdfText}">
        <em class="fa-solid fa-barcode"></em> {$printManifestPdfText}
    </a>
    BARCODEBUTTON;

    $editBtn = '';
    if ($editAllowed) {
        $editBtn = '<a href="' . $editUrl . '&id=' . base64_encode((string) $aRow['manifest_id']) . '" class="btn btn-primary btn-xs" ' . $disable . ' style="margin-right: 2px;' . $pointerEvent . '" title="Edit"><em class="fa-solid fa-pen-to-square"></em> Edit</em></a>';
    }

    $disable = '';
    $pointerEvent = '';
    if ($aRow['manifest_status'] == 'dispatch') {
        $pointerEvent = "pointer-events:none;";
        $disable = "disabled";
    }
    if ($testType == 'generic-tests') {
        $aRow['module'] = "OTHER LAB TESTS ";
    }
    $row = [];
    $row[] = $aRow['manifest_code'];
    $row[] = strtoupper((string) $aRow['module']);
    $row[] = $aRow['labName'];
    $row[] = $aRow['number_of_samples'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'] ?? '', true);
    $row[] = $editBtn . '&nbsp;&nbsp;' . $printBarcode;

    $output['aaData'][] = $row;
}
echo json_encode($output);
