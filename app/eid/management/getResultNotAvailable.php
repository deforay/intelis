<?php

use Laminas\Diactoros\ServerRequest;
use const SAMPLE_STATUS\REJECTED;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);


    /** @var FacilitiesService $facilitiesService */
    $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

    $tableName = "form_eid";
    $primaryKey = "eid_id";
    $key = (string) $general->getGlobalConfig('key');

    $aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.child_id', 'vl.child_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'fd.facility_name'];
    $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.child_id', 'vl.child_name', 'vl.sample_collection_date', 'fd.facility_name'];
    if ($general->isStandaloneInstance()) {
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
        $sWhere[] = $sWhereSub;
    }




    $sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*,
                f.*,
                s.*,
                fd.facility_name as labName,
                ts.status_name FROM form_eid as vl
                LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
                LEFT JOIN r_eid_sample_type as s ON s.sample_id=vl.specimen_type
                LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
                INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                WHERE vl.result_status != " . REJECTED . "
                AND vl.sample_code is NOT NULL AND (vl.result IS NULL OR vl.result='')";
    if (isset($_POST['noResultBatchCode']) && trim((string) $_POST['noResultBatchCode']) !== '') {
        $sWhere[] = ' b.batch_code LIKE "%' . $_POST['noResultBatchCode'] . '%"';
    }

    [$start_date, $end_date] = DateUtility::convertDateRange($_POST['noResultSampleTestDate'] ?? '');
    if (isset($_POST['noResultSampleTestDate']) && trim((string) $_POST['noResultSampleTestDate']) !== '') {
        if (trim((string) $start_date) === trim((string) $end_date)) {
            $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
        } else {
            $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['noResultSampleType']) && $_POST['noResultSampleType'] != '') {
        $sWhere[] = ' s.sample_id = "' . $_POST['noResultSampleType'] . '"';
    }
    if (isset($_POST['noResultState']) && trim((string) $_POST['noResultState']) !== '') {
        $sWhere[] = " f.facility_state_id = '" . $_POST['noResultState'] . "' ";
    }
    if (isset($_POST['noResultDistrict']) && trim((string) $_POST['noResultDistrict']) !== '') {
        $sWhere[] = " f.facility_district_id = '" . $_POST['noResultDistrict'] . "' ";
    }
    if (isset($_POST['noResultFacilityName']) && $_POST['noResultFacilityName'] != '') {
        $sWhere[] = ' f.facility_id IN (' . $_POST['noResultFacilityName'] . ')';
    }
    if (isset($_POST['noResultGender']) && $_POST['noResultGender'] != '') {
        if (trim((string) $_POST['noResultGender']) === "unreported") {
            $sWhere[] =  ' (vl.child_gender = "unreported" OR vl.child_gender ="" OR vl.child_gender IS NULL)';
        } else {
            $sWhere[] =  ' (vl.child_gender IS NOT NULL AND vl.child_gender ="' . $_POST['noResultGender'] . '") ';
        }
    }
    if (isset($_POST['noResultPatientPregnant']) && $_POST['noResultPatientPregnant'] != '') {
        $sWhere[] = ' vl.is_patient_pregnant = "' . $_POST['noResultPatientPregnant'] . '"';
    }
    if (isset($_POST['noResultPatientBreastfeeding']) && $_POST['noResultPatientBreastfeeding'] != '') {
        $sWhere[] = ' vl.is_patient_breastfeeding = "' . $_POST['noResultPatientBreastfeeding'] . '"';
    }


    if (!empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    }


    if ($sWhere !== []) {
        $sWhere = ' AND ' . implode(' AND ', $sWhere);
        $sQuery = $sQuery . ' ' . $sWhere;
    }

    $sQuery .= ' group by vl.eid_id';
    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
    }
    $_SESSION['resultNotAvailable'] = $sQuery;

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }

    $rResult = $db->rawQuery($sQuery);
    $aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
    $iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];
    $_SESSION['resultNotAvailableCount'] = $iTotal;


    $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

    foreach ($rResult as $aRow) {
        if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        } else {
            $aRow['sample_collection_date'] = '';
        }
        $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';
        $childName = $general->crypto('doNothing', $aRow['child_name'], $aRow[$decrypt]);

        $row = [];

        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
            $childName = $general->crypto('decrypt', $childName, $key);
        }
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['child_id'];
        $row[] = ($childName);
        $row[] = $aRow['sample_collection_date'];
        $row[] = ($aRow['labName']);
        $row[] = ($aRow['status_name']);
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
