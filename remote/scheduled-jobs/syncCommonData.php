<?php
//update common table from remote to lab db
require_once(dirname(__FILE__) . "/../../startup.php");



if (!isset($systemConfig['remoteURL']) || $systemConfig['remoteURL'] == '') {
    echo "Please check if the Remote URL is set." . PHP_EOL;
    exit(0);
}

$systemConfig['remoteURL'] = rtrim($systemConfig['remoteURL'], "/");

$general = new \Vlsm\Models\General($db);
$globalConfigQuery = "SELECT * FROM system_config";
$configResult = $db->query($globalConfigQuery);


$globalConfigLastModified = $general->getLastModifiedDateTime('global_config', 'updated_on');
$provinceLastModified = $general->getLastModifiedDateTime('province_details');
$facilityLastModified = $general->getLastModifiedDateTime('facility_details');

$data = array(
    'globalConfigLastModified' => $globalConfigLastModified,
    'provinceLastModified' => $provinceLastModified,
    'facilityLastModified' => $facilityLastModified,
    "Key" => "vlsm-get-remote",
);

if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
    $data['vlArtCodesLastModified'] = $general->getLastModifiedDateTime('r_art_code_details');
    $data['vlRejectionReasonsLastModified'] = $general->getLastModifiedDateTime('r_sample_rejection_reasons');
    $data['vlSampleTypesLastModified'] = $general->getLastModifiedDateTime('r_vl_sample_type');
}

if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
    $data['eidRejectionReasonsLastModified'] = $general->getLastModifiedDateTime('r_eid_sample_rejection_reasons');
    $data['eidSampleTypesLastModified'] = $general->getLastModifiedDateTime('r_eid_sample_type');
}


if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
    $data['covid19RejectionReasonsLastModified'] = $general->getLastModifiedDateTime('r_covid19_sample_rejection_reasons');
    $data['covid19SampleTypesLastModified'] = $general->getLastModifiedDateTime('r_covid19_sample_type');
    $data['covid19ComorbiditiesLastModified'] = $general->getLastModifiedDateTime('r_covid19_comorbidities');
    $data['covid19ResultsLastModified'] = $general->getLastModifiedDateTime('r_covid19_results');
    $data['covid19SymptomsLastModified'] = $general->getLastModifiedDateTime('r_covid19_symptoms');
    $data['covid19ReasonForTestingLastModified'] = $general->getLastModifiedDateTime('r_covid19_test_reasons');
}

$url = $systemConfig['remoteURL'] . '/remote/remote/commonData.php';

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
$curl_response = curl_exec($ch);
//close connection
curl_close($ch);
$result = json_decode($curl_response, true);
// echo "<pre>";print_r($result);die;

//update or insert sample type
if (!empty($result['vlSampleTypes']) && count($result['vlSampleTypes']) > 0) {
    // making all local rows inactive 
    // this way any additional rows in local that are not on remote
    // become inactive.

    //$db->update('r_vl_sample_type',array('status'=>'inactive'));    

    foreach ($result['vlSampleTypes'] as $type) {
        $sTypeQuery = "SELECT sample_id FROM r_vl_sample_type WHERE sample_id=" . $type['sample_id'];
        $sTypeLocalResult = $db->query($sTypeQuery);
        $sTypeData = array('sample_name' => $type['sample_name'], 'status' => $type['status'], 'data_sync' => 1);
        $lastId = 0;
        if ($sTypeLocalResult) {
            $db = $db->where('sample_id', $type['sample_id']);
            $lastId = $db->update('r_vl_sample_type', $sTypeData);
        } else {
            $sTypeData['sample_id'] = $type['sample_id'];
            $db->insert('r_vl_sample_type', $sTypeData);
            $lastId = $db->getInsertId();
        }
    }
}

//update or insert art code deatils
if (!empty($result['vlArtCodes']) && count($result['vlArtCodes']) > 0) {
    // making all local rows inactive 
    // this way any additional rows in local that are not on remote
    // become inactive.

    //$db->update('r_art_code_details',array('art_status'=>'inactive'));    

    foreach ($result['vlArtCodes'] as $artCode) {
        $artCodeQuery = "SELECT art_id FROM r_art_code_details WHERE art_id=" . $artCode['art_id'];
        $artCodeLocalResult = $db->query($artCodeQuery);
        $artCodeData = array(
            'art_code' => $artCode['art_code'],
            'parent_art' => $artCode['parent_art'],
            'headings' => $artCode['headings'],
            'nation_identifier' => $artCode['nation_identifier'],
            'art_status' => $artCode['art_status'],
            'data_sync' => 1,
            'updated_datetime' => $artCode['updated_datetime']
        );
        $lastId = 0;
        if ($artCodeLocalResult) {
            $db = $db->where('art_id', $artCode['art_id']);
            $lastId = $db->update('r_art_code_details', $artCodeData);
        } else {
            $artCodeData['art_id'] = $artCode['art_id'];
            $db->insert('r_art_code_details', $artCodeData);
            $lastId = $db->getInsertId();
        }
    }
}

