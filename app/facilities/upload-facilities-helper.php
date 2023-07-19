<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;
use App\Services\FacilitiesService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\IOFactory;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$arr = [];
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilityService */
$facilityService = ContainerRegistry::get(FacilitiesService::class);

try {
   // $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['requestFile']['name']);
  //  $fileName = str_replace(" ", "-", $fileName);
    $fileName = $_FILES['facilitiesInfo']['name'];
    $ranNumber = $general->generateRandomString(12);
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName = $ranNumber . "." . $extension;

    if (!file_exists(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request") && !is_dir(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request")) {
        mkdir(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request", 0777, true);
    }
    if (move_uploaded_file($_FILES['facilitiesInfo']['tmp_name'], TEMP_PATH . DIRECTORY_SEPARATOR . "import-request" . DIRECTORY_SEPARATOR . $fileName)) {

        $file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        $mime_type = $file_info->buffer(file_get_contents(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request" . DIRECTORY_SEPARATOR . $fileName)); // e.g. gives "image/jpeg"

        $spreadsheet = IOFactory::load(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData   = $spreadsheet->getActiveSheet();
        $sheetData   = $sheetData->toArray(null, true, true, true);
        $returnArray = [];
        $resultArray = array_slice($sheetData, 1);
        $total = count($resultArray);
        $facilityNotAdded = [];
        foreach ($resultArray as $rowIndex => $rowData) {
           // echo '<pre>'; print_r($rowData); die;
            if (!empty($rowData['A'])) {
                $sampleCode         = $general->getDataFromOneFieldAndValue('form_covid19', 'sample_code', $rowData['A']);
                $provinceId         = $general->getDataFromOneFieldAndValue('geographical_divisions', 'geo_name', $rowData['D']);
                $facility           = $general->getDataFromOneFieldAndValue('facility_details', 'facility_name', $rowData['F']);
            }
            $instanceId = '';
            if (isset($_SESSION['instanceId'])) {
                $instanceId = $_SESSION['instanceId'];
                $_POST['instanceId'] = $instanceId;
            }
            $facilityCheck = $general->getDataFromOneFieldAndValue('facility_details', 'facility_name', $rowData['A']);
            $facilityCodeCheck = $general->getDataFromOneFieldAndValue('facility_details', 'facility_code', $rowData['B']);

            $provinceId = $facilityService->getOrCreateProvince($rowData['D']);
            $districtId = $facilityService->getOrCreateDistrict($rowData['E'],null,$provinceId);
            $data = array(
                'facility_name' => $rowData['A'],
                'facility_code' => !empty($rowData['B']) ? $rowData['B'] : null,
                'vlsm_instance_id' => $instanceId,
                'facility_mobile_numbers' => $rowData['I'],
                'address' => $rowData['G'],
                'facility_state' => $rowData['D'],
                'facility_district' => $rowData['E'],
                'facility_state_id' => $provinceId,
                'facility_district_id' => $districtId,
                'latitude' => $rowData['J'],
                'longitude' => $rowData['K'],
                'facility_emails' => $rowData['H'],
                'facility_type' => $rowData['F'],
                //'report_format' => (isset($rowData['F']) && $rowData['F'] == 2) ? json_encode($_POST['reportFormat']) : null,
                'updated_datetime' => DateUtility::getCurrentDateTime(),
                'status' => 'active'
            );
        
            if((isset($facilityCheck['facility_id']) && $facilityCheck['facility_id']!="") || (isset($facilityCodeCheck['facility_id']) && $facilityCodeCheck['facility_id']!=""))
            { 
                array_push($facilityNotAdded,$rowData['A']);
            }
            else{
                $db->insert('facility_details', $data);
                error_log($db->getLastError());
            }
        }
        $notAdded = count($facilityNotAdded);

    }
        $_SESSION['alertMsg'] = _("Facility details added successfully");
		header("Location:/facilities/upload-facilities.php?total=$total&notAdded=$notAdded");
}catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
