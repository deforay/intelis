<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Utilities\JsonUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {

    $uploadedFiles = $request->getUploadedFiles();
    $uploadedFile = $uploadedFiles['storageInfo'];
    $fileName = $uploadedFile->getClientFilename();

    if (!empty($fileName)) {

        $uploadOption = $_POST['uploadOption'];

        $randomFileId = MiscUtility::generateRandomString(8);
        $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
        $fileName = "BULK-STORAGE-IMPORT-" . DateUtility::getCurrentDateTime('Y-m-d-h-i-s') . "-" . $randomFileId . "." . $extension;

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
            $storageNotAdded = [];

            if ($total == 0) {
                $_SESSION['alertMsg'] = _translate("Please enter all the mandatory fields in the excel sheet");
                header("Location:/vl/requests/upload-storage.php");
            }
            $failedRow = [];

            foreach ($filteredArray as $rowIndex => $rowData) {

                if (empty($rowData['A']) || empty($rowData['C']) || empty($rowData['D']) || empty($rowData['E']) || empty($rowData['F']) || empty($rowData['G'])) {
                    $_SESSION['alertMsg'] = _translate("Please enter all the mandatory fields in the excel sheet");
                    header("Location:/vl/requests/upload-storage.php");
                }

                $instanceId = '';
                if (isset($_SESSION['instanceId'])) {
                    $instanceId = $_SESSION['instanceId'];
                    $_POST['instanceId'] = $instanceId;
                }
                $condition = "sample_code = '" . $rowData['A'] . "'";
                $condition1 = "storage_code = '" . $rowData['C'] . "'";

                $getSample = $general->fetchDataFromTable('form_vl', $condition);


                if (isset($getSample[0]['sample_code']) && $getSample[0]['sample_code'] != "") {
                    $freezerCheck = $general->fetchDataFromTable('lab_storage', $condition1);

                    if (empty($freezerCheck)) {
                        $data = ['storage_id' => MiscUtility::generateULID(), 'storage_code' => $rowData['C'], 'lab_id' => $getSample[0]['lab_id'], 'storage_status' => "active", 'updated_datetime' => DateUtility::getCurrentDateTime()];
                        $db->insert('lab_storage', $data);
                        $storageId = $data['storage_id'];
                    } else {
                        $storageId = $freezerCheck[0]['storage_id'];
                    }
                    $formAttributes = json_decode((string) $getSample[0]['form_attributes']);
                }

                try {
                    if (!isset($formAttributes->storage) && empty($formAttributes->storage)) {
                        $formAttributes->storage = ["storageId" => $storageId, "storageCode" => $rowData['C'], "rack" => $rowData['D'], "box" => $rowData['E'], "position" => $rowData['F'], "volume" => $rowData['G']];
                        // Convert the $formAttributes array to a JSON string and set it using JsonUtility::jsonToSetString
                        $formAttributesString = JsonUtility::jsonToSetString(json_encode($formAttributes), 'form_attributes');
                        $vlData['form_attributes'] = $db->func($formAttributesString);
                        $db->where('vl_sample_id', $getSample[0]['vl_sample_id']);
                        $id = $db->update('form_vl', $vlData);
                    } else {
                        $storageNotAdded[] = $rowData;
                        $failedRow[] = [$rowData['A'], $rowData['C'], $rowData['D'], $rowData['E'], $rowData['F'], $rowData['G']];
                    }
                } catch (Throwable) {
                    $storageNotAdded[] = $rowData;
                    $failedRow[] = [$rowData['A'], $rowData['C'], $rowData['D'], $rowData['E'], $rowData['F'], $rowData['G']];
                    LoggerUtility::logError(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
                    LoggerUtility::logError(__FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
                }
            }

            $notAdded = count($storageNotAdded);
            if ($notAdded > 0) {

                $spreadsheet = IOFactory::load(WEB_ROOT . '/files/storage/storage-bulk-upload.xlsx');
                $sheet = $spreadsheet->getActiveSheet();

                foreach ($storageNotAdded as $rowNo => $dataValue) {
                    $rRowCount = $rowNo + 2;
                    $sheet->fromArray($dataValue, null, 'A' . $rRowCount);
                }

                $writer = IOFactory::createWriter($spreadsheet, IOFactory::READER_XLSX);
                $filename = TEMP_PATH . DIRECTORY_SEPARATOR . "INCORRECT-STORAGE-ROWS-" . DateUtility::getCurrentDateTime('Y-m-d-h-i-s') . "-" . $randomFileId . ".xlsx";
                $writer->save($filename);
            }

            $_SESSION['alertMsg'] = _translate("Lab Storage added successfully");
        } else {
            throw new SystemException(_translate("Bulk Storage Import Failed") . " - " . $uploadedFile->getError());
        }
        $failedSampleCount = count($failedRow);
        $failedRow = http_build_query($failedRow);
        header("Location:/vl/requests/upload-storage.php?total=$total&notAdded=$notAdded&link=$filename&option=$uploadOption&failedRowCount=$failedSampleCount&$failedRow");
    } else {
        $_SESSION['alertMsg'] = _translate("Please Upload File");
        header("Location:/vl/requests/upload-storage.php");
    }
} catch (Throwable $exc) {
    throw new SystemException(($exc->getMessage()));
}
