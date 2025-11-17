<?php

use Laminas\Diactoros\ServerRequest;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
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
    $tableName = "form_tb";
    $primaryKey = "tb_id";
    $key = (string) $general->getGlobalConfig('key');
    $sampleCode = 'sample_code';
    $aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_name', 'vl.patient_id', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", "DATE_FORMAT(vl.sample_tested_datetime,'%d-%b-%Y')", 'fd.facility_name', 'vl.result'];
    $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_id', 'vl.patient_name', 'vl.sample_collection_date', 'vl.sample_tested_datetime', 'fd.facility_name', 'vl.result'];
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

    /*
     * Ordering
     */

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




    $sQuery = "SELECT vl.*,f.facility_name,fd.facility_name as labName, rtbr.result as lamResult FROM form_tb as vl
        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
        LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
        LEFT JOIN r_tb_results as rtbr ON rtbr.result_id = vl.result
        LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

    $start_date = '';
    $end_date = '';

    if (isset($_POST['hvlBatchCode']) && trim((string) $_POST['hvlBatchCode']) !== '') {
        $sWhere[] = ' b.batch_code LIKE "%' . $_POST['hvlBatchCode'] . '%"';
    }
    /* if(isset($_POST['hvlContactStatus']) && trim($_POST['hvlContactStatus'])!= ''){
        if($_POST['hvlContactStatus']=='all')
        {
            $sWhere = $sWhere.' AND (contact_complete_status = "no" OR contact_complete_status="yes" OR contact_complete_status IS NULL OR contact_complete_status="")';
        }else{
        $sWhere = $sWhere.' AND contact_complete_status = "'.$_POST['hvlContactStatus'].'"';
        }
    } */

    if (isset($_POST['hvlSampleTestDate']) && trim((string) $_POST['hvlSampleTestDate']) !== '') {
        $s_c_date = explode("to", (string) $_POST['hvlSampleTestDate']);

        if (isset($s_c_date[0]) && trim($s_c_date[0]) !== "") {
            $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
        }
        if (isset($s_c_date[1]) && trim($s_c_date[1]) !== "") {
            $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
        }
        if (trim((string) $start_date) === trim((string) $end_date)) {
            $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $start_date . '"';
        } else {
            $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['hvlSampleType']) && $_POST['hvlSampleType'] != '') {
        $sWhere[] = ' s.sample_id = "' . $_POST['hvlSampleType'] . '"';
    }
    if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
        $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
    }
    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $sWhere[] = " f.facility_district_id = '" . $_POST['district'] . "' ";
    }
    if (isset($_POST['hvlFacilityName']) && $_POST['hvlFacilityName'] != '') {
        $sWhere[] = ' f.facility_id IN (' . $_POST['hvlFacilityName'] . ')';
    }
    if (isset($_POST['hvlGender']) && $_POST['hvlGender'] != '') {
        if (trim((string) $_POST['hvlGender']) === "unreported") {
            $sWhere[] = ' vl.patient_gender="unreported" OR vl.patient_gender="" OR vl.patient_gender IS NULL';
        } else {
            $sWhere[] = ' vl.patient_gender IN ("' . $_POST['hvlGender'] . '")';
        }
    }
    if (isset($_POST['hvlPatientPregnant']) && $_POST['hvlPatientPregnant'] != '') {
        $sWhere[] = ' vl.is_patient_pregnant = "' . $_POST['hvlPatientPregnant'] . '"';
    }
    if (isset($_POST['hvlPatientBreastfeeding']) && $_POST['hvlPatientBreastfeeding'] != '') {
        $sWhere[] = ' vl.is_patient_breastfeeding = "' . $_POST['hvlPatientBreastfeeding'] . '"';
    }

    if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
    }

    $sWhere = $sWhere === [] ? "" : ' AND' . implode(" AND ", $sWhere);
    $sQuery .= $sWhere;
    $sQuery .= ' group by vl.tb_id';
    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
    }
    $_SESSION['highTbResult'] = $sQuery;

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

    $_SESSION['highViralResultCount'] = $resultCount;


    /*
     * Output
     */
    $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $resultCount, "iTotalDisplayRecords" => $resultCount, "aaData" => []];

    foreach ($rResult as $aRow) {
        if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        } else {
            $aRow['sample_collection_date'] = '';
        }
        if (isset($aRow['sample_tested_datetime']) && trim((string) $aRow['sample_tested_datetime']) !== '' && $aRow['sample_tested_datetime'] != '0000-00-00 00:00:00') {
            $aRow['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
        } else {
            $aRow['sample_tested_datetime'] = '';
        }
        $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';
        $patientFname = $general->crypto('doNothing', $aRow['patient_name'], $aRow[$decrypt]);
        $patientMname = $general->crypto('doNothing', $aRow['patient_surname'], $aRow[$decrypt]);
        // $patientLname = $general->crypto('doNothing', $aRow['patient_last_name'], $aRow[$decrypt]);
        $row = [];
        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
            $patientFname = $general->crypto('decrypt', $patientFname, $key);
            $patientMname = $general->crypto('decrypt', $patientMname, $key);
        }
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['patient_id'];
        $row[] = ($patientFname . " " . $patientMname);
        $row[] = $aRow['sample_collection_date'];
        $row[] = $aRow['sample_tested_datetime'];
        $row[] = $aRow['labName'];
        $row[] = $aRow['lamResult'];
        $row[] = '';
        /* $row[] = '<select class="form-control" name="status" id=' . $aRow['tb_id'] . ' title="Please select status" onchange="updateStatus(this.id,this.value)">
                            <option value=""> -- Select -- </option>
                            <option value="yes" ' . ($aRow['contact_complete_status'] == "yes" ? "selected=selected" : "") . '>Yes</option>
                            <option value="no" ' . ($aRow['contact_complete_status'] == "no" ? "selected=selected" : "") . '>No</option>
                        </select>'; */
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
