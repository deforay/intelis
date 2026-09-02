<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tableName = "facility_details";
$primaryKey = "facility_id";

$aColumns = ['facility_code', 'facility_name', 'facility_type_name', 'p.geo_name', 'd.geo_name', 'status'];

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;

$sOffset = $sLimit = null;
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
    $sOffset = (int) $_POST['iDisplayStart'];
    $sLimit = (int) $_POST['iDisplayLength'];
}


$sOrder = $general->generateDataTablesSorting($_POST, $aColumns);



$sWhere = [];
$columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? null, $aColumns);
if (!empty($columnSearch)) {
    $sWhere[] = $columnSearch;
}


$facilityType = $_POST['facilityType'] ?? null;
if (isset($facilityType) && trim((string) $facilityType) !== '') {
    $sWhere[] = ' f_t.facility_type_id = ' . (int) $_POST['facilityType'];
}
if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
    $sWhere[] = ' d.geo_id = ' . (int) $_POST['district'] . ' ';
}
if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
    $sWhere[] = ' p.geo_id = ' . (int) $_POST['state'] . ' ';
}
$qry = "";
if (isset($_POST['testType']) && trim((string) $_POST['testType']) !== '' && !empty($facilityType)) {
    if ($facilityType == '2') {
        $qry = " LEFT JOIN testing_labs tl ON tl.facility_id=f_d.facility_id";
        $sWhere[] = ' tl.test_type = "' . $db->escape((string) $_POST['testType']) . '"';
    } else {
        $qry = " LEFT JOIN health_facilities hf ON hf.facility_id=f_d.facility_id";
        $sWhere[] = ' hf.test_type = "' . $db->escape((string) $_POST['testType']) . '"';
    }
}
if (isset($_POST['activeFacility']) && trim((string) $_POST['activeFacility']) !== '') {
    $sWhere[] = " f_d.status = '" . $db->escape((string) $_POST['activeFacility']) . "' ";
}
$orphanFacility = $_POST['orphanFacility'] ?? '';
if ($orphanFacility === 'yes') {
    $sWhere[] = "(p.geo_status IS NULL OR p.geo_status != 'active' OR d.geo_status IS NULL OR d.geo_status != 'active'"
        . " OR (d.geo_id IS NOT NULL AND f_d.facility_state_id IS NOT NULL AND (d.geo_parent IS NULL OR d.geo_parent <> f_d.facility_state_id)))";
}

$sQuery = "SELECT SQL_CALC_FOUND_ROWS f_d.*, f_t.*,p.geo_name as province ,d.geo_name as district,
            p.geo_status as province_status, d.geo_status as district_status, d.geo_parent as district_parent
            FROM facility_details as f_d
            LEFT JOIN facility_type as f_t ON f_t.facility_type_id=f_d.facility_type
            LEFT JOIN geographical_divisions as p ON f_d.facility_state_id = p.geo_id
            LEFT JOIN geographical_divisions as d ON f_d.facility_district_id = d.geo_id $qry ";

if ($sWhere !== []) {
    $sWhere = ' where ' . implode(' AND ', $sWhere);
    $sQuery = $sQuery . ' ' . $sWhere;
}
if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
// echo $sQuery;
$rResult = $db->rawQuery($sQuery);
// print_r($rResult);

$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

foreach ($rResult as $aRow) {
    $provinceName = $aRow['province'];
    $districtName = $aRow['district'];
    $provinceStatus = strtolower((string) ($aRow['province_status'] ?? ''));
    $districtStatus = strtolower((string) ($aRow['district_status'] ?? ''));

    // The district name resolves purely by id, independent of the province. So a
    // district can show even when its hierarchy link is broken. Two broken cases:
    //  - mis-parented: district exists but is parented under a different province.
    //  - no province: the facility has a district but no province set at all.
    // In both, the Edit form can't select the district, so we flag it on the list.
    $stateId = $aRow['facility_state_id'] ?? null;
    $districtParent = $aRow['district_parent'] ?? null;
    $districtNoProvince = !empty($districtName) && empty($stateId);
    $districtMisparented = !empty($districtName) && !empty($stateId)
        && (string) $districtParent !== (string) $stateId;

    if (!empty($provinceName) && $provinceStatus !== 'active') {
        $provinceName .= ' (' . _translate(ucwords($provinceStatus ?: 'missing')) . ')';
    }
    if (!empty($districtName) && $districtStatus !== 'active') {
        $districtName .= ' (' . _translate(ucwords($districtStatus ?: 'missing')) . ')';
    } elseif ($districtMisparented) {
        $districtName .= ' (' . _translate('Not under this province') . ')';
    } elseif ($districtNoProvince) {
        $districtName .= ' (' . _translate('No province set') . ')';
    }

    // Incorrect geolocation data is a problem regardless of whether the facility is
    // active, so this is intentionally NOT gated on facility status (matches the
    // orphan filter WHERE clause below).
    $isOrphan = (
        empty($provinceStatus) || $provinceStatus !== 'active' ||
        empty($districtStatus) || $districtStatus !== 'active' ||
        $districtMisparented || $districtNoProvince
    );

    $row = [];
    $row[] = $aRow['facility_code'];
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['facility_type_name']);
    $row[] = $provinceName;
    $row[] = $districtName;
    $row[] = ($aRow['status']);
    if (_isAllowed("editFacility.php") && ($general->isSTSInstance() || $general->isStandaloneInstance())) {
        $row[] = '<a href="editFacility.php?id=' . base64_encode((string) $aRow['facility_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
    }
    if ($isOrphan) {
        $row['DT_RowClass'] = 'orphan-facility';
        $row['DT_RowAttr'] = ['data-orphan' => 'yes'];
    } else {
        $row['DT_RowAttr'] = ['data-orphan' => 'no'];
    }
    $output['aaData'][] = $row;
}

echo json_encode($output);
