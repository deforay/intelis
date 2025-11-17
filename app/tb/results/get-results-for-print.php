<?php

use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Services\TbService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$key = (string) $general->getGlobalConfig('key');

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);
$tbResults = $tbService->getTbResults();

$tableName = "form_tb";
$primaryKey = "tb_id";

$sampleCode = 'sample_code';
$aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'CONCAT(COALESCE(vl.patient_name,""), COALESCE(vl.patient_surname,""))', 'f.facility_name', 'l_f.facility_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name'];
$orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'l_f.facility_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'];
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
            $sWhereSub .= " (";
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




$sQuery = "SELECT vl.*,b.batch_code,ts.*,imp.*,
            f.facility_name,f.facility_district,f.facility_state,
            l_f.facility_name as labName,
            l_f.facility_logo as facilityLogo,
            l_f.header_text as headerText,
            f.facility_code,f.facility_state,f.facility_district,
            imp.i_partner_name,
            u_d.user_name as reviewedBy,
            a_u_d.user_name as approvedBy,
            rs.rejection_reason_name,
            r_c_a.recommended_corrective_action_name
            FROM form_tb as vl
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
            LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
            INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
            LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
            LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
            LEFT JOIN r_tb_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
            LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=vl.recommended_corrective_action
            LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id=vl.implementing_partner";
[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
[$t_start_date, $t_end_date] = DateUtility::convertDateRange($_POST['sampleTestDate'] ?? '');
[$r_start_date, $r_end_date] = DateUtility::convertDateRange($_POST['sampleReceivedDate'] ?? '');

if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
    $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}
if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
    $sWhere[] = ' f.facility_state_id = "' . $_POST['state'] . '"';
}

if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
    $sWhere[] = ' vl.patient_id like "%' . $_POST['patientId'] . '%"';
}
if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
    $sWhere[] = " CONCAT(COALESCE(vl.patient_name,''), COALESCE(vl.patient_surname,'')) like '%" . $_POST['patientName'] . "%'";
}


if (!empty($_POST['sampleCollectionDate'])) {
    if (trim((string) $start_date) === trim((string) $end_date)) {
        $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
    } else {
        $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
    }
}

if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
    if (trim((string) $t_start_date) === trim((string) $t_end_date)) {
        $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $t_start_date . '"';
    } else {
        $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $t_start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $t_end_date . '"';
    }
}

if (isset($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) !== '') {
    if (trim((string) $r_start_date) === trim((string) $r_end_date)) {
        $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) = "' . $r_start_date . '"';
    } else {
        $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) >= "' . $r_start_date . '" AND DATE(vl.sample_received_at_lab_datetime) <= "' . $r_end_date . '"';
    }
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
if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}


// Only approved results can be printed
if (!isset($_POST['status']) || trim((string) $_POST['status']) === '') {
    if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'not-print') {
        $sWhere[] = " ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND (result_printed_datetime is NULL OR DATE(result_printed_datetime) = '0000-00-00')";
    } else {
        $sWhere[] = " ((vl.result_status = 7 AND vl.result is NOT NULL AND vl.result !='') OR (vl.result_status = 4 AND (vl.result is NULL OR vl.result = ''))) AND (result_printed_datetime is not NULL)";
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
if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}
$_SESSION['tbPrintQuery'] = $sQuery;

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

[$rResult, $resultCount] = $db->getDataAndCount($sQuery);


$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $resultCount, "iTotalDisplayRecords" => $resultCount, "aaData" => []];

foreach ($rResult as $aRow) {
    $row = [];
    if (isset($_POST['vlPrint'])) {
        if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'not-print') {
            $row[] = '<input type="checkbox" name="chk[]" class="checkRows" id="chk' . $aRow['tb_id'] . '"  value="' . $aRow['tb_id'] . '" onclick="checkedRow(this);"  />';
        } else {
            $row[] = '<input type="checkbox" name="chkPrinted[]" class="checkPrintedRows" id="chkPrinted' . $aRow['tb_id'] . '"  value="' . $aRow['tb_id'] . '" onclick="checkedPrintedRow(this);"  />';
        }
        $print = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Print") . '" onclick="resultPDF(' . $aRow['tb_id'] . ')"><em class="fa-solid fa-print"></em> ' . _translate("Print") . '</a>';
    }

    $patientFname = $general->crypto('doNothing', $aRow['patient_name'], $aRow['patient_id']);
    $patientLname = $general->crypto('doNothing', $aRow['patient_surname'], $aRow['patient_id']);

    $row[] = $aRow['sample_code'];
    if (!$general->isStandaloneInstance()) {
        $row[] = $aRow['remote_sample_code'];
    }
    if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
        $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
        $patientFname = $general->crypto('decrypt', $patientFname, $key);
        $patientLname = $general->crypto('decrypt', $patientLname, $key);
    }
    $row[] = $aRow['batch_code'];
    $row[] = $aRow['patient_id'];
    $row[] = ($patientFname . " " . $patientLname);
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['labName']);
    $row[] = ($aRow['facility_state']);
    $row[] = ($aRow['facility_district']);
    $row[] = $tbResults[$aRow['result']];
    $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');
    $row[] = ($aRow['status_name']);
    $row[] = $print;
    $output['aaData'][] = $row;
}

echo json_encode($output);
