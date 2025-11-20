<?php

use const COUNTRY\CAMEROON;
use Slim\Psr7\UploadedFile;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;
use App\Utilities\ImageResizeUtility;
use Psr\Http\Message\ServerRequestInterface;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

// Get the uploaded files from the request object
$uploadedFiles = $request->getUploadedFiles();
$sanitizedInstanceLogo = $sanitizedLogo = $sanitizedReportTemplate = null;
if (isset($_FILES['reportFormat']['name']) && isset($uploadedFiles['reportFormat']['report_template'])) {
    $sanitizedReportTemplate = _sanitizeFiles($uploadedFiles['reportFormat']['report_template'], ['pdf']);
}
if (isset($_FILES['instanceLogo']) && isset($uploadedFiles['instanceLogo'])) {
    $sanitizedInstanceLogo = _sanitizeFiles($uploadedFiles['instanceLogo'], ['png', 'jpg', 'jpeg', 'gif']);
}
if (isset($_FILES['logo']) && empty($_FILES['logo']) && isset($uploadedFiles['logo']) && !empty($uploadedFiles['logo'])) {
    $sanitizedLogo = _sanitizeFiles($uploadedFiles['logo'], ['png', 'jpg', 'jpeg', 'gif']);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$instanceTableName = "s_vlsm_instance";


/** @var SystemService $systemService */
$systemService = ContainerRegistry::get(SystemService::class);

$currentDateTime = DateUtility::getCurrentDateTime();

// unset global config cache so that it can be reloaded with new values
// this is set in CommonService::getGlobalConfig()
(ContainerRegistry::get(FileCacheUtility::class))->delete('app_global_config');


try {


    $removedImage = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $_POST['removedLogoImage']);
    if (isset($_POST['removedLogoImage']) && trim((string) $_POST['removedLogoImage']) !== "" && !($removedImage === '' || $removedImage === '0' || $removedImage === false) && file_exists($removedImage)) {
        MiscUtility::deleteFile($removedImage);
        $data = ['value' => null];
        $db->where('name', 'logo');
        $id = $db->update("global_config", $data);
        if ($id) {
            $db->where('name', 'logo');
            $db->update("global_config", [
                "updated_datetime" => $currentDateTime,
                "updated_by" => $_SESSION['userId']
            ]);
        }
    }


    if ($sanitizedLogo instanceof UploadedFile && $sanitizedLogo->getError() === UPLOAD_ERR_OK) {
        $logoImagePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo";
        MiscUtility::makeDirectory($logoImagePath);
        $extension = MiscUtility::getFileExtension($sanitizedLogo->getClientFilename());
        $string = MiscUtility::generateRandomString(12) . ".";
        $imageName = "logo-" . $string . $extension;
        $imagePath = realpath($logoImagePath) . DIRECTORY_SEPARATOR . $imageName;

        // Move the uploaded file to the desired location
        $sanitizedLogo->moveTo($imagePath);

        // Resize the image
        $resizeObj = new ImageResizeUtility($imagePath);
        if ($_POST['vl_form'] == CAMEROON) {
            [$width, $height] = getimagesize($imagePath);
            if ($width > 240) {
                $resizeObj->resizeToBestFit(240, 80);
            }
        } else {
            $resizeObj->resizeToWidth(100);
        }
        $resizeObj->save($imagePath);


        // Update the database with the image name
        $db->where('name', 'logo');
        $db->update("global_config", [
            "value" => $imageName,
            "updated_datetime" => $currentDateTime,
            "updated_by" => $_SESSION['userId']
        ]);
    }
    // if (!isset($_POST['r_mandatory_fields'])) {
    //     $data = ['value' => null];
    //     $db->where('name', 'r_mandatory_fields');
    //     $id = $db->update("global_config", $data);
    //     if ($id) {
    //         $db->where('name', 'r_mandatory_fields');
    //         $db->update("global_config", [
    //             "updated_datetime" => $currentDateTime,
    //             "updated_by" => $_SESSION['userId']
    //         ]);
    //     }
    // }
    if (
        (isset($_POST['reportFormat']['test_type']) && !empty($_POST['reportFormat']['test_type'])) ||
        (isset($_POST['reportFormat']['old_template']) && !empty($_POST['reportFormat']['old_template']))
        || isset($_FILES['reportFormat']['name'])
    ) {
        $fileResponse = [];
        $directories = [UPLOAD_PATH, 'labs', 'report-template'];
        $currentPath = '';
        foreach ($directories as $directory) {
            $currentPath = $currentPath === '' ? $directory : $currentPath . DIRECTORY_SEPARATOR . $directory;
            MiscUtility::makeDirectory($currentPath, 0777); // will just skip if exists
        }
        if (isset($_POST['reportFormat']['deleteTemplate']) && !empty($_POST['reportFormat']['deleteTemplate'])) {
            foreach (explode(',', (string) $_POST['reportFormat']['deleteTemplate']) as $testToRemove)
                MiscUtility::removeDirectory(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $testToRemove);
        }
        foreach ($_POST['reportFormat']['test_type'] as $key => $test) {
            $sanitizedReportTemplate = _sanitizeFiles($uploadedFiles['reportFormat']['report_template'][$key], ['pdf']);

            if (isset($uploadedFiles['reportFormat']['report_template'][$key]) && $sanitizedReportTemplate instanceof UploadedFile && $sanitizedReportTemplate->getError() === UPLOAD_ERR_OK) {
                $directoryPath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $test;
                MiscUtility::makeDirectory($directoryPath, 0777, true);
                $extension = MiscUtility::getFileExtension($sanitizedReportTemplate->getClientFilename());
                $fileName = "default." . $extension;
                $filePath = $directoryPath . DIRECTORY_SEPARATOR . $fileName;
                $fileResponse[$test]['file'] = $fileName;
                $fileResponse[$test]['mtop'] = $_POST['reportFormat']['header_margin'][$key];

                // Move the uploaded file to the desired location
                $sanitizedReportTemplate->moveTo($filePath);
            } else {
                $fileResponse[$test]['file'] = $_POST['reportFormat']['old_template'][$key];
                $fileResponse[$test]['mtop'] = $_POST['reportFormat']['header_margin'][$key];
            }
        }
        // $reportFormatJson = JsonUtility::jsonToSetString(json_encode($fileResponse), 'value');
        $updateData = ['value' => json_encode($fileResponse)];
        $db->where('name', 'report_format');
        $db->update('global_config', $updateData);
    }

    unset($_POST['reportFormat']);
    unset($_SESSION['APP_LOCALE']);
    foreach ($_POST as $fieldName => $fieldValue) {
        if ($fieldName != 'removedLogoImage') {
            if ($fieldName == 'r_mandatory_fields') {
                $fieldValue = implode(',', $fieldValue);
            }
            $data = ['value' => $fieldValue];
            $db->where('name', $fieldName);
            $id = $db->update("global_config", $data);
            if ($id) {
                $db->where('name', $fieldName);
                $db->update("global_config", [
                    "updated_datetime" => $currentDateTime,
                    "updated_by" => $_SESSION['userId']
                ]);
            }
        }
        $barcode = $_POST['bar_code_printing'];
        $message = $_POST['contentFormat'] ?? null;
        if ($barcode == "zebra-printer") {
            $content = "let zebraFormat = `$message`;";
            $fileName = "zebra-format.js";
        } elseif ($barcode == "dymo-labelwriter-450") {
            $content = "let dymoFormat = `$message`;";
            $fileName = "dymo-format.js";
        }

        $path = 'public/uploads' . DIRECTORY_SEPARATOR . 'barcode-formats';

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }
        if (isset($fileName) && !empty($fileName)) {
            file_put_contents($path . DIRECTORY_SEPARATOR . $fileName, $content);
        }
    }

    $dateFormat = $_POST['gui_date_format'] ?? 'd-M-Y';
    $systemService->setGlobalDateFormat($dateFormat);


    /* For Lock approve sample updates */

    if (isset($_POST['vl_monthly_target']) && trim((string) $_POST['vl_monthly_target']) !== "") {
        $data = ['value' => trim((string) $_POST['vl_monthly_target'])];
        $db->where('name', 'vl_monthly_target');
        $id = $db->update("global_config", $data);
    }
    if (isset($_POST['vl_suppression_target']) && trim((string) $_POST['vl_suppression_target']) !== "") {
        $data = ['value' => trim((string) $_POST['vl_suppression_target'])];
        $db->where('name', 'vl_suppression_target');
        $id = $db->update("global_config", $data);
    }

    if (isset($_POST['covid19PositiveConfirmatoryTestsRequiredByCentralLab']) && trim((string) $_POST['covid19PositiveConfirmatoryTestsRequiredByCentralLab']) !== "") {
        $data = ['value' => trim((string) $_POST['covid19PositiveConfirmatoryTestsRequiredByCentralLab'])];
        $db->where('name', 'covid19_positive_confirmatory_tests_required_by_central_lab');
        $id = $db->update("global_config", $data);
        if ($id) {
            $db->where('name', 'logo');
            $db->update("global_config", ["updated_datetime" => $currentDateTime, "updated_by" => $_SESSION['userId']]);
        }
    }

    $_SESSION['alertMsg'] = _translate("Configuration updated successfully");

    //Add event log
    $eventType = 'general-config-update';
    $action = $_SESSION['userName'] . ' updated general config';
    $resource = 'general-config';

    $general->activityLog($eventType, $action, $resource);
    header("Location:/global-config/editGlobalConfig.php");
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
