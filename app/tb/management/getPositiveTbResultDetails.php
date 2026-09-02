<?php

use App\Utilities\SampleCountUtility;
use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
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
        $sOffset = (int) $_POST['iDisplayStart'];
        $sLimit = (int) $_POST['iDisplayLength'];
    }

    /*
     * Ordering
     */

    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

    $sWhere = [];
    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? null, $aColumns);
    if (!empty($columnSearch)) {
        $sWhere[] = $columnSearch;
    }




    $sQuery = "SELECT vl.*,f.facility_name,fd.facility_name as labName, rtbr.result as lamResult FROM form_tb as vl
        LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
        LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
        LEFT JOIN r_tb_results as rtbr ON rtbr.result_id = vl.result
        LEFT JOIN r_tb_sample_type as s ON s.sample_id=vl.specimen_type
        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

    $start_date = '';
    $end_date = '';

    if (isset($_POST['positiveTbBatchCode']) && trim((string) $_POST['positiveTbBatchCode']) !== '') {
        // The filter is fed by a dropdown of exact batch codes; LIKE made
        // batch "B1" also match "B12".
        $sWhere[] = ' b.batch_code = "' . $db->escape((string) $_POST['positiveTbBatchCode']) . '"';
    }

    if (isset($_POST['positiveTbSampleTestDate']) && trim((string) $_POST['positiveTbSampleTestDate']) !== '') {
        $s_c_date = explode("to", (string) $_POST['positiveTbSampleTestDate']);

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
    if (isset($_POST['positiveTbSampleType']) && $_POST['positiveTbSampleType'] != '') {
        $sWhere[] = ' vl.specimen_type = ' . (int) $_POST['positiveTbSampleType'];
    }
    if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
        $sWhere[] = ' f.facility_state_id = ' . (int) $_POST['state'] . ' ';
    }
    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $sWhere[] = ' f.facility_district_id = ' . (int) $_POST['district'] . ' ';
    }
    if (isset($_POST['positiveTbFacilityName']) && $_POST['positiveTbFacilityName'] != '') {
        $sWhere[] = ' f.facility_id IN (' . $db->inIntList($_POST['positiveTbFacilityName']) . ')';
    }
    if (isset($_POST['positiveTbGender']) && $_POST['positiveTbGender'] != '') {
        if (trim((string) $_POST['positiveTbGender']) === "unreported") {
            // Parenthesised: joined into an AND chain, a bare OR let every
            // NULL-sex record leak past all the other filters.
            $sWhere[] = ' (vl.patient_gender="unreported" OR vl.patient_gender="" OR vl.patient_gender IS NULL)';
        } else {
            $sWhere[] = ' vl.patient_gender = "' . $db->escape((string) $_POST['positiveTbGender']) . '"';
        }
    }
    if (isset($_POST['positiveTbPatientPregnant']) && $_POST['positiveTbPatientPregnant'] != '') {
        $sWhere[] = ' vl.is_patient_pregnant = "' . $db->escape((string) $_POST['positiveTbPatientPregnant']) . '"';
    }
    if (isset($_POST['positiveTbPatientBreastfeeding']) && $_POST['positiveTbPatientBreastfeeding'] != '') {
        $sWhere[] = ' vl.is_patient_breastfeeding = "' . $db->escape((string) $_POST['positiveTbPatientBreastfeeding']) . '"';
    }

    if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
    }

    if ($labScope = $general->labScopeWhere('vl')) {
        $sWhere[] = $labScope;
    }

    // WHERE, not AND. The query above ends in a LEFT JOIN and has no WHERE of its own,
    // so an ' AND' prefix attached every filter to that join's ON condition instead.
    // That is valid SQL, which is why nothing complained, and it filters nothing: a
    // LEFT JOIN whose ON fails keeps the row and nulls the joined columns. Every
    // filter on this report was silently ignored, the facility map and the lab scope
    // included, and the Excel export re-runs this same query out of the session.
    //
    // A cancelled sample was called off before testing, so it is not a result to
    // report either.
    $sWhere[] = SampleCountUtility::countableWhere('vl');
    $sWhere = $sWhere === [] ? "" : ' WHERE ' . implode(" AND ", $sWhere);
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

    $_SESSION['highTbResultCount'] = $resultCount;


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
    throw $e;
}