//update or insert rejected reason
if (!empty($result['vlRejectionReasons']) && count($result['vlRejectionReasons']) > 0) {

    // making all local rows inactive 
    // this way any additional rows in local that are not on remote
    // become inactive.

    //$db->update('r_sample_rejection_reasons',array('rejection_reason_status'=>'inactive'));    

    foreach ($result['vlRejectionReasons'] as $reason) {
        $rejectQuery = "SELECT rejection_reason_id FROM r_sample_rejection_reasons WHERE rejection_reason_id=" . $reason['rejection_reason_id'];
        $rejectLocalResult = $db->query($rejectQuery);
        $rejectResultData = array(
            'rejection_reason_name' => $reason['rejection_reason_name'],
            'rejection_type' => $reason['rejection_type'],
            'rejection_reason_status' => $reason['rejection_reason_status'],
            'rejection_reason_code' => $reason['rejection_reason_code'],
            'data_sync' => 1,
            'updated_datetime' => $reason['updated_datetime']
        );
        $lastId = 0;
        if ($rejectLocalResult) {
            $db = $db->where('rejection_reason_id', $reason['rejection_reason_id']);
            $lastId = $db->update('r_sample_rejection_reasons', $rejectResultData);
        } else {
            $rejectResultData['rejection_reason_id'] = $reason['rejection_reason_id'];
            $db->insert('r_sample_rejection_reasons', $rejectResultData);
            $lastId = $db->getInsertId();
        }
    }
}

//update or insert rejected reason
if (!empty($result['eidRejectionReasons']) && count($result['eidRejectionReasons']) > 0) {

    // making all local rows inactive 
    // this way any additional rows in local that are not on remote
    // become inactive.

    //$db->update('r_eid_sample_rejection_reasons',array('rejection_reason_status'=>'inactive'));    

    foreach ($result['eidRejectionReasons'] as $reason) {
        $rejectQuery = "SELECT rejection_reason_id FROM r_eid_sample_rejection_reasons WHERE rejection_reason_id=" . $reason['rejection_reason_id'];
        $rejectLocalResult = $db->query($rejectQuery);
        $rejectResultData = array(
            'rejection_reason_name' => $reason['rejection_reason_name'],
            'rejection_type' => $reason['rejection_type'],
            'rejection_reason_status' => $reason['rejection_reason_status'],
            'rejection_reason_code' => $reason['rejection_reason_code'],
            'data_sync' => 1, 'updated_datetime' => $reason['updated_datetime']
        );
        $lastId = 0;
        if ($rejectLocalResult) {
            $db = $db->where('rejection_reason_id', $reason['rejection_reason_id']);
            $lastId = $db->update('r_eid_sample_rejection_reasons', $rejectResultData);
        } else {
            $rejectResultData['rejection_reason_id'] = $reason['rejection_reason_id'];
            $db->insert('r_eid_sample_rejection_reasons', $rejectResultData);
            $lastId = $db->getInsertId();
        }
    }
}

