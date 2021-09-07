<?php

require_once(dirname(__FILE__) . "/../../startup.php");
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);


if (!isset($systemConfig['remoteURL']) || $systemConfig['remoteURL'] == '') {
    echo "Please check if the Remote URL is set." . PHP_EOL;
    exit(0);
}


//update common data from remote to lab db
$systemConfig['remoteURL'] = rtrim($systemConfig['remoteURL'], "/");

$headers = @get_headers($systemConfig['remoteURL'] . '/vlsts-icons/favicon-16x16.png');

if (strpos($headers[0], '200') === false) {
    error_log("No internet connectivity while trying remote sync.");
    return false;
}


$general = new \Vlsm\Models\General($db);
$app = new \Vlsm\Models\App($db);

$globalConfigQuery = "SELECT * FROM system_config";
$configResult = $db->query($globalConfigQuery);

$dataToSync = array();
$commonDataToSync = array();
$vlDataToSync = array();
$eidDataToSync = array();
$covid19DataToSync = array();
$hepatitisDataToSync = array();


$data = array(
    'globalConfigLastModified'      => $general->getLastModifiedDateTime('global_config', 'updated_on'),
    'provinceLastModified'          => $general->getLastModifiedDateTime('province_details'),
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
        'primaryKey' => 'province_id',
        'tableName' => 'province_details',
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


$url = $systemConfig['remoteURL'] . '/remote/remote/commonData.php';

if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
    $data['vlArtCodesLastModified'] = $general->getLastModifiedDateTime('r_vl_art_regimen');
    $data['vlRejectionReasonsLastModified'] = $general->getLastModifiedDateTime('r_vl_sample_rejection_reasons');
    $data['vlSampleTypesLastModified'] = $general->getLastModifiedDateTime('r_vl_sample_type');




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
        )
    );
}

if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
    $data['eidRejectionReasonsLastModified'] = $general->getLastModifiedDateTime('r_eid_sample_rejection_reasons');
    $data['eidSampleTypesLastModified'] = $general->getLastModifiedDateTime('r_eid_sample_type');
    $data['eidResultsLastModified'] = $general->getLastModifiedDateTime('r_eid_results ');
    $data['eidReasonForTestingLastModified'] = $general->getLastModifiedDateTime('r_eid_test_reasons  ');


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


if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
    $data['covid19RejectionReasonsLastModified'] = $general->getLastModifiedDateTime('r_covid19_sample_rejection_reasons');
    $data['covid19SampleTypesLastModified'] = $general->getLastModifiedDateTime('r_covid19_sample_type');
    $data['covid19ComorbiditiesLastModified'] = $general->getLastModifiedDateTime('r_covid19_comorbidities');
    $data['covid19ResultsLastModified'] = $general->getLastModifiedDateTime('r_covid19_results');
    $data['covid19SymptomsLastModified'] = $general->getLastModifiedDateTime('r_covid19_symptoms');
    $data['covid19ReasonForTestingLastModified'] = $general->getLastModifiedDateTime('r_covid19_test_reasons');



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
        )
    );
}

if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) {
    $data['hepatitisRejectionReasonsLastModified'] = $general->getLastModifiedDateTime('r_hepatitis_sample_rejection_reasons');
    $data['hepatitisSampleTypesLastModified'] = $general->getLastModifiedDateTime('r_hepatitis_sample_type');
    $data['hepatitisComorbiditiesLastModified'] = $general->getLastModifiedDateTime('r_hepatitis_comorbidities');
    $data['hepatitisResultsLastModified'] = $general->getLastModifiedDateTime('r_hepatitis_results');
    $data['hepatitisReasonForTestingLastModified'] = $general->getLastModifiedDateTime('r_hepatitis_test_reasons');

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



// echo "<pre>";print_r($data);die;
$ch = curl_init($url);
$json_data = json_encode($data);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Content-Type: application/json',
    'Content-Length: ' . strlen($json_data)
));
// execute post
$jsonResponse = curl_exec($ch);
//close connection
curl_close($ch);


$parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse);
foreach ($parsedData as $dataType => $dataValues) {
    

    if (isset($dataToSync[$dataType]) && !empty($dataValues)) {

        if($dataType == 'healthFacilities'){
            $updatedFacilities = array_column($dataValues, 'facility_id');
            $db = $db->where('facility_id', $updatedFacilities, 'IN');
            $id = $db->delete('health_facilities');
        }
        else if($dataType == 'testingLabs'){
            $updatedFacilities = array_column($dataValues, 'facility_id');
            $db->where('facility_id', $updatedFacilities, 'IN');
            $id = $db->delete('testing_labs');
        }

        foreach ($dataValues as $tableData) {
            // getting column names using array_key
            // we will update all columns ON DUPLICATE
            $updateColumns = array_keys($tableData);
            $lastInsertId = $dataToSync[$dataType]['primaryKey'];
            $db->onDuplicate($updateColumns, $lastInsertId);
            $db->insert($dataToSync[$dataType]['tableName'], $tableData);
        }
    }

    //update or insert testing labs signs
    if ($dataType == 'labReportSignatories') {
        foreach ($dataValues as $key => $sign) {

            // Deleteold signatures, before we save new ones
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
                $filePath = $systemConfig['remoteURL'] . '/uploads/labs/' . $sign['lab_id'] . '/signatures/' . $sign['signature'];
                $pathname = $signaturesFolder . DIRECTORY_SEPARATOR . $sign['signature'];
                if (file_put_contents($pathname, file_get_contents($filePath))) {
                    $db->insert('lab_report_signatories', $sign);
                }
            }
        }
    }
}


/* Get instance id for update last_remote_results_sync */
$instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

/* Update last_remote_results_sync in s_vlsm_instance */
$db = $db->where('vlsm_instance_id', $instanceResult['vlsm_instance_id']);
$id = $db->update('s_vlsm_instance', array('last_remote_reference_data_sync' => $general->getDateTime()));