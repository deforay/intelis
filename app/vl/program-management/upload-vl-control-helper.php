<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\VlService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Services\InstrumentsService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$usersService = ContainerRegistry::get(UsersService::class);

/** @var InstrumentsService $instrumentsService */
$instrumentsService = ContainerRegistry::get(InstrumentsService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {

    $uploadedFiles = $request->getUploadedFiles();
    $uploadedFile = $uploadedFiles['controlInfo'];
    $fileName = $uploadedFile->getClientFilename();

    $labName = $_POST['labName'];
    $machineName = $_POST['machineName'];

    $ranNumber = "BULK-CONTROLS-" . MiscUtility::generateRandomString(16);
    $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
    $fileName = $ranNumber . "." . $extension;
    $allowedExtensions = ['xls', 'xlsx', 'csv'];

    MiscUtility::makeDirectory(TEMP_PATH);

    // Define the target path
    $targetPath = TEMP_PATH . DIRECTORY_SEPARATOR . $fileName;

    // Move the file
    $uploadedFile->moveTo($targetPath);

    $formatFilePath = WEB_ROOT . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'controls' . DIRECTORY_SEPARATOR . 'VL_Controls_Bulk_Upload_Excel_Format.xlsx';

    if (0 == $uploadedFile->getError()) {

        if (in_array($extension, $allowedExtensions)) {

            if (file_exists($targetPath) && file_exists($formatFilePath)) {
                $validate = $general->validateUploadedFile($targetPath, $formatFilePath);

                if ($validate) {

                    $spreadsheet = IOFactory::load($targetPath);
                    $sheetData = $spreadsheet->getActiveSheet();
                    $sheetData = $sheetData->toArray(null, true, true, true);

                    $filteredArray = array_filter(array_slice($sheetData, 1), fn($row): array => array_filter($row)); // Remove empty rows

                    $total = count($filteredArray);
                    $facilityNotAdded = [];

                    if ($total == 0) {
                        $_SESSION['alertMsg'] = _translate("Please enter all the mandatory fields in the excel sheet");
                        MiscUtility::redirect("/vl/program-management/upload-vl-control.php");
                        exit();
                    }

                    $userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
                    $userMapping = array_column($userResult, 'user_id', 'user_name');

                    foreach ($filteredArray as $rowIndex => $rowData) {

                        if (empty($rowData['A']) || empty($rowData['B']) || empty($rowData['F']) || empty($rowData['G']) || empty($rowData['H'])) {
                            $facilityNotAdded[] = $rowData;
                            continue;
                        }

                        try {

                            $controlCode = MiscUtility::generateULID();
                            $testedBy = in_array(trim((string) $rowData['E']), ['', '0'], true) ? (null) : $userMapping[trim((string) $rowData['E'])] ?? null;
                            $instrumentInfo = $instrumentsService->getSingleInstrument($machineName);
                            $importMachineFileName = $instrumentInfo['import_machine_file_name'] ?? '';

                            $params = [
                                'vlResult' => $rowData['H'],
                            ];
                            $processedResults = $vlService->processViralLoadResultFromForm($params);
                            $absVal = $processedResults['absVal'];
                            $logVal = $processedResults['logVal'];
                            $txtVal = $processedResults['txtVal'];
                            $absDecimalVal = $processedResults['absDecimalVal'];
                            $result = $processedResults['finalResult'];

                            $data = [
                                'control_code' => $controlCode,
                                'lab_id' => $labName,
                                'batch_id' => trim((string) $rowData['A']) ?? null,
                                'control_type' => trim((string) $rowData['B']) ?? null,
                                'lot_number' => trim((string) $rowData['C']) ?? null,
                                'lot_expiration_date' => trim((string) $rowData['D']) ?? null,
                                'tested_by' => ($testedBy),
                                'sample_tested_datetime' => empty($rowData['F']) ? null : DateUtility::isoDateFormat($rowData['F'], true),
                                'is_sample_rejected' => trim((string) $rowData['G']) ?? null,
                                'result_value_absolute' => $absVal,
                                'result_value_log' => $logVal,
                                'result_value_text' => $txtVal,
                                'result_value_absolute_decimal' => $absDecimalVal,
                                'result' => $result,
                                'import_machine_file_name' => $importMachineFileName
                            ];

                            $db->insert('vl_imported_controls', $data);
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

                        $spreadsheet = IOFactory::load(WEB_ROOT . '/files/controls/VL_Controls_Bulk_Upload_Excel_Format.xlsx');
                        $sheet = $spreadsheet->getActiveSheet();

                        foreach ($facilityNotAdded as $rowNo => $dataValue) {
                            $rRowCount = $rowNo + 2;
                            $sheet->fromArray($dataValue, null, 'A' . $rRowCount);
                        }

                        $writer = IOFactory::createWriter($spreadsheet, IOFactory::READER_XLSX);
                        $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'INCORRECT-CONTROLS-ROWS.xlsx';
                        $writer->save($filename);
                    }
                    $_SESSION['alertMsg'] = _translate("Controls added successfully");
                } else {
                    $_SESSION['alertMsg'] = _translate("Uploaded file column mismatched");
                    MiscUtility::redirect("/vl/program-management/upload-vl-control.php");
                    exit();
                }
            }
        } else {
            $_SESSION['alertMsg'] = _translate("Please Upload xls, xlsx, csv format only");
            MiscUtility::redirect("/vl/program-management/upload-vl-control.php");
            exit();
        }
    } else {
        throw new SystemException(_translate("Bulk Controls Import Failed") . " - " . $uploadedFile->getError());
    }
    MiscUtility::redirect("/vl/program-management/upload-vl-control.php?total=$total&notAdded=$notAdded&link=$filename");
} catch (Exception $exc) {
    throw new SystemException(($exc->getMessage()));
}
