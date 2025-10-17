<?php

use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$testType = 'tb';
$table = TestsService::getTestTableName($testType);
$primaryKeyColumn = TestsService::getPrimaryColumn($testType);

// DataTables parameters
$sLimit = "";
$aColumns = [
    'sample_package_id',
    'sample_package_code',
    'sample_count',
    'referral_lab_name',
    'referral_date'
];

// Paging
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
    $sOffset = $_POST['iDisplayStart'];
    $sLimit = $_POST['iDisplayLength'];
}

// Ordering
$sOrder = "";
if (isset($_POST['iSortCol_0'])) {
    for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
        $sortCol = (int) $_POST['iSortCol_' . $i];
        // Check if the sort column index exists in aColumns array
        if (
            isset($_POST['bSortable_' . $sortCol]) &&
            $_POST['bSortable_' . $sortCol] == "true" &&
            isset($aColumns[$sortCol])
        ) {
            $sOrder .= $aColumns[$sortCol] . " " . ($_POST['sSortDir_' . $i]) . ", ";
        }
    }
    if (!empty($sOrder)) {
        $sOrder = substr_replace($sOrder, "", -2);
    }
}

// Filtering
$sWhere = "";
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
    $searchValue = $_POST['sSearch'];
    $sWhere = " AND (";
    $sWhere .= " vl.sample_package_code LIKE '%" . $searchValue . "%' ";
    $sWhere .= " OR f2.facility_name LIKE '%" . $searchValue . "%' ";
    $sWhere .= " OR f2.facility_code LIKE '%" . $searchValue . "%' ";
    $sWhere .= ")";
}

// Main query - grouped by sample_package_id and sample_package_code
$sQuery = "SELECT 
vl.referred_to_lab_id, 
vl.sample_package_id, 
vl.sample_package_code, 
COUNT(vl.$primaryKeyColumn) as sample_count, 
f2.facility_name as referral_lab_name, 
f2.facility_code as referral_lab_code, 
MAX(vl.last_modified_datetime) as referral_date 
FROM $table as vl 
LEFT JOIN facility_details as f2 ON vl.referred_to_lab_id = f2.facility_id 
WHERE vl.referred_to_lab_id IS NOT NULL 
    AND vl.referred_to_lab_id != '' 
    AND vl.referred_to_lab_id != 0 
    AND vl.sample_package_id IS NOT NULL 
    AND vl.sample_package_id != '' 
    AND vl.sample_package_id != 0 
    $sWhere 
GROUP BY vl.sample_package_id, vl.sample_package_code, f2.facility_name, f2.facility_code";

if (!empty($sOrder)) {
    $sQuery = $sQuery . ' ORDER BY referral_date ' . $sOrder;
} else {
    $sQuery = $sQuery . ' ORDER BY referral_date DESC';
}

if (isset($sLimit) && $sLimit != '') {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// die($sQuery);
[$result, $resultCount] = $db->getDataAndCount($sQuery);
// Output
$output = [
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $resultCount,
    "iTotalDisplayRecords" => $resultCount,
    "aaData" => []
];

foreach ($result as $row) {
    $rowData = [];

    // Checkbox
    $rowData[] = '<input type="checkbox" class="sample-checkbox" value="' . $row[$primaryKeyColumn] . '" />';
    // Sample Package Code
    $rowData[] = $row['sample_package_code'] ?? '-';

    // Number of Samples
    $rowData[] = $row['sample_count'];

    // Referral Lab
    $referralLab = !empty($row['referral_lab_name']) ? $row['referral_lab_name'] : '-';
    if (!empty($row['referral_lab_code'])) {
        $referralLab .= '<br><small class="text-muted">(' . $row['referral_lab_code'] . ')</small>';
    }
    $rowData[] = $referralLab;

    // Referral Date
    $referralDate = '-';
    if (!empty($row['referral_date'])) {
        $referralDate = DateUtility::humanReadableDateFormat($row['referral_date']);
    }
    $rowData[] = $referralDate;

    // Edit Button
    $encodedId = base64_encode($row['referred_to_lab_id']);
    $encodedCode = base64_encode($row['sample_package_id']);
    $editBtn = '<a href="edit-tb-referral.php?id=' . $encodedId . '&code=' . $encodedCode . '" class="btn btn-sm btn-primary" title="Edit Package">
                    <i class="fa fa-edit"></i>
                </a>';
    $rowData[] = $editBtn;
    $output['aaData'][] = $rowData;
}

echo JsonUtility::encodeUtf8Json($output);
