<?php

use App\Services\Covid19Service;
use App\Services\EidService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

$tableName = "temp_sample_import";
$primaryKey = "temp_sample_id";

$importedBy = $_SESSION['userId'];
$module = $_POST['module'];

if ($module == 'vl') {
    $mainTableName = "form_vl";
    $rejectionTableName = 'r_vl_sample_rejection_reasons';
} else if ($module == 'eid') {
    $mainTableName = "form_eid";
    $rejectionTableName = 'r_eid_sample_rejection_reasons';
    $eidObj = \App\Registries\ContainerRegistry::get(EidService::class);
    $eidResults = $eidObj->getEidResults();
} else if ($module == 'covid19') {
    $mainTableName = "form_covid19";
    $rejectionTableName = 'r_covid19_sample_rejection_reasons';
    
/** @var Covid19Service $covid19Service */
$covid19Service = \App\Registries\ContainerRegistry::get(Covid19Service::class);
    $covid19Results = $covid19Service->getCovid19Results();
} else if ($module == 'hepatitis') {
    $mainTableName = "form_hepatitis";
    $rejectionTableName = 'r_hepatitis_sample_rejection_reasons';
} else if ($module == 'tb') {
    $mainTableName = "form_tb";
    $rejectionTableName = 'r_tb_sample_rejection_reasons';
}



$configQuery = "SELECT `value` FROM global_config WHERE name ='import_non_matching_sample'";
$configResult = $db->query($configQuery);
//$import_decided = (isset($configResult[0]['value']) && $configResult[0]['value'] == 'no')?'INNER JOIN':'LEFT JOIN';
$import_decided = 'LEFT JOIN';

$dtsQuery = "SELECT SQL_CALC_FOUND_ROWS tsr.temp_sample_id,
                tsr.module,tsr.sample_code,tsr.sample_details,
                    tsr.result_value_absolute,
                    tsr.result_value_log,
                    tsr.result_value_text,
                    vl.sample_collection_date,
                    tsr.sample_tested_datetime,
                    tsr.lot_number,
                    tsr.lot_expiration_date,
                    tsr.batch_code,fd.facility_name,
                    rsrr.rejection_reason_name,
                    tsr.sample_type,tsr.result,
                    tsr.result_status,ts.status_name 
                    FROM temp_sample_import as tsr $import_decided $mainTableName as vl 
                    ON vl.sample_code=tsr.sample_code 
                    LEFT JOIN facility_details as fd ON fd.facility_id=vl.facility_id 
                    LEFT JOIN $rejectionTableName as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection 
                    INNER JOIN r_sample_status as ts ON ts.status_id=tsr.result_status";

if (isset($configResult[0]['value']) && $configResult[0]['value'] == 'no') {
    //check matched samples avaiable or not
    $sampleQuery = "SELECT tsr.temp_sample_id,vl.sample_collection_date 
    FROM temp_sample_import as tsr $import_decided $mainTableName as vl 
    ON vl.sample_code=tsr.sample_code";
    $sampleResultResult = $db->rawQuery($sampleQuery);
    if (count($sampleResultResult) > 0) {
        // Do Nothing
    } else {
        $db = $db->where('sample_type', 'S');
        $delId = $db->delete($tableName);
        $import_decided = 'LEFT JOIN';
        $dtsQuery = "SELECT 
                    SQL_CALC_FOUND_ROWS tsr.temp_sample_id,tsr.sample_code,
                    tsr.sample_details,tsr.result_value_absolute,
                    tsr.result_value_log,tsr.result_value_text,
                    vl.sample_collection_date,
                    tsr.sample_tested_datetime,
                    tsr.lot_number,
                    tsr.lot_expiration_date,tsr.batch_code,
                    fd.facility_name,rsrr.rejection_reason_name,tsr.sample_type,
                    tsr.result,tsr.result_status,ts.status_name 
                    FROM temp_sample_import as tsr $import_decided 
                    $mainTableName as vl ON vl.sample_code=tsr.sample_code 
                    LEFT JOIN facility_details as fd ON fd.facility_id=vl.facility_id 
                    LEFT JOIN $rejectionTableName as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection 
                    INNER JOIN r_sample_status as ts ON ts.status_id=tsr.result_status";
    }
}

$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM $rejectionTableName WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);

//sample rejection reason
$rejectionQuery = "SELECT * FROM $rejectionTableName where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);

$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);
$scQuery = "select r_sample_control_name from r_sample_controls ORDER BY r_sample_control_name DESC";
$scResult = $db->rawQuery($scQuery);
//in-house control limit
$inQuery = "SELECT ic.number_of_in_house_controls,ic.number_of_manufacturer_controls,i.machine_name from temp_sample_import as ts INNER JOIN instruments as i ON i.machine_name=ts.vl_test_platform INNER JOIN instrument_controls as ic ON ic.config_id=i.config_id WHERE ic.test_type = '" . $module . "' limit 0,1";
$inResult = $db->rawQuery($inQuery);

