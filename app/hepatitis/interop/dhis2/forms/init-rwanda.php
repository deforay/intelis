<?php

use App\Interop\Dhis2;
use App\Utilities\DateUtils;

require_once(__DIR__ . "/../../../../../bootstrap.php");


// this file is included in /hepatitis/interop/dhis2/hepatitis-init.php

$dhis2 = new Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);
$instanceId = 'dhis2';

$initOptionSets = array(
    //'province' => 'LqaKTLJFf4H',
    //'district' => 'HGTWO3xvXRX',
    //'socialStatus' => 'cNhaGfDzbUc',
    'vlTestReasons' => 'zgKhu7dBYJm',
    //'gender' => 'CFbcYp2biob',
    'testingLabs' => 'qrroYEzTQd3',
);

$_SESSION['DHIS2_HEP_PROVINCES'] = array();
$_SESSION['DHIS2_HEP_DISTRICTS'] = array();
$_SESSION['DHIS2_VL_TEST_REASONS'] = array();

foreach ($initOptionSets as $t => $id) {
    $data = array();
    $data[] = "fields=options[:all]";
    $data[] = "paging=false";

    $url = "/api/optionSets/$id.json";

    $response = $dhis2->get($url, $data);

    $response = json_decode($response, true);
    if (!empty($response) && $t == 'province') {


        foreach ($response['options'] as $province) {
            $_SESSION['DHIS2_HEP_PROVINCES'][$province['code']] = $province['name'];
        }
    } else if (!empty($response) && $t == 'district') {


        foreach ($response['options'] as $district) {
            $_SESSION['DHIS2_HEP_DISTRICTS'][$district['code']] = $district['name'];
        }
    } else if (!empty($response) && $t == 'vlTestReasons') {

        foreach ($response['options'] as $vlTestReasons) {
            $_SESSION['DHIS2_VL_TEST_REASONS'][$vlTestReasons['code']] = $vlTestReasons['name'];
        }
    } else if (!empty($response) && $t == 'testingLabs') {

        foreach ($response['options'] as $lab) {

            $facilityData = array(
                'facility_name' => $lab['name'],
                'vlsm_instance_id' => $instanceId,
                'other_id' => $lab['id'],
                'facility_type' => 2,
                'test_type' => 'hepatitis',
                'updated_datetime' => DateUtils::getCurrentDateTime(),
                'status' => 'active'
            );
            $updateColumns = array("other_id", "updated_datetime");
            $db->onDuplicate($updateColumns, 'facility_id');
            $db->insert('facility_details', $facilityData);
            $id = $db->getInsertId();

            $dataTest = array(
                'test_type' => 'hepatitis',
                'facility_id' => $id,
                'monthly_target' => null,
                'suppressed_monthly_target' => null,
                "updated_datetime" => DateUtils::getCurrentDateTime()
            );
            $db->setQueryOption(array('IGNORE'))->insert('testing_labs', $dataTest);
        }
    }
}


// Adding Facilities - We will only run this once
// https://hmis.moh.gov.rw/hepatitis/api/organisationUnits?filter=level:eq:6&paging=false&


$data[] = "filter=level:eq:6";
$data[] = "paging=false";
$data[] = "fields=id,level,name,path,coordinates[id,name,parent]";

$url = "/api/organisationUnits.json";

$response = $dhis2->get($url, $data);
$response = json_decode($response, true);

foreach ($response['organisationUnits'] as $facility) {

    // $db->where("other_id", $facility['id']);
    // $db->orWhere("facility_name", $facility['name']);
    // $facilityResult = $db->getOne("facility_details");

    

    $facilityData = array(
        'facility_name' => $facility['name'],
        'vlsm_instance_id' => $instanceId,
        'other_id' => $facility['id'],
        'facility_type' => 1,
        'test_type' => 'hepatitis',
        'updated_datetime' => DateUtils::getCurrentDateTime(),
        'status' => 'active'
    );
    $updateColumns = array("other_id", "updated_datetime");
    $db->onDuplicate($updateColumns, 'facility_id');
    $db->insert('facility_details', $facilityData);
    $id = $db->getInsertId();

    $dataTest = array(
        'test_type' => 'hepatitis',
        'facility_id' => $id,
        "updated_datetime" => DateUtils::getCurrentDateTime()
    );
    $db->setQueryOption(array('IGNORE'))->insert('health_facilities', $dataTest);
}
