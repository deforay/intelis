<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = "temp_sample_import";
try {
    $result = 0;

    // Handle bulk sample test date updates
    if (isset($_POST['bulkSampleTestDate']) && isset($_POST['tempSampleIds'])) {
        $sampleTestDate = $_POST['bulkSampleTestDate'];
        $tempSampleIds = explode(',', (string) $_POST['tempSampleIds']);

        // Validate all IDs are numeric to prevent injection
        $validIds = array_filter($tempSampleIds, fn($id): bool => is_numeric(trim((string) $id)));

        if ($validIds !== []) {
            $placeholders = str_repeat('?,', count($validIds) - 1) . '?';
            $updateQuery = "UPDATE temp_sample_import SET sample_tested_datetime = ? WHERE temp_sample_id IN ($placeholders)";

            $params = array_merge([$sampleTestDate], $validIds);
            $result = $db->rawQuery($updateQuery, $params);

            if ($result !== false && $db->getLastErrno() === 0) {
                echo "1"; // Success
            } else {
                echo "0"; // Failed
            }
        } else {
            echo "0"; // No valid IDs
        }
        exit;
    }



    // Handle clearing sample test date
    if (isset($_POST['clearSampleTestDate']) && isset($_POST['tempsampleId'])) {
        $tempSampleId = $_POST['tempsampleId'];

        $updateQuery = "UPDATE temp_sample_import SET sample_tested_datetime = NULL WHERE temp_sample_id = ?";
        $result = $db->rawQuery($updateQuery, [$tempSampleId]);

        if ($result !== false && $db->getLastErrno() === 0) {
            echo "1"; // Success
        } else {
            echo "0"; // Failed
        }
        exit;
    }

    // Handle single sample test date update
    if (isset($_POST['sampleTestDate']) && isset($_POST['tempsampleId'])) {
        $sampleTestDate = $_POST['sampleTestDate'];
        $tempSampleId = $_POST['tempsampleId'];

        $updateQuery = "UPDATE temp_sample_import SET sample_tested_datetime = ? WHERE temp_sample_id = ?";
        $result = $db->rawQuery($updateQuery, [$sampleTestDate, $tempSampleId]);

        if ($result !== false && $db->getLastErrno() === 0) {
            echo "1"; // Success
        } else {
            echo "0"; // Failed
        }
        exit;
    }

    // Handle sample code update
    if (isset($_POST['sampleCode']) && trim((string) $_POST['sampleCode']) !== '') {
        $sampleResult = $db->rawQuery("SELECT sample_code FROM form_vl WHERE sample_code='" . trim((string) $_POST['sampleCode']) . "'");
        $sampleDetails = empty($sampleResult) ? _translate('New Sample') : _translate('Result already exists');
        $db->where('temp_sample_id', $_POST['tempsampleId']);
        $result = $db->update($tableName, ['sample_code' => $_POST['sampleCode'], 'sample_details' => $sampleDetails]);
    } elseif (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) !== '') {
        $sampleControlResult = $db->rawQuery("SELECT r_sample_control_name from r_sample_controls where r_sample_control_name='" . trim((string) $_POST['sampleType']) . "'");
        $db->where('temp_sample_id', $_POST['tempsampleId']);
        $result = $db->update($tableName, ['sample_type' => trim((string) $_POST['sampleType'])]);
    }

    echo $result;
} catch (Exception $e) {
    LoggerUtility::log("error", $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    echo "0"; // Return failure on exception
}
