<?php

use App\Services\ApiService;
use App\Services\CommonService;
use App\Utilities\DateUtils;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

require_once(dirname(__FILE__) . "/../../../bootstrap.php");

ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

$systemConfig = SYSTEM_CONFIG;

if (!isset($systemConfig['remoteURL']) || $systemConfig['remoteURL'] == '') {
    error_log("Please check if Remote URL is set");
    exit(0);
}

$general = new CommonService();
$app = new ApiService();

$labId = $general->getSystemConfig('sc_testing_lab_id');
$version = VERSION;

//update common data from remote to lab db
$remoteUrl = rtrim($systemConfig['remoteURL'], "/");

$headers = @get_headers($remoteUrl . '/api/version.php?labId=' . $labId . '&version=' . $version);

if (strpos($headers[0], '200') === false) {
    error_log("No internet connectivity while trying remote sync.");
    exit();
}




$dataToSync = [];
$commonDataToSync = [];
$vlDataToSync = [];
$eidDataToSync = [];
$covid19DataToSync = [];
$hepatitisDataToSync = [];


$payload = array(
    'globalConfigLastModified'      => $general->getLastModifiedDateTime('global_config', 'updated_on'),
    'provinceLastModified'          => $general->getLastModifiedDateTime('geographical_divisions'),
    'facilityLastModified'          => $general->getLastModifiedDateTime('facility_details'),
    'healthFacilityLastModified'    => $general->getLastModifiedDateTime('health_facilities'),
    'testingLabsLastModified'       => $general->getLastModifiedDateTime('testing_labs'),
    'fundingSourcesLastModified'    => $general->getLastModifiedDateTime('r_funding_sources'),
    'partnersLastModified'          => $general->getLastModifiedDateTime('r_implementation_partners'),
    'geoDivisionsLastModified'      => $general->getLastModifiedDateTime('geographical_divisions'),
    "Key"                          => "vlsm-get-remote",
);

// This array is used to sync data that we will later receive from the API call
$commonDataToSync = array(
    'globalConfig'  => array(
        'primaryKey' => 'name',
        'tableName' => 'global_config',
    ),
    'province'  => array(
        'primaryKey' => 'geo_id',
        'tableName' => 'geographical_divisions',
    ), 
    'users'  => array(
        'primaryKey' => 'user_id',
        'tableName' => 'user_details',
    ),
    'facilities'  => array(
        'primaryKey' => 'facility_id',
        'tableName' => 'facility_details',
    ),
    'healthFacilities'  => array(
        'primaryKey' => 'facility_id',
        'tableName' => 'health_facilities',
    ),
    'testingLabs'  => array(
        'primaryKey' => 'facility_id',
        'tableName' => 'testing_labs',
    ),
    'fundingSources'  => array(
        'primaryKey' => 'funding_source_id',
        'tableName' => 'r_funding_sources',
    ),
    'partners'  => array(
        'primaryKey' => 'i_partner_id',
        'tableName' => 'r_implementation_partners',
    ),
    'geoDivisions'  => array(
        'primaryKey' => 'geo_id',
        'tableName' => 'geographical_divisions',
    )
);


$url = $remoteUrl . '/remote/remote/commonData.php';

if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] === true) {
    $payload['vlArtCodesLastModified'] = $general->getLastModifiedDateTime('r_vl_art_regimen');
    $payload['vlRejectionReasonsLastModified'] = $general->getLastModifiedDateTime('r_vl_sample_rejection_reasons');
    $payload['vlSampleTypesLastModified'] = $general->getLastModifiedDateTime('r_vl_sample_type');
    $payload['vlFailureReasonsLastModified'] = $general->getLastModifiedDateTime('r_vl_test_failure_reasons');
    $payload['vlResultsLastModified'] = $general->getLastModifiedDateTime('r_vl_results');




    // This array is used to sync data that we will later receive from the API call
    $vlDataToSync = array(
        'vlSampleTypes' => array(
            'primaryKey' => 'sample_id',
            'tableName' => 'r_vl_sample_type',
        ),
        'vlArtCodes' => array(
            'primaryKey' => 'art_id',
            'tableName' => 'r_vl_art_regimen',
        ),
        'vlRejectionReasons' => array(
            'primaryKey' => 'rejection_reason_id',
            'tableName' => 'r_vl_sample_rejection_reasons',
        ),
        'vlFailureReasons' => array(
            'primaryKey' => 'failure_id',
            'tableName' => 'r_vl_test_failure_reasons',
        ),
        'vlResults' => array(
            'primaryKey' => 'result_id',
            'tableName' => 'r_vl_results',
        )
    );
}

