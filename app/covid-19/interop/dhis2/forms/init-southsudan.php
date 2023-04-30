<?php

// this file is included in /covid-19/interop/dhis2/covid-19-init.php

use App\Interop\Dhis2;
use App\Utilities\DateUtility;

$dhis2 = new Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);
$instanceId = 'dhis2';

//tkIsR0Lo23H

$initOptionSets = array(
    'testingLabs' => 'fsHj2ZG3iHJ',
    // 'testTypes' => 'tkIsR0Lo23H',
    // 'testingPlatform' => 'RFqme0EHhdF'
);

foreach ($initOptionSets as $t => $id) {
    $data = [];
    $data[] = "fields=options[:all]";
    $data[] = "paging=false";

    $url = "/api/optionSets/$id.json";

    $response = $dhis2->get($url, $data);

    $response = json_decode($response, true);
    if (!empty($response) && $t == 'testingLabs') {
        foreach ($response['options'] as $lab) {

            $facilityData = array(
                'facility_name' => $lab['name'],
                'vlsm_instance_id' => $instanceId,
                'other_id' => $lab['id'],
                'facility_type' => 2,
                'test_type' => 'covid19',
                'updated_datetime' => DateUtility::getCurrentDateTime(),
                'status' => 'active'
            );
            $updateColumns = array("other_id", "updated_datetime");
            $db->onDuplicate($updateColumns, 'facility_id');
            $db->insert('facility_details', $facilityData);
            $id = $db->getInsertId();

            $dataTest = array(
                'test_type' => 'covid19',
                'facility_id' => $id,
                'monthly_target' => null,
                'suppressed_monthly_target' => null,
                "updated_datetime" => DateUtility::getCurrentDateTime()
            );
            $db->setQueryOption(array('IGNORE'))->insert('testing_labs', $dataTest);
        }
    } else if (!empty($response) && $t == 'testTypes') {
        $_SESSION['DHIS2_TEST_TYPES'] = [];
        foreach ($response['options'] as $opts) {
            $_SESSION['DHIS2_TEST_TYPES'][$opts['code']] = $opts['name'];
        }
    } else if (!empty($response) && $t == 'testingPlatform') {
        $_SESSION['DHIS2_TESTING_PLATFORMS'] = [];
        foreach ($response['options'] as $opts) {
            $_SESSION['DHIS2_TESTING_PLATFORMS'][$opts['code']] = $opts['name'];
        }
    }
}




// // Adding Facilities - We will only run this once
// $data = [];
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
//         'updated_datetime' => \App\Utilities\DateUtility::getCurrentDateTime(),
//         'status' => 'active'
//     );
//     $updateColumns = array("other_id", "updated_datetime");
//     $db->onDuplicate($updateColumns, 'facility_id');
//     $db->insert('facility_details', $facilityData);
//     $id = $db->getInsertId();
//     // $db->where('facility_id  = ' . $id);
//     // $db->delete('health_facilities');
//     $dataTest = array(
//         'test_type' => 'covid19',
//         'facility_id' => $id,
//         "updated_datetime" => \App\Utilities\DateUtility::getCurrentDateTime()
//     );
//     $db->setQueryOption(array('IGNORE'))->insert('health_facilities', $dataTest);
// }
