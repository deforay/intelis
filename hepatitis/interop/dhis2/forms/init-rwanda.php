<?php

// this file is included in /hepatitis/interop/dhis2/hepatitis-init.php

$dhis2 = new \Vlsm\Interop\Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);
$instanceId = 'dhis2';

$initOptionSets = array(
    //'province' => 'LqaKTLJFf4H',
    //'district' => 'HGTWO3xvXRX',
    //'socialStatus' => 'cNhaGfDzbUc',
    'vlTestReasons' => 'zgKhu7dBYJm',
    //'gender' => 'CFbcYp2biob',
    'testingLabs' => 'qrroYEzTQd3',
);

foreach ($initOptionSets as $t => $id) {
    $data = array();
    $data[] = "fields=options[:all]";
    $data[] = "paging=false";

    $url = "/api/optionSets/$id.json";

    $response = $dhis2->get($url, $data);

    $response = json_decode($response, true);
    if (!empty($response) && $t == 'province') {
        $_SESSION['DHIS2_HEP_PROVINCES'] = array();

        foreach ($response['options'] as $province) {
            $_SESSION['DHIS2_HEP_PROVINCES'][$province['code']] = $province['name'];
        }
    } else if (!empty($response) && $t == 'district') {

        $_SESSION['DHIS2_HEP_DISTRICTS'] = array();
        foreach ($response['options'] as $district) {
            $_SESSION['DHIS2_HEP_DISTRICTS'][$district['code']] = $district['name'];
        }
    } else if (!empty($response) && $t == 'vlTestReasons') {

        $_SESSION['DHIS2_VL_TEST_REASONS'] = array();
        foreach ($response['options'] as $vlTestReasons) {
            $_SESSION['DHIS2_VL_TEST_REASONS'][$vlTestReasons['code']] = $vlTestReasons['name'];
        }
    } else if (!empty($response) && $t == 'testingLabs') {

        foreach ($response['options'] as $lab) {

            //$_SESSION['DHIS2_HEP_TESTING_LABS'][$lab['id']] = $lab['name'];

            $db->where("other_id", $lab['code']);
            $db->orWhere("facility_name", $lab['name']);
            $facility = $db->getOne("facility_details");
            //echo "<pre> FAC ";var_dump($facility);echo "</pre>";
            if (empty($facility)) {
                $facilityData = array(
                    'facility_name' => $lab['name'],
                    'vlsm_instance_id' => $instanceId,
                    'other_id' => $lab['code'],
                    'facility_type' => 2,
                    'test_type' => 'hepatitis',
                    'updated_datetime' => $general->getDateTime(),
                    'status' => 'active'
                );
                //echo "<pre> DAT ";var_dump($facilityData);echo "</pre>";
                $id = $db->insert('facility_details', $facilityData);
                $id = $db->getInsertId();

                $dataTest = array(
                    'test_type' => 'hepatitis',
                    'facility_id' => $id,
                    'monthly_target' => null,
                    'suppressed_monthly_target' => null,
                    "updated_datetime" => $general->getDateTime()
                );
                //echo "<pre> TESTING ";var_dump($dataTest);echo "</pre>";
                $db->insert('testing_labs', $dataTest);
            }
        }
    }
}


// // Adding Facilities - We will only run this once
// // https://his.rbc.gov.rw/hepatitis/api/organisationUnits?filter=level:eq:6&paging=false&


// $data[] = "filter=level:eq:6";
// $data[] = "paging=false";
// $data[] = "fields=id,level,name,path,coordinates[id,name,parent]";

// $url = "/api/organisationUnits.json";

// $response = $dhis2->get($url, $data);
// $response = json_decode($response, true);

// foreach ($response['organisationUnits'] as $facility) {

//     // $db->where("other_id", $facility['id']);
//     // $db->orWhere("facility_name", $facility['name']);
//     // $facilityResult = $db->getOne("facility_details");

    

//     $facilityData = array(
//         'facility_name' => $facility['name'],
//         'vlsm_instance_id' => $instanceId,
//         'other_id' => $facility['id'],
//         'facility_type' => 1,
//         'test_type' => 'hepatitis',
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
//         'test_type' => 'hepatitis',
//         'facility_id' => $id,
//         "updated_datetime" => $general->getDateTime()
//     );
//     $db->insert('health_facilities', $dataTest);
// }
