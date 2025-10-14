<?php

use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    $gconfig = $general->getGlobalConfig();
    $sarr = $general->getSystemConfig();
    $key = (string) $general->getGlobalConfig('key');

    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    $tableName = "form_cd4";
    $primaryKey = "cd4_id";


    $aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.cd4_result', 'ts.status_name');
    $orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.cd4_result', 'ts.status_name');
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



    $sOrder = "";
    if (isset($_POST['iSortCol_0'])) {
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




    $sQuery = "SELECT SQL_CALC_FOUND_ROWS * FROM form_cd4 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_cd4_sample_types as s ON s.sample_id=vl.specimen_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

    $start_date = '';
    $end_date = '';
    if (!empty($_POST['sampleCollectionDate'])) {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
    }

    if (!empty($_POST['sampleCollectionDate'])) {
        if (trim((string) $start_date) == trim((string) $end_date)) {
            $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
        } else {
            $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['formField']) && trim((string) $_POST['formField']) != '') {
        $sWhereSub = '';
        $sWhereSubC = " (";
        $searchArray = explode(",", (string) $_POST['formField']);
        foreach ($searchArray as $search) {
            if ($sWhereSub == "") {
                $sWhereSub .= $sWhereSubC;
                $sWhereSub .= "(";
            } else {
                $sWhereSub .= " AND (";
            }
            if ($search == 'sample_collection_date')
                $sWhereSub .= 'vl.' . $search . " IS NULL";
            else
                $sWhereSub .= 'vl.' . $search . " ='' OR " . 'vl.' . $search . " IS NULL";
            $sWhereSub .= ")";
        }
        $sWhereSub .= ")";
        $sWhere[] = $sWhereSub;
    }

    // $sWhere[] = ' vl.vlsm_country_id="' . $gconfig['vl_form'] . '"';

    $dWhere = '';

    if (!empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
        $dWhere = $dWhere . " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    }

    if (!empty($sWhere)) {
        $sWhere = ' WHERE ' . implode(' AND ', $sWhere);
    } else {
        $sWhere = "";
    }
    $sQuery = $sQuery . ' ' . $sWhere;
    $_SESSION['vlIncompleteForm'] = $sQuery;
    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
    }

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }

    $rResult = $db->rawQuery($sQuery);
    /* Data set length after filtering */

    $aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
    $iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];
    $_SESSION['vlIncompleteFormCount'] = $iTotal;

    /*
                                                       * Output
                                                       */
    $output = array(
        "sEcho" => (int) $_POST['sEcho'],
        "iTotalRecords" => $iTotal,
        "iTotalDisplayRecords" => $iFilteredTotal,
        "aaData" => []
    );

    foreach ($rResult as $aRow) {
        if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        } else {
            $aRow['sample_collection_date'] = '';
        }

        if ($aRow['remote_sample'] == 'yes') {
            $decrypt = 'remote_sample_code';
        } else {
            $decrypt = 'sample_code';
        }

        $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow[$decrypt]));

        $row = [];
        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
            $patientFname = $general->crypto('decrypt', $patientFname, $key);
        }
        $row[] = $aRow['sample_collection_date'];
        $row[] = $aRow['batch_code'];
        $row[] = ($patientFname);
        $row[] = ($aRow['facility_name']);
        $row[] = ($aRow['facility_state']);
        $row[] = ($aRow['facility_district']);
        $row[] = ($aRow['sample_name']);
        $row[] = ($aRow['cd4_result']);
        $row[] = ($aRow['status_name']);
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
