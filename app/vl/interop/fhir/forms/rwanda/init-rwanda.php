<?php

// this file is included in /vl/interop/fhir/vl-receive.php

use App\Services\FacilitiesService;
use App\Services\CommonService;
use App\Services\VlService;
use App\Utilities\DateUtils;
use DCarbone\PHPFHIRGenerated\R4\PHPFHIRResponseParser;
use App\Interop\Fhir;

$interopConfig = require(APPLICATION_PATH . '/../configs/config.interop.php');

$general = new CommonService();
$vlModel = new VlService();

$vlsmSystemConfig = $general->getSystemConfig();

$fhir = new Fhir($interopConfig['FHIR']['url'], $interopConfig['FHIR']['auth']);

$receivedCounter = 0;
$processedCounter = 0;

$json = $fhir->get('/Organization');
//var_dump($json);die;

$organizations = json_decode($json, true);
$parser = new PHPFHIRResponseParser();

$metaResource = $parser->parse($json);


$db = MysqliDb::getInstance();

$entries = $metaResource->getEntry();
$facilityDb = new FacilitiesService();

foreach ($entries as $entry) {
    $resource = $entry->getResource();


    $facilityFHIRId = ((string) $resource->getId());
    $facilityName = $resource->getName() . "-$facilityFHIRId";
    $facilityCode = ((string) $resource->getIdentifier()[0]->getValue()) . "-$facilityFHIRId";
    $facilityState = ((string) $resource->getAddress()[0]->getState());

    $db->where("geo_name", $facilityState);
    $db->where("geo_parent", 0);
    $facilityStateRow = $db->getOne('geographical_divisions', array("geo_id", "geo_name"));
    if(!empty($facilityStateRow)){
        $facilityStateId = $facilityStateRow['geo_id'];
    } else {
        $facilityStateId = $db->insert('geographical_divisions', array('geo_name' => $facilityState, 'geo_parent' => 0, 'geo_status' => 'active'));
    }


    $facilityDistrict = ((string) $resource->getAddress()[0]->getDistrict());
    $db->where("geo_name", $facilityDistrict);
    $db->where("geo_parent", $facilityStateId);
    $facilityStateRow = $db->getOne('geographical_divisions', array("geo_id", "geo_name"));
    if(!empty($facilityStateRow)){
        $facilityDistrictId = $facilityStateRow['geo_id'];
    } else {
        $facilityDistrictId = $db->insert('geographical_divisions', array('geo_name' => $facilityDistrict, 'geo_parent' => $facilityStateId, 'geo_status' => 'active'));
    }    

    $facilityRow = $facilityDb->getFacilityByAttribute('facility_fhir_id', $facilityFHIRId);

    // looks like this FHIR Facility ID is already in the database. No need to do anything
    if (!empty($facilityRow)) continue;

    $instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");
    $instanceId = $instanceResult['vlsm_instance_id'];

    // looks like the FHIR Facility ID is not in db, let us add/insert new facility
    $db->where("facility_name", $facilityCode);
    $facilityRow = $db->getOne("facility_details");


    $facilityId = $facilityRow['facility_id'];
    $facilityAttributes = json_decode($facilityRow['facility_attributes'], true);
    $facilityAttributes['facility_fhir_id'] = $facilityFHIRId;

    $data['facility_attributes'] = json_encode($facilityAttributes);
    $data['facility_code'] = $facilityCode;
    $data['facility_name'] = $facilityName;
    $data['facility_state_id'] = $facilityStateId;
    $data['facility_state'] = $facilityState;
    $data['facility_district_id'] = $facilityDistrictId;
    $data['facility_district'] = $facilityDistrict;
    $data['vlsm_instance_id'] = $instanceId;
    $data['updated_datetime'] = DateUtils::getCurrentDateTime();
    $data['facility_type'] = 1;
    $data['status'] = 'active';

    if (!empty($facilityRow)) {
        $db->where("facility_id", $facilityId);
        $db->update("facility_details", $data);
        $id = $facilityId;
    } else {
        $id = $db->insert("facility_details", $data);
    }

    $dataTest = array(
        'test_type' => 'vl',
        'facility_id' => $id,
        "updated_datetime" => DateUtils::getCurrentDateTime()
    );
    $db->setQueryOption(array('IGNORE'))->insert('health_facilities', $dataTest);    
}