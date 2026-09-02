<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Utilities\SampleCountUtility;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    $key = (string) $general->getGlobalConfig('key');

    /** @var FacilitiesService $facilitiesService */
    $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

    $tableName = "form_vl";
    $primaryKey = "vl_sample_id";


    $sampleCode = 'sample_code';
    $aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_first_name', 'vl.patient_art_no', 'vl.patient_mobile_number', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", "DATE_FORMAT(vl.sample_tested_datetime,'%d-%b-%Y')", 'fd.facility_name', 'vl.result', 'r_i_p.i_partner_name'];
    $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', 'vl.patient_mobile_number', 'vl.sample_collection_date', 'vl.sample_tested_datetime', 'fd.facility_name', 'vl.result', 'r_i_p.i_partner_name'];
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




    $sQuery = "SELECT vl.*,f.facility_name, b.batch_code,fd.facility_name as labName, r_i_p.i_partner_name
    FROM form_vl as vl
    INNER JOIN facility_details as f ON vl.facility_id=f.facility_id
    INNER JOIN facility_details as fd ON fd.facility_id=vl.lab_id
    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
    LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner
    WHERE vl_result_category like 'not suppressed' AND IFNULL(reason_for_vl_testing, 0)  != 9999 AND vl.lab_id is NOT NULL ";
    $start_date = '';
    $end_date = '';

    if (isset($_POST['hvlBatchCode']) && trim((string) $_POST['hvlBatchCode']) !== '') {
        // The filter is fed by a dropdown of exact batch codes; LIKE made
        // batch "B1" also match "B12".
        $sWhere[] = ' b.batch_code = "' . $db->escape((string) $_POST['hvlBatchCode']) . '"';
    }
    if (isset($_POST['hvlContactStatus']) && trim((string) $_POST['hvlContactStatus']) !== '' && $_POST['hvlContactStatus'] != 'all') {
        $sWhere[] = ' contact_complete_status = "' . $db->escape((string) $_POST['hvlContactStatus']) . '"';
    }

    if (isset($_POST['hvlSampleTestDate']) && trim((string) $_POST['hvlSampleTestDate']) !== '') {
        $s_c_date = explode("to", (string) $_POST['hvlSampleTestDate']);

        if (isset($s_c_date[0]) && trim($s_c_date[0]) !== "") {
            $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
        }
        if (isset($s_c_date[1]) && trim($s_c_date[1]) !== "") {
            $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
        }
        if (trim((string) $start_date) === trim((string) $end_date)) {
            $sWhere[] = '  DATE(vl.sample_tested_datetime) = "' . $start_date . '"';
        } else {
            $sWhere[] = '  DATE(vl.sample_tested_datetime) >= "' . $start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['hvlSampleType']) && $_POST['hvlSampleType'] != '') {
        $sWhere[] = ' vl.specimen_type = ' . (int) $_POST['hvlSampleType'];
    }

    if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
        $sWhere[] = ' f.facility_state_id = ' . (int) $_POST['state'];
    }
    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $sWhere[] = ' f.facility_district_id = ' . (int) $_POST['district'];
    }
    if (isset($_POST['hvlFacilityName']) && $_POST['hvlFacilityName'] != '') {
        $sWhere[] = ' f.facility_id IN (' . $db->inIntList($_POST['hvlFacilityName']) . ')';
    }
    if (isset($_POST['hvlGender']) && $_POST['hvlGender'] != '') {
        if (trim((string) $_POST['hvlGender']) === "unreported") {
            $sWhere[] = ' (vl.patient_gender = "unreported" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
        } else {
            $sWhere[] = ' vl.patient_gender ="' . $db->escape((string) $_POST['hvlGender']) . '"';
        }
    }
    if (isset($_POST['hvlPatientPregnant']) && $_POST['hvlPatientPregnant'] != '') {
        $sWhere[] = ' vl.is_patient_pregnant = "' . $db->escape((string) $_POST['hvlPatientPregnant']) . '"';
    }
    if (isset($_POST['hvlPatientBreastfeeding']) && $_POST['hvlPatientBreastfeeding'] != '') {
        $sWhere[] = ' vl.is_patient_breastfeeding = "' . $db->escape((string) $_POST['hvlPatientBreastfeeding']) . '"';
    }
    if (isset($_POST['hvlImplementingPartner']) && trim((string) $_POST['hvlImplementingPartner']) !== '') {
        $sWhere[] = ' vl.implementing_partner = "' . $db->escape(base64_decode((string) $_POST['hvlImplementingPartner'])) . '"';
    }


    if (!empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    }

    if ($labScope = $general->labScopeWhere('vl')) {
        $sWhere[] = $labScope;
    }

    // A cancelled sample was called off before testing, so it is not work
    // this report should count. Applied unconditionally so an unfiltered view
    // excludes cancelled samples too.
    $sWhere[] = SampleCountUtility::countableWhere('vl');
    $sQuery = $sQuery . ' AND ' . implode(" AND ", $sWhere);


    //$sQuery = $sQuery . ' group by vl.vl_sample_id';
    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
    }
    $_SESSION['highViralResult'] = $sQuery;

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

    /*
     * Output
     */
    $output = [
        "sEcho" => (int) $_POST['sEcho'],
        "iTotalRecords" => $resultCount,
        "iTotalDisplayRecords" => $resultCount,
        "aaData" => []
    ];

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
        $patientFname = $aRow['patient_first_name'] ?? '';
        $patientMname = $aRow['patient_middle_name'] ?? '';
        $patientLname = $aRow['patient_last_name'] ?? '';
        $row = [];
        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
            $patientFname = $general->crypto('decrypt', $patientFname, $key);
            $patientMname = $general->crypto('decrypt', $patientMname, $key);
            $patientLname = $general->crypto('decrypt', $patientLname, $key);
        }
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['patient_art_no'];
        $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
        $row[] = $aRow['patient_mobile_number'];
        $row[] = $aRow['sample_collection_date'];
        $row[] = $aRow['sample_tested_datetime'];
        $row[] = $aRow['labName'];
        $row[] = $aRow['result'];
        $row[] = $aRow['i_partner_name'];
        $row[] = '<select class="form-control" name="status" id=' . $aRow['vl_sample_id'] . ' title="Please select status" onchange="updateStatus(this.id,this.value)">
                            <option value=""> ' . _translate("-- Select --") . ' </option>
                            <option value="yes" ' . ($aRow['contact_complete_status'] == "yes" ? "selected=selected" : "") . '>' . _translate("Yes") . '</option>
                            <option value="no" ' . ($aRow['contact_complete_status'] == "no" ? "selected=selected" : "") . '>' . _translate("No") . '</option>
                        </select>';
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
    throw $e;
}
