<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\SpreadsheetUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use OpenSpout\Common\Entity\Row;
use App\Utilities\JsonUtility;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

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

            try {
                $filteredArray = SpreadsheetUtility::readRowsByColumnLetter($targetPath);
            } catch (InvalidArgumentException $notXlsx) {
                // Nothing imported yet, so send them back to upload it again
                $_SESSION['alertMsg'] = $notXlsx->getMessage();
                header("Location:/vl/requests/upload-storage.php");
                exit;
            }
            $total = count($filteredArray);
            $storageNotAdded = [];

            if ($total == 0) {
                $_SESSION['alertMsg'] = _translate("Please enter all the mandatory fields in the excel sheet");
                header("Location:/vl/requests/upload-storage.php");
                exit;
            }
            $failedRow = [];

            foreach ($filteredArray as $rowIndex => $rowData) {

                $formAttributes = null;
                $storageId = null;

                if (empty($rowData['A']) || empty($rowData['C']) || empty($rowData['D']) || empty($rowData['E']) || empty($rowData['F']) || empty($rowData['G'])) {
                    $storageNotAdded[] = $rowData;
                    $failedRow[] = [$rowData['A'], $rowData['C'], $rowData['D'], $rowData['E'], $rowData['F'], $rowData['G']];
                    continue;
                }

                $instanceId = '';
                if (isset($_SESSION['instanceId'])) {
                    $instanceId = $_SESSION['instanceId'];
                    $_POST['instanceId'] = $instanceId;
                }
                $db->where('sample_code', $rowData['A']);
                $getSample = $db->getOne('form_vl');

                if (empty($getSample['sample_code'])) {
                    $storageNotAdded[] = $rowData;
                    $failedRow[] = [$rowData['A'], $rowData['C'], $rowData['D'], $rowData['E'], $rowData['F'], $rowData['G']];
                    continue;
                }

                $db->where('storage_code', $rowData['C']);
                $freezerCheck = $db->getOne('lab_storage');

                if (empty($freezerCheck)) {
                    $data = ['storage_id' => MiscUtility::generateULID(), 'storage_code' => $rowData['C'], 'lab_id' => $getSample['lab_id'], 'storage_status' => "active", 'updated_datetime' => DateUtility::getCurrentDateTime()];
                    $db->insert('lab_storage', $data);
                    $storageId = $data['storage_id'];
                } else {
                    $storageId = $freezerCheck['storage_id'];
                }
                $formAttributes = json_decode((string) $getSample['form_attributes']) ?: new \stdClass();

                try {
                    if (!isset($formAttributes->storage) && empty($formAttributes->storage)) {
                        $formAttributes->storage = ["storageId" => $storageId, "storageCode" => $rowData['C'], "rack" => $rowData['D'], "box" => $rowData['E'], "position" => $rowData['F'], "volume" => $rowData['G']];
                        // Convert the $formAttributes array to a JSON string and set it using JsonUtility::jsonToSetString
                        $formAttributesString = JsonUtility::jsonToSetString(json_encode($formAttributes), 'form_attributes');
                        $vlData['form_attributes'] = $db->func($formAttributesString);
                        $db->where('vl_sample_id', $getSample['vl_sample_id']);
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
            $filename = '';
            if ($notAdded > 0) {
                $filename = TEMP_PATH . '/INCORRECT-STORAGE-ROWS-' . MiscUtility::generateRandomString(12) . '.xlsx';
                $writer = new \OpenSpout\Writer\XLSX\Writer();
                $writer->openToFile($filename);
                $writer->addRow(Row::fromValues(SpreadsheetUtility::readHeadingRow(WEB_ROOT . '/files/storage/storage-bulk-upload.xlsx')));
                foreach ($storageNotAdded as $rowData) {
                    $writer->addRow(Row::fromValues(array_values($rowData)));
                }
                $writer->close();
            }

            $_SESSION['alertMsg'] = _translate("Lab Storage added successfully");
        } else {
            throw new SystemException(_translate("Bulk Storage Import Failed") . " - " . $uploadedFile->getError());
        }
        $failedSampleCount = count($failedRow);
        $failedRow = http_build_query($failedRow);
        header("Location:/vl/requests/upload-storage.php?total=$total&notAdded=$notAdded" . '&link=' . ($filename === '' ? '' : rawurlencode(_downloadToken($filename))) . "&option=$uploadOption&failedRowCount=$failedSampleCount&$failedRow");
    } else {
        $_SESSION['alertMsg'] = _translate("Please Upload File");
        header("Location:/vl/requests/upload-storage.php");
    }
} catch (Throwable $exc) {
    throw new SystemException(($exc->getMessage()));
}
