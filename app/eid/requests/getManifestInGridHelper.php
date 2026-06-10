<?php

// app/eid/requests/getManifestInGridHelper.php

use App\Services\EidService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = $general ?? ContainerRegistry::get(CommonService::class);

$eidResults = ContainerRegistry::get(EidService::class)->getEidResults();

$manifestGrid = [
    'select' => "SELECT vl.sample_collection_date,
                    vl.eid_id,
                    vl.last_modified_datetime,
                    vl.sample_code,
                    vl.remote_sample_code,
                    vl.result,
                    b.batch_code,
                    vl.child_id,
                    vl.child_name,
                    vl.mother_id,
                    vl.mother_name,
                    vl.facility_id,
                    vl.result_status,
                    f.facility_name,
                    f.facility_state,
                    f.facility_district,
                    ts.status_name
                    FROM form_eid as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id",
    'aColumns' => ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.child_id', 'vl.child_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name'],
    'orderColumns' => ['vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.child_id', 'vl.child_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'],
    'manifestWhere' => fn(string $code): string => " vl.sample_package_code = '$code'",
    'rowMapper' => function (array $aRow) use ($general, $eidResults): array {
        $row = [];
        $row[] = $aRow['sample_code'];
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        $row[] = $aRow['batch_code'];
        $row[] = $aRow['facility_name'];
        $row[] = $aRow['child_id'];
        $row[] = trim(($aRow['child_name'] ?? '') . ' ' . ($aRow['child_surname'] ?? ''));
        $row[] = $aRow['mother_id'];
        $row[] = $aRow['mother_name'];
        $row[] = $aRow['facility_state'];
        $row[] = $aRow['facility_district'];
        $row[] = $eidResults[$aRow['result']] ?? $aRow['result'] ?? '';
        $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');
        $row[] = $aRow['status_name'];
        return $row;
    },
];

require APPLICATION_PATH . '/specimen-referral-manifest/_get-manifest-in-grid-helper.php';