if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] === true) {
    $payload['eidRejectionReasonsLastModified'] = $general->getLastModifiedDateTime('r_eid_sample_rejection_reasons');
    $payload['eidSampleTypesLastModified'] = $general->getLastModifiedDateTime('r_eid_sample_type');
    $payload['eidResultsLastModified'] = $general->getLastModifiedDateTime('r_eid_results ');
    $payload['eidReasonForTestingLastModified'] = $general->getLastModifiedDateTime('r_eid_test_reasons  ');


    // This array is used to sync data that we will later receive from the API call
    $eidDataToSync = array(
        'eidRejectionReasons' => array(
            'primaryKey' => 'rejection_reason_id',
            'tableName' => 'r_eid_sample_rejection_reasons',
        ),
        'eidSampleTypes' => array(
            'primaryKey' => 'sample_id',
            'tableName' => 'r_eid_sample_type',
        ),
        'eidResults' => array(
            'primaryKey' => 'result_id',
            'tableName' => 'r_eid_results',
        ),
        'eidReasonForTesting' => array(
            'primaryKey' => 'test_reason_id',
            'tableName' => 'r_eid_test_reasons',
        )
    );
}


if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] === true) {
    $payload['covid19RejectionReasonsLastModified'] = $general->getLastModifiedDateTime('r_covid19_sample_rejection_reasons');
    $payload['covid19SampleTypesLastModified'] = $general->getLastModifiedDateTime('r_covid19_sample_type');
    $payload['covid19ComorbiditiesLastModified'] = $general->getLastModifiedDateTime('r_covid19_comorbidities');
    $payload['covid19ResultsLastModified'] = $general->getLastModifiedDateTime('r_covid19_results');
    $payload['covid19SymptomsLastModified'] = $general->getLastModifiedDateTime('r_covid19_symptoms');
    $payload['covid19ReasonForTestingLastModified'] = $general->getLastModifiedDateTime('r_covid19_test_reasons');
    $payload['covid19QCTestKitsLastModified'] = $general->getLastModifiedDateTime('r_covid19_qc_testkits');



    // This array is used to sync data that we will later receive from the API call
    $covid19DataToSync = array(
        'covid19RejectionReasons' => array(
            'primaryKey' => 'rejection_reason_id',
            'tableName' => 'r_covid19_sample_rejection_reasons',
        ),
        'covid19SampleTypes' => array(
            'primaryKey' => 'sample_id',
            'tableName' => 'r_covid19_sample_type',
        ),
        'covid19Comorbidities' => array(
            'primaryKey' => 'comorbidity_id',
            'tableName' => 'r_covid19_comorbidities',
        ),
        'covid19Results' => array(
            'primaryKey' => 'result_id',
            'tableName' => 'r_covid19_results',
        ),
        'covid19Symptoms' => array(
            'primaryKey' => 'symptom_id',
            'tableName' => 'r_covid19_symptoms',
        ),
        'covid19ReasonForTesting' => array(
            'primaryKey' => 'test_reason_id',
            'tableName' => 'r_covid19_test_reasons',
        ),
        'covid19QCTestKits' => array(
            'primaryKey' => 'testkit_id',
            'tableName' => 'r_covid19_qc_testkits',
        )
    );
}

if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] === true) {
    $payload['hepatitisRejectionReasonsLastModified'] = $general->getLastModifiedDateTime('r_hepatitis_sample_rejection_reasons');
    $payload['hepatitisSampleTypesLastModified'] = $general->getLastModifiedDateTime('r_hepatitis_sample_type');
    $payload['hepatitisComorbiditiesLastModified'] = $general->getLastModifiedDateTime('r_hepatitis_comorbidities');
    $payload['hepatitisResultsLastModified'] = $general->getLastModifiedDateTime('r_hepatitis_results');
    $payload['hepatitisReasonForTestingLastModified'] = $general->getLastModifiedDateTime('r_hepatitis_test_reasons');

    // This array is used to sync data that we will later receive from the API call
    $hepatitisDataToSync = array(
        'hepatitisReasonForTesting' => array(
            'primaryKey' => 'test_reason_id',
            'tableName' => 'r_hepatitis_test_reasons',
        ),
        'hepatitisResults' => array(
            'primaryKey' => 'result_id',
            'tableName' => 'r_hepatitis_results',
        ),
        'hepatitisComorbidities' => array(
            'primaryKey' => 'comorbidity_id',
            'tableName' => 'r_hepatitis_comorbidities',
        ),
        'hepatitisSampleTypes' => array(
            'primaryKey' => 'sample_id',
            'tableName' => 'r_hepatitis_sample_type',
        ),
        'hepatitisRejectionReasons' => array(
            'primaryKey' => 'rejection_reason_id',
            'tableName' => 'r_hepatitis_sample_rejection_reasons',
        )
    );
}


$dataToSync = array_merge(
    $commonDataToSync,
    $vlDataToSync,
    $eidDataToSync,
    $covid19DataToSync,
    $hepatitisDataToSync
);


$payload['labId'] = $labId;


$client = new GuzzleHttp\Client();

$response = $client->post(
    $url,
    [
        GuzzleHttp\RequestOptions::JSON => $payload
    ]
);

