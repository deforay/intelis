<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilityService */
$facilityService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();

try {

    $uploadedFiles = $request->getUploadedFiles();
    $uploadedFile = $uploadedFiles['facilitiesInfo'];
    $fileName = $uploadedFile->getClientFilename();

    $uploadOption = $_POST['uploadOption'];

    $ranNumber = "BULK-FACILITIES-" . strtoupper($general->generateRandomString(16));
    $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
    $fileName = $ranNumber . "." . $extension;


    $excel = new Spreadsheet();
    $output = [];
    $sheet = $excel->getActiveSheet();

    MiscUtility::makeDirectory(TEMP_PATH);

    // Define the target path
    $targetPath = TEMP_PATH . DIRECTORY_SEPARATOR . $fileName;

    // Move the file
    $uploadedFile->moveTo($targetPath);

    if (0 == $uploadedFile->getError()) {

        $spreadsheet = IOFactory::load($targetPath);
        $sheetData   = $spreadsheet->getActiveSheet();
        $sheetData   = $sheetData->toArray(null, true, true, true);
        $returnArray = [];
        $resultArray = array_slice($sheetData, 1);
        $total = count($resultArray);
        $facilityNotAdded = [];

        foreach ($resultArray as $rowIndex => $rowData) {

            if (empty($rowData['A']) || empty($rowData['D']) || empty($rowData['E']) || empty($rowData['F'])) {
                $_SESSION['alertMsg'] = _translate("Please enter all the mandatory fields in the excel sheet");
                header("Location:/facilities/upload-facilities.php");
                die;
            }
            if (!in_array($rowData['F'], ['1', '2', '3'], true)) {
                $rowData['F'] = 1;
            }

            $instanceId = '';
            if (isset($_SESSION['instanceId'])) {
                $instanceId = $_SESSION['instanceId'];
                $_POST['instanceId'] = $instanceId;
            }
            $facilityCheck = $general->getDataFromOneFieldAndValue('facility_details', 'facility_name', $rowData['A']);
            $facilityCodeCheck = $general->getDataFromOneFieldAndValue('facility_details', 'facility_code', $rowData['B']);

            $provinceId = $facilityService->getOrCreateProvince($rowData['D']);
            $districtId = $facilityService->getOrCreateDistrict($rowData['E'], null, $provinceId);

            $data = array(
                'facility_name' => $rowData['A'],
                'facility_code' => $rowData['B'] ?? null,
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
                'updated_datetime' => DateUtility::getCurrentDateTime(),
                'status' => 'active'
            );


            if ($uploadOption == "facility_name_match") {
                if ((isset($facilityCheck['facility_id']) && $facilityCheck['facility_id'] != "") && (($facilityCodeCheck['facility_id']) == "")) {
                    $db->where("facility_id", $facilityCheck['facility_id']);
                    $db->update('facility_details', $data);
                    error_log($db->getLastError());
                } else {
                    $facilityNotAdded[] = $rowData;
                }
            } elseif ($uploadOption == "facility_code_match") {
                if ((isset($facilityCodeCheck['facility_id']) && $facilityCodeCheck['facility_id'] != "") && (($facilityCheck['facility_id']) == "")) {
                    $db->where("facility_id", $facilityCodeCheck['facility_id']);
                    $db->update('facility_details', $data);
                    error_log($db->getLastError());
                } else {
                    $facilityNotAdded[] = $rowData;
                }
            } elseif ($uploadOption == "facility_name_code_match") {
                if ((isset($facilityCheck['facility_id']) && $facilityCheck['facility_id'] != "") && (isset($facilityCodeCheck['facility_id']) && $facilityCodeCheck['facility_id'] != "")) {
                    $db->where("facility_id", $facilityCheck['facility_id']);
                    $db->update('facility_details', $data);
                    error_log($db->getLastError());
                } else {
                    $facilityNotAdded[] = $rowData;
                }
            } elseif ($uploadOption == "default") {
                if ((isset($facilityCheck['facility_id']) && $facilityCheck['facility_id'] != "") || (isset($facilityCodeCheck['facility_id']) && $facilityCodeCheck['facility_id'] != "")) {
                    $facilityNotAdded[] = $rowData;
                } else {
                    $db->insert('facility_details', $data);
                    error_log($db->getLastError());
                }
            }
        }

        $notAdded = count($facilityNotAdded);
        if ($notAdded > 0) {
            $column_header = ["Facility Name*", "Facility Code*", "External Facility Code", "Province/State*", "District/County*", "Facility Type* (1-Health Facility,2-Testing Lab,3-Collection Site)", "Address", "Email", "Phone Number", "Latitude", "Longitude"];


            $sheet->fromArray($column_header);

            foreach ($facilityNotAdded as $rowNo => $dataValue) {
                $rRowCount = $rowNo + 2;
                $sheet->fromArray($dataValue, null, 'A' . $rRowCount);
            }

            $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
            $filename = 'INCORRECT-FACILITY-ROWS.xlsx';
            $writer->save($targetPath);
        }


        $_SESSION['alertMsg'] = _translate("Facilities added successfully");
    } else {
        throw new SystemException(_translate("Bulk Facility Import Failed") . " - " . $uploadedFile->getError());
    }
    header("Location:/facilities/upload-facilities.php?total=$total&notAdded=$notAdded&link=$filename&option=$uploadOption");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    throw new SystemException(($exc->getMessage()));
}
