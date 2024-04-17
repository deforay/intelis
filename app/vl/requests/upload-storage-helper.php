<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {

    $uploadedFiles = $request->getUploadedFiles();
    $uploadedFile = $uploadedFiles['storageInfo'];
    $fileName = $uploadedFile->getClientFilename();

    $uploadOption = $_POST['uploadOption'];

    $ranNumber = "BULK-LAB-STORAGE-" . strtoupper($general->generateRandomString(16));
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
        $filteredArray = array_filter($resultArray, function ($row) {
            return array_filter($row); // Remove empty rows
        });
        $total = count($filteredArray);
        $storageNotAdded = [];

        if ($total == 0) {
            $_SESSION['alertMsg'] = _translate("Please enter all the mandatory fields in the excel sheet");
            header("Location:/vl/requests/upload-storage.php");
        }

        foreach ($filteredArray as $rowIndex => $rowData) {

            if (empty($rowData['A']) || empty($rowData['B']) || empty($rowData['C']) || empty($rowData['D'])) {
                $_SESSION['alertMsg'] = _translate("Please enter all the mandatory fields in the excel sheet");
                header("Location:/vl/requests/upload-storage.php");
            }

            $instanceId = '';
            if (isset($_SESSION['instanceId'])) {
                $instanceId = $_SESSION['instanceId'];
                $_POST['instanceId'] = $instanceId;
            }
            $getSample = $general->getDataFromOneFieldAndValue('form_vl', 'sample_code', $rowData['A']);
            $freezerCheck = $general->getDataFromOneFieldAndValue('lab_storage', 'storage_code', $rowData['B']);

            if (empty($freezerCheck)) {
                $data = array(
                    'storage_id' => $general->generateUUID(),
                    'storage_code'     => $rowData['B'],
                    'lab_id'     => $getSample['lab_id'],
                    'storage_status' => "active",
                    'updated_datetime'    => DateUtility::getCurrentDateTime()
                );
                $db->insert('lab_storage', $data);
                $storageId = $data['storage_id'];
            } else {
                $storageId = $freezerCheck['storage_id'];
            }

            $formAttributes = json_decode($getSample['form_attributes']);


            try {
                if (!isset($formAttributes->storage) && empty($formAttributes->storage)) {
                    $formAttributes->storage = array("storageId" => $storageId, "storageCode" => $rowData['B'], "rack" => $rowData['C'], "box" => $rowData['D'], "position" => $rowData['E'], "volume" => $rowData['F']);
                    $vlData['form_attributes'] = json_encode($formAttributes);
                    $db->where('vl_sample_id', $getSample['vl_sample_id']);
                    $id = $db->update('form_vl', $vlData);
                } else {
                    $storageNotAdded[] = $rowData;
                }
            } catch (Throwable $e) {
                $storageNotAdded[] = $rowData;
                error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastError());
                error_log(__FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
            }
        }

        $notAdded = count($storageNotAdded);
        if ($notAdded > 0) {

            $spreadsheet = IOFactory::load(WEB_ROOT . '/files/storages/Storage_Bulk_Upload_Excel_Format.xlsx');

            $sheet = $spreadsheet->getActiveSheet();

            foreach ($storageNotAdded as $rowNo => $dataValue) {
                $rRowCount = $rowNo + 2;
                $sheet->fromArray($dataValue, null, 'A' . $rRowCount);
            }

            $writer = IOFactory::createWriter($spreadsheet, IOFactory::READER_XLSX);
            $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'INCORRECT-STORAGE-ROWS.xlsx';
            $writer->save($filename);
        }

        $_SESSION['alertMsg'] = _translate("Lab Storage added successfully");
    } else {
        throw new SystemException(_translate("Bulk Storage Import Failed") . " - " . $uploadedFile->getError());
    }
    header("Location:/vl/requests/upload-storage.php?total=$total&notAdded=$notAdded&link=$filename&option=$uploadOption");
} catch (Exception $exc) {
    throw new SystemException(($exc->getMessage()));
}
