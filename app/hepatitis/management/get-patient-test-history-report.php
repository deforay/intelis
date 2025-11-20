<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\ACCEPTED;
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


    $tableName = "form_hepatitis";
    $primaryKey = "hepatitis_id";

    $aColumns = ['vl.patient_id', 'vl.patient_name', 'vl.patient_age', 'vl.patient_dob', 'f.facility_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 's.sample_name', 'fd.facility_name', "DATE_FORMAT(vl.sample_tested_datetime,'%d-%b-%Y')", 'vl.hcv_vl_count', 'vl.hbv_vl_count'];
    $orderColumns = ['vl.patient_id', 'vl.patient_name', 'vl.patient_age', 'vl.patient_dob', 'f.facility_name', 'vl.sample_collection_date', 's.sample_name', 'fd.facility_name', 'vl.sample_tested_datetime', 'vl.hcv_vl_count', 'vl.hbv_vl_count'];

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



    $sQuery = "SELECT
                vl.hepatitis_id,
                vl.patient_id,
                vl.is_encrypted,
                vl.patient_name,
                vl.patient_surname,
                vl.patient_age,
                vl.patient_dob,
                vl.sample_collection_date,
                vl.sample_tested_datetime,
                vl.hcv_vl_count,
                vl.hbv_vl_count,
                f.facility_name,
                s.sample_name,
                fd.facility_name as labName,
                ts.status_name
            FROM form_hepatitis as vl
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
            LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
            LEFT JOIN r_hepatitis_sample_type as s ON s.sample_id=vl.specimen_type
            INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status ";

    $sWhere[] = ' vl.hcv_vl_count != "" OR vl.hbv_vl_count != "" AND result_status = ' . ACCEPTED;

    if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
        $sWhere[] = ' vl.patient_id like "%' . $_POST['patientId'] . '%"';
    }
    if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
        $sWhere[] = " CONCAT(COALESCE(vl.patient_name,''), COALESCE(vl.patient_surname,'')) like '%" . $_POST['patientName'] . "%'";
    }

    if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    }

    if ($sWhere !== []) {
        $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
    }

    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
    }
    $_SESSION['patientTestHistoryResult'] = $sQuery;

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);


    $_SESSION['patientTestHistoryResultCount'] = $resultCount;

    /*
     * Output
     */
    $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $resultCount, "iTotalDisplayRecords" => $resultCount, "aaData" => []];

    foreach ($rResult as $aRow) {

        $aRow['patient_dob'] = DateUtility::humanReadableDateFormat($aRow['patient_dob'] ?? '');
        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        $aRow['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
        $print = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Print") . '" onclick="generateResultPDF(' . $aRow['hepatitis_id'] . ')"><em class="fa-solid fa-print"></em> ' . _translate("Print") . '</a>';
        $patientName = $aRow['patient_name'] ?? '';
        $patientSurName = $aRow['patient_surname'] ?? '';

        $row = [];
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
            $patientName = $general->crypto('decrypt', $patientName, $key);
            $patientSurName = $general->crypto('decrypt', $patientSurName, $key);
        }
        $row[] = $aRow['patient_id'];
        $row[] = ($patientName . " " . $patientSurName);
        $row[] = $aRow['patient_age'];
        $row[] = $aRow['patient_dob'];
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['sample_collection_date'];
        $row[] = $aRow['sample_name'];
        $row[] = $aRow['labName'];
        $row[] = $aRow['sample_tested_datetime'];
        $row[] = $aRow['hcv_vl_count'];
        $row[] = $aRow['hbv_vl_count'];
        $row[] = $print;
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
