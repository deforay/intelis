<?php

// app/hepatitis/requests/get-manifest-in-grid-helper.php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = $general ?? ContainerRegistry::get(CommonService::class);

$key = (string) $general->getGlobalConfig('key');

$manifestGrid = [
    'select' => "SELECT * FROM form_hepatitis as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id",
    'aColumns' => ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'CONCAT(COALESCE(vl.patient_name,""), COALESCE(vl.patient_surname,""))', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name'],
    'orderColumns' => ['vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'],
    // The entered code may be a package code or a remote sample code (resolved to
    // its package via the subquery).
    'manifestWhere' => fn(string $code): string => " vl.sample_package_code IN
                    (
                        '$code',
                        (SELECT DISTINCT sample_package_code FROM form_hepatitis WHERE remote_sample_code LIKE '$code')
                    )",
    'rowMapper' => function (array $aRow) use ($general, $key): array {
        $patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow['patient_id']));
        $patientLname = ($general->crypto('doNothing', $aRow['patient_surname'], $aRow['patient_id']));

        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
            $patientFname = $general->crypto('decrypt', $patientFname, $key);
            $patientLname = $general->crypto('decrypt', $patientLname, $key);
        }

        $row = [];
        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        $row[] = $aRow['batch_code'];
        $row[] = $aRow['facility_name'];
        $row[] = $aRow['patient_id'];
        $row[] = $patientFname . " " . $patientLname;
        $row[] = $aRow['facility_state'];
        $row[] = $aRow['facility_district'];
        $row[] = $aRow['hcv_vl_count'];
        $row[] = $aRow['hbv_vl_count'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);
        $row[] = $aRow['status_name'];
        return $row;
    },
];

require APPLICATION_PATH . '/specimen-referral-manifest/_get-manifest-in-grid-helper.php';
