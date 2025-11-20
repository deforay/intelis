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

    $aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'fd.facility_name', 'rsrr.rejection_reason_name'];
    $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', 'vl.sample_collection_date', 'fd.facility_name', 'rsrr.rejection_reason_name'];

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

    $sWhere = [];

    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'], $aColumns);

    $sWhere = [];
    if (!empty($columnSearch) && $columnSearch != '') {
        $sWhere[] = $columnSearch;
    }



    $sQuery = "SELECT vl.*,
            f.*,
            s.*,
            fd.facility_name as labName,
            rsrr.rejection_reason_name,
            r_c_a.recommended_corrective_action_name
            FROM form_vl as vl
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
            LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
            LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
            LEFT JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id
            INNER JOIN r_vl_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection
            LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=vl.recommended_corrective_action ";
    $sWhere[] = " vl.is_sample_rejected='yes' AND IFNULL(reason_for_vl_testing, 0)  != 9999 ";
    if (isset($_POST['rjtBatchCode']) && trim((string) $_POST['rjtBatchCode']) !== '') {
        $sWhere[] = ' b.batch_code LIKE "%' . $_POST['rjtBatchCode'] . '%"';
    }

    if (!empty($_POST['rjtSampleTestDate'])) {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['rjtSampleTestDate'] ?? '');
        if (trim((string) $start_date) === trim((string) $end_date)) {
            $sWhere[] = " DATE(vl.sample_tested_datetime) = '$start_date' ";
        } else {
            $sWhere[] = " DATE(vl.sample_tested_datetime) BETWEEN '$start_date' AND '$end_date' ";
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
            $sWhere[] = ' (vl.patient_gender = "unreported" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
        } else {
            $sWhere[] = ' vl.patient_gender ="' . $_POST['rjtGender'] . '"';
        }
    }
    if (isset($_POST['rjtPatientPregnant']) && $_POST['rjtPatientPregnant'] != '') {
        $sWhere[] = ' vl.is_patient_pregnant = "' . $_POST['rjtPatientPregnant'] . '"';
    }
    if (isset($_POST['rjtPatientBreastfeeding']) && $_POST['rjtPatientBreastfeeding'] != '') {
        $sWhere[] = ' vl.is_patient_breastfeeding = "' . $_POST['rjtPatientBreastfeeding'] . '"';
    }
    if (isset($_POST['rejectionReason']) && $_POST['rejectionReason'] != '') {
        $sWhere[] = ' vl.reason_for_sample_rejection = "' . $_POST['rejectionReason'] . '"';
    }

    if (!empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    }


    if ($sWhere !== []) {
        $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
    }

    //$sQuery = $sQuery . ' GROUP BY vl.vl_sample_id';

    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
    }
    $_SESSION['rejectedViralLoadResult'] = $sQuery;

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);


    $_SESSION['rejectedViralLoadResultCount'] = $resultCount;

    /*
     * Output
     */
    $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $resultCount, "iTotalDisplayRecords" => $resultCount, "aaData" => []];

    foreach ($rResult as $aRow) {

        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');

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
        'last_db_query' => $db->getLastQuery()
    ]);
}