$sampleTypeTotal = 0;
if (isset($_SESSION['refno']) && $_SESSION['refno'] > 0) {
    $sampleTypeTotal = $_SESSION['refno'];
}
$totalControls = 0;
if (isset($tsrResult[0]['count']) && $tsrResult[0]['count'] > 0) {
    $totalControls = $inResult[0]['number_of_manufacturer_controls'] + $inResult[0]['number_of_in_house_controls'];
}

/* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */

$aColumns = array('tsr.sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y H:i')", "DATE_FORMAT(tsr.sample_tested_datetime,'%d-%b-%Y')", 'fd.facility_name', 'rsrr.rejection_reason_name', 'tsr.sample_type', 'tsr.result', 'ts.status_name');
$orderColumns = array('tsr.sample_code', 'vl.sample_collection_date', 'tsr.sample_tested_datetime', 'fd.facility_name', 'rsrr.rejection_reason_name', 'tsr.sample_type', 'tsr.result', 'ts.status_name');

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;
/*
         * Paging
         */
$sLimit = "";
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
    for ($i = 0; $i < intval($_POST['iSortingCols']); $i++) {
        if ($_POST['bSortable_' . intval($_POST['iSortCol_' . $i])] == "true") {
            $sOrder .= $orderColumns[intval($_POST['iSortCol_' . $i])] . "
				 	" . ($_POST['sSortDir_' . $i]) . ", ";
        }
    }
    $sOrder = substr_replace($sOrder, "", -2);
}

/*
         * Filtering
         * NOTE this does not match the built-in DataTables filtering which does it
         * word by word on any field. It's possible to do here, but concerned about efficiency
         * on very large tables, and MySQL's regex functionality is very limited
        */

$sWhere = "";
$sWhereSub = "";
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
    $searchArray = explode(" ", $_POST['sSearch']);
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
    $sWhere .= $sWhereSub;
}

/* Individual column filtering */
for ($i = 0; $i < count($aColumns); $i++) {
    if (isset($_POST['bSearchable_' . $i]) && $_POST['bSearchable_' . $i] == "true" && $_POST['sSearch_' . $i] != '') {
        if ($sWhere == "") {
            $sWhere .= $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
        } else {
            $sWhere .= " AND " . $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
        }
    }
}

/*
         * SQL queries
         * Get data to display
        */
$aWhere = '';
$sQuery = "$dtsQuery";
$sOrder = 'temp_sample_id ASC';
//echo $sQuery;die;

if (isset($sWhere) && $sWhere != "") {
    $sWhere = "WHERE temp_sample_status=0 AND imported_by ='" . $importedBy . "' AND $sWhere";
} else {
    $sWhere = "WHERE temp_sample_status=0 AND imported_by ='" . $importedBy . "' ";
}
$sQuery = $sQuery . ' ' . $sWhere;
if (isset($sOrder) && $sOrder != "") {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' order by ' . $sOrder;
} else {

    $sQuery = $sQuery . ' order by ' . $sOrder;
}
//echo $sQuery;die;
if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */

$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];
/*
         * Output
        */