$jsonResponse = $response->getBody()->getContents();
if (!empty($jsonResponse) && $jsonResponse != "[]") {

    $options = [
        'decoder' => new ExtJsonDecoder(true)
    ];
    $parsedData = Items::fromString($jsonResponse, $options);
    foreach ($parsedData as $dataType => $dataValues) {

        if (isset($dataToSync[$dataType]) && !empty($dataValues)) {
            if ($dataType === 'healthFacilities' && !empty($dataValues)) {
                $updatedFacilities = array_unique(array_column($dataValues, 'facility_id'));
                $db = $db->where('facility_id', $updatedFacilities, 'IN');
                $id = $db->delete('health_facilities');
            } else if ($dataType === 'testingLabs' && !empty($dataValues)) {
                $updatedFacilities = array_unique(array_column($dataValues, 'facility_id'));
                $db->where('facility_id', $updatedFacilities, 'IN');
                $id = $db->delete('testing_labs');
            }

            $tableColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $systemConfig['database']['db'] . "' AND table_name='" . $dataToSync[$dataType]['tableName'] . "'";
            $columnList = array_map('current', $db->rawQuery($tableColumns));
            
            foreach ($dataValues as $tableDataValues) {
                $tableData = [];
                $updateColumns = [];
                foreach ($columnList as $colName) {
                    if (isset($tableDataValues[$colName])) {
                        $tableData[$colName] = $tableDataValues[$colName];
                    } else {
                        $tableData[$colName] = null;
                    }
                }

                // For users table, we do not want to sync password and few other fields
                if ($dataType === 'users'){
                    $userColumnList = array('user_id', 'user_name', 'phone_number', 'email', 'updated_datetime');
                    $tableData = array_intersect_key($tableData, array_flip($userColumnList));
                }

                // getting column names using array_key
                // we will update all columns ON DUPLICATE
                $updateColumns = array_keys($tableData);
                $lastInsertId = $dataToSync[$dataType]['primaryKey'];
                $db->onDuplicate($updateColumns, $lastInsertId);
                $db->insert($dataToSync[$dataType]['tableName'], $tableData);

                // For updated facilities, we delete logo images (if any) and then we get new images (if any)
                // this ensures that if the logo was there previously it gets removed
                if ($dataType === 'facilities') {
                    $labLogoFolder = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $tableData['facility_id'];
                    // if (file_exists($labLogoFolder) && is_dir($labLogoFolder)) {
                    //     $images = glob("$labLogoFolder/*.{jpg,png,gif,jpeg}", GLOB_BRACE);
                    //     foreach ($images as $image) {
                    //         @unlink($image);
                    //     }
                    // }
                    if (!empty($tableData['facility_logo'])) {
                        if (!file_exists($labLogoFolder)) {
                            mkdir($labLogoFolder, 0777, true);
                        }
                        $remoteFileUrl = $systemConfig['remoteURL'] . '/uploads/facility-logo/' . $tableData['facility_id'] . '/' . "actual-" . $tableData['facility_logo'];
                        $localFilePath = $labLogoFolder . "/" . "actual-" . $tableData['facility_logo'];
                        file_put_contents($localFilePath, file_get_contents($remoteFileUrl));

                        $remoteFileUrl = $systemConfig['remoteURL'] . '/uploads/facility-logo/' . $tableData['facility_id'] . '/' . $tableData['facility_logo'];
                        $localFilePath = $labLogoFolder . "/" . $tableData['facility_logo'];
                        file_put_contents($localFilePath, file_get_contents($remoteFileUrl));
                    }
                }
            }
        }

        //update or insert testing labs signs
        if ($dataType === 'labReportSignatories') {
            foreach ($dataValues as $key => $sign) {

                // Delete old signatures, before we save new ones
                $db->where('lab_id  = ' . $sign['lab_id']);
                $id = $db->delete('lab_report_signatories');

                $signaturesFolder = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $sign['lab_id'] . DIRECTORY_SEPARATOR . 'signatures';

                if (!file_exists($signaturesFolder)) {
                    // make new folder
                    mkdir($signaturesFolder, 0777, true);
                } else {
                    // in case folder exists, we can delete all old files
                    $images = glob("$signaturesFolder/*.{jpg,png,gif,jpeg}", GLOB_BRACE);
                    foreach ($images as $image) {
                        @unlink($image);
                    }
                }
                // Save Data to DB
                unset($sign['signatory_id']);
                if (isset($sign['signature']) && $sign['signature'] != "") {
                    /* To save file from the url */
                    $remoteFileUrl = $systemConfig['remoteURL'] . '/uploads/labs/' . $sign['lab_id'] . '/signatures/' . $sign['signature'];
                    $localFileLocation = $signaturesFolder . DIRECTORY_SEPARATOR . $sign['signature'];
                    if (file_put_contents($localFileLocation, file_get_contents($remoteFileUrl))) {
                        $db->insert('lab_report_signatories', $sign);
                    }
                }
            }
        }
    }
}

/* Get instance id for update last_remote_results_sync */
$instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

/* Update last_remote_results_sync in s_vlsm_instance */
$db = $db->where('vlsm_instance_id', $instanceResult['vlsm_instance_id']);
$id = $db->update('s_vlsm_instance', array('last_remote_reference_data_sync' => DateUtils::getCurrentDateTime()));
