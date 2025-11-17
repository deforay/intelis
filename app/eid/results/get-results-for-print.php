<?php

use const COUNTRY\CAMEROON;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Services\EidService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$sarr = $general->getSystemConfig();
$key = (string) $general->getGlobalConfig('key');
$formId = (int) $general->getGlobalConfig('vl_form');


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();


try {



    $tableName = "form_eid";
    $primaryKey = "eid_id";

    $sampleCode = 'sample_code';
    $aColumns = ['vl.sample_code', 'vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.child_id', 'vl.child_name', 'vl.mother_id', 'mother_name', 'f.facility_name', 'l_f.facility_name', 'vl.lab_assigned_code', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name'];
    $orderColumns = ['vl.sample_code', 'vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.child_id', 'vl.child_name', 'vl.mother_id', 'mother_name', 'f.facility_name', 'l_f.facility_name', 'vl.lab_assigned_code', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'];
    if ($general->isSTSInstance()) {
        $sampleCode = 'remote_sample_code';
    } elseif ($general->isStandaloneInstance()) {
        $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
        $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
    }
    if ($formId != CAMEROON) {
        $aColumns = array_values(array_diff($aColumns, ['vl.lab_assigned_code']));
        $orderColumns = array_values(array_diff($orderColumns, ['vl.lab_assigned_code']));
    }

    /* Indexed column (used for fast and accurate table cardinality) */
    $sIndexColumn = $primaryKey;

    $sTable = $tableName;

    $sOffset = $sLimit = null;
    if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
        $sOffset = $_POST['iDisplayStart'];
        $sLimit = $_POST['iDisplayLength'];
    }

    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);


    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'], $aColumns);
    $sWhere = [];
    if (!empty($columnSearch) && $columnSearch != '') {
        $sWhere[] = $columnSearch;
    }


    $sQuery = "SELECT vl.*,b.batch_code,ts.*,imp.*,
            f.facility_name, f.facility_district, f.facility_state,
            l_f.facility_name as labName,
            l_f.facility_logo as facilityLogo,
            l_f.header_text as headerText,
            f.facility_code,f.facility_state,f.facility_district,
            imp.i_partner_name,
            u_d.user_name as reviewedBy,
            a_u_d.user_name as approvedBy,
            rs.rejection_reason_name,
            b.batch_code
            FROM form_eid as vl
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
            LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
            LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
            LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
            LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
            LEFT JOIN r_eid_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
            LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id=vl.implementing_partner";




    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
    }
    if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
        $sWhere[] = ' f.facility_state_id = "' . $_POST['state'] . '"';
    }

    if (isset($_POST['childId']) && $_POST['childId'] != "") {
        $sWhere[] = ' vl.child_id like "%' . $_POST['childId'] . '%"';
    }
    if (isset($_POST['childName']) && $_POST['childName'] != "") {
        $sWhere[] = " CONCAT(COALESCE(vl.child_name,''), COALESCE(vl.child_surname,'')) like '%" . $_POST['childName'] . "%'";
    }
    if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
        $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
    }

    if (!empty($_POST['sampleCollectionDate'])) {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
        $sWhere[] = " DATE(vl.sample_collection_date) BETWEEN '$start_date' AND '$end_date'";
    }
    if (!empty($_POST['sampleTestDate'])) {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleTestDate'] ?? '');
        $sWhere[] = " DATE(vl.sample_tested_datetime) BETWEEN '$start_date' AND '$end_date'";
    }


    if (isset($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) !== '') {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleReceivedDate'] ?? '');
        $sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$start_date' AND '$end_date'";
    }


    if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) !== '') {
        $sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
    }
    if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
        $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
    }
    if (isset($_POST['labId']) && trim((string) $_POST['labId']) !== '') {
        $sWhere[] = ' vl.lab_id IN (' . $_POST['labId'] . ')';
    }
    if (isset($_POST['artNo']) && trim((string) $_POST['artNo']) !== '') {
        $sWhere[] = " vl.child_id LIKE '%" . $_POST['artNo'] . "%' ";
    }
    if (isset($_POST['status']) && trim((string) $_POST['status']) !== '') {
        if ($_POST['status'] == 'no_result') {
            $statusCondition = '  (vl.result is NULL OR vl.result ="")';
        } elseif ($_POST['status'] == 'result') {
            $statusCondition = ' (vl.result is NOT NULL AND vl.result !="")';
        } else {
            $statusCondition = ' vl.result_status = ' . REJECTED;
        }
        $sWhere[] = $statusCondition;
    }
    if (isset($_POST['gender']) && trim((string) $_POST['gender']) !== '') {
        if (trim((string) $_POST['gender']) === "unreported") {
            $sWhere[] = ' (vl.patient_gender = "unreported" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
        } else {
            $sWhere[] = ' vl.patient_gender ="' . $_POST['gender'] . '"';
        }
    }
    if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) !== '') {
        $sWhere[] = ' vl.funding_source ="' . base64_decode((string) $_POST['fundingSource']) . '"';
    }
    if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) !== '') {
        $sWhere[] = ' vl.implementing_partner ="' . base64_decode((string) $_POST['implementingPartner']) . '"';
    }

    // Only approved results can be printed
    if (!isset($_POST['status']) || trim((string) $_POST['status']) === '') {
        if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'print') {
            $sWhere[] = " ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND result_printed_datetime is NOT NULL";
        } else {
            $sWhere[] = " ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND result_printed_datetime is NULL";
        }
    } else {
        $sWhere[] = " vl.result_status != " . RECEIVED_AT_CLINIC;
    }
    if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
    }
    if ($sWhere !== []) {
        $sQuery = $sQuery . ' WHERE' . implode(" AND ", $sWhere);
    }
    //echo $_SESSION['vlResultQuery'];die;
    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
        $sQuery = "$sQuery ORDER BY $sOrder";
    }
    $_SESSION['eidPrintQuery'] = $sQuery;
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

    foreach ($rResult as $aRow) {
        $row = [];
        if (isset($_POST['vlPrint'])) {
            if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'not-print') {
                $row[] = '<input type="checkbox" name="chk[]" class="checkRows" id="chk' . $aRow['eid_id'] . '"  value="' . $aRow['eid_id'] . '" onclick="checkedRow(this);"  />';
            } else {
                $row[] = '<input type="checkbox" name="chkPrinted[]" class="checkPrintedRows" id="chkPrinted' . $aRow['eid_id'] . '"  value="' . $aRow['eid_id'] . '" onclick="checkedPrintedRow(this);"  />';
            }
            $print = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Print") . '" onclick="resultPDF(' . $aRow['eid_id'] . ')"><em class="fa-solid fa-print"></em> ' . _translate("Print") . '</a>';
        }

        $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';

        // $patientFname = ($general->crypto('doNothing', $aRow['child_name'], $aRow[$decrypt]));

        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
            $aRow['child_name'] = $general->crypto('decrypt', $aRow['child_name'], $key);
            $aRow['mother_id'] = $general->crypto('decrypt', $aRow['mother_id'], $key);
            $aRow['mother_name'] = $general->crypto('decrypt', $aRow['mother_name'], $key);
        }
        $row[] = $aRow['batch_code'];
        $row[] = $aRow['child_id'];
        $row[] = $aRow['child_name'];
        $row[] = $aRow['mother_id'];
        $row[] = $aRow['mother_name'];
        // $row[] = ($patientFname);
        $row[] = $aRow['facility_name'];
        $row[] = $aRow['labName'];
        if ($formId == CAMEROON) {
            $row[] = $aRow['lab_assigned_code'];
        }

        $row[] = $aRow['facility_state'];
        $row[] = $aRow['facility_district'];
        $row[] = $eidResults[$aRow['result']] ?? $aRow['result'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');
        $row[] = $aRow['status_name'];
        $row[] = $print;
        $output['aaData'][] = $row;
    }

    echo json_encode($output);
} catch (Exception $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'code' => $e->getCode(),
        'last_db_query' => $db->getLastQuery(),
        'las_db_error' => $db->getLastError(),
        'trace' => $e->getTraceAsString()
    ]);
}
