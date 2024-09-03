<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilityService */
$facilityService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {

    $uploadedFiles = $request->getUploadedFiles();
    $uploadedFile = $uploadedFiles['facilitiesInfo'];
    $fileName = $uploadedFile->getClientFilename();

    $uploadOption = $_POST['uploadOption'];

    $ranNumber = "BULK-FACILITIES-" . strtoupper(MiscUtility::generateRandomString(16));
    $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
    $fileName = $ranNumber . "." . $extension;

    $output = [];

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
        $filteredArray = array_filter((array)$resultArray, function ($row) {
            return array_filter($row); // Remove empty rows
        });
        $total = count($filteredArray);
        $facilityNotAdded = [];

        if ($total == 0) {
            $_SESSION['alertMsg'] = _translate("Please enter all the mandatory fields in the excel sheet");
            header("Location:/facilities/upload-facilities.php");
        }

        foreach ($filteredArray as $rowIndex => $rowData) {

            if (empty($rowData['A']) || empty($rowData['D']) || empty($rowData['E']) || empty($rowData['F'])) {
                $_SESSION['alertMsg'] = _translate("Please enter all the mandatory fields in the excel sheet");
                header("Location:/facilities/upload-facilities.php");
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

            $provinceId = $facilityService->getOrCreateProvince(trim($rowData['D']));
            $districtId = $facilityService->getOrCreateDistrict(trim($rowData['E']), null, $provinceId);

            $data = [
                'facility_name' => trim($rowData['A']) ?? null,
                'facility_code' => trim($rowData['B']) ?? null,
                'vlsm_instance_id' => $instanceId,
                'facility_mobile_numbers' => trim($rowData['I']) ?? null,
                'address' => trim($rowData['G']) ?? null,
                'facility_state' => trim($rowData['D']) ?? null,
                'facility_district' => trim($rowData['E']) ?? null,
                'facility_state_id' => $provinceId ?? null,
                'facility_district_id' => $districtId ?? null,
                'latitude' => trim($rowData['J']) ?? null,
                'longitude' => trim($rowData['K']) ?? null,
                'facility_emails' => trim($rowData['H']) ?? null,
                'facility_type' => trim($rowData['F']) ?? null,
                'updated_datetime' => DateUtility::getCurrentDateTime(),
                'status' => 'active'
            ];

            try {
                if ($uploadOption == "facility_name_match") {
                    if (!empty($facilityCheck)) {
                        $db->where("facility_id", $facilityCheck['facility_id']);
                        $db->update('facility_details', $data);
                    } else {
                        $facilityNotAdded[] = $rowData;
                    }
                } elseif ($uploadOption == "facility_code_match") {
                    if (!empty($facilityCodeCheck)) {
                        $db->where("facility_id", $facilityCodeCheck['facility_id']);
                        $db->update('facility_details', $data);
                    } else {
                        $facilityNotAdded[] = $rowData;
                    }
                } elseif ($uploadOption == "facility_name_code_match") {
                    if (!empty($facilityCodeCheck) && !empty($facilityCheck)) {
                        $db->where("facility_id", $facilityCheck['facility_id']);
                        $db->update('facility_details', $data);
                    } else {
                        $facilityNotAdded[] = $rowData;
                    }
                } else {
                    if (empty($facilityCodeCheck) && empty($facilityCheck)) {
                        $db->insert('facility_details', $data);
                    } else {
                        $facilityNotAdded[] = $rowData;
                    }
                }
            } catch (Throwable $e) {
                $facilityNotAdded[] = $rowData;
                LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
                LoggerUtility::logError($e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        $notAdded = count($facilityNotAdded);
        if ($notAdded > 0) {

            $spreadsheet = IOFactory::load(WEB_ROOT . '/files/facilities/Facilities_Bulk_Upload_Excel_Format.xlsx');

            $sheet = $spreadsheet->getActiveSheet();

            foreach ($facilityNotAdded as $rowNo => $dataValue) {
                $rRowCount = $rowNo + 2;
                $sheet->fromArray($dataValue, null, 'A' . $rRowCount);
            }

            $writer = IOFactory::createWriter($spreadsheet, IOFactory::READER_XLSX);
            $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'INCORRECT-FACILITY-ROWS.xlsx';
            $writer->save($filename);
        }

        $_SESSION['alertMsg'] = _translate("Facilities added successfully");
    } else {
        throw new SystemException(_translate("Bulk Facility Import Failed") . " - " . $uploadedFile->getError());
    }
    header("Location:/facilities/upload-facilities.php?total=$total&notAdded=$notAdded&link=$filename&option=$uploadOption");
} catch (Exception $exc) {
    throw new SystemException(($exc->getMessage()));
}