/* for covid19 module common tables updates 
Covid19 Rejection Reasons Updates*/
if(!empty($result['covid19RejectionReasons']) && sizeof($result['covid19RejectionReasons']) > 0){
    foreach ($result['covid19RejectionReasons'] as $reason) {
        $c19RejectionReasonQuery = "SELECT rejection_reason_id FROM r_covid19_sample_rejection_reasons WHERE rejection_reason_id=" . $reason['rejection_reason_id'];
        $c19RejectionReasonResult = $db->query($c19RejectionReasonQuery);
        $c19RejectionReasonData = array(
            'rejection_reason_name'     => $reason['rejection_reason_name'],
            'rejection_type'            => $reason['rejection_type'],
            'rejection_reason_status'   => $reason['rejection_reason_status'],
            'rejection_reason_code'     => $reason['rejection_reason_code'],
            'updated_datetime'          => $reason['updated_datetime'],
            'data_sync'                 => 1
        );
        $lastId = 0;
        if ($c19RejectionReasonResult) {
            $db = $db->where('rejection_reason_id', $reason['rejection_reason_id']);
            $lastId = $db->update('r_covid19_sample_rejection_reasons', $c19RejectionReasonData);
        } else {
            $c19RejectionReasonData['rejection_reason_id'] = $reason['rejection_reason_id'];
            $db->insert('r_covid19_sample_rejection_reasons', $c19RejectionReasonData);
            $lastId = $db->getInsertId();
        }
    }
}
/* Covid19 Sample Types Updates */
if(!empty($result['covid19SampleTypes']) && sizeof($result['covid19SampleTypes']) > 0){
    foreach ($result['covid19SampleTypes'] as $sampleType) {
        $c19SampleTypeQuery = "SELECT sample_id FROM r_covid19_sample_type WHERE sample_id=" . $sampleType['sample_id'];
        $c19SampleTypeResult = $db->query($c19SampleTypeQuery);
        $c19SampleTypeData = array(
            'sample_name'       => $sampleType['sample_name'],
            'status'            => $sampleType['status'],
            'updated_datetime'  => $sampleType['updated_datetime'],
            'data_sync'         => 1
        );
        $lastId = 0;
        if ($c19SampleTypeResult) {
            $db = $db->where('sample_id', $sampleType['sample_id']);
            $lastId = $db->update('r_covid19_sample_type', $c19SampleTypeData);
        } else {
            $c19SampleTypeData['sample_id'] = $sampleType['sample_id'];
            $db->insert('r_covid19_sample_type', $c19SampleTypeData);
            $lastId = $db->getInsertId();
        }
    }
}
/* Covid19 Comorbidities Updates */
if(!empty($result['covid19Comorbidities']) && sizeof($result['covid19Comorbidities']) > 0){
    foreach ($result['covid19Comorbidities'] as $comorbidities) {
        $c19ComorbiditiesQuery = "SELECT comorbidity_id FROM r_covid19_comorbidities WHERE comorbidity_id=" . $comorbidities['comorbidity_id'];
        $c19ComorbiditiesResult = $db->query($c19ComorbiditiesQuery);
        $c19ComorbiditiesData = array(
            'comorbidity_name'      => $comorbidities['comorbidity_name'],
            'comorbidity_status'    => $comorbidities['comorbidity_status'],
            'updated_datetime'      => $comorbidities['updated_datetime'],
        );
        $lastId = 0;
        if ($c19ComorbiditiesResult) {
            $db = $db->where('comorbidity_id', $comorbidities['comorbidity_id']);
            $lastId = $db->update('r_covid19_comorbidities', $c19ComorbiditiesData);
        } else {
            $c19ComorbiditiesData['comorbidity_id'] = $comorbidities['comorbidity_id'];
            $db->insert('r_covid19_comorbidities', $c19ComorbiditiesData);
            $lastId = $db->getInsertId();
        }
    }
}
/* Covid19 Results Updates */
if(!empty($result['covid19Results']) && sizeof($result['covid19Results']) > 0){
    foreach ($result['covid19Results'] as $results) {
        $c19ResultsQuery = "SELECT result_id FROM r_covid19_results WHERE result_id='" . $results['result_id'] ."'";
        $c19ResultsResult = $db->query($c19ResultsQuery);
        $c19ResultsData = array(
            'result'            => $results['result'],
            'status'            => $results['status'],
            'updated_datetime'  => $results['updated_datetime'],
            'data_sync'         => 1
        );
        $lastId = 0;
        if ($c19ResultsResult) {
            $db = $db->where('result_id', $results['result_id']);
            $lastId = $db->update('r_covid19_results', $c19ResultsData);
        } else {
            $c19ResultsData['result_id'] = $results['result_id'];
            $db->insert('r_covid19_results', $c19ResultsData);
            $lastId = $db->getInsertId();
        }
    }
}
/* Covid19 Symptoms Updates */
if(!empty($result['covid19Symptoms']) && sizeof($result['covid19Symptoms']) > 0){
    foreach ($result['covid19Symptoms'] as $symptoms) {
        $c19SymptomsQuery = "SELECT symptom_id FROM r_covid19_symptoms WHERE symptom_id=" . $symptoms['symptom_id'];
        $c19SymptomsResult = $db->query($c19SymptomsQuery);
        $c19SymptomsData = array(
            'symptom_name'      => $symptoms['symptom_name'],
            'parent_symptom'    => $symptoms['parent_symptom'],
            'symptom_status'    => $symptoms['symptom_status'],
            'updated_datetime'  => $symptoms['updated_datetime'],
        );
        $lastId = 0;
        if ($c19SymptomsResult) {
            $db = $db->where('symptom_id', $symptoms['symptom_id']);
            $lastId = $db->update('r_covid19_symptoms', $c19SymptomsData);
        } else {
            $c19SymptomsData['symptom_id'] = $symptoms['symptom_id'];
            $db->insert('r_covid19_symptoms', $c19SymptomsData);
            $lastId = $db->getInsertId();
        }
    }
}
/* Covid19 ReasonForTesting Updates */
if(!empty($result['covid19ReasonForTesting']) && sizeof($result['covid19ReasonForTesting']) > 0){
    foreach ($result['covid19ReasonForTesting'] as $reasonForTesting) {
        $c19ReasonForTestingQuery = "SELECT test_reason_id FROM r_covid19_test_reasons WHERE test_reason_id=" . $reasonForTesting['test_reason_id'];
        $c19ReasonForTestingResult = $db->query($c19ReasonForTestingQuery);
        $c19ReasonForTestingData = array(
            'test_reason_name'      => $reasonForTesting['test_reason_name'],
            'parent_reason'         => $reasonForTesting['parent_reason'],
            'test_reason_status'    => $reasonForTesting['test_reason_status'],
            'updated_datetime'      => $reasonForTesting['updated_datetime']
        );
        $lastId = 0;
        if ($c19ReasonForTestingResult) {
            $db = $db->where('test_reason_id', $reasonForTesting['test_reason_id']);
            $lastId = $db->update('r_covid19_test_reasons', $c19ReasonForTestingData);
        } else {
            $c19ReasonForTestingData['test_reason_id'] = $reasonForTesting['test_reason_id'];
            $db->insert('r_covid19_test_reasons', $c19ReasonForTestingData);
            $lastId = $db->getInsertId();
        }
    }
}

