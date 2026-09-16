<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;
use App\Services\FacilityImportService;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilityImportService $importService */
$importService = ContainerRegistry::get(FacilityImportService::class);

// Bulk facility upload is shared under addFacility; only a user who can add
// facilities may reach this helper.
_requirePrivilege('/facilities/addFacility.php');

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$uploadPage = '/facilities/upload-facilities.php';
$action = $_POST['action'] ?? 'stage';
$batchId = (string) ($_POST['batchId'] ?? '');

$backWithAlert = function (string $message) use ($uploadPage): never {
    $_SESSION['alertMsg'] = $message;
    header("Location: $uploadPage");
    exit;
};

if ($action === 'discard') {
    $importService->discardBatch($batchId);
    $backWithAlert(_translate('Facility upload cancelled. Nothing was saved.'));
}

if ($action === 'confirm') {
    $batch = $importService->loadBatch($batchId);
    if ($batch === null) {
        $backWithAlert(_translate('This upload has expired or was already imported. Upload the file again.'));
    }
    // Remove first so a double submit cannot import the batch twice.
    $importService->discardBatch($batchId);

    $result = $importService->apply($batch, (array) ($_POST['rows'] ?? []));
    $failedToken = null;
    if ($result['failed'] !== []) {
        $failedFile = VAR_TEMP_PATH . DIRECTORY_SEPARATOR . 'INCORRECT-FACILITY-ROWS-' . DateUtility::getCurrentDateTime('Y-m-d-H-i-s') . '-' . MiscUtility::generateRandomString(8) . '.xlsx';
        $importService->writeRowsFile($result['failed'], $failedFile);
        $failedToken = _downloadToken($failedFile);
    }

    $logMessage = sprintf(
        _translate('%s uploaded facilities in bulk from %s (Added: %d, Updated: %d, Unchanged: %d, Left out: %d, Failed: %d)'),
        $_SESSION['userName'] ?? '',
        $batch['fileName'],
        $result['inserted'],
        $result['updated'],
        $result['unchanged'],
        $result['excluded'],
        count($result['failed'])
    );
    $general->activityLog('bulk-upload-facility', $logMessage, 'facility');

    $_SESSION['facilityImportResult'] = [
        'inserted' => $result['inserted'],
        'updated' => $result['updated'],
        'unchanged' => $result['unchanged'],
        'excluded' => $result['excluded'],
        'failed' => count($result['failed']),
        'failedToken' => $failedToken,
    ];
    header("Location: $uploadPage");
    exit;
}

// Stage: read and check the file, write nothing, show the review.
$uploadOption = $_POST['uploadOption'] ?? FacilityImportService::OPTION_DEFAULT;
if (!in_array($uploadOption, FacilityImportService::options(), true)) {
    $uploadOption = FacilityImportService::OPTION_DEFAULT;
}

/** @var UploadedFileInterface|null $uploadedFile */
$uploadedFile = $request->getUploadedFiles()['facilitiesInfo'] ?? null;
if (!$uploadedFile instanceof UploadedFileInterface || $uploadedFile->getError() !== UPLOAD_ERR_OK) {
    $backWithAlert(_translate('Please choose the Excel file to upload.'));
}
$clientName = basename((string) $uploadedFile->getClientFilename());
if (strtolower(pathinfo($clientName, PATHINFO_EXTENSION)) !== 'xlsx') {
    $backWithAlert(_translate('Please upload the facilities as an .xlsx file.'));
}

$stagingDir = VAR_TEMP_PATH . DIRECTORY_SEPARATOR . 'facility-import';
MiscUtility::makeDirectory($stagingDir);
$targetPath = $stagingDir . DIRECTORY_SEPARATOR . 'upload-' . MiscUtility::generateRandomString(16) . '.xlsx';

$stageError = null;
try {
    $uploadedFile->moveTo($targetPath);
    $batchId = $importService->stage($targetPath, $uploadOption, $clientName);
} catch (InvalidArgumentException $e) {
    $stageError = $e->getMessage();
} catch (Throwable $e) {
    LoggerUtility::logError('Bulk facility upload could not be read: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    $stageError = _translate('The file could not be read. Download the Excel format, fill it in and upload it again.');
} finally {
    if (is_file($targetPath)) {
        @unlink($targetPath);
    }
}

if ($stageError !== null) {
    $backWithAlert($stageError);
}
header("Location: $uploadPage?batch=" . urlencode($batchId));
