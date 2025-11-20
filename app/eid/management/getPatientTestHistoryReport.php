<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\ACCEPTED;
use App\Services\EidService;
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

    /** @var EidService $eidService */
    $eidService = ContainerRegistry::get(EidService::class);
    $eidResults = $eidService->getEidResults();

    $tableName = "form_eid";
    $primaryKey = "eid_id";

    $aColumns = ['vl.child_id', 'vl.child_name', 'vl.child_age', 'vl.child_dob', 'f.facility_name', 'vl.clinician_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 's.sample_name', 'fd.facility_name', "DATE_FORMAT(vl.sample_tested_datetime,'%d-%b-%Y')", 'vl.result'];
    $orderColumns = ['vl.child_id', 'vl.child_name', 'vl.child_age', 'vl.child_dob', 'f.facility_name', 'vl.clinician_name', 'vl.sample_collection_date', 's.sample_name', 'fd.facility_name', 'vl.sample_tested_datetime', 'vl.result'];

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
                vl.eid_id,
                vl.is_encrypted,
                vl.child_id,
                vl.child_name,
                vl.child_surname,
                vl.child_age,
                vl.child_dob,
                vl.clinician_name,
                vl.sample_collection_date,
                vl.sample_tested_datetime,
                vl.result,
                f.facility_name,
                s.sample_name,
                fd.facility_name as labName,
                ts.status_name
            FROM form_eid as vl
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
            LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
            LEFT JOIN r_eid_sample_type as s ON s.sample_id=vl.specimen_type
            INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status ";

    $sWhere[] = ' vl.result is not null AND vl.result not like "" AND result_status = ' . ACCEPTED;

    if (isset($_POST['childId']) && $_POST['childId'] != "") {
        $sWhere[] = ' vl.child_id like "%' . $_POST['childId'] . '%"';
    }
    if (isset($_POST['childName']) && $_POST['childName'] != "") {
        $sWhere[] = " CONCAT(COALESCE(vl.child_name,''), COALESCE(vl.child_surname,'')) like '%" . $_POST['childName'] . "%'";
    }

    if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    }

    if ($sWhere !== []) {
        $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
    }

    //$sQuery = $sQuery . ' GROUP BY vl.eid_id';

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

        $aRow['child_dob'] = DateUtility::humanReadableDateFormat($aRow['child_dob'] ?? '');
        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        $aRow['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
        $print = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Print") . '" onclick="generateResultPDF(' . $aRow['eid_id'] . ')"><em class="fa-solid fa-print"></em> ' . _translate("Print") . '</a>';

        $row = [];

        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
            $aRow['child_name'] = $general->crypto('decrypt', $aRow['child_name'], $key);
        }

        $row[] = $aRow['child_id'];
        $row[] = $aRow['child_name'];
        $row[] = $aRow['child_age'];
        $row[] = $aRow['child_dob'];
        $row[] = ($aRow['facility_name']);
        $row[] = ($aRow['clinician_name']);
        $row[] = $aRow['sample_collection_date'];
        $row[] = $aRow['sample_name'];
        $row[] = $aRow['labName'];
        $row[] = $aRow['sample_tested_datetime'];
        $row[] = ucwords($eidResults[$aRow['result']] ?? $aRow['result']);
        $row[] = $print;
        $output['aaData'][] = $row;
    }
    echo JsonUtility::encodeUtf8Json($output);
} catch (Exception $exc) {
    LoggerUtility::logError($exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