//update or insert global config
if (!empty($result['globalConfig']) && count($result['globalConfig']) > 0) {

    foreach ($result['globalConfig'] as $config) {
        $configQuery = "SELECT name FROM global_config WHERE name='" . $config['name'] ."'";
        $configLocalResult = $db->query($configQuery);
        $configData = array(
            'display_name'          => $config['display_name'],
            'name'                  => $config['name'],
            'value'                 => $config['value'],
            'category'              => $config['category'],
            'remote_sync_needed'    => $config['remote_sync_needed'],
            'updated_on'            => $config['updated_on'],
            'updated_by'            => $config['updated_by'],
            'status'                => $config['status']
        );
        $lastId = 0;
        if ($configLocalResult) {
            $configData['updated_on'] = $general->getDateTime();
            $db = $db->where('name', $config['name']);
            $lastId = $db->update('global_config', $configData);
        } else {
            $db->insert('global_config', $configData);
            $lastId = $db->getInsertId();
        }
    }
}

//update or insert province
if (!empty($result['province']) && count($result['province']) > 0) {

    foreach ($result['province'] as $province) {
        $provinceQuery = "SELECT province_id FROM province_details WHERE province_id=" . $province['province_id'];
        $provinceLocalResult = $db->query($provinceQuery);
        $provinceData = array(
            'province_name' => $province['province_name'],
            'province_code' => $province['province_code'],
            'data_sync' => 1,
            'updated_datetime' => $general->getDateTime()
        );
        $lastId = 0;
        if ($provinceLocalResult) {
            $db = $db->where('province_id', $province['province_id']);
            $lastId = $db->update('province_details', $provinceData);
        } else {
            $provinceData['province_id'] = $province['province_id'];
            $db->insert('province_details', $provinceData);
            $lastId = $db->getInsertId();
        }
    }
}

//update or insert facility data

if (!empty($result['facilities']) && count($result['facilities']) > 0) {

    // making all local rows inactive 
    // this way any additional rows in local that are not on remote
    // become inactive.

    //$db->update('facility_details',array('status'=>'inactive'));

    $instanceId = $db->getValue('s_vlsm_instance', 'vlsm_instance_id');

    foreach ($result['facilities'] as $facility) {
        $facilityQuery = "SELECT facility_id FROM facility_details WHERE facility_id=" . $facility['facility_id'];
        $facilityLocalResult = $db->query($facilityQuery);
        $facilityData = array(
            'vlsm_instance_id' => $instanceId,
            'facility_name' => $facility['facility_name'],
            'facility_code' => $facility['facility_code'],
            'other_id' => $facility['other_id'],
            'facility_emails' => $facility['facility_emails'],
            'report_email' => $facility['report_email'],
            'contact_person' => $facility['contact_person'],
            'facility_mobile_numbers' => $facility['facility_mobile_numbers'],
            'address' => $facility['address'],
            'country' => $facility['country'],
            'facility_state' => $facility['facility_state'],
            'facility_state' => $facility['facility_state'],
            'facility_district' => $facility['facility_district'],
            'facility_hub_name' => $facility['facility_hub_name'],
            'latitude' => $facility['latitude'],
            'longitude' => $facility['longitude'],
            'facility_type' => $facility['facility_type'],
            'testing_points' => $facility['testing_points'],
            'status' => $facility['status'],
            'data_sync' => 1,
            'updated_datetime' => $facility['updated_datetime']
        );
        $lastId = 0;
        if ($facilityLocalResult) {
            $db = $db->where('facility_id', $facility['facility_id']);
            $lastId = $db->update('facility_details', $facilityData);
        } else {
            $facilityData['facility_id'] = $facility['facility_id'];
            $db->insert('facility_details', $facilityData);
            $lastId = $db->getInsertId();
        }
    }
}