$output = array(
    "sEcho" => intval($_POST['sEcho']),
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => array()
);
$refno = abs($sampleTypeTotal - $totalControls);
foreach ($rResult as $aRow) {
    $row = [];
    $rsDetails = '';
    $sampleCode = "'" . $aRow['sample_code'] . "'";
    $batchCode = "'" . $aRow['batch_code'] . "'";
    $controlCode = "'" . $aRow['sample_type'] . "'";
    $color = '';
    $status = '';
    if (isset($aRow['sample_code']) && trim($aRow['sample_code']) != '') {
        $batchCodeQuery = "SELECT batch_code from batch_details as b_d INNER JOIN $mainTableName as vl ON vl.sample_batch_id = b_d.batch_id WHERE vl.sample_code = ?";
        $batchCodeResult = $db->rawQuery($batchCodeQuery, array($aRow['sample_code']));
        if (isset($batchCodeResult) && count($batchCodeResult) > 0) {
            $batchCode = "'" . $batchCodeResult[0]['batch_code'] . "'";
            $aRow['batch_code'] = $batchCodeResult[0]['batch_code'];
        }
    }
    if (isset($aRow['sample_collection_date']) && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
    } else {
        $aRow['sample_collection_date'] = '';
    }
    if (isset($aRow['sample_tested_datetime']) && trim($aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00 00:00:00') {
        $aRow['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'], true);
    } else {
        $aRow['sample_tested_datetime'] = '';
    }
    //if($aRow['sample_type']=='s' || $aRow['sample_type']=='S'){
    if ($aRow['sample_details'] == 'Result already exists') {
        $rsDetails = 'Existing Result';
        $color = '<span style="color:#86c0c8;font-weight:bold;"><em class="fa-solid fa-exclamation"></em></span>';
    }
    if ($aRow['sample_details'] == 'New Sample') {
        $rsDetails = 'Unknown Sample';
        $color = '<span style="color:#e8000b;font-weight:bold;"><em class="fa-solid fa-exclamation"></em></span>';
    }
    //if($aRow['sample_details']==''){
    else {
        $rsDetails = 'Result for Sample';
        $color = '<span style="color:#337ab7;font-weight:bold;"><em class="fa-solid fa-exclamation"></em></span>';
    }
    //}
    //$row[]='<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['temp_sample_id'] . '"  value="' . $aRow['temp_sample_id'] . '" onclick="toggleTest(this);"  />';
    $status = '<select class="form-control"  name="status[]" id="' . $aRow['temp_sample_id'] . '" title="Please select status" onchange="toggleTest(this,' . $sampleCode . ')">
			<option value="">-- Select --</option>
			<option value="7" ' . ($aRow['result_status'] == "7" ? "selected=selected" : "") . '>Accepted</option>
			<option value="1" ' . ($aRow['result_status'] == "1" ? "selected=selected" : "") . '>Hold</option>
			<option value="4" ' . ($aRow['result_status'] == "4"  ? "selected=selected" : "") . '>Rejected</option>
			</select><br><br>';
    //}
    //sample to control & control to sample
    if (!empty($scResult) && !empty($inResult) && !empty($inResult[0]) && count($scResult) > 0 && $inResult[0]['number_of_in_house_controls'] > 0 && $tsrResult[0]['count'] > 0 && $tsrResult[0]['count'] > $refno) {
        $controlName = '<select class="form-control"  name="controlName[]" id="controlName' . $aRow['temp_sample_id'] . '" title="Please select control" onchange="sampleToControl(this,' . $controlCode . ',' . $aRow['temp_sample_id'] . ')"><option value="">-- Select --</option>';
    } else {
        if ($aRow['sample_type'] == 'S' || $aRow['sample_type'] == 's') {
            $controlName = '<select class="form-control"  name="controlName[]" id="controlName' . $aRow['temp_sample_id'] . '" title="Please select control" onchange="sampleToControlAlert(' . $totalControls . ')"><option value="">-- Select --</option>';
        } else {
            $controlName = '<select class="form-control"  name="controlName[]" id="controlName' . $aRow['temp_sample_id'] . '" title="Please select control" onchange="sampleToControl(this,' . $controlCode . ',' . $aRow['temp_sample_id'] . ')"><option value="">-- Select --</option>';
        }
    }

    foreach ($scResult as $control) {
        if (trim($control['r_sample_control_name']) != '') {
            $controlName .= '<option value="' . $control['r_sample_control_name'] . '" ' . ($aRow['sample_type'] == $control['r_sample_control_name'] || $aRow['sample_type'] == ($control['r_sample_control_name']) ? "selected=selected" : "") . '>' . ($control['r_sample_control_name']) . '</option>';
        }
    }
    $controlName .= '</select><br><br>';
    $row[] = '<input style="width:90%;" type="text" name="sampleCode" id="sampleCode' . $aRow['temp_sample_id'] . '" title="' . $rsDetails . '" value="' . $aRow['sample_code'] . '" onchange="updateSampleCode(this,' . $sampleCode . ',' . $aRow['temp_sample_id'] . ');"/>&nbsp;&nbsp;' . $color;
    $row[] = $aRow['sample_collection_date'];
    $row[] = $aRow['sample_tested_datetime'];
    $row[] = $aRow['facility_name'];
    $row[] = '<input style="width:90%;" type="text" name="batchCode" id="batchCode' . $aRow['temp_sample_id'] . '" value="' . $aRow['batch_code'] . '" onchange="updateBatchCode(this,' . $batchCode . ',' . $aRow['temp_sample_id'] . ');"/>';
    $row[] = $aRow['lot_number'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['lot_expiration_date']);
    $row[] = '<span id="rejectReasonName' . $aRow['temp_sample_id'] . '"><input type="hidden" id="rejectedReasonId' . $aRow['temp_sample_id'] . '" name="rejectedReasonId[]"/>'
        . $aRow['rejection_reason_name'] .
        '</span>';
    $row[] = $controlName;
    if ($aRow['module'] == 'eid') {
        $row[] = $eidResults[$aRow['result']];
    } else if ($aRow['module'] == 'covid19') {
        $row[] = $covid19Results[$aRow['result']];
    } else {
        $row[] = $aRow['result'];
    }

    $row[] = $status;
    $output['aaData'][] = $row;
}

echo json_encode($output);
