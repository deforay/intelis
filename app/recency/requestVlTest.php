<?php
// Allow from any origin
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");

    exit(0);
}
try {
    
    
    

    /** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

    // Takes raw data from the request
    $json = file_get_contents('php://input');
    $result = explode('&', $json);

    /* While it coming from the recency service we change the params */
    if ($result[9] == "service=") {
        $sam    = explode('=', $result[0]);
        $pat    = explode('=', $result[1]);
        $fac    = explode('=', $result[2]);
        $scd    = str_replace("sCDate=", "", $result[3]);
        $lab    = explode('=', $result[4]);
        $by     = explode('=', $result[5]);
        $dob    = explode('=', $result[6]);
        $age    = explode('=', $result[7]);
        $gender = explode('=', $result[8]);
        $result = [];
        $result[0] = $sam[1];
        $result[1] = $pat[1];
        $result[2] = $fac[1];
        $result[5] = $scd;
        $result[7] = $lab[1];
        $result[8] = $by[1];
        $result[3] = null;
        $result[4] = null;
        $result[9] = $dob[1];
        $result[10] = $age[1];
        $result[11] = $gender[1];
    }

    $data = [];
    $vlReqFromTable = "form_vl";
    
    if (isset($result) && count($result) > 0 && $result[0] != "") {

        /* To get province and district from facility id */
        $facilityQuery = "SELECT facility_id, facility_state, facility_district from facility_details WHERE other_id =" . $result[2];
        $facilityResult = $db->query($facilityQuery);

        /* Prepare the values to insert */
        $data['remote_sample_code'] = $result[0];
        $data['external_sample_code'] = $result[0];
        $data['sample_code'] = null;
        $data['patient_art_no'] = $result[1];
        $data['facility_id'] = $facilityResult[0]['facility_id'];
        $data['patient_province'] = $facilityResult[0]['facility_state'];
        $data['patient_district'] = $facilityResult[0]['facility_district'];
        $data['sample_collection_date'] = date('Y-m-d', strtotime($result[5]));
        // $data['sample_type']= $result[6];
        $data['sample_type'] = 2;
        $data['request_created_by'] = $result[8];
        $data['lab_id'] = $result[7];
        $data['request_created_datetime'] = DateUtility::getCurrentDateTime();
        $data['last_modified_datetime'] = DateUtility::getCurrentDateTime();
        $data['result_status'] = 6;
        $data['data_sync'] = 0;
        $data['recency_vl'] = 'yes';
        $data['reason_for_vl_testing'] = 9999; // 9999 is Recency Test in r_vl_test_reasons table
        $data['vlsm_country_id'] = $general->getGlobalConfig('vl_form');
        $data['result_status'] = 9;
        $data['patient_dob'] = date('Y-m-d',strtotime($result[9]));
        $data['patient_age_in_years'] = $result[10];
        $data['patient_gender'] = $result[11];

        /* Check if request data already placed or not */
        // $vlFormReqQuery ="SELECT vl_sample_id from form_vl WHERE remote_sample_code ='".$result[0]."' AND patient_art_no ='".$result[1]."' AND facility_id ='".$result[2]."' AND patient_province ='".$facilityResult[0]['facility_state']."' AND patient_district ='".$facilityResult[0]['facility_district']."' AND sample_collection_date ='".date('Y-m-d',strtotime($result[5]))."' AND sample_type ='".$result[6]."'";
        $vlFormReqQuery = "SELECT vl_sample_id from form_vl WHERE remote_sample_code ='" . $result[0] . "'";
        $vlFormReqResult = $db->query($vlFormReqQuery);

        /* If request data not requested then process otherwise send msg */
        if (isset($vlFormReqResult) && count($vlFormReqResult) == 0) {
            $db->insert($vlReqFromTable, $data);
            $lastId = $db->getInsertId();
            if (isset($lastId) && $lastId > 0) {
                echo 'success';
            } else {
                // echo 'Something went wrong try after some time..!';
            }
        } else {
            echo 'Testing request already sent for this sample id';
        }
    } else {
        // echo 'Something went wrong try after some time..!';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
