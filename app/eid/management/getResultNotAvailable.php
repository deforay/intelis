<?php

use App\Utilities\SampleCountUtility;
use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\EXPIRED;
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


    /** @var FacilitiesService $facilitiesService */
    $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

    $tableName = "form_eid";
    $primaryKey = "eid_id";
    $key = (string) $general->getGlobalConfig('key');

    $aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.child_id', 'vl.child_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'fd.facility_name', 'ts.status_name', 'r_i_p.i_partner_name'];
    $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.child_id', 'vl.child_name', 'vl.sample_collection_date', 'fd.facility_name', 'ts.status_name', 'r_i_p.i_partner_name'];
    if ($general->isStandaloneInstance()) {
        $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
        $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
    }

    /* Indexed column (used for fast and accurate table cardinality) */
    $sIndexColumn = $primaryKey;

    $sTable = $tableName;

    $sOffset = $sLimit = null;
    if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
        $sOffset = (int) $_POST['iDisplayStart'];
        $sLimit = (int) $_POST['iDisplayLength'];
    }



    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

    $sWhere = [];
    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? null, $aColumns);
    if (!empty($columnSearch)) {
        $sWhere[] = $columnSearch;
    }




    $sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*,
                f.*,
                s.*,
                fd.facility_name as labName,
                ts.status_name,
                r_i_p.i_partner_name FROM form_eid as vl
                LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
                LEFT JOIN r_eid_sample_type as s ON s.sample_id=vl.specimen_type
                LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
                LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner
                INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                WHERE vl.result_status != " . REJECTED . "
                    -- A cancelled sample was called off before testing, so it is not
                    -- a sample still awaiting a result.
                    AND " . SampleCountUtility::countableWhere('vl') . "
                AND vl.sample_code is NOT NULL AND (vl.result IS NULL OR vl.result='')";
    if (isset($_POST['noResultBatchCode']) && trim((string) $_POST['noResultBatchCode']) !== '') {
        // The filter is fed by a dropdown of exact batch codes; LIKE made
        // batch "B1" also match "B12".
        $sWhere[] = ' b.batch_code = "' . $db->escape((string) $_POST['noResultBatchCode']) . '"';
    }

    [$start_date, $end_date] = DateUtility::convertDateRange($_POST['noResultSampleTestDate'] ?? '');
    if (isset($_POST['noResultSampleTestDate']) && trim((string) $_POST['noResultSampleTestDate']) !== '') {
        if (trim((string) $start_date) === trim((string) $end_date)) {
            $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
        } else {
            $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
        }
    }
    if (isset($_POST['noResultSampleType']) && $_POST['noResultSampleType'] != '') {
        $sWhere[] = ' vl.specimen_type = ' . (int) $_POST['noResultSampleType'];
    }
    if (isset($_POST['noResultState']) && trim((string) $_POST['noResultState']) !== '') {
        $sWhere[] = ' f.facility_state_id = ' . (int) $_POST['noResultState'] . ' ';
    }
    if (isset($_POST['noResultDistrict']) && trim((string) $_POST['noResultDistrict']) !== '') {
        $sWhere[] = ' f.facility_district_id = ' . (int) $_POST['noResultDistrict'] . ' ';
    }
    if (isset($_POST['noResultFacilityName']) && $_POST['noResultFacilityName'] != '') {
        $sWhere[] = ' f.facility_id IN (' . $db->inIntList($_POST['noResultFacilityName']) . ')';
    }
    if (isset($_POST['noResultGender']) && $_POST['noResultGender'] != '') {
        if (trim((string) $_POST['noResultGender']) === "unreported") {
            $sWhere[] = ' (vl.child_gender = "unreported" OR vl.child_gender ="" OR vl.child_gender IS NULL)';
        } else {
            $sWhere[] = ' (vl.child_gender IS NOT NULL AND vl.child_gender ="' . $db->escape((string) $_POST['noResultGender']) . '") ';
        }
    }
    if (isset($_POST['noResultPatientPregnant']) && $_POST['noResultPatientPregnant'] != '') {
        $sWhere[] = ' vl.is_patient_pregnant = "' . $db->escape((string) $_POST['noResultPatientPregnant']) . '"';
    }
    if (isset($_POST['noResultPatientBreastfeeding']) && $_POST['noResultPatientBreastfeeding'] != '') {
        $sWhere[] = ' vl.is_patient_breastfeeding = "' . $db->escape((string) $_POST['noResultPatientBreastfeeding']) . '"';
    }
    if (isset($_POST['noResultImplementingPartner']) && trim((string) $_POST['noResultImplementingPartner']) !== '') {
        $sWhere[] = ' vl.implementing_partner = "' . $db->escape(base64_decode((string) $_POST['noResultImplementingPartner'])) . '"';
    }
    // An expired sample will never get a result, so it is noise when this report
    // is read as a backlog; counting them stays the default for continuity.
    if (($_POST['noResultIncludeExpired'] ?? '') === 'no') {
        $sWhere[] = ' vl.result_status != ' . EXPIRED;
    }


    if (!empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    }

    if ($labScope = $general->labScopeWhere('vl')) {
        $sWhere[] = $labScope;
    }


    if ($sWhere !== []) {
        $sWhere = ' AND ' . implode(' AND ', $sWhere);
        $sQuery = $sQuery . ' ' . $sWhere;
    }

    $sQuery .= ' group by vl.eid_id';
    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
    }
    $_SESSION['resultNotAvailable'] = $sQuery;

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }

    $rResult = $db->rawQuery($sQuery);
    $aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
    $iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];
    $_SESSION['resultNotAvailableCount'] = $iTotal;


    $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

    foreach ($rResult as $aRow) {
        if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        } else {
            $aRow['sample_collection_date'] = '';
        }
        $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';
        $childName = $general->crypto('doNothing', $aRow['child_name'], $aRow[$decrypt]);

        $row = [];

        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
            $childName = $general->crypto('decrypt', $childName, $key);
        }
        $row[] = ($aRow['facility_name']);
        $row[] = $aRow['child_id'];
        $row[] = trim(($childName ?? '') . ' ' . ($aRow['child_surname'] ?? ''));
        $row[] = $aRow['sample_collection_date'];
        $row[] = ($aRow['labName']);
        $row[] = ($aRow['status_name']);
        $row[] = $aRow['i_partner_name'];
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
    throw $e;
}
