<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\FacilitiesService;
use Laminas\Diactoros\ServerRequest;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilityService */
$facilityService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {

    $uploadedFiles = $request->getUploadedFiles();
    $uploadedFile = $uploadedFiles['facilitiesInfo'];
    $fileName = $uploadedFile->getClientFilename();

    $uploadOption = $_POST['uploadOption'];

    $randomFileId = MiscUtility::generateRandomString(8);
    $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
    $fileName = "BULK-FACILITIES-IMPORT-" . DateUtility::getCurrentDateTime('Y-m-d-h-i-s') . "-" . $randomFileId . "." . $extension;

    $output = [];

    MiscUtility::makeDirectory(TEMP_PATH);

    // Define the target path
    $targetPath = TEMP_PATH . DIRECTORY_SEPARATOR . $fileName;

    // Move the file
    $uploadedFile->moveTo($targetPath);

    if (0 == $uploadedFile->getError()) {

        $spreadsheet = IOFactory::load($targetPath);
        $sheetData = $spreadsheet->getActiveSheet();
        $sheetData = $sheetData->toArray(null, true, true, true);
        $returnArray = [];
        $resultArray = array_slice($sheetData, 1);
        $filteredArray = array_filter((array) $resultArray, function ($row): array {
            return array_filter($row); // Remove empty rows
        });
        $total = count($filteredArray);
        $facilityNotAdded = [];
        $insertedCount = 0;
        $updatedCount = 0;

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

            $provinceId = $facilityService->getOrCreateProvince(trim((string) $rowData['D']));
            $districtId = $facilityService->getOrCreateDistrict(trim((string) $rowData['E']), null, $provinceId);

            $data = [
                'facility_name' => trim((string) $rowData['A']) ?? null,
                'facility_code' => trim((string) $rowData['B']) ?? null,
                'vlsm_instance_id' => $instanceId,
                'facility_mobile_numbers' => trim((string) $rowData['I']) ?? null,
                'address' => trim((string) $rowData['G']) ?? null,
                'facility_state' => trim((string) $rowData['D']) ?? null,
                'facility_district' => trim((string) $rowData['E']) ?? null,
                'facility_state_id' => $provinceId ?? null,
                'facility_district_id' => $districtId ?? null,
                'latitude' => trim((string) $rowData['J']) ?? null,
                'longitude' => trim((string) $rowData['K']) ?? null,
                'facility_emails' => trim((string) $rowData['H']) ?? null,
                'facility_type' => trim($rowData['F']) ?? null,
                'updated_datetime' => DateUtility::getCurrentDateTime(),
                'status' => 'active'
            ];

            try {
                if ($uploadOption == "facility_name_match") {
                    if (!empty($facilityCheck)) {
                        $db->where("facility_id", $facilityCheck['facility_id']);
                        $result = $db->update('facility_details', $data);
                        if ($result !== false) {
                            $updatedCount++;
                        } else {
                            $facilityNotAdded[] = $rowData;
                        }
                    } else {
                        $facilityNotAdded[] = $rowData;
                    }
                } elseif ($uploadOption == "facility_code_match") {
                    if (!empty($facilityCodeCheck)) {
                        $db->where("facility_id", $facilityCodeCheck['facility_id']);
                        $result = $db->update('facility_details', $data);
                        if ($result !== false) {
                            $updatedCount++;
                        } else {
                            $facilityNotAdded[] = $rowData;
                        }
                    } else {
                        $facilityNotAdded[] = $rowData;
                    }
                } elseif ($uploadOption == "facility_name_code_match") {
                    if (!empty($facilityCodeCheck) && !empty($facilityCheck)) {
                        $db->where("facility_id", $facilityCheck['facility_id']);
                        $result = $db->update('facility_details', $data);
                        if ($result !== false) {
                            $updatedCount++;
                        } else {
                            $facilityNotAdded[] = $rowData;
                        }
                    } else {
                        $facilityNotAdded[] = $rowData;
                    }
                } elseif (empty($facilityCodeCheck) && empty($facilityCheck)) {
                    $result = $db->insert('facility_details', $data);
                    if ($result !== false) {
                        $insertedCount++;
                    } else {
                        $facilityNotAdded[] = $rowData;
                    }
                } else {
                    $facilityNotAdded[] = $rowData;
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
            $filename = TEMP_PATH . DIRECTORY_SEPARATOR . "INCORRECT-FACILITY-ROWS-" . DateUtility::getCurrentDateTime('Y-m-d-h-i-s') . "-" . $randomFileId . ".xlsx";
            $writer->save($filename);
        }


        $logMessage = sprintf(
            _translate('%s uploaded %d facilities in bulk (Inserted: %d, Updated: %d, Failed: %d)'),
            $_SESSION['userName'],
            $total,
            $insertedCount,
            $updatedCount,
            $notAdded
        );
        $_SESSION['alertMsg'] = $logMessage;
        $general->activityLog('bulk-upload-facility', $logMessage, 'facility');
    } else {
        throw new SystemException(_translate("Bulk Facility Import Failed") . " - " . $uploadedFile->getError());
    }
    header("Location:/facilities/upload-facilities.php?total=$total&notAdded=$notAdded&link=$filename&option=$uploadOption");
} catch (Exception $exc) {
    throw new SystemException(($exc->getMessage()));
}
