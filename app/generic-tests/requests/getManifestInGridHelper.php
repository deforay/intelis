<?php

// app/generic-tests/requests/getManifestInGridHelper.php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = $general ?? ContainerRegistry::get(CommonService::class);

$manifestGrid = [
    'select' => "SELECT *, ts.status_name FROM form_generic as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN r_generic_sample_types as s ON s.sample_type_id=vl.specimen_type
                    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    LEFT JOIN r_generic_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
                    LEFT JOIN r_generic_test_reasons as tr ON tr.test_reason_id=vl.reason_for_testing
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id",
    'aColumns' => ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name'],
    'orderColumns' => ['vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'],
    // The entered code may be a package code, a referral code, or a remote sample
    // code (resolved to its package via the subquery).
    'manifestWhere' => fn(string $code): string => " (vl.sample_package_code IN
                    (
                        '$code',
                        (SELECT DISTINCT sample_package_code FROM form_generic WHERE remote_sample_code LIKE '$code')
                    ) OR vl.referral_manifest_code = '$code')",
    'referrable' => true,
    'rowMapper' => function (array $aRow) use ($general): array {
        $patientFname = ($general->crypto('doNothing', $aRow['patient_first_name'], $aRow['patient_id']));
        $patientMname = ($general->crypto('doNothing', $aRow['patient_middle_name'], $aRow['patient_id']));
        $patientLname = ($general->crypto('doNothing', $aRow['patient_last_name'], $aRow['patient_id']));

        $row = [];
        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        $row[] = $aRow['batch_code'];
        $row[] = $aRow['patient_id'];
        $row[] = ($patientFname . " " . $patientMname . " " . $patientLname);
        $row[] = ($aRow['facility_name']);
        $row[] = ($aRow['facility_state']);
        $row[] = ($aRow['facility_district']);
        $row[] = ($aRow['sample_name']);
        $row[] = $aRow['result'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');
        $row[] = ($aRow['status_name']);
        return $row;
    },
];

require APPLICATION_PATH . '/specimen-referral-manifest/_get-manifest-in-grid-helper.php';
