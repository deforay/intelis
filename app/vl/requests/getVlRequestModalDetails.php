<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "form_vl";
$primaryKey = "vl_sample_id";



$aColumns = ['vl.vl_sample_id', 'vl.sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_code', 's.sample_name'];
$orderColumns = ['vl.vl_sample_id', 'vl.sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_code', 's.sample_name'];

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



$sWhere = "";
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
    $searchArray = explode(" ", (string) $_POST['sSearch']);
    $sWhereSub = "";
    foreach ($searchArray as $search) {
        if ($sWhereSub === "") {
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



$aWhere = '';
//$sQuery="SELECT vl.vl_sample_id,vl.facility_id,vl.patient_name,f.facility_name,f.facility_code,art.art_code,s.sample_name FROM form_vl as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id  INNER JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id INNER JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type";
$sQuery = "SELECT * FROM form_vl as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id INNER JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";



[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

if ($sWhere !== '' && $sWhere !== '0') {
    $sWhere = ' WHERE ' . $sWhere;
    //$sQuery = $sQuery.' '.$sWhere;
    if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
        $sWhere = $sWhere . ' AND b.batch_code LIKE "%' . $_POST['batchCode'] . '%"';
    }
    if (!empty($_POST['sampleCollectionDate'])) {
        if (trim((string) $start_date) === trim((string) $end_date)) {
            $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) = "' . $start_date . '"';
        } else {
            $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['sampleType']) && $_POST['sampleType'] != '') {
        $sWhere = $sWhere . ' AND s.sample_id = "' . $_POST['sampleType'] . '"';
    }
    if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
        $sWhere = $sWhere . ' AND f.facility_id = "' . $_POST['facilityName'] . '"';
    }
} else {
    if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
        $setWhr = 'where';
        $sWhere = ' WHERE ' . $sWhere;
        $sWhere = $sWhere . ' b.batch_code = "' . $_POST['batchCode'] . '"';
    }
    if (!empty($_POST['sampleCollectionDate'])) {
        if (isset($setWhr)) {
            if (trim((string) $start_date) === trim((string) $end_date)) {
                if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
                    $sWhere = $sWhere . ' AND DATE(vl.sample_collection_date) = "' . $start_date . '"';
                } else {
                    $sWhere = ' WHERE ' . $sWhere;
                    $sWhere = $sWhere . ' DATE(vl.sample_collection_date) = "' . $start_date . '"';
                }
            }
        } else {
            $setWhr = 'where';
            $sWhere = ' WHERE ' . $sWhere;
            $sWhere = $sWhere . ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) !== '') {
        if (isset($setWhr)) {
            $sWhere = $sWhere . ' AND s.sample_id = "' . $_POST['sampleType'] . '"';
        } else {
            $setWhr = 'where';
            $sWhere = ' WHERE ' . $sWhere;
            $sWhere = $sWhere . ' s.sample_id = "' . $_POST['sampleType'] . '"';
        }
    }
    if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
        if (isset($setWhr)) {
            $sWhere = $sWhere . ' AND f.facility_id = "' . $_POST['facilityName'] . '"';
        } else {
            $sWhere = ' WHERE ' . $sWhere;
            $sWhere = $sWhere . ' f.facility_id = "' . $_POST['facilityName'] . '"';
        }
    }
}
$sQuery = $sQuery . ' ' . $sWhere;


if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
//die($sQuery);
// echo $sQuery;
$rResult = $db->rawQuery($sQuery);
// print_r($rResult);
/* Data set length after filtering */

$aResultFilterTotal = $db->rawQuery("SELECT vl.vl_sample_id,vl.facility_id,vl.patient_first_name,vl.result,f.facility_name,f.facility_code,vl.patient_art_no,s.sample_name,b.batch_code,vl.sample_batch_id,ts.status_name FROM form_vl as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id  INNER JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id $sWhere order by $sOrder");
$iFilteredTotal = count($aResultFilterTotal);

/* Total data set length */
$aResultTotal = $db->rawQuery("select COUNT(vl_sample_id) as total FROM form_vl");
// $aResultTotal = $countResult->fetch_row();
//print_r($aResultTotal);
$iTotal = $aResultTotal[0]['total'];


$output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];


foreach ($rResult as $aRow) {
    if (isset($aRow['patient_dob']) && trim((string) $aRow['patient_dob']) !== '' && $aRow['patient_dob'] != '0000-00-00') {
        $aRow['patient_dob'] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
    } else {
        $aRow['patient_dob'] = '';
    }
    $patientDetails = $aRow['patient_art_no'] . "##" . $aRow['sample_code'] . "##" . $aRow['patient_other_id'] . "##" . $aRow['patient_first_name'] . "##" . $aRow['patient_dob'] . "##" . $aRow['patient_gender'] . "##" . $aRow['patient_age_in_years'] . "##" . $aRow['patient_mobile_number'] . "##" . $aRow['patient_location'];
    if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
    } else {
        $aRow['sample_collection_date'] = '';
    }
    $row = [];
    $row[] = '<input type="radio" id="vlTestRequest' . $aRow['vl_sample_id'] . '" name="vlTestRequest"  value="' . $patientDetails . '" onclick="getPatient(this.value);"  />';
    $row[] = $aRow['sample_code'];
    $row[] = $aRow['sample_collection_date'];
    $row[] = $aRow['batch_code'];
    $row[] = $aRow['patient_art_no'];
    $row[] = ($aRow['patient_first_name']);
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['sample_name']);
    $output['aaData'][] = $row;
}

echo json_encode($output);
