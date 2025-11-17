<?php

// this file is included in /hepatitis/interop/dhis2/hepatitis-init.php

use App\Interop\Dhis2;
use JsonMachine\Items;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\Exception\PathNotFoundException;
use JsonMachine\Exception\InvalidArgumentException;

require_once(__DIR__ . "/../../../../../bootstrap.php");

try {

    $dhis2 = new Dhis2(DHIS2_URL, DHIS2_USER, DHIS2_PASSWORD);
    $instanceId = 'dhis2';


    $initOptionSets = [
        //'province' => 'LqaKTLJFf4H',
        //'district' => 'HGTWO3xvXRX',
        //'socialStatus' => 'cNhaGfDzbUc',
        'vlTestReasons' => 'zgKhu7dBYJm',
        //'gender' => 'CFbcYp2biob',
        'testingLabs' => 'qrroYEzTQd3',
    ];

    $_SESSION['DHIS2_HEP_PROVINCES'] = [];
    $_SESSION['DHIS2_HEP_DISTRICTS'] = [];
    $_SESSION['DHIS2_VL_TEST_REASONS'] = [];

    foreach ($initOptionSets as $t => $id) {
        $data = [];
        $data[] = "fields=options[:all]";
        $data[] = "paging=false";

        $url = "/api/optionSets/$id.json";

        $response = $dhis2->get($url, $data);

        if ($response && $response->getStatusCode() === 200) {
            $responseBody = (string) $response->getBody();

            $options = Items::fromString($responseBody, ['pointer' => '/options', 'decoder' => new ExtJsonDecoder(true)]);

            foreach ($options as $option) {
                if ($t === 'province') {
                    $_SESSION['DHIS2_HEP_PROVINCES'][$option['code']] = $option['name'];
                } elseif ($t === 'district') {
                    $_SESSION['DHIS2_HEP_DISTRICTS'][$option['code']] = $option['name'];
                } elseif ($t === 'vlTestReasons') {
                    $_SESSION['DHIS2_VL_TEST_REASONS'][$option['code']] = $option['name'];
                } elseif ($t === 'testingLabs') {
                    $facilityData = [
                        'facility_name' => $option['name'],
                        'vlsm_instance_id' => $instanceId, // Ensure $instanceId is defined
                        'other_id' => $option['id'],
                        'facility_type' => 2,
                        'test_type' => 'hepatitis',
                        'updated_datetime' => DateUtility::getCurrentDateTime(),
                        'status' => 'active'
                    ];
                    $updateColumns = ["other_id", "updated_datetime"];

                    $db->upsert('facility_details', $facilityData, $updateColumns);
                    $id = $db->getInsertId();
                    if ($id > 0) {
                        $dataTest = [
                            'test_type' => 'hepatitis',
                            'facility_id' => $id,
                            'monthly_target' => null,
                            'suppressed_monthly_target' => null,
                            "updated_datetime" => DateUtility::getCurrentDateTime()
                        ];
                        //$db->setQueryOption(['IGNORE'])->insert('testing_labs', $dataTest);
                        $db->upsert('testing_labs', $dataTest);
                    }
                }
            }
        }
    }


    // Adding Facilities - We will only run this once
    // /hepatitis/api/organisationUnits?filter=level:eq:6&paging=false&


    $data[] = "filter=level:eq:6";
    $data[] = "paging=false";
    $data[] = "fields=id,level,name,path,coordinates[id,name,parent]";

    $url = "/api/organisationUnits.json";

    $response = $dhis2->get($url, $data);

    if ($response && $response->getStatusCode() === 200) {
        $responseBody = (string) $response->getBody();

        $facilities = Items::fromString(
            $responseBody,
            [
                'pointer' => '/organisationUnits',
                'decoder' => new ExtJsonDecoder(true)
            ]
        );
        foreach ($facilities as $facility) {

            // $db->where("other_id", $facility['id']);
            // $db->orWhere("facility_name", $facility['name']);
            // $facilityResult = $db->getOne("facility_details");

            $facilityData = ['facility_name' => $facility['name'], 'vlsm_instance_id' => $instanceId, 'other_id' => $facility['id'], 'facility_type' => 1, 'test_type' => 'hepatitis', 'updated_datetime' => DateUtility::getCurrentDateTime(), 'status' => 'active'];
            $updateColumns = ["other_id", "updated_datetime"];
            $db->upsert('facility_details', $facilityData, $updateColumns);
            $id = $db->getInsertId();

            if ($id > 0) {
                $dataTest = ['test_type' => 'hepatitis', 'facility_id' => $id, "updated_datetime" => DateUtility::getCurrentDateTime()];
                $db->upsert('health_facilities', $dataTest);
            }
        }
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile()
    ]);
}
