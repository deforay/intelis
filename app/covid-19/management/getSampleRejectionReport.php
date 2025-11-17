<?php

use Laminas\Diactoros\ServerRequest;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
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
    $key = (string) $general->getGlobalConfig('key');

    $tableName = "form_covid19";
    $primaryKey = "covid19_id";

    $aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_id', 'vl.patient_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'fd.facility_name', 'rsrr.rejection_reason_name', 'Recommended Corrective Action'];
    $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_id', 'vl.patient_name', 'vl.sample_collection_date', 'fd.facility_name', 'rsrr.rejection_reason_name', 'Recommended Corrective Action'];

    if ($general->isStandaloneInstance()) {
        $aColumns = MiscUtility::removeMatchingElements($aColumns, ['vl.remote_sample_code']);
        $orderColumns = MiscUtility::removeMatchingElements($orderColumns, ['vl.remote_sample_code']);
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


    $sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*,f.*,s.*,fd.facility_name as labName,rsrr.rejection_reason_name,r_c_a.recommended_corrective_action_name FROM form_covid19 as vl
        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
        LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
        LEFT JOIN r_covid19_sample_type as s ON s.sample_id=vl.specimen_type
        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
        JOIN r_covid19_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection
        LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=vl.recommended_corrective_action ";

    $sWhere[] = " vl.is_sample_rejected='yes' ";
    if (isset($_POST['rjtBatchCode']) && trim((string) $_POST['rjtBatchCode']) !== '') {
        $sWhere[] = ' b.batch_code LIKE "%' . $_POST['rjtBatchCode'] . '%"';
    }

    if (isset($_POST['rjtSampleTestDate']) && trim((string) $_POST['rjtSampleTestDate']) !== '') {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['rjtSampleTestDate'] ?? '');

        if (trim((string) $start_date) === trim((string) $end_date)) {
            $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $start_date . '"';
        } else {
            $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['rjtSampleType']) && $_POST['rjtSampleType'] != '') {
        $sWhere[] = ' s.sample_id = "' . $_POST['rjtSampleType'] . '"';
    }
    if (isset($_POST['rjtState']) && trim((string) $_POST['rjtState']) !== '') {
        $sWhere[] = " f.facility_state_id = '" . $_POST['rjtState'] . "' ";
    }
    if (isset($_POST['rjtDistrict']) && trim((string) $_POST['rjtDistrict']) !== '') {
        $sWhere[] = " f.facility_district_id = '" . $_POST['rjtDistrict'] . "' ";
    }
    if (isset($_POST['rjtFacilityName']) && $_POST['rjtFacilityName'] != '') {
        $sWhere[] = ' f.facility_id IN (' . $_POST['rjtFacilityName'] . ')';
    }
    if (isset($_POST['rjtGender']) && $_POST['rjtGender'] != '') {
        if (trim((string) $_POST['rjtGender']) === "unreported") {
            $sWhere[] =  ' (vl.patient_gender = "unreported" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
        } else {
            $sWhere[] =  ' (vl.patient_gender IS NOT NULL AND vl.patient_gender ="' . $_POST['rjtGender'] . '") ';
        }
    }
    if (isset($_POST['rjtPatientPregnant']) && $_POST['rjtPatientPregnant'] != '') {
        $sWhere[] = ' vl.is_patient_pregnant = "' . $_POST['rjtPatientPregnant'] . '"';
    }
    if (isset($_POST['rjtPatientBreastfeeding']) && $_POST['rjtPatientBreastfeeding'] != '') {
        $sWhere[] = ' vl.is_patient_breastfeeding = "' . $_POST['rjtPatientBreastfeeding'] . '"';
    }
    if (isset($_POST['sampleRejectionReason']) && $_POST['sampleRejectionReason'] != '') {
        $sWhere[] = ' vl.reason_for_sample_rejection = "' . $_POST['sampleRejectionReason'] . '"';
    }
    if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
    }
    $sWhere = $sWhere === [] ? [] : ' AND ' . implode(' AND ', $sWhere);
    //echo $sWhere;
    $sQuery = $sQuery . ' ' . $sWhere;

    $sQuery .= ' GROUP BY vl.covid19_id';
    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
    }
    $_SESSION['rejectedViralLoadResult'] = $sQuery;
    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }


    $rResult = $db->rawQuery($sQuery);

    $aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
    $iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];
    $_SESSION['rejectedViralLoadResultCount'] = $iTotal;

    /*
         * Output
        */
    $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

    foreach ($rResult as $aRow) {
        if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        } else {
            $aRow['sample_collection_date'] = '';
        }
        $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';
        /*$patientFname = $general->crypto('doNothing', $aRow['patient_name'], $aRow[$decrypt]);
    $patientMname = $general->crypto('doNothing', $aRow['patient_middle_name'], $aRow[$decrypt]);
    $patientLname = $general->crypto('doNothing', $aRow['patient_surname'], $aRow[$decrypt]);*/
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
            $aRow['patient_name'] = $general->crypto('decrypt', $aRow['patient_name'], $key);
        }
        $row = [];
        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['patient_id'];
        $row[] = $aRow['patient_name'];
        $row[] = $aRow['sample_collection_date'];
        $row[] = $aRow['labName'];
        $row[] = $aRow['rejection_reason_name'];
        $row[] = $aRow['recommended_corrective_action_name'];
        $output['aaData'][] = $row;
    }
    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
    ]);
}
