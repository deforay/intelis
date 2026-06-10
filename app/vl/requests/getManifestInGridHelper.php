<?php

// app/vl/requests/getManifestInGridHelper.php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = $general ?? ContainerRegistry::get(CommonService::class);

$manifestGrid = [
    'select' => "SELECT vl.sample_collection_date,
                    vl.vl_sample_id,
                    vl.last_modified_datetime,
                    vl.sample_code,
                    vl.remote_sample_code,
                    vl.result,
                    b.batch_code,
                    vl.patient_first_name,
                    vl.patient_middle_name,
                    vl.patient_last_name,
                    vl.patient_art_no,
                    vl.facility_id,
                    vl.specimen_type,
                    vl.result_status,
                    f.facility_name,
                    f.facility_state,
                    f.facility_district,
                    s.sample_name,
                    ts.status_name FROM form_vl as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
                    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id",
    'aColumns' => ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name'],
    'orderColumns' => ['vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'],
    'manifestWhere' => fn(string $code): string => " vl.sample_package_code = '$code'",
    'rowMapper' => function (array $aRow) use ($general): array {
        $patientFname = $aRow['patient_first_name'];
        $patientMname = $aRow['patient_middle_name'];
        $patientLname = $aRow['patient_last_name'];

        $row = [];
        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '', true);
        $row[] = $aRow['batch_code'];
        $row[] = $aRow['patient_art_no'];
        $row[] = "$patientFname $patientMname $patientLname";
        $row[] = $aRow['facility_name'];
        $row[] = $aRow['facility_state'];
        $row[] = $aRow['facility_district'];
        $row[] = $aRow['sample_name'];
        $row[] = $aRow['result'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '', true);
        $row[] = $aRow['status_name'];
        return $row;
    },
];

require APPLICATION_PATH . '/specimen-referral-manifest/_get-manifest-in-grid-helper.php';
