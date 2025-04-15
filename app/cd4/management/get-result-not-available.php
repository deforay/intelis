<?php

use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    $db->beginReadOnlyTransaction();

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    $tableName = "form_cd4";
    $primaryKey = "cd4_id";
    $key = (string) $general->getGlobalConfig('key');

    $aColumns = array('vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'fd.facility_name');
    $orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', 'vl.sample_collection_date', 'fd.facility_name');
    if ($general->isStandaloneInstance()) {
        $aColumns = array('vl.sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'fd.facility_name');
        $orderColumns = array('vl.sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', 'vl.sample_collection_date', 'fd.facility_name');
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




    $sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*,
                    f.*,s.*,fd.facility_name as labName,
                    ts.status_name
                    FROM form_cd4 as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
                    LEFT JOIN r_cd4_sample_types as s ON s.sample_id=vl.specimen_type
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
                    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    WHERE vl.result_status != " . SAMPLE_STATUS\REJECTED . "
                    AND vl.sample_code is NOT NULL
                    AND (vl.cd4_result IS NULL OR vl.cd4_result='')";
    $start_date = '';
    $end_date = '';
    if (isset($_POST['noResultBatchCode']) && trim((string) $_POST['noResultBatchCode']) != '') {
        $sWhere[] = ' b.batch_code LIKE "%' . $_POST['noResultBatchCode'] . '%"';
    }

    if (isset($_POST['noResultSampleTestDate']) && trim((string) $_POST['noResultSampleTestDate']) != '') {
        $s_c_date = explode("to", (string) $_POST['noResultSampleTestDate']);
        //print_r($s_c_date);die;
        if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
            $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
        }
        if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
            $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
        }
        if (trim((string) $start_date) == trim((string) $end_date)) {
            $sWhere[] = ' DATE(vl.sample_collection_date) = "' . $start_date . '"';
        } else {
            $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['noResultSampleType']) && $_POST['noResultSampleType'] != '') {
        $sWhere[] = ' s.sample_id = "' . $_POST['noResultSampleType'] . '"';
    }
    if (isset($_POST['noResultState']) && trim((string) $_POST['noResultState']) != '') {
        $sWhere[] = " f.facility_state_id = '" . $_POST['noResultState'] . "' ";
    }
    if (isset($_POST['noResultDistrict']) && trim((string) $_POST['noResultDistrict']) != '') {
        $sWhere[] = " f.facility_district_id = '" . $_POST['noResultDistrict'] . "' ";
    }
    if (isset($_POST['noResultFacilityName']) && $_POST['noResultFacilityName'] != '') {
        $sWhere[] = ' f.facility_id IN (' . $_POST['noResultFacilityName'] . ')';
    }
    if (isset($_POST['noResultGender']) && $_POST['noResultGender'] != '') {
        $sWhere[] = ' vl.patient_gender = "' . $_POST['noResultGender'] . '"';
    }
    if (isset($_POST['noResultPatientPregnant']) && $_POST['noResultPatientPregnant'] != '') {
        $sWhere[] = ' vl.is_patient_pregnant = "' . $_POST['noResultPatientPregnant'] . '"';
    }
    if (isset($_POST['noResultPatientBreastfeeding']) && $_POST['noResultPatientBreastfeeding'] != '') {
        $sWhere[] = ' vl.is_patient_breastfeeding = "' . $_POST['noResultPatientBreastfeeding'] . '"';
    }

    if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
    }

    if (!empty($sWhere)) {
        $sWhere = ' AND ' . implode(' AND ', $sWhere);
    } else {
        $sWhere = "";
    }
    $sQuery = $sQuery . ' ' . $sWhere;
    $sQuery = $sQuery . ' group by vl.cd4_id';
    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
    }
    $_SESSION['resultNotAvailable'] = $sQuery;

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }

    // echo $sQuery;die;
    $rResult = $db->rawQuery($sQuery);
    $aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
    $iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];
    $_SESSION['resultNotAvailableCount'] = $iTotal;


    $output = array(
        "sEcho" => (int) $_POST['sEcho'],
        "iTotalRecords" => $iTotal,
        "iTotalDisplayRecords" => $iFilteredTotal,
        "aaData" => []
    );

    foreach ($rResult as $aRow) {
        if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        } else {
            $aRow['sample_collection_date'] = '';
        }
        if ($aRow['remote_sample'] == 'yes') {
            $decrypt = 'remote_sample_code';
        } else {
            $decrypt = 'sample_code';
        }
        $patientName = $general->crypto('doNothing', $aRow['patient_first_name'], $aRow[$decrypt]);

        $row = [];

        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
            $patientName = $general->crypto('decrypt', $patientName, $key);
        }
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['patient_art_no'];
        $row[] = ($patientName);
        $row[] = $aRow['sample_collection_date'];
        $row[] = ($aRow['labName']);
        $row[] = ($aRow['status_name']);
        $output['aaData'][] = $row;
    }
    echo JsonUtility::encodeUtf8Json($output);

    $db->commitTransaction();
} catch (Exception $exc) {
    LoggerUtility::log('error', $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
