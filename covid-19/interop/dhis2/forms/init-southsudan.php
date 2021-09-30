<?php

// this file is included in /covid-19/interop/dhis2/covid-19-init.php

$dhis2 = new \Vlsm\Interop\Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);
$instanceId = 'dhis2';


// Getting all Testing Labs 
$data = array();
$data[] = "fields=options[id,name]";
$data[] = "paging=false";

$url = "/api/optionSets/fsHj2ZG3iHJ.json";

$response = $dhis2->get($url, $data);

$response = json_decode($response, true);

foreach ($response['options'] as $lab) {

    $db->where("other_id", $lab['id']);
    $facility = $db->getOne("facility_details");


    if (empty($facility)) {
        $facilityData = array(
            'facility_name' => $lab['name'],
            'vlsm_instance_id' => $instanceId,
            'other_id' => $lab['id'],
            'facility_type' => 2,
            'test_type' => 'covid19',
            'updated_datetime' => $general->getDateTime(),
            'status' => 'active'
        );
        $id = $db->insert('facility_details', $facilityData);

        $dataTest = array(
            'test_type' => 'covid19',
            'facility_id' => $id,
            'monthly_target' => null,
            'suppressed_monthly_target' => null,
            "updated_datetime" => $general->getDateTime()
        );
        $db->insert('testing_labs', $dataTest);
    }
}





// // Adding Facilities - We will only run this once
// $data = array();
// $data[] = "filter=level:eq:5";
// $data[] = "paging=false";
// $data[] = "fields=id,level,name,path,coordinates[id,name,parent]";

// $url = "/api/organisationUnits.json";

// $response = $dhis2->get($url, $data);
// $response = json_decode($response, true);

// foreach ($response['organisationUnits'] as $facility) {

//     $facilityData = array(
//         'facility_name' => $facility['name'],
//         'vlsm_instance_id' => $instanceId,
//         'other_id' => $facility['id'],
//         'facility_type' => 1,
//         'test_type' => 'covid19',
//         'updated_datetime' => $general->getDateTime(),
//         'status' => 'active'
//     );
//     $updateColumns = array("other_id", "updated_datetime");
//     $db->onDuplicate($updateColumns, 'facility_id');
//     $db->insert('facility_details', $facilityData);
//     $id = $db->getInsertId();
//     $db->where('facility_id  = ' . $id);
//     $db->delete('health_facilities');
//     $dataTest = array(
//         'test_type' => 'covid19',
//         'facility_id' => $id,
//         "updated_datetime" => $general->getDateTime()
//     );
//     $db->insert('health_facilities', $dataTest);
// }