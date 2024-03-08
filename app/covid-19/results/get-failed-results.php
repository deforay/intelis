<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {

    $db->beginReadOnlyTransaction();

    $formConfigQuery = "SELECT * FROM global_config";
    $configResult = $db->query($formConfigQuery);
    $gconfig = [];
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($configResult); $i++) {
        $gconfig[$configResult[$i]['name']] = $configResult[$i]['value'];
    }
    //system config
    $systemConfigQuery = "SELECT * from system_config";
    $systemConfigResult = $db->query($systemConfigQuery);
    $sarr = [];
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
        $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
    }
    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    $tableName = "form_covid19";
    $primaryKey = "covid19_id";
    $key = (string) $general->getGlobalConfig('key');


    $sampleCode = 'sample_code';

    $aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'f.facility_name',  'vl.patient_id', 'vl.patient_name', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');
    $orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'f.facility_name', 'vl.patient_id', 'vl.patient_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');

    if ($_SESSION['instance']['type'] == 'remoteuser') {
        $sampleCode = 'remote_sample_code';
    } else if ($sarr['sc_user_type'] == 'standalone') {
        $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
        $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
    }


    /* Indexed column (used for fast and accurate table cardinality) */
    $sIndexColumn = $primaryKey;

    $sTable = $tableName;
    /*
* Paging
*/
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
                if (!empty($orderColumns[(int) $_POST['iSortCol_' . $i]]))
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



    /*
          * SQL queries
          * Get data to display
          */

    $sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*, f.*, ts.status_name, b.batch_code FROM form_covid19 as vl
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

    $start_date = '';
    $end_date = '';
    if (!empty($_POST['sampleCollectionDate'])) {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
    }

    if (!empty($_POST['sampleCollectionDate'])) {
        if (trim((string) $start_date) == trim((string) $end_date)) {
            $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
        } else {
            $sWhere[] =  ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['sampleType']) && $_POST['sampleType'] != '') {
        $sWhere[] =  ' vl.specimen_type = "' . $_POST['sampleType'] . '"';
    }
    if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
        $sWhere[] =  ' f.facility_id IN (' . $_POST['facilityName'] . ')';
    }
    if (isset($_POST['district']) && trim((string) $_POST['district']) != '') {
        $sWhere[] =  " f.facility_district_id = '" . $_POST['district'] . "' ";
    }
    if (isset($_POST['state']) && trim((string) $_POST['state']) != '') {
        $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
    }
    if (isset($_POST['vlLab']) && trim((string) $_POST['vlLab']) != '') {
        $sWhere[] =  '  vl.lab_id IN (' . $_POST['vlLab'] . ')';
    }
    if (isset($_POST['status']) && $_POST['status'] != '') {
        $sWhere[] =  ' vl.result_status = "' . $_POST['status'] . '"';
    }
    if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
        $sWhere[] = ' vl.patient_id like "%' . $_POST['patientId'] . '%"';
    }
    if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
        $sWhere[] = " CONCAT(COALESCE(vl.patient_name,''), COALESCE(vl.patient_surname,'')) like '%" . $_POST['patientName'] . "%'";
    }

    if ($_SESSION['instance']['type'] == 'remoteuser' && !empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
    }

    // $sWhere[] =  ' (vl.result_status= 1 OR LOWER(vl.result) IN ("failed", "fail", "invalid"))';
    if (!empty($sWhere)) {
        $sWhere = ' where ' . implode(' AND ', $sWhere);
        $sQuery = $sQuery . ' ' . $sWhere;
    }

    if (isset($sOrder) && !empty(trim($sOrder))) {
        $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
        $sQuery = $sQuery . " ORDER BY " . $sOrder;
    }
    $_SESSION['covid19RequestSearchResultQuery'] = $sQuery;
    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }
    //echo $sQuery;die;
    $rResult = $db->rawQuery($sQuery);
    $aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
    $iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

    $_SESSION['covid19RequestSearchResultQueryCount'] = $iTotal;

    $output = array(
        "sEcho" => (int) $_POST['sEcho'],
        "iTotalRecords" => $iTotal,
        "iTotalDisplayRecords" => $iFilteredTotal,
        "aaData" => []
    );
    $editRequest = false;
    if ((_isAllowed("/covid-19/requests/covid-19-edit-request.php"))) {
        $editRequest = true;
    }

    foreach ($rResult as $aRow) {

        if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        } else {
            $aRow['sample_collection_date'] = '';
        }
        if (isset($aRow['last_modified_datetime']) && trim((string) $aRow['last_modified_datetime']) != '' && $aRow['last_modified_datetime'] != '0000-00-00 00:00:00') {
            $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);
        } else {
            $aRow['last_modified_datetime'] = '';
        }

        $row = [];

        $row[] = '<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['covid19_id'] . '"  value="' . $aRow['covid19_id'] . '" onchange="resetBtnShowHide();" onclick="toggleTest(this);"  />';
        $row[] = $aRow['sample_code'];
        if ($_SESSION['instance']['type'] != 'standalone') {
            $row[] = $aRow['remote_sample_code'];
        }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
            $aRow['patient_name'] = $general->crypto('decrypt', $aRow['patient_name'], $key);
        }
        $row[] = $aRow['sample_collection_date'];
        $row[] = $aRow['batch_code'];
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['patient_id'];
        $row[] = $aRow['patient_name'];

        $row[] = ($aRow['facility_state']);
        $row[] = ($aRow['facility_district']);
        $row[] = ($aRow['result']);
        $row[] = $aRow['last_modified_datetime'];
        $row[] = ($aRow['status_name']);

        if ($editRequest) {
            $row[] = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Test Sample Again") . '" onclick="retestSample(\'' . trim(base64_encode((string) $aRow['covid19_id'])) . '\')"><em class="fa-solid fa-arrows-rotate"></em> ' . _translate("Retest") . '</a>';
        }
        $output['aaData'][] = $row;
    }
    echo MiscUtility::convertToUtf8AndEncode($output);

    $db->commitTransaction();
} catch (Exception $exc) {
    LoggerUtility::log('error', $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
