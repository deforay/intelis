<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    $db->beginReadOnlyTransaction();

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    $key = (string) $general->getGlobalConfig('key');

    /** @var FacilitiesService $facilitiesService */
    $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

    $tableName = "form_vl";
    $primaryKey = "vl_sample_id";

    /* Array of database columns which should be read and sent back to DataTables. Use a space where
         * you want to insert a non-database field (for example a counter or static image)
        */
    $sampleCode = 'sample_code';
    $aColumns = array('vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_first_name', 'vl.patient_art_no', 'vl.patient_mobile_number', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", "DATE_FORMAT(vl.sample_tested_datetime,'%d-%b-%Y')", 'fd.facility_name', 'vl.result');
    $orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', 'vl.patient_mobile_number', 'vl.sample_collection_date', 'vl.sample_tested_datetime', 'fd.facility_name', 'vl.result');
    if ($_SESSION['instance']['type'] == 'remoteuser') {
        $sampleCode = 'remote_sample_code';
    } else if ($_SESSION['instance']['type'] == 'standalone') {
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
                $sOrder .= $orderColumns[(int) $_POST['iSortCol_' . $i]] . "
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
    $sQuery = "SELECT vl.*,f.facility_name, b.batch_code,fd.facility_name as labName
    FROM form_vl as vl
    INNER JOIN facility_details as f ON vl.facility_id=f.facility_id
    INNER JOIN facility_details as fd ON fd.facility_id=vl.lab_id
    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
    WHERE vl_result_category like 'not suppressed' AND IFNULL(reason_for_vl_testing, 0)  != 9999 AND vl.lab_id is NOT NULL ";
    $start_date = '';
    $end_date = '';

    if (isset($_POST['hvlBatchCode']) && trim((string) $_POST['hvlBatchCode']) != '') {
        $sWhere[] = ' b.batch_code LIKE "%' . $_POST['hvlBatchCode'] . '%"';
    }
    if (isset($_POST['hvlContactStatus']) && trim((string) $_POST['hvlContactStatus']) != '') {
        if ($_POST['hvlContactStatus'] != 'all') {
            $sWhere[] = ' contact_complete_status = "' . $_POST['hvlContactStatus'] . '"';
        }
    }

    if (isset($_POST['hvlSampleTestDate']) && trim((string) $_POST['hvlSampleTestDate']) != '') {
        $s_c_date = explode("to", (string) $_POST['hvlSampleTestDate']);
        //print_r($s_c_date);die;
        if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
            $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
        }
        if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
            $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
        }
        if (trim((string) $start_date) == trim((string) $end_date)) {
            $sWhere[] =  '  DATE(vl.sample_tested_datetime) = "' . $start_date . '"';
        } else {
            $sWhere[] = '  DATE(vl.sample_tested_datetime) >= "' . $start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['hvlSampleType']) && $_POST['hvlSampleType'] != '') {
        $sWhere[] =  ' vl.specimen_type = "' . $_POST['hvlSampleType'] . '"';
    }

    if (isset($_POST['state']) && trim((string) $_POST['state']) != '') {
        $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
    }
    if (isset($_POST['district']) && trim((string) $_POST['district']) != '') {
        $sWhere[] = " f.facility_district_id = '" . $_POST['district'] . "' ";
    }
    if (isset($_POST['hvlFacilityName']) && $_POST['hvlFacilityName'] != '') {
        $sWhere[] =  ' f.facility_id IN (' . $_POST['hvlFacilityName'] . ')';
    }
    if (isset($_POST['hvlGender']) && $_POST['hvlGender'] != '') {
        $sWhere[] = ' vl.patient_gender = "' . $_POST['hvlGender'] . '"';
    }
    if (isset($_POST['hvlPatientPregnant']) && $_POST['hvlPatientPregnant'] != '') {
        $sWhere[] =  ' vl.is_patient_pregnant = "' . $_POST['hvlPatientPregnant'] . '"';
    }
    if (isset($_POST['hvlPatientBreastfeeding']) && $_POST['hvlPatientBreastfeeding'] != '') {
        $sWhere[] = ' vl.is_patient_breastfeeding = "' . $_POST['hvlPatientBreastfeeding'] . '"';
    }


    if (!empty($_SESSION['facilityMap'])) {
        $sWhere[] =  " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    }

    if (!empty($sWhere)) {
        $sQuery = $sQuery . ' AND ' . implode(" AND ", $sWhere);
    }


    //$sQuery = $sQuery . ' group by vl.vl_sample_id';
    if (!empty($sOrder)) {
        $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
        $sQuery = $sQuery . ' order by ' . $sOrder;
    }
    $_SESSION['highViralResult'] = $sQuery;

    [$rResult, $resultCount] = $general->getQueryResultAndCount($sQuery, null, $sLimit, $sOffset, true);

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
        if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        } else {
            $aRow['sample_collection_date'] = '';
        }
        if (isset($aRow['sample_tested_datetime']) && trim((string) $aRow['sample_tested_datetime']) != '' && $aRow['sample_tested_datetime'] != '0000-00-00 00:00:00') {
            $aRow['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
        } else {
            $aRow['sample_tested_datetime'] = '';
        }
        if ($aRow['remote_sample'] == 'yes') {
            $decrypt = 'remote_sample_code';
        } else {
            $decrypt = 'sample_code';
        }
        $patientFname = $aRow['patient_first_name'] ?? '';
        $patientMname = $aRow['patient_middle_name'] ?? '';
        $patientLname = $aRow['patient_last_name'] ?? '';
        $row = [];
        $row[] = $aRow['sample_code'];
        if ($_SESSION['instance']['type'] != 'standalone') {
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
        $row[] = '<select class="form-control" name="status" id=' . $aRow['vl_sample_id'] . ' title="Please select status" onchange="updateStatus(this.id,this.value)">
                            <option value=""> ' . _translate("-- Select --") . ' </option>
                            <option value="yes" ' . ($aRow['contact_complete_status'] == "yes" ? "selected=selected" : "") . '>' . _translate("Yes") . '</option>
                            <option value="no" ' . ($aRow['contact_complete_status'] == "no" ? "selected=selected" : "") . '>' . _translate("No") . '</option>
                        </select>';
        $output['aaData'][] = $row;
    }
    echo MiscUtility::convertToUtf8AndEncode($output);

    $db->commitTransaction();
} catch (Exception $exc) {
    LoggerUtility::log('error', $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
