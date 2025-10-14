<?php


use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    /** @var FacilitiesService $facilitiesService */
    $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

    $tableName = "form_vl";
    $primaryKey = "vl_sample_id";


    $sampleCode = 'sample_code';
    $aColumns = ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'lab_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name'];
    $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'lab_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'];

    if ($general->isSTSInstance()) {
        $sampleCode = 'remote_sample_code';
    } elseif ($general->isStandaloneInstance()) {
        $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
        $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
    }
    /* Indexed column (used for fast and accurate table cardinality) */
    $sIndexColumn = $primaryKey;

    $sTable = $tableName;

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

    $sQuery = "SELECT
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
    LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
    LEFT JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id
    LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
    LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id=vl.implementing_partner
    LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing
    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

    $failedStatusIds = [
        SAMPLE_STATUS\ON_HOLD,
        SAMPLE_STATUS\LOST_OR_MISSING,
        SAMPLE_STATUS\TEST_FAILED
    ];

    if (!empty($_POST['sampleCollectionDate'])) {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

        if (trim((string) $start_date) == trim((string) $end_date)) {
            $sWhere[] = " DATE(vl.sample_collection_date) like '$start_date'";
        } else {
            $sWhere[] =  " DATE(vl.sample_collection_date) BETWEEN '$start_date' AND '$end_date'";
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
    if (isset($_POST['status']) && !empty($_POST['status'])) {
        $sWhere[] =  ' vl.result_status IN (' . $_POST['status'] . ')';
    } else {
        $sWhere[] =  ' vl.result_status IN (' . implode(',', $failedStatusIds) . ')';
    }
    if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
        $sWhere[] = ' vl.patient_art_no like "%' . $_POST['patientId'] . '%"';
    }
    if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
        $sWhere[] = " CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,'')) like '%" . $_POST['patientName'] . "%'";
    }


    if (!empty($_SESSION['facilityMap'])) {
        $sWhere[] = "  vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
    }

    //  $sWhere[] = ' (vl.result_status= 1 OR LOWER(vl.result) IN ("failed", "fail", "invalid"))';
    if (!empty($sWhere)) {
        $sWhere = implode(' AND ', $sWhere);
        $sQuery = "$sQuery WHERE $sWhere";
    }



    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $sQuery = "$sQuery ORDER BY $sOrder";
    }

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
    }

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

    $output = [
        "sEcho" => (int) $_POST['sEcho'],
        "iTotalRecords" => $resultCount,
        "iTotalDisplayRecords" => $resultCount,
        "aaData" => []
    ];

    $editRequest = false;
    if ((_isAllowed("/vl/requests/editVlRequest.php"))) {
        $editRequest = true;
    }
    //echo '<pre>'; print_r($rResult); die;
    foreach ($rResult as $aRow) {
        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '', true);

        $patientFname = $aRow['patient_first_name'];
        $patientMname = $aRow['patient_middle_name'];
        $patientLname = $aRow['patient_last_name'];

        $row = [];
        $row[] = '<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['vl_sample_id'] . '"  value="' . $aRow['vl_sample_id'] . '" onchange="resetBtnShowHide();" onclick="toggleTest(this);"  />';
        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        $row[] = $aRow['sample_collection_date'];
        $row[] = $aRow['batch_code'];
        $row[] = $aRow['patient_art_no'];
        $row[] = trim("$patientFname $patientMname $patientLname");
        $row[] = $aRow['facility_name'];
        $row[] = $aRow['facility_state'];
        $row[] = $aRow['facility_district'];
        $row[] = $aRow['sample_name'];
        $row[] = $aRow['result'];
        $row[] = $aRow['last_modified_datetime'];
        $row[] = $aRow['status_name'];
        if ($editRequest) {
            $row[] = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Failed result retest") . '" onclick="retestSample(\'' . trim(base64_encode((string) $aRow['vl_sample_id'])) . '\')"><em class="fa-solid fa-arrows-rotate"></em>' . _translate("Retest") . '</a>';
        }

        $output['aaData'][] = $row;
    }
    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
}
