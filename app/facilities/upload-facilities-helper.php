<?php

use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilityService */
$facilityService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];

try {

    $uploadedFiles = $request->getUploadedFiles();
    $uploadedFile = $uploadedFiles['facilitiesInfo'];
    $fileName = $uploadedFile->getClientFilename();



    $ranNumber = "BULK-FACILITIES-" . strtoupper($general->generateRandomString(16));
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
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

            if ((isset($facilityCheck['facility_id']) && $facilityCheck['facility_id'] != "") || (isset($facilityCodeCheck['facility_id']) && $facilityCodeCheck['facility_id'] != "")) {
                array_push($facilityNotAdded, $rowData);
            } else {
                $db->insert('facility_details', $data);
                error_log($db->getLastError());
            }
        }

        $notAdded = count($facilityNotAdded);
        if ($notAdded > 0) {
            $column_header = ["Facility Name*", "Facility Code*", "External Facility Code", "Province/State*", "District/County*", "Facility Type* (1-Health Facility,2-Testing Lab,3-Collection Site)", "Address", "Email", "Phone Number", "Latitude", "Longitude"];
            $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
                ->setValueExplicit(html_entity_decode($nameValue));
            $colNo = 1;
            foreach ($column_header as $value) {
                $sheet->getCell(Coordinate::stringFromColumnIndex($colNo) . '1')
                    ->setValueExplicit(html_entity_decode($value));
                $colNo++;
            }

            foreach ($facilityNotAdded as $rowNo => $dataValue) {
                $colNo = 1;
                $rRowCount = $rowNo + 2;
                foreach ($dataValue as $field => $value) {
                    $sheet->setCellValue(
                        Coordinate::stringFromColumnIndex($colNo) . $rRowCount,
                        html_entity_decode($value)
                    );
                    $colNo++;
                }
            }

            $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
            $filename = 'INCORRECT-FACILITY-ROWS.xlsx';
            $writer->save($targetPath);
        }


        $_SESSION['alertMsg'] = _translate("Facilities added successfully");
    } else {
        throw new SystemException(_translate("Bulk Facility Import File not uploaded") . " - " . $uploadedFile->getError());
    }
    header("Location:/facilities/upload-facilities.php?total=$total&notAdded=$notAdded&link=$filename");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
    throw new SystemException(($exc->getMessage()));
}
